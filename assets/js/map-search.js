document.addEventListener('DOMContentLoaded', function () {
  var mapEl = document.getElementById('map-search');
  if (!mapEl) return;

  var endpoint = mapEl.getAttribute('data-endpoint');
  var categorySelect = document.getElementById('map-filter-category');
  var corridorSelect = document.getElementById('map-filter-corridor');
  var list = document.querySelector('[data-map-list]');
  var count = document.querySelector('[data-map-count]');
  var countLabel = document.querySelector('[data-map-count-label]');

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  var map = null;
  var markers = null;
  if (typeof L !== 'undefined') {
    map = L.map(mapEl).setView([28.4595, 77.0266], 11); // Gurgaon centroid
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 18,
    }).addTo(map);
    markers = L.layerGroup().addTo(map);
  } else {
    mapEl.hidden = true; // map library unavailable — the list below still has every listing
  }

  // Filters from the Home Finder (sector[], bhk[], price range) stay applied; the two selects override their own fields.
  function params() {
    var p = new URLSearchParams(location.search);
    p.delete('category');
    p.delete('corridor_id');
    if (categorySelect.value) p.set('category', categorySelect.value);
    if (corridorSelect.value) p.set('corridor_id', corridorSelect.value);
    return p;
  }

  function renderList(items) {
    if (!list) return;
    list.textContent = '';
    items.forEach(function (p) {
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.href = p.url;
      a.textContent = p.name;
      var meta = document.createElement('span');
      meta.textContent = p.sector + ' · ' + p.category + ' · ' + p.price;
      li.appendChild(a);
      li.appendChild(meta);
      if (!p.unmapped && p.exact === false) {
        var approx = document.createElement('span');
        approx.className = 'map-list-approx';
        approx.textContent = 'Approximate location (corridor)';
        li.appendChild(approx);
      }
      if (p.unmapped) {
        var note = document.createElement('span');
        note.className = 'map-list-nopin';
        note.textContent = 'Not on map yet';
        li.appendChild(note);
      }
      list.appendChild(li);
    });
    if (count) count.textContent = String(items.length);
    if (countLabel) countLabel.textContent = items.length === 1 ? 'property' : 'properties';
    var empty = document.querySelector('[data-map-empty]');
    if (empty) empty.hidden = items.length > 0;
  }

  function load(updateUrl) {
    var p = params();
    if (updateUrl) history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p.toString() : ''));
    p.set('with_list', '1');
    fetch(endpoint + '?' + p.toString())
      .then(function (res) { return res.json(); })
      .then(function (data) {
        var points = data.points || [];
        var unmapped = (data.unmapped || []).map(function (u) { u.unmapped = true; return u; });
        renderList(points.concat(unmapped));
        if (!map) return;
        markers.clearLayers();
        var bounds = [];
        points.forEach(function (p) {
          var marker = L.marker([p.lat, p.lng], { title: p.name, alt: p.name + (p.exact ? '' : ' (approximate location)') }).addTo(markers);
          marker.bindPopup(
            '<div class="map-popup-card"><strong>' + escapeHtml(p.name) + '</strong><br>' +
            escapeHtml(p.sector) + ' &middot; ' + escapeHtml(p.category) + '<br>' +
            '<span>' + escapeHtml(p.price) + '</span><br>' +
            '<em class="map-popup-loc">' + (p.exact ? 'Exact location' : 'Approximate location (corridor)') + '</em><br>' +
            '<a href="' + escapeHtml(p.url) + '">View Details →</a></div>'
          );
          bounds.push([p.lat, p.lng]);
        });
        if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
      });
  }

  categorySelect.addEventListener('change', function () { load(true); });
  corridorSelect.addEventListener('change', function () { load(true); });
  load(false);
});

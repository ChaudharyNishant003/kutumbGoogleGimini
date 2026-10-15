/* Neighbourhood (Feature 4): tabs, on-demand Leaflet map, and distance questions. Text via textContent only. */
(function () {
  'use strict';

  var LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
  var LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

  function loadLeaflet() {
    if (window.L) return Promise.resolve(window.L);
    return new Promise(function (resolve, reject) {
      var css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = LEAFLET_CSS;
      document.head.appendChild(css);
      var s = document.createElement('script');
      s.src = LEAFLET_JS;
      s.onload = function () { resolve(window.L); };
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }

  document.querySelectorAll('[data-nb]').forEach(function (nb) {
    var data = {};
    try { data = JSON.parse(nb.querySelector('[data-nb-data]').textContent); } catch (e) { data = { places: [] }; }
    var tabs = Array.prototype.slice.call(nb.querySelectorAll('[data-nb-tab]'));
    var mapWrap = nb.querySelector('[data-nb-map]');
    var mapToggle = nb.querySelector('[data-nb-map-toggle]');
    var map = null;
    var markers = [];
    var centerMarker = null;

    function showTab(key, focus) {
      tabs.forEach(function (t) {
        var on = t.getAttribute('data-nb-tab') === key;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        if (on && focus) t.focus();
      });
      nb.querySelectorAll('[data-nb-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-nb-panel') !== key; });
      if (map) highlight(key);
    }

    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { showTab(t.getAttribute('data-nb-tab')); });
      t.addEventListener('keydown', function (e) {
        var next = null;
        if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
        else if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
        else if (e.key === 'Home') next = tabs[0];
        else if (e.key === 'End') next = tabs[tabs.length - 1];
        if (next) { e.preventDefault(); showTab(next.getAttribute('data-nb-tab'), true); }
      });
    });

    function activeKey() {
      var t = tabs.filter(function (x) { return x.getAttribute('aria-selected') === 'true'; })[0];
      return t ? t.getAttribute('data-nb-tab') : null;
    }

    function highlight(key) {
      var group = [];
      markers.forEach(function (m) {
        var on = m.options.cat === key;
        m.setOpacity(on ? 1 : 0.35);
        if (on) group.push(m.getLatLng());
      });
      if (centerMarker) group.push(centerMarker.getLatLng());
      if (group.length > 1) map.fitBounds(group, { padding: [30, 30], maxZoom: 15 });
    }

    function buildMap(L) {
      var canvas = nb.querySelector('[data-nb-map-canvas]');
      map = L.map(canvas, { scrollWheelZoom: false }).setView([data.center.lat, data.center.lng], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
      centerMarker = L.marker([data.center.lat, data.center.lng], { title: data.center.name, alt: data.center.name + (data.center.approximate ? ' (approximate)' : '') })
        .addTo(map).bindPopup(data.center.name);
      (data.places || []).forEach(function (p) {
        var m = L.marker([p.lat, p.lng], { title: p.name, alt: p.name + ', ' + p.km + ' km', cat: p.cat })
          .addTo(map).bindPopup(p.name + ' — ' + p.km + ' km');
        markers.push(m);
      });
      highlight(activeKey());
    }

    mapToggle.addEventListener('click', function () {
      var open = mapWrap.hidden;
      mapWrap.hidden = !open;
      mapToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      mapToggle.textContent = open ? 'Hide map' : 'View on map';
      if (open && !map) {
        mapToggle.disabled = true;
        loadLeaflet().then(function (L) {
          mapToggle.disabled = false;
          buildMap(L);
        }).catch(function () {
          mapToggle.disabled = false;
          mapWrap.hidden = true;
          mapToggle.textContent = 'Map unavailable — see the list above';
          mapToggle.setAttribute('aria-expanded', 'false');
        });
      } else if (open && map) {
        setTimeout(function () { map.invalidateSize(); }, 50);
      }
    });

    var jump = nb.querySelector('[data-nb-jump]');
    if (jump) {
      jump.addEventListener('change', function () {
        if (!map) return;
        var v = jump.value;
        var target = v === 'center' ? centerMarker : markers[parseInt(v, 10)];
        if (target) { map.setView(target.getLatLng(), 15); target.openPopup(); }
      });
    }

    var srcBtn = nb.querySelector('[data-nb-sources]');
    srcBtn.addEventListener('click', function () {
      var panel = nb.querySelector('[data-nb-sources-panel]');
      panel.hidden = !panel.hidden;
      srcBtn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
    });

    var form = nb.querySelector('[data-nb-form]');
    var answer = nb.querySelector('[data-nb-answer]');
    if (!form) return;
    var busy = false;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = form.querySelector('input');
      var q = input.value.trim();
      if (!q || busy) return;
      busy = true;
      form.classList.add('is-busy');
      answer.hidden = false;
      answer.className = 'ai-nb-answer';
      answer.textContent = 'Checking our location list…';
      fetch('/api/ai-neighborhood.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ property_id: parseInt(nb.getAttribute('data-property-id'), 10), question: q })
      }).then(function (r) { return r.json(); }).then(function (res) {
        answer.textContent = '';
        if (!res || res.ok === false) {
          answer.textContent = (res && res.message) || 'Our assistant is unavailable right now — the list below is still up to date.';
          if (res && res.whatsapp) {
            answer.appendChild(document.createTextNode(' '));
            var wa = el('a', null, 'WhatsApp us');
            wa.href = res.whatsapp; wa.target = '_blank'; wa.rel = 'noopener';
            answer.appendChild(wa);
          }
          return;
        }
        if (res.kind === 'refusal') answer.classList.add('is-refusal');
        answer.appendChild(el('p', 'ai-answer-title', res.title));
        if (res.text) answer.appendChild(el('p', null, res.text));
        if (res.items && res.items.length) {
          var ul = el('ul');
          res.items.forEach(function (it) {
            var li = el('li');
            li.appendChild(el('strong', null, it.name));
            li.appendChild(document.createTextNode(' — ' + it.km.toFixed(1) + ' km (straight-line) '));
            li.appendChild(el('span', 'ai-source', 'Verified ' + it.verified + ' · ' + it.source));
            ul.appendChild(li);
          });
          answer.appendChild(ul);
        }
        if (res.note) answer.appendChild(el('p', 'ai-answer-caveat', res.note));
      }).catch(function () {
        answer.textContent = 'Our assistant is unavailable right now — the list below is still up to date.';
      }).finally(function () {
        busy = false;
        form.classList.remove('is-busy');
      });
    });
  });
})();

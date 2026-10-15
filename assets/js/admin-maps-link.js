/* Fills lat/lng fields from a pasted Google Maps link or "lat, lng" text. No network calls. */
(function () {
  'use strict';

  function valid(lat, lng) {
    return isFinite(lat) && isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 && !(lat === 0 && lng === 0);
  }

  function parse(text) {
    var t = decodeURIComponent(String(text || '').trim());
    var m;
    // Place links carry the pin itself as !3d<lat>!4d<lng> — more exact than the @ viewport centre.
    if ((m = t.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/))) return [parseFloat(m[1]), parseFloat(m[2])];
    if ((m = t.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/))) return [parseFloat(m[1]), parseFloat(m[2])];
    if ((m = t.match(/[?&](?:q|query|ll|destination|center)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/))) return [parseFloat(m[1]), parseFloat(m[2])];
    if ((m = t.match(/^\s*(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)\s*$/))) return [parseFloat(m[1]), parseFloat(m[2])];
    return null;
  }

  document.querySelectorAll('[data-maps-link]').forEach(function (box) {
    var input = box.querySelector('input');
    var status = box.querySelector('[data-maps-status]');
    var lat = document.querySelector(box.getAttribute('data-lat-target'));
    var lng = document.querySelector(box.getAttribute('data-lng-target'));
    if (!input || !lat || !lng) return;

    function apply() {
      var raw = input.value.trim();
      if (!raw) { status.textContent = ''; return; }
      var ll = parse(raw);
      if (ll && valid(ll[0], ll[1])) {
        lat.value = ll[0].toFixed(7);
        lng.value = ll[1].toFixed(7);
        status.textContent = 'Location filled: ' + lat.value + ', ' + lng.value;
        status.className = 'maps-link-status ok';
      } else if (/goo\.gl|maps\.app/.test(raw)) {
        status.textContent = 'Short links can’t be read. Open the link, then copy the full address from the browser bar — or right-click the pin in Google Maps and click the numbers to copy them.';
        status.className = 'maps-link-status warn';
      } else {
        status.textContent = 'No location found in that text. Paste a full Google Maps link, or coordinates like 28.4595, 77.0266.';
        status.className = 'maps-link-status warn';
      }
    }
    input.addEventListener('input', apply);
    input.addEventListener('paste', function () { setTimeout(apply, 0); });
  });
})();

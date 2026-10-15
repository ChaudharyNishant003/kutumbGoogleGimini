/* AI Home Finder (Feature 2). All server/model text is inserted with textContent only. */
(function () {
  'use strict';

  var ENDPOINT = '/api/ai-home-finder.php';

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }

  function toQueryString(q) {
    var parts = [];
    Object.keys(q || {}).forEach(function (k) {
      var v = q[k];
      if (Array.isArray(v)) v.forEach(function (x) { parts.push(encodeURIComponent(k + '[]') + '=' + encodeURIComponent(x)); });
      else parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
    });
    return parts.length ? '?' + parts.join('&') : '';
  }

  function post(payload) {
    return fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, message: 'Something went wrong — please use the regular filters.' }; });
    });
  }

  document.querySelectorAll('[data-finder]').forEach(function (finder) {
    var form = finder.querySelector('[data-finder-form]');
    var input = finder.querySelector('[data-finder-input]');
    var goBtn = form.querySelector('.ai-finder-go');
    var msg = finder.querySelector('[data-finder-msg]');
    var aiOff = finder.hasAttribute('data-ai-off');
    var current = null; // last payload
    var targetPath = finder.getAttribute('data-target-path') || '/properties.php';
    var targetAnchor = finder.getAttribute('data-target-anchor') || '';
    var busy = false;

    function setState(s) {
      finder.setAttribute('data-state', s);
    }

    function setMsg(text, link) {
      msg.textContent = text || '';
      if (link) {
        msg.appendChild(document.createTextNode(' '));
        var a = el('a', null, link.text);
        a.href = link.href;
        if (link.external) { a.target = '_blank'; a.rel = 'noopener'; }
        msg.appendChild(a);
      }
    }

    function setBusy(on, label) {
      busy = on;
      finder.classList.toggle('is-busy', on);
      goBtn.setAttribute('aria-busy', on ? 'true' : 'false');
      goBtn.disabled = on;
      if (on) setMsg(label || 'Reading your requirements…');
    }

    function chipNode(chip) {
      var span = el('span', 'ai-chip ' + (chip.type === 'pref' ? 'ai-chip--pref' : 'ai-chip--req'), chip.label);
      var x = el('a', 'ai-chip-x', '×');
      x.href = targetPath + toQueryString(chip.query);
      x.setAttribute('aria-label', 'Remove ' + chip.label);
      x.setAttribute('data-chip-query', JSON.stringify(chip.query || {}));
      span.appendChild(x);
      return span;
    }

    function render(p) {
      current = p;
      var req = finder.querySelector('[data-chips="req"]');
      var pref = finder.querySelector('[data-chips="pref"]');
      req.textContent = '';
      pref.textContent = '';
      if (p.city) {
        var city = el('span', 'ai-chip ai-chip--info', p.city);
        city.title = 'All our listings are in Gurgaon';
        req.appendChild(city);
      }
      (p.chips || []).forEach(function (c) { (c.type === 'pref' ? pref : req).appendChild(chipNode(c)); });
      finder.querySelector('[data-pref-group]').hidden = !pref.children.length;

      var note = finder.querySelector('[data-finder-note]');
      if (p.unrecognised && p.unrecognised.length) {
        note.hidden = false;
        note.textContent = 'Not recognised: ' + p.unrecognised.join(', ') + '. We may not list projects there yet, so it isn’t used as a filter.';
      } else {
        note.hidden = true;
        note.textContent = '';
      }

      var link = finder.querySelector('[data-search-link]');
      link.href = targetPath + toQueryString(p.query || {}) + targetAnchor;
      link.textContent = p.count > 0 ? 'Show ' + p.count + ' matching project' + (p.count === 1 ? '' : 's') : 'Search';

      var wanted = finder.querySelector('[data-c-wanted]');
      var labels = (p.chips || []).filter(function (c) { return c.type === 'req'; }).map(function (c) { return c.label; });
      wanted.textContent = labels.length ? 'You asked for: ' + labels.join(' · ') : '';
      var relax = finder.querySelector('[data-relax]');
      relax.textContent = '';
      (p.relaxations || []).forEach(function (r) {
        var a = el('a', 'ai-option');
        a.href = targetPath + toQueryString(r.query || {}) + targetAnchor;
        a.appendChild(el('span', null, r.label));
        a.appendChild(el('span', 'ai-option-count', r.count + ' project' + (r.count === 1 ? '' : 's') + ' →'));
        relax.appendChild(a);
      });
      finder.querySelector('[data-relax-label]').hidden = !(p.relaxations && p.relaxations.length);

      if (p.ambiguity) {
        finder.querySelector('[data-amb-term]').textContent = p.ambiguity.term;
        var school = p.ambiguity.kind === 'school_quality';
        finder.querySelector('[data-amb="nearest_school"]').hidden = !school;
        finder.querySelector('[data-amb-school-note]').hidden = !school;
      }

      setMsg('');
      setState(p.state || 'B');
      fillForm(p);
      var focusTarget = finder.querySelector('[data-for="' + (p.state || 'B') + '"] .ai-panel-title');
      if (focusTarget) { focusTarget.setAttribute('tabindex', '-1'); focusTarget.focus({ preventScroll: true }); }
    }

    // Saved-search page: copy the interpreted requirements into the alert form (the visitor still reviews and submits).
    function fillForm(p) {
      var sel = finder.getAttribute('data-fill-form');
      var target = sel ? document.querySelector(sel) : null;
      if (!target || !p.query) return;
      var q = p.query;
      function set(name, value) {
        var f = target.querySelector('[name="' + name + '"]');
        if (f) f.value = value == null ? '' : value;
      }
      set('corridor_id', q.corridor_id || '');
      set('category', q.category || '');
      set('budget_min', q.price_min || '');
      set('budget_max', q.price_max || '');
      set('bhk_config', q.bhk ? [].concat(q.bhk).join(' / ') + ' BHK' : '');
      var notes = [];
      if (q.sector) notes.push('Sectors: ' + [].concat(q.sector).join(', ') + '.');
      var prefs = (p.chips || []).filter(function (c) { return c.type === 'pref'; }).map(function (c) { return c.label; });
      if (prefs.length) notes.push('Preferences: ' + prefs.join(', ') + '.');
      set('notes', notes.join(' '));
      setMsg('We’ve filled in the form below from this — check it, add your name and phone, and save.');
    }

    function handleError(res) {
      setState('A');
      if (res && (res.code === 'limited' || res.code === 'disabled' || res.code === 'no_key')) {
        setMsg(res.message, res.whatsapp ? { text: 'WhatsApp us', href: res.whatsapp, external: true } : null);
      } else if (res && res.fallback_url) {
        setMsg(res.message, { text: 'Search with regular filters', href: res.fallback_url });
      } else {
        setMsg((res && res.message) || 'Something went wrong — please use the regular filters.');
      }
    }

    function interpret(text) {
      if (busy) return;
      setBusy(true);
      var context = {};
      try { context = JSON.parse(finder.getAttribute('data-context') || '{}') || {}; } catch (err) { context = {}; }
      post({ mode: 'interpret', query: text, context: context })
        .then(function (res) {
          setBusy(false);
          if (!res || res.ok === false) return handleError(res);
          if (res.state === 'A') { setState('A'); setMsg(res.message || ''); input.focus(); return; }
          render(res);
        })
        .catch(function () { setBusy(false); handleError(null); });
    }

    function recount(query, ambiguity) {
      if (busy) return;
      setBusy(true, 'Updating…');
      post({ mode: 'count', filters: query })
        .then(function (res) {
          setBusy(false);
          if (!res || res.ok === false) return handleError(res);
          res.city = current && current.city;
          res.unrecognised = current && current.unrecognised;
          res.ambiguity = ambiguity || null;
          render(res);
        })
        .catch(function () { setBusy(false); handleError(null); });
    }

    if (aiOff) {
      finder.classList.add('is-ai-off');
      return; // form submits as a normal keyword search
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = input.value.trim();
      if (!text) { input.focus(); setMsg('Tell us what you’re looking for — location, size, budget or timeline.'); return; }
      interpret(text);
    });

    finder.addEventListener('click', function (e) {
      var ex = e.target.closest('[data-example]');
      if (ex) {
        input.value = ex.getAttribute('data-example');
        interpret(input.value);
        return;
      }
      var x = e.target.closest('[data-chip-query]');
      if (x && finder.getAttribute('data-variant') === 'compact') return; // results page: the link reloads with the chip removed
      if (x) {
        e.preventDefault();
        var q = {};
        try { q = JSON.parse(x.getAttribute('data-chip-query')) || {}; } catch (err) { q = {}; }
        var chip = x.closest('.ai-chip');
        if (chip) chip.classList.add('is-removed');
        recount(q, null);
        return;
      }
      if (e.target.closest('[data-edit]')) {
        setState('A');
        input.focus();
        input.select();
        return;
      }
      var amb = e.target.closest('[data-amb]');
      if (amb && amb.tagName === 'BUTTON') {
        var q2 = (current && current.query) || {};
        var prefs = (q2.pref || []).slice();
        if (amb.getAttribute('data-amb') === 'nearest_school') {
          if (prefs.indexOf('near_school') === -1) prefs.push('near_school');
        } else if (current && current.ambiguity && current.ambiguity.kind === 'school_quality') {
          prefs = prefs.filter(function (p) { return p !== 'near_school'; });
        }
        var next = Object.assign({}, q2, { pref: prefs });
        if (!prefs.length) delete next.pref;
        recount(next, null);
      }
    });

    document.querySelectorAll('[data-focus-target="#' + input.id + '"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        finder.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(function () { input.focus({ preventScroll: true }); }, 350);
      });
    });

    // Server-rendered start state (properties.php): remember the active filters for chip re-counts.
    var firstChip = finder.querySelector('[data-chip-query]');
    if (firstChip && finder.getAttribute('data-state') !== 'A') {
      var params = new URLSearchParams(location.search);
      var q = {};
      params.forEach(function (v, k) {
        var key = k.replace(/\[\]$/, '');
        if (k !== key) { (q[key] = q[key] || []).push(v); } else if (k !== 'page') { q[k] = v; }
      });
      current = { query: q, chips: [], state: finder.getAttribute('data-state') };
    }

    finder.addEventListener('ai-finder:transcript', function (e) {
      input.value = e.detail.text;
      interpret(e.detail.text);
    });
  });
})();

/* Project Assistant (Feature 5). Answers come from /api/ai-assistant.php; all text via textContent. */
(function () {
  'use strict';

  var PREFILL_KEY = 'kutumb_assistant_prefill';

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }

  document.querySelectorAll('[data-pa]').forEach(function (pa) {
    var panel = pa.querySelector('.ai-pa-panel');
    var launcher = pa.querySelector('[data-pa-toggle]');
    var log = pa.querySelector('[data-pa-log]');
    var form = pa.querySelector('[data-pa-form]');
    var input = form.querySelector('input');
    var propertyId = parseInt(pa.getAttribute('data-property-id'), 10);
    var propertyName = pa.getAttribute('data-property-name');
    var busy = false;
    var lastQuestion = '';

    function open() {
      pa.setAttribute('data-pa-open', '');
      panel.hidden = false;
      launcher.setAttribute('aria-expanded', 'true');
      input.focus();
      if (window.KutumbLeadCapture) window.KutumbLeadCapture.restore(log, 'ai-bubble ai-bubble--bot');
    }
    function close() {
      pa.removeAttribute('data-pa-open');
      panel.hidden = true;
      launcher.setAttribute('aria-expanded', 'false');
      launcher.focus();
    }

    // WCAG 3.3.7: what the visitor already asked is carried to the enquiry form instead of being retyped.
    function rememberForContact(question) {
      try { sessionStorage.setItem(PREFILL_KEY, JSON.stringify({ property: propertyId, name: propertyName, question: question })); } catch (e) { /* storage blocked */ }
    }

    function scrollDown() { log.scrollTop = log.scrollHeight; }

    function addUser(text) {
      log.appendChild(el('div', 'ai-bubble ai-bubble--user', text));
      scrollDown();
    }

    function addLinks(parent, links) {
      if (!links || !links.length) return;
      var wrap = el('div', 'ai-answer-links');
      links.forEach(function (l) {
        var a = el('a', null, l.text);
        a.href = l.href;
        if (l.external) { a.target = '_blank'; a.rel = 'noopener'; }
        if (l.href.indexOf('/contact.php') === 0) a.addEventListener('click', function () { rememberForContact(lastQuestion); });
        if (l.href.charAt(0) === '#') a.addEventListener('click', function () { close(); });
        wrap.appendChild(a);
      });
      parent.appendChild(wrap);
    }

    function addAnswer(res) {
      var bubble = el('div', 'ai-bubble ai-bubble--bot ai-answer' + (res.kind === 'fallback' ? ' ai-answer--none' : ''));
      if (res.title) bubble.appendChild(el('p', 'ai-answer-title', res.title));
      (res.lines || []).forEach(function (line) { bubble.appendChild(el('p', null, line)); });
      if (res.source) bubble.appendChild(el('p', 'ai-source', res.source));
      if (res.caveat) bubble.appendChild(el('p', 'ai-answer-caveat', res.caveat));
      addLinks(bubble, res.links);
      log.appendChild(bubble);
      scrollDown();
    }

    function addError(res) {
      var bubble = el('div', 'ai-bubble ai-bubble--bot ai-answer ai-answer--none');
      bubble.appendChild(el('p', null, (res && res.message) || 'Something went wrong. Please try again, or contact our team.'));
      var links = [{ text: 'Contact our team', href: '/contact.php?property=' + propertyId }];
      if (res && res.whatsapp) links.push({ text: 'WhatsApp us', href: res.whatsapp, external: true });
      addLinks(bubble, links);
      log.appendChild(bubble);
      scrollDown();
    }

    function ask(question) {
      question = (question || '').trim();
      if (!question || busy) return;
      busy = true;
      lastQuestion = question;
      addUser(question);
      input.value = '';
      var typing = el('div', 'ai-bubble ai-bubble--bot ai-typing', 'Checking verified project information…');
      log.appendChild(typing);
      scrollDown();
      fetch('/api/ai-assistant.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ property_id: propertyId, message: question })
      }).then(function (r) { return r.json(); }).then(function (res) {
        typing.remove();
        if (!res || res.ok === false) addError(res); else addAnswer(res);
        if (res && res.next && window.KutumbLeadCapture) window.KutumbLeadCapture.show(log, res.next, 'ai-bubble ai-bubble--bot');
      }).catch(function () {
        typing.remove();
        addError(null);
      }).finally(function () { busy = false; });
    }

    launcher.addEventListener('click', open);
    pa.querySelector('[data-pa-close]').addEventListener('click', close);
    panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    form.addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
    pa.querySelector('[data-pa-suggest]').addEventListener('click', function (e) {
      var b = e.target.closest('[data-pa-ask]');
      if (b) ask(b.getAttribute('data-pa-ask'));
    });
    pa.querySelector('[data-pa-contact]').addEventListener('click', function () { rememberForContact(lastQuestion); });

    if (/[?&]assistant=open\b/.test(location.search)) open();
  });
})();

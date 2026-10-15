/* Lead capture inside the website chats (site chatbot + project assistant).
   After an answer the server may send `next`:
     {step: 'requirement', n, text, options}   → one requirement question as buttons, with Skip
     {step: 'contact', required, text}         → name + phone form (required = no more answers until sent)
   window.KutumbLeadCapture.show(log, next, bubbleClass) renders it into a chat log. */
(function () {
  'use strict';

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  function post(payload) {
    return fetch('/api/chat-capture.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); });
  }

  function scroll(log) { log.scrollTop = log.scrollHeight; }

  function showRequirement(log, next, bubbleClass) {
    var box = el('div', bubbleClass + ' lc-box');
    box.setAttribute('data-lc', 'requirement');
    box.appendChild(el('p', 'lc-text', next.text));
    var row = el('div', 'lc-options');
    function answer(value, label) {
      row.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
      box.appendChild(el('p', 'lc-picked', label));
      post({ action: 'requirement', n: next.n, value: value }).then(function (res) {
        if (res.reply) log.appendChild(el('div', bubbleClass, res.reply));
        if (res.next) show(log, res.next, bubbleClass);
        scroll(log);
      });
    }
    Object.keys(next.options).forEach(function (key) {
      var b = el('button', 'lc-option', next.options[key]);
      b.type = 'button';
      b.addEventListener('click', function () { answer(key, next.options[key]); });
      row.appendChild(b);
    });
    var skip = el('button', 'lc-skip', 'Skip');
    skip.type = 'button';
    skip.addEventListener('click', function () { answer(null, 'Skipped'); });
    row.appendChild(skip);
    box.appendChild(row);
    log.appendChild(box);
    scroll(log);
  }

  function showContact(log, next, bubbleClass) {
    // One form at a time: a newer prompt replaces an unanswered one.
    log.querySelectorAll('[data-lc="contact"]').forEach(function (old) { if (!old.hasAttribute('data-lc-done')) old.remove(); });
    var box = el('div', bubbleClass + ' lc-box' + (next.required ? ' lc-required' : ''));
    box.setAttribute('data-lc', 'contact');
    box.appendChild(el('p', 'lc-text', next.text));
    var form = el('form', 'lc-form');
    form.noValidate = true;
    var name = el('input');
    name.type = 'text'; name.name = 'name'; name.placeholder = 'Your name'; name.autocomplete = 'name'; name.maxLength = 120; name.required = true;
    name.setAttribute('aria-label', 'Your name');
    var phone = el('input');
    phone.type = 'tel'; phone.name = 'phone'; phone.placeholder = 'Phone (10 digits)'; phone.autocomplete = 'tel'; phone.maxLength = 15; phone.required = true;
    phone.setAttribute('aria-label', 'Phone number');
    var send = el('button', 'lc-send', 'Send');
    send.type = 'submit';
    var msg = el('p', 'lc-msg');
    msg.setAttribute('role', 'status');
    form.appendChild(name); form.appendChild(phone); form.appendChild(send);
    box.appendChild(form);
    box.appendChild(msg);
    box.appendChild(el('p', 'lc-note', 'By sending, you agree that a Kutumb advisor may call, WhatsApp or email you about your search.'));
    if (!next.required) {
      var later = el('button', 'lc-skip', 'Not now');
      later.type = 'button';
      later.addEventListener('click', function () { box.remove(); });
      box.appendChild(later);
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!name.value.trim() || phone.value.replace(/\D/g, '').length < 10) {
        msg.textContent = 'Please enter your name and a 10-digit phone number.';
        return;
      }
      send.disabled = true;
      msg.textContent = 'Sending…';
      post({ action: 'contact', name: name.value, phone: phone.value }).then(function (res) {
        if (!res.ok) {
          send.disabled = false;
          msg.textContent = res.message || 'Please check the details and try again.';
          return;
        }
        box.setAttribute('data-lc-done', '');
        form.remove();
        msg.textContent = '';
        box.querySelectorAll('.lc-skip').forEach(function (b) { b.remove(); });
        log.appendChild(el('div', bubbleClass, res.message));
        document.dispatchEvent(new CustomEvent('kutumb:lead-captured'));
        scroll(log);
      }).catch(function () {
        send.disabled = false;
        msg.textContent = 'Could not send — please try again.';
      });
    });
    log.appendChild(box);
    scroll(log);
    if (next.required) setTimeout(function () { name.focus(); }, 50);
  }

  function show(log, next, bubbleClass) {
    if (!next || !log) return;
    if (next.step === 'requirement') showRequirement(log, next, bubbleClass);
    else if (next.step === 'contact') showContact(log, next, bubbleClass);
  }

  /** On opening a chat: restore a pending requirement question or a required phone form. */
  function restore(log, bubbleClass) {
    if (!log || log.hasAttribute('data-lc-restored')) return;
    log.setAttribute('data-lc-restored', '');
    post({ action: 'state' }).then(function (res) {
      if (res && res.ok && res.next && !log.querySelector('[data-lc]')) show(log, res.next, bubbleClass);
    }).catch(function () { /* the chat still works without it */ });
  }

  window.KutumbLeadCapture = { show: show, restore: restore };
})();

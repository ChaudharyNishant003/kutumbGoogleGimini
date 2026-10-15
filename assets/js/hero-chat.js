document.addEventListener('DOMContentLoaded', function () {
  var wrap = document.getElementById('hero-ask');
  if (!wrap) return;

  var typedEl = document.getElementById('hero-ask-typed');
  var typedTextEl = document.getElementById('hero-ask-typed-text');
  var input = document.getElementById('hero-ask-input');
  var sendBtn = document.getElementById('hero-ask-send');
  var starters = document.getElementById('hero-ask-starters');

  var QUESTIONS = [
    '3BHK in Sector 82 under ₹2.5 Cr',
    'Ready-to-move home within a ₹50 lakh budget',
    'New launch on Dwarka Expressway'
  ];

  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var qIndex = 0, charIndex = 0, phase = 'typing', timer = null;

  function typeTick() {
    if (wrap.classList.contains('open')) return;
    var full = QUESTIONS[qIndex];
    if (phase === 'typing') {
      charIndex++;
      if (charIndex > full.length) { phase = 'pausing'; setTimeout(function () { phase = 'deleting'; }, 1300); }
    } else if (phase === 'deleting') {
      charIndex--;
      if (charIndex < 0) { charIndex = 0; phase = 'typing'; qIndex = (qIndex + 1) % QUESTIONS.length; }
    }
    if (typedTextEl) typedTextEl.textContent = full.slice(0, Math.max(0, charIndex));
  }

  if (typedTextEl) {
    if (prefersReducedMotion) {
      typedTextEl.textContent = QUESTIONS[0];
    } else {
      timer = setInterval(typeTick, 55);
    }
  }

  function openBar() {
    wrap.classList.add('open');
    if (starters) starters.hidden = false;
    if (input) input.focus();
  }

  if (typedEl) {
    typedEl.addEventListener('click', openBar);
    typedEl.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openBar(); } });
  }

  function send(text) {
    var q = (text !== undefined ? text : (input ? input.value : '')).trim();
    if (!q) { openBar(); return; }
    // Launcher only — the single discovery tool on the home page is the AI
    // Home Finder below. Hand the query to it (it shows editable filter chips
    // before anything is searched). If it is not on the page, fall back to
    // the chat widget so the question is never lost.
    var finderInput = document.getElementById('ai-finder-hero-q');
    var finder = document.getElementById('ai-finder-hero');
    if (finderInput && finder) {
      finderInput.value = q;
      finder.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'center' });
      var form = finderInput.form;
      if (form && form.requestSubmit) form.requestSubmit(); else if (form) form.dispatchEvent(new Event('submit', { cancelable: true }));
    } else {
      document.dispatchEvent(new CustomEvent('kutumb:chat-open', { detail: { prefill: q, autoSend: true } }));
    }
    if (input) input.value = '';
  }

  if (sendBtn) sendBtn.addEventListener('click', function () { send(); });
  if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });
  if (starters) {
    starters.querySelectorAll('.hero-ask-starter').forEach(function (btn) {
      btn.addEventListener('click', function () { send(btn.textContent); });
    });
  }
});

(function () {
  function ensureContainer() {
    var el = document.getElementById('toast-container');
    if (!el) {
      el = document.createElement('div');
      el.id = 'toast-container';
      el.className = 'toast-container';
      document.body.appendChild(el);
    }
    return el;
  }

  window.kutumbToast = function (message, type) {
    var container = ensureContainer();
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + (type || 'success');
    toast.textContent = message;
    container.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('toast-visible'); });
    setTimeout(function () {
      toast.classList.remove('toast-visible');
      setTimeout(function () { toast.remove(); }, 350);
    }, 4200);
  };

  window.kutumbAnchoredToast = function (message, type, targetEl) {
    if (!targetEl) return window.kutumbToast(message, type);
    var toast = document.createElement('div');
    toast.className = 'toast-anchored toast-' + (type || 'success');
    toast.textContent = message;
    document.body.appendChild(toast);

    var rect = targetEl.getBoundingClientRect();
    var top = rect.top - toast.offsetHeight - 10;
    if (top < 8) top = rect.bottom + 10; // not enough room above (e.g. topmost row) — place below instead
    var left = Math.min(window.innerWidth - toast.offsetWidth - 12, Math.max(12, rect.left));
    toast.style.top = top + 'px';
    toast.style.left = left + 'px';

    requestAnimationFrame(function () { toast.classList.add('toast-visible'); });
    setTimeout(function () {
      toast.classList.remove('toast-visible');
      setTimeout(function () { toast.remove(); }, 300);
    }, 4200);
  };

  window.kutumbSetLoading = function (btn, loading) {
    if (!btn) return;
    btn.disabled = !!loading;
    btn.classList.toggle('btn-loading', !!loading);
  };
})();

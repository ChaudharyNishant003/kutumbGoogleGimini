(function () {
  var STORAGE_KEY = 'kutumb_compare';
  var MAX_ITEMS = 4;

  function getList() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) { return []; }
  }

  function saveList(list) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(list)); } catch (e) {}
  }

  function render() {
    var list = getList();
    var bar = document.getElementById('compare-bar');
    var itemsEl = document.getElementById('compare-bar-items');
    var nowLink = document.getElementById('compare-now');

    if (bar) bar.classList.toggle('visible', list.length > 0);
    if (itemsEl) {
      itemsEl.innerHTML = list.map(function (item) {
        return '<span class="compare-bar-chip">' + item.name + '<button type="button" data-remove="' + item.id + '" aria-label="Remove">×</button></span>';
      }).join('');
    }
    if (nowLink) {
      nowLink.href = '/compare.php?ids=' + list.map(function (i) { return i.id; }).join(',');
    }

    document.querySelectorAll('.compare-toggle').forEach(function (btn) {
      var id = btn.getAttribute('data-id');
      var active = list.some(function (i) { return String(i.id) === String(id); });
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      if (btn.hasAttribute('data-inline')) {
        btn.textContent = active ? '✓ Added to Compare' : '+ Add to Compare';
      }
    });
  }

  function toggle(id, name) {
    if (!id) return;
    var list = getList();
    var idx = list.findIndex(function (i) { return String(i.id) === String(id); });
    if (idx >= 0) {
      list.splice(idx, 1);
    } else {
      if (list.length >= MAX_ITEMS) {
        alert('You can compare up to ' + MAX_ITEMS + ' properties at a time.');
        return;
      }
      list.push({ id: id, name: name || ('Property ' + id) });
    }
    saveList(list);
    render();
  }

  function remove(id) {
    saveList(getList().filter(function (i) { return String(i.id) !== String(id); }));
    render();
  }

  document.addEventListener('click', function (e) {
    var toggleEl = e.target.closest('.compare-toggle');
    if (toggleEl) {
      e.preventDefault();
      e.stopPropagation();
      toggle(toggleEl.getAttribute('data-id'), toggleEl.getAttribute('data-name'));
      return;
    }
    var removeBtn = e.target.closest('[data-remove]');
    if (removeBtn) {
      e.preventDefault();
      remove(removeBtn.getAttribute('data-remove'));
      return;
    }
    if (e.target && e.target.id === 'compare-clear') {
      saveList([]);
      render();
    }
  });

  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('compare-toggle') && e.target.getAttribute('role') === 'button') {
      e.preventDefault();
      toggle(e.target.getAttribute('data-id'), e.target.getAttribute('data-name'));
    }
  });

  document.addEventListener('DOMContentLoaded', render);
  document.addEventListener('kutumb:cards-updated', render);
})();

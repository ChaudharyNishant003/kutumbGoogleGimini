document.addEventListener('DOMContentLoaded', function () {
  /* ---------------- Success/error banners -> toast ----------------
     Forms redirect back with a static ".form-note" banner after saving —
     landing on a different-looking page with no obvious confirmation read as
     "goes to a blank page" on some flows. Convert the banner into a toast
     instead of showing both. Fully revertible: remove this block and the
     original static banners render exactly as before, unchanged. */
  document.querySelectorAll('.form-note.success, .form-note.error').forEach(function (note) {
    var type = note.classList.contains('error') ? 'error' : 'success';
    var text = note.textContent.trim();
    var rowId = note.dataset.anchorRowId;
    note.remove(); // remove first — its layout space would otherwise skew the row position measured below
    var row = rowId ? document.querySelector('tr[data-row-id="' + rowId + '"], .admin-photo-card[data-row-id="' + rowId + '"]') : null;
    if (row && window.kutumbAnchoredToast) {
      window.kutumbAnchoredToast(text, type, row);
    } else if (window.kutumbToast) {
      window.kutumbToast(text, type);
    }
  });

  var toggle = document.querySelector('.admin-nav-toggle');
  var sidebar = document.querySelector('.admin-sidebar');
  if (!toggle || !sidebar) return;

  var setOpen = function (open) {
    sidebar.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
  };
  toggle.addEventListener('click', function () {
    setOpen(!sidebar.classList.contains('open'));
  });
  sidebar.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () { setOpen(false); });
  });

  /* ---------------- Leads: Status/Assignee auto-save ----------------
     Previously each dropdown submitted its own form with onchange, causing a
     full page reload per change (and, since it's a real bug worth flagging:
     the OTHER dropdown's form carries the pre-reload status/assignee value in
     a hidden field — save one without reloading, and the next save from the
     other form would silently revert it back to that stale value). Fixed by
     saving via fetch and syncing both forms' hidden fields together, so
     neither can ever go stale relative to the other. */
  document.querySelectorAll('.js-lead-update-field').forEach(function (select) {
    var previousValue = select.value;
    select.addEventListener('change', function () {
      var form = select.closest('form');
      var row = select.closest('tr');
      var label = select.name === 'status' ? 'Status updated' : 'Lead reassigned';
      var formData = new FormData(form);

      fetch(form.action, { method: 'POST', body: formData })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok || !result.data.success) throw new Error(result.data.error || 'Save failed.');
          previousValue = select.value;
          if (row) {
            row.querySelectorAll('.js-lead-update-form input[type="hidden"][name="' + select.name + '"]').forEach(function (hidden) {
              hidden.value = select.value;
            });
          }
          if (window.kutumbToast) window.kutumbToast(label, 'success');
        })
        .catch(function (err) {
          select.value = previousValue; // revert on failure — don't leave the UI showing an unsaved value
          if (window.kutumbToast) window.kutumbToast(err.message || 'Could not save — please try again.', 'error');
        });
    });
  });

  /* ---------------- Follow-up: opens in a real <dialog> popup ----------------
     Previously this expanded inline inside the lead/saved-search card, which
     grew that card's height — and since CSS grid stretches every card in a
     row to match the tallest one, opening a follow-up on one card visibly
     stretched its unrelated siblings too. A native <dialog> renders in the
     browser's top layer (via showModal()), completely outside the card's own
     box and its overflow:hidden, so sibling card heights are never touched. */
  document.querySelectorAll('.js-followup-open').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var modal = document.getElementById(btn.dataset.modal);
      if (modal) modal.showModal();
    });
  });
  document.querySelectorAll('.js-followup-close').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dialog = btn.closest('dialog');
      if (dialog) dialog.close();
    });
  });
  // Clicking the backdrop (outside the dialog's own box) also closes it.
  document.querySelectorAll('dialog.followup-modal').forEach(function (dialog) {
    dialog.addEventListener('click', function (e) {
      if (e.target === dialog) dialog.close();
    });
  });

  /* ---------------- Row delete: AJAX + inline message + slide-up ----------------
     Bug fixed here: this used to rely on an inline onsubmit="return
     confirm(...)" attribute to gate the delete, on the assumption that
     returning false from it would stop this listener from running too. It
     doesn't — an inline onsubmit handler returning false only cancels the
     form's own default submission; it has no effect on other listeners
     added via addEventListener, so this fetch() call fired unconditionally
     regardless of whether the user clicked OK or Cancel. The confirm() is
     now called directly in here instead, so its result actually gates the
     delete. */
  document.querySelectorAll('.js-row-delete-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (form.dataset.confirm && !confirm(form.dataset.confirm)) return;
      var row = form.closest('tr') || form.closest('.admin-photo-card');
      var isTableRow = !!row && row.tagName === 'TR';
      var colCount = isTableRow ? row.children.length : 0;
      var message = form.dataset.deleteMessage || 'Deleted.';

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'fetch' },
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok || !result.data.success) throw new Error(result.data.error || 'Could not delete — please try again.');
          if (!row) return;
          row.innerHTML = '';
          if (isTableRow) {
            var cell = document.createElement('td');
            cell.colSpan = colCount;
            cell.className = 'row-delete-message';
            cell.textContent = message;
            row.appendChild(cell);
          } else {
            row.classList.add('card-delete-message');
            var msg = document.createElement('div');
            msg.className = 'row-delete-message';
            msg.textContent = message;
            row.appendChild(msg);
          }
          setTimeout(function () {
            row.classList.add('row-collapsing');
            row.addEventListener('transitionend', function () { row.remove(); }, { once: true });
          }, 3500);
        })
        .catch(function (err) {
          if (window.kutumbToast) window.kutumbToast(err.message || 'Could not delete — please try again.', 'error');
        });
    });
  });

  /* ---------------- Dashboard: clickable Lead Funnel rows ----------------
     A <tr> can't be an <a>, so the row's own click navigates to the URL
     carried in data-href instead. */
  document.querySelectorAll('tr.funnel-row-linked[data-href]').forEach(function (row) {
    row.addEventListener('click', function () {
      window.location.href = row.dataset.href;
    });
  });
});

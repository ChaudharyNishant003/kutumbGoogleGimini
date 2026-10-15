<?php
// After a project is saved with a new location (or none yet), fills its nearby places in the
// background from OpenStreetMap and says so — the save itself never waits for it.
$autofillId = (int)($_GET['autofill'] ?? 0);
if ($autofillId): ?>
<p class="form-note" id="places-autofill-note" style="font-size:13px;">Filling in nearby places for this project from OpenStreetMap…</p>
<script>
fetch('/admin/places-autofill.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ property_id: <?= $autofillId ?>, csrf_token: <?= json_encode(csrf_token()) ?> }) })
  .then(function (r) { return r.json(); })
  .then(function (r) { document.getElementById('places-autofill-note').textContent = 'Nearby places: ' + (r.message || (r.ok ? 'done' : 'could not be filled in')); })
  .catch(function () { document.getElementById('places-autofill-note').textContent = 'Nearby places could not be filled in right now — use "Auto-fill" on the Nearby Places page later.'; });
</script>
<?php endif;

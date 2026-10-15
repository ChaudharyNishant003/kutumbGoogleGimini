<?php
$page_title = 'Nearby Places — Kutumb Admin';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ai-places.php';

$categories = place_categories();
$searchQuery = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
if (!isset($categories[$category])) $category = '';

$whereSql = ' WHERE deleted_at IS NULL';
$params = [];
if ($searchQuery !== '') {
    $whereSql .= ' AND (name LIKE ? OR source LIKE ?)';
    $params[] = '%' . $searchQuery . '%';
    $params[] = '%' . $searchQuery . '%';
}
if ($category !== '') {
    $whereSql .= ' AND category = ?';
    $params[] = $category;
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM places' . $whereSql);
$countStmt->execute($params);
$pg = paginate_params((int)$countStmt->fetchColumn(), 25, [25, 50, 100]);

$stmt = db()->prepare('SELECT * FROM places' . $whereSql . ' ORDER BY category, name LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$places = $stmt->fetchAll();

$counts = db()->query('SELECT category, COUNT(*) n FROM places WHERE deleted_at IS NULL GROUP BY category')->fetchAll(PDO::FETCH_KEY_PAIR);
$missingCoords = (int)db()->query('SELECT COUNT(*) FROM properties WHERE deleted_at IS NULL AND (lat IS NULL OR lng IS NULL)')->fetchColumn();

$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);
$savedId = (int)($_GET['id'] ?? 0);
$query = array_filter(['q' => $searchQuery, 'category' => $category], fn($v) => $v !== '');
?>

<div class="admin-topbar">
  <h1>Nearby Places</h1>
  <div class="admin-topbar-actions">
    <form method="get" class="admin-search">
      <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
      <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by name or source...">
      <?php if ($searchQuery !== ''): ?><a href="/admin/places.php<?= $category !== '' ? '?category=' . e($category) : '' ?>" class="admin-search-clear">Clear</a><?php endif; ?>
    </form>
    <button type="button" class="btn btn-soft btn-sm" id="places-autofill-all">Auto-fill for all projects</button>
    <a href="/admin/place-form.php" class="btn btn-primary btn-sm">+ Add Place</a>
  </div>
</div>
<div class="admin-card" id="places-autofill-progress" hidden>
  <h4 style="margin-bottom:6px;">Auto-filling from OpenStreetMap</h4>
  <p style="font-size:13px; margin:0 0 8px;" data-status>Starting…</p>
  <ol style="font-size:12.5px; color:var(--ink-soft); margin:0; padding-left:18px;" data-results></ol>
</div>
<script>
// One project at a time (OpenStreetMap allows about one request per second), then reload to show the new places.
document.getElementById('places-autofill-all').addEventListener('click', function () {
  if (!confirm('Find each project's location (where missing) and fill in schools, hospitals, malls, metro and offices within 5 km from OpenStreetMap? This takes a few seconds per project. Places you added or hid yourself are not changed.')) return;
  var btn = this, box = document.getElementById('places-autofill-progress');
  var status = box.querySelector('[data-status]'), list = box.querySelector('[data-results]');
  var token = <?= json_encode(csrf_token()) ?>;
  function call(payload) {
    payload.csrf_token = token;
    return fetch('/admin/places-autofill.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).then(function (r) { return r.json(); });
  }
  btn.disabled = true; box.hidden = false;
  call({ action: 'list' }).then(function (res) {
    var ids = res.ids || [], i = 0, added = 0;
    (function next() {
      if (i >= ids.length) { status.textContent = 'Done — ' + added + ' new place(s). Reloading…'; setTimeout(function () { location.reload(); }, 1500); return; }
      status.textContent = 'Project ' + (i + 1) + ' of ' + ids.length + '…';
      call({ property_id: ids[i] }).then(function (r) {
        added += r.added || 0;
        var li = document.createElement('li');
        li.textContent = (r.name || ('#' + ids[i])) + ': ' + (r.message || (r.ok ? 'done' : 'failed'));
        list.appendChild(li);
      }).catch(function () {
        var li = document.createElement('li'); li.textContent = '#' + ids[i] + ': failed'; list.appendChild(li);
      }).finally(function () { i++; next(); });
    })();
  });
});
</script>

<p style="color:var(--ink-soft); font-size:13.5px; margin:-8px 0 16px; max-width:760px;">
  Schools, hospitals, metro stations, the airport, malls and offices shown in the "What's around" section of each property page and used by the chat assistant.
  "Auto-fill" brings them in from OpenStreetMap (also run automatically when a project's location is saved); you can still add, edit or hide places by hand — an auto-fill never changes those.
  Distances are straight-line from each property's location.
</p>
<?php if ($missingCoords): ?>
  <p class="admin-note-warn"><?= $missingCoords ?> propert<?= $missingCoords === 1 ? 'y has' : 'ies have' ?> no location yet, so no nearby places can be shown for <?= $missingCoords === 1 ? 'it' : 'them' ?>. Add a location by pasting a Google Maps link in each property's edit form, or press "Auto-fill for all projects" to look it up from the project name and sector (approximate).</p>
<?php endif; ?>

<?php if ($saved): ?><div class="form-note success">Place saved.</div><?php endif; ?>
<?php if ($deleted): ?><div class="form-note success">Place moved to Bin.</div><?php endif; ?>

<div class="pill-row" style="margin:0 0 16px;">
  <a class="pill<?= $category === '' ? ' active' : '' ?>" href="/admin/places.php<?= $searchQuery !== '' ? '?q=' . urlencode($searchQuery) : '' ?>">All (<?= array_sum($counts) ?>)</a>
  <?php foreach ($categories as $key => $label): ?>
    <a class="pill<?= $category === $key ? ' active' : '' ?>" href="/admin/places.php?<?= e(http_build_query(array_filter(['category' => $key, 'q' => $searchQuery]))) ?>"><?= e($label) ?> (<?= (int)($counts[$key] ?? 0) ?>)</a>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <?php if (!$places && ($searchQuery !== '' || $category !== '')): ?>
    <p style="color:var(--ink-soft)">No places match these filters.</p>
  <?php elseif (!$places): ?>
    <p style="color:var(--ink-soft)">No places yet. <a href="/admin/place-form.php">Add your first one</a> — e.g. the nearest schools, hospitals and metro stations for your corridors.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Name</th><th>Category</th><th>Location</th><th>Source</th><th>Verified on</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($places as $pl): ?>
          <tr<?= $savedId === (int)$pl['id'] ? ' style="background:var(--paper-dim)"' : '' ?>>
            <td data-label="Name"><a href="/admin/place-form.php?id=<?= (int)$pl['id'] ?>" style="font-weight:600;"><?= e($pl['name']) ?></a></td>
            <td data-label="Category"><?= e($categories[$pl['category']] ?? $pl['category']) ?></td>
            <td data-label="Location"><a href="https://www.google.com/maps?q=<?= e($pl['lat'] . ',' . $pl['lng']) ?>" target="_blank" rel="noopener"><?= e(number_format((float)$pl['lat'], 5) . ', ' . number_format((float)$pl['lng'], 5)) ?></a></td>
            <td data-label="Source"><?= e($pl['source']) ?></td>
            <td data-label="Verified on"><?= e(date('d M Y', strtotime($pl['verified_on']))) ?></td>
            <td data-label="">
              <form method="post" action="/admin/delete-place.php" onsubmit="return confirm('Move this place to the Bin?');">
                <input type="hidden" name="id" value="<?= (int)$pl['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <?= render_pagination($pg, '/admin/places.php', $query) ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

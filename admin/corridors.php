<?php
$page_title = 'Corridors — Kutumb Admin';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';

$searchQuery = trim($_GET['q'] ?? '');
$whereSql = ' WHERE c.deleted_at IS NULL';
$params = [];
if ($searchQuery !== '') {
    $whereSql .= ' AND c.name LIKE ?';
    $params[] = '%' . $searchQuery . '%';
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM corridors c' . $whereSql);
$countStmt->execute($params);
$pg = paginate_params_grid((int)$countStmt->fetchColumn());

$stmt = db()->prepare(
    'SELECT c.*, COUNT(p.id) AS property_count FROM corridors c LEFT JOIN properties p ON p.corridor_id = c.id'
    . $whereSql . ' GROUP BY c.id ORDER BY c.name'
    . ' LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']
);
$stmt->execute($params);
$corridors = $stmt->fetchAll();

$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);
$savedId = (int)($_GET['id'] ?? 0);
$savedNew = ($_GET['new'] ?? '') === '1';
$savedName = '';
if ($savedId) {
    $nameStmt = db()->prepare('SELECT name FROM corridors WHERE id = ?');
    $nameStmt->execute([$savedId]);
    $savedName = (string)($nameStmt->fetchColumn() ?: '');
}
?>

<div class="admin-topbar">
  <h1>Corridors</h1>
  <div class="admin-topbar-actions">
    <form method="get" class="admin-search">
      <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by name...">
      <?php if ($searchQuery !== ''): ?><a href="/admin/corridors.php" class="admin-search-clear">Clear</a><?php endif; ?>
    </form>
    <a href="/admin/corridor-form.php" class="btn btn-primary btn-sm">+ Add Corridor</a>
  </div>
</div>

<?php if ($saved && $savedName !== ''): ?>
  <div class="save-banner">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
    <div><strong><?= e($savedName) ?></strong> <?= $savedNew ? 'added' : 'updated' ?> successfully. <span class="save-banner-id">ID: <?= $savedId ?></span></div>
  </div>
<?php elseif ($saved): ?>
  <div class="form-note success">Corridor saved.</div>
<?php endif; ?>
<?php if ($deleted): ?><div class="form-note success">Corridor moved to Bin.</div><?php endif; ?>

<div class="admin-card">
  <?php if (!$corridors && $searchQuery !== ''): ?>
    <p style="color:var(--ink-soft)">No corridors match "<?= e($searchQuery) ?>".</p>
  <?php elseif (!$corridors): ?>
    <p style="color:var(--ink-soft)">No corridors yet. <a href="/admin/corridor-form.php">Add your first one</a>.</p>
  <?php else: ?>
    <div class="admin-card-grid">
      <?php foreach ($corridors as $c): ob_start(); ?>
        <form method="post" action="/admin/delete-corridor.php" class="js-row-delete-form" data-delete-message="Corridor moved to Bin." data-confirm="Delete this corridor?">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
        </form>
        <?php $deleteForm = ob_get_clean(); ?>
        <?= render_admin_card([
          'image' => !empty($c['hero_image_path']) ? UPLOAD_URL . e($c['hero_image_path']) : property_placeholder_svg($c['name']),
          'alt' => $c['name'],
          'badge' => ($c['lat'] !== null && $c['lng'] !== null) ? 'Map Set' : null,
          'title' => $c['name'],
          'meta' => [$c['avg_price_band'], $c['tier'] !== 'standard' ? 'Tier: ' . ucfirst($c['tier']) : null],
          'highlight_label' => 'Listings',
          'highlight_value' => (string)(int)$c['property_count'],
          'added' => date('d M Y, h:i A', strtotime($c['created_at'])),
          'edit_url' => '/admin/corridor-form.php?id=' . (int)$c['id'],
          'delete_form' => $deleteForm,
          'row_id' => $c['id'],
          'highlighted' => $savedId === (int)$c['id'],
        ]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?= render_pagination($pg, '/admin/corridors.php', $searchQuery !== '' ? ['q' => $searchQuery] : []) ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

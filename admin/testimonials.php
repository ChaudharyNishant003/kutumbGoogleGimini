<?php
$page_title = 'Testimonials — Kutumb Admin';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';

$searchQuery = trim($_GET['q'] ?? '');
$whereSql = ' WHERE t.deleted_at IS NULL';
$params = [];
if ($searchQuery !== '') {
    $whereSql .= ' AND t.client_name LIKE ?';
    $params[] = '%' . $searchQuery . '%';
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM testimonials t' . $whereSql);
$countStmt->execute($params);
$pg = paginate_params_grid((int)$countStmt->fetchColumn());

$stmt = db()->prepare(
    'SELECT t.*, p.name AS property_name FROM testimonials t LEFT JOIN properties p ON p.id = t.property_id'
    . $whereSql . ' ORDER BY t.sort_order, t.id DESC'
    . ' LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']
);
$stmt->execute($params);
$testimonials = $stmt->fetchAll();

$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);
$savedId = (int)($_GET['id'] ?? 0);
$savedNew = ($_GET['new'] ?? '') === '1';
$savedName = '';
if ($savedId) {
    $nameStmt = db()->prepare('SELECT client_name FROM testimonials WHERE id = ?');
    $nameStmt->execute([$savedId]);
    $savedName = (string)($nameStmt->fetchColumn() ?: '');
}
?>

<div class="admin-topbar">
  <h1>Testimonials</h1>
  <div class="admin-topbar-actions">
    <form method="get" class="admin-search">
      <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by client name...">
      <?php if ($searchQuery !== ''): ?><a href="/admin/testimonials.php" class="admin-search-clear">Clear</a><?php endif; ?>
    </form>
    <a href="/admin/testimonial-form.php" class="btn btn-primary btn-sm">+ Add Testimonial</a>
  </div>
</div>

<?php if ($saved && $savedName !== ''): ?>
  <div class="save-banner">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
    <div><strong><?= e($savedName) ?></strong> <?= $savedNew ? 'added' : 'updated' ?> successfully. <span class="save-banner-id">ID: <?= $savedId ?></span></div>
  </div>
<?php elseif ($saved): ?>
  <div class="form-note success">Testimonial saved.</div>
<?php endif; ?>
<?php if ($deleted): ?><div class="form-note success">Testimonial moved to Bin.</div><?php endif; ?>

<div class="admin-card">
  <?php if (!$testimonials && $searchQuery !== ''): ?>
    <p style="color:var(--ink-soft)">No testimonials match "<?= e($searchQuery) ?>".</p>
  <?php elseif (!$testimonials): ?>
    <p style="color:var(--ink-soft)">No testimonials yet — the homepage shows nothing until at least one is marked "Featured on homepage".</p>
  <?php else: ?>
    <div class="admin-card-grid">
      <?php foreach ($testimonials as $t): ob_start(); ?>
        <form method="post" action="/admin/delete-testimonial.php" class="js-row-delete-form" data-delete-message="Testimonial moved to Bin." data-confirm="Delete this testimonial?">
          <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
        </form>
        <?php $deleteForm = ob_get_clean(); ?>
        <?= render_admin_card([
          'image' => !empty($t['photo_path']) ? UPLOAD_URL . e($t['photo_path']) : property_placeholder_svg($t['client_name']),
          'alt' => $t['client_name'],
          'badge' => $t['featured_home'] ? 'Homepage' : null,
          'title' => $t['client_name'],
          // Blank optional fields (property, role) are simply omitted, never shown as a bare "—".
          'meta' => [$t['client_role'] ?? '', $t['property_name'] ?? ''],
          'highlight_label' => $t['rating'] ? 'Rating' : null,
          'highlight_value' => $t['rating'] ? str_repeat('★', (int)$t['rating']) : null,
          'added' => date('d M Y, h:i A', strtotime($t['created_at'])),
          'edit_url' => '/admin/testimonial-form.php?id=' . (int)$t['id'],
          'delete_form' => $deleteForm,
          'row_id' => $t['id'],
          'highlighted' => $savedId === (int)$t['id'],
        ]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?= render_pagination($pg, '/admin/testimonials.php', $searchQuery !== '' ? ['q' => $searchQuery] : []) ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

<?php
$page_title = 'Properties — Kutumb Admin';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';

$searchQuery = trim($_GET['q'] ?? '');
$missingOnly = ($_GET['missing'] ?? '') === '1';
// What the project assistant, the chatbot and "What's around" need from each project.
$missingSql = "(info_verified_on IS NULL OR possession_text IS NULL OR possession_text = '' OR lat IS NULL
               OR (COALESCE(rera_number, '') = '' AND NOT EXISTS (SELECT 1 FROM property_rera_registrations r WHERE r.property_id = properties.id)))";
$whereSql = ' WHERE deleted_at IS NULL';
$params = [];
if ($searchQuery !== '') {
    $whereSql .= ' AND name LIKE ?';
    $params[] = '%' . $searchQuery . '%';
}

if ($missingOnly) {
    $whereSql .= ' AND ' . $missingSql;
}
$missingCount = (int)db()->query('SELECT COUNT(*) FROM properties WHERE deleted_at IS NULL AND ' . $missingSql)->fetchColumn();

$countStmt = db()->prepare('SELECT COUNT(*) FROM properties' . $whereSql);
$countStmt->execute($params);
$pg = paginate_params_grid((int)$countStmt->fetchColumn());

$stmt = db()->prepare('SELECT properties.*, (SELECT COUNT(*) FROM property_rera_registrations r WHERE r.property_id = properties.id) AS rera_count FROM properties' . $whereSql . ' ORDER BY id DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$properties = $stmt->fetchAll();

/** "⚠ Missing: …" for a card, or null when the project has everything the chat assistant uses. */
function property_missing_info_line(array $p): ?string
{
    $missing = [];
    if (!$p['info_verified_on']) $missing[] = 'verified date';
    if (trim((string)$p['possession_text']) === '') $missing[] = 'possession';
    if (trim((string)$p['rera_number']) === '' && !(int)$p['rera_count']) $missing[] = 'RERA';
    if ($p['lat'] === null || $p['lng'] === null) $missing[] = 'location';
    return $missing ? '⚠ Missing: ' . implode(', ', $missing) : null;
}

$deleted = isset($_GET['deleted']);
$saved = isset($_GET['saved']);
$savedId = (int)($_GET['id'] ?? 0);
$savedNew = ($_GET['new'] ?? '') === '1';
$savedName = '';
if ($savedId) {
    $nameStmt = db()->prepare('SELECT name FROM properties WHERE id = ?');
    $nameStmt->execute([$savedId]);
    $savedName = (string)($nameStmt->fetchColumn() ?: '');
}
?>

<div class="admin-topbar">
  <h1>Properties</h1>
  <div class="admin-topbar-actions">
    <form method="get" class="admin-search">
      <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by name...">
      <?php if ($searchQuery !== ''): ?><a href="/admin/properties.php" class="admin-search-clear">Clear</a><?php endif; ?>
    </form>
    <form method="post" action="/admin/property-verify.php" onsubmit="return confirm('Mark the information of ALL live projects as verified today? Only do this after checking prices, configurations and possession dates.');">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="all" value="1">
      <button type="submit" class="btn btn-soft btn-sm">Mark all verified today</button>
    </form>
    <a href="/admin/property-form.php" class="btn btn-primary btn-sm">+ Add Property</a>
  </div>
</div>
<?php if (isset($_GET['verified'])): ?><div class="form-note success">Marked as verified today.</div><?php endif; ?>
<?php if ($missingCount && !$missingOnly): ?>
  <div class="admin-filter-active"><?= $missingCount ?> project<?= $missingCount === 1 ? ' is' : 's are' ?> missing information the chat assistant uses (verified date, possession, RERA or location). <a href="/admin/properties.php?missing=1">Show them</a></div>
<?php elseif ($missingOnly): ?>
  <div class="admin-filter-active">Showing projects with missing information. <a href="/admin/properties.php">Show all</a></div>
<?php endif; ?>

<?php if ($deleted): ?><div class="form-note success">Property moved to Bin.</div><?php endif; ?>
<?php include __DIR__ . '/../includes/places-autofill-trigger.php'; ?>
<?php if ($saved && $savedName !== ''): ?>
  <div class="save-banner">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
    <div><strong><?= e($savedName) ?></strong> <?= $savedNew ? 'added' : 'updated' ?> successfully. <span class="save-banner-id">ID: <?= $savedId ?></span></div>
  </div>
<?php elseif ($saved): ?>
  <div class="form-note success">Property saved.</div>
<?php endif; ?>

<div class="admin-card">
  <?php if (!$properties && $searchQuery !== ''): ?>
    <p style="color:var(--ink-soft)">No properties match "<?= e($searchQuery) ?>".</p>
  <?php elseif (!$properties): ?>
    <p style="color:var(--ink-soft)">No properties yet. <a href="/admin/property-form.php">Add your first one</a>.</p>
  <?php else: ?>
    <div class="admin-card-grid">
      <?php foreach ($properties as $p): ob_start(); ?>
        <form method="post" action="/admin/delete-property.php" class="js-row-delete-form" data-delete-message="Property moved to Bin." data-confirm="Delete this property?">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
        </form>
        <?php $deleteForm = ob_get_clean(); ?>
        <?= render_admin_card([
          'image' => property_card_image_src($p),
          'alt' => $p['name'],
          'badge' => $p['featured'] ? 'Featured' : null,
          'title' => $p['name'],
          'meta' => [$p['sector'], category_label($p['category']), $p['tier'] !== 'standard' ? 'Tier: ' . ucfirst($p['tier']) : null, property_missing_info_line($p)],
          'highlight_label' => 'Price',
          'highlight_value' => $p['price_display'],
          'added' => date('d M Y, h:i A', strtotime($p['created_at'])),
          'edit_url' => '/admin/property-form.php?id=' . (int)$p['id'],
          'actions_extra' => '<a href="/admin/property-faqs.php?property_id=' . (int)$p['id'] . '">FAQs</a>',
          'delete_form' => $deleteForm,
          'row_id' => $p['id'],
          'highlighted' => $savedId === (int)$p['id'],
        ]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?= render_pagination($pg, '/admin/properties.php', array_filter(['q' => $searchQuery, 'missing' => $missingOnly ? '1' : ''], fn($v) => $v !== '')) ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

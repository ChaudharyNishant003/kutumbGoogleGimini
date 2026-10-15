<?php
$page_title = 'Bin — Kutumb Admin';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';

$allItems = bin_list_items();
$expiredCount = count(array_filter($allItems, fn($item) => $item['days_left'] === 0));

$searchQuery = trim($_GET['q'] ?? '');
if ($searchQuery !== '') {
    $allItems = array_values(array_filter($allItems, function ($item) use ($searchQuery) {
        return stripos($item['label'], $searchQuery) !== false;
    }));
}

$pg = paginate_params(count($allItems));
$items = array_slice($allItems, $pg['offset'], $pg['per_page']);

$restored = isset($_GET['restored']);
$purged = isset($_GET['purged']);
$purgedExpired = isset($_GET['purged_expired']) ? (int)$_GET['purged_expired'] : null;
?>

<div class="admin-topbar">
  <h1>Bin</h1>
  <div class="admin-topbar-actions">
    <form method="get" class="admin-search">
      <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by name...">
      <?php if ($searchQuery !== ''): ?><a href="/admin/bin.php" class="admin-search-clear">Clear</a><?php endif; ?>
    </form>
  </div>
</div>

<?php if ($restored): ?><div class="form-note success">Item restored.</div><?php endif; ?>
<?php if ($purged): ?><div class="form-note success">Item permanently deleted.</div><?php endif; ?>
<?php if ($purgedExpired !== null): ?><div class="form-note success"><?= $purgedExpired ?> expired item<?= $purgedExpired === 1 ? '' : 's' ?> permanently deleted.</div><?php endif; ?>

<p style="color:var(--ink-soft); font-size:13.5px; margin:-8px 0 20px;">Deleted properties, blog posts, team members, and testimonials stay here and can be restored. After 15 days they can be removed for good with the button below — nothing is deleted automatically.</p>
<?php if ($expiredCount): ?>
  <form method="post" action="/admin/bin-action.php" style="margin:-8px 0 20px;" onsubmit="return confirm('Permanently delete <?= $expiredCount ?> item<?= $expiredCount === 1 ? '' : 's' ?> that have been in the Bin for more than 15 days? This cannot be undone.');">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="purge_expired">
    <button type="submit" class="btn btn-danger btn-sm">Delete <?= $expiredCount ?> expired item<?= $expiredCount === 1 ? '' : 's' ?> (older than 15 days)</button>
  </form>
<?php endif; ?>

<div class="admin-card">
  <?php if (!$items && $searchQuery !== ''): ?>
    <div class="bin-empty-state">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 7h16"></path>
        <path d="M9 7V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V7"></path>
        <path d="M6 7l1 12.5A1.5 1.5 0 0 0 8.5 21h7a1.5 1.5 0 0 0 1.5-1.5L18 7"></path>
        <path d="M10 11v6"></path>
        <path d="M14 11v6"></path>
      </svg>
      <p>No items in Bin match "<?= e($searchQuery) ?>".</p>
    </div>
  <?php elseif (!$items): ?>
    <div class="bin-empty-state">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 7h16"></path>
        <path d="M9 7V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V7"></path>
        <path d="M6 7l1 12.5A1.5 1.5 0 0 0 8.5 21h7a1.5 1.5 0 0 0 1.5-1.5L18 7"></path>
        <path d="M10 11v6"></path>
        <path d="M14 11v6"></path>
      </svg>
      <p>Bin is empty.</p>
    </div>
  <?php else: ?>
    <div class="admin-card-grid">
      <?php foreach ($items as $item): ?>
        <div class="admin-photo-card admin-data-card">
          <div class="admin-data-card-header">
            <h3><?= e($item['label']) ?></h3>
            <div class="admin-data-card-sub"><?= e(bin_type_label($item['type'])) ?></div>
          </div>
          <div class="admin-data-card-rows">
            <div class="admin-data-card-row"><span class="label">Deleted</span><span class="value"><?= e(date('d M Y, h:i:s A', strtotime($item['deleted_at']))) ?></span></div>
            <div class="admin-data-card-row"><span class="label">Can be deleted</span><span class="value"><?= $item['days_left'] === 0 ? 'now (over 15 days)' : 'in ' . $item['days_left'] . ' day' . ($item['days_left'] === 1 ? '' : 's') ?></span></div>
          </div>
          <div class="admin-data-card-footer">
            <form method="post" action="/admin/bin-action.php">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="type" value="<?= e($item['type']) ?>">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="action" value="restore">
              <button type="submit" style="background:none;border:none;color:var(--navy);cursor:pointer;padding:0;font-size:13px;font-weight:500;">Restore</button>
            </form>
            <form method="post" action="/admin/bin-action.php" onsubmit="return confirm('Permanently delete this? This cannot be undone.');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="type" value="<?= e($item['type']) ?>">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="action" value="purge">
              <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete Permanently</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?= render_pagination($pg, '/admin/bin.php', $searchQuery !== '' ? ['q' => $searchQuery] : []) ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

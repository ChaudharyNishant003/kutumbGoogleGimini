<?php
$page_title = 'Blog & Insights — Kutumb Admin';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';

$searchQuery = trim($_GET['q'] ?? '');
$whereSql = ' WHERE p.deleted_at IS NULL';
$params = [];
if ($searchQuery !== '') {
    $whereSql .= ' AND p.title LIKE ?';
    $params[] = '%' . $searchQuery . '%';
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM blog_posts p' . $whereSql);
$countStmt->execute($params);
$pg = paginate_params_grid((int)$countStmt->fetchColumn());

$stmt = db()->prepare(
    'SELECT p.*, c.name AS corridor_name FROM blog_posts p LEFT JOIN corridors c ON c.id = p.corridor_id'
    . $whereSql . ' ORDER BY p.created_at DESC'
    . ' LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']
);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);
$savedId = (int)($_GET['id'] ?? 0);
$savedNew = ($_GET['new'] ?? '') === '1';
$savedName = '';
if ($savedId) {
    $nameStmt = db()->prepare('SELECT title FROM blog_posts WHERE id = ?');
    $nameStmt->execute([$savedId]);
    $savedName = (string)($nameStmt->fetchColumn() ?: '');
}
?>

<div class="admin-topbar">
  <h1>Blog &amp; Insights</h1>
  <div class="admin-topbar-actions">
    <form method="get" class="admin-search">
      <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by title...">
      <?php if ($searchQuery !== ''): ?><a href="/admin/blog-list.php" class="admin-search-clear">Clear</a><?php endif; ?>
    </form>
    <a href="/admin/blog-form.php" class="btn btn-primary btn-sm">+ Add Post</a>
  </div>
</div>

<?php if ($saved && $savedName !== ''): ?>
  <div class="save-banner">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
    <div><strong><?= e($savedName) ?></strong> <?= $savedNew ? 'added' : 'updated' ?> successfully. <span class="save-banner-id">ID: <?= $savedId ?></span></div>
  </div>
<?php elseif ($saved): ?>
  <div class="form-note success">Post saved.</div>
<?php endif; ?>
<?php if ($deleted): ?><div class="form-note success">Post moved to Bin.</div><?php endif; ?>

<div class="admin-card">
  <?php if (!$posts && $searchQuery !== ''): ?>
    <p style="color:var(--ink-soft)">No posts match "<?= e($searchQuery) ?>".</p>
  <?php elseif (!$posts): ?>
    <p style="color:var(--ink-soft)">No posts yet.</p>
  <?php else: ?>
    <div class="admin-card-grid">
      <?php foreach ($posts as $p): ob_start(); ?>
        <form method="post" action="/admin/delete-blog-post.php" class="js-row-delete-form" data-delete-message="Post moved to Bin." data-confirm="Delete this post?">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
        </form>
        <?php $deleteForm = ob_get_clean(); ?>
        <?= render_admin_card([
          'image' => !empty($p['cover_image_path']) ? UPLOAD_URL . e($p['cover_image_path']) : property_placeholder_svg($p['title']),
          'alt' => $p['title'],
          'badge' => ucfirst($p['status']),
          'title' => $p['title'],
          'meta' => [ucfirst(str_replace('-', ' ', $p['post_type'])), $p['corridor_name'] ?? ''],
          'highlight_label' => $p['published_at'] ? 'Published' : null,
          'highlight_value' => $p['published_at'] ? date('d M Y', strtotime($p['published_at'])) : null,
          'added' => date('d M Y, h:i A', strtotime($p['created_at'])),
          'edit_url' => '/admin/blog-form.php?id=' . (int)$p['id'],
          'actions_extra' => $p['status'] === 'published' ? '<a href="/blog-post.php?slug=' . e($p['slug']) . '" target="_blank">View ↗</a>' : '',
          'delete_form' => $deleteForm,
          'row_id' => $p['id'],
          'highlighted' => $savedId === (int)$p['id'],
        ]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?= render_pagination($pg, '/admin/blog-list.php', $searchQuery !== '' ? ['q' => $searchQuery] : []) ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

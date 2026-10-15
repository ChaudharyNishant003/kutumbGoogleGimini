<?php
$page_title = 'Team — Kutumb Admin';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';

$searchQuery = trim($_GET['q'] ?? '');
$whereSql = ' WHERE deleted_at IS NULL';
$params = [];
if ($searchQuery !== '') {
    $whereSql .= ' AND name LIKE ?';
    $params[] = '%' . $searchQuery . '%';
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM team_members' . $whereSql);
$countStmt->execute($params);
$pg = paginate_params_grid((int)$countStmt->fetchColumn());

$stmt = db()->prepare('SELECT * FROM team_members' . $whereSql . ' ORDER BY sort_order, name LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$members = $stmt->fetchAll();

$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);
$savedId = (int)($_GET['id'] ?? 0);
$savedNew = ($_GET['new'] ?? '') === '1';
$savedName = '';
if ($savedId) {
    $nameStmt = db()->prepare('SELECT name FROM team_members WHERE id = ?');
    $nameStmt->execute([$savedId]);
    $savedName = (string)($nameStmt->fetchColumn() ?: '');
}
?>

<div class="admin-topbar">
  <h1>Team</h1>
  <div class="admin-topbar-actions">
    <form method="get" class="admin-search">
      <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by name...">
      <?php if ($searchQuery !== ''): ?><a href="/admin/team.php" class="admin-search-clear">Clear</a><?php endif; ?>
    </form>
    <a href="/admin/team-form.php" class="btn btn-primary btn-sm">+ Add Team Member</a>
  </div>
</div>

<?php if ($saved && $savedName !== ''): ?>
  <div class="save-banner">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
    <div><strong><?= e($savedName) ?></strong> <?= $savedNew ? 'added' : 'updated' ?> successfully. <span class="save-banner-id">ID: <?= $savedId ?></span></div>
  </div>
<?php elseif ($saved): ?>
  <div class="form-note success">Team member saved.</div>
<?php endif; ?>
<?php if ($deleted): ?><div class="form-note success">Team member moved to Bin.</div><?php endif; ?>

<div class="admin-card">
  <?php if (!$members && $searchQuery !== ''): ?>
    <p style="color:var(--ink-soft)">No team members match "<?= e($searchQuery) ?>".</p>
  <?php elseif (!$members): ?>
    <p style="color:var(--ink-soft)">No team members yet.</p>
  <?php else: ?>
    <div class="admin-card-grid">
      <?php foreach ($members as $m): ob_start(); ?>
        <form method="post" action="/admin/delete-team-member.php" class="js-row-delete-form" data-delete-message="Team member moved to Bin." data-confirm="Remove this team member?">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
        </form>
        <?php $deleteForm = ob_get_clean(); ?>
        <?= render_admin_card([
          'image' => !empty($m['photo_path']) ? UPLOAD_URL . e($m['photo_path']) : property_placeholder_svg($m['name']),
          'alt' => $m['name'],
          'badge' => $m['active'] ? null : 'Inactive',
          'title' => $m['name'],
          'meta' => [$m['role_title'], $m['phone'] ?? ''],
          'highlight_label' => 'Phone',
          'highlight_value' => $m['phone'] ?: '—',
          'added' => date('d M Y, h:i A', strtotime($m['created_at'])),
          'edit_url' => '/admin/team-form.php?id=' . (int)$m['id'],
          'delete_form' => $deleteForm,
          'row_id' => $m['id'],
          'highlighted' => $savedId === (int)$m['id'],
        ]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?= render_pagination($pg, '/admin/team.php', $searchQuery !== '' ? ['q' => $searchQuery] : []) ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

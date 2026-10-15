<?php
$page_title = 'Manage Admins — Kutumb Admin';
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';
require_role('owner');

$totalAdmins = (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
$pg = paginate_params_grid($totalAdmins);
$admins = db()->query('SELECT * FROM admin_users ORDER BY id ASC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset'])->fetchAll();

$saved = isset($_GET['saved']);
$deactivated = isset($_GET['deactivated']);
$deactivatedName = $_GET['admin_name'] ?? '';
$deactivatedStatus = $_GET['admin_status'] ?? '';
$deactivatedId = (int)($_GET['admin_id'] ?? 0);
$savedId = (int)($_GET['id'] ?? 0);
$savedNew = ($_GET['new'] ?? '') === '1';
$savedName = '';
if ($savedId) {
    foreach ($admins as $a) {
        if ((int)$a['id'] === $savedId) { $savedName = $a['username']; break; }
    }
}
?>

<div class="admin-topbar">
  <h1>Manage Admins</h1>
  <a href="/admin/admin-form.php" class="btn btn-primary btn-sm">+ Add Admin</a>
</div>

<?php $deleted = isset($_GET['deleted']); ?>
<?php if ($saved): ?>
  <div class="form-note success"<?= ($savedId && $savedName !== '') ? ' data-anchor-row-id="' . $savedId . '"' : '' ?>>
    <?= $savedName !== '' ? e($savedName) . ($savedNew ? ' added.' : ' updated.') : 'Admin saved.' ?>
  </div>
<?php endif; ?>
<?php if ($deactivated): ?>
  <div class="form-note success"<?= $deactivatedId ? ' data-anchor-row-id="' . $deactivatedId . '"' : '' ?>><?php if ($deactivatedName !== ''): ?><?= e($deactivatedName) ?> is now <?= $deactivatedStatus === 'active' ? 'Active' : 'Inactive' ?>.<?php else: ?>Admin status updated.<?php endif; ?></div>
<?php endif; ?>
<?php if ($deleted): ?><div class="form-note success">Admin removed.</div><?php endif; ?>

<div class="admin-card">
  <?php if (!$admins): ?>
    <p style="color:var(--ink-soft)">No admins yet.</p>
  <?php else: ?>
    <div class="admin-card-grid">
      <?php foreach ($admins as $a):
        ob_start(); ?>
        <a href="/admin/admin-form.php?id=<?= (int)$a['id'] ?>">Edit</a>
        <?php if ((int)$a['id'] !== (int)current_admin()['id']): ?>
        <form method="post" action="/admin/deactivate-admin.php" onsubmit="return confirm('<?= $a['active'] ? 'Deactivate' : 'Reactivate' ?> this admin?');">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;"><?= $a['active'] ? 'Deactivate' : 'Reactivate' ?></button>
        </form>
        <?php if ($a['role'] !== 'owner'): ?>
        <form method="post" action="/admin/delete-admin.php" onsubmit="return confirm('Permanently delete this admin account? This cannot be undone.');">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
        <?php $footer = ob_get_clean();

        echo render_admin_data_card([
            'title' => $a['username'],
            'subtitle' => ($a['full_name'] ?: '—') . ' · ' . ucfirst($a['role']),
            'badges' => ['<span class="admin-data-card-badge ' . ($a['active'] ? 'active' : 'inactive') . '">' . ($a['active'] ? 'Active' : 'Inactive') . '</span>'],
            'rows' => [
                '<span class="label">Email</span><span class="value">' . (e($a['email'] ?? '') ?: '—') . '</span>',
                '<span class="label">Added</span><span class="value">' . e(date('d M Y, h:i A', strtotime($a['created_at']))) . '</span>',
            ],
            'footer' => $footer,
            'row_id' => (int)$a['id'],
        ]);
      endforeach; ?>
    </div>
  <?php endif; ?>
  <?= render_pagination($pg, '/admin/admins.php') ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

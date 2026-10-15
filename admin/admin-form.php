<?php
// admin-header.php is required after POST-handling below, not here — see the
// comment in property-form.php for why (early output breaks header('Location')).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_admin();
require_role('owner');

$page_title = 'Admin — Kutumb Admin';

$id = (int)($_GET['id'] ?? 0);
$admin_row = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = ?');
    $stmt->execute([$id]);
    $admin_row = $stmt->fetch();
}

$roles = ['owner' => 'Owner', 'admin' => 'Admin', 'agent' => 'Agent'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    }

    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'agent';
    $password = $_POST['password'] ?? '';
    $active = isset($_POST['active']) ? 1 : 0;

    if ($username === '' || !isset($roles[$role])) {
        $errors[] = 'Please fill in username and a valid role.';
    }
    if (!$admin_row && $password === '') {
        $errors[] = 'A password is required for a new admin.';
    }
    if (!validate_email($email)) {
        $errors[] = 'That email address doesn\'t look right.';
    }

    if (!$errors) {
        $isNew = !$admin_row;
        if ($admin_row) {
            if ($password !== '') {
                $stmt = db()->prepare('UPDATE admin_users SET username=?, full_name=?, email=?, role=?, active=?, password_hash=? WHERE id=?');
                $stmt->execute([$username, $fullName ?: null, $email ?: null, $role, $active, password_hash($password, PASSWORD_DEFAULT), $admin_row['id']]);
            } else {
                $stmt = db()->prepare('UPDATE admin_users SET username=?, full_name=?, email=?, role=?, active=? WHERE id=?');
                $stmt->execute([$username, $fullName ?: null, $email ?: null, $role, $active, $admin_row['id']]);
            }
            $savedAdminId = $admin_row['id'];
        } else {
            $stmt = db()->prepare('INSERT INTO admin_users (username, full_name, email, role, active, password_hash) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$username, $fullName ?: null, $email ?: null, $role, $active, password_hash($password, PASSWORD_DEFAULT)]);
            $savedAdminId = (int)db()->lastInsertId();
        }
        if (($_POST['action'] ?? 'close') === 'new') {
            header('Location: /admin/admin-form.php?saved=1');
        } else {
            header('Location: /admin/admins.php?saved=1&id=' . $savedAdminId . '&new=' . ($isNew ? '1' : '0'));
        }
        exit;
    }

    $admin_row = [
        'id' => $admin_row['id'] ?? null, 'username' => $username, 'full_name' => $fullName,
        'email' => $email, 'role' => $role, 'active' => $active,
    ];
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-topbar">
  <h1><?= $admin_row && !empty($admin_row['id']) ? 'Edit Admin' : 'Add Admin' ?></h1>
  <a href="/admin/admins.php" class="btn btn-navy btn-sm">← Back</a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="form-note success">Admin saved successfully. Add another below, or head back to the list.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="form-field"><label for="af-username">Username<span class="field-required-mark">*</span></label><input type="text" id="af-username" name="username" required value="<?= e($admin_row['username'] ?? '') ?>"></div>
    <div class="form-field"><label for="af-fullname">Full Name<span class="field-badge-optional">Optional</span></label><input type="text" id="af-fullname" name="full_name" value="<?= e($admin_row['full_name'] ?? '') ?>"></div>
    <div class="form-field"><label for="af-email">Email<span class="field-badge-optional">Optional</span></label><input type="email" id="af-email" name="email" value="<?= e($admin_row['email'] ?? '') ?>"></div>

    <div class="form-field">
      <label for="af-role">Role<span class="field-required-mark">*</span></label>
      <select id="af-role" name="role">
        <?php foreach ($roles as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= (($admin_row['role'] ?? 'agent') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-field">
      <label for="af-password">Password<?= ($admin_row && !empty($admin_row['id'])) ? '<span class="field-badge-optional">Optional</span> (leave blank to keep current)' : '<span class="field-required-mark">*</span>' ?></label>
      <input type="password" id="af-password" name="password" autocomplete="new-password">
    </div>

    <div class="form-field">
      <label><input type="checkbox" name="active" value="1" <?= (!isset($admin_row['active']) || $admin_row['active']) ? 'checked' : '' ?> style="width:auto; margin-right:8px;"> Active</label>
    </div>

    <div style="display:flex; gap:10px;">
      <button type="submit" name="action" value="close" class="btn btn-primary">Save &amp; Close</button>
      <button type="submit" name="action" value="new" class="btn btn-navy">Save &amp; Add New</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

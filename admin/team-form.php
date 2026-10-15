<?php
// admin-header.php is required after POST-handling below, not here — see the
// comment in property-form.php for why (early output breaks header('Location')).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$page_title = 'Team Member — Kutumb Admin';

$id = (int)($_GET['id'] ?? 0);
$member = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM team_members WHERE id = ?');
    $stmt->execute([$id]);
    $member = $stmt->fetch();
}

$corridors = db()->query('SELECT * FROM corridors WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $roleTitle = trim($_POST['role_title'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $specialtyCorridorId = !empty($_POST['specialty_corridor_id']) ? (int)$_POST['specialty_corridor_id'] : null;
    $active = isset($_POST['active']) ? 1 : 0;

    if (!validate_name($name) || $roleTitle === '') {
        $errors[] = 'Please fill in name and role.';
    }
    if ($phone !== '' && !validate_phone($phone)) {
        $errors[] = 'That phone number doesn\'t look right.';
    }
    if ($whatsapp !== '' && !validate_phone($whatsapp)) {
        $errors[] = 'That WhatsApp number doesn\'t look right.';
    }
    if (!validate_email($email)) {
        $errors[] = 'That email address doesn\'t look right.';
    }

    $photoPath = $member['photo_path'] ?? null;
    if (!empty($_FILES['photo']['name'])) {
        $uploadError = null;
        $newName = handle_file_upload($_FILES['photo'], UPLOAD_DIR, image_mime_map(), MAX_UPLOAD_BYTES, $uploadError);
        if ($uploadError) {
            $errors[] = $uploadError;
        } elseif ($newName) {
            delete_uploaded_file(UPLOAD_DIR, $photoPath);
            $photoPath = $newName;
        }
    }

    if (!$errors) {
        $isNew = !$member;
        if ($member) {
            $slug = $member['slug']; // keep existing slug on edit
            $stmt = db()->prepare('UPDATE team_members SET name=?, slug=?, role_title=?, phone=?, email=?, whatsapp=?, bio=?, photo_path=?, specialty_corridor_id=?, active=? WHERE id=?');
            $stmt->execute([$name, $slug, $roleTitle, $phone ?: null, $email ?: null, $whatsapp ?: null, $bio ?: null, $photoPath, $specialtyCorridorId, $active, $member['id']]);
            $memberId = $member['id'];
        } else {
            $slug = slugify($name) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            $stmt = db()->prepare('INSERT INTO team_members (name, slug, role_title, phone, email, whatsapp, bio, photo_path, specialty_corridor_id, active) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$name, $slug, $roleTitle, $phone ?: null, $email ?: null, $whatsapp ?: null, $bio ?: null, $photoPath, $specialtyCorridorId, $active]);
            $memberId = (int)db()->lastInsertId();
        }
        if (($_POST['action'] ?? 'close') === 'new') {
            header('Location: /admin/team-form.php?saved=1');
        } else {
            header('Location: /admin/team.php?saved=1&id=' . $memberId . '&new=' . ($isNew ? '1' : '0'));
        }
        exit;
    }

    $member = [
        'id' => $member['id'] ?? null, 'name' => $name, 'role_title' => $roleTitle, 'phone' => $phone,
        'email' => $email, 'whatsapp' => $whatsapp, 'bio' => $bio, 'photo_path' => $photoPath,
        'specialty_corridor_id' => $specialtyCorridorId, 'active' => $active,
    ];
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-topbar">
  <h1><?= $member && !empty($member['id']) ? 'Edit Team Member' : 'Add Team Member' ?></h1>
  <a href="/admin/team.php" class="btn btn-navy btn-sm">← Back</a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="form-note success">Team member added successfully. Add another below, or head back to the list.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="form-field"><label for="tf-name">Name<span class="field-required-mark">*</span></label><input type="text" id="tf-name" name="name" required value="<?= e($member['name'] ?? '') ?>"></div>
    <div class="form-field"><label for="tf-role">Role / Title<span class="field-required-mark">*</span></label><input type="text" id="tf-role" name="role_title" required value="<?= e($member['role_title'] ?? '') ?>" placeholder="e.g. Senior Property Advisor"></div>

    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1"><label for="tf-phone">Phone<span class="field-badge-optional">Optional</span></label><input type="text" id="tf-phone" name="phone" value="<?= e($member['phone'] ?? '') ?>"></div>
      <div class="form-field" style="flex:1">
        <label for="tf-whatsapp">WhatsApp<span class="field-badge-optional">Optional</span></label>
        <input type="text" id="tf-whatsapp" name="whatsapp" value="<?= e($member['whatsapp'] ?? '') ?>" placeholder="e.g. 919318373250">
        <label style="font-weight:400; font-size:12.5px; margin-top:6px; display:block;"><input type="checkbox" id="tf-whatsapp-same" style="width:auto; margin-right:6px;">Same as phone number</label>
      </div>
      <div class="form-field" style="flex:1"><label for="tf-email">Email<span class="field-badge-optional">Optional</span></label><input type="email" id="tf-email" name="email" value="<?= e($member['email'] ?? '') ?>"></div>
    </div>
    <script>
      (function () {
        var phone = document.getElementById('tf-phone');
        var whatsapp = document.getElementById('tf-whatsapp');
        var same = document.getElementById('tf-whatsapp-same');
        if (!phone || !whatsapp || !same) return;
        same.checked = whatsapp.value !== '' && whatsapp.value === phone.value;
        function sync() { if (same.checked) whatsapp.value = phone.value; }
        same.addEventListener('change', function () {
          whatsapp.readOnly = same.checked;
          sync();
        });
        phone.addEventListener('input', sync);
        whatsapp.readOnly = same.checked;
      })();
    </script>

    <div class="form-field">
      <label for="tf-corridor">Specialty Corridor<span class="field-badge-optional">Optional</span></label>
      <select id="tf-corridor" name="specialty_corridor_id">
        <option value="">— None —</option>
        <?php foreach ($corridors as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (isset($member['specialty_corridor_id']) && $member['specialty_corridor_id'] == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-field"><label for="tf-bio">Bio<span class="field-badge-optional">Optional</span></label><textarea id="tf-bio" name="bio"><?= e($member['bio'] ?? '') ?></textarea></div>

    <div class="form-field">
      <label for="tf-photo">Photo<span class="field-badge-optional">Optional</span> <?= !empty($member['photo_path']) ? '(replace existing)' : '' ?></label>
      <?php if (!empty($member['photo_path'])): ?>
        <img class="thumb" style="width:64px;height:64px;margin-bottom:8px;" src="<?= UPLOAD_URL . e($member['photo_path']) ?>" alt="">
      <?php endif; ?>
      <input type="file" id="tf-photo" name="photo" accept=".jpg,.jpeg,.png,.webp">
    </div>

    <div class="form-field">
      <label><input type="checkbox" name="active" value="1" <?= (!isset($member['active']) || $member['active']) ? 'checked' : '' ?> style="width:auto; margin-right:8px;"> Show on public Team page</label>
    </div>

    <div style="display:flex; gap:10px;">
      <button type="submit" name="action" value="close" class="btn btn-primary">Save &amp; Close</button>
      <button type="submit" name="action" value="new" class="btn btn-navy">Save &amp; Add New</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

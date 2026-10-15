<?php
// Add a lead by hand (phone call, walk-in, WhatsApp). Saved like a website
// enquiry. An agent's own lead stays with them; admins pick an owner or let
// the round-robin choose (the owner gets a first-call follow-up either way).
// admin-header.php is required after POST-handling below (early output breaks header('Location')).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/lead_assign.php';
require_admin();

$page_title = 'Add Lead — Kutumb Admin';

$properties = db()->query('SELECT id, name FROM properties WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
$canChooseOwner = admin_role_at_least('admin');
$team = $canChooseOwner ? db()->query('SELECT id, username, full_name, role FROM admin_users WHERE active = 1 ORDER BY COALESCE(full_name, username)')->fetchAll() : [];
$assignTo = $_POST['assign_to'] ?? 'auto';

$errors = [];
$lead = ['name' => '', 'phone' => '', 'email' => '', 'message' => '', 'property_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    }

    $lead = [
        'name' => trim($_POST['name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'message' => trim($_POST['message'] ?? ''),
        'property_id' => (string)(int)($_POST['property_id'] ?? 0),
    ];

    if ($lead['name'] === '' || mb_strlen($lead['name']) > 120) {
        $errors[] = 'Please enter the lead\'s name.';
    }
    if (mb_strlen($lead['phone']) > 30 || !validate_phone($lead['phone'])) {
        $errors[] = 'Please enter a valid 10-digit phone number.';
    }
    if ($lead['email'] !== '' && !filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email, or leave it blank.';
    }

    if (!$errors) {
        $propertyId = (int)$lead['property_id'] > 0 ? (int)$lead['property_id'] : null;
        // The customer enquired (by phone/in person) — same consent basis as a website enquiry.
        $stmt = db()->prepare('INSERT INTO enquiries (name, phone, phone_e164, email, message, property_id, source, inquiry_at, consent_basis) VALUES (?, ?, ?, ?, ?, ?, "admin", NOW(), "inquiry")');
        $stmt->execute([$lead['name'], $lead['phone'], normalize_to_e164_in($lead['phone']), $lead['email'] !== '' ? $lead['email'] : null,
                        $lead['message'] !== '' ? $lead['message'] : null, $propertyId]);
        $enquiryId = (int)db()->lastInsertId();
        $teamIds = array_map('intval', array_column($team, 'id'));
        if (!$canChooseOwner) {
            lead_assign_to($enquiryId, (int)current_admin()['id'], 'First call — lead added by you');
        } elseif ($assignTo === 'auto') {
            lead_auto_assign($enquiryId);
        } elseif (in_array((int)$assignTo, $teamIds, true)) {
            lead_assign_to($enquiryId, (int)$assignTo, 'First call — lead added from admin');
        }

        if (($_POST['action'] ?? 'close') === 'new') {
            header('Location: /admin/enquiry-add.php?saved=1');
        } else {
            header('Location: /admin/enquiry-view.php?id=' . $enquiryId . '&added=1');
        }
        exit;
    }
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-topbar">
  <h1>Add Lead</h1>
  <a href="/admin/enquiries.php" class="btn btn-navy btn-sm">← Back</a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="form-note success">Lead saved. Add another below, or head back to the list.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <p style="font-size:12.5px; color:var(--ink-soft); margin-top:0;">Only add people who enquired with Kutumb. The person the lead is assigned to gets a "first call" follow-up.</p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="form-field"><label for="la-name">Name<span class="field-required-mark">*</span></label><input type="text" id="la-name" name="name" required maxlength="120" value="<?= e($lead['name']) ?>"></div>
    <div class="form-field"><label for="la-phone">Phone<span class="field-required-mark">*</span></label><input type="tel" id="la-phone" name="phone" required maxlength="30" value="<?= e($lead['phone']) ?>" placeholder="e.g. 98765 43210"></div>
    <div class="form-field"><label for="la-email">Email<span class="field-badge-optional">Optional</span></label><input type="email" id="la-email" name="email" maxlength="160" value="<?= e($lead['email']) ?>"></div>
    <div class="form-field">
      <label for="la-property">Property of interest<span class="field-badge-optional">Optional</span></label>
      <select id="la-property" name="property_id">
        <option value="0">— None —</option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$lead['property_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($canChooseOwner): ?>
    <div class="form-field">
      <label for="la-assign">Assign to</label>
      <select id="la-assign" name="assign_to">
        <option value="auto" <?= $assignTo === 'auto' ? 'selected' : '' ?>>Next agent in turn (auto)</option>
        <option value="none" <?= $assignTo === 'none' ? 'selected' : '' ?>>Unassigned</option>
        <?php foreach ($team as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= $assignTo === (string)$t['id'] ? 'selected' : '' ?>><?= e(($t['full_name'] ?: $t['username']) . ' · ' . ucfirst($t['role'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="form-field"><label for="la-message">Note<span class="field-badge-optional">Optional</span></label><textarea id="la-message" name="message" rows="3"><?= e($lead['message']) ?></textarea></div>
    <div style="display:flex; gap:10px;">
      <button type="submit" name="action" value="close" class="btn btn-primary">Save &amp; Close</button>
      <button type="submit" name="action" value="new" class="btn btn-navy">Save &amp; Add New</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

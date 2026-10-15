<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/lead_assign.php';
require_admin();
require_role('owner');

$page_title = 'Settings — Kutumb Admin';

$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    } elseif (($_POST['form'] ?? 'key') === 'leads') {
        db()->prepare('UPDATE app_settings SET auto_assign_leads = ? WHERE id = 1')->execute([isset($_POST['auto_assign_leads']) ? 1 : 0]);
        $saved = true;
    } elseif (!empty($_POST['clear_key'])) {
        db()->prepare('UPDATE app_settings SET openai_api_key = NULL WHERE id = 1')->execute();
        $saved = true;
    } else {
        $key = trim($_POST['openai_api_key'] ?? '');
        if ($key !== '') {
            db()->prepare('UPDATE app_settings SET openai_api_key = ? WHERE id = 1')->execute([$key]);
        }
        $saved = true;
    }
}

$current = db()->query('SELECT openai_api_key FROM app_settings WHERE id = 1')->fetchColumn();
$autoAssign = (bool)db()->query('SELECT auto_assign_leads FROM app_settings WHERE id = 1')->fetchColumn();
$agents = db()->query("SELECT COALESCE(full_name, username) FROM admin_users WHERE role = 'agent' AND active = 1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-topbar"><h1>Settings</h1></div>

<?php if ($saved): ?><div class="form-note success">Settings saved.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <h4 style="margin-bottom:4px;">OpenAI API Key</h4>
  <p style="font-size:13px; margin-bottom:20px;">
    Powers the site chatbot and the AI features (Home Finder, Neighbourhood questions, Project Assistant). Get a key at
    <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com/api-keys</a>.
    Stored in the database and used only server-side — never sent to visitors' browsers.
  </p>

  <?php if ($current): ?>
    <p style="font-size:13px; color:var(--ink-soft); margin-bottom:12px;">
      Current key: <strong style="font-family:monospace; letter-spacing:1px;"><?= e(mask_api_key($current)) ?></strong>
    </p>
  <?php else: ?>
    <p style="font-size:13px; color:var(--ink-soft); margin-bottom:12px;">No key saved yet — the chatbot and AI features will show a "not configured" message to visitors.</p>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="form" value="key">
    <div class="form-field">
      <label for="settings-key">New API Key <?= $current ? '(leave blank to keep current, or clear below)' : '' ?></label>
      <input type="password" id="settings-key" name="openai_api_key" placeholder="sk-..." autocomplete="off">
    </div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn btn-primary">Save Key</button>
      <?php if ($current): ?>
        <button type="submit" name="clear_key" value="1" class="btn btn-danger" onclick="return confirm('Remove the saved API key? The chatbot and AI features will stop working until a new one is added.');">Clear Key</button>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="admin-card" style="margin-top:24px;">
  <h4 style="margin-bottom:4px;">New leads</h4>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="form" value="leads">
    <div class="form-field">
      <label style="display:flex; gap:10px; align-items:center; font-weight:600;">
        <input type="checkbox" name="auto_assign_leads" value="1" style="width:auto;" <?= $autoAssign ? 'checked' : '' ?>>
        Auto-assign new leads
      </label>
      <p style="font-size:12.5px; color:var(--ink-soft); margin:4px 0 0;">
        Each new lead from the website goes to the next active agent in turn, with a "first call" follow-up due in <?= LEAD_FIRST_CALL_HOURS ?> hours. Admins can reassign any lead from Leads.
        When this is off, new leads arrive Unassigned.
      </p>
      <p style="font-size:12.5px; margin:8px 0 0;">
        Active agents: <?= $agents ? '<strong>' . e(implode(', ', $agents)) . '</strong>' : '<strong>none yet</strong> — add sales people in <a href="/admin/admins.php">Manage Admins</a> with the role "Agent".' ?>
      </p>
    </div>
    <button type="submit" class="btn btn-primary">Save</button>
  </form>
</div>

<div class="admin-card" style="margin-top:24px;">
  <h4 style="margin-bottom:4px;">AI features</h4>
  <p style="font-size:13px; color:var(--ink-soft); margin:0;">Coming soon.</p>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

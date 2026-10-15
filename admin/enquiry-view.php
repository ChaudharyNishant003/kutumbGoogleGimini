<?php
// Single-lead page: contact details, owner and status, and follow-ups.
// Agents only see leads assigned to them.
$page_title = 'Lead — Kutumb Admin';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/leads.php';
require_once __DIR__ . '/../includes/ai-visitor-view.php';
require_admin();

$stmt = db()->prepare(
    'SELECT e.*, p.name AS property_name, a.full_name AS assignee_name, a.username AS assignee_username
     FROM enquiries e LEFT JOIN properties p ON p.id = e.property_id LEFT JOIN admin_users a ON a.id = e.assigned_admin_id
     WHERE e.id = ?'
);
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$lead = $stmt->fetch();
if (!$lead || !lead_visible_to_current_admin($lead)) {
    http_response_code(404);
    require_once __DIR__ . '/../includes/admin-header.php';
    echo '<div class="admin-card"><p>Lead not found. <a href="/admin/enquiries.php">Back to leads</a></p></div>';
    require_once __DIR__ . '/../includes/admin-footer.php';
    exit;
}
$followUps = db()->prepare(
    'SELECT f.*, a.full_name AS owner_name, a.username AS owner_username
     FROM follow_ups f LEFT JOIN admin_users a ON a.id = f.assigned_admin_id
     WHERE f.enquiry_id = ? ORDER BY f.completed, f.due_at'
);
$followUps->execute([(int)$lead['id']]);
$followUps = $followUps->fetchAll();

// Website chats: every chatbot / AI visitor who left this lead's phone number.
$chatVisitors = db()->prepare('SELECT id FROM ai_visitors WHERE enquiry_id = ?');
$chatVisitors->execute([(int)$lead['id']]);
$chatVisitors = $chatVisitors->fetchAll(PDO::FETCH_COLUMN);

$statusOptions = lead_status_options();
$canAssign = admin_role_at_least('admin');
$team = $canAssign ? db()->query('SELECT id, username, full_name FROM admin_users WHERE active = 1 ORDER BY COALESCE(full_name, username)')->fetchAll() : [];
$page_title = $lead['name'] . ' — Lead';

require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="admin-topbar">
  <h1><?= e($lead['name']) ?></h1>
  <div class="admin-topbar-actions"><a href="/admin/enquiries.php" class="btn btn-navy btn-sm">&larr; Leads</a></div>
</div>
<?php if (isset($_GET['added'])): ?><div class="form-note success">Lead added.<?= $lead['assigned_admin_id'] ? ' Assigned to ' . e($lead['assignee_name'] ?: $lead['assignee_username']) . ', with a first-call follow-up.' : ' It is not assigned to anyone yet.' ?></div><?php endif; ?>

<div class="dashboard-analytics-grid">
  <div class="admin-card">
    <h4>Contact</h4>
    <table class="admin-table">
      <tr><th>Phone</th><td><a href="tel:<?= e($lead['phone']) ?>"><?= e($lead['phone']) ?></a></td></tr>
      <tr><th>Email</th><td><?= e($lead['email'] ?? '—') ?></td></tr>
      <tr><th>Source</th><td><?= e($lead['source'] ?? '—') ?></td></tr>
      <tr><th>Property</th><td><?= e($lead['property_name'] ?? '—') ?></td></tr>
      <tr><th>Enquired</th><td><?= e(date('d M Y, h:i A', strtotime($lead['created_at']))) ?></td></tr>
      <?php if ($lead['message']): ?><tr><th>Message</th><td><?= nl2br(e($lead['message'])) ?></td></tr><?php endif; ?>
    </table>
  </div>
  <div class="admin-card">
    <h4>Status &amp; owner</h4>
    <form method="post" action="/admin/enquiry-update.php" class="js-lead-update-form">
      <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="form-field">
        <label for="lv-status">Status</label>
        <select id="lv-status" name="status" class="js-lead-update-field">
          <?php foreach ($statusOptions as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $lead['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($canAssign): ?>
        <div class="form-field">
          <label for="lv-owner">Assigned to</label>
          <select id="lv-owner" name="assigned_admin_id" class="js-lead-update-field">
            <option value="">Unassigned</option>
            <?php foreach ($team as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= (int)($lead['assigned_admin_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['full_name'] ?: $t['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php else: ?>
        <input type="hidden" name="assigned_admin_id" value="<?= (int)($lead['assigned_admin_id'] ?? 0) ?>">
        <p style="font-size:13px;">Assigned to <strong><?= e($lead['assignee_name'] ?: ($lead['assignee_username'] ?? 'Unassigned')) ?></strong></p>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="admin-card">
  <h4>Follow-ups</h4>
  <?php if (!$followUps): ?><p style="color:var(--ink-soft); font-size:13px;">None yet.</p><?php else: ?>
  <table class="admin-table">
    <thead><tr><th>Due</th><th>Note</th><th>Owner</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($followUps as $fu): ?>
      <tr>
        <td><?= e(date('d M Y, h:i A', strtotime($fu['due_at']))) ?></td>
        <td><?= e($fu['note'] ?? '') ?></td>
        <td><?= e($fu['owner_name'] ?: ($fu['owner_username'] ?? '—')) ?></td>
        <td><?php if ($fu['completed']): ?><span class="ai-status ai-status-answered">done</span>
            <?php elseif (strtotime($fu['due_at']) < time()): ?><span class="status-badge overdue">overdue</span>
            <?php else: ?><span class="ai-status ai-status-fallback">pending</span><?php endif; ?></td>
        <td><?php if (!$fu['completed']): ?>
          <form method="post" action="/admin/follow-up-complete.php" style="margin:0;">
            <input type="hidden" name="id" value="<?= (int)$fu['id'] ?>">
            <input type="hidden" name="back" value="lead">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="btn btn-primary btn-sm">Mark Done</button>
          </form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <form method="post" action="/admin/follow-up-add.php" class="lead-followup-add" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-top:14px;">
    <input type="hidden" name="enquiry_id" value="<?= (int)$lead['id'] ?>">
    <input type="hidden" name="back" value="lead">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="form-field" style="margin:0;"><label for="lv-due">Next follow-up</label><input type="datetime-local" id="lv-due" name="due_at" required></div>
    <div class="form-field" style="margin:0; flex:1; min-width:180px;"><label for="lv-note">Note</label><input type="text" id="lv-note" name="note" maxlength="500" placeholder="e.g. Call back re: budget"></div>
    <button type="submit" class="btn btn-primary btn-sm">Add</button>
  </form>
</div>

<?php if ($chatVisitors): ?>
<div class="admin-card">
  <h4 style="margin-bottom:12px;">Website chats</h4>
  <?= render_ai_conversation($chatVisitors) ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

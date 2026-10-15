<?php
// One website visitor's AI conversations, every visit, every feature. Visitors who left the
// same phone number (another device or browser) are shown together under their lead.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ai-visitor.php';
require_once __DIR__ . '/../includes/ai-visitor-view.php';
require_role('admin');

$stmt = db()->prepare('SELECT v.*, e.name AS lead_name, e.status AS lead_status FROM ai_visitors v LEFT JOIN enquiries e ON e.id = v.enquiry_id WHERE v.id = ?');
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$visitor = $stmt->fetch();

$page_title = 'Website visitor — Kutumb Admin';
require_once __DIR__ . '/../includes/admin-header.php';

if (!$visitor) {
    echo '<div class="admin-card"><p>Visitor not found. <a href="/admin/ai-log.php">Back to the AI Question Log</a></p></div>';
    require_once __DIR__ . '/../includes/admin-footer.php';
    exit;
}

$group = [$visitor];
if ($visitor['enquiry_id']) {
    $same = db()->prepare('SELECT * FROM ai_visitors WHERE enquiry_id = ? ORDER BY first_seen_at');
    $same->execute([$visitor['enquiry_id']]);
    $group = $same->fetchAll();
}
$ids = array_column($group, 'id');
$questions = array_sum(array_column($group, 'question_count'));
$visits = array_sum(array_column($group, 'visit_count'));
$firstSeen = min(array_column($group, 'first_seen_at'));
$lastSeen = max(array_column($group, 'last_seen_at'));
$requirement = ai_visitor_requirement_text($visitor);
?>
<div class="admin-topbar">
  <h1><?= e($visitor['name'] ?: 'Anonymous visitor #' . (int)$visitor['id']) ?></h1>
  <div class="admin-topbar-actions"><a href="/admin/ai-log.php" class="btn btn-navy btn-sm">&larr; AI Question Log</a></div>
</div>

<div class="admin-card">
  <table class="admin-table">
    <tr><th>Phone</th><td><?= $visitor['phone'] ? '<a href="tel:' . e($visitor['phone']) . '">' . e($visitor['phone']) . '</a>' : 'Not given yet' ?></td></tr>
    <tr><th>Lead</th><td><?= $visitor['enquiry_id'] ? '<a href="/admin/enquiry-view.php?id=' . (int)$visitor['enquiry_id'] . '">' . e($visitor['lead_name']) . '</a> (' . e($visitor['lead_status']) . ')' : '—' ?></td></tr>
    <tr><th>Requirement</th><td><?= $requirement !== '' ? e($requirement) : '—' ?></td></tr>
    <tr><th>Visits</th><td><?= (int)$visits ?> · <?= (int)$questions ?> question<?= $questions == 1 ? '' : 's' ?><?= count($group) > 1 ? ' · ' . count($group) . ' devices/browsers' : '' ?></td></tr>
    <tr><th>First seen</th><td><?= e(date('d M Y, h:i A', strtotime($firstSeen))) ?></td></tr>
    <tr><th>Last seen</th><td><?= e(date('d M Y, h:i A', strtotime($lastSeen))) ?></td></tr>
  </table>
</div>

<div class="admin-card">
  <h4 style="margin-bottom:12px;">Conversation</h4>
  <?= render_ai_conversation($ids) ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

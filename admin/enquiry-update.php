<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/leads.php';
require_once __DIR__ . '/../includes/lead_assign.php';
require_admin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Session expired — please reload the page.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$statusOptions = lead_status_options();
$status = $_POST['status'] ?? '';
$assignedRaw = trim($_POST['assigned_admin_id'] ?? '');
// The status form sends "0" for an unassigned lead — that means nobody, not
// admin #0 (which the foreign key rejects, silently failing every status
// change on unassigned leads).
$assignedId = (int)$assignedRaw > 0 ? (int)$assignedRaw : null;

if (!$id || !isset($statusOptions[$status])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid lead or status.']);
    exit;
}

$lead = db()->prepare('SELECT id, assigned_admin_id FROM enquiries WHERE id = ?');
$lead->execute([$id]);
$lead = $lead->fetch();
if (!$lead || !lead_visible_to_current_admin($lead)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Lead not found.']);
    exit;
}
// Only admins and owners hand leads around; an agent can update the status of their own lead.
if (!admin_role_at_least('admin')) {
    $assignedId = $lead['assigned_admin_id'] !== null ? (int)$lead['assigned_admin_id'] : null;
}

$stmt = db()->prepare('UPDATE enquiries SET status = ?, assigned_admin_id = ? WHERE id = ?');
$stmt->execute([$status, $assignedId, $id]);

// New owner: the lead's open follow-ups move with it, or they get a first call if there were none.
if ($assignedId !== null && $assignedId !== ($lead['assigned_admin_id'] !== null ? (int)$lead['assigned_admin_id'] : null)) {
    $moved = db()->prepare('UPDATE follow_ups SET assigned_admin_id = ? WHERE enquiry_id = ? AND completed = 0');
    $moved->execute([$assignedId, $id]);
    if ($moved->rowCount() === 0) {
        db()->prepare("INSERT INTO follow_ups (enquiry_id, assigned_admin_id, due_at, note, kind) VALUES (?, ?, ?, ?, 'callback')")
            ->execute([$id, $assignedId, date('Y-m-d H:i:s', time() + LEAD_FIRST_CALL_HOURS * 3600), 'First call — lead assigned to you']);
    }
}

echo json_encode(['success' => true]);

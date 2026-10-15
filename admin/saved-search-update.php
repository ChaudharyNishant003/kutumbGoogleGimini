<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/leads.php';
require_admin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Session expired — please reload the page.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$statusOptions = saved_search_status_options();
$status = $_POST['status'] ?? '';
$assignedRaw = trim($_POST['assigned_admin_id'] ?? '');
// "0" means nobody (the foreign key rejects admin #0), same as on Leads.
$assignedId = (int)$assignedRaw > 0 ? (int)$assignedRaw : null;

if (!$id || !isset($statusOptions[$status])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid saved search or status.']);
    exit;
}

$search = db()->prepare('SELECT id, assigned_admin_id FROM saved_searches WHERE id = ?');
$search->execute([$id]);
$search = $search->fetch();
if (!$search || !lead_visible_to_current_admin($search)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Saved search not found.']);
    exit;
}
// Only admins and owners reassign; an agent can update the status of their own saved search.
if (!admin_role_at_least('admin')) {
    $assignedId = $search['assigned_admin_id'] !== null ? (int)$search['assigned_admin_id'] : null;
}

$stmt = db()->prepare('UPDATE saved_searches SET status = ?, assigned_admin_id = ? WHERE id = ?');
$stmt->execute([$status, $assignedId, $id]);

echo json_encode(['success' => true]);

<?php
// JSON endpoint behind "Auto-fill nearby places": {action: "list"} returns the live project ids,
// {property_id} fills one project (looking up its location first if it has none).
// The admin page calls it one project at a time, so no single request runs long.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/places-auto.php';
require_role('admin');

header('Content-Type: application/json');
header('Cache-Control: no-store');

$body = json_decode((string)file_get_contents('php://input'), true) ?: [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($body['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Session expired — please reload the page.']);
    exit;
}

if (($body['action'] ?? '') === 'list') {
    $ids = db()->query('SELECT id FROM properties WHERE deleted_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['ok' => true, 'ids' => array_map('intval', $ids)]);
    exit;
}

set_time_limit(120);
try {
    echo json_encode(places_autofill_property((int)($body['property_id'] ?? 0)), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Places auto-fill failed: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Could not reach OpenStreetMap — please try again later.']);
}

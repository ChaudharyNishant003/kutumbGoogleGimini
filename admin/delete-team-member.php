<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Session expired — please reload the page.']);
        exit;
    }
    header('Location: /admin/team.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    bin_soft_delete('team_member', $id);
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

header('Location: /admin/team.php?deleted=1');
exit;

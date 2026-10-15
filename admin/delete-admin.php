<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    header('Location: /admin/admins.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id && $id !== (int)current_admin()['id']) {
    // Safety rule: an Owner can never remove another Owner through the UI,
    // under any circumstance — deliberately, so ownership access can't be
    // stripped by one Owner acting alone (accidentally or otherwise).
    // Removing an Owner account is only possible via a direct backend/code
    // change, which requires mutual agreement among owners in practice.
    $stmt = db()->prepare('SELECT role FROM admin_users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if ($target && $target['role'] !== 'owner') {
        db()->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
        header('Location: /admin/admins.php?deleted=1');
        exit;
    }
}

header('Location: /admin/admins.php');
exit;

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    header('Location: /admin/admins.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$name = '';
$status = '';
if ($id && $id !== (int)current_admin()['id']) {
    $stmt = db()->prepare('UPDATE admin_users SET active = 1 - active WHERE id = ?');
    $stmt->execute([$id]);

    $row = db()->prepare('SELECT username, full_name, active FROM admin_users WHERE id = ?');
    $row->execute([$id]);
    if ($admin = $row->fetch()) {
        $name = $admin['full_name'] ?: $admin['username'];
        $status = $admin['active'] ? 'active' : 'inactive';
    }
}

$query = 'deactivated=1';
if ($name !== '') {
    $query .= '&admin_name=' . rawurlencode($name) . '&admin_status=' . $status . '&admin_id=' . $id;
}
header('Location: /admin/admins.php?' . $query);
exit;

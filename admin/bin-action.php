<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    header('Location: /admin/bin.php');
    exit;
}

$action = $_POST['action'] ?? '';
if ($action === 'purge_expired') {
    header('Location: /admin/bin.php?purged_expired=' . bin_purge_expired());
    exit;
}
$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if ($id && array_key_exists($type, bin_config())) {
    if ($action === 'restore') {
        bin_restore($type, $id);
        header('Location: /admin/bin.php?restored=1');
        exit;
    }
    if ($action === 'purge') {
        bin_purge_item($type, $id);
        header('Location: /admin/bin.php?purged=1');
        exit;
    }
}

header('Location: /admin/bin.php');
exit;

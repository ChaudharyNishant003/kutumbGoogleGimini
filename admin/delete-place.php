<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    header('Location: /admin/places.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    bin_soft_delete('place', $id);
}

header('Location: /admin/places.php?deleted=1');
exit;

<?php
// "Mark verified today": stamps a project's (or every live project's) "information verified on"
// date, which the project assistant shows as the source of its answers.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    header('Location: /admin/properties.php');
    exit;
}

if (!empty($_POST['all'])) {
    $n = db()->exec('UPDATE properties SET info_verified_on = CURDATE() WHERE deleted_at IS NULL');
    header('Location: /admin/properties.php?verified=' . (int)$n);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    db()->prepare('UPDATE properties SET info_verified_on = CURDATE() WHERE id = ? AND deleted_at IS NULL')->execute([$id]);
}
header('Location: /admin/properties.php?verified=1');
exit;

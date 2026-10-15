<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    header('Location: /admin/properties.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$propertyId = (int)($_POST['property_id'] ?? 0);

if ($id) {
    $stmt = db()->prepare('SELECT file_path FROM property_documents WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        delete_uploaded_file(DOCS_UPLOAD_DIR, $row['file_path']);
    }
    db()->prepare('DELETE FROM property_documents WHERE id = ?')->execute([$id]);
}

header('Location: /admin/property-documents.php?property_id=' . $propertyId);
exit;

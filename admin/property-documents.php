<?php
// admin-header.php is required after all header('Location: ...') calls below,
// not here — see the comment in property-form.php for why (early output
// breaks header('Location'), including the "property not found" redirect
// just below).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$page_title = 'Documents — Kutumb Admin';

$propertyId = (int)($_GET['property_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM properties WHERE id = ?');
$stmt->execute([$propertyId]);
$property = $stmt->fetch();

if (!$property) {
    header('Location: /admin/properties.php');
    exit;
}

$docTypes = ['rera' => 'RERA Certificate', 'brochure' => 'Brochure', 'floor-plan' => 'Floor Plan', 'other' => 'Other'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    } else {
        $docType = $_POST['doc_type'] ?? 'other';
        $label = trim($_POST['label'] ?? '');
        if (!isset($docTypes[$docType])) $docType = 'other';
        if ($label === '') $label = $docTypes[$docType];

        if (empty($_FILES['document']['name'])) {
            $errors[] = 'Please choose a PDF file to upload.';
        } else {
            $uploadError = null;
            $newName = handle_file_upload($_FILES['document'], DOCS_UPLOAD_DIR, ['application/pdf' => 'pdf'], MAX_UPLOAD_BYTES, $uploadError);
            if ($uploadError) {
                $errors[] = $uploadError;
            } elseif ($newName) {
                $stmt = db()->prepare('INSERT INTO property_documents (property_id, doc_type, label, file_path, file_size) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$propertyId, $docType, $label, $newName, $_FILES['document']['size']]);
                header('Location: /admin/property-documents.php?property_id=' . $propertyId . '&saved=1');
                exit;
            }
        }
    }
}

$documents = db()->prepare('SELECT * FROM property_documents WHERE property_id = ? ORDER BY uploaded_at DESC');
$documents->execute([$propertyId]);
$documents = $documents->fetchAll();

$saved = isset($_GET['saved']);

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-topbar">
  <h1>Documents — <?= e($property['name']) ?></h1>
  <a href="/admin/property-form.php?id=<?= (int)$property['id'] ?>" class="btn btn-navy btn-sm">← Back to Property</a>
</div>

<?php if ($saved): ?><div class="form-note success">Document uploaded.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="form-field">
      <label for="pd-type">Document Type</label>
      <select id="pd-type" name="doc_type">
        <?php foreach ($docTypes as $val => $label): ?><option value="<?= e($val) ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label for="pd-label">Label (optional)</label><input type="text" id="pd-label" name="label" placeholder="e.g. RERA Certificate — Phase 2"></div>
    <div class="form-field"><label for="pd-file">PDF File</label><input type="file" id="pd-file" name="document" accept=".pdf"></div>
    <button type="submit" class="btn btn-primary">Upload</button>
  </form>
</div>

<div class="admin-card">
  <?php if (!$documents): ?>
    <p style="color:var(--ink-soft)">No documents uploaded yet.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Type</th><th>Label</th><th>Size</th><th>Uploaded</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($documents as $doc): ?>
          <tr>
            <td><?= e($docTypes[$doc['doc_type']] ?? ucfirst($doc['doc_type'])) ?></td>
            <td><a href="<?= DOCS_UPLOAD_URL . e($doc['file_path']) ?>" target="_blank"><?= e($doc['label']) ?></a></td>
            <td><?= round($doc['file_size'] / 1024) ?> KB</td>
            <td><?= e(date('d M Y', strtotime($doc['uploaded_at']))) ?></td>
            <td>
              <form method="post" action="/admin/delete-property-document.php" onsubmit="return confirm('Delete this document?');">
                <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
                <input type="hidden" name="property_id" value="<?= (int)$propertyId ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

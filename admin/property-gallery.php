<?php
// admin-header.php is required after all header('Location: ...') calls below,
// not here — see the comment in property-form.php for why (early output
// breaks header('Location'), including the "property not found" redirect
// just below).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$page_title = 'Photo Gallery — Kutumb Admin';

$propertyId = (int)($_GET['property_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM properties WHERE id = ?');
$stmt->execute([$propertyId]);
$property = $stmt->fetch();

if (!$property) {
    header('Location: /admin/properties.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    } elseif (!empty($_FILES['images']['name'][0])) {
        $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM property_images WHERE property_id = ?');
        $stmt->execute([$propertyId]);
        $nextOrder = (int)$stmt->fetchColumn() + 1;

        $insert = db()->prepare('INSERT INTO property_images (property_id, image_path, caption, sort_order) VALUES (?, ?, ?, ?)');
        $count = count($_FILES['images']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $file = [
                'name' => $_FILES['images']['name'][$i],
                'type' => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error' => $_FILES['images']['error'][$i],
                'size' => $_FILES['images']['size'][$i],
            ];
            $uploadError = null;
            $newName = handle_file_upload($file, UPLOAD_DIR, image_mime_map(), MAX_UPLOAD_BYTES, $uploadError);
            if ($newName) {
                generate_image_card_derivative(UPLOAD_DIR, $newName);
                $insert->execute([$propertyId, $newName, null, $nextOrder]);
                $nextOrder++;
            } elseif ($uploadError) {
                $errors[] = $file['name'] . ': ' . $uploadError;
            }
        }
        if (!$errors) {
            header('Location: /admin/property-gallery.php?property_id=' . $propertyId . '&saved=1');
            exit;
        }
    }
}

$images = db()->prepare('SELECT * FROM property_images WHERE property_id = ? ORDER BY sort_order, id');
$images->execute([$propertyId]);
$images = $images->fetchAll();

$saved = isset($_GET['saved']);

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-topbar">
  <h1>Photo Gallery — <?= e($property['name']) ?></h1>
  <a href="/admin/property-form.php?id=<?= (int)$property['id'] ?>" class="btn btn-navy btn-sm">← Back to Property</a>
</div>

<?php if ($saved): ?><div class="form-note success">Photos added.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="form-field"><label for="pg-images">Add Photos (JPG, PNG or WEBP — select multiple)</label><input type="file" id="pg-images" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple></div>
    <button type="submit" class="btn btn-primary">Upload</button>
  </form>
</div>

<div class="admin-card">
  <?php if (!$images): ?>
    <p style="color:var(--ink-soft)">No gallery photos yet — the cover photo on the property form is used until you add some.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th></th><th>Order</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($images as $img): ?>
          <tr>
            <td><img class="thumb" src="<?= UPLOAD_URL . e($img['image_path']) ?>" alt=""></td>
            <td><?= (int)$img['sort_order'] ?></td>
            <td>
              <form method="post" action="/admin/delete-property-image.php" onsubmit="return confirm('Remove this photo?');">
                <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                <input type="hidden" name="property_id" value="<?= (int)$propertyId ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" style="background:none;border:none;color:#d64545;cursor:pointer;padding:0;font-size:13px;">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

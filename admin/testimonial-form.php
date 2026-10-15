<?php
// admin-header.php is required after POST-handling below, not here — see the
// comment in property-form.php for why (early output breaks header('Location')).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$page_title = 'Testimonial — Kutumb Admin';

$id = (int)($_GET['id'] ?? 0);
$testimonial = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM testimonials WHERE id = ?');
    $stmt->execute([$id]);
    $testimonial = $stmt->fetch();
}

$properties = db()->query('SELECT id, name FROM properties WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    }

    $clientName = trim($_POST['client_name'] ?? '');
    $clientRole = trim($_POST['client_role'] ?? '');
    $quote = trim($_POST['quote'] ?? '');
    $rating = !empty($_POST['rating']) ? min(5, max(1, (int)$_POST['rating'])) : null;
    $propertyId = !empty($_POST['property_id']) ? (int)$_POST['property_id'] : null;
    $featuredHome = isset($_POST['featured_home']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if (!validate_name($clientName) || $quote === '') {
        $errors[] = 'Please fill in the client name and quote.';
    }

    $photoPath = $testimonial['photo_path'] ?? null;
    if (!empty($_FILES['photo']['name'])) {
        $uploadError = null;
        $newName = handle_file_upload($_FILES['photo'], UPLOAD_DIR, image_mime_map(), MAX_UPLOAD_BYTES, $uploadError);
        if ($uploadError) {
            $errors[] = $uploadError;
        } elseif ($newName) {
            delete_uploaded_file(UPLOAD_DIR, $photoPath);
            $photoPath = $newName;
        }
    }

    if (!$errors) {
        $isNew = !$testimonial;
        if ($testimonial) {
            $stmt = db()->prepare('UPDATE testimonials SET client_name=?, client_role=?, quote=?, rating=?, photo_path=?, property_id=?, featured_home=?, sort_order=? WHERE id=?');
            $stmt->execute([$clientName, $clientRole ?: null, $quote, $rating, $photoPath, $propertyId, $featuredHome, $sortOrder, $testimonial['id']]);
            $testimonialId = $testimonial['id'];
        } else {
            $stmt = db()->prepare('INSERT INTO testimonials (client_name, client_role, quote, rating, photo_path, property_id, featured_home, sort_order) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$clientName, $clientRole ?: null, $quote, $rating, $photoPath, $propertyId, $featuredHome, $sortOrder]);
            $testimonialId = (int)db()->lastInsertId();
        }
        if (($_POST['action'] ?? 'close') === 'new') {
            header('Location: /admin/testimonial-form.php?saved=1');
        } else {
            header('Location: /admin/testimonials.php?saved=1&id=' . $testimonialId . '&new=' . ($isNew ? '1' : '0'));
        }
        exit;
    }

    $testimonial = [
        'id' => $testimonial['id'] ?? null, 'client_name' => $clientName, 'client_role' => $clientRole,
        'quote' => $quote, 'rating' => $rating, 'photo_path' => $photoPath, 'property_id' => $propertyId,
        'featured_home' => $featuredHome, 'sort_order' => $sortOrder,
    ];
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-topbar">
  <h1><?= $testimonial && !empty($testimonial['id']) ? 'Edit Testimonial' : 'Add Testimonial' ?></h1>
  <a href="/admin/testimonials.php" class="btn btn-navy btn-sm">← Back</a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="form-note success">Testimonial saved successfully. Add another below, or head back to the list.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1"><label for="tsf-name">Client Name<span class="field-required-mark">*</span></label><input type="text" id="tsf-name" name="client_name" required value="<?= e($testimonial['client_name'] ?? '') ?>"></div>
      <div class="form-field" style="flex:1"><label for="tsf-role">Client Role / Location<span class="field-badge-optional">Optional</span></label><input type="text" id="tsf-role" name="client_role" value="<?= e($testimonial['client_role'] ?? '') ?>" placeholder="e.g. Sector 54, Golf Course Road"></div>
    </div>

    <div class="form-field"><label for="tsf-quote">Quote<span class="field-required-mark">*</span></label><textarea id="tsf-quote" name="quote" required><?= e($testimonial['quote'] ?? '') ?></textarea></div>

    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1">
        <label for="tsf-rating">Rating<span class="field-badge-optional">Optional</span></label>
        <select id="tsf-rating" name="rating">
          <option value="">— None —</option>
          <?php for ($r = 5; $r >= 1; $r--): ?>
            <option value="<?= $r ?>" <?= (isset($testimonial['rating']) && (int)$testimonial['rating'] === $r) ? 'selected' : '' ?>><?= $r ?> star<?= $r === 1 ? '' : 's' ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-field" style="flex:1">
        <label for="tsf-property">Related Property<span class="field-badge-optional">Optional</span></label>
        <select id="tsf-property" name="property_id">
          <option value="">— None —</option>
          <?php foreach ($properties as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (isset($testimonial['property_id']) && $testimonial['property_id'] == $p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field" style="flex:1"><label for="tsf-sort">Sort Order<span class="field-badge-optional">Optional</span></label><input type="number" id="tsf-sort" name="sort_order" placeholder="0" <?= isset($testimonial['sort_order']) ? 'value="' . e((string)$testimonial['sort_order']) . '"' : '' ?>></div>
    </div>

    <div class="form-field">
      <label for="tsf-photo">Photo<span class="field-badge-optional">Optional</span> <?= !empty($testimonial['photo_path']) ? '(replace existing)' : '' ?></label>
      <?php if (!empty($testimonial['photo_path'])): ?>
        <img class="thumb" style="width:64px;height:64px;margin-bottom:8px;" src="<?= UPLOAD_URL . e($testimonial['photo_path']) ?>" alt="">
      <?php endif; ?>
      <input type="file" id="tsf-photo" name="photo" accept=".jpg,.jpeg,.png,.webp">
    </div>

    <div class="form-field">
      <label><input type="checkbox" name="featured_home" value="1" <?= !empty($testimonial['featured_home']) ? 'checked' : '' ?> style="width:auto; margin-right:8px;"> Feature on homepage</label>
    </div>

    <div style="display:flex; gap:10px;">
      <button type="submit" name="action" value="close" class="btn btn-primary">Save &amp; Close</button>
      <button type="submit" name="action" value="new" class="btn btn-navy">Save &amp; Add New</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

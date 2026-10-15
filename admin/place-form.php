<?php
// admin-header.php is required after the redirect below (see property-form.php).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ai-places.php';
require_role('admin');

$categories = place_categories();
$id = (int)($_GET['id'] ?? 0);
$place = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM places WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $place = $stmt->fetch();
    if (!$place) {
        header('Location: /admin/places.php');
        exit;
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? '';
    $lat = trim($_POST['lat'] ?? '');
    $lng = trim($_POST['lng'] ?? '');
    $source = trim($_POST['source'] ?? '');
    $verifiedOn = trim($_POST['verified_on'] ?? '');

    if (!csrf_check($_POST['csrf_token'] ?? null)) $errors[] = 'Session expired — please try again.';
    if ($name === '' || mb_strlen($name) > 160) $errors[] = 'Name is required (max 160 characters).';
    if (!isset($categories[$category])) $errors[] = 'Choose a category.';
    if (!is_numeric($lat) || abs((float)$lat) > 90 || !is_numeric($lng) || abs((float)$lng) > 180) {
        $errors[] = 'A valid location is required — paste a Google Maps link to fill it.';
    }
    if ($source === '' || mb_strlen($source) > 160) $errors[] = 'Source is required (e.g. "Google Maps", "School website") — max 160 characters.';
    $date = DateTime::createFromFormat('Y-m-d', $verifiedOn);
    if (!$date || $date->format('Y-m-d') !== $verifiedOn) {
        $errors[] = 'Verified-on date is required.';
    } elseif ($date > new DateTime('tomorrow')) {
        $errors[] = 'Verified-on date can’t be in the future.';
    }

    if (!$errors) {
        if ($place) {
            db()->prepare('UPDATE places SET name=?, category=?, lat=?, lng=?, source=?, verified_on=? WHERE id=?')
                ->execute([$name, $category, (float)$lat, (float)$lng, $source, $verifiedOn, $place['id']]);
            $savedId = (int)$place['id'];
        } else {
            db()->prepare('INSERT INTO places (name, category, lat, lng, source, verified_on) VALUES (?,?,?,?,?,?)')
                ->execute([$name, $category, (float)$lat, (float)$lng, $source, $verifiedOn]);
            $savedId = (int)db()->lastInsertId();
        }
        header('Location: /admin/places.php?saved=1&id=' . $savedId);
        exit;
    }
    $place = ['id' => $place['id'] ?? 0, 'name' => $name, 'category' => $category, 'lat' => $lat, 'lng' => $lng, 'source' => $source, 'verified_on' => $verifiedOn];
}

$page_title = ($id ? 'Edit' : 'Add') . ' Place — Kutumb Admin';
require_once __DIR__ . '/../includes/admin-header.php';
$mlLatId = 'pl-lat';
$mlLngId = 'pl-lng';
?>

<div class="admin-topbar">
  <h1><?= $id ? 'Edit Place' : 'Add Place' ?></h1>
  <a href="/admin/places.php" class="btn btn-navy btn-sm">← Back to Places</a>
</div>

<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card" style="max-width:720px;">
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="form-field"><label for="pl-name">Name</label><input type="text" id="pl-name" name="name" maxlength="160" required value="<?= e($place['name'] ?? '') ?>" placeholder="e.g. DPS Sector 45"></div>
    <div class="form-field">
      <label for="pl-category">Category</label>
      <select id="pl-category" name="category" required>
        <option value="">Choose…</option>
        <?php foreach ($categories as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= ($place['category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php include __DIR__ . '/../includes/admin-maps-link.php'; ?>
    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1"><label for="pl-lat">Latitude</label><input type="text" id="pl-lat" name="lat" inputmode="decimal" required value="<?= e((string)($place['lat'] ?? '')) ?>" placeholder="e.g. 28.4595"></div>
      <div class="form-field" style="flex:1"><label for="pl-lng">Longitude</label><input type="text" id="pl-lng" name="lng" inputmode="decimal" required value="<?= e((string)($place['lng'] ?? '')) ?>" placeholder="e.g. 77.0266"></div>
    </div>
    <div class="form-field"><label for="pl-source">Source</label><input type="text" id="pl-source" name="source" maxlength="160" required value="<?= e($place['source'] ?? 'Google Maps') ?>"></div>
    <div class="form-field">
      <label for="pl-verified">Verified on</label>
      <input type="date" id="pl-verified" name="verified_on" required max="<?= date('Y-m-d') ?>" value="<?= e($place['verified_on'] ?? date('Y-m-d')) ?>">
      <p style="font-size:12.5px; color:var(--ink-soft); margin:4px 0 0;">Shown to visitors next to the distance ("Verified 3 Aug 2026"). Update it whenever you re-check the place.</p>
    </div>
    <button type="submit" class="btn btn-primary"><?= $id ? 'Save Changes' : 'Add Place' ?></button>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

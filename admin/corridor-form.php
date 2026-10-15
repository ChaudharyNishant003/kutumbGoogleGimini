<?php
// admin-header.php is required after POST-handling below, not here — see the
// comment in property-form.php for why (early output breaks header('Location')).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$page_title = 'Corridor — Kutumb Admin';

$id = (int)($_GET['id'] ?? 0);
$corridor = null;
$faqs = [];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM corridors WHERE id = ?');
    $stmt->execute([$id]);
    $corridor = $stmt->fetch();
    if ($corridor) {
        $faqStmt = db()->prepare('SELECT * FROM corridor_faqs WHERE corridor_id = ? ORDER BY sort_order, id');
        $faqStmt->execute([$id]);
        $faqs = $faqStmt->fetchAll();
    }
}

$tiers = ['luxury' => 'Luxury', 'premium' => 'Premium', 'standard' => 'Standard'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $tier = isset($tiers[$_POST['tier'] ?? '']) ? $_POST['tier'] : 'standard';
    $avgPriceBand = trim($_POST['avg_price_band'] ?? '');
    $priceGrowthLabel = trim($_POST['price_growth_label'] ?? '');
    $ratesVerifiedRaw = trim($_POST['rates_verified_on'] ?? '');
    $ratesVerifiedOn = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ratesVerifiedRaw) && $ratesVerifiedRaw <= date('Y-m-d')) ? $ratesVerifiedRaw : null;
    $description = trim($_POST['description'] ?? '');
    $longDescription = trim($_POST['long_description'] ?? '');
    $lat = trim($_POST['lat'] ?? '');
    $lng = trim($_POST['lng'] ?? '');
    $faqQuestions = $_POST['faq_question'] ?? [];
    $faqAnswers = $_POST['faq_answer'] ?? [];

    if ($name === '' || $avgPriceBand === '' || $description === '') {
        $errors[] = 'Please fill in name, average price band and description.';
    }

    // Unlike every other form here, a corridor's slug has no random suffix —
    // corridor URLs are meant to be clean (e.g. /sector.php?slug=sohna-road),
    // so a duplicate name has to be caught explicitly instead of letting the
    // database's unique constraint throw an uncaught, request-ending error.
    if ($name !== '') {
        $slugCheck = slugify($name);
        $dupStmt = db()->prepare('SELECT id FROM corridors WHERE slug = ? AND id <=> ? IS NOT TRUE');
        $dupStmt->execute([$slugCheck, $corridor['id'] ?? null]);
        if ($dupStmt->fetch()) {
            $errors[] = 'A corridor named "' . $name . '" already exists. Choose a different name.';
        }
    }

    $heroPath = $corridor['hero_image_path'] ?? null;
    if (!empty($_FILES['hero_image']['name'])) {
        $uploadError = null;
        $newName = handle_file_upload($_FILES['hero_image'], UPLOAD_DIR, image_mime_map(), MAX_UPLOAD_BYTES, $uploadError);
        if ($uploadError) {
            $errors[] = $uploadError;
        } elseif ($newName) {
            delete_uploaded_file(UPLOAD_DIR, $heroPath);
            $heroPath = $newName;
        }
    }

    if (!$errors) {
        $isNew = !$corridor;
        $latVal = $lat !== '' ? (float)$lat : null;
        $lngVal = $lng !== '' ? (float)$lng : null;

        if ($corridor) {
            $stmt = db()->prepare('UPDATE corridors SET name=?, avg_price_band=?, tier=?, price_growth_label=?, rates_verified_on=?, description=?, long_description=?, hero_image_path=?, lat=?, lng=? WHERE id=?');
            $stmt->execute([$name, $avgPriceBand, $tier, $priceGrowthLabel ?: null, $ratesVerifiedOn, $description, $longDescription ?: null, $heroPath, $latVal, $lngVal, $corridor['id']]);
            $corridorId = $corridor['id'];
        } else {
            $slug = slugify($name);
            $stmt = db()->prepare('INSERT INTO corridors (name, slug, avg_price_band, tier, price_growth_label, rates_verified_on, description, long_description, hero_image_path, lat, lng) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$name, $slug, $avgPriceBand, $tier, $priceGrowthLabel ?: null, $ratesVerifiedOn, $description, $longDescription ?: null, $heroPath, $latVal, $lngVal]);
            $corridorId = (int)db()->lastInsertId();
        }

        // Simplest-correct FAQ sync: replace all rows for this corridor on every save.
        db()->prepare('DELETE FROM corridor_faqs WHERE corridor_id = ?')->execute([$corridorId]);
        $insertFaq = db()->prepare('INSERT INTO corridor_faqs (corridor_id, question, answer, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($faqQuestions as $idx => $q) {
            $q = trim($q);
            $a = trim($faqAnswers[$idx] ?? '');
            if ($q !== '' && $a !== '') {
                $insertFaq->execute([$corridorId, $q, $a, $idx]);
            }
        }

        if (($_POST['action'] ?? 'close') === 'new') {
            header('Location: /admin/corridor-form.php?saved=1');
        } else {
            header('Location: /admin/corridors.php?saved=1&id=' . $corridorId . '&new=' . ($isNew ? '1' : '0'));
        }
        exit;
    }

    $corridor = [
        'id' => $corridor['id'] ?? null, 'name' => $name, 'avg_price_band' => $avgPriceBand,
        'tier' => $tier, 'price_growth_label' => $priceGrowthLabel, 'rates_verified_on' => $ratesVerifiedOn,
        'description' => $description, 'long_description' => $longDescription,
        'lat' => $lat, 'lng' => $lng, 'hero_image_path' => $heroPath,
    ];
    $faqs = [];
    foreach ($faqQuestions as $idx => $q) {
        $faqs[] = ['question' => $q, 'answer' => $faqAnswers[$idx] ?? ''];
    }
}

while (count($faqs) < 3) {
    $faqs[] = ['question' => '', 'answer' => ''];
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-topbar">
  <h1><?= $corridor && !empty($corridor['id']) ? 'Edit Corridor' : 'Add Corridor' ?></h1>
  <a href="/admin/corridors.php" class="btn btn-navy btn-sm">← Back</a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="form-note success">Corridor saved successfully. Add another below, or head back to the list.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card autofill-card">
  <label for="cf-autofill-text">✨ Paste &amp; Auto-fill</label>
  <p>Paste a corridor writeup, market note, or any description — we'll try to fill the fields below from it. Always review before saving; nothing here is saved until you click Save.</p>
  <textarea id="cf-autofill-text" rows="5" placeholder="Paste corridor details here..."></textarea>
  <div class="autofill-actions">
    <button type="button" id="cf-autofill-btn" class="btn btn-navy btn-sm">Auto-fill form</button>
    <span id="cf-autofill-status" class="autofill-status"></span>
  </div>
</div>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data" id="corridor-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="form-field"><label for="cf-name">Name<span class="field-required-mark">*</span></label><input type="text" id="cf-name" name="name" required value="<?= e($corridor['name'] ?? '') ?>"></div>
    <div class="form-field"><label for="cf-band">Average Price Band<span class="field-required-mark">*</span></label><input type="text" id="cf-band" name="avg_price_band" required value="<?= e($corridor['avg_price_band'] ?? '') ?>" placeholder="e.g. ₹14,000–19,500/sq ft"></div>
    <div class="form-field">
      <label for="cf-tier">Tier<span class="field-badge-optional">Optional — Luxury/Premium show as a badge on the public site; Standard shows nothing</span></label>
      <select id="cf-tier" name="tier">
        <?php foreach ($tiers as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= (($corridor['tier'] ?? 'standard') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label for="cf-growth">YoY Price Growth (shown as a badge on the homepage rate carousel)<span class="field-badge-optional">Optional</span></label><input type="text" id="cf-growth" name="price_growth_label" value="<?= e($corridor['price_growth_label'] ?? '') ?>" placeholder="e.g. +9.1%"></div>
    <div class="form-field"><label for="cf-verified">Price band last reviewed on (shown on the corridor page)<span class="field-badge-optional">Optional</span></label><input type="date" id="cf-verified" name="rates_verified_on" value="<?= e($corridor['rates_verified_on'] ?? '') ?>" max="<?= e(date('Y-m-d')) ?>"></div>
    <div class="form-field"><label for="cf-description">Short Description (used on cards &amp; footer)<span class="field-required-mark">*</span></label><textarea id="cf-description" name="description" required><?= e($corridor['description'] ?? '') ?></textarea></div>
    <div class="form-field"><label for="cf-long-description">Long-form Guide (shown on the corridor page — falls back to short description if blank)<span class="field-badge-optional">Optional</span></label><textarea id="cf-long-description" name="long_description" rows="6"><?= e($corridor['long_description'] ?? '') ?></textarea></div>

    <?php $mlLatId = 'cf-lat'; $mlLngId = 'cf-lng'; include __DIR__ . '/../includes/admin-maps-link.php'; ?>
    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1"><label for="cf-lat">Latitude (for map search)<span class="field-badge-optional">Optional</span></label><input type="text" id="cf-lat" name="lat" value="<?= e((string)($corridor['lat'] ?? '')) ?>" placeholder="e.g. 28.4595"></div>
      <div class="form-field" style="flex:1"><label for="cf-lng">Longitude<span class="field-badge-optional">Optional</span></label><input type="text" id="cf-lng" name="lng" value="<?= e((string)($corridor['lng'] ?? '')) ?>" placeholder="e.g. 77.0266"></div>
    </div>
    <p style="font-size:12.5px; color:var(--ink-soft); margin-top:-10px;">Find coordinates by right-clicking a location on <a href="https://www.google.com/maps" target="_blank" rel="noopener">Google Maps</a> — the numbers shown are lat, lng.</p>

    <div class="form-field">
      <label for="cf-hero">Hero Image<span class="field-badge-optional">Optional</span> <?= !empty($corridor['hero_image_path']) ? '(replace existing)' : '' ?></label>
      <?php if (!empty($corridor['hero_image_path'])): ?>
        <img class="thumb" style="width:64px;height:64px;margin-bottom:8px;" src="<?= UPLOAD_URL . e($corridor['hero_image_path']) ?>" alt="">
      <?php endif; ?>
      <input type="file" id="cf-hero" name="hero_image" accept=".jpg,.jpeg,.png,.webp">
    </div>

    <h4 style="margin:24px 0 4px;">Frequently Asked Questions<span class="field-badge-optional">Optional</span></h4>
    <p style="font-size:12.5px; color:var(--ink-soft); margin-top:0;">Leave a row blank to skip it.</p>
    <?php foreach ($faqs as $idx => $faq): ?>
      <div class="form-field"><label for="cf-faq-q-<?= $idx ?>">Question <?= $idx + 1 ?></label><input type="text" id="cf-faq-q-<?= $idx ?>" name="faq_question[]" value="<?= e($faq['question']) ?>"></div>
      <div class="form-field"><label for="cf-faq-a-<?= $idx ?>">Answer <?= $idx + 1 ?></label><textarea id="cf-faq-a-<?= $idx ?>" name="faq_answer[]"><?= e($faq['answer']) ?></textarea></div>
    <?php endforeach; ?>

    <div style="display:flex; gap:10px;">
      <button type="submit" name="action" value="close" class="btn btn-primary">Save &amp; Close</button>
      <button type="submit" name="action" value="new" class="btn btn-navy">Save &amp; Add New</button>
    </div>
  </form>
</div>

<script>
(function () {
  var btn = document.getElementById('cf-autofill-btn');
  var textarea = document.getElementById('cf-autofill-text');
  var status = document.getElementById('cf-autofill-status');
  var form = document.getElementById('corridor-form');

  function setValue(id, value) {
    if (value === null || value === undefined || value === '') return;
    var el = document.getElementById(id);
    if (!el) return;
    el.value = value;
  }

  function selectByVisibleText(selectEl, text) {
    if (!text) return;
    var target = String(text).trim().toLowerCase();
    for (var i = 0; i < selectEl.options.length; i++) {
      if (selectEl.options[i].text.trim().toLowerCase() === target) {
        selectEl.selectedIndex = i;
        return;
      }
    }
  }

  btn.addEventListener('click', function () {
    var text = textarea.value.trim();
    if (!text) { status.textContent = 'Paste some text first.'; return; }

    btn.disabled = true;
    status.textContent = 'Reading…';

    fetch('/admin/ajax-parse-text.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        form_type: 'corridor',
        text: text,
        csrf_token: form.querySelector('input[name="csrf_token"]').value,
      }),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) { status.textContent = res.message || 'Could not auto-fill.'; return; }
        var f = res.fields;

        setValue('cf-name', f.name);
        setValue('cf-band', f.avg_price_band);
        setValue('cf-growth', f.price_growth_label);
        setValue('cf-description', f.description);
        setValue('cf-long-description', f.long_description);
        if (f.tier) selectByVisibleText(document.getElementById('cf-tier'), f.tier);

        status.textContent = 'Filled — please review every field before saving.';
      })
      .catch(function () {
        status.textContent = 'Something went wrong — please fill the form manually.';
      })
      .finally(function () {
        btn.disabled = false;
      });
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

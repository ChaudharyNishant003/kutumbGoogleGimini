<?php
// admin-header.php is required at the BOTTOM of the POST-handling block below,
// not here. It echoes the page shell immediately as it's included — doing that
// before a possible header('Location: ...') redirect means the redirect fails
// silently in production (Apache has already started streaming the response,
// so "headers already sent" isn't even visible with display_errors off). That
// was the actual cause of every admin form appearing to "go blank" on submit:
// the redirect was firing, but stripped of its Location header by the time it
// reached the browser, leaving just the header/sidebar markup that had
// already been flushed. Same fix applies to every other *-form.php file.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$page_title = 'Property — Kutumb Admin';

$id = (int)($_GET['id'] ?? 0);
$property = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt->execute([$id]);
    $property = $stmt->fetch();
}

$corridors = db()->query('SELECT * FROM corridors WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
$agents = db()->query('SELECT id, name FROM team_members WHERE active = 1 AND deleted_at IS NULL ORDER BY name')->fetchAll();
$categories = ['new-launch' => 'New Launch', 'under-construction' => 'Under Construction', 'ready-to-move' => 'Ready To Move', 'commercial' => 'Commercial'];
$tiers = ['luxury' => 'Luxury', 'premium' => 'Premium', 'standard' => 'Standard'];
$allAmenities = db()->query('SELECT * FROM amenities ORDER BY sort_order, name')->fetchAll();

$selectedAmenityIds = [];
if ($id) {
    $stmt = db()->prepare('SELECT amenity_id FROM property_amenities WHERE property_id = ?');
    $stmt->execute([$id]);
    $selectedAmenityIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired — please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $sector = trim($_POST['sector'] ?? '');
    $category = $_POST['category'] ?? '';
    $tier = isset($tiers[$_POST['tier'] ?? '']) ? $_POST['tier'] : 'standard';
    $bhk = trim($_POST['bhk_config'] ?? '');
    $priceDisplay = trim($_POST['price_display'] ?? '');
    $priceMin = (float)str_replace(',', '', $_POST['price_min'] ?? '0');
    $developer = trim($_POST['developer'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $corridorId = !empty($_POST['corridor_id']) ? (int)$_POST['corridor_id'] : null;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $latRaw = trim($_POST['lat'] ?? '');
    $lngRaw = trim($_POST['lng'] ?? '');
    $lat = $latRaw !== '' ? (float)$latRaw : null;
    $lng = $lngRaw !== '' ? (float)$lngRaw : null;
    $virtualTourUrl = trim($_POST['virtual_tour_url'] ?? '');
    $reraNumber = trim($_POST['rera_number'] ?? '');
    $listingAgentId = !empty($_POST['listing_agent_id']) ? (int)$_POST['listing_agent_id'] : null;
    $possessionText = trim($_POST['possession_text'] ?? '');
    $infoVerifiedOn = trim($_POST['info_verified_on'] ?? '');
    $landAreaRaw = trim($_POST['land_area_acres'] ?? '');
    $towerRaw = trim($_POST['tower_count'] ?? '');
    $unitRaw = trim($_POST['unit_count'] ?? '');
    $floorsText = trim($_POST['floors_text'] ?? '');
    $rateText = trim($_POST['rate_text'] ?? '');
    $paymentPlanNote = trim($_POST['payment_plan_note'] ?? '');
    $isEoi = isset($_POST['is_eoi']) ? 1 : 0;

    if ($landAreaRaw !== '' && (!is_numeric($landAreaRaw) || (float)$landAreaRaw <= 0 || (float)$landAreaRaw >= 100000)) {
        $errors[] = 'Land area must be a number of acres, e.g. 14.05.';
    }
    foreach ([[$towerRaw, 'Towers'], [$unitRaw, 'Total units']] as [$raw, $label]) {
        if ($raw !== '' && (!ctype_digit($raw) || (int)$raw > 65535)) {
            $errors[] = $label . ' must be a whole number.';
        }
    }
    if (mb_strlen($floorsText) > 40) $errors[] = 'Floors must be 40 characters or fewer (e.g. "G+46").';
    if (mb_strlen($rateText) > 80) $errors[] = 'Rate must be 80 characters or fewer.';
    if (mb_strlen($paymentPlanNote) > 255) $errors[] = 'Payment plan note must be 255 characters or fewer.';
    $landArea = $landAreaRaw !== '' && is_numeric($landAreaRaw) ? round((float)$landAreaRaw, 2) : null;
    $towerCount = $towerRaw !== '' && ctype_digit($towerRaw) ? (int)$towerRaw : null;
    $unitCount = $unitRaw !== '' && ctype_digit($unitRaw) ? (int)$unitRaw : null;

    if (mb_strlen($possessionText) > 60) {
        $errors[] = 'Expected possession must be 60 characters or fewer (e.g. "December 2028").';
    }
    if ($infoVerifiedOn !== '') {
        $verifiedDate = DateTime::createFromFormat('Y-m-d', $infoVerifiedOn);
        if (!$verifiedDate || $verifiedDate->format('Y-m-d') !== $infoVerifiedOn || $verifiedDate > new DateTime('tomorrow')) {
            $errors[] = '"Project information verified on" must be a valid date, not in the future.';
        }
    }
    if (($latRaw === '') !== ($lngRaw === '') || ($latRaw !== '' && (!is_numeric($latRaw) || !is_numeric($lngRaw) || abs($lat) > 90 || abs($lng) > 180))) {
        $errors[] = 'Location needs both a valid latitude and longitude — paste a Google Maps link to fill them.';
    }

    if ($name === '' || $sector === '' || !isset($categories[$category]) || $priceDisplay === '' || $description === '') {
        $errors[] = 'Please fill in name, sector, category, display price, and description.';
    }

    $imagePath = $property['image_path'] ?? null;

    if (!empty($_FILES['image']['name'])) {
        $uploadError = null;
        $newName = handle_file_upload($_FILES['image'], UPLOAD_DIR, image_mime_map(), MAX_UPLOAD_BYTES, $uploadError);
        if ($uploadError) {
            $errors[] = $uploadError;
        } elseif ($newName) {
            delete_uploaded_file(UPLOAD_DIR, $imagePath);
            $imagePath = $newName;
            generate_image_card_derivative(UPLOAD_DIR, $newName);
        }
    }

    if (!$errors) {
        $isNew = !$property;
        $slug = slugify($name) . ($property ? '' : '-' . substr(bin2hex(random_bytes(3)), 0, 6));
        if ($property) {
            $slug = $property['slug']; // keep existing slug on edit
            $stmt = db()->prepare('UPDATE properties SET name=?, slug=?, corridor_id=?, sector=?, category=?, tier=?, bhk_config=?, possession_text=?, info_verified_on=?, land_area_acres=?, tower_count=?, unit_count=?, floors_text=?, rate_text=?, payment_plan_note=?, is_eoi=?, price_display=?, price_min=?, developer=?, description=?, image_path=?, lat=?, lng=?, virtual_tour_url=?, rera_number=?, listing_agent_id=?, featured=? WHERE id=?');
            $stmt->execute([$name, $slug, $corridorId, $sector, $category, $tier, $bhk, $possessionText ?: null, $infoVerifiedOn ?: null, $landArea, $towerCount, $unitCount, $floorsText ?: null, $rateText ?: null, $paymentPlanNote ?: null, $isEoi, $priceDisplay, $priceMin, $developer, $description, $imagePath, $lat, $lng, $virtualTourUrl ?: null, $reraNumber ?: null, $listingAgentId, $featured, $property['id']]);
            $propertyId = $property['id'];
        } else {
            $stmt = db()->prepare('INSERT INTO properties (name, slug, corridor_id, sector, category, tier, bhk_config, possession_text, info_verified_on, land_area_acres, tower_count, unit_count, floors_text, rate_text, payment_plan_note, is_eoi, price_display, price_min, developer, description, image_path, lat, lng, virtual_tour_url, rera_number, listing_agent_id, featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$name, $slug, $corridorId, $sector, $category, $tier, $bhk, $possessionText ?: null, $infoVerifiedOn ?: null, $landArea, $towerCount, $unitCount, $floorsText ?: null, $rateText ?: null, $paymentPlanNote ?: null, $isEoi, $priceDisplay, $priceMin, $developer, $description, $imagePath, $lat, $lng, $virtualTourUrl ?: null, $reraNumber ?: null, $listingAgentId, $featured]);
            $propertyId = (int)db()->lastInsertId();
        }

        $postedAmenityIds = array_map('intval', $_POST['amenities'] ?? []);
        db()->prepare('DELETE FROM property_amenities WHERE property_id = ?')->execute([$propertyId]);
        if ($postedAmenityIds) {
            $insertAmenity = db()->prepare('INSERT INTO property_amenities (property_id, amenity_id) VALUES (?, ?)');
            foreach ($postedAmenityIds as $amenityId) {
                $insertAmenity->execute([$propertyId, $amenityId]);
            }
        }

        // A location typed or pasted here is exact; a new or still-missing location also triggers
        // the nearby-places auto-fill (which looks the location up when there is none).
        $oldLat = $property['lat'] ?? null;
        $oldLng = $property['lng'] ?? null;
        $locationChanged = $lat !== null && ($oldLat === null || abs((float)$oldLat - $lat) > 0.00001 || abs((float)$oldLng - $lng) > 0.00001);
        if ($locationChanged) {
            db()->prepare("UPDATE properties SET location_source = 'manual' WHERE id = ?")->execute([$propertyId]);
        } elseif ($lat === null) {
            db()->prepare('UPDATE properties SET location_source = NULL WHERE id = ?')->execute([$propertyId]);
        }
        $autofill = ($locationChanged || $lat === null) ? '&autofill=' . $propertyId : '';

        // Two submit buttons (name="action") decide where we land: back to the
        // list ("close"), or a blank form ready for the next one ("new").
        if (($_POST['action'] ?? 'close') === 'new') {
            header('Location: /admin/property-form.php?saved=1' . $autofill);
        } else {
            header('Location: /admin/properties.php?saved=1&id=' . $propertyId . '&new=' . ($isNew ? '1' : '0') . $autofill);
        }
        exit;
    }

    // Re-populate $property from posted values so the form redisplays what was typed.
    $property = [
        'id' => $property['id'] ?? null, 'name' => $name, 'sector' => $sector, 'category' => $category,
        'tier' => $tier, 'bhk_config' => $bhk, 'price_display' => $priceDisplay, 'price_min' => $priceMin, 'developer' => $developer,
        'description' => $description, 'corridor_id' => $corridorId, 'featured' => $featured, 'image_path' => $imagePath,
        'lat' => $latRaw, 'lng' => $lngRaw, 'virtual_tour_url' => $virtualTourUrl, 'rera_number' => $reraNumber,
        'listing_agent_id' => $listingAgentId, 'possession_text' => $possessionText, 'info_verified_on' => $infoVerifiedOn,
        'land_area_acres' => $landAreaRaw, 'tower_count' => $towerRaw, 'unit_count' => $unitRaw, 'floors_text' => $floorsText,
        'rate_text' => $rateText, 'payment_plan_note' => $paymentPlanNote, 'is_eoi' => $isEoi,
    ];
    $selectedAmenityIds = array_map('intval', $_POST['amenities'] ?? []);
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-topbar">
  <h1><?= $property && !empty($property['id']) ? 'Edit Property' : 'Add Property' ?></h1>
  <a href="/admin/properties.php" class="btn btn-navy btn-sm">← Back to Properties</a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="form-note success">Property added successfully. Add another below, or head back to the list.</div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="form-note error"><?= e($err) ?></div><?php endforeach; ?>
<?php include __DIR__ . '/../includes/places-autofill-trigger.php'; ?>

<div class="admin-card autofill-card">
  <label for="pf-autofill-text">✨ Paste &amp; Auto-fill</label>
  <p>Paste a brochure, WhatsApp message, or any project description — we'll try to fill the fields below from it. Always review before saving; nothing here is saved until you click Save.</p>
  <textarea id="pf-autofill-text" rows="5" placeholder="Paste property details here..."></textarea>
  <div class="autofill-actions">
    <button type="button" id="pf-autofill-btn" class="btn btn-navy btn-sm">Auto-fill form</button>
    <span id="pf-autofill-status" class="autofill-status"></span>
  </div>
</div>

<div class="admin-card">
  <form method="post" enctype="multipart/form-data" id="property-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="form-field"><label for="pf-name">Property Name<span class="field-required-mark">*</span></label><input type="text" id="pf-name" name="name" required value="<?= e($property['name'] ?? '') ?>"></div>

    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1"><label for="pf-sector">Sector<span class="field-required-mark">*</span></label><input type="text" id="pf-sector" name="sector" required value="<?= e($property['sector'] ?? '') ?>" placeholder="e.g. Sector 54"></div>
      <div class="form-field" style="flex:1">
        <label for="pf-corridor">Corridor<span class="field-badge-optional">Optional</span></label>
        <select id="pf-corridor" name="corridor_id">
          <option value="">— None —</option>
          <?php foreach ($corridors as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (isset($property['corridor_id']) && $property['corridor_id'] == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1">
        <label for="pf-category">Category<span class="field-required-mark">*</span></label>
        <select id="pf-category" name="category" required>
          <?php foreach ($categories as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= (($property['category'] ?? '') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field" style="flex:1"><label for="pf-bhk">Configuration<span class="field-badge-optional">Optional</span></label><input type="text" id="pf-bhk" name="bhk_config" value="<?= e($property['bhk_config'] ?? '') ?>" placeholder="e.g. 3/4 BHK"></div>
    </div>

    <div class="form-field">
      <label for="pf-tier">Tier<span class="field-badge-optional">Optional — Luxury/Premium show as a badge on the public site; Standard shows nothing</span></label>
      <select id="pf-tier" name="tier">
        <?php foreach ($tiers as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= (($property['tier'] ?? 'standard') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1"><label for="pf-price-display">Display Price<span class="field-required-mark">*</span></label><input type="text" id="pf-price-display" name="price_display" required value="<?= e($property['price_display'] ?? '') ?>" placeholder="e.g. 2.6 Cr"></div>
      <div class="form-field" style="flex:1"><label for="pf-price-min">Price (numeric, for sorting)<span class="field-badge-optional">Optional</span></label><input type="text" id="pf-price-min" name="price_min" value="<?= e((string)($property['price_min'] ?? '')) ?>" placeholder="e.g. 26000000"></div>
    </div>

    <div class="form-field"><label for="pf-developer">Developer<span class="field-badge-optional">Optional</span></label><input type="text" id="pf-developer" name="developer" value="<?= e($property['developer'] ?? '') ?>"></div>
    <div class="form-field"><label for="pf-description">Description<span class="field-required-mark">*</span></label><textarea id="pf-description" name="description" required><?= e($property['description'] ?? '') ?></textarea></div>

    <div class="form-field">
      <label>Amenities<span class="field-badge-optional">Optional</span></label>
      <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:8px 16px;">
        <?php foreach ($allAmenities as $a): ?>
          <label style="font-weight:400; display:flex; align-items:center; gap:8px;">
            <input type="checkbox" name="amenities[]" value="<?= (int)$a['id'] ?>" style="width:auto;" <?= in_array((int)$a['id'], $selectedAmenityIds, true) ? 'checked' : '' ?>>
            <?= e($a['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-field">
      <label for="pf-image">Photo<span class="field-badge-optional">Optional</span> <?= !empty($property['image_path']) ? '(replace existing)' : '' ?></label>
      <?php if (!empty($property['image_path'])): ?>
        <img class="thumb" style="width:64px;height:64px;margin-bottom:8px;" src="<?= UPLOAD_URL . e($property['image_path']) ?>" alt="">
      <?php endif; ?>
      <input type="file" id="pf-image" name="image" accept=".jpg,.jpeg,.png,.webp">
    </div>

    <?php $mlLatId = 'pf-lat'; $mlLngId = 'pf-lng'; include __DIR__ . '/../includes/admin-maps-link.php'; ?>
    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1"><label for="pf-lat">Latitude (for map search)<span class="field-badge-optional">Optional</span></label><input type="text" id="pf-lat" name="lat" value="<?= e((string)($property['lat'] ?? '')) ?>" placeholder="e.g. 28.4595"></div>
      <div class="form-field" style="flex:1"><label for="pf-lng">Longitude<span class="field-badge-optional">Optional</span></label><input type="text" id="pf-lng" name="lng" value="<?= e((string)($property['lng'] ?? '')) ?>" placeholder="e.g. 77.0266"></div>
    </div>
    <p style="font-size:12.5px; color:var(--ink-soft); margin-top:-10px;">Leave blank to show this property at its corridor's approximate location instead. Find exact coordinates by right-clicking a spot on <a href="https://www.google.com/maps" target="_blank" rel="noopener">Google Maps</a> — the numbers shown are lat, lng. The location is also used to list nearby schools, hospitals and metro stations on the property page.</p>

    <div class="form-field" style="background:var(--paper-dim); border-radius:10px; padding:14px 16px;">
      <label style="margin-bottom:2px;">Project Assistant facts<span class="field-badge-optional">Optional</span></label>
      <p style="font-size:12.5px; color:var(--ink-soft); margin:0 0 12px;">The chat assistant on the property page answers price and possession questions only when "verified on" is set, and shows that date as the source date.</p>
      <div style="display:flex; gap:16px; flex-wrap:wrap;">
        <div class="form-field" style="flex:1; min-width:200px; margin-bottom:0;"><label for="pf-possession">Expected possession</label><input type="text" id="pf-possession" name="possession_text" maxlength="60" value="<?= e($property['possession_text'] ?? '') ?>" placeholder="e.g. December 2028"></div>
        <div class="form-field" style="flex:1; min-width:200px; margin-bottom:0;"><label for="pf-verified">Project information verified on</label><input type="date" id="pf-verified" name="info_verified_on" max="<?= date('Y-m-d') ?>" value="<?= e($property['info_verified_on'] ?? '') ?>"> <button type="button" class="btn btn-soft btn-sm" style="margin-top:6px;" onclick="var d=new Date();document.getElementById('pf-verified').value=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');">Mark verified today</button></div>
      </div>
    </div>

    <div class="form-field" style="background:var(--paper-dim); border-radius:10px; padding:14px 16px;">
      <label style="margin-bottom:2px;">Project facts<span class="field-badge-optional">Optional — shown as the Key Facts strip on the property page</span></label>
      <p style="font-size:12.5px; color:var(--ink-soft); margin:0 0 12px;">Leave any box empty to hide it. Unit plans, payment plan, specifications and multiple RERA numbers are edited on the <strong>Project Details</strong> page (button below, after saving).</p>
      <div style="display:flex; gap:16px; flex-wrap:wrap;">
        <div class="form-field" style="flex:1; min-width:140px;"><label for="pf-land">Land area (acres)</label><input type="text" id="pf-land" name="land_area_acres" inputmode="decimal" value="<?= e((string)($property['land_area_acres'] ?? '')) ?>" placeholder="e.g. 14.05"></div>
        <div class="form-field" style="flex:1; min-width:140px;"><label for="pf-towers">Towers</label><input type="text" id="pf-towers" name="tower_count" inputmode="numeric" value="<?= e((string)($property['tower_count'] ?? '')) ?>" placeholder="e.g. 9"></div>
        <div class="form-field" style="flex:1; min-width:140px;"><label for="pf-units">Total units</label><input type="text" id="pf-units" name="unit_count" inputmode="numeric" value="<?= e((string)($property['unit_count'] ?? '')) ?>" placeholder="e.g. 760"></div>
        <div class="form-field" style="flex:1; min-width:140px;"><label for="pf-floors">Floors</label><input type="text" id="pf-floors" name="floors_text" maxlength="40" value="<?= e($property['floors_text'] ?? '') ?>" placeholder="e.g. G+46"></div>
      </div>
      <div class="form-field"><label for="pf-rate">Rate</label><input type="text" id="pf-rate" name="rate_text" maxlength="80" value="<?= e($property['rate_text'] ?? '') ?>" placeholder="e.g. ₹17,000/sq ft + PLC + GST"></div>
      <div class="form-field"><label for="pf-pay-note">Payment plan note</label><input type="text" id="pf-pay-note" name="payment_plan_note" maxlength="255" value="<?= e($property['payment_plan_note'] ?? '') ?>" placeholder="e.g. CLP & PLP available"></div>
      <label style="font-weight:400;"><input type="checkbox" name="is_eoi" value="1" <?= !empty($property['is_eoi']) ? 'checked' : '' ?> style="width:auto; margin-right:8px;"> EOI / Coming Soon — not yet RERA-registered. Shows an "EOI Open" badge, hides the price and asks visitors to register interest.</label>
    </div>

    <div class="form-field"><label for="pf-tour">Virtual Tour URL<span class="field-badge-optional">Optional</span></label><input type="url" id="pf-tour" name="virtual_tour_url" value="<?= e($property['virtual_tour_url'] ?? '') ?>" placeholder="e.g. a Matterport or YouTube 360 embed link"></div>

    <div style="display:flex; gap:16px;">
      <div class="form-field" style="flex:1"><label for="pf-rera">RERA Number<span class="field-badge-optional">Optional</span></label><input type="text" id="pf-rera" name="rera_number" value="<?= e($property['rera_number'] ?? '') ?>"></div>
      <div class="form-field" style="flex:1">
        <label for="pf-agent">Listing Agent<span class="field-badge-optional">Optional</span></label>
        <select id="pf-agent" name="listing_agent_id">
          <option value="">— None —</option>
          <?php foreach ($agents as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= (isset($property['listing_agent_id']) && $property['listing_agent_id'] == $a['id']) ? 'selected' : '' ?>><?= e($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-field">
      <label><input type="checkbox" name="featured" value="1" <?= !empty($property['featured']) ? 'checked' : '' ?> style="width:auto; margin-right:8px;"> Feature this property on the homepage</label>
    </div>

    <div style="display:flex; gap:10px;">
      <button type="submit" name="action" value="close" class="btn btn-primary">Save &amp; Close</button>
      <button type="submit" name="action" value="new" class="btn btn-navy"><?= $property && !empty($property['id']) ? 'Save & Add New' : 'Save & Add Another' ?></button>
    </div>
  </form>

  <?php if ($property && !empty($property['id'])): ?>
    <div style="display:flex; gap:12px; margin-top:16px; flex-wrap:wrap;">
      <a href="/admin/property-gallery.php?property_id=<?= (int)$property['id'] ?>" class="btn btn-navy btn-sm">Manage Photos</a>
      <a href="/admin/property-documents.php?property_id=<?= (int)$property['id'] ?>" class="btn btn-navy btn-sm">Manage Documents</a>
      <a href="/admin/property-details.php?property_id=<?= (int)$property['id'] ?>" class="btn btn-navy btn-sm">Project Details</a>
      <a href="/admin/property-faqs.php?property_id=<?= (int)$property['id'] ?>" class="btn btn-navy btn-sm">FAQs &amp; Disclosures</a>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var btn = document.getElementById('pf-autofill-btn');
  var textarea = document.getElementById('pf-autofill-text');
  var status = document.getElementById('pf-autofill-status');
  var form = document.getElementById('property-form');

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
        form_type: 'property',
        text: text,
        csrf_token: form.querySelector('input[name="csrf_token"]').value,
      }),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) { status.textContent = res.message || 'Could not auto-fill.'; return; }
        var f = res.fields;

        setValue('pf-name', f.name);
        setValue('pf-sector', f.sector);
        setValue('pf-bhk', f.bhk_config);
        setValue('pf-price-display', f.price_display);
        if (f.price_min) setValue('pf-price-min', f.price_min);
        setValue('pf-developer', f.developer);
        setValue('pf-description', f.description);
        setValue('pf-rera', f.rera_number);

        if (f.category) selectByVisibleText(document.getElementById('pf-category'), f.category.replace(/-/g, ' '));
        if (f.tier) selectByVisibleText(document.getElementById('pf-tier'), f.tier);
        if (f.corridor_name) selectByVisibleText(document.getElementById('pf-corridor'), f.corridor_name);

        if (Array.isArray(f.amenities) && f.amenities.length) {
          var wanted = f.amenities.map(function (a) { return a.trim().toLowerCase(); });
          document.querySelectorAll('input[name="amenities[]"]').forEach(function (cb) {
            var label = cb.closest('label');
            var labelText = label ? label.textContent.trim().toLowerCase() : '';
            if (wanted.indexOf(labelText) !== -1) cb.checked = true;
          });
        }

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

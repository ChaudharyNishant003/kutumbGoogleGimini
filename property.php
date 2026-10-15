<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/structured-data.php';

$id = (int)($_GET['id'] ?? 0);

try {
    $stmt = db()->prepare('SELECT p.id, p.name, p.sector, p.category, p.bhk_config, p.price_display, p.price_min, p.developer, p.description, p.image_path, p.virtual_tour_url, p.rera_number, p.listing_agent_id, p.corridor_id, p.view_count, p.lat, p.lng, p.location_source, p.possession_text, p.info_verified_on, p.land_area_acres, p.tower_count, p.unit_count, p.floors_text, p.rate_text, p.payment_plan_note, p.is_eoi, c.name AS corridor_name, c.slug AS corridor_slug, c.avg_price_band, c.lat AS corridor_lat, c.lng AS corridor_lng FROM properties p LEFT JOIN corridors c ON c.id = p.corridor_id WHERE p.id = ? AND p.deleted_at IS NULL LIMIT 1');
    $stmt->execute([$id]);
    $property = $stmt->fetch();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

if (!$property) {
    http_response_code(404);
    $page_title = 'Property not found — Kutumb Realty';
    include __DIR__ . '/includes/header.php';
    echo '<section><div class="container"><h1>Property not found</h1><p><a href="/index.php">Back to home</a></p></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

try {
    record_view_once('properties', $property['id'], 'kutumb_viewed_property');
    $property['view_count'] = (int)db()->query('SELECT view_count FROM properties WHERE id = ' . (int)$property['id'])->fetchColumn();

    $related_stmt = db()->prepare('SELECT id, name, sector, category, tier, developer, price_display, price_min, image_path, featured, is_eoi FROM properties WHERE corridor_id <=> ? AND id != ? AND deleted_at IS NULL LIMIT 3');
    $related_stmt->execute([$property['corridor_id'], $property['id']]);
    $related = $related_stmt->fetchAll();

    $images_stmt = db()->prepare('SELECT image_path FROM property_images WHERE property_id = ? ORDER BY sort_order, id');
    $images_stmt->execute([$property['id']]);
    $images = $images_stmt->fetchAll();

    $documents_stmt = db()->prepare('SELECT doc_type, label, file_path FROM property_documents WHERE property_id = ? ORDER BY uploaded_at');
    $documents_stmt->execute([$property['id']]);
    $documents = $documents_stmt->fetchAll();
    $docTypeLabels = ['rera' => 'RERA Certificate', 'brochure' => 'Brochure', 'floor-plan' => 'Floor Plan', 'other' => 'Document'];

    $agent = null;
    if ($property['listing_agent_id']) {
        $agent_stmt = db()->prepare('SELECT name, slug, role_title, photo_path FROM team_members WHERE id = ? AND active = 1 AND deleted_at IS NULL');
        $agent_stmt->execute([$property['listing_agent_id']]);
        $agent = $agent_stmt->fetch() ?: null;
    }

    $amenities_stmt = db()->prepare('SELECT a.name FROM amenities a INNER JOIN property_amenities pa ON pa.amenity_id = a.id WHERE pa.property_id = ? ORDER BY a.sort_order, a.name');
    $amenities_stmt->execute([$property['id']]);
    $amenities = $amenities_stmt->fetchAll(PDO::FETCH_COLUMN);

    $detailQuery = function (string $sql) use ($property): array {
        $stmt = db()->prepare($sql);
        $stmt->execute([$property['id']]);
        return $stmt->fetchAll();
    };
    $unitPlans = $detailQuery('SELECT group_label, unit_label, saleable_sqft, carpet_sqft, balcony_sqft, rate_per_sqft FROM property_unit_plans WHERE property_id = ? ORDER BY sort_order, id');
    $paymentPlans = $detailQuery('SELECT plan_name, milestone, amount_text FROM property_payment_plans WHERE property_id = ? ORDER BY sort_order, id');
    $specifications = $detailQuery('SELECT group_label, category, detail FROM property_specifications WHERE property_id = ? ORDER BY sort_order, id');
    $reraRegistrations = $detailQuery('SELECT phase_label, rera_number, registered_on FROM property_rera_registrations WHERE property_id = ? ORDER BY sort_order, id');
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

$page_title = $property['name'] . ' — ' . $property['sector'] . ' — Kutumb Realty';
$page_description = excerpt($property['description'], 160) ?: ($property['name'] . ' — ' . $property['sector'] . ', ' . category_label($property['category']) . '. ' . $property['price_display'] . '.');
$structured_data = structured_data_property($property)
    . structured_data_breadcrumbs(array_values(array_filter([
        ['name' => 'Home', 'url' => '/index.php'],
        $property['corridor_name'] ? ['name' => $property['corridor_name'], 'url' => '/sector.php?slug=' . $property['corridor_slug']] : null,
        ['name' => $property['name'], 'url' => '/property.php?id=' . $property['id']],
    ])));
$page_scripts = ['gallery', 'ai-neighborhood', 'ai-assistant'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain" style="padding-bottom:32px;">
  <div class="container">
    <div class="breadcrumb">
      <a href="/index.php">Home</a> /
      <?php if ($property['corridor_name']): ?><a href="/sector.php?slug=<?= e($property['corridor_slug']) ?>"><?= e($property['corridor_name']) ?></a> /<?php endif; ?>
      <?= e($property['name']) ?>
    </div>
    <h1><?= e($property['name']) ?></h1>
    <p><?= e($property['sector']) ?> · <?= e($property['developer']) ?> · <?= e(category_label($property['category'])) ?></p>
    <?php if (!empty($property['is_eoi'])): ?><span class="eoi-badge">EOI Open · Coming Soon</span><?php endif; ?>
    <?php if ($property['view_count'] >= 5): ?>
      <p class="view-count-note">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <?= number_format($property['view_count']) ?> people have viewed this listing
      </p>
    <?php endif; ?>
  </div>
</section>

<section style="padding-top:40px;">
  <div class="container">
    <div class="gallery-hero reveal">
      <img src="<?= property_image_src($property) ?>" alt="<?= e($property['name']) ?>">
    </div>
    <?php if ($images): ?><?php include __DIR__ . '/includes/property-gallery.php'; ?><?php endif; ?>
    <?php if ($images): ?><p class="gallery-note">Images are artistic impressions and developer plans, for representation only.</p><?php endif; ?>

    <div class="two-col">
      <div>
        <div class="reveal">
          <span class="eyebrow">About this project</span>
          <h2><?= e($property['name']) ?></h2>
          <p><?= e($property['description']) ?></p>
          <div class="pill-row">
            <span class="pill"><?= e($property['bhk_config']) ?></span>
            <span class="pill"><?= e($property['sector']) ?></span>
            <span class="pill"><?= e(category_label($property['category'])) ?></span>
            <?php if ($property['corridor_name']): ?><span class="pill"><?= e($property['avg_price_band']) ?> avg. on corridor</span><?php endif; ?>
          </div>
          <?php if (!empty($property['info_verified_on'])): ?>
            <p class="verified-note">Project information verified on <?= e(date('j M Y', strtotime($property['info_verified_on']))) ?>. Prices and availability can change &mdash; <a href="<?= whatsapp_url('Hi, could you confirm the current details for ' . $property['name'] . '?') ?>" target="_blank" rel="noopener">confirm with an advisor</a>.</p>
          <?php else: ?>
            <p class="verified-note is-pending">Details for this project are still being verified &mdash; <a href="<?= whatsapp_url('Hi, could you confirm the current details for ' . $property['name'] . '?') ?>" target="_blank" rel="noopener">ask an advisor to confirm them</a> before you decide.</p>
          <?php endif; ?>
        </div>

        <?php include __DIR__ . '/includes/property-details.php'; ?>

        <div class="reveal" style="margin-top:32px;">
          <h4 style="font-family:var(--font-body); font-size:15px; text-transform:uppercase; letter-spacing:0.06em; color:var(--navy-deep);">Amenities</h4>
          <?php if ($amenities): ?>
          <div class="amenity-grid">
            <?php foreach ($amenities as $a): ?>
              <div class="amenity-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                <?= e($a) ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p style="font-size:13px; color:var(--ink-soft);">Amenities for this project haven't been listed yet &mdash; <a href="<?= whatsapp_url('Hi, could you share the amenities list for ' . $property['name'] . '?') ?>" target="_blank" rel="noopener" style="color:var(--navy-deep); text-decoration:underline;">ask an advisor for the full list</a>.</p>
          <?php endif; ?>
        </div>

        <?php include __DIR__ . '/includes/ai/neighborhood.php'; ?>

        <?php if (!empty($property['virtual_tour_url'])): ?>
        <div class="reveal" style="margin-top:32px;">
          <h4 style="font-family:var(--font-body); font-size:15px; text-transform:uppercase; letter-spacing:0.06em; color:var(--navy-deep);">Virtual Tour</h4>
          <div class="tour-embed">
            <iframe src="<?= e($property['virtual_tour_url']) ?>" allow="fullscreen" loading="lazy" title="<?= e($property['name']) ?> virtual tour"></iframe>
          </div>
        </div>
        <?php endif; ?>

        <span id="ai-documents"></span>
        <div class="reveal" style="margin-top:32px;">
          <h4 style="font-family:var(--font-body); font-size:15px; text-transform:uppercase; letter-spacing:0.06em; color:var(--navy-deep);">Documents &amp; Compliance</h4>
          <?php if ($reraRegistrations): ?>
            <ul class="rera-list">
              <?php foreach ($reraRegistrations as $r): ?>
                <li><?= !empty($r['phase_label']) ? e($r['phase_label']) . ': ' : '' ?>RERA No. <strong style="color:var(--navy-deep)"><?= e($r['rera_number']) ?></strong><?= $r['registered_on'] ? ' <span>(dated ' . e(date('d M Y', strtotime($r['registered_on']))) . ')</span>' : '' ?></li>
              <?php endforeach; ?>
            </ul>
            <p style="font-size:12.5px; color:var(--ink-soft);">Verify on the <a href="https://haryanarera.gov.in/" target="_blank" rel="noopener" style="color:var(--navy-deep); text-decoration:underline;">Haryana RERA website</a>.</p>
          <?php elseif (!empty($property['rera_number'])): ?>
            <p style="font-size:13.5px; margin-top:-6px;">RERA No. <strong style="color:var(--navy-deep)"><?= e($property['rera_number']) ?></strong></p>
          <?php elseif (!empty($property['is_eoi'])): ?>
            <p style="font-size:13.5px; margin-top:-6px; color:var(--ink-soft);">RERA registration awaited. This project is taking expressions of interest (EOI) only — the price list and bookings open once it is RERA-registered.</p>
          <?php else: ?>
            <p style="font-size:13.5px; margin-top:-6px; color:var(--ink-soft);">No RERA number is recorded for this project yet &mdash; <a href="<?= whatsapp_url('Hi, what is the RERA registration number for ' . $property['name'] . '?') ?>" target="_blank" rel="noopener" style="color:var(--navy-deep); text-decoration:underline;">ask an advisor</a> and check it on the Haryana RERA website before you commit.</p>
          <?php endif; ?>
          <?php if ($documents): ?>
          <div class="doc-list">
            <?php foreach ($documents as $doc): ?>
              <a class="doc-item" href="<?= DOCS_UPLOAD_URL . e($doc['file_path']) ?>" target="_blank" rel="noopener">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?= e($doc['label']) ?> (<?= e($docTypeLabels[$doc['doc_type']] ?? 'Document') ?>)
              </a>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
            <p style="font-size:13px; color:var(--ink-soft);"><?= $images && $unitPlans ? 'Renders and floor plans are in the gallery above. For the full brochure and cost sheet' : "Brochure and floor plans haven't been uploaded yet" ?> — <a href="<?= whatsapp_url('Hi, could you share the brochure and floor plans for ' . $property['name'] . '?') ?>" target="_blank" rel="noopener" style="color:var(--navy-deep); text-decoration:underline;">ask your advisor to send them over WhatsApp</a>.</p>
          <?php endif; ?>
        </div>

        <?php if (!empty($property['advisor_note'])): ?>
        <div class="reveal" style="margin-top:32px; background:var(--paper-dim); padding:24px; border-radius:var(--radius-md);">
          <span class="eyebrow">Advisor's Note</span>
          <h4 style="margin-bottom:8px;">Why we curated this project</h4>
          <p style="margin:0; font-size:14px;"><?= nl2br(e($property['advisor_note'])) ?></p>
        </div>
        <?php endif; ?>
      </div>

      <div class="sticky-cta">
        <div class="info-card reveal">
          <?php if (!empty($property['is_eoi'])): ?>
          <span class="eyebrow" style="margin-bottom:4px;">Expression of Interest</span>
          <div style="font-family:var(--font-display); font-size:30px; color:var(--navy-deep); margin-bottom:6px;">EOI Open</div>
          <p style="font-size:13px; color:var(--ink-soft); margin-bottom:18px;">The price list releases with the RERA registration. Register your interest to receive it first.</p>

          <a href="<?= whatsapp_url('Hi, I\'d like to register interest (EOI) for ' . $property['name'] . ' (' . $property['sector'] . ').') ?>" class="btn btn-primary btn-block" target="_blank" rel="noopener" style="margin-bottom:12px;">Register Interest on WhatsApp</a>

          <div style="display:flex; gap:10px; margin-bottom:16px;">
            <a href="tel:<?= e(SITE_PHONE) ?>" class="btn btn-soft btn-sm" style="flex:1;">Call Now</a>
          </div>
          <?php else: ?>
          <span class="eyebrow" style="margin-bottom:4px;">Starting Price</span>
          <div style="font-family:var(--font-display); font-size:32px; color:var(--navy-deep); margin-bottom:<?= !empty($property['rate_text']) ? '4px' : '18px' ?>;"><?= e($property['price_display']) ?></div>
          <?php if (!empty($property['rate_text'])): ?><p style="font-size:13px; color:var(--ink-soft); margin-bottom:18px;"><?= e($property['rate_text']) ?></p><?php endif; ?>

          <a href="<?= whatsapp_url('Hi, I\'m interested in ' . $property['name'] . ' (' . $property['sector'] . ').') ?>" class="btn btn-primary btn-block" target="_blank" rel="noopener" style="margin-bottom:12px;">Chat on WhatsApp</a>

          <div style="display:flex; gap:10px; margin-bottom:16px;">
            <a href="tel:<?= e(SITE_PHONE) ?>" class="btn btn-soft btn-sm" style="flex:1;">Call Now</a>
            <?php if ((float)$property['price_min'] > 0): ?><a href="/emi-calculator.php?price=<?= (int)$property['price_min'] ?>&name=<?= urlencode($property['name']) ?>" class="btn btn-soft btn-sm" style="flex:1;">Calculate EMI</a><?php endif; ?>
          </div>
          <?php endif; ?>

          <button type="button" class="compare-toggle compare-toggle-link" data-inline="1" data-id="<?= (int)$property['id'] ?>" data-name="<?= e($property['name']) ?>" aria-pressed="false">+ Add to Compare</button>

          <div style="border-top:1px solid var(--line); padding-top:18px;">
            <p style="font-size:13px; color:var(--ink-soft); margin-bottom:12px;">Prefer an advisor to reach out? We reply <?= e(SITE_RESPONSE_PROMISE) ?>.</p>
            <form id="enquiry-form" class="enquiry-form">
              <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
              <input type="hidden" name="source" value="property-page">
              <div class="hp-field" aria-hidden="true"><label for="enquiry-company">Company Website</label><input type="text" id="enquiry-company" name="company_website" tabindex="-1" autocomplete="off"></div>
              <div class="form-field"><label for="enquiry-name">Name</label><input type="text" id="enquiry-name" name="name" required></div>
              <div class="form-field"><label for="enquiry-phone">Phone</label><input type="tel" id="enquiry-phone" name="phone" required></div>
              <div class="form-field"><label for="enquiry-message">Message (optional)</label><textarea id="enquiry-message" name="message" placeholder="Any specific questions?"></textarea></div>
              <?php include __DIR__ . '/includes/lead-disclosure.php'; ?>
              <button type="submit" class="btn btn-soft btn-block"><?= !empty($property['is_eoi']) ? 'Register Interest' : 'Request a Callback' ?></button>
            </form>
          </div>
        </div>

        <?php if ($agent): ?>
        <div class="info-card reveal" style="margin-top:20px; display:flex; gap:14px; align-items:center;">
          <img src="<?= !empty($agent['photo_path']) ? UPLOAD_URL . e($agent['photo_path']) : property_placeholder_svg($agent['name']) ?>" alt="<?= e($agent['name']) ?>" style="width:56px; height:56px; border-radius:50%; object-fit:cover; flex-shrink:0;">
          <div>
            <div style="font-size:12px; color:var(--ink-soft); text-transform:uppercase; letter-spacing:0.05em;">Your Advisor</div>
            <a href="/agent.php?slug=<?= e($agent['slug']) ?>" style="font-weight:600; color:var(--navy-deep); font-size:15px;"><?= e($agent['name']) ?></a>
            <div style="font-size:12.5px; color:var(--ink-soft);"><?= e($agent['role_title']) ?></div>
          </div>
        </div>
        <?php else: ?>
        <div class="info-card reveal" style="margin-top:20px; display:flex; gap:14px; align-items:center;">
          <div style="width:56px; height:56px; border-radius:50%; background:var(--navy); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--white)" stroke-width="1.8"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/><path d="M9 12l2 2 4-4"/></svg>
          </div>
          <div>
            <div style="font-size:12px; color:var(--ink-soft); text-transform:uppercase; letter-spacing:0.05em;">Your Advisor</div>
            <div style="font-weight:600; color:var(--navy-deep); font-size:15px;">Kutumb Advisory Team</div>
            <div style="font-size:12.5px; color:var(--ink-soft);">A specialist will be assigned when you reach out</div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($related): ?>
    <div style="margin-top:72px;">
      <div class="section-head reveal">
        <div><span class="eyebrow">More on this corridor</span><h2>You might also like</h2></div>
      </div>
      <div class="property-grid reveal reveal-stagger">
        <?php foreach ($related as $i => $p): include __DIR__ . '/includes/property-card.php'; endforeach; ?>
      </div>
      <?php include __DIR__ . '/includes/ai/recommend-related.php'; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/ai/project-assistant.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.getElementById('enquiry-form').addEventListener('submit', function (e) {
  e.preventDefault();
  var form = e.target;
  var btn = form.querySelector('button[type="submit"]');
  kutumbSetLoading(btn, true);
  fetch('/api/enquiry-submit.php', { method: 'POST', body: new FormData(form) })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      kutumbToast(res.message, res.ok ? 'success' : 'error');
      if (res.ok) form.reset();
    })
    .catch(function () {
      kutumbToast('Something went wrong. Please call or WhatsApp us instead.', 'error');
    })
    .finally(function () { kutumbSetLoading(btn, false); });
});
</script>

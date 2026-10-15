<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/structured-data.php';

$slug = $_GET['slug'] ?? '';

try {
    $stmt = db()->prepare('SELECT * FROM corridors WHERE slug = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$slug]);
    $corridor = $stmt->fetch();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

if (!$corridor) {
    http_response_code(404);
    $page_title = 'Corridor not found — Kutumb Realty';
    include __DIR__ . '/includes/header.php';
    echo '<section><div class="container"><h1>Corridor not found</h1><p><a href="/index.php">Back to home</a></p></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

try {
    record_view_once('corridors', $corridor['id'], 'kutumb_viewed_corridor');

    $stmt = db()->prepare('SELECT * FROM properties WHERE corridor_id = ? AND deleted_at IS NULL ORDER BY featured DESC, id ASC');
    $stmt->execute([$corridor['id']]);
    $properties = $stmt->fetchAll();

    $faqStmt = db()->prepare('SELECT * FROM corridor_faqs WHERE corridor_id = ? ORDER BY sort_order, id');
    $faqStmt->execute([$corridor['id']]);
    $faqs = $faqStmt->fetchAll();

    $postsStmt = db()->prepare("SELECT * FROM blog_posts WHERE corridor_id = ? AND status = 'published' AND deleted_at IS NULL ORDER BY published_at DESC LIMIT 3");
    $postsStmt->execute([$corridor['id']]);
    $corridorPosts = $postsStmt->fetchAll();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

$page_title = $corridor['name'] . ' — Gurgaon Corridor Guide — Kutumb Realty';
$page_description = excerpt($corridor['long_description'] ?: $corridor['description'], 160);
$structured_data = structured_data_breadcrumbs([
    ['name' => 'Home', 'url' => '/index.php'],
    ['name' => $corridor['name'], 'url' => '/sector.php?slug=' . $corridor['slug']],
]);
if ($faqs) {
    $structured_data .= structured_data_faq($faqs);
}
$page_scripts = ['ai-finder'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / Corridors / <?= e($corridor['name']) ?></div>
    <?php if ($badge = tier_badge_html($corridor['tier'] ?? 'standard')): ?><div style="margin-bottom:10px;"><?= $badge ?></div><?php endif; ?>
    <h1><?= e($corridor['name']) ?></h1>
    <p><?= e($corridor['description']) ?></p>
    <div class="stat-row">
      <div><span class="num" style="color:var(--white)"><?= e($corridor['avg_price_band']) ?></span><span class="label" style="color:rgba(255,255,255,0.6)">Average price band</span></div>
      <div><span class="num" style="color:var(--white)"><?= count($properties) ?></span><span class="label" style="color:rgba(255,255,255,0.6)">Curated listings</span></div>
    </div>
    <p class="rate-caveat">Indicative price band for orientation &mdash; individual projects, floors and views vary.<?php if (!empty($corridor['rates_verified_on'])): ?> Last reviewed by our advisors on <?= e(date('j M Y', strtotime($corridor['rates_verified_on']))) ?>.<?php endif; ?> Confirm current rates with an advisor before you decide.</p>
  </div>
</section>

<?php if (!empty($corridor['hero_image_path'])): ?>
<div class="gallery-hero" style="border-radius:0; margin:0;">
  <img src="<?= UPLOAD_URL . e($corridor['hero_image_path']) ?>" alt="<?= e($corridor['name']) ?>">
</div>
<?php endif; ?>

<section style="background:var(--paper-dim)">
  <div class="container two-col reveal">
    <div>
      <span class="eyebrow">Why this corridor</span>
      <h2>Is <?= e($corridor['name']) ?> right for you?</h2>
      <p><?= nl2br(e($corridor['long_description'] ?: ($corridor['description'] . ' Pricing here typically runs ' . $corridor['avg_price_band'] . ', though individual projects, floor and view can move that considerably — our advisors can walk you through the specific tradeoffs for each listing above.'))) ?></p>
    </div>
    <div class="info-card">
      <h4>Talk to a corridor specialist</h4>
      <p style="font-size:13px; margin-bottom:16px;">Get a straight answer on whether this corridor fits your budget and timeline.</p>
      <a href="<?= whatsapp_url('Hi, I\'m interested in properties on ' . $corridor['name'] . '.') ?>" class="btn btn-primary btn-block" target="_blank" rel="noopener">Chat on WhatsApp</a>
    </div>
  </div>
</section>

<section id="properties">
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">On this corridor</span>
        <h2>Listings in <?= e($corridor['name']) ?></h2>
      </div>
      <a href="<?= whatsapp_url("Hi, I'm interested in properties on " . $corridor['name'] . '.') ?>" class="btn btn-navy" target="_blank" rel="noopener">Ask an advisor about <?= e($corridor['name']) ?></a>
    </div>
    <?php
    $hf_variant = 'compact';
    $hf_filters = null;
    $hf_context = ['corridor_id' => (int)$corridor['id']];
    $hf_title = 'Looking for something specific on ' . $corridor['name'] . '?';
    $hf_placeholder = 'e.g. 3 BHK under ₹3 crore, ready to move';
    include __DIR__ . '/includes/ai/home-finder.php';
    $fitProperties = array_map(fn($p) => $p + ['corridor_name' => $corridor['name']], $properties);
    $fitTitle = 'How ' . $corridor['name'] . ' listings fit your last search';
    include __DIR__ . '/includes/ai/fit-panel.php';
    ?>
    <?php if ($properties): ?>
      <div class="property-grid">
        <?php foreach ($properties as $i => $p): include __DIR__ . '/includes/property-card.php'; endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color:var(--ink-soft)">No listings on this corridor right now — <a href="<?= whatsapp_url("Hi, I'm interested in properties on " . $corridor['name'] . '.') ?>" target="_blank" rel="noopener">tell an advisor what you're looking for</a> and we'll find it.</p>
    <?php endif; ?>
  </div>
</section>

<?php if ($faqs): ?>
<section>
  <div class="container">
    <div class="section-head reveal">
      <div><span class="eyebrow">Common Questions</span><h2>FAQs about <?= e($corridor['name']) ?></h2></div>
    </div>
    <div class="faq-list reveal">
      <?php foreach ($faqs as $faq): ?>
        <details class="faq-item">
          <summary><?= e($faq['question']) ?></summary>
          <p><?= e($faq['answer']) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($corridorPosts): ?>
<section style="background:var(--paper-dim)">
  <div class="container">
    <div class="section-head reveal">
      <div><span class="eyebrow">Related Insights</span><h2>Read more about <?= e($corridor['name']) ?></h2></div>
    </div>
    <div class="property-grid reveal reveal-stagger">
      <?php foreach ($corridorPosts as $i => $post): include __DIR__ . '/includes/blog-card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

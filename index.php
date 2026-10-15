<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/site-stats.php';

try {
    $corridors = get_active_corridors();
    // Generous fixed cap, not a hard "today's count": keeps the homepage grid
    // unbounded in practice while stopping it from silently scaling to a
    // full-catalog query once inventory grows well past current size.
    $properties = db()->query('SELECT * FROM properties WHERE deleted_at IS NULL ORDER BY featured DESC, id ASC LIMIT 60')->fetchAll();
    $testimonials = db()->query('SELECT * FROM testimonials WHERE featured_home = 1 AND deleted_at IS NULL ORDER BY sort_order, id LIMIT 3')->fetchAll();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

$page_title = 'Kutumb Realty — Trusted Property Consultants in Gurgaon';
$page_description = "Curated residential and commercial property advisory across Gurgaon's main corridors — " . SITE_CUSTOMERS_SERVED . " customers served since " . SITE_FOUNDED_YEAR . ".";
$page_scripts = ['hero-canvas', 'hero-chat', 'rate-stack', 'ai-finder'];
include __DIR__ . '/includes/header.php';

$rateStackCorridors = array_map(function ($c) {
    return [
        'name' => $c['name'],
        'slug' => $c['slug'],
        'priceRange' => $c['avg_price_band'],
        'growth' => $c['price_growth_label'],
        'blurb' => excerpt($c['description'], 110),
    ];
}, $corridors);
?>

<section class="hero texture-grain">
  <canvas id="hero-canvas" aria-hidden="true"></canvas>
  <div class="container">
    <div class="hero-inner">
      <span class="eyebrow">Curated Portfolio</span>
      <h1>Gurgaon's most trusted property advisors.</h1>
      <p class="lede">Kutumb means family. And family never gives you the wrong advice.</p>

      <div class="hero-ask" id="hero-ask">
        <div class="hero-ask-row">
          <span class="hero-ask-typed" id="hero-ask-typed" role="button" tabindex="0" aria-label="Describe the home you want"><span id="hero-ask-typed-text"></span><span class="hero-ask-caret">|</span></span>
          <input type="text" class="hero-ask-input" id="hero-ask-input" placeholder="Describe the home you want…" autocomplete="off">
          <button type="button" class="hero-ask-send" id="hero-ask-send" aria-label="Find matching homes">→</button>
        </div>
        <div class="hero-ask-starters" id="hero-ask-starters" hidden>
          <button type="button" class="hero-ask-starter">3BHK in Sector 82 under ₹2.5 Cr</button>
          <button type="button" class="hero-ask-starter">Ready-to-move home within a ₹50 lakh budget</button>
          <button type="button" class="hero-ask-starter">New launch on Dwarka Expressway</button>
        </div>
      </div>

      <div class="hero-cta">
        <a href="#properties" class="btn btn-primary">View Featured Properties</a>
        <a href="<?= whatsapp_url("Hi, I'm exploring properties in Gurgaon and would like to talk to an advisor.") ?>" class="btn btn-outline" target="_blank" rel="noopener">Chat on WhatsApp</a>
      </div>
    </div>

    <?php if ($rateStackCorridors): ?>
    <div class="rate-stack-wrap" id="rate-stack" data-corridors='<?= e(json_encode($rateStackCorridors)) ?>'></div>
    <?php endif; ?>
  </div>
</section>

<div class="stat-bar-wrap">
  <div class="stat-bar">
    <div class="stat-bar-item"><span class="num"><?= site_years_in_business() ?>+</span><span class="label">Years advising families</span></div>
    <div class="stat-bar-item"><span class="num"><?= SITE_CUSTOMERS_SERVED ?></span><span class="label">Families advised</span></div>
    <div class="stat-bar-item"><span class="num"><?= count($corridors) ?></span><span class="label">Corridors covered</span></div>
  </div>
</div>

<?php $hf_variant = 'hero'; include __DIR__ . '/includes/ai/home-finder.php'; ?>

<section id="properties">
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">Curated Listings</span>
        <h2>A handpicked portfolio, not an overwhelming list</h2>
        <p class="browse-note">Prefer to browse? Pick a category or corridor below.</p>
        <p>Every project below is one our advisors have personally vetted — fewer options, better decisions.</p>
      </div>
    </div>
    <div class="filter-panel">
      <div class="search-tabs" role="group" aria-label="Property category">
        <button class="search-tab active" data-category="all">All</button>
        <button class="search-tab" data-category="new-launch">New Launch</button>
        <button class="search-tab" data-category="under-construction">Under Construction</button>
        <button class="search-tab" data-category="ready-to-move">Ready To Move</button>
        <button class="search-tab" data-category="commercial">Commercial</button>
      </div>
      <form class="search-fields" action="/properties.php" method="get">
        <input type="hidden" name="category" id="hero-search-category" value="">
        <label for="hero-search-keyword" class="sr-only">Search by name, sector or developer</label>
        <input type="text" id="hero-search-keyword" name="keyword" placeholder="Search by project or sector">
        <label for="hero-search-corridor" class="sr-only">Preferred corridor</label>
        <select id="hero-search-corridor" name="corridor_id">
          <option value="">All Corridors</option>
          <?php foreach ($corridors as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-navy">Filter</button>
      </form>
    </div>
    <div class="property-grid bento" id="property-grid">
      <?php foreach ($properties as $i => $p): include __DIR__ . '/includes/property-card.php'; endforeach; ?>
    </div>
    <p class="section-more"><a href="/properties.php" class="btn btn-navy">View all properties</a></p>
  </div>
</section>

<section style="background:var(--paper-dim)">
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">Corridor Journey</span>
        <h2>Explore Gurgaon, corridor by corridor</h2>
        <p>We go deep on a handful of corridors instead of spreading thin across the city.</p>
      </div>
    </div>
  </div>
  <div class="corridor-journey" id="corridor-journey">
    <div class="corridor-journey-track">
      <?php foreach ($corridors as $ji => $c): ?>
        <a class="corridor-journey-panel" href="/sector.php?slug=<?= e($c['slug']) ?>" data-cursor="Explore"<?= !empty($c['hero_image_path']) ? ' style="background-image:url(' . UPLOAD_URL . e($c['hero_image_path']) . ')"' : '' ?>>
          <?php if ($badge = tier_badge_html($c['tier'] ?? 'standard')): ?><div class="card-badges"><?= $badge ?></div><?php endif; ?>
          <div class="corridor-journey-content">
            <span class="eyebrow"><?= str_pad((string)($ji + 1), 2, '0', STR_PAD_LEFT) ?> / <?= str_pad((string)count($corridors), 2, '0', STR_PAD_LEFT) ?></span>
            <h3><?= e($c['name']) ?></h3>
            <p><?= e(excerpt($c['description'], 140)) ?></p>
            <div class="band"><?= e($c['avg_price_band']) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="trust-section texture-grain" id="what-we-do">
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">Why Kutumb</span>
        <h2>Advisors first, brokers second.</h2>
      </div>
      <div class="trust-callout">
        <div class="num"><?= SITE_CUSTOMERS_SERVED ?></div>
        <div class="label">Customers since <?= SITE_FOUNDED_YEAR ?></div>
      </div>
    </div>
    <div class="trust-grid reveal reveal-stagger">
      <div class="trust-item">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2l2 7h7l-5.5 4.5L17 21l-5-4-5 4 1.5-7.5L3 9h7z"/></svg>
        <h4>Advisors first, brokers second</h4>
        <p>We work for you, not the builder — every recommendation is judged against your goals alone.</p>
      </div>
      <div class="trust-item">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/><path d="M9 12l2 2 4-4"/></svg>
        <h4>RERA-verified, always</h4>
        <p>Every listing is checked against its RERA registration before it ever reaches you.</p>
      </div>
      <div class="trust-item">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        <h4>WhatsApp-fast responses</h4>
        <p>Real answers within the hour on the channel you already use, not a support queue.</p>
      </div>
      <div class="trust-item">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg>
        <h4>Corridor specialists</h4>
        <p>We know each sector's tradeoffs cold — not just the listing, the neighbourhood.</p>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="container">
    <p class="partners-label reveal">Trusted developer partners across our corridors</p>
    <div class="partners-strip reveal">
      <span>Godrej</span><span>Emaar</span><span>Signature</span><span>Whiteland</span><span>M3M</span><span>Ashiana</span>
    </div>
  </div>
</section>

<?php if ($testimonials): ?>
<section style="background:var(--paper-dim)">
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">What Buyers Say</span>
        <h2>Verified buyers, real decisions</h2>
      </div>
    </div>
    <div class="testimonial-grid reveal reveal-stagger">
      <?php foreach ($testimonials as $t): ?>
        <div class="testimonial-card">
          <p>"<?= e($t['quote']) ?>"</p>
          <div class="testimonial-name"><?= e($t['client_name']) ?><?= $t['rating'] ? ' ' . str_repeat('★', (int)$t['rating']) : '' ?></div>
          <?php if ($t['client_role']): ?><div class="testimonial-role"><?= e($t['client_role']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

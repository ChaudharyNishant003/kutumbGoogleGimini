<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/site-stats.php';
require_once __DIR__ . '/includes/structured-data.php';

$teamPreview = db()->query('SELECT * FROM team_members WHERE active = 1 AND deleted_at IS NULL ORDER BY sort_order, name LIMIT 4')->fetchAll();

$page_title = 'About Us — Kutumb Realty';
$page_description = 'Founded in ' . SITE_FOUNDED_YEAR . ', Kutumb Realty is built on curated advice rather than volume — meet the local team behind ' . SITE_CUSTOMERS_SERVED . ' Gurgaon property transactions.';
$structured_data = structured_data_breadcrumbs([
    ['name' => 'Home', 'url' => '/index.php'],
    ['name' => 'About', 'url' => '/about.php'],
]);
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / About</div>
    <h1>About Kutumb Realty</h1>
    <p>Founded in 2019, built on curated advice rather than volume.</p>
  </div>
</section>

<section>
  <div class="container two-col">
    <div class="reveal">
      <span class="eyebrow">Our Story</span>
      <h2>A local team that knows every corridor by name</h2>
      <p>Kutumb Realty started in 2019 with a simple idea: Gurgaon buyers don't need more listings, they need a smaller, better-vetted set of them and someone who'll tell them the truth about a project's tradeoffs. Since then we've worked with 1500+ customers across residential and commercial property in Gurgaon.</p>
      <div class="stat-row">
        <div><span class="num"><?= SITE_FOUNDED_YEAR ?></span><span class="label">Founded</span></div>
        <div><span class="num"><?= SITE_CUSTOMERS_SERVED ?></span><span class="label">Customers Served</span></div>
        <div><span class="num"><?= site_corridor_count() ?></span><span class="label">Corridors Covered</span></div>
      </div>
      <p style="font-size:14px; color:var(--ink-soft); margin:0;">Kutumb Realty is run by <strong style="color:var(--navy-deep)">Kutumb Real Estate Private Limited</strong>, Gurugram.<br>Directors: Upendra Singh, Amit Singh and Sandeep Kumar.</p>
    </div>
    <div class="info-card reveal">
      <h4>Talk to us</h4>
      <p style="font-size:13px; margin-bottom:16px;">Have a project or corridor in mind? Get a straight answer.</p>
      <a href="/contact.php" class="btn btn-navy btn-block">Contact an Advisor</a>
    </div>
  </div>
</section>

<section class="trust-section" id="what-we-do">
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow" style="color:var(--orange)">How We Work</span>
        <h2>Buying process, in four steps</h2>
      </div>
      <a href="/why-an-advisor.php" class="btn btn-navy">Why an Advisor?</a>
    </div>
    <div class="trust-grid reveal reveal-stagger">
      <div class="trust-item"><h4>1. Understand your brief</h4><p>Budget, corridor preference, timeline — a short call to get the shortlist right.</p></div>
      <div class="trust-item"><h4>2. Curated shortlist</h4><p>A handful of vetted projects, not a hundred irrelevant ones.</p></div>
      <div class="trust-item"><h4>3. Site visits</h4><p>We accompany you — no pressure, just facts.</p></div>
      <div class="trust-item"><h4>4. Verification</h4><p>RERA status and legal documents checked before you commit.</p></div>
    </div>
  </div>
</section>

<?php if ($teamPreview): ?>
<section>
  <div class="container">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">Meet the Team</span>
        <h2>The advisors behind your shortlist</h2>
      </div>
      <a href="/team.php" class="btn btn-navy">View Full Team</a>
    </div>
    <div class="team-grid reveal reveal-stagger">
      <?php foreach ($teamPreview as $m): ?>
        <a class="team-card" href="/agent.php?slug=<?= e($m['slug']) ?>">
          <img src="<?= !empty($m['photo_path']) ? UPLOAD_URL . e($m['photo_path']) : property_placeholder_svg($m['name']) ?>" alt="<?= e($m['name']) ?>">
          <h4><?= e($m['name']) ?></h4>
          <div class="card-meta"><?= e($m['role_title']) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section style="background:var(--paper-dim)">
  <div class="container">
    <div class="partners-strip reveal">
      <span>Godrej</span><span>Emaar</span><span>Signature</span><span>Whiteland</span><span>M3M</span><span>Ashiana</span>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

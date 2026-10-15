<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/structured-data.php';

$slug = $_GET['slug'] ?? '';

try {
    $stmt = db()->prepare('SELECT t.*, c.name AS corridor_name FROM team_members t LEFT JOIN corridors c ON c.id = t.specialty_corridor_id WHERE t.slug = ? AND t.active = 1 AND t.deleted_at IS NULL LIMIT 1');
    $stmt->execute([$slug]);
    $member = $stmt->fetch();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

if (!$member) {
    http_response_code(404);
    $page_title = 'Team member not found — Kutumb Realty';
    include __DIR__ . '/includes/header.php';
    echo '<section><div class="container"><h1>Team member not found</h1><p><a href="/team.php">Back to team</a></p></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

try {
    $listingsStmt = db()->prepare('SELECT * FROM properties WHERE listing_agent_id = ? AND deleted_at IS NULL ORDER BY featured DESC, id ASC LIMIT 6');
    $listingsStmt->execute([$member['id']]);
    $listings = $listingsStmt->fetchAll();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

$page_title = $member['name'] . ' — Kutumb Realty';
$page_description = $member['name'] . ', ' . $member['role_title'] . ' at Kutumb Realty' . ($member['corridor_name'] ? ' — specializing in ' . $member['corridor_name'] : '') . '.';
$structured_data = structured_data_breadcrumbs([
    ['name' => 'Home', 'url' => '/index.php'],
    ['name' => 'Team', 'url' => '/team.php'],
    ['name' => $member['name'], 'url' => '/agent.php?slug=' . $member['slug']],
]);
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain" style="padding-bottom:32px;">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / <a href="/team.php">Team</a> / <?= e($member['name']) ?></div>
    <h1><?= e($member['name']) ?></h1>
    <p><?= e($member['role_title']) ?><?= $member['corridor_name'] ? ' · ' . e($member['corridor_name']) . ' specialist' : '' ?></p>
  </div>
</section>

<section style="padding-top:40px;">
  <div class="container">
    <div class="two-col">
      <div class="reveal">
        <div class="gallery-hero" style="aspect-ratio:1/1; max-width:320px;">
          <img src="<?= !empty($member['photo_path']) ? UPLOAD_URL . e($member['photo_path']) : property_placeholder_svg($member['name']) ?>" alt="<?= e($member['name']) ?>">
        </div>
        <?php if ($member['bio']): ?><p style="margin-top:24px;"><?= nl2br(e($member['bio'])) ?></p><?php endif; ?>
      </div>
      <div class="sticky-cta">
        <div class="info-card reveal">
          <h4>Get in touch</h4>
          <?php if ($member['whatsapp']): ?><a href="<?= whatsapp_url('Hi ' . $member['name'] . ', I\'d like to talk about a property in Gurgaon.', $member['whatsapp']) ?>" class="btn btn-primary btn-block" target="_blank" rel="noopener" style="margin-bottom:10px;">Chat on WhatsApp</a><?php endif; ?>
          <?php if ($member['phone']): ?><a href="tel:<?= e($member['phone']) ?>" class="btn btn-navy btn-block" style="margin-bottom:10px;">Call <?= e($member['name']) ?></a><?php endif; ?>
          <?php if ($member['email']): ?><a href="mailto:<?= e($member['email']) ?>" class="btn btn-soft btn-block">Email</a><?php endif; ?>
          <div style="border-top:1px solid var(--line); margin-top:18px; padding-top:18px;">
            <p style="font-size:13px; color:var(--ink-soft); margin-bottom:12px;">Prefer a callback? <?= e($member['name']) ?>'s team replies <?= e(SITE_RESPONSE_PROMISE) ?>.</p>
            <form id="agent-callback-form" class="enquiry-form">
              <input type="hidden" name="source" value="<?= e(mb_substr('agent-' . $member['slug'], 0, 40)) ?>">
              <input type="hidden" name="message" value="<?= e('Callback request for ' . $member['name']) ?>">
              <div class="hp-field" aria-hidden="true"><label for="agent-company">Company Website</label><input type="text" id="agent-company" name="company_website" tabindex="-1" autocomplete="off"></div>
              <div class="form-field"><label for="agent-name">Name</label><input type="text" id="agent-name" name="name" required></div>
              <div class="form-field"><label for="agent-phone">Phone</label><input type="tel" id="agent-phone" name="phone" required></div>
              <?php include __DIR__ . '/includes/lead-disclosure.php'; ?>
              <button type="submit" class="btn btn-soft btn-block">Request a Callback</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php if ($listings): ?>
    <div style="margin-top:72px;">
      <div class="section-head reveal">
        <div><span class="eyebrow">Listings</span><h2>Properties handled by <?= e($member['name']) ?></h2></div>
      </div>
      <div class="property-grid reveal reveal-stagger">
        <?php foreach ($listings as $i => $p): include __DIR__ . '/includes/property-card.php'; endforeach; ?>
      </div>
      <?php $fitProperties = $listings; $fitTitle = 'How ' . $member['name'] . '’s listings fit your last search'; $fitLimit = 6; ?>
      <div style="margin-top:32px;"><?php include __DIR__ . '/includes/ai/fit-panel.php'; ?></div>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
(function () {
  var form = document.getElementById('agent-callback-form');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    kutumbSetLoading(btn, true);
    fetch('/api/enquiry-submit.php', { method: 'POST', body: new FormData(form) })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        kutumbToast(res.message, res.ok ? 'success' : 'error');
        if (res.ok) form.reset();
      })
      .catch(function () { kutumbToast('Something went wrong. Please call or WhatsApp us instead.', 'error'); })
      .finally(function () { kutumbSetLoading(btn, false); });
  });
})();
</script>

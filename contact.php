<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/site-stats.php';
require_once __DIR__ . '/includes/structured-data.php';

$page_title = 'Contact — Kutumb Realty';
$page_description = "Talk to a Kutumb Realty advisor about Gurgaon property — call, WhatsApp, or send an enquiry and we'll get back " . SITE_RESPONSE_PROMISE . ".";
$structured_data = structured_data_breadcrumbs([
    ['name' => 'Home', 'url' => '/index.php'],
    ['name' => 'Contact', 'url' => '/contact.php'],
]);
// Arriving from a listing's assistant (WCAG 3.3.7 redundant entry): name the project in
// the message so the visitor doesn't retype it; their question is added client-side
// from sessionStorage so it never travels in the URL.
$prefillProperty = null;
if (!empty($_GET['property'])) {
    try {
        $ps = db()->prepare('SELECT id, name FROM properties WHERE id = ? AND deleted_at IS NULL');
        $ps->execute([(int)$_GET['property']]);
        $prefillProperty = $ps->fetch() ?: null;
    } catch (Throwable $e) {
        $prefillProperty = null;
    }
}
$prefillTopics = ['price' => 'price and availability', 'configuration' => 'configurations', 'possession' => 'possession timeline', 'rera' => 'RERA registration', 'amenities' => 'amenities', 'disclosure' => 'disclosures', 'construction' => 'construction updates'];
$prefillTopic = $prefillTopics[$_GET['topic'] ?? ''] ?? null;
// ?ref=blog-<slug> / agent-<slug>: lets a CTA elsewhere tag where the enquiry came from.
$refSource = preg_match('/^[a-z0-9][a-z0-9-]{0,34}$/', $_GET['ref'] ?? '') ? 'ref:' . $_GET['ref'] : null;
$prefillMessage = $prefillProperty ? 'Regarding ' . $prefillProperty['name'] . ($prefillTopic ? ' (' . $prefillTopic . ')' : '') . ': ' : '';
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / Contact</div>
    <h1>Talk to a Kutumb advisor</h1>
    <p>Tell us what you're looking for — we'll get back <?= e(SITE_RESPONSE_PROMISE) ?>, faster on WhatsApp.</p>
  </div>
</section>

<section>
  <div class="container two-col">
    <div class="reveal">
      <span class="eyebrow">Send an Enquiry</span>
      <h2>Tell us what you need</h2>
      <form id="contact-form">
        <input type="hidden" name="source" value="<?= e($prefillProperty ? 'assistant' : ($refSource ?? 'contact-page')) ?>">
        <?php if ($prefillProperty): ?><input type="hidden" name="property_id" value="<?= (int)$prefillProperty['id'] ?>"><?php endif; ?>
        <div class="hp-field" aria-hidden="true"><label for="contact-company">Company Website</label><input type="text" id="contact-company" name="company_website" tabindex="-1" autocomplete="off"></div>
        <div class="form-field"><label for="contact-name">Full Name</label><input type="text" id="contact-name" name="name" required></div>
        <div class="form-field"><label for="contact-phone">Phone</label><input type="tel" id="contact-phone" name="phone" required></div>
        <div class="form-field"><label for="contact-email">Email (optional)</label><input type="email" id="contact-email" name="email"></div>
        <div class="form-field"><label for="contact-message">What are you looking for?</label><textarea id="contact-message" name="message" placeholder="e.g. 3 BHK on Golf Course Extension Road, budget 3-4 Cr"<?= $prefillProperty ? ' data-prefill-property="' . (int)$prefillProperty['id'] . '"' : '' ?>><?= e($prefillMessage) ?></textarea></div>
        <?php include __DIR__ . '/includes/lead-disclosure.php'; ?>
        <button type="submit" class="btn btn-primary">Submit Enquiry</button>
      </form>
    </div>
    <div class="reveal">
      <div class="info-card" style="margin-bottom:20px;">
        <h4>Office</h4>
        <ul class="info-list">
          <li><?= e(SITE_ADDRESS) ?></li>
          <li><a href="tel:<?= e(SITE_PHONE) ?>"><?= e(SITE_PHONE) ?></a></li>
          <li><a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></li>
        </ul>
        <a href="<?= whatsapp_url("Hi, I'm exploring properties in Gurgaon and would like to talk to an advisor.") ?>" class="btn btn-navy btn-block" target="_blank" rel="noopener" style="margin-top:16px;">Chat on WhatsApp</a>
      </div>
      <div style="border-radius:var(--radius-md); overflow:hidden; aspect-ratio:4/3;">
        <iframe
          src="https://maps.google.com/maps?q=<?= urlencode(SITE_ADDRESS) ?>&output=embed"
          width="100%" height="100%" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
          title="Kutumb Realty office location"></iframe>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
(function () {
  var msg = document.getElementById('contact-message');
  var pid = msg.getAttribute('data-prefill-property');
  if (!pid) return;
  try {
    var saved = JSON.parse(sessionStorage.getItem('kutumb_assistant_prefill') || 'null');
    if (saved && String(saved.property) === pid && saved.question) {
      msg.value = msg.value + saved.question;
    }
  } catch (err) { /* nothing to prefill */ }
})();
document.getElementById('contact-form').addEventListener('submit', function (e) {
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

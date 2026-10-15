<?php
// DRAFT legal copy — to be reviewed by the business owner / counsel before being relied on.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/site-stats.php';
require_once __DIR__ . '/includes/structured-data.php';

$page_title = 'Terms of Use — Kutumb Realty';
$page_description = 'The terms for using the Kutumb Realty website and advisory enquiry services.';
$structured_data = structured_data_breadcrumbs([
    ['name' => 'Home', 'url' => '/index.php'],
    ['name' => 'Terms of Use', 'url' => '/terms.php'],
]);
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / Terms of Use</div>
    <h1>Terms of Use</h1>
    <p>Plain-language terms for using this website.</p>
  </div>
</section>

<section>
  <div class="container legal-page">
    <p class="legal-updated">Last updated: 12 October 2026</p>

    <h2>Information, not an offer</h2>
    <p>Listings, prices, rate bands, availability, possession dates and calculator results on this site are indicative and can change without notice. They are not an offer or a guarantee. Please confirm current details with an advisor and check each project&rsquo;s RERA registration on the Haryana RERA website before you make a decision.</p>

    <h2>Advice</h2>
    <p>Our advisors give property guidance, but you remain responsible for your own decisions, including legal and financial checks. Fit scores, comparisons and AI-generated answers are aids to your thinking, not professional, legal or financial advice.</p>

    <h2>Contacting you</h2>
    <p>When you submit a form, you agree that we may contact you about that request by phone, WhatsApp or email. See our <a href="/privacy-policy.php">Privacy Policy</a>. You can ask us to stop at any time.</p>

    <h2>Using the site</h2>
    <p>Please use the site lawfully and give accurate details. Do not attempt to disrupt it, scrape it at scale, or submit false or abusive enquiries. Content and branding on this site belong to Kutumb Realty or its licensors.</p>

    <h2>Liability</h2>
    <p>To the extent the law allows, we are not liable for losses arising from reliance on indicative information on this site. Nothing here limits liability that cannot be limited by law.</p>

    <h2>Contact</h2>
    <p>Questions about these terms: <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a>.</p>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
/** Included when a page's critical database query fails. Ends the request. */
http_response_code(500);
$page_title = 'Something went wrong — Kutumb Realty';
$page_description = 'We hit a technical issue loading this page — please try again shortly, or reach an advisor directly.';
require_once __DIR__ . '/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <h1>Something went wrong on our end</h1>
    <p>We're already looking into it. In the meantime, you can reach an advisor directly.</p>
  </div>
</section>

<section style="text-align:center;">
  <div class="container">
    <a href="/index.php" class="btn btn-primary">Back to Home</a>
    <a href="<?= whatsapp_url("Hi, I ran into a technical issue on your website and need some help.") ?>" class="btn btn-navy" target="_blank" rel="noopener" style="margin-left:12px;">Chat on WhatsApp</a>
  </div>
</section>

<?php
require_once __DIR__ . '/footer.php';
exit;

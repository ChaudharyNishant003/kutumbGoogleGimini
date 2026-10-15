<?php
// DRAFT legal copy — to be reviewed by the business owner / counsel before being relied on.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/site-stats.php';
require_once __DIR__ . '/includes/structured-data.php';

$page_title = 'Privacy Policy — Kutumb Realty';
$page_description = 'How Kutumb Realty collects, uses and protects the information you share when you enquire about Gurgaon property.';
$structured_data = structured_data_breadcrumbs([
    ['name' => 'Home', 'url' => '/index.php'],
    ['name' => 'Privacy Policy', 'url' => '/privacy-policy.php'],
]);
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / Privacy Policy</div>
    <h1>Privacy Policy</h1>
    <p>What we collect when you contact us, why, and the choices you have.</p>
  </div>
</section>

<section>
  <div class="container legal-page">
    <p class="legal-updated">Last updated: 12 October 2026</p>

    <h2>Who we are</h2>
    <p>This website is run by Kutumb Real Estate Private Limited (&ldquo;Kutumb Realty&rdquo;, &ldquo;we&rdquo;), <?= e(SITE_ADDRESS) ?>. You can reach us at <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a> or <a href="tel:<?= e(SITE_PHONE) ?>"><?= e(SITE_PHONE) ?></a>.</p>

    <h2>What we collect</h2>
    <ul>
      <li><strong>What you send us:</strong> your name, phone number, optional email and message, and the property or corridor you asked about.</li>
      <li><strong>Search preferences</strong> you save with us (budget, corridor, configuration, timeline).</li>
      <li><strong>Where the request came from:</strong> the page or form you used, so the right advisor can help.</li>
      <li><strong>Technical data:</strong> your IP address is logged when you submit a form, only to limit spam and abuse. A short-lived cookie records that you viewed a page, so view counts are not inflated.</li>
      <li><strong>Calls:</strong> if an advisor speaks with you, we keep notes of the conversation and the details you shared.</li>
      <li><strong>Chat and AI questions:</strong> the questions you ask our chat assistant and AI search, and the answers shown, are saved so our advisors can follow up and improve our information. When you use them, a cookie (<code>kv_vid</code>, kept for up to a year) lets us recognise your browser so your earlier questions stay together. If you share your name and phone number in the chat, these questions are linked to your enquiry.</li>
    </ul>

    <h2>How we use it</h2>
    <ul>
      <li>To respond to your enquiry and contact you by phone, WhatsApp or email about it.</li>
      <li>To shortlist properties that match your brief and to follow up when you ask us to.</li>
      <li>To keep our service safe, fix problems and improve how the website and our advisors work.</li>
    </ul>
    <p>We do not sell your personal information.</p>

    <h2>AI-assisted features</h2>
    <p>Some features (the property assistant and the home finder) use third-party AI service providers to process what you type. Please do not share sensitive financial identifiers, passwords or government ID numbers with them. A Kutumb advisor remains responsible for any advice you act on.</p>

    <h2>Who we share it with</h2>
    <p>Only with the people and service providers needed to run the service &mdash; for example our advisors, hosting and messaging providers, and AI service providers &mdash; or where the law requires it. Our pages also load fonts and maps from third-party providers, which may receive your IP address.</p>

    <h2>Your choices</h2>
    <ul>
      <li>Ask us to stop calling or messaging you at any time &mdash; on a call, by WhatsApp, or by email. We will record the request and stop.</li>
      <li>Ask to see, correct or delete the information we hold about you by writing to <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a>.</li>
      <li>You can withdraw your consent whenever you like; this does not affect what we did before you withdrew it.</li>
    </ul>

    <h2>How long we keep it</h2>
    <p>We keep enquiry information for as long as it is needed to help you and to meet legal and accounting obligations, then delete or anonymise it.</p>

    <h2>Changes</h2>
    <p>We will update this page when our practices change and show the date above.</p>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$prefillPrice = isset($_GET['price']) ? (float)$_GET['price'] : 0;
$prefillName = $_GET['name'] ?? '';

$page_title = 'EMI Calculator — Kutumb Realty';
$page_scripts = ['emi-calculator'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / EMI Calculator</div>
    <h1>Home Loan EMI Calculator</h1>
    <p>Estimate your monthly EMI for any property in our portfolio — adjust down payment, interest rate and tenure to see what fits your budget.<?= ($prefillName && $prefillPrice) ? ' Pre-filled for ' . e($prefillName) . ' at ' . e(format_inr($prefillPrice)) . '.' : '' ?></p>
  </div>
</section>

<section>
  <div class="container two-col">
    <div class="reveal">
      <span class="eyebrow">Calculate</span>
      <h2>Your Monthly EMI</h2>
      <form id="emi-form" class="emi-form">
        <div class="form-field"><label for="emi-price">Property Price (₹)</label><input type="number" id="emi-price" min="0" step="1000" value="<?= e((string)($prefillPrice ?: 10000000)) ?>"></div>
        <div class="form-field"><label for="emi-down-pct">Down Payment (%)</label><input type="number" id="emi-down-pct" min="0" max="90" step="1" value="20"></div>
        <div class="form-field"><label for="emi-rate">Interest Rate (% p.a.)</label><input type="number" id="emi-rate" min="0" max="20" step="0.05" value="8.5"></div>
        <div class="form-field"><label for="emi-years">Loan Tenure (Years)</label><input type="number" id="emi-years" min="1" max="30" step="1" value="20"></div>
      </form>
    </div>
    <div class="reveal">
      <div class="info-card">
        <span class="eyebrow" style="margin-bottom:4px;">Estimated EMI</span>
        <div id="emi-result"></div>
        <div id="emi-breakdown" class="emi-breakdown"></div>
        <div class="emi-assumptions">
          <strong>What this estimate assumes</strong>
          <ul>
            <li>Fixed interest rate, monthly reducing balance, equal monthly instalments for the full tenure.</li>
            <li>The 8.5% rate and 20-year tenure shown are illustrative defaults, not an offer from any lender &mdash; change them to match your quote.</li>
            <li>Not included: processing and other lender fees, loan insurance, stamp duty, registration charges, GST and maintenance deposits.</li>
          </ul>
          <p>Actual EMI depends on the lender&rsquo;s terms and your eligibility. An advisor can talk you through financing options.</p>
        </div>
        <a id="emi-advisor-link" href="<?= whatsapp_url("Hi, I'd like help understanding financing options for a property purchase.") ?>" data-base="<?= e(whatsapp_url('')) ?>" class="btn btn-primary btn-block" target="_blank" rel="noopener" style="margin-top:12px;">Discuss these numbers with an advisor</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
function nav_active(string $file, string $current): string
{
    return $file === $current ? ' active' : '';
}
$navCorridors = get_active_corridors();
$navTrustLines = [
    site_years_in_business() . '+ years advising Gurgaon families',
    SITE_CUSTOMERS_SERVED . ' families advised since ' . SITE_FOUNDED_YEAR,
    site_corridor_phrase(),
];
?>
<?php if ($navCorridors): ?>
<div class="corridor-ticker" aria-hidden="true">
  <span class="corridor-ticker-label">Indicative rate bands</span>
  <div class="corridor-ticker-viewport">
    <div class="corridor-ticker-track">
      <?php
      $tickerItems = [];
      $tickerTierLabels = ['luxury' => 'Luxury', 'premium' => 'Premium'];
      foreach ($navCorridors as $i => $tc) {
          $tierTag = isset($tickerTierLabels[$tc['tier'] ?? '']) ? '<span class="corridor-ticker-tier tier-' . e($tc['tier']) . '">' . e($tickerTierLabels[$tc['tier']]) . '</span>' : '';
          $tickerItems[] = '<span class="corridor-ticker-item">' . $tierTag . e($tc['name']) . '<b>' . e($tc['avg_price_band']) . '</b></span>';
          if ($i % 2 === 1) {
              $tickerItems[] = '<span class="corridor-ticker-item is-trust">' . e($navTrustLines[(int)($i / 2) % count($navTrustLines)]) . '</span>';
          }
      }
      // Duplicated x2 so the track can loop seamlessly at translateX(-50%).
      echo implode('', $tickerItems) . implode('', $tickerItems);
      ?>
    </div>
  </div>
</div>
<?php endif; ?>
<header class="site-header">
  <div class="container">
    <a href="/index.php" class="logo">
      <svg width="30" height="30" viewBox="0 0 48 48" fill="none" aria-hidden="true" class="logo-mark">
        <path d="M8 33 L24 9" stroke-width="4" stroke-linecap="round"/>
        <path d="M40 33 L24 9" stroke-width="4" stroke-linecap="round"/>
      </svg>
      <span class="logo-text">Kutumb<span>Realty</span></span>
    </a>
    <nav class="main-nav">
      <a href="/index.php" class="<?= nav_active('index.php', $current) ?>">Home</a>
      <a href="/about.php" class="<?= nav_active('about.php', $current) ?>">About</a>
      <a href="/properties.php" class="<?= in_array($current, ['properties.php', 'property.php', 'sector.php'], true) ? 'active' : '' ?>">Projects</a>
      <a href="/blog.php" class="<?= in_array($current, ['blog.php', 'blog-post.php'], true) ? 'active' : '' ?>">Insights</a>
      <a href="/why-an-advisor.php" class="<?= nav_active('why-an-advisor.php', $current) ?>">Why an Advisor</a>
      <a href="/contact.php" class="<?= nav_active('contact.php', $current) ?>">Contact</a>
    </nav>
    <div class="header-actions">
      <a class="header-phone" href="tel:<?= e(SITE_PHONE) ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        <span><?= e(SITE_PHONE) ?></span>
      </a>
      <a href="/contact.php" class="btn btn-primary btn-sm">Enquire Now</a>
      <button class="nav-toggle" aria-label="Toggle menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
    </div>
  </div>
</header>

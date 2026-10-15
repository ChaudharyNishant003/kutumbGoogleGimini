<footer class="site-footer texture-grain">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-logo">
          <svg width="36" height="36" viewBox="0 0 48 48" fill="none" aria-hidden="true" class="logo-mark">
            <path d="M8 33 L24 9" stroke-width="4" stroke-linecap="round"/>
            <path d="M40 33 L24 9" stroke-width="4" stroke-linecap="round"/>
          </svg>
          <span>Kutumb<span style="color:var(--gold)">Realty</span></span>
        </div>
        <p style="color:rgba(255,255,255,0.62); max-width:320px; font-size:14px;">Curated residential &amp; commercial property advisory across Gurgaon — founded <?= SITE_FOUNDED_YEAR ?>, <?= SITE_CUSTOMERS_SERVED ?> customers served.</p>
        <?php if (SITE_FACEBOOK_URL || SITE_INSTAGRAM_URL || SITE_LINKEDIN_URL): ?>
        <div class="social-row">
          <?php if (SITE_FACEBOOK_URL): ?><a href="<?= e(SITE_FACEBOOK_URL) ?>" target="_blank" rel="noopener" aria-label="Facebook"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a><?php endif; ?>
          <?php if (SITE_INSTAGRAM_URL): ?><a href="<?= e(SITE_INSTAGRAM_URL) ?>" target="_blank" rel="noopener" aria-label="Instagram"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.6" fill="currentColor" stroke="none"/></svg></a><?php endif; ?>
          <?php if (SITE_LINKEDIN_URL): ?><a href="<?= e(SITE_LINKEDIN_URL) ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4V8h4v1.5A6 6 0 0 1 16 8z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div>
        <h4>Explore</h4>
        <ul>
          <li><a href="/index.php">Home</a></li>
          <li><a href="/about.php">About Us</a></li>
          <li><a href="/why-an-advisor.php">Why an Advisor</a></li>
          <li><a href="/blog.php">Insights</a></li>
          <li><a href="/team.php">Our Team</a></li>
          <li><a href="/contact.php">Contact</a></li>
        </ul>
      </div>
      <div>
        <h4>Tools</h4>
        <ul>
          <li><a href="/emi-calculator.php">EMI Calculator</a></li>
          <li><a href="/compare.php">Compare Properties</a></li>
          <li><a href="/map-search.php">Map Search</a></li>
          <li><a href="/saved-searches.php">Get Matched</a></li>
        </ul>
      </div>
      <div>
        <h4>Corridors</h4>
        <ul>
          <?php
          if (function_exists('db')) {
              try {
                  $corridors = get_active_corridors();
                  foreach ($corridors as $c) {
                      echo '<li><a href="/sector.php?slug=' . e($c['slug']) . '">' . e($c['name']) . '</a></li>';
                  }
              } catch (Throwable $e) { /* DB not reachable — footer degrades gracefully */ }
          }
          ?>
        </ul>
      </div>
      <div>
        <h4>Contact</h4>
        <ul class="info-list" style="color:rgba(255,255,255,0.68)">
          <li><?= e(SITE_ADDRESS) ?></li>
          <li><a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></li>
          <li><a href="tel:<?= e(SITE_PHONE) ?>"><?= e(SITE_PHONE) ?></a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> Kutumb Realty. All rights reserved. &middot; <a href="/privacy-policy.php">Privacy Policy</a> &middot; <a href="/terms.php">Terms</a></span>
      <span>RERA status is shown on every project page &mdash; always verify it on the Haryana RERA website.</span>
    </div>
  </div>
</footer>

<button class="chat-toggle" id="chat-toggle" aria-label="Chat with Kutumb assistant">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
</button>
<div class="chat-panel" id="chat-panel">
  <div class="chat-head texture-grain">
    <span class="chat-head-avatar" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/></svg></span>
    <div class="chat-head-text">
      <strong>Asha, your Kutumb advisor</strong>
      <span>Usually replies in minutes</span>
    </div>
  </div>
  <a href="<?= whatsapp_url("Hi, I'd like to talk to a person instead of the chatbot.") ?>" class="chat-human-link" target="_blank" rel="noopener">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.52 0-10 4.48-10 10 0 1.76.46 3.48 1.34 5L2 22l5.16-1.35a10 10 0 0 0 4.88 1.24h.01c5.52 0 10-4.48 10-10s-4.48-10-10.01-10zm5.87 14.3c-.25.7-1.45 1.34-2 1.42-.51.08-1.15.11-1.86-.12-.43-.13-.98-.32-1.68-.62-2.96-1.28-4.89-4.26-5.04-4.46-.15-.2-1.2-1.6-1.2-3.05 0-1.45.76-2.16 1.03-2.46.27-.3.59-.37.79-.37.2 0 .39 0 .56.01.18.01.42-.07.65.5.25.6.85 2.07.92 2.22.07.15.12.33.02.53-.1.2-.15.32-.3.5-.15.18-.31.4-.44.53-.15.15-.3.31-.13.6.17.3.76 1.25 1.63 2.03 1.12 1 2.06 1.31 2.36 1.46.3.15.47.13.65-.08.18-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.28.1 1.75.83 2.05 .98.3.15.5.23.57.35.08.13.08.72-.17 1.42z"/></svg>
    Talk to a person on WhatsApp
  </a>
  <div class="chat-messages" id="chat-messages">
    <div class="chat-bubble bot">Hi! Ask me about any property we have listed — price, sector, configuration, or corridor.</div>
  </div>
  <form class="chat-input-row" id="chat-form">
    <input type="text" id="chat-input" placeholder="e.g. What's available under 2 Cr?" autocomplete="off">
    <button type="submit" aria-label="Send">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </form>
</div>

<?php include __DIR__ . '/compare-bar.php'; ?>

<?php
// $page_scripts (set by the calling page before including this file) lists
// this page's own optional, self-guarded scripts — see PROJECT docs, Batch 2.3.
// GSAP/SplitType/toast/main/chatbot/compare/premium-ui stay global: main.js's
// reveal-fade-ins, magnetic buttons, and chat-toggle pulse run on every page
// and are gated behind GSAP being loaded (see main.js line ~117), and
// chatbot/compare operate on the chat widget + compare bar in this same
// footer, present on every page.
$page_scripts = $page_scripts ?? [];
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/split-type@0.3.4/umd/index.min.js"></script>
<?php if (in_array('hero-canvas', $page_scripts, true)): ?><script src="/assets/js/hero-canvas.js"></script><?php endif; ?>
<script src="/assets/js/toast.js"></script>
<script src="/assets/js/main.js"></script>
<script src="/assets/js/lead-capture.js?v=<?= filemtime(__DIR__ . '/../assets/js/lead-capture.js') ?>"></script>
<script src="/assets/js/chatbot.js?v=<?= filemtime(__DIR__ . '/../assets/js/chatbot.js') ?>"></script>
<script src="/assets/js/compare.js"></script>
<?php if (in_array('emi-calculator', $page_scripts, true)): ?><script src="/assets/js/emi-calculator.js"></script><?php endif; ?>
<?php if (in_array('gallery', $page_scripts, true)): ?><script src="/assets/js/gallery.js"></script><?php endif; ?>
<?php if (in_array('hero-chat', $page_scripts, true)): ?><script src="/assets/js/hero-chat.js"></script><?php endif; ?>
<?php if (in_array('rate-stack', $page_scripts, true)): ?><script src="/assets/js/rate-stack.js"></script><?php endif; ?>
<script src="/assets/js/premium-ui.js"></script>
<?php foreach (['ai-finder', 'ai-neighborhood', 'ai-assistant'] as $aiScript): ?>
<?php if (in_array($aiScript, $page_scripts, true) && is_file(__DIR__ . '/../assets/js/' . $aiScript . '.js')): ?><script src="/assets/js/<?= $aiScript ?>.js?v=<?= filemtime(__DIR__ . '/../assets/js/' . $aiScript . '.js') ?>" defer></script><?php endif; ?>
<?php endforeach; ?>
</body>
</html>

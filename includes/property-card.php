<?php
/** Expects $p (property row) and optional $i (index, for stagger delay) in scope. */
$i = $i ?? 0;
?>
<a class="card-property reveal" href="/property.php?id=<?= (int)$p['id'] ?>" style="--i:<?= $i % 8 ?>" data-cursor="View">
  <div class="card-media">
    <div class="card-badges">
      <?php if (!empty($p['featured'])): ?><span class="card-badge featured">Featured</span>
      <?php elseif (!empty($p['is_eoi'])): ?><span class="card-badge eoi">EOI Open</span>
      <?php else: ?><span class="card-badge"><?= e(category_label($p['category'])) ?></span><?php endif; ?>
      <?= tier_badge_html($p['tier'] ?? 'standard') ?>
    </div>
    <span class="compare-toggle" role="button" tabindex="0" data-id="<?= (int)$p['id'] ?>" data-name="<?= e($p['name']) ?>" aria-label="Add to compare" aria-pressed="false" data-cursor="Compare">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </span>
    <img src="<?= property_card_image_src($p) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
  </div>
  <div class="card-body">
    <h3><?= e($p['name']) ?></h3>
    <div class="card-meta"><?= e($p['sector']) ?> · <?= e($p['developer']) ?></div>
    <div class="card-price">
      <span class="amount"><?= e($p['price_display']) ?></span>
      <span class="card-cta">View Details <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
    </div>
  </div>
</a>

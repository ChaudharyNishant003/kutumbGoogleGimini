<?php
/**
 * "Recommended because" on the search results page (Feature 1).
 * Expects $filters (validated) and $total in scope. Optional $rec_title, $rec_invite_target.
 */
require_once __DIR__ . '/../ai-recommend.php';

$rec_title = $rec_title ?? 'Best fit for what you asked';
$rec_invite_target = $rec_invite_target ?? '#ai-finder-compact-q';
$rec_items = (filters_active($filters) && ($total ?? 0) > 0) ? rec_top($filters, 2) : [];
?>
<?php if ($rec_items): ?>
<section class="ai-recommend" id="ai-recommend" aria-labelledby="ai-recommend-title">
  <div class="ai-recommend-head">
    <div>
      <span class="ai-eyebrow">Ranked by fit</span>
      <h2 class="ai-recommend-title" id="ai-recommend-title"><?= e($rec_title) ?></h2>
    </div>
    <p class="ai-recommend-note">Ranked only by the filters you chose — never by hidden tracking. Every reason below is checked against our listing data.</p>
  </div>
  <div class="ai-recommend-grid">
    <?php foreach ($rec_items as $item): $rp = $item['p']; $reasons = $item['reasons']; ?>
      <article class="ai-rec">
        <a class="ai-rec-card" href="/property.php?id=<?= (int)$rp['id'] ?>">
          <img src="<?= property_card_image_src($rp) ?>" alt="<?= e($rp['name']) ?>" loading="lazy">
          <div class="ai-rec-card-body">
            <h3><?= e($rp['name']) ?></h3>
            <div class="ai-rec-meta"><?= e($rp['sector']) ?><?= !empty($rp['bhk_config']) ? ' · ' . e($rp['bhk_config']) : '' ?></div>
            <div class="ai-rec-price"><?= e($rp['price_display']) ?></div>
          </div>
        </a>
        <div class="ai-rec-why">
          <p class="ai-rec-why-title">Recommended because</p>
          <?php $reasonsCompact = false; include __DIR__ . '/reasons-list.php'; ?>
          <p class="ai-rec-score"><?= (int)$item['score'] ?> of <?= count($item['reasons']) ?> of your requirements met</p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php elseif (!filters_active($filters) && ($rec_show_invite ?? true)): ?>
<div class="ai-recommend-invite" id="ai-recommend">
  <div>
    <span class="ai-eyebrow">Ranked by fit</span>
    <p>Tell us what you need — we'll rank homes by how well they fit and show exactly why each one is recommended.</p>
  </div>
  <a href="<?= e($rec_invite_target) ?>" class="btn btn-soft btn-sm" data-focus-target="<?= e($rec_invite_target) ?>">Describe your home</a>
</div>
<?php endif; ?>

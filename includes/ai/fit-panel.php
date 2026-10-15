<?php
/**
 * "How these fit your search" — reasons for a given set of listings against the
 * visitor's last search (Feature 1 pattern). Renders nothing without a remembered search.
 * Expects $fitProperties (rows). Optional $fitLimit, $fitTitle.
 */
require_once __DIR__ . '/../ai-recommend.php';

$fitFilters = filters_remembered();
if (!$fitFilters || empty($fitProperties)) return;

$fitItems = [];
foreach ($fitProperties as $i => $fp) {
    $reasons = rec_reasons($fp, $fitFilters);
    if (!$reasons) continue;
    $fitItems[] = ['p' => $fp, 'reasons' => $reasons, 'score' => rec_score($reasons), 'order' => $i];
}
usort($fitItems, fn($a, $b) => [$b['score'], $a['order']] <=> [$a['score'], $b['order']]);
$fitItems = array_slice($fitItems, 0, $fitLimit ?? 3);
if (!$fitItems) return;
?>
<section class="ai-fit" aria-labelledby="ai-fit-title">
  <div class="ai-recommend-head">
    <div>
      <span class="ai-eyebrow">Ranked by fit</span>
      <h3 class="ai-recommend-title" id="ai-fit-title"><?= e($fitTitle ?? 'How these fit your last search') ?></h3>
    </div>
    <p class="ai-recommend-note">
      Your search: <?= e(filters_describe($fitFilters)) ?>.
      <a href="<?= e(filters_url($fitFilters)) ?>">Change it</a>
    </p>
  </div>
  <div class="ai-fit-grid">
    <?php foreach ($fitItems as $item): $reasons = $item['reasons']; $reasonsCompact = true; ?>
      <div class="ai-related-why-item">
        <p class="ai-rec-why-title"><a href="/property.php?id=<?= (int)$item['p']['id'] ?>"><?= e($item['p']['name']) ?></a> — <?= (int)$item['score'] ?> of <?= count($reasons) ?> requirements met</p>
        <?php include __DIR__ . '/reasons-list.php'; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

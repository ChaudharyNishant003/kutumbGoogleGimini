<?php
/**
 * Why each "You might also like" listing is suggested, compared with the
 * listing being viewed (Feature 1 pattern). Expects $related and $property.
 */
if (empty($related)) return;

$relReasons = function (array $rp, array $base): array {
    $reasons = [];
    $reasons[] = ['ok' => true, 'text' => 'Same corridor' . (!empty($base['corridor_name']) ? ' (' . $base['corridor_name'] . ')' : '')];
    $sameCat = ($rp['category'] ?? '') === ($base['category'] ?? '');
    $reasons[] = $sameCat
        ? ['ok' => true, 'text' => category_label($rp['category']) . ', like this project']
        : ['ok' => false, 'text' => 'Different stage: ' . category_label($rp['category'] ?? '')];
    $a = (float)($rp['price_min'] ?? 0);
    $b = (float)($base['price_min'] ?? 0);
    if ($a > 0 && $b > 0) {
        $diff = ($a - $b) / $b;
        $reasons[] = abs($diff) <= 0.2
            ? ['ok' => true, 'text' => 'Similar starting price (' . ($rp['price_display'] ?? '') . ')']
            : ['ok' => false, 'text' => 'Starts ' . ($diff > 0 ? 'higher' : 'lower') . ' (' . ($rp['price_display'] ?? '') . ')'];
    }
    $sameSector = strcasecmp((string)($rp['sector'] ?? ''), (string)($base['sector'] ?? '')) === 0;
    $reasons[] = $sameSector
        ? ['ok' => true, 'text' => 'Same sector (' . $rp['sector'] . ')']
        : ['ok' => false, 'text' => 'Nearby sector: ' . ($rp['sector'] ?? '')];
    return $reasons;
};
?>
<div class="ai-related-why" aria-label="Why these projects are suggested">
  <?php foreach ($related as $rp): $reasons = $relReasons($rp, $property); $reasonsCompact = true; ?>
    <div class="ai-related-why-item">
      <p class="ai-rec-why-title"><?= e($rp['name']) ?> — suggested because</p>
      <?php include __DIR__ . '/reasons-list.php'; ?>
    </div>
  <?php endforeach; ?>
</div>

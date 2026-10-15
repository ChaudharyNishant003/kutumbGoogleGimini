<?php
/**
 * AI Home Finder + Voice (Features 2 & 3).
 * Optional in scope: $hf_variant ('hero' | 'compact'), $hf_filters (validated filter set for a
 * server-rendered start state), $hf_total (match count for $hf_filters), $hf_title, $hf_placeholder.
 */
require_once __DIR__ . '/../ai-finder.php';

$hf_variant = $hf_variant ?? 'hero';
$hf_id = 'ai-finder-' . $hf_variant;
$hf_filters = $hf_filters ?? null;
$hf_placeholder = $hf_placeholder ?? '3 BHK in Sector 86 under ₹3 crore, ready to move';
$hf_title = $hf_title ?? ($hf_variant === 'hero' ? 'Tell us what you’re looking for' : 'Describe your home in your own words');
$hf_enabled = ai_settings()['enabled'];
$hf_target = $hf_target_path ?? '/properties.php';
$hf_anchor = $hf_target === '/properties.php' ? '#ai-recommend' : '';
$hf_context = $hf_context ?? [];
$hf_fill_form = $hf_fill_form ?? '';

$hf_state = 'A';
$hf_chips = [];
$hf_relax = [];
if ($hf_filters && filters_active($hf_filters)) {
    $hf_state = ($hf_total ?? filters_count($hf_filters)) > 0 ? 'B' : 'C';
    $hf_chips = filters_chips($hf_filters);
    $hf_relax = $hf_state === 'C' ? finder_relaxations($hf_filters) : [];
    $hf_count = $hf_total ?? filters_count($hf_filters);
}
$hf_whatsapp = whatsapp_url("Hi, I'm looking for a home in Gurgaon and would like an advisor's help.");
?>
<?php if ($hf_variant === 'hero'): ?><section class="ai-finder-section" aria-labelledby="<?= e($hf_id) ?>-title"><div class="container"><?php endif; ?>
<div class="ai-finder ai-finder--<?= e($hf_variant) ?>" id="<?= e($hf_id) ?>" data-finder data-variant="<?= e($hf_variant) ?>" data-state="<?= e($hf_state) ?>" data-target-path="<?= e($hf_target) ?>" data-target-anchor="<?= e($hf_anchor) ?>" data-context="<?= e(json_encode($hf_context)) ?>"<?= $hf_fill_form ? ' data-fill-form="' . e($hf_fill_form) . '"' : '' ?><?= $hf_enabled ? '' : ' data-ai-off' ?>>
  <div class="ai-finder-head">
    <div>
      <span class="ai-eyebrow">AI Home Finder</span>
      <h2 class="ai-finder-title<?= $hf_variant === 'hero' ? '' : ' ai-finder-title--sm' ?>" id="<?= e($hf_id) ?>-title"><?= e($hf_title) ?></h2>
      <?php if ($hf_variant === 'hero'): ?>
        <p class="ai-finder-sub">Type naturally — in English, Hindi or Hinglish. We turn it into filters you can check and edit before anything is searched.</p>
      <?php endif; ?>
    </div>
  </div>

  <form class="ai-finder-bar" data-finder-form action="<?= e($hf_target) ?>" method="get" role="search">
    <label for="<?= e($hf_id) ?>-q" class="sr-only">Describe the home you're looking for</label>
    <input type="text" id="<?= e($hf_id) ?>-q" name="keyword" class="ai-finder-input" maxlength="<?= AI_MAX_INPUT ?>" placeholder="<?= e($hf_placeholder) ?>" autocomplete="off" data-finder-input>
    <button type="submit" class="ai-finder-go" aria-label="Find matching homes">→</button>
  </form>

  <p class="ai-finder-msg" data-finder-msg role="status" aria-live="polite"></p>

  <!-- State A: examples -->
  <div class="ai-finder-state" data-for="A">
    <div class="ai-examples">
      <span class="ai-examples-label">Examples:</span>
      <button type="button" class="ai-example" data-example="Ready-to-move home under ₹3 crore">Ready-to-move home</button>
      <button type="button" class="ai-example" data-example="3 BHK near a business district, near metro">Near business district</button>
      <button type="button" class="ai-example" data-example="Luxury 4 BHK on Golf Course Road">Luxury home</button>
    </div>
  </div>

  <!-- State B: interpretation review -->
  <div class="ai-finder-state" data-for="B">
    <div class="ai-panel">
      <p class="ai-panel-title">I understood:</p>
      <div class="ai-chip-group">
        <span class="ai-chip-label">Required</span>
        <div class="ai-chips" data-chips="req">
          <?php foreach ($hf_chips as $c): if ($c['type'] !== 'req') continue; ?>
            <span class="ai-chip ai-chip--req"><?= e($c['label']) ?><a class="ai-chip-x" href="<?= e(filters_url($c['filters'], $hf_target)) ?>" data-chip-query="<?= e(json_encode(filters_to_query($c['filters']))) ?>" aria-label="Remove <?= e($c['label']) ?>">×</a></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="ai-chip-group" data-pref-group<?= array_filter($hf_chips, fn($c) => $c['type'] === 'pref') ? '' : ' hidden' ?>>
        <span class="ai-chip-label">Preference</span>
        <div class="ai-chips" data-chips="pref">
          <?php foreach ($hf_chips as $c): if ($c['type'] !== 'pref') continue; ?>
            <span class="ai-chip ai-chip--pref"><?= e($c['label']) ?><a class="ai-chip-x" href="<?= e(filters_url($c['filters'], $hf_target)) ?>" data-chip-query="<?= e(json_encode(filters_to_query($c['filters']))) ?>" aria-label="Remove <?= e($c['label']) ?>">×</a></span>
          <?php endforeach; ?>
        </div>
      </div>
      <p class="ai-finder-note" data-finder-note hidden></p>
      <div class="ai-actions">
        <a href="<?= e($hf_filters ? filters_url($hf_filters, $hf_target) . $hf_anchor : $hf_target) ?>" class="btn btn-primary" data-search-link><?= isset($hf_count) ? 'Show ' . (int)$hf_count . ' matching project' . ((int)$hf_count === 1 ? '' : 's') : 'Search' ?></a>
        <button type="button" class="btn btn-soft" data-edit>Edit requirements</button>
      </div>
    </div>
  </div>

  <!-- State C: no exact match -->
  <div class="ai-finder-state" data-for="C">
    <div class="ai-panel">
      <p class="ai-panel-title">I couldn't find an exact match.</p>
      <p class="ai-finder-note" data-c-wanted><?= $hf_chips ? 'You asked for: ' . e(finder_describe($hf_filters)) : '' ?></p>
      <span class="ai-chip-label" data-relax-label<?= $hf_relax ? '' : ' hidden' ?>>Closest options</span>
      <div class="ai-options" data-relax>
        <?php foreach ($hf_relax as $r): ?>
          <a class="ai-option" href="<?= e(filters_url(filters_from_array($r['query']), $hf_target) . $hf_anchor) ?>"><span><?= e($r['label']) ?></span><span class="ai-option-count"><?= (int)$r['count'] ?> project<?= $r['count'] === 1 ? '' : 's' ?> →</span></a>
        <?php endforeach; ?>
      </div>
      <div class="ai-actions">
        <button type="button" class="btn btn-soft btn-sm" data-edit>Change requirements</button>
        <a href="/properties.php" class="btn btn-soft btn-sm">Show all properties</a>
        <a href="<?= $hf_whatsapp ?>" class="btn btn-soft btn-sm" target="_blank" rel="noopener">Ask an advisor</a>
      </div>
    </div>
  </div>

  <!-- State D: ambiguous term -->
  <div class="ai-finder-state" data-for="D">
    <div class="ai-panel">
      <p class="ai-panel-title">“<span data-amb-term></span>” can mean different things.</p>
      <div class="ai-actions" data-amb-options>
        <button type="button" class="ai-choice" data-amb="nearest_school" hidden>Nearest schools (within 3 km)</button>
        <button type="button" class="ai-choice" data-amb="ignore">Search without it</button>
        <a class="ai-choice" href="<?= $hf_whatsapp ?>" target="_blank" rel="noopener" data-amb="advisor">Ask an advisor</a>
      </div>
      <p class="ai-finder-note" data-amb-school-note hidden>We don’t rate or rank schools — we can show which schools are nearest, with distances from our verified location list.</p>
    </div>
  </div>
</div>
<?php if ($hf_variant === 'hero'): ?></div></section><?php endif; ?>

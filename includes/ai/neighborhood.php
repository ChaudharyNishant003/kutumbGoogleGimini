<?php
/**
 * AI Neighbourhood Assistant (Feature 4). The plain list is the real content;
 * the map is an optional enhancement loaded only on request.
 * Expects $property (with lat/lng and corridor_lat/corridor_lng).
 */
require_once __DIR__ . '/../ai-places.php';
require_once __DIR__ . '/../ai.php';

$nb_loc = property_location($property);
$nb_places = $nb_loc ? nearby_by_category($nb_loc['lat'], $nb_loc['lng']) : [];
if (!$nb_places) return;

$nb_first = array_key_first($nb_places);
$nb_mapData = [
    'center' => ['lat' => $nb_loc['lat'], 'lng' => $nb_loc['lng'], 'name' => $property['name'], 'approximate' => $nb_loc['approximate']],
    'places' => [],
];
foreach ($nb_places as $key => $cat) {
    foreach ($cat['items'] as $pl) {
        $nb_mapData['places'][] = ['cat' => $key, 'name' => $pl['name'], 'lat' => (float)$pl['lat'], 'lng' => (float)$pl['lng'], 'km' => $pl['km']];
    }
}
$nb_askable = ai_settings()['enabled'];
?>
<section class="ai-nb" id="ai-neighborhood" data-nb data-property-id="<?= (int)$property['id'] ?>" aria-labelledby="ai-nb-title">
  <span class="ai-eyebrow">Neighbourhood</span>
  <h3 class="ai-nb-title" id="ai-nb-title">What's around <?= e($property['name']) ?></h3>
  <?php if ($nb_loc['basis'] === 'corridor'): ?>
    <p class="ai-nb-approx">Distances are measured from the centre of the <?= e($property['corridor_name'] ?? 'corridor') ?> corridor, because this project's exact location isn't set yet — treat them as approximate.</p>
  <?php elseif ($nb_loc['basis'] === 'lookup'): ?>
    <p class="ai-nb-approx">Distances are measured from the project's approximate location (looked up from its name and sector) — treat them as approximate.</p>
  <?php endif; ?>

  <?php if ($nb_askable): ?>
  <form class="ai-ask" data-nb-form>
    <label for="ai-nb-q" class="sr-only">Ask about distances from this project</label>
    <input type="text" id="ai-nb-q" maxlength="<?= AI_MAX_INPUT ?>" placeholder="e.g. How far is the airport?" autocomplete="off">
    <button type="submit" aria-label="Ask">→</button>
  </form>
  <div class="ai-nb-answer" data-nb-answer role="status" aria-live="polite" hidden></div>
  <?php endif; ?>

  <div class="ai-tabs" role="tablist" aria-label="Place category">
    <?php foreach ($nb_places as $key => $cat): ?>
      <button type="button" role="tab" class="ai-tab" id="ai-nb-tab-<?= e($key) ?>" aria-controls="ai-nb-panel-<?= e($key) ?>" aria-selected="<?= $key === $nb_first ? 'true' : 'false' ?>" tabindex="<?= $key === $nb_first ? '0' : '-1' ?>" data-nb-tab="<?= e($key) ?>"><?= e($cat['label']) ?></button>
    <?php endforeach; ?>
  </div>

  <?php foreach ($nb_places as $key => $cat): ?>
    <div class="ai-nb-panel" role="tabpanel" id="ai-nb-panel-<?= e($key) ?>" aria-labelledby="ai-nb-tab-<?= e($key) ?>" data-nb-panel="<?= e($key) ?>"<?= $key === $nb_first ? '' : ' hidden' ?>>
      <p class="ai-nb-intro"><strong>Nearby <?= e(strtolower($cat['label'])) ?></strong> — listed in our location dataset<?= $key === 'airport' ? '' : ' (within ' . (int)PLACE_RADIUS_KM . ' km)' ?>:</p>
      <ol class="ai-nb-list">
        <?php foreach ($cat['items'] as $n => $pl): ?>
          <li>
            <span class="ai-nb-num" aria-hidden="true"><?= $n + 1 ?></span>
            <span class="ai-nb-name"><?= e($pl['name']) ?></span>
            <span class="ai-nb-dist">
              <strong><?= e(number_format((float)$pl['km'], 1)) ?> km</strong> <span>straight-line</span>
              <span class="ai-source">Verified <?= e(format_verified_date($pl['verified_on'])) ?> · <?= e($pl['source']) ?></span>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  <?php endforeach; ?>

  <div class="ai-map" id="ai-nb-map" data-nb-map hidden>
    <div class="ai-map-canvas" data-nb-map-canvas role="region" aria-label="Map of nearby places. The same places are listed above."></div>
    <div class="ai-map-controls">
      <label for="ai-map-jump" class="sr-only">Show a place on the map</label>
      <select id="ai-map-jump" data-nb-jump>
        <option value="">Show a place on the map…</option>
        <option value="center"><?= e($property['name']) ?></option>
        <?php foreach ($nb_mapData['places'] as $i => $pl): ?><option value="<?= $i ?>"><?= e($pl['name']) ?> (<?= e(number_format((float)$pl['km'], 1)) ?> km)</option><?php endforeach; ?>
      </select>
    </div>
    <p class="ai-map-note">Map data © OpenStreetMap contributors.</p>
  </div>
  <script type="application/json" data-nb-data><?= json_encode($nb_mapData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

  <p class="ai-disclaimer">Distances are straight-line, not road distance — travel time can vary by route and traffic. We don't independently guarantee school rankings or commute times, and we don't answer subjective questions such as whether an area is "safe" or "good for families".</p>

  <div class="ai-actions">
    <button type="button" class="btn btn-soft btn-sm" data-nb-map-toggle aria-expanded="false" aria-controls="ai-nb-map">View on map</button>
    <button type="button" class="btn btn-soft btn-sm" data-nb-sources aria-expanded="false">See source details</button>
  </div>
  <div class="ai-nb-sources" data-nb-sources-panel hidden>
    Places come from OpenStreetMap and from our team. Each entry shows its source and the date it was last checked. Distances are calculated automatically from <?= $nb_loc['basis'] === 'corridor' ? 'the corridor centre' : ($nb_loc['basis'] === 'lookup' ? 'the project\'s approximate location' : 'the project\'s location') ?> as straight-line distance.
  </div>
</section>

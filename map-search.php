<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ai-filters.php';

$corridors = db()->query('SELECT id, name FROM corridors WHERE deleted_at IS NULL ORDER BY name')->fetchAll();

$filters = filters_from_array($_GET);
filters_remember($filters);
[$where, $params] = filters_where($filters);
$listStmt = db()->prepare('SELECT p.id, p.name, p.sector, p.price_display, p.category, p.lat, p.lng, c.lat AS corridor_lat, c.lng AS corridor_lng FROM properties p LEFT JOIN corridors c ON c.id = p.corridor_id' . $where . ' ORDER BY p.featured DESC, p.name');
$listStmt->execute($params);
$listed = $listStmt->fetchAll();
$total = count($listed);

$extra_head = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">';

$page_title = 'Map Search — Kutumb Realty';
$page_scripts = ['ai-finder'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain" style="padding-bottom:32px;">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / Map Search</div>
    <h1>Search Properties on the Map</h1>
    <p>Browse our Gurgaon listings geographically — filter by category or corridor and click a pin for details.</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="container">
    <?php $hf_variant = 'compact'; $hf_filters = $filters; $hf_total = $total; $hf_target_path = '/map-search.php'; $hf_title = 'Describe what you want to see on the map'; include __DIR__ . '/includes/ai/home-finder.php'; ?>
    <div class="map-filters reveal">
      <label for="map-filter-category" class="sr-only">Filter by category</label>
      <select id="map-filter-category">
        <option value="">All Categories</option>
        <?php foreach (FILTER_CATEGORIES as $val): ?>
          <option value="<?= e($val) ?>" <?= $filters['category'] === $val ? 'selected' : '' ?>><?= e(category_label($val)) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="map-filter-corridor" class="sr-only">Filter by corridor</label>
      <select id="map-filter-corridor">
        <option value="">All Corridors</option>
        <?php foreach ($corridors as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$filters['corridor_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="map-layout">
      <div id="map-search" class="map-wrap reveal" data-endpoint="/api/map-properties.php" role="region" aria-label="Map of the listings shown in the list"></div>
      <div class="map-list" aria-labelledby="map-list-title">
        <h2 class="map-list-title" id="map-list-title"><span data-map-count><?= $total ?></span> <span data-map-count-label>propert<?= $total === 1 ? 'y' : 'ies' ?></span></h2>
        <ol class="map-list-items" data-map-list>
          <?php foreach ($listed as $r): ?>
            <li>
              <a href="/property.php?id=<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a>
              <span><?= e($r['sector']) ?> · <?= e(category_label($r['category'])) ?> · <?= e($r['price_display']) ?></span>
              <?php if ($r['lat'] === null && $r['corridor_lat'] === null): ?><span class="map-list-nopin">Not on map yet</span>
              <?php elseif ($r['lat'] === null): ?><span class="map-list-approx">Approximate location (corridor)</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
        <div class="map-list-empty" data-map-empty<?= $listed ? ' hidden' : '' ?>>
          <p>No properties match these filters.</p>
          <p><a href="/map-search.php">Clear filters</a> &middot; <a href="/properties.php" data-map-list-link>Browse the list instead</a></p>
        </div>
      </div>
    </div>
    <p style="font-size:12.5px; color:var(--ink-soft); margin-top:12px;">Pins without an exact address use their corridor's approximate location. Every listing is also in the list, including ones without a location yet.</p>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="/assets/js/map-search.js?v=<?= filemtime(__DIR__ . '/assets/js/map-search.js') ?>"></script>

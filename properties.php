<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/structured-data.php';
require_once __DIR__ . '/includes/ai-filters.php';

$validCategories = FILTER_CATEGORIES;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

try {
    $filters = filters_from_array($_GET);
    $keyword = $filters['keyword'];
    $category = $filters['category'];
    $corridorId = $filters['corridor_id'];
    $priceMax = $filters['price_max'];
    [$where, $params] = filters_where($filters);
    filters_remember($filters);

    $countStmt = db()->prepare("SELECT COUNT(*) FROM properties p LEFT JOIN corridors c ON c.id = p.corridor_id$where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = db()->prepare("SELECT p.*, c.name AS corridor_name FROM properties p LEFT JOIN corridors c ON c.id = p.corridor_id$where ORDER BY p.featured DESC, p.id ASC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $properties = $stmt->fetchAll();

    $corridors = db()->query('SELECT id, name FROM corridors WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

$page_title = 'All Properties — Kutumb Realty';
$page_description = 'Browse the full curated Kutumb Realty portfolio across Gurgaon — filter by corridor, category, or budget.';
$structured_data = structured_data_breadcrumbs([
    ['name' => 'Home', 'url' => '/index.php'],
    ['name' => 'Properties', 'url' => '/properties.php'],
]);
$page_scripts = ['ai-finder'];
include __DIR__ . '/includes/header.php';

function qs_with(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / Properties</div>
    <h1>All Properties</h1>
    <p>The full curated Kutumb Realty portfolio — filter by corridor, category, or budget to narrow it down.</p>
  </div>
</section>

<section>
  <div class="container">
    <?php $filtersOn = filters_active($filters); ?>
    <div class="results-bar" role="status">
      <span class="results-count"><?= $total ?> propert<?= $total === 1 ? 'y' : 'ies' ?> <?= $filtersOn ? 'match your filters' : 'in the portfolio' ?></span>
      <?php if ($filtersOn): ?><a href="/properties.php" class="results-clear">Clear all filters</a><?php endif; ?>
    </div>
    <form method="get" class="search-fields" style="background:var(--white); border-radius:var(--radius-md); box-shadow:var(--shadow-card); margin-bottom:32px;">
      <?php foreach ($filters['sectors'] as $s): ?><input type="hidden" name="sector[]" value="<?= e($s) ?>"><?php endforeach; ?>
      <?php foreach ($filters['bhk'] as $b): ?><input type="hidden" name="bhk[]" value="<?= (int)$b ?>"><?php endforeach; ?>
      <?php foreach ($filters['prefs'] as $pr): ?><input type="hidden" name="pref[]" value="<?= e($pr) ?>"><?php endforeach; ?>
      <?php if ($filters['price_min']): ?><input type="hidden" name="price_min" value="<?= (int)$filters['price_min'] ?>"><?php endif; ?>
      <label for="pf-keyword" class="sr-only">Search by name, sector or developer</label>
      <input type="text" id="pf-keyword" name="keyword" value="<?= e($keyword) ?>" placeholder="Search by name, sector, developer...">
      <label for="pf-corridor" class="sr-only">Filter by corridor</label>
      <select id="pf-corridor" name="corridor_id">
        <option value="">Any Corridor</option>
        <?php foreach ($corridors as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $corridorId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="pf-category" class="sr-only">Filter by category</label>
      <select id="pf-category" name="category">
        <option value="">Any Category</option>
        <?php foreach ($validCategories as $val): ?>
          <option value="<?= e($val) ?>" <?= $category === $val ? 'selected' : '' ?>><?= e(category_label($val)) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="pf-price-max" class="sr-only">Maximum budget in rupees</label>
      <input type="number" id="pf-price-max" name="price_max" value="<?= e((string)($priceMax ?: '')) ?>" placeholder="Max Budget (₹)" min="0" step="100000">
      <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <?php
    // Conventional filters come first; the natural-language finder is an optional second way in.
    // It opens by itself when the current results came from it (sector/bhk/preference/min-price filters
    // only the finder can set), so its editable chips stay visible.
    $finderDriven = $filters['sectors'] || $filters['bhk'] || $filters['prefs'] || $filters['price_min'];
    ?>
    <details class="finder-toggle"<?= $finderDriven ? ' open' : '' ?>>
      <summary><span>Prefer to describe it in your own words?</span> <span class="finder-toggle-tag">AI finder</span></summary>
      <?php include __DIR__ . '/includes/ai/properties-finder.php'; ?>
    </details>

    <?php $rec_show_invite = false; include __DIR__ . '/includes/ai/recommend-panel.php'; ?>
    <?php if ($properties): ?>
      <div class="property-grid">
        <?php foreach ($properties as $i => $p): include __DIR__ . '/includes/property-card.php'; endforeach; ?>
      </div>
      <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:10px; margin-top:40px;">
          <?php if ($page > 1): ?><a href="<?= e(qs_with(['page' => $page - 1])) ?>" class="btn btn-soft btn-sm">← Previous</a><?php endif; ?>
          <span style="align-self:center; font-size:13px; color:var(--ink-soft);">Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?><a href="<?= e(qs_with(['page' => $page + 1])) ?>" class="btn btn-soft btn-sm">Next →</a><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-state">
        <p>No properties match those filters.</p>
        <p class="empty-state-actions">
          <?php if ($filtersOn): ?><a href="/properties.php" class="btn btn-navy btn-sm">Clear all filters</a><?php endif; ?>
          <a href="/contact.php" class="btn btn-soft btn-sm">Tell us what you're looking for</a>
        </p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

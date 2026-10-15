<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ai-recommend.php';

$idsParam = $_GET['ids'] ?? '';
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $idsParam)))));
$ids = array_slice($ids, 0, 4);

$properties = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT p.*, c.name AS corridor_name FROM properties p LEFT JOIN corridors c ON c.id = p.corridor_id WHERE p.id IN ($placeholders) AND p.deleted_at IS NULL");
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt->fetchAll() as $r) { $byId[$r['id']] = $r; }
    foreach ($ids as $id) { if (isset($byId[$id])) $properties[] = $byId[$id]; }
}

// "Fits your needs" row: reasons against the visitor's last search, if they made one.
$fitFilters = filters_remembered();
$fit = [];
foreach ($properties as $p) {
    $fit[$p['id']] = $fitFilters ? rec_reasons($p, $fitFilters) : [];
}

// Plain-text cells for the extra comparison rows (data is already loaded via p.*).
function compare_rera_text(array $p): string
{
    if (!empty($p['is_eoi'])) return 'Awaited (EOI only)';
    return !empty($p['rera_number']) ? $p['rera_number'] : 'Ask your advisor';
}
function compare_verified_text(array $p): string
{
    return !empty($p['info_verified_on']) ? date('j M Y', strtotime($p['info_verified_on'])) : 'Being verified';
}
$shortlistMsg = '';
if ($properties) {
    $shortlistMsg = 'Hi, I would like an advisor to review my shortlist: ' . implode('; ', array_map(
        fn($p) => $p['name'] . ' (' . $p['sector'] . ', ID ' . (int)$p['id'] . ')',
        $properties
    )) . '.';
}

$page_title = 'Compare Properties — Kutumb Realty';
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / Compare</div>
    <h1>Compare Properties</h1>
    <p>Side-by-side comparison of your shortlisted projects.</p>
  </div>
</section>

<section>
  <div class="container">
    <?php if (!$properties): ?>
      <p style="color:var(--ink-soft)">Nothing to compare yet — tap the <strong>+</strong> icon on any property card to add it here.</p>
      <a href="/properties.php" class="btn btn-primary">Browse Properties</a>
    <?php else: ?>
      <div class="compare-table-wrap reveal">
        <table class="compare-table">
          <thead>
            <tr>
              <th></th>
              <?php foreach ($properties as $p): ?>
                <th>
                  <img src="<?= property_card_image_src($p) ?>" alt="<?= e($p['name']) ?>">
                  <div><?= e($p['name']) ?></div>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <tr><td>Price</td><?php foreach ($properties as $p): ?><td><?= e($p['price_display']) ?></td><?php endforeach; ?></tr>
            <tr><td>Sector</td><?php foreach ($properties as $p): ?><td><?= e($p['sector']) ?></td><?php endforeach; ?></tr>
            <tr><td>Corridor</td><?php foreach ($properties as $p): ?><td><?= e($p['corridor_name'] ?? '') ?: '—' ?></td><?php endforeach; ?></tr>
            <tr><td>Category</td><?php foreach ($properties as $p): ?><td><?= e(category_label($p['category'])) ?></td><?php endforeach; ?></tr>
            <tr><td>Configuration</td><?php foreach ($properties as $p): ?><td><?= e($p['bhk_config']) ?></td><?php endforeach; ?></tr>
            <tr><td>Developer</td><?php foreach ($properties as $p): ?><td><?= e($p['developer']) ?></td><?php endforeach; ?></tr>
            <tr><td>Possession</td><?php foreach ($properties as $p): ?><td><?= e($p['possession_text'] ?? '') ?: '—' ?></td><?php endforeach; ?></tr>
            <tr><td>Rate</td><?php foreach ($properties as $p): ?><td><?= e($p['rate_text'] ?? '') ?: '—' ?></td><?php endforeach; ?></tr>
            <tr><td>RERA</td><?php foreach ($properties as $p): ?><td><?= e(compare_rera_text($p)) ?></td><?php endforeach; ?></tr>
            <tr><td>Details verified</td><?php foreach ($properties as $p): ?><td><?= e(compare_verified_text($p)) ?></td><?php endforeach; ?></tr>
            <tr class="compare-fit-row">
              <td>Fits your needs<?php if ($fitFilters): ?><span class="compare-fit-search"><?= e(filters_describe($fitFilters)) ?></span><?php endif; ?></td>
              <?php foreach ($properties as $p): $reasons = $fit[$p['id']]; $reasonsCompact = true; ?>
                <td>
                  <?php if ($reasons): ?>
                    <strong class="compare-fit-score"><?= rec_score($reasons) ?> of <?= count($reasons) ?> met</strong>
                    <?php include __DIR__ . '/includes/ai/reasons-list.php'; ?>
                  <?php else: ?>
                    <a href="/index.php#ai-finder-hero" class="compare-fit-invite">Tell us what you need</a>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr><td></td><?php foreach ($properties as $p): ?><td><a href="/property.php?id=<?= (int)$p['id'] ?>" class="btn btn-navy btn-sm">View Details</a></td><?php endforeach; ?></tr>
          </tbody>
        </table>
      </div>
      <div class="compare-cards reveal">
        <?php foreach ($properties as $p): ?>
          <div class="compare-card">
            <img src="<?= property_card_image_src($p) ?>" alt="<?= e($p['name']) ?>">
            <h3><?= e($p['name']) ?></h3>
            <dl>
              <div><dt>Price</dt><dd><?= e($p['price_display']) ?></dd></div>
              <div><dt>Sector</dt><dd><?= e($p['sector']) ?></dd></div>
              <div><dt>Corridor</dt><dd><?= e($p['corridor_name'] ?? '') ?: '—' ?></dd></div>
              <div><dt>Category</dt><dd><?= e(category_label($p['category'])) ?></dd></div>
              <div><dt>Configuration</dt><dd><?= e($p['bhk_config']) ?></dd></div>
              <div><dt>Developer</dt><dd><?= e($p['developer']) ?></dd></div>
              <div><dt>Possession</dt><dd><?= e($p['possession_text'] ?? '') ?: '—' ?></dd></div>
              <div><dt>Rate</dt><dd><?= e($p['rate_text'] ?? '') ?: '—' ?></dd></div>
              <div><dt>RERA</dt><dd><?= e(compare_rera_text($p)) ?></dd></div>
              <div><dt>Details verified</dt><dd><?= e(compare_verified_text($p)) ?></dd></div>
            </dl>
            <?php if ($fit[$p['id']]): $reasons = $fit[$p['id']]; $reasonsCompact = true; ?>
              <div class="compare-card-fit">
                <p class="ai-rec-why-title">Fits your needs — <?= rec_score($reasons) ?> of <?= count($reasons) ?> met</p>
                <?php include __DIR__ . '/includes/ai/reasons-list.php'; ?>
              </div>
            <?php endif; ?>
            <a href="/property.php?id=<?= (int)$p['id'] ?>" class="btn btn-navy btn-sm btn-block">View Details</a>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="compare-advisor reveal">
        <div>
          <h3>Not sure which one fits?</h3>
          <p>Send this shortlist to an advisor and they&rsquo;ll go through the trade-offs with you. The fit notes above are a guide, not a guarantee.</p>
        </div>
        <a href="<?= whatsapp_url($shortlistMsg) ?>" class="btn btn-primary" target="_blank" rel="noopener">Review this shortlist with an advisor</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

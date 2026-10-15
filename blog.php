<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/structured-data.php';

$validTypes = ['article', 'market-report', 'guide', 'case-study'];
$typeFilter = $_GET['type'] ?? '';
$corridorSlug = (string)($_GET['corridor'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;

/** Link to the listing with the current filters, overriding some params (empty values are dropped). */
function blog_url(array $overrides = []): string
{
    $params = array_filter(array_merge($_GET, ['page' => null], $overrides), fn($v) => $v !== '' && $v !== null);
    return '/blog.php' . ($params ? '?' . http_build_query($params) : '');
}

try {
    // Corridors that actually have published posts, for the topic filter.
    $postCorridors = db()->query("SELECT DISTINCT c.slug, c.name FROM blog_posts p JOIN corridors c ON c.id = p.corridor_id
            WHERE p.status = 'published' AND p.deleted_at IS NULL AND c.deleted_at IS NULL ORDER BY c.name")->fetchAll();
    if (!in_array($corridorSlug, array_column($postCorridors, 'slug'), true)) {
        $corridorSlug = '';
    }

    $from = "FROM blog_posts p
            LEFT JOIN corridors c ON c.id = p.corridor_id
            LEFT JOIN admin_users au ON au.id = p.author_id
            LEFT JOIN team_members tm ON tm.admin_user_id = p.author_id
            WHERE p.status = 'published' AND p.deleted_at IS NULL";
    $params = [];
    if (in_array($typeFilter, $validTypes, true)) {
        $from .= ' AND p.post_type = ?';
        $params[] = $typeFilter;
    } else {
        $typeFilter = '';
    }
    if ($corridorSlug !== '') {
        $from .= ' AND c.slug = ?';
        $params[] = $corridorSlug;
    }
    $countStmt = db()->prepare("SELECT COUNT(*) $from");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = db()->prepare("SELECT p.*, c.name AS corridor_name, au.full_name AS author_name, tm.slug AS author_slug, tm.photo_path AS author_photo $from ORDER BY p.published_at DESC, p.id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

$page_title = 'Insights & Market Reports — Kutumb Realty';
$page_description = 'Corridor guides, market reports and buying advice for Gurgaon real estate from the Kutumb Realty team.';
$structured_data = structured_data_breadcrumbs([
    ['name' => 'Home', 'url' => '/index.php'],
    ['name' => 'Insights', 'url' => '/blog.php'],
]);
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / Insights</div>
    <h1>Insights &amp; Market Reports</h1>
    <p>Corridor guides, market reports and buying advice from the Kutumb Realty team.</p>
  </div>
</section>

<section>
  <div class="container">
    <div class="search-tabs" style="margin-bottom:32px; background:var(--white); box-shadow:var(--shadow-card); display:inline-flex; border-radius:var(--radius-md);">
      <a href="<?= e(blog_url(['type' => ''])) ?>" class="search-tab <?= $typeFilter === '' ? 'active' : '' ?>">All</a>
      <a href="<?= e(blog_url(['type' => 'article'])) ?>" class="search-tab <?= $typeFilter === 'article' ? 'active' : '' ?>">Articles</a>
      <a href="<?= e(blog_url(['type' => 'market-report'])) ?>" class="search-tab <?= $typeFilter === 'market-report' ? 'active' : '' ?>">Market Reports</a>
      <a href="<?= e(blog_url(['type' => 'guide'])) ?>" class="search-tab <?= $typeFilter === 'guide' ? 'active' : '' ?>">Guides</a>
      <a href="<?= e(blog_url(['type' => 'case-study'])) ?>" class="search-tab <?= $typeFilter === 'case-study' ? 'active' : '' ?>">Case Studies</a>
    </div>
    <?php if (!empty($postCorridors)): ?>
    <div class="topic-filter" aria-label="Filter by corridor">
      <span class="topic-filter-label">Corridor:</span>
      <a href="<?= e(blog_url(['corridor' => ''])) ?>" class="topic-chip <?= $corridorSlug === '' ? 'active' : '' ?>">All</a>
      <?php foreach ($postCorridors as $pc): ?>
        <a href="<?= e(blog_url(['corridor' => $pc['slug']])) ?>" class="topic-chip <?= $corridorSlug === $pc['slug'] ? 'active' : '' ?>"><?= e($pc['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($posts): ?>
      <div class="property-grid">
        <?php foreach ($posts as $i => $post): include __DIR__ . '/includes/blog-card.php'; endforeach; ?>
      </div>
      <?php if ($totalPages > 1): ?>
        <nav class="pager" aria-label="Pages">
          <?php if ($page > 1): ?><a href="<?= e(blog_url(['page' => $page - 1])) ?>" class="btn btn-soft btn-sm" rel="prev">&larr; Previous</a><?php endif; ?>
          <span class="pager-info">Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?><a href="<?= e(blog_url(['page' => $page + 1])) ?>" class="btn btn-soft btn-sm" rel="next">Next &rarr;</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php elseif ($typeFilter !== '' || $corridorSlug !== ''): ?>
      <div class="empty-state"><p>No posts match those filters.</p><p class="empty-state-actions"><a href="/blog.php" class="btn btn-navy btn-sm">Show all insights</a></p></div>
    <?php else: ?>
      <p style="color:var(--ink-soft)">No posts yet — check back soon.</p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

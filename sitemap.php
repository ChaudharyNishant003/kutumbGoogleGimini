<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/xml; charset=utf-8');

// Regenerated from live queries at most once every 15 minutes instead of on
// every single request — content here (listings/posts/corridors/team) rarely
// changes minute to minute. Cached to the system temp dir since it's always
// writable regardless of the app directory's runtime permissions.
$cacheFile = sys_get_temp_dir() . '/kutumb-sitemap-cache.xml';
$cacheTtlSeconds = 900;
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtlSeconds) {
    readfile($cacheFile);
    exit;
}

ob_start();

$urls = [];
$staticPages = ['/index.php', '/properties.php', '/about.php', '/why-an-advisor.php', '/contact.php', '/blog.php', '/team.php', '/emi-calculator.php', '/map-search.php', '/privacy-policy.php', '/terms.php'];
foreach ($staticPages as $page) {
    $urls[] = ['loc' => SITE_URL . $page, 'changefreq' => 'weekly', 'priority' => $page === '/index.php' ? '1.0' : '0.7'];
}

try {
$properties = db()->query('SELECT id, created_at FROM properties WHERE deleted_at IS NULL')->fetchAll();
foreach ($properties as $p) {
    $urls[] = ['loc' => SITE_URL . '/property.php?id=' . $p['id'], 'lastmod' => date('Y-m-d', strtotime($p['created_at'])), 'changefreq' => 'weekly', 'priority' => '0.8'];
}

$corridors = db()->query('SELECT slug FROM corridors WHERE deleted_at IS NULL')->fetchAll();
foreach ($corridors as $c) {
    $urls[] = ['loc' => SITE_URL . '/sector.php?slug=' . $c['slug'], 'changefreq' => 'weekly', 'priority' => '0.7'];
}

$posts = db()->query("SELECT slug, published_at FROM blog_posts WHERE status = 'published' AND deleted_at IS NULL")->fetchAll();
foreach ($posts as $post) {
    $urls[] = ['loc' => SITE_URL . '/blog-post.php?slug=' . $post['slug'], 'lastmod' => date('Y-m-d', strtotime($post['published_at'])), 'changefreq' => 'monthly', 'priority' => '0.6'];
}

$team = db()->query('SELECT slug FROM team_members WHERE active = 1 AND deleted_at IS NULL')->fetchAll();
foreach ($team as $t) {
    $urls[] = ['loc' => SITE_URL . '/agent.php?slug=' . $t['slug'], 'changefreq' => 'monthly', 'priority' => '0.5'];
}

} catch (Throwable $e) {
    // Don't emit (or cache) a half-built sitemap when the DB is unreachable.
    ob_end_clean();
    http_response_code(503);
    header('Retry-After: 300');
    exit;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
    if (!empty($u['lastmod'])) echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
    if (!empty($u['changefreq'])) echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    if (!empty($u['priority'])) echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>' . "\n";

$output = ob_get_clean();
echo $output;
@file_put_contents($cacheFile, $output);

<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/structured-data.php';
require_once __DIR__ . '/site-stats.php';
$page_title = $page_title ?? SITE_NAME . ' — Trusted Property Consultants in Gurgaon';
$page_description = $page_description ?? "Kutumb Realty — curated residential and commercial property advisory across Gurgaon's main corridors.";
// Canonical = path plus only the params that identify distinct content. Tracking
// params (utm_*, gclid ...) and search filters must not create their own canonicals.
if (!isset($canonical_url)) {
    $canonicalKeep = ['id', 'slug', 'type', 'corridor', 'page'];
    $canonicalQuery = array_intersect_key($_GET, array_flip($canonicalKeep));
    if (isset($canonicalQuery['page']) && (int)$canonicalQuery['page'] <= 1) {
        unset($canonicalQuery['page']);
    }
    $canonicalPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $canonical_url = SITE_URL . $canonicalPath . ($canonicalQuery ? '?' . http_build_query($canonicalQuery) : '');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="view-transition" content="same-origin">
<title><?= e($page_title) ?></title>
<meta name="description" content="<?= e($page_description) ?>">
<link rel="canonical" href="<?= e($canonical_url) ?>">
<meta property="og:title" content="<?= e($page_title) ?>">
<meta property="og:description" content="<?= e($page_description) ?>">
<meta property="og:url" content="<?= e($canonical_url) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="/assets/css/ai.css?v=<?= filemtime(__DIR__ . '/../assets/css/ai.css') ?>">
<noscript><style>.reveal, .reveal-stagger > * { opacity: 1 !important; }</style></noscript>
<?= $extra_head ?? '' ?>
<?= structured_data_local_business() ?>
<?= $structured_data ?? '' ?>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>

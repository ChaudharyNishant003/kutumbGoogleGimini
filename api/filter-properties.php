<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$validCategories = ['new-launch', 'under-construction', 'ready-to-move', 'commercial'];
$category = $_GET['category'] ?? 'all';

if ($category === 'all') {
    $properties = db()->query('SELECT * FROM properties WHERE deleted_at IS NULL ORDER BY featured DESC, id ASC')->fetchAll();
} elseif (in_array($category, $validCategories, true)) {
    $stmt = db()->prepare('SELECT * FROM properties WHERE category = ? AND deleted_at IS NULL ORDER BY featured DESC, id ASC');
    $stmt->execute([$category]);
    $properties = $stmt->fetchAll();
} else {
    http_response_code(400);
    exit;
}

if (!$properties) {
    echo '<p style="grid-column:1/-1; color:var(--ink-soft); padding:24px 0;">No properties in this category yet — check back soon.</p>';
    exit;
}

foreach ($properties as $i => $p) {
    include __DIR__ . '/../includes/property-card.php';
}

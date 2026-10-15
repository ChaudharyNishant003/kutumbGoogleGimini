<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai-filters.php';

header('Content-Type: application/json');

// Same allowlisted filters as properties.php (category, corridor, plus the Home
// Finder's sector[] / bhk[] / price range), so a search looks the same on the map.
[$where, $params] = filters_where(filters_from_array($_GET));

$stmt = db()->prepare(
    'SELECT p.id, p.name, p.sector, p.price_display, p.category, p.lat, p.lng, c.lat AS corridor_lat, c.lng AS corridor_lng
     FROM properties p LEFT JOIN corridors c ON c.id = p.corridor_id' . $where . ' ORDER BY p.featured DESC, p.name'
);
$stmt->execute($params);

$points = [];
$unmapped = [];
foreach ($stmt->fetchAll() as $r) {
    // Prefer an exact property location; fall back to the corridor centroid so pins
    // still show up before every listing has been individually geotagged.
    $lat = $r['lat'] !== null ? (float)$r['lat'] : ($r['corridor_lat'] !== null ? (float)$r['corridor_lat'] : null);
    $lng = $r['lng'] !== null ? (float)$r['lng'] : ($r['corridor_lng'] !== null ? (float)$r['corridor_lng'] : null);
    $item = [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'sector' => $r['sector'],
        'price' => $r['price_display'],
        'category' => category_label($r['category']),
        'url' => '/property.php?id=' . (int)$r['id'],
    ];
    if ($lat === null || $lng === null) {
        $unmapped[] = $item;
        continue;
    }
    $points[] = $item + ['lat' => $lat, 'lng' => $lng, 'exact' => $r['lat'] !== null];
}

// Old clients expect a bare array of points; ?with_list=1 also returns listings without a location.
echo json_encode(!empty($_GET['with_list']) ? ['points' => $points, 'unmapped' => $unmapped] : $points);

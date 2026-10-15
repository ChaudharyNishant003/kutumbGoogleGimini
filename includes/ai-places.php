<?php
/**
 * Neighbourhood data (Feature 4). Distances are always computed here from the
 * admin-maintained places table — the model never produces a distance.
 */
require_once __DIR__ . '/db.php';

const PLACE_RADIUS_KM = 10.0;

function place_categories(): array
{
    return ['school' => 'Schools', 'hospital' => 'Hospitals', 'metro' => 'Metro', 'airport' => 'Airport', 'mall' => 'Malls', 'office' => 'Offices'];
}

function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 6371.0 * 2 * asin(min(1.0, sqrt($a)));
}

/**
 * Where to measure from: the property's own pin, else its corridor's centre
 * (flagged approximate so the page can say so), else null.
 * Expects lat/lng and corridor_lat/corridor_lng keys on $property.
 */
function property_location(array $property): ?array
{
    if (isset($property['lat'], $property['lng']) && $property['lat'] !== null && $property['lng'] !== null) {
        // 'auto' = looked up from the project name/sector, not pinned by an admin.
        $auto = ($property['location_source'] ?? null) === 'auto';
        return ['lat' => (float)$property['lat'], 'lng' => (float)$property['lng'], 'approximate' => $auto, 'basis' => $auto ? 'lookup' : 'exact'];
    }
    if (isset($property['corridor_lat'], $property['corridor_lng']) && $property['corridor_lat'] !== null && $property['corridor_lng'] !== null) {
        return ['lat' => (float)$property['corridor_lat'], 'lng' => (float)$property['corridor_lng'], 'approximate' => true, 'basis' => 'corridor'];
    }
    return null;
}

/** Nearest places of one category, closest first. $radiusKm null = no distance cap. */
function get_nearby_places(float $lat, float $lng, string $category, ?float $radiusKm = PLACE_RADIUS_KM, int $limit = 5): array
{
    if (!isset(place_categories()[$category])) {
        return [];
    }
    $distanceSql = '(6371 * 2 * ASIN(LEAST(1, SQRT(POWER(SIN(RADIANS(lat - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS(lat)) * POWER(SIN(RADIANS(lng - ?) / 2), 2)))))';
    $sql = "SELECT id, name, category, lat, lng, source, verified_on, {$distanceSql} AS km
            FROM places WHERE deleted_at IS NULL AND category = ?";
    $params = [$lat, $lat, $lng, $category];
    if ($radiusKm !== null) {
        $sql .= " HAVING km <= ?";
        $params[] = $radiusKm;
    }
    $sql .= ' ORDER BY km ASC LIMIT ' . max(1, min(20, $limit));
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return array_map(function ($r) {
            $r['km'] = round((float)$r['km'], 1);
            return $r;
        }, $stmt->fetchAll());
    } catch (Throwable $e) {
        error_log('Nearby places query failed: ' . $e->getMessage());
        return [];
    }
}

/** Every category that has at least one place near this point, in display order. */
function nearby_by_category(float $lat, float $lng): array
{
    $out = [];
    foreach (place_categories() as $key => $label) {
        $items = $key === 'airport'
            ? get_nearby_places($lat, $lng, $key, null, 1)
            : get_nearby_places($lat, $lng, $key);
        if ($items) {
            $out[$key] = ['label' => $label, 'items' => $items];
        }
    }
    return $out;
}

function format_verified_date(?string $date): string
{
    return $date ? date('j M Y', strtotime($date)) : '';
}

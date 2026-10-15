<?php
/**
 * Nearby places filled in automatically from OpenStreetMap (Nominatim search API, free, no key).
 * - A project without a map position gets one looked up from its name + sector (marked approximate).
 * - Schools, hospitals, malls, metro stations and office hubs within PLACES_AUTO_RADIUS_KM are saved
 *   to `places` with source "OpenStreetMap"; osm_ref stops duplicates on refresh.
 * - Manual places (osm_ref NULL) are never changed, and a place an admin hid (deleted_at set) is
 *   never brought back by a refresh.
 * Nominatim's usage policy allows about one request per second with an identifying User-Agent,
 * so this only runs on admin actions, never on public page views.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ai-places.php';

const PLACES_AUTO_RADIUS_KM = 5.0;
const PLACES_AUTO_PER_CATEGORY = 5;
const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
// Rough box around Gurugram; a lookup outside it is a wrong match.
const GURUGRAM_BOUNDS = ['lat_min' => 28.25, 'lat_max' => 28.65, 'lng_min' => 76.70, 'lng_max' => 77.25];

/** One Nominatim search, throttled to ~1 request/second. Returns the decoded result list (empty on failure). */
function osm_search(array $params): array
{
    static $last = 0.0;
    $wait = 1.1 - (microtime(true) - $last);
    if ($wait > 0) usleep((int)($wait * 1e6));
    $last = microtime(true);

    $ch = curl_init(NOMINATIM_URL . '?' . http_build_query($params + ['format' => 'jsonv2', 'countrycodes' => 'in']));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'KutumbRealty/1.0 (' . (defined('SITE_EMAIL') ? SITE_EMAIL : 'info@kutumbrealty.com') . ')',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code !== 200) {
        error_log('OpenStreetMap search failed: HTTP ' . $code);
        return [];
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

function osm_in_gurugram(float $lat, float $lng): bool
{
    $b = GURUGRAM_BOUNDS;
    return $lat >= $b['lat_min'] && $lat <= $b['lat_max'] && $lng >= $b['lng_min'] && $lng <= $b['lng_max'];
}

/** Looks up a project's position from its name and sector. Returns [lat, lng] or null. */
function osm_geocode_property(array $property): ?array
{
    $queries = [];
    if (!empty($property['name'])) $queries[] = $property['name'] . ', Gurugram, Haryana';
    if (!empty($property['sector'])) $queries[] = $property['sector'] . ', Gurugram, Haryana';
    foreach ($queries as $q) {
        foreach (osm_search(['q' => $q, 'limit' => 3]) as $r) {
            $lat = (float)$r['lat'];
            $lng = (float)$r['lon'];
            if (osm_in_gurugram($lat, $lng)) return [$lat, $lng];
        }
    }
    return null;
}

/** Nominatim searches per category. Metro = railway stations tagged as subway / metro (not Indian Railways). */
function osm_category_searches(): array
{
    return [
        'school' => [['amenity' => 'school']],
        'hospital' => [['amenity' => 'hospital']],
        'mall' => [['amenity' => 'mall']],
        'metro' => [['amenity' => 'station', 'extratags' => 1]],
        'office' => [['q' => 'business park'], ['q' => 'IT park'], ['q' => 'corporate park']],
    ];
}

function osm_is_metro(array $r): bool
{
    $t = $r['extratags'] ?? [];
    return ($t['station'] ?? '') === 'subway' || ($t['subway'] ?? '') === 'yes' || ($t['light_rail'] ?? '') === 'yes'
        || stripos((string)($t['network'] ?? ''), 'metro') !== false;
}

/** Places around a point, nearest first per category: [['name','category','lat','lng','osm_ref','km'], …]. */
function osm_places_near(float $lat, float $lng): array
{
    $dLat = PLACES_AUTO_RADIUS_KM / 111.0;
    $dLng = PLACES_AUTO_RADIUS_KM / (111.0 * cos(deg2rad($lat)));
    $viewbox = implode(',', [$lng - $dLng, $lat + $dLat, $lng + $dLng, $lat - $dLat]);
    $out = [];
    foreach (osm_category_searches() as $category => $searches) {
        $found = [];
        foreach ($searches as $params) {
            foreach (osm_search($params + ['viewbox' => $viewbox, 'bounded' => 1, 'limit' => 40]) as $r) {
                if ($category === 'metro' && !osm_is_metro($r)) continue;
                $name = trim((string)($r['name'] ?? ''));
                if ($name === '' || empty($r['osm_type']) || empty($r['osm_id'])) continue;
                if ($category === 'metro') {
                    $name = trim(preg_replace('/\s*gate\s*#?\s*\d+.*$/i', '', $name));
                    if (!preg_match('/metro|station/i', $name)) $name .= ' Metro Station';
                }
                $pLat = (float)$r['lat'];
                $pLng = (float)$r['lon'];
                $km = haversine_km($lat, $lng, $pLat, $pLng);
                if ($km > PLACES_AUTO_RADIUS_KM) continue;
                $key = mb_strtolower($name);
                if (isset($found[$key]) && $found[$key]['km'] <= $km) continue; // same place mapped twice: keep the nearer one
                $found[$key] = ['name' => mb_substr($name, 0, 160), 'category' => $category, 'lat' => $pLat, 'lng' => $pLng,
                                'osm_ref' => substr($r['osm_type'], 0, 1) . $r['osm_id'], 'km' => $km];
            }
        }
        usort($found, fn($a, $b) => $a['km'] <=> $b['km']);
        $out = array_merge($out, array_slice(array_values($found), 0, PLACES_AUTO_PER_CATEGORY));
    }
    return $out;
}

/** Indira Gandhi International Airport is the one airport that matters for every project. */
function places_ensure_airport(): void
{
    db()->prepare("INSERT IGNORE INTO places (name, category, lat, lng, source, osm_ref, verified_on) VALUES (?, 'airport', ?, ?, 'OpenStreetMap', 'fixed:igi', CURDATE())")
        ->execute(['Indira Gandhi International Airport (IGI)', 28.5562, 77.1000]);
}

/** Saves places around a point. Returns ['added' => n, 'updated' => n, 'kept_hidden' => n]. */
function places_autofill_for_location(float $lat, float $lng): array
{
    places_ensure_airport();
    $counts = ['added' => 0, 'updated' => 0, 'kept_hidden' => 0];
    $find = db()->prepare('SELECT id, deleted_at FROM places WHERE osm_ref = ?');
    foreach (osm_places_near($lat, $lng) as $p) {
        $find->execute([$p['osm_ref']]);
        $row = $find->fetch();
        if ($row && $row['deleted_at'] !== null) {
            $counts['kept_hidden']++;
        } elseif ($row) {
            db()->prepare('UPDATE places SET name = ?, category = ?, lat = ?, lng = ?, verified_on = CURDATE() WHERE id = ?')
                ->execute([$p['name'], $p['category'], $p['lat'], $p['lng'], $row['id']]);
            $counts['updated']++;
        } else {
            db()->prepare("INSERT INTO places (name, category, lat, lng, source, osm_ref, verified_on) VALUES (?, ?, ?, ?, 'OpenStreetMap', ?, CURDATE())")
                ->execute([$p['name'], $p['category'], $p['lat'], $p['lng'], $p['osm_ref']]);
            $counts['added']++;
        }
    }
    return $counts;
}

/**
 * Fills in one project: looks up its position first when it has none (saved as approximate),
 * then saves the places around it. Returns a summary for the admin screen.
 */
function places_autofill_property(int $propertyId): array
{
    $stmt = db()->prepare('SELECT id, name, sector, lat, lng, location_source FROM properties WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$propertyId]);
    $p = $stmt->fetch();
    if (!$p) return ['ok' => false, 'message' => 'Project not found.'];

    $located = false;
    if ($p['lat'] === null || $p['lng'] === null) {
        $pos = osm_geocode_property($p);
        if (!$pos) return ['ok' => false, 'name' => $p['name'], 'message' => 'Location not found on OpenStreetMap — paste a Google Maps link in the project form.'];
        db()->prepare("UPDATE properties SET lat = ?, lng = ?, location_source = 'auto' WHERE id = ?")->execute([$pos[0], $pos[1], $p['id']]);
        [$p['lat'], $p['lng']] = $pos;
        $located = true;
    }
    $counts = places_autofill_for_location((float)$p['lat'], (float)$p['lng']);
    return ['ok' => true, 'name' => $p['name'], 'located' => $located] + $counts
        + ['message' => ($located ? 'Location found (approximate). ' : '') . $counts['added'] . ' new place(s), ' . $counts['updated'] . ' refreshed.'];
}

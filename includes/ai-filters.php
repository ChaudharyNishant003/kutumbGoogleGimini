<?php
/**
 * One definition of the property search filters, shared by properties.php,
 * the AI Home Finder (which only ever produces values from this allowlist),
 * the map search and the recommendation reasons.
 * Within a field values are OR'd; across fields they're AND'd.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

const FILTER_CATEGORIES = ['new-launch', 'under-construction', 'ready-to-move', 'commercial'];
const FILTER_BHK = [1, 2, 3, 4, 5];
const FILTER_PREFS = [
    'near_school'   => ['label' => 'Near schools',   'category' => 'school',   'km' => 3.0],
    'near_metro'    => ['label' => 'Near metro',     'category' => 'metro',    'km' => 1.5],
    'near_hospital' => ['label' => 'Near hospitals', 'category' => 'hospital', 'km' => 5.0],
    'near_office'   => ['label' => 'Near offices',   'category' => 'office',   'km' => 5.0],
];

/** Real sectors and corridors currently in the catalogue — the allowlist the AI output is checked against. */
function filter_catalog(): array
{
    static $catalog = null;
    if ($catalog === null) {
        $sectors = db()->query("SELECT DISTINCT sector FROM properties WHERE deleted_at IS NULL AND sector <> '' ORDER BY sector")->fetchAll(PDO::FETCH_COLUMN);
        $corridors = db()->query('SELECT id, name FROM corridors WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
        $catalog = ['sectors' => $sectors, 'corridors' => $corridors];
    }
    return $catalog;
}

function filter_empty(): array
{
    return ['keyword' => '', 'category' => '', 'corridor_id' => null, 'price_min' => null, 'price_max' => null, 'sectors' => [], 'bhk' => [], 'prefs' => []];
}

/** Validates raw request values (GET or AI output) into a clean filter set; unknown values are dropped, never guessed. */
function filters_from_array(array $in): array
{
    $f = filter_empty();
    $catalog = filter_catalog();

    $keyword = trim((string)($in['keyword'] ?? ''));
    $f['keyword'] = mb_substr($keyword, 0, 100);

    $category = (string)($in['category'] ?? '');
    $f['category'] = in_array($category, FILTER_CATEGORIES, true) ? $category : '';

    $corridorId = (int)($in['corridor_id'] ?? 0);
    foreach ($catalog['corridors'] as $c) {
        if ((int)$c['id'] === $corridorId) $f['corridor_id'] = $corridorId;
    }

    foreach (['price_min', 'price_max'] as $k) {
        $v = $in[$k] ?? null;
        if ($v !== null && $v !== '' && is_numeric($v) && (float)$v > 0 && (float)$v < 1e11) {
            $f[$k] = (float)$v;
        }
    }
    if ($f['price_min'] !== null && $f['price_max'] !== null && $f['price_min'] > $f['price_max']) {
        [$f['price_min'], $f['price_max']] = [$f['price_max'], $f['price_min']];
    }

    $sectorMap = [];
    foreach ($catalog['sectors'] as $s) $sectorMap[mb_strtolower($s)] = $s;
    foreach ((array)($in['sector'] ?? []) as $s) {
        $key = mb_strtolower(trim((string)$s));
        if (isset($sectorMap[$key]) && !in_array($sectorMap[$key], $f['sectors'], true)) $f['sectors'][] = $sectorMap[$key];
    }

    foreach ((array)($in['bhk'] ?? []) as $b) {
        $b = (int)$b;
        if (in_array($b, FILTER_BHK, true) && !in_array($b, $f['bhk'], true)) $f['bhk'][] = $b;
    }
    sort($f['bhk']);

    foreach ((array)($in['pref'] ?? []) as $p) {
        if (isset(FILTER_PREFS[$p]) && !in_array($p, $f['prefs'], true)) $f['prefs'][] = $p;
    }
    return $f;
}

function filters_active(array $f): bool
{
    return $f['keyword'] !== '' || $f['category'] !== '' || $f['corridor_id'] || $f['price_min'] || $f['price_max'] || $f['sectors'] || $f['bhk'] || $f['prefs'];
}

/**
 * Regex (MariaDB and PCRE compatible) that matches "3" in "3 BHK", "3BHK", "3/4 BHK",
 * "2, 3 & 4 BHK" — but not "3.5 BHK", "13 BHK", or a 3 inside "(~2,125–3,000 sq ft)":
 * the number must be followed, before any "(", by "BHK".
 */
function bhk_regex(int $n): string
{
    return '(^|[^0-9.])' . $n . '([^0-9.(][^(]*)?[Bb][Hh][Kk]';
}

/** WHERE clause (for alias p) and params for the hard filters. Preferences never filter — they only rank. */
function filters_where(array $f): array
{
    $where = ' WHERE p.deleted_at IS NULL';
    $params = [];
    if ($f['keyword'] !== '') {
        $where .= ' AND (p.name LIKE ? OR p.sector LIKE ? OR p.developer LIKE ?)';
        $like = '%' . $f['keyword'] . '%';
        array_push($params, $like, $like, $like);
    }
    if ($f['category'] !== '') {
        $where .= ' AND p.category = ?';
        $params[] = $f['category'];
    }
    if ($f['corridor_id']) {
        $where .= ' AND p.corridor_id = ?';
        $params[] = $f['corridor_id'];
    }
    if ($f['price_max']) {
        // "Price on Request" listings store 0 — they'd otherwise match every budget.
        $where .= ' AND p.price_min > 0 AND p.price_min <= ?';
        $params[] = $f['price_max'];
    }
    if ($f['price_min']) {
        $where .= ' AND p.price_min >= ?';
        $params[] = $f['price_min'];
    }
    if ($f['sectors']) {
        $where .= ' AND p.sector IN (' . implode(',', array_fill(0, count($f['sectors']), '?')) . ')';
        array_push($params, ...$f['sectors']);
    }
    if ($f['bhk']) {
        // Most listings only say "Multiple configurations" — they may well have the size asked
        // for, so they stay in (their reasons say "configuration not listed"). Commercial
        // listings never match a BHK request.
        $where .= " AND p.category <> 'commercial' AND ((" . implode(' OR ', array_fill(0, count($f['bhk']), 'p.bhk_config REGEXP ?'))
            . ") OR p.bhk_config NOT LIKE '%BHK%')";
        foreach ($f['bhk'] as $b) $params[] = bhk_regex($b);
    }
    return [$where, $params];
}

function filters_count(array $f): int
{
    [$where, $params] = filters_where($f);
    $stmt = db()->prepare('SELECT COUNT(*) FROM properties p' . $where);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/** Query-string parameters for properties.php (and map/other pages) from a filter set. */
function filters_to_query(array $f): array
{
    $q = [];
    if ($f['keyword'] !== '') $q['keyword'] = $f['keyword'];
    if ($f['category'] !== '') $q['category'] = $f['category'];
    if ($f['corridor_id']) $q['corridor_id'] = $f['corridor_id'];
    if ($f['price_min']) $q['price_min'] = (int)$f['price_min'];
    if ($f['price_max']) $q['price_max'] = (int)$f['price_max'];
    if ($f['sectors']) $q['sector'] = $f['sectors'];
    if ($f['bhk']) $q['bhk'] = $f['bhk'];
    if ($f['prefs']) $q['pref'] = $f['prefs'];
    return $q;
}

function filters_url(array $f, string $path = '/properties.php'): string
{
    $q = filters_to_query($f);
    return $path . ($q ? '?' . http_build_query($q) : '');
}

function format_inr_short(float $inr): string
{
    if ($inr >= 10000000) {
        return '₹' . rtrim(rtrim(number_format($inr / 10000000, 2), '0'), '.') . ' Cr';
    }
    return '₹' . rtrim(rtrim(number_format($inr / 100000, 2), '0'), '.') . ' L';
}

function budget_label(?float $min, ?float $max): string
{
    if ($min && $max) return format_inr_short($min) . ' – ' . format_inr_short($max);
    if ($max) return 'Up to ' . format_inr_short($max);
    if ($min) return 'From ' . format_inr_short($min);
    return '';
}

/**
 * Chips describing a filter set. Each chip carries the filter set with that
 * one value removed, so removing a chip is a plain link (works without JS).
 */
function filters_chips(array $f): array
{
    $chips = [];
    $catalog = filter_catalog();
    $without = function (callable $mutate) use ($f) {
        $copy = $f;
        $mutate($copy);
        return $copy;
    };
    foreach ($f['sectors'] as $s) {
        $chips[] = ['label' => $s, 'type' => 'req', 'key' => 'sector', 'value' => $s, 'filters' => $without(function (&$c) use ($s) { $c['sectors'] = array_values(array_diff($c['sectors'], [$s])); })];
    }
    if ($f['corridor_id']) {
        foreach ($catalog['corridors'] as $c) {
            if ((int)$c['id'] === (int)$f['corridor_id']) {
                $chips[] = ['label' => $c['name'], 'type' => 'req', 'key' => 'corridor_id', 'value' => (string)$c['id'], 'filters' => $without(function (&$x) { $x['corridor_id'] = null; })];
            }
        }
    }
    if ($f['keyword'] !== '') {
        $chips[] = ['label' => '"' . $f['keyword'] . '"', 'type' => 'req', 'key' => 'keyword', 'value' => $f['keyword'], 'filters' => $without(function (&$c) { $c['keyword'] = ''; })];
    }
    foreach ($f['bhk'] as $b) {
        $chips[] = ['label' => $b . ' BHK', 'type' => 'req', 'key' => 'bhk', 'value' => (string)$b, 'filters' => $without(function (&$c) use ($b) { $c['bhk'] = array_values(array_diff($c['bhk'], [$b])); })];
    }
    if ($f['price_min'] || $f['price_max']) {
        $chips[] = ['label' => budget_label($f['price_min'], $f['price_max']), 'type' => 'req', 'key' => 'budget', 'value' => '', 'filters' => $without(function (&$c) { $c['price_min'] = null; $c['price_max'] = null; })];
    }
    if ($f['category'] !== '') {
        $chips[] = ['label' => category_label($f['category']), 'type' => 'req', 'key' => 'category', 'value' => $f['category'], 'filters' => $without(function (&$c) { $c['category'] = ''; })];
    }
    foreach ($f['prefs'] as $p) {
        $chips[] = ['label' => FILTER_PREFS[$p]['label'], 'type' => 'pref', 'key' => 'pref', 'value' => $p, 'filters' => $without(function (&$c) use ($p) { $c['prefs'] = array_values(array_diff($c['prefs'], [$p])); })];
    }
    return $chips;
}

/** Remembers the visitor's last active search so Compare/Agent/Sector can explain fit. */
function filters_remember(array $f): void
{
    if (filters_active($f) && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['ai_filters'] = filters_to_query($f);
    }
}

function filters_remembered(): ?array
{
    $q = $_SESSION['ai_filters'] ?? null;
    if (!is_array($q)) return null;
    $f = filters_from_array($q);
    return filters_active($f) ? $f : null;
}

/** Human description of a filter set, e.g. "3 BHK · Sector 65 · Up to ₹2.2 Cr". */
function filters_describe(array $f): string
{
    $parts = array_map(fn($c) => $c['label'], filters_chips($f));
    return $parts ? implode(' · ', $parts) : 'All properties';
}

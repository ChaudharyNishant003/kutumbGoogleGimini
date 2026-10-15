<?php
/**
 * "Recommended because" (Feature 1). Deterministic scoring against the
 * visitor's own filters — no LLM, no behavioural tracking. Every reason is
 * computed here; nothing is inferred.
 */
require_once __DIR__ . '/ai-filters.php';
require_once __DIR__ . '/ai-places.php';

/** Matched / not-matched reasons for one listing. Only filters the visitor actually set produce a reason. */
function rec_reasons(array $p, array $f): array
{
    $reasons = [];
    $ok = fn(string $t) => ['ok' => true, 'text' => $t];
    $miss = fn(string $t) => ['ok' => false, 'text' => $t];

    if ($f['sectors']) {
        $reasons[] = in_array($p['sector'] ?? '', $f['sectors'], true)
            ? $ok('Matches your selected sector (' . $p['sector'] . ')')
            : $miss('Not matched: ' . implode(' / ', $f['sectors']) . ' (this is ' . ($p['sector'] ?? '') . ')');
    }
    if ($f['corridor_id']) {
        $reasons[] = (int)($p['corridor_id'] ?? 0) === (int)$f['corridor_id']
            ? $ok('On your chosen corridor' . (!empty($p['corridor_name']) ? ' (' . $p['corridor_name'] . ')' : ''))
            : $miss('Not matched: a different corridor' . (!empty($p['corridor_name']) ? ' (' . $p['corridor_name'] . ')' : ''));
    }
    if ($f['keyword'] !== '') {
        $hay = ($p['name'] ?? '') . ' ' . ($p['sector'] ?? '') . ' ' . ($p['developer'] ?? '');
        $reasons[] = stripos($hay, $f['keyword']) !== false
            ? $ok('Matches your search "' . $f['keyword'] . '"')
            : $miss('Not matched: your search "' . $f['keyword'] . '"');
    }
    if ($f['bhk']) {
        $config = (string)($p['bhk_config'] ?? '');
        $hits = array_values(array_filter($f['bhk'], fn($b) => preg_match('/' . bhk_regex($b) . '/', $config)));
        $wanted = implode(' / ', $f['bhk']) . ' BHK';
        if (stripos($config, 'BHK') === false) {
            $reasons[] = $miss('Configuration not listed — ask our team about ' . $wanted);
        } else {
            $reasons[] = $hits
                ? $ok(implode(' / ', $hits) . ' BHK available (' . $config . ')')
                : $miss('Not matched: ' . $wanted . ' (offers ' . $config . ')');
        }
    }
    if ($f['price_min'] || $f['price_max']) {
        $price = (float)($p['price_min'] ?? 0);
        $display = $p['price_display'] ?? format_inr_short($price);
        if ($price <= 0) {
            $reasons[] = $miss('Starting price not listed — ask our team');
        } elseif (($f['price_max'] && $price > $f['price_max']) || ($f['price_min'] && $price < $f['price_min'])) {
            $reasons[] = $miss('Not matched: starts at ' . $display . ', outside your ' . budget_label($f['price_min'], $f['price_max']) . ' budget');
        } else {
            $reasons[] = $ok('Starts at ' . $display . ', within your ' . budget_label($f['price_min'], $f['price_max']) . ' budget');
        }
    }
    if ($f['category'] !== '') {
        $reasons[] = ($p['category'] ?? '') === $f['category']
            ? $ok(category_label($f['category']) . ' (matches your stated timeline)')
            : $miss('Not matched: ' . category_label($f['category']) . ' (this is ' . category_label($p['category'] ?? '') . ')');
    }
    if ($f['prefs']) {
        $nouns = ['school' => 'school', 'metro' => 'metro station', 'hospital' => 'hospital', 'office' => 'office hub'];
        $loc = property_location($p);
        foreach ($f['prefs'] as $pref) {
            $cfg = FILTER_PREFS[$pref];
            $noun = $nouns[$cfg['category']];
            $km = rtrim(rtrim(number_format($cfg['km'], 1), '0'), '.');
            if (!$loc || $loc['approximate']) {
                $reasons[] = $miss($cfg['label'] . ': not checked — exact location not set yet');
                continue;
            }
            $near = get_nearby_places($loc['lat'], $loc['lng'], $cfg['category'], $cfg['km'], 1);
            $reasons[] = $near
                ? $ok(ucfirst($noun) . ' within ' . $km . ' km (' . $near[0]['name'] . ', ' . $near[0]['km'] . ' km straight-line)')
                : $miss('Not matched: no ' . $noun . ' within ' . $km . ' km in our location dataset');
        }
    }
    return $reasons;
}

function rec_score(array $reasons): int
{
    return count(array_filter($reasons, fn($r) => $r['ok']));
}

/**
 * Best-fit listings for a filter set. Scores the whole matching result set
 * (bounded), not just the visible page; ties keep featured-first order.
 */
function rec_top(array $f, int $limit = 2, array $excludeIds = []): array
{
    [$where, $params] = filters_where($f);
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS corridor_name, c.lat AS corridor_lat, c.lng AS corridor_lng
         FROM properties p LEFT JOIN corridors c ON c.id = p.corridor_id' . $where . ' ORDER BY p.featured DESC, p.id ASC LIMIT 200'
    );
    $stmt->execute($params);
    $items = [];
    foreach ($stmt->fetchAll() as $i => $p) {
        if (in_array((int)$p['id'], $excludeIds, true)) continue;
        $reasons = rec_reasons($p, $f);
        $items[] = ['p' => $p, 'reasons' => $reasons, 'score' => rec_score($reasons), 'order' => $i];
    }
    usort($items, fn($a, $b) => [$b['score'], $a['order']] <=> [$a['score'], $b['order']]);
    return array_slice($items, 0, $limit);
}

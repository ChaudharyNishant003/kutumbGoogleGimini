<?php
// Deterministic tests for the AI features' PHP logic (no OpenAI calls).
// Run inside the app container:  docker exec -i <container> php /var/www/html/tests/backend/ai-unit.php
require '/var/www/html/includes/ai-finder.php';
require '/var/www/html/includes/ai-recommend.php';

$pass = 0;
$fail = 0;
function check(string $name, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) {
        $pass++;
    } else {
        $fail++;
        echo "FAIL: {$name}\n  got:  " . var_export($got, true) . "\n  want: " . var_export($want, true) . "\n";
    }
}

// --- Indian budget parsing ---
$inr = [
    '2 crore' => 20000000.0, '2cr' => 20000000.0, '1.5 Cr' => 15000000.0, '₹2 Cr' => 20000000.0, '2.5 crores' => 25000000.0,
    '80 lakh' => 8000000.0, '80L' => 8000000.0, '₹90L' => 9000000.0, '90 lakhs' => 9000000.0, '75 lac' => 7500000.0,
    '1,20,00,000' => 12000000.0, '12000000' => 12000000.0, 'Rs 2 crore' => 20000000.0,
    '2 करोड़' => 20000000.0, 'डेढ़ करोड़' => 15000000.0, 'ढाई करोड़' => 25000000.0, 'साढ़े 3 करोड़' => 35000000.0, '50 लाख' => 5000000.0,
    'dedh crore' => 15000000.0, 'dhai cr' => 25000000.0, 'do crore' => 20000000.0, 'pachas lakh' => 5000000.0,
    'sawa 2 crore' => 22500000.0, 'sadhe 2 crore' => 25000000.0,
];
foreach ($inr as $text => $want) check("inr '{$text}'", ai_parse_inr($text), $want);
check('inr empty', ai_parse_inr(''), null);
check('inr null', ai_parse_inr(null), null);
check('inr nonsense', ai_parse_inr('cheap'), null);
check('inr bare small number is ambiguous', ai_parse_inr('2'), null);
check('inr "years" not treated as rupees', ai_parse_inr('3 years'), null);

// --- Budget ranges from the two model phrases ---
check('range in one phrase (se)', ai_budget_from_texts('2.5 se 4 crore', null), [25000000.0, 40000000.0]);
check('range in max phrase (to)', ai_budget_from_texts(null, '80 lakh to 1.2 crore'), [8000000.0, 12000000.0]);
check('range with dash', ai_budget_from_texts(null, '2-3 cr'), [20000000.0, 30000000.0]);
check('bare min inherits unit', ai_budget_from_texts('2.5', '4 crore'), [25000000.0, 40000000.0]);
check('only max', ai_budget_from_texts(null, 'under 2 crore'), [null, 20000000.0]);
check('swapped bounds', ai_budget_from_texts('5 crore', '2 crore'), [20000000.0, 50000000.0]);
check('Hindi range', ai_budget_from_texts(null, '2 से 3 करोड़'), [20000000.0, 30000000.0]);

// --- BHK regex (exact number, not 3.5 / 13) ---
$m = fn($n, $s) => (bool)preg_match('/' . bhk_regex($n) . '/', $s);
check('3 in "3 BHK"', $m(3, '3 BHK'), true);
check('3 in "3 & 4 BHK"', $m(3, '3 & 4 BHK (~1,751 sq ft)'), true);
check('4 in "3/4 BHK"', $m(4, '3/4 BHK'), true);
check('3 not in "3.5 & 4.5 BHK"', $m(3, '3.5 & 4.5 BHK'), false);
check('3 not in "13 BHK"', $m(3, '13 BHK'), false);
check('3 not from sq-ft "3,000"', $m(3, '3.5 & 4.5 BHK (~2,125–3,000 sq ft)'), false);
check('4 in "3.5 & 4 BHK (~3,000 sq ft)"', $m(4, '3.5 & 4 BHK (~3,000 sq ft)'), true);
check('3 in "3BHK"', $m(3, '3BHK'), true);
check('3 in "2, 3 & 4 bhk"', $m(3, '2, 3 & 4 bhk'), true);
check('2 not from "(2 towers)"', $m(2, '3 BHK (2 towers)'), false);

// --- Filter validation drops anything not in the allowlist ---
$f = filters_from_array(['category' => 'mansion', 'bhk' => [3, 9, '4', 'x'], 'pref' => ['near_school', 'near_beach'], 'price_max' => '-5', 'sector' => ['Sector 99999']]);
check('unknown category dropped', $f['category'], '');
check('bhk allowlist', $f['bhk'], [3, 4]);
check('pref allowlist', $f['prefs'], ['near_school']);
check('negative budget dropped', $f['price_max'], null);
check('unknown sector dropped', $f['sectors'], []);
$catalogSectors = filter_catalog()['sectors'];
if ($catalogSectors) {
    $s = $catalogSectors[0];
    check('known sector kept (case-insensitive)', filters_from_array(['sector' => [strtoupper($s)]])['sectors'], [$s]);
}
$f2 = filters_from_array(['price_min' => '50000000', 'price_max' => '20000000']);
check('min/max swapped', [$f2['price_min'], $f2['price_max']], [20000000.0, 50000000.0]);

// --- Location matching ---
check('city', finder_match_location('Gurugram')['type'], 'city');
check('city (Hindi)', finder_match_location('गुड़गांव')['type'], 'city');
check('unknown sector', finder_match_location('Sector 999')['type'], 'unknown');
if (in_array('Sector 79', $catalogSectors, true)) {
    check('sector phrase', finder_match_location('sector 79'), ['type' => 'sector', 'value' => 'Sector 79']);
    check('sector (Hindi)', finder_match_location('सेक्टर 79'), ['type' => 'sector', 'value' => 'Sector 79']);
}

// --- Haversine ---
check('haversine zero', haversine_km(28.4595, 77.0266, 28.4595, 77.0266), 0.0);
$d = haversine_km(28.4595, 77.0266, 28.5562, 77.1000); // Gurgaon centre → IGI T3, ~13 km
check('haversine ~13km', $d > 12.5 && $d < 13.5, true);

// --- Recommendation reasons ---
$p = ['name' => 'X', 'sector' => 'Sector 86', 'corridor_id' => 1, 'category' => 'new-launch', 'bhk_config' => '3.5 & 4.5 BHK', 'price_min' => 30000000, 'price_display' => '3 Cr+'];
$r = rec_reasons($p, array_merge(filter_empty(), ['bhk' => [3]]));
check('3.5 BHK is not 3 BHK', $r[0]['ok'], false);
$r = rec_reasons(array_merge($p, ['bhk_config' => 'Multiple configurations']), array_merge(filter_empty(), ['bhk' => [3]]));
check('unknown config flagged, not matched', [$r[0]['ok'], str_contains($r[0]['text'], 'not listed')], [false, true]);
$r = rec_reasons($p, array_merge(filter_empty(), ['price_max' => 40000000.0]));
check('within budget', $r[0]['ok'], true);
$r = rec_reasons($p, array_merge(filter_empty(), ['price_max' => 20000000.0]));
check('over budget', $r[0]['ok'], false);
check('no filters → no reasons', rec_reasons($p, filter_empty()), []);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);

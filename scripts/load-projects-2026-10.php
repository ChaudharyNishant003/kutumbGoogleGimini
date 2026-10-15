<?php
// One-off data load (2026-10-03): adds 4 projects the site owner shared —
// Adani M2K The Marq, ATS HomeKraft Sanctuary 105, Cocoa County (County Group)
// and Sakura by Pardos (EOI) — with unit plans, payment plan, specifications,
// RERA registrations, amenities, Project Assistant FAQs and brochure images
// (images live in database/data-assets/projects-2026-10/, phone numbers cropped out).
// It also soft-deletes (Bin, restorable) the old placeholder rows and the
// test properties listed below.
//
// Needs migration 0019. Idempotent: re-running updates the same 4 rows (by slug)
// and replaces their unit plans / payment plan / specs / RERA rows / FAQs with
// what's below — so after admins start editing these in the panel, don't re-run it.
// Run inside the app container:  php scripts/load-projects-2026-10.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// The container's php.ini hides errors, so an uncaught exception would otherwise exit silently.
set_exception_handler(function (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
});

const VERIFIED_ON = '2026-10-03';
$assetDir = __DIR__ . '/../database/data-assets/projects-2026-10/';

$projects = [
    [
        'slug' => 'adani-the-marq',
        'name' => 'Adani M2K The Marq',
        'corridor' => 'dwarka-expressway',
        'sector' => 'Sector 102A',
        'category' => 'new-launch',
        'bhk_config' => '3 & 4 BHK (2,375–3,295 sq ft)',
        'price_display' => '4.04 Cr+',
        'price_min' => 40375000,
        'developer' => 'Adani Realty & M2K',
        'description' => 'The Marq is an Adani Realty and M2K development on a corner site at the junction of the 60 m and 75 m sector roads in Sector 102A, off Dwarka Expressway. Two G+46 towers on about 5 acres hold 3 and 4 BHK residences, with a double-height (6 m) ground floor, a vehicle-free podium, three-level basement parking, 3-tier security and IGBC Gold pre-certification. The clubhouse covers outdoor and indoor sports, wellness and community spaces.',
        'land_area_acres' => 5,
        'tower_count' => 2,
        'unit_count' => null,
        'floors_text' => 'G+46',
        'rate_text' => '₹17,000/sq ft + PLC + GST',
        'payment_plan_note' => 'After 10% payment, signing the Agreement for Sale is mandatory.',
        'is_eoi' => 0,
        'possession_text' => null,
        'rera_number' => 'RC/REP/HARERA/GGM/1042/774/2026/14',
        'lat' => null,
        'lng' => null,
        'images' => ['marq-01-clubhouse-entrance', 'marq-02-towers', 'marq-03-master-plan', 'marq-04-location-map', 'marq-05-outdoor-amenities', 'marq-06-wellness', 'marq-07-community', 'marq-08-tower-a-plan', 'marq-09-3bhk-a', 'marq-10-3bhk-b', 'marq-11-4bhk'],
        'rera' => [
            [null, 'RC/REP/HARERA/GGM/1042/774/2026/14 (Registration no. 14 of 2026)', '2026-02-24'],
        ],
        'units' => [
            // group, unit, saleable, carpet, balcony, rate
            [null, '3 BHK – Type B', 2375, 1287.59, 485.99, 17000],
            [null, "3 BHK – Type B′", 2375, 1288.99, 485.99, 17000],
            [null, '3 BHK – Type A', 2475, 1333.12, 504.29, 17000],
            [null, '3 BHK – Type A + Service Personnel Room', 2490, 1386.51, 504.29, 17000],
            [null, '4 BHK', 3295, 1785.75, 644.23, 17000],
        ],
        'payment' => [
            ['Payment Plan (30:40:30)', 'On application (booking amount)', '₹25 Lakh'],
            ['Payment Plan (30:40:30)', 'On or within 30 days of application', '10% less application amount'],
            ['Payment Plan (30:40:30)', 'On or within 90 days of application', '10%'],
            ['Payment Plan (30:40:30)', 'On or within 150 days of application', '10%'],
            ['Payment Plan (30:40:30)', 'On start of 22nd floor roof slab', '15%'],
            ['Payment Plan (30:40:30)', 'On start of 45th floor roof slab', '25%'],
            ['Payment Plan (30:40:30)', 'On completion of internal plaster of the tower', '15%'],
            ['Payment Plan (30:40:30)', 'On application of OC', '10%'],
            ['Payment Plan (30:40:30)', 'On offer of possession', '5%'],
        ],
        'specs' => [
            [null, 'Air conditioning', 'VRV / VRF air conditioning with heating and cooling'],
            [null, 'Flooring', 'Luxury tiles in living, dining, foyer and passage'],
            [null, 'Flooring', 'Laminated wooden flooring in all bedrooms and dress area'],
            [null, 'Kitchen', 'Modular kitchen with chimney, hob, built-in microwave and oven, refrigerator, and sink with faucet'],
            [null, 'Kitchen', 'Provision for piped gas supply'],
            [null, 'Appliances', 'Instant geyser and washing machine'],
            [null, 'Doors & windows', 'Digital main door lock and video door phone'],
            [null, 'Doors & windows', 'Internal doors: laminated flush / factory-made modular flush doors and frames'],
            [null, 'Doors & windows', 'External glazing: aluminium frames with toughened glass and mosquito mesh in living, dining, kitchen and all bedrooms'],
        ],
        'amenities' => ['Clubhouse', 'Swimming Pool', "Kids' Play Area", 'Gymnasium', 'Indoor Games Room', 'Landscaped Gardens', '24x7 Security', 'Covered Parking', 'Pickleball Court', 'Multipurpose Court', 'Squash Court', 'Badminton Court', 'Yoga & Meditation', 'Spa / Sauna & Steam', 'Mini Theatre', 'Library', 'Café / Restaurant', 'Business Centre', 'Amphitheatre', 'Pet Park', 'Senior Citizen Area'],
        'faqs' => [
            ['What is the price of The Marq?', 'The base rate is ₹17,000 per sq ft plus PLC and GST. At that rate a 3 BHK of 2,375 sq ft (saleable) works out to about ₹4.04 Cr and the 4 BHK of 3,295 sq ft to about ₹5.60 Cr, before PLC, parking, GST and other charges. Your advisor can share the exact cost sheet.'],
            ['What unit sizes are available at The Marq?', '3 BHK Type B and B′: 2,375 sq ft saleable (about 1,288 sq ft carpet). 3 BHK Type A: 2,475 sq ft saleable (1,333.12 sq ft carpet). 3 BHK Type A with service personnel room: 2,490 sq ft saleable (1,386.51 sq ft carpet). 4 BHK: 3,295 sq ft saleable (1,785.75 sq ft carpet).'],
            ['What is the payment plan and booking amount?', 'It is a 30:40:30 plan: ₹25 lakh on application, 10% (less the application amount) within 30 days, 10% within 90 days, 10% within 150 days, 15% on the 22nd floor roof slab, 25% on the 45th floor roof slab, 15% on internal plaster of the tower, 10% on application of OC and 5% on offer of possession. The Agreement for Sale is signed after 10% payment.'],
            ['Where exactly is The Marq?', 'In Sector 102A, on a corner site at the junction of the 60 m and 75 m sector roads off Dwarka Expressway, with a proposed metro station nearby.'],
            ['How many towers and floors does The Marq have?', 'Two towers of G+46 floors on about 5 acres, with three levels of basement parking.'],
            ['Is The Marq green certified?', 'Yes — it is IGBC pre-certified with a Gold rating (GH 240971).'],
        ],
    ],
    [
        'slug' => 'ats-sanctuary-105',
        'name' => 'ATS HomeKraft Sanctuary 105',
        'corridor' => 'dwarka-expressway',
        'sector' => 'Sector 105',
        'category' => 'new-launch',
        'bhk_config' => '3.5 & 4 BHK (1,850–2,800 sq ft)',
        'price_display' => 'Price on Request',
        'price_min' => 0,
        'developer' => 'ATS HomeKraft',
        'description' => 'Sanctuary 105 by ATS HomeKraft brings art, nature and Art Deco architecture together on 13.07 acres in Sector 105, off Dwarka Expressway. Its 11 towers hold 734 apartments in 3.5 BHK and 4 BHK layouts around Club Reverb, a clubhouse with recreation, celebration, transformation and party zones. The landscape includes Miyawaki, butterfly and fragrance gardens, a tree house, an amphitheatre and a full set of sports courts.',
        'land_area_acres' => 13.07,
        'tower_count' => 11,
        'unit_count' => 734,
        'floors_text' => null,
        'rate_text' => null,
        'payment_plan_note' => null,
        'is_eoi' => 0,
        'possession_text' => null,
        'rera_number' => '89 of 2024',
        'lat' => null,
        'lng' => null,
        'images' => ['sanctuary-01-entrance', 'sanctuary-02-pool', 'sanctuary-03-basketball', 'sanctuary-04-greens', 'sanctuary-05-master-plan', 'sanctuary-06-location-map', 'sanctuary-07-unit-plans'],
        'rera' => [
            ['Phase 1', '89 of 2024', '2024-08-28'],
            ['Phase 2', '25 of 2025', '2025-03-04'],
        ],
        'units' => [
            [null, '3.5 BHK – Type B', 1850, 1035, 245, null],
            [null, '4 BHK – Type A', 2800, 1565, 340, null],
        ],
        'payment' => [],
        'specs' => [],
        'amenities' => ['Clubhouse', 'Swimming Pool', "Kids' Play Area", 'Gymnasium', 'Landscaped Gardens', 'Indoor Games Room', 'Tennis Court', 'Pickleball Court', 'Badminton Court', 'Squash Court', 'Basketball Court', 'Cricket Pitch', 'Golf Putting Green / Simulator', 'Skating Rink', 'Spa / Sauna & Steam', 'Amphitheatre', 'Café / Restaurant', 'Pet Park', 'Senior Citizen Area'],
        'faqs' => [
            ['What unit sizes are available at Sanctuary 105?', '3.5 BHK (Type B): 1,850 sq ft saleable, 1,035 sq ft carpet, 245 sq ft balcony. 4 BHK (Type A): 2,800 sq ft saleable, 1,565 sq ft carpet, 340 sq ft balcony.'],
            ['How big is the Sanctuary 105 project?', 'It spans 13.07 acres (52,924.7 sq m) with 11 towers and 734 apartments.'],
            ['What is the price of Sanctuary 105?', 'The price is shared on request — your advisor will send the current price list and availability.'],
            ['What is Club Reverb?', 'Club Reverb is the project clubhouse at the centre of the site, with four zones: recreation, celebration, transformation and party.'],
            ['What is near Sanctuary 105?', 'As per the developer, within about 10 minutes: Gurugram Railway Station, Conscient One Mall, the Delhi border, Old Gurugram and the upcoming metro station. Within about 20 minutes: Sector 21 metro station, the upcoming Dwarka golf course, Diplomatic Enclave-2, Yashobhoomi and 32nd Avenue. Within about 30 minutes: IGI Airport, Cyber City, IMT Manesar, Worldmark and SGT University.'],
        ],
    ],
    [
        'slug' => 'cocoa-county',
        'name' => 'Cocoa County by County Group',
        'corridor' => 'new-gurgaon',
        'sector' => 'Sector 88A',
        'category' => 'new-launch',
        'bhk_config' => '3 & 4 BHK (2,592–4,671 sq ft)',
        'price_display' => 'Price on Request',
        'price_min' => 0,
        'developer' => 'County Group',
        'description' => 'Cocoa County is County Group’s township address in Sector 88A on Pataudi Road, off Dwarka Expressway. Phase 1 is delivered; Phases 2, 3 and 4 were RERA-registered in 2026. It comes in two collections — Cocoa County Gold, with large 4 BHK homes, VRV air-conditioning and false ceilings, and Cocoa County, with 3 and 4 BHK homes — all with imported marble in living areas and 4 lifts per core. The master plan includes Club Mocha, a swimming pool, padel, pickleball, tennis and volleyball courts, golf putting greens, a cricket pitch, a floating café, a nature trail, a pet park and an on-site school.',
        'land_area_acres' => null,
        'tower_count' => null,
        'unit_count' => null,
        'floors_text' => null,
        'rate_text' => null,
        'payment_plan_note' => null,
        'is_eoi' => 0,
        'possession_text' => null,
        'rera_number' => 'RC/REP/HARERA/GGM/1086/818/2026/58',
        'lat' => 28.4296287,
        'lng' => 76.9492138,
        'images' => ['cocoa-01-cover', 'cocoa-02-master-plan', 'cocoa-03-gold-4671', 'cocoa-04-gold-3825', 'cocoa-05-2997', 'cocoa-06-2592'],
        'rera' => [
            ['Phase 2', 'RC/REP/HARERA/GGM/1086/818/2026/58', null],
            ['Phase 3', 'RC/REP/HARERA/GGM/1087/819/2026/59', null],
            ['Phase 4', 'RC/REP/HARERA/GGM/1088/820/2026/60', null],
        ],
        'units' => [
            ['Cocoa County Gold', '4 BHK + Study + Family Lounge', 4671, null, null, null],
            ['Cocoa County Gold', '4 BHK + Family Lounge', 3825, null, null, null],
            ['Cocoa County', '4 BHK + 4T + Study', 2997, null, null, null],
            ['Cocoa County', '3 BHK', 2592, 1498, 582, null],
        ],
        'payment' => [],
        'specs' => [
            ['Cocoa County Gold', 'Air conditioning', 'VRV ACs in bedrooms, drawing, dining room and kitchen'],
            ['Cocoa County Gold', 'Lighting & ceiling', 'Chandelier / hanging light in drawing room and master bedroom'],
            ['Cocoa County Gold', 'Lighting & ceiling', 'Designer false ceiling in all rooms, drawing and dining room'],
            ['Cocoa County', 'Air conditioning', 'Split ACs in all bedrooms, drawing room and dining room'],
            ['Cocoa County', 'Lighting & ceiling', 'Light fixtures in drawing room and bedrooms'],
            [null, 'Flooring', 'Imported marble in drawing, dining and kitchen'],
            [null, 'Flooring', 'Laminated wooden flooring in all bedrooms'],
            [null, 'Flooring', 'Designer anti-skid tiles in balconies; granite stone in toilets; stone in common lobby and stairs'],
            [null, 'Kitchen', 'Granite counter with stainless steel sink fitted with RO'],
            [null, 'Kitchen', 'Modular cabinets; designer ceramic tiles / granite up to 2 ft above the counter'],
            [null, 'Electrical', 'Fire-resistant copper wiring in concealed PVC conduits; modular switches and sockets'],
            [null, 'Electrical', 'TV and telephone points in all rooms; exhaust fan in kitchen; geysers in toilets and kitchen; fans in drawing room and bedrooms'],
            [null, 'Sanitary', 'Corrosion-free PPR / UPVC internal piping; CP taps and fittings of reputed brands'],
            [null, 'Sanitary', 'Wall-mounted WCs and wash basins; floor-mounted WC and corner basin in utility toilet'],
            [null, 'Doors & windows', 'Designer flush doors in polish / duco paint on hardwood frames; UPVC sliding doors and windows'],
            [null, 'Walls & railing', 'Textured paint outside, OBD paint inside; stainless steel / glass / RCC / mild steel balcony railing'],
        ],
        'amenities' => ['Clubhouse', 'Swimming Pool', "Kids' Play Area", 'Landscaped Gardens', 'Padel Court', 'Pickleball Court', 'Tennis Court', 'Multipurpose Court', 'Cricket Pitch', 'Golf Putting Green / Simulator', 'Café / Restaurant', 'Pet Park', 'Senior Citizen Area'],
        'faqs' => [
            ['What is the difference between Cocoa County and Cocoa County Gold?', 'Cocoa County Gold has the larger 4 BHK homes (4,671 and 3,825 sq ft super area) with VRV air-conditioning, chandeliers and designer false ceilings. Cocoa County has 4 BHK + study (2,997 sq ft) and 3 BHK (2,592 sq ft) homes with split ACs. Both get imported marble in drawing, dining and kitchen.'],
            ['What unit sizes are available at Cocoa County?', 'Cocoa County Gold: 4 BHK + study + family lounge, 4,671 sq ft; 4 BHK + family lounge, 3,825 sq ft. Cocoa County: 4 BHK + 4T + study, 2,997 sq ft; 3 BHK, 2,592 sq ft super area (1,498 sq ft carpet, 582 sq ft balcony).'],
            ['Which phases of Cocoa County are RERA registered?', 'Phase 2 (RC/REP/HARERA/GGM/1086/818/2026/58), Phase 3 (RC/REP/HARERA/GGM/1087/819/2026/59) and Phase 4 (RC/REP/HARERA/GGM/1088/820/2026/60). Phase 1 is already delivered.'],
            ['Where is Cocoa County?', 'Sector 88A on Pataudi Road, off Dwarka Expressway, Gurugram.'],
            ['What is the price of Cocoa County?', 'The price is shared on request — your advisor will send the current price list and availability.'],
        ],
    ],
    [
        'slug' => 'sakura-by-pardos',
        'name' => 'Sakura by Pardos',
        'corridor' => 'new-gurgaon',
        'sector' => 'Sector 90',
        'category' => 'new-launch',
        'bhk_config' => '3 & 4 BHK (1,850–3,400 sq ft)',
        'price_display' => 'Price on Request',
        'price_min' => 0,
        'developer' => 'Pardos',
        'description' => 'Sakura by Pardos is a premium residential project planned on 14.05 acres in Sector 90, New Gurgaon — 9 towers of G+23 to G+24 floors with spacious 3 and 4 BHK homes from about 1,850 to 3,400 sq ft, including servant-room and study options. The project is taking expressions of interest (EOI); its RERA registration and official price list are awaited.',
        'land_area_acres' => 14.05,
        'tower_count' => 9,
        'unit_count' => null,
        'floors_text' => 'G+23 to G+24',
        'rate_text' => null,
        'payment_plan_note' => 'Construction-linked (CLP) and possession-linked (PLP) payment plans available.',
        'is_eoi' => 1,
        'possession_text' => null,
        'rera_number' => null,
        'lat' => null,
        'lng' => null,
        'images' => [],
        'rera' => [],
        'units' => [
            [null, '3 BHK + 2T', 1850, null, null, null],
            [null, '3 BHK + 3T', 2200, null, null, null],
            [null, '3 BHK + Servant', 2400, null, null, null],
            [null, '3 BHK + Study + Servant', 2800, null, null, null],
            [null, '4 BHK + Servant', 2900, null, null, null],
            [null, '4 BHK + Study + Servant', 3400, null, null, null],
        ],
        'payment' => [],
        'specs' => [],
        'amenities' => [],
        'faqs' => [
            ['What does EOI mean for Sakura by Pardos?', 'The project is taking expressions of interest (EOI) before its RERA registration. Registering interest gets you the official price list and unit availability first; bookings open only after the project is RERA-registered.'],
            ['What unit sizes are planned at Sakura?', '3 BHK + 2T from about 1,850 sq ft, 3 BHK + 3T about 2,200 sq ft, 3 BHK + servant 2,400 sq ft, 3 BHK + study + servant 2,800 sq ft, 4 BHK + servant 2,900 sq ft and 4 BHK + study + servant 3,400 sq ft.'],
            ['How big is the Sakura project?', 'About 14.05 acres in Sector 90 with 9 towers of G+23 to G+24 floors.'],
            ['What payment plans will Sakura offer?', 'Both construction-linked (CLP) and possession-linked (PLP) payment plans.'],
            ['What is the price of Sakura by Pardos?', 'The official price list releases with the RERA registration. Register your interest and your advisor will share it as soon as it is out.'],
        ],
    ],
];

// Placeholder rows from the 2026-09 data load (local DB only) that these replace.
$replacedSlugs = ['county', 'ats-homekraft-sanctuary'];
// Test properties created while QA-ing the admin panel on production (matched by id AND name).
$testProperties = [[12, 'Nishant01'], [14, 'Mobile created'], [15, 'Mobile created'], [39, '28aug26 new test property'], [40, 'sdfg'], [41, 'dvxfbcgh']];

$pdo = db();
$corridorIds = $pdo->query('SELECT slug, id FROM corridors')->fetchAll(PDO::FETCH_KEY_PAIR);
$amenityIds = $pdo->query('SELECT name, id FROM amenities')->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($projects as $p) {
    foreach ($p['amenities'] as $a) {
        if (!isset($amenityIds[$a])) throw new RuntimeException("Unknown amenity '$a' — run migration 0019 first.");
    }
    if (!isset($corridorIds[$p['corridor']])) throw new RuntimeException("Unknown corridor '{$p['corridor']}'.");

    // Copy images first (outside the transaction — files aren't transactional).
    $imageFiles = [];
    foreach ($p['images'] as $img) {
        $src = $assetDir . $img . '.jpg';
        $dest = 'proj2610-' . $img . '.jpg';
        if (!is_file($src)) throw new RuntimeException("Missing image $src");
        if (!is_file(UPLOAD_DIR . $dest)) {
            copy($src, UPLOAD_DIR . $dest);
            generate_image_card_derivative(UPLOAD_DIR, $dest);
        }
        $imageFiles[] = $dest;
    }

    $pdo->beginTransaction();
    $fields = [
        'name' => $p['name'], 'corridor_id' => (int)$corridorIds[$p['corridor']], 'sector' => $p['sector'],
        'category' => $p['category'], 'bhk_config' => $p['bhk_config'], 'possession_text' => $p['possession_text'],
        'info_verified_on' => VERIFIED_ON, 'land_area_acres' => $p['land_area_acres'], 'tower_count' => $p['tower_count'],
        'unit_count' => $p['unit_count'], 'floors_text' => $p['floors_text'], 'rate_text' => $p['rate_text'],
        'payment_plan_note' => $p['payment_plan_note'], 'is_eoi' => $p['is_eoi'], 'price_display' => $p['price_display'],
        'price_min' => $p['price_min'], 'developer' => $p['developer'], 'description' => $p['description'],
        'image_path' => $imageFiles[0] ?? null, 'lat' => $p['lat'], 'lng' => $p['lng'], 'rera_number' => $p['rera_number'],
    ];
    $stmt = $pdo->prepare('SELECT id FROM properties WHERE slug = ?');
    $stmt->execute([$p['slug']]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($fields)));
        $pdo->prepare("UPDATE properties SET $set, deleted_at = NULL WHERE id = ?")->execute([...array_values($fields), $id]);
        $verb = 'updated';
    } else {
        $cols = array_keys($fields);
        $pdo->prepare('INSERT INTO properties (slug, ' . implode(', ', $cols) . ') VALUES (?' . str_repeat(',?', count($cols)) . ')')
            ->execute([$p['slug'], ...array_values($fields)]);
        $id = (int)$pdo->lastInsertId();
        $verb = 'added';
    }

    foreach (['property_unit_plans', 'property_payment_plans', 'property_specifications', 'property_rera_registrations', 'property_amenities', 'property_faqs'] as $t) {
        $pdo->prepare("DELETE FROM $t WHERE property_id = ?")->execute([$id]);
    }
    $ins = $pdo->prepare('INSERT INTO property_unit_plans (property_id, group_label, unit_label, saleable_sqft, carpet_sqft, balcony_sqft, rate_per_sqft, sort_order) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($p['units'] as $i => $u) $ins->execute([$id, ...$u, $i + 1]);
    $ins = $pdo->prepare('INSERT INTO property_payment_plans (property_id, plan_name, milestone, amount_text, sort_order) VALUES (?,?,?,?,?)');
    foreach ($p['payment'] as $i => $r) $ins->execute([$id, ...$r, $i + 1]);
    $ins = $pdo->prepare('INSERT INTO property_specifications (property_id, group_label, category, detail, sort_order) VALUES (?,?,?,?,?)');
    foreach ($p['specs'] as $i => $s) $ins->execute([$id, ...$s, $i + 1]);
    $ins = $pdo->prepare('INSERT INTO property_rera_registrations (property_id, phase_label, rera_number, registered_on, sort_order) VALUES (?,?,?,?,?)');
    foreach ($p['rera'] as $i => $r) $ins->execute([$id, ...$r, $i + 1]);
    $ins = $pdo->prepare('INSERT INTO property_amenities (property_id, amenity_id) VALUES (?,?)');
    foreach ($p['amenities'] as $a) $ins->execute([$id, $amenityIds[$a]]);
    $ins = $pdo->prepare('INSERT INTO property_faqs (property_id, question, answer, sort_order) VALUES (?,?,?,?)');
    foreach ($p['faqs'] as $i => $f) $ins->execute([$id, $f[0], $f[1], $i + 1]);

    $existing = $pdo->prepare('SELECT image_path FROM property_images WHERE property_id = ?');
    $existing->execute([$id]);
    $have = $existing->fetchAll(PDO::FETCH_COLUMN);
    $ins = $pdo->prepare('INSERT INTO property_images (property_id, image_path, sort_order) VALUES (?,?,?)');
    foreach ($imageFiles as $i => $f) {
        if (!in_array($f, $have, true)) $ins->execute([$id, $f, $i + 1]);
    }
    $pdo->commit();

    printf("%-30s %s (id %d): %d units, %d payment rows, %d specs, %d RERA, %d amenities, %d FAQs, %d images\n",
        $p['name'], $verb, $id, count($p['units']), count($p['payment']), count($p['specs']), count($p['rera']),
        count($p['amenities']), count($p['faqs']), count($imageFiles));
}

$stmt = $pdo->prepare('UPDATE properties SET deleted_at = NOW() WHERE slug = ? AND deleted_at IS NULL');
foreach ($replacedSlugs as $slug) {
    $stmt->execute([$slug]);
    if ($stmt->rowCount()) echo "Moved old placeholder '$slug' to the Bin\n";
}
$stmt = $pdo->prepare('UPDATE properties SET deleted_at = NOW() WHERE id = ? AND name = ? AND deleted_at IS NULL');
foreach ($testProperties as [$tid, $tname]) {
    $stmt->execute([$tid, $tname]);
    if ($stmt->rowCount()) echo "Moved test property #$tid '$tname' to the Bin\n";
}
echo "Done.\n";

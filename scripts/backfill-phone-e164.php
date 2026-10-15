<?php
// One-off, idempotent: normalizes enquiries.phone into phone_e164
// (+91XXXXXXXXXX). Only touches rows where phone_e164 is still NULL, so
// re-running is always safe. Run via `php scripts/backfill-phone-e164.php`
// inside the app container (has DB access via config/config.php).
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; // normalize_to_e164_in()

$rows = db()->query('SELECT id, phone FROM enquiries WHERE phone_e164 IS NULL')->fetchAll();
$updated = 0;
$skipped = [];

$stmt = db()->prepare('UPDATE enquiries SET phone_e164 = ? WHERE id = ?');
foreach ($rows as $row) {
    $e164 = normalize_to_e164_in($row['phone']);
    if ($e164 === null) {
        $skipped[] = $row['id'] . ' (' . $row['phone'] . ')';
        continue;
    }
    $stmt->execute([$e164, $row['id']]);
    $updated++;
}

echo "Updated: $updated\n";
if ($skipped) {
    echo "Skipped (unparseable, needs manual review): " . implode(', ', $skipped) . "\n";
} else {
    echo "Skipped: none\n";
}

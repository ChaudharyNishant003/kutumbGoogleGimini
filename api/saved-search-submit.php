<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/lead_assign.php';
require_once __DIR__ . '/../includes/leads.php';

$successMessage = "Thanks — we've saved your search and an advisor will get in touch personally when we have a match.";
['name' => $name, 'phone' => $phone, 'email' => $email] = validate_public_lead_form('saved-search-submit', $successMessage);

$corridorId = !empty($_POST['corridor_id']) ? (int)$_POST['corridor_id'] : null;

$validCategories = ['new-launch', 'under-construction', 'ready-to-move', 'commercial'];
$category = $_POST['category'] ?? '';
$category = in_array($category, $validCategories, true) ? $category : null;

$budgetMinRaw = trim($_POST['budget_min'] ?? '');
$budgetMin = $budgetMinRaw !== '' ? (float)$budgetMinRaw : null;
$budgetMaxRaw = trim($_POST['budget_max'] ?? '');
$budgetMax = $budgetMaxRaw !== '' ? (float)$budgetMaxRaw : null;

// A budget "band" (e.g. "2.5-4", "6+", "commercial") is a coarser shorthand
// used by the Why an Advisor consultation form, sent instead of explicit
// budget_min/budget_max. It always maps to a Cr figure, so the multiplier
// is fixed here rather than passed from the client.
$budgetBand = trim($_POST['budget_band'] ?? '');
if ($budgetBand !== '' && $budgetMin === null && $budgetMax === null) {
    if ($budgetBand === 'commercial') {
        $category = 'commercial';
    } elseif (preg_match('/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/', $budgetBand, $m)) {
        $budgetMin = (float)$m[1] * 10000000;
        $budgetMax = (float)$m[2] * 10000000;
    } elseif (preg_match('/^(\d+(?:\.\d+)?)\+$/', $budgetBand, $m)) {
        $budgetMin = (float)$m[1] * 10000000;
    }
}

$validTimelines = ['now', '6m', '12m', 'research'];
$timeline = $_POST['timeline'] ?? '';
$timeline = in_array($timeline, $validTimelines, true) ? $timeline : null;

$source = trim($_POST['source'] ?? '');
$source = $source !== '' ? mb_substr($source, 0, 40) : null;

$bhkConfig = trim($_POST['bhk_config'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$stmt = db()->prepare('INSERT INTO saved_searches (name, phone, email, corridor_id, category, budget_min, budget_max, timeline, bhk_config, notes, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([
    $name, $phone, $email !== '' ? $email : null, $corridorId, $category,
    $budgetMin, $budgetMax, $timeline, $bhkConfig !== '' ? $bhkConfig : null, $notes !== '' ? $notes : null, $source,
]);

$savedSearchId = (int)db()->lastInsertId();

// One person, one lead: the same submission also lands in the main enquiries
// list (with the search criteria in the message) so advisors see it there and
// nobody creates a duplicate by hand. The lead and the saved search go to the
// same agent. consent_basis records whether the visitor asked to be called
// (the consultation form is itself a call booking; on the saved-search page
// they tick the box).
$wantsCall = !empty($_POST['call_ok']) || ($_POST['source'] ?? '') === 'why-an-advisor';
$bits = ['Saved search #' . $savedSearchId];
if ($corridorId) {
    $cn = db()->prepare('SELECT name FROM corridors WHERE id = ?');
    $cn->execute([$corridorId]);
    if ($cname = $cn->fetchColumn()) $bits[] = 'Corridor: ' . $cname;
}
if ($category) $bits[] = 'Category: ' . $category;
if ($budgetMin !== null || $budgetMax !== null) {
    $bits[] = 'Budget: ' . ($budgetMin !== null ? '₹' . number_format($budgetMin) : 'any') . ' – ' . ($budgetMax !== null ? '₹' . number_format($budgetMax) : 'any');
}
if ($bhkConfig !== '') $bits[] = 'Config: ' . $bhkConfig;
if ($timeline) $bits[] = 'Timeline: ' . (saved_search_timeline_label($timeline) ?? $timeline);
if ($notes !== '') $bits[] = 'Notes: ' . $notes;
if ($campaign = public_form_campaign()) $bits[] = 'Campaign: ' . $campaign;
$enquiryMessage = mb_substr(implode(' | ', $bits), 0, 1000);

$enq = db()->prepare('INSERT INTO enquiries (name, phone, phone_e164, email, message, source, inquiry_at, consent_basis) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)');
$enq->execute([$name, $phone, normalize_to_e164_in($phone), $email !== '' ? $email : null, $enquiryMessage, $source ?? 'saved-search', $wantsCall ? 'inquiry' : 'none']);
lead_auto_assign((int)db()->lastInsertId(), $savedSearchId);

respond_json(true, $successMessage);

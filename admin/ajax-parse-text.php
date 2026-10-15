<?php
// Admin-only helper: takes whatever raw text an admin pastes (a builder's
// brochure copy, a WhatsApp forward, a corridor writeup) and asks OpenAI to
// extract it into the exact fields the Property/Corridor forms use, as
// strict JSON. The browser then fills the visible form fields from that
// JSON — nothing is saved here, the admin still reviews and clicks Save
// themselves on the real form.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

header('Content-Type: application/json');

function fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!csrf_check($input['csrf_token'] ?? null)) {
    fail('Session expired — please refresh the page and try again.');
}

$formType = $input['form_type'] ?? '';
if (!in_array($formType, ['property', 'corridor'], true)) {
    fail('Unknown form type.');
}

$pastedText = trim($input['text'] ?? '');
if ($pastedText === '') {
    fail('Paste some text first.');
}
if (mb_strlen($pastedText) > 8000) {
    fail('That text is too long — please paste a shorter excerpt (max ~8000 characters).');
}

$apiKey = get_openai_key();
if ($apiKey === '') {
    fail("Auto-fill isn't configured yet — add an OpenAI API key in admin Settings first.");
}

if ($formType === 'property') {
    $corridorNames = db()->query('SELECT name FROM corridors WHERE deleted_at IS NULL ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    $amenityNames = db()->query('SELECT name FROM amenities ORDER BY sort_order, name')->fetchAll(PDO::FETCH_COLUMN);

    $corridorList = implode(', ', array_map(fn($n) => '"' . $n . '"', $corridorNames));
    $amenityList = implode(', ', array_map(fn($n) => '"' . $n . '"', $amenityNames));

    $schema = <<<'SCHEMA'
Return a single JSON object with exactly these keys (use null for anything not
mentioned in the text — never invent a value that isn't supported by the text):
{
  "name": string or null — the project/building name,
  "sector": string or null — e.g. "Sector 54",
  "category": one of "new-launch" | "under-construction" | "ready-to-move" | "commercial", or null,
  "bhk_config": string or null — e.g. "3/4 BHK" or "2,150 sq ft, 3BHK",
  "price_display": string or null — a short human price like "3.75 Cr+" or "70 Lakhs+",
  "price_min": number or null — the same starting price as a plain integer number of rupees (1 Cr = 10000000, 1 Lakh = 100000),
  "developer": string or null,
  "description": string or null — 2-4 sentences, written in a confident real-estate-advisory tone, based only on facts present in the text,
  "corridor_name": string or null — MUST be exactly one of this list if it matches, else null: __CORRIDOR_LIST__
  "tier": one of "luxury" | "premium" | "standard", or null — infer from price/positioning language only if clearly stated or strongly implied, else null,
  "rera_number": string or null,
  "amenities": array of strings or null — each one MUST be an exact match from this list, else omit it: __AMENITY_LIST__
}
SCHEMA;
    $schema = str_replace('__CORRIDOR_LIST__', '[' . $corridorList . ']', $schema);
    $schema = str_replace('__AMENITY_LIST__', '[' . $amenityList . ']', $schema);
} else {
    $schema = <<<'SCHEMA'
Return a single JSON object with exactly these keys (use null for anything not
mentioned in the text — never invent a value that isn't supported by the text):
{
  "name": string or null — the corridor/area name, e.g. "Golf Course Extension Road",
  "avg_price_band": string or null — e.g. "₹14,000–19,500/sq ft",
  "tier": one of "luxury" | "premium" | "standard", or null,
  "price_growth_label": string or null — a YoY % like "+9.1%", only if a specific figure is stated,
  "description": string or null — one short sentence for card/footer use,
  "long_description": string or null — a fuller paragraph for the corridor guide page, based only on facts present in the text
}
SCHEMA;
}

$systemPrompt = "You extract structured real-estate listing data from raw pasted text (brochures, WhatsApp "
    . "messages, broker notes) for Kutumb Realty's admin panel. Be conservative — only fill a field when the "
    . "text actually supports it; use null rather than guessing. Respond with ONLY the JSON object, no prose, "
    . "no markdown fences.\n\n" . $schema;

$payload = [
    'model' => OPENAI_MODEL,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $pastedText],
    ],
    'max_tokens' => 700,
    'temperature' => 0.1,
    'response_format' => ['type' => 'json_object'],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

if ($curlError || $httpCode !== 200) {
    error_log('OpenAI auto-fill error: ' . $curlError . ' HTTP ' . $httpCode . ' body: ' . $response);
    fail('The auto-fill assistant is temporarily unavailable — please fill the form manually.', 502);
}

$data = json_decode($response, true);
$content = $data['choices'][0]['message']['content'] ?? null;
$fields = $content ? json_decode($content, true) : null;

if (!is_array($fields)) {
    fail('Could not read structured data from that text — please fill the form manually.', 502);
}

echo json_encode(['ok' => true, 'fields' => $fields]);

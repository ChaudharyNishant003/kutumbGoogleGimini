<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

/** Sends the chat reply; $next tells the widget to show the requirement buttons or the name + phone form. */
function reply(string $text, ?array $next = null): void
{
    echo json_encode(['reply' => $text, 'next' => $next], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    reply('Invalid request.');
}

require_once __DIR__ . '/../includes/ai-guard.php';
if ($block = ai_guard('chatbot')) {
    http_response_code($block['status']);
    reply($block['message']);
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if ($message === '') {
    reply('Ask me anything about our listed properties — price, sector, or configuration.');
}
if (mb_strlen($message) > 500) {
    reply('That message is a bit long — could you shorten it?');
}

// After AI_PHONE_AFTER answers without a phone number, the chat asks for one instead of answering.
ai_visitor();
if (ai_visitor_gated('chatbot')) {
    ai_log('chatbot', $message, ['summary' => 'Asked for name + phone (answer limit reached)'], 'gated');
    reply(ai_visitor_gate_message(), ai_visitor_next());
}

$apiKey = get_openai_key();

if ($apiKey === '') {
    reply("This assistant isn't configured yet — an admin needs to add an OpenAI API key in the admin Settings panel. In the meantime, WhatsApp us at " . SITE_PHONE . " and we'll help directly.");
}

// Build compact, factual context from the live database — the model only
// ever answers from what's actually listed, never invents projects.
$properties = db()->query(
    'SELECT p.name, p.sector, p.category, p.bhk_config, p.price_display, p.price_min, p.developer, c.name AS corridor
     FROM properties p LEFT JOIN corridors c ON c.id = p.corridor_id
     WHERE p.deleted_at IS NULL
     ORDER BY p.price_min ASC'
)->fetchAll();

$context = "Kutumb Realty's current listings (starting_price_inr is the exact numeric starting price in rupees — use it for any price comparison or sorting, never parse the price_display text for math):\n";
foreach ($properties as $p) {
    $context .= sprintf(
        "- %s | Sector: %s | Corridor: %s | Category: %s | Config: %s | price_display: %s | starting_price_inr: %d | Developer: %s\n",
        $p['name'], $p['sector'], $p['corridor'] ?? 'N/A', category_label($p['category']),
        $p['bhk_config'], $p['price_display'], (int)$p['price_min'], $p['developer']
    );
}

$systemPrompt = "You are the property assistant for Kutumb Realty, a Gurgaon real-estate consultancy. "
    . "Answer visitor questions ONLY using the listings data provided below — never invent a project, price, "
    . "or availability that isn't listed. When a question involves a price threshold (\"under X\", \"above Y\", "
    . "\"cheapest\", \"most expensive\"), compare using starting_price_inr as plain numbers (1 crore = 10000000, "
    . "1 lakh = 100000) — do not reason from the price_display text. Double-check each comparison before answering. "
    . "If someone asks about something not in the list, say it's not "
    . "currently in the portfolio and suggest they WhatsApp/call the team at " . SITE_PHONE . " for other options. "
    . "Keep answers short (2-4 sentences), friendly, and specific to the listings.\n\n" . $context;

$payload = [
    'model' => OPENAI_MODEL,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        // Earlier turns of this visit, so "and the 4 BHK one?" still makes sense.
        ...ai_visitor_chat_history(),
        ['role' => 'user', 'content' => $message],
    ],
    'max_tokens' => 300,
    'temperature' => 0.4,
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
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

if ($curlError || $httpCode !== 200) {
    error_log('OpenAI chatbot error: ' . $curlError . ' HTTP ' . $httpCode . ' body: ' . $response);
    ai_log('chatbot', $message, ['summary' => 'OpenAI error (HTTP ' . $httpCode . ')'], 'error');
    reply('Sorry, the assistant is temporarily unavailable — please WhatsApp us at ' . SITE_PHONE . '.');
}

$data = json_decode($response, true);
$answer = trim((string)($data['choices'][0]['message']['content'] ?? ''));

ai_visitor_count_question();
if ($answer === '') {
    ai_log('chatbot', $message, ['summary' => 'No answer returned'], 'fallback');
    reply('Sorry, I could not find an answer to that. Please WhatsApp us at ' . SITE_PHONE . '.', ai_visitor_next($message));
}
ai_log('chatbot', $message, ['summary' => excerpt($answer, 140), 'answer' => $answer], 'answered');
reply($answer, ai_visitor_next($message));

<?php
/**
 * Website AI visitors: one row per person who uses the chatbot, the project assistant, the
 * home finder or the neighbourhood questions, identified by a first-party cookie (kv_vid).
 * Counts their questions across all of those features, asks for their requirement after the
 * 3rd answer and for a name + phone after the 5th (after which the chatbot and the project
 * assistant answer nothing more until a number is given), and turns that number into a lead.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/lead_assign.php';

const AI_VISITOR_COOKIE = 'kv_vid';
const AI_REQUIREMENT_AFTER = 3;   // answers before the requirement questions
const AI_PHONE_AFTER = 5;         // answers before a phone number is required
const AI_VISIT_GAP_MINUTES = 30;  // idle time that starts a new visit
const AI_GATED_ENDPOINTS = ['chatbot', 'assistant']; // the home finder and neighbourhood keep working

function ai_visitor_hash(string $cookieValue): string
{
    return hash('sha256', 'vid:' . $cookieValue . '|' . (defined('DB_PASS') ? DB_PASS : '') . '|kutumb-ai');
}

/**
 * The current visitor's row, creating the cookie and the row on first use. Also starts a new
 * visit when the last activity was more than AI_VISIT_GAP_MINUTES ago. Cached per request.
 */
function ai_visitor(bool $create = true): ?array
{
    static $visitor = null;
    static $touched = false;
    if ($visitor !== null && $touched) return $visitor;

    $cookie = $_COOKIE[AI_VISITOR_COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $cookie)) {
        if (!$create) return null;
        $cookie = bin2hex(random_bytes(16));
        $secure = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if (!headers_sent()) {
            setcookie(AI_VISITOR_COOKIE, $cookie, ['expires' => time() + 365 * 86400, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
        }
        $_COOKIE[AI_VISITOR_COOKIE] = $cookie;
    }
    $key = ai_visitor_hash($cookie);

    $stmt = db()->prepare('SELECT * FROM ai_visitors WHERE visitor_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) {
        if (!$create) return null;
        db()->prepare('INSERT IGNORE INTO ai_visitors (visitor_key) VALUES (?)')->execute([$key]);
        $stmt->execute([$key]);
        $row = $stmt->fetch();
    } elseif ($create) {
        db()->prepare('UPDATE ai_visitors SET visit_count = visit_count + IF(last_seen_at < NOW() - INTERVAL ' . AI_VISIT_GAP_MINUTES . ' MINUTE, 1, 0), last_seen_at = NOW() WHERE id = ?')
            ->execute([$row['id']]);
        $stmt->execute([$key]);
        $row = $stmt->fetch();
    }
    $visitor = $row ?: null;
    $touched = $create;
    return $visitor;
}

/** Re-reads the visitor after a change, keeping the per-request cache in step. */
function ai_visitor_reload(): ?array
{
    $v = ai_visitor(false);
    if (!$v) return null;
    $stmt = db()->prepare('SELECT * FROM ai_visitors WHERE id = ?');
    $stmt->execute([$v['id']]);
    return $stmt->fetch() ?: null;
}

function ai_visitor_has_phone(array $v): bool
{
    return !empty($v['phone']);
}

/** One more question answered (every feature counts towards the same total). */
function ai_visitor_count_question(): void
{
    $v = ai_visitor();
    if ($v) db()->prepare('UPDATE ai_visitors SET question_count = question_count + 1 WHERE id = ?')->execute([$v['id']]);
}

/** True when this endpoint must ask for a phone number instead of answering. */
function ai_visitor_gated(string $endpoint): bool
{
    if (!in_array($endpoint, AI_GATED_ENDPOINTS, true)) return false;
    $v = ai_visitor();
    return $v && !ai_visitor_has_phone($v) && (int)$v['question_count'] >= AI_PHONE_AFTER;
}

function ai_visitor_gate_message(): string
{
    return 'To keep getting answers, please share your name and phone number below — an advisor can also call you to help with anything the chat can’t answer.';
}

/** Buying-intent words (English, Hindi, Hinglish) that make the chat offer the name + phone form straight away. */
function ai_intent_wants_contact(string $message): bool
{
    return (bool)preg_match('/\b(price|prices|pricing|cost|rate|rates|quote|site\s*visit|visit|call\s*me|call\s*back|callback|call|book|booking|contact|number|whatsapp|meet|appointment|kitne|kitna|keemat|kimat|daam|dekhna|milna|baat)\b|कीमत|दाम|कॉल|विज़िट|मिलना|बात/iu', $message);
}

/** The four requirement questions shown as buttons after the 3rd answer. Each can be skipped. */
function ai_requirement_questions(): array
{
    $corridors = db()->query('SELECT id, name FROM corridors WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
    return [
        1 => ['field' => 'budget', 'text' => 'To point you to the right homes — what’s your budget?',
              'options' => ['u1' => 'Under ₹1 Cr', '1-2.5' => '₹1–2.5 Cr', '2.5-4' => '₹2.5–4 Cr', '4+' => '₹4 Cr+']],
        2 => ['field' => 'bhk', 'text' => 'How many bedrooms?',
              'options' => ['2' => '2 BHK', '3' => '3 BHK', '4' => '4 BHK', '5+' => '5 BHK+']],
        3 => ['field' => 'corridor', 'text' => 'Which area do you prefer?',
              'options' => array_column(array_map(fn($c) => ['k' => (string)$c['id'], 'v' => $c['name']], $corridors), 'v', 'k')],
        4 => ['field' => 'timeline', 'text' => 'When are you planning to buy?',
              'options' => ['3m' => 'Within 3 months', '6m' => 'In 3–6 months', '1y' => 'In 6–12 months', 'browsing' => 'Just exploring']],
    ];
}

function ai_requirement_question(int $step): ?array
{
    $all = ai_requirement_questions();
    if (!isset($all[$step])) return null;
    return ['step' => 'requirement', 'n' => $step] + $all[$step];
}

/**
 * What the chat should offer after an answer: the requirement questions (once, after the 3rd
 * answer), a name + phone form (required after the 5th answer, optional on buying intent), or nothing.
 */
function ai_visitor_next(string $message = ''): ?array
{
    $v = ai_visitor_reload();
    if (!$v || ai_visitor_has_phone($v)) return null;
    $count = (int)$v['question_count'];
    if ($count >= AI_PHONE_AFTER) {
        return ['step' => 'contact', 'required' => true, 'text' => ai_visitor_gate_message()];
    }
    if ($count >= AI_REQUIREMENT_AFTER && (int)$v['req_step'] === 0) {
        db()->prepare('UPDATE ai_visitors SET req_step = 1 WHERE id = ?')->execute([$v['id']]);
        return ai_requirement_question(1);
    }
    if ($message !== '' && ai_intent_wants_contact($message)) {
        return ['step' => 'contact', 'required' => false, 'text' => 'Want an advisor to help with this directly? Leave your name and number and they’ll call you.'];
    }
    return null;
}

/** Stores one requirement answer (null = skipped) and returns the next question, or null when done. */
function ai_visitor_save_requirement(int $step, ?string $value): ?array
{
    $v = ai_visitor();
    $question = ai_requirement_question($step);
    if (!$v || !$question) return null;
    $columns = ['budget' => 'req_budget', 'bhk' => 'req_bhk', 'corridor' => 'req_corridor_id', 'timeline' => 'req_timeline'];
    $col = $columns[$question['field']];
    $value = ($value !== null && isset($question['options'][$value])) ? $value : null;
    db()->prepare("UPDATE ai_visitors SET {$col} = ?, req_step = ? WHERE id = ?")
        ->execute([$col === 'req_corridor_id' && $value !== null ? (int)$value : $value, min($step + 1, 5), $v['id']]);
    return ai_requirement_question($step + 1);
}

/** The visitor's requirement as readable text (for the lead message and the admin log). */
function ai_visitor_requirement_text(array $v): string
{
    $q = ai_requirement_questions();
    $bits = [];
    if ($v['req_budget'] && isset($q[1]['options'][$v['req_budget']])) $bits[] = 'Budget: ' . $q[1]['options'][$v['req_budget']];
    if ($v['req_bhk'] && isset($q[2]['options'][$v['req_bhk']])) $bits[] = 'Size: ' . $q[2]['options'][$v['req_bhk']];
    if ($v['req_corridor_id'] && isset($q[3]['options'][(string)$v['req_corridor_id']])) $bits[] = 'Area: ' . $q[3]['options'][(string)$v['req_corridor_id']];
    if ($v['req_timeline'] && isset($q[4]['options'][$v['req_timeline']])) $bits[] = 'Timeline: ' . $q[4]['options'][$v['req_timeline']];
    return implode(' · ', $bits);
}

/**
 * Turns the visitor's name + phone into a lead. A lead with the same phone is reused (its owner
 * gets a call-back follow-up) instead of creating a duplicate; a new lead is auto-assigned and,
 * when the visitor gave a requirement, gets a matching saved search.
 * Returns ['ok' => bool, 'message' => visitor-facing text, 'enquiry_id' => ?int, 'existing' => bool].
 */
function ai_visitor_capture_contact(string $name, string $phone): array
{
    $v = ai_visitor();
    if (!$v) return ['ok' => false, 'message' => 'Please try again.'];
    $name = trim($name);
    $phone = trim($phone);
    if ($name === '' || mb_strlen($name) > 120) return ['ok' => false, 'message' => 'Please enter your name.'];
    if (mb_strlen($phone) > 30 || !validate_phone($phone)) return ['ok' => false, 'message' => 'Please enter a valid 10-digit phone number.'];
    $e164 = normalize_to_e164_in($phone);

    $existing = db()->prepare('SELECT id, assigned_admin_id FROM enquiries WHERE phone_e164 = ? OR phone = ? ORDER BY id DESC LIMIT 1');
    $existing->execute([$e164, $phone]);
    $existing = $existing->fetch();

    $requirement = ai_visitor_requirement_text($v);
    $lastQ = db()->prepare("SELECT input, property_id FROM ai_query_log WHERE visitor_id = ? AND input <> '' ORDER BY id DESC LIMIT 1");
    $lastQ->execute([$v['id']]);
    $lastQ = $lastQ->fetch() ?: ['input' => '', 'property_id' => null];

    if ($existing) {
        $enquiryId = (int)$existing['id'];
        $savedSearchId = null;
        if ($existing['assigned_admin_id']) {
            db()->prepare("INSERT INTO follow_ups (enquiry_id, assigned_admin_id, due_at, note, kind) VALUES (?, ?, ?, ?, 'callback')")
                ->execute([$enquiryId, $existing['assigned_admin_id'], date('Y-m-d H:i:s', time() + LEAD_FIRST_CALL_HOURS * 3600),
                           'Call back — asked questions on the website chat again']);
        } else {
            lead_auto_assign($enquiryId);
        }
    } else {
        $message = 'Website chat — ' . (int)$v['question_count'] . ' question(s) asked.'
            . ($requirement !== '' ? ' Requirement: ' . $requirement . '.' : '')
            . ($lastQ['input'] !== '' ? ' Last question: "' . mb_substr($lastQ['input'], 0, 200) . '"' : '');
        db()->prepare('INSERT INTO enquiries (name, phone, phone_e164, message, property_id, source, inquiry_at, consent_basis) VALUES (?, ?, ?, ?, ?, ?, NOW(), "inquiry")')
            ->execute([$name, $phone, $e164, $message, $lastQ['property_id'] ?: null, 'chatbot']);
        $enquiryId = (int)db()->lastInsertId();
        $savedSearchId = $requirement !== '' ? ai_visitor_create_saved_search($v, $name, $phone) : null;
        lead_auto_assign($enquiryId, $savedSearchId);
    }

    db()->prepare('UPDATE ai_visitors SET name = ?, phone = ?, phone_e164 = ?, enquiry_id = ?, saved_search_id = COALESCE(?, saved_search_id) WHERE id = ?')
        ->execute([$name, $phone, $e164, $enquiryId, $savedSearchId, $v['id']]);
    // The same person on another device or browser: link those visitors to the same lead.
    db()->prepare('UPDATE ai_visitors SET enquiry_id = ? WHERE phone_e164 = ? AND id <> ?')->execute([$enquiryId, $e164, $v['id']]);

    return ['ok' => true, 'enquiry_id' => $enquiryId, 'existing' => (bool)$existing,
            'message' => 'Thank you, ' . $name . '! An advisor will call you ' . (defined('SITE_RESPONSE_PROMISE') ? SITE_RESPONSE_PROMISE : 'soon') . '. Ask me anything else in the meantime.'];
}

function ai_visitor_create_saved_search(array $v, string $name, string $phone): int
{
    $budgets = ['u1' => [null, 10000000], '1-2.5' => [10000000, 25000000], '2.5-4' => [25000000, 40000000], '4+' => [40000000, null]];
    $timelines = ['3m' => 'now', '6m' => '6m', '1y' => '12m', 'browsing' => 'research'];
    [$min, $max] = $budgets[$v['req_budget']] ?? [null, null];
    db()->prepare('INSERT INTO saved_searches (name, phone, corridor_id, budget_min, budget_max, timeline, bhk_config, notes, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $phone, $v['req_corridor_id'] ?: null, $min, $max, $timelines[$v['req_timeline']] ?? null,
                   $v['req_bhk'] ? $v['req_bhk'] . ' BHK' : null, 'From the website chat', 'chatbot']);
    return (int)db()->lastInsertId();
}

/** The current visit's recent chatbot turns, oldest first, so a follow-up question keeps its context. */
function ai_visitor_chat_history(int $turns = 4): array
{
    $v = ai_visitor(false);
    if (!$v) return [];
    $stmt = db()->prepare("SELECT input, output FROM ai_query_log WHERE visitor_id = ? AND visit_no = ? AND endpoint = 'chatbot' AND status = 'answered' ORDER BY id DESC LIMIT " . (int)$turns);
    $stmt->execute([$v['id'], $v['visit_count']]);
    $out = [];
    foreach (array_reverse($stmt->fetchAll()) as $row) {
        $answer = json_decode((string)$row['output'], true)['answer'] ?? '';
        if ($row['input'] === '' || $answer === '') continue;
        $out[] = ['role' => 'user', 'content' => $row['input']];
        $out[] = ['role' => 'assistant', 'content' => $answer];
    }
    return $out;
}

/** Visitor state for a widget that just opened: whether it must show the phone form straight away. */
function ai_visitor_state(): array
{
    $v = ai_visitor(false);
    if (!$v) return ['questions' => 0, 'has_phone' => false, 'next' => null];
    $next = null;
    if (!ai_visitor_has_phone($v) && (int)$v['question_count'] >= AI_PHONE_AFTER) {
        $next = ['step' => 'contact', 'required' => true, 'text' => ai_visitor_gate_message()];
    } elseif ((int)$v['req_step'] >= 1 && (int)$v['req_step'] <= 4 && !ai_visitor_has_phone($v)) {
        $next = ai_requirement_question((int)$v['req_step']);
    }
    return ['questions' => (int)$v['question_count'], 'has_phone' => ai_visitor_has_phone($v), 'name' => $v['name'], 'next' => $next];
}

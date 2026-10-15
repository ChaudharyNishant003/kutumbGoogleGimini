<?php
/**
 * The chat widgets' lead-capture steps for the current visitor (kv_vid cookie):
 *   POST {action: "state"}                         → what the widget should show when it opens
 *   POST {action: "requirement", n: 1-4, value}    → saves one requirement answer (value null = skip), returns the next question
 *   POST {action: "contact", name, phone}          → creates or updates the lead, returns a thank-you line
 * Same-origin JSON only, like the AI endpoints.
 */
require_once __DIR__ . '/../includes/ai-guard.php';
require_once __DIR__ . '/../includes/site-stats.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !ai_same_origin()) {
    ai_respond(['ok' => false, 'message' => 'Invalid request.'], 403);
}
$body = ai_json_body();
if ($body === null) {
    ai_respond(['ok' => false, 'message' => 'Invalid request.'], 400);
}

switch ($body['action'] ?? '') {
    case 'state':
        ai_respond(['ok' => true] + ai_visitor_state());

    case 'requirement':
        ai_visitor();
        $n = (int)($body['n'] ?? 0);
        $value = isset($body['value']) && $body['value'] !== null ? (string)$body['value'] : null;
        $next = ai_visitor_save_requirement($n, $value);
        if ($next === null) {
            // Requirement done: the 5th-answer rule (or buying intent) decides whether to ask for a number now.
            $v = ai_visitor_reload();
            $next = ($v && !ai_visitor_has_phone($v) && (int)$v['question_count'] >= AI_PHONE_AFTER)
                ? ['step' => 'contact', 'required' => true, 'text' => ai_visitor_gate_message()]
                : null;
        }
        ai_respond(['ok' => true, 'reply' => $next === null ? 'Thanks — that helps us find the right homes for you. What else would you like to know?' : null, 'next' => $next]);

    case 'contact':
        if (!rate_limit_check('chat-contact', 5, 10)) {
            ai_respond(['ok' => false, 'message' => 'Too many attempts — please try again in a few minutes, or WhatsApp us at ' . SITE_PHONE . '.'], 429);
        }
        ai_visitor();
        $result = ai_visitor_capture_contact((string)($body['name'] ?? ''), (string)($body['phone'] ?? ''));
        unset($result['enquiry_id'], $result['existing']);
        ai_respond($result, $result['ok'] ? 200 : 422);

    default:
        ai_respond(['ok' => false, 'message' => 'Invalid request.'], 400);
}

<?php
/**
 * POST {mode: "interpret", query: "..."}  → model extracts, PHP validates + counts (metered)
 * POST {mode: "count", filters: {...}}     → re-count after a chip is removed or an option picked (no LLM, unmetered)
 */
require_once __DIR__ . '/../includes/ai-guard.php';
require_once __DIR__ . '/../includes/ai-finder.php';

$body = ai_json_body();
$mode = ($body['mode'] ?? '') === 'count' ? 'count' : 'interpret';

ai_guard_or_exit('home_finder', $mode === 'interpret');
if ($body === null) {
    ai_respond(['ok' => false, 'message' => 'Invalid request.'], 400);
}

try {
    if ($mode === 'count') {
        $f = filters_from_array(is_array($body['filters'] ?? null) ? $body['filters'] : []);
        ai_respond(finder_payload($f));
    }

    $query = trim((string)($body['query'] ?? ''));
    if ($query === '') {
        ai_respond(['ok' => true, 'state' => 'A', 'message' => 'Tell us what you’re looking for — location, size, budget or timeline.']);
    }
    if (mb_strlen($query) > AI_MAX_INPUT) {
        ai_respond(['ok' => false, 'message' => 'That’s a bit long — please keep it under ' . AI_MAX_INPUT . ' characters.'], 400);
    }

    $context = is_array($body['context'] ?? null) ? ['corridor_id' => (int)($body['context']['corridor_id'] ?? 0)] : [];
    // Counts towards the visitor's question total (search is never blocked for a phone number).
    ai_visitor();
    ai_visitor_count_question();
    $result = finder_interpret($query, $context);
    if ($result === null) {
        ai_log('home_finder', $query, null, 'error');
        ai_respond([
            'ok' => false,
            'code' => 'model_unavailable',
            'message' => 'Our smart search is unavailable right now — here are the regular filters instead.',
            'fallback_url' => '/properties.php?keyword=' . rawurlencode(mb_substr($query, 0, 60)),
        ], 502);
    }

    $raw = $result['raw'] ?? null;
    unset($result['raw']);
    $status = ($result['state'] === 'A') ? 'fallback' : 'answered';
    ai_log('home_finder', $query, ['summary' => ($result['state'] === 'A' ? 'Not understood' : finder_describe(filters_from_array($result['query'] ?? [])) . ' → ' . ($result['count'] ?? 0) . ' matches'), 'model' => $raw, 'state' => $result['state']], $status);
    ai_respond($result);
} catch (Throwable $e) {
    error_log('Home finder failure: ' . $e->getMessage());
    ai_respond(['ok' => false, 'message' => 'Something went wrong — please use the regular filters below.'], 500);
}

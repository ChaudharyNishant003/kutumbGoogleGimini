<?php
/**
 * Abuse and cost protection shared by every endpoint that can call the paid
 * LLM API: POST + same-origin only, a site-wide kill switch, a global daily
 * cap, and per-IP / per-session fixed-window limits.
 */
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/ai-visitor.php';

/** The visitor's real IP behind Railway's proxy (see client_ip() in functions.php). */
function ai_client_ip(): string
{
    return client_ip();
}

function ai_hash(string $value): string
{
    return hash('sha256', $value . '|' . (defined('DB_PASS') ? DB_PASS : '') . '|kutumb-ai');
}

function ai_same_origin(): bool
{
    $source = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($source === '' || $host === '') {
        return false;
    }
    $sourceHost = parse_url($source, PHP_URL_HOST);
    $sourcePort = parse_url($source, PHP_URL_PORT);
    $sourceAuthority = $sourceHost . ($sourcePort ? ':' . $sourcePort : '');
    return is_string($sourceHost) && (strcasecmp($sourceAuthority, $host) === 0 || strcasecmp($sourceHost, $host) === 0);
}

/** Increments a fixed-window counter and returns the new count. */
function ai_bump(string $subjectHash, string $bucket, string $windowKind): int
{
    $windowStart = $windowKind === 'day' ? date('Y-m-d 00:00:00') : date('Y-m-d H:i:00', (int)(floor(time() / 600) * 600));
    db()->prepare(
        'INSERT INTO ai_rate_limits (subject_hash, endpoint, window_kind, window_start, count) VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE count = count + 1'
    )->execute([$subjectHash, $bucket, $windowKind, $windowStart]);
    $stmt = db()->prepare('SELECT count FROM ai_rate_limits WHERE subject_hash = ? AND endpoint = ? AND window_kind = ? AND window_start = ?');
    $stmt->execute([$subjectHash, $bucket, $windowKind, $windowStart]);
    return (int)$stmt->fetchColumn();
}

/**
 * Runs every guard. Returns null when the request may proceed, otherwise a
 * ['status' => HTTP code, 'message' => visitor-facing text, 'code' => reason].
 * $metered = false for calls that never reach the LLM (e.g. re-counting chips).
 */
function ai_guard(string $endpoint, bool $metered = true): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['status' => 405, 'code' => 'method', 'message' => 'Invalid request.'];
    }
    if (!ai_same_origin()) {
        return ['status' => 403, 'code' => 'origin', 'message' => 'Invalid request.'];
    }
    $settings = ai_settings();
    if (!$settings['enabled']) {
        return ['status' => 503, 'code' => 'disabled', 'message' => 'Our AI assistant is switched off right now. WhatsApp us at ' . SITE_PHONE . ' and an advisor will help directly.'];
    }
    if (!$metered) {
        return null;
    }
    if (!ai_key_configured()) {
        return ['status' => 503, 'code' => 'no_key', 'message' => "Our AI assistant isn't set up yet. WhatsApp us at " . SITE_PHONE . ' and an advisor will help directly.'];
    }

    try {
        if (mt_rand(1, 50) === 1) {
            db()->exec('DELETE FROM ai_rate_limits WHERE window_start < (NOW() - INTERVAL 2 DAY)');
        }
        $limited = ['status' => 429, 'code' => 'limited', 'message' => "You've asked a lot of questions in a short time. Please try again a little later, or WhatsApp us at " . SITE_PHONE . ' for a quick answer.'];

        if (ai_bump('global', 'all', 'day') > $settings['global_daily_cap']) {
            return $limited;
        }
        $subjects = [ai_hash('ip:' . ai_client_ip())];
        if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
            $subjects[] = ai_hash('session:' . session_id());
        }
        foreach ($subjects as $subject) {
            $tenMin = ai_bump($subject, 'all', '10min');
            $day = ai_bump($subject, 'all', 'day');
            if ($tenMin > $settings['limit_10min'] || $day > $settings['limit_day']) {
                return $limited;
            }
        }
    } catch (Throwable $e) {
        error_log('AI guard failure: ' . $e->getMessage());
        return ['status' => 503, 'code' => 'guard_error', 'message' => 'Our AI assistant is unavailable right now. WhatsApp us at ' . SITE_PHONE . '.'];
    }
    return null;
}

function ai_log(string $endpoint, string $input, $output, string $status, ?int $propertyId = null): void
{
    try {
        // Every question belongs to the visitor (and visit) who asked it, so the admin log can group them.
        $visitor = ai_visitor(false);
        db()->prepare('INSERT INTO ai_query_log (visitor_id, visit_no, endpoint, property_id, input, output, status, ip_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $visitor ? (int)$visitor['id'] : null,
                $visitor ? (int)$visitor['visit_count'] : null,
                $endpoint,
                $propertyId,
                mb_substr($input, 0, 500),
                is_string($output) ? $output : json_encode($output, JSON_UNESCAPED_UNICODE),
                $status,
                ai_hash('ip:' . ai_client_ip()),
            ]);
    } catch (Throwable $e) {
        error_log('AI log failure: ' . $e->getMessage());
    }
}

/** Reads the JSON body; null if it isn't a JSON object. */
function ai_json_body(): ?array
{
    $body = json_decode((string)file_get_contents('php://input'), true);
    return is_array($body) ? $body : null;
}

function ai_respond(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Stops the request with the guard's JSON error if any guard failed. */
function ai_guard_or_exit(string $endpoint, bool $metered = true): void
{
    $block = ai_guard($endpoint, $metered);
    if ($block) {
        if ($block['code'] === 'limited') {
            ai_log($endpoint, '', null, 'limited');
        }
        ai_respond(['ok' => false, 'code' => $block['code'], 'message' => $block['message'], 'whatsapp' => html_entity_decode(whatsapp_url(), ENT_QUOTES)], $block['status']);
    }
}

<?php
/**
 * OpenAI access for the AI features. The model is only ever asked to classify
 * or extract into a strict JSON schema — facts shown to visitors always come
 * from PHP/MySQL, never from model text.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/** Hard cap on visitor text sent to the model (brief §6). */
const AI_MAX_INPUT = 300;

/** AI settings row, or safe "disabled" defaults when the AI migration hasn't been applied yet. */
function ai_settings(): array
{
    static $settings = null;
    if ($settings === null) {
        try {
            $row = db()->query('SELECT ai_enabled, ai_limit_10min, ai_limit_day, ai_global_daily_cap FROM app_settings WHERE id = 1')->fetch();
        } catch (Throwable $e) {
            $row = false;
        }
        $settings = $row ? [
            'enabled' => (bool)$row['ai_enabled'],
            'limit_10min' => max(1, (int)$row['ai_limit_10min']),
            'limit_day' => max(1, (int)$row['ai_limit_day']),
            'global_daily_cap' => max(1, (int)$row['ai_global_daily_cap']),
        ] : ['enabled' => false, 'limit_10min' => 20, 'limit_day' => 100, 'global_daily_cap' => 1500];
    }
    return $settings;
}

/**
 * Calls chat completions with a strict JSON schema and returns the decoded
 * object, or null on any failure (no key, network, HTTP error, bad JSON).
 */
function ai_structured(string $system, string $user, string $schemaName, array $schema, int $maxTokens = 250): ?array
{
    $apiKey = get_openai_key();
    if ($apiKey === '') {
        return null;
    }
    $payload = [
        'model' => OPENAI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
        'max_tokens' => $maxTokens,
        'temperature' => 0,
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => ['name' => $schemaName, 'strict' => true, 'schema' => $schema],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($curlError || $httpCode !== 200) {
        error_log('OpenAI ' . $schemaName . ' error: ' . $curlError . ' HTTP ' . $httpCode . ' body: ' . substr((string)$response, 0, 500));
        return null;
    }
    $data = json_decode((string)$response, true);
    $message = $data['choices'][0]['message'] ?? [];
    if (!empty($message['refusal']) || empty($message['content'])) {
        return null;
    }
    $parsed = json_decode($message['content'], true);
    return is_array($parsed) ? $parsed : null;
}

function ai_key_configured(): bool
{
    try {
        return get_openai_key() !== '';
    } catch (Throwable $e) {
        return false;
    }
}

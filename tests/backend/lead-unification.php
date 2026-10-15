<?php
// Saved-search / consultation submissions must also create ONE enquiry, record whether
// the visitor asked to be called, and never queue an AI call (the voice agent is
// parked on the ai-voice-agent branch).
// Local run:  php tests/backend/lead-unification.php [base_url]   (default http://localhost:8000)
// Uses the DB from config/config.php; cleans up the rows it creates (phone 99999009xx).

require __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../includes/functions.php';

// The endpoint is rate-limited per IP; clear this test's own recent hits first (local/test DB only).
db()->exec("DELETE FROM form_submission_log WHERE endpoint = 'saved-search-submit' AND created_at > (NOW() - INTERVAL 30 MINUTE)");

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$fail = 0;
function check(string $id, string $title, bool $ok, string $detail): void
{
    global $fail;
    if (!$ok) $fail++;
    echo ($ok ? 'PASS ' : 'FAIL ') . "$id: $title — $detail\n";
}
function submit(string $base, array $fields): array
{
    $ch = curl_init("$base/api/saved-search-submit.php");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true]);
    $body = curl_exec($ch);
    return json_decode((string)$body, true) ?: [];
}
function enquiry(string $phone): ?array
{
    $st = db()->prepare('SELECT id, consent_basis, source, message FROM enquiries WHERE phone = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$phone]);
    return $st->fetch() ?: null;
}
function scanJobs(int $enquiryId): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM ai_jobs WHERE type = 'eligibility_scan' AND payload_json LIKE ?");
    $st->execute(['%"enquiry_id":' . $enquiryId . '%']);
    return (int)$st->fetchColumn();
}

$cases = [
    ['9999900901', ['source' => 'saved-searches-page'], 'none'],
    ['9999900902', ['source' => 'saved-searches-page', 'call_ok' => '1'], 'inquiry'],
    ['9999900903', ['source' => 'why-an-advisor'], 'inquiry'],
];
foreach ($cases as $i => [$phone, $extra, $expectConsent]) {
    $res = submit($base, ['name' => 'QA Unify', 'phone' => $phone, 'timeline' => 'now'] + $extra);
    $enq = enquiry($phone);
    $n = $i + 1;
    check("QF-LEAD-10$n", "submit ok and enquiry created ($phone)", !empty($res['ok']) && $enq !== null, $enq ? "enquiry #{$enq['id']}" : 'no enquiry row');
    if ($enq) {
        check("QF-LEAD-11$n", "consent_basis = $expectConsent", $enq['consent_basis'] === $expectConsent, "got {$enq['consent_basis']}");
        check("QF-LEAD-12$n", 'no AI call queued', scanJobs((int)$enq['id']) === 0, 'scan jobs: ' . scanJobs((int)$enq['id']));
        check("QF-LEAD-13$n", 'message links the saved search', str_contains((string)$enq['message'], 'Saved search #'), substr((string)$enq['message'], 0, 60));
    }
}

// Campaign context from the forms' hidden field is kept (clean values only) on the enquiry message.
$res = submit($base, ['name' => 'QA Unify', 'phone' => '9999900904', 'source' => 'saved-searches-page', 'campaign' => 'google/cpc/diwali-2026']);
$enq = enquiry('9999900904');
check('QF-LEAD-141', 'campaign tag stored on the enquiry', $enq && str_contains((string)$enq['message'], 'Campaign: google/cpc/diwali-2026'), $enq ? substr((string)$enq['message'], 0, 80) : 'no row');
submit($base, ['name' => 'QA Unify', 'phone' => '9999900905', 'source' => 'saved-searches-page', 'campaign' => '<script>alert(1)</script>']);
$enq = enquiry('9999900905');
check('QF-LEAD-142', 'unsafe campaign value is dropped', $enq && !str_contains((string)$enq['message'], 'script'), $enq ? substr((string)$enq['message'], 0, 80) : 'no row');

db()->exec("DELETE FROM form_submission_log WHERE endpoint = 'saved-search-submit' AND created_at > (NOW() - INTERVAL 30 MINUTE)");
foreach (['9999900901', '9999900902', '9999900903', '9999900904', '9999900905'] as $phone) {
    $enq = enquiry($phone);
    if ($enq) {
        db()->prepare("DELETE FROM ai_jobs WHERE type = 'eligibility_scan' AND payload_json LIKE ?")->execute(['%"enquiry_id":' . $enq['id'] . '%']);
    }
    db()->prepare('DELETE FROM enquiries WHERE phone = ?')->execute([$phone]);
    db()->prepare('DELETE FROM saved_searches WHERE phone = ?')->execute([$phone]);
}
echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit($fail === 0 ? 0 : 1);

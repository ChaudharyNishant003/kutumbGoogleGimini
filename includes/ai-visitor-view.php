<?php
// Admin rendering of website AI conversations, grouped by visit — used by the visitor page
// (admin/ai-visitor.php) and the "Website chats" card on the lead page.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function ai_feature_labels(): array
{
    return ['chatbot' => 'Asha chatbot', 'assistant' => 'Project Assistant', 'home_finder' => 'Home Finder', 'neighborhood' => 'Neighbourhood'];
}

function ai_status_labels(): array
{
    return ['answered' => 'Answered', 'fallback' => 'No answer on record', 'refused' => 'Refused (subjective)', 'limited' => 'Rate-limited', 'error' => 'Error', 'gated' => 'Asked for phone'];
}

/** What the visitor was shown: the full answer when stored, else the one-line summary. */
function ai_log_answer(?string $output): string
{
    if ($output === null || $output === '') return '—';
    $data = json_decode($output, true);
    if (!is_array($data)) return excerpt($output, 300);
    return (string)($data['answer'] ?? $data['summary'] ?? '—');
}

/** All logged questions of these visitors, grouped into visits (newest visit first). */
function ai_conversation_visits(array $visitorIds): array
{
    $visitorIds = array_values(array_filter(array_map('intval', $visitorIds)));
    if (!$visitorIds) return [];
    $in = implode(',', array_fill(0, count($visitorIds), '?'));
    $stmt = db()->prepare("SELECT l.*, p.name AS property_name FROM ai_query_log l LEFT JOIN properties p ON p.id = l.property_id
                           WHERE l.visitor_id IN ($in) ORDER BY l.created_at, l.id");
    $stmt->execute($visitorIds);
    $visits = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = $row['visitor_id'] . ':' . $row['visit_no'];
        $visits[$key] ??= ['started' => $row['created_at'], 'rows' => []];
        $visits[$key]['rows'][] = $row;
    }
    uasort($visits, fn($a, $b) => strcmp($b['started'], $a['started']));
    return array_values($visits);
}

function render_ai_conversation(array $visitorIds): string
{
    $visits = ai_conversation_visits($visitorIds);
    if (!$visits) return '<p style="color:var(--ink-soft); font-size:13px;">No questions logged yet.</p>';
    $features = ai_feature_labels();
    $statuses = ai_status_labels();
    $total = count($visits);
    ob_start();
    foreach ($visits as $i => $visit): ?>
      <div class="ai-visit">
        <h5 class="ai-visit-head">Visit <?= $total - $i ?> — <?= e(date('d M Y, h:i A', strtotime($visit['started']))) ?> · <?= count($visit['rows']) ?> question<?= count($visit['rows']) === 1 ? '' : 's' ?></h5>
        <?php foreach ($visit['rows'] as $r): ?>
          <div class="ai-turn<?= in_array($r['status'], ['fallback', 'refused'], true) ? ' ai-gap' : '' ?>">
            <div class="ai-turn-meta"><?= e(date('h:i A', strtotime($r['created_at']))) ?> · <?= e($features[$r['endpoint']] ?? $r['endpoint']) ?><?= $r['property_name'] ? ' · ' . e($r['property_name']) : '' ?>
              <span class="ai-status ai-status-<?= e($r['status']) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></div>
            <div class="ai-turn-q"><strong>Q:</strong> <?= e($r['input'] !== '' ? $r['input'] : '—') ?></div>
            <div class="ai-turn-a"><strong>A:</strong> <?= nl2br(e(ai_log_answer($r['output']))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach;
    return ob_get_clean();
}

<?php
// admin-header.php is required after the redirect below (see property-form.php).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ai-visitor.php';
require_once __DIR__ . '/../includes/ai-visitor-view.php';
require_role('admin');

$endpoints = ['home_finder' => 'Home Finder', 'neighborhood' => 'Neighbourhood', 'assistant' => 'Project Assistant', 'chatbot' => 'Asha chatbot'];
$statuses = ai_status_labels();
// Default view: one row per visitor (all their visits and features together); 'questions' = every question as its own row.
$view = ($_GET['view'] ?? '') === 'questions' ? 'questions' : 'visitors';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'purge') {
    if (csrf_check($_POST['csrf_token'] ?? null) && (current_admin()['role'] ?? '') === 'owner') {
        db()->exec('DELETE FROM ai_query_log WHERE created_at < (NOW() - INTERVAL 365 DAY)');
        db()->exec('DELETE FROM ai_visitors WHERE enquiry_id IS NULL AND last_seen_at < (NOW() - INTERVAL 365 DAY)');
        header('Location: /admin/ai-log.php?purged=1');
        exit;
    }
    header('Location: /admin/ai-log.php');
    exit;
}

$endpoint = $_GET['endpoint'] ?? '';
$status = $_GET['status'] ?? '';
if (!isset($endpoints[$endpoint])) $endpoint = '';
if (!isset($statuses[$status]) && $status !== 'gaps') $status = '';

$where = ' WHERE 1=1';
$params = [];
if ($endpoint !== '') { $where .= ' AND l.endpoint = ?'; $params[] = $endpoint; }
if ($status === 'gaps') { $where .= " AND l.status IN ('fallback','refused')"; }
elseif ($status !== '') { $where .= ' AND l.status = ?'; $params[] = $status; }

$countStmt = db()->prepare('SELECT COUNT(*) FROM ai_query_log l' . $where);
$countStmt->execute($params);
$pg = paginate_params((int)$countStmt->fetchColumn(), 25, [25, 50, 100]);

$stmt = db()->prepare('SELECT l.*, p.name AS property_name FROM ai_query_log l LEFT JOIN properties p ON p.id = l.property_id' . $where
    . ' ORDER BY l.id DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ---- By visitor: visitors who left the same phone number are one row (their lead). ----
$who = $_GET['who'] ?? '';
$search = trim($_GET['q'] ?? '');
$vWhere = ' WHERE v.question_count > 0';
$vParams = [];
if ($who === 'phone') $vWhere .= ' AND v.phone IS NOT NULL';
if ($who === 'anon') $vWhere .= ' AND v.phone IS NULL';
if ($search !== '') { $vWhere .= ' AND (v.name LIKE ? OR v.phone LIKE ?)'; $vParams[] = '%' . $search . '%'; $vParams[] = '%' . $search . '%'; }
$groupKey = "COALESCE(CONCAT('L', v.enquiry_id), CONCAT('V', v.id))";
$visitors = [];
$vpg = null;
if ($view === 'visitors') {
    $c = db()->prepare("SELECT COUNT(DISTINCT $groupKey) FROM ai_visitors v" . $vWhere);
    $c->execute($vParams);
    $vpg = paginate_params((int)$c->fetchColumn(), 25, [25, 50, 100]);
    $vs = db()->prepare(
        "SELECT MIN(v.id) AS id, MAX(v.name) AS name, MAX(v.phone) AS phone, MAX(v.enquiry_id) AS enquiry_id, MIN(v.first_seen_at) AS first_seen,
                MAX(v.last_seen_at) AS last_seen, SUM(v.visit_count) AS visits, SUM(v.question_count) AS questions, COUNT(*) AS devices,
                MAX(v.req_budget) AS req_budget, MAX(v.req_bhk) AS req_bhk, MAX(v.req_corridor_id) AS req_corridor_id, MAX(v.req_timeline) AS req_timeline,
                GROUP_CONCAT(v.id) AS visitor_ids
         FROM ai_visitors v" . $vWhere . " GROUP BY $groupKey ORDER BY last_seen DESC LIMIT " . (int)$vpg['per_page'] . ' OFFSET ' . (int)$vpg['offset']);
    $vs->execute($vParams);
    $visitors = $vs->fetchAll();
    foreach ($visitors as &$v) {
        $ids = array_map('intval', explode(',', $v['visitor_ids']));
        $in = implode(',', $ids);
        $v['features'] = db()->query("SELECT DISTINCT endpoint FROM ai_query_log WHERE visitor_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
        $v['gaps'] = (int)db()->query("SELECT COUNT(*) FROM ai_query_log WHERE visitor_id IN ($in) AND status IN ('fallback','refused')")->fetchColumn();
        $last = db()->query("SELECT input FROM ai_query_log WHERE visitor_id IN ($in) AND input <> '' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $v['last_question'] = $last ?: '';
    }
    unset($v);
}

$stats = db()->query("SELECT status, COUNT(*) n FROM ai_query_log WHERE created_at >= (NOW() - INTERVAL 7 DAY) GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

function ai_log_summary(?string $output): string
{
    if ($output === null || $output === '') return '—';
    $data = json_decode($output, true);
    if (!is_array($data)) return excerpt($output, 140);
    if (isset($data['answer'])) return excerpt((string)$data['answer'], 200);
    if (isset($data['summary'])) return (string)$data['summary'];
    return excerpt(json_encode($data, JSON_UNESCAPED_UNICODE), 140);
}

$page_title = 'AI Question Log — Kutumb Admin';
require_once __DIR__ . '/../includes/admin-header.php';
$query = array_filter(['endpoint' => $endpoint, 'status' => $status], fn($v) => $v !== '');
?>

<div class="admin-topbar">
  <h1>AI Question Log</h1>
  <div class="admin-topbar-actions">
    <div class="pill-row" style="margin:0;">
      <a class="pill<?= $view === 'visitors' ? ' active' : '' ?>" href="/admin/ai-log.php">By visitor</a>
      <a class="pill<?= $view === 'questions' ? ' active' : '' ?>" href="/admin/ai-log.php?view=questions">All questions</a>
    </div>
    <?php if ($view === 'visitors'): ?>
    <form method="get" class="admin-search" style="gap:8px;">
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Name or phone...">
      <select name="who" onchange="this.form.submit()" aria-label="Visitors">
        <option value="">All visitors</option>
        <option value="phone" <?= $who === 'phone' ? 'selected' : '' ?>>Gave a phone number</option>
        <option value="anon" <?= $who === 'anon' ? 'selected' : '' ?>>Anonymous</option>
      </select>
    </form>
    <?php else: ?>
    <form method="get" class="admin-search" style="gap:8px;">
      <input type="hidden" name="view" value="questions">
      <select name="endpoint" onchange="this.form.submit()" aria-label="Feature">
        <option value="">All features</option>
        <?php foreach ($endpoints as $k => $label): ?><option value="<?= e($k) ?>" <?= $endpoint === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <select name="status" onchange="this.form.submit()" aria-label="Result">
        <option value="">All results</option>
        <option value="gaps" <?= $status === 'gaps' ? 'selected' : '' ?>>Data gaps (not answered)</option>
        <?php foreach ($statuses as $k => $label): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <noscript><button type="submit" class="btn btn-soft btn-sm">Filter</button></noscript>
    </form>
    <?php endif; ?>
  </div>
</div>

<p style="color:var(--ink-soft); font-size:13.5px; margin:-8px 0 16px; max-width:820px;">
  Every question visitors ask the chatbot and the AI features, with the answer they got. Each visitor is one row, however many times they come back (same browser); once they leave a phone number in the chat they also become a lead.
  Answers marked <span class="ai-status ai-status-fallback">No answer on record</span> are data gaps — add the missing FAQ, possession date or nearby place to close them.
  Visitor IPs and browser IDs are stored only as an irreversible hash.
</p>
<?php if (isset($_GET['purged'])): ?><div class="form-note success">Entries older than 1 year deleted.</div><?php endif; ?>

<div class="pill-row" style="margin:0 0 16px;">
  <?php foreach ($statuses as $k => $label): ?>
    <span class="pill">Last 7 days — <?= e($label) ?>: <strong><?= (int)($stats[$k] ?? 0) ?></strong></span>
  <?php endforeach; ?>
</div>

<?php if ($view === 'visitors'): ?>
<div class="admin-card">
  <?php if (!$visitors): ?>
    <p style="color:var(--ink-soft)">No visitors<?= ($who !== '' || $search !== '') ? ' for these filters' : ' have used the chat or AI features yet' ?>.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Visitor</th><th>Last seen</th><th>Visits</th><th>Questions</th><th>Requirement</th><th>Last question</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($visitors as $v): ?>
          <tr>
            <td data-label="Visitor">
              <a href="/admin/ai-visitor.php?id=<?= (int)$v['id'] ?>"><strong><?= e($v['name'] ?: 'Anonymous #' . (int)$v['id']) ?></strong></a>
              <?php if ($v['phone']): ?><br><a href="tel:<?= e($v['phone']) ?>"><?= e($v['phone']) ?></a><?php endif; ?>
              <?php if ($v['enquiry_id']): ?><br><a href="/admin/enquiry-view.php?id=<?= (int)$v['enquiry_id'] ?>">Lead #<?= (int)$v['enquiry_id'] ?></a><?php endif; ?>
            </td>
            <td data-label="Last seen" style="white-space:nowrap;"><?= e(date('d M, h:i A', strtotime($v['last_seen']))) ?></td>
            <td data-label="Visits"><?= (int)$v['visits'] ?></td>
            <td data-label="Questions"><?= (int)$v['questions'] ?><?= $v['gaps'] ? ' <span class="ai-status ai-status-fallback">' . (int)$v['gaps'] . ' gap' . ($v['gaps'] === 1 ? '' : 's') . '</span>' : '' ?>
              <div style="font-size:12px; color:var(--ink-soft);"><?= e(implode(', ', array_map(fn($f) => ai_feature_labels()[$f] ?? $f, $v['features']))) ?></div></td>
            <td data-label="Requirement" style="font-size:13px; max-width:220px;"><?= e(ai_visitor_requirement_text($v) ?: '—') ?></td>
            <td data-label="Last question" style="font-size:13px; max-width:260px;"><?= e(excerpt($v['last_question'], 90) ?: '—') ?></td>
            <td><a href="/admin/ai-visitor.php?id=<?= (int)$v['id'] ?>" class="btn btn-soft btn-sm">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <?= render_pagination($vpg, '/admin/ai-log.php', array_filter(['who' => $who, 'q' => $search], fn($x) => $x !== '')) ?>
</div>
<?php else: ?>
<div class="admin-card">
  <?php if (!$rows): ?>
    <p style="color:var(--ink-soft)">No questions logged<?= $query ? ' for these filters' : ' yet' ?>.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>When</th><th>Feature</th><th>Property</th><th>Question</th><th>Result</th><th>What was shown</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $gap = in_array($r['status'], ['fallback', 'refused'], true); ?>
          <tr class="<?= $gap ? 'ai-gap' : '' ?>">
            <td data-label="When" style="white-space:nowrap;"><?= e(date('d M, h:i A', strtotime($r['created_at']))) ?></td>
            <td data-label="Feature"><?= e($endpoints[$r['endpoint']] ?? $r['endpoint']) ?></td>
            <td data-label="Property"><?php if ($r['property_id']): ?><a href="/admin/property-faqs.php?property_id=<?= (int)$r['property_id'] ?>"><?= e($r['property_name'] ?? ('#' . $r['property_id'])) ?></a><?php else: ?>—<?php endif; ?></td>
            <td data-label="Question" style="max-width:280px;"><?= $r['input'] !== '' ? e($r['input']) : '<span style="color:var(--ink-soft)">—</span>' ?></td>
            <td data-label="Result"><span class="ai-status ai-status-<?= e($r['status']) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
            <td data-label="What was shown" style="max-width:320px; font-size:13px; color:var(--ink-soft);"><?= e(ai_log_summary($r['output'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <?= render_pagination($pg, '/admin/ai-log.php', ['view' => 'questions'] + $query) ?>
</div>
<?php endif; ?>

<?php if ((current_admin()['role'] ?? '') === 'owner'): ?>
<form method="post" style="margin-top:8px;" onsubmit="return confirm('Delete AI log entries older than 1 year? This cannot be undone.');">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="purge">
  <button type="submit" class="btn btn-soft btn-sm">Delete entries older than 1 year</button>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

<?php
$page_title = 'Saved Searches — Kutumb Admin';
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/leads.php';

$statusOptions = saved_search_status_options();
$searchQuery = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$conditions = [];
$params = [];
if ($searchQuery !== '') {
    $conditions[] = 's.name LIKE ?';
    $params[] = '%' . $searchQuery . '%';
}
if ($statusFilter !== '' && isset($statusOptions[$statusFilter])) {
    $conditions[] = 's.status = ?';
    $params[] = $statusFilter;
}
$whereSql = ' WHERE 1=1' . ($conditions ? ' AND ' . implode(' AND ', $conditions) : '') . lead_scope_sql('s.assigned_admin_id');
$canAssign = admin_role_at_least('admin');

$countStmt = db()->prepare('SELECT COUNT(*) FROM saved_searches s' . $whereSql);
$countStmt->execute($params);
$pg = paginate_params_grid((int)$countStmt->fetchColumn());

$stmt = db()->prepare(
    'SELECT s.*, c.name AS corridor_name, a.username AS assigned_username, a.full_name AS assigned_full_name
     FROM saved_searches s
     LEFT JOIN corridors c ON c.id = s.corridor_id
     LEFT JOIN admin_users a ON a.id = s.assigned_admin_id'
    . $whereSql . ' ORDER BY s.created_at DESC'
    . ' LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']
);
$stmt->execute($params);
$searches = $stmt->fetchAll();

$admins = db()->query('SELECT id, username, full_name FROM admin_users WHERE active = 1 ORDER BY COALESCE(full_name, username)')->fetchAll();

$followUpCounts = db()->query(
    'SELECT saved_search_id, COUNT(*) AS cnt FROM follow_ups WHERE completed = 0 AND saved_search_id IS NOT NULL GROUP BY saved_search_id'
)->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="admin-topbar">
  <h1><?= admin_role_at_least('admin') ? 'Saved Searches' : 'My Saved Searches' ?></h1>
  <div class="admin-topbar-actions">
    <form method="get" class="admin-search">
      <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by name...">
      <?php if ($searchQuery !== ''): ?><a href="/admin/saved-searches.php<?= $statusFilter !== '' ? '?status=' . e($statusFilter) : '' ?>" class="admin-search-clear">Clear</a><?php endif; ?>
      <select name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <?php foreach ($statusOptions as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= $statusFilter === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<div class="admin-card">
  <?php if (!$searches && $searchQuery !== ''): ?>
    <p style="color:var(--ink-soft)">No saved searches match "<?= e($searchQuery) ?>".</p>
  <?php elseif (!$searches): ?>
    <p style="color:var(--ink-soft)">No saved searches yet.</p>
  <?php else: ?>
    <div class="admin-card-grid">
      <?php foreach ($searches as $s): $matches = saved_search_match_count($s);
        ob_start(); ?><span class="label">Status</span>
        <form method="post" action="/admin/saved-search-update.php" class="inline-form js-lead-update-form">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="assigned_admin_id" value="<?= (int)($s['assigned_admin_id'] ?? 0) ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <select name="status" class="js-lead-update-field">
            <?php foreach ($statusOptions as $val => $label): ?>
              <option value="<?= e($val) ?>" <?= $s['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php $statusRow = ob_get_clean();

        ob_start(); ?><span class="label">Assigned</span>
        <?php if (!$canAssign): ?><span class="value"><?= e($s['assigned_full_name'] ?: ($s['assigned_username'] ?? 'Unassigned')) ?></span><?php else: ?>
        <form method="post" action="/admin/saved-search-update.php" class="inline-form js-lead-update-form">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="status" value="<?= e($s['status']) ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <select name="assigned_admin_id" class="js-lead-update-field">
            <option value="">Unassigned</option>
            <?php foreach ($admins as $a): ?>
              <option value="<?= (int)$a['id'] ?>" <?= (int)($s['assigned_admin_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['full_name'] ?: $a['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>
        <?php $assignedRow = ob_get_clean();

        ob_start(); ?>
        <button type="button" class="btn-link-followup js-followup-open" data-modal="followup-modal-ss-<?= (int)$s['id'] ?>">
          Follow-up<?= isset($followUpCounts[$s['id']]) ? ' (' . (int)$followUpCounts[$s['id']] . ')' : '' ?>
        </button>
        <dialog class="followup-modal" id="followup-modal-ss-<?= (int)$s['id'] ?>">
          <h4>Add Follow-up</h4>
          <form method="post" action="/admin/follow-up-add.php">
            <input type="hidden" name="saved_search_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-field"><label for="ss-due-<?= (int)$s['id'] ?>">Due Date</label><input type="datetime-local" id="ss-due-<?= (int)$s['id'] ?>" name="due_at" required></div>
            <div class="form-field"><label for="ss-note-<?= (int)$s['id'] ?>">Note</label><input type="text" id="ss-note-<?= (int)$s['id'] ?>" name="note" placeholder="e.g. Call with matching options"></div>
            <div class="followup-modal-actions">
              <button type="button" class="btn btn-navy btn-sm js-followup-close">Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm">Add</button>
            </div>
          </form>
          <?php if ($s['notes']): ?><p class="lead-message"><?= e($s['notes']) ?></p><?php endif; ?>
        </dialog>
        <?php $footer = ob_get_clean();

        echo render_admin_data_card([
            'title' => $s['name'],
            'subtitle' => date('d M Y, h:i A', strtotime($s['created_at'])),
            'badges' => [
                '<span class="admin-data-card-badge ' . ($matches > 0 ? 'active' : '') . '">' . $matches . ' listing' . ($matches === 1 ? '' : 's') . '</span>',
                $s['source'] ? '<span class="admin-data-card-badge">' . e($s['source']) . '</span>' : null,
            ],
            'rows' => [
                '<span class="label">Phone</span><a class="value" href="tel:' . e($s['phone']) . '">' . e($s['phone']) . '</a>',
                '<span class="label">Criteria</span><span class="value">' . e($s['corridor_name'] ?? 'Any corridor') . ' · ' . e($s['category'] ? category_label($s['category']) : 'Any category') . '</span>',
                ($s['budget_min'] || $s['budget_max']) ? '<span class="label">Budget</span><span class="value">' . e(format_inr((float)($s['budget_min'] ?? 0))) . '–' . ($s['budget_max'] ? e(format_inr((float)$s['budget_max'])) : '+') . '</span>' : null,
                saved_search_timeline_label($s['timeline']) ? '<span class="label">Timeline</span><span class="value">' . e(saved_search_timeline_label($s['timeline'])) . '</span>' : null,
                $statusRow,
                $assignedRow,
            ],
            'footer' => $footer,
        ]);
      endforeach; ?>
    </div>
  <?php endif; ?>
  <?php
    $paginationParams = [];
    if ($searchQuery !== '') $paginationParams['q'] = $searchQuery;
    if ($statusFilter !== '') $paginationParams['status'] = $statusFilter;
  ?>
  <?= render_pagination($pg, '/admin/saved-searches.php', $paginationParams) ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

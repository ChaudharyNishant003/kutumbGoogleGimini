<?php
$page_title = 'Leads — Kutumb Admin';
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/leads.php';

$statusOptions = lead_status_options();
$statusFilter = $_GET['status'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');
// category/corridor are reachable only via the Dashboard widget click-throughs
// (7b/7c) — no dropdown UI for them here, just a "Filtered by" indicator with
// a way to clear it, since arriving pre-filtered from elsewhere still needs
// to be legible.
$categoryFilter = $_GET['category'] ?? '';
$corridorFilter = $_GET['corridor'] ?? '';
$unassignedOnly = ($_GET['assigned'] ?? '') === 'none';

$conditions = [];
$params = [];
if ($statusFilter !== '' && isset($statusOptions[$statusFilter])) {
    $conditions[] = 'e.status = ?';
    $params[] = $statusFilter;
}
if ($searchQuery !== '') {
    $conditions[] = 'e.name LIKE ?';
    $params[] = '%' . $searchQuery . '%';
}
if ($categoryFilter === 'none') {
    $conditions[] = 'e.property_id IS NULL';
} elseif ($categoryFilter !== '') {
    $conditions[] = 'p.category = ?';
    $params[] = $categoryFilter;
}
if ($corridorFilter === 'none') {
    $conditions[] = '(e.property_id IS NULL OR p.corridor_id IS NULL)';
} elseif ($corridorFilter !== '') {
    $conditions[] = 'c.name = ?';
    $params[] = $corridorFilter;
}
if ($unassignedOnly) {
    $conditions[] = 'e.assigned_admin_id IS NULL';
}
$whereSql = ' WHERE 1=1' . ($conditions ? ' AND ' . implode(' AND ', $conditions) : '') . lead_scope_sql('e.assigned_admin_id');
$canAssign = admin_role_at_least('admin');

$countStmt = db()->prepare(
    'SELECT COUNT(*) FROM enquiries e
     LEFT JOIN properties p ON p.id = e.property_id
     LEFT JOIN corridors c ON c.id = p.corridor_id' . $whereSql
);
$countStmt->execute($params);
$pg = paginate_params_grid((int)$countStmt->fetchColumn());

$sql = 'SELECT e.*, p.name AS property_name, p.price_min, p.featured,
               a.username AS assigned_username, a.full_name AS assigned_full_name
        FROM enquiries e
        LEFT JOIN properties p ON p.id = e.property_id
        LEFT JOIN corridors c ON c.id = p.corridor_id
        LEFT JOIN admin_users a ON a.id = e.assigned_admin_id'
    . $whereSql
    . ' ORDER BY e.created_at DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset'];
$stmt = db()->prepare($sql);
$stmt->execute($params);
$enquiries = $stmt->fetchAll();

$admins = db()->query('SELECT id, username, full_name FROM admin_users WHERE active = 1 ORDER BY COALESCE(full_name, username)')->fetchAll();

$followUpCounts = db()->query(
    'SELECT enquiry_id, COUNT(*) AS cnt FROM follow_ups WHERE completed = 0 GROUP BY enquiry_id'
)->fetchAll(PDO::FETCH_KEY_PAIR);

$activeFilterLabel = null;
if ($categoryFilter !== '') {
    $activeFilterLabel = $categoryFilter === 'none' ? 'not property-specific' : category_label($categoryFilter);
} elseif ($corridorFilter !== '') {
    $activeFilterLabel = $corridorFilter === 'none' ? 'no corridor' : $corridorFilter;
} elseif ($unassignedOnly) {
    $activeFilterLabel = 'nobody (unassigned)';
}
?>

<div class="admin-topbar">
  <h1><?= admin_role_at_least('admin') ? 'Leads' : 'My Leads' ?></h1>
  <div class="admin-topbar-actions">
    <form method="get" class="admin-search">
      <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by name...">
      <?php if ($searchQuery !== ''): ?><a href="/admin/enquiries.php<?= $statusFilter !== '' ? '?status=' . e($statusFilter) : '' ?>" class="admin-search-clear">Clear</a><?php endif; ?>
      <select name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <?php foreach ($statusOptions as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= $statusFilter === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <a href="/admin/enquiry-add.php" class="btn btn-primary btn-sm">+ Add Lead</a>
  </div>
</div>

<?php if ($activeFilterLabel !== null): ?>
  <div class="admin-filter-active">
    Showing leads for <strong><?= e($activeFilterLabel) ?></strong>
    <a href="/admin/enquiries.php">Clear filter</a>
  </div>
<?php endif; ?>

<div class="admin-card">
  <?php if (!$enquiries && $searchQuery !== ''): ?>
    <p style="color:var(--ink-soft)">No leads match "<?= e($searchQuery) ?>".</p>
  <?php elseif (!$enquiries): ?>
    <p style="color:var(--ink-soft)">No leads yet.</p>
  <?php else: ?>
    <div class="admin-card-grid">
      <?php foreach ($enquiries as $en): $score = lead_score($en);
        ob_start(); ?><span class="label">Status</span>
        <form method="post" action="/admin/enquiry-update.php" class="inline-form js-lead-update-form">
          <input type="hidden" name="id" value="<?= (int)$en['id'] ?>">
          <input type="hidden" name="assigned_admin_id" value="<?= (int)($en['assigned_admin_id'] ?? 0) ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <select name="status" class="js-lead-update-field">
            <?php foreach ($statusOptions as $val => $label): ?>
              <option value="<?= e($val) ?>" <?= $en['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php $statusRow = ob_get_clean();

        ob_start(); ?><span class="label">Assigned</span>
        <?php if (!$canAssign): ?><span class="value"><?= e($en['assigned_full_name'] ?: ($en['assigned_username'] ?? 'Unassigned')) ?></span><?php else: ?>
        <form method="post" action="/admin/enquiry-update.php" class="inline-form js-lead-update-form">
          <input type="hidden" name="id" value="<?= (int)$en['id'] ?>">
          <input type="hidden" name="status" value="<?= e($en['status']) ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <select name="assigned_admin_id" class="js-lead-update-field">
            <option value="">Unassigned</option>
            <?php foreach ($admins as $a): ?>
              <option value="<?= (int)$a['id'] ?>" <?= (int)($en['assigned_admin_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['full_name'] ?: $a['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>
        <?php $assignedRow = ob_get_clean();

        ob_start(); ?>
        <a href="/admin/enquiry-view.php?id=<?= (int)$en['id'] ?>" class="btn-link-followup" style="margin-right:12px;">View</a>
        <button type="button" class="btn-link-followup js-followup-open" data-modal="followup-modal-en-<?= (int)$en['id'] ?>">
          Follow-up<?= isset($followUpCounts[$en['id']]) ? ' (' . (int)$followUpCounts[$en['id']] . ')' : '' ?>
        </button>
        <dialog class="followup-modal" id="followup-modal-en-<?= (int)$en['id'] ?>">
          <h4>Add Follow-up</h4>
          <form method="post" action="/admin/follow-up-add.php">
            <input type="hidden" name="enquiry_id" value="<?= (int)$en['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-field"><label for="en-due-<?= (int)$en['id'] ?>">Due Date</label><input type="datetime-local" id="en-due-<?= (int)$en['id'] ?>" name="due_at" required></div>
            <div class="form-field"><label for="en-note-<?= (int)$en['id'] ?>">Note</label><input type="text" id="en-note-<?= (int)$en['id'] ?>" name="note" placeholder="e.g. Call back re: budget"></div>
            <div class="followup-modal-actions">
              <button type="button" class="btn btn-navy btn-sm js-followup-close">Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm">Add</button>
            </div>
          </form>
          <?php if ($en['message']): ?><p class="lead-message"><?= e($en['message']) ?></p><?php endif; ?>
        </dialog>
        <?php $footer = ob_get_clean();

        echo render_admin_data_card([
            'title' => $en['name'],
            'subtitle' => date('d M Y, h:i A', strtotime($en['created_at'])) . ($en['property_name'] ? ' · ' . $en['property_name'] : ''),
            'badges' => [
                '<span class="admin-data-card-badge ' . lead_score_class($score) . '">' . e(lead_score_label($score)) . '</span>',
                $en['source'] ? '<span class="admin-data-card-badge">' . e($en['source']) . '</span>' : null,
            ],
            'rows' => [
                '<span class="label">Phone</span><a class="value" href="tel:' . e($en['phone']) . '">' . e($en['phone']) . '</a>',
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
    if ($statusFilter !== '') $paginationParams['status'] = $statusFilter;
    if ($searchQuery !== '') $paginationParams['q'] = $searchQuery;
    if ($categoryFilter !== '') $paginationParams['category'] = $categoryFilter;
    if ($corridorFilter !== '') $paginationParams['corridor'] = $corridorFilter;
    if ($unassignedOnly) $paginationParams['assigned'] = 'none';
  ?>
  <?= render_pagination($pg, '/admin/enquiries.php', $paginationParams) ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

<?php
$page_title = 'Follow-Ups — Kutumb Admin';
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/leads.php';

// Agents see only their own follow-ups.
$scopeSql = lead_scope_sql('f.assigned_admin_id');

$searchQuery = trim($_GET['q'] ?? '');
$searchHaving = $searchQuery !== '' ? ' HAVING lead_name LIKE ' . db()->quote('%' . $searchQuery . '%') : '';

// Follow-ups don't have a multi-value status column like Leads/Saved Searches
// (just a completed flag, already split into the two sections below) — the
// closest sensible equivalent for the pending list is Overdue vs Upcoming.
$dueFilterOptions = ['overdue' => 'Overdue', 'upcoming' => 'Upcoming'];
$dueFilter = $_GET['due'] ?? '';
$dueSql = '';
if ($dueFilter === 'overdue') {
    $dueSql = ' AND f.due_at < NOW()';
} elseif ($dueFilter === 'upcoming') {
    $dueSql = ' AND f.due_at >= NOW()';
}

$pending = db()->query(
    "SELECT f.*, COALESCE(e.name, s.name) AS lead_name, COALESCE(e.phone, s.phone) AS lead_phone,
            CASE WHEN f.saved_search_id IS NOT NULL THEN 'Saved Search' ELSE 'Lead' END AS lead_type,
            a.username AS assigned_username, a.full_name AS assigned_full_name
     FROM follow_ups f
     LEFT JOIN enquiries e ON e.id = f.enquiry_id
     LEFT JOIN saved_searches s ON s.id = f.saved_search_id
     LEFT JOIN admin_users a ON a.id = f.assigned_admin_id
     WHERE f.completed = 0" . $dueSql . $scopeSql
    . $searchHaving .
    " ORDER BY f.due_at ASC"
)->fetchAll();

$totalCompletedStmt = db()->query(
    "SELECT COUNT(*) FROM (SELECT f.id, COALESCE(e.name, s.name) AS lead_name
     FROM follow_ups f
     LEFT JOIN enquiries e ON e.id = f.enquiry_id
     LEFT JOIN saved_searches s ON s.id = f.saved_search_id
     WHERE f.completed = 1" . $scopeSql . $searchHaving . ") t"
);
$totalCompleted = (int)$totalCompletedStmt->fetchColumn();
$pg = paginate_params($totalCompleted);
$completed = db()->query(
    "SELECT f.*, COALESCE(e.name, s.name) AS lead_name,
            CASE WHEN f.saved_search_id IS NOT NULL THEN 'Saved Search' ELSE 'Lead' END AS lead_type
     FROM follow_ups f
     LEFT JOIN enquiries e ON e.id = f.enquiry_id
     LEFT JOIN saved_searches s ON s.id = f.saved_search_id
     WHERE f.completed = 1" . $scopeSql
    . $searchHaving .
    " ORDER BY f.completed_at DESC
     LIMIT " . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']
)->fetchAll();
?>

<div class="admin-topbar">
  <h1><?= admin_role_at_least('admin') ? 'Follow-Ups' : 'My Follow-Ups' ?></h1>
  <div class="admin-topbar-actions">
    <form method="get" class="admin-search">
      <input type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by name...">
      <?php if ($searchQuery !== ''): ?><a href="/admin/follow-ups.php<?= $dueFilter !== '' ? '?due=' . e($dueFilter) : '' ?>" class="admin-search-clear">Clear</a><?php endif; ?>
      <select name="due" onchange="this.form.submit()">
        <option value="">All pending</option>
        <?php foreach ($dueFilterOptions as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= $dueFilter === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<div class="admin-card">
  <?php if (!$pending && ($searchQuery !== '' || $dueFilter !== '')): ?>
    <p style="color:var(--ink-soft)">No pending follow-ups match this filter.</p>
  <?php elseif (!$pending): ?>
    <p style="color:var(--ink-soft)">Nothing due — you're all caught up.</p>
  <?php else: ?>
    <div class="admin-card-grid">
      <?php foreach ($pending as $f): $overdue = strtotime($f['due_at']) < time();
        ob_start(); ?>
        <form method="post" action="/admin/follow-up-complete.php">
          <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" class="btn btn-primary btn-sm">Mark Done</button>
        </form>
        <?php $footer = ob_get_clean();

        echo render_admin_data_card([
            'title' => $f['lead_name'],
            'subtitle' => $f['lead_type'] . ' · Added ' . date('d M Y, h:i A', strtotime($f['created_at'])),
            'badges' => [$overdue ? '<span class="admin-data-card-badge overdue">Overdue</span>' : null],
            'rows' => [
                '<span class="label">Due</span><span class="value">' . e(date('d M Y, h:i A', strtotime($f['due_at']))) . '</span>',
                '<span class="label">Phone</span><a class="value" href="tel:' . e($f['lead_phone']) . '">' . e($f['lead_phone']) . '</a>',
                $f['note'] ? '<span class="label">Note</span><span class="value">' . e($f['note']) . '</span>' : null,
                ($f['assigned_full_name'] || $f['assigned_username']) ? '<span class="label">Assigned</span><span class="value">' . e($f['assigned_full_name'] ?: $f['assigned_username']) . '</span>' : null,
            ],
            'footer' => $footer,
        ]);
      endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($completed): ?>
<div class="admin-card">
  <h4 style="margin-bottom:12px;">Recently Completed</h4>
  <div class="admin-card-grid">
    <?php foreach ($completed as $f):
      echo render_admin_data_card([
          'title' => $f['lead_name'],
          'subtitle' => $f['lead_type'] . ' · Added ' . date('d M Y, h:i A', strtotime($f['created_at'])),
          'rows' => [
              '<span class="label">Completed</span><span class="value">' . e(date('d M Y, h:i A', strtotime($f['completed_at']))) . '</span>',
              $f['note'] ? '<span class="label">Note</span><span class="value">' . e($f['note']) . '</span>' : null,
          ],
      ]);
    endforeach; ?>
  </div>
  <?php
    $paginationParams = [];
    if ($searchQuery !== '') $paginationParams['q'] = $searchQuery;
    if ($dueFilter !== '') $paginationParams['due'] = $dueFilter;
  ?>
  <?= render_pagination($pg, '/admin/follow-ups.php', $paginationParams) ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

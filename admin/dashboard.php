<?php
$page_title = 'Dashboard — Kutumb Admin';
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/leads.php';

if (!admin_role_at_least('admin')) {
    require __DIR__ . '/_dashboard_agent.php';
}

// Team workload: who holds how many open leads and follow-ups.
$teamLoad = db()->query(
    "SELECT a.id, COALESCE(a.full_name, a.username) AS name, a.role,
            (SELECT COUNT(*) FROM enquiries e WHERE e.assigned_admin_id = a.id AND e.status NOT IN ('converted', 'lost')) AS open_leads,
            (SELECT COUNT(*) FROM follow_ups f WHERE f.assigned_admin_id = a.id AND f.completed = 0) AS open_fu,
            (SELECT COUNT(*) FROM follow_ups f WHERE f.assigned_admin_id = a.id AND f.completed = 0 AND f.due_at < NOW()) AS overdue_fu
     FROM admin_users a WHERE a.active = 1 ORDER BY a.role = 'agent' DESC, name"
)->fetchAll();
$unassignedLeads = (int)db()->query("SELECT COUNT(*) FROM enquiries WHERE assigned_admin_id IS NULL AND status NOT IN ('converted', 'lost')")->fetchColumn();

$totalProperties = (int)db()->query('SELECT COUNT(*) FROM properties WHERE deleted_at IS NULL')->fetchColumn();
$totalEnquiries = (int)db()->query('SELECT COUNT(*) FROM enquiries')->fetchColumn();
$featuredCount = (int)db()->query('SELECT COUNT(*) FROM properties WHERE featured = 1 AND deleted_at IS NULL')->fetchColumn();
$corridorCount = (int)db()->query('SELECT COUNT(*) FROM corridors WHERE deleted_at IS NULL')->fetchColumn();

$deleted = isset($_GET['deleted']);
$saved = isset($_GET['saved']);

function bar_max(array $rows, string $key): int
{
    $max = 0;
    foreach ($rows as $r) { $max = max($max, (int)$r[$key]); }
    return max(1, $max);
}

$funnelOrder = ['new', 'contacted', 'qualified', 'converted', 'lost'];
$funnelRaw = db()->query('SELECT status, COUNT(*) AS cnt FROM enquiries GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$funnel = [];
foreach ($funnelOrder as $status) { $funnel[$status] = (int)($funnelRaw[$status] ?? 0); }
$totalLeads = array_sum($funnel);
$conversionRate = $totalLeads > 0 ? round($funnel['converted'] / $totalLeads * 100, 1) : 0;

$topProperties = db()->query('SELECT name, view_count FROM properties WHERE deleted_at IS NULL ORDER BY view_count DESC, id LIMIT 5')->fetchAll();
$topCorridors = db()->query('SELECT name, view_count FROM corridors WHERE deleted_at IS NULL ORDER BY view_count DESC, id LIMIT 5')->fetchAll();
$topPosts = db()->query("SELECT title, view_count FROM blog_posts WHERE status = 'published' AND deleted_at IS NULL ORDER BY view_count DESC, id LIMIT 5")->fetchAll();

$leadsByCorridor = db()->query(
    "SELECT COALESCE(c.name, 'No Corridor') AS label, COUNT(e.id) AS cnt
     FROM enquiries e LEFT JOIN properties p ON p.id = e.property_id LEFT JOIN corridors c ON c.id = p.corridor_id
     GROUP BY label ORDER BY cnt DESC"
)->fetchAll();

$leadsByCategoryRaw = db()->query(
    'SELECT p.category AS category, COUNT(e.id) AS cnt
     FROM enquiries e LEFT JOIN properties p ON p.id = e.property_id
     GROUP BY p.category ORDER BY cnt DESC'
)->fetchAll();
$leadsByCategory = [];
foreach ($leadsByCategoryRaw as $row) {
    $leadsByCategory[] = [
        'key' => $row['category'] ?: 'none',
        'label' => $row['category'] ? category_label($row['category']) : 'Not property-specific',
        'cnt' => (int)$row['cnt'],
    ];
}

$trendRaw = db()->query(
    'SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM enquiries WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY DATE(created_at)'
)->fetchAll(PDO::FETCH_KEY_PAIR);
$trend = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $trend[] = ['date' => $day, 'cnt' => (int)($trendRaw[$day] ?? 0)];
}
$trendMax = max(1, max(array_column($trend, 'cnt')));
?>

<div class="admin-topbar">
  <h1>Dashboard</h1>
  <a href="/admin/property-form.php" class="btn btn-primary btn-sm">+ Add Property</a>
</div>

<?php if ($deleted): ?><div class="form-note success">Property deleted.</div><?php endif; ?>
<?php if ($saved): ?><div class="form-note success">Property saved.</div><?php endif; ?>

<div class="stat-cards">
  <a href="/admin/properties.php" class="stat-card stat-card-linked"><div class="num"><?= $totalProperties ?></div><div class="label">Total Listings</div></a>
  <a href="/admin/properties.php" class="stat-card stat-card-linked"><div class="num"><?= $featuredCount ?></div><div class="label">Featured</div></a>
  <a href="/admin/corridors.php" class="stat-card stat-card-linked"><div class="num"><?= $corridorCount ?></div><div class="label">Corridors</div></a>
  <a href="/admin/enquiries.php" class="stat-card stat-card-linked"><div class="num"><?= $totalEnquiries ?></div><div class="label">Enquiries</div></a>
</div>

<div class="stat-cards">
  <a href="/admin/enquiries.php" class="stat-card stat-card-linked"><div class="num"><?= $totalLeads ?></div><div class="label">Total Leads</div></a>
  <a href="/admin/enquiries.php?status=converted" class="stat-card stat-card-linked"><div class="num"><?= $conversionRate ?>%</div><div class="label">Conversion Rate</div></a>
  <a href="/admin/enquiries.php?status=new" class="stat-card stat-card-linked"><div class="num"><?= $funnel['new'] ?></div><div class="label">New (Unactioned)</div></a>
</div>

<div class="admin-card">
  <h4 style="margin-bottom:12px;">Team Workload</h4>
  <table class="admin-table">
    <thead><tr><th>Person</th><th>Role</th><th>Open leads</th><th>Open follow-ups</th><th>Overdue</th></tr></thead>
    <tbody>
    <?php foreach ($teamLoad as $t): ?>
      <tr><td><?= e($t['name']) ?></td><td><?= e(ucfirst($t['role'])) ?></td><td><?= (int)$t['open_leads'] ?></td><td><?= (int)$t['open_fu'] ?></td>
          <td><?= (int)$t['overdue_fu'] ? '<span class="status-badge overdue">' . (int)$t['overdue_fu'] . '</span>' : '0' ?></td></tr>
    <?php endforeach; ?>
    <tr><td><a href="/admin/enquiries.php?assigned=none">Unassigned</a></td><td>—</td><td><?= $unassignedLeads ?></td><td>—</td><td>—</td></tr>
    </tbody>
  </table>
  <?php if (!array_filter($teamLoad, fn($t) => $t['role'] === 'agent')): ?>
    <p style="font-size:12.5px; color:var(--ink-soft);">No active agents yet, so new website leads stay Unassigned. Add sales people in <a href="/admin/admins.php">Manage Admins</a> with the role "Agent".</p>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h4 style="margin-bottom:16px;">Lead Funnel</h4>
  <?php $funnelMax = max(1, max($funnel)); ?>
  <table class="funnel-table">
    <?php foreach ($funnel as $status => $cnt): ?>
      <tr class="funnel-row-linked" data-href="/admin/enquiries.php?status=<?= e($status) ?>">
        <td style="width:120px;"><?= e(ucfirst($status)) ?></td>
        <td><div class="bar-row"><div class="bar-fill" style="width:<?= round($cnt / $funnelMax * 100) ?>%"></div></div></td>
        <td style="width:40px; text-align:right;"><?= $cnt ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="admin-card">
  <h4 style="margin-bottom:16px;">Leads — Last 30 Days</h4>
  <div class="trend-chart">
    <?php foreach ($trend as $day): ?>
      <div class="trend-bar" style="height:<?= max(4, round($day['cnt'] / $trendMax * 100)) ?>%" title="<?= e($day['date']) ?>: <?= $day['cnt'] ?>"></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="dashboard-analytics-grid">
  <div class="admin-card">
    <h4 style="margin-bottom:16px;">Leads by Corridor</h4>
    <?php if (!$leadsByCorridor): ?><p style="color:var(--ink-soft); font-size:13px;">No leads yet.</p>
    <?php else: $max = bar_max($leadsByCorridor, 'cnt'); ?>
      <?php foreach ($leadsByCorridor as $row): $corridorParam = $row['label'] === 'No Corridor' ? 'none' : $row['label']; ?>
        <a class="bar-row-labeled bar-row-linked" href="/admin/enquiries.php?corridor=<?= urlencode($corridorParam) ?>">
          <span><?= e($row['label']) ?></span>
          <div class="bar-row"><div class="bar-fill" style="width:<?= round($row['cnt'] / $max * 100) ?>%"></div></div>
          <strong><?= (int)$row['cnt'] ?></strong>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="admin-card">
    <h4 style="margin-bottom:16px;">Leads by Category</h4>
    <?php if (!$leadsByCategory): ?><p style="color:var(--ink-soft); font-size:13px;">No leads yet.</p>
    <?php else: $max = bar_max($leadsByCategory, 'cnt'); ?>
      <?php foreach ($leadsByCategory as $row): ?>
        <a class="bar-row-labeled bar-row-linked" href="/admin/enquiries.php?category=<?= urlencode($row['key']) ?>">
          <span><?= e($row['label']) ?></span>
          <div class="bar-row"><div class="bar-fill" style="width:<?= round($row['cnt'] / $max * 100) ?>%"></div></div>
          <strong><?= (int)$row['cnt'] ?></strong>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="dashboard-analytics-grid dashboard-analytics-grid-3">
  <div class="admin-card">
    <h4 style="margin-bottom:16px;">Top Properties (views)</h4>
    <?php if (!$topProperties): ?><p style="color:var(--ink-soft); font-size:13px;">No data yet.</p>
    <?php else: $max = bar_max($topProperties, 'view_count'); ?>
      <?php foreach ($topProperties as $row): ?>
        <a class="bar-row-labeled bar-row-linked" href="/admin/properties.php?q=<?= urlencode($row['name']) ?>">
          <span><?= e($row['name']) ?></span>
          <div class="bar-row"><div class="bar-fill" style="width:<?= round($row['view_count'] / $max * 100) ?>%"></div></div>
          <strong><?= (int)$row['view_count'] ?></strong>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="admin-card">
    <h4 style="margin-bottom:16px;">Top Corridors (views)</h4>
    <?php if (!$topCorridors): ?><p style="color:var(--ink-soft); font-size:13px;">No data yet.</p>
    <?php else: $max = bar_max($topCorridors, 'view_count'); ?>
      <?php foreach ($topCorridors as $row): ?>
        <a class="bar-row-labeled bar-row-linked" href="/admin/corridors.php?q=<?= urlencode($row['name']) ?>">
          <span><?= e($row['name']) ?></span>
          <div class="bar-row"><div class="bar-fill" style="width:<?= round($row['view_count'] / $max * 100) ?>%"></div></div>
          <strong><?= (int)$row['view_count'] ?></strong>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="admin-card">
    <h4 style="margin-bottom:16px;">Top Posts (views)</h4>
    <?php if (!$topPosts): ?><p style="color:var(--ink-soft); font-size:13px;">No published posts yet.</p>
    <?php else: $max = bar_max($topPosts, 'view_count'); ?>
      <?php foreach ($topPosts as $row): ?>
        <a class="bar-row-labeled bar-row-linked" href="/admin/blog-list.php?q=<?= urlencode($row['title']) ?>">
          <span><?= e($row['title']) ?></span>
          <div class="bar-row"><div class="bar-fill" style="width:<?= round($row['view_count'] / $max * 100) ?>%"></div></div>
          <strong><?= (int)$row['view_count'] ?></strong>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

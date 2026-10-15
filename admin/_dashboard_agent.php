<?php
// Dashboard for the Agent role: only this agent's own leads and follow-ups.
$me = (int)current_admin()['id'];
$myFunnelRaw = db()->prepare('SELECT status, COUNT(*) FROM enquiries WHERE assigned_admin_id = ? GROUP BY status');
$myFunnelRaw->execute([$me]);
$myFunnelRaw = $myFunnelRaw->fetchAll(PDO::FETCH_KEY_PAIR);
$myTotal = array_sum(array_map('intval', $myFunnelRaw));
$fuCounts = db()->prepare(
    'SELECT SUM(due_at < NOW()) AS overdue, SUM(DATE(due_at) = CURDATE() AND due_at >= NOW()) AS today
     FROM follow_ups WHERE completed = 0 AND assigned_admin_id = ?'
);
$fuCounts->execute([$me]);
$fuCounts = $fuCounts->fetch();
$nextUp = db()->prepare(
    'SELECT f.due_at, f.note, e.id AS enquiry_id, COALESCE(e.name, s.name) AS lead_name, COALESCE(e.phone, s.phone) AS lead_phone
     FROM follow_ups f LEFT JOIN enquiries e ON e.id = f.enquiry_id LEFT JOIN saved_searches s ON s.id = f.saved_search_id
     WHERE f.completed = 0 AND f.assigned_admin_id = ? ORDER BY f.due_at LIMIT 8'
);
$nextUp->execute([$me]);
$nextUp = $nextUp->fetchAll();
?>
<div class="admin-topbar"><h1>My Day</h1><a href="/admin/enquiry-add.php" class="btn btn-primary btn-sm">+ Add Lead</a></div>

<div class="stat-cards">
  <a href="/admin/enquiries.php" class="stat-card stat-card-linked"><div class="num"><?= $myTotal ?></div><div class="label">My Leads</div></a>
  <a href="/admin/enquiries.php?status=new" class="stat-card stat-card-linked"><div class="num"><?= (int)($myFunnelRaw['new'] ?? 0) ?></div><div class="label">New (not yet called)</div></a>
  <a href="/admin/follow-ups.php?due=overdue" class="stat-card stat-card-linked"><div class="num"><?= (int)($fuCounts['overdue'] ?? 0) ?></div><div class="label">Follow-ups Overdue</div></a>
  <a href="/admin/follow-ups.php?due=upcoming" class="stat-card stat-card-linked"><div class="num"><?= (int)($fuCounts['today'] ?? 0) ?></div><div class="label">Due Later Today</div></a>
</div>

<div class="admin-card">
  <h4 style="margin-bottom:12px;">Next follow-ups</h4>
  <?php if (!$nextUp): ?><p style="color:var(--ink-soft); font-size:13px;">Nothing pending — you're all caught up.</p><?php else: ?>
  <table class="admin-table">
    <thead><tr><th>Due</th><th>Lead</th><th>Phone</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach ($nextUp as $f): ?>
      <tr>
        <td><?= e(date('d M, h:i A', strtotime($f['due_at']))) ?><?= strtotime($f['due_at']) < time() ? ' <span class="status-badge overdue">overdue</span>' : '' ?></td>
        <td><?php if ($f['enquiry_id']): ?><a href="/admin/enquiry-view.php?id=<?= (int)$f['enquiry_id'] ?>"><?= e($f['lead_name']) ?></a><?php else: ?><?= e($f['lead_name']) ?><?php endif; ?></td>
        <td><a href="tel:<?= e($f['lead_phone']) ?>"><?= e($f['lead_phone']) ?></a></td>
        <td><?= e($f['note'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; exit; ?>

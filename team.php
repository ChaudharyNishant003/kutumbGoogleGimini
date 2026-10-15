<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/structured-data.php';

try {
    $members = db()->query(
        'SELECT t.*, c.name AS corridor_name FROM team_members t LEFT JOIN corridors c ON c.id = t.specialty_corridor_id WHERE t.active = 1 AND t.deleted_at IS NULL ORDER BY t.sort_order, t.name'
    )->fetchAll();
} catch (Throwable $e) {
    require __DIR__ . '/includes/error-page.php';
}

$page_title = 'Our Team — Kutumb Realty';
$page_description = 'Meet the Kutumb Realty advisory team — named specialists across Gurgaon\'s Golf Course Road, Dwarka Expressway, SPR and Sohna Road corridors.';
$structured_data = structured_data_breadcrumbs([
    ['name' => 'Home', 'url' => '/index.php'],
    ['name' => 'Team', 'url' => '/team.php'],
]);
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero texture-grain">
  <div class="container">
    <div class="breadcrumb"><a href="/index.php">Home</a> / Team</div>
    <h1>Meet the Kutumb Team</h1>
    <p>The advisors behind every curated shortlist — real people, real corridor expertise.</p>
    <p style="margin-top:20px;"><a href="/contact.php?ref=team" class="btn btn-primary">Not sure who to talk to? Tell us your brief</a></p>
  </div>
</section>

<section>
  <div class="container">
    <?php if ($members): ?>
      <div class="team-grid reveal reveal-stagger">
        <?php foreach ($members as $m): ?>
          <a class="team-card" href="/agent.php?slug=<?= e($m['slug']) ?>">
            <img src="<?= !empty($m['photo_path']) ? UPLOAD_URL . e($m['photo_path']) : property_placeholder_svg($m['name']) ?>" alt="<?= e($m['name']) ?>">
            <h4><?= e($m['name']) ?></h4>
            <div class="card-meta"><?= e($m['role_title']) ?></div>
            <?php if ($m['corridor_name']): ?><div class="card-meta" style="color:var(--orange-deep)"><?= e($m['corridor_name']) ?> specialist</div><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color:var(--ink-soft)">Team profiles coming soon.</p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

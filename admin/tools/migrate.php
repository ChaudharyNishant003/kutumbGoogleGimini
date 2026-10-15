<?php
// Owner-only migration runner. No migration-runner mechanism existed before
// this feature — migrations/*.sql were presumably applied by hand. This is
// intentionally generic (not AI-specific) so future migrations can reuse it,
// but was built now only because 0015_ai_calling.sql needed a safe way to run.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('owner');

db()->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$migrationsDir = __DIR__ . '/../../database/migrations';
$allFiles = glob($migrationsDir . '/*.sql');
sort($allFiles);

$applied = db()->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$pending = array_values(array_filter($allFiles, fn($f) => !in_array(basename($f), $applied, true)));

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Session expired — please try again.';
    } else {
        $target = basename($_POST['filename'] ?? '');
        $path = $migrationsDir . '/' . $target;
        if (!in_array($target, array_map('basename', $pending), true) || !is_file($path)) {
            $error = 'That migration is not pending or does not exist.';
        } else {
            $sql = file_get_contents($path);
            // Split on statement-terminating semicolons at end-of-line — every
            // statement in these migration files is written one per line/block
            // ending in ";\n", which is simpler and safer here than a real SQL
            // parser given we only ever run our own migration files.
            $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
            db()->beginTransaction();
            try {
                foreach ($statements as $stmt) {
                    if ($stmt === '') continue;
                    db()->exec($stmt);
                }
                db()->prepare('INSERT INTO schema_migrations (filename) VALUES (?)')->execute([$target]);
                db()->commit();
                $message = "Applied $target successfully.";
                $pending = array_values(array_filter($pending, fn($f) => basename($f) !== $target));
            } catch (Throwable $e) {
                db()->rollBack();
                $error = 'Migration failed: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="admin-topbar">
  <h1>Run Migrations</h1>
  <a href="/admin/dashboard.php" class="btn btn-navy btn-sm">&larr; Back to Dashboard</a>
</div>

<?php if ($message): ?><div class="form-note success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-note error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-card">
  <h4>Pending migrations</h4>
  <?php if (!$pending): ?>
    <p style="color:var(--ink-soft)">Nothing pending — all migrations in <code>database/migrations/</code> have been applied.</p>
  <?php else: ?>
    <?php foreach ($pending as $file): ?>
      <form method="post" style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="filename" value="<?= e(basename($file)) ?>">
        <code style="flex:1"><?= e(basename($file)) ?></code>
        <button type="submit" class="btn btn-primary btn-sm">Run</button>
      </form>
    <?php endforeach; ?>
  <?php endif; ?>

  <h4 style="margin-top:24px;">Already applied</h4>
  <?php if (!$applied): ?>
    <p style="color:var(--ink-soft)">None yet.</p>
  <?php else: ?>
    <ul style="font-size:13px; color:var(--ink-soft);">
      <?php foreach ($applied as $a): ?><li><?= e($a) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>

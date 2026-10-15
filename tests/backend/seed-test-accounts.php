<?php
// QA Fortress fixture accounts — synthetic, clearly labeled, local Docker only.
// Idempotent: safe to re-run.
require '/var/www/html/includes/db.php';

$accounts = [
    ['qa_owner_test', 'QaFortressOwner!2026', 'owner'],
    ['qa_admin_test', 'QaFortressAdmin!2026', 'admin'],
    ['qa_agent_test', 'QaFortressAgent!2026', 'agent'],
];

foreach ($accounts as [$username, $password, $role]) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $existing = db()->prepare('SELECT id FROM admin_users WHERE username = ?');
    $existing->execute([$username]);
    if ($row = $existing->fetch()) {
        db()->prepare('UPDATE admin_users SET password_hash = ?, role = ?, active = 1 WHERE id = ?')
            ->execute([$hash, $role, $row['id']]);
        echo "Updated: $username ($role)\n";
    } else {
        db()->prepare('INSERT INTO admin_users (username, password_hash, role, full_name, active) VALUES (?,?,?,?,1)')
            ->execute([$username, $hash, $role, 'QA Fortress Test — ' . ucfirst($role)]);
        echo "Created: $username ($role)\n";
    }
}

// Deactivate the old leftover qaadminA/qaadminB from the earlier security sweep — superseded by these.
db()->exec("UPDATE admin_users SET active = 0 WHERE username IN ('qaadminA','qaadminB')");
echo "Done.\n";

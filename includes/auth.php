<?php
require_once __DIR__ . '/db.php';

const ADMIN_ROLE_RANK = ['agent' => 1, 'admin' => 2, 'owner' => 3];

function current_admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_admin(): void
{
    if (!current_admin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/** Requires the current admin to hold at least $minRole (owner > admin > agent). */
function require_role(string $minRole): void
{
    require_admin();
    $have = ADMIN_ROLE_RANK[current_admin()['role'] ?? 'agent'] ?? 0;
    $need = ADMIN_ROLE_RANK[$minRole] ?? 999;
    if ($have < $need) {
        http_response_code(403);
        echo '<p style="font-family:sans-serif; padding:40px;">You don\'t have permission to view this page. <a href="/admin/dashboard.php">Back to dashboard</a></p>';
        exit;
    }
}

/** True when the logged-in admin holds at least $role (owner > admin > agent). */
function admin_role_at_least(string $role): bool
{
    $have = ADMIN_ROLE_RANK[current_admin()['role'] ?? ''] ?? 0;
    return $have >= (ADMIN_ROLE_RANK[$role] ?? 999);
}

function attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, username, password_hash, role, full_name, active FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && (int)$user['active'] === 1 && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
        ];
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

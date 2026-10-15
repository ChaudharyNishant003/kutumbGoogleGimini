<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (current_admin()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Higher ceiling than the public-form default (5/10min): this endpoint can see several
    // legitimate logins in quick succession from one IP (a small office team signing in each
    // morning on the same network), and a brute-force attempt against a bcrypt hash is still
    // fully defeated well below this rate.
    if (!rate_limit_check('admin-login', 15, 10)) {
        $error = 'Too many login attempts — please try again in a few minutes.';
    } elseif (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Session expired — please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (attempt_login($username, $password)) {
            header('Location: /admin/dashboard.php');
            exit;
        }
        $error = 'Incorrect username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login — Kutumb Realty</title>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Newsreader:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="login-shell texture-grain">
  <div class="login-card">
    <div class="brand">Kutumb<span>Realty</span></div>
    <p style="font-size:13px; margin-bottom:24px;">Admin panel login</p>
    <?php if ($error): ?><div class="form-note error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="form-field"><label for="login-username">Username</label><input type="text" id="login-username" name="username" required autofocus></div>
      <div class="form-field"><label for="login-password">Password</label><input type="password" id="login-password" name="password" required></div>
      <button type="submit" class="btn btn-primary btn-block">Log In</button>
    </form>
  </div>
</div>
</body>
</html>

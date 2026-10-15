<?php
// Analytics is no longer a standalone tab — its charts moved into Dashboard.
require_once __DIR__ . '/../includes/auth.php';
require_admin();
header('Location: /admin/dashboard.php');
exit;

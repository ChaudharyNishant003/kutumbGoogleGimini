<?php
// Kutumb Realty — site configuration template.
// Copy this file to config.php and fill in real values. config.php itself
// is gitignored so real credentials never get committed.

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'kutumb_realty');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('SITE_NAME', 'Kutumb Realty');
define('SITE_URL', 'http://localhost:8000');
define('SITE_PHONE', '+91 9318373250');
define('SITE_WHATSAPP', '919318373250');
define('SITE_EMAIL', 'info@kutumbrealty.com');
define('SITE_ADDRESS', 'DLF Corporate Greens, Sector-74A, Gurugram, Haryana');

// Leave blank to hide that footer social icon until a real profile exists.
define('SITE_FACEBOOK_URL', '');
define('SITE_INSTAGRAM_URL', '');
define('SITE_LINKEDIN_URL', '');

// Fill this in with a real OpenAI API key to activate the property chatbot,
// or leave blank and set it from the admin Settings panel instead.
define('OPENAI_API_KEY', '');
define('OPENAI_MODEL', 'gpt-4o-mini');

// Encrypts secrets stored in the ai_settings table (AI Calling feature, parked on the ai-voice-agent branch).
// Generate your own via: php -r "echo base64_encode(sodium_crypto_secretbox_keygen());"
define('APP_ENCRYPTION_KEY', '');

define('UPLOAD_DIR', __DIR__ . '/../uploads/properties/');
define('UPLOAD_URL', '/uploads/properties/');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5MB

define('DOCS_UPLOAD_DIR', __DIR__ . '/../uploads/documents/');
define('DOCS_UPLOAD_URL', '/uploads/documents/');

// Railway terminates TLS at its edge proxy, so $_SERVER['HTTPS'] alone isn't
// set on the origin request — check the forwarded-proto header too, or the
// Secure flag would never turn on in production and the cookie would silently
// travel over plain HTTP-downgrade attempts.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
session_start();
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', '1');

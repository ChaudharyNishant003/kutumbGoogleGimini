<?php
// Kutumb Realty — production configuration for Railway hosting.
// Reads real values from environment variables set on the Railway service
// (Project → kutumb-web → Variables), so no secrets are committed to GitHub.
// The Dockerfile copies this file to config/config.php during the build.

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'kutumb_realty');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('SITE_NAME', getenv('SITE_NAME') ?: 'Kutumb Realty');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost:8000');
define('SITE_PHONE', getenv('SITE_PHONE') ?: '+91 9318373250');
define('SITE_WHATSAPP', getenv('SITE_WHATSAPP') ?: '919318373250');
define('SITE_EMAIL', getenv('SITE_EMAIL') ?: 'info@kutumbrealty.com');
define('SITE_ADDRESS', getenv('SITE_ADDRESS') ?: 'DLF Corporate Greens, Sector-74A, Gurugram, Haryana');

define('SITE_FACEBOOK_URL', getenv('SITE_FACEBOOK_URL') ?: '');
define('SITE_INSTAGRAM_URL', getenv('SITE_INSTAGRAM_URL') ?: '');
define('SITE_LINKEDIN_URL', getenv('SITE_LINKEDIN_URL') ?: '');

define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('OPENAI_MODEL', getenv('OPENAI_MODEL') ?: 'gpt-4o-mini');

// Encrypts secrets stored in the ai_settings table (AI Calling feature, parked on the ai-voice-agent branch).
// Must be set as a real Railway service variable — generate once via
// `php -r "echo base64_encode(sodium_crypto_secretbox_keygen());"` and never
// change it once real encrypted settings exist (rotating it makes existing
// encrypted rows unreadable).
define('APP_ENCRYPTION_KEY', getenv('APP_ENCRYPTION_KEY') ?: '');

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
ini_set('display_errors', '0');

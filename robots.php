<?php
// Served at /robots.txt via a rewrite in .htaccess so the Sitemap URL always
// matches the real SITE_URL instead of a hard-coded host.
require_once __DIR__ . '/config/config.php';

header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Allow: /\n\n";
echo 'Sitemap: ' . rtrim(SITE_URL, '/') . "/sitemap.php\n";

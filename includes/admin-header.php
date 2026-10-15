<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/admin-card.php';
require_admin();

$admin = current_admin();
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$page_title = $page_title ?? 'Admin — Kutumb Realty';

// "Visit Website" (sidebar) links to the public-site page that corresponds
// to whichever admin section is currently open — only where a sensible
// public equivalent exists (content sections); everything else (leads,
// follow-ups, settings, bin, admin accounts — none of which are publicly
// browsable) falls back to the homepage.
$visitWebsiteMap = [
    'properties.php' => '/properties.php', 'property-form.php' => '/properties.php',
    'property-gallery.php' => '/properties.php', 'property-documents.php' => '/properties.php', 'property-faqs.php' => '/properties.php',
    'blog-list.php' => '/blog.php', 'blog-form.php' => '/blog.php',
    'corridors.php' => '/map-search.php', 'corridor-form.php' => '/map-search.php',
    'team.php' => '/team.php', 'team-form.php' => '/team.php',
];
$visitWebsiteUrl = $visitWebsiteMap[$current] ?? '/index.php';
$isAdmin = admin_role_at_least('admin');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Newsreader:ital,wght@0,400;0,500;0,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
</head>
<body class="admin">
<div class="admin-shell">
  <div class="admin-topbar-mobile">
    <!-- Toggle comes before the logo so it sits on the LEFT of the bar,
         the same side the menu panel itself slides in from — tapping it
         and the menu appearing now happen on the same side. -->
    <button type="button" class="admin-nav-toggle" aria-label="Toggle menu">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <a href="/admin/dashboard.php" class="brand">Kutumb<span>Admin</span></a>
  </div>
  <aside class="admin-sidebar texture-grain">
    <a href="/admin/dashboard.php" class="brand">Kutumb<span>Admin</span></a>
    <a href="<?= e($visitWebsiteUrl) ?>" target="_blank" rel="noopener" class="sidebar-visit-btn">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
      Visit Website
    </a>
    <nav class="admin-nav">
      <div class="admin-nav-label admin-nav-label-top">Kutumb</div>
      <a href="/admin/dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>"><?= $isAdmin ? 'Dashboard' : 'My Day' ?></a>
      <?php if ($isAdmin): ?>
        <a href="/admin/properties.php" class="<?= in_array($current, ['properties.php', 'property-form.php', 'property-faqs.php', 'property-details.php', 'property-gallery.php', 'property-documents.php'], true) ? 'active' : '' ?>">Properties</a>
      <?php endif; ?>
      <a href="/admin/enquiries.php" class="<?= in_array($current, ['enquiries.php', 'enquiry-view.php', 'enquiry-add.php'], true) ? 'active' : '' ?>"><?= $isAdmin ? 'Leads' : 'My Leads' ?></a>
      <a href="/admin/follow-ups.php" class="<?= $current === 'follow-ups.php' ? 'active' : '' ?>"><?= $isAdmin ? 'Follow-Ups' : 'My Follow-Ups' ?></a>
      <a href="/admin/saved-searches.php" class="<?= $current === 'saved-searches.php' ? 'active' : '' ?>"><?= $isAdmin ? 'Saved Searches' : 'My Saved Searches' ?></a>
      <?php if ($isAdmin): ?>
        <a href="/admin/blog-list.php" class="<?= $current === 'blog-list.php' || $current === 'blog-form.php' ? 'active' : '' ?>">Blog &amp; Insights</a>
        <a href="/admin/corridors.php" class="<?= $current === 'corridors.php' || $current === 'corridor-form.php' ? 'active' : '' ?>">Corridors</a>
        <a href="/admin/places.php" class="<?= in_array($current, ['places.php', 'place-form.php'], true) ? 'active' : '' ?>">Nearby Places</a>
        <a href="/admin/team.php" class="<?= $current === 'team.php' || $current === 'team-form.php' ? 'active' : '' ?>">Team</a>
        <a href="/admin/testimonials.php" class="<?= $current === 'testimonials.php' || $current === 'testimonial-form.php' ? 'active' : '' ?>">Testimonials</a>
        <a href="/admin/ai-log.php" class="<?= $current === 'ai-log.php' ? 'active' : '' ?>">AI Question Log</a>
        <a href="/admin/bin.php" class="<?= $current === 'bin.php' ? 'active' : '' ?>">Bin<?php $binCount = bin_count(); if ($binCount): ?> <span class="nav-badge"><?= $binCount ?></span><?php endif; ?></a>
      <?php endif; ?>
      <?php if (($admin['role'] ?? '') === 'owner'): ?>
        <a href="/admin/settings.php" class="<?= $current === 'settings.php' ? 'active' : '' ?>">Settings</a>
        <a href="/admin/admins.php" class="<?= $current === 'admins.php' || $current === 'admin-form.php' ? 'active' : '' ?>">Manage Admins</a>
      <?php endif; ?>
    </nav>
    <a href="/admin/logout.php" class="logout">Log out (<?= e($admin['full_name'] ?: $admin['username']) ?>)</a>
  </aside>
  <main class="admin-main">

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/leads.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    header('Location: /admin/follow-ups.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$redirect = '/admin/follow-ups.php';
if ($id && ($_POST['back'] ?? '') === 'lead') {
    $lead = db()->prepare('SELECT enquiry_id FROM follow_ups WHERE id = ?');
    $lead->execute([$id]);
    if ($leadId = (int)$lead->fetchColumn()) $redirect = '/admin/enquiry-view.php?id=' . $leadId;
}
if ($id) {
    // Agents close only their own follow-ups.
    $stmt = db()->prepare('UPDATE follow_ups SET completed = 1, completed_at = NOW() WHERE id = ?' . lead_scope_sql('assigned_admin_id'));
    $stmt->execute([$id]);
}

header('Location: ' . $redirect);
exit;

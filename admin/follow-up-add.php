<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/leads.php';
require_admin();

$enquiryId = !empty($_POST['enquiry_id']) ? (int)$_POST['enquiry_id'] : null;
$savedSearchId = !empty($_POST['saved_search_id']) ? (int)$_POST['saved_search_id'] : null;
$redirect = $savedSearchId ? '/admin/saved-searches.php' : '/admin/enquiries.php';
if (($_POST['back'] ?? '') === 'lead' && $enquiryId) {
    $redirect = '/admin/enquiry-view.php?id=' . $enquiryId;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    header('Location: ' . $redirect);
    exit;
}

$dueAt = $_POST['due_at'] ?? '';
$note = trim($_POST['note'] ?? '');
$admin = current_admin();

if (($enquiryId xor $savedSearchId) && $dueAt !== '') {
    $target = db()->prepare($enquiryId ? 'SELECT assigned_admin_id FROM enquiries WHERE id = ?' : 'SELECT assigned_admin_id FROM saved_searches WHERE id = ?');
    $target->execute([$enquiryId ?: $savedSearchId]);
    $target = $target->fetch();
    // Agents add follow-ups only on their own leads. The follow-up belongs to whoever owns the
    // lead (so it shows in their list), or to the person adding it when the lead is unassigned.
    if ($target && lead_visible_to_current_admin($target)) {
        $owner = $target['assigned_admin_id'] !== null ? (int)$target['assigned_admin_id'] : (int)$admin['id'];
        $stmt = db()->prepare('INSERT INTO follow_ups (enquiry_id, saved_search_id, assigned_admin_id, due_at, note) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$enquiryId, $savedSearchId, $owner, str_replace('T', ' ', $dueAt), $note !== '' ? $note : null]);
    }
}

header('Location: ' . $redirect);
exit;

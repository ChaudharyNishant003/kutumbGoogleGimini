<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/site-stats.php';
require_once __DIR__ . '/../includes/lead_assign.php';

$successMessage = 'Thanks — an advisor will get back to you ' . SITE_RESPONSE_PROMISE . '.';
['name' => $name, 'phone' => $phone, 'email' => $email] = validate_public_lead_form('enquiry-submit', $successMessage);

$message = trim($_POST['message'] ?? '');
$propertyId = !empty($_POST['property_id']) ? (int)$_POST['property_id'] : null;
$source = trim($_POST['source'] ?? '');
if ($campaign = public_form_campaign()) {
    $message = trim($message . "\n[Campaign: " . $campaign . ']');
}

$stmt = db()->prepare('INSERT INTO enquiries (name, phone, phone_e164, email, message, property_id, source, inquiry_at, consent_basis) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), "inquiry")');
$stmt->execute([$name, $phone, normalize_to_e164_in($phone), $email !== '' ? $email : null, $message !== '' ? $message : null, $propertyId, $source !== '' ? mb_substr($source, 0, 40) : null]);
lead_auto_assign((int)db()->lastInsertId());

respond_json(true, $successMessage);

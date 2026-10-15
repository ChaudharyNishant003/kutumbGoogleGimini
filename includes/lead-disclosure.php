<?php
/**
 * Visitor-facing contact disclosure shown under every public lead form.
 * Single source for the wording: edit the text here and every form updates.
 * DRAFT copy — to be confirmed by the business owner / legal counsel.
 * (This is a notice only. It does not record or prove consent in the CRM.)
 */
$leadDisclosureMode = $leadDisclosureMode ?? 'submit'; // 'submit' = the form is a request to be called; 'optin' = calling only if the visitor ticks the box above
$leadDisclosureText = $leadDisclosureMode === 'optin'
    ? 'An advisor will get in touch about your search. If you tick the box above, a Kutumb advisor may also phone you. You can ask us to stop at any time.'
    : 'By submitting, you agree that a Kutumb advisor may call, WhatsApp or email you about this request. You can ask us to stop at any time.';
?>
<p class="lead-disclosure"><?= e($leadDisclosureText) ?> <a href="/privacy-policy.php" target="_blank" rel="noopener">Privacy Policy</a></p>

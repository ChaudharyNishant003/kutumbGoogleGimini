<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** A wa.me link with an optional pre-filled, page-specific message. Result is pre-escaped — print directly into href. */
/** e.g. "sector:dwarka-expressway" — the public page a CTA was rendered on (never for admin/API output). */
function whatsapp_page_tag(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script === '' || str_contains($script, '/admin/') || str_contains($script, '/api/')) {
        return '';
    }
    $tag = basename($script, '.php');
    $key = $_GET['slug'] ?? ($_GET['id'] ?? '');
    if (is_string($key) && preg_match('/^[a-z0-9-]{1,60}$/i', $key)) {
        $tag .= ':' . $key;
    }
    return $tag;
}

function whatsapp_url(string $message = '', ?string $number = null): string
{
    $number = $number ?: SITE_WHATSAPP;
    $url = 'https://wa.me/' . rawurlencode($number);
    if ($message !== '') {
        // Direct WhatsApp chats bypass the forms, so say which page they came from.
        if ($tag = whatsapp_page_tag()) {
            $message .= "\n\n(Sent from the Kutumb website, page: " . $tag . ')';
        }
        $url .= '?text=' . rawurlencode($message);
    }
    return e($url);
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(?string $token): bool
{
    return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Public-facing tier badge for a property/corridor's 'tier' column.
 * Standard is the baseline and intentionally renders nothing — only
 * Luxury/Premium are visually called out. Returns pre-escaped HTML
 * (a single <span>) or '' when there's nothing to show.
 */
function tier_badge_html(string $tier): string
{
    $labels = ['luxury' => 'Luxury', 'premium' => 'Premium'];
    if (!isset($labels[$tier])) return '';
    return '<span class="tier-badge tier-badge-' . e($tier) . '">' . e($labels[$tier]) . '</span>';
}

function category_label(string $category): string
{
    $labels = [
        'new-launch' => 'New Launch',
        'under-construction' => 'Under Construction',
        'ready-to-move' => 'Ready To Move',
        'commercial' => 'Commercial',
    ];
    return $labels[$category] ?? ucfirst($category);
}

/**
 * Deterministic navy/orange gradient SVG placeholder for a property card,
 * used until a real photo is uploaded via the admin panel.
 */
function property_placeholder_svg(string $name): string
{
    $initials = '';
    foreach (preg_split('/\s+/', trim($name)) as $word) {
        if ($word !== '') {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }
        if (mb_strlen($initials) >= 2) break;
    }
    $hash = crc32($name);
    $hueA = $hash % 360;
    $hueB = ($hueA + 28) % 360;

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 320">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0%" stop-color="hsl(' . $hueA . ',45%,16%)"/>'
        . '<stop offset="100%" stop-color="hsl(' . $hueB . ',55%,24%)"/>'
        . '</linearGradient></defs>'
        . '<rect width="480" height="320" fill="url(#g)"/>'
        . '<circle cx="240" cy="150" r="70" fill="none" stroke="#ff9909" stroke-width="1.5" opacity="0.35"/>'
        . '<text x="240" y="172" font-family="Georgia, serif" font-size="64" fill="#ffffff" fill-opacity="0.92" text-anchor="middle">' . $initials . '</text>'
        . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Active (non-deleted) corridors, cached for the lifetime of the request.
 * Used by the nav ticker, footer links, and homepage search/journey strip —
 * previously each ran its own separate query for the same small table.
 */
function get_active_corridors(): array
{
    static $corridors = null;
    if ($corridors === null) {
        $corridors = db()->query(
            'SELECT id, name, slug, avg_price_band, tier, price_growth_label, hero_image_path, description FROM corridors WHERE deleted_at IS NULL ORDER BY name'
        )->fetchAll();
    }
    return $corridors;
}

/**
 * Increments {$table}.view_count for $id at most once per 24h per visitor,
 * tracked via a short-lived cookie rather than a login. Previously every
 * single page load counted — the owner's own repeat visits, admin previews,
 * and crawlers included — which made the Dashboard's "Top by views" widgets
 * a raw hit-counter rather than a real popularity signal. $table is always
 * one of a handful of fixed, hardcoded call-site literals (never user
 * input), same trust boundary as bin_config()'s table names.
 */
function record_view_once(string $table, int $id, string $cookiePrefix): void
{
    $cookieName = $cookiePrefix . '_' . $id;
    if (isset($_COOKIE[$cookieName])) {
        return;
    }
    // blog_posts.updated_at is ON UPDATE CURRENT_TIMESTAMP and feeds sitemap lastmod / JSON-LD
    // dateModified, so a page view must not count as an edit.
    $keepUpdatedAt = $table === 'blog_posts' ? ', updated_at = updated_at' : '';
    db()->prepare("UPDATE {$table} SET view_count = view_count + 1{$keepUpdatedAt} WHERE id = ?")->execute([$id]);
    setcookie($cookieName, '1', time() + 86400, '/', '', false, true);
}

function get_openai_key(): string
{
    $stmt = db()->query('SELECT openai_api_key FROM app_settings WHERE id = 1');
    $dbKey = $stmt->fetchColumn();
    return $dbKey ?: OPENAI_API_KEY;
}

function mask_api_key(string $key): string
{
    if ($key === '') return '';
    $tail = substr($key, -4);
    return str_repeat('•', 10) . $tail;
}

function property_image_src(array $property): string
{
    if (!empty($property['image_path'])) {
        return UPLOAD_URL . e($property['image_path']);
    }
    return property_placeholder_svg($property['name']);
}

/**
 * Same contract as property_image_src(), for card/thumbnail contexts —
 * uses the small derivative when one exists (see image_card_url()),
 * otherwise falls back to the exact same full-size image/placeholder.
 */
function property_card_image_src(array $property): string
{
    if (!empty($property['image_path'])) {
        return image_card_url($property['image_path'], UPLOAD_URL, UPLOAD_DIR);
    }
    return property_placeholder_svg($property['name']);
}

function image_mime_map(): array
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
}

/**
 * Validates and saves an uploaded file under $destDir with a random name.
 * Returns the new filename on success, or null (with $error set) on failure/no-file.
 */
function handle_file_upload(array $file, string $destDir, array $allowedMimes, int $maxBytes, ?string &$error = null): ?string
{
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > $maxBytes) {
        $error = 'File is too large.';
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMimes[$mime])) {
        $error = 'That file type is not allowed.';
        return null;
    }
    $newName = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $destDir . $newName)) {
        $error = 'Could not save the uploaded file.';
        return null;
    }
    return $newName;
}

function delete_uploaded_file(string $dir, ?string $filename): void
{
    if ($filename && file_exists($dir . $filename)) {
        @unlink($dir . $filename);
    }
    if ($filename) {
        $cardFile = $dir . image_card_filename($filename);
        if (file_exists($cardFile)) @unlink($cardFile);
    }
}

// ---------- Image card derivatives (Batch 3.1) ----------
// A phone-camera photo uploaded at full resolution was previously served
// as-is everywhere it appears — the hero, every listing card, and a
// 100x74px gallery thumbnail alike. This generates one smaller derivative
// alongside the original at upload time; the original is untouched (still
// what's stored in the DB column and used for the hero/detail view), and
// the derivative is only ever looked up by filename convention below.
// Currently wired into property cover photos and gallery photos only —
// not yet team/blog/testimonial/corridor images, so those keep serving
// full-size until this is deliberately extended to them.

function image_card_filename(string $filename): string
{
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);
    return $base . '-card.' . $ext;
}

/**
 * Generates a max-480px-wide derivative of an already-saved upload, named
 * per image_card_filename(). Silently does nothing (never throws, never
 * blocks the upload) if GD isn't available, the source isn't decodable, or
 * the source is already small enough — a missing derivative just means
 * image_card_url() falls back to the full image.
 */
function generate_image_card_derivative(string $dir, string $filename, int $maxWidth = 480): void
{
    if (!function_exists('imagecreatetruecolor')) return;

    $path = $dir . $filename;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $info = @getimagesize($path);
    if (!$info) return;
    [$width, $height] = $info;
    if ($width <= $maxWidth) return;

    $src = match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        'png' => @imagecreatefrompng($path),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
        default => null,
    };
    if (!$src) return;

    $newHeight = max(1, (int)round($height * ($maxWidth / $width)));
    $dst = imagecreatetruecolor($maxWidth, $newHeight);
    if ($ext === 'png' || $ext === 'webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);

    $cardPath = $dir . image_card_filename($filename);
    match ($ext) {
        'jpg', 'jpeg' => imagejpeg($dst, $cardPath, 82),
        'png' => imagepng($dst, $cardPath, 6),
        'webp' => function_exists('imagewebp') ? imagewebp($dst, $cardPath, 82) : null,
        default => null,
    };
    imagedestroy($src);
    imagedestroy($dst);
}

/**
 * URL for an uploaded image's small "card" derivative if one exists on disk,
 * otherwise the full-size image — pre-escaped, safe to print directly (same
 * contract as property_image_src()). A missing derivative (pre-optimization
 * uploads, or a failed resize) always falls back to the full image, never a
 * broken one.
 */
function image_card_url(string $filename, string $baseUrl, string $dir): string
{
    $cardFilename = image_card_filename($filename);
    $use = is_file($dir . $cardFilename) ? $cardFilename : $filename;
    return $baseUrl . e($use);
}

function format_inr(float $amount): string
{
    $amount = round($amount);
    $isNegative = $amount < 0;
    $amount = abs($amount);
    $str = number_format($amount, 0, '', '');

    if (strlen($str) <= 3) {
        $formatted = $str;
    } else {
        $last3 = substr($str, -3);
        $rest = substr($str, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $formatted = $rest . ',' . $last3;
    }
    return ($isNegative ? '-' : '') . '₹' . $formatted;
}

/** Short Indian price label: 40375000 → "₹4.04 Cr", 9500000 → "₹95 L". */
function format_price_short(float $amount): string
{
    if ($amount >= 10000000) {
        return '₹' . rtrim(rtrim(number_format($amount / 10000000, 2, '.', ''), '0'), '.') . ' Cr';
    }
    return '₹' . rtrim(rtrim(number_format($amount / 100000, 2, '.', ''), '0'), '.') . ' L';
}

/** "1333.12" → "1,333.12", "1565.00" → "1,565" (areas are stored with 2 decimals). */
function format_area(float $sqft): string
{
    return floor($sqft) == $sqft ? number_format($sqft) : number_format($sqft, 2);
}

function excerpt(string $html, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '…';
}

/**
 * Admin-authored blog body HTML — stripped to a safe tag allowlist, and every
 * remaining tag's attributes stripped to a safe per-tag allowlist too (strip_tags()
 * alone removes disallowed tags but leaves attributes like onerror/onclick on
 * whatever tags it keeps, e.g. <img src=x onerror=alert(1)> stays intact).
 */
function sanitize_post_body(string $html): string
{
    $allowedTags = ['p', 'br', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'strong', 'b', 'em', 'i', 'u', 'blockquote', 'img', 'figure', 'figcaption'];
    $alignable = ['style'];
    $allowedAttrs = [
        'a' => ['href'], 'img' => ['src', 'alt'],
        'p' => $alignable, 'h2' => $alignable, 'h3' => $alignable, 'h4' => $alignable,
        'li' => $alignable, 'blockquote' => $alignable,
    ];

    $stripped = strip_tags($html, '<' . implode('><', $allowedTags) . '>');

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $stripped . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $wrapper = $doc->getElementsByTagName('div')->item(0);
    if (!$wrapper) return '';
    sanitize_dom_node($wrapper, $allowedAttrs);

    $out = '';
    foreach ($wrapper->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

function sanitize_dom_node(DOMNode $node, array $allowedAttrs): void
{
    if ($node->nodeType === XML_ELEMENT_NODE && $node->hasAttributes()) {
        $tag = strtolower($node->nodeName);
        $keep = $allowedAttrs[$tag] ?? [];
        $toRemove = [];
        foreach ($node->attributes as $attr) {
            $name = strtolower($attr->name);
            $isUrlAttr = in_array($name, ['href', 'src'], true);
            $hasSafeScheme = !$isUrlAttr || preg_match('#^(https?:|mailto:|/)#i', trim($attr->value));
            // style is only ever allowed to carry a plain text-align declaration (the
            // rich text editor's alignment buttons) — never pass through arbitrary CSS.
            $isStyleAttr = $name === 'style';
            $hasSafeStyle = !$isStyleAttr || preg_match('#^text-align:\s*(left|center|right|justify);?$#i', trim($attr->value));
            if (!in_array($name, $keep, true) || !$hasSafeScheme || !$hasSafeStyle) {
                $toRemove[] = $attr->name;
            }
        }
        foreach ($toRemove as $name) {
            $node->removeAttribute($name);
        }
    }
    foreach (iterator_to_array($node->childNodes) as $child) {
        sanitize_dom_node($child, $allowedAttrs);
    }
}

// ---------- Bin / soft-delete ----------
// Every bin-eligible table has a nullable deleted_at column. Deleting sets it;
// restoring clears it; nothing is permanently removed (including uploaded
// files on disk) until it's actually purged, either one by one or with the
// Bin page's "Delete expired items" button (items older than 15 days).

function bin_config(): array
{
    return [
        'property'    => ['table' => 'properties',    'label_col' => 'name',        'file_cols' => ['image_path']],
        'blog_post'   => ['table' => 'blog_posts',     'label_col' => 'title',       'file_cols' => ['cover_image_path']],
        'team_member' => ['table' => 'team_members',   'label_col' => 'name',        'file_cols' => ['photo_path']],
        'testimonial' => ['table' => 'testimonials',   'label_col' => 'client_name', 'file_cols' => ['photo_path']],
        'corridor'    => ['table' => 'corridors',      'label_col' => 'name',        'file_cols' => ['hero_image_path']],
        'place'       => ['table' => 'places',         'label_col' => 'name',        'file_cols' => []],
    ];
}

function bin_soft_delete(string $type, int $id): void
{
    $cfg = bin_config()[$type] ?? null;
    if (!$cfg) return;
    $stmt = db()->prepare("UPDATE {$cfg['table']} SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
}

function bin_restore(string $type, int $id): void
{
    $cfg = bin_config()[$type] ?? null;
    if (!$cfg) return;
    $stmt = db()->prepare("UPDATE {$cfg['table']} SET deleted_at = NULL WHERE id = ?");
    $stmt->execute([$id]);
}

// Permanently removes a bin item: deletes its uploaded files, then the row
// (property image/document child rows and their files are cleaned up first,
// same as the old hard-delete flow did, since the DB cascade only removes
// rows, not files on disk).
function bin_purge_item(string $type, int $id): void
{
    $cfg = bin_config()[$type] ?? null;
    if (!$cfg) return;
    $table = $cfg['table'];

    // Only ever reads $cfg['file_cols'] off this row (below) — no need for
    // every column on a table this generic function doesn't otherwise know.
    $stmt = db()->prepare('SELECT ' . implode(', ', $cfg['file_cols'] ?: ['id']) . " FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return;

    foreach ($cfg['file_cols'] as $col) {
        delete_uploaded_file(UPLOAD_DIR, $row[$col] ?? null);
    }

    if ($type === 'property') {
        $imgStmt = db()->prepare('SELECT image_path FROM property_images WHERE property_id = ?');
        $imgStmt->execute([$id]);
        foreach ($imgStmt->fetchAll() as $img) {
            delete_uploaded_file(UPLOAD_DIR, $img['image_path']);
        }
        $docStmt = db()->prepare('SELECT file_path FROM property_documents WHERE property_id = ?');
        $docStmt->execute([$id]);
        foreach ($docStmt->fetchAll() as $doc) {
            delete_uploaded_file(DOCS_UPLOAD_DIR, $doc['file_path']);
        }
    }

    db()->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
}

// Permanently removes everything past the 15-day grace period. Runs only when
// an admin presses "Delete expired items" on the Bin page (never just by
// opening the page). Returns how many items were removed.
function bin_purge_expired(): int
{
    $removed = 0;
    foreach (bin_config() as $type => $cfg) {
        if (!bin_table_exists($cfg['table'])) continue;
        $stmt = db()->prepare("SELECT id FROM {$cfg['table']} WHERE deleted_at IS NOT NULL AND deleted_at < NOW() - INTERVAL 15 DAY");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            bin_purge_item($type, (int)$id);
            $removed++;
        }
    }
    return $removed;
}

// Everything currently in the bin, newest-deleted first, with days-remaining
// for the UI countdown.
function bin_list_items(): array
{
    $items = [];
    foreach (bin_config() as $type => $cfg) {
        if (!bin_table_exists($cfg['table'])) continue;
        $stmt = db()->query("SELECT id, {$cfg['label_col']} AS label, deleted_at FROM {$cfg['table']} WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
        foreach ($stmt->fetchAll() as $row) {
            $deletedAt = strtotime($row['deleted_at']);
            $daysLeft = max(0, 15 - (int)floor((time() - $deletedAt) / 86400));
            $items[] = [
                'type' => $type,
                'id' => (int)$row['id'],
                'label' => $row['label'],
                'deleted_at' => $row['deleted_at'],
                'days_left' => $daysLeft,
            ];
        }
    }
    usort($items, fn($a, $b) => strtotime($b['deleted_at']) <=> strtotime($a['deleted_at']));
    return $items;
}

// ---------- Form validation ----------
// Shared rules for the name/phone/email fields collected across public lead
// forms (contact, saved search, quick enquiry) and admin forms (Team,
// Testimonials, Manage Admins). Centralized so a typo like "1234" or
// "not-an-email" is rejected the same way everywhere, instead of each form
// re-implementing its own ad-hoc filter_var() call.

function validate_name(string $name): bool
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 120) return false;
    return (bool)preg_match('/\p{L}/u', $name); // must contain at least one letter — rejects "12345", "...", etc.
}

function validate_phone(string $phone): bool
{
    $digits = preg_replace('/\D/', '', $phone);
    // A bare 10-digit Indian mobile number, or the same with a "91" country
    // code prefix (any +/leading zero is already stripped above).
    return strlen($digits) === 10 || (strlen($digits) === 12 && str_starts_with($digits, '91'));
}

/**
 * "98765 43210" / "09876543210" / "+91-9876543210" -> "+919876543210".
 * Null when it isn't a plausible Indian mobile (the AI dialer needs this
 * canonical form in enquiries.phone_e164).
 */
function normalize_to_e164_in(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return null;
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10 || !preg_match('/^[6-9]/', $digits)) {
        return null;
    }
    return '+91' . $digits;
}

function validate_email(string $email): bool
{
    // Email is optional everywhere it's collected — blank is valid; only a
    // non-blank value that isn't a real address is rejected.
    $email = trim($email);
    return $email === '' || (mb_strlen($email) <= 160 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
}

// ---------- Public form spam protection ----------
// Two independent, no-external-service layers for public lead forms (contact,
// saved search, quick enquiry): a per-IP rate limit and a honeypot field.

// Logs this attempt and returns false once an IP has made more than
// $maxAttempts requests to $endpoint within $windowMinutes. Every attempt is
// logged (even ones later rejected by validation) so a bot can't dodge the
// limit by sending deliberately-invalid data. There's no cron, so old rows
// are purged opportunistically (~1 in 20 calls) instead.
/**
 * The visitor's real IP. On Railway every request arrives from an internal proxy (100.64.0.0/10),
 * which appends the real client as the LAST X-Forwarded-For entry — the one a visitor can't spoof.
 * The header is only trusted when the request really came from that proxy range.
 */
function client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $fromProxy = (bool)preg_match('/^100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\./', $remote);
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($fromProxy && $xff !== '') {
        $parts = array_map('trim', explode(',', $xff));
        $last = end($parts);
        if (filter_var($last, FILTER_VALIDATE_IP)) return $last;
    }
    return $remote;
}

function rate_limit_check(string $endpoint, int $maxAttempts = 5, int $windowMinutes = 10): bool
{
    // Per real visitor, not per Railway proxy node (all visitors used to share ~20 proxy addresses).
    $ip = client_ip();
    db()->prepare('INSERT INTO form_submission_log (ip_address, endpoint) VALUES (?, ?)')->execute([$ip, $endpoint]);

    if (mt_rand(1, 20) === 1) {
        db()->exec("DELETE FROM form_submission_log WHERE created_at < (NOW() - INTERVAL 1 DAY)");
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM form_submission_log WHERE ip_address = ? AND endpoint = ? AND created_at > (NOW() - INTERVAL ' . (int)$windowMinutes . ' MINUTE)');
    $stmt->execute([$ip, $endpoint]);
    return (int)$stmt->fetchColumn() <= $maxAttempts;
}

// True if the honeypot field was filled in — a real visitor never sees or
// fills it (hidden off-screen, named to look like a normal field to a bot's
// generic form-filler). Callers should respond with the same success message
// as a real submission without actually saving anything, so the bot never
// learns it was detected.
function honeypot_tripped(): bool
{
    return trim($_POST['company_website'] ?? '') !== '';
}

// ---------- Shared public lead-form endpoint sequence ----------
// Both /api/enquiry-submit.php and /api/saved-search-submit.php ran this
// exact same guard sequence copy-pasted per file: JSON response helper,
// method check, honeypot, rate limit, then name/phone/email validation.
// Centralized here so a new validation rule (or a bug in one) only needs
// fixing once.

/** Campaign tag sent by the forms' hidden `campaign` field (utm_source/medium/campaign), or null if absent/odd. */
function public_form_campaign(): ?string
{
    $c = trim((string)($_POST['campaign'] ?? ''));
    return ($c !== '' && preg_match('/^[A-Za-z0-9 _.\\/|:+-]{1,80}$/', $c)) ? $c : null;
}

function respond_json(bool $ok, string $message): void
{
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

/**
 * Runs every guard a public lead-capture endpoint needs before its own
 * INSERT: POST-only, honeypot, rate limit, then name/phone/email validation.
 * Calls respond_json() and exits on any failure — only returns (with the
 * validated/trimmed name/phone/email) once every guard has passed.
 *
 * $endpoint: rate-limit bucket name, also logged as the endpoint column in
 * form_submission_log.
 * $honeypotSuccessMessage: the fake-success message shown to a bot that
 * tripped the honeypot — must match that form's real success message so a
 * bot can't tell it was caught.
 */
function validate_public_lead_form(string $endpoint, string $honeypotSuccessMessage): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        respond_json(false, 'Invalid request method.');
    }
    if (honeypot_tripped()) {
        respond_json(true, $honeypotSuccessMessage);
    }
    if (!rate_limit_check($endpoint)) {
        respond_json(false, 'Too many requests — please try again in a few minutes.');
    }

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!validate_name($name)) {
        respond_json(false, 'Please enter your full name.');
    }
    if (mb_strlen($phone) > 30 || !validate_phone($phone)) {
        respond_json(false, 'Please enter a valid 10-digit phone number.');
    }
    if (!validate_email($email)) {
        respond_json(false, 'That email address doesn\'t look right.');
    }

    return ['name' => $name, 'phone' => $phone, 'email' => $email];
}

// ---------- Pagination ----------
// Reusable across any admin list. Reads page/per_page from the query string
// (per_page must be one of $perPageOptions or it falls back to the default —
// never trust an arbitrary value straight into a LIMIT clause).
function paginate_params(int $totalRows, int $defaultPerPage = 10, array $perPageOptions = [5, 10, 25, 50]): array
{
    $perPage = (int)($_GET['per_page'] ?? $defaultPerPage);
    if (!in_array($perPage, $perPageOptions, true)) {
        $perPage = $defaultPerPage;
    }
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
    return [
        'page' => $page, 'per_page' => $perPage, 'per_page_options' => $perPageOptions,
        'total_pages' => $totalPages, 'offset' => ($page - 1) * $perPage, 'total' => $totalRows,
    ];
}

// Same as paginate_params(), but for pages built on .admin-card-grid (a
// fixed 3-column grid) — Per Page values are multiples of 3 so every page
// fills complete rows, instead of leaving 1-2 empty slots in the last row
// whenever the count doesn't divide evenly into 3.
function paginate_params_grid(int $totalRows): array
{
    return paginate_params($totalRows, 9, [3, 6, 9, 12, 24]);
}

function render_pagination(array $p, string $basePath, array $extraParams = []): string
{
    if ($p['total'] <= 0) return '';
    $mk = function (array $overrides) use ($p, $basePath, $extraParams) {
        return $basePath . '?' . http_build_query(array_merge($extraParams, ['page' => $p['page'], 'per_page' => $p['per_page']], $overrides));
    };
    $from = ($p['page'] - 1) * $p['per_page'] + 1;
    $to = min($p['page'] * $p['per_page'], $p['total']);

    $out = '<div class="pagination">';
    $out .= '<div class="pagination-info">Showing ' . $from . '–' . $to . ' of ' . $p['total'] . '</div>';

    if ($p['total_pages'] > 1) {
        $out .= '<div class="pagination-controls">';
        $prevDisabled = $p['page'] <= 1;
        $out .= '<a href="' . e($mk(['page' => max(1, $p['page'] - 1)])) . '" class="page-btn' . ($prevDisabled ? ' disabled' : '') . '"' . ($prevDisabled ? ' aria-disabled="true"' : '') . '>&lsaquo; Prev</a>';
        for ($i = 1; $i <= $p['total_pages']; $i++) {
            $out .= '<a href="' . e($mk(['page' => $i])) . '" class="page-btn' . ($i === $p['page'] ? ' active' : '') . '">' . $i . '</a>';
        }
        $nextDisabled = $p['page'] >= $p['total_pages'];
        $out .= '<a href="' . e($mk(['page' => min($p['total_pages'], $p['page'] + 1)])) . '" class="page-btn' . ($nextDisabled ? ' disabled' : '') . '"' . ($nextDisabled ? ' aria-disabled="true"' : '') . '>Next &rsaquo;</a>';
        $out .= '</div>';
    }

    $out .= '<div class="pagination-perpage"><label for="pp-select">Per page</label><select id="pp-select" onchange="location.href=this.value">';
    foreach ($p['per_page_options'] as $opt) {
        $out .= '<option value="' . e($mk(['per_page' => $opt, 'page' => 1])) . '"' . ($opt === $p['per_page'] ? ' selected' : '') . '>' . $opt . '</option>';
    }
    $out .= '</select></div>';
    $out .= '</div>';
    return $out;
}

function bin_count(): int
{
    $total = 0;
    foreach (bin_config() as $cfg) {
        if (!bin_table_exists($cfg['table'])) continue;
        $total += (int)db()->query("SELECT COUNT(*) FROM {$cfg['table']} WHERE deleted_at IS NOT NULL")->fetchColumn();
    }
    return $total;
}

// Lets code that knows about newer tables (e.g. places, from migration 0014)
// run safely against a database where that migration hasn't been applied yet.
function bin_table_exists(string $table): bool
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $cache[$table] = (int)$stmt->fetchColumn() > 0;
    }
    return $cache[$table];
}

function bin_type_label(string $type): string
{
    return ['property' => 'Property', 'blog_post' => 'Blog Post', 'team_member' => 'Team Member', 'testimonial' => 'Testimonial', 'corridor' => 'Corridor', 'place' => 'Nearby Place'][$type] ?? $type;
}

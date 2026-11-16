<?php
/**
 * Remote-only capability endpoint for trusted /share/remote links.
 *
 * Shares are verified from the existing share_secret / HMAC on every call.
 * There is deliberately NO users.token, PHP browser login, or database migration.
 * Ordinary device clients keep using /api/remote unchanged.
 */
define('RATEL_API_REQUEST', true);
define('RATEL_PUBLIC_SHARE_REQUEST', true);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

// Same-origin XHR/fetch only. No CORS response headers and no credentials.
// An HTML form on a foreign site cannot set X-Requested-With.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    echo 'Access denied';
    exit;
}
$fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if ($fetchSite !== '' && $fetchSite !== 'same-origin' && $fetchSite !== 'none') {
    http_response_code(403);
    echo 'Access denied';
    exit;
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originParts = parse_url($origin);
    $originHost = is_array($originParts) ? ($originParts['host'] ?? '') : '';
    $requestHost = $_SERVER['HTTP_HOST'] ?? '';
    $requestHostParts = parse_url('http://' . $requestHost);
    $serverHost = is_array($requestHostParts) ? ($requestHostParts['host'] ?? '') : '';
    $originScheme = is_array($originParts) ? strtolower((string)($originParts['scheme'] ?? '')) : '';
    $originPort = is_array($originParts) ? ($originParts['port'] ?? null) : null;
    $serverPort = is_array($requestHostParts) ? ($requestHostParts['port'] ?? null) : null;
    // Explicit :443/:80 on Host is equivalent to the default URL port.
    $defaultPort = $originScheme === 'https' ? 443 : ($originScheme === 'http' ? 80 : null);
    $effectiveOriginPort = $originPort ?? $defaultPort;
    if ($originHost === '' || $serverHost === '' || $defaultPort === null
        || strcasecmp($originHost, $serverHost) !== 0
        || ($serverPort !== null && $effectiveOriginPort !== $serverPort)) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}
if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/x-www-form-urlencoded')) {
    http_response_code(415);
    exit;
}

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/translations.php';

$fail = static function (int $status, string $message = 'Access denied'): never {
    http_response_code($status);
    echo $message;
    exit;
};

$lang = $_POST['lang'] ?? 'en';
if (!is_string($lang) || !isset($translations[$lang])) {
    $lang = 'en';
}
if (is_maintenance()) {
    $fail(423, $translations[$lang]['maintenance'] ?? 'Maintenance');
}

$shareId = $_POST['u'] ?? null;
$sig = $_POST['sig'] ?? null;
$data = $_POST['data'] ?? null;
if (!is_string($shareId) || !preg_match('/^[a-f0-9]{32}$/', $shareId)
    || !is_string($sig) || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
    $fail(403);
}
if (!is_string($data) || $data === '' || strlen($data) > 2048) {
    $fail(400, 'Invalid data');
}

$owner = share_lookup_user($db, $shareId);
if (!$owner || (int)$owner['s'] === 0) {
    $fail(403);
}
$secret = (string)$owner['share_secret'];
$expected = hash_hmac('sha256', share_payload_remote($shareId), $secret);
if (!hash_equals($expected, $sig)) {
    $fail(403);
}
$ownerId = (int)$owner['id'];

if ($data === 'fetch') {
    // The share must still be valid at read time (including concurrent revocation).
    $row = $db->execute_query(
        'SELECT mcu_data FROM users WHERE id=? AND share_id=? AND share_secret=? AND s<>0',
        [$ownerId, $shareId, $secret]
    )->fetch_assoc();
    if (!$row) {
        $fail(403);
    }
    $mcuData = $row['mcu_data'] ?? '';
    if (!is_string($mcuData) || strlen($mcuData) < 824) {
        http_response_code(204);
        exit;
    }
    [$ok] = validateMcuData($mcuData);
    if (!$ok) {
        http_response_code(204);
        exit;
    }
    echo $mcuData;
    exit;
}

// Only the same 404-byte remote MCU configuration accepted by /api/remote.
[$ok] = validateMcuData($data);
if (!$ok) {
    $fail(400, 'Invalid data');
}
$parts = explode(',', $data);
$serverTs = (int)(microtime(true) * 1000);
$parts[405] = (string)$serverTs;
$data = implode(',', $parts);

// Atomic revocation: rotating share_secret invalidates even an in-flight update
// after HMAC validation, without rotating the main device API token.
$db->execute_query(
    'UPDATE users SET mcu_data=? WHERE id=? AND share_id=? AND share_secret=? AND s<>0',
    [$data, $ownerId, $shareId, $secret]
);
if ($db->affected_rows !== 1) {
    $fail(403);
}
echo (string)$serverTs;
exit;

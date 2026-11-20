<?php
// Public links grant access to one signed resource, not a browser account.
// Do not cache the capability-bearing URL or reveal it in Referer headers.
header('Cache-Control: no-store, private, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
if (!defined('RATEL_PUBLIC_SHARE_REQUEST')) {
    define('RATEL_PUBLIC_SHARE_REQUEST', true);
}
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
include_once __DIR__ . '/translations.php';
$lang = $_COOKIE['lang'] ?? 'en';

if (!checkRateLimit(5)) {
    header('Location: /catch?c=block');
    exit;
}

require_once __DIR__ . '/timezone.php';

$share_id = $_GET['u']   ?? null;
$sig      = $_GET['sig'] ?? null;

if (!is_string($share_id) || $share_id === ''
    || !is_string($sig) || $sig === '') {
    header('Location: /');
    exit;
}

$user_data = share_lookup_user($db, $share_id);
if (!$user_data) {
    header('Location: /catch?c=noshare');
    exit;
}

if ((int)$user_data['s'] === 0) {
    header('Location: /catch?c=noshare');
    exit;
}

$share_secret = $user_data['share_secret'];
$user_time    = $user_data['time'];
$user_id      = (int)$user_data['id'];
$uid          = $share_id; // used by remote_inc.php as "share mode" flag

$payload      = share_payload_remote($share_id);
$expected_sig = hash_hmac('sha256', $payload, $share_secret);

if (!hash_equals($expected_sig, $sig)) {
    header('Location: /catch?c=noshare');
    exit;
}

// Only the remote configuration is needed to render a shared page.
// Never fetch or expose users.token: that credential authorizes other device APIs.
$remote = $db->execute_query(
    "SELECT mcu_data FROM users WHERE id = ?",
    [$user_id]
)->fetch_assoc();

$mcu_data = (string)($remote['mcu_data'] ?? '');

// Keep shared-device formatting isolated from the viewer's account.
setcookie('timeformat', $user_time, 0, '/share');
$_COOKIE['timeformat'] = $user_time;

// Validate mcu_data (404 bytes + '~' + 13-digit ms timestamp)
$array   = explode(',', $mcu_data);
$isValid = true;

if (count($array) !== 406) {
    $isValid = false;
}

$secondLast = $array[count($array) - 2] ?? null;
if ($secondLast !== '~') {
    $isValid = false;
}

$lastElement = $array[count($array) - 1] ?? null;
$lastElement = (int)$lastElement / 1000;
if (!is_numeric($lastElement) || $lastElement <= 0) {
    $isValid = false;
} else {
    $timestamp   = (int)$lastElement;
    $currentTime = time();
    if ($timestamp > $currentTime + 3600) {
        $isValid = false;
    }
    if ($timestamp < 1609459200) {
        $isValid = false;
    }
}

$data = null;
if ($isValid) {
    $dataArray = array_slice($array, 0, -2);
    $data      = implode(",", $dataArray);
}

checkRateLimit(5, 3600, true);

include_once __DIR__ . '/src/head.php';
include_once __DIR__ . '/src/remote_inc.php';

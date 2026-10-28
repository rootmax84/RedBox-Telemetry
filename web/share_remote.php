<?php
$_SESSION['torque_logged_in'] = true;
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
include_once __DIR__ . '/translations.php';
$lang = $_COOKIE['lang'] ?? 'en';

if (!checkRateLimit(5)) {
    if (!isset($_SESSION['torque_user'])) {
        unset($_SESSION['torque_logged_in'], $_SESSION['share']);
    }
    header('Location: /catch?c=block');
    exit;
}

require_once __DIR__ . '/timezone.php';

$share_id = $_GET['u']   ?? null;
$sig      = $_GET['sig'] ?? null;

if (!is_string($share_id) || $share_id === ''
    || !is_string($sig) || $sig === '') {
    if (!isset($_SESSION['torque_user'])) {
        unset($_SESSION['torque_logged_in'], $_SESSION['share']);
    }
    header('Location: /');
    exit;
}

$user_data = share_lookup_user($db, $share_id);
if (!$user_data) {
    if (!isset($_SESSION['torque_user'])) {
        unset($_SESSION['torque_logged_in'], $_SESSION['share']);
    }
    header('Location: /catch?c=noshare');
    exit;
}

if ((int)$user_data['s'] === 0) {
    if (!isset($_SESSION['torque_user'])) {
        unset($_SESSION['torque_logged_in'], $_SESSION['share']);
    }
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
    if (!isset($_SESSION['torque_user'])) {
        unset($_SESSION['torque_logged_in'], $_SESSION['share']);
    }
    header('Location: /catch?c=noshare');
    exit;
}

// token + mcu_data — отдельный запрос (не тащим их в share_lookup_user)
$remote = $db->execute_query(
    "SELECT token, mcu_data FROM users WHERE id = ?",
    [$user_id]
)->fetch_assoc();

$token    = $remote['token']    ?? null;
$mcu_data = (string)($remote['mcu_data'] ?? '');

setcookie('timeformat', $user_time);
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

$_SESSION['remote_token'] = $token;
checkRateLimit(5, 3600, true);
$_SESSION['share'] = true;

include_once __DIR__ . '/src/head.php';
include_once __DIR__ . '/src/remote_inc.php';

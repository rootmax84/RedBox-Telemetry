<?php
include_once __DIR__ . '/src/helpers.php';
include_once __DIR__ . '/translations.php';
require_once __DIR__ . '/src/methods.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Authorization, Content-Type');
header('Access-Control-Max-Age: 86400');

allowMethods('POST', 'OPTIONS');

/* =========================
 *  RAW INPUT & MAINTENANCE
 * ========================= */

$rawData = $_POST['data'] ?? '';
$rawLang = $_POST['lang'] ?? 'en';

$safeLang = is_string($rawLang) ? $rawLang : 'en';

if (file_exists(__DIR__ . '/maintenance')) {
    http_response_code(423);
    $msg = $translations[$safeLang]['maintenance']
        ?? ($translations['en']['maintenance'] ?? 'Maintenance');
    echo $msg;
    exit;
}

/* =========================
 *  TYPE CHECKS
 * ========================= */

if (!is_string($rawData) || !is_string($rawLang)) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$data = $rawData;
$lang = $rawLang;

$allowedLangs = array_keys($translations);
if (!in_array($lang, $allowedLangs, true)) {
    $lang = 'en';
}

if (empty($_POST) || $data === '') {
    http_response_code(400);
    echo 'No data provided';
    exit;
}

/* =========================
 *  TOKEN
 * ========================= */

$bearer = getBearerToken();

if (is_string($bearer) && $bearer !== '') {
    define('RATEL_API_REQUEST', true);
    $token = $bearer;
}

if (empty($token) || !is_string($token)
    || strlen($token) < 16 || strlen($token) > 128) {
    http_response_code(403);
    echo $translations[$lang]['denied'] ?? 'Access denied';
    exit;
}

/* =========================
 *  DB
 * ========================= */

require_once __DIR__ . '/src/db.php';

$row = $db->execute_query(
    "SELECT mcu_data, s FROM users WHERE token=?",
    [$token]
)->fetch_assoc();

if (!$row) {
    http_response_code(403);
    echo $translations[$lang]['denied'] ?? 'Access denied';
    exit;
}

$mcu_data = $row['mcu_data'] ?? '';
$s        = (int)$row['s'];

if ($s === 0) {
    http_response_code(403);
    echo $translations[$lang]['denied'] ?? 'Access denied';
    exit;
}

/* =========================
 *  FETCH
 * ========================= */

if ($data === 'fetch') {
    if (!is_string($mcu_data) || strlen($mcu_data) < 824) {
        http_response_code(204);
        exit;
    }

    [$ok] = validateMcuData($mcu_data);
    if (!$ok) {
        // stored data is corrupt — do not leak it
        http_response_code(204);
        exit;
    }

    echo $mcu_data;
    $db->close();
    exit;
}

/* =========================
 *  UPDATE
 * ========================= */

if (strlen($data) > 2048) {
    http_response_code(400);
    echo 'Invalid data';
    exit;
}

[$ok] = validateMcuData($data);
if (!$ok) {
    http_response_code(400);
    echo 'Invalid data';
    exit;
}

// Серверный timestamp — единственный источник правды для ordering.
$parts      = explode(',', $data);
$serverTs   = (int)(microtime(true) * 1000);
$parts[405] = (string)$serverTs;
$data       = implode(',', $parts);

$db->execute_query("UPDATE users SET mcu_data=? WHERE token=?", [$data, $token]);
$db->close();

// Возвращаем клиенту присвоенный ts, чтобы он мог обновить last_srv_ts без лишнего fetch.
echo (string)$serverTs;
exit;

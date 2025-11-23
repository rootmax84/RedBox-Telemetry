<?php
include_once 'helpers.php';
include_once 'translations.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Authorization, Content-Type');
header('Access-Control-Max-Age: 86400');

$allowedMethods = ['POST', 'OPTIONS'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

if (!in_array($requestMethod, $allowedMethods)) {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

if ($requestMethod === 'OPTIONS') {
    header('Access-Control-Allow-Methods: ' . implode(", ", $allowedMethods));
    exit;
}

$data = $_POST['data'] ?? '';
$lang = $_POST['lang'] ?? 'en';

if (file_exists('maintenance')){
    http_response_code(423);
    echo $translations[$lang]['maintenance'];
    exit;
}

if (empty($_POST) || empty($data)) {
    http_response_code(400);
    echo 'No data provided';
    exit;
}

session_start();
$token = getBearerToken() ?? $_SESSION['remote_token'];
if (empty($token)) {
    http_response_code(403);
    echo $translations[$lang]['denied'];
    exit;
}

$_SESSION['torque_logged_in'] = true;
require_once 'db.php';

$row = $db->execute_query("SELECT mcu_data, s FROM $db_users WHERE token=?", [$token])->fetch_assoc();

if (!$row) {
    http_response_code(403);
    echo $translations[$lang]['denied'];
    exit;
}

$mcu_data = $row['mcu_data'] ?? '';
$s = (int)$row['s'];

if ($s === 0) {
    http_response_code(403);
    echo $translations[$lang]['denied'];
    exit;
}

if ($data === 'fetch') {
    if (strlen($mcu_data) < 824) {
        http_response_code(204);
        exit;
    }
    echo $mcu_data;
    $db->close();
    exit;
}

$parts = explode(',', $data);
if (strlen($data) > 2048 || count($parts) !== 406 || $parts[count($parts) - 2] !== '~') {
    http_response_code(400);
    //error_log('[REMOTE] Invalid data: ' . $data);
    echo 'Invalid data';
    exit;
}

$db->execute_query("UPDATE $db_users SET mcu_data=? WHERE token=?", [$data, $token]);
$db->close();
exit;

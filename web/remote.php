<?php
include 'helpers.php';
include 'translations.php';

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

if (empty($_POST)) {
    http_response_code(400);
    echo 'No data provided';
    exit;
}

$data = $_POST['data'] ?? '';
$lang = $_POST['lang'] ?? 'en';

if (file_exists('maintenance')){
    http_response_code(423);
    echo $translations[$lang]['maintenance'];
    exit;
}

if (empty($data)) {
    http_response_code(400);
    echo $translations[$lang]['denied'];
    exit;
}

$token = getBearerToken();
if (!empty($token)) {
    $_SESSION['torque_logged_in'] = true;
    require_once 'db.php';

    if ($data === 'fetch') {
        // SELECT запрос
        echo $db->execute_query("SELECT mcu_data FROM $db_users WHERE token=?", [$token])->fetch_assoc()['mcu_data'] ?? '';
    } else {
        // UPDATE запрос с проверкой длины
        if (!empty($data) && strlen($data) <= 2048) {
            $db->execute_query("UPDATE $db_users SET mcu_data=? WHERE token=?", [$data, $token]);
        } else {
            http_response_code(400);
            echo 'Data empty or too long!';
            exit;
        }
    }
    $db->close();
}

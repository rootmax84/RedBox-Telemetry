<?php
include 'translations.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Authorization, Content-Type');
header('Access-Control-Max-Age: 30');

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

$user = $_POST['user'] ?? '';
$pass = $_POST['pass'] ?? '';
$lang = $_POST['lang'] ?? 'en';

if (empty($user) || empty($pass)) {
    http_response_code(400);
    echo $translations[$lang]['required'];
    exit;
}

$_SESSION['torque_logged_in'] = true;
require_once 'auth_functions.php';

$db = get_db_connection();
global $db_users;

// Check user presence
$userqry = $db->execute_query("SELECT user, pass, token, s FROM $db_users WHERE user=?", [$user]);
if ($userqry->num_rows === 0) {
    http_response_code(401);
    echo $translations[$lang]['catch.loginfailed'];
    exit;
}

$row = $userqry->fetch_assoc();

// Check disabled user
if (!$row['s']) {
    http_response_code(403);
    echo $translations[$lang]['disabled'];
    exit;
}

// Login attempts
if (!check_login_attempts($user)) {
    http_response_code(403);
    echo $translations[$lang]['blocked'];
    exit;
}

// Password check
if (!password_verify($pass, $row['pass'])) {
    update_login_attempts($user, false);
    http_response_code(401);
    echo $translations[$lang]['catch.loginfailed'];
    exit;
}

// Token presence
if ($row['token'] === NULL) {
    http_response_code(406);
    echo $translations[$lang]['gen_token'];
    exit;
}

// Success
update_login_attempts($user, true);
echo $row['token'];

$db->close();

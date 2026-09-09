<?php
include __DIR__ . '/translations.php';
require_once __DIR__ . '/src/methods.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Authorization, Content-Type');
header('Access-Control-Max-Age: 30');

allowMethods('POST', 'OPTIONS');

if (empty($_POST)) {
    http_response_code(400);
    echo 'No data provided';
    exit;
}

$user = $_POST['user'] ?? '';
$pass = $_POST['pass'] ?? '';
$lang = $_POST['lang'] ?? 'en';

if (file_exists('maintenance')){
    http_response_code(423);
    echo $translations[$lang]['maintenance'];
    exit;
}

if (empty($user) || empty($pass)) {
    http_response_code(400);
    echo $translations[$lang]['required'];
    exit;
}

$_SESSION['torque_logged_in'] = true;
require_once __DIR__ . '/src/auth_functions.php';
require_once __DIR__ . '/src/db.php';

$db = get_db_connection();
global $db_users;

// Check user presence
$userqry = $db->execute_query("SELECT user, pass, s FROM $db_users WHERE user=?", [$user]);
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

// Generate new token
try {
    $token = $db->execute_query("SELECT token FROM $db_users WHERE user=?", [$user])->fetch_assoc()["token"];
    cache_flush($token);
    $token = generate_token($user);
    $db->execute_query("UPDATE $db_users SET token=? WHERE user=?", [$token, $user]);
} catch(Exception $e) {
    http_response_code(500);
    echo $translations[$lang]['dialog.token.err.msg'];
    exit;
}

// Success
update_login_attempts($user, true);
echo $token;

$db->close();

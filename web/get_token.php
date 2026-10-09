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

if (is_maintenance()){
    http_response_code(423);
    echo $translations[$lang]['maintenance'];
    exit;
}

if (empty($user) || empty($pass)) {
    http_response_code(400);
    echo $translations[$lang]['required'];
    exit;
}

define('RATEL_API_REQUEST', true);
require_once __DIR__ . '/src/auth_functions.php';
require_once __DIR__ . '/src/db.php';

$fail = function (int $code, string $msg): void {
    http_response_code($code);
    echo $msg;
    exit;
};

$db = get_db_connection();

// Check user presence
$userqry = $db->execute_query("SELECT user, pass, s FROM users WHERE user=?", [$user]);
if ($userqry->num_rows === 0) {
    $fail(401, $translations[$lang]['catch.loginfailed']);
}

$row = $userqry->fetch_assoc();

// Check disabled user
if (!$row['s']) {
    $fail(403, $translations[$lang]['disabled']);
}

// Login attempts
if (!check_login_attempts($user)) {
    $fail(403, $translations[$lang]['blocked']);
}

// Password check
if (!password_verify($pass, $row['pass'])) {
    update_login_attempts($user, false);
    $fail(401, $translations[$lang]['catch.loginfailed']);
}

// Generate new token
try {
    $old_token = $db->execute_query(
        "SELECT token FROM users WHERE user=?",
        [$user]
    )->fetch_assoc()["token"];
    cache_flush($old_token);
    $token = generate_token($user);
    $db->execute_query("UPDATE users SET token=? WHERE user=?", [$token, $user]);
} catch (Exception $e) {
    $fail(500, $translations[$lang]['dialog.token.err.msg']);
}

update_login_attempts($user, true);

echo $token;

$db->close();

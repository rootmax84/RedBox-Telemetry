<?php
if (empty($_COOKIE['stream'])) http_response_code(401);
require_once('creds.php');
if (file_exists('maintenance')) http_response_code(307);

if (file_exists(sys_get_temp_dir().'/'.$username)) {
    setcookie("newsess", true, time() + 3, "/");
    unlink(sys_get_temp_dir().'/'.$username);
} else {
    setcookie("newsess", true, time(), "/");
}

if (isset($_GET["update-csrf-token"])) {
    $token = generate_csrf_token();
    echo json_encode([
        'token' => $token,
        'expiry' => $_SESSION['csrf_token_time'] + 3300
    ]);
    exit;
}

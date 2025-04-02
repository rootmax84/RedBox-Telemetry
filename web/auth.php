<?php
if (empty($_COOKIE['stream'])) header('HTTP/1.0 401 Unauthorized');
require_once('creds.php');
if (file_exists('maintenance')) header('HTTP/1.0 307 Temporary Redirect');

if (isset($_GET["update-csrf-token"])) {
    $token = generate_csrf_token();
    echo json_encode([
        'token' => $token,
        'expiry' => $_SESSION['csrf_token_time'] + 3300
    ]);
    exit;
}

?>

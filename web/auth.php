<?php
if (empty($_COOKIE['stream'])) {
    http_response_code(401);
}

require_once __DIR__ . '/src/creds.php';
require_once __DIR__ . '/src/methods.php';
require_once __DIR__ . '/src/db.php';
allowMethods('HEAD', 'POST');

if (file_exists('maintenance')) {
    http_response_code(307);
}

$newSessionSignalled = false;

if (!empty($memcached_connected) && isset($memcached)) {
    try {
        if ($memcached->get("new_session_" . $username)) {
            $memcached->delete("new_session_" . $username);
            $newSessionSignalled = true;
        }
    } catch (Throwable $e) {
        error_log("Memcached error on new-session check: " . $e->getMessage());
    }
}

if ($newSessionSignalled) {
    setcookie("newsess", true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['action']) && $input['action'] === 'update-csrf-token') {
        $token = generate_csrf_token();
        echo json_encode([
            'token' => $token,
            'expiry' => $_SESSION['csrf_token_time'] + 3300
        ]);
        exit;
    }
}

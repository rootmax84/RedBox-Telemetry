<?php
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/methods.php';
allowMethods('POST');

header('Content-Type: application/json');

if (empty($username)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

if (empty($data['uid'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$uid = (int)$data['uid'];

if (isset($data['id'])) {
    $id = (string)$data['id'];
    $payload = "uid={$uid}&id={$id}";
} else {
    $payload = "uid={$uid}";
}

if (empty($_SESSION['share_secret'])) {
    $secret = bin2hex(random_bytes(16));
    $db->execute_query(
        "UPDATE users SET share_secret = ? WHERE user = ?",
        [$secret, $username]
    );
    $_SESSION['share_secret'] = $secret;
} else {
    $secret = $_SESSION['share_secret'];
}

$signature = hash_hmac('sha256', $payload, $secret);

echo json_encode(['signature' => $signature]);

<?php
require_once 'db.php';

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['uid'], $data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$uid = $data['uid'];
$id = $data['id'];
$payload = "uid={$uid}&id={$id}";

if ((int)$uid !== $_SESSION['uid']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (empty($_SESSION['share_secret'])) {
    $secret = bin2hex(random_bytes(16));
    $db->execute_query("UPDATE $db_users SET share_secret=? WHERE user=?", [$secret, $username]);
    $_SESSION['share_secret'] = $secret;
} else {
    $secret = $_SESSION['share_secret'];
}

$signature = hash_hmac('sha256', $payload, $secret);

echo json_encode(['signature' => $signature]);

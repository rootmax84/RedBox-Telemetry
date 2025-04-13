<?php
require_once('db.php');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['uid'], $data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$uid = $data['uid'];
$id = $data['id'];

$payload = "uid={$uid}&id={$id}";

$secret = $_SESSION['share_secret'];

if (empty($_SESSION['share_secret'])) {
    $secret = bin2hex(random_bytes(16));
    $db->execute_query("UPDATE $db_users SET share_secret=? WHERE user=?", [$secret, $username]);
    $_SESSION['share_secret'] = $secret;
}

$signature = hash_hmac('sha256', $payload, $secret);

echo json_encode(['signature' => $signature]);
?>
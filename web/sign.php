<?php
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/methods.php';
require_once __DIR__ . '/src/helpers.php';

allowMethods('POST');

header('Content-Type: application/json');

if (empty($username)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$user_id = current_user_id();
if (!$user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$row = $db->execute_query(
    "SELECT share_secret FROM users WHERE id = ?",
    [$user_id]
)->fetch_assoc();

if (!$row) {
    http_response_code(500);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$secret = (string)($row['share_secret'] ?? '');

if ($secret === '') {
    $secret = bin2hex(random_bytes(16));
    $db->execute_query(
        "UPDATE users SET share_secret = ? WHERE id = ?",
        [$secret, $user_id]
    );
}

$_SESSION['share_secret'] = $secret;

$share_id = share_id_from_secret($secret);

$id = isset($data['id']) && $data['id'] !== ''
    ? (string)$data['id']
    : null;

$payload = $id !== null
    ? share_payload_session($share_id, $id)
    : share_payload_remote($share_id);

$signature = hash_hmac('sha256', $payload, $secret);

echo json_encode([
    'signature' => $signature,
    'share'     => $share_id,
]);

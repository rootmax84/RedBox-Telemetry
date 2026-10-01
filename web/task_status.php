<?php
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/methods.php';
require_once __DIR__ . '/src/heavy_tasks.php';

header('Content-Type: application/json');
allowMethods('GET');

$task_id = $_GET['id'] ?? '';
if (!is_string($task_id) || !preg_match('/^[a-f0-9]{32}$/', $task_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_task_id']);
    exit;
}

$task = heavy_task_get($task_id);
if ($task === null) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$owner_uid   = (int)($task['owner_user_id'] ?? 0);
$current_uid = (int)($_SESSION['uid'] ?? 0);
$is_admin    = !empty($_SESSION['admin']);

if ($owner_uid !== $current_uid && !$is_admin) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

echo json_encode([
    'status'      => $task['status'] ?? 'unknown',
    'type'        => $task['type']   ?? null,
    'result'      => isset($task['result']) ? json_decode($task['result'], true) : null,
    'error'       => $task['error']  ?? null,
    'created_at'  => isset($task['created_at'])  ? (int)$task['created_at']  : null,
    'started_at'  => isset($task['started_at'])  ? (int)$task['started_at']  : null,
    'finished_at' => isset($task['finished_at']) ? (int)$task['finished_at'] : null,
]);

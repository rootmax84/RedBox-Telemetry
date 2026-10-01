<?php
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/methods.php';
require_once __DIR__ . '/src/heavy_tasks.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
allowMethods('GET');

$uid = (int)($_SESSION['uid'] ?? 0);
if ($uid <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

/* Мягкий кэш на 2 сек: при 10 открытых вкладках не душим Redis.
 * Опрос идёт раз в 5 сек, кэш на 2 сек — норм. */
global $memcached, $memcached_connected;
$cache_key = "user_tasks_{$uid}";

if ($memcached_connected) {
    $cached = $memcached->get($cache_key);
    if (is_array($cached)) {
        echo json_encode(['tasks' => $cached]);
        exit;
    }
}

$tasks = heavy_user_tasks_list($uid);

if ($memcached_connected) {
    try { $memcached->set($cache_key, $tasks, 2); } catch (Throwable $e) {}
}

echo json_encode(['tasks' => $tasks]);

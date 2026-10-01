<?php
/**
 * Heavy tasks — producer helpers, chunked deletion и consumer-handler.
 *
 * Используется:
 *   - del_sessions.php / users_handler.php — heavy_task_push() (producer)
 *   - task_status.php — heavy_task_get() (reader)
 *   - worker.php — heavy_process_task() (consumer, в том же процессе)
 *
 * Heartbeat: worker.php кладёт в $GLOBALS['worker_heartbeat_cb'] callable,
 * который обновляет ключ worker:hb:<consumer> с TTL. Мы вызываем его на
 * каждом чанке удаления, чтобы админка видела воркер живым во время
 * длинных задач.
 */

require_once __DIR__ . '/redis.php';

$redis_heavy_stream_key    ??= 'ratel:heavy_tasks';
$redis_heavy_stream_maxlen ??= 10000;
$heavy_tasks_enabled       ??= true;
$heavy_task_ttl            ??= 86400;    // 24h
$heavy_chunk_size          ??= 5000;     // строк за один чанк DELETE
$heavy_chunk_pause_us      ??= 100000;   // 100ms пауза между чанками

/** Обновить heartbeat воркера, если callback зарегистрирован. */
function heavy_heartbeat(): void
{
    if (isset($GLOBALS['worker_heartbeat_cb'])
        && is_callable($GLOBALS['worker_heartbeat_cb'])) {
        ($GLOBALS['worker_heartbeat_cb'])();
    }
}

function heavy_task_key(string $task_id): string
{
    return 'ratel:task:' . $task_id;
}

/**
 * Поставить задачу в очередь.
 * @return string|null task_id, либо null если Redis недоступен/выключен.
 */
function heavy_task_push(string $type, array $payload, ?int $owner_user_id,
                         ?Redis $redis = null): ?string
{
    global $redis_heavy_stream_key, $redis_heavy_stream_maxlen,
           $heavy_tasks_enabled, $heavy_task_ttl;

    if (empty($heavy_tasks_enabled)) return null;

    if ($redis === null) $redis = get_redis_connection();
    if ($redis === null) return null;

    $task_id = bin2hex(random_bytes(16));
    $hash    = heavy_task_key($task_id);

    try {
        $redis->hMSet($hash, [
            'status'        => 'queued',
            'type'          => $type,
            'owner_user_id' => (string)($owner_user_id ?? 0),
            'created_at'    => (string)time(),
        ]);
        $redis->expire($hash, (int)$heavy_task_ttl);

        $key    = $redis_heavy_stream_key   ?? 'ratel:heavy_tasks';
        $maxlen = (int)($redis_heavy_stream_maxlen ?? 0);

        $id = $redis->xAdd($key, '*', [
            'task_id'       => $task_id,
            'type'          => $type,
            'owner_user_id' => (string)($owner_user_id ?? 0),
            'payload'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'received'      => (string)microtime(true),
        ], $maxlen, true);

        if ($id === false) {
            try { $redis->del($hash); } catch (Throwable $e) {}
            return null;
        }

        // Индексируем задачу для пользователя — чтобы её увидели все его вкладки
        heavy_user_tasks_add((int)($owner_user_id ?? 0), $task_id, $redis);

        return $task_id;
    } catch (Throwable $e) {
        error_log('heavy_task_push: ' . $e->getMessage());
        return null;
    }
}

function heavy_task_update(string $task_id, string $status,
                           ?array $result = null, ?string $error = null,
                           ?Redis $redis = null): void
{
    global $heavy_task_ttl;

    if ($redis === null) $redis = get_redis_connection();
    if ($redis === null) return;

    try {
        $hash   = heavy_task_key($task_id);
        $update = ['status' => $status];

        if ($status === 'running')                      $update['started_at']  = (string)time();
        if ($status === 'done' || $status === 'failed') $update['finished_at'] = (string)time();
        if ($result !== null) $update['result'] = json_encode($result, JSON_UNESCAPED_UNICODE);
        if ($error  !== null) $update['error']  = $error;

        $redis->hMSet($hash, $update);
        $redis->expire($hash, (int)$heavy_task_ttl);

        // Снимаем задачу с индекса пользователя при завершении
        if ($status === 'done' || $status === 'failed') {
            try {
                $owner = (int)($redis->hGet($hash, 'owner_user_id') ?: 0);
                if ($owner > 0) {
                    heavy_user_tasks_remove($owner, $task_id, $redis);
                }
            } catch (Throwable $e) {
                error_log('heavy_task_update: remove from user index: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('heavy_task_update: ' . $e->getMessage());
    }
}

function heavy_task_get(string $task_id, ?Redis $redis = null): ?array
{
    if ($redis === null) $redis = get_redis_connection();
    if ($redis === null) return null;

    try {
        $data = $redis->hGetAll(heavy_task_key($task_id));
        return empty($data) ? null : $data;
    } catch (Throwable $e) {
        error_log('heavy_task_get: ' . $e->getMessage());
        return null;
    }
}

/* ────────────────────────────────────────────────────────────
 * Chunked deletion
 *
 * SELECT time LIMIT N → DELETE WHERE time IN — порционно,
 * с паузой между чанками. На каждом чанке дёргаем heartbeat,
 * чтобы админка видела воркер живым.
 * ──────────────────────────────────────────────────────────── */

function heavy_delete_logs_by_sessions(mysqli $db, int $user_id, array $session_ids): int
{
    global $heavy_chunk_size, $heavy_chunk_pause_us;

    if (empty($session_ids)) return 0;

    $chunk_size = max(100, (int)$heavy_chunk_size);
    $pause_us   = max(0,   (int)$heavy_chunk_pause_us);

    $ph     = implode(',', array_fill(0, count($session_ids), '?'));
    $params = array_merge([$user_id], array_values($session_ids));

    $total = 0;

    while (true) {
        heavy_heartbeat();

        $rows = $db->execute_query(
            "SELECT time FROM logs
              WHERE user_id = ? AND session IN ($ph)
              LIMIT $chunk_size",
            $params
        );
        if (!$rows || $rows->num_rows === 0) break;

        $times = [];
        while ($r = $rows->fetch_row()) $times[] = (int)$r[0];
        $rows->free();
        if (empty($times)) break;

        $time_ph = implode(',', array_fill(0, count($times), '?'));
        $db->execute_query(
            "DELETE FROM logs WHERE user_id = ? AND time IN ($time_ph)",
            array_merge([$user_id], $times)
        );

        $total += count($times);
        heavy_heartbeat();

        if (count($times) < $chunk_size) break;
        if ($pause_us > 0) usleep($pause_us);
    }

    return $total;
}

function heavy_delete_logs_by_user(mysqli $db, int $user_id): int
{
    global $heavy_chunk_size, $heavy_chunk_pause_us;

    $chunk_size = max(100, (int)$heavy_chunk_size);
    $pause_us   = max(0,   (int)$heavy_chunk_pause_us);
    $total      = 0;

    while (true) {
        heavy_heartbeat();

        $rows = $db->execute_query(
            "SELECT time FROM logs WHERE user_id = ? LIMIT $chunk_size",
            [$user_id]
        );
        if (!$rows || $rows->num_rows === 0) break;

        $times = [];
        while ($r = $rows->fetch_row()) $times[] = (int)$r[0];
        $rows->free();
        if (empty($times)) break;

        $time_ph = implode(',', array_fill(0, count($times), '?'));
        $db->execute_query(
            "DELETE FROM logs WHERE user_id = ? AND time IN ($time_ph)",
            array_merge([$user_id], $times)
        );

        $total += count($times);
        heavy_heartbeat();

        if (count($times) < $chunk_size) break;
        if ($pause_us > 0) usleep($pause_us);
    }

    return $total;
}

/**
 * Временная подмена контекста текущего юзера — чтобы cache_flush()
 * сбросил кэш нужного пользователя. Возвращает callable для восстановления.
 */
function heavy_with_user_context(string $username, int $user_id): callable
{
    $prev_username    = $GLOBALS['username'] ?? null;
    $prev_user_id     = $GLOBALS['user_id']  ?? null;
    $prev_session_uid = $_SESSION['uid']     ?? null;

    $GLOBALS['username'] = $username;
    $GLOBALS['user_id']  = $user_id;
    $_SESSION['uid']     = $user_id;

    return function () use ($prev_username, $prev_user_id, $prev_session_uid) {
        if ($prev_username === null) unset($GLOBALS['username']);
        else                         $GLOBALS['username'] = $prev_username;

        if ($prev_user_id === null) unset($GLOBALS['user_id']);
        else                        $GLOBALS['user_id'] = $prev_user_id;

        if ($prev_session_uid === null) unset($_SESSION['uid']);
        else                            $_SESSION['uid'] = $prev_session_uid;
    };
}

/* ────────────────────────────────────────────────────────────
 * Consumer handler — вызывается из worker.php
 * ──────────────────────────────────────────────────────────── */

function heavy_process_task(mysqli $db, array $fields, ?Redis $redis = null): void
{
    $task_id       = $fields['task_id']       ?? null;
    $type          = $fields['type']          ?? null;
    $owner_user_id = (int)($fields['owner_user_id'] ?? 0);
    $payloadStr    = $fields['payload']       ?? '';

    if (!$task_id || !$type || $payloadStr === '') {
        throw new RuntimeException('Malformed heavy task message');
    }

    $payload = json_decode($payloadStr, true);
    if (!is_array($payload)) {
        heavy_task_update($task_id, 'failed', null, 'Invalid payload JSON', $redis);
        throw new RuntimeException('Invalid payload JSON');
    }

    heavy_heartbeat();
    heavy_task_update($task_id, 'running', null, null, $redis);

    try {
        switch ($type) {
            case 'delete_sessions':
                $result = heavy_do_delete_sessions($db, $owner_user_id, $payload);
                break;
            case 'delete_user':
                $result = heavy_do_delete_user($db, $payload);
                break;
            case 'truncate_user':
                $result = heavy_do_truncate_user($db, $payload);
                break;
            default:
                throw new RuntimeException("Unknown heavy task type: $type");
        }
        heavy_heartbeat();
        heavy_task_update($task_id, 'done', $result, null, $redis);
    } catch (Throwable $e) {
        heavy_task_update($task_id, 'failed', null, $e->getMessage(), $redis);
        throw $e;
    }
}

function heavy_do_delete_sessions(mysqli $db, int $user_id, array $payload): array
{
    $session_ids = $payload['session_ids'] ?? [];
    $username    = (string)($payload['username'] ?? '');

    if (!is_array($session_ids) || empty($session_ids)) {
        return ['logs_deleted' => 0, 'sessions_deleted' => 0];
    }

    $session_ids = array_values(array_unique(array_filter(
        array_map('intval', $session_ids), fn($v) => $v > 0
    )));
    if (empty($session_ids)) return ['logs_deleted' => 0, 'sessions_deleted' => 0];

    $restore = heavy_with_user_context($username, $user_id);

    try {
        $logs_deleted = heavy_delete_logs_by_sessions($db, $user_id, $session_ids);

        $ph     = implode(',', array_fill(0, count($session_ids), '?'));
        $params = array_merge([$user_id], $session_ids);
        $db->execute_query(
            "DELETE FROM sessions WHERE user_id = ? AND session IN ($ph)",
            $params
        );
        $sessions_deleted = $db->affected_rows;

        if ($username !== '') cache_flush();

        return [
            'logs_deleted'     => $logs_deleted,
            'sessions_deleted' => $sessions_deleted,
        ];
    } finally {
        $restore();
    }
}

function heavy_do_delete_user(mysqli $db, array $payload): array
{
    global $db_users;

    $target_uid  = (int)($payload['target_user_id']     ?? 0);
    $target_name = (string)($payload['target_username'] ?? '');
    $token       = (string)($payload['target_token']    ?? '');

    if ($target_uid <= 0) throw new RuntimeException('Missing target_user_id');

    if ($target_name === '') {
        $row = $db->execute_query(
            "SELECT user, token FROM $db_users WHERE id = ?",
            [$target_uid]
        )->fetch_assoc();
        if (!$row) return ['deleted' => false, 'reason' => 'user_not_found'];
        $target_name = (string)$row['user'];
        $token       = (string)($row['token'] ?? '');
    }

    $restore = heavy_with_user_context($target_name, $target_uid);

    try {
        $logs_deleted = heavy_delete_logs_by_user($db, $target_uid);

        $db->execute_query("DELETE FROM sessions WHERE user_id = ?", [$target_uid]);
        $sessions_deleted = $db->affected_rows;

        $db->execute_query("DELETE FROM pids WHERE user_id = ?", [$target_uid]);
        $pids_deleted = $db->affected_rows;

        $db->execute_query("DELETE FROM $db_users WHERE id = ?", [$target_uid]);
        $user_deleted = $db->affected_rows > 0;

        if ($token !== '') cache_flush($token);
        cache_flush();

        return [
            'deleted'          => $user_deleted,
            'target_username'  => $target_name,
            'logs_deleted'     => $logs_deleted,
            'sessions_deleted' => $sessions_deleted,
            'pids_deleted'     => $pids_deleted,
        ];
    } finally {
        $restore();
    }
}

function heavy_do_truncate_user(mysqli $db, array $payload): array
{
    global $db_users;

    $target_uid  = (int)($payload['target_user_id']     ?? 0);
    $target_name = (string)($payload['target_username'] ?? '');
    $token       = (string)($payload['target_token']    ?? '');

    if ($target_uid <= 0) throw new RuntimeException('Missing target_user_id');

    if ($target_name === '') {
        $row = $db->execute_query(
            "SELECT user, token FROM $db_users WHERE id = ?",
            [$target_uid]
        )->fetch_assoc();
        if (!$row) return ['truncated' => false, 'reason' => 'user_not_found'];
        $target_name = (string)$row['user'];
        $token       = (string)($row['token'] ?? '');
    }

    $restore = heavy_with_user_context($target_name, $target_uid);

    try {
        // Чанковое удаление logs — основная тяжесть
        $logs_deleted = heavy_delete_logs_by_user($db, $target_uid);

        $db->execute_query("DELETE FROM sessions WHERE user_id = ?", [$target_uid]);
        $sessions_deleted = $db->affected_rows;

        $db->execute_query("DELETE FROM pids WHERE user_id = ?", [$target_uid]);
        $pids_deleted = $db->affected_rows;

        // Пересоздаём дефолтный набор PID'ов.
        // Legacy не восстанавливаем — при следующем импорте Torque
        // они появятся автоматически (INSERT IGNORE в import_torque.php).
        seed_default_pids($db, $target_uid, false);

        if ($token !== '') cache_flush($token);
        cache_flush();

        return [
            'truncated'        => true,
            'target_username'  => $target_name,
            'logs_deleted'     => $logs_deleted,
            'sessions_deleted' => $sessions_deleted,
            'pids_deleted'     => $pids_deleted,
        ];
    } finally {
        $restore();
    }
}

/* ────────────────────────────────────────────────────────────
 * Индекс активных задач пользователя
 *
 * ZSET ratel:user_tasks:<uid> — score = время истечения (unix).
 * Позволяет узнать «какие тяжёлые задачи сейчас идут у юзера»
 * независимо от браузера/устройства.
 * ──────────────────────────────────────────────────────────── */

function heavy_user_tasks_key(int $user_id): string
{
    return 'ratel:user_tasks:' . $user_id;
}

function heavy_user_tasks_add(int $user_id, string $task_id, ?Redis $redis = null): void
{
    global $heavy_task_ttl;

    if ($user_id <= 0) return;
    if ($redis === null) $redis = get_redis_connection();
    if ($redis === null) return;

    try {
        $key    = heavy_user_tasks_key($user_id);
        $expiry = time() + (int)$heavy_task_ttl;
        $redis->zAdd($key, $expiry, $task_id);
        $redis->expire($key, (int)$heavy_task_ttl + 60);
    } catch (Throwable $e) {
        error_log('heavy_user_tasks_add: ' . $e->getMessage());
    }
}

function heavy_user_tasks_remove(int $user_id, string $task_id, ?Redis $redis = null): void
{
    if ($user_id <= 0) return;
    if ($redis === null) $redis = get_redis_connection();
    if ($redis === null) return;

    try {
        $redis->zRem(heavy_user_tasks_key($user_id), $task_id);
    } catch (Throwable $e) {
        error_log('heavy_user_tasks_remove: ' . $e->getMessage());
    }
}

/**
 * Список активных задач пользователя.
 * Чистит истёкшие и уже завершённые записи на лету.
 *
 * @return array<int, array{task_id:string, type:?string, started_at:?int}>
 */
function heavy_user_tasks_list(int $user_id, ?Redis $redis = null): array
{
    if ($user_id <= 0) return [];
    if ($redis === null) $redis = get_redis_connection();
    if ($redis === null) return [];

    try {
        $key = heavy_user_tasks_key($user_id);
        $now = time();

        // Чистим истёкшие по score
        $redis->zRemRangeByScore($key, '-inf', (string)$now);

        $ids = $redis->zRange($key, 0, -1);
        if (!is_array($ids) || empty($ids)) return [];

        $out = [];
        foreach ($ids as $task_id) {
            $data = heavy_task_get($task_id, $redis);
            if (!$data) {
                // Задача уже исчезла (TTL Redis хэша) — убираем из индекса
                $redis->zRem($key, $task_id);
                continue;
            }
            $status = $data['status'] ?? '';
            if ($status === 'done' || $status === 'failed') {
                $redis->zRem($key, $task_id);
                continue;
            }
            $out[] = [
                'task_id'    => $task_id,
                'type'       => $data['type'] ?? null,
                'started_at' => isset($data['started_at']) ? (int)$data['started_at'] : null,
            ];
        }
        return $out;
    } catch (Throwable $e) {
        error_log('heavy_user_tasks_list: ' . $e->getMessage());
        return [];
    }
}

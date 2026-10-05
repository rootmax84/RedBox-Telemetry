<?php
/**
 * Redis Streams consumer — телеметрия + тяжёлые задачи в одном воркере.
 *
 * Обслуживает два стрима одним блокирующим XREADGROUP:
 *   - $redis_stream_key       (telemetry:uploads)   — обычные аплоады
 *   - $redis_heavy_stream_key (ratel:heavy_tasks)   — удаление сессий/юзера
 *
 * Оба стрима используют одну consumer group ($redis_stream_group).
 * Масштабируется как раньше: docker compose up -d --scale worker=N
 *
 *   php worker.php
 */

if (PHP_SAPI !== 'cli') {
    header('Location: .');
    exit;
}

// ────────────────────────────────────────────────────────────
// CLI bootstrap
// ────────────────────────────────────────────────────────────
$_SESSION = [
    'torque_logged_in' => true,
    'admin'            => true,
];
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['REQUEST_METHOD']  = 'CLI';

require_once __DIR__ . '/src/redis.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/translations.php';
require_once __DIR__ . '/src/upload_processor.php';
require_once __DIR__ . '/src/heavy_tasks.php';

// ────────────────────────────────────────────────────────────
// Config
// ────────────────────────────────────────────────────────────
$stream          = $redis_stream_key   ?? 'telemetry:uploads';
$group           = $redis_stream_group ?? 'telemetry-workers';
$heavyStream     = $redis_heavy_stream_key ?? 'ratel:heavy_tasks';
$heavyEnabled    = !empty($heavy_tasks_enabled);
$blockMs         = 5000;    // XREADGROUP blocking timeout (ms)
$batchSize       = 20;      // messages per XREADGROUP call
$reclaimMinIdle  = 60000;   // 60 sec — "stale" threshold for XAUTOCLAIM
$reclaimBatch    = 10;      // messages per XAUTOCLAIM call
$statsEvery      = 60;      // seconds between stats log lines
$redisRetryDelay = 5;       // seconds between Redis reconnect attempts
$heartbeatTtl    = 120;     // TTL heartbeat-ключа, сек

// ────────────────────────────────────────────────────────────
// Early exit if Redis is disabled in creds.php
// ────────────────────────────────────────────────────────────
if (empty($redis_enabled)) {
    fwrite(STDOUT, "[worker] Redis disabled in creds.php. Exiting (code 0).\n");
    exit(0);
}

// ────────────────────────────────────────────────────────────
// Redis connection (own helper, no static caching, retry-friendly)
// ────────────────────────────────────────────────────────────
function worker_connect_redis(): ?Redis
{
    global $redis_host, $redis_port, $redis_timeout,
           $redis_password, $redis_db;

    if (!class_exists('Redis')) {
        error_log('[worker] phpredis extension not loaded');
        return null;
    }

    try {
        $r = new Redis();
        $ok = @$r->connect(
            $redis_host ?? 'redis',
            (int)($redis_port ?? 6379),
            (float)($redis_timeout ?? 2.0)
        );
        if (!$ok) {
            return null;
        }

        if (!empty($redis_password)) {
            $r->auth($redis_password);
        }
        if (isset($redis_db) && $redis_db !== null && $redis_db !== '') {
            $r->select((int)$redis_db);
        }

        // Must be > $blockMs (5000) with margin for network latency
        $r->setOption(Redis::OPT_READ_TIMEOUT, 15);
        $r->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);

        return $r;
    } catch (Throwable $e) {
        error_log('[worker] Redis connect failed: ' . $e->getMessage());
        return null;
    }
}

// ────────────────────────────────────────────────────────────
// Список стримов, которые обслуживает воркер
// ────────────────────────────────────────────────────────────
$pollStreams = [$stream];
if ($heavyEnabled && $heavyStream !== $stream) {
    $pollStreams[] = $heavyStream;
}

// ────────────────────────────────────────────────────────────
// Consumer name: unique per container/process
// ────────────────────────────────────────────────────────────
$consumer = gethostname() . '-' . getmypid();

fwrite(STDOUT, sprintf(
    "[worker] starting consumer=%s streams=[%s] group=%s\n",
    $consumer, implode(', ', $pollStreams), $group
));

// ────────────────────────────────────────────────────────────
// Heartbeat callback
//
// Пока воркер жив, ключ worker:hb:<consumer> обновляется:
//   - в начале каждой итерации главного цикла
//   - на каждом чанке тяжёлого удаления (через heavy_tasks.php)
// Админка считает живых воркеров по этим ключам, что устраняет
// ложный "Workers: 0" во время долгих задач.
// ────────────────────────────────────────────────────────────
$GLOBALS['worker_heartbeat_cb'] = function () use (&$redis, $consumer, $heartbeatTtl) {
    if ($redis instanceof Redis && $consumer !== '') {
        try {
            $redis->set("worker:hb:{$consumer}", (string)time(), ['EX' => (int)$heartbeatTtl]);
        } catch (Throwable $e) {
            /* не роняем воркер из-за heartbeat */
        }
    }
};

// ────────────────────────────────────────────────────────────
// Connect to Redis (retry until success)
// ────────────────────────────────────────────────────────────
$redis = null;
while ($redis === null) {
    $redis = worker_connect_redis();
    if ($redis === null) {
        fwrite(STDERR, sprintf(
            "[worker] Redis unavailable, retry in %d sec...\n",
            $redisRetryDelay
        ));
        sleep($redisRetryDelay);
    }
}

// Первичный heartbeat сразу после подключения
($GLOBALS['worker_heartbeat_cb'])();

// ────────────────────────────────────────────────────────────
// Ensure consumer groups exist for all streams (idempotent)
// ────────────────────────────────────────────────────────────
foreach ($pollStreams as $sKey) {
    try {
        $redis->xGroup('CREATE', $sKey, $group, '0', true);
        fwrite(STDOUT, "[worker] consumer group created for {$sKey}\n");
    } catch (RedisException $e) {
        if (strpos($e->getMessage(), 'BUSYGROUP') === false) {
            throw $e;
        }
        try {
            $info = $redis->xInfo('GROUPS', $sKey);
            if (is_array($info)) {
                foreach ($info as $g) {
                    if (($g['name'] ?? null) === $group) {
                        fwrite(STDOUT, sprintf(
                            "[worker] group exists on %s: consumers=%d pending=%d lag=%d last-delivered-id=%s\n",
                            $sKey,
                            (int)($g['consumers'] ?? 0),
                            (int)($g['pending']   ?? 0),
                            (int)($g['lag']       ?? 0),
                            $g['last-delivered-id'] ?? '?'
                        ));
                        break;
                    }
                }
            }
        } catch (Throwable $e2) {
            error_log("[worker] xInfo({$sKey}) failed: " . $e2->getMessage());
        }
    }
}

// ────────────────────────────────────────────────────────────
// Graceful shutdown on SIGTERM/SIGINT
// ────────────────────────────────────────────────────────────
$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$running) {
        fwrite(STDOUT, "[worker] SIGTERM received, finishing current batch...\n");
        $running = false;
    });
    pcntl_signal(SIGINT, function () use (&$running) {
        fwrite(STDOUT, "[worker] SIGINT received, finishing current batch...\n");
        $running = false;
    });
}

// ────────────────────────────────────────────────────────────
// Main loop
// ────────────────────────────────────────────────────────────
$lastStatsAt    = 0;
$processedCount = 0;

while ($running) {
    // ─── Redis health check / reconnect ───
    if (!is_object($redis)) {
        fwrite(STDERR, "[worker] Redis lost, reconnecting...\n");
        $redis = null;
        while ($redis === null && $running) {
            $redis = worker_connect_redis();
            if ($redis === null) {
                sleep($redisRetryDelay);
            }
        }
        if (!$running) break;
        fwrite(STDOUT, "[worker] Redis reconnected\n");
    }

    // Обновляем heartbeat — мы живы
    ($GLOBALS['worker_heartbeat_cb'])();

    // ─── Reclaim stale pending messages (XAUTOCLAIM) ───
    // XAUTOCLAIM принимает один ключ — идём циклом по всем стримам.
    foreach ($pollStreams as $sKey) {
        $dbLost = false;

        try {
            $claimed = $redis->xAutoClaim(
                $sKey, $group, $consumer,
                $reclaimMinIdle, '0-0', $reclaimBatch
            );

            if (!empty($claimed) && is_array($claimed)
                && !empty($claimed[1]) && is_array($claimed[1])) {

                foreach ($claimed[1] as $id => $fields) {
                    try {
                        worker_dispatch($db, $sKey, $fields, $redis);
                        $redis->xAck($sKey, $group, [$id]);
                        $processedCount++;
                        fwrite(STDOUT, "[worker] reclaimed {$sKey}/{$id}\n");
                    } catch (mysqli_sql_exception $e) {
                        $errno = (int)$e->getCode();
                        if (in_array($errno, [2002, 2003, 2006, 2013, 1927, 1040], true)) {
                            error_log(sprintf(
                                '[worker] DB connection lost (%d) while reclaiming %s/%s',
                                $errno, $sKey, $id
                            ));
                            try {
                                $db = worker_reconnect_db($db);
                                error_log('[worker] DB reconnected');
                            } catch (Throwable $re) {
                                error_log('[worker] DB reconnect failed: ' . $re->getMessage());
                                sleep(5);
                            }
                            $dbLost = true;
                            break;
                        }
                        error_log(sprintf(
                            '[worker] reclaimed %s/%s SQL error (%d): %s',
                            $sKey, $id, $errno, $e->getMessage()
                        ));
                        $redis->xAck($sKey, $group, [$id]);
                    } catch (Throwable $e) {
                        error_log(sprintf(
                            '[worker] reclaimed %s/%s failed: %s',
                            $sKey, $id, $e->getMessage()
                        ));
                        $redis->xAck($sKey, $group, [$id]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("[worker] XAUTOCLAIM({$sKey}) error: " . $e->getMessage());
            if (preg_match('/read error|went away|Connection/i', $e->getMessage())) {
                $redis = null;
                break;
            }
        }

        if ($dbLost || $redis === null) {
            break;
        }
    }

    if ($redis === null) {
        continue;
    }

    // ─── Read new messages из всех стримов одним вызовом ───
    $readKeys = [];
    foreach ($pollStreams as $sKey) {
        $readKeys[$sKey] = '>';
    }

    try {
        $messages = $redis->xReadGroup(
            $group, $consumer,
            $readKeys,
            $batchSize, $blockMs
        );
    } catch (Throwable $e) {
        error_log('[worker] XREADGROUP error: ' . $e->getMessage());
        if (preg_match('/read error|went away|Connection/i', $e->getMessage())) {
            $redis = null;
            continue;
        }
        sleep(2);
        continue;
    }

    if ($messages && is_array($messages)) {
        foreach ($messages as $sKey => $items) {
            if (!is_array($items)) continue;

            $breakOuter = false;

            foreach ($items as $id => $fields) {
                $retry = false;

                try {
                    worker_dispatch($db, $sKey, $fields, $redis);
                    $processedCount++;
                } catch (mysqli_sql_exception $e) {
                    $errno = (int)$e->getCode();
                    if (in_array($errno, [2002, 2003, 2006, 2013, 1927, 1040], true)) {
                        error_log(sprintf(
                            '[worker] DB connection lost (%d: %s); message %s/%s left pending',
                            $errno, $e->getMessage(), $sKey, $id
                        ));
                        try {
                            $db = worker_reconnect_db($db);
                            error_log('[worker] DB reconnected');
                        } catch (Throwable $re) {
                            error_log('[worker] DB reconnect failed: ' . $re->getMessage());
                            sleep(5);
                        }
                        $retry = true;
                    } else {
                        error_log(sprintf(
                            '[worker] message %s/%s SQL error (%d): %s',
                            $sKey, $id, $errno, $e->getMessage()
                        ));
                    }
                } catch (Throwable $e) {
                    error_log(sprintf(
                        "[worker] message %s/%s failed: %s\n%s",
                        $sKey, $id, $e->getMessage(), $e->getTraceAsString()
                    ));
                }

                if (!$retry) {
                    try { $redis->xAck($sKey, $group, [$id]); } catch (Throwable $e) {}
                } else {
                    $breakOuter = true;
                    break;
                }
            }

            if ($breakOuter) break;
        }
    }

    // ─── Periodic stats ───
    $now = time();
    if ($now - $lastStatsAt >= $statsEvery) {
        $lastStatsAt = $now;
        $parts = [];
        foreach ($pollStreams as $sKey) {
            try {
                $len    = (int)$redis->xLen($sKey);
                $lag    = '?';
                $pend   = '?';
                $consum = '?';
                $groups = $redis->xInfo('GROUPS', $sKey);
                if (is_array($groups)) {
                    foreach ($groups as $g) {
                        if (($g['name'] ?? null) === $group) {
                            $lag    = (int)($g['lag']       ?? 0);
                            $pend   = (int)($g['pending']   ?? 0);
                            $consum = (int)($g['consumers'] ?? 0);
                            break;
                        }
                    }
                }
                $parts[] = sprintf('%s[len=%d lag=%s pend=%s consumers=%s]',
                    $sKey, $len, $lag, $pend, $consum);
            } catch (Throwable $e) {
                $parts[] = "{$sKey}[err]";
            }
        }
        fwrite(STDOUT, sprintf(
            "[worker] stats: consumer=%s %s processed_total=%d\n",
            $consumer, implode(' ', $parts), $processedCount
        ));
    }
}

// ────────────────────────────────────────────────────────────
// Shutdown
// ────────────────────────────────────────────────────────────
try { $redis->del("worker:hb:{$consumer}"); } catch (Throwable $e) {}
try { $db->close(); } catch (Throwable $e) {}
fwrite(STDOUT, sprintf(
    "[worker] stopped consumer=%s streams=[%s] processed_total=%d\n",
    $consumer, implode(', ', $pollStreams), $processedCount
));

/* ────────────────────────────────────────────────────────────
 * Stream dispatcher
 * ──────────────────────────────────────────────────────────── */
function worker_dispatch(mysqli $db, string $streamKey, array $fields, ?Redis $redis = null): void
{
    global $redis_stream_key, $redis_heavy_stream_key;

    $uploadsKey = $redis_stream_key       ?? 'telemetry:uploads';
    $heavyKey   = $redis_heavy_stream_key ?? 'ratel:heavy_tasks';

    if ($streamKey === $heavyKey) {
        heavy_process_task($db, $fields, $redis);
        return;
    }

    if ($streamKey === $uploadsKey) {
        processStreamMessage($db, $fields);
        return;
    }

    throw new RuntimeException("Unknown stream: $streamKey");
}

/* ────────────────────────────────────────────────────────────
 * Stream message handler (uploads)
 * ──────────────────────────────────────────────────────────── */
function processStreamMessage(mysqli $db, array $fields): void
{
    global $translations, $tg_socks_proxy;

    $username   = $fields['user']    ?? null;
    $kind       = $fields['kind']    ?? null;
    $ip         = $fields['ip']      ?? '0.0.0.0';
    $payloadStr = $fields['payload'] ?? '';

    // Normalize language
    $lang = $fields['lang'] ?? '';
    if ($lang === '' || $lang === null || !isset($translations[$lang])) {
        $lang = 'en';
    }

    if (!$username || !$kind || $payloadStr === '') {
        throw new RuntimeException('Malformed stream message');
    }

    if (!is_array($translations) || empty($translations)) {
        throw new RuntimeException('Translations not loaded (missing translations.php?)');
    }

    $payload = json_decode($payloadStr, true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid payload JSON');
    }

    $userData = getUserData($username);
    if (!$userData) {
        throw new RuntimeException("User $username not found");
    }

    // user_id: сначала из payload (ul.php шлёт его явно),
    // иначе fallback — из БД через getUserData().
    $payload_uid = isset($fields['user_id']) && $fields['user_id'] !== ''
        ? (int)$fields['user_id']
        : 0;

    $user_id = $payload_uid > 0
        ? $payload_uid
        : (int)($userData['id'] ?? 0);

    // Защита: никогда не пишем в user_id = 0.
    if ($user_id <= 0) {
        throw new RuntimeException("Cannot resolve user_id for $username");
    }

    // Зафиксировать в глобале — на случай, если где-то внутри
    // helpers.php вызовется current_user_id().
    $GLOBALS['user_id'] = $user_id;

    $ctx = [
        'username'       => $username,
        'user_id'        => $user_id,
        'lang'           => $lang,
        'tg_token'       => $userData['tg_token']  ?? null,
        'tg_chatid'      => $userData['tg_chatid'] ?? null,
        'tg_socks_proxy' => $tg_socks_proxy ?? '',
        'translations'   => $translations,
        'ip'             => $ip,
    ];

    processUpload($db, $ctx, $kind, $payload);
}

/* ────────────────────────────────────────────────────────────
 * User cache lookup
 * ──────────────────────────────────────────────────────────── */
function getUserData(string $username): ?array
{
    global $db, $db_users, $memcached, $memcached_connected, $db_memcached_ttl;

    $cacheKey = "worker_user_" . $username;

    if ($memcached_connected) {
        $cached = $memcached->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $row = $db->execute_query(
        "SELECT id, user, s, tg_token, tg_chatid, lang FROM $db_users WHERE user=?",
        [$username]
    )->fetch_assoc();

    if (!$row) {
        return null;
    }

    if ($memcached_connected) {
        try { $memcached->set($cacheKey, $row, $db_memcached_ttl ?? 3600); }
        catch (Throwable $e) { /* ignore */ }
    }

    return $row;
}

/**
 * Переподключение к MariaDB с retry.
 *
 * Не использует get_db_connection(), потому что та делает exit() при
 * ошибке соединения (см. auth_functions.php) — не подходит для
 * long-running worker'а.
 *
 * @throws RuntimeException если не удалось переподключиться за ~60 секунд
 */
function worker_reconnect_db(?mysqli $oldDb): mysqli
{
    global $db_host, $db_user, $db_pass, $db_name, $db_port;

    if ($oldDb !== null) {
        try { $oldDb->close(); } catch (Throwable $e) {}
    }

    $maxAttempts = 12;   // ~60 секунд суммарно при sleep(5)
    for ($i = 1; $i <= $maxAttempts; $i++) {
        try {
            $newDb = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
            // Явная проверка, что соединение действительно работает
            $newDb->query('SELECT 1');
            return $newDb;
        } catch (Throwable $e) {
            error_log(sprintf(
                '[worker] DB reconnect attempt %d/%d failed: %s',
                $i, $maxAttempts, $e->getMessage()
            ));
            if ($i < $maxAttempts) {
                sleep(5);
            }
        }
    }

    throw new RuntimeException(
        'Cannot reconnect to DB after ' . $maxAttempts . ' attempts'
    );
}

#!/usr/bin/env php
<?php
/**
 * Redis Streams consumer for telemetry uploads.
 *
 * Supports single-worker and multi-worker (horizontal scaling) modes.
 * Run as a long-running process under systemd / supervisord / docker.
 *
 *   php worker.php
 *
 * Multi-worker with docker compose:
 *   docker compose up -d --scale worker=3
 */

// ────────────────────────────────────────────────────────────
// CLI bootstrap
// ────────────────────────────────────────────────────────────
$_SESSION = ['torque_logged_in' => true];
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['REQUEST_METHOD']  = 'CLI';

require_once __DIR__ . '/src/redis.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/translations.php';
require_once __DIR__ . '/src/upload_processor.php';

// ────────────────────────────────────────────────────────────
// Config
// ────────────────────────────────────────────────────────────
$stream          = $redis_stream_key   ?? 'telemetry:uploads';
$group           = $redis_stream_group ?? 'telemetry-workers';
$blockMs         = 5000;    // XREADGROUP blocking timeout (ms)
$batchSize       = 20;      // messages per XREADGROUP call
$reclaimMinIdle  = 60000;   // 60 sec — "stale" threshold for XAUTOCLAIM
$reclaimBatch    = 10;      // messages per XAUTOCLAIM call
$statsEvery      = 60;      // seconds between stats log lines
$redisRetryDelay = 5;       // seconds between Redis reconnect attempts

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
        $ok = $r->connect(
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
// Consumer name: unique per container/process
// ────────────────────────────────────────────────────────────
$consumer = gethostname() . '-' . getmypid();

fwrite(STDOUT, sprintf(
    "[worker] starting consumer=%s stream=%s group=%s\n",
    $consumer, $stream, $group
));

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

// ────────────────────────────────────────────────────────────
// Ensure consumer group exists (idempotent) + log state
// ────────────────────────────────────────────────────────────
try {
    $redis->xGroup('CREATE', $stream, $group, '0', true);
    fwrite(STDOUT, "[worker] consumer group created\n");
} catch (RedisException $e) {
    if (strpos($e->getMessage(), 'BUSYGROUP') === false) {
        throw $e;
    }
    try {
        $info = $redis->xInfo('GROUPS', $stream);
        if (is_array($info)) {
            foreach ($info as $g) {
                if (($g['name'] ?? null) === $group) {
                    fwrite(STDOUT, sprintf(
                        "[worker] group exists: consumers=%d pending=%d lag=%d last-delivered-id=%s\n",
                        (int)($g['consumers'] ?? 0),
                        (int)($g['pending'] ?? 0),
                        (int)($g['lag'] ?? 0),
                        $g['last-delivered-id'] ?? '?'
                    ));
                    break;
                }
            }
        }
    } catch (Throwable $e2) {
        error_log('[worker] xInfo failed: ' . $e2->getMessage());
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

    // ─── Reclaim stale pending messages (XAUTOCLAIM) ───
    // Runs every iteration; harmless in single-worker mode.
    // Reclaims messages that a dead (or restarted) consumer
    // left unacknowledged in PEL for > $reclaimMinIdle ms.
    try {
        $claimed = $redis->xAutoClaim(
            $stream, $group, $consumer,
            $reclaimMinIdle, '0-0', $reclaimBatch
        );

        if (!empty($claimed) && is_array($claimed)
            && !empty($claimed[1]) && is_array($claimed[1])) {

            foreach ($claimed[1] as $id => $fields) {
                try {
                    processStreamMessage($db, $fields);
                    $redis->xAck($stream, $group, [$id]);
                    $processedCount++;
                    fwrite(STDOUT, "[worker] reclaimed {$id}\n");
                } catch (mysqli_sql_exception $e) {
                    $errno = (int)$e->getCode();
                    if (in_array($errno, [2002, 2003, 2006, 2013, 1927, 1040], true)) {
                        error_log(sprintf(
                            '[worker] DB connection lost (%d) while reclaiming %s',
                            $errno, $id
                        ));
                        try {
                            $db = worker_reconnect_db($db);
                            error_log('[worker] DB reconnected');
                        } catch (Throwable $re) {
                            error_log('[worker] DB reconnect failed: ' . $re->getMessage());
                            sleep(5);
                        }
                        break;   // не ack — XAUTOCLAIM вернёт позже
                    }
                    error_log(sprintf(
                        '[worker] reclaimed %s SQL error (%d): %s',
                        $id, $errno, $e->getMessage()
                    ));
                    $redis->xAck($stream, $group, [$id]);
                } catch (Throwable $e) {
                    error_log(sprintf(
                        '[worker] reclaimed %s failed: %s',
                        $id, $e->getMessage()
                    ));
                    $redis->xAck($stream, $group, [$id]);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[worker] XAUTOCLAIM error: ' . $e->getMessage());
        if (stripos($e->getMessage(), 'read error') !== false
            || stripos($e->getMessage(), 'went away') !== false
            || stripos($e->getMessage(), 'Connection') !== false) {
            $redis = null;
            continue;
        }
    }

    // ─── Read new messages (XREADGROUP) ───
    try {
        $messages = $redis->xReadGroup(
            $group, $consumer,
            [$stream => '>'],
            $batchSize, $blockMs
        );
    } catch (Throwable $e) {
        error_log('[worker] XREADGROUP error: ' . $e->getMessage());
        if (stripos($e->getMessage(), 'read error') !== false
            || stripos($e->getMessage(), 'went away') !== false
            || stripos($e->getMessage(), 'Connection') !== false) {
            $redis = null;
            continue;
        }
        sleep(2);
        continue;
    }

    if ($messages && isset($messages[$stream])) {
        foreach ($messages[$stream] as $id => $fields) {
            try {
                processStreamMessage($db, $fields);
                $redis->xAck($stream, $group, [$id]);
                $processedCount++;
            } catch (mysqli_sql_exception $e) {
                $errno = (int)$e->getCode();
                if (in_array($errno, [2002, 2003, 2006, 2013, 1927, 1040], true)) {
                    error_log(sprintf(
                        '[worker] DB connection lost (%d: %s); message %s left pending',
                        $errno, $e->getMessage(), $id
                    ));
                    try {
                        $db = worker_reconnect_db($db);
                        error_log('[worker] DB reconnected');
                    } catch (Throwable $re) {
                        error_log('[worker] DB reconnect failed: ' . $re->getMessage());
                        sleep(5);
                    }
                    break;   // не ack — сообщение останется в PEL для XAUTOCLAIM
                }
                // Не-connection ошибка SQL (битые данные, unknown column и т.п.)
                error_log(sprintf(
                    '[worker] message %s SQL error (%d): %s',
                    $id, $errno, $e->getMessage()
                ));
                $redis->xAck($stream, $group, [$id]);
            } catch (Throwable $e) {
                error_log(sprintf(
                    "[worker] message %s failed: %s\n%s",
                    $id, $e->getMessage(), $e->getTraceAsString()
                ));
                // Ack on error to avoid poison-message lock-up.
                // For production, push to a DLQ after N retries.
                $redis->xAck($stream, $group, [$id]);
            }
        }
    }

    // ─── Periodic stats ───
    $now = time();
    if ($now - $lastStatsAt >= $statsEvery) {
        $lastStatsAt = $now;
        try {
            $len       = (int)$redis->xLen($stream);
            $groups    = $redis->xInfo('GROUPS', $stream);
            $lag       = null;
            $pending   = null;
            $consumers = null;
            if (is_array($groups)) {
                foreach ($groups as $g) {
                    if (($g['name'] ?? null) === $group) {
                        $lag       = (int)($g['lag'] ?? 0);
                        $pending   = (int)($g['pending'] ?? 0);
                        $consumers = (int)($g['consumers'] ?? 0);
                        break;
                    }
                }
            }
            fwrite(STDOUT, sprintf(
                "[worker] stats: consumer=%s stream_len=%d lag=%s pending=%s consumers=%s processed_total=%d\n",
                $consumer,
                $len,
                $lag === null ? '?' : $lag,
                $pending === null ? '?' : $pending,
                $consumers === null ? '?' : $consumers,
                $processedCount
            ));
        } catch (Throwable $e) {
            error_log('[worker] stats failed: ' . $e->getMessage());
        }
    }
}

// ────────────────────────────────────────────────────────────
// Shutdown
// ────────────────────────────────────────────────────────────
try { $db->close(); } catch (Throwable $e) {}
fwrite(STDOUT, sprintf(
    "[worker] stopped consumer=%s processed_total=%d\n",
    $consumer, $processedCount
));

/* ────────────────────────────────────────────────────────────
 * Stream message handler
 * ──────────────────────────────────────────────────────────── */
function processStreamMessage(mysqli $db, array $fields): void
{
    global $translations, $db_log_prefix, $db_sessions_prefix,
           $db_pids_prefix, $tg_socks_proxy;

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

    $ctx = [
        'username'          => $username,
        'db_table'          => $username . $db_log_prefix,
        'db_sessions_table' => $username . $db_sessions_prefix,
        'db_pids_table'     => $username . $db_pids_prefix,
        'lang'              => $lang,
        'tg_token'          => $userData['tg_token']  ?? null,
        'tg_chatid'         => $userData['tg_chatid'] ?? null,
        'tg_socks_proxy'    => $tg_socks_proxy ?? '',
        'translations'      => $translations,
        'ip'                => $ip,
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
        "SELECT user, s, tg_token, tg_chatid, lang FROM $db_users WHERE user=?",
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

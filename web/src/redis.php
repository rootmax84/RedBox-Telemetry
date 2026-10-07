<?php

/**
 * Redis connection helper (native phpredis extension).
 * Used by ul.php (producer) and worker.php (consumer).
 */

$redis_enabled        ??= false;
$redis_host           ??= 'redis';
$redis_port           ??= 6379;
$redis_timeout        ??= 2.0;
$redis_password       ??= '';
$redis_db             ??= 0;
$redis_stream_enabled ??= true;
$redis_stream_key     ??= 'telemetry:uploads';
$redis_stream_group   ??= 'telemetry-workers';
$redis_stream_maxlen  ??= 1000000;

/**
 * Позволяет worker.php зарегистрировать свой long-running коннект,
 * чтобы get_redis_connection() не открывал второй.
 * Должен вызываться до первого get_redis_connection().
 */
function redis_set_shared_connection(?Redis $r): void
{
    $GLOBALS['__ratel_shared_redis'] = $r;
}

function get_redis_connection()
{
    global $redis_enabled, $redis_host, $redis_port, $redis_timeout,
           $redis_password, $redis_db;

    // Переиспользуем коннект, если его зарегистрировал worker.php
    if (isset($GLOBALS['__ratel_shared_redis'])
        && $GLOBALS['__ratel_shared_redis'] instanceof Redis) {
        return $GLOBALS['__ratel_shared_redis'];
    }

    if (empty($redis_enabled)) {
        return null;
    }

    static $redis = null;
    static $attempted = false;

    if ($attempted) {
        return $redis;
    }
    $attempted = true;

    if (!class_exists('Redis')) {
        error_log('Redis: phpredis extension not loaded');
        return null;
    }

    try {
        $redis = new Redis();
        $ok = @$redis->pconnect(
            $redis_host ?? 'redis',
            (int)($redis_port ?? 6379),
            (float)($redis_timeout ?? 2.0),
            null,   // persistent_id — null = пул по host:port
            30,     // retry_interval
            0       // read_timeout (значение по умолчанию, не блокирующий)
        );

        if (!$ok) {
            $redis = null;
            return null;
        }

        // AUTH — один раз на постоянное соединение; повторные вызовы no-op.
        if (!empty($redis_password)) {
            $redis->auth($redis_password);
        }

        // SELECT — тоже идемпотентно.
        if (isset($redis_db) && $redis_db !== '' && $redis_db !== null) {
            $redis->select((int)$redis_db);
        }

        // Постоянные соединения могут «заснуть» — keepalive страхует.
        $redis->setOption(Redis::OPT_TCP_KEEPALIVE, 1);

        // Web: конечный таймаут. Блокирующих операций здесь нет,
        // а висящий read_timeout = -1 «залипает» в FPM-пуле.
        $redis->setOption(Redis::OPT_READ_TIMEOUT, 3);

        return $redis;
    } catch (Throwable $e) {
        error_log('Redis: connection failed: ' . $e->getMessage());
        $redis = null;
        return null;
    }
}

/**
 * Push an upload payload onto the Stream.
 * Returns true on success, false otherwise (caller should fall back to inline processing).
 */
function redis_stream_push(array $payload): bool
{
    $redis = get_redis_connection();
    if ($redis === null) {
        return false;
    }

    global $redis_stream_key, $redis_stream_maxlen;

    try {
        $fields = [];
        foreach ($payload as $k => $v) {
            $fields[$k] = is_scalar($v) || $v === null
                ? (string)$v
                : json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        $key     = $redis_stream_key ?? 'telemetry:uploads';
        $maxlen  = (int)($redis_stream_maxlen ?? 0);

        // xAdd($key, '*', $fields, $maxlen, $approximate=true)
        $id = $redis->xAdd($key, '*', $fields, $maxlen, true);
        return $id !== false;
    } catch (Throwable $e) {
        error_log('Redis: XADD failed: ' . $e->getMessage());
        return false;
    }
}

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

function get_redis_connection()
{
    global $redis_enabled, $redis_host, $redis_port, $redis_timeout,
           $redis_password, $redis_db;

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
        $ok = $redis->connect(
            $redis_host ?? 'redis',
            (int)($redis_port ?? 6379),
            (float)($redis_timeout ?? 2.0)
        );

        if (!$ok) {
            $redis = null;
            return null;
        }

        if (!empty($redis_password)) {
            $redis->auth($redis_password);
        }
        if (isset($redis_db) && $redis_db !== '' && $redis_db !== null) {
            $redis->select((int)$redis_db);
        }

        // For blocking XREADGROUP in worker
        $redis->setOption(Redis::OPT_READ_TIMEOUT, -1);

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

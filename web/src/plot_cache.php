<?php
/**
 * Bounded-memory Redis cache for complete plot JSON responses.
 *
 * A unique generation is written first, and the manifest is published last.
 * On a cache hit, chunks are emitted directly to HTTP without a temporary file.
 * A Redis failure after output begins can truncate JSON; clients may retry.
 * No session payload or complete JSON response is materialized as a PHP string.
 */

const RATEL_PLOT_CACHE_CHUNK_BYTES = 262144; // 256 KiB, before compression
const RATEL_PLOT_CACHE_MAX_CHUNKS = 4096;
const RATEL_PLOT_CACHE_VERSION = 1;

function plotCacheWriteAll($stream, string $bytes): bool
{
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length;) {
        $written = fwrite($stream, substr($bytes, $offset));
        if ($written === false || $written === 0) {
            return false;
        }
        $offset += $written;
    }
    return true;
}

/**
 * Stream cached JSON directly to the HTTP response (no tmpfile on cache hit).
 *
 * Returns false only if nothing was emitted, so the caller can safely fall back
 * to MariaDB. Once the first chunk is emitted, any later Redis failure is logged
 * and treated as handled: appending DB output would produce invalid JSON too.
 *
 * The first chunk is validated before headers or response bytes are sent.
 */
function plotCacheServe(RatelCache $cache, string $key): bool
{
    if (!$cache->isConnected()) {
        return false;
    }
    $manifest = $cache->get($key);
    if (!is_array($manifest)
        || ($manifest['version'] ?? null) !== RATEL_PLOT_CACHE_VERSION
        || !is_string($manifest['prefix'] ?? null)
        || !preg_match('/^[a-zA-Z0-9_:]+$/D', $manifest['prefix'])
        || !is_int($manifest['count'] ?? null)
        || $manifest['count'] < 1
        || $manifest['count'] > RATEL_PLOT_CACHE_MAX_CHUNKS
        || !is_int($manifest['length'] ?? null)
        || $manifest['length'] < 1
        || $manifest['length'] > $manifest['count'] * RATEL_PLOT_CACHE_CHUNK_BYTES
        || !is_string($manifest['sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $manifest['sha256'])
    ) {
        return false;
    }

    // A missing first chunk is a normal cache miss; no response has started.
    $first = $cache->get($manifest['prefix'] . ':0');
    if (!is_string($first) || $first === ''
        || strlen($first) > RATEL_PLOT_CACHE_CHUNK_BYTES
        || strlen($first) > $manifest['length']
    ) {
        return false;
    }

    header('Content-Type: application/json; charset=utf-8');
    $hash = hash_init('sha256');
    $total = 0;
    for ($i = 0; $i < $manifest['count']; ++$i) {
        $chunk = $i === 0 ? $first : $cache->get($manifest['prefix'] . ':' . $i);
        if (!is_string($chunk) || $chunk === ''
            || strlen($chunk) > RATEL_PLOT_CACHE_CHUNK_BYTES
            || $total + strlen($chunk) > $manifest['length']
        ) {
            error_log('plotCacheServe: missing or invalid chunk ' . $i . ' for ' . $key);
            return true; // Output may have begun. Do not append a DB response.
        }
        $total += strlen($chunk);
        hash_update($hash, $chunk);
        echo $chunk;
        unset($chunk);
    }
    if ($total !== $manifest['length']
        || !hash_equals($manifest['sha256'], hash_final($hash))
    ) {
        error_log('plotCacheServe: checksum or length mismatch for ' . $key);
    }
    return true;
}

/** Leave headroom for Redis Streams with maxmemory-policy=noeviction. */
function plotCacheHasHeadroom(): bool
{
    if (!function_exists('get_redis_connection')) {
        return true; // Also permits stand-alone testing of cache codec.
    }
    try {
        $redis = get_redis_connection();
        if (!$redis instanceof Redis) {
            return false;
        }
        $memory = $redis->info('memory');
        if (!is_array($memory)) {
            return false;
        }
        $limit = (int)($memory['maxmemory'] ?? 0);
        if ($limit <= 0) {
            return true; // No explicit maxmemory setting.
        }
        $used = (int)($memory['used_memory'] ?? $limit);
        return $used < $limit - max(16 * 1024 * 1024, (int)($limit * 0.15));
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Cache a finished response without ever loading it entirely into PHP RAM.
 * Any failed write leaves the existing manifest untouched. Stranded chunks
 * have an expiry and are best-effort deleted immediately on failures.
 */
function plotCacheStore(RatelCache $cache, string $key, $file, int $ttl): bool
{
    if (!$cache->isConnected() || $ttl <= 0) {
        return false;
    }
    $stats = fstat($file);
    if (!is_array($stats) || !isset($stats['size']) || $stats['size'] <= 0) {
        return false;
    }
    $count = (int)ceil($stats['size'] / RATEL_PLOT_CACHE_CHUNK_BYTES);
    if ($count < 1 || $count > RATEL_PLOT_CACHE_MAX_CHUNKS) {
        return false;
    }
    try {
        $prefix = $key . ':g' . bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        return false;
    }
    rewind($file);
    $hash = hash_init('sha256');
    $writtenKeys = [];
    $length = 0;
    $ok = true;
    for ($i = 0; $i < $count; ++$i) {
        // Redis also handles telemetry streams. Under maxmemory/noeviction,
        // never let a best-effort plot cache consume the last headroom.
        // Recheck periodically to account for compression and concurrent work.
        if (($i % 8) === 0 && !plotCacheHasHeadroom()) {
            $ok = false;
            break;
        }
        $chunk = fread($file, RATEL_PLOT_CACHE_CHUNK_BYTES);
        if (!is_string($chunk) || $chunk === '') {
            $ok = false;
            break;
        }
        $chunkKey = $prefix . ':' . $i;
        if (!$cache->set($chunkKey, $chunk, $ttl + 120)) {
            $ok = false;
            break;
        }
        $writtenKeys[] = $chunkKey;
        $length += strlen($chunk);
        hash_update($hash, $chunk);
    }
    if ($ok && $length === $stats['size']) {
        $ok = $cache->set($key, [
            'version' => RATEL_PLOT_CACHE_VERSION,
            'prefix' => $prefix,
            'count' => $count,
            'length' => $length,
            'sha256' => hash_final($hash),
        ], $ttl);
    } else {
        $ok = false;
    }
    if (!$ok) {
        foreach ($writtenKeys as $chunkKey) {
            $cache->delete($chunkKey);
        }
    }
    rewind($file);
    return $ok;
}

/**
 * Redis lock reduces duplicate generation during simultaneous cache misses.
 * Failure to acquire a lock never prevents DB fallback. The token prevents an
 * expired lease from being removed by a former owner.
 */
function plotCacheLock(?Redis $redis, string $key): ?string
{
    if (!$redis) {
        return null;
    }
    try {
        $token = bin2hex(random_bytes(12));
        return $redis->set($key . ':lock', $token, ['nx', 'ex' => 120]) ? $token : null;
    } catch (Throwable $e) {
        return null;
    }
}

function plotCacheUnlock(?Redis $redis, string $key, ?string $token): void
{
    if (!$redis || $token === null) {
        return;
    }
    try {
        $redis->eval(
            "if redis.call('GET', KEYS[1]) == ARGV[1] then return redis.call('DEL', KEYS[1]) else return 0 end",
            [$key . ':lock', $token],
            1
        );
    } catch (Throwable $e) {
        // Lock expires automatically.
    }
}

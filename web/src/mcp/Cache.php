<?php
declare(strict_types=1);

/**
 * Per-token, per-scope Redis cache for bounded MCP read operations.
 * Never cache credentials or unbounded raw logs.
 * Falls back to the normal query path if Redis is unavailable.
 */
final class McpCache
{
    public function __construct(
        private readonly mysqli $db,
        private readonly array $auth,
        private readonly ?RatelCache $redis
    ) {}

    /** TTLs can be set in creds.php. A zero TTL disables that cache category. */
    public static function ttl(string $name): int
    {
        $config = match ($name) {
            'list_metrics', 'res:pids' => 'mcp_cache_catalog_ttl',
            'list_sessions', 'get_session_info', 'res:sessions' => 'mcp_cache_sessions_ttl',
            'get_live_metrics', 'get_device_status', 'check_thresholds' => 'mcp_cache_live_ttl',
            'get_metric_data', 'get_metric_stats', 'compare_sessions',
            'get_session_events', 'find_anomalies', 'analyze_session',
            'generate_diagnostic_report', 'diagnose_behavior',
            'get_session_route', 'find_route_events',
            'analyze_boost_behavior', 'analyze_pid_response' => 'mcp_cache_analytics_ttl',
            default => null,
        };
        if ($config === null) {
            return 0;
        }
        $defaults = ['mcp_cache_catalog_ttl' => 120, 'mcp_cache_sessions_ttl' => 15,
            'mcp_cache_live_ttl' => 2, 'mcp_cache_analytics_ttl' => 600];
        return max(0, min(3600, (int)($GLOBALS[$config] ?? $defaults[$config])));
    }

    /** Read existing application cache epoch (cache_flush increments this key). */
    private function epoch(): int
    {
        $value = $this->redis?->get('u' . (int)$this->auth['user_id'] . '_varver');
        return is_int($value) ? $value : 0;
    }

    /**
     * Session revision fingerprint for cache invalidation.
     * Session metadata changes (including names and favorites) alter the key.
     * Data inserts/deletes change the session count or time bounds.
     */
    private function sessionSignature(string $name, array $args) : ?array
    {
        $sessionNames = ['get_session_info', 'get_metric_data', 'get_metric_stats',
            'compare_sessions', 'get_session_events', 'find_anomalies',
            'analyze_session', 'generate_diagnostic_report', 'diagnose_behavior',
            'get_session_route', 'find_route_events',
            'analyze_boost_behavior', 'analyze_pid_response'];
        if (!in_array($name, $sessionNames, true)) {
            return [];
        }
        $ids = $name === 'compare_sessions' ? ($args['session_ids'] ?? null) : [$args['session_id'] ?? null];
        // Invalid parameters must reach the original validation, not the cache.
        if (!is_array($ids) || count($ids) > 5) {
            return null;
        }
        $ids = array_values(array_unique(array_map('strval', $ids)));
        $signature = [];
        foreach ($ids as $id) {
            if (!ctype_digit($id) || $id === '0' || strlen($id) > 19 || (float)$id > PHP_INT_MAX) {
                return null;
            }
            $row = $this->db->execute_query(
                'SELECT time, timeend, sessionsize, id, profileName, description, favorite '
                . 'FROM sessions WHERE user_id=? AND session=?',
                [(int)$this->auth['user_id'], (int)$id]
            )->fetch_assoc();
            if (!$row) {
                return null;
            }
            // preserve the original 'not found' error
            $signature[$id] = [
                (string)$row['time'],
                (string)$row['timeend'],
                (string)$row['sessionsize'],
                (string)($row['id'] ?? ''),
                (string)($row['profileName'] ?? ''),
                (string)($row['description'] ?? ''),
                (string)($row['favorite'] ?? ''),
            ];
        }
        ksort($signature, SORT_STRING);
        return $signature;
    }

    /**
     * Remote analyses include the saved MCU configuration in their output.
     * Hash the complete stored payload (including its server timestamp) so a
     * configuration change invalidates the cache even if no session changes.
     * Do not put raw configuration or token material in Redis cache keys.
     */
    private function remoteSignature(string $name): ?string
    {
        if (!in_array($name, ['analyze_boost_behavior', 'analyze_pid_response'], true)) {
            return null;
        }

        $row = $this->db->execute_query(
            'SELECT mcu_data FROM users WHERE id=?',
            [(int)$this->auth['user_id']]
        )->fetch_assoc();
        $raw = $row['mcu_data'] ?? null;

        return is_string($raw) ? hash('sha256', $raw) : 'no-configuration';
    }

    public function remember(string $name, array $args, callable $compute): array
    {
        $ttl = self::ttl($name);
        if ($ttl === 0 || ($GLOBALS['mcp_cache_enabled'] ?? true) === false
            || $this->redis === null || !$this->redis->isConnected()
            || (int)($this->auth['user_id'] ?? 0) < 1 || (int)($this->auth['token_id'] ?? 0) < 1) {
            return $compute();
        }
        try {
            $revision = $this->sessionSignature($name, $args);
            if ($revision === null) {
                return $compute();
            }
            $remoteRevision = $this->remoteSignature($name);
            $scopes = $this->auth['scopes'] ?? [];
            sort($scopes, SORT_STRING);
            $identity = [5, (int)$this->auth['user_id'], (string)($this->auth['cache_identity'] ?? ('manual:' . $this->auth['token_id'])),
                $scopes, $name, $args, $revision, $remoteRevision,
                // Live sampling needs its own short TTL, not the constantly changing dataset epoch.
                $ttl <= 2 ? null : $this->epoch()];
            $key = 'mcp_cached_v5_' . hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
            $cached = $this->redis->get($key);
            if (is_array($cached) && is_array($cached['payload'] ?? null)) {
                return $cached['payload'];
            }
            $lockKey = 'mcp_lock_' . substr($key, strlen('mcp_cached_v5_'));
            $owner = $this->redis->acquireLock($lockKey,
                max(1, min(60, (int)($GLOBALS['mcp_cache_lock_ttl'] ?? 15))));
        } catch (Throwable $error) {
            // Cache failures must never break MCP reads.
            error_log('MCP cache preparation failed: ' . $error->getMessage());
            return $compute();
        }
        if ($owner === null) {
            // Another worker may already be calculating it. Wait at most 150 ms.
            for ($i = 0; $i < 6; $i++) {
                usleep(25000);
                $cached = $this->redis->get($key);
                if (is_array($cached) && is_array($cached['payload'] ?? null)) {
                    return $cached['payload'];
                }
            }
            // No long-running PHP wait: calculate normally if the other worker is slow.
            return $compute();
        }
        try {
            // Business errors propagate unchanged; no caching of failed operations.
            $result = $compute();
            try {
                $max = max(1024, min(1048576, (int)($GLOBALS['mcp_cache_max_bytes'] ?? 131072)));
                if (strlen(serialize($result)) <= $max) {
                    // Do not publish old results after a concurrent metadata, MCU
                    // configuration, or application-cache epoch change.
                    if ($ttl <= 2 || (
                        $this->epoch() === $identity[8]
                        && $this->sessionSignature($name, $args) === $revision
                        && $this->remoteSignature($name) === $remoteRevision
                    )) {
                        $this->redis->set($key, ['payload' => $result], $ttl);
                    }
                }
            } catch (Throwable $error) {
                // A failed cache post-check must never discard a successfully
                // computed answer; skip caching and return the live result.
                error_log('MCP cache publication skipped: ' . $error->getMessage());
            }
            return $result;
        } finally {
            $this->redis->releaseLock($lockKey, $owner);
        }
    }
}

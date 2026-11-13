<?php
declare(strict_types=1);

require_once __DIR__ . '/../version.php';

/** Format MCP responses without coupling the protocol layer to the database. */
final class McpProtocol
{
    /** Cache lifetimes, in milliseconds, advertised by the modern MCP protocol. */
    private const CACHEABLE_TTL = [
        'tools/list' => 300000,
        'prompts/list' => 300000,
        'resources/list' => 300000,
        'resources/templates/list' => 300000,
        'resources/read' => 0,
        'completion/complete' => 0,
    ];

    /** Use the application's canonical version for every MCP handshake. */
    public static function serverInfo(): array
    {
        return [
            'name' => 'redbox-telemetry',
            'version' => RATEL_VERSION . '-mcp',
        ];
    }

    /**
     * Shared, static server guidance for MCP clients. Put the core workflow first:
     * some clients inspect only the initial portion of this field.
     * No user-provided telemetry, prompt text or configuration is interpolated here.
     */
    public static function instructions(): string
    {
        return 'RedBox Telemetry MCP is read-only. For recorded sessions: list_sessions, list_metrics, '
            . 'then get_session_quality before analysis; use get_session_segments for recording gaps. '
            . 'Use raw Unix-millisecond timestamps, not compressed graph time. '
            . 'Check incomplete/scan_truncated and coverage_ratio; never treat partial results as complete. '
            . 'get_metric_stats offers sample mean and time-weighted mean; distinguish them. '
            . 'analyze_metric_relationship is observational, not proof of causality. '
            . 'For Rollback use get_rollback_events and related sensors; decode independent warning bits. '
            . 'For live data start with get_device_status; uploads do not prove connection. '
            . 'For RedManage use get_remote_schema, get_remote_architecture, get_remote_behavior '
            . 'before get_remote_settings. Boost eligibility requires ECT AND EOT AND ATF; '
            . 'disabled threshold checks are bypassed. RedManage gauge boost pressures require '
            . '+101 kPa to compare with MCU absolute MAP. '
            . 'ds_avail counts registered EEPROM sensor addresses, not sensors online. '
            . 'Stored motor-hours history is not live run-time; advanced hours use RPM. '
            . 'PG delays: use get_remote_behavior(topic=logic); off-delay is not cancellable, '
            . 'repeat bypasses maximum on-time. Do not prescribe unverified tuning or '
            . 'interpret a configured target as achieved pressure. '
            . 'Compare engineering units, signal timestamps, and measurement freshness. '
            . 'GPS and RedManage require separate scopes: location:read and remote:read. '
            . 'full_scan requires explicit opt-in and remains bounded';
    }

    /** Capabilities and guidance must match across legacy initialize and modern discovery. */
    public static function capabilities(): array
    {
        return [
            'tools' => ['listChanged' => false],
            'resources' => ['listChanged' => false],
            'prompts' => ['listChanged' => false],
            'completions' => new stdClass(),
        ];
    }

    public static function initializeResult(string $protocolVersion): array
    {
        return [
            'protocolVersion' => $protocolVersion,
            'capabilities' => self::capabilities(),
            'serverInfo' => self::serverInfo(),
            'instructions' => self::instructions(),
        ];
    }

    public static function discoveryResult(array $supportedVersions): array
    {
        return [
            'resultType' => 'complete',
            'supportedVersions' => $supportedVersions,
            'capabilities' => self::capabilities(),
            'instructions' => self::instructions(),
            '_meta' => ['io.modelcontextprotocol/serverInfo' => self::serverInfo()],
            'ttlMs' => 300000,
            'cacheScope' => 'private',
        ];
    }

    /** Align HTTP freshness with MCP cache metadata; never share private data. */
    public static function httpCacheControl(array|stdClass $result): string
    {
        $value = $result instanceof stdClass ? (array)$result : $result;
        $ttl = $value['ttlMs'] ?? 0;
        if (($value['cacheScope'] ?? null) !== 'private' || !is_int($ttl) || $ttl < 1000) {
            return 'no-store';
        }
        return 'private, max-age=' . intdiv($ttl, 1000);
    }

    public static function decorateResult(string $method, array|stdClass $result, bool $modern): array|stdClass
    {
        if (!$modern || $method === 'server/discover') {
            // Legacy ping must remain an object ({}) instead of an array ([]).
            return $result;
        }

        if ($result instanceof stdClass) {
            $result = (array)$result;
        }

        if (in_array($method, [
            'tools/list',
            'tools/call',
            'resources/list',
            'resources/templates/list',
            'resources/read',
            'prompts/list',
            'prompts/get',
            'completion/complete',
        ], true)) {
            $result = ['resultType' => 'complete'] + $result;
        }

        if (array_key_exists($method, self::CACHEABLE_TTL)) {
            $result['ttlMs'] = self::CACHEABLE_TTL[$method];
            $result['cacheScope'] = 'private';
        }

        $result['_meta']['io.modelcontextprotocol/serverInfo'] = self::serverInfo();

        return $result;
    }
}

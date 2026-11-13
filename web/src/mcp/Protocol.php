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
        return 'RedBox Telemetry MCP is read-only. For recorded sessions: find a session with list_sessions, '
            . 'resolve PID identifiers and raw units with list_metrics, and check get_session_quality '
            . 'before interpreting measurements. Use original database timestamps (Unix milliseconds), '
            . 'not the compressed display X axis. Recording gaps are not measured time; inspect '
            . 'get_session_segments when event duration or data coverage matters. '
            . 'Check incomplete/scan_truncated and coverage_ratio; do not present partial results '
            . 'as whole-session conclusions. get_metric_stats gives a sample mean and a time-weighted '
            . 'mean: choose explicitly according to the question. Do not infer causality from '
            . 'analyze_metric_relationship or treat statistical anomalies as confirmed faults. '
            . 'For Rollback, use get_rollback_events and contextual PIDs; a positive Rollback value '
            . 'does not by itself establish a root cause. For live data, check get_device_status '
            . 'first: recent uploads do not prove an active connection. For RedManage, start with '
            . 'get_remote_architecture or get_remote_schema and get_remote_behavior, then examine '
            . 'get_remote_settings if authorized; never mistake a stored target for observed pressure '
            . 'or prescribe unverified boost/PID settings. Telemetry uses raw sensor units; verify '
            . 'that compared PIDs have compatible units and timestamps. Location and RedManage '
            . 'require separate scopes (location:read and remote:read); absence of permission '
            . 'is not absence of data. If necessary, full_scan is explicitly opt-in and still bounded.';
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

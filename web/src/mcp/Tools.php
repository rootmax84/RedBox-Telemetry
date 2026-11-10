<?php
declare(strict_types=1);
require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/RemoteSchema.php';
require_once __DIR__ . '/RemoteData.php';
final class McpTools
{
    private const READ = ['telemetry:read'];
    private const LIVE = ['telemetry:live'];
    private const GPS = ['telemetry:read', 'location:read'];
    private const REMOTE = ['remote:read'];
    private const REMOTE_ANALYSIS = ['remote:read', 'telemetry:read'];
    /** Explicit schemas keep the MCP surface stable across firmware/PID changes. */
    public static function definitions(): array
    {
        $s = static fn(array $props = [], array $required = []): array => [
            'type' => 'object', 'properties' => $props === [] ? new stdClass() : $props, 'required' => $required, 'additionalProperties' => false,
        ];
        $int = static fn(string $desc): array => ['type' => 'integer', 'description' => $desc];
        $str = static fn(string $desc): array => ['type' => 'string', 'description' => $desc];
        $sid = ['session_id' => $str('Session ID as a decimal string or integer')];
        $pid = ['pid' => $str('Exact PID identifier from list_metrics, e.g. kff1001')];
        $range = ['start_ms' => $int('Optional inclusive Unix milliseconds'), 'end_ms' => $int('Optional inclusive Unix milliseconds')];
        $defs = [
            ['list_sessions', 'Find user-owned recorded sessions', self::READ, $s([
                        'limit' => $int('1–100; default 25'), 'offset' => $int('0–10000'),
                        'from_ms' => $int('Start time lower bound'), 'to_ms' => $int('Start time upper bound'),
                        'favorites_only' => ['type' => 'boolean']], [])],
            ['get_session_info', 'Get session metadata including timing and source', self::READ, $s($sid, ['session_id'])],
            ['list_metrics', 'List available PIDs with units and live flags. GPS PIDs require location:read.', self::READ, $s()],
            ['get_metric_data', 'Stream data and return bounded min/max downsampling (max 1000 points); raw sensor units.', self::READ,
                $s($sid + $pid + $range + ['max_points' => $int('10–1000')], ['session_id', 'pid'])],
            ['get_metric_stats', 'Minimum, maximum, mean, sample standard deviation, first/last time for a PID.', self::READ,
                $s($sid + $pid + $range, ['session_id', 'pid'])],
            ['compare_sessions', 'Compare the same PID across 2–5 sessions.', self::READ,
                $s($pid + ['session_ids' => ['type' => 'array', 'items' => ['type' => ['integer', 'string']], 'minItems' => 2, 'maxItems' => 5]], ['pid', 'session_ids'])],
            ['get_session_events', 'Find contiguous threshold excursions for a PID, with optional GPS if authorized.', self::READ,
                $s($sid + $pid + $range + ['operator' => ['type' => 'string', 'enum' => ['gt', 'gte', 'lt', 'lte']],
                        'threshold' => ['type' => 'number'], 'max_events' => $int('1–100'), 'include_location' => ['type' => 'boolean']],
                    ['session_id', 'pid', 'threshold'])],
            ['find_anomalies', 'Find statistical outliers (z-score) relative to the session, not a diagnosis.', self::READ,
                $s($sid + $pid + $range + ['sigma' => ['type' => 'number', 'minimum' => 1.5, 'maximum' => 10],
                        'max_events' => $int('1–100')], ['session_id', 'pid'])],
            ['analyze_session', 'Compute a multi-PID statistical snapshot for one session (up to six PIDs).', self::READ,
                $s($sid + ['pids' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 6]], ['session_id', 'pids'])],
            ['generate_diagnostic_report', 'Generate a structured Markdown facts report (no speculative diagnosis).', self::READ,
                $s($sid + ['pids' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 6]], ['session_id', 'pids'])],
            ['diagnose_behavior', 'Get evidence/statistics to assist a human diagnostic interpretation, not a confirmed fault.', self::READ,
                $s($sid + ['pids' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 6]], ['session_id', 'pids'])],
            ['get_live_metrics', 'Latest uploaded PID readings and age; optionally select up to 50 PIDs.', self::LIVE,
                $s(['pids' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 50]])],
            ['get_device_status', 'Last upload timestamp and session ID; recent upload does not guarantee an active connection.', self::LIVE, $s()],
            ['check_thresholds', 'Check latest uploaded values against up to 20 threshold rules.', self::LIVE,
                $s(['checks' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 20, 'items' => [
                                'type' => 'object', 'properties' => [
                                    'pid' => $str('PID identifier'),
                                    'operator' => ['type' => 'string', 'enum' => ['gt', 'gte', 'lt', 'lte']],
                                    'threshold' => ['type' => 'number']],
                                'required' => ['pid', 'operator', 'threshold'], 'additionalProperties' => false]]], ['checks'])],
            ['get_session_route', 'Retrieve bounded GPS route points for a session.', self::GPS,
                $s($sid + $range + ['max_points' => $int('10–500')], ['session_id'])],
            ['find_route_events', 'Get threshold excursions with GPS points at event onset.', self::GPS,
                $s($sid + $pid + $range + ['operator' => ['type' => 'string', 'enum' => ['gt', 'gte', 'lt', 'lte']],
                        'threshold' => ['type' => 'number'], 'max_events' => $int('1–100')], ['session_id', 'pid', 'threshold'])],
            ['get_remote_schema', 'Read-only RedManage field catalogue: engineering meaning, units, byte decoding, groups and dependencies. No writes.', self::REMOTE,
                $s(['group' => ['type' => 'string', 'enum' => array_keys(McpRemoteSchema::GROUPS)]])],
            ['get_remote_settings', 'Read decoded current RedManage settings, server timestamp and configuration warnings. Never changes settings.', self::REMOTE,
                $s(['group' => ['type' => 'string', 'enum' => array_keys(McpRemoteSchema::GROUPS)]])],
            ['get_remote_explanation', 'Explain a named RedManage field, relationships and relevant cautions. Keys from get_remote_schema.', self::REMOTE,
                $s(['key' => $str('Exact dotted field key from get_remote_schema')], ['key'])],
            ['analyze_boost_behavior', 'Read-only paired RPM/boost analysis near selected engine RPM. Requires correct PIDs and matching engineering units; never prescribe duty or target changes.', self::REMOTE_ANALYSIS,
                $s($sid + ['rpm_pid' => $str('RPM PID from list_metrics'),
                    'pressure_pid' => $str('Boost pressure PID from list_metrics'),
                    'duty_pid' => $str('Optional duty PID from list_metrics'),
                    'center_rpm' => $int('RPM center; default 5000'),
                    'rpm_window' => $int('Half-width of RPM interval; default 200')] + $range,
                    ['session_id', 'rpm_pid', 'pressure_pid'])],
            ['analyze_pid_response', 'Read-only observed pressure response: rise/fall, overshoot and oscillation clues; identify transients, not tuning prescriptions.', self::REMOTE_ANALYSIS,
                $s($sid + ['pressure_pid' => $str('Pressure PID from list_metrics'),
                    'rpm_pid' => $str('Optional RPM PID from list_metrics'),
                    'target_pid' => $str('Optional time-aligned controller target PID; must have same pressure unit'),
                    'duty_pid' => $str('Optional actuator duty PID from list_metrics')] + $range,
                    ['session_id', 'pressure_pid'])],
        ];
        $out = [];
        foreach ($defs as [$name, $description, $scopes, $schema]) {
            $out[$name] = [
                'scopes' => $scopes,
                'schema' => ['name' => $name, 'description' => $description, 'inputSchema' => $schema,
                    '_meta' => ['securitySchemes' => [['type' => 'oauth2', 'scopes' => $scopes]]],
                    'annotations' => ['readOnlyHint' => true,
                        'destructiveHint' => false, 'openWorldHint' => false]],
            ];
        }
        return $out;
    }

    public static function available(array $auth): array
    {
        $tools = [];
        foreach (self::definitions() as $def) {
            if (McpSecurity::can($auth, $def['scopes'])) {
                $tools[] = $def['schema'];
            }
        }
        return $tools;
    }

    public static function call(mysqli $db, array $auth, string $name, array $args, ?RatelCache $redis = null): array
    {
        $defs = self::definitions();
        if (!isset($defs[$name])) {
            throw new InvalidArgumentException('Unknown MCP tool');
        }
        McpSecurity::requireScopes($auth, $defs[$name]['scopes']);
        $service = new McpData($db, $auth);
        $compute = static fn(): array => match ($name) {
            'list_sessions' => $service->listSessions($args),
            'get_session_info' => $service->session($args),
            'list_metrics' => $service->listMetrics($args),
            'get_metric_data' => $service->metricData($args),
            'get_metric_stats' => $service->metricStats($args),
            'compare_sessions' => $service->compareSessions($args),
            'get_session_events' => $service->events($args),
            'find_anomalies' => $service->anomalies($args),
            'analyze_session' => self::snapshot($service, $args),
            'generate_diagnostic_report' => self::report($service, $args),
            'diagnose_behavior' => self::diagnose($service, $args),
            'get_live_metrics' => $service->live($args),
            'get_device_status' => $service->deviceStatus(),
            'check_thresholds' => self::thresholds($service, $args),
            'get_session_route' => $service->route($args),
            'find_route_events' => $service->events($args, true),
            'get_remote_schema' => McpRemoteSchema::catalog(self::remoteGroup($args)),
            'get_remote_settings' => (new McpRemoteData($db, $auth))->settings(self::remoteGroup($args)),
            'get_remote_explanation' => McpRemoteSchema::explain(self::remoteKey($args)),
            'analyze_boost_behavior' => (new McpRemoteData($db, $auth))->analyzeBoost($service, $args),
            'analyze_pid_response' => (new McpRemoteData($db, $auth))->analyzePid($service, $args),
        };
        return (new McpCache($db, $auth, $redis))->remember($name, $args, $compute);
    }

    private static function remoteGroup(array $args): ?string
    {
        $group = $args['group'] ?? null;
        if ($group !== null && !is_string($group)) {
            throw new InvalidArgumentException('group must be a string');
        }
        McpRemoteSchema::checkGroup($group);
        return $group;
    }

    private static function remoteKey(array $args): string
    {
        $key = $args['key'] ?? null;
        if (!is_string($key)) {
            throw new InvalidArgumentException('key must be a string');
        }
        return $key;
    }

    private static function snapshot(McpData $d, array $args): array
    {
        $pids = $args['pids'] ?? null;
        if (!is_array($pids) || count($pids) < 1 || count($pids) > 6) {
            throw new InvalidArgumentException('Provide 1–6 PIDs');
        }
        $session = $d->session($args);
        $stats = [];
        foreach (array_unique($pids) as $pid) {
            if (!is_string($pid)) {
                throw new InvalidArgumentException('Invalid PID');
            }
            $stats[] = $d->metricStats(['pid' => $pid, 'session_id' => $session['session_id']]);
        }
        return ['session' => $session, 'metrics' => $stats,
            'caution' => 'Computed over available samples, not necessarily synchronized across PIDs. No diagnostics inferred.'];
    }

    private static function report(McpData $d, array $args): array
    {
        $result = self::snapshot($d, $args);
        $s = $result['session'];
        $lines = ['# RedBox Telemetry — Session ' . $s['session_id'], '',
            'Profile: ' . $s['profile'] . '; Source: ' . $s['source'] . '; Records (reported): ' . $s['records'],
            'Start (Unix ms): ' . $s['start_ms'] . '; End (Unix ms): ' . $s['end_ms'], '',
            '| PID | Samples | Min | Max | Mean | Std. dev. |', '|---|---:|---:|---:|---:|---:|'];
        foreach ($result['metrics'] as $m) {
            $lines[] = '| ' . str_replace('|', '/', (string)$m['pid']['description']) . ' (`' . $m['pid']['id'] . '`) | ' . $m['valid_samples'] .
            ' | ' . ($m['min']['value'] ?? 'n/a') . ' | ' . ($m['max']['value'] ?? 'n/a') . ' | ' . ($m['mean'] ?? 'n/a') .
            ' | ' . ($m['stddev'] ?? 'n/a') . ' |';
        }
        $lines[] = '';
        $lines[] = 'This is a factual telemetry summary in raw sensor units; not a diagnosis. Missing/-1 samples are excluded.';
        return ['session' => $s, 'report_markdown' => implode("\n", $lines), 'statistics' => $result['metrics']];
    }

    private static function diagnose(McpData $d, array $args): array
    {
        $result = self::snapshot($d, $args);
        return $result + ['interpretation_guidance' => [
                'Compare sensor ranges with the vehicle/ECU-specific specifications.',
                'Correlations and outliers alone do not identify a defective component.',
                'Confirm a suspected issue using independent testing and the relevant operating conditions.',
            ]];
    }

    private static function thresholds(McpData $d, array $args): array
    {
        $checks = $args['checks'] ?? null;
        if (!is_array($checks) || count($checks) < 1 || count($checks) > 20) {
            throw new InvalidArgumentException('Provide 1–20 checks');
        }
        $pids = [];
        foreach ($checks as $check) {
            if (!is_array($check) || !isset($check['pid'], $check['operator'], $check['threshold']) || !is_string($check['pid']))
            {
                throw new InvalidArgumentException('Invalid check');
            }
            $pids[] = $check['pid'];
        }
        $live = $d->live(['pids' => array_values(array_unique($pids))]);
        if (!($live['available'] ?? false)) {
            return $live;
        }
        $results = [];
        foreach ($checks as $check) {
            $op = $check['operator'];
            $n = $check['threshold'];
            if (!in_array($op, ['gt', 'gte', 'lt', 'lte'], true) || !is_numeric($n) || !is_finite((float)$n)) {
                throw new InvalidArgumentException('Invalid threshold rule');
            }
            $v = McpData::numeric($live['values'], $check['pid']);
            $triggered = $v === null ? null : match ($op) {
                'gt' => $v > (float)$n, 'gte' => $v >= (float)$n,
                'lt' => $v < (float)$n, 'lte' => $v <= (float)$n
            };
            $results[] = ['pid' => $check['pid'], 'value' => $v, 'operator' => $op, 'threshold' => (float)$n, 'triggered' => $triggered];
        }
        return ['time_ms' => $live['time_ms'], 'stale' => $live['stale'], 'checks' => $results];
    }

    public static function resources(array $auth): array
    {
        $out = [];
        if (McpSecurity::can($auth, self::READ)) {
            $out[] = ['uri' => 'redbox://pids/catalog', 'name' => 'PID catalog', 'mimeType' => 'application/json'];
            $out[] = ['uri' => 'redbox://sessions/latest/summary', 'name' => 'Last session summary', 'mimeType' => 'application/json'];
        }
        return $out;
    }

    public static function readResource(mysqli $db, array $auth, string $uri, ?RatelCache $redis = null): array
    {
        $d = new McpData($db, $auth);
        $compute = static fn(): array => match ($uri) {
            'redbox://pids/catalog' => (function ()use ($d, $auth) {
                    McpSecurity::requireScopes($auth, self::READ); return $d->listMetrics([]);
                })(),
            'redbox://sessions/latest/summary' => (function ()use ($d, $auth) {
                    McpSecurity::requireScopes($auth, self::READ); return $d->listSessions(['limit' => 1]);
                })(),
            default => throw new InvalidArgumentException('Resource not found'),
        };
        $cacheName = match ($uri) {
            'redbox://pids/catalog' => 'res:pids',
            'redbox://sessions/latest/summary' => 'res:sessions',
            default => throw new InvalidArgumentException('Resource not found'),
        };
        $value = (new McpCache($db, $auth, $redis))->remember($cacheName, [], $compute);
        return ['contents' => [['uri' => $uri, 'mimeType' => 'application/json', 'text' => json_encode($value, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]]];
    }

    public static function prompts(array $auth): array
    {
        if (!McpSecurity::can($auth, self::READ)) {
            return [];
        }
        return [
            ['name' => 'session_health', 'description' => 'Review session statistics without inventing unsupported diagnoses',
                'arguments' => [['name' => 'session_id', 'required' => true], ['name' => 'pids', 'required' => true]]],
            ['name' => 'compare_drives', 'description' => 'Compare operating conditions of two sessions',
                'arguments' => [['name' => 'session_a', 'required' => true], ['name' => 'session_b', 'required' => true], ['name' => 'pid', 'required' => true]]],
        ];
    }

    public static function getPrompt(array $auth, string $name, array $args): array
    {
        McpSecurity::requireScopes($auth, self::READ);
        $text = match ($name) {
            'session_health' => 'Use get_session_info, get_metric_stats and find_anomalies for session ' . (string)($args['session_id'] ?? '')
            . '. Focus on PIDs: ' . (string)($args['pids'] ?? '') . '. Cite measured values and units; distinguish observations from hypotheses.',
            'compare_drives' => 'Compare session ' . (string)($args['session_a'] ?? '') . ' and ' . (string)($args['session_b'] ?? '')
            . ' using compare_sessions for PID ' . (string)($args['pid'] ?? '') . '. Explain differences cautiously.',
            default => throw new InvalidArgumentException('Prompt not found'),
        };
        return ['messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]]]];
    }
}

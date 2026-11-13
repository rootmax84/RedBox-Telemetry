<?php
declare(strict_types=1);
require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/Analytics.php';
require_once __DIR__ . '/RemoteSchema.php';
require_once __DIR__ . '/RemoteData.php';
require_once __DIR__ . '/RemoteBehavior.php';
require_once __DIR__ . '/RemoteArchitecture.php';
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
        $full = ['full_scan' => ['type' => 'boolean', 'description' => sprintf('Explicitly permit up to %s log rows (default %s); results remain bounded', number_format(McpData::MAX_FULL_SCAN_ROWS), number_format(McpData::MAX_SCANNED_ROWS))]];
        $defs = [
            ['list_sessions', 'Find the current user’s recorded sessions and their IDs. Call first when the session is not specified; filter by time or favorites, then pass an exact session_id to session tools. Results are paginated.', self::READ, $s([
                        'limit' => $int('1–100; default 25'), 'offset' => $int('0–10000'),
                        'from_ms' => $int('Start time lower bound'), 'to_ms' => $int('Start time upper bound'),
                        'favorites_only' => ['type' => 'boolean']], [])],
            ['get_session_info', 'Get metadata for one session: start/end Unix milliseconds, profile, source and reported record count. Use to confirm the selected session; metadata alone does not establish telemetry quality.', self::READ, $s($sid, ['session_id'])],
            ['list_metrics', 'Discover exact PID IDs, names, raw units, live flags and availability before requesting data or comparing sensors. Do not guess PID codes or compare incompatible units. GPS PIDs require location:read.', self::READ, $s()],
            ['get_metric_data', 'Get a bounded, min/max-downsampled time series (at most 1000 points) for visual trends or an interval. Uses original Unix-ms timestamps and raw units; not suitable for computing exact whole-session statistics.', self::READ,
                $s($sid + $pid + $range + $full + ['max_points' => $int('10–1000')], ['session_id', 'pid'])],
            ['get_metric_stats', 'Compute exact scanned-sample min/max, sample mean, time-weighted mean, standard deviation and temporal coverage for one PID. First check session quality; distinguish sample mean from time mean and report scan_truncated/incomplete.', self::READ,
                $s($sid + $pid + $range + $full, ['session_id', 'pid'])],
            ['compare_sessions', 'Compare the same PID over two to five recorded sessions in raw sensor units. Verify comparable vehicle/operating conditions and data completeness; differences alone do not diagnose a fault.', self::READ,
                $s($pid + ['session_ids' => ['type' => 'array', 'items' => ['type' => ['integer', 'string']], 'minItems' => 2, 'maxItems' => 5]] + $full, ['pid', 'session_ids'])],
            ['get_session_events', 'Find threshold excursions for one PID on original time: events are split across acquisition gaps. Specify threshold and operator; durations mean observed continuous segments, not unobserved pauses. Optional GPS needs location:read.', self::READ,
                $s($sid + $pid + $range + $full + ['operator' => ['type' => 'string', 'enum' => ['gt', 'gte', 'lt', 'lte']],
                        'threshold' => ['type' => 'number'], 'max_events' => $int('1–100'), 'include_location' => ['type' => 'boolean']],
                    ['session_id', 'pid', 'threshold'])],
            ['find_anomalies', 'Detect within-session statistical outliers for a PID using z-scores. Check missing samples, coverage and operating conditions first; statistical rarity does not establish malfunction or a safety threshold.', self::READ,
                $s($sid + $pid + $range + $full + ['sigma' => ['type' => 'number', 'minimum' => 1.5, 'maximum' => 10],
                        'max_events' => $int('1–100')], ['session_id', 'pid'])],
            ['analyze_session', 'Compute one-pass statistics for up to six PIDs in one session. Prefer for multi-sensor inspection after list_metrics/get_session_quality; assess coverage and incomplete flags separately for each PID.', self::READ,
                $s($sid + ['pids' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 6]] + $full, ['session_id', 'pids'])],
            ['generate_diagnostic_report', 'Generate a factual Markdown telemetry report for up to six PIDs: raw units, observed ranges, mean and coverage. Explicitly warns of partial scans; do not treat it as a confirmed diagnostic verdict.', self::READ,
                $s($sid + ['pids' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 6]] + $full, ['session_id', 'pids'])],
            ['diagnose_behavior', 'Return evidence-based statistics and interpretation cautions for up to six PIDs. Use after checking quality and selecting relevant sensors; corroborate hypotheses with operating context and external specifications.', self::READ,
                $s($sid + ['pids' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 6]] + $full, ['session_id', 'pids'])],
            ['get_live_metrics', 'Read latest uploaded values and their ages for selected PIDs (up to 50). Call get_device_status first; a value can be stale and does not prove a connected or currently running device.', self::LIVE,
                $s(['pids' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 50]])],
            ['get_device_status', 'Check age and timestamp of the last telemetry upload and associated session before interpreting live readings. A recent upload is not evidence of a continuously connected device.', self::LIVE, $s()],
            ['check_thresholds', 'Evaluate up to 20 numeric threshold rules against the latest uploaded PID values, not the entire session. Check reading freshness with get_device_status; findings are observations, not persistent alerts.', self::LIVE,
                $s(['checks' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 20, 'items' => [
                                'type' => 'object', 'properties' => [
                                    'pid' => $str('PID identifier'),
                                    'operator' => ['type' => 'string', 'enum' => ['gt', 'gte', 'lt', 'lte']],
                                    'threshold' => ['type' => 'number']],
                                'required' => ['pid', 'operator', 'threshold'], 'additionalProperties' => false]]], ['checks'])],
            ['get_session_quality', 'Start recorded-session analysis here: assess sampling cadence, missing measurements, real-time coverage, acquisition gaps and scan completeness. Optional PID narrows quality to that sensor; charts may compress gaps but data must not.', self::READ,
                $s($sid + $range + $full + ['pid' => $str('Optional PID-specific quality'), 'max_segments' => $int('1–200')], ['session_id'])],
            ['get_session_segments', 'Describe observed acquisition segments and actual timestamp gaps for a session or PID. Call before reasoning about event durations, averages across pauses or discontinuous recordings; never interpolate missing time.', self::READ,
                $s($sid + $range + $full + ['pid' => $str('Optional PID'), 'max_segments' => $int('1–200')], ['session_id'])],
            ['analyze_metric_relationship', 'Pair two different PIDs using bounded timestamp freshness, then calculate observational Pearson correlation. Verify units and quality and respect asynchronous sampling; correlation is not causation or proof of control response.', self::READ,
                $s($sid + $range + $full + ['pid_a' => $str('First PID'), 'pid_b' => $str('Second PID'),
                    'max_time_delta_ms' => $int('0 for adaptive, or 1–60000 milliseconds')], ['session_id', 'pid_a', 'pid_b'])],
            ['get_rollback_events', 'Identify nonzero Rollback bitmask intervals split by recording gaps, with up to four contextual PIDs. Decode bit positions via get_remote_behavior(protection): numeric peak is NOT fault severity and multiple flags may coexist; not a confirmed root-cause diagnosis.', self::READ,
                $s($sid + $range + $full + ['max_events' => $int('1–50'), 'window_ms' => $int('0–60000'),
                    'context_pids' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 4]], ['session_id'])],
            ['compare_operating_conditions', 'Compare one PID across two to five sessions restricted to a comparable RPM band. Supply exact RPM/PID IDs and verify engineering units and session coverage; similarities or differences are observational.', self::READ,
                $s(['session_ids' => ['type' => 'array', 'items' => ['type' => ['integer','string']], 'minItems' => 2, 'maxItems' => 5],
                    'rpm_pid' => $str('RPM PID'), 'pid' => $str('PID to compare'),
                    'center_rpm' => $int('0–15000'), 'rpm_window' => $int('10–5000')] + $full,
                    ['session_ids', 'rpm_pid', 'pid'])],
            ['get_session_route', 'Retrieve bounded timestamped GPS points of a recorded session, for mapping only when location is relevant and permitted (location:read). This does not supply a complete high-resolution track.', self::GPS,
                $s($sid + $range + $full + ['max_points' => $int('10–500')], ['session_id'])],
            ['find_route_events', 'Locate observed threshold-excursion onsets along a session GPS route. Requires telemetry:read and location:read; gaps and GPS sample availability may prevent precise event positioning.', self::GPS,
                $s($sid + $pid + $range + $full + ['operator' => ['type' => 'string', 'enum' => ['gt', 'gte', 'lt', 'lte']],
                        'threshold' => ['type' => 'number'], 'max_events' => $int('1–100')], ['session_id', 'pid', 'threshold'])],
            ['get_remote_schema', 'Read the RedManage field catalogue: engineering units, encoding, limits, groups and dependencies. Use before interpreting saved values; a configuration target is not a measured telemetry value. Read-only.', self::REMOTE,
                $s(['group' => ['type' => 'string', 'enum' => array_keys(McpRemoteSchema::GROUPS)]])],
            ['get_remote_settings', 'Read decoded saved MCU configuration, not live sensor/actuator state. Boost includes absolute-MAP vs gauge-pressure conversion and ECT AND EOT AND ATF prerequisites; inputs distinguish stored DS ROMs from live sensors and flag duplicate temperature assignments; other.motor_hours_history excludes the live counter; logic includes PG timing. Requires remote:read; read-only.', self::REMOTE,
                $s(['group' => ['type' => 'string', 'enum' => array_keys(McpRemoteSchema::GROUPS)]])],
            ['get_remote_explanation', 'Explain one exact dotted RedManage field from get_remote_schema, including unit, related settings and cautions. Useful when a setting looks ambiguous; read-only and does not validate vehicle safety.', self::REMOTE,
                $s(['key' => $str('Exact dotted field key from get_remote_schema')], ['key'])],
            ['get_remote_behavior', 'Read MCU-derived RedManage behavior for boost, PID, protection, fan and PG logic. For PG0/PG1 delays use topic=logic: off delay cannot be cancelled while pending, and repeat ignores the on-time limit. Read-only; not a tuning prescription.', self::REMOTE,
                $s(['topic' => ['type' => 'string', 'enum' => array_keys(McpRemoteBehavior::TOPICS)]])],
            ['get_remote_architecture', 'Map RedManage MCU signal sources, subsystems and telemetry provenance. Start here for system-wide architecture questions, then query a component or its schema/behavior; read-only and not firmware source code.', self::REMOTE,
                $s(['component' => ['type' => 'string', 'enum' => array_keys(McpRemoteArchitecture::COMPONENTS)]])],
            ['analyze_boost_behavior', 'Compare time-aligned RPM and boost within a selected RPM band, optionally including duty. Check raw pressure units, gauge/absolute basis, data gaps and stored targets separately; no tuning prescriptions.', self::REMOTE_ANALYSIS,
                $s($sid + ['rpm_pid' => $str('RPM PID from list_metrics'),
                    'pressure_pid' => $str('Boost pressure PID from list_metrics'),
                    'duty_pid' => $str('Optional duty PID from list_metrics'),
                    'center_rpm' => $int('RPM center; default 5000'),
                    'rpm_window' => $int('Half-width of RPM interval; default 200')] + $range + $full,
                    ['session_id', 'rpm_pid', 'pressure_pid'])],
            ['analyze_pid_response', 'Analyze observed pressure rise/fall slopes and optional target/duty association within contiguous recordings. Do not compute across gaps or claim validated overshoot, settling time or suitable PID gains.', self::REMOTE_ANALYSIS,
                $s($sid + ['pressure_pid' => $str('Pressure PID from list_metrics'),
                    'rpm_pid' => $str('Optional RPM PID from list_metrics'),
                    'target_pid' => $str('Optional time-aligned controller target PID; must have same pressure unit'),
                    'duty_pid' => $str('Optional actuator duty PID from list_metrics')] + $range + $full,
                    ['session_id', 'pressure_pid'])],
        ];
        $out = [];
        // Structured response contracts for the most commonly composed analytical tools.
        $outSchemas = [
            'get_metric_stats' => ['pid' => 'object', 'valid_samples' => 'integer', 'mean' => ['number','null'],
                'time_weighted_mean' => ['number','null'], 'coverage_ratio' => ['number','null'], 'scan_truncated' => 'boolean'],
            'get_session_quality' => ['session_id' => 'string', 'sample_count' => 'integer', 'gap_count' => 'integer',
                'coverage_ratio' => ['number','null'], 'incomplete' => 'boolean'],
            'get_session_segments' => ['session_id' => 'string', 'segments' => 'array', 'gaps' => 'array',
                'gap_count' => 'integer', 'incomplete' => 'boolean'],
            'analyze_metric_relationship' => ['session_id' => 'string', 'paired_samples' => 'integer',
                'pearson_r' => ['number','null'], 'incomplete' => 'boolean'],
            'get_rollback_events' => ['session_id' => 'string', 'events' => 'array', 'event_count' => 'integer'],
            'analyze_session' => ['session' => 'object', 'metrics' => 'array', 'incomplete' => 'boolean'],
            'compare_operating_conditions' => ['pid' => 'object', 'rpm_pid' => 'object',
                'comparison' => 'array', 'incomplete' => 'boolean'],
            'generate_diagnostic_report' => ['session' => 'object', 'report_markdown' => 'string',
                'statistics' => 'array', 'incomplete' => 'boolean'],
        ];
        foreach ($defs as [$name, $description, $scopes, $schema]) {
            $output = null;
            if (isset($outSchemas[$name])) {
                $props = [];
                foreach ($outSchemas[$name] as $key => $type) $props[$key] = ['type' => $type];
                $output = ['type' => 'object', 'properties' => $props,
                    'required' => array_keys($props), 'additionalProperties' => true];
            }
            $out[$name] = [
                'scopes' => $scopes,
                'schema' => ['name' => $name, 'description' => $description, 'inputSchema' => $schema,
                    ...($output === null ? [] : ['outputSchema' => $output]),
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
            'get_session_quality' => McpAnalytics::quality($service, $args),
            'get_session_segments' => McpAnalytics::segments($service, $args),
            'analyze_metric_relationship' => McpAnalytics::relationship($service, $args),
            'get_rollback_events' => McpAnalytics::rollback($service, $args),
            'compare_operating_conditions' => McpAnalytics::operatingConditions($service, $args),
            'get_session_route' => $service->route($args),
            'find_route_events' => $service->events($args, true),
            'get_remote_schema' => McpRemoteSchema::catalog(self::remoteGroup($args)),
            'get_remote_settings' => (new McpRemoteData($db, $auth))->settings(self::remoteGroup($args)),
            'get_remote_explanation' => McpRemoteSchema::explain(self::remoteKey($args)),
            'get_remote_behavior' => self::remoteBehavior($db, $auth, $args),
            'get_remote_architecture' => self::remoteArchitecture($args),
            'analyze_boost_behavior' => (new McpRemoteData($db, $auth))->analyzeBoost($service, $args),
            'analyze_pid_response' => (new McpRemoteData($db, $auth))->analyzePid($service, $args),
        };
        return (new McpCache($db, $auth, $redis))->remember($name, $args, $compute);
    }

    private static function remoteBehavior(mysqli $db, array $auth, array $args): array
    {
        $topic = $args['topic'] ?? null;
        if ($topic !== null && !is_string($topic)) {
            throw new InvalidArgumentException('topic must be a string');
        }

        $behavior = McpRemoteBehavior::describe($topic);
        if ($topic !== null) {
            $behavior['saved_configuration'] = (new McpRemoteData($db, $auth))->settings($topic);
        }

        return $behavior;
    }

    private static function remoteArchitecture(array $args): array
    {
        $component = $args['component'] ?? null;
        if ($component !== null && !is_string($component)) {
            throw new InvalidArgumentException('component must be a string');
        }
        return McpRemoteArchitecture::describe($component);
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
        return $d->multiStats($args);
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
        $lines[] = 'Time-weighted means exclude gaps; coverage measures recorded intervals only.';
        foreach ($result['metrics'] as $m) {
            $lines[] = '- ' . $m['pid']['id'] . ': time_weighted_mean=' . ($m['time_weighted_mean'] ?? 'n/a')
                . ', coverage_ratio=' . ($m['coverage_ratio'] ?? 'n/a');
        }
        if ($result['incomplete']) $lines[] = '**WARNING: PARTIAL REPORT — scan limit reached; not whole-session statistics.**';
        $lines[] = 'This is a factual telemetry summary in raw sensor units; not a diagnosis. Missing/-1 samples are excluded.';
        return ['session' => $s, 'report_markdown' => implode("\n", $lines), 'statistics' => $result['metrics'],
            'incomplete' => $result['incomplete']];
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
            $out[] = ['uri' => 'redbox://diagnostics/definitions', 'name' => 'Diagnostics definitions and limitations', 'mimeType' => 'application/json'];
        }
        return $out;
    }

    /** URI templates are intentionally read-only and scoped like the corresponding tools. */
    public static function resourceTemplates(array $auth): array
    {
        $result = [];
        if (McpSecurity::can($auth, self::READ)) {
            $result[] = ['uriTemplate' => 'redbox://sessions/{session_id}/summary',
                'name' => 'Session metadata', 'mimeType' => 'application/json'];
            $result[] = ['uriTemplate' => 'redbox://sessions/{session_id}/quality',
                'name' => 'Session acquisition quality', 'mimeType' => 'application/json'];
        }
        if (McpSecurity::can($auth, self::REMOTE)) {
            $result[] = ['uriTemplate' => 'redbox://remote/schema/{group}',
                'name' => 'RedManage field schema by group', 'mimeType' => 'application/json'];
            $result[] = ['uriTemplate' => 'redbox://remote/architecture/{component}',
                'name' => 'RedManage component architecture', 'mimeType' => 'application/json'];
        }
        return $result;
    }

    public static function readResource(mysqli $db, array $auth, string $uri, ?RatelCache $redis = null): array
    {
        $d = new McpData($db, $auth);
        $resource = null; $cacheName = null; $required = self::READ;
        if ($uri === 'redbox://pids/catalog') {
            $resource = static fn() => $d->listMetrics([]);
            $cacheName = 'res:pids';
        } elseif ($uri === 'redbox://sessions/latest/summary') {
            $resource = static fn() => $d->listSessions(['limit' => 1]);
            $cacheName = 'res:sessions';
        } elseif ($uri === 'redbox://diagnostics/definitions') {
            $resource = static fn() => [
                'missing_sentinel' => '-1 for non-GPS PIDs; do not treat it as a physical reading',
                'units' => 'Raw sensor units from PID catalog; no web display unit conversion',
                'time_basis' => 'Unix milliseconds from original session logs, not compressed chart time',
                'gap_model' => 'Adaptive median-based sampling intervals; no interpolation through offline periods',
                'means' => ['sample_mean' => 'Average per sample', 'time_weighted_mean' => 'Trapezoidal average over observed contiguous intervals'],
                'coverage' => 'Within first/last valid readings, excludes detected gaps; null for a single sample',
                'scan_limits' => ['default_rows' => McpData::MAX_SCANNED_ROWS, 'requested_full_scan_rows' => McpData::MAX_FULL_SCAN_ROWS, 'page_rows' => McpData::SCAN_CHUNK],
                'interpretation' => 'Statistical associations and threshold events do not establish component faults. Compare source/units and hardware-specific specifications.',
                'security' => 'Read-only; GPS requires location:read and MCU settings require remote:read',
            ];
            $cacheName = 'res:diagnostics';
        } elseif (preg_match('#^redbox://sessions/([1-9][0-9]{0,18})/(summary|quality)$#D', $uri, $m)) {
            $sid = $m[1];
            $d->sessionId(['session_id' => $sid]);
            $resource = $m[2] === 'summary'
                ? static fn() => $d->session(['session_id' => $sid])
                : static fn() => McpAnalytics::quality($d, ['session_id' => $sid]);
            $cacheName = $m[2] === 'summary' ? 'res:session-summary' : 'res:session-quality';
        } elseif (preg_match('#^redbox://remote/schema/([a-z]+)$#D', $uri, $m)) {
            $group = $m[1]; McpRemoteSchema::checkGroup($group);
            if ($group === null) throw new InvalidArgumentException('Invalid group');
            $required = self::REMOTE;
            $resource = static fn() => McpRemoteSchema::catalog($group);
            $cacheName = 'res:remote-schema';
        } elseif (preg_match('#^redbox://remote/architecture/([a-z_0-9]+)$#D', $uri, $m)) {
            $component = $m[1];
            if (!array_key_exists($component, McpRemoteArchitecture::COMPONENTS)) throw new InvalidArgumentException('Invalid component');
            $required = self::REMOTE;
            $resource = static fn() => McpRemoteArchitecture::describe($component);
            $cacheName = 'res:remote-architecture';
        } else {
            throw new InvalidArgumentException('Resource not found');
        }
        McpSecurity::requireScopes($auth, $required);
        $value = (new McpCache($db, $auth, $redis))->remember($cacheName, ['uri' => $uri], $resource);
        return ['contents' => [['uri' => $uri, 'mimeType' => 'application/json',
            'text' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]]];
    }

    private static function promptDefinitions(): array
    {
        return [
            'session_health' => ['Review session statistics and data quality without speculative diagnoses', self::READ, ['session_id', 'pids']],
            'compare_drives' => ['Compare an observed PID across two sessions', self::READ, ['session_a', 'session_b', 'pid']],
            'audit_session' => ['Assess recording coverage, gaps, incomplete scans and suitable diagnostics', self::READ, ['session_id']],
            'diagnose_rollback' => ['Investigate observed rollback states, associated data and limitations', self::READ, ['session_id']],
            'analyze_boost_control' => ['Interpret boost/RPM response with read-only RedManage settings', self::REMOTE_ANALYSIS, ['session_id','rpm_pid','pressure_pid']],
            'investigate_temperature' => ['Review a temperature PID with acquisition quality and fan context', self::READ, ['session_id','temperature_pid']],
            'compare_engine_modes' => ['Compare a metric at matching RPM bands from two sessions', self::READ, ['session_a','session_b','rpm_pid','pid']],
        ];
    }

    public static function prompts(array $auth): array
    {
        $out = [];
        foreach (self::promptDefinitions() as $name => [$description, $scopes, $required]) {
            if (!McpSecurity::can($auth, $scopes)) continue;
            $out[] = ['name' => $name, 'description' => $description,
                'arguments' => array_map(static fn($arg) => ['name' => $arg, 'required' => true], $required)];
        }
        return $out;
    }

    public static function getPrompt(array $auth, string $name, array $args): array
    {
        $defs = self::promptDefinitions();
        if (!isset($defs[$name])) throw new InvalidArgumentException('Prompt not found');
        McpSecurity::requireScopes($auth, $defs[$name][1]);
        foreach ($defs[$name][2] as $key) {
            $value = $args[$key] ?? null;
            $pattern = in_array($key, ['session_a', 'session_b', 'session_id'], true)
                ? '/^[1-9][0-9]{0,18}$/D' :
                ($key === 'pids' ? '/^[a-zA-Z0-9_]{1,16}(?:,[a-zA-Z0-9_]{1,16}){0,5}$/D' : '/^[a-zA-Z0-9_]{1,16}$/D');
            if (!is_string($value) || !preg_match($pattern, $value)) {
                throw new InvalidArgumentException('Missing or invalid prompt argument: ' . $key);
            }
        }
        $v = static fn($key) => $args[$key];
        $text = match ($name) {
            'session_health' => 'For session ' . $v('session_id') . ', first run get_session_quality, then analyze_session and find_anomalies for PID(s) ' . $v('pids') . '. Cite raw units, time coverage, gaps, truncated scans and distinguish facts from hypotheses.',
            'compare_drives' => 'Compare sessions ' . $v('session_a') . ' and ' . $v('session_b') . ' using compare_sessions for PID ' . $v('pid') . '. Check completeness and compare time-weighted and sample means.',
            'audit_session' => 'Audit session ' . $v('session_id') . ': run get_session_quality and get_session_segments first; report missing data, variable frequency, gaps, timestamps, scan_truncated, and whether conclusions are reliable.',
            'diagnose_rollback' => 'Investigate session ' . $v('session_id') . ' with get_rollback_events, get_session_quality and get_metric_stats for relevant protection PIDs. Identify what was measured before/after events; do not claim hardware cause without independent evidence.',
            'analyze_boost_control' => 'Review session ' . $v('session_id') . ', RPM PID ' . $v('rpm_pid') . ', pressure PID ' . $v('pressure_pid') . ': obtain get_remote_settings, get_remote_behavior, analyze_boost_behavior and analyze_pid_response. Check raw engineering units and data coverage; do not prescribe controller duty/gains.',
            'investigate_temperature' => 'Review temperature PID ' . $v('temperature_pid') . ' in session ' . $v('session_id') . ': get_session_quality, get_metric_stats, get_session_events, compare fan PID if available; report only verified temperature/activation observations.',
            'compare_engine_modes' => 'Compare PID ' . $v('pid') . ' at matched RPM PID ' . $v('rpm_pid') . ' in sessions ' . $v('session_a') . ' and ' . $v('session_b') . ' with compare_operating_conditions. Explain confounding factors such as load, throttle and gaps.',
        };
        return ['messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]]]];
    }

    /** Optional MCP argument completion, strictly scoped and bounded to 100 suggestions. */
    public static function complete(mysqli $db, array $auth, array $args): array
    {
        $ref = $args['ref'] ?? null; $argument = $args['argument'] ?? null;
        if (!is_array($ref) || !is_array($argument) || !is_string($argument['name'] ?? null)
            || !is_string($argument['value'] ?? null)) throw new InvalidArgumentException('Invalid completion request');
        $kind = $ref['type'] ?? null;
        if ($kind === 'ref/prompt') {
            $name = $ref['name'] ?? null;
            $defs = self::promptDefinitions();
            if (!is_string($name) || !isset($defs[$name])) throw new InvalidArgumentException('Unknown prompt');
            McpSecurity::requireScopes($auth, $defs[$name][1]);
        } elseif ($kind === 'ref/resource') {
            $uri = $ref['uri'] ?? null;
            if (!is_string($uri) || !in_array($uri, array_column(self::resourceTemplates($auth), 'uriTemplate'), true)) {
                throw new InvalidArgumentException('Unknown resource template');
            }
        } else throw new InvalidArgumentException('Invalid completion reference');
        $key = $argument['name']; $prefix = substr($argument['value'], 0, 80);
        $choices = [];
        if (in_array($key, ['pid','pids','rpm_pid','pressure_pid','temperature_pid'], true)) {
            McpSecurity::requireScopes($auth, self::READ);
            $list = (new McpData($db, $auth))->listMetrics([])['metrics'];
            $choices = array_column($list, 'id');
        } elseif (in_array($key, ['session_id','session_a','session_b'], true)) {
            McpSecurity::requireScopes($auth, self::READ);
            $choices = array_column((new McpData($db, $auth))->listSessions(['limit' => 100])['sessions'], 'session_id');
        } elseif ($key === 'group') {
            McpSecurity::requireScopes($auth, self::REMOTE);
            $choices = array_keys(McpRemoteSchema::GROUPS);
        } elseif ($key === 'component') {
            McpSecurity::requireScopes($auth, self::REMOTE);
            $choices = array_keys(McpRemoteArchitecture::COMPONENTS);
        }
        $filtered = array_values(array_filter($choices, static fn($x) => str_starts_with((string)$x, $prefix)));
        return ['completion' => ['values' => array_slice($filtered, 0, 100),
            'total' => count($filtered), 'hasMore' => count($filtered) > 100]];
    }
}

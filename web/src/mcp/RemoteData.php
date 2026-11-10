<?php
declare(strict_types=1);
require_once __DIR__ . '/RemoteBehavior.php';

require_once __DIR__ . '/RemoteSchema.php';

/** Read-only MCU configuration and observational telemetry analysis. No write APIs. */
final class McpRemoteData
{
    public function __construct(
        private readonly mysqli $db,
        private readonly array $auth
    ) {
    }

    /** @return array{bytes: array<int,int>, updated_ms:int}|null */
    private function configuration(): ?array
    {
        $row = $this->db->execute_query(
            'SELECT mcu_data FROM users WHERE id=?',
            [(int)$this->auth['user_id']]
        )->fetch_assoc();
        $raw = $row['mcu_data'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        // Same on-disk contract as validateMcuData(), without including web helpers or
        // accidentally triggering browser/session bootstrapping.
        if (strlen($raw) > 2048 || strlen($raw) < 824) {
            throw new UnexpectedValueException('Stored RedManage configuration has an invalid length');
        }
        $parts = explode(',', $raw);
        if (count($parts) !== 406 || $parts[404] !== '~') {
            throw new UnexpectedValueException('Stored RedManage configuration has an invalid structure');
        }
        $bytes = [];
        for ($i = 0; $i < 404; $i++) {
            $part = $parts[$i];
            if ($part === '' || strlen($part) > 3 || !ctype_digit($part) || (int)$part > 255) {
                throw new UnexpectedValueException('Stored RedManage configuration contains an invalid byte');
            }
            $bytes[] = (int)$part;
        }
        $timestamp = $parts[405];
        if (!preg_match('/^\d{13}$/D', $timestamp)
            || (int)$timestamp < 1577836800000
            || (int)$timestamp > (int)(microtime(true) * 1000) + 86400000) {
            throw new UnexpectedValueException('Stored RedManage configuration timestamp is invalid');
        }
        return ['bytes' => $bytes, 'updated_ms' => (int)$timestamp];
    }

    public function settings(?string $group = null): array
    {
        McpRemoteSchema::checkGroup($group);
        $configuration = $this->configuration();
        if ($configuration === null) {
            return ['available' => false, 'reason' => 'No saved RedManage configuration'];
        }
        $values = McpRemoteSchema::decode($configuration['bytes'], $group);
        $allValues = $group === null ? $values : McpRemoteSchema::decode($configuration['bytes']);
        $derived = [];
        if ($group === null || $group === 'fan') {
            $derived['fan_pwm'] = McpRemoteSchema::fanPwmSummary($allValues);
        }
        if ($group === null || $group === 'boost') {
            $derived['boost_mode'] = McpRemoteSchema::boostModeSummary($allValues);
        }
        if ($group === null || $group === 'logic') {
            $derived['logic_conditions'] = McpRemoteSchema::logicConditions($allValues);
        }

        return [
            'available' => true,
            'group' => $group,
            'settings_updated_ms' => $configuration['updated_ms'],
            'values' => $values,
            'units' => McpRemoteSchema::units($group, $allValues),
            'value_labels' => McpRemoteSchema::valueLabels($values, $group),
            'derived' => $derived,
            'warnings' => McpRemoteSchema::warnings($allValues),
            'read_only' => true,
            'architecture_reference' => ['tool' => 'get_remote_architecture', 'component' => null],
            'behavior_reference' => $group !== null && array_key_exists($group, McpRemoteBehavior::TOPICS)
                ? ['tool' => 'get_remote_behavior', 'topic' => $group]
                : ['tool' => 'get_remote_behavior', 'topics' => array_keys(McpRemoteBehavior::TOPICS)],
            'caution' => 'Values are stored device configuration, not live hardware confirmation. Firmware and sensor calibration must be verified before interpreting them.',
        ];
    }

    private function pid(McpData $data, array $args, string $key, bool $optional = false): ?array
    {
        $value = $args[$key] ?? null;
        if ($optional && $value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException("{$key} must be a PID string");
        }
        $pid = $data->metric(['pid' => $value]);
        if (in_array($pid['id'], McpData::GPS_PIDS, true)) {
            throw new InvalidArgumentException('GPS metrics cannot be used as a control signal');
        }
        return $pid;
    }

    private static function summary(array $values): array
    {
        if ($values === []) {
            return ['samples' => 0];
        }
        sort($values, SORT_NUMERIC);
        $count = count($values);
        return [
            'samples' => $count,
            'min' => round($values[0], 3),
            'max' => round($values[$count - 1], 3),
            'mean' => round(array_sum($values) / $count, 3),
            'p10' => round($values[(int)floor(($count - 1) * 0.1)], 3),
            'p90' => round($values[(int)floor(($count - 1) * 0.9)], 3),
        ];
    }

    public function analyzeBoost(McpData $data, array $args): array
    {
        $rpmPid = $this->pid($data, $args, 'rpm_pid');
        $pressurePid = $this->pid($data, $args, 'pressure_pid');
        $dutyPid = $this->pid($data, $args, 'duty_pid', true);
        $center = $data->intParam($args, 'center_rpm', 5000, 500, 12000);
        $window = $data->intParam($args, 'rpm_window', 200, 25, 2000);
        [$session, $start, $end] = $data->bounds($args);
        $pressure = [];
        $duty = [];
        $allRpm = [];
        $matches = 0;
        $scan = $data->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use ($rpmPid, $pressurePid, $dutyPid, $center, $window, &$pressure, &$duty, &$allRpm, &$matches): void {
                $rpm = McpData::numeric($row, $rpmPid['id']);
                $boost = McpData::numeric($row, $pressurePid['id']);
                if ($rpm === null || $boost === null) {
                    return;
                }
                $allRpm[] = $rpm;
                if (abs($rpm - $center) > $window) {
                    return;
                }
                $matches++;
                $pressure[] = $boost;
                if ($dutyPid !== null) {
                    $sampleDuty = McpData::numeric($row, $dutyPid['id']);
                    if ($sampleDuty !== null) {
                        $duty[] = $sampleDuty;
                    }
                }
            }
        );
        $stored = $this->settings('boost');
        return [
            'session_id' => $session['session_id'],
            'rpm_center' => $center,
            'rpm_window' => $window,
            'pressure_pid' => $pressurePid,
            'rpm_pid' => $rpmPid,
            'duty_pid' => $dutyPid,
            'paired_samples' => $matches,
            'observed_pressure' => self::summary($pressure),
            'observed_duty' => self::summary($duty),
            'all_observed_rpm' => self::summary($allRpm),
            'stored_boost_settings' => $stored,
            'limitations' => [
                'PID values are raw sensor units; verify whether pressure is absolute, gauge, bar or kPa before comparing with target.',
                'Samples are matched only when recorded in the same log row; missing values are excluded.',
                'A target at a particular RPM cannot be inferred solely from base duty or RPM compensation.',
                'No actuator recommendations or numerical tuning values are generated.',
            ],
        ] + $scan;
    }

    public function analyzePid(McpData $data, array $args): array
    {
        $pressurePid = $this->pid($data, $args, 'pressure_pid');
        $rpmPid = $this->pid($data, $args, 'rpm_pid', true);
        $targetPid = $this->pid($data, $args, 'target_pid', true);
        $dutyPid = $this->pid($data, $args, 'duty_pid', true);
        [$session, $start, $end] = $data->bounds($args);
        $values = [];
        $intervals = [];
        $rises = [];
        $falls = [];
        $previous = null;
        $errors = [];
        $targetSteps = 0;
        $previousTarget = null;
        $dutyValues = [];
        $scan = $data->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use ($pressurePid, $rpmPid, $targetPid, $dutyPid, &$values, &$intervals, &$rises, &$falls, &$previous, &$errors, &$targetSteps, &$previousTarget, &$dutyValues): void {
                $value = McpData::numeric($row, $pressurePid['id']);
                if ($value === null) {
                    return;
                }
                if ($rpmPid !== null && McpData::numeric($row, $rpmPid['id']) === null) {
                    return;
                }
                $values[] = $value;
                if ($targetPid !== null) {
                    $target = McpData::numeric($row, $targetPid['id']);
                    if ($target !== null) {
                        $errors[] = $target - $value;
                        if ($previousTarget !== null && abs($target - $previousTarget) > 0.001) {
                            $targetSteps++;
                        }
                        $previousTarget = $target;
                    }
                }
                if ($dutyPid !== null) {
                    $duty = McpData::numeric($row, $dutyPid['id']);
                    if ($duty !== null) {
                        $dutyValues[] = $duty;
                    }
                }
                if ($previous !== null && $time > $previous[0]) {
                    $dt = ($time - $previous[0]) / 1000;
                    $delta = ($value - $previous[1]) / $dt;
                    $intervals[] = $dt;
                    if ($delta > 0) {
                        $rises[] = $delta;
                    } elseif ($delta < 0) {
                        $falls[] = $delta;
                    }
                }
                $previous = [$time, $value];
            }
        );
        return [
            'session_id' => $session['session_id'],
            'pressure_pid' => $pressurePid,
            'target_pid' => $targetPid,
            'duty_pid' => $dutyPid,
            'observed_pressure' => self::summary($values),
            'observed_target_error' => self::summary($errors),
            'observed_duty' => self::summary($dutyValues),
            'target_value_changes' => $targetSteps,
            'sampling_interval_seconds' => self::summary($intervals),
            'positive_slope_per_second' => self::summary($rises),
            'negative_slope_per_second' => self::summary($falls),
            'stored_pid_settings' => $this->settings('pid'),
            'limitations' => [
                'Observed derivative extrema alone do not prove controller response speed or safe gain direction.',
                'Absolute overshoot and settling time are not derived here. Such conclusions require validated target step events, stable windows and time-aligned actuator telemetry.',
                'No numerical PID coefficient changes are suggested without suitable measurements and hardware-specific validation.',
            ],
        ] + $scan;
    }
}

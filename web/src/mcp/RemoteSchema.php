<?php
declare(strict_types=1);

/**
 * Read-only RedManage configuration schema.
 *
 * Offsets and conversions reflect web/static/js/remote.js (fillData()).
 * This is descriptive metadata, never an encoder or an MCU command interface.
 */
final class McpRemoteSchema
{
    public const GROUPS = [
        'boost' => 'Boost target, solenoid duty, RPM and gear compensation, MAP sensor selection',
        'pid' => 'PID controller gains, sampling frequency, mode and deadband',
        'protection' => 'Engine and transmission protection thresholds and sensor switches',
        'fan' => 'Fan activation, PWM and switch-off behaviour',
        'logic' => 'Programmable logic conditions for outputs PG0 and PG1',
        'inputs' => 'Input modes, analog and DS18B20 assignments and sensor identities',
        'calibration' => 'AFR and MAP voltage calibration and auxiliary sensor calibration',
        'other' => 'Voltage/speed/RPM scaling and service options',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        $fields = [];
        $add = static function (
            string $key,
            string $group,
            string $label,
            int $offset,
            string $codec = 'u8',
            string $unit = '',
            float $scale = 1.0,
            float $bias = 0.0,
            ?array $range = null,
            string $description = '',
            array $related = []
        ) use (&$fields): void {
            $fields[$key] = compact('group', 'label', 'offset', 'codec', 'unit', 'scale', 'bias', 'range', 'description', 'related');
        };

        $add('boost.enabled', 'boost', 'Boost control enabled', 225, 'disabled_zero', '', 1, 0, null,
            'Stored as active-low switch: byte 0 means enabled.');
        $add('boost.target_kpa', 'boost', 'Base target boost pressure', 226, 'u16', 'kPa', 1, -101, [0, 999],
            'Displayed RedManage boost value. Not a target-vs-RPM map; confirm gauge/absolute basis before comparing with telemetry.', ['boost.gear_enabled', 'boost.map_mode']);
        $add('boost.start_kpa', 'boost', 'Boost control start', 229, 'u8', 'kPa', 5, 0, null,
            'At this pressure the solenoid is fully closed (100% duty) before PID regulation.', ['boost.threshold_kpa']);
        $add('boost.threshold_kpa', 'boost', 'Boost control threshold', 330, 'u8', 'kPa', 5, 0, null,
            'Solenoid remains off up to this threshold; it must not exceed boost.start_kpa.', ['boost.start_kpa']);
        $add('boost.base_duty_percent', 'boost', 'Base solenoid duty', 228, 'u8', '%', 1, 0, [0, 100],
            'Base duty in the normal boost mode. Duty corrections and gear mode may override effective output.', ['boost.duty_correction_percent', 'boost.rpm_duty_percent', 'boost.gear_enabled']);
        $add('boost.duty_correction_percent', 'boost', 'Duty correction', 295, 'u8', '%', 1, 0, [0, 100],
            'According to remote.js, a nonzero correction disables RPM boost correction.', ['boost.rpm_duty_percent']);
        $add('boost.rpm_start', 'boost', 'RPM correction start', 245, 'u8', 'rpm', 50, 0, [0, 10000],
            'RPM boost correction begins here; start must be less than end.', ['boost.rpm_end', 'boost.rpm_duty_percent']);
        $add('boost.rpm_end', 'boost', 'RPM correction end', 294, 'u8', 'rpm', 50, 0, [0, 10000],
            'Upper endpoint of RPM boost correction.', ['boost.rpm_start']);
        $add('boost.rpm_duty_percent', 'boost', 'RPM duty correction', 246, 'u8', '%', 1, 0, [0, 100],
            'Duty correction over the configured RPM interval; not a target pressure map.', ['boost.rpm_start', 'boost.rpm_end']);
        $add('boost.map_mode', 'boost', 'MAP sensor mode', 264, 'u8', '', 1, 0, [0, 3],
            'MAP selection determines the valid target pressure range; OBD1 MAP is unsupported for boost control.');
        $add('boost.map_filter', 'boost', 'MAP digital filter', 314, 'u8', '', 1, 0, null,
            'Larger filter values can reduce signal noise but slow the response.', ['pid.kp', 'pid.frequency_hz']);
        $add('boost.solenoid_frequency_code', 'boost', 'Solenoid frequency selection', 323, 'u8', '', 1, 0, [0, 1],
            'Selection code, not frequency in Hz. Reboot may be required to apply the frequency setting.');
        $add('boost.gear_enabled', 'boost', 'Gear-specific targets enabled', 232, 'bool', '', 1, 0, null,
            'When enabled, the base target/duty fields are disabled by the web UI; gear-specific values take precedence.', ['boost.target_kpa', 'boost.base_duty_percent']);
        for ($gear = 1; $gear <= 4; $gear++) {
            $add("boost.gear{$gear}_target_kpa", 'boost', "Gear {$gear} target pressure", 233 + ($gear - 1) * 2, 'u16', 'kPa', 1, -101, [0, 999],
                'Requires gear mode and correct BSx gear input assignment.', ['boost.gear_enabled']);
            $add("boost.gear{$gear}_duty_percent", 'boost', "Gear {$gear} base duty", 240 + $gear, 'u8', '%', 1, 0, [0, 100],
                'Combined duty corrections must not exceed 100%.', ['boost.rpm_duty_percent']);
        }

        $add('pid.mode', 'pid', 'PID proportional mode', 211, 'u8', '', 1, 0, [0, 1],
            '0 = proportional on measurement (M); 1 = proportional on error (E).');
        $add('pid.frequency_hz', 'pid', 'PID sampling frequency', 212, 'u8', 'Hz', 1, 0, [5, 100],
            'Frequency used to sample MAP and adjust the PID output.');
        $add('pid.deadband_kpa', 'pid', 'PID deadband', 329, 'u8', 'kPa', 1, 0, [0, 25],
            'No correction when target-minus-measured pressure is inside this band; small values can amplify noise.', ['boost.map_filter']);
        foreach (['kp' => 213, 'ki' => 217, 'kd' => 221] as $gain => $offset) {
            $meaning = match ($gain) {
                'kp' => 'Proportional gain; Kp=0 means open-loop control in the UI.',
                'ki' => 'Integral gain; can reduce persistent offset but may produce windup.',
                'kd' => 'Derivative gain; sensitive to MAP noise and sample interval.',
            };
            $add("pid.{$gain}", 'pid', strtoupper($gain) . ' gain', $offset, 'f32', '', 1, 0, [0, 100], $meaning,
                ['pid.mode', 'pid.frequency_hz', 'pid.deadband_kpa']);
        }

        foreach ([
            'max_coolant_c' => [249, 1, -40, '°C'], 'max_oil_c' => [247, 1, -40, '°C'],
            'max_egt_c' => [252, 5, 0, '°C'], 'max_intake_c' => [251, 1, -40, '°C'],
            'max_transmission_c' => [296, 1, -40, '°C'], 'max_ambient_c' => [327, 1, -40, '°C'],
            'max_external_c' => [328, 1, -40, '°C'], 'min_coolant_c' => [250, 1, -40, '°C'],
            'min_oil_c' => [248, 1, -40, '°C'], 'min_transmission_c' => [297, 1, -40, '°C'],
        ] as $name => [$offset, $scale, $bias, $unit]) {
            $add("protection.{$name}", 'protection', str_replace('_', ' ', ucfirst($name)), $offset, 'u8', $unit, $scale, $bias,
                null, 'Protection threshold; treat as a safety boundary, not a tuning target.');
        }
        $add('protection.boost_limit_kpa', 'protection', 'Boost pressure limit', 230, 'u16', 'kPa', 1, -101, null,
            'Upper pressure protection threshold; do not recommend relaxing it to achieve higher boost.', ['boost.target_kpa']);
        $add('protection.afr_limit', 'protection', 'AFR protection threshold', 256, 'u8', 'AFR', 0.1, 0);
        foreach (['low_oil_pressure' => 253, 'knock' => 254, 'low_fuel_pressure' => 255] as $name => $offset) {
            $add("protection.{$name}_enabled", 'protection', str_replace('_', ' ', ucfirst($name)) . ' protection', $offset, 'bool');
        }

        foreach ([
            'mode' => [155, 'u8', '', 1, 0], 'sensor_source' => [202, 'u8', '', 1, 0],
            'temperature_c' => [203, 'u8', '°C', 1, -40], 'ac_trigger_enabled' => [208, 'bool', '', 1, 0],
            'engine_trigger_enabled' => [207, 'bool', '', 1, 0], 'test_enabled' => [293, 'bool', '', 1, 0],
            'pwm_inverted' => [205, 'bool', '', 1, 0], 'pwm_speed' => [209, 'u8', '', 1, 0],
            'pwm_width' => [206, 'u8', '', 1, 0], 'pwm_frequency_code' => [201, 'u8', '', 1, 0],
            'switch_off_delay' => [204, 'u8', '', 1, 0], 'hysteresis' => [210, 'u8', '°C', 1, 0],
        ] as $name => [$offset, $codec, $unit, $scale, $bias]) {
            $add("fan.{$name}", 'fan', str_replace('_', ' ', ucfirst($name)), $offset, $codec, $unit, $scale, $bias);
        }
        $add('fan.min_pwm_duty_percent', 'fan', 'Minimum PWM duty', 299, 'fan_min_duty', '%', 1, 0, [0, 100],
            'UI displays round(100 - raw*100/255).');

        foreach ([
            'enabled' => [267, 268], 'engine_running_only' => [321, 322], 'repeat' => [312, 313],
            'off_delay' => [278, 289], 'on_delay_seconds' => [279, 290], 'on_limit_seconds' => [310, 311],
            'a_variable' => [269, 280], 'b_variable' => [270, 281],
            'function_code' => [271, 282], 'a_operand' => [272, 283], 'b_operand' => [273, 284],
            'a_threshold' => [274, 285], 'b_threshold' => [276, 287],
        ] as $name => $offsets) {
            foreach ($offsets as $index => $offset) {
                $codec = in_array($name, ['enabled', 'engine_running_only', 'repeat'], true) ? 'bool' :
                    (str_ends_with($name, 'threshold') ? 'logic_threshold' : 'u8');
                $scale = str_ends_with($name, '_seconds') ? 0.04 : 1;
                $unit = str_ends_with($name, '_seconds') ? 's' : '';
                $add("logic.pg{$index}_{$name}", 'logic', "PG{$index} " . str_replace('_', ' ', $name), $offset,
                    $codec, $unit, $scale, 0, null,
                    $codec === 'logic_threshold' ? 'Decoded according to selected PG input variable (temperature, duty, RPM, etc.).' : 'Programmable output condition.');
            }
        }

        foreach (['bsx_mode' => 266, 'bs1_pullup' => 308, 'bs2_pullup' => 309, 'vfd_mode_code' => 265,
            'analog0_pullup' => 291, 'analog1_pullup' => 292] as $name => $offset) {
            $add("inputs.{$name}", 'inputs', str_replace('_', ' ', ucfirst($name)), $offset,
                str_contains($name, 'pullup') ? 'bool' : 'u8');
        }
        for ($n = 0; $n < 5; $n++) {
            $add("inputs.aux{$n}_assignment", 'inputs', "AUX{$n} assignment", 150 + $n);
            $add("inputs.ds{$n}_assignment", 'inputs', "DS{$n} assignment", 156 + $n);
            $add("inputs.ds{$n}_address", 'inputs', "DS{$n} sensor address", 161 + $n * 8, 'hex8', '', 1, 0, null,
                'Eight-byte DS18B20 sensor identity, not a secret.');
            $add("inputs.ds{$n}_status", 'inputs', "DS{$n} status", 398 + $n, 'u8', '', 1, 0, null,
                '0 = no data; 1 = error; 239 = OK; other value indicates an abnormal or unsupported status.');
        }
        $add('inputs.ds_online_count', 'inputs', 'Online DS sensor count', 403);

        $add('calibration.afr_0v', 'calibration', 'AFR calibration at 0 V', 257, 'u16', 'AFR', 0.01);
        $add('calibration.afr_5v', 'calibration', 'AFR calibration at 5 V', 259, 'u16', 'AFR', 0.01);
        $add('calibration.afr_filter', 'calibration', 'AFR digital filter', 317);
        $add('calibration.oil_pressure_sensor_code', 'calibration', 'Oil pressure sensor mode', 318);
        $add('calibration.fuel_pressure_sensor_code', 'calibration', 'Fuel pressure sensor mode', 319);
        $add('calibration.map_0v', 'calibration', 'MAP low voltage', 300, 'map_voltage', 'V');
        $add('calibration.map_1v', 'calibration', 'MAP high voltage', 302, 'map_voltage', 'V');
        $add('calibration.map_0p', 'calibration', 'MAP low pressure calibration', 304, 'u16', 'kPa');
        $add('calibration.map_1p', 'calibration', 'MAP high pressure calibration', 306, 'u16', 'kPa');
        for ($a = 0; $a < 5; $a++) {
            for ($i = 0; $i < 10; $i++) {
                $offset = $a * 30 + $i * 2;
                $add("calibration.aux{$a}_point{$i}_input", 'calibration', "AUX{$a} input point {$i}", $offset,
                    'aux_input', $a < 2 ? 'V / Ω' : 'V', 1, 0, null,
                    'Inputs 0–1 can be configured as pull-up resistive inputs; read unit according to inputs.analog0/1_pullup.');
                $add("calibration.aux{$a}_point{$i}_temperature", 'calibration', "AUX{$a} temperature point {$i}", $a * 30 + 20 + $i,
                    'u8', 'raw temperature code');
            }
        }

        foreach ([
            'voltage_correction' => [263, 'u8', '', 0.01, 0],
            'speed_multiplier' => [325, 'u8', '', 0.01, 0],
            'rpm_multiplier' => [326, 'u8', '', 0.01, 0],
            'pim_mode' => [262, 'u8', '', 1, 0],
            'pim_output' => [261, 'u8', '', 1, -100],
            'maintenance_mode' => [320, 'u8', '', 1, 0],
            'motor_hours' => [394, 'u32_minutes', 'h', 1, 0],
        ] as $name => [$offset, $codec, $unit, $scale, $bias]) {
            $add("other.{$name}", 'other', str_replace('_', ' ', ucfirst($name)), $offset, $codec, $unit, $scale, $bias);
        }

        return $fields;
    }

    /** @param array<int, int> $bytes */
    private static function uint16(array $bytes, int $offset): int
    {
        return $bytes[$offset] | ($bytes[$offset + 1] << 8);
    }

    /** @param array<int, int> $bytes */
    public static function value(array $bytes, array $field, string $key = ''): int|float|bool|string|null
    {
        $offset = $field['offset'];
        $raw = match ($field['codec']) {
            'u8' => $bytes[$offset],
            'bool' => $bytes[$offset] !== 0,
            'disabled_zero' => $bytes[$offset] === 0,
            'u16' => self::uint16($bytes, $offset),
            'f32' => round(unpack('g', pack('C4', ...array_slice($bytes, $offset, 4)))[1], 2),
            'fan_min_duty' => round(100 - $bytes[$offset] * 100 / 255),
            'map_voltage' => round(self::uint16($bytes, $offset) * 0.0048828, 2),
            'u32_minutes' => intdiv(unpack('V', pack('C4', ...array_slice($bytes, $offset, 4)))[1], 60),
            'hex8' => implode(':', array_map(static fn(int $n): string => strtoupper(str_pad(dechex($n), 2, '0', STR_PAD_LEFT)), array_slice($bytes, $offset, 8))),
            'aux_input' => self::auxInput($bytes, $offset, $key),
            'logic_threshold' => self::logicThreshold($bytes, $offset, $key),
        };
        if (is_bool($raw) || is_string($raw) || $raw === null) {
            return $raw;
        }
        if (!is_finite((float)$raw)) {
            return null;
        }
        if (in_array($field['codec'], ['u8', 'u16'], true) && $field['scale'] === 1.0 && $field['bias'] === 0.0) {
            return $raw;
        }
        return round((float)$raw * $field['scale'] + $field['bias'], 3);
    }

    /** @param array<int, int> $bytes */
    private static function auxInput(array $bytes, int $offset, string $key): int|float
    {
        preg_match('/aux([0-4])_/', $key, $match);
        $sensor = (int)($match[1] ?? 0);
        $v = self::uint16($bytes, $offset) * 0.0048828;
        $pullup = $sensor < 2 && $bytes[291 + $sensor] === 1;
        if (!$pullup) {
            return round(5 - $v, 2);
        }
        // pT() in remote.js: display resistance derived from a 4700-ohm divider.
        return $v <= 0 ? 0 : round((5 - $v) * 4700 / $v);
    }

    /** @param array<int, int> $bytes */
    private static function logicThreshold(array $bytes, int $offset, string $key): int|float
    {
        $value = self::uint16($bytes, $offset);
        $pg = str_contains($key, 'pg1_') ? 1 : 0;
        $a = str_contains($key, '_a_threshold');
        $var = $bytes[($pg === 1 ? 280 : 269) + ($a ? 0 : 1)];
        return match (true) {
            $var >= 1 && $var <= 6 => $value - 40,
            $var === 7 => round($value * 100 / 147),
            $var === 20 => round($value / 60, 3),
            in_array($var, [11, 12, 15, 18], true) => round($value / 100, 3),
            default => $value,
        };
    }

    /** @param array<int, int> $bytes */
    public static function decode(array $bytes, ?string $group = null): array
    {
        self::checkGroup($group);
        if (count($bytes) !== 404) {
            throw new InvalidArgumentException('Invalid RedManage data length');
        }
        $out = [];
        foreach (self::definitions() as $key => $field) {
            if ($group !== null && $field['group'] !== $group) {
                continue;
            }
            $out[$key] = self::value($bytes, $field, $key);
        }
        return $out;
    }

    public static function checkGroup(?string $group): void
    {
        if ($group !== null && !array_key_exists($group, self::GROUPS)) {
            throw new InvalidArgumentException('Unknown RedManage settings group');
        }
    }

    public static function catalog(?string $group = null): array
    {
        self::checkGroup($group);
        $fields = self::definitions();
        if ($group !== null) {
            $fields = array_filter($fields, static fn(array $field): bool => $field['group'] === $group);
        }
        return [
            'groups' => self::GROUPS,
            'fields' => $fields,
            'read_only' => true,
            'source' => 'RedManage remote.js fillData() UI decoding; firmware may vary',
            'caution' => 'This schema describes UI-visible values. It does not certify an ECU firmware variant or authorize edits.',
        ];
    }

    public static function explain(string $key): array
    {
        $field = self::definitions()[$key] ?? null;
        if ($field === null) {
            throw new InvalidArgumentException('Unknown RedManage setting key; use get_remote_schema');
        }
        return ['key' => $key, 'field' => $field, 'group_description' => self::GROUPS[$field['group']],
            'reference' => 'Manual control only at /settings/remote. No write tools are exposed via MCP.'];
    }

    public static function warnings(array $values): array
    {
        $warnings = [];
        if (($values['boost.threshold_kpa'] ?? 0) > ($values['boost.start_kpa'] ?? PHP_INT_MAX)) {
            $warnings[] = 'Boost threshold exceeds start pressure.';
        }
        if (($values['boost.rpm_start'] ?? 0) >= ($values['boost.rpm_end'] ?? PHP_INT_MAX)) {
            $warnings[] = 'RPM compensation start is not below end.';
        }
        $rpmDuty = (int)($values['boost.rpm_duty_percent'] ?? 0);
        $corr = (int)($values['boost.duty_correction_percent'] ?? 0);
        if ($corr > 0) {
            $warnings[] = 'Nonzero duty correction disables RPM boost correction in RedManage.';
        }
        if ((int)($values['boost.base_duty_percent'] ?? 0) + max($rpmDuty, $corr) > 100) {
            $warnings[] = 'Base duty plus boost correction may exceed 100%.';
        }
        for ($n = 1; $n <= 4; $n++) {
            if (($values["boost.gear{$n}_duty_percent"] ?? 0) + max($rpmDuty, $corr) > 100) {
                $warnings[] = "Gear {$n} duty plus correction may exceed 100%.";
            }
        }
        return $warnings;
    }
}

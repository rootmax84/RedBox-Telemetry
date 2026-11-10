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

        $add('boost.enabled', 'boost', 'Boost control enabled', 225, 'enabled_unless_one', '', 1, 0, null,
            'RedManage treats every value except 1 as enabled; normal stored values are 0 (enabled) and 1 (disabled).');
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
            '0 = OBD1 (boost control unsupported); 1 = 250 kPa MAP (UI max boost target 150 kPa); 2 = 300 kPa MAP (UI max 200 kPa); 3 = custom MAP (UI max custom high-pressure point minus 100 kPa).');
        $add('boost.map_filter', 'boost', 'MAP digital filter', 314, 'u8', '', 1, 0, null,
            'Larger filter values can reduce signal noise but slow the response.', ['pid.kp', 'pid.frequency_hz']);
        $add('boost.solenoid_frequency_code', 'boost', 'Solenoid frequency selection', 323, 'u8', '', 1, 0, [0, 1],
            '0 = 30 Hz; 1 = 60 Hz. The stored field is a code, not a numeric frequency in Hz. Reboot is required after changing it.');
        $add('boost.gear_enabled', 'boost', 'Gear-specific targets enabled', 232, 'bool', '', 1, 0, null,
            'When enabled, the base target/duty fields are disabled by the web UI; gear-specific values take precedence.', ['boost.target_kpa', 'boost.base_duty_percent']);
        for ($gear = 1; $gear <= 4; $gear++) {
            $add("boost.gear{$gear}_target_kpa", 'boost', "Gear {$gear} target pressure", 233 + ($gear - 1) * 2, 'u16', 'kPa', 1, -101, [0, 999],
                'Requires gear mode and correct BSx gear input assignment.', ['boost.gear_enabled']);
            $add("boost.gear{$gear}_duty_percent", 'boost', "Gear {$gear} base duty", 240 + $gear, 'u8', '%', 1, 0, [0, 100],
                'Combined duty corrections must not exceed 100%.', ['boost.rpm_duty_percent']);
        }

        $add('pid.mode', 'pid', 'PID proportional mode', 211, 'u8', '', 1, 0, [0, 1],
            '0 = proportional on measurement (M); 1 = proportional on error (E). The web UI defaults to E.');
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
            $uiMax = str_starts_with($name, 'min_') ? 100 : ($name === 'max_egt_c' ? 1000 : 125);
            $add("protection.{$name}", 'protection', str_replace('_', ' ', ucfirst($name)), $offset, 'u8', $unit, $scale, $bias,
                [0, $uiMax], 'Protection threshold; alarms and/or boost reduction may apply. Treat as a safety boundary, not a tuning target.');
        }
        $add('protection.boost_limit_kpa', 'protection', 'Boost pressure limit', 230, 'u16', 'kPa', 1, -101, [0, 999],
            'Upper pressure protection threshold; do not recommend relaxing it to achieve higher boost.', ['boost.target_kpa']);
        $add('protection.afr_limit', 'protection', 'AFR protection threshold', 256, 'u8', 'A/F', 0.1, 0, [0, 22.4],
            'Active under boost; protection may reduce commanded boost when the configured AFR condition is met.');
        foreach (['low_oil_pressure' => 253, 'knock' => 254, 'low_fuel_pressure' => 255] as $name => $offset) {
            $description = match ($name) {
                'low_oil_pressure' => 'Requires a valid RPM signal; RedManage warns and reduces boost when the low-oil-pressure condition is triggered.',
                'knock' => 'Knock detection protection is available only in OBD1 mode; it may reduce boost.',
                'low_fuel_pressure' => 'Enable fuel-pressure protection; correct fuel pressure sensor assignment and calibration are required.',
            };
            $add("protection.{$name}_enabled", 'protection', str_replace('_', ' ', ucfirst($name)) . ' protection', $offset,
                'bool', '', 1, 0, null, $description);
        }

        // Fan field names and engineering units mirror remote_inc.php and
        // the conversions in remote.js. Keep the PWM ramp in °C, not a unitless
        // byte: the upper PWM temperature-ramp edge is target + width.
        // Full physical fan speed at that edge is not guaranteed.
        $add('fan.mode', 'fan', 'Fan output mode', 155, 'u8', '', 1, 0, [0, 1],
            '0 = SW (on/off); 1 = PWM (proportional). RedManage asks for a reboot after changing the mode.');
        $add('fan.sensor_source', 'fan', 'Fan temperature source', 202, 'u8', '', 1, 0, [0, 6],
            '0 = Off, 1 = ECT, 2 = EOT, 3 = IAT, 4 = ATF, 5 = AAT, 6 = EXT. If Off, the configured temperature ramp is inactive.');
        $add('fan.temperature_c', 'fan', 'Fan activation target', 203, 'u8', '°C', 1, -40, [0, 125],
            'Temperature target for temperature-controlled operation, subject to the selected mode, A/C, overrides and startup conditions. UI limit depends on mode (PWM: 105 °C; SW: 125 °C).',
            ['fan.sensor_source', 'fan.mode', 'fan.pwm_width']);
        $add('fan.ac_trigger_enabled', 'fan', 'Run fan when A/C on', 208, 'bool', '', 1, 0, null,
            'A/C request uses BS2 in logic mode or OBD1, according to RedManage.');
        $add('fan.engine_trigger_enabled', 'fan', 'Run fan when engine on', 207, 'bool', '', 1, 0, null,
            'Startup gating by engine-running status, with fallback and overrides. This does not mean that detecting RPM automatically turns the fan on.');
        $add('fan.test_enabled', 'fan', 'Test fan at ignition', 293, 'bool', '', 1, 0, null,
            'Runs a fan output test when ignition is switched on. Not a normal temperature trigger.');
        $add('fan.pwm_inverted', 'fan', 'Invert fan PWM output', 205, 'bool', '', 1, 0, null,
            'Inverts the output polarity. Do not infer physical fan speed from duty without checking wiring and firmware.');
        $add('fan.pwm_speed', 'fan', 'Disable fan above vehicle speed', 209, 'u8', 'km/h', 1, 0, [0, 250],
            'Optional PWM-mode vehicle-speed inhibit, requiring valid vehicle-speed telemetry. The web UI may expose this setting only for OBD1.');
        $add('fan.pwm_width', 'fan', 'Fan PWM temperature ramp width', 206, 'u8', '°C', 1, 0, [3, 20],
            'Defines the upper edge of the temperature-controlled PWM ramp (°C). Maximum output can be requested beyond that edge or through other conditions; the exact edge does not guarantee full speed. '
            . 'Width is a temperature interval, not duty percent or time.',
            ['fan.temperature_c', 'fan.mode', 'fan.min_pwm_duty_percent']);
        $add('fan.pwm_frequency_code', 'fan', 'Fan PWM frequency selection', 201, 'u8', '', 1, 0, [0, 2],
            'Selection code: 0 = 490 Hz, 1 = 120 Hz, 2 = 30 Hz. Reboot after changing this setting.');
        $add('fan.switch_off_delay', 'fan', 'Fan switch-off delay', 204, 'u8', 's', 1, 0, [0, 65],
            'Delay after the hysteresis threshold is reached, in SW mode.', ['fan.hysteresis']);
        $add('fan.hysteresis', 'fan', 'Fan switch-off hysteresis', 210, 'u8', '°C', 1, 0, [1, 10],
            'Temperature hysteresis for SW mode, not the PWM ramp width.', ['fan.temperature_c']);
        $add('fan.min_pwm_duty_percent', 'fan', 'Minimum fan PWM duty', 299, 'fan_min_duty', '%', 1, 0, [10, 30],
            'RedManage displays round(100 - raw*100/255). PWM ramps from this configured minimum.',
            ['fan.pwm_width', 'fan.temperature_c']);

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
                $isShortTime = in_array($name, ['on_delay_seconds', 'on_limit_seconds'], true);
                $scale = $isShortTime ? 0.04 : 1;
                $unit = $isShortTime || $name === 'off_delay' ? 's' : '';
                $range = match ($name) {
                    'off_delay' => [0, 65],
                    'on_delay_seconds', 'on_limit_seconds' => [0, 10],
                    'a_variable', 'b_variable' => [0, 22],
                    'function_code' => [0, 2],
                    'a_operand', 'b_operand' => [0, 1],
                    default => null,
                };
                $description = match ($name) {
                    'off_delay' => 'Switch-off delay in seconds.',
                    'on_delay_seconds' => 'Switch-on delay in seconds. Stored in 0.04-second units; current web editor submits whole seconds.',
                    'on_limit_seconds' => 'Maximum on-time in seconds; can be overridden by loop mode.',
                    'a_threshold', 'b_threshold' => 'Engineering units depend on the corresponding PG variable. See settings.units and derived.logic_conditions.',
                    'a_variable', 'b_variable' => 'Selects the telemetry signal that determines the units and conversion of the corresponding threshold.',
                    default => 'Programmable output condition.',
                };
                $related = match ($name) {
                    'a_threshold' => ["logic.pg{$index}_a_variable"],
                    'b_threshold' => ["logic.pg{$index}_b_variable"],
                    'a_variable' => ["logic.pg{$index}_a_threshold"],
                    'b_variable' => ["logic.pg{$index}_b_threshold"],
                    default => [],
                };
                $add("logic.pg{$index}_{$name}", 'logic', "PG{$index} " . str_replace('_', ' ', $name), $offset,
                    $codec, $unit, $scale, 0, $range, $description, $related);
            }
        }

        foreach (['bsx_mode' => 266, 'bs1_pullup' => 308, 'bs2_pullup' => 309, 'vfd_mode_code' => 265,
            'analog0_pullup' => 291, 'analog1_pullup' => 292] as $name => $offset) {
            $ranges = ['bsx_mode' => [0, 1]];
            $add("inputs.{$name}", 'inputs', str_replace('_', ' ', ucfirst($name)), $offset,
                str_contains($name, 'pullup') ? 'bool' : 'u8', '', 1, 0, $ranges[$name] ?? null,
                $name === 'vfd_mode_code' ? 'Stored firmware code (20, 30, 0, 21, 31 or 70), not the UI option index.' : 'Input configuration.');
        }
        for ($n = 0; $n < 5; $n++) {
            $add("inputs.aux{$n}_assignment", 'inputs', "AUX{$n} assignment", 150 + $n, 'u8', '', 1, 0, [0, 10],
                'Sensor function assigned to the analog AUX input; check for collisions with other AUX/DS assignments.');
            $add("inputs.ds{$n}_assignment", 'inputs', "DS{$n} assignment", 156 + $n, 'u8', '', 1, 0, [0, 6],
                'Sensor function assigned to the DS18B20 input; collisions with other AUX/DS assignments are invalid.');
            $add("inputs.ds{$n}_address", 'inputs', "DS{$n} sensor address", 161 + $n * 8, 'hex8', '', 1, 0, null,
                'Eight-byte DS18B20 sensor identity, not a secret.');
            $add("inputs.ds{$n}_status", 'inputs', "DS{$n} status", 398 + $n, 'u8', '', 1, 0, null,
                '0 = no data; 1 = error; 239 = OK; other value indicates an abnormal or unsupported status.');
        }
        $add('inputs.ds_online_count', 'inputs', 'Online DS sensor count', 403, 'u8', '', 1, 0, [0, 5],
            'Read-only status count of online DS sensors, not a configurable setting.');

        $add('calibration.afr_0v', 'calibration', 'AFR calibration at 0 V', 257, 'u16', 'A/F', 0.01, 0, [0, 25],
            'Air/fuel ratio mapped to a 0 V sensor signal.');
        $add('calibration.afr_5v', 'calibration', 'AFR calibration at 5 V', 259, 'u16', 'A/F', 0.01, 0, [0, 25],
            'Air/fuel ratio mapped to a 5 V sensor signal.');
        $add('calibration.afr_filter', 'calibration', 'AFR digital filter', 317, 'u8', '', 1, 0, [0, 20],
            'Higher filtering smooths noisy AFR readings, but slows response.');
        $add('calibration.oil_pressure_sensor_code', 'calibration', 'Oil pressure sensor mode', 318, 'u8', '', 1, 0, [0, 1],
            '0 = 10 bar; 1 = 7 bar full-scale sensor. Affects calibration and valid programmable-logic thresholds.');
        $add('calibration.fuel_pressure_sensor_code', 'calibration', 'Fuel pressure sensor mode', 319, 'u8', '', 1, 0, [0, 1],
            '0 = 10 bar; 1 = 7 bar full-scale sensor. Affects calibration and valid programmable-logic thresholds.');
        $add('calibration.map_0v', 'calibration', 'MAP low voltage', 300, 'map_voltage', 'V', 1, 0, [0, 5],
            'Voltage of the custom MAP sensor at its low absolute-pressure calibration point.');
        $add('calibration.map_1v', 'calibration', 'MAP high voltage', 302, 'map_voltage', 'V', 1, 0, [0, 5],
            'Voltage of the custom MAP sensor at its high absolute-pressure calibration point.');
        $add('calibration.map_0p', 'calibration', 'MAP low absolute pressure calibration', 304, 'u16', 'kPa', 1, 0, [0, 1000],
            'Absolute manifold pressure, not boost gauge pressure.');
        $add('calibration.map_1p', 'calibration', 'MAP high absolute pressure calibration', 306, 'u16', 'kPa', 1, 0, [0, 1000],
            'Absolute manifold pressure. Custom MAP boost target UI limit uses this value minus 100 kPa.');
        for ($a = 0; $a < 5; $a++) {
            for ($i = 0; $i < 10; $i++) {
                $offset = $a * 30 + $i * 2;
                $add("calibration.aux{$a}_point{$i}_input", 'calibration', "AUX{$a} input point {$i}", $offset,
                    'aux_input', $a < 2 ? 'V / Ω' : 'V', 1, 0, null,
                    'Decoded as (5 - ADC voltage) in V, or as resistance in Ω when AUX0/AUX1 pull-up is enabled. See settings.units for the effective unit.');
                $add("calibration.aux{$a}_point{$i}_temperature", 'calibration', "AUX{$a} temperature point {$i}", $a * 30 + 20 + $i,
                    'u8', '°C', 1, 0, [0, 150],
                    'Calibrated temperature point in degrees Celsius as displayed in RedManage.');
            }
        }

        foreach ([
            'voltage_correction' => [263, 'u8', 'V', 0.01, 0],
            'speed_multiplier' => [325, 'u8', '×', 0.01, 0],
            'rpm_multiplier' => [326, 'u8', '×', 0.01, 0],
            'pim_mode' => [262, 'u8', '', 1, 0],
            'pim_output' => [261, 'u8', '%', 1, -100],
            'motor_hours_mode' => [320, 'u8', '', 1, 0],
            'maintenance_mode' => [320, 'u8', '', 1, 0], // Deprecated name: kept for backwards compatibility.
            'motor_hours' => [394, 'u32_minutes', 'h', 1, 0],
        ] as $name => [$offset, $codec, $unit, $scale, $bias]) {
            $ranges = [
                'voltage_correction' => [0, 2.55],
                'speed_multiplier' => [0.01, 1.99],
                'rpm_multiplier' => [0.01, 1.99],
                'pim_mode' => [0, 14],
                'pim_output' => [0, 85],
                'motor_hours_mode' => [0, 1],
                'maintenance_mode' => [0, 1],
            ];
            $description = match ($name) {
                'voltage_correction' => 'Voltmeter correction displayed in V (not a dimensionless multiplier).',
                'speed_multiplier', 'rpm_multiplier' => 'Dimensionless multiplier; 1.00 means no correction.',
                'pim_mode' => 'PIM output source/mode; PIM output is disabled for manual editing in the web UI when mode is greater than 2.',
                'maintenance_mode' => 'Deprecated name for other.motor_hours_mode; this controls the motor-hours counter, not maintenance mode.',
                'motor_hours_mode' => '0 = Simple; 1 = Advanced (depends on engine load).',
                'motor_hours' => 'Recorded accumulated motor hours (whole hours as displayed in RedManage).',
                'pim_output' => 'Stored PIM output in percent. The web UI disables manual adjustment when PIM mode exceeds 2.',
                default => '',
            };
            $add("other.{$name}", 'other', str_replace('_', ' ', ucfirst($name)), $offset, $codec, $unit, $scale, $bias,
                $ranges[$name] ?? null, $description);
        }

        foreach (self::optionCatalog() as $key => $choices) {
            if (isset($fields[$key])) {
                $fields[$key]['options'] = $choices;
            }
        }
        $fields['other.maintenance_mode']['deprecated_alias_for'] = 'other.motor_hours_mode';

        return $fields;
    }

    /**
     * Stored numeric codes and their UI meanings. The values are taken from
     * remote_inc.php controls and the special VFD encoding in remote.js.
     * No text is generated from a user-controlled code.
     *
     * @return array<string, array<int, string>>
     */
    private static function optionCatalog(): array
    {
        $temperatureSources = [
            0 => 'Off', 1 => 'ECT', 2 => 'EOT', 3 => 'IAT',
            4 => 'ATF', 5 => 'AAT', 6 => 'EXT',
        ];
        $auxSources = $temperatureSources + [7 => 'MAP', 8 => 'FLP', 9 => 'AFR', 10 => 'EOP'];
        $logicVariables = $temperatureSources + [
            7 => 'TPS', 8 => 'SPD', 9 => 'RLC', 10 => 'BST', 11 => 'EOP',
            12 => 'FLP', 13 => 'MAP', 14 => 'RPM', 15 => 'VLT', 16 => 'EGT',
            17 => 'KNK', 18 => 'AFR', 19 => 'ERT', 20 => 'MH',
            21 => 'BS1', 22 => 'BS2',
        ];
        $options = [
            'boost.map_mode' => [0 => 'OBD1', 1 => '250 kPa MAP', 2 => '300 kPa MAP', 3 => 'Custom MAP'],
            'boost.solenoid_frequency_code' => [0 => '30 Hz', 1 => '60 Hz'],
            'pid.mode' => [0 => 'M — proportional on measurement', 1 => 'E — proportional on error'],
            'fan.mode' => [0 => 'SW', 1 => 'PWM'],
            'fan.sensor_source' => $temperatureSources,
            'fan.pwm_frequency_code' => [0 => '490 Hz', 1 => '120 Hz', 2 => '30 Hz'],
            'inputs.bsx_mode' => [0 => 'Gear inputs', 1 => 'Programmable logic inputs'],
            'inputs.vfd_mode_code' => [
                20 => 'RPM (R6/V6)', 30 => 'RPM (R4/V8)', 0 => 'OBD1',
                21 => 'OBD1+RPM (R6/V6)', 31 => 'OBD1+RPM (R4/V8)', 70 => 'RPM (V2)',
            ],
            'calibration.oil_pressure_sensor_code' => [0 => '10 bar sensor', 1 => '7 bar sensor'],
            'calibration.fuel_pressure_sensor_code' => [0 => '10 bar sensor', 1 => '7 bar sensor'],
            'other.pim_mode' => [
                0 => 'Static', 1 => 'MAP', 2 => 'MAF', 3 => 'ECT', 4 => 'EOT',
                5 => 'IAT', 6 => 'ATF', 7 => 'AAT', 8 => 'EXT', 9 => 'BST',
                10 => 'FLP', 11 => 'EOP', 12 => 'AFR', 13 => 'EGT', 14 => 'RPM',
            ],
            'other.motor_hours_mode' => [0 => 'Simple', 1 => 'Advanced'],
            'other.maintenance_mode' => [0 => 'Simple', 1 => 'Advanced'],
        ];
        for ($pg = 0; $pg <= 1; $pg++) {
            $options["logic.pg{$pg}_a_variable"] = $logicVariables;
            $options["logic.pg{$pg}_b_variable"] = $logicVariables;
            $options["logic.pg{$pg}_function_code"] = [0 => 'A', 1 => 'A AND B', 2 => 'A OR B'];
            $options["logic.pg{$pg}_a_operand"] = [0 => '<', 1 => '>'];
            $options["logic.pg{$pg}_b_operand"] = [0 => '<', 1 => '>'];
        }
        for ($sensor = 0; $sensor < 5; $sensor++) {
            $options["inputs.aux{$sensor}_assignment"] = $auxSources;
            $options["inputs.ds{$sensor}_assignment"] = $temperatureSources;
            $options["inputs.ds{$sensor}_status"] = [0 => 'No data', 1 => 'Error', 239 => 'OK'];
        }
        return $options;
    }

    /**
     * Decoded labels for numeric option values; unknown values remain explicitly
     * unknown rather than being interpreted as a different UI selection.
     *
     * @param array<string, int|float|bool|string|null> $values
     * @return array<string, string>
     */
    public static function valueLabels(array $values, ?string $group = null): array
    {
        self::checkGroup($group);
        $out = [];
        foreach (self::optionCatalog() as $key => $options) {
            if (($group !== null && !str_starts_with($key, $group . '.')) || !array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            if (!is_int($value) && !is_float($value)) {
                continue;
            }
            $out[$key] = $options[(int)$value] ?? 'Unknown/unsupported code';
        }
        return $out;
    }

    /**
     * Engineering units for programmable-logic comparisons depend on the chosen
     * signal. The mapping mirrors limits_logic() and calcVal() in remote.js.
     *
     * @return array<int, string>
     */
    private static function logicVariableUnits(): array
    {
        return [
            1 => '°C', 2 => '°C', 3 => '°C', 4 => '°C', 5 => '°C', 6 => '°C',
            7 => '%', 8 => 'km/h', 11 => 'bar', 12 => 'bar', 13 => 'kPa',
            14 => 'rpm', 15 => 'V', 16 => '°C', 18 => 'A/F', 19 => 's', 20 => 'h',
        ];
    }

    /**
     * Explain both PG comparisons with their signal, decoded value and
     * context-dependent unit. Does not predict any real output state.
     *
     * @param array<string, int|float|bool|string|null> $values
     * @return array<string, array<string, mixed>>
     */
    public static function logicConditions(array $values): array
    {
        $labels = self::valueLabels($values, 'logic');
        $units = self::logicVariableUnits();
        $out = [];
        for ($pg = 0; $pg <= 1; $pg++) {
            foreach (['a', 'b'] as $side) {
                $prefix = "logic.pg{$pg}_{$side}_";
                $sourceCode = (int)($values[$prefix . 'variable'] ?? 0);
                $out["pg{$pg}_{$side}"] = [
                    'signal' => $labels[$prefix . 'variable'] ?? 'Unknown',
                    'signal_code' => $sourceCode,
                    'operator' => $labels[$prefix . 'operand'] ?? 'Unknown',
                    'threshold' => $values[$prefix . 'threshold'] ?? null,
                    'unit' => $units[$sourceCode] ?? '',
                    'enabled' => $sourceCode !== 0 && (bool)($values["logic.pg{$pg}_enabled"] ?? false),
                ];
            }
        }
        return $out;
    }

    /**
     * UI-selected MAP capability, not a recommendation to change boost targets.
     * Values for fixed MAP modes follow limits_boost() in remote.js.
     *
     * @param array<string, int|float|bool|string|null> $values
     * @return array<string, mixed>
     */
    public static function boostModeSummary(array $values): array
    {
        $mapCode = (int)($values['boost.map_mode'] ?? -1);
        $mapCustom = $values['calibration.map_1p'] ?? null;
        $uiMax = match ($mapCode) {
            0 => 0,
            1 => 150,
            2 => 200,
            3 => is_numeric($mapCustom) ? (float)$mapCustom - 100 : null,
            default => null,
        };
        return [
            'enabled' => (bool)($values['boost.enabled'] ?? false),
            'gear_specific_targets_enabled' => (bool)($values['boost.gear_enabled'] ?? false),
            'map_mode' => self::valueLabels($values, 'boost')['boost.map_mode'] ?? 'Unknown',
            'boost_control_supported_by_map' => $mapCode >= 1 && $mapCode <= 3,
            'ui_target_limit_kpa' => $uiMax,
            'caution' => 'RedManage displays boost-target values in kPa with an offset of 101; MAP calibration is absolute pressure. Verify pressure reference before comparing with live telemetry.',
        ];
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
            'bool' => $bytes[$offset] === 1,
            'enabled_unless_one' => $bytes[$offset] !== 1,
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

    /**
     * Unit map for decoded settings. Optional values make dependent units explicit.
     * Unlike the schema catalogue this can be
     * returned alongside values, so agents do not need a second tool call.
     *
     * @return array<string, string>
     */
    public static function units(?string $group = null, ?array $values = null): array
    {
        self::checkGroup($group);
        $units = [];
        foreach (self::definitions() as $key => $field) {
            if (($group === null || $field['group'] === $group) && $field['unit'] !== '') {
                $units[$key] = $field['unit'];
            }
        }
        if ($values !== null && ($group === null || $group === 'logic')) {
            $logicUnits = self::logicVariableUnits();
            for ($pg = 0; $pg <= 1; $pg++) {
                foreach (['a', 'b'] as $side) {
                    $base = "logic.pg{$pg}_{$side}_";
                    $code = (int)($values[$base . 'variable'] ?? 0);
                    if (isset($logicUnits[$code])) {
                        $units[$base . 'threshold'] = $logicUnits[$code];
                    }
                }
            }
        }
        if ($values !== null && ($group === null || $group === 'calibration')) {
            for ($aux = 0; $aux < 5; $aux++) {
                $unit = $aux < 2 && ($values["inputs.analog{$aux}_pullup"] ?? false) ? 'Ω' : 'V';
                for ($point = 0; $point < 10; $point++) {
                    $units["calibration.aux{$aux}_point{$point}_input"] = $unit;
                }
            }
        }
        return $units;
    }

    /**
     * User-facing interpretation of the stored fan configuration.
     * This describes configured thresholds, not confirmed fan performance.
     *
     * @param array<string, int|float|bool|string|null> $values
     * @return array<string, mixed>
     */
    public static function fanPwmSummary(array $values): array
    {
        $modeCode = (int)($values['fan.mode'] ?? -1);
        $sensorCode = (int)($values['fan.sensor_source'] ?? -1);
        $target = $values['fan.temperature_c'] ?? null;
        $width = $values['fan.pwm_width'] ?? null;
        $isPwm = $modeCode === 1;
        $configuredMax = $isPwm && is_numeric($target) && is_numeric($width)
            ? (float)$target + (float)$width
            : null;
        $frequencyCode = (int)($values['fan.pwm_frequency_code'] ?? -1);

        return [
            'mode' => match ($modeCode) {
                0 => 'SW',
                1 => 'PWM',
                default => 'unknown',
            },
            'temperature_source' => match ($sensorCode) {
                0 => 'Off',
                1 => 'ECT',
                2 => 'EOT',
                3 => 'IAT',
                4 => 'ATF',
                5 => 'AAT',
                6 => 'EXT',
                default => 'unknown',
            },
            'temperature_control_enabled' => $sensorCode > 0 && $sensorCode <= 6,
            'activation_temperature_c' => $target,
            'minimum_pwm_duty_percent' => $values['fan.min_pwm_duty_percent'] ?? null,
            'pwm_ramp_width_c' => $width,
            'configured_full_pwm_temperature_c' => $configuredMax, // Legacy name; nominal upper ramp edge only.
            'upper_pwm_ramp_temperature_c' => $configuredMax,
            'legacy_full_pwm_key_note' => 'configured_full_pwm_temperature_c is retained for compatibility; it denotes the upper temperature-ramp edge, not verified 100% duty or motor speed.',
            'pwm_frequency_hz' => match ($frequencyCode) {
                0 => 490,
                1 => 120,
                2 => 30,
                default => null,
            },
            'rule' => $isPwm
                ? 'The configured upper temperature-ramp edge is defined by the activation target and PWM width; an exact-edge reading does not prove maximum fan output.'
                : 'PWM width does not define a full-speed threshold in SW mode.',
            'caution' => 'Stored configuration only. In PWM mode A/C, override, fault, start conditions and speed can modify the output. Status/duty is not a fan tachometer or proof of physical speed.',
        ];
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
            'architecture_reference' => ['tool' => 'get_remote_architecture'],
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
        // A saved but disabled boost configuration must not be presented as an
        // active tuning fault. Warnings below concern the currently selected
        // boost path, while the schema retains every stored field for review.
        if (!($values['boost.enabled'] ?? false)) {
            return [];
        }

        $warnings = [];
        if (($values['boost.threshold_kpa'] ?? 0) > ($values['boost.start_kpa'] ?? PHP_INT_MAX)) {
            $warnings[] = 'Boost threshold exceeds start pressure.';
        }
        $rpmDuty = (int)($values['boost.rpm_duty_percent'] ?? 0);
        $corr = (int)($values['boost.duty_correction_percent'] ?? 0);
        if ($corr === 0 && $rpmDuty > 0
            && ($values['boost.rpm_start'] ?? 0) >= ($values['boost.rpm_end'] ?? PHP_INT_MAX)) {
            $warnings[] = 'Enabled RPM correction start is not below end.';
        }
        if ($corr > 0 && $rpmDuty > 0) {
            $warnings[] = 'Nonzero duty correction disables the configured RPM boost correction in RedManage.';
        }

        if ($values['boost.gear_enabled'] ?? false) {
            for ($n = 1; $n <= 4; $n++) {
                if (($values["boost.gear{$n}_duty_percent"] ?? 0) + max($rpmDuty, $corr) > 100) {
                    $warnings[] = "Gear {$n} duty plus boost correction may exceed 100%.";
                }
            }
        } elseif (($values['boost.base_duty_percent'] ?? 0) + max($rpmDuty, $corr) > 100) {
            $warnings[] = 'Base duty plus boost correction may exceed 100%.';
        }
        return $warnings;
    }

}

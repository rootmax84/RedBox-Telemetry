<?php
declare(strict_types=1);

/**
 * Public, read-only descriptions of RedManage controller behaviour.
 *
 * This catalogue intentionally contains no MCU source, state machine,
 * control equations, byte-level implementation or actuator instructions.
 * Descriptions apply to the reviewed firmware family, not every device.
 */
final class McpRemoteBehavior
{
    public const TOPICS = [
        'boost' => 'Boost operating modes and constraints',
        'pid' => 'Closed-loop pressure regulation and interpretation',
        'fan' => 'SW/PWM fan modes and conditional overrides',
        'protection' => 'Protective conditions and diagnostic cautions',
    ];

    /** @return array<string, mixed> */
    public static function describe(?string $topic = null): array
    {
        if ($topic !== null && !array_key_exists($topic, self::TOPICS)) {
            throw new InvalidArgumentException('Unknown controller behaviour topic');
        }

        if ($topic === null) {
            return [
                'topics' => self::TOPICS,
                'usage' => 'Select a topic to obtain operating conditions, related RedManage settings and diagnostic cautions. '
                    . 'Then use get_remote_settings for current saved values and telemetry tools to evaluate observed behaviour.',
                'read_only' => true,
                'architecture_reference' => ['tool' => 'get_remote_architecture'],
                'source_confidence' => 'Functional descriptions derived from reviewed firmware fragments; installed firmware version is not verified.',
                'disclosure' => 'No implementation code, internal state transitions or control equations are exposed.',
            ];
        }

        $details = match ($topic) {
            'boost' => self::boost(),
            'pid' => self::pid(),
            'fan' => self::fan(),
            'protection' => self::protection(),
        };

        return [
            'topic' => $topic,
            'title' => self::TOPICS[$topic],
            'behavior' => $details,
            'read_only' => true,
            'architecture_reference' => ['tool' => 'get_remote_architecture'],
            'source_confidence' => 'Reviewed MCU fragments describe a firmware family, not the verified firmware currently installed.',
            'disclosure' => 'High-level operating semantics only; no proprietary control equations or source code.',
            'general_limitations' => [
                'Saved RedManage settings do not prove the current device state or firmware revision.',
                'Sensor calibration, sampling cadence and unit basis must be checked before comparing settings and telemetry.',
                'Actuator command or reported duty is not a measurement of boost achieved, airflow or fan motor speed.',
                'Do not infer safe numeric changes to boost, PID gains or safety thresholds from these descriptions alone.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function boost(): array
    {
        return [
            'summary' => 'Boost regulation depends on its enable state, operating conditions, selected targets, limits and measured manifold pressure.',
            'operating_modes' => [
                'Disabled or prerequisites not satisfied: normal active boost regulation is not available.',
                'Below the start of closed-loop regulation: output behaviour may depend on a separate pressure threshold and protection state.',
                'Active closed-loop regulation: controller response depends on measured pressure, selected setpoint and permitted actuator duty.',
                'Open-loop mode: a zero proportional gain changes the behaviour; do not interpret this as a normally tuned PID loop.',
                'Protective reduction: safety conditions may override otherwise plausible target and duty settings.',
            ],
            'interactions' => [
                'Gear-based targets may override the general boost target and duty when that mode is active.',
                'Pressure-based and RPM-based duty corrections are alternatives rather than two independent additions.',
                'RPM correction modifies actuator duty, not a target boost pressure map indexed by engine RPM.',
                'Both controller limits and mechanical capability can prevent the commanded pressure from being reached.',
            ],
            'relevant_settings' => [
                'boost.enabled', 'boost.target_kpa', 'boost.base_duty_percent', 'boost.gear_enabled',
                'boost.start_kpa', 'boost.threshold_kpa', 'boost.duty_correction_percent',
                'boost.rpm_start', 'boost.rpm_end', 'boost.rpm_duty_percent', 'protection.boost_limit_kpa',
            ],
            'diagnostic_guidance' => [
                'First confirm whether the controller was enabled and all operating prerequisites were satisfied.',
                'Check measured MAP against the correct pressure basis and sensor calibration.',
                'Compare comparable RPM/load windows in recorded sessions, including actuator duty if available.',
                'A persistent target error does not by itself identify the correct duty or PID adjustment.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function pid(): array
    {
        return [
            'summary' => 'PID is a periodic feedback controller. Its response depends on controller mode, sampling interval, pressure measurement and output constraints.',
            'behavior' => [
                'The proportional term can use either error-based or measurement-based behaviour, depending on the saved mode.',
                'Integral action can compensate persistent error but is affected by output saturation and mode changes.',
                'Derivative action responds to measured pressure change and can be sensitive to sensor noise and filtering.',
                'A configured deadband can suppress small corrections near the target.',
                'The main loop frequency, PID calculation frequency and logged telemetry sampling frequency are not identical.',
                'Controller output is constrained by the active duty allowance and may not match an unconstrained calculation.',
            ],
            'relevant_settings' => [
                'pid.kp', 'pid.ki', 'pid.kd', 'pid.mode', 'pid.frequency_hz', 'pid.deadband_kpa',
                'boost.map_filter', 'boost.base_duty_percent', 'boost.gear_enabled',
            ],
            'diagnostic_guidance' => [
                'Determine whether closed-loop operation was actually active during the selected session segment.',
                'Look for time-aligned target, measured pressure and commanded duty before attributing oscillation to gains.',
                'Sampling gaps and filtered pressure readings can hide fast transients or create misleading apparent delays.',
                'Do not give unvalidated replacement gain values or disable safety constraints to improve response.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function fan(): array
    {
        return [
            'summary' => 'The fan supports switch and PWM modes. Temperature is one input; A/C demand, explicit override, start conditions and faults may affect the output.',
            'operating_modes' => [
                'SW mode: temperature-based switching uses hysteresis and a configurable switch-off delay.',
                'PWM mode: temperature-based output rises across a configured temperature interval, with a separate minimum duty setting.',
                'The upper end of the PWM temperature range is a transition region; it must not be interpreted as proof that the fan is already at physical maximum speed at that exact temperature.',
                'An A/C request can activate fan operation independently of the normal temperature threshold, with mode-dependent behaviour.',
                'A temporary manual request, test mode or fault-related condition can override the normal temperature path.',
                'Startup conditions, engine RPM/run-status gating and a supported vehicle-speed condition can influence whether the fan is commanded.',
                'Fan PWM frequency describes the electrical output signal, not how often the temperature-control decision is evaluated.',
            ],
            'relevant_settings' => [
                'fan.mode', 'fan.sensor_source', 'fan.temperature_c', 'fan.pwm_width',
                'fan.min_pwm_duty_percent', 'fan.hysteresis', 'fan.switch_off_delay',
                'fan.ac_trigger_enabled', 'fan.engine_trigger_enabled', 'fan.test_enabled',
                'fan.pwm_inverted', 'fan.pwm_speed', 'fan.pwm_frequency_code',
            ],
            'diagnostic_guidance' => [
                'Check SW/PWM mode, the chosen temperature sensor, its validity and the configured threshold.',
                'Check whether A/C, temporary override, startup state, speed gating or a fault may explain unexpected fan operation.',
                'Do not compare SW hysteresis with PWM temperature width: they have different meanings.',
                'A recorded fan status is a commanded/status value, not an independent tachometer measurement of fan motor rotation.',
                'Temperature sensor updates and logged values may have different cadences; a single pair of samples cannot establish precise switching timing.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function protection(): array
    {
        return [
            'summary' => 'Configured engine and sensor protection conditions can override normal boost operation; recorded settings do not prove that a fault occurred.',
            'behavior' => [
                'Multiple pressure, temperature and sensor conditions may contribute to protective operation.',
                'Protective reduction can persist while relevant conditions remain active; later recovery depends on firmware state and input validity.',
                'Some conditions require a valid engine RPM or protocol-specific signal before they can be evaluated.',
                'Protection thresholds are safety limits, not performance targets.',
            ],
            'relevant_settings' => [
                'protection.boost_limit_kpa', 'protection.max_coolant_c', 'protection.max_oil_c',
                'protection.max_egt_c', 'protection.max_intake_c',
                'protection.low_oil_pressure_enabled', 'protection.low_fuel_pressure_enabled',
                'protection.afr_limit', 'protection.knock_enabled',
            ],
            'diagnostic_guidance' => [
                'Look for correlated warning/status telemetry and independently validate sensor readings.',
                'Do not claim a particular protection activated solely because a threshold exists in saved configuration.',
                'Do not recommend bypassing, raising or disabling protections to attain a boost target.',
            ],
        ];
    }
}

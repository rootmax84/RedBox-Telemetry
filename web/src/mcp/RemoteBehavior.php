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
        'logic' => 'PG0/PG1 programmable output logic and timing',
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
            'logic' => self::logic(),
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
                'Sensor calibration, sampling cadence and unit basis must be checked before comparing settings and telemetry; a registered DS address is not proof of a connected sensor.',
                'Actuator command or reported duty is not a measurement of boost achieved, airflow or fan motor speed.',
                'Do not infer safe numeric changes to boost, PID gains or safety thresholds from these descriptions alone.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function boost(): array
    {
        return [
            'summary' => 'Normal boost output requires the controller enabled, a positive selected target and duty, and ALL three minimum-temperature gates (coolant AND oil AND transmission). Actual MAP and protection state determine the subsequent output mode.',
            'temperature_prerequisites' => [
                'operator' => 'AND',
                'checks' => [
                    'Coolant ECT at/above protection.min_coolant_c',
                    'Engine oil EOT at/above protection.min_oil_c',
                    'Transmission ATF at/above protection.min_transmission_c',
                ],
                'disabled_check' => 'Any individual minimum stored as 0 °C (MCU raw 40) is bypassed, independently of the other two.',
                'additional_requirements' => 'Boost enabled, selected target above zero gauge (MCU Setpoint > 101), selected Duty > 0; gear mode may replace target/duty.',
                'observable_limit' => 'Saved thresholds alone cannot establish current eligibility; all three live temperatures, selected mode and boost state are needed.',
            ],
            'operating_modes' => [
                'Disabled or prerequisites not satisfied: normal active boost regulation is not available.',
                'Below the ABSOLUTE start threshold (101 + UI boost.start_kpa), an ABSOLUTE MAP reading at/above 101 + UI boost.threshold_kpa can command full solenoid output without Rollback (anti-lag); below the threshold it is off. With Rollback active, a reduced duty is used instead.',
                'Active closed-loop regulation: controller response depends on measured pressure, selected setpoint and permitted actuator duty.',
                'At or above the ABSOLUTE start threshold (101 + displayed boost.start_kpa): Kp > 0 permits closed-loop PID regulation; Kp = 0 uses a bounded static duty rather than a PID loop.',
                'During approximately the first 1.25 s after MCU startup the solenoid has a temporary startup command; do not confuse it with normal temperature-permitted boost regulation.',
                'Protective reduction: safety conditions may override otherwise plausible target and duty settings.',
            ],
            'interactions' => [
                'Gear-based targets override the base target/duty only when the mode is enabled, BSx is in gear mode and the decoded gear is 0–4. Gear 0 commands no boost, while unavailable/unknown gear cannot be assumed to select a valid profile.',
                'Pressure-based and RPM-based duty corrections are alternatives rather than two independent additions.',
                'RPM correction modifies actuator duty, not a target boost pressure map indexed by engine RPM.',
                'Both controller limits and mechanical capability can prevent the commanded pressure from being reached.',
            ],
            'relevant_settings' => [
                'boost.enabled', 'boost.target_kpa', 'boost.base_duty_percent', 'boost.gear_enabled',
                'boost.start_kpa', 'boost.threshold_kpa', 'protection.min_coolant_c',
                'protection.min_oil_c', 'protection.min_transmission_c', 'boost.duty_correction_percent',
                'boost.rpm_start', 'boost.rpm_end', 'boost.rpm_duty_percent', 'protection.boost_limit_kpa',
            ],
            'diagnostic_guidance' => [
                'First retrieve get_remote_settings(group=boost) or group=protection to read the derived boost temperature AND gate, then verify all three actual temperatures and selected target/duty; these are prerequisites, not three alternative triggers.',
                'Read derived.boost_mode.pressure_reference from get_remote_settings(boost): the MCU uses absolute MAP while RedManage target/start/threshold and protection limit are displayed in gauge kPa (offset +101). Check sensor calibration and telemetry basis.',
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
                'Integral action can compensate persistent error but is affected by output saturation and mode changes. MCU MAP ADC is updated at about 100 Hz; PID Compute() is independently rate-limited by pid.frequency_hz and runs only in the active feedback branch.',
                'Derivative action responds to measured pressure change and can be sensitive to sensor noise and filtering.',
                'Within a configured deadband the firmware skips normal PID recalculation, keeps the bounded output and synchronizes the previous MAP input; this is not proof that the boost output is off.',
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
                'An A/C request can activate fan operation independently of the normal temperature threshold; in PWM mode speed-based inhibition may take priority over an A/C-only demand, but not over a high-temperature/fault/override demand.',
                'A temporary manual request, ignition self-test or fault-related condition can override normal temperature behaviour. With a failed selected temperature sensor, protection alternates roughly 120 s on and 60 s off rather than simply commanding a permanent full-on state.',
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
    private static function logic(): array
    {
        return [
            'summary' => 'PG0 and PG1 each evaluate A, A AND B, or A OR B at about 25 Hz. Their on delay, off delay, finite on-time and repeat mode have distinct roles; a saved configuration is not proof that an output actually switched.',
            'comparisons' => [
                'function_codes' => [0 => 'A only', 1 => 'A AND B', 2 => 'A OR B'],
                'operators' => [0 => 'strictly below (<)', 1 => 'strictly above (>)'],
                'equal_values' => 'Equality does not satisfy either comparator.',
                'timing_basis' => 'Automatic evaluations occur approximately every 40 ms (25 Hz). On-delay and on-limit values count these evaluations, not a separate hardware timer.',
            ],
            'on_delay' => [
                'meaning' => 'The combined comparison must remain true for the configured number of successful 40 ms evaluations before the output can be switched on.',
                'reset' => 'A failed automatic comparison resets the on-delay and run-time counters. Manual override or disabled automatic processing also resets them.',
                'zero' => 'Zero permits activation on the next eligible automatic evaluation, subject to other gates.',
            ],
            'off_delay' => [
                'meaning' => 'When the combined comparison fails, output-off becomes pending. If the output is HIGH, it remains HIGH until the configured number of seconds has elapsed since the last eligible evaluation.',
                'pending' => 'While off is pending the MCU returns before evaluating A/B again: recovery of the comparison during this delay does NOT cancel the pending switch-off. After switch-off, any new activation must pass the on delay again.',
                'zero' => 'Zero means switch-off on the next PG evaluation after the failing comparison, rather than an instantaneous action inside that comparison.',
                'already_off' => 'If the output is already LOW, the pending-off flag is cleared without waiting out the off delay.',
            ],
            'on_limit' => [
                'meaning' => 'When repeat is OFF, a nonzero on-limit counts time after the on delay has been reached. At the limit, the output is set LOW and stays off while the comparison remains continuously true.',
                'reset' => 'The run-time counter is reset when the automatic comparison fails or auto processing is bypassed. Zero disables the on-time limit.',
            ],
            'repeat' => [
                'meaning' => 'When repeat is ON, the on-time limit is ignored. Each successful activation starts a pending off phase; the output cycles through the configured off delay and on delay while the comparison remains true.',
                'edge_case' => 'An off delay of zero may produce a very short HIGH pulse (approximately one evaluation tick), even while the condition stays true.',
            ],
            'priority_and_gates' => [
                'The optional engine-running gate prevents automatic evaluation during the first approximately five seconds after start; it can force an active output LOW immediately after engine stop, without respecting the configured off delay.',
                'Manual override has priority over automatic comparison evaluation, including the engine-running gate. A timed manual request uses its own timer, separate from the programmable on/off delays.',
                'Disabled automatic processing does not evaluate A/B and resets the automatic on/run counters.',
            ],
            'relevant_settings' => [
                'logic.pg0_enabled', 'logic.pg1_enabled',
                'logic.pg0_function_code', 'logic.pg1_function_code',
                'logic.pg0_on_delay_seconds', 'logic.pg1_on_delay_seconds',
                'logic.pg0_off_delay', 'logic.pg1_off_delay',
                'logic.pg0_on_limit_seconds', 'logic.pg1_on_limit_seconds',
                'logic.pg0_repeat', 'logic.pg1_repeat',
                'logic.pg0_engine_running_only', 'logic.pg1_engine_running_only',
            ],
            'diagnostic_guidance' => [
                'First read get_remote_settings(group=logic), then compare both channel conditions and timing parameters with get_remote_behavior(topic=logic).',
                'Separate automatic, repeating, engine-gated and manual modes; do not treat off delay as a cancellable debounce or run limit as an off delay.',
                'Telemetered PG state may be sampled more slowly than the approximately 25 Hz logic evaluation; short pulses can be missed.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function protection(): array
    {
        return [
            'summary' => 'Configured engine and sensor protection conditions can override normal boost operation; recorded settings do not prove that a fault occurred.',
            'behavior' => [
                'Maximum temperature limits and other fault triggers may set Rollback warning bits; the three configured MINIMUM temperatures are instead a combined AND prerequisite for normal boost start, not three independent Rollback alarms.',
                'Warning processing begins only after roughly 10 s of MCU uptime. Rollback is checked at about 1 Hz, and different warning types have different persistence/debounce logic; a brief event can leave a warning bit active temporarily.',
                'Protective reduction can persist while relevant conditions remain active; later recovery depends on firmware state and input validity.',
                'Oil/fuel pressure warning checks require a bound analog sensor. Their saved enable flags gate just the high-RPM low-pressure subconditions; additional engine-running/low-pressure and idle plausibility subconditions operate even when those flags are off. Knock requires the Toyota ECU knock flag.',
                'Protection thresholds are safety limits, not performance targets.',
            ],
            'rollback_code_format' => 'Unsigned 16-bit bitmask: multiple flags may be set at once. A larger numeric value is not a severity ranking; decoded bits identify reported warning classes, not proven root causes.',
            'rollback_flags' => [
                0 => 'Knock warning (ECU detection when enabled)',
                1 => 'EGT high or invalid after running-time check',
                2 => 'Engine oil pressure warning (requires configured sensor; engine/RPM dependent)',
                3 => 'Fuel pressure warning (requires configured sensor; engine/RPM dependent)',
                4 => 'Engine oil temperature high or sensor fault',
                5 => 'Coolant temperature high or sensor/ECU fault',
                6 => 'Overboost / MAP above configured limit',
                7 => 'AFR condition during boost or AFR sensor fault',
                8 => 'Intake air temperature high or sensor fault',
                9 => 'MAP plausibility warning',
                10 => 'Selected fan temperature source fault',
                11 => 'Transmission temperature high or sensor fault',
                12 => 'Ambient temperature high or sensor fault',
                13 => 'External temperature high or sensor fault',
                14 => 'Supply voltage outside monitored limits',
                15 => 'Engine-stop/run-state-loss warning (does not distinguish an intentional stop from a signal fault)',
            ],
            'rollback_reporting_limit' => 'The EEPROM stores the last nonzero changed Rollback bitmask separately from the current mask. Warning counters, sensor dependencies and persistence vary by flag; bit 15 may follow ordinary engine stop as well as signal loss.',
            'relevant_settings' => [
                'protection.boost_limit_kpa', 'protection.max_coolant_c', 'protection.max_oil_c',
                'protection.max_egt_c', 'protection.max_intake_c',
                'protection.min_coolant_c', 'protection.min_oil_c', 'protection.min_transmission_c',
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

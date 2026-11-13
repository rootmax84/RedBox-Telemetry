<?php
declare(strict_types=1);

/**
 * Public functional architecture of the RedManage MCU family.
 *
 * This is a curated diagnostic reference, NOT a firmware port. No MCU files,
 * protocol frames, proprietary equations, internal state machine or memory
 * layout belong here. Each description may differ from installed firmware.
 */
final class McpRemoteArchitecture
{
    public const COMPONENTS = [
        'system' => 'System layers and signal flow',
        'scheduling' => 'Independent acquisition, decision and reporting cadences',
        'ecu' => 'Engine ECU protocol and derived engine signals',
        'analog' => 'Analog input sensors, calibration and filtering',
        'digital_temperature' => 'DS temperature inputs and sensor validity',
        'rpm' => 'RPM sources and engine-running state',
        'gear' => 'Gear and BSx input modes',
        'boost' => 'Boost solenoid control and selected setpoints',
        'pid' => 'Pressure-feedback regulation',
        'fan' => 'Switch/PWM fan control and overrides',
        'protection' => 'Protective warnings, reductions and sensor faults',
        'programmable_outputs' => 'PG0/PG1 programmable power outputs',
        'pim' => 'PIM signal generation for external consumers',
        'dashboard' => 'Diagnostic/dashboard data presentation',
        'communications' => 'MCU serial communication and command surface',
        'persistence' => 'On-device settings, calibration and stored history',
        'engine_hours' => 'Engine runtime and engine-hours accounting',
        'observability' => 'Meaning, provenance and limits of telemetry',
    ];

    /** @return array<string, mixed> */
    public static function describe(?string $component = null): array
    {
        if ($component !== null && !array_key_exists($component, self::COMPONENTS)) {
            throw new InvalidArgumentException('Unknown RedManage architecture component');
        }

        if ($component === null) {
            return [
                'name' => 'RedManage MCU functional architecture',
                'components' => self::COMPONENTS,
                'signal_flow' => [
                    'ECU, analog, tachometer and digital temperature inputs provide signals with different origins and freshness.',
                    'Sensor assignment and calibration determine which physical measurement a logical signal represents.',
                    'Shared signals feed boost regulation, protection, fan decisions, programmable outputs and PIM generation.',
                    'MCU states and derived readings may be reported to a dashboard or logging application over a serial interface.',
                    'RedManage saved configuration, device runtime state and uploaded RedBox Telemetry samples are three distinct evidence sources.',
                ],
                'how_to_use' => [
                    'Read the relevant component before interpreting readings or explaining a device symptom.',
                    'Use get_remote_schema or get_remote_settings for named settings and engineering units.',
                    'Use get_remote_behavior for boost, pid, fan, protection and programmable-output (logic) semantics.',
                    'Use list_metrics and recorded/live telemetry to establish which observations are actually available.',
                    'Do not infer a specific active MCU state, warning cause or actuator motion from configuration alone.',
                ],
                'read_only' => true,
                'source_confidence' => self::confidence(),
                'disclosure' => self::disclosure(),
            ];
        }

        $details = self::component($component);
        return [
            'component' => $component,
            'title' => self::COMPONENTS[$component],
            'purpose' => $details['purpose'],
            'inputs' => $details['inputs'],
            'outputs' => $details['outputs'],
            'behaviors' => $details['behaviors'],
            'diagnostic_checks' => $details['checks'],
            'limitations' => $details['limitations'],
            'related_components' => $details['related'],
            'settings_groups' => $details['groups'],
            'related_setting_keys' => $details['keys'],
            'read_only' => true,
            'source_confidence' => self::confidence(),
            'disclosure' => self::disclosure(),
        ];
    }

    private static function confidence(): string
    {
        return 'Functional relationships reviewed against the supplied MCU source family; the running device firmware and wiring are not verified.';
    }

    private static function disclosure(): string
    {
        return 'Public diagnostic semantics only: no MCU source, control equations, protocol framing, memory map, or internal execution logic.';
    }

    /** @return array<string, mixed> */
    private static function component(string $component): array
    {
        return match ($component) {
            'system' => self::entry(
                'Connects input acquisition, normalized sensor state, controllers, outputs and reporting.',
                ['Vehicle ECU', 'Analog/temperature inputs', 'RPM and gear signals', 'Saved user configuration'],
                ['Boost/fan/PG/PIM actuation requests', 'Warnings', 'Dashboard and telemetry values'],
                ['Different subsystems consume the same logical temperature, pressure, RPM and engine-run signals.',
                    'Sensor origin and assignment can change without a displayed metric name changing.',
                    'A stored setting does not establish that its control path is currently active.'],
                ['Identify the signal source, saved input assignment, operating mode and sensor validity before attributing a symptom to one subsystem.'],
                ['This is an architectural map, not a complete runtime execution trace.'],
                ['scheduling', 'ecu', 'analog', 'digital_temperature', 'boost', 'fan', 'protection', 'dashboard', 'observability'],
                ['inputs', 'boost', 'fan', 'logic', 'other'], []),
            'scheduling' => self::entry(
                'Separates periodic sensor acquisition and slower protection/temperature decisions from feedback regulation and communications.',
                ['Elapsed runtime', 'Sensor scheduling', 'Controller operating modes'],
                ['Measurements with different update ages', 'Periodic decisions', 'Serial responses'],
                ['The 10 ms scheduler acquires MAP and AFR at approximately 100 Hz; oil/fuel pressure, PG comparisons and RPM at 25 Hz; vehicle voltage, BSx and some PIM modes at 5 Hz; EGT at 4 Hz; fan and DS-conversion starts at 2 Hz; analog temperatures, engine-runtime accounting and Rollback checks at 1 Hz.',
                    'The feedback regulator applies its own configured calculation cadence.',
                    'DS temperature sensors share one non-blocking broadcast conversion, followed by ROM-addressed sequential reads; the 9-bit devices usually need roughly 100 ms, while DS18S20 can require roughly 750 ms.',
                    'UART requests and main-loop activity have different timing from recorded telemetry.'],
                ['Check logging resolution and timestamp alignment before comparing response delays or claiming a control action was late.'],
                ['Do not treat a chart sample interval as an MCU control period or an output PWM frequency.'],
                ['analog', 'digital_temperature', 'pid', 'fan', 'protection', 'observability'],
                ['pid', 'fan'], ['pid.frequency_hz', 'fan.pwm_frequency_code']),
            'ecu' => self::entry(
                'Decodes supported Toyota ECU data and flags that other subsystems can use.',
                ['ECU data link and its availability'],
                ['Engine speed, coolant temperature, speed, throttle and other diagnostic/derived signals', 'Knock, A/C, starter and gear-related flags'],
                ['An ECU-sourced signal and a locally measured auxiliary signal are not interchangeable unless their assignments and units agree.',
                    'Some displayed readings are computed from ECU protocol fields rather than measured by an independent RedManage sensor.',
                    'ECU availability and selected protocol/tachometer mode affect which data can be trusted.'],
                ['Check ECU protocol availability and selected RPM/MAP source before diagnosing missing or inconsistent readings.'],
                ['A reported ECU field is not proof that the vehicle supports or correctly publishes that metric.'],
                ['rpm', 'gear', 'analog', 'protection', 'dashboard', 'observability'],
                ['inputs', 'other', 'boost'], ['inputs.vfd_mode_code', 'boost.map_mode']),
            'analog' => self::entry(
                'Acquires multiplexed AUX measurements and dedicated voltage/EGT signals.',
                ['AUX0–AUX4 assignments', 'MAP/AFR/pressure sensor wiring', 'Stored calibration', 'Vehicle supply voltage'],
                ['Pressure, AFR and selected temperature signals', 'Voltage and EGT observations'],
                ['AUX channels can be assigned to different logical sensors; one physical channel cannot safely be assumed to represent several sensors.',
                    'MAP selection can use ECU or external sensor input; external sensor scaling depends on its mode and calibration.',
                    'Pressure sensor type, AFR calibration and digital filtering affect engineering values and response.',
                    'Temperature inputs can use calibration tables and pull-ups; converted temperature is not a raw ADC measurement.',
                    'EGT and other channels have independent filtering and acquisition timing.'],
                ['Verify AUX binding, sensor range, calibration, units and whether each sample is fresh and plausible.'],
                ['A saved calibration does not prove physical wiring, electrical health or sensor accuracy.'],
                ['ecu', 'digital_temperature', 'boost', 'protection', 'observability'],
                ['inputs', 'calibration', 'boost', 'other'],
                ['boost.map_mode', 'boost.map_filter', 'calibration.afr_0v', 'calibration.afr_5v', 'calibration.oil_pressure_sensor_code', 'calibration.fuel_pressure_sensor_code']),
            'digital_temperature' => self::entry(
                'Acquires assigned OneWire temperature sensors and tracks read validity.',
                ['Configured DS sensor addresses', 'Logical temperature assignment', 'Bus/sensor health'],
                ['ECT/EOT/IAT/ATF/AAT/EXT readings where assigned', 'DS availability/error indicators'],
                ['Up to five DS identities can be assigned to supported logical temperature roles.',
                    'A single asynchronous broadcast conversion is shared by all registered sensors, then each bound ROM is read separately; there is no new conversion before each sensor read.',
                    'Read failures are debounced (up to five consecutive failures) before temperature error state changes; stale measurements cannot be treated as fresh live samples.',
                    'A sensor fault or unassigned input may affect fan and protection interpretation.',
                    'A reported error/status is not an independent proof of actual engine temperature.'],
                ['Compare DS assignment and status with alternative temperature sources; do not interpret a fault sentinel as a real temperature.'],
                ['ds_avail is the number of stored DS ROM addresses in EEPROM, NOT the number currently online. Registered but unbound DS slots are not read; DS read status and assignments need separate interpretation. Multiple AUX/DS sources bound to one temperature overwrite the same MCU variable on different schedules.'],
                ['analog', 'fan', 'protection', 'observability'],
                ['inputs'], ['inputs.ds0_assignment', 'inputs.ds0_status', 'inputs.ds_registered_count']),
            'rpm' => self::entry(
                'Selects RPM from ECU or supported external tachometer sources and helps classify engine-running state.',
                ['Selected tachometer mode', 'ECU speed or pulse input', 'RPM multiplier'],
                ['Engine RPM', 'Engine-running indication used by dependent controllers'],
                ['RPM can originate from ECU data or external digital/analog tachometer processing.',
                    'Engine-running state is evaluated at about 1 Hz: RPM >= 300 sets running, RPM == 0 clears it, and 1–299 RPM retains the previous state. External tachometer absence for >200 ms produces a filtered RPM decay rather than instantly setting the reported value to zero.',
                    'The selected VF code determines ECU-only, external pulse RPM or mixed ECU+analog tachometer modes; mixed codes 21/31 use BS1/A5 for analog tachometer input and make normal gear decoding unavailable.'],
                ['Check RPM provenance, continuity, selected mode and multiplier before using engine-run-dependent diagnoses.'],
                ['No RPM samples or intermittent zero RPM do not independently prove the engine was stopped.'],
                ['ecu', 'gear', 'boost', 'fan', 'programmable_outputs', 'engine_hours'],
                ['inputs', 'other'], ['inputs.vfd_mode_code', 'other.rpm_multiplier']),
            'gear' => self::entry(
                'Interprets transmission/BSx inputs when available and supplies gear context to boost settings.',
                ['BSx input mode and levels', 'ECU park/neutral flag', 'Wiring and pull-up configuration'],
                ['Gear/unknown gear classification', 'Optional logic and A/C input signals'],
                ['BSx may operate as gear inputs or programmable logic signals, depending on configuration.',
                    'Gear-specific boost profiles require a valid gear indication and correct input mode.',
                    'Unknown/unavailable gear is encoded as 255 when BSx is in logic mode or the tachometer uses the shared BS1 input; 0 is the ECU neutral/park indication. Neither is a valid gear-specific boost profile, and an unknown gear must not be silently interpreted as first gear.'],
                ['Check input mode, park/neutral state and reported gear before comparing gear-specific targets.'],
                ['The inferred gear is not a direct measurement of mechanical gear engagement.'],
                ['ecu', 'rpm', 'boost', 'fan', 'programmable_outputs'],
                ['inputs', 'boost'], ['inputs.bsx_mode', 'inputs.bs1_pullup', 'inputs.bs2_pullup', 'boost.gear_enabled']),
            'boost' => self::entry(
                'Controls boost solenoid according to enabled state, valid pressure, selected target and permitted operating conditions.',
                ['MAP pressure', 'Configured targets and duty limits', 'RPM/gear context', 'ECT/EOT/ATF minimum temperatures', 'Protection status'],
                ['Solenoid command/status', 'Pressure-control response'],
                ['Normal boost eligibility requires coolant ECT AND oil EOT AND transmission ATF at/above their separate minimums, plus enabled boost, positive selected target and duty. Any 0 °C minimum disables just its own check.',
                    'Pressure setpoint, start threshold, anti-lag threshold and protection limit are displayed by RedManage as gauge kPa; the MCU compares absolute Input_MAP with each decoded UI value plus 101 kPa. Below the absolute start threshold, anti-lag can request full output without Rollback; above it PID requires nonzero Kp, otherwise duty is static.',
                    'Closed-loop regulation is only one operating regime; prerequisites and pressure thresholds change which regime can apply.',
                    'Gear profiles can select alternative boost targets and duty baselines when a valid gear signal is available.',
                    'RPM-based and fixed duty corrections have selection rules, not simply unconditional accumulation.',
                    'Protective conditions can restrict or override normal actuation.'],
                ['Compare pressure basis, MAP validity, true target selection, RPM/gear conditions, protection and observed duty in the same time window.'],
                ['Commanded solenoid duty is not achieved manifold pressure or direct wastegate-position feedback.'],
                ['analog', 'rpm', 'gear', 'pid', 'protection', 'observability'],
                ['boost', 'pid', 'protection', 'inputs'],
                ['boost.enabled', 'boost.map_mode', 'boost.target_kpa', 'boost.base_duty_percent', 'boost.gear_enabled', 'boost.start_kpa', 'boost.threshold_kpa',
                    'protection.min_coolant_c', 'protection.min_oil_c', 'protection.min_transmission_c']),
            'pid' => self::entry(
                'Provides periodic pressure feedback while respecting selected mode, output bounds and signal quality.',
                ['Measured MAP', 'Selected boost setpoint', 'Gain/mode settings', 'Sampling frequency', 'Controller output allowance'],
                ['Bounded control command during active feedback mode'],
                ['Proportional behavior may be error-based or measurement-based; gains have different effects and tuning implications.',
                    'Filtering and deadband can affect apparent sensitivity and transient behavior.',
                    'Controller constraints and mode changes can affect integral behavior and observable duty.',
                    'Regular application loop activity does not imply every iteration performs a PID calculation.'],
                ['Use time-aligned pressure, target and output signals and the configured sampling mode before attributing overshoot to gains.'],
                ['Without actual controller state or confirmed telemetry cadence, numeric gain prescriptions are not validated.'],
                ['boost', 'analog', 'scheduling', 'protection', 'observability'],
                ['pid', 'boost'], ['pid.mode', 'pid.kp', 'pid.ki', 'pid.kd', 'pid.deadband_kpa', 'pid.frequency_hz']),
            'fan' => self::entry(
                'Controls an on/off or PWM fan path using a selected temperature and conditional demands.',
                ['Selected temperature source', 'SW/PWM settings', 'A/C status', 'Engine-run status', 'Vehicle speed if available', 'Fault/override state'],
                ['Fan electrical command and reported status'],
                ['SW mode combines a temperature threshold with hysteresis and post-demand run-on behavior.',
                    'PWM mode uses a temperature region and minimum output setting with startup/override behavior.',
                    'An A/C request or sensor fault can affect output outside normal temperature demand. In PWM mode forced/fault/high-temperature demand is checked before the speed inhibit, while A/C-only demand is checked after it.',
                    'When start-by-engine status is selected, a startup gate may defer fan operation; after roughly two minutes of MCU uptime the firmware permits a fallback if RPM data has failed.',
                    'Speed-related inhibition is conditional and can have a different priority from forced or fault operation.',
                    'PWM signal frequency, fan decision rate and mechanical fan speed are distinct quantities.'],
                ['Review the selected temperature input, validity, mode, A/C/override/fault and speed gating before interpreting fan status.'],
                ['The end of the PWM temperature region does not prove 100% electrical duty or maximum motor RPM.'],
                ['digital_temperature', 'analog', 'ecu', 'rpm', 'scheduling', 'protection'],
                ['fan', 'inputs'], ['fan.mode', 'fan.sensor_source', 'fan.temperature_c', 'fan.pwm_width', 'fan.hysteresis', 'fan.ac_trigger_enabled']),
            'protection' => self::entry(
                'Monitors configured protection and sensor-plausibility conditions that can affect boost and fan behavior.',
                ['Engine temperature and pressure signals', 'AFR/EGT/knock', 'MAP and ECU flags', 'Voltage', 'Saved safety thresholds'],
                ['Current and last-warning indications where available', 'Possible boost reduction', 'Fault-related fan behavior'],
                ['Overboost, engine temperature, pressure, AFR and sensor-health conditions are distinct diagnostic categories.',
                    'Some fault paths depend on sensor binding or engine state; specifically the EOP/FP enable switches only gate the high-RPM low-pressure branch, while their other low-pressure and sensor-plausibility checks may still trigger Rollback when a sensor is bound.',
                    'Transient handling means an individual suspicious sample may not equal an active warning; protection starts checking after approximately 10 s of boot, and different causes have different debounce times.',
                    'The three minimum ECT/EOT/ATF settings are a combined boost-enable condition, not the same thing as independent high-temperature Rollback warnings.',
                    'Fan sensor failure can alternate roughly 120 s of fail-safe on and 60 s off, so a single fan-status sample may not convey the full fault state.',
                    'Rollback warning data is a 16-bit bitmask, not an ordinal severity score; simultaneous bits indicate different warning classes.',
                    'The current Rollback code is a 16-bit mask of warning states, while the last stored nonzero mask survives resets in EEPROM; neither a set bit nor the numerical magnitude establishes the root cause or fault severity.',
                    'Loss of a selected fan temperature source may cause a protective fan response.'],
                ['Correlate available warning/status metrics with relevant source readings; distinguish observed warnings from possible causes.'],
                ['Do not assume an active code from a configured threshold; do not suggest disabling protections for performance.'],
                ['boost', 'fan', 'analog', 'digital_temperature', 'ecu', 'rpm', 'observability'],
                ['protection', 'inputs', 'calibration'], ['protection.boost_limit_kpa', 'protection.max_coolant_c', 'protection.max_oil_c']),
            'programmable_outputs' => self::entry(
                'Provides two configurable power outputs driven by conditions on selected normalized signals.',
                ['PG0/PG1 selected signal variables', 'Comparison thresholds and logic mode', 'Timing settings', 'Engine-running gate', 'Manual override'],
                ['PG0 and PG1 output request/state'],
                ['Each PG channel compares one signal or combines two conditions with strict < or > operators. RLC is a numeric bitmask value: an ordinary >/< threshold comparison on RLC is not a selected warning-bit test.',
                    'Automatic PG conditions are checked about 25 times per second: on delay counts true evaluations, while off delay begins after a false evaluation.',
                    'While off delay is pending, A/B are not rechecked; recovering the condition does not cancel the already scheduled switch-off.',
                    'Finite on-time is counted after the on delay and only limits a non-repeating output; repeat mode instead cycles using the off and on delays.',
                    'When the engine-run gate is enabled, the MCU suppresses automatic activation during the first approximately 5 s of engine runtime and can turn an active output off after engine stop.',
                    'Manual override takes precedence over automatic condition evaluation, and its timer differs from PG on/off delays. Disabled auto mode is not evidence that a manual override is inactive.',
                    'The two channels are independently configured but can depend on the same source measurement.'],
                ['Use get_remote_settings(group=logic), then get_remote_behavior(topic=logic) for the exact on/off-delay, repeat and gating semantics. Check threshold units, manual state and sampling cadence.'],
                ['A satisfied comparison is not enough to prove that an output was energized at a sampled instant.'],
                ['analog', 'digital_temperature', 'rpm', 'ecu', 'observability'],
                ['logic'], ['logic.pg0_enabled', 'logic.pg1_enabled', 'logic.pg0_a_variable', 'logic.pg0_a_threshold']),
            'pim' => self::entry(
                'Generates a configured analog-like signal for a downstream external ECU or instrumentation input.',
                ['Selected PIM source', 'Sensor/ECU measurements', 'Output mode and baseline'],
                ['Generated PIM electrical signal'],
                ['The output may be static, MAP-related or derived from another chosen sensor, depending on configured mode.',
                    'PIM is generated PWM, not an independent sensor reading. The UI baseline 0–85 is internally stored with an offset of 100 and does not directly equal percentage physical PWM duty or measured voltage.',
                    'Source validity and calibration determine whether downstream interpretations are meaningful.'],
                ['Determine configured PIM mode and input source; compare against original source signal, not only downstream indication.'],
                ['The electrical signal is not proof that the receiving ECU interprets or scales it as expected.'],
                ['analog', 'ecu', 'dashboard', 'observability'],
                ['other', 'boost', 'inputs'], ['other.pim_mode', 'other.pim_output']),
            'dashboard' => self::entry(
                'Exposes available and derived engine/controller readings to compatible diagnostic dashboards.',
                ['Normalized signals', 'ECU protocol fields', 'MCU state and derived counters'],
                ['Diagnostic PID-style responses and displayed metrics, including supported controller status'],
                ['The MCU provides a compatibility-oriented dashboard interface; some values are converted or calculated.',
                    'The reviewed dashboard data family includes temperatures, pressures, RPM, vehicle data, boost command, fan status, gear, PG output status, voltage and warning codes. Boost duty telemetry reflects the stored Output variable (or -1 when disabled), not necessarily the brief PWM startup command sent to the pin.',
                    'A displayed PID name does not establish which physical sensor produced it.',
                    'Serial response cadence and external log cadence are separate from the internal sensor update cadence.'],
                ['Use list_metrics to identify exact logged PIDs, units and provenance before comparing readings.'],
                ['Availability in the MCU dashboard does not guarantee upload or storage by RedBox Telemetry.'],
                ['communications', 'ecu', 'analog', 'observability'],
                ['inputs', 'other'], []),
            'communications' => self::entry(
                'Handles the MCU serial interface used by diagnostic applications and device configuration.',
                ['UART requests', 'MCU state', 'Saved configuration'],
                ['Readings, acknowledgements and configured responses'],
                ['The firmware supports distinct diagnostic and management operations over the serial link.',
                    'Some management operations can change device settings, but they are deliberately not available as MCP actions.',
                    'A successful dashboard reading is not evidence that the latest server-side RedManage configuration has been applied to hardware.'],
                ['Compare server snapshot timestamp with actual device and uploaded telemetry if device state matters.'],
                ['MCP exposes neither MCU write commands nor the underlying serial protocol implementation.'],
                ['dashboard', 'persistence', 'observability'],
                ['other'], []),
            'persistence' => self::entry(
                'Stores configuration, sensor assignments, calibration, and selected service/warning history on the device.',
                ['RedManage settings changes', 'Sensor identity/calibration', 'Runtime counters and warning state'],
                ['Persistent configuration and historical device metadata'],
                ['Settings may need explicit application/reinitialization or a device restart to affect particular hardware behavior.',
                    'Stored settings and runtime status have different persistence and freshness.',
                    'Some service counters are managed to limit persistent-memory wear.'],
                ['Check whether settings were applied and when the device last synchronized; use readings to confirm resulting behavior.'],
                ['RedBox database snapshots are not live EEPROM dumps and cannot establish current MCU firmware revision.'],
                ['communications', 'engine_hours', 'observability', 'analog', 'fan', 'boost'],
                ['calibration', 'inputs', 'other'], ['other.motor_hours_mode']),
            'engine_hours' => self::entry(
                'Tracks elapsed engine operation and an engine-hours counter with selectable accounting behavior.',
                ['Derived engine-running state', 'RPM', 'Counter mode'],
                ['Engine runtime information', 'Accumulated engine-hours counter'],
                ['Simple mode counts one motor-hours minute per 60 s of engine run; advanced mode counts a minute every approximately 20–60 s based on RPM (not throttle load). Writing the EEPROM wear-levelled current counter is deferred until low RPM (<1250).',
                    'The EEPROM bytes 396–399 store history accumulated after earlier counter resets; they are not the current wear-levelled counter stored in EEPROM slots 400 onward. Current runtime and current motor-hours value are reported separately in dashboard/telemetry.',
                    'Engine-running classification turns on at RPM >= 300 and off at RPM == 0; intermediate values retain the previous state.',
                    'Engine-running inference, counter update timing and persistence can differ from logged sample cadence.'],
                ['Check counter mode and reliable RPM/engine-run history before comparing hours with clock time.'],
                ['An engine-hours value is not necessarily equal to wall-clock hours with ignition on.'],
                ['rpm', 'persistence', 'dashboard'],
                ['other'], ['other.motor_hours_mode', 'other.motor_hours_history']),
            'observability' => self::entry(
                'Classifies which claims are supported by saved settings, instantaneous MCU values or recorded samples.',
                ['Server-stored settings', 'Device/ECU signals', 'Telemetry session uploads'],
                ['Evidence-based interpretation with explicit uncertainty'],
                ['Settings values describe intent or configuration; they are not proof that an output was active.',
                    'Some readings are inferred, filtered, converted, generated or sampled from a shared source.',
                    'Protective states, gear selection, fan commands and physical outcomes may have different observability.',
                    'Missing telemetry metrics must be reported as missing rather than reconstructed from firmware descriptions.',
                    'A real-world device may run a different firmware revision or have a different board wiring.'],
                ['For each assertion state the source, unit, time range, and whether it is measured, derived, commanded or inferred.'],
                ['Do not present model-based explanations as firmware-version-verified or hardware-validated facts.'],
                ['system', 'scheduling', 'dashboard', 'persistence', 'protection'],
                [], []),
        };
    }

    /** @return array<string, mixed> */
    private static function entry(
        string $purpose,
        array $inputs,
        array $outputs,
        array $behaviors,
        array $checks,
        array $limitations,
        array $related,
        array $groups,
        array $keys
    ): array {
        return compact('purpose', 'inputs', 'outputs', 'behaviors', 'checks', 'limitations', 'related', 'groups', 'keys');
    }
}

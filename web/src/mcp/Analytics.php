<?php
declare(strict_types=1);
require_once __DIR__ . '/Sampling.php';

/** Read-only analytics that retain true session timestamps, independent of chart stitching. */
final class McpAnalytics
{
    /** Single pass over logs with bounded result size and constant-size cadence history. */
    public static function segments(McpData $data, array $args): array
    {
        [$session, $start, $end] = $data->bounds($args);
        $limit = $data->intParam($args, 'max_segments', 100, 1, 200);
        $pid = isset($args['pid']) ? $data->metric($args) : null;
        $sampling = new McpSampling();
        $segments = []; $count = 0; $gaps = []; $gapCount = 0;
        $segment = null; $first = null; $last = null; $valid = 0; $missing = 0; $covered = 0;
        $pendingMissing = false;
        $scan = $data->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use ($pid, $sampling, $limit,
                &$segments, &$count, &$gaps, &$gapCount, &$segment, &$first, &$last, &$valid, &$missing, &$covered, &$pendingMissing): void {
                if ($pid !== null && McpData::numeric($row, $pid['id']) === null) {
                    ++$missing;
                    $pendingMissing = true;
                    return;
                }
                if ($pendingMissing && $segment !== null) {
                    ++$count;
                    if (count($segments) < $limit) $segments[] = $segment;
                    $segment = null;
                }
                $pendingMissing = false;
                $info = $sampling->observe($time);
                $first ??= $time;
                $last = $time;
                ++$valid;
                if ($segment !== null && $info['gap']) {
                    ++$count;
                    if (count($segments) < $limit) $segments[] = $segment;
                    ++$gapCount;
                    if (count($gaps) < $limit) $gaps[] = [
                        'from_ms' => $segment['end_ms'], 'to_ms' => $time,
                        'duration_ms' => $info['delta_ms'], 'adaptive_threshold_ms' => $info['threshold_ms']];
                    $segment = null;
                } elseif ($segment !== null && $info['delta_ms'] !== null && $info['delta_ms'] > 0) {
                    $covered += $info['delta_ms'];
                }
                if ($segment === null) $segment = ['start_ms' => $time, 'end_ms' => $time, 'samples' => 0];
                $segment['end_ms'] = $time;
                ++$segment['samples'];
            }, McpData::fullScan($args));
        if ($segment !== null) {
            ++$count;
            if (count($segments) < $limit) $segments[] = $segment;
        }
        $window = $first !== null && $last !== null ? max(0, $last - $first) : 0;
        return [
            'session_id' => $session['session_id'], 'pid' => $pid,
            'sample_count' => $valid, 'missing_samples' => $missing,
            'first_ms' => $first, 'last_ms' => $last,
            'segment_count' => $count, 'gap_count' => $gapCount,
            'segments' => $segments, 'gaps' => $gaps,
            'results_truncated' => $count > $limit || $gapCount > $limit,
            'observed_coverage_ms' => $covered, 'coverage_window_ms' => $window,
            'coverage_ratio' => $window > 0 ? round(min(1, $covered / $window), 6) : null,
            'sampling' => $sampling->summary(),
            'incomplete' => $scan['scan_truncated'],
            'caution' => $scan['scan_truncated'] ? 'Incomplete scan; segment and coverage statistics are partial' :
                'Gaps are inferred from observed cadence. No values are interpolated across gaps.',
        ] + $scan;
    }

    public static function quality(McpData $data, array $args): array
    {
        $result = self::segments($data, $args);
        // The same one-pass computation feeds both quality and segment tools.
        return ['quality_status' => $result['incomplete'] ? 'incomplete' :
                (($result['sample_count'] < 3) ? 'insufficient_samples' : 'evaluated'),
                'time_basis' => 'original_database_timestamps_ms',
                'sampling_note' => 'Coverage is measured between first and last observations; it does not assess time before first or after last sample']
            + $result;
    }

    public static function relationship(McpData $data, array $args): array
    {
        $a = $data->metric(['pid' => $args['pid_a'] ?? null]);
        $b = $data->metric(['pid' => $args['pid_b'] ?? null]);
        if ($a['id'] === $b['id']) throw new InvalidArgumentException('Choose two distinct PIDs');
        [$session, $start, $end] = $data->bounds($args);
        $maxLag = $data->intParam($args, 'max_time_delta_ms', 0, 0, 60000);
        $sampling = new McpSampling();
        $lastA = null; $lastB = null;
        $n = 0; $meanA = 0.0; $meanB = 0.0; $m2a = 0.0; $m2b = 0.0; $cov = 0.0;
        $maxObservedLag = 0;
        $scan = $data->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use ($a, $b, $sampling, $maxLag,
                &$lastA, &$lastB, &$n, &$meanA, &$meanB, &$m2a, &$m2b, &$cov, &$maxObservedLag): void {
                $status = $sampling->observe($time);
                if ($status['gap']) { $lastA = null; $lastB = null; }
                $va = McpData::numeric($row, $a['id']);
                $vb = McpData::numeric($row, $b['id']);
                if (array_key_exists($a['id'], $row)) $lastA = $va === null ? null : [$time, $va];
                if (array_key_exists($b['id'], $row)) $lastB = $vb === null ? null : [$time, $vb];
                if (($va === null && $vb === null) || $lastA === null || $lastB === null) return;
                $lag = abs($lastA[0] - $lastB[0]);
                $allowed = $maxLag ?: max(200, min(60000, 2 * max(100, (int)($sampling->typicalIntervalMs() ?? 1000))));
                if ($lag > $allowed) return;
                $maxObservedLag = max($maxObservedLag, $lag);
                ++$n;
                $da = $lastA[1] - $meanA;
                $meanA += $da / $n;
                $db = $lastB[1] - $meanB;
                $meanB += $db / $n;
                $m2a += $da * ($lastA[1] - $meanA);
                $m2b += $db * ($lastB[1] - $meanB);
                $cov += $da * ($lastB[1] - $meanB);
            }, McpData::fullScan($args));
        $correlation = $n >= 3 && $m2a > 0 && $m2b > 0 ? $cov / sqrt($m2a * $m2b) : null;
        return ['session_id' => $session['session_id'], 'pid_a' => $a, 'pid_b' => $b,
            'paired_samples' => $n, 'mean_a' => $n ? round($meanA, 6) : null,
            'mean_b' => $n ? round($meanB, 6) : null,
            'pearson_r' => $correlation !== null ? round(max(-1, min(1, $correlation)), 6) : null,
            'max_observed_timestamp_difference_ms' => $maxObservedLag,
            'max_allowed_timestamp_difference_ms' => $maxLag ?: 'adaptive',
            'sampling' => $sampling->summary(), 'incomplete' => $scan['scan_truncated'],
            'caution' => 'Correlation describes association, not causation; paired values are nearest recent observations without interpolation across detected gaps.'] + $scan;
    }

    public static function rollback(McpData $data, array $args): array
    {
        $limit = $data->intParam($args, 'max_events', 20, 1, 50);
        $window = $data->intParam($args, 'window_ms', 10000, 0, 60000);
        $contextIds = $args['context_pids'] ?? [];
        if (!is_array($contextIds) || count($contextIds) > 4 || count($contextIds) !== count(array_unique($contextIds))) {
            throw new InvalidArgumentException('Provide up to four distinct context PIDs');
        }
        $contextPids = [];
        foreach ($contextIds as $id) {
            if (!is_string($id) || $id === 'k21fa') throw new InvalidArgumentException('Invalid context PID');
            $contextPids[$id] = $data->metric(['pid' => $id]);
        }
        $event = $data->events(array_merge($args, ['pid' => 'k21fa', 'operator' => 'gt', 'threshold' => 0,
            'max_events' => $limit]));
        $event['event_type'] = 'rollback_positive_code';
        $event['context_window_ms'] = $window;
        $event['context_pids'] = array_values($contextPids);
        $event['caution'] = 'A positive Rollback PID reading is observed state, not a confirmed root cause. Missing samples and gaps do not establish continuity.';
        if ($contextPids === [] || $event['events'] === []) return $event;

        [$session, $start, $end] = $data->bounds($args);
        $details = [];
        foreach ($event['events'] as $i => $e) {
            $details[$i] = ['event_start_ms' => $e['start_ms'], 'event_end_ms' => $e['end_ms'], 'metrics' => []];
            foreach ($contextPids as $pid => $meta) {
                $details[$i]['metrics'][$pid] = [
                    'pid' => $meta,
                    'before' => ['count' => 0, 'mean' => null],
                    'during' => ['count' => 0, 'mean' => null],
                    'after' => ['count' => 0, 'mean' => null]];
            }
        }
        // At most 50 events x 4 metrics; aggregate in a single bounded log scan.
        $contextScan = $data->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use (&$details, $window, $contextPids): void {
                foreach ($details as &$detail) {
                    $start = $detail['event_start_ms'];
                    $end = $detail['event_end_ms'];
                    if ($time < $start - $window || $time > $end + $window) continue;
                    $phase = $time < $start ? 'before' : ($time <= $end ? 'during' : 'after');
                    foreach ($contextPids as $id => $_meta) {
                        $value = McpData::numeric($row, $id);
                        if ($value === null) continue;
                        $metric = &$detail['metrics'][$id][$phase];
                        $metric['count']++;
                        $metric['mean'] = ($metric['mean'] ?? 0.0) + ($value - ($metric['mean'] ?? 0.0)) / $metric['count'];
                        unset($metric);
                    }
                }
                unset($detail);
            }, McpData::fullScan($args));
        $event['context'] = array_values($details);
        $event['context_scan_truncated'] = $contextScan['scan_truncated'];
        $event['incomplete'] = $event['incomplete'] || $contextScan['scan_truncated'];
        return $event;
    }

    public static function operatingConditions(McpData $data, array $args): array
    {
        $ids = $args['session_ids'] ?? null;
        if (!is_array($ids) || count($ids) < 2 || count($ids) > 5 || count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('Provide 2–5 distinct session IDs');
        }
        $rpm = $data->metric(['pid' => $args['rpm_pid'] ?? null]);
        $target = $data->metric(['pid' => $args['pid'] ?? null]);
        $center = $data->intParam($args, 'center_rpm', 3000, 0, 15000);
        $width = $data->intParam($args, 'rpm_window', 250, 10, 5000);
        $out = [];
        foreach ($ids as $sid) {
            [$session, $start, $end] = $data->bounds(['session_id' => $sid]);
            $agg = new McpMetricAggregate($target);
            $scan = $data->scan((int)$session['session_id'], $start, $end,
                static function (int $time, array $row) use ($agg, $rpm, $target, $center, $width): void {
                    $speed = McpData::numeric($row, $rpm['id']);
                    $v = McpData::numeric($row, $target['id']);
                    $agg->observe($time, $speed !== null && abs($speed - $center) <= $width ? $v : null);
                }, McpData::fullScan($args));
            $out[] = $agg->result($session['session_id'], $scan, $start, $end);
        }
        return ['pid' => $target, 'rpm_pid' => $rpm, 'center_rpm' => $center,
            'rpm_window' => $width, 'comparison' => $out,
            'incomplete' => (bool)array_filter($out, static fn($r) => $r['incomplete']),
            'caution' => 'Matching engine-speed ranges does not guarantee identical throttle, load, road grade, ambient conditions or sensor calibration.'];
    }
}

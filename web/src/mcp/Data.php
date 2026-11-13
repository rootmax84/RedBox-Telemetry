<?php
declare(strict_types=1);
require_once __DIR__ . '/Sampling.php';

/** All data queries are scoped to the token owner's user_id; never trust client uid. */
final class McpData
{
    public const GPS_PIDS = ['kff1005', 'kff1006', 'kff1007'];
    public const MAX_SCANNED_ROWS = 120000;
    public const MAX_FULL_SCAN_ROWS = 250000;
    public const SCAN_CHUNK = 10000;

    public function __construct(private readonly mysqli $db, private readonly array $auth)
    {
    }

    public function uid(): int
    {
        return $this->auth['user_id'];
    }

    public function intParam(array $args, string $key, int $default, int $min, int $max): int
    {
        $value = $args[$key] ?? $default;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException("{$key} must be an integer");
        }
        $value = (int)$value;
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("{$key} out of range");
        }
        return $value;
    }

    public function sessionId(array $args): int
    {
        $id = $args['session_id'] ?? null;
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            throw new InvalidArgumentException('session_id must be an integer');
        }
        if ((float)$id <= 0 || (float)$id > PHP_INT_MAX) {
            throw new InvalidArgumentException('Invalid session_id');
        }
        return (int)$id;
    }

    public function listSessions(array $args): array
    {
        $limit = $this->intParam($args, 'limit', 25, 1, 100);
        $offset = $this->intParam($args, 'offset', 0, 0, 10000);
        $conditions = ['user_id=?'];
        $params = [$this->uid()];
        foreach (['from_ms' => 'time >= ?', 'to_ms' => 'time <= ?'] as $key => $fragment) {
            if (isset($args[$key])) {
                $n = $this->intParam($args, $key, 0, 1, PHP_INT_MAX);
                $conditions[] = $fragment;
                $params[] = $n;
            }
        }
        if (isset($args['favorites_only']) && $args['favorites_only'] === true) {
            $conditions[] = 'favorite=1';
        }
        $where = implode(' AND ', $conditions);
        $params[] = $limit;
        $params[] = $offset;
        $result = $this->db->execute_query(
            "SELECT session, id, profileName, description, time, timeend, sessionsize, favorite "
            . "FROM sessions WHERE {$where} ORDER BY session DESC LIMIT ? OFFSET ?", $params
        );
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'session_id' => (string)$row['session'], 'source' => $row['id'],
                'profile' => $row['profileName'], 'description' => $row['description'],
                'start_ms' => (int)$row['time'], 'end_ms' => (int)$row['timeend'],
                'records' => (int)$row['sessionsize'], 'favorite' => (bool)$row['favorite'],
            ];
        }
        return ['sessions' => $rows, 'limit' => $limit, 'offset' => $offset, 'has_more' => count($rows) === $limit];
    }

    public function session(array $args): array
    {
        $sid = $this->sessionId($args);
        $row = $this->db->execute_query(
            'SELECT session, id, profileName, description, time, timeend, sessionsize, favorite '
            . 'FROM sessions WHERE user_id=? AND session=?', [$this->uid(), $sid]
        )->fetch_assoc();
        if (!$row) {
            throw new InvalidArgumentException('Session not found');
        }
        return [
            'session_id' => (string)$row['session'], 'source' => $row['id'],
            'profile' => $row['profileName'], 'description' => $row['description'],
            'start_ms' => (int)$row['time'], 'end_ms' => (int)$row['timeend'],
            'records' => (int)$row['sessionsize'], 'favorite' => (bool)$row['favorite'],
        ];
    }

    public function listMetrics(array $args): array
    {
        $uid = $this->uid();
        $result = $this->db->execute_query(
            'SELECT id, description, units, populated, stream, favorite FROM pids WHERE user_id=? ORDER BY description, id',
            [$uid]
        );
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            if (in_array($row['id'], self::GPS_PIDS, true) && !McpSecurity::can($this->auth, ['location:read'])) {
                continue;
            }
            $rows[] = [
                'id' => $row['id'], 'name' => $row['description'], 'unit' => $row['units'],
                'populated' => (bool)$row['populated'], 'live' => (bool)$row['stream'],
                'favorite' => (bool)$row['favorite'],
            ];
        }
        return ['metrics' => $rows, 'unit_system' => 'raw sensor units; user display conversions are not applied'];
    }

    public function metric(array $args): array
    {
        $pid = $args['pid'] ?? null;
        if (!is_string($pid) || !preg_match('/^[a-zA-Z0-9_]{1,16}$/D', $pid)) {
            throw new InvalidArgumentException('Invalid PID identifier');
        }
        if (in_array($pid, self::GPS_PIDS, true)) {
            McpSecurity::requireScopes($this->auth, ['location:read']);
        }
        $row = $this->db->execute_query('SELECT id, description, units FROM pids WHERE user_id=? AND id=?',
            [$this->uid(), $pid])->fetch_assoc();
        if (!$row && in_array($pid, self::GPS_PIDS, true)) {
            return ['id' => $pid, 'description' => ['kff1005' => 'GPS longitude', 'kff1006' => 'GPS latitude', 'kff1007' => 'GPS bearing'][$pid], 'units' => '°'];
        }
        if (!$row) {
            throw new InvalidArgumentException('PID not found for user');
        }
        return $row;
    }

    public function bounds(array $args): array
    {
        $session = $this->session($args);
        $start = $this->intParam($args, 'start_ms', max(1, $session['start_ms']), 1, PHP_INT_MAX);
        $finish = $this->intParam($args, 'end_ms', max($start, $session['end_ms'], $session['start_ms']), 1, PHP_INT_MAX);
        if ($start > $finish) {
            throw new InvalidArgumentException('start_ms is later than end_ms');
        }
        return [$session, $start, $finish];
    }

    /** Keyset-paginated, unbuffered scan. logs has PRIMARY KEY (user_id, time).
     *  Default is 120k; explicitly requested full scans are capped at 250k rows. Pages are 10k.
     */
    public function scan(int $sid, int $start, int $end, callable $callback, bool $full = false): array
    {
        $limit = $full ? self::MAX_FULL_SCAN_ROWS : self::MAX_SCANNED_ROWS;
        $cursor = $start;
        $scanned = 0;
        $truncated = false;
        $uid = $this->uid();
        while (true) {
            $page = min(self::SCAN_CHUNK, $limit - $scanned + 1);
            $stmt = $this->db->prepare(
                'SELECT time, data FROM logs WHERE user_id=? AND session=? AND time>=? AND time<=? '
                . 'AND time>=? ORDER BY time ASC LIMIT ?'
            );
            $stmt->bind_param('iiiiii', $uid, $sid, $start, $end, $cursor, $page);
            $stmt->execute();
            $stmt->bind_result($time, $raw);
            $fetched = 0;
            try {
                while ($stmt->fetch()) {
                    if ($scanned >= $limit) {
                        $truncated = true;
                        break;
                    }
                    $fetched++;
                    $scanned++;
                    $cursor = (int)$time + 1; // Unique (user_id, time), no duplicate rows.
                    $callback((int)$time, decode_log_data((string)$raw));
                }
            } finally {
                $stmt->close();
            }
            if ($truncated || $fetched < $page) break;
        }
        return ['scanned' => $scanned, 'scan_truncated' => $truncated,
            'scan_limit' => $limit, 'full_scan_requested' => $full];
    }

    public static function fullScan(array $args): bool
    {
        if (isset($args['full_scan']) && !is_bool($args['full_scan'])) {
            throw new InvalidArgumentException('full_scan must be boolean');
        }
        return $args['full_scan'] ?? false;
    }

    public static function numeric(array $row, string $pid) : ?float
    {
        $v = $row[$pid] ?? null;
        if (!is_numeric($v) || !is_finite((float)$v)) {
            return null;
        }
        // Non-GPS PID value -1 is an application missing-data sentinel.
        // Geographic coordinates such as longitude -1.0 are valid.
        if (!in_array($pid, self::GPS_PIDS, true) && (float)$v === - 1.0) {
            return null;
        }
        return (float)$v;
    }

    private static function points(array $entries): array
    {
        usort($entries, static fn($a, $b) => $a[0] <=> $b[0]);
        $unique = [];
        foreach ($entries as $point) {
            $unique[(string)$point[0]] = $point;
        }
        return array_values($unique);
    }

    public function metricData(array $args): array
    {
        $pid = $this->metric($args);
        [$session, $start, $end] = $this->bounds($args);
        $maxPoints = $this->intParam($args, 'max_points', 500, 10, 1000);
        $bucketMs = max(1, (int)ceil(($end - $start + 1) / max(1, intdiv($maxPoints, 2))));
        $buckets = [];
        $available = 0;
        $scan = $this->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use ($pid, $start, $bucketMs, &$buckets, &$available): void {
                $value = self::numeric($row, $pid['id']);
                if ($value === null) {
                    return ;
                }
                $available++;
                $bucket = intdiv($time - $start, $bucketMs);
                if (!isset($buckets[$bucket])) {
                    $buckets[$bucket] = ['min' => [$time, $value], 'max' => [$time, $value]];
                } else {
                    if ($value < $buckets[$bucket]['min'][1]) {
                        $buckets[$bucket]['min'] = [$time, $value];
                    }
                    if ($value > $buckets[$bucket]['max'][1]) {
                        $buckets[$bucket]['max'] = [$time, $value];
                    }
                }
            }, self::fullScan($args));
        $points = [];
        foreach ($buckets as $b) {
            $points[] = $b['min'];
            $points[] = $b['max'];
        }
        $points = self::points($points);
        if (count($points) > $maxPoints) {
            // Timestamp bucketing can create a boundary extra point.
            $stride = (count($points) - 1) / ($maxPoints - 1);
            $points = array_map(static fn($i) => $points[(int)round($i * $stride)], range(0, $maxPoints - 1));
        }
        return ['session_id' => $session['session_id'], 'pid' => $pid, 'start_ms' => $start, 'end_ms' => $end,
            'points' => $points, 'valid_samples' => $available, 'returned_points' => count($points),
            'incomplete' => $scan['scan_truncated']] + $scan;
    }

    public function metricStats(array $args): array
    {
        $pid = $this->metric($args);
        [$session, $start, $end] = $this->bounds($args);
        $agg = new McpMetricAggregate($pid);
        $scan = $this->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use ($pid, $agg): void {
                $agg->observe($time, self::numeric($row, $pid['id']));
            }, self::fullScan($args));
        return $agg->result($session['session_id'], $scan, $start, $end);
    }

    /** Read each row once, irrespective of number of requested metrics. */
    public function multiStats(array $args): array
    {
        $ids = $args['pids'] ?? null;
        if (!is_array($ids) || count($ids) < 1 || count($ids) > 6) {
            throw new InvalidArgumentException('Provide 1–6 PIDs');
        }
        $accumulators = [];
        foreach (array_unique($ids) as $id) {
            if (!is_string($id)) throw new InvalidArgumentException('Invalid PID');
            $pid = $this->metric(['pid' => $id]);
            $accumulators[$id] = new McpMetricAggregate($pid);
        }
        [$session, $start, $end] = $this->bounds($args);
        $scan = $this->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use ($accumulators): void {
                foreach ($accumulators as $id => $agg) {
                    $agg->observe($time, self::numeric($row, $id));
                }
            }, self::fullScan($args));
        return ['session' => $session,
            'metrics' => array_values(array_map(
                static fn(McpMetricAggregate $agg) => $agg->result($session['session_id'], $scan, $start, $end),
                $accumulators)),
            'scan' => $scan, 'incomplete' => $scan['scan_truncated'],
            'caution' => $scan['scan_truncated'] ? 'Partial session: scan limit reached' :
                'Time-weighted means exclude detected gaps; PIDs can have different sampling rates.'];
    }

    public function compareSessions(array $args): array
    {
        $ids = $args['session_ids'] ?? null;
        if (!is_array($ids) || count($ids) < 2 || count($ids) > 5) {
            throw new InvalidArgumentException('Require 2–5 session_ids');
        }
        $pid = $this->metric($args);
        $result = [];
        foreach (array_unique($ids) as $id) {
            $result[] = $this->metricStats(['session_id' => $id, 'pid' => $pid['id'],
                'full_scan' => self::fullScan($args)]);
        }
        if (count($result) < 2) {
            throw new InvalidArgumentException('Require distinct session ids');
        }
        return ['pid' => $pid, 'comparison' => $result];
    }

    public function live(array $args): array
    {
        $row = $this->db->execute_query(
            'SELECT time, session, data FROM logs WHERE user_id=? ORDER BY time DESC LIMIT 1',
            [$this->uid()]
        )->fetch_assoc();
        if (!$row) {
            return ['available' => false, 'reason' => 'No telemetry uploaded'];
        }
        $metrics = decode_log_data((string)$row['data']);
        $requested = $args['pids'] ?? null;
        if ($requested !== null) {
            if (!is_array($requested) || count($requested) > 50) {
                throw new InvalidArgumentException('pids must be an array of up to 50 identifiers');
            }
            $out = [];
            foreach ($requested as $pid) {
                if (!is_string($pid)) {
                    throw new InvalidArgumentException('Invalid PID');
                }
                $this->metric(['pid' => $pid]);
                if (array_key_exists($pid, $metrics)) {
                    $out[$pid] = $metrics[$pid];
                }
            }
            $metrics = $out;
        }
        if (!McpSecurity::can($this->auth, ['location:read'])) {
            foreach (self::GPS_PIDS as $pid) {
                unset($metrics[$pid]);
            }
        }
        if (count($metrics) > 100) {
            $metrics = array_slice($metrics, 0, 100, true);
        }
        $time = (int)$row['time'];
        return ['available' => true, 'session_id' => (string)$row['session'], 'time_ms' => $time,
            'age_seconds' => max(0, round((microtime(true) * 1000 - $time) / 1000, 1)),
            'stale' => ($time < ((int)(microtime(true) * 1000) - 60000)), 'values' => $metrics,
            'units' => 'raw sensor units'];
    }

    public function deviceStatus(): array
    {
        $last = $this->db->execute_query(
            'SELECT time, session FROM logs WHERE user_id=? ORDER BY time DESC LIMIT 1', [$this->uid()]
        )->fetch_assoc();
        $time = $last ? (int)$last['time'] : null;
        return [
            'last_received_ms' => $time,
            'last_session_id' => $last ? (string)$last['session'] : null,
            'age_seconds' => $time === null ? null : max(0, (int)floor(microtime(true) - $time / 1000)),
            'recent_telemetry' => $time !== null && $time >= ((int)(microtime(true) * 1000) - 60000),
            'note' => 'Recent upload is not proof of a live device connection',
        ];
    }

    public function events(array $args, bool $forceLocation = false): array
    {
        $pid = $this->metric($args);
        [$session, $start, $end] = $this->bounds($args);
        $operator = $args['operator'] ?? 'gt';
        if (!in_array($operator, ['gt', 'gte', 'lt', 'lte'], true)) {
            throw new InvalidArgumentException('Invalid operator');
        }
        $threshold = $args['threshold'] ?? null;
        if (!is_numeric($threshold) || !is_finite((float)$threshold)) {
            throw new InvalidArgumentException('threshold must be numeric');
        }
        $limit = $this->intParam($args, 'max_events', 40, 1, 100);
        $withGps = $forceLocation || ($args['include_location'] ?? false) === true;
        if ($withGps) {
            McpSecurity::requireScopes($this->auth, ['location:read']);
        }
        $fn = match ($operator) {
            'gt' => static fn($x) => $x > (float)$threshold,
            'gte' => static fn($x) => $x >= (float)$threshold,
            'lt' => static fn($x) => $x < (float)$threshold,
            'lte' => static fn($x) => $x <= (float)$threshold,
        };
        $events = [];
        $active = null;
        $total = 0;
        $sampling = new McpSampling();
        $scan = $this->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use ($pid, $fn, $operator, $limit, $withGps, $sampling, &$events, &$active, &$total): void {
                $gap = $sampling->observe($time)['gap'];
                if ($gap && $active !== null) {
                    $active['duration_ms'] = $active['end_ms'] - $active['start_ms'];
                    ++$total;
                    if (count($events) < $limit) $events[] = $active;
                    $active = null;
                }
                $v = self::numeric($row, $pid['id']);
                if ($v !== null && $fn($v)) {
                    if ($active === null) {
                        $active = ['start_ms' => $time, 'end_ms' => $time, 'peak_value' => $v, 'peak_time_ms' => $time, 'samples' => 0];
                        if ($withGps) {
                            $lat = self::numeric($row, 'kff1006'); $lon = self::numeric($row, 'kff1005');
                            if ($lat !== null && $lon !== null && abs($lat) <= 90 && abs($lon) <= 180 && ($lat != 0 || $lon != 0)) {
                                $active['location'] = ['lat' => $lat, 'lon' => $lon];
                            }
                        }
                    }
                    $active['end_ms'] = $time;
                    $active['samples']++;
                    // Furthest excursion in the threshold direction.
                    if ((in_array($operator, ['gt', 'gte'], true) && $v > $active['peak_value']) ||
                        (in_array($operator, ['lt', 'lte'], true) && $v < $active['peak_value'])) {
                        $active['peak_value'] = $v; $active['peak_time_ms'] = $time;
                    }
                } elseif ($active !== null) {
                    $active['duration_ms'] = $active['end_ms'] - $active['start_ms'];
                    ++$total;
                    if (count($events) < $limit) {
                        $events[] = $active;
                    }
                    $active = null;
                }
            }, self::fullScan($args));
        if ($active !== null) {
            $active['duration_ms'] = $active['end_ms'] - $active['start_ms'];
            ++$total;
            if (count($events) < $limit) {
                $events[] = $active;
            }
        }
        return ['session_id' => $session['session_id'], 'pid' => $pid, 'operator' => $operator,
            'threshold' => (float)$threshold, 'event_count' => $total, 'events' => $events, 'events_truncated' => $total > $limit,
            'segmentation' => $sampling->summary(), 'incomplete' => $scan['scan_truncated']] + $scan;
    }

    public function anomalies(array $args): array
    {
        $pid = $this->metric($args);
        $stats = $this->metricStats($args);
        $sd = $stats['stddev'];
        $mean = $stats['mean'];
        $sigma = $args['sigma'] ?? 3.0;
        if (!is_numeric($sigma) || (float)$sigma < 1.5 || (float)$sigma > 10) {
            throw new InvalidArgumentException('sigma must be between 1.5 and 10');
        }
        if ($sd === null || $sd == 0.0) {
            return ['pid' => $pid, 'events' => [], 'reason' => 'Not enough variance'];
        }
        [$session, $start, $end] = $this->bounds($args);
        $max = $this->intParam($args, 'max_events', 30, 1, 100);
        $events = [];
        $total = 0;
        $scan = $this->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use ($pid, $mean, $sd, $sigma, $max, &$total, &$events): void {
                $v = self::numeric($row, $pid['id']); if ($v === null) {
                    return ;
                }
                $z = ($v - $mean) / $sd;
                if (abs($z) >= (float)$sigma) {
                    $total++; if (count($events) < $max) {
                        $events[] = ['time_ms' => $time, 'value' => $v, 'z_score' => round($z, 2)];
                    }
                }
            }, self::fullScan($args));
        return ['session_id' => $session['session_id'], 'pid' => $pid, 'baseline_mean' => $mean,
            'baseline_stddev' => $sd, 'sigma' => (float)$sigma, 'outliers' => $total,
            'events' => $events, 'events_truncated' => $total > $max,
            'baseline_incomplete' => $stats['scan_truncated'],
            'incomplete' => $scan['scan_truncated'] || $stats['scan_truncated'],
            'caution' => $stats['scan_truncated'] ? 'Partial baseline: z-scores are from an incomplete subset' : null,
            'method' => 'Univariate sample z-score; diagnostic hint, not a confirmed fault'] + $scan;
    }

    public function route(array $args): array
    {
        [$session, $start, $end] = $this->bounds($args);
        $maxPoints = $this->intParam($args, 'max_points', 200, 10, 500);
        $bucketMs = max(1, (int)ceil(($end - $start + 1) / $maxPoints));
        $selected = [];
        $valid = 0;
        $scan = $this->scan((int)$session['session_id'], $start, $end,
            static function (int $time, array $row) use ($start, $bucketMs, &$selected, &$valid): void {
                $lat = self::numeric($row, 'kff1006'); $lon = self::numeric($row, 'kff1005');
                if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180 || ($lat == 0.0 && $lon == 0.0)) {
                    return ;
                }
                $valid++;
                $key = intdiv($time - $start, $bucketMs);
                $selected[$key] = ['time_ms' => $time, 'lat' => $lat, 'lon' => $lon, 'bearing' => self::numeric($row, 'kff1007')];
            }, self::fullScan($args));
        $points = array_values($selected);
        if (count($points) > $maxPoints) {
            $points = array_slice($points, 0, $maxPoints);
        }
        return ['session_id' => $session['session_id'], 'valid_gps_samples' => $valid, 'points' => $points,
            'incomplete' => $scan['scan_truncated']] + $scan;
    }


}

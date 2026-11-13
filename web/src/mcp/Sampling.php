<?php
declare(strict_types=1);

/** Streaming time-axis analysis. Uses actual DB timestamps; never stitched chart X coordinates. */
final class McpSampling
{
    private ?int $previous = null;
    private array $recent = [];
    private int $slowStreak = 0;
    private int $seen = 0;
    private int $gapCount = 0;
    private array $intervalSample = [];
    private const HISTORY = 17;
    private const SAMPLE_LIMIT = 2048;

    private static function percentile(array $values, float $fraction): ?float
    {
        if ($values === []) return null;
        sort($values, SORT_NUMERIC);
        return (float)$values[(int)floor((count($values) - 1) * $fraction)];
    }

    /** Gap threshold adjusts to the recent non-gap cadence; 120 s is a conservative cold-start bound. */
    public function typicalIntervalMs(): ?float
    {
        return self::percentile($this->recent, 0.5);
    }

    public function thresholdMs(): int
    {
        $base = self::percentile($this->recent, 0.5);
        return $base === null || count($this->recent) < 3
            ? 120000 : max(100, (int)ceil($base * 8));
    }

    /** @return array{delta_ms:?int,gap:bool,threshold_ms:int} */
    public function observe(int $time): array
    {
        $threshold = $this->thresholdMs();
        if ($this->previous === null) {
            $this->previous = $time;
            return ['delta_ms' => null, 'gap' => false, 'threshold_ms' => $threshold];
        }
        $delta = $time - $this->previous;
        $this->previous = $time;
        if ($delta <= 0) {
            return ['delta_ms' => $delta, 'gap' => false, 'threshold_ms' => $threshold];
        }
        $gap = $delta > $threshold;
        if ($gap) {
            $this->gapCount++;
            $this->slowStreak++;
            // If acquisition has switched from fast to slow, learn the new cadence.
            if ($this->slowStreak >= 3) {
                $this->recent = [$delta];
                $this->slowStreak = 0;
            }
        } else {
            $this->slowStreak = 0;
            $this->recent[] = $delta;
            if (count($this->recent) > self::HISTORY) array_shift($this->recent);
            $this->seen++;
            if (count($this->intervalSample) < self::SAMPLE_LIMIT) {
                $this->intervalSample[] = $delta;
            } elseif ($this->seen % 64 === 0) {
                $this->intervalSample[($this->seen / 64) % self::SAMPLE_LIMIT] = $delta;
            }
        }
        return ['delta_ms' => $delta, 'gap' => $gap, 'threshold_ms' => $threshold];
    }

    public function summary(): array
    {
        return ['median_interval_ms' => self::percentile($this->intervalSample, 0.5),
            'p95_interval_ms' => self::percentile($this->intervalSample, 0.95),
            'observed_regular_intervals' => $this->seen, 'intervals_sampled' => count($this->intervalSample),
            'gap_count' => $this->gapCount, 'current_gap_threshold_ms' => $this->thresholdMs(),
            'method' => 'Rolling median of last 17 regular intervals, 8x threshold (minimum 100 ms); cold start 120 s; sampled percentiles are approximate'];
    }
}

/** One accumulator per PID; several can be updated during a single log scan. */
final class McpMetricAggregate
{
    private int $count = 0;
    private float $mean = 0.0;
    private float $m2 = 0.0;
    private ?array $min = null;
    private ?array $max = null;
    private ?array $first = null;
    private ?array $last = null;
    private ?array $previous = null;
    private float $integral = 0.0;
    private int $coveredMs = 0;
    private int $missing = 0;
    private McpSampling $sampling;
    public function __construct(private readonly array $pid)
    {
        $this->sampling = new McpSampling();
    }
    public function observe(int $time, ?float $value): void
    {
        if ($value === null) {
            $this->missing++;
            $this->previous = null;
            return;
        }
        $status = $this->sampling->observe($time);
        if ($this->previous !== null && !$status['gap'] && ($status['delta_ms'] ?? 0) > 0) {
            $dt = $status['delta_ms'];
            $this->integral += ($this->previous['value'] + $value) / 2 * $dt;
            $this->coveredMs += $dt;
        }
        $point = ['time_ms' => $time, 'value' => $value];
        $this->first ??= $point;
        $this->last = $point;
        if ($this->min === null || $value < $this->min['value']) $this->min = $point;
        if ($this->max === null || $value > $this->max['value']) $this->max = $point;
        ++$this->count;
        $delta = $value - $this->mean;
        $this->mean += $delta / $this->count;
        $this->m2 += $delta * ($value - $this->mean);
        $this->previous = $point;
    }
    public function result(string $sessionId, array $scan, int $rangeStart, int $rangeEnd): array
    {
        $span = $this->first && $this->last ? max(0, $this->last['time_ms'] - $this->first['time_ms']) : 0;
        return ['session_id' => $sessionId, 'pid' => $this->pid,
            'valid_samples' => $this->count, 'missing_samples' => $this->missing,
            'min' => $this->min, 'max' => $this->max, 'first' => $this->first, 'last' => $this->last,
            'mean' => $this->count ? round($this->mean, 6) : null,
            'sample_mean' => $this->count ? round($this->mean, 6) : null,
            'stddev' => $this->count > 1 ? round(sqrt(max(0, $this->m2 / ($this->count - 1))), 6) : null,
            'time_weighted_mean' => $this->coveredMs > 0 ? round($this->integral / $this->coveredMs, 6) : null,
            'observed_coverage_ms' => $this->coveredMs,
            'coverage_window_ms' => $span,
            'coverage_ratio' => $span > 0 ? round(min(1, $this->coveredMs / $span), 6) : null,
            'coverage_basis' => 'Between first and last valid measurement, excluding detected gaps and missing samples',
            'requested_window_ms' => max(0, $rangeEnd - $rangeStart),
            'sampling' => $this->sampling->summary(),
            'incomplete' => $scan['scan_truncated'],
            'caution' => $scan['scan_truncated'] ? 'Partial result: the row scan limit was reached; this is NOT whole-session statistics' : null,
        ] + $scan;
    }
}

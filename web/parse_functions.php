<?php
declare(strict_types=1);

/**
 * Count uppercase strings
 */
function substri_count(?string $haystack, ?string $needle): int
{
    $haystack = $haystack ?? '';
    $needle = $needle ?? '';

    return substr_count(strtoupper($haystack), strtoupper($needle));
}

/**
 * Calculate average from array of numbers
 */
function average(array $arr): float
{
    $count = count($arr);
    if ($count === 0) return 0.0;

    $sum = array_sum($arr);

    return $sum / $count;
}

/**
 * Convert pressure values between units
 */
function pressure_conv(float $val, string $unit, string $id): float
{
    return round(
        match ($unit) {
            "Psi to Bar" => $id !== "RedManage" ? $val / 14.504 : $val,
            "Bar to Psi" => $val * 14.504,
            default => $val,
        },
        2
    );
}

/**
 * Convert speed values between units
 */
function speed_conv(float|int $val, string $unit, string $id): int
{
    return (int)round(
        match ($unit) {
            "km to miles" => $val * 0.621371,
            "miles to km" => $id !== "RedManage" ? $val * 1.609344 : $val,
            default => $val,
        }
    );
}

/**
 * Convert temperature values between units
 */
function temp_conv(float|int $val, string $unit, string $id): float
{
    return round(
        match ($unit) {
            "Celsius to Fahrenheit" => $val * 9.0 / 5.0 + 32.0,
            "Fahrenheit to Celsius" => $id !== "RedManage" ? ($val - 32.0) * 5.0 / 9.0 : $val,
            default => $val,
        },
        1
    );
}

/**
 * @param mysqli $db
 * @param string $session_id
 * @param string $db_sessions_table
 * @return int|null
 */
function getLastUpdateTimestamp(mysqli $db, string $session_id, string $db_sessions_table): ?int
{
    $result = $db->execute_query(
        "SELECT timeend FROM $db_sessions_table WHERE session = ?",
        [$session_id]
    );

    if ($row = $result->fetch_assoc()) {
        return (int)$row['timeend'];
    }

    return null;
}
?>

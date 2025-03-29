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

/**
 * Datapoints filter for GPS data
 */
function getFilteredGpsQuery($db_table, $filterRate) {
    $filterRate = max(1, min(5, intval($filterRate)));

    if ($filterRate === 1) {
        // 100% of data (without filtering)
        return "SELECT kff1006, kff1005, time FROM $db_table WHERE session=? ORDER BY time DESC";
    } else if ($filterRate === 2) {
        // 75%
        return "SELECT * FROM (
            SELECT kff1006, kff1005, time, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 4 < 3
        ORDER BY time DESC";
    } else if ($filterRate === 3) {
        // 50%
        return "SELECT * FROM (
            SELECT kff1006, kff1005, time, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 2 = 0
        ORDER BY time DESC";
    } else if ($filterRate === 4) {
        // 33%
        return "SELECT * FROM (
            SELECT kff1006, kff1005, time, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 3 = 0
        ORDER BY time DESC";
    } else {
        // 25%
        return "SELECT * FROM (
            SELECT kff1006, kff1005, time, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 4 = 0
        ORDER BY time DESC";
    }
}

/**
 * Datapoints filter for sessions pids data
 */
function getFilteredQuery($selectstring, $db_table, $streamLimit, $filterRate) {
    $filterRate = max(1, min(5, intval($filterRate)));

    if ($filterRate === 1) {
        // 100% of data (without filtering)
        return "SELECT $selectstring FROM $db_table WHERE session=? ORDER BY time DESC $streamLimit";
    } else if ($filterRate === 2) {
        // 75%
        return "SELECT * FROM (
            SELECT $selectstring, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 4 < 3
        ORDER BY time DESC $streamLimit";
    } else if ($filterRate === 3) {
        // 50%
        return "SELECT * FROM (
            SELECT $selectstring, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 2 = 0
        ORDER BY time DESC $streamLimit";
    } else if ($filterRate === 4) {
        // 33%
        return "SELECT * FROM (
            SELECT $selectstring, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 3 = 0
        ORDER BY time DESC $streamLimit";
    } else {
        // 25%
        return "SELECT * FROM (
            SELECT $selectstring, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 4 = 0
        ORDER BY time DESC $streamLimit";
    }
}

/**
 * Forward url validation
 */
function isValidExternalHttpUrl($url) {
    // 1. Check if it's a valid URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    // 2. Check if the scheme is http or https
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array(strtolower($scheme), ['http', 'https'])) {
        return false;
    }

    // 3. Get the host from the URL
    $host = parse_url($url, PHP_URL_HOST);

    // 4. Get the current host (the server's own domain)
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';

    // 5. Normalize both hosts by removing 'www.' and converting to lowercase
    $host = strtolower(preg_replace('/^www\./', '', $host));
    $currentHost = strtolower(preg_replace('/^www\./', '', $currentHost));

    // 6. Return true only if the URL doesn't point to the same host
    return $host !== $currentHost;
}
?>

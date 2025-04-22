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

/**
 * Checks rate limits for requests based on client IP
 * Running memcached required
 *
 * @param int $limit Maximum number of attempts allowed
 * @param int $period Time period in seconds for the limit
 * @param bool $success If true, resets the counter for successful attempts
 * @return bool True if within limits, false if exceeded
 */
function checkRateLimit($limit = 10, $period = 3600, $success = false) {
    global $memcached, $memcached_connected;

    // Determine client IP considering possible proxies
    $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

    // If IP contains a list of addresses (comma separated), take the first one
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    $rate_key = "rate_limit:block:{$ip}";
    $backoff_key = "rate_backoff:block:{$ip}";

    // If this is a successful request, reset the counter and return true
    if ($success && $memcached_connected) {
        try {
            $memcached->delete($rate_key);
            $memcached->delete($backoff_key);
        } catch (Exception $e) {
            error_log("Memcached error clearing rate limit: " . $e->getMessage());
        }
        return true;
    }

    if (!$memcached_connected) {
        return true; // If memcached is not connected, skip the check
    }

    try {
        $attempts = $memcached->get($rate_key);
        if ($attempts === false) {
            $attempts = 0;
        }

        // Check if we need to enforce a backoff period
        $backoff = $memcached->get($backoff_key);
        if ($backoff !== false) {
            $now = time();
            if ($now < $backoff) {
                // Still in backoff period, reject request
                return false;
            }
        }

        $attempts++;
        $memcached->set($rate_key, $attempts, $period);

        if ($attempts > $limit) {
            // Calculate exponential backoff time
            // Start with 5 seconds, double with each attempt beyond limit
            $backoff_seconds = min(1800, 5 * pow(2, $attempts - $limit - 1)); // Cap at 30 minutes
            $backoff_until = time() + $backoff_seconds;

            // Store the backoff timestamp
            $memcached->set($backoff_key, $backoff_until, $period);

            return false;
        }

        // For non-blocked but repeated requests, set a short backoff to slow down attempts
        if ($attempts > 3) {
            $short_backoff = time() + ($attempts - 3); // 1 second per attempt beyond 3
            $memcached->set($backoff_key, $short_backoff, $period);
        }

        return true;
    } catch (Exception $e) {
        error_log("Memcached error in rate limiting: " . $e->getMessage());
        return true; // In case of cache error, don't block access
    }
}

/**
 * Generate authentication token
 */
function generate_token(string $username): string
{
    return hash('sha3-256', random_bytes(32) . $username);
}

/**
 * Get Bearer token from request headers
 */
function getBearerToken(): ?string
{
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    return isset($headers['authorization']) ? 
        trim(str_replace('Bearer ', '', $headers['authorization'])) : 
        null;
}

/**
 * Send notification to Telegram
 * @return array|null Returns decoded response or null on failure
 */
function notify(?string $text, ?string $tg_token, ?string $tg_chatid): ?array
{
    if (empty($tg_token) || empty($tg_chatid)) {
        return null;
    }

    $ch = curl_init('https://api.telegram.org/bot' . $tg_token . '/sendMessage');
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $tg_chatid,
            'text' => $text,
        ],
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * Generate CSRF token
 */
function generate_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || 
        !isset($_SESSION['csrf_token_time']) || 
        time() - $_SESSION['csrf_token_time'] > 3300
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token(string $token): bool
{
    return isset($_SESSION['csrf_token']) &&
           isset($_SESSION['csrf_token_time']) &&
           time() - $_SESSION['csrf_token_time'] <= 3600 &&
           hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Forward upload request
 */
function forward_request(string $username, string $forward_url, ?string $forward_token = null): void
{
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $forward_data = $_REQUEST;

    if (empty($forward_data['eml'])) {
        $forward_data['eml'] = $username . '@redbox.null';
    }

    $ch = curl_init();

    if ($method === 'GET') {
        $query = http_build_query($forward_data);
        $url_with_query = $forward_url . (strpos($forward_url, '?') === false ? '?' : '&') . $query;
        curl_setopt($ch, CURLOPT_URL, $url_with_query);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    } else {
        curl_setopt($ch, CURLOPT_URL, $forward_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $forward_data);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

    // Headers
    $headers = [];
    if (!empty($forward_token)) {
        $headers[] = 'Authorization: Bearer ' . $forward_token;
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    // Execute and log
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        error_log("[Forwarding] Failed to forward {$method} request for user {$username} to {$forward_url}: $error");
    } elseif ($http_code >= 400) {
        error_log("[Forwarding] Forwarded {$method} request for user {$username} to {$forward_url}, but got HTTP error: $http_code");
    } else {
        error_log("[Forwarding] Successfully forwarded {$method} request for user {$username} to {$forward_url}");
    }
}

/**
 * return PIDs data for API
 */
function getPidsQuery($db, $table, $includeGps = false)
{
    $where = $includeGps ? "stream = 1 OR id IN ('kff1005', 'kff1006')" : "stream = 1";
    return $db->query("SELECT id, description, units FROM $table WHERE $where ORDER BY description ASC");
}
?>
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
        return "SELECT kff1006, kff1005, kff1007, time FROM $db_table WHERE session=? ORDER BY time DESC";
    } elseif ($filterRate === 2) {
        // 75%
        return "SELECT * FROM (
            SELECT kff1006, kff1005, kff1007, time, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 4 < 3
        ORDER BY time DESC";
    } elseif ($filterRate === 3) {
        // 50%
        return "SELECT * FROM (
            SELECT kff1006, kff1005, kff1007, time, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 2 = 0
        ORDER BY time DESC";
    } elseif ($filterRate === 4) {
        // 33%
        return "SELECT * FROM (
            SELECT kff1006, kff1005, kff1007, time, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 3 = 0
        ORDER BY time DESC";
    } else {
        // 25%
        return "SELECT * FROM (
            SELECT kff1006, kff1005, kff1007, time, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
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
    } elseif ($filterRate === 2) {
        // 75%
        return "SELECT * FROM (
            SELECT $selectstring, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 4 < 3
        ORDER BY time DESC $streamLimit";
    } elseif ($filterRate === 3) {
        // 50%
        return "SELECT * FROM (
            SELECT $selectstring, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
            FROM $db_table
            WHERE session=?
        ) as filtered_data
        WHERE row_num % 2 = 0
        ORDER BY time DESC $streamLimit";
    } elseif ($filterRate === 4) {
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
 * @return array|null|int Returns decoded response on success, null on nothing, -1 on timeout
 */
function notify(?string $text, ?string $tg_token, ?string $tg_chatid, ?string $tg_socks_proxy = null): array|int|null
{
    global $tg_api_url, $tg_api_id;

    if (empty($tg_token) || empty($tg_chatid)) {
        return null;
    }

    // Validate parameters
    if (!preg_match('/^\d+:[a-zA-Z0-9_-]+$/', $tg_token) ||
        !preg_match('/^-?\d+$/', $tg_chatid)) {
        return null;
    }

    $apiBaseUrl = !empty($tg_api_url) ? $tg_api_url : 'https://api.telegram.org/bot';

    $ch = curl_init($apiBaseUrl . urlencode($tg_token) . '/sendMessage');
    if ($ch === false) {
        return null;
    }

    $curlOptions = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $tg_chatid,
            'text' => $text,
        ],
        CURLOPT_HTTPHEADER => array_key_exists('tg_api_id', $GLOBALS) ? ['X-Connection-Id: ' . ($tg_api_id ?? '')] : [],
    ];

    // Configure proxy if provided
    if (!empty($tg_socks_proxy)) {
        // Parse proxy string (format: address:port or username:password@address:port)
        $proxyAuth = null;
        $proxyAddress = $tg_socks_proxy;

        // Check if proxy contains authentication
        if (strpos($tg_socks_proxy, '@') !== false) {
            $parts = explode('@', $tg_socks_proxy, 2);
            $proxyAuth = $parts[0];
            $proxyAddress = $parts[1];
        }

        // Validate proxy address format (address:port)
        $addressParts = explode(':', $proxyAddress);
        if (count($addressParts) === 2 && is_numeric($addressParts[1])) {
            $curlOptions[CURLOPT_PROXY] = $proxyAddress;
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;

            // Set proxy authentication if provided
            if (!empty($proxyAuth)) {
                $authParts = explode(':', $proxyAuth, 2);
                if (count($authParts) === 2) {
                    $curlOptions[CURLOPT_PROXYUSERPWD] = $proxyAuth;
                }
            }
        } else {
            error_log("Invalid proxy format");
            curl_close($ch);
            return null;
        }
    }

    curl_setopt_array($ch, $curlOptions);

    $response = curl_exec($ch);

    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Check for timeout
    if ($response === false) {
        $curlError = curl_errno($ch);
        curl_close($ch);

        // CURLE_OPERATION_TIMEDOUT (28) is the timeout error code
        if ($curlError === CURLE_OPERATION_TIMEDOUT) {
            return -1;
        }

        return null;
    }

    curl_close($ch);

    if ($httpCode === 500) {
        return -1;
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
 * return PIDs data for API
 */
function getPidsQuery($db, $table, $includeGps = false)
{
    $where = $includeGps ? "stream = 1 OR id IN ('kff1005', 'kff1006', 'kff1007')" : "stream = 1";
    return $db->query("SELECT id, description, units FROM $table WHERE $where ORDER BY description ASC");
}

/**
 * return fomatted data
 */
function formatDuration(int $start, int $end, string $lang, bool $isMilliseconds = true): string {
    global $translations;

    if ($isMilliseconds) {
        $start = intdiv($start, 1000);
        $end = intdiv($end, 1000);
    }

    $duration = $end - $start;
    if ($duration < 0) {
        return "00:00:00";
    }

    $days = intdiv($duration, 86400);
    $hours = intdiv($duration % 86400, 3600);
    $minutes = intdiv($duration % 3600, 60);
    $seconds = $duration % 60;

    if ($days > 0) {
        return sprintf('%d'.$translations[$lang]['days'].' %02d:%02d:%02d', $days, $hours, $minutes, $seconds);
    }

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

$valid_months = [
    'ALL', 'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

function sanitizeInput($input, string $type = 'string') {
    global $year;

    if ($input === null) {
        return null;
    }

    $input = is_scalar($input) ? strval($input) : '';

    switch ($type) {
        case 'int':
            $result = filter_var($input, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0]
            ]);
            return $result !== false ? $result : null;

        case 'alphanum':
            return preg_match('/^[a-zA-Z0-9]+$/', $input) ? $input : null;

        case 'month':
            global $valid_months;
            return in_array($input, $valid_months, true) ? $input : null;

        case 'year':
            $year = filter_var($input, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 2000, 'max_range' => 2100]
            ]);
            return $year !== false ? strval($year) : null;

        case 'year_or_all':
            if (strtoupper($input) === 'ALL') {
                return 'ALL';
            }
            $year = filter_var($input, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 2000, 'max_range' => 2100]
            ]);
            return $year !== false ? strval($year) : null;

        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * single insert of raw data
 */
function insert_single_record(mysqli $db, string $db_table, array $rawkeys, array $rawvalues) {
    $sql = "INSERT IGNORE INTO $db_table (".quote_names($rawkeys).") VALUES (".quote_values($rawvalues).")";
    try {
        $db->query($sql);
    } catch (Exception $e) {
        cache_flush();
    }
}

/**
 * Bulk insert of raw data records.
 *
 * @param mysqli $db
 * @param string $db_table
 * @param array $records
 */
function insert_bulk_records(mysqli $db, string $db_table, array $records) {
    if (empty($records)) return;

    // Collect all unique keeys from all records
    $allKeys = [];
    foreach ($records as $record) {
        foreach (array_keys($record) as $key) {
            if (!in_array($key, $allKeys)) {
                $allKeys[] = $key;
            }
        }
    }

    // Build string of columns
    $columns = quote_names($allKeys);

    // Build value array for each record
    $valueRows = [];
    foreach ($records as $record) {
        $rowValues = [];
        foreach ($allKeys as $key) {
            $rowValues[] = $record[$key] ?? '';
        }
        $valueRows[] = '(' . quote_values($rowValues) . ')';
    }

    $valuesStr = implode(', ', $valueRows);
    $sql = "INSERT IGNORE INTO $db_table ($columns) VALUES $valuesStr";

    try {
        $db->query($sql);
    } catch (Exception $e) {
        cache_flush();
        error_log("Bulk insert error: " . $e->getMessage());
    }
}

/**
 * Session start records handler
 *
 * @param mysqli $db
 * @param array $record
 * @param string $db_sessions_table
 * @param string $lang
 * @param string $username
 * @param string $tg_token
 * @param string $tg_chatid
 * @param string $tg_socks_proxy
 * @param array $translations
 */
function processSessionStartRecord($db, array $record, string $db_sessions_table, string $lang, string $username, ?string $tg_token, ?string $tg_chatid, ?string $tg_socks_proxy, array $translations): void {
    $sesskeys = [];
    $sessvalues = [];
    $spv = [];
    $sessuploadid = $record['session'];
    $sesstime = $record['time'];
    $id = $record['id'] ?? '';

    $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

    foreach ($record as $key => $value) {
        if (preg_match("/^profile/", $key) && in_array($key, ['profileName'], true)) {
            $spv[$key] = $value;
        } elseif (in_array($key, ['session', 'time', 'id'], true)) {
            $sesskeys[] = $key;
            $sessvalues[] = $value;
        }
    }

    $sesskeys[] = 'timeend';
    $sessvalues[] = $sesstime;

    $sessionqrystring = "INSERT INTO $db_sessions_table (" . quote_names($sesskeys) . ") VALUES (" . quote_values($sessvalues) . ") ON DUPLICATE KEY UPDATE id=?, timeend=?, sessionsize=sessionsize+1";
    $db->execute_query($sessionqrystring, [$id, $sesstime]);

    $updateFields = [];
    $params = [];
    foreach ($spv as $field => $value) {
        if ($value !== '') {
            $updateFields[] = "$field = ?";
            $params[] = $value;
        }
    }

    if (!empty($updateFields)) {
        $updateFields[] = "ip = ?";
        $params[] = $ip;
        $updateFields[] = "timeend = ?";
        $timeend = round(microtime(true) * 1000);
        $params[] = $timeend;

        $sql = "UPDATE $db_sessions_table SET " . implode(', ', $updateFields) . " WHERE session = ?";
        $params[] = $sessuploadid;
        $db->execute_query($sql, $params);
    }

    if (!empty($tg_token) && !empty($tg_chatid)) {
        $delay = time() - intval($sessuploadid / 1000);
        if ($delay > 10) {
            $formattedDelay = formatDuration((int)$sessuploadid, time() * 1000, $lang);
            $startTime = intval($sessuploadid / 1000);
            $formattedDate = date("d.m.Y", $startTime);
            $formattedTime = date("H:i", $startTime);
            $message = "{$translations[$lang]['upload.start']} {$ip}. {$translations[$lang]['sel.profile']}: {$spv['profileName']} ({$translations[$lang]['upload.delayed']} {$formattedDelay}, {$translations[$lang]['upload.start_time']} {$formattedDate} {$translations[$lang]['upload.at']} {$formattedTime})";
        } else {
            $message = "{$translations[$lang]['upload.start']} {$ip}. {$translations[$lang]['sel.profile']}: {$spv['profileName']}";
        }
        notify($message, $tg_token, $tg_chatid, $tg_socks_proxy);
    }
    touch(sys_get_temp_dir() . '/' . $username);
}

/**
 * Months translator
 */
function getTranslatedMonth(string $month, string $lang) {
    global $translations;
    $month_key = 'month.' . strtolower(substr($month, 0, 3));
    return $translations[$lang][$month_key] ?? $month;
}

/**
 * map with constrain
 */
function map(float $x, float $in_min, float $in_max, float $out_min, float $out_max): float {
    if ($in_min == $in_max) {
        return $x <= $in_min ? $out_min : $out_max;
    }

    $result = ($x - $in_min) * ($out_max - $out_min) / ($in_max - $in_min) + $out_min;

    return $out_min < $out_max
        ? max($out_min, min($out_max, $result))
        : max($out_max, min($out_min, $result));
}

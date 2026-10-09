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
 * @return int|null
 */
function getLastUpdateTimestamp(mysqli $db, int $user_id, string $session_id): ?int
{
    $result = $db->execute_query(
        "SELECT timeend FROM sessions WHERE user_id = ? AND session = ?",
        [$user_id, $session_id]
    );

    if ($row = $result->fetch_assoc()) {
        return (int)$row['timeend'];
    }

    return null;
}

/**
 * Datapoints filter for GPS data
 */
/**
 * GPS-запрос для plot/index/share. Возвращает SQL с одним плейсхолдером `?`
 * (session_id). user_id передаётся первым аргументом и вклеивается как int.
 */
function getFilteredGpsQuery(int $user_id, int $filterRate): string
{
    $user_id    = (int)$user_id;
    $filterRate = max(1, min(5, $filterRate));

    $cols = "
        COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.kff1006')) AS DECIMAL(20,10)), 0) AS kff1006,
        COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.kff1005')) AS DECIMAL(20,10)), 0) AS kff1005,
        COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.kff1007')) AS DECIMAL(20,10)), 0) AS kff1007,
        time
    ";

    $base = "FROM logs WHERE user_id = {$user_id} AND session = ?";

    if ($filterRate === 1) {
        return "SELECT {$cols} {$base} ORDER BY time DESC";
    }

    // остальные проценты — через ROW_NUMBER, как было
    $mod = match ($filterRate) {
        2 => "row_num % 4 < 3",   // 75%
        3 => "row_num % 2 = 0",   // 50%
        4 => "row_num % 3 = 0",   // 33%
        5 => "row_num % 4 = 0",   // 25%
        default => "1=1",
    };

    return "SELECT kff1006, kff1005, kff1007, time
            FROM (
                SELECT {$cols}, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
                {$base}
            ) as filtered_data
            WHERE {$mod}
            ORDER BY time DESC";
}

/**
 * Datapoints filter for sessions pids data
 */
/**
 * Запрос datapoint'ов для plot.php.
 * Возвращает SQL с одним плейсхолдером `?` (session_id).
 * Селектит time, data — выбор конкретных PID'ов делает PHP после decode_log_data.
 */
function getFilteredQuery(int $user_id, string $streamLimit, int $filterRate): string
{
    $user_id    = (int)$user_id;
    $filterRate = max(1, min(5, $filterRate));
    $base       = "FROM logs WHERE user_id = {$user_id} AND session = ?";

    if ($filterRate === 1) {
        return "SELECT time, data {$base} ORDER BY time DESC {$streamLimit}";
    }

    $mod = match ($filterRate) {
        2 => "row_num % 4 < 3",
        3 => "row_num % 2 = 0",
        4 => "row_num % 3 = 0",
        5 => "row_num % 4 = 0",
        default => "1=1",
    };

    return "SELECT time, data FROM (
                SELECT time, data, ROW_NUMBER() OVER (ORDER BY time DESC) as row_num
                {$base}
            ) as filtered_data
            WHERE {$mod}
            ORDER BY time DESC {$streamLimit}";
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
            error_log("Ratel cache error clearing rate limit: " . $e->getMessage());
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
        error_log("Ratel cache error in rate limiting: " . $e->getMessage());
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
function getPidsQuery($db, $table, $includeGps = false, ?int $user_id = null)
{
    if ($user_id === null) {
        $user_id = current_user_id();
    }
    $where = $includeGps
        ? "stream = 1 OR id IN ('kff1005', 'kff1006', 'kff1007')"
        : "stream = 1";
    return $db->execute_query(
        "SELECT id, description, units FROM pids
          WHERE user_id = ? AND ({$where})
          ORDER BY description ASC",
        [$user_id]
    );
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
 * Session start records handler
 *
 * @param mysqli $db
 * @param array $record
 * @param string $lang
 * @param string $username
 * @param string $tg_token
 * @param string $tg_chatid
 * @param string $tg_socks_proxy
 * @param array $translations
 * @param string $clientIp
 * @return array|null message/tg_token/tg_chatid/tg_socks_proxy or null
 */
function processSessionStartRecord(
    mysqli  $db,
    array   $record,
    string  $lang,
    string  $username,
    ?string $tg_token,
    ?string $tg_chatid,
    ?string $tg_socks_proxy,
    array   $translations,
    ?string $clientIp = null,
    ?int    $user_id  = null
): ?array {
    global $memcached, $memcached_connected;

    if ($user_id === null) {
        $user_id = current_user_id();
    }
    if ($user_id === null) {
        throw new RuntimeException('processSessionStartRecord: user_id is null');
    }

    $sesskeys     = [];
    $sessvalues   = [];
    $spv          = [];
    $sessuploadid = $record['session'] ?? null;
    $sesstime     = $record['time']    ?? null;
    $id           = $record['id']      ?? '-';

    if ($sessuploadid === null || $sesstime === null) {
        return null;
    }

    $ip = $clientIp
        ?? $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';

    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip, 2)[0]);
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ip = '0.0.0.0';
    }

    foreach ($record as $key => $value) {
        if ($key === 'profileName') {
            $spv[$key] = $value;
        } elseif (in_array($key, ['session', 'time'], true)) {
            $sesskeys[]   = $key;
            $sessvalues[] = $value;
        }
    }

    $sesskeys[]   = 'timeend';
    $sessvalues[] = $sesstime;

    $sesskeys[]   = 'id';
    $sessvalues[] = $id;

    $sql = "INSERT INTO sessions (user_id, " . quote_names($sesskeys) . ", sessionsize)
            VALUES (" . (int)$user_id . ", " . quote_values($sessvalues) . ", 0)
            ON DUPLICATE KEY UPDATE
                id      = VALUES(id),
                timeend = GREATEST(timeend, VALUES(timeend))";
    $db->execute_query($sql);

    $isNewSessionStart = false;

    if (!empty($spv['profileName']) && $spv['profileName'] !== 'Not Specified') {
        $timeend = round(microtime(true) * 1000);

        $db->execute_query(
            "UPDATE sessions
                SET profileName = ?,
                    ip          = ?,
                    timeend     = GREATEST(timeend, ?)
              WHERE user_id     = ?
                AND session     = ?
                AND profileName = 'Not Specified'",
            [$spv['profileName'], $ip, $timeend, $user_id, $sessuploadid]
        );
        $isNewSessionStart = ($db->affected_rows === 1);

        if (!$isNewSessionStart) {
            $db->execute_query(
                "UPDATE sessions
                    SET profileName = ?,
                        ip          = ?,
                        timeend     = GREATEST(timeend, ?)
                  WHERE user_id = ? AND session = ?",
                [$spv['profileName'], $ip, $timeend, $user_id, $sessuploadid]
            );
        }
    }

    if (!empty($GLOBALS['memcached_connected']) && isset($GLOBALS['memcached'])) {
        try {
            $GLOBALS['memcached']->set("new_session_" . $username, 1, 300);
        } catch (Throwable $e) {
            error_log("Ratel cache error on new-session signal: " . $e->getMessage());
        }
    }

    if ($isNewSessionStart && !empty($tg_token) && !empty($tg_chatid)) {
        $delay = time() - intval($sessuploadid / 1000);

        if ($delay > 30) {
            $formattedDelay = formatDuration((int)$sessuploadid, time() * 1000, $lang);
            $startTime      = intval($sessuploadid / 1000);
            $formattedDate  = date("d.m.Y", $startTime);
            $formattedTime  = date("H:i", $startTime);

            $message = "{$translations[$lang]['upload.start']} {$ip}. "
                     . "{$translations[$lang]['sel.profile']}: {$spv['profileName']} "
                     . "({$translations[$lang]['upload.delayed']} {$formattedDelay}, "
                     . "{$translations[$lang]['upload.start_time']} {$formattedDate} "
                     . "{$translations[$lang]['upload.at']} {$formattedTime})";
        } else {
            $message = "{$translations[$lang]['upload.start']} {$ip}. "
                     . "{$translations[$lang]['sel.profile']}: {$spv['profileName']}";
        }

        return [
            'message'        => $message,
            'tg_token'       => $tg_token,
            'tg_chatid'      => $tg_chatid,
            'tg_socks_proxy' => $tg_socks_proxy,
        ];
    }

    return null;
}

/**
 * @param array $notifications payloads array from processSessionStartRecord()
 */
function sendPendingNotifications(array $notifications): void {
    foreach ($notifications as $n) {
        if (!is_array($n) || empty($n['message'])) {
            continue;
        }

        try {
            notify(
                $n['message'],
                $n['tg_token']       ?? null,
                $n['tg_chatid']      ?? null,
                $n['tg_socks_proxy'] ?? ''
            );
        } catch (Throwable $e) {
            error_log("sendPendingNotifications: notify() error: " . $e->getMessage());
        }
    }
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

/**
 * Add a cache-busting version parameter to a URL.
 *
 * Uses file mtime when the file exists, otherwise container start time,
 * otherwise current time.
 */
function version_url(string $url): string
{
    if (!preg_match('~^(?:https?:)?//~i', $url) && !str_starts_with($url, '/')) {
        $url = '/' . $url;
    }

    $path      = parse_url($url, PHP_URL_PATH) ?: '';
    $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $file_path = $docRoot . '/' . ltrim($path, '/');

    if (is_file($file_path)) {
        $timestamp = filemtime($file_path);
    } elseif (is_file('/proc/1/stat')) {
        $timestamp = filemtime('/proc/1/stat');
    } else {
        $timestamp = time();
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $timestamp;
}

/**
 * Возвращает user_id текущего контекста.
 *
 * Приоритет:
 *   1) явное переопределение через $GLOBALS['user_id']
 *      (нужно в share.php, plot.php, worker.php, где контекст
 *       принадлежит не тому, кто залогинен)
 *   2) $_SESSION['uid']
 *   3) null
 */
function current_user_id(): ?int
{
    if (isset($GLOBALS['user_id']) && $GLOBALS['user_id'] !== null) {
        return (int)$GLOBALS['user_id'];
    }
    if (isset($_SESSION['uid'])) {
        return (int)$_SESSION['uid'];
    }
    return null;
}

function current_sessions_filter(): int
{
    // 1) явное переопределение из share-контекста
    if (isset($GLOBALS['share_sessions_filter'])
        && $GLOBALS['share_sessions_filter'] !== null) {
        return max(1, min(5, (int)$GLOBALS['share_sessions_filter']));
    }
    // 2) обычный залогиненный пользователь
    return max(1, min(5, (int)($_SESSION['sessions_filter'] ?? 1)));
}

/**
 * Декодирует data-столбец logs. Возвращает [] на битом JSON.
 */
function decode_log_data(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $d = json_decode($json, true);
    return is_array($d) ? $d : [];
}

/**
 * Кодирует массив PID-значений в JSON.
 *
 * 'Infinity' → -1
 * Всё остальное → float.
 *
 * Возвращает как минимум '{}' — никогда пустую строку.
 */
function encode_log_data(array $values): string
{
    $out = [];
    foreach ($values as $k => $v) {
        // null / пустая строка / false — данных нет, не пишем
        if ($v === null || $v === '' || $v === false) {
            continue;
        }
        // Infinity → -1
        if (is_string($v) && $v === 'Infinity') {
            $out[$k] = -1;
            continue;
        }
        // Всё остальное пишем как есть, включая 0
        $out[$k] = is_numeric($v) ? (float)$v : $v;
    }

    $encoded = json_encode(
        $out,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    return $encoded === false ? '{}' : $encoded;
}

/**
 * Одиночная вставка datapoint'а в logs.
 *
 * @param mysqli $db
 * @param int    $user_id
 * @param int    $session
 * @param int    $time        (ms)
 * @param array  $pid_values  [ 'k5' => 87.3, 'kc' => 24.5, ... ]
 */
function insert_log_row(mysqli $db, int $user_id, int $session, int $time, array $pid_values): void
{
    $db->execute_query(
        "INSERT IGNORE INTO logs (user_id, session, time, data) VALUES (?,?,?,?)",
        [$user_id, $session, $time, encode_log_data($pid_values)]
    );
}

/**
 * Bulk-вставка datapoint'ов в logs.
 *
 * @param mysqli $db
 * @param int    $user_id
 * @param array  $rows  [
 *                        ['session' => 123, 'time' => 456, 'pids' => ['k5'=>87.3, ...]],
 *                        ...
 *                      ]
 */
function insert_log_rows_bulk(mysqli $db, int $user_id, array $rows): void
{
    if (empty($rows)) {
        return;
    }

    $placeholders = [];
    $values       = [];

    foreach ($rows as $r) {
        $placeholders[] = '(?,?,?,?)';
        $values[]       = $user_id;
        $values[]       = (int)$r['session'];
        $values[]       = (int)$r['time'];
        $values[]       = encode_log_data($r['pids'] ?? []);
    }

    $sql = "INSERT IGNORE INTO logs (user_id, session, time, data) VALUES "
         . implode(',', $placeholders);

    $db->execute_query($sql, $values);
}

/**
 * Дефолтный набор PID'ов для нового юзера.
 *
 * @return array<array{id:string, description:?string, units:?string, populated:int, stream:int, favorite:int}>
 */
function default_pids_data(bool $include_legacy = false): array
{
    $rows = [
        ['k10',     'Mass Air Flow Rate',                'g/sec', 1, 1, 0],
        ['k11',     'Throttle Position (Manifold)',      '%',     1, 1, 0],
        ['k1f',     'Run Time Since Engine Start',       's',     1, 1, 0],
        ['k2100',   'Injector duty',                     '%',     1, 1, 0],
        ['k2101',   'EXT temperature',                   '°C',    1, 1, 0],
        ['k2111',   'Engine Oil Pressure',               'Bar',   1, 1, 0],
        ['k2112',   'Injection time',                    'ms',    1, 1, 0],
        ['k2113',   'Idle Air Control',                  '%',     1, 1, 0],
        ['k2118',   'Motorhours',                        'H',     1, 1, 0],
        ['k2120',   'Boost solenoid duty',               '%',     1, 1, 0],
        ['k2122',   'Fan Status',                        '%',     1, 1, 0],
        ['k2124',   'Gear',                              null,    1, 1, 0],
        ['k2125',   'PG0 Output',                        null,    1, 1, 0],
        ['k2126',   'PG1 Output',                        null,    1, 1, 0],
        ['k21cc',   'Air Fuel Ratio',                    null,    1, 1, 0],
        ['k21e1',   'BS1 Input',                         null,    1, 1, 0],
        ['k21e2',   'BS2 Input',                         null,    1, 1, 0],
        ['k21fa',   'Rollback',                          '⚠',     1, 1, 0],
        ['k46',     'Ambient Air Temp',                  '°C',    1, 1, 0],
        ['k5',      'Engine Coolant Temperature',        '°C',    1, 1, 0],
        ['k5c',     'Engine Oil Temperature',            '°C',    1, 1, 0],
        ['k78',     'EGT',                               '°C',    1, 1, 0],
        ['k2119',   'Fuel Pressure',                     'Bar',   1, 1, 0],
        ['kb',      'Intake Manifold Pressure',          'kPa',   1, 1, 0],
        ['kb4',     'Transmission Temperature (Method 2)','°C',   1, 1, 0],
        ['kc',      'Engine RPM x 100',                  'rpm',   1, 1, 0],
        ['kd',      'Speed (OBD)',                       'km/h',  1, 1, 0],
        ['ke',      'Ignition Advance',                  '°',     1, 1, 0],
        ['kf',      'Intake Air Temperature',            '°C',    1, 1, 0],
        ['kff1001', 'Speed (GPS)',                       'km/h',  1, 1, 0],
        ['kff1005', 'GPS Longitude',                     '°',     0, 0, 0],
        ['kff1006', 'GPS Latitude',                      '°',     0, 0, 0],
        ['kff1007', 'GPS Bearing',                       '°',     0, 0, 0],
        ['kff1202', 'Boost',                             'Bar',   1, 1, 0],
        ['kff1204', 'Trip Distance',                     'km',    1, 1, 0],
        ['kff120c', 'Trip Distance (Stored in Vehicle Profile)', 'km', 1, 1, 0],
        ['kff1214', 'O2 Volts Bank 1 Sensor 1',          'V',     1, 1, 0],
        ['kff1218', 'O2 Volts Bank 2 Sensor 1',          'V',     1, 1, 0],
        ['kff1238', 'Voltage (OBD Adapter)',             'V',     1, 1, 0],
    ];

    if ($include_legacy) {
        $legacy = [
            ['kff122e','0-100kph Time','s',0,0,0], ['kff1278','0-100mph Time','s',0,0,0],
            ['kff124f','0-200kph Time','s',0,0,0], ['kff1277','0-30mph Time','s',0,0,0],
            ['kff122d','0-60mph Time','s',0,0,0],  ['kff122f','1/4 mile Time','s',0,0,0],
            ['kff1230','1/8 mile Time','s',0,0,0], ['kff1264','100-0kph Time','s',0,0,0],
            ['kff1280','100-200kph Time','s',0,0,0],['kff1260','40-60mph Time','s',0,0,0],
            ['kff1265','60-0mph Time','s',0,0,0],  ['kff125e','60-120mph Time','s',0,0,0],
            ['kff1276','60-130mph Time','s',0,0,0],['kff125f','60-80mph Time','s',0,0,0],
            ['kff1261','80-100mph Time','s',0,0,0],['kff1275','80-120kph Time','s',0,0,0],
            ['k47','Absolute Throttle Position B','%',0,0,0],
            ['kff1223','Acceleration Sensor (Total)','g',0,0,0],
            ['kff1220','Acceleration Sensor (X Axis)','g',0,0,0],
            ['kff1221','Acceleration Sensor (Y Axis)','g',0,0,0],
            ['kff1222','Acceleration Sensor (Z Axis)','g',0,0,0],
            ['k49','Accelerator Pedal Position D','%',0,0,0],
            ['k4a','Accelerator Pedal Position E','%',0,0,0],
            ['k4b','Accelerator Pedal Position F','%',0,0,0],
            ['kff124d','Air Fuel Ratio (Commanded)',null,0,0,0],
            ['kff1249','Air Fuel Ratio (Measured)',null,0,0,0],
            ['k12','Air Status',null,0,0,0],
            ['kff129a','Android Device Battery Level','%',0,0,0],
            ['kff1263','Average Trip Speed (Whilst Moving Only)','km/h',0,0,0],
            ['kff1272','Average Trip Speed (Whilst Stopped or Moving)','km/h',0,0,0],
            ['kff1270','Barometer (On Android device)','mb',0,0,0],
            ['k33','Barometric Pressure (From Vehicle)','kPa',0,0,0],
            ['k3c','Catalyst Temperature (Bank 1 Sensor 1)','°C',0,0,0],
            ['k3e','Catalyst Temperature (Bank 1 Sensor 2)','°C',0,0,0],
            ['k3d','Catalyst Temperature (Bank 2 Sensor 1)','°C',0,0,0],
            ['k3f','Catalyst Temperature (Bank 2 Sensor 2)','°C',0,0,0],
            ['kff1258','CO2 (Average)','g/km',0,0,0],
            ['kff1257','CO2 (Instantaneous)','g/km',0,0,0],
            ['k44','Commanded Equivalence Ratio (lambda)',null,0,0,0],
            ['kff126d','Cost per mile/km (Instant)','$/km',0,0,0],
            ['kff126e','Cost per mile/km (Trip)','$/km',0,0,0],
            ['kff126a','Distance to empty (Estimated)','km',0,0,0],
            ['k31','Distance Travelled Since Codes Cleared','km',0,0,0],
            ['k21','Distance Travelled With MIL/CEL Lit','km',0,0,0],
            ['k2c','EGR Commanded','%',0,0,0],
            ['k2d','EGR Error','%',0,0,0],
            ['kff1273','Engine kW (At the Wheels)','kW',0,0,0],
            ['k4','Engine Load','%',0,0,0],
            ['k43','Engine Load (Absolute)','%',0,0,0],
            ['k52','Ethanol Fuel %','%',0,0,0],
            ['k32','Evap System Vapor Pressure','Pa',0,0,0],
            ['k79','Exhaust Gas Temperature Bank 2 Sensor 1','°C',0,0,0],
            ['kff125c','Fuel Cost (Trip)','$',0,0,0],
            ['kff125d','Fuel Flow Rate/Hour','l/hr',0,0,0],
            ['kff125a','Fuel Flow Rate/Minute','cc/min',0,0,0],
            ['k2f','Fuel Level (From Engine ECU)','%',0,0,0],
            ['ka','Fuel Pressure legacy','kPa',0,0,0],
            ['k23','Fuel Rail Pressure','kPa',0,0,0],
            ['k22','Fuel Rail Pressure (Relative to Manifold Vacuum)','kPa',0,0,0],
            ['kff126b','Fuel Remaining (Calculated From Vehicle Profile)','%',0,0,0],
            ['k3','Fuel Status',null,0,0,0],
            ['k7','Fuel Trim Bank 1 Long Term','%',0,0,0],
            ['k14','Fuel Trim Bank 1 Sensor 1','%',0,0,0],
            ['k15','Fuel Trim Bank 1 Sensor 2','%',0,0,0],
            ['k16','Fuel Trim Bank 1 Sensor 3','%',0,0,0],
            ['k17','Fuel Trim Bank 1 Sensor 4','%',0,0,0],
            ['k6','Fuel Trim Bank 1 Short Term','%',0,0,0],
            ['k9','Fuel Trim Bank 2 Long Term','%',0,0,0],
            ['k18','Fuel Trim Bank 2 Sensor 1','%',0,0,0],
            ['k19','Fuel Trim Bank 2 Sensor 2','%',0,0,0],
            ['k1a','Fuel Trim Bank 2 Sensor 3','%',0,0,0],
            ['k1b','Fuel Trim Bank 2 Sensor 4','%',0,0,0],
            ['k8','Fuel Trim Bank 2 Short Term','%',0,0,0],
            ['kff1271','Fuel Used (Trip)','l',0,0,0],
            ['kff1239','GPS Accuracy','m',0,0,0],
            ['kff1010','GPS Altitude','m',0,0,0],
            ['kff123a','GPS Satellites',null,0,0,0],
            ['kff1237','GPS vs OBD Speed Difference','km/h',0,0,0],
            ['kff1226','Horsepower (At the Wheels)','hp',0,0,0],
            ['kff1203','Kilometers Per Litre (Instant)','kpl',0,0,0],
            ['kff5202','Kilometers Per Litre (Long Term Average)','kpl',0,0,0],
            ['kff1207','Litres Per 100 Kilometer (Instant)','l/100km',0,0,0],
            ['kff5203','Litres Per 100 Kilometer (Long Term Average)','l/100km',0,0,0],
            ['kff1201','Miles Per Gallon (Instant)','mpg',0,0,0],
            ['kff5201','Miles Per Gallon (Long Term Average)','mpg',0,0,0],
            ['k24','O2 Sensor1 Equivalence Ratio',null,0,0,0],
            ['k34','O2 Sensor1 Equivalence Ratio (Alternate)',null,0,0,0],
            ['kff1240','O2 Sensor1 Wide-range Voltage','V',0,0,0],
            ['k25','O2 Sensor2 Equivalence Ratio',null,0,0,0],
            ['kff1241','O2 Sensor2 Wide-range Voltage','V',0,0,0],
            ['k26','O2 Sensor3 Equivalence Ratio',null,0,0,0],
            ['kff1242','O2 Sensor3 Wide-range Voltage','V',0,0,0],
            ['k27','O2 Sensor4 Equivalence Ratio',null,0,0,0],
            ['kff1243','O2 Sensor4 Wide-range Voltage','V',0,0,0],
            ['k28','O2 Sensor5 Equivalence Ratio',null,0,0,0],
            ['kff1244','O2 Sensor5 Wide-range Voltage','V',0,0,0],
            ['k29','O2 Sensor6 Equivalence Ratio',null,0,0,0],
            ['kff1245','O2 Sensor6 Wide-range Voltage','V',0,0,0],
            ['k2a','O2 Sensor7 Equivalence Ratio',null,0,0,0],
            ['kff1246','O2 Sensor7 Wide-range Voltage','V',0,0,0],
            ['k2b','O2 Sensor8 Equivalence Ratio',null,0,0,0],
            ['kff1247','O2 Sensor8 Wide-range Voltage','V',0,0,0],
            ['kff1215','O2 Volts Bank 1 Sensor 2','V',0,0,0],
            ['kff1216','O2 Volts Bank 1 Sensor 3','V',0,0,0],
            ['kff1217','O2 Volts Bank 1 Sensor 4','V',0,0,0],
            ['kff1219','O2 Volts Bank 2 Sensor 2','V',0,0,0],
            ['kff121a','O2 Volts Bank 2 Sensor 3','V',0,0,0],
            ['kff121b','O2 Volts Bank 2 Sensor 4','V',0,0,0],
            ['kff1296','Percentage of City Driving','%',0,0,0],
            ['kff1297','Percentage of Highway Driving','%',0,0,0],
            ['kff1298','Percentage of Idle Driving','%',0,0,0],
            ['k5a','Relative Accelerator Pedal Position','%',0,0,0],
            ['k45','Relative Throttle Position','%',0,0,0],
            ['kff124a','Tilt (x)',null,0,0,0],
            ['kff124b','Tilt (y)',null,0,0,0],
            ['kff124c','Tilt (z)',null,0,0,0],
            ['kff1225','Torque','ft-lb',0,0,0],
            ['kfe1805','Transmission Temperature (Method 1)','°C',0,0,0],
            ['kff1206','Trip Average KPL','kpl',0,0,0],
            ['kff1208','Trip Average Litres/100 KM','l/100km',0,0,0],
            ['kff1205','Trip Average MPG','mpg',0,0,0],
            ['kff1266','Trip Time (Since Journey Start)','s',0,0,0],
            ['kff1268','Trip Time (Whilst Moving)','s',0,0,0],
            ['kff1267','Trip Time (Whilst Stationary)','s',0,0,0],
            ['k42','Voltage (Control Module)','V',0,0,0],
            ['kff1269','Volumetric Efficiency (Calculated)','%',0,0,0],
        ];
        $rows = array_merge($rows, $legacy);
    }

    return array_map(fn($r) => [
        'id'          => $r[0],
        'description' => $r[1],
        'units'       => $r[2],
        'populated'   => (int)$r[3],
        'stream'      => (int)$r[4],
        'favorite'    => (int)$r[5],
    ], $rows);
}

/**
 * Засеивает pids для нового юзера.
 * Идемпотентно: INSERT IGNORE, не перезатирает существующие.
 */
function seed_default_pids(mysqli $db, int $user_id, bool $include_legacy = false): int
{
    $rows = default_pids_data($include_legacy);
    if (empty($rows)) return 0;

    $placeholders = [];
    $values       = [];
    foreach ($rows as $r) {
        $placeholders[] = '(?,?,?,?,?,?,?)';
        $values[] = $user_id;
        $values[] = $r['id'];
        $values[] = $r['description'];
        $values[] = $r['units'];
        $values[] = $r['populated'];
        $values[] = $r['stream'];
        $values[] = $r['favorite'];
    }

    $sql = "INSERT IGNORE INTO pids
              (user_id, id, description, units, populated, stream, favorite)
            VALUES " . implode(',', $placeholders);

    $db->execute_query($sql, $values);
    return count($rows);
}

/**
 * Регистрирует PID'ы, которых ещё нет у юзера.
 *
 * Вызывается при записи логов: если клиент прислал новый PID,
 * которого нет в таблице `pids`, он автоматически добавляется
 * с дефолтными настройками (populated=1, stream=1, favorite=0).
 *
 * ВАЖНО: функция НЕ трогает Redis-кэш. Раньше она писала
 * `pids_known_<uid>` и сбрасывала `columns_data_pids_<user>` прямо
 * здесь, но это опасно: функция вызывается ВНУТРИ транзакции
 * (processBulkRecords / processSingleRequest), и при rollback кэш
 * оставался с PID'ами, которых в БД нет. Следующие 60 секунд
 * `ensure_pids_exist` считал бы их известными и не пытался вставить.
 *
 * Теперь caller обязан после успешного commit'а вызвать
 * `cache_pids_after_commit()` с возвращённым массивом.
 *
 * @return array{
 *   inserted: array<string,bool>,  // ключи, реально вставленные в БД
 *   known:    array<string,bool>,  // полный набор известных PID'ов
 *                                  // (existing + inserted) для кэша
 * }
 */
function ensure_pids_exist(mysqli $db, int $user_id, array $pid_keys): array
{
    global $memcached, $memcached_connected;

    $result = ['inserted' => [], 'known' => []];

    if (empty($pid_keys)) {
        return $result;
    }

    $valid = [];
    foreach ($pid_keys as $key) {
        if (!is_string($key)) continue;
        if (!preg_match('/^k[0-9a-fA-F]+$/', $key)) continue;
        $valid[$key] = true;
    }
    if (empty($valid)) {
        return $result;
    }

    $cache_key = "pids_known_{$user_id}";
    $cache_hit = false;
    $known = false;

    if ($memcached_connected) {
        $known = $memcached->get($cache_key);
        $cache_hit = is_array($known);
    }

    if (!$cache_hit) {
        $known = [];
        $r = $db->execute_query("SELECT id FROM pids WHERE user_id = ?", [$user_id]);
        while ($row = $r->fetch_assoc()) {
            $known[$row['id']] = true;
        }
    }

    $missing = [];
    foreach (array_keys($valid) as $pid) {
        if (!isset($known[$pid])) {
            $missing[$pid] = true;
        }
    }

    // Если новых PID'ов нет — возвращаем current known для кэша.
    if (empty($missing)) {
        $result['known'] = $known;
        return $result;
    }

    // Есть новые — INSERT.
    $placeholders = [];
    $values       = [];
    foreach (array_keys($missing) as $pid) {
        $placeholders[] = '(?,?,?,?,?,?)';
        $values[] = $user_id;
        $values[] = $pid;
        $values[] = $pid;
        $values[] = 1;
        $values[] = 1;
        $values[] = 0;
    }

    $db->execute_query(
        "INSERT IGNORE INTO pids
           (user_id, id, description, populated, stream, favorite)
         VALUES " . implode(',', $placeholders),
        $values
    );

    foreach (array_keys($missing) as $pid) {
        $known[$pid] = true;
    }

    $result['inserted'] = $missing;
    $result['known']    = $known;

    return $result;
}

/**
 * Обновляет Redis-кэши после успешного commit'а.
 *
 * Вызывать ТОЛЬКО после $db->commit() (не после rollback!).
 *
 *   - pids_known_<uid>          — полный набор известных PID'ов (TTL 60s);
 *   - columns_data_pids_<user>  — сбрасывается, чтобы get_columns.php
 *                                 перечитал pids из БД с новыми строками.
 *
 * @param array $result  то, что вернул ensure_pids_exist()
 */
function cache_pids_after_commit(int $user_id, string $username, array $result): void
{
    global $memcached, $memcached_connected;

    if (!empty($result['known']) && $memcached_connected) {
        try {
            $memcached->set("pids_known_{$user_id}", $result['known'], 60);
        } catch (Exception $e) {
            error_log("Ratel cache error on PID cache: " . $e->getMessage());
        }
    }

    if (!empty($result['inserted']) && $username !== '') {
        cache_flush(null, "columns_data_pids_{$username}");
    }
}

/**
 * Возвращает валидный код языка из cookie. Если cookie нет или она
 * содержит неизвестный код — 'en'.
 *
 * Используется везде, где сейчас читается $_COOKIE['lang'] напрямую,
 * чтобы не ловить undefined-index при мусорной куке.
 */
function current_lang(): string
{
    global $translations;

    $candidate = $_COOKIE['lang'] ?? 'en';
    return (is_string($candidate)
         && is_array($translations)
         && isset($translations[$candidate]))
        ? $candidate
        : 'en';
}

/**
 * Validate MCU payload: 404 bytes (0..255) + '~' + 13-digit ms timestamp.
 *
 * @return array{0: bool, 1: string}  [ok, error]
 */
function validateMcuData(string $data): array
{
    $len = strlen($data);
    if ($len < 400 || $len > 2048) {
        return [false, 'Bad length'];
    }

    $parts = explode(',', $data);
    if (count($parts) !== 406) {
        return [false, 'Bad parts count'];
    }
    if ($parts[404] !== '~') {
        return [false, 'Missing terminator'];
    }

    for ($i = 0; $i < 404; $i++) {
        $v = $parts[$i];
        if ($v === '' || !ctype_digit($v) || strlen($v) > 3) {
            return [false, "Bad cell $i"];
        }
        if ((int)$v > 255) {
            return [false, "Out of range cell $i"];
        }
    }

    $tsStr = $parts[405];
    if (strlen($tsStr) !== 13 || !ctype_digit($tsStr)) {
        return [false, 'Bad timestamp'];
    }

    $ts  = (int)$tsStr;
    $now = (int)(microtime(true) * 1000);

    // Roughly 2020-01-01 .. now + 1 day
    if ($ts < 1577836800000 || $ts > $now + 86400000) {
        return [false, 'Timestamp out of range'];
    }

    return [true, ''];
}

function maintenance_flag_path(): string
{
    return dirname(__DIR__) . '/_maintenance';
}

function is_maintenance(): bool
{
    return file_exists(maintenance_flag_path());
}

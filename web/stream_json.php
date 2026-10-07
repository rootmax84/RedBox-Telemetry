<?php
/*
    USAGE EXAMPLE:
    curl https://your_site/stream_json.php -H "Authorization: Bearer $username_token"
    returns the latest user log entry checked in the PID menu as Stream

      [
        {
          "id": "kff1238",
          "description": "Voltage (OBD Adapter)",
          "value": 13.70,
          "unit": "V",
          "time": 1720767600011
        },
        ...
      ]
*/

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/methods.php';

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With,Authorization,Content-Type');
header('Access-Control-Max-Age: 86400');

allowMethods('GET');
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Maintenance check — before any DB or auth work
if (file_exists('maintenance')) {
    http_response_code(423);
    echo json_encode(['error' => 'Server under maintenance']);
    exit;
}

// Bearer token must be present
$token = getBearerToken();
if (empty($token)) {
    // Show usage without token
    header('Content-Type: text/plain; charset=utf-8', true);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ? 'https'
        : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/stream_json.php', PHP_URL_PATH) ?: '/stream_json.php';

    $current_url = $scheme . '://' . $host . $path;
    $usage  = "RedBox Telemetry Stream JSON API\n";
    $usage .= "================================\n\n";
    $usage .= "Returns the latest user log entry checked in the PID menu as a JSON stream.\n\n";
    $usage .= "USAGE EXAMPLE\n";
    $usage .= "-------------\n";
    $usage .= "curl ${current_url} -H \"Authorization: Bearer \$username_token\"\n\n";
    $usage .= "RESPONSE\n";
    $usage .= "--------\n";
    $usage .= "[\n";
    $usage .= "  {\n";
    $usage .= "    \"id\": \"kff1238\",\n";
    $usage .= "    \"description\": \"Voltage (OBD Adapter)\",\n";
    $usage .= "    \"value\": 13.70,\n";
    $usage .= "    \"unit\": \"V\",\n";
    $usage .= "    \"time\": 1720767600011\n";
    $usage .= "  },\n";
    $usage .= "  ...\n";
    $usage .= "]\n";

    echo $usage;
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['torque_logged_in'] = true;
require_once __DIR__ . '/src/db.php';

// Auth via Bearer token
$cache_key = "user_api_data_" . $token;
$user_data = false;

if ($memcached_connected) {
    $user_data = $memcached->get($cache_key);

    // Sanity: кэш старого формата без 'id' — инвалидируем и игнорируем,
    // чтобы не возвращать 500 при апгрейде.
    if (is_array($user_data) && empty($user_data['id'])) {
        try {
            $memcached->delete($cache_key);
        } catch (Exception $e) {
            error_log("Ratel cache error on stream api: " . $e->getMessage());
        }
        $user_data = false;
    }
}

if ($user_data === false) {
    $userqry = $db->execute_query(
        "SELECT id, user, s, api_gps FROM users WHERE token=?",
        [$token]
    );
    if ($userqry->num_rows) {
        $user_data = $userqry->fetch_assoc();
        if ($memcached_connected) {
            try {
                $memcached->set($cache_key, $user_data, $db_cache_ttl ?? 3600);
            } catch (Exception $e) {
                error_log(sprintf("Ratel cache error on api: %s (Code: %d)", $e->getMessage(), $e->getCode()));
            }
        }
    }
}

$access  = 0;
$user_id = 0;

if ($user_data) {
    $user_id = (int)($user_data['id'] ?? 0);

    if ($user_id > 0) {
        $user   = $user_data["user"];
        $limit  = $user_data["s"];
        $gps    = $user_data["api_gps"];
        $access = 1;
    }
}

if (!$user_data || $access !== 1 || (int)$limit === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Rate limit (only for valid users)
$rate_limit_key = "api_rate_limit_" . $user;
$max_api_requests_per_second = $max_api_requests_per_second ?? 10;

if ($memcached_connected) {
    try {
        $count = $memcached->incrementWithTtl($rate_limit_key, 1, 1);

        if ($count !== false && $count > $max_api_requests_per_second) {
            http_response_code(429);
            error_log("API spammer detected: " . $user);
            echo json_encode(['error' => 'Too many requests']);
            exit;
        }
    } catch (Exception $e) {
        error_log(sprintf("Ratel cache error on api: %s (Code: %d)", $e->getMessage(), $e->getCode()));
    }
}

// Fetch the latest data record
$r = $db->execute_query(
    "SELECT time, data FROM logs WHERE user_id = ? ORDER BY time DESC LIMIT 1",
    [$user_id]
);
if (!$r->num_rows) {
    echo json_encode(['error' => 'No data available']);
    exit;
}

// Fetch data with or without GPS data
$cache_key_api_pids = "api_pids_" . $user;
$pids = false;

if ($memcached_connected) {
    $pids = $memcached->get($cache_key_api_pids);
}

if ($pids === false) {
    $result = getPidsQuery($db, 'pids', $gps, $user_id);
    if ($result->num_rows) {
        $pids = [];
        while ($row = $result->fetch_array()) {
            $pids[] = $row;
        }
        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_api_pids, $pids, $db_cache_meta_ttl ?? 300);
            } catch (Exception $e) {
                error_log(sprintf("Ratel cache error on api: %s (Code: %d)", $e->getMessage(), $e->getCode()));
            }
        }
    }
}

$id = $db->execute_query(
    "SELECT id FROM sessions WHERE user_id = ? ORDER BY timeend DESC LIMIT 1",
    [$user_id]
)->fetch_row()[0] ?? null;

$cache_key_api_conv = "api_conv_" . $user;
$user_settings = false;

if ($memcached_connected) {
    $user_settings = $memcached->get($cache_key_api_conv);
}

if ($user_settings === false) {
    $setqry = $db->execute_query(
        "SELECT speed,temp,pressure,boost FROM users WHERE user=?",
        [$user]
    );
    if ($setqry->num_rows) {
        $user_settings = $setqry->fetch_row();
        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_api_conv, $user_settings, $db_cache_ttl ?? 3600);
            } catch (Exception $e) {
                error_log(sprintf("Ratel cache error on api: %s (Code: %d)", $e->getMessage(), $e->getCode()));
            }
        }
    }
}

if ($user_settings === false) {
    http_response_code(500);
    echo json_encode(['error' => 'User settings not found']);
    exit;
}

[$speed, $temp, $pressure, $boost] = $user_settings;

if (!is_array($pids) || empty($pids)) {
    echo json_encode(['error' => 'Select PIDs to show in Functions']);
    exit;
}

$pid = $des = $unit = [];
foreach ($pids as $key) {
    $pid[]  = $key['id'];
    $des[]  = $key['description'];
    $unit[] = $key['units'];
}

$unitMappings = [
    'speed'    => ['km to miles' => ['mph', 'miles'], 'miles to km' => ['km/h', 'km']],
    'temp'     => ['Celsius to Fahrenheit' => '°F', 'Fahrenheit to Celsius' => '°C'],
    'pressure' => ['Psi to Bar' => 'Bar', 'Bar to Psi' => 'Psi'],
    'boost'    => ['Psi to Bar' => 'Bar', 'Bar to Psi' => 'Psi']
];

$data = [];
$raw  = $r->fetch_assoc();
$time_raw = (int)$raw['time'];
$row  = decode_log_data($raw['data']);

for ($i = 0; $i < count($pid); $i++) {
    $currentPid  = $pid[$i];
    $currentDes  = $des[$i];
    $currentUnit = $unit[$i];

    $spd_unit   = $unitMappings['speed'][$speed][0]     ?? $currentUnit;
    $trip_unit  = $unitMappings['speed'][$speed][1]     ?? $currentUnit;
    $temp_unit  = $unitMappings['temp'][$temp]          ?? $currentUnit;
    $press_unit = $unitMappings['pressure'][$pressure]  ?? $currentUnit;
    $boost_unit = $unitMappings['boost'][$boost]        ?? $currentUnit;

    $value = $row[$currentPid] ?? null;

    $formattedValue = formatValue($currentPid, $value, $currentDes, $speed, $temp, $pressure, $boost, $id);
    $formattedUnit  = formatUnit($currentPid, $currentDes, $spd_unit, $trip_unit, $temp_unit, $press_unit, $boost_unit, $currentUnit);

    // Значение может быть:
    //   null    — данных нет
    //   float   — обычный числовой PID
    //   string  — текстовый статус (OFF/ON/MAX/OK/N/A/h:m:s)
    if ($formattedValue === null) {
        $outValue = null;
    } elseif (is_numeric($formattedValue)) {
        $outValue = (float) $formattedValue;
    } else {
        $outValue = (string) $formattedValue;
    }

    $data[] = [
        'id'          => $currentPid,
        'description' => $currentDes,
        'value'       => $outValue,
        'unit'        => $formattedUnit,
        'time'        => $time_raw,
    ];
}

echo json_encode($data);

function formatValue($pid, $value, $des, $speed, $temp, $pressure, $boost, $id) {
    if ($value === null || !is_numeric($value)) {
        return null;
    }

    return match ($pid) {
        'kff1202' => pressure_conv(sprintf("%.2f", $value), $boost, $id),
        'k2122' => match ((int)$value) {
            0 => 'OFF',
            1 => 'ON',
            default => $value >= 95 ? 'MAX' : $value,
        },
        'k1f' => sprintf("%02d:%02d:%02d", (int)($value/3600), ((int)($value/60))%60, $value%60),
        'k2118' => intval($value),
        'k2124' => $value == 255 ? 'N/A' : $value,
        'k21fa' => $value == 0 ? 'OK' : $value,
        'kff1238', 'ke', 'kff1214', 'kff1218', 'k21cc', 'k2111' => sprintf("%.2f", $value),
        'kff1204', 'kff120c' => speed_conv($value, $speed, $id),
        'kc' => sprintf("%.2f", $value/100),
        'k11' => round($value),
        default => match (true) {
            stripos($des, 'Pressure') !== false && !in_array($pid, ['kb', 'k33', 'k32', 'ka', 'k23', 'k22']) => pressure_conv(sprintf("%.2f", $value), $pressure, $id),
            stripos($des, 'Temp') !== false || stripos($des, 'EGT') !== false => temp_conv($value, $temp, $id),
            stripos($des, 'Speed') !== false => speed_conv($value, $speed, $id),
            default => $value,
        },
    };
}

function formatUnit($pid, $des, $spd_unit, $trip_unit, $temp_unit, $press_unit, $boost_unit, $defaultUnit) {
    return match ($pid) {
        'k1f' => 'h:m:s',
        'kff1202' => $boost_unit,
        'kff1204', 'kff120c' => $trip_unit,
        default => match (true) {
            stripos($des, 'Pressure') !== false && !in_array($pid, ['kb', 'k33', 'k32', 'ka', 'k23', 'k22']) => $press_unit,
            stripos($des, 'Temp') !== false || stripos($des, 'EGT') !== false => $temp_unit,
            stripos($des, 'Speed') !== false => $spd_unit,
            default => $defaultUnit,
        },
    };
}

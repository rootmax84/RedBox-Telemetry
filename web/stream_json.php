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
      ],
*/

require_once('helpers.php');

//Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With,Authorization,Content-Type');
header('Access-Control-Max-Age: 86400');

//Allow GET only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

//Check if token header is present and non-empty then go to database
$token = getBearerToken();
if (!empty($token)) {

    $_SESSION['torque_logged_in'] = true;
    require_once('db.php');

    header('Content-Type: application/json');
    header('Cache-Control: no-cache');

    $cache_key = "user_api_data_" . $token;
    $user_data = false;

    if ($memcached_connected) {
        $user_data = $memcached->get($cache_key);
    }

    //Check auth via Bearer token
    if ($user_data === false) {
        $userqry = $db->execute_query("SELECT user, s, api_gps FROM $db_users WHERE token=?", [$token]);
        if ($userqry->num_rows) {
            $access = 1;
            $user_data = $userqry->fetch_assoc();
            if ($memcached_connected) {
                try {
                    $memcached->set($cache_key, $user_data, $db_memcached_ttl ?? 3600);
                } catch (Exception $e) {
                    error_log(sprintf("Memcached error on api: %s (Code: %d)", $e->getMessage(), $e->getCode()));
                }
            }
        } else {
            $access = 0;
        }
    }

    if ($user_data) {
        $user = $user_data["user"];
        $limit = $user_data["s"];
        $gps = $user_data["api_gps"];
        $access = 1;
    }
} else {
    $access = 0;
}

$rate_limit_key = "api_rate_limit_" . $user;
$max_api_requests_per_second = $max_api_requests_per_second ?? 10;

if ($memcached_connected) {
    $current_requests = $memcached->get($rate_limit_key);
    if ($current_requests === false) {
        try {
            $memcached->set($rate_limit_key, 1, 1);
        } catch (Exception $e) {
            error_log(sprintf("Memcached error on api: %s (Code: %d)", $e->getMessage(), $e->getCode()));
        }
    } else {
        if ($current_requests >= $max_api_requests_per_second) {
            http_response_code(429);
            error_log("API spammer detected: " . $user);
            echo json_encode(['error' => 'Too many requests']);
            exit;
        } else {
            try {
                $memcached->increment($rate_limit_key, 1);
            } catch (Exception $e) {
                error_log(sprintf("Memcached error on api: %s (Code: %d)", $e->getMessage(), $e->getCode()));
            }
        }
    }
}

if ($access != 1 || $limit == 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
} else {
    $db_table = $user.$db_log_prefix;
    $db_sessions_table = $user.$db_sessions_prefix;
    $db_pids_table = $user.$db_pids_prefix;
}

// Fetch the latest data record
$r = $db->query("SELECT * FROM $db_table ORDER BY time DESC LIMIT 1");
if (!$r->num_rows) {
    echo json_encode(['error' => 'No data available']);
    exit;
}

// Fetch data with or without GPS data
$d = getPidsQuery($db, $db_pids_table, $gps);

$id = $db->query("SELECT id FROM $db_sessions_table ORDER BY timeend DESC LIMIT 1")->fetch_row()[0];

$setqry = $db->execute_query("SELECT speed,temp,pressure,boost FROM $db_users WHERE user=?", [$user])->fetch_row();
[$speed, $temp, $pressure, $boost] = $setqry;

if (!$d->num_rows) {
    echo json_encode(['error' => 'Select PIDs to show in Functions']);
    exit;
}

$pid = $des = $unit = [];
while ($key = $d->fetch_array()) {
    $pid[] = $key['id'];
    $des[] = $key['description'];
    $unit[] = $key['units'];
}

$unitMappings = [
    'speed' => ['km to miles' => ['mph', 'miles'], 'miles to km' => ['km/h', 'km']],
    'temp' => ['Celsius to Fahrenheit' => '°F', 'Fahrenheit to Celsius' => '°C'],
    'pressure' => ['Psi to Bar' => 'Bar', 'Bar to Psi' => 'Psi'],
    'boost' => ['Psi to Bar' => 'Bar', 'Bar to Psi' => 'Psi']
];

$data = [];
$row = $r->fetch_assoc();

for ($i = 0; $i < count($pid); $i++) {
    $currentPid = $pid[$i];
    $currentDes = $des[$i];
    $currentUnit = $unit[$i];

    $spd_unit = $unitMappings['speed'][$speed][0] ?? $currentUnit;
    $trip_unit = $unitMappings['speed'][$speed][1] ?? $currentUnit;
    $temp_unit = $unitMappings['temp'][$temp] ?? $currentUnit;
    $press_unit = $unitMappings['pressure'][$pressure] ?? $currentUnit;
    $boost_unit = $unitMappings['boost'][$boost] ?? $currentUnit;

    $value = $row[$currentPid] ?? '-';

    $formattedValue = formatValue($currentPid, $value, $currentDes, $speed, $temp, $pressure, $boost, $id);
    $formattedUnit = formatUnit($currentPid, $currentDes, $spd_unit, $trip_unit, $temp_unit, $press_unit, $boost_unit, $currentUnit);

    $data[] = [
        'id' => $currentPid,
        'description' => $currentDes,
        'value' => (float) $formattedValue,
        'unit' => $formattedUnit,
        'time' => (int) $row['time']
    ];
}

echo json_encode($data);

function formatValue($pid, $value, $des, $speed, $temp, $pressure, $boost, $id) {
    return match ($pid) {
        'kff1202' => pressure_conv(sprintf("%.2f", $value), $boost, $id),
        'k2122' => match ($value) {
            0 => 'OFF',
            1 => 'ON',
            default => $value >= 95 ? 'MAX' : $value,
        },
        'k1f' => sprintf("%02d:%02d:%02d", (int)($value/3600), ((int)($value/60))%60, $value%60),
        'k2118' => intval($value),
        'k2124' => $value == 255 ? 'N/A' : $value,
        'k21fa' => $value == 0 ? 'OK' : $value,
        // Some PIDs need formatting to two decimal places
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

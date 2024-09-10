<?php

/*
    USAGE EXAMPLE:
    curl https://your_site/stream_json.php -H "Authorization: Bearer $username_token"
    returns the latest user log entry checked in the PID menu as Stream without GPS data

    {
      "data": [
        {
          "id": "kff1238",
          "description": "Voltage (OBD Adapter)",
          "value": "13.70",
          "unit": "V"
        },
        ...
      ],
      "lastRecordDate": "1720767600011"
    }
*/

require_once('token_functions.php');
require_once('parse_functions.php');

//Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With,Authorization,Content-Type');
header('Access-Control-Max-Age: 86400');

//Allow GET only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.0 405 Method Not Allowed');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

//Check if token header is present and non empty than go to database
$token = getBearerToken();
if (!empty($token)) {

$_SESSION['torque_logged_in'] = true;
require_once('db.php');

header('Content-Type: application/json');
header('Cache-Control: no-cache');

 //Check auth via Bearer token
$userqry = $db->execute_query("SELECT user, s FROM $db_users WHERE token=?", [$token]);
  if (!$userqry->num_rows) $access = 0;
  else {
    $row = $userqry->fetch_assoc();
    $user = $row["user"];
    $limit = $row["s"];
    $access = 1;
  }
} else $access = 0;

if ($access != 1 || $limit == 0) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['error' => 'Access denied']);
    exit;
} else {
    $db_table = $user.$db_log_prefix;
    $db_sessions_table = $user.$db_sessions_prefix;
    $db_pids_table = $user.$db_pids_prefix;
}

$r = $db->query("SELECT * FROM $db_table ORDER BY time DESC LIMIT 1");
if (!$r->num_rows) {
    echo json_encode(['error' => 'No data available']);
    exit;
}

$d = $db->query("SELECT id,description,units FROM $db_pids_table WHERE stream = 1");
$id = $db->query("SELECT id FROM $db_sessions_table ORDER BY timeend DESC LIMIT 1")->fetch_row()[0];

$setqry = $db->execute_query("SELECT speed,temp,pressure,boost FROM $db_users WHERE user=?", [$username])->fetch_row();
[$speed, $temp, $pressure, $boost] = $setqry;

if (!$d->num_rows) {
    echo json_encode(['message' => 'Select PIDs to show in Functions']);
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
        'value' => $formattedValue,
        'unit' => $formattedUnit
    ];
}

$lastRecordDate = outputLastRecordDate($row['time']);

echo json_encode(['data' => $data, 'lastRecordDate' => $lastRecordDate]);

function formatValue($pid, $value, $des, $speed, $temp, $pressure, $boost, $id) {
    switch ($pid) {
        case 'kff1202':
            return pressure_conv(sprintf("%.2f", $value), $boost, $id);
        case 'kff1238':
            return sprintf("%.2f", $value);
        case 'k2122':
            if ($value == 0) return 'OFF';
            if ($value == 1) return 'ON';
            if ($value >= 95) return 'MAX';
            return $value;
        case 'k1f':
            return sprintf("%02d:%02d:%02d", (int)$value/3600, ((int)$value/60)%60, $value%60);
        case 'k2118':
            return intval($value);
        case 'k2124':
            return $value == 255 ? 'N/A' : $value;
        case 'k21fa':
            return $value == 0 ? 'OK' : $value;
        case 'ke':
        case 'kff1214':
        case 'kff1218':
            return sprintf("%.2f", $value);
        case 'kff1204':
        case 'kff120c':
            return speed_conv($value, $speed, $id);
        case 'kc':
            return sprintf("%.2f", $value/100);
        case 'k11':
            return round($value);
        default:
            if (stripos($des, 'Pressure') !== false && !in_array($pid, ['kb', 'k33', 'k32', 'ka', 'k23', 'k22'])) {
                return pressure_conv(sprintf("%.2f", $value), $pressure, $id);
            }
            if (stripos($des, 'Temp') !== false || stripos($des, 'EGT') !== false) {
                return temp_conv($value, $temp, $id);
            }
            if (stripos($des, 'Speed') !== false) {
                return speed_conv(round($value), $speed, $id);
            }
            return $value;
    }
}

function formatUnit($pid, $des, $spd_unit, $trip_unit, $temp_unit, $press_unit, $boost_unit, $defaultUnit) {
    switch ($pid) {
        case 'k1f':
            return 'h:m:s';
        case 'kff1202':
            return $boost_unit;
        case 'kff1204':
        case 'kff120c':
            return $trip_unit;
        default:
            if (stripos($des, 'Pressure') !== false && !in_array($pid, ['kb', 'k33', 'k32', 'ka', 'k23', 'k22'])) {
                return $press_unit;
            }
            if (stripos($des, 'Temp') !== false || stripos($des, 'EGT') !== false) {
                return $temp_unit;
            }
            if (stripos($des, 'Speed') !== false) {
                return $spd_unit;
            }
            return $defaultUnit;
    }
}

function outputLastRecordDate($time) {
    if ($time != '') {
        return $time;
    } else {
        return "No data available";
    }
}
?>
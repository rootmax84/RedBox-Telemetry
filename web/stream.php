<?php
require_once __DIR__ . '/src/db.php';
include_once __DIR__ . '/timezone.php';
require_once __DIR__ . '/src/helpers.php';
include_once __DIR__ . '/translations.php';
require_once __DIR__ . '/src/methods.php';

if (isset($_SESSION['admin'])) {
    header("Refresh:0; url=/");
    exit;
}

allowMethods('GET');
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$user_id    = current_user_id();
$session_id = filter_var($_GET['id'] ?? null, FILTER_SANITIZE_NUMBER_INT) ?: null;

$query  = "SELECT time, data FROM logs WHERE user_id = ?";
$params = [$user_id];
if ($session_id) {
    $query   .= " AND session = ?";
    $params[] = $session_id;
}
$query .= " ORDER BY time DESC LIMIT 1";

$r = $db->execute_query($query, $params);

if (!$r->num_rows) {
    echo "data: <tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-warning'>"
       . $translations[current_lang()]['nodata']
       . "</span></td></tr>\n\nretry: 5000\n\n";
    die;
}

/* ─── PIDs для stream ─── */

$cache_key_s = "stream_pids_s_" . $username;
$s_data = false;

if ($memcached_connected) {
    $s_data = $memcached->get($cache_key_s);
}

if ($s_data === false) {
    $s_result = $db->execute_query(
        "SELECT id, description, units FROM pids
          WHERE user_id = ? AND (stream = 1 OR id IN ('kff1005','kff1006','kff1007'))
          ORDER BY description ASC",
        [$user_id]
    );
    if ($s_result->num_rows) {
        $s_data = [];
        while ($row = $s_result->fetch_array()) $s_data[] = $row;
        if ($memcached_connected) {
            try { $memcached->set($cache_key_s, $s_data, $db_cache_meta_ttl ?? 300); }
            catch (Exception $e) { error_log("Ratel cache error on stream (s): " . $e->getMessage()); }
        }
    }
}

$cache_key_d = "stream_pids_d_" . $username;
$d_data = false;

if ($memcached_connected) {
    $d_data = $memcached->get($cache_key_d);
}

if ($d_data === false) {
    $d_result = $db->execute_query(
        "SELECT id, description, units FROM pids WHERE user_id = ? AND stream = 1",
        [$user_id]
    );
    if ($d_result->num_rows) {
        $d_data = [];
        while ($row = $d_result->fetch_array()) $d_data[] = $row;
        if ($memcached_connected) {
            try { $memcached->set($cache_key_d, $d_data, $db_cache_meta_ttl ?? 300); }
            catch (Exception $e) { error_log("Ratel cache error on stream (d): " . $e->getMessage()); }
        }
    }
}

/* ─── Session id (RedManage / TorqueLog) ─── */

if ($session_id) {
    $id = $db->execute_query(
        "SELECT id FROM sessions WHERE user_id = ? AND session = ?",
        [$user_id, $session_id]
    )->fetch_row()[0] ?? null;
} else {
    $id = $db->execute_query(
        "SELECT id FROM sessions WHERE user_id = ? ORDER BY timeend DESC LIMIT 1",
        [$user_id]
    )->fetch_row()[0] ?? null;
}

/* ─── Units settings ─── */

$cache_key_api_conv = "stream_conv_" . $username;
$user_settings = false;

if ($memcached_connected) {
    $user_settings = $memcached->get($cache_key_api_conv);
}

if ($user_settings === false) {
    $setqry = $db->execute_query(
        "SELECT speed,temp,pressure,boost FROM users WHERE user=?",
        [$username]
    );
    if ($setqry->num_rows) {
        $user_settings = $setqry->fetch_row();
        if ($memcached_connected) {
            try { $memcached->set($cache_key_api_conv, $user_settings, $db_cache_ttl ?? 3600); }
            catch (Exception $e) { error_log("Ratel cache error on api: " . $e->getMessage()); }
        }
    }
}

if ($user_settings === false) {
    echo "data: <tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-warning'>"
       . $translations[current_lang()]['nodata']
       . "</span></td></tr>\n\nretry: 5000\n\n";
    die;
}

[$speed, $temp, $pressure, $boost] = $user_settings;

if (empty($s_data) || empty($d_data)) {
    echo "data: <tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-default'>"
       . $translations[current_lang()]['stream.empty']
       . "</span></td></tr>\n\nretry: 5000\n\n";
    die;
}

$pid = $des = $unit = [];
foreach ($s_data as $key) {
    $pid[]  = $key['id'];
    $des[]  = $key['description'];
    $unit[] = $key['units'];
}

$unitMappings = [
    'speed'    => ['km to miles' => ['mph', 'miles'], 'miles to km' => ['km/h', 'km']],
    'temp'     => ['Celsius to Fahrenheit' => '°F', 'Fahrenheit to Celsius' => '°C'],
    'pressure' => ['Psi to Bar' => 'Bar', 'Bar to Psi' => 'Psi'],
    'boost'    => ['Psi to Bar' => 'Bar', 'Bar to Psi' => 'Psi'],
];

$raw       = $r->fetch_assoc();
$time_raw  = (int)$raw['time'];
$row       = decode_log_data($raw['data']);

if ($time_raw > 0) {
    $seconds = intval($time_raw / 1000);
    if (time() - $seconds < 30) {
        setcookie("plot", true, [
            'expires'  => time() + 30,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
    }
}

for ($i = 0; $i < count($pid); $i++) {
    $currentPid  = $pid[$i];
    $currentDes  = $des[$i];
    $currentUnit = $unit[$i];

    $spd_unit   = $unitMappings['speed'][$speed][0]  ?? $currentUnit;
    $trip_unit  = $unitMappings['speed'][$speed][1]  ?? $currentUnit;
    $temp_unit  = $unitMappings['temp'][$temp]       ?? $currentUnit;
    $press_unit = $unitMappings['pressure'][$pressure] ?? $currentUnit;
    $boost_unit = $unitMappings['boost'][$boost]     ?? $currentUnit;

    if ($currentPid === 'kff1005') {
        $data = "<tr hidden><td id='lon'>" . ($row['kff1005'] ?? 0) . "</td></tr>";
    } elseif ($currentPid === 'kff1006') {
        $data = "<tr hidden><td id='lat'>" . ($row['kff1006'] ?? 0) . "</td></tr>";
    } elseif ($currentPid === 'kff1007') {
        $data = "<tr hidden><td id='hdg'>" . ($row['kff1007'] ?? 0) . "</td></tr>";
    } else {
        $value = $row[$currentPid] ?? null;
        $data  = "<tr><td>{$currentDes}</td>";
        if ($value === null) {
            $data .= "<td title='No data available' tabindex='0'>-</td>";
        } else {
            $data .= formatValue($currentPid, $value, $currentDes, $speed, $temp, $pressure, $boost, $id);
        }
        $data .= formatUnit($currentPid, $currentDes, $spd_unit, $trip_unit, $temp_unit, $press_unit, $boost_unit, $currentUnit);
        $data .= "</tr>";
    }

    echo "data: {$data}\n";
}

outputLastRecordDate($time_raw, $live_data_rate);

/* ─── Helpers ─── */

function formatValue($pid, $value, $des, $speed, $temp, $pressure, $boost, $id) {
    if ($value === null || !is_numeric($value)) {
        return null;
    }

    return match ($pid) {
        'kff1202' => "<td><samp>" . pressure_conv(sprintf("%.2f", $value), $boost, $id) . "</samp></td>",
        'k2122' => match ((int)$value) {
            0 => "<td><samp>OFF</samp></td>",
            1 => "<td><samp>ON</samp></td>",
            default => $value >= 95 ? "<td><samp>MAX</samp></td>" : "<td><samp>{$value}</samp></td>",
        },
        'k1f' => "<td><samp>" . sprintf("%02d:%02d:%02d", (int)$value/3600, ((int)$value/60)%60, $value%60) . "</samp></td>",
        'k2118' => "<td><samp>" . intval($value) . "</samp></td>",
        'k2124' => $value == 255 ? "<td><samp>N/A</samp></td>" : "<td><samp>{$value}</samp></td>",
        'k21fa' => "<td><samp id='rollback' onclick='xhrResponse(calculate({$value}))'" . ($value != 0 ? " style='color:red;font-weight:bold'" : "") . ">" . ($value == 0 ? "OK" : $value) . "</samp></td>",
        'kff1238','ke','kff1214','kff1218','k21cc','k2111' => "<td><samp>" . sprintf("%.2f", $value) . "</samp></td>",
        'kff1204','kff120c' => "<td><samp>" . speed_conv($value, $speed, $id) . "</samp></td>",
        'kc' => "<td><samp>" . sprintf("%.2f", $value/100) . "</samp></td>",
        'k11' => "<td><samp>" . round($value) . "</samp></td>",
        default => match (true) {
            stripos($des, 'Pressure') !== false && !in_array($pid, ['kb','k33','k32','ka','k23','k22']) => "<td><samp>" . pressure_conv(sprintf("%.2f", $value), $pressure, $id) . "</samp></td>",
            stripos($des, 'Temp') !== false || stripos($des, 'EGT') !== false => "<td><samp>" . temp_conv($value, $temp, $id) . "</samp></td>",
            stripos($des, 'Speed') !== false => "<td id='spd'><samp>" . speed_conv($value, $speed, $id) . "</samp></td>",
            default => "<td><samp>{$value}</samp></td>",
        },
    };
}

function formatUnit($pid, $des, $spd_unit, $trip_unit, $temp_unit, $press_unit, $boost_unit, $defaultUnit) {
    return match ($pid) {
        'k1f' => "<td><samp>h:m:s</samp></td>",
        'kff1202' => "<td><samp>{$boost_unit}</samp></td>",
        'kff1204','kff120c' => "<td><samp>{$trip_unit}</samp></td>",
        default => match (true) {
            stripos($des, 'Pressure') !== false && !in_array($pid, ['kb','k33','k32','ka','k23','k22']) => "<td><samp>{$press_unit}</samp></td>",
            stripos($des, 'Temp') !== false || stripos($des, 'EGT') !== false => "<td><samp>{$temp_unit}</samp></td>",
            stripos($des, 'Speed') !== false => "<td id='spd-unit'><samp>{$spd_unit}</samp></td>",
            default => "<td><samp>{$defaultUnit}</samp></td>",
        },
    };
}

function outputLastRecordDate($time, $rate) {
    global $translations;
    if ($time != '') {
        $seconds = intval($time / 1000);
        $time_format = $_COOKIE['timeformat'] == "12" ? "d.m.Y h:i:sa" : "d.m.Y H:i:s";
        $data = "<tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-default'>" . $translations[current_lang()]['stream.last'] . date($time_format, $seconds) . "</span></td></tr>";
    } else {
        $data = "<tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-warning'>" . $translations[current_lang()]['nodata'] . "</span></td></tr>";
    }

    echo "data: {$data}\n";

    if (isset($seconds) && time() - $seconds < 10) {
        echo "retry: {$rate}\n\n";
    } else {
        echo "retry: 5000\n\n";
    }
}

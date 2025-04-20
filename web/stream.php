<?php

require_once('db.php');
include('timezone.php');
require_once('parse_functions.php');
include_once('translations.php');

if (isset($_SESSION['admin'])) {
    header("Refresh:0; url=.");
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$session_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$placeholders = $session_id ? [$session_id] : [];
$query = "SELECT * FROM $db_table";

if ($session_id) {
    $query .= " WHERE session=?";
}
$query .= " ORDER BY time DESC LIMIT 1";

$r = $db->execute_query($query, $placeholders);

if (!$r->num_rows) {
    echo "data: <tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-warning'>" . $translations[$_COOKIE['lang']]['nodata'] . "</span></td></tr>\n\nretry: 5000\n\n";
    die;
}

$s = $db->query("SELECT id,description,units FROM $db_pids_table WHERE stream = 1 OR id IN ('kff1005', 'kff1006') ORDER by description ASC");
$d = $db->query("SELECT id,description,units FROM $db_pids_table WHERE stream = 1");

if ($session_id) {
    $id = $db->execute_query("SELECT id FROM $db_sessions_table WHERE session=?", [$session_id])->fetch_row()[0];
} else  $id = $db->query("SELECT id FROM $db_sessions_table ORDER BY timeend DESC LIMIT 1")->fetch_row()[0];
$setqry = $db->execute_query("SELECT speed,temp,pressure,boost FROM $db_users WHERE user=?", [$username])->fetch_row();
[$speed, $temp, $pressure, $boost] = $setqry;

if (!$s->num_rows || !$d->num_rows) {
    echo "data: <tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-default'>" . $translations[$_COOKIE['lang']]['stream.empty'] . "</span></td></tr>\n\nretry: 5000\n\n";
    die;
}

$pid = $des = $unit = [];
while ($key = $s->fetch_array()) {
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

if ($r->num_rows) {
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

        if ($currentPid === 'kff1005') {
            $data = "<tr hidden><td id='lon'>{$row[$currentPid]}</td></tr>";
        } elseif ($currentPid === 'kff1006') {
            $data = "<tr hidden><td id='lat'>{$row[$currentPid]}</td></tr>";
        } else {
            $data = "<tr>";
            $data .= "<td>{$currentDes}</td>";

            $value = $row[$currentPid];
            if ($value === '') {
                $data .= "<td title='No data available' tabindex='0'>-</td>";
            } else {
                $data .= formatValue($currentPid, $value, $currentDes, $speed, $temp, $pressure, $boost, $id);
            }

            $data .= formatUnit($currentPid, $currentDes, $spd_unit, $trip_unit, $temp_unit, $press_unit, $boost_unit, $currentUnit);
            $data .= "</tr>";
        }

        echo "data: {$data}\n";
    }

    outputLastRecordDate($row['time'], $live_data_rate);
}

function formatValue($pid, $value, $des, $speed, $temp, $pressure, $boost, $id) {
    return match ($pid) {
        'kff1202' => "<td><samp>" . pressure_conv(sprintf("%.2f", $value), $boost, $id) . "</samp></td>",
        'k2122' => match ($value) {
            0 => "<td><samp>OFF</samp></td>",
            1 => "<td><samp>ON</samp></td>",
            default => $value >= 95 ? "<td><samp>MAX</samp></td>" : "<td><samp>{$value}</samp></td>",
        },
        'k1f' => "<td><samp>" . sprintf("%02d:%02d:%02d", (int)$value/3600, ((int)$value/60)%60, $value%60) . "</samp></td>",
        'k2118' => "<td><samp>" . intval($value) . "</samp></td>",
        'k2124' => $value == 255 ? "<td><samp>N/A</samp></td>" : "<td><samp>{$value}</samp></td>",
        'k21fa' => "<td><samp id='rollback'" . ($value != 0 ? " style='color:red;font-weight:bold'" : "") . ">" . ($value == 0 ? "OK" : $value) . "</samp></td>",
        'kff1238', 'ke', 'kff1214', 'kff1218', 'k21cc', 'k2111' => "<td><samp>" . sprintf("%.2f", $value) . "</samp></td>",
        'kff1204', 'kff120c' => "<td><samp>" . speed_conv($value, $speed, $id) . "</samp></td>",
        'kc' => "<td><samp>" . sprintf("%.2f", $value/100) . "</samp></td>",
        'k11' => "<td><samp>" . round($value) . "</samp></td>",
        default => match (true) {
            stripos($des, 'Pressure') !== false && !in_array($pid, ['kb', 'k33', 'k32', 'ka', 'k23', 'k22']) => "<td><samp>" . pressure_conv(sprintf("%.2f", $value), $pressure, $id) . "</samp></td>",
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
        'kff1204', 'kff120c' => "<td><samp>{$trip_unit}</samp></td>",
        default => match (true) {
            stripos($des, 'Pressure') !== false && !in_array($pid, ['kb', 'k33', 'k32', 'ka', 'k23', 'k22']) => "<td><samp>{$press_unit}</samp></td>",
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
        $data = "<tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-default'>" . $translations[$_COOKIE['lang']]['stream.last'] . date($time_format, $seconds) . "</span></td></tr>";
    } else {
        $data = "<tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-warning'>" . $translations[$_COOKIE['lang']]['nodata'] . "</span></td></tr>";
    }
    echo "data: {$data}\n";
    if (isset($seconds) && time() - $seconds < 10) {
        echo "retry: {$rate}\n\n";
        setcookie("plot", true, time() + 10, "/");
    } else {
        echo "retry: 5000\n\n";
    }
}
?>

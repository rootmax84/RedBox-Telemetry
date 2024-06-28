<?php
ini_set("zlib.output_compression", 1); //Enable gzip compression

require_once('db.php');
include('timezone.php');
require_once('parse_functions.php');

if (isset($_SESSION['admin'])) {
    header("Refresh:0; url=.");
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$r = $db->query("SELECT * FROM $db_table ORDER BY time DESC LIMIT 1");
if (!$r->num_rows) die;

$s = $db->query("SELECT id,description,units FROM $db_pids_table WHERE stream = 1 OR id IN ('kff1005', 'kff1006') ORDER by description ASC");
$d = $db->query("SELECT id,description,units FROM $db_pids_table WHERE stream = 1");
$id = $db->query("SELECT id FROM $db_sessions_table ORDER BY timeend DESC LIMIT 1")->fetch_row()[0];

$setqry = $db->execute_query("SELECT speed,temp,pressure,boost FROM $db_users WHERE user=?", [$username])->fetch_row();
[$speed, $temp, $pressure, $boost] = $setqry;

if (!$s->num_rows || !$d->num_rows) {
    echo "data: <tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-default'>Select PIDs to show in Functions ↓</span></td></tr>\n\nretry: 5000\n\n";
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
    switch ($pid) {
        case 'kff1202':
            return "<td><samp>" . pressure_conv(sprintf("%.2f", $value), $boost, $id) . "</samp></td>";
        case 'kff1238':
            return "<td><samp>" . sprintf("%.2f", $value) . "</samp></td>";
        case 'k2122':
            if ($value == 0) return "<td><samp>OFF</samp></td>";
            if ($value == 1) return "<td><samp>ON</samp></td>";
            if ($value >= 95) return "<td><samp>MAX</samp></td>";
            return "<td><samp>{$value}</samp></td>";
        case 'k1f':
            return "<td><samp>" . sprintf("%02d:%02d:%02d", (int)$value/3600, ((int)$value/60)%60, $value%60) . "</samp></td>";
        case 'k2118':
            return "<td><samp>" . intval($value) . "</samp></td>";
        case 'k2124':
            return $value == 255 ? "<td><samp>N/A</samp></td>" : "<td><samp>{$value}</samp></td>";
        case 'k21fa':
            $style = $value != 0 ? " style='color:red;font-weight:bold'" : "";
            return "<td><samp id='rollback'{$style}>" . ($value == 0 ? "OK" : $value) . "</samp></td>";
        case 'ke':
        case 'kff1214':
        case 'kff1218':
            return "<td><samp>" . sprintf("%.2f", $value) . "</samp></td>";
        case 'kff1204':
        case 'kff120c':
            return "<td><samp>" . speed_conv($value, $speed, $id) . "</samp></td>";
        case 'kc':
            return "<td><samp>" . sprintf("%.2f", $value/100) . "</samp></td>";
        case 'k11':
            return "<td><samp>" . round($value) . "</samp></td>";
        default:
            if (stripos($des, 'Pressure') !== false && !in_array($pid, ['kb', 'k33', 'k32', 'ka', 'k23', 'k22'])) {
                return "<td><samp>" . pressure_conv(sprintf("%.2f", $value), $pressure, $id) . "</samp></td>";
            }
            if (stripos($des, 'Temp') !== false || stripos($des, 'EGT') !== false) {
                return "<td><samp>" . temp_conv($value, $temp, $id) . "</samp></td>";
            }
            if (stripos($des, 'Speed') !== false) {
                return "<td id='spd'><samp>" . speed_conv(round($value), $speed, $id) . "</samp></td>";
            }
            return "<td><samp>{$value}</samp></td>";
    }
}

function formatUnit($pid, $des, $spd_unit, $trip_unit, $temp_unit, $press_unit, $boost_unit, $defaultUnit) {
    switch ($pid) {
        case 'k1f':
            return "<td><samp>h:m:s</samp></td>";
        case 'kff1202':
            return "<td><samp>{$boost_unit}</samp></td>";
        case 'kff1204':
        case 'kff120c':
            return "<td><samp>{$trip_unit}</samp></td>";
        default:
            if (stripos($des, 'Pressure') !== false && !in_array($pid, ['kb', 'k33', 'k32', 'ka', 'k23', 'k22'])) {
                return "<td><samp>{$press_unit}</samp></td>";
            }
            if (stripos($des, 'Temp') !== false || stripos($des, 'EGT') !== false) {
                return "<td><samp>{$temp_unit}</samp></td>";
            }
            if (stripos($des, 'Speed') !== false) {
                return "<td id='spd-unit'><samp>{$spd_unit}</samp></td>";
            }
            return "<td><samp>{$defaultUnit}</samp></td>";
    }
}

function outputLastRecordDate($time, $rate) {
    if ($time != '') {
        $seconds = intval($time / 1000);
        $time_format = $_COOKIE['timeformat'] == "12" ? "d.m.Y h:i:sa" : "d.m.Y H:i:s";
        $data = "<tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-default'>Last record at: " . date($time_format, $seconds) . "</span></td></tr>";
    } else {
        $data = "<tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-warning'>No data available</span></td></tr>";
    }
    echo "data: {$data}\n";
    if (isset($seconds) && time() - $seconds < 10) {
        echo "retry: {$rate}\n\n";
    } else {
        echo "retry: 5000\n\n";
    }
}
?>

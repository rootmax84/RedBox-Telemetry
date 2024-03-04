<?php
ini_set("zlib.output_compression", 1); //Enable gzip compression

require_once('db.php');
include ("timezone.php");
require_once('parse_functions.php');

if (isset($_SESSION['admin'])) header("Refresh:0; url=/");

 header('Content-Type: text/event-stream');
 header('Cache-Control: no-cache');

 $r = $db->query("SELECT * FROM $db_table ORDER BY time DESC LIMIT 1"); //Select last row from raw data table
 $s = $db->query("SELECT id,description,units FROM $db_pids_table WHERE stream = 1 ORDER by description ASC");  //Check if pid in stream
 $id = $db->query("SELECT id FROM $db_sessions_table ORDER BY timeend DESC LIMIT 1")->fetch_row()[0];

 //Get units conversion settings
 $setqry = $db->execute_query("SELECT speed,temp,pressure,boost FROM $db_users WHERE user=?", [$username])->fetch_row();
 $speed = $setqry[0];
 $temp = $setqry[1];
 $pressure = $setqry[2];
 $boost = $setqry[3];

  if ($s->num_rows) {
   while ($key = $s->fetch_array()) {
    $pid[] = $key['id'];
    $des[] = $key['description'];
    $unit[] = $key['units'];
   }
  }
else {
    echo "data: <tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-default'>Select PIDs to show in Functions ↓</span></td></tr>\n\nretry: 5000\n\n";
    die;
}

  if ($r->num_rows) {
   while ($row = $r->fetch_assoc()) {

    //Print last record from logs table
    for ($i = 0; $i < count($pid); $i++) {

	switch ($speed) {
	    case "km to miles":
	    $spd_unit = "mph";
	    $trip_unit = "miles";
	    break;
	    case "miles to km":
	    $spd_unit = "km/h";
	    $trip_unit = "km";
	    break;
	    default:
	    $spd_unit = $unit[$i];
	    $trip_unit = $unit[$i];
	    break;
	}
	switch ($temp) {
	    case "Celsius to Fahrenheit":
	    $temp_unit = "°F";
	    break;
	    case "Fahrenheit to Celsius":
	    $temp_unit = "°C";
	    break;
	    default:
	    $temp_unit = $unit[$i];
	    break;
	}
	switch ($pressure) {
	    case "Psi to Bar":
	    $press_unit = "Bar";
	    break;
	    case "Bar to Psi":
	    $press_unit = "Psi";
	    break;
	    default:
	    $press_unit = $unit[$i];
	    break;
	}
	switch ($boost) {
	    case "Psi to Bar":
	    $boost_unit = "Bar";
	    break;
	    case "Bar to Psi":
	    $boost_unit = "Psi";
	    break;
	    default:
	    $boost_unit = $unit[$i];
	    break;
	}

	$data = "<tr>";
	$data.= "<td>".$des[array_search($pid[$i],$pid)]."</td>"; //pid description
	if ($row[$pid[$i]] == '') $data.= "<td title='No data available' tabindex='0'>-</td>"; // '-' if no data
	else if ($pid[$i] == 'kff1202') $data.= "<td><samp>".pressure_conv(sprintf("%.2f", $row[$pid[$i]]), $boost, $id)."</samp></td>"; // boost conversion
	else if (substri_count($des[$i], 'Pressure') > 0 && $pid[$i] != 'kb' && $pid[$i] != 'k33' && $pid[$i] != 'k32' && $pid[$i] != 'ka' && $pid[$i] != 'k23' && $pid[$i] != 'k22') $data.= "<td><samp>".pressure_conv(sprintf("%.2f", $row[$pid[$i]]), $pressure, $id)."</samp></td>"; // pressures conversion. Skip (k)Pa things
	else if (substri_count($des[$i], 'Temp') > 0) $data.= "<td><samp>".temp_conv($row[$pid[$i]], $temp, $id)."</samp></td>"; // temp conversion
	else if (substri_count($des[$i], 'Speed') > 0) $data.= "<td><samp>".speed_conv(round($row[$pid[$i]]), $speed, $id)."</samp></td>"; // speed conversion
	else if ($pid[$i] == 'k2111') $data.= "<td><samp>".sprintf("%.2f", $row[$pid[$i]])."</samp></td>"; // oil pressure 2 digits
	else if ($pid[$i] == 'k2119') $data.= "<td><samp>".sprintf("%.2f", $row[$pid[$i]])."</samp></td>"; // fuel pressure 2 digits
	else if ($pid[$i] == 'k2122' && $row[$pid[$i]] == 0) $data.= "<td><samp>OFF</samp></td>"; // fan off state
	else if ($pid[$i] == 'k2122' && $row[$pid[$i]] == 1) $data.= "<td><samp>ON</samp></td>"; // fan sw on state
	else if ($pid[$i] == 'k2122' && $row[$pid[$i]] >= 95) $data.= "<td><samp>MAX</samp></td>"; // fan pwm max power
	else if ($pid[$i] == 'k21cc') $data.= "<td><samp>".sprintf("%.2f", $row[$pid[$i]])."</samp></td>"; // afr 2 digits
	else if ($pid[$i] == 'kff1238') $data.= "<td><samp>".sprintf("%.2f", $row[$pid[$i]])."</samp></td>"; // voltmeter 2 digits
	else if ($pid[$i] == 'k1f') $data.= "<td><samp>".sprintf("%02d", (int)$row[$pid[$i]]/60/60).':'.sprintf("%02d", (int)($row[$pid[$i]]/60)%60).':'.sprintf("%02d", $row[$pid[$i]]%'60')."</samp></td>"; // formating runtime output
	else if ($pid[$i] == 'k2118') $data.= "<td><samp>".intval($row[$pid[$i]])."</samp></td>"; // trim motorhours value
	else if ($pid[$i] == 'k2124' && $row[$pid[$i]] == 255) $data.= "<td><samp>N/A</samp></td>"; // Gear disabled state
	else if ($pid[$i] == 'k21fa' && $row[$pid[$i]] == 0) $data.= "<td><samp id='rollback'>OK</samp></td>"; // OK rollback
	else if ($pid[$i] == 'k21fa' && $row[$pid[$i]] != 0) $data.= "<td><samp id='rollback' style='color:red;font-weight:bold'>".$row[$pid[$i]]."</samp></td>"; // Coloring active rollback code
	else if ($pid[$i] == 'ke') $data.= "<td><samp>".sprintf("%.1f", $row[$pid[$i]])."</samp></td>"; // timing advance 1 digit
	else if ($pid[$i] == 'kff1214') $data.= "<td><samp>".sprintf("%.2f", $row[$pid[$i]])."</samp></td>"; // O2S1 2 digits
	else if ($pid[$i] == 'kff1218') $data.= "<td><samp>".sprintf("%.2f", $row[$pid[$i]])."</samp></td>"; // O2S2 2 digits
	else if ($pid[$i] == 'kff1204' || $pid[$i] == 'kff120c') $data.= "<td><samp>".speed_conv($row[$pid[$i]], $speed, $id)."</samp></td>"; // Trip Distance (ODO) conversion
	else if ($pid[$i] == 'kc') $data.= "<td><samp>".sprintf("%.2f", $row[$pid[$i]]/100)."</samp></td>"; // RPM divide by 100
	else if ($pid[$i] == 'k11') $data.= "<td><samp>".round($row[$pid[$i]])."</samp></td>"; // Throttle position round
	else $data.= "<td><samp>".$row[$pid[$i]]."</samp></td>"; // REST DATA
	if ($pid[$i] == 'k1f') 	$data.= "<td><samp>h:m:s</samp></td>"; // runtime custom unit
	else if ($pid[$i] == 'kff1202') $data.= "<td><samp>".$boost_unit."</samp></td>"; // boost unit
	else if (substri_count($des[$i], 'Pressure') > 0 && $pid[$i] != 'kb' && $pid[$i] != 'k33' && $pid[$i] != 'k32' && $pid[$i] != 'ka' && $pid[$i] != 'k23' && $pid[$i] != 'k22') $data.= "<td><samp>".$press_unit."</samp></td>"; // pressures unit
	else if (substri_count($des[$i], 'Temp') > 0) $data.= "<td><samp>".$temp_unit."</samp></td>"; // temp unit
	else if (substri_count($des[$i], 'Speed') > 0) $data.= "<td><samp>".$spd_unit."</samp></td>"; // speed unit
	else if ($pid[$i] == 'kff1204' || $pid[$i] == 'kff120c' ) $data.= "<td><samp>".$trip_unit."</samp></td>"; // Trip/ODO unit
	else $data.= "<td><samp>".$unit[array_search($pid[$i],$pid)]."</samp></td>"; // REST PID UNITS
	$data.= "</tr>";
	echo "data: {$data}\n";
    }

    if ($row['time'] != ''){ //Last record date
	$seconds = intval($row['time'] / 1000);
	$time_format = $_COOKIE['timeformat'] == "12" ? "d.m.Y h:i:sa" : "d.m.Y H:i:s";
	$data = "<tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-default'>Last record at: ".date($time_format, $seconds)."</span></td></tr>";
    }
    else $data = "<tr><td colspan='3' style='text-align:center;font-size:14px'><span class='label label-warning'>No data available</span></td></tr>";
    echo "data: {$data}\n";
    if (isset($seconds) && time() - $seconds < 10)
        echo "retry: {$live_data_rate}\n\n";
    else
        echo "retry: 5000\n\n";
 }
}
?>

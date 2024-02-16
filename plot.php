<?php
require_once('db.php');
require_once('parse_functions.php');
if (!isset($sids) && !isset($_SESSION['admin'])) { //this is to default to get the session list and default to json output if called directly
	require_once("./get_sessions.php");
	$json = [];
}
// Convert data units
//Trip round
$trip = function ($trip) { return round($trip,1); };

//Trip (Stored in profile) round
$trip_s = function ($trip_s) { return round($trip_s,1); };

//gx rpm devider
$temp_rpm_dev = function ($rpm_dev) { return $rpm_dev/100; };

//gx EOP round
$tmp_eop = function ($eop) { return round($eop,2); };

//gx FP round
$tmp_fp = function ($fp) { return round($fp,1); };

//gx INJ duty round
$tmp_injd = function ($injd) { return round($injd,0); };

//gx MHS round
$tmp_mhs = function ($mhs) { return round($mhs,0); };

//gx VLT round
$tmp_vlt = function ($vlt) { return round($vlt,2); };

//gx ERT seconds to minutes
$tmp_ert = function ($ert) { return round($ert/60,0); };

//gx gear 255 to 0 (BSx inputs Logic mode)
$tmp_gear = function ($gear) { return $gear == '255' ? '0' : $gear; };

// Grab the session number
if (isset($_GET["id"]) && $sids && in_array($_GET["id"], $sids)) {
    $session_id = $db->real_escape_string($_GET['id']);
    $id = $db->execute_query("SELECT id FROM $db_sessions_table WHERE session=?", [$session_id])->fetch_row()[0];

    //Get units conversion settings
    $setqry = $db->execute_query("SELECT speed,temp,pressure FROM $db_users WHERE user=?", [$username])->fetch_row();
    $speed = $setqry[0];
    $temp = $setqry[1];
    $pressure = $setqry[2];

    // Get the torque key->val mappings
    $keyquery = $db->query("SELECT id,description,units FROM $db_pids_table");
    $keyarr = [];
    while($row = $keyquery->fetch_assoc()) {
      $keyarr[$row['id']] = array($row['description'], $row['units']);
    }
	$selectstring = "time";
	$i = 1;
	while ( isset($_GET["s$i"]) ) {

	if ($_GET["s$i"] == ''){header('Location:/');} //gx

		${'v' . $i} = $_GET["s$i"];
		$selectstring = $selectstring.",".quote_name(${'v' . $i});
		$i = $i + 1;
	}

	// Get data for session
	try {
	    $sessionqry = $db->execute_query("SELECT $selectstring FROM $db_table WHERE session=? ORDER BY time DESC", [$session_id]);
	} catch (Exception $e) { /*No data for selected pid*/ }
	if (!$sessionqry->num_rows) die;
	while($row = $sessionqry->fetch_assoc()) {
	    $i = 1;
		switch ($speed) {
		    case "km to miles":
		    $spd_unit = " (mph)";
		    $trip_unit = " (miles)";
		    break;
		    case "miles to km":
		    $spd_unit = " (km/h)";
		    $trip_unit = " (km)";
		    break;
		    default:
		    $spd_unit = ' ('.$keyarr[${'v' . $i}][1].')';
		    $trip_unit = ' ('.$keyarr[${'v' . $i}][1].')';
		    break;
		}
		switch ($temp) {
		    case "Celsius to Fahrenheit":
		    $temp_unit = " (°F)";
		    break;
		    case "Fahrenheit to Celsius":
		    $temp_unit = " (°C)";
		    break;
		    default:
		    $temp_unit = ' ('.$keyarr[${'v' . $i}][1].')';
		    break;
		}
		switch ($pressure) {
		    case "Psi to Bar":
		    $press_unit = " (Bar)";
		    break;
		    case "Bar to Psi":
		    $press_unit = " (Psi)";
		    break;
		    default:
		    $press_unit = ' ('.$keyarr[${'v' . $i}][1].')';
		    break;
		}
		while (isset(${'v' . $i})) {
	        if (substri_count($keyarr[${'v' . $i}][0], "Speed") > 0) {
	            $x = speed_conv($row[${'v' . $i}], $speed, $id);
	            ${'v' . $i . '_measurand'} = $spd_unit;
	        } elseif (substri_count($keyarr[${'v' . $i}][0], "Distance") > 0) {
	            $x = speed_conv($row[${'v' . $i}], $speed, $id);
	            ${'v' . $i . '_measurand'} = $trip_unit;
	        } elseif (substri_count($keyarr[${'v' . $i}][0], "Temp") > 0) {
	            $x = temp_conv($row[${'v' . $i}], $temp, $id);
	            ${'v' . $i . '_measurand'} = $temp_unit;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Boost Solenoid Duty") > 0) {
		     $x = $row[${'v' . $i}];
		     ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Boost") > 0) {
		     $x = pressure_conv($row[${'v' . $i}], $pressure, $id);
		     ${'v' . $i . '_measurand'} = $press_unit;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Pressure") > 0) {
		     $x = pressure_conv($row[${'v' . $i}], $pressure, $id);
		     ${'v' . $i . '_measurand'} = $press_unit;
		} elseif (substri_count($keyarr[${'v' . $i}][1], "rpm") > 0) {
		    $x = $temp_rpm_dev ($row[${'v' . $i}]);
		    ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Engine Oil Pressure") > 0) {
		    $x = $tmp_eop ($row[${'v' . $i}]);
		    ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Fuel Pressure") > 0) {
		    $x = $tmp_fp ($row[${'v' . $i}]);
		    ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Injector duty") > 0) {
		    $x = $tmp_injd ($row[${'v' . $i}]);
		    ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Motorhours") > 0) {
		    $x = $tmp_mhs ($row[${'v' . $i}]);
		    ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Voltage (OBD Adapter)") > 0) {
		    $x = $tmp_vlt ($row[${'v' . $i}]);
		    ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Run Time Since Engine Start") > 0) {
		    $x = $tmp_ert ($row[${'v' . $i}]);
		    ${'v' . $i . '_measurand'} = ' (m)';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Trip Distance") > 0) {
		    $x = $trip ($row[${'v' . $i}]);
		    ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Trip Distance (Stored in Vehicle Profile)") > 0) {
		    $x = $trip_s ($row[${'v' . $i}]);
		    ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Gear") > 0) {
		    $x = $tmp_gear ($row[${'v' . $i}]);
		    ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
	        } else {
	            $x = $row[${'v' . $i}];
	            ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
	        }
	        ${'d' . $i}[] = array($row['time'], $x);
			${'spark' . $i}[] = $x;
			$i = $i + 1;
		}
	}
	$i = 1;	
	while (isset(${'v' . $i})) {
	    ${'v' . $i . '_label'} = '"'.$keyarr[${'v' . $i}][0].${'v' . $i . '_measurand'}.'"';
	    ${'sparkdata' . $i} = implode(",", array_reverse(${'spark' . $i}));
	    ${'max' . $i} = round(max(${'spark' . $i}), 2);
	    ${'min' . $i} = round(min(${'spark' . $i}), 2);
	    ${'avg' . $i} = round(average(${'spark' . $i}), 2);
		$i = $i + 1;
	}
}
if (isset($json)) {
	$i = 1;	
	while (isset(${'v' . $i})) {
	    $json[] = [${'v' . $i},$keyarr[${'v' . $i}][0].${'v' . $i . '_measurand'},${'d' . $i},${'sparkdata' . $i},${'max' . $i},${'min' . $i},${'avg' . $i}];
		$i = $i + 1;
	}
	if (sizeof($json)) print_r(json_encode($json/*,JSON_PRETTY_PRINT/**/));
}
?>

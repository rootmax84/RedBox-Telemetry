<?php
require_once('db.php');
require_once('parse_functions.php');
if (!isset($sids) && !isset($_SESSION['admin'])) { //this is to default to get the session list and default to json output if called directly
	require_once("./get_sessions.php");
	$json = [];
}
// Convert data units
// TODO: Use the userDefault fields to do these conversions dynamically

//gx
//psi to bar conversion
if ($use_bar) {
    $temp_boost = function ($boost) { return round($boost/14.504,2); };
    $boost_measurand = ' (Bar)';
}
else{
     $temp_boost = function ($boost) { return $boost; };
     $boost_measurand = ' (Psi)';
}

//Trip round
$trip = function ($trip) { return round($trip,1); };
$trip_measurand = ' (Km)';

//Trip (Stored in profile) round
$trip_s = function ($trip_s) { return round($trip_s,1); };
$trip_s_measurand = ' (Km)';

//RedManage boost in bar only
$red_boost = function ($boost) { return $boost; };
$red_measurand = ' (Bar)';

//gx boost solenoid duty workaround
$temp_duty = function ($duty) { return $duty; };
$duty_measurand = ' (%)';

//gx boost setpoint kpa -> bar
$temp_setpoint = function ($setpoint) { return $setpoint/100; };
$setpoint_measurand = ' (Bar)';

//gx rpm devider
$temp_rpm_dev = function ($rpm_dev) { return $rpm_dev/100; };
$rpm_measurand = ' (RPM)';

//gx EOP round
$tmp_eop = function ($eop) { return round($eop,2); };
$eop_measurand = ' (Bar)';

//gx FP round
$tmp_fp = function ($fp) { return round($fp,1); };
$fp_measurand = ' (Bar)';

//gx INJ duty round
$tmp_injd = function ($injd) { return round($injd,0); };
$injd_measurand = ' (%)';

//gx MHS round
$tmp_mhs = function ($mhs) { return round($mhs,0); };
$mhs_measurand = ' (h)';

//gx VLT round
$tmp_vlt = function ($vlt) { return round($vlt,2); };
$vlt_measurand = ' (V)';

//gx ERT seconds to minutes
$tmp_ert = function ($ert) { return round($ert/60,0); };
$ert_measurand = ' (m)';

//gx gear 255 to 0 (BSx inputs Logic mode)
$tmp_gear = function ($gear) { return $gear == '255' ? '0' : $gear; };
$gear_measurand = ' ()';

//Speed conversion
if (!$source_is_miles && $use_miles) {
    $speed_factor = 0.621371;
    $speed_measurand = ' (mph)';
} elseif ($source_is_miles && $use_miles) {
    $speed_factor = 1.0;
    $speed_measurand = ' (mph)';
} elseif ($source_is_miles && !$use_miles) {
    $speed_factor = 1.609344;
    $speed_measurand = ' (km/h)';
} else {
    $speed_factor = 1.0;
    $speed_measurand = ' (km/h)';
}

//Temperature Conversion
if (!$source_is_fahrenheit && $use_fahrenheit) { //From Celsius to Fahrenheit
    $temp_func = function ($temp) { return $temp*9.0/5.0+32.0; };
    $temp_measurand = ' (°F)';
} elseif ($source_is_fahrenheit && $use_fahrenheit) { //Just Fahrenheit
    $temp_func = function ($temp) { return $temp; };
    $temp_measurand = ' (°F)';
} elseif ($source_is_fahrenheit && !$use_fahrenheit) { //From Fahrenheit to Celsius
    $temp_func = function ($temp) { return ($temp-32.0)*5.0/9.0; };
    $temp_measurand = ' (℃)';
} else { //Just Celsius
    $temp_func = function ($temp) { return $temp; };
    $temp_measurand = ' (℃)';
}

// Grab the session number
if (isset($_GET["id"]) && $sids && in_array($_GET["id"], $sids)) {
    $session_id = $db->real_escape_string($_GET['id']);
    $id = $db->execute_query("SELECT id FROM $db_sessions_table WHERE session=?", [$session_id])->fetch_row()[0];

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
		while (isset(${'v' . $i})) {
	        if (substri_count($keyarr[${'v' . $i}][0], "Speed") > 0) {
	            $x = intval($row[${'v' . $i}]) * $speed_factor;
	            ${'v' . $i . '_measurand'} = $speed_measurand;
	        } elseif (substri_count($keyarr[${'v' . $i}][0], "Temp") > 0) {
	            $x = $temp_func ( floatval($row[${'v' . $i}]) );
	            ${'v' . $i . '_measurand'} = $temp_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Boost setpoint") > 0) {
		    $x = $temp_setpoint ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $setpoint_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Boost solenoid duty") > 0) {
		    $x = $temp_duty ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $duty_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Boost") > 0) {
		    if ($id == "RedManage") {
		     $x = $red_boost ( floatval($row[${'v' . $i}]) );
		     ${'v' . $i . '_measurand'} = $red_measurand;
		    }
		    else {
		     $x = $temp_boost ( floatval($row[${'v' . $i}]) );
		     ${'v' . $i . '_measurand'} = $boost_measurand;
		    }
		} elseif (substri_count($keyarr[${'v' . $i}][1], "rpm") > 0) {
		    $x = $temp_rpm_dev ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $rpm_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Engine Oil Pressure") > 0) {
		    $x = $tmp_eop ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $eop_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Fuel Pressure") > 0) {
		    $x = $tmp_fp ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $fp_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Injector duty") > 0) {
		    $x = $tmp_injd ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $injd_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Motorhours") > 0) {
		    $x = $tmp_mhs ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $mhs_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Voltage (OBD Adapter)") > 0) {
		    $x = $tmp_vlt ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $vlt_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Run Time Since Engine Start") > 0) {
		    $x = $tmp_ert ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $ert_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Trip Distance") > 0) {
		    $x = $trip ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $trip_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Trip Distance (Stored in Vehicle Profile)") > 0) {
		    $x = $trip_s ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $trip_s_measurand;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Gear") > 0) {
		    $x = $tmp_gear ( floatval($row[${'v' . $i}]) );
		    ${'v' . $i . '_measurand'} = $gear_measurand;
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

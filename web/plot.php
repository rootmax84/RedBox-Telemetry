<?php
$sid = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$uid = filter_input(INPUT_GET, 'uid', FILTER_SANITIZE_NUMBER_INT);
$key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if ($sid && $uid && $key) {
    $_SESSION['torque_logged_in'] = true;
    require_once('db.php');
    $user_data = $db->execute_query("SELECT user, share, sessions_filter FROM $db_users WHERE id=?", [$uid])->fetch_assoc();
    $username = $user_data['user'];
    $share_key = $user_data['share'];
    $_SESSION['sessions_filter'] = $user_data['sessions_filter'];

    if (!$username || $share_key !== $key) exit;
    $db_table = $username.$db_log_prefix;
    $db_sessions_table = $username.$db_sessions_prefix;
    $db_pids_table = $username.$db_pids_prefix;
} else {
    require_once('db.php');
}

require_once('parse_functions.php');
$json = [];

// Convert data units
//gx rpm devider
$temp_rpm_dev = function ($rpm_dev) { return round($rpm_dev/100, 2); };

//gx MHS round
$tmp_mhs = function ($mhs) { return round($mhs,0); };

//gx VLT round
$tmp_vlt = function ($vlt) { return round($vlt,2); };

//gx ERT seconds to minutes
$tmp_ert = function ($ert) { return round($ert/60,0); };

//gx gear 255 to 0 (BSx inputs Logic mode)
$tmp_gear = function ($gear) { return $gear == '255' ? '0' : $gear; };

// Grab the session number
if (isset($_GET["id"])) {
    $session_id = $db->real_escape_string($_GET['id']);
    $cached_timestamp = null;
    $current_timestamp = getLastUpdateTimestamp($db, $session_id, $db_sessions_table);

    // id
    $cache_key_id = "session_id_{$session_id}";
    $id = false;

    if ($memcached_connected) {
        $cached_id_data = $memcached->get($cache_key_id);
        if ($memcached->getResultCode() === Memcached::RES_SUCCESS && is_array($cached_id_data)) {
            list($id, $cached_timestamp) = $cached_id_data;
        }
    }

    if ($id === false || $cached_timestamp !== $current_timestamp) {
        $id = $db->execute_query("SELECT id FROM $db_sessions_table WHERE session=?", [$session_id])->fetch_row()[0];

        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_id, [$id, $current_timestamp], 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    //Get units conversion settings
    $cache_key_settings = "user_settings_{$username}";
    $setqry = false;

    if ($memcached_connected) {
        $cached_settings_data = $memcached->get($cache_key_settings);
        if ($memcached->getResultCode() === Memcached::RES_SUCCESS && is_array($cached_settings_data)) {
            list($setqry, $cached_timestamp) = $cached_settings_data;
        }
    }

    if ($setqry === false || $cached_timestamp !== $current_timestamp) {
        $setqry = $db->execute_query("SELECT speed,temp,pressure,boost FROM $db_users WHERE user=?", [$username])->fetch_row();

        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_settings, [$setqry, $current_timestamp], 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    $speed = $setqry[0];
    $temp = $setqry[1];
    $pressure = $setqry[2];
    $boost = $setqry[3];

    // Get the torque key->val mappings
    $cache_key_pids = "pids_mapping_{$username}";
    $keyarr = false;

    if ($memcached_connected) {
        $cached_pids_data = $memcached->get($cache_key_pids);
        if ($memcached->getResultCode() === Memcached::RES_SUCCESS && is_array($cached_pids_data)) {
            list($keyarr, $cached_timestamp) = $cached_pids_data;
        }
    }

    if ($keyarr === false || $cached_timestamp !== $current_timestamp) {
        $keyquery = $db->query("SELECT id,description,units FROM $db_pids_table");
        $keyarr = [];
        while($row = $keyquery->fetch_assoc()) {
            $keyarr[$row['id']] = array($row['description'], $row['units']);
        }

        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_pids, [$keyarr, $current_timestamp], 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    $selectstring = "time";
    $i = 1;
    while ( isset($_GET["s$i"]) ) {
        if ($_GET["s$i"] == ''){header('Location: .');} //gx
        ${'v' . $i} = $_GET["s$i"];
        $selectstring = $selectstring.",".quote_name(${'v' . $i});
        $i = $i + 1;
    }

    $cache_key = "session_data_{$username}_{$session_id}_{$selectstring}";
    $session_data = false;

    $isStreamQuery = isset($_GET["last"]);
    $streamLimit = $isStreamQuery ? "LIMIT 1" : "";

    if ($isStreamQuery) {
        $memcached_connected = false; //Disable cache on stream query
    }

    if ($memcached_connected) {
        $cached_data = $memcached->get($cache_key);
        if ($memcached->getResultCode() === Memcached::RES_SUCCESS && is_array($cached_data)) {
            list($session_data, $cached_timestamp) = $cached_data;
        }
    }

    if ($session_data === false || $cached_timestamp !== $current_timestamp) {
        try {
            $query = getFilteredQuery($selectstring, $db_table, $streamLimit, $_SESSION['sessions_filter']);
            $sessionqry = $db->execute_query($query, [$session_id]);
            $session_data = $sessionqry->fetch_all(MYSQLI_ASSOC);

            if ($memcached_connected) {
                try {
                    $memcached->set($cache_key, [$session_data, $current_timestamp], 3600);
                } catch (Exception $e) {
                    $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                    error_log($errorMessage);
                }
            }
        } catch (Exception $e) {
            // No data for selected pid
        }
    }

    if (empty($session_data)) return;

	$units = [
	    'speed' => [
	        "km to miles" => [" (mph)", " (miles)"],
	        "miles to km" => [" (km/h)", " (km)"],
	    ],
	    'temp' => [
	        "Celsius to Fahrenheit" => " (°F)",
	        "Fahrenheit to Celsius" => " (°C)",
	    ],
	    'pressure' => [
	        "Psi to Bar" => " (Bar)",
	        "Bar to Psi" => " (Psi)",
	    ],
	    'boost' => [
	        "Psi to Bar" => " (Bar)",
	        "Bar to Psi" => " (Psi)",
	    ],
	];

	foreach ($session_data as $row) {
	    $i = 1;
	    while (isset(${'v' . $i})) {

		$spd_unit = $units['speed'][$speed][0] ?? ' ('.$keyarr[${'v' . $i}][1].')';
		$trip_unit = $units['speed'][$speed][1] ?? ' ('.$keyarr[${'v' . $i}][1].')';
		$temp_unit = $units['temp'][$temp] ?? ' ('.$keyarr[${'v' . $i}][1].')';
		$press_unit = $units['pressure'][$pressure] ?? ' ('.$keyarr[${'v' . $i}][1].')';
		$boost_unit = $units['boost'][$boost] ?? ' ('.$keyarr[${'v' . $i}][1].')';

	        if (substri_count($keyarr[${'v' . $i}][0], "Speed") > 0) {
	            $x = speed_conv($row[${'v' . $i}], $speed, $id);
	            ${'v' . $i . '_measurand'} = $spd_unit;
	        } elseif (substri_count($keyarr[${'v' . $i}][0], "Distance") > 0) {
	            $x = speed_conv($row[${'v' . $i}], $speed, $id);
	            ${'v' . $i . '_measurand'} = $trip_unit;
	        } elseif (substri_count($keyarr[${'v' . $i}][0], "Temp") > 0) {
	            $x = temp_conv($row[${'v' . $i}], $temp, $id);
	            ${'v' . $i . '_measurand'} = $temp_unit;
	        } elseif (substri_count($keyarr[${'v' . $i}][0], "EGT") > 0) {
	            $x = temp_conv($row[${'v' . $i}], $temp, $id);
	            ${'v' . $i . '_measurand'} = $temp_unit;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Boost Solenoid Duty") > 0) {
		     $x = $row[${'v' . $i}];
		     ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Boost") > 0) {
		     $x = pressure_conv($row[${'v' . $i}], $boost, $id);
		     ${'v' . $i . '_measurand'} = $boost_unit;
		} elseif (substri_count($keyarr[${'v' . $i}][0], "Pressure") > 0 && !substri_count($keyarr[${'v' . $i}][0], "Manifold") && !substri_count($keyarr[${'v' . $i}][0], "Barometric") && !substri_count($keyarr[${'v' . $i}][0], "Evap System") && !substri_count($keyarr[${'v' . $i}][0], "Fuel Pressure legacy") && !substri_count($keyarr[${'v' . $i}][0], "Fuel Rail Pressure")) { //Skip (k)Pa things
		     $x = pressure_conv($row[${'v' . $i}], $pressure, $id);
		     ${'v' . $i . '_measurand'} = $press_unit;
		} elseif (substri_count($keyarr[${'v' . $i}][1], "rpm") > 0) {
		    $x = $temp_rpm_dev ($row[${'v' . $i}]);
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

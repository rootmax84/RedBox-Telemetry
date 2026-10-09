<?php
require_once __DIR__ . '/src/helpers.php';

if (isset($_GET['uid'], $_GET['id'], $_GET['sig'])) {
    $_SESSION['torque_logged_in'] = true;
    require_once __DIR__ . '/src/db.php';

    $uid = $_GET['uid'];
    $sid = $_GET['id'];
    $sig = $_GET['sig'];

    if (!checkRateLimit(5)) {
        header('Location: /catch?c=block');
        exit;
    }

    $cache_key = "share_plot_" . $uid;
    $user_data = false;

    if ($memcached_connected) {
        $user_data = $memcached->get($cache_key);
    }

    if ($user_data === false) {
        $userqry = $db->execute_query(
            "SELECT id, user, sessions_filter, share_secret FROM users WHERE id=?",
            [$uid]
        );
        if ($userqry->num_rows) {
            $user_data = $userqry->fetch_assoc();
            if ($memcached_connected) {
                try {
                    $memcached->set($cache_key, $user_data, $db_cache_ttl ?? 3600);
                } catch (Exception $e) {
                    error_log(sprintf("Ratel cache error on share plot: %s (Code: %d)", $e->getMessage(), $e->getCode()));
                }
            }
        } else {
            header('Location: /catch?c=noshare');
            exit;
        }
    }

    if ($user_data) {
        $username            = $user_data['user'];
        $share_secret        = $user_data['share_secret'];
        $user_filter         = $user_data['sessions_filter'];
        $GLOBALS['user_id']  = (int)$user_data['id'];
    }

    $GLOBALS['share_sessions_filter'] = (int)$user_filter;

    $payload = "uid={$uid}&id={$sid}";
    $expected_sig = hash_hmac('sha256', $payload, $share_secret);
    if (!hash_equals($expected_sig, $sig)) {
        header('Location: /catch?c=noshare');
        exit;
    } else {
        checkRateLimit(5, 3600, true);
    }
} else {
    require_once __DIR__ . '/src/db.php';
}

$user_id = current_user_id();

$json = [];

// Convert data units
$temp_rpm_dev = function ($rpm_dev) { return round($rpm_dev/100, 2); };
$tmp_mhs      = function ($mhs)     { return round($mhs,0); };
$tmp_vlt      = function ($vlt)     { return round($vlt,2); };
$tmp_ert      = function ($ert)     { return round($ert/60,0); };
$tmp_gear     = function ($gear)    { return $gear == '255' ? '0' : $gear; };

if (isset($_GET["id"])) {
    $session_id = $db->real_escape_string($_GET['id']);
    $cached_timestamp = null;
    $current_timestamp = getLastUpdateTimestamp($db, (int)$user_id, $session_id);

    if ($current_timestamp === null && empty($_SESSION['share'])) {
        $db->close();
        header('Location: /');
        exit;
    }

    // id (RedManage / TorqueLog / etc.)
    $cache_key_id = cache_var_key("session_id_{$session_id}");
    $id = false;

    if ($memcached_connected) {
        $cached_id_data = $memcached->get($cache_key_id);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($cached_id_data)) {
            list($id, $cached_timestamp) = $cached_id_data;
        }
    }

    if ($id === false || $cached_timestamp !== $current_timestamp) {
        $id = $db->execute_query(
            "SELECT id FROM sessions WHERE user_id = ? AND session = ?",
            [$user_id, $session_id]
        )->fetch_row()[0] ?? null;

        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_id, [$id, $current_timestamp], $db_cache_meta_ttl ?? 300);
            } catch (Exception $e) {
                error_log(sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode()));
            }
        }
    }

    // Units conversion settings
    $cache_key_settings = "user_settings_{$username}";
    $setqry = false;

    if ($memcached_connected) {
        $cached_settings_data = $memcached->get($cache_key_settings);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($cached_settings_data)) {
            list($setqry, $cached_timestamp) = $cached_settings_data;
        }
    }

    if ($setqry === false || $cached_timestamp !== $current_timestamp) {
        $setqry = $db->execute_query(
            "SELECT speed,temp,pressure,boost FROM users WHERE user=?",
            [$username]
        )->fetch_row();

        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_settings, [$setqry, $current_timestamp], $db_cache_ttl ?? 3600);
            } catch (Exception $e) {
                error_log(sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode()));
            }
        }
    }

    $speed    = $setqry[0];
    $temp     = $setqry[1];
    $pressure = $setqry[2];
    $boost    = $setqry[3];

    // PID descriptions/units
    $cache_key_pids = "pids_mapping_{$username}";
    $keyarr = false;

    if ($memcached_connected) {
        $cached_pids_data = $memcached->get($cache_key_pids);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($cached_pids_data)) {
            list($keyarr, $cached_timestamp) = $cached_pids_data;
        }
    }

    if ($keyarr === false || $cached_timestamp !== $current_timestamp) {
        $keyquery = $db->execute_query(
            "SELECT id, description, units FROM pids WHERE user_id = ?",
            [$user_id]
        );
        $keyarr = [];
        while ($row = $keyquery->fetch_assoc()) {
            $keyarr[$row['id']] = [$row['description'], $row['units']];
        }

        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_pids, [$keyarr, $current_timestamp], $db_cache_meta_ttl ?? 300);
            } catch (Exception $e) {
                error_log(sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode()));
            }
        }
    }

    // Selected PIDs (s1, s2, ...)
    $selected_pids = [];
    $i = 1;
    while (isset($_GET["s$i"])) {
        if ($_GET["s$i"] == '') { header('Location: /'); exit; }
        ${'v' . $i} = $_GET["s$i"];
        $selected_pids[] = ${'v' . $i};
        $i++;
    }

    $selectkey = implode('|', $selected_pids);
    $cache_key = cache_var_key("session_data_{$session_id}_{$selectkey}");
    $session_data = false;

    $isStreamQuery = isset($_GET["last"]);
    $streamLimit   = $isStreamQuery ? "LIMIT 1" : "";

    if ($isStreamQuery) {
        $memcached_connected = false;
    }

    if ($memcached_connected) {
        $cached_data = $memcached->get($cache_key);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($cached_data)) {
            list($session_data, $cached_timestamp) = $cached_data;
        }
    }

    if ($session_data === false || $cached_timestamp !== $current_timestamp) {
        try {
            $query = getFilteredQuery(
                (int)$user_id,
                $streamLimit,
                current_sessions_filter()
            );
            $sessionqry = $db->execute_query($query, [$session_id]);
            $raw = $sessionqry->fetch_all(MYSQLI_ASSOC);

            // Распаковка JSON в плоский формат, как раньше
            $session_data = [];
            foreach ($raw as $rawRow) {
                $d = decode_log_data($rawRow['data']);
                $flat = ['time' => $rawRow['time']];
                foreach ($selected_pids as $pid) {
                    $flat[$pid] = $d[$pid] ?? 0;
                }
                $session_data[] = $flat;
            }

            if ($memcached_connected) {
                try {
                    $memcached->set($cache_key, [$session_data, $current_timestamp], $db_cache_ttl ?? 3600);
                } catch (Exception $e) {
                    error_log(sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode()));
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
            $spd_unit   = $units['speed'][$speed][0]  ?? ' ('.$keyarr[${'v' . $i}][1].')';
            $trip_unit  = $units['speed'][$speed][1]  ?? ' ('.$keyarr[${'v' . $i}][1].')';
            $temp_unit  = $units['temp'][$temp]       ?? ' ('.$keyarr[${'v' . $i}][1].')';
            $press_unit = $units['pressure'][$pressure] ?? ' ('.$keyarr[${'v' . $i}][1].')';
            $boost_unit = $units['boost'][$boost]     ?? ' ('.$keyarr[${'v' . $i}][1].')';

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
            } elseif (substri_count($keyarr[${'v' . $i}][0], "Pressure") > 0
                   && !substri_count($keyarr[${'v' . $i}][0], "Manifold")
                   && !substri_count($keyarr[${'v' . $i}][0], "Barometric")
                   && !substri_count($keyarr[${'v' . $i}][0], "Evap System")
                   && !substri_count($keyarr[${'v' . $i}][0], "Fuel Pressure legacy")
                   && !substri_count($keyarr[${'v' . $i}][0], "Fuel Rail Pressure")) {
                $x = pressure_conv($row[${'v' . $i}], $pressure, $id);
                ${'v' . $i . '_measurand'} = $press_unit;
            } elseif (substri_count($keyarr[${'v' . $i}][1], "rpm") > 0) {
                $x = $temp_rpm_dev($row[${'v' . $i}]);
                ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
            } elseif (substri_count($keyarr[${'v' . $i}][0], "Motorhours") > 0) {
                $x = $tmp_mhs($row[${'v' . $i}]);
                ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
            } elseif (substri_count($keyarr[${'v' . $i}][0], "Voltage (OBD Adapter)") > 0) {
                $x = $tmp_vlt($row[${'v' . $i}]);
                ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
            } elseif (substri_count($keyarr[${'v' . $i}][0], "Run Time Since Engine Start") > 0) {
                $x = $tmp_ert($row[${'v' . $i}]);
                ${'v' . $i . '_measurand'} = ' (m)';
            } elseif (substri_count($keyarr[${'v' . $i}][0], "Gear") > 0) {
                $x = $tmp_gear($row[${'v' . $i}]);
                ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
            } else {
                $x = $row[${'v' . $i}];
                ${'v' . $i . '_measurand'} = ' ('.$keyarr[${'v' . $i}][1].')';
            }

            ${'d' . $i}[]     = [$row['time'], $x];
            ${'spark' . $i}[] = $x;
            $i++;
        }
    }

    $i = 1;
    while (isset(${'v' . $i})) {
        ${'v' . $i . '_label'}   = '"'.$keyarr[${'v' . $i}][0].${'v' . $i . '_measurand'}.'"';
        ${'sparkdata' . $i}      = implode(",", array_reverse(${'spark' . $i}));
        ${'max' . $i}            = round(max(${'spark' . $i}), 2);
        ${'min' . $i}            = round(min(${'spark' . $i}), 2);
        ${'avg' . $i}            = round(average(${'spark' . $i}), 2);
        $i++;
    }
}

if (isset($json)) {
    $i = 1;
    while (isset(${'v' . $i})) {
        $json[] = [
            ${'v' . $i},
            $keyarr[${'v' . $i}][0].${'v' . $i . '_measurand'},
            ${'d' . $i},
            ${'sparkdata' . $i},
            ${'max' . $i},
            ${'min' . $i},
            ${'avg' . $i},
        ];
        $i++;
    }
    if (sizeof($json)) print_r(json_encode($json));
}

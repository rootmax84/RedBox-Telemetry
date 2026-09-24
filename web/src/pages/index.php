<?php
/**
 * Backend logic for the main page.
 * Included from /index.php after db.php, db_limits.php, translations.php,
 * helpers.php, redis.php are loaded.
 *
 * Returns an array of view data for src/templates/index/page.php.
 */

$lang = $_COOKIE['lang'];
setcookie("newsess", "");

// Capture the session ID if one has been chosen already
$session_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT) ?: null;

$page = $_GET["page"] ?? 1;
$raw_year = $_GET["year"] ?? "";
if ($raw_year) {
    $filteryear = sanitizeInput($raw_year, 'year_or_all');
    if ($year) {
        $filteryear = $year;
    }
}
$filtermonth = sanitizeInput($_GET["month"] ?? "", 'month');
$filterprofile = sanitizeInput($_GET["profile"] ?? "");
if ($filterprofile == "Not Specified") {
    $filterprofile = $translations[$lang]['profile.ns'];
}

$var1 = "";

// From the output of the get_sessions.php file, populate the page with info from
//  the current session. Using successful existence of a session as a trigger,
//  populate some other variables as well.
if (isset($sids[0])) {
    if (!isset($session_id)) {
        $session_id = $sids[0];
    }

    if ($session_id == ''){
        header('Location: .');
    }

    $cached_timestamp = null;
    $current_timestamp = getLastUpdateTimestamp($db, $session_id, $db_sessions_table);

    // Years
    $years_cache_key = "years_list_" . $username;
    $yeararray = false;

    if ($memcached_connected) {
        $y_cached_data = $memcached->get($years_cache_key);
        if ($memcached->getResultCode() === Memcached::RES_SUCCESS && is_array($y_cached_data)) {
            list($yeararray, $cached_timestamp) = $y_cached_data;
        }
    }

    if ($yeararray === false || $cached_timestamp !== $current_timestamp) {
        $yearquery = $db->query("SELECT YEAR(FROM_UNIXTIME(session/1000)) as 'year'
            FROM $db_sessions_table WHERE session <> ''
            GROUP BY YEAR(FROM_UNIXTIME(session/1000))
            ORDER BY YEAR(FROM_UNIXTIME(session/1000)) DESC");
        $yeararray = [];
        while($row = $yearquery->fetch_assoc()) {
            $yeararray[] = $row['year'];
        }
        if ($memcached_connected) {
            try {
                $memcached->set($years_cache_key, [$yeararray, $current_timestamp], $db_memcached_ttl ?? 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    // Profiles
    $profiles_cache_key = "profiles_list_" . $username;
    $profilearray = false;

    if ($memcached_connected) {
        $p_cached_data = $memcached->get($profiles_cache_key);
        if ($memcached->getResultCode() === Memcached::RES_SUCCESS && is_array($p_cached_data)) {
            list($profilearray, $cached_timestamp) = $p_cached_data;
        }
    }

    if ($profilearray === false || $cached_timestamp !== $current_timestamp) {
        $profilequery = $db->query("SELECT distinct profileName FROM $db_sessions_table ORDER BY profileName asc");
        $profilearray = [];
        while($row = $profilequery->fetch_assoc()) {
            $profilearray[] = $row['profileName'] === 'Not Specified' ? $translations[$lang]['profile.ns'] : $row['profileName'];
        }
        if ($memcached_connected) {
            try {
                $memcached->set($profiles_cache_key, [$profilearray, $current_timestamp], $db_memcached_ttl ?? 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    // GPS data
    $gps_cache_key = "gps_data_" . $username . "_" . $session_id;
    $gps_data = false;

    if ($memcached_connected) {
        $g_cached_data = $memcached->get($gps_cache_key);
        if ($memcached->getResultCode() === Memcached::RES_SUCCESS && is_array($g_cached_data)) {
            list($gps_data, $cached_timestamp) = $g_cached_data;
        }
    }

    if ($gps_data === false || $cached_timestamp !== $current_timestamp) {
        $gpsQuery = getFilteredGpsQuery($db_table, $_SESSION['sessions_filter']);
        $gps_time_data = $db->execute_query($gpsQuery, [$session_id]);
        $geolocs = [];
        $timearray = [];
        $i = 0;
        while($row = $gps_time_data->fetch_row()) {
            if (($row[0] != 0) && ($row[1] != 0)) {
                $geolocs[] = ["lat" => $row[0], "lon" => $row[1], "heading" => $row[2]];
            }
            $timearray[$i] = $row[3];
            $i++;
        }
        $gps_data = ['geolocs' => $geolocs, 'timearray' => $timearray];
        if ($memcached_connected) {
            try {
                $memcached->set($gps_cache_key, [$gps_data, $current_timestamp], $db_memcached_ttl ?? 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    $geolocs = $gps_data['geolocs'];
    $timearray = $gps_data['timearray'];

    $itime = implode(",", $timearray);

    // Create array of Latitude/Longitude strings in leafletjs JavaScript format
    $mapdata = [];
    foreach($geolocs as $d) {
        $mapdata[] = "[".sprintf("%.14f",$d['lat']).",".sprintf("%.14f",$d['lon']).",".sprintf("%.14f",$d['heading'])."]";
    }
    $imapdata = implode(",", $mapdata);

    // stream_lock
    $stream_lock_cache_key = "stream_lock_" . $username;
    $stream_lock = false;

    if ($memcached_connected) {
        $s_cached_data = $memcached->get($stream_lock_cache_key);
        if ($memcached->getResultCode() === Memcached::RES_SUCCESS && is_array($s_cached_data)) {
            list($stream_lock, $cached_timestamp) = $s_cached_data;
        }
    }

    if ($stream_lock === false || $cached_timestamp !== $current_timestamp) {
        $stream_lock = $db->execute_query("SELECT stream_lock FROM $db_users WHERE user=?", [$username])->fetch_row()[0];
        if ($memcached_connected) {
            try {
                $memcached->set($stream_lock_cache_key, [$stream_lock, $current_timestamp], $db_memcached_ttl ?? 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    // id
    $session_id_cache_key = "session_id_" . $session_id;
    $id = false;

    if ($memcached_connected) {
        $i_cached_data = $memcached->get($session_id_cache_key);
        if ($memcached->getResultCode() === Memcached::RES_SUCCESS && is_array($i_cached_data)) {
            list($id, $cached_timestamp) = $i_cached_data;
        }
    }

    if ($id === false || $cached_timestamp !== $current_timestamp) {
        $id = $db->execute_query("SELECT id FROM $db_sessions_table WHERE session=?", [$session_id])->fetch_row()[0];
        if ($memcached_connected) {
            try {
                $memcached->set($session_id_cache_key, [$id, $current_timestamp], $db_memcached_ttl ?? 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    $db->close();
}

return [
    'lang'                 => $lang,
    'session_id'           => $session_id,
    'limit'                => $limit ?? 0,
    'filteryear'           => $filteryear ?? '',
    'filtermonth'          => $filtermonth ?? '',
    'filterprofile'        => $filterprofile ?? '',
    'yeararray'            => $yeararray ?? [],
    'profilearray'         => $profilearray ?? [],
    'coldata'              => $coldata ?? [],
    'var1'                 => $var1,
    'imapdata'             => $imapdata ?? '',
    'itime'                => $itime ?? '',
    'stream_lock'          => $stream_lock ?? 0,
    'id'                   => $id ?? null,
    'seshdates'            => $seshdates ?? [],
    'seshsizes'            => $seshsizes ?? [],
    'seshprofile'          => $seshprofile ?? [],
    'seship'               => $seship ?? [],
    'sesactive'            => $sesactive ?? [],
    'sesfavorite'          => $sesfavorite ?? [],
    'show_session_length'  => $show_session_length ?? true,
    'admin'                => $admin ?? '',
    'admin_timeformat_12'  => $admin_timeformat_12 ?? false,
    'db_host'              => $db_host ?? '',
    'db_user'              => $db_user ?? '',
    'db_name'              => $db_name ?? '',
    'db_users'             => $db_users ?? '',
    'db_log_prefix'        => $db_log_prefix ?? '',
    'results_per_page'     => $results_per_page ?? 50,
    'memcached_connected'  => $memcached_connected,
    'redis_stream_enabled' => $redis_stream_enabled ?? false,
    'redis_stream_key'     => $redis_stream_key ?? 'telemetry:uploads',
    'redis_stream_group'   => $redis_stream_group ?? 'telemetry-workers',
    'translations'         => $translations,
    'page'                 => $page,
    'db'                   => $db,
];

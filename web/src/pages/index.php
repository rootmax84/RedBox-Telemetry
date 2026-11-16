<?php
/**
 * Backend logic for the main page.
 * Included from /index.php after db.php, db_limits.php, translations.php,
 * helpers.php, redis.php are loaded.
 *
 * Returns an array of view data for src/templates/index/page.php.
 */

unset($GLOBALS['share_sessions_filter']);
$lang = current_lang();
setcookie("newsess", "");

// Capture the session ID if one has been chosen already
$session_id = filter_var($_GET['id'] ?? null, FILTER_SANITIZE_NUMBER_INT) ?: null;

$page = max(1, (int)($_GET["page"] ?? 1));
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
        header('Location: /');
    }

    $cached_timestamp = null;
    $current_timestamp = getLastUpdateTimestamp($db, (int)current_user_id(), $session_id);

    // Years
    $years_cache_key = "years_list_" . $username;
    $yeararray = false;

    if ($memcached_connected) {
        $y_cached_data = $memcached->get($years_cache_key);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($y_cached_data)) {
            list($yeararray, $cached_timestamp) = $y_cached_data;
        }
    }

    if ($yeararray === false || $cached_timestamp !== $current_timestamp) {
        $yearquery = $db->execute_query(
            "SELECT YEAR(FROM_UNIXTIME(session/1000)) as 'year'
             FROM sessions
             WHERE user_id = ? AND session <> ''
             GROUP BY YEAR(FROM_UNIXTIME(session/1000))
             ORDER BY YEAR(FROM_UNIXTIME(session/1000)) DESC",
            [current_user_id()]
        );
        $yeararray = [];
        while($row = $yearquery->fetch_assoc()) {
            $yeararray[] = $row['year'];
        }
        if ($memcached_connected) {
            try {
                $memcached->set($years_cache_key, [$yeararray, $current_timestamp], $db_cache_meta_ttl ?? 300);
            } catch (Exception $e) {
                $errorMessage = sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    // Profiles
    $profiles_cache_key = "profiles_list_" . $username;
    $profilearray = false;

    if ($memcached_connected) {
        $p_cached_data = $memcached->get($profiles_cache_key);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($p_cached_data)) {
            list($profilearray, $cached_timestamp) = $p_cached_data;
        }
    }

    if ($profilearray === false || $cached_timestamp !== $current_timestamp) {
        $profilequery = $db->execute_query(
            "SELECT DISTINCT profileName FROM sessions
              WHERE user_id = ? ORDER BY profileName ASC",
            [current_user_id()]
        );
        $profilearray = [];
        while($row = $profilequery->fetch_assoc()) {
            $profilearray[] = $row['profileName'] === 'Not Specified' ? $translations[$lang]['profile.ns'] : $row['profileName'];
        }
        if ($memcached_connected) {
            try {
                $memcached->set($profiles_cache_key, [$profilearray, $current_timestamp], $db_cache_meta_ttl ?? 300);
            } catch (Exception $e) {
                $errorMessage = sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    // GPS data
    $gps_cache_key = cache_var_key("gps_data_v4_{$session_id}");
    $gps_data = false;

    if ($memcached_connected) {
        $g_cached_data = $memcached->get($gps_cache_key);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($g_cached_data)) {
            list($gps_data, $cached_timestamp) = $g_cached_data;
        }
    }

    if ($gps_data === false || $cached_timestamp !== $current_timestamp) {
        $gpsQuery = getFilteredGpsQuery((int)current_user_id(), current_sessions_filter());
        $gps_time_data = $db->execute_query($gpsQuery, [$session_id]);
        $geolocs = [];
        $timearray = [];
        $i = 0;
        $segmentId = 0;
        $gpsMissing = false;
        $previousMissingCount = null;
        while ($row = $gps_time_data->fetch_row()) {
            // Keep the exact log timestamp and GPS outages; the filtered GPS
            // array is not index-aligned with the chart's telemetry array.
            $lat = (float)$row[0];
            $lon = (float)$row[1];
            $valid = is_finite($lat) && is_finite($lon)
                && $lat != 0 && $lon != 0 && abs($lat) <= 90 && abs($lon) <= 180;
            if ($valid) {
                $missingCount = isset($row[4]) ? (int)$row[4] : null;
                if ($gpsMissing || ($previousMissingCount !== null &&
                    $missingCount !== null && $missingCount > $previousMissingCount)) {
                    ++$segmentId;
                }
                $gpsMissing = false;
                $previousMissingCount = $missingCount;
                $geolocs[] = ['lat' => $lat, 'lon' => $lon,
                    'heading' => (float)$row[2], 'time' => (int)$row[3],
                    'segment' => $segmentId,
                    'obd_speed' => (isset($row[5]) && is_numeric($row[5])) ? (float)$row[5] : null,
                    'gps_speed' => (isset($row[6]) && is_numeric($row[6])) ? (float)$row[6] : null];
            } else {
                $gpsMissing = true;
            }
            $timearray[$i] = $row[3];
            $i++;
        }
        $gps_data = ['geolocs' => $geolocs, 'timearray' => $timearray];
        if ($memcached_connected) {
            try {
                $memcached->set($gps_cache_key, [$gps_data, $current_timestamp], $db_cache_ttl ?? 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
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
        $mapdata[] = json_encode([(float)$d['lat'], (float)$d['lon'],
            (float)$d['heading'], (int)$d['time'], (int)$d['segment'],
            $d['obd_speed'], $d['gps_speed']], JSON_THROW_ON_ERROR);
    }
    $imapdata = implode(",", $mapdata);

    // id
    $session_id_cache_key = cache_var_key("session_id_{$session_id}");
    $id = false;

    if ($memcached_connected) {
        $i_cached_data = $memcached->get($session_id_cache_key);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($i_cached_data)) {
            list($id, $cached_timestamp) = $i_cached_data;
        }
    }

    if ($id === false || $cached_timestamp !== $current_timestamp) {
        $id = $db->execute_query(
            "SELECT id FROM sessions WHERE user_id = ? AND session = ?",
            [current_user_id(), $session_id]
        )->fetch_row()[0] ?? null;
        if ($memcached_connected) {
            try {
                $memcached->set($session_id_cache_key, [$id, $current_timestamp], $db_cache_meta_ttl ?? 300);
            } catch (Exception $e) {
                $errorMessage = sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }
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
    'results_per_page'     => $results_per_page ?? 50,
    'memcached_connected'  => $memcached_connected,
    'redis_stream_enabled' => $redis_stream_enabled ?? false,
    'redis_stream_key'     => $redis_stream_key ?? 'telemetry:uploads',
    'redis_stream_group'   => $redis_stream_group ?? 'telemetry-workers',
    'translations'         => $translations,
    'page'                 => $page,
    'db'                   => $db,
];

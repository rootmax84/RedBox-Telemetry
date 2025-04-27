<?php
include("timezone.php");
include_once("translations.php");
$lang = $_COOKIE['lang'];

$seshdates = $seshsizes = $seshprofile = $seship = $sesactive = $sesfavorite = $sids = [];

function getFilterValue($postKey, $getKey, $default) {
    return isset($_POST[$postKey]) ? $_POST[$postKey] : (isset($_GET[$getKey]) ? $_GET[$getKey] : $default);
}

$filteryear = getFilterValue("selyear", "year", date('Y'));
$filteryear = ($filteryear === "ALL") ? "%" : $filteryear;

$filtermonth = getFilterValue("selmonth", "month", (isset($_POST["selyear"]) || isset($_GET["year"])) ? "%" : date('F'));
$filtermonth = ($filtermonth === "ALL") ? "%" : $filtermonth;

$filterprofile = getFilterValue("selprofile", "profile", "%%");
$filterprofile = ($filterprofile === "ALL") ? "%%" : $filterprofile;

$cache_key = "sessions_list_" . $username . "_" . md5(serialize([$filteryear, $filtermonth, $filterprofile, $_GET['id'] ?? null]));

$cached_data = false;
if ($memcached_connected) {
    $cached_data = $memcached->get($cache_key);

    if ($cached_data !== false && is_array($cached_data)) {
        $seshdates = $cached_data['seshdates'] ?? [];
        $seshsizes = $cached_data['seshsizes'] ?? [];
        $seshprofile = $cached_data['seshprofile'] ?? [];
        $seship = $cached_data['seship'] ?? [];
        $sesactive = $cached_data['sesactive'] ?? [];
        $sesfavorite = $cached_data['sesfavorite'] ?? [];
        $sids = $cached_data['sids'] ?? [];
    }
}

if (empty($sids)) {
    // array for prepared query params
    $params = [];
    $types = "";

    // Build SQL-query
    $query = "SELECT time, timeend, session, profileName, sessionsize, ip, favorite
              FROM $db_sessions_table
              WHERE 1=1";

    if ($filteryear !== "%") {
        $query .= " AND YEAR(FROM_UNIXTIME(session / 1000)) LIKE ?";
        $params[] = $filteryear;
        $types .= "s";
    }

    if ($filtermonth !== "%") {
        $query .= " AND MONTHNAME(FROM_UNIXTIME(session / 1000)) LIKE ?";
        $params[] = $filtermonth;
        $types .= "s";
    }

    if ($filterprofile !== "%%") {
        $query .= " AND profileName LIKE ?";
        $params[] = $filterprofile;
        $types .= "s";
    }

    if (isset($_GET['id'])) {
        $query .= " OR session LIKE ?";
        $params[] = $_GET['id'];
        $types .= "s";
    }

    $query .= " GROUP BY session, profileName, time, timeend, sessionsize ORDER BY session DESC";

    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $sessionqry = $stmt->get_result();

    if ($sessionqry->num_rows == 0) {
        $query = "SELECT time, timeend, session, profileName, sessionsize, ip, favorite
                  FROM $db_sessions_table
                  GROUP BY session, profileName, time, timeend, sessionsize
                  ORDER BY session DESC
                  LIMIT 20";
        $sessionqry = $db->query($query);
    }

    $seshdates = $seshsizes = $seshprofile = $seship = $sesactive = $sesfavorite = $sids = [];

    while ($row = $sessionqry->fetch_assoc()) {
        $row["timeend"] = !$row["timeend"] ? $row["time"] : $row["timeend"];
        $session_duration_str = gmdate("H:i:s", intval(($row["timeend"] - $row["time"]) / 1000));
        $sid = $row["session"];
        $session_profileName = $row["profileName"] === 'Not Specified' ? $translations[$lang]['profile.ns'] : $row["profileName"];
        $session_ip = $row["ip"];

        $sids[] = preg_replace('/\D/', '', $sid);
        $seshdates[$sid] = date($_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i", substr($sid, 0, -3));
        $seshsizes[$sid] = " ({$translations[$lang]['get.sess.length']} $session_duration_str)";
        $seshprofile[$sid] = " ({$translations[$lang]['get.sess.profile']} $session_profileName)";
        $seship[$sid] = " ({$translations[$lang]['get.sess.ip']} $session_ip)";
        $sesactive[$sid] = ($row["timeend"] > (time() * 1000) - 60000) ? " {$translations[$lang]['get.sess.active']}" : null;
        $sesfavorite[$sid] = $row["favorite"];
    }

    if ($memcached_connected) {
        $sessions_data = [
            'seshdates' => $seshdates,
            'seshsizes' => $seshsizes,
            'seshprofile' => $seshprofile,
            'seship' => $seship,
            'sesactive' => $sesactive,
            'sesfavorite' => $sesfavorite,
            'sids' => $sids
        ];

        try {
            $memcached->set($cache_key, $sessions_data, $db_memcached_ttl ?? 3600);
        } catch (Exception $e) {
            error_log("Memcached error: " . $e->getMessage());
        }
    }
}

function getTranslatedMonth($month, $lang) {
    global $translations;
    $month_key = 'month.' . strtolower(substr($month, 0, 3));
    return $translations[$lang][$month_key] ?? $month;
}

foreach ($seshdates as $sid => $date) {
    $month_name = date("F", strtotime($date));
    $translated_month = getTranslatedMonth($month_name, $lang);
    $seshdates[$sid] = str_replace($month_name, $translated_month, $date);
}

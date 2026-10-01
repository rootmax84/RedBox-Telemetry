<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../timezone.php';
include_once __DIR__ . '/../translations.php';
$lang = $_COOKIE['lang'] ?? 'en';

function getFilterValue($postKey, $getKey, $default) {
    return isset($_POST[$postKey]) ? $_POST[$postKey] : (isset($_GET[$getKey]) ? $_GET[$getKey] : $default);
}

$filteryear = getFilterValue("selyear", "year", date('Y'));
$filteryear = ($filteryear === "ALL") ? "%" : $filteryear;

$filtermonth = getFilterValue("selmonth", "month", (isset($_POST["selyear"]) || isset($_GET["year"])) ? "%" : date('F'));
$filtermonth = ($filtermonth === "ALL") ? "%" : $filtermonth;

$filterprofile = getFilterValue("selprofile", "profile", "%%");
$filterprofile = ($filterprofile === "ALL") ? "%%" : $filterprofile;

$query = "SELECT time, timeend, session, profileName, ip, favorite
          FROM sessions
          WHERE user_id = ?";

$params = [current_user_id()];
$types  = "i";

// year filter
if ($filteryear !== "%") {
    $query .= " AND YEAR(FROM_UNIXTIME(session / 1000)) LIKE ?";
    $params[] = $filteryear;
    $types .= "s";
}

// month filter
if ($filtermonth !== "%") {
    $query .= " AND MONTHNAME(FROM_UNIXTIME(session / 1000)) LIKE ?";
    $params[] = $filtermonth;
    $types .= "s";
}

// profile filter
if ($filterprofile !== "%%") {
    $query .= " AND profileName LIKE ?";
    $params[] = $filterprofile;
    $types .= "s";
}

// session id filter if presence
if (isset($_GET['id'])) {
    $query .= " AND session LIKE ?";
    $params[] = $_GET['id'];
    $types .= "s";
}

// Sort and group
$query .= " GROUP BY session, profileName, time, timeend ORDER BY session DESC";

// Do stuff in database
$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$sessionqry = $stmt->get_result();

// If nothing found pull last 20 sessions
if ($sessionqry->num_rows == 0) {
    $sessionqry = $db->execute_query(
        "SELECT time, timeend, session, profileName, ip, favorite
         FROM sessions
         WHERE user_id = ?
         GROUP BY session, profileName, time, timeend
         ORDER BY session DESC
         LIMIT 20",
        [current_user_id()]
    );
}

$seshdates = [];
$seshsizes = [];
$seshprofile = [];
$seship = [];
$sesactive = [];
$sesfavorite = [];
$sids = [];

while ($row = $sessionqry->fetch_assoc()) {
    $row["timeend"] = !$row["timeend"] ? $row["time"] : $row["timeend"];
    $session_duration_str = formatDuration((int)$row["time"], (int)$row["timeend"], $lang);
    $sid = $row["session"];
    $session_profileName = $row["profileName"] === 'Not Specified' ? $translations[$lang]['profile.ns'] : $row["profileName"];
    $session_ip = $row["ip"];
    $sids[] = preg_replace('/\D/', '', $sid);
    $seshdates[$sid] = date($_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i", substr($sid, 0, -3));
    $seshsizes[$sid] = " ({$translations[$lang]['get.sess.length']}: $session_duration_str)";
    $seshprofile[$sid] = " ({$translations[$lang]['sel.profile']}: $session_profileName)";
    $seship[$sid] = " (IP: $session_ip)";
    $sesactive[$sid] = ($row["timeend"] > (time() * 1000) - 60000) ? " {$translations[$lang]['get.sess.active']}" : null;
    $sesfavorite[$sid] = $row["favorite"];
}

foreach ($seshdates as $sid => $date) {
    $month_name = date("F", strtotime($date));
    $translated_month = getTranslatedMonth($month_name, $lang);
    $seshdates[$sid] = str_replace($month_name, $translated_month, $date);
}

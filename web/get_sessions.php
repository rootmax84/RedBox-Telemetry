<?php
if (!isset($_SESSION)) {
    session_start();
}

include("timezone.php");
include_once("translations.php");
$lang = $_COOKIE['lang'];

function getFilterValue($postKey, $getKey, $default) {
    return isset($_POST[$postKey]) ? $_POST[$postKey] : (isset($_GET[$getKey]) ? $_GET[$getKey] : $default);
}

$filteryear = getFilterValue("selyear", "year", date('Y'));
$filteryear = ($filteryear === "ALL") ? "%" : $filteryear;

$filtermonth = getFilterValue("selmonth", "month", (isset($_POST["selyear"]) || isset($_GET["year"])) ? "%" : date('F'));
$filtermonth = ($filtermonth === "ALL") ? "%" : $filtermonth;

$filterprofile = getFilterValue("selprofile", "profile", "%%");
$filterprofile = ($filterprofile === "ALL") ? "%%" : $filterprofile;

// array for prepared query params
$params = [];
$types = ""; // Types for bind_param (example, 's' for strings)

// Build SQL-query with prepared expressions
$query = "SELECT time, timeend, session, profileName, sessionsize, ip
          FROM $db_sessions_table
          WHERE 1=1";

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
    $query .= " OR session LIKE ?";
    $params[] = $_GET['id'];
    $types .= "s";
}

// Sort and group
$query .= " GROUP BY session, profileName, time, timeend, sessionsize ORDER BY session DESC";

// Do stuff in database
try {
    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $sessionqry = $stmt->get_result();

} catch (Exception $e) {
    logout_user();
}

// If nothing found pull last 20 sessions
if ($sessionqry->num_rows == 0) {
    $query = "SELECT time, timeend, session, profileName, sessionsize, ip
              FROM $db_sessions_table
              GROUP BY session, profileName, time, timeend, sessionsize
              ORDER BY session DESC
              LIMIT 20";

    $sessionqry = $db->query($query);
}

$seshdates = [];
$seshsizes = [];
$seshprofile = [];
$seship = [];

while ($row = $sessionqry->fetch_assoc()) {
    $row["timeend"] = !$row["timeend"] ? $row["time"] : $row["timeend"];
    $session_duration_str = gmdate("H:i:s", intval(($row["timeend"] - $row["time"]) / 1000));
    $sid = $row["session"];
    $session_profileName = $row["profileName"];
    $session_ip = $row["ip"];
    $sids[] = preg_replace('/\D/', '', $sid);
    $seshdates[$sid] = date($_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i", substr($sid, 0, -3));
    $seshsizes[$sid] = " ({$translations[$lang]['get.sess.length']} $session_duration_str)";
    $seshprofile[$sid] = " ($session_profileName {$translations[$lang]['get.sess.profile']})";
    $seship[$sid] = " ({$translations[$lang]['get.sess.ip']} $session_ip)";
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
?>
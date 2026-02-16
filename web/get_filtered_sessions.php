<?php
require_once 'db.php';
require_once 'helpers.php';
require_once 'timezone.php';
include_once 'translations.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$lang = $_COOKIE['lang'] ?? 'en';

$filteryear = $_GET['year'] ?? '%';
$filtermonth = $_GET['month'] ?? '%';
$filterprofile = $_GET['profile'] ?? '%%';

if ($filteryear === "ALL" || $filteryear === "") $filteryear = "%";
if ($filtermonth === "ALL" || $filtermonth === "") $filtermonth = "%";
if ($filterprofile === "ALL" || $filterprofile === "") $filterprofile = "%%";

$current_id = $_GET['current_id'] ?? '';

$query = "SELECT time, timeend, session, profileName, sessionsize, ip, favorite
          FROM $db_sessions_table
          WHERE 1=1";

$params = [];
$types = "";

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

$query .= " GROUP BY session, profileName, time, timeend, sessionsize ORDER BY session DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$sessionqry = $stmt->get_result();

$sessions = [];
while ($row = $sessionqry->fetch_assoc()) {
    $row["timeend"] = !$row["timeend"] ? $row["time"] : $row["timeend"];

    $session_duration_str = formatDuration((int)$row["time"], (int)$row["timeend"], $lang);

    $sid = $row["session"];

    $session_profileName = $row["profileName"] === 'Not Specified' 
        ? ($translations[$lang]['profile.ns'] ?? 'Not Specified') 
        : $row["profileName"];

    $timeformat = isset($_COOKIE['timeformat']) && $_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i";
    $timestamp = substr($sid, 0, -3);

    if (is_numeric($timestamp) && $timestamp > 0) {
        $session_date = date($timeformat, $timestamp);
        $month_name = date("F", $timestamp);

        $translated_month = getTranslatedMonth($month_name, $lang);
        if ($translated_month !== $month_name) {
            $session_date = str_replace($month_name, $translated_month, $session_date);
        }
    } else {
        $session_date = "Invalid date";
    }

    $session_active = ($row["timeend"] > (time() * 1000) - 60000) 
        ? " " . ($translations[$lang]['get.sess.active'] ?? 'Active') 
        : null;

    $sessions[] = [
        'id' => $sid,
        'date' => $session_date,
        'profile' => $session_profileName,
        'duration' => $session_duration_str,
        'ip' => $row["ip"],
        'active' => $session_active,
        'favorite' => (int)$row["favorite"],
        'selected' => $current_id == $sid
    ];
}

echo json_encode(['sessions' => $sessions]);

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

/* ─── Собираем фильтры отдельно ─── */
$filter_conditions = [];
$params = [current_user_id()];
$types  = "i";

// year filter
if ($filteryear !== "%") {
    $filter_conditions[] = "YEAR(FROM_UNIXTIME(session / 1000)) LIKE ?";
    $params[] = $filteryear;
    $types .= "s";
}

// month filter
if ($filtermonth !== "%") {
    $filter_conditions[] = "MONTHNAME(FROM_UNIXTIME(session / 1000)) LIKE ?";
    $params[] = $filtermonth;
    $types .= "s";
}

// profile filter
if ($filterprofile !== "%%") {
    $filter_conditions[] = "profileName LIKE ?";
    $params[] = $filterprofile;
    $types .= "s";
}

$current_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$query = "SELECT time, timeend, session, profileName, ip, favorite
          FROM sessions
          WHERE user_id = ?";

/* ─── Если выбрана конкретная сессия — добавляем её к списку, ───
 *     даже если она не попадает под фильтр года/месяца/профиля. ─── */
if ($current_id > 0) {
    $filter_sql = !empty($filter_conditions)
        ? "(" . implode(" AND ", $filter_conditions) . ")"
        : "1=1";

    $query .= " AND ($filter_sql OR session = ?)";
    $params[] = $current_id;
    $types .= "s";
} else {
    if (!empty($filter_conditions)) {
        $query .= " AND " . implode(" AND ", $filter_conditions);
    }
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

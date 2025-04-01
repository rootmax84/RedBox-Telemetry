<?php
require('db.php');

// Capture the session ID (timestamp like 1741674322414)
$seshid = $_GET["seshid"] ?? $_POST["seshidtag"] ?? $_GET["id"] ?? $_SESSION['recent_session_id'];
$seshid = $db->escape_string(strval($seshid));

// Convert session timestamp to month name
$session_month = '';
if ($seshid && is_numeric($seshid)) {
    $timestamp = intval(substr($seshid, 0, -3)); // Remove milliseconds
    $session_month = date('F', $timestamp); // Full month name (e.g. "January")
}

$baselink = ".";
$outurl = $baselink . "?id=" . $seshid;

// Add month parameter from session if not explicitly provided
$month = $_POST["selmonth"] ?? $_GET["month"] ?? $session_month;
if ($month !== "") {
    $outurl .= "&month=" . $month;
}

// Other parameters (profile and year)
$profile = $_POST["selprofile"] ?? $_GET["profile"] ?? null;
if ($profile) {
    $outurl .= "&profile=" . $profile;
}

$year = $_POST["selyear"] ?? $_GET["year"] ?? null;
if ($year) {
    $outurl .= "&year=" . $year;
}

header("Location: " . $outurl);
?>

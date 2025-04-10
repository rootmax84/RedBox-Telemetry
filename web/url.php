<?php
require('db.php');

// Get current session ID
$current_seshid = $_GET["seshid"] ?? $_POST["seshidtag"] ?? $_GET["id"] ?? null;
if ($current_seshid) {
    $current_seshid = $db->escape_string(strval($current_seshid));
}

// Calculate month from session ID if possible
$calculated_month = '';
if ($current_seshid && is_numeric($current_seshid)) {
    $timestamp = intval(substr($current_seshid, 0, -3));
    $calculated_month = date('F', $timestamp);
}

// Determine month (explicit or calculated)
$month = $_POST["selmonth"] ?? $_GET["month"] ?? $calculated_month ?? '';

// Build URL
$baselink = ".";
$outurl = $baselink;

// Always add ID if provided
if ($current_seshid) {
    $outurl .= "?id=" . $current_seshid;
}

// Add month if provided
if (!empty($month)) {
    $outurl .= (strpos($outurl, '?') === false ? "?" : "&") . "month=" . $month;
}

// Add other parameters
$profile = $_POST["selprofile"] ?? $_GET["profile"] ?? null;
if ($profile) {
    $outurl .= (strpos($outurl, '?') === false ? "?" : "&") . "profile=" . $profile;
}

$year = $_POST["selyear"] ?? $_GET["year"] ?? null;
if ($year) {
    $outurl .= (strpos($outurl, '?') === false ? "?" : "&") . "year=" . $year;
}

header("Location: " . $outurl);
?>

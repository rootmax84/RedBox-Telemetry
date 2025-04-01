<?php
require('db.php');

// Capture the session ID we're going to be working with
$seshid = $_GET["seshid"] ?? $_POST["seshidtag"] ?? $_SESSION['recent_session_id'];

// Only ignore GET['id'] if month is specified and NOT "ALL"
$month = $_POST["selmonth"] ?? $_GET["month"] ?? "";
if (!($month !== "" && $month !== "ALL")) {
    $seshid = $_GET["id"] ?? $seshid;
}

$seshid = $db->escape_string(strval($seshid));

$baselink = ".";
$outurl = $baselink;

// Add id parameter if it exists and (month is not specified or month is "ALL")
if ($seshid && (!($month !== "" && $month !== "ALL"))) {
    $outurl .= "?id=" . $seshid;
}

// Capture the profile we will be working with
$profile = $_POST["selprofile"] ?? $_GET["profile"] ?? null;
if ($profile) {
    $outurl .= (strpos($outurl, '?') === false ? "?" : "&") . "profile=" . $profile;
}

// Capture the year we will be working with
$year = $_POST["selyear"] ?? $_GET["year"] ?? null;
if ($year) {
    $outurl .= (strpos($outurl, '?') === false ? "?" : "&") . "year=" . $year;
}

// Capture the month we will be working with
if ($month !== "") {
    $outurl .= (strpos($outurl, '?') === false ? "?" : "&") . "month=" . $month;
}

header("Location: " . $outurl);
?>

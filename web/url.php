<?php
if (!isset($_SESSION)) { session_start(); }
require('db.php');

// Capture the session ID we're going to be working with
$seshid = $_GET["seshid"] ?? $_POST["seshidtag"] ?? $_GET["id"] ?? $_SESSION['recent_session_id'];
$seshid = $db->escape_string(strval($seshid));

$baselink = ".";
$outurl = $baselink . "?id=" . $seshid;

// Capture the profile we will be working with
$profile = $_POST["selprofile"] ?? $_GET["profile"] ?? null;
if ($profile) {
    $outurl .= "&profile=" . $profile;
}

// Capture the year we will be working with
$year = $_POST["selyear"] ?? $_GET["year"] ?? null;
if ($year) {
    $outurl .= "&year=" . $year;
}

// Capture the month we will be working with
$month = $_POST["selmonth"] ?? $_GET["month"] ?? "";
if ($month !== "") {
    $outurl .= "&month=" . $month;
}

header("Location: " . $outurl);
?>

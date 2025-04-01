<?php
require('db.php');

// Initialize session storage
if (!isset($_SESSION['last_seshid'])) {
    $_SESSION['last_seshid'] = null;
    $_SESSION['last_month'] = null;
}

// Get current session ID
$current_seshid = $_GET["seshid"] ?? $_POST["seshidtag"] ?? $_GET["id"] ?? $_SESSION['last_seshid'];
$current_seshid = $db->escape_string(strval($current_seshid));

// Calculate month from session ID if needed
$calculated_month = '';
if ($current_seshid && is_numeric($current_seshid)) {
    $timestamp = intval(substr($current_seshid, 0, -3)); // Remove milliseconds
    $calculated_month = date('F', $timestamp); // Full month name
}

// Determine if we should add parameters
$add_id = $current_seshid && $current_seshid !== $_SESSION['last_seshid'];
$add_month = false;

// Check if we have explicitly provided month
$explicit_month = $_POST["selmonth"] ?? $_GET["month"] ?? null;

if ($explicit_month) {
    // Always add explicit month
    $add_month = true;
    $month = $explicit_month;
} elseif ($calculated_month && $add_id) {
    // Only add calculated month if ID is new
    $add_month = true;
    $month = $calculated_month;
}

// Build URL
$baselink = ".";
$outurl = $baselink;

// Add ID if needed
if ($add_id) {
    $outurl .= "?id=" . $current_seshid;
}

// Add month if needed
if ($add_month) {
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

// Update session storage
$_SESSION['last_seshid'] = $current_seshid;
$_SESSION['last_month'] = $calculated_month;

header("Location: " . $outurl);
?>

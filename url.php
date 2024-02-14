<?php
if (!isset($_SESSION)) { session_start(); }
require('db.php');

// Capture the session ID we're going to be working with
if (isset($_GET["seshid"])) {
	$seshid = strval($db->escape_string($_GET["seshid"]));
} elseif (isset($_POST["seshidtag"])) {
	$seshid = strval($db->escape_string($_POST["seshidtag"]));
} elseif (isset($_GET["id"])) {
	$seshid = $_GET["id"];
} else {
	$seshid = $_SESSION['recent_session_id'];
}

$baselink = "/";

$outurl = $baselink."?id=".$seshid;

// Capture the profile we will be working with
if (isset($_POST["selprofile"])) {
	if ($_POST["selprofile"]) {
		$outurl = $outurl."&profile=".$_POST["selprofile"];
	}
} elseif (isset($_GET["profile"])) {
	if ($_GET["profile"]) {
		$outurl = $outurl."&profile=".$_GET["profile"];
	}
}

// Capture the year we will be working with
if (isset($_POST["selyear"])) {
	if ($_POST["selyear"]) {
		$outurl = $outurl."&year=".$_POST["selyear"];
	}
} elseif (isset($_GET["year"])) {
	if ($_GET["year"]) {
		$outurl = $outurl."&year=".$_GET["year"];
	}
}

//Capture the month we will be working with
if (isset($_POST["selmonth"])) {
	if ($_POST["selmonth"] <> "") {
		$outurl = $outurl."&month=".$_POST["selmonth"];
	}
} elseif (isset($_GET["month"])) {
	if ($_GET["month"]) {
		$outurl = $outurl."&month=".$_GET["month"]; 
	}
}

header("Location: ".$outurl);
?>

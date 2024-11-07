<?php
if (!isset($_SESSION)) {
    session_start();
}

include("timezone.php");

// Process the 4 possibilities for the year filter: Set in POST, Set in GET, select all possible years, or the default: select the current year
if ( isset($_POST["selyear"]) ) {
	$filteryear = $_POST["selyear"];
} elseif ( isset($_GET["year"])) {
	$filteryear = $_GET["year"];
} else {
	$filteryear = date('Y');
}
if ( $filteryear == "ALL" ) {
	$filteryear = "%";
}

// Process the 4 possibilities for the month filter: Set in POST, Set in GET, select all possible months, or the default: select the current month
if ( isset($_POST["selmonth"]) ) {
	$filtermonth = $_POST["selmonth"];
} elseif ( isset($_GET["month"])) {
	$filtermonth = $_GET["month"];
} else {
	if ( isset($_POST["selyear"]) || isset($_GET["year"]) ) {
		$filtermonth = "%";
	} else {
		$filtermonth = date('F');
	}
}
if ( $filtermonth == "ALL" ) {
	$filtermonth = "%";
}

// Process the 4 possibilities for the profile filter: Set in POST, Set in GET, select all possible profiles, or no filter as default
if ( isset($_POST["selprofile"]) ) {
	$filterprofile = $_POST["selprofile"];
} elseif ( isset($_GET["profile"])) {
	$filterprofile = $_GET["profile"];
} else {
	$filterprofile = "%%";
}
if ( $filterprofile == "ALL" ) {
	$filterprofile = "%%";
}

// Build the MySQL select string based on the inputs (year, month, or session id)
$sessionqrystring = "SELECT time, timeend, session, profileName, sessionsize, ip FROM $db_sessions_table ";
$sqlqryyear = "YEAR(FROM_UNIXTIME(session/1000)) LIKE " . quote_value($filteryear) . " ";
$sqlqrymonth = "MONTHNAME(FROM_UNIXTIME(session/1000)) LIKE " . quote_value($filtermonth) . " ";
$sqlqryprofile = "profileName LIKE " . quote_value($filterprofile) . " " ;
$orselector = "WHERE ";
$andselector = "";
if ( $filteryear <> "%" || $filtermonth <> "%" || $filterprofile <> "%") {
	$orselector = " OR ";
	$sessionqrystring = $sessionqrystring . "WHERE ( ";
	if ( $filteryear <> "%" ) {
		$sessionqrystring = $sessionqrystring . $sqlqryyear;
		$andselector = " AND ";
	}
	if ( $filtermonth <> "%" ) {
		$sessionqrystring = $sessionqrystring . $andselector . $sqlqrymonth;
		$andselector = " AND ";
	}
	if ( $filterprofile <> "%" ) {
		$sessionqrystring = $sessionqrystring . $andselector . $sqlqryprofile;
	}
	$sessionqrystring = $sessionqrystring . " ) ";
}

if ( isset($_GET['id'])) {
	$sessionqrystring = $sessionqrystring . $orselector . "( session LIKE " . quote_value($_GET['id']) . " )";
}

$sessionqrystring = $sessionqrystring . " GROUP BY session, profileName, time, timeend, sessionsize ORDER BY session DESC";

// Get list of unique session IDs
try {
    $sessionqry = $db->query($sessionqrystring);
} catch (Exception $e) { logout_user(); }

// If you get no results, just pull the last 20
if ($sessionqry->num_rows == 0){
	$sessionqry = $db->query("SELECT time, timeend, session, profileName, sessionsize, ip FROM $db_sessions_table GROUP BY session, profileName, time, timeend, sessionsize ORDER BY session DESC LIMIT 20");
}

// Create an array mapping session IDs to date strings
$seshdates = [];
$seshsizes = [];
$seshprofile = [];
$seship = [];
while($row = $sessionqry->fetch_assoc()) {
    $row["timeend"] = !$row["timeend"] ? $row["time"] : $row["timeend"];
    $session_duration_str = gmdate("H:i:s", intval(($row["timeend"] - $row["time"])/1000));
    $session_profileName = $row["profileName"];
    $session_ip = $row["ip"];
    $sid = $row["session"];
    $sids[] = preg_replace('/\D/', '', $sid);
    $seshdates[$sid] = date($_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i", substr($sid, 0, -3));
    $seshsizes[$sid] = " (Length $session_duration_str)";
    $seshprofile[$sid] = " ($session_profileName Profile)";
    $seship[$sid] = " (From ip $session_ip)";
}
?>

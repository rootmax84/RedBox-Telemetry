<?php
require_once('db.php');
require_once('creds.php');
require_once('auth_functions.php');
require_once('auth_user.php');

if(!isset($username) || $username == $admin){
    header("Location: .");
    die;
}

if (isset($_GET["sid"]) && $_GET["sid"]) {
	$session_id = $_GET['sid'];
	// Get data for session
	$output = "";
	$sql = $db->execute_query("SELECT * FROM $db_table WHERE session=? ORDER BY time ASC", [$session_id]);
	$kml = $db->execute_query("SELECT kff1005,kff1006,kff1007 FROM $db_table join $db_sessions_table on $db_table.session = $db_sessions_table.session WHERE $db_table.session=? AND kff1005 > 0 ORDER BY $db_table.time DESC", [$session_id]);
	try {
	    $txt = $db->execute_query("SELECT $db_table.time,k5,k5c,kf,kb4,k46,k2101,kd,kc,kb,k10,k11,ke,k2112,k2100,k2113,k21cc,kff1214,kff1218,k78,k2111,k2119,k1f,k2118,k2120,k2122,k2124,k21e1,k21e2,k2125,k2126,kff1238,k21fa,kff1006,kff1005,kff1001,kff120c FROM $db_table join $db_sessions_table on $db_table.session = $db_sessions_table.session WHERE $db_table.session=? AND $db_sessions_table.id=? ORDER BY $db_table.time ASC", [$session_id, "RedManage"]);
	} catch (Exception $e) {}

	if ($_GET["filetype"] == "kml") {
		$columns_total = $kml->field_count;

		$output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
		$output .="\n";
		$output .= "<kml>";
		$output .="\n";
		$output .= "<Placemark>";
		$output .="\n";
		$output .= "<name>RedBox Telemetry Tracklog</name>";
		$output .="\n";
		$output .= "<LineString>";
		$output .="\n";
		$output .= "<extrude>1</extrude>";
		$output .="\n";
		$output .= "<tessellate>1</tessellate>";
		$output .="\n";
		$output .="<coordinates>";
		$output .="\n";

		// Get Records from the table
		while ($row = $kml->fetch_array()) {

			for ($i = 0; $i < $columns_total; $i++) {
				$output .=$row["$i"].',';
			}
			$output = rtrim($output, ",");
			$output .="\n";
		}
		$output .="</coordinates>";
		$output .="\n";
		$output .= "</LineString>";
		$output .="\n";
		$output .= "</Placemark>";
		$output .="\n";
		$output .= "</kml>";
		$output .="\n";

		// Download the file
		$kmlfilename = "log_session_".$session_id.".kml";
		header('Content-type: application/kml');
		header('Content-Disposition: attachment; filename='.$kmlfilename);

		echo $output;
		exit;
	}

	else if ($_GET["filetype"] == "csv") {
		$columns_total = $sql->field_count;

		// Get The Field Name
	        $properties = $sql->fetch_fields();
		foreach ($properties as $property) {
		$p = $property->name;
		    $output .='"'.$property->name.'",';
		}
		$output .="\n";

		// Get Records from the table
		while ($row = $sql->fetch_array()) {
			for ($i = 0; $i < $columns_total; $i++) {
			     $output .='"'.$row["$i"].'",';
			}
			$output .="\n";
		}

		// Download the file
		$csvfilename = "log_session_".$session_id.".csv";
		header('Content-type: application/csv');
		header('Content-Disposition: attachment; filename='.$csvfilename);

		echo $output;
		exit;
	}

	else if ($_GET["filetype"] == "txt") { //RedManage session export
		$columns_total = $txt->field_count;
		$rows_total = $txt->num_rows;

		if ($rows_total) {
		    // Field Name
		    $output = "TIME ECT EOT IAT ATF AAT EXT SPD RPM MAP MAF TPS IGN INJ INJD IAC AFR O2S O2S2 EGT EOP FP ERT MHS BSTD FAN GEAR BS1 BS2 PG0 PG1 VLT RLC GLAT GLON GSPD ODO\n";

		// Get Records from the table
		    while ($row = $txt->fetch_array()) {
			for ($i = 0; $i < $columns_total; $i++) {
			    $output .= $row["$i"].' ';
			}
			    $output = rtrim($output);
			    $output .="\n";
		    }
		} else {
		    $output = "This is not RedManage session";
		}

		// Download the file
		$txtfilename = "rbx_log_".$session_id.".txt";
		header('Content-type: application/txt');
		header('Content-Disposition: attachment; filename='.$txtfilename);

		echo $output;
		exit;
	}

	else if ($_GET["filetype"] == "json") {
		$rows = array();
		while($r = $sql->fetch_assoc()) {
			$rows[] = $r;
		}

		$jsonrows = json_encode($rows);

		// Download the file
		$jsonfilename = "log_session_".$session_id.".json";
		header('Content-type: application/json');
		header('Content-Disposition: attachment; filename='.$jsonfilename);

		echo $jsonrows;
	}
}

else header('Location: .');

$db->close();

?>

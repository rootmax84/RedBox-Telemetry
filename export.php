<?php
require_once('db.php');
require_once('creds.php');
require_once('auth_functions.php');
require_once('auth_user.php');

if(!isset($username) || $username == $admin){
    header("Location:/");
    die;
}

if (isset($_GET["sid"]) && $_GET["sid"]) {
	$session_id = $_GET['sid'];
	// Get data for session
	$output = "";
	$sql = $db->execute_query("SELECT * FROM $db_table WHERE session=? ORDER BY time ASC", [$session_id]);
	$kml = $db->execute_query("SELECT kff1005,kff1006,kff1007 FROM $db_table join $db_sessions_table on $db_table.session = $db_sessions_table.session WHERE $db_table.session=? AND kff1005 > 0 ORDER BY $db_table.time DESC", [$session_id]);

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
		$boost = 0;
	        $properties = $sql->fetch_fields();
		$cnt = 0;
		foreach ($properties as $property) {
		$p = $property->name;
		    $cnt++;
		    if ($p == "kff1202") $boost = $cnt - 1;
		    if ($p != "session" && $p != "id" && $p != "time")
		    $output .='"'.$property->name.'",';
		}
		$output .="\n";

		// Get Records from the table
		$skip_boost_calc = false;
		while ($row = $sql->fetch_array()) {
			for ($i = 0; $i < $columns_total; $i++) {
			 if ($row["$i"] == "RedManage") $skip_boost_calc = true;
			 if ($i > 2) { //Skip first 3 columns
			    if ($i == $boost && $use_bar && !$skip_boost_calc) $output .='"'.round($row["$i"]/14.504,2).'",'; //round float by 2 psi/bar conversion
			    else $output .='"'.$row["$i"].'",';
			    }
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

	else if ($_GET["filetype"] == "json") {
		$rows = array();
		while($r = $sql->fetch_assoc()) {
			$rows[] = $r;
		}

		if ($rows[0]["id"] != "RedManage") {
		    for ($i = 0; $i < sizeof($rows); $i++){ //convert boost
		      if (!$use_bar) break;
			$rows[$i]["kff1202"] = round($rows[$i]["kff1202"]/14.504,2);
		    }
		}

		for ($i = 0; $i < sizeof($rows); $i++){ //Skip fist 3 columns
		    unset($rows[$i]["id"]);
		    unset($rows[$i]["session"]);
		    unset($rows[$i]["time"]);
		}

		$jsonrows = json_encode($rows);

		// Download the file
		$jsonfilename = "log_session_".$session_id.".json";
		header('Content-type: application/json');
		header('Content-Disposition: attachment; filename='.$jsonfilename);

		echo $jsonrows;
	}
}

else header('Location:/');

$db->close();

?>

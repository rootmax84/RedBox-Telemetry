<?php
try {
if (!$_COOKIE['stream']) {
 header('HTTP/1.0 401 Unauthorized');
}
require_once('db.php');
include ("timezone.php");

if (isset($_SESSION['admin'])) header("Refresh:0; url=.");

$db_limit = $db->execute_query("SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [$db_name, $db_table])->fetch_row()[0];

$ok = 0;
$files = [];

//Exceed php_post_size
if (!isset($_FILES['file'])) {
 header('HTTP/1.0 406');
 die("POST max size exceed!");
}

//Convert to simply array
foreach($_FILES['file'] as $k => $l) {
    foreach($l as $i => $v) {
	$files[$i][$k] = $v;
    }
}

if(count($files) > 10) { //10 files per upload limit
  header('HTTP/1.0 406');
  echo "Acceptable 10 files per upload!";
  die;
}

$target_file = [];
for ($f = 0; $f < count($files); $f++) {
 $target_file[$f] = '/tmp/' . basename($files[$f]['name']);
 if (!move_uploaded_file($files[$f]['tmp_name'], $target_file[$f]) ) {
  header('HTTP/1.0 406');
  die("Server error!");
 }

 $data = file_get_contents($target_file[$f]);
 $data_size = filesize($target_file[$f])/1048576; //Size in mb

 //Check size limit (5MB per file MAX)
 if ($data_size > 5) {
  if (file_exists($target_file[$f])) unlink($target_file[$f]);
  header('HTTP/1.0 406');
  echo $files[$f]['name'] . " too big!";
  die;
 }
 if ($db_limit >= $limit || $data_size >= $limit || ($db_limit+$data_size) >= $limit) {
  if (file_exists($target_file[$f])) unlink($target_file[$f]);
  header('HTTP/1.0 406');
  die("No space left or file(s) too big!"); //No space left. Stops
 }
 else if (!$data || !$data_size || !str_contains($data, "TIME ECT EOT IAT ATF AAT EXT SPD RPM MAP MAF TPS IGN INJ INJD IAC AFR O2S O2S2 EGT EOP FP ERT MHS BSTD FAN GEAR BS1 BS2 PG0 PG1 VLT RLC GLAT GLON GSPD ODO\n")) {
  if (file_exists($target_file[$f])) unlink($target_file[$f]);
  header('HTTP/1.0 406');
  echo $files[$f]['name'] . " is broken or empty!";
  die;
 }
 else {
  $data = str_replace("TIME ECT EOT IAT ATF AAT EXT SPD RPM MAP MAF TPS IGN INJ INJD IAC AFR O2S O2S2 EGT EOP FP ERT MHS BSTD FAN GEAR BS1 BS2 PG0 PG1 VLT RLC GLAT GLON GSPD ODO\n", "", $data);
  $data = str_replace("\n", ' ', $data);
  $data = explode(" ", $data);

  //Create session
  $session = $data[0] - 1000;
  $size = count(file($target_file[$f])) - 1;
  $time = $data[0];
  $time_end = $data[ array_key_last($data) - 37 ];
 }
 try {
  $db->execute_query("INSERT INTO $db_sessions_table (id, session, time, profileName, timeend, sessionsize) VALUES (?,?,?,?,?,?)", ['RedManage', $session, $time, 'RedManage-Log', $time_end, $size]);
 } catch (Exception $e) {
  if (file_exists($target_file[$f])) unlink($target_file[$f]);
  header('HTTP/1.0 406');
  echo $files[$f]['name'] . " is duplicate!";
  die;
 } //Stop on duplicate

 //Alter table if removed pid by user
 $alter = "ALTER TABLE $db_table ADD IF NOT EXISTS(k21fa float NOT NULL default 0,
  kff1202 float NOT NULL default 0,
  k5 float NOT NULL default 0,
  k5c float NOT NULL default 0,
  kf float NOT NULL default 0,
  kb4 float NOT NULL default 0,
  kc float NOT NULL default 0,
  kb float NOT NULL default 0,
  k1f float NOT NULL default 0,
  k2118 float NOT NULL default 0,
  k2120 float NOT NULL default 0,
  k2122 float NOT NULL default 0,
  k2125 float NOT NULL default 0,
  kff1238 float NOT NULL default 0,
  k46 float NOT NULL default 0,
  k2101 float NOT NULL default 0,
  kd float NOT NULL default 0,
  k10 float NOT NULL default 0,
  k11 float NOT NULL default 0,
  ke float NOT NULL default 0,
  k2112 float NOT NULL default 0,
  k2100 float NOT NULL default 0,
  k2113 float NOT NULL default 0,
  k21cc float NOT NULL default 0,
  kff1214 float NOT NULL default 0,
  kff1218 float NOT NULL default 0,
  k78 float NOT NULL default 0,
  k2111 float NOT NULL default 0,
  k2119 float NOT NULL default 0,
  k2124 float NOT NULL default 0,
  k21e1 float NOT NULL default 0,
  k21e2 float NOT NULL default 0,
  k2126 float NOT NULL default 0,
  kff120c float NOT NULL default 0,
  kff1001 float NOT NULL default 0)";
 $db->query($alter);

 //insert missed pids
 $alter = "INSERT IGNORE INTO $db_pids_table (id, description, units, populated, stream) VALUES
 ('k10',	'Mass Air Flow Rate',				'g/sec',	1,	1),
 ('k11',	'Throttle Position (Manifold)',			'%',		1,	1),
 ('k1f',	'Run Time Since Engine Start',			's',		1,	1),
 ('k2100',	'Injector duty',				'%',		1,	1),
 ('k2101',	'EXT temperature',				'°C',		1,	1),
 ('k2111',	'Engine Oil Pressure',				'Bar',		1,	1),
 ('k2112',	'Injection time',				'ms',		1,	1),
 ('k2113',	'Idle Air Control',				'%',		1,	1),
 ('k2118',	'Motorhours',					'H',		1,	1),
 ('k2120',	'Boost solenoid duty',				'%',		1,	1),
 ('k2122',	'Fan Status',					'%',		1,	1),
 ('k2124',	'Gear',						NULL,		1,	1),
 ('k2125',	'PG0 Output',					NULL,		1,	1),
 ('k2126',	'PG1 Output',					NULL,		1,	1),
 ('k21cc',	'Air Fuel Ratio',				NULL,		1,	1),
 ('k21e1',	'BS1 Input',					NULL,		1,	1),
 ('k21e2',	'BS2 Input',					NULL,		1,	1),
 ('k21fa',	'Rollback',					NULL,		1,	1),
 ('k46',	'Ambient Air Temp',				'°C',		1,	1),
 ('k5',		'Engine Coolant Temperature',			'°C',		1,	1),
 ('k5c',	'Engine Oil Temperature',			'°C',		1,	1),
 ('k78',	'EGT',						'°C',		1,	1),
 ('k2119',	'Fuel Pressure',				'Bar',		1,	1),
 ('kb',		'Intake Manifold Pressure',			'kPa',		1,	1),
 ('kb4',	'Transmission Temperature (Method 2)',		'°C',		1,	1),
 ('kc',		'Engine RPM x 100',				'rpm',		1,	1),
 ('kd',		'Speed (OBD)',					'km/h',		1,	1),
 ('ke',		'Ignition Advance',				'°',		1,	1),
 ('kf',		'Intake Air Temperature',			'°C',		1,	1),
 ('kff1001',	'Speed (GPS)',					'km/h',		1,	1),
 ('kff1005',	'GPS Longitude',				'°',		0,	0),
 ('kff1006',	'GPS Latitude',					'°',		0,	0),
 ('kff1007',	'GPS Bearing (Used in GPS records)',		NULL,		0,	0),
 ('kff1202',	'Boost',					'Bar',		1,	1),
 ('kff1204',	'Trip Distance',				'km',		1,	1),
 ('kff120c',	'Trip Distance (Stored in Vehicle Profile)',	'km',		1,	1),
 ('kff1214',	'O2 Volts Bank 1 Sensor 1',			'V',		1,	1),
 ('kff1218',	'O2 Volts Bank 2 Sensor 1',			'V',		1,	1),
 ('kff1238',	'Voltage (OBD Adapter)',			'V',		1,	1)";
 $db->query($alter);

 //Insert data
 for ($i = 0; $i < sizeof($data)-1; $i+=37) {
    $time= $data[$i];
    $ect = $data[$i+1];
    $eot = $data[$i+2];
    $iat = $data[$i+3];
    $atf = $data[$i+4];
    $aat = $data[$i+5];
    $ext = $data[$i+6];
    $spd = $data[$i+7];
    $rpm = $data[$i+8];
    $map = $data[$i+9];
    $boost = ($data[$i+9]-101)/100;
    $maf = $data[$i+10];
    $tps = $data[$i+11];
    $ign = $data[$i+12];
    $inj = $data[$i+13];
    $injd = $data[$i+14];
    $iac = $data[$i+15];
    $afr = $data[$i+16];
    $o2s = $data[$i+17];
    $o2s2 = $data[$i+18];
    $egt = $data[$i+19];
    $eop = $data[$i+20];
    $fp = $data[$i+21];
    $ert = $data[$i+22];
    $mhs = $data[$i+23];
    $bstd = $data[$i+24];
    $fan = $data[$i+25];
    $gear = $data[$i+26];
    $bs1 = $data[$i+27];
    $bs2 = $data[$i+28];
    $pg0 = $data[$i+29];
    $pg1 = $data[$i+30];
    $vlt = $data[$i+31];
    $rlc = $data[$i+32];
    $glat = $data[$i+33];
    $glon = $data[$i+34];
    $gspd = $data[$i+35];
    $odo = $data[$i+36];

    // Prepare data for insertion
    $insertData = [
        $session, $time, $glon, $glat, $rlc, $boost, $ect, $eot, $iat, $atf, $rpm, $map, $ert, $mhs, $bstd, $fan, $pg0, $vlt,
        $aat, $ext, $spd, $maf, $tps, $ign, $inj, $injd, $iac, $afr, $o2s, $o2s2, $egt, $eop, $fp, $gear, $bs1, $bs2, $pg1,
        $gspd, $odo
    ];

    try {
        $db->execute_query("INSERT INTO $db_table (session, time, kff1005, kff1006, k21fa, kff1202, k5, k5c, kf, kb4, kc, kb, k1f, k2118, k2120, k2122, k2125, kff1238, k46, k2101, kd, k10, k11, ke, k2112, k2100, k2113, k21cc, kff1214, kff1218, k78, k2111, k2119, k2124, k21e1, k21e2, k2126, kff1001, kff120c) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", $insertData);
    } catch (Exception $e) {
        header('HTTP/1.0 406');
        echo $files[$f]['name'] . " is duplicate!";
        $db->execute_query("DELETE FROM $db_table WHERE session=?", [$session]);
        $db->execute_query("DELETE FROM $db_sessions_table WHERE session=?", [$session]);
        die;
    }
}
 $ok++;
 unlink($target_file[$f]);
}

echo $ok . " files uploaded successfully";
$db->close();

} catch (TypeError $e) {
    header('HTTP/1.0 406');
    echo $files[$f]['name'] . " is broken!";
    $db->execute_query("DELETE FROM $db_table WHERE session=?", [$session]);
    $db->execute_query("DELETE FROM $db_sessions_table WHERE session=?", [$session]);
    die;
}
?>

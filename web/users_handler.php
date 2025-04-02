<?php
require_once ('token_functions.php');
require_once ('auth_functions.php');
require_once ('db.php');
require_once ('parse_functions.php');
include_once ('translations.php');

if (isset($_POST['speed'], $_POST['temp'], $_POST['pressure'], $_POST['boost'], $_POST['time'], $_POST['gap'], $_POST['stream_lock'], $_POST['sessions_filter'], $_POST['user_share']) && isset($username) && $username != $admin){ //Update users settings
    $share_key = $_POST['user_share'] ? shareKey() : NULL;
    $_SESSION['share_key'] = $share_key;
    $db->execute_query("UPDATE $db_users SET speed=?, temp=?, pressure=?, boost=?, time=?, gap=?, stream_lock=?, sessions_filter=?, share=? WHERE user=?", [$_POST['speed'], $_POST['temp'], $_POST['pressure'], $_POST['boost'], $_POST['time'], $_POST['gap'], $_POST['stream_lock'], $_POST['sessions_filter'], $share_key, $username]);
    $db->close();
    setcookie("timeformat", $_POST['time'] == '1' ? '24' : '12');
    setcookie("gap", $_POST['gap']);
    $_SESSION['sessions_filter'] = $_POST['sessions_filter'];
    cache_flush();
    die($translations[$_COOKIE['lang']]['set.common.updated']);
}

else if (isset($_GET['get_token']) && isset($username) && $username != $admin){ //Get current user token
    $row = $db->execute_query("SELECT token FROM $db_users WHERE user=?", [$username])->fetch_assoc();
    $token = $row["token"];
    $token = $token === NULL ? $translations[$_COOKIE['lang']]['new.token'] : $token;
    $db->close();
    die($token);
}
else if (isset($_GET['renew_token']) && isset($username) && $username != $admin){ //Renew token by user action
    $db->execute_query("UPDATE $db_users SET token=? WHERE user=?", [generate_token($username), $username]);
    $db->close();
    die($translations[$_COOKIE['lang']]['set.token.updated']);
}
else if (isset($_POST['old_p']) && isset($_POST['new_p1']) && isset($_POST['new_p2']) && isset($username) && $username != $admin){ // Change password by user action
    $old_p = $_POST['old_p'];
    $new_p1 = $_POST['new_p1'];
    $new_p2 = $_POST['new_p2'];
    $row = $db->execute_query("SELECT id, pass FROM $db_users WHERE user=?", [$username])->fetch_assoc();
     if (password_verify($old_p, $row["pass"])) {
     if ($new_p1 != $new_p2) die ($translations[$_COOKIE['lang']]['set.pwd.not.match']);
     if (strlen($new_p1) < 8 || strlen($new_p2) < 8) die($translations[$_COOKIE['lang']]['set.pwd.short']);
     if ($old_p == $new_p1 || $old_p == $new_p2) die($translations[$_COOKIE['lang']]['set.pwd.same']);
     $new_pass = $new_p2;
     if (!preg_match("#[0-9]+#", $new_pass)) die($translations[$_COOKIE['lang']]['set.pwd.number']);
     if (!preg_match("#[a-zA-Z]+#", $new_pass)) die($translations[$_COOKIE['lang']]['set.pwd.char']);
     $db->execute_query("UPDATE $db_users SET pass=? WHERE id=?", [password_hash($new_pass, PASSWORD_DEFAULT, $salt), $row['id']]);
     $db->close();
     die($translations[$_COOKIE['lang']]['set.pwd.changed']);
    }
    else die($translations[$_COOKIE['lang']]['set.pwd.wrong.curr']);
}
else if (isset($_POST['tg_token']) && isset($_POST['tg_chatid']) && isset($username) && $username != $admin){ //Set telegram bot creds for notifying
    $db->execute_query("UPDATE $db_users SET tg_token=?, tg_chatid=? WHERE user=?", [$_POST['tg_token'], $_POST['tg_chatid'], $username]);
    $db->close();
    $response = notify("RedBox Telemetry test message", $_POST['tg_token'], $_POST['tg_chatid']); //Send test message
    die($response == NULL ? $translations[$_COOKIE['lang']]['set.nothing'] : ($response['ok'] ? $translations[$_COOKIE['lang']]['set.tg.send'] : $response['description']));
}
else if (isset($_POST['forward_url']) && isset($username) && $username != $admin){ //Set forward url
    if (isValidExternalHttpUrl($_POST['forward_url']) || empty($_POST['forward_url'])) {
        $row = $db->execute_query("SELECT token FROM $db_users WHERE user=?", [$username])->fetch_assoc();
        $db->execute_query("UPDATE $db_users SET forward_url=? WHERE user=?", [$_POST['forward_url'], $username]);
        $db->close();
        cache_flush($row["token"]);
        die($translations[$_COOKIE['lang']]['user.url.updated']);
    } else {
        die($translations[$_COOKIE['lang']]['user.url.err']);
    }
}
if (!isset($_SESSION['admin'])) {
    header("Location: .");
    die;
}
else
{ try {
    if (isset($_POST['e_login'])){ //edit user
	$login = $_POST['e_login'];
	$password = $_POST['e_pass'];
	$e_limit = $_POST['e_limit'];

	if ($login == $admin && $e_limit != NULL) die("Can't set limit for admin");

	$row = $db->execute_query("SELECT id, token FROM $db_users WHERE user=?", [$login])->fetch_assoc();
	if (!$row) die($translations[$_COOKIE['lang']]['admin.user.not.found'].$login);

	if (strlen($password) > 1 && strlen($password) < 5) die($translations[$_COOKIE['lang']]['admin.pwd.short']);
	if (!strlen($e_limit) && !strlen($password)) die($translations[$_COOKIE['lang']]['set.nothing']);

	if (!strlen($password) && strlen($e_limit)) { //Change only limit
	 $db->execute_query("UPDATE $db_users SET s=? WHERE id=?", [$e_limit, $row['id']]);
	 $msg = $translations[$_COOKIE['lang']]['admin.limit.changed'].$login;
	}
	else if (strlen($password) && !strlen($e_limit)) { //Change only password
	 $db->execute_query("UPDATE $db_users SET pass=? WHERE id=?", [password_hash($password, PASSWORD_DEFAULT, $salt), $row['id']]);
	 $msg = $translations[$_COOKIE['lang']]['admin.pwd.changed'].$login;
	}
	else { // Change password and limit
	 $db->execute_query("UPDATE $db_users SET pass=?, s=? WHERE id=?", [password_hash($password, PASSWORD_DEFAULT, $salt), $e_limit, $row['id']]);
	 $msg = $translations[$_COOKIE['lang']]['admin.changed'].$login;
	}
	$username = $login;
	cache_flush($row['token']);
	$db->close();
	die($msg);
    }

    else if (isset($_POST['reg_login']) && isset($_POST['reg_pass'])){ //add user
	$login = $_POST['reg_login'];
	$password = $_POST['reg_pass'];

	$userqry = $db->execute_query("SELECT id FROM $db_users WHERE user=?", [$login]);
	if ($userqry->num_rows || strlen($login) < 1) die($translations[$_COOKIE['lang']]['admin.user.exists']);

	if (strlen($password) < 5) die($translations[$_COOKIE['lang']]['admin.pwd.short']);

	$logs_table = "CREATE TABLE ".$login.$db_log_prefix." (
			session bigint(20) unsigned NOT NULL,
			time bigint(20) unsigned NOT NULL,
			kff1005	double NOT NULL DEFAULT 0,
			kff1006	double NOT NULL DEFAULT 0,
			kff1007	float NOT NULL DEFAULT 0,
			k21fa	float NOT NULL DEFAULT 0,
			kff1202	float NOT NULL DEFAULT 0,
			k5	float NOT NULL DEFAULT 0,
			k5c	float NOT NULL DEFAULT 0,
			kf	float NOT NULL DEFAULT 0,
			kb4	float NOT NULL DEFAULT 0,
			kc	float NOT NULL DEFAULT 0,
			kb	float NOT NULL DEFAULT 0,
			k1f	float NOT NULL DEFAULT 0,
			k2118	float NOT NULL DEFAULT 0,
			k2120	float NOT NULL DEFAULT 0,
			k2122	float NOT NULL DEFAULT 0,
			k2125	float NOT NULL DEFAULT 0,
			kff1238	float NOT NULL DEFAULT 0,
			k46	float NOT NULL DEFAULT 0,
			k2101	float NOT NULL DEFAULT 0,
			kd	float NOT NULL DEFAULT 0,
			k10	float NOT NULL DEFAULT 0,
			k11	float NOT NULL DEFAULT 0,
			ke	float NOT NULL DEFAULT 0,
			k2112	float NOT NULL DEFAULT 0,
			k2100	float NOT NULL DEFAULT 0,
			k2113	float NOT NULL DEFAULT 0,
			k21cc	float NOT NULL DEFAULT 0,
			kff1214	float NOT NULL DEFAULT 0,
			kff1218	float NOT NULL DEFAULT 0,
			k78	float NOT NULL DEFAULT 0,
			k2111	float NOT NULL DEFAULT 0,
			k2119	float NOT NULL DEFAULT 0,
			k2124	float NOT NULL DEFAULT 0,
			k21e1	float NOT NULL DEFAULT 0,
			k21e2	float NOT NULL DEFAULT 0,
			k2126	float NOT NULL DEFAULT 0,
			kff120c	float NOT NULL DEFAULT 0,
			kff1001	float NOT NULL DEFAULT 0,
			kff1204	float NOT NULL DEFAULT 0,
			KEY session_kff1005_kff1006 (session,kff1005,kff1006),
			PRIMARY KEY (time)) ENGINE=".$db_engine."";

	$sessions_table = "CREATE TABLE ".$login.$db_sessions_prefix." (
			id varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '-',
			profileName varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Not Specified',
			ip char(15) NOT NULL DEFAULT '0.0.0.0',
			sessionsize mediumint(8) unsigned NOT NULL DEFAULT 0,
			session bigint(20) unsigned NOT NULL,
			time bigint(20) unsigned NOT NULL,
			timeend bigint(20) unsigned NOT NULL,
			profileFuelType float NOT NULL DEFAULT 0,
			profileWeight float NOT NULL DEFAULT 0,
			profileVe float NOT NULL DEFAULT 0,
			profileFuelCost float NOT NULL DEFAULT 0,
			profileDisplacement float NOT NULL DEFAULT 0,
			profileTankCapacity float NOT NULL DEFAULT 0,
			profileTankUsed float NOT NULL DEFAULT 0,
			profileVehicleType float NOT NULL DEFAULT 0,
			profileOdometer float NOT NULL DEFAULT 0,
			profileMPGAdjust float NOT NULL DEFAULT 0,
			profileBoostAdjust float NOT NULL DEFAULT 0,
			profileDragCoeff float NOT NULL DEFAULT 0,
			profileOBDAdjust float NOT NULL DEFAULT 0,
			UNIQUE KEY session_key (session),
			KEY timeend_index (timeend)) ENGINE=".$db_engine."";

	$pids_table = "CREATE TABLE ".$login.$db_pids_prefix." (
			id varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
			description varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Description',
			units varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Units',
			populated tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is This Variable Populated?',
			stream tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is This Variable show in Stream?',
			favorite tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is This Variable show as default?',
			UNIQUE KEY id (id)) ENGINE=".$db_engine."";

	$pids_values = "INSERT INTO ".$login.$db_pids_prefix." (id, description, units, populated, stream, favorite) VALUES
			('k10',		'Mass Air Flow Rate',				'g/sec',	1,	1,	0),
			('k11',		'Throttle Position (Manifold)',			'%',		1,	1,	0),
			('k1f',		'Run Time Since Engine Start',			's',		1,	1,	0),
			('k2100',	'Injector duty',				'%',		1,	1,	0),
			('k2101',	'EXT temperature',				'°C',		1,	1,	0),
			('k2111',	'Engine Oil Pressure',				'Bar',		1,	1,	0),
			('k2112',	'Injection time',				'ms',		1,	1,	0),
			('k2113',	'Idle Air Control',				'%',		1,	1,	0),
			('k2118',	'Motorhours',					'H',		1,	1,	0),
			('k2120',	'Boost solenoid duty',				'%',		1,	1,	0),
			('k2122',	'Fan Status',					'%',		1,	1,	0),
			('k2124',	'Gear',						NULL,		1,	1,	0),
			('k2125',	'PG0 Output',					NULL,		1,	1,	0),
			('k2126',	'PG1 Output',					NULL,		1,	1,	0),
			('k21cc',	'Air Fuel Ratio',				NULL,		1,	1,	0),
			('k21e1',	'BS1 Input',					NULL,		1,	1,	0),
			('k21e2',	'BS2 Input',					NULL,		1,	1,	0),
			('k21fa',	'Rollback',					NULL,		1,	1,	0),
			('k46',		'Ambient Air Temp',				'°C',		1,	1,	0),
			('k5',		'Engine Coolant Temperature',			'°C',		1,	1,	0),
			('k5c',		'Engine Oil Temperature',			'°C',		1,	1,	0),
			('k78',		'EGT',						'°C',		1,	1,	0),
			('k2119',	'Fuel Pressure',				'Bar',		1,	1,	0),
			('kb',		'Intake Manifold Pressure',			'kPa',		1,	1,	0),
			('kb4',		'Transmission Temperature (Method 2)',		'°C',		1,	1,	0),
			('kc',		'Engine RPM x 100',				'rpm',		1,	1,	0),
			('kd',		'Speed (OBD)',					'km/h',		1,	1,	0),
			('ke',		'Ignition Advance',				'°',		1,	1,	0),
			('kf',		'Intake Air Temperature',			'°C',		1,	1,	0),
			('kff1001',	'Speed (GPS)',					'km/h',		1,	1,	0),
			('kff1005',	'GPS Longitude',				'°',		0,	0,	0),
			('kff1006',	'GPS Latitude',					'°',		0,	0,	0),
			('kff1007',	'GPS Bearing (Used in GPS records)',		NULL,		0,	0,	0),
			('kff1202',	'Boost',					'Bar',		1,	1,	0),
			('kff1204',	'Trip Distance',				'km',		1,	1,	0),
			('kff120c',	'Trip Distance (Stored in Vehicle Profile)',	'km',		1,	1,	0),
			('kff1214',	'O2 Volts Bank 1 Sensor 1',			'V',		1,	1,	0),
			('kff1218',	'O2 Volts Bank 2 Sensor 1',			'V',		1,	1,	0),
			('kff1238',	'Voltage (OBD Adapter)',			'V',		1,	1,	0)";

	$pids_legacy_values = "INSERT INTO ".$login.$db_pids_prefix." (id, description, units, populated, stream, favorite) VALUES
			('kff122e',	'0-100kph Time',					's',		0,0,0),
			('kff1278',	'0-100mph Time',					's',		0,0,0),
			('kff124f',	'0-200kph Time',					's',		0,0,0),
			('kff1277',	'0-30mph Time',						's',		0,0,0),
			('kff122d',	'0-60mph Time',						's',		0,0,0),
			('kff122f',	'1/4 mile Time',					's',		0,0,0),
			('kff1230',	'1/8 mile Time',					's',		0,0,0),
			('kff1264',	'100-0kph Time',					's',		0,0,0),
			('kff1280',	'100-200kph Time',					's',		0,0,0),
			('kff1260',	'40-60mph Time',					's',		0,0,0),
			('kff1265',	'60-0mph Time',						's',		0,0,0),
			('kff125e',	'60-120mph Time',					's',		0,0,0),
			('kff1276',	'60-130mph Time',					's',		0,0,0),
			('kff125f',	'60-80mph Time',					's',		0,0,0),
			('kff1261',	'80-100mph Time',					's',		0,0,0),
			('kff1275',	'80-120kph Time',					's',		0,0,0),
			('k47',		'Absolute Throttle Position B',				'%',		0,0,0),
			('kff1223',	'Acceleration Sensor (Total)',				'g',		0,0,0),
			('kff1220',	'Acceleration Sensor (X Axis)',				'g',		0,0,0),
			('kff1221',	'Acceleration Sensor (Y Axis)',				'g',		0,0,0),
			('kff1222',	'Acceleration Sensor (Z Axis)',				'g',		0,0,0),
			('k49',		'Accelerator Pedal Position D',				'%',		0,0,0),
			('k4a',		'Accelerator Pedal Position E',				'%',		0,0,0),
			('k4b',		'Accelerator Pedal Position F',				'%',		0,0,0),
			('kff124d',	'Air Fuel Ratio (Commanded)',				NULL,		0,0,0),
			('kff1249',	'Air Fuel Ratio (Measured)',				NULL,		0,0,0),
			('k12',		'Air Status',						NULL,		0,0,0),
			('kff129a',	'Android Device Battery Level',				'%',		0,0,0),
			('kff1263',	'Average Trip Speed (Whilst Moving Only)',		'km/h',		0,0,0),
			('kff1272',	'Average Trip Speed (Whilst Stopped or Moving)',	'km/h',		0,0,0),
			('kff1270',	'Barometer (On Android device)',			'mb',		0,0,0),
			('k33',		'Barometric Pressure (From Vehicle)',			'kPa',		0,0,0),
			('k3c',		'Catalyst Temperature (Bank 1 Sensor 1)',		'°C',		0,0,0),
			('k3e',		'Catalyst Temperature (Bank 1 Sensor 2)',		'°C',		0,0,0),
			('k3d',		'Catalyst Temperature (Bank 2 Sensor 1)',		'°C',		0,0,0),
			('k3f',		'Catalyst Temperature (Bank 2 Sensor 2)',		'°C',		0,0,0),
			('kff1258',	'CO2 (Average)',					'g/km',		0,0,0),
			('kff1257',	'CO2 (Instantaneous)',					'g/km',		0,0,0),
			('k44',		'Commanded Equivalence Ratio (lambda)',			NULL,		0,0,0),
			('kff126d',	'Cost per mile/km (Instant)',				'$/km',		0,0,0),
			('kff126e',	'Cost per mile/km (Trip)',				'$/km',		0,0,0),
			('kff126a',	'Distance to empty (Estimated)',			'km',		0,0,0),
			('k31',		'Distance Travelled Since Codes Cleared',		'km',		0,0,0),
			('k21',		'Distance Travelled With MIL/CEL Lit',			'km',		0,0,0),
			('k2c',		'EGR Commanded',					'%',		0,0,0),
			('k2d',		'EGR Error',						'%',		0,0,0),
			('kff1273',	'Engine kW (At the Wheels)',				'kW',		0,0,0),
			('k4',		'Engine Load',						'%',		0,0,0),
			('k43',		'Engine Load (Absolute)',				'%',		0,0,0),
			('k52',		'Ethanol Fuel %',					'%',		0,0,0),
			('k32',		'Evap System Vapor Pressure',				'Pa',		0,0,0),
			('k79',		'Exhaust Gas Temperature Bank 2 Sensor 1',		'°C',		0,0,0),
			('kff125c',	'Fuel Cost (Trip)',					'$',		0,0,0),
			('kff125d',	'Fuel Flow Rate/Hour',					'l/hr',		0,0,0),
			('kff125a',	'Fuel Flow Rate/Minute',				'cc/min',	0,0,0),
			('k2f',		'Fuel Level (From Engine ECU)',				'%',		0,0,0),
			('ka',		'Fuel Pressure legacy',					'kPa',		0,0,0),
			('k23',		'Fuel Rail Pressure',					'kPa',		0,0,0),
			('k22',		'Fuel Rail Pressure (Relative to Manifold Vacuum)',	'kPa',		0,0,0),
			('kff126b',	'Fuel Remaining (Calculated From Vehicle Profile)',	'%',		0,0,0),
			('k3',		'Fuel Status',						NULL,		0,0,0),
			('k7',		'Fuel Trim Bank 1 Long Term',				'%',		0,0,0),
			('k14',		'Fuel Trim Bank 1 Sensor 1',				'%',		0,0,0),
			('k15',		'Fuel Trim Bank 1 Sensor 2',				'%',		0,0,0),
			('k16',		'Fuel Trim Bank 1 Sensor 3',				'%',		0,0,0),
			('k17',		'Fuel Trim Bank 1 Sensor 4',				'%',		0,0,0),
			('k6',		'Fuel Trim Bank 1 Short Term',				'%',		0,0,0),
			('k9',		'Fuel Trim Bank 2 Long Term',				'%',		0,0,0),
			('k18',		'Fuel Trim Bank 2 Sensor 1',				'%',		0,0,0),
			('k19',		'Fuel Trim Bank 2 Sensor 2',				'%',		0,0,0),
			('k1a',		'Fuel Trim Bank 2 Sensor 3',				'%',		0,0,0),
			('k1b',		'Fuel Trim Bank 2 Sensor 4',				'%',		0,0,0),
			('k8',		'Fuel Trim Bank 2 Short Term',				'%',		0,0,0),
			('kff1271',	'Fuel Used (Trip)',					'l',		0,0,0),
			('kff1239',	'GPS Accuracy',						'm',		0,0,0),
			('kff1010',	'GPS Altitude',						'm',		0,0,0),
			('kff123a',	'GPS Satellites',					NULL,		0,0,0),
			('kff1237',	'GPS vs OBD Speed Difference',				'km/h',		0,0,0),
			('kff1226',	'Horsepower (At the Wheels)',				'hp',		0,0,0),
			('kff1203',	'Kilometers Per Litre (Instant)',			'kpl',		0,0,0),
			('kff5202',	'Kilometers Per Litre (Long Term Average)',		'kpl',		0,0,0),
			('kff1207',	'Litres Per 100 Kilometer (Instant)',			'l/100km',	0,0,0),
			('kff5203',	'Litres Per 100 Kilometer (Long Term Average)',		'l/100km',	0,0,0),
			('kff1201',	'Miles Per Gallon (Instant)',				'mpg',		0,0,0),
			('kff5201',	'Miles Per Gallon (Long Term Average)',			'mpg',		0,0,0),
			('k24',		'O2 Sensor1 Equivalence Ratio',				NULL,		0,0,0),
			('k34',		'O2 Sensor1 Equivalence Ratio (Alternate)',		NULL,		0,0,0),
			('kff1240',	'O2 Sensor1 Wide-range Voltage',			'V',		0,0,0),
			('k25',		'O2 Sensor2 Equivalence Ratio',				NULL,		0,0,0),
			('kff1241',	'O2 Sensor2 Wide-range Voltage',			'V',		0,0,0),
			('k26',		'O2 Sensor3 Equivalence Ratio',				NULL,		0,0,0),
			('kff1242',	'O2 Sensor3 Wide-range Voltage',			'V',		0,0,0),
			('k27',		'O2 Sensor4 Equivalence Ratio',				NULL,		0,0,0),
			('kff1243',	'O2 Sensor4 Wide-range Voltage',			'V',		0,0,0),
			('k28',		'O2 Sensor5 Equivalence Ratio',				NULL,		0,0,0),
			('kff1244',	'O2 Sensor5 Wide-range Voltage',			'V',		0,0,0),
			('k29',		'O2 Sensor6 Equivalence Ratio',				NULL,		0,0,0),
			('kff1245',	'O2 Sensor6 Wide-range Voltage',			'V',		0,0,0),
			('k2a',		'O2 Sensor7 Equivalence Ratio',				NULL,		0,0,0),
			('kff1246',	'O2 Sensor7 Wide-range Voltage',			'V',		0,0,0),
			('k2b',		'O2 Sensor8 Equivalence Ratio',				NULL,		0,0,0),
			('kff1247',	'O2 Sensor8 Wide-range Voltage',			'V',		0,0,0),
			('kff1215',	'O2 Volts Bank 1 Sensor 2',				'V',		0,0,0),
			('kff1216',	'O2 Volts Bank 1 Sensor 3',				'V',		0,0,0),
			('kff1217',	'O2 Volts Bank 1 Sensor 4',				'V',		0,0,0),
			('kff1219',	'O2 Volts Bank 2 Sensor 2',				'V',		0,0,0),
			('kff121a',	'O2 Volts Bank 2 Sensor 3',				'V',		0,0,0),
			('kff121b',	'O2 Volts Bank 2 Sensor 4',				'V',		0,0,0),
			('kff1296',	'Percentage of City Driving',				'%',		0,0,0),
			('kff1297',	'Percentage of Highway Driving',			'%',		0,0,0),
			('kff1298',	'Percentage of Idle Driving',				'%',		0,0,0),
			('k5a',		'Relative Accelerator Pedal Position',			'%',		0,0,0),
			('k45',		'Relative Throttle Position',				'%',		0,0,0),
			('kff124a',	'Tilt (x)',						NULL,		0,0,0),
			('kff124b',	'Tilt (y)',						NULL,		0,0,0),
			('kff124c',	'Tilt (z)',						NULL,		0,0,0),
			('kff1225',	'Torque',						'ft-lb',	0,0,0),
			('kfe1805',	'Transmission Temperature (Method 1)',			'°C',		0,0,0),
			('kff1206',	'Trip Average KPL',					'kpl',		0,0,0),
			('kff1208',	'Trip Average Litres/100 KM',				'l/100km',	0,0,0),
			('kff1205',	'Trip Average MPG',					'mpg',		0,0,0),
			('kff1266',	'Trip Time (Since Journey Start)',			's',		0,0,0),
			('kff1268',	'Trip Time (Whilst Moving)',				's',		0,0,0),
			('kff1267',	'Trip Time (Whilst Stationary)',			's',		0,0,0),
			('k42',		'Voltage (Control Module)',				'V',		0,0,0),
			('kff1269',	'Volumetric Efficiency (Calculated)',			'%',		0,0,0)";

$innodb_compression_pids = "ALTER TABLE ".$login.$db_pids_prefix." ROW_FORMAT=compressed";
$innodb_compression_logs = "ALTER TABLE ".$login.$db_log_prefix." ROW_FORMAT=compressed";
$innodb_compression_sessions = "ALTER TABLE ".$login.$db_sessions_prefix." ROW_FORMAT=compressed";

$db->query($logs_table);
$db->query($sessions_table);
$db->query($pids_table);
$db->query($pids_values);

if (isset($_POST['reg_legacy'])) $db->query($pids_legacy_values);

if ($db_engine == "INNODB" && $db_innodb_compression) {
 $db->query($innodb_compression_pids);
 $db->query($innodb_compression_logs);
 $db->query($innodb_compression_sessions);
}

//Insert user entry to users table
$db->execute_query("INSERT INTO $db_users (user, pass, s) VALUES (?,?,?)", [$login, password_hash($password, PASSWORD_DEFAULT, $salt), $def_limit]);
$db->close();
die($translations[$_COOKIE['lang']]['admin.user.added'].$login);
}

    else if (isset($_POST['del_login'])){ //delete user
	$login = $_POST['del_login'];

	$userqry = $db->execute_query("SELECT id FROM $db_users WHERE user=?", [$login]);
	if (!$userqry->num_rows || strlen($login) < 1) die($translations[$_COOKIE['lang']]['admin.user.not.found'].$login);
	else if ($login == $admin) die($translations[$_COOKIE['lang']]['admin.del.admin']);

	$logs_table = "DROP TABLE ".$login.$db_log_prefix;
	$sessions_table = "DROP TABLE ".$login.$db_sessions_prefix;
	$pids_table = "DROP TABLE ".$login.$db_pids_prefix;
	$user_entry = "DELETE FROM $db_users WHERE user=" . quote_value($login);
	try {
	    $db->query($logs_table);
	    $db->query($sessions_table);
	    $db->query($pids_table);
	} catch (Exception $e) { die($login.$translations[$_COOKIE['lang']]['admin.del.catch']); }
	$db->query($user_entry);
	$db->close();
	die($translations[$_COOKIE['lang']]['admin.del.ok'].$login);
    }

    else if (isset($_POST['trunc_login'])){ //truncate user db
	$login = $_POST['trunc_login'];

	$userqry = $db->execute_query("SELECT id FROM $db_users WHERE user=?", [$login]);
	if (!$userqry->num_rows || strlen($login) < 1) die($translations[$_COOKIE['lang']]['admin.user.not.found'].$login);
	else if ($login == $admin) die($translations[$_COOKIE['lang']]['admin.trunc.admin']);

	$logs_table = "TRUNCATE TABLE ".$login.$db_log_prefix;
	$sessions_table = "TRUNCATE TABLE ".$login.$db_sessions_prefix;

	$db->query($logs_table);
	$db->query($sessions_table);
	$db->close();
	die($translations[$_COOKIE['lang']]['admin.trunc'].$login);
    }

    else {
	header('HTTP/1.0 403 Forbidden');
	header("Location: .");
	die;
    }
  }
  catch (Exception $e) {
    die($e);
  }
}
?>
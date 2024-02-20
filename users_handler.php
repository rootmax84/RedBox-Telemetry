<?php
require_once ('token_functions.php');
require_once ('auth_functions.php');
require_once ('db.php');

if (isset($_POST['speed']) && isset($_POST['temp']) && isset($_POST['pressure']) && isset($_POST['boost']) && isset($_POST['time']) && isset($username) && $username != $admin){ //Update users settings
    $db->execute_query("UPDATE $db_users SET speed=?, temp=?, pressure=?, boost=?, time=? WHERE user=?", [$_POST['speed'], $_POST['temp'], $_POST['pressure'], $_POST['boost'], $_POST['time'], $username]);
    $db->close();
    setcookie("timeformat", $_POST['time'] == '1' ? '24' : '12');
    die("Conversion settings updated");
}

if (isset($_GET['get_token']) && isset($username) && $username != $admin){ //Get current user token
    $row = $db->execute_query("SELECT token FROM $db_users WHERE user=?", [$username])->fetch_assoc();
    $token = $row["token"];
    $db->close();
    die($token);
}
else if (isset($_GET['renew_token']) && isset($username) && $username != $admin){ //Renew token by user action
    do { //Check for duplicates
      $token = generate_token($username);
      $dup = $db->execute_query("SELECT token FROM $db_users WHERE token=?", [$token]);
    } while ($dup->num_rows);

    $db->execute_query("UPDATE $db_users SET token=? WHERE user=?", [$token, $username]);
    $db->close();
    die("Token updated");
}
else if (isset($_POST['old_p']) && isset($_POST['new_p1']) && isset($_POST['new_p2']) && isset($username) && $username != $admin){ // Change password by user action
    $old_p = $_POST['old_p'];
    $new_p1 = $_POST['new_p1'];
    $new_p2 = $_POST['new_p2'];
    $row = $db->execute_query("SELECT id, pass FROM $db_users WHERE user=?", [$username])->fetch_assoc();
     if (password_verify($old_p, $row["pass"])) {
     if ($new_p1 != $new_p2) die ("New password not match!");
     if (strlen($new_p1) < 8 || strlen($new_p2) < 8) die("New password must be at least 8 chars!");
     if ($old_p == $new_p1 || $old_p == $new_p2) die("New password same as current password!");
     $new_pass = $new_p2;
     if (!preg_match("#[0-9]+#", $new_pass)) die("New password must have at least one number!");
     if (!preg_match("#[a-zA-Z]+#", $new_pass)) die("New password must have at least one letter!");
     $db->execute_query("UPDATE $db_users SET pass=? WHERE id=?", [password_hash($new_pass, PASSWORD_DEFAULT, $salt), $row['id']]);
     $db->close();
     die("Password changed successfully");
    }
    else die("Wrong current password!");
}
else if (isset($_POST['tg_token']) && isset($_POST['tg_chatid']) && isset($username) && $username != $admin){ //Set telegram bot creds for notifying
    $db->execute_query("UPDATE $db_users SET tg_token=?, tg_chatid=? WHERE user=?", [$_POST['tg_token'], $_POST['tg_chatid'], $username]);
    $db->close();
    notify("RedBox Telemetry test message", $_POST['tg_token'], $_POST['tg_chatid']); //Send test message
    die("Notify settings updated. Test message sent.");
}

if (!isset($_SESSION['admin'])) {
    header("Location: /");
    die;
}
else
{
    if (isset($_POST['e_login'])){ //edit user
	$login = $_POST['e_login'];
	$password = $_POST['e_pass'];
	$e_limit = $_POST['e_limit'];

	$row = $db->execute_query("SELECT id, user, pass, s FROM $db_users WHERE user=?", [$login])->fetch_assoc();
	if (!$row) die("User not found");

	if (strlen($password) > 1 && strlen($password) < 5) die("Password too short");
	if (!strlen($e_limit) && !strlen($password)) die("Nothing to do");

	if (!strlen($password) && strlen($e_limit)) { //Change only limit
	 $db->execute_query("UPDATE $db_users SET s=? WHERE id=?", [$e_limit, $row['id']]);
	 $msg = "Limit changed";
	}
	else if (strlen($password) && !strlen($e_limit)) { //Change only password
	 $db->execute_query("UPDATE $db_users SET pass=? WHERE id=?", [password_hash($password, PASSWORD_DEFAULT, $salt), $row['id']]);
	 $msg = "Password changed";
	}
	else { // Change password and limit
	 $db->execute_query("UPDATE $db_users SET pass=?, s=? WHERE id=?", [password_hash($password, PASSWORD_DEFAULT, $salt), $e_limit, $row['id']]);
	 $msg = "Limit and password changed";
	}
	$db->close();
	die($msg);
    }

    else if (isset($_POST['reg_login']) && isset($_POST['reg_pass'])){ //add user
	$login = $_POST['reg_login'];
	$password = $_POST['reg_pass'];

	$userqry = $db->execute_query("SELECT user, pass, s FROM $db_users WHERE user=?", [$ogin]);
	if ($userqry->num_rows || strlen($login) < 1) die("User already exist or login empty");

	if (strlen($password) < 5) die("Password too short");

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
			ip char(15) NOT NULL DEFAULT 'Unknown',
			sessionsize mediumint(8) unsigned NOT NULL DEFAULT 0,
			session bigint(20) unsigned NOT NULL,
			time bigint(20) unsigned NOT NULL,
			timestart bigint(20) unsigned NOT NULL,
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
			UNIQUE KEY id (id)) ENGINE=".$db_engine."";

	$pids_values = "INSERT INTO ".$login.$db_pids_prefix." (id, description, units, populated, stream) VALUES
			('k10',		'Mass Air Flow Rate',				'g/sec',	1,	1),
			('k11',		'Throttle Position (Manifold)',			'%',		1,	1),
			('k1f',		'Run Time Since Engine Start',			's',		1,	1),
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
			('k46',		'Ambient Air Temp',				'°C',		1,	1),
			('k5',		'Engine Coolant Temperature',			'°C',		1,	1),
			('k5c',		'Engine Oil Temperature',			'°C',		1,	1),
			('k78',		'EGT',						'°C',		1,	1),
			('k2119',	'Fuel Pressure',				'Bar',		1,	1),
			('kb',		'Intake Manifold Pressure',			'kPa',		1,	1),
			('kb4',		'Transmission Temperature (Method 2)',		'°C',		1,	1),
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

$innodb_compression_pids = "ALTER TABLE ".$login.$db_pids_prefix." ROW_FORMAT=compressed";
$innodb_compression_logs = "ALTER TABLE ".$login.$db_log_prefix." ROW_FORMAT=compressed";
$innodb_compression_sessions = "ALTER TABLE ".$login.$db_sessions_prefix." ROW_FORMAT=compressed";

$db->query($logs_table);
$db->query($sessions_table);
$db->query($pids_table);
$db->query($pids_values);

if ($db_engine == "INNODB" && $db_innodb_compression) {
 $db->query($innodb_compression_pids);
 $db->query($innodb_compression_logs);
 $db->query($innodb_compression_sessions);
}

//Insert user entry to users table
$db->execute_query("INSERT INTO $db_users (user, pass, s, token) VALUES (?,?,?,?)", [$login, password_hash($password, PASSWORD_DEFAULT, $salt), $def_limit, 'Welcome, '.$login.'! Click renew to create token.']);
$db->close();
die("User added");
}

    else if (isset($_POST['del_login'])){ //delete user
	$login = $_POST['del_login'];

	$userqry = $db->execute_query("SELECT user, pass, s FROM $db_users WHERE user=?", [$login]);
	if (!$userqry->num_rows || strlen($login) < 1) die("User not found");
	else if ($login == $admin) die("Admin cannot be deleted!");

	$logs_table = "DROP TABLE ".$login.$db_log_prefix;
	$sessions_table = "DROP TABLE ".$login.$db_sessions_prefix;
	$pids_table = "DROP TABLE ".$login.$db_pids_prefix;
	$user_entry = "DELETE FROM $db_users WHERE user=" . quote_value($login);
	try {
	    $db->query($logs_table);
	    $db->query($sessions_table);
	    $db->query($pids_table);
	} catch (Exception $e) { die("User has no tables and cannot be deleted!"); }
	$db->query($user_entry);
	$db->close();
	die("User deleted");
    }

    else if (isset($_POST['trunc_login'])){ //truncate user db
	$login = $_POST['trunc_login'];

	$userqry = $db->execute_query("SELECT user, pass, s FROM $db_users WHERE user=?", [$login]);
	if (!$userqry->num_rows || strlen($login) < 1) die("User not found");

	$logs_table = "TRUNCATE TABLE ".$login.$db_log_prefix;
	$sessions_table = "TRUNCATE TABLE ".$login.$db_sessions_prefix;

	$db->query($logs_table);
	$db->query($sessions_table);
	$db->close();
	die("User database truncated");
    }

    else {
	header('HTTP/1.0 403 Forbidden');
	header("Location: /");
	die;
    }
}
?>
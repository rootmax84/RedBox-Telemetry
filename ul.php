<?php
require_once('token_functions.php');

//Allow CORS and JWT
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With,Authorization,Content-Type');
header('Access-Control-Max-Age: 86400');
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') die; //Respond to preflights

//Check if token header is present and non empty than go to database
if (getBearerToken() != NULL && getBearerToken() != '') {

 //Maintenance mode
 if (file_exists('maintenance')){
  header('HTTP/1.0 423 Locked');
  die('Server under maintenance');
 }

 $_SESSION['torque_logged_in'] = true;
 require_once('db.php');

 //Server overload check
 $load = sys_getloadavg(); //Fetch CPU load avg
 if ($max_load_avg > 0 && $load[1] > $max_load_avg){
  header('HTTP/1.0 413 Server overload');
  die('Server overloaded');
 }

 //Check auth via Bearer token
 $userqry = $db->execute_query("SELECT user, s, tg_token, tg_chatid FROM $db_users WHERE token=?", [getBearerToken()]);
  if (!$userqry->num_rows) $access = 0;
  else {
    $row = $userqry->fetch_assoc();
    $limit = $row["s"];
    $user = $row["user"];
    $tg_token = $row["tg_token"];
    $tg_chatid = $row["tg_chatid"];
    $access = 1;
  }
} else $access = 0;

 if ($access != 1 || $limit == 0){
     header('HTTP/1.0 403 Forbidden');
     die('Access denied');
 }

$db_table = $user.$db_log_prefix;

$db_limit = $db->execute_query("SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [$db_name, $db_table])->fetch_row()[0];
 if ($db_limit >= $limit && $limit != -1){
    header('HTTP/1.0 406 Not Acceptable');
    die('No space left');
}

$db_sessions_table = $user.$db_sessions_prefix;
$db_pids_table = $user.$db_pids_prefix;

// Create an array of all the existing fields in the database
$result = $db->query("SHOW COLUMNS FROM $db_table");
if ($result->num_rows) {
  while ($row = $result->fetch_assoc()) {
    $dbfields[]=($row['Field']);
  }
}

// Iterate over all the k* _GET arguments to check that a field exists
if (sizeof($_GET) > 0) {
  $keys = array();
  $values = array();
  $sesskeys = array();
  $sessvalues = array();
  $sessprofilekeys = array();
  $spv = array();
  $sessuploadid = "";
  $sesstime = "0";

  foreach ($_GET as $key => $value) {
    if (preg_match("/^k/", $key)) {
      // Keep columns starting with k
      $keys[] = $key;
      // My Torque app tries to pass "Infinity" in for some values...catch that error, set to -1
      if ($value == 'Infinity') {
        $values[] = -1;
      } else {
        $values[] = $value;
      }
      $submitval = 1;
    } else if (in_array($key, array("time", "session"))) {
      // Keep non k* columns listed here
      if ($key == 'session') {
        $sessuploadid = $value;
      }
      if ($key == 'time') {
        $sesstime = $value;
      }
      $sesskeys[] = $key;
      $sessvalues[] = $value;
      $submitval = 1;
    } else if (in_array($key, array("id"))) {
      // Keep id column here
      $id = $value;
      $submitval = 99; //Store id in sessions table only
    } else if (in_array($key, array("notice", "noticeClass"))) {
      $keys[] = $key;
      $values[] = $value;
      $submitval = 3; //do nothing with this yet
    } else if (preg_match("/^profile/", $key)) {
      $spv[] = $value;
      $submitval = 2;
    } else {
      $submitval = 0;
    }

    // If the field doesn't already exist, add it to the database except id key
    if (!in_array($key, $dbfields) && $submitval == 1) {
      if (is_numeric($value)) {
        // Add field if it's a int/float
        $sqlalter = "ALTER TABLE $db_table ADD IF NOT EXISTS".quote_name($key)." float NOT NULL default '0'";
      } else {
        // Add field if it's a string, specifically varchar(255)
        $sqlalter = "ALTER TABLE $db_table ADD IF NOT EXISTS".quote_name($key)." VARCHAR(255) NOT NULL default '0'";
      }
      $sqlalterkey = "INSERT IGNORE INTO $db_pids_table (id, description, populated, stream, favorite) VALUES (?,?,?,?,?)";
      $db->execute_query($sqlalterkey, [$key, $key, '1', '1', '0']);
      $db->query($sqlalter);
    }
  }
  // start insert/update incoming data
  $rawkeys = array_merge($keys, $sesskeys);
  $rawvalues = array_merge($values, $sessvalues);

  if ((sizeof($rawkeys) === sizeof($rawvalues)) && sizeof($rawkeys) > 0 && (sizeof($sesskeys) === sizeof($sessvalues)) && sizeof($sesskeys) > 0) {
    // Now insert the data for all the fields into the raw logs table
    if ($submitval == 1) {
      $sql = "INSERT INTO $db_table (".quote_names($rawkeys).") VALUES (".quote_values($rawvalues).")";
        try { //Supress time possible duplicate
          $db->query($sql);
        } catch (Exception $e) { die("OK!"); }
    }
    // See if there is already an entry in the sessions table for this session
    $sessionqry = $db->execute_query("SELECT sessionsize, profileName FROM $db_sessions_table WHERE session=?", [$sessuploadid])->fetch_assoc();
    // If there's an entry in the session table for this session, update the session end time and the datapoint count
    $sesssizecount = empty($sessionqry["sessionsize"]) ? 1 : $sessionqry["sessionsize"] + 1;
    $sessionqrystring = "INSERT INTO $db_sessions_table (".quote_names($sesskeys).", timestart, sessionsize, id) VALUES (".quote_values($sessvalues).", $sesstime, '1',".quote_value(isset($id)?$id:'-').") ON DUPLICATE KEY UPDATE timeend='$sesstime', sessionsize='$sesssizecount'";
    $db->query($sessionqrystring);

    $ip = isset($_SERVER['HTTP_CLIENT_IP']) //get user ip
     ? $_SERVER['HTTP_CLIENT_IP']
     : (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
      ? $_SERVER['HTTP_X_FORWARDED_FOR']
      : $_SERVER['REMOTE_ADDR']);

    if ($submitval == 2) { //Profile info
    $sql = "UPDATE $db_sessions_table SET
     profileName =         ?,
     profileFuelType =     ?,
     profileWeight =       ?,
     profileVe =           ?,
     profileFuelCost =     ?,
     profileDisplacement = ?,
     profileTankCapacity = ?,
     profileTankUsed =     ?,
     profileVehicleType =  ?,
     profileOdometer =     ?,
     profileMPGAdjust =    ?,
     profileBoostAdjust =  ?,
     profileDragCoeff =    ?,
     profileOBDAdjust =    ?,
     ip =                  ?
     WHERE session =       ?";
    $db->execute_query($sql, [$spv[0], $spv[1], $spv[2], $spv[3], $spv[4], $spv[5], $spv[6], $spv[7], $spv[8], $spv[9], $spv[10], $spv[11], $spv[12], $spv[13], $ip, $sessuploadid]);
    }
    if ($sesssizecount == 5) notify("Session started from ip ".$ip.". Profile: ".$sessionqry["profileName"], $tg_token, $tg_chatid); //Notify to user telegram bot at session start
  }
}

$db->close();

// Return the response required by Torque/RedManage
echo "OK!";
?>

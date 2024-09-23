<?php
require_once('token_functions.php');

//Allow CORS and JWT
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With,Authorization,Content-Type');
header('Access-Control-Max-Age: 86400');

$allowedMethods = ['GET', 'POST', 'OPTIONS'];
if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    header('HTTP/1.0 405 Method Not Allowed');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { //Respond to preflights
    header('Access-Control-Allow-Methods: ' . implode(", ", $allowedMethods));
    exit;
}

//Check if token header is present and non empty than go to database
$token = getBearerToken();
if (!empty($token)) {

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
 $userqry = $db->execute_query("SELECT user, s, tg_token, tg_chatid FROM $db_users WHERE token=?", [$token]);
  if (!$userqry->num_rows) $access = 0;
  else {
    [$user, $limit, $tg_token, $tg_chatid] = $userqry->fetch_row();
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

$allowedProfileFields = [
    'profileName', 'profileFuelType', 'profileWeight', 'profileVe', 'profileFuelCost',
    'profileDisplacement', 'profileTankCapacity', 'profileTankUsed', 'profileVehicleType',
    'profileOdometer', 'profileMPGAdjust', 'profileBoostAdjust', 'profileDragCoeff', 'profileOBDAdjust'
];

// Iterate over all the k* _GET arguments to check that a field exists
if (sizeof($_REQUEST) > 0) {
  $keys = [];
  $values = [];
  $sesskeys = [];
  $sessvalues = [];
  $sessprofilekeys = [];
  $spv = [];
  $sessuploadid = "";
  $sesstime = "0";

  foreach ($_REQUEST as $key => $value) {
    if (in_array($key, ["time", "session", "id"])) {
      // Keep non k* columns listed here
      if ($key == 'session') {
        $sessuploadid = $value;
      }
      if ($key == 'time') {
        $sesstime = $value;
      }
      if ($key == 'id') {
        $id = $value;
      } else {
        $sesskeys[] = $key;
        $sessvalues[] = $value;
     }
      $submitval = 1;
    } else if (preg_match("/^k/", $key)) {
      // Keep columns starting with k
      $keys[] = $key;
      // My Torque app tries to pass "Infinity" in for some values...catch that error, set to -1
      if ($value == 'Infinity') {
        $values[] = -1;
      } else {
        $values[] = $value;
      }
      $submitval = 1;
    } else if (in_array($key, ["notice", "noticeClass"])) {
      $keys[] = $key;
      $values[] = $value;
      $submitval = 3; //do nothing with this yet
    } else if (preg_match("/^profile/", $key)) {
        if (in_array($key, $allowedProfileFields)) {
            $spv[$key] = $value;
            $submitval = 2;
        }
    } else {
      $submitval = 0;
    }

    // If the field doesn't already exist, add it to the database except id key
    if (!in_array($key, $dbfields) && $key != "id" && $submitval == 1) {
      $dataType = is_numeric($value) ? "float" : "VARCHAR(255)";

      $sqlalter = "ALTER TABLE $db_table ADD IF NOT EXISTS ".quote_name($key)." $dataType NOT NULL default '0'";
      $db->query($sqlalter);

      $sqlalterkey = "INSERT IGNORE INTO $db_pids_table (id, description, populated, stream, favorite) VALUES (?,?,?,?,?)";
      $db->execute_query($sqlalterkey, [$key, $key, '1', '1', '0']);
    }
  }
  // start insert/update incoming data
  $rawkeys = array_merge($keys, $sesskeys);
  $rawvalues = array_merge($values, $sessvalues);

  if ((sizeof($rawkeys) === sizeof($rawvalues)) && sizeof($rawkeys) > 0 && (sizeof($sesskeys) === sizeof($sessvalues)) && sizeof($sesskeys) > 0) {
    // Now insert the data for all the fields into the raw logs table
    if ($submitval == 1) {
      $sql = "INSERT IGNORE INTO $db_table (".quote_names($rawkeys).") VALUES (".quote_values($rawvalues).")";
      $db->query($sql);
    }
    $sessionqrystring = "INSERT INTO $db_sessions_table (".quote_names($sesskeys).", timestart) VALUES (".quote_values($sessvalues).", $sesstime) ON DUPLICATE KEY UPDATE id=?, timeend=?, sessionsize=sessionsize+1";
    $db->execute_query($sessionqrystring, [$id ?? '', $sesstime]);

    if ($submitval == 2) { //Profile info
        $updateFields = [];
        $params = [];

        foreach ($spv as $field => $value) {
            if ($value !== '') {
                $updateFields[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (!empty($updateFields)) {
            $updateFields[] = "ip = ?";
            $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
            $params[] = $ip;

            $sql = "UPDATE $db_sessions_table SET " . implode(', ', $updateFields) . " WHERE session = ?";
            $params[] = $sessuploadid;

            $db->execute_query($sql, $params);

            $delay = time() - intval($sessuploadid / 1000);
            if ($delay > 10) {
                $formattedDelay = gmdate("H:i:s", $delay);
                $message = "Session started from IP {$ip}. Profile: {$spv['profileName']} (Delayed by {$formattedDelay})";
            } else {
                $message = "Session started from IP {$ip}. Profile: {$spv['profileName']}";
            }
            notify($message, $tg_token, $tg_chatid); // Notify to user telegram bot at session start
        }
    }
  }
}

$db->close();

// Return the response required by Torque/RedManage
echo "OK!";
?>

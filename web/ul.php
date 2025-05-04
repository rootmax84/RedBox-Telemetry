<?php
require_once('helpers.php');
include('translations.php');

//Allow CORS and JWT
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With,Authorization,Content-Type');
header('Access-Control-Max-Age: 86400');

$allowedMethods = ['GET', 'POST', 'OPTIONS'];
if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    http_response_code(405);
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

 $lang = $_POST['lang'] ?? $_GET['lang'] ?? null;

 //Maintenance mode
 if (file_exists('maintenance')){
  http_response_code(423);
  die($translations[$lang ?? 'en']['maintenance']);
 }

 $_SESSION['torque_logged_in'] = true;
 require_once('db.php');

 //Server overload check
 $load = sys_getloadavg(); //Fetch CPU load avg
 if ($max_load_avg > 0 && $load[1] > $max_load_avg){
  http_response_code(503);
  die($translations[$lang ?? 'en']['overload']);
 }

 $cache_key = "user_data_" . $token;
 $user_data = false;

 if ($memcached_connected) {
    $user_data = $memcached->get($cache_key);
 }

 //Check auth via Bearer token
 if ($user_data === false) {
    $userqry = $db->execute_query("SELECT user, s, tg_token, tg_chatid, forward_url, forward_token, lang FROM $db_users WHERE token=?", [$token]);
    if ($userqry->num_rows) {
        $access = 1;
        $user_data = $userqry->fetch_assoc();
        if ($memcached_connected) {
            try {
                $memcached->set($cache_key, $user_data, $db_memcached_ttl ?? 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error on upload auth: %s (Code: %d)", $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    } else {
        $access = 0;
    }
 }

 if ($user_data) {
    $access = 1;
    $username = $user_data['user'];
    $limit = $user_data['s'];
    $tg_token = $user_data['tg_token'];
    $tg_chatid = $user_data['tg_chatid'];
    $forward_url = $user_data['forward_url'] ?? null;
    $forward_token = $user_data['forward_token'] ?? null;
    $lang = $lang ?? $user_data['lang'];
 }
} else $access = 0;

if ($access != 1 || $limit == 0){
     http_response_code(403);
     die($translations[$lang ?? 'en']['denied']);
}

$db_table = $username.$db_log_prefix;

$db_limit_cache_key = "db_limit_" . $db_table;
$db_limit = false;

if ($memcached_connected) {
    $db_limit = $memcached->get($db_limit_cache_key);
}

if ($db_limit === false) {
    $db_limit = $db->execute_query("SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [$db_name, $db_table])->fetch_row()[0];
    if ($memcached_connected) {
        try {
            $memcached->set($db_limit_cache_key, $db_limit, 300);
        } catch (Exception $e) {
            $errorMessage = sprintf("Memcached error on upload: %s (Code: %d)", $e->getMessage(), $e->getCode());
            error_log($errorMessage);
        }
    }
}

if ($db_limit >= $limit && $limit != -1){
    http_response_code(507);
    die($translations[$lang]['no_space']);
}

$db_sessions_table = $username.$db_sessions_prefix;
$db_pids_table = $username.$db_pids_prefix;

$table_structure_cache_key = "table_structure_" . $db_table;
$dbfields = false;

if ($memcached_connected) {
    $dbfields = $memcached->get($table_structure_cache_key);
}

// Create an array of all the existing fields in the database
if ($dbfields === false) {
    $result = $db->query("SHOW COLUMNS FROM $db_table");
    $dbfields = [];
    if ($result->num_rows) {
        while ($row = $result->fetch_assoc()) {
            $dbfields[] = $row['Field'];
        }
    }
    if ($memcached_connected) {
        try {
            $memcached->set($table_structure_cache_key, $dbfields, $db_memcached_ttl ?? 3600);
        } catch (Exception $e) {
            $errorMessage = sprintf("Memcached error on upload: %s (Code: %d)", $e->getMessage(), $e->getCode());
            error_log($errorMessage);
        }
    }
}

$rate_limit_key = "rate_limit_" . $username;
$max_upload_requests_per_second = $max_upload_requests_per_second ?? 100;

if ($memcached_connected) {
    $current_requests = $memcached->get($rate_limit_key);

    if ($current_requests === false) {
        try {
            $memcached->set($rate_limit_key, 1, 1);
        } catch (Exception $e) {
            error_log(sprintf("Memcached error on upload: %s (Code: %d)", $e->getMessage(), $e->getCode()));
        }
    } else {
        if ($current_requests >= $max_upload_requests_per_second) {
            http_response_code(429);
            error_log("Upload spammer detected: " . $username);
            die($translations[$lang]['upload.429']);
        } else {
            try {
                $memcached->increment($rate_limit_key, 1);
            } catch (Exception $e) {
                error_log(sprintf("Memcached error on upload: %s (Code: %d)", $e->getMessage(), $e->getCode()));
            }
        }
    }
}

$allowedProfileFields = [
    'profileName'
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
    if (!in_array($key, $dbfields) && $submitval == 1 && preg_match('/^k[0-9a-fA-F]+$/', $key)) {
      $dataType = is_numeric($value) ? "FLOAT" : "VARCHAR(255)";

      if (!column_exists($db, $db_table, $key)) {
        $sqlalter = "ALTER TABLE $db_table ADD COLUMN ".quote_name($key)." $dataType NOT NULL DEFAULT '0'";
        $db->query($sqlalter);
      }

      $sqlalterkey = "INSERT IGNORE INTO $db_pids_table (id, description, populated, stream, favorite) VALUES (?,?,?,?,?)";
      $db->execute_query($sqlalterkey, [$key, $key, '1', '1', '0']);
      cache_flush();
    }
  }
  // start insert/update incoming data
  $rawkeys = array_merge($keys, $sesskeys);
  $rawvalues = array_merge($values, $sessvalues);

  if ((sizeof($rawkeys) === sizeof($rawvalues)) && sizeof($rawkeys) > 0 && (sizeof($sesskeys) === sizeof($sessvalues)) && sizeof($sesskeys) > 0) {
    // Now insert the data for all the fields into the raw logs table
    if ($submitval == 1) {
      $sql = "INSERT IGNORE INTO $db_table (".quote_names($rawkeys).") VALUES (".quote_values($rawvalues).")";
      try {
        $db->query($sql);
      } catch (Exception $e) {
        cache_flush();
      }
    }
    $sesskeys[] = 'timeend';
    $sessvalues[] = $sesstime;
    $sessionqrystring = "INSERT INTO $db_sessions_table (".quote_names($sesskeys).") VALUES (".quote_values($sessvalues).") ON DUPLICATE KEY UPDATE id=?, timeend=?, sessionsize=sessionsize+1";
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

            $updateFields[] = "timeend = ?";
            $timeend = round(microtime(true) * 1000);
            $params[] = $timeend;

            $sql = "UPDATE $db_sessions_table SET " . implode(', ', $updateFields) . " WHERE session = ?";
            $params[] = $sessuploadid;

            $db->execute_query($sql, $params);

            $delay = time() - intval($sessuploadid / 1000);
            if ($delay > 10) {
                $formattedDelay = gmdate("H:i:s", $delay);
                $message = "{$translations[$lang]['upload.start']} {$ip}. {$translations[$lang]['get.sess.profile']}: {$spv['profileName']} ({$translations[$lang]['upload.delayed']} {$formattedDelay})";
            } else {
                $message = "{$translations[$lang]['upload.start']} {$ip}. {$translations[$lang]['get.sess.profile']}: {$spv['profileName']}";
            }
            touch(sys_get_temp_dir().'/'.$username); // Create empty file in tmp to get new session notify on frontend
            notify($message, $tg_token, $tg_chatid); // Notify to user telegram bot at session start
        }
    }
  }
}

$db->close();

// Return the response required by Torque/RedManage
echo "OK!";

// Forward request to another URL if specified
if (!empty($forward_url)) {
    forward_request($username, $forward_url, $forward_token);
}

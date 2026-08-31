<?php
require_once 'helpers.php';
include 'translations.php';

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

    if (file_exists('maintenance')) {
        http_response_code(423);
        die($translations[$lang ?? 'en']['maintenance']);
    }

    $_SESSION['torque_logged_in'] = true;
    require_once 'db.php';

    $load = sys_getloadavg();
    if ($max_load_avg > 0 && $load[1] > $max_load_avg) {
        http_response_code(503);
        die($translations[$lang ?? 'en']['overload']);
    }

    $cache_key = "user_data_" . $token;
    $user_data = false;

    if ($memcached_connected) {
        $user_data = $memcached->get($cache_key);
    }

    if ($user_data === false) {
        $userqry = $db->execute_query("SELECT user, s, tg_token, tg_chatid, lang FROM $db_users WHERE token=?", [$token]);
        if ($userqry->num_rows) {
            $access = 1;
            $user_data = $userqry->fetch_assoc();
            if ($memcached_connected) {
                try {
                    $memcached->set($cache_key, $user_data, $db_memcached_ttl ?? 3600);
                } catch (Exception $e) {
                    error_log("Memcached error on upload auth: " . $e->getMessage());
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
        $lang = $lang ?? $user_data['lang'];
    }
} else {
    $access = 0;
}

if ($access != 1 || $limit == 0) {
    http_response_code(403);
    die($translations[$lang ?? 'en']['denied']);
}

$db_table = $username . $db_log_prefix;

if (isset($_REQUEST['servertime'])) {
    $dt = new DateTime('now', new DateTimeZone('UTC'));
    $timestamp = (int)($dt->format('Uu') / 1000);
    echo $timestamp;
    exit;
}

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
            error_log("Memcached error on upload: " . $e->getMessage());
        }
    }
}

if ($db_limit >= $limit && $limit != -1) {
    http_response_code(507);
    die($translations[$lang]['no_space']);
}

$db_sessions_table = $username . $db_sessions_prefix;
$db_pids_table = $username . $db_pids_prefix;

$table_structure_cache_key = "table_structure_" . $db_table;
$dbfields = false;

if ($memcached_connected) {
    $dbfields = $memcached->get($table_structure_cache_key);
}

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
            error_log("Memcached error on upload: " . $e->getMessage());
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
            error_log("Memcached error on upload: " . $e->getMessage());
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
                error_log("Memcached error on upload: " . $e->getMessage());
            }
        }
    }
}

$allowedProfileFields = ['profileName'];

function processSessionStartRecord($db, $record, $db_sessions_table, $lang, $username, $tg_token, $tg_chatid, $tg_socks_proxy, $translations) {
    $sesskeys = [];
    $sessvalues = [];
    $spv = [];
    $sessuploadid = $record['session'];
    $sesstime = $record['time'];
    $id = $record['id'] ?? '';
    $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

    foreach ($record as $key => $value) {
        if (preg_match("/^profile/", $key) && in_array($key, ['profileName'])) {
            $spv[$key] = $value;
        } elseif (in_array($key, ['session', 'time', 'id'])) {
            $sesskeys[] = $key;
            $sessvalues[] = $value;
        }
    }

    $sesskeys[] = 'timeend';
    $sessvalues[] = $sesstime;

    $sessionqrystring = "INSERT INTO $db_sessions_table (" . quote_names($sesskeys) . ") VALUES (" . quote_values($sessvalues) . ") ON DUPLICATE KEY UPDATE id=?, timeend=?, sessionsize=sessionsize+1";
    $db->execute_query($sessionqrystring, [$id, $sesstime]);

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
        $params[] = $ip;
        $updateFields[] = "timeend = ?";
        $timeend = round(microtime(true) * 1000);
        $params[] = $timeend;

        $sql = "UPDATE $db_sessions_table SET " . implode(', ', $updateFields) . " WHERE session = ?";
        $params[] = $sessuploadid;
        $db->execute_query($sql, $params);
    }

    $delay = time() - intval($sessuploadid / 1000);
    if ($delay > 10) {
        $formattedDelay = formatDuration((int)$sessuploadid, time() * 1000, $lang);
        $startTime = intval($sessuploadid / 1000);
        $formattedDate = date("d.m.Y", $startTime);
        $formattedTime = date("H:i", $startTime);
        $message = "{$translations[$lang]['upload.start']} {$ip}. {$translations[$lang]['sel.profile']}: {$spv['profileName']} ({$translations[$lang]['upload.delayed']} {$formattedDelay}, {$translations[$lang]['upload.start_time']} {$formattedDate} {$translations[$lang]['upload.at']} {$formattedTime})";
    } else {
        $message = "{$translations[$lang]['upload.start']} {$ip}. {$translations[$lang]['sel.profile']}: {$spv['profileName']}";
    }

    touch(sys_get_temp_dir() . '/' . $username);
    if (!empty($tg_token) && !empty($tg_chatid)) {
        notify($message, $tg_token, $tg_chatid, $tg_socks_proxy ?? '');
    }
}

//RedManage bulk requests
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $json = file_get_contents('php://input');
    $records = json_decode($json, true);

    if (is_array($records) && !empty($records)) {
        if (count($records) > 100) {
            http_response_code(400);
            echo "Too many records";
            exit;
        }

        $db->begin_transaction();
        try {
            $bulkRecords = [];
            $sessionUpdates = [];
            $sessionStartRecords = [];

            foreach ($records as $record) {
                $isSessionStart = isset($record['profileName']) || !empty(array_filter(array_keys($record), fn($k) => strpos($k, 'profile') === 0));

                if ($isSessionStart) {
                    $sessionStartRecords[] = $record;
                    continue;
                }

                $rawkeys = [];
                $rawvalues = [];
                $sesskeys = [];
                $sessvalues = [];
                $sessuploadid = '';
                $sesstime = '0';
                $id = '';

                foreach ($record as $key => $value) {
                    if (in_array($key, ["time", "session", "id"])) {
                        if ($key == 'session') $sessuploadid = $value;
                        if ($key == 'time') $sesstime = $value;
                        if ($key == 'id') $id = $value;
                        else {
                            $sesskeys[] = $key;
                            $sessvalues[] = $value;
                        }
                    } elseif (preg_match("/^k/", $key)) {
                        $rawkeys[] = $key;
                        $rawvalues[] = ($value == 'Infinity') ? -1 : $value;
                    }
                }

                foreach ($rawkeys as $idx => $key) {
                    if (!in_array($key, $dbfields) && preg_match('/^k[0-9a-fA-F]+$/', $key)) {
                        $dataType = is_numeric($rawvalues[$idx]) ? "FLOAT" : "VARCHAR(255)";
                        if (!column_exists($db, $db_table, $key)) {
                            $sqlalter = "ALTER TABLE $db_table ADD COLUMN " . quote_name($key) . " $dataType NOT NULL DEFAULT '0'";
                            $db->query($sqlalter);
                        }
                        $sqlalterkey = "INSERT IGNORE INTO $db_pids_table (id, description, populated, stream, favorite) VALUES (?,?,?,?,?)";
                        $db->execute_query($sqlalterkey, [$key, $key, '1', '1', '0']);
                        $dbfields[] = $key;
                        cache_flush();
                    }
                }

                $allRawKeys = array_merge($rawkeys, $sesskeys);
                $allRawValues = array_merge($rawvalues, $sessvalues);
                $bulkRecord = [];
                foreach ($allRawKeys as $i => $key) {
                    $bulkRecord[$key] = $allRawValues[$i];
                }
                $bulkRecords[] = $bulkRecord;

                $sesskeys[] = 'timeend';
                $sessvalues[] = $sesstime;
                $sessionUpdates[] = [
                    'keys' => $sesskeys,
                    'values' => $sessvalues,
                    'id' => $id,
                    'sesstime' => $sesstime,
                ];
            }

            if (!empty($bulkRecords)) {
                insert_bulk_records($db, $db_table, $bulkRecords);
            }

            foreach ($sessionUpdates as $sess) {
                $sessionqrystring = "INSERT INTO $db_sessions_table (" . quote_names($sess['keys']) . ") VALUES (" . quote_values($sess['values']) . ") ON DUPLICATE KEY UPDATE id=?, timeend=?, sessionsize=sessionsize+1";
                $db->execute_query($sessionqrystring, [$sess['id'], $sess['sesstime']]);
            }

            foreach ($sessionStartRecords as $record) {
                processSessionStartRecord($db, $record, $db_sessions_table, $lang, $username, $tg_token, $tg_chatid, $tg_socks_proxy ?? '', $translations);
            }

            $db->commit();
            echo "OK!";
            exit;
        } catch (Exception $e) {
            $db->rollback();
            error_log("Bulk processing error: " . $e->getMessage());
            http_response_code(500);
            echo "Bulk error";
            exit;
        }
    } else {
        http_response_code(400);
        echo "Invalid JSON";
        exit;
    }
}

// single requests
if (sizeof($_REQUEST) > 0) {
    $keys = [];
    $values = [];
    $sesskeys = [];
    $sessvalues = [];
    $spv = [];
    $sessuploadid = "";
    $sesstime = "0";
    $submitval = 0;

    foreach ($_REQUEST as $key => $value) {
        if (in_array($key, ["time", "session", "id"])) {
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
        } elseif (preg_match("/^k/", $key)) {
            $keys[] = $key;
            $values[] = ($value == 'Infinity') ? -1 : $value;
            $submitval = 1;
        } elseif (in_array($key, ["notice", "noticeClass"])) {
            $keys[] = $key;
            $values[] = $value;
            $submitval = 3;
        } elseif (preg_match("/^profile/", $key)) {
            if (in_array($key, $allowedProfileFields)) {
                $spv[$key] = $value;
                $submitval = 2;
            }
        } else {
            $submitval = 0;
        }

        if (!in_array($key, $dbfields) && $submitval == 1 && preg_match('/^k[0-9a-fA-F]+$/', $key)) {
            $dataType = is_numeric($value) ? "FLOAT" : "VARCHAR(255)";
            if (!column_exists($db, $db_table, $key)) {
                $sqlalter = "ALTER TABLE $db_table ADD COLUMN " . quote_name($key) . " $dataType NOT NULL DEFAULT '0'";
                $db->query($sqlalter);
            }
            $sqlalterkey = "INSERT IGNORE INTO $db_pids_table (id, description, populated, stream, favorite) VALUES (?,?,?,?,?)";
            $db->execute_query($sqlalterkey, [$key, $key, '1', '1', '0']);
            cache_flush();
        }
    }

    if ($submitval == 2) {
        $record = [];
        foreach ($_REQUEST as $key => $value) {
            if (in_array($key, ['session', 'time', 'id']) || preg_match("/^profile/", $key)) {
                $record[$key] = $value;
            }
        }
        $db->begin_transaction();
        try {
            processSessionStartRecord($db, $record, $db_sessions_table, $lang, $username, $tg_token, $tg_chatid, $tg_socks_proxy ?? '', $translations);
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            error_log("Profile transaction error: " . $e->getMessage());
        }
    } else {
        $rawkeys = array_merge($keys, $sesskeys);
        $rawvalues = array_merge($values, $sessvalues);

        if ((sizeof($rawkeys) === sizeof($rawvalues)) && sizeof($rawkeys) > 0 && (sizeof($sesskeys) === sizeof($sessvalues)) && sizeof($sesskeys) > 0) {
            if ($submitval == 1) {
                insert_single_record($db, $db_table, $rawkeys, $rawvalues);
            }

            $sesskeys[] = 'timeend';
            $sessvalues[] = $sesstime;
            $sessionqrystring = "INSERT INTO $db_sessions_table (" . quote_names($sesskeys) . ") VALUES (" . quote_values($sessvalues) . ") ON DUPLICATE KEY UPDATE id=?, timeend=?, sessionsize=sessionsize+1";
            $db->execute_query($sessionqrystring, [$id ?? '', $sesstime]);
        }
    }
}

$db->close();

// Return the response required by Torque/RedManage
echo "OK!";

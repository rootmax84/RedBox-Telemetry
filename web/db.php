<?php
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    die('PHP 8.2+ required, your version: ' . PHP_VERSION . "\n");
}

set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage());
    session_destroy();
    header('Location: catch.php?c=error');
    die();
});

$required_extensions = ['mysqli'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        die("php-$ext extension required");
    }
}

require_once('creds.php');

if (isset($_GET['logout'])) {
    logout_user();
}

if (file_exists('maintenance') && !isset($_SESSION['admin'])) {
    die();
}

// Check Memcached presence
$memcached_available = class_exists('Memcached');
$memcached_connected = false;

if ($memcached_available) {
    try {
        $memcached = new Memcached();
        $memcached->addServer($db_memcached, 11211);
        $memcached_connected = !empty($memcached->getStats());
    } catch (Exception $e) {
        $memcached_connected = false;
    }
}

$db = get_db_connection();

function quote_name($name) {
    return "`" . str_replace("`", "``", $name) . "`";
}

function quote_names($column_names) {
    return implode(", ", array_map('quote_name', $column_names));
}

function quote_value($value) {
    global $db;
    return "'" . $db->real_escape_string($value) . "'";
}

function search($value) {
    global $db;
    return "'%" . $db->real_escape_string($value) . "%'";
}

function quote_values($values) {
    return implode(", ", array_map('quote_value', $values));
}

function cache_flush($token = null, $keyname = null) {
    global $memcached, $memcached_connected, $username, $db_table, $db_pids_table;

    if (!$memcached_connected) {
        return;
    }

    try {
        if ($keyname !== null) {
            $allKeys = $memcached->getAllKeys();
            if ($allKeys !== false) {
                foreach ($allKeys as $key) {
                    if (strpos($key, $keyname) === 0) {
                        $memcached->delete($key);
                    }
                }
            }
            return;
        }

        $keys = $token !== null
            ? ["user_data_{$token}", "user_api_data_{$token}"]
            : [
                "profiles_list_{$username}",
                "years_list_{$username}",
                "stream_lock_{$username}",
                "user_settings_{$username}",
                "db_limit_{$db_table}",
                "table_structure_{$db_table}",
                "user_status_{$username}",
                "columns_data_{$db_pids_table}",
                "pids_mapping_{$username}",
                "share_data_{$_SESSION['uid']}",
                "share_plot_{$_SESSION['uid']}",
                "fav_data_{$username}",
                "stream_conv_{$username}",
                "api_conv_{$username}"
            ];

        if ($token === null) {
            $patterns = [
                "gps_data_{$username}_",
                "session_data_{$username}_",
                "sessions_list_{$username}_"
            ];

            $allKeys = $memcached->getAllKeys();
            if ($allKeys !== false) {
                foreach ($patterns as $pattern) {
                    foreach ($allKeys as $key) {
                        if (strpos($key, $pattern) === 0) {
                            $keys[] = $key;
                        }
                    }
                }
            }
        }

        foreach (array_unique($keys) as $key) {
            $memcached->delete($key);
        }

    } catch (Exception $e) {
        error_log(sprintf(
            "Memcached error for user %s: %s (Code: %d)",
            $username,
            $e->getMessage(),
            $e->getCode()
        ));
    }
}

function column_exists($db, $table, $column) {
    $table = $db->real_escape_string($table);
    $column = $db->real_escape_string($column);
    $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $db->query($query);
    return $result && $result->num_rows > 0;
}

function index_exists($db, $table, $index) {
    $table = $db->real_escape_string($table);
    $index = $db->real_escape_string($index);
    $query = "SHOW INDEX FROM `$table` WHERE Key_name = '$index'";
    $result = $db->query($query);
    return $result && $result->num_rows > 0;
}

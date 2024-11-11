<?php
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    die('PHP 8.2+ required, your version: ' . PHP_VERSION . "\n");
}

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

function cache_flush() {
    global $memcached, $memcached_connected, $username;
    if ($memcached_connected) {
        try {
            $memcached->delete("profiles_list_{$username}");
            $memcached->delete("years_list_{$username}");
            $memcached->delete("stream_lock_{$username}");
            $memcached->delete("user_settings_{$username}");
        } catch (Exception $e) {}
    }
}
?>

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

try {
    $db = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
} catch (Exception $e) {
    header('HTTP/1.0 503 Service unavailable');
    die("No database connection!");
}

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
?>

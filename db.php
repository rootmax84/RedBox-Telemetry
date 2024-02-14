<?php
if (version_compare(PHP_VERSION, '8.2.0') < 0) die('PHP 8.2+ required, your version: ' . PHP_VERSION . "\n");
else if (!extension_loaded('gd')) die("php-gd extension required");
else if (!extension_loaded('mysqli')) die("php-mysql extension required");

// load database credentials
require_once ('creds.php');

if (isset($_GET['logout'])) {
    logout_user();
}

if (file_exists('maintenance') && !isset($_SESSION['admin'])) die;

// Connect to Database
try {
    $db = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
} catch (Exception $e) {
 header('HTTP/1.0 503 Service unavailable');
 die("No database connection!");
}

$db->select_db($db_name);

// helper function to quote a single identifier
// suitable for a single column name or table name
// the name will have quotes around it
function quote_name($name) {
  return "`" . str_replace("`", "``", $name) . "`";
}

// helper function to quote column names
// when constructing a query, give a list of column names, and
// it will return a properly-quoted string to put in the query
function quote_names($column_names) {
  $quoted_names = array();
  foreach ($column_names as $name) {
    $quoted_names[] = quote_name($name);
  }
  return implode(", ", $quoted_names);
}

// helper function to quote a single value
// suitable for a single value
// the value will have quotes around it
function quote_value($value) {
  require ('creds.php');
  if (!isset($db)) $db = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
  return "'" . $db->real_escape_string($value) . "'";
}

function search($value) {
  require ('creds.php');
  if (!isset($db)) $db = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
  return "'%" . $db->real_escape_string($value) . "%'";
}

// helper function to quote multiple values
// when constructing a query, give a list of values, and
// it will return a properly-quoted string to put in the query
function quote_values($values) {
  $quoted_values = array();
  foreach ($values as $value) {
    $quoted_values[] = quote_value($value);
  }
  return implode(", ", $quoted_values);
}
?>

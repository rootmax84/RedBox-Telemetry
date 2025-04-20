<?php
require_once('db.php');
require_once('auth_user.php');
require_once('translations.php');

if (empty($_POST)) {
    die("Invalid Requests");
}

$db->begin_transaction();

try {
  if (!isset($_POST["delete"])) {
    foreach ($_POST as $field_name => $val) {
        $field_name = strip_tags(trim($field_name));
        $val = strip_tags(trim($val));

        $parts = explode(':', $field_name);

        if (count($parts) === 2) {
            [$field_name, $id] = $parts;
        } else {
            continue;
        }

        if (empty($id) || empty($field_name) || !isset($val)) {
            continue;
        }

        if (in_array($field_name, ['populated', 'stream', 'favorite'])) {
            $val = ($val === 'true') ? 1 : 0;
        }

        $db->execute_query("UPDATE $db_pids_table SET " . quote_name($field_name) . " = ? WHERE id = ?", [$val, $id]);
    }
  } else {
        $pid = $_POST["delete"];
        $db->execute_query("DELETE FROM $db_pids_table WHERE id = ?", [$pid]);
        if (column_exists($db, $db_table, $pid)) {
            $db->query("ALTER TABLE $db_table DROP COLUMN " . quote_name($pid));
        }
  }

    $db->commit();
    echo $translations[$_COOKIE['lang']]['dialog.pid.update'];
} catch (Exception $e) {
    $db->rollback();
    echo "Error: " . $e->getMessage();
}
cache_flush();
$db->close();
?>
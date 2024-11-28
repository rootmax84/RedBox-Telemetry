<?php
require_once('db.php');
require_once('auth_user.php');
require_once('translations.php');

if (empty($_POST)) {
    die("Invalid Requests");
}

$db->begin_transaction();

try {
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
            if ($val === 1) {
                $query = "ALTER TABLE $db_table ADD IF NOT EXISTS " . quote_name($id) . " float NOT NULL DEFAULT '0'";
                $db->query($query);
            }
        }

        if ($val === "delete") {
            $db->execute_query("DELETE FROM $db_pids_table WHERE id = ?", [$id]);
            $db->query("ALTER TABLE $db_table DROP IF EXISTS " . quote_name($id));
        } else {
            $db->execute_query("UPDATE $db_pids_table SET " . quote_name($field_name) . " = ? WHERE id = ?", [$val, $id]);
        }
    }

    $db->commit();
    echo  $translations[$_COOKIE['lang']]['dialog.pid.update'];
} catch (Exception $e) {
    $db->rollback();
    echo "Error: " . $e->getMessage();
}
cache_flush();
$db->close();
?>
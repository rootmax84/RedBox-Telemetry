<?php
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/auth_user.php';
require_once __DIR__ . '/translations.php';
require_once __DIR__ . '/src/methods.php';
allowMethods('POST');

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
            $allowed_fields = ['description', 'units', 'populated', 'stream', 'favorite'];
            if (!in_array($field_name, $allowed_fields, true)) {
                continue;
            }
        } else {
            continue;
        }

        if (empty($id) || empty($field_name) || !isset($val)) {
            continue;
        }

        if (in_array($field_name, ['populated', 'stream', 'favorite'])) {
            $val = ($val === 'true') ? 1 : 0;
        }

        $db->execute_query(
            "UPDATE pids SET " . quote_name($field_name) . " = ? WHERE user_id = ? AND id = ?",
            [$val, current_user_id(), $id]
        );
    }
  } else {
        $pid = $_POST["delete"];
        $db->execute_query("DELETE FROM pids WHERE user_id = ? AND id = ?", [current_user_id(), $pid]);
  }

    $db->commit();
    echo $translations[current_lang()]['dialog.pid.update'];
} catch (Exception $e) {
    $db->rollback();
    echo "Error: " . $e->getMessage();
}
cache_flush();
$db->close();

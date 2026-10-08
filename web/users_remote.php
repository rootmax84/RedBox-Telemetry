<?php
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/db.php';

$qry = $db->execute_query(
    "SELECT token, mcu_data FROM users WHERE user=?",
    [$username]
)->fetch_row();

[$token, $mcu_data] = $qry;

$db->close();

$isValid = false;
$data    = null;

if (is_string($mcu_data) && $mcu_data !== '') {
    [$isValid] = validateMcuData($mcu_data);

    if ($isValid) {
        $array     = explode(',', $mcu_data);
        $dataArray = array_slice($array, 0, -2); // drop '~' and timestamp
        $data      = implode(',', $dataArray);
    }
}

include_once __DIR__ . '/src/head.php';
include_once __DIR__ . '/src/remote_inc.php';

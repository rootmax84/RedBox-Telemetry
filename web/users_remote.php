<?php
require_once 'db.php';

$qry = $db->execute_query("SELECT token, mcu_data FROM $db_users WHERE user=?", [$username])->fetch_row();
[$token, $mcu_data] = $qry;

$db->close();

$array = explode(',', $mcu_data);

$isValid = true;

if (count($array) !== 406) {
    $isValid = false;
}

$secondLast = $array[count($array) - 2] ?? null;
if ($secondLast !== '~') {
    $isValid = false;
}

$lastElement = $array[count($array) - 1] ?? null;
$lastElement = (int)$lastElement / 1000;
if (!is_numeric($lastElement) || $lastElement <= 0) {
    $isValid = false;
} else {
    $timestamp = (int)$lastElement;
    $currentTime = time();
    if ($timestamp > $currentTime + 3600) {
        $isValid = false;
    }
    if ($timestamp < 1609459200) {
        $isValid = false;
    }
}

if ($isValid) {
    $dataArray = array_slice($array, 0, -2);
    $data = implode(",", $dataArray);
}

include 'head.php';
include 'remote_inc.php';
?>
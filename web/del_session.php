<?php
if (!isset($_SESSION)) { session_start(); }

$deletesession = filter_input(INPUT_POST, 'deletesession', FILTER_SANITIZE_NUMBER_INT) 
               ?? filter_input(INPUT_GET, 'deletesession', FILTER_SANITIZE_NUMBER_INT);

if ($deletesession !== '' && $deletesession !== false && $deletesession !== null) {
    $db->execute_query("DELETE FROM $db_table WHERE session=?", [$deletesession]);
    $db->execute_query("DELETE FROM $db_sessions_table WHERE session=?", [$deletesession]);
    header("Location: .");
}
?>

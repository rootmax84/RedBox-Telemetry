<?php
if (!isset($_SESSION)) { session_start(); }

if (isset($_POST["deletesession"])) {
    $deletesession = preg_replace('/\D/', '', $_POST['deletesession']);
}
elseif (isset($_GET["deletesession"])) {
    $deletesession = preg_replace('/\D/', '', $_GET['deletesession']);
}

if (isset($deletesession) && !empty($deletesession)) {
    $db->execute_query("DELETE FROM $db_table WHERE session=?", [$deletesession]);
    $db->execute_query("DELETE FROM $db_sessions_table WHERE session=?", [$deletesession]);
    header("Location: .");
}
?>

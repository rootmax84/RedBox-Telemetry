<?php
if (!isset($_SESSION['admin'])) { //admin not need db tables
	require_once('auth_user.php');
	require_once('del_session.php');
	require_once('get_sessions.php');
	require_once('get_columns.php');

 ////size limit
 $db_limit = $db->execute_query("SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [$db_name, $db_table])->fetch_row()[0];

 $row = $db->execute_query("SELECT s FROM $db_users WHERE user=?",[$username])->fetch_assoc();
 $_SESSION['torque_limit'] = $row['s'];

 function map($x, $in_min, $in_max, $out_min, $out_max) {
   return ($x - $in_min) * ($out_max - $out_min) / ($in_max - $in_min) + $out_min;
 }

 //send used space to frontend
 $db_limit >= $limit ? $db_used = "100%" : $db_used = $limit == -1 ? 0 : round(map($db_limit, 0, $limit, 0, 100))."%";

 if ($row['s'] == 0) { //Banned
  session_destroy();
  header("Refresh:0; url=catch.php?c=disabled");
  die;
 }
}
?>

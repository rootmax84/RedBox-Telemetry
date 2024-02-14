<?php
$colqry = $db->query("SELECT id,description FROM $db_pids_table WHERE populated = 1 ORDER BY description");
while ($x = $colqry->fetch_array()) {
    $coldata[] = array("colname"=>$x[0], "colcomment"=>$x[1]);
}

if (isset($coldata)) $numcols = strval(count($coldata)+1);

//TODO: Do this once in a dedicated file
if (isset($_POST["id"])) {
  $session_id = preg_replace('/\D/', '', $_POST['id']);
}
elseif (isset($_GET["id"])) {
  $session_id = preg_replace('/\D/', '', $_GET['id']);
}

$coldataempty = array();
?>

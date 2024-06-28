<?php
$coldata = [];
$colqry = $db->query("SELECT id, description, favorite FROM $db_pids_table WHERE populated = 1 ORDER BY description");
while ($x = $colqry->fetch_assoc()) {
    $coldata[] = [
        "colname" => $x['id'],
        "colcomment" => $x['description'],
        "colfavorite" => $x['favorite']
    ];
}

$numcols = count($coldata) + 1;

$session_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT) 
            ?? filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT) 
            ?? null;

$coldataempty = [];
?>

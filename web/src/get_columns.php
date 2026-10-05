<?php
require_once __DIR__ . '/helpers.php';

$columns_cache_key = "columns_data_pids_{$username}";

$coldata = [];
if ($memcached_connected) {
    $coldata = $memcached->get($columns_cache_key);
    if ($memcached->getResultCode() !== RatelCache::RES_SUCCESS) {
        $coldata = [];
    }
}

if (empty($coldata)) {
    $colqry = $db->execute_query(
        "SELECT id, description, favorite FROM pids
          WHERE user_id = ? AND populated = 1
          ORDER BY description",
        [current_user_id()]
    );
    while ($x = $colqry->fetch_assoc()) {
        $coldata[] = [
            "colname" => $x['id'],
            "colcomment" => $x['description'],
            "colfavorite" => $x['favorite']
        ];
    }

    if ($memcached_connected) {
        try {
            $memcached->set($columns_cache_key, $coldata, $db_memcached_ttl ?? 3600);
        } catch (Exception $e) {
            $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
            error_log($errorMessage);
        }
    }
}

$numcols = count($coldata) + 1;

$session_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            ?? filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT)
            ?? null;

$coldataempty = [];

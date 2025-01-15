<?php
if (!isset($_SESSION['admin'])) { //admin not need db tables
    require_once('auth_user.php');
    require_once('del_session.php');
    require_once('get_sessions.php');
    require_once('get_columns.php');

    // Cache keys
    $db_limit_cache_key = "db_limit_{$db_table}";
    $user_status_cache_key = "user_status_{$username}";

    $db_limit = false;
    if ($memcached_connected) {
        $db_limit = $memcached->get($db_limit_cache_key);
    }

    if ($db_limit === false) {
        $db_limit = $db->execute_query("SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [$db_name, $db_table])->fetch_row()[0];
        if ($memcached_connected) {
            try {
                $memcached->set($db_limit_cache_key, $db_limit, 300);
            } catch (Exception $e) {
                error_log("Memcached error (db_limit): " . $e->getMessage());
            }
        }
    }

    $user_status = false;
    if ($memcached_connected) {
        $user_status = $memcached->get($user_status_cache_key);
    }

    if ($user_status === false) {
        $row = $db->execute_query("SELECT s FROM $db_users WHERE user=?", [$username])->fetch_assoc();
        $user_status = $row['s'];
        if ($memcached_connected) {
            try {
                $memcached->set($user_status_cache_key, $user_status, 300);
            } catch (Exception $e) {
                error_log("Memcached error (user_status): " . $e->getMessage());
            }
        }
    }

    $_SESSION['torque_limit'] = $user_status;

    function map($x, $in_min, $in_max, $out_min, $out_max) {
        return ($x - $in_min) * ($out_max - $out_min) / ($in_max - $in_min) + $out_min;
    }

    //send used space to frontend
    $db_limit >= $limit ? $db_used = "100%" : $db_used = $limit == -1 ? 0 : round(map($db_limit, 0, $limit, 0, 100))."%";

    if ($user_status == 0) { //Banned
        session_destroy();
        header('Location: catch.php?c=disabled');
        die;
    }
}
?>

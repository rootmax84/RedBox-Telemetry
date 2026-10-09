<?php
if (!isset($_SESSION['admin'])) { //admin not need db tables
    require_once __DIR__ . '/auth_user.php';
    require_once __DIR__ . '/../del_session.php';
    require_once __DIR__ . '/get_sessions.php';
    require_once __DIR__ . '/get_columns.php';
    require_once __DIR__ . '/helpers.php';

    // Cache keys
    $db_limit_cache_key    = "session_count_{$user_id}";
    $user_status_cache_key = "user_status_{$username}";

    $db_limit = false;
    if ($memcached_connected) {
        $db_limit = $memcached->get($db_limit_cache_key);
    }

    if ($db_limit === false) {
        $db_limit = (int)$db->execute_query(
            "SELECT COUNT(*) FROM sessions WHERE user_id = ?",
            [$user_id]
        )->fetch_row()[0];
        if ($memcached_connected) {
            try {
                // короткий TTL — счётчик меняется на каждой загрузке
                $memcached->set($db_limit_cache_key, $db_limit, 60);
            } catch (Exception $e) {
                $errorMessage = sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    $user_status = false;
    if ($memcached_connected) {
        $user_status = $memcached->get($user_status_cache_key);
    }

    if ($user_status === false) {
        $row = $db->execute_query("SELECT s FROM users WHERE user=?", [$username])->fetch_assoc();
        $user_status = $row['s'];
        if ($memcached_connected) {
            try {
                $memcached->set($user_status_cache_key, $user_status, $db_cache_meta_ttl ?? 300);
            } catch (Exception $e) {
                $errorMessage = sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    $_SESSION['torque_limit'] = $user_status;

    //send used space to frontend
//    $db_used = $limit == -1 ? 0 : round(map($db_limit, 0, $limit, 0, 100));
    $db_used = $limit == -1 ? 0 : $db_limit . '/' . $limit;

    if (!headers_sent()) {
        setcookie("storage_usage", $db_used, 0, "/");
    }

    if ($user_status == 0) { //Banned
        session_destroy();
        header('Location: /catch?c=disabled');
        die;
    }
}

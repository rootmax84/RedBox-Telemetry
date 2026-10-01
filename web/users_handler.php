<?php
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/auth_functions.php';
require_once __DIR__ . '/src/db.php';
include_once __DIR__ . '/translations.php';

function handleUserSettings($db, $translations, $username, $admin, $db_users) {
    if (!isset($_POST['speed'], $_POST['temp'], $_POST['pressure'], $_POST['boost'],
          $_POST['time'], $_POST['gap'], $_POST['stream_lock'],
          $_POST['sessions_filter'], $_POST['api_gps']) || $username == $admin) {
        return false;
    }

    $params = [
        $_POST['speed'], $_POST['temp'], $_POST['pressure'], $_POST['boost'],
        $_POST['time'], $_POST['gap'], $_POST['stream_lock'],
        $_POST['sessions_filter'], $_POST['api_gps'], $_COOKIE['lang'], $username
    ];

    $db->execute_query(
        "UPDATE $db_users SET speed=?, temp=?, pressure=?, boost=?, time=?, gap=?, stream_lock=?, sessions_filter=?, api_gps=?, lang=? WHERE user=?",
        $params
    );

    setcookie("timeformat", $_POST['time'] == '1' ? '24' : '12');
    setcookie("gap", $_POST['gap']);
    $_SESSION['sessions_filter'] = $_POST['sessions_filter'];

    $token = $db->execute_query("SELECT token FROM $db_users WHERE user=?", [$username])->fetch_assoc()["token"];
    cache_flush($token);
    cache_flush();

    return $translations[$_COOKIE['lang']]['set.common.updated'];
}

function handleTokenRequests($db, $translations, $username, $admin, $db_users) {
    if ($username == $admin) return false;

    if (isset($_GET['get_token'])) {
        $row = $db->execute_query("SELECT token FROM $db_users WHERE user=?", [$username])->fetch_assoc();
        return $row["token"] ?? $translations[$_COOKIE['lang']]['new.token'];
    }

    if (isset($_GET['renew_token'])) {
        $token = $db->execute_query("SELECT token FROM $db_users WHERE user=?", [$username])->fetch_assoc()["token"];
        cache_flush($token);
        $token = generate_token($username);
        $db->execute_query("UPDATE $db_users SET token=? WHERE user=?", [$token, $username]);
        return $translations[$_COOKIE['lang']]['set.token.updated'];
    }

    return false;
}

function handlePasswordChange($db, $translations, $username, $admin, $salt, $db_users) {
    if (!isset($_POST['old_p'], $_POST['new_p1'], $_POST['new_p2']) || $username == $admin) {
        return false;
    }

    $row = $db->execute_query("SELECT id, pass FROM $db_users WHERE user=?", [$username])->fetch_assoc();

    if (!password_verify($_POST['old_p'], $row["pass"])) {
        return $translations[$_COOKIE['lang']]['set.pwd.wrong.curr'];
    }
    if ($_POST['new_p1'] != $_POST['new_p2']) {
        return $translations[$_COOKIE['lang']]['set.pwd.not.match'];
    }
    if (mb_strlen($_POST['new_p1']) < 8) {
        return $translations[$_COOKIE['lang']]['set.pwd.short'];
    }
    if ($_POST['old_p'] == $_POST['new_p1']) {
        return $translations[$_COOKIE['lang']]['set.pwd.same'];
    }
    if (!preg_match("#[0-9]+#", $_POST['new_p2'])) {
        return $translations[$_COOKIE['lang']]['set.pwd.number'];
    }
    if (!preg_match("#[a-zA-Z]+#", $_POST['new_p2'])) {
        return $translations[$_COOKIE['lang']]['set.pwd.char'];
    }

    $db->execute_query(
        "UPDATE $db_users SET pass=? WHERE id=?",
        [password_hash($_POST['new_p2'], PASSWORD_DEFAULT, $salt), $row['id']]
    );

    return $translations[$_COOKIE['lang']]['set.pwd.changed'];
}

/* ════════════════════════════════════════════════════════════════
 * Main execution flow
 * ════════════════════════════════════════════════════════════════ */

try {
    $response = false;

    /* ─────────── Regular user requests ─────────── */

    if (isset($username) && $username != $admin) {
        $response = handleUserSettings($db, $translations, $username, $admin, $db_users) ?:
                   handleTokenRequests($db, $translations, $username, $admin, $db_users) ?:
                   handlePasswordChange($db, $translations, $username, $admin, $salt, $db_users);

        // Handle Telegram integration
        if (isset($_POST['tg_token'], $_POST['tg_chatid'])) {
            $token = $db->execute_query("SELECT token FROM $db_users WHERE user=?", [$username])->fetch_assoc()["token"];
            cache_flush($token);

            // Validate and sanitize Telegram token and chat ID
            $tg_token = $_POST['tg_token'] !== '' ? trim($_POST['tg_token']) : null;
            $tg_chatid = $_POST['tg_chatid'] !== '' ? trim($_POST['tg_chatid']) : null;

            if ($tg_token !== null && !preg_match('/^\d+:[a-zA-Z0-9_-]+$/', $tg_token)) {
                $tg_token = null;
            }
            if ($tg_chatid !== null && !preg_match('/^-?\d+$/', $tg_chatid)) {
                $tg_chatid = null;
            }

            $db->execute_query(
                "UPDATE $db_users SET tg_token=?, tg_chatid=? WHERE user=?",
                [$tg_token, $tg_chatid, $username]
            );

            $testMessage = notify("👋", $tg_token, $tg_chatid, $tg_socks_proxy ?? '');

            $response = $testMessage === null
                ? $translations[$_COOKIE['lang']]['set.nothing']
                : ($testMessage === -1
                    ? $translations[$_COOKIE['lang']]['set.tg.timeout']
                    : ($testMessage['ok']
                        ? $translations[$_COOKIE['lang']]['set.tg.send']
                        : $testMessage['description']));
        }

        // Handle share secret update
        if (isset($_POST['share_secret'])) {
            $secret = bin2hex(random_bytes(16));
            $db->execute_query("UPDATE $db_users SET share_secret=? WHERE user=?", [$secret, $username]);
            $_SESSION['share_secret'] = $secret;
            cache_flush();
            $response = $translations[$_COOKIE['lang']]['share.sec.update'];
        }
    }

    /* ─────────── Admin requests ─────────── */

    if (isset($_SESSION['admin'])) {

        /* ── Edit user ── */
        if (isset($_POST['e_login'])) {
            $login = preg_replace('/[^\p{L}\p{N}_]+/u', '', $_POST['e_login']);
            $password = $_POST['e_pass'];
            $e_limit = $_POST['e_limit'];

            if ($login == $admin && $e_limit != null) {
                die($translations[$_COOKIE['lang']]['admin.limit.catch']);
            }

            $row = $db->execute_query("SELECT id, token FROM $db_users WHERE user=?", [$login])->fetch_assoc();

            if (!$row) {
                die($translations[$_COOKIE['lang']]['admin.user.not.found'].$login);
            }
            if (mb_strlen($password) > 1 && mb_strlen($password) < 5) {
                die($translations[$_COOKIE['lang']]['admin.pwd.short']);
            }
            if (!strlen($e_limit) && !mb_strlen($password)) {
                die($translations[$_COOKIE['lang']]['set.nothing']);
            }
            if (!mb_strlen($password) && strlen($e_limit)) {
                $db->execute_query("UPDATE $db_users SET s=? WHERE id=?", [$e_limit, $row['id']]);
                $response = $translations[$_COOKIE['lang']]['admin.limit.changed'].$login;
            }
            elseif (mb_strlen($password) && !strlen($e_limit)) {
                $db->execute_query("UPDATE $db_users SET pass=? WHERE id=?", [password_hash($password, PASSWORD_DEFAULT, $salt), $row['id']]);
                $response = $translations[$_COOKIE['lang']]['admin.pwd.changed'].$login;
            }
            else {
                $db->execute_query("UPDATE $db_users SET pass=?, s=? WHERE id=?", [password_hash($password, PASSWORD_DEFAULT, $salt), $e_limit, $row['id']]);
                $response = $translations[$_COOKIE['lang']]['admin.changed'].$login;
            }

            $username = $login;
            cache_flush($row['token']);
            cache_flush();
        }

        /* ── Register user ── */
        elseif (isset($_POST['reg_login'], $_POST['reg_pass'])) {
            $login = preg_replace('/[^\p{L}\p{N}_]+/u', '', $_POST['reg_login']);
            $password = $_POST['reg_pass'];

            $userqry = $db->execute_query("SELECT id FROM $db_users WHERE user=?", [$login]);

            if ($userqry->num_rows || mb_strlen($login) < 1 || mb_strlen($login) > 32) {
                die($translations[$_COOKIE['lang']]['admin.user.exists']);
            }

            if (mb_strlen($password) < 5) {
                die($translations[$_COOKIE['lang']]['admin.pwd.short']);
            }

            // ── Создать запись в users ──
            $db->execute_query(
                "INSERT INTO $db_users (user, pass, s) VALUES (?,?,?)",
                [$login, password_hash($password, PASSWORD_DEFAULT, $salt), $def_limit]
            );

            $new_user_id = (int)$db->insert_id;

            if ($new_user_id <= 0) {
                die($translations[$_COOKIE['lang']]['admin.user.exists']);
            }

            // ── Засеять дефолтные PID'ы ──
            $include_legacy = isset($_POST['reg_legacy']);
            seed_default_pids($db, $new_user_id, $include_legacy);

            $response = $translations[$_COOKIE['lang']]['admin.user.added'].$login;
        }

        /* ── Delete user ── */
        elseif (isset($_POST['del_login'])) {
            $login = preg_replace('/[^\p{L}\p{N}_]+/u', '', $_POST['del_login']);

            $userqry = $db->execute_query("SELECT id, token FROM $db_users WHERE user=?", [$login]);

            if (!$userqry->num_rows || mb_strlen($login) < 1) {
                die($translations[$_COOKIE['lang']]['admin.user.not.found'].$login);
            }

            if ($login == $admin) {
                die($translations[$_COOKIE['lang']]['admin.del.admin']);
            }

            $row        = $userqry->fetch_assoc();
            $target_uid = (int)$row['id'];
            $token      = $row['token'] ?? null;

            // ── Удалить все данные юзера из shared-таблиц ──
            $db->execute_query("DELETE FROM logs     WHERE user_id = ?", [$target_uid]);
            $db->execute_query("DELETE FROM sessions WHERE user_id = ?", [$target_uid]);
            $db->execute_query("DELETE FROM pids     WHERE user_id = ?", [$target_uid]);

            // ── Удалить самого юзера ──
            $db->execute_query("DELETE FROM $db_users WHERE id = ?", [$target_uid]);

            $username = $login;

            // Сбросить кэш удаляемого юзера.
            $saved_uid = $_SESSION['uid'] ?? null;
            $saved_user_id = $user_id;

            $_SESSION['uid'] = $target_uid;
            $user_id = $target_uid;

            if (!empty($token)) {
                cache_flush($token);
            }
            cache_flush();

            $_SESSION['uid'] = $saved_uid;
            $user_id = $saved_user_id;

            $response = $translations[$_COOKIE['lang']]['admin.del.ok'].$login;
        }

        /* ── Truncate user data ── */
        elseif (isset($_POST['trunc_login'])) {
            $login = preg_replace('/[^\p{L}\p{N}_]+/u', '', $_POST['trunc_login']);

            $userqry = $db->execute_query("SELECT id, token FROM $db_users WHERE user=?", [$login]);

            if (!$userqry->num_rows || mb_strlen($login) < 1) {
                die($translations[$_COOKIE['lang']]['admin.user.not.found'].$login);
            }

            if ($login == $admin) {
                die($translations[$_COOKIE['lang']]['admin.trunc.admin']);
            }

            $row        = $userqry->fetch_assoc();
            $target_uid = (int)$row['id'];
            $token      = $row['token'] ?? null;

            // ── Удалить данные из shared-таблиц ──
            $db->execute_query("DELETE FROM logs     WHERE user_id = ?", [$target_uid]);
            $db->execute_query("DELETE FROM sessions WHERE user_id = ?", [$target_uid]);

            // ── Сбросить pids к дефолтному набору ──
            // Legacy не восстанавливаем: если юзер их использовал, они появятся
            // автоматически при следующем импорте (INSERT IGNORE в import_torque.php)
            $db->execute_query("DELETE FROM pids WHERE user_id = ?", [$target_uid]);
            seed_default_pids($db, $target_uid, false);

            $username = $login;

            // Сбросить кэш целевого юзера (не админа).
            // cache_flush() определяет uid через $_SESSION['uid'] — временно
            // подменяем, чтобы очистить session_count_, pids_known_,
            // share_data_, share_plot_ именно для target_uid.
            $saved_uid = $_SESSION['uid'] ?? null;
            $saved_user_id = $user_id;

            $_SESSION['uid'] = $target_uid;
            $user_id = $target_uid;

            if (!empty($token)) {
                cache_flush($token);
            }
            cache_flush();

            $_SESSION['uid'] = $saved_uid;
            $user_id = $saved_user_id;

            $response = $translations[$_COOKIE['lang']]['admin.trunc'].$login;
        }

        /* ── Invalid admin request ── */
        else {
            http_response_code(403);
            header("Location: .");
            die;
        }
    }

    // Send response if any handler produced one
    if ($response !== false) {
        $db->close();
        die($response);
    }

    // Redirect non-admin users
    if (!isset($_SESSION['admin'])) {
        header("Location: .");
        die;
    }

} catch (Exception $e) {
    die($e->getMessage());
}

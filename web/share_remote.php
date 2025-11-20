<?php
$_SESSION['torque_logged_in'] = true;
require_once 'db.php';
require_once 'helpers.php';
include_once 'translations.php';
$lang = $_COOKIE['lang'] ?? 'en';

if (!checkRateLimit(5)) {
    header('Location: catch.php?c=block');
    exit;
}

require_once 'timezone.php';

if (isset($_GET['uid'], $_GET['sig'])) {
    $uid = $_GET['uid'];
    $sig = $_GET['sig'];
    $userqry = $db->execute_query("SELECT token, mcu_data, time, share_secret, s FROM $db_users WHERE id=?", [$uid]);
    if ($userqry->num_rows) {
        $user_data = $userqry->fetch_assoc();
    } else {
        header('Location: catch.php?c=noshare');
        exit;
    }

    if ($user_data) {
        $share_secret = $user_data['share_secret'];
        $mcu_data = $user_data['mcu_data'];
        $user_time = $user_data['time'];
        $token = $user_data['token'];
        $blocked = $user_data['s'];
    }

    setcookie('timeformat', $user_time);
    $_COOKIE['timeformat'] = $user_time;

    $payload = "uid={$uid}";
    $expected_sig = hash_hmac('sha256', $payload, $share_secret);

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
} else {
    header('Location: .');
    exit;

} if ($uid) {
    if (!hash_equals($expected_sig, $sig) || $blocked === 0) {
        header('Location: catch.php?c=noshare');
        exit;
    } else {
        $_SESSION['remote_token'] = $token;
        checkRateLimit(5, 3600, true);
        $_SESSION['share'] = true;
    }
}

include 'head.php';
include 'remote_inc.php';
?>

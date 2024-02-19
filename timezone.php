<?php
 if (isset($_GET['time'])){
    $timezone = $_GET['time'];
    setcookie("timezone", $timezone);
}

 if (isset($_COOKIE['timezone'])){
    $timezone = $_COOKIE['timezone'];
    date_default_timezone_set($timezone);
}

 if (isset($_GET['format'])){
    $timeformat = $_GET['format'];
    setcookie("timeformat", $timeformat);
}

 if (isset($_COOKIE['timeformat'])){
    $timeformat = $_COOKIE['timeformat'];
}
?>
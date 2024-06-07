<?php
if (!$_COOKIE['stream']) header('HTTP/1.0 401 Unauthorized');
require_once('creds.php');
if (file_exists('maintenance')) header('HTTP/1.0 307 Temporary Redirect');
?>

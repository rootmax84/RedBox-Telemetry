<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Authorization, Content-Type');
header('Access-Control-Max-Age: 30');

$allowedMethods = ['POST', 'OPTIONS'];

if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header('Access-Control-Allow-Methods: ' . implode(", ", $allowedMethods));
    exit;
}

if (empty($_POST)) {
    http_response_code(400);
    echo 'No data provided';
    exit;
}

$user = isset($_POST['user']) ? $_POST['user'] : '';
$pass = isset($_POST['pass']) ? $_POST['pass'] : '';

if (empty($user) || empty($pass)) {
    http_response_code(400);
    echo 'Username and password are required';
    exit;
}

$_SESSION['torque_logged_in'] = true;
require_once('db.php');

$userqry = $db->execute_query("SELECT user, pass, token, s FROM $db_users WHERE user=?", [$user]);

if (!$userqry->num_rows) {
    http_response_code(401);
    echo 'User not found';
    exit;
}

$row = $userqry->fetch_assoc();

if (!$row["s"]) {
    http_response_code(403);
    echo 'User disabled';
    exit;
}

if (password_verify($pass, $row["pass"])) {
    $token = $row["token"];
    if (strpos($token, 'Welcome') === false) {
        echo $token;
    } else {
        http_response_code(406);
        echo 'Generate token first';
    }
} else {
    http_response_code(403);
    echo 'Wrong password';
}

$db->close();
?>
<?php

//Get Username from Browser-Request
function get_user()
{
    if (isset($_POST["user"])) {
        $user = $_POST['user'];
    }
    elseif (isset($_GET["user"])) {
        $user = $_GET['user'];
    }
    else
    {
        $user = "";
    }

    return $user;
}


//Get Password from Browser-Request
function get_pass()
{
    if (isset($_POST["pass"])) {
        $pass = $_POST['pass'];
    }
    elseif (isset($_GET["pass"])) {
        $pass = $_GET['pass'];
    }
    else
    {
        $pass = "";
    }

    return $pass;
}

//Auth user by user/pass
function auth_user()
{
    include('creds.php');
    try {
        if (!isset($db)) $db = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    } catch (Exception $e) {
        header('HTTP/1.0 503 Service unavailable');
        die("No database connection!");
    }

    if (file_exists('install')) {
	create_users_table();
	unlink('install');
    }

    $user = preg_replace('/\s+/', '', get_user());
    $pass = preg_replace('/\s+/', '', get_pass());

  try {
      $userqry = $db->execute_query("SELECT user, pass, s, time FROM $db_users WHERE user=?", [$user]);
  } catch(Exception $e) { return; }
	if (!$userqry->num_rows) return false;
	else {
	    $row = $userqry->fetch_assoc();
	    if (password_verify($pass, $row["pass"])) {
		$_SESSION['torque_user'] = $row["user"];
		$_SESSION['torque_pass'] = $row["pass"];
		$_SESSION['torque_limit'] = $row["s"];
		setcookie("stream", true);
		setcookie("timeformat", $row["time"]);
		$_COOKIE['timeformat'] = $row["time"];
		$db->close();
		return true;
	    }
	    else return false;
	}
}

function create_users_table()
{
 include('creds.php');
 try {
  if (!isset($db)) $db = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

  $is_empty = "SELECT * FROM $db_users LIMIT 1";

  $table = "CREATE TABLE IF NOT EXISTS " . $db_users . " (
	id bigint unsigned NOT NULL AUTO_INCREMENT,
	s bigint NOT NULL DEFAULT 100,
	user varchar(190) COLLATE utf8mb4_bin NOT NULL,
	pass varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
	token varchar(190) COLLATE utf8mb4_unicode_ci NULL,
	tg_token varchar(190) COLLATE utf8mb4_unicode_ci NULL,
	tg_chatid varchar(190) COLLATE utf8mb4_unicode_ci NULL,
	speed enum('No conversion','km to miles','miles to km') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No conversion',
	temp enum('No conversion','Celsius to Fahrenheit','Fahrenheit to Celsius') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No conversion',
	pressure enum('No conversion','Psi to Bar','Bar to Psi') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No conversion',
	boost enum('No conversion','Psi to Bar','Bar to Psi') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No conversion',
	time enum('24','12') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '24',
	PRIMARY KEY (id),
	UNIQUE KEY user (user),
	UNIQUE KEY token (token),
	KEY indexes (s,pass,tg_token,tg_chatid))
	ENGINE=".$db_engine." DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

  $db->query($table);
  if (!$db->query($is_empty)->num_rows) $db->execute_query("INSERT INTO $db_users (s, user, pass) VALUES (?,?,?)", [0, $admin, password_hash('admin', PASSWORD_DEFAULT, $salt)]);
  $db->close();
 } catch(Exception $e) {
  die($e);
 }
}

function logout_user()
{
    session_destroy();
    header("Location: .");
    die;
}

?>
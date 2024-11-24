<?php

function get_db_connection() {
    global $db_users, $live_data_rate, $db_engine, $admin, $salt;
    include('creds.php');

    if (!isset($db)) {
        try {
            $db = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
        } catch (Exception $e) {
            header('Location: catch.php?c=dberror');
        }
    }

    return $db;
}

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

function check_login_attempts($user) {
    $db = get_db_connection();
    global $db_users;

    // Clean install
    if (!check_table_exists($db, $db_users)) {
        return true;
    }

    $result = $db->execute_query("SELECT login_attempts, last_attempt FROM $db_users WHERE user=?", [$user]);
    $row = $result->fetch_assoc();

    if ($row['login_attempts'] >= 5 && (time() - strtotime($row['last_attempt'])) < 300) {
        return false; // Blocked
    }

    return true; // Non blocked
}

function update_login_attempts($user, $success) {
    $db = get_db_connection();
    global $db_users;

    if ($success) {
        $db->execute_query("UPDATE $db_users SET login_attempts = 0, last_attempt = NOW() WHERE user=?", [$user]);
    } else {
        $db->execute_query("UPDATE $db_users SET login_attempts = login_attempts + 1, last_attempt = NOW() WHERE user=?", [$user]);
    }
}

//Auth user by user/pass
function auth_user()
{
    $db = get_db_connection();
    global $db_users, $live_data_rate;

    global $csrf_exempt_scripts;
    $current_script = basename($_SERVER['SCRIPT_FILENAME']);

    if (!in_array($current_script, $csrf_exempt_scripts)) {
        // CSRF token check
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            return false;
        }
    }

    if (file_exists('install')) {
        create_users_table();
        unlink('install');
    }

    $user = preg_replace('/\s+/', '', get_user());
    $pass = preg_replace('/\s+/', '', get_pass());

    if (!check_login_attempts($user)) {
        header('Location: catch.php?c=toomanyattempts');
        exit;
    }

    try {
        $userqry = $db->execute_query("SELECT user, pass, s, time, gap FROM $db_users WHERE user=?", [$user]);
    } catch(Exception $e) { return false; }

    if (!$userqry->num_rows) {
        update_login_attempts($user, false);
        return false;
    } else {
        $row = $userqry->fetch_assoc();
        if (password_verify($pass, $row["pass"])) {
            $_SESSION['torque_user'] = $row["user"];
            $_SESSION['torque_pass'] = $row["pass"];
            $_SESSION['torque_limit'] = $row["s"];
            setcookie("stream", true);
            setcookie("timeformat", $row["time"]);
            $_COOKIE['timeformat'] = $row["time"];
            setcookie("tracking-rate", $live_data_rate);
            setcookie("gap", $row["gap"]);
            update_login_attempts($user, true);
            $db->close();
            return true;
        } else {
            update_login_attempts($user, false);
            return false;
        }
    }
}

function create_users_table()
{
 $db = get_db_connection();
 global $db_users, $db_engine, $admin, $salt;

 try {

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
	gap enum('5000','10000','20000','30000','60000') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '5000',
	stream_lock tinyint(1) NOT NULL DEFAULT 0,
	login_attempts TINYINT UNSIGNED DEFAULT 0,
	last_attempt DATETIME,
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

function perform_migration() {
    $db = get_db_connection();
    global $db_users;

    // Clean install
    if (!check_table_exists($db, $db_users)) {
        return;
    }

    $migrations = [
        "ALTER TABLE $db_users ADD COLUMN IF NOT EXISTS stream_lock TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE $db_users ADD COLUMN IF NOT EXISTS login_attempts TINYINT UNSIGNED DEFAULT 0",
        "ALTER TABLE $db_users ADD COLUMN IF NOT EXISTS last_attempt DATETIME"
    ];

    foreach ($migrations as $migration) {
        try {
            $db->query($migration);
        } catch (Exception $e) {
            die("Migration failed: " . $e->getMessage());
        }
    }
}

function check_table_exists($db, $table_name) {
    $query = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $table_name);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function logout_user()
{
    session_destroy();
    header("Location: .");
    die;
}

?>
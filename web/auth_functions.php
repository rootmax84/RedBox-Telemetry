<?php

function get_db_connection() {
    global $db_users, $live_data_rate, $db_engine, $admin, $salt, $username, $db_sessions_table;
    include 'creds.php';

    if (!isset($db)) {
        try {
            $db = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
        } catch (Exception $e) {
            if (file_exists('maintenance')) {
                header('Location: catch.php?c=maintenance');
            } else {
                header('Location: catch.php?c=dberror');
            }
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

    $result = $db->execute_query("SELECT login_attempts, UNIX_TIMESTAMP(last_attempt) as last_attempt FROM $db_users WHERE user=?", [$user]);

    if (!$result || !$result->num_rows) {
        return true;
    }

    $row = $result->fetch_assoc();

    if (isset($row['login_attempts']) && isset($row['last_attempt'])) {
        if ($row['login_attempts'] >= 5 && (time() - $row['last_attempt']) < 300) {
            return false; // Blocked
        }
    }

    return true; // Not blocked
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

    $userqry = $db->execute_query("SELECT id, user, pass, s, time, gap, sessions_filter, share_secret FROM $db_users WHERE user=?", [$user]);

    if (!$userqry->num_rows) {
        update_login_attempts($user, false);
        return false;
    } else {
        $row = $userqry->fetch_assoc();
        if (password_verify($pass, $row["pass"])) {
            $_SESSION['torque_user'] = $row["user"];
            $_SESSION['torque_limit'] = $row["s"];
            setcookie("stream", true);
            setcookie("timeformat", $row["time"]);
            $_COOKIE['timeformat'] = $row["time"];
            setcookie("tracking-rate", $live_data_rate);
            setcookie("gap", $row["gap"]);
            $_SESSION['sessions_filter'] = $row["sessions_filter"];
            $_SESSION['uid'] = $row["id"];
            $_SESSION['share_secret'] = $row["share_secret"];
            update_login_attempts($user, true);
            $db->close();

            $usr_new_sess = sys_get_temp_dir().'/'.$user;
            if (file_exists($usr_new_sess)) {
                unlink($usr_new_sess);
            }

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

  $is_empty = "SELECT * FROM $db_users LIMIT 1";

  $table = "CREATE TABLE IF NOT EXISTS " . $db_users . " (
	id bigint unsigned NOT NULL AUTO_INCREMENT,
	s mediumint NOT NULL DEFAULT 100,
	user varchar(128) COLLATE utf8mb4_bin NOT NULL,
	pass char(60) NOT NULL,
	token char(64) NULL,
	tg_token varchar(50) NULL,
	tg_chatid bigint(20) NULL,
	forward_url varchar(2083) NULL,
	forward_token varchar(128) NULL,
	share_secret char(32) NULL,
	speed enum('No conversion','km to miles','miles to km') NOT NULL DEFAULT 'No conversion',
	temp enum('No conversion','Celsius to Fahrenheit','Fahrenheit to Celsius') NOT NULL DEFAULT 'No conversion',
	pressure enum('No conversion','Psi to Bar','Bar to Psi') NOT NULL DEFAULT 'No conversion',
	boost enum('No conversion','Psi to Bar','Bar to Psi') NOT NULL DEFAULT 'No conversion',
	time enum('24','12') NOT NULL DEFAULT '24',
	gap enum('5000','10000','20000','30000','60000') NOT NULL DEFAULT '5000',
	lang enum('en','ru','es','de') NOT NULL DEFAULT 'en',
	stream_lock tinyint(1) NOT NULL DEFAULT 0,
	sessions_filter tinyint(1) NOT NULL DEFAULT 1,
	api_gps tinyint(1) NOT NULL DEFAULT 0,
	mcu_data varchar(2048) NULL,
	login_attempts tinyint UNSIGNED DEFAULT 0,
	last_attempt DATETIME,
	PRIMARY KEY (id),
	UNIQUE KEY user (user),
	UNIQUE KEY token (token))
	ENGINE=".$db_engine." DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

  $db->query($table);
  if (!$db->query($is_empty)->num_rows) $db->execute_query("INSERT INTO $db_users (s, user, pass) VALUES (?,?,?)", [0, $admin, password_hash('admin', PASSWORD_DEFAULT, $salt)]);
  $db->close();
}

function perform_migration() {
    $db = get_db_connection();
    global $db_users;

    // Clean install
    if (!check_table_exists($db, $db_users)) {
        return;
    }

    $migrations = [
        'stream_lock'     => "ALTER TABLE $db_users ADD COLUMN stream_lock TINYINT(1) NOT NULL DEFAULT 0",
        'sessions_filter' => "ALTER TABLE $db_users ADD COLUMN sessions_filter TINYINT(1) NOT NULL DEFAULT 1",
        'forward_url'     => "ALTER TABLE $db_users ADD COLUMN forward_url VARCHAR(2083) NULL",
        'forward_token'   => "ALTER TABLE $db_users ADD COLUMN forward_token VARCHAR(190) NULL",
        'share_secret'    => "ALTER TABLE $db_users ADD COLUMN share_secret CHAR(32)",
        'login_attempts'  => "ALTER TABLE $db_users ADD COLUMN login_attempts TINYINT UNSIGNED DEFAULT 0",
        'last_attempt'    => "ALTER TABLE $db_users ADD COLUMN last_attempt DATETIME",
        'api_gps'         => "ALTER TABLE $db_users ADD COLUMN api_gps TINYINT(1) NOT NULL DEFAULT 0",
        'lang'            => "ALTER TABLE $db_users ADD COLUMN lang enum('en','ru','es','de') NOT NULL DEFAULT 'en' AFTER gap",
        'mcu_data'        => "ALTER TABLE $db_users ADD COLUMN mcu_data VARCHAR(2048) NULL AFTER sessions_filter",
    ];

    foreach ($migrations as $migration => $query) {
        if (!column_exists($db, $db_users, $migration)) {
            $db->query($query);
        }
    }

    $index_name = 'indexes';
    if (index_exists($db, $db_users, $index_name)) {
        $db->query("DROP INDEX `$index_name` ON $db_users");
    }
}

function perform_user_migration() {
    $db = get_db_connection();
    global $username, $admin, $db_sessions_table;

    if ($username == $admin) {
        return;
    }

    $migrations = [
        'favorite'     => "ALTER TABLE $db_sessions_table ADD COLUMN favorite TINYINT(1) NOT NULL DEFAULT 0",
        'description'  => "ALTER TABLE $db_sessions_table ADD COLUMN description VARCHAR(128) NOT NULL DEFAULT '-' AFTER profileName",
    ];

    foreach ($migrations as $migration => $query) {
        if (!column_exists($db, $db_sessions_table, $migration)) {
            $db->query($query);
        }
    }

    $index_name = 'favorite_index';
    if (!index_exists($db, $db_sessions_table, $index_name)) {
        $db->query("ALTER TABLE $db_sessions_table ADD INDEX `$index_name` (`favorite`)");
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

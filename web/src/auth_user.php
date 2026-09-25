<?php
require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/helpers.php';
if (!isset($_SESSION)) { session_start(); }

$current_script = basename($_SERVER['SCRIPT_FILENAME']);
$csrf_exempt_scripts = ['get_token.php', 'ul.php', 'adminer.php', 'remote.php', 'search_processor.php']; //CSRF exclude

$logged_in = isset($_SESSION['torque_logged_in']) && $_SESSION['torque_logged_in'];

if(isset($_POST) && !empty($_POST)){
    if (!in_array($current_script, $csrf_exempt_scripts)) {
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            header('Location: catch.php?c=csrffailed');
            exit;
        }
    }

    if (!$logged_in) {
        perform_migration();
        if (!check_login_attempts(get_user())) {
            header('Location: catch.php?c=toomanyattempts');
            exit;
        }
        if (auth_user()) {
            perform_user_migration();
            $logged_in = true;

            $loggedInUser = $_SESSION['torque_user'];
            register_shutdown_function(function () use ($loggedInUser) {
                global $memcached, $memcached_connected;
                if (!empty($memcached_connected) && isset($memcached)) {
                    try {
                        $memcached->delete("new_session_" . $loggedInUser);
                    } catch (Throwable $e) {
                        error_log("Memcached cleanup on login failed: " . $e->getMessage());
                    }
                }
            });
        } else {
            header('Location: catch.php?c=loginfailed');
            exit;
        }
    }
}

$_SESSION['torque_logged_in'] = $logged_in;

if (!$logged_in) {
    setcookie("stream", "");
    include_once __DIR__ . '/head.php';
    ?>
    <body style="display:flex; justify-content:center; align-items:center; height:100vh">
    <div class="login login-form" id="login-form">
        <div class="login-lang" id="lang-switch">
          <div class="selected-lang" id="selected-lang"></div>
          <ul class="lang-options" id="lang-options">
            <li data-value="en">English</li>
            <li data-value="ru">Русский</li>
            <li data-value="es">Español</li>
            <li data-value="de">Deutsch</li>
          </ul>
        </div>
        <div style="font-weight:bold; color:#961911; text-align:center; width:100%; font-size:20px; letter-spacing:1.5px; text-shadow: none">RedB<img src="static/img/logo.svg" alt style="height:12px; width:12px; margin-right:1px">x Telemetry</div>
        <h6 style="text-align:center; margin-bottom:20px" l10n="login.label"></h6>
        <form method="post" class="form-group" action=".">
            <div class="clear-input">
                <input class="form-control clear-input__input" type="text" name="user" value="" maxlength="32" l10n-placeholder="login.login" autocomplete="off" required autofocus>
                <button type="button" class="clear-input__btn">
                    <span class="clear-input__icon"></span>
                </button>
            </div><br><br>
            <div class="password-toggle">
                <input class="form-control password-input" type="password" name="pass" value="" maxlength="64" l10n-placeholder="login.pwd" autocomplete="off" required="">
                <button type="button" class="password-toggle__btn">
                    <span class="password-toggle__icon"></span>
                </button>
            </div><br><br>
            <button id="login-btn" class="btn btn-info btn-sm" type="submit" name="Login" style="width:100%; height:35px" l10n="login.signin"></button>
            <div style="text-align:center; margin:15px 0 -20px; font-size:12px; opacity:.6"><a href="https://github.com/rootmax84/RedBox-Telemetry" target="_blank" l10n="login.github"></a></div>
        </form>
    </div>
    <div class="login-background"></div>
    <script src="<?php echo version_url('static/js/auth_user.js'); ?>"></script>
    </body>
    </html>
    <?php
    exit;
}

<?php
require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/helpers.php';

// API-эндпоинты (ul.php, get_token.php, stream_json.php) аутентифицируются
// собственными средствами: Bearer-токен, пароль, подпись. Форма логина им
// не нужна, сессия — тоже. Флаг RATEL_API_REQUEST сообщает этот факт
// auth_user.php, чтобы он не стартовал сессию и не рендерил HTML-форму.
$api_request = defined('RATEL_API_REQUEST') && RATEL_API_REQUEST;
$cli_request = (PHP_SAPI === 'cli');

if (!$api_request && !$cli_request && session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_script = basename($_SERVER['SCRIPT_FILENAME']);
$csrf_exempt_scripts = ['get_token.php', 'ul.php', 'adminer.php', 'remote.php'];

$logged_in = $api_request
    || $cli_request
    || (isset($_SESSION['torque_logged_in']) && $_SESSION['torque_logged_in']);

/* Определяем AJAX/JSON-запрос один раз — чтобы различать HTML-редирект и API-ответ */
$is_ajax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
        || (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
        || (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);

/* Хелпер: для AJAX — 401 JSON, для обычного запроса — редирект на catch.php */
$auth_fail = function (string $reason, int $http_code = 401, ?string $catch = null) use ($is_ajax) {
    if ($is_ajax) {
        http_response_code($http_code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $reason, 'reload' => true]);
        exit;
    }
    header('Location: /catch?c=' . ($catch ?? $reason));
    exit;
};

if(isset($_POST) && !empty($_POST)){
    if (!in_array($current_script, $csrf_exempt_scripts)) {
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $auth_fail('csrffailed', 401, 'csrffailed');
        }
    }

    if (!$logged_in) {
        if (!check_login_attempts(get_user())) {
            $auth_fail('toomanyattempts', 429, 'toomanyattempts');
        }
        if (auth_user()) {
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
            $auth_fail('loginfailed', 401, 'loginfailed');
        }
    }
}

if (!$api_request) {
    $_SESSION['torque_logged_in'] = $logged_in;
}

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
        <div style="font-weight:bold; color:#961911; text-align:center; width:100%; font-size:20px; letter-spacing:1.5px; text-shadow: none">RedB<img src="/static/img/logo.svg" alt style="height:12px; width:12px; margin-right:1px">x Telemetry</div>
        <h6 style="text-align:center; margin-bottom:20px" l10n="login.label"></h6>
        <form method="post" class="form-group" action="/">
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

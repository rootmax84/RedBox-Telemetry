<?php
require_once ('creds.php');
require_once ('auth_functions.php');
require_once ('token_functions.php');
if (!isset($_SESSION)) { session_start(); }

$current_script = basename($_SERVER['SCRIPT_FILENAME']);
$csrf_exempt_scripts = ['get_token.php', 'ul.php', 'adminer.php']; //CSRF exclude

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
            $logged_in = true;
        } else {
            header('Location: catch.php?c=loginfailed');
            exit;
        }
    }
}

$_SESSION['torque_logged_in'] = $logged_in;

if (!$logged_in) {
    setcookie("stream", "");
    include("head.php");
    ?>
    <body style="display:flex; justify-content:center; align-items:center; height:100vh">
    <div class="login login-form" id="login-form">
        <div style="font-weight:bold; color:red; text-align:center; width:100%; font-size:16px; letter-spacing:1.5px">RedB<img src="static/img/logo.svg" alt style="height:10px; width:12px; margin-right:1px">x Telemetry</div>
        <h6 style="text-align:center; margin-bottom:20px">Sign in to your account</h6>
        <form method="post" class="form-group" action=".">
            <input class="form-control" type="text" name="user" value="" placeholder="(Username)" autocomplete="off" required><br>
            <input class="form-control" type="password" name="pass" value="" placeholder="(Password)" autocomplete="off" required><br>
            <button id="login-btn" class="btn btn-info btn-sm" type="submit" name="Login" style="width:100%; height:35px">Sign in</button>
            <div style="text-align:center; margin:15px 0 -20px; font-size:12px; opacity:.6"><a href="https://github.com/rootmax84/RedBox-Telemetry" target="_blank">Project on Github</a></div>
        </form>
    </div>
    <div class="login-background"></div>
    <script>
        $(document).ready(function(){
            $("#login-form").css({"opacity":"1"});
            $(".form-group").submit((e)=>{
                $("#login-btn").prop('disabled', true);
                $("#login-btn").html('<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"><circle cx="18" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin=".67" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle><circle cx="12" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin=".33" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle><circle cx="6" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin="0" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle></svg>');
            });
        });
    </script>
    </body>
    </html>
    <?php
    exit;
}
?>

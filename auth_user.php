<?php
require_once ('creds.php');
require_once ('auth_functions.php');
if (!isset($_SESSION)) { session_start(); }

//This variable will be evaluated at the end of this file to check if a user is authenticated
$logged_in = false;
$captcha = false;

if (!isset($_SESSION['torque_logged_in'])) {
    $_SESSION['torque_logged_in'] = false;
}
$logged_in = (boolean)$_SESSION['torque_logged_in'];

if(isset($_POST) && !empty($_POST)){
    if (isset($_POST['captcha']) && isset($_SESSION['code']) && $_POST['captcha'] != $_SESSION['code'] && !$logged_in) {
        header('Location: catch.php?c=loginfailed');
        die;
    }
    else $captcha = true;
}

if (!$logged_in && $captcha)
{
    if ( auth_user() && $captcha) {
        $logged_in = true;
    }
    else {
        header('Location: catch.php?c=loginfailed');
        die;
    }
}

$_SESSION['torque_logged_in'] = $logged_in;

if (!$logged_in) {
 setcookie("stream", "");
?><!DOCTYPE html>
<html lang="en">
    <head>
    <?php include("head.php");?>
    </head>
    <body style="height:auto">
        <div class="login" id="login-form" style="margin:5% auto; opacity:0; transition:.5s;">
            <div style="font-weight:bold; color:red; text-align:center; width:100%; font-size:16px; letter-spacing:1.5px">RedB<img src="static/img/logo.svg" alt style="height:10px; width:12px; margin-right:1px">x Telemetry</div>
            <h4 style="text-align:center">Login</h4>
                <form method="post" class="form-group" action=".">
                <input class="form-control" type="text" name="user" value="" placeholder="(Username)" autocomplete="off"><br>
                <input class="form-control" type="password" name="pass" value="" placeholder="(Password)" autocomplete="off"><br>
                <div id="captcha"><input class="form-control" style="width:70%;" type="number" min="0" max="9999" name="captcha" placeholder="(Captcha)" autocomplete="off" onkeydown="javascript: return event.keyCode == 69 ? false : true"></div><br>
                <button id="login-btn" class="btn btn-info btn-sm" type="submit" name="Login" style="width:100%; height:35px" onClick="this.form.submit(); signIn()">Login</button>
                <div style="text-align:center; margin:15px 0 -20px; font-size:12px; opacity:.6"><a href="https://github.com/rootmax84/RedBox-Telemetry" target="_blank">Project on Github</a></div>
            </form>
	</div>
<script>
function signIn() {
 $("#login-btn").prop('disabled','true');
 $("#login-btn").html('<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"><circle cx="18" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin=".67" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle><circle cx="12" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin=".33" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle><circle cx="6" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin="0" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle></svg>');
}

$(document).ready(function(){
    $("#captcha").css("background", "url(captcha.php?r=" + Math.random() + ") no-repeat right");
    $("#login-form").css({"opacity":"1", "margin":"10% auto"});
});
</script>
    </body>
</html>
<?php
    exit(0);
}
else
{
    //Prepare session
}
?>

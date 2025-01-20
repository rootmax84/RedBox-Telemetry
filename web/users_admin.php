<?php
require_once ('creds.php');
require_once ('auth_functions.php');
require_once ('db.php');

if (!isset($_SESSION['admin'])) {
    header('HTTP/1.0 403 Forbidden');
    header("Location: .");
    die;
}

if (!isset($_GET['action'])) {
 header("Location: .");
 die;
}
include("head.php");
?>
    <body>
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse" style="position: relative">
            <div class="container">
              <div id="theme-switch"></div>
                <div class="navbar-header">
		    <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
                </div>
            </div>
        </div>
        <div class="login">
	<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
<?php
if ($_GET['action'] == "edit") {
    $user = isset($_GET['user']) ? $_GET['user'] : null;
    $limit = isset($_GET['limit']) ? $_GET['limit'] : null;
?>
	    <h4 l10n="admin.edit.title"></h4>
		<input class="form-control" type="text" name="e_login" value="<?php echo $user; ?>" l10n-placeholder="login.login" required autofocus <?php if(isset($user)){?> readonly <?php }?>></br>
		<input class="form-control" type="password" name="e_pass" value="" l10n-placeholder="login.pwd" autocomplete="new-password"></br>
		<input class="form-control" type="number" min="-1" max="100000" name="e_limit" l10n-placeholder="input.limits" value="<?php if($user != $admin) echo $limit; ?>" <?php if($user == $admin){?> readonly <?php }?>></br>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit" l10n="admin.edit.btn"></button>
<?php
}
else if ($_GET['action'] == "reg") {
?>
	    <h4 l10n="admin.reg.title"></h4>
		<input class="form-control" type="text" name="reg_login" value="" l10n-placeholder="login.login" required autofocus><br>
		<input class="form-control" type="password" name="reg_pass" value="" l10n-placeholder="login.pwd" autocomplete="new-password" required>
		<div style="padding:15px 0"><label style="font-size:13px;font-family:'Open Sans'"><input type="checkbox" name="reg_legacy"><span l10n="admin.reg.obd"></span></label></div>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit" l10n="admin.reg.btn"></button>
<?php
}
else if ($_GET['action'] == "del") {
?>
	    <h4 l10n="admin.del.title"></h4>
		<input class="form-control" type="text" name="del_login" value="" l10n-placeholder="login.login" required autofocus><br>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit" l10n="admin.del.btn"></button>
<?php
}
else if ($_GET['action'] == "trunc") {
?>
	    <h4 l10n="admin.trunc.title"></h4>
		<input class="form-control" type="text" name="trunc_login" value="" l10n-placeholder="login.login" required autofocus></br>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit" l10n="admin.trunc.btn"></button>
<?php
}
?>

 </form>
</div>

<script>
"use strict";
function submitForm(el) {
  let xhr = new XMLHttpRequest();
  xhr.onload = function(){ xhrResponse(xhr.responseText); }
  xhr.open(el.method, el.getAttribute("action"));
  xhr.send(new FormData(el));
  return false;
}

function xhrResponse(text) {
 let dialogOpt = {
    title: localization.key['dialog.result'],
    message : text,
    btnClassSuccessText: "OK",
    btnClassFail: "hidden",
 };
 redDialog.make(dialogOpt);
}
</script>
</body>
</html>

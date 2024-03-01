<?php
require_once ('creds.php');
require_once ('auth_functions.php');
require_once ('db.php');

if (!isset($_SESSION['admin'])) {
    header('HTTP/1.0 403 Forbidden');
    header("Location: /");
    die;
}

if (!isset($_GET['action'])) {
 header("Location: /");
 die;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <?php include("head.php");?>
    <link rel="stylesheet" href="static/css/admin.css">
    </head>
    <body>
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse" style="position: relative">
            <div class="container">
              <div id="theme-switch"></div>
                <div class="navbar-header">
		    <a class="navbar-brand" href="/"><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
                </div>
            </div>
        </div>
        <div class="login">
	<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
<?php
if ($_GET['action'] == "edit") {
?>
	    <h4>Edit user</h4>
		<input class="form-control" type="text" name="e_login" value="" placeholder="(Username)"></br>
		<input class="form-control" type="password" name="e_pass" value="" placeholder="(New password)" autocomplete="new-password"></br>
		<input class="form-control" type="number" min="-1" max="100000" name="e_limit" placeholder="(Limits in mb)"></br>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit">Edit</button>
<?php
}
else if ($_GET['action'] == "reg") {
?>
	    <h4>Register user</h4>
		<input class="form-control" type="text" name="reg_login" value="" placeholder="(Username)"><br>
		<input class="form-control" type="password" name="reg_pass" value="" placeholder="(Password)" autocomplete="new-password">
		<div style="padding:15px 0"><label style="font-size:13px;font-family:'Open Sans'"><input type="checkbox" name="reg_legacy"> Generic OBD device</label></div>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit">Register</button>
<?php
}
else if ($_GET['action'] == "del") {
?>
	    <h4>Delete user</h4>
		<input class="form-control" type="text" name="del_login" value="" placeholder="(Username)"><br>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit">Delete</button>
<?php
}
else if ($_GET['action'] == "trunc") {
?>
	    <h4>Truncate user database</h4>
		<input class="form-control" type="text" name="trunc_login" value="" placeholder="(Username)"></br>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit">Truncate</button>
<?php
}
?>

 </form>
</div>

<script>
"use strict";
function submitForm(el) {
  var xhr = new XMLHttpRequest();
  xhr.onload = function(){ xhrResponse(xhr.responseText); }
  xhr.open(el.method, el.getAttribute("action"));
  xhr.send(new FormData(el));
  return false;
}

function xhrResponse(text) {
 var dialogOpt = {
    title: "Result",
    message : text,
    btnClassSuccessText: "OK",
    btnClassFail: "hidden",
 };
 redDialog.make(dialogOpt);
}
</script>
</body>
</html>

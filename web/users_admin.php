<?php
require_once 'creds.php';
require_once 'auth_functions.php';
require_once 'db.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    header("Location: .");
    die;
}

if (!isset($_GET['action'])) {
 header("Location: .");
 die;
}
include 'head.php';
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
		<input class="form-control" type="text" name="e_login" value="<?php echo htmlspecialchars($user); ?>" maxlength="32" l10n-placeholder="login.login" required autofocus <?php if(isset($user)){?> readonly <?php }?>></br>
		<div class="password-toggle">
		    <input class="form-control password-input" type="password" name="e_pass" value="" maxlength="64" l10n-placeholder="login.pwd" autocomplete="new-password">
		    <button type="button" class="password-toggle__btn">
			<span class="password-toggle__icon"></span>
		    </button>
		</div><br><br>
		<input class="form-control" type="number" min="-1" max="100000" name="e_limit" l10n-placeholder="input.limits" value="<?php if($user != $admin) echo htmlspecialchars($limit); ?>" <?php if($user == $admin){?> readonly <?php }?>></br>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit" l10n="admin.edit.btn"></button>
<?php
}
elseif ($_GET['action'] == "reg") {
?>
	    <h4 l10n="admin.reg.title"></h4>
		<input class="form-control" type="text" name="reg_login" value="" maxlength="32" l10n-placeholder="login.login" required autofocus><br>
		<div class="password-toggle">
		    <input class="form-control password-input" type="password" name="reg_pass" value="" maxlength="64" l10n-placeholder="login.pwd" autocomplete="new-password" required>
		    <button type="button" class="password-toggle__btn">
			<span class="password-toggle__icon"></span>
		    </button>
		</div>
		<div style="padding:15px 0"><label style="font-size:13px;font-family:'Open Sans'"><input type="checkbox" style="margin-bottom:3px" name="reg_legacy"><span l10n="admin.reg.obd" style="margin-left:3px"></span></label></div>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit" l10n="admin.reg.btn"></button>
<?php
}
elseif ($_GET['action'] == "del") {
?>
	    <h4 l10n="admin.del.title"></h4>
		<input class="form-control" type="text" name="del_login" value="" maxlength="32" l10n-placeholder="login.login" required autofocus><br>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit" l10n="admin.del.btn"></button>
<?php
}
elseif ($_GET['action'] == "trunc") {
?>
	    <h4 l10n="admin.trunc.title"></h4>
		<input class="form-control" type="text" name="trunc_login" value="" maxlength="32" l10n-placeholder="login.login" required autofocus></br>
		<button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit" l10n="admin.trunc.btn"></button>
<?php
}
?>

 </form>
</div>

<script>
"use strict";
function submitForm(el) {
  const submitBtn = el.querySelector('button[type="submit"]');

  if (submitBtn.disabled) {
    return false;
  }

  submitBtn.disabled = true;

  fetch(el.getAttribute("action"), {
    method: el.method,
    body: new FormData(el)
  })
  .then(response => response.text())
  .then(responseText => {
    xhrResponse(responseText);
    setTimeout(() => {
      submitBtn.disabled = false;
    }, 1000);
  })
  .catch(error => {
    console.error('Error:', error);
    setTimeout(() => {
      submitBtn.disabled = false;
    }, 1000);
  });

  return false;
}
</script>
</body>
</html>

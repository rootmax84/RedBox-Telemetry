<?php
    require_once('db.php');
    require_once('db_limits.php');

    //Conversion settings
    $setqry = $db->execute_query("SELECT speed,temp,pressure FROM $db_users WHERE user=?", [$username])->fetch_row();
    $speed = $setqry[0];
    $temp = $setqry[1];
    $pressure = $setqry[2];

    //Telegram token/chatid
    $row = $db->execute_query("SELECT tg_token, tg_chatid FROM $db_users WHERE user=?", [$username])->fetch_assoc();
    $token = $row["tg_token"];
    $chatid = $row["tg_chatid"];

    $db->close();
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <?php include("head.php");?>
    </head>
    <body>
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
    <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
         <label id="storage-usage">Storage usage: <?php echo $db_used;?></label>
    <?php } ?>
          <div id="theme-switch"></div>
            <div class="container">
                <div class="navbar-header">
		 <a class="navbar-brand" href="/"><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
                </div>
              </div>
            </div>
    <div class="settings-container">
            <div class="settings-unit">
             <h4>Telegram notifications</h4>
             <h6 style="color:#777">Notification when starting a new session</h6>
		<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
		 <input class="form-control" type="text" name="tg_token"  placeholder="(Telegram bot token)" maxlength="64" autocomplete="new-password" value="<?php echo $token; ?>"><br>
		 <input class="form-control" type="text" name="tg_chatid" placeholder="(Telegram your chatid)" maxlength="64" autocomplete="new-password" value="<?php echo $chatid; ?>"><br>
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit">Save</button></div>
		</form>
        </div>
	<hr>
            <div class="settings-unit">
             <h4>Units conversion</h4>
             <h6 style="color:#777">Dynamic unit conversion settings</h6>
		<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
		 <label>Speed</label><select class="form-control" name="speed">
		    <option value="1"<?php if ($speed == "No conversion") echo ' selected'; ?>>No conversion</option>
		    <option value="2"<?php if ($speed == "km to miles") echo ' selected'; ?>>km to miles</option>
		    <option value="3"<?php if ($speed == "miles to km") echo ' selected'; ?>>miles to km</option>
		</select>
		 <label>Temperature</label><select class="form-control" name="temp">
		    <option value="1"<?php if ($temp == "No conversion") echo ' selected'; ?>>No conversion</option>
		    <option value="2"<?php if ($temp == "Celsius to Fahrenheit") echo ' selected'; ?>>Celsius to Fahrenheit</option>
		    <option value="3"<?php if ($temp == "Fahrenheit to Celsius") echo ' selected'; ?>>Fahrenheit to Celsius</option>
		</select>
		 <label>Pressure</label><select class="form-control" name="pressure">
		    <option value="1"<?php if ($pressure == "No conversion") echo ' selected'; ?>>No conversion</option>
		    <option value="2"<?php if ($pressure == "Psi to Bar") echo ' selected'; ?>>Psi to Bar</option>
		    <option value="3"<?php if ($pressure == "Bar to Psi") echo ' selected'; ?>>Bar to Psi</option>
		</select>
		 <br>
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit">Save</button></div>
		</form>
        </div>
	<hr>
            <div class="settings-unit">
             <h4>Change password</h4>
             <h6 style="color:#777">You can change your current password here</h6>
		<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
		 <input class="form-control" type="password" name="old_p"  placeholder="(Current password)" maxlength="64" autocomplete="new-password"><br>
		 <input class="form-control" type="password" name="new_p1" placeholder="(New password)" maxlength="64" autocomplete="new-password"><br>
		 <input class="form-control" type="password" name="new_p2" placeholder="(Repeat new password)" maxlength="64" autocomplete="new-password"><br>
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit">Save</button></div>
		</form>
        </div>
	<hr>
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
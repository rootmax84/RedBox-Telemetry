<?php
    require_once('db.php');
    require_once('db_limits.php');

    //Conversion and gap settings
    $setqry = $db->execute_query("SELECT speed,temp,pressure,boost,time,gap,stream_lock FROM $db_users WHERE user=?", [$username])->fetch_row();
    [$speed, $temp, $pressure, $boost, $time, $gap, $stream_lock] = $setqry;

    //Telegram token/chatid
    $row = $db->execute_query("SELECT tg_token, tg_chatid FROM $db_users WHERE user=?", [$username])->fetch_assoc();
    $token = $row["tg_token"];
    $chatid = $row["tg_chatid"];

    cache_flush();

    $db->close();

    include("head.php");
?>
    <body>
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
    <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
         <label id="storage-usage">Storage usage: <?php echo $db_used;?></label>
    <?php } ?>
          <div id="theme-switch"></div>
            <div class="container">
                <div class="navbar-header">
		 <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
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
             <h4>Units conversion and other</h4>
             <h6 style="color:#777">Dynamic unit conversion and other settings</h6>
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
		 <label>Pressure (oil, fuel, etc)</label><select class="form-control" name="pressure">
		    <option value="1"<?php if ($pressure == "No conversion") echo ' selected'; ?>>No conversion</option>
		    <option value="2"<?php if ($pressure == "Psi to Bar") echo ' selected'; ?>>Psi to Bar</option>
		    <option value="3"<?php if ($pressure == "Bar to Psi") echo ' selected'; ?>>Bar to Psi</option>
		</select>
		 <label>Boost</label><select class="form-control" name="boost">
		    <option value="1"<?php if ($boost == "No conversion") echo ' selected'; ?>>No conversion</option>
		    <option value="2"<?php if ($boost == "Psi to Bar") echo ' selected'; ?>>Psi to Bar</option>
		    <option value="3"<?php if ($boost == "Bar to Psi") echo ' selected'; ?>>Bar to Psi</option>
		</select>
		 <label>Time</label><select class="form-control" name="time">
		    <option value="1"<?php if ($time == "24") echo ' selected'; ?>>24 Hours</option>
		    <option value="2"<?php if ($time == "12") echo ' selected'; ?>>12 Hours</option>
		</select>
		 <label>Chart time gap remove if interval greater than</label><select class="form-control" name="gap">
		    <option value="5000"<?php if ($gap == "5000") echo ' selected'; ?>>5 sec</option>
		    <option value="10000"<?php if ($gap == "10000") echo ' selected'; ?>>10 sec</option>
		    <option value="20000"<?php if ($gap == "20000") echo ' selected'; ?>>20 sec</option>
		    <option value="30000"<?php if ($gap == "30000") echo ' selected'; ?>>30 sec</option>
		    <option value="60000"<?php if ($gap == "60000") echo ' selected'; ?>>60 sec</option>
		</select>
		 <label>Lock stream on current session</label><select class="form-control" name="stream_lock">
		    <option value="0"<?php if ($stream_lock == "0") echo ' selected'; ?>>No</option>
		    <option value="1"<?php if ($stream_lock == "1") echo ' selected'; ?>>Yes</option>
		</select>
		 <label>Chart fill</label><select class="form-control" id="chart-fill">
		    <option value="false">No</option>
		    <option value="true">Yes</option>
		</select>
		 <label>Chart steps</label><select class="form-control" id="chart-steps">
		    <option value="false">No</option>
		    <option value="true">Yes</option>
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
		 <input class="form-control" type="password" name="old_p"  placeholder="(Current password)" maxlength="64" autocomplete="new-password" required><br>
		 <input class="form-control" type="password" name="new_p1" placeholder="(New password)" maxlength="64" autocomplete="new-password" required><br>
		 <input class="form-control" type="password" name="new_p2" placeholder="(Repeat new password)" maxlength="64" autocomplete="new-password" required><br>
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit">Save</button></div>
		</form>
        </div>
</div>
<script>
"use strict";
function submitForm(el) {
  var xhr = new XMLHttpRequest();
  xhr.onload = function(){ xhrResponse(xhr.responseText); }
  xhr.open(el.method, el.getAttribute("action"));
  xhr.send(new FormData(el));
  localStorage.setItem(`${username}-chart_fill`, $("#chart-fill").val());
  localStorage.setItem(`${username}-chart_steps`, $("#chart-steps").val());
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

$(document).ready(function() {
    $("#chart-fill").val(localStorage.getItem(`${username}-chart_fill`) || "false");
    $("#chart-steps").val(localStorage.getItem(`${username}-chart_steps`) || "false");
});
</script>
 </body>
</html>
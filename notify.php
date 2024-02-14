<?php
    require_once('db.php');
    require_once('db_limits.php');

$row = $db->execute_query("SELECT tg_token, tg_chatid FROM $db_users WHERE user=?", [$username])->fetch_assoc();
$token = $row["tg_token"];
$chatid = $row["tg_chatid"];
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <?php include("head.php");?>
    </head>
    <body>
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse" style="position:relative">
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
            <div class="login" style="text-align:center; margin: 50px auto !important">
             <h4>Telegram notifications</h4>
             <h6 style="color:#777">Notification when starting a new session</h6>
		<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
		 <input class="form-control" type="text" name="tg_token"  placeholder="(Telegram bot token)" maxlength="64" autocomplete="new-password" value="<?php echo $token; ?>"><br>
		 <input class="form-control" type="text" name="tg_chatid" placeholder="(Telegram your chatid)" maxlength="64" autocomplete="new-password" value="<?php echo $chatid; ?>"><br>
		 <button class="btn btn-info btn-sm" style="width:100%; height:35px" type="submit">Save</button>
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
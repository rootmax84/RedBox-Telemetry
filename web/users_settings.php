<?php
    require_once 'db.php';
    require_once 'db_limits.php';

    //Conversion and gap settings etc
    $setqry = $db->execute_query("SELECT tg_token,tg_chatid,speed,temp,pressure,boost,time,gap,stream_lock,sessions_filter,api_gps,forward_url,forward_token FROM $db_users WHERE user=?", [$username])->fetch_row();
    [$token, $chatid, $speed, $temp, $pressure, $boost, $time, $gap, $stream_lock, $sessions_filter, $api_gps, $forward_url, $forward_token] = $setqry;

    $db->close();

    include 'head.php';
?>
    <body>
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
    <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
         <div class="new-session"><a href='.' l10n='sess.new'></a></div>
         <div class="storage-usage-img" onclick></div>
         <label id="storage-usage" l10n='stor.usage'><span><?php echo $db_used;?></span></label>
    <?php } ?>
            <div class="container">
              <div id="theme-switch"></div>
                <div class="navbar-header">
		 <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
                </div>
              </div>
            </div>
    <div class="settings-container">
            <div class="settings-unit">
             <h4 l10n="user.tg.title"></h4>
             <h6 style="color:#777" l10n="user.tg.label"></h6>
		<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
		<div class="settings--cell">
		 <input class="form-control" type="text" name="tg_token"  l10n-placeholder="user.tg.token" maxlength="64" autocomplete="new-password" value="<?php echo $token; ?>"><br>
		 <input class="form-control" type="number" name="tg_chatid" l10n-placeholder="user.tg.id" autocomplete="new-password" value="<?php echo $chatid; ?>">
		</div>
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit" l10n="btn.save"></button></div>
		</form>
        </div>
	<hr>
            <div class="settings-unit">
             <h4 l10n="user.set.title"></h4>
             <h6 style="color:#777" l10n="user.set.label"></h6>
		<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
		<div class="settings--cell">
		 <label l10n="user.set.spd"></label><select class="form-control" name="speed">
		    <option value="1"<?php if ($speed == "No conversion") echo ' selected'; ?> l10n="user.conv.no"></option>
		    <option value="2"<?php if ($speed == "km to miles") echo ' selected'; ?> l10n="user.conv.km.to.miles"></option>
		    <option value="3"<?php if ($speed == "miles to km") echo ' selected'; ?> l10n="user.conv.miles.to.km"></option>
		</select>
		</div>
		<div class="settings--cell">
		 <label l10n="user.set.temp"></label><select class="form-control" name="temp">
		    <option value="1"<?php if ($temp == "No conversion") echo ' selected'; ?> l10n="user.conv.no"></option>
		    <option value="2"<?php if ($temp == "Celsius to Fahrenheit") echo ' selected'; ?> l10n="user.conv.c.to.f"></option>
		    <option value="3"<?php if ($temp == "Fahrenheit to Celsius") echo ' selected'; ?> l10n="user.conv.f.to.c"></option>
		</select>
		</div>
		<div class="settings--cell">
		 <label l10n="user.set.press"></label><select class="form-control" name="pressure">
		    <option value="1"<?php if ($pressure == "No conversion") echo ' selected'; ?> l10n="user.conv.no"></option>
		    <option value="2"<?php if ($pressure == "Psi to Bar") echo ' selected'; ?> l10n="user.conv.psi.to.bar"></option>
		    <option value="3"<?php if ($pressure == "Bar to Psi") echo ' selected'; ?> l10n="user.conv.bar.to.psi"></option>
		</select>
		</div>
		<div class="settings--cell">
		 <label l10n="user.set.boost"></label><select class="form-control" name="boost">
		    <option value="1"<?php if ($boost == "No conversion") echo ' selected'; ?> l10n="user.conv.no"></option>
		    <option value="2"<?php if ($boost == "Psi to Bar") echo ' selected'; ?> l10n="user.conv.psi.to.bar"></option>
		    <option value="3"<?php if ($boost == "Bar to Psi") echo ' selected'; ?> l10n="user.conv.bar.to.psi"></option>
		</select>
		</div>
		<div class="settings--cell">
		 <label l10n="user.set.time"></label><select class="form-control" name="time">
		    <option value="1"<?php if ($time == "24") echo ' selected'; ?> l10n="user.conv.time.24"></option>
		    <option value="2"<?php if ($time == "12") echo ' selected'; ?> l10n="user.conv.time.12"></option>
		</select>
		</div>
		<div class="settings--cell">
		 <label l10n="user.set.gap"></label><select class="form-control" name="gap">
		    <option value="5000"<?php if ($gap == "5000") echo ' selected'; ?> l10n="user.conv.gap.5"></option>
		    <option value="10000"<?php if ($gap == "10000") echo ' selected'; ?> l10n="user.conv.gap.10"></option>
		    <option value="20000"<?php if ($gap == "20000") echo ' selected'; ?> l10n="user.conv.gap.20"></option>
		    <option value="30000"<?php if ($gap == "30000") echo ' selected'; ?> l10n="user.conv.gap.30"></option>
		    <option value="60000"<?php if ($gap == "60000") echo ' selected'; ?> l10n="user.conv.gap.60"></option>
		</select>
		</div>
		<div class="settings--cell">
		 <label l10n="user.set.lock"></label><select class="form-control" name="stream_lock">
		    <option value="0"<?php if ($stream_lock == "0") echo ' selected'; ?> l10n="btn.no"></option>
		    <option value="1"<?php if ($stream_lock == "1") echo ' selected'; ?> l10n="btn.yes"></option>
		</select>
		</div>
		<div class="settings--cell">
		 <label l10n="sessions.filter"></label><select class="form-control" name="sessions_filter">
		    <option value="1"<?php if ($sessions_filter == "1") echo ' selected'; ?> l10n="btn.no"></option>
		    <option value="2"<?php if ($sessions_filter == "2") echo ' selected'; ?>>75%</option>
		    <option value="3"<?php if ($sessions_filter == "3") echo ' selected'; ?>>50%</option>
		    <option value="4"<?php if ($sessions_filter == "4") echo ' selected'; ?>>33%</option>
		    <option value="5"<?php if ($sessions_filter == "5") echo ' selected'; ?>>25%</option>
		</select>
		</div>
		<div class="settings--cell">
		 <label l10n="api.gps"></label><select class="form-control" name="api_gps">
		    <option value="0"<?php if ($api_gps == "0") echo ' selected'; ?> l10n="btn.no"></option>
		    <option value="1"<?php if ($api_gps == "1") echo ' selected'; ?> l10n="btn.yes"></option>
		</select>
		</div>
		<div class="settings--cell">
		 <label l10n="user.set.lang"></label><select class="form-control" id="lang">
		    <option value="en">English</option>
		    <option value="ru">Русский</option>
		    <option value="es">Español</option>
		    <option value="de">Deutsch</option>
		</select>
		</div>
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit" l10n="btn.save"></button></div>
		</form>
        </div>
	<hr>
            <div class="settings-unit">
             <h4 l10n="user.url.title"></h4>
             <h6 style="color:#777" l10n="user.url.label"></h6>
		<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
		<div class="settings--cell">
		 <input class="form-control" type="text" name="forward_url"  l10n-placeholder="user.url.placeholder" maxlength="2083" value="<?php echo $forward_url; ?>"><br>
		 <input class="form-control" type="text" name="forward_token"  l10n-placeholder="user.url.token.placeholder" maxlength="128" value="<?php echo $forward_token; ?>">
		</div>
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit" l10n="btn.save"></button></div>
		</form>
        </div>
	<hr>
            <div class="settings-unit">
             <h4 l10n="share.sec.title"></h4>
             <h6 style="color:#777" l10n="share.sec.label"></h6>
		<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
		 <input class="form-control" type="text" name="share_secret" value="1" style="display:none">
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit" l10n="btn.renew"></button></div>
		</form>
        </div>
	<hr>
            <div class="settings-unit">
             <h4 l10n="user.pwd.title"></h4>
             <h6 style="color:#777" l10n="user.pwd.label"></h6>
		<form method="POST" action="users_handler.php" onsubmit="return submitForm(this);">
		<div class="settings--cell">
		 <input class="form-control" type="password" name="old_p"  l10n-placeholder="user.pwd.curr" maxlength="64" autocomplete="new-password" required><br>
		 <input class="form-control" type="password" name="new_p1" l10n-placeholder="user.pwd.new" maxlength="64" autocomplete="new-password" required><br>
		 <input class="form-control" type="password" name="new_p2" l10n-placeholder="user.pwd.repeat" maxlength="64" autocomplete="new-password" required>
		</div>
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit" l10n="btn.save"></button></div>
		</form>
        </div>
</div>
<script>
"use strict";
function submitForm(el) {
  const submitBtn = el.querySelector('button[type="submit"]');

  if (submitBtn.disabled) {
    return false;
  }

  submitBtn.disabled = true;

  lang = $("#lang").val();
  fetch(`translations.php?lang=${lang}`)
    .then(() => localization.setLang(lang))
    .then(() => {
      return fetch(el.getAttribute("action"), {
        method: el.method,
        body: new FormData(el),
      });
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
    })
    .finally(() => {
        createChoices();
    });


  return false;
}

function createChoices() {
    document.querySelectorAll('select').forEach(select => {
        if (select._choices) {
            select._choices.destroy();
            select._choices = null;
        }
    });

    document.querySelectorAll('select').forEach(select => {
        select._choices = new Choices(select, {
            itemSelectText: null,
            shouldSort: false,
            searchEnabled: false,
            classNames: {
                containerInner: ['choices__inner', 'choices__settings__text'],
            },
        });
    });
}

$(document).ready(function() {
    $("#lang").val(lang) || "en";
    createChoices();
});
</script>
 </body>
</html>
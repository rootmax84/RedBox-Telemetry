<?php
require_once('db.php');
require_once('db_limits.php');
require_once('plot.php');
require_once('timezone.php');
include_once('translations.php');
$lang = $_COOKIE['lang'];

// Capture the session ID if one has been chosen already
$session_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT) ?: null;

$page = $_GET["page"] ?? 1;
$filteryear = $_GET["year"] ?? "";
$filtermonth = $_GET["month"] ?? "";
$filterprofile = $_GET["profile"] ?? "";

$var1 = "";

// From the output of the get_sessions.php file, populate the page with info from
//  the current session. Using successful existence of a session as a trigger, 
//  populate some other variables as well.
if (isset($sids[0])) {
    if (!isset($session_id)) {
        $session_id = $sids[0];
    }

    if ($session_id == ''){
        header('Location: .');
    }

    //For the merge function, we need to find out, what would be the next session
    $idx = array_search($session_id, $sids);
    $session_id_next = "";
    if ($idx > 0) {
        $session_id_next = $sids[$idx - 1];
    }

    $cached_timestamp = null;
    $current_timestamp = getLastUpdateTimestamp($db, $session_id, $db_sessions_table);

    // Years
    $years_cache_key = "years_list_" . $username;
    $yeararray = false;

    if ($memcached_connected) {
        try {
            $y_cached_data = $memcached->get($years_cache_key);
            if ($y_cached_data !== false) {
                list($yeararray, $cached_timestamp) = $y_cached_data;
            }
        } catch (Exception $e) {
            $yeararray = false;
        }
    }

    if ($yeararray === false || $cached_timestamp !== $current_timestamp) {
        $yearquery = $db->query("SELECT YEAR(FROM_UNIXTIME(session/1000)) as 'year'
            FROM $db_sessions_table WHERE session <> ''
            GROUP BY YEAR(FROM_UNIXTIME(session/1000)) 
            ORDER BY YEAR(FROM_UNIXTIME(session/1000)) DESC");
        $yeararray = [];
        while($row = $yearquery->fetch_assoc()) {
            $yeararray[] = $row['year'];
        }
        if ($memcached_connected) {
            try {
                $memcached->set($years_cache_key, [$yeararray, $current_timestamp], 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    // Profiles
    $profiles_cache_key = "profiles_list_" . $username;
    $profilearray = false;

    if ($memcached_connected) {
        try {
            $p_cached_data = $memcached->get($profiles_cache_key);
            if ($p_cached_data !== false) {
                list($profilearray, $cached_timestamp) = $p_cached_data;
            }
        } catch (Exception $e) {
            $profilearray = false;
        }
    }

    if ($profilearray === false || $cached_timestamp !== $current_timestamp) {
        $profilequery = $db->query("SELECT distinct profileName FROM $db_sessions_table ORDER BY profileName asc");
        $profilearray = [];
        while($row = $profilequery->fetch_assoc()) {
            $profilearray[] = $row['profileName'] === 'Not Specified' ? $translations[$lang]['profile.ns'] : $row['profileName'];
        }
        if ($memcached_connected) {
            try {
                $memcached->set($profiles_cache_key, [$profilearray, $current_timestamp], 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    // GPS data
    $gps_cache_key = "gps_data_" . $username . "_" . $session_id;
    $gps_data = false;

    if ($memcached_connected) {
        try {
            $g_cached_data = $memcached->get($gps_cache_key);
            if ($g_cached_data !== false) {
                list($gps_data, $cached_timestamp) = $g_cached_data;
            }
        } catch (Exception $e) {
            $gps_data = false;
        }
    }

    if ($gps_data === false || $cached_timestamp !== $current_timestamp) {
        $gpsQuery = getFilteredGpsQuery($db_table, $_SESSION['sessions_filter']);
        $gps_time_data = $db->execute_query($gpsQuery, [$session_id]);
        $geolocs = [];
        $timearray = [];
        $i = 0;
        while($row = $gps_time_data->fetch_row()) {
            if (($row[0] != 0) && ($row[1] != 0)) {
                $geolocs[] = ["lat" => $row[0], "lon" => $row[1]];
            }
            $timearray[$i] = $row[2];
            $i++;
        }
        $gps_data = ['geolocs' => $geolocs, 'timearray' => $timearray];
        if ($memcached_connected) {
            try {
                $memcached->set($gps_cache_key, [$gps_data, $current_timestamp], 1800);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    $geolocs = $gps_data['geolocs'];
    $timearray = $gps_data['timearray'];

    $itime = implode(",", $timearray);

    // Create array of Latitude/Longitude strings in leafletjs JavaScript format
    $mapdata = [];
    foreach($geolocs as $d) {
        $mapdata[] = "[".sprintf("%.14f",$d['lat']).",".sprintf("%.14f",$d['lon'])."]";
    }
    $imapdata = implode(",", $mapdata);

    // stream_lock
    $stream_lock_cache_key = "stream_lock_" . $username;
    $stream_lock = false;

    if ($memcached_connected) {
        try {
            $s_cached_data = $memcached->get($stream_lock_cache_key);
            if ($s_cached_data !== false) {
                list($stream_lock, $cached_timestamp) = $s_cached_data;
            }
        } catch (Exception $e) {
            $stream_lock = false;
        }
    }

    if ($stream_lock === false || $cached_timestamp !== $current_timestamp) {
        $stream_lock = $db->execute_query("SELECT stream_lock FROM $db_users WHERE user=?", [$username])->fetch_row()[0];
        if ($memcached_connected) {
            try {
                $memcached->set($stream_lock_cache_key, [$stream_lock, $current_timestamp], 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    // id
    $session_id_cache_key = "session_id_" . $session_id;
    $id = false;

    if ($memcached_connected) {
        try {
            $i_cached_data = $memcached->get($session_id_cache_key);
            if ($i_cached_data !== false) {
                list($id, $cached_timestamp) = $i_cached_data;
            }
        } catch (Exception $e) {
            $id = false;
        }
    }

    if ($id === false || $cached_timestamp !== $current_timestamp) {
        $id = $db->execute_query("SELECT id FROM $db_sessions_table WHERE session=?", [$session_id])->fetch_row()[0];
        if ($memcached_connected) {
            try {
                $memcached->set($session_id_cache_key, [$id, $current_timestamp], 3600);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    $db->close();
}
 include("head.php");
?>
    <body>
    <!-- Flot Local Javascript files -->
    <script src="<?php echo version_url('static/js/jquery.flot.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.axislabels.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.hiddengraphs.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.multihighlight-delta.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.selection.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.time.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.resize.min.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/Control.FullScreen.js'); ?>"></script>
    <!-- Configure Jquery Flot graph and plot code -->
    <script>
      $(document).ready(function(){
        $(".copyright").html(`&copy; 2019-${(new Date).getFullYear()} RedBox Automotive`);

        if (!document.getElementById('plot_data')) {
            $(".share-img").css("display", "none");
            return;
        }

        let plotData = $('#plot_data');
        let lastValue = plotData.val() || [];

        function handleChange() {
            const newValue = plotData.val() || [];
            if (JSON.stringify(newValue) !== JSON.stringify(lastValue)) {
                lastValue = newValue;
                updCharts();
            }
        }

        const observer = new MutationObserver((mutations) => {
            if (!lastValue.length && $('#placeholder')[0] != undefined) {
                updCharts();
            }
        });

        const targetNode = $('#right-container')[0];

        if (targetNode) {
            observer.observe(targetNode, {childList: true, subtree: true});
        }

        plotData.on('change', handleChange);
        plotData.chosen();
        updCharts();
        if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
      });
    </script>
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
<?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
    <div class="storage-usage-img"></div>
    <label id="storage-usage" l10n='stor.usage'><span><?php echo $db_used;?></span></label>
<?php } ?>
<?php if (isset($_SESSION['share_key'])) {?>
        <div class="share-img" onClick="shareSession()" <?php if ($limit < 0) { ?> style="right:40px" <?php } ?>></div>
<?php } ?>
      <div class="container">
       <div id="theme-switch"></div>
	<div class="navbar-header">
	    <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
	</div>
      </div>
    </div>
    <div id="right-container" class="col-md-auto col-xs-12">
<?php if (!isset($_SESSION['admin']) && isset($session_id) && !empty($session_id)) {?>
	<h4 l10n="sel.sess">...</h4>
	<div class="row center-block" style="padding-bottom:4px;">
	  <!-- Filter the session list by year and month -->
	  <h5 l10n="filter.sess"></h5>
	  <form method="post" class="form-horizontal" action="url.php?id=<?php echo $session_id; ?>">
	    <table style="width:100%">
	      <tr>
		<!-- Profile Filter -->
		<td style="width:22%">
		  <select id="selprofile" name="selprofile" class="form-control chosen-select" data-placeholder="Select Profile" onchange="$('#wait_layout').show();this.form.submit()">
		    <option value="" disabled selected l10n="sel.profile"></option>
		    <option style="text-align:center" value="ALL"<?php if ($filterprofile == "ALL") echo ' selected'; ?> l10n="profile.any"></option>
<?php $i = 0; ?>
<?php while(isset($profilearray[$i])) { ?>
		    <option value="<?php echo $profilearray[$i]; ?>"<?php if ($filterprofile == $profilearray[$i]) echo ' selected'; ?>><?php echo $profilearray[$i]; ?></option>
<?php   $i = $i + 1; ?>
<?php } ?>
		  </select>
		</td>
		<td style="width:2%"></td>
		<!-- Year Filter -->
		<td style="width:22%">
		  <select id="selyear" name="selyear" class="form-control chosen-select" data-placeholder="Select Year" onchange="$('#wait_layout').show();this.form.submit()">
		    <option value="" disabled selected l10n="sel.year"></option>
		    <option style="text-align:center" value="ALL"<?php if ($filteryear == "ALL") echo ' selected'; ?> l10n="year.any"></option>
<?php $i = 0; ?>
<?php while(isset($yeararray[$i])) { ?>
		    <option value="<?php echo $yeararray[$i]; ?>"<?php if ($filteryear == $yeararray[$i]) echo ' selected'; ?>><?php echo $yeararray[$i]; ?></option>
<?php   $i = $i + 1; ?>
<?php } ?>
		  </select>
		</td>
		<td style="width:2%"></td>
		<!-- Month Filter -->
		<td style="width:22%">
		  <select id="selmonth" name="selmonth" class="form-control chosen-select" data-placeholder="Select Month" onchange="$('#wait_layout').show();this.form.submit()">
		    <option value="" disabled selected l10n="sel.month"></option>
		    <option style="text-align:center" value="ALL"<?php if ($filtermonth == "ALL") echo ' selected'; ?> l10n="month.any"></option>
		    <option value="January"<?php if ($filtermonth == "January") echo ' selected'; ?> l10n="month.jan"></option>
		    <option value="February"<?php if ($filtermonth == "February") echo ' selected'; ?> l10n="month.feb"></option>
		    <option value="March"<?php if ($filtermonth == "March") echo ' selected'; ?> l10n="month.mar"></option>
		    <option value="April"<?php if ($filtermonth == "April") echo ' selected'; ?> l10n="month.apr"></option>
		    <option value="May"<?php if ($filtermonth == "May") echo ' selected'; ?> l10n="month.may"></option>
		    <option value="June"<?php if ($filtermonth == "June") echo ' selected'; ?> l10n="month.jun"></option>
		    <option value="July"<?php if ($filtermonth == "July") echo ' selected'; ?> l10n="month.jul"></option>
		    <option value="August"<?php if ($filtermonth == "August") echo ' selected'; ?> l10n="month.aug"></option>
		    <option value="September"<?php if ($filtermonth == "September") echo ' selected'; ?> l10n="month.sep"></option>
		    <option value="October"<?php if ($filtermonth == "October") echo ' selected'; ?> l10n="month.oct"></option>
		    <option value="November"<?php if ($filtermonth == "November") echo ' selected'; ?> l10n="month.nov"></option>
		    <option value="December"<?php if ($filtermonth == "December") echo ' selected'; ?> l10n="month.dec"></option>
		  </select>
		</td>
	      </tr>
	    </table>
	  </form><br>
	  <!-- Session Select Drop-Down List -->
	<table style="width:100%">
	  <form method="post" class="form-horizontal" action="url.php">
	   <tr>
	    <td>
	     <select id="seshidtag" name="seshidtag" class="form-control chosen-select" onchange="$('#wait_layout').show();this.form.submit()" data-placeholder="Select Session...">
<?php foreach ($seshdates as $dateid => $datestr) { ?>
	      <option value="<?php echo $dateid; ?>"<?php if ($dateid == $session_id) echo ' selected'; ?>><?php echo $datestr; echo $seshprofile[$dateid]; if ($show_session_length) {echo $seshsizes[$dateid];} {echo $seship[$dateid];} ?><?php if ($dateid == $session_id) echo $translations[$lang]['get.sess.curr']; ?></option>
<?php } ?>
	    </select>
<?php   if ( $filterprofile <> "" ) { ?>
	    <input type="hidden" name="selprofile" value="<?php echo $filterprofile; ?>">
<?php   } ?>
<?php   if ( $filteryear <> "" ) { ?>
	    <input type="hidden" name="selyear" value="<?php echo $filteryear; ?>">
<?php   } ?>
<?php   if ( $filtermonth <> "" ) { ?>
	    <input type="hidden" name="selmonth" value="<?php echo $filtermonth; ?>">
<?php   } ?>
	    <noscript><input type="submit" class="input-sm"></noscript>
	  </form>
	 </td>
      </tr>
    </table>
</div>

<!-- Variable Select Block -->
	<h4 l10n="sel.var"></h4>
	  <div class="row center-block" style="padding-top:3px;">
	      <select data-placeholder="Choose data..." multiple class="chosen-select" size="<?php echo $numcols; ?>" style="width:100%;" id="plot_data" name="plotdata[]">
<?php   foreach ($coldata as $xcol) { ?>
		<option value="<?php echo $xcol['colname']; ?>" <?php $i = 1; while ( isset(${'var' . $i}) ) { if ( ${'var' . $i} == $xcol['colname'] || $xcol['colfavorite'] == 1 ) { echo " selected"; } $i = $i + 1; } ?>><?php echo $xcol['colcomment']; ?></option>
<?php   } ?>
	    </select>
<?php   if ( $filterprofile <> "" ) { ?>
	    <input type="hidden" name="selprofile" value="<?php echo $filterprofile; ?>">
<?php   } ?>
<?php   if ( $filteryear <> "" ) { ?>
	    <input type="hidden" name="selyear" value="<?php echo $filteryear; ?>">
<?php   } ?>
<?php   if ( $filtermonth <> "" ) { ?>
	    <input type="hidden" name="selmonth" value="<?php echo $filtermonth; ?>">
<?php   } ?>
         <div id="chart-load"></div>
	</div>

<div <?php if($imapdata) { ?> class="pure-g" <?php } ?>>
  <div <?php if($imapdata) { ?> class="pure-u-md-1-2" <?php } ?>>
    <!-- Chart Block -->
    <div id="update-plot">
        <?php if($imapdata) { ?> <h4 class="wide-h" l10n="chart"></h4>
        <?php } else { ?> <h4 l10n="chart"><span class="nogps" l10n="nogps"></span></h4> <?php } ?>
    </div>
    <div id="Chart-Container" class="row center-block" style="z-index:1;position:relative;">
    <?php   if ( $var1 <> "" ) { ?>
    <div class="demo-container">
    <div id="placeholder" class="demo-placeholder"></div>
    </div>
    <?php   } else { ?>
    <div style="display:flex; justify-content:center;">
    <h5><span class="label label-warning">. . .</span></h5>
    </div>
    <?php   } ?>
    </div>
  </div>
<?php if ($imapdata) { ?>
 <div class="pure-u-md-1-2">
    <!-- MAP -->
    <h4 class="wide-h" l10n="tracking"></h4>
    <div id="map-div"><div class="row center-block map-container" id="map"></div></div>
  </div>
<?php } ?>
</div>
<br>

<!-- slider -->
<script>
jsTimeMap = [<?php echo $itime; ?>].reverse(); //Session time array, reversed for silder
initSlider(jsTimeMap,jsTimeMap[0],jsTimeMap.at(-1));
</script>
<span class="h4" l10n="trim.sess"></span>
<input type="text" id="slider-time" readonly style="border:0; font-family:monospace; width:300px;" disabled>
<div id="slider-range11"></div>
<br>

<!-- Data Summary Block -->
	<h4 l10n="summary"></h4>
	<div id="Summary-Container" class="row center-block" style="user-select:text;">
	  <div style="display:flex; justify-content:center;">
	    <h5><span class="label label-warning">. . .</span></h5>
	  </div>
	</div><br>

	<div class="row center-block" style="padding-bottom:18px;">

<!--Live DATA -->
<p class="divided" onclick="dataToggle()">
 <span class="tlue" l10n="stream"></span>
 <span class="divider"></span>
 <span class="toggle" id="data_toggle" l10n="expand"></span>
</p>
<div id="data" style="display:none">
	    <table class="table live-data" style="width:410px;font-size:0.875em;margin:0 auto;">
	      <thead>
		<tr>
		  <th l10n="stream.name"></th>
		  <th l10n="stream.val"></th>
		  <th l10n="stream.unit"></th>
		</tr>
	      </thead>
	       <tbody id="stream"><tr><td colspan="3" style="text-align:center"><span class="label label-success" l10n="stream.fetch.label"></span></td></tr>
	      </tbody>
	    </table>
</div>
<br>

<!--Functions buttons -->
<p class="divided" onclick="funcToggle()">
 <span class="tlue" l10n="functions"></span>
 <span class="divider"></span>
 <span class="toggle" id="func_toggle" l10n="expand"></span>
</p>

<div id="func" style="display:none">
<div class="btn-group btn-group-justified">
    <a class="btn btn-default func-btn" onclick="delSession()" l10n="func.del"></a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" onclick="delSessions()" l10n="func.multi.del"></a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" onclick="mergeSessions()" l10n="func.merge"></a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" onclick="pidEdit()" l10n="func.pid"></a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" onclick="showToken()" l10n="func.token"></a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" onclick="usersSettings()" l10n="func.settings"></a>
   </div>
</div>
<br>

<!--Upload log -->
<p class="divided" onclick="logToggle()">
 <span class="tlue" l10n="import.data"></span>
 <span class="divider"></span>
 <span class="toggle" id="log_toggle" l10n="expand"></span>
</p>

<div id="log" style="display:none">
    <div style="display:flex; justify-content:center; margin-bottom:10px;">
	     <span class="label label-default" id="log-msg-def" l10n="import.label"></span>
	     <span class="label label-success" id="log-msg-ok"></span>
	     <span class="label label-danger" id="log-msg-err"></span>
    </div>
    <div style="display:flex; justify-content:center;">
	    <form method="POST" action="redlog.php" onsubmit="return submitLog(this);" style="display:contents">
	     <input class="btn btn-default" style="border-radius:5px" type="file" multiple name="file[]" id="logFile" onchange="checkLog();" accept=".txt">
	     <input class="btn btn-default upload-log-btn" id="log-upload-btn" value="" type="submit">
	    </form>
    </div>
    <ul id="log-list"></ul>
   </div>
<br>

<!-- Export Data Block -->
<p class="divided" onclick="expToggle()">
 <span class="tlue" l10n="export.data"></span>
 <span class="divider"></span>
 <span class="toggle" id="exp_toggle" l10n="expand"></span>
</p>
<div id="exp" style="display:none">
	  <div class="btn-group btn-group-justified func-btn">
	    <a class="btn btn-default func-btn" onclick="exportSession('CSV')">CSV</a>
	  </div>
	  <div class="btn-group btn-group-justified func-btn">
	    <a class="btn btn-default func-btn" onclick="exportSession('JSON')">JSON</a>
	  </div>
	  <div class="btn-group btn-group-justified func-btn">
	    <a class="btn btn-default func-btn" onclick="exportSession('KML')" <?php if (!$imapdata) { ?> disabled <?php } ?>>KML</a>
	  </div>
	  <div class="btn-group btn-group-justified func-btn">
	    <a class="btn btn-default func-btn" onclick="exportSession('RBX')" <?php if ($id != "RedManage") { ?> disabled <?php } ?>>RBX</a>
	  </div>

   </div>
<script>
function funcToggle() {
	if ($("#func").is(":hidden")) {
		$("#func").show();
		$("#func_toggle").html(localization.key['collapse']);
	} else {
		$("#func").hide();
		$("#func_toggle").html(localization.key['expand']);
	}
}

function expToggle() {
	if ($("#exp").is(":hidden")) {
		$("#exp").show();
		$("#exp_toggle").html(localization.key['collapse']);
	} else {
		$("#exp").hide();
		$("#exp_toggle").html(localization.key['expand']);
	}
}

function logToggle() {
	if ($("#log").is(":hidden")) {
		$("#log").show();
		$("#log_toggle").html(localization.key['collapse']);
	} else {
		$("#log").hide();
		$("#log_toggle").html(localization.key['expand']);
		msg_def.innerHTML = localization.key['import.label'];
		msg_ok.innerHTML = "";
		msg_err.innerHTML = "";
		document.getElementById('logFile').value = "";
		up_btn.hide();
		log_list.innerHTML = "";
	}
}

const noSleep = new NoSleep();
let streamBtn_svg = null
let stream = false;
let src = null;
function dataToggle() {
    updatePlot(function() {
	if ($("#data").is(":hidden")) {
		$("#data").show();
		$("#data_toggle").html(localization.key['collapse']);
		src = new EventSource("stream.php<?php echo $stream_lock > 0 ? '?id=' . $session_id : ''; ?>");
		src.onmessage = e => {$("#stream").html(e.data)};
		alarm.muted = false;
		noSleep.enable();
		stream = true;
		startPlotUpdates();
	} else {
		$("#data").hide();
		$("#data_toggle").html(localization.key['expand']);
		src.close();
		alarm.muted = true;
		noSleep.disable();
		stream = false;
		stopPlotUpdates();
	}
	if (streamBtn_svg !== null) streamBtn_svg.style.color = stream ? '#008000' : 'inherit';
    });
}
</script>
	</div>
    <div class="row center-block" style="padding-bottom:18px;text-align:center;">
<?php } ?>

<?php if(isset($_SESSION['admin'])) {?>
<div class="admin-card">
    <div>
    <h4 style="text-align:center" l10n="admin.page.title"></h4>
<hr>
<div class="users-list">
<table>
 <thead>
  <tr>
    <th></th>
    <th l10n="admin.table.login"></th>
    <th l10n="admin.table.limit"></th>
    <th l10n="admin.table.size"></th>
    <th l10n="admin.table.ll"></th>
    <th l10n="admin.table.lu"></th>
    <th></th>
  </tr>
 </thead>
<tbody>

<?php
$page_first_result = ($page-1) * $results_per_page;
$usrqry = $db->query("SELECT COUNT(*) FROM $db_users");
$number_of_result = $usrqry->fetch_row()[0];
$number_of_page = ceil ($number_of_result / $results_per_page);

$res = $db->query("SELECT TABLE_SCHEMA AS '$db_name', ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db_name'")->fetch_array();
$r = $db->query("SELECT user, s, last_attempt FROM $db_users ORDER BY id = (SELECT MIN(id) FROM $db_users) DESC, user ASC  LIMIT " . $page_first_result . "," . $results_per_page);
if ($r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        $username = htmlspecialchars($row["user"]);
        $isAdmin = ($username == $admin);
        $isDisabled = ($row["s"] == 0);
        $isUnlimited = ($row["s"] == -1);
        
        // DB size
        $dbSize = "-";
        if (!$isAdmin) {
            $db_sz = $db->query("SHOW TABLE STATUS LIKE '".$username.$db_log_prefix."'")->fetch_array();
            $dbSize = round(($db_sz[6] + $db_sz['Index_length']) / (1024 * 1024), 0);
        }
        
        // Last activity
        $lastActivity = "-";
        if (!$isAdmin) {
            $lastResult = $db->query("SELECT time FROM ".$username.$db_log_prefix." ORDER BY time DESC LIMIT 1")->fetch_array();
            if ($lastResult) {
                $seconds = intval($lastResult[0] / 1000);
                $timeFormat = $admin_timeformat_12 ? "Y-m-d h:i:sa" : "Y-m-d H:i:s";
                $lastActivity = date($timeFormat, $seconds);
            }
        }
        
        // Last in
        $lastAttempt = empty($row["last_attempt"]) ? "-" : 
            date($admin_timeformat_12 ? "Y-m-d h:i:sa" : "Y-m-d H:i:s", strtotime($row["last_attempt"]));

        // limit format
        $limit = "-";
        if (!$isAdmin) {
            $limit = $isUnlimited ? "∞" : $row["s"];
        }
        
        // Username style and text
        $usernameDisplay = $username;
        $usernameStyle = "";
        if ($isAdmin) {
            $usernameDisplay .= " (admin)";
        } else if ($isDisabled) {
            $usernameStyle = "text-decoration:line-through";
        }
        
        // Output table
        echo "<tr ondblclick='window.location=\"./users_admin.php?action=edit&user=" . urlencode($username) . "&limit=" . $row["s"] . "\";'>";
        echo "<td>" . $i++ . "</td>";
        echo "<td" . ($usernameStyle ? " style='$usernameStyle'" : "") . ">" . $usernameDisplay . "</td>";
        echo "<td>" . $limit . "</td>";
        echo "<td>" . $dbSize . "</td>";
        echo "<td>" . $lastActivity . "</td>";
        echo "<td>" . $lastAttempt . "</td>";
        echo "</tr>";
    }
}
?>
</tbody>
</table>
</div>
<p class="db-size">
    <?= "Memcached: " . ($memcached_connected ? $translations[$lang]['btn.yes'] : $translations[$lang]['btn.no']) . " | " . $translations[$lang]['admin.db'] . ": " . round($res[1]) . $translations[$lang]['admin.mb'] ?>
</p>
<hr>
<div class="pages" style="padding:0">
<?php //Pagination with page count limit
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$total_pages = $number_of_page;
$page_numbers_limit = 10;
$start = $current_page - floor($page_numbers_limit / 2);
$end = $current_page + floor($page_numbers_limit / 2);
if ($start < 1) {
    $start = 1;
    $end = min($page_numbers_limit, $total_pages);
}
if ($end > $total_pages) {
    $end = $total_pages;
    $start = max(1, $total_pages - $page_numbers_limit + 1);
}
if ($current_page > 1) {
    echo '<a class="pages" href="?page=1">&#171;</a> ';
}
if ($current_page > 1) {
    $previous_page = $current_page - 1;
    echo '<a class="pages" href="?page=' . $previous_page . '">&#60;</a> ';
}
for ($page = $start; $page <= $end; $page++) {
    if ($number_of_result < $results_per_page) break;
    if ($page == $current_page) {
        echo '<a class="current-page" href="?page=' . $page . '">' . $page . ' </a>';
    } else {
        echo '<a class="pages" href="?page=' . $page . '">' . $page . ' </a>';
    }
}
if ($current_page < $total_pages) {
    $next_page = $current_page + 1;
    echo ' <a class="pages" href="?page=' . $next_page . '">&#62;</a>';
}
if ($current_page < $total_pages) {
    echo ' <a class="pages" href="?page=' . $total_pages . '">&#187;</a>';
}
?>
</div>

<div class="admin-panel">
    <a class="btn btn-default btn-admin" href="./users_admin.php?action=reg" l10n="admin.page.btn.reg"></a>
    <a class="btn btn-default btn-admin" href="./users_admin.php?action=edit" l10n="admin.page.btn.edit"></a>
    <a class="btn btn-default btn-admin" href="./users_admin.php?action=del" l10n="admin.page.btn.del"></a>
</div>
<div class="admin-panel">
    <a class="btn btn-default btn-admin" href="./users_admin.php?action=trunc" l10n="admin.page.btn.trunc"></a>
    <a class="btn btn-default btn-admin" href="./adminer.php?server=<?php echo $db_host; ?>&username=<?php echo $db_user; ?>&db=<?php echo $db_name; ?>" target="_blank">Adminer</a>
    <a class="btn btn-default btn-admin" href="#" onclick="maintenance()" l10n="admin.page.btn.maintenance"></a>
</div>
    </div>
<script>initTableSorting(".users-list")</script>
<?php } else if (isset($session_id) && !empty($session_id)) { ?>
    <p class="copyright"></p>
</div>
<?php } else { ?>
<div class="login" style="text-align:center; width:fit-content; margin: 50px auto">
    <h4 l10n="nodata.show"></h4>
    <h6 l10n="data.upload.label"></h6>
<ul class="no-data-url-list">
    <li><a href="#" onclick="showToken()" l10n="nodata.token"></a></li>
    <li><a href="#" onclick="usersSettings()" l10n="nodata.settings"></a></li>
    <li><a href="#" onclick="pidEdit()" l10n="nodata.pid"></a></li>
</ul>
<div id="log">
    <div style="display:flex; justify-content:center; margin-bottom:10px;">
	     <span class="label label-default" id="log-msg-def" l10n="import.label" style="width:320px"></span>
	     <span class="label label-success" id="log-msg-ok"></span>
	     <span class="label label-danger" id="log-msg-err"></span>
    </div>
    <div style="display:flex; justify-content:center;">
	    <form method="POST" action="redlog.php" onsubmit="return submitLog(this);" style="display:contents">
	     <input class="btn btn-default" style="border-radius:5px;width:100%" type="file" multiple name="file[]" id="logFile" onchange="checkLog();" accept=".txt">
	     <input class="btn btn-default upload-log-btn" id="log-upload-btn" value="" type="submit">
	    </form>
    </div>
    <ul id="log-list"></ul>
   </div>
</div>
<div class="row center-block" style="transform:translateY(-30px);text-align:center">
    <p class="copyright"></p>
</div>
<script>
let src = new EventSource("stream.php");

src.onmessage = e => {
    if (e.data.length) {
        location.reload();
    }
};
</script>
<?php } ?>
      </div>
    </div>
<?php if(!isset($_SESSION['admin']) && isset($session_id) && !empty($session_id)) {?>

<script>
 const path = [<?php echo $imapdata; ?>]; //this would be a new variable containing speed data for each segment
 if (!path.length) {
    $('#map-div').hide();
 } else {
    window.MapData = {path};
    initMap = initMapLeaflet;
    jsCBinitMap = ()=>$(document).ready(initMap);
    jsCBinitMap();
 }
</script>

<?php } ?>

<!-- logs upload -->
<?php if(!isset($_SESSION['admin'])) {?>
<script>
const msg_def = document.getElementById('log-msg-def');
const msg_ok = document.getElementById('log-msg-ok');
const msg_err = document.getElementById('log-msg-err');
const up_btn = $('#log-upload-btn');
const log_list = document.getElementById('log-list');
const logInput = document.getElementById('logFile');

function submitLog(el) {
  up_btn.hide();
  msg_err.innerHTML = "";
  msg_def.innerHTML = "";

  let xhr = new XMLHttpRequest();
  xhr.onload = function() {
     msg_ok.innerHTML = xhr.responseText;
     if (xhr.status == 406) {
      msg_ok.innerHTML = "";
      msg_err.innerHTML = xhr.responseText;
    }
    logFile.removeAttribute("disabled");
  }
  xhr.upload.onprogress = p => { msg_ok.innerHTML = `${localization.key['import.upload']} ${Math.round((p.loaded / p.total) * 100)}%` }
  xhr.upload.onloadend = () => { msg_ok.innerHTML = localization.key['import.end'] }
  xhr.open(el.method, el.getAttribute("action"));
  xhr.send(new FormData(el));
  logFile.setAttribute("disabled", "");
  return false;
}

function checkLog() {
 msg_def.innerHTML = "";
 msg_err.innerHTML = "";
 msg_ok.innerHTML = "";
 log_list.innerHTML = "";
 const log_data = document.getElementById('logFile');
 let size = 0;
 let filesProcessed = 0;

 if (!log_data.files.length) {
    $("#log-list").css({"display":"none"});
    msg_def.innerHTML = localization.key['import.label'];
    up_btn.hide();
    return;
 } else {
    $("#log-list").css({"display":"grid"});
 }

 msg_def.innerHTML = localization.key['import.read'];

 for (let i = 0; i < log_data.files.length; i++) {
    size += log_data.files[i].size;
 }

 if (log_data.files.length > 10) {
    msg_def.innerHTML = "";
    msg_err.innerHTML = localization.key['import.warn.count'];
    up_btn.hide();
    return;
 }

 if (size > 52428800) {
    msg_def.innerHTML = "";
    msg_err.innerHTML = localization.key['import.warn.size'];
    up_btn.hide();
    return;
 }

 for (let i = 0; i < log_data.files.length; i++) {
    let reader = new FileReader();
    reader.readAsText(log_data.files[i], "UTF-8");

    reader.onload = (f) => {
        let logDate, dateDMY, dateTime, dateStr;
        try {
            logDate = new Date(parseInt(f.target.result.split("\n")[1].split(" ")[0]));
            if (isNaN(logDate) || logDate.getFullYear() < 2000) throw new Error('');
            dateDMY = `${logDate.getFullYear()}-${(logDate.getMonth() + 1)}-${logDate.getDate()}`;
            dateTime = $.cookie('timeformat') === '12'
              ? logDate.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true })
              : `${logDate.getHours()}:${('0' + logDate.getMinutes()).slice(-2)}`;
            dateStr = `${localization.key['import.date']} ${dateDMY} ${dateTime})`;
        } catch(e) {
            reader.abort();
            dateStr = localization.key['import.broken.el'];
            msg_def.innerHTML = "";
            msg_err.innerHTML = localization.key['import.broken.label'];
            msg_ok.innerHTML = "";
            up_btn.hide();
            log_list.innerHTML += `<li style='font-family:monospace'> ${log_data.files[i].name} ${dateStr}</li>`;
            return;
        }
        log_list.innerHTML += `<li style='font-family:monospace'> ${log_data.files[i].name} ${dateStr}</li>`;

        filesProcessed++;

        if (filesProcessed === log_data.files.length) {
            msg_def.innerHTML = "";
            msg_ok.innerHTML = localization.key['import.ready'];
            up_btn.show();
        }
    }

    reader.onerror = () => {
        msg_def.innerHTML = "";
        msg_err.innerHTML = localization.key['import.broken.label'];
        msg_ok.innerHTML = "";
        up_btn.hide();
    }
 }
}

function delSession() {
  $("#wait_layout").hide();
  const sessionId = "<?php echo $session_id; ?>";
  const sessionDate = "<?php echo isset($session_id) ? $seshdates[$session_id] : ''; ?>";
  if (!sessionId.length) return;

  // Format time based on cookie setting
  const formatTime = (timestamp) => {
    const date = new Date(timestamp);
    // Check if timeformat cookie is set to 12-hour format
    if ($.cookie('timeformat') == '12') {
      return date.toLocaleTimeString('en-US'); // 12-hour format with AM/PM
    } else {
      return date.toLocaleTimeString('ru-RU'); // 24-hour format
    }
  };

  let messageText = `${localization.key['dialog.del.session']} (${sessionDate})`;
  if (cutStart !== null && cutEnd !== null) {
    const startTime = formatTime(cutStart);
    const endTime = formatTime(cutEnd);
    messageText += ` <strong>${localization.key['dialog.del.range']}</strong> ${startTime} - ${endTime}`;
  }
  messageText += "?";

  let dialogOpt = {
    title: localization.key['dialog.confirm'],
    btnClassSuccessText: localization.key['btn.yes'],
    btnClassFailText: localization.key['btn.no'],
    btnClassFail: "btn btn-info btn-sm",
    message: messageText,
    onResolve: function() {
      $("#wait_layout").show();
      let url = `?deletesession=${sessionId}`;
      if (cutStart !== null && cutEnd !== null) {
        url += `&cutstart=${cutStart}&cutend=${cutEnd}`;
      }
      location.href = url;
    },
    onReject: function() { return; }
  };
  redDialog.make(dialogOpt);
}

function showToken() {
$("#wait_layout").show();
let xhr = new XMLHttpRequest();
 xhr.onreadystatechange = function() {
  if (this.readyState == 4 && this.status == 200) {
	$("#wait_layout").hide();
	let token = this.responseText;
	let dialogOpt = {
	    title : `${localization.key['dialog.token']} <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" style="float: right;"><path fill="currentColor" d="M7 14q-.825 0-1.412-.587T5 12t.588-1.412T7 10t1.413.588T9 12t-.587 1.413T7 14m0 4q-2.5 0-4.25-1.75T1 12t1.75-4.25T7 6q1.675 0 3.038.825T12.2 9H21l3 3l-4.5 4.5l-2-1.5l-2 1.5l-2.125-1.5H12.2q-.8 1.35-2.162 2.175T7 18m0-2q1.4 0 2.463-.85T10.875 13H14l1.45 1.025L17.5 12.5l1.775 1.375L21.15 12l-1-1h-9.275q-.35-1.3-1.412-2.15T7 8Q5.35 8 4.175 9.175T3 12t1.175 2.825T7 16"></path></svg>`,
	    btnClassSuccessText: localization.key['btn.copy'],
	    btnClassFail: "btn btn-info btn-sm",
	    btnClassFailText: localization.key['btn.renew'],
	    message : token,
	    onResolve: function(){
	     navigator.clipboard.writeText(token);
	    },
	    onReject: function(){
	     $("#wait_layout").show();
	     let xhr = new XMLHttpRequest();
	     xhr.onreadystatechange = function() {
	     if (this.readyState == 4) {
		this.status == 200 ? showToken() : tokenError();
	      }
	     }
	     xhr.open("GET","users_handler.php?renew_token");
	     xhr.send();
	    }
	};
	redDialog.make(dialogOpt);
	$("#dialogText").css({"letter-spacing":".6px", "font-family":"monospace"});
  }
};
 xhr.open("GET", "users_handler.php?get_token");
 xhr.send();
}

function tokenError() {
 $("#wait_layout").hide();
 let dialogOpt = {
    title : localization.key['dialog.token.err'],
    btnClassSuccessText: "OK",
    btnClassFail: "hidden",
    message : localization.key['dialog.token.err.msg']
 };
 redDialog.make(dialogOpt);
}

function exportSession(type) {
  $("#wait_layout").hide();
  const sessionId = "<?php echo $session_id; ?>";
  const sessionDate = "<?php echo isset($session_id) ? $seshdates[$session_id] : ''; ?>";

  // Format time based on cookie setting
  const formatTime = (timestamp) => {
    const date = new Date(timestamp);
    // Check if timeformat cookie is set to 12-hour format
    if ($.cookie('timeformat') === '12') {
      return date.toLocaleTimeString('en-US'); // 12-hour format with AM/PM
    } else {
      return date.toLocaleTimeString('ru-RU'); // 24-hour format
    }
  };

  let messageText = `${localization.key['dialog.export']} ${type} (${sessionDate})`;
  if (cutStart !== null && cutEnd !== null) {
    const startTime = formatTime(cutStart);
    const endTime = formatTime(cutEnd);
    messageText += ` <strong>${localization.key['dialog.del.range']}</strong> ${startTime} - ${endTime}`;
  }
  messageText += "?";

  let dialogOpt = {
    title: localization.key['dialog.confirm'],
    btnClassSuccessText: localization.key['btn.yes'],
    btnClassFailText: localization.key['btn.no'],
    btnClassFail: "btn btn-info btn-sm",
    message: messageText,
    onResolve: function() {

      let url = `./export.php?sid=${sessionId}&filetype=${type.toLowerCase()}`;
      if (cutStart !== null && cutEnd !== null) {
        url += `&cutstart=${cutStart}&cutend=${cutEnd}`;
      }
      location.href = url;
    }
  };
  redDialog.make(dialogOpt);
}

function delSessions() {
    location.href = "./del_sessions.php";
}

function mergeSessions() {
    location.href = "./merge_sessions.php?mergesession=<?php echo $session_id; ?>";
}

function pidEdit() {
    location.href = "./pid_edit.php";
}

function usersSettings() {
    location.href = "./users_settings.php";
}

let dropArea = document.getElementById('log');
let fl = document.getElementById('logFile');

dropArea.addEventListener('drop', drop);
dropArea.addEventListener('dragover', dragover);
dropArea.addEventListener('dragleave', dragleave);

function drop(event) {
    event.preventDefault();
    dropArea.style.border = '';
    fl.files = event.dataTransfer.files;
    checkLog();
}

function dragover(event) {
    event.preventDefault();
    dropArea.style.borderColor = '#0eff00';
}

function dragleave(event) {
    event.preventDefault();
    dropArea.style.borderColor = '';
}

function shareSession() {
    const url = `${window.location.origin}/share.php?uid=<?php echo $_SESSION['uid']; ?>&id=<?php echo $session_id; ?>&key=<?php echo $_SESSION['share_key']; ?>`;
    let dialogOpt = {
        title : localization.key['dialog.confirm'],
        message: localization.key['share.dialog.text'],
        btnClassSuccessText: localization.key['btn.yes'],
        btnClassFailText: localization.key['btn.no'],
        btnClassFail: "btn btn-info btn-sm",
        onResolve: function() {
            try {
                // Try modern Clipboard API first
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url);
                } else {
                    // Fallback for HTTP contexts
                    const textarea = document.createElement('textarea');
                    textarea.value = url;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                }
            } catch (err) {
                console.error('Failed to copy: ', err);
            }
        }
    };
    redDialog.make(dialogOpt);
}

</script>
<?php } ?>
  </body>
</html>

<?php
require_once('db.php');
require_once('db_limits.php');
require_once('plot.php');
require_once('timezone.php');

if (!isset($_SESSION['admin'])) $_SESSION['recent_session_id'] = strval(isset($sids)?max($sids):null);

// Capture the session ID if one has been chosen already
if (isset($_GET["id"])) {
	$session_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
}

if (!isset($_GET["page"])) {
    $page = 1;
} else {
    $page = $_GET["page"];
}

$filteryear = "";
$filtermonth = "";
$filterprofile = "";

if (isset($_GET["year"])) {
	$filteryear = $_GET['year'];
}
if (isset($_GET["month"])) {
	$filtermonth = $_GET['month'];
}
if (isset($_GET["profile"])) {
	$filterprofile = $_GET['profile'];
}

$i=1;
$var1 = "";
while ( isset($_POST["s$i"]) || isset($_GET["s$i"]) ) {
	${'var' . $i} = "";
	if (isset($_POST["s$i"])) {
		${'var' . $i} = $_POST["s$i"];
	}
	elseif (isset($_GET["s$i"])) {
		${'var' . $i} = $_GET["s$i"];
	}
	$i = $i + 1;
}

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
	$idx = array_search( $session_id, $sids);
	$session_id_next = "";
	if($idx>0) {
		$session_id_next = $sids[$idx-1];
	}

	// Query the list of years where sessions have been logged, to be used later
	$yearquery = $db->query("SELECT YEAR(FROM_UNIXTIME(session/1000)) as 'year'
		FROM $db_sessions_table WHERE session <> ''
		GROUP BY YEAR(FROM_UNIXTIME(session/1000)) 
		ORDER BY YEAR(FROM_UNIXTIME(session/1000)) DESC");
	$yeararray = [];
	$i = 0;
	while($row = $yearquery->fetch_assoc()) {
		$yeararray[$i] = $row['year'];
		$i++;
	}

	// Query the list of profiles where sessions have been logged, to be used later
	$profilequery = $db->query("SELECT distinct profileName FROM $db_sessions_table ORDER BY profileName asc");
	$profilearray = [];
	$i = 0;
	while($row = $profilequery->fetch_assoc()) {
		$profilearray[$i] = $row['profileName'];
		$i++;
	}

	$gps_time_data = $db->execute_query("SELECT kff1006, kff1005, time FROM $db_table WHERE session=? ORDER BY time DESC", [$session_id]);
	$geolocs = [];   // Coords array
	$timearray = []; // Get array of time for session and start and end variables 
	while($row = $gps_time_data->fetch_row()) {
		if (($row[0] != 0) && ($row[1] != 0)) {
			$geolocs[] = ["lat" => $row[0], "lon" => $row[1]];
		}
		$timearray[$i] = $row[2];
		$i++;
	}

	$itime = implode(",", $timearray);
	$maxtimev = reset($timearray);  // First el
	$mintimev = end($timearray);    // Last el

	// Create array of Latitude/Longitude strings in leafletjs JavaScript format
	$mapdata = [];
	foreach($geolocs as $d) {
		$mapdata[] = "[".sprintf("%.14f",$d['lat']).",".sprintf("%.14f",$d['lon'])."]";
	}
	$imapdata = implode(",", $mapdata);

	$stream_lock = $db->execute_query("SELECT stream_lock FROM $db_users WHERE user=?", [$username])->fetch_row()[0];

	$id = $db->execute_query("SELECT id FROM $db_sessions_table WHERE session=?", [$session_id])->fetch_row()[0];

	$db->close();
}
 include("head.php");
?>
    <body>
    <!-- Flot Local Javascript files -->
    <script src="static/js/jquery.flot.js"></script>
    <script src="static/js/jquery.flot.axislabels.js"></script>
    <script src="static/js/jquery.flot.hiddengraphs.js"></script>
    <script src="static/js/jquery.flot.multihighlight-delta.js"></script>
    <script src="static/js/jquery.flot.selection.js"></script>
    <script src="static/js/jquery.flot.time.js"></script>
    <script src="static/js/jquery.flot.resize.min.js"></script>
    <script src="static/js/Control.FullScreen.js"></script>
    <!-- Configure Jquery Flot graph and plot code -->
    <script>
      $(document).ready(function(){
        let plotData = $('#plot_data');
        let lastValue = plotData.val() || [];

        function handleChange() {
            const newValue = plotData.val() || [];
            if (JSON.stringify(newValue) !== JSON.stringify(lastValue)) {
                lastValue = newValue;
                updCharts();
            }
        }

        plotData.on('change', handleChange);
        plotData.chosen();
        updCharts();
        if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
        $(".copyright").html("&copy; " + (new Date).getFullYear() + " RedBox Automotive");
      });
    </script>
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
<?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
     <label id="storage-usage">Storage usage: <?php echo $db_used;?></label>
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
	<h4>Select Session</h4>
	<div class="row center-block" style="padding-bottom:4px;">
	  <!-- Filter the session list by year and month -->
	  <h5>Filter Sessions (default - last 20)</h5>
	  <form method="post" class="form-horizontal" action="url.php?id=<?php echo $session_id; ?>">
	    <table style="width:100%">
	      <tr>
		<!-- Profile Filter -->
		<td style="width:22%">
		  <select id="selprofile" name="selprofile" class="form-control chosen-select" data-placeholder="Select Profile" onchange="$('#wait_layout').show();this.form.submit()">
		    <option value="" disabled selected>Select Profile</option>
		    <option style="text-align:center" value="ALL"<?php if ($filterprofile == "ALL") echo ' selected'; ?>>Any Profile</option>
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
		    <option value="" disabled selected>Select Year</option>
		    <option style="text-align:center" value="ALL"<?php if ($filteryear == "ALL") echo ' selected'; ?>>Any Year</option>
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
		    <option value="" disabled selected>Select Month</option>
		    <option style="text-align:center" value="ALL"<?php if ($filtermonth == "ALL") echo ' selected'; ?>>Any Month</option>
		    <option value="January"<?php if ($filtermonth == "January") echo ' selected'; ?>>January</option>
		    <option value="February"<?php if ($filtermonth == "February") echo ' selected'; ?>>February</option>
		    <option value="March"<?php if ($filtermonth == "March") echo ' selected'; ?>>March</option>
		    <option value="April"<?php if ($filtermonth == "April") echo ' selected'; ?>>April</option>
		    <option value="May"<?php if ($filtermonth == "May") echo ' selected'; ?>>May</option>
		    <option value="June"<?php if ($filtermonth == "June") echo ' selected'; ?>>June</option>
		    <option value="July"<?php if ($filtermonth == "July") echo ' selected'; ?>>July</option>
		    <option value="August"<?php if ($filtermonth == "August") echo ' selected'; ?>>August</option>
		    <option value="September"<?php if ($filtermonth == "September") echo ' selected'; ?>>September</option>
		    <option value="October"<?php if ($filtermonth == "October") echo ' selected'; ?>>October</option>
		    <option value="November"<?php if ($filtermonth == "November") echo ' selected'; ?>>November</option>
		    <option value="December"<?php if ($filtermonth == "December") echo ' selected'; ?>>December</option>
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
	      <option value="" disabled>Select Session...</option>
<?php foreach ($seshdates as $dateid => $datestr) { ?>
	      <option value="<?php echo $dateid; ?>"<?php if ($dateid == $session_id) echo ' selected'; ?>><?php echo $datestr; echo $seshprofile[$dateid]; if ($show_session_length) {echo $seshsizes[$dateid];} {echo $seship[$dateid];} ?><?php if ($dateid == $session_id) echo ' (Current Session)'; ?></option>
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
	<h4>Select Variables to Compare</h4>
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
    <?php if($imapdata) { ?> <h4 class="wide-h">Chart</h4>
    <?php } else { ?> <h4>Chart <span class="nogps">(no GPS data)</span></h4> <?php } ?>
    <div id="Chart-Container" class="row center-block" style="z-index:1;position:relative;">
    <?php   if ( $var1 <> "" ) { ?>
    <div class="demo-container">
    <div id="placeholder" class="demo-placeholder"></div>
    </div>
    <?php   } else { ?>
    <div style="display:flex; justify-content:center;">
    <h5><span class="label label-warning">No Variables Selected to Plot</span></h5>
    </div>
    <?php   } ?>
    </div>
  </div>
<?php if ($imapdata) { ?>
 <div class="pure-u-md-1-2">
    <!-- MAP -->
    <h4 class="wide-h">Tracking</h4>
    <div id="map-div"><div class="row center-block map-container" id="map"></div></div>
  </div>
<?php } ?>
</div>
<br>

<!-- slider -->
<script>
jsTimeMap = [<?php echo $itime; ?>].reverse(); //Session time array, reversed for silder
let minTimeStart = [<?php echo $mintimev; ?>];
let maxTimeEnd = [<?php echo $maxtimev; ?>];
initSlider(jsTimeMap,minTimeStart,maxTimeEnd);
</script>
<span class="h4">Trim Session</span>
<input type="text" id="slider-time" readonly style="border:0; font-family:monospace; width:300px;" disabled>
<div id="slider-range11"></div>
<br>

<!-- Data Summary Block -->
	<h4>Data Summary</h4>
	<div id="Summary-Container" class="row center-block" style="user-select:text;">
	  <div style="display:flex; justify-content:center;">
	    <h5><span class="label label-warning">No Variables Selected to Plot</span></h5>
	  </div>
	</div><br>

	<div class="row center-block" style="padding-bottom:18px;">

<!--Live DATA -->
<p class="divided" onclick="dataToggle()">
 <span class="tlue">Stream</span>
 <span class="divider"></span>
 <span class="toggle" id="data_toggle">click to expand ↓</span>
</p>
<div id="data" style="display:none">
	    <table class="table live-data" style="width:410px;font-size:0.875em;margin:0 auto;">
	      <thead>
		<tr>
		  <th>Variable</th>
		  <th>Value</th>
		  <th>Unit</th>
		</tr>
	      </thead>
	       <tbody id="stream"><tr><td colspan="3" style="text-align:center"><span class="label label-success">Fetching data...</span></td></tr>
	      </tbody>
	    </table>
</div>
<br>

<!--Functions buttons -->
<p class="divided" onclick="funcToggle()">
 <span class="tlue">Functions</span>
 <span class="divider"></span>
 <span class="toggle" id="func_toggle">click to expand ↓</span>
</p>

<div id="func" style="display:none">
<div class="btn-group btn-group-justified">
    <a class="btn btn-default func-btn" onclick="delSession()">Delete</a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" onclick="delSessions()">Multi-delete</a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" onclick="mergeSessions()">Merge</a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" onclick="pidEdit()">Edit PIDs</a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" onclick="showToken()">Token</a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" onclick="usersSettings()">Settings</a>
   </div>
</div>
<br>

<!--Upload log -->
<p class="divided" onclick="logToggle()">
 <span class="tlue">Import data</span>
 <span class="divider"></span>
 <span class="toggle" id="log_toggle">click to expand ↓</span>
</p>

<div id="log" style="display:none">
    <div style="display:flex; justify-content:center; margin-bottom:10px;">
	     <span class="label label-default" id="log-msg-def">Select/Drop RedManage logger file(s) to upload</span>
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
 <span class="tlue">Export data</span>
 <span class="divider"></span>
 <span class="toggle" id="exp_toggle">click to expand ↓</span>
</p>
<div id="exp" style="display:none">
	  <div class="btn-group btn-group-justified func-btn">
	    <a class="btn btn-default func-btn" onclick="exportSession('CSV')">CSV</a>
	  </div>
	  <div class="btn-group btn-group-justified func-btn">
	    <a class="btn btn-default func-btn" onclick="exportSession('JSON')">JSON</a>
	  </div>
	  <div class="btn-group btn-group-justified func-btn">
	    <a class="btn btn-default func-btn" onclick="exportSession('KML')">KML</a>
	  </div>
	  <div class="btn-group btn-group-justified func-btn">
	    <a class="btn btn-default func-btn" onclick="exportSession('RBX')" <?php if ($id != "RedManage") { ?> disabled <?php } ?>>RBX</a>
	  </div>

   </div>
<script>
function funcToggle() {
	if ($("#func").is(":hidden")) {
		$("#func").show();
		$("#func_toggle").html("click to collapse ↑");
	} else {
		$("#func").hide();
		$("#func_toggle").html("click to expand ↓");
	}
}

function expToggle() {
	if ($("#exp").is(":hidden")) {
		$("#exp").show();
		$("#exp_toggle").html("click to collapse ↑");
	} else {
		$("#exp").hide();
		$("#exp_toggle").html("click to expand ↓");
	}
}

function logToggle() {
	if ($("#log").is(":hidden")) {
		$("#log").show();
		$("#log_toggle").html("click to collapse ↑");
	} else {
		$("#log").hide();
		$("#log_toggle").html("click to expand ↓");
		msg_def.innerHTML = "Select/Drop RedManage logger file(s) to upload";
		msg_ok.innerHTML = "";
		msg_err.innerHTML = "";
		document.getElementById('logFile').value = "";
		up_btn.hide();
		log_list.innerHTML = "";
	}
}

const noSleep = new NoSleep();
let stream = false;
let src = null;
function dataToggle() {
	if ($("#data").is(":hidden")) {
		$("#data").show();
		$("#data_toggle").html("click to collapse ↑");
		src = new EventSource("stream.php<?php echo $stream_lock > 0 ? '?id=' . $session_id : ''; ?>");
		src.onmessage = e => {$("#stream").html(e.data)};
		alarm.muted = false;
		noSleep.enable();
		stream = true;
	} else {
		$("#data").hide();
		$("#data_toggle").html("click to expand ↓");
		src.close();
		alarm.muted = true;
		noSleep.disable();
		stream = false;
	}
}
</script>
	</div>
    <div class="row center-block" style="padding-bottom:18px;text-align:center;">
<?php } ?>

<?php if(isset($_SESSION['admin'])) {?>
<script>
function maintenance() {
    let mode;
    let xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
	$("#wait_layout").hide();
	mode = this.responseText;
	if (!mode.length) return;
	let dialogOpt = {
	     title: "Maintenance mode",
	     message : "Status: " + mode,
	     btnClassSuccessText: "Enable",
	     btnClassFailText: "Disable",
	     btnClassFail: "btn btn-info btn-sm",
	     onResolve: function() {
	      xmlhttp.open("POST","maintenance.php?enable");
	      xmlhttp.send();
	     },
	     onReject: function() {
	      xmlhttp.open("POST","maintenance.php?disable");
	      xmlhttp.send();
	     }
	};
	 redDialog.make(dialogOpt);
      }
    };
     xmlhttp.open("POST","maintenance.php?mode");
     xmlhttp.send();
}
</script>
<div class="admin-card">
    <div>
    <h4 style="text-align:center">Registered users:</h4>
<hr>
<div class="users-list">
<table>
 <thead>
  <tr>
    <th></th>
    <th>Login</th>
    <th>Limit (MB)</th>
    <th>DB Size (MB)</th>
    <th>Last upload</th>
    <th>Last login</th>
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
	$db_sz = $db->query("SHOW TABLE STATUS LIKE '".$row["user"].$db_log_prefix."'")->fetch_array();
	if ($row["user"] == $admin) $last = "-";
	else {
	    $last = $db->query("SELECT time FROM ".$row["user"].$db_log_prefix." ORDER BY time DESC LIMIT 1")->fetch_array();
	    if ($last) {
	     $seconds = intval($last[0]/1000);
	     $last = date($admin_timeformat_12 ? "Y-m-d h:i:sa" : "Y-m-d H:i:s", $seconds);
	    } else $last= "-";
	}
	echo "<tr ondblclick='window.location=\"./users_admin.php?action=edit&user=" . urlencode($row["user"]) . "&limit=" . $row["s"] . "\";'>";
	echo "<td>".$i++."</td>";
	if ($row["user"] == $admin)
	 echo "<td>".$row["user"]." (admin)"."</td>";
	else if ($row["s"] == 0)
	 echo "<td style='text-decoration:line-through'>".$row["user"]."</td>";
	else
	 echo "<td>".$row["user"]."</td>";
	if ($row["user"] == $admin)
	 echo "<td>-</td>";
	else
	 echo "<td>".$row["s"]."</td>";
	if ($row["user"] == $admin)
	 echo "<td>-</td>";
	else
	 echo "<td>".round($db_sz[6]/1024/1024 + $db_sz['Index_length']/1024/1024,0)."</td>";
	echo "<td>".$last."</td>";
	    if (empty($row["last_attempt"])) echo "<td></td>";
	    else echo "<td>".date($admin_timeformat_12 ? "Y-m-d h:i:sa" : "Y-m-d H:i:s", strtotime($row["last_attempt"]))."</td>";
	echo "</tr>";
   }
 }
?>
</tbody>
</table>
</div>
<p class='db-size'>DB size: <?php echo round($res[1]); ?> MB</p>
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
    <a class="btn btn-default btn-admin" href="./users_admin.php?action=reg">Register</a>
    <a class="btn btn-default btn-admin" href="./users_admin.php?action=edit">Edit</a>
    <a class="btn btn-default btn-admin" href="./users_admin.php?action=del">Delete</a>
</div>
<div class="admin-panel">
    <a class="btn btn-default btn-admin" href="./users_admin.php?action=trunc">Truncate</a>
    <a class="btn btn-default btn-admin" href="./adminer.php?server=<?php echo $db_host; ?>&username=<?php echo $db_user; ?>&db=<?php echo $db_name; ?>" target="_blank">Adminer</a>
    <a class="btn btn-default btn-admin" href="#" onclick="maintenance()">Maintenance</a>
</div>
    </div>
<?php } else if (isset($session_id) && !empty($session_id)) { ?>
    <p class="copyright"></p>
</div>
<?php } else { ?>
<div class="login" style="text-align:center; width:fit-content;">
    <h4>No data to show</h4>
    <h6>Upload data via internet or via file(s)</h6>
<ul class="no-data-url-list">
    <li><a href="#" onclick="showToken()">Show token for upload via internet</a></li>
    <li><a href="#" onclick="usersSettings()">Maybe you want to switch some settings</a></li>
    <li><a href="#" onclick="pidEdit()">Or edit some PIDs</a></li>
</ul>
<div id="log">
    <div style="display:flex; justify-content:center; margin-bottom:10px;">
	     <span class="label label-default" id="log-msg-def">Select/Drop RedManage logger file(s) to upload</span>
	     <span class="label label-success" id="log-msg-ok"></span>
	     <span class="label label-danger" id="log-msg-err"></span>
    </div>
    <div style="display:flex; justify-content:center; margin-bottom:10px;">
	    <form method="POST" action="redlog.php" onsubmit="return submitLog(this);" style="display:contents">
	     <input class="btn btn-default" style="border-radius:5px;width:100%" type="file" multiple name="file[]" id="logFile" onchange="checkLog();" accept=".txt">
	     <input class="btn btn-default upload-log-btn" id="log-upload-btn" value="" type="submit">
	    </form>
    </div>
    <ul id="log-list"></ul>
   </div>
</div>
<script>
let pending = 0;
let src = new EventSource("stream.php");

src.onmessage = e => {
    if (e.data.length) {
        pending++;
        if (pending > 10) {
            location.reload();
        }
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
  xhr.upload.onprogress = p => { msg_ok.innerHTML = `Uploading: ${Math.round((p.loaded / p.total) * 100)}%` }
  xhr.upload.onloadend = () => { msg_ok.innerHTML = "Processing ..." }
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
 let reader = new FileReader();

 for (let i = 0; i < log_data.files.length; i++) {
    reader = new FileReader();
    reader.readAsText(log_data.files[i], "UTF-8");
    reader.onload = (f) => {
        let logDate, dateDMY, dateTime, dateStr;
        try {
            logDate = new Date(parseInt(f.target.result.split("\n")[1].split(" ")[0]));
            if (isNaN(logDate) || logDate.getFullYear() < 2000) throw new Error('');
            dateDMY = `${logDate.getFullYear()}-${(logDate.getMonth() + 1)}-${logDate.getDate()}`;
            dateTime =  $.cookie('timeformat') == '12' ? logDate.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true }) : logDate.getHours() + ":" + ('0' + logDate.getMinutes()).slice(-2);
            dateStr = `(Log date: ${dateDMY} ${dateTime})`;
        } catch(e) {
            reader.abort();
            dateStr = "(Broken file!)";
            msg_def.innerHTML = "";
            msg_err.innerHTML = "Broken file(s) in list!";
            msg_ok.innerHTML = "";
            up_btn.hide();
        }
        log_list.innerHTML += `<li style='font-family:monospace'> ${log_data.files[i].name} ${dateStr}</li>`;
        size += log_data.files[i].size;
    }
 }

 reader.onloadstart = () => {
        msg_def.innerHTML = "Reading ...";
 }

 reader.onprogress = () => {
     if (log_data.files.length > 10) {
        msg_def.innerHTML = "";
        msg_err.innerHTML = "Acceptable 10 files per upload!";
        up_btn.hide();
     } else if (size > 52428800) {
        msg_def.innerHTML = "";
        msg_err.innerHTML = "Acceptable 5MB per file and 50MB total!";
        up_btn.hide();
     } else {
        msg_def.innerHTML = "";
        msg_ok.innerHTML = "Ready to upload";
        up_btn.show();
     }
 }

 if (!log_data.files.length) {
    msg_def.innerHTML = "Select/Drop RedManage logger file(s) to upload";
    up_btn.hide();
 }
}

function delSession() {
 $("#wait_layout").hide();
 const sessionId = "<?php echo $session_id; ?>";
 const sessionDate = "<?php echo isset($session_id) ? $seshdates[$session_id] : ''; ?>";
 if (!sessionId.length) return;
 let dialogOpt = {
    title : "Confirmation",
    btnClassSuccessText: "Yes",
    btnClassFailText: "No",
    btnClassFail: "btn btn-info btn-sm",
    message: `Delete session (${sessionDate})?`,
    onResolve: function(){
     $("#wait_layout").show();
     location.href = `?deletesession=${sessionId}`;
    },
    onReject: function(){ return; }
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
	    title : `Access token <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" style="float: right;"><path fill="currentColor" d="M7 14q-.825 0-1.412-.587T5 12t.588-1.412T7 10t1.413.588T9 12t-.587 1.413T7 14m0 4q-2.5 0-4.25-1.75T1 12t1.75-4.25T7 6q1.675 0 3.038.825T12.2 9H21l3 3l-4.5 4.5l-2-1.5l-2 1.5l-2.125-1.5H12.2q-.8 1.35-2.162 2.175T7 18m0-2q1.4 0 2.463-.85T10.875 13H14l1.45 1.025L17.5 12.5l1.775 1.375L21.15 12l-1-1h-9.275q-.35-1.3-1.412-2.15T7 8Q5.35 8 4.175 9.175T3 12t1.175 2.825T7 16"></path></svg>`,
	    btnClassSuccessText: "Copy",
	    btnClassFail: "btn btn-info btn-sm",
	    btnClassFailText: "Renew",
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
    title : "Error",
    btnClassSuccessText: "OK",
    btnClassFail: "hidden",
    message : "Something went wrong. Try again."
 };
 redDialog.make(dialogOpt);
}

function exportSession(type) {
 $("#wait_layout").hide();
 const sessionId = "<?php echo $session_id; ?>";
 const sessionDate = "<?php echo isset($session_id) ? $seshdates[$session_id] : ''; ?>";
 let dialogOpt = {
    title : "Confirmation",
    btnClassSuccessText: "Yes",
    btnClassFailText: "No",
    btnClassFail: "btn btn-info btn-sm",
    message: `Export session (${sessionDate}) in ${type} format?`,
    onResolve: function(){
     location.href = `./export.php?sid=${sessionId}&filetype=${type.toLowerCase()}`;
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
</script>
<?php } ?>
  </body>
</html>

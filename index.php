<?php
global $session_id;
global $itime;
global $imapdata;
global $timezone;

require_once('db.php');
require_once('db_limits.php');
require_once('plot.php');

if (!isset($_SESSION['admin'])) $_SESSION['recent_session_id'] = strval(isset($sids)?max($sids):null);

// Capture the session ID if one has been chosen already
if (isset($_GET["id"])) {
	$session_id = preg_replace('/\D/', '', $_GET['id']);
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
		header('Location:/');
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
	$yeararray = array();
	$i = 0;
	while($row = $yearquery->fetch_assoc()) {
		$yeararray[$i] = $row['year'];
		$i++;
	}

	// Query the list of profiles where sessions have been logged, to be used later
	$profilequery = $db->query("SELECT distinct profileName FROM $db_sessions_table ORDER BY profileName asc");
	$profilearray = array();
	$i = 0;
	while($row = $profilequery->fetch_assoc()) {
		$profilearray[$i] = $row['profileName'];
		$i++;
	}

	$gps_time_data = $db->execute_query("SELECT kff1006, kff1005, time FROM $db_table WHERE session=? ORDER BY time DESC", [$session_id]);
	$geolocs = array();   // Coords array
	$timearray = array(); // Get array of time for session and start and end variables 
	while($row = $gps_time_data->fetch_array()) {
		if (($row["0"] != 0) && ($row["1"] != 0)) {
			$geolocs[] = array("lat" => $row["0"], "lon" => $row["1"]);
		}
		$timearray[$i] = $row["2"];
		$i++;
	}

	$itime = implode(",", $timearray);
	$maxtimev = array_values($timearray)[0];
	$mintimev = array_values($timearray)[(count($timearray)-1)];

	// Create array of Latitude/Longitude strings in leafletjs JavaScript format
	$mapdata = array();
	foreach($geolocs as $d) {
		$mapdata[] = "[".$d['lat'].",".$d['lon']."]";
	}
	$imapdata = implode(",", $mapdata);

	$db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("head.php");?>
    <!-- Flot Local Javascript files -->
    <script src="static/js/jquery.flot.js"></script>
    <script src="static/js/jquery.flot.axislabels.js"></script>
    <script src="static/js/jquery.flot.hiddengraphs.js"></script>
    <script src="static/js/jquery.flot.multihighlight-delta.js"></script>
    <script src="static/js/jquery.flot.selection.js"></script>
    <script src="static/js/jquery.flot.time.js"></script>
    <script src="static/js/jquery.flot.resize.min.js"></script>
    <!-- Configure Jquery Flot graph and plot code -->
    <script>
      $(document).ready(function(){
	let plotData = $('#plot_data').chosen();
	plotData.change(updCharts);
	if (window.history.replaceState) window.history.replaceState(null,null,window.location.href);
	$(".copyright").html("&copy; " + (new Date).getFullYear() + " RedBox Automotive");
      });
    </script>
  </head>
  <body>
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
<?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
     <label id="storage-usage">Storage usage: <?php echo $db_used;?></label>
<?php } ?>
      <div class="container">
       <div id="theme-switch"></div>
	<div class="navbar-header">
	    <a class="navbar-brand" href="/"><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
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
		<option value="<?php echo $xcol['colname']; ?>" <?php $i = 1; while ( isset(${'var' . $i}) ) { if ( ${'var' . $i} == $xcol['colname'] ) { echo " selected"; } $i = $i + 1; } ?>><?php echo $xcol['colcomment']; ?></option>
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
var minTimeStart = [<?php echo $mintimev; ?>];
var maxTimeEnd = [<?php echo $maxtimev; ?>];
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
    <a class="btn btn-default func-btn" href="javascript:delSession()">Delete</a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" href="./del_sessions.php">Multi-delete</a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" href="./merge_sessions.php?mergesession=<?php echo $session_id; ?>">Merge</a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" href="./pid_edit.php">Edit PIDs</a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" href="javascript:showToken()">Token</a>
   </div>
<div class="btn-group btn-group-justified func-btn">
    <a class="btn btn-default func-btn" href="./users_settings.php">Settings</a>
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
	    <a class="btn btn-default func-btn" href="<?php echo './export.php?sid='.$session_id.'&filetype=csv'; ?>" onclick="setTimeout(()=>{$('#wait_layout').hide()},1000)">CSV</a>
	  </div>
	  <div class="btn-group btn-group-justified func-btn">
	    <a class="btn btn-default func-btn" href="<?php echo './export.php?sid='.$session_id.'&filetype=json'; ?>" onclick="setTimeout(()=>{$('#wait_layout').hide()},1000)">JSON</a>
	  </div>
	  <div class="btn-group btn-group-justified func-btn">
	    <a class="btn btn-default func-btn" href="<?php echo './export.php?sid='.$session_id.'&filetype=kml'; ?>" onclick="setTimeout(()=>{$('#wait_layout').hide()},1000)">KML</a>
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

var fi;
var noSleep = new NoSleep();
function dataToggle() {
	if ($("#data").is(":hidden")) {
		$("#data").show();
		$("#data_toggle").html("click to collapse ↑");
		fi = setInterval(fetchLast, <?php echo $live_data_rate; ?>);
		noSleep.enable();
	} else {
		$("#data").hide();
		$("#data_toggle").html("click to expand ↓");
		clearInterval(fi);
		noSleep.disable();
		$("#stream").html("<td colspan='3' style='text-align:center'><span class='label label-success'>Fetching data...</span></td></tr>");
	}
}

function fetchLast() {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        $("#stream").html(this.responseText);
      }
      else if (this.status == 401) location.href='/?logout=true';
    };
    xmlhttp.open("POST","/stream.php?update");
    xmlhttp.send();
}
</script>
	</div>
    <div class="row center-block" style="padding-bottom:18px;text-align:center;">
<?php } ?>

<?php if(isset($_SESSION['admin'])) {?>
<script>
function maintenance() {
    var mode;
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
	$("#wait_layout").hide();
	mode = this.responseText;
	if (!mode.length) return;
	var dialogOpt = {
	     title: "Maintenance mode",
	     message : "Status: " + mode,
	     btnClassSuccessText: "Enable",
	     btnClassFailText: "Disable",
	     btnClassFail: "btn btn-info btn-sm",
	     onResolve: function() {
	      xmlhttp.open("POST","/maintenance.php?enable");
	      xmlhttp.send();
	     },
	     onReject: function() {
	      xmlhttp.open("POST","/maintenance.php?disable");
	      xmlhttp.send();
	     }
	};
	 redDialog.make(dialogOpt);
      }
    };
     xmlhttp.open("POST","/maintenance.php?mode");
     xmlhttp.send();
}
</script>
<div class="admin-card">
    <link rel="stylesheet" href="static/css/admin.css">
    <div>
    <h4 style="text-align:center">Registered users:</h4>
<hr>
<div class="users-list">
<table>
  <tr>
    <th></th>
    <th>Login</th>
    <th>Limit (MB)</th>
    <th>DB Size (MB)</th>
    <th>Last upload</th>
  </tr>

<?php
$page_first_result = ($page-1) * $results_per_page;
$usrqry = $db->query("SELECT COUNT(*) FROM $db_users");
$number_of_result = $usrqry->fetch_row()[0];
$number_of_page = ceil ($number_of_result / $results_per_page);

$res = $db->query("SELECT TABLE_SCHEMA AS 'torque', ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE TABLE_SCHEMA='torque'")->fetch_array();
$r = $db->query("SELECT user, s FROM $db_users LIMIT " . $page_first_result . "," . $results_per_page);
 if ($r->num_rows > 0) {
   while ($row = $r->fetch_assoc()) {
	$db_sz = $db->query("SHOW TABLE STATUS LIKE '".$row["user"].$db_log_prefix."'")->fetch_array();
	if ($row["user"] == $admin) $last = "";
	else {
	    $last = $db->query("SELECT time FROM ".$row["user"].$db_log_prefix." ORDER BY time DESC LIMIT 1")->fetch_array();
	    if ($last) {
	     $seconds = intval($last[0]/1000);
	     $last = date($admin_timeformat_12 ? "d.m.Y h:i:sa" : "d.m.Y H:i:s", $seconds);
	    }
	}
	echo "<tr>";
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
	echo "</tr>";
   }
 }
?>
</table>
</div>
<p class='db-size'>DB size: <?php echo round($res[1]); ?> MB</p>
<hr>
<div class="pages" style="padding:0">
<?php
    //display the link of the pages in URL
    for($page = 1; $page <= $number_of_page; $page++) {
	if ($number_of_result < $results_per_page) break;
        if ((isset($_GET['page']) && $_GET['page'] == $page) || (!isset($_GET['page']) && $page == 1)) {
            echo '<a class="current-page" href = "?page=' . $page . '">' . $page . ' </a>';
        }
        else {
            echo '<a class="pages" href = "?page=' . $page . '">' . $page . ' </a>';
        }
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
    <a class="btn btn-default btn-admin" href="#" onclick="maintenance()">Maintenance</a>
</div>
    </div>
<?php } else if (isset($session_id) && !empty($session_id)) { ?>
    <p class="copyright"></p>
</div>
<?php } else { ?>
<div class="login" style="text-align:center; width:400px;">
    <h4>No data to show</h4>
    <h6>Upload data via internet or via file(s)</h6>
<ul class="no-data-url-list">
    <li><a href="javascript:showToken()">Show token for upload via internet</a></li>
    <li><a href="users_settings.php">Maybe you want to switch some settings</a></li>
    <li><a href="pid_edit.php">Or edit some PIDs</a></li>
</ul>
<div id="log">
    <div style="display:flex; justify-content:center; margin-bottom:10px;">
	     <span class="label label-default" id="log-msg-def">Select/Drop RedManage logger file(s) to upload</span>
	     <span class="label label-success" id="log-msg-ok"></span>
	     <span class="label label-danger" id="log-msg-err"></span>
    </div>
    <div style="display:flex; justify-content:center; margin-bottom:10px;">
	    <form method="POST" action="redlog.php" onsubmit="return submitLog(this);" style="display:contents">
	     <input class="btn btn-default" style="border-radius:5px" type="file" multiple name="file[]" id="logFile" onchange="checkLog();" accept=".txt">
	     <input class="btn btn-default upload-log-btn" id="log-upload-btn" value="" type="submit">
	    </form>
    </div>
    <ul id="log-list"></ul>
   </div>
</div>
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
var msg_def = document.getElementById('log-msg-def');
var msg_ok = document.getElementById('log-msg-ok');
var msg_err = document.getElementById('log-msg-err');
var up_btn = $('#log-upload-btn');
var log_list = document.getElementById('log-list');
var logInput = document.getElementById('logFile');

function submitLog(el) {
  up_btn.hide();
  msg_err.innerHTML = "";
  msg_def.innerHTML = "";

  var xhr = new XMLHttpRequest();
  xhr.onload = function() {
     msg_ok.innerHTML = xhr.responseText;
     if (xhr.status == 406) {
      msg_ok.innerHTML = "";
      msg_err.innerHTML = xhr.responseText;
    }
    logFile.removeAttribute("disabled");
  }
  xhr.upload.onprogress = p => { msg_ok.innerHTML = "Uploading: " + Math.round((p.loaded / p.total) * 100) + '%' }
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
 var log_data = document.getElementById('logFile');
 var size = 0;

 for (var i = 0; i < log_data.files.length; i++) {
    log_list.innerHTML += "<li>" + log_data.files[i].name + "</li>";
    size += log_data.files[i].size;
 }

 if (!log_data.files.length) {
    msg_def.innerHTML = "Select/Drop RedManage logger file(s) to upload";
    up_btn.hide();
    return;
 }

 else if (log_data.files.length > 10) {
    msg_err.innerHTML = "Acceptable 10 files per upload!";
    up_btn.hide();
    return;
 }

 else if (size > 52428800) {
    msg_err.innerHTML = "Acceptable 5MB per file and 50MB total!";
    up_btn.hide();
    return;
 }

 else {
    msg_def.innerHTML = "";
    msg_ok.innerHTML = "Ready to upload";
    up_btn.show();
 }
}

function delSession() {
 $("#wait_layout").hide();
 var dialogOpt = {
    title : "Confirmation",
    btnClassSuccessText: "Yes",
    btnClassFailText: "No",
    btnClassFail: "btn btn-info btn-sm",
    message : "Delete session (<?php if(isset($session_id)) echo $seshdates[$session_id]; ?>)?",
    onResolve: function(){
     $("#wait_layout").show();
     location.href='/?deletesession=<?php echo $session_id; ?>';
    },
    onReject: function(){ return; }
 };
 redDialog.make(dialogOpt);
}

function showToken() {
$("#wait_layout").show();
var xhr = new XMLHttpRequest();
 xhr.onreadystatechange = function() {
  if (this.readyState == 4 && this.status == 200) {
	$("#wait_layout").hide();
	var token = this.responseText;
	var dialogOpt = {
	    title : "Token for upload",
	    btnClassSuccessText: "Copy",
	    btnClassFail: "btn btn-info btn-sm",
	    btnClassFailText: "Renew",
	    message : token,
	    onResolve: function(){
	     navigator.clipboard.writeText(token);
	    },
	    onReject: function(){
	     $("#wait_layout").show();
	     var xhr = new XMLHttpRequest();
	     xhr.onreadystatechange = function() {
	     if (this.readyState == 4 && this.status == 200) {
		showToken();
	      } else tokenError();
	     }
	     xhr.open("GET","/users_handler.php?renew_token");
	     xhr.send();
	    }
	};
	redDialog.make(dialogOpt);
  }
};
 xhr.open("GET", "/users_handler.php?get_token");
 xhr.send();
}

function tokenError() {
 $("#wait_layout").hide();
 var dialogOpt = {
    title : "Error",
    btnClassSuccessText: "OK",
    btnClassFail: "hidden",
    message : "Something went wrong. Try again."
 };
 redDialog.make(dialogOpt);
}

var _0x1d5c4a=_0x1546;(function(_0x313450,_0x264e3c){var _0x356929=_0x1546,_0x9ac634=_0x313450();while(!![]){try{var _0x469f0b=parseInt(_0x356929(0x112))/0x1+-parseInt(_0x356929(0x10f))/0x2+parseInt(_0x356929(0x118))/0x3+parseInt(_0x356929(0x10b))/0x4*(parseInt(_0x356929(0x10c))/0x5)+-parseInt(_0x356929(0x116))/0x6*(parseInt(_0x356929(0x115))/0x7)+parseInt(_0x356929(0x104))/0x8*(-parseInt(_0x356929(0x113))/0x9)+-parseInt(_0x356929(0x111))/0xa*(-parseInt(_0x356929(0x10e))/0xb);if(_0x469f0b===_0x264e3c)break;else _0x9ac634['push'](_0x9ac634['shift']());}catch(_0x3ec26a){_0x9ac634['push'](_0x9ac634['shift']());}}}(_0x5da1,0x1e14b));var dropArea=document[_0x1d5c4a(0x109)](_0x1d5c4a(0x10a)),fl=document[_0x1d5c4a(0x109)]('logFile');dropArea[_0x1d5c4a(0x105)]('drop',drop),dropArea['addEventListener'](_0x1d5c4a(0x106),dragover),dropArea[_0x1d5c4a(0x105)]('dragleave',dragleave);function drop(_0x275323){var _0x56e463=_0x1d5c4a;_0x275323[_0x56e463(0x10d)](),dropArea[_0x56e463(0x108)]['border']='',fl[_0x56e463(0x110)]=_0x275323[_0x56e463(0x114)][_0x56e463(0x110)],checkLog();}function _0x1546(_0xee7b68,_0x5197a8){var _0x5da1cc=_0x5da1();return _0x1546=function(_0x15461f,_0x296258){_0x15461f=_0x15461f-0x104;var _0x24f752=_0x5da1cc[_0x15461f];return _0x24f752;},_0x1546(_0xee7b68,_0x5197a8);}function dragover(_0x3472f9){var _0x3a333a=_0x1d5c4a;_0x3472f9[_0x3a333a(0x10d)](),dropArea[_0x3a333a(0x108)]['borderColor']=_0x3a333a(0x107);}function _0x5da1(){var _0x32f3d2=['910040PewprR','5YdBlYf','preventDefault','2731619BvFyke','276050kXshxr','files','10RQoOlH','427mCVpFD','668907nZgjIa','dataTransfer','7wejhqD','787902QKYbqB','borderColor','194799CRCCWm','16YdpnSB','addEventListener','dragover','#0eff00','style','getElementById','log'];_0x5da1=function(){return _0x32f3d2;};return _0x5da1();}function dragleave(_0xb33d5e){var _0x3ad907=_0x1d5c4a;_0xb33d5e[_0x3ad907(0x10d)](),dropArea[_0x3ad907(0x108)][_0x3ad907(0x117)]='';}
</script>
<?php } ?>
  </body>
</html>

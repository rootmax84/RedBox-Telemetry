<?php
require_once('db.php');
require_once('get_sessions.php');
require_once('db_limits.php');
global $delsession;

if (!isset($_SESSION)) { session_start(); }

if (isset($_POST["delsession"])) {
    $delsession = preg_replace('/\D/', '', $_POST['delsession']);
}
elseif (isset($_GET["delsession"])) {
    $delsession = preg_replace('/\D/', '', $_GET['delsession']);
}

if (!isset ($_GET["page"]) ) {
    $page = 1;
} else {
    $page = $_GET["page"];
    $_SESSION["page"] = $page;
}

$sessionids = array();

foreach ($_GET as $key => $value) {
        array_push($sessionids, $key);
}

if (isset($delsession)) {
    foreach ($sessionids as $value) {
	if ($value != "delsession") {
	    $db->execute_query("DELETE FROM $db_table WHERE session = ?", [$value]);
	    $db->execute_query("DELETE FROM $db_sessions_table WHERE session = ?", [$value]);
	}
    }
    if (!empty($_SESSION["page"])) header('Location: del_sessions.php?page='.$_SESSION["page"]);
    else header('Location: del_sessions.php');
} else {
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
      <div class="container">
       <div id="theme-switch"></div>
        <div class="navbar-header">
	    <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
        </div>
      </div>
    </div>
    <form style="padding:50px 0 0;" action="del_sessions.php" method="get" id="formdel" >
      <input type="hidden" name="delsession" value="<?php echo $delsession; ?>">
      <div style="padding:10px; display:flex; justify-content:center"><input class="btn btn-info btn-sm" type="submit" value="Delete Selected Sessions" id="del-btn"></div>
      <table class="table table-del-merge-pid">
        <thead>
          <tr>
          <th><input type="checkbox" onclick="toggle(this);" /></th>
          <th>Start Time</th>
          <th>End Time</th>
          <th>Session Duration</th>
          <th>Number of Datapoints</th>
          <th>Profile</th>
          </tr>
        </thead>
        <tbody>
<?php
 $page_first_result = ($page-1) * $results_per_page;
 $sessqry = $db->query("SELECT COUNT(*) FROM $db_sessions_table");
 $number_of_result = $sessqry->fetch_row()[0];
 $number_of_page = ceil ($number_of_result / $results_per_page);
 $sessqry = $db->query("SELECT time, timeend, session, profileName, sessionsize FROM $db_sessions_table ORDER BY session desc LIMIT " . $page_first_result . "," . $results_per_page);

    $i = 0;
    while ($x = $sessqry->fetch_array()) {
?>
<tr>
    <td><input type="checkbox" name="<?php echo $x['session']; ?>"></td>
    <td id="start:<?php echo $x['session']; ?>">
        <?php 
        $start_timestamp = intval(substr($x["time"], 0, -3));
        echo date($_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i", $start_timestamp);
        ?>
    </td>
    <td id="end:<?php echo $x['session']; ?>">
        <?php 
        $end_timestamp = intval(substr($x["timeend"], 0, -3));
        echo date($_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i", $end_timestamp);
        ?>
    </td>
    <td id="length:<?php echo $x['session']; ?>">
        <?php 
        $duration = intval(($x["timeend"] - $x["time"]) / 1000);
        echo gmdate("H:i:s", $duration);
        ?>
    </td>
    <td id="size:<?php echo $x['session']; ?>"><?php echo $x["sessionsize"]; ?></td>
    <td id="profile:<?php echo $x['session']; ?>"><?php echo $x["profileName"]; ?></td>
</tr>
<?php
    }
?>
        </tbody>
      </table>
    </form>
<div class="pages">
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
    echo '<a class="pages" href="del_sessions.php?page=1">&#171;</a> ';
}
if ($current_page > 1) {
    $previous_page = $current_page - 1;
    echo '<a class="pages" href="del_sessions.php?page=' . $previous_page . '">&#60;</a> ';
}
for ($page = $start; $page <= $end; $page++) {
    if ($number_of_result < $results_per_page) break;
    if ($page == $current_page) {
        echo '<a class="current-page" href="del_sessions.php?page=' . $page . '">' . $page . ' </a>';
    } else {
        echo '<a class="pages" href="del_sessions.php?page=' . $page . '">' . $page . ' </a>';
    }
}
if ($current_page < $total_pages) {
    $next_page = $current_page + 1;
    echo ' <a class="pages" href="del_sessions.php?page=' . $next_page . '">&#62;</a>';
}
if ($current_page < $total_pages) {
    echo ' <a class="pages" href="del_sessions.php?page=' . $total_pages . '">&#187;</a>';
}
?>
</div>
    <script>
    $(document).ready(()=> {
	$("#del-btn").on("click",(e)=> {
	    e.preventDefault();
	    var isChecked = $("input[type=checkbox]").is(":checked");
	    if (!isChecked) {
		 noSel();
	    }
	    else delSessions();
       });
	$(".table-del-merge-pid tr").click(function(e) {
	    if (e.target.type !== "checkbox") {
		$(":checkbox", this).trigger("click");
	    }
	});
     });

    function delSessions() {
     var dialogOpt = {
        btnClassSuccessText: "Yes",
        btnClassFail: "btn btn-info btn-sm",
        message : "Delete selected sessions?",
        onResolve: function(){
         $("#wait_layout").show();
         document.getElementById("formdel").submit();
        }
     };
     redDialog.make(dialogOpt);
    }

    function noSel() {
     var dialogOpt = {
        btnClassSuccessText: "OK",
        btnClassFail: "hidden",
        message : "No sessions selected."
     };
     redDialog.make(dialogOpt);
    }

    function toggle(source) {
     var checkboxes = document.querySelectorAll('input[type="checkbox"]');
     for (var i = 0; i < checkboxes.length; i++) {
         if (checkboxes[i] != source)
             checkboxes[i].checked = source.checked;
     }
    }
    </script>
  </body>
</html>
<?php
}
$db->close();
?>

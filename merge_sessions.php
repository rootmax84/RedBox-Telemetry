<?php
require_once('db.php');
require_once('get_sessions.php');
require_once('db_limits.php');

if (!isset($_SESSION)) { session_start(); }

if (isset($_POST["mergesession"])) {
    $mergesession = preg_replace('/\D/', '', $_POST['mergesession']);
}
elseif (isset($_GET["mergesession"])) {
    $mergesession = preg_replace('/\D/', '', $_GET['mergesession']);
}

if (!isset($_GET["page"])) {
    $page = 1;
} else {
    $page = $_GET["page"];
}

$sessionids = array();

$i=1;
$mergesess1 = "";
foreach ($_GET as $key => $value) {
    if ($key != "mergesession" && $key != "page") {
        ${'mergesess' . $i} = $key;
        array_push($sessionids, $key);
        $i = $i + 1;
    } else {
        array_push($sessionids, $value);
    }
}

if (isset($mergesession) && !empty($mergesession) && isset($mergesess1) && !empty($mergesess1)) {
    $qrystr = "SELECT MIN(timestart) as timestart, MAX(timeend) as timeend, MIN(session) as session, SUM(sessionsize) as sessionsize FROM $db_sessions_table WHERE session = ?";
    $i=1;
    while (isset(${'mergesess' . $i}) || !empty(${'mergesess' . $i})) {
        $qrystr = $qrystr . " OR session = '" . ${'mergesess' . $i} . "'";
        $i = $i + 1;
    }

    $mergerow = $db->execute_query($qrystr, [$mergesession])->fetch_assoc();
    $newsession = $mergerow['session'];
    $newtimestart = $mergerow['timestart'];
    $newtimeend = $mergerow['timeend'];
    $newsessionsize = $mergerow['sessionsize'];

    foreach ($sessionids as $value) {
        if ($value == $newsession) {
            $updatequery = "UPDATE $db_sessions_table SET timestart=$newtimestart, timeend=$newtimeend, sessionsize=$newsessionsize where session = ?";
            $db->execute_query($updatequery, [$newsession]);
        } else {
            $delquery = "DELETE FROM $db_sessions_table WHERE session = ?";
            $db->execute_query($delquery, [$value]);
            $updatequery = "UPDATE $db_table SET session=$newsession WHERE session = ?";
            $db->execute_query($updatequery, [$value]);
        }
    }
    //Show merged session
    header('Location: /?id=' . $newsession);
} elseif (isset($mergesession) && !empty($mergesession)) {
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
	    <a class="navbar-brand" href="/"><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
        </div>
      </div>
    </div>
    <form style="padding:50px 0 0;" action="merge_sessions.php" method="get" id="formmerge" >
      <input type="hidden" name="mergesession" value="<?php echo $mergesession; ?>">
      <div style="padding:10px; display:flex; justify-content:center;"><input class="btn btn-info btn-sm" type="submit" value="Merge Selected Sessions" id="merge-btn"></div>
      <table class="table table-del-merge-pid">
        <thead>
          <tr>
          <th></th>
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
 $sessqry = $db->query("SELECT timestart, timeend, session, profileName, sessionsize FROM $db_sessions_table ORDER BY session desc LIMIT " . $page_first_result . "," . $results_per_page);

    $i = 0;
    while ($x = $sessqry->fetch_array()) {
?>
          <tr>
            <td><input type="checkbox" name="<?php echo $x['session']; ?>" <?php if ($x['session'] == $mergesession) { echo "checked disabled"; } ?>></td>
            <td id="start:<?php echo $x['session']; ?>"><?php echo date($_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i", substr($x["timestart"], 0, -3)); ?></td>
            <td id="end:<?php echo $x['session']; ?>"><?php echo date($_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i", substr($x["timeend"], 0, -3)); ?></td>
            <td id="length:<?php echo $x['session']; ?>"><?php echo gmdate("H:i:s", intval(($x["timeend"] - $x["timestart"])/1000)); ?></td>
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
<?php
    //display the link of the pages in URL
    for($page = 1; $page <= $number_of_page; $page++) {
	if ($number_of_result < $results_per_page) break;
        if ((isset($_GET['page']) && $_GET['page'] == $page) || (!isset($_GET['page']) && $page == 1)) {
	    echo '<a class="current-page" href = "merge_sessions.php?mergesession='.$mergesession.'&page=' . $page . '">' . $page . ' </a>';
	}
	else {
	    echo '<a class="pages" href = "merge_sessions.php?mergesession='.$mergesession.'&page=' . $page . '">' . $page . ' </a>';
	}
    }
?>
</div>
    <script>
    window.addEventListener("load",function() {
       document.getElementById("merge-btn").addEventListener("click",function(e) {
         e.preventDefault();
         mergeSession();
       });
     });

    function mergeSession() {
     var dialogOpt = {
        message : "Merge selected session(s) with session <?php echo $mergesession; ?>?",
        onResolve: function(){
         $("#wait_layout").show();
         document.getElementById("formmerge").submit();
        }
     };
     redDialog.make(dialogOpt);
    }
    </script>
    <div id="status" style="padding:10px; background:#88C4FF; color:#000; font-weight:bold; font-size:12px; margin-bottom:10px; display:none; width:90%;"></div>
  </body>
</html>
<?php
}
else header('Location: /');
$db->close();
?>

<?php
require_once('db.php');
require_once('get_sessions.php');
require_once('db_limits.php');
global $delsession;

$delsession = filter_input(INPUT_POST, 'delsession', FILTER_SANITIZE_NUMBER_INT) 
            ?? filter_input(INPUT_GET, 'delsession', FILTER_SANITIZE_NUMBER_INT);

$page = $_GET["page"] ?? 1;

$sessionids = [];

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
    else {
        cache_flush();
        header('Location: del_sessions.php');
    }
} else {
    include("head.php");
?>
  <body>
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
<?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
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
    <form style="padding:50px 0 0;" action="del_sessions.php" method="get" id="formdel" >
      <input type="hidden" name="delsession" value="<?php echo $delsession; ?>">
      <div style="padding:10px; display:flex; justify-content:center"><button class="btn btn-info btn-sm" type="submit" id="del-btn" l10n="btn.del"></button></div>
      <table class="table table-del-merge-pid">
        <thead>
          <tr>
          <th><input type="checkbox" onclick="toggle(this);" /></th>
          <th l10n="s.table.start"></th>
          <th l10n="s.table.end"></th>
          <th l10n="s.table.duration"></th>
          <th l10n="s.table.datapoints"></th>
          <th l10n="s.table.profile"></th>
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
        $month_num = date('n', $start_timestamp);
        $month_key = 'month.' . strtolower(date('M', $start_timestamp));
        $translated_month = $translations[$lang][$month_key];
        $date = date($_COOKIE['timeformat'] == "12" ? "d, Y h:ia" : "d, Y H:i", $start_timestamp);
        echo $translated_month . ' ' . $date;
        ?>
    </td>
    <td id="end:<?php echo $x['session']; ?>">
        <?php 
        $end_timestamp = intval(substr($x["timeend"], 0, -3));
        $month_num = date('n', $end_timestamp);
        $month_key = 'month.' . strtolower(date('M', $end_timestamp));
        $translated_month = $translations[$lang][$month_key];
        $date = date($_COOKIE['timeformat'] == "12" ? "d, Y h:ia" : "d, Y H:i", $end_timestamp);
        echo $translated_month . ' ' . $date;
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
    <?php
        if (!$number_of_result) {
    ?>
        <h3 style='text-align:center' l10n="no.sess"></h>
        <script>
            document.getElementById('del-btn').disabled = true;
            document.querySelector('input[type="checkbox"]').disabled = true;
        </script>
    <?php } ?>
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
	    let isChecked = $("input[type=checkbox]").is(":checked");
	    if (!isChecked) {
		 noSel();
	    }
	    else delSessions();
       });
	sortMergeDel();
     });

    function delSessions() {
     let dialogOpt = {
        title: localization.key['dialog.confirm'],
        btnClassSuccessText: localization.key['btn.yes'],
        btnClassFailText: localization.key['btn.no'],
        btnClassFail: "btn btn-info btn-sm",
        message : localization.key['dialog.del.sessions'],
        onResolve: function(){
         $("#wait_layout").show();
         document.getElementById("formdel").submit();
        }
     };
     redDialog.make(dialogOpt);
    }

    function noSel() {
     let dialogOpt = {
        title: localization.key['dialog.confirm'],
        btnClassSuccessText: "OK",
        btnClassFail: "hidden",
        message : localization.key['dialog.no.select']
     };
     redDialog.make(dialogOpt);
    }

    function toggle(source) {
     let checkboxes = document.querySelectorAll('input[type="checkbox"]');
     for (let i = 0; i < checkboxes.length; i++) {
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

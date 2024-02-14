<?php

require_once('db.php');
require_once('auth_user.php');
require_once('creds.php');
require_once('db_limits.php');

$keyqry = $db->query("SELECT id,description,units,populated,stream FROM ".$db_pids_table." WHERE id != 'kff1005' AND id != 'kff1006' AND id != 'kff1007' ORDER BY description");
$i = 0;
while ($x = $keyqry->fetch_array()) {
		$keydata[$i] = array("id"=>$x[0], "description"=>$x[1], "units"=>$x[2], "populated"=>$x[3], "stream"=>$x[4]);
		$i++;
}
$db->close();

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include("head.php");?>
<script>
$(document).ready(function() {
    var message_status;
    var timeout;
      $(function(){
        message_status = $("#status");

        $("td[contenteditable=true]").blur(function(){
          var field_pid = $(this).attr("id");
          var value = $(this).text();
          $.post('pid_commit.php' , field_pid + "=" + value, function(data) {
            if(data != '') {
              message_status.text(data);
            }
          });
        });
	
        $("input[contenteditable=true]").blur(function(){
          var field_pid = $(this).attr("id");
          var value = $(this).is(":checked");
          $.post('pid_commit.php' , field_pid + "=" + value, function(data) {
            if(data != '') {
              message_status.text(data);
            }
          });
        });
	
        $("select[contenteditable=true]").blur(function(){
          var field_pid = $(this).attr("id");
          var value = $(this).val();
          $.post('pid_commit.php' , field_pid + "=" + value, function(data) {
            if(data != '') {
              message_status.text(data);
            }
          });
        });
      });

     $("#btn-apply").on('click', function(e) {
      if (!message_status.text().length)
       message_status.text("Updated");
      clearTimeout(timeout);
      message_status.show();
      timeout = setTimeout(()=>{message_status.hide()},3000);
     });
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
    <form style="margin-top:50px;" action="javascript:void(0);">
      <div style="padding:10px; display:flex; justify-content:center;"><input class="btn btn-info btn-sm" type="submit" value="Apply" id="btn-apply"></div>
    <table class="table table-del-merge-pid">
      <thead>
        <tr>
        <th>ID</th>
        <th>Description</th>
        <th>Units</th>
        <th>In Chart?</th>
        <th>In Stream?</th>
        </tr>
      </thead>
      <tbody>
<?php $i = 1; ?>
<?php foreach ($keydata as $keycol) { ?>
        <tr<?php if ($i & 1) echo " class=\"odd\"";?>>
          <td id="id:<?php echo $keycol['id']; ?>"><?php echo $keycol['id']; ?></td>
          <td id="description:<?php echo $keycol['id']; ?>" contenteditable="true"><?php echo $keycol['description']; ?></td>
          <td id="units:<?php echo $keycol['id']; ?>" contenteditable="true"><?php echo $keycol['units']; ?></td>
          <td><input type="checkbox" id="populated:<?php echo $keycol['id']; ?>" contenteditable="true"<?php if ( $keycol['populated'] ) echo " CHECKED"; ?>></td>
          <td><input type="checkbox" id="stream:<?php echo $keycol['id']; ?>" contenteditable="true"<?php if ( $keycol['stream'] ) echo " CHECKED"; ?>></td>
        </tr>
<?php   $i = $i + 1; ?>
<?php } ?>
      </tbody>
    </table>
  </form>
<br>
    <div id="status"></div>
  </body>
</html>
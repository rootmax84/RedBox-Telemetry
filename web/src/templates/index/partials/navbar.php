<div class="navbar navbar-default navbar-fixed-top navbar-inverse">
  <div class="fetch-data"></div>
  <div class="new-session"><a href='.' l10n='sess.new'></a></div>
  <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
    <div class="storage-usage-img"></div>
  <?php } if (!isset($_SESSION['admin']) && isset($session_id) && !empty($session_id)) { ?>
  <div class="share-img" onClick="shareSession()" <?php if ($limit < 0) { ?> style="right:40px" <?php } ?>></div>
  <div class="chart-fill-toggle" onClick="chartToggle()" style="right:<?php echo ($limit < 0) ? '70px' : '100px'; ?>"></div>
  <div class="favorite" onClick="addToFavorite()" style="right:<?php echo ($limit < 0) ? '100px' : '130px'; ?>"></div>
  <div class="live" style="right:<?php echo ($limit < 0) ? '130px' : '160px'; ?>"></div>
  <?php } elseif (!isset($_SESSION['admin'])) { ?>
  <a href="users_remote.php" class="remote-img" style="right:<?php echo ($limit < 0) ? '40px' : '70px'; ?>"></a>
  <?php } ?>
  <div class="container">
    <div id="theme-switch"></div>
    <div class="navbar-header">
      <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a>
    </div>
  </div>
</div>

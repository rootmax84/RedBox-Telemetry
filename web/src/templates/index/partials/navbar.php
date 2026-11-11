<div class="navbar navbar-default navbar-fixed-top navbar-inverse">
  <div class="fetch-data"></div>
  <div class="new-session"><a href='/' l10n='sess.new'></a></div>
  <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
    <div class="storage-usage-img"></div>
  <?php } if (!isset($_SESSION['admin']) && isset($session_id) && !empty($session_id)) { ?>
  <div class="share-img" onClick="shareSession()" <?php if ($limit < 0) { ?> style="right:40px" <?php } ?>></div>
  <div class="chart-fill-toggle" onClick="chartToggle()" style="right:<?php echo ($limit < 0) ? '70px' : '100px'; ?>"></div>
  <div class="favorite" onClick="addToFavorite()" style="right:<?php echo ($limit < 0) ? '100px' : '130px'; ?>"></div>
  <button type="button" class="stream-power" onclick="dataToggle()" aria-pressed="false" aria-controls="stream-panel"
    title="<?php echo escapeHtml($translations[$lang]['stream.start']); ?>" aria-label="<?php echo escapeHtml($translations[$lang]['stream.start']); ?>"
    style="right:<?php echo ($limit < 0) ? '130px' : '160px'; ?>">
    <svg aria-hidden="true" viewBox="0 0 24 24" width="24" height="24"><path d="M5 4v16l15-8z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
  </button>
  <div class="live" style="right:<?php echo ($limit < 0) ? '160px' : '190px'; ?>"></div>
  <?php } elseif (!isset($_SESSION['admin'])) { ?>
  <a href="/settings/remote" class="remote-img" style="right:<?php echo ($limit < 0) ? '40px' : '70px'; ?>"></a>
  <?php } ?>
  <div class="container">
    <div id="theme-switch"></div>
    <div class="navbar-header">
      <a class="navbar-brand" href="/"><div id="redhead">RedB<img src="/static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a>
    </div>
  </div>
</div>

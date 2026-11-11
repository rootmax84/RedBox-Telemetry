<?php
/** @var array $data */
extract($data, EXTR_SKIP);
?>
<body>
<?php include __DIR__ . '/partials/flot-scripts.php'; ?>
<?php include __DIR__ . '/partials/navbar.php'; ?>
<?php if (!isset($_SESSION['admin']) && !empty($session_id)) { ?>
<div id="session-initial-loader" role="status" aria-live="polite" aria-label="Loading session">
    <span class="session-initial-spinner" aria-hidden="true"></span>
</div>
<?php } ?>
<?php include __DIR__ . '/partials/menu.php'; ?>

<div id="right-container" class="col-md-auto col-xs-12<?php if (!isset($_SESSION['admin']) && !empty($session_id)) echo ' session-page'; ?>">
<?php if (!isset($_SESSION['admin']) && !empty($session_id)) { ?>
<?php include __DIR__ . '/partials/filters.php'; ?>
<div class="session-workspace">
  <div class="session-main">
    <?php include __DIR__ . '/partials/plot-data.php'; ?>
    <?php include __DIR__ . '/partials/chart-map.php'; ?>
  </div>
  <div class="session-summary">
    <?php include __DIR__ . '/partials/session-summary.php'; ?>
  </div>
  <div id="stream-resizer" class="stream-resizer" role="separator" tabindex="0"
       aria-orientation="vertical" aria-controls="stream-panel" aria-valuemin="330" aria-valuemax="430"
       aria-label="<?php echo escapeHtml($translations[$lang]['stream.resize']); ?>"
       title="<?php echo escapeHtml($translations[$lang]['stream.resize']); ?>"></div>
  <?php include __DIR__ . '/partials/live-data.php'; ?>
</div>
<?php } ?>

<?php if (isset($_SESSION['admin'])) {
    include __DIR__ . '/partials/admin.php';
} elseif (isset($session_id) && !empty($session_id)) { ?>
    <p class="copyright"></p>
<?php } else {
    include __DIR__ . '/partials/no-session.php';
} ?>
      </div>

<?php include __DIR__ . '/partials/scripts.php'; ?>
</body>
</html>

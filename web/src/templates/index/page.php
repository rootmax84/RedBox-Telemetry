<?php
/** @var array $data */
extract($data, EXTR_SKIP);
?>
<body>
<?php include __DIR__ . '/partials/flot-scripts.php'; ?>
<?php include __DIR__ . '/partials/navbar.php'; ?>
<?php include __DIR__ . '/partials/menu.php'; ?>

<div id="right-container" class="col-md-auto col-xs-12">
<?php if (!isset($_SESSION['admin']) && isset($session_id) && !empty($session_id)) {
    include __DIR__ . '/partials/filters.php';
    include __DIR__ . '/partials/plot-data.php';
    include __DIR__ . '/partials/chart-map.php';
    include __DIR__ . '/partials/live-data.php';
} ?>

<?php if (isset($_SESSION['admin'])) {
    include __DIR__ . '/partials/admin.php';
} elseif (isset($session_id) && !empty($session_id)) { ?>
    <p class="copyright"></p>
</div>
<?php } else {
    include __DIR__ . '/partials/no-session.php';
} ?>
      </div>
    </div>

<?php include __DIR__ . '/partials/scripts.php'; ?>
</body>
</html>

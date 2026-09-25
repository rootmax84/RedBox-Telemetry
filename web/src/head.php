<?php
require_once __DIR__ . '/helpers.php';

// Extract cache-busting timestamp for localStorage key
preg_match('/\d+/', version_url('translations.php'), $l10n_match);
$l10n_time = $l10n_match[0] ?? '';

// Username for document.title (skip admin)
$head_username = (isset($username) && isset($admin) && $username != $admin)
    ? $username
    : '';
?>
<!DOCTYPE html>
<html>
<head>
<script src="<?php echo version_url('static/js/localization.js'); ?>"></script>
<meta property="og:title" content="RedBox Telemetry">
<meta property="og:type" content="website">
<meta property="og:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/static/img/android-chrome-192x192.png">
<meta property="og:description" content="Go hard!">
<link rel="apple-touch-icon" sizes="180x180" href="static/img/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="static/img/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="static/img/favicon-16x16.png">
<link rel="manifest" href="static/img/manifest.json">
<link rel="mask-icon" href="static/img/safari-pinned-tab.svg" color="#5bbad5">
<meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
<meta name="csrf-token-expiry" content="<?php echo $_SESSION['csrf_token_time'] + 3300; ?>">
<meta name="theme-color" content="#1a1a1a">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=0.8">
<title>RedBox Telemetry</title>
<meta name="description" content="RedBox Telemetry">
<link rel="stylesheet" href="<?php echo version_url('static/css/bootstrap.min.css'); ?>">
<link rel="stylesheet" href="<?php echo version_url('static/css/choices.min.css'); ?>">
<link rel="stylesheet" href="<?php echo version_url('static/css/main.css'); ?>">
<link rel="stylesheet" href="<?php echo version_url('static/css/remote.css'); ?>">
<link rel="stylesheet" href="<?php echo version_url('static/css/Control.FullScreen.css'); ?>">
<?php if (isset($_SESSION['torque_user']) || isset($_SESSION['share'])) {?>
<script>let darkCssUrl = "<?php echo version_url('static/css/dark.css'); ?>";</script>
<script src="<?php echo version_url('static/js/theme.js'); ?>"></script>
<?php } if (isset($_SESSION['admin'])) {?>
<link rel="stylesheet" href="<?php echo version_url('static/css/admin.css'); ?>">
<script src="<?php echo version_url('static/js/admin.js'); ?>"></script>
<?php } ?>
<script src="<?php echo version_url('static/js/jquery.min.js'); ?>"></script>
<script src="<?php echo version_url('static/js/js.cookie.min.js'); ?>"></script>
<script src="<?php echo version_url('static/js/jquery-ui.min.js'); ?>"></script>
<script src="<?php echo version_url('static/js/jquery-ui.touch-punch.min.js'); ?>"></script>
<script src="<?php echo version_url('static/js/jquery.peity.min.js'); ?>"></script>
<script src="<?php echo version_url('static/js/choices.min.js'); ?>"></script>
<link rel="stylesheet" href="<?php echo version_url('static/css/leaflet.css'); ?>">
<script src="<?php echo version_url('static/js/leaflet.js'); ?>"></script>
<script src="<?php echo version_url('static/js/leaflet.hotline.min.js'); ?>"></script>
<script src="<?php echo version_url('static/js/coords.js'); ?>"></script>
<script src="<?php echo version_url('static/js/nosleep.js'); ?>"></script>
<script>
window.HEAD_CONFIG = <?php echo json_encode([
    'l10nTime'   => $l10n_time,
    'username'   => $head_username,
    'torqueUser' => isset($_SESSION['torque_user']),
    'authPoll'   => !file_exists('maintenance')
                    && isset($_SESSION['torque_user'])
                    && !isset($_SESSION['admin']),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="<?php echo version_url('static/js/head.js'); ?>"></script>
<script src="<?php echo version_url('static/js/helpers.js'); ?>"></script>
<?php if (isset($_SESSION['torque_user'])) {?>
<a id="top-btn"></a>
<div class="wait_out" id="offline_layout" style="display: none;">
 <div class="wait_in">
  <svg xmlns="http://www.w3.org/2000/svg" id="conn_lost" style="transform: scale(.4); position:fixed;" width="100%" height="100%" viewBox="0 0 24 24">
   <path fill="darkorange" d="m2.5 3.77l4.37 4.37L5 10v3H3v-3H1v8h2v-3h2v3h3l2 2h8v-.73l3.23 3.23l1.27-1.28L3.78 2.5zM16 18h-5l-2-2H7v-5l1-1h.73L16 17.27zm7-9v10h-.18L16 12.18V10h-2.18l-6-6H15v2h-3v2h6v4h2V9z"/>
  </svg>
 </div>
</div>
<div class="wait_out" id="wait_layout" style="display: block;">
 <div class="wait_in">
  <svg xmlns="http://www.w3.org/2000/svg" style="transform: scale(.3); color:lightgray; position:fixed;" width="100%" height="100%" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a1 1 0 1 0 2 0a1 1 0 1 0-2 0m7 0a1 1 0 1 0 2 0a1 1 0 1 0-2 0m7 0a1 1 0 1 0 2 0a1 1 0 1 0-2 0"/></svg>
 </div>
</div>
</head>
<?php }
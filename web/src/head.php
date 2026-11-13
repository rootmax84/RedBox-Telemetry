<?php
require_once __DIR__ . '/helpers.php';

// Extract cache-busting timestamp for localStorage key
preg_match('/\d+/', version_url('translations.php'), $l10n_match);
$l10n_time = $l10n_match[0] ?? '';

// Username for document.title (skip admin)
$head_username = (isset($username) && isset($admin) && $username != $admin)
    ? $username
    : '';
// The admin page keeps the visible username blank, but still needs its own theme key.
$head_theme_username = isset($_SESSION['admin'])
    ? (string)($_SESSION['torque_user'] ?? ($admin ?? ''))
    : $head_username;
$head_host = htmlspecialchars($_SERVER['HTTP_HOST'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
<script>
document.documentElement.classList.add('choices-pending');
<?php if (!isset($_SESSION['admin']) && !empty($session_id) ) { ?>
document.documentElement.classList.add('session-initial-loading');
<?php } ?>
</script>
<script src="<?php echo version_url('/static/js/localization.js'); ?>"></script>
<meta property="og:title" content="RedBox Telemetry">
<meta property="og:type" content="website">
<meta property="og:image" content="https://<?php echo $head_host; ?>/static/img/android-chrome-192x192.png">
<meta property="og:description" content="Go hard!">
<meta property="og:site_name" content="RedBox Automotive">
<link rel="apple-touch-icon" sizes="180x180" href="/static/img/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/static/img/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/static/img/favicon-16x16.png">
<link rel="manifest" href="/static/img/manifest.json">
<link rel="mask-icon" href="/static/img/safari-pinned-tab.svg" color="#5bbad5">
<meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
<meta name="csrf-token-expiry" content="<?php echo $_SESSION['csrf_token_time'] + 3300; ?>">
<meta name="theme-color" content="#1a1a1a">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=0.8">
<title>RedBox Telemetry</title>
<meta name="description" content="RedBox Telemetry">
<link rel="stylesheet" href="<?php echo escapeHtml(version_url('/static/css/bootstrap.min.css')); ?>">
<link rel="stylesheet" href="<?php echo escapeHtml(version_url('/static/css/choices.min.css')); ?>">
<link rel="stylesheet" href="<?php echo escapeHtml(version_url('/static/css/main.css')); ?>">
<link rel="stylesheet" href="<?php echo escapeHtml(version_url('/static/css/remote.css')); ?>">
<link rel="stylesheet" href="<?php echo escapeHtml(version_url('/static/css/Control.FullScreen.css')); ?>">
<?php if (isset($_SESSION['torque_user']) || isset($_SESSION['share']) || isset($_SESSION['admin'])) {?>
<script>let darkCssUrl = <?php echo js_string(version_url('/static/css/dark.css')); ?>;</script>
<script src="<?php echo version_url('/static/js/theme.js'); ?>"></script>
<?php } if (isset($_SESSION['admin'])) {?>
<link rel="stylesheet" href="<?php echo escapeHtml(version_url('/static/css/admin.css')); ?>">
<script src="<?php echo version_url('/static/js/admin.js'); ?>"></script>
<?php } ?>
<script src="<?php echo version_url('/static/js/jquery.min.js'); ?>"></script>
<script src="<?php echo version_url('/static/js/js.cookie.min.js'); ?>"></script>
<script src="<?php echo version_url('/static/js/jquery-ui.min.js'); ?>"></script>
<script src="<?php echo version_url('/static/js/jquery-ui.touch-punch.min.js'); ?>"></script>
<script src="<?php echo version_url('/static/js/jquery.peity.min.js'); ?>"></script>
<script src="<?php echo version_url('/static/js/choices.min.js'); ?>"></script>
<link rel="stylesheet" href="<?php echo escapeHtml(version_url('/static/css/leaflet.css')); ?>">
<script src="<?php echo version_url('/static/js/leaflet.js'); ?>"></script>
<script src="<?php echo version_url('/static/js/leaflet.hotline.min.js'); ?>"></script>
<script src="<?php echo version_url('/static/js/coords.js'); ?>"></script>
<script src="<?php echo version_url('/static/js/nosleep.js'); ?>"></script>
<script>
window.HEAD_CONFIG = <?php echo json_encode([
    'l10nTime'   => $l10n_time,
    'username'   => $head_username,
    'themeUsername' => $head_theme_username,
    'torqueUser' => isset($_SESSION['torque_user']),
    'authPoll'   => !is_maintenance()
                    && isset($_SESSION['torque_user']),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="<?php echo version_url('/static/js/head.js'); ?>"></script>
<script src="<?php echo version_url('/static/js/helpers.js'); ?>"></script>
<?php if (isset($_SESSION['torque_user']) && empty($mcp_page_body_overlays)) {
    require __DIR__ . '/templates/loading_overlays.php';
} ?>
</head>

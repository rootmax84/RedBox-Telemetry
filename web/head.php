<!DOCTYPE html>
<html>
<head>
<?php require_once('token_functions.php');

function version_url($url) {
    // If file exists use it modify time
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . parse_url($url, PHP_URL_PATH);
    if (file_exists($file_path)) {
        $timestamp = filemtime($file_path);
    } else {
        // otherwise use container start time or current time
        if (file_exists('/proc/1/stat')) {
            $timestamp = filemtime('/proc/1/stat'); // Container start timr
        } else {
            $timestamp = time(); // Current time
        }
    }

    // Add v param to url
    return $url . (strpos($url, '?') !== false ? '&' : '?') . 'v=' . $timestamp;
}
 ?>
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
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=0.8">
<title>RedBox Telemetry</title>
<meta name="description" content="RedBox Telemetry">
<link rel="stylesheet" href="<?php echo version_url('static/css/bootstrap.min.css'); ?>">
<link rel="stylesheet" href="<?php echo version_url('static/css/choices.min.css'); ?>">
<link rel="stylesheet" href="<?php echo version_url('static/css/main.css'); ?>">
<link rel="stylesheet" href="<?php echo version_url('static/css/Control.FullScreen.css'); ?>">
<?php if (isset($_SESSION['torque_user'])) {?>
<script>let darkCssUrl = "<?php echo version_url('static/css/dark.css'); ?>";</script>
<script src="<?php echo version_url('static/js/theme.js'); ?>"></script>
<?php } if (isset($_SESSION['admin'])) {?>
<link rel="stylesheet" href="<?php echo version_url('static/css/admin.css'); ?>">
<script src="<?php echo version_url('static/js/admin.js'); ?>"></script>
<?php } ?>
<script src="<?php echo version_url('static/js/jquery.min.js'); ?>"></script>
<script src="<?php echo version_url('static/js/jquery.cookie.min.js'); ?>"></script>
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
    const l10n_time = "<?php preg_match('/\d+/', version_url('translations.php'), $m); echo $m[0]; ?>";
    const l10n_saved = localStorage.getItem('l10n_time');

    $(document).ready(function() {

     localization = new Localization();
     if (l10n_saved !== l10n_time) {
        localization.clearCache();
        localization.loadTranslations();
        localStorage.setItem('l10n_time', l10n_time);
     }
     fetch(`translations.php?lang=${lang}`);

     const visitortimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
     fetch("timezone.php?time=" + visitortimezone);
      $("#theme-switch").click( function() {
       toggle_dark();
     });
      let btn = $('#top-btn');
      $(window).scroll(function() {
       if ($(window).scrollTop() > 1000) {
         btn.addClass('show');
       } else {
         btn.removeClass('show');
       }
     });

     btn.on('click', function(e) {
       e.preventDefault();
       $('html, body').animate({scrollTop:0}, 500);
     });

    $(".navbar-brand").click(()=> {
        $("#wait_layout").show();
    });

    $("#wait_layout").hide();
    <?php if (!file_exists('maintenance') && isset($_SESSION['torque_user']) && !isset($_SESSION['admin'])) { ?> auth(); <?php } ?>
    });

function addCsrfTokenToForms() {
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    document.querySelectorAll('form').forEach(form => {
        let input = form.querySelector('input[name="csrf_token"]');
        if (input) {
            input.value = token;
        } else {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = token;
            form.appendChild(input);
        }
    });
}

<?php if (isset($_SESSION['torque_user'])) {?>
function checkCSRFToken() {
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    const expiryMeta = document.querySelector('meta[name="csrf-token-expiry"]');
    const currentTime = Math.floor(Date.now() / 1000);

    if (tokenMeta && expiryMeta) {
        const expiryTime = parseInt(expiryMeta.content);

        if (currentTime > expiryTime + 60) {
            fetch('auth.php?update-csrf-token', { method: 'GET', credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    tokenMeta.content = data.token;
                    expiryMeta.content = data.expiry;
                    addCsrfTokenToForms();
                    console.log('CSRF token updated');
                })
                .catch(error => console.error('Error updating CSRF token:', error));
        }
    }
}
setInterval(checkCSRFToken, 60000);
<?php } ?>

document.addEventListener('DOMContentLoaded', addCsrfTokenToForms);

function auth() {
 setTimeout(auth,5000);
 fetch("auth.php", {method: "HEAD"})
    .then(resp => {
        switch(resp.status) {
            case 200:
            $("#offline_layout").hide();
            break;
            case 401:
            location.href='.?logout=true';
            break;
            case 307:
            location.href='maintenance.php';
            break;
        }
    }).catch(err => {$("#offline_layout").show()});
}

const username = "<?php if (isset($username) && $username != $admin) echo $username; ?>";
if (username.trim() !== "") {
    document.title += ` - ${username}`;
}
</script>
<script src="<?php echo version_url('static/js/helpers.js'); ?>"></script>
<?php if (isset($_SESSION['torque_user'])) {?>
<a id="top-btn"></a>
<div class="wait_out" id="offline_layout" style="display: none;">
 <div class="wait_in">
  <svg xmlns="http://www.w3.org/2000/svg" id="conn_lost" style="transform: scale(.4); position:fixed;" width="100%" height="100%" viewBox="0 0 24 24">
   <path fill="darkorange" d="m2.5 3.77l4.37 4.37L5 10v3H3v-3H1v8h2v-3h2v3h3l2 2h8v-.73l3.23 3.23l1.27-1.28L3.78 2.5zM16 18h-5l-2-2H7v-5l1-1h.73L16 17.27zm7-9v10h-.18L16 12.18V10h-2.18l-6-6H15v2h-3v2h6v4h2V9z"/>
    <text x="0" y="23.7" fill="darkorange" style="font-size: 1.78px;">Connection bitten by BMW :(</text>
  </svg>
 </div>
</div>
<div class="wait_out" id="wait_layout" style="display: block;">
 <div class="wait_in">
  <svg xmlns="http://www.w3.org/2000/svg" style="transform: scale(.3); color:lightgray; position:fixed;" width="100%" height="100%" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a1 1 0 1 0 2 0a1 1 0 1 0-2 0m7 0a1 1 0 1 0 2 0a1 1 0 1 0-2 0m7 0a1 1 0 1 0 2 0a1 1 0 1 0-2 0"/></svg>
 </div>
</div>
</head>
<?php } ?>

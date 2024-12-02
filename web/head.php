<!DOCTYPE html>
<html lang="en">
<head>
<script async src="static/js/localization.js"></script>
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
<link rel="stylesheet" href="static/css/bootstrap.min.css">
<link rel="stylesheet" href="static/css/chosen.min.css">
<link rel="stylesheet" href="static/css/torque.css">
<link rel="stylesheet" href="static/css/Control.FullScreen.css">
<?php require_once('token_functions.php'); if (isset($_SESSION['torque_user'])) {?>
<script src="static/js/theme.js"></script>
<?php } if (isset($_SESSION['admin'])) {?>
<link rel="stylesheet" href="static/css/admin.css">
<?php } ?>
<script src="static/js/jquery.min.js"></script>
<script src="static/js/jquery.cookie.min.js"></script>
<script src="static/js/jquery-ui.min.js"></script>
<script src="static/js/jquery-ui.touch-punch.min.js"></script>
<script src="static/js/jquery.peity.min.js"></script>
<script src="static/js/chosen.jquery.min.js"></script>
<link rel="stylesheet" href="static/css/leaflet.css">
<link rel="stylesheet" href="static/css/locate.css">
<script src="static/js/leaflet.js"></script>
<script src="static/js/coords.js"></script>
<script src="static/js/locate.js"></script>
<script src="static/js/nosleep.js"></script>
<script>
    $(document).ready(function() {
     localization = new Localization();
     fetch(`translations.php?lang=${lang}`);
     const visitortimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
     fetch("timezone.php?time=" + visitortimezone);
      $("#theme-switch").click( function() {
       toggle_dark();
     });
      var btn = $('#top-btn');
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
<script src="static/js/helpers.js"></script>
</head>
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
<?php } ?>

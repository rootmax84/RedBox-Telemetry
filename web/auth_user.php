<?php
require_once 'auth_functions.php';
require_once 'helpers.php';
if (!isset($_SESSION)) { session_start(); }

$current_script = basename($_SERVER['SCRIPT_FILENAME']);
$csrf_exempt_scripts = ['get_token.php', 'ul.php', 'adminer.php']; //CSRF exclude

$logged_in = isset($_SESSION['torque_logged_in']) && $_SESSION['torque_logged_in'];

if(isset($_POST) && !empty($_POST)){
    if (!in_array($current_script, $csrf_exempt_scripts)) {
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            header('Location: catch.php?c=csrffailed');
            exit;
        }
    }

    if (!$logged_in) {
        perform_migration();
        if (!check_login_attempts(get_user())) {
            header('Location: catch.php?c=toomanyattempts');
            exit;
        }
        if (auth_user()) {
            perform_user_migration();
            $logged_in = true;
        } else {
            header('Location: catch.php?c=loginfailed');
            exit;
        }
    }
}

$_SESSION['torque_logged_in'] = $logged_in;

if (!$logged_in) {
    setcookie("stream", "");
    include 'head.php';
    ?>
    <body style="display:flex; justify-content:center; align-items:center; height:100vh">
    <div class="login login-form" id="login-form">
        <div class="login-lang" id="lang-switch">
          <div class="selected-lang" id="selected-lang"></div>
          <ul class="lang-options" id="lang-options">
            <li data-value="en">English</li>
            <li data-value="ru">Русский</li>
            <li data-value="es">Español</li>
            <li data-value="de">Deutsch</li>
          </ul>
        </div>
        <div style="font-weight:bold; color:#961911; text-align:center; width:100%; font-size:16px; letter-spacing:1.5px; text-shadow: none">RedB<img src="static/img/logo.svg" alt style="height:10px; width:10px; margin-right:1px">x Telemetry</div>
        <h6 style="text-align:center; margin-bottom:20px" l10n="login.label"></h6>
        <form method="post" class="form-group" action=".">
            <input class="form-control" type="text" name="user" value="" maxlength="32" l10n-placeholder="login.login" autocomplete="off" required autofocus><br>
            <input class="form-control" type="password" name="pass" value="" maxlength="64" l10n-placeholder="login.pwd" autocomplete="off" required><br>
            <button id="login-btn" class="btn btn-info btn-sm" type="submit" name="Login" style="width:100%; height:35px" l10n="login.signin"></button>
            <div style="text-align:center; margin:15px 0 -20px; font-size:12px; opacity:.6"><a href="https://github.com/rootmax84/RedBox-Telemetry" target="_blank" l10n="login.github"></a></div>
        </form>
    </div>
    <div class="login-background"></div>
    <script>
        $(document).ready(function(){
            $("#login-form").css({"opacity":"1"});
            $(".form-group").submit((e)=>{
                $("#login-btn").prop('disabled', true);
                $("#login-btn").html('<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"><circle cx="18" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin=".67" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle><circle cx="12" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin=".33" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle><circle cx="6" cy="12" r="0" fill="currentColor"><animate attributeName="r" begin="0" calcMode="spline" dur="1.5s" keySplines="0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8;0.2 0.2 0.4 0.8" repeatCount="indefinite" values="0;2;0;0"/></circle></svg>');
            });

          const langSwitch = document.getElementById('lang-switch');
          const selectedLang = document.getElementById('selected-lang');
          const langOptions = document.getElementById('lang-options');

          function closeDropdown() {
            langOptions.classList.remove('show');
          }

          selectedLang.addEventListener('click', function(event) {
            event.stopPropagation();
            if (langOptions.classList.contains('show')) {
              closeDropdown();
            } else {
              langOptions.classList.add('show');
            }
          });

          langOptions.querySelectorAll('li').forEach(option => {
            option.addEventListener('click', function() {
              const selectedValue = this.getAttribute('data-value');
              const selectedText = this.textContent;
              closeDropdown();

              fetch(`translations.php?lang=${selectedValue}`)
                .then(() => {
                  return localization.setLang(selectedValue);
                })
                .catch(error => {
                  console.error('Error:', error);
                });
            });
          });

          document.addEventListener('click', function(event) {
            if (!langSwitch.contains(event.target)) {
              closeDropdown();
            }
          });
        });
    </script>
    </body>
    </html>
    <?php
    exit;
}

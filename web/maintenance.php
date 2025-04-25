<?php
require_once('creds.php');
include_once('translations.php');

if (isset($_SESSION['admin'])) {
    $maintenanceFile = 'maintenance';

    if (isset($_GET['enable'])) {
        if (!file_exists($maintenanceFile)) {
            touch($maintenanceFile);
        }
    } elseif (isset($_GET['disable'])) {
        if (file_exists($maintenanceFile)) {
            unlink($maintenanceFile);
        }
    } elseif (isset($_GET['mode'])) {
        die(file_exists($maintenanceFile) ? $translations[$_COOKIE['lang']]['dialog.maintenance.on'] : $translations[$_COOKIE['lang']]['dialog.maintenance.off']);
    }
}

if (file_exists('maintenance') && $username !== $admin) {
    http_response_code(423);
    header("Refresh:30; url=maintenance.php");
include("head.php");
?>
    <body>
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse" style="position:relative">
            <div class="container">
                <div id="theme-switch"></div>
                <div class="navbar-header">
		<a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
                </div>
              </div>
            </div>
            <div class="login" style="width:400px; text-align:center; margin: 50px auto">
             <h2 l10n="maintenance.title"></h2>
             <h4 l10n="maintenance.text"></h4>
	     <div><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><defs><symbol id="lineMdCogLoop0"><path fill="none" stroke-width="2" d="M15.24 6.37C15.65 6.6 16.04 6.88 16.38 7.2C16.6 7.4 16.8 7.61 16.99 7.83C17.46 8.4 17.85 9.05 18.11 9.77C18.2 10.03 18.28 10.31 18.35 10.59C18.45 11.04 18.5 11.52 18.5 12"><animate fill="freeze" attributeName="d" begin="0.8s" dur="0.2s" values="M15.24 6.37C15.65 6.6 16.04 6.88 16.38 7.2C16.6 7.4 16.8 7.61 16.99 7.83C17.46 8.4 17.85 9.05 18.11 9.77C18.2 10.03 18.28 10.31 18.35 10.59C18.45 11.04 18.5 11.52 18.5 12;M15.24 6.37C15.65 6.6 16.04 6.88 16.38 7.2C16.38 7.2 19 6.12 19.01 6.14C19.01 6.14 20.57 8.84 20.57 8.84C20.58 8.87 18.35 10.59 18.35 10.59C18.45 11.04 18.5 11.52 18.5 12"/></path></symbol></defs><g fill="none" stroke="currentColor" stroke-width="2"><g stroke-linecap="round" stroke-linejoin="round"><path stroke-dasharray="42" stroke-dashoffset="42" d="M12 5.5C15.59 5.5 18.5 8.41 18.5 12C18.5 15.59 15.59 18.5 12 18.5C8.41 18.5 5.5 15.59 5.5 12C5.5 8.41 8.41 5.5 12 5.5z" opacity="0"><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.2s" dur="0.5s" values="42;0"/><set attributeName="opacity" begin="0.2s" to="1"/><set attributeName="opacity" begin="0.7s" to="0"/></path><path stroke-dasharray="20" stroke-dashoffset="20" d="M12 9C13.66 9 15 10.34 15 12C15 13.66 13.66 15 12 15C10.34 15 9 13.66 9 12C9 10.34 10.34 9 12 9z"><animate fill="freeze" attributeName="stroke-dashoffset" dur="0.2s" values="20;0"/></path></g><g opacity="0"><use href="#lineMdCogLoop0"/><use href="#lineMdCogLoop0" transform="rotate(60 12 12)"/><use href="#lineMdCogLoop0" transform="rotate(120 12 12)"/><use href="#lineMdCogLoop0" transform="rotate(180 12 12)"/><use href="#lineMdCogLoop0" transform="rotate(240 12 12)"/><use href="#lineMdCogLoop0" transform="rotate(300 12 12)"/><set attributeName="opacity" begin="0.7s" to="1"/><animateTransform attributeName="transform" dur="30s" repeatCount="indefinite" type="rotate" values="0 12 12;360 12 12"/></g></g></svg></div>
        </div>
 </body>
</html>
<?php
    }
else {
	header("Refresh:0; url=.");
 }

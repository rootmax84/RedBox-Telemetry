<?php
$_SESSION['torque_logged_in'] = true;
require_once('db.php');
require_once('parse_functions.php');
include_once('translations.php');
$lang = $_COOKIE['lang'] ?? 'en';

if (!checkRateLimit(5)) {
    header('Location: catch.php?c=block');
    exit;
}

$share_secret = $share_secret ?? 'default_secret'; //default if missed in creds.php

if (isset($_GET['uid'], $_GET['id'], $_GET['sig'])) {
    $uid = $_GET['uid'];
    $session_id = $_GET['id'];
    $sig = $_GET['sig'];
    $payload = "uid={$uid}&id={$session_id}";
    $expected_sig = hash_hmac('sha256', $payload, $share_secret);
} else {
    header('Location: .');
    exit;
}

$user_data = $db->execute_query("SELECT user, sessions_filter, time, gap FROM $db_users WHERE id=?", [$uid])->fetch_assoc();
$username = $user_data['user'];
$_SESSION['sessions_filter'] = $user_data['sessions_filter'];
setcookie('gap', $user_data['gap']);

setcookie('timeformat', $user_data['time']);
$_COOKIE['timeformat'] = $user_data['time'];
require_once('timezone.php');

$db_table = $username.$db_log_prefix;
$db_sessions_table = $username.$db_sessions_prefix;
$db_pids_table = $username.$db_pids_prefix;

if ($username) {
    $cached_timestamp = null;
    $current_timestamp = getLastUpdateTimestamp($db, $session_id, $db_sessions_table);

    if (!$current_timestamp || !hash_equals($expected_sig, $sig)) {
        header('Location: catch.php?c=noshare');
        exit;
    } else {
        checkRateLimit(5, 3600, true);
    }

    // GPS data
    $gps_cache_key = "gps_data_" . $username . "_" . $session_id;
    $gps_data = false;

    if ($memcached_connected) {
        $g_cached_data = $memcached->get($gps_cache_key);
        if ($memcached->getResultCode() === Memcached::RES_SUCCESS && is_array($g_cached_data)) {
            list($gps_data, $cached_timestamp) = $g_cached_data;
        }
    }

    if ($gps_data === false || $cached_timestamp !== $current_timestamp) {
        $gpsQuery = getFilteredGpsQuery($db_table, $_SESSION['sessions_filter']);
        $gps_time_data = $db->execute_query($gpsQuery, [$session_id]);
        $geolocs = [];
        $timearray = [];
        $i = 0;
        while($row = $gps_time_data->fetch_row()) {
            if (($row[0] != 0) && ($row[1] != 0)) {
                $geolocs[] = ["lat" => $row[0], "lon" => $row[1]];
            }
            $timearray[$i] = $row[2];
            $i++;
        }
        $gps_data = ['geolocs' => $geolocs, 'timearray' => $timearray];
        if ($memcached_connected) {
            try {
                $memcached->set($gps_cache_key, [$gps_data, $current_timestamp], 1800);
            } catch (Exception $e) {
                $errorMessage = sprintf("Memcached error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode());
                error_log($errorMessage);
            }
        }
    }

    $geolocs = $gps_data['geolocs'];
    $timearray = $gps_data['timearray'];

    $itime = implode(",", $timearray);

    // Create array of Latitude/Longitude strings in leafletjs JavaScript format
    $mapdata = [];
    foreach($geolocs as $d) {
        $mapdata[] = "[".sprintf("%.14f",$d['lat']).",".sprintf("%.14f",$d['lon'])."]";
    }
    $imapdata = implode(",", $mapdata);

    require_once('get_columns.php');
    require_once('plot.php');

    $db->close();
} else {
    header('Location: catch.php?c=noshare');
    exit;
}

include("head.php");
?>

<body>
    <!-- Flot Local Javascript files -->
    <script src="<?php echo version_url('static/js/jquery.flot.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.axislabels.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.hiddengraphs.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.multihighlight-delta.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.selection.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.time.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.resize.min.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/Control.FullScreen.js'); ?>"></script>
    
    <!-- Configure Jquery Flot graph and plot code -->
    <script>
        let streamBtn_svg = null
        let stream = false;
        sid = `<?php echo $session_id; ?>`;
        uid = `<?php echo $uid; ?>`;
        sig = `<?php echo $sig; ?>`;
        
        $(document).ready(function(){
            if (!document.getElementById('plot_data')) return;

            let plotData = $('#plot_data');
            let lastValue = plotData.val() || [];

            function handleChange() {
                const newValue = plotData.val() || [];
                if (JSON.stringify(newValue) !== JSON.stringify(lastValue)) {
                    lastValue = newValue;
                    updCharts();
                }
            }

            const observer = new MutationObserver((mutations) => {
                if (!lastValue.length && $('#placeholder')[0] != undefined) {
                    updCharts();
                }
            });

            const targetNode = $('#right-container')[0];

            if (targetNode) {
                observer.observe(targetNode, {childList: true, subtree: true});
            }

            plotData.on('change', handleChange);
            plotData.chosen();
            updCharts();
            $(".copyright").html(`&copy; 2019-${(new Date).getFullYear()} RedBox Automotive`);
            resizeSplitter();

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
                        localization.setLang(selectedValue);
                        location.reload();
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
    
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
        <div class="fetch-data"></div>
        <?php if (!isset($_SESSION['admin']) && $limit > 0) { ?>
            <div class="storage-usage-img"></div>
            <label id="storage-usage" l10n='stor.usage'><span><?php echo $db_used;?></span></label>
        <?php } ?>
        
        <div class="container">
                <div class="login-lang" id="lang-switch" style="position:absolute;top:10px;right:10px">
                    <div class="selected-lang" id="selected-lang" style="width:24px;height:24px"></div>
                      <ul class="lang-options" id="lang-options" style="background:#fff">
                        <li data-value="en">English</li>
                        <li data-value="ru">Русский</li>
                        <li data-value="es">Español</li>
                        <li data-value="de">Deutsch</li>
                      </ul>
                </div>
            <div class="navbar-header">
                <a class="navbar-brand" href=".">
                    <div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry
                </a>
            </div>
        </div>
    </div>
    
    <div id="right-container" class="col-md-auto col-xs-12">
        <?php if (!isset($_SESSION['admin']) && isset($session_id) && !empty($session_id)) { ?>
            <!-- Variable Select Block -->
            <h4 l10n="sel.var"></h4>
            <div class="row center-block" style="padding-top:3px;">
                <select data-placeholder="Choose data..." multiple class="chosen-select" size="<?php echo $numcols; ?>" style="width:100%;" id="plot_data" name="plotdata[]">
                    <?php $var1 = ""; foreach ($coldata as $xcol) { ?>
                        <option value="<?php echo $xcol['colname']; ?>" <?php $i = 1; while (isset(${'var' . $i})) { if (${'var' . $i} == $xcol['colname'] || $xcol['colfavorite'] == 1) { echo " selected"; } $i = $i + 1; } ?>>
                            <?php echo $xcol['colcomment']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div <?php if($imapdata) { ?> class="pure-g split-container" <?php } ?>>
                <div <?php if($imapdata) { ?> class="pure-u-md-1-2 pane left" <?php } ?>>
                    <!-- Chart Block -->
                    <div id="Chart-Container" class="row center-block" style="z-index:1;position:relative;">
                            <div style="display:flex; justify-content:center;">
                                <h5><span class="label label-warning">. . .</span></h5>
                            </div>
                    </div>
                </div>
                 <div class="resizer"></div>
                <?php if ($imapdata) { ?>
                    <div class="pure-u-md-1-2 pane right">
                        <!-- MAP -->
                        <div id="map-div"><div class="row center-block map-container" id="map"></div></div>
                    </div>
                <?php } else { ?>
                    <div id="nogps"></div>
                <?php   } ?>
            </div>
            <br>

            <!-- slider -->
            <script>
                jsTimeMap = [<?php echo $itime; ?>].reverse(); //Session time array, reversed for silder
                initSlider(jsTimeMap,jsTimeMap[0],jsTimeMap.at(-1));
            </script>
            <span class="h4" l10n="trim.sess"></span>
            <input type="text" id="slider-time" readonly style="border:0; font-family:monospace; width:300px;" disabled>
            <div id="slider-range11"></div>
            <br>

            <!-- Data Summary Block -->
            <h4 l10n="summary"></h4>
            <div id="Summary-Container" class="row center-block" style="user-select:text;">
                <div style="display:flex; justify-content:center;">
                    <h5><span class="label label-warning">. . .</span></h5>
                </div>
            </div>
            <br>
            <div class="row center-block" style="padding-bottom:18px;text-align:center">
                <p class="copyright"></p>
            </div>
        <?php } ?>
    </div>
    
    <?php if(!isset($_SESSION['admin']) && isset($session_id) && !empty($session_id)) { ?>
        <script>
            const path = [<?php echo $imapdata; ?>]; //this would be a new variable containing speed data for each segment
            if (!path.length) {
                $('#map-div').hide();
            } else {
                window.MapData = {path};
                initMap = initMapLeaflet;
                jsCBinitMap = ()=>$(document).ready(initMap);
                jsCBinitMap();
            }
        </script>
    <?php } ?>
</body>
</html>
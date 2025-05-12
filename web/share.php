<?php
$_SESSION['torque_logged_in'] = true;
require_once 'db.php';
require_once 'helpers.php';
include_once 'translations.php';
$lang = $_COOKIE['lang'] ?? 'en';

if (!checkRateLimit(5)) {
    header('Location: catch.php?c=block');
    exit;
}

require_once('timezone.php');

if (isset($_GET['uid'], $_GET['id'], $_GET['sig'])) {
    $uid = $_GET['uid'];
    $session_id = $_GET['id'];
    $sig = $_GET['sig'];

    $cache_key = "share_data_" . $uid;
    $user_data = false;

    if ($memcached_connected) {
        $user_data = $memcached->get($cache_key);
    }

    if ($user_data === false) {
        $userqry = $db->execute_query("SELECT user, sessions_filter, time, gap, share_secret FROM $db_users WHERE id=?", [$uid]);
        if ($userqry->num_rows) {
            $user_data = $userqry->fetch_assoc();
            if ($memcached_connected) {
                try {
                    $memcached->set($cache_key, $user_data, $db_memcached_ttl ?? 3600);
                } catch (Exception $e) {
                    error_log(sprintf("Memcached error on share: %s (Code: %d)", $e->getMessage(), $e->getCode()));
                }
            }
        } else {
            header('Location: catch.php?c=noshare');
            exit;
        }
    }

    if ($user_data) {
        $username = $user_data['user'];
        $share_secret = $user_data['share_secret'];
        $user_time = $user_data['time'];
        $user_filter = $user_data['sessions_filter'];
        $gap = $user_data['gap'];
    }

    $_SESSION['sessions_filter'] = $user_filter;
    setcookie('gap', $gap);
    setcookie('timeformat', $user_time);
    $_COOKIE['timeformat'] = $user_time;

    $payload = "uid={$uid}&id={$session_id}";
    $expected_sig = hash_hmac('sha256', $payload, $share_secret);
} else {
    header('Location: .');
    exit;
}

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
        $_SESSION['share'] = true;
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
                $memcached->set($gps_cache_key, [$gps_data, $current_timestamp], $db_memcached_ttl ?? 3600);
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

include 'head.php';
?>

<body>
    <!-- Flot Local Javascript files -->
    <script src="<?php echo version_url('static/js/jquery.flot.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.axislabels.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.hiddengraphs.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.multihighlight-delta.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.selection.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.time.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/jquery.flot.resize.js'); ?>"></script>
    <script src="<?php echo version_url('static/js/Control.FullScreen.js'); ?>"></script>
    
    <!-- Configure Jquery Flot graph and plot code -->
    <script>
        let streamBtn_svg = null
        let stream = false;
        sid = '<?php echo htmlspecialchars($session_id); ?>';
        uid = '<?php echo htmlspecialchars($uid); ?>';
        sig = '<?php echo htmlspecialchars($sig); ?>';
        
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

            plotDataChoices = new Choices('#plot_data', {
                removeItemButton: true,
                placeholder: true,
                shouldSort: false,
                itemSelectText: null,
                maxItemCount: 10,
                maxItemText: (maxItemCount) => {
                return `${localization.key['overdata']} ${maxItemCount}`;
                },
                noResultsText: localization.key['vars.nores'] || 'Oops, nothing found!',
                placeholderValue: localization.key['vars.placeholder'] || 'Choose data...',
                classNames: {
                    containerInner: ['choices__inner', 'choices__inner__plot'],
                },
            });

            plotData.on('change', handleChange);
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
        <div class="container">
                <div id="theme-switch"></div>
                <div class="chart-fill-toggle" onClick="chartToggle()" style="right:70px"></div>
                <div class="login-lang" id="lang-switch" style="position:absolute;top:10px;right:40px">
                    <div class="selected-lang" id="selected-lang" style="width:24px;height:24px;color:#5d5d5d"></div>
                      <ul class="lang-options" id="lang-options" style="background:#fff">
                        <li data-value="en">English</li>
                        <li data-value="ru">Русский</li>
                        <li data-value="es">Español</li>
                        <li data-value="de">Deutsch</li>
                      </ul>
                </div>
            <div class="navbar-header">
                <a class="navbar-brand" href="#" style="cursor:default">
                    <div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry
                </a>
            </div>
        </div>
    </div>
    
    <div id="right-container" class="col-md-auto col-xs-12">
        <?php if (!isset($_SESSION['admin']) && isset($session_id) && !empty($session_id)) { ?>
            <!-- Variable Select Block -->
            <div class="row center-block" style="padding-bottom:10px;">
                <select multiple id="plot_data">
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

            <!-- slider -->
            <script>
                jsTimeMap = [<?php echo $itime; ?>].reverse(); //Session time array, reversed for silder
                initSlider(jsTimeMap,jsTimeMap[0],jsTimeMap.at(-1));
            </script>
            <div class="slider-container">
              <input type="text" id="slider-time" readonly disabled>
              <div id="slider-range11"></div>
            </div>
            <br>

            <!-- Data Summary Block -->
            <div id="Summary-Container" class="row center-block" style="user-select:text;">
                <div style="display:flex; justify-content:center;">
                </div>
            </div>

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
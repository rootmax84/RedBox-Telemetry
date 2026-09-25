<?php

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/auth_user.php';
require_once __DIR__ . '/src/creds.php';
require_once __DIR__ . '/src/db_limits.php';

$cache_key = "fav_data_" . $username;
$fav_data = false;

if ($memcached_connected) {
    $fav_data = $memcached->get($cache_key);
}

if ($fav_data === false) {
    $query = "SELECT session, profileName, description, time, timeend
          FROM $db_sessions_table
          WHERE favorite = 1
          ORDER BY session DESC";
    $keydata = $db->query($query);
    if ($keydata->num_rows) {
        $fav_data = $keydata->fetch_all(MYSQLI_ASSOC);
        if ($memcached_connected) {
            try {
                $memcached->set($cache_key, $fav_data, $db_memcached_ttl ?? 3600);
            } catch (Exception $e) {
                error_log(sprintf("Memcached error on favorite: %s (Code: %d)", $e->getMessage(), $e->getCode()));
            }
        }
    }
}
$row_count = $fav_data ? count($fav_data) : 0;

$db->close();
include_once __DIR__ . '/src/head.php';
?>
    <body>
    <script src="<?php echo version_url('static/js/fav_sessions.js'); ?>"></script>
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
        <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
            <div class="new-session"><a href='.' l10n='sess.new'></a></div>
            <div class="storage-usage-img"></div>
        <?php } ?>
        <div class="container">
            <div id="theme-switch"></div>
            <div class="navbar-header">
                <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a>
            </div>
        </div>
    </div>
  <div class="menu-container">
    <input type="checkbox" id="menu-toggle" class="menu-toggle"/>

    <label for="menu-toggle" class="menu-button">
      <span class="hamburger">
        <span></span>
        <span></span>
        <span></span>
      </span>
    </label>

    <label for="menu-toggle" class="menu-overlay"></label>

    <ul class="menu-list" role="menu">
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="location.href='./search.php'">
          <span class="icon" id="search-img"></span>
          <span l10n="search.find"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" style="color:#961911">
          <span class="icon" id="fav-img"></span>
          <span l10n="fav.btn"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="delSessions()">
          <span class="icon" id="delMass-img"></span>
          <span l10n="func.multi.del"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="pidEdit()">
          <span class="icon" id="editPid-img"></span>
          <span l10n="func.pid"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="showToken()">
          <span class="icon" id="token-img"></span>
          <span l10n="func.token"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="usersSettings()">
          <span class="icon" id="settings-img"></span>
          <span l10n="func.settings"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="remoteRa()">
          <span class="icon" id="remote-ra-rbx-img"></span>
          <span l10n="func.remote"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="showHints()">
          <span class="icon" id="hint-img"></span>
          <span l10n="hint.button"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="logout()">
          <span class="icon" id="logout-img"></span>
          <span l10n="logout"></span>
        </button>
      </li>
    </ul>
  </div>

        <div style="padding:60px 0 10px; display:flex; justify-content:center;">
            <button class="btn btn-info btn-sm" id="update_desc" l10n="btn.apply"></button>
        </div>
        <table class="table table-del-merge-pid" id="fav-table">
            <thead>
                <tr>
                    <th l10n="fav.sess"></th>
                    <th l10n="s.table.duration"></th>
                    <th l10n="sel.profile"></th>
                    <th l10n="p.table.desc"></th>
                    <th l10n="fav.url"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fav_data as $i => $keycol) { ?>
                    <tr data-sid=<?php echo $keycol['session']; ?>>
                        <td id="id:<?php echo $keycol['session']; ?>">
                            <?php
                                $start_timestamp = intval(substr($keycol['session'], 0, -3));
                                $month_num = date('n', $start_timestamp);
                                $month_key = 'month.' . strtolower(date('M', $start_timestamp));
                                $translated_month = $translations[$lang][$month_key];
                                $date = date($_COOKIE['timeformat'] == "12" ? "d, Y h:ia" : "d, Y H:i", $start_timestamp);
                                $session_id_str = $translated_month . ' ' . $date;
                            ?>
                            <span class='delete-icon' onclick="event.stopPropagation(); removeFavorite('<?php echo $keycol['session']; ?>', '<?php echo $session_id_str; ?>')">&times;</span>
                            <?php
                                echo $session_id_str;
                            ?>
                        </td>
                        <td>
                            <?php
                                echo formatDuration((int)$keycol['time'], (int)$keycol['timeend'], $lang);
                            ?>
                        </td>
                        <td><?php if ($keycol['profileName'] == 'Not Specified') { echo $translations[$lang]['profile.ns']; } else { echo $keycol['profileName']; } ?></td>
                        <td data-sid="<?php echo $keycol['session']; ?>"><?php echo $keycol['description']; ?></td>
                        <td><a href=<?php echo '.?id='.$keycol['session']; ?> l10n='fav.open'></a></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php
        if (!$row_count) {
    ?>
        <h3 style='text-align:center' l10n="fav.empty"></h3>
        <script>
            document.getElementById('update_desc').disabled = true;
        </script>

    <?php } ?>
    <br>
</body>
</html>
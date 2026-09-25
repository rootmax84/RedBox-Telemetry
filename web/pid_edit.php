<?php

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/auth_user.php';
require_once __DIR__ . '/src/creds.php';
require_once __DIR__ . '/src/db_limits.php';

$excludedIds = ['kff1005', 'kff1006', 'kff1007'];
$excludedIdsString = implode(',', array_map(fn($id) => "'$id'", $excludedIds));

$query = "SELECT id, description, units, populated, stream, favorite 
          FROM $db_pids_table 
          WHERE id NOT IN ($excludedIdsString) 
          ORDER BY description";

$keydata = $db->query($query)->fetch_all(MYSQLI_ASSOC);

$db->close();
include_once __DIR__ . '/src/head.php';
?>
    <body>
    <script src="<?php echo version_url('static/js/pid_edit.js'); ?>"></script>
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
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="favoriteSessions()">
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
        <button class="menu-item" role="menuitem" tabindex="-1" style="color:#961911">
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

    <form style="padding:50px 0 0;" method="POST" action="pid_commit.php" onsubmit="return submitForm(this);">
        <div style="padding:10px; display:flex; justify-content:center;">
            <button class="btn btn-info btn-sm" type="submit" l10n="btn.apply"></button>
        </div>
        <table class="table table-del-merge-pid">
            <thead>
                <tr>
                    <th l10n="p.table.id"></th>
                    <th l10n="p.table.desc"></th>
                    <th l10n="p.table.units"></th>
                    <th l10n="p.table.chart"></th>
                    <th l10n="p.table.stream"></th>
                    <th l10n="p.table.fav"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keydata as $i => $keycol) { ?>
                    <tr data-pid=<?php echo $keycol['id']; ?>>
                        <td style="white-space:nowrap" id="id:<?php echo $keycol['id']; ?>"><span class='delete-icon' onclick="event.stopPropagation(); deletePID('<?php echo $keycol['id']; ?>')">&times;</span><?php echo $keycol['id']; ?></td>
                        <td id="description:<?php echo $keycol['id']; ?>" contenteditable="true"><?php echo $keycol['description']; ?></td>
                        <td id="units:<?php echo $keycol['id']; ?>" contenteditable="true"><?php echo $keycol['units']; ?></td>
                        <td><input type="checkbox" id="populated:<?php echo $keycol['id']; ?>"<?php if ($keycol['populated']) echo " checked"; ?>></td>
                        <td><input type="checkbox" id="stream:<?php echo $keycol['id']; ?>"<?php if ($keycol['stream']) echo " checked"; ?>></td>
                        <td><input type="checkbox" id="favorite:<?php echo $keycol['id']; ?>"<?php if ($keycol['favorite']) echo " checked"; ?>></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </form>
    <br>
</body>
</html>
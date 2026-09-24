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
<?php if(isset($_SESSION['admin'])) {?>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="location.href='./users_admin.php?action=reg'">
          <span class="icon" id="reg-img"></span>
          <span l10n="admin.page.btn.reg"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="location.href='./users_admin.php?action=edit'">
          <span class="icon" id="editPid-img"></span>
          <span l10n="admin.page.btn.edit"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="location.href='./users_admin.php?action=del'">
          <span class="icon" id="del-img"></span>
          <span l10n="admin.page.btn.del"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="location.href='./users_admin.php?action=trunc'">
          <span class="icon" id="clear-img"></span>
          <span l10n="admin.page.btn.trunc"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="window.open('./adminer.php?server=<?php echo $db_host; ?>&username=<?php echo $db_user; ?>&db=<?php echo $db_name; ?>', '_blank')">
          <span class="icon" id="adminer-img"></span>
          Adminer
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="maintenance()">
          <span class="icon" id="maintenance-img"></span>
          <span l10n="admin.page.btn.maintenance"></span>
        </button>
      </li>
<?php } else {?>
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
    <?php if (isset($session_id) && !empty($session_id)) { ?>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="delSession()">
          <span class="icon" id="del-img"></span>
          <span l10n="func.del"></span>
        </button>
      </li>
    <?php } ?>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="delSessions()">
          <span class="icon" id="delMass-img"></span>
          <span l10n="func.multi.del"></span>
        </button>
      </li>
    <?php if (isset($session_id) && !empty($session_id)) { ?>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="mergeSessions()">
          <span class="icon" id="merge-img"></span>
          <span l10n="func.merge"></span>
        </button>
      </li>
    <?php } ?>
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
    <?php if (isset($session_id) && !empty($session_id)) { ?>
      <li role="none">
          <hr>
      </li>
      <li role="none">
        <button class="menu-item <?php if ($id != "RedManage") { ?> menu-item-disabled <?php } ?>" role="menuitem" tabindex="-1" onclick="exportSession('RBX')"<?php if ($id != "RedManage") { ?> disabled <?php } ?>>
          <span class="icon" id="rbx-img"></span>
          <span l10n="export.session"></span>RBX
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="exportSession('CSV')">
          <span class="icon" id="csv-img"></span>
          <span l10n="export.session"></span>CSV
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="exportSession('JSON')">
          <span class="icon" id="json-img"></span>
          <span l10n="export.session"></span>JSON
        </button>
      </li>
      <li role="none">
        <button class="menu-item <?php if (!$imapdata) { ?> menu-item-disabled <?php } ?>" role="menuitem" tabindex="-1" onclick="exportSession('KML')" <?php if (!$imapdata) { ?> disabled <?php } ?>>
          <span class="icon" id="kml-img"></span>
          <span l10n="export.session"></span>KML
        </button>
      </li>
    <?php } ?>
      <li role="none">
          <hr>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="uploadLogDialog()">
          <span class="icon" id="import-img"></span>
          <span l10n="import.data"></span>
        </button>
      </li>
<?php }?>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="logout()">
          <span class="icon" id="logout-img"></span>
          <span l10n="logout"></span>
        </button>
      </li>
    </ul>
  </div>

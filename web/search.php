<?php
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/db_limits.php';
include_once __DIR__ . '/translations.php';

$pids = [];
$pidQuery = $db->query("SELECT id, description FROM $db_pids_table ORDER BY description ASC");
if ($pidQuery) {
    while ($row = $pidQuery->fetch_assoc()) {
        $pids[] = $row;
    }
}
$db->close();

include_once __DIR__ . '/src/head.php';
?>
<body>
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
        <div class="fetch-data"></div>
        <?php if (!isset($_SESSION['admin']) && $limit > 0) { ?>
            <div class="new-session"><a href='.' l10n='sess.new'></a></div>
            <div class="storage-usage-img"></div>
        <?php } ?>
        <div class="container">
            <div id="theme-switch"></div>
            <div class="navbar-header">
                <a class="navbar-brand" href=".">
                    <div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry
                </a>
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
            <button class="menu-item" role="menuitem" tabindex="-1"  style="color:#961911">
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
                <button class="menu-item" role="menuitem" tabindex="-1" onclick="window.location.href='del_sessions.php'">
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

    <div class="col-md-auto col-xs-12" id="right-container">

        <div class="row center-block" style="max-width:720px;">
            <form id="searchForm" class="form-horizontal">
                <table style="width:100%">
                    <tr>
                        <td style="width:10%">
                            <select id="pidSelect" name="pid" class="form-control choices-select" required>
                                <option value="" disabled selected><?= $translations[$lang]['search.select_pid'] ?></option>
                                <?php foreach ($pids as $pid): ?>
                                    <option value="<?= htmlspecialchars($pid['id']) ?>"><?= htmlspecialchars($pid['description']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="width:.5%"></td>
                        <td style="width:1%">
                            <select id="operatorSelect" name="operator" class="form-control choices-select">
                                <option value="=" selected>=</option>
                                <option value=">">></option>
                                <option value="<"><</option>
                                <option value=">=">>=</option>
                                <option value="<="><=</option>
                            </select>
                        </td>
                        <td style="width:.5%"></td>
                        <td style="width:5%">
                            <input type="number" style="text-align:center" step="any" class="form-control" id="valueInput" name="value" l10n-placeholder="stream.val" required>
                        </td>
                        <td style="width:.5%"></td>
                        <td style="width:0%">
                            <button type="submit" class="btn btn-info btn-sm"><?= $translations[$lang]['search.find'] ?></button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>

        <div id="results-container" style="margin-top:20px">
            <table class="table table-del-merge-pid" id="results-table" style="display:none;">
                <thead>
                    <tr>
                        <th><?= $translations[$lang]['s.table.start'] ?></th>
                        <th><?= $translations[$lang]['s.table.end'] ?></th>
                        <th><?= $translations[$lang]['s.table.datapoints'] ?></th>
                        <th><?= $translations[$lang]['sel.profile'] ?></th>
                        <th><?= $translations[$lang]['fav.url'] ?></th>
                    </tr>
                </thead>
                <tbody id="results-body"></tbody>
            </table>
            <div id="no-more" style="text-align:center; padding:20px; display:none;">
                <span class="label label-default"><?= $translations[$lang]['search.no_more'] ?></span>
            </div>
            <div id="no-results" style="text-align:center; display:none;">
                <span class="label label-default"><?= $translations[$lang]['search.no_results'] ?></span>
            </div>
        </div>
    </div>
    <script>
    window.SEARCH_CONFIG = <?php echo json_encode([
        'lang'              => $lang ?? 'en',
        'favOpen'           => $translations[$lang]['fav.open'] ?? '',
        'errorNoPid'        => $translations[$lang]['search.error_no_pid'] ?? '',
        'errorInvalidValue' => $translations[$lang]['search.error_invalid_value'] ?? '',
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="<?php echo version_url('static/js/search.js'); ?>"></script>
</body>
</html>

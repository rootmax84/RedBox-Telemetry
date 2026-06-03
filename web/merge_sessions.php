<?php
require_once 'db.php';
require_once 'get_sessions.php';
require_once 'db_limits.php';

$mergesession = filter_input(INPUT_POST, 'mergesession', FILTER_SANITIZE_NUMBER_INT) 
              ?? filter_input(INPUT_GET, 'mergesession', FILTER_SANITIZE_NUMBER_INT);

$page = $_GET["page"] ?? 1;

$sessionids = [];
$mergesess = [];

foreach ($_GET as $key => $value) {
    if (!in_array($key, ["mergesession", "page", "csrf_token"])) {
        $sid = (int)$key;
        if ($sid > 0) {
            $sessionids[] = $sid;
            $mergesess[] = $sid;
        }
    } elseif ($key === "mergesession" && !empty($value)) {
        $sessionids[] = (int)$value;
    }
}

$sessionids = array_unique($sessionids);
$mergesess1 = !empty($mergesess) ? $mergesess[0] : null;

if (!empty($mergesession) && !empty($mergesess1)) {

    $profileQuery = "SELECT profileName, description, favorite, ip FROM $db_sessions_table WHERE session = ?";
    $stmt = $db->prepare($profileQuery);
    $stmt->bind_param('i', $mergesession);
    $stmt->execute();
    $profileResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $profileName = $profileResult['profileName'];
    $profileFavorite = $profileResult['favorite'];
    $profileDesc = $profileResult['description'];
    $profileIp = $profileResult['ip'];

    $allSessions = array_values($sessionids);
    $placeholders = implode(',', array_fill(0, count($allSessions), '?'));
    $qrystr = "SELECT MIN(time) as time, MAX(timeend) as timeend, MIN(session) as session, SUM(sessionsize) as sessionsize 
               FROM $db_sessions_table 
               WHERE session IN ($placeholders)";

    $stmt = $db->prepare($qrystr);
    $types = str_repeat('i', count($allSessions));
    $stmt->bind_param($types, ...$allSessions);
    $stmt->execute();
    $mergerow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $newsession = $mergerow['session'];
    $newtimestart = $mergerow['time'];
    $newtimeend = $mergerow['timeend'];
    $newsessionsize = $mergerow['sessionsize'];

    $updateMain = "UPDATE $db_sessions_table 
                   SET time = ?, timeend = ?, sessionsize = ?, profileName = ?, favorite = ?, description = ?, ip = ? 
                   WHERE session = ?";
    $stmt = $db->prepare($updateMain);
    $stmt->bind_param('iiissssi', $newtimestart, $newtimeend, $newsessionsize, $profileName, $profileFavorite, $profileDesc, $profileIp, $newsession);
    $stmt->execute();
    $stmt->close();

    foreach ($allSessions as $sid) {
        if ($sid == $newsession) continue;

        $delStmt = $db->prepare("DELETE FROM $db_sessions_table WHERE session = ?");
        $delStmt->bind_param('i', $sid);
        $delStmt->execute();
        $delStmt->close();

        $updDataStmt = $db->prepare("UPDATE $db_table SET session = ? WHERE session = ?");
        $updDataStmt->bind_param('ii', $newsession, $sid);
        $updDataStmt->execute();
        $updDataStmt->close();
    }

    cache_flush();

    //Show merged session
    header('Location: .?id=' . $newsession);
    exit;
} elseif (isset($mergesession) && !empty($mergesession)) {
    include 'head.php';
?>
    <body>
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
            <?php if (!isset($_SESSION['admin']) && $limit > 0) { ?>
                <div class="new-session"><a href='.' l10n='sess.new'></a></div>
                <div class="storage-usage-img"></div>
            <?php } ?>
                <a href="users_remote.php" class="remote-img" style="right:<?php echo ($limit < 0) ? '40px' : '70px'; ?>"></a>
            <div class="container">
                <div id="theme-switch"></div>
                <div class="navbar-header">
                    <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
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
          <span class="icon" id="merge-img"></span>
          <span l10n="func.merge"></span>
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
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="showHints()">
          <span class="icon" id="hint-img"></span>
          <span l10n="hint.button"></span>
        </button>
      </li>
    </ul>
  </div>

        <form style="padding:50px 0 0;" action="merge_sessions.php" method="get" id="formmerge">
            <input type="hidden" name="mergesession" value="<?php echo $mergesession; ?>">
            <div style="padding:10px; display:flex; justify-content:center;">
                <button class="btn btn-info btn-sm" type="submit" id="merge-btn" l10n="btn.merge"></button>
            </div>
            <table class="table table-del-merge-pid">
                <thead>
                    <tr>
                        <th></th>
                        <th l10n="s.table.start"></th>
                        <th l10n="s.table.end"></th>
                        <th l10n="s.table.duration"></th>
                        <th l10n="s.table.datapoints"></th>
                        <th l10n="sel.profile"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $page_first_result = ($page - 1) * $results_per_page;
                    $sessqry = $db->query("SELECT COUNT(*) FROM $db_sessions_table");
                    $number_of_result = $sessqry->fetch_row()[0];
                    $number_of_page = ceil($number_of_result / $results_per_page);
                    $sessqry = $db->query("SELECT time, timeend, session, profileName, sessionsize FROM $db_sessions_table ORDER BY session desc LIMIT " . $page_first_result . "," . $results_per_page);

                    while ($x = $sessqry->fetch_array()) {
                    ?>
                        <tr>
                            <td><input type="checkbox" name="<?php echo $x['session']; ?>" class="session-checkbox" data-sessionsize="<?php echo $x['sessionsize']; ?>" <?php if ($x['session'] == $mergesession) { echo "checked disabled"; } ?>></td>
                            <td id="start:<?php echo $x['session']; ?>">
                                <?php
                                $start_timestamp = intval(substr($x["time"], 0, -3));
                                $month_num = date('n', $start_timestamp);
                                $month_key = 'month.' . strtolower(date('M', $start_timestamp));
                                $translated_month = $translations[$lang][$month_key];
                                $date = date($_COOKIE['timeformat'] == "12" ? "d, Y h:ia" : "d, Y H:i", $start_timestamp);
                                echo $translated_month . ' ' . $date;
                                ?>
                            </td>
                            <td id="end:<?php echo $x['session']; ?>">
                                <?php
                                $end_timestamp = intval(substr($x["timeend"], 0, -3));
                                $month_num = date('n', $end_timestamp);
                                $month_key = 'month.' . strtolower(date('M', $end_timestamp));
                                $translated_month = $translations[$lang][$month_key];
                                $date = date($_COOKIE['timeformat'] == "12" ? "d, Y h:ia" : "d, Y H:i", $end_timestamp);
                                echo $translated_month . ' ' . $date;
                                ?>
                            </td>
                            <td id="length:<?php echo $x['session']; ?>">
                                <?php
                                echo formatDuration((int)$x["time"], (int)$x["timeend"], $lang);
                                ?>
                            </td>
                            <td id="size:<?php echo $x['session']; ?>" class="datapoints"><?php echo $x["sessionsize"]; ?></td>
                            <td id="profile:<?php echo $x['session']; ?>"><?php if ($x["profileName"] == 'Not Specified') { echo $translations[$lang]['profile.ns']; } else { echo $x["profileName"]; } ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
            <?php
                if (!$number_of_result) {
            ?>
                <h3 style='text-align:center' l10n="no.sess"></h3>
                <script>
                    document.getElementById('merge-btn').disabled = true;
                </script>
            <?php } ?>
        </form>
        <div class="pages">
        <?php //Pagination with page count limit
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $total_pages = $number_of_page;
            $page_numbers_limit = 10;
            $start = $current_page - floor($page_numbers_limit / 2);
            $end = $current_page + floor($page_numbers_limit / 2);
            if ($start < 1) {
                $start = 1;
                $end = min($page_numbers_limit, $total_pages);
            }
            if ($end > $total_pages) {
                $end = $total_pages;
                $start = max(1, $total_pages - $page_numbers_limit + 1);
            }
            if ($current_page > 1) {
                echo '<a class="pages" href="merge_sessions.php?mergesession=' . $mergesession . '&page=1">&#171;</a> ';
            }
            if ($current_page > 1) {
                $previous_page = $current_page - 1;
                echo '<a class="pages" href="merge_sessions.php?mergesession=' . $mergesession . '&page=' . $previous_page . '">&#60;</a> ';
            }
            for ($page = $start; $page <= $end; $page++) {
                if ($number_of_result < $results_per_page) break;
                if ($page == $current_page) {
                    echo '<a class="current-page" href="merge_sessions.php?mergesession=' . $mergesession . '&page=' . $page . '">' . $page . ' </a>';
                } else {
                    echo '<a class="pages" href="merge_sessions.php?mergesession=' . $mergesession . '&page=' . $page . '">' . $page . ' </a>';
                }
            }
            if ($current_page < $total_pages) {
                $next_page = $current_page + 1;
                echo ' <a class="pages" href="merge_sessions.php?mergesession=' . $mergesession . '&page=' . $next_page . '">&#62;</a>';
            }
            if ($current_page < $total_pages) {
                echo ' <a class="pages" href="merge_sessions.php?mergesession=' . $mergesession . '&page=' . $total_pages . '">&#187;</a>';
            }
            ?>
    </div>
        <script>
            let total = 0;
            $(document).ready(() => {
                updateTotalDatapoints();

                $(".session-checkbox").on("change", function() {
                    updateTotalDatapoints();
                });

                function updateTotalDatapoints() {
                    let sum = 0;
                    $(".session-checkbox:checked").each(function() {
                        sum += parseInt($(this).data("sessionsize"));
                        total = sum;
                    });
                }

                $("#merge-btn").on("click", (e) => {
                    e.preventDefault();
                    const checkedCount = $('input[type="checkbox"]:checked').length;
                    if (checkedCount > 1) {
                        mergeSession();
                    } else {
                        noSel();
                    }
                });
                sortMergeDel();
            });

            function noSel() {
             let dialogOpt = {
                title: localization.key['dialog.confirm'],
                btnClassSuccessText: "OK",
                btnClassFail: "hidden",
                message : localization.key['dialog.no.select']
             };
             redDialog.make(dialogOpt);
            }

            function mergeSession() {
                const mergedSession = document.querySelector('input[type="checkbox"].session-checkbox[disabled]');
                let msDate = "";
                if (mergedSession) {
                        const checkboxCell = mergedSession.closest('td');
                        const nextCell = checkboxCell.nextElementSibling;
                    if (nextCell) {
                        msDate = nextCell.textContent.trim();
                    } else {
                        msDate = <?php echo $mergesession; ?>;
                    }
                }

                if (!msDate.length) {
                    serverError();
                    return;
                }

                let maximum = <?php echo isset($merge_max) ? $merge_max : 50000; ?>;
                let oversize = total > maximum;
                let dialogOpt = {
                    title : oversize ? localization.key['dialog.merge.big.title'] : localization.key['dialog.confirm'],
                    message: oversize ? `${localization.key['dialog.merge.big.msg']} ${maximum/1000}k ${localization.key['dialog.merge.big.datapoints']}<br>${localization.key['dialog.merge.big.sel']} ${total/1000}k` : `${localization.key['dialog.merge.sessions']} (${msDate})?`,
                    btnClassSuccessText: oversize ? "OK" : localization.key['btn.yes'],
                    btnClassFailText: localization.key['btn.no'],
                    btnClassFail: oversize ? "hidden" : "btn btn-info btn-sm",
                    onResolve: function() {
                        if (!oversize) {
                            $("#wait_layout").show();
                            document.getElementById("formmerge").submit();
                        }
                    }
                };
                redDialog.make(dialogOpt);
            }
        </script>
    </body>
</html>
<?php
} else {
    header('Location: .');
}
$db->close();

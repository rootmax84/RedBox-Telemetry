<?php
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/get_sessions.php';
require_once __DIR__ . '/src/db_limits.php';
require_once __DIR__ . '/src/heavy_tasks.php';

$mergesession = filter_input(INPUT_POST, 'mergesession', FILTER_SANITIZE_NUMBER_INT)
              ?? filter_input(INPUT_GET,  'mergesession', FILTER_SANITIZE_NUMBER_INT);

$page = $_GET["page"] ?? $_POST["page"] ?? 1;

$is_ajax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');

// Читаем параметры из POST для AJAX, иначе из GET (как раньше)
$src = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;

$sessionids = [];
$mergesess  = [];

foreach ($src as $key => $value) {
    if (!in_array($key, ["mergesession", "page", "csrf_token"])) {
        $sid = (int)$key;
        if ($sid > 0) {
            $sessionids[] = $sid;
            $mergesess[]  = $sid;
        }
    } elseif ($key === "mergesession" && !empty($value)) {
        $sessionids[] = (int)$value;
    }
}

$sessionids  = array_unique($sessionids);
$mergesess1  = !empty($mergesess) ? $mergesess[0] : null;

if (!empty($mergesession) && !empty($mergesess1)) {

    // ── Fast path: поставить в очередь heavy worker ──
    $task_id = heavy_task_push(
        'merge_sessions',
        [
            'username'       => $username,
            'session_ids'    => array_values($sessionids),
            'target_session' => (int)$mergesession,
        ],
        current_user_id()
    );

    if ($task_id !== null) {
        header('Content-Type: application/json');
        http_response_code(202);
        echo json_encode(['status' => 'accepted', 'task_id' => $task_id]);
        exit;
    }

    // ── Fallback inline (Redis недоступен) ──
    // (то же самое, что делает heavy_do_merge_sessions, но синхронно)

    /* ── 1. Метаданные целевой сессии ──
     * target_session — то, что пользователь выбрал в UI. Должна
     * существовать и принадлежать текущему юзеру. Если её нет
     * (удалена в другой вкладке, подделан URL, гонка) — уводим
     * на главную, иначе UPDATE logs уедет на несуществующий id
     * и оставит сиротские логи без метаданных.
     */
    $profileResult = $db->execute_query(
        "SELECT profileName, description, favorite, ip
           FROM sessions
          WHERE user_id = ? AND session = ?",
        [current_user_id(), $mergesession]
    )->fetch_assoc();

    if (!$profileResult) {
        header('Location: .');
        exit;
    }

    $profileName     = $profileResult['profileName'];
    $profileFavorite = $profileResult['favorite'];
    $profileDesc     = $profileResult['description'];
    $profileIp       = $profileResult['ip'];

    /* ── 2. Агрегаты по всем выбранным сессиям ──
     * MIN(session) здесь не нужен — new_session всегда равен
     * target_session (то, что пользователь выбрал в UI).
     */
    $allSessions = array_values($sessionids);
    $ph          = implode(',', array_fill(0, count($allSessions), '?'));
    $params      = array_merge([current_user_id()], $allSessions);
    $types       = 'i' . str_repeat('i', count($allSessions));

    $stmt = $db->prepare(
        "SELECT MIN(time) AS time, MAX(timeend) AS timeend,
                SUM(sessionsize) AS sessionsize
           FROM sessions
          WHERE user_id = ? AND session IN ($ph)"
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $mergerow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$mergerow || $mergerow['time'] === null) {
        header('Location: .');
        exit;
    }

    $newsession     = $mergesession;               // = target_session, а не MIN()
    $newtimestart   = $mergerow['time'];
    $newtimeend     = $mergerow['timeend'];
    $newsessionsize = $mergerow['sessionsize'];

    /* ── 2b. Защита от слишком большого мержа ──
     * UI проверяет mergeMax до отправки, но полагаться только
     * на клиент нельзя — запрос мог быть сформирован вручную.
     */
    if (!empty($merge_max) && (int)$newsessionsize > (int)$merge_max) {
        header('Location: .');
        exit;
    }

    /* ── 3. Атомарное обновление ──
     * В inline-пути чанков нет (один UPDATE logs без LIMIT), поэтому
     * транзакция бесплатна и даёт атомарность: либо обновили метаданные
     * + перелили логи + удалили чужие sessions — либо ничего.
     */
    $db->begin_transaction();

    try {
        // 3a. Обновить метаданные целевой сессии
        $stmt = $db->prepare(
            "UPDATE sessions
                SET time = ?, timeend = ?, sessionsize = ?, profileName = ?,
                    favorite = ?, description = ?, ip = ?
              WHERE user_id = ? AND session = ?"
        );
        $stmt->bind_param('iiissssii',
            $newtimestart, $newtimeend, $newsessionsize,
            $profileName, $profileFavorite, $profileDesc, $profileIp,
            current_user_id(), $newsession
        );
        $stmt->execute();
        $stmt->close();

        // 3b. Перелить логи из чужих сессий и удалить их метаданные.
        //     Порядок: UPDATE logs → DELETE sessions. Если упадёт
        //     DELETE — останутся пустые сессии, их легко дочистить.
        //     Обратный порядок оставил бы логи без метаданных.
        foreach ($allSessions as $sid) {
            if ($sid == $newsession) continue;

            $updDataStmt = $db->prepare("UPDATE logs SET session = ? WHERE user_id = ? AND session = ?");
            $updDataStmt->bind_param('iii', $newsession, current_user_id(), $sid);
            $updDataStmt->execute();
            $updDataStmt->close();

            $delStmt = $db->prepare("DELETE FROM sessions WHERE user_id = ? AND session = ?");
            $delStmt->bind_param('ii', current_user_id(), $sid);
            $delStmt->execute();
            $delStmt->close();
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    cache_flush();

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status'      => 'done',
            'new_session' => (int)$newsession,
        ]);
        exit;
    }

    header('Location: .?id=' . $newsession);
    exit;

} elseif (isset($mergesession) && !empty($mergesession)) {
    include_once __DIR__ . '/src/head.php';
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
                    $sessqry = $db->execute_query("SELECT COUNT(*) FROM sessions WHERE user_id = ?", [current_user_id()]);
                    $number_of_result = $sessqry->fetch_row()[0];
                    $number_of_page = ceil($number_of_result / $results_per_page);
                    $sessqry = $db->execute_query(
                        "SELECT time, timeend, session, profileName, sessionsize
                           FROM sessions
                          WHERE user_id = ?
                          ORDER BY session DESC
                          LIMIT " . (int)$page_first_result . "," . (int)$results_per_page,
                        [current_user_id()]
                    );

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
    </div>
    <script>
    window.MERGE_CONFIG = <?php echo json_encode([
        'mergesession' => (int)$mergesession,
        'mergeMax'     => isset($merge_max) ? (int)$merge_max : 50000,
        'noSessions'   => !$number_of_result,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="<?php echo version_url('static/js/merge_sessions.js'); ?>"></script>
    </body>
</html>
<?php
} else {
    header('Location: .');
}
$db->close();

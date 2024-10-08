<?php
require_once('db.php');
require_once('get_sessions.php');
require_once('db_limits.php');

if (!isset($_SESSION)) { session_start(); }

if (isset($_POST["mergesession"])) {
    $mergesession = preg_replace('/\D/', '', $_POST['mergesession']);
} elseif (isset($_GET["mergesession"])) {
    $mergesession = preg_replace('/\D/', '', $_GET['mergesession']);
}

if (!isset($_GET["page"])) {
    $page = 1;
} else {
    $page = $_GET["page"];
}

$sessionids = array();

$i = 1;
$mergesess1 = "";
foreach ($_GET as $key => $value) {
    if ($key != "mergesession" && $key != "page") {
        ${'mergesess' . $i} = $key;
        array_push($sessionids, $key);
        $i++;
    } else {
        array_push($sessionids, $value);
    }
}

if (isset($mergesession) && !empty($mergesession) && isset($mergesess1) && !empty($mergesess1)) {
    $qrystr = "SELECT MIN(time) as time, MAX(timeend) as timeend, MIN(session) as session, SUM(sessionsize) as sessionsize FROM $db_sessions_table WHERE session = ?";
    $i = 1;
    while (isset(${'mergesess' . $i}) || !empty(${'mergesess' . $i})) {
        $qrystr .= " OR session = '" . ${'mergesess' . $i} . "'";
        $i++;
    }

    $mergerow = $db->execute_query($qrystr, [$mergesession])->fetch_assoc();
    $newsession = $mergerow['session'];
    $newtimestart = $mergerow['time'];
    $newtimeend = $mergerow['timeend'];
    $newsessionsize = $mergerow['sessionsize'];

    foreach ($sessionids as $value) {
        if ($value == $newsession) {
            $updatequery = "UPDATE $db_sessions_table SET time=$newtimestart, timeend=$newtimeend, sessionsize=$newsessionsize where session = ?";
            $db->execute_query($updatequery, [$newsession]);
        } else {
            $delquery = "DELETE FROM $db_sessions_table WHERE session = ?";
            $db->execute_query($delquery, [$value]);
            $updatequery = "UPDATE $db_table SET session=$newsession WHERE session = ?";
            $db->execute_query($updatequery, [$value]);
        }
    }
    //Show merged session
    header('Location: .?id=' . $newsession);
} elseif (isset($mergesession) && !empty($mergesession)) {
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <?php include("head.php"); ?>
    </head>
    <body>
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
            <?php if (!isset($_SESSION['admin']) && $limit > 0) { ?>
                <label id="storage-usage">Storage usage: <?php echo $db_used; ?></label>
            <?php } ?>
            <div class="container">
                <div id="theme-switch"></div>
                <div class="navbar-header">
                    <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a><span title="logout" class="navbar-brand logout" onClick="logout()"></span>
                </div>
            </div>
        </div>
        <form style="padding:50px 0 0;" action="merge_sessions.php" method="get" id="formmerge">
            <input type="hidden" name="mergesession" value="<?php echo $mergesession; ?>">
            <div style="padding:10px; display:flex; justify-content:center;">
                <input class="btn btn-info btn-sm" type="submit" value="Merge Selected Sessions" id="merge-btn">
            </div>
            <table class="table table-del-merge-pid">
                <thead>
                    <tr>
                        <th></th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Session Duration</th>
                        <th>Number of Datapoints</th>
                        <th>Profile</th>
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
                                echo date($_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i", $start_timestamp);
                                ?>
                            </td>
                            <td id="end:<?php echo $x['session']; ?>">
                                <?php
                                $end_timestamp = intval(substr($x["timeend"], 0, -3));
                                echo date($_COOKIE['timeformat'] == "12" ? "F d, Y h:ia" : "F d, Y H:i", $end_timestamp);
                                ?>
                            </td>
                            <td id="length:<?php echo $x['session']; ?>">
                                <?php
                                $duration = intval(($x["timeend"] - $x["time"]) / 1000);
                                echo gmdate("H:i:s", $duration);
                                ?>
                            </td>
                            <td id="size:<?php echo $x['session']; ?>" class="datapoints"><?php echo $x["sessionsize"]; ?></td>
                            <td id="profile:<?php echo $x['session']; ?>"><?php echo $x["profileName"]; ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
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
                    mergeSession();
                });

                $(".table-del-merge-pid tr").click(function(e) {
                    if (e.target.type !== "checkbox") {
                        $(":checkbox", this).trigger("click");
                    }
                });
            });

            function mergeSession() {
                var maximum = <?php echo isset($merge_max) ? $merge_max : 50000; ?>;
                var oversize = total > maximum;
                var dialogOpt = {
                    title : oversize ? "Result session is too big" : "Confirmation",
                    message: oversize ? `Merge maximum: ${maximum/1000}k datapoints<br>Selected: ${total/1000}k` : "Merge selected session(s) with session <?php echo $mergesession; ?>?",
                    btnClassSuccessText: oversize ? "OK" : "Yes",
                    btnClassFailText: "No",
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
?>

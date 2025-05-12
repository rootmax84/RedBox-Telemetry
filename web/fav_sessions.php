<?php

require_once('db.php');
require_once('auth_user.php');
require_once('creds.php');
require_once('db_limits.php');

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
include("head.php");
?>
    <body>
    <script>
        function removeFavorite(id) {
            $(".fetch-data").css("display", "block");
            fetch('favorite.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(data => {
                document.querySelector(`tr[data-sid="${id}"]`)?.remove();
                if ($('#fav-table tbody tr').length === 0) {
                    $('<h3 style="text-align:center"></h3>').text(localization.key['fav.empty']).insertAfter('#fav-table');
                    document.getElementById('update_desc').disabled = true;
                }
            })
            .catch(err => {
                serverError(err);
            })
            .finally(() => {
                $(".fetch-data").css("display", "none");
            });
        }

        function updateDescriptions() {
            $(".fetch-data").css("display", "block");
            const updates = [];

            document.querySelectorAll('td[contenteditable="true"][data-sid]').forEach(td => {
                const sessionId = td.getAttribute('data-sid');
                const newDescription = td.textContent.trim();
                updates.push({
                    id: sessionId,
                    description: newDescription
                });
            });

            if (updates.length === 0) {
                $(".fetch-data").css("display", "none");
                return;
            }

            fetch('favorite.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ updates: updates })
            })
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(data => {
                xhrResponse(localization.key['fav.desc.update']);
            })
            .catch(err => {
                serverError(err);
            })
            .finally(() => {
                $(".fetch-data").css("display", "none");
            });
        }

        // Initialize editable fields
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('update_desc').addEventListener('click', updateDescriptions);

            // Make description cells editable
            document.querySelectorAll('#fav-table tbody td:nth-child(4)').forEach(td => {
                td.setAttribute('contenteditable', 'true');
                td.setAttribute('data-sid', td.closest('tr').getAttribute('data-sid'));
            });
        });

        $(document).on('keydown paste', '[contenteditable="true"]', function(e) {
            let max = 64; // Maximum length for description
            let currentText = $(this).text();

            if (e.type === 'paste') {
                setTimeout(() => {
                    if ($(this).text().length > max) {
                        $(this).text(currentText.substring(0, max));
                    }
                }, 0);
                return true;
            }

            if (currentText.length >= max &&
                ![8, 46, 37, 38, 39, 40].includes(e.keyCode)) {
                return false;
            }
        });

        function sortFavorites() {
            if ($("head style.table-sort-indicators").length === 0) {
                $("<style>")
                .prop("type", "text/css")
                .html(`
                    th.sortable { cursor: pointer; }
                    th.sorted-asc::after { content: " ▲"; }
                    th.sorted-desc::after { content: " ▼"; }
                `)
                .appendTo("head");
            }

            $("#fav-table thead th").not(":first, :last").addClass("sortable");

            $("#fav-table thead th.sortable").click(function() {
                const table = $(this).closest("table");
                const columnIndex = $(this).index();
                const rows = table.find("tbody tr").get();

                const isAscending = !$(this).hasClass("sorted-asc");
                table.find("th").removeClass("sorted-asc sorted-desc");
                $(this).addClass(isAscending ? "sorted-asc" : "sorted-desc");

                rows.sort((a, b) => {
                    const valueA = getFavCellValue(a, columnIndex);
                    const valueB = getFavCellValue(b, columnIndex);

                    if (columnIndex === 1) {
                        const timeA = valueA.split(':').reduce((acc, time) => (60 * acc) + +time, 0);
                        const timeB = valueB.split(':').reduce((acc, time) => (60 * acc) + +time, 0);
                        return timeA - timeB;
                    }
                    else if (columnIndex === 2 || columnIndex === 3) {
                        return valueA.localeCompare(valueB);
                    }

                    return 0;
                });

                if (!isAscending) rows.reverse();
                table.find("tbody").empty().append(rows);
            });

            function getFavCellValue(row, index) {
                const cell = $(row).children("td").eq(index);
                return cell.text().trim();
            }
        }

        $(function() {
            sortFavorites();
        });
    </script>
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
        <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
            <div class="new-session"><a href='.' l10n='sess.new'></a></div>
            <div class="storage-usage-img" onclick></div>
            <label id="storage-usage" l10n='stor.usage'><span><?php echo $db_used;?></span></label>
        <?php } ?>
        <div class="container">
            <div id="theme-switch"></div>
            <div class="navbar-header">
                <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a>
                <span title="logout" class="navbar-brand logout" onClick="logout()"></span>
            </div>
        </div>
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
                            <span class='delete-icon' onclick="event.stopPropagation(); removeFavorite('<?php echo $keycol['session']; ?>')">&times;</span>
                            <?php
                                $start_timestamp = intval(substr($keycol['session'], 0, -3));
                                $month_num = date('n', $start_timestamp);
                                $month_key = 'month.' . strtolower(date('M', $start_timestamp));
                                $translated_month = $translations[$lang][$month_key];
                                $date = date($_COOKIE['timeformat'] == "12" ? "d, Y h:ia" : "d, Y H:i", $start_timestamp);
                                echo $translated_month . ' ' . $date;
                            ?>
                        </td>
                        <td>
                            <?php
                                echo formatDuration((int)$keycol['time'], (int)$keycol['timeend'], $lang);
                            ?>
                        </td>
                        <td><?php echo $keycol['profileName']; ?></td>
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
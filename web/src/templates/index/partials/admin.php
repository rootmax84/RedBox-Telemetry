<div class="admin-card">
    <div>
    <h4 style="text-align:center" l10n="admin.page.title"></h4>
<hr>
<div class="users-list">
<table>
 <thead>
  <tr>
    <th></th>
    <th l10n="admin.table.login"></th>
    <th l10n="admin.table.limit"></th>
    <th l10n="admin.table.size"></th>
    <th l10n="admin.table.ll"></th>
    <th l10n="admin.table.lu"></th>
    <th></th>
  </tr>
 </thead>
<tbody>

<?php
$page_first_result = ($page-1) * $results_per_page;
$usrqry = $db->query("SELECT COUNT(*) FROM $db_users");
$number_of_result = $usrqry->fetch_row()[0];
$number_of_page = ceil ($number_of_result / $results_per_page);

$res = $db->query("SELECT TABLE_SCHEMA AS '$db_name', ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db_name'")->fetch_array();
$r = $db->query("SELECT user, s, last_attempt FROM $db_users ORDER BY id = (SELECT MIN(id) FROM $db_users) DESC, user ASC  LIMIT " . $page_first_result . "," . $results_per_page);
$i = 0;
if ($r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        $username_row = htmlspecialchars($row["user"]);
        $isAdmin = ($username_row == $admin);
        $isDisabled = ($row["s"] == 0);
        $isUnlimited = ($row["s"] == -1);

        // DB size
        $dbSize = "-";
        if (!$isAdmin) {
            $db_sz = $db->query("SHOW TABLE STATUS LIKE '".$username_row.$db_log_prefix."'")->fetch_array();
            $dbSize = round(($db_sz[6] + $db_sz['Index_length']) / (1024 * 1024), 0);
        }

        // Last activity
        $lastActivity = "-";
        if (!$isAdmin) {
            $lastResult = $db->query("SELECT time FROM ".$username_row.$db_log_prefix." ORDER BY time DESC LIMIT 1")->fetch_array();
            if ($lastResult) {
                $seconds = intval($lastResult[0] / 1000);
                $timeFormat = $admin_timeformat_12 ? "Y-m-d h:i:sa" : "Y-m-d H:i:s";
                $lastActivity = date($timeFormat, $seconds);
            }
        }

        // Last in
        $lastAttempt = empty($row["last_attempt"]) ? "-" :
            date($admin_timeformat_12 ? "Y-m-d h:i:sa" : "Y-m-d H:i:s", strtotime($row["last_attempt"]));

        // limit format
        $lim = "-";
        if (!$isAdmin) {
            $lim = $isUnlimited ? "∞" : $row["s"];
        }

        // Username style and text
        $usernameDisplay = $username_row;
        $usernameStyle = "";
        if ($isAdmin) {
            $usernameDisplay .= " (admin)";
        } elseif ($isDisabled) {
            $usernameStyle = "text-decoration:line-through";
        }

        // Output table
        echo "<tr onclick='window.location=\"./users_admin.php?action=edit&user=" . urlencode($username_row) . "&limit=" . $row["s"] . "\";' data-username=".$username_row.">";
        echo "<td>" . $i++ . "</td>";
        echo "<td" . ($usernameStyle ? " style='$usernameStyle'" : "") . ">";
        if (!$isAdmin) {
            echo "<span class='delete-icon' onclick='event.stopPropagation(); adminUserDelete(\"$username_row\")'>&times;</span>";
        }
        echo $usernameDisplay;
        echo "</td>";
        echo "<td>" . $lim . "</td>";
        echo "<td>" . $dbSize . "</td>";
        echo "<td>" . $lastActivity . "</td>";
        echo "<td>" . $lastAttempt . "</td>";
        echo "</tr>";
    }
}
?>
</tbody>
</table>
</div>
<?php
$redis_connected = false;
$redis_stream_lag = null;
$redis_pending    = null;
$redis_workers_live = null;

if (!empty($redis_stream_enabled)) {
    $__r = get_redis_connection();
    if ($__r !== null) {
        $redis_connected = true;
        $streamKey = $redis_stream_key   ?? 'telemetry:uploads';
        $groupName = $redis_stream_group ?? 'telemetry-workers';

        try {
            $groups = $__r->xInfo('GROUPS', $streamKey);
            if (is_array($groups)) {
                foreach ($groups as $g) {
                    if (($g['name'] ?? null) === $groupName) {
                        $redis_stream_lag = isset($g['lag'])
                            ? (int)$g['lag']
                            : (int)($g['pending'] ?? 0);
                        break;
                    }
                }
            }

            $pending = $__r->xPending($streamKey, $groupName);
            $redis_pending = is_array($pending)
                ? (int)($pending['pending'] ?? 0)
                : null;

            $consumers = $__r->xInfo('CONSUMERS', $streamKey, $groupName);
            if (is_array($consumers)) {
                $live = 0;
                foreach ($consumers as $c) {
                    $idleMs = (int)($c['idle'] ?? PHP_INT_MAX);
                    if ($idleMs < 30000) {
                        $live++;
                    }
                }
                $redis_workers_live = $live;
            } else {
                $redis_workers_live = 0;
            }
        } catch (Throwable $e) {
            // группа ещё не создана или redis недоступен — оставляем null
        }
    }
}
?>
<div class="db-size">
<?php
    $yes = "✔️";
    $no  = "❌";
    $mb  = $translations[$lang]['admin.mb'];

    $redis_part = "Redis: " . ($redis_connected ? $yes : $no);
    if ($redis_connected) {
        if ($redis_stream_lag !== null) {
            $redis_part .= " (backlog: {$redis_stream_lag}";
            if ($redis_pending !== null && $redis_pending > 0) {
                $redis_part .= ", pending: {$redis_pending}";
            }
            $redis_part .= ")";
        } else {
            $redis_part .= " (group not initialised)";
        }
    }

    $worker_part = "Workers: ";
    if (!$redis_connected || $redis_workers_live === null) {
        $worker_part .= "n/a";
    } elseif ($redis_workers_live === 0) {
        $worker_part .= $no . " 0";
    } elseif ($redis_stream_lag !== null && $redis_stream_lag > 0 && $redis_workers_live < 2) {
        $worker_part .= "⚠️ {$redis_workers_live}";
    } else {
        $worker_part .= $yes . " {$redis_workers_live}";
    }

    echo "<ul style='margin:0'>"
       . "<li>Memcached: " . ($memcached_connected ? $yes : $no) . "</li>"
       . "<li>" . $redis_part . "</li>"
       . "<li>" . $worker_part . "</li>"
       . "<li>" . $translations[$lang]['admin.db'] . ": " . round($res[1]) . $mb . "</li>"
       . "</ul>";
?>
</div>
<hr>
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
    echo '<a class="pages" href="?page=1">&#171;</a> ';
}
if ($current_page > 1) {
    $previous_page = $current_page - 1;
    echo '<a class="pages" href="?page=' . $previous_page . '">&#60;</a> ';
}
for ($page_num = $start; $page_num <= $end; $page_num++) {
    if ($number_of_result < $results_per_page) break;
    if ($page_num == $current_page) {
        echo '<a class="current-page" href="?page=' . $page_num . '">' . $page_num . ' </a>';
    } else {
        echo '<a class="pages" href="?page=' . $page_num . '">' . $page_num . ' </a>';
    }
}
if ($current_page < $total_pages) {
    $next_page = $current_page + 1;
    echo ' <a class="pages" href="?page=' . $next_page . '">&#62;</a>';
}
if ($current_page < $total_pages) {
    echo ' <a class="pages" href="?page=' . $total_pages . '">&#187;</a>';
}
?>
</div>
</div>

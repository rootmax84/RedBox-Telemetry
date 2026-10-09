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
    <th l10n="admin.table.sessions"></th>
    <th l10n="admin.table.ll"></th>
    <th l10n="admin.table.lu"></th>
    <th></th>
  </tr>
 </thead>
<tbody>

<?php
$page_first_result = ($page - 1) * $results_per_page;
$usrqry = $db->query("SELECT COUNT(*) FROM users");
$number_of_result = $usrqry->fetch_row()[0];
$number_of_page = ceil($number_of_result / $results_per_page);

// Общий размер БД — используется в сводке ниже (DB total).
$db_name_esc = $db->real_escape_string($db_name);
$res = $db->query(
    "SELECT TABLE_SCHEMA AS `schema`,
            ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS `size_mb`
       FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = '$db_name_esc'"
)->fetch_array();

// Счётчики сессий по всем юзерам одним запросом — быстрее, чем N COUNT'ов в цикле
$session_stats = [];
$cnt_r = $db->query(
    "SELECT user_id, COUNT(*) AS c, MAX(timeend) AS last_time
       FROM sessions
      GROUP BY user_id"
);
while ($cr = $cnt_r->fetch_assoc()) {
    $session_stats[(int)$cr['user_id']] = [
        'count' => (int)$cr['c'],
        'last'  => (int)$cr['last_time'],
    ];
}

$r = $db->query(
    "SELECT id, user, s, last_attempt
       FROM users
      ORDER BY id = (SELECT MIN(id) FROM users) DESC, user ASC
      LIMIT " . (int)$page_first_result . "," . (int)$results_per_page
);

$i = 0;
if ($r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        $uid          = (int)$row['id'];
        $username_row = htmlspecialchars($row['user']);
        $isAdmin      = ($username_row == $admin);
        $isDisabled   = ($row['s'] == 0);
        $isUnlimited  = ($row['s'] == -1);

        // Sessions used / limit
        $sessionsDisplay = "-";
        $sessionsStyle   = "";
        if (!$isAdmin) {
            $session_count = $session_stats[$uid]['count'] ?? 0;

            if ($isUnlimited) {
                $sessionsDisplay = $session_count . " / ∞";
            } elseif ($isDisabled) {
                $sessionsDisplay = $session_count . " / 0";
            } else {
                $sessionsDisplay = $session_count . " / " . (int)$row['s'];

                // Подсветка при приближении к лимиту
                $pct = $row['s'] > 0 ? ($session_count / $row['s']) * 100 : 0;
                if ($pct >= 90) {
                    $sessionsStyle = " style='color:#c0392b;font-weight:bold'";
                } elseif ($pct >= 70) {
                    $sessionsStyle = " style='color:#d68910'";
                }
            }
        }

        $lastActivity = "-";
        if (!$isAdmin) {
            $stats = $session_stats[$uid] ?? null;
            if ($stats && $stats['last'] > 0) {
                $seconds = intval($stats['last'] / 1000);
                $timeFormat = $admin_timeformat_12 ? "Y-m-d h:i:sa" : "Y-m-d H:i:s";
                $lastActivity = date($timeFormat, $seconds);
            }
        }

        // Last login attempt
        $lastAttempt = empty($row['last_attempt'])
            ? "-"
            : date(
                $admin_timeformat_12 ? "Y-m-d h:i:sa" : "Y-m-d H:i:s",
                strtotime($row['last_attempt'])
            );

        // Username display
        $usernameDisplay = $username_row;
        $usernameStyle   = "";
        if ($isAdmin) {
            $usernameDisplay .= " (admin)";
        } elseif ($isDisabled) {
            $usernameStyle = "text-decoration:line-through";
        }

        // Output row
        echo "<tr onclick='window.location=\"/admin/users?action=edit&user="
             . urlencode($username_row) . "&limit=" . (int)$row['s'] . "\";'"
             . " data-username=\"" . $username_row . "\">";
        echo "<td>" . $i++ . "</td>";
        echo "<td" . ($usernameStyle ? " style='$usernameStyle'" : "") . ">";
        if (!$isAdmin) {
            echo "<span class='delete-icon' onclick='event.stopPropagation(); adminUserDelete(\""
                 . $username_row . "\")'>&times;</span>";
        }
        echo $usernameDisplay;
        echo "</td>";
        echo "<td{$sessionsStyle}>" . $sessionsDisplay . "</td>";
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
$redis_connected    = false;
$redis_stream_lag   = null;
$redis_pending      = null;
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

            $live = 0;

            try {
                $hb_count = 0;
                $it = null;
                while (true) {
                    $keys = $__r->scan($it, 'worker:hb:*', 100);
                    if ($keys === false) break;
                    $hb_count += count($keys);
                    if ((int)$it === 0) break;
                }

                if ($hb_count > 0) {
                    $live = $hb_count;
                } else {
                    $consumers = $__r->xInfo('CONSUMERS', $streamKey, $groupName);
                        if (is_array($consumers)) {
                            foreach ($consumers as $c) {
                                $idleMs = (int)($c['idle'] ?? PHP_INT_MAX);
                            if ($idleMs < 30000) $live++;
                        }
                    }
                }
            } catch (Throwable $e) {
                // Не валим страницу из-за проблем с Redis
                $live = $redis_workers_live ?? 0;
            }

            $redis_workers_live = $live;

            // ── Heavy tasks queue stats (ratel:heavy_tasks) ──
            $redis_heavy_lag     = null;
            $redis_heavy_pending = null;
            $redis_heavy_total   = null;

            $heavyStreamKey = $redis_heavy_stream_key ?? 'ratel:heavy_tasks';

            try {
                // Стрим может ещё не существовать — ни одной задачи не было
                if ($__r->exists($heavyStreamKey)) {
                    $heavyGroups = $__r->xInfo('GROUPS', $heavyStreamKey);
                    if (is_array($heavyGroups)) {
                        foreach ($heavyGroups as $hg) {
                            if (($hg['name'] ?? null) === $groupName) {
                                $redis_heavy_lag     = isset($hg['lag'])
                                    ? (int)$hg['lag']
                                    : (int)($hg['pending'] ?? 0);
                                $redis_heavy_pending = (int)($hg['pending'] ?? 0);
                                $redis_heavy_total   = $redis_heavy_lag + $redis_heavy_pending;
                                break;
                            }
                        }
                    }
                } else {
                    // Стрима нет — задач 0
                    $redis_heavy_lag     = 0;
                    $redis_heavy_pending = 0;
                    $redis_heavy_total   = 0;
                }
            } catch (Throwable $e) {
                // не валим страницу из-за проблем с Redis
            }
        } catch (Throwable $e) {
            // группа ещё не создана или redis недоступен
        }
    }
}
?>

<div class="db-size">
<?php
    $yes = "✔️";
    $no  = "❌";
    $mb  = $translations[$lang]['admin.mb'];

    $redis_part = "Redis: " . ($redis_connected ? $yes : $no)
        . " <a href='/admin/redis'>📊</a>";
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

    $db_tasks_value = "n/a";
    if ($redis_connected && $redis_heavy_total !== null) {
        $db_tasks_value = (string)$redis_heavy_total;
        if ($redis_heavy_pending > 0) {
            $db_tasks_value .= " ({$redis_heavy_pending} "
                         . $translations[$lang]['admin.db.running'] . ")";
        }
    }

    echo "<ul style='margin:0;list-style:disc'>"
       . "<li>" . $redis_part . "</li>"
       . "<li>" . $worker_part . "</li>"
       . "<li>" . $translations[$lang]['admin.db.title']
        . "<ul style='margin:0;padding-left:18px;list-style:\"- \"'>"
            . "<li>" . $translations[$lang]['admin.db.tasks'] . ": " . $db_tasks_value . "</li>"
            . "<li>" . $translations[$lang]['admin.db.size']  . ": " . round($res[1]) . $mb . "</li>"
        . "</ul>"
       . "</li>"
       . "</ul>";
?>
</div>
<hr>
<div class="pages">
<?php // Pagination
$current_page       = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$total_pages        = $number_of_page;
$page_numbers_limit = 10;
$start = $current_page - floor($page_numbers_limit / 2);
$end   = $current_page + floor($page_numbers_limit / 2);

if ($start < 1) {
    $start = 1;
    $end   = min($page_numbers_limit, $total_pages);
}
if ($end > $total_pages) {
    $end   = $total_pages;
    $start = max(1, $total_pages - $page_numbers_limit + 1);
}

if ($current_page > 1) {
    echo '<a class="pages" href="?page=1">&#171;</a> ';
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
    echo ' <a class="pages" href="?page=' . $total_pages . '">&#187;</a>';
}
?>
</div>
</div>
</div>

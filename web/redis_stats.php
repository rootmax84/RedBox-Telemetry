<?php
/**
 * redis_stats.php — administrator diagnostics page for Redis.
 *
 * Read-only except for a small set of admin actions:
 *   - purge DLQ             (DEL ratel:dead_letters)
 *   - replay DLQ            (XRANGE → XADD into _src_stream → XDEL)
 *   - kill dead consumers   (bulk XGROUP DELCONSUMER, only pending=0 && idle>60s)
 *
 * CSRF is not checked on this file — add it to $csrf_exempt_scripts
 * in auth_user.php. Access is restricted to admin sessions only.
 */
require_once __DIR__ . '/src/db.php';

if (!isset($_SESSION['admin'])) {
    header('Location: .');
    exit;
}

/* ────────────────────────────────────────────────────────────
 * Formatting helpers
 * ──────────────────────────────────────────────────────────── */
function rs_human_duration(int $s): string {
    if ($s < 0) return 'n/a';
    if ($s < 60) return $s . 's';
    if ($s < 3600) return intdiv($s, 60) . 'm ' . ($s % 60) . 's';
    if ($s < 86400) return intdiv($s, 3600) . 'h ' . intdiv($s % 3600, 60) . 'm';
    return intdiv($s, 86400) . 'd ' . intdiv($s % 86400, 3600) . 'h';
}
function rs_human_bytes(int $b): string {
    if ($b <= 0) return '0 B';
    if ($b < 1024) return $b . ' B';
    if ($b < 1048576) return round($b / 1024, 2) . ' KB';
    if ($b < 1073741824) return round($b / 1048576, 2) . ' MB';
    return round($b / 1073741824, 2) . ' GB';
}
function rs_age(int $ts): string {
    if ($ts <= 0) return 'n/a';
    return rs_human_duration(time() - $ts) . ' ago';
}
function rs_id_ts(string $id): int {
    $p = explode('-', $id, 2);
    $ms = (int)($p[0] ?? 0);
    return $ms > 0 ? intdiv($ms, 1000) : 0;
}
function rs_h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function rs_entry_to_assoc(array $fields): array {
    $kv = [];
    $n  = count($fields);
    for ($i = 0; $i < $n; $i += 2) {
        $k = (string)($fields[$i] ?? '');
        if ($k === '') continue;
        $kv[$k] = (string)($fields[$i + 1] ?? '');
    }
    return $kv;
}

/* ────────────────────────────────────────────────────────────
 * Redis connection + config
 * ──────────────────────────────────────────────────────────── */
$r = get_redis_connection();

global $redis_stream_key, $redis_stream_group,
       $redis_stream_maxlen,
       $redis_heavy_stream_key, $redis_dlq_key,
       $db, $db_users;

$uploadsKey = $redis_stream_key       ?? 'telemetry:uploads';
$heavyKey   = $redis_heavy_stream_key ?? 'ratel:heavy_tasks';
$dlqKey     = $redis_dlq_key          ?? 'ratel:dead_letters';
$groupName  = $redis_stream_group     ?? 'telemetry-workers';

const RS_DEAD_IDLE_SECONDS  = 60;
const RS_DLQ_ROW_LIMIT      = 500;
const RS_PER_USER_ROW_LIMIT = 500;
const RS_TASK_SCAN_LIMIT    = 5000;

/* ────────────────────────────────────────────────────────────
 * Action handling (POST → 303 redirect)
 * ──────────────────────────────────────────────────────────── */
$action = $_POST['action'] ?? null;

if ($action !== null) {
    $ok  = false;
    $msg = '';

    if ($r === null) {
        $msg = 'Redis connection unavailable';
    } else {
        try {
            switch ($action) {

                case 'purge_dlq': {
                    $n = 0;
                    try { $n = (int)$r->xLen($dlqKey); } catch (Throwable $e) {}
                    $r->del($dlqKey);
                    $ok  = true;
                    $msg = "Purged DLQ — {$n} message(s) dropped";
                    break;
                }

                case 'replay_dlq': {
                    $limit   = 1000;
                    $entries = $r->xRange($dlqKey, '-', '+', $limit);
                    if (!is_array($entries)) $entries = [];

                    $skipped  = ['_src_stream', '_src_id', '_error', '_failed_at', '_consumer'];
                    $replayed = 0;
                    $failed   = 0;

                    foreach ($entries as $e) {
                        $id     = $e[0] ?? null;
                        $fields = $e[1] ?? null;
                        if (!$id || !is_array($fields)) { $failed++; continue; }

                        $kv = rs_entry_to_assoc($fields);
                        if (empty($kv)) { $failed++; continue; }

                        $target = !empty($kv['_src_stream']) ? $kv['_src_stream'] : $uploadsKey;

                        $out = [];
                        foreach ($kv as $k => $v) {
                            if (in_array($k, $skipped, true)) continue;
                            $out[$k] = $v;
                        }
                        if (empty($out)) { $failed++; continue; }

                        try {
                            $maxlen = (int)($redis_stream_maxlen ?? 0);
                            $newId  = $r->xAdd($target, '*', $out, $maxlen, true);
                            if ($newId !== false) {
                                $r->xDel($dlqKey, [$id]);
                                $replayed++;
                            } else {
                                $failed++;
                            }
                        } catch (Throwable $e) {
                            $failed++;
                        }
                    }

                    $ok  = true;
                    $msg = "Replayed {$replayed} message(s)";
                    if ($failed > 0) $msg .= ", {$failed} failed";
                    if (count($entries) >= $limit) {
                        $msg .= " (limited to {$limit}; press again for more)";
                    }
                    break;
                }

                case 'kill_dead_consumers': {
                    $killed  = 0;
                    $skipped = 0;

                    foreach ([$uploadsKey, $heavyKey] as $stream) {
                        try { $cs = $r->xInfo('CONSUMERS', $stream, $groupName); }
                        catch (Throwable $e) { continue; }
                        if (!is_array($cs)) continue;

                        foreach ($cs as $c) {
                            $name    = (string)($c['name'] ?? '');
                            $idleMs  = (int)($c['idle']    ?? 0);
                            $pending = (int)($c['pending'] ?? 0);

                            if ($name === '')                          { $skipped++; continue; }
                            if ($pending > 0)                          { $skipped++; continue; }
                            if ($idleMs < RS_DEAD_IDLE_SECONDS * 1000) { $skipped++; continue; }

                            try {
                                $r->xGroup('DELCONSUMER', $stream, $groupName, $name);
                                $killed++;
                            } catch (Throwable $e) { $skipped++; }
                        }
                    }

                    $ok  = true;
                    $msg = "Killed {$killed} dead consumer(s)";
                    if ($skipped > 0) $msg .= ", {$skipped} skipped";
                    break;
                }

                default:
                    throw new RuntimeException('Unknown action: ' . $action);
            }
        } catch (Throwable $e) {
            $ok  = false;
            $msg = $e->getMessage();
        }
    }

    header('Location: redis_stats.php?ok=' . ($ok ? '1' : '0')
        . '&msg=' . urlencode($msg));
    exit;
}

/* ────────────────────────────────────────────────────────────
 * Collect stats
 * ──────────────────────────────────────────────────────────── */
$stats = [
    'connected'      => false, 'error' => null, 'info' => [],
    'streams'        => [], 'consumers' => [], 'consumers_dead' => 0,
    'workers'        => [],
    'tasks'          => ['hashes' => 0, 'by_status' => [], 'user_indexes' => 0],
    'attempts'       => 0, 'dlq_exists' => false,
];

if ($r === null) {
    $stats['error'] = 'Redis connection unavailable (see src/creds.php → $redis_enabled)';
} else {
    $stats['connected'] = true;

    /* 1. INFO */
    try {
        $info = $r->info();
        if (is_array($info)) {
            $stats['info'] = [
                'version'          => $info['redis_version']           ?? '?',
                'role'             => $info['role']                    ?? '?',
                'uptime'           => (int)($info['uptime_in_seconds'] ?? 0),
                'used_memory'      => (int)($info['used_memory']       ?? 0),
                'used_memory_peak' => (int)($info['used_memory_peak']  ?? 0),
                'maxmemory'        => (int)($info['maxmemory']         ?? 0),
                'clients'          => (int)($info['connected_clients'] ?? 0),
                'total_commands'   => (int)($info['total_commands_processed'] ?? 0),
            ];
        }
    } catch (Throwable $e) {}

    /* 2. Streams */
    $streamDefs = [
        ['key' => $uploadsKey, 'label' => 'Uploads',      'group' => $groupName],
        ['key' => $heavyKey,   'label' => 'Heavy tasks',  'group' => $groupName],
        ['key' => $dlqKey,     'label' => 'Dead letters', 'group' => null],
    ];
    foreach ($streamDefs as $def) {
        $entry = [
            'key' => $def['key'], 'label' => $def['label'],
            'exists' => false, 'length' => 0,
            'first_id' => null, 'first_ts' => null,
            'last_id'  => null, 'last_ts'  => null,
            'group' => null, 'lag' => null, 'pending' => null,
            'consumers' => null, 'last_del_id' => null, 'error' => null,
        ];
        try {
            if (!$r->exists($def['key'])) { $stats['streams'][] = $entry; continue; }
            $entry['exists'] = true;
            if ($def['key'] === $dlqKey) $stats['dlq_exists'] = true;

            $si = $r->xInfo('STREAM', $def['key']);
            if (is_array($si)) {
                $entry['length'] = (int)($si['length'] ?? 0);
                if (!empty($si['first-entry'][0])) {
                    $entry['first_id'] = $si['first-entry'][0];
                    $entry['first_ts'] = rs_id_ts($si['first-entry'][0]);
                }
                if (!empty($si['last-entry'][0])) {
                    $entry['last_id'] = $si['last-entry'][0];
                    $entry['last_ts'] = rs_id_ts($si['last-entry'][0]);
                }
            }
            if ($def['group'] !== null) {
                $groups = $r->xInfo('GROUPS', $def['key']);
                if (is_array($groups)) {
                    foreach ($groups as $g) {
                        if (($g['name'] ?? null) === $def['group']) {
                            $entry['group']       = $def['group'];
                            $entry['lag']         = isset($g['lag']) ? (int)$g['lag'] : null;
                            $entry['pending']     = (int)($g['pending']   ?? 0);
                            $entry['consumers']   = (int)($g['consumers'] ?? 0);
                            $entry['last_del_id'] = $g['last-delivered-id'] ?? null;
                            break;
                        }
                    }
                }
            }
        } catch (Throwable $e) { $entry['error'] = $e->getMessage(); }
        $stats['streams'][] = $entry;
    }

    /* 3. Consumers */
    foreach ([$uploadsKey, $heavyKey] as $sKey) {
        try {
            $cs = $r->xInfo('CONSUMERS', $sKey, $groupName);
            if (is_array($cs)) {
                foreach ($cs as $c) {
                    $pending = (int)($c['pending'] ?? 0);
                    $idleMs  = (int)($c['idle']    ?? 0);
                    $isDead  = ($pending === 0) && ($idleMs >= RS_DEAD_IDLE_SECONDS * 1000);
                    $stats['consumers'][] = [
                        'stream' => $sKey,
                        'name'   => (string)($c['name'] ?? '?'),
                        'pending' => $pending, 'idle_ms' => $idleMs, 'dead' => $isDead,
                    ];
                    if ($isDead) $stats['consumers_dead']++;
                }
            }
        } catch (Throwable $e) {}
    }

    /* 4. Worker heartbeats */
    try {
        $it = null;
        while (true) {
            $keys = $r->scan($it, 'worker:hb:*', 100);
            if ($keys === false) break;
            foreach ($keys as $k) {
                $ttl = $r->ttl($k);
                $val = $r->get($k);
                $stats['workers'][] = [
                    'consumer' => substr($k, strlen('worker:hb:')),
                    'ttl'      => is_int($ttl) ? $ttl : -1,
                    'hb_at'    => ($val !== false) ? (int)$val : null,
                ];
            }
            if ((int)$it === 0) break;
        }
        usort($stats['workers'], fn($a, $b) => strcmp($a['consumer'], $b['consumer']));
    } catch (Throwable $e) {}

    /* 5. Heavy tasks — hashes */
    $heavyByUid = []; $heavyOrphans = 0;
    try {
        $it = null; $byStatus = []; $hashes = 0;
        while (true) {
            $keys = $r->scan($it, 'ratel:task:*', 500);
            if ($keys === false) break;
            $hashes += count($keys);
            foreach ($keys as $k) {
                $owner  = (int)$r->hGet($k, 'owner_user_id');
                $status = (string)($r->hGet($k, 'status') ?: 'unknown');
                $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

                if ($owner <= 0) { $heavyOrphans++; continue; }
                if (!isset($heavyByUid[$owner])) {
                    $heavyByUid[$owner] = ['total' => 0, 'by_status' => []];
                }
                $heavyByUid[$owner]['total']++;
                $heavyByUid[$owner]['by_status'][$status] =
                    ($heavyByUid[$owner]['by_status'][$status] ?? 0) + 1;

                if ($hashes >= RS_TASK_SCAN_LIMIT) break 2;
            }
            if ((int)$it === 0) break;
        }
        $stats['tasks']['hashes']    = $hashes;
        $stats['tasks']['by_status'] = $byStatus;
        $stats['tasks']['orphans']   = $heavyOrphans;
    } catch (Throwable $e) {}

    /* 6. Active user task indexes */
    $activeTasksByUid = [];
    try {
        $it = null; $n = 0;
        while (true) {
            $keys = $r->scan($it, 'ratel:user_tasks:*', 500);
            if ($keys === false) break;
            $n += count($keys);
            foreach ($keys as $k) {
                $uid = (int)substr($k, strlen('ratel:user_tasks:'));
                if ($uid <= 0) continue;
                try { $cnt = (int)$r->zCard($k); } catch (Throwable $e) { $cnt = 0; }
                $activeTasksByUid[$uid] = ($activeTasksByUid[$uid] ?? 0) + $cnt;
            }
            if ((int)$it === 0) break;
        }
        $stats['tasks']['user_indexes'] = $n;
    } catch (Throwable $e) {}

    /* 7. Attempt counters */
    try {
        $it = null; $n = 0;
        while (true) {
            $keys = $r->scan($it, 'ratel:attempts:*', 500);
            if ($keys === false) break;
            $n += count($keys);
            if ((int)$it === 0) break;
        }
        $stats['attempts'] = $n;
    } catch (Throwable $e) {}

    /* 8. Fresh per-user counters */
    $freshByUser = [];
    foreach ([
        ['prefix' => 'rate_limit_', 'field' => 'rate_limit'],
        ['prefix' => 'new_session_', 'field' => 'new_session_ttl'],
        ['prefix' => 'worker_user_', 'field' => 'worker_cache_ttl'],
    ] as $p) {
        try {
            $it = null;
            while (true) {
                $keys = $r->scan($it, $p['prefix'] . '*', 200);
                if ($keys === false) break;
                foreach ($keys as $k) {
                    $username = substr($k, strlen($p['prefix']));
                    if ($username === '') continue;
                    if (!isset($freshByUser[$username])) $freshByUser[$username] = [];
                    $ttl = $r->ttl($k);
                    if ($p['field'] === 'rate_limit') {
                        $val = $r->get($k);
                        $freshByUser[$username]['rate_limit'] = [
                            'value' => is_numeric($val) ? (int)$val : 0,
                            'ttl'   => is_int($ttl) ? $ttl : -1,
                        ];
                    } else {
                        $freshByUser[$username][$p['field']] = is_int($ttl) ? $ttl : -1;
                    }
                }
                if ((int)$it === 0) break;
            }
        } catch (Throwable $e) {}
    }

    /* 9. DLQ entries + per-user aggregate */
    $dlqAll = []; $dlqByUser = [];
    if ($stats['dlq_exists']) {
        try {
            $raw = $r->xRange($dlqKey, '-', '+', RS_DLQ_ROW_LIMIT);
            if (is_array($raw)) {
                foreach ($raw as $e) {
                    $id     = $e[0] ?? null;
                    $fields = $e[1] ?? null;
                    if (!$id || !is_array($fields)) continue;
                    $kv = rs_entry_to_assoc($fields);
                    $u  = $kv['user'] ?? '';
                    $entry = [
                        'id'         => $id,
                        'user'       => $u !== '' ? $u : '-',
                        'kind'       => $kv['kind']        ?? '-',
                        'error'      => $kv['_error']      ?? '-',
                        'failed_at'  => (int)($kv['_failed_at'] ?? 0),
                        'src_stream' => $kv['_src_stream'] ?? '-',
                        'src_id'     => $kv['_src_id']     ?? '-',
                        'consumer'   => $kv['_consumer']   ?? '-',
                    ];
                    $dlqAll[] = $entry;
                    if ($u !== '') {
                        if (!isset($dlqByUser[$u])) $dlqByUser[$u] = ['count' => 0, 'last_ts' => 0];
                        $dlqByUser[$u]['count']++;
                        if ($entry['failed_at'] > $dlqByUser[$u]['last_ts']) {
                            $dlqByUser[$u]['last_ts'] = $entry['failed_at'];
                        }
                    }
                }
            }
        } catch (Throwable $e) {}
    }

    /* 10. uid ↔ username map */
    $userRows = [];
    try {
        $res = $db->query("SELECT id, user FROM `$db_users`");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $userRows[(int)$row['id']] = ['id' => (int)$row['id'], 'user' => (string)$row['user']];
            }
        }
    } catch (Throwable $e) {}

    /* 11. Assemble per-user aggregate */
    $perUser = [];
    foreach ($userRows as $uid => $row) {
        $perUser[$row['user']] = [
            'uid' => $uid,
            'rate_limit' => null, 'new_session_ttl' => null, 'worker_cache_ttl' => null,
            'dlq_count' => 0, 'dlq_last_ts' => 0,
            'heavy_total' => 0, 'heavy_by_status' => [],
            'user_tasks_active' => 0,
        ];
    }
    foreach ($freshByUser as $username => $data) {
        if (!isset($perUser[$username])) {
            $perUser[$username] = [
                'uid' => null,
                'rate_limit' => null, 'new_session_ttl' => null, 'worker_cache_ttl' => null,
                'dlq_count' => 0, 'dlq_last_ts' => 0,
                'heavy_total' => 0, 'heavy_by_status' => [],
                'user_tasks_active' => 0,
            ];
        }
        if (isset($data['rate_limit']))       $perUser[$username]['rate_limit']       = $data['rate_limit'];
        if (isset($data['new_session_ttl']))  $perUser[$username]['new_session_ttl']  = $data['new_session_ttl'];
        if (isset($data['worker_cache_ttl'])) $perUser[$username]['worker_cache_ttl'] = $data['worker_cache_ttl'];
    }
    foreach ($dlqByUser as $username => $data) {
        if (!isset($perUser[$username])) {
            $perUser[$username] = [
                'uid' => null,
                'rate_limit' => null, 'new_session_ttl' => null, 'worker_cache_ttl' => null,
                'dlq_count' => 0, 'dlq_last_ts' => 0,
                'heavy_total' => 0, 'heavy_by_status' => [],
                'user_tasks_active' => 0,
            ];
        }
        $perUser[$username]['dlq_count']   = $data['count'];
        $perUser[$username]['dlq_last_ts'] = $data['last_ts'];
    }
    foreach ($heavyByUid as $uid => $data) {
        $uname = $userRows[$uid]['user'] ?? null;
        if ($uname === null || !isset($perUser[$uname])) continue;
        $perUser[$uname]['heavy_total']     = $data['total'];
        $perUser[$uname]['heavy_by_status'] = $data['by_status'];
    }
    foreach ($activeTasksByUid as $uid => $cnt) {
        $uname = $userRows[$uid]['user'] ?? null;
        if ($uname === null || !isset($perUser[$uname])) continue;
        $perUser[$uname]['user_tasks_active'] = $cnt;
    }
    $stats['per_user'] = $perUser;
}

/* ────────────────────────────────────────────────────────────
 * DLQ filtered view (GET ?user=...)
 * ──────────────────────────────────────────────────────────── */
$dlqFilter  = trim((string)($_GET['user'] ?? ''));
$dlqEntries = [];
foreach (($dlqAll ?? []) as $e) {
    if ($dlqFilter !== '' && $e['user'] !== $dlqFilter) continue;
    $dlqEntries[] = $e;
}
$dlqUsers = [];
foreach (($dlqAll ?? []) as $e) {
    if ($e['user'] !== '-') $dlqUsers[$e['user']] = true;
}
$dlqUsers = array_keys($dlqUsers); sort($dlqUsers);

/* ────────────────────────────────────────────────────────────
 * Per-user list
 * ──────────────────────────────────────────────────────────── */
$perUserWithActivity = [];
$perUserTotal        = count($stats['per_user'] ?? []);
foreach (($stats['per_user'] ?? []) as $username => $u) {
    $hasFresh      = $u['rate_limit'] !== null
                  || $u['new_session_ttl'] !== null
                  || $u['worker_cache_ttl'] !== null;
    $hasHistorical = $u['dlq_count'] > 0
                  || $u['heavy_total'] > 0
                  || $u['user_tasks_active'] > 0;
    if (!$hasFresh && !$hasHistorical) continue;
    $u['username'] = $username;
    $u['score']    = ($u['rate_limit']       !== null ? 1 : 0)
                   + ($u['new_session_ttl']  !== null ? 1 : 0)
                   + ($u['worker_cache_ttl'] !== null ? 1 : 0)
                   + ($u['dlq_count'] > 0 ? 2 : 0)
                   + ($u['heavy_total'] > 0 ? 2 : 0)
                   + ($u['user_tasks_active'] > 0 ? 2 : 0);
    $perUserWithActivity[] = $u;
}
usort($perUserWithActivity, function ($a, $b) {
    if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
    return strcmp($a['username'], $b['username']);
});
$perUserShown  = array_slice($perUserWithActivity, 0, RS_PER_USER_ROW_LIMIT);
$perUserCapped = count($perUserWithActivity) > RS_PER_USER_ROW_LIMIT;

/* ────────────────────────────────────────────────────────────
 * Badge helper
 * ──────────────────────────────────────────────────────────── */
function rs_badge(int|string $v, int $warnAt, int $errAt, string $suffix = ''): string {
    if (!is_numeric($v)) return '<span class="rs-badge neutral">' . rs_h((string)$v) . '</span>';
    $n = (int)$v;
    $cls = 'ok';
    if ($n >= $errAt)      $cls = 'err';
    elseif ($n >= $warnAt) $cls = 'warn';
    return '<span class="rs-badge ' . $cls . '">' . $n . rs_h($suffix) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Redis statistics</title>

<script>
(function () {
    var choice;
    try { choice = localStorage.getItem('rs_theme') || 'auto'; }
    catch (e) { choice = 'auto'; }

    var resolved;
    if (choice === 'light' || choice === 'dark') {
        resolved = choice;
    } else {
        resolved = (window.matchMedia
            && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-theme', resolved);
})();
</script>

<style>
* { box-sizing: border-box; }

:root {
    --bg: #f5f5f7;
    --fg: #1d1d1f;
    --sub: #6e6e73;
    --dim: #86868b;
    --link: #0a84ff;

    --card-bg: #fff;
    --card-border: #e2e2e7;
    --card-shadow: 0 1px 2px rgba(0,0,0,.03);
    --card-err-bg: #fdecea;
    --card-err-border: #e74c3c;

    --toast-ok-bg: #d1f5d6;
    --toast-ok-fg: #0a6928;
    --toast-ok-border: #a3e0ae;
    --toast-err-bg: #f8d7da;
    --toast-err-fg: #842029;
    --toast-err-border: #f0aeb2;

    --err-inline: #c0392b;
    --error-cell: #842029;

    --table-th-bg: #fafafc;
    --table-th-fg: #6e6e73;
    --table-border: #ececf0;
    --table-hover: #fafafc;

    --badge-ok-bg: #d1f5d6;       --badge-ok-fg: #0a6928;
    --badge-warn-bg: #fff3cd;     --badge-warn-fg: #8a6100;
    --badge-err-bg: #f8d7da;      --badge-err-fg: #842029;
    --badge-neutral-bg: #e9ecef;  --badge-neutral-fg: #495057;

    --btn-bg: #fff;               --btn-fg: #1d1d1f;        --btn-border: #d1d1d6;
    --btn-hover-bg: #f5f5f7;
    --btn-danger-bg: #fdf2f3;     --btn-danger-fg: #842029; --btn-danger-border: #f0aeb2;
    --btn-danger-hover-bg: #f8d7da;

    --input-bg: #fff;             --input-fg: #1d1d1f;      --input-border: #d1d1d6;
}

[data-theme="dark"] {
    --bg: #1a1a1c;
    --fg: #e8e8ec;
    --sub: #9a9aa0;
    --dim: #86868b;

    --card-bg: #26262a;
    --card-border: #35353a;
    --card-shadow: none;
    --card-err-bg: #3a1f1f;
    --card-err-border: #843029;

    --toast-ok-bg: #0a3a1a;   --toast-ok-fg: #7dffa0;  --toast-ok-border: #0a6928;
    --toast-err-bg: #3a0a0a;  --toast-err-fg: #ff9d9d; --toast-err-border: #843029;

    --err-inline: #ff9d9d;
    --error-cell: #ff9d9d;

    --table-th-bg: #2e2e33;
    --table-th-fg: #9a9aa0;
    --table-border: #35353a;
    --table-hover: #2e2e33;

    --badge-ok-bg: #0a3a1a;       --badge-ok-fg: #7dffa0;
    --badge-warn-bg: #3a2e0a;     --badge-warn-fg: #ffd76e;
    --badge-err-bg: #3a0a0a;      --badge-err-fg: #ff9d9d;
    --badge-neutral-bg: #35353a;  --badge-neutral-fg: #b0b0b5;

    --btn-bg: #2e2e33;            --btn-fg: #e8e8ec;        --btn-border: #4a4a50;
    --btn-hover-bg: #35353a;
    --btn-danger-bg: #3a1a1d;     --btn-danger-fg: #ff9d9d; --btn-danger-border: #843029;
    --btn-danger-hover-bg: #4a2020;

    --input-bg: #2e2e33;          --input-fg: #e8e8ec;      --input-border: #4a4a50;
}

body {
    margin: 0;
    padding: 24px 16px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: var(--bg);
    color: var(--fg);
    line-height: 1.5;
    font-size: 14px;
}
.rs-wrap { max-width: 1200px; margin: 0 auto; }
.rs-title { margin-bottom: 16px; }
.rs-title h1 { font-size: 22px; margin: 0 0 4px; font-weight: 600; }
.rs-sub {
    color: var(--sub); font-size: 13px;
    display: flex; align-items: center;
    gap: 6px; flex-wrap: wrap;
}
.rs-sub a { color: var(--link); text-decoration: none; }
.rs-sub a:hover { text-decoration: underline; }
.rs-autorefresh { user-select: none; cursor: pointer; }

.rs-theme-select {
    padding: 3px 6px;
    border: 1px solid var(--input-border);
    background: var(--input-bg);
    color: var(--input-fg);
    border-radius: 6px;
    font-size: 12px;
    font-family: inherit;
    cursor: pointer;
}

.rs-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 14px;
    box-shadow: var(--card-shadow);
}
.rs-card h2 {
    margin: 0 0 12px;
    font-size: 15px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.rs-card h2 form { margin: 0; }
.rs-card.rs-err { border-color: var(--card-err-border); background: var(--card-err-bg); }

.rs-toast {
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 14px;
    font-weight: 500;
    word-break: break-word;
}
.rs-toast.ok  { background: var(--toast-ok-bg);  color: var(--toast-ok-fg);  border: 1px solid var(--toast-ok-border); }
.rs-toast.err { background: var(--toast-err-bg); color: var(--toast-err-fg); border: 1px solid var(--toast-err-border); }

.rs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 8px 24px;
}
.rs-grid > div {
    display: flex; align-items: center;
    gap: 8px; flex-wrap: wrap;
    min-width: 0;
}
.rs-lbl { color: var(--sub); min-width: 120px; font-size: 13px; }
.rs-dim { color: var(--dim); font-size: 12px; }
.rs-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
.rs-name { font-weight: 600; }
.rs-err-inline { color: var(--err-inline); font-size: 12px; margin-top: 4px; }
.rs-error-cell { max-width: 480px; word-break: break-word; font-size: 12px; color: var(--error-cell); }

/* ── Table wrapper — fixes horizontal overflow on narrow screens ── */
.rs-table-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    /* Небольшой негативный margin, чтобы скролл-бар не «съедал» padding карточки */
    margin: 0 -4px;
    padding: 0 4px;
}
.rs-table {
    width: 100%;
    min-width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.rs-table th, .rs-table td {
    text-align: left;
    padding: 8px 10px;
    border-bottom: 1px solid var(--table-border);
    vertical-align: top;
}
.rs-table th {
    font-weight: 600;
    color: var(--table-th-fg);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .03em;
    background: var(--table-th-bg);
    white-space: nowrap;       /* заголовки не переносятся по буквам */
}
.rs-table tr:last-child td { border-bottom: none; }
.rs-table tr:hover td { background: var(--table-hover); }

.rs-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
    white-space: nowrap;
}
.rs-badge.ok      { background: var(--badge-ok-bg);      color: var(--badge-ok-fg); }
.rs-badge.warn    { background: var(--badge-warn-bg);    color: var(--badge-warn-fg); }
.rs-badge.err     { background: var(--badge-err-bg);     color: var(--badge-err-fg); }
.rs-badge.neutral { background: var(--badge-neutral-bg); color: var(--badge-neutral-fg); }

.rs-btn {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid var(--btn-border);
    background: var(--btn-bg);
    color: var(--btn-fg);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    line-height: 1.3;
    text-transform: none;
    letter-spacing: 0;
    white-space: nowrap;
}
.rs-btn:hover { background: var(--btn-hover-bg); }
.rs-btn-danger { color: var(--btn-danger-fg); border-color: var(--btn-danger-border); background: var(--btn-danger-bg); }
.rs-btn-danger:hover { background: var(--btn-danger-hover-bg); }
.rs-btn-small { padding: 3px 8px; font-size: 12px; }
.rs-btn-ghost { border-color: transparent; background: transparent; color: var(--link); }
.rs-btn-ghost:hover { text-decoration: underline; background: transparent; }

.rs-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}
.rs-filter { display: flex; gap: 6px; margin-left: auto; }
.rs-filter input[type=text] {
    padding: 5px 10px;
    border: 1px solid var(--input-border);
    background: var(--input-bg);
    color: var(--input-fg);
    border-radius: 6px;
    font-size: 13px;
    min-width: 160px;
}

.rs-statuses { display: inline-flex; gap: 4px; flex-wrap: wrap; }

/* ── Mobile / narrow viewports ──────────────────────────── */
@media (max-width: 700px) {
    body {
        padding: 12px 8px;
        font-size: 13px;
    }
    .rs-wrap { max-width: 100%; }

    .rs-title h1 { font-size: 18px; }
    .rs-sub { font-size: 12px; gap: 4px 6px; }
    .rs-sub > span:not(.rs-autorefresh) { display: inline; }

    .rs-card {
        padding: 12px 12px;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    .rs-card h2 {
        font-size: 13px;
        margin-bottom: 10px;
        gap: 8px;
    }

    .rs-grid {
        grid-template-columns: 1fr;
        gap: 6px 12px;
    }
    .rs-grid > div { gap: 4px 8px; }
    .rs-lbl { min-width: 90px; font-size: 12px; }

    .rs-table th, .rs-table td {
        padding: 6px 8px;
        font-size: 12px;
    }
    .rs-table th {
        font-size: 11px;
        letter-spacing: .02em;
    }

    .rs-btn { padding: 7px 12px; }
    .rs-btn-small { padding: 5px 9px; font-size: 12px; }

    .rs-actions { gap: 8px; }
    .rs-filter {
        margin-left: 0;
        width: 100%;
    }
    .rs-filter input[type=text] {
        flex: 1 1 auto;
        min-width: 0;
        width: auto;
    }

    .rs-error-cell { max-width: none; }
}
</style>
</head>
<body>
<div class="rs-wrap">

    <div class="rs-title">
        <h1>Redis statistics</h1>
        <div class="rs-sub">
            <span>fetched at <?= date('Y-m-d H:i:s') ?></span>
            <span>·</span>
            <a href="">↻ refresh</a>
            <span>·</span>
            <a href="./">← admin</a>
            <span>·</span>
            <label class="rs-autorefresh">
                <input type="checkbox" id="autoRefresh"> auto-refresh 10s
            </label>
            <span>·</span>
            <select id="themeSelect" class="rs-theme-select" title="Theme">
                <option value="auto">🌗 Auto</option>
                <option value="light">☀ Light</option>
                <option value="dark">🌙 Dark</option>
            </select>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php $ok = ($_GET['ok'] ?? '0') === '1'; ?>
        <div class="rs-toast <?= $ok ? 'ok' : 'err' ?>">
            <?= $ok ? '✔' : '✖' ?> <?= rs_h((string)$_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <?php if (!$stats['connected']): ?>
        <div class="rs-card rs-err">
            <b>Connection failed.</b>
            <div><?= rs_h($stats['error']) ?></div>
        </div>
    <?php else: ?>

    <?php /* 1. Connection */ ?>
    <div class="rs-card">
        <h2>Connection</h2>
        <div class="rs-grid">
            <div><span class="rs-lbl">Status</span><span class="rs-badge ok">✔ connected</span></div>
            <div><span class="rs-lbl">Version</span><span><?= rs_h($stats['info']['version'] ?? '?') ?></span></div>
            <div><span class="rs-lbl">Role</span><span><?= rs_h($stats['info']['role'] ?? '?') ?></span></div>
            <div><span class="rs-lbl">Uptime</span><span><?= rs_h(rs_human_duration((int)($stats['info']['uptime'] ?? 0))) ?></span></div>
            <div><span class="rs-lbl">Used memory</span>
                <span><?= rs_h(rs_human_bytes((int)($stats['info']['used_memory'] ?? 0))) ?></span>
                <span class="rs-dim">(peak <?= rs_h(rs_human_bytes((int)($stats['info']['used_memory_peak'] ?? 0))) ?>)</span></div>
            <div><span class="rs-lbl">Maxmemory</span>
                <span><?= (int)($stats['info']['maxmemory'] ?? 0) > 0
                        ? rs_h(rs_human_bytes((int)$stats['info']['maxmemory']))
                        : '<span class="rs-dim">unlimited</span>' ?></span></div>
            <div><span class="rs-lbl">Clients</span><span><?= (int)($stats['info']['clients'] ?? 0) ?></span></div>
            <div><span class="rs-lbl">Total commands</span><span><?= number_format((int)($stats['info']['total_commands'] ?? 0)) ?></span></div>
        </div>
    </div>

    <?php /* 2. Streams */ ?>
    <div class="rs-card">
        <h2>Streams</h2>
        <div class="rs-table-wrap">
            <table class="rs-table">
                <thead>
                    <tr>
                        <th>Stream</th><th>Length</th><th>Lag</th><th>Pending</th>
                        <th>Consumers</th><th>First entry</th><th>Last entry</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($stats['streams'] as $s): ?>
                    <tr>
                        <td>
                            <div class="rs-name"><?= rs_h($s['label']) ?></div>
                            <div class="rs-mono rs-dim"><?= rs_h($s['key']) ?></div>
                            <?php if ($s['error']): ?>
                                <div class="rs-err-inline"><?= rs_h($s['error']) ?></div>
                            <?php endif; ?>
                        </td>
                        <?php if (!$s['exists']): ?>
                            <?php $noExistMsg = $s['key'] === $dlqKey
                                ? 'no dead letters yet (good)'
                                : 'stream does not exist'; ?>
                            <td colspan="6" class="rs-dim"><?= rs_h($noExistMsg) ?></td>
                        <?php else: ?>
                            <td><?= (int)$s['length'] ?></td>
                            <td>
                                <?php if ($s['group'] === null): ?><span class="rs-dim">—</span>
                                <?php elseif ($s['lag'] === null): ?><span class="rs-dim">?</span>
                                <?php else: ?><?= rs_badge($s['lag'], 1, 100) ?><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s['pending'] === null): ?><span class="rs-dim">—</span>
                                <?php else: ?><?= rs_badge($s['pending'], 1, 50) ?><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s['consumers'] === null): ?><span class="rs-dim">—</span>
                                <?php else: ?><?= (int)$s['consumers'] ?><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s['first_id']): ?>
                                    <div class="rs-mono rs-dim"><?= rs_h($s['first_id']) ?></div>
                                    <div class="rs-dim"><?= rs_h(rs_age((int)$s['first_ts'])) ?></div>
                                <?php else: ?><span class="rs-dim">—</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s['last_id']): ?>
                                    <div class="rs-mono rs-dim"><?= rs_h($s['last_id']) ?></div>
                                    <div class="rs-dim"><?= rs_h(rs_age((int)$s['last_ts'])) ?></div>
                                <?php else: ?><span class="rs-dim">—</span><?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php /* 3. Dead letters */ ?>
    <div class="rs-card">
        <h2>
            <span>Dead letters <span class="rs-dim">(<?= rs_h($dlqKey) ?>)</span></span>
        </h2>

        <?php if (!$stats['dlq_exists']): ?>
            <div class="rs-dim">DLQ is empty — no failures recorded yet.</div>
        <?php else: ?>
            <div class="rs-actions">
                <form method="post" style="display:inline"
                      onsubmit="return confirm('Replay all DLQ messages back to their source streams?');">
                    <input type="hidden" name="action" value="replay_dlq">
                    <button type="submit" class="rs-btn">↻ Replay all</button>
                </form>
                <form method="post" style="display:inline"
                      onsubmit="return confirm('Permanently DELETE all DLQ messages? This cannot be undone.');">
                    <input type="hidden" name="action" value="purge_dlq">
                    <button type="submit" class="rs-btn rs-btn-danger">✖ Purge DLQ</button>
                </form>
                <form method="get" class="rs-filter">
                    <input type="text" name="user" list="dlq-users"
                           placeholder="filter by user" value="<?= rs_h($dlqFilter) ?>">
                    <datalist id="dlq-users">
                        <?php foreach ($dlqUsers as $u): ?>
                            <option value="<?= rs_h($u) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <button type="submit" class="rs-btn rs-btn-small">Filter</button>
                    <?php if ($dlqFilter !== ''): ?>
                        <a href="redis_stats.php" class="rs-btn rs-btn-small rs-btn-ghost">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($dlqEntries)): ?>
                <div class="rs-dim" style="margin-top:10px">No messages match filter.</div>
            <?php else: ?>
                <div class="rs-table-wrap" style="margin-top:12px">
                    <table class="rs-table">
                        <thead>
                            <tr>
                                <th>ID</th><th>User</th><th>Kind</th>
                                <th>Failed</th><th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dlqEntries as $e): ?>
                            <tr>
                                <td class="rs-mono rs-dim"><?= rs_h($e['id']) ?></td>
                                <td><?= rs_h($e['user']) ?></td>
                                <td><?= rs_h($e['kind']) ?></td>
                                <td>
                                    <div><?= rs_h(rs_age($e['failed_at'])) ?></div>
                                    <div class="rs-dim rs-mono">
                                        src: <?= rs_h($e['src_stream']) ?> / <?= rs_h($e['src_id']) ?>
                                    </div>
                                </td>
                                <td class="rs-error-cell"><?= rs_h($e['error']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($dlqEntries) >= RS_DLQ_ROW_LIMIT): ?>
                    <div class="rs-dim" style="margin-top:6px">
                        Showing first <?= RS_DLQ_ROW_LIMIT ?> entries<?= $dlqFilter !== '' ? ' (filtered)' : '' ?>.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php /* 4. Consumers */ ?>
    <div class="rs-card">
        <h2>
            <span>Consumers <span class="rs-dim">(XINFO CONSUMERS)</span></span>
            <?php if ($stats['consumers_dead'] > 0): ?>
                <form method="post"
                      onsubmit="return confirm('Delete <?= (int)$stats['consumers_dead'] ?> dead consumer(s)? Only consumers with pending=0 and idle > <?= RS_DEAD_IDLE_SECONDS ?>s will be removed.');">
                    <input type="hidden" name="action" value="kill_dead_consumers">
                    <button type="submit" class="rs-btn rs-btn-small rs-btn-danger">
                        ✖ Kill dead consumers (<?= (int)$stats['consumers_dead'] ?>)
                    </button>
                </form>
            <?php endif; ?>
        </h2>

        <?php if (empty($stats['consumers'])): ?>
            <div class="rs-dim">no consumers registered yet</div>
        <?php else: ?>
            <div class="rs-table-wrap">
                <table class="rs-table">
                    <thead>
                        <tr>
                            <th>Stream</th><th>Consumer</th><th>Pending</th>
                            <th>Idle</th><th>State</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stats['consumers'] as $c): ?>
                        <?php
                            $idleSec = intdiv($c['idle_ms'], 1000);
                            $idleCls = $idleSec < 30 ? 'ok' : ($idleSec < 300 ? 'warn' : 'neutral');
                        ?>
                        <tr>
                            <td class="rs-mono"><?= rs_h($c['stream']) ?></td>
                            <td class="rs-mono"><?= rs_h($c['name']) ?></td>
                            <td><?= rs_badge($c['pending'], 1, 10) ?></td>
                            <td><span class="rs-badge <?= $idleCls ?>"><?= rs_h(rs_human_duration($idleSec)) ?></span></td>
                            <td>
                                <?php if ($c['dead']): ?>
                                    <span class="rs-badge err">dead</span>
                                <?php else: ?>
                                    <span class="rs-badge ok">active</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="rs-dim" style="margin-top:6px">
                A consumer is marked <b>dead</b> when pending = 0 and idle &gt; <?= RS_DEAD_IDLE_SECONDS ?>s —
                these are leftovers from previous worker restarts.
                <b>Kill dead consumers</b> removes all of them in one click; active consumers and any
                consumer with pending messages are left untouched.
            </div>
        <?php endif; ?>
    </div>

    <?php /* 5. Workers */ ?>
    <div class="rs-card">
        <h2>Workers <span class="rs-dim">(worker:hb:*)</span></h2>
        <?php if (empty($stats['workers'])): ?>
            <div><span class="rs-badge err">✖ 0 alive</span>
                <span class="rs-dim"> — no worker registered</span></div>
        <?php else: ?>
            <div style="margin-bottom:8px">
                <span class="rs-badge ok">✔ <?= count($stats['workers']) ?> alive</span>
            </div>
            <div class="rs-table-wrap">
                <table class="rs-table">
                    <thead>
                        <tr><th>Consumer</th><th>TTL</th><th>Last beat</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stats['workers'] as $w): ?>
                        <tr>
                            <td class="rs-mono"><?= rs_h($w['consumer']) ?></td>
                            <td>
                                <?php if ($w['ttl'] < 0): ?>
                                    <span class="rs-badge neutral">no TTL</span>
                                <?php else: ?>
                                    <?= rs_badge($w['ttl'], 0, 99999, ' s') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $w['hb_at'] ? rs_h(rs_age($w['hb_at'])) : '<span class="rs-dim">?</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php /* 6. Heavy tasks */ ?>
    <div class="rs-card">
        <h2>Heavy tasks</h2>
        <div class="rs-grid">
            <div><span class="rs-lbl">Total hashes</span>
                <span><?= (int)$stats['tasks']['hashes'] ?></span>
                <span class="rs-dim">(ratel:task:*)</span></div>
            <div><span class="rs-lbl">User indexes</span>
                <span><?= (int)$stats['tasks']['user_indexes'] ?></span>
                <span class="rs-dim">(ratel:user_tasks:*)</span></div>
            <div><span class="rs-lbl">Attempt counters</span>
                <span><?= (int)$stats['attempts'] ?></span>
                <span class="rs-dim">(ratel:attempts:*)</span></div>
            <?php if (!empty($stats['tasks']['orphans'])): ?>
                <div><span class="rs-lbl">Orphan tasks</span>
                    <span class="rs-badge warn"><?= (int)$stats['tasks']['orphans'] ?></span>
                    <span class="rs-dim">owner not in DB</span></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($stats['tasks']['by_status'])): ?>
            <div class="rs-table-wrap" style="margin-top:12px">
                <table class="rs-table">
                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['tasks']['by_status'] as $st => $cnt): ?>
                        <?php $cls = match ($st) {
                            'done'    => 'ok',
                            'running' => 'warn',
                            'failed'  => 'err',
                            default   => 'neutral',
                        }; ?>
                        <tr>
                            <td><span class="rs-badge <?= $cls ?>"><?= rs_h($st) ?></span></td>
                            <td><?= (int)$cnt ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php /* 7. Per-user statistics */ ?>
    <div class="rs-card">
        <h2>
            <span>Per-user statistics</span>
            <span class="rs-dim">
                <?= count($perUserWithActivity) ?> active / <?= (int)$perUserTotal ?> total
            </span>
        </h2>

        <?php if (empty($perUserShown)): ?>
            <div class="rs-dim">No per-user activity in Redis right now.</div>
        <?php else: ?>
            <div class="rs-table-wrap">
                <table class="rs-table">
                    <thead>
                        <tr>
                            <th>User</th><th>UID</th>
                            <th title="rate_limit_<user> — upload counter, TTL ≤ 1s">rate_limit</th>
                            <th title="new_session_<user> — flag, TTL 300s">new_session</th>
                            <th title="worker_user_<user> — cached user row in worker">worker_cache</th>
                            <th title="DLQ messages for this user">DLQ</th>
                            <th title="Heavy task hashes owned by this user (ratel:task:*)">Heavy</th>
                            <th title="Active task IDs in ratel:user_tasks:<uid>">Active tasks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($perUserShown as $u): ?>
                        <tr>
                            <td>
                                <span class="rs-name"><?= rs_h($u['username']) ?></span>
                                <?php if ($u['uid'] === null): ?>
                                    <span class="rs-badge warn">no DB row</span>
                                <?php endif; ?>
                            </td>
                            <td class="rs-mono rs-dim"><?= $u['uid'] !== null ? (int)$u['uid'] : '—' ?></td>
                            <td>
                                <?php if ($u['rate_limit'] === null): ?><span class="rs-dim">—</span>
                                <?php else: ?>
                                    <span class="rs-badge <?= $u['rate_limit']['value'] > 100 ? 'err' : ($u['rate_limit']['value'] > 50 ? 'warn' : 'neutral') ?>">
                                        <?= (int)$u['rate_limit']['value'] ?>
                                    </span>
                                    <span class="rs-dim"> (ttl <?= (int)$u['rate_limit']['ttl'] ?>s)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['new_session_ttl'] === null): ?><span class="rs-dim">—</span>
                                <?php else: ?><span class="rs-badge warn"><?= (int)$u['new_session_ttl'] ?>s left</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['worker_cache_ttl'] === null): ?><span class="rs-dim">—</span>
                                <?php else: ?><span class="rs-badge neutral"><?= (int)$u['worker_cache_ttl'] ?>s left</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['dlq_count'] === 0): ?><span class="rs-dim">—</span>
                                <?php else: ?>
                                    <span class="rs-badge err"><?= (int)$u['dlq_count'] ?></span>
                                    <?php if ($u['dlq_last_ts']): ?>
                                        <div class="rs-dim">last <?= rs_h(rs_age((int)$u['dlq_last_ts'])) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['heavy_total'] === 0): ?><span class="rs-dim">—</span>
                                <?php else: ?>
                                    <span class="rs-badge neutral"><?= (int)$u['heavy_total'] ?></span>
                                    <div class="rs-statuses">
                                        <?php foreach ($u['heavy_by_status'] as $st => $cnt): ?>
                                            <?php $cls = match ($st) {
                                                'done'    => 'ok',
                                                'running' => 'warn',
                                                'failed'  => 'err',
                                                default   => 'neutral',
                                            }; ?>
                                            <span class="rs-badge <?= $cls ?>" style="font-size:10px">
                                                <?= rs_h($st) ?>:<?= (int)$cnt ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['user_tasks_active'] === 0): ?><span class="rs-dim">—</span>
                                <?php else: ?><span class="rs-badge warn"><?= (int)$u['user_tasks_active'] ?></span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($perUserCapped): ?>
                <div class="rs-dim" style="margin-top:6px">
                    Showing top <?= RS_PER_USER_ROW_LIMIT ?> of <?= count($perUserWithActivity) ?> active users.
                </div>
            <?php endif; ?>

            <div class="rs-dim" style="margin-top:8px">
                <b>Fresh columns</b> reflect ephemeral Redis keys (with TTL).
                <b>Historical columns</b> aggregate over DLQ entries and heavy-task hashes.
                Users with no activity anywhere are not shown.
            </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

</div>

<script>
(function () {
    const ms  = 10000;
    const cb  = document.getElementById('autoRefresh');
    if (!cb) return;
    const KEY = 'rs_auto_refresh';
    let timer = null;

    function stop()  { if (timer) { clearTimeout(timer); timer = null; } }
    function start() {
        stop();
        if (!cb.checked) return;
        timer = setTimeout(() => location.reload(), ms);
    }

    cb.checked = localStorage.getItem(KEY) === '1';
    cb.addEventListener('change', () => {
        localStorage.setItem(KEY, cb.checked ? '1' : '0');
        start();
    });
    window.addEventListener('beforeunload', stop);
    start();
})();

(function () {
    const sel = document.getElementById('themeSelect');
    if (!sel) return;

    const KEY = 'rs_theme';

    let stored = 'auto';
    try { stored = localStorage.getItem(KEY) || 'auto'; } catch (e) {}
    sel.value = stored;

    function apply(choice) {
        let resolved = choice;
        if (choice !== 'light' && choice !== 'dark') {
            resolved = (window.matchMedia
                && window.matchMedia('(prefers-color-scheme: dark)').matches)
                ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', resolved);
    }

    sel.addEventListener('change', () => {
        const choice = sel.value;
        try { localStorage.setItem(KEY, choice); } catch (e) {}
        apply(choice);
    });

    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', () => {
                if (sel.value === 'auto') apply('auto');
            });
    }
})();
</script>

</body>
</html>
#!/usr/bin/env php
<?php
/**
 * RedBox Telemetry — self-contained migration.
 *
 *   php migrate.php --dry-run            # показать план, ничего не менять
 *   php migrate.php                      # выполнить (идемпотентно)
 *   php migrate.php --reset              # дропнуть shared-таблицы и перелить заново
 *   php migrate.php --reset --dry-run    # показать план reset'а
 *
 * Делает две вещи:
 *   1. Обновляет src/creds.php   (per-user → shared tables)
 *   2. Переносит данные:
 *        {user}_sessions → sessions  (+ user_id)
 *        {user}_pids     → pids      (+ user_id)
 *        {user}_logs     → logs      (колонки → JSON в data, включая нули)
 *
 * Идемпотентен. Бэкап: src/creds.php.bak.YYYYMMDD_HHMMSS
 */

$_SESSION = [
    'torque_logged_in' => true,
    'admin'            => true,
];
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['REQUEST_METHOD']  = 'CLI';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';

// creds.php читает $username. В CLI его нет — ставим пустышку.
$username = '';
$limit    = 0;

chdir(__DIR__);

$DRY_RUN = in_array('--dry-run', $argv, true);
$RESET   = in_array('--reset',   $argv, true);

/* ══════════════════════════════════════════════════════════
 * Утилиты
 * ══════════════════════════════════════════════════════════ */

function out(string $s = ''): void { fwrite(STDOUT, $s . "\n"); }
function err(string $s): void      { fwrite(STDERR, $s . "\n"); }
function phase(string $t): void    { out(); out("--- {$t} ---"); }

/* ══════════════════════════════════════════════════════════
 * Phase 1: миграция src/creds.php
 * ══════════════════════════════════════════════════════════ */

/**
 * @return array{status:string, backup:?string, matched:array<string>}
 *   status: 'updated' | 'already' | 'no_match' | 'error'
 */
function migrate_creds_php(string $path, bool $dry_run): array
{
    $result = [
        'status'  => 'error',
        'backup'  => null,
        'matched' => [],
    ];

    if (!file_exists($path)) {
        err("[creds] not found: {$path}");
        return $result;
    }

    $src = file_get_contents($path);
    if ($src === false) {
        err("[creds] cannot read: {$path}");
        return $result;
    }

    // Уже мигрирован?
    if (strpos($src, '$db_log_table') !== false) {
        $result['status'] = 'already';
        return $result;
    }

    $orig = $src;

    /* T1: global $username, $limit; → global $username, $limit, $user_id; */
    $src = preg_replace_callback(
        '/^([ \t]*global[ \t]+\$username[ \t]*,[ \t]*\$limit[ \t]*);[ \t]*$/m',
        function ($m) use (&$result) {
            $result['matched'][] = 'global ... $user_id';
            return $m[1] . ', $user_id;';
        },
        $src,
        1
    );

    /* T2: $db_table = $username.$db_log_prefix; → новый блок shared tables */
    $new_block = <<<'PHP'
// === [migrate.php] shared tables ===
$db_log_table       = 'logs';
$db_sessions_table  = 'sessions';
$db_pids_table      = 'pids';
$db_table           = $db_log_table;
$db_log_prefix      = '';
$db_sessions_prefix = '';
$db_pids_prefix     = '';
$user_id            = $_SESSION['uid'] ?? null;
// === [/migrate.php] ===
PHP;

    $src = preg_replace_callback(
        '/^[ \t]*\$db_table[ \t]*=[ \t]*\$username[ \t]*\.[ \t]*\$db_log_prefix[ \t]*;[ \t]*$/m',
        function ($m) use (&$result, $new_block) {
            $result['matched'][] = '$db_table = $username.$db_log_prefix (replaced)';
            return $new_block;
        },
        $src,
        1
    );

    /* T3: закомментировать $db_sessions_table = ... */
    $src = preg_replace_callback(
        '/^([ \t]*)(\$db_sessions_table[ \t]*=[ \t]*\$username[ \t]*\.[ \t]*\$db_sessions_prefix[ \t]*;)/m',
        function ($m) use (&$result) {
            $result['matched'][] = '$db_sessions_table (commented out)';
            return $m[1] . '// [migrate.php] removed: ' . $m[2];
        },
        $src,
        1
    );

    /* T4: закомментировать $db_pids_table = ... */
    $src = preg_replace_callback(
        '/^([ \t]*)(\$db_pids_table[ \t]*=[ \t]*\$username[ \t]*\.[ \t]*\$db_pids_prefix[ \t]*;)/m',
        function ($m) use (&$result) {
            $result['matched'][] = '$db_pids_table (commented out)';
            return $m[1] . '// [migrate.php] removed: ' . $m[2];
        },
        $src,
        1
    );

    // Критическое условие: блок $db_table обязан был замениться
    $critical_ok = false;
    foreach ($result['matched'] as $m) {
        if (strpos($m, '$db_table = $username') !== false) {
            $critical_ok = true;
            break;
        }
    }

    if (!$critical_ok || $src === $orig) {
        $result['status'] = 'no_match';
        return $result;
    }

    if ($dry_run) {
        $result['status'] = 'updated';
        return $result;
    }

    // Backup
    $backup = $path . '.bak.' . date('Ymd_His');
    if (!@copy($path, $backup)) {
        err("[creds] backup failed: {$backup}");
        $result['status'] = 'error';
        return $result;
    }
    $result['backup'] = $backup;

    if (@file_put_contents($path, $src) === false) {
        err("[creds] write failed: {$path}");
        $result['status'] = 'error';
        return $result;
    }

    $result['status'] = 'updated';
    return $result;
}

/* ══════════════════════════════════════════════════════════
 * Phase 2: DDL
 * ══════════════════════════════════════════════════════════ */

function create_tables(mysqli $db, bool $dry_run): void
{
    $ddl = [
        'pids' => "
CREATE TABLE IF NOT EXISTS pids (
    user_id     BIGINT UNSIGNED  NOT NULL,
    id          VARCHAR(16)      NOT NULL,
    description VARCHAR(255)     DEFAULT NULL,
    units       VARCHAR(64)      DEFAULT NULL,
    populated   TINYINT(1)       NOT NULL DEFAULT 0,
    stream      TINYINT(1)       NOT NULL DEFAULT 0,
    favorite    TINYINT(1)       NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, id)
) ENGINE=ROCKSDB DEFAULT CHARSET=utf8mb4",

        'sessions' => "
CREATE TABLE IF NOT EXISTS sessions (
    user_id     BIGINT UNSIGNED      NOT NULL,
    id          VARCHAR(32)          NOT NULL DEFAULT '-',
    profileName VARCHAR(128)         NOT NULL DEFAULT 'Not Specified',
    description VARCHAR(128)         NOT NULL DEFAULT '-',
    ip          CHAR(15)             NOT NULL DEFAULT '0.0.0.0',
    sessionsize MEDIUMINT UNSIGNED   NOT NULL DEFAULT 0,
    session     BIGINT UNSIGNED      NOT NULL,
    time        BIGINT UNSIGNED      NOT NULL,
    timeend     BIGINT UNSIGNED      NOT NULL,
    favorite    TINYINT(1) UNSIGNED  NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, session),
    KEY timeend_index  (user_id, timeend),
    KEY favorite_index (user_id, favorite)
) ENGINE=ROCKSDB DEFAULT CHARSET=utf8mb4",

        'logs' => "
CREATE TABLE IF NOT EXISTS logs (
    user_id BIGINT UNSIGNED NOT NULL,
    session BIGINT UNSIGNED NOT NULL,
    time    BIGINT UNSIGNED NOT NULL,
    data    LONGTEXT        NOT NULL,
    PRIMARY KEY (user_id, time),
    KEY session_time (user_id, session, time)
) ENGINE=ROCKSDB DEFAULT CHARSET=utf8mb4",
    ];

    if ($dry_run) {
        out("[ddl] would CREATE TABLE IF NOT EXISTS: " . implode(', ', array_keys($ddl)));
        return;
    }

    foreach ($ddl as $name => $sql) {
        if ($db->query($sql) === false) {
            err("CREATE {$name} failed: {$db->error}");
            exit(1);
        }
    }
    out("[ddl] shared tables ready: pids, sessions, logs");
}

/* ══════════════════════════════════════════════════════════
 * Phase 3: миграция данных
 * ══════════════════════════════════════════════════════════ */

function table_exists(mysqli $db, string $t): bool
{
    $q = $db->prepare(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?"
    );
    $q->bind_param('s', $t);
    $q->execute();
    return (bool)$q->get_result()->num_rows;
}

function columns_of(mysqli $db, string $t): array
{
    $r = $db->query("SHOW COLUMNS FROM `$t`");
    return $r ? array_column($r->fetch_all(MYSQLI_ASSOC), 'Field') : [];
}

function migrate_simple(
    mysqli $db,
    string $src_table,
    string $dst_table,
    array  $allowed_cols,
    int    $uid,
    bool   $dry_run
): void {
    if (!table_exists($db, $src_table)) {
        out("  - {$src_table}: not found, skip");
        return;
    }

    $src_cols = columns_of($db, $src_table);
    $cols     = array_values(array_intersect($allowed_cols, $src_cols));

    if (count($cols) < 3) {
        out("  ! {$src_table}: too few columns (" . implode(',', $src_cols) . "), skip");
        return;
    }

    $col_list = '`' . implode('`,`', $cols) . '`';
    $count    = (int)$db->query("SELECT COUNT(*) FROM `$src_table`")->fetch_row()[0];

    if ($dry_run) {
        out("  [dry] {$src_table} → {$dst_table}: would copy {$count} rows");
        return;
    }

    $db->query(
        "INSERT IGNORE INTO {$dst_table} (user_id, {$col_list})
         SELECT {$uid}, {$col_list} FROM `{$src_table}`"
    );
    if ($db->error) {
        err("  ERROR: {$db->error}");
        return;
    }
    out("  + {$src_table} → {$dst_table}: source={$count}, copied={$db->affected_rows}");
}

function migrate_logs(mysqli $db, string $src_table, int $uid, bool $dry_run): void
{
    if (!table_exists($db, $src_table)) {
        out("  - {$src_table}: not found, skip");
        return;
    }

    $cols     = columns_of($db, $src_table);
    $pid_cols = array_values(array_diff($cols, ['session', 'time']));

    if (empty($pid_cols)) {
        out("  ! {$src_table}: no PID columns, skip");
        return;
    }

    $total = (int)$db->query("SELECT COUNT(*) FROM `{$src_table}`")->fetch_row()[0];

    if ($dry_run) {
        out("  [dry] {$src_table} → logs: would migrate {$total} rows, "
          . count($pid_cols) . " PID columns");
        return;
    }

    $batch_size  = 1000;
    $last_time   = 0;
    $migrated    = 0;
    $pids_select = '`' . implode('`,`', $pid_cols) . '`';

    while (true) {
        $rows = $db->execute_query(
            "SELECT session, time, {$pids_select}
             FROM `{$src_table}`
             WHERE time > ?
             ORDER BY time ASC
             LIMIT {$batch_size}",
            [$last_time]
        )->fetch_all(MYSQLI_ASSOC);

        if (empty($rows)) break;

        $placeholders = [];
        $values       = [];

        foreach ($rows as $row) {
            $last_time = (int)$row['time'];

            $pids = [];
            foreach ($pid_cols as $pid) {
                $v = $row[$pid] ?? null;
                if ($v === null) continue;
                if (is_string($v) && $v === 'Infinity') { $pids[$pid] = -1; continue; }
                if (!is_numeric($v)) continue;
                // Нули пишем — это семантически значимые значения
                // (Fan OFF, Rollback OK, Gear N, BS1/BS2 = GND и т.д.)
                $pids[$pid] = (float)$v;
            }

            $data_json = json_encode(
                $pids,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '{}';

            $placeholders[] = '(?,?,?,?)';
            $values[] = $uid;
            $values[] = (int)$row['session'];
            $values[] = (int)$row['time'];
            $values[] = $data_json;
        }

        $sql = "INSERT IGNORE INTO logs (user_id, session, time, data) VALUES "
             . implode(',', $placeholders);

        $db->execute_query($sql, $values);
        if ($db->error) {
            err("  ERROR batch: {$db->error}");
            exit(1);
        }

        $migrated += count($rows);

        if ($total > 5000 && ($migrated % 10000 === 0)) {
            out("    ... {$migrated}/{$total}");
        }
    }

    out("  + {$src_table} → logs: migrated {$migrated} rows");
}

/* ══════════════════════════════════════════════════════════
 * Main
 * ══════════════════════════════════════════════════════════ */

out("=== RedBox Telemetry migration ===");
out($DRY_RUN ? "MODE: DRY-RUN (no changes)" : "MODE: LIVE");
if ($RESET) out("FLAG: --reset (shared tables will be dropped)");

/* --- Phase 1 --- */
phase("Phase 1/3: src/creds.php");

$creds_path = __DIR__ . '/src/creds.php';

// Legacy: если creds.php лежит в корне — переносим в src/
if (!file_exists($creds_path) && file_exists(__DIR__ . '/creds.php')) {
    if (!$DRY_RUN) {
        rename(__DIR__ . '/creds.php', $creds_path);
        out("[creds] moved: creds.php → src/creds.php");
    } else {
        out("[creds] [dry] would move: creds.php → src/creds.php");
    }
}

$creds_result = migrate_creds_php($creds_path, $DRY_RUN);

switch ($creds_result['status']) {
    case 'already':
        out("[creds] already migrated, skipping");
        break;

    case 'updated':
        foreach ($creds_result['matched'] as $m) {
            out("[creds] pattern matched: {$m}");
        }
        if ($DRY_RUN) {
            out("[creds] [dry] would update {$creds_path}");
        } else {
            out("[creds] backup:  {$creds_result['backup']}");
            out("[creds] updated: {$creds_path}");
        }
        break;

    case 'no_match':
        err("[creds] no patterns matched in {$creds_path}");
        err("[creds] файл не похож на стандартный creds.php;");
        err("[creds] обновите вручную или начните с creds.php.example");
        if (!$DRY_RUN) {
            err("[creds] ABORT — БД не тронута");
            exit(2);
        }
        break;

    case 'error':
    default:
        if (!$DRY_RUN) {
            err("[creds] ABORT — БД не тронута");
            exit(2);
        }
}

/* --- Phase 2 --- */
require_once $creds_path;

global $db_host, $db_user, $db_pass, $db_name, $db_port, $db_users;

$db = new mysqli($db_host, $db_user, $db_pass, $db_name, (int)$db_port);
if ($db->connect_error) {
    err("DB connect failed: {$db->connect_error}");
    exit(1);
}
$db->set_charset('utf8mb4');

phase("Phase 2/3: DDL");

if ($RESET) {
    if ($DRY_RUN) {
        out("[reset] would DROP TABLE IF EXISTS: logs, sessions, pids");
    } else {
        out("[reset] dropping existing shared tables...");
        $db->query("DROP TABLE IF EXISTS logs");
        $db->query("DROP TABLE IF EXISTS sessions");
        $db->query("DROP TABLE IF EXISTS pids");
        out("[reset] dropped: logs, sessions, pids");
    }
}

create_tables($db, $DRY_RUN);

/* --- Phase 3 --- */
phase("Phase 3/3: data migration");

$users = $db->query("SELECT id, user FROM `$db_users` ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
out("[users] found: " . count($users));
out();

$allowed_sessions = [
    'id','profileName','description','ip','sessionsize',
    'session','time','timeend','favorite',
];
$allowed_pids = [
    'id','description','units','populated','stream','favorite',
];

foreach ($users as $u) {
    $uid   = (int)$u['id'];
    $login = $u['user'];

    out("[user] id={$uid} login={$login}");

    $before_sess = $before_pids = $before_logs = null;
    if (!$DRY_RUN) {
        $before_sess = (int)$db->query("SELECT COUNT(*) FROM sessions WHERE user_id={$uid}")->fetch_row()[0];
        $before_pids = (int)$db->query("SELECT COUNT(*) FROM pids     WHERE user_id={$uid}")->fetch_row()[0];
        $before_logs = (int)$db->query("SELECT COUNT(*) FROM logs     WHERE user_id={$uid}")->fetch_row()[0];
    }

    migrate_simple($db, $login . '_sessions', 'sessions', $allowed_sessions, $uid, $DRY_RUN);
    migrate_simple($db, $login . '_pids',     'pids',     $allowed_pids,     $uid, $DRY_RUN);
    migrate_logs  ($db, $login . '_logs',                  $uid,               $DRY_RUN);

    if (!$DRY_RUN) {
        $after_sess = (int)$db->query("SELECT COUNT(*) FROM sessions WHERE user_id={$uid}")->fetch_row()[0];
        $after_pids = (int)$db->query("SELECT COUNT(*) FROM pids     WHERE user_id={$uid}")->fetch_row()[0];
        $after_logs = (int)$db->query("SELECT COUNT(*) FROM logs     WHERE user_id={$uid}")->fetch_row()[0];

        out(sprintf(
            "  = sessions: %d → %d | pids: %d → %d | logs: %d → %d",
            $before_sess, $after_sess,
            $before_pids, $after_pids,
            $before_logs, $after_logs
        ));
    }

    out();
}

/* --- Итог --- */
if (!$DRY_RUN) {
    out("=== Totals in shared tables ===");
    $r = $db->query("SELECT user_id, COUNT(*) c FROM sessions GROUP BY user_id ORDER BY user_id");
    while ($row = $r->fetch_assoc()) out("  sessions.user_id={$row['user_id']}: {$row['c']}");
    $r = $db->query("SELECT user_id, COUNT(*) c FROM pids GROUP BY user_id ORDER BY user_id");
    while ($row = $r->fetch_assoc()) out("  pids.user_id={$row['user_id']}: {$row['c']}");
    $r = $db->query("SELECT user_id, COUNT(*) c FROM logs GROUP BY user_id ORDER BY user_id");
    while ($row = $r->fetch_assoc()) out("  logs.user_id={$row['user_id']}: {$row['c']}");

    out();
    out("DONE. Disable maintenance mode.");
    out();
    out("Old tables can be renamed (safe — reversible):");
    foreach ($users as $u) {
        $login = $u['user'];
        out("  RENAME TABLE {$login}_logs     TO _bak_{$login}_logs;");
        out("  RENAME TABLE {$login}_sessions TO _bak_{$login}_sessions;");
        out("  RENAME TABLE {$login}_pids     TO _bak_{$login}_pids;");
    }
    out();
    out("After checking, old tables can be deleted:");
    foreach ($users as $u) {
        $login = $u['user'];
        out("  DROP TABLE IF EXISTS _bak_{$login}_logs;");
        out("  DROP TABLE IF EXISTS _bak_{$login}_sessions;");
        out("  DROP TABLE IF EXISTS _bak_{$login}_pids;");
    }
} else {
    out("[dry] no changes made.");
}

$db->close();

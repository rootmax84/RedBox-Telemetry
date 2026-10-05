<?php
/**
 * RedBox Telemetry — migrations runner.
 *
 * Запускается вручную или автоматически из startup-скрипта Docker
 * (/startup/99-migrate) ПЕРЕД запуском nginx/php-fpm.
 *
 * ────────────────────────────────────────────────────────────
 * Использование:
 *
 *   php migrate.php                        # интерактивный режим
 *   php migrate.php --dry-run              # показать план, ничего не менять
 *   php migrate.php --reset                # дропнуть shared-таблицы и перелить заново
 *   php migrate.php --auto                 # тихий режим (для startup)
 *   php migrate.php --auto --wait-db=60    # типичный вызов из Dockerfile
 *   php migrate.php --no-backup            # не создавать .bak-файлы creds.php
 *
 * ────────────────────────────────────────────────────────────
 * Что делает:
 *
 *   Phase 1. src/creds.php
 *     1.1 Переносит creds.php из корня в src/ (legacy-инсталляции)
 *     1.2 Создаёт src/creds.php из src/creds.php.example, если его нет
 *     1.3 Migrates per-user → shared tables (логи/sessions/pids)
 *     1.4 Добавляет недостающие параметры (heavy_tasks и др.)
 *
 *   Phase 2. Подключение к MariaDB с retry (--wait-db=N)
 *
 *   Phase 3. DDL
 *     3.1 CREATE TABLE IF NOT EXISTS: logs, sessions, pids
 *     3.2 ALTER TABLE users: недостающие колонки + drop legacy index
 *         (пропускается, если users ещё нет — clean install)
 *
 *   Phase 4. Data migration
 *     4.1 {user}_logs     → logs      (колонки PID → JSON в data)
 *     4.2 {user}_sessions → sessions  (+ user_id)
 *     4.3 {user}_pids     → pids      (+ user_id)
 *     Полностью пропускается, если:
 *       - таблицы users ещё нет (clean install), или
 *       - per-user таблиц нет ни у одного юзера.
 *
 * ────────────────────────────────────────────────────────────
 * Особенности:
 *
 *   - Идемпотентен. Можно запускать сколько угодно раз.
 *   - Изменения creds.php сопровождаются бэкапом:
 *       src/creds.php.bak.YYYYMMDD_HHMMSS        (shared-tables migration)
 *       src/creds.php.params.bak.YYYYMMDD_HHMMSS (params migration)
 *   - --auto возвращает ненулевой exit code при любой ошибке,
 *     чтобы startup-скрипт мог остановить контейнер.
 *   - --reset + --auto запрещены вместе (safety).
 *   - Все обращения к БД обёрнуты в try/catch для mysqli_sql_exception,
 *     т.к. в PHP 8.1+ mysqli по умолчанию работает в strict-режиме.
 */

if (PHP_SAPI !== 'cli') {
    header('Location: .');
    exit;
}

// ────────────────────────────────────────────────────────────
// CLI bootstrap: имитируем сессию и запрос, как это делают
// web-эндпоинты. creds.php читает $username из $_SESSION.
// ────────────────────────────────────────────────────────────
$_SESSION = [
    'torque_logged_in' => true,
    'admin'            => true,
];
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['REQUEST_METHOD']  = 'CLI';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';

// creds.php ожидает эти переменные в global scope
$username = '';
$limit    = 0;

chdir(__DIR__);

/* ══════════════════════════════════════════════════════════
 * Парсинг аргументов командной строки
 * ══════════════════════════════════════════════════════════ */

$DRY_RUN   = in_array('--dry-run',   $argv, true);
$RESET     = in_array('--reset',     $argv, true);
$AUTO      = in_array('--auto',      $argv, true);
$NO_BACKUP = in_array('--no-backup', $argv, true);

$WAIT_DB = 0;
foreach ($argv as $a) {
    if (preg_match('/^--wait-db=(\d+)$/', $a, $m)) {
        $WAIT_DB = (int)$m[1];
    }
}

// --reset + --auto — небезопасная комбинация: reset может удалить данные,
// а в startup-скрипте это недопустимо.
if ($RESET && $AUTO) {
    fwrite(STDERR, "[migrate] --reset cannot be combined with --auto (safety)\n");
    exit(2);
}

// Глобальный флаг "подробный вывод". В --auto-режиме глушим info/debug,
// оставляем только жизненно важные сообщения.
$GLOBALS['VERBOSE'] = !$AUTO;

/* ══════════════════════════════════════════════════════════
 * Утилиты вывода
 *
 *   out()   — debug/informative, только в verbose (без --auto)
 *   info()  — важное, выводится всегда
 *   err()   — ошибки, всегда в STDERR
 *   phase() — заголовок фазы, только в verbose
 * ══════════════════════════════════════════════════════════ */

function out(string $s = ''): void
{
    if (!empty($GLOBALS['VERBOSE'])) {
        fwrite(STDOUT, $s . "\n");
    }
}

function info(string $s): void
{
    fwrite(STDOUT, $s . "\n");
}

function err(string $s): void
{
    fwrite(STDERR, $s . "\n");
}

function phase(string $t): void
{
    if (!empty($GLOBALS['VERBOSE'])) {
        out();
        out("--- {$t} ---");
    }
}

/* ══════════════════════════════════════════════════════════
 * Phase 1.3: миграция creds.php (per-user → shared tables)
 *
 * Меняет схему хранения данных с "{login}_logs / {login}_sessions /
 * {login}_pids" на общие таблицы "logs / sessions / pids" с колонкой
 * user_id. Идемпотентно — если в creds.php уже есть $db_log_table,
 * сразу возвращает 'already'.
 *
 * @return array{status:string, backup:?string, matched:array<string>}
 *   status: 'updated' | 'already' | 'no_match' | 'error'
 * ══════════════════════════════════════════════════════════ */

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

    // Критическое условие: блок $db_table обязан был замениться.
    // Если нет — файл не похож на стандартный creds.php, лучше не трогать.
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
 * Phase 1.4: добавить недостающие параметры в creds.php
 *
 * Идемпотентно вставляет два блока:
 *   1. Redis / Streams     (если нет $redis_enabled)
 *   2. Heavy tasks         (если нет $redis_heavy_stream_key)
 *
 * Порядок важен: сначала Redis-блок, потом heavy_tasks —
 * heavy вставляется после $redis_stream_maxlen, а эта переменная
 * появляется вместе с Redis-блоком.
 *
 * Якоря вставки (по убыванию приоритета):
 *   Redis-блок:  после $max_api_requests_per_second; иначе перед $salt; иначе в конец.
 *   Heavy:       после $redis_stream_maxlen;            иначе перед $salt; иначе в конец.
 *
 * @return array{status:string, backup:?string, matched:array<string>}
 *   status: 'updated' | 'already' | 'error'
 * ══════════════════════════════════════════════════════════ */

function migrate_creds_params(string $path, bool $dry_run, bool $no_backup): array
{
    $result = ['status' => 'error', 'backup' => null, 'matched' => []];

    if (!file_exists($path)) {
        err("[creds-params] not found: {$path}");
        return $result;
    }

    $src = file_get_contents($path);
    if ($src === false) {
        err("[creds-params] cannot read: {$path}");
        return $result;
    }

    $orig  = $src;
    $added = [];

    /* ──────────────────────────────────────────────────────
     * 1. Redis / Streams
     * ────────────────────────────────────────────────────── */
    if (!preg_match('/^\$redis_enabled\b/m', $src)) {
        $redis_block =
              "\n// --- Redis / Streams (async upload processing) ---\n"
            . "\$redis_enabled        = true;\n"
            . "\$redis_host           = 'redis';                // docker service name / IP\n"
            . "\$redis_port           = 6379;\n"
            . "\$redis_timeout        = 2.0;\n"
            . "\$redis_password       = '';\n"
            . "\$redis_db             = 0;\n"
            . "\$redis_stream_enabled = true;                   // Use Streams в ul.php\n"
            . "\$redis_stream_key     = 'telemetry:uploads';\n"
            . "\$redis_stream_group   = 'telemetry-workers';\n"
            . "\$redis_stream_maxlen  = 50000;                  // ~ MAXLEN, 0 = no limit\n";

        if (preg_match('/^(\$max_api_requests_per_second\s*=\s*[^;]+;.*)$/m', $src, $m)) {
            $src = str_replace($m[0], $m[0] . "\n" . $redis_block, $src);
            $added[] = 'redis block after $max_api_requests_per_second';
        } elseif (preg_match('/^(\$salt\s*=)/m', $src, $m)) {
            $src = str_replace($m[0], $redis_block . "\n" . $m[0], $src);
            $added[] = 'redis block before $salt';
        } else {
            $src .= $redis_block;
            $added[] = 'redis block appended';
        }
    }

    /* ──────────────────────────────────────────────────────
     * 2. Heavy tasks
     *    Проверка по уникальной строке $redis_heavy_stream_key —
     *    она не содержится в Redis-блоке, так что strpos безопасен.
     * ────────────────────────────────────────────────────── */
    if (strpos($src, '$redis_heavy_stream_key') === false) {
        $heavy_block =
              "\n// --- Heavy tasks (async delete, обслуживается тем же worker.php) ---\n"
            . "\$heavy_tasks_enabled       = true;\n"
            . "\$redis_heavy_stream_key    = 'ratel:heavy_tasks';\n"
            . "\$redis_heavy_stream_maxlen = 10000;   // ~ MAXLEN, 0 = без лимита\n"
            . "\$heavy_task_ttl            = 86400;   // TTL статуса задачи, сек\n"
            . "\$heavy_chunk_size          = 5000;    // строк за один чанк DELETE\n"
            . "\$heavy_chunk_pause_us      = 100000;  // 100ms пауза между чанками\n";

        if (preg_match('/^(\$redis_stream_maxlen\s*=\s*[^;]+;.*)$/m', $src, $m)) {
            $src = str_replace($m[0], $m[0] . "\n" . $heavy_block, $src);
            $added[] = 'heavy_tasks after $redis_stream_maxlen';
        } elseif (preg_match('/^(\$salt\s*=)/m', $src, $m)) {
            $src = str_replace($m[0], $heavy_block . "\n" . $m[0], $src);
            $added[] = 'heavy_tasks before $salt';
        } else {
            $src .= $heavy_block;
            $added[] = 'heavy_tasks appended';
        }
    }

    /* ──────────────────────────────────────────────────────
     * Ничего не менялось?
     * ────────────────────────────────────────────────────── */
    if (empty($added) || $src === $orig) {
        $result['status'] = 'already';
        return $result;
    }

    $result['matched'] = $added;

    if ($dry_run) {
        $result['status'] = 'updated';
        return $result;
    }

    /* ──────────────────────────────────────────────────────
     * Backup
     * ────────────────────────────────────────────────────── */
    if (!$no_backup) {
        $backup = $path . '.params.bak.' . date('Ymd_His');
        if (!@copy($path, $backup)) {
            err("[creds-params] backup failed: {$backup}");
            $result['status'] = 'error';
            return $result;
        }
        $result['backup'] = $backup;
    }

    if (@file_put_contents($path, $src) === false) {
        err("[creds-params] write failed: {$path}");
        $result['status'] = 'error';
        return $result;
    }

    $result['status'] = 'updated';
    return $result;
}

/* ══════════════════════════════════════════════════════════
 * Phase 3.1: DDL — создать shared-таблицы
 * ══════════════════════════════════════════════════════════ */

function create_tables(mysqli $db, bool $dry_run): void
{
    global $db_engine;

    // Fallback на случай, если creds.php не определяет движок
    $engine = !empty($db_engine) ? (string)$db_engine : 'ROCKSDB';

    // Разрешаем только известные движки — защита от подстановки произвольного SQL
    $allowed_engines = ['ROCKSDB', 'INNODB'];
    if (!in_array(strtoupper($engine), $allowed_engines, true)) {
        err("[ddl] unknown engine '{$engine}', falling back to ROCKSDB");
        $engine = 'ROCKSDB';
    }

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
) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",

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
) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",

        'logs' => "
CREATE TABLE IF NOT EXISTS logs (
    user_id BIGINT UNSIGNED NOT NULL,
    session BIGINT UNSIGNED NOT NULL,
    time    BIGINT UNSIGNED NOT NULL,
    data    LONGTEXT        NOT NULL,
    PRIMARY KEY (user_id, time),
    KEY session_time (user_id, session, time)
) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
    ];

    if ($dry_run) {
        out("[ddl] engine: {$engine}");
        out("[ddl] would CREATE TABLE IF NOT EXISTS: " . implode(', ', array_keys($ddl)));
        return;
    }

    foreach ($ddl as $name => $sql) {
        try {
            $db->query($sql);
        } catch (mysqli_sql_exception $e) {
            err("CREATE {$name} failed: " . $e->getMessage());
            exit(1);
        }
    }
    info("[ddl] shared tables ready ({$engine}): pids, sessions, logs");
}

/* ══════════════════════════════════════════════════════════
 * Хелперы проверки существования объектов БД
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

if (!function_exists('column_exists')) {
    function column_exists(mysqli $db, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = ?
                AND column_name = ?"
        );
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        return (bool)$stmt->get_result()->num_rows;
    }
}

if (!function_exists('index_exists')) {
    function index_exists(mysqli $db, string $table, string $index): bool
    {
        $stmt = $db->prepare(
            "SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = ?
                AND index_name = ?"
        );
        $stmt->bind_param('ss', $table, $index);
        $stmt->execute();
        return (bool)$stmt->get_result()->num_rows;
    }
}

function columns_of(mysqli $db, string $t): array
{
    $r = $db->query("SHOW COLUMNS FROM `$t`");
    return $r ? array_column($r->fetch_all(MYSQLI_ASSOC), 'Field') : [];
}

/* ══════════════════════════════════════════════════════════
 * Phase 3.2: ALTER users — дотянуть схему users до актуальной.
 *
 * Пропускается, если таблицы users нет (clean install —
 * её создаст auth_functions.php::create_users_table() при первом заходе).
 * ══════════════════════════════════════════════════════════ */

function migrate_users_table(mysqli $db, string $db_users, bool $dry_run): void
{
    if (!table_exists($db, $db_users)) {
        out("[users] table not found — clean install, skipping column migration");
        return;
    }

    $migrations = [
        'stream_lock'     => "ALTER TABLE `$db_users` ADD COLUMN stream_lock TINYINT(1) NOT NULL DEFAULT 0",
        'sessions_filter' => "ALTER TABLE `$db_users` ADD COLUMN sessions_filter TINYINT(1) NOT NULL DEFAULT 1",
        'share_secret'    => "ALTER TABLE `$db_users` ADD COLUMN share_secret CHAR(32)",
        'login_attempts'  => "ALTER TABLE `$db_users` ADD COLUMN login_attempts TINYINT UNSIGNED DEFAULT 0",
        'last_attempt'    => "ALTER TABLE `$db_users` ADD COLUMN last_attempt DATETIME",
        'api_gps'         => "ALTER TABLE `$db_users` ADD COLUMN api_gps TINYINT(1) NOT NULL DEFAULT 0",
        'lang'            => "ALTER TABLE `$db_users` ADD COLUMN lang ENUM('en','ru','es','de') NOT NULL DEFAULT 'en' AFTER gap",
        'mcu_data'        => "ALTER TABLE `$db_users` ADD COLUMN mcu_data VARCHAR(2048) NULL AFTER sessions_filter",
    ];

    $added = 0;
    foreach ($migrations as $col => $sql) {
        if (column_exists($db, $db_users, $col)) {
            continue;
        }
        if ($dry_run) {
            out("[users] [dry] would ADD COLUMN {$col}");
            $added++;
            continue;
        }
        try {
            $db->query($sql);
            info("[users] added column: {$col}");
            $added++;
        } catch (mysqli_sql_exception $e) {
            err("[users] ALTER {$col} failed: " . $e->getMessage());
        }
    }

    // Legacy-индекс, который оставался с очень старых версий
    if (index_exists($db, $db_users, 'indexes')) {
        if ($dry_run) {
            out("[users] [dry] would DROP INDEX `indexes`");
        } else {
            try {
                $db->query("DROP INDEX `indexes` ON `$db_users`");
                info("[users] dropped legacy index: indexes");
            } catch (mysqli_sql_exception $e) {
                err("[users] DROP INDEX `indexes` failed: " . $e->getMessage());
            }
        }
    }

    if ($added === 0 && !$dry_run) {
        out("[users] no column changes needed");
    }
}

/* ══════════════════════════════════════════════════════════
 * Phase 4: миграция данных из per-user таблиц в shared
 * ══════════════════════════════════════════════════════════ */

/**
 * Копирует строки из {src_table} в {dst_table} c добавлением user_id.
 * Используется для sessions и pids.
 */
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

    try {
        $db->query(
            "INSERT IGNORE INTO {$dst_table} (user_id, {$col_list})
             SELECT {$uid}, {$col_list} FROM `{$src_table}`"
        );
    } catch (mysqli_sql_exception $e) {
        err("  ERROR: " . $e->getMessage());
        return;
    }
    info("  + {$src_table} → {$dst_table}: source={$count}, copied={$db->affected_rows}");
}

/**
 * Копирует строки из {src_table} в logs, превращая PID-колонки в JSON.
 * Работает батчами по 1000 строк, пагинируясь по time (PRIMARY KEY).
 *
 * Семантика значений:
 *   - null / нечисловое → пропускается;
 *   - 'Infinity'        → -1 (как в encode_log_data);
 *   - 0                 → пишется (Fan OFF, Rollback OK, Gear N и т.п.).
 */
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

        try {
            $db->execute_query($sql, $values);
        } catch (mysqli_sql_exception $e) {
            err("  ERROR batch: " . $e->getMessage());
            exit(1);
        }

        $migrated += count($rows);

        if ($total > 5000 && ($migrated % 10000 === 0)) {
            out("    ... {$migrated}/{$total}");
        }
    }

    info("  + {$src_table} → logs: migrated {$migrated} rows");
}

/* ══════════════════════════════════════════════════════════
 * Main
 * ══════════════════════════════════════════════════════════ */

out("=== RedBox Telemetry migrations ===");
out($DRY_RUN ? "MODE: DRY-RUN (no changes)" : "MODE: LIVE");
if ($RESET) out("FLAG: --reset (shared tables will be dropped)");
if ($AUTO)  out("MODE: AUTO (quiet)");

/* ═══════════════ Phase 1: creds.php ═══════════════ */

phase("Phase 1/4: src/creds.php");

$creds_path         = __DIR__ . '/src/creds.php';
$creds_example_path = __DIR__ . '/src/creds.php.example';

// 1.1 Legacy: если creds.php лежит в корне — переносим в src/
if (!file_exists($creds_path) && file_exists(__DIR__ . '/creds.php')) {
    if (!$DRY_RUN) {
        rename(__DIR__ . '/creds.php', $creds_path);
        info("[creds] moved: creds.php → src/creds.php");
    } else {
        out("[creds] [dry] would move: creds.php → src/creds.php");
    }
}

// 1.2 Если creds.php всё ещё нет — создаём из example
if (!file_exists($creds_path) && file_exists($creds_example_path)) {
    if (!$DRY_RUN) {
        if (!@copy($creds_example_path, $creds_path)) {
            err("[creds] cannot copy example → creds.php");
            exit(2);
        }
        @chmod($creds_path, 0644);
        info("[creds] created from example");
    } else {
        out("[creds] [dry] would create creds.php from example");
    }
}

if (!file_exists($creds_path)) {
    err("[creds] not found and no example to copy from");
    exit(2);
}

// 1.3 Migrates per-user → shared tables
$creds_result = migrate_creds_php($creds_path, $DRY_RUN);

switch ($creds_result['status']) {
    case 'already':
        out("[creds] shared tables: already migrated");
        break;

    case 'updated':
        foreach ($creds_result['matched'] as $m) {
            out("[creds] pattern matched: {$m}");
        }
        if ($DRY_RUN) {
            out("[creds] [dry] would update {$creds_path}");
        } else {
            info("[creds] shared-tables migration applied: {$creds_path}");
            if (!empty($creds_result['backup'])) {
                info("[creds] backup: {$creds_result['backup']}");
            }
        }
        break;

    case 'no_match':
        err("[creds] no shared-tables patterns matched in {$creds_path}");
        err("[creds] файл не похож на стандартный creds.php;");
        err("[creds] проверьте вручную или начните с creds.php.example");
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

// 1.4 Добавить недостающие параметры (heavy_tasks и др.)
$params_result = migrate_creds_params($creds_path, $DRY_RUN, $NO_BACKUP);

switch ($params_result['status']) {
    case 'already':
        out("[creds] params: already present");
        break;

    case 'updated':
        foreach ($params_result['matched'] as $m) {
            out("[creds] params added: {$m}");
        }
        if ($DRY_RUN) {
            out("[creds] [dry] would add new params to {$creds_path}");
        } else {
            info("[creds] new params added: {$creds_path}");
            if (!empty($params_result['backup'])) {
                info("[creds] backup: {$params_result['backup']}");
            }
        }
        break;

    case 'error':
    default:
        if (!$DRY_RUN) {
            err("[creds] params update failed — ABORT");
            exit(2);
        }
}

/* ═══════════════ Phase 2: DB connection ═══════════════ */

phase("Phase 2/4: DB connection");

require_once $creds_path;

global $db_host, $db_user, $db_pass, $db_name, $db_port, $db_users;

$db       = null;
$deadline = time() + max(0, $WAIT_DB);
$lastErr  = '';

// Retry-loop: полезен при старте контейнера — БД может ещё подниматься,
// даже если healthcheck уже прошёл.
while (true) {
    try {
        $db = new mysqli($db_host, $db_user, $db_pass, $db_name, (int)$db_port);
        if ($db->connect_error) {
            throw new Exception($db->connect_error);
        }
        break;
    } catch (Throwable $e) {
        $lastErr = $e->getMessage();
        if (time() >= $deadline) {
            err("DB connect failed: {$lastErr}");
            exit(1);
        }
        out("[db] waiting for DB... {$lastErr}");
        sleep(2);
    }
}
$db->set_charset('utf8mb4');

/* ═══════════════ Phase 3: DDL ═══════════════ */

phase("Phase 3/4: DDL");

// --reset: дропнуть shared-таблицы и создать заново (данные потеряются!)
if ($RESET) {
    if ($DRY_RUN) {
        out("[reset] would DROP TABLE IF EXISTS: logs, sessions, pids");
    } else {
        out("[reset] dropping existing shared tables...");
        try {
            $db->query("DROP TABLE IF EXISTS logs");
            $db->query("DROP TABLE IF EXISTS sessions");
            $db->query("DROP TABLE IF EXISTS pids");
            info("[reset] dropped: logs, sessions, pids");
        } catch (mysqli_sql_exception $e) {
            err("[reset] DROP failed: " . $e->getMessage());
            exit(1);
        }
    }
}

// 3.1 Создать shared-таблицы, если их нет
create_tables($db, $DRY_RUN);

// 3.2 Дотянуть users до актуальной схемы (ALTER-ы).
// Пропускается на clean install (users ещё нет).
migrate_users_table($db, $db_users, $DRY_RUN);

/* ═══════════════ Phase 4: data migration ═══════════════ */

phase("Phase 4/4: data migration");

// Clean install: таблицы users ещё нет (её создаст auth_functions.php
// при первом заходе на логин). Мигрировать нечего.
if (!table_exists($db, $db_users)) {
    info("[migrate] users table not found — clean install, data migration skipped");
    info("[migrate] done");
    $db->close();
    exit(0);
}

$users = $db->query(
    "SELECT id, user FROM `$db_users` ORDER BY id ASC"
)->fetch_all(MYSQLI_ASSOC);

out("[users] found: " . count($users));

/* Быстрая проверка: есть ли вообще per-user таблицы?
 * Раньше цикл по юзерам всегда проходил полностью (N * 3 table_exists).
 * Теперь — если ни у кого нет {user}_logs/{user}_sessions/{user}_pids,
 * Phase 4 пропускается целиком. Это заметно ускоряет startup
 * уже мигрированных инсталляций. */
$has_old_tables = false;
foreach ($users as $u) {
    $login = $u['user'];
    if (table_exists($db, $login . '_logs')
        || table_exists($db, $login . '_sessions')
        || table_exists($db, $login . '_pids')) {
        $has_old_tables = true;
        break;
    }
}

$allowed_sessions = [
    'id','profileName','description','ip','sessionsize',
    'session','time','timeend','favorite',
];
$allowed_pids = [
    'id','description','units','populated','stream','favorite',
];

if (!$has_old_tables) {
    info("[migrate] no per-user tables found — data migration skipped");
} else {
    foreach ($users as $u) {
        $uid   = (int)$u['id'];
        $login = $u['user'];

        out("[user] id={$uid} login={$login}");

        // Снимок "до" — чтобы в verbose показать дельту
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

            info(sprintf(
                "  = sessions: %d → %d | pids: %d → %d | logs: %d → %d",
                $before_sess, $after_sess,
                $before_pids, $after_pids,
                $before_logs, $after_logs
            ));
        }

        out();
    }
}

/* ═══════════════ Итог ═══════════════ */

if (!$DRY_RUN) {
    if ($GLOBALS['VERBOSE']) {
        // Подробный отчёт — только в интерактивном режиме,
        // не в Docker startup.
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
        // AUTO-режим: краткий итог для Docker-логов
        info("[migrate] done");
    }
} else {
    out("[dry] no changes made.");
}

$db->close();

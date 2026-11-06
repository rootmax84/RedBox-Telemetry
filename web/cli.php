<?php
declare(strict_types=1);

/**
 * cli.php — RedBox Telemetry admin CLI
 *
 * Запуск:  php cli.php <command> [args...] [--options]
 *          php cli.php help
 */

if (PHP_SAPI !== 'cli') {
    header('Location: /');
    exit;
}

$_SESSION = ['torque_logged_in' => true, 'admin' => true];
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['REQUEST_METHOD']  = 'CLI';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';

$username = '';
$limit    = 0;

chdir(__DIR__);

/* ────────────────────────────────────────────────────────────
 * TOP-LEVEL creds.php + helpers.php
 *
 * helpers.php нужен для seed_default_pids()/default_pids_data().
 * Оба файла подключаются на верхнем уровне (в глобальном scope),
 * чтобы creds.php выставил $db_host/$db_user/... и т.д.
 * ──────────────────────────────────────────────────────────── */
$credsPath   = __DIR__ . '/src/creds.php';
$helpersPath = __DIR__ . '/src/helpers.php';

if (!file_exists($credsPath)) {
    fwrite(STDERR, "[error] src/creds.php not found at: $credsPath\n");
    exit(1);
}
if (!file_exists($helpersPath)) {
    fwrite(STDERR, "[error] src/helpers.php not found at: $helpersPath\n");
    exit(1);
}

try {
    require_once $credsPath;
    require_once $helpersPath;
} catch (Throwable $ex) {
    fwrite(STDERR, "[error] Failed to load bootstrap files: " . $ex->getMessage() . "\n");
    exit(1);
}

exit(main($argv));

/* ════════════════════════════════════════════════════════════
 * ENTRY POINT
 * ════════════════════════════════════════════════════════════ */

function main(array $argv): int
{
    $cmd   = $argv[1] ?? null;
    $debug = in_array('--debug', $argv, true);

    if ($cmd === null || in_array($cmd, ['help', '-h', '--help'], true)) {
        printUsage();
        return $cmd === null ? 2 : 0;
    }

    try {
        $db = connectDb($debug);
    } catch (Throwable $ex) {
        e("[error] " . $ex->getMessage());
        if ($debug) {
            e("");
            e("Trace:");
            e($ex->getTraceAsString());
        }
        return 1;
    }

    try {
        $exit = dispatch($db, $cmd, array_slice($argv, 2));
    } catch (Throwable $ex) {
        e("[error] " . $ex->getMessage());
        if ($debug) {
            e($ex->getTraceAsString());
        }
        $exit = 1;
    }

    $db->close();
    return $exit;
}

function dispatch(mysqli $db, string $cmd, array $args): int
{
    [$pos, $opts] = parseArgs($args, [
        'password', 'limit', 'user', 'id', 'profile',
        'batch-size', 'batch-pause',
    ]);

    switch ($cmd) {
        case 'admin:reset':
            return cmdAdminReset($db, $pos, $opts);
        case 'user:list':
            return cmdUserList($db, $pos, $opts);
        case 'user:add':
            return cmdUserAdd($db, $pos, $opts);
        case 'user:delete':
            return cmdUserDelete($db, $pos, $opts);
        case 'user:reset':
            return cmdUserReset($db, $pos, $opts);
        case 'user:limit':
            return cmdUserLimit($db, $pos, $opts);
        case 'user:disable':
            return cmdUserToggle($db, $pos, $opts, false);
        case 'user:enable':
            return cmdUserToggle($db, $pos, $opts, true);
        case 'orphans:scan':
            return cmdOrphansScan($db, $pos, $opts);
        case 'orphans:clean':
            return cmdOrphansClean($db, $pos, $opts);
        case 'orphans:recover':
            return cmdOrphansRecover($db, $pos, $opts);
        case 'maintenance:on':
            return cmdMaintenance($pos, $opts, true);
        case 'maintenance:off':
            return cmdMaintenance($pos, $opts, false);
        case 'maintenance:status':
            return cmdMaintenanceStatus();
        default:
            fwrite(STDERR, "Unknown command: $cmd\n\n");
            printUsage();
            return 2;
    }
}

/* ════════════════════════════════════════════════════════════
 * HELPERS
 * ════════════════════════════════════════════════════════════ */

function o(string $s = ''): void
{
    fwrite(STDOUT, $s . "\n");
}

function e(string $s): void
{
    fwrite(STDERR, $s . "\n");
}

function connectDb(bool $debug = false): mysqli
{
    global $db_host, $db_user, $db_pass, $db_name, $db_port;

    $host = (string)($db_host ?? '');
    $port = ($db_port !== '' && $db_port !== null) ? (int)$db_port : null;

    if ($debug) {
        e(sprintf(
            "[debug] connect: host=%s port=%s user=%s db=%s",
            $host === '' ? '(EMPTY!)' : $host,
            $port === null ? '(null)' : (string)$port,
            (string)($db_user ?? ''),
            (string)($db_name ?? '')
        ));
    }

    if ($host === '') {
        throw new RuntimeException(
            '$db_host is empty. This usually means creds.php was not loaded ' .
            'at global scope. Make sure require_once src/creds.php is at ' .
            'the top level of cli.php, not inside a function.'
        );
    }

    try {
        $db = new mysqli(
            $host,
            (string)($db_user ?? ''),
            (string)($db_pass ?? ''),
            (string)($db_name ?? ''),
            $port
        );
    } catch (mysqli_sql_exception $ex) {
        throw new RuntimeException(
            sprintf(
                'DB connect failed: host=%s port=%s user=%s db=%s — %s',
                $host,
                $port === null ? '(null)' : (string)$port,
                (string)($db_user ?? ''),
                (string)($db_name ?? ''),
                $ex->getMessage()
            ),
            0,
            $ex
        );
    }

    if ($db->connect_error) {
        throw new RuntimeException(
            sprintf('DB connect failed: host=%s — %s', $host, $db->connect_error)
        );
    }

    $db->set_charset('utf8mb4');
    return $db;
}

function isInteractive(): bool
{
    if (function_exists('stream_isatty')) {
        return @stream_isatty(STDIN);
    }
    if (function_exists('posix_isatty')) {
        return @posix_isatty(STDIN);
    }
    return true;
}

function confirm(string $msg, bool $defaultYes = false): bool
{
    if (!isInteractive()) {
        return false;
    }

    $suffix = $defaultYes ? ' [Y/n] ' : ' [y/N] ';
    fwrite(STDOUT, $msg . $suffix);
    $line = trim((string)fgets(STDIN));

    if ($line === '') {
        return $defaultYes;
    }
    return strtolower($line[0]) === 'y';
}

function requireForce(array $opts, string $msg): bool
{
    if (isset($opts['force'])) {
        return true;
    }
    if (!isInteractive()) {
        e("Non-interactive session: pass --force to proceed.");
        e("Reason: " . $msg);
        return false;
    }
    return confirm($msg, false);
}

function readPassword(string $prompt): string
{
    if (!isInteractive()) {
        fwrite(STDOUT, $prompt);
        return trim((string)fgets(STDIN));
    }

    fwrite(STDOUT, $prompt);
    @shell_exec('stty -echo 2>/dev/null');
    $line = trim((string)fgets(STDIN));
    @shell_exec('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
    return $line;
}

function parseArgs(array $args, array $valueOpts = []): array
{
    $pos = [];
    $opts = [];
    $n = count($args);

    for ($i = 0; $i < $n; $i++) {
        $a = $args[$i];

        if ($a === '--') {
            for ($j = $i + 1; $j < $n; $j++) {
                $pos[] = $args[$j];
            }
            break;
        }

        if (str_starts_with($a, '--')) {
            $k = substr($a, 2);
            if (str_contains($k, '=')) {
                [$k, $v] = explode('=', $k, 2);
                $opts[$k] = $v;
            } elseif (in_array($k, $valueOpts, true) && $i + 1 < $n) {
                $opts[$k] = $args[++$i];
            } else {
                $opts[$k] = true;
            }
        } else {
            $pos[] = $a;
        }
    }

    return [$pos, $opts];
}

/**
 * Разбор целочисленной опции с обязательным значением и границами.
 *
 * Бросает RuntimeException на --opt без значения, не-число и выход за
 * пределы. Значение возвращается как int — интерполяция в SQL безопасна.
 */
function parseBatchIntOpt(array $opts, string $name, int $default, int $min, int $max): int
{
    if (!array_key_exists($name, $opts)) {
        return $default;
    }

    $raw = $opts[$name];

    if ($raw === true) {
        throw new RuntimeException("--$name requires a value (use --$name=VALUE)");
    }
    if (!is_numeric($raw)) {
        throw new RuntimeException("--$name must be an integer");
    }

    $val = (int)$raw;

    if ($val < $min || $val > $max) {
        throw new RuntimeException("--$name must be between $min and $max (got $val)");
    }

    return $val;
}

function promptPassword(array $opts): ?string
{
    $password = $opts['password'] ?? null;

    if ($password !== null) {
        if (!is_string($password) || $password === '') {
            return null;
        }
        return $password;
    }

    $password = readPassword('New password: ');
    if ($password === '') {
        return null;
    }

    $confirm = readPassword('Confirm password: ');
    if ($password !== $confirm) {
        e("Passwords do not match.");
        return null;
    }

    return $password;
}

function hashPassword(string $password): string
{
    global $salt;

    $cost = max(4, min(31, (int)($salt['cost'] ?? 10)));
    $hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => $cost]);

    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('password_hash() failed');
    }

    return $hash;
}

function findUser(mysqli $db, string $username): ?array
{
    $stmt = $db->prepare("SELECT * FROM users WHERE user = ? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function usersTableExists(mysqli $db): bool
{
    $r = $db->query(
        "SELECT 1 FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = 'users'"
    );
    return $r && $r->num_rows > 0;
}

function maintenanceFilePath(): string
{
    return __DIR__ . '/_maintenance';
}

/**
 * Универсальный count по user_id для заданной таблицы.
 * Возвращает 0 при ошибке (например, таблицы нет в старой инсталляции).
 */
function countByUserId(mysqli $db, string $table, int $uid): int
{
    $allowed = ['sessions', 'logs', 'pids'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $r = $db->query("SELECT COUNT(*) FROM `$table` WHERE user_id = " . (int)$uid);
    if (!$r) {
        return 0;
    }

    $row = $r->fetch_row();
    $r->free();

    return $row ? (int)$row[0] : 0;
}

/**
 * Удалить все строки с заданным user_id из таблицы.
 * Возвращает число удалённых строк.
 */
function deleteByUserId(mysqli $db, string $table, int $uid): int
{
    $allowed = ['sessions', 'logs', 'pids'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $stmt = $db->prepare("DELETE FROM `$table` WHERE user_id = ?");
    if (!$stmt) {
        throw new RuntimeException("prepare failed for DELETE FROM `$table`: " . $db->error);
    }

    $stmt->bind_param('i', $uid);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException("DELETE FROM `$table` failed: " . $err);
    }

    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    return $affected;
}

/* ════════════════════════════════════════════════════════════
 * ORPHANS
 * ════════════════════════════════════════════════════════════ */

/**
 * Найти сессии без логов: rows в `sessions`, для которых нет
 * ни одной строки в `logs` с той же парой (user_id, session).
 *
 * @return array{total:int, by_user:array<int,int>}
 */
function orphansScanSessions(mysqli $db, ?int $uid): array
{
    $sql = "SELECT s.user_id, COUNT(*) AS cnt
              FROM sessions s
              LEFT JOIN logs l
                     ON l.user_id = s.user_id
                    AND l.session = s.session
             WHERE l.session IS NULL";

    if ($uid !== null) {
        $sql .= " AND s.user_id = " . (int)$uid;
    }
    $sql .= " GROUP BY s.user_id ORDER BY s.user_id";

    $r = $db->query($sql);
    if (!$r) {
        throw new RuntimeException("orphans scan (sessions) failed: " . $db->error);
    }

    $total  = 0;
    $byUser = [];
    while ($row = $r->fetch_assoc()) {
        $u = (int)$row['user_id'];
        $c = (int)$row['cnt'];
        $byUser[$u] = $c;
        $total += $c;
    }
    $r->free();

    return [
        'total'   => $total,
        'by_user' => $byUser,
    ];
}

/**
 * Найти логи без сессий.
 *
 * @return array{
 *     total_rows:int,
 *     total_sessions:int,
 *     by_user:array<int, array{user_id:int, session:int, rows:int}>
 * }
 */
function orphansScanLogs(mysqli $db, ?int $uid): array
{
    $sql = "SELECT l.user_id, l.session, COUNT(*) AS cnt
              FROM logs l
              LEFT JOIN sessions s
                     ON s.user_id = l.user_id
                    AND s.session = l.session
             WHERE s.session IS NULL";

    if ($uid !== null) {
        $sql .= " AND l.user_id = " . (int)$uid;
    }
    $sql .= " GROUP BY l.user_id, l.session ORDER BY l.user_id, l.session";

    $r = $db->query($sql);
    if (!$r) {
        throw new RuntimeException("orphans scan (logs) failed: " . $db->error);
    }

    $totalRows     = 0;
    $totalSessions = 0;
    $byUser        = [];
    while ($row = $r->fetch_assoc()) {
        $item = [
            'user_id' => (int)$row['user_id'],
            'session' => (int)$row['session'],
            'rows'    => (int)$row['cnt'],
        ];
        $byUser[] = $item;
        $totalRows += $item['rows'];
        $totalSessions++;
    }
    $r->free();

    return [
        'total_rows'     => $totalRows,
        'total_sessions' => $totalSessions,
        'by_user'        => $byUser,
    ];
}

/**
 * Удалить сессии без логов.
 *
 * Батчинг: DELETE не поддерживает multi-table LIMIT, поэтому используем
 * материализованную derived table + tuple IN по PK (user_id, session).
 * Внутренний SELECT с LIMIT N материализуется (не может быть влит
 * оптимизатором), внешний DELETE удаляет ровно N строк — по индексу.
 *
 * Итог агрегируется по батчам. Пауза между батчами даёт InnoDB purge
 * догнать undo/binlog и снижает длительность row-lock'ов.
 *
 * @return int суммарно удалено строк sessions
 */
function orphansDeleteSessions(
    mysqli $db,
    ?int $uid,
    int $batchSize = 1000,
    int $batchPauseMs = 0
): int {
    $batchSize = max(1, $batchSize);

    $uidClause = ($uid !== null) ? " AND s.user_id = ?" : "";

    $sql = "DELETE FROM sessions
              WHERE (user_id, session) IN (
                SELECT user_id, session FROM (
                    SELECT s.user_id, s.session
                      FROM sessions s
                      LEFT JOIN logs l
                             ON l.user_id = s.user_id
                            AND l.session = s.session
                     WHERE l.session IS NULL
                     $uidClause
                     LIMIT $batchSize
                ) AS batch
              )";

    $total = 0;

    while (true) {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException("prepare failed for batch delete (sessions): " . $db->error);
        }

        if ($uid !== null) {
            $stmt->bind_param('i', $uid);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException("batch delete (sessions) failed: " . $err);
        }

        $affected = (int)$stmt->affected_rows;
        $stmt->close();

        $total += $affected;

        if ($affected < $batchSize) {
            break;
        }

        if ($batchPauseMs > 0) {
            usleep($batchPauseMs * 1000);
        }
    }

    return $total;
}

/**
 * Удалить логи без сессий.
 *
 * Аналогично orphansDeleteSessions(): материализованная derived table
 * с LIMIT + tuple IN по PK (user_id, time), т.к. multi-table DELETE
 * не поддерживает LIMIT.
 *
 * @return int суммарно удалено строк logs
 */
function orphansDeleteLogs(
    mysqli $db,
    ?int $uid,
    int $batchSize = 1000,
    int $batchPauseMs = 0
): int {
    $batchSize = max(1, $batchSize);

    $uidClause = ($uid !== null) ? " AND l.user_id = ?" : "";

    $sql = "DELETE FROM logs
              WHERE (user_id, time) IN (
                SELECT user_id, time FROM (
                    SELECT l.user_id, l.time
                      FROM logs l
                      LEFT JOIN sessions s
                             ON s.user_id = l.user_id
                            AND s.session = l.session
                     WHERE s.session IS NULL
                     $uidClause
                     LIMIT $batchSize
                ) AS batch
              )";

    $total = 0;

    while (true) {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException("prepare failed for batch delete (logs): " . $db->error);
        }

        if ($uid !== null) {
            $stmt->bind_param('i', $uid);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException("batch delete (logs) failed: " . $err);
        }

        $affected = (int)$stmt->affected_rows;
        $stmt->close();

        $total += $affected;

        if ($affected < $batchSize) {
            break;
        }

        if ($batchPauseMs > 0) {
            usleep($batchPauseMs * 1000);
        }
    }

    return $total;
}

/**
 * Создать недостающие строки в `sessions` для orphan-логов.
 *
 * Для каждой пары (user_id, session), присутствующей в `logs`,
 * но отсутствующей в `sessions`, вставляется строка:
 *
 *   id          = $sessionId          (по умолчанию '-')
 *   profileName = $profileName        (по умолчанию 'Orphan sessions')
 *   description = '-'
 *   ip          = '0.0.0.0'
 *   sessionsize = COUNT(*) строк logs этой сессии
 *   session     = l.session
 *   time        = MIN(l.time)
 *   timeend     = MAX(l.time)
 *   favorite    = 0
 *
 * INSERT IGNORE — защита от гонки с параллельным upload/повторным
 * вызовом recover: если сессия успела появиться, вставка тихо
 * пропускается, существующие данные не перетираются.
 *
 * @return int число реально вставленных строк sessions
 */
function orphansRecoverSessions(
    mysqli $db,
    ?int $uid,
    string $sessionId,
    string $profileName
): int {
    $sql = "INSERT IGNORE INTO sessions
                (user_id, id, profileName, description, ip, sessionsize, session, time, timeend, favorite)
            SELECT
                l.user_id,
                ?,
                ?,
                '-',
                '0.0.0.0',
                COUNT(*),
                l.session,
                MIN(l.time),
                MAX(l.time),
                0
              FROM logs l
              LEFT JOIN sessions s
                     ON s.user_id = l.user_id
                    AND s.session = l.session
             WHERE s.session IS NULL";

    if ($uid !== null) {
        $sql .= " AND l.user_id = " . (int)$uid;
    }
    $sql .= " GROUP BY l.user_id, l.session";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException("prepare failed for orphans recover: " . $db->error);
    }

    $stmt->bind_param('ss', $sessionId, $profileName);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException("orphans recover failed: " . $err);
    }

    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    return $affected;
}

/**
 * Валидация значения для sessions.id (VARCHAR(32)).
 *
 * @throws RuntimeException при некорректном значении
 */
function validateSessionId($raw): string
{
    if ($raw === true) {
        throw new RuntimeException("--id requires a value (use --id=VALUE)");
    }

    $v = trim((string)$raw);

    if ($v === '') {
        throw new RuntimeException("--id cannot be empty");
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $v)) {
        throw new RuntimeException("--id contains control characters or newlines");
    }
    if (mb_strlen($v) > 32) {
        throw new RuntimeException(
            "--id must be at most 32 characters (got " . mb_strlen($v) . ")"
        );
    }

    return $v;
}

/**
 * Валидация значения для sessions.profileName (VARCHAR(128)).
 *
 * @throws RuntimeException при некорректном значении
 */
function validateProfileName($raw): string
{
    if ($raw === true) {
        throw new RuntimeException("--profile requires a value (use --profile=VALUE)");
    }

    $v = trim((string)$raw);

    if ($v === '') {
        throw new RuntimeException("--profile cannot be empty");
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $v)) {
        throw new RuntimeException("--profile contains control characters or newlines");
    }
    if (mb_strlen($v) > 128) {
        throw new RuntimeException(
            "--profile must be at most 128 characters (got " . mb_strlen($v) . ")"
        );
    }

    return $v;
}

function cmdOrphansScan(mysqli $db, array $pos, array $opts): int
{
    return orphansImpl($db, $opts, 'scan');
}

function cmdOrphansClean(mysqli $db, array $pos, array $opts): int
{
    return orphansImpl($db, $opts, 'clean');
}

function cmdOrphansRecover(mysqli $db, array $pos, array $opts): int
{
    return orphansImpl($db, $opts, 'recover');
}

/**
 * Общая реализация orphans:* — один и тот же скан, разные действия.
 *
 * @param string $mode 'scan' | 'clean' | 'recover'
 */
function orphansImpl(mysqli $db, array $opts, string $mode): int
{
    $isScan    = ($mode === 'scan');
    $isClean   = ($mode === 'clean');
    $isRecover = ($mode === 'recover');
    $jsonMode  = isset($opts['json']);

    if (!usersTableExists($db)) {
        e("[error] users table does not exist");
        return 1;
    }

    /* ── Recover-only options: --id / --profile ──
     * Разрешены ТОЛЬКО для orphans:recover. Для scan/clean —
     * отвергаем с ошибкой, чтобы не было ложных ожиданий.
     * ── */
    $recoverId          = '-';
    $recoverProfileName = 'Orphan sessions';

    if (isset($opts['id']) || isset($opts['profile'])) {
        if (!$isRecover) {
            e("[error] --id and --profile are only valid with orphans:recover");
            return 2;
        }

        try {
            if (isset($opts['id'])) {
                $recoverId = validateSessionId($opts['id']);
            }
            if (isset($opts['profile'])) {
                $recoverProfileName = validateProfileName($opts['profile']);
            }
        } catch (Throwable $ex) {
            e("[error] " . $ex->getMessage());
            return 2;
        }
    }

    /* ── Clean-only options: --batch-size / --batch-pause ──
     * Для scan/recover эти опции не имеют смысла — молча игнорируем,
     * чтобы не ломать совместимость с существующими скриптами.
     * ── */
    $batchSize     = 1000;
    $batchPauseMs  = 0;

    if ($isClean) {
        try {
            $batchSize    = parseBatchIntOpt($opts, 'batch-size',  1000, 1,       1000000);
            $batchPauseMs = parseBatchIntOpt($opts, 'batch-pause', 0,    0,       3600000);
        } catch (Throwable $ex) {
            e("[error] " . $ex->getMessage());
            return 2;
        }
    }

    /* ── Optional user filter ── */
    $uid   = null;
    $uname = null;
    if (isset($opts['user'])) {
        $uname = (string)$opts['user'];
        $u = findUser($db, $uname);
        if (!$u) {
            e("[error] user '$uname' not found");
            return 1;
        }
        $uid = (int)$u['id'];
    }

    $filterLabel = ($uid === null) ? "(all users)" : "user='$uname' (id=$uid)";

    if (!$jsonMode) {
        o("");
        o("Scanning orphans $filterLabel ...");
        o("");
    }

    /* ── Scan ── */
    try {
        $sessOrphans = orphansScanSessions($db, $uid);
        $logOrphans  = orphansScanLogs($db, $uid);
    } catch (Throwable $ex) {
        e("[error] scan failed: " . $ex->getMessage());
        return 1;
    }

    /* ── JSON mode: emit before any action ── */
    if ($jsonMode) {
        $payload = [
            'mode'   => $mode,
            'filter' => [
                'user_id'  => $uid,
                'username' => $uname,
            ],
            'orphan_sessions' => $sessOrphans,
            'orphan_logs'     => $logOrphans,
        ];
        if ($isRecover) {
            $payload['recover_options'] = [
                'id'          => $recoverId,
                'profileName' => $recoverProfileName,
            ];
        }
        if ($isClean) {
            $payload['batch_options'] = [
                'batch_size'      => $batchSize,
                'batch_pause_ms'  => $batchPauseMs,
            ];
        }
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "\n";
    } else {
        /* ── Human-readable ── */
        if ($sessOrphans['total'] === 0) {
            o("Sessions without logs:  none");
        } else {
            o(sprintf("Sessions without logs:  %d", $sessOrphans['total']));
            foreach ($sessOrphans['by_user'] as $u => $c) {
                o(sprintf("  user_id=%-6d  %d session(s)", $u, $c));
            }
        }
        o("");

        if ($logOrphans['total_sessions'] === 0) {
            o("Logs without sessions:  none");
        } else {
            o(sprintf(
                "Logs without sessions:  %d session(s), %d row(s)",
                $logOrphans['total_sessions'],
                $logOrphans['total_rows']
            ));
            foreach ($logOrphans['by_user'] as $item) {
                o(sprintf(
                    "  user_id=%-6d session=%-20d  %d row(s)",
                    $item['user_id'],
                    $item['session'],
                    $item['rows']
                ));
            }
        }
        o("");

        if ($isRecover) {
            o(sprintf(
                "Recover options:  id='%s', profileName='%s'",
                $recoverId,
                $recoverProfileName
            ));
            o("");
        }

        if ($isClean) {
            o(sprintf(
                "Batch options:    size=%d, pause=%dms",
                $batchSize,
                $batchPauseMs
            ));
            o("");
        }
    }

    /* ═══════════ scan: только статистика ═══════════ */
    if ($isScan) {
        if (!$jsonMode && ($sessOrphans['total'] > 0 || $logOrphans['total_sessions'] > 0)) {
            o("Hints:");
            if ($logOrphans['total_sessions'] > 0) {
                o("  • orphans:recover — create missing sessions for orphan logs (fixes them)");
            }
            if ($sessOrphans['total'] > 0) {
                o("  • orphans:clean   — delete orphan sessions (and orphan logs)");
            }
            o("");
        }
        return 0;
    }

    /* ═══════════ recover: создать sessions для orphan-логов ═══════════ */
    if ($isRecover) {
        if ($logOrphans['total_sessions'] === 0) {
            o("Nothing to recover.");
            if ($sessOrphans['total'] > 0) {
                o("Note: sessions without logs cannot be recovered; use orphans:clean to delete them.");
            }
            return 0;
        }

        if (isset($opts['dry-run'])) {
            o(sprintf(
                "[dry-run] would create %d session row(s) for %d orphan log row(s) " .
                "[id='%s', profileName='%s']",
                $logOrphans['total_sessions'],
                $logOrphans['total_rows'],
                $recoverId,
                $recoverProfileName
            ));
            if ($sessOrphans['total'] > 0) {
                o("Note: sessions without logs cannot be recovered; use orphans:clean to delete them.");
            }
            return 0;
        }

        if (!isset($opts['force'])) {
            if (!isInteractive()) {
                e("Non-interactive session: pass --force to proceed.");
                e(sprintf(
                    "Reason: this will create %d session row(s) in `sessions` " .
                    "for orphan logs (id='%s', profileName='%s').",
                    $logOrphans['total_sessions'],
                    $recoverId,
                    $recoverProfileName
                ));
                return 1;
            }
            $q = sprintf(
                "Create %d session row(s) in `sessions` for orphan logs " .
                "(id='%s', profileName='%s')?",
                $logOrphans['total_sessions'],
                $recoverId,
                $recoverProfileName
            );
            if (!confirm($q, false)) {
                e("Aborted.");
                return 1;
            }
        }

        o("");
        o("Recovering orphan log sessions ...");

        try {
            $created = orphansRecoverSessions($db, $uid, $recoverId, $recoverProfileName);
        } catch (Throwable $ex) {
            e("[error] recover failed: " . $ex->getMessage());
            return 1;
        }

        o("");
        o(sprintf(
            "✔ Created %d session row(s) for orphan logs (id='%s', profileName='%s')",
            $created,
            $recoverId,
            $recoverProfileName
        ));

        if ($created !== $logOrphans['total_sessions']) {
            o("");
            o("Note: created count differs from scan " .
              "(sessions appeared between scan and insert; they were skipped).");
        }

        if ($sessOrphans['total'] > 0) {
            o("");
            o(sprintf(
                "Note: %d session(s) without logs still need orphans:clean to be removed.",
                $sessOrphans['total']
            ));
        }

        return 0;
    }

    /* ═══════════ clean: удалить сирот ═══════════ */
    if ($isClean) {
        if ($sessOrphans['total'] === 0 && $logOrphans['total_rows'] === 0) {
            o("Nothing to clean.");
            return 0;
        }

        $totalLogs     = $logOrphans['total_rows'];
        $totalSessions = $sessOrphans['total'];
        $approxBatches = (int)ceil(max(1, $totalLogs) / $batchSize)
                       + (int)ceil(max(1, $totalSessions) / $batchSize);

        if (isset($opts['dry-run'])) {
            o(sprintf(
                "[dry-run] would delete %d orphan log row(s) and %d orphan session row(s)",
                $totalLogs,
                $totalSessions
            ));
            o(sprintf(
                "          in ~%d batch(es) of %d (pause %dms between batches)",
                $approxBatches,
                $batchSize,
                $batchPauseMs
            ));
            return 0;
        }

        if (!isset($opts['force'])) {
            if (!isInteractive()) {
                e("Non-interactive session: pass --force to proceed.");
                e(sprintf(
                    "Reason: this will delete %d orphan session(s) and %d orphan log row(s) " .
                    "permanently (in batches of %d).",
                    $totalSessions,
                    $totalLogs,
                    $batchSize
                ));
                return 1;
            }
            $q = sprintf(
                "Delete %d orphan session(s) and %d orphan log row(s) permanently " .
                "(in batches of %d)?",
                $totalSessions,
                $totalLogs,
                $batchSize
            );
            if (!confirm($q, false)) {
                e("Aborted.");
                return 1;
            }
        }

        o("");
        o(sprintf(
            "Deleting orphans in batches of %d%s ...",
            $batchSize,
            $batchPauseMs > 0 ? " (pause {$batchPauseMs}ms)" : ""
        ));
        o("");

        try {
            $deletedLogs     = orphansDeleteLogs($db, $uid, $batchSize, $batchPauseMs);
            $deletedSessions = orphansDeleteSessions($db, $uid, $batchSize, $batchPauseMs);
        } catch (Throwable $ex) {
            e("[error] clean failed: " . $ex->getMessage());
            e(sprintf(
                "[error] partial: logs deleted so far, sessions not touched " .
                "(re-run is safe: anti-join re-evaluated from scratch)."
            ));
            return 1;
        }

        o("");
        o(sprintf("✔ Deleted %d orphan log row(s)", $deletedLogs));
        o(sprintf("✔ Deleted %d orphan session(s)", $deletedSessions));

        if ($deletedLogs !== $logOrphans['total_rows']
            || $deletedSessions !== $sessOrphans['total']) {
            o("");
            o("Note: delete counts differ from scan (data changed between scan and delete).");
        }

        return 0;
    }

    /* unreachable */
    e("[error] unknown orphans mode: $mode");
    return 1;
}

/* ════════════════════════════════════════════════════════════
 * COMMANDS
 * ════════════════════════════════════════════════════════════ */

function cmdAdminReset(mysqli $db, array $pos, array $opts): int
{
    global $admin;

    if (!usersTableExists($db)) {
        e("[error] users table does not exist. Запустите web UI с файлом `install`.");
        return 1;
    }

    $adminUser = trim((string)($admin ?? ''));
    if ($adminUser === '') {
        e("[error] \$admin is not set in creds.php");
        return 1;
    }

    $user = findUser($db, $adminUser);
    if (!$user) {
        e("[error] admin user '$adminUser' not found in users table");
        return 1;
    }
    $uid = (int)$user['id'];

    $password = promptPassword($opts);
    if ($password === null) {
        e("[error] empty or mismatched password");
        return 1;
    }
    $hash = hashPassword($password);

    if (isset($opts['dry-run'])) {
        o("[dry-run] would update admin uid=$uid ('$adminUser')");
        return 0;
    }

    $stmt = $db->prepare(
        "UPDATE users SET pass = ?, login_attempts = 0, last_attempt = NOW() WHERE id = ?"
    );
    $stmt->bind_param('si', $hash, $uid);
    if (!$stmt->execute()) {
        e("[error] update failed: " . $db->error);
        return 1;
    }
    $stmt->close();

    o("✔ Admin password updated for '$adminUser' (id=$uid). Login attempts reset.");
    return 0;
}

function cmdUserList(mysqli $db, array $pos, array $opts): int
{
    if (!usersTableExists($db)) {
        e("[error] users table does not exist");
        return 1;
    }

    $sql = "
        SELECT u.id, u.user, u.s, u.login_attempts, u.last_attempt,
               COALESCE(s.cnt, 0) AS session_count
          FROM users u
          LEFT JOIN (SELECT user_id, COUNT(*) AS cnt FROM sessions GROUP BY user_id) s
                 ON s.user_id = u.id
         ORDER BY u.id
    ";
    $res  = $db->query($sql);
    $rows = $res->fetch_all(MYSQLI_ASSOC);

    if (isset($opts['json'])) {
        echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
        return 0;
    }

    if (empty($rows)) {
        o("(no users)");
        return 0;
    }

    printf("%-4s  %-10s  %-8s  %-10s  %-19s  %s\n",
        'ID', 'LIMIT', 'SESSIONS', 'ATTEMPTS', 'LAST ATTEMPT', 'USER');
    printf("%s\n", str_repeat('-', 76));

    foreach ($rows as $r) {
        $s = (int)$r['s'];
        $limit = ($s === -1) ? 'unlimited' : (($s === 0) ? 'DISABLED' : (string)$s);
        $last  = $r['last_attempt'] ?: '-';
        printf("%-4d  %-10s  %-8d  %-10d  %-19s  %s\n",
            (int)$r['id'], $limit, (int)$r['session_count'],
            (int)$r['login_attempts'], $last, $r['user']);
    }

    return 0;
}

function cmdUserAdd(mysqli $db, array $pos, array $opts): int
{
    global $def_limit;

    if (!usersTableExists($db)) {
        e("[error] users table does not exist. Запустите web UI с файлом `install`.");
        return 1;
    }

    if (count($pos) < 1) {
        e("Usage: user:add <username> [--password=X] [--limit=N] [--legacy] [--dry-run]");
        return 2;
    }

    /* ── Username validation (same rules as web registration) ── */
    $raw   = (string)$pos[0];
    $login = (string)preg_replace('/[^\p{L}\p{N}_]+/u', '', $raw);

    if ($login !== $raw || $login === '') {
        e("[error] username may contain only letters (unicode), digits, and underscores");
        return 2;
    }
    if (mb_strlen($login) < 1 || mb_strlen($login) > 32) {
        e("[error] username must be 1-32 characters long");
        return 2;
    }

    if (findUser($db, $login) !== null) {
        e("[error] user '$login' already exists");
        return 1;
    }

    /* ── Password ── */
    $password = promptPassword($opts);
    if ($password === null) {
        e("[error] empty or mismatched password");
        return 1;
    }
    if (mb_strlen($password) < 5) {
        e("[error] password must be at least 5 characters long");
        return 1;
    }

    /* ── Session limit ── */
    $limit = isset($opts['limit'])
        ? (int)$opts['limit']
        : (int)($def_limit ?? 100);

    if ($limit < -1) {
        e("[error] limit must be >= -1 (use -1 for unlimited)");
        return 2;
    }

    $legacy = isset($opts['legacy']);
    $hash   = hashPassword($password);

    if (isset($opts['dry-run'])) {
        o(sprintf(
            "[dry-run] would create user '%s' (limit=%d, legacy=%s)",
            $login,
            $limit,
            $legacy ? 'yes' : 'no'
        ));
        return 0;
    }

    /* ── Insert user ── */
    $stmt = $db->prepare("INSERT INTO users (user, pass, s) VALUES (?, ?, ?)");
    if (!$stmt) {
        e("[error] prepare failed: " . $db->error);
        return 1;
    }

    $stmt->bind_param('ssi', $login, $hash, $limit);
    if (!$stmt->execute()) {
        e("[error] insert failed: " . $db->error);
        $stmt->close();
        return 1;
    }
    $newId = (int)$db->insert_id;
    $stmt->close();

    if ($newId <= 0) {
        e("[error] insert succeeded but insert_id is empty");
        return 1;
    }

    /* ── Seed PIDs ── */
    $pids = seed_default_pids($db, $newId, $legacy);

    o(sprintf(
        "✔ User '%s' created (id=%d, limit=%d, pids=%d%s)",
        $login,
        $newId,
        $limit,
        $pids,
        $legacy ? ', legacy included' : ''
    ));

    return 0;
}

function cmdUserDelete(mysqli $db, array $pos, array $opts): int
{
    global $admin;

    if (!usersTableExists($db)) {
        e("[error] users table does not exist");
        return 1;
    }

    if (count($pos) < 1) {
        e("Usage: user:delete <username> [--force] [--dry-run]");
        return 2;
    }

    $username = (string)$pos[0];

    /* ── Protect admin ── */
    if ($admin !== null && $username === (string)$admin) {
        e("[error] admin user '$username' cannot be deleted");
        return 1;
    }

    $user = findUser($db, $username);
    if (!$user) {
        e("[error] user '$username' not found");
        return 1;
    }
    $uid = (int)$user['id'];

    /* ── Inventory of what will be deleted ── */
    $stats = [
        'sessions' => countByUserId($db, 'sessions', $uid),
        'logs'     => countByUserId($db, 'logs', $uid),
        'pids'     => countByUserId($db, 'pids', $uid),
    ];

    o(sprintf("User:     %s (id=%d)", $username, $uid));
    o(sprintf("Sessions: %d", $stats['sessions']));
    o(sprintf("Logs:     %d", $stats['logs']));
    o(sprintf("PIDs:     %d", $stats['pids']));
    o("");

    if (isset($opts['dry-run'])) {
        o("[dry-run] would delete the user and ALL related data");
        return 0;
    }

    /* ── Confirmation (interactive) or --force ── */
    if (!isset($opts['force'])) {
        if (!isInteractive()) {
            e("Non-interactive session: pass --force to proceed.");
            e("Reason: this will delete user '$username' and ALL related data permanently.");
            return 1;
        }
        $question = sprintf(
            "Delete user '%s' (id=%d) and ALL related data permanently?",
            $username,
            $uid
        );
        if (!confirm($question, false)) {
            e("Aborted.");
            return 1;
        }
    }

    /* ── Atomic delete ── */
    $db->begin_transaction();

    try {
        $deletedLogs     = deleteByUserId($db, 'logs',     $uid);
        $deletedSessions = deleteByUserId($db, 'sessions', $uid);
        $deletedPids     = deleteByUserId($db, 'pids',     $uid);

        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException("prepare failed for DELETE FROM users: " . $db->error);
        }
        $stmt->bind_param('i', $uid);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException("DELETE FROM users failed: " . $err);
        }
        $deletedUsers = (int)$stmt->affected_rows;
        $stmt->close();

        if ($deletedUsers === 0) {
            throw new RuntimeException(
                "users row for id=$uid was not deleted (concurrent deletion?)"
            );
        }

        $db->commit();
    } catch (Throwable $ex) {
        $db->rollback();
        e("[error] delete failed, transaction rolled back: " . $ex->getMessage());
        return 1;
    }

    o(sprintf(
        "✔ User '%s' (id=%d) deleted " .
        "[logs=%d, sessions=%d, pids=%d, users=%d]",
        $username,
        $uid,
        $deletedLogs,
        $deletedSessions,
        $deletedPids,
        $deletedUsers
    ));
    o("  Redis caches for this user will expire by their own TTL.");

    return 0;
}

function cmdUserReset(mysqli $db, array $pos, array $opts): int
{
    if (count($pos) < 1) {
        e("Usage: user:reset <username> [--password=X] [--dry-run]");
        return 2;
    }

    $username = $pos[0];
    $user = findUser($db, $username);
    if (!$user) {
        e("[error] user '$username' not found");
        return 1;
    }
    $uid = (int)$user['id'];

    $password = promptPassword($opts);
    if ($password === null) {
        e("[error] empty or mismatched password");
        return 1;
    }
    $hash = hashPassword($password);

    if (isset($opts['dry-run'])) {
        o("[dry-run] would update uid=$uid ('$username')");
        return 0;
    }

    $stmt = $db->prepare(
        "UPDATE users SET pass = ?, login_attempts = 0, last_attempt = NOW() WHERE id = ?"
    );
    $stmt->bind_param('si', $hash, $uid);
    if (!$stmt->execute()) {
        e("[error] update failed: " . $db->error);
        return 1;
    }
    $stmt->close();

    o("✔ Password updated for '$username' (id=$uid).");
    return 0;
}

function cmdUserLimit(mysqli $db, array $pos, array $opts): int
{
    if (count($pos) < 2) {
        e("Usage: user:limit <username> <N>    (N=-1 unlimited, 0 = disabled)");
        return 2;
    }

    [$username, $nStr] = $pos;

    if (!preg_match('/^-?\d+$/', (string)$nStr)) {
        e("Invalid limit: $nStr (must be integer, use -1 for unlimited)");
        return 2;
    }

    $n = (int)$nStr;
    $user = findUser($db, $username);
    if (!$user) {
        e("[error] user '$username' not found");
        return 1;
    }
    $uid = (int)$user['id'];

    if (isset($opts['dry-run'])) {
        o("[dry-run] would set s=$n for '$username' (uid=$uid)");
        return 0;
    }

    $stmt = $db->prepare("UPDATE users SET s = ? WHERE id = ?");
    $stmt->bind_param('ii', $n, $uid);
    $stmt->execute();
    $stmt->close();

    $desc = ($n === -1) ? 'unlimited' : (($n === 0) ? 'DISABLED' : (string)$n);
    o("✔ Limit for '$username' set to $n ($desc)");

    return 0;
}

function cmdUserToggle(mysqli $db, array $pos, array $opts, bool $enable): int
{
    if (count($pos) < 1) {
        e("Usage: " . ($enable ? 'user:enable' : 'user:disable') . " <username> "
           . ($enable ? '[--limit=N]' : ''));
        return 2;
    }

    $username = $pos[0];
    $user = findUser($db, $username);
    if (!$user) {
        e("[error] user '$username' not found");
        return 1;
    }
    $uid = (int)$user['id'];

    if ($enable) {
        global $def_limit;
        $n = isset($opts['limit']) ? (int)$opts['limit'] : (int)($def_limit ?? 100);
        if ($n === 0) {
            $n = 100;
        }
    } else {
        $n = 0;
    }

    if (isset($opts['dry-run'])) {
        o("[dry-run] would set s=$n for '$username' (uid=$uid)");
        return 0;
    }

    $stmt = $db->prepare("UPDATE users SET s = ? WHERE id = ?");
    $stmt->bind_param('ii', $n, $uid);
    $stmt->execute();
    $stmt->close();

    o($enable
        ? "✔ User '$username' enabled (limit=$n)"
        : "✔ User '$username' disabled");

    return 0;
}

/* ─────────── Maintenance ─────────── */

function cmdMaintenance(array $pos, array $opts, bool $on): int
{
    $file = maintenanceFilePath();

    if ($on) {
        if (file_exists($file) && !isset($opts['force'])) {
            o("Maintenance mode is already ON.");
            return 0;
        }
        if (!requireForce(
            $opts,
            "Turn ON maintenance mode? Users (non-admin) will see the maintenance page."
        )) {
            e("Aborted.");
            return 1;
        }
        if (!file_exists($file)) {
            if (!@touch($file)) {
                e("[error] cannot create " . $file);
                return 1;
            }
        }
        o("✔ Maintenance mode: ON");
        return 0;
    }

    if (!file_exists($file)) {
        o("Maintenance mode is already OFF.");
        return 0;
    }
    if (!requireForce($opts, "Turn OFF maintenance mode?")) {
        e("Aborted.");
        return 1;
    }
    if (!@unlink($file)) {
        e("[error] cannot remove " . $file);
        return 1;
    }

    o("✔ Maintenance mode: OFF");
    return 0;
}

function cmdMaintenanceStatus(): int
{
    $file = maintenanceFilePath();
    o((file_exists($file) ? "ON" : "OFF") . " (file: " . $file . ")");
    return 0;
}

/* ════════════════════════════════════════════════════════════
 * USAGE
 * ════════════════════════════════════════════════════════════ */

function printUsage(): void
{
    fwrite(STDOUT, <<<TXT
RedBox Telemetry CLI

USAGE
    php cli.php <command> [args...] [--options]

ADMIN
    admin:reset                        Reset admin password (from \$admin in creds.php)
        --password=X                   Non-interactive password (visible in ps)
        --dry-run                      Show plan, don't touch DB

USERS
    user:list                          List all users
        --json                         Output as JSON

    user:add <username>                Create a new user and seed default PIDs
        --password=X                   Non-interactive password (visible in ps)
        --limit=N                      Session limit (default: \$def_limit; -1 = unlimited)
        --legacy                       Include the legacy Torque PID set
        --dry-run                      Show plan, don't touch DB

    user:delete <username>             Delete user and ALL related data (logs/sessions/pids)
        --force                        Skip confirmation (for non-interactive use)
        --dry-run                      Show what would be deleted

    user:reset <username>              Reset a user's password
        --password=X
        --dry-run

    user:limit <username> <N>          Set session limit (-1 = unlimited, 0 = disabled)
        --dry-run

    user:disable <username>            Set s=0 (login blocked)
        --dry-run

    user:enable <username>             Restore s to default or --limit=N
        --limit=N
        --dry-run

ORPHANS
    orphans:scan                       Find orphan sessions/logs, print stats (read-only)
    orphans:recover                    Create sessions for orphan logs
    orphans:clean                      Delete orphan sessions and/or orphan logs (batched)

    Common options:
        --user=USERNAME                Only consider this user (faster, uses indexes)
        --json                         Output scan result as JSON

    orphans:recover only:
        --id=VALUE                     sessions.id for new rows (default: '-', max 32 chars)
        --profile=VALUE                sessions.profileName for new rows
                                       (default: 'Orphan sessions', max 128 chars)
        --dry-run                      Show plan, don't touch DB
        --force                        Skip confirmation

    orphans:clean only:
        --batch-size=N                 Rows per DELETE statement (default: 1000)
                                       Range: 1..1,000,000
        --batch-pause=MS               Pause between batches, milliseconds (default: 0)
                                       Range: 0..3,600,000
        --dry-run                      Show plan, don't touch DB
        --force                        Skip confirmation

    Definitions:
        Sessions without logs — row in `sessions` with no rows in `logs`
                                for the same (user_id, session).
                                Only orphans:clean can remove these.
        Logs without sessions — rows in `logs` with no matching row in
                                `sessions` for the same (user_id, session).
                                orphans:recover creates the missing session row.
                                orphans:clean deletes these log rows.

    Race-safety:
        All operations re-evaluate the anti-join at execution time.
        If new data appears between scan and action, it is not touched.
        orphans:recover uses INSERT IGNORE, so a session that appeared
        concurrently is left intact (its metadata is not overwritten).
        orphans:clean re-evaluates the anti-join per batch: a session
        that loses its only log between two batches will be caught by
        the next sessions batch (delete order: logs first, sessions next).

    Batching (orphans:clean):
        MySQL/MariaDB do not support `LIMIT` on multi-table DELETE,
        so each batch is a single-table DELETE driven by a materialized
        derived table:
            DELETE FROM t WHERE <pk> IN (
                SELECT <pk> FROM ( SELECT ... LIMIT N ) AS batch
            )
        Between batches, InnoDB is given time to catch up on purge,
        binlog, and replica apply. Row-locks are held for one batch
        only, so cron/backup/monitor statements are not blocked for
        the whole run. If the process is killed mid-run, re-running
        the command is safe: the anti-join is re-evaluated from
        scratch and no double-delete is possible.

MAINTENANCE
    maintenance:on                     Enable maintenance mode
    maintenance:off                    Disable maintenance mode
    maintenance:status                 Show current state

DEBUG
    --debug                            Print host/port/user/db before connecting,
                                       and full exception trace on errors.

EXIT CODES
    0 success, 1 runtime error, 2 usage error

TXT);
}

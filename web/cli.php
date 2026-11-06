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
 * TOP-LEVEL creds.php
 * ──────────────────────────────────────────────────────────── */
$credsPath = __DIR__ . '/src/creds.php';
if (!file_exists($credsPath)) {
    fwrite(STDERR, "[error] src/creds.php not found at: $credsPath\n");
    exit(1);
}
try {
    require_once $credsPath;
} catch (Throwable $ex) {
    fwrite(STDERR, "[error] Failed to load src/creds.php: " . $ex->getMessage() . "\n");
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
        if ($debug) e($ex->getTraceAsString());
        $exit = 1;
    }

    $db->close();
    return $exit;
}

function dispatch(mysqli $db, string $cmd, array $args): int
{
    [$pos, $opts] = parseArgs($args, ['password', 'limit']);

    switch ($cmd) {
        case 'admin:reset':        return cmdAdminReset($db, $pos, $opts);
        case 'user:list':          return cmdUserList($db, $pos, $opts);
        case 'user:reset':         return cmdUserReset($db, $pos, $opts);
        case 'user:limit':         return cmdUserLimit($db, $pos, $opts);
        case 'user:disable':       return cmdUserToggle($db, $pos, $opts, false);
        case 'user:enable':        return cmdUserToggle($db, $pos, $opts, true);
        case 'maintenance:on':     return cmdMaintenance($pos, $opts, true);
        case 'maintenance:off':    return cmdMaintenance($pos, $opts, false);
        case 'maintenance:status': return cmdMaintenanceStatus();
        default:
            fwrite(STDERR, "Unknown command: $cmd\n\n");
            printUsage();
            return 2;
    }
}

/* ════════════════════════════════════════════════════════════
 * HELPERS
 * ════════════════════════════════════════════════════════════ */

function o(string $s = ''): void { fwrite(STDOUT, $s . "\n"); }
function e(string $s): void      { fwrite(STDERR, $s . "\n"); }

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
        $db = new mysqli($host, (string)($db_user ?? ''), (string)($db_pass ?? ''), (string)($db_name ?? ''), $port);
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
    if (function_exists('stream_isatty'))  return @stream_isatty(STDIN);
    if (function_exists('posix_isatty'))   return @posix_isatty(STDIN);
    return true;
}

function confirm(string $msg, bool $defaultYes = false): bool
{
    if (!isInteractive()) return false;
    $suffix = $defaultYes ? ' [Y/n] ' : ' [y/N] ';
    fwrite(STDOUT, $msg . $suffix);
    $line = trim((string)fgets(STDIN));
    if ($line === '') return $defaultYes;
    return strtolower($line[0]) === 'y';
}

function requireForce(array $opts, string $msg): bool
{
    if (isset($opts['force'])) return true;
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
    $pos  = [];
    $opts = [];
    $n    = count($args);

    for ($i = 0; $i < $n; $i++) {
        $a = $args[$i];
        if ($a === '--') {
            for ($j = $i + 1; $j < $n; $j++) $pos[] = $args[$j];
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

function promptPassword(array $opts): ?string
{
    $password = $opts['password'] ?? null;
    if ($password !== null) {
        if (!is_string($password) || $password === '') return null;
        return $password;
    }
    $password = readPassword('New password: ');
    if ($password === '') return null;
    $confirm  = readPassword('Confirm password: ');
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
        $limit = $s === -1 ? 'unlimited' : ($s === 0 ? 'DISABLED' : (string)$s);
        $last  = $r['last_attempt'] ?: '-';
        printf("%-4d  %-10s  %-8d  %-10d  %-19s  %s\n",
            (int)$r['id'], $limit, (int)$r['session_count'],
            (int)$r['login_attempts'], $last, $r['user']);
    }
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
    if (!preg_match('/^-?\d+$/', $nStr)) {
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

    $desc = $n === -1 ? 'unlimited' : ($n === 0 ? 'DISABLED' : (string)$n);
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
        if ($n === 0) $n = 100;
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

    o($enable ? "✔ User '$username' enabled (limit=$n)" : "✔ User '$username' disabled");
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
        if (!requireForce($opts,
            "Turn ON maintenance mode? Users (non-admin) will see the maintenance page.")) {
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

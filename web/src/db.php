<?php
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    die('PHP 8.2+ required, your version: ' . PHP_VERSION . "\n");
}

set_exception_handler(function($exception) {
    $previous = $exception->getPrevious();

    $log = sprintf(
        "=== UNCAUGHT EXCEPTION ===\n" .
        "Message: %s\n" .
        "Code: %d\n" .
        "File: %s\n" .
        "Line: %d\n" .
        "Trace:\n%s\n",
        $exception->getMessage(),
        $exception->getCode(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    if ($previous) {
        $log .= sprintf(
            "\nPrevious exception:\n" .
            "Message: %s\n" .
            "File: %s\n" .
            "Line: %d\n",
            $previous->getMessage(),
            $previous->getFile(),
            $previous->getLine()
        );
    }

    $log .= sprintf(
        "\nRequest info:\n" .
        "URI: %s\n" .
        "Method: %s\n" .
        "IP: %s\n",
        $_SERVER['REQUEST_URI'] ?? 'unknown',
        $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    );

    error_log($log);

    // Сессия может быть не активна (API-эндпоинты) — не вызываем destroy()
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_destroy();
    }

    // Для CLI — только лог
    if (PHP_SAPI === 'cli') {
        exit(1);
    }

    // Определяем API-эндпоинт по имени исполняемого скрипта,
    // а не по REQUEST_URI — иначе /upload (nginx try_files → ul.php)
    // не детектился бы как API.
    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    $is_api = in_array($script, [
        'stream_json.php',
        'ul.php',
        'remote.php',
        'get_token.php',
        'del_session.php',
        'del_sessions.php',
        'merge_sessions.php',
    ], true)
    || (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');

    if ($is_api) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        exit;
    }

    // Обычные страницы — редирект, но только если headers не отправлены
    if (!headers_sent()) {
        header('Location: /catch?c=error');
    }
    exit;
});

$required_extensions = ['mysqli'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        die("php-$ext extension required");
    }
}

require_once __DIR__ . '/creds.php';
require_once __DIR__ . '/version.php';

if (isset($_GET['logout'])) {
    logout_user();
}

if (PHP_SAPI !== 'cli'
    && is_maintenance()
    && !isset($_SESSION['admin'])
) {
    header("Refresh:0; url=/maintenance");
    exit;
}

// Cache layer — Memcached-совместимая обёртка над Redis.
// Переиспользует то же соединение, что streams и heavy-tasks.
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/redis.php';

$memcached = new RatelCache(get_redis_connection());
$memcached_connected = $memcached->isConnected();

$db = get_db_connection();

function quote_name($name) {
    return "`" . str_replace("`", "``", $name) . "`";
}

function quote_names($column_names) {
    return implode(", ", array_map('quote_name', $column_names));
}

function quote_value($value) {
    global $db;
    return "'" . $db->real_escape_string($value) . "'";
}

function search($value) {
    global $db;
    return "'%" . $db->real_escape_string($value) . "%'";
}

function quote_values($values) {
    return implode(", ", array_map('quote_value', $values));
}

/**
 * Единый источник правды: какой uid использовать для per-user кэша.
 *
 * Приоритет:
 *   1) $GLOBALS['user_id'] — используется в share.php / plot.php / worker.php,
 *      где контекст принадлежит не залогиненному, а целевому юзеру.
 *   2) $_SESSION['uid']    — обычный залогиненный пользователь.
 *   3) 0                    — гость / аноним.
 *
 * ВАЖНО: и cache_var_key(), и cache_flush() должны использовать эту функцию,
 * иначе они будут инвалидировать разные пространства ключей.
 */
function cache_current_uid(): int
{
    return (int)($GLOBALS['user_id'] ?? $_SESSION['uid'] ?? 0);
}

/**
 * Ключ для переменных per-user кэшей (session_data_*, gps_data_*).
 *
 * Формат: u{$uid}_vv{$version}_{$suffix}
 *
 * Версия читается из Redis один раз на запрос и кэшируется в
 * $GLOBALS['__ratel_varver']. При cache_flush() версия инкрементируется
 * в Redis, а локальный кэш сбрасывается → следующее чтение подхватит
 * новую версию. Старые ключи становятся недостижимыми и истекают
 * по TTL сами.
 *
 * Если uid невозможно определить — возвращает "guest_{$suffix}".
 */
function cache_var_key(string $suffix): string
{
    global $memcached, $memcached_connected;

    $uid = cache_current_uid();
    if ($uid <= 0) {
        return "guest_{$suffix}";
    }

    if (!isset($GLOBALS['__ratel_varver'])) {
        $GLOBALS['__ratel_varver'] = [];
    }

    if (!isset($GLOBALS['__ratel_varver'][$uid])) {
        $GLOBALS['__ratel_varver'][$uid] = 1;
        if ($memcached_connected) {
            try {
                $v = $memcached->get("u{$uid}_varver");
                if ($v !== false && $v !== null) {
                    $GLOBALS['__ratel_varver'][$uid] = (int)$v;
                }
            } catch (Throwable $e) {
                error_log("cache_var_key get version failed: " . $e->getMessage());
            }
        }
    }

    return "u{$uid}_vv{$GLOBALS['__ratel_varver'][$uid]}_{$suffix}";
}

function cache_flush($token = null, $keyname = null)
{
    // $user_id в global не нужен: uid резолвится через cache_current_uid()
    // из $GLOBALS['user_id'] / $_SESSION['uid'].
    global $memcached, $memcached_connected, $username;

    if (!$memcached_connected) {
        return;
    }

    try {
        /* ─── Точечная инвалидация по имени ключа ───
         * Используется редко (только для явного сброса одного ключа
         * или группы). Перебирает ключи через getAllKeys — приемлемо,
         * т.к. вызывается не на каждом запросе.
         */
        if ($keyname !== null) {
            // Все текущие вызовы передают точный ключ, а не префикс.
            // Удаляем напрямую — без SCAN/KEYS.
            $memcached->delete($keyname);
            return;
        }

        /* ─── Token-специфичные ключи (не per-user) ─── */
        if ($token !== null) {
            $memcached->delete("user_data_{$token}");
            $memcached->delete("user_api_data_{$token}");
            return;
        }

        /* ─── Полный сброс per-user кэшей ───
         * uid резолвится той же функцией, что и в cache_var_key() —
         * единый источник правды, чтобы cache_flush и cache_var_key
         * никогда не разъехались по разным пространствам ключей.
         */
        $uid = cache_current_uid();

        // 1. Фиксированные ключи — удаляем явно по именам.
        //    Их имена заранее известны.
        $fixed_keys = [
            "profiles_list_{$username}",
            "years_list_{$username}",
            "stream_lock_{$username}",
            "user_settings_{$username}",
            "user_status_{$username}",
            "pids_mapping_{$username}",
            "fav_data_{$username}",
            "stream_conv_{$username}",
            "stream_pids_s_{$username}",
            "stream_pids_d_{$username}",
            "api_conv_{$username}",
            "api_pids_{$username}",
            "worker_user_{$username}",
            "columns_data_pids_{$username}",
        ];

        if ($uid > 0) {
            $fixed_keys[] = "pids_known_{$uid}";
            $fixed_keys[] = "session_count_{$uid}";
            $fixed_keys[] = "share_data_{$uid}";
            $fixed_keys[] = "share_plot_{$uid}";
        }

        foreach (array_unique($fixed_keys) as $key) {
            $memcached->delete($key);
        }

        // 2. Переменные ключи (session_data_*, gps_data_*) — инкремент
        //    версии. Все новые чтения получат новую версию, старые ключи
        //    недостижимы и истекут по TTL сами.
        if ($uid > 0) {
            // ВАЖНО: не используем increment() — php-memcached не создаёт
            // ключ, если его нет (NOT_FOUND → false), и версия не растёт.
            // Делаем get + set вручную.
            $cur = $memcached->get("u{$uid}_varver");
            $cur = is_numeric($cur) ? (int)$cur : 0;
            $memcached->set("u{$uid}_varver", $cur + 1, 0);

            // Сбросить локальный кэш версии: если в этом же запросе
            // после flush кто-то вызовет cache_var_key(), он должен
            // получить НОВУЮ версию из Redis, а не залипшую старую.
            unset($GLOBALS['__ratel_varver'][$uid]);
        }

    } catch (Exception $e) {
        error_log(sprintf(
            "Ratel cache error for user %s: %s (Code: %d)",
            $username ?? '?',
            $e->getMessage(),
            $e->getCode()
        ));
    }
}

function column_exists($db, $table, $column) {
    $table = $db->real_escape_string($table);
    $column = $db->real_escape_string($column);
    $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $db->query($query);
    return $result && $result->num_rows > 0;
}

function index_exists($db, $table, $index) {
    $table = $db->real_escape_string($table);
    $index = $db->real_escape_string($index);
    $query = "SHOW INDEX FROM `$table` WHERE Key_name = '$index'";
    $result = $db->query($query);
    return $result && $result->num_rows > 0;
}

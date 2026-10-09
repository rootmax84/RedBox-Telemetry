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
 *     1.4 Добавляет недостающие параметры (heavy_tasks, worker DLQ и др.)
 *     1.5 Синхронизирует creds.php с creds.php.example:
 *           - удаляет переменные, которых нет в example;
 *           - добавляет переменные из example, которых нет в creds.php;
 *           - сохраняет значения существующих переменных из creds.php
 *             (кроме простых ссылок на другие переменные — они
 *              берутся из example, чтобы не получить undefined).
 *
 *   Phase 2. Подключение к MariaDB с retry (--wait-db=N)
 *
 *   Phase 3. DDL
 *     3.1 CREATE TABLE IF NOT EXISTS: logs, sessions, pids
 *     3.2 ALTER TABLE users: недостающие колонки + drop legacy index
 *         (пропускается, если users ещё нет — clean install)
 *     3.3 ALTER TABLE sessions — расширяющие миграции (заглушка)
 *     3.4 ALTER TABLE logs     — расширяющие миграции (заглушка)
 *     3.5 ALTER TABLE pids     — расширяющие миграции (заглушка)
 *         Все три идемпотентны: ADD COLUMN пропускается по column_exists,
 *         MODIFY COLUMN — только если текущий тип совпал с одним из
 *         ожидаемых "старых" (защита от случайного сужения).
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
 *     src/creds.php.YYYYMMDD_HHMMSS.bak        (shared-tables migration)
 *     src/creds.php.params.YYYYMMDD_HHMMSS.bak (params migration)
 *     src/creds.php.sync.YYYYMMDD_HHMMSS.bak   (example-sync)
 *   - --auto возвращает ненулевой exit code при любой ошибке,
 *     чтобы startup-скрипт мог остановить контейнер.
 *   - --reset + --auto запрещены вместе (safety).
 *   - Все обращения к БД обёрнуты в try/catch для mysqli_sql_exception,
 *     т.к. в PHP 8.1+ mysqli по умолчанию работает в strict-режиме.
 *
 *   - Таблица users жёстко захардкожена как 'users' — глобальная
 *     переменная $db_users из creds.php больше не используется.
 */

if (PHP_SAPI !== 'cli') {
    header('Location: /');
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
 * {login}_pids" (или через префиксы) на общие таблицы
 * "logs / sessions / pids" с колонкой user_id.
 *
 * Критерий «нужна миграция» — ЕДИНСТВЕННЫЙ: наличие строки
 *   $db_table = $username.<что угодно>
 *
 * Если такой строки нет:
 *   - есть $user_id  → «already» (уже мигрирован или синхронизирован);
 *   - нет $user_id   → «no_match» (нестандартный файл, не трогаем).
 *
 * Значения и наличие любых других переменных ($db_log_table,
 * $db_sessions_table, $db_pids_table, их префиксы и т.п.) здесь
 * НЕ проверяются — их подчистит фаза 1.5, синхронизируя файл
 * с creds.php.example.
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

    // ─── Единственный legacy-маркер ───
    // Старый формат: $db_table = $username.$db_log_prefix (или похожее).
    $has_legacy_db_table = (bool)preg_match(
        '/^[ \t]*\$db_table[ \t]*=[ \t]*\$username(?!\w)/m',
        $src
    );

    if (!$has_legacy_db_table) {
        // Либо уже мигрирован, либо файл нестандартный.
        // Маркер «мигрирован» — присутствие $user_id.
        $has_user_id = (bool)preg_match('/^[ \t]*\$user_id\b/m', $src);
        $result['status'] = $has_user_id ? 'already' : 'no_match';
        return $result;
    }

    // ─── Мигрируем старый формат ───
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

    /* T2: $db_table = $username.$db_log_prefix; → новый блок.
     *
     * $db_table в новом блоке больше НЕ пишем — переменной нет
     * в проекте, все запросы используют литерал 'logs'/'sessions'/'pids'.
     * Единственное, что нужно сохранить — $user_id, потому что
     * он используется в runtime-коде приложения.
     *
     * Остальные per-user переменные ($db_log_table, $db_sessions_table,
     * $db_pids_table, префиксы) здесь намеренно не трогаем — их
     * удалит 1.5 через сравнение с creds.php.example. */
    $new_block = <<<'PHP'
// === [migrate.php] shared tables ===
$user_id = $_SESSION['uid'] ?? null;
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

    // Критическое условие: блок $db_table обязан был замениться.
    // Если нет — файл не похож на стандартный creds.php, лучше не трогать.
    if ($src === $orig || empty($result['matched'])) {
        $result['status'] = 'no_match';
        return $result;
    }

    if ($dry_run) {
        $result['status'] = 'updated';
        return $result;
    }

    // Backup
    $backup = $path . '.' . date('Ymd_His') . '.bak';
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
 * Идемпотентно вставляет три блока:
 *   1. Redis / Streams     (если нет $redis_enabled)
 *   2. Heavy tasks         (если нет $redis_heavy_stream_key)
 *   3. Worker retry / DLQ  (если нет $worker_max_attempts)
 *
 * Порядок важен: сначала Redis-блок, потом heavy_tasks (якорится
 * на $redis_stream_maxlen, которая появляется вместе с Redis-блоком),
 * потом DLQ (якорится на $heavy_chunk_pause_us из heavy-блока).
 *
 * Якоря вставки (по убыванию приоритета):
 *   Redis-блок:   после $max_api_requests_per_second; иначе перед $salt; иначе в конец.
 *   Heavy:        после $redis_stream_maxlen;          иначе перед $salt; иначе в конец.
 *   DLQ:          после $heavy_chunk_pause_us;
 *                 иначе после $redis_stream_maxlen;
 *                 иначе перед $salt; иначе в конец.
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
     * 3. Worker retry / DLQ
     *
     * Параметры обработки ошибок в worker.php:
     *   - worker_max_attempts — сколько transient-падений терпим до DLQ;
     *   - redis_dlq_key       — стрим «мёртвых писем» (ratel:dead_letters);
     *   - worker_dlq_maxlen   — MAXLEN для DLQ;
     *   - worker_attempt_ttl  — TTL счётчика попыток на сообщение.
     *
     * Проверка идемпотентности — по $worker_max_attempts.
     * ────────────────────────────────────────────────────── */
    if (strpos($src, '$worker_max_attempts') === false) {
        $dlq_block =
              "\n// --- Worker retry / DLQ (dead letter queue) ---\n"
            . "\$worker_max_attempts  = 3;             // transient-ошибок до DLQ\n"
            . "\$redis_dlq_key        = 'ratel:dead_letters';\n"
            . "\$worker_dlq_maxlen    = 10000;         // ~ MAXLEN для DLQ\n"
            . "\$worker_attempt_ttl   = 86400;         // TTL счётчика попыток, сек\n";

        if (preg_match('/^(\$heavy_chunk_pause_us\s*=\s*[^;]+;.*)$/m', $src, $m)) {
            $src = str_replace($m[0], $m[0] . "\n" . $dlq_block, $src);
            $added[] = 'worker_dlq after $heavy_chunk_pause_us';
        } elseif (preg_match('/^(\$redis_stream_maxlen\s*=\s*[^;]+;.*)$/m', $src, $m)) {
            $src = str_replace($m[0], $m[0] . "\n" . $dlq_block, $src);
            $added[] = 'worker_dlq after $redis_stream_maxlen';
        } elseif (preg_match('/^(\$salt\s*=)/m', $src, $m)) {
            $src = str_replace($m[0], $dlq_block . "\n" . $m[0], $src);
            $added[] = 'worker_dlq before $salt';
        } else {
            $src .= $dlq_block;
            $added[] = 'worker_dlq appended';
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
        $backup = $path . '.params.' . date('Ymd_His') . '.bak';
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
 * Phase 1.5: синхронизация creds.php с creds.php.example
 *
 * creds.php.example — эталон. После sync в creds.php:
 *   - есть все переменные, которые есть в example;
 *   - нет переменных, которых в example нет;
 *   - значения существующих переменных сохранены из creds.php
 *     (кроме случая, когда значение — простая ссылка на другую
 *      переменную: такие значения берутся из example, чтобы
 *      не получить «Undefined variable» после удаления
 *      пер-юзерных переменных вида $db_log_table);
 *   - комментарии, порядок, структура, кастомный runtime-код
 *     (не присваивания верхнего уровня) — берутся из example.
 *
 * Идемпотентно: если наборы переменных уже совпадают —
 * возвращает 'already' и файл не трогает.
 * ══════════════════════════════════════════════════════════ */

/**
 * Находит в PHP-исходнике присваивания верхнего уровня `$var = ...;`.
 *
 * Возвращает массив:
 *   [ ['var' => 'db_host', 'value_start' => 123, 'value_end' => 140], ... ]
 *
 * Игнорирует строки, комментарии //, #, /* ... *\/, вложенные блоки {},
 * операторы ==, ===, =>.
 *
 * Не поддерживает heredoc / nowdoc — в creds.php.example их нет.
 */
function scan_php_assignments(string $src): array
{
    $findings = [];
    $len      = strlen($src);
    $i        = 0;
    $depth    = 0;

    while ($i < $len) {
        $c = $src[$i];

        // // комментарий
        if ($c === '/' && ($src[$i + 1] ?? '') === '/') {
            $nl = strpos($src, "\n", $i);
            if ($nl === false) break;
            $i = $nl + 1;
            continue;
        }
        // # комментарий
        if ($c === '#') {
            $nl = strpos($src, "\n", $i);
            if ($nl === false) break;
            $i = $nl + 1;
            continue;
        }
        // /* ... */ комментарий
        if ($c === '/' && ($src[$i + 1] ?? '') === '*') {
            $end = strpos($src, '*/', $i + 2);
            $i   = $end === false ? $len : $end + 2;
            continue;
        }

        // Строковый литерал
        if ($c === "'" || $c === '"') {
            $i = scan_php_string_end($src, $i);
            continue;
        }

        // Фигурные скобки — тела if/for/while/функций
        if ($c === '{') { $depth++; $i++; continue; }
        if ($c === '}') { $depth = max(0, $depth - 1); $i++; continue; }

        if ($depth === 0 && $c === '$') {
            // Читаем имя переменной
            $name_end = $i + 1;
            while ($name_end < $len
                && (ctype_alnum($src[$name_end]) || $src[$name_end] === '_')) {
                $name_end++;
            }
            if ($name_end === $i + 1) { $i++; continue; }

            $varName = substr($src, $i + 1, $name_end - $i - 1);

            // Пропускаем пробелы до '='
            $p = $name_end;
            while ($p < $len && ctype_space($src[$p])) $p++;

            // Должно быть именно '=', не '==', не '=>'
            if ($p >= $len || $src[$p] !== '='
                || ($src[$p + 1] ?? '') === '='
                || ($src[$p + 1] ?? '') === '>') {
                $i = $name_end;
                continue;
            }

            $p++; // пропускаем '='
            while ($p < $len && ctype_space($src[$p])) $p++;

            $value_start = $p;

            // Читаем выражение до ';' с учётом вложенных [], ()
            $subDepth = 0;
            while ($p < $len) {
                $vc = $src[$p];
                if ($vc === "'" || $vc === '"') { $p = scan_php_string_end($src, $p); continue; }
                if ($vc === '[' || $vc === '(') { $subDepth++; $p++; continue; }
                if ($vc === ']' || $vc === ')') { $subDepth = max(0, $subDepth - 1); $p++; continue; }
                if ($vc === ';' && $subDepth === 0) break;
                $p++;
            }
            if ($p >= $len) { $i = $name_end; continue; }

            $value_end = $p;
            while ($value_end > $value_start && ctype_space($src[$value_end - 1])) {
                $value_end--;
            }

            $findings[] = [
                'var'         => $varName,
                'value_start' => $value_start,
                'value_end'   => $value_end,
            ];

            $i = $p + 1;
            continue;
        }

        $i++;
    }

    return $findings;
}

/**
 * Позиция сразу после закрывающей кавычки строкового литерала.
 * $i указывает на открывающую кавычку.
 */
function scan_php_string_end(string $src, int $i): int
{
    $len   = strlen($src);
    $quote = $src[$i];
    $i++;
    while ($i < $len) {
        if ($src[$i] === '\\') { $i += 2; continue; }
        if ($src[$i] === $quote) { return $i + 1; }
        $i++;
    }
    return $len;
}

/**
 * Строит новый исходник creds.php: берёт example как шаблон и
 * подменяет значения указанных переменных.
 *
 * $values_from_creds: [varname => raw_value_expression]
 */
function build_synced_creds(string $example_src, array $values_from_creds): string
{
    if (empty($values_from_creds)) {
        return $example_src;
    }

    $findings = scan_php_assignments($example_src);

    // Заменяем справа налево, чтобы не сбить offsets
    usort($findings, fn($a, $b) => $b['value_start'] <=> $a['value_start']);

    foreach ($findings as $f) {
        if (!isset($values_from_creds[$f['var']])) continue;
        $new_value   = $values_from_creds[$f['var']];
        $example_src = substr($example_src, 0, $f['value_start'])
                     . $new_value
                     . substr($example_src, $f['value_end']);
    }

    return $example_src;
}

/**
 * @return array{
 *   status: 'updated'|'already'|'error',
 *   backup: ?string,
 *   added: string[],
 *   removed: string[],
 *   kept: string[]
 * }
 */
function sync_creds_with_example(
    string $creds_path,
    string $example_path,
    bool   $dry_run,
    bool   $no_backup
): array {
    $result = [
        'status'  => 'error',
        'backup'  => null,
        'added'   => [],
        'removed' => [],
        'kept'    => [],
    ];

    if (!file_exists($creds_path)) {
        err("[creds-sync] not found: {$creds_path}");
        return $result;
    }
    if (!file_exists($example_path)) {
        err("[creds-sync] example not found: {$example_path}");
        return $result;
    }

    $creds_src   = file_get_contents($creds_path);
    $example_src = file_get_contents($example_path);
    if ($creds_src === false || $example_src === false) {
        err("[creds-sync] cannot read files");
        return $result;
    }

    // Значения из creds.php
    $creds_vars = [];
    foreach (scan_php_assignments($creds_src) as $f) {
        $creds_vars[$f['var']] = substr(
            $creds_src, $f['value_start'], $f['value_end'] - $f['value_start']
        );
    }

    // Набор переменных из example
    $example_vars = [];
    foreach (scan_php_assignments($example_src) as $f) {
        $example_vars[$f['var']] = true;
    }

    $added   = array_diff_key($example_vars, $creds_vars);
    $removed = array_diff_key($creds_vars, $example_vars);
    $kept    = array_intersect_key($creds_vars, $example_vars);

    $result['added']   = array_keys($added);
    $result['removed'] = array_keys($removed);
    $result['kept']    = array_keys($kept);

    if (empty($added) && empty($removed)) {
        $result['status'] = 'already';
        return $result;
    }

    if ($dry_run) {
        $result['status'] = 'updated';
        return $result;
    }

    if (!$no_backup) {
        $backup = $creds_path . '.sync.' . date('Ymd_His') . '.bak';
        if (!@copy($creds_path, $backup)) {
            err("[creds-sync] backup failed: {$backup}");
            return $result;
        }
        $result['backup'] = $backup;
    }

    // Значения для подстановки: только те, что есть в example.
    // Простые ссылки ($var) не сохраняем — иначе после удаления
    // целевой переменной получим undefined.
    $keep_values = [];
    foreach ($kept as $var => $value) {
        if (preg_match('/^\$[a-zA-Z_]\w*$/', $value)) {
            continue;
        }
        $keep_values[$var] = $value;
    }

    $new_src = build_synced_creds($example_src, $keep_values);

    if (@file_put_contents($creds_path, $new_src) === false) {
        err("[creds-sync] write failed: {$creds_path}");
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

/**
 * Текущий тип колонки из information_schema (в нижнем регистре),
 * либо null, если колонки нет.
 *
 * Используется для "расширяющих" миграций: реагируем только если
 * тип колонки в точности совпал с одним из ожидаемых "старых",
 * чтобы случайно не сузить колонку, которую уже расширили руками.
 */
function column_type(mysqli $db, string $table, string $column): ?string
{
    $stmt = $db->prepare(
        "SELECT COLUMN_TYPE FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name = ?
            AND column_name = ?"
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? strtolower((string)$row['COLUMN_TYPE']) : null;
}

/* ══════════════════════════════════════════════════════════
 * Phase 3.2: ALTER users — дотянуть схему users до актуальной.
 *
 * Таблица жёстко захардкожена как 'users' — переменная $db_users
 * в проекте больше не используется.
 *
 * Пропускается, если таблицы users нет (clean install —
 * её создаст auth_functions.php::create_users_table() при первом заходе).
 * ══════════════════════════════════════════════════════════ */

function migrate_users_table(mysqli $db, bool $dry_run): void
{
    $users_table = 'users';

    if (!table_exists($db, $users_table)) {
        out("[users] table not found — clean install, skipping column migration");
        return;
    }

    $migrations = [
        'stream_lock'     => "ALTER TABLE `$users_table` ADD COLUMN stream_lock TINYINT(1) NOT NULL DEFAULT 0",
        'sessions_filter' => "ALTER TABLE `$users_table` ADD COLUMN sessions_filter TINYINT(1) NOT NULL DEFAULT 1",
        'share_secret'    => "ALTER TABLE `$users_table` ADD COLUMN share_secret CHAR(32)",
        'login_attempts'  => "ALTER TABLE `$users_table` ADD COLUMN login_attempts TINYINT UNSIGNED DEFAULT 0",
        'last_attempt'    => "ALTER TABLE `$users_table` ADD COLUMN last_attempt DATETIME",
        'api_gps'         => "ALTER TABLE `$users_table` ADD COLUMN api_gps TINYINT(1) NOT NULL DEFAULT 0",
        'lang'            => "ALTER TABLE `$users_table` ADD COLUMN lang ENUM('en','ru','es','de') NOT NULL DEFAULT 'en' AFTER gap",
        'mcu_data'        => "ALTER TABLE `$users_table` ADD COLUMN mcu_data VARCHAR(2048) NULL AFTER sessions_filter",
    ];

    $added = 0;
    foreach ($migrations as $col => $sql) {
        if (column_exists($db, $users_table, $col)) {
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
    if (index_exists($db, $users_table, 'indexes')) {
        if ($dry_run) {
            out("[users] [dry] would DROP INDEX `indexes`");
        } else {
            try {
                $db->query("DROP INDEX `indexes` ON `$users_table`");
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
 * Универсальный раннер ADD/MODIFY для одной таблицы
 *
 * @param array  $add_columns    ['col' => 'ALTER TABLE ... ADD COLUMN ...']
 * @param array  $widen_columns  ['col' => [
 *                                  'from' => ['char(15)'],       // только эти типы триггерят ALTER
 *                                  'to'   => 'varchar(45)',      // для лога
 *                                  'sql'  => 'ALTER TABLE ... MODIFY COLUMN ...',
 *                               ]]
 *
 * Идемпотентно: ADD — по column_exists(), MODIFY — по совпадению
 * текущего типа с одной из строк списка 'from'.
 * ══════════════════════════════════════════════════════════ */

function migrate_table_columns(
    mysqli $db,
    string $table,
    array  $add_columns,
    array  $widen_columns,
    bool   $dry_run,
    string $label
): void {
    if (!table_exists($db, $table)) {
        out("[{$label}] table not found — skipping");
        return;
    }

    $applied = 0;

    // ── ADD COLUMN ──
    foreach ($add_columns as $col => $sql) {
        if (column_exists($db, $table, $col)) {
            continue;
        }
        if ($dry_run) {
            out("[{$label}] [dry] would ADD COLUMN {$col}");
            $applied++;
            continue;
        }
        try {
            $db->query($sql);
            info("[{$label}] added column: {$col}");
            $applied++;
        } catch (mysqli_sql_exception $e) {
            err("[{$label}] ALTER ADD {$col} failed: " . $e->getMessage());
        }
    }

    // ── MODIFY COLUMN (widening) ──
    foreach ($widen_columns as $col => $def) {
        $current = column_type($db, $table, $col);
        if ($current === null) {
            continue;   // колонки нет — это не widening
        }
        $from = array_map('strtolower', (array)($def['from'] ?? []));
        if (empty($from) || !in_array($current, $from, true)) {
            continue;   // уже не тот тип — не трогаем
        }
        $to = (string)($def['to'] ?? '?');

        if ($dry_run) {
            out("[{$label}] [dry] would MODIFY COLUMN {$col} ({$current} → {$to})");
            $applied++;
            continue;
        }
        try {
            $db->query($def['sql']);
            info("[{$label}] widened column: {$col} ({$current} → {$to})");
            $applied++;
        } catch (mysqli_sql_exception $e) {
            err("[{$label}] ALTER MODIFY {$col} failed: " . $e->getMessage());
        }
    }

    if ($applied === 0 && !$dry_run) {
        out("[{$label}] no changes needed");
    }
}

/* ══════════════════════════════════════════════════════════
 * Phase 3.3: ALTER sessions — расширяющие миграции.
 *
 * Заглушка. Живые правила добавляются в $add_columns
 * (для новых колонок) или в $widen_columns (для изменения
 * типа существующих).
 *
 * Примеры (закомментированы, ничего не делают):
 *
 *   $add_columns = [
 *       'user_agent' => "ALTER TABLE sessions
 *                          ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip",
 *       'note'       => "ALTER TABLE sessions
 *                          ADD COLUMN note TEXT NULL",
 *   ];
 *
 *   $widen_columns = [
 *       // ip CHAR(15) → VARCHAR(45): хватит на IPv6 и на результат
 *       // нормализации X-Forwarded-For. Безопасно расширяет,
 *       // существующие значения не трогает.
 *       'ip' => [
 *           'from' => ['char(15)'],
 *           'to'   => 'varchar(45)',
 *           'sql'  => "ALTER TABLE sessions
 *                        MODIFY COLUMN ip VARCHAR(45) NOT NULL DEFAULT '0.0.0.0'",
 *       ],
 *
 *       // profileName VARCHAR(128) → VARCHAR(255): длинные профили
 *       // Torque. 'from' перечисляет оба возможных "старых" типа,
 *       // чтобы миграция сработала независимо от того, что стоит
 *       // сейчас — CHAR или VARCHAR с коротким размером.
 *       // 'profileName' => [
 *       //     'from' => ['varchar(128)', 'char(128)'],
 *       //     'to'   => 'varchar(255)',
 *       //     'sql'  => "ALTER TABLE sessions
 *       //                  MODIFY COLUMN profileName VARCHAR(255)
 *       //                  NOT NULL DEFAULT 'Not Specified'",
 *       // ],
 *   ];
 *
 * На MariaDB с ROCKSDB ALTER MODIFY перестраивает таблицу —
 * на большой базе первый запуск migrate.php после апдейта
 * может занять заметное время.
 * ══════════════════════════════════════════════════════════ */

function migrate_sessions_table(mysqli $db, bool $dry_run): void
{
    $add_columns = [
        // 'user_agent' => "ALTER TABLE sessions ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip",
    ];

    $widen_columns = [
        // 'ip' => [
        //     'from' => ['char(15)'],
        //     'to'   => 'varchar(45)',
        //     'sql'  => "ALTER TABLE sessions MODIFY COLUMN ip VARCHAR(45) NOT NULL DEFAULT '0.0.0.0'",
        // ],
        // 'profileName' => [
        //     'from' => ['varchar(128)', 'char(128)'],
        //     'to'   => 'varchar(255)',
        //     'sql'  => "ALTER TABLE sessions MODIFY COLUMN profileName VARCHAR(255) NOT NULL DEFAULT 'Not Specified'",
        // ],
    ];

    migrate_table_columns($db, 'sessions', $add_columns, $widen_columns, $dry_run, 'sessions');
}

/* ══════════════════════════════════════════════════════════
 * Phase 3.4: ALTER logs — расширяющие миграции.
 *
 * Заглушка. Примеры (закомментированы, ничего не делают):
 *
 *   $add_columns = [
 *       'source' => "ALTER TABLE logs ADD COLUMN source VARCHAR(32) NULL",
 *   ];
 *
 *   $widen_columns = [
 *       // data TEXT → LONGTEXT: если кто-то изначально создал
 *       // таблицу с TEXT (64 KB), большие JSON с десятками PID
 *       // не влезали и падали с 1406.
 *       // 'data' => [
 *       //     'from' => ['text', 'mediumtext'],
 *       //     'to'   => 'longtext',
 *       //     'sql'  => "ALTER TABLE logs MODIFY COLUMN data LONGTEXT NOT NULL",
 *       // ],
 *   ];
 * ══════════════════════════════════════════════════════════ */

function migrate_logs_table(mysqli $db, bool $dry_run): void
{
    $add_columns = [
        // 'source' => "ALTER TABLE logs ADD COLUMN source VARCHAR(32) NULL",
    ];

    $widen_columns = [
        // 'data' => [
        //     'from' => ['text', 'mediumtext'],
        //     'to'   => 'longtext',
        //     'sql'  => "ALTER TABLE logs MODIFY COLUMN data LONGTEXT NOT NULL",
        // ],
    ];

    migrate_table_columns($db, 'logs', $add_columns, $widen_columns, $dry_run, 'logs');
}

/* ══════════════════════════════════════════════════════════
 * Phase 3.5: ALTER pids — расширяющие миграции.
 *
 * Заглушка. Примеры (закомментированы, ничего не делают):
 *
 *   $add_columns = [
 *       'min_value' => "ALTER TABLE pids ADD COLUMN min_value DECIMAL(10,3) NULL",
 *       'max_value' => "ALTER TABLE pids ADD COLUMN max_value DECIMAL(10,3) NULL",
 *   ];
 *
 *   $widen_columns = [
 *       // description VARCHAR(255) → VARCHAR(512): длинные
 *       // описания новых PID'ов, которых нет в дефолтном наборе.
 *       // 'description' => [
 *       //     'from' => ['varchar(255)'],
 *       //     'to'   => 'varchar(512)',
 *       //     'sql'  => "ALTER TABLE pids MODIFY COLUMN description VARCHAR(512) DEFAULT NULL",
 *       // ],
 *       //
 *       // id VARCHAR(16) → VARCHAR(32): если появятся PID'ы
 *       // с более длинным hex-кодом, чем текущие kff120c.
 *       // 'id' => [
 *       //     'from' => ['varchar(16)'],
 *       //     'to'   => 'varchar(32)',
 *       //     'sql'  => "ALTER TABLE pids MODIFY COLUMN id VARCHAR(32) NOT NULL",
 *       // ],
 *   ];
 * ══════════════════════════════════════════════════════════ */

function migrate_pids_table(mysqli $db, bool $dry_run): void
{
    $add_columns = [
        // 'min_value' => "ALTER TABLE pids ADD COLUMN min_value DECIMAL(10,3) NULL",
        // 'max_value' => "ALTER TABLE pids ADD COLUMN max_value DECIMAL(10,3) NULL",
    ];

    $widen_columns = [
        // 'description' => [
        //     'from' => ['varchar(255)'],
        //     'to'   => 'varchar(512)',
        //     'sql'  => "ALTER TABLE pids MODIFY COLUMN description VARCHAR(512) DEFAULT NULL",
        // ],
        // 'id' => [
        //     'from' => ['varchar(16)'],
        //     'to'   => 'varchar(32)',
        //     'sql'  => "ALTER TABLE pids MODIFY COLUMN id VARCHAR(32) NOT NULL",
        // ],
    ];

    migrate_table_columns($db, 'pids', $add_columns, $widen_columns, $dry_run, 'pids');
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
        err("[creds] file does not look like a standard creds.php;");
        err("[creds] check it manually or start from creds.php.example");
        if (!$DRY_RUN) {
            err("[creds] ABORT — database untouched");
            exit(2);
        }
        break;

    case 'error':
    default:
        if (!$DRY_RUN) {
            err("[creds] ABORT — database untouched");
            exit(2);
        }
}

// 1.4 Добавить недостающие параметры (heavy_tasks, worker DLQ и др.)
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

// 1.5 Синхронизация creds.php с creds.php.example (эталон).
// Выполняется последней: подчищает «хвосты» 1.3 и 1.4,
// приводит структуру к актуальной и удаляет мёртвые переменные
// (например, $db_log_table / $db_sessions_table / $db_pids_table,
// а также $db_users / $db_table, если их нет в example).
if (file_exists($creds_example_path)) {
    $sync_result = sync_creds_with_example(
        $creds_path,
        $creds_example_path,
        $DRY_RUN,
        $NO_BACKUP
    );

    switch ($sync_result['status']) {
        case 'already':
            out("[creds-sync] already in sync with example");
            break;

        case 'updated':
            if (!empty($sync_result['added'])) {
                out("[creds-sync] add: " . implode(', ', $sync_result['added']));
            }
            if (!empty($sync_result['removed'])) {
                out("[creds-sync] remove: " . implode(', ', $sync_result['removed']));
            }
            if ($DRY_RUN) {
                out("[creds-sync] [dry] would sync {$creds_path} from {$creds_example_path}");
            } else {
                info("[creds-sync] synced: {$creds_path}");
                if (!empty($sync_result['backup'])) {
                    info("[creds-sync] backup: {$sync_result['backup']}");
                }
            }
            break;

        case 'error':
        default:
            if (!$DRY_RUN) {
                err("[creds-sync] failed — ABORT");
                exit(2);
            }
    }
} else {
    out("[creds-sync] example not found, skipping sync");
}

/* ═══════════════ Phase 2: DB connection ═══════════════ */

phase("Phase 2/4: DB connection");

require_once $creds_path;

global $db_host, $db_user, $db_pass, $db_name, $db_port;

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
migrate_users_table($db, $DRY_RUN);

// 3.3-3.5 Расширяющие миграции для shared-таблиц.
// Сейчас это заглушки — живых правил нет, всё закомментировано
// внутри функций. Когда понадобится — раскомментировать правило
// в нужном массиве ($add_columns или $widen_columns).
migrate_sessions_table($db, $DRY_RUN);
migrate_logs_table($db, $DRY_RUN);
migrate_pids_table($db, $DRY_RUN);

/* ═══════════════ Phase 4: data migration ═══════════════ */

phase("Phase 4/4: data migration");

// Clean install: таблицы users ещё нет (её создаст auth_functions.php
// при первом заходе на логин). Мигрировать нечего.
if (!table_exists($db, 'users')) {
    info("[migrate] users table not found — clean install, data migration skipped");
    info("[migrate] done");
    $db->close();
    exit(0);
}

$users = $db->query(
    "SELECT id, user FROM `users` ORDER BY id ASC"
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

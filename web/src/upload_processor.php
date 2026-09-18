<?php
/**
 * Upload processor — общая логика записи аплоадов в БД.
 *
 * Используется:
 *   - ul.php       (inline fallback, когда Redis недоступен/выключен)
 *   - worker.php   (основной путь: читает из Redis Stream и вызывает processUpload)
 *
 * Вход: нормализованный контекст $ctx (см. ниже) + $kind + $payload.
 * Контекст:
 *   username, db_table, db_sessions_table, db_pids_table,
 *   lang, tg_token, tg_chatid, tg_socks_proxy, translations, ip
 */

require_once __DIR__ . '/helpers.php';

/**
 * Точка входа.
 *
 * @param mysqli $db
 * @param array  $ctx     Контекст пользователя
 * @param string $kind    'bulk' | 'single'
 * @param mixed  $payload Для 'bulk' — массив записей, для 'single' — $_REQUEST-подобный массив
 */
function processUpload($db, array $ctx, string $kind, $payload): void
{
    // helpers.php использует $GLOBALS['username'], $GLOBALS['db_table'] и т.д.
    // внутри cache_flush(), processSessionStartRecord(), insert_*_record().
    $GLOBALS['username']          = $ctx['username'];
    $GLOBALS['db_table']          = $ctx['db_table'];
    $GLOBALS['db_sessions_table'] = $ctx['db_sessions_table'];
    $GLOBALS['db_pids_table']     = $ctx['db_pids_table'];

    $dbfields = get_db_fields($db, $ctx['db_table']);

    switch ($kind) {
        case 'bulk':
            processBulkRecords($db, $ctx, $payload, $dbfields);
            break;

        case 'single':
            processSingleRequest($db, $ctx, $payload, $dbfields);
            break;

        default:
            throw new InvalidArgumentException("Unknown upload kind: $kind");
    }
}

/* ───────────────────────── Вспомогательное ───────────────────────── */

/**
 * Список колонок таблицы логов. Кэшируется в Memcached.
 */
function get_db_fields($db, string $db_table): array
{
    global $memcached, $memcached_connected, $db_memcached_ttl;

    $cache_key = "table_structure_" . $db_table;
    $dbfields  = false;

    if ($memcached_connected) {
        $dbfields = $memcached->get($cache_key);
    }

    if (!is_array($dbfields)) {
        $dbfields = [];
        $result = $db->query("SHOW COLUMNS FROM $db_table");
        if ($result && $result->num_rows) {
            while ($row = $result->fetch_assoc()) {
                $dbfields[] = $row['Field'];
            }
        }
        if ($memcached_connected) {
            try {
                $memcached->set($cache_key, $dbfields, $db_memcached_ttl ?? 3600);
            } catch (Throwable $e) {
                error_log("Memcached error in get_db_fields: " . $e->getMessage());
            }
        }
    }

    return $dbfields;
}

/**
 * Гарантирует, что колонка `kXXXX` существует в таблице логов
 * и что соответствующая запись есть в таблице pids.
 */
function ensureColumnAndPid(
    $db,
    string $db_table,
    string $db_pids_table,
    string $key,
    $value,
    array &$dbfields
): void {
    if (in_array($key, $dbfields, true) || !preg_match('/^k[0-9a-fA-F]+$/', $key)) {
        return;
    }

    $dataType = is_numeric($value) ? "FLOAT" : "VARCHAR(255)";

    if (!column_exists($db, $db_table, $key)) {
        $db->query(
            "ALTER TABLE $db_table ADD COLUMN " . quote_name($key)
            . " $dataType NOT NULL DEFAULT '0'"
        );
    }

    $db->execute_query(
        "INSERT IGNORE INTO $db_pids_table (id, description, populated, stream, favorite)
         VALUES (?,?,?,?,?)",
        [$key, $key, '1', '1', '0']
    );

    $dbfields[] = $key;
    cache_flush();
}

/* ───────────────────────── Bulk JSON ───────────────────────── */

/**
 * Обработка массива записей (application/json от RedManage).
 *
 * Запись с ключом 'profileName' (или начинающимся с 'profile') считается
 * маркером старта сессии.
 */
function processBulkRecords($db, array $ctx, array $records, array $dbfields): void
{
    $db_table          = $ctx['db_table'];
    $db_sessions_table = $ctx['db_sessions_table'];
    $db_pids_table     = $ctx['db_pids_table'];
    $lang              = $ctx['lang'];
    $translations      = $ctx['translations'];

    $db->begin_transaction();
    try {
        $bulkRecords         = [];  // обычные datapoint'ы
        $sessionUpdates      = [];  // UPSERT-апдейты сессий
        $sessionStartRecords = [];  // записи с profileName

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $isSessionStart = isset($record['profileName'])
                || !empty(array_filter(
                    array_keys($record),
                    fn($k) => strpos((string)$k, 'profile') === 0
                ));

            if ($isSessionStart) {
                $sessionStartRecords[] = $record;
                continue;
            }

            $rawkeys      = [];
            $rawvalues    = [];
            $sesskeys     = [];
            $sessvalues   = [];
            $sessuploadid = '';
            $sesstime     = '0';
            $id           = '';

            // ВАЖНО: повторяем поведение оригинала ul.php.
            // Ключ 'id' перехватывается отдельно и НЕ попадает в $sesskeys.
            // Ключи 'session' и 'time' идут и в $sesskeys, и в отдельные переменные.
            foreach ($record as $key => $value) {
                if (in_array($key, ['time', 'session', 'id'], true)) {
                    if ($key === 'session') {
                        $sessuploadid = $value;
                    }
                    if ($key === 'time') {
                        $sesstime = $value;
                    }
                    if ($key === 'id') {
                        $id = $value;
                    } else {
                        $sesskeys[]   = $key;
                        $sessvalues[] = $value;
                    }
                } elseif (preg_match('/^k/', (string)$key)) {
                    $rawkeys[]   = $key;
                    $rawvalues[] = ($value == 'Infinity') ? -1 : $value;
                }
            }

            // Автодобавление колонок/PID'ов
            foreach ($rawkeys as $idx => $key) {
                ensureColumnAndPid(
                    $db, $db_table, $db_pids_table,
                    $key, $rawvalues[$idx], $dbfields
                );
            }

            $allRawKeys   = array_merge($rawkeys, $sesskeys);
            $allRawValues = array_merge($rawvalues, $sessvalues);

            $bulkRecord = [];
            foreach ($allRawKeys as $i => $key) {
                $bulkRecord[$key] = $allRawValues[$i];
            }
            $bulkRecords[] = $bulkRecord;

            $sesskeys[]   = 'timeend';
            $sessvalues[] = $sesstime;

            $sessionUpdates[] = [
                'keys'     => $sesskeys,
                'values'   => $sessvalues,
                'id'       => $id,
                'sesstime' => $sesstime,
            ];
        }

        if (!empty($bulkRecords)) {
            insert_bulk_records($db, $db_table, $bulkRecords);
        }

        foreach ($sessionUpdates as $sess) {
            $sql = "INSERT INTO $db_sessions_table ("
                 . quote_names($sess['keys']) . ") VALUES ("
                 . quote_values($sess['values'])
                 . ") ON DUPLICATE KEY UPDATE id=?, timeend=GREATEST(timeend, ?), sessionsize=sessionsize+1";
            $db->execute_query($sql, [$sess['id'], $sess['sesstime']]);
        }

        foreach ($sessionStartRecords as $record) {
            processSessionStartRecord(
                $db,
                $record,
                $db_sessions_table,
                $lang,
                $ctx['username'],
                $ctx['tg_token']       ?? null,
                $ctx['tg_chatid']      ?? null,
                $ctx['tg_socks_proxy'] ?? '',
                $translations,
                $ctx['ip']             ?? null   // явная передача IP
            );
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/* ───────────────────────── Single request ───────────────────────── */

/**
 * Обработка одиночного запроса (application/x-www-form-urlencoded).
 */
function processSingleRequest($db, array $ctx, array $request, array $dbfields): void
{
    $db_table          = $ctx['db_table'];
    $db_sessions_table = $ctx['db_sessions_table'];
    $db_pids_table     = $ctx['db_pids_table'];
    $lang              = $ctx['lang'];
    $translations      = $ctx['translations'];

    $allowedProfileFields = ['profileName'];

    $keys       = [];   // k-колонки
    $values     = [];
    $sesskeys   = [];
    $sessvalues = [];
    $spv        = [];   // profile values
    $sesstime   = '0';
    $id         = '';
    $submitval  = 0;

    // ВАЖНО: та же логика, что и в bulk — 'id' перехватывается,
    // 'session' и 'time' идут в $sesskeys.
    foreach ($request as $key => $value) {
        if (in_array($key, ['time', 'session', 'id'], true)) {
            if ($key === 'session') {
                // значение не используется дальше, но оставлено для совместимости
            }
            if ($key === 'time') {
                $sesstime = $value;
            }
            if ($key === 'id') {
                $id = $value;
            } else {
                $sesskeys[]   = $key;
                $sessvalues[] = $value;
            }
            $submitval = 1;
        } elseif (preg_match('/^k/', (string)$key)) {
            $keys[]    = $key;
            $values[]  = ($value == 'Infinity') ? -1 : $value;
            $submitval = 1;
        } elseif (in_array($key, ['notice', 'noticeClass'], true)) {
            $keys[]    = $key;
            $values[]  = $value;
            $submitval = 3;
        } elseif (preg_match('/^profile/', (string)$key)) {
            if (in_array($key, $allowedProfileFields, true)) {
                $spv[$key] = $value;
                $submitval = 2;
            }
        } else {
            $submitval = 0;
        }

        if (!in_array($key, $dbfields, true)
            && $submitval == 1
            && preg_match('/^k[0-9a-fA-F]+$/', $key)) {
            ensureColumnAndPid(
                $db, $db_table, $db_pids_table,
                $key, $value, $dbfields
            );
        }
    }

    // Случай 1: это старт сессии (пришёл profileName)
    if ($submitval == 2) {
        $record = [];
        foreach ($request as $key => $value) {
            if (in_array($key, ['session', 'time', 'id'], true)
                || preg_match('/^profile/', $key)) {
                $record[$key] = $value;
            }
        }

        $db->begin_transaction();
        try {
            processSessionStartRecord(
                $db,
                $record,
                $db_sessions_table,
                $lang,
                $ctx['username'],
                $ctx['tg_token']       ?? null,
                $ctx['tg_chatid']      ?? null,
                $ctx['tg_socks_proxy'] ?? '',
                $translations,
                $ctx['ip']             ?? null
            );
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return;
    }

    // Случай 2: обычный datapoint
    $rawkeys   = array_merge($keys, $sesskeys);
    $rawvalues = array_merge($values, $sessvalues);

    if (count($rawkeys) !== count($rawvalues)
        || count($rawkeys) === 0
        || count($sesskeys) !== count($sessvalues)
        || count($sesskeys) === 0) {
        return;
    }

    if ($submitval == 1) {
        insert_single_record($db, $db_table, $rawkeys, $rawvalues);
    }

    $sesskeys[]   = 'timeend';
    $sessvalues[] = $sesstime;

    $sql = "INSERT INTO $db_sessions_table ("
         . quote_names($sesskeys) . ") VALUES ("
         . quote_values($sessvalues)
         . ") ON DUPLICATE KEY UPDATE id=?, timeend=GREATEST(timeend, ?), sessionsize=sessionsize+1";
    $db->execute_query($sql, [$id, $sesstime]);
}

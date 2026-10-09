<?php
const REDLOG_HEADER = "TIME ECT EOT IAT ATF AAT EXT SPD RPM MAP MAF TPS IGN INJ INJD IAC AFR O2S O2S2 EGT EOP FP ERT MHS BSTD FAN GEAR BS1 BS2 PG0 PG1 VLT RLC GLAT GLON GSPD ODO\n";

// ────────────────────────────────────────────────────────────
// Streaming (NDJSON) — включается заголовком X-Stream: 1
// ────────────────────────────────────────────────────────────
$streaming = ($_SERVER['HTTP_X_STREAM'] ?? '') === '1';

if ($streaming) {
    while (ob_get_level()) ob_end_clean();
    ob_implicit_flush(true);
    set_time_limit(0);
    ini_set('zlib.output_compression', 'Off');
    header('X-Accel-Buffering: no');
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache');
}

/** Отправить NDJSON-событие (только в streaming-режиме). */
function stream_emit(array $event): void {
    global $streaming;
    if (!$streaming) return;
    echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    @flush();
}

/**
 * Ошибка одного файла в streaming-режиме.
 * НЕ делает exit — вызывающий код сам решает: continue или die.
 */
function stream_file_error(string $file, string $message): void {
    global $streaming;
    if (!$streaming) return;
    stream_emit([
        'type'    => 'file_done',
        'file'    => $file,
        'status'  => 'error',
        'message' => $message,
    ]);
}

/**
 * Фатальная ошибка в streaming-режиме: эмитит summary и делает exit.
 * Только для глобальных сбоев (нет POST, слишком много файлов и т.п.),
 * НЕ для ошибок отдельного файла.
 */
function stream_fail(string $message, ?mysqli $db = null): void {
    global $streaming;
    if (!$streaming) return;
    stream_emit([
        'type'    => 'summary',
        'message' => $message,
        'error'   => true,
    ]);
    if ($db) { try { $db->close(); } catch (Throwable $e) {} }
    exit;
}

// ────────────────────────────────────────────────────────────
// Переменные, читаемые в outer catch. Инициализируем null:
// исключение может прилететь ДО их объявления в try-блоке.
// ────────────────────────────────────────────────────────────
$files             = [];     // нормализованный $_FILES['file']
$target_file       = [];     // tmp-пути, созданные tempnam()
$current_file_name = '?';    // имя файла текущей итерации
$pending_session   = null;   // сессия, вставленная в БД, но НЕ закоммиченная

try {
    if (!$_COOKIE['stream']) {
        if ($streaming) {
            http_response_code(401);
            stream_emit(['type' => 'error', 'message' => 'unauthorized']);
            exit;
        }
        http_response_code(401);
        die;
    }
    require_once __DIR__ . '/src/db.php';
    include_once __DIR__ . '/timezone.php';
    include_once __DIR__ . '/translations.php';
    require_once __DIR__ . '/src/methods.php';
    allowMethods('POST');

    if (isset($_SESSION['admin'])) header("Refresh:0; url=/");

    $session_count = (int)$db->execute_query(
        "SELECT COUNT(*) FROM sessions WHERE user_id = ?",
        [$user_id]
    )->fetch_row()[0];

    $ok = 0;

    // Exceed php_post_size
    if (!isset($_FILES['file'])) {
        $msg = $translations[current_lang()]['redlog.post.max'];
        if ($streaming) {
            http_response_code(406);
            stream_fail($msg, $db);
        }
        http_response_code(406);
        die($msg);
    }

    // Convert to simply array
    foreach ($_FILES['file'] as $k => $l) {
        foreach ($l as $i => $v) {
            $files[$i][$k] = $v;
        }
    }

    if (count($files) > 10) {
        $msg = $translations[current_lang()]['redlog.warn.count'];
        if ($streaming) {
            http_response_code(406);
            stream_fail($msg, $db);
        }
        http_response_code(406);
        echo $msg;
        die;
    }

    for ($f = 0; $f < count($files); $f++) {

        $current_file_name = $files[$f]['name'];
        $fileName          = $current_file_name;

        // Обрыв соединения — прекращаем (только в streaming)
        if ($streaming && connection_aborted()) {
            $db->close();
            exit;
        }

        stream_emit([
            'type'  => 'file_start',
            'file'  => $fileName,
            'index' => $f + 1,
            'total' => count($files),
        ]);

        $tmp_dir = sys_get_temp_dir();
        $target_file[$f] = tempnam($tmp_dir, 'upload_');
        if (!$target_file[$f]) {
            error_log("Error creating temporary file.");
            $msg = $translations[current_lang()]['redlog.err'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(500);
            die($msg);
        }

        if (!move_uploaded_file($files[$f]['tmp_name'], $target_file[$f])) {
            $msg = $translations[current_lang()]['redlog.err'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(406);
            die($msg);
        }

        $data = file_get_contents($target_file[$f]);
        $data_size = filesize($target_file[$f]) / 1048576;

        if ($data_size > 15) {
            if (file_exists($target_file[$f])) unlink($target_file[$f]);
            $msg = htmlspecialchars($fileName) . " " . $translations[current_lang()]['redlog.warn.size'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(406);
            echo $msg;
            die;
        }
        if ($limit != -1 && $session_count >= $limit) {
            if (file_exists($target_file[$f])) unlink($target_file[$f]);
            $msg = $translations[current_lang()]['redlog.nospace'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(507);
            die($msg);
        }
        elseif (!$data || !$data_size || strpos($data, REDLOG_HEADER) !== 0) {
            if (file_exists($target_file[$f])) unlink($target_file[$f]);
            $msg = htmlspecialchars($fileName) . " " . $translations[current_lang()]['redlog.broken'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(406);
            echo $msg;
            die;
        }
        else {
            $data = str_replace(REDLOG_HEADER, "", $data);
            $data = str_replace("\n", ' ', $data);
            $data = explode(" ", $data);

            $session = $data[0] - 1000;
            $size = count(file($target_file[$f])) - 1;
            $time = $data[0];

            $last_index = array_key_last($data);
            $time_end = $data[$last_index - 37] ?? $data[$last_index] ?? $time;
        }

        try {
            $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
            $db->execute_query(
                "INSERT INTO sessions (user_id, id, session, time, profileName, timeend, sessionsize, ip)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$user_id, 'RedManage', $session, $time, 'RedManage-Log', $time_end, $size, $ip]
            );
        } catch (Exception $e) {
            if (file_exists($target_file[$f])) unlink($target_file[$f]);
            $msg = htmlspecialchars($fileName) . " " . $translations[current_lang()]['redlog.dup'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(406);
            echo $msg;
            die;
        }

        // Сессия вставлена, но ещё не закоммичена.
        // Outer catch должен её снести, если TypeError пробросится.
        $pending_session = $session;

        $default_redlog_pids = [
            ['k10',     'Mass Air Flow Rate',                           'g/sec', 1, 1],
            ['k11',     'Throttle Position (Manifold)',                 '%',     1, 1],
            ['k1f',     'Run Time Since Engine Start',                  's',     1, 1],
            ['k2100',   'Injector duty',                                '%',     1, 1],
            ['k2101',   'EXT temperature',                              '°C',    1, 1],
            ['k2111',   'Engine Oil Pressure',                          'Bar',   1, 1],
            ['k2112',   'Injection time',                               'ms',    1, 1],
            ['k2113',   'Idle Air Control',                             '%',     1, 1],
            ['k2118',   'Motorhours',                                   'H',     1, 1],
            ['k2120',   'Boost solenoid duty',                          '%',     1, 1],
            ['k2122',   'Fan Status',                                   '%',     1, 1],
            ['k2124',   'Gear',                                         null,    1, 1],
            ['k2125',   'PG0 Output',                                   null,    1, 1],
            ['k2126',   'PG1 Output',                                   null,    1, 1],
            ['k21cc',   'Air Fuel Ratio',                               null,    1, 1],
            ['k21e1',   'BS1 Input',                                    null,    1, 1],
            ['k21e2',   'BS2 Input',                                    null,    1, 1],
            ['k21fa',   'Rollback',                                     null,    1, 1],
            ['k46',     'Ambient Air Temp',                             '°C',    1, 1],
            ['k5',      'Engine Coolant Temperature',                   '°C',    1, 1],
            ['k5c',     'Engine Oil Temperature',                       '°C',    1, 1],
            ['k78',     'EGT',                                          '°C',    1, 1],
            ['k2119',   'Fuel Pressure',                                'Bar',   1, 1],
            ['kb',      'Intake Manifold Pressure',                     'kPa',   1, 1],
            ['kb4',     'Transmission Temperature (Method 2)',          '°C',    1, 1],
            ['kc',      'Engine RPM x 100',                             'rpm',   1, 1],
            ['kd',      'Speed (OBD)',                                  'km/h',  1, 1],
            ['ke',      'Ignition Advance',                             '°',     1, 1],
            ['kf',      'Intake Air Temperature',                       '°C',    1, 1],
            ['kff1001', 'Speed (GPS)',                                  'km/h',  1, 1],
            ['kff1005', 'GPS Longitude',                                '°',     0, 0],
            ['kff1006', 'GPS Latitude',                                 '°',     0, 0],
            ['kff1007', 'GPS Bearing (Used in GPS records)',            '°',     0, 0],
            ['kff1202', 'Boost',                                        'Bar',   1, 1],
            ['kff1204', 'Trip Distance',                                'km',    1, 1],
            ['kff120c', 'Trip Distance (Stored in Vehicle Profile)',    'km',    1, 1],
            ['kff1214', 'O2 Volts Bank 1 Sensor 1',                     'V',     1, 1],
            ['kff1218', 'O2 Volts Bank 2 Sensor 1',                     'V',     1, 1],
            ['kff1238', 'Voltage (OBD Adapter)',                        'V',     1, 1],
        ];

        $pids_ph = [];
        $pids_vals = [];
        foreach ($default_redlog_pids as $row) {
            $pids_ph[] = '(?,?,?,?,?,?)';
            $pids_vals[] = $user_id;
            $pids_vals[] = $row[0];
            $pids_vals[] = $row[1];
            $pids_vals[] = $row[2];
            $pids_vals[] = $row[3];
            $pids_vals[] = $row[4];
        }
        $db->execute_query(
            "INSERT IGNORE INTO pids (user_id, id, description, units, populated, stream)
             VALUES " . implode(',', $pids_ph),
            $pids_vals
        );

        // Сбор и вставка
        $batch = [];
        $batchSize = 500;
        $total = sizeof($data);

        try {
            $db->begin_transaction();

            for ($i = 0; $i < $total - 1; $i += 37) {
                $time    = $data[$i];
                $ect     = $data[$i + 1];
                $eot     = $data[$i + 2];
                $iat     = $data[$i + 3];
                $atf     = $data[$i + 4];
                $aat     = $data[$i + 5];
                $ext     = $data[$i + 6];
                $spd     = $data[$i + 7];
                $rpm     = $data[$i + 8];
                $map     = $data[$i + 9];
                $boost   = ($map - 101) / 100;
                $maf     = $data[$i + 10];
                $tps     = $data[$i + 11];
                $ign     = $data[$i + 12];
                $inj     = $data[$i + 13];
                $injd    = $data[$i + 14];
                $iac     = $data[$i + 15];
                $afr     = $data[$i + 16];
                $o2s     = $data[$i + 17];
                $o2s2    = $data[$i + 18];
                $egt     = $data[$i + 19];
                $eop     = $data[$i + 20];
                $fp      = $data[$i + 21];
                $ert     = $data[$i + 22];
                $mhs     = $data[$i + 23];
                $bstd    = $data[$i + 24];
                $fan     = $data[$i + 25];
                $gear    = $data[$i + 26];
                $bs1     = $data[$i + 27];
                $bs2     = $data[$i + 28];
                $pg0     = $data[$i + 29];
                $pg1     = $data[$i + 30];
                $vlt     = $data[$i + 31];
                $rlc     = $data[$i + 32];
                $glat    = $data[$i + 33];
                $glon    = $data[$i + 34];
                $gspd    = $data[$i + 35];
                $odo     = $data[$i + 36];

                $pids = [
                    'kff1005' => $glon,
                    'kff1006' => $glat,
                    'k21fa'   => $rlc,
                    'kff1202' => $boost,
                    'k5'      => $ect,
                    'k5c'     => $eot,
                    'kf'      => $iat,
                    'kb4'     => $atf,
                    'kc'      => $rpm,
                    'kb'      => $map,
                    'k1f'     => $ert,
                    'k2118'   => $mhs,
                    'k2120'   => $bstd,
                    'k2122'   => $fan,
                    'k2125'   => $pg0,
                    'kff1238' => $vlt,
                    'k46'     => $aat,
                    'k2101'   => $ext,
                    'kd'      => $spd,
                    'k10'     => $maf,
                    'k11'     => $tps,
                    'ke'      => $ign,
                    'k2112'   => $inj,
                    'k2100'   => $injd,
                    'k2113'   => $iac,
                    'k21cc'   => $afr,
                    'kff1214' => $o2s,
                    'kff1218' => $o2s2,
                    'k78'     => $egt,
                    'k2111'   => $eop,
                    'k2119'   => $fp,
                    'k2124'   => $gear,
                    'k21e1'   => $bs1,
                    'k21e2'   => $bs2,
                    'k2126'   => $pg1,
                    'kff1001' => $gspd,
                    'kff120c' => $odo,
                ];

                $batch[] = [
                    'session' => $session,
                    'time'    => (int)$time,
                    'pids'    => $pids,
                ];

                if (count($batch) >= $batchSize) {
                    insert_log_rows_bulk($db, (int)$user_id, $batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                insert_log_rows_bulk($db, (int)$user_id, $batch);
            }

            $db->commit();

            // Коммит прошёл — сессия больше не pending.
            $pending_session = null;

        } catch (Exception $e) {
            $db->rollBack();
            $pending_session = null;   // транзакция откатана — pending не нужен

            if (file_exists($target_file[$f])) unlink($target_file[$f]);

            $file = htmlspecialchars($fileName);
            $msg = str_contains($e->getMessage(), 'Duplicate')
                ? $file . " " . $translations[current_lang()]['redlog.dup']
                : $file . " " . $translations[current_lang()]['redlog.broken'];

            $db->execute_query("DELETE FROM logs     WHERE user_id = ? AND session = ?", [$user_id, $session]);
            $db->execute_query("DELETE FROM sessions WHERE user_id = ? AND session = ?", [$user_id, $session]);

            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(406);
            echo $msg;
            die;
        }

        $session_count++;
        $ok++;

        stream_emit([
            'type'     => 'file_done',
            'file'     => $fileName,
            'status'   => 'ok',
            'sessions' => 1,
        ]);

        unlink($target_file[$f]);
    }

    cache_flush();

    $word = getPluralForm($ok, $translations[current_lang()]['redlog.upload.ok']);
    $finalMsg = "$ok $word [RedManage]";

    if ($streaming) {
        $filesFailed = count($files) - $ok;
        stream_emit([
            'type'         => 'summary',
            'files_ok'     => $ok,
            'files_failed' => $filesFailed,
            'sessions_ok'  => $ok,
            'message'      => $finalMsg,
            'error'        => $filesFailed > 0,
        ]);
        $db->close();
        exit;
    }

    echo $finalMsg;
    $db->close();

} catch (TypeError $e) {
    // ────────────────────────────────────────────────────────
    // Аварийный путь.
    //
    // TypeError extends Error, не Exception — поэтому внутренние
    // catch (Exception $e) его НЕ ловят. Всё, что осталось
    // незачищенным (транзакция, tmp-файл, pending-сессия),
    // прибираем здесь.
    // ────────────────────────────────────────────────────────

    // 1) Откат незакоммиченной транзакции
    if (isset($db) && $db instanceof mysqli) {
        try { $db->rollBack(); } catch (Throwable $e2) {}
    }

    // 2) Чистим все временные файлы, которые не успели
    //    за собой прибрать. Итерация по массиву: несуществующие
    //    уже unlink'нуты, @unlink на них — no-op.
    if (!empty($target_file) && is_array($target_file)) {
        foreach ($target_file as $tmp) {
            if (is_string($tmp) && $tmp !== '' && file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    // 3) Сносим ЧАСТИЧНУЮ сессию.
    //    ВАЖНО: используем $pending_session, а НЕ $session.
    //    $session — значение из последнего валидного файла,
    //    оно могло быть уже успешно закоммичено ранее, и его
    //    удаление = потеря данных.
    if ($pending_session !== null && isset($db) && $db instanceof mysqli) {
        try {
            $db->execute_query("DELETE FROM logs     WHERE user_id = ? AND session = ?", [$user_id, $pending_session]);
            $db->execute_query("DELETE FROM sessions WHERE user_id = ? AND session = ?", [$user_id, $pending_session]);
        } catch (Throwable $e2) {
            error_log("import_redlog: outer-catch cleanup failed: " . $e2->getMessage());
        }
    }

    // 4) Имя файла — без warning'ов про undefined.
    $fileName = $current_file_name;
    $msg = htmlspecialchars($fileName) . " " . $translations[current_lang()]['redlog.broken'];

    if ($streaming) {
        stream_emit([
            'type'    => 'summary',
            'message' => $msg,
            'error'   => true,
        ]);
        if (isset($db) && $db instanceof mysqli) {
            try { $db->close(); } catch (Throwable $e2) {}
        }
        exit;
    }

    http_response_code(406);
    echo $msg;
    die;
}

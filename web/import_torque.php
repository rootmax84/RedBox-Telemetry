<?php
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

/** Ошибка одного файла в streaming-режиме (без exit). */
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

/** Фатальная ошибка в streaming-режиме (с exit). */
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

    if (isset($_SESSION['admin'])) {
        header("Refresh:0; url=.");
        exit;
    }

    if (!isset($_FILES['file'])) {
        $msg = $translations[current_lang()]['redlog.post.max'];
        if ($streaming) {
            http_response_code(406);
            stream_fail($msg, $db);
        }
        http_response_code(406);
        die($msg);
    }

    $files = [];
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

    $session_count = (int)$db->execute_query(
        "SELECT COUNT(*) FROM sessions WHERE user_id = ?",
        [$user_id]
    )->fetch_row()[0];

    $totalOk = 0;
    $filesOk = 0;
    $filesTotal = count($files);

    foreach ($files as $index => $fileInfo) {

        $fileName = $fileInfo['name'];

        if ($streaming && connection_aborted()) {
            $db->close();
            exit;
        }

        stream_emit([
            'type'  => 'file_start',
            'file'  => $fileName,
            'index' => $index + 1,
            'total' => $filesTotal,
        ]);

        $tmp_dir = sys_get_temp_dir();
        $target_file = tempnam($tmp_dir, 'torque_');
        if (!$target_file) {
            $msg = $translations[current_lang()]['redlog.err'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(500);
            die($msg);
        }

        if (!move_uploaded_file($fileInfo['tmp_name'], $target_file)) {
            $msg = $translations[current_lang()]['redlog.err'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(406);
            die($msg);
        }

        $data_raw = file_get_contents($target_file);
        $data_size = filesize($target_file) / 1048576;

        if ($data_size > 15) {
            unlink($target_file);
            $msg = htmlspecialchars($fileName) . " " . $translations[current_lang()]['redlog.warn.size'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(406);
            echo $msg;
            die;
        }

        // Разбиение на блоки
        $lines = preg_split('/\r?\n/', $data_raw);
        $blocks = [];
        $currentBlock = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $isHeader = (strpos($line, 'GPS Time') === 0 || strpos($line, 'Device Time') === 0);
            if ($isHeader) {
                if ($currentBlock !== null) {
                    $blocks[] = $currentBlock;
                }
                $currentBlock = ['header' => $line, 'lines' => []];
            } else {
                if ($currentBlock !== null) {
                    $currentBlock['lines'][] = $line;
                }
            }
        }
        if ($currentBlock !== null) {
            $blocks[] = $currentBlock;
        }

        if (empty($blocks)) {
            unlink($target_file);
            $msg = htmlspecialchars($fileName) . " " . $translations[current_lang()]['redlog.broken'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(406);
            echo $msg;
            die;
        }

        $fileOk = 0;

        $pidRes = $db->execute_query(
            "SELECT id, description FROM pids WHERE user_id = ?",
            [$user_id]
        );
        $pids = [];
        while ($row = $pidRes->fetch_assoc()) {
            $pids[] = $row;
        }

        $normalise = function($str) {
            $str = preg_replace('/\(.*?\)/', '', $str);
            $str = trim(strtolower($str));
            $str = preg_replace('/\s+/', ' ', $str);
            return $str;
        };

        $isMatch = function($colClean, $pidClean) {
            if ($colClean === $pidClean) return true;
            $colNoSpaces = str_replace(' ', '', $colClean);
            $pidNoSpaces = str_replace(' ', '', $pidClean);
            if ($colNoSpaces === $pidNoSpaces) return true;
            $colNoGps = trim(str_replace('gps', '', $colNoSpaces));
            $pidNoGps = trim(str_replace('gps', '', $pidNoSpaces));
            if ($colNoGps === $pidNoGps) return true;
            if (strpos($pidClean, $colClean) === 0 || strpos($colClean, $pidClean) === 0) return true;
            if (strpos($pidNoSpaces, $colNoSpaces) !== false || strpos($colNoSpaces, $pidNoSpaces) !== false) return true;
            return false;
        };

        // Флаг — прерывание обработки этого файла (переход к следующему)
        $skipFile = false;

        foreach ($blocks as $block) {
            $headerLine = $block['header'];
            $dataLines = $block['lines'];
            if (empty($dataLines)) continue;

            $headerCols = str_getcsv($headerLine);
            $headerCount = count($headerCols);

            $colMap = [];
            $gpsTimeIdx = null;
            $deviceTimeIdx = null;

            foreach ($headerCols as $idx => $colName) {
                $clean = $normalise($colName);
                if ($clean === 'gps time') {
                    $gpsTimeIdx = $idx;
                } elseif ($clean === 'device time') {
                    $deviceTimeIdx = $idx;
                } else {
                    $candidates = [];
                    foreach ($pids as $pid) {
                        $pidClean = $normalise($pid['description']);
                        if ($isMatch($clean, $pidClean)) {
                            $candidates[] = $pid;
                        }
                    }
                    if ($candidates) {
                        usort($candidates, function($a, $b) use ($clean) {
                            $aHasGps = stripos($a['description'], 'gps') !== false;
                            $bHasGps = stripos($b['description'], 'gps') !== false;
                            $colHasGps = stripos($clean, 'gps') !== false;
                            if ($colHasGps) {
                                if ($aHasGps && !$bHasGps) return -1;
                                if (!$aHasGps && $bHasGps) return 1;
                            }
                            return strlen($b['description']) - strlen($a['description']);
                        });
                        $best = $candidates[0];
                        if (!empty($best['id']) && !in_array($best['id'], $colMap, true)) {
                            $colMap[$idx] = $best['id'];
                        }
                    }
                }
            }

            if ($deviceTimeIdx === null) continue;

            $rows = [];
            $timestampsMs = [];
            foreach ($dataLines as $line) {
                $cols = str_getcsv($line);
                if (count($cols) !== $headerCount) continue;
                $deviceTimeStr = $cols[$deviceTimeIdx] ?? '';
                $tsMs = parseDeviceTime($deviceTimeStr);
                if ($tsMs === false) continue;
                $timestampsMs[] = $tsMs;
                $row_pids = [];
                foreach ($colMap as $csvIdx => $pidId) {
                    $val = $cols[$csvIdx] ?? 0;
                    $row_pids[$pidId] = is_numeric($val) ? floatval($val) : 0;
                }
                $rows[] = ['time' => $tsMs, 'pids' => $row_pids];
            }

            if (empty($rows)) continue;

            $sessionId = $timestampsMs[0];
            $firstTime = $timestampsMs[0];
            $lastTime  = end($timestampsMs);
            $rowCount  = count($rows);

            if ($limit != -1 && $session_count >= $limit) {
                unlink($target_file);
                $msg = $translations[current_lang()]['redlog.nospace'];
                if ($streaming) {
                    stream_file_error($fileName, $msg);
                    $skipFile = true;
                    break;
                }
                http_response_code(507);
                die($msg);
            }

            try {
                $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
                $db->execute_query(
                    "INSERT INTO sessions (user_id, id, session, time, profileName, timeend, sessionsize, ip)
                     VALUES (?,?,?,?,?,?,?,?)",
                    [$user_id, 'TorqueLog', $sessionId, $firstTime, 'Torque-Log', $lastTime, $rowCount, $ip]
                );
            } catch (Exception $e) {
                unlink($target_file);
                $msg = htmlspecialchars($fileName) . " " . $translations[current_lang()]['redlog.dup'];
                if ($streaming) {
                    stream_file_error($fileName, $msg);
                    $skipFile = true;
                    break;
                }
                http_response_code(406);
                echo $msg;
                die;
            }

            $pidIdsOrdered = array_values($colMap);
            $existing_pids = [];
            $__r = $db->execute_query("SELECT id FROM pids WHERE user_id = ?", [$user_id]);
            while ($__row = $__r->fetch_assoc()) {
                $existing_pids[$__row['id']] = true;
            }
            foreach ($pidIdsOrdered as $pidId) {
                if (!isset($existing_pids[$pidId])) {
                    $db->execute_query(
                        "INSERT IGNORE INTO pids (user_id, id, description, populated, stream, favorite) 
                         VALUES (?,?,?,1,1,0)",
                        [$user_id, $pidId, $pidId]
                    );
                    $existing_pids[$pidId] = true;
                    $pids[] = ['id' => $pidId, 'description' => $pidId];
                }
            }

            $batch = [];
            $batchSize = 500;

            try {
                $db->begin_transaction();
                foreach ($rows as $row) {
                    $batch[] = [
                        'session' => $sessionId,
                        'time'    => $row['time'],
                        'pids'    => $row['pids'],
                    ];
                    if (count($batch) >= $batchSize) {
                        insert_log_rows_bulk($db, (int)$user_id, $batch);
                        $batch = [];
                    }
                }
                if ($batch) {
                    insert_log_rows_bulk($db, (int)$user_id, $batch);
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $db->execute_query("DELETE FROM logs     WHERE user_id = ? AND session = ?", [$user_id, $sessionId]);
                $db->execute_query("DELETE FROM sessions WHERE user_id = ? AND session = ?", [$user_id, $sessionId]);
                unlink($target_file);
                $msg = htmlspecialchars($fileName) . " " . $translations[current_lang()]['redlog.broken'];
                if ($streaming) {
                    stream_file_error($fileName, $msg);
                    $skipFile = true;
                    break;
                }
                http_response_code(406);
                echo $msg;
                die;
            }

            $fileOk++;
            $session_count++;
        }

        // Если прервали обработку этого файла — идём к следующему
        if ($skipFile) {
            continue;
        }

        unlink($target_file);

        if ($fileOk === 0) {
            $msg = htmlspecialchars($fileName) . " " . $translations[current_lang()]['nodata'];
            if ($streaming) {
                stream_file_error($fileName, $msg);
                continue;
            }
            http_response_code(406);
            echo $msg;
            die;
        }

        $filesOk++;
        $totalOk += $fileOk;

        stream_emit([
            'type'     => 'file_done',
            'file'     => $fileName,
            'status'   => 'ok',
            'sessions' => $fileOk,
        ]);
    }

    cache_flush();

    if (current_lang() === 'ru') {
        $fileWord = getPluralForm($filesOk, $translations[current_lang()]['redlog.file']);
        $sessionWord = getPluralForm($totalOk, $translations[current_lang()]['redlog.session']);
        $finalMsg = "$filesOk $fileWord ($totalOk $sessionWord) успешно загружено [Torque]";
    } else {
        $finalMsg = "$filesOk file(s) ($totalOk session(s)) successfully uploaded [Torque]";
    }

    if ($streaming) {
        $filesFailed = $filesTotal - $filesOk;
        stream_emit([
            'type'         => 'summary',
            'files_ok'     => $filesOk,
            'files_failed' => $filesFailed,
            'sessions_ok'  => $totalOk,
            'message'      => $finalMsg,
            'error'        => $filesFailed > 0,
        ]);
        $db->close();
        exit;
    }

    echo $finalMsg;
    $db->close();

} catch (TypeError $e) {
    $fileName = $files[$index]['name'] ?? '?';
    $msg = htmlspecialchars($fileName) . " " . $translations[current_lang()]['redlog.broken'];

    if ($streaming) {
        stream_emit([
            'type'    => 'summary',
            'message' => $msg,
            'error'   => true,
        ]);
        try { $db->close(); } catch (Throwable $e2) {}
        exit;
    }

    http_response_code(406);
    echo $msg;
    die;
}

function parseDeviceTime($str) {
    $str = trim($str);
    $lowerStr = mb_strtolower($str, 'UTF-8');

    $replacements = [
        'янв' => 'jan', 'фев' => 'feb', 'мар' => 'mar', 'апр' => 'apr',
        'май' => 'may', 'мая' => 'may',
        'июн' => 'jun', 'июл' => 'jul', 'авг' => 'aug',
        'сен' => 'sep', 'окт' => 'oct', 'ноя' => 'nov', 'дек' => 'dec',
        'ene' => 'jan', 'feb' => 'feb', 'mar' => 'mar', 'abr' => 'apr',
        'may' => 'may', 'jun' => 'jun', 'jul' => 'jul', 'ago' => 'aug',
        'sep' => 'sep', 'oct' => 'oct', 'nov' => 'nov', 'dic' => 'dec',
        'mär' => 'mar', 'mrz' => 'mar', 'mai' => 'may', 'okt' => 'oct', 'dez' => 'dec',
    ];

    foreach ($replacements as $local => $eng) {
        $lowerStr = preg_replace('/' . preg_quote($local, '/') . '\./', $eng, $lowerStr);
        $lowerStr = str_replace($local, $eng, $lowerStr);
    }

    $dt = DateTime::createFromFormat('d-M-Y H:i:s.v', $lowerStr);
    if ($dt) {
        return (int)($dt->getTimestamp() . sprintf('%03d', (int)$dt->format('v')));
    }

    if (($ts = strtotime($str)) !== false) {
        return $ts * 1000;
    }
    return false;
}

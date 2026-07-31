<?php
try {
    if (!$_COOKIE['stream']) {
        http_response_code(401);
        die;
    }

    require_once 'db.php';
    include 'timezone.php';
    include_once 'translations.php';

    if (isset($_SESSION['admin'])) {
        header("Refresh:0; url=.");
        exit;
    }

    if (!isset($_FILES['file'])) {
        http_response_code(406);
        die($translations[$_COOKIE['lang']]['redlog.post.max']);
    }

    $files = [];
    foreach ($_FILES['file'] as $k => $l) {
        foreach ($l as $i => $v) {
            $files[$i][$k] = $v;
        }
    }

    if (count($files) > 10) {
        http_response_code(406);
        echo $translations[$_COOKIE['lang']]['redlog.warn.count'];
        die;
    }

    $db_limit = $db->execute_query(
        "SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
        [$db_name, $db_table]
    )->fetch_row()[0];

    $totalOk = 0;   // всего успешных сессий
    $filesOk = 0;   // количество файлов с хотя бы одной успешной сессией

    foreach ($files as $index => $fileInfo) {
        $tmp_dir = sys_get_temp_dir();
        $target_file = tempnam($tmp_dir, 'torque_');
        if (!$target_file) {
            http_response_code(500);
            die($translations[$_COOKIE['lang']]['redlog.err']);
        }

        if (!move_uploaded_file($fileInfo['tmp_name'], $target_file)) {
            http_response_code(406);
            die($translations[$_COOKIE['lang']]['redlog.err']);
        }

        $data_raw = file_get_contents($target_file);
        $data_size = filesize($target_file) / 1048576;

        if ($data_size > 15) {
            unlink($target_file);
            http_response_code(406);
            echo htmlspecialchars($fileInfo['name']) . " " . $translations[$_COOKIE['lang']]['redlog.warn.size'];
            die;
        }

        if ($db_limit >= $limit || $data_size >= $limit || ($db_limit + $data_size) >= $limit) {
            unlink($target_file);
            http_response_code(406);
            die($translations[$_COOKIE['lang']]['redlog.nospace']);
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
            http_response_code(406);
            echo htmlspecialchars($fileInfo['name']) . " " . $translations[$_COOKIE['lang']]['redlog.broken'];
            die;
        }

        $fileOk = 0; // успешных блоков в этом файле

        $pidRes = $db->query("SELECT id, description FROM $db_pids_table");
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
                        if (!in_array($best['id'], $colMap)) {
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
                $rowValues = [];
                foreach ($colMap as $csvIdx => $pidId) {
                    $val = $cols[$csvIdx] ?? 0;
                    $rowValues[] = is_numeric($val) ? floatval($val) : 0;
                }
                $rows[] = ['time' => $tsMs, 'values' => $rowValues];
            }

            if (empty($rows)) continue;

            $sessionId = $timestampsMs[0];
            $firstTime = $timestampsMs[0];
            $lastTime  = end($timestampsMs);
            $rowCount  = count($rows);

            try {
                $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
                $db->execute_query(
                    "INSERT INTO $db_sessions_table (id, session, time, profileName, timeend, sessionsize, ip)
                     VALUES (?,?,?,?,?,?,?)",
                    ['TorqueLog', $sessionId, $firstTime, 'Torque-Log', $lastTime, $rowCount, $ip]
                );
            } catch (Exception $e) {
                unlink($target_file);
                http_response_code(406);
                echo htmlspecialchars($fileInfo['name']) . " " . $translations[$_COOKIE['lang']]['redlog.dup'];
                die;
            }

            // Автодобавление столбцов
            $pidIdsOrdered = array_values($colMap);
            foreach ($pidIdsOrdered as $pidId) {
                if (!column_exists($db, $db_table, $pidId)) {
                    $db->query("ALTER TABLE $db_table ADD COLUMN `$pidId` FLOAT NOT NULL DEFAULT 0");
                }
            }

            // Вставка данных
            $allColumns = array_merge(['session', 'time'], $pidIdsOrdered);
            $batch = [];
            $batchSize = 500;

            try {
                $db->begin_transaction();
                foreach ($rows as $row) {
                    $batch[] = array_merge([$sessionId, $row['time']], $row['values']);
                    if (count($batch) >= $batchSize) {
                        bulkInsertIgnore($db, $db_table, $allColumns, $batch);
                        $batch = [];
                    }
                }
                if ($batch) {
                    bulkInsertIgnore($db, $db_table, $allColumns, $batch);
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $db->execute_query("DELETE FROM $db_sessions_table WHERE id='TorqueLog' AND session=?", [$sessionId]);
                unlink($target_file);
                http_response_code(406);
                echo htmlspecialchars($fileInfo['name']) . " " . $translations[$_COOKIE['lang']]['redlog.broken'];
                die;
            }

            $fileOk++;
        }

        unlink($target_file);

        if ($fileOk === 0) {
            http_response_code(406);
            echo htmlspecialchars($fileInfo['name']) . " " . $translations[$_COOKIE['lang']]['nodata'];
            die;
        }

        $filesOk++;
        $totalOk += $fileOk;
    }

    cache_flush();

    if ($_COOKIE['lang'] === 'ru') {
        $fileWord = getPluralForm($filesOk, $translations[$_COOKIE['lang']]['redlog.file']);
        $sessionWord = getPluralForm($totalOk, $translations[$_COOKIE['lang']]['redlog.session']);
        echo "$filesOk $fileWord ($totalOk $sessionWord) успешно загружено [Torque]";
    } else {
        echo "$filesOk file(s) ($totalOk session(s)) successfully uploaded [Torque]";
    }

    $db->close();

} catch (TypeError $e) {
    http_response_code(406);
    echo htmlspecialchars($files[$f]['name'] ?? '') . " " . $translations[$_COOKIE['lang']]['redlog.broken'];
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

function bulkInsertIgnore($db, $table, $columns, $rows) {
    $placeholders = [];
    $values = [];
    foreach ($rows as $row) {
        $placeholders[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $values = array_merge($values, $row);
    }
    $sql = "INSERT IGNORE INTO $table (`" . implode('`,`', $columns) . "`) VALUES " . implode(',', $placeholders);
    $db->execute_query($sql, $values);
}

<?php
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/plot_cache.php';

$share_id = $_GET['u'] ?? $_GET['uid'] ?? null;

if (isset($_GET['id'], $_GET['sig'])
    && is_string($share_id) && $share_id !== ''
    && is_string($_GET['id']) && $_GET['id'] !== ''
    && is_string($_GET['sig']) && $_GET['sig'] !== ''
) {
    $_SESSION['torque_logged_in'] = true;
    require_once __DIR__ . '/src/db.php';

    $sid = $_GET['id'];
    $sig = $_GET['sig'];

    if (!checkRateLimit(5)) {
        if (!isset($_SESSION['torque_user'])) {
            unset($_SESSION['torque_logged_in'], $_SESSION['share']);
        }
        header('Location: /catch?c=block');
        exit;
    }

    $user_data = share_lookup_user($db, $share_id);
    if (!$user_data) {
        header('Location: /catch?c=noshare');
        exit;
    }

    if ((int)$user_data['s'] === 0) {
        header('Location: /catch?c=noshare');
        exit;
    }

    $username            = $user_data['user'];
    $share_secret        = $user_data['share_secret'];
    $user_filter         = $user_data['sessions_filter'];
    $GLOBALS['user_id']  = (int)$user_data['id'];
    $GLOBALS['share_sessions_filter'] = (int)$user_filter;

    $payload      = share_payload_session($share_id, $sid, share_gps_flag());
    $expected_sig = hash_hmac('sha256', $payload, $share_secret);

    if (!hash_equals($expected_sig, $sig)) {
        header('Location: /catch?c=noshare');
        exit;
    }

    checkRateLimit(5, 3600, true);
} else {
    require_once __DIR__ . '/src/db.php';
}

$user_id = current_user_id();

$json = [];

// Convert data units
$temp_rpm_dev = function ($rpm_dev) { return round($rpm_dev/100, 2); };
$tmp_mhs      = function ($mhs)     { return round($mhs,0); };
$tmp_vlt      = function ($vlt)     { return round($vlt,2); };
$tmp_ert      = function ($ert)     { return round($ert/60,0); };
$tmp_gear     = function ($gear)    { return $gear == '255' ? '0' : $gear; };

if (isset($_GET["id"])) {
    $session_id = (string)$_GET['id'];
    $cached_timestamp = null;
    $current_timestamp = getLastUpdateTimestamp($db, (int)$user_id, $session_id);

    if ($current_timestamp === null && empty($_SESSION['share'])) {
        $db->close();
        header('Location: /');
        exit;
    }

    // id (RedManage / TorqueLog / etc.)
    $cache_key_id = cache_var_key("session_id_{$session_id}");
    $id = false;

    if ($memcached_connected) {
        $cached_id_data = $memcached->get($cache_key_id);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($cached_id_data)) {
            list($id, $cached_timestamp) = $cached_id_data;
        }
    }

    if ($id === false || $cached_timestamp !== $current_timestamp) {
        $id = $db->execute_query(
            "SELECT id FROM sessions WHERE user_id = ? AND session = ?",
            [$user_id, $session_id]
        )->fetch_row()[0] ?? null;

        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_id, [$id, $current_timestamp], $db_cache_meta_ttl ?? 300);
            } catch (Exception $e) {
                error_log(sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode()));
            }
        }
    }

    // Units conversion settings
    $cache_key_settings = "user_settings_{$username}";
    $setqry = false;

    if ($memcached_connected) {
        $cached_settings_data = $memcached->get($cache_key_settings);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($cached_settings_data)) {
            list($setqry, $cached_timestamp) = $cached_settings_data;
        }
    }

    if ($setqry === false || $cached_timestamp !== $current_timestamp) {
        $setqry = $db->execute_query(
            "SELECT speed,temp,pressure,boost FROM users WHERE user=?",
            [$username]
        )->fetch_row();

        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_settings, [$setqry, $current_timestamp], $db_cache_ttl ?? 3600);
            } catch (Exception $e) {
                error_log(sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode()));
            }
        }
    }

    $speed    = $setqry[0];
    $temp     = $setqry[1];
    $pressure = $setqry[2];
    $boost    = $setqry[3];

    // PID descriptions/units
    $cache_key_pids = "pids_mapping_{$username}";
    $keyarr = false;

    if ($memcached_connected) {
        $cached_pids_data = $memcached->get($cache_key_pids);
        if ($memcached->getResultCode() === RatelCache::RES_SUCCESS && is_array($cached_pids_data)) {
            list($keyarr, $cached_timestamp) = $cached_pids_data;
        }
    }

    if ($keyarr === false || $cached_timestamp !== $current_timestamp) {
        $keyquery = $db->execute_query(
            "SELECT id, description, units FROM pids WHERE user_id = ?",
            [$user_id]
        );
        $keyarr = [];
        while ($row = $keyquery->fetch_assoc()) {
            $keyarr[$row['id']] = [$row['description'], $row['units']];
        }

        if ($memcached_connected) {
            try {
                $memcached->set($cache_key_pids, [$keyarr, $current_timestamp], $db_cache_meta_ttl ?? 300);
            } catch (Exception $e) {
                error_log(sprintf("Ratel cache error for user %s: %s (Code: %d)", $username, $e->getMessage(), $e->getCode()));
            }
        }
    }

    // A single request may contain 20 × 50k+ values. Never retain the
    // result set, expanded PID maps, and rendered series simultaneously.
    $selected_pids = [];
    for ($i = 1; $i <= 20 && isset($_GET["s$i"]); $i++) {
        if (!is_string($_GET["s$i"]) || $_GET["s$i"] === '') {
            http_response_code(400);
            echo '[]';
            return;
        }
        $selected_pids[] = $_GET["s$i"];
    }

    if ($selected_pids === []) {
        return; // Included by home.php/share.php without a chart request.
    }

    $units = [
        'speed' => [
            'km to miles' => [' (mph)', ' (miles)'],
            'miles to km' => [' (km/h)', ' (km)'],
        ],
        'temp' => [
            'Celsius to Fahrenheit' => ' (°F)',
            'Fahrenheit to Celsius' => ' (°C)',
        ],
        'pressure' => [
            'Psi to Bar' => ' (Bar)',
            'Bar to Psi' => ' (Psi)',
        ],
        'boost' => [
            'Psi to Bar' => ' (Bar)',
            'Bar to Psi' => ' (Psi)',
        ],
    ];

    // Reuse the existing per-user version namespace. Include every input that
    // changes the response (PID order, filters, units, PID labels and session
    // revision), so shared links and account views cannot cross-contaminate.
    $isStreamQuery = isset($_GET['last']);
    $plotCacheKey = null;
    $plotCacheRedis = null;
    $plotCacheLockToken = null;
    if (!$isStreamQuery && $memcached_connected && $current_timestamp !== null) {
        $filterRate = current_sessions_filter();
        $signature = hash('sha256', json_encode([
            'v' => RATEL_PLOT_CACHE_VERSION,
            'session' => $session_id,
            'timestamp' => $current_timestamp,
            'filter' => $filterRate,
            'pids' => $selected_pids,
            'units' => [$speed, $temp, $pressure, $boost, $id],
            'labels' => array_map(static fn($pid) => $keyarr[$pid] ?? null, $selected_pids),
        ], JSON_INVALID_UTF8_SUBSTITUTE));
        $plotCacheKey = cache_var_key('plot_json_' . $signature);
        if (plotCacheServe($memcached, $plotCacheKey)) {
            return;
        }
        $plotCacheRedis = get_redis_connection();
        $plotCacheLockToken = plotCacheLock($plotCacheRedis, $plotCacheKey);
        if ($plotCacheLockToken === null && $plotCacheRedis instanceof Redis) {
            // Another request may be warming the same key. Wait briefly;
            // never block a request indefinitely on an abandoned lock.
            for ($attempt = 0; $attempt < 10; ++$attempt) {
                usleep(100000);
                if (plotCacheServe($memcached, $plotCacheKey)) {
                    return;
                }
            }
        }
    }

    try {
    // Determine conversion/units once per selected PID rather than per row.
    $series = [];
    foreach ($selected_pids as $pid) {
        $description = (string)($keyarr[$pid][0] ?? $pid);
        $originalUnit = (string)($keyarr[$pid][1] ?? '');
        $originalSuffix = ' (' . $originalUnit . ')';
        $type = 'raw';
        $suffix = $originalSuffix;

        if (substri_count($description, 'Speed') > 0) {
            $type = 'speed';
            $suffix = $units['speed'][$speed][0] ?? $originalSuffix;
        } elseif (substri_count($description, 'Distance') > 0) {
            $type = 'speed';
            $suffix = $units['speed'][$speed][1] ?? $originalSuffix;
        } elseif (substri_count($description, 'Temp') > 0 || substri_count($description, 'EGT') > 0) {
            $type = 'temp';
            $suffix = $units['temp'][$temp] ?? $originalSuffix;
        } elseif (substri_count($description, 'Boost Solenoid Duty') > 0) {
            $type = 'raw';
        } elseif (substri_count($description, 'Boost') > 0) {
            $type = 'boost';
            $suffix = $units['boost'][$boost] ?? $originalSuffix;
        } elseif (substri_count($description, 'Pressure') > 0
            && !substri_count($description, 'Manifold')
            && !substri_count($description, 'Barometric')
            && !substri_count($description, 'Evap System')
            && !substri_count($description, 'Fuel Pressure legacy')
            && !substri_count($description, 'Fuel Rail Pressure')) {
            $type = 'pressure';
            $suffix = $units['pressure'][$pressure] ?? $originalSuffix;
        } elseif (substri_count($originalUnit, 'rpm') > 0) {
            $type = 'rpm';
        } elseif (substri_count($description, 'Motorhours') > 0) {
            $type = 'motorhours';
        } elseif (substri_count($description, 'Voltage (OBD Adapter)') > 0) {
            $type = 'voltage';
        } elseif (substri_count($description, 'Run Time Since Engine Start') > 0) {
            $type = 'runtime';
            $suffix = ' (m)';
        } elseif (substri_count($description, 'Gear') > 0) {
            $type = 'gear';
        }

        // Hold small series in RAM; spill individual series larger than 256 KiB
        // to disk automatically. At most 20 series = ~5 MiB stream RAM.
        $file = fopen('php://temp/maxmemory:262144', 'w+b');
        if ($file === false) {
            throw new RuntimeException('Unable to create plot spool stream');
        }
        $series[] = [
            'pid' => $pid, 'label' => $description . $suffix,
            'type' => $type, 'file' => $file, 'count' => 0,
            'sum' => 0.0, 'min' => null, 'max' => null,
            'spark' => [], 'sparkStride' => 1,
        ];
    }

    // mysqli prepared statements stream rows by default when store_result() /
    // get_result() are not called. In particular DO NOT use fetch_all().
    $query = getFilteredQuery(
        (int)$user_id,
        isset($_GET['last']) ? 'LIMIT 1' : '',
        current_sessions_filter()
    );
    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare plot query');
    }
    try {
        $stmt->bind_param('s', $session_id);
        $stmt->execute();
        $stmt->bind_result($rowTime, $rowJson);
        $jsonOptions = JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
        $rowCount = 0;

        while ($stmt->fetch()) {
            ++$rowCount;
            $data = decode_log_data($rowJson);
            foreach ($series as &$metric) {
                $value = $data[$metric['pid']] ?? 0;
                $value = match ($metric['type']) {
                    'speed' => speed_conv($value, $speed, $id),
                    'temp' => temp_conv($value, $temp, $id),
                    'pressure' => pressure_conv($value, $pressure, $id),
                    'boost' => pressure_conv($value, $boost, $id),
                    'rpm' => $temp_rpm_dev($value),
                    'motorhours' => $tmp_mhs($value),
                    'voltage' => $tmp_vlt($value),
                    'runtime' => $tmp_ert($value),
                    'gear' => $tmp_gear($value),
                    default => $value,
                };

                // Keep the exact Flot data (including order and every point).
                $encodedPoint = json_encode([$rowTime, $value], $jsonOptions);
                if ($encodedPoint === false || fwrite($metric['file'], $encodedPoint . "\n") === false) {
                    throw new RuntimeException('Unable to spool plot data');
                }
                ++$metric['count'];
                $metric['sum'] += (float)$value;
                $metric['min'] = $metric['min'] === null ? $value : min($metric['min'], $value);
                $metric['max'] = $metric['max'] === null ? $value : max($metric['max'], $value);

                // Sparklines are tiny previews: bounded sampling prevents a
                // second full copy of each metric in RAM and in the response.
                if (($metric['count'] - 1) % $metric['sparkStride'] === 0) {
                    $metric['spark'][] = $value;
                    if (count($metric['spark']) > 4096) {
                        $metric['spark'] = array_values(array_filter(
                            $metric['spark'], static fn($unused, $index) => $index % 2 === 0,
                            ARRAY_FILTER_USE_BOTH
                        ));
                        $metric['sparkStride'] *= 2;
                    }
                }
            }
            unset($metric);
            unset($data);
        }
    } finally {
        $stmt->close();
    }

    if ($rowCount === 0) {
        foreach ($series as $metric) fclose($metric['file']);
        echo '[]';
        return;
    }

    // Keep small JSON responses in RAM; spill above 1 MiB to disk.
    // The full response is never materialized as a PHP string/array.
    $response = fopen('php://temp/maxmemory:1048576', 'w+b');
    if ($response === false) {
        throw new RuntimeException('Unable to create plot response stream');
    }
    $append = static function (string $bytes) use ($response): void {
        if (!plotCacheWriteAll($response, $bytes)) {
            throw new RuntimeException('Unable to write plot response');
        }
    };
    $append('[');
    foreach ($series as $index => $metric) {
        if ($index > 0) $append(',');
        $append('[' . json_encode($metric['pid'], $jsonOptions) . ','
            . json_encode($metric['label'], $jsonOptions) . ',[');
        rewind($metric['file']);
        $firstPoint = true;
        while (($line = fgets($metric['file'])) !== false) {
            if (!$firstPoint) $append(',');
            $append(rtrim($line, "\r\n"));
            $firstPoint = false;
        }
        fclose($metric['file']);
        $append('],');
        $append(json_encode(implode(',', array_reverse($metric['spark'])), $jsonOptions));
        $append(',' . json_encode(round((float)$metric['max'], 2), $jsonOptions));
        $append(',' . json_encode(round((float)$metric['min'], 2), $jsonOptions));
        $append(',' . json_encode(round($metric['sum'] / $metric['count'], 2), $jsonOptions) . ']');
    }
    $append(']');

    // Stream requests never populate or read the full-session cache.
    if ($plotCacheKey !== null && $plotCacheLockToken !== null) {
        // Do not publish a cache generated from an obsolete session revision.
        $latestTimestamp = getLastUpdateTimestamp($db, (int)$user_id, $session_id);
        if ($latestTimestamp === $current_timestamp) {
            plotCacheStore($memcached, $plotCacheKey, $response, (int)($db_cache_ttl ?? 3600));
        }
    }
    rewind($response);
    header('Content-Type: application/json; charset=utf-8');
    fpassthru($response);
    fclose($response);
    } finally {
        plotCacheUnlock($plotCacheRedis, (string)$plotCacheKey, $plotCacheLockToken);
    }
}

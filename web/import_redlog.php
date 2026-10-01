<?php
const REDLOG_HEADER = "TIME ECT EOT IAT ATF AAT EXT SPD RPM MAP MAF TPS IGN INJ INJD IAC AFR O2S O2S2 EGT EOP FP ERT MHS BSTD FAN GEAR BS1 BS2 PG0 PG1 VLT RLC GLAT GLON GSPD ODO\n";

try {
if (!$_COOKIE['stream']) {
 http_response_code(401);
 die;
}
require_once __DIR__ . '/src/db.php';
include_once __DIR__ . '/timezone.php';
include_once __DIR__ . '/translations.php';
require_once __DIR__ . '/src/methods.php';
allowMethods('POST');

if (isset($_SESSION['admin'])) header("Refresh:0; url=.");

$session_count = (int)$db->execute_query(
    "SELECT COUNT(*) FROM sessions WHERE user_id = ?",
    [$user_id]
)->fetch_row()[0];

$ok = 0;
$files = [];

//Exceed php_post_size
if (!isset($_FILES['file'])) {
 http_response_code(406);
 die($translations[$_COOKIE['lang']]['redlog.post.max']);
}

//Convert to simply array
foreach($_FILES['file'] as $k => $l) {
    foreach($l as $i => $v) {
    $files[$i][$k] = $v;
    }
}

if(count($files) > 10) {
  http_response_code(406);
  echo $translations[$_COOKIE['lang']]['redlog.warn.count'];
  die;
}

$target_file = [];
for ($f = 0; $f < count($files); $f++) {
 $tmp_dir = sys_get_temp_dir();
 $target_file[$f] = tempnam($tmp_dir, 'upload_');
 if (!$target_file[$f]) {
  http_response_code(500);
  error_log("Error creating temporary file.");
  die($translations[$_COOKIE['lang']]['redlog.err']);
 }

 if (!move_uploaded_file($files[$f]['tmp_name'], $target_file[$f]) ) {
  http_response_code(406);
  die($translations[$_COOKIE['lang']]['redlog.err']);
 }

 $data = file_get_contents($target_file[$f]);
 $data_size = filesize($target_file[$f])/1048576;

 if ($data_size > 15) {
  if (file_exists($target_file[$f])) unlink($target_file[$f]);
  http_response_code(406);
  echo htmlspecialchars($files[$f]['name']) . " " . $translations[$_COOKIE['lang']]['redlog.warn.size'];
  die;
 }
 if ($limit != -1 && $session_count >= $limit) {
  if (file_exists($target_file[$f])) unlink($target_file[$f]);
  http_response_code(507);
  die($translations[$_COOKIE['lang']]['redlog.nospace']);
 }
 elseif (!$data || !$data_size || strpos($data, REDLOG_HEADER) !== 0) {
  if (file_exists($target_file[$f])) unlink($target_file[$f]);
  http_response_code(406);
  echo htmlspecialchars($files[$f]['name']) . " " . $translations[$_COOKIE['lang']]['redlog.broken'];
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
  http_response_code(406);
  echo htmlspecialchars($files[$f]['name']) . " " . $translations[$_COOKIE['lang']]['redlog.dup'];
  die;
 }

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
 } catch (Exception $e) {
    $db->rollBack();
    http_response_code(406);
    $file = htmlspecialchars($files[$f]['name']);

    if (str_contains($e->getMessage(), 'Duplicate')) {
        echo $file . " " . $translations[$_COOKIE['lang']]['redlog.dup'];
    } else {
        echo $file . " " . $translations[$_COOKIE['lang']]['redlog.broken'];
    }

    $db->execute_query("DELETE FROM logs     WHERE user_id = ? AND session = ?", [$user_id, $session]);
    $db->execute_query("DELETE FROM sessions WHERE user_id = ? AND session = ?", [$user_id, $session]);
    die;
 }
 $session_count++;
 $ok++;
 unlink($target_file[$f]);
}

cache_flush();

$word = getPluralForm($ok, $translations[$_COOKIE['lang']]['redlog.upload.ok']);
echo "$ok $word [RedManage]";

$db->close();

} catch (TypeError $e) {
    http_response_code(406);
    echo htmlspecialchars($files[$f]['name'] ?? '?') . " " . $translations[$_COOKIE['lang']]['redlog.broken'];
    $db->execute_query("DELETE FROM logs     WHERE user_id = ? AND session = ?", [$user_id, $session]);
    $db->execute_query("DELETE FROM sessions WHERE user_id = ? AND session = ?", [$user_id, $session]);
    die;
}

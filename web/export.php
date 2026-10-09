<?php
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/creds.php';
require_once __DIR__ . '/src/auth_functions.php';
require_once __DIR__ . '/src/auth_user.php';
require_once __DIR__ . '/src/helpers.php';

if (!isset($username) || $username == $admin) {
    header('Location: /');
    die;
}

$user_id  = current_user_id();
$cut_start = filter_input(INPUT_GET, 'cutstart', FILTER_SANITIZE_NUMBER_INT);
$cut_end   = filter_input(INPUT_GET, 'cutend',   FILTER_SANITIZE_NUMBER_INT);

$is_cut = ($cut_start !== null && $cut_start !== false && $cut_start !== ''
        && $cut_end   !== null && $cut_end   !== false && $cut_end   !== '');

if (empty($_GET["sid"])) {
    header('Location: /');
    $db->close();
    exit;
}

$session_id = preg_replace('/[^0-9]/', '', $_GET['sid'] ?? '');
$filetype   = $_GET["filetype"] ?? '';

if ($session_id === '') {
    header('Location: /');
    $db->close();
    exit;
}

// Streaming
while (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);
set_time_limit(0);
ini_set('zlib.output_compression', 'Off');
header('X-Accel-Buffering: no');

/* ─── Общий список PID'ов для CSV/JSON ─── */

$pids_list = [];
$pids_q = $db->execute_query(
    "SELECT id FROM pids WHERE user_id = ? ORDER BY description ASC",
    [$user_id]
);
while ($row = $pids_q->fetch_assoc()) $pids_list[] = $row['id'];

/* ─── KML ─── */

if ($filetype == "kml") {
    $query  = "SELECT time, data FROM logs WHERE user_id = ? AND session = ?";
    $params = [$user_id, $session_id];
    $types  = "is";

    if ($is_cut) {
        $query   .= " AND time BETWEEN ? AND ?";
        $params[] = $cut_start;
        $params[] = $cut_end;
        $types   .= "ii";
    }
    $query .= " ORDER BY time DESC";

    $stmt = $db->prepare($query);
    if (!$stmt) { http_response_code(500); die("DB error: " . $db->error); }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($time, $data);

    $filename = "log_session_" . $session_id . ($is_cut ? "_cut" : "") . ".kml";
    header('Content-type: application/kml');
    header('Content-Disposition: attachment; filename=' . $filename);

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<kml>\n<Placemark>\n";
    echo "<name>RedBox Telemetry Tracklog</name>\n<LineString>\n";
    echo "<extrude>1</extrude>\n<tessellate>1</tessellate>\n<coordinates>\n";
    flush();

    while ($stmt->fetch()) {
        $d = decode_log_data($data);
        $lon = $d['kff1005'] ?? 0;
        $lat = $d['kff1006'] ?? 0;
        $alt = $d['kff1007'] ?? 0;
        if ($lon == 0 || $lat == 0) continue;
        echo "$lon,$lat,$alt\n";
        flush();
    }

    echo "</coordinates>\n</LineString>\n</Placemark>\n</kml>\n";
    flush();
    $stmt->close();
    $db->close();
    exit;
}

/* ─── CSV ─── */

if ($filetype == "csv") {
    $query  = "SELECT time, data FROM logs WHERE user_id = ? AND session = ?";
    $params = [$user_id, $session_id];
    $types  = "is";

    if ($is_cut) {
        $query   .= " AND time BETWEEN ? AND ?";
        $params[] = $cut_start;
        $params[] = $cut_end;
        $types   .= "ii";
    }
    $query .= " ORDER BY time ASC";

    $stmt = $db->prepare($query);
    if (!$stmt) { http_response_code(500); die("DB error: " . $db->error); }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($time, $data);

    $filename = "log_session_" . $session_id . ($is_cut ? "_cut" : "") . ".csv";
    header('Content-type: application/csv');
    header('Content-Disposition: attachment; filename=' . $filename);

    // Header
    $out = fopen('php://output', 'w');
    $header = ['session', 'time'];
    foreach ($pids_list as $pid) $header[] = $pid;
    fputcsv($out, $header, ',', '"', '\\');

    while ($stmt->fetch()) {
        $d = decode_log_data($data);
        $row = [$session_id, $time];
        foreach ($pids_list as $pid) {
            $row[] = $d[$pid] ?? 0;
        }
        fputcsv($out, $row, ',', '"', '\\');
        flush();
    }

    fclose($out);
    $stmt->close();
    $db->close();
    exit;
}

/* ─── RBX ─── */

if ($filetype == "rbx") {
    $query  = "SELECT time, data FROM logs WHERE user_id = ? AND session = ?";
    $params = [$user_id, $session_id];
    $types  = "is";

    if ($is_cut) {
        $query   .= " AND time BETWEEN ? AND ?";
        $params[] = $cut_start;
        $params[] = $cut_end;
        $types   .= "ii";
    }
    $query .= " ORDER BY time ASC";

    $stmt = $db->prepare($query);
    if (!$stmt) { http_response_code(500); die("DB error: " . $db->error); }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($time, $data);

    $rbxFields = ['time','k5','k5c','kf','kb4','k46','k2101','kd','kc','kb',
                  'k10','k11','ke','k2112','k2100','k2113','k21cc','kff1214',
                  'kff1218','k78','k2111','k2119','k1f','k2118','k2120',
                  'k2122','k2124','k21e1','k21e2','k2125','k2126','kff1238',
                  'k21fa','kff1006','kff1005','kff1001','kff120c'];

    $filename = "rbx_log_" . $session_id . ($is_cut ? "_cut" : "") . ".txt";
    header('Content-Type: application/txt');
    header('Content-Disposition: attachment; filename=' . $filename);

    $first = true;
    while ($stmt->fetch()) {
        $d = decode_log_data($data);
        $d['time'] = $time;
        if ($first) {
            echo "TIME ECT EOT IAT ATF AAT EXT SPD RPM MAP MAF TPS IGN INJ INJD IAC AFR O2S O2S2 EGT EOP FP ERT MHS BSTD FAN GEAR BS1 BS2 PG0 PG1 VLT RLC GLAT GLON GSPD ODO\n";
            flush();
            $first = false;
        }
        $row = [];
        foreach ($rbxFields as $f) $row[] = $d[$f] ?? 0;
        echo implode(' ', $row) . "\n";
        flush();
    }

    if ($first) echo "This is not RedManage session";

    $stmt->close();
    $db->close();
    exit;
}

/* ─── JSON ─── */

if ($filetype == "json") {
    $query  = "SELECT time, data FROM logs WHERE user_id = ? AND session = ?";
    $params = [$user_id, $session_id];
    $types  = "is";

    if ($is_cut) {
        $query   .= " AND time BETWEEN ? AND ?";
        $params[] = $cut_start;
        $params[] = $cut_end;
        $types   .= "ii";
    }
    $query .= " ORDER BY time ASC";

    $stmt = $db->prepare($query);
    if (!$stmt) { http_response_code(500); die("DB error: " . $db->error); }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($time, $data);

    $filename = "log_session_" . $session_id . ($is_cut ? "_cut" : "") . ".json";
    header('Content-type: application/json');
    header('Content-Disposition: attachment; filename=' . $filename);

    echo '[';
    flush();
    $first = true;

    while ($stmt->fetch()) {
        $d = decode_log_data($data);
        $d['time'] = $time;
        if (!$first) echo ',';
        echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        flush();
        $first = false;
    }

    echo ']';
    flush();

    $stmt->close();
    $db->close();
    exit;
}

header('Location: /');
$db->close();

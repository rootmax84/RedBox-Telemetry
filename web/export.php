<?php
require_once 'db.php';
require_once 'creds.php';
require_once 'auth_functions.php';
require_once 'auth_user.php';

if(!isset($username) || $username == $admin){
    header("Location: .");
    die;
}

$cut_start = filter_input(INPUT_GET, 'cutstart', FILTER_SANITIZE_NUMBER_INT);
$cut_end = filter_input(INPUT_GET, 'cutend', FILTER_SANITIZE_NUMBER_INT);

$is_cut = ($cut_start !== null && $cut_start !== false && $cut_start !== '' && 
          $cut_end !== null && $cut_end !== false && $cut_end !== '');

if (isset($_GET["sid"]) && $_GET["sid"]) {
    $session_id = $_GET['sid'];

    // Streaming transfer settings
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_implicit_flush(true);
    set_time_limit(0);
    ini_set('zlib.output_compression', 'Off');
    header('X-Accel-Buffering: no');

    // KML
    if ($_GET["filetype"] == "kml") {
        $query = "SELECT kff1005, kff1006, kff1007
                  FROM $db_table
                  JOIN $db_sessions_table ON $db_table.session = $db_sessions_table.session
                  WHERE $db_table.session = ? AND kff1005 > 0";

        $params = [$session_id];
        $types  = 's';

        if ($is_cut) {
            $query .= " AND $db_table.time BETWEEN ? AND ?";
            $params[] = $cut_start;
            $params[] = $cut_end;
            $types   .= 'ss';
        }
        $query .= " ORDER BY $db_table.time DESC";

        $stmt = $db->prepare($query);
        if (!$stmt) {
            http_response_code(500);
            die("DB error: " . $db->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $lon = $lat = $alt = null;
        $stmt->bind_result($lon, $lat, $alt);

        $filename = "log_session_" . $session_id . ($is_cut ? "_cut" : "") . ".kml";
        header('Content-type: application/kml');
        header('Content-Disposition: attachment; filename=' . $filename);

        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<kml>\n";
        echo "<Placemark>\n";
        echo "<name>RedBox Telemetry Tracklog</name>\n";
        echo "<LineString>\n";
        echo "<extrude>1</extrude>\n";
        echo "<tessellate>1</tessellate>\n";
        echo "<coordinates>\n";
        flush();

        while ($stmt->fetch()) {
            echo "$lon,$lat,$alt\n";
            flush();
        }

        echo "</coordinates>\n";
        echo "</LineString>\n";
        echo "</Placemark>\n";
        echo "</kml>\n";
        flush();

        $stmt->close();
        $db->close();
        exit;
    }

    // CSV
    elseif ($_GET["filetype"] == "csv") {
        $query = "SELECT * FROM $db_table WHERE session = ?";
        $params = [$session_id];
        $types  = 's';

        if ($is_cut) {
            $query .= " AND time BETWEEN ? AND ?";
            $params[] = $cut_start;
            $params[] = $cut_end;
            $types   .= 'ss';
        }
        $query .= " ORDER BY time ASC";

        $stmt = $db->prepare($query);
        if (!$stmt) {
            http_response_code(500);
            die("DB error: " . $db->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $meta = $stmt->result_metadata();
        $fields = [];
        $rowVars = [];
        $bindArgs = [];
        while ($field = $meta->fetch_field()) {
            $fields[] = $field->name;
            $rowVars[$field->name] = null;
            $bindArgs[] = &$rowVars[$field->name];
        }
        $meta->close();
        $stmt->bind_result(...$bindArgs);

        $filename = "log_session_" . $session_id . ($is_cut ? "_cut" : "") . ".csv";
        header('Content-type: application/csv');
        header('Content-Disposition: attachment; filename=' . $filename);

        $headerLine = '"' . implode('","', $fields) . '",' . "\n";
        echo $headerLine;
        flush();

        while ($stmt->fetch()) {
            $csvLine = '"' . implode('","', $rowVars) . '",' . "\n";
            echo $csvLine;
            flush();
        }

        $stmt->close();
        $db->close();
        exit;
    }

    // RBX
    elseif ($_GET["filetype"] == "rbx") {
        $query = "SELECT $db_table.time, k5, k5c, kf, kb4, k46, k2101, kd, kc, kb, k10, k11,
                         ke, k2112, k2100, k2113, k21cc, kff1214, kff1218, k78,
                         k2111, k2119, k1f, k2118, k2120, k2122, k2124, k21e1, k21e2,
                         k2125, k2126, kff1238, k21fa, kff1006, kff1005, kff1001, kff120c
                  FROM $db_table
                  JOIN $db_sessions_table ON $db_table.session = $db_sessions_table.session
                  WHERE $db_table.session = ? AND $db_sessions_table.id = ?";

        $params = [$session_id, "RedManage"];
        $types  = 'ss';

        if ($is_cut) {
            $query .= " AND $db_table.time BETWEEN ? AND ?";
            $params[] = $cut_start;
            $params[] = $cut_end;
            $types   .= 'ss';
        }
        $query .= " ORDER BY $db_table.time ASC";

        $stmt = $db->prepare($query);
        if (!$stmt) {
            http_response_code(500);
            die("DB error: " . $db->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $rbxFields = ['time','k5','k5c','kf','kb4','k46','k2101','kd','kc','kb',
                      'k10','k11','ke','k2112','k2100','k2113','k21cc','kff1214',
                      'kff1218','k78','k2111','k2119','k1f','k2118','k2120',
                      'k2122','k2124','k21e1','k21e2','k2125','k2126','kff1238',
                      'k21fa','kff1006','kff1005','kff1001','kff120c'];
        $rbxVars = [];
        $rbxRefs = [];
        foreach ($rbxFields as $f) {
            $rbxVars[$f] = null;
            $rbxRefs[] = &$rbxVars[$f];
        }
        $stmt->bind_result(...$rbxRefs);

        $filename = "rbx_log_" . $session_id . ($is_cut ? "_cut" : "") . ".txt";
        header('Content-Type: application/txt');
        header('Content-Disposition: attachment; filename=' . $filename);

        if ($stmt->fetch()) {
            echo "TIME ECT EOT IAT ATF AAT EXT SPD RPM MAP MAF TPS IGN INJ INJD IAC AFR O2S O2S2 EGT EOP FP ERT MHS BSTD FAN GEAR BS1 BS2 PG0 PG1 VLT RLC GLAT GLON GSPD ODO\n";
            flush();

            echo implode(' ', $rbxVars) . "\n";
            flush();

            while ($stmt->fetch()) {
                echo implode(' ', $rbxVars) . "\n";
                flush();
            }
        } else {
            echo "This is not RedManage session";
            flush();
        }

        $stmt->close();
        $db->close();
        exit;
    }

    // JSON
    elseif ($_GET["filetype"] == "json") {
        $query = "SELECT * FROM $db_table WHERE session = ?";
        $params = [$session_id];
        $types  = 's';

        if ($is_cut) {
            $query .= " AND time BETWEEN ? AND ?";
            $params[] = $cut_start;
            $params[] = $cut_end;
            $types   .= 'ss';
        }
        $query .= " ORDER BY time ASC";

        $stmt = $db->prepare($query);
        if (!$stmt) {
            http_response_code(500);
            die("DB error: " . $db->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $meta = $stmt->result_metadata();
        $fields = [];
        $rowVars = [];
        $bindArgs = [];
        while ($field = $meta->fetch_field()) {
            $fields[] = $field->name;
            $rowVars[$field->name] = null;
            $bindArgs[] = &$rowVars[$field->name];
        }
        $meta->close();
        $stmt->bind_result(...$bindArgs);

        $filename = "log_session_" . $session_id . ($is_cut ? "_cut" : "") . ".json";
        header('Content-type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);

        echo '[';
        flush();
        $first = true;

        while ($stmt->fetch()) {
            if (!$first) {
                echo ',';
            }
            echo json_encode($rowVars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            flush();
            $first = false;
        }

        echo ']';
        flush();

        $stmt->close();
        $db->close();
        exit;
    }
}

header('Location: .');
$db->close();

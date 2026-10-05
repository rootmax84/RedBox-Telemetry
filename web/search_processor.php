<?php
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/db_limits.php';
require_once __DIR__ . '/src/helpers.php';
include_once __DIR__ . '/translations.php';
require_once __DIR__ . '/src/methods.php';
allowMethods('POST');

header('Content-Type: application/json');

$lang = $_COOKIE['lang'] ?? 'en';
if (!isset($translations[$lang])) {
    $lang = 'en';
}

$pid      = $_POST['pid']      ?? '';
$operator = $_POST['operator'] ?? '=';
$value    = $_POST['value']    ?? '';
$range    = $_POST['range']    ?? 'day';
$perPage  = 50;

/* ─── Keyset-пагинация: последний показанный session ─── */
$lastSession = isset($_POST['last_session']) ? (int)$_POST['last_session'] : 0;
if ($lastSession < 0) {
    $lastSession = 0;
}

/* ─── Диапазон дат ─── */
$rangeMap = [
    'day'   => 86400,
    'month' => 2592000,
];

$timeFrom = null;
$timeTo   = null;

if (is_string($range) && preg_match('/^(\d{4})$/', $range, $m)) {
    $year     = (int)$m[1];
    $timeFrom = gmmktime(0, 0, 0, 1, 1, $year) * 1000;
    $timeTo   = gmmktime(23, 59, 59, 12, 31, $year) * 1000;
} elseif (array_key_exists($range, $rangeMap)) {
    $timeFrom = (time() - $rangeMap[$range]) * 1000;
} else {
    $range    = 'month';
    $timeFrom = (time() - $rangeMap[$range]) * 1000;
}

// Для day/month верхняя граница пользователем не задаётся ($timeTo === null).
// Чтобы не дублировать SQL двумя ветками, подставляем заведомо большое
// значение — оно всегда истинно и не влияет на выборку.
$timeToBound = $timeTo ?? 9999999999999;

$user_id = current_user_id();

if ($user_id === null) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}

/* ─── Валидация PID + units ─── */
$checkStmt = $db->prepare("SELECT units FROM pids WHERE user_id = ? AND id = ? LIMIT 1");
if ($checkStmt === false) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$checkStmt->bind_param('is', $user_id, $pid);
$checkStmt->execute();
$pidRow = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($pidRow === null) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$pidUnits = $pidRow['units'] ?? '';

/* ─── Настройки конвертации пользователя ─── */
$setStmt = $db->prepare(
    "SELECT pressure, boost, temp, speed FROM users WHERE id = ? LIMIT 1"
);
if ($setStmt === false) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$setStmt->bind_param('i', $user_id);
$setStmt->execute();
$userSettings = $setStmt->get_result()->fetch_assoc() ?: [];
$setStmt->close();

$boostSetting    = $userSettings['boost']    ?? 'No conversion';
$pressureSetting = $userSettings['pressure'] ?? 'No conversion';
$tempSetting     = $userSettings['temp']     ?? 'No conversion';
$speedSetting    = $userSettings['speed']    ?? 'No conversion';

/* ─── Валидация оператора ─── */

$operatorMap = ['=' => '=', '>' => '>', '<' => '<', '>=' => '>=', '<=' => '<='];
if (!isset($operatorMap[$operator])) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$operator = $operatorMap[$operator];

if (!is_numeric($value)) {
    echo json_encode(['error' => $translations[$lang]['search.error_invalid_value']]);
    exit;
}
$valueFloat = (float)$value;

/* ────────────────────────────────────────────────────────────────
 * Определяем, какая конвертация применима к выбранному PID.
 * Логика зеркалит helpers.php: pressure_conv / temp_conv / speed_conv.
 * kff1202 (Boost) использует users.boost, остальные давления —
 * users.pressure.
 * ──────────────────────────────────────────────────────────────── */
if ($pid === 'kff1202') {
    $convSetting = $boostSetting;
    $convType    = 'pressure';
} elseif (in_array($pidUnits, ['Bar', 'PSI', 'Psi', 'psi', 'bar'], true)) {
    $convSetting = $pressureSetting;
    $convType    = 'pressure';
} elseif (in_array($pidUnits, ['°C', '°F', 'C', 'F'], true)) {
    $convSetting = $tempSetting;
    $convType    = 'temp';
} elseif (in_array($pidUnits, ['km/h', 'mph'], true)) {
    $convSetting = $speedSetting;
    $convType    = 'speed';
} else {
    $convSetting = 'No conversion';
    $convType    = null;
}

/* ────────────────────────────────────────────────────────────────
 * SQL-выражения: сырое значение + два варианта конвертации.
 *
 * $rmExpr — для сессий, у которых sessions.id = 'RedManage';
 * $otExpr — для всех остальных (Torque, живой стрим и т.п.).
 *
 * ВНИМАНИЕ: поведение зеркалит helpers.php, включая тот факт, что
 * ветки «Bar to Psi», «Celsius to Fahrenheit», «km to miles»
 * применяются безусловно (без проверки $id).
 * ──────────────────────────────────────────────────────────────── */
$rawExpr = "CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$pid}')) AS DECIMAL(20,6))";
$rmExpr  = $rawExpr;
$otExpr  = $rawExpr;

switch ($convType) {
    case 'pressure':
        switch ($convSetting) {
            case 'Psi to Bar':
                // id !== 'RedManage' → /14.504
                $otExpr = "($rawExpr / 14.504)";
                break;
            case 'Bar to Psi':
                $rmExpr = "($rawExpr * 14.504)";
                $otExpr = "($rawExpr * 14.504)";
                break;
        }
        break;

    case 'temp':
        switch ($convSetting) {
            case 'Celsius to Fahrenheit':
                $rmExpr = "($rawExpr * 9.0 / 5.0 + 32.0)";
                $otExpr = $rmExpr;
                break;
            case 'Fahrenheit to Celsius':
                // id !== 'RedManage' → (v - 32) * 5 / 9
                $otExpr = "(($rawExpr - 32.0) * 5.0 / 9.0)";
                break;
        }
        break;

    case 'speed':
        switch ($convSetting) {
            case 'km to miles':
                $rmExpr = "($rawExpr * 0.621371)";
                $otExpr = $rmExpr;
                break;
            case 'miles to km':
                // id !== 'RedManage' → * 1.609344
                $otExpr = "($rawExpr * 1.609344)";
                break;
        }
        break;
}

$hasConversion = ($convType !== null) && ($convSetting !== 'No conversion');

/* ────────────────────────────────────────────────────────────────
   Шаг 1. Кандидаты из sessions
   При keyset добавляем  AND session < lastSession
   ──────────────────────────────────────────────────────────────── */

if ($lastSession > 0) {
    $sqlCandidates = "SELECT session, time, timeend, profileName, sessionsize, id
                      FROM sessions
                      WHERE user_id = ?
                        AND session < ?
                        AND time <= ?
                        AND (timeend >= ? OR timeend IS NULL OR timeend = 0)
                      ORDER BY session DESC";
} else {
    $sqlCandidates = "SELECT session, time, timeend, profileName, sessionsize, id
                      FROM sessions
                      WHERE user_id = ?
                        AND time <= ?
                        AND (timeend >= ? OR timeend IS NULL OR timeend = 0)
                      ORDER BY session DESC";
}

$stmtCand = $db->prepare($sqlCandidates);
if ($stmtCand === false) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}

if ($lastSession > 0) {
    $stmtCand->bind_param('iiii', $user_id, $lastSession, $timeToBound, $timeFrom);
} else {
    $stmtCand->bind_param('iii', $user_id, $timeToBound, $timeFrom);
}

if (!$stmtCand->execute()) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$resultCand = $stmtCand->get_result();
$candidates = [];
while ($row = $resultCand->fetch_assoc()) {
    $candidates[] = $row;
}
$stmtCand->close();

if (empty($candidates)) {
    echo json_encode(['data' => [], 'total' => 0, 'hasMore' => false]);
    exit;
}

/* ────────────────────────────────────────────────────────────────
   Шаг 2. Батч-проверка в logs + ранний выход
   Останавливаемся, как только набрали perPage + 1 совпадение.
   ──────────────────────────────────────────────────────────────── */

$needUntil = $perPage + 1;
$matched   = [];
$batchSize = 100;
$nCand     = count($candidates);

for ($i = 0; $i < $nCand; $i += $batchSize) {
    $batch = array_slice($candidates, $i, $batchSize);
    $ids   = [];
    $rmIds = [];

    foreach ($batch as $b) {
        $sid = (int)$b['session'];
        $ids[] = $sid;
        if (($b['id'] ?? '') === 'RedManage') {
            $rmIds[] = $sid;
        }
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    if ($hasConversion) {
        // CASE WHEN session ∈ RedManage-набор → $rmExpr, иначе → $otExpr
        $convertedExpr = "CASE WHEN FIND_IN_SET(session, ?) > 0 "
                       . "THEN {$rmExpr} ELSE {$otExpr} END";
        $rmCsv = empty($rmIds) ? '0' : implode(',', $rmIds);

        $sql = "SELECT DISTINCT session
                FROM logs
                WHERE user_id = ?
                  AND session IN ($placeholders)
                  AND time >= ?
                  AND time <= ?
                  AND {$convertedExpr} $operator ?";

        $types  = 'i' . str_repeat('i', count($ids)) . 'iisd';
        $params = array_merge(
            [$user_id],
            $ids,
            [$timeFrom, $timeToBound, $rmCsv, $valueFloat]
        );
    } else {
        $sql = "SELECT DISTINCT session
                FROM logs
                WHERE user_id = ?
                  AND session IN ($placeholders)
                  AND time >= ?
                  AND time <= ?
                  AND {$rawExpr} $operator ?";

        $types  = 'i' . str_repeat('i', count($ids)) . 'iid';
        $params = array_merge(
            [$user_id],
            $ids,
            [$timeFrom, $timeToBound, $valueFloat]
        );
    }

    try {
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new mysqli_sql_exception($db->error, $db->errno);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $matched[(int)$row['session']] = true;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        if (in_array((int)$e->getCode(), [1054, 1064, 3141], true)) {
            echo json_encode(['data' => [], 'total' => 0, 'hasMore' => false]);
            exit;
        }
        echo json_encode(['error' => $translations[$lang]['search.error_query']]);
        exit;
    }

    // Ранний выход: уже набрали всё, что нужно для страницы
    if (count($matched) >= $needUntil) {
        break;
    }
}

/* ────────────────────────────────────────────────────────────────
   Шаг 3. Собираем страницу в исходном порядке (session DESC)
   ──────────────────────────────────────────────────────────────── */

$pageSessions = [];
foreach ($candidates as $cand) {
    if (isset($matched[(int)$cand['session']])) {
        $pageSessions[] = $cand;
        if (count($pageSessions) >= $needUntil) {
            break;
        }
    }
}

if (empty($pageSessions)) {
    echo json_encode(['data' => [], 'total' => 0, 'hasMore' => false]);
    exit;
}

$hasMore = count($pageSessions) > $perPage;
if ($hasMore) {
    array_pop($pageSessions); // убираем "лишний" пробный элемент
}

$data = [];
foreach ($pageSessions as $row) {
    $data[] = [
        'session'     => $row['session'],
        'time'        => $row['time'],
        'timeend'     => $row['timeend'],
        'profileName' => $row['profileName'],
        'sessionsize' => $row['sessionsize'],
        'id'          => $row['id'] ?? '',
    ];
}

echo json_encode([
    'data'    => $data,
    'total'   => count($data),
    'hasMore' => $hasMore,
]);

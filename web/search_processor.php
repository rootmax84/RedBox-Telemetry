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
$range    = $_POST['range']    ?? 'month';
$page     = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$perPage  = 50;

/* ─── Диапазон дат ─── */
$rangeMap = [
    'day'   => 86400,      // 24 часа
    'month' => 2592000,    // 30 дней
    'year'  => 31536000,   // 365 дней
    'all'   => null,
];
if (!array_key_exists($range, $rangeMap)) {
    $range = 'month';
}
$rangeSeconds = $rangeMap[$range];

// time в logs хранится в миллисекундах
$timeFrom = $rangeSeconds === null ? 0 : (time() - $rangeSeconds) * 1000;

$user_id = current_user_id();

if ($user_id === null) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}

/* ─── Валидация PID ─── */

$checkStmt = $db->prepare("SELECT id FROM pids WHERE user_id = ? AND id = ? LIMIT 1");
if ($checkStmt === false) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$checkStmt->bind_param('is', $user_id, $pid);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows === 0) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$checkStmt->close();

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

$page   = max(1, $page);
$offset = ($page - 1) * $perPage;

/* ─── COUNT ─── */

$countSql = "SELECT COUNT(DISTINCT session) AS total
             FROM logs
             WHERE user_id = ?
               AND time >= ?
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$pid}')) AS DECIMAL(20,6)) $operator ?";

try {
    $countStmt = $db->prepare($countSql);
    if ($countStmt === false) {
        throw new mysqli_sql_exception($db->error, $db->errno);
    }
    $countStmt->bind_param('iid', $user_id, $timeFrom, $valueFloat);
    $countStmt->execute();
} catch (mysqli_sql_exception $e) {
    // Unknown column / invalid JSON path
    if (in_array((int)$e->getCode(), [1054, 1064, 3141], true)) {
        echo json_encode([
            'data'    => [],
            'total'   => 0,
            'hasMore' => false,
            'page'    => $page,
        ]);
    } else {
        echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    }
    exit;
}

$countResult = $countStmt->get_result();
$totalRow    = $countResult->fetch_assoc();
$total       = (int)$totalRow['total'];
$countStmt->close();

if ($total === 0) {
    echo json_encode(['data' => [], 'total' => 0, 'hasMore' => false, 'page' => $page]);
    exit;
}

/* ─── Session IDs ─── */

$sqlSessions = "SELECT DISTINCT session
                FROM logs
                WHERE user_id = ?
                  AND time >= ?
                  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$pid}')) AS DECIMAL(20,6)) $operator ?
                LIMIT ? OFFSET ?";

$stmtSessions = $db->prepare($sqlSessions);
if ($stmtSessions === false) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$stmtSessions->bind_param('iidii', $user_id, $timeFrom, $valueFloat, $perPage, $offset);
if (!$stmtSessions->execute()) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$resultSessions = $stmtSessions->get_result();
$sessionIds = [];
while ($row = $resultSessions->fetch_assoc()) {
    $sessionIds[] = $row['session'];
}
$stmtSessions->close();

if (empty($sessionIds)) {
    echo json_encode(['data' => [], 'total' => $total, 'hasMore' => false, 'page' => $page]);
    exit;
}

/* ─── Данные сессий ─── */

$placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
$types        = 'i' . str_repeat('i', count($sessionIds));

$sqlData = "SELECT session, time, timeend, profileName, sessionsize
            FROM sessions
            WHERE user_id = ? AND session IN ($placeholders)
            ORDER BY session DESC";

$stmtData = $db->prepare($sqlData);
if ($stmtData === false) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}

$bindParams = array_merge([$user_id], $sessionIds);
$stmtData->bind_param($types, ...$bindParams);

if (!$stmtData->execute()) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$resultData = $stmtData->get_result();
$data = [];
while ($row = $resultData->fetch_assoc()) {
    $data[] = $row;
}
$stmtData->close();

$hasMore = ($page * $perPage) < $total;

echo json_encode([
    'data'    => $data,
    'total'   => $total,
    'hasMore' => $hasMore,
    'page'    => $page,
]);

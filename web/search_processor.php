<?php
require_once 'db.php';
require_once 'db_limits.php';
include_once 'translations.php';

header('Content-Type: application/json');

$lang = $_COOKIE['lang'] ?? 'en';
if (!isset($translations[$lang])) {
    $lang = 'en';
}

$pid = $_POST['pid'] ?? '';
$operator = $_POST['operator'] ?? '=';
$value = $_POST['value'] ?? '';
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$perPage = 50;

$checkStmt = $db->prepare("SELECT id FROM $db_pids_table WHERE id = ? LIMIT 1");
if (!$checkStmt) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$checkStmt->bind_param('s', $pid);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows === 0) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$checkStmt->close();

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

$page = max(1, $page);
$offset = ($page - 1) * $perPage;

$column = "`" . $pid . "`";

$countSql = "SELECT COUNT(DISTINCT session) AS total FROM $db_table WHERE $column $operator ?";
$countStmt = $db->prepare($countSql);
if (!$countStmt) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$countStmt->bind_param('d', $valueFloat);
if (!$countStmt->execute()) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$countResult = $countStmt->get_result();
$totalRow = $countResult->fetch_assoc();
$total = (int)$totalRow['total'];
$countStmt->close();

if ($total === 0) {
    echo json_encode(['data' => [], 'total' => 0, 'hasMore' => false, 'page' => $page]);
    exit;
}

$sqlSessions = "SELECT DISTINCT session FROM $db_table WHERE $column $operator ? LIMIT ? OFFSET ?";
$stmtSessions = $db->prepare($sqlSessions);
if (!$stmtSessions) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$stmtSessions->bind_param('dii', $valueFloat, $perPage, $offset);
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

$placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
$types = str_repeat('s', count($sessionIds));
$sqlData = "SELECT session, time, timeend, profileName, sessionsize
            FROM $db_sessions_table
            WHERE session IN ($placeholders)
            ORDER BY session DESC";

$stmtData = $db->prepare($sqlData);
if (!$stmtData) {
    echo json_encode(['error' => $translations[$lang]['search.error_query']]);
    exit;
}
$stmtData->bind_param($types, ...$sessionIds);
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
    'data' => $data,
    'total' => $total,
    'hasMore' => $hasMore,
    'page' => $page
]);

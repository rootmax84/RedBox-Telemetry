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

/* ────────────────────────────────────────────────────────────────
   Шаг 1. Кандидаты из sessions
   При keyset добавляем  AND session < lastSession
   ──────────────────────────────────────────────────────────────── */

if ($lastSession > 0) {
    $sqlCandidates = "SELECT session, time, timeend, profileName, sessionsize
                      FROM sessions
                      WHERE user_id = ?
                        AND session < ?
                        AND time <= ?
                        AND (timeend >= ? OR timeend IS NULL OR timeend = 0)
                      ORDER BY session DESC";
} else {
    $sqlCandidates = "SELECT session, time, timeend, profileName, sessionsize
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
    foreach ($batch as $b) {
        $ids[] = (int)$b['session'];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT DISTINCT session
            FROM logs
            WHERE user_id = ?
              AND session IN ($placeholders)
              AND time >= ?
              AND time <= ?
              AND CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$pid}')) AS DECIMAL(20,6)) $operator ?";

    $types  = 'i' . str_repeat('i', count($ids)) . 'iid';
    $params = array_merge([$user_id], $ids, [$timeFrom, $timeToBound, $valueFloat]);

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
    ];
}

echo json_encode([
    'data'    => $data,
    'total'   => count($data),
    'hasMore' => $hasMore,
]);

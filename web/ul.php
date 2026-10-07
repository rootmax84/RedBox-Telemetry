<?php
require_once __DIR__ . '/src/helpers.php';
include_once __DIR__ . '/translations.php';
require_once __DIR__ . '/src/methods.php';
require_once __DIR__ . '/src/redis.php';
require_once __DIR__ . '/src/upload_processor.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With,Authorization,Content-Type');
header('Access-Control-Max-Age: 86400');

allowMethods('GET', 'POST', 'OPTIONS');

/* ────────────────────────────────────────────────────────────
 * Ранний разбор запроса.
 *
 * Тело php://input читается один раз и сохраняется в $payload.
 * Язык из payload нужен ДО проверки maintenance/overload,
 * чтобы сообщения отдавались на языке устройства.
 * ──────────────────────────────────────────────────────────── */
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isJson      = stripos($contentType, 'application/json') !== false;

$kind      = null;   // 'bulk' | 'single' | null
$payload   = null;
$jsonError = false;

if ($isJson) {
    $records = json_decode(file_get_contents('php://input'), true);
    if (is_array($records) && !empty($records)) {
        $kind    = 'bulk';
        $payload = $records;
    } else {
        $jsonError = true;
    }
} elseif (count($_REQUEST) > 0) {
    $kind    = 'single';
    $payload = $_REQUEST;
}

/* ────────────────────────────────────────────────────────────
 * Resolve language, шаг 1 — из payload (RedManage шлёт lang
 * внутри каждой записи bulk-массива, Torque — нет).
 * ──────────────────────────────────────────────────────────── */
if ($kind === 'bulk' && is_array($payload)) {
    foreach ($payload as $record) {
        if (is_array($record)
            && isset($record['lang'])
            && is_string($record['lang'])
            && $record['lang'] !== '') {
            $lang = $record['lang'];
            break;   // все записи в батче обычно с одним языком
        }
    }
} elseif ($kind === 'single'
        && is_array($payload)
        && !empty($payload['lang'])
        && is_string($payload['lang'])) {
    $lang = $payload['lang'];
}

/* ────────────────────────────────────────────────────────────
 * Resolve language, шаг 2 — из POST/GET.
 * Пустая строка превращается в null, чтобы шаг 3 (БД) сработал.
 * ──────────────────────────────────────────────────────────── */
if (empty($lang) || !is_string($lang)) {
    $candidate = $_POST['lang'] ?? $_GET['lang'] ?? null;
    if (is_string($candidate) && $candidate !== '') {
        $lang = $candidate;
    } else {
        $lang = null;
    }
}

/* ────────────────────────────────────────────────────────────
 * Аутентификация по Bearer-токену.
 * ──────────────────────────────────────────────────────────── */
$token = getBearerToken();
if (!empty($token)) {
    if (file_exists('maintenance')) {
        http_response_code(423);
        die($translations[$lang ?? 'en']['maintenance']);
    }

    // API-запрос: сессия не нужна. auth_user.php (подтянется через db.php)
    // не будет стартовать её и не будет рендерить форму логина.
    define('RATEL_API_REQUEST', true);
    require_once __DIR__ . '/src/db.php';

    $load = sys_getloadavg();
    if ($max_load_avg > 0 && $load[1] > $max_load_avg) {
        http_response_code(503);
        die($translations[$lang ?? 'en']['overload']);
    }

    $cache_key = "user_data_" . $token;
    $user_data = false;

    if ($memcached_connected) {
        $user_data = $memcached->get($cache_key);
    }

    if ($user_data === false) {
        $userqry = $db->execute_query(
            "SELECT id, user, s, tg_token, tg_chatid, lang FROM users WHERE token=?",
            [$token]
        );
        if ($userqry->num_rows) {
            $access = 1;
            $user_data = $userqry->fetch_assoc();
            if ($memcached_connected) {
                try {
                    $memcached->set($cache_key, $user_data, $db_cache_ttl ?? 3600);
                } catch (Exception $e) {
                    error_log("Ratel cache error on upload auth: " . $e->getMessage());
                }
            }
        } else {
            $access = 0;
        }
    }

    if ($user_data) {
        $access    = 1;
        $username  = $user_data['user'];
        $limit     = $user_data['s'];
        $tg_token  = $user_data['tg_token'];
        $tg_chatid = $user_data['tg_chatid'];
        $user_id = (int)$user_data['id'];

        /* ────────────────────────────────────────────────────
         * Resolve language, шаг 3 — из БД.
         * Сработает только если payload и POST/GET пусты
         * (типичный случай для Torque).
         * ──────────────────────────────────────────────────── */
        if (empty($lang)) {
            $lang = $user_data['lang'] ?? null;
        }
    }
} else {
    $access = 0;
}

/* ────────────────────────────────────────────────────────────
 * Resolve language, шаг 4 — финальный fallback.
 * Пустой / невалидный / неизвестный код → 'en'.
 * ──────────────────────────────────────────────────────────── */
if (empty($lang)
    || !is_string($lang)
    || !isset($translations[$lang])) {
    $lang = 'en';
}

if ($access != 1 || $limit == 0) {
    http_response_code(403);
    die($translations[$lang]['denied']);
}

if (isset($_REQUEST['servertime'])) {
    $dt = new DateTime('now', new DateTimeZone('UTC'));
    echo (int)($dt->format('Uu') / 1000);
    exit;
}

/* ────────────────────────────────────────────────────────────
 * Проверка лимита сессий.
 * ──────────────────────────────────────────────────────────── */
$session_count_cache_key = "session_count_" . $user_id;
$session_count = false;
if ($memcached_connected) {
    $session_count = $memcached->get($session_count_cache_key);
}
if ($session_count === false) {
    $session_count = (int)$db->execute_query(
        "SELECT COUNT(*) FROM sessions WHERE user_id = ?",
        [$user_id]
    )->fetch_row()[0];
    if ($memcached_connected) {
        try {
            $memcached->set($session_count_cache_key, $session_count, 60);
        } catch (Exception $e) {
            error_log("Ratel cache error on upload: " . $e->getMessage());
        }
    }
}

if ($session_count >= $limit && $limit != -1) {
    http_response_code(507);
    die($translations[$lang]['no_space']);
}

/* ────────────────────────────────────────────────────────────
 * Rate limit на аплоады.
 * ──────────────────────────────────────────────────────────── */
$rate_limit_key = "rate_limit_" . $username;
$max_upload_requests_per_second = $max_upload_requests_per_second ?? 100;

if ($memcached_connected) {
    try {
        // INCR + EXPIRE одной атомарной операцией на сервере Redis.
        // TTL = 1 секунда устанавливается только на первом инкременте.
        $count = $memcached->incrementWithTtl($rate_limit_key, 1, 1);

        if ($count !== false && $count > $max_upload_requests_per_second) {
            http_response_code(429);
            error_log("Upload spammer detected: " . $username);
            die($translations[$lang]['upload.429']);
        }
    } catch (Exception $e) {
        error_log("Ratel cache error on upload: " . $e->getMessage());
    }
}

/* ────────────────────────────────────────────────────────────
 * Валидация payload (после auth, чтобы не палить структуру).
 * ──────────────────────────────────────────────────────────── */
if ($jsonError) {
    http_response_code(400);
    echo "Invalid JSON";
    exit;
}

if ($kind === null || $payload === null) {
    $db->close();
    echo "OK!";
    exit;
}

if ($kind === 'bulk' && count($payload) > 100) {
    http_response_code(400);
    echo "Too many records";
    exit;
}

/* ────────────────────────────────────────────────────────────
 * Fast path: положить в Redis Stream, ответить сразу.
 * ──────────────────────────────────────────────────────────── */
$ip = $_SERVER['HTTP_CLIENT_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '0.0.0.0';

// X-Forwarded-For — список через запятую: "client, proxy1, proxy2".
// Берём первый (клиентский) IP.
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip, 2)[0]);
}

// Страховка: если пришёл мусор — не пишем его в БД.
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $ip = '0.0.0.0';
}

$streamed = false;
if (!empty($redis_stream_enabled)) {
    $streamed = redis_stream_push([
        'user'     => $username,
        'user_id'  => (string)$user_id,
        'ip'       => $ip,
        'lang'     => $lang,
        'kind'     => $kind,
        'payload'  => json_encode($payload, JSON_UNESCAPED_UNICODE),
        'received' => (string)microtime(true),
    ]);
}

/* ────────────────────────────────────────────────────────────
 * Fallback: обработать inline (Redis недоступен или выключен).
 * ──────────────────────────────────────────────────────────── */
if (!$streamed) {
    $ctx = [
        'username'       => $username,
        'user_id'        => (int)$user_id,
        'lang'           => $lang,
        'tg_token'       => $tg_token ?? null,
        'tg_chatid'      => $tg_chatid ?? null,
        'tg_socks_proxy' => $tg_socks_proxy ?? '',
        'translations'   => $translations,
        'ip'             => $ip,
    ];

    try {
        processUpload($db, $ctx, $kind, $payload);
    } catch (Throwable $e) {
        error_log("Upload processing error: " . $e->getMessage());
        http_response_code(500);
        $db->close();
        echo "Error";
        exit;
    }
}

$db->close();
echo "OK!";

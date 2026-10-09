<?php

require_once __DIR__ . '/src/methods.php';
allowMethods('HEAD', 'POST');

/* ────────────────────────────────────────────────────────────
 * auth.php — heartbeat + обновление CSRF-токена.
 * ──────────────────────────────────────────────────────────── */
if (empty($_COOKIE['stream'])) {
    http_response_code(401);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$is_logged_in = !empty($_SESSION['torque_logged_in']);

/* ────────────────────────────────────────────────────────────
 * Maintenance — до ветвления. Админ не блокируется (как в db.php).
 * ──────────────────────────────────────────────────────────── */
if (is_maintenance() && empty($_SESSION['admin'])) {
    http_response_code(307);
    exit;
}

/* ────────────────────────────────────────────────────────────
 * HEAD — heartbeat из static/js/head.js.
 *   - мёртвая сессия → 401 (head.js уводит на .?logout=true);
 *   - живая → проверяем new_session_{username} в memcached и,
 *     если телеметрия сигнализировала о новой сессии, выставляем
 *     cookie `newsess` (её читает helpers.js: checkNewSession()).
 * ──────────────────────────────────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') {
    if (!$is_logged_in) {
        http_response_code(401);
        exit;
    }

    require_once __DIR__ . '/src/creds.php';
    require_once __DIR__ . '/src/db.php';

    if (!empty($memcached_connected) && isset($memcached)) {
        try {
            if ($memcached->get("new_session_" . $username)) {
                $memcached->delete("new_session_" . $username);
                setcookie("newsess", true);
            }
        } catch (Throwable $e) {
            error_log("Ratel cache error on new-session check: " . $e->getMessage());
        }
    }

    http_response_code(200);
    exit;
}

/* ────────────────────────────────────────────────────────────
 * POST action=update-csrf-token — продление CSRF из head.js.
 * Всегда JSON, никогда HTML.
 * ──────────────────────────────────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (is_array($input)
        && isset($input['action'])
        && $input['action'] === 'update-csrf-token'
    ) {
        header('Content-Type: application/json');

        if (!$is_logged_in) {
            http_response_code(401);
            echo json_encode(['error' => 'session_expired', 'reload' => true]);
            exit;
        }

        require_once __DIR__ . '/src/helpers.php';

        $token = generate_csrf_token();
        echo json_encode([
            'token'  => $token,
            'expiry' => $_SESSION['csrf_token_time'] + 3300,
        ]);
        exit;
    }
}

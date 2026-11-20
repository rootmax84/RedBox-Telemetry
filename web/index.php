<?php
declare(strict_types=1);

require_once __DIR__ . '/src/router.php';

// MCP must never create a browser session or run browser-CSRF middleware.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (in_array($requestPath, [
    '/mcp', '/oauth/token', '/api/share/remote', '/.well-known/oauth-protected-resource',
    '/.well-known/oauth-protected-resource/mcp', '/.well-known/oauth-authorization-server',
], true)) {
    define('RATEL_API_REQUEST', true);
}

// A signed share is a standalone capability, not a browser account session.
// Never read, create or rotate the PHP login session for public share pages
// or their signed graph requests (even if the browser sends PHPSESSID).
$isSignedPlot = $requestPath === '/plot'
    && isset($_GET['id'], $_GET['sig'])
    && is_string($_GET['id']) && $_GET['id'] !== ''
    && is_string($_GET['sig']) && $_GET['sig'] !== ''
    && ((isset($_GET['u']) && is_string($_GET['u']) && $_GET['u'] !== '')
        || (isset($_GET['uid']) && is_string($_GET['uid']) && $_GET['uid'] !== ''));
if (in_array($requestPath, ['/share', '/share/remote'], true) || $isSignedPlot) {
    define('RATEL_PUBLIC_SHARE_REQUEST', true);
}

$router = new Router(__DIR__);
require __DIR__ . '/src/routes.php';

if (session_status() === PHP_SESSION_NONE
    && !(defined('RATEL_API_REQUEST') && RATEL_API_REQUEST)
    && !(defined('RATEL_PUBLIC_SHARE_REQUEST') && RATEL_PUBLIC_SHARE_REQUEST)
    // Public localization/timezone requests also run from share pages.
    // Neither endpoint reads account state; avoid reopening its session.
    && !in_array($requestPath, ['/translations', '/timezone'], true)) {
    session_start();
}

$target = $router->dispatch($_SERVER['REQUEST_URI'] ?? '/');

// ────────────────────────────────────────────────────────────
// Подключаем целевой файл В ГЛОБАЛЬНОМ SCOPE.
// Здесь, на верхнем уровне index.php, все top-level переменные
// целевого файла ($db, $username, $memcached, $csrf_exempt_scripts
// и т.д.) попадают в истинный global scope и живут до конца запроса.
// ────────────────────────────────────────────────────────────
if ($target !== null) {
    require $target;
}

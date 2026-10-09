<?php
declare(strict_types=1);

require_once __DIR__ . '/src/router.php';

$router = new Router(__DIR__);
require __DIR__ . '/src/routes.php';

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

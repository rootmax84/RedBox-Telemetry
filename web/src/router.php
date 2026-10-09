<?php
declare(strict_types=1);

/**
 * Minimal front-controller router for RedBox Telemetry.
 *
 * Особенности:
 *   - Поддерживает {name} и {name:regex} в паттернах.
 *   - В regex-части плейсхолдера корректно обрабатываются вложенные
 *     фигурные скобки: \d{3}, [a-f0-9]{32}, \d{1,10} и т.п.
 *   - Перед возвратом пути подменяет $_SERVER['SCRIPT_FILENAME'],
 *     чтобы существующие basename() проверки в auth_user.php и db.php
 *     продолжали работать без правок в этих файлах.
 *   - Прокидывает route-params в $_GET / $_REQUEST.
 *   - Защищает от path traversal (target обязан лежать внутри baseDir).
 *
 * ──────────────────────────────────────────────────────────────
 * ВАЖНО: dispatch() НЕ подключает целевой файл сам.
 *
 * require делает web/index.php на верхнем уровне — в истинном
 * global scope, чтобы top-level переменные целевого файла
 * ($db, $username, $memcached, $csrf_exempt_scripts и т.д.)
 * жили до конца запроса.
 * ──────────────────────────────────────────────────────────────
 */
final class Router
{
    /**
     * @var list<array{
     *   method:string,
     *   pattern:string,
     *   file:string,
     *   opts:array,
     *   regex:?string
     * }>
     */
    private array $routes = [];

    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim(realpath($baseDir) ?: $baseDir, '/');
    }

    public function get(string $p, string $f, array $o = []): void  { $this->add('GET',  $p, $f, $o); }
    public function post(string $p, string $f, array $o = []): void { $this->add('POST', $p, $f, $o); }
    public function any(string $p, string $f, array $o = []): void  { $this->add('*',    $p, $f, $o); }

    public function add(string $method, string $pattern, string $file, array $opts = []): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'file'    => $file,
            'opts'    => $opts,
            'regex'   => $this->compilePattern($pattern),
        ];
    }

    /**
     * Находит маршрут, готовит окружение и возвращает абсолютный
     * путь к целевому PHP-файлу — либо null, если ничего не найдено
     * (в этом случае notFound() уже сделал exit).
     *
     * Caller (web/index.php) обязан сделать `require $target;`
     * в глобальном scope.
     */
    public function dispatch(string $uri): ?string
    {
        $path   = parse_url($uri, PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Нормализация: убираем trailing slash (кроме корня)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $r) {
            if ($r['method'] !== '*' && $r['method'] !== $method) {
                continue;
            }

            $params = $this->match($r, $path);
            if ($params === null) {
                continue;
            }

            return $this->prepare($r, $params);
        }

        $this->notFound();
        return null;   // unreachable: notFound() делает exit
    }

    /** @return array<string,string>|null */
    private function match(array $route, string $path): ?array
    {
        // Паттерн без плейсхолдеров — точное сравнение строк.
        // $route['regex'] === null означает, что '{' в паттерне не было.
        if ($route['regex'] === null) {
            return $route['pattern'] === $path ? [] : null;
        }

        if (!preg_match($route['regex'], $path, $m)) {
            return null;
        }

        $params = [];
        foreach ($m as $k => $v) {
            if (is_string($k)) {
                $params[$k] = $v;
            }
        }
        return $params;
    }

    /**
     * Компилирует URI-паттерн в регэксп.
     *
     * Поддерживает:
     *   /foo/{id}              → (?P<id>[^/]+)
     *   /foo/{id:\d+}          → (?P<id>\d+)
     *   /tasks/{id:[a-f0-9]{32}} → (?P<id>[a-f0-9]{32})
     *   /x/{id:\d{1,10}}       → (?P<id>\d{1,10})
     *
     * Возвращает null, если плейсхолдеров нет — caller сравнивает
     * строки напрямую.
     *
     * Сканер ручной (не regexp по самому паттерну), чтобы корректно
     * отслеживать вложенные '{' / '}' внутри regex-части.
     */
    private function compilePattern(string $pattern): ?string
    {
        if (!str_contains($pattern, '{')) {
            return null;
        }

        $regex = '';
        $len   = strlen($pattern);
        $i     = 0;

        while ($i < $len) {
            $c = $pattern[$i];

            if ($c !== '{') {
                $regex .= $c;
                $i++;
                continue;
            }

            // ─── Нашли '{'. Пробуем прочитать плейсхолдер. ───
            $j    = $i + 1;
            $name = '';
            while ($j < $len && (ctype_alnum($pattern[$j]) || $pattern[$j] === '_')) {
                $name .= $pattern[$j];
                $j++;
            }

            // Имя должно начинаться с буквы/подчёркивания и быть непустым.
            // Иначе это литеральный '{' — не наш случай.
            if ($name === '' || !(ctype_alpha($name[0]) || $name[0] === '_')) {
                $regex .= $c;
                $i++;
                continue;
            }

            $sub = '[^/]+';   // дефолт: один и более символов, кроме '/'

            if ($j < $len && $pattern[$j] === ':') {
                // ─── Есть regex-часть. Читаем её с балансировкой {}. ───
                $j++;
                $depth = 0;
                $start = $j;

                while ($j < $len) {
                    $ch = $pattern[$j];
                    if ($ch === '{') {
                        $depth++;
                    } elseif ($ch === '}') {
                        if ($depth === 0) {
                            // Закрывающий '}' плейсхолдера.
                            break;
                        }
                        $depth--;
                    }
                    $j++;
                }

                $sub = substr($pattern, $start, $j - $start);

                // Съедаем закрывающий '}' плейсхолдера.
                if ($j < $len && $pattern[$j] === '}') {
                    $j++;
                }
                // Если '}' так и не нашёлся — молча заканчиваем.
                // Паттерн-ошибку проявит сам regex (preg_match вернёт 0).
            } else {
                // ─── Простой {name} — сразу ждём '}'. ───
                if ($j < $len && $pattern[$j] === '}') {
                    $j++;
                } else {
                    // Не '}' — некорректный плейсхолдер. Копируем '{'
                    // как литерал, чтобы не съесть возможный настоящий '{'.
                    $regex .= $c;
                    $i++;
                    continue;
                }
            }

            $regex .= '(?P<' . $name . '>' . $sub . ')';
            $i = $j;
        }

        return '~^' . $regex . '$~u';
    }

    /**
     * Готовит окружение для маршрута:
     *   - валидирует target
     *   - прокидывает {param} в $_GET / $_REQUEST
     *   - эмулирует SCRIPT_FILENAME / SCRIPT_NAME / PHP_SELF
     *   - запускает middleware
     *
     * Возвращает абсолютный путь к файлу. НЕ подключает его.
     */
    private function prepare(array $route, array $params): string
    {
        /* Заглушка: пустой (или null) файл = ничего не подключаем ───
        * routes.php
        *
        * Просто 404:
        * $router->any('/test', '');
        *
        * 404 с текстом:
        * $router->any('/test', '', ['status' => 404, 'body' => 'Not found']);
        *
        * 204 No Content:
        * $router->any('/webhook/ping', '', ['status' => 204]);
        *
        * 410 Gone
        *$router->any('/legacy/api', '', ['status' => 410, 'body' => 'Gone']);
        */
        if ($route['file'] === '' || $route['file'] === null) {
            $status = (int)($route['opts']['status'] ?? 404);

            foreach ($params as $k => $v) {
                if (!array_key_exists($k, $_GET)) {
                    $_GET[$k]     = $v;
                    $_REQUEST[$k] = $v;
                }
            }

            $GLOBALS['__route'] = [
                'pattern' => $route['pattern'],
                'file'    => null,
                'params'  => $params,
                'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            ];

            http_response_code($status);

            if (!empty($route['opts']['body'])) {
                header('Content-Type: text/plain; charset=utf-8');
                echo $route['opts']['body'];
            }

            exit;
        }
        $target = realpath($this->baseDir . '/' . ltrim($route['file'], '/'));

        if ($target === false
            || !is_file($target)
            || !str_starts_with($target, $this->baseDir . '/')
        ) {
            http_response_code(500);
            error_log("[router] target invalid: {$route['file']}");
            echo 'Internal error';
            exit;
        }

        // ─── 1. Route params → $_GET / $_REQUEST ───
        foreach ($params as $k => $v) {
            if (!array_key_exists($k, $_GET)) {
                $_GET[$k]     = $v;
                $_REQUEST[$k] = $v;
            }
        }

        // ─── 2. Эмуляция SCRIPT_FILENAME ───
        $relative = '/' . ltrim($route['file'], '/');
        $_SERVER['SCRIPT_FILENAME'] = $target;
        $_SERVER['SCRIPT_NAME']     = $relative;
        $_SERVER['PHP_SELF']        = $relative;

        $GLOBALS['__route'] = [
            'pattern' => $route['pattern'],
            'file'    => $route['file'],
            'params'  => $params,
            'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ];

        // ─── 3. Middleware (по желанию) ───
        foreach ((array)($route['opts']['middleware'] ?? []) as $mw) {
            $mwFile = $this->baseDir . '/src/middleware/' . $mw . '.php';
            if (is_file($mwFile)) {
                require $mwFile;
            }
        }

        // ─── 4. Возвращаем путь, НЕ подключаем ───
        return $target;
    }

    private function notFound(): void
    {
        http_response_code(404);
        $_GET['c'] = '404';
        require $this->baseDir . '/catch.php';
        exit;
    }
}

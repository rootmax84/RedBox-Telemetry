<?php
declare(strict_types=1);

/** Stateless MCP endpoint; browser cookies and device API tokens are not accepted. */
if (!defined('RATEL_API_REQUEST')) {
    define('RATEL_API_REQUEST', true);
}

// Load lightweight configuration and protocol helpers before any external I/O.
// A missing/malformed Bearer token must receive an immediate 401 even if
// MariaDB or Redis is unavailable. In particular, scanners send GET /mcp.
require_once __DIR__ . '/src/creds.php';
require_once __DIR__ . '/src/mcp/Security.php';
require_once __DIR__ . '/src/mcp/OAuth.php';
require_once __DIR__ . '/src/mcp/Protocol.php';
require_once __DIR__ . '/src/mcp/Transport.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Vary: Authorization, MCP-Protocol-Version, Mcp-Method, Mcp-Name');

function mcp_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/**
 * Normalize untrusted values before writing them to a single log line.
 * Removes line breaks, control characters and Unicode line separators.
 */
function mcp_log_safe(string $value): string
{
    $clean = preg_replace(
        '/[\x00-\x1F\x7F]|\xC2[\x80-\x9F]|\xE2\x80[\xA8\xA9]/',
        ' ',
        $value
    );

    return substr($clean ?? '[invalid log value]', 0, 256);
}

function mcp_error(mixed $id, int $code, string $message, int $status = 200, ?array $data = null): never
{
    $error = ['code' => $code, 'message' => $message];

    if ($data !== null) {
        $error['data'] = $data;
    }

    mcp_json([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => $error,
    ], $status);
}

/**
 * Open a bounded MariaDB connection without the page-oriented db.php bootstrap.
 * The normal bootstrap eagerly connects Redis and redirects to HTML error pages,
 * neither of which is appropriate for authenticated MCP HTTP requests.
 */
function mcp_open_database(): mysqli
{
    global $db_host, $db_user, $db_pass, $db_name, $db_port;
    global $mcp_db_connect_timeout;

    $connection = mysqli_init();

    if (!$connection instanceof mysqli) {
        throw new RuntimeException('Unable to initialize the database driver');
    }

    $timeout = max(1, min(10, (int)($mcp_db_connect_timeout ?? 3)));
    $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, $timeout);
    if (!$connection->real_connect(
        (string)$db_host,
        (string)$db_user,
        (string)$db_pass,
        (string)$db_name,
        (int)$db_port
    )) {
        throw new RuntimeException('MCP database connection failed');
    }

    return $connection;
}

function mcp_initialize(string $protocol, bool $modern, mixed $id): array
{
    if ($modern) {
        mcp_error($id, -32601, 'initialize removed in MCP 2026-07-28');
    }

    return McpProtocol::initializeResult($protocol);
}

function mcp_discover(bool $modern, mixed $id): array
{
    if (!$modern) {
        mcp_error($id, -32601, 'server/discover requires MCP 2026-07-28');
    }

    return McpProtocol::discoveryResult(McpTransport::SUPPORTED_VERSIONS);
}

function mcp_ping(bool $modern, mixed $id): stdClass
{
    if ($modern) {
        mcp_error($id, -32601, 'ping unsupported in MCP 2026-07-28');
    }

    return new stdClass();
}

function mcp_call_tool(mysqli $db, array $auth, array $params, ?RatelCache $redis, mixed $id): array
{
    $name = $params['name'] ?? null;
    $arguments = $params['arguments'] ?? [];

    if (!is_string($name) || !is_array($arguments) || (array_is_list($arguments) && $arguments !== [])) {
        mcp_error($id, -32602, 'Invalid tool name or arguments');
    }

    try {
        $data = McpTools::call($db, $auth, $name, $arguments, $redis);

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ]],
            'structuredContent' => $data,
            'isError' => false,
        ];
    } catch (InvalidArgumentException | McpAccessException $e) {
        $result = [
            'content' => [['type' => 'text', 'text' => $e->getMessage()]],
            'isError' => true,
        ];
        if ($e instanceof McpAccessException && ($auth['token_kind'] ?? '') === 'oauth') {
            // An OAuth client can request an expanded grant when a scope is absent.
            $result['_meta']['mcp/www_authenticate'] = [
                McpOAuth::challenge('insufficient_scope') . ', error_description="Additional MCP permissions required"',
            ];
        }
        return $result;
    }
}

function mcp_read_resource(mysqli $db, array $auth, array $params, ?RatelCache $redis, mixed $id): array
{
    $uri = $params['uri'] ?? null;
    if (!is_string($uri)) {
        mcp_error($id, -32602, 'Missing URI');
    }

    return McpTools::readResource($db, $auth, $uri, $redis);
}

function mcp_get_prompt(array $auth, array $params, mixed $id): array
{
    $name = $params['name'] ?? null;
    if (!is_string($name)) {
        mcp_error($id, -32602, 'Missing prompt name');
    }

    $arguments = $params['arguments'] ?? null;
    return McpTools::getPrompt($auth, $name, is_array($arguments) ? $arguments : []);
}

function mcp_dispatch(
    string $method,
    mysqli $db,
    array $auth,
    array $params,
    ?RatelCache $redis,
    string $protocol,
    bool $modern,
    mixed $id
): array|stdClass {
    return match ($method) {
        'initialize' => mcp_initialize($protocol, $modern, $id),
        'server/discover' => mcp_discover($modern, $id),
        'ping' => mcp_ping($modern, $id),
        'tools/list' => ['tools' => McpTools::available($auth)],
        'tools/call' => mcp_call_tool($db, $auth, $params, $redis, $id),
        'resources/list' => ['resources' => McpTools::resources($auth)],
        'resources/templates/list' => ['resourceTemplates' => McpTools::resourceTemplates($auth)],
        'resources/read' => mcp_read_resource($db, $auth, $params, $redis, $id),
        'prompts/list' => ['prompts' => McpTools::prompts($auth)],
        'prompts/get' => mcp_get_prompt($auth, $params, $id),
        'completion/complete' => McpTools::complete($db, $auth, $params),
        default => mcp_error($id, -32601, 'Method not found'),
    };
}

/** Advertise OAuth only when configured; legacy manual Bearer remains valid. */
function mcp_auth_challenge(string $error = 'invalid_token'): void
{
    try {
        header('WWW-Authenticate: ' . McpOAuth::challenge($error));
    } catch (Throwable $e) {
        header('WWW-Authenticate: Bearer realm="RedBox MCP"');
    }
}

// Validate the complete browser Origin, including scheme and effective port.
// OAuth installs use the configured public issuer as the trusted origin;
// manual-token installs must also restrict Host at the reverse proxy/vhost.
if (!McpTransport::originAllowed(
    (string)($_SERVER['HTTP_ORIGIN'] ?? ''),
    $_SERVER,
    (bool)($mcp_trust_forwarded_proto ?? false),
    (string)($mcp_oauth_issuer ?? '')
)) {
    mcp_json(['error' => 'Cross-origin MCP requests are forbidden'], 403);
}

// The forwarded scheme is trusted only when explicitly configured for a TLS proxy.
$host = parse_url('http://' . (string)($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?: '';
$isLocal = in_array(strtolower($host), ['localhost', '127.0.0.1', '[::1]', '::1'], true);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((bool)($mcp_trust_forwarded_proto ?? false) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (($mcp_require_https ?? true) && !$secure && !$isLocal) {
    mcp_json(['error' => 'HTTPS required for MCP'], 403);
}

// Reject unauthenticated probes before connecting to MariaDB or Redis.
// A syntactically valid token is NOT trusted until the database authenticates it.
try {
    McpSecurity::bearerToken();
} catch (McpAccessException $e) {
    mcp_auth_challenge();
    mcp_json(['error' => $e->getMessage()], 401);
}

// Database is mandatory for token ownership, revocation and scope checks;
// Redis is optional. Connection failure must be a bounded JSON 503, not an
// HTML redirect or a hanging 2026 protocol request.
try {
    $db = mcp_open_database();
} catch (Throwable $e) {
    error_log('MCP database connection failed: ' . mcp_log_safe($e->getMessage()));
    mcp_json(['error' => 'MCP authentication unavailable'], 503);
}

try {
    $auth = McpSecurity::authenticate($db);
} catch (McpAccessException $e) {
    if ($e->httpStatus === 401) {
        mcp_auth_challenge();
    }
    mcp_json(['error' => $e->getMessage()], $e->httpStatus);
} catch (Throwable $e) {
    error_log('MCP authentication failure: ' . mcp_log_safe($e->getMessage()));
    mcp_json(['error' => 'MCP authentication unavailable'], 503);
}

// A valid token may use only POST. Do not start Redis for unused GET requests.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    mcp_json(['error' => 'MCP Streamable HTTP endpoint accepts POST only'], 405);
}

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/cache.php';
require_once __DIR__ . '/src/redis.php';
require_once __DIR__ . '/src/mcp/Data.php';
require_once __DIR__ . '/src/mcp/Tools.php';

$redis = new RatelCache(get_redis_connection(), $redis_cache_compression_enabled ?? true);

try {
    McpSecurity::rateLimit($auth, $redis);
    $table = ($auth['token_kind'] ?? 'manual') === 'oauth' ? 'mcp_oauth_tokens' : 'mcp_tokens';
    $db->execute_query(
        "UPDATE {$table} SET last_used_at=NOW() WHERE id=? "
        . 'AND (last_used_at IS NULL OR last_used_at < DATE_SUB(NOW(),INTERVAL 60 SECOND))',
        [$auth['token_id']]
    );
} catch (McpAccessException $e) {
    if ($e->httpStatus === 401) {
        mcp_auth_challenge();
    }

    mcp_json(['error' => $e->getMessage()], $e->httpStatus);
} catch (Throwable $e) {
    error_log('MCP authentication failure: ' . mcp_log_safe($e->getMessage()));
    mcp_json(['error' => 'MCP authentication unavailable'], 503);
}

if (!str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
    mcp_json(['error' => 'Content-Type: application/json required'], 415);
}

$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > 65536) {
    mcp_json(['error' => 'Request body too large'], 413);
}

$raw = file_get_contents('php://input', false, null, 0, 65537);
if (!is_string($raw) || strlen($raw) > 65536) {
    mcp_json(['error' => 'Request body too large'], 413);
}

try {
    $request = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    mcp_error(null, -32700, 'Parse error', 400);
}

if (!is_array($request)
    || array_is_list($request)
    || ($request['jsonrpc'] ?? null) !== '2.0'
    || !is_string($request['method'] ?? null)
) {
    mcp_error(null, -32600, 'Invalid JSON-RPC request', 400);
}

$id = $request['id'] ?? null;
$isNotification = !array_key_exists('id', $request);
if (!$isNotification && !is_int($id) && !is_string($id)) {
    mcp_error(null, -32600, 'Invalid request id', 400);
}

$params = $request['params'] ?? [];
if (!is_array($params) || (array_is_list($params) && $params !== [])) {
    mcp_error($id, -32602, 'Invalid params');
}

$rpcMethod = $request['method'];

try {
    $protocol = McpTransport::negotiate($request, $_SERVER);
} catch (McpProtocolViolation $e) {
    header('MCP-Protocol-Version: ' . McpTransport::MODERN_VERSION);
    mcp_error($id, $e->rpcCode, $e->getMessage(), $e->httpStatus, $e->data);
}

$modern = $protocol === McpTransport::MODERN_VERSION;
header('MCP-Protocol-Version: ' . $protocol);

if ($modern && !McpTransport::supportsModernMethod($rpcMethod)) {
    mcp_error($id, -32601, 'Method not found', 404);
}


if ($isNotification) {
    // Legacy clients send notifications/initialized after initialize.
    // Modern HTTP has no core client-to-server notifications.
    if ($modern) {
        mcp_error(null, -32601, 'Method not found', 404);
    }

    http_response_code(202);
    exit;
}

try {
    $result = mcp_dispatch($rpcMethod, $db, $auth, $params, $redis, $protocol, $modern, $id);
    $result = McpProtocol::decorateResult($rpcMethod, $result, $modern);
    if ($modern) {
        header('Cache-Control: ' . McpProtocol::httpCacheControl($result));
    }
    mcp_json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
} catch (InvalidArgumentException | McpAccessException $e) {
    mcp_error($id, -32602, $e->getMessage());
} catch (Throwable $e) {
    error_log('MCP internal failure (' . mcp_log_safe($rpcMethod) . '): ' . mcp_log_safe($e->getMessage()));
    mcp_error($id, -32603, 'Internal MCP error', 500);
}

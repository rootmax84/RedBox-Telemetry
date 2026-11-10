<?php
declare(strict_types=1);

/** Token exchange has its own form-urlencoded authorization and no browser CSRF cookie. */
if (!defined('RATEL_API_REQUEST')) {
    define('RATEL_API_REQUEST', true);
}
require_once __DIR__ . '/src/creds.php';
require_once __DIR__ . '/src/mcp/Security.php';
require_once __DIR__ . '/src/mcp/OAuth.php';
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function oauth_token_response(array $data, int $status): never
{
    http_response_code($status);
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    oauth_token_response(['error' => 'invalid_request'], 405);
}
if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/x-www-form-urlencoded')
    || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    oauth_token_response(['error' => 'invalid_request'], 400);
}
// No client secret is accepted: supported public CIMD clients use PKCE S256.
if (isset($_POST['client_secret']) || ($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== '') {
    oauth_token_response(['error' => 'invalid_client'], 401);
}
try {
    McpOAuth::issuer();
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($mcp_trust_forwarded_proto ?? false) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (!$scheme) {
        oauth_token_response(['error' => 'invalid_request', 'error_description' => 'HTTPS required'], 400);
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    $db->real_connect($db_host, $db_user, $db_pass, $db_name, (int)$db_port);
    $tokens = match ((string)($_POST['grant_type'] ?? '')) {
        'authorization_code' => McpOAuth::exchangeCode($db, $_POST),
        'refresh_token' => McpOAuth::refresh($db, $_POST),
        default => throw new InvalidArgumentException('unsupported_grant_type'),
    };
    oauth_token_response($tokens, 200);
} catch (InvalidArgumentException $e) {
    $allowedErrors = ['invalid_grant', 'unsupported_grant_type'];
    $error = in_array($e->getMessage(), $allowedErrors, true) ? $e->getMessage() : 'invalid_request';
    oauth_token_response(['error' => $error], 400);
} catch (Throwable $e) {
    error_log('MCP OAuth token endpoint unavailable (non-sensitive): ' . get_class($e));
    oauth_token_response(['error' => 'temporarily_unavailable'], 503);
}

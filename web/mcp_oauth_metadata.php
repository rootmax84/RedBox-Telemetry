<?php
declare(strict_types=1);

/** Public OAuth discovery never requires an MCP Bearer token or an application cookie. */
if (!defined('RATEL_API_REQUEST')) {
    define('RATEL_API_REQUEST', true);
}
require_once __DIR__ . '/src/creds.php';
require_once __DIR__ . '/src/mcp/Security.php';
require_once __DIR__ . '/src/mcp/OAuth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
try {
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $data = match ($path) {
        '/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/mcp'
            => McpOAuth::resourceMetadata(),
        '/.well-known/oauth-authorization-server' => McpOAuth::serverMetadata(),
        default => null,
    };
    if ($data === null) {
        http_response_code(404);
        exit;
    }
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(503);
    header('Cache-Control: no-store');
    echo json_encode(['error' => 'OAuth metadata not configured']);
}

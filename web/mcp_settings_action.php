<?php
declare(strict_types=1);

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/mcp/Security.php';

/** Map safe validation errors to the application's existing localized messages. */
function mcp_error_l10n_key(string $message): string
{
    return match ($message) {
        'Invalid token id' => 'mcp.error.token_id',
        'Token has not expired' => 'mcp.error.not_expired',
        'Unknown action' => 'mcp.error.action',
        'Select at least one access scope' => 'mcp.error.scopes_empty',
        'Unknown access scope' => 'mcp.error.scopes_invalid',
        'Invalid token parameters (expiry 1–365 days)' => 'mcp.error.params',
        'Maximum of 5 active MCP tokens' => 'mcp.error.limit',
        default => 'mcp.error.generic',
    };
}

function mcp_settings_toggle(mysqli $db, int $uid, array $input): string
{
    $enabled = ($input['mcp_enabled'] ?? '') === '1' ? 1 : 0;
    $db->execute_query('UPDATE users SET mcp_enabled=? WHERE id=?', [$enabled, $uid]);

    return $enabled ? 'mcp.notice.enabled' : 'mcp.notice.disabled';
}

function mcp_settings_create(mysqli $db, int $uid, array $input): string
{
    $plain = McpSecurity::generate(
        $db,
        $uid,
        (string)($input['label'] ?? ''),
        $input['scopes'] ?? [],
        (int)($input['days'] ?? 30)
    );

    // The plaintext secret is shown once. Only its SHA-256 hash is stored in MariaDB.
    $_SESSION['mcp_new_token'] = $plain;

    return 'mcp.notice.created';
}

function mcp_settings_remove_token(mysqli $db, int $uid, int $tokenId, bool $expiredOnly): string
{
    if ($tokenId < 1) {
        throw new InvalidArgumentException('Invalid token id');
    }

    McpSecurity::revoke($db, $uid, $tokenId, $expiredOnly);

    return $expiredOnly ? 'mcp.notice.deleted' : 'mcp.notice.revoked';
}

function mcp_settings_dispatch(mysqli $db, int $uid, string $action, array $input): string
{
    return match ($action) {
        'toggle' => mcp_settings_toggle($db, $uid, $input),
        'create' => mcp_settings_create($db, $uid, $input),
        'revoke' => mcp_settings_remove_token($db, $uid, (int)($input['token_id'] ?? 0), false),
        'delete_expired' => mcp_settings_remove_token($db, $uid, (int)($input['token_id'] ?? 0), true),
        default => throw new InvalidArgumentException('Unknown action'),
    };
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

if (empty($_SESSION['uid']) || !empty($_SESSION['admin']) || empty($_SESSION['torque_user'])) {
    http_response_code(403);
    exit;
}

if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('CSRF check failed');
}

$uid = (int)$_SESSION['uid'];
$action = (string)($_POST['action'] ?? '');
$tokenId = (int)($_POST['token_id'] ?? 0);
$ajaxTokenAction = in_array($action, ['revoke', 'delete_expired'], true)
    && strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0;
$responseStatus = 200;

try {
    $notice = mcp_settings_dispatch($db, $uid, $action, $_POST);
} catch (InvalidArgumentException $e) {
    $responseStatus = 400;
    $notice = mcp_error_l10n_key($e->getMessage());
} catch (Throwable $e) {
    error_log('MCP settings operation failed: ' . $e->getMessage());
    $responseStatus = 500;
    $notice = 'mcp.error.unavailable';
}

if ($ajaxTokenAction) {
    // Send a localized JSON response to xhrResponse/serverError without disclosing secrets.
    require_once __DIR__ . '/translations.php';
    $lang = current_lang();
    $message = (string)($translations[$lang][$notice] ?? $translations['en'][$notice] ?? $notice);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code($responseStatus);
    echo json_encode([
        'ok' => $responseStatus === 200,
        'message' => $message,
        'token_id' => $responseStatus === 200 ? $tokenId : null,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

$_SESSION['mcp_notice'] = $notice;
header('Location: /settings/mcp', true, 303);
exit;

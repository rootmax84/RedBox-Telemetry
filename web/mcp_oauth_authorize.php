<?php
declare(strict_types=1);

/** Only the existing logged-in RedBox account owner can consent to OAuth access. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['uid']) || empty($_SESSION['torque_user']) || !empty($_SESSION['admin'])) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $return = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (str_starts_with($return, '/oauth/authorize?') && strlen($return) <= 3072) {
            $_SESSION['mcp_oauth_login_return'] = $return;
        }
    }
    header('Location: /', true, 303);
    exit;
}

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/mcp/Security.php';
require_once __DIR__ . '/src/mcp/OAuth.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$uid = (int)$_SESSION['uid'];
$h = static fn(mixed $s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

try {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($mcp_trust_forwarded_proto ?? false) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (!$scheme) {
        throw new InvalidArgumentException('OAuth requires HTTPS');
    }
    $account = $db->execute_query('SELECT mcp_enabled,s,lang FROM users WHERE id=?', [$uid])->fetch_assoc();
    if (!$account || (int)$account['mcp_enabled'] !== 1 || (int)$account['s'] <= 0) {
        throw new InvalidArgumentException('Enable MCP in Settings before authorizing an OAuth application');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $clientId = $_GET['client_id'] ?? '';
        if (!is_string($clientId)) {
            throw new InvalidArgumentException('Invalid client ID');
        }
        $metadata = McpOAuth::clientMetadata($clientId);
        $request = McpOAuth::validateAuthorization($_GET, $metadata);
        // Pending consent is server-side; POST never accepts redirect_uri or client_id directly.
        $_SESSION['mcp_oauth_pending'] = $request;
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
            throw new InvalidArgumentException('CSRF verification failed');
        }
        $request = $_SESSION['mcp_oauth_pending'] ?? null;
        unset($_SESSION['mcp_oauth_pending']);
        if (!is_array($request)) {
            throw new InvalidArgumentException('Authorization request expired, please try again');
        }
        if (($_POST['decision'] ?? '') !== 'approve') {
            McpOAuth::redirect($request, ['error' => 'access_denied']);
        }
        $selected = $_POST['scopes'] ?? [];
        // Unrequested read-only scopes are allowed only after explicit selection on this consent page.
        $code = McpOAuth::authorize($db, $uid, $request, $selected, true);
        McpOAuth::redirect($request, ['code' => $code]);
    } else {
        http_response_code(405);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(400);
    $message = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Authorization unavailable';
    error_log('MCP OAuth consent error: ' . get_class($e));
    echo '<!doctype html><html><head><meta charset="utf-8"><title>RedBox OAuth</title></head><body><p>'
        . $h($message) . '</p><a href="/settings/mcp">MCP Settings</a></body></html>';
    exit;
}

require_once __DIR__ . '/translations.php';
$lang = in_array($account['lang'] ?? 'en', ['en', 'ru', 'es', 'de'], true)
    ? $account['lang'] : 'en';
$t = static fn(string $key): string => (string)($translations[$lang][$key] ?? $translations['en'][$key] ?? $key);
$csrf = generate_csrf_token();
?>
<!doctype html>
<html lang="<?= $h($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="referrer" content="no-referrer">
<title><?= $h($t('mcp.oauth.consent.title')) ?> · RedBox Telemetry</title>
<style>
body {margin:0;background:#1b1b1b;color:#eee;font:15px Arial,sans-serif;}
main {max-width:540px;margin:8vh auto;padding:30px;background:#292929;border-radius:8px;box-sizing:border-box;}
h1 {font-size:23px;margin:0 0 12px;}p {line-height:1.5;color:#bbb;}
.scope {display:flex;align-items:flex-start;gap:9px;margin:14px 0;cursor:pointer;}
.scope input {margin:3px 0 0;flex:0 0 auto;accent-color:#961911;}
.scope-details {display:block;min-width:0;}
.scope-description {display:block;margin-top:3px;color:#bbb;font-size:13px;line-height:1.45;}
.scope-optional {display:block;margin-top:4px;font-size:12px;color:#aaa;}
.actions {display:flex;justify-content:flex-end;gap:10px;margin-top:24px;}
button {padding:10px 18px;color:#fff;background:#961911;border:0;border-radius:5px;cursor:pointer;}
button.secondary {background:#555;}
small {color:#aaa;}
@media screen and (max-width: 560px) {main {background:none;margin:0;}}
</style>
</head>
<body>
<main>
<h1><?= $h($t('mcp.oauth.consent.title')) ?></h1>
<p><?= $h($t('mcp.oauth.consent.signed')) ?> <strong><?= $h($_SESSION['torque_user']) ?></strong>. <?= $h($t('mcp.oauth.consent.explain')) ?></p>
<form method="post" action="/oauth/authorize">
<input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
<?php foreach (McpSecurity::SCOPES as $scope): ?>
<?php $requested = in_array($scope, $request['scopes'], true); ?>
<label class="scope">
<input type="checkbox" name="scopes[]" value="<?= $h($scope) ?>" <?= $requested ? 'checked' : '' ?>>
<span class="scope-details">
<strong><?= $h($scope) ?></strong>
<span class="scope-description"><?= $h($t('mcp.scope.' . $scope)) ?></span>
<?php if (!$requested): ?>
<small class="scope-optional"><?= $h($t('mcp.oauth.consent.optional')) ?></small>
<?php endif; ?>
</span>
</label>
<?php endforeach; ?>
<p><small><?= $h($t('mcp.oauth.consent.readonly')) ?></small></p>
<div class="actions">
<button class="secondary" type="submit" name="decision" value="deny"><?= $h($t('mcp.oauth.consent.cancel')) ?></button>
<button type="submit" name="decision" value="approve"><?= $h($t('mcp.oauth.consent.allow')) ?></button>
</div>
</form>
</main>
</body>
</html>

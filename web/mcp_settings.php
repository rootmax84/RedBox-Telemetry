<?php
declare(strict_types=1);

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/mcp/Security.php';

if (empty($_SESSION['uid']) || !empty($_SESSION['admin']) || empty($_SESSION['torque_user'])) {
    header('Location: /');
    exit;
}

$uid = (int)$_SESSION['uid'];
$status = $db->execute_query('SELECT mcp_enabled FROM users WHERE id=?', [$uid])->fetch_assoc();
$enabled = (int)($status['mcp_enabled'] ?? 0);
$tokens = $db->execute_query(
    'SELECT id,label,token_prefix,scopes,created_at,expires_at,revoked_at,last_used_at, '
    . '(expires_at>NOW()) AS not_expired FROM mcp_tokens '
    . 'WHERE user_id=? AND revoked_at IS NULL ORDER BY id DESC LIMIT 100',
    [$uid]
)->fetch_all(MYSQLI_ASSOC);

$notice = (string)($_SESSION['mcp_notice'] ?? '');
unset($_SESSION['mcp_notice']);
$newToken = (string)($_SESSION['mcp_new_token'] ?? '');
unset($_SESSION['mcp_new_token']);

$csrf = generate_csrf_token();
$h = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

include_once __DIR__ . '/translations.php';
$lang = current_lang();
$t = static fn(string $key): string => (string)($translations[$lang][$key] ?? $translations['en'][$key] ?? $key);

$endpointScheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    ? 'https'
    : 'http';
$endpointUrl = $endpointScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/mcp';

// The loading overlays belong inside <body>, not the document head.
$mcp_page_body_overlays = true;
include_once __DIR__ . '/src/head.php';
?>
<link rel="stylesheet" href="<?= $h(version_url('/static/css/mcp.css')) ?>">
<body class="mcp-page">
<?php require __DIR__ . '/src/templates/loading_overlays.php'; ?>

<div class="navbar navbar-default navbar-fixed-top navbar-inverse">
    <div class="container">
        <div id="theme-switch"></div>
        <div class="navbar-header">
            <a class="navbar-brand" href="/">
                <div id="redhead">RedB<img src="/static/img/logo.svg" alt="" style="height:11px;">x</div> Telemetry
            </a>
        </div>
    </div>
</div>

<div class="menu-container">
    <input type="checkbox" id="menu-toggle" class="menu-toggle" />
    <label for="menu-toggle" class="menu-button">
        <span class="hamburger"><span></span><span></span><span></span></span>
    </label>
    <label for="menu-toggle" class="menu-overlay"></label>
    <ul class="menu-list" role="menu">
        <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1" onclick="location.href='/search'">
                <span class="icon" id="search-img"></span><span l10n="search.find"></span>
            </button>
        </li>
        <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1" onclick="favoriteSessions()">
                <span class="icon" id="fav-img"></span><span l10n="fav.btn"></span>
            </button>
        </li>
        <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1" onclick="delSessions()">
                <span class="icon" id="delMass-img"></span><span l10n="func.multi.del"></span>
            </button>
        </li>
        <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1" onclick="pidEdit()">
                <span class="icon" id="editPid-img"></span><span l10n="func.pid"></span>
            </button>
        </li>
        <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1" onclick="showToken()">
                <span class="icon" id="token-img"></span><span l10n="func.token"></span>
            </button>
        </li>
        <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1" onclick="usersSettings()">
                <span class="icon" id="settings-img"></span><span l10n="func.settings"></span>
            </button>
        </li>
        <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1" onclick="remoteRa()">
                <span class="icon" id="remote-ra-rbx-img"></span><span l10n="func.remote"></span>
            </button>
        </li>
        <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1" style="color:#961911">
                <span class="icon" id="mcp-img"></span><span l10n="func.mcp">MCP</span>
            </button>
        </li>
        <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1" onclick="showHints()">
                <span class="icon" id="hint-img"></span><span l10n="hint.button"></span>
            </button>
        </li>
        <li role="none">
            <button class="menu-item" role="menuitem" tabindex="-1" onclick="logout()">
                <span class="icon" id="logout-img"></span><span l10n="logout"></span>
            </button>
        </li>
    </ul>
</div>

<div class="settings-container mcp-settings">
    <div class="settings-unit">
        <h4 l10n="mcp.title"><?= $h($t('mcp.title')) ?></h4>
        <h6 class="mcp-description">
            <span l10n="mcp.intro"><?= $h($t('mcp.intro')) ?></span>
            <code>/mcp</code>.
        </h6>
        <?php if ($notice !== ''): ?>
            <div class="mcp-alert" l10n="<?= $h($notice) ?>"><?= $h($t($notice)) ?></div>
        <?php endif; ?>
    </div>
    <hr>

    <?php if ($newToken !== ''): ?>
        <div class="settings-unit mcp-secret">
            <h4 l10n="mcp.secret.title"><?= $h($t('mcp.secret.title')) ?></h4>
            <h6 class="mcp-description" l10n="mcp.secret.help"><?= $h($t('mcp.secret.help')) ?></h6>
            <div class="mcp-token-field">
                <input id="mcp-new-token" type="text" class="form-control mcp-token-input"
                       value="<?= $h($newToken) ?>" readonly
                       aria-label="<?= $h($t('mcp.secret.title')) ?>" autocomplete="off" spellcheck="false">
                <button type="button" class="mcp-copy-token" onclick="copyMcpToken()"
                        title="<?= $h($t('mcp.copy_token')) ?>"
                        aria-label="<?= $h($t('mcp.copy_token')) ?>">
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="8" y="8" width="12" height="12" rx="2"></rect>
                        <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path>
                    </svg>
                </button>
            </div>
        </div>
        <hr>
    <?php endif; ?>

    <div class="settings-unit">
        <h4 l10n="mcp.access.title"><?= $h($t('mcp.access.title')) ?></h4>
        <p>
            <span l10n="mcp.endpoint"><?= $h($t('mcp.endpoint')) ?></span>:
            <a class="mcp-endpoint-link" href="<?= $h($endpointUrl) ?>" target="_blank" rel="noopener noreferrer">
                <code><?= $h($endpointUrl) ?></code>
            </a>
        </p>
        <form method="post" action="/settings/mcp/tokens">
            <input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="toggle">
            <label>
                <input type="checkbox" name="mcp_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                <span l10n="mcp.access.enable"><?= $h($t('mcp.access.enable')) ?></span>
            </label>
            <p><small l10n="mcp.access.help"><?= $h($t('mcp.access.help')) ?></small></p>
            <div class="cntr">
                <button class="btn btn-info btn-sm" type="submit"
                        l10n="mcp.access.save"><?= $h($t('mcp.access.save')) ?></button>
            </div>
        </form>
    </div>
    <hr>

    <div class="settings-unit">
        <h4 l10n="mcp.create.title"><?= $h($t('mcp.create.title')) ?></h4>
        <h6 class="mcp-description" l10n="mcp.create.help"><?= $h($t('mcp.create.help')) ?></h6>
        <form method="post" action="/settings/mcp/tokens">
            <input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <div class="mcp-field">
                <label>
                    <span l10n="mcp.token.name"><?= $h($t('mcp.token.name')) ?></span>
                    <input class="form-control" maxlength="80" required name="label"
                           l10n-placeholder="mcp.token.placeholder"
                           placeholder="<?= $h($t('mcp.token.placeholder')) ?>" autocomplete="off">
                </label>
            </div>
            <fieldset>
                <legend l10n="mcp.permissions"><?= $h($t('mcp.permissions')) ?></legend>
                <?php foreach (McpSecurity::SCOPES as $scope): ?>
                    <label class="mcp-scope">
                        <input type="checkbox" name="scopes[]" value="<?= $h($scope) ?>"
                               <?= $scope === 'telemetry:read' ? 'checked' : '' ?>>
                        <b><?= $h($scope) ?></b> —
                        <span l10n="<?= $h('mcp.scope.' . $scope) ?>"><?= $h($t('mcp.scope.' . $scope)) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <div class="settings--cell mcp-expiry-field">
                <div>
                    <label for="mcp-token-expiry" l10n="mcp.expiry"><?= $h($t('mcp.expiry')) ?></label>
                    <select id="mcp-token-expiry" class="form-control" name="days">
                        <?php foreach ([7, 30, 90, 365] as $days): ?>
                            <option value="<?= $days ?>" <?= $days === 30 ? 'selected' : '' ?>
                                    l10n="mcp.days.<?= $days ?>"><?= $h($t('mcp.days.' . $days)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="cntr">
                <button class="btn btn-info btn-sm" type="submit"
                        l10n="mcp.generate"><?= $h($t('mcp.generate')) ?></button>
            </div>
        </form>
    </div>
    <hr>

    <div class="settings-unit mcp-wide">
        <h4 l10n="mcp.tokens.title"><?= $h($t('mcp.tokens.title')) ?></h4>
        <div class="mcp-table-wrap">
            <table class="table table-bordered table-condensed">
                <thead>
                    <tr>
                        <?php foreach (['mcp.table.name', 'mcp.table.scopes', 'mcp.table.created', 'mcp.table.expires', 'mcp.table.last_used', 'mcp.table.status', 'mcp.table.action'] as $key): ?>
                            <th l10n="<?= $key ?>"><?= $h($t($key)) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tokens as $token): ?>
                        <?php
                        $active = $token['revoked_at'] === null && (int)$token['not_expired'] === 1;
                        $tokenAction = $active ? 'revoke' : 'delete_expired';
                        $tokenActionLabel = $active ? 'mcp.revoke' : 'mcp.delete';
                        $tokenConfirmation = $active ? 'mcp.confirm.revoke' : 'mcp.confirm.delete';
                        $state = $token['revoked_at'] !== null
                            ? 'revoked'
                            : ($active ? ($enabled ? 'active' : 'disabled') : 'expired');
                        ?>
                        <tr class="mcp-token-row" data-token-id="<?= (int)$token['id'] ?>">
                            <td>
                                <?= $h($token['label']) ?>
                                <small class="mcp-hint"><?= $h($token['token_prefix']) ?>…</small>
                            </td>
                            <td><?= $h(implode(' · ', McpSecurity::displayedScopes((string)$token['scopes'])) ?: '—') ?></td>
                            <td><?= $h($token['created_at']) ?></td>
                            <td><?= $h($token['expires_at']) ?></td>
                            <td>
                                <?php if ($token['last_used_at'] === null): ?>
                                    <span l10n="mcp.never"><?= $h($t('mcp.never')) ?></span>
                                <?php else: ?>
                                    <?= $h($token['last_used_at']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span l10n="mcp.status.<?= $state ?>"><?= $h($t('mcp.status.' . $state)) ?></span>
                            </td>
                            <td>
                                <form method="post" action="/settings/mcp/tokens"
                                      onsubmit="return confirmMcp(event, '<?= $h($tokenConfirmation) ?>')">
                                    <input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
                                    <input type="hidden" name="action" value="<?= $h($tokenAction) ?>">
                                    <input type="hidden" name="token_id" value="<?= (int)$token['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit"
                                            l10n="<?= $h($tokenActionLabel) ?>"><?= $h($t($tokenActionLabel)) ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tokens): ?>
                        <tr><td colspan="7" l10n="mcp.tokens.empty"><?= $h($t('mcp.tokens.empty')) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
window.MCP_TEXT_FALLBACK = <?= json_encode([
    'mcp.confirm.revoke' => $t('mcp.confirm.revoke'),
    'mcp.confirm.delete' => $t('mcp.confirm.delete'),
    'mcp.notice.deleted' => $t('mcp.notice.deleted'),
    'mcp.error.not_expired' => $t('mcp.error.not_expired'),
    'mcp.error.unavailable' => $t('mcp.error.unavailable'),
    'mcp.tokens.empty' => $t('mcp.tokens.empty'),
    'mcp.dialog.title' => $t('mcp.dialog.title'),
    'mcp.dialog.yes' => $t('mcp.dialog.yes'),
    'mcp.dialog.no' => $t('mcp.dialog.no'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $h(version_url('/static/js/mcp_settings.js')) ?>"></script>
</body>
</html>

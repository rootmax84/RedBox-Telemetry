<?php
    require_once __DIR__ . '/src/db.php';
    require_once __DIR__ . '/src/db_limits.php';
    require_once __DIR__ . '/src/mcp/Security.php';
    include_once __DIR__ . '/translations.php';

    // The unified settings page is account-only, just like the old MCP screen.
    if (empty($_SESSION['uid']) || !empty($_SESSION['admin']) || empty($_SESSION['torque_user'])) {
        header('Location: /');
        exit;
    }

    $uid = (int) $_SESSION['uid'];
    $setqry = $db->execute_query(
        "SELECT tg_token,tg_chatid,speed,temp,pressure,boost,time,sessions_filter,api_gps,mcp_enabled FROM users WHERE user=?",
        [$username]
    )->fetch_row();
    [$token, $chatid, $speed, $temp, $pressure, $boost, $time, $sessions_filter, $api_gps, $enabled] = $setqry;

    // Existing MCP behavior is unchanged: read only the signed-in user's tokens.
    $enabled = (int)$enabled;
    $tokens = $db->execute_query(
        'SELECT id,label,token_prefix,scopes,created_at,expires_at,revoked_at,last_used_at, '
        . '(expires_at>NOW()) AS not_expired FROM mcp_tokens '
        . 'WHERE user_id=? AND revoked_at IS NULL ORDER BY id DESC LIMIT 100', [$uid]
    )->fetch_all(MYSQLI_ASSOC);
    $oauthConnections = $db->execute_query(
        'SELECT id,client_id,scopes,created_at,expires_at,refresh_expires_at,last_used_at '
        . 'FROM mcp_oauth_tokens WHERE user_id=? AND revoked_at IS NULL AND refresh_expires_at>NOW() '
        . 'ORDER BY id DESC LIMIT 50', [$uid]
    )->fetch_all(MYSQLI_ASSOC);

    $notice = (string)($_SESSION['mcp_notice'] ?? '');
    unset($_SESSION['mcp_notice']);
    $newToken = (string)($_SESSION['mcp_new_token'] ?? '');
    unset($_SESSION['mcp_new_token']);
    $csrf = generate_csrf_token();
    $h = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $lang = current_lang();
    $t = static fn(string $key): string => (string)($translations[$lang][$key] ?? $translations['en'][$key] ?? $key);
    $endpointScheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ? 'https' : 'http';
    $endpointUrl = $endpointScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/mcp';

    $db->close();
    $settings_unified_page = true;
    include_once __DIR__ . '/src/head.php';
?>
    <body class="settings-page">
        <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
    <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
         <div class="new-session"><a href='/' l10n='sess.new'></a></div>
         <div class="storage-usage-img"></div>
    <?php } ?>
            <div class="container">
              <div id="theme-switch"></div>
                <div class="navbar-header">
		 <a class="navbar-brand" href="/"><div id="redhead">RedB<img src="/static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a>
                </div>
              </div>
            </div>
  <div class="menu-container">
    <input type="checkbox" id="menu-toggle" class="menu-toggle"/>

    <label for="menu-toggle" class="menu-button">
      <span class="hamburger">
        <span></span>
        <span></span>
        <span></span>
      </span>
    </label>

    <label for="menu-toggle" class="menu-overlay"></label>

    <ul class="menu-list" role="menu">
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="location.href='/search'">
          <span class="icon" id="search-img"></span>
          <span l10n="search.find"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="favoriteSessions()">
          <span class="icon" id="fav-img"></span>
          <span l10n="fav.btn"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="delSessions()">
          <span class="icon" id="delMass-img"></span>
          <span l10n="func.multi.del"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="pidEdit()">
          <span class="icon" id="editPid-img"></span>
          <span l10n="func.pid"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="showToken()">
          <span class="icon" id="token-img"></span>
          <span l10n="func.token"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" style="color:#961911">
          <span class="icon" id="settings-img"></span>
          <span l10n="func.settings"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="remoteRa()">
          <span class="icon" id="remote-ra-rbx-img"></span>
          <span l10n="func.remote"></span>
        </button>
      </li>

      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="showHints()">
          <span class="icon" id="hint-img"></span>
          <span l10n="hint.button"></span>
        </button>
      </li>
      <li role="none">
        <button class="menu-item" role="menuitem" tabindex="-1" onclick="logout()">
          <span class="icon" id="logout-img"></span>
          <span l10n="logout"></span>
        </button>
      </li>
    </ul>
  </div>

<main class="container-remote settings-shell" id="settings" aria-label="<?= $h($t('func.settings')) ?>">
    <div class="tabs settings-tabs" role="tablist" aria-label="<?= $h($t('func.settings')) ?>">
        <button type="button" class="tab active" role="tab" id="settings-tab-general"
                data-tab="settings-general" aria-controls="settings-general" aria-selected="true" tabindex="0"
                l10n="settings.tab.general"><?= $h($t('settings.tab.general')) ?></button>
        <button type="button" class="tab" role="tab" id="settings-tab-notifications"
                data-tab="settings-notifications" aria-controls="settings-notifications" aria-selected="false" tabindex="-1"
                l10n="settings.tab.notifications"><?= $h($t('settings.tab.notifications')) ?></button>
        <button type="button" class="tab" role="tab" id="settings-tab-security"
                data-tab="settings-security" aria-controls="settings-security" aria-selected="false" tabindex="-1"
                l10n="settings.tab.security"><?= $h($t('settings.tab.security')) ?></button>
        <button type="button" class="tab" role="tab" id="settings-tab-mcp"
                data-tab="settings-mcp" aria-controls="settings-mcp" aria-selected="false" tabindex="-1"
                l10n="settings.tab.mcp"><?= $h($t('settings.tab.mcp')) ?></button>
    </div>
    <?php if ($notice !== ''): ?>
        <div class="mcp-alert settings-notice" role="status" l10n="<?= $h($notice) ?>"><?= $h($t($notice)) ?></div>
    <?php endif; ?>
    <section id="settings-general" class="tab-content settings-panel active"
             role="tabpanel" aria-labelledby="settings-tab-general">
      <div class="settings-group">
<div class="card-remote settings-card">
             <h4 l10n="user.set.title"></h4>
             <h6 style="color:#777" l10n="user.set.label"></h6>
		<form method="POST" action="/settings/save" onsubmit="return submitForm(this);">
		    <table class="remote-table remote-table-horizontal settings-form-table">
                <tbody>
                    <tr>
                        <td class="setting-name"><label for="speed" l10n="user.set.spd"></label></td>
                        <td class="setting-value"><select id="speed" class="form-control" name="speed">
		            <option value="1"<?php if ($speed == "No conversion") echo ' selected'; ?> l10n="user.conv.no"></option>
		            <option value="2"<?php if ($speed == "km to miles") echo ' selected'; ?> l10n="user.conv.km.to.miles"></option>
		            <option value="3"<?php if ($speed == "miles to km") echo ' selected'; ?> l10n="user.conv.miles.to.km"></option>
		        </select></td>
                    </tr>
                    <tr>
                        <td class="setting-name"><label for="temp" l10n="user.set.temp"></label></td>
                        <td class="setting-value"><select id="temp" class="form-control" name="temp">
		            <option value="1"<?php if ($temp == "No conversion") echo ' selected'; ?> l10n="user.conv.no"></option>
		            <option value="2"<?php if ($temp == "Celsius to Fahrenheit") echo ' selected'; ?> l10n="user.conv.c.to.f"></option>
		            <option value="3"<?php if ($temp == "Fahrenheit to Celsius") echo ' selected'; ?> l10n="user.conv.f.to.c"></option>
		        </select></td>
                    </tr>
                    <tr>
                        <td class="setting-name"><label for="pressure" l10n="user.set.press"></label></td>
                        <td class="setting-value"><select id="pressure" class="form-control" name="pressure">
		            <option value="1"<?php if ($pressure == "No conversion") echo ' selected'; ?> l10n="user.conv.no"></option>
		            <option value="2"<?php if ($pressure == "Psi to Bar") echo ' selected'; ?> l10n="user.conv.psi.to.bar"></option>
		            <option value="3"<?php if ($pressure == "Bar to Psi") echo ' selected'; ?> l10n="user.conv.bar.to.psi"></option>
		        </select></td>
                    </tr>
                    <tr>
                        <td class="setting-name"><label for="boost" l10n="user.set.boost"></label></td>
                        <td class="setting-value"><select id="boost" class="form-control" name="boost">
		            <option value="1"<?php if ($boost == "No conversion") echo ' selected'; ?> l10n="user.conv.no"></option>
		            <option value="2"<?php if ($boost == "Psi to Bar") echo ' selected'; ?> l10n="user.conv.psi.to.bar"></option>
		            <option value="3"<?php if ($boost == "Bar to Psi") echo ' selected'; ?> l10n="user.conv.bar.to.psi"></option>
		        </select></td>
                    </tr>
                    <tr>
                        <td class="setting-name"><label for="time" l10n="user.set.time"></label></td>
                        <td class="setting-value"><select id="time" class="form-control" name="time">
		            <option value="1"<?php if ($time == "24") echo ' selected'; ?> l10n="user.conv.time.24"></option>
		            <option value="2"<?php if ($time == "12") echo ' selected'; ?> l10n="user.conv.time.12"></option>
		        </select></td>
                    </tr>
                    <tr>
                        <td class="setting-name"><label for="sessions_filter" l10n="sessions.filter"></label></td>
                        <td class="setting-value"><select id="sessions_filter" class="form-control" name="sessions_filter">
		            <option value="1"<?php if ($sessions_filter == "1") echo ' selected'; ?> l10n="btn.no"></option>
		            <option value="2"<?php if ($sessions_filter == "2") echo ' selected'; ?>>75%</option>
		            <option value="3"<?php if ($sessions_filter == "3") echo ' selected'; ?>>50%</option>
		            <option value="4"<?php if ($sessions_filter == "4") echo ' selected'; ?>>33%</option>
		            <option value="5"<?php if ($sessions_filter == "5") echo ' selected'; ?>>25%</option>
		        </select></td>
                    </tr>
                    <tr>
                        <td class="setting-name"><label for="api_gps" l10n="api.gps"></label></td>
                        <td class="setting-value"><select id="api_gps" class="form-control" name="api_gps">
		            <option value="0"<?php if ($api_gps == "0") echo ' selected'; ?> l10n="btn.no"></option>
		            <option value="1"<?php if ($api_gps == "1") echo ' selected'; ?> l10n="btn.yes"></option>
		        </select></td>
                    </tr>
                    <tr>
                        <td class="setting-name"><label for="lang" l10n="user.set.lang"></label></td>
                        <td class="setting-value"><select id="lang" class="form-control" name="lang">
		            <option value="en">English</option>
		            <option value="ru">Русский</option>
		            <option value="es">Español</option>
		            <option value="de">Deutsch</option>
		        </select></td>
                    </tr>
                </tbody>
            </table>
            <div class="cntr">
		        <button class="btn btn-info btn-sm" type="submit" l10n="btn.save"></button>
		    </div>
		</form>
        </div>
      </div>
    </section>
    <section id="settings-notifications" class="tab-content settings-panel"
             role="tabpanel" aria-labelledby="settings-tab-notifications" hidden>
      <div class="settings-group">
<div class="card-remote settings-card">
             <h4 l10n="user.tg.title"></h4>
             <h6 style="color:#777" l10n="user.tg.label"></h6>
		<form method="POST" action="/settings/save" onsubmit="return submitForm(this);">
		<table class="remote-table remote-table-horizontal settings-form-table">
                <tbody>
                    <tr><td class="setting-name"><label for="settings-tg-token" l10n="user.tg.token"></label></td>
                        <td class="setting-value"><div class="password-toggle">
			<input class="form-control password-input" type="password" id="settings-tg-token" name="tg_token" l10n-placeholder="user.tg.token" maxlength="64" autocomplete="new-password" value="<?php echo escapeHtml($token); ?>">
			<button type="button" class="password-toggle__btn">
			    <span class="password-toggle__icon"></span>
			</button>
		    </div></td></tr>
                    <tr><td class="setting-name"><label for="settings-tg-chatid" l10n="user.tg.id"></label></td>
                        <td class="setting-value"><input class="form-control" type="number" id="settings-tg-chatid" name="tg_chatid" l10n-placeholder="user.tg.id" autocomplete="new-password" value="<?php echo escapeHtml($chatid); ?>"></td></tr>
                </tbody>
            </table>
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit" l10n="btn.save"></button></div>
		</form>
        </div>
      </div>
    </section>
    <section id="settings-security" class="tab-content settings-panel"
             role="tabpanel" aria-labelledby="settings-tab-security" hidden>
      <div class="settings-group">
<div class="card-remote settings-card">
             <h4 l10n="share.sec.title"></h4>
             <h6 style="color:#777" l10n="share.sec.label"></h6>
		<form method="POST" action="/settings/save" onsubmit="return submitForm(this);">
		 <input class="form-control" type="text" name="share_secret" value="1" style="display:none">
		 <div class="cntr"><button class="btn btn-info btn-sm" type="submit" l10n="btn.renew"></button></div>
		</form>
        </div>

<div class="card-remote settings-card">
             <h4 l10n="user.pwd.title"></h4>
             <h6 style="color:#777" l10n="user.pwd.label"></h6>
		<form method="POST" action="/settings/save" onsubmit="return submitForm(this);">
		<table class="remote-table remote-table-horizontal settings-form-table">
                <tbody>
                    <tr><td class="setting-name"><label for="settings-old_p" l10n="user.pwd.curr"></label></td>
                        <td class="setting-value"><div class="password-toggle">
			<input class="form-control password-input" type="password" id="settings-old_p" name="old_p"  l10n-placeholder="user.pwd.curr" maxlength="64" autocomplete="new-password" required>
			<button type="button" class="password-toggle__btn">
			    <span class="password-toggle__icon"></span>
			</button>
		    </div></td></tr>
                    <tr><td class="setting-name"><label for="settings-new_p1" l10n="user.pwd.new"></label></td>
                        <td class="setting-value"><div class="password-toggle">
			<input class="form-control password-input" type="password" id="settings-new_p1" name="new_p1" l10n-placeholder="user.pwd.new" maxlength="64" autocomplete="new-password" required>
			<button type="button" class="password-toggle__btn">
			    <span class="password-toggle__icon"></span>
			</button>
		    </div></td></tr>
                    <tr><td class="setting-name"><label for="settings-new_p2" l10n="user.pwd.repeat"></label></td>
                        <td class="setting-value"><div class="password-toggle">
			<input class="form-control password-input" type="password" id="settings-new_p2" name="new_p2" l10n-placeholder="user.pwd.repeat" maxlength="64" autocomplete="new-password" required>
			<button type="button" class="password-toggle__btn">
			    <span class="password-toggle__icon"></span>
			</button>
		    </div></td></tr>
                </tbody>
            </table>
            <div class="cntr"><button class="btn btn-info btn-sm" type="submit" l10n="btn.save"></button></div>
		</form>
        </div>
      </div>
    </section>
    <section id="settings-mcp" class="tab-content settings-panel"
             role="tabpanel" aria-labelledby="settings-tab-mcp" hidden>
      <div class="mcp-settings">
<?php if ($newToken !== ''): ?>
        <div class="card-remote settings-card settings-unit mcp-secret">
            <h4 l10n="mcp.secret.title"><?= $h($t('mcp.secret.title')) ?></h4>
            <h6 class="mcp-description" l10n="mcp.secret.help"><?= $h($t('mcp.secret.help')) ?></h6>
            <div class="mcp-token-field">
                <input id="mcp-new-token" type="text" class="form-control mcp-token-input"
                       value="<?= $h($newToken) ?>" readonly
                       aria-label="<?= $h($t('mcp.secret.title')) ?>" autocomplete="off" spellcheck="false">
                <button type="button" class="mcp-copy-token" onclick="copyMcpToken()"
                        title="<?= $h($t('mcp.copy_token')) ?>"
                        aria-label="<?= $h($t('mcp.copy_token')) ?>"
                        data-copy-label="<?= $h($t('mcp.copy_token')) ?>"
                        data-copied-label="<?= $h($t('mcp.copied_token')) ?>">
                    <svg class="mcp-copy-icon" viewBox="0 0 24 24" width="19" height="19" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="8" y="8" width="12" height="12" rx="2"></rect>
                        <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path>
                    </svg>
                    <svg class="mcp-copied-icon" viewBox="0 0 24 24" width="19" height="19" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M4 12.5l5.5 5.5L20 7"></path>
                    </svg>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div class="card-remote settings-card settings-unit">
        <h4 l10n="mcp.access.title"><?= $h($t('mcp.access.title')) ?></h4>

        <form method="post" action="/settings/mcp/tokens">
            <input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="toggle">
            <table class="remote-table remote-table-horizontal settings-form-table">
                <tbody>
                    <tr>
                        <td class="setting-name"><span l10n="mcp.endpoint"><?= $h($t('mcp.endpoint')) ?></span></td>
                        <td class="setting-value">
                            <a class="mcp-endpoint-link" href="<?= $h($endpointUrl) ?>" target="_blank" rel="noopener noreferrer"><code><?= $h($endpointUrl) ?></code></a>
                        </td>
                    </tr>
                    <tr>
                        <td class="setting-name"><label for="settings-mcp-enable" l10n="mcp.access.enable"><?= $h($t('mcp.access.enable')) ?></label></td>
                        <td class="setting-value"><input id="settings-mcp-enable" type="checkbox" name="mcp_enabled" value="1" <?= $enabled ? 'checked' : '' ?>></td>
                    </tr>
                </tbody>
            </table>
            <p class="settings-form-help"><small l10n="mcp.access.help"><?= $h($t('mcp.access.help')) ?></small></p>
            <div class="cntr">
                <button class="btn btn-info btn-sm" type="submit"
                        l10n="mcp.access.save"><?= $h($t('mcp.access.save')) ?></button>
            </div>
        </form>
    </div>

<div class="card-remote settings-card settings-unit">
        <h4 l10n="mcp.create.title"><?= $h($t('mcp.create.title')) ?></h4>
        <h6 class="mcp-description" l10n="mcp.create.help"><?= $h($t('mcp.create.help')) ?></h6>
        <form method="post" action="/settings/mcp/tokens">
            <input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <table class="remote-table remote-table-horizontal settings-form-table">
                <tbody>
                    <tr>
                        <td class="setting-name"><label for="settings-mcp-token-label" l10n="mcp.token.name"><?= $h($t('mcp.token.name')) ?></label></td>
                        <td class="setting-value"><input class="form-control" maxlength="80" required id="settings-mcp-token-label" name="label"
                           l10n-placeholder="mcp.token.placeholder"
                           placeholder="<?= $h($t('mcp.token.placeholder')) ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <td class="setting-name"><span id="settings-mcp-scopes-title" l10n="mcp.permissions"><?= $h($t('mcp.permissions')) ?></span></td>
                        <td class="setting-value"><fieldset class="settings-mcp-scopes" aria-labelledby="settings-mcp-scopes-title">
                            <?php foreach (McpSecurity::SCOPES as $scope): ?>
                    <label class="mcp-scope">
                        <input type="checkbox" name="scopes[]" value="<?= $h($scope) ?>"
                               <?= $scope === 'telemetry:read' ? 'checked' : '' ?>>
                        <b><?= $h($scope) ?></b> —
                        <span l10n="<?= $h('mcp.scope.' . $scope) ?>"><?= $h($t('mcp.scope.' . $scope)) ?></span>
                    </label>
                <?php endforeach; ?>
                        </fieldset></td>
                    </tr>
                    <tr>
                        <td class="setting-name"><label for="mcp-token-expiry" l10n="mcp.expiry"><?= $h($t('mcp.expiry')) ?></label></td>
                        <td class="setting-value"><select id="mcp-token-expiry" class="form-control" name="days">
                        <?php foreach ([7, 30, 90, 365] as $days): ?>
                            <option value="<?= $days ?>" <?= $days === 30 ? 'selected' : '' ?>
                                    l10n="mcp.days.<?= $days ?>"><?= $h($t('mcp.days.' . $days)) ?></option>
                        <?php endforeach; ?>
                    </select></td>
                    </tr>
                </tbody>
            </table>
            <div class="cntr">
                <button class="btn btn-info btn-sm" type="submit"
                        l10n="mcp.generate"><?= $h($t('mcp.generate')) ?></button>
            </div>
        </form>
    </div>
    <div class="card-remote settings-card settings-unit mcp-wide">
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

<div class="card-remote settings-card settings-unit mcp-wide">
        <h4 l10n="mcp.oauth.connections.title"><?= $h($t('mcp.oauth.connections.title')) ?></h4>
        <?php if (empty($mcp_oauth_issuer)): ?>
            <p class="mcp-description" l10n="mcp.oauth.not_configured"><?= $h($t('mcp.oauth.not_configured')) ?></p>
        <?php endif; ?>
        <div class="mcp-table-wrap">
            <table class="table table-bordered table-condensed">
                <thead><tr>
                    <th l10n="mcp.oauth.client"><?= $h($t('mcp.oauth.client')) ?></th>
                    <th l10n="mcp.table.scopes"><?= $h($t('mcp.table.scopes')) ?></th>
                    <th l10n="mcp.table.created"><?= $h($t('mcp.table.created')) ?></th>
                    <th l10n="mcp.oauth.refresh_expires"><?= $h($t('mcp.oauth.refresh_expires')) ?></th>
                    <th l10n="mcp.table.action"><?= $h($t('mcp.table.action')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($oauthConnections as $connection): ?>
                        <tr>
                            <td style="overflow-wrap:anywhere"><?= $h($connection['client_id']) ?></td>
                            <td><?= $h(str_replace(' ', ' · ', $connection['scopes'])) ?></td>
                            <td><?= $h($connection['created_at']) ?></td>
                            <td><?= $h($connection['refresh_expires_at']) ?></td>
                            <td>
                                <form method="post" action="/settings/mcp/tokens"
                                      onsubmit="return confirmMcp(event, 'mcp.oauth.confirm.revoke')">
                                    <input type="hidden" name="csrf_token" value="<?= $h($csrf) ?>">
                                    <input type="hidden" name="action" value="oauth_revoke">
                                    <input type="hidden" name="token_id" value="<?= (int)$connection['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit"
                                            l10n="mcp.revoke"><?= $h($t('mcp.revoke')) ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$oauthConnections): ?>
                        <tr><td colspan="5" l10n="mcp.oauth.empty"><?= $h($t('mcp.oauth.empty')) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
      </div>
    </section>
</main>
<script>
window.MCP_TEXT_FALLBACK = <?= json_encode([
    'mcp.confirm.revoke' => $t('mcp.confirm.revoke'),
    'mcp.oauth.confirm.revoke' => $t('mcp.oauth.confirm.revoke'),
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
<script src="<?php echo escapeHtml(version_url('/static/js/users_settings.js')); ?>"></script>
<script src="<?php echo escapeHtml(version_url('/static/js/settings_tabs.js')); ?>"></script>
</body>
</html>

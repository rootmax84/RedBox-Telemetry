<?php
/**
 * Home page controller.
 * Логика перенесена из старого web/index.php без изменений.
 */

require_once __DIR__ . '/../db.php';
// Resume the pending OAuth consent after an ordinary RedBox login.
if (!empty($_SESSION['torque_user']) && !empty($_SESSION['mcp_oauth_login_return'])) {
    $url = (string)$_SESSION['mcp_oauth_login_return'];
    unset($_SESSION['mcp_oauth_login_return']);
    if (str_starts_with($url, '/oauth/authorize?') && strlen($url) <= 3072) {
        header('Location: ' . $url, true, 303);
        exit;
    }
}

require_once __DIR__ . '/../db_limits.php';
require_once __DIR__ . '/../../plot.php';
require_once __DIR__ . '/../../timezone.php';
include_once __DIR__ . '/../../translations.php';
include_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../redis.php';

$data = require __DIR__ . '/../pages/index.php';

include_once __DIR__ . '/../head.php';
require __DIR__ . '/../templates/index/page.php';

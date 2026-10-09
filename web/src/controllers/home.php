<?php
/**
 * Home page controller.
 * Логика перенесена из старого web/index.php без изменений.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../db_limits.php';
require_once __DIR__ . '/../../plot.php';
require_once __DIR__ . '/../../timezone.php';
include_once __DIR__ . '/../../translations.php';
include_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../redis.php';

$data = require __DIR__ . '/../pages/index.php';

include_once __DIR__ . '/../head.php';
require __DIR__ . '/../templates/index/page.php';

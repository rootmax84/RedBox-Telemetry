<?php

// creds migration
if (file_exists(__DIR__.'/creds.php')) {
    rename(__DIR__.'/creds.php', __DIR__.'/src/creds.php');
}

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/db_limits.php';
require_once __DIR__ . '/plot.php';
require_once __DIR__ . '/timezone.php';
include_once __DIR__ . '/translations.php';
include_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/redis.php';

$data = require __DIR__ . '/src/pages/index.php';

include_once __DIR__ . '/src/head.php';
require __DIR__ . '/src/templates/index/page.php';

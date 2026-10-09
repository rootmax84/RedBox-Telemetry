<?php
/** @var Router $router */

// ════════════════════════════════════════════════════════════
//  Public / Auth
// ════════════════════════════════════════════════════════════
$router->any ('/',              'src/controllers/home.php');
$router->any ('/login',         'src/controllers/home.php');
$router->get ('/logout',        'src/controllers/logout.php');
$router->any ('/auth',          'auth.php');
$router->any ('/catch',         'catch.php');
$router->any ('/maintenance',   'maintenance.php');

// ════════════════════════════════════════════════════════════
//  User pages
// ════════════════════════════════════════════════════════════
$router->any ('/search',              'search.php');
$router->post('/search/query',        'search_processor.php');
$router->any ('/favorites',           'fav_sessions.php');
$router->any ('/settings',            'users_settings.php');
$router->post('/settings/save',       'users_handler.php');
$router->any ('/settings/remote',     'users_remote.php');
$router->any ('/settings/token',      'users_handler.php');   // ?get_token || ?renew_token
$router->any ('/remote',              'users_remote.php');
$router->any ('/pids',                'pid_edit.php');
$router->post('/pids/commit',         'pid_commit.php');

// ════════════════════════════════════════════════════════════
//  Sessions
// ════════════════════════════════════════════════════════════
$router->get ('/sessions',              'del_sessions.php');
$router->post('/sessions/delete',       'del_sessions.php');
$router->post ('/sessions/delete-one',   'del_session.php');
$router->get ('/sessions/merge',        'merge_sessions.php');
$router->post ('/sessions/merge',        'merge_sessions.php');
$router->any ('/sessions/export',       'export.php');
$router->any ('/sessions/favorite',     'favorite.php');
$router->any ('/sessions/stream',       'stream.php');
$router->any ('/sessions/filtered',     'get_filtered_sessions.php');
$router->any ('/sessions/{id:\d+}',     'src/controllers/home.php');

// ════════════════════════════════════════════════════════════
//  Import (RedManage / Torque)
// ════════════════════════════════════════════════════════════
$router->post('/import/redlog', 'import_redlog.php');
$router->post('/import/torque', 'import_torque.php');

// ════════════════════════════════════════════════════════════
//  Share / plot
// ════════════════════════════════════════════════════════════
$router->any ('/share',        'share.php');
$router->any ('/share/remote', 'share_remote.php');
$router->any ('/plot',         'plot.php');
$router->post('/sign',         'sign.php');

// ════════════════════════════════════════════════════════════
//  Device API (firmware update needed to 1.65.4+)
// ════════════════════════════════════════════════════════════
$router->any('/api/upload', 'ul.php');
$router->any('/upload', 'ul.php'); // Legacy path
$router->any('/api/remote', 'remote.php');
$router->any('/api/stream', 'stream_json.php');
$router->any('/api/token',  'get_token.php');

// ════════════════════════════════════════════════════════════
//  Async tasks
// ════════════════════════════════════════════════════════════
$router->any('/tasks',                   'user_tasks.php');
$router->any('/tasks/{id:[a-f0-9]{32}}', 'task_status.php');

// ════════════════════════════════════════════════════════════
//  Utility
// ════════════════════════════════════════════════════════════
$router->any('/translations', 'translations.php');
$router->any('/timezone',     'timezone.php');
$router->any('/url',          'url.php');
$router->any('/adminer',      'adminer.php');

// ════════════════════════════════════════════════════════════
//  Admin
// ════════════════════════════════════════════════════════════
$router->any ('/admin/users',         'users_admin.php');
$router->post('/admin/users/handler', 'users_handler.php');
$router->any ('/admin/redis',         'redis_stats.php');

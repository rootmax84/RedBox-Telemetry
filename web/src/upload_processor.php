<?php
/**
 * Upload processor — запись аплоадов в shared-таблицы.
 *
 * Используется:
 *   - ul.php       (inline fallback, Redis недоступен)
 *   - worker.php   (основной путь через Redis Stream)
 *
 * Контекст $ctx:
 *   username, user_id, lang, tg_token, tg_chatid, tg_socks_proxy,
 *   translations, ip
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/redis.php';

function processUpload(mysqli $db, array $ctx, string $kind, $payload): void
{
    // helpers.php использует $GLOBALS['username'] / $GLOBALS['user_id']
    // внутри cache_flush(), processSessionStartRecord(), insert_log_*.
    $GLOBALS['username'] = $ctx['username'];
    $GLOBALS['user_id']  = $ctx['user_id'];

    switch ($kind) {
        case 'bulk':
            processBulkRecords($db, $ctx, $payload);
            break;

        case 'single':
            processSingleRequest($db, $ctx, $payload);
            break;

        default:
            throw new InvalidArgumentException("Unknown upload kind: $kind");
    }
}

/* ───────────────────────── Bulk (RedManage JSON) ───────────────────────── */

function processBulkRecords(mysqli $db, array $ctx, array $records): void
{
    $user_id      = (int)$ctx['user_id'];
    $lang         = $ctx['lang'];
    $translations = $ctx['translations'];

    $pendingNotifications = [];

    $db->begin_transaction();
    try {
        $log_rows            = [];  // для insert_log_rows_bulk
        $sessionUpserts      = [];  // UPSERT-и в sessions
        $sessionStartRecords = [];  // записи с profileName
        $all_pids            = [];  // уникальные PID-ключи для ensure_pids_exist

        foreach ($records as $record) {
            if (!is_array($record)) continue;

            $isSessionStart =
                   isset($record['profileName'])
                || !empty(array_filter(
                       array_keys($record),
                       fn($k) => strpos((string)$k, 'profile') === 0
                   ));

            if ($isSessionStart) {
                $sessionStartRecords[] = $record;
                continue;
            }

            $session = 0;
            $time    = 0;
            $id      = '';
            $pids    = [];

            foreach ($record as $key => $value) {
                if ($key === 'session') {
                    $session = (int)$value;
                } elseif ($key === 'time') {
                    $time = (int)$value;
                } elseif ($key === 'id') {
                    $id = (string)$value;
                } elseif (strpos((string)$key, 'k') === 0) {
                    $pids[$key] = ($value === 'Infinity') ? -1 : $value;
                    $all_pids[$key] = true;
                }
            }

            if ($session > 0 && $time > 0) {
                $log_rows[] = [
                    'session' => $session,
                    'time'    => $time,
                    'pids'    => $pids,
                ];
            }

            if ($session > 0) {
                $sessionUpserts[] = [
                    'id'      => $id,
                    'session' => $session,
                    'time'    => $time,
                    'timeend' => $time,
                ];
            }
        }

        // ─── Авто-регистрация новых PID'ов ───
        if (!empty($all_pids)) {
            ensure_pids_exist($db, $user_id, array_keys($all_pids));
        }

        if (!empty($log_rows)) {
            insert_log_rows_bulk($db, $user_id, $log_rows);
        }

        foreach ($sessionUpserts as $s) {
            $db->execute_query(
                "INSERT INTO sessions (user_id, id, session, time, timeend, sessionsize)
                 VALUES (?,?,?,?,?,1)
                 ON DUPLICATE KEY UPDATE
                    id          = VALUES(id),
                    timeend     = GREATEST(timeend, VALUES(timeend)),
                    sessionsize = sessionsize + 1",
                [$user_id, $s['id'], $s['session'], $s['time'], $s['timeend']]
            );
        }

        foreach ($sessionStartRecords as $record) {
            $notif = processSessionStartRecord(
                $db,
                $record,
                'sessions',
                $lang,
                $ctx['username'],
                $ctx['tg_token']       ?? null,
                $ctx['tg_chatid']      ?? null,
                $ctx['tg_socks_proxy'] ?? '',
                $translations,
                $ctx['ip']             ?? null,
                $user_id
            );

            if ($notif !== null) {
                $pendingNotifications[] = $notif;
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    if (!empty($pendingNotifications)) {
        sendPendingNotifications($pendingNotifications);
    }
}

/* ───────────────────────── Single (form-urlencoded) ───────────────────────── */

function processSingleRequest(mysqli $db, array $ctx, array $request): void
{
    $user_id      = (int)$ctx['user_id'];
    $lang         = $ctx['lang'];
    $translations = $ctx['translations'];

    $session        = 0;
    $time           = 0;
    $id             = '';
    $pids           = [];
    $isSessionStart = false;
    $isNotice       = false;
    $hasKData       = false;

    foreach ($request as $key => $value) {
        if ($key === 'session') {
            $session = (int)$value;
        } elseif ($key === 'time') {
            $time = (int)$value;
        } elseif ($key === 'id') {
            $id = (string)$value;
        } elseif ($key === 'profileName') {
            $isSessionStart = true;
        } elseif ($key === 'notice' || $key === 'noticeClass') {
            $isNotice = true;
        } elseif (strpos((string)$key, 'k') === 0) {
            $pids[$key] = ($value === 'Infinity') ? -1 : $value;
            $hasKData = true;
        }
    }

    /* Случай 1: session start */
    if ($isSessionStart) {
        $record = [];
        foreach ($request as $key => $value) {
            if (in_array($key, ['session', 'time', 'id', 'profileName'], true)) {
                $record[$key] = $value;
            }
        }

        $notif = null;
        $db->begin_transaction();
        try {
            $notif = processSessionStartRecord(
                $db,
                $record,
                'sessions',
                $lang,
                $ctx['username'],
                $ctx['tg_token']       ?? null,
                $ctx['tg_chatid']      ?? null,
                $ctx['tg_socks_proxy'] ?? '',
                $translations,
                $ctx['ip']             ?? null,
                $user_id
            );
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        if ($notif !== null) {
            sendPendingNotifications([$notif]);
        }
        return;
    }

    /* Случай 2: обычный datapoint */
    if ($session <= 0 || $time <= 0) {
        return;
    }
    if (!$hasKData && !$isNotice) {
        return;
    }

    if ($isNotice) {
        if (isset($request['notice']))      $pids['notice']      = $request['notice'];
        if (isset($request['noticeClass'])) $pids['noticeClass'] = $request['noticeClass'];
    }

    // ─── Авто-регистрация новых PID'ов ───
    if (!empty($pids)) {
        ensure_pids_exist($db, $user_id, array_keys($pids));
    }

    insert_log_row($db, $user_id, $session, $time, $pids);

    $db->execute_query(
        "INSERT INTO sessions (user_id, id, session, time, timeend, sessionsize)
         VALUES (?,?,?,?,?,1)
         ON DUPLICATE KEY UPDATE
            id          = VALUES(id),
            timeend     = GREATEST(timeend, VALUES(timeend)),
            sessionsize = sessionsize + 1",
        [$user_id, $id, $session, $time, $time]
    );
}

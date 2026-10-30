<?php
/**
 * Telegram-нотификации через отдельный Redis Stream (ratel:notifications).
 *
 * Поток:
 *   upload_processor.php (bulk/single)
 *     → processSessionStartRecord() возвращает ['message', 'tg_token', ...]
 *     → notifications_dispatch() — fast path XADD в ratel:notifications,
 *                                  inline fallback через notify()
 *
 *   worker.php → notifications_process() — consumer handler,
 *     вызывает notify(), классифицирует результат:
 *       - success / permanent config error → ack
 *       - timeout / 429 / 5xx              → RatelRetryableException → retry,
 *                                            затем DLQ после N попыток
 *
 * Конфиг (creds.php):
 *   $redis_notifications_stream_key    = 'ratel:notifications';
 *   $redis_notifications_stream_maxlen = 10000;
 */

require_once __DIR__ . '/redis.php';
require_once __DIR__ . '/helpers.php';

/**
 * Маркер «эту ошибку имеет смысл повторить».
 *
 * worker.php ловит этот тип ДО generic catch(Throwable) и обрабатывает
 * как transient: attempts++, до $worker_max_attempts попыток, затем DLQ.
 */
class RatelRetryableException extends RuntimeException {}

/* ────────────────────────────────────────────────────────────
 * Producer
 * ──────────────────────────────────────────────────────────── */

/**
 * Положить одну нотификацию в Stream.
 * @return bool  true — XADD успешен; false — Redis недоступен/выключен.
 */
function notifications_push(array $notif): bool
{
    if (empty($notif['message']) || !is_string($notif['message'])) {
        return false;
    }

    $redis = get_redis_connection();
    if ($redis === null) {
        return false;
    }

    global $redis_notifications_stream_key, $redis_notifications_stream_maxlen;
    $key    = $redis_notifications_stream_key    ?? 'ratel:notifications';
    $maxlen = (int)($redis_notifications_stream_maxlen ?? 0);

    try {
        $id = $redis->xAdd($key, '*', [
            'message'        => (string)$notif['message'],
            'user'           => (string)($notif['user']           ?? ''),
            'tg_token'       => (string)($notif['tg_token']       ?? ''),
            'tg_chatid'      => (string)($notif['tg_chatid']      ?? ''),
            'tg_socks_proxy' => (string)($notif['tg_socks_proxy'] ?? ''),
            'received'       => (string)microtime(true),
        ], $maxlen, true);

        return $id !== false;
    } catch (Throwable $e) {
        error_log('notifications_push: ' . $e->getMessage());
        return false;
    }
}

/**
 * Отправить пачку нотификаций.
 *
 * Fast path: XADD в ratel:notifications (Redis включён).
 * Inline fallback: синхронный notify() — Redis выключен или XADD упал.
 *
 * Fallback идентичен по поведению прежней sendPendingNotifications():
 * блокирует вызывающий процесс до 15 с на одну нотификацию.
 */
function notifications_dispatch(array $notifications): void
{
    global $redis_stream_enabled;
    $use_stream = !empty($redis_stream_enabled);

    foreach ($notifications as $n) {
        if (!is_array($n) || empty($n['message'])) {
            continue;
        }

        if ($use_stream && notifications_push($n)) {
            continue;
        }

        // Inline fallback
        try {
            notify(
                $n['message'],
                $n['tg_token']       ?? null,
                $n['tg_chatid']      ?? null,
                $n['tg_socks_proxy'] ?? ''
            );
        } catch (Throwable $e) {
            error_log('notifications_dispatch (inline fallback): ' . $e->getMessage());
        }
    }
}

/* ────────────────────────────────────────────────────────────
 * Consumer handler — вызывается из worker.php::worker_dispatch()
 * ──────────────────────────────────────────────────────────── */

/**
 * Обработать одно сообщение из ratel:notifications.
 *
 * @param mysqli $db       не используется, оставлен для совместимости
 *                         с сигнатурой worker_dispatch().
 * @param array  $fields   поля сообщения (message, tg_token, tg_chatid, ...)
 * @param ?Redis $redis    не используется.
 *
 * @throws RatelRetryableException при transient-ошибке Telegram
 *         (timeout, 429, 5xx) — worker сделает retry / DLQ.
 *
 * Постоянные ошибки (невалидный токен, blocked bot, wrong chatid)
 * логируются и НЕ бросают исключение — сообщение ack'ается.
 */
function notifications_process(mysqli $db, array $fields, ?Redis $redis = null): void
{
    $message = $fields['message'] ?? '';
    if (!is_string($message) || $message === '') {
        // Битое сообщение — ретраить бессмысленно.
        throw new RuntimeException('Malformed notification: empty message');
    }

    $tg_token  = (string)($fields['tg_token']       ?? '');
    $tg_chatid = (string)($fields['tg_chatid']      ?? '');
    $tg_proxy  = (string)($fields['tg_socks_proxy'] ?? '');

    // Конфиг-ошибка (пользователь стёр токен/chatid или они битые).
    // Не ретраим — такой пейлоад никогда не станет валидным.
    if ($tg_token === '' || $tg_chatid === ''
        || !preg_match('/^\d+:[a-zA-Z0-9_-]+$/', $tg_token)
        || !preg_match('/^-?\d+$/', $tg_chatid)) {
        error_log('[notifications] invalid tg credentials, dropping (chatid="'
            . $tg_chatid . '")');
        return;
    }

    $result = notify($message, $tg_token, $tg_chatid, $tg_proxy);

    // notify() возвращает:
    //   null  — конфиг/curl не инициализировался/неверный proxy
    //   -1    — timeout или HTTP 500
    //   array — декодированный JSON от Telegram

    if ($result === null) {
        // notify() возвращает null только для ПЕРМАНЕНТНЫХ ошибок:
        // bad URL, unsupported scheme, invalid CA bundle, bad proxy format,
        // curl_init() failure. Ретрай не поможет — ack + лог.
        error_log('[notifications] permanent notify() failure (URL/SSL/proxy), dropping');
        return;
    }

    if ($result === -1) {
        throw new RatelRetryableException('Telegram API timeout or 500');
    }

    if (is_array($result)) {
        $ok = $result['ok'] ?? null;

        if ($ok === true) {
            return;   // ack
        }

        $errorCode   = (int)($result['error_code']   ?? 0);
        $description = (string)($result['description'] ?? 'unknown');

        // Rate limit — ретраим (после backoff через XAUTOCLAIM)
        if ($errorCode === 429) {
            throw new RatelRetryableException("Telegram rate limit: $description");
        }

        // Server-side error — ретраим
        if ($errorCode >= 500) {
            throw new RatelRetryableException("Telegram $errorCode: $description");
        }

        // 4xx — permanent: bad token, chat not found, bot blocked, etc.
        // Логируем и ack, чтобы не крутить в DLQ.
        error_log("[notifications] Telegram permanent error $errorCode: $description");
        return;
    }

    // Неожидаемый тип возврата — не ретраим.
    error_log('[notifications] unexpected notify() return type: ' . gettype($result));
}

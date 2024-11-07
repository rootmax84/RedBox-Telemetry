<?php
declare(strict_types=1);

/**
 * Generate authentication token
 */
function generate_token(string $username): string
{
    return hash('sha3-256', random_bytes(32) . $username);
}

/**
 * Get Bearer token from request headers
 */
function getBearerToken(): ?string
{
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    return isset($headers['authorization']) ? 
        trim(str_replace('Bearer ', '', $headers['authorization'])) : 
        null;
}

/**
 * Send notification to Telegram
 * @return array|null Returns decoded response or null on failure
 */
function notify(string $text, string $tg_token, string $tg_chatid): ?array
{
    if (empty($tg_token) || empty($tg_chatid)) {
        return null;
    }

    $ch = curl_init('https://api.telegram.org/bot' . $tg_token . '/sendMessage');
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $tg_chatid,
            'text' => $text,
        ],
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * Generate CSRF token
 */
function generate_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || 
        !isset($_SESSION['csrf_token_time']) || 
        time() - $_SESSION['csrf_token_time'] > 3300
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token(string $token): bool
{
    return isset($_SESSION['csrf_token']) &&
           isset($_SESSION['csrf_token_time']) &&
           time() - $_SESSION['csrf_token_time'] <= 3600 &&
           hash_equals($_SESSION['csrf_token'], $token);
}
?>
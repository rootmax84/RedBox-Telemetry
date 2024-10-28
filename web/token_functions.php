<?php

function generate_token($username) {
    return hash('sha3-256', random_bytes(32) . $username);
}

function getBearerToken() : ?string {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    return isset($headers['authorization']) ? trim(str_replace('Bearer ', '', $headers['authorization'])) : null;
}

// Session upload notification to telegram
function notify($text, $tg_token, $tg_chatid) {
    if (empty($tg_token) || empty($tg_chatid)) return;
    $ch = curl_init('https://api.telegram.org/bot' . $tg_token . '/sendMessage');
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

    return json_decode($response, true);
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || time() - $_SESSION['csrf_token_time'] > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && 
           isset($_SESSION['csrf_token_time']) && 
           time() - $_SESSION['csrf_token_time'] <= 3600 &&
           hash_equals($_SESSION['csrf_token'], $token);
}
?>
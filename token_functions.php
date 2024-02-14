<?php

function generate_token($username) {
    return hash('sha3-256', microtime() . $username);
}

function getBearerToken() : ?string {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    if (!isset($headers['authorization'])) {
        return null;
    }
        return trim(str_replace('Bearer', '', $headers['authorization']));
}

// Session upload notification to telegram
function notify($text, $tg_token, $tg_chatid) {
  if (!strlen($tg_token) || !strlen($tg_chatid)) die;
    include('creds.php');
    $ch = curl_init();
    curl_setopt_array(
        $ch,
        array(
            CURLOPT_URL => 'https://api.telegram.org/bot' . $tg_token . '/sendMessage',
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => array(
                'chat_id' => $tg_chatid,
                'text' => $text,
            ),
        )
    );
    curl_exec($ch);
}
?>
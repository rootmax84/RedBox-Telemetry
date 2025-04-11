<?php
require_once('creds.php');

$share_secret = $share_secret ?? 'default_secret'; //default if missed in creds.php

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['uid'], $data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$uid = $data['uid'];
$id = $data['id'];

$payload = "uid={$uid}&id={$id}";
$signature = hash_hmac('sha256', $payload, $share_secret);

echo json_encode(['signature' => $signature]);
?>
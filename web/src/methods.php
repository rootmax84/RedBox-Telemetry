<?php

function allowMethods(...$methods) {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        $allowed = array_diff($methods, ['OPTIONS']);
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowed));
        http_response_code(204);
        exit;
    }

    if (!in_array($_SERVER['REQUEST_METHOD'], $methods)) {
        http_response_code(405);
        echo 'Method not allowed';
        exit;
    }
}

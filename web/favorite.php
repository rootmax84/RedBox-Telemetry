<?php
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/methods.php';
allowMethods('POST', 'DELETE', 'PUT');

header('Content-Type: application/json');

cache_flush(null, "fav_data_{$username}");

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        if (!isset($data['updates']) || !is_array($data['updates'])) {
            throw new Exception('Missing updates data');
        }

        $updatedCount = 0;
        foreach ($data['updates'] as $update) {
            if (!isset($update['id']) || !isset($update['description'])) {
                continue;
            }

            $db->execute_query(
                "UPDATE sessions SET description = ?
                  WHERE user_id = ? AND session = ?",
                [$update['description'], current_user_id(), $update['id']]
            );
            $updatedCount++;
        }

        echo json_encode([
            'status' => 'success',
            'updated' => $updatedCount
        ]);
        exit;
    }


    $session_id = $data['id'] ?? null;

    if (!$session_id) {
        throw new Exception('Missing session ID parameter');
    }

    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
        throw new Exception('Invalid request method');
    }

    $favorite_value = $_SERVER['REQUEST_METHOD'] === 'POST' ? 1 : 0;
    $action = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'added' : 'deleted';

    $db->execute_query(
        "UPDATE sessions SET description = '-', favorite = ?
          WHERE user_id = ? AND session = ?",
        [$favorite_value, current_user_id(), $session_id]
    );

    echo json_encode([
        'status' => 'success',
        'action' => $action,
        'session_id' => $session_id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

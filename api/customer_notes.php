<?php
/**
 * Customer Notes API
 * Handles fetching and adding internal notes for customers.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
requireAdmin(true);

try {
    if ($method === 'GET') {
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id || !ctype_digit((string)$user_id)) {
            Response::error('user_id is required', null, 400);
        }

        try {
            $notes = Database::queryAll(
                "SELECT * FROM customer_notes WHERE user_id = ? ORDER BY created_at DESC",
                [$user_id]
            );

            echo json_encode([
                'success' => true,
                'notes' => $notes ?: []
            ]);
        } catch (PDOException $e) {
            // Check if the error is "Table not found"
            if ($e->getCode() === '42S02') {
                echo json_encode([
                    'success' => true,
                    'notes' => [],
                    'warning' => 'Notes table missing. Please run migration.'
                ]);
            } else {
                throw $e;
            }
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            Response::error('Invalid JSON', null, 400);
        }
        $user_id = $data['user_id'] ?? null;
        $note_text = trim((string)($data['note_text'] ?? ''));
        $author = trim((string)($data['author_username'] ?? 'Admin'));

        if (!$user_id || !ctype_digit((string)$user_id) || $note_text === '') {
            Response::error('user_id and note_text are required', null, 400);
        }
        if (strlen($note_text) > 2000) {
            Response::error('note_text too long', null, 422);
        }
        if ($author === '' || strlen($author) > 120) {
            $author = 'Admin';
        }

        $sql = "INSERT INTO customer_notes (user_id, note_text, author_username) VALUES (?, ?, ?)";
        Database::execute($sql, [$user_id, $note_text, $author]);

        echo json_encode([
            'success' => true,
            'message' => 'Note added successfully'
        ]);
    } else {
        Response::methodNotAllowed('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}

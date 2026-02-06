<?php
/**
 * Customer Notes API
 * Handles fetching and adding internal notes for customers.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id is required']);
            exit;
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
        $user_id = $data['user_id'] ?? null;
        $note_text = $data['note_text'] ?? null;
        $author = $data['author_username'] ?? 'Admin';

        if (!$user_id || !$note_text) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id and note_text are required']);
            exit;
        }

        $sql = "INSERT INTO customer_notes (user_id, note_text, author_username) VALUES (?, ?, ?)";
        Database::execute($sql, [$user_id, $note_text, $author]);

        echo json_encode([
            'success' => true,
            'message' => 'Note added successfully'
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}

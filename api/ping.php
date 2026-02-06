<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Lightweight endpoint to warm up PHP process and DB connection
try {
    // Touch Database to ensure PDO is initialized (no query work)
    Database::getInstance();
    echo json_encode(['success' => true, 'time' => date('c')]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

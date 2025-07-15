<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Create database connection
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Get the highest existing order item ID to determine next sequence
    $maxIdStmt = $pdo->prepare("SELECT id FROM order_items WHERE id REGEXP '^OI[0-9]+$' ORDER BY CAST(SUBSTRING(id, 3) AS UNSIGNED) DESC LIMIT 1");
    $maxIdStmt->execute();
    $maxId = $maxIdStmt->fetchColumn();
    
    // Extract the sequence number from the highest ID
    $nextSequence = 1; // Default starting sequence
    if ($maxId) {
        $currentSequence = (int)substr($maxId, 2); // Remove 'OI' prefix and convert to int
        $nextSequence = $currentSequence + 1;
    }
    
    // Generate the next ID in format OI0000000001, OI0000000002, etc.
    $nextSequenceFormatted = str_pad($nextSequence, 10, '0', STR_PAD_LEFT);
    $nextId = 'OI' . $nextSequenceFormatted;
    
    echo json_encode([
        'success' => true,
        'nextId' => $nextId,
        'nextSequence' => $nextSequence,
        'maxExistingId' => $maxId
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
}
?> 
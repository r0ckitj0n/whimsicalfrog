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
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get the current count of order items
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM order_items');
    $stmt->execute();
    $itemCount = $stmt->fetchColumn();
    
    // Generate the next ID in format OI001, OI002, etc.
    $nextSequence = str_pad($itemCount + 1, 3, '0', STR_PAD_LEFT);
    $nextId = 'OI' . $nextSequence;
    
    echo json_encode([
        'success' => true,
        'nextId' => $nextId,
        'currentCount' => $itemCount
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
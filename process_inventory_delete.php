<?php
<<<<<<< HEAD
// Include the configuration file
require_once 'api/config.php';
=======
// Include centralized systems
require_once 'api/config.php';
require_once 'includes/functions.php';
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
<<<<<<< HEAD
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate ID field
    if (!isset($data['id']) || empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Item ID is required']);
        exit;
    }
    
    // Extract ID
    $id = $data['id'];
    
    // Create database connection using config
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
=======

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json(null, 200);
}

// Validate HTTP method using centralized function
Response::validateMethod('POST');

try {
    // Get and validate input using centralized method
    $data = Response::getJsonInput();
    
    // Validate required fields
    if (!isset($data['id']) || empty($data['id'])) {
        Response::error('Item ID is required');
    }
    
    // Extract and sanitize ID
    $id = trim($data['id']);
    
    // Get database connection using centralized system
    $pdo = Database::getInstance();
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    
    // Check if item exists
    $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE sku = ?');
    $checkStmt->execute([$id]);
    if ($checkStmt->fetchColumn() == 0) {
<<<<<<< HEAD
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
        exit;
=======
        Response::notFound('Item not found');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    }
    
    // Delete item
    $stmt = $pdo->prepare('DELETE FROM items WHERE sku = ?');
    $result = $stmt->execute([$id]);
    
    if ($result) {
<<<<<<< HEAD
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Item deleted successfully',
            'id' => $id
        ]);
=======
        // Log successful deletion using centralized logging
        Logger::info("Item deleted successfully", [
            'sku' => $id
        ]);
        
        // Return success response using centralized method
        Response::success([
            'id' => $id
        ], 'Item deleted successfully');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    } else {
        throw new Exception('Failed to delete inventory item');
    }
    
} catch (PDOException $e) {
<<<<<<< HEAD
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
    exit;
=======
    // Log database error using centralized logging
    Logger::databaseError($e, 'Item deletion failed', ['sku' => $id ?? 'unknown']);
    Response::serverError('Database error occurred');
    
} catch (Exception $e) {
    // Log general error using centralized logging
    Logger::error('Item deletion error: ' . $e->getMessage(), ['sku' => $id ?? 'unknown']);
    Response::serverError('Deletion failed');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
}
?>

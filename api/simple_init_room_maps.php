<?php
// Simplified Room Maps Table Creation
// This version has better error handling for live servers

ini_set('display_errors', 0); // Turn off display errors for production
error_reporting(E_ALL);

// Set content type
header('Content-Type: application/json');

try {
    // Try to load config
    if (!file_exists(__DIR__ . '/config.php')) {
        throw new Exception('Config file not found');
    }
    
    require_once 'config.php';
    
    // Check if required variables exist
    if (!isset($dsn) || !isset($user) || !isset($pass)) {
        throw new Exception('Database configuration not properly set');
    }
    
    // Create PDO connection with error handling
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Simple table creation with basic compatibility
    $createTableSQL = "CREATE TABLE IF NOT EXISTS room_maps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_type VARCHAR(50) NOT NULL,
        map_name VARCHAR(100) NOT NULL,
        coordinates TEXT,
        is_active TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($createTableSQL);
    
    // Add indexes separately (more compatible)
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_room_type ON room_maps (room_type)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_active ON room_maps (is_active)");
    } catch (PDOException $e) {
        // Indexes might not be supported, continue anyway
    }
    
    // Test the table
    $testQuery = $pdo->query("SELECT COUNT(*) as count FROM room_maps");
    $result = $testQuery->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Room maps table created successfully',
        'table_count' => $result['count']
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in simple_init_room_maps: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in simple_init_room_maps: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Configuration error',
        'details' => $e->getMessage()
    ]);
}
?> 
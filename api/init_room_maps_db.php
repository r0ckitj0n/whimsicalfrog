<?php
require_once 'config.php';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Create room_maps table with better MySQL compatibility
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS room_maps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_type VARCHAR(50) NOT NULL,
        map_name VARCHAR(100) NOT NULL,
        coordinates TEXT,
        is_active BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_room_type (room_type),
        INDEX idx_active (is_active),
        INDEX idx_room_active (room_type, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTableSQL);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Room maps database table created successfully'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database initialization error: ' . $e->getMessage()
    ]);
}
?> 
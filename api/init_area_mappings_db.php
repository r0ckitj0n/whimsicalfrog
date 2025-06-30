<?php
// Initialize area mappings database table
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database configuration
require_once __DIR__ . '/config.php';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Create area_mappings table
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS area_mappings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_type VARCHAR(50) NOT NULL,
            area_selector VARCHAR(20) NOT NULL,
            mapping_type ENUM('item', 'category') NOT NULL,
            item_id VARCHAR(10) NULL,
            category_id INT NULL,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_room_area (room_type, area_selector),
            INDEX idx_item (item_id),
            INDEX idx_category (category_id),
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createTableSQL);
    
    echo json_encode([
        'success' => true,
        'message' => 'Area mappings database table created successfully',
        'table_created' => true
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 
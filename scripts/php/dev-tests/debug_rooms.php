<?php
/**
 * Debug script to test getRoomDoorsData() function
 */

require_once __DIR__ . '/../../../api/room_helpers.php';
require_once __DIR__ . '/../../../api/config.php';

echo "Testing getRoomDoorsData()...\n\n";

try {
    // Test database connection
    $pdo = Database::getInstance();
    echo "✅ Database connection successful\n";
    
    // Test the room_settings table
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM room_settings");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Room settings table has {$count['count']} records\n";
    
    // Test the actual query from getRoomDoorsData()
    $stmt = $pdo->prepare("
        SELECT room_number, room_name, door_label, description, display_order
        FROM room_settings 
        WHERE is_active = 1 
        AND room_number NOT IN ('A', 'B')
        ORDER BY display_order, room_number
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Query returned " . count($results) . " results:\n";
    foreach ($results as $row) {
        echo "  - Room {$row['room_number']}: {$row['room_name']} ({$row['door_label']})\n";
    }
    
    // Test the actual function
    echo "\n🔍 Testing getRoomDoorsData() function:\n";
    $doorData = getRoomDoorsData();
    echo "Function returned " . count($doorData) . " results:\n";
    foreach ($doorData as $door) {
        echo "  - Room {$door['room_number']}: {$door['room_name']} ({$door['door_label']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    error_log("Debug rooms error: " . $e->getMessage());
}

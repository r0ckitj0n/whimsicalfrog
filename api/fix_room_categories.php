<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/room_helpers.php';

header('Content-Type: text/plain');

try {
    Database::getInstance();
    echo "=== ROOM-CATEGORY MAPPING FIX ===\n\n";
    
    // 1. Check existing categories
    echo "1. Available Categories:\n";
    $categories = Database::queryAll("SELECT id, name FROM categories ORDER BY name");
    foreach ($categories as $cat) {
        echo "   - ID {$cat['id']}: {$cat['name']}\n";
    }
    echo "\n";
    
    // 2. Check current room settings
    echo "2. Current Room Settings:\n";
    $rooms = Database::queryAll("SELECT room_number, room_name, description FROM room_settings ORDER BY room_number");
    foreach ($rooms as $room) {
        echo "   - Room {$room['room_number']}: {$room['room_name']}\n";
    }
    echo "\n";
    
    // 3. Create logical room-to-category mappings
    echo "3. Creating Room-Category Mappings (Rooms as Category Aliases):\n";
    
    // Mapping based on room names and available categories
    $roomCategoryMappings = [
        1 => 'T-Shirts',           // Room 1: T-Shirts & Apparel → T-Shirts
        2 => 'Tumblers',           // Room 2: Tumblers & Drinkware → Tumblers  
        3 => 'Artwork',            // Room 3: Custom Artwork → Artwork
        4 => 'Fluid Art',          // Room 4: Sublimation Items → Fluid Art
        5 => 'Decor'               // Room 5: Window Wraps → Decor
    ];
    
    foreach ($roomCategoryMappings as $roomNum => $categoryName) {
        echo "   Processing Room $roomNum -> $categoryName:\n";
        
        // Try exact match first, then partial match
        $categoryData = Database::queryOne("SELECT id, name FROM categories WHERE name = ?", [$categoryName]);
        
        if (!$categoryData) {
            // Try partial match
            $categoryData = Database::queryOne("SELECT id, name FROM categories WHERE name LIKE ?", ["%$categoryName%"]); 
        }
        
        if ($categoryData) {
            $categoryId = $categoryData['id'];
            $actualName = $categoryData['name'];
            echo "     Found category: ID $categoryId ($actualName)\n";
            
            // Clear existing assignments for this room
            Database::execute("DELETE FROM room_category_assignments WHERE room_number = ?", [$roomNum]);
            echo "     Cleared old assignments\n";
            
            // Get room name for the assignment
            $roomData = Database::queryOne("SELECT room_name FROM room_settings WHERE room_number = ?", [$roomNum]);
            $roomName = $roomData['room_name'] ?? "Room $roomNum";
            
            // Create new primary assignment
            Database::execute("\n                INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary) \n                VALUES (?, ?, ?, 1)\n                ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), is_primary = VALUES(is_primary)\n            ", [$roomNum, $roomName, $categoryId]);
            echo "     ✅ Created primary assignment: Room $roomNum -> $actualName\n";
            
            // Test item count
            $itemCount = Database::queryOne("SELECT COUNT(*) as count FROM items WHERE category = ?", [$actualName]);
            echo "     Items available: {$itemCount['count']}\n";
            
        } else {
            echo "     ❌ Category '$categoryName' not found\n";
            
            // Show available categories that might match
            $similar = array_column(Database::queryAll("SELECT name FROM categories WHERE name LIKE ? OR name LIKE ? LIMIT 5", ["%$categoryName%", "%" . explode(' ', $categoryName)[0] . "%"]) , 'name');
            if ($similar) {
                echo "     Available categories: " . implode(', ', $similar) . "\n";
            }
        }
        echo "\n";
    }
    
    echo "4. Final Verification - Updated Room-Category Mappings:\n";
    $finalMappings = Database::queryAll("\n        SELECT rca.room_number, rs.room_name, c.name as category_name, c.id as category_id\n        FROM room_category_assignments rca\n        JOIN categories c ON rca.category_id = c.id\n        LEFT JOIN room_settings rs ON rca.room_number = rs.room_number\n        WHERE rca.is_primary = 1\n        ORDER BY rca.room_number\n    ");
    
    foreach ($finalMappings as $mapping) {
        echo "   ✅ Room {$mapping['room_number']} ({$mapping['room_name']}) -> {$mapping['category_name']} (ID: {$mapping['category_id']})\n";
    }
    
    echo "\n=== ROOM-CATEGORY MAPPING COMPLETE ===\n";
    echo "Rooms are now properly aliased to categories!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>

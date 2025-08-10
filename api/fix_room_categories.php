<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/room_helpers.php';

header('Content-Type: text/plain');

try {
    $pdo = Database::getInstance();
    echo "=== ROOM-CATEGORY MAPPING FIX ===\n\n";
    
    // 1. Check existing categories
    echo "1. Available Categories:\n";
    $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categories as $cat) {
        echo "   - ID {$cat['id']}: {$cat['name']}\n";
    }
    echo "\n";
    
    // 2. Check current room settings
    echo "2. Current Room Settings:\n";
    $stmt = $pdo->prepare("SELECT room_number, room_name, description FROM room_settings ORDER BY room_number");
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE name = ?");
        $stmt->execute([$categoryName]);
        $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$categoryData) {
            // Try partial match
            $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE name LIKE ?");
            $stmt->execute(["%$categoryName%"]);
            $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($categoryData) {
            $categoryId = $categoryData['id'];
            $actualName = $categoryData['name'];
            echo "     Found category: ID $categoryId ($actualName)\n";
            
            // Clear existing assignments for this room
            $stmt = $pdo->prepare("DELETE FROM room_category_assignments WHERE room_number = ?");
            $stmt->execute([$roomNum]);
            echo "     Cleared old assignments\n";
            
            // Get room name for the assignment
            $stmt = $pdo->prepare("SELECT room_name FROM room_settings WHERE room_number = ?");
            $stmt->execute([$roomNum]);
            $roomData = $stmt->fetch(PDO::FETCH_ASSOC);
            $roomName = $roomData['room_name'] ?? "Room $roomNum";
            
            // Create new primary assignment
            $stmt = $pdo->prepare("
                INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary) 
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), is_primary = VALUES(is_primary)
            ");
            $stmt->execute([$roomNum, $roomName, $categoryId]);
            echo "     ✅ Created primary assignment: Room $roomNum -> $actualName\n";
            
            // Test item count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM items WHERE category = ?");
            $stmt->execute([$actualName]);
            $itemCount = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "     Items available: {$itemCount['count']}\n";
            
        } else {
            echo "     ❌ Category '$categoryName' not found\n";
            
            // Show available categories that might match
            $stmt = $pdo->prepare("SELECT name FROM categories WHERE name LIKE ? OR name LIKE ? LIMIT 5");
            $stmt->execute(["%$categoryName%", "%" . explode(' ', $categoryName)[0] . "%"]);
            $similar = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($similar) {
                echo "     Available categories: " . implode(', ', $similar) . "\n";
            }
        }
        echo "\n";
    }
    
    echo "4. Final Verification - Updated Room-Category Mappings:\n";
    $stmt = $pdo->prepare("
        SELECT rca.room_number, rs.room_name, c.name as category_name, c.id as category_id
        FROM room_category_assignments rca
        JOIN categories c ON rca.category_id = c.id
        LEFT JOIN room_settings rs ON rca.room_number = rs.room_number
        WHERE rca.is_primary = 1
        ORDER BY rca.room_number
    ");
    $stmt->execute();
    $finalMappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

<?php
require_once __DIR__ . '/../../../api/config.php';

try {
    $pdo = Database::getInstance();
    echo "Database connected successfully\n\n";
    
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
    
    // 2. Check current room settings and their mappings
    echo "2. Current Room Settings:\n";
    $stmt = $pdo->prepare("SELECT room_number, room_name, description FROM room_settings ORDER BY room_number");
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rooms as $room) {
        echo "   - Room {$room['room_number']}: {$room['room_name']}\n";
    }
    echo "\n";
    
    // 3. Check current room-category assignments
    echo "3. Current Room-Category Assignments:\n";
    $stmt = $pdo->prepare("
        SELECT rca.room_number, rca.category_id, c.name as category_name, rca.is_primary
        FROM room_category_assignments rca
        LEFT JOIN categories c ON rca.category_id = c.id
        ORDER BY rca.room_number
    ");
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($assignments as $assign) {
        $primary = $assign['is_primary'] ? ' (PRIMARY)' : '';
        echo "   - Room {$assign['room_number']} -> Category {$assign['category_id']}: {$assign['category_name']}{$primary}\n";
    }
    echo "\n";
    
    // 4. Create logical room-to-category mappings based on room names
    echo "4. Creating Room-Category Mappings:\n";
    
    $roomCategoryMappings = [
        1 => 'T-Shirts',           // Room 1: T-Shirts & Apparel
        2 => 'Tumblers',           // Room 2: Tumblers & Drinkware  
        3 => 'Custom Art',         // Room 3: Custom ART
        4 => 'Sublimation',        // Room 4: Sublimation Items
        5 => 'Vehicle Graphics'    // Room 5: Window Wraps
    ];
    
    foreach ($roomCategoryMappings as $roomNum => $categoryName) {
        // Find category ID
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR name LIKE ?");
        $stmt->execute([$categoryName, "%$categoryName%"]);
        $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($categoryData) {
            $categoryId = $categoryData['id'];
            echo "   Mapping Room $roomNum -> Category $categoryId ($categoryName)\n";
            
            // Clear existing assignments for this room
            $stmt = $pdo->prepare("DELETE FROM room_category_assignments WHERE room_number = ?");
            $stmt->execute([$roomNum]);
            
            // Create new primary assignment
            $stmt = $pdo->prepare("
                INSERT INTO room_category_assignments (room_number, category_id, is_primary) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE is_primary = 1
            ");
            $stmt->execute([$roomNum, $categoryId]);
            echo "     ✅ Assignment created\n";
        } else {
            echo "   ❌ Category '$categoryName' not found for Room $roomNum\n";
            
            // Show similar categories
            $stmt = $pdo->prepare("SELECT name FROM categories WHERE name LIKE ? LIMIT 3");
            $stmt->execute(["%$categoryName%"]);
            $similar = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($similar) {
                echo "     Similar categories: " . implode(', ', $similar) . "\n";
            }
        }
    }
    
    echo "\n5. Verifying Updated Mappings:\n";
    $stmt = $pdo->prepare("
        SELECT rca.room_number, rs.room_name, c.name as category_name
        FROM room_category_assignments rca
        JOIN categories c ON rca.category_id = c.id
        JOIN room_settings rs ON rca.room_number = rs.room_number
        WHERE rca.is_primary = 1
        ORDER BY rca.room_number
    ");
    $stmt->execute();
    $verifyAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($verifyAssignments as $verify) {
        echo "   ✅ Room {$verify['room_number']} ({$verify['room_name']}) -> {$verify['category_name']}\n";
    }
    
    echo "\n6. Testing Item Count for Each Room:\n";
    foreach ($verifyAssignments as $verify) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM items WHERE category = ?");
        $stmt->execute([$verify['category_name']]);
        $itemCount = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Room {$verify['room_number']} ({$verify['category_name']}): {$itemCount['count']} items\n";
    }
    
    echo "\n=== ROOM-CATEGORY MAPPING COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>

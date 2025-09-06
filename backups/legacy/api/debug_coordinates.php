<?php
// Database coordinate audit script
require_once __DIR__ . '/config.php';

try {
    $pdo = Database::getInstance();
    
    echo "=== ROOM COORDINATE AUDIT ===\n\n";
    
    // Check items and their room assignments
    echo "1. ITEMS AND ROOM ASSIGNMENTS:\n";
    $stmt = $pdo->query("
        SELECT 
            i.id, i.name, i.room_number,
            rca.room_number as category_room,
            c.name as category_name
        FROM items i 
        LEFT JOIN room_category_assignments rca ON rca.category_id = (
            SELECT category_id FROM item_categories WHERE item_id = i.id LIMIT 1
        )
        LEFT JOIN categories c ON c.id = (
            SELECT category_id FROM item_categories WHERE item_id = i.id LIMIT 1
        )
        WHERE i.room_number IN (1,2,4,5) 
        ORDER BY i.room_number, i.id
        LIMIT 20
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  Item {$row['id']}: '{$row['name']}' | room_number={$row['room_number']} | category_room={$row['category_room']} | category='{$row['category_name']}'\n";
    }
    
    echo "\n2. ROOM COORDINATES TABLE:\n";
    $stmt = $pdo->query("
        SELECT 
            rc.item_id, rc.room_number, rc.top_position, rc.left_position, 
            rc.width, rc.height, i.name
        FROM room_coordinates rc
        LEFT JOIN items i ON i.id = rc.item_id
        WHERE rc.room_number IN (1,2,4,5)
        ORDER BY rc.room_number, rc.item_id
    ");
    
    $coordinates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($coordinates)) {
        echo "  ❌ NO COORDINATES FOUND in room_coordinates table!\n";
    } else {
        foreach ($coordinates as $coord) {
            echo "  Room {$coord['room_number']}: Item {$coord['item_id']} ('{$coord['name']}') -> top:{$coord['top_position']}, left:{$coord['left_position']}, w:{$coord['width']}, h:{$coord['height']}\n";
        }
    }
    
    echo "\n3. CHECKING COORDINATE QUERY LOGIC:\n";
    // Test the exact query used in load_room_content.php
    for ($roomNum = 1; $roomNum <= 5; $roomNum++) {
        echo "  Room {$roomNum}:\n";
        
        // Get room category
        $stmt = $pdo->prepare("
            SELECT c.name as category_name
            FROM room_category_assignments rca
            JOIN categories c ON rca.category_id = c.id
            WHERE rca.room_number = ? AND rca.is_primary = 1
            LIMIT 1
        ");
        $stmt->execute([$roomNum]);
        $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);
        $categoryName = $categoryData ? $categoryData['category_name'] : '';
        echo "    Category: '{$categoryName}'\n";
        
        // Get items for this category
        $stmt = $pdo->prepare("
            SELECT i.*,
                   rc.top_position, rc.left_position, rc.width as coord_width, rc.height as coord_height
            FROM items i
            JOIN item_categories ic ON i.id = ic.item_id
            JOIN categories c ON ic.category_id = c.id
            LEFT JOIN room_coordinates rc ON i.id = rc.item_id AND rc.room_number = ?
            WHERE c.name = ? AND i.status = 'active'
            ORDER BY i.id
        ");
        $stmt->execute([$roomNum, $categoryName]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "    Items found: " . count($items) . "\n";
        foreach ($items as $item) {
            $coords = "top:{$item['top_position']}, left:{$item['left_position']}, w:{$item['coord_width']}, h:{$item['coord_height']}";
            echo "      Item {$item['id']}: '{$item['name']}' -> {$coords}\n";
        }
        echo "\n";
    }
    
    echo "\n4. HARDCODED COORDINATE SOURCES TO AUDIT:\n";
    echo "  - Check room_main.php for hardcoded door positions\n";
    echo "  - Check landing page coordinate sources\n";
    echo "  - Check CSS files for hardcoded item positions\n";
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>

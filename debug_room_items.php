<?php
require_once 'config.php';
require_once 'api/room_helpers.php';

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Database connected successfully\n";
    
    $roomNumber = 5;
    echo "=== DEBUGGING ROOM $roomNumber ===\n\n";
    
    // Test room metadata
    $meta = getRoomMetadata($roomNumber, $pdo);
    echo "1. Room metadata:\n";
    print_r($meta);
    echo "\n";
    
    $categoryName = $meta['category'];
    echo "2. Category name: '$categoryName'\n\n";
    
    // Check categories table
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE name = ? LIMIT 1");
    $stmt->execute([$categoryName]);
    $catInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "3. Category info:\n";
    print_r($catInfo);
    echo "\n";
    
    $catId = $catInfo['id'] ?? null;
    echo "4. Category ID: $catId\n\n";
    
    if ($catId) {
        // Check items in that category
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM items WHERE category = ?");
        $stmt->execute([$categoryName]);
        $itemCount = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "5. Item count in category '$categoryName': {$itemCount['count']}\n\n";
        
        // Get actual items
        $stmt = $pdo->prepare(
            "SELECT i.*, 
                    COALESCE(img.image_path, i.imageUrl) as image_path,
                    img.is_primary,
                    img.alt_text
             FROM items i
             LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
             WHERE i.category = ? ORDER BY i.sku ASC LIMIT 5"
        );
        $stmt->execute([$categoryName]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "6. Sample items:\n";
        print_r($items);
        echo "\n";
        
        // Check room coordinates
        $roomType = "room$roomNumber";
        $cd = loadRoomCoordinates($roomType, $pdo);
        echo "7. Room coordinates data:\n";
        print_r($cd);
        echo "\n";
    } else {
        echo "5. No category ID found - checking available categories:\n";
        $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name");
        $stmt->execute();
        $allCats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($allCats);
        echo "\n";
        
        echo "6. Checking items table structure:\n";
        $stmt = $pdo->prepare("SELECT DISTINCT category FROM items ORDER BY category");
        $stmt->execute();
        $itemCats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($itemCats);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>

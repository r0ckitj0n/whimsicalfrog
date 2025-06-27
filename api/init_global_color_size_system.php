<?php
// Initialize Global Color and Size Management System
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected to database successfully.\n";

    // Create global_colors table - Master list of all available colors
    $createGlobalColorsTable = "
    CREATE TABLE IF NOT EXISTS global_colors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        color_name VARCHAR(100) NOT NULL UNIQUE,
        color_code VARCHAR(7) DEFAULT NULL,
        category VARCHAR(50) DEFAULT 'General',
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_active (is_active),
        INDEX idx_display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($createGlobalColorsTable);
    echo "✅ Created global_colors table.\n";

    // Create global_sizes table - Master list of all available sizes
    $createGlobalSizesTable = "
    CREATE TABLE IF NOT EXISTS global_sizes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        size_name VARCHAR(50) NOT NULL,
        size_code VARCHAR(10) NOT NULL UNIQUE,
        category VARCHAR(50) DEFAULT 'General',
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_active (is_active),
        INDEX idx_display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($createGlobalSizesTable);
    echo "✅ Created global_sizes table.\n";

    // Create item_size_assignments table - Which sizes are available for each item
    $createItemSizeAssignmentsTable = "
    CREATE TABLE IF NOT EXISTS item_size_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_sku VARCHAR(64) NOT NULL,
        global_size_id INT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_sku) REFERENCES items(sku) ON DELETE CASCADE,
        FOREIGN KEY (global_size_id) REFERENCES global_sizes(id) ON DELETE CASCADE,
        UNIQUE KEY unique_item_size (item_sku, global_size_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($createItemSizeAssignmentsTable);
    echo "✅ Created item_size_assignments table.\n";

    // Create item_color_assignments table - Which colors are available for each size of each item
    $createItemColorAssignmentsTable = "
    CREATE TABLE IF NOT EXISTS item_color_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_sku VARCHAR(64) NOT NULL,
        global_size_id INT NOT NULL,
        global_color_id INT NOT NULL,
        stock_level INT DEFAULT 0,
        price_adjustment DECIMAL(10,2) DEFAULT 0.00,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (item_sku) REFERENCES items(sku) ON DELETE CASCADE,
        FOREIGN KEY (global_size_id) REFERENCES global_sizes(id) ON DELETE CASCADE,
        FOREIGN KEY (global_color_id) REFERENCES global_colors(id) ON DELETE CASCADE,
        UNIQUE KEY unique_item_size_color (item_sku, global_size_id, global_color_id),
        INDEX idx_item_sku (item_sku),
        INDEX idx_size_id (global_size_id),
        INDEX idx_color_id (global_color_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($createItemColorAssignmentsTable);
    echo "✅ Created item_color_assignments table.\n";

    // Insert default global colors
    $defaultColors = [
        ['Black', '#000000', 'Basic', 'Classic black color'],
        ['White', '#FFFFFF', 'Basic', 'Pure white color'],
        ['Navy Blue', '#000080', 'Basic', 'Deep navy blue'],
        ['Red', '#FF0000', 'Basic', 'Bright red color'],
        ['Blue', '#0000FF', 'Basic', 'Standard blue'],
        ['Green', '#008000', 'Basic', 'Standard green'],
        ['Pink', '#FFC0CB', 'Pastel', 'Light pink color'],
        ['Purple', '#800080', 'Vibrant', 'Deep purple'],
        ['Orange', '#FFA500', 'Vibrant', 'Bright orange'],
        ['Yellow', '#FFFF00', 'Vibrant', 'Bright yellow'],
        ['Gray', '#808080', 'Neutral', 'Medium gray'],
        ['Silver', '#C0C0C0', 'Metallic', 'Silver metallic'],
        ['Gold', '#FFD700', 'Metallic', 'Gold metallic'],
        ['Brown', '#A52A2A', 'Earth', 'Rich brown'],
        ['Maroon', '#800000', 'Deep', 'Deep maroon'],
        ['Teal', '#008080', 'Cool', 'Teal blue-green']
    ];

    $insertColorStmt = $pdo->prepare("
        INSERT IGNORE INTO global_colors (color_name, color_code, category, description, display_order) 
        VALUES (?, ?, ?, ?, ?)
    ");

    $order = 1;
    foreach ($defaultColors as $color) {
        $insertColorStmt->execute([$color[0], $color[1], $color[2], $color[3], $order]);
        $order++;
    }
    echo "✅ Inserted default global colors.\n";

    // Insert default global sizes for T-shirts
    $defaultSizes = [
        ['Extra Small', 'XS', 'T-Shirts', 'Extra Small size'],
        ['Small', 'S', 'T-Shirts', 'Small size'],
        ['Medium', 'M', 'T-Shirts', 'Medium size'],
        ['Large', 'L', 'T-Shirts', 'Large size'],
        ['Extra Large', 'XL', 'T-Shirts', 'Extra Large size'],
        ['2X Large', '2XL', 'T-Shirts', '2X Large size'],
        ['3X Large', '3XL', 'T-Shirts', '3X Large size'],
        ['Youth Small', 'YS', 'Youth', 'Youth Small size'],
        ['Youth Medium', 'YM', 'Youth', 'Youth Medium size'],
        ['Youth Large', 'YL', 'Youth', 'Youth Large size'],
        ['20oz', '20oz', 'Tumblers', '20 ounce tumbler'],
        ['30oz', '30oz', 'Tumblers', '30 ounce tumbler'],
        ['Small Print', 'SM', 'Prints', 'Small print size'],
        ['Medium Print', 'MD', 'Prints', 'Medium print size'],
        ['Large Print', 'LG', 'Prints', 'Large print size']
    ];

    $insertSizeStmt = $pdo->prepare("
        INSERT IGNORE INTO global_sizes (size_name, size_code, category, description, display_order) 
        VALUES (?, ?, ?, ?, ?)
    ");

    $order = 1;
    foreach ($defaultSizes as $size) {
        $insertSizeStmt->execute([$size[0], $size[1], $size[2], $size[3], $order]);
        $order++;
    }
    echo "✅ Inserted default global sizes.\n";

    echo "\n🎉 Global Color and Size Management System initialized successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Access Admin Settings > Global Color & Size Management\n";
    echo "2. Customize colors and sizes for your needs\n";
    echo "3. Assign sizes and colors to individual items\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 
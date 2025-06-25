<?php
// Initialize Item Colors Database Tables
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected to database successfully.\n";

    // Create item_colors table
    $createItemColorsTable = "
    CREATE TABLE IF NOT EXISTS item_colors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_sku VARCHAR(64) NOT NULL,
        color_name VARCHAR(100) NOT NULL,
        color_code VARCHAR(7) DEFAULT NULL,
        stock_level INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (item_sku) REFERENCES items(sku) ON DELETE CASCADE,
        UNIQUE KEY unique_item_color (item_sku, color_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($createItemColorsTable);
    echo "Created item_colors table.\n";

    // Insert some sample color data for existing items
    $sampleColors = [
        ['WF-TS-001', 'Black', '#000000', 15],
        ['WF-TS-001', 'White', '#FFFFFF', 20],
        ['WF-TS-001', 'Navy Blue', '#000080', 12],
        ['WF-TS-002', 'Red', '#FF0000', 8],
        ['WF-TS-002', 'Blue', '#0000FF', 10],
        ['WF-TU-001', 'Silver', '#C0C0C0', 5],
        ['WF-TU-001', 'Black', '#000000', 7],
        ['WF-TU-002', 'White', '#FFFFFF', 6],
        ['WF-TU-002', 'Pink', '#FFC0CB', 4],
        ['WF-TU-003', 'Blue', '#0000FF', 9],
        ['WF-TU-003', 'Green', '#008000', 6]
    ];

    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO item_colors (item_sku, color_name, color_code, stock_level, display_order) 
        VALUES (?, ?, ?, ?, ?)
    ");

    $order = 1;
    foreach ($sampleColors as $color) {
        $insertStmt->execute([$color[0], $color[1], $color[2], $color[3], $order]);
        $order++;
        if ($order > 3) $order = 1; // Reset order for each new item
    }

    echo "Inserted sample color data.\n";
    echo "Item colors database initialization completed successfully!\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 
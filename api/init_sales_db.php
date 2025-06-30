<?php
require_once 'config.php';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Create sales table
    $salesTableSql = "
    CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        discount_percentage DECIMAL(5,2) NOT NULL,
        start_date DATETIME NOT NULL,
        end_date DATETIME NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Create sale_items table (many-to-many relationship between sales and items)
    $saleItemsTableSql = "
    CREATE TABLE IF NOT EXISTS sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        item_sku VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (item_sku) REFERENCES items(sku) ON DELETE CASCADE,
        UNIQUE KEY unique_sale_item (sale_id, item_sku)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Execute table creation
    $pdo->exec($salesTableSql);
    echo "✅ Created 'sales' table successfully\n";
    
    $pdo->exec($saleItemsTableSql);
    echo "✅ Created 'sale_items' table successfully\n";
    
    // Add sample data for testing
    $sampleSales = [
        [
            'name' => 'Summer Sale 2024',
            'description' => 'Great discounts on selected summer items',
            'discount_percentage' => 20.00,
            'start_date' => '2024-06-01 00:00:00',
            'end_date' => '2024-08-31 23:59:59',
            'is_active' => true
        ],
        [
            'name' => 'Holiday Special',
            'description' => 'Special holiday pricing on featured products',
            'discount_percentage' => 15.00,
            'start_date' => '2024-12-01 00:00:00',
            'end_date' => '2024-12-31 23:59:59',
            'is_active' => false
        ]
    ];
    
    $insertSale = $pdo->prepare("
        INSERT INTO sales (name, description, discount_percentage, start_date, end_date, is_active) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($sampleSales as $sale) {
        $insertSale->execute([
            $sale['name'],
            $sale['description'],
            $sale['discount_percentage'],
            $sale['start_date'],
            $sale['end_date'],
            $sale['is_active']
        ]);
    }
    
    echo "✅ Added sample sales data\n";
    echo "✅ Sales database initialization completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error initializing sales database: " . $e->getMessage() . "\n";
    exit(1);
}
?> 
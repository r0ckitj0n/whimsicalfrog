<?php
// Initialize dashboard sections table for configurable dashboard widgets
require_once __DIR__ . '/config.php';

try {
    $pdo = Database::getInstance();
    
    // Create dashboard_sections table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dashboard_sections (
            id INT PRIMARY KEY AUTO_INCREMENT,
            section_key VARCHAR(50) NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            show_title TINYINT(1) NOT NULL DEFAULT 1,
            show_description TINYINT(1) NOT NULL DEFAULT 1,
            custom_title VARCHAR(255) NULL,
            custom_description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_section (section_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insert default dashboard sections
    $defaultSections = [
        ['metrics', 1, 1, 1, 1, null, null],
        ['recent_orders', 2, 1, 1, 1, null, null],
        ['low_stock', 3, 1, 1, 1, null, null]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO dashboard_sections (section_key, display_order, is_active, show_title, show_description, custom_title, custom_description)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        display_order = VALUES(display_order),
        is_active = VALUES(is_active),
        show_title = VALUES(show_title),
        show_description = VALUES(show_description),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $insertedCount = 0;
    foreach ($defaultSections as $section) {
        $stmt->execute($section);
        $insertedCount++;
    }
    
    echo "✅ Dashboard sections table initialized successfully!\n";
    echo "📊 Inserted/updated {$insertedCount} default dashboard sections\n";
    echo "\nDefault sections:\n";
    echo "- 📊 Quick Metrics\n";
    echo "- 📋 Recent Orders\n";
    echo "- ⚠️ Low Stock Alerts\n";
    echo "\n🔧 Use Admin Settings > Dashboard Configuration to customize your dashboard\n";
    
    // Verify the table was created correctly
    $result = $pdo->query("SELECT COUNT(*) as count FROM dashboard_sections")->fetch();
    echo "\n✨ Total dashboard sections in database: {$result['count']}\n";
    
} catch (Exception $e) {
    echo "❌ Error initializing dashboard sections table: " . $e->getMessage() . "\n";
    exit(1);
}
?> 
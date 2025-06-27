<?php
// Initialize Size Templates Database Tables
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected to database successfully.\n";

    // Create size_templates table
    $createSizeTemplatesTable = "
    CREATE TABLE IF NOT EXISTS size_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        category VARCHAR(50) DEFAULT 'General',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($createSizeTemplatesTable);
    echo "✅ Created size_templates table.\n";

    // Create size_template_items table (the actual sizes in each template)
    $createSizeTemplateItemsTable = "
    CREATE TABLE IF NOT EXISTS size_template_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_id INT NOT NULL,
        size_name VARCHAR(50) NOT NULL,
        size_code VARCHAR(10) NOT NULL,
        price_adjustment DECIMAL(10,2) DEFAULT 0.00,
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (template_id) REFERENCES size_templates(id) ON DELETE CASCADE,
        INDEX idx_template (template_id),
        INDEX idx_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($createSizeTemplateItemsTable);
    echo "✅ Created size_template_items table.\n";

    // Insert default size templates
    $defaultTemplates = [
        [
            'name' => 'Standard T-Shirt Sizes',
            'description' => 'Standard apparel sizing from XS to XXL',
            'category' => 'T-Shirts',
            'sizes' => [
                ['Extra Small', 'XS', 0.00, 0],
                ['Small', 'S', 0.00, 1],
                ['Medium', 'M', 0.00, 2],
                ['Large', 'L', 0.00, 3],
                ['Extra Large', 'XL', 1.00, 4],
                ['2X Large', 'XXL', 2.00, 5]
            ]
        ],
        [
            'name' => 'Extended T-Shirt Sizes',
            'description' => 'Extended apparel sizing including 3XL and 4XL',
            'category' => 'T-Shirts',
            'sizes' => [
                ['Extra Small', 'XS', 0.00, 0],
                ['Small', 'S', 0.00, 1],
                ['Medium', 'M', 0.00, 2],
                ['Large', 'L', 0.00, 3],
                ['Extra Large', 'XL', 1.00, 4],
                ['2X Large', 'XXL', 2.00, 5],
                ['3X Large', 'XXXL', 3.00, 6],
                ['4X Large', 'XXXXL', 4.00, 7]
            ]
        ],
        [
            'name' => 'Tumbler Sizes',
            'description' => 'Common drinkware volume sizes',
            'category' => 'Tumblers',
            'sizes' => [
                ['12 oz', '12OZ', 0.00, 0],
                ['16 oz', '16OZ', 2.00, 1],
                ['20 oz', '20OZ', 4.00, 2],
                ['24 oz', '24OZ', 6.00, 3],
                ['30 oz', '30OZ', 8.00, 4],
                ['40 oz', '40OZ', 12.00, 5]
            ]
        ],
        [
            'name' => 'Artwork Sizes',
            'description' => 'Standard print and artwork dimensions',
            'category' => 'Artwork',
            'sizes' => [
                ['5x7 inches', '5X7', 0.00, 0],
                ['8x10 inches', '8X10', 5.00, 1],
                ['11x14 inches', '11X14', 10.00, 2],
                ['16x20 inches', '16X20', 20.00, 3],
                ['18x24 inches', '18X24', 30.00, 4],
                ['24x36 inches', '24X36', 50.00, 5]
            ]
        ],
        [
            'name' => 'Youth Sizes',
            'description' => 'Youth and children\'s apparel sizing',
            'category' => 'Youth',
            'sizes' => [
                ['Youth XS', 'YXS', -2.00, 0],
                ['Youth S', 'YS', -2.00, 1],
                ['Youth M', 'YM', -2.00, 2],
                ['Youth L', 'YL', -1.00, 3],
                ['Youth XL', 'YXL', -1.00, 4]
            ]
        ],
        [
            'name' => 'Window Decal Sizes',
            'description' => 'Common vehicle window decal dimensions',
            'category' => 'Window Wraps',
            'sizes' => [
                ['Small (4x6)', 'SM', 0.00, 0],
                ['Medium (6x8)', 'MD', 3.00, 1],
                ['Large (8x10)', 'LG', 6.00, 2],
                ['Extra Large (10x12)', 'XL', 10.00, 3],
                ['Jumbo (12x16)', 'JB', 15.00, 4]
            ]
        ],
        [
            'name' => 'Basic Sizes',
            'description' => 'Simple Small, Medium, Large sizing',
            'category' => 'General',
            'sizes' => [
                ['Small', 'S', 0.00, 0],
                ['Medium', 'M', 0.00, 1],
                ['Large', 'L', 2.00, 2]
            ]
        ]
    ];

    foreach ($defaultTemplates as $template) {
        // Insert template
        $stmt = $pdo->prepare("
            INSERT INTO size_templates (template_name, description, category) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$template['name'], $template['description'], $template['category']]);
        $templateId = $pdo->lastInsertId();
        
        // Insert sizes for this template
        $sizeStmt = $pdo->prepare("
            INSERT INTO size_template_items (template_id, size_name, size_code, price_adjustment, display_order) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($template['sizes'] as $size) {
            $sizeStmt->execute([$templateId, $size[0], $size[1], $size[2], $size[3]]);
        }
        
        echo "✅ Created size template: {$template['name']} with " . count($template['sizes']) . " sizes.\n";
    }

    echo "\n🎉 Size templates database initialization completed successfully!\n";
    echo "📊 Created " . count($defaultTemplates) . " default size templates.\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 
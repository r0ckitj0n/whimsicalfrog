<?php
// Initialize Color Templates Database Tables
require_once __DIR__ . '/config.php';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    echo "Connected to database successfully.\n";

    // Create color_templates table
    $createColorTemplatesTable = "
    CREATE TABLE IF NOT EXISTS color_templates (
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

    $pdo->exec($createColorTemplatesTable);
    echo "✅ Created color_templates table.\n";

    // Create color_template_items table (the actual colors in each template)
    $createColorTemplateItemsTable = "
    CREATE TABLE IF NOT EXISTS color_template_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_id INT NOT NULL,
        color_name VARCHAR(50) NOT NULL,
        color_code VARCHAR(7) DEFAULT NULL,
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (template_id) REFERENCES color_templates(id) ON DELETE CASCADE,
        INDEX idx_template (template_id),
        INDEX idx_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($createColorTemplateItemsTable);
    echo "✅ Created color_template_items table.\n";

    // Insert default color templates
    $defaultTemplates = [
        [
            'name' => 'Basic T-Shirt Colors',
            'description' => 'Standard t-shirt color options for most apparel items',
            'category' => 'T-Shirts',
            'colors' => [
                ['Black', '#000000', 0],
                ['White', '#FFFFFF', 1],
                ['Navy Blue', '#000080', 2],
                ['Gray', '#808080', 3],
                ['Red', '#FF0000', 4],
                ['Royal Blue', '#4169E1', 5]
            ]
        ],
        [
            'name' => 'Tumbler Colors',
            'description' => 'Popular color choices for drinkware and tumblers',
            'category' => 'Tumblers',
            'colors' => [
                ['Stainless Steel', '#C0C0C0', 0],
                ['Matte Black', '#1C1C1C', 1],
                ['White', '#FFFFFF', 2],
                ['Rose Gold', '#E8B4B8', 3],
                ['Navy Blue', '#000080', 4],
                ['Forest Green', '#228B22', 5]
            ]
        ],
        [
            'name' => 'Sublimation Colors',
            'description' => 'Color options for sublimation products',
            'category' => 'Sublimation',
            'colors' => [
                ['White', '#FFFFFF', 0],
                ['Light Gray', '#D3D3D3', 1],
                ['Cream', '#F5F5DC', 2],
                ['Light Blue', '#ADD8E6', 3]
            ]
        ],
        [
            'name' => 'Premium Colors',
            'description' => 'High-end color palette for premium products',
            'category' => 'Premium',
            'colors' => [
                ['Charcoal', '#36454F', 0],
                ['Platinum', '#E5E4E2', 1],
                ['Deep Navy', '#000080', 2],
                ['Burgundy', '#800020', 3],
                ['Forest Green', '#228B22', 4],
                ['Copper', '#B87333', 5]
            ]
        ],
        [
            'name' => 'Bright & Bold',
            'description' => 'Vibrant colors for eye-catching designs',
            'category' => 'Vibrant',
            'colors' => [
                ['Electric Blue', '#7DF9FF', 0],
                ['Hot Pink', '#FF69B4', 1],
                ['Lime Green', '#32CD32', 2],
                ['Orange', '#FFA500', 3],
                ['Purple', '#800080', 4],
                ['Yellow', '#FFFF00', 5]
            ]
        ]
    ];

    foreach ($defaultTemplates as $template) {
        // Insert template
        $stmt = $pdo->prepare("
            INSERT INTO color_templates (template_name, description, category) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$template['name'], $template['description'], $template['category']]);
        $templateId = $pdo->lastInsertId();
        
        // Insert colors for this template
        $colorStmt = $pdo->prepare("
            INSERT INTO color_template_items (template_id, color_name, color_code, display_order) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($template['colors'] as $color) {
            $colorStmt->execute([$templateId, $color[0], $color[1], $color[2]]);
        }
        
        echo "✅ Created color template: {$template['name']} with " . count($template['colors']) . " colors.\n";
    }

    echo "\n🎉 Color templates database initialization completed successfully!\n";
    echo "📊 Created " . count($defaultTemplates) . " default color templates.\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 
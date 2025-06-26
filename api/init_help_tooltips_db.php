<?php
// Initialize Help Tooltips Database Table
require_once 'config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Create help_tooltips table
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS help_tooltips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        element_id VARCHAR(255) NOT NULL,
        page_context VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        position ENUM('top', 'bottom', 'left', 'right') DEFAULT 'top',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_tooltip (element_id, page_context),
        INDEX idx_page_context (page_context),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTableSQL);
    
    // Insert default tooltips for admin settings
    $defaultTooltips = [
        // Admin Settings Main Page
        [
            'element_id' => 'categoriesBtn',
            'page_context' => 'settings',
            'title' => 'Category Management',
            'content' => 'Manage product categories. Add, edit, or remove categories that organize your inventory items.',
            'position' => 'bottom'
        ],
        [
            'element_id' => 'room-category-btn',
            'page_context' => 'settings',
            'title' => 'Room-Category Links',
            'content' => 'Link product categories to specific rooms. This determines which products appear in each room.',
            'position' => 'bottom'
        ],
        [
            'element_id' => 'room-mapper-btn',
            'page_context' => 'settings',
            'title' => 'Room Mapper',
            'content' => 'Visual tool to map clickable areas on room images. Define where customers can click to view products.',
            'position' => 'bottom'
        ],
        [
            'element_id' => 'database-tables-btn',
            'page_context' => 'settings',
            'title' => 'Database Tables',
            'content' => 'Advanced database management. View, edit, and manage all database tables directly.',
            'position' => 'bottom'
        ],

        [
            'element_id' => 'business-settings-btn',
            'page_context' => 'settings',
            'title' => 'Business Settings',
            'content' => 'Configure core business information like company name, contact details, payment methods, and operational settings.',
            'position' => 'bottom'
        ],
        [
            'element_id' => 'global-css-btn',
            'page_context' => 'settings',
            'title' => 'Global CSS Rules',
            'content' => 'Customize the visual appearance of your website. Change colors, fonts, spacing, and other design elements.',
            'position' => 'bottom'
        ],
        [
            'element_id' => 'help-hints-btn',
            'page_context' => 'settings',
            'title' => 'Help Hints Management',
            'content' => 'Create and manage hover tooltips for admin interface elements. Add helpful hints to guide users through complex features.',
            'position' => 'bottom'
        ],
        
        // Inventory Management
        [
            'element_id' => 'add-new-item-btn',
            'page_context' => 'inventory',
            'title' => 'Add New Item',
            'content' => 'Create a new inventory item. Upload images and let AI help with descriptions and pricing.',
            'position' => 'bottom'
        ],
        [
            'element_id' => 'search-items-input',
            'page_context' => 'inventory',
            'title' => 'Search Items',
            'content' => 'Search your inventory by name, SKU, category, or description. Use filters to narrow results.',
            'position' => 'bottom'
        ],
        [
            'element_id' => 'cost-suggestion-btn',
            'page_context' => 'inventory',
            'title' => 'AI Cost Suggestion',
            'content' => 'Get AI-powered cost analysis based on materials, labor, and market data. Helps determine accurate pricing.',
            'position' => 'top'
        ],
        [
            'element_id' => 'price-suggestion-btn',
            'page_context' => 'inventory',
            'title' => 'AI Price Suggestion',
            'content' => 'AI analyzes your costs, market conditions, and competition to suggest optimal retail pricing.',
            'position' => 'top'
        ],
        
        // Order Management
        [
            'element_id' => 'order-status-filter',
            'page_context' => 'orders',
            'title' => 'Order Status Filter',
            'content' => 'Filter orders by status: pending, processing, shipped, delivered, or cancelled.',
            'position' => 'bottom'
        ],
        [
            'element_id' => 'bulk-actions-select',
            'page_context' => 'orders',
            'title' => 'Bulk Actions',
            'content' => 'Perform actions on multiple orders at once. Update status, print labels, or send notifications.',
            'position' => 'bottom'
        ],
        
        // Common Elements
        [
            'element_id' => 'save-btn',
            'page_context' => 'common',
            'title' => 'Save Changes',
            'content' => 'Save your changes to the database. Make sure all required fields are filled out correctly.',
            'position' => 'top'
        ],
        [
            'element_id' => 'cancel-btn',
            'page_context' => 'common',
            'title' => 'Cancel',
            'content' => 'Discard changes and return to the previous screen. Any unsaved changes will be lost.',
            'position' => 'top'
        ]
    ];
    
    // Check if tooltips already exist and insert only new ones
    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO help_tooltips (element_id, page_context, title, content, position) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $insertedCount = 0;
    foreach ($defaultTooltips as $tooltip) {
        if ($insertStmt->execute([
            $tooltip['element_id'],
            $tooltip['page_context'],
            $tooltip['title'],
            $tooltip['content'],
            $tooltip['position']
        ])) {
            if ($insertStmt->rowCount() > 0) {
                $insertedCount++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Help tooltips table initialized successfully. Inserted $insertedCount new default tooltips.",
        'table_created' => true,
        'default_tooltips_added' => $insertedCount
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 
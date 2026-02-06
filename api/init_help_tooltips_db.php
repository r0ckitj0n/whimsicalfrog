<?php
// Re-initialize Help Tooltips Database Table
require_once __DIR__ . '/config.php';

try {
    $pdo = Database::getInstance();

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

    Database::execute($createTableSQL);

    // Insert default tooltips for admin settings
    $defaultTooltips = [
        [
            'element_id' => 'categoriesBtn',
            'page_context' => 'settings',
            'title' => 'Category Management',
            'content' => 'Manage item categories. Add, edit, or remove categories that organize your inventory items.',
            'position' => 'bottom'
        ],
        [
            'element_id' => 'room-category-btn',
            'page_context' => 'settings',
            'title' => 'Room-Category Links',
            'content' => 'Link item categories to specific rooms. This determines which items appear in each room.',
            'position' => 'bottom'
        ],
        [
            'element_id' => 'room-mapper-btn',
            'page_context' => 'settings',
            'title' => 'Room Map Editor',
            'content' => 'Draw and edit clickable polygons on room background images. Define the areas where customers can click to view items.',
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
        // Global tooltips (Feb 2026 - Phase 2 standardization)
        [
            'element_id' => 'common-close',
            'page_context' => 'global',
            'title' => 'Close',
            'content' => 'Close this panel or modal window.',
            'position' => 'top'
        ],
        [
            'element_id' => 'address-add-new',
            'page_context' => 'checkout',
            'title' => 'Add Address',
            'content' => 'Add a new shipping address to your account.',
            'position' => 'top'
        ],
        [
            'element_id' => 'captcha-explanation',
            'page_context' => 'contact',
            'title' => 'Human Verification',
            'content' => 'We use a quick human check to protect our contact details from spam bots.',
            'position' => 'top'
        ]
    ];

    $insertedCount = 0;
    foreach ($defaultTooltips as $tooltip) {
        $affected = Database::execute(
            "INSERT IGNORE INTO help_tooltips (element_id, page_context, title, content, position) VALUES (?, ?, ?, ?, ?)",
            [
                $tooltip['element_id'],
                $tooltip['page_context'],
                $tooltip['title'],
                $tooltip['content'],
                $tooltip['position']
            ]
        );
        if ($affected > 0)
            $insertedCount++;
    }

    echo json_encode([
        'success' => true,
        'message' => "Help tooltips table initialized successfully. Re-inserted $insertedCount tooltips.",
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

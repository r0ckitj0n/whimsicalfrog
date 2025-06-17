<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Create global_css_rules table
    $createTable = "
    CREATE TABLE IF NOT EXISTS global_css_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rule_name VARCHAR(100) NOT NULL UNIQUE,
        css_property VARCHAR(100) NOT NULL,
        css_value TEXT NOT NULL,
        category VARCHAR(50) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTable);

    // Insert default CSS rules
    $defaultRules = [
        // Brand Colors
        ['primary_color', 'color', '#87ac3a', 'brand', 'Primary brand color (WhimsicalFrog green)'],
        ['primary_color_hover', 'color', '#a3cc4a', 'brand', 'Primary brand color on hover'],
        ['secondary_color', 'color', '#556B2F', 'brand', 'Secondary brand color (darker green)'],
        ['accent_color', 'color', '#6B8E23', 'brand', 'Accent color for highlights'],
        
        // Typography
        ['font_family_primary', 'font-family', "'Merienda', cursive", 'typography', 'Primary font family'],
        ['font_size_base', 'font-size', '16px', 'typography', 'Base font size'],
        ['font_size_heading', 'font-size', '24px', 'typography', 'Heading font size'],
        ['line_height_base', 'line-height', '1.6', 'typography', 'Base line height'],
        
        // Buttons
        ['button_bg_primary', 'background-color', '#87ac3a', 'buttons', 'Primary button background'],
        ['button_bg_primary_hover', 'background-color', '#a3cc4a', 'buttons', 'Primary button hover background'],
        ['button_text_primary', 'color', '#ffffff', 'buttons', 'Primary button text color'],
        ['button_border_radius', 'border-radius', '8px', 'buttons', 'Button border radius'],
        ['button_padding', 'padding', '10px 20px', 'buttons', 'Button padding'],
        
        // Layout
        ['container_max_width', 'max-width', '1200px', 'layout', 'Maximum container width'],
        ['border_radius_default', 'border-radius', '8px', 'layout', 'Default border radius'],
        ['shadow_default', 'box-shadow', '0 4px 6px rgba(0, 0, 0, 0.1)', 'layout', 'Default shadow'],
        ['spacing_small', 'margin', '8px', 'layout', 'Small spacing unit'],
        ['spacing_medium', 'margin', '16px', 'layout', 'Medium spacing unit'],
        ['spacing_large', 'margin', '24px', 'layout', 'Large spacing unit'],
        
        // Navigation
        ['nav_bg_color', 'background-color', 'rgba(0, 0, 0, 0.95)', 'navigation', 'Navigation background color'],
        ['nav_text_color', 'color', '#ffffff', 'navigation', 'Navigation text color'],
        ['nav_link_hover', 'color', '#87ac3a', 'navigation', 'Navigation link hover color'],
        
        // Forms
        ['input_border_color', 'border-color', '#d1d5db', 'forms', 'Input border color'],
        ['input_border_radius', 'border-radius', '6px', 'forms', 'Input border radius'],
        ['input_padding', 'padding', '8px 12px', 'forms', 'Input padding'],
        ['input_focus_color', 'border-color', '#87ac3a', 'forms', 'Input focus border color'],
        
        // Modals
        ['modal_bg_color', 'background-color', '#ffffff', 'modals', 'Modal background color'],
        ['modal_overlay_color', 'background-color', 'rgba(0, 0, 0, 0.5)', 'modals', 'Modal overlay color'],
        ['modal_border_radius', 'border-radius', '12px', 'modals', 'Modal border radius'],
        ['modal_shadow', 'box-shadow', '0 25px 50px -12px rgba(0, 0, 0, 0.25)', 'modals', 'Modal shadow'],
        
        // Admin Interface
        ['admin_bg_color', 'background-color', '#f9fafb', 'admin', 'Admin interface background'],
        ['admin_sidebar_bg', 'background-color', '#ffffff', 'admin', 'Admin sidebar background'],
        ['admin_text_color', 'color', '#374151', 'admin', 'Admin text color'],
        ['admin_border_color', 'border-color', '#e5e7eb', 'admin', 'Admin border color']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO global_css_rules (rule_name, css_property, css_value, category, description)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        css_property = VALUES(css_property),
        css_value = VALUES(css_value),
        category = VALUES(category),
        description = VALUES(description),
        updated_at = CURRENT_TIMESTAMP
    ");

    $insertedCount = 0;
    foreach ($defaultRules as $rule) {
        $stmt->execute($rule);
        $insertedCount++;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Global CSS rules table created successfully',
        'rules_inserted' => $insertedCount,
        'total_rules' => count($defaultRules)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 
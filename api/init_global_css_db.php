<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';

try {
    $pdo = Database::getInstance();

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
        
        // Popups - Standard System
        ['popup_bg_color', 'background-color', '#ffffff', 'popups', 'Popup background color'],
        ['popup_border_color', 'border-color', '#8B4513', 'popups', 'Popup border color'],
        ['popup_border_width', 'border-width', '3px', 'popups', 'Popup border width'],
        ['popup_border_radius', 'border-radius', '15px', 'popups', 'Popup border radius'],
        ['popup_shadow', 'box-shadow', '0 8px 25px rgba(0, 0, 0, 0.3)', 'popups', 'Popup shadow'],
        ['popup_padding', 'padding', '15px', 'popups', 'Popup padding'],
        ['popup_min_width', 'min-width', '280px', 'popups', 'Popup minimum width'],
        ['popup_max_width', 'max-width', '450px', 'popups', 'Popup maximum width'],
        ['popup_z_index', 'z-index', '200', 'popups', 'Popup z-index'],
        
        // Popup Text Elements
        ['popup_title_color', 'color', '#556B2F', 'popups', 'Popup title color'],
        ['popup_title_size', 'font-size', '16px', 'popups', 'Popup title font size'],
        ['popup_title_weight', 'font-weight', 'bold', 'popups', 'Popup title font weight'],
        ['popup_category_color', 'color', '#6B8E23', 'popups', 'Popup category color'],
        ['popup_category_size', 'font-size', '12px', 'popups', 'Popup category font size'],
        ['popup_description_color', 'color', '#666666', 'popups', 'Popup description color'],
        ['popup_description_size', 'font-size', '12px', 'popups', 'Popup description font size'],
        ['popup_price_color', 'color', '#6B8E23', 'popups', 'Popup price color'],
        ['popup_price_size', 'font-size', '18px', 'popups', 'Popup price font size'],
        
        // Enhanced Popups System
        ['popup_enhanced_bg_color', 'background-color', '#ffffff', 'popups_enhanced', 'Enhanced popup background'],
        ['popup_enhanced_border_radius', 'border-radius', '20px', 'popups_enhanced', 'Enhanced popup border radius'],
        ['popup_enhanced_shadow', 'box-shadow', '0 12px 40px rgba(0, 0, 0, 0.4)', 'popups_enhanced', 'Enhanced popup shadow'],
        ['popup_enhanced_min_width', 'min-width', '400px', 'popups_enhanced', 'Enhanced popup minimum width'],
        ['popup_enhanced_max_width', 'max-width', '600px', 'popups_enhanced', 'Enhanced popup maximum width'],
        ['popup_enhanced_padding', 'padding', '20px', 'popups_enhanced', 'Enhanced popup padding'],
        
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
        ['filter_label_color', 'color', '#87ac3a', 'forms', 'Filter label color (From:, To:, Clear)'],
        ['filter_label_font_weight', 'font-weight', '500', 'forms', 'Filter label font weight'],
        ['filter_label_font_size', 'font-size', '0.875rem', 'forms', 'Filter label font size'],
        
        // Modals
        ['modal_bg_color', 'background-color', '#ffffff', 'modals', 'Modal background color'],
        ['modal_overlay_color', 'background-color', 'rgba(0, 0, 0, 0.5)', 'modals', 'Modal overlay color'],
        ['modal_border_radius', 'border-radius', '12px', 'modals', 'Modal border radius'],
        ['modal_shadow', 'box-shadow', '0 25px 50px -12px rgba(0, 0, 0, 0.25)', 'modals', 'Modal shadow'],
        
        // Room Headers - Make room titles/descriptions configurable
        ['room_title_font_family', 'font-family', "'Merienda', cursive", 'room_headers', 'Room title font family'],
        ['room_title_font_size', 'font-size', '2.5rem', 'room_headers', 'Room title font size'],
        ['room_title_color', 'color', '#ffffff', 'room_headers', 'Room title color'],
        ['room_title_text_stroke', 'text-stroke', '2px #556B2F', 'room_headers', 'Room title text stroke'],
        ['room_title_text_shadow', 'text-shadow', 'none', 'room_headers', 'Room title text shadow'],
        ['room_description_font_size', 'font-size', '1rem', 'room_headers', 'Room description font size'],
        ['room_description_color', 'color', '#ffffff', 'room_headers', 'Room description color'],
        ['room_description_text_stroke', 'text-stroke', '2px #556B2F', 'room_headers', 'Room description text stroke'],
        ['room_description_text_shadow', 'text-shadow', 'none', 'room_headers', 'Room description text shadow'],
        
        // Admin Interface
        ['admin_bg_color', 'background-color', '#f9fafb', 'admin', 'Admin interface background'],
        ['admin_sidebar_bg', 'background-color', '#ffffff', 'admin', 'Admin sidebar background'],
        ['admin_text_color', 'color', '#374151', 'admin', 'Admin text color'],
        ['admin_border_color', 'border-color', '#e5e7eb', 'admin', 'Admin border color'],
        
        // Admin Modal Headers
        ['admin_modal_header_bg', 'background-color', '#87ac3a', 'admin_modals', 'Admin modal header background'],
        ['admin_modal_header_bg_gradient', 'background', 'linear-gradient(to right, #87ac3a, #a3cc4a)', 'admin_modals', 'Admin modal header gradient'],
        ['admin_modal_header_text', 'color', '#ffffff', 'admin_modals', 'Admin modal header text color'],
        ['admin_modal_sales_header_bg', 'background', 'linear-gradient(to right, #87ac3a, #a3cc4a)', 'admin_modals', 'Sales admin modal header gradient'],
        ['brand_bg_text_color', 'color', '#ffffff', 'brand', 'Text color when background uses brand colors'],
        
        // Modal Close Button System
        ['modal_close_position', '--modal-close-position', 'top-right', 'modal_close', 'Position of the X close button in modals'],
        ['modal_close_top', '--modal-close-top', '10px', 'modal_close', 'Distance from top edge of modal'],
        ['modal_close_right', '--modal-close-right', '15px', 'modal_close', 'Distance from right edge of modal'],
        ['modal_close_left', '--modal-close-left', '15px', 'modal_close', 'Distance from left edge of modal'],
        ['modal_close_size', '--modal-close-size', '30px', 'modal_close', 'Size of the close button'],
        ['modal_close_font_size', '--modal-close-font-size', '24px', 'modal_close', 'Font size of the X symbol'],
        ['modal_close_color', '--modal-close-color', '#6b7280', 'modal_close', 'Color of the close button'],
        ['modal_close_hover_color', '--modal-close-hover-color', '#374151', 'modal_close', 'Color when hovering over close button'],
        ['modal_close_bg_hover', '--modal-close-bg-hover', '#f3f4f6', 'modal_close', 'Background color when hovering'],
        
        // Section Headers - Admin Settings Page
        ['section_title_text_color', 'color', '#ffffff', 'admin_sections', 'Section title text color (white for visibility on gradient backgrounds)'],
        ['section_description_text_color', 'color', '#ffffff', 'admin_sections', 'Section description text color (white for visibility on gradient backgrounds)'],
        
        // Step Badges - First Time User Guide System
        ['step_badge_bg_color', 'background-color', '#ef4444', 'step_badges', 'Step badge background color'],
        ['step_badge_text_color', 'color', '#ffffff', 'step_badges', 'Step badge text color'],
        ['step_badge_size', 'width', '60px', 'step_badges', 'Step badge width and height'],
        ['step_badge_font_size', 'font-size', '12px', 'step_badges', 'Step badge font size'],
        ['step_badge_border_radius', 'border-radius', '50%', 'step_badges', 'Step badge border radius (circular)'],
        ['step_badge_z_index', 'z-index', '1000', 'step_badges', 'Step badge z-index for layering'],
        ['step_badge_position_top', 'top', '-10px', 'step_badges', 'Step badge position from top of button'],
        ['step_badge_position_right', 'right', '-10px', 'step_badges', 'Step badge position from right of button'],
        ['step_badge_shadow', 'box-shadow', '0 2px 8px rgba(0,0,0,0.3)', 'step_badges', 'Step badge shadow effect'],
        ['step_badge_font_weight', 'font-weight', 'bold', 'step_badges', 'Step badge font weight'],
        ['step_badge_animation_duration', 'animation-duration', '2s', 'step_badges', 'Step badge pulse animation duration'],
        ['step_badge_text_step1', 'content', '"Step 1"', 'step_badges', 'Text for Step 1 badge'],
        ['step_badge_text_step2', 'content', '"Step 2"', 'step_badges', 'Text for Step 2 badge'],
        ['step_badge_text_step3', 'content', '"Step 3"', 'step_badges', 'Text for Step 3 badge']
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
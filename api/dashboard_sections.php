<?php
// Dashboard Sections Management API
ob_start(); // Start output buffering to prevent header issues

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Clear any previous output that might interfere with headers
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Check admin authentication using centralized helper
AuthHelper::requireAdmin();

try {
    $db = Database::getInstance();

    // Parse JSON input for action and data
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? 'get_sections';

    switch ($action) {
        case 'get_sections':
            // Get all dashboard sections with available section info
            $sections = $db->query('SELECT * FROM dashboard_sections ORDER BY display_order ASC')->fetchAll();

            // Available section definitions
            $availableSections = [
                'metrics' => [
                    'title' => '📊 Quick Metrics',
                    'description' => 'Key performance indicators and business metrics',
                    'type' => 'built-in',
                    'category' => 'Analytics'
                ],
                'recent_orders' => [
                    'title' => '📋 Recent Orders',
                    'description' => 'Latest customer orders and order status',
                    'type' => 'built-in',
                    'category' => 'Orders'
                ],
                'low_stock' => [
                    'title' => '⚠️ Low Stock Alerts',
                    'description' => 'Items running low on inventory',
                    'type' => 'built-in',
                    'category' => 'Inventory'
                ],
                'inventory_summary' => [
                    'title' => '📦 Inventory Summary',
                    'description' => 'Mini version of inventory management',
                    'type' => 'external',
                    'source' => 'inventory',
                    'category' => 'Inventory'
                ],
                'customer_summary' => [
                    'title' => '👥 Customer Overview',
                    'description' => 'Recent customers and activity',
                    'type' => 'external',
                    'source' => 'customers',
                    'category' => 'Customers'
                ],
                'marketing_tools' => [
                    'title' => '📈 Marketing Tools',
                    'description' => 'Quick access to marketing features',
                    'type' => 'external',
                    'source' => 'marketing',
                    'category' => 'Marketing'
                ],
                'order_fulfillment' => [
                    'title' => '🚚 Order Fulfillment',
                    'description' => 'Opens full order fulfillment interface in modal',
                    'type' => 'modal',
                    'source' => 'order_fulfillment',
                    'category' => 'Orders'
                ],
                'reports_summary' => [
                    'title' => '📊 Reports Summary',
                    'description' => 'Key business reports and analytics',
                    'type' => 'external',
                    'source' => 'reports',
                    'category' => 'Analytics'
                ]
            ];

            // Enhance sections with available section info
            foreach ($sections as &$section) {
                $sectionInfo = $availableSections[$section['section_key']] ?? null;
                if ($sectionInfo) {
                    $section['section_info'] = $sectionInfo;
                    $section['display_title'] = $section['custom_title'] ?: $sectionInfo['title'];
                    $section['display_description'] = $section['custom_description'] ?: $sectionInfo['description'];
                }
            }

            Response::success([
                'sections' => $sections,
                'available_sections' => $availableSections
            ]);
            break;

        case 'update_sections':
            // Update dashboard sections configuration
            $data = $input; // Use already parsed JSON input
            if (!$data || !isset($data['sections'])) {
                Response::error('Invalid request data');
            }

            // Start transaction
            $db->beginTransaction();

            try {
                // Clear existing sections
                $db->query('DELETE FROM dashboard_sections');

                // Insert new configuration
                $stmt = $db->prepare('
                    INSERT INTO dashboard_sections 
                    (section_key, display_order, is_active, show_title, show_description, custom_title, custom_description, width_class) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');

                foreach ($data['sections'] as $section) {
                    $stmt->execute([
                        $section['section_key'],
                        $section['display_order'] ?? 0,
                        $section['is_active'] ? 1 : 0,
                        $section['show_title'] ? 1 : 0,
                        $section['show_description'] ? 1 : 0,
                        $section['custom_title'] ?: null,
                        $section['custom_description'] ?: null,
                        $section['width_class'] ?? 'half-width'
                    ]);
                }

                $db->commit();

                // Logger::userAction('dashboard_sections_updated', [
                //     'sections_count' => count($data['sections'])
                // ]);

                Response::success(['message' => 'Dashboard configuration updated successfully']);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'add_section':
            // Add a new section to the dashboard
            $data = $input; // Use already parsed JSON input
            if (!$data || !isset($data['section_key'])) {
                Response::error('Section key is required');
            }

            // Get the highest display order
            $maxOrder = $db->query('SELECT COALESCE(MAX(display_order), 0) as max_order FROM dashboard_sections')->fetch()['max_order'];

            $stmt = $db->prepare('
                INSERT INTO dashboard_sections 
                (section_key, display_order, is_active, show_title, show_description, custom_title, custom_description, width_class) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                is_active = VALUES(is_active),
                display_order = VALUES(display_order),
                width_class = VALUES(width_class)
            ');

            $stmt->execute([
                $data['section_key'],
                $maxOrder + 1,
                $data['is_active'] ?? 1,
                $data['show_title'] ?? 1,
                $data['show_description'] ?? 1,
                $data['custom_title'] ?? null,
                $data['custom_description'] ?? null,
                $data['width_class'] ?? 'half-width'
            ]);

            // Logger::userAction('dashboard_section_added', [
            //     'section_key' => $data['section_key']
            // ]);

            Response::success(['message' => 'Section added successfully']);
            break;

        case 'remove_section':
            // Remove a section from the dashboard
            $data = $input; // Use already parsed JSON input
            if (!$data || !isset($data['section_key'])) {
                Response::error('Section key is required');
            }

            $stmt = $db->prepare('DELETE FROM dashboard_sections WHERE section_key = ?');
            $stmt->execute([$data['section_key']]);

            // Logger::userAction('dashboard_section_removed', [
            //     'section_key' => $data['section_key']
            // ]);

            Response::success(['message' => 'Section removed successfully']);
            break;

        case 'reorder_section':
            // Reorder a single section
            $data = $input; // Use already parsed JSON input
            if (!$data || !isset($data['section_key']) || !isset($data['new_order'])) {
                Response::error('Section key and new order are required');
            }

            // Update the specific section's order
            $stmt = $db->prepare('UPDATE dashboard_sections SET display_order = ? WHERE section_key = ?');
            $result = $stmt->execute([$data['new_order'], $data['section_key']]);

            if (!$result) {
                Response::error('Failed to update section order');
            }

            // Normalize all orders to prevent gaps
            $sections = $db->query('SELECT section_key FROM dashboard_sections ORDER BY display_order ASC')->fetchAll();
            $normalizeStmt = $db->prepare('UPDATE dashboard_sections SET display_order = ? WHERE section_key = ?');

            foreach ($sections as $index => $section) {
                $normalizeStmt->execute([$index + 1, $section['section_key']]);
            }

            // Logger::userAction('dashboard_section_reordered', [
            //     'section_key' => $data['section_key'],
            //     'new_order' => $data['new_order']
            // ]);

            Response::success(['message' => 'Section reordered successfully']);
            break;

        case 'reorder_sections':
            // Reorder dashboard sections
            $data = $input; // Use already parsed JSON input
            if (!$data || !isset($data['sections'])) {
                Response::error('Section order data is required');
            }

            $stmt = $db->prepare('UPDATE dashboard_sections SET display_order = ? WHERE section_key = ?');

            foreach ($data['sections'] as $section) {
                $stmt->execute([
                    $section['display_order'],
                    $section['section_key']
                ]);
            }

            // Logger::userAction('dashboard_sections_reordered', [
            //     'new_order' => array_column($data['sections'], 'section_key')
            // ]);

            Response::success(['message' => 'Sections reordered successfully']);
            break;

        case 'update_section':
            // Update individual section settings
            $data = $input; // Use already parsed JSON input
            if (!$data || !isset($data['section_key'])) {
                Response::error('Section key is required');
            }

            $stmt = $db->prepare('
                UPDATE dashboard_sections 
                SET width_class = ?, show_title = ?, show_description = ?, 
                    custom_title = ?, custom_description = ?
                WHERE section_key = ?
            ');

            $stmt->execute([
                $data['width_class'] ?? 'half-width',
                $data['show_title'] ?? 1,
                $data['show_description'] ?? 1,
                $data['custom_title'] ?: null,
                $data['custom_description'] ?: null,
                $data['section_key']
            ]);

            Response::success(['message' => 'Section updated successfully']);
            break;

        case 'get_available_sections':
            // Get list of available sections that can be added
            $availableSections = [
                'metrics' => [
                    'title' => '📊 Quick Metrics',
                    'description' => 'Key performance indicators and business metrics',
                    'type' => 'built-in',
                    'category' => 'Analytics'
                ],
                'recent_orders' => [
                    'title' => '📋 Recent Orders',
                    'description' => 'Latest customer orders and order status',
                    'type' => 'built-in',
                    'category' => 'Orders'
                ],
                'low_stock' => [
                    'title' => '⚠️ Low Stock Alerts',
                    'description' => 'Items running low on inventory',
                    'type' => 'built-in',
                    'category' => 'Inventory'
                ],
                'inventory_summary' => [
                    'title' => '📦 Inventory Summary',
                    'description' => 'Mini version of inventory management',
                    'type' => 'external',
                    'source' => 'inventory',
                    'category' => 'Inventory'
                ],
                'customer_summary' => [
                    'title' => '👥 Customer Overview',
                    'description' => 'Recent customers and activity',
                    'type' => 'external',
                    'source' => 'customers',
                    'category' => 'Customers'
                ],
                'marketing_tools' => [
                    'title' => '📈 Marketing Tools',
                    'description' => 'Quick access to marketing features',
                    'type' => 'external',
                    'source' => 'marketing',
                    'category' => 'Marketing'
                ],
                'order_fulfillment' => [
                    'title' => '🚚 Order Fulfillment',
                    'description' => 'Opens full order fulfillment interface in modal',
                    'type' => 'modal',
                    'source' => 'order_fulfillment',
                    'category' => 'Orders'
                ],
                'reports_summary' => [
                    'title' => '📊 Reports Summary',
                    'description' => 'Key business reports and analytics',
                    'type' => 'external',
                    'source' => 'reports',
                    'category' => 'Analytics'
                ]
            ];

            // Get currently active sections
            $activeSections = $db->query('SELECT section_key FROM dashboard_sections')->fetchAll(PDO::FETCH_COLUMN);

            // Filter out already active sections
            $available = array_filter($availableSections, function ($key) use ($activeSections) {
                return !in_array($key, $activeSections);
            }, ARRAY_FILTER_USE_KEY);

            Response::success(['available_sections' => $available]);
            break;

        default:
            Response::error('Invalid action specified');
    }

} catch (Exception $e) {
    Logger::exception($e, 'Dashboard sections API error');
    ob_clean(); // Clear any output before sending error response
    Response::serverError('Failed to process dashboard sections request: ' . $e->getMessage());
}

ob_end_flush(); // Send the buffered output
exit();
?> 
<?php
// Dashboard Sections Management API

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Disable HTML error output to keep responses strictly JSON
@ini_set('display_errors', 0);
@ini_set('html_errors', 0);
// Clear any previous output buffers to prevent mixed output
try { while (ob_get_level() > 0) { @ob_end_clean(); } } catch(Throwable $____) {}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit(0); }

// Check admin authentication using centralized helper
AuthHelper::requireAdmin();

// Ensure the dashboard_sections table exists (idempotent)
if (!function_exists('wf_ensure_dashboard_sections_table')) {
    function wf_ensure_dashboard_sections_table(PDO $db): void {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `dashboard_sections` (
  `section_key` varchar(64) NOT NULL,
  `display_order` int NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `show_title` tinyint(1) NOT NULL DEFAULT 1,
  `show_description` tinyint(1) NOT NULL DEFAULT 1,
  `custom_title` varchar(255) DEFAULT NULL,
  `custom_description` text DEFAULT NULL,
  `width_class` varchar(64) NOT NULL DEFAULT 'half-width',
  PRIMARY KEY (`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        try { $db->exec($sql); } catch (Throwable $____) { /* ignore; will fail later if unusable */ }
    }
}

try {
    // Parse JSON input for action and data
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? 'get_sections';

    switch ($action) {
        case 'diagnostics':
            // Quick check for DB connectivity and table existence
            $result = [
                'db_connect' => false,
                'table_exists' => false,
            ];
            try {
                $db = Database::getInstance();
                $result['db_connect'] = true;
                try {
                    $q = $db->query("SHOW TABLES LIKE 'dashboard_sections'");
                    $result['table_exists'] = $q && $q->fetch() ? true : false;
                } catch (Throwable $t2) {
                    $result['table_exists'] = false;
                }
            } catch (Throwable $t) {
                $result['db_connect'] = false;
            }
            Response::success(['diagnostics' => $result]);
            break;

        case 'reset_defaults':
            // Reset the dashboard configuration to sane defaults
            try {
                $db = Database::getInstance();
                wf_ensure_dashboard_sections_table($db);
                // Clear existing
                $db->beginTransaction();
                $db->query('DELETE FROM dashboard_sections');
                // Seed defaults (match dashboard defaults)
                $stmt = $db->prepare('INSERT INTO dashboard_sections (section_key, display_order, is_active, show_title, show_description, custom_title, custom_description, width_class) VALUES (?, ?, 1, 1, 1, NULL, NULL, ?)');
                $defaults = [
                    ['metrics', 1, 'half-width'],
                    ['recent_orders', 2, 'half-width'],
                    ['low_stock', 3, 'half-width'],
                ];
                foreach ($defaults as $d) { $stmt->execute([$d[0], $d[1], $d[2]]); }
                $db->commit();
                // Also reset file fallback if present
                try {
                    $store = dirname(__DIR__) . '/storage';
                    if (!is_dir($store)) { @mkdir($store, 0775, true); }
                    $file = $store . '/dashboard_sections.json';
                    $payload = array_map(function($d){ return [
                        'section_key' => $d[0],
                        'display_order' => $d[1],
                        'is_active' => 1,
                        'show_title' => 1,
                        'show_description' => 1,
                        'custom_title' => null,
                        'custom_description' => null,
                        'width_class' => $d[2],
                    ]; }, $defaults);
                    @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                } catch (Throwable $ioErr) { /* ignore */ }
                Response::success(['message' => 'Defaults restored']);
            } catch (Throwable $e) {
                try { $db && $db->rollBack(); } catch (Throwable $____) {}
                Response::serverError('Failed to reset defaults');
            }
            break;
        case 'get_sections':
            // Available section definitions (static map)
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

            // Try database; if it fails, attempt file-based fallback; otherwise return success with empty sections
            $sections = [];
            try {
                $db = Database::getInstance();
                // Get all dashboard sections with available section info
                $sections = $db->query('SELECT * FROM dashboard_sections ORDER BY display_order ASC')->fetchAll();
                // Enhance sections with available section info
                foreach ($sections as &$section) {
                    $sectionInfo = $availableSections[$section['section_key']] ?? null;
                    if ($sectionInfo) {
                        $section['section_info'] = $sectionInfo;
                        $section['display_title'] = $section['custom_title'] ?: $sectionInfo['title'];
                        $section['display_description'] = $section['custom_description'] ?: $sectionInfo['description'];
                    }
                }
            } catch (Throwable $dbErr) {
                // Fallback to file-based storage
                try {
                    $store = dirname(__DIR__) . '/storage';
                    $file = $store . '/dashboard_sections.json';
                    if (is_file($file) && is_readable($file)) {
                        $json = file_get_contents($file);
                        $saved = json_decode($json, true);
                        if (is_array($saved)) {
                            // Normalize to DB-like rows
                            $sections = array_map(function($s){
                                return [
                                    'section_key' => $s['section_key'] ?? ($s['key'] ?? ''),
                                    'display_order' => $s['display_order'] ?? 0,
                                    'is_active' => $s['is_active'] ?? 1,
                                    'show_title' => $s['show_title'] ?? 1,
                                    'show_description' => $s['show_description'] ?? 1,
                                    'custom_title' => $s['custom_title'] ?? null,
                                    'custom_description' => $s['custom_description'] ?? null,
                                    'width_class' => $s['width_class'] ?? 'half-width',
                                ];
                            }, $saved);
                        }
                    }
                } catch (Throwable $ioErr) { /* ignore; leave empty */ }
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
            $db = Database::getInstance();
            // Detect-and-create: ensure table exists before attempting writes
            wf_ensure_dashboard_sections_table($db);
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
                // DB path failed — fallback to file-based persistence
                try { $db->rollback(); } catch(Throwable $____) {}
                try {
                    $store = dirname(__DIR__) . '/storage';
                    if (!is_dir($store)) { @mkdir($store, 0775, true); }
                    $file = $store . '/dashboard_sections.json';
                    $payload = [];
                    foreach ($data['sections'] as $idx => $s) {
                        $payload[] = [
                            'section_key' => (string)($s['section_key'] ?? ''),
                            'display_order' => (int)($s['display_order'] ?? ($idx + 1)),
                            'is_active' => !empty($s['is_active']) ? 1 : 0,
                            'show_title' => !empty($s['show_title']) ? 1 : 0,
                            'show_description' => !empty($s['show_description']) ? 1 : 0,
                            'custom_title' => $s['custom_title'] ?? null,
                            'custom_description' => $s['custom_description'] ?? null,
                            'width_class' => $s['width_class'] ?? 'half-width',
                        ];
                    }
                    @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    Response::success(['message' => 'Dashboard configuration saved (file fallback)']);
                } catch (Throwable $ioErr) {
                    Response::serverError('Failed to save dashboard configuration');
                }
            }
            break;

        case 'add_section':
            // Add a new section to the dashboard
            $data = $input; // Use already parsed JSON input
            if (!$data || !isset($data['section_key'])) {
                Response::error('Section key is required');
            }

            // Get the highest display order
            $db = Database::getInstance();
            // Detect-and-create: ensure table exists before attempting writes
            wf_ensure_dashboard_sections_table($db);
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
            $db = Database::getInstance();
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
            $db = Database::getInstance();
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
            $db = Database::getInstance();
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
            $db = Database::getInstance();
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
            // Get list of available sections that can be added (no DB required)
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

            // Try to exclude active sections if DB is available; otherwise just return full list
            $available = $availableSections;
            try {
                $db = Database::getInstance();
                $activeSections = $db->query('SELECT section_key FROM dashboard_sections')->fetchAll(PDO::FETCH_COLUMN);
                if (is_array($activeSections)) {
                    $available = array_filter($availableSections, function ($key) use ($activeSections) {
                        return !in_array($key, $activeSections);
                    }, ARRAY_FILTER_USE_KEY);
                }
            } catch (Throwable $dbErr) {
                // ignore; fall back to full list
            }

            Response::success(['available_sections' => $available]);
            break;

        default:
            Response::error('Invalid action specified');
    }

} catch (Exception $e) {
    // Guard logger to avoid secondary fatals blocking JSON output
    try { if (class_exists('Logger') && method_exists('Logger', 'exception')) { Logger::exception($e, 'Dashboard sections API error'); } } catch (Throwable $____) {}
    // Ensure buffers are clear before sending JSON error
    try { while (ob_get_level() > 0) { @ob_end_clean(); } } catch(Throwable $____) {}
    Response::serverError('Failed to process dashboard sections request: ' . $e->getMessage());
}
exit();
?> 
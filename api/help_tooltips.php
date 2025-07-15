<?php

require_once __DIR__ . '/../includes/database.php';
// Help Tooltips API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Get the action first to determine if authentication is needed
$action = $_GET['action'] ?? 'get';

// Define actions that require admin authentication
$adminOnlyActions = ['update', 'create', 'delete', 'list_all', 'set_global_enabled'];

// Check if user is logged in and is admin (only for admin-only actions)
session_start();
$isAdmin = false;

// Check session authentication first (standardized pattern)
require_once __DIR__ . '/../includes/auth.php'; if (isAdminWithToken()) {
    $isAdmin = true;
}

// Admin token fallback for API access (check both GET and POST/JSON)
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$adminToken = $_GET['admin_token'] ?? $_POST['admin_token'] ?? $input['admin_token'] ?? null;
if (!$isAdmin && $adminToken === 'whimsical_admin_2024') {
    $isAdmin = true;
}

// If this is an admin-only action, check authentication
if (in_array($action, $adminOnlyActions)) {
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
}

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    switch ($action) {
        case 'get':
        case 'get_tooltips': // Alternative action name for compatibility
            // Get tooltips for a specific page or all tooltips (PUBLIC ACCESS)
            $pageContext = $_GET['page_context'] ?? $_GET['page'] ?? null;
            $elementId = $_GET['element_id'] ?? null;
            
            $sql = "SELECT * FROM help_tooltips WHERE is_active = 1";
            $params = [];
            
            if ($pageContext) {
                $sql .= " AND (page_context = ? OR page_context = 'common')";
                $params[] = $pageContext;
            }
            
            if ($elementId) {
                $sql .= " AND element_id = ?";
                $params[] = $elementId;
            }
            
            $sql .= " ORDER BY page_context, element_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tooltips = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'tooltips' => $tooltips
            ]);
            break;

        case 'get_stats':
            // Get statistics about tooltips (PUBLIC ACCESS for basic stats)
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_tooltips,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_tooltips,
                    COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_tooltips,
                    COUNT(DISTINCT page_context) as unique_pages
                FROM help_tooltips
            ");
            $stmt->execute();
            $stats = $stmt->fetch();
            
            // Check if tooltips are globally enabled
            $globalEnabled = true; // Default to enabled
            try {
                if (file_exists(__DIR__ . '/business_settings_helper.php')) {
                    require_once __DIR__ . '/business_settings_helper.php';
                    $globalEnabled = BusinessSettings::get('tooltips_enabled', true);
                } else if (file_exists(__DIR__ . '/tooltip_global_setting.txt')) {
                    // Fallback: read from simple file
                    $globalEnabled = (bool) intval(file_get_contents(__DIR__ . '/tooltip_global_setting.txt'));
                }
            } catch (Exception $e) {
                // If business settings not available, default to enabled
                $globalEnabled = true;
            }
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'global_enabled' => $globalEnabled
            ]);
            break;

        case 'list_all':
            // Get all tooltips including inactive ones for management (ADMIN ONLY)
            $stmt = $pdo->prepare("
                SELECT id, element_id, page_context, title, content, position, is_active, 
                       created_at, updated_at 
                FROM help_tooltips 
                ORDER BY page_context, element_id
            ");
            $stmt->execute();
            $tooltips = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'tooltips' => $tooltips
            ]);
            break;

        case 'get_pages':
            // Get list of unique page contexts (PUBLIC ACCESS)
            $stmt = $pdo->prepare("
                SELECT DISTINCT page_context, COUNT(*) as tooltip_count 
                FROM help_tooltips 
                WHERE is_active = 1
                GROUP BY page_context 
                ORDER BY page_context
            ");
            $stmt->execute();
            $pages = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'pages' => $pages
            ]);
            break;
            
        case 'set_global_enabled':
            // Set global tooltip enabled/disabled status (ADMIN ONLY)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $enabled = filter_var($data['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
            
            try {
                if (file_exists(__DIR__ . '/business_settings_helper.php')) {
                    require_once __DIR__ . '/business_settings_helper.php';
                    // Note: BusinessSettings class doesn't have a setSetting method, use fallback
                    file_put_contents(__DIR__ . '/tooltip_global_setting.txt', $enabled ? '1' : '0');
                } else {
                    // Fallback: store in a simple file
                    file_put_contents(__DIR__ . '/tooltip_global_setting.txt', $enabled ? '1' : '0');
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Tooltips globally ' . ($enabled ? 'enabled' : 'disabled'),
                    'enabled' => $enabled
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error updating global setting: ' . $e->getMessage()]);
            }
            break;
            
        case 'update':
            // Update a tooltip (ADMIN ONLY)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id']) || !isset($data['title']) || !isset($data['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE help_tooltips 
                SET element_id = ?, page_context = ?, title = ?, content = ?, position = ?, 
                    is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['element_id'],
                $data['page_context'],
                $data['title'],
                $data['content'],
                $data['position'] ?? 'top',
                $data['is_active'] ?? 1,
                $data['id']
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Tooltip updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update tooltip']);
            }
            break;
            
        case 'create':
            // Create a new tooltip (ADMIN ONLY)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['element_id']) || !isset($data['page_context']) || 
                !isset($data['title']) || !isset($data['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            // Check if element_id already exists for this page_context
            $checkStmt = $pdo->prepare("
                SELECT id FROM help_tooltips 
                WHERE element_id = ? AND page_context = ?
            ");
            $checkStmt->execute([$data['element_id'], $data['page_context']]);
            
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Tooltip already exists for this element on this page']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO help_tooltips (element_id, page_context, title, content, position, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['element_id'],
                $data['page_context'],
                $data['title'],
                $data['content'],
                $data['position'] ?? 'top',
                $data['is_active'] ?? 1
            ]);
            
            if ($result) {
                $newId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Tooltip created successfully', 'id' => $newId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create tooltip']);
            }
            break;
            
        case 'delete':
            // Delete a tooltip (soft delete by setting is_active = 0)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tooltip ID required']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE help_tooltips SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Tooltip deactivated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to deactivate tooltip']);
            }
            break;

        case 'hard_delete':
            // Permanently delete a tooltip
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tooltip ID required']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM help_tooltips WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Tooltip permanently deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete tooltip']);
            }
            break;
            
        case 'toggle':
            // Toggle tooltip active status
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tooltip ID required']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE help_tooltips SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Tooltip status toggled successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to toggle tooltip status']);
            }
            break;

        case 'bulk_toggle':
            // Bulk toggle tooltips for a page
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $pageContext = $data['page_context'] ?? null;
            $active = $data['active'] ?? true;
            
            if (!$pageContext) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Page context required']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE help_tooltips SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE page_context = ?");
            $result = $stmt->execute([$active ? 1 : 0, $pageContext]);
            
            if ($result) {
                $action_text = $active ? 'activated' : 'deactivated';
                echo json_encode(['success' => true, 'message' => "All tooltips for page '$pageContext' have been $action_text"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update tooltips']);
            }
            break;

        case 'export':
            // Export tooltips as JSON
            $stmt = $pdo->prepare("
                SELECT element_id, page_context, title, content, position, is_active 
                FROM help_tooltips 
                ORDER BY page_context, element_id
            ");
            $stmt->execute();
            $tooltips = $stmt->fetchAll();
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="help_tooltips_export.json"');
            echo json_encode($tooltips, JSON_PRETTY_PRINT);
            exit;
            break;

        case 'import':
            // Import tooltips from JSON
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $tooltips = $data['tooltips'] ?? [];
            
            if (empty($tooltips)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No tooltips provided']);
                exit;
            }
            
            $imported = 0;
            $skipped = 0;
            
            foreach ($tooltips as $tooltip) {
                // Check if tooltip already exists
                $checkStmt = $pdo->prepare("
                    SELECT id FROM help_tooltips 
                    WHERE element_id = ? AND page_context = ?
                ");
                $checkStmt->execute([$tooltip['element_id'], $tooltip['page_context']]);
                
                if ($checkStmt->fetch()) {
                    $skipped++;
                    continue;
                }
                
                // Insert new tooltip
                $insertStmt = $pdo->prepare("
                    INSERT INTO help_tooltips (element_id, page_context, title, content, position, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                if ($insertStmt->execute([
                    $tooltip['element_id'],
                    $tooltip['page_context'],
                    $tooltip['title'],
                    $tooltip['content'],
                    $tooltip['position'] ?? 'top',
                    $tooltip['is_active'] ?? 1
                ])) {
                    $imported++;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "Import completed: $imported imported, $skipped skipped",
                'imported' => $imported,
                'skipped' => $skipped
            ]);
            break;
            
        case 'init_comprehensive':
            // Initialize comprehensive tooltips for all admin pages
            header('Content-Type: application/json');
            echo json_encode(initializeComprehensiveTooltips($pdo));
            exit;
            break;

        case 'generate_css':
            // Generate CSS for tooltips dynamically (PUBLIC ACCESS)
            $css = generateTooltipCSS();
            
            echo json_encode([
                'success' => true,
                'css_content' => $css,
                'size' => strlen($css)
            ]);
            break;
            
        case 'generate_js':
            // Generate JavaScript for tooltips dynamically (PUBLIC ACCESS)
            $js = generateTooltipJS();
            
            echo json_encode([
                'success' => true,
                'js_content' => $js,
                'size' => strlen($js)
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
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
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * Initialize comprehensive tooltips for all admin pages
 */
function initializeComprehensiveTooltips($pdo) {
    try {
        // Admin Settings Page Tooltips
        $settingsTooltips = [
            // Content Management
            ['categoriesBtn', 'settings', 'Categories Management', 'Manage your product types like T-Shirts, Tumblers, Artwork, etc. Add new categories, edit existing ones, and organize your products so customers can find them easily.', 'bottom'],
            ['roomsBtn', 'settings', 'Room Settings', 'Change room names and descriptions. Control what customers see when they visit different rooms in your virtual store.', 'bottom'],
            ['roomCategoryBtn', 'settings', 'Room-Category Links', 'Connect product types to specific rooms. Decide which products show up in each room of your store.', 'bottom'],
            ['dashboardConfigBtn', 'settings', 'Dashboard Configuration', 'Customize what shows up on your main dashboard because apparently the default layout isn\'t good enough for your refined tastes. Rearrange widgets like you\'re playing digital Tetris with your business metrics.', 'bottom'],
            ['globalColorSizeBtn', 'settings', 'Global Colors & Sizes', 'Manage your universal color and size options because consistency is apparently too difficult to maintain manually. Create your master list of colors and sizes so you don\'t have to type "Medium" 47 times.', 'bottom'],
            
            // Room & Visual Tools
            ['roomMapperBtn', 'settings', 'Room Mapper', 'Tool to create clickable areas on room pictures. Set up spots where customers can click to see products.', 'bottom'],
            ['backgroundManagerBtn', 'settings', 'Background Manager', 'Upload and change background pictures for different rooms. Make your virtual store look how you want.', 'bottom'],
            ['areaItemMapperBtn', 'settings', 'Area-Item Mapper', 'Connect specific products to clickable spots in rooms. Make it easy for customers to find and buy products.', 'bottom'],
            
            // Business & Design
            ['aiSettingsBtn', 'settings', 'AI Settings', 'Set up AI helpers that can write product descriptions, suggest prices, and create marketing content for you.', 'bottom'],
            ['globalCSSBtn', 'settings', 'Global CSS Rules', 'Change how your website looks including colors, fonts, and spacing. Your changes show up right away across your whole site.', 'bottom'],
            ['cssRulesBtn', 'settings', 'CSS Rules', 'Another way to fiddle with your website\'s appearance because apparently one CSS interface wasn\'t enough. For when you absolutely must have two different places to change the same styling options.', 'bottom'],
            ['templateManagerBtn', 'settings', 'Template Manager', 'Manage email templates and page layouts. Control how your emails and pages look to customers.', 'bottom'],
            ['websiteConfigBtn', 'settings', 'Website Configuration', 'Set up basic website info like your business name, contact info, and how your store works.', 'bottom'],
            ['analyticsBtn', 'settings', 'Analytics & Insights', 'See detailed reports about your customers and sales. Learn what\'s working and what needs improvement.', 'bottom'],
            ['marketingAnalyticsBtn', 'settings', 'Marketing Analytics', 'Dive deep into marketing performance because regular analytics apparently don\'t give you enough numbers to obsess over. Perfect for when you need 47 different ways to measure the same customer behavior.', 'bottom'],
            ['businessReportsBtn', 'settings', 'Business Reports', 'Generate comprehensive business reports that will sit in your downloads folder forever. Create beautiful charts and graphs that make your business look more successful than it actually is.', 'bottom'],
            ['salesAdminBtn', 'settings', 'Sales Administration', 'Manage sales, discounts, and special offers. Create promotions to encourage customers to buy more.', 'bottom'],
            ['cartButtonTextBtn', 'settings', 'Cart Button Text', 'Change the words on your buy buttons. Use text that encourages customers to make purchases.', 'bottom'],
            ['squareSettingsBtn', 'settings', 'Square Settings', 'Configure your Square payment integration because modern businesses need at least 12 different ways to accept money. Set up your fancy credit card processing so customers can pay with plastic instead of actual money.', 'bottom'],
            ['receiptSettingsBtn', 'settings', 'Receipt Settings', 'Customize your digital receipts because apparently regular receipts aren\'t special enough. Choose fonts, colors, and layouts for those pieces of paper customers immediately throw away or lose.', 'bottom'],
            
            // Email & Communications
            ['emailConfigBtn', 'settings', 'Email Configuration', 'Set up your email system for sending order confirmations, newsletters, and customer messages.', 'bottom'],
            ['emailHistoryBtn', 'settings', 'Email History', 'See all emails you\'ve sent, check if they were delivered, and track how well your email campaigns worked.', 'bottom'],
            ['fixSampleEmailBtn', 'settings', 'Fix Sample Email', 'Test and fix email problems. Use this when emails aren\'t sending properly.', 'bottom'],
            
            // System & Technical
            ['systemConfigBtn', 'settings', 'System Reference', 'See technical info about your website and database. Useful for troubleshooting problems.', 'bottom'],
            ['systemDocumentationBtn', 'settings', 'System Documentation', 'A comprehensive manual for your website that you\'ll probably never read but feel good about having. It\'s like an expensive car manual that sits in your glove compartment gathering dust while you call tech support anyway.', 'bottom'],
            ['systemCleanupBtn', 'settings', 'System Cleanup', 'Clean up digital clutter that accumulates faster than dishes in a college dorm. Your website collects junk files like a hoarder collects newspapers - this button is basically Marie Kondo for your database.', 'bottom'],
            ['fileExplorerBtn', 'settings', 'File Explorer', 'Browse and manage your website files and images. Upload, organize, and maintain your digital files.', 'bottom'],
            ['databaseTablesBtn', 'settings', 'Database Tables', 'View and manage your website\'s database. See table contents and maintain your data.', 'bottom'],
            ['help-hints-btn', 'settings', 'Help Hints Management', 'Create and manage the helpful popup tips you see throughout the admin area.', 'bottom'],
            ['databaseMaintenanceBtn', 'settings', 'Database Maintenance', 'Clean up and optimize your database. Keep your website running fast and fix any data problems.', 'bottom'],
        ];
        
        // Admin Navigation Tooltips - Available on all pages
        $adminNavigationForAllPages = [
            ['adminDashboardTab', 'common', 'Dashboard', 'See your business overview with sales numbers, recent orders, and important info. This is your main control center.', 'bottom'],
            ['adminCustomersTab', 'common', 'Customers', 'Manage customer accounts, see their order history, and communicate with them. Build good relationships with your customers.', 'bottom'],
            ['adminInventoryTab', 'common', 'Inventory', 'Manage your products including prices, stock amounts, and product details. Add new products and organize your store.', 'bottom'],
            ['adminOrdersTab', 'common', 'Orders', 'Handle customer orders from start to finish. Track orders, manage shipping, process refunds, and talk to customers about their purchases.', 'bottom'],
            ['adminPosTab', 'common', 'Point of Sale', 'Your digital cash register for in-person sales. Because apparently we\'re living in the future where everything is computerized, including counting money. Perfect for when customers actually show up to buy things instead of just browsing endlessly.', 'bottom'],
            ['adminReportsTab', 'common', 'Reports', 'Create detailed business reports and charts. See sales trends, customer habits, and inventory performance to make better business decisions.', 'bottom'],
            ['adminMarketingTab', 'common', 'Marketing', 'Create marketing campaigns, send emails, make discount codes, and run promotions to attract more customers and increase sales.', 'bottom'],
            ['adminSettingsTab', 'common', 'Settings', 'Change website settings, appearance, and system options. Customize your admin experience and store setup.', 'bottom'],
        ];

        // Reports Page Tooltips
        $reportsTooltips = [
            // Key Metrics Cards
            ['totalRevenueCard', 'reports', 'Total Revenue', 'The total amount of money your business has made during the selected time period. This includes all completed sales.', 'bottom'],
            ['ordersCard', 'reports', 'Orders', 'The total number of orders customers placed during the selected time period. Each order may contain multiple items.', 'bottom'],
            ['averageOrderCard', 'reports', 'Average Order Value', 'The average amount customers spend per order. Calculated by dividing total revenue by number of orders.', 'bottom'],
            ['totalCustomersCard', 'reports', 'Total Customers', 'The total number of people who have created accounts or made purchases in your store since it opened.', 'bottom'],
            ['paymentsReceivedCard', 'reports', 'Payments Received', 'The number of orders where payment was successfully completed during the selected time period.', 'bottom'],
            ['paymentsPendingCard', 'reports', 'Payments Pending', 'The number of orders where payment is still waiting to be completed during the selected time period.', 'bottom'],
            
            // Charts and Reports
            ['salesChart', 'reports', 'Sales Over Time Chart', 'Shows how your sales change over time. The blue line shows number of orders, green line shows money earned. Higher lines mean better sales.', 'bottom'],
            ['paymentMethodChart', 'reports', 'Payment Methods Chart', 'Shows which payment methods customers use most. Each colored section represents a different payment type like credit cards or PayPal.', 'bottom'],
            ['topProductsTable', 'reports', 'Top Selling Products', 'Lists your best-selling products ranked by how much money they made. Shows quantity sold and total revenue for each product.', 'bottom'],
            ['inventoryAlertsTable', 'reports', 'Inventory Alerts', 'Shows products that are running low on stock. Red means out of stock, yellow means low stock. Time to reorder these items.', 'bottom'],
            ['printReportBtn', 'reports', 'Print Report', 'Create a printable version of this report. Great for keeping paper records or sharing with others who need to see the data.', 'bottom'],
        ];

        // Marketing Page Tooltips  
        $marketingTooltips = [
            // Stats Cards
            ['marketingTotalCustomers', 'marketing', 'Total Customers', 'The total number of people who have bought from your store. More customers means more potential for repeat sales.', 'bottom'],
            ['marketingTotalOrders', 'marketing', 'Total Orders', 'The total number of orders placed during the selected time period. Shows how busy your store has been.', 'bottom'],
            ['marketingTotalSales', 'marketing', 'Total Sales', 'The total amount of money made during the selected time period. This is your store\'s income for the dates you selected.', 'bottom'],
            ['marketingProductsSold', 'marketing', 'Products Sold', 'The total number of individual items sold during the selected time period. Different from orders because one order can have many items.', 'bottom'],
            ['marketingPaymentsReceived', 'marketing', 'Payments Received', 'The number of orders where customers successfully paid during the selected time period.', 'bottom'],
            ['marketingPaymentsPending', 'marketing', 'Payments Pending', 'The number of orders where payment is still waiting to be completed. Follow up on these to get paid.', 'bottom'],
            
            // Charts and Lists
            ['marketingSalesChart', 'marketing', 'Sales Overview Chart', 'Shows your monthly sales over time. Higher points mean better sales months. Helps you see trends and plan for busy seasons.', 'bottom'],
            ['marketingPaymentChart', 'marketing', 'Payment Methods Chart', 'Shows which payment methods customers prefer. Each colored section represents a different way customers pay you.', 'bottom'],
            ['marketingTopItemsList', 'marketing', 'Top Items List', 'Shows your best-selling products ranked by how many units sold. Focus your marketing efforts on these popular items.', 'bottom'],
            ['marketingRecentOrdersTable', 'marketing', 'Recent Orders Table', 'Shows the most recent orders with customer info and amounts. Helps you see current activity and follow up if needed.', 'bottom'],
            
            // Marketing Tools
            ['emailCampaignsCard', 'marketing', 'Email Campaigns', 'Create and send marketing emails to your customers. Build email lists, design newsletters, and track who opens your emails.', 'bottom'],
            ['discountCodesCard', 'marketing', 'Discount Codes', 'Create special coupon codes for customers. Set percentage or dollar amount discounts to encourage sales and reward loyal customers.', 'bottom'],
            ['socialMediaCard', 'marketing', 'Social Media', 'Manage your social media posts and accounts. Schedule posts, track engagement, and connect with customers on social platforms.', 'bottom'],
            ['analyticsCard', 'marketing', 'Analytics', 'View detailed reports about your website visitors and customer behavior. See which pages are popular and how customers use your site.', 'bottom'],
        ];

        // Analytics Dashboard Tooltips (from settings page)
        $analyticsTooltips = [
            ['totalSessions', 'settings', 'Total Sessions', 'The number of times people visited your website during the selected time period. Each visit counts as one session.', 'bottom'],
            ['conversionRate', 'settings', 'Conversion Rate', 'The percentage of website visitors who actually bought something. Higher is better - it means your site turns visitors into customers.', 'bottom'],
            ['avgSessionDuration', 'settings', 'Average Session Duration', 'How long people typically spend on your website per visit. Longer times usually mean they\'re more interested in your products.', 'bottom'],
            ['bounceRate', 'settings', 'Bounce Rate', 'The percentage of visitors who leave your site immediately after viewing only one page. Lower is better - you want people to explore.', 'bottom'],
            ['conversionFunnel', 'settings', 'Conversion Funnel', 'Shows the step-by-step process of how visitors become customers. Each step shows how many people continue vs. leave.', 'bottom'],
            ['topPages', 'settings', 'Top Pages', 'Lists the most popular pages on your website. Shows which content attracts the most visitors and keeps them engaged.', 'bottom'],
            ['deviceAnalytics', 'settings', 'Device & Browser Analytics', 'Shows what devices (phone, computer, tablet) and browsers people use to visit your site. Helps you optimize for popular devices.', 'bottom'],
            ['userFlow', 'settings', 'User Flow Analysis', 'Shows the path visitors take through your website. Helps you understand how people navigate and where they get stuck.', 'bottom'],
            ['productPerformance', 'settings', 'Product Performance', 'Shows which products get the most views, add-to-carts, and purchases. Helps you identify your most and least popular items.', 'bottom'],
        ];

        // Admin Inventory Page Tooltips
        $inventoryTooltips = [
            // Main actions
            ['addNewItemBtn', 'inventory', 'Add New Item', 'Create a new product. Add pictures, descriptions, prices, and organize it so customers can find it.', 'bottom'],
            ['bulkEditBtn', 'inventory', 'Bulk Edit', 'Change many products at once. Great for updating prices or stock levels across lots of products.', 'bottom'],
            ['exportInventoryBtn', 'inventory', 'Export Inventory', 'Download all your product info as a spreadsheet. Good for backup or sharing with others.', 'bottom'],
            ['importInventoryBtn', 'inventory', 'Import Inventory', 'Upload a spreadsheet to quickly add or update many products at once.', 'bottom'],
            
            // Item management
            ['editItemBtn', 'inventory', 'Edit Item', 'Change product details like name, description, price, and pictures. Your changes save right away.', 'top'],
            ['deleteItemBtn', 'inventory', 'Delete Item', 'Remove this product permanently. Be careful - you can\'t undo this and it affects existing orders.', 'top'],
            ['duplicateItemBtn', 'inventory', 'Duplicate Item', 'Make a copy of this product. Useful for creating similar products with small changes.', 'top'],
            ['viewItemBtn', 'inventory', 'View Item', 'See how this product looks to customers on your website.', 'top'],
            
            // Stock management
            ['stockLevelInput', 'inventory', 'Stock Level', 'How many you have to sell. When this hits zero, customers see "out of stock".', 'top'],
            ['reorderPointInput', 'inventory', 'Reorder Point', 'When stock gets this low, you\'ll get an alert to order more. Helps prevent running out.', 'top'],
            ['costPriceInput', 'inventory', 'Cost Price', 'What you paid for this item. Used to calculate your profit.', 'top'],
            ['retailPriceInput', 'inventory', 'Retail Price', 'What customers pay for this item. This is the price shown on your website.', 'top'],
            
            // Categories and organization
            ['categorySelect', 'inventory', 'Product Category', 'Group products by type so customers can find them easier.', 'top'],
            ['tagsInput', 'inventory', 'Product Tags', 'Add keywords to help customers find this product when they search.', 'top'],
            
            // AI and automation
            ['aiSuggestPriceBtn', 'inventory', 'AI Price Suggestion', 'Get smart pricing ideas based on market research and your costs.', 'bottom'],
            ['aiSuggestCostBtn', 'inventory', 'AI Cost Analysis', 'Break down your costs including materials, labor, and shipping to help set better prices.', 'bottom'],
            ['aiMarketingBtn', 'inventory', 'AI Marketing Content', 'Create product descriptions and marketing text using AI.', 'bottom'],
        ];
        
        // Admin Orders Page Tooltips
        $ordersTooltips = [
            // Order management
            ['newOrderBtn', 'orders', 'Create New Order', 'Make a new order for phone or in-person sales. Add products, customer info, and payment details.', 'bottom'],
            ['exportOrdersBtn', 'orders', 'Export Orders', 'Download order info as a spreadsheet for bookkeeping or analysis. Pick date ranges and file types.', 'bottom'],
            ['printPackingSlipsBtn', 'orders', 'Print Packing Slips', 'Print shipping slips that show what to pack. Includes all items and shipping info.', 'bottom'],
            ['orderSearchInput', 'orders', 'Search Orders', 'Find orders by order number, customer name, email, or product. Type keywords to find specific orders quickly.', 'top'],
            
            // Order actions
            ['viewOrderBtn', 'orders', 'View Order Details', 'See all order info including items, customer details, payment status, and shipping info.', 'top'],
            ['editOrderBtn', 'orders', 'Edit Order', 'Change order details like shipping address or add/remove items before shipping.', 'top'],
            ['fulfillOrderBtn', 'orders', 'Fulfill Order', 'Mark order as shipped and add tracking number. Customer gets notified automatically.', 'top'],
            ['cancelOrderBtn', 'orders', 'Cancel Order', 'Cancel this order and give refunds if needed. Customer will be told about the cancellation.', 'top'],
            ['refundOrderBtn', 'orders', 'Process Refund', 'Give back money for this order. Choose full or partial refund and explain why.', 'top'],
            
            // Status management
            ['orderStatusSelect', 'orders', 'Order Status', 'Track order progress from new to completed. Customers can see these updates in their account.', 'top'],
            ['paymentStatusSelect', 'orders', 'Payment Status', 'Track payment progress. Update when payments come in or need follow-up.', 'top'],
            ['shippingStatusSelect', 'orders', 'Shipping Status', 'Track shipping from packing to delivery. Customers get automatic updates.', 'top'],
        ];
        
        // Admin Customers Page Tooltips
        $customersTooltips = [
            // Customer management
            ['addCustomerBtn', 'customers', 'Add New Customer', 'Create a customer account by hand. Good for phone orders or adding existing customers.', 'bottom'],
            ['exportCustomersBtn', 'customers', 'Export Customers', 'Download customer info as a spreadsheet for marketing or backup. Includes contact info and order history.', 'bottom'],
            ['customerSearchInput', 'customers', 'Search Customers', 'Find customers by name, email, phone, or order history. Type keywords to find specific customers quickly.', 'top'],
            
            // Customer actions
            ['viewCustomerBtn', 'customers', 'View Customer Profile', 'See all customer info including order history, contact details, and account status.', 'top'],
            ['editCustomerBtn', 'customers', 'Edit Customer', 'Change customer info like contact details, addresses, or account settings.', 'top'],
            ['deleteCustomerBtn', 'customers', 'Delete Customer', 'Remove customer account and data. Be careful - this affects order history and can\'t be undone.', 'top'],
            ['emailCustomerBtn', 'customers', 'Email Customer', 'Send an email directly to this customer. Pick from templates or write a custom message.', 'top'],
            
            // Customer analysis
            ['customerOrdersBtn', 'customers', 'View Customer Orders', 'See all orders from this customer including dates, amounts, and status.', 'top'],
            ['customerValueBtn', 'customers', 'Customer Lifetime Value', 'See how much this customer has spent total, their average order amount, and buying habits.', 'top'],
            
            // Password management
            ['newPassword', 'customers', 'New Password', 'Oh look, someone wants to change a customer\'s password! Enter the new password here. Must be at least 6 characters because apparently that\'s considered "secure" these days. Leave blank to keep their current password - revolutionary concept!', 'top'],
            ['confirmPassword', 'customers', 'Confirm Password', 'Type the same password again because we don\'t trust you to type it correctly the first time. This is that annoying but necessary step that prevents you from accidentally locking customers out of their accounts.', 'top'],
        ];
        
        // Common form tooltips
        $commonTooltips = [
            ['saveBtn', 'common', 'Save Changes', 'Save your current settings. Changes happen right away and customers will see them.', 'top'],
            ['cancelBtn', 'common', 'Cancel', 'Throw away any changes and go back. Nothing will be saved.', 'top'],
            ['resetBtn', 'common', 'Reset to Default', 'Go back to the original settings. Be careful - this will erase your current setup.', 'top'],
            ['previewBtn', 'common', 'Preview Changes', 'See how your changes will look before saving. Opens a preview in a new window.', 'top'],
            ['deleteBtn', 'common', 'Delete', 'Remove this item forever. You can\'t undo this. Make sure you have backups if needed.', 'top'],
            ['duplicateBtn', 'common', 'Duplicate', 'Make a copy of this item with all settings. Good for creating similar things.', 'top'],
            ['exportBtn', 'common', 'Export Data', 'Download data as a spreadsheet for backup or sharing with others.', 'top'],
            ['importBtn', 'common', 'Import Data', 'Upload a file to quickly add or update many items at once.', 'top'],
        ];
        
        // Combine all tooltips
        $allTooltips = array_merge($settingsTooltips, $adminNavigationForAllPages, $reportsTooltips, $marketingTooltips, $analyticsTooltips, $inventoryTooltips, $ordersTooltips, $customersTooltips, $commonTooltips);
        
        // Insert tooltips into database
        $stmt = $pdo->prepare("INSERT INTO help_tooltips (element_id, page_context, title, content, position, is_active) VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), position = VALUES(position), updated_at = CURRENT_TIMESTAMP");
        
        $insertedCount = 0;
        foreach ($allTooltips as $tooltip) {
            if ($stmt->execute($tooltip)) {
                $insertedCount++;
            }
        }
        
        return [
            'success' => true,
            'message' => "Initialized $insertedCount comprehensive tooltips for all admin pages",
            'total_tooltips' => count($allTooltips)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Failed to initialize comprehensive tooltips: ' . $e->getMessage()
        ];
    }
}

/**
 * Generate CSS for tooltips dynamically from database settings
 */
function generateTooltipCSS() {
    $css = "/* Help Tooltips CSS - Generated from Database */\n\n";
    
    // Base tooltip styling
    $css .= ".help-tooltip {\n";
    $css .= "    position: fixed !important;\n";
    $css .= "    background: var(-color_gray_800, #333333) !important;\n";
    $css .= "    color: var(-color_white, #ffffff) !important;\n";
    $css .= "    padding: 12px 16px !important;\n";
    $css .= "    border-radius: 8px !important;\n";
    $css .= "    font-size: 14px !important;\n";
    $css .= "    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;\n";
    $css .= "    line-height: 1.4 !important;\n";
    $css .= "    max-width: 320px !important;\n";
    $css .= "    word-wrap: break-word !important;\n";
    $css .= "    white-space: normal !important;\n";
    $css .= "    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;\n";
    $css .= "    border: 1px solid var(-color_gray_600, #555555) !important;\n";
    $css .= "    z-index: 99999 !important;\n";
    $css .= "    opacity: 0 !important;\n";
    $css .= "    visibility: hidden !important;\n";
    $css .= "    display: block !important;\n";
    $css .= "    transition: opacity 0.2s ease-in-out, visibility 0.2s ease-in-out !important;\n";
    $css .= "    pointer-events: none !important;\n";
    $css .= "    box-sizing: border-box !important;\n";
    $css .= "    text-align: left !important;\n";
    $css .= "    font-weight: normal !important;\n";
    $css .= "    text-decoration: none !important;\n";
    $css .= "    text-transform: none !important;\n";
    $css .= "    letter-spacing: normal !important;\n";
    $css .= "}\n\n";
    
    // Visible state
    $css .= ".help-tooltip.show {\n";
    $css .= "    opacity: 1 !important;\n";
    $css .= "    visibility: visible !important;\n";
    $css .= "    display: block !important;\n";
    $css .= "    pointer-events: auto !important;\n";
    $css .= "}\n\n";
    
    // Tooltip content structure
    $css .= ".tooltip-title {\n";
    $css .= "    font-weight: 600 !important;\n";
    $css .= "    margin-bottom: 6px !important;\n";
    $css .= "    color: #ffffff !important;\n";
    $css .= "    font-size: 15px !important;\n";
    $css .= "}\n\n";
    
    $css .= ".tooltip-content {\n";
    $css .= "    color: #e5e5e5 !important;\n";
    $css .= "    font-size: 13px !important;\n";
    $css .= "    line-height: 1.5 !important;\n";
    $css .= "}\n\n";
    
    // Arrow positioning
    $css .= ".help-tooltip::before {\n";
    $css .= "    content: '' !important;\n";
    $css .= "    position: absolute !important;\n";
    $css .= "    width: 0 !important;\n";
    $css .= "    height: 0 !important;\n";
    $css .= "    border: 6px solid transparent !important;\n";
    $css .= "}\n\n";
    
    // Arrow directions
    $css .= ".tooltip-top::before {\n";
    $css .= "    bottom: 100% !important;\n";
    $css .= "    left: 50% !important;\n";
    $css .= "    transform: translateX(-50%) !important;\n";
    $css .= "    border-bottom-color: #333333 !important;\n";
    $css .= "    border-top: none !important;\n";
    $css .= "}\n\n";
    
    $css .= ".tooltip-bottom::before {\n";
    $css .= "    top: 100% !important;\n";
    $css .= "    left: 50% !important;\n";
    $css .= "    transform: translateX(-50%) !important;\n";
    $css .= "    border-top-color: #333333 !important;\n";
    $css .= "    border-bottom: none !important;\n";
    $css .= "}\n\n";
    
    $css .= ".tooltip-left::before {\n";
    $css .= "    right: 100% !important;\n";
    $css .= "    top: 50% !important;\n";
    $css .= "    transform: translateY(-50%) !important;\n";
    $css .= "    border-right-color: #333333 !important;\n";
    $css .= "    border-left: none !important;\n";
    $css .= "}\n\n";
    
    $css .= ".tooltip-right::before {\n";
    $css .= "    left: 100% !important;\n";
    $css .= "    top: 50% !important;\n";
    $css .= "    transform: translateY(-50%) !important;\n";
    $css .= "    border-left-color: #333333 !important;\n";
    $css .= "    border-right: none !important;\n";
    $css .= "}\n\n";
    
    // Responsive design
    $css .= "@media (max-width: 768px) {\n";
    $css .= "    .help-tooltip {\n";
    $css .= "        max-width: 280px !important;\n";
    $css .= "        font-size: 13px !important;\n";
    $css .= "        padding: 10px 14px !important;\n";
    $css .= "    }\n";
    $css .= "    .tooltip-title { font-size: 14px !important; }\n";
    $css .= "    .tooltip-content { font-size: 12px !important; }\n";
    $css .= "}\n\n";
    
    // Accessibility
    $css .= "@media (prefers-reduced-motion: reduce) {\n";
    $css .= "    .help-tooltip { transition: none !important; }\n";
    $css .= "}\n\n";
    
    $css .= "@media (prefers-contrast: high) {\n";
    $css .= "    .help-tooltip {\n";
    $css .= "        background: #000000 !important;\n";
    $css .= "        color: #ffffff !important;\n";
    $css .= "        border: 2px solid #ffffff !important;\n";
    $css .= "    }\n";
    $css .= "}\n\n";
    
    return $css;
}

/**
 * Generate JavaScript for tooltips dynamically from database
 */
function generateTooltipJS() {
    try {
        $pdo = Database::getInstance();
        
        // Get all active tooltips from database
        $stmt = $pdo->prepare("SELECT element_id, page_context, title, content, position FROM help_tooltips WHERE is_active = 1");
        $stmt->execute();
        $tooltips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $js = "/* Help Tooltips JS - Generated from Database */\n\n";
        
        $js .= "class TooltipSystem {\n";
        $js .= "    constructor() {\n";
        $js .= "        this.tooltips = new Map();\n";
        $js .= "        this.activeTooltip = null;\n";
        $js .= "        this.showDelay = 500;\n";
        $js .= "        this.hideDelay = 100;\n";
        $js .= "        this.showTimeout = null;\n";
        $js .= "        this.hideTimeout = null;\n";
        $js .= "        this.isInitialized = false;\n";
        $js .= "        this.init();\n";
        $js .= "    }\n\n";
        
        $js .= "    async init() {\n";
        $js .= "        try {\n";
        $js .= "            this.loadTooltipsFromData();\n";
        $js .= "            if (document.readyState === 'loading') {\n";
        $js .= "                document.addEventListener('DOMContentLoaded', () => this.attachTooltips());\n";
        $js .= "            } else {\n";
        $js .= "                this.attachTooltips();\n";
        $js .= "            }\n";
        $js .= "            this.isInitialized = true;\n";
        $js .= "        } catch (error) {\n";
        $js .= "            console.error('TooltipSystem initialization failed:', error);\n";
        $js .= "        }\n";
        $js .= "    }\n\n";
        
        $js .= "    loadTooltipsFromData() {\n";
        $js .= "        const tooltipData = " . json_encode($tooltips) . ";\n";
        $js .= "        const pageContext = this.getPageContext();\n";
        $js .= "        tooltipData.forEach(tooltip => {\n";
        $js .= "            if (tooltip.page_context === pageContext || tooltip.page_context === 'common') {\n";
        $js .= "                this.tooltips.set(tooltip.element_id, {\n";
        $js .= "                    title: tooltip.title,\n";
        $js .= "                    content: tooltip.content,\n";
        $js .= "                    position: tooltip.position || 'top'\n";
        $js .= "                });\n";
        $js .= "            }\n";
        $js .= "        });\n";
        $js .= "    }\n\n";
        
        $js .= "    getPageContext() {\n";
        $js .= "        const params = new URLSearchParams(window.location.search);\n";
        $js .= "        const page = params.get('page') || 'home';\n";
        $js .= "        if (page.startsWith('admin')) {\n";
        $js .= "            const section = params.get('section');\n";
        $js .= "            return section || page.replace('admin_', '') || 'admin';\n";
        $js .= "        }\n";
        $js .= "        return page;\n";
        $js .= "    }\n\n";
        
        $js .= "    attachTooltips() {\n";
        $js .= "        this.tooltips.forEach((tooltipData, elementId) => {\n";
        $js .= "            const element = document.getElementById(elementId);\n";
        $js .= "            if (element) {\n";
        $js .= "                this.attachTooltipToElement(element, tooltipData);\n";
        $js .= "            }\n";
        $js .= "        });\n";
        $js .= "    }\n\n";
        
        $js .= "    attachTooltipToElement(element, tooltipData) {\n";
        $js .= "        element.addEventListener('mouseenter', () => {\n";
        $js .= "            this.clearTimeouts();\n";
        $js .= "            this.showTimeout = setTimeout(() => {\n";
        $js .= "                this.showTooltip(element, tooltipData);\n";
        $js .= "            }, this.showDelay);\n";
        $js .= "        });\n\n";
        
        $js .= "        element.addEventListener('mouseleave', () => {\n";
        $js .= "            this.clearTimeouts();\n";
        $js .= "            this.hideTimeout = setTimeout(() => {\n";
        $js .= "                this.hideTooltip();\n";
        $js .= "            }, this.hideDelay);\n";
        $js .= "        });\n";
        $js .= "    }\n\n";
        
        $js .= "    showTooltip(element, tooltipData) {\n";
        $js .= "        this.hideTooltip();\n";
        $js .= "        const tooltip = this.createTooltipElement(tooltipData);\n";
        $js .= "        document.body.appendChild(tooltip);\n";
        $js .= "        this.positionTooltip(tooltip, element, tooltipData.position);\n";
        $js .= "        tooltip.classList.add('show');\n";
        $js .= "        this.activeTooltip = tooltip;\n";
        $js .= "    }\n\n";
        
        $js .= "    hideTooltip() {\n";
        $js .= "        if (this.activeTooltip) {\n";
        $js .= "            this.activeTooltip.remove();\n";
        $js .= "            this.activeTooltip = null;\n";
        $js .= "        }\n";
        $js .= "    }\n\n";
        
        $js .= "    createTooltipElement(tooltipData) {\n";
        $js .= "        const tooltip = document.createElement('div');\n";
        $js .= "        tooltip.className = `help-tooltip tooltip-\${tooltipData.position}`;\n";
        $js .= "        const title = document.createElement('div');\n";
        $js .= "        title.className = 'tooltip-title';\n";
        $js .= "        title.textContent = tooltipData.title;\n";
        $js .= "        const content = document.createElement('div');\n";
        $js .= "        content.className = 'tooltip-content';\n";
        $js .= "        content.textContent = tooltipData.content;\n";
        $js .= "        tooltip.appendChild(title);\n";
        $js .= "        tooltip.appendChild(content);\n";
        $js .= "        return tooltip;\n";
        $js .= "    }\n\n";
        
        $js .= "    positionTooltip(tooltip, element, position = 'top') {\n";
        $js .= "        const elementRect = element.getBoundingClientRect();\n";
        $js .= "        const tooltipRect = tooltip.getBoundingClientRect();\n";
        $js .= "        const viewport = { width: window.innerWidth, height: window.innerHeight };\n";
        $js .= "        let left, top;\n";
        $js .= "        switch (position) {\n";
        $js .= "            case 'top':\n";
        $js .= "                left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);\n";
        $js .= "                top = elementRect.top - tooltipRect.height - 10;\n";
        $js .= "                break;\n";
        $js .= "            case 'bottom':\n";
        $js .= "                left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);\n";
        $js .= "                top = elementRect.bottom + 10;\n";
        $js .= "                break;\n";
        $js .= "            case 'left':\n";
        $js .= "                left = elementRect.left - tooltipRect.width - 10;\n";
        $js .= "                top = elementRect.top + (elementRect.height / 2) - (tooltipRect.height / 2);\n";
        $js .= "                break;\n";
        $js .= "            case 'right':\n";
        $js .= "                left = elementRect.right + 10;\n";
        $js .= "                top = elementRect.top + (elementRect.height / 2) - (tooltipRect.height / 2);\n";
        $js .= "                break;\n";
        $js .= "            default:\n";
        $js .= "                left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);\n";
        $js .= "                top = elementRect.top - tooltipRect.height - 10;\n";
        $js .= "        }\n";
        $js .= "        left = Math.max(10, Math.min(left, viewport.width - tooltipRect.width - 10));\n";
        $js .= "        top = Math.max(10, Math.min(top, viewport.height - tooltipRect.height - 10));\n";
        $js .= "        tooltip.style.left = `\${left}px`;\n";
        $js .= "        tooltip.style.top = `\${top}px`;\n";
        $js .= "    }\n\n";
        
        $js .= "    clearTimeouts() {\n";
        $js .= "        if (this.showTimeout) {\n";
        $js .= "            clearTimeout(this.showTimeout);\n";
        $js .= "            this.showTimeout = null;\n";
        $js .= "        }\n";
        $js .= "        if (this.hideTimeout) {\n";
        $js .= "            clearTimeout(this.hideTimeout);\n";
        $js .= "            this.hideTimeout = null;\n";
        $js .= "        }\n";
        $js .= "    }\n";
        $js .= "}\n\n";
        
        $js .= "// Initialize global tooltip system\n";
        $js .= "let globalTooltipSystem = null;\n\n";
        
        $js .= "function initializeTooltipSystem() {\n";
        $js .= "    if (!globalTooltipSystem) {\n";
        $js .= "        globalTooltipSystem = new TooltipSystem();\n";
        $js .= "        window.tooltipSystem = globalTooltipSystem;\n";
        $js .= "    }\n";
        $js .= "}\n\n";
        
        $js .= "// Auto-initialize\n";
        $js .= "if (document.readyState === 'loading') {\n";
        $js .= "    document.addEventListener('DOMContentLoaded', initializeTooltipSystem);\n";
        $js .= "} else {\n";
        $js .= "    initializeTooltipSystem();\n";
        $js .= "}\n\n";
        
        $js .= "// Legacy compatibility\n";
        $js .= "function loadTooltips() {\n";
        $js .= "    if (globalTooltipSystem) {\n";
        $js .= "        return Promise.resolve();\n";
        $js .= "    }\n";
        $js .= "}\n";
        
        return $js;
        
    } catch (Exception $e) {
        return "/* Error generating tooltip JS: " . $e->getMessage() . " */";
    }
}
?> 
<?php
// Admin Dashboard - Main administrative interface
require_once __DIR__ . '/../includes/functions.php';
// Seed DB connection globals ($host,$db,$user,$pass,...) for section files that use Database::getInstance()
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include dirname(__DIR__) . '/partials/header.php';
    if (!function_exists('__wf_admin_root_footer_shutdown')) {
        function __wf_admin_root_footer_shutdown() {
            @include __DIR__ . '/../partials/footer.php';
        }
    }
    register_shutdown_function('__wf_admin_root_footer_shutdown');
}

// Get current user data (lightweight; avoid loading section data up-front)
if (!function_exists('getCurrentUser') && class_exists('AuthHelper')) {
    $userData = AuthHelper::getCurrentUser() ?? [];
} else {
    $userData = getCurrentUser() ?? [];
}
$adminName = trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? ''));
if (empty($adminName)) { $adminName = $userData['username'] ?? 'Admin'; }
$adminRole = $userData['role'] ?? 'Administrator';
?>

<div class="admin-dashboard page-content">



    <?php
    // Derive admin section from query when loading this router directly
    if (!isset($adminSection) || $adminSection === '' || $adminSection === null) {
        $raw = isset($_GET['section']) ? strtolower((string)$_GET['section']) : '';
        $aliases = [
            'index' => 'dashboard', 'home' => 'dashboard',
            'order' => 'orders',
            'product' => 'inventory', 'products' => 'inventory',
            'customer' => 'customers', 'user' => 'customers', 'users' => 'customers',
            'report' => 'reports',
            'setting' => 'settings', 'admin_settings' => 'settings', 'admin_settings.php' => 'settings',
        ];
        if (isset($aliases[$raw])) { $raw = $aliases[$raw]; }
        $adminSection = $raw;
    }
    // Default to dashboard when section missing or unknown
    $currentSection = $adminSection ?: 'dashboard';
    ?>

    <!-- Dynamic Section Content -->
    <div id="admin-section-content">
        <?php
        switch ($currentSection) {
            case 'customers':
                include 'admin_customers.php';
                break;
            case 'inventory':
            case 'admin_inventory':
                include 'admin_inventory.php';
                break;
            case 'orders':
                include 'admin_orders.php';
                break;
            case 'pos':
                include 'admin_pos.php';
                break;
            case 'reports':
                include 'admin_reports.php';
                break;
            case 'marketing':
                include 'admin_marketing.php';
                break;
            case 'settings':
                include 'admin_settings.php';
                break;
            case 'secrets':
                include 'admin_secrets.php';
                break;
            case 'categories':
                include 'admin_categories.php';
                break;
            case 'dashboard':
            default:
                // Load heavy data only for the dashboard
                $inventoryData = [];
                $ordersData = [];
                $customersData = [];
                try {
                    $inventoryData = Database::queryAll('SELECT * FROM items ORDER BY sku');
                    $ordersData = Database::queryAll('SELECT * FROM orders ORDER BY date DESC');
                    foreach ($ordersData as &$order) {
                        $order['items'] = Database::queryAll('SELECT * FROM order_items WHERE orderId = ?', [$order['id']]);
                        if (isset($order['shippingAddress']) && is_string($order['shippingAddress'])) {
                            $order['shippingAddress'] = json_decode($order['shippingAddress'], true);
                        }
                    }
                    $customersData = Database::queryAll('SELECT * FROM users ORDER BY firstName, lastName');
                } catch (Exception $e) {
                    Logger::error('Admin dashboard data loading failed', ['error' => $e->getMessage()]);
                }
                // Calculate dashboard metrics
                $totalProducts = count($inventoryData);
                $totalOrders = count($ordersData);
                $totalCustomers = count($customersData);
                $totalRevenue = array_sum(array_column($ordersData, 'total'));
                $formattedRevenue = '$' . number_format($totalRevenue, 2);
                include 'admin_dashboard.php';
                break;
        }
?>
    </div>
</div>

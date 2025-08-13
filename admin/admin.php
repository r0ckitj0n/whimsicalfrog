<?php
// Admin Dashboard - Main administrative interface
require_once __DIR__ . '/../includes/functions.php';

// Get current user data
$userData = getCurrentUser() ?? [];

// Initialize data arrays
$inventoryData = [];
$ordersData = [];
$customersData = [];

try {
    // Fetch core data with optimized queries
    $inventoryData = Database::queryAll('SELECT * FROM items ORDER BY sku');
    $ordersData = Database::queryAll('SELECT * FROM orders ORDER BY date DESC');

    // Enhanced orders with items and formatted shipping
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

// Admin user info
$adminName = trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? ''));
if (empty($adminName)) {
    $adminName = $userData['username'] ?? 'Admin';
}
$adminRole = $userData['role'] ?? 'Administrator';
?>

<div class="admin-dashboard page-content">



    <!-- Navigation Tabs -->
    <?php
    // The $adminSection variable is now passed from index.php
    // Default to 'dashboard' if it's empty or not set.
    $currentSection = $adminSection ?: 'dashboard';

$tabs = [
    'dashboard' => ['Dashboard', 'admin-tab-dashboard'],
    'customers' => ['Customers', 'admin-tab-customers'],
    'inventory' => ['Inventory', 'admin-tab-inventory'],
    'orders' => ['Orders', 'admin-tab-orders'],
    'pos' => ['POS', 'admin-tab-pos'],
    'reports' => ['Reports', 'admin-tab-reports'],
    'marketing' => ['Marketing', 'admin-tab-marketing'],
    'settings' => ['Settings', 'admin-tab-settings'],
];
?>
    <?php if ($section !== 'pos'): ?>
    <div class="admin-tab-navigation">
        <div class="u-display-flex u-flex-wrap-wrap u-gap-2">
            <?php foreach ($tabs as $key => [$label, $cssClass]): ?>
                <?php
            // Map tab keys to tooltip IDs
            $tooltipIds = [
                'dashboard' => 'adminDashboardTab',
                'customers' => 'adminCustomersTab',
                'inventory' => 'adminInventoryTab',
                'orders' => 'adminOrdersTab',
                'pos' => 'adminPosTab',
                'reports' => 'adminReportsTab',
                'marketing' => 'adminMarketingTab',
                'settings' => 'adminSettingsTab'
            ];
                $tooltipId = $tooltipIds[$key] ?? '';
                $url = ($key === 'dashboard') ? '/admin' : "/admin/{$key}";
                ?>
                <a href="<?= $url ?>"
                   id="<?= $tooltipId ?>"
                   class="admin-nav-tab <?= $cssClass ?> <?= ($currentSection === $key) ? 'active' : '' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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
            case 'categories':
                include 'admin_categories.php';
                break;
            case 'dashboard':
            default:
                include 'admin_dashboard.php';
                break;
        }
?>
    </div>
</div>

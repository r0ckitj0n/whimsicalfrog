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

<div class="admin-dashboard">
<<<<<<< HEAD
    <!-- Admin Header -->
    <div class="admin-header-card mb-4">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div>
                <h1 class="admin-title">Admin Dashboard</h1>
                <p class="admin-subtitle">Welcome back, <?= htmlspecialchars($adminName) ?> (<?= htmlspecialchars($adminRole) ?>)</p>
            </div>
            <div class="mt-2 md:mt-0">
                <p class="admin-meta">Last login: <?= date('F j, Y, g:i a') ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
=======



    <!- Navigation Tabs ->
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    <?php
    $section = $_GET['section'] ?? '';
    $tabs = [
        '' => ['Dashboard', 'admin-tab-dashboard'],
        'customers' => ['Customers', 'admin-tab-customers'],
        'inventory' => ['Inventory', 'admin-tab-inventory'],
        'orders' => ['Orders', 'admin-tab-orders'],
        'pos' => ['POS', 'admin-tab-pos'],
        'reports' => ['Reports', 'admin-tab-reports'],
        'marketing' => ['Marketing', 'admin-tab-marketing'],
        'settings' => ['Settings', 'admin-tab-settings'],
    ];
    ?>
<<<<<<< HEAD
    <div class="admin-tab-navigation mb-1">
=======
    <?php if ($section !== 'pos'): ?>
    <div class="admin-tab-navigation">
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
        <div class="flex flex-wrap gap-2">
            <?php foreach ($tabs as $key => [$label, $cssClass]): ?>
                <?php 
                // Map tab keys to tooltip IDs
                $tooltipIds = [
                    '' => 'adminDashboardTab',
                    'customers' => 'adminCustomersTab', 
                    'inventory' => 'adminInventoryTab',
                    'orders' => 'adminOrdersTab',
                    'pos' => 'adminPosTab',
                    'reports' => 'adminReportsTab',
                    'marketing' => 'adminMarketingTab',
                    'settings' => 'adminSettingsTab'
                ];
                $tooltipId = $tooltipIds[$key] ?? '';
                ?>
                <a href="/?page=admin<?= $key ? '&section=' . $key : '' ?>"
                   id="<?= $tooltipId ?>"
                   class="admin-nav-tab <?= $cssClass ?> <?= ($section === $key || ($key === '' && !$section)) ? 'active' : '' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
<<<<<<< HEAD
        
        <div class="admin-page-title">
            <?php 
            $pageTitles = [
                '' => 'Dashboard',
                'customers' => 'Customers',
                'inventory' => 'Inventory',
                'orders' => 'Orders',
                'pos' => 'Point of Sale',
                'reports' => 'Reports',
                'marketing' => 'Marketing',
                'settings' => 'Settings',
                'categories' => 'Categories'
            ];
            echo htmlspecialchars($pageTitles[$section] ?? 'Admin Panel');
            ?>
        </div>
    </div>

    <!-- Dynamic Section Content -->
=======
    </div>
    <?php endif; ?>

    <!- Dynamic Section Content ->
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    <div id="admin-section-content">
        <?php
        switch($section) {
            case 'customers':
                include 'sections/admin_customers.php';
                break;
            case 'inventory':
            case 'admin_inventory':
                include 'sections/admin_inventory.php';
                break;
            case 'orders':
                include 'sections/admin_orders.php';
                break;
            case 'pos':
                include 'sections/admin_pos.php';
                break;
            case 'reports':
                include 'sections/admin_reports.php';
                break;
            case 'marketing':
                include 'sections/admin_marketing.php';
                break;
            case 'settings':
                include 'sections/admin_settings.php';
                break;
            case 'categories':
                include 'sections/admin_categories.php';
                break;
            default:
                include 'sections/admin_dashboard.php';
                break;
        }
        ?>
    </div>
</div>

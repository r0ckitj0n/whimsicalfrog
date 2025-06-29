<?php
// Admin Dashboard - Main administrative interface
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';

// Initialize data arrays
$inventoryData = [];
$ordersData = [];
$customersData = [];

try {
    $db = Database::getInstance();
    
    // Fetch core data with optimized queries
    $inventoryData = $db->query('SELECT * FROM items ORDER BY sku')->fetchAll();
    $ordersData = $db->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();
    
    // Enhanced orders with items and formatted shipping
    foreach ($ordersData as &$order) {
        $order['items'] = $db->query('SELECT * FROM order_items WHERE orderId = ?', [$order['id']])->fetchAll();
        
        if (isset($order['shippingAddress']) && is_string($order['shippingAddress'])) {
            $order['shippingAddress'] = json_decode($order['shippingAddress'], true);
        }
    }
    
    $customersData = $db->query('SELECT * FROM users ORDER BY firstName, lastName')->fetchAll();
    
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
    <?php
    $section = $_GET['section'] ?? '';
    $tabs = [
        '' => ['Dashboard', 'admin-tab-dashboard'],
        'customers' => ['Customers', 'admin-tab-customers'],
        'inventory' => ['Inventory', 'admin-tab-inventory'],
        'orders' => ['Orders', 'admin-tab-orders'],
        'reports' => ['Reports', 'admin-tab-reports'],
        'marketing' => ['Marketing', 'admin-tab-marketing'],
        'settings' => ['Settings', 'admin-tab-settings'],
    ];
    ?>
    <div class="admin-tab-navigation mb-1">
        <div class="flex flex-wrap gap-2">
            <?php foreach ($tabs as $key => [$label, $cssClass]): ?>
                <a href="/?page=admin<?= $key ? '&section=' . $key : '' ?>"
                   class="admin-nav-tab <?= $cssClass ?> <?= ($section === $key || ($key === '' && !$section)) ? 'active' : '' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="admin-page-title">
            <?php 
            $pageTitles = [
                '' => 'Dashboard',
                'customers' => 'Customers',
                'inventory' => 'Inventory',
                'orders' => 'Orders',
                'reports' => 'Reports',
                'marketing' => 'Marketing',
                'settings' => 'Settings',
                'categories' => 'Categories',
                'order_fulfillment' => 'Order Fulfillment'
            ];
            echo htmlspecialchars($pageTitles[$section] ?? 'Admin Panel');
            ?>
        </div>
    </div>

    <!-- Dynamic Section Content -->
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
            case 'order_fulfillment':
                include 'sections/order_fulfillment.php';
                break;
            default:
                include 'sections/order_fulfillment.php';
                break;
        }
        ?>
    </div>
</div>

<?php
// Admin Dashboard
// This page provides comprehensive management tools for administrators

// Include database configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';

// Initialize arrays to prevent null values
$inventoryData = [];
$ordersData = [];
$customersData = [];

try {
    // Create a PDO connection
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Fetch items data directly from database
    $stmt = $pdo->query('SELECT * FROM items');
    $inventoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch orders data directly from database
    $ordersStmt = $pdo->query('SELECT * FROM orders');
    $ordersData = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each order, get its items
    foreach ($ordersData as &$order) {
        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE orderId = ?');
        $itemsStmt->execute([$order['id']]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert shippingAddress from JSON string to array if it exists
        if (isset($order['shippingAddress']) && is_string($order['shippingAddress'])) {
            $order['shippingAddress'] = json_decode($order['shippingAddress'], true);
        }
    }
    
    // Fetch customers/users data directly from database
    $usersStmt = $pdo->query('SELECT * FROM users');
    $customersData = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the connection
    $pdo = null;
} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log('General Error: ' . $e->getMessage());
}

// Calculate metrics
$totalProducts = count($inventoryData);
$totalOrders = count($ordersData);
$totalCustomers = count($customersData);

// Calculate total revenue
$totalRevenue = 0;
foreach ($ordersData as $order) {
    if (isset($order['total'])) {
        $totalRevenue += floatval($order['total']);
    }
}

// Format revenue for display
$formattedRevenue = '$' . number_format($totalRevenue, 2);

// Get current admin user info
$adminName = ($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? '');
$adminRole = $userData['roleType'] ?? 'Administrator';
?>

<div class="admin-dashboard">
    <!-- Admin Header -->
    <div class="bg-white shadow rounded-lg p-4 mb-4">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Admin Dashboard</h1>
                <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($adminName); ?> (<?php echo htmlspecialchars($adminRole); ?>)</p>
            </div>
            <div class="mt-2 md:mt-0">
                <p class="text-xs text-gray-500">Last login: <?php echo date('F j, Y, g:i a'); ?></p>
            </div>
        </div>
    </div>

    <!-- Compact Tab Bar for Management Areas -->
    <?php
    $section = isset($_GET['section']) ? $_GET['section'] : '';
    $tabs = [
        '' => ['Dashboard', 'bg-gray-200', 'text-gray-800'],
        'customers' => ['Customers', 'bg-purple-100', 'text-purple-800'],
        'inventory' => ['Inventory', 'bg-green-100', 'text-green-800'],
        'orders' => ['Orders', 'bg-blue-100', 'text-blue-800'],
        'reports' => ['Reports', 'bg-indigo-100', 'text-indigo-800'],
        'marketing' => ['Marketing', 'bg-red-100', 'text-red-800'],
        'settings' => ['Settings', 'bg-gray-100', 'text-gray-800'],
    ];
    ?>
    <div class="flex justify-between items-center mb-1 admin-tab-bar p-2 rounded-lg">
        <!-- Left side: Navigation tabs -->
        <div class="flex flex-wrap gap-2">
            <?php foreach ($tabs as $key => [$label, $bg, $text]): ?>
                <a href="/?page=admin<?php echo $key ? '&section=' . $key : ''; ?>"
                   class="px-3 py-1 rounded text-xs font-semibold <?php echo $bg . ' ' . $text; ?> <?php echo ($section === $key || ($key === '' && !$section)) ? 'ring-2 ring-green-400' : 'hover:bg-green-200'; ?>">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Right side: Page title -->
        <div class="text-lg font-semibold bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200" style="color: #87ac3a !important;">
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

    <!-- Section Content: Show only the selected section below the tabs -->
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
                // Show dashboard summary without statistics cards
                include 'sections/order_fulfillment.php';
                break;
        }
        ?>
    </div>
</div>

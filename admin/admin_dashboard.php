<?php
// Admin Dashboard - Configurable widget-based dashboard
// IMPORTANT: Initialize API config first so Database globals are set
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    // Get dashboard configuration
    $dashboardConfig = Database::queryAll('SELECT * FROM dashboard_sections WHERE is_active = 1 ORDER BY display_order ASC');

    // Fetch core metrics
    $totalItems = (Database::queryOne('SELECT COUNT(*) as count FROM items')['count'] ?? 0);
    $totalOrders = (Database::queryOne('SELECT COUNT(*) as count FROM orders')['count'] ?? 0);
    $totalCustomers = (Database::queryOne('SELECT COUNT(*) as count FROM users WHERE role != "admin"')['count'] ?? 0);
    $totalRevenue = (Database::queryOne('SELECT SUM(total) as revenue FROM orders')['revenue'] ?? 0);

    // Get recent activity
    $recentOrders = Database::queryAll('SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.userId = u.id ORDER BY o.date DESC LIMIT 5');
    $lowStockItems = Database::queryAll('SELECT * FROM items WHERE stockLevel <= reorderPoint AND stockLevel > 0 ORDER BY stockLevel ASC LIMIT 5');

} catch (Exception $e) {
    Logger::error('Dashboard data loading failed', ['error' => $e->getMessage()]);
    $dashboardConfig = [];
    $totalItems = $totalOrders = $totalCustomers = $totalRevenue = 0;
    $recentOrders = $lowStockItems = [];
}

// Available section templates
$availableSections = [
    'metrics' => [
        'title' => '📊 Quick Metrics',
        'description' => 'Key performance indicators and business metrics',
        'type' => 'built-in'
    ],
    'recent_orders' => [
        'title' => '📋 Recent Orders',
        'description' => 'Latest customer orders and order status',
        'type' => 'built-in'
    ],
    'low_stock' => [
        'title' => '⚠️ Low Stock Alerts',
        'description' => 'Items running low on inventory',
        'type' => 'built-in'
    ],
    'inventory_summary' => [
        'title' => '📦 Inventory Summary',
        'description' => 'Mini version of inventory management',
        'type' => 'external',
        'source' => 'inventory'
    ],
    'customer_summary' => [
        'title' => '👥 Customer Overview',
        'description' => 'Recent customers and activity',
        'type' => 'external',
        'source' => 'customers'
    ],
    'marketing_tools' => [
        'title' => '📈 Marketing Tools',
        'description' => 'Quick access to marketing features',
        'type' => 'external',
        'source' => 'marketing'
    ],
    'order_fulfillment' => [
        'title' => '🚚 Order Fulfillment',
        'description' => 'Process and manage order fulfillment',
        'type' => 'external',
        'source' => 'order_fulfillment'
    ],
    'reports_summary' => [
        'title' => '📊 Reports Summary',
        'description' => 'Key business reports and analytics',
        'type' => 'external',
        'source' => 'reports'
    ]
];

// Default sections if no configuration exists
if (empty($dashboardConfig)) {
    $dashboardConfig = [
        ['section_key' => 'metrics', 'display_order' => 1, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null],
        ['section_key' => 'recent_orders', 'display_order' => 2, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null],
        ['section_key' => 'low_stock', 'display_order' => 3, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null]
    ];
}
?>

<div class="dashboard-container">
    <!- Dashboard Sections ->
    <div id="dashboardGrid" class="dashboard-grid space-y-6">
        <?php foreach ($dashboardConfig as $config): ?>
            <?php
            $sectionInfo = $availableSections[$config['section_key']] ?? null;
            if (!$sectionInfo) {
                continue;
            }
            $widthClass = $config['width_class'] ?? 'half-width';
            // Map dashboard section keys to settings card theme classes
            $themeMap = [
                'metrics' => 'card-theme-blue',
                'recent_orders' => 'card-theme-purple',
                'low_stock' => 'card-theme-red',
                'inventory_summary' => 'card-theme-emerald',
                'customer_summary' => 'card-theme-cyan',
                'marketing_tools' => 'card-theme-amber',
                'order_fulfillment' => 'card-theme-blue',
                'reports_summary' => 'card-theme-purple',
            ];
            $themeClass = $themeMap[$config['section_key']] ?? 'card-theme-blue';
            ?>
            
            <div class="dashboard-section settings-section <?= htmlspecialchars($themeClass) ?> draggable-section <?= htmlspecialchars($widthClass) ?>" 
                 data-section-key="<?= htmlspecialchars($config['section_key']) ?>" 
                 data-order="<?= $config['display_order'] ?>"
                 data-width="<?= htmlspecialchars($widthClass) ?>">
                <!- Card header styled like settings page ->
                <div class="section-header rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h3 class="section-title text-lg font-semibold">
                                <?= htmlspecialchars(($config['custom_title'] ?? '') ?: $sectionInfo['title']) ?>
                            </h3>
                            <p class="section-description text-sm">
                                <?= htmlspecialchars(($config['custom_description'] ?? '') ?: $sectionInfo['description']) ?>
                            </p>
                        </div>
                        <div class="drag-handle cursor-move text-gray-400 hover:text-gray-600 rounded hover:bg-gray-100" title="Drag to reorder">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M8 6h2v2H8V6zm6 0h2v2h-2V6zM8 10h2v2H8v-2zm6 0h2v2h-2v-2zM8 14h2v2H8v-2zm6 0h2v2h-2v-2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="section-content">
                    <?php if ($config['section_key'] === 'metrics'): ?>
                        <!- Quick Metrics Section ->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="metric-card bg-blue-50 rounded-lg text-center">
                                <div class="text-3xl font-bold text-blue-600"><?= number_format($totalItems) ?></div>
                                <div class="text-sm text-blue-800 font-medium">Items</div>
                            </div>
                            <div class="metric-card bg-green-50 rounded-lg text-center">
                                <div class="text-3xl font-bold text-green-600"><?= number_format($totalOrders) ?></div>
                                <div class="text-sm text-green-800 font-medium">Orders</div>
                            </div>
                            <div class="metric-card bg-purple-50 rounded-lg text-center">
                                <div class="text-3xl font-bold text-purple-600"><?= number_format($totalCustomers) ?></div>
                                <div class="text-sm text-purple-800 font-medium">Customers</div>
                            </div>
                            <div class="metric-card bg-yellow-50 rounded-lg text-center">
                                <div class="text-3xl font-bold text-yellow-600">$<?= number_format($totalRevenue, 2) ?></div>
                                <div class="text-sm text-yellow-800 font-medium">Revenue</div>
                            </div>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'recent_orders'): ?>
                        <!- Recent Orders Section ->
                        <div class="space-y-3">
                            <?php if (!empty($recentOrders)): ?>
                                <?php foreach (array_slice($recentOrders, 0, 5) as $order): ?>
                                <div class="flex justify-between items-center bg-gray-50 rounded">
                                    <div>
                                        <div class="font-medium text-sm"><?= htmlspecialchars($order['id']) ?></div>
                                        <div class="text-xs text-gray-600"><?= htmlspecialchars($order['username'] ?? 'Guest') ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium">$<?= number_format($order['total'], 2) ?></div>
                                        <div class="text-xs rounded-full bg-blue-100 text-blue-800">
                                            <?= htmlspecialchars($order['status'] ?? 'Pending') ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <div class="text-center">
                                    <a href="/admin/orders" class="text-blue-600 hover:text-blue-800 text-sm">View All Orders →</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-gray-500">
                                    <div class="text-3xl">📋</div>
                                    <div class="text-sm">No recent orders</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'low_stock'): ?>
                        <!- Low Stock Section ->
                        <div class="space-y-3">
                            <?php if (!empty($lowStockItems)): ?>
                                <?php foreach ($lowStockItems as $item): ?>
                                <div class="flex justify-between items-center bg-red-50 rounded">
                                    <div>
                                        <div class="font-medium text-sm"><?= htmlspecialchars($item['name'] ?? $item['sku']) ?></div>
                                        <div class="text-xs text-gray-600"><?= htmlspecialchars($item['sku']) ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-red-600"><?= $item['stockLevel'] ?> left</div>
                                        <div class="text-xs text-gray-500">Reorder: <?= $item['reorderPoint'] ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <div class="text-center">
                                    <a href="/admin/inventory" class="text-red-600 hover:text-red-800 text-sm">Manage Inventory →</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-gray-500">
                                    <div class="text-3xl">✅</div>
                                    <div class="text-sm">All items well stocked</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'inventory_summary'): ?>
                        <!- Inventory Summary Section ->
                        <div class="space-y-3">
                            <?php
                                                         $inventoryStats = Database::queryOne('SELECT 
                                 COUNT(*) as total_items,
                                 COUNT(CASE WHEN stockLevel <= reorderPoint AND stockLevel > 0 THEN 1 END) as low_stock,
                                 COUNT(CASE WHEN stockLevel = 0 THEN 1 END) as out_of_stock,
                                 SUM(stockLevel * COALESCE(costPrice, 0)) as inventory_value
                                 FROM items');
                        $topItems = Database::queryAll('SELECT name, sku, stockLevel, reorderPoint FROM items ORDER BY stockLevel DESC LIMIT 3');
                        ?>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-blue-50 rounded text-center">
                                    <div class="text-lg font-bold text-blue-600"><?= $inventoryStats['total_items'] ?? 0 ?></div>
                                    <div class="text-xs text-blue-800">Total Items</div>
                                </div>
                                <div class="bg-red-50 rounded text-center">
                                    <div class="text-lg font-bold text-red-600"><?= $inventoryStats['low_stock'] ?? 0 ?></div>
                                    <div class="text-xs text-red-800">Low Stock</div>
                                </div>
                            </div>
                            <?php if (!empty($topItems)): ?>
                                <div class="text-xs font-medium text-gray-600">Top Stock Items:</div>
                                <?php foreach ($topItems as $item): ?>
                                <div class="flex justify-between items-center text-xs bg-gray-50 rounded">
                                    <span><?= htmlspecialchars($item['name'] ?? $item['sku']) ?></span>
                                    <span class="font-medium"><?= $item['stockLevel'] ?? 0 ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="text-center">
                                <a href="/admin/inventory" class="text-blue-600 hover:text-blue-800 text-sm">Manage Inventory →</a>
                            </div>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'customer_summary'): ?>
                        <!- Customer Summary Section ->
                        <div class="space-y-3">
                            <?php
                                                     $customerStats = Database::queryOne('SELECT 
                                 COUNT(*) as total_customers
                                 FROM users WHERE role != \'admin\'');
                        $recentCustomers = Database::queryAll('SELECT username, email FROM users WHERE role != \'admin\' ORDER BY id DESC LIMIT 3');
                        ?>
                                                         <div class="grid grid-cols-1 gap-3">
                                 <div class="bg-green-50 rounded text-center">
                                     <div class="text-lg font-bold text-green-600"><?= $customerStats['total_customers'] ?? 0 ?></div>
                                     <div class="text-xs text-green-800">Total Customers</div>
                                 </div>
                             </div>
                            <?php if (!empty($recentCustomers)): ?>
                                <div class="text-xs font-medium text-gray-600">Recent Customers:</div>
                                <?php foreach ($recentCustomers as $customer): ?>
                                <div class="text-xs bg-gray-50 rounded">
                                    <div class="font-medium"><?= htmlspecialchars($customer['username'] ?? 'Unknown') ?></div>
                                    <div class="text-gray-500"><?= htmlspecialchars($customer['email'] ?? '') ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="text-center">
                                <a href="/admin/customers" class="text-green-600 hover:text-green-800 text-sm">Manage Customers →</a>
                            </div>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'marketing_tools'): ?>
                        <!- Marketing Tools Section ->
                        <div class="space-y-3">
                            <?php
                        $marketingStats = Database::queryRow('SELECT 
                                (SELECT COUNT(*) FROM email_campaigns) as email_campaigns,
                                (SELECT COUNT(*) FROM discount_codes WHERE (end_date IS NULL OR end_date >= CURDATE())) as active_discounts,
                                (SELECT COUNT(*) FROM social_posts WHERE scheduled_date >= CURDATE()) as scheduled_posts
                            ');
                        ?>
                            <div class="grid grid-cols-1 gap-2">
                                <div class="bg-orange-50 rounded text-center">
                                    <div class="text-lg font-bold text-orange-600"><?= $marketingStats['email_campaigns'] ?? 0 ?></div>
                                    <div class="text-xs text-orange-800">Email Campaigns</div>
                                </div>
                                <div class="bg-indigo-50 rounded text-center">
                                    <div class="text-lg font-bold text-indigo-600"><?= $marketingStats['active_discounts'] ?? 0 ?></div>
                                    <div class="text-xs text-indigo-800">Active Discounts</div>
                                </div>
                            </div>
                            <div class="text-center space-y-2">
                                <div class="flex gap-2 justify-center">
                                    <a href="/admin/marketing" class="bg-orange-600 hover:bg-orange-700 text-white text-xs rounded">📧 Email</a>
                                    <a href="/admin/marketing" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs rounded">🏷️ Discounts</a>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'order_fulfillment'): ?>
                        <!- Updated Order Fulfillment Interface Embedded ->
                        <?php
                        // Get filter parameters
                        $filterDate = $_GET['filter_date'] ?? '';
                        $filterItems = $_GET['filter_items'] ?? '';
                        $filterStatus = $_GET['filter_status'] ?? '';
                        $filterPaymentMethod = $_GET['filter_payment_method'] ?? '';
                        $filterShippingMethod = $_GET['filter_shipping_method'] ?? '';
                        $filterPaymentStatus = $_GET['filter_payment_status'] ?? '';

                        // Build the WHERE clause based on filters
                        $whereConditions = [];
                        $params = [];

                        // Default to Processing status if no status filter is provided, but allow "All" to show everything
                        if (!isset($_GET['filter_status'])) {
                            $defaultStatus = 'Processing';
                        } else {
                            $defaultStatus = $filterStatus;
                        }

            if (!empty($filterDate)) {
                $whereConditions[] = "DATE(o.date) = ?";
                $params[] = $filterDate;
            }

            if (!empty($defaultStatus)) {
                $whereConditions[] = "o.order_status = ?";
                $params[] = $defaultStatus;
            }

            if (!empty($filterPaymentMethod)) {
                $whereConditions[] = "o.paymentMethod = ?";
                $params[] = $filterPaymentMethod;
            }

            if (!empty($filterShippingMethod)) {
                $whereConditions[] = "o.shippingMethod = ?";
                $params[] = $filterShippingMethod;
            }

            if (!empty($filterPaymentStatus)) {
                $whereConditions[] = "o.paymentStatus = ?";
                $params[] = $filterPaymentStatus;
            }

            if (!empty($filterItems)) {
                $whereConditions[] = "EXISTS (SELECT 1 FROM order_items oi LEFT JOIN items i ON oi.sku = i.sku WHERE oi.orderId = o.id AND (COALESCE(i.name, oi.sku) LIKE ? OR oi.sku LIKE ?))";
                $params[] = "%{$filterItems}%";
                $params[] = "%{$filterItems}%";
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $orders = Database::queryAll("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode FROM orders o JOIN users u ON o.userId = u.id {$whereClause} ORDER BY o.date DESC", $params);

            // Get unique values for filter dropdowns
            $statusOptions = array_column(Database::queryAll("SELECT DISTINCT order_status FROM orders WHERE order_status IN ('Pending','Processing','Shipped','Delivered','Cancelled') ORDER BY order_status"), 'order_status');
            $paymentMethodOptions = array_column(Database::queryAll("SELECT DISTINCT paymentMethod FROM orders WHERE paymentMethod IS NOT NULL AND paymentMethod != '' ORDER BY paymentMethod"), 'paymentMethod');
            $shippingMethodOptions = array_column(Database::queryAll("SELECT DISTINCT shippingMethod FROM orders WHERE shippingMethod IS NOT NULL AND shippingMethod != '' ORDER BY shippingMethod"), 'shippingMethod');
            $paymentStatusOptions = array_column(Database::queryAll("SELECT DISTINCT paymentStatus FROM orders WHERE paymentStatus IS NOT NULL AND paymentStatus != '' ORDER BY paymentStatus"), 'paymentStatus');
            ?>
                        
                        <div class="space-y-4">
                            <!- Filter Section ->
                            <div class="bg-white border border-gray-200 rounded-lg">
                                <form method="GET" action="/admin" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Date</label>
                                        <input type="date" name="filter_date" value="<?= htmlspecialchars($filterDate) ?>" class="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Items</label>
                                        <input type="text" name="filter_items" value="<?= htmlspecialchars($filterItems) ?>" placeholder="Search..." class="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Order Status</label>
                                        <select name="filter_status" class="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                            <option value="">All Order Status</option>
                                            <?php foreach ($statusOptions as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= $defaultStatus === $status ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($status) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Payment</label>
                                        <select name="filter_payment_method" class="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                            <option value="">All Payment</option>
                                            <?php foreach ($paymentMethodOptions as $method): ?>
                                            <option value="<?= htmlspecialchars($method) ?>" <?= $filterPaymentMethod === $method ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($method) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Shipping</label>
                                        <select name="filter_shipping_method" class="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                            <option value="">All Shipping</option>
                                            <?php foreach ($shippingMethodOptions as $method): ?>
                                            <option value="<?= htmlspecialchars($method) ?>" <?= $filterShippingMethod === $method ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($method) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Pay Status</label>
                                        <select name="filter_payment_status" class="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                            <option value="">All Pay Status</option>
                                            <?php foreach ($paymentStatusOptions as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= $filterPaymentStatus === $status ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($status) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="flex items-end space-x-2">
                                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-xs rounded transition-colors">Filter</button>
                                        <a href="/?page=admin" class="bg-gray-500 hover:bg-gray-600 text-white text-xs rounded transition-colors">Clear</a>
                                    </div>
                                </form>
                            </div>

                            <!- Orders Table ->
                            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                <?php if (empty($orders)): ?>
                                    <div class="text-center text-gray-500">
                                        <div class="text-3xl">📋</div>
                                        <div class="text-sm">No orders found</div>
                                        <div class="text-xs">Try adjusting your filters</div>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs">
                                            <thead class="bg-gray-50 border-b border-gray-200">
                                                <tr>
                                                    <th class="text-left font-medium text-gray-700">Order ID</th>
                                                    <th class="text-left font-medium text-gray-700">Customer</th>
                                                    <th class="text-left font-medium text-gray-700">Date</th>
                                                    <th class="text-left font-medium text-gray-700">Time</th>
                                                    <th class="text-left font-medium text-gray-700">Items</th>
                                                    <th class="text-left font-medium text-gray-700">Total</th>
                                                    <th class="text-left font-medium text-gray-700">Payment Status</th>
                                                    <th class="text-left font-medium text-gray-700">Payment Date</th>
                                                    <th class="text-left font-medium text-gray-700">Order Status</th>
                                                    <th class="text-left font-medium text-gray-700">Payment Method</th>
                                                    <th class="text-left font-medium text-gray-700">Shipping Method</th>
                                                    <th class="text-left font-medium text-gray-700">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <?php foreach ($orders as $order): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="font-mono font-medium text-gray-900">#<?= htmlspecialchars($order['id'] ?? '') ?></td>
                                                    <td class=""><?= htmlspecialchars($order['username'] ?? 'N/A') ?></td>
                                                    <td class="text-gray-600"><?= htmlspecialchars(date('M j, Y', strtotime($order['date'] ?? 'now'))) ?></td>
                                                    <td class="text-gray-600"><?= htmlspecialchars(date('g:i A', strtotime($order['date'] ?? 'now'))) ?></td>
                                                    <td class="text-center">
                                                        <button data-action="open-order-details" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>"
                                                                class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer bg-transparent border-none"
                                                                title="Click to view order details">
                                                            <?php
                                                $totalItems = Database::queryOne("SELECT SUM(quantity) as total_items FROM order_items WHERE orderId = ?", [$order['id']])['total_items'] ?? 0;
                                                    echo ($totalItems ?: '0') . ' item' . (($totalItems != 1) ? 's' : '');
                                                    ?>
                                                        </button>
                                                    </td>
                                                    <td class="font-semibold">$<?= number_format(floatval($order['total'] ?? 0), 2) ?></td>
                                                    <td class="">
                                                        <select class="w-full text-xs border border-gray-300 rounded order-field-update" data-field="paymentStatus" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                                            <option value="Pending" <?= strtolower($order['paymentStatus'] ?? 'Pending') === strtolower('Pending') ? 'selected' : '' ?>>Pending</option>
                                                            <option value="Received" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Received') ? 'selected' : '' ?>>Received</option>
                                                            <option value="Refunded" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Refunded') ? 'selected' : '' ?>>Refunded</option>
                                                            <option value="Failed" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Failed') ? 'selected' : '' ?>>Failed</option>
                                                        </select>
                                                    </td>
                                                    <td class="">
                                                        <input type="date" 
                                                               class="w-full text-xs border border-gray-300 rounded order-field-update" 
                                                               data-field="paymentDate" 
                                                               data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>"
                                                               value="<?= !empty($order['paymentDate']) ? htmlspecialchars(date('Y-m-d', strtotime($order['paymentDate']))) : '' ?>"
                                                               >
                                                    </td>
                                                    <td class="">
                                                        <select class="w-full text-xs border border-gray-300 rounded order-field-update" data-field="order_status" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                                            <option value="Pending" <?= strtolower($order['order_status'] ?? 'Pending') === strtolower('Pending') ? 'selected' : '' ?>>Pending</option>
                                                            <option value="Processing" <?= strtolower($order['order_status'] ?? '') === strtolower('Processing') ? 'selected' : '' ?>>Processing</option>
                                                            <option value="Shipped" <?= strtolower($order['order_status'] ?? '') === strtolower('Shipped') ? 'selected' : '' ?>>Shipped</option>
                                                            <option value="Delivered" <?= strtolower($order['order_status'] ?? '') === strtolower('Delivered') ? 'selected' : '' ?>>Delivered</option>
                                                            <option value="Cancelled" <?= strtolower($order['order_status'] ?? '') === strtolower('Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                                        </select>
                                                    </td>
                                                    <td class="">
                                                        <select class="w-full text-xs border border-gray-300 rounded order-field-update" data-field="paymentMethod" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                                            <option value="Credit Card" <?= strtolower($order['paymentMethod'] ?? 'Credit Card') === strtolower('Credit Card') ? 'selected' : '' ?>>Credit Card</option>
                                                            <option value="Cash" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Cash') ? 'selected' : '' ?>>Cash</option>
                                                            <option value="Check" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Check') ? 'selected' : '' ?>>Check</option>
                                                            <option value="PayPal" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('PayPal') ? 'selected' : '' ?>>PayPal</option>
                                                            <option value="Venmo" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Venmo') ? 'selected' : '' ?>>Venmo</option>
                                                        </select>
                                                    </td>
                                                    <td class="">
                                                        <select class="w-full text-xs border border-gray-300 rounded order-field-update" data-field="shippingMethod" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                                            <option value="Customer Pickup" <?= strtolower($order['shippingMethod'] ?? 'Customer Pickup') === strtolower('Customer Pickup') ? 'selected' : '' ?>>Customer Pickup</option>
                                                            <option value="Local Delivery" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('Local Delivery') ? 'selected' : '' ?>>Local Delivery</option>
                                                            <option value="USPS" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('USPS') ? 'selected' : '' ?>>USPS</option>
                                                            <option value="FedEx" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('FedEx') ? 'selected' : '' ?>>FedEx</option>
                                                            <option value="UPS" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('UPS') ? 'selected' : '' ?>>UPS</option>
                                                        </select>
                                                    </td>
                                                    <td class="">
                                                        <div class="flex space-x-2">
                                                            <a href="/?page=admin&section=orders&view=<?= urlencode($order['id']) ?>" 
                                                               class="text-blue-600 hover:text-blue-800" title="View Order">👁️</a>
                                                            <a href="/?page=admin&section=orders&edit=<?= urlencode($order['id']) ?>" 
                                                               class="text-green-600 hover:text-green-800" title="Edit Order">✏️</a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        


                                    const fieldName = this.dataset.field;
                                    const newValue = this.value;
                                    
                                    const originalStyle = this.style.backgroundColor;
                                    this.style.backgroundColor = '#fef3c7';
                                    this.disabled = true;
                                    
                                    try {
                                        const formData = new FormData();
                                        formData.append('orderId', orderId);
                                        formData.append('field', fieldName);
                                        formData.append('value', newValue);
                                        formData.append('action', 'updateField');
                                        
                                        const response = await fetch('/api/fulfill_order.php', {
                                            method: 'POST',
                                            body: formData
                                        });
                                        
                                        const result = await response.json();
                                        
                                        if (result.success) {
                                            this.style.backgroundColor = '#dcfce7';
                                            setTimeout(() => {
                                                this.style.backgroundColor = originalStyle;
                                            }, 2000);
                                        } else {
                                            this.style.backgroundColor = '#fecaca';
                                            setTimeout(() => {
                                                this.style.backgroundColor = originalStyle;
                                            }, 2000);
                                        }
                                    } catch (error) {
                                        this.style.backgroundColor = '#fecaca';
                                        setTimeout(() => {
                                            this.style.backgroundColor = originalStyle;
                                        }, 2000);
                                    } finally {
                                        this.disabled = false;
                                    }
                                });
                            });
                        });
                        </script>

                        <!- Order Details Modal - Sunday Layout ->
                        <div class="modal-overlay order-modal" id="orderDetailsModal" class="hidden">
                            <div class="modal-content order-modal-content">
                                <!- Modal Header ->
                                <div class="modal-header">
                                    <h2 class="modal-title">Order Details: <span id="modal-order-id">#0000</span></h2>
                                    <button data-action="close-order-details" class="modal-close">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <!- Modal Body ->
                                <div class="modal-body">
                                    <div class="order-form-grid">
                                        <!- Order Details Column ->
                                        <div class="order-details-column">
                                            <div class="form-section">
                                                <h3 class="form-section-title">Order Information</h3>
                                                <div class="form-grid">
                                                    <div class="form-group">
                                                        <label class="form-label">Customer</label>
                                                        <div class="form-input" id="modal-customer">N/A</div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Date</label>
                                                        <div class="form-input" id="modal-date">N/A</div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Order Status</label>
                                                        <div class="form-input" id="modal-status">Pending</div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Total</label>
                                                        <div class="form-input font-bold" id="modal-total">$0.00</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-section">
                                                <h3 class="form-section-title">Payment & Shipping</h3>
                                                <div class="form-grid">
                                                    <div class="form-group">
                                                        <label class="form-label">Payment Method</label>
                                                        <div class="form-input" id="modal-payment-method">N/A</div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Payment Status</label>
                                                        <div class="form-input" id="modal-payment-status">Pending</div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Shipping Method</label>
                                                        <div class="form-input" id="modal-shipping-method">N/A</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!- Order Items Column ->
                                        <div class="order-items-column">
                                            <div class="form-section">
                                                <h3 class="form-section-title">Order Items</h3>
                                                <div class="order-items-list" id="modal-order-items">
                                                    <!- Items will be populated dynamically ->
                                                </div>
                                            </div>

                                            <div class="form-section">
                                                <h3 class="form-section-title">Shipping Address</h3>
                                                <div class="address-display">
                                                    <pre id="modal-address">No address provided</pre>
                                                </div>
                                            </div>

                                            <div class="form-section">
                                                <h3 class="form-section-title">Notes</h3>
                                                <div class="form-group">
                                                    <label class="form-label">Order Notes</label>
                                                    <div class="form-input" id="modal-notes">No notes</div>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Payment Notes</label>
                                                    <div class="form-input" id="modal-payment-notes">No payment notes</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'reports_summary'): ?>
                        <!- Reports Summary Section ->
                        <div class="space-y-2">
                            <?php
                            $reportsStats = Database::queryRow('SELECT 
                                COUNT(*) as total_orders,
                                SUM(total) as total_revenue,
                                AVG(total) as avg_order_value
                                FROM orders WHERE DATE(date) >= CURDATE() - INTERVAL 30 DAY');
                        ?>
                            <div class="grid grid-cols-1 gap-1">
                                <div class="bg-teal-50 rounded text-center">
                                    <div class="text-sm font-bold text-teal-600">$<?= number_format($reportsStats['total_revenue'] ?? 0, 0) ?></div>
                                    <div class="text-xs text-teal-800">30-Day Revenue</div>
                                </div>
                                <div class="bg-cyan-50 rounded text-center">
                                    <div class="text-sm font-bold text-cyan-600">$<?= number_format($reportsStats['avg_order_value'] ?? 0, 0) ?></div>
                                    <div class="text-xs text-cyan-800">Avg Order Value</div>
                                </div>
                            </div>
                            <div class="text-center">
                                <a href="/?page=admin&section=reports" class="text-teal-600 hover:text-teal-800 text-xs">View Reports →</a>
                            </div>
                        </div>
                        
                    <?php elseif ($sectionInfo['type'] === 'external'): ?>
                        <!- External Section Placeholder ->
                        <div class="text-center text-gray-500">
                            <div class="text-3xl">🔗</div>
                            <div class="text-sm">External Section</div>
                            <a href="/?page=admin&section=<?= htmlspecialchars($sectionInfo['source']) ?>" 
                               class="text-blue-600 hover:text-blue-800 text-sm">
                                Open <?= htmlspecialchars($sectionInfo['title']) ?> →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (empty($dashboardConfig)): ?>
    <!- Empty State ->
    <div class="text-center">
        <div class="text-6xl">📊</div>
        <h3 class="text-xl font-semibold text-gray-800">Welcome to Your Dashboard</h3>
        <p class="text-gray-600">Configure your dashboard to see the information that matters most to you.</p>
        <button data-action="open-dashboard-config" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
            ⚙️ Configure Dashboard
        </button>
    </div>
    <?php endif; ?>
</div>







 
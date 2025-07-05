
<!-- Database-driven CSS for admin_dashboard -->
<style id="admin_dashboard-css">
/* CSS will be loaded from database */
</style>
<script>
    // Load CSS from database
    async function loadAdmin_dashboardCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=admin_dashboard');
            const cssText = await response.text();
            const styleElement = document.getElementById('admin_dashboard-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ admin_dashboard CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load admin_dashboard CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>admin_dashboard CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadAdmin_dashboardCSS);
</script>

<?php
// Admin Dashboard - Configurable widget-based dashboard
require_once __DIR__ . '/../includes/functions.php';

try {
    // Get dashboard configuration
    $dashboardConfig = Database::queryAll('SELECT * FROM dashboard_sections WHERE is_active = 1 ORDER BY display_order ASC');
    
    // Fetch core metrics
    $totalItems = Database::queryRow('SELECT COUNT(*) as count FROM items')['count'] ?? 0;
    $totalOrders = Database::queryRow('SELECT COUNT(*) as count FROM orders')['count'] ?? 0;
    $totalCustomers = Database::queryRow('SELECT COUNT(*) as count FROM users WHERE role != "admin"')['count'] ?? 0;
    $totalRevenue = Database::queryRow('SELECT SUM(total) as revenue FROM orders')['revenue'] ?? 0;
    
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
    <!-- Dashboard Sections -->
    <div id="dashboardGrid" class="dashboard-grid space-y-6">
        <?php foreach ($dashboardConfig as $config): ?>
            <?php 
            $sectionInfo = $availableSections[$config['section_key']] ?? null;
            if (!$sectionInfo) continue;
            $widthClass = $config['width_class'] ?? 'half-width';
            ?>
            
            <div class="dashboard-section bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow draggable-section <?= htmlspecialchars($widthClass) ?>" 
                 data-section-key="<?= htmlspecialchars($config['section_key']) ?>" 
                 data-order="<?= $config['display_order'] ?>"
                 data-width="<?= htmlspecialchars($widthClass) ?>">
                <!-- Always show title and description for dashboard sections -->
                <div class="section-header p-4 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-800 mb-1">
                                <?= htmlspecialchars(($config['custom_title'] ?? '') ?: $sectionInfo['title']) ?>
                            </h3>
                            <p class="text-sm text-gray-600">
                                <?= htmlspecialchars(($config['custom_description'] ?? '') ?: $sectionInfo['description']) ?>
                            </p>
                        </div>
                        <div class="drag-handle cursor-move text-gray-400 hover:text-gray-600 ml-3 p-2 rounded hover:bg-gray-100" title="Drag to reorder">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M8 6h2v2H8V6zm6 0h2v2h-2V6zM8 10h2v2H8v-2zm6 0h2v2h-2v-2zM8 14h2v2H8v-2zm6 0h2v2h-2v-2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="section-content p-4">
                    <?php if ($config['section_key'] === 'metrics'): ?>
                        <!-- Quick Metrics Section -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="metric-card bg-blue-50 p-4 rounded-lg text-center">
                                <div class="text-3xl font-bold text-blue-600 mb-1"><?= number_format($totalItems) ?></div>
                                <div class="text-sm text-blue-800 font-medium">Items</div>
                            </div>
                            <div class="metric-card bg-green-50 p-4 rounded-lg text-center">
                                <div class="text-3xl font-bold text-green-600 mb-1"><?= number_format($totalOrders) ?></div>
                                <div class="text-sm text-green-800 font-medium">Orders</div>
                            </div>
                            <div class="metric-card bg-purple-50 p-4 rounded-lg text-center">
                                <div class="text-3xl font-bold text-purple-600 mb-1"><?= number_format($totalCustomers) ?></div>
                                <div class="text-sm text-purple-800 font-medium">Customers</div>
                            </div>
                            <div class="metric-card bg-yellow-50 p-4 rounded-lg text-center">
                                <div class="text-3xl font-bold text-yellow-600 mb-1">$<?= number_format($totalRevenue, 2) ?></div>
                                <div class="text-sm text-yellow-800 font-medium">Revenue</div>
                            </div>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'recent_orders'): ?>
                        <!-- Recent Orders Section -->
                        <div class="space-y-3">
                            <?php if (!empty($recentOrders)): ?>
                                <?php foreach (array_slice($recentOrders, 0, 5) as $order): ?>
                                <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                    <div>
                                        <div class="font-medium text-sm"><?= htmlspecialchars($order['id']) ?></div>
                                        <div class="text-xs text-gray-600"><?= htmlspecialchars($order['username'] ?? 'Guest') ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium">$<?= number_format($order['total'], 2) ?></div>
                                        <div class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                                            <?= htmlspecialchars($order['status'] ?? 'Pending') ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <div class="text-center pt-2">
                                    <a href="/?page=admin&section=orders" class="text-blue-600 hover:text-blue-800 text-sm">View All Orders →</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-gray-500 py-8">
                                    <div class="text-3xl mb-2">📋</div>
                                    <div class="text-sm">No recent orders</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'low_stock'): ?>
                        <!-- Low Stock Section -->
                        <div class="space-y-3">
                            <?php if (!empty($lowStockItems)): ?>
                                <?php foreach ($lowStockItems as $item): ?>
                                <div class="flex justify-between items-center p-2 bg-red-50 rounded">
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
                                <div class="text-center pt-2">
                                    <a href="/?page=admin&section=inventory" class="text-red-600 hover:text-red-800 text-sm">Manage Inventory →</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-gray-500 py-8">
                                    <div class="text-3xl mb-2">✅</div>
                                    <div class="text-sm">All items well stocked</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'inventory_summary'): ?>
                        <!-- Inventory Summary Section -->
                        <div class="space-y-3">
                            <?php 
                                                         $inventoryStats = Database::queryRow('SELECT 
                                 COUNT(*) as total_items,
                                 COUNT(CASE WHEN stockLevel <= reorderPoint AND stockLevel > 0 THEN 1 END) as low_stock,
                                 COUNT(CASE WHEN stockLevel = 0 THEN 1 END) as out_of_stock,
                                 SUM(stockLevel * COALESCE(costPrice, 0)) as inventory_value
                                 FROM items');
                            $topItems = Database::queryAll('SELECT name, sku, stockLevel, reorderPoint FROM items ORDER BY stockLevel DESC LIMIT 3');
                            ?>
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div class="bg-blue-50 p-3 rounded text-center">
                                    <div class="text-lg font-bold text-blue-600"><?= $inventoryStats['total_items'] ?? 0 ?></div>
                                    <div class="text-xs text-blue-800">Total Items</div>
                                </div>
                                <div class="bg-red-50 p-3 rounded text-center">
                                    <div class="text-lg font-bold text-red-600"><?= $inventoryStats['low_stock'] ?? 0 ?></div>
                                    <div class="text-xs text-red-800">Low Stock</div>
                                </div>
                            </div>
                            <?php if (!empty($topItems)): ?>
                                <div class="text-xs font-medium text-gray-600 mb-2">Top Stock Items:</div>
                                <?php foreach ($topItems as $item): ?>
                                <div class="flex justify-between items-center text-xs p-2 bg-gray-50 rounded">
                                    <span><?= htmlspecialchars($item['name'] ?? $item['sku']) ?></span>
                                    <span class="font-medium"><?= $item['stockLevel'] ?? 0 ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="text-center pt-2">
                                <a href="/?page=admin&section=inventory" class="text-blue-600 hover:text-blue-800 text-sm">Manage Inventory →</a>
                            </div>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'customer_summary'): ?>
                        <!-- Customer Summary Section -->
                        <div class="space-y-3">
                            <?php 
                                                         $customerStats = Database::queryRow('SELECT 
                                 COUNT(*) as total_customers
                                 FROM users WHERE role != \'admin\'');
                             $recentCustomers = Database::queryAll('SELECT username, email FROM users WHERE role != \'admin\' ORDER BY id DESC LIMIT 3');
                            ?>
                                                         <div class="grid grid-cols-1 gap-3 mb-4">
                                 <div class="bg-green-50 p-3 rounded text-center">
                                     <div class="text-lg font-bold text-green-600"><?= $customerStats['total_customers'] ?? 0 ?></div>
                                     <div class="text-xs text-green-800">Total Customers</div>
                                 </div>
                             </div>
                            <?php if (!empty($recentCustomers)): ?>
                                <div class="text-xs font-medium text-gray-600 mb-2">Recent Customers:</div>
                                <?php foreach ($recentCustomers as $customer): ?>
                                <div class="text-xs p-2 bg-gray-50 rounded">
                                    <div class="font-medium"><?= htmlspecialchars($customer['username'] ?? 'Unknown') ?></div>
                                    <div class="text-gray-500"><?= htmlspecialchars($customer['email'] ?? '') ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="text-center pt-2">
                                <a href="/?page=admin&section=customers" class="text-green-600 hover:text-green-800 text-sm">Manage Customers →</a>
                            </div>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'marketing_tools'): ?>
                        <!-- Marketing Tools Section -->
                        <div class="space-y-3">
                            <?php 
                            $marketingStats = Database::queryRow('SELECT 
                                (SELECT COUNT(*) FROM email_campaigns) as email_campaigns,
                                (SELECT COUNT(*) FROM discount_codes WHERE (end_date IS NULL OR end_date >= CURDATE())) as active_discounts,
                                (SELECT COUNT(*) FROM social_posts WHERE scheduled_date >= CURDATE()) as scheduled_posts
                            ');
                            ?>
                            <div class="grid grid-cols-1 gap-2 mb-4">
                                <div class="bg-orange-50 p-3 rounded text-center">
                                    <div class="text-lg font-bold text-orange-600"><?= $marketingStats['email_campaigns'] ?? 0 ?></div>
                                    <div class="text-xs text-orange-800">Email Campaigns</div>
                                </div>
                                <div class="bg-indigo-50 p-3 rounded text-center">
                                    <div class="text-lg font-bold text-indigo-600"><?= $marketingStats['active_discounts'] ?? 0 ?></div>
                                    <div class="text-xs text-indigo-800">Active Discounts</div>
                                </div>
                            </div>
                            <div class="text-center space-y-2">
                                <div class="flex gap-2 justify-center">
                                    <a href="/?page=admin&section=marketing" class="px-3 py-1 bg-orange-600 hover:bg-orange-700 text-white text-xs rounded">📧 Email</a>
                                    <a href="/?page=admin&section=marketing" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs rounded">🏷️ Discounts</a>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'order_fulfillment'): ?>
                        <!-- Updated Order Fulfillment Interface Embedded -->
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
                        $statusOptions = Database::queryAll("SELECT DISTINCT order_status FROM orders WHERE order_status IN ('Pending','Processing','Shipped','Delivered','Cancelled') ORDER BY order_status", [], PDO::FETCH_COLUMN);
                        $paymentMethodOptions = Database::queryAll("SELECT DISTINCT paymentMethod FROM orders WHERE paymentMethod IS NOT NULL AND paymentMethod != '' ORDER BY paymentMethod", [], PDO::FETCH_COLUMN);
                        $shippingMethodOptions = Database::queryAll("SELECT DISTINCT shippingMethod FROM orders WHERE shippingMethod IS NOT NULL AND shippingMethod != '' ORDER BY shippingMethod", [], PDO::FETCH_COLUMN);
                        $paymentStatusOptions = Database::queryAll("SELECT DISTINCT paymentStatus FROM orders WHERE paymentStatus IS NOT NULL AND paymentStatus != '' ORDER BY paymentStatus", [], PDO::FETCH_COLUMN);
                        ?>
                        
                        <div class="space-y-4">
                            <!-- Filter Section -->
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <form method="GET" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                    <input type="hidden" name="page" value="admin">
                                    <input type="hidden" name="section" value="">
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Date</label>
                                        <input type="date" name="filter_date" value="<?= htmlspecialchars($filterDate) ?>" class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Items</label>
                                        <input type="text" name="filter_items" value="<?= htmlspecialchars($filterItems) ?>" placeholder="Search..." class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Order Status</label>
                                        <select name="filter_status" class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                            <option value="">All Order Status</option>
                                            <?php foreach ($statusOptions as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= $defaultStatus === $status ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($status) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Payment</label>
                                        <select name="filter_payment_method" class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                            <option value="">All Payment</option>
                                            <?php foreach ($paymentMethodOptions as $method): ?>
                                            <option value="<?= htmlspecialchars($method) ?>" <?= $filterPaymentMethod === $method ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($method) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Shipping</label>
                                        <select name="filter_shipping_method" class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                            <option value="">All Shipping</option>
                                            <?php foreach ($shippingMethodOptions as $method): ?>
                                            <option value="<?= htmlspecialchars($method) ?>" <?= $filterShippingMethod === $method ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($method) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Pay Status</label>
                                        <select name="filter_payment_status" class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                            <option value="">All Pay Status</option>
                                            <?php foreach ($paymentStatusOptions as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= $filterPaymentStatus === $status ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($status) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="flex items-end space-x-2">
                                        <button type="submit" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs rounded transition-colors">Filter</button>
                                        <a href="/?page=admin" class="px-3 py-1 bg-gray-500 hover:bg-gray-600 text-white text-xs rounded transition-colors">Clear</a>
                                    </div>
                                </form>
                            </div>

                            <!-- Orders Table -->
                            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                <?php if (empty($orders)): ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <div class="text-3xl mb-2">📋</div>
                                        <div class="text-sm">No orders found</div>
                                        <div class="text-xs">Try adjusting your filters</div>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs">
                                            <thead class="bg-gray-50 border-b border-gray-200">
                                                <tr>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Order ID</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Customer</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Date</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Time</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Items</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Total</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Payment Status</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Payment Date</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Order Status</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Payment Method</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Shipping Method</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <?php foreach ($orders as $order): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-3 py-2 font-mono font-medium text-gray-900">#<?= htmlspecialchars($order['id'] ?? '') ?></td>
                                                    <td class="px-3 py-2"><?= htmlspecialchars($order['username'] ?? 'N/A') ?></td>
                                                    <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars(date('M j, Y', strtotime($order['date'] ?? 'now'))) ?></td>
                                                    <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars(date('g:i A', strtotime($order['date'] ?? 'now'))) ?></td>
                                                    <td class="px-3 py-2 text-center">
                                                        <button onclick="openOrderDetailsModal(<?= htmlspecialchars($order['id'] ?? '') ?>)"
                                                                class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer bg-transparent border-none p-0"
                                                                title="Click to view order details">
                                                            <?php
                                                            $totalItems = Database::queryRow("SELECT SUM(quantity) as total_items FROM order_items WHERE orderId = ?", [$order['id']])['total_items'] ?? 0;
                                                            echo ($totalItems ?: '0') . ' item' . (($totalItems != 1) ? 's' : '');
                                                            ?>
                                                        </button>
                                                    </td>
                                                    <td class="px-3 py-2 font-semibold">$<?= number_format(floatval($order['total'] ?? 0), 2) ?></td>
                                                    <td class="px-3 py-2">
                                                        <select class="w-full px-1 py-1 text-xs border border-gray-300 rounded order-field-update" data-field="paymentStatus" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                                            <option value="Pending" <?= strtolower($order['paymentStatus'] ?? 'Pending') === strtolower('Pending') ? 'selected' : '' ?>>Pending</option>
                                                            <option value="Received" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Received') ? 'selected' : '' ?>>Received</option>
                                                            <option value="Refunded" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Refunded') ? 'selected' : '' ?>>Refunded</option>
                                                            <option value="Failed" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Failed') ? 'selected' : '' ?>>Failed</option>
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="date" 
                                                               class="w-full px-1 py-1 text-xs border border-gray-300 rounded order-field-update" 
                                                               data-field="paymentDate" 
                                                               data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>"
                                                               value="<?= !empty($order['paymentDate']) ? htmlspecialchars(date('Y-m-d', strtotime($order['paymentDate']))) : '' ?>"
                                                               style="min-width: 100px;">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <select class="w-full px-1 py-1 text-xs border border-gray-300 rounded order-field-update" data-field="order_status" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                                            <option value="Pending" <?= strtolower($order['order_status'] ?? 'Pending') === strtolower('Pending') ? 'selected' : '' ?>>Pending</option>
                                                            <option value="Processing" <?= strtolower($order['order_status'] ?? '') === strtolower('Processing') ? 'selected' : '' ?>>Processing</option>
                                                            <option value="Shipped" <?= strtolower($order['order_status'] ?? '') === strtolower('Shipped') ? 'selected' : '' ?>>Shipped</option>
                                                            <option value="Delivered" <?= strtolower($order['order_status'] ?? '') === strtolower('Delivered') ? 'selected' : '' ?>>Delivered</option>
                                                            <option value="Cancelled" <?= strtolower($order['order_status'] ?? '') === strtolower('Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <select class="w-full px-1 py-1 text-xs border border-gray-300 rounded order-field-update" data-field="paymentMethod" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                                            <option value="Credit Card" <?= strtolower($order['paymentMethod'] ?? 'Credit Card') === strtolower('Credit Card') ? 'selected' : '' ?>>Credit Card</option>
                                                            <option value="Cash" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Cash') ? 'selected' : '' ?>>Cash</option>
                                                            <option value="Check" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Check') ? 'selected' : '' ?>>Check</option>
                                                            <option value="PayPal" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('PayPal') ? 'selected' : '' ?>>PayPal</option>
                                                            <option value="Venmo" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Venmo') ? 'selected' : '' ?>>Venmo</option>
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <select class="w-full px-1 py-1 text-xs border border-gray-300 rounded order-field-update" data-field="shippingMethod" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                                            <option value="Customer Pickup" <?= strtolower($order['shippingMethod'] ?? 'Customer Pickup') === strtolower('Customer Pickup') ? 'selected' : '' ?>>Customer Pickup</option>
                                                            <option value="Local Delivery" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('Local Delivery') ? 'selected' : '' ?>>Local Delivery</option>
                                                            <option value="USPS" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('USPS') ? 'selected' : '' ?>>USPS</option>
                                                            <option value="FedEx" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('FedEx') ? 'selected' : '' ?>>FedEx</option>
                                                            <option value="UPS" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('UPS') ? 'selected' : '' ?>>UPS</option>
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-2">
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

                        

                        <script>
                        // Open order details modal function
                        async function openOrderDetailsModal(orderId) {try {
                                // Show the modal
                                const modal = document.getElementById('orderDetailsModal');
                                if (modal) {
                                    // Fetch order details
                                    const response = await fetch(`api/get_order.php?id=${orderId}`);
                                    const result = await response.json();
                                    
                                    if (result.success && result.order) {
                                        // Update modal content with order data
                                        updateOrderModalContent(result.order, result.items || []);
                                        modal.style.display = 'flex';
                                        document.body.style.overflow = 'hidden';
                                    } else {
                                        console.error('Failed to load order details:', result.message);
                                        if (window.showError) {
                    window.showError('Failed to load order details');
                } else {
                    alert('Failed to load order details');
                }
                                    }
                                } else {
                                    console.error('Order details modal not found');
                                }
                            } catch (error) {
                                console.error('Error opening order details modal:', error);
                                if (window.showError) {
                    window.showError('Error loading order details');
                } else {
                    alert('Error loading order details');
                }
                            }
                        }
                        
                        // Function to update order modal content
                        function updateOrderModalContent(order, items) {
                            // Update basic order information
                            document.getElementById('modal-order-id').textContent = order.id;
                            document.getElementById('modal-customer').textContent = order.username || 'N/A';
                            document.getElementById('modal-date').textContent = new Date(order.date).toLocaleDateString();
                            document.getElementById('modal-total').textContent = `$${parseFloat(order.total || 0).toFixed(2)}`;
                            document.getElementById('modal-status').textContent = order.order_status || 'Pending';
                            document.getElementById('modal-payment-method').textContent = order.paymentMethod || 'N/A';
                            document.getElementById('modal-payment-status').textContent = order.paymentStatus || 'Pending';
                            document.getElementById('modal-shipping-method').textContent = order.shippingMethod || 'N/A';
                            
                            // Update order items
                            const itemsContainer = document.getElementById('modal-order-items');
                            itemsContainer.innerHTML = '';
                            
                            if (items && items.length > 0) {
                                items.forEach(item => {
                                    const itemCard = document.createElement('div');
                                    itemCard.className = 'order-item-card';
                                    itemCard.innerHTML = `
                                        <div class="order-item-details">
                                            <div class="order-item-name">${item.item_name || item.sku}</div>
                                            <div class="order-item-sku">SKU: ${item.sku}</div>
                                            <div class="order-item-price">$${parseFloat(item.price || 0).toFixed(2)} × ${item.quantity}</div>
                                        </div>
                                        <div class="order-item-total">
                                            $${(parseFloat(item.price || 0) * parseInt(item.quantity || 0)).toFixed(2)}
                                        </div>
                                    `;
                                    itemsContainer.appendChild(itemCard);
                                });
                            } else {
                                itemsContainer.innerHTML = '<div class="text-gray-500 text-center py-4">No items found</div>';
                            }
                            
                            // Update address if available
                            const addressElement = document.getElementById('modal-address');
                            if (order.addressLine1 || order.city) {
                                let address = '';
                                if (order.addressLine1) address += order.addressLine1;
                                if (order.addressLine2) address += '\n' + order.addressLine2;
                                if (order.city) address += '\n' + order.city;
                                if (order.state) address += ', ' + order.state;
                                if (order.zipCode) address += ' ' + order.zipCode;
                                addressElement.textContent = address;
                            } else {
                                addressElement.textContent = 'No address provided';
                            }
                            
                            // Update notes
                            document.getElementById('modal-notes').textContent = order.note || 'No notes';
                            document.getElementById('modal-payment-notes').textContent = order.paynote || 'No payment notes';
                        }
                        
                        // Close order details modal
                        function closeOrderDetailsModal() {
                            const modal = document.getElementById('orderDetailsModal');
                            if (modal) {
                                modal.style.display = 'none';
                                document.body.style.overflow = '';
                            }
                        }
                        
                        // Initialize inline editing for order fulfillment
                        document.addEventListener('DOMContentLoaded', function() {
                            // Force hide any hanging progress indicators when section loads
                            hideAutoSaveIndicator();
// hideAutoSaveIndicator function moved to ui-manager.js for centralization
                            const editableFields = document.querySelectorAll('.order-field-update');
                            editableFields.forEach(field => {
                                field.addEventListener('change', async function() {
                                    const orderId = this.dataset.orderId;
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

                        <!-- Order Details Modal - Sunday Layout -->
                        <div class="modal-overlay order-modal" id="orderDetailsModal" style="display: none;">
                            <div class="modal-content order-modal-content">
                                <!-- Modal Header -->
                                <div class="modal-header">
                                    <h2 class="modal-title">Order Details: <span id="modal-order-id">#0000</span></h2>
                                    <button onclick="closeOrderDetailsModal()" class="modal-close">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Modal Body -->
                                <div class="modal-body">
                                    <div class="order-form-grid">
                                        <!-- Order Details Column -->
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

                                        <!-- Order Items Column -->
                                        <div class="order-items-column">
                                            <div class="form-section">
                                                <h3 class="form-section-title">Order Items</h3>
                                                <div class="order-items-list" id="modal-order-items">
                                                    <!-- Items will be populated dynamically -->
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
                        <!-- Reports Summary Section -->
                        <div class="space-y-2">
                            <?php 
                            $reportsStats = Database::queryRow('SELECT 
                                COUNT(*) as total_orders,
                                SUM(total) as total_revenue,
                                AVG(total) as avg_order_value
                                FROM orders WHERE DATE(date) >= CURDATE() - INTERVAL 30 DAY');
                            ?>
                            <div class="grid grid-cols-1 gap-1 mb-2">
                                <div class="bg-teal-50 p-2 rounded text-center">
                                    <div class="text-sm font-bold text-teal-600">$<?= number_format($reportsStats['total_revenue'] ?? 0, 0) ?></div>
                                    <div class="text-xs text-teal-800">30-Day Revenue</div>
                                </div>
                                <div class="bg-cyan-50 p-2 rounded text-center">
                                    <div class="text-sm font-bold text-cyan-600">$<?= number_format($reportsStats['avg_order_value'] ?? 0, 0) ?></div>
                                    <div class="text-xs text-cyan-800">Avg Order Value</div>
                                </div>
                            </div>
                            <div class="text-center pt-1">
                                <a href="/?page=admin&section=reports" class="text-teal-600 hover:text-teal-800 text-xs">View Reports →</a>
                            </div>
                        </div>
                        
                    <?php elseif ($sectionInfo['type'] === 'external'): ?>
                        <!-- External Section Placeholder -->
                        <div class="text-center text-gray-500 py-8">
                            <div class="text-3xl mb-2">🔗</div>
                            <div class="text-sm mb-2">External Section</div>
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
    <!-- Empty State -->
    <div class="text-center py-12">
        <div class="text-6xl mb-4">📊</div>
        <h3 class="text-xl font-semibold text-gray-800 mb-2">Welcome to Your Dashboard</h3>
        <p class="text-gray-600 mb-4">Configure your dashboard to see the information that matters most to you.</p>
        <button onclick="openDashboardConfig()" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
            ⚙️ Configure Dashboard
        </button>
    </div>
    <?php endif; ?>
</div>

<script>
function refreshDashboard() {
    window.location.reload();
}

function openDashboardConfig() {
    // Navigate to settings and open dashboard config modal
    window.location.href = '/?page=admin&section=settings#dashboard_config';
}

// Check if we need to open dashboard config modal
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open') === 'dashboard_config') {
        // Remove the parameter and open the modal
        const newUrl = new URL(window.location);
        newUrl.searchParams.delete('open');
        window.history.replaceState({}, '', newUrl);
        
        // Navigate to settings and open dashboard config
        window.location.href = '/?page=admin&section=settings#dashboard_config';
    }
    
    // Initialize draggable functionality
    initializeDraggableSections();
});

// Draggable sections functionality
function initializeDraggableSections() {
    const sections = document.querySelectorAll('.draggable-section');
    const grid = document.getElementById('dashboardGrid');
    
    let draggedElement = null;
    let placeholder = null;
    
    sections.forEach(section => {
        const dragHandle = section.querySelector('.drag-handle');
        
        dragHandle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            draggedElement = section;
            
            // Create placeholder
            placeholder = document.createElement('div');
            placeholder.className = 'dashboard-section bg-gray-100 border-2 border-dashed border-gray-300 opacity-50';
            placeholder.style.height = section.offsetHeight + 'px';
            placeholder.textContent = 'Drop here';
            placeholder.style.display = 'flex';
            placeholder.style.alignItems = 'center';
            placeholder.style.justifyContent = 'center';
            placeholder.style.color = '#9ca3af';
            
            section.classList.add('dragging');
            section.style.position = 'fixed';
            section.style.pointerEvents = 'none';
            section.style.zIndex = '1000';
            section.style.width = section.offsetWidth + 'px';
            
            // Insert placeholder
            section.parentNode.insertBefore(placeholder, section.nextSibling);
            
            document.addEventListener('mousemove', handleMouseMove);
            document.addEventListener('mouseup', handleMouseUp);
        });
    });
    
    function handleMouseMove(e) {
        if (!draggedElement) return;
        
        draggedElement.style.left = (e.clientX - draggedElement.offsetWidth / 2) + 'px';
        draggedElement.style.top = (e.clientY - 20) + 'px';
        
        // Find drop target
        const elementsBelow = document.elementsFromPoint(e.clientX, e.clientY);
        const dropTarget = elementsBelow.find(el => 
            el.classList.contains('draggable-section') && el !== draggedElement
        );
        
        // Remove previous drag-over classes
        sections.forEach(s => s.classList.remove('drag-over'));
        
        if (dropTarget) {
            dropTarget.classList.add('drag-over');
            
            // Determine if we should insert before or after
            const rect = dropTarget.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            
            if (e.clientY < midY) {
                dropTarget.parentNode.insertBefore(placeholder, dropTarget);
            } else {
                dropTarget.parentNode.insertBefore(placeholder, dropTarget.nextSibling);
            }
        }
    }
    
    function handleMouseUp(e) {
        if (!draggedElement) return;
        
        // Reset styles
        draggedElement.classList.remove('dragging');
        draggedElement.style.position = '';
        draggedElement.style.pointerEvents = '';
        draggedElement.style.zIndex = '';
        draggedElement.style.width = '';
        draggedElement.style.left = '';
        draggedElement.style.top = '';
        
        // Remove drag-over classes
        sections.forEach(s => s.classList.remove('drag-over'));
        
        // Replace placeholder with dragged element
        if (placeholder && placeholder.parentNode) {
            placeholder.parentNode.insertBefore(draggedElement, placeholder);
            placeholder.remove();
            
            // Save new order
            saveDashboardOrder();
        }
        
        // Cleanup
        draggedElement = null;
        placeholder = null;
        
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);
    }
}

async function saveDashboardOrder() {
    try {
        const sections = Array.from(document.querySelectorAll('.draggable-section'));
        const newOrder = sections.map((section, index) => ({
            section_key: section.dataset.sectionKey,
            display_order: index + 1
        }));
        
        const response = await fetch('/api/dashboard_sections.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'reorder_sections',
                sections: newOrder,
                admin_token: 'whimsical_admin_2024'
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to save order');
        }
        
        // Show success indicator
        showOrderSaveSuccess();
        
    } catch (error) {
        console.error('Error saving dashboard order:', error);
        // Reload page to reset order
        location.reload();
    }
}

function showOrderSaveSuccess() {
    const indicator = document.createElement('div');
    indicator.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
    indicator.textContent = '✅ Dashboard order saved';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.remove();
    }, 2000);
}
</script>



 
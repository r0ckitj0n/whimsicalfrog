<?php
// Admin Dashboard - Configurable widget-based dashboard
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';

try {
    $db = Database::getInstance();
    
    // Get dashboard configuration
    $dashboardConfig = $db->query('SELECT * FROM dashboard_sections WHERE is_active = 1 ORDER BY display_order ASC')->fetchAll();
    
    // Fetch core metrics
    $totalItems = $db->query('SELECT COUNT(*) as count FROM items')->fetch()['count'] ?? 0;
    $totalOrders = $db->query('SELECT COUNT(*) as count FROM orders')->fetch()['count'] ?? 0;
    $totalCustomers = $db->query('SELECT COUNT(*) as count FROM users WHERE role != "admin"')->fetch()['count'] ?? 0;
    $totalRevenue = $db->query('SELECT SUM(total) as revenue FROM orders')->fetch()['revenue'] ?? 0;
    
    // Get recent activity
    $recentOrders = $db->query('SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.userId = u.id ORDER BY o.created_at DESC LIMIT 5')->fetchAll();
    $lowStockItems = $db->query('SELECT * FROM items WHERE stockLevel <= reorderPoint AND stockLevel > 0 ORDER BY stockLevel ASC LIMIT 5')->fetchAll();
    
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
    <!-- Dashboard Header -->
    <div class="dashboard-header mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">📊 Dashboard Overview</h1>
                <p class="text-gray-600">Your business at a glance - key metrics and recent activity</p>
            </div>
            <div class="flex gap-2">
                <button onclick="refreshDashboard()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                    🔄 Refresh
                </button>
                <button onclick="openDashboardConfig()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                    ⚙️ Configure Dashboard
                </button>
            </div>
        </div>
    </div>

    <!-- Dashboard Sections -->
    <div class="dashboard-grid space-y-6">
        <?php foreach ($dashboardConfig as $config): ?>
            <?php 
            $sectionInfo = $availableSections[$config['section_key']] ?? null;
            if (!$sectionInfo) continue;
            ?>
            
            <div class="dashboard-section bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                <!-- Always show title and description for dashboard sections -->
                <div class="section-header p-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">
                        <?= htmlspecialchars(($config['custom_title'] ?? '') ?: $sectionInfo['title']) ?>
                    </h3>
                    <p class="text-sm text-gray-600">
                        <?= htmlspecialchars(($config['custom_description'] ?? '') ?: $sectionInfo['description']) ?>
                    </p>
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
});
</script>

<style>
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

.dashboard-header {
    margin-bottom: 1.5rem;
    padding: 1.5rem;
    background: white;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.dashboard-grid {
    min-height: 200px;
}

.dashboard-section {
    background: white;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: box-shadow 0.2s ease;
    overflow: hidden;
    min-height: 200px;
    display: flex;
    flex-direction: column;
}

.dashboard-section:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.section-header {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    background: #f9fafb;
}

.section-content {
    padding: 1rem;
    flex: 1;
}

.metric-card {
    text-align: center;
    transition: transform 0.2s ease;
}

.metric-card:hover {
    transform: translateY(-2px);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .dashboard-header {
        padding: 1rem;
    }
    
    .section-content {
        padding: 0.75rem;
    }
    
    /* Stack metrics vertically on mobile */
    .dashboard-section .grid.grid-cols-2.md\\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Ensure no horizontal scroll */
.dashboard-grid,
.dashboard-section,
.section-content,
.metric-card {
    max-width: 100%;
    overflow-x: hidden;
}

/* Button styling */
.dashboard-section a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
}

.dashboard-section a:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

/* Ensure dashboard modals have titles and descriptions */
.modal-overlay.dashboard-modal .admin-modal-content .admin-modal-header {
    padding-bottom: 1rem;
}

.modal-overlay.dashboard-modal .admin-modal-header .modal-title {
    margin-bottom: 0.5rem;
}

.modal-overlay.dashboard-modal .admin-modal-header .modal-description {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.9);
    font-weight: normal;
}
</style> 
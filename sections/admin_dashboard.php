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
    $recentOrders = $db->query('SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.userId = u.id ORDER BY o.date DESC LIMIT 5')->fetchAll();
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
                                                         $inventoryStats = $db->query('SELECT 
                                 COUNT(*) as total_items,
                                 COUNT(CASE WHEN stockLevel <= reorderPoint AND stockLevel > 0 THEN 1 END) as low_stock,
                                 COUNT(CASE WHEN stockLevel = 0 THEN 1 END) as out_of_stock,
                                 SUM(stockLevel * COALESCE(costPrice, 0)) as inventory_value
                                 FROM items')->fetch();
                            $topItems = $db->query('SELECT name, sku, stockLevel, reorderPoint FROM items ORDER BY stockLevel DESC LIMIT 3')->fetchAll();
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
                                                         $customerStats = $db->query('SELECT 
                                 COUNT(*) as total_customers
                                 FROM users WHERE role != \'admin\'')->fetch();
                             $recentCustomers = $db->query('SELECT username, email FROM users WHERE role != \'admin\' ORDER BY id DESC LIMIT 3')->fetchAll();
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
                            $marketingStats = $db->query('SELECT 
                                (SELECT COUNT(*) FROM email_campaigns) as email_campaigns,
                                (SELECT COUNT(*) FROM discount_codes WHERE (end_date IS NULL OR end_date >= CURDATE())) as active_discounts,
                                (SELECT COUNT(*) FROM social_posts WHERE scheduled_date >= CURDATE()) as scheduled_posts
                            ')->fetch();
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
                        <!-- Order Fulfillment Section -->
                        <div class="space-y-3">
                            <?php 
                            $fulfillmentStats = $db->query('SELECT 
                                COUNT(CASE WHEN status = \'Processing\' THEN 1 END) as processing,
                                COUNT(CASE WHEN status = \'Shipped\' THEN 1 END) as shipped,
                                COUNT(CASE WHEN status = \'Delivered\' THEN 1 END) as delivered
                                FROM orders WHERE DATE(date) >= CURDATE() - INTERVAL 30 DAY')->fetch();
                            $urgentOrders = $db->query('SELECT id, total, date, status FROM orders WHERE status = \'Processing\' ORDER BY date ASC LIMIT 3')->fetchAll();
                            ?>
                            <div class="grid grid-cols-1 gap-2 mb-4">
                                <div class="bg-yellow-50 p-3 rounded text-center">
                                    <div class="text-lg font-bold text-yellow-600"><?= $fulfillmentStats['processing'] ?? 0 ?></div>
                                    <div class="text-xs text-yellow-800">Processing Orders</div>
                                </div>
                            </div>
                            <?php if (!empty($urgentOrders)): ?>
                                <div class="text-xs font-medium text-gray-600 mb-2">Urgent Orders:</div>
                                <?php foreach ($urgentOrders as $order): ?>
                                <div class="flex justify-between items-center text-xs p-2 bg-yellow-50 rounded">
                                    <span>#<?= htmlspecialchars($order['id'] ?? '') ?></span>
                                    <span class="font-medium">$<?= number_format($order['total'] ?? 0, 2) ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="text-center pt-2">
                                <a href="/?page=admin&section=order_fulfillment" class="text-yellow-600 hover:text-yellow-800 text-sm">Process Orders →</a>
                            </div>
                        </div>
                        
                    <?php elseif ($config['section_key'] === 'reports_summary'): ?>
                        <!-- Reports Summary Section -->
                        <div class="space-y-3">
                            <?php 
                            $reportsStats = $db->query('SELECT 
                                COUNT(*) as total_orders,
                                SUM(total) as total_revenue,
                                AVG(total) as avg_order_value
                                FROM orders WHERE DATE(date) >= CURDATE() - INTERVAL 30 DAY')->fetch();
                            ?>
                            <div class="grid grid-cols-1 gap-2 mb-4">
                                <div class="bg-teal-50 p-3 rounded text-center">
                                    <div class="text-lg font-bold text-teal-600">$<?= number_format($reportsStats['total_revenue'] ?? 0, 0) ?></div>
                                    <div class="text-xs text-teal-800">30-Day Revenue</div>
                                </div>
                                <div class="bg-cyan-50 p-3 rounded text-center">
                                    <div class="text-lg font-bold text-cyan-600">$<?= number_format($reportsStats['avg_order_value'] ?? 0, 0) ?></div>
                                    <div class="text-xs text-cyan-800">Avg Order Value</div>
                                </div>
                            </div>
                            <div class="text-center pt-2">
                                <a href="/?page=admin&section=reports" class="text-teal-600 hover:text-teal-800 text-sm">View Reports →</a>
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

<style>
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}



.dashboard-grid {
    min-height: 200px;
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 1.5rem;
    grid-auto-rows: min-content;
}

.dashboard-section {
    background: white;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: box-shadow 0.2s ease, transform 0.2s ease;
    overflow: hidden;
    min-height: 200px;
    display: flex;
    flex-direction: column;
}

.dashboard-section:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dashboard-section.full-width {
    grid-column: span 12;
}

.dashboard-section.half-width {
    grid-column: span 6;
}

.dashboard-section.third-width {
    grid-column: span 4;
}

.dashboard-section.dragging {
    opacity: 0.7;
    transform: rotate(3deg);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    z-index: 1000;
}

.dashboard-section.drag-over {
    border: 2px dashed #3b82f6;
    background: #eff6ff;
}

.drag-handle {
    transition: all 0.2s ease;
}

.drag-handle:hover {
    transform: scale(1.1);
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
    
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .dashboard-section.full-width,
    .dashboard-section.half-width,
    .dashboard-section.third-width {
        grid-column: span 12;
    }
    
    .section-content {
        padding: 0.75rem;
    }
    
    /* Stack metrics vertically on mobile */
    .dashboard-section .grid.grid-cols-2.md\\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    /* Hide drag handles on mobile for better touch experience */
    .drag-handle {
        display: none;
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

/* Enhanced dashboard modal styling */
.modal-overlay.dashboard-modal .admin-modal-content {
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-overlay.dashboard-modal .admin-modal-content .admin-modal-header {
    padding: 1.5rem;
    padding-bottom: 1rem;
}

.modal-overlay.dashboard-modal .admin-modal-header .modal-title {
    margin-bottom: 0.5rem;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-overlay.dashboard-modal .admin-modal-header .modal-description {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.9);
    font-weight: normal;
    line-height: 1.5;
}

.modal-overlay.dashboard-modal .modal-body {
    padding: 0 1.5rem 1.5rem;
}

/* Improve section cards in dashboard modal */
.modal-overlay.dashboard-modal .grid > div {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.modal-overlay.dashboard-modal .grid > div:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

/* Better spacing for current sections */
.modal-overlay.dashboard-modal #currentSectionsList > div {
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    background: #f9fafb;
}
</style> 
<?php
// Admin Reports - Business analytics and insights
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

require_once __DIR__ . '/../includes/functions.php';

// Initialize data arrays
$ordersData = [];
$customersData = [];
$inventoryData = [];

try {
    $db = Database::getInstance();
    
    // Fetch all data with optimized queries
    $ordersData = $db->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();
    $customersData = $db->query('SELECT * FROM users WHERE role != "admin"')->fetchAll();
    $inventoryData = $db->query('SELECT * FROM items')->fetchAll();
    
    // Enhance orders with items and shipping data
    foreach ($ordersData as &$order) {
        $order['items'] = $db->query('SELECT oi.*, i.name as item_name FROM order_items oi 
                                    LEFT JOIN items i ON oi.sku = i.sku 
                                    WHERE oi.orderId = ?', [$order['id']])->fetchAll();
        
        // Parse JSON shipping address
        if (isset($order['shippingAddress']) && is_string($order['shippingAddress'])) {
            $order['shippingAddress'] = json_decode($order['shippingAddress'], true);
        }
    }
    
} catch (Exception $e) {
    Logger::error('Admin Reports Database Error: ' . $e->getMessage());
    $ordersData = $customersData = $inventoryData = [];
}

// Date filtering
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$startParam = $startDate ?: '1900-01-01';
$endParam = $endDate ?: '2100-12-31';

// Calculate YTD and filtered metrics
$ordersYTD = array_filter($ordersData, function($order) {
    $dt = $order['date'] ?? $order['orderDate'] ?? $order['created_at'] ?? null;
    return $dt && date('Y', strtotime($dt)) == date('Y');
});

$filteredOrders = array_filter($ordersData, function($order) use ($startParam, $endParam) {
    $orderDate = $order['orderDate'] ?? $order['date'] ?? $order['created_at'] ?? '';
    $orderDate = $orderDate ? date('Y-m-d', strtotime($orderDate)) : '';
    return $orderDate >= $startParam && $orderDate <= $endParam;
});

// Calculate comprehensive metrics
$metrics = [
    'totalRevenue' => array_sum(array_map(fn($o) => floatval($o['totalAmount'] ?? $o['total'] ?? 0), $ordersYTD)),
    'filteredRevenue' => array_sum(array_map(fn($o) => floatval($o['totalAmount'] ?? $o['total'] ?? 0), $filteredOrders)),
    'totalOrders' => count($ordersYTD),
    'filteredOrderCount' => count($filteredOrders),
    'totalCustomers' => count($customersData),
    'averageOrderValue' => 0
];

$metrics['averageOrderValue'] = $metrics['totalOrders'] > 0 ? $metrics['totalRevenue'] / $metrics['totalOrders'] : 0;

// Payment analysis
$paymentStats = ['status' => [], 'method' => []];
foreach ($filteredOrders as $order) {
    $status = $order['paymentStatus'] ?? 'Unknown';
    $method = $order['paymentMethod'] ?? 'Unknown';
    $paymentStats['status'][$status] = ($paymentStats['status'][$status] ?? 0) + 1;
    $paymentStats['method'][$method] = ($paymentStats['method'][$method] ?? 0) + 1;
}

$paymentsReceived = $paymentStats['status']['Received'] ?? 0;
$paymentsPending = $paymentStats['status']['Pending'] ?? 0;

// Chart data preparation
$ordersByDate = [];
foreach ($filteredOrders as $order) {
    $orderDate = $order['orderDate'] ?? $order['date'] ?? $order['created_at'] ?? '';
    $orderDate = $orderDate ? date('Y-m-d', strtotime($orderDate)) : '';
    if ($orderDate) {
        if (!isset($ordersByDate[$orderDate])) {
            $ordersByDate[$orderDate] = ['count' => 0, 'revenue' => 0];
        }
        $ordersByDate[$orderDate]['count']++;
        $ordersByDate[$orderDate]['revenue'] += floatval($order['totalAmount'] ?? $order['total'] ?? 0);
    }
}
ksort($ordersByDate);

// Product sales analysis
$productSales = [];
foreach ($ordersData as $order) {
    foreach ($order['items'] ?? [] as $item) {
        $sku = $item['sku'] ?? $item['productId'] ?? '';
        if ($sku) {
            if (!isset($productSales[$sku])) {
                $productSales[$sku] = ['quantity' => 0, 'revenue' => 0, 'name' => $item['item_name'] ?? 'Unknown'];
            }
            $productSales[$sku]['quantity'] += intval($item['quantity'] ?? 0);
            $productSales[$sku]['revenue'] += floatval($item['price'] ?? 0) * intval($item['quantity'] ?? 0);
        }
    }
}

uasort($productSales, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
$topProducts = array_slice($productSales, 0, 5, true);

// Low stock analysis
$lowStockProducts = array_filter($inventoryData, fn($item) => 
    isset($item['stockLevel'], $item['reorderPoint']) && 
    intval($item['stockLevel']) <= intval($item['reorderPoint'])
);

// Prepare JSON data for charts
$chartData = [
    'labels' => json_encode(array_keys($ordersByDate)),
    'orders' => json_encode(array_column($ordersByDate, 'count')),
    'revenue' => json_encode(array_column($ordersByDate, 'revenue')),
    'paymentLabels' => json_encode(array_keys($paymentStats['method'])),
    'paymentCounts' => json_encode(array_values($paymentStats['method']))
];
?>

<div class="admin-content-container">
    <div class="admin-filter-section">
        <form action="" method="GET" class="admin-filter-form">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="reports">
            
            <input type="date" name="start_date" id="start_date" 
                   value="<?= htmlspecialchars($startDate) ?>" class="admin-form-input">
            
            <input type="date" name="end_date" id="end_date" 
                   value="<?= htmlspecialchars($endDate) ?>" class="admin-form-input">
            
            <button type="submit" class="btn-primary admin-filter-button">Apply Filter</button>
            <a href="?page=admin&section=reports" class="btn-secondary admin-filter-button">Clear</a>
        </form>
    </div>

    <div class="admin-table-section">
        <!-- Key Metrics Dashboard -->
        <div class="metrics-grid">
        <div class="metric-card success">
            <div class="metric-label">Total Revenue</div>
            <div class="metric-value">$<?= number_format($metrics['filteredRevenue'], 2) ?></div>
            <div class="metric-meta">For selected period</div>
        </div>
        
        <div class="metric-card primary">
            <div class="metric-label">Orders</div>
            <div class="metric-value"><?= $metrics['filteredOrderCount'] ?></div>
            <div class="metric-meta">For selected period</div>
        </div>
        
        <div class="metric-card secondary">
            <div class="metric-label">Average Order Value</div>
            <div class="metric-value">$<?= number_format($metrics['averageOrderValue'], 2) ?></div>
            <div class="metric-meta">All time average</div>
        </div>
        
        <div class="metric-card warning">
            <div class="metric-label">Total Customers</div>
            <div class="metric-value"><?= $metrics['totalCustomers'] ?></div>
            <div class="metric-meta">All time</div>
        </div>
        
        <div class="metric-card success">
            <div class="metric-label">Payments Received</div>
            <div class="metric-value"><?= $paymentsReceived ?></div>
            <div class="metric-meta">Selected period</div>
        </div>
        
        <div class="metric-card danger">
            <div class="metric-label">Payments Pending</div>
            <div class="metric-value"><?= $paymentsPending ?></div>
            <div class="metric-meta">Selected period</div>
        </div>
    </div>

    <!-- Sales Over Time Chart -->
    <div class="admin-card">
        <h3 class="admin-card-title">📈 Sales Performance Over Time</h3>
        <div class="chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Payment Method Distribution -->
    <div class="admin-card">
        <h3 class="admin-card-title">💳 Payment Method Distribution</h3>
        <div class="chart-container">
            <canvas id="paymentMethodChart"></canvas>
        </div>
    </div>

    <!-- Top Products Table -->
    <div class="admin-card">
        <h3 class="admin-card-title">🏆 Top Selling Products</h3>
        <?php if (empty($topProducts)): ?>
            <div class="admin-empty-state">
                <div class="empty-icon">📊</div>
                <div class="empty-title">No Sales Data</div>
                <div class="empty-subtitle">No product sales data available for the selected period.</div>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $sku => $data): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($data['name']) ?></td>
                                <td><?= $data['quantity'] ?></td>
                                <td class="font-medium">$<?= number_format($data['revenue'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Low Stock Alerts -->
    <div class="admin-card">
        <h3 class="admin-card-title">⚠️ Inventory Alerts</h3>
        <?php if (empty($lowStockProducts)): ?>
            <div class="admin-empty-state">
                <div class="empty-icon">✅</div>
                <div class="empty-title">All Good!</div>
                <div class="empty-subtitle">No low stock alerts at this time.</div>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Current Stock</th>
                            <th>Reorder Point</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockProducts as $product): 
                            $stockLevel = intval($product['stockLevel'] ?? 0);
                            $reorderPoint = intval($product['reorderPoint'] ?? 0);
                            $status = $stockLevel === 0 ? 'Out of Stock' : 'Low Stock';
                            $statusClass = $stockLevel === 0 ? 'danger' : 'warning';
                        ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($product['name'] ?? 'Unknown') ?></td>
                                <td><?= $stockLevel ?></td>
                                <td><?= $reorderPoint ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= $status ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Print Action -->
    <div class="report-actions">
        <button type="button" class="btn-secondary" onclick="window.print()">
            <svg class="btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print Report
        </button>
    </div>
    </div>
</div>

<!-- Chart.js Integration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sales Performance Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: <?= $chartData['labels'] ?>,
            datasets: [
                {
                    label: 'Orders',
                    data: <?= $chartData['orders'] ?>,
                    backgroundColor: 'rgba(135, 172, 58, 0.2)',
                    borderColor: 'rgba(135, 172, 58, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    yAxisID: 'y'
                },
                {
                    label: 'Revenue ($)',
                    data: <?= $chartData['revenue'] ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: { display: true, text: 'Orders' }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: { display: true, text: 'Revenue ($)' },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });

    // Payment Method Distribution Chart
    const paymentCtx = document.getElementById('paymentMethodChart').getContext('2d');
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: <?= $chartData['paymentLabels'] ?>,
            datasets: [{
                data: <?= $chartData['paymentCounts'] ?>,
                backgroundColor: [
                    'rgba(135, 172, 58, 0.8)',   // WhimsicalFrog Green
                    'rgba(75, 85, 99, 0.8)',     // Gray
                    'rgba(16, 185, 129, 0.8)',   // Emerald
                    'rgba(245, 158, 11, 0.8)',   // Amber
                    'rgba(239, 68, 68, 0.8)',    // Red
                    'rgba(99, 102, 241, 0.8)'    // Indigo
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});
</script>

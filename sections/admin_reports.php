<?php
// Admin Reports
// This page provides analytics and reporting tools for business insights

// Prevent direct access
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// Include database configuration - using relative path
require_once 'api/config.php';

// Initialize arrays to prevent null values
$ordersData = [];
$customersData = [];
$inventoryData = [];
$productsData = [];

try {
    // Create a PDO connection
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if orders table exists
    $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    $ordersTableExists = $tableCheckStmt->rowCount() > 0;
    
    if ($ordersTableExists) {
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
    }
    
    // Fetch customers/users data directly from database
    $usersStmt = $pdo->query('SELECT * FROM users');
    $customersData = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch inventory data directly from database
    $inventoryStmt = $pdo->query('SELECT * FROM items');
    $inventoryData = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch products data directly from database
    $productsStmt = $pdo->query('SELECT * FROM items');
    $productsData = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the connection
    $pdo = null;
} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log('General Error: ' . $e->getMessage());
}

// Ensure arrays are never null
$ordersData = is_array($ordersData) ? $ordersData : [];
$customersData = is_array($customersData) ? $customersData : [];
$inventoryData = is_array($inventoryData) ? $inventoryData : [];
$productsData = is_array($productsData) ? $productsData : [];

// Calculate metrics for reports
$ordersYTD = array_filter($ordersData, function($order){
    $dt = $order['date'] ?? $order['orderDate'] ?? null;
    return $dt && date('Y', strtotime($dt)) == date('Y');
});

// Calculate metrics for reports (YTD)
$totalRevenue = 0;
$totalOrders = count($ordersYTD);
$totalCustomers = count($customersData);
$totalProducts = count($productsData);

// Calculate revenue - using totalAmount instead of total
foreach ($ordersYTD as $order) {
    $totalRevenue += floatval($order['totalAmount'] ?? $order['total'] ?? 0);
}

// Get date range for filtering
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$startParam = $startDate === '' ? '1900-01-01' : $startDate;
$endParam = $endDate === '' ? '2100-12-31' : $endDate;

// Filter orders by date range - using orderDate instead of date
$filteredOrders = array_filter($ordersData, function($order) use ($startParam, $endParam) {
    $orderDate = isset($order['orderDate']) ? date('Y-m-d', strtotime($order['orderDate'])) : 
                (isset($order['date']) ? date('Y-m-d', strtotime($order['date'])) : '');
    return $orderDate >= $startParam && $orderDate <= $endParam;
});

// Calculate metrics for filtered orders
$filteredRevenue = 0;
foreach ($filteredOrders as $order) {
    $filteredRevenue += floatval($order['totalAmount'] ?? $order['total'] ?? 0);
}

// Payment status & method breakdown for selected range
$paymentStatusCounts = [];
$paymentMethodCounts = [];
foreach ($filteredOrders as $order) {
    $status = $order['paymentStatus'] ?? 'Unknown';
    $method = $order['paymentMethod'] ?? 'Unknown';
    $paymentStatusCounts[$status] = ($paymentStatusCounts[$status] ?? 0) + 1;
    $paymentMethodCounts[$method] = ($paymentMethodCounts[$method] ?? 0) + 1;
}

$paymentsReceived = $paymentStatusCounts['Received'] ?? 0;
$paymentsPending  = $paymentStatusCounts['Pending']  ?? 0;

// Prepare data for Chart.js
$paymentMethodLabelsJson = json_encode(array_keys($paymentMethodCounts));
$paymentMethodCountsJson = json_encode(array_values($paymentMethodCounts));

// Calculate average order value
$averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Group orders by date for chart data
$ordersByDate = [];
foreach ($filteredOrders as $order) {
    $orderDate = isset($order['orderDate']) ? date('Y-m-d', strtotime($order['orderDate'])) : 
                (isset($order['date']) ? date('Y-m-d', strtotime($order['date'])) : '');
    if (!isset($ordersByDate[$orderDate])) {
        $ordersByDate[$orderDate] = [
            'count' => 0,
            'revenue' => 0
        ];
    }
    $ordersByDate[$orderDate]['count']++;
    $ordersByDate[$orderDate]['revenue'] += floatval($order['totalAmount'] ?? $order['total'] ?? 0);
}

// Sort by date
ksort($ordersByDate);

// Prepare chart data
$chartLabels = [];
$chartData = [];
$chartRevenue = [];

foreach ($ordersByDate as $date => $data) {
    $chartLabels[] = $date;
    $chartData[] = $data['count'];
    $chartRevenue[] = $data['revenue'];
}

// Convert to JSON for chart.js
$chartLabelsJson = json_encode($chartLabels);
$chartDataJson = json_encode($chartData);
$chartRevenueJson = json_encode($chartRevenue);

// Get top products
$productSales = [];
foreach ($ordersData as $order) {
    foreach ($order['items'] ?? [] as $item) {
        $productId = $item['productId'] ?? '';
        if (!isset($productSales[$productId])) {
            $productSales[$productId] = [
                'quantity' => 0,
                'revenue' => 0
            ];
        }
        $productSales[$productId]['quantity'] += intval($item['quantity'] ?? 0);
        $productSales[$productId]['revenue'] += floatval($item['price'] ?? 0) * intval($item['quantity'] ?? 0);
    }
}

// Sort by revenue
uasort($productSales, function($a, $b) {
    return $b['revenue'] <=> $a['revenue'];
});

// Get top 5 products
$topProducts = array_slice($productSales, 0, 5, true);

// Get product names
$productNames = [];
foreach ($productsData as $product) {
    $productId = $product['id'] ?? '';
    $productNames[$productId] = $product['name'] ?? 'Unknown Product';
}

// Get low stock products
$lowStockProducts = array_filter($inventoryData, function($product) {
    return isset($product['stockLevel']) && isset($product['reorderPoint']) && 
           intval($product['stockLevel']) <= intval($product['reorderPoint']);
});
?>

<style>
  .admin-data-label {
    color: #222 !important;
  }
  .admin-data-value {
    color: #c00 !important;
    font-weight: bold;
  }
  .report-card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
  }
  .report-card-title {
    font-size: 1.125rem;
    font-weight: 500;
    color: #111827;
    margin-bottom: 1rem;
  }
  .metric-card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    padding: 1rem;
  }
  .metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
  }
  .metric-label {
    font-size: 0.875rem;
    color: #6B7280;
  }
  .chart-container {
    position: relative;
    height: 300px;
    width: 100%;
  }
  
  /* Stats section with explicit white background */
  .stats-section {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
    background-color: white;
    padding: 1rem;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
  }
  
  @media (min-width: 768px) {
    .stats-section {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
  }
</style>

<!-- Top bar: Date Range Selector -->
<div class="mb-4 flex justify-start">
    <form action="" method="GET" class="flex flex-row items-center gap-2 mb-0">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="reports">
        <div class="flex items-center">
            <label for="start_date" class="filter-label block mr-2">From:</label>
            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="block px-2 py-1 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div class="flex items-center">
            <label for="end_date" class="filter-label block mr-2">To:</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="block px-2 py-1 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <button type="submit" class="px-3 py-1 rounded bg-[#87ac3a] text-white hover:bg-[#a3cc4a] transition">
            Apply
        </button>
                        <a href="?page=admin&section=reports" class="filter-label underline ml-2">Clear</a>
    </form>
</div>

<!-- Key Metrics Cards - Using the new stats-section class for white background -->
<div class="stats-section">
    <div class="metric-card">
        <div class="metric-label">Total Revenue</div>
        <div class="metric-value text-green-600">$<?php echo number_format($filteredRevenue, 2); ?></div>
        <div class="text-xs text-gray-500">For selected period</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Orders</div>
        <div class="metric-value text-blue-600"><?php echo count($filteredOrders); ?></div>
        <div class="text-xs text-gray-500">For selected period</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Average Order Value</div>
        <div class="metric-value text-purple-600">$<?php echo number_format($averageOrderValue, 2); ?></div>
        <div class="text-xs text-gray-500">All time</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Total Customers</div>
        <div class="metric-value text-yellow-600"><?php echo $totalCustomers; ?></div>
        <div class="text-xs text-gray-500">All time</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Payments Received</div>
        <div class="metric-value text-green-600"><?php echo $paymentsReceived; ?></div>
        <div class="text-xs text-gray-500">Selected period</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Payments Pending</div>
        <div class="metric-value text-red-600"><?php echo $paymentsPending; ?></div>
        <div class="text-xs text-gray-500">Selected period</div>
    </div>
</div>

<!-- Sales Over Time Chart -->
<div class="report-card">
    <h3 class="report-card-title">Sales Over Time</h3>
    <div class="chart-container">
        <canvas id="salesChart"></canvas>
    </div>
</div>

<!-- Payment Method Chart -->
<div class="report-card">
    <h3 class="report-card-title">Payment Method Distribution</h3>
    <div class="chart-container" style="height:300px;">
        <canvas id="paymentMethodChart"></canvas>
    </div>
</div>

<!-- Top Products -->
<div class="report-card">
    <h3 class="report-card-title">Top Selling Products</h3>
    <?php if (empty($topProducts)): ?>
        <p class="text-gray-500 italic">No product sales data available for the selected period.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity Sold</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($topProducts as $productId => $data): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($productNames[$productId] ?? "Product ID: $productId"); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $data['quantity']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                $<?php echo number_format($data['revenue'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Low Stock Alerts -->
<div class="report-card">
    <h3 class="report-card-title">Inventory Alerts</h3>
    <?php if (empty($lowStockProducts)): ?>
        <p class="text-gray-500 italic">No low stock alerts at this time.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reorder Point</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($lowStockProducts as $product): 
                        $stockLevel = intval($product['stockLevel'] ?? 0);
                        $reorderPoint = intval($product['reorderPoint'] ?? 0);
                        $status = $stockLevel === 0 ? 'Out of Stock' : 'Low Stock';
                        $statusColor = $stockLevel === 0 ? 'red' : 'yellow';
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($product['name'] ?? "Unknown Product"); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $stockLevel; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $reorderPoint; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js for Sales Chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $chartLabelsJson; ?>,
            datasets: [
                {
                    label: 'Orders',
                    data: <?php echo $chartDataJson; ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    yAxisID: 'y'
                },
                {
                    label: 'Revenue',
                    data: <?php echo $chartRevenueJson; ?>,
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
                    title: {
                        display: true,
                        text: 'Orders'
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Revenue ($)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Payment method doughnut chart
    const pCtx = document.getElementById('paymentMethodChart').getContext('2d');
    const paymentChart = new Chart(pCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo $paymentMethodLabelsJson; ?>,
            datasets: [{
                data: <?php echo $paymentMethodCountsJson; ?>,
                backgroundColor: ['#87ac3a','#4B5563','#10B981','#F59E0B','#EF4444','#6366F1']
            }]
        },
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}} }
    });
});
</script>

<!-- Bottom action bar -->
<div class="mt-6 flex justify-end">
    <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
        Print Report
    </button>
</div>

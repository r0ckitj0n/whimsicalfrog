<?php
// Admin Reports & Analytics
// This page provides comprehensive reports and analytics for business insights

// Prevent direct access
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// Fetch data for reports
$ordersData = fetchData('sales_orders') ?? [];
$customersData = fetchData('users') ?? [];
$inventoryData = fetchData('inventory') ?? [];

// Get time period filter
$timePeriod = isset($_GET['time_period']) ? $_GET['time_period'] : 'month';
$customStartDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$customEndDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Calculate date ranges based on selected time period
$endDate = date('Y-m-d');
$startDate = '';

switch ($timePeriod) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'quarter':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-365 days'));
        break;
    case 'custom':
        $startDate = !empty($customStartDate) ? $customStartDate : date('Y-m-d', strtotime('-30 days'));
        $endDate = !empty($customEndDate) ? $customEndDate : date('Y-m-d');
        break;
}

// Filter orders by date range
$filteredOrders = array_filter($ordersData, function($order) use ($startDate, $endDate) {
    if (!isset($order['date'])) return false;
    $orderDate = date('Y-m-d', strtotime($order['date']));
    return $orderDate >= $startDate && $orderDate <= $endDate;
});

// Calculate key metrics
$totalRevenue = 0;
$totalOrders = count($filteredOrders);
$totalProductsSold = 0;
$productSales = [];
$dailySales = [];
$categorySales = [];
$customerSales = [];
$regionSales = [];

// Initialize daily sales array with zeros for all dates in range
$currentDate = new DateTime($startDate);
$lastDate = new DateTime($endDate);
while ($currentDate <= $lastDate) {
    $dateKey = $currentDate->format('Y-m-d');
    $dailySales[$dateKey] = 0;
    $currentDate->modify('+1 day');
}

// Process orders to calculate metrics
foreach ($filteredOrders as $order) {
    $orderTotal = floatval($order['total'] ?? 0);
    $totalRevenue += $orderTotal;
    
    // Daily sales
    $orderDate = date('Y-m-d', strtotime($order['date']));
    if (isset($dailySales[$orderDate])) {
        $dailySales[$orderDate] += $orderTotal;
    }
    
    // Process order items
    $items = $order['items'] ?? [];
    foreach ($items as $item) {
        $productId = $item['productId'] ?? '';
        $quantity = intval($item['quantity'] ?? 0);
        $price = floatval($item['price'] ?? 0);
        $totalProductsSold += $quantity;
        
        // Product sales
        if (!isset($productSales[$productId])) {
            $productSales[$productId] = [
                'quantity' => 0,
                'revenue' => 0,
                'name' => ''
            ];
        }
        $productSales[$productId]['quantity'] += $quantity;
        $productSales[$productId]['revenue'] += $quantity * $price;
        
        // Get product name and category
        foreach ($inventoryData as $product) {
            if (($product['id'] ?? '') == $productId) {
                $productSales[$productId]['name'] = $product['name'] ?? 'Unknown Product';
                $category = $product['category'] ?? 'Uncategorized';
                
                // Category sales
                if (!isset($categorySales[$category])) {
                    $categorySales[$category] = [
                        'quantity' => 0,
                        'revenue' => 0
                    ];
                }
                $categorySales[$category]['quantity'] += $quantity;
                $categorySales[$category]['revenue'] += $quantity * $price;
                break;
            }
        }
    }
    
    // Customer sales
    $customerId = $order['customerId'] ?? '';
    if (!isset($customerSales[$customerId])) {
        $customerSales[$customerId] = [
            'orders' => 0,
            'revenue' => 0,
            'name' => 'Unknown Customer'
        ];
        
        // Get customer name
        foreach ($customersData as $customer) {
            if (($customer['first_name'] ?? '') == $customerId) {
                $customerSales[$customerId]['name'] = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
                break;
            }
        }
    }
    $customerSales[$customerId]['orders']++;
    $customerSales[$customerId]['revenue'] += $orderTotal;
    
    // Region sales (using shipping address state/region)
    $shippingAddress = $order['shippingAddress'] ?? '';
    $region = 'Unknown';
    
    // Simple region extraction (would be more sophisticated in a real system)
    if (preg_match('/([A-Z]{2})/', $shippingAddress, $matches)) {
        $region = $matches[1];
    }
    
    if (!isset($regionSales[$region])) {
        $regionSales[$region] = [
            'orders' => 0,
            'revenue' => 0
        ];
    }
    $regionSales[$region]['orders']++;
    $regionSales[$region]['revenue'] += $orderTotal;
}

// Calculate average order value
$averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Sort product sales by revenue (descending)
uasort($productSales, function($a, $b) {
    return $b['revenue'] <=> $a['revenue'];
});

// Get top 5 products
$topProducts = array_slice($productSales, 0, 5, true);

// Sort customer sales by revenue (descending)
uasort($customerSales, function($a, $b) {
    return $b['revenue'] <=> $a['revenue'];
});

// Get top 5 customers
$topCustomers = array_slice($customerSales, 0, 5, true);

// Sort category sales by revenue (descending)
uasort($categorySales, function($a, $b) {
    return $b['revenue'] <=> $a['revenue'];
});

// Format daily sales for chart
$salesDates = array_keys($dailySales);
$salesValues = array_values($dailySales);

// Format category sales for chart
$categoryNames = array_keys($categorySales);
$categoryRevenues = array_map(function($item) {
    return $item['revenue'];
}, $categorySales);

// Format region sales for chart
$regionNames = array_keys($regionSales);
$regionRevenues = array_map(function($item) {
    return $item['revenue'];
}, $regionSales);
?>

<!-- Back to Dashboard Navigation -->
<div class="mb-6">
    <a href="/?page=admin" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium rounded-md">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back to Dashboard
    </a>
</div>

<!-- Reports Header -->
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Reports & Analytics</h1>
            <p class="text-gray-600">Insights and performance metrics for your business</p>
        </div>
        <div class="mt-4 md:mt-0">
            <div class="flex space-x-2">
                <button type="button" onclick="exportReportCSV()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                </button>
                <button type="button" onclick="printReport()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Time Period Filter -->
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <form action="" method="GET" class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="reports">
        
        <div class="w-full md:w-48">
            <label for="time_period" class="block text-sm font-medium text-gray-700">Time Period</label>
            <select id="time_period" name="time_period" onchange="toggleCustomDateInputs()" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                <option value="week" <?php echo $timePeriod === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="month" <?php echo $timePeriod === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="quarter" <?php echo $timePeriod === 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                <option value="year" <?php echo $timePeriod === 'year' ? 'selected' : ''; ?>>Last 365 Days</option>
                <option value="custom" <?php echo $timePeriod === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
            </select>
        </div>
        
        <div id="custom_date_container" class="<?php echo $timePeriod !== 'custom' ? 'hidden' : ''; ?> flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4">
            <div class="w-full md:w-48">
                <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo $customStartDate; ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <div class="w-full md:w-48">
                <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo $customEndDate; ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>
        </div>
        
        <div class="flex items-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                Apply Filter
            </button>
        </div>
    </form>
</div>

<!-- Key Metrics Summary -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <!-- Revenue Metric -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-800 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Total Revenue</p>
                <span class="admin-data-label">Total Revenue</span>
                <span class="admin-data-value">$<?php echo number_format($totalRevenue, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Orders Metric -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-800 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Total Orders</p>
                <span class="admin-data-label">Total Orders</span>
                <span class="admin-data-value"><?php echo $totalOrders; ?></span>
            </div>
        </div>
    </div>

    <!-- Average Order Value -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-800 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Average Order Value</p>
                <span class="admin-data-label">Average Order Value</span>
                <span class="admin-data-value">$<?php echo number_format($averageOrderValue, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Products Sold -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-800 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Products Sold</p>
                <span class="admin-data-label">Products Sold</span>
                <span class="admin-data-value"><?php echo $totalProductsSold; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Over Time Chart -->
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <h2 class="text-lg font-medium text-gray-900 mb-4">Revenue Over Time</h2>
    <div class="h-80">
        <canvas id="revenueChart"></canvas>
    </div>
</div>

<!-- Sales by Category Chart -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Sales by Category</h2>
        <div class="h-64">
            <canvas id="categorySalesChart"></canvas>
        </div>
    </div>
    
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Geographic Sales Distribution</h2>
        <div class="h-64">
            <canvas id="regionSalesChart"></canvas>
        </div>
    </div>
</div>

<!-- Top Products -->
<div class="bg-white shadow rounded-lg overflow-hidden mb-6">
    <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            Top Selling Products
        </h3>
        <p class="mt-1 max-w-2xl text-sm text-gray-500">
            Products with the highest revenue during the selected period
        </p>
    </div>
    
    <?php if (empty($topProducts)): ?>
        <div class="p-6 text-center text-gray-500 italic">
            No product sales data available for the selected period.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% of Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    $rank = 1;
                    foreach ($topProducts as $productId => $product): 
                        $percentOfTotal = $totalRevenue > 0 ? ($product['revenue'] / $totalRevenue) * 100 : 0;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                #<?php echo $rank++; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="text-sm text-gray-500">ID: <?php echo htmlspecialchars($productId); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $product['quantity']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                $<?php echo number_format($product['revenue'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?php echo number_format($percentOfTotal, 1); ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Top Customers -->
<div class="bg-white shadow rounded-lg overflow-hidden mb-6">
    <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            Top Customers
        </h3>
        <p class="mt-1 max-w-2xl text-sm text-gray-500">
            Customers with the highest spending during the selected period
        </p>
    </div>
    
    <?php if (empty($topCustomers)): ?>
        <div class="p-6 text-center text-gray-500 italic">
            No customer data available for the selected period.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Spent</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Order Value</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    $rank = 1;
                    foreach ($topCustomers as $customerId => $customer): 
                        $avgOrderValue = $customer['orders'] > 0 ? $customer['revenue'] / $customer['orders'] : 0;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                #<?php echo $rank++; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($customer['name']); ?></div>
                                <div class="text-sm text-gray-500">ID: <?php echo htmlspecialchars($customerId); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $customer['orders']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                $<?php echo number_format($customer['revenue'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                $<?php echo number_format($avgOrderValue, 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Toggle custom date inputs based on time period selection
    function toggleCustomDateInputs() {
        const timePeriod = document.getElementById('time_period').value;
        const customDateContainer = document.getElementById('custom_date_container');
        
        if (timePeriod === 'custom') {
            customDateContainer.classList.remove('hidden');
        } else {
            customDateContainer.classList.add('hidden');
        }
    }
    
    // Revenue over time chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($salesDates); ?>,
            datasets: [{
                label: 'Daily Revenue',
                data: <?php echo json_encode($salesValues); ?>,
                backgroundColor: 'rgba(107, 142, 35, 0.2)',
                borderColor: 'rgba(107, 142, 35, 1)',
                borderWidth: 2,
                tension: 0.4,
                pointBackgroundColor: 'rgba(107, 142, 35, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                },
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: $' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            }
        }
    });
    
    // Category sales chart
    const categoryCtx = document.getElementById('categorySalesChart').getContext('2d');
    const categorySalesChart = new Chart(categoryCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($categoryNames); ?>,
            datasets: [{
                data: <?php echo json_encode($categoryRevenues); ?>,
                backgroundColor: [
                    'rgba(107, 142, 35, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(199, 199, 199, 0.7)'
                ],
                borderColor: [
                    'rgba(107, 142, 35, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(199, 199, 199, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return context.label + ': $' + value.toFixed(2) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // Region sales chart
    const regionCtx = document.getElementById('regionSalesChart').getContext('2d');
    const regionSalesChart = new Chart(regionCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($regionNames); ?>,
            datasets: [{
                label: 'Revenue by Region',
                data: <?php echo json_encode($regionRevenues); ?>,
                backgroundColor: 'rgba(107, 142, 35, 0.7)',
                borderColor: 'rgba(107, 142, 35, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: $' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            }
        }
    });
    
    // Export report as CSV
    function exportReportCSV() {
        // In a real application, this would generate a CSV file with report data
        // For now, we'll just show an alert
        alert('CSV export functionality would be implemented here.');
        
        // Example implementation would include:
        // 1. Collect all report data
        // 2. Format as CSV
        // 3. Create a download link with the CSV data
        // 4. Trigger the download
    }
    
    // Print report
    function printReport() {
        window.print();
    }
</script>

<style>
    @media print {
        .no-print, .no-print * {
            display: none !important;
        }
        
        body {
            background-color: white;
        }
        
        .shadow {
            box-shadow: none !important;
        }
        
        .rounded-lg {
            border-radius: 0 !important;
        }
        
        .mb-6 {
            margin-bottom: 1rem !important;
        }
    }
    
    .admin-data-label {
        color: #222 !important;
    }
    .admin-data-value {
        color: #c00 !important;
        font-weight: bold;
    }
</style>

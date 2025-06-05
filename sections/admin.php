<?php
// Admin Dashboard
// This page provides comprehensive management tools for administrators

// Fetch summary data for dashboard metrics
$inventoryData = fetchData('inventory');
$ordersData = fetchData('sales_orders') ?? [];
$customersData = fetchData('users') ?? [];

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
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Admin Dashboard</h1>
                <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($adminName); ?> (<?php echo htmlspecialchars($adminRole); ?>)</p>
            </div>
            <div class="mt-4 md:mt-0">
                <p class="text-sm text-gray-500">Last login: <?php echo date('F j, Y, g:i a'); ?></p>
            </div>
        </div>
    </div>

    <!-- Metrics Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Products Metric -->
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-800 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Products</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $totalProducts; ?></p>
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
                    <p class="text-2xl font-bold text-gray-800"><?php echo $totalOrders; ?></p>
                </div>
            </div>
        </div>

        <!-- Customers Metric -->
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-800 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Customers</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $totalCustomers; ?></p>
                </div>
            </div>
        </div>

        <!-- Revenue Metric -->
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-800 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $formattedRevenue; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Management Areas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Inventory Management -->
        <a href="/?page=admin_inventory" class="bg-white shadow rounded-lg p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center mb-4">
                <div class="p-3 rounded-full bg-green-600 text-white mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Inventory Management</h2>
            </div>
            <p class="text-gray-600">Manage products, stock levels, and product categories.</p>
            <div class="mt-4 text-green-600 font-medium flex items-center">
                <span>Manage Inventory</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
        </a>

        <!-- Customer Management -->
        <a href="/?page=admin&section=customers" class="bg-white shadow rounded-lg p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center mb-4">
                <div class="p-3 rounded-full bg-purple-600 text-white mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Customer Management</h2>
            </div>
            <p class="text-gray-600">View and manage customer accounts and information.</p>
            <div class="mt-4 text-purple-600 font-medium flex items-center">
                <span>Manage Customers</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
        </a>

        <!-- Order Management -->
        <a href="/?page=admin&section=orders" class="bg-white shadow rounded-lg p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center mb-4">
                <div class="p-3 rounded-full bg-blue-600 text-white mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Order Management</h2>
            </div>
            <p class="text-gray-600">Process orders, track shipments, and manage order history.</p>
            <div class="mt-4 text-blue-600 font-medium flex items-center">
                <span>Manage Orders</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
        </a>

        <!-- Reports -->
        <a href="/?page=admin&section=reports" class="bg-white shadow rounded-lg p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center mb-4">
                <div class="p-3 rounded-full bg-indigo-600 text-white mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Reports & Analytics</h2>
            </div>
            <p class="text-gray-600">View sales reports, analytics, and business insights.</p>
            <div class="mt-4 text-indigo-600 font-medium flex items-center">
                <span>View Reports</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
        </a>

        <!-- Marketing -->
        <a href="/?page=admin&section=marketing" class="bg-white shadow rounded-lg p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center mb-4">
                <div class="p-3 rounded-full bg-red-600 text-white mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Marketing</h2>
            </div>
            <p class="text-gray-600">Manage promotions, discounts, and marketing campaigns.</p>
            <div class="mt-4 text-red-600 font-medium flex items-center">
                <span>Manage Marketing</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
        </a>

        <!-- Settings -->
        <a href="/?page=admin&section=settings" class="bg-white shadow rounded-lg p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center mb-4">
                <div class="p-3 rounded-full bg-gray-600 text-white mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Settings</h2>
            </div>
            <p class="text-gray-600">Configure store settings, payment methods, and user permissions.</p>
            <div class="mt-4 text-gray-600 font-medium flex items-center">
                <span>Manage Settings</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
        </a>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent Activity</h2>
        
        <?php if (empty($ordersData)): ?>
            <p class="text-gray-500 italic">No recent orders found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        // Display up to 5 most recent orders
                        $recentOrders = array_slice($ordersData, 0, 5);
                        foreach ($recentOrders as $order): 
                            // Get customer name if available
                            $customerName = "Customer";
                            if (isset($order['customerId'])) {
                                foreach ($customersData as $customer) {
                                    if ($customer[0] == $order['customerId']) {
                                        $customerName = ($customer[6] ?? '') . ' ' . ($customer[7] ?? '');
                                        break;
                                    }
                                }
                            }
                        ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#<?php echo htmlspecialchars($order['id'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($customerName); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($order['date'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format(floatval($order['total'] ?? 0), 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $status = $order['status'] ?? 'Pending';
                                    $statusColor = 'gray';
                                    
                                    switch($status) {
                                        case 'Completed':
                                            $statusColor = 'green';
                                            break;
                                        case 'Processing':
                                            $statusColor = 'blue';
                                            break;
                                        case 'Shipped':
                                            $statusColor = 'indigo';
                                            break;
                                        case 'Cancelled':
                                            $statusColor = 'red';
                                            break;
                                        default:
                                            $statusColor = 'yellow';
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 text-right">
                <a href="/?page=admin&section=orders" class="text-green-600 hover:text-green-800 font-medium">View All Orders →</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Low Stock Alert -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Low Stock Alert</h2>
        
        <?php
        $lowStockItems = [];
        foreach ($inventoryData as $item) {
            if (isset($item['quantity']) && $item['quantity'] < 5) {
                $lowStockItems[] = $item;
            }
        }
        
        if (empty($lowStockItems)): 
        ?>
            <p class="text-green-600">All products are well-stocked.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($lowStockItems as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#<?php echo htmlspecialchars($item['id'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['name'] ?? 'Unknown Product'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        <?php echo htmlspecialchars($item['quantity'] ?? '0'); ?> left
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <a href="/?page=admin_inventory&action=edit&id=<?php echo $item['id']; ?>" class="text-indigo-600 hover:text-indigo-900">Update Stock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 text-right">
                <a href="/?page=admin_inventory" class="text-green-600 hover:text-green-800 font-medium">Manage Inventory →</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Section Handling -->
<?php
// Handle different admin sections
$section = isset($_GET['section']) ? $_GET['section'] : '';

switch($section) {
    case 'customers':
        include 'sections/admin_customers.php';
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
    // Default section is already shown (dashboard)
}
?>

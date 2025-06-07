<?php
// Admin Orders Management
// This page provides tools for managing customer orders and order processing

// Prevent direct access
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// Fetch orders, users, and inventory data from Node API (MySQL)
$apiBase = 'https://whimsicalfrog.us';
$ordersJson = @file_get_contents($apiBase . '/api/sales_orders');
$ordersData = $ordersJson ? json_decode($ordersJson, true) : [];
$customersJson = @file_get_contents($apiBase . '/api/users');
$customersData = $customersJson ? json_decode($customersJson, true) : [];
$inventoryJson = @file_get_contents($apiBase . '/api/inventory');
$inventoryData = $inventoryJson ? json_decode($inventoryJson, true) : [];

// Handle search/filter
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';

// Filter orders based on search term
if (!empty($searchTerm)) {
    $ordersData = array_filter($ordersData, function($order) use ($searchTerm) {
        // Search in order ID, customer name, or email
        $orderId = $order['id'] ?? '';
        $customerId = $order['customerId'] ?? '';
        
        // Find customer info
        $customerName = '';
        $customerEmail = '';
        foreach ($GLOBALS['customersData'] as $customer) {
            if (($customer['id'] ?? '') == $customerId) {
                $customerName = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
                $customerEmail = $customer['email'] ?? '';
                break;
            }
        }
        
        return (stripos($orderId, $searchTerm) !== false ||
                stripos($customerName, $searchTerm) !== false ||
                stripos($customerEmail, $searchTerm) !== false);
    });
}

// Filter by status
if ($filterStatus !== 'all') {
    $ordersData = array_filter($ordersData, function($order) use ($filterStatus) {
        return isset($order['status']) && $order['status'] === $filterStatus;
    });
}

// Filter by date range
if ($dateRange !== 'all') {
    $today = date('Y-m-d');
    $ordersData = array_filter($ordersData, function($order) use ($dateRange, $today) {
        if (!isset($order['date'])) return false;
        
        $orderDate = date('Y-m-d', strtotime($order['date']));
        $diff = (strtotime($today) - strtotime($orderDate)) / (60 * 60 * 24); // difference in days
        
        switch ($dateRange) {
            case 'today':
                return $orderDate === $today;
            case 'yesterday':
                return $diff >= 1 && $diff < 2;
            case 'week':
                return $diff < 7;
            case 'month':
                return $diff < 30;
            default:
                return true;
        }
    });
}

// Sort orders
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$sortDir = isset($_GET['dir']) ? $_GET['dir'] : 'desc';

usort($ordersData, function($a, $b) use ($sortBy, $sortDir) {
    switch($sortBy) {
        case 'id':
            $valA = $a['id'] ?? '';
            $valB = $b['id'] ?? '';
            break;
        case 'customer':
            $customerIdA = $a['customerId'] ?? '';
            $customerIdB = $b['customerId'] ?? '';
            
            $customerNameA = '';
            $customerNameB = '';
            
            foreach ($GLOBALS['customersData'] as $customer) {
                if (($customer['id'] ?? '') == $customerIdA) {
                    $customerNameA = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
                }
                if (($customer['id'] ?? '') == $customerIdB) {
                    $customerNameB = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
                }
            }
            
            $valA = $customerNameA;
            $valB = $customerNameB;
            break;
        case 'total':
            $valA = floatval($a['total'] ?? 0);
            $valB = floatval($b['total'] ?? 0);
            break;
        case 'status':
            $valA = $a['status'] ?? '';
            $valB = $b['status'] ?? '';
            break;
        case 'date':
        default:
            $valA = strtotime($a['date'] ?? '');
            $valB = strtotime($b['date'] ?? '');
    }
    
    if ($sortDir === 'asc') {
        return $valA <=> $valB;
    } else {
        return $valB <=> $valA;
    }
});

// Pagination
$itemsPerPage = 10;
$totalItems = count($ordersData);
$totalPages = ceil($totalItems / $itemsPerPage);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

$paginatedOrders = array_slice($ordersData, $offset, $itemsPerPage);

// Helper function to generate sort URL
function getSortUrl($column, $currentSort, $currentDir) {
    $newDir = ($column === $currentSort && $currentDir === 'asc') ? 'desc' : 'asc';
    $queryParams = $_GET;
    $queryParams['sort'] = $column;
    $queryParams['dir'] = $newDir;
    return '?' . http_build_query($queryParams);
}

// Helper function to get sort indicator
function getSortIndicator($column, $currentSort, $currentDir) {
    if ($column !== $currentSort) {
        return '';
    }
    return $currentDir === 'asc' ? '↑' : '↓';
}

// Helper function to get customer name from ID
function getCustomerName($customerId, $customersData) {
    foreach ($customersData as $customer) {
        if (($customer['id'] ?? '') == $customerId) {
            return ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
        }
    }
    return 'Unknown Customer';
}

// Helper function to get product name from ID
function getProductName($productId, $inventoryData) {
    foreach ($inventoryData as $product) {
        if (($product['id'] ?? '') == $productId) {
            return $product['name'] ?? 'Unknown Product';
        }
    }
    return 'Unknown Product';
}

// Process order status update if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $orderId = $_POST['order_id'] ?? '';
    $newStatus = $_POST['status'] ?? '';
    $trackingNumber = $_POST['tracking_number'] ?? '';
    
    // In a real application, this would update the database
    // For now, we'll just show a success message
    $updateSuccess = true;
}
?>

<style>
  .admin-data-label {
    color: #222 !important;
  }
  .admin-data-value {
    color: #c00 !important;
    font-weight: bold;
  }
</style>

<!-- Top bar: Back to Dashboard | Search/Filters | Add New Order (if applicable) -->
<div class="mb-4 flex flex-row justify-between items-center gap-2">
    <a href="/?page=admin" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium rounded-md">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back to Dashboard
    </a>
    <form action="" method="GET" class="flex flex-row items-center gap-2 mb-0" style="flex:1;max-width:600px;justify-content:center;">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="orders">
        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($searchTerm); ?>" class="block w-full px-2 py-1 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500" placeholder="Search..." style="max-width:140px;">
        <select id="status" name="status" class="block px-2 py-1 border border-gray-300 rounded-md text-xs focus:ring-green-500 focus:border-green-500" style="max-width:100px;">
            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="processing" <?php echo $filterStatus === 'processing' ? 'selected' : ''; ?>>Processing</option>
            <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
        <select id="date_range" name="date_range" class="block px-2 py-1 border border-gray-300 rounded-md text-xs focus:ring-green-500 focus:border-green-500" style="max-width:100px;">
            <option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>All Dates</option>
            <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>>This Week</option>
            <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>This Month</option>
        </select>
        <button type="submit" class="inline-flex items-center px-2 py-1 border border-transparent rounded-md shadow-sm text-xs font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </button>
    </form>
    <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500" onclick="window.location.href='/?page=admin&section=orders&export=1'">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Export Orders
    </button>
</div>

<!-- Status Update Success Message -->
<?php if (isset($updateSuccess) && $updateSuccess): ?>
<div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm text-green-700">
                Order status updated successfully.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Order List -->
<div class="bg-white shadow rounded-lg overflow-hidden mb-6">
    <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            Order List
        </h3>
        <p class="mt-1 max-w-2xl text-sm text-gray-500">
            Showing <?php echo count($paginatedOrders); ?> of <?php echo $totalItems; ?> orders
        </p>
    </div>
    
    <?php if (empty($paginatedOrders)): ?>
        <div class="p-6 text-center text-gray-500 italic">
            No orders found matching your criteria.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('id', $sortBy, $sortDir); ?>" class="flex items-center">
                                <span class="admin-data-label">Order ID</span> <?php echo getSortIndicator('id', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('date', $sortBy, $sortDir); ?>" class="flex items-center">
                                <span class="admin-data-label">Date</span> <?php echo getSortIndicator('date', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('customer', $sortBy, $sortDir); ?>" class="flex items-center">
                                <span class="admin-data-label">Customer</span> <?php echo getSortIndicator('customer', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('total', $sortBy, $sortDir); ?>" class="flex items-center">
                                <span class="admin-data-label">Total</span> <?php echo getSortIndicator('total', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('status', $sortBy, $sortDir); ?>" class="flex items-center">
                                <span class="admin-data-label">Status</span> <?php echo getSortIndicator('status', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <span class="admin-data-label">Payment Status</span>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <span class="admin-data-label">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($paginatedOrders as $order): 
                        $orderId = $order['id'] ?? '';
                        $orderDate = $order['date'] ?? '';
                        $customerId = $order['customerId'] ?? '';
                        $total = $order['total'] ?? 0;
                        $status = $order['status'] ?? 'Pending';
                        $paymentStatus = $order['paymentStatus'] ?? $order['payment_status'] ?? 'Pending';
                        
                        // Get customer name
                        $customerName = getCustomerName($customerId, $customersData);
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="admin-data-value">#<?php echo htmlspecialchars($orderId); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="admin-data-value"><?php echo htmlspecialchars($orderDate); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($customerName); ?></div>
                                <div class="text-sm text-gray-500">ID: <span class="admin-data-value"><?php echo htmlspecialchars($customerId); ?></span></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="admin-data-value">$<?php echo number_format(floatval($total), 2); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
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
                                    case 'Delivered':
                                        $statusColor = 'purple';
                                        break;
                                    case 'Cancelled':
                                        $statusColor = 'red';
                                        break;
                                    default:
                                        $statusColor = 'yellow';
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                    <span class="admin-data-value"><?php echo htmlspecialchars($status); ?></span>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $paymentStatus === 'Received' ? 'green' : 'yellow'; ?>-100 text-<?php echo $paymentStatus === 'Received' ? 'green' : 'yellow'; ?>-800">
                                    <span class="admin-data-value"><?php echo htmlspecialchars($paymentStatus); ?></span>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="/?page=admin&section=orders&action=view&id=<?php echo $orderId; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                <a href="/?page=admin&section=orders&action=edit&id=<?php echo $orderId; ?>" class="text-green-600 hover:text-green-900">Update</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $itemsPerPage, $totalItems); ?></span> of <span class="font-medium"><?php echo $totalItems; ?></span> results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php
                            // Previous page link
                            $prevPageUrl = '?';
                            $queryParams = $_GET;
                            $queryParams['page'] = max(1, $currentPage - 1);
                            $prevPageUrl .= http_build_query($queryParams);
                            
                            // Next page link
                            $nextPageUrl = '?';
                            $queryParams = $_GET;
                            $queryParams['page'] = min($totalPages, $currentPage + 1);
                            $nextPageUrl .= http_build_query($queryParams);
                            ?>
                            
                            <a href="<?php echo $prevPageUrl; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            
                            <?php
                            // Page number links
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                $pageUrl = '?';
                                $queryParams = $_GET;
                                $queryParams['page'] = $i;
                                $pageUrl .= http_build_query($queryParams);
                                
                                $isCurrentPage = $i === $currentPage;
                                $classes = $isCurrentPage 
                                    ? 'z-10 bg-green-50 border-green-500 text-green-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium'
                                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium';
                                
                                echo '<a href="' . $pageUrl . '" class="' . $classes . '">' . $i . '</a>';
                            }
                            ?>
                            
                            <a href="<?php echo $nextPageUrl; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Order Detail View -->
<?php
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
    $orderId = $_GET['id'];
    $orderData = null;
    
    // Find the order
    foreach ($ordersData as $order) {
        if (($order['id'] ?? '') == $orderId) {
            $orderData = $order;
            break;
        }
    }
    
    if ($orderData) {
        $customerId = $orderData['customerId'] ?? '';
        $orderDate = $orderData['date'] ?? '';
        $orderStatus = $orderData['status'] ?? 'Pending';
        $orderTotal = $orderData['total'] ?? 0;
        $orderItems = $orderData['items'] ?? [];
        $shippingAddress = $orderData['shippingAddress'] ?? '';
        $billingAddress = $orderData['billingAddress'] ?? '';
        $trackingNumber = $orderData['trackingNumber'] ?? '';
        $paymentMethod = $orderData['paymentMethod'] ?? 'Credit Card';
        
        // Get customer info
        $customerName = getCustomerName($customerId, $customersData);
        $customerEmail = '';
        foreach ($customersData as $customer) {
            if (($customer['id'] ?? '') == $customerId) {
                $customerEmail = $customer['email'] ?? '';
                break;
            }
        }
?>
<div class="bg-white shadow rounded-lg overflow-hidden mb-6">
    <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Order #<span class="admin-data-value"><?php echo htmlspecialchars($orderId); ?></span>
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                Placed on <span class="admin-data-value"><?php echo htmlspecialchars($orderDate); ?></span>
            </p>
        </div>
        <div>
            <a href="/?page=admin&section=orders" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Orders
            </a>
        </div>
    </div>
    
    <!-- Order Status -->
    <div class="p-6 border-b border-gray-200">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div class="mb-4 md:mb-0">
                <h4 class="text-lg font-medium text-gray-900">Order Status</h4>
                <?php 
                $statusColor = 'gray';
                
                switch($orderStatus) {
                    case 'Completed':
                        $statusColor = 'green';
                        break;
                    case 'Processing':
                        $statusColor = 'blue';
                        break;
                    case 'Shipped':
                        $statusColor = 'indigo';
                        break;
                    case 'Delivered':
                        $statusColor = 'purple';
                        break;
                    case 'Cancelled':
                        $statusColor = 'red';
                        break;
                    default:
                        $statusColor = 'yellow';
                }
                ?>
                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                    <span class="admin-data-value"><?php echo htmlspecialchars($orderStatus); ?></span>
                </span>
                <?php if (!empty($trackingNumber)): ?>
                <div class="mt-2 text-sm text-gray-500">
                    Tracking #: <span class="admin-data-value"><?php echo htmlspecialchars($trackingNumber); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <a href="/?page=admin&section=orders&action=edit&id=<?php echo $orderId; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Update Status
                </a>
            </div>
        </div>
    </div>
    
    <!-- Customer and Order Info -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-b border-gray-200">
        <div>
            <h4 class="text-lg font-medium text-gray-900 mb-4">Customer Information</h4>
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 h-10 w-10">
                    <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                        <span class="text-gray-600 font-medium">
                            <?php 
                            $initials = 'CU';
                            $nameParts = explode(' ', $customerName);
                            if (count($nameParts) >= 2) {
                                $initials = substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1);
                            } elseif (!empty($customerName)) {
                                $initials = substr($customerName, 0, 2);
                            }
                            echo strtoupper($initials);
                            ?>
                        </span>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">
                        <span class="admin-data-label">Customer Name</span>
                        <span class="admin-data-value"><?php echo htmlspecialchars($customerName); ?></span>
                    </div>
                    <div class="text-sm text-gray-500">
                        <span class="admin-data-label">Customer Email</span>
                        <span class="admin-data-value"><?php echo htmlspecialchars($customerEmail); ?></span>
                    </div>
                </div>
            </div>
            <a href="/?page=admin&section=customers&action=view&id=<?php echo $customerId; ?>" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                <span class="admin-data-label">View Customer Profile</span> →
            </a>
        </div>
        
        <div>
            <h4 class="text-lg font-medium text-gray-900 mb-4">Order Details</h4>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-sm font-medium text-gray-500">Order Date</span>
                    <span class="block text-gray-900"><span class="admin-data-label">Order Date</span> <?php echo htmlspecialchars($orderDate); ?></span>
                </div>
                <div>
                    <span class="block text-sm font-medium text-gray-500">Payment Method</span>
                    <span class="block text-gray-900"><span class="admin-data-label">Payment Method</span> <?php echo htmlspecialchars($paymentMethod); ?></span>
                </div>
                <div>
                    <span class="block text-sm font-medium text-gray-500">Total Amount</span>
                    <span class="block text-gray-900 font-bold"><span class="admin-data-label">Total Amount</span> $<?php echo number_format(floatval($orderTotal), 2); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Shipping and Billing -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-b border-gray-200">
        <div>
            <h4 class="text-lg font-medium text-gray-900 mb-4">Shipping Address</h4>
            <p class="text-gray-700 whitespace-pre-line"><span class="admin-data-label">Shipping Address</span> <?php echo htmlspecialchars($shippingAddress); ?></p>
        </div>
        
        <div>
            <h4 class="text-lg font-medium text-gray-900 mb-4">Billing Address</h4>
            <p class="text-gray-700 whitespace-pre-line"><span class="admin-data-label">Billing Address</span> <?php echo htmlspecialchars($billingAddress); ?></p>
        </div>
    </div>
    
    <!-- Payment Status Section in Order Detail View -->
    <div class="p-6 border-b border-gray-200">
        <h4 class="text-lg font-medium text-gray-900 mb-2">Payment Status</h4>
        <div class="flex items-center gap-4">
            <span id="paymentStatusLabel" class="inline-block px-3 py-1 rounded-full text-sm font-semibold bg-<?php echo ($orderData['paymentStatus'] ?? $orderData['payment_status'] ?? 'Pending') === 'Received' ? 'green' : 'yellow'; ?>-100 text-<?php echo ($orderData['paymentStatus'] ?? $orderData['payment_status'] ?? 'Pending') === 'Received' ? 'green' : 'yellow'; ?>-800">
                <span class="admin-data-value"><?php echo htmlspecialchars($orderData['paymentStatus'] ?? $orderData['payment_status'] ?? 'Pending'); ?></span>
            </span>
            <?php if ((($orderData['paymentStatus'] ?? $orderData['payment_status'] ?? 'Pending') === 'Pending') && (($_SESSION['user']['role'] ?? '') === 'Admin')) : ?>
                <button id="markPaymentReceivedBtn" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">Mark as Received</button>
                <script>
                document.getElementById('markPaymentReceivedBtn').onclick = async function() {
                    if (!confirm('Mark payment as received for this order?')) return;
                    const apiBase = 'https://whimsicalfrog.us';
                    const response = await fetch(apiBase + '/api/update-payment-status', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ orderId: <?php echo json_encode($orderId); ?>, newStatus: 'Received' })
                    });
                    const data = await response.json();
                    if (data.success) {
                        alert('Payment marked as received!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                };
                </script>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Order Items -->
    <div class="p-6">
        <h4 class="text-lg font-medium text-gray-900 mb-4">Order Items</h4>
        
        <?php if (empty($orderItems)): ?>
            <p class="text-gray-500 italic">No items found for this order.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $subtotal = 0;
                        foreach ($orderItems as $item): 
                            $productId = $item['productId'] ?? '';
                            $productName = getProductName($productId, $inventoryData);
                            $price = floatval($item['price'] ?? 0);
                            $quantity = intval($item['quantity'] ?? 0);
                            $itemTotal = $price * $quantity;
                            $subtotal += $itemTotal;
                        ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><span class="admin-data-label">Product</span> <?php echo htmlspecialchars($productName); ?></div>
                                            <div class="text-sm text-gray-500"><span class="admin-data-label">SKU</span> <?php echo htmlspecialchars($productId); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="admin-data-value">$<?php echo number_format($price, 2); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="admin-data-value"><?php echo $quantity; ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <span class="admin-data-value">$<?php echo number_format($itemTotal, 2); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">Subtotal:</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                <span class="admin-data-value">$<?php echo number_format($subtotal, 2); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">Shipping:</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                <span class="admin-data-value">$<?php echo number_format(floatval($orderTotal) - $subtotal, 2); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-base font-bold text-gray-900 text-right">Total:</td>
                            <td class="px-6 py-4 whitespace-nowrap text-base font-bold text-gray-900 text-right">
                                <span class="admin-data-value">$<?php echo number_format(floatval($orderTotal), 2); ?></span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
    } else {
        echo '<div class="bg-white shadow rounded-lg p-6 mb-6 text-center text-red-600">Order not found.</div>';
    }
}
?>

<!-- Order Status Update Form -->
<?php
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $orderId = $_GET['id'];
    $orderData = null;
    
    // Find the order
    foreach ($ordersData as $order) {
        if (($order['id'] ?? '') == $orderId) {
            $orderData = $order;
            break;
        }
    }
    
    if ($orderData) {
        $orderStatus = $orderData['status'] ?? 'Pending';
        $trackingNumber = $orderData['trackingNumber'] ?? '';
        $paymentStatus = $orderData['paymentStatus'] ?? $orderData['payment_status'] ?? 'Pending';
?>
<div class="bg-white shadow rounded-lg overflow-hidden mb-6">
    <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Update Order Status
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                Order #<span class="admin-data-value"><?php echo htmlspecialchars($orderId); ?></span>
            </p>
        </div>
        <div>
            <a href="/?page=admin&section=orders&action=view&id=<?php echo $orderId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Order Details
            </a>
        </div>
    </div>
    
    <div class="p-6">
        <form action="/?page=admin&section=orders&action=view&id=<?php echo $orderId; ?>" method="POST" class="space-y-6">
            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Order Status</label>
                    <select id="status" name="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                        <option value="Pending" <?php echo $orderStatus === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Processing" <?php echo $orderStatus === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="Shipped" <?php echo $orderStatus === 'Shipped' ? 'selected' : ''; ?> <?php echo $paymentStatus !== 'Received' ? 'disabled' : ''; ?>>Shipped<?php echo $paymentStatus !== 'Received' ? ' (Payment not received)' : ''; ?></option>
                        <option value="Delivered" <?php echo $orderStatus === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="Completed" <?php echo $orderStatus === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $orderStatus === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <?php if ($paymentStatus !== 'Received') : ?>
                        <div class="text-yellow-700 text-xs mt-1">You must mark payment as received before shipping.</div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="tracking_number" class="block text-sm font-medium text-gray-700">Tracking Number</label>
                    <input type="text" name="tracking_number" id="tracking_number" value="<?php echo htmlspecialchars($trackingNumber); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
            </div>
            
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Status Update Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                    <p class="mt-2 text-sm text-gray-500">Add any notes about this status update (optional).</p>
                </div>
            </div>
            
            <div class="pt-5 border-t border-gray-200">
                <div class="flex justify-end">
                    <a href="/?page=admin&section=orders&action=view&id=<?php echo $orderId; ?>" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 mr-3">Cancel</a>
                    <button type="submit" name="update_order_status" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Update Order Status
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
    } else {
        echo '<div class="bg-white shadow rounded-lg p-6 mb-6 text-center text-red-600">Order not found.</div>';
    }
}
?>

<!-- Square Payment Configuration Placeholder (Admin Only) -->
<?php if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'Admin') : ?>
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-2">Payment Integration Settings</h2>
    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Square Payment Integration</label>
        <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded">
            <strong>Coming Soon:</strong> You will be able to connect your Square account here to accept credit card payments online.<br>
            When available, paste your Square Application ID and Access Token below.<br>
            <em>(This section is a placeholder. No credentials are stored yet.)</em>
        </div>
    </div>
    <form>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Square Application ID</label>
            <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Paste Square Application ID here" disabled>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Square Access Token</label>
            <input type="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Paste Square Access Token here" disabled>
        </div>
        <button type="button" class="bg-gray-400 text-white px-4 py-2 rounded cursor-not-allowed" disabled>Save (Coming Soon)</button>
    </form>
</div>
<?php endif; ?>

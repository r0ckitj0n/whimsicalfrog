<?php
// Admin Customers Management
// This page provides tools for managing customer accounts and information

// Prevent direct access
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// Fetch customers data
$customersData = fetchData('users') ?? [];

// Filter out admin users - only show customers
$customers = array_filter($customersData, function($user) {
    return isset($user[4]) && $user[4] !== 'Admin';
});

// Get orders data for customer statistics
$ordersData = fetchData('sales_orders') ?? [];

// Handle search/filter
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';

if (!empty($searchTerm)) {
    $customers = array_filter($customers, function($customer) use ($searchTerm) {
        // Search in name, email, or ID
        $firstName = $customer[6] ?? '';
        $lastName = $customer[7] ?? '';
        $email = $customer[3] ?? '';
        $id = $customer[0] ?? '';
        
        return (stripos($firstName, $searchTerm) !== false ||
                stripos($lastName, $searchTerm) !== false ||
                stripos($email, $searchTerm) !== false ||
                stripos($id, $searchTerm) !== false);
    });
}

// Calculate customer statistics
function getCustomerStats($customerId, $ordersData) {
    $customerOrders = array_filter($ordersData, function($order) use ($customerId) {
        return isset($order['customerId']) && $order['customerId'] == $customerId;
    });
    
    $totalOrders = count($customerOrders);
    $totalSpent = 0;
    
    foreach ($customerOrders as $order) {
        $totalSpent += floatval($order['total'] ?? 0);
    }
    
    $averageOrderValue = $totalOrders > 0 ? $totalSpent / $totalOrders : 0;
    $lastOrderDate = $totalOrders > 0 ? max(array_column($customerOrders, 'date')) : 'Never';
    
    return [
        'totalOrders' => $totalOrders,
        'totalSpent' => $totalSpent,
        'averageOrderValue' => $averageOrderValue,
        'lastOrderDate' => $lastOrderDate
    ];
}

// Sort customers (default by name)
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sortDir = isset($_GET['dir']) ? $_GET['dir'] : 'asc';

usort($customers, function($a, $b) use ($sortBy, $sortDir) {
    switch($sortBy) {
        case 'id':
            $valA = $a[0] ?? '';
            $valB = $b[0] ?? '';
            break;
        case 'email':
            $valA = $a[3] ?? '';
            $valB = $b[3] ?? '';
            break;
        case 'orders':
            $statsA = getCustomerStats($a[0], $GLOBALS['ordersData']);
            $statsB = getCustomerStats($b[0], $GLOBALS['ordersData']);
            $valA = $statsA['totalOrders'];
            $valB = $statsB['totalOrders'];
            break;
        case 'spent':
            $statsA = getCustomerStats($a[0], $GLOBALS['ordersData']);
            $statsB = getCustomerStats($b[0], $GLOBALS['ordersData']);
            $valA = $statsA['totalSpent'];
            $valB = $statsB['totalSpent'];
            break;
        case 'name':
        default:
            $valA = ($a[6] ?? '') . ' ' . ($a[7] ?? '');
            $valB = ($b[6] ?? '') . ' ' . ($b[7] ?? '');
    }
    
    if ($sortDir === 'asc') {
        return $valA <=> $valB;
    } else {
        return $valB <=> $valA;
    }
});

// Pagination
$itemsPerPage = 10;
$totalItems = count($customers);
$totalPages = ceil($totalItems / $itemsPerPage);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

$paginatedCustomers = array_slice($customers, $offset, $itemsPerPage);

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

<!-- Customer Management Header -->
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Customer Management</h1>
            <p class="text-gray-600">Manage and view customer accounts and information</p>
        </div>
        <div class="mt-4 md:mt-0">
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add New Customer
            </button>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <form action="" method="GET" class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="customers">
        
        <div class="flex-grow">
            <label for="search" class="block text-sm font-medium text-gray-700">Search Customers</label>
            <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($searchTerm); ?>" class="focus:ring-green-500 focus:border-green-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md" placeholder="Search by name, email, or ID">
            </div>
        </div>
        
        <div class="w-full md:w-48">
            <label for="status" class="block text-sm font-medium text-gray-700">Customer Status</label>
            <select id="status" name="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Customers</option>
                <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="new" <?php echo $filterStatus === 'new' ? 'selected' : ''; ?>>New (Last 30 days)</option>
            </select>
        </div>
        
        <div class="flex items-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                Filter
            </button>
        </div>
    </form>
</div>

<!-- Customer List -->
<div class="bg-white shadow rounded-lg overflow-hidden mb-6">
    <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            Customer List
        </h3>
        <p class="mt-1 max-w-2xl text-sm text-gray-500">
            Showing <?php echo count($paginatedCustomers); ?> of <?php echo $totalItems; ?> customers
        </p>
    </div>
    
    <?php if (empty($paginatedCustomers)): ?>
        <div class="p-6 text-center text-gray-500 italic">
            No customers found matching your criteria.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('id', $sortBy, $sortDir); ?>" class="flex items-center">
                                ID <?php echo getSortIndicator('id', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('name', $sortBy, $sortDir); ?>" class="flex items-center">
                                Customer Name <?php echo getSortIndicator('name', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('email', $sortBy, $sortDir); ?>" class="flex items-center">
                                Email <?php echo getSortIndicator('email', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('orders', $sortBy, $sortDir); ?>" class="flex items-center">
                                Orders <?php echo getSortIndicator('orders', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('spent', $sortBy, $sortDir); ?>" class="flex items-center">
                                Total Spent <?php echo getSortIndicator('spent', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Last Order
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($paginatedCustomers as $customer): 
                        $customerId = $customer[0] ?? '';
                        $username = $customer[1] ?? '';
                        $email = $customer[3] ?? '';
                        $firstName = $customer[6] ?? '';
                        $lastName = $customer[7] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                        
                        // Get customer statistics
                        $stats = getCustomerStats($customerId, $ordersData);
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                #<?php echo htmlspecialchars($customerId); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                            <span class="text-gray-600 font-medium">
                                                <?php 
                                                    if (!empty($firstName) && !empty($lastName)) {
                                                        echo substr($firstName, 0, 1) . substr($lastName, 0, 1);
                                                    } else {
                                                        echo substr($username, 0, 2);
                                                    }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo !empty($fullName) ? htmlspecialchars($fullName) : htmlspecialchars($username); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            @<?php echo htmlspecialchars($username); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($email); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $stats['totalOrders']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                $<?php echo number_format($stats['totalSpent'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $stats['lastOrderDate'] !== 'Never' ? htmlspecialchars($stats['lastOrderDate']) : 'Never'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="/?page=admin&section=customers&action=view&id=<?php echo $customerId; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                <a href="/?page=admin&section=customers&action=edit&id=<?php echo $customerId; ?>" class="text-green-600 hover:text-green-900">Edit</a>
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

<!-- Customer Details View (shown when viewing a specific customer) -->
<?php
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
    $customerId = $_GET['id'];
    $customerData = null;
    
    // Find the customer
    foreach ($customersData as $customer) {
        if (($customer[0] ?? '') == $customerId) {
            $customerData = $customer;
            break;
        }
    }
    
    if ($customerData) {
        $username = $customerData[1] ?? '';
        $email = $customerData[3] ?? '';
        $firstName = $customerData[6] ?? '';
        $lastName = $customerData[7] ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        
        // Get customer statistics
        $stats = getCustomerStats($customerId, $ordersData);
        
        // Get customer orders
        $customerOrders = array_filter($ordersData, function($order) use ($customerId) {
            return isset($order['customerId']) && $order['customerId'] == $customerId;
        });
        
        // Sort orders by date (newest first)
        usort($customerOrders, function($a, $b) {
            return strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0);
        });
?>
<div class="bg-white shadow rounded-lg overflow-hidden mb-6">
    <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Customer Details
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                Personal information and order history
            </p>
        </div>
        <div>
            <a href="/?page=admin&section=customers" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Customer List
            </a>
        </div>
    </div>
    
    <div class="border-b border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
            <div>
                <h4 class="text-lg font-medium text-gray-900 mb-4">Customer Information</h4>
                <div class="grid grid-cols-1 gap-4">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-500">Customer ID</span>
                        <span class="text-gray-900">#<?php echo htmlspecialchars($customerId); ?></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-500">Full Name</span>
                        <span class="text-gray-900"><?php echo !empty($fullName) ? htmlspecialchars($fullName) : 'Not provided'; ?></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-500">Username</span>
                        <span class="text-gray-900">@<?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-500">Email Address</span>
                        <span class="text-gray-900"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                </div>
                
                <div class="mt-6">
                    <a href="/?page=admin&section=customers&action=edit&id=<?php echo $customerId; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit Customer
                    </a>
                </div>
            </div>
            
            <div>
                <h4 class="text-lg font-medium text-gray-900 mb-4">Customer Statistics</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="block text-sm font-medium text-gray-500">Total Orders</span>
                        <span class="block text-2xl font-bold text-gray-900"><?php echo $stats['totalOrders']; ?></span>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="block text-sm font-medium text-gray-500">Total Spent</span>
                        <span class="block text-2xl font-bold text-gray-900">$<?php echo number_format($stats['totalSpent'], 2); ?></span>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="block text-sm font-medium text-gray-500">Average Order</span>
                        <span class="block text-2xl font-bold text-gray-900">$<?php echo number_format($stats['averageOrderValue'], 2); ?></span>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="block text-sm font-medium text-gray-500">Last Order</span>
                        <span class="block text-lg font-bold text-gray-900"><?php echo $stats['lastOrderDate'] !== 'Never' ? htmlspecialchars($stats['lastOrderDate']) : 'Never'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="p-6">
        <h4 class="text-lg font-medium text-gray-900 mb-4">Order History</h4>
        
        <?php if (empty($customerOrders)): ?>
            <p class="text-gray-500 italic">This customer has not placed any orders yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($customerOrders as $order): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    #<?php echo htmlspecialchars($order['id'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($order['date'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    $<?php echo number_format(floatval($order['total'] ?? 0), 2); ?>
                                </td>
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
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="/?page=admin&section=orders&action=view&id=<?php echo $order['id']; ?>" class="text-indigo-600 hover:text-indigo-900">View Order</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
    } else {
        echo '<div class="bg-white shadow rounded-lg p-6 mb-6 text-center text-red-600">Customer not found.</div>';
    }
}
?>

<!-- Customer Edit Form (shown when editing a specific customer) -->
<?php
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $customerId = $_GET['id'];
    $customerData = null;
    
    // Find the customer
    foreach ($customersData as $customer) {
        if (($customer[0] ?? '') == $customerId) {
            $customerData = $customer;
            break;
        }
    }
    
    if ($customerData) {
        $username = $customerData[1] ?? '';
        $password = $customerData[2] ?? ''; // Note: This would be hashed
        $email = $customerData[3] ?? '';
        $role = $customerData[4] ?? '';
        $roleType = $customerData[5] ?? '';
        $firstName = $customerData[6] ?? '';
        $lastName = $customerData[7] ?? '';
?>
<div class="bg-white shadow rounded-lg overflow-hidden mb-6">
    <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Edit Customer
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                Update customer information
            </p>
        </div>
        <div>
            <a href="/?page=admin&section=customers<?php echo isset($_GET['id']) ? '&action=view&id=' . $_GET['id'] : ''; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Cancel
            </a>
        </div>
    </div>
    
    <div class="p-6">
        <form action="/?page=admin&section=customers" method="POST" class="space-y-6">
            <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customerId); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="firstName" class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" name="firstName" id="firstName" value="<?php echo htmlspecialchars($firstName); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label for="lastName" class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" name="lastName" id="lastName" value="<?php echo htmlspecialchars($lastName); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" id="password" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                    <select id="role" name="role" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                        <option value="Customer" <?php echo $role === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="Admin" <?php echo $role === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
            </div>
            
            <div class="pt-5 border-t border-gray-200">
                <div class="flex justify-end">
                    <button type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="update_customer" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
    } else {
        echo '<div class="bg-white shadow rounded-lg p-6 mb-6 text-center text-red-600">Customer not found.</div>';
    }
}
?>

<?php
// Admin Customers Management
// This page provides tools for managing customer accounts and information

// Prevent direct access
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// Fetch customers data from Node API (MySQL)
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    $apiBase = 'http://localhost:3000';
} else {
    $apiBase = 'https://whimsicalfrog.us';
}
$usersJson = @file_get_contents($apiBase . '/api/users');
$customersData = $usersJson ? json_decode($usersJson, true) : [];
$customers = $customersData;

// Get orders data for customer statistics
$ordersData = fetchData('sales_orders') ?? [];

// Handle search/filter
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$filterRole = isset($_GET['role']) ? $_GET['role'] : 'all';

// Robust filter: if no customers match, show all users
$filtered = false;
if ($filterRole === 'customer') {
    $customers = array_filter($customers, function($user) {
        return isset($user['role']) && strtolower($user['role']) === 'customer';
    });
    $filtered = true;
} elseif ($filterRole === 'admin') {
    $customers = array_filter($customers, function($user) {
        return isset($user['role']) && strtolower($user['role']) === 'admin';
    });
    $filtered = true;
}
// If filter applied and no results, show all users
if ($filtered && empty($customers)) {
    $customers = $customersData;
    $filterRole = 'all';
}

if (!empty($searchTerm)) {
    $customers = array_filter($customers, function($customer) use ($searchTerm) {
        // Search in name, email, or ID
        $firstName = $customer['first_name'] ?? '';
        $lastName = $customer['last_name'] ?? '';
        $email = $customer['email'] ?? '';
        $id = $customer['id'] ?? '';
        
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
            $valA = $a['id'] ?? '';
            $valB = $b['id'] ?? '';
            break;
        case 'email':
            $valA = $a['email'] ?? '';
            $valB = $b['email'] ?? '';
            break;
        case 'orders':
            $statsA = getCustomerStats($a['id'], $GLOBALS['ordersData']);
            $statsB = getCustomerStats($b['id'], $GLOBALS['ordersData']);
            $valA = $statsA['totalOrders'];
            $valB = $statsB['totalOrders'];
            break;
        case 'spent':
            $statsA = getCustomerStats($a['id'], $GLOBALS['ordersData']);
            $statsB = getCustomerStats($b['id'], $GLOBALS['ordersData']);
            $valA = $statsA['totalSpent'];
            $valB = $statsB['totalSpent'];
            break;
        case 'name':
        default:
            $valA = ($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '');
            $valB = ($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '');
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

// Handle customer update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $apiBase = ($_SERVER['SERVER_NAME'] === 'localhost') ? 'http://localhost:3000' : 'https://whimsicalfrog.us';
    $payload = [
        'userId' => $_POST['customer_id'] ?? '',
        'firstName' => $_POST['firstName'] ?? '',
        'lastName' => $_POST['lastName'] ?? '',
        'email' => $_POST['email'] ?? '',
        'addressLine1' => $_POST['addressLine1'] ?? '',
        'addressLine2' => $_POST['addressLine2'] ?? '',
        'city' => $_POST['city'] ?? '',
        'state' => $_POST['state'] ?? '',
        'zipCode' => $_POST['zipCode'] ?? '',
        'phoneNumber' => $_POST['phoneNumber'] ?? '',
        'username' => $_POST['username'] ?? '',
        'role' => $_POST['role'] ?? '',
    ];
    // Remove empty fields so they don't overwrite existing data
    $payload = array_filter($payload, function($v) { return $v !== ''; });
    if (!empty($_POST['password'])) {
        $payload['password'] = $_POST['password'];
    }
    $ch = curl_init($apiBase . '/api/update-user');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);
    if ($result && !empty($result['success'])) {
        $updateSuccess = true;
        // Redirect to view page for this customer with a status message
        $redirectUrl = '/?page=admin&section=customers&action=view&id=' . urlencode($_POST['customer_id']) . '&status=updated';
        echo '<script>window.location.href = "' . $redirectUrl . '";</script>';
        exit;
    } else {
        $updateError = $result['error'] ?? 'Failed to update customer.';
    }
}
?>

<style>
  .admin-form-label {
    color: #222 !important;
  }
  .admin-form-input {
    color: #c00 !important;
    border-color: #c00 !important;
  }
  .admin-data-label {
    color: #222 !important;
  }
  .admin-data-value {
    color: #c00 !important;
    font-weight: bold;
  }
  /* Remove the green heading override */
</style>

<!-- Top bar: Back to Dashboard | Search | Add New Customer -->
<div class="mb-4 flex flex-row justify-between items-center gap-2">
    <a href="/?page=admin" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium rounded-md">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back to Dashboard
    </a>
    <form action="" method="GET" class="flex flex-row items-center gap-2 mb-0" style="flex:1;max-width:600px;justify-content:center;">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="customers">
        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($searchTerm); ?>" class="block w-full px-2 py-1 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500" placeholder="Search..." style="max-width:140px;">
        <select id="role" name="role" class="block px-2 py-1 border border-gray-300 rounded-md text-xs focus:ring-green-500 focus:border-green-500" style="max-width:100px;">
            <option value="all" <?php echo $filterRole === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="customer" <?php echo $filterRole === 'customer' ? 'selected' : ''; ?>>Customers</option>
            <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Admins</option>
        </select>
        <select id="status" name="status" class="block px-2 py-1 border border-gray-300 rounded-md text-xs focus:ring-green-500 focus:border-green-500" style="max-width:100px;">
            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            <option value="new" <?php echo $filterStatus === 'new' ? 'selected' : ''; ?>>New</option>
        </select>
        <button type="submit" class="inline-flex items-center px-2 py-1 border border-transparent rounded-md shadow-sm text-xs font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </button>
    </form>
    <button id="addCustomerBtn" type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Add New Customer
    </button>
</div>

<!-- Add Customer Modal -->
<div id="addCustomerModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-lg relative">
        <button id="closeAddCustomerModal" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 text-2xl">&times;</button>
        <h2 class="text-xl font-bold mb-4 text-gray-800">Add New Customer</h2>
        <form id="addCustomerForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" name="firstName" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" name="lastName" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" name="username" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                    <input type="text" name="phoneNumber" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Address Line 1</label>
                <input type="text" name="addressLine1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Address Line 2</label>
                <input type="text" name="addressLine2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">City</label>
                <input type="text" name="city" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">State</label>
                    <input type="text" name="state" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Zip Code</label>
                    <input type="text" name="zipCode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
            </div>
            <div id="addCustomerError" class="text-red-600 text-sm hidden"></div>
            <div class="flex justify-end gap-2">
                <button type="button" id="cancelAddCustomer" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Add Customer</button>
            </div>
        </form>
    </div>
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
                        <!-- <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('id', $sortBy, $sortDir); ?>" class="flex items-center">
                                ID <?php echo getSortIndicator('id', $sortBy, $sortDir); ?>
                            </a>
                        </th> -->
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
                        $customerId = $customer['id'] ?? '';
                        $username = $customer['username'] ?? '';
                        $email = $customer['email'] ?? '';
                        $firstName = $customer['first_name'] ?? '';
                        $lastName = $customer['last_name'] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                        
                        // Get customer statistics
                        $stats = getCustomerStats($customerId, $ordersData);
                    ?>
                        <tr>
                            <!-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                #<?php echo htmlspecialchars($customerId); ?>
                            </td> -->
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
                                <a href="/?page=admin&section=customers&action=edit&id=<?php echo $customerId; ?>" class="text-green-600 hover:text-green-900 mr-3">Edit</a>
                                <button class="delete-customer-btn bg-red-500 hover:bg-red-700 text-white px-2 py-1 rounded" data-customer-id="<?php echo htmlspecialchars($customerId); ?>">Delete</button>
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
    $statusMsg = '';
    if (isset($_GET['status']) && $_GET['status'] === 'updated') {
        $statusMsg = '<div id="customerStatusMsg" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert"><strong class="font-bold">Success!</strong> Customer updated successfully.</div>';
    }
    
    // Find the customer
    foreach ($customersData as $customer) {
        if (($customer['id'] ?? '') == $customerId) {
            $customerData = $customer;
            break;
        }
    }
    
    if ($customerData) {
        $username = $customerData['username'] ?? '';
        $email = $customerData['email'] ?? '';
        $firstName = $customerData['first_name'] ?? '';
        $lastName = $customerData['last_name'] ?? '';
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
    <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200 flex justify-between items-center" id="customerDetailsHeader">
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
    
    <div id="customerInfoSection" class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
        <div>
            <h4 class="text-lg font-medium text-gray-900 mb-4">Customer Information</h4>
            <div class="grid grid-cols-1 gap-4">
                <div class="flex flex-col">
                    <span class="text-sm font-medium admin-data-label">Customer ID</span>
                    <span class="admin-data-value">#<?php echo htmlspecialchars($customerId); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-medium admin-data-label">Full Name</span>
                    <span class="admin-data-value"><?php echo !empty($fullName) ? htmlspecialchars($fullName) : 'Not provided'; ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-medium admin-data-label">Username</span>
                    <span class="admin-data-value"><?php echo htmlspecialchars($username); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-medium admin-data-label">Email Address</span>
                    <span class="admin-data-value"><?php echo htmlspecialchars($email); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-medium admin-data-label">Address Line 1</span>
                    <span class="admin-data-value"><?php echo htmlspecialchars($customerData['address_line1'] ?? ''); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-medium admin-data-label">Address Line 2</span>
                    <span class="admin-data-value"><?php echo htmlspecialchars($customerData['address_line2'] ?? ''); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-medium admin-data-label">City</span>
                    <span class="admin-data-value"><?php echo htmlspecialchars($customerData['city'] ?? ''); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-medium admin-data-label">State</span>
                    <span class="admin-data-value"><?php echo htmlspecialchars($customerData['state'] ?? ''); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-medium admin-data-label">Zip Code</span>
                    <span class="admin-data-value"><?php echo htmlspecialchars($customerData['zip_code'] ?? ''); ?></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-medium admin-data-label">Phone Number</span>
                    <span class="admin-data-value"><?php echo htmlspecialchars($customerData['phone_number'] ?? ''); ?></span>
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
                    <span class="block text-2xl font-bold admin-data-value"><?php echo $stats['totalOrders']; ?></span>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <span class="block text-sm font-medium text-gray-500">Total Spent</span>
                    <span class="block text-2xl font-bold admin-data-value">$<?php echo number_format($stats['totalSpent'], 2); ?></span>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <span class="block text-sm font-medium text-gray-500">Average Order</span>
                    <span class="block text-2xl font-bold admin-data-value">$<?php echo number_format($stats['averageOrderValue'], 2); ?></span>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <span class="block text-sm font-medium text-gray-500">Last Order</span>
                    <span class="block text-lg font-bold admin-data-value"><?php echo $stats['lastOrderDate'] !== 'Never' ? htmlspecialchars($stats['lastOrderDate']) : 'Never'; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-white shadow rounded-lg p-6 mb-6">
    <h4 class="text-lg font-medium mb-4">Order History</h4>
    <?php if (empty($customerOrders)): ?>
        <p class="italic">This customer has not placed any orders yet.</p>
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
        if (($customer['id'] ?? '') == $customerId) {
            $customerData = $customer;
            break;
        }
    }
    
    if ($customerData) {
        $username = $customerData['username'] ?? '';
        $password = $customerData['password'] ?? ''; // Note: This would be hashed
        $email = $customerData['email'] ?? '';
        $role = $customerData['role'] ?? '';
        $roleType = $customerData['roleType'] ?? '';
        $firstName = $customerData['first_name'] ?? '';
        $lastName = $customerData['last_name'] ?? '';
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
                    <input type="text" name="firstName" id="firstName" value="<?php echo htmlspecialchars($firstName); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="lastName" class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" name="lastName" id="lastName" value="<?php echo htmlspecialchars($lastName); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="addressLine1" class="block text-sm font-medium text-gray-700">Address Line 1</label>
                    <input type="text" name="addressLine1" id="addressLine1" value="<?php echo htmlspecialchars($customerData['address_line1'] ?? ''); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="addressLine2" class="block text-sm font-medium text-gray-700">Address Line 2</label>
                    <input type="text" name="addressLine2" id="addressLine2" value="<?php echo htmlspecialchars($customerData['address_line2'] ?? ''); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                    <input type="text" name="city" id="city" value="<?php echo htmlspecialchars($customerData['city'] ?? ''); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700">State</label>
                    <input type="text" name="state" id="state" value="<?php echo htmlspecialchars($customerData['state'] ?? ''); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="zipCode" class="block text-sm font-medium text-gray-700">Zip Code</label>
                    <input type="text" name="zipCode" id="zipCode" value="<?php echo htmlspecialchars($customerData['zip_code'] ?? ''); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="phoneNumber" class="block text-sm font-medium text-gray-700">Phone Number</label>
                    <input type="text" name="phoneNumber" id="phoneNumber" value="<?php echo htmlspecialchars($customerData['phone_number'] ?? ''); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" id="password" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md admin-data-value">
                </div>
                
                <div>
                    <label for="editRole" class="block text-sm font-medium text-gray-700">Role</label>
                    <select id="editRole" name="role" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md admin-data-value">
                        <option value="Customer" <?php echo $role === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="Admin" <?php echo $role === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
            </div>
            
            <div class="pt-5 border-t border-gray-200">
                <div class="flex justify-end">
                    <button type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 mr-3" onclick="window.location.href='/?page=admin&section=customers<?php echo isset($customerId) ? '&action=view&id=' . $customerId : ''; ?>'">Cancel</button>
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

<?php if (isset($updateSuccess) && $updateSuccess): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong> Customer updated successfully.
    </div>
<?php elseif (isset($updateError)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error:</strong> <?php echo htmlspecialchars($updateError); ?>
    </div>
<?php endif; ?>

<script>
// --- Scroll to edit form on page load if editing ---
(function() {
  const editForm = document.querySelector('form[action*="section=customers"]');
  if (editForm && window.location.search.includes('action=edit')) {
    setTimeout(() => editForm.scrollIntoView({behavior: 'smooth', block: 'center'}), 100);
    editForm.querySelector('input,select,textarea').focus();
  }
})();
// --- Phone number formatting for all phone inputs ---
function formatPhoneInput(input) {
  input.addEventListener('input', function(e) {
    let val = input.value.replace(/\D/g, '');
    if (val.length > 10) val = val.slice(0, 10);
    let formatted = val;
    if (val.length > 6) formatted = `(${val.slice(0,3)}) ${val.slice(3,6)}-${val.slice(6)}`;
    else if (val.length > 3) formatted = `(${val.slice(0,3)}) ${val.slice(3)}`;
    else if (val.length > 0) formatted = `(${val}`;
    input.value = formatted;
  });
  // On submit, strip formatting
  input.form && input.form.addEventListener('submit', function() {
    input.value = input.value.replace(/\D/g, '');
  });
}
document.querySelectorAll('input[name="phoneNumber"], input[name="phone_number"]').forEach(formatPhoneInput);
// --- Format phone number in customer view section ---
function formatPhoneDisplay(num) {
  if (!num) return '';
  const val = num.replace(/\D/g, '');
  if (val.length === 10) return `(${val.slice(0,3)}) ${val.slice(3,6)}-${val.slice(6)}`;
  return num;
}
document.querySelectorAll('.customer-phone-display').forEach(function(span) {
  span.textContent = formatPhoneDisplay(span.textContent);
});
document.querySelectorAll('.delete-customer-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Are you sure you want to delete this customer?')) return;
        const customerId = this.getAttribute('data-customer-id');
        const apiBase = 'https://whimsicalfrog.us';
        const deleteUrl = apiBase + '/api/delete-customer/' + encodeURIComponent(customerId);
        const response = await fetch(deleteUrl, { method: 'DELETE' });
        const data = await response.json();
        if (data.success) {
            alert('Customer deleted.');
            location.reload();
        } else {
            alert('Error deleting customer: ' + (data.error || 'Unknown error'));
        }
    });
});

// Modal open/close logic
const addCustomerBtn = document.getElementById('addCustomerBtn');
const addCustomerModal = document.getElementById('addCustomerModal');
const closeAddCustomerModal = document.getElementById('closeAddCustomerModal');
const cancelAddCustomer = document.getElementById('cancelAddCustomer');

addCustomerBtn.addEventListener('click', () => {
    addCustomerModal.classList.remove('hidden');
});
closeAddCustomerModal.addEventListener('click', () => {
    addCustomerModal.classList.add('hidden');
});
cancelAddCustomer.addEventListener('click', () => {
    addCustomerModal.classList.add('hidden');
});

// Form submit logic
const addCustomerForm = document.getElementById('addCustomerForm');
const addCustomerError = document.getElementById('addCustomerError');
addCustomerForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    addCustomerError.classList.add('hidden');
    const formData = new FormData(addCustomerForm);
    const data = Object.fromEntries(formData.entries());
    // Use local API endpoint
    const apiBase = 'https://whimsicalfrog.us';
    try {
        const response = await fetch(apiBase + '/api/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...data, recaptchaToken: 'bypass' }) // bypass recaptcha for admin
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Registration failed');
        addCustomerModal.classList.add('hidden');
        location.reload();
    } catch (err) {
        addCustomerError.textContent = err.message;
        addCustomerError.classList.remove('hidden');
    }
});

// --- Scroll to status message if present, otherwise scroll to customer info section (with header offset, after DOMContentLoaded) ---
document.addEventListener('DOMContentLoaded', function() {
  const statusMsg = document.getElementById('customerStatusMsg');
  const HEADER_OFFSET = 60; // <-- Adjust this value to match your fixed header height in px
  function scrollWithOffset(el) {
    if (!el) return;
    el.scrollIntoView({behavior: 'smooth', block: 'start'});
    window.scrollBy({top: -HEADER_OFFSET, left: 0, behavior: 'smooth'});
  }
  setTimeout(function() {
    if (statusMsg) {
      scrollWithOffset(statusMsg);
    } else {
      const infoSection = document.getElementById('customerInfoSection');
      scrollWithOffset(infoSection);
    }
  }, 200);
});
</script>

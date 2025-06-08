<?php
// Admin Customers Management
// This page provides tools for managing customer accounts and viewing customer data

// Prevent direct access
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// Include database configuration
require_once 'api/config.php';

// Initialize arrays to prevent null values
$customersData = [];
$ordersData = [];

try {
    // Create a PDO connection
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Fetch customers/users data directly from database
    $stmt = $pdo->query('SELECT * FROM users');
    $customersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch orders data directly from database
    $ordersStmt = $pdo->query('SELECT * FROM orders');
    $ordersData = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the connection
    $pdo = null;
} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log('General Error: ' . $e->getMessage());
}

// Handle search/filter
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$filterRole = isset($_GET['role']) ? $_GET['role'] : 'all';

// Filter customers based on search term
if (!empty($searchTerm)) {
    $customersData = array_filter($customersData, function($customer) use ($searchTerm) {
        $firstName = $customer['first_name'] ?? '';
        $lastName = $customer['last_name'] ?? '';
        $email = $customer['email'] ?? '';
        $username = $customer['username'] ?? '';
        
        return (stripos($firstName, $searchTerm) !== false ||
                stripos($lastName, $searchTerm) !== false ||
                stripos($email, $searchTerm) !== false ||
                stripos($username, $searchTerm) !== false);
    });
}

// Filter by role
if ($filterRole !== 'all') {
    $customersData = array_filter($customersData, function($customer) use ($filterRole) {
        return isset($customer['role']) && strtolower($customer['role']) === strtolower($filterRole);
    });
}

// Sort customers
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sortDir = isset($_GET['dir']) ? $_GET['dir'] : 'asc';

usort($customersData, function($a, $b) use ($sortBy, $sortDir) {
    switch($sortBy) {
        case 'name':
            $aName = ($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '');
            $bName = ($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '');
            $valA = $aName;
            $valB = $bName;
            break;
        case 'email':
            $valA = $a['email'] ?? '';
            $valB = $b['email'] ?? '';
            break;
        case 'role':
            $valA = $a['role'] ?? '';
            $valB = $b['role'] ?? '';
            break;
        case 'orders':
            $aId = $a['id'] ?? '';
            $bId = $b['id'] ?? '';
            
            $aOrders = array_filter($ordersData, function($order) use ($aId) {
                return isset($order['userId']) && $order['userId'] === $aId;
            });
            
            $bOrders = array_filter($ordersData, function($order) use ($bId) {
                return isset($order['userId']) && $order['userId'] === $bId;
            });
            
            $valA = count($aOrders);
            $valB = count($bOrders);
            break;
        default:
            $valA = $a['id'] ?? '';
            $valB = $b['id'] ?? '';
    }
    
    if ($sortDir === 'asc') {
        return $valA <=> $valB;
    } else {
        return $valB <=> $valA;
    }
});

// Pagination
$itemsPerPage = 10;
$totalItems = count($customersData);
$totalPages = ceil($totalItems / $itemsPerPage);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

$paginatedCustomers = array_slice($customersData, $offset, $itemsPerPage);

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

// Helper function to get customer orders
function getCustomerOrders($customerId, $ordersData) {
    return array_filter($ordersData, function($order) use ($customerId) {
        return isset($order['userId']) && $order['userId'] === $customerId;
    });
}

// Process customer deletion if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $customerId = $_POST['customer_id'] ?? '';
    
    try {
        // Create a PDO connection
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Delete customer from database
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $result = $stmt->execute([$customerId]);
        
        // Close the connection
        $pdo = null;
        
        if ($result) {
            $deleteSuccess = true;
            // Remove from the array to update the view
            $customersData = array_filter($customersData, function($customer) use ($customerId) {
                return ($customer['id'] ?? '') !== $customerId;
            });
            $paginatedCustomers = array_slice($customersData, $offset, $itemsPerPage);
        } else {
            $deleteError = 'Failed to delete customer.';
        }
    } catch (PDOException $e) {
        $deleteError = 'Database error: ' . $e->getMessage();
    } catch (Exception $e) {
        $deleteError = 'Unexpected error: ' . $e->getMessage();
    }
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

<!-- Top bar: Back to Dashboard | Search/Filters | Add New Customer -->
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
        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($searchTerm); ?>" class="block w-full px-2 py-1 border border-gray-300 rounded-md text-sm focus:ring-purple-500 focus:border-purple-500" placeholder="Search..." style="max-width:140px;">
        <select id="role" name="role" class="block px-2 py-1 border border-gray-300 rounded-md text-xs focus:ring-purple-500 focus:border-purple-500" style="max-width:100px;">
            <option value="all" <?php echo $filterRole === 'all' ? 'selected' : ''; ?>>All Roles</option>
            <option value="customer" <?php echo $filterRole === 'customer' ? 'selected' : ''; ?>>Customer</option>
            <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
        </select>
        <button type="submit" class="inline-flex items-center px-2 py-1 border border-transparent rounded-md shadow-sm text-xs font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </button>
    </form>
    <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500" onclick="window.location.href='/?page=admin&section=customers&action=add'">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Add Customer
    </button>
</div>

<!-- Delete Success Message -->
<?php if (isset($deleteSuccess) && $deleteSuccess): ?>
<div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm text-green-700">
                Customer deleted successfully.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Error Message -->
<?php if (isset($deleteError)): ?>
<div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm text-red-700">
                <?php echo htmlspecialchars($deleteError); ?>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

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
                            <a href="<?php echo getSortUrl('name', $sortBy, $sortDir); ?>" class="flex items-center">
                                <span class="admin-data-label">Customer</span> <?php echo getSortIndicator('name', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('email', $sortBy, $sortDir); ?>" class="flex items-center">
                                <span class="admin-data-label">Email</span> <?php echo getSortIndicator('email', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('role', $sortBy, $sortDir); ?>" class="flex items-center">
                                <span class="admin-data-label">Role</span> <?php echo getSortIndicator('role', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('orders', $sortBy, $sortDir); ?>" class="flex items-center">
                                <span class="admin-data-label">Orders</span> <?php echo getSortIndicator('orders', $sortBy, $sortDir); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <span class="admin-data-label">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($paginatedCustomers as $customer): 
                        $customerId = $customer['id'] ?? '';
                        $firstName = $customer['first_name'] ?? '';
                        $lastName = $customer['last_name'] ?? '';
                        $email = $customer['email'] ?? '';
                        $role = $customer['role'] ?? '';
                        $customerOrders = getCustomerOrders($customerId, $ordersData);
                        $orderCount = count($customerOrders);
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center">
                                            <span class="text-purple-600 font-medium">
                                                <?php 
                                                $initials = 'CU';
                                                if ($firstName && $lastName) {
                                                    $initials = substr($firstName, 0, 1) . substr($lastName, 0, 1);
                                                } elseif ($firstName) {
                                                    $initials = substr($firstName, 0, 2);
                                                } elseif ($lastName) {
                                                    $initials = substr($lastName, 0, 2);
                                                }
                                                echo strtoupper($initials);
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><span class="admin-data-value"><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></span></div>
                                        <div class="text-sm text-gray-500"><span class="admin-data-label">Username:</span> <span class="admin-data-value"><?php echo htmlspecialchars($customer['username'] ?? ''); ?></span></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><span class="admin-data-value"><?php echo htmlspecialchars($email); ?></span></div>
                                <div class="text-sm text-gray-500">
                                    <?php if (!empty($customer['phone_number'])): ?>
                                        <span class="admin-data-label">Phone:</span> <span class="admin-data-value"><?php echo htmlspecialchars($customer['phone_number']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $roleColor = 'gray';
                                
                                switch(strtolower($role)) {
                                    case 'admin':
                                        $roleColor = 'purple';
                                        break;
                                    case 'customer':
                                        $roleColor = 'green';
                                        break;
                                    default:
                                        $roleColor = 'gray';
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $roleColor; ?>-100 text-<?php echo $roleColor; ?>-800">
                                    <span class="admin-data-value"><?php echo htmlspecialchars($role); ?></span>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="admin-data-value"><?php echo $orderCount; ?></span> orders
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="/?page=admin&section=customers&action=view&id=<?php echo $customerId; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                <a href="/?page=admin&section=customers&action=edit&id=<?php echo $customerId; ?>" class="text-purple-600 hover:text-purple-900 mr-3">Edit</a>
                                <button type="button" onclick="confirmDelete('<?php echo $customerId; ?>', '<?php echo htmlspecialchars(addslashes($firstName . ' ' . $lastName)); ?>')" class="text-red-600 hover:text-red-900">Delete</button>
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
                                    ? 'z-10 bg-purple-50 border-purple-500 text-purple-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium'
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

<!-- Delete Confirmation Modal (hidden by default) -->
<div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Delete Customer
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="modal-message">
                                Are you sure you want to delete this customer? This action cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <form action="" method="POST">
                    <input type="hidden" name="customer_id" id="delete_customer_id">
                    <button type="submit" name="delete_customer" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Delete
                    </button>
                </form>
                <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Customer Detail View -->
<?php
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
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
        $firstName = $customerData['first_name'] ?? '';
        $lastName = $customerData['last_name'] ?? '';
        $email = $customerData['email'] ?? '';
        $role = $customerData['role'] ?? '';
        $phoneNumber = $customerData['phone_number'] ?? '';
        $addressLine1 = $customerData['address_line1'] ?? '';
        $addressLine2 = $customerData['address_line2'] ?? '';
        $city = $customerData['city'] ?? '';
        $state = $customerData['state'] ?? '';
        $zipCode = $customerData['zip_code'] ?? '';
        
        // Get customer orders
        $customerOrders = getCustomerOrders($customerId, $ordersData);
?>
<div class="bg-white shadow rounded-lg overflow-hidden mb-6">
    <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Customer Profile
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                Customer ID: <span class="admin-data-value"><?php echo htmlspecialchars($customerId); ?></span>
            </p>
        </div>
        <div>
            <a href="/?page=admin&section=customers" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Customers
            </a>
        </div>
    </div>
    
    <!-- Customer Information -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-b border-gray-200">
        <div>
            <h4 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h4>
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 h-16 w-16">
                    <div class="h-16 w-16 rounded-full bg-purple-100 flex items-center justify-center">
                        <span class="text-purple-600 font-medium text-xl">
                            <?php 
                            $initials = 'CU';
                            if ($firstName && $lastName) {
                                $initials = substr($firstName, 0, 1) . substr($lastName, 0, 1);
                            } elseif ($firstName) {
                                $initials = substr($firstName, 0, 2);
                            } elseif ($lastName) {
                                $initials = substr($lastName, 0, 2);
                            }
                            echo strtoupper($initials);
                            ?>
                        </span>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-lg font-medium text-gray-900">
                        <span class="admin-data-value"><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></span>
                    </div>
                    <div class="text-sm text-gray-500">
                        <span class="admin-data-label">Username:</span> <span class="admin-data-value"><?php echo htmlspecialchars($customerData['username'] ?? ''); ?></span>
                    </div>
                    <div class="text-sm text-gray-500">
                        <span class="admin-data-label">Email:</span> <span class="admin-data-value"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                    <div class="text-sm text-gray-500">
                        <span class="admin-data-label">Role:</span> <span class="admin-data-value"><?php echo htmlspecialchars($role); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Contact Information</h5>
                <?php if (!empty($phoneNumber)): ?>
                    <div class="text-sm text-gray-500 mb-1">
                        <span class="admin-data-label">Phone:</span> <span class="admin-data-value"><?php echo htmlspecialchars($phoneNumber); ?></span>
                    </div>
                <?php endif; ?>
                
                <h5 class="text-sm font-medium text-gray-700 mt-4 mb-2">Address</h5>
                <?php if (!empty($addressLine1)): ?>
                    <div class="text-sm text-gray-500 mb-1">
                        <span class="admin-data-value"><?php echo htmlspecialchars($addressLine1); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($addressLine2)): ?>
                    <div class="text-sm text-gray-500 mb-1">
                        <span class="admin-data-value"><?php echo htmlspecialchars($addressLine2); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($city) || !empty($state) || !empty($zipCode)): ?>
                    <div class="text-sm text-gray-500 mb-1">
                        <span class="admin-data-value">
                            <?php echo htmlspecialchars($city); ?>
                            <?php if (!empty($city) && !empty($state)) echo ', '; ?>
                            <?php echo htmlspecialchars($state); ?>
                            <?php if (!empty($zipCode)) echo ' ' . htmlspecialchars($zipCode); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-6 flex space-x-3">
                <a href="/?page=admin&section=customers&action=edit&id=<?php echo $customerId; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Profile
                </a>
                <button type="button" onclick="confirmDelete('<?php echo $customerId; ?>', '<?php echo htmlspecialchars(addslashes($firstName . ' ' . $lastName)); ?>')" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Delete Customer
                </button>
            </div>
        </div>
        
        <div>
            <h4 class="text-lg font-medium text-gray-900 mb-4">Order History</h4>
            <?php if (empty($customerOrders)): ?>
                <p class="text-gray-500 italic">No orders found for this customer.</p>
            <?php else: ?>
                <div class="overflow-y-auto max-h-96">
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
                            <?php foreach ($customerOrders as $order): 
                                $orderId = $order['id'] ?? '';
                                $orderDate = $order['date'] ?? '';
                                $total = $order['total'] ?? 0;
                                $status = $order['status'] ?? 'Pending';
                            ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="admin-data-value">#<?php echo htmlspecialchars($orderId); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="admin-data-value"><?php echo htmlspecialchars($orderDate); ?></span>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="/?page=admin&section=orders&action=view&id=<?php echo $orderId; ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
    } else {
        echo '<div class="bg-white shadow rounded-lg p-6 mb-6 text-center text-red-600">Customer not found.</div>';
    }
}
?>

<script>
function confirmDelete(customerId, customerName) {
    document.getElementById('delete_customer_id').value = customerId;
    document.getElementById('modal-message').innerText = `Are you sure you want to delete ${customerName}? This action cannot be undone.`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}
</script>

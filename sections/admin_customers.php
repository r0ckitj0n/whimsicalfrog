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

// Initialize modal state
$modalMode = ''; // Default to no modal unless 'view' or 'edit' is in URL
$editCustomer = null;
$customerOrders = [];

// Check if we're in view mode
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $customerIdToView = $_GET['view'];
    $editCustomer = array_filter($customersData, function($customer) use ($customerIdToView) {
        return ($customer['id'] ?? '') === $customerIdToView;
    });
    $editCustomer = !empty($editCustomer) ? array_values($editCustomer)[0] : null;

    if ($editCustomer) {
        $modalMode = 'view';
        // Get customer orders
        $customerOrders = getCustomerOrders($editCustomer['id'], $ordersData);
    }
}
// Check if we're in edit mode
elseif (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $customerIdToEdit = $_GET['edit'];
    $editCustomer = array_filter($customersData, function($customer) use ($customerIdToEdit) {
        return ($customer['id'] ?? '') === $customerIdToEdit;
    });
    $editCustomer = !empty($editCustomer) ? array_values($editCustomer)[0] : null;

    if ($editCustomer) {
        $modalMode = 'edit';
        // Get customer orders
        $customerOrders = getCustomerOrders($editCustomer['id'], $ordersData);
    }
}

// Handle search/filter
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$filterRole = isset($_GET['role']) ? $_GET['role'] : 'all';

// Filter customers based on search term
if (!empty($searchTerm)) {
    $customersData = array_filter($customersData, function($customer) use ($searchTerm) {
        $firstName = $customer['firstName'] ?? '';
        $lastName = $customer['lastName'] ?? '';
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
            $aName = ($a['firstName'] ?? '') . ' ' . ($a['lastName'] ?? '');
            $bName = ($b['firstName'] ?? '') . ' ' . ($b['lastName'] ?? '');
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

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';
?>

<style>
    /* Force the customers title to be green with highest specificity */
    h1.customers-title.text-2xl.font-bold {
        color: #87ac3a !important;
    }
    
    /* Brand button styling */
    .brand-button {
        background-color: #87ac3a !important;
        color: white !important;
        transition: background-color 0.3s ease;
    }
    
    .brand-button:hover {
        background-color: #6b8e23 !important; /* Darker shade for hover */
    }
    
    .toast-notification {
        position: fixed; top: 20px; right: 20px; padding: 12px 20px;
        border-radius: 4px; color: white; font-weight: 500; z-index: 9999;
        opacity: 0; transform: translateY(-20px); box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: opacity 0.3s, transform 0.3s;
    }
    .toast-notification.show { opacity: 1; transform: translateY(0); }
    .toast-notification.success { background-color: #48bb78; } /* Tailwind green-500 */
    .toast-notification.error { background-color: #f56565; } /* Tailwind red-500 */
    .toast-notification.info { background-color: #4299e1; } /* Tailwind blue-500 */

    .customers-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
    .customers-table th { background-color: #87ac3a; color: white; padding: 10px 12px; text-align: left; font-weight: 600; font-size: 0.8rem; position: sticky; top: 0; z-index: 10; }
    .customers-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 0.85rem; }
    .customers-table tr:hover { background-color: #f7fafc; }
    .customers-table th:first-child { border-top-left-radius: 6px; }
    .customers-table th:last-child { border-top-right-radius: 6px; }

    .action-btn { padding: 5px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; border: none; }
    .view-btn { background-color: #4299e1; color: white; } .view-btn:hover { background-color: #3182ce; }
    .edit-btn { background-color: #f59e0b; color: white; } .edit-btn:hover { background-color: #d97706; }
    .delete-btn { background-color: #f56565; color: white; } .delete-btn:hover { background-color: #e53e3e; }

    .admin-data-label {
        color: #222 !important;
    }
    .admin-data-value {
        color: #c00 !important;
        font-weight: bold;
    }

    .modal-outer { position: fixed; inset: 0; background-color: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 1rem; }
    .modal-content-wrapper { background-color: white; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); padding: 1.25rem; width: 100%; max-width: 60rem; max-height: 90vh; display: flex; flex-direction: column; }
    .modal-form-container { flex-grow: 1; display: flex; flex-direction: column; padding-right: 0.5rem; min-height: 0; }
    @media (min-width: 768px) { .modal-form-container { flex-direction: row; } }
    .modal-form-main-column { flex: 1; padding-right: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; overflow-y: auto; min-height: 0; }
    @media (max-width: 767px) { .modal-form-main-column { padding-right: 0; } }
    .modal-form-side-column { width: 100%; padding-left: 0; margin-top: 1rem; min-height: 0; }
    @media (min-width: 768px) { .modal-form-side-column { flex: 0 0 40%; padding-left: 0.75rem; margin-top: 0; } }

    .cost-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 100; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
    .cost-modal.show { opacity: 1; pointer-events: auto; }
    .cost-modal-content { background-color: white; border-radius: 8px; padding: 1rem; width: 100%; max-width: 380px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transform: scale(0.95); transition: transform 0.3s; }
    .cost-modal.show .cost-modal-content { transform: scale(1); }
    .cost-modal-content label { font-size: 0.8rem; }
    .cost-modal-content input { font-size: 0.85rem; padding: 0.4rem 0.6rem; }
    .cost-modal-content button { font-size: 0.85rem; padding: 0.4rem 0.8rem; }

    .order-item { padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; background-color: #f9fafb; }
    .order-item h5 { color: #374151; font-size: 0.9rem; font-weight: 600; margin-bottom: 4px; }
    .order-detail { display: flex; justify-content: space-between; font-size: 0.8rem; color: #6b7280; margin-bottom: 2px; }
    .order-detail span:last-child { font-weight: 600; color: #1f2937; }
    .order-status { padding: 2px 6px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
    .status-pending { background-color: #fef3c7; color: #92400e; }
    .status-processing { background-color: #dbeafe; color: #1e40af; }
    .status-shipped { background-color: #dcfce7; color: #166534; }
    .status-delivered { background-color: #d1fae5; color: #065f46; }
    .status-cancelled { background-color: #fee2e2; color: #991b1b; }

    /* Navigation Arrow Styling */
    .nav-arrow {
        position: fixed;
        top: 50%;
        transform: translateY(-50%);
        z-index: 60; /* Higher than modal z-index */
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(4px);
        color: white;
        border: none;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .nav-arrow:hover {
        background: rgba(0, 0, 0, 0.5);
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    }
    
    .nav-arrow:active {
        transform: translateY(-50%) scale(0.95);
    }
    
    .nav-arrow svg {
        width: 24px;
        height: 24px;
        stroke-width: 2.5;
    }
    
    .nav-arrow.left {
        left: 20px;
    }
    
    .nav-arrow.right {
        right: 20px;
    }
    
    /* Hide arrows on smaller screens to avoid overlap */
    @media (max-width: 768px) {
        .nav-arrow {
            display: none;
        }
    }
</style>

<div class="container mx-auto px-4 py-2">
    <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        
        <form method="GET" action="" class="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="customers">
            <input type="text" name="search" placeholder="Search customers..." class="p-2 border border-gray-300 rounded text-sm flex-grow" value="<?= htmlspecialchars($searchTerm); ?>">
            <select name="role" class="p-2 border border-gray-300 rounded text-sm flex-grow">
                <option value="all">All Roles</option>
                <option value="customer" <?= ($filterRole === 'customer') ? 'selected' : ''; ?>>Customer</option>
                <option value="admin" <?= ($filterRole === 'admin') ? 'selected' : ''; ?>>Admin</option>
            </select>
            <button type="submit" class="brand-button p-2 rounded text-sm">Filter</button>
        </form>
    </div>
    
    <?php if ($message): ?>
        <div class="mb-4 p-3 rounded text-white <?= $messageType === 'success' ? 'bg-green-500' : 'bg-red-500'; ?>">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="customers-table">
            <thead>
                <tr>
                    <th>Customer</th><th>Email</th><th>Role</th><th>Orders</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($paginatedCustomers)): ?>
                    <tr><td colspan="5" class="text-center py-4">No customers found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($paginatedCustomers as $customer): 
                        $customerId = $customer['id'] ?? '';
                        $firstName = $customer['firstName'] ?? '';
                        $lastName = $customer['lastName'] ?? '';
                        $email = $customer['email'] ?? '';
                        $role = $customer['role'] ?? '';
                        $customerOrdersForCount = getCustomerOrders($customerId, $ordersData);
                        $orderCount = count($customerOrdersForCount);
                    ?>
                    <tr class="hover:bg-gray-50" data-customer-id="<?= htmlspecialchars($customerId) ?>">
                        <td>
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                        <span class="text-green-600 font-medium text-sm">
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
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($firstName . ' ' . $lastName); ?></div>
                                    <div class="text-xs text-gray-500">@<?= htmlspecialchars($customer['username'] ?? ''); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($email); ?></td>
                        <td>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                <?= htmlspecialchars($role); ?>
                            </span>
                        </td>
                        <td class="text-sm text-gray-600"><?= $orderCount; ?> orders</td>
                        <td>
                            <a href="?page=admin&section=customers&view=<?= htmlspecialchars($customerId) ?>" class="action-btn view-btn" title="View Customer">👁️</a>
                            <a href="?page=admin&section=customers&edit=<?= htmlspecialchars($customerId) ?>" class="action-btn edit-btn" title="Edit Customer">✏️</a>
                            <button onclick="confirmDelete('<?= $customerId; ?>', '<?= htmlspecialchars(addslashes($firstName . ' ' . $lastName)); ?>')" class="action-btn delete-btn" title="Delete Customer">🗑️</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="cost-modal">
    <div class="cost-modal-content max-w-sm">
        <h2 class="text-md font-bold mb-3 text-gray-800">Confirm Delete</h2>
        <p class="mb-4 text-sm text-gray-600" id="modal-message">Are you sure you want to delete this customer? This action cannot be undone.</p>
        <div class="flex justify-end space-x-2">
            <button type="button" class="px-3 py-1.5 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm" onclick="closeModal()">Cancel</button>
            <form action="" method="POST" style="display: inline;">
                <input type="hidden" name="customer_id" id="delete_customer_id">
                <button type="submit" name="delete_customer" class="px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700 text-sm">Delete</button>
            </form>
        </div>
    </div>
</div>

<?php if (($modalMode === 'view' || $modalMode === 'edit') && $editCustomer): ?>
<div class="modal-outer" id="customerModalOuter">
    <!-- Navigation Arrows -->
    <button id="prevCustomerBtn" onclick="navigateToCustomer('prev')" class="nav-arrow left" title="Previous customer">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
        </svg>
    </button>
    <button id="nextCustomerBtn" onclick="navigateToCustomer('next')" class="nav-arrow right" title="Next customer">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
        </svg>
    </button>
    
    <div class="modal-content-wrapper">
        <div class="flex justify-between items-center mb-3">
            <h2 class="text-lg font-bold text-green-700">
                <?= $modalMode === 'view' ? 'View Customer: ' : 'Edit Customer: ' ?>
                <?= htmlspecialchars(($editCustomer['firstName'] ?? '') . ' ' . ($editCustomer['lastName'] ?? '')) ?>
            </h2>
            <a href="?page=admin&section=customers" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</a>
        </div>

        <?php if ($modalMode === 'edit'): ?>
        <form id="customerForm" method="POST" action="#" class="flex flex-col flex-grow overflow-hidden">
            <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="customerId" value="<?= htmlspecialchars($editCustomer['id'] ?? ''); ?>">
        <?php endif; ?>

        <div class="modal-form-container gap-5">
            <div class="modal-form-main-column">
                <?php
                $firstName = $editCustomer['firstName'] ?? '';
                $lastName = $editCustomer['lastName'] ?? '';
                $email = $editCustomer['email'] ?? '';
                $username = $editCustomer['username'] ?? '';
                $role = $editCustomer['role'] ?? '';
                $phoneNumber = $editCustomer['phoneNumber'] ?? '';
                $addressLine1 = $editCustomer['addressLine1'] ?? '';
                $addressLine2 = $editCustomer['addressLine2'] ?? '';
                $city = $editCustomer['city'] ?? '';
                $state = $editCustomer['state'] ?? '';
                $zipCode = $editCustomer['zipCode'] ?? '';
                
                $initials = ($firstName && $lastName) ? 
                    (substr($firstName, 0, 1) . substr($lastName, 0, 1)) : 
                    (substr($firstName ?: $lastName ?: 'CU', 0, 2));
                $initials = strtoupper($initials);
                ?>
                
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 h-16 w-16">
                        <div class="h-16 w-16 rounded-full bg-green-100 flex items-center justify-center">
                            <span class="text-green-600 font-medium text-xl"><?= $initials ?></span>
                        </div>
                    </div>
                    <div class="ml-4 flex-grow">
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Personal Information</h4>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="firstName" class="block text-gray-700">First Name</label>
                        <?php if ($modalMode === 'edit'): ?>
                            <input type="text" id="firstName" name="firstName" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                                   value="<?= htmlspecialchars($firstName) ?>" required>
                        <?php else: ?>
                            <input type="text" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                                   value="<?= htmlspecialchars($firstName) ?>">
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="lastName" class="block text-gray-700">Last Name</label>
                        <?php if ($modalMode === 'edit'): ?>
                            <input type="text" id="lastName" name="lastName" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                                   value="<?= htmlspecialchars($lastName) ?>" required>
                        <?php else: ?>
                            <input type="text" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                                   value="<?= htmlspecialchars($lastName) ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="username" class="block text-gray-700">Username</label>
                        <?php if ($modalMode === 'edit'): ?>
                            <input type="text" id="username" name="username" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                                   value="<?= htmlspecialchars($username) ?>" required>
                        <?php else: ?>
                            <input type="text" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                                   value="<?= htmlspecialchars($username) ?>">
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="email" class="block text-gray-700">Email</label>
                        <?php if ($modalMode === 'edit'): ?>
                            <input type="email" id="email" name="email" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                                   value="<?= htmlspecialchars($email) ?>" required>
                        <?php else: ?>
                            <input type="email" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                                   value="<?= htmlspecialchars($email) ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="role" class="block text-gray-700">Role</label>
                        <?php if ($modalMode === 'edit'): ?>
                            <select id="role" name="role" class="mt-1 block w-full p-2 border border-gray-300 rounded" required>
                                <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        <?php else: ?>
                            <input type="text" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                                   value="<?= htmlspecialchars($role) ?>">
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="phoneNumber" class="block text-gray-700">Phone Number</label>
                        <?php if ($modalMode === 'edit'): ?>
                            <input type="tel" id="phoneNumber" name="phoneNumber" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                                   value="<?= htmlspecialchars($phoneNumber) ?>">
                        <?php else: ?>
                            <input type="tel" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                                   value="<?= htmlspecialchars($phoneNumber) ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <h5 class="text-sm font-medium text-gray-700 mb-2">Address Information</h5>
                </div>

                <div>
                    <label for="addressLine1" class="block text-gray-700">Address Line 1</label>
                    <?php if ($modalMode === 'edit'): ?>
                        <input type="text" id="addressLine1" name="addressLine1" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                               value="<?= htmlspecialchars($addressLine1) ?>">
                    <?php else: ?>
                        <input type="text" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($addressLine1) ?>">
                    <?php endif; ?>
                </div>

                <div>
                    <label for="addressLine2" class="block text-gray-700">Address Line 2</label>
                    <?php if ($modalMode === 'edit'): ?>
                        <input type="text" id="addressLine2" name="addressLine2" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                               value="<?= htmlspecialchars($addressLine2) ?>">
                    <?php else: ?>
                        <input type="text" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($addressLine2) ?>">
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label for="city" class="block text-gray-700">City</label>
                        <?php if ($modalMode === 'edit'): ?>
                            <input type="text" id="city" name="city" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                                   value="<?= htmlspecialchars($city) ?>">
                        <?php else: ?>
                            <input type="text" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                                   value="<?= htmlspecialchars($city) ?>">
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="state" class="block text-gray-700">State</label>
                        <?php if ($modalMode === 'edit'): ?>
                            <input type="text" id="state" name="state" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                                   value="<?= htmlspecialchars($state) ?>">
                        <?php else: ?>
                            <input type="text" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                                   value="<?= htmlspecialchars($state) ?>">
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="zipCode" class="block text-gray-700">ZIP Code</label>
                        <?php if ($modalMode === 'edit'): ?>
                            <input type="text" id="zipCode" name="zipCode" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                                   value="<?= htmlspecialchars($zipCode) ?>">
                        <?php else: ?>
                            <input type="text" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                                   value="<?= htmlspecialchars($zipCode) ?>">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="modal-form-side-column">
                <div class="bg-gray-50 border-radius: 6px; padding: 10px; border: 1px solid #e2e8f0; height: 100%; display: flex; flex-direction: column;">
                    <h3 class="color: #374151; font-size: 1rem; font-weight: 600; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #d1d5db;">Order History</h3>
                    <div style="flex-grow: 1; overflow-y: auto; min-height: 200px; max-height: 600px;">
                        <?php if (empty($customerOrders)): ?>
                            <p class="text-gray-500 italic text-sm">No orders found for this customer.</p>
                        <?php else: ?>
                            <?php foreach ($customerOrders as $order): 
                                $orderDate = date('M j, Y', strtotime($order['date'] ?? $order['createdAt'] ?? 'now'));
                                $statusClass = 'status-' . strtolower($order['status'] ?? 'pending');
                            ?>
                            <div class="order-item mb-3 p-2 bg-white rounded border">
                                <h5 class="font-medium text-sm">Order #<?= htmlspecialchars($order['id'] ?? '') ?></h5>
                                <div class="text-xs text-gray-600 mt-1">
                                    <div>Date: <?= $orderDate ?></div>
                                    <div>Total: $<?= number_format(floatval($order['total'] ?? $order['totalAmount'] ?? 0), 2) ?></div>
                                    <div>Status: <span class="<?= $statusClass ?>"><?= htmlspecialchars($order['status'] ?? 'Pending') ?></span></div>
                                    <div>Payment: <?= htmlspecialchars($order['paymentMethod'] ?? 'N/A') ?> - <?= htmlspecialchars($order['paymentStatus'] ?? 'N/A') ?></div>
                                    <?php if (!empty($order['shippingMethod'])): ?>
                                    <div>Shipping: <?= htmlspecialchars($order['shippingMethod'] ?? 'N/A') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-auto pt-4 border-t">
            <a href="?page=admin&section=customers" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block text-sm">
                <?= $modalMode === 'edit' ? 'Cancel' : 'Close' ?>
            </a>
            <?php if ($modalMode === 'view'): ?>
                <a href="?page=admin&section=customers&edit=<?= htmlspecialchars($editCustomer['id'] ?? '') ?>" class="brand-button px-4 py-2 rounded text-sm">Edit Customer</a>
            <?php else: ?>
                <button type="submit" id="saveCustomerBtn" class="brand-button px-4 py-2 rounded text-sm">
                    <span class="button-text">Save Changes</span>
                    <span class="loading-spinner hidden"></span>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($modalMode === 'edit'): ?>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
// Initialize variables
var modalMode = <?= json_encode($modalMode ?? '') ?>;
var currentCustomerId = <?= json_encode(isset($editCustomer['id']) ? $editCustomer['id'] : '') ?>;

// Initialize customers list for navigation
var allCustomers = <?= json_encode(array_values($customersData)) ?>;
var currentCustomerIndex = -1;

// Find current customer index if we're in view/edit mode
if (currentCustomerId && allCustomers.length > 0) {
    currentCustomerIndex = allCustomers.findIndex(customer => customer.id === currentCustomerId);
}

// Navigation functions
function navigateToCustomer(direction) {
    if (allCustomers.length === 0) return;
    
    let newIndex = currentCustomerIndex;
    
    if (direction === 'prev') {
        newIndex = currentCustomerIndex > 0 ? currentCustomerIndex - 1 : allCustomers.length - 1;
    } else if (direction === 'next') {
        newIndex = currentCustomerIndex < allCustomers.length - 1 ? currentCustomerIndex + 1 : 0;
    }
    
    if (newIndex !== currentCustomerIndex && newIndex >= 0 && newIndex < allCustomers.length) {
        const targetCustomer = allCustomers[newIndex];
        const currentMode = modalMode === 'view' ? 'view' : 'edit';
        let newUrl = `?page=admin&section=customers&${currentMode}=${encodeURIComponent(targetCustomer.id)}`;
        
        // Preserve any existing search/filter parameters
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('search')) newUrl += `&search=${encodeURIComponent(urlParams.get('search'))}`;
        if (urlParams.get('role')) newUrl += `&role=${encodeURIComponent(urlParams.get('role'))}`;
        
        window.location.href = newUrl;
    }
}

// Update navigation button states
function updateCustomerNavigationButtons() {
    const prevBtn = document.getElementById('prevCustomerBtn');
    const nextBtn = document.getElementById('nextCustomerBtn');
    
    if (prevBtn && nextBtn && allCustomers.length > 0) {
        // Always enable buttons for circular navigation
        prevBtn.style.display = 'block';
        nextBtn.style.display = 'block';
        
        // Add customer counter to buttons for better UX
        const customerCounter = `${currentCustomerIndex + 1} of ${allCustomers.length}`;
        const currentCustomer = allCustomers[currentCustomerIndex];
        const prevIndex = currentCustomerIndex > 0 ? currentCustomerIndex - 1 : allCustomers.length - 1;
        const nextIndex = currentCustomerIndex < allCustomers.length - 1 ? currentCustomerIndex + 1 : 0;
        const prevCustomer = allCustomers[prevIndex];
        const nextCustomer = allCustomers[nextIndex];
        
        const prevName = (prevCustomer?.firstName || '') + ' ' + (prevCustomer?.lastName || '');
        const nextName = (nextCustomer?.firstName || '') + ' ' + (nextCustomer?.lastName || '');
        
        prevBtn.title = `Previous: ${prevName.trim() || 'Unknown'} (${customerCounter})`;
        nextBtn.title = `Next: ${nextName.trim() || 'Unknown'} (${customerCounter})`;
    }
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    // Only activate in modal mode and when not typing in input fields
    if ((modalMode === 'view' || modalMode === 'edit') && 
        !['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
        
        if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            navigateToCustomer('prev');
        } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            navigateToCustomer('next');
        }
    }
});

function showToast(type, message) {
    const existingToast = document.getElementById('toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    const toast = document.createElement('div');
    toast.id = 'toast-notification';
    toast.className = `toast-notification ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function confirmDelete(customerId, customerName) {
    document.getElementById('delete_customer_id').value = customerId;
    document.getElementById('modal-message').innerText = `Are you sure you want to delete ${customerName}? This action cannot be undone.`;
    document.getElementById('deleteConfirmModal').classList.add('show');
}

function closeModal() {
    document.getElementById('deleteConfirmModal').classList.remove('show');
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize navigation buttons for view/edit modes
    if (modalMode === 'view' || modalMode === 'edit') {
        updateCustomerNavigationButtons();
    }
    
    // Handle customer form submission for edit mode
    const customerForm = document.getElementById('customerForm');
    if (customerForm) {
        const saveBtn = customerForm.querySelector('#saveCustomerBtn');
        const btnText = saveBtn ? saveBtn.querySelector('.button-text') : null;
        const spinner = saveBtn ? saveBtn.querySelector('.loading-spinner') : null;

        customerForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            
            if(saveBtn && btnText && spinner) {
                btnText.classList.add('hidden');
                spinner.classList.remove('hidden');
                saveBtn.disabled = true;
            }
            
            const formData = new FormData(customerForm);

            fetch('/process_customer_update.php', { // API endpoint for processing
                method: 'POST', 
                body: formData, 
                headers: { 'X-Requested-With': 'XMLHttpRequest' } // Important for backend to identify AJAX
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    // If not JSON, read as text and throw an error to be caught by .catch()
                    return response.text().then(text => { 
                        throw new Error("Server returned non-JSON response: " + text.substring(0, 200)); 
                    });
                }
            })
            .then(data => { // This block executes if response.json() was successful
                if (data.success) {
                    showToast('success', data.message);
                    
                    // Redirect to the customers page, optionally highlighting the customer
                    let redirectUrl = '?page=admin&section=customers';
                    if (data.customerId) { // customerId is returned by update operations
                        redirectUrl += '&highlight=' + data.customerId;
                    }
                    // Use a short delay to allow toast to be seen before navigation
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 500); 
                    return; 

                } else { // data.success is false
                    showToast('error', data.error || 'Failed to save customer. Please check inputs.');
                    if(saveBtn && btnText && spinner) {
                        btnText.classList.remove('hidden');
                        spinner.classList.add('hidden');
                        saveBtn.disabled = false;
                    }
                    if (data.field_errors) {
                        document.querySelectorAll('.field-error-highlight').forEach(el => el.classList.remove('field-error-highlight'));
                        data.field_errors.forEach(fieldName => {
                            const fieldElement = document.getElementById(fieldName) || document.querySelector(`[name="${fieldName}"]`);
                            if (fieldElement) fieldElement.classList.add('field-error-highlight');
                        });
                    }
                }
            })
            .catch(error => { // Catches network errors or the error thrown from non-JSON response
                console.error('Error saving customer:', error);
                showToast('error', 'An unexpected error occurred: ' + error.message);
                 if(saveBtn && btnText && spinner) {
                    btnText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                    saveBtn.disabled = false;
                }
            });
        });
    }

    // Handle escape key to close modal
    window.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const mainModal = document.getElementById('customerModalOuter');
            // Check if mainModal is actually displayed (not just present in DOM)
            if (mainModal && mainModal.offsetParent !== null) { 
                window.location.href = '?page=admin&section=customers'; // Redirect to close
            } else if (document.getElementById('deleteConfirmModal')?.classList.contains('show')) {
                closeModal();
            }
        }
    });

    // Highlight row if specified in URL
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight');
    if (highlightId) {
        const rowToHighlight = document.querySelector(`tr[data-customer-id='${highlightId}']`);
        if (rowToHighlight) {
            rowToHighlight.classList.add('bg-yellow-100'); 
            rowToHighlight.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => {
                rowToHighlight.classList.remove('bg-yellow-100');
                const cleanUrl = window.location.pathname + '?page=admin&section=customers'; // Remove highlight param
                history.replaceState({path: cleanUrl}, '', cleanUrl);
            }, 3000);
        }
    }
});
</script>

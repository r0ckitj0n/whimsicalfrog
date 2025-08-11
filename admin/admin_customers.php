<?php
// Admin Customers Management
// This page provides tools for managing customer accounts and viewing customer data

// Prevent direct access
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// CSS is now loaded globally in index.php

// Include database configuration
require_once 'api/config.php';

// Initialize arrays to prevent null values
$customersData = [];
$ordersData = [];

try {
    // Create a PDO connection using centralized Database class
    $pdo = Database::getInstance();

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
    $editCustomer = array_filter($customersData, function ($customer) use ($customerIdToView) {
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
    $editCustomer = array_filter($customersData, function ($customer) use ($customerIdToEdit) {
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
    $customersData = array_filter($customersData, function ($customer) use ($searchTerm) {
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
    $customersData = array_filter($customersData, function ($customer) use ($filterRole) {
        return isset($customer['role']) && strtolower($customer['role']) === strtolower($filterRole);
    });
}

// Sort customers
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sortDir = isset($_GET['dir']) ? $_GET['dir'] : 'asc';

usort($customersData, function ($a, $b) use ($sortBy, $sortDir) {
    switch ($sortBy) {
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

            $aOrders = array_filter($ordersData, function ($order) use ($aId) {
                return isset($order['userId']) && $order['userId'] === $aId;
            });

            $bOrders = array_filter($ordersData, function ($order) use ($bId) {
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
function getSortUrl($column, $currentSort, $currentDir)
{
    $newDir = ($column === $currentSort && $currentDir === 'asc') ? 'desc' : 'asc';
    $queryParams = $_GET;
    $queryParams['sort'] = $column;
    $queryParams['dir'] = $newDir;
    // Build query string but remove page, section, view, edit from it to avoid messy URLs
    unset($queryParams['page'], $queryParams['section'], $queryParams['view'], $queryParams['edit']);
    return '/admin/customers?' . http_build_query($queryParams);
}

// Helper function to get sort indicator
function getSortIndicator($column, $currentSort, $currentDir)
{
    if ($column !== $currentSort) {
        return '';
    }
    return $currentDir === 'asc' ? '↑' : '↓';
}

// Helper function to get customer orders
function getCustomerOrders($customerId, $ordersData)
{
    return array_filter($ordersData, function ($order) use ($customerId) {
        return isset($order['userId']) && $order['userId'] === $customerId;
    });
}

// Process customer deletion if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $customerId = $_POST['customer_id'] ?? '';

    try {
        // Create a PDO connection
        try {
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }

        // Delete customer from database
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $result = $stmt->execute([$customerId]);

        // Close the connection
        $pdo = null;

        if ($result) {
            $deleteSuccess = true;
            // Remove from the array to update the view
            $customersData = array_filter($customersData, function ($customer) use ($customerId) {
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

<div class="admin-content-container">




    <div class="admin-filter-section">
        <form method="GET" action="/admin/customers" class="admin-filter-form">
            
            <input type="text" name="search" placeholder="Search customers..." 
                   class="admin-form-input" value="<?= htmlspecialchars($searchTerm) ?>">
            
            <select name="role" class="admin-form-select">
                <option value="all">All Roles</option>
                <option value="customer" <?= $filterRole === 'customer' ? 'selected' : '' ?>>Customer</option>
                <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            
            <button type="submit" class="btn btn-primary admin-filter-button">Filter</button>
        </form>
    </div>
    
    <?php if ($message): ?>
        <div class="admin-message <?= $messageType === 'success' ? 'admin-message-success' : 'admin-message-error'; ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="admin-table-section">
        <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>
                            <a href="<?= getSortUrl('name', $sortBy, $sortDir) ?>" class="table-sort-link">
                                Customer <?= getSortIndicator('name', $sortBy, $sortDir) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('email', $sortBy, $sortDir) ?>" class="table-sort-link">
                                Email <?= getSortIndicator('email', $sortBy, $sortDir) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('role', $sortBy, $sortDir) ?>" class="table-sort-link">
                                Role <?= getSortIndicator('role', $sortBy, $sortDir) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('orders', $sortBy, $sortDir) ?>" class="table-sort-link">
                                Orders <?= getSortIndicator('orders', $sortBy, $sortDir) ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paginatedCustomers)): ?>
                        <tr>
                            <td colspan="5" class="table-empty-cell">
                                <div class="admin-empty-state">
                                    <div class="empty-icon">👤</div>
                                    <div class="empty-title">No Customers Found</div>
                                    <div class="empty-subtitle">No customers match your search criteria.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($paginatedCustomers as $customer):
                            $customerId = $customer['id'] ?? '';
                            $firstName = $customer['firstName'] ?? '';
                            $lastName = $customer['lastName'] ?? '';
                            $email = $customer['email'] ?? '';
                            $role = $customer['role'] ?? '';
                            $orderCount = count(array_filter($ordersData, fn ($o) => ($o['userId'] ?? '') === $customerId));

                            // Generate initials
                            $initials = 'CU';
                            if ($firstName && $lastName) {
                                $initials = substr($firstName, 0, 1) . substr($lastName, 0, 1);
                            } elseif ($firstName) {
                                $initials = substr($firstName, 0, 2);
                            } elseif ($lastName) {
                                $initials = substr($lastName, 0, 2);
                            }
                            $initials = strtoupper($initials);
                            ?>
                        <tr data-customer-id="<?= htmlspecialchars($customerId) ?>">
                            <td>
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-sm font-semibold">
                                        <?= $initials ?>
                                    </div>
                                    <div>
                                        <div class="font-medium"><?= htmlspecialchars($firstName . ' ' . $lastName) ?></div>
                                        <div class="text-sm text-gray-500">@<?= htmlspecialchars($customer['username'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($email) ?></td>
                            <td>
                                <span class="inline-flex items-center rounded-full text-xs font-medium <?= strtolower($role) === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' ?>">
                                    <?= htmlspecialchars($role) ?>
                                </span>
                            </td>
                            <td><?= $orderCount ?> orders</td>
                            <td>
                                <div class="flex space-x-2">
                                    <a href="?page=admin&section=customers&view=<?= htmlspecialchars($customerId) ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="View Customer">👁️</a>
                                    <a href="?page=admin&section=customers&edit=<?= htmlspecialchars($customerId) ?>" 
                                       class="text-green-600 hover:text-green-800" title="Edit Customer">✏️</a>
                                    <button data-action="confirm-delete" data-customer-id="<?= $customerId ?>" data-customer-name="<?= htmlspecialchars($firstName . ' ' . $lastName) ?>" 
                                            class="text-red-600 hover:text-red-800" title="Delete Customer">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
    </div>
</div>

<!- Delete Confirmation Modal ->
<div id="deleteConfirmModal" class="delete-modal">
    <div class="delete-modal-content">
        <h2 class="delete-modal-title">Confirm Delete</h2>
        <p class="delete-modal-message" id="modal-message">
            Are you sure you want to delete this customer? This action cannot be undone.
        </p>
        <div class="delete-modal-actions">
            <button type="button" class="btn btn-secondary" data-action="close-delete-modal">Cancel</button>
                            <form action="" method="POST" class="inline-block">
                <input type="hidden" name="customer_id" id="delete_customer_id">
                <button type="submit" name="delete_customer" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<?php if (($modalMode === 'view' || $modalMode === 'edit') && $editCustomer): ?>
<!- Customer View/Edit Modal ->
<div class="customer-modal" id="customerModalOuter">
    <!- Navigation Arrows ->
    <button id="prevCustomerBtn" data-action="navigate-customer" data-direction="prev" class="absolute left-4 top-1/2 transform -translate-y-1/2 bg-white rounded-full shadow-lg hover:bg-gray-50 text-gray-600" title="Previous customer">
        <span class="text-xl">‹</span>
    </button>
    <button id="nextCustomerBtn" data-action="navigate-customer" data-direction="next" class="absolute right-4 top-1/2 transform -translate-y-1/2 bg-white rounded-full shadow-lg hover:bg-gray-50 text-gray-600" title="Next customer">
        <span class="text-xl">›</span>
    </button>
    
    <div class="modal-content">
        <!- Modal Header ->
        <div class="modal-header">
            <h2 class="modal-title">
                <?= $modalMode === 'view' ? 'View Customer: ' : 'Edit Customer: ' ?>
                <?= htmlspecialchars(($editCustomer['firstName'] ?? '') . ' ' . ($editCustomer['lastName'] ?? '')) ?>
            </h2>
            <a href="?page=admin&section=customers" class="modal-close">&times;</a>
        </div>

        <?php if ($modalMode === 'edit'): ?>
        <form id="customerForm" method="POST" action="#" class="modal-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="customerId" value="<?= htmlspecialchars($editCustomer['id'] ?? '') ?>">
        <?php endif; ?>

        <div class="modal-body">
            <div class="modal-main">
                <!- Customer Avatar and Basic Info ->
                <div class="customer-profile-header">
                    <div class="customer-avatar-large">
                        <?php
                            $firstName = $editCustomer['firstName'] ?? '';
    $lastName = $editCustomer['lastName'] ?? '';
    $initials = ($firstName && $lastName) ?
        (substr($firstName, 0, 1) . substr($lastName, 0, 1)) :
        (substr($firstName ?: $lastName ?: 'CU', 0, 2));
    $initials = strtoupper($initials);
    ?>
                        <span class="avatar-initials-large"><?= $initials ?></span>
                    </div>
                    <div class="profile-info">
                        <h4 class="profile-section-title">Personal Information</h4>
                    </div>
                </div>

                <!- Form Fields Grid ->
                <div class="form-grid">
                    <div class="form-group">
                        <label for="firstName" class="form-label">First Name</label>
                        <input type="text" id="firstName" name="firstName" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>" 
                               value="<?= htmlspecialchars($firstName) ?>" <?= $modalMode === 'view' ? 'readonly' : 'required' ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="lastName" class="form-label">Last Name</label>
                        <input type="text" id="lastName" name="lastName" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>" 
                               value="<?= htmlspecialchars($lastName) ?>" <?= $modalMode === 'view' ? 'readonly' : 'required' ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>" 
                               value="<?= htmlspecialchars($editCustomer['username'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : 'required' ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>" 
                               value="<?= htmlspecialchars($editCustomer['email'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : 'required' ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">Role</label>
                        <?php if ($modalMode === 'edit'): ?>
                            <select id="role" name="role" class="form-select" required>
                                <option value="customer" <?= ($editCustomer['role'] ?? '') === 'customer' ? 'selected' : '' ?>>Customer</option>
                                <option value="admin" <?= ($editCustomer['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        <?php else: ?>
                            <input type="text" class="form-input readonly" readonly 
                                   value="<?= htmlspecialchars($editCustomer['role'] ?? '') ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="phoneNumber" class="form-label">Phone Number</label>
                        <input type="tel" id="phoneNumber" name="phoneNumber" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>" 
                               value="<?= htmlspecialchars($editCustomer['phoneNumber'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : '' ?>>
                    </div>
                </div>

                <!- Address Section ->
                <div class="form-section">
                    <h5 class="form-section-title">Address Information</h5>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="addressLine1" class="form-label">Address Line 1</label>
                            <input type="text" id="addressLine1" name="addressLine1" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>" 
                                   value="<?= htmlspecialchars($editCustomer['addressLine1'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : '' ?>>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="addressLine2" class="form-label">Address Line 2</label>
                            <input type="text" id="addressLine2" name="addressLine2" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>" 
                                   value="<?= htmlspecialchars($editCustomer['addressLine2'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : '' ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="city" class="form-label">City</label>
                            <input type="text" id="city" name="city" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>" 
                                   value="<?= htmlspecialchars($editCustomer['city'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : '' ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="state" class="form-label">State</label>
                            <input type="text" id="state" name="state" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>" 
                                   value="<?= htmlspecialchars($editCustomer['state'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : '' ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="zipCode" class="form-label">ZIP Code</label>
                            <input type="text" id="zipCode" name="zipCode" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>" 
                                   value="<?= htmlspecialchars($editCustomer['zipCode'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : '' ?>>
                        </div>
                    </div>
                </div>

                <?php if ($modalMode === 'edit'): ?>
                <!- Password Management Section ->
                <div class="form-section">
                    <h5 class="form-section-title">Password Management</h5>
                    <p class="form-section-help">Leave password fields blank to keep the current password unchanged.</p>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" id="newPassword" name="newPassword" 
                                   class="form-input" placeholder="Enter new password (min 6 characters)" minlength="6">
                            <div class="form-help">Minimum 6 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" 
                                   class="form-input" placeholder="Confirm new password">
                            <div class="form-help">Must match new password</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!- Order History Sidebar ->
            <div class="modal-sidebar">
                <div class="order-history-panel">
                    <h3 class="order-history-title">Order History</h3>
                    <div class="order-history-content">
                        <?php if (empty($customerOrders)): ?>
                            <div class="order-history-empty">
                                <div class="empty-icon">📦</div>
                                <div class="empty-subtitle">No orders found for this customer.</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($customerOrders as $order):
                                $orderDate = date('M j, Y', strtotime($order['date'] ?? $order['createdAt'] ?? 'now'));
                                $statusClass = 'status-' . strtolower($order['status'] ?? 'pending');
                                ?>
                            <div class="order-history-item">
                                <h5 class="order-id">Order #<?= htmlspecialchars($order['id'] ?? '') ?></h5>
                                <div class="order-details">
                                    <div class="order-detail">
                                        <span>Date:</span>
                                        <span><?= $orderDate ?></span>
                                    </div>
                                    <div class="order-detail">
                                        <span>Total:</span>
                                        <span>$<?= number_format(floatval($order['total'] ?? $order['totalAmount'] ?? 0), 2) ?></span>
                                    </div>
                                    <div class="order-detail">
                                        <span>Status:</span>
                                        <span class="order-status <?= $statusClass ?>">
                                            <?= htmlspecialchars($order['status'] ?? 'Pending') ?>
                                        </span>
                                    </div>
                                    <div class="order-detail">
                                        <span>Payment:</span>
                                        <span><?= htmlspecialchars($order['paymentMethod'] ?? 'N/A') ?> - <?= htmlspecialchars($order['paymentStatus'] ?? 'N/A') ?></span>
                                    </div>
                                    <?php if (!empty($order['shippingMethod'])): ?>
                                    <div class="order-detail">
                                        <span>Shipping:</span>
                                        <span><?= htmlspecialchars($order['shippingMethod']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!- Modal Footer ->
        <div class="modal-footer">
            <a href="?page=admin&section=customers" class="btn btn-secondary">
                <?= $modalMode === 'edit' ? 'Cancel' : 'Close' ?>
            </a>
            <?php if ($modalMode === 'view'): ?>
                <a href="?page=admin&section=customers&edit=<?= htmlspecialchars($editCustomer['id'] ?? '') ?>" 
                   class="btn btn-primary">Edit Customer</a>
            <?php else: ?>
                <button type="submit" id="saveCustomerBtn" class="btn btn-primary" data-action="submit-customer-form">
                    <span class="button-text">Save Changes</span>
                    <span class="loading-spinner hidden">⏳</span>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($modalMode === 'edit'): ?>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Page Data for admin-customers module (consumed by js/admin-customers.js) -->
<script type="application/json" id="customer-page-data">
<?= json_encode([
    'customers' => array_values($customersData),
    'currentCustomerId' => $editCustomer['id'] ?? null,
    'modalMode' => $modalMode ?? null,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
</script>

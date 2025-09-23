<?php
// sections/admin_customers.php — Primary implementation for Customers section

// This page provides tools for managing customer accounts and viewing customer data

// Ensure routing guards are defined (harmless if already set)
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// Include database configuration (absolute path for reliability)
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/components/admin_customer_editor.php';

// Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include dirname(__DIR__) . '/partials/header.php';
    if (!function_exists('__wf_admin_customers_footer_shutdown')) {
        function __wf_admin_customers_footer_shutdown()
        {
            @include __DIR__ . '/../partials/footer.php';
        }
    }
    register_shutdown_function('__wf_admin_customers_footer_shutdown');
}

// Only include the admin navbar if this file is accessed directly, not when included by admin_router.php
if (!isset($adminSection) || $adminSection !== 'customers'):
    // Always include admin navbar on customers page, even when accessed directly
    $section = 'customers';
    include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';
endif;

// Initialize arrays to prevent null values
$customersData = [];
$ordersData = [];

try {
    // Initialize database (singleton)
    Database::getInstance();

    // Fetch customers/users data directly from database
    $customersData = Database::queryAll('SELECT * FROM users');

    // Fetch orders data directly from database
    $ordersData = Database::queryAll('SELECT * FROM orders');
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
        return ($customer['id'] ?? '') == $customerIdToView;
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
        return ($customer['id'] ?? '') == $customerIdToEdit;
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
        // Initialize database (singleton)
        Database::getInstance();

        // Delete customer from database
        $affected = Database::execute('DELETE FROM users WHERE id = ?', [$customerId]);
        $result = $affected > 0;

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

<?php
// Only include the wrapper if this file is accessed directly, not when included by admin_router.php
if (!isset($adminSection) || $adminSection !== "customers") {
    // Always include admin navbar on customers page, even when accessed directly
    $section = "customers";
    include_once dirname(__DIR__) . "/components/admin_nav_tabs.php";
}
?>


            <div class="admin-filter-section">
                <div class="admin-filters">
                    <form method="GET" action="/admin/customers" class="admin-filter-form">
                        <input type="text" name="search" placeholder="Search customers..."
                               class="admin-form-input" value="<?= htmlspecialchars($searchTerm) ?>">
                        <select name="role" class="admin-form-select">
                            <option value="all">All Roles</option>
                            <option value="customer" <?= $filterRole === 'customer' ? 'selected' : '' ?>>Customer</option>
                            <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <span class="admin-actions">
                            <button type="submit" class="btn btn-primary admin-filter-button">Filter</button>
                        </span>
                    </form>
                </div>
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
                                    <div class="admin-actions">
                                        <a href="/sections/admin_router.php?section=customers&view=<?= htmlspecialchars($customerId) ?>"
                                           class="text-blue-600 hover:text-blue-800" title="View Customer">👁️</a>
                                        <a href="/sections/admin_router.php?section=customers&edit=<?= htmlspecialchars($customerId) ?>"
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


<?php if (($modalMode === 'view' || $modalMode === 'edit') && $editCustomer): ?>
<!-- Customer View/Edit Modal -->
<div class="customer-modal admin-modal-overlay" id="customerModalOuter" data-action="close-customer-editor-on-overlay">
    <!-- Navigation Arrows -->
    <button id="prevCustomerBtn" data-action="navigate-customer" data-direction="prev" class="absolute left-4 top-1/2 transform -translate-y-1/2 bg-white rounded-full shadow-lg hover:bg-gray-50 text-gray-600" title="Previous customer">
        <span class="text-xl">‹</span>
    </button>
    <button id="nextCustomerBtn" data-action="navigate-customer" data-direction="next" class="absolute right-4 top-1/2 transform -translate-y-1/2 bg-white rounded-full shadow-lg hover:bg-gray-50 text-gray-600" title="Next customer">
        <span class="text-xl">›</span>
    </button>

    <div class="admin-modal">
        <!-- Modal Header -->
        <div class="modal-header">
            <h2 class="modal-title">
                <?= $modalMode === 'view' ? 'View Customer: ' : 'Edit Customer: ' ?>
                <?= htmlspecialchars(($editCustomer['firstName'] ?? '') . ' ' . ($editCustomer['lastName'] ?? '')) ?>
            </h2>
            <a href="/sections/admin_router.php?section=customers" class="modal-close" data-action="close-customer-editor">&times;</a>
        </div>

        <?php if ($modalMode === 'edit'): ?>
        <form id="customerForm" method="POST" action="#" class="modal-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="customerId" value="<?= htmlspecialchars($editCustomer['id'] ?? '') ?>">
        <?php endif; ?>

        <div class="modal-body">

            <div class="personal-col">
                <div class="form-section">
                    <h5 class="form-section-title">Personal Information</h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" id="firstName" name="firstName" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>"
                                   value="<?= htmlspecialchars($editCustomer['firstName'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : 'required' ?>>
                        </div>
                        <div class="form-group">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" id="lastName" name="lastName" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>"
                                   value="<?= htmlspecialchars($editCustomer['lastName'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : 'required' ?>>
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
                </div>

                <?php if ($modalMode === 'edit'): ?>
                <div class="form-section">
                    <h5 class="form-section-title">Password Management</h5>
                    <p class="form-section-help">Leave password fields blank to keep the current password unchanged.</p>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" id="newPassword" name="newPassword"
                                   class="form-input" placeholder="Enter new password (min 6 characters)" minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword"
                                   class="form-input" placeholder="Confirm new password">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="address-col">
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
            </div>

            <!-- Order History Sidebar -->
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
                            <div class="order-history-item" data-action="view-order" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
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

        <!-- Modal Footer -->
        <div class="modal-footer">
            <a href="/sections/admin_router.php?section=customers" class="btn btn-secondary" data-action="close-customer-editor">
                <?= $modalMode === 'edit' ? 'Cancel' : 'Close' ?>
            </a>
            <?php if ($modalMode === 'view'): ?>
                <a href="/sections/admin_router.php?section=customers&edit=<?= htmlspecialchars($editCustomer['id'] ?? '') ?>"
                   class="btn btn-primary" data-action="navigate-to-edit">Edit Customer</a>
            <?php else: ?>
                <button type="button" id="saveCustomerBtn" class="btn btn-primary" data-action="save-customer">
                    <span class="button-text">Save Changes</span>
                    <span class="loading-spinner hidden">⏳</span>
                </button>
            <?php endif; ?>

            <!-- Test buttons removed -->
        </div>

        <?php if ($modalMode === 'edit'): ?>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
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


        <div class="modal-body">

            <div class="personal-col">
                <div class="form-section">
                    <h5 class="form-section-title">Personal Information</h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" id="firstName" name="firstName" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>"
                                   value="<?= htmlspecialchars($editCustomer['firstName'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : 'required' ?>>
                        </div>
                        <div class="form-group">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" id="lastName" name="lastName" class="form-input <?= $modalMode === 'view' ? 'readonly' : '' ?>"
                                   value="<?= htmlspecialchars($editCustomer['lastName'] ?? '') ?>" <?= $modalMode === 'view' ? 'readonly' : 'required' ?>>
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
                </div>

                <?php if ($modalMode === 'edit'): ?>
                <div class="form-section">
                    <h5 class="form-section-title">Password Management</h5>
                    <p class="form-section-help">Leave password fields blank to keep the current password unchanged.</p>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" id="newPassword" name="newPassword"
                                   class="form-input" placeholder="Enter new password (min 6 characters)" minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword"
                                   class="form-input" placeholder="Confirm new password">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="address-col">
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
            </div>

            <!-- Order History Sidebar -->
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
                            <div class="order-history-item" data-action="view-order" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
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

        <!-- Modal Footer -->
        <div class="modal-footer">
            <a href="/sections/admin_router.php?section=customers" class="btn btn-secondary" data-action="close-customer-editor">
                <?= $modalMode === 'edit' ? 'Cancel' : 'Close' ?>
            </a>
            <?php if ($modalMode === 'view'): ?>
                <a href="/sections/admin_router.php?section=customers&edit=<?= htmlspecialchars($editCustomer['id'] ?? '') ?>"
                   class="btn btn-primary" data-action="navigate-to-edit">Edit Customer</a>
            <?php else: ?>
                <button type="button" id="saveCustomerBtn" class="btn btn-primary" data-action="save-customer">
                    <span class="button-text">Save Changes</span>
                    <span class="loading-spinner hidden">⏳</span>
                </button>
            <?php endif; ?>

            <!-- Test buttons removed -->
        </div>

        <?php if ($modalMode === 'edit'): ?>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php
// Only include the wrapper if this file is accessed directly, not when included by admin_router.php
if (!isset($adminSection) || $adminSection !== 'customers'):
    ?>
?>
</div>
</div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
        </div>
    </div>
</div>


<!-- Test notification script removed -->
<!--
    console.log('[TEST] Testing notification system...');
    console.log('[TEST] window.wfNotifications:', typeof window.wfNotifications);
    console.log('[TEST] window.showSuccess:', typeof window.showSuccess);
    console.log('[TEST] window.showError:', typeof window.showError);
    console.log('[TEST] window.showNotification:', typeof window.showNotification);

    // Test 1: Try wfNotifications directly
    if (window.wfNotifications) {
        console.log('[TEST] Using window.wfNotifications');
        window.wfNotifications.success('✅ Direct wfNotifications test!');
        window.wfNotifications.error('❌ Direct wfNotifications error test!');
        window.wfNotifications.info('ℹ️ Direct wfNotifications info test!');
    } else {
        console.log('[TEST] window.wfNotifications not available');
    }

    // Test 2: Try showNotification functions
    if (window.showSuccess) {
        console.log('[TEST] Using window.showSuccess');
        window.showSuccess('✅ window.showSuccess test!');
    } else {
        console.log('[TEST] window.showSuccess not available');
    }

    if (window.showError) {
        console.log('[TEST] Using window.showError');
        window.showError('❌ window.showError test!');
    } else {
        console.log('[TEST] window.showError not available');
    }

    if (window.showNotification) {
        console.log('[TEST] Using window.showNotification');
        window.showNotification('ℹ️ window.showNotification test!', 'info', { title: 'Test' });
    } else {
        console.log('[TEST] window.showNotification not available');
    }

    // Test 3: Manual notification creation
    try {
        console.log('[TEST] Creating manual notification...');
        if (window.AdminCustomersModule && window.AdminCustomersModule.createManualNotification) {
            window.AdminCustomersModule.createManualNotification('✅ Manual notification test!', 'success');
            window.AdminCustomersModule.createManualNotification('❌ Manual error notification test!', 'error');
            window.AdminCustomersModule.createManualNotification('ℹ️ Manual info notification test!', 'info');
        } else {
            console.log('[TEST] AdminCustomersModule.createManualNotification not available');
        }
    } catch(error) {
        console.error('[TEST] Manual notification failed:', error);
    }

    // Test 4: Try direct DOM manipulation
    try {
        console.log('[TEST] Creating direct DOM notification...');
        const container = document.getElementById('wf-notification-container') || (() => {
            const div = document.createElement('div');
            div.id = 'wf-notification-container';
            div.className = 'wf-notification-container';
            div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 999999; pointer-events: none; max-width: 400px;';
            document.body.appendChild(div);
            return div;
        })();

        const notification = document.createElement('div');
        notification.className = 'wf-notification wf-success-notification';
        notification.style.cssText = 'pointer-events: auto; margin-bottom: 10px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border: 1px solid #6b8e23; background: linear-gradient(135deg, #87ac3a, #6b8e23); color: white; padding: 16px; position: relative; z-index: 999999;';
        notification.innerHTML = `
            <div class="wf-notification-content" style="display: flex; align-items: flex-start; gap: 12px;">
                <div class="wf-notification-icon" style="font-size: 20px; line-height: 1;">✅</div>
                <div class="wf-notification-body" style="flex: 1;">
                    <div class="wf-notification-message" style="font-size: 14px; line-height: 1.4;">🎯 Direct DOM notification test!</div>
                </div>
                <button class="wf-notification-close" style="position: absolute; top: 8px; right: 8px; background: none; border: none; color: rgba(255,255,255,0.8); font-size: 18px; cursor: pointer;">&times;</button>
            </div>
        `;

        container.appendChild(notification);

        // Show notification
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
            notification.style.opacity = '1';
        }, 10);

        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(120%)';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 400);
        }, 5000);

        console.log('[TEST] Direct DOM notification created and should be visible!');
    } catch(error) {
        console.error('[TEST] Direct DOM notification failed:', error);
    }

    // Test 5: Debug save button
    console.log('[TEST] Testing save button...');
    const saveBtn = document.getElementById('saveCustomerBtn');
    if (saveBtn) {
        console.log('[TEST] Save button found:', saveBtn);
        console.log('[TEST] Save button attributes:', {
            id: saveBtn.id,
            type: saveBtn.type,
            tagName: saveBtn.tagName,
            className: saveBtn.className,
            dataAction: saveBtn.dataset.action
        });

        // Try to trigger save manually
        console.log('[TEST] Attempting to trigger save manually...');
        try {
            if (window.AdminCustomersModule && window.AdminCustomersModule.handleCustomerSave) {
                window.AdminCustomersModule.handleCustomerSave();
                console.log('[TEST] Manual save triggered successfully!');
            } else {
                console.log('[TEST] AdminCustomersModule.handleCustomerSave not available');
            }
        } catch(error) {
            console.error('[TEST] Manual save failed:', error);
        }
    } else {
        console.log('[TEST] Save button not found!');
    }
}

function debugSave() {
    console.log('[DEBUG] Manual save trigger initiated');

    // Test 1: Check if button exists
    const saveBtn = document.getElementById('saveCustomerBtn');
    console.log('[DEBUG] Save button element:', saveBtn);

    if (!saveBtn) {
        console.error('[DEBUG] Save button not found!');
        return;
    }

    // Test 2: Check button properties
    console.log('[DEBUG] Save button details:', {
        id: saveBtn.id,
        type: saveBtn.type,
        tagName: saveBtn.tagName,
        className: saveBtn.className,
        disabled: saveBtn.disabled,
        style: saveBtn.style.cssText,
        dataAction: saveBtn.dataset.action
    });

    // Test 3: Check if AdminCustomersModule exists
    console.log('[DEBUG] Checking AdminCustomersModule...');
    console.log('[DEBUG] window.AdminCustomersModule:', typeof window.AdminCustomersModule);
    console.log('[DEBUG] AdminCustomersModule object:', window.AdminCustomersModule);

    if (window.AdminCustomersModule) {
        console.log('[DEBUG] AdminCustomersModule methods:', Object.getOwnPropertyNames(window.AdminCustomersModule));
    }

    // Test 4: Try clicking programmatically
    console.log('[DEBUG] Attempting programmatic click...');
    try {
        saveBtn.click();
        console.log('[DEBUG] Programmatic click successful');
    } catch(error) {
        console.error('[DEBUG] Programmatic click failed:', error);
    }

    // Test 5: Try manual save function
    console.log('[DEBUG] Attempting manual save...');
    try {
        if (window.AdminCustomersModule && window.AdminCustomersModule.handleCustomerSave) {
            console.log('[DEBUG] AdminCustomersModule.handleCustomerSave exists, calling it...');
            window.AdminCustomersModule.handleCustomerSave();
            console.log('[DEBUG] Manual save successful');
        } else {
            console.error('[DEBUG] AdminCustomersModule.handleCustomerSave not available');
            console.log('[DEBUG] Available properties:', window.AdminCustomersModule ? Object.keys(window.AdminCustomersModule) : 'AdminCustomersModule not found');
        }
    } catch(error) {
        console.error('[DEBUG] Manual save failed:', error);
    }

    // Test 6: Try direct notification test
    console.log('[DEBUG] Testing notifications directly...');
    try {
        if (window.showSuccess) {
            window.showSuccess('Debug save test notification');
        } else {
            console.log('[DEBUG] window.showSuccess not available');
        }
    } catch(error) {
        console.error('[DEBUG] Direct notification failed:', error);
    }
}</script>

<script>
function directNotificationTest() {
    console.log('[DIRECT] Direct notification test started');

    // Test 1: Direct showSuccess call
    try {
        console.log('[DIRECT] Calling window.showSuccess');
        window.showSuccess('🎯 Direct notification test - SUCCESS!');
        console.log('[DIRECT] showSuccess called successfully');
    } catch(error) {
        console.error('[DIRECT] showSuccess failed:', error);
    }

    // Test 2: Direct wfNotifications call
    try {
        console.log('[DIRECT] Calling window.wfNotifications.success');
        window.wfNotifications.success('✅ Direct wfNotifications test');
        console.log('[DIRECT] wfNotifications.success called successfully');
    } catch(error) {
        console.error('[DIRECT] wfNotifications failed:', error);
    }

    // Test 3: Manual notification creation
    try {
        console.log('[DIRECT] Calling AdminCustomersModule.createManualNotification');
        window.AdminCustomersModule.createManualNotification('🔧 Direct manual notification test', 'success');
        console.log('[DIRECT] Manual notification created successfully');
    } catch(error) {
        console.error('[DIRECT] Manual notification failed:', error);
    }
}

<!-- Page Data for admin-customers module (consumed by js/admin-customers.js) -->
<script type="application/json" id="customer-page-data">
<?= json_encode([
        'customers' => array_values($customersData),
        'currentCustomerId' => $editCustomer['id'] ?? null,
        'modalMode' => $modalMode ?? null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
</script>

<script>
function simpleNotificationTest() {
    console.log('[SIMPLE] Testing basic notification...');

    // Test 1: Use wfNotifications directly
    try {
        if (window.wfNotifications && window.wfNotifications.success) {
            console.log('[SIMPLE] Using wfNotifications.success');
            window.wfNotifications.success('🎯 Simple notification test - SUCCESS!');
            console.log('[SIMPLE] wfNotifications.success called');
        } else {
            console.log('[SIMPLE] wfNotifications not available');
        }
    } catch(error) {
        console.error('[SIMPLE] wfNotifications failed:', error);
    }

    // Test 2: Use showSuccess directly
    try {
        if (window.showSuccess) {
            console.log('[SIMPLE] Using window.showSuccess');
            window.showSuccess('✅ Direct showSuccess test');
            console.log('[SIMPLE] showSuccess called');
        } else {
            console.log('[SIMPLE] showSuccess not available');
        }
    } catch(error) {
        console.error('[SIMPLE] showSuccess failed:', error);
    }

    // Test 3: Create manual notification
    try {
        console.log('[SIMPLE] Creating manual notification');
        if (window.AdminCustomersModule && window.AdminCustomersModule.createManualNotification) {
            window.AdminCustomersModule.createManualNotification('🔧 Manual notification test', 'success');
            console.log('[SIMPLE] Manual notification created');
        } else {
            console.log('[SIMPLE] Manual notification method not available');
        }
    } catch(error) {
        console.error('[SIMPLE] Manual notification failed:', error);
    }

    // Test 4: Direct DOM creation (guaranteed to work)
    try {
        console.log('[SIMPLE] Creating direct DOM notification');
        const container = document.getElementById('wf-notification-container') || (() => {
            const div = document.createElement('div');
            div.id = 'wf-notification-container';
            div.className = 'wf-notification-container';
            div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 999999; pointer-events: none; max-width: 400px;';
            document.body.appendChild(div);
            return div;
        })();

        const notification = document.createElement('div');
        notification.className = 'wf-notification wf-success-notification';
        notification.style.cssText = 'pointer-events: auto; margin-bottom: 10px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border: 1px solid #6b8e23; background: #87ac3a; color: white; padding: 16px; position: relative; z-index: 999999; transform: translateX(120%); opacity: 0; transition: all 0.4s ease;';
        notification.innerHTML = `
            <div class="wf-notification-content" style="display: flex; align-items: flex-start; gap: 12px;">
                <div class="wf-notification-icon" style="font-size: 20px; line-height: 1;">🎉</div>
                <div class="wf-notification-body" style="flex: 1;">
                    <div class="wf-notification-message" style="font-size: 14px; line-height: 1.4;">💯 DIRECT DOM TEST - This should definitely appear!</div>
                </div>
                <button class="wf-notification-close" style="position: absolute; top: 8px; right: 8px; background: none; border: none; color: rgba(255,255,255,0.8); font-size: 18px; cursor: pointer;">&times;</button>
            </div>
        `;

        container.appendChild(notification);

        // Show notification with animation
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
            notification.style.opacity = '1';
        }, 10);

        // Auto remove after 10 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(120%)';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 400);
        }, 10000);

        console.log('[SIMPLE] Direct DOM notification created - should be visible!');
    } catch(error) {
        console.error('[SIMPLE] Direct DOM notification failed:', error);
    }
}
</script>

<!-- Simple save notification handler (remove in production) -->
<script>
// Wait for AdminCustomersModule to be ready, then enhance notifications
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit for the module to initialize
    setTimeout(function() {
        if (window.AdminCustomersModule && window.AdminCustomersModule.createManualNotification) {
            console.log('[NOTIFICATION] AdminCustomersModule notification system is working');
        } else {
            console.log('[NOTIFICATION] AdminCustomersModule not ready yet');
        }
    }, 1000);
});
</script>

<?php // Admin customers script is loaded via app.js per-page imports?>

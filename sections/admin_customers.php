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

<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="admin-header-section mb-6">
        <h1 class="admin-title">Customer Management</h1>
        <div class="admin-subtitle">Manage customer accounts and view customer data</div>
    </div>

    <!-- Search and Filter Controls -->
    <div class="admin-card mb-6">
        <form method="GET" action="" class="customer-filter-form">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="customers">
            
            <div class="filter-group">
                <div class="filter-field">
                    <input type="text" name="search" placeholder="Search customers..." 
                           class="form-input" value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
                
                <div class="filter-field">
                    <select name="role" class="form-select">
                        <option value="all">All Roles</option>
                        <option value="customer" <?= $filterRole === 'customer' ? 'selected' : '' ?>>Customer</option>
                        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary">Filter</button>
            </div>
        </form>
    </div>
    
    <!-- Status Messages -->
    <?php if ($message): ?>
        <div class="admin-alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?> mb-6">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Customers Table -->
    <div class="admin-card">
        <div class="table-container">
            <table class="admin-table">
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
                            $orderCount = count(array_filter($ordersData, fn($o) => ($o['userId'] ?? '') === $customerId));
                            
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
                        <tr class="customer-row" data-customer-id="<?= htmlspecialchars($customerId) ?>">
                            <td>
                                <div class="customer-info">
                                    <div class="customer-avatar">
                                        <span class="avatar-initials"><?= $initials ?></span>
                                    </div>
                                    <div class="customer-details">
                                        <div class="customer-name"><?= htmlspecialchars($firstName . ' ' . $lastName) ?></div>
                                        <div class="customer-username">@<?= htmlspecialchars($customer['username'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="customer-email"><?= htmlspecialchars($email) ?></td>
                            <td>
                                <span class="role-badge role-<?= strtolower($role) ?>">
                                    <?= htmlspecialchars($role) ?>
                                </span>
                            </td>
                            <td class="order-count"><?= $orderCount ?> orders</td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?page=admin&section=customers&view=<?= htmlspecialchars($customerId) ?>" 
                                       class="action-btn view-btn" title="View Customer">👁️</a>
                                    <a href="?page=admin&section=customers&edit=<?= htmlspecialchars($customerId) ?>" 
                                       class="action-btn edit-btn" title="Edit Customer">✏️</a>
                                    <button onclick="confirmDelete('<?= $customerId ?>', '<?= htmlspecialchars(addslashes($firstName . ' ' . $lastName)) ?>')" 
                                            class="action-btn delete-btn" title="Delete Customer">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="delete-modal">
    <div class="delete-modal-content">
        <h2 class="delete-modal-title">Confirm Delete</h2>
        <p class="delete-modal-message" id="modal-message">
            Are you sure you want to delete this customer? This action cannot be undone.
        </p>
        <div class="delete-modal-actions">
            <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            <form action="" method="POST" style="display: inline;">
                <input type="hidden" name="customer_id" id="delete_customer_id">
                <button type="submit" name="delete_customer" class="btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<?php if (($modalMode === 'view' || $modalMode === 'edit') && $editCustomer): ?>
<!-- Customer View/Edit Modal -->
<div class="customer-modal" id="customerModalOuter">
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
    
    <div class="modal-content">
        <!-- Modal Header -->
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
                <!-- Customer Avatar and Basic Info -->
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

                <!-- Form Fields Grid -->
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

                <!-- Address Section -->
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
                <!-- Password Management Section -->
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

        <!-- Modal Footer -->
        <div class="modal-footer">
            <a href="?page=admin&section=customers" class="btn-secondary">
                <?= $modalMode === 'edit' ? 'Cancel' : 'Close' ?>
            </a>
            <?php if ($modalMode === 'view'): ?>
                <a href="?page=admin&section=customers&edit=<?= htmlspecialchars($editCustomer['id'] ?? '') ?>" 
                   class="btn-primary">Edit Customer</a>
            <?php else: ?>
                <button type="submit" id="saveCustomerBtn" class="btn-primary">
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

<!-- JavaScript -->
<script>
// Initialize variables
const modalMode = <?= json_encode($modalMode ?? '') ?>;
const currentCustomerId = <?= json_encode($editCustomer['id'] ?? '') ?>;
const allCustomers = <?= json_encode(array_values($customersData)) ?>;
let currentCustomerIndex = allCustomers.findIndex(customer => customer.id === currentCustomerId);

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
        
        // Preserve search/filter parameters
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('search')) newUrl += `&search=${encodeURIComponent(urlParams.get('search'))}`;
        if (urlParams.get('role')) newUrl += `&role=${encodeURIComponent(urlParams.get('role'))}`;
        
        window.location.href = newUrl;
    }
}

function updateCustomerNavigationButtons() {
    const prevBtn = document.getElementById('prevCustomerBtn');
    const nextBtn = document.getElementById('nextCustomerBtn');
    
    if (prevBtn && nextBtn && allCustomers.length > 0) {
        prevBtn.style.display = 'block';
        nextBtn.style.display = 'block';
        
        const customerCounter = `${currentCustomerIndex + 1} of ${allCustomers.length}`;
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

// Delete confirmation
function confirmDelete(customerId, customerName) {
    document.getElementById('delete_customer_id').value = customerId;
    document.getElementById('modal-message').innerText = 
        `Are you sure you want to delete ${customerName}? This action cannot be undone.`;
    document.getElementById('deleteConfirmModal').classList.add('show');
}

function closeModal() {
    document.getElementById('deleteConfirmModal').classList.remove('show');
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Initialize navigation for modal mode
    if (modalMode === 'view' || modalMode === 'edit') {
        updateCustomerNavigationButtons();
    }
    
    // Handle customer form submission
    const customerForm = document.getElementById('customerForm');
    if (customerForm) {
        const saveBtn = customerForm.querySelector('#saveCustomerBtn');
        const btnText = saveBtn?.querySelector('.button-text');
        const spinner = saveBtn?.querySelector('.loading-spinner');

        customerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Clear previous errors
            document.querySelectorAll('.field-error-highlight').forEach(el => 
                el.classList.remove('field-error-highlight'));
            
            // Validate passwords
            const newPassword = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            let validationErrors = [];
            
            if (newPassword && confirmPassword) {
                const newPwd = newPassword.value.trim();
                const confirmPwd = confirmPassword.value.trim();
                
                if (newPwd || confirmPwd) {
                    if (newPwd.length < 6) {
                        validationErrors.push('Password must be at least 6 characters long');
                        newPassword.classList.add('field-error-highlight');
                    }
                    
                    if (newPwd !== confirmPwd) {
                        validationErrors.push('Password confirmation does not match');
                        confirmPassword.classList.add('field-error-highlight');
                    }
                }
            }
            
            if (validationErrors.length > 0) {
                showError(validationErrors.join('. '));
                return;
            }
            
            if (saveBtn && btnText && spinner) {
                btnText.classList.add('hidden');
                spinner.classList.remove('hidden');
                saveBtn.disabled = true;
            }
            
            const formData = new FormData(customerForm);

            fetch('/process_customer_update.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => { 
                        throw new Error("Server returned non-JSON response: " + text.substring(0, 200)); 
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    
                    let redirectUrl = '?page=admin&section=customers';
                    if (data.customerId) {
                        redirectUrl += '&highlight=' + data.customerId;
                    }
                    
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 500); 
                } else {
                    showError(data.error || 'Failed to save customer. Please check inputs.');
                    if (saveBtn && btnText && spinner) {
                        btnText.classList.remove('hidden');
                        spinner.classList.add('hidden');
                        saveBtn.disabled = false;
                    }
                    if (data.field_errors) {
                        document.querySelectorAll('.field-error-highlight').forEach(el => 
                            el.classList.remove('field-error-highlight'));
                        data.field_errors.forEach(fieldName => {
                            const fieldElement = document.getElementById(fieldName) || 
                                document.querySelector(`[name="${fieldName}"]`);
                            if (fieldElement) fieldElement.classList.add('field-error-highlight');
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error saving customer:', error);
                showError('An unexpected error occurred: ' + error.message);
                if (saveBtn && btnText && spinner) {
                    btnText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                    saveBtn.disabled = false;
                }
            });
        });
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
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

    // Escape key handling
    window.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const mainModal = document.getElementById('customerModalOuter');
            if (mainModal && mainModal.offsetParent !== null) { 
                window.location.href = '?page=admin&section=customers';
            } else if (document.getElementById('deleteConfirmModal')?.classList.contains('show')) {
                closeModal();
            }
        }
    });

    // Row highlighting
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight');
    if (highlightId) {
        const rowToHighlight = document.querySelector(`tr[data-customer-id='${highlightId}']`);
        if (rowToHighlight) {
            rowToHighlight.classList.add('highlighted-row'); 
            rowToHighlight.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => {
                rowToHighlight.classList.remove('highlighted-row');
                const cleanUrl = window.location.pathname + '?page=admin&section=customers';
                history.replaceState({path: cleanUrl}, '', cleanUrl);
            }, 3000);
        }
    }
});
</script>

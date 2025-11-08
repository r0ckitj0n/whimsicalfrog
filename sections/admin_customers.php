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
$customerAddresses = [];

// Check if we're in view mode
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $customerKeyRaw = (string)$_GET['view'];
    if (($p = stripos($customerKeyRaw, 'debug_nav')) !== false) {
        $customerKeyRaw = substr($customerKeyRaw, 0, $p);
    }
    $customerKey = trim($customerKeyRaw);
    $norm = static function($v){ return strtolower(trim((string)$v)); };
    $editCustomer = array_filter($customersData, function ($customer) use ($customerKey, $norm) {
        $id = $customer['id'] ?? '';
        $username = $customer['username'] ?? '';
        return $norm($id) === $norm($customerKey) || $norm($username) === $norm($customerKey);
    });
    $editCustomer = !empty($editCustomer) ? array_values($editCustomer)[0] : null;

    if ($editCustomer) {
        $modalMode = 'view';
        // Get customer orders
        $customerOrders = getCustomerOrders($editCustomer['id'], $ordersData);
        $customerAddresses = Database::queryAll(
            'SELECT * FROM customer_addresses WHERE user_id = ? ORDER BY is_default DESC, address_name ASC',
            [$editCustomer['id'] ?? '']
        );
    }
}
// Check if we're in edit mode
elseif (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $customerKeyRaw = (string)$_GET['edit'];
    if (($p = stripos($customerKeyRaw, 'debug_nav')) !== false) {
        $customerKeyRaw = substr($customerKeyRaw, 0, $p);
    }
    $customerKey = trim($customerKeyRaw);
    $norm = static function($v){ return strtolower(trim((string)$v)); };
    $editCustomer = array_filter($customersData, function ($customer) use ($customerKey, $norm) {
        $id = $customer['id'] ?? '';
        $username = $customer['username'] ?? '';
        return $norm($id) === $norm($customerKey) || $norm($username) === $norm($customerKey);
    });
    $editCustomer = !empty($editCustomer) ? array_values($editCustomer)[0] : null;

    if ($editCustomer) {
        $modalMode = 'edit';
        // Get customer orders
        $customerOrders = getCustomerOrders($editCustomer['id'], $ordersData);
        $customerAddresses = Database::queryAll(
            'SELECT * FROM customer_addresses WHERE user_id = ? ORDER BY is_default DESC, address_name ASC',
            [$editCustomer['id'] ?? '']
        );
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

// No pagination - display all customers

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

function normalizeCustomerAddress(array $address): array
{
    $address['id'] = (string)($address['id'] ?? $address['address_id'] ?? '');
    $address['addressName'] = $address['address_name'] ?? ($address['addressName'] ?? '');
    $address['addressLine1'] = $address['address_line1'] ?? ($address['addressLine1'] ?? '');
    $address['addressLine2'] = $address['address_line2'] ?? ($address['addressLine2'] ?? '');
    $address['city'] = $address['city'] ?? ($address['city'] ?? '');
    $address['state'] = $address['state'] ?? ($address['state'] ?? '');
    $address['zipCode'] = $address['zip_code'] ?? ($address['zipCode'] ?? '');
    $address['isDefault'] = isset($address['is_default']) ? (int)$address['is_default'] : (isset($address['isDefault']) ? (int)$address['isDefault'] : 0);
    return $address;
}

function renderCustomerAddressesHtml(array $addresses, bool $editable): string
{
    if (empty($addresses)) {
        return '<div class="text-sm text-gray-500">No addresses on file.</div>';
    }

    ob_start();
    foreach ($addresses as $address) {
        $id = htmlspecialchars((string)($address['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars((string)($address['addressName'] ?? ''), ENT_QUOTES, 'UTF-8');
        $line1 = htmlspecialchars((string)($address['addressLine1'] ?? ''), ENT_QUOTES, 'UTF-8');
        $line2Raw = trim((string)($address['addressLine2'] ?? ''));
        $line2 = $line2Raw !== '' ? htmlspecialchars($line2Raw, ENT_QUOTES, 'UTF-8') : '';
        $city = htmlspecialchars((string)($address['city'] ?? ''), ENT_QUOTES, 'UTF-8');
        $state = htmlspecialchars((string)($address['state'] ?? ''), ENT_QUOTES, 'UTF-8');
        $zip = htmlspecialchars((string)($address['zipCode'] ?? ''), ENT_QUOTES, 'UTF-8');
        $isDefault = !empty($address['isDefault']);

        $containerClasses = 'customer-address-item border border-gray-200 rounded-md p-3 flex flex-col gap-2';
        if ($isDefault) {
            $containerClasses .= ' bg-green-50 border-green-300';
        }

        echo '<div class="' . $containerClasses . '" data-address-id="' . $id . '" data-address-default="' . ($isDefault ? '1' : '0') . '">';
        echo '<div class="text-sm text-gray-700">';
        echo '<div class="font-medium">' . $name;
        if ($isDefault) {
            echo ' <span class="ml-2 text-xs font-semibold text-green-700">(Default)</span>';
        }
        echo '</div>';
        echo '<div>' . $line1 . '</div>';
        if ($line2 !== '') {
            echo '<div>' . $line2 . '</div>';
        }
        echo '<div>' . $city . ', ' . $state . ' ' . $zip . '</div>';
        echo '</div>';

        if ($editable && $id !== '') {
            echo '<div class="flex flex-wrap gap-2">';
            echo '<button type="button" class="btn btn-small" data-action="customer-address-edit" data-address-id="' . $id . '">Edit</button>';
            if (!$isDefault) {
                echo '<button type="button" class="btn btn-small btn-secondary" data-action="customer-address-default" data-address-id="' . $id . '">Make Default</button>';
            }
            echo '<button type="button" class="btn btn-small btn-danger" data-action="customer-address-delete" data-address-id="' . $id . '">Delete</button>';
            echo '</div>';
        }

        echo '</div>';
    }

    return ob_get_clean();
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

<?php /* Removed redundant wrapper include to avoid duplicate nav and stray endif */ ?>


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
                                <a href="<?= getSortUrl('name', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='name' ? ' is-active' : '' ?>">
                                    Customer <?= getSortIndicator('name', $sortBy, $sortDir) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('email', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='email' ? ' is-active' : '' ?>">
                                    Email <?= getSortIndicator('email', $sortBy, $sortDir) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('role', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='role' ? ' is-active' : '' ?>">
                                    Role <?= getSortIndicator('role', $sortBy, $sortDir) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('orders', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='orders' ? ' is-active' : '' ?>">
                                    Orders <?= getSortIndicator('orders', $sortBy, $sortDir) ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customersData)): ?>
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
                            <?php foreach ($customersData as $customer):
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
                                        <?php $linkBaseTbl = $_GET; unset($linkBaseTbl['view'], $linkBaseTbl['edit']); $linkBaseTbl['section']='customers';
                                        $viewHref = '/sections/admin_router.php?' . http_build_query(array_merge($linkBaseTbl, ['view' => $customerId]));
                                        $editHref = '/sections/admin_router.php?' . http_build_query(array_merge($linkBaseTbl, ['edit' => $customerId])); ?>
                                        <a href="<?= htmlspecialchars($viewHref) ?>"
                                           class="admin-action-button btn btn-xs btn-icon btn-icon--view" title="View Customer" aria-label="View Customer"></a>
                                        <a href="<?= htmlspecialchars($editHref) ?>"
                                           class="admin-action-button btn btn-xs btn-icon btn-icon--edit" title="Edit Customer" aria-label="Edit Customer"></a>
                                        <button data-action="confirm-delete" data-customer-id="<?= $customerId ?>" data-customer-name="<?= htmlspecialchars($firstName . ' ' . $lastName) ?>"
                                                class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" title="Delete Customer" aria-label="Delete Customer"></button>
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
<?php
    // Compute prev/next using the full customers list that's displayed in the table
    $currentId = (string)($editCustomer['id'] ?? '');
    $prevCustomerId = null;
    $nextCustomerId = null;
    $rawIds = array_values(array_map(fn($c) => (string)($c['id'] ?? ''), $customersData));
    $norm = static function ($v) { return strtolower(trim((string)$v)); };
    $idListNorm = array_map($norm, $rawIds);
    $idx = array_search($norm($currentId), $idListNorm, true);
    if ($idx === false) {
        // Fallback: try to locate by username using the original view/edit param
        $openKey = $_GET['view'] ?? ($_GET['edit'] ?? null);
        if ($openKey !== null && $openKey !== '') {
            $rawUsernames = array_values(array_map(fn($c) => (string)($c['username'] ?? ''), $customersData));
            $userIdx = array_search($norm($openKey), array_map($norm, $rawUsernames), true);
            if ($userIdx !== false) {
                $idx = $userIdx;
            }
        }
    }
    $n = count($rawIds);
    if ($idx !== false && $n > 0) {
        $prevCustomerId = $rawIds[(($idx - 1 + $n) % $n)];
        $nextCustomerId = $rawIds[(($idx + 1) % $n)];
    }
    // debug panel removed
    $modeParam = $modalMode === 'edit' ? 'edit' : 'view';
?>
<!-- Customer View/Edit Modal -->
<div class="customer-modal admin-modal-overlay wf-overlay-viewport over-header topmost show" id="customerModalOuter" data-action="close-customer-editor-on-overlay">
    <!-- Navigation Arrows -->
    <?php
        $linkBase = $_GET;
        unset($linkBase['view'], $linkBase['edit']);
        $linkBase['section'] = 'customers';
        $prevTarget = ($prevCustomerId !== null && $prevCustomerId !== '') ? $prevCustomerId : $currentId;
        $nextTarget = ($nextCustomerId !== null && $nextCustomerId !== '') ? $nextCustomerId : $currentId;
        $prevHref = '/sections/admin_router.php?' . http_build_query(array_merge($linkBase, [$modeParam => $prevTarget]));
        $nextHref = '/sections/admin_router.php?' . http_build_query(array_merge($linkBase, [$modeParam => $nextTarget]));
    ?>
    <a href="<?= htmlspecialchars($prevHref) ?>"
       class="nav-arrow nav-arrow-left wf-nav-arrow wf-nav-left" title="Previous customer" aria-label="Previous customer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M15 19l-7-7 7-7" />
        </svg>
    </a>
    <a href="<?= htmlspecialchars($nextHref) ?>"
       class="nav-arrow nav-arrow-right wf-nav-arrow wf-nav-right" title="Next customer" aria-label="Next customer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 5l7 7-7 7" />
        </svg>
    </a>

    <div class="admin-modal">
        <!-- Modal Header -->
        <div class="modal-header flex items-center justify-between gap-2">
            <h2 class="modal-title">
                <?= $modalMode === 'view' ? 'View Customer: ' : 'Edit Customer: ' ?>
                <?= htmlspecialchars(($editCustomer['firstName'] ?? '') . ' ' . ($editCustomer['lastName'] ?? '')) ?>
            </h2>
            <?php if ($modalMode === 'view'): ?>
                <a href="/sections/admin_router.php?section=customers&amp;edit=<?= htmlspecialchars($editCustomer['id'] ?? '') ?>"
                   class="btn btn-primary btn-small" data-action="navigate-to-edit">Edit Customer</a>
            <?php elseif ($modalMode === 'edit'): ?>
                <button type="submit" form="customerForm" class="btn btn-primary btn-small" data-action="save-customer">Save</button>
            <?php endif; ?>
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
                                   class="form-input" placeholder="Enter new password (min 6 characters)" minlength="6" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword"
                                   class="form-input" placeholder="Confirm new password" autocomplete="new-password">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="address-col">
                <div class="form-section">
                    <h5 class="form-section-title">Address Information</h5>
                    <?php $normalizedAddresses = array_map('normalizeCustomerAddress', $customerAddresses); ?>
                    <?php if ($modalMode === 'view'): ?>
                        <div id="viewCustomerAddresses" class="customer-address-list">
                            <?= renderCustomerAddressesHtml($normalizedAddresses, false) ?>
                        </div>
                    <?php else: ?>
                        <div class="customer-address-editor" data-customer-id="<?= htmlspecialchars($editCustomer['id'] ?? '') ?>">
                            <div class="customer-address-list" id="editCustomerAddresses">
                                <?= renderCustomerAddressesHtml($normalizedAddresses, true) ?>
                            </div>
                            <button type="button" class="btn btn-small btn-outline" data-action="customer-address-add">Add Address</button>
                        </div>
                    <?php endif; ?>
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

        <!-- Modal Footer intentionally empty for edit mode (no bottom controls) -->

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


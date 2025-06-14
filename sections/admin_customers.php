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
    .modal-form-container { flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column; padding-right: 0.5rem; }
    @media (min-width: 768px) { .modal-form-container { flex-direction: row; } }
    .modal-form-main-column { flex: 1; padding-right: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; }
    @media (max-width: 767px) { .modal-form-main-column { padding-right: 0; } }
    .modal-form-side-column { width: 100%; padding-left: 0; margin-top: 1rem; }
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
</style>

<div class="container mx-auto px-4 py-6">
    <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h1 class="customers-title text-2xl font-bold" style="color: #87ac3a !important;">Customer Management</h1>
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
                        $firstName = $customer['first_name'] ?? '';
                        $lastName = $customer['last_name'] ?? '';
                        $email = $customer['email'] ?? '';
                        $role = $customer['role'] ?? '';
                        $customerOrders = getCustomerOrders($customerId, $ordersData);
                        $orderCount = count($customerOrders);
                    ?>
                    <tr class="hover:bg-gray-50">
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
                            <button onclick="viewCustomer('<?= $customerId; ?>')" class="action-btn view-btn" title="View Customer">👁️</button>
                            <button onclick="editCustomer('<?= $customerId; ?>')" class="action-btn edit-btn" title="Edit Customer">✏️</button>
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

<!-- Customer Detail Modal -->
<div id="customerDetailModal" class="modal-outer" style="display: none;">
    <div class="modal-content-wrapper">
        <div class="flex justify-between items-center mb-3">
            <h2 class="text-lg font-bold text-green-700" id="modalTitle">Customer Profile</h2>
            <button type="button" onclick="closeCustomerModal()" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
        </div>

        <div class="modal-form-container">
            <div class="modal-form-main-column">
                <div id="modalContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
            
            <div class="modal-form-side-column">
                <div class="bg-gray-50 border-radius: 6px; padding: 10px; border: 1px solid #e2e8f0; height: 100%; display: flex; flex-direction: column;">
                    <h3 class="color: #374151; font-size: 1rem; font-weight: 600; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #d1d5db;">Order History</h3>
                    <div id="orderHistory" style="flex-grow: 1; overflow-y: auto; max-height: 300px;">
                        Loading orders...
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-auto pt-4 border-t">
            <button type="button" onclick="closeCustomerModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 text-sm">Close</button>
            <button type="button" id="editCustomerBtn" onclick="editCustomerFromModal()" class="brand-button px-4 py-2 rounded text-sm" style="display: none;">Edit Customer</button>
        </div>
    </div>
</div>

<script>
function confirmDelete(customerId, customerName) {
    document.getElementById('delete_customer_id').value = customerId;
    document.getElementById('modal-message').innerText = `Are you sure you want to delete ${customerName}? This action cannot be undone.`;
    document.getElementById('deleteConfirmModal').classList.add('show');
}

function closeModal() {
    document.getElementById('deleteConfirmModal').classList.remove('show');
}

function closeCustomerModal() {
    document.getElementById('customerDetailModal').style.display = 'none';
}

async function viewCustomer(customerId) {
    try {
        // Show modal and loading state
        const modal = document.getElementById('customerDetailModal');
        const modalContent = document.getElementById('modalContent');
        const modalTitle = document.getElementById('modalTitle');
        const editBtn = document.getElementById('editCustomerBtn');
        
        modalTitle.textContent = 'Customer Profile';
        modalContent.innerHTML = '<div class="text-center py-8">Loading customer details...</div>';
        editBtn.style.display = 'none';
        modal.style.display = 'flex';
        
        // Fetch customer data
        const response = await fetch(`/process_customers_get.php?id=${customerId}`);
        const customer = await response.json();
        
        if (!response.ok) {
            throw new Error(customer.error || 'Failed to load customer');
        }
        
        // Build customer details HTML
        const firstName = customer.firstName || '';
        const lastName = customer.lastName || '';
        const email = customer.email || '';
        const username = customer.username || '';
        const role = customer.role || '';
        const phoneNumber = customer.phoneNumber || '';
        const addressLine1 = customer.addressLine1 || '';
        const addressLine2 = customer.addressLine2 || '';
        const city = customer.city || '';
        const state = customer.state || '';
        const zipCode = customer.zipCode || '';
        
        const initials = (firstName && lastName) ? 
            (firstName.charAt(0) + lastName.charAt(0)).toUpperCase() : 
            (firstName ? firstName.substring(0, 2).toUpperCase() : 
             (lastName ? lastName.substring(0, 2).toUpperCase() : 'CU'));
        
        modalContent.innerHTML = `
            <div>
                <h4 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h4>
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 h-16 w-16">
                        <div class="h-16 w-16 rounded-full bg-green-100 flex items-center justify-center">
                            <span class="text-green-600 font-medium text-xl">${initials}</span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-lg font-medium text-gray-900">
                            <span class="admin-data-value">${firstName} ${lastName}</span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <span class="admin-data-label">Username:</span> <span class="admin-data-value">${username}</span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <span class="admin-data-label">Email:</span> <span class="admin-data-value">${email}</span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <span class="admin-data-label">Role:</span> <span class="admin-data-value">${role}</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h5 class="text-sm font-medium text-gray-700 mb-2">Contact Information</h5>
                    ${phoneNumber ? `<div class="text-sm text-gray-500 mb-1">
                        <span class="admin-data-label">Phone:</span> <span class="admin-data-value">${phoneNumber}</span>
                    </div>` : '<div class="text-sm text-gray-500 italic">No phone number provided</div>'}
                    
                    <h5 class="text-sm font-medium text-gray-700 mt-4 mb-2">Address</h5>
                    ${addressLine1 ? `<div class="text-sm text-gray-500 mb-1">
                        <span class="admin-data-value">${addressLine1}</span>
                    </div>` : ''}
                    ${addressLine2 ? `<div class="text-sm text-gray-500 mb-1">
                        <span class="admin-data-value">${addressLine2}</span>
                    </div>` : ''}
                    ${(city || state || zipCode) ? `<div class="text-sm text-gray-500 mb-1">
                        <span class="admin-data-value">${city}${city && state ? ', ' : ''}${state}${zipCode ? ' ' + zipCode : ''}</span>
                    </div>` : ''}
                    ${(!addressLine1 && !addressLine2 && !city && !state && !zipCode) ? '<div class="text-sm text-gray-500 italic">No address provided</div>' : ''}
                </div>
            </div>
        `;
        
        // Show edit button and store customer ID
        editBtn.style.display = 'inline-block';
        editBtn.dataset.customerId = customerId;
        
        // Load order history
        loadCustomerOrders(customerId);
        
    } catch (error) {
        document.getElementById('modalContent').innerHTML = `
            <div class="text-center py-8 text-red-600">
                Error loading customer: ${error.message}
            </div>
        `;
    }
}

async function loadCustomerOrders(customerId) {
    try {
        const response = await fetch(`/process_customer_orders.php?customerId=${customerId}`);
        const orders = await response.json();
        
        if (!response.ok) {
            throw new Error(orders.error || 'Failed to load orders');
        }
        
        const orderHistoryDiv = document.getElementById('orderHistory');
        
        if (!orders || orders.length === 0) {
            orderHistoryDiv.innerHTML = '<p class="text-gray-500 italic text-sm">No orders found for this customer.</p>';
            return;
        }
        
        let ordersHtml = '';
        orders.forEach(order => {
            const orderDate = new Date(order.createdAt).toLocaleDateString();
            const statusClass = `status-${order.status.toLowerCase()}`;
            
            ordersHtml += `
                <div class="order-item">
                    <h5>Order #${order.id}</h5>
                    <div class="order-detail">
                        <span>Date:</span>
                        <span>${orderDate}</span>
                    </div>
                    <div class="order-detail">
                        <span>Total:</span>
                        <span>$${parseFloat(order.totalAmount || 0).toFixed(2)}</span>
                    </div>
                    <div class="order-detail">
                        <span>Status:</span>
                        <span class="order-status ${statusClass}">${order.status}</span>
                    </div>
                    <div class="order-detail">
                        <span>Payment:</span>
                        <span>${order.paymentMethod || 'N/A'} - ${order.paymentStatus || 'N/A'}</span>
                    </div>
                    ${order.shippingMethod ? `<div class="order-detail">
                        <span>Shipping:</span>
                        <span>${order.shippingMethod}</span>
                    </div>` : ''}
                </div>
            `;
        });
        
        orderHistoryDiv.innerHTML = ordersHtml;
        
    } catch (error) {
        document.getElementById('orderHistory').innerHTML = `
            <p class="text-red-500 text-sm">Error loading orders: ${error.message}</p>
        `;
    }
}

function editCustomer(customerId) {
    // Redirect to edit page
    window.location.href = `/?page=admin&section=customers&action=edit&id=${customerId}`;
}

function editCustomerFromModal() {
    const customerId = document.getElementById('editCustomerBtn').dataset.customerId;
    if (customerId) {
        editCustomer(customerId);
    }
}
</script>

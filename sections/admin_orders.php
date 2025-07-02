<?php

require_once __DIR__ . '/../includes/auth.php';
// Admin Orders Management Section
ob_start();

// Use centralized database connection
require_once __DIR__ . '/../includes/functions.php';

// Get database instance
$db = Database::getInstance();


// Get filter parameters with null coalescing
$filters = [
    'date' => $_GET['filter_date'] ?? '',
    'items' => $_GET['filter_items'] ?? '',
    'status' => $_GET['filter_status'] ?? '',
    'payment_method' => $_GET['filter_payment_method'] ?? '',
    'shipping_method' => $_GET['filter_shipping_method'] ?? '',
    'payment_status' => $_GET['filter_payment_status'] ?? ''
];

// Build dynamic WHERE clause
$conditions = [];
$params = [];

// Apply filters with null coalescing
array_walk($filters, function($value, $key) use (&$conditions, &$params) {
    if (empty($value)) return;
    
    switch($key) {
        case 'date':
            $conditions[] = "DATE(o.date) = ?";
            $params[] = $value;
            break;
        case 'items':
            $conditions[] = "EXISTS (SELECT 1 FROM order_items oi LEFT JOIN items i ON oi.sku = i.sku WHERE oi.orderId = o.id AND (COALESCE(i.name, oi.sku) LIKE ? OR oi.sku LIKE ?))";
            $params[] = "%{$value}%";
            $params[] = "%{$value}%";
            break;
        case 'status':
            $conditions[] = "o.status = ?";
            $params[] = $value;
            break;
        case 'payment_method':
            $conditions[] = "o.paymentMethod = ?";
            $params[] = $value;
            break;
        case 'shipping_method':
            $conditions[] = "o.shippingMethod = ?";
            $params[] = $value;
            break;
        case 'payment_status':
            $conditions[] = "o.paymentStatus = ?";
            $params[] = $value;
            break;
    }
});

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Main orders query with optimized JOIN
if (!empty($params)) {
    $stmt = $db->prepare("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode 
                          FROM orders o 
                          JOIN users u ON o.userId = u.id 
                          {$whereClause} 
                          ORDER BY o.date DESC");
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $orders = $db->query("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode 
                          FROM orders o 
                          JOIN users u ON o.userId = u.id 
                          ORDER BY o.date DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// Get filter dropdown options with single queries
$dropdownOptions = [
    'status' => $db->query("SELECT DISTINCT order_status FROM orders WHERE order_status IN ('Pending','Processing','Shipped','Delivered','Cancelled') ORDER BY order_status")->fetchAll(PDO::FETCH_COLUMN),
    'payment_method' => $db->query("SELECT DISTINCT paymentMethod FROM orders WHERE paymentMethod IS NOT NULL AND paymentMethod != '' ORDER BY paymentMethod")->fetchAll(PDO::FETCH_COLUMN),
    'shipping_method' => $db->query("SELECT DISTINCT shippingMethod FROM orders WHERE shippingMethod IS NOT NULL AND shippingMethod != '' ORDER BY shippingMethod")->fetchAll(PDO::FETCH_COLUMN),
    'payment_status' => $db->query("SELECT DISTINCT paymentStatus FROM orders WHERE paymentStatus IS NOT NULL AND paymentStatus != '' ORDER BY paymentStatus")->fetchAll(PDO::FETCH_COLUMN)
];

// Modal state management
$modalState = [
    'mode' => '',
    'view_id' => $_GET['view'] ?? '',
    'edit_id' => $_GET['edit'] ?? ''
];

if ($modalState['view_id']) $modalState['mode'] = 'view';
if ($modalState['edit_id']) $modalState['mode'] = 'edit';

// Get all items for modal dropdowns
$allItems = $db->query("SELECT sku, name, retailPrice as basePrice FROM items ORDER BY name");

// Message handling
$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

// Helper functions
function formatAddress($order) {
    if (!empty($order['shippingAddress'])) {
        // Handle JSON shipping address
        $decoded = json_decode($order['shippingAddress'], true);
        if ($decoded) {
            $parts = array_filter([
                $decoded['addressLine1'] ?? '',
                $decoded['addressLine2'] ?? '',
                $decoded['city'] ?? '',
                $decoded['state'] ?? '',
                $decoded['zipCode'] ?? ''
            ]);
            return implode(', ', $parts);
        }
        return $order['shippingAddress'];
    }
    
    // Fallback to customer address
    $parts = array_filter([
        $order['addressLine1'] ?? '',
        $order['addressLine2'] ?? '',
        $order['city'] ?? '',
        $order['state'] ?? '',
        $order['zipCode'] ?? ''
    ]);
    return implode(', ', $parts) ?: 'N/A';
}

function getStatusBadgeClass($status) {
    return match(strtolower($status ?? '')) {
        'pending' => 'badge-status-pending',
        'processing' => 'badge-status-processing', 
        'shipped' => 'badge-status-shipped',
        'delivered' => 'badge-status-delivered',
        'cancelled' => 'badge-status-cancelled',
        default => 'badge-status-default'
    };
}

function getPaymentStatusBadgeClass($status) {
    return match(strtolower($status ?? '')) {
        'pending' => 'badge-payment-pending',
        'received' => 'badge-payment-received',
        'processing' => 'badge-payment-processing',
        'refunded' => 'badge-payment-refunded',
        'failed' => 'badge-payment-failed',
        default => 'badge-payment-default'
    };
}
?>

<div class="admin-content-container">
    <div class="admin-filter-section">
        <form method="GET" class="admin-filter-form">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="orders">
            
            <input type="date" name="filter_date" 
                   value="<?= htmlspecialchars($filters['date']) ?>" class="admin-form-input">
            
            <input type="text" name="filter_items" 
                   value="<?= htmlspecialchars($filters['items']) ?>" 
                   placeholder="Search items..." class="admin-form-input">
            
            <select name="filter_status" class="admin-form-select">
                <option value="">All Status</option>
                <?php foreach ($dropdownOptions['status'] as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" 
                        <?= $filters['status'] === $status ? 'selected' : '' ?>>
                    <?= htmlspecialchars($status) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="filter_payment_method" class="admin-form-select">
                <option value="">All Payment</option>
                <?php foreach ($dropdownOptions['payment_method'] as $method): ?>
                <option value="<?= htmlspecialchars($method) ?>" 
                        <?= $filters['payment_method'] === $method ? 'selected' : '' ?>>
                    <?= htmlspecialchars($method) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="filter_shipping_method" class="admin-form-select">
                <option value="">All Shipping</option>
                <?php foreach ($dropdownOptions['shipping_method'] as $method): ?>
                <option value="<?= htmlspecialchars($method) ?>" 
                        <?= $filters['shipping_method'] === $method ? 'selected' : '' ?>>
                    <?= htmlspecialchars($method) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
            <select name="filter_payment_status" class="admin-form-select">
                <option value="">All Pay Status</option>
                <?php foreach ($dropdownOptions['payment_status'] as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" 
                        <?= $filters['payment_status'] === $status ? 'selected' : '' ?>>
                    <?= htmlspecialchars($status) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn-primary admin-filter-button">Filter</button>
            <a href="/?page=admin&section=orders" class="btn-secondary admin-filter-button">Clear</a>
        </form>
    </div>

    <div class="admin-table-section">
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th style="white-space: nowrap;">Date</th>
                    <th style="white-space: nowrap;">Time</th>
                    <th>Items</th>
                    <th>Order Status</th>
                    <th>Payment</th>
                    <th>Shipping</th>
                    <th>Pay Status</th>
                    <th>Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="11" class="text-center text-gray-500 py-8">
                        No orders found matching the current filters.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <?php
                // Get order items
                $stmt = $db->prepare("SELECT oi.*, COALESCE(i.name, oi.sku) as item_name 
                                      FROM order_items oi 
                                      LEFT JOIN items i ON oi.sku = i.sku 
                                      WHERE oi.orderId = ?");
                $stmt->execute([$order['id']]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $itemsList = implode(', ', array_map(fn($item) => $item['item_name'] . ' (x' . $item['quantity'] . ')', $items));
                $totalItems = array_sum(array_column($items, 'quantity'));
                ?>
                <tr>
                    <td class="font-mono"><?= htmlspecialchars($order['id']) ?></td>
                    <td><?= htmlspecialchars($order['username'] ?? 'N/A') ?></td>
                    <td class="editable-field" data-order-id="<?= $order['id'] ?>" data-field="date" data-type="date" style="white-space: nowrap;">
                        <?= htmlspecialchars(date('M j, Y', strtotime($order['date'] ?? 'now'))) ?>
                    </td>
                    <td class="text-gray-600" style="white-space: nowrap;">
                        <?= htmlspecialchars(date('g:i A', strtotime($order['date'] ?? 'now'))) ?>
                    </td>
                    <td class="items-cell text-center" title="<?= htmlspecialchars($itemsList) ?>">
                        <?= $totalItems ?>
                    </td>
                    <td class="editable-field" data-order-id="<?= $order['id'] ?>" data-field="order_status" data-type="select">
                        <span class="status-badge <?= getStatusBadgeClass($order['order_status']) ?>">
                            <?= htmlspecialchars($order['order_status'] ?? 'Pending') ?>
                        </span>
                    </td>
                    <td class="editable-field" data-order-id="<?= $order['id'] ?>" data-field="paymentMethod" data-type="select">
                        <?= htmlspecialchars($order['paymentMethod'] ?? 'N/A') ?>
                    </td>
                    <td class="editable-field" data-order-id="<?= $order['id'] ?>" data-field="shippingMethod" data-type="select">
                        <?= htmlspecialchars($order['shippingMethod'] ?? 'N/A') ?>
                    </td>
                    <td class="editable-field" data-order-id="<?= $order['id'] ?>" data-field="paymentStatus" data-type="select">
                        <span class="payment-status-badge <?= getPaymentStatusBadgeClass($order['paymentStatus']) ?>">
                            <?= htmlspecialchars($order['paymentStatus'] ?? 'Pending') ?>
                        </span>
                    </td>
                    <td class="font-bold">$<?= number_format($order['total'] ?? 0, 2) ?></td>
                    <td>
                        <div class="flex space-x-2">
                            <a href="?page=admin&section=orders&view=<?= $order['id'] ?>" 
                               class="text-blue-600 hover:text-blue-800" title="View Order">👁️</a>
                            <a href="?page=admin&section=orders&edit=<?= $order['id'] ?>" 
                               class="text-green-600 hover:text-green-800" title="Edit Order">✏️</a>
                            <button onclick="confirmDelete('<?= htmlspecialchars($order['id']) ?>')" 
                                    class="text-red-600 hover:text-red-800" title="Delete Order">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Order View/Edit Modal -->
<?php if ($modalState['mode']): ?>
<?php
$orderId = $modalState['view_id'] ?: $modalState['edit_id'];
$stmt = $db->prepare("SELECT o.*, u.username, u.email, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode FROM orders o JOIN users u ON o.userId = u.id WHERE o.id = ?");
$stmt->execute([$orderId]);
$orderData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($orderData):
    $stmt = $db->prepare("SELECT oi.*, COALESCE(i.name, oi.sku) as item_name, i.retailPrice 
                          FROM order_items oi 
                          LEFT JOIN items i ON oi.sku = i.sku 
                          WHERE oi.orderId = ?");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all orders for navigation
    $allOrderIds = $db->query("SELECT id FROM orders ORDER BY date DESC")->fetchAll(PDO::FETCH_COLUMN);
    $currentIndex = array_search($orderId, $allOrderIds);
    $prevOrderId = $currentIndex > 0 ? $allOrderIds[$currentIndex - 1] : null;
    $nextOrderId = $currentIndex < count($allOrderIds) - 1 ? $allOrderIds[$currentIndex + 1] : null;
?>

<div class="modal-overlay order-modal" id="orderModal">
    <!-- Navigation Arrows -->
    <?php if ($prevOrderId): ?>
    <a href="?page=admin&section=orders&<?= $modalState['mode'] ?>=<?= $prevOrderId ?>" 
       class="nav-arrow nav-arrow-left">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <?php endif; ?>
    
    <?php if ($nextOrderId): ?>
    <a href="?page=admin&section=orders&<?= $modalState['mode'] ?>=<?= $nextOrderId ?>" 
       class="nav-arrow nav-arrow-right">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
    <?php endif; ?>

    <div class="modal-content order-modal-content">
        <!-- Modal Header -->
        <div class="modal-header">
            <h2 class="modal-title">
                <?= $modalState['mode'] === 'view' ? 'View' : 'Edit' ?> Order: <?= htmlspecialchars($orderId) ?>
            </h2>
            <a href="?page=admin&section=orders" class="modal-close">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </a>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
            <form id="orderForm" class="order-form-grid">
                <!-- Order Details Column -->
                <div class="order-details-column">
                    <div class="form-section">
                        <h3 class="form-section-title">Order Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Order ID</label>
                                <input type="text" value="<?= htmlspecialchars($orderId) ?>" class="form-input" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Date</label>
                                <input type="datetime-local" name="date" 
                                       value="<?= date('Y-m-d\TH:i', strtotime($orderData['date'])) ?>" 
                                       class="form-input" <?= $modalState['mode'] === 'view' ? 'readonly' : '' ?>>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Order Status</label>
                                <select name="order_status" class="form-select" <?= $modalState['mode'] === 'view' ? 'disabled' : '' ?>>
                                    <?php foreach (['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'] as $status): ?>
                                    <option value="<?= $status ?>" <?= $orderData['order_status'] === $status ? 'selected' : '' ?>>
                                        <?= $status ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Total</label>
                                <input type="number" name="total" step="0.01" 
                                       value="<?= $orderData['total'] ?>" 
                                       class="form-input" <?= $modalState['mode'] === 'view' ? 'readonly' : '' ?>>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">Customer Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Customer</label>
                                <input type="text" value="<?= htmlspecialchars($orderData['username']) ?>" class="form-input" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" value="<?= htmlspecialchars($orderData['email']) ?>" class="form-input" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">Payment & Shipping</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Payment Method</label>
                                <select name="paymentMethod" class="form-select" <?= $modalState['mode'] === 'view' ? 'disabled' : '' ?>>
                                    <?php foreach (['Credit Card', 'Cash', 'Check', 'PayPal', 'Venmo', 'Other'] as $method): ?>
                                    <option value="<?= $method ?>" <?= $orderData['paymentMethod'] === $method ? 'selected' : '' ?>>
                                        <?= $method ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Payment Status</label>
                                <select name="paymentStatus" class="form-select" <?= $modalState['mode'] === 'view' ? 'disabled' : '' ?>>
                                    <?php foreach (['Pending', 'Received', 'Processing', 'Refunded', 'Failed'] as $status): ?>
                                    <option value="<?= $status ?>" <?= $orderData['paymentStatus'] === $status ? 'selected' : '' ?>>
                                        <?= $status ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Shipping Method</label>
                                <select name="shippingMethod" class="form-select" <?= $modalState['mode'] === 'view' ? 'disabled' : '' ?>>
                                    <?php foreach (['Customer Pickup', 'Local Delivery', 'USPS', 'FedEx', 'UPS'] as $method): ?>
                                    <option value="<?= $method ?>" <?= $orderData['shippingMethod'] === $method ? 'selected' : '' ?>>
                                        <?= $method ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items Column -->
                <div class="order-items-column">
                    <div class="form-section">
                        <div class="form-section-header">
                            <h3 class="form-section-title">Order Items</h3>
                            <?php if ($modalState['mode'] === 'edit'): ?>
                            <button type="button" class="btn-small btn-primary" onclick="showAddItemModal()">
                                ➕ Add Item
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="order-items-list" id="orderItemsList">
                            <?php foreach ($orderItems as $item): ?>
                            <div class="order-item-card" data-item-id="<?= $item['id'] ?>">
                                <div class="order-item-details">
                                    <div class="order-item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                                    <div class="order-item-sku">SKU: <?= htmlspecialchars($item['sku']) ?></div>
                                    <div class="order-item-price">$<?= number_format($item['price'], 2) ?> × 
                                        <?php if ($modalState['mode'] === 'edit'): ?>
                                        <input type="number" 
                                               class="quantity-input" 
                                               value="<?= $item['quantity'] ?>" 
                                               min="1" 
                                               onchange="updateItemQuantity(<?= $item['id'] ?>, this.value)">
                                        <?php else: ?>
                                        <?= $item['quantity'] ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="order-item-actions">
                                    <div class="order-item-total">
                                        $<?= number_format($item['price'] * $item['quantity'], 2) ?>
                                    </div>
                                    <?php if ($modalState['mode'] === 'edit'): ?>
                                    <button type="button" 
                                            class="btn-small btn-danger" 
                                            onclick="removeItemFromOrder(<?= $item['id'] ?>)"
                                            title="Remove item">
                                        🗑️
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="order-total">
                            <strong>Total: $<span id="orderTotal"><?= number_format($orderData['total'], 2) ?></span></strong>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-header">
                            <h3 class="form-section-title">Shipping Address</h3>
                            <?php if ($modalState['mode'] === 'edit'): ?>
                            <div class="address-actions">
                                <button type="button" class="btn-small btn-secondary" onclick="showAddressSelector()">
                                    📍 Select Address
                                </button>
                                <button type="button" class="btn-small btn-primary" onclick="showAddAddressModal()">
                                    ➕ Add New
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="address-display" id="currentAddress">
                            <?= nl2br(htmlspecialchars(formatAddress($orderData))) ?>
                        </div>
                        <?php if ($modalState['mode'] === 'edit'): ?>
                        <div class="form-group mt-3">
                            <button type="button" class="btn-small btn-secondary" onclick="editCurrentAddress()">
                                ✏️ Edit Current Address
                            </button>
                            <button type="button" class="btn-small btn-primary" onclick="impersonateCustomer('<?= $orderData['userId'] ?>')">
                                👤 Shop as Customer
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">Notes</h3>
                        <div class="form-group">
                            <label class="form-label">Order Notes</label>
                            <textarea name="note" class="form-textarea" rows="3" 
                                      <?= $modalState['mode'] === 'view' ? 'readonly' : '' ?>><?= htmlspecialchars($orderData['note'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Notes</label>
                            <textarea name="paynote" class="form-textarea" rows="2" 
                                      <?= $modalState['mode'] === 'view' ? 'readonly' : '' ?>><?= htmlspecialchars($orderData['paynote'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <?php if ($modalState['mode'] === 'edit'): ?>
        <div class="modal-footer">
            <button type="button" onclick="window.location.href='?page=admin&section=orders'" class="btn-secondary">
                Cancel
            </button>
            <button type="submit" form="orderForm" class="btn-primary">
                Save Changes
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay delete-confirmation-modal" id="deleteModal" style="display: none;">
    <div class="modal-content compact-modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Confirm Delete</h3>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this order? This action cannot be undone.</p>
            <p class="text-sm text-gray-600 mt-2">Order ID: <span id="deleteOrderId" class="font-mono"></span></p>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeDeleteModal()" class="btn-secondary">Cancel</button>
            <button type="button" onclick="deleteOrder()" class="btn-danger">Delete Order</button>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($message): ?>
<div class="toast-notification <?= $messageType === 'success' ? 'toast-success' : 'toast-error' ?>" id="toast">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when filters change
    const filterInputs = document.querySelectorAll('.filter-form-orders input, .filter-form-orders select');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            const form = this.closest('.filter-form-orders');
            // Add small delay to allow user to make multiple selections
            setTimeout(() => form.submit(), 100);
        });
    });

    // Toast notification auto-hide
    const toast = document.getElementById('toast');
    if (toast) {
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 4000);
    }

    // Order form submission
    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
        orderForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'updateOrder');
            formData.append('orderId', '<?= $orderId ?? '' ?>');
            
            try {
                const response = await fetch('api/update-order.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = '?page=admin&section=orders&message=' + 
                        encodeURIComponent('Order updated successfully') + '&type=success';
                } else {
                    showError(data.error || 'Failed to update order');
                }
            } catch (error) {
                showError('Network error occurred');
            }
        });
    }

    // Inline editing functionality
    document.querySelectorAll('.editable-field').forEach(field => {
        field.addEventListener('click', function() {
            if (this.classList.contains('editing')) return;
            
            const orderId = this.dataset.orderId;
            const fieldName = this.dataset.field;
            const fieldType = this.dataset.type;
            
            let currentValue;
            if (fieldName === 'order_status' || fieldName === 'paymentStatus') {
                const badge = this.querySelector('span');
                currentValue = badge ? badge.textContent.trim() : this.textContent.trim();
            } else {
                currentValue = this.textContent.trim();
            }
            
            const originalHTML = this.innerHTML;
            this.classList.add('editing');
            
            if (fieldType === 'select') {
                const select = document.createElement('select');
                select.className = 'form-select-inline';
                
                let options = [];
                switch (fieldName) {
                    case 'order_status':
                        options = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
                        break;
                    case 'paymentMethod':
                        options = ['Credit Card', 'Cash', 'Check', 'PayPal', 'Venmo', 'Other'];
                        break;
                    case 'shippingMethod':
                        options = ['Customer Pickup', 'Local Delivery', 'USPS', 'FedEx', 'UPS'];
                        break;
                    case 'paymentStatus':
                        options = ['Pending', 'Received', 'Refunded', 'Failed'];
                        break;
                }
                
                options.forEach(option => {
                    const optionElement = document.createElement('option');
                    optionElement.value = option;
                    optionElement.textContent = option;
                    if (option === currentValue) optionElement.selected = true;
                    select.appendChild(optionElement);
                });
                
                this.innerHTML = '';
                this.appendChild(select);
                select.focus();
                
                const saveValue = async (newValue) => {
                    if (newValue === currentValue) {
                        this.classList.remove('editing');
                        this.innerHTML = originalHTML;
                        return;
                    }
                    
                    try {
                        const response = await fetch('api/fulfill_order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=updateField&orderId=${orderId}&field=${fieldName}&value=${encodeURIComponent(newValue)}`
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.classList.remove('editing');
                            
                            if (fieldName === 'order_status') {
                                this.innerHTML = `<span class="status-badge ${getStatusBadgeClass(newValue)}">${newValue}</span>`;
                            } else if (fieldName === 'paymentStatus') {
                                this.innerHTML = `<span class="payment-status-badge ${getPaymentStatusBadgeClass(newValue)}">${newValue}</span>`;
                            } else {
                                this.textContent = newValue;
                            }
                            
                            showSuccess(data.message);
                        } else {
                            showError(data.error || 'Update failed');
                            this.classList.remove('editing');
                            this.innerHTML = originalHTML;
                        }
                    } catch (error) {
                        showError('Network error occurred');
                        this.classList.remove('editing');
                        this.innerHTML = originalHTML;
                    }
                };
                
                select.addEventListener('change', () => saveValue(select.value));
                select.addEventListener('blur', () => saveValue(select.value));
                
            } else if (fieldType === 'date') {
                const input = document.createElement('input');
                input.type = 'date';
                input.className = 'form-input-inline';
                input.value = currentValue;
                
                this.innerHTML = '';
                this.appendChild(input);
                input.focus();
                
                const saveValue = async (newValue) => {
                    if (newValue === currentValue) {
                        this.classList.remove('editing');
                        this.textContent = currentValue || '';
                        return;
                    }
                    
                    try {
                        const response = await fetch('api/fulfill_order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=updateField&orderId=${orderId}&field=${fieldName}&value=${encodeURIComponent(newValue)}`
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.classList.remove('editing');
                            this.textContent = newValue || '';
                            showSuccess(data.message);
                        } else {
                            showError(data.error || 'Update failed');
                            this.classList.remove('editing');
                            this.innerHTML = originalHTML;
                        }
                    } catch (error) {
                        showError('Network error occurred');
                        this.classList.remove('editing');
                        this.innerHTML = originalHTML;
                    }
                };
                
                input.addEventListener('change', () => saveValue(input.value));
                input.addEventListener('blur', () => saveValue(input.value));
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.classList.remove('editing');
                        this.innerHTML = originalHTML;
                    }
                });
            }
        });
    });
});

// Helper functions for badge classes (simplified)
function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'badge-status-pending',
        'processing': 'badge-status-processing',
        'shipped': 'badge-status-shipped',
        'delivered': 'badge-status-delivered',
        'cancelled': 'badge-status-cancelled'
    };
    return classes[status.toLowerCase()] || 'badge-status-default';
}

function getPaymentStatusBadgeClass(status) {
    const classes = {
        'pending': 'badge-payment-pending',
        'received': 'badge-payment-received',
        'processing': 'badge-payment-processing',
        'refunded': 'badge-payment-refunded',
        'failed': 'badge-payment-failed'
    };
    return classes[status.toLowerCase()] || 'badge-payment-default';
}

// Delete confirmation modal
let deleteOrderIdToDelete = null;

function confirmDelete(orderId) {
    deleteOrderIdToDelete = orderId;
    document.getElementById('deleteOrderId').textContent = orderId;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteOrderIdToDelete = null;
}

async function deleteOrder() {
    if (!deleteOrderIdToDelete) return;
    
    try {
        const response = await fetch('api/delete-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `orderId=${deleteOrderIdToDelete}&admin_token=whimsical_admin_2024`
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = '?page=admin&section=orders&message=' + 
                encodeURIComponent('Order deleted successfully') + '&type=success';
        } else {
            showError(data.error || 'Failed to delete order');
            closeDeleteModal();
        }
    } catch (error) {
        showError('Network error occurred');
        closeDeleteModal();
    }
}

// Notification functions
// showSuccess function moved to js/global-notifications.js for centralization

// showError function moved to js/global-notifications.js for centralization

function showNotification(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => document.body.removeChild(toast), 300);
    }, 4000);
}

// Enhanced Order Management Functions
async function updateItemQuantity(itemId, quantity) {
    try {
        const response = await fetch('/api/order_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_item_quantity',
                order_item_id: itemId,
                quantity: parseInt(quantity)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            document.getElementById('orderTotal').textContent = parseFloat(data.new_total).toFixed(2);
            updateItemTotal(itemId);
        } else {
            showError(data.error || 'Failed to update quantity');
        }
    } catch (error) {
        showError('Network error occurred');
    }
}

async function removeItemFromOrder(itemId) {
    if (!confirm('Remove this item from the order?')) return;
    
    try {
        const response = await fetch('/api/order_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'remove_item_from_order',
                order_item_id: itemId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            document.querySelector(`[data-item-id="${itemId}"]`).remove();
            document.getElementById('orderTotal').textContent = parseFloat(data.new_total).toFixed(2);
        } else {
            showError(data.error || 'Failed to remove item');
        }
    } catch (error) {
        showError('Network error occurred');
    }
}

function updateItemTotal(itemId) {
    const itemCard = document.querySelector(`[data-item-id="${itemId}"]`);
    if (!itemCard) return;
    
    const quantityInput = itemCard.querySelector('.quantity-input');
    const priceText = itemCard.querySelector('.order-item-price').textContent;
    const price = parseFloat(priceText.match(/\$([0-9.]+)/)[1]);
    const quantity = parseInt(quantityInput.value);
    const total = price * quantity;
    
    itemCard.querySelector('.order-item-total').textContent = '$' + total.toFixed(2);
}

async function showAddItemModal() {
    // Create modal for adding items
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Item to Order</h3>
                <button type="button" onclick="this.closest('.modal-overlay').remove()" class="modal-close">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Search Items</label>
                    <input type="text" id="itemSearch" class="form-input" placeholder="Search by SKU or name...">
                </div>
                <div class="items-list" id="itemsList" style="max-height: 300px; overflow-y: auto;">
                    <div style="text-align: center; padding: 20px; color: #666;">Loading items...</div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Load items
    const itemSearch = modal.querySelector('#itemSearch');
    const itemsList = modal.querySelector('#itemsList');
    
    const loadItems = async (search = '') => {
        try {
            const response = await fetch(`/api/order_management.php?action=get_available_items&search=${encodeURIComponent(search)}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.items.length === 0) {
                    itemsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">No items found</div>';
                } else {
                    itemsList.innerHTML = data.items.map(item => `
                        <div class="item-card-small" onclick="addItemToOrder('${item.sku}', '${item.name.replace(/'/g, "\\'")}', ${item.retailPrice})" style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border: 1px solid #ddd; margin-bottom: 5px; cursor: pointer; border-radius: 4px;">
                            <div class="item-info">
                                <div class="item-name" style="font-weight: 500;">${item.name}</div>
                                <div class="item-sku" style="color: #666; font-size: 0.875rem;">${item.sku}</div>
                            </div>
                            <div class="item-price" style="font-weight: 600; color: #007bff;">$${parseFloat(item.retailPrice || 0).toFixed(2)}</div>
                        </div>
                    `).join('');
                }
            } else {
                itemsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">Failed to load items</div>';
            }
        } catch (error) {
            itemsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">Network error</div>';
        }
    };
    
    itemSearch.addEventListener('input', () => loadItems(itemSearch.value));
    loadItems();
}

async function addItemToOrder(sku, name, price) {
    const orderId = '<?= $orderId ?? '' ?>';
    const quantity = prompt(`Add quantity for ${name}:`, '1');
    
    if (!quantity || quantity <= 0) return;
    
    try {
        const response = await fetch('/api/order_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_item_to_order',
                order_id: orderId,
                sku: sku,
                quantity: parseInt(quantity),
                price: parseFloat(price)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            document.querySelector('.modal-overlay').remove();
            location.reload(); // Reload to show updated items
        } else {
            showError(data.error || 'Failed to add item');
        }
    } catch (error) {
        showError('Network error occurred');
    }
}

async function impersonateCustomer(customerId) {
    if (!confirm('This will switch your session to impersonate this customer. You can shop as them and then return to admin. Continue?')) return;
    
    try {
        const response = await fetch('/api/order_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'impersonate_customer',
                customer_id: customerId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (window.showSuccess) {
                window.showSuccess(data.message + ' You will now be redirected to shop as this customer.', {
                    title: '🛒 Shop as Customer',
                    duration: 5000
                });
            } else {
                alert(data.message + '\n\nYou will now be redirected to shop as this customer.');
            }
            window.location.href = data.redirect_url;
        } else {
            showError(data.error || 'Failed to impersonate customer');
        }
    } catch (error) {
        showError('Network error occurred');
    }
}

function showAddressSelector() {
    // Implementation for showing customer's saved addresses
    showError('Address selector coming soon - use Edit Current Address for now');
}

function showAddAddressModal() {
    // Implementation for adding new address
    showError('Add new address coming soon - use Edit Current Address for now');
}

function editCurrentAddress() {
    showError('Address editing coming soon - this will allow inline editing of the current address');
}
</script>

<?php
$output = ob_get_clean();
echo $output;
?>

<?php
// Admin Orders Management Section
ob_start();

// Use centralized database connection
require_once __DIR__ . '/../includes/functions.php';
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
$orders = $db->query(
    "SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode 
     FROM orders o 
     JOIN users u ON o.userId = u.id 
     {$whereClause} 
     ORDER BY o.date DESC",
    $params
);

// Get filter dropdown options with single queries
$dropdownOptions = [
    'status' => $db->query("SELECT DISTINCT status FROM orders WHERE status IN ('Pending','Processing','Shipped','Delivered','Cancelled') ORDER BY status")->fetchAll(PDO::FETCH_COLUMN),
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

<div class="admin-container">
    <!-- Orders Filter Section -->
    <div class="admin-section-header">
        <h1 class="admin-page-title">Orders Management</h1>
        
        <form method="GET" class="filter-form-orders">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="orders">
            
            <div class="filter-group">
                <label for="filter_date" class="filter-label">Date:</label>
                <input type="date" name="filter_date" id="filter_date" 
                       value="<?= htmlspecialchars($filters['date']) ?>" class="filter-input">
            </div>
            
            <div class="filter-group">
                <label for="filter_items" class="filter-label">Items:</label>
                <input type="text" name="filter_items" id="filter_items" 
                       value="<?= htmlspecialchars($filters['items']) ?>" 
                       placeholder="Search..." class="filter-input">
            </div>
            
            <div class="filter-group">
                <label for="filter_status" class="filter-label">Status:</label>
                <select name="filter_status" id="filter_status" class="filter-select">
                    <option value="">All</option>
                    <?php foreach ($dropdownOptions['status'] as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" 
                            <?= $filters['status'] === $status ? 'selected' : '' ?>>
                        <?= htmlspecialchars($status) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter_payment_method" class="filter-label">Payment:</label>
                <select name="filter_payment_method" id="filter_payment_method" class="filter-select">
                    <option value="">All</option>
                    <?php foreach ($dropdownOptions['payment_method'] as $method): ?>
                    <option value="<?= htmlspecialchars($method) ?>" 
                            <?= $filters['payment_method'] === $method ? 'selected' : '' ?>>
                        <?= htmlspecialchars($method) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter_shipping_method" class="filter-label">Shipping:</label>
                <select name="filter_shipping_method" id="filter_shipping_method" class="filter-select">
                    <option value="">All</option>
                    <?php foreach ($dropdownOptions['shipping_method'] as $method): ?>
                    <option value="<?= htmlspecialchars($method) ?>" 
                            <?= $filters['shipping_method'] === $method ? 'selected' : '' ?>>
                        <?= htmlspecialchars($method) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter_payment_status" class="filter-label">Pay Status:</label>
                <select name="filter_payment_status" id="filter_payment_status" class="filter-select">
                    <option value="">All</option>
                    <?php foreach ($dropdownOptions['payment_status'] as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" 
                            <?= $filters['payment_status'] === $status ? 'selected' : '' ?>>
                        <?= htmlspecialchars($status) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <a href="/?page=admin&section=orders" class="filter-clear-link">Clear All</a>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="admin-table-container">
        <table class="admin-table orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Shipping</th>
                    <th>Pay Status</th>
                    <th>Total</th>
                    <th>Address</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="12" class="text-center text-gray-500 py-8">
                        No orders found matching the current filters.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <?php
                // Get order items
                $items = $db->query(
                    "SELECT oi.*, COALESCE(i.name, oi.sku) as item_name 
                     FROM order_items oi 
                     LEFT JOIN items i ON oi.sku = i.sku 
                     WHERE oi.orderId = ?",
                    [$order['id']]
                );
                $itemsList = implode(', ', array_map(fn($item) => $item['item_name'] . ' (x' . $item['quantity'] . ')', $items));
                ?>
                <tr>
                    <td class="font-mono"><?= htmlspecialchars($order['id']) ?></td>
                    <td><?= htmlspecialchars($order['username'] ?? 'N/A') ?></td>
                    <td class="editable-field" data-order-id="<?= $order['id'] ?>" data-field="date" data-type="date">
                        <?= htmlspecialchars($order['date'] ?? '') ?>
                    </td>
                    <td class="items-cell" title="<?= htmlspecialchars($itemsList) ?>">
                        <?= htmlspecialchars(strlen($itemsList) > 30 ? substr($itemsList, 0, 30) . '...' : $itemsList) ?>
                    </td>
                    <td class="editable-field" data-order-id="<?= $order['id'] ?>" data-field="status" data-type="select">
                        <span class="status-badge <?= getStatusBadgeClass($order['status']) ?>">
                            <?= htmlspecialchars($order['status'] ?? 'Pending') ?>
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
                    <td class="address-cell" title="<?= htmlspecialchars(formatAddress($order)) ?>">
                        <?= htmlspecialchars(formatAddress($order)) ?>
                    </td>
                    <td class="notes-cell">
                        <div title="<?= htmlspecialchars($order['note'] ?? '') ?>">
                            <?= htmlspecialchars(strlen($order['note'] ?? '') > 20 ? substr($order['note'], 0, 20) . '...' : ($order['note'] ?? '')) ?>
                        </div>
                        <?php if (!empty($order['paynote'])): ?>
                        <div title="Pay: <?= htmlspecialchars($order['paynote']) ?>" class="text-xs text-blue-600">
                            Pay: <?= htmlspecialchars(strlen($order['paynote']) > 15 ? substr($order['paynote'], 0, 15) . '...' : $order['paynote']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="?page=admin&section=orders&view=<?= $order['id'] ?>" 
                               class="btn-action btn-view" title="View Order">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                            <a href="?page=admin&section=orders&edit=<?= $order['id'] ?>" 
                               class="btn-action btn-edit" title="Edit Order">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <button onclick="confirmDelete('<?= htmlspecialchars($order['id']) ?>')" 
                                    class="btn-action btn-delete" title="Delete Order">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
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
$orderData = $db->query("SELECT o.*, u.username, u.email, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode FROM orders o JOIN users u ON o.userId = u.id WHERE o.id = ?", [$orderId])->fetch(PDO::FETCH_ASSOC);

if ($orderData):
    $orderItems = $db->query(
        "SELECT oi.*, COALESCE(i.name, oi.sku) as item_name, i.retailPrice 
         FROM order_items oi 
         LEFT JOIN items i ON oi.sku = i.sku 
         WHERE oi.orderId = ?",
        [$orderId]
    );
    
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
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" <?= $modalState['mode'] === 'view' ? 'disabled' : '' ?>>
                                    <?php foreach (['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'] as $status): ?>
                                    <option value="<?= $status ?>" <?= $orderData['status'] === $status ? 'selected' : '' ?>>
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
                        <h3 class="form-section-title">Order Items</h3>
                        <div class="order-items-list">
                            <?php foreach ($orderItems as $item): ?>
                            <div class="order-item-card">
                                <div class="order-item-details">
                                    <div class="order-item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                                    <div class="order-item-sku">SKU: <?= htmlspecialchars($item['sku']) ?></div>
                                    <div class="order-item-price">$<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></div>
                                </div>
                                <div class="order-item-total">
                                    $<?= number_format($item['price'] * $item['quantity'], 2) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">Shipping Address</h3>
                        <div class="address-display">
                            <?= nl2br(htmlspecialchars(formatAddress($orderData))) ?>
                        </div>
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
            if (fieldName === 'status' || fieldName === 'paymentStatus') {
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
                    case 'status':
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
                            
                            if (fieldName === 'status') {
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
function showSuccess(message) {
    showNotification(message, 'success');
}

function showError(message) {
    showNotification(message, 'error');
}

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
</script>

<?php
$output = ob_get_clean();
echo $output;
?>

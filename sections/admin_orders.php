<?php
// sections/admin_orders.php — Primary implementation for Orders section

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/components/admin_order_editor.php';

// Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include dirname(__DIR__) . '/partials/header.php';
    if (!function_exists('__wf_admin_orders_footer_shutdown')) {
        function __wf_admin_orders_footer_shutdown()
        {
            @include __DIR__ . '/../partials/footer.php';
        }
    }
    register_shutdown_function('__wf_admin_orders_footer_shutdown');
}

// Always include admin navbar on orders page, even when accessed directly
$section = 'orders';
include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';

// Admin Orders Management Section
ob_start();

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
array_walk($filters, function ($value, $key) use (&$conditions, &$params) {
    if (empty($value)) {
        return;
    }

    switch ($key) {
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
    $orders = $stmt->fetchAll(); // Database class sets default fetch mode
} else {
    $orders = $db->query("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode 
                          FROM orders o 
                          JOIN users u ON o.userId = u.id 
                          ORDER BY o.date DESC")->fetchAll(); // Database class sets default fetch mode
}

// Get filter dropdown options with single queries
$dropdownOptions = [
    'status' => $db->query("SELECT DISTINCT order_status FROM orders WHERE order_status IN ('Pending','Processing','Shipped','Delivered','Cancelled') ORDER BY order_status")->fetchAll(\PDO::FETCH_COLUMN),
    'payment_method' => $db->query("SELECT DISTINCT paymentMethod FROM orders WHERE paymentMethod IS NOT NULL AND paymentMethod != '' ORDER BY paymentMethod")->fetchAll(\PDO::FETCH_COLUMN),
    'shipping_method' => $db->query("SELECT DISTINCT shippingMethod FROM orders WHERE shippingMethod IS NOT NULL AND shippingMethod != '' ORDER BY shippingMethod")->fetchAll(\PDO::FETCH_COLUMN),
    'payment_status' => $db->query("SELECT DISTINCT paymentStatus FROM orders WHERE paymentStatus IS NOT NULL AND paymentStatus != '' ORDER BY paymentStatus")->fetchAll(\PDO::FETCH_COLUMN)
];

// Modal state management
$modalState = [
    'mode' => '',
    'view_id' => $_GET['view'] ?? '',
    'edit_id' => $_GET['edit'] ?? ''
];

if ($modalState['view_id']) {
    $modalState['mode'] = 'view';
}
if ($modalState['edit_id']) {
    $modalState['mode'] = 'edit';
}

// Get all items for modal dropdowns
$allItems = $db->query("SELECT sku, name, retailPrice as basePrice FROM items ORDER BY name");

// Message handling
$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

// Helper functions
function formatAddress($order)
{
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

function getStatusBadgeClass($status)
{
    return match(strtolower($status ?? '')) {
        'pending' => 'badge-status-pending',
        'processing' => 'badge-status-processing',
        'shipped' => 'badge-status-shipped',
        'delivered' => 'badge-status-delivered',
        'cancelled' => 'badge-status-cancelled',
        default => 'badge-status-default'
    };
}

function getPaymentStatusBadgeClass($status)
{
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
        <div class="admin-filters">
        <form method="GET" action="/admin/orders" class="admin-filter-form">
            
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
            
            <span class="admin-actions">
                <button type="submit" class="btn btn-primary admin-filter-button">Filter</button>
                <a href="/admin/orders" class="btn btn-secondary admin-filter-button">Clear</a>
            </span>
        </form>
        </div>
    </div>

    <div class="admin-table-section">
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Time</th>
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
                    <td colspan="11" class="text-center text-gray-500">
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
                    $items = $stmt->fetchAll(); // Database class sets default fetch mode
                    $itemsList = implode(', ', array_map(fn ($item) => $item['item_name'] . ' (x' . $item['quantity'] . ')', $items));
                    $totalItems = array_sum(array_column($items, 'quantity'));
                    ?>
                <tr>
                    <td class="font-mono"><?= htmlspecialchars($order['id']) ?></td>
                    <td><?= htmlspecialchars($order['username'] ?? 'N/A') ?></td>
                    <td class="editable-field" data-order-id="<?= $order['id'] ?>" data-field="date" data-type="date" >
                        <?= htmlspecialchars(date('M j, Y', strtotime($order['date'] ?? 'now'))) ?>
                    </td>
                    <td class="text-gray-600" >
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
                        <div class="admin-actions">
                            <a href="/admin/orders?view=<?= $order['id'] ?>" 
                               class="text-blue-600 hover:text-blue-800" title="View Order">👁️</a>
                            <a href="/admin/orders?edit=<?= $order['id'] ?>" 
                               class="text-green-600 hover:text-green-800" title="Edit Order">✏️</a>
                            <button data-action="show-receipt" data-order-id="<?= htmlspecialchars($order['id']) ?>"
                                    class="text-purple-600 hover:text-purple-800" title="Print Receipt">🖨️</button>
                            <button data-action="confirm-delete" data-order-id="<?= htmlspecialchars($order['id']) ?>"
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
    $orderData = $stmt->fetch(); // Database class sets default fetch mode

    // Precompute navigation and items when order exists (used below)
    $orderItems = [];
    $prevOrderId = null;
    $nextOrderId = null;
    if ($orderData) {
        $stmt = $db->prepare("SELECT oi.*, COALESCE(i.name, oi.sku) as item_name, i.retailPrice 
                          FROM order_items oi 
                          LEFT JOIN items i ON oi.sku = i.sku 
                          WHERE oi.orderId = ?");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll(); // Database class sets default fetch mode

        $allOrderIds = $db->query("SELECT id FROM orders ORDER BY date DESC")->fetchAll(\PDO::FETCH_COLUMN);
        $currentIndex = array_search($orderId, $allOrderIds);
        $prevOrderId = $currentIndex > 0 ? $allOrderIds[$currentIndex - 1] : null;
        $nextOrderId = $currentIndex < count($allOrderIds) - 1 ? $allOrderIds[$currentIndex + 1] : null;
    }
    ?>

<div class="modal-overlay order-modal show" id="orderModal">
    <!-- Navigation Arrows -->
    <?php if ($prevOrderId): ?>
    <a href="/admin/orders?<?= $modalState['mode'] ?>=<?= $prevOrderId ?>" 
       class="nav-arrow nav-arrow-left">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <?php endif; ?>
    
    <?php if ($orderData && $nextOrderId): ?>
    <a href="/admin/orders?<?= $modalState['mode'] ?>=<?= $nextOrderId ?>" 
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
            <a href="/admin/orders" class="modal-close">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </a>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
            <?php if ($orderData): ?>
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
                                <select name="paymentMethod" class="form-select" <?= $modalState['mode'] === 'view' ? 'disabled' : '' ?>
                                >
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
                            <button type="button" class="btn btn-primary btn-sm" data-action="show-add-item-modal">
                                Add Item
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
                                               data-item-id="<?= $item['id'] ?>">
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
                                    <button type="button" class="text-red-500 hover:text-red-700 text-xs"
                                                    data-action="remove-item-from-order" data-item-id="<?= $item['id'] ?>">
                                                Remove
                                            </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="order-total">
                            <strong>Total: $<span id="orderTotal"><?= number_format($orderData['total'] ?? 0, 2) ?></span></strong>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-header">
                            <h3 class="form-section-title">Shipping Address</h3>
                            <?php if ($modalState['mode'] === 'edit'): ?>
                            <div class="address-actions">
                                <button type="button" class="btn btn-secondary btn-sm" data-action="show-address-selector">
                                    📍 Select Address
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" data-action="show-add-address-modal">
                                    ➕ Add New
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="address-display" id="currentAddress">
                            <?= nl2br(htmlspecialchars(formatAddress($orderData))) ?>
                        </div>
                        <?php if ($modalState['mode'] === 'edit'): ?>
                        <div class="form-group">
                            <button type="button" class="btn btn-secondary btn-sm" data-action="edit-current-address">
                                ✏️ Edit Current Address
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" data-action="impersonate-customer" data-user-id="<?= $orderData['userId'] ?>">
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
            <?php else: ?>
            <div class="p-4">
                <div class="text-red-600 font-semibold text-lg">Order not found</div>
                <div class="text-gray-600 text-sm mt-1">The requested order (ID: <?= htmlspecialchars($orderId ?? 'N/A') ?>) could not be located.</div>
                <div class="mt-3">
                    <a href="/admin/orders" class="btn btn-secondary">Back to Orders</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Modal Footer -->
        <?php if ($orderData && $modalState['mode'] === 'edit'): ?>
        <div class="modal-footer">
            <a href="?page=admin&section=orders" class="btn btn-secondary">
                Cancel
            </a>
            <button type="submit" form="orderForm" class="btn btn-primary">
                Save Changes
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay delete-confirmation-modal hidden">
    <div class="modal-content compact-modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Confirm Delete</h3>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this order? This action cannot be undone.</p>
            <p class="text-sm text-gray-600">Order ID: <span id="deleteOrderId" class="font-mono"></span></p>
        </div>
        <div class="modal-footer">
            <button type="button" data-action="close-delete-modal" class="btn btn-secondary">Cancel</button>
            <button type="button" data-action="delete-order" class="btn btn-danger">Delete Order</button>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div id="receiptModal" class="modal-overlay hidden">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3>Order Receipt</h3>
            <button data-action="close-receipt-modal">×</button>
        </div>
        <div class="modal-body">
            <div id="receiptContent"></div>
        </div>
        <div class="modal-footer">
            <button data-action="print-receipt" class="btn btn-primary">Print</button>
            <button data-action="close-receipt-modal" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($message): ?>
<div class="toast-notification <?= $messageType === 'success' ? 'toast-success' : 'toast-error' ?>" id="toast">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<script type="application/json" id="order-page-data">
    <?php
        echo json_encode([
            'orderData' => $orderData ?? null,
            'allItems' => $allItems->fetchAll(), // Database class sets default fetch mode
            'modalMode' => $modalState['mode'],
            'currentOrderId' => $orderId ?? null
        ]);
?>
</script>

<?php // Admin orders script is loaded via app.js per-page imports?>

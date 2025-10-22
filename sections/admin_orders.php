<?php
// sections/admin_orders.php — Primary implementation for Orders section

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/components/admin_order_editor.php';

// Detect partial requests (skip header/footer and only output requested fragment)
if (isset($_GET['wf_partial'])) {
    define('WF_PARTIAL_REQUEST', true);
}


// Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
if (!defined('WF_LAYOUT_BOOTSTRAPPED') && !defined('WF_PARTIAL_REQUEST')) {
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

// Sorting (whitelisted)
$sortBy = isset($_GET['sort']) ? strtolower((string)$_GET['sort']) : 'date';
$sortDir = isset($_GET['dir']) ? strtolower((string)$_GET['dir']) : 'desc';
$validDir = ($sortDir === 'asc') ? 'ASC' : 'DESC';
$sortMap = [
    'id' => 'o.id',
    'customer' => 'u.username',
    'date' => 'o.date',
    'status' => 'o.order_status',
    'payment' => 'o.paymentMethod',
    'shipping' => 'o.shippingMethod',
    'paystatus' => 'o.paymentStatus',
    'total' => 'o.total',
    'items' => 'COALESCE(oc.items_count,0)',
];
$orderColumn = $sortMap[$sortBy] ?? 'o.date';
$orderClause = $orderColumn . ' ' . $validDir . ', o.date DESC, o.id DESC';

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
    $stmt = $db->prepare("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode,
                          COALESCE(oc.items_count, 0) AS items_count
                          FROM orders o 
                          JOIN users u ON o.userId = u.id 
                          LEFT JOIN (
                            SELECT orderId, SUM(quantity) AS items_count
                            FROM order_items
                            GROUP BY orderId
                          ) oc ON oc.orderId = o.id
                          {$whereClause} 
                          ORDER BY {$orderClause}");
    $stmt->execute($params);
    $orders = $stmt->fetchAll(); // Database class sets default fetch mode
} else {
    $orders = $db->query("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode,
                          COALESCE(oc.items_count, 0) AS items_count
                          FROM orders o 
                          JOIN users u ON o.userId = u.id 
                          LEFT JOIN (
                            SELECT orderId, SUM(quantity) AS items_count
                            FROM order_items
                            GROUP BY orderId
                          ) oc ON oc.orderId = o.id
                          ORDER BY {$orderClause}")->fetchAll(); // Database class sets default fetch mode
}

// Get filter dropdown options with single queries
$dropdownOptions = [
    'status' => $db->query("SELECT DISTINCT order_status FROM orders WHERE order_status IN ('Pending','Processing','Shipped','Delivered','Cancelled') ORDER BY order_status")->fetchAll(\PDO::FETCH_COLUMN),
    'payment_method' => $db->query("SELECT DISTINCT paymentMethod FROM orders WHERE paymentMethod IS NOT NULL AND paymentMethod != '' ORDER BY paymentMethod")->fetchAll(\PDO::FETCH_COLUMN),
    'shipping_method' => $db->query("SELECT DISTINCT shippingMethod FROM orders WHERE shippingMethod IS NOT NULL AND shippingMethod != '' ORDER BY shippingMethod")->fetchAll(\PDO::FETCH_COLUMN),
    'payment_status' => $db->query("SELECT DISTINCT paymentStatus FROM orders WHERE paymentStatus IS NOT NULL AND paymentStatus != '' ORDER BY paymentStatus")->fetchAll(\PDO::FETCH_COLUMN)
];

// Modal state management
$sanitizeKey = static function($v){
    $raw = (string)($v ?? '');
    if (($p = stripos($raw, 'debug_nav')) !== false) { $raw = substr($raw, 0, $p); }
    return trim($raw);
};
$modalState = [
    'mode' => '',
    'view_id' => $sanitizeKey($_GET['view'] ?? ''),
    'edit_id' => $sanitizeKey($_GET['edit'] ?? '')
];

if ($modalState['view_id']) {
    $modalState['mode'] = 'view';
}
if ($modalState['edit_id']) {
    $modalState['mode'] = 'edit';
}

// Handle lightweight partial for order modal to speed up inline opens
if (defined('WF_PARTIAL_REQUEST') && ($_GET['wf_partial'] ?? '') === 'order_modal') {
    $orderId = $modalState['view_id'] ?: $modalState['edit_id'];
    if ($orderId) {
        $stmt = $db->prepare("SELECT o.*, u.username, u.email, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode FROM orders o LEFT JOIN users u ON o.userId = u.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $orderData = $stmt->fetch();
        // Compute prev/next using the same dataset and order as the table ($orders)
        $prevOrderId = null;
        $nextOrderId = null;
        $rawIds = array_map(static fn($o) => (string)($o['id'] ?? ''), $orders ?? []);
        $norm = static function ($v) { return strtolower(trim((string)$v)); };
        $idListNorm = array_map($norm, $rawIds);
        $idx = array_search($norm($orderId), $idListNorm, true);
        $n = count($rawIds);
        if ($idx !== false && $n > 0) {
            $prevOrderId = $rawIds[(($idx - 1 + $n) % $n)];
            $nextOrderId = $rawIds[(($idx + 1) % $n)];
        }
        // debug output removed
        // Output only the modal HTML fragment
        ?>
<div class="admin-modal-overlay topmost over-header order-modal show" id="orderModal" data-action="close-order-editor-on-overlay">
    <?php 
        $linkBase = $_GET; unset($linkBase['view'], $linkBase['edit']);
        $prevTarget = $prevOrderId ?: $orderId;
        $nextTarget = $nextOrderId ?: $orderId;
        $prevHref = '/admin/orders?' . http_build_query(array_merge($linkBase, [ $modalState['mode'] => $prevTarget ]));
        $nextHref = '/admin/orders?' . http_build_query(array_merge($linkBase, [ $modalState['mode'] => $nextTarget ]));
    ?>
    <a href="<?= htmlspecialchars($prevHref) ?>" 
       class="nav-arrow nav-arrow-left wf-nav-arrow wf-nav-left">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <a href="<?= htmlspecialchars($nextHref) ?>" 
       class="nav-arrow nav-arrow-right wf-nav-arrow wf-nav-right">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
    <div class="admin-modal admin-modal-content admin-modal--order-editor wf-admin-panel-visible show">
        <div class="modal-header">
            <h2 class="modal-title">
                <?= $modalState['mode'] === 'view' ? 'View' : 'Edit' ?> Order: <?= htmlspecialchars($orderId) ?>
            </h2>
            <?php if ($modalState['mode'] === 'view' && $orderId): ?>
            <a href="/admin/orders?edit=<?= htmlspecialchars($orderId) ?>" class="btn btn-primary btn-sm modal-action-edit" title="Edit Order">
                Edit
            </a>
            <?php elseif ($orderData && $modalState['mode'] === 'edit'): ?>
            <button type="submit" form="orderForm" class="btn btn-primary btn-sm" data-action="save-order">Save</button>
            <?php endif; ?>
        </div>
        <div class="modal-body">
            <?php if ($orderData): ?>
            <form id="orderForm" class="order-form-grid">
                <div class="order-details-column">
                    <div class="modal-section">
                        <h3 class="form-section-title">Order Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Order ID</label>
                                <input type="text" value="<?= htmlspecialchars($orderId) ?>" class="form-input" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Date</label>
                                <input type="datetime-local" name="date" 
                                       value="<?= date('Y-m-d\\TH:i', strtotime($orderData['date'])) ?>" 
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
                    <div class="modal-section">
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
                </div>
            </form>
            <?php else: ?>
                <div class="p-4">Order not found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
        <?php
    }
    // Terminate response for partial
    exit;
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

// Helpers for sortable header links
function ordSortUrl($column, $currentSort, $currentDir)
{
    $newDir = ($column === $currentSort && $currentDir === 'asc') ? 'desc' : 'asc';
    $queryParams = $_GET;
    $queryParams['sort'] = $column;
    $queryParams['dir'] = $newDir;
    // Remove modal params on sort
    unset($queryParams['view'], $queryParams['edit']);
    return '/admin/orders?' . http_build_query($queryParams);
}
function ordSortIndicator($column, $currentSort, $currentDir)
{
    if ($column !== $currentSort) return '';
    return $currentDir === 'asc' ? '↑' : '↓';
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
                    <th>
                        <a href="<?= ordSortUrl('id', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='id' ? ' is-active' : '' ?>">Order ID <?= ordSortIndicator('id', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= ordSortUrl('customer', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='customer' ? ' is-active' : '' ?>">Customer <?= ordSortIndicator('customer', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= ordSortUrl('date', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='date' ? ' is-active' : '' ?>">Date <?= ordSortIndicator('date', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= ordSortUrl('date', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='date' ? ' is-active' : '' ?>">Time <?= ordSortIndicator('date', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= ordSortUrl('items', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='items' ? ' is-active' : '' ?>">Items <?= ordSortIndicator('items', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= ordSortUrl('status', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='status' ? ' is-active' : '' ?>">Order Status <?= ordSortIndicator('status', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= ordSortUrl('payment', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='payment' ? ' is-active' : '' ?>">Payment <?= ordSortIndicator('payment', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= ordSortUrl('shipping', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='shipping' ? ' is-active' : '' ?>">Shipping <?= ordSortIndicator('shipping', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= ordSortUrl('paystatus', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='paystatus' ? ' is-active' : '' ?>">Pay Status <?= ordSortIndicator('paystatus', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= ordSortUrl('total', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='total' ? ' is-active' : '' ?>">Total <?= ordSortIndicator('total', $sortBy, $sortDir) ?></a>
                    </th>
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
                    <td class="editable-field" data-order-id="<?= $order['id'] ?>" data-field="date" data-type="date" data-raw-value="<?= htmlspecialchars(date('Y-m-d', strtotime($order['date'] ?? 'now'))) ?>">
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

        $rawIds = array_map(static fn($o) => (string)($o['id'] ?? ''), $orders ?? []);
        $norm = static function ($v) { return strtolower(trim((string)$v)); };
        $idListNorm = array_map($norm, $rawIds);
        $currentIndex = array_search($norm($orderId), $idListNorm, true);
        $n = count($rawIds);
        if ($currentIndex !== false && $n > 0) {
            $prevOrderId = $rawIds[(($currentIndex - 1 + $n) % $n)];
            $nextOrderId = $rawIds[(($currentIndex + 1) % $n)];
        }
        // debug output removed
    }
    ?>

<div class="admin-modal-overlay topmost over-header order-modal show" id="orderModal" data-action="close-order-editor-on-overlay">
    <!-- Navigation Arrows -->
    <?php $linkBase = $_GET; unset($linkBase['view'], $linkBase['edit']);
          $prevTarget = $prevOrderId ?: $orderId;
          $nextTarget = $nextOrderId ?: $orderId;
          $prevHref = '/admin/orders?' . http_build_query(array_merge($linkBase, [ $modalState['mode'] => $prevTarget ]));
          $nextHref = '/admin/orders?' . http_build_query(array_merge($linkBase, [ $modalState['mode'] => $nextTarget ])); ?>
    <a href="<?= htmlspecialchars($prevHref) ?>" 
       class="nav-arrow nav-arrow-left wf-nav-arrow wf-nav-left">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    
    <a href="<?= htmlspecialchars($nextHref) ?>" 
       class="nav-arrow nav-arrow-right wf-nav-arrow wf-nav-right">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>

    <div class="admin-modal admin-modal-content admin-modal--order-editor wf-admin-panel-visible show">
        <!-- Modal Header -->
        <div class="modal-header">
            <h2 class="modal-title">
                <?= $modalState['mode'] === 'view' ? 'View' : 'Edit' ?> Order: <?= htmlspecialchars($orderId) ?>
            </h2>
            <?php if ($modalState['mode'] === 'view' && $orderId): ?>
            <a href="/admin/orders?edit=<?= htmlspecialchars($orderId) ?>" class="btn btn-primary btn-sm modal-action-edit" title="Edit Order">
                Edit
            </a>
            <?php elseif ($orderData && $modalState['mode'] === 'edit'): ?>
            <button type="submit" form="orderForm" class="btn btn-primary btn-sm" data-action="save-order">Save</button>
            <?php endif; ?>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
            <?php if ($orderData): ?>
            <form id="orderForm" class="order-form-grid">
                <!-- Order Details Column -->
                <div class="order-details-column">
                    <div class="modal-section">
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

                    <div class="modal-section">
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

                    <div class="modal-section">
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
                    <div class="modal-section">
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

                    <div class="modal-section">
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

                    <div class="modal-section">
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

        <!-- Modal Footer intentionally empty for edit mode (no Cancel) -->
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

<!-- Receipt Modal (tokenized sizing) -->
<div id="receiptModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="receiptTitle">
    <div class="admin-modal admin-modal--receipt">
        <div class="modal-header">
            <h3 id="receiptTitle">Order Receipt</h3>
            <button type="button" class="admin-modal-close" data-action="close-receipt-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body" id="receiptContent"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-action="print-receipt">Print</button>
            <button type="button" class="btn" data-action="close-receipt-modal">Close</button>
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

<?php // Admin orders script is loaded via app.js per-page imports ?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';
try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }

// Get filter parameters
$filterDate = $_GET['filter_date'] ?? '';
$filterItems = $_GET['filter_items'] ?? '';
$filterStatus = $_GET['filter_status'] ?? '';
$filterPaymentMethod = $_GET['filter_payment_method'] ?? '';
$filterShippingMethod = $_GET['filter_shipping_method'] ?? '';
$filterPaymentStatus = $_GET['filter_payment_status'] ?? '';

// Build the WHERE clause based on filters
$whereConditions = [];
$params = [];

// Default to Processing status if no status filter is provided, but allow "All" to show everything
if (!isset($_GET['filter_status'])) {
    // No filter parameter provided at all (first page load), default to Processing
    $defaultStatus = 'Processing';
} else {
    // Filter parameter exists - could be empty string for "All" or specific status
    $defaultStatus = $filterStatus;
}

if (!empty($filterDate)) {
    $whereConditions[] = "DATE(o.date) = ?";
    $params[] = $filterDate;
}

// Apply status filter (defaults to Processing if not specified, but allows "All" to show everything)
if (!empty($defaultStatus)) {
    $whereConditions[] = "o.status = ?";
    $params[] = $defaultStatus;
}
// Note: If $defaultStatus is empty (user selected "All"), no status filter is applied

if (!empty($filterPaymentMethod)) {
    $whereConditions[] = "o.paymentMethod = ?";
    $params[] = $filterPaymentMethod;
}

if (!empty($filterShippingMethod)) {
    $whereConditions[] = "o.shippingMethod = ?";
    $params[] = $filterShippingMethod;
}

if (!empty($filterPaymentStatus)) {
    $whereConditions[] = "o.paymentStatus = ?";
    $params[] = $filterPaymentStatus;
}

// Handle items filter - this requires a subquery since items are in order_items table
if (!empty($filterItems)) {
    $whereConditions[] = "EXISTS (SELECT 1 FROM order_items oi LEFT JOIN items i ON oi.sku = i.sku WHERE oi.orderId = o.id AND (COALESCE(i.name, oi.sku) LIKE ? OR oi.sku LIKE ?))";
    $params[] = "%{$filterItems}%";
    $params[] = "%{$filterItems}%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$stmt = $pdo->prepare("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode FROM orders o JOIN users u ON o.userId = u.id {$whereClause} ORDER BY o.date DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique values for filter dropdowns
$statusOptions = $pdo->query("SELECT DISTINCT status FROM orders WHERE status IN ('Pending','Processing','Shipped','Delivered','Cancelled') ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
$paymentMethodOptions = $pdo->query("SELECT DISTINCT paymentMethod FROM orders WHERE paymentMethod IS NOT NULL AND paymentMethod != '' ORDER BY paymentMethod")->fetchAll(PDO::FETCH_COLUMN);
$shippingMethodOptions = $pdo->query("SELECT DISTINCT shippingMethod FROM orders WHERE shippingMethod IS NOT NULL AND shippingMethod != '' ORDER BY shippingMethod")->fetchAll(PDO::FETCH_COLUMN);
$paymentStatusOptions = $pdo->query("SELECT DISTINCT paymentStatus FROM orders WHERE paymentStatus IS NOT NULL AND paymentStatus != '' ORDER BY paymentStatus")->fetchAll(PDO::FETCH_COLUMN);

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

// Helper functions for status badges
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
?>

<div class="admin-content-container">
    <div class="admin-filter-section">
        <form method="GET" class="admin-filter-form">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="order_fulfillment">
            
            <input type="date" name="filter_date" id="filter_date" 
                   value="<?= htmlspecialchars($filterDate) ?>" class="admin-form-input">
            
            <input type="text" name="filter_items" id="filter_items" 
                   value="<?= htmlspecialchars($filterItems) ?>" 
                   placeholder="Search items..." class="admin-form-input">
            
            <select name="filter_status" id="filter_status" class="admin-form-select">
                <option value="">All Status</option>
                <?php foreach ($statusOptions as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" <?= $defaultStatus === $status ? 'selected' : '' ?>>
                    <?= htmlspecialchars($status) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="filter_payment_method" id="filter_payment_method" class="admin-form-select">
                <option value="">All Payment</option>
                <?php foreach ($paymentMethodOptions as $method): ?>
                <option value="<?= htmlspecialchars($method) ?>" <?= $filterPaymentMethod === $method ? 'selected' : '' ?>>
                    <?= htmlspecialchars($method) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="filter_shipping_method" id="filter_shipping_method" class="admin-form-select">
                <option value="">All Shipping</option>
                <?php foreach ($shippingMethodOptions as $method): ?>
                <option value="<?= htmlspecialchars($method) ?>" <?= $filterShippingMethod === $method ? 'selected' : '' ?>>
                    <?= htmlspecialchars($method) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="filter_payment_status" id="filter_payment_status" class="admin-form-select">
                <option value="">All Pay Status</option>
                <?php foreach ($paymentStatusOptions as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" <?= $filterPaymentStatus === $status ? 'selected' : '' ?>>
                    <?= htmlspecialchars($status) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <a href="/?page=admin&section=order_fulfillment" class="admin-filter-clear">Clear All</a>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="admin-message <?= $messageType === 'success' ? 'admin-message-success' : 'admin-message-error'; ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="admin-table-section">
        <?php if (empty($orders)): ?>
            <div class="admin-empty-state">
                <div class="empty-icon">📋</div>
                <div class="empty-title">No orders found</div>
                <div class="empty-subtitle">Try adjusting your filters to see more orders.</div>
            </div>
        <?php else: ?>
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment Method</th>
                        <th>Shipping Method</th>
                        <th>Payment Status</th>
                        <th>Payment Date</th>
                        <th>Shipping Address</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="font-medium text-gray-900">#<?= htmlspecialchars($order['id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($order['username'] ?? 'N/A') ?></td>
                        <td class="text-sm text-gray-600"><?= htmlspecialchars(date('M j, Y', strtotime($order['date'] ?? 'now'))) ?></td>
                        <td class="text-center">
                            <?php
                            $items = $pdo->prepare("SELECT SUM(quantity) as total_items FROM order_items WHERE orderId = ?");
                            $items->execute([$order['id']]);
                            $totalItems = $items->fetchColumn();
                            echo $totalItems ?: '0';
                            ?>
                        </td>
                        <td class="font-semibold">$<?= number_format(floatval($order['total'] ?? 0), 2) ?></td>
                        <td>
                            <select class="admin-form-select-sm order-field-update" data-field="status" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                <option value="Pending" <?= strtolower($order['status'] ?? 'Pending') === strtolower('Pending') ? 'selected' : '' ?>>Pending</option>
                                <option value="Processing" <?= strtolower($order['status'] ?? '') === strtolower('Processing') ? 'selected' : '' ?>>Processing</option>
                                <option value="Shipped" <?= strtolower($order['status'] ?? '') === strtolower('Shipped') ? 'selected' : '' ?>>Shipped</option>
                                <option value="Delivered" <?= strtolower($order['status'] ?? '') === strtolower('Delivered') ? 'selected' : '' ?>>Delivered</option>
                                <option value="Cancelled" <?= strtolower($order['status'] ?? '') === strtolower('Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </td>
                        <td>
                            <select class="admin-form-select-sm order-field-update" data-field="paymentMethod" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                <option value="Credit Card" <?= strtolower($order['paymentMethod'] ?? 'Credit Card') === strtolower('Credit Card') ? 'selected' : '' ?>>Credit Card</option>
                                <option value="Cash" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Cash') ? 'selected' : '' ?>>Cash</option>
                                <option value="Check" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Check') ? 'selected' : '' ?>>Check</option>
                                <option value="PayPal" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('PayPal') ? 'selected' : '' ?>>PayPal</option>
                                <option value="Venmo" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Venmo') ? 'selected' : '' ?>>Venmo</option>
                                <option value="Other" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </td>
                        <td>
                            <select class="admin-form-select-sm order-field-update" data-field="shippingMethod" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                <option value="Customer Pickup" <?= strtolower($order['shippingMethod'] ?? 'Customer Pickup') === strtolower('Customer Pickup') ? 'selected' : '' ?>>Customer Pickup</option>
                                <option value="Local Delivery" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('Local Delivery') ? 'selected' : '' ?>>Local Delivery</option>
                                <option value="USPS" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('USPS') ? 'selected' : '' ?>>USPS</option>
                                <option value="FedEx" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('FedEx') ? 'selected' : '' ?>>FedEx</option>
                                <option value="UPS" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('UPS') ? 'selected' : '' ?>>UPS</option>
                            </select>
                        </td>
                        <td>
                            <select class="admin-form-select-sm order-field-update" data-field="paymentStatus" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                <option value="Pending" <?= strtolower($order['paymentStatus'] ?? 'Pending') === strtolower('Pending') ? 'selected' : '' ?>>Pending</option>
                                <option value="Received" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Received') ? 'selected' : '' ?>>Received</option>
                                <option value="Refunded" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Refunded') ? 'selected' : '' ?>>Refunded</option>
                                <option value="Failed" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Failed') ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </td>
                        <td class="text-sm text-gray-600">
                            <?= !empty($order['paymentDate']) ? htmlspecialchars(date('M j, Y', strtotime($order['paymentDate']))) : '-' ?>
                        </td>
                        <td class="text-sm">
                            <?= formatAddress($order) ?>
                        </td>
                        <td class="text-sm">
                            <?php
                            $notesBlock = [];
                            if (!empty($order['fulfillmentNotes'])) {
                                $notesBlock[] = '<span class="font-semibold">Fulfillment:</span> ' . htmlspecialchars($order['fulfillmentNotes'] ?? '');
                            }
                            if (!empty($order['paymentNotes'])) {
                                $notesBlock[] = '<span class="font-semibold">Payment:</span> ' . htmlspecialchars($order['paymentNotes'] ?? '');
                            }
                            echo !empty($notesBlock) ? implode('<br>', $notesBlock) : '-';
                            ?>
                        </td>
                        <td>
                            <div class="flex space-x-2">
                                <a href="/?page=admin&section=orders&view=<?= urlencode($order['id']) ?>" 
                                   class="text-blue-600 hover:text-blue-800" title="View Order">👁️</a>
                                <a href="/?page=admin&section=orders&edit=<?= urlencode($order['id']) ?>" 
                                   class="text-green-600 hover:text-green-800" title="Edit Order">✏️</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-submit form when any filter changes
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.querySelector('.admin-filter-form');
    const filterInputs = filterForm.querySelectorAll('input, select');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            filterForm.submit();
        });
        
        // For text inputs, also submit after a short delay when typing stops
        if (input.type === 'text') {
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    filterForm.submit();
                }, 1000); // 1 second delay after typing stops
            });
        }
    });
});

// Handle dropdown field updates
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.order-field-update').forEach(select => {
        select.addEventListener('change', async function() {
            const orderId = this.dataset.orderId;
            const field = this.dataset.field;
            const value = this.value;
            
            // Show loading state
            const originalStyle = this.style.backgroundColor;
            this.style.backgroundColor = '#fef3c7'; // yellow loading color
            this.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('orderId', orderId);
                formData.append('field', field);
                formData.append('value', value);
                formData.append('action', 'updateField');
                
                const response = await fetch('/api/fulfill_order.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Success state
                    this.style.backgroundColor = '#dcfce7'; // green success color
                    setTimeout(() => {
                        this.style.backgroundColor = originalStyle;
                    }, 2000);
                } else {
                    // Error state
                    this.style.backgroundColor = '#fecaca'; // red error color
                    if (typeof showError === 'function') {
                        showError('Error updating field: ' + (result.error || 'Unknown error'));
                    } else {
                        console.error('Error updating field:', result.error || 'Unknown error');
                    }
                    setTimeout(() => {
                        this.style.backgroundColor = originalStyle;
                    }, 2000);
                }
            } catch (error) {
                // Network error
                this.style.backgroundColor = '#fecaca'; // red error color
                console.error('Error updating field:', error);
                if (typeof showError === 'function') {
                    showError('Network error updating field');
                } else {
                    console.error('Network error updating field');
                }
                setTimeout(() => {
                    this.style.backgroundColor = originalStyle;
                }, 2000);
            } finally {
                this.disabled = false;
            }
        });
    });
});
</script> 
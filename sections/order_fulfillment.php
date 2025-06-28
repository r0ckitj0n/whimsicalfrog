<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';
$pdo = new PDO($dsn, $user, $pass, $options);

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
?>

<style>
    /* Force the fulfillment title to be green with highest specificity */
    h1.fulfillment-title.text-2xl.font-bold {
        color: var(--admin-page-title-color, #87ac3a) !important;
        font-size: var(--admin-page-title-font-size, 1.5rem) !important;
        font-weight: var(--admin-page-title-font-weight, bold) !important;
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
            .toast-notification.success { background-color: #87ac3a; } /* Brand green */
    .toast-notification.error { background-color: #f56565; } /* Tailwind red-500 */
    .toast-notification.info { background-color: #4299e1; } /* Tailwind blue-500 */

    .fulfillment-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
    .fulfillment-table th { background-color: #87ac3a; color: white; padding: 10px 12px; text-align: left; font-weight: 600; font-size: 0.8rem; position: sticky; top: 0; z-index: 10; }
    .fulfillment-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 0.85rem; }
    .fulfillment-table tr:hover { background-color: #f7fafc; }
    .fulfillment-table th:first-child { border-top-left-radius: 6px; }
    .fulfillment-table th:last-child { border-top-right-radius: 6px; }

    .action-btn { padding: 5px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; border: none; }
    .view-btn { background-color: #4299e1; color: white; } .view-btn:hover { background-color: #3182ce; }
    .edit-btn { background-color: #f59e0b; color: white; } .edit-btn:hover { background-color: #d97706; }
    .delete-btn { background-color: #f56565; color: white; } .delete-btn:hover { background-color: #e53e3e; }

    .order-field-select { 
        border: 1px solid #d1d5db; 
        border-radius: 4px; 
        padding: 4px 8px; 
        font-size: 0.75rem; 
        min-width: 100px;
        background-color: white;
        transition: all 0.2s;
    }
    .order-field-select:focus { 
        border-color: #87ac3a; 
        outline: none; 
        box-shadow: 0 0 0 1px #87ac3a; 
    }
    .order-field-select:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .status-badge {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        text-align: center;
    }
    .status-pending { background-color: #fef3c7; color: #92400e; }
    .status-processing { background-color: #dbeafe; color: #1e40af; }
    .status-shipped { background-color: #dcfce7; color: #166534; }
    .status-delivered { background-color: #d1fae5; color: #065f46; }
    .status-cancelled { background-color: #fee2e2; color: #991b1b; }

    .order-items-cell {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .order-items-cell:hover {
        white-space: normal;
        overflow: visible;
    }

    .address-cell {
        max-width: 150px;
        font-size: 0.75rem;
        line-height: 1.2;
    }

    .notes-cell {
        max-width: 120px;
        font-size: 0.75rem;
        line-height: 1.2;
    }

    /* Filter styling to match orders page */
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem;
        background-color: #f8fafc;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    .filter-form label {
        color: var(--filter-label-color, #87ac3a) !important;
        font-weight: var(--filter-label-font-weight, 500) !important;
        font-size: var(--filter-label-font-size, 0.875rem) !important;
    }

    .filter-form input,
    .filter-form select {
        border: 1px solid #d1d5db;
        border-radius: 0.25rem;
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        background-color: white;
    }

    .filter-form input:focus,
    .filter-form select:focus {
        outline: none;
        border-color: #87ac3a;
        box-shadow: 0 0 0 2px rgba(135, 172, 58, 0.2);
    }

    .filter-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-clear-link {
        font-size: 0.875rem;
        color: var(--filter_label_color, #87ac3a);
        font-weight: var(--filter_label_font_weight, 500);
        text-decoration: underline;
    }

    .filter-apply-btn {
        padding: 0.25rem 0.75rem;
        border-radius: 0.25rem;
        background-color: #87ac3a;
        color: white;
        transition: background-color 0.2s;
        border: none;
        font-size: 0.875rem;
        cursor: pointer;
    }

    .filter-apply-btn:hover {
        background-color: #a3cc4a;
    }

         .fulfillment-section-header {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    @media (min-width: 768px) {
        .fulfillment-section-header {
            flex-direction: row;
            align-items: flex-start;
            justify-content: space-between;
        }
    }

    .title-and-button-stack {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
</style>

<div class="container mx-auto px-4 py-2">
    <div class="fulfillment-section-header">
        
        
        <form method="GET" class="filter-form">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="order_fulfillment">
            
            <label for="filter_date">Date:</label>
            <input type="date" name="filter_date" id="filter_date" value="<?= htmlspecialchars($filterDate) ?>">
            
            <label for="filter_items">Items:</label>
            <input type="text" name="filter_items" id="filter_items" value="<?= htmlspecialchars($filterItems) ?>" placeholder="Search...">
            
            <label for="filter_status">Status:</label>
            <select name="filter_status" id="filter_status">
                <option value="">All</option>
                <?php foreach ($statusOptions as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" <?= $defaultStatus === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="filter_payment_method">Payment:</label>
            <select name="filter_payment_method" id="filter_payment_method">
                <option value="">All</option>
                <?php foreach ($paymentMethodOptions as $method): ?>
                <option value="<?= htmlspecialchars($method) ?>" <?= $filterPaymentMethod === $method ? 'selected' : '' ?>><?= htmlspecialchars($method) ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="filter_shipping_method">Shipping:</label>
            <select name="filter_shipping_method" id="filter_shipping_method">
                <option value="">All</option>
                <?php foreach ($shippingMethodOptions as $method): ?>
                <option value="<?= htmlspecialchars($method) ?>" <?= $filterShippingMethod === $method ? 'selected' : '' ?>><?= htmlspecialchars($method) ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="filter_payment_status">Pay Status:</label>
            <select name="filter_payment_status" id="filter_payment_status">
                <option value="">All</option>
                <?php foreach ($paymentStatusOptions as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" <?= $filterPaymentStatus === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                <?php endforeach; ?>
            </select>
            
            <a href="/?page=admin&section=order_fulfillment" class="text-xs text-gray-500 hover:text-gray-700 underline ml-2">Clear All</a>
        </form>
    </div>

    <script>
    // Auto-submit form when any filter changes
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.querySelector('.filter-form');
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
    </script>
    
    <?php if ($message): ?>
        <div class="mb-4 p-3 rounded text-white <?= $messageType === 'success' ? 'bg-green-500' : 'bg-red-500'; ?>">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <?php if (empty($orders)): ?>
            <div class="text-center text-gray-500 py-12">
                <div class="text-4xl mb-4">📋</div>
                <div class="text-lg font-medium mb-2">No orders found</div>
                <div class="text-sm">Try adjusting your filters to see more orders.</div>
            </div>
        <?php else: ?>
            <table class="fulfillment-table">
                <thead>
                    <tr>
                        <th>Order ID</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Payment Method</th><th>Shipping Method</th><th>Payment Status</th><th>Payment Date</th><th>Shipping Address</th><th>Notes</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr class="hover:bg-gray-50">
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
                            <select class="order-field-select order-field-update" data-field="status" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                <option value="Pending" <?= strtolower($order['status'] ?? 'Pending') === strtolower('Pending') ? 'selected' : '' ?>>Pending</option>
                                <option value="Processing" <?= strtolower($order['status'] ?? '') === strtolower('Processing') ? 'selected' : '' ?>>Processing</option>
                                <option value="Shipped" <?= strtolower($order['status'] ?? '') === strtolower('Shipped') ? 'selected' : '' ?>>Shipped</option>
                                <option value="Delivered" <?= strtolower($order['status'] ?? '') === strtolower('Delivered') ? 'selected' : '' ?>>Delivered</option>
                                <option value="Cancelled" <?= strtolower($order['status'] ?? '') === strtolower('Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </td>
                        <td>
                            <select class="order-field-select order-field-update" data-field="paymentMethod" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                <option value="Credit Card" <?= strtolower($order['paymentMethod'] ?? 'Credit Card') === strtolower('Credit Card') ? 'selected' : '' ?>>Credit Card</option>
                                <option value="Cash" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Cash') ? 'selected' : '' ?>>Cash</option>
                                <option value="Check" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Check') ? 'selected' : '' ?>>Check</option>
                                <option value="PayPal" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('PayPal') ? 'selected' : '' ?>>PayPal</option>
                                <option value="Venmo" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Venmo') ? 'selected' : '' ?>>Venmo</option>
                                <option value="Other" <?= strtolower($order['paymentMethod'] ?? '') === strtolower('Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </td>
                        <td>
                            <select class="order-field-select order-field-update" data-field="shippingMethod" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                <option value="Customer Pickup" <?= strtolower($order['shippingMethod'] ?? 'Customer Pickup') === strtolower('Customer Pickup') ? 'selected' : '' ?>>Customer Pickup</option>
                                <option value="Local Delivery" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('Local Delivery') ? 'selected' : '' ?>>Local Delivery</option>
                                <option value="USPS" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('USPS') ? 'selected' : '' ?>>USPS</option>
                                <option value="FedEx" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('FedEx') ? 'selected' : '' ?>>FedEx</option>
                                <option value="UPS" <?= strtolower($order['shippingMethod'] ?? '') === strtolower('UPS') ? 'selected' : '' ?>>UPS</option>
                            </select>
                        </td>
                        <td>
                            <select class="order-field-select order-field-update" data-field="paymentStatus" data-order-id="<?= htmlspecialchars($order['id'] ?? '') ?>">
                                <option value="Pending" <?= strtolower($order['paymentStatus'] ?? 'Pending') === strtolower('Pending') ? 'selected' : '' ?>>Pending</option>
                                <option value="Received" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Received') ? 'selected' : '' ?>>Received</option>
                                <option value="Refunded" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Refunded') ? 'selected' : '' ?>>Refunded</option>
                                <option value="Failed" <?= strtolower($order['paymentStatus'] ?? '') === strtolower('Failed') ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </td>
                        <td class="text-sm text-gray-600">
                            <?= !empty($order['paymentDate']) ? htmlspecialchars(date('M j, Y', strtotime($order['paymentDate']))) : '-' ?>
                        </td>
                        <td class="address-cell">
                            <?php
                            $customShip = trim($order['shippingAddress'] ?? '');
                            if ($customShip !== '') {
                                // Check if it's JSON data
                                $jsonData = json_decode($customShip, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                                    // It's JSON - format it properly
                                    $addrParts = array_filter([
                                        $jsonData['addressLine1'] ?? '',
                                        $jsonData['addressLine2'] ?? '',
                                        ($jsonData['city'] ?? '') . (isset($jsonData['state']) ? ', ' . $jsonData['state'] : ''),
                                        $jsonData['zipCode'] ?? ''
                                    ]);
                                    $fullAddress = !empty($addrParts) ? implode('<br>', array_map('htmlspecialchars', $addrParts)) : 'N/A';
                                    echo $fullAddress;
                                } else {
                                    // It's plain text - display as is
                                    echo nl2br(htmlspecialchars($customShip));
                                }
                            } else {
                                // Fall back to individual user address fields
                                $addrParts = array_filter([
                                    $order['addressLine1'] ?? '',
                                    $order['addressLine2'] ?? '',
                                    ($order['city'] ?? '') . (isset($order['state']) ? ', ' . $order['state'] : ''),
                                    $order['zipCode'] ?? ''
                                ]);
                                $fullAddress = !empty($addrParts) ? implode('<br>', array_map('htmlspecialchars', $addrParts)) : 'N/A';
                                echo $fullAddress;
                            }
                            ?>
                        </td>
                        <td class="notes-cell">
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
                            <div class="flex gap-1 items-center">
                                <a href="/?page=admin&section=orders&view=<?= urlencode($order['id']) ?>" class="action-btn view-btn" title="View Order">👁️</a>
                                <a href="/?page=admin&section=orders&edit=<?= urlencode($order['id']) ?>" class="action-btn edit-btn" title="Edit Order">✏️</a>
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
                    showError('Error updating field: ' + (result.error || 'Unknown error'));
                    setTimeout(() => {
                        this.style.backgroundColor = originalStyle;
                    }, 2000);
                }
            } catch (error) {
                // Network error
                this.style.backgroundColor = '#fecaca'; // red error color
                console.error('Error updating field:', error);
                showError('Network error updating field');
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
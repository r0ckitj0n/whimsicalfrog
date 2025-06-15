<?php
// Admin Orders Management Section
ob_start();

// Database connection
$pdo = new PDO($dsn, $user, $pass, $options);

$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date']   ?? '';

// Get orders within range
if ($startDate === '' && $endDate === '') {
    $ordersStmt = $pdo->prepare("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode \n                                  FROM orders o \n                                  JOIN users u ON o.userId = u.id \n                                  ORDER BY o.date DESC");
    $ordersStmt->execute();
} elseif ($startDate === '') {
    $ordersStmt = $pdo->prepare("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode \n                                  FROM orders o JOIN users u ON o.userId = u.id \n                                  WHERE DATE(o.date) <= :end ORDER BY o.date DESC");
    $ordersStmt->execute([':end'=>$endDate]);
} elseif ($endDate === '') {
    $ordersStmt = $pdo->prepare("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode \n                                  FROM orders o JOIN users u ON o.userId = u.id \n                                  WHERE DATE(o.date) >= :start ORDER BY o.date DESC");
    $ordersStmt->execute([':start'=>$startDate]);
} else {
    $ordersStmt = $pdo->prepare("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode \n                                  FROM orders o JOIN users u ON o.userId = u.id \n                                  WHERE DATE(o.date) BETWEEN :start AND :end ORDER BY o.date DESC");
    $ordersStmt->execute([':start'=>$startDate, ':end'=>$endDate]);
}
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize modal state
$modalMode = ''; // Default to no modal
$viewOrderId = '';
$editOrderId = '';

// Check if we're in view mode
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $viewOrderId = $_GET['view'];
    $modalMode = 'view';
}

// Check if we're in edit mode
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editOrderId = $_GET['edit'];
    $modalMode = 'edit';
}

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

$prodStmt = $pdo->query("SELECT id, name, basePrice FROM products ORDER BY name");
$allProducts = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Force the orders title to be green with highest specificity */
    h1.orders-title.text-2xl.font-bold {
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
    
    /* Payment status toggle buttons */
    .payment-toggle {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 4px;
    }
    
    .mark-paid {
        background-color: #87ac3a;
        color: white;
    }
    
    .mark-paid:hover {
        background-color: #6b8e23;
    }
    
    .mark-unpaid {
        background-color: #f59e0b;
        color: white;
    }
    
    .mark-unpaid:hover {
        background-color: #d97706;
    }
    
    /* Toast notification */
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

    /* Orders table */
    .orders-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
    .orders-table th { background-color: #87ac3a; color: white; padding: 10px 12px; text-align: left; font-weight: 600; font-size: 0.8rem; position: sticky; top: 0; z-index: 10; }
    .orders-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 0.85rem; }
    .orders-table tr:hover { background-color: #f7fafc; }
    .orders-table th:first-child { border-top-left-radius: 6px; }
    .orders-table th:last-child { border-top-right-radius: 6px; }

    /* Action buttons */
    .action-btn { padding: 5px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; border: none; }
    .view-btn { background-color: #4299e1; color: white; } .view-btn:hover { background-color: #3182ce; }
    .edit-btn { background-color: #f59e0b; color: white; } .edit-btn:hover { background-color: #d97706; }
    .delete-btn { background-color: #f56565; color: white; } .delete-btn:hover { background-color: #e53e3e; }
    
    /* Modal styles */
    .modal-outer { position: fixed; top: 70px; left: 0; right: 0; bottom: 0; height: calc(100vh - 70px); background-color: rgba(0,0,0,0.6); display: flex; align-items: flex-start; justify-content: center; z-index: 40; padding: 1rem; overflow-y: auto; }
    .modal-content-wrapper { background-color: white; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); padding: 1.5rem; width: 95%; max-width: 80rem; margin: auto; display: flex; flex-direction: column; min-height: min-content; }
    .modal-form-container { flex-grow: 1; display: flex; flex-direction: row; flex-wrap: nowrap; column-gap: 0.75rem; padding-right: 0.5rem; }
    
    /* Ensure body scrolling is preserved when modal is open */
    body { overflow: auto !important; }
    
    /* Grid layout for streamlined view/edit panels */
    .order-modal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
    
    /* Order details in modal */
    .order-details { margin-bottom: 1rem; }
    .order-details-section { margin-bottom: 0.75rem; padding: 0.75rem; border-radius: 0.5rem; background-color: #f9fafb; }
    .order-details-section h3 { margin-bottom: 0.5rem; color: #374151; font-size: 1.1rem; font-weight: 600; }
    .order-details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; }
    .order-detail-item { margin-bottom: 0.5rem; }
    .order-detail-label { font-weight: 500; color: #6b7280; font-size: 0.85rem; }
    .order-detail-value { font-weight: 600; color: #111827; }
    
    /* Delete confirmation modal */
    .delete-modal { position: fixed; inset: 0; background-color: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 60; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
    .delete-modal.show { opacity: 1; pointer-events: auto; }
    .delete-modal-content { background-color: white; border-radius: 0.5rem; padding: 1.25rem; width: 100%; max-width: 30rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); }
    
    /* Loading spinner */
    .loading-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    /* Status badges */
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .status-pending { background-color: #fef3c7; color: #92400e; }
    .status-processing { background-color: #e0f2fe; color: #0369a1; }
    .status-shipped { background-color: #d1fae5; color: #047857; }
    .status-delivered { background-color: #dcfce7; color: #166534; }
    .status-cancelled { background-color: #fee2e2; color: #b91c1c; }
    
    .payment-status-badge { /* Renamed for clarity */
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 9999px; /* Tailwind's rounded-full */
        font-size: 0.75rem; /* Tailwind's text-xs */
        font-weight: 500; /* Tailwind's font-medium */
    }
    .payment-status-pending { background-color: #fef3c7; color: #92400e; } /* Tailwind's yellow-100 bg, yellow-700 text */
    .payment-status-received { background-color: #dcfce7; color: #166534; } /* Tailwind's green-100 bg, green-700 text */
    /* Add more payment statuses as needed, e.g., Processing, Refunded, Failed */
    .payment-status-processing { background-color: #e0f2fe; color: #0369a1; } /* Example: blue */
    .payment-status-refunded { background-color: #e5e7eb; color: #4b5563; } /* Example: gray */
    .payment-status-failed { background-color: #fee2e2; color: #b91c1c; } /* Example: red */

    .hidden { display:none !important; }

    /* --- Added to match Inventory modal column layout --- */
    .modal-form-main-column { flex: 1; padding-right: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; }
    @media (max-width: 767px) { .modal-form-main-column { padding-right: 0; } }
    .modal-form-cost-column { width: 100%; padding-left: 0; margin-top: 1rem; }
    @media (min-width: 768px) { .modal-form-cost-column { flex: 0 0 30%; padding-left: 0.75rem; margin-top: 0; } }
    .order-notes-section h3 { color: #374151; font-size: 1rem; font-weight: 600; margin-bottom: 8px; }
    .order-notes-section textarea { font-size: 0.85rem; padding: 0.4rem 0.6rem; border: 1px solid #d1d5db; border-radius: 0.25rem; width: 100%; }
    .modal-form-items-column { width: 100%; margin-top: 1rem; }
    @media (min-width: 768px) { .modal-form-items-column { flex: 0 0 30%; padding-left: 0.75rem; margin-top: 0; } }
    
    /* Inline editing styles */
    .editable-field {
        position: relative;
        cursor: pointer;
        transition: background-color 0.2s;
        padding: 8px 12px;
        border-radius: 4px;
    }
    .editable-field:hover {
        background-color: #f0f9ff;
    }
    .editable-field.editing {
        background-color: #fff;
        cursor: default;
    }
    .editable-field select,
    .editable-field input[type="date"] {
        width: 100%;
        padding: 4px 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 0.85rem;
        background-color: white;
    }
    .editable-field select:focus,
    .editable-field input[type="date"]:focus {
        outline: none;
        border-color: #87ac3a;
        box-shadow: 0 0 0 2px rgba(135, 172, 58, 0.2);
    }
</style>

<div class="container mx-auto px-4 py-6">
    <div class="orders-section-header flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-5">
        <h1 class="orders-title text-2xl font-bold" style="color:#87ac3a !important;">Orders Management</h1>
        <form action="" method="GET" class="flex items-center gap-2">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="orders">
            <label for="start_date" class="text-sm font-medium" style="color:#87ac3a !important;">From:</label>
            <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($startDate); ?>" class="border rounded px-2 py-1 text-sm">
            <label for="end_date" class="text-sm font-medium" style="color:#87ac3a !important;">To:</label>
            <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($endDate); ?>" class="border rounded px-2 py-1 text-sm">
            <a href="?page=admin&section=orders" class="text-sm text-gray-600 underline">Clear</a>
            <button type="submit" class="px-3 py-1 rounded bg-[#87ac3a] text-white hover:bg-[#a3cc4a] transition">Apply</button>
        </form>
    </div>
    
    <?php if ($message): ?>
        <div class="mb-4 p-3 rounded text-white <?= $messageType === 'success' ? 'bg-green-500' : 'bg-red-500'; ?>">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment Method</th>
                    <th>Shipping Method</th>
                    <th>Payment Status</th>
                    <th>Payment Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="9" class="text-center py-4">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td style="font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars($order['id']) ?></td>
                        <td><?= htmlspecialchars($order['username']) ?></td>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($order['date']))) ?></td>
                        <td>$<?= number_format(floatval($order['total']), 2) ?></td>
                        <td class="editable-field" data-order-id="<?= htmlspecialchars($order['id']) ?>" data-field="status" data-type="select">
                            <span class="status-badge status-<?= strtolower(htmlspecialchars($order['status'])) ?>">
                                <?= htmlspecialchars($order['status']) ?>
                            </span>
                        </td>
                        <td class="editable-field" data-order-id="<?= htmlspecialchars($order['id']) ?>" data-field="paymentMethod" data-type="select"><?= htmlspecialchars($order['paymentMethod']) ?></td>
                        <td class="editable-field" data-order-id="<?= htmlspecialchars($order['id']) ?>" data-field="shippingMethod" data-type="select"><?= htmlspecialchars($order['shippingMethod'] ?? 'Standard') ?></td>
                        <td class="editable-field" data-order-id="<?= htmlspecialchars($order['id']) ?>" data-field="paymentStatus" data-type="select">
                            <span class="payment-status-badge payment-status-<?= strtolower(htmlspecialchars($order['paymentStatus'])) ?>">
                                <?= htmlspecialchars($order['paymentStatus']) ?>
                            </span>
                        </td>
                        <td class="editable-field" data-order-id="<?= htmlspecialchars($order['id']) ?>" data-field="paymentDate" data-type="date"><?= !empty($order['paymentDate']) ? htmlspecialchars(date('Y-m-d', strtotime($order['paymentDate']))) : '' ?></td>
                        <td>
                            <a href="?page=admin&section=orders&view=<?= htmlspecialchars($order['id']) ?>" class="action-btn view-btn" title="View Order">👁️</a>
                            <a href="?page=admin&section=orders&edit=<?= htmlspecialchars($order['id']) ?>" class="action-btn edit-btn" title="Edit Order">✏️</a>
                            <button class="action-btn delete-btn delete-order" data-id="<?= htmlspecialchars($order['id']) ?>" title="Delete Order">🗑️</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Order Modal -->
<?php if ($modalMode === 'view' && !empty($viewOrderId)): ?>
    <?php
    // Get order details
    $orderStmt = $pdo->prepare("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode FROM orders o JOIN users u ON o.userId = u.id WHERE o.id = ?");
    $orderStmt->execute([$viewOrderId]);
    $orderDetails = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($orderDetails):
    ?>
    <div class="modal-outer" id="viewOrderModal">
        <div class="modal-content-wrapper">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-bold text-green-700">Order Details: <?= htmlspecialchars($viewOrderId) ?></h2>
                <a href="?page=admin&section=orders" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</a>
            </div>
            
            <div class="modal-form-container">
                <div class="modal-form-main-column">
                    <div class="order-modal-grid">
                        <!-- Left column: summary info -->
                        <div class="order-details-section">
                            <h3>Order Information</h3>
                            <div class="order-details-grid">
                                <div class="order-detail-item">
                                    <div class="order-detail-label">Order ID</div>
                                    <div class="order-detail-value"><?= htmlspecialchars($orderDetails['id']) ?></div>
                                </div>
                                <div class="order-detail-item">
                                    <div class="order-detail-label">Date</div>
                                    <div class="order-detail-value"><?= htmlspecialchars(date('F j, Y', strtotime($orderDetails['date']))) ?></div>
                                </div>
                                <div class="order-detail-item">
                                    <div class="order-detail-label">Customer</div>
                                    <div class="order-detail-value"><?= htmlspecialchars($orderDetails['username']) ?></div>
                                </div>
                                <div class="order-detail-item">
                                    <div class="order-detail-label">Status</div>
                                    <div class="order-detail-value">
                                        <span class="status-badge status-<?= strtolower(htmlspecialchars($orderDetails['status'])) ?>">
                                            <?= htmlspecialchars($orderDetails['status']) ?>
                                        </span>
                                    </div>
                                </div>

                            </div>

                            <!-- Payment Information -->
                            <details class="order-details-section mt-4">
                                <summary class="cursor-pointer font-semibold text-gray-700 select-none">Payment Information</summary>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Method</label>
                                        <p class="text-sm text-gray-900 leading-tight"><?= htmlspecialchars($orderDetails['paymentMethod'] ?? ''); ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Status</label>
                                        <p class="text-sm text-gray-900 leading-tight"><?= htmlspecialchars($orderDetails['paymentStatus'] ?? ''); ?></p>
                                    </div>
                                    <?php if (!empty($orderDetails['paymentDate'])): ?>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Date</label>
                                        <p class="text-sm text-gray-900 leading-tight"><?= htmlspecialchars(date('F j, Y', strtotime($orderDetails['paymentDate']))); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (($orderDetails['paymentMethod'] ?? '') === 'Check' && !empty($orderDetails['checkNumber'])): ?>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Check #</label>
                                        <p class="text-sm text-gray-900 leading-tight"><?= htmlspecialchars($orderDetails['checkNumber']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </details>

                            <!-- Shipping Information -->
                            <details class="order-details-section mt-4">
                                <summary class="cursor-pointer font-semibold text-gray-700 select-none">Shipping Information</summary>
                                <div class="mt-2">
                                    <div class="order-detail-item mb-4">
                                        <div class="order-detail-label">Shipping Method</div>
                                        <div class="order-detail-value"><?= htmlspecialchars($orderDetails['shippingMethod'] ?? 'Standard') ?></div>
                                    </div>
                                    <?php if (!empty($orderDetails['trackingNumber'])): ?>
                                    <div class="order-detail-item mb-4">
                                        <div class="order-detail-label">Tracking Number</div>
                                        <div class="order-detail-value"><?= htmlspecialchars($orderDetails['trackingNumber']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="order-detail-item">
                                        <div class="order-detail-label">Shipping Address</div>
                                        <div class="order-detail-value">
                                            <?php
                                                $customShip = trim($orderDetails['shippingAddress'] ?? '');
                                                if ($customShip !== '') {
                                                    echo nl2br(htmlspecialchars($customShip));
                                                } else {
                                                    $addrParts = array_filter([
                                                        $orderDetails['addressLine1'] ?? '',
                                                        $orderDetails['addressLine2'] ?? '',
                                                        ($orderDetails['city'] ?? '') . (isset($orderDetails['state']) ? ', ' . $orderDetails['state'] : ''),
                                                        $orderDetails['zipCode'] ?? ''
                                                    ]);
                                                    $fullAddress = !empty($addrParts) ? implode('<br>', array_map('htmlspecialchars', $addrParts)) : 'N/A';
                                                    echo $fullAddress;
                                                }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </details>

                            <!-- Customer Information -->
                            <details class="order-details-section mt-4">
                                <summary class="cursor-pointer font-semibold text-gray-700 select-none">Customer Information</summary>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Username</label>
                                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($orderDetails['username']); ?></p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Address</label>
                                        <?php
                                            $cParts = array_filter([
                                                $orderDetails['addressLine1'] ?? '',
                                                $orderDetails['addressLine2'] ?? '',
                                                ($orderDetails['city'] ?? '') . (isset($orderDetails['state']) ? ', ' . $orderDetails['state'] : ''),
                                                $orderDetails['zipCode'] ?? ''
                                            ]);
                                            $cAddr = !empty($cParts) ? implode('<br>', array_map('htmlspecialchars', $cParts)) : 'N/A';
                                        ?>
                                        <p class="text-sm text-gray-900 leading-tight"><?= $cAddr; ?></p>
                                    </div>
                                </div>
                            </details>
                        </div>
                        
                        <!-- Right column: items list -->
                        <div class="order-details-section">
                            <div class="flex justify-between items-center mb-3">
                                <h3>Items</h3>
                                <div class="text-lg font-semibold text-green-700">Total: $<?= number_format(floatval($orderDetails['total']), 2) ?></div>
                            </div>
                            <table class="w-full text-sm">
                                <thead><tr><th class="text-left">Item</th><th class="text-center">Qty</th><th class="text-right">Total</th></tr></thead>
                                <tbody>
                                    <?php
                                        $itemStmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.productId = p.id WHERE oi.orderId = ? LIMIT 6");
                                        $itemStmt->execute([$viewOrderId]);
                                        $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($orderItems as $it): ?>
                                            <tr><td><?= htmlspecialchars($it['name']); ?></td><td class="text-center"><?= $it['quantity']; ?></td><td class="text-right">$<?= number_format($it['price'] * $it['quantity'], 2); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-form-cost-column">
                    <!-- Notes Section -->
                    <div class="order-notes-section mt-6">
                        <h3>Notes</h3>

                        <!-- Add-note form -->
                        <form method="POST" action="api/fulfill_order.php" class="note-form" data-mode="view">
                            <input type="hidden" name="orderId" value="<?= htmlspecialchars($viewOrderId) ?>">
                            <input type="hidden" name="action" value="note">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">
                                <textarea name="note" rows="2" placeholder="Add fulfillment note..." class="w-full p-2 border rounded"></textarea>
                                <textarea name="paynote" rows="2" placeholder="Add payment note..." class="w-full p-2 border rounded"></textarea>
                            </div>
                            <button type="submit" class="brand-button px-3 py-1 text-sm">Save Note</button>
                        </form>

                        <?php
                            $fLines = !empty($orderDetails['fulfillmentNotes']) ? array_filter(explode("\n", trim($orderDetails['fulfillmentNotes']))) : [];
                            $pLines = !empty($orderDetails['paymentNotes']) ? array_filter(explode("\n", trim($orderDetails['paymentNotes']))) : [];
                            $latestF = !empty($fLines) ? array_pop($fLines) : '';
                            $latestP = !empty($pLines) ? array_pop($pLines) : '';
                        ?>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="font-medium mb-2">Fulfillment Notes</p>
                                <?php if($latestF !== ''): ?>
                                    <div class="bg-gray-50 p-2 rounded mb-1"><?= htmlspecialchars($latestF) ?></div>
                                <?php else: ?><p class="text-gray-500 text-xs">None</p><?php endif; ?>
                                <?php if(!empty($fLines)): ?>
                                    <details class="text-xs">
                                        <summary class="cursor-pointer text-blue-600 underline">Show previous (<?= count($fLines) ?>)</summary>
                                        <?php foreach(array_reverse($fLines) as $ln): ?>
                                            <div class="bg-gray-50 p-2 rounded mb-1 mt-1"><?= htmlspecialchars($ln) ?></div>
                                        <?php endforeach; ?>
                                    </details>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="font-medium mb-2">Payment Notes</p>
                                <?php if($latestP !== ''): ?>
                                    <div class="bg-gray-50 p-2 rounded mb-1"><?= htmlspecialchars($latestP) ?></div>
                                <?php else: ?><p class="text-gray-500 text-xs">None</p><?php endif; ?>
                                <?php if(!empty($pLines)): ?>
                                    <details class="text-xs">
                                        <summary class="cursor-pointer text-blue-600 underline">Show previous (<?= count($pLines) ?>)</summary>
                                        <?php foreach(array_reverse($pLines) as $ln): ?>
                                            <div class="bg-gray-50 p-2 rounded mb-1 mt-1"><?= htmlspecialchars($ln) ?></div>
                                        <?php endforeach; ?>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-auto pt-4 border-t">
                <a href="?page=admin&section=orders" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block text-sm">Close</a>
                <a href="?page=admin&section=orders&edit=<?= htmlspecialchars($viewOrderId) ?>" class="brand-button px-4 py-2 rounded text-sm">Edit Order</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Edit Order Modal -->
<?php if ($modalMode === 'edit' && !empty($editOrderId)): ?>
    <?php
    // Get order details
    $orderStmt = $pdo->prepare("SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode FROM orders o JOIN users u ON o.userId = u.id WHERE o.id = ?");
    $orderStmt->execute([$editOrderId]);
    $orderDetails = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($orderDetails):
    ?>
    <div class="modal-outer" id="editOrderModal">
        <div class="modal-content-wrapper">
            <div class="flex justify-between items-start mb-3">
                <h2 class="text-lg font-bold text-green-700">Edit Order: <?= htmlspecialchars($editOrderId) ?></h2>
                <div class="flex gap-2">
                    <button form="orderForm" type="submit" class="brand-button px-4 py-2 rounded text-sm flex items-center gap-2" id="saveItemBtn">
                        <span class="button-text">Save Changes</span>
                        <span class="loading-spinner hidden"></span>
                    </button>
                    <a href="?page=admin&section=orders" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block text-sm">Cancel</a>
                </div>
            </div>
            
            <div class="modal-form-container">
                <div class="modal-form-main-column">
                    <form id="orderForm" method="POST" action="#" class="space-y-4">
                        <input type="hidden" name="orderId" value="<?= htmlspecialchars($editOrderId) ?>">
                        
                        <!-- Order Information -->
                        <div class="order-details-section">
                            <h3>Order Information</h3>
                            <div class="order-details-grid">
                                <div class="order-detail-item">
                                    <div class="order-detail-label">Order ID</div>
                                    <div class="order-detail-value"><?= htmlspecialchars($orderDetails['id']) ?></div>
                                </div>
                                <div class="order-detail-item">
                                    <div class="order-detail-label">Date</div>
                                    <div class="order-detail-value"><?= htmlspecialchars(date('F j, Y', strtotime($orderDetails['date']))) ?></div>
                                </div>
                                <div class="order-detail-item">
                                    <div class="order-detail-label">Customer</div>
                                    <div class="order-detail-value"><?= htmlspecialchars($orderDetails['username']) ?></div>
                                </div>
                                <div class="order-detail-item">
                                    <div class="order-detail-label">Status</div>
                                    <div class="order-detail-value">
                                        <select id="status" name="status" class="mt-1 block w-full p-2 border border-gray-300 rounded text-sm">
                                            <option value="Pending" <?= $orderDetails['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Processing" <?= $orderDetails['status'] === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                            <option value="Shipped" <?= $orderDetails['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                            <option value="Delivered" <?= $orderDetails['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="Cancelled" <?= $orderDetails['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            <option value="Pending" <?= $orderDetails['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        </select>
                                    </div>
                                </div>

                            </div>

                            <!-- Payment Information -->
                            <details class="order-details-section mt-4">
                                <summary class="cursor-pointer font-semibold text-gray-700 select-none">Payment Information</summary>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Method</label>
                                        <select id="paymentMethod" name="paymentMethod" class="block w-full p-2 border border-gray-300 rounded text-sm" onchange="toggleCheckNumberField()">
                                            <option value="Credit Card" <?= $orderDetails['paymentMethod'] === 'Credit Card' ? 'selected' : '' ?>>Credit Card</option>
                                            <option value="PayPal" <?= $orderDetails['paymentMethod'] === 'PayPal' ? 'selected' : '' ?>>PayPal</option>
                                            <option value="Check" <?= $orderDetails['paymentMethod'] === 'Check' ? 'selected' : '' ?>>Check</option>
                                            <option value="Cash" <?= $orderDetails['paymentMethod'] === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Status</label>
                                        <select id="paymentStatus" name="paymentStatus" class="block w-full p-2 border border-gray-300 rounded text-sm">
                                            <option value="Pending" <?= $orderDetails['paymentStatus'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Received" <?= $orderDetails['paymentStatus'] === 'Received' ? 'selected' : '' ?>>Received</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Date</label>
                                        <input type="date" id="paymentDate" name="paymentDate" class="block w-full p-2 border border-gray-300 rounded text-sm" value="<?= htmlspecialchars($orderDetails['paymentDate'] ?? '') ?>">
                                    </div>
                                    <div id="checkNumberField" style="<?= $orderDetails['paymentMethod'] === 'Check' ? '' : 'display: none;' ?>">
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Check #</label>
                                        <input type="text" id="checkNumber" name="checkNumber" class="block w-full p-2 border border-gray-300 rounded text-sm" value="<?= htmlspecialchars($orderDetails['checkNumber'] ?? '') ?>">
                                    </div>
                                </div>
                            </details>

                            <!-- Shipping Information -->
                            <details class="order-details-section mt-4">
                                <summary class="cursor-pointer font-semibold text-gray-700 select-none">Shipping Information</summary>
                                <div class="mt-2">
                                    <div class="order-detail-item mb-4">
                                        <div class="order-detail-label">Shipping Method</div>
                                        <div class="order-detail-value">
                                            <select id="shippingMethod" name="shippingMethod" class="block w-full p-2 border border-gray-300 rounded text-sm" onchange="toggleTrackingNumberField()">
                                                <option value="Customer Pickup" <?= ($orderDetails['shippingMethod'] ?? 'Customer Pickup') === 'Customer Pickup' ? 'selected' : '' ?>>Customer Pickup</option>
                                                <option value="Local Delivery" <?= ($orderDetails['shippingMethod'] ?? '') === 'Local Delivery' ? 'selected' : '' ?>>Local Delivery</option>
                                                <option value="USPS" <?= ($orderDetails['shippingMethod'] ?? '') === 'USPS' ? 'selected' : '' ?>>USPS</option>
                                                <option value="FedEx" <?= ($orderDetails['shippingMethod'] ?? '') === 'FedEx' ? 'selected' : '' ?>>FedEx</option>
                                                <option value="UPS" <?= ($orderDetails['shippingMethod'] ?? '') === 'UPS' ? 'selected' : '' ?>>UPS</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="order-detail-item mb-4" id="trackingNumberField">
                                        <div class="order-detail-label">Tracking Number</div>
                                        <div class="order-detail-value">
                                            <input type="text" id="trackingNumber" name="trackingNumber" class="block w-full p-2 border border-gray-300 rounded text-sm" value="<?= htmlspecialchars($orderDetails['trackingNumber'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="order-detail-item" id="shippingAddressSection">
                                        <div class="order-detail-label">Shipping Address</div>
                                        <?php $useAccount = empty(trim($orderDetails['shippingAddress'] ?? '')); ?>
                                        <div class="mb-2 flex items-center gap-4 mt-2">
                                            <label class="inline-flex items-center text-xs">
                                                <input type="radio" name="shipAddrMode" value="account" <?= $useAccount ? 'checked' : '' ?> class="mr-1"> Use customer address
                                            </label>
                                            <label class="inline-flex items-center text-xs">
                                                <input type="radio" name="shipAddrMode" value="custom" <?= !$useAccount ? 'checked' : '' ?> class="mr-1"> Custom address
                                            </label>
                                        </div>
                                        <div id="customShippingFields" class="grid grid-cols-1 gap-2 mt-2" <?= $useAccount ? 'style="display:none;"' : '' ?>>
                                            <?php
                                                $customAddrRaw = $orderDetails['shippingAddress'] ?? '';
                                                $shipLine1 = $shipLine2 = $shipCity = $shipState = $shipZip = '';
                                                if (!empty($customAddrRaw)) {
                                                    if ($customAddrRaw[0] === '{') {
                                                        $obj = json_decode($customAddrRaw, true);
                                                        if ($obj) {
                                                            $shipLine1 = $obj['street'] ?? ($obj['line1'] ?? '');
                                                            $shipLine2 = $obj['line2'] ?? '';
                                                            $shipCity  = $obj['city'] ?? '';
                                                            $shipState = $obj['state'] ?? '';
                                                            $shipZip   = $obj['zip'] ?? '';
                                                        }
                                                    } else {
                                                        $parts = preg_split('/\r?\n/', $customAddrRaw);
                                                        $shipLine1 = $parts[0] ?? '';
                                                        $shipLine2 = $parts[1] ?? '';
                                                        if (isset($parts[2])) {
                                                            $cityLine = trim($parts[2]);
                                                            if (preg_match('/^(.*?)(?:,\s*([A-Za-z]{2}))?\s*(\d{5})?$/', $cityLine, $m)) {
                                                                $shipCity = $m[1] ?? '';
                                                                $shipState = $m[2] ?? '';
                                                                $shipZip = $m[3] ?? '';
                                                            }
                                                        }
                                                    }
                                                }
                                                if (empty($shipLine1.$shipLine2.$shipCity.$shipState.$shipZip)) {
                                                    // Fallback to customer's billing/account address
                                                    $shipLine1 = $orderDetails['addressLine1'] ?? '';
                                                    $shipLine2 = $orderDetails['addressLine2'] ?? '';
                                                    $shipCity  = $orderDetails['city'] ?? '';
                                                    $shipState = $orderDetails['state'] ?? '';
                                                    $shipZip   = $orderDetails['zipCode'] ?? '';
                                                }
                                            ?>
                                            <input type="text" id="shipLine1" name="shipLine1" class="block w-full p-2 border border-gray-300 rounded text-sm" placeholder="Address Line 1" value="<?= htmlspecialchars($shipLine1); ?>">
                                            <input type="text" id="shipLine2" name="shipLine2" class="block w-full p-2 border border-gray-300 rounded text-sm" placeholder="Address Line 2" value="<?= htmlspecialchars($shipLine2); ?>">
                                            <div class="grid grid-cols-3 gap-2">
                                                <input type="text" id="shipCity" name="shipCity" class="block w-full p-2 border border-gray-300 rounded text-sm" placeholder="City" value="<?= htmlspecialchars($shipCity); ?>">
                                                <input type="text" id="shipState" name="shipState" class="block w-full p-2 border border-gray-300 rounded text-sm" placeholder="State" value="<?= htmlspecialchars($shipState); ?>">
                                                <input type="text" id="shipZip" name="shipZip" class="block w-full p-2 border border-gray-300 rounded text-sm" placeholder="ZIP" value="<?= htmlspecialchars($shipZip); ?>">
                                            </div>
                                        </div>
                                        <div id="accountShippingDisplay" <?= !$useAccount ? 'style="display:none;"' : '' ?>>
                                            <?php
                                                $addrParts = array_filter([
                                                    $orderDetails['addressLine1'] ?? '',
                                                    $orderDetails['addressLine2'] ?? '',
                                                    ($orderDetails['city'] ?? '') . (isset($orderDetails['state']) ? ', ' . $orderDetails['state'] : ''),
                                                    $orderDetails['zipCode'] ?? ''
                                                ]);
                                                $fullAddress = !empty($addrParts) ? implode('<br>', array_map('htmlspecialchars', $addrParts)) : 'N/A';
                                                echo '<div class="order-detail-value text-sm">' . $fullAddress . '</div>';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </details>

                            <!-- Customer Information -->
                            <details class="order-details-section mt-4">
                                <summary class="cursor-pointer font-semibold text-gray-700 select-none">Customer Information</summary>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Username</label>
                                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($orderDetails['username']); ?></p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-gray-700 text-sm font-medium mb-1">Address</label>
                                        <?php
                                            $cParts = array_filter([
                                                $orderDetails['addressLine1'] ?? '',
                                                $orderDetails['addressLine2'] ?? '',
                                                ($orderDetails['city'] ?? '') . (isset($orderDetails['state']) ? ', ' . $orderDetails['state'] : ''),
                                                $orderDetails['zipCode'] ?? ''
                                            ]);
                                            $cAddr = !empty($cParts) ? implode('<br>', array_map('htmlspecialchars', $cParts)) : 'N/A';
                                        ?>
                                        <p class="text-sm text-gray-900 leading-tight"><?= $cAddr; ?></p>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </form>
                </div>
                
                <!-- Middle column: Items -->
                <div class="modal-form-items-column">
                    <div class="order-details-section">
                        <div class="flex justify-between items-center mb-3">
                            <h3>Items</h3>
                            <div class="text-lg font-semibold text-green-700" id="orderTotalDisplay">Total: $<?= number_format(floatval($orderDetails['total']), 2) ?></div>
                        </div>
                        <table class="w-full text-sm" id="itemsTable">
                            <thead><tr><th class="text-left">Item</th><th class="text-center">Qty</th><th class="text-right">Total</th><th class="w-8"></th></tr></thead>
                            <tbody>
                                <?php
                                    $itemStmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.productId = p.id WHERE oi.orderId = ?");
                                    $itemStmt->execute([$editOrderId]);
                                    $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($orderItems as $it): ?>
                                    <tr class="item-row" data-item-id="<?= htmlspecialchars($it['id']) ?>" data-product-id="<?= htmlspecialchars($it['productId']) ?>" data-price="<?= $it['price'] ?>">
                                        <td><?= htmlspecialchars($it['name']); ?></td>
                                        <td class="text-center">
                                            <input type="number" class="w-16 border-0 bg-transparent text-center qty-input focus:bg-white focus:border focus:border-blue-300 rounded" 
                                                   value="<?= $it['quantity'] ?>" min="1" onchange="updateRowTotal(this)" 
                                                   style="background: none; outline: none;">
                                        </td>
                                        <td class="text-right line-total">$<?= number_format($it['price'] * $it['quantity'], 2) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="text-red-500 hover:text-red-700 p-1" onclick="removeItemAndUpdateTotal(this)" title="Remove item">
                                                🗑️
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="mt-3 pt-2 border-t text-sm">
                            <div class="flex items-center gap-2 mb-2" id="addItemRow">
                                <select id="addProductSelect" class="flex-1 border rounded px-2 py-1 text-sm max-w-xs">
                                    <option value="">-- Add Product --</option>
                                    <?php foreach($allProducts as $p): ?>
                                        <option value="<?= htmlspecialchars($p['id']) ?>" data-price="<?= $p['basePrice'] ?>"><?= htmlspecialchars($p['name']) ?> ($<?= number_format($p['basePrice'], 2) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" id="addQty" class="w-16 border rounded px-2 py-1 text-sm" min="1" value="1">
                                <button type="button" class="brand-button px-3 py-1 text-sm" id="addItemBtn">Add</button>
                            </div>

                        </div>
                    </div>
                </div>
                
                <!-- Right column: Notes -->
                <div class="modal-form-cost-column">
                    <!-- Notes Section -->
                    <div class="order-notes-section mt-6">
                        <h3>Notes</h3>

                        <!-- Add-note form -->
                        <form method="POST" action="api/fulfill_order.php" class="note-form" data-mode="edit">
                            <input type="hidden" name="orderId" value="<?= htmlspecialchars($editOrderId) ?>">
                            <input type="hidden" name="action" value="note">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">
                                <textarea name="note" rows="2" placeholder="Add fulfillment note..." class="w-full p-2 border rounded"></textarea>
                                <textarea name="paynote" rows="2" placeholder="Add payment note..." class="w-full p-2 border rounded"></textarea>
                            </div>
                            <button type="submit" class="brand-button px-3 py-1 text-sm">Save Note</button>
                        </form>

                        <?php
                            $fLines = !empty($orderDetails['fulfillmentNotes']) ? array_filter(explode("\n", trim($orderDetails['fulfillmentNotes']))) : [];
                            $pLines = !empty($orderDetails['paymentNotes']) ? array_filter(explode("\n", trim($orderDetails['paymentNotes']))) : [];
                            $latestF = !empty($fLines) ? array_pop($fLines) : '';
                            $latestP = !empty($pLines) ? array_pop($pLines) : '';
                        ?>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="font-medium mb-2">Fulfillment Notes</p>
                                <?php if($latestF !== ''): ?>
                                    <div class="bg-gray-50 p-2 rounded mb-1"><?= htmlspecialchars($latestF) ?></div>
                                <?php else: ?><p class="text-gray-500 text-xs">None</p><?php endif; ?>
                                <?php if(!empty($fLines)): ?>
                                    <details class="text-xs">
                                        <summary class="cursor-pointer text-blue-600 underline">Show previous (<?= count($fLines) ?>)</summary>
                                        <?php foreach(array_reverse($fLines) as $ln): ?>
                                            <div class="bg-gray-50 p-2 rounded mb-1 mt-1"><?= htmlspecialchars($ln) ?></div>
                                        <?php endforeach; ?>
                                    </details>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="font-medium mb-2">Payment Notes</p>
                                <?php if($latestP !== ''): ?>
                                    <div class="bg-gray-50 p-2 rounded mb-1"><?= htmlspecialchars($latestP) ?></div>
                                <?php else: ?><p class="text-gray-500 text-xs">None</p><?php endif; ?>
                                <?php if(!empty($pLines)): ?>
                                    <details class="text-xs">
                                        <summary class="cursor-pointer text-blue-600 underline">Show previous (<?= count($pLines) ?>)</summary>
                                        <?php foreach(array_reverse($pLines) as $ln): ?>
                                            <div class="bg-gray-50 p-2 rounded mb-1 mt-1"><?= htmlspecialchars($ln) ?></div>
                                        <?php endforeach; ?>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="delete-modal">
    <div class="delete-modal-content">
        <h2 class="text-md font-bold mb-3 text-gray-800">Confirm Delete</h2>
        <p class="mb-4 text-sm text-gray-600">Are you sure you want to delete this order? This action cannot be undone.</p>
        <div class="flex justify-end space-x-2">
            <button type="button" class="px-3 py-1.5 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm close-modal-button">Cancel</button>
            <button type="button" id="confirmDeleteBtn" class="px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700 text-sm">Delete</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle check number field based on payment method
    window.toggleCheckNumberField = function() {
        const paymentMethodEl = document.getElementById('paymentMethod');
        const checkNumberField = document.getElementById('checkNumberField');
        
        if (paymentMethodEl && checkNumberField) {
            if (paymentMethodEl.value === 'Check') {
                checkNumberField.style.display = 'block';
            } else {
                checkNumberField.style.display = 'none';
            }
        }
    };
    
    // Toggle tracking number field and shipping address based on shipping method
    window.toggleTrackingNumberField = function() {
        const shippingMethodEl = document.getElementById('shippingMethod');
        const trackingNumberField = document.getElementById('trackingNumberField');
        const shippingAddressSection = document.getElementById('shippingAddressSection');
        
        if (shippingMethodEl) {
            // Only show tracking number for methods that use tracking
            if (trackingNumberField) {
                const trackingMethods = ['USPS', 'FedEx', 'UPS'];
                if (trackingMethods.includes(shippingMethodEl.value)) {
                    trackingNumberField.style.display = 'block';
                } else {
                    trackingNumberField.style.display = 'none';
                }
            }
            
            // Hide shipping address for Customer Pickup
            if (shippingAddressSection) {
                if (shippingMethodEl.value === 'Customer Pickup') {
                    shippingAddressSection.style.display = 'none';
                } else {
                    shippingAddressSection.style.display = 'block';
                }
            }
        }
    };
    
    // Show toast notification
    function showToast(type, message) {
        const existingToast = document.getElementById('toast-notification');
        if (existingToast) {
            existingToast.remove();
        }
        const toast = document.createElement('div');
        toast.id = 'toast-notification';
        toast.className = `toast-notification ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Handle order form submission
    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
        orderForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const saveBtn = document.getElementById('saveItemBtn');
            const btnText = saveBtn.querySelector('.button-text');
            const spinner = saveBtn.querySelector('.loading-spinner');
            
            btnText.classList.add('hidden');
            spinner.classList.remove('hidden');
            saveBtn.disabled = true;
            
            const formData = new FormData(orderForm);
            const shippingMethodEl = document.getElementById('shippingMethod');
            const shippingAddressSection = document.getElementById('shippingAddressSection');
            const useCustom = document.querySelector('input[name="shipAddrMode"]:checked')?.value === 'custom';
            
            const buildAddress = () => {
                // If shipping method is Customer Pickup or section is hidden, return empty address
                if (shippingMethodEl.value === 'Customer Pickup' || 
                    (shippingAddressSection && shippingAddressSection.style.display === 'none')) {
                    return '';
                }
                
                // If using custom address, build from form fields
                if (useCustom) {
                    const l1 = formData.get('shipLine1').trim();
                    const l2 = formData.get('shipLine2').trim();
                    const city = formData.get('shipCity').trim();
                    const st = formData.get('shipState').trim();
                    const zip = formData.get('shipZip').trim();
                    let parts = [];
                    if (l1) parts.push(l1);
                    if (l2) parts.push(l2);
                    let cityLine = '';
                    if (city) cityLine += city;
                    if (st) cityLine += (cityLine ? ', ' : '') + st;
                    if (zip) cityLine += (cityLine ? ' ' : '') + zip;
                    if (cityLine) parts.push(cityLine);
                    return parts.join('\n');
                } else {
                    // Use account address - return empty to signal using account address
                    return '';
                }
            };
            // Collect items table rows
            const items=[];
            document.querySelectorAll('#itemsTable tbody tr.item-row').forEach(async tr=>{
                const pid=tr.dataset.productId;
                // Generate streamlined order item ID for frontend use
            let nextItemId = 'OI001'; // Default fallback
            try {
                const response = await fetch('/api/next-order-item-id.php');
                const responseText = await response.text();
                        let data;
                        try {
                            data = JSON.parse(responseText);
                        } catch (e) {
                            console.error("JSON parse error:", e, "Raw response:", responseText);
                            throw new Error("Invalid response from server");
                        }
                nextItemId = data.nextId || 'OI001';
            } catch (e) {
                console.warn('Could not fetch next order item ID, using fallback');
            }
            const rowId = tr.dataset.itemId || nextItemId;
                const qty=parseInt(tr.querySelector('.qty-input').value||'0',10);
                if(pid && qty>0){ items.push({orderItemId:rowId,productId:pid,quantity:qty}); }
            });

            const payload = {
                orderId: formData.get('orderId'),
                status: formData.get('status'),
                trackingNumber: formData.get('trackingNumber'),
                paymentStatus: formData.get('paymentStatus'),
                paymentMethod: formData.get('paymentMethod'),
                shippingMethod: formData.get('shippingMethod'),
                checkNumber: formData.get('checkNumber'),
                paymentDate: formData.get('paymentDate'),
                paymentNotes: formData.get('paymentNotes'),
                shippingAddress: useCustom ? buildAddress() : '',
                items: items
            };
            

            fetch('/api/update-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', data.message || 'Order updated successfully.');
                    setTimeout(() => {
                        window.location.href = '?page=admin&section=orders&highlight=' + payload.orderId;
                    }, 1000);
                } else {
                    showToast('error', data.error || 'Failed to update order.');
                    btnText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                    saveBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'An error occurred while updating the order.');
                btnText.classList.remove('hidden');
                spinner.classList.add('hidden');
                saveBtn.disabled = false;
            });
        });
    }
    
    // Total calculation functions
    function updateRowTotal(qtyInput) {
        const row = qtyInput.closest('tr');
        const price = parseFloat(row.dataset.price || 0);
        const qty = parseInt(qtyInput.value || 0);
        const lineTotal = price * qty;
        
        // Update this row's line total
        const lineTotalCell = row.querySelector('.line-total');
        if (lineTotalCell) {
            lineTotalCell.textContent = '$' + lineTotal.toFixed(2);
        }
        
        updateGrandTotal();
    }

    function updateGrandTotal() {
        let grandTotal = 0;
        document.querySelectorAll('#itemsTable tbody tr.item-row').forEach(row => {
            const price = parseFloat(row.dataset.price || 0);
            const qty = parseInt(row.querySelector('.qty-input').value || 0);
            grandTotal += price * qty;
        });
        
        const orderTotalDisplayEl = document.getElementById('orderTotalDisplay');
        if (orderTotalDisplayEl) orderTotalDisplayEl.textContent = 'Total: $' + grandTotal.toFixed(2);
    }

    function removeItemAndUpdateTotal(button) {
        button.closest('tr').remove();
        updateGrandTotal();
    }

    // Make functions global so onclick handlers can access them
    window.updateRowTotal = updateRowTotal;
    window.removeItemAndUpdateTotal = removeItemAndUpdateTotal;

    // Individual item removal is now handled by the trash can buttons

    // Add/remove item handlers (only in edit modal)
    const itemsTbody=document.querySelector('#itemsTable tbody');
    const addBtn=document.getElementById('addItemBtn');
    if(addBtn){
        addBtn.addEventListener('click', async () => {
            const sel=document.getElementById('addProductSelect');
            const qtyInput=document.getElementById('addQty');
            const pid=sel.value; const qty=parseInt(qtyInput.value||'0',10);
            if(!pid||qty<=0){ alert('Select product and qty'); return; }
            
            const selectedOption = sel.options[sel.selectedIndex];
            const price = parseFloat(selectedOption.dataset.price || 0);
            const name = selectedOption.text.split(' - $')[0]; // Remove price from display name
            
            // If product already exists row, just update qty
            const existingRow=[...itemsTbody.querySelectorAll('tr')].find(r=>r.dataset.productId===pid);
            if(existingRow){ 
                existingRow.querySelector('.qty-input').value = qty; 
                updateRowTotal(existingRow.querySelector('.qty-input'));
            }
            else{
                const tr=document.createElement('tr');
                tr.className='item-row border-b';
                tr.dataset.productId=pid;
                tr.dataset.price=price;
                // Generate streamlined order item ID for new items
            let nextItemId = 'OI001'; // Default fallback
            try {
                const response = await fetch('/api/next-order-item-id.php');
                const responseText = await response.text();
                        let data;
                        try {
                            data = JSON.parse(responseText);
                        } catch (e) {
                            console.error("JSON parse error:", e, "Raw response:", responseText);
                            throw new Error("Invalid response from server");
                        }
                nextItemId = data.nextId || 'OI001';
            } catch (e) {
                console.warn('Could not fetch next order item ID, using fallback');
            }
            tr.dataset.itemId = nextItemId;
                tr.innerHTML=`<td>${name}</td><td class="text-center"><input type="number" class="w-16 border-0 bg-transparent text-center qty-input focus:bg-white focus:border focus:border-blue-300 rounded" value="${qty}" min="1" onchange="updateRowTotal(this)" style="background: none; outline: none;"></td><td class="text-right line-total">$${(price * qty).toFixed(2)}</td><td class="text-center"><button type="button" class="text-red-500 hover:text-red-700 p-1" onclick="removeItemAndUpdateTotal(this)" title="Remove item">🗑️</button></td>`;
                itemsTbody.appendChild(tr);
                updateGrandTotal();
            }
            sel.value=''; qtyInput.value='1';
        });
    }

    // Handle delete order buttons
    let orderIdToDelete = null;
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    
    document.querySelectorAll('.delete-order').forEach(button => {
        button.addEventListener('click', function() {
            orderIdToDelete = this.dataset.id;
            if (deleteConfirmModal) {
                deleteConfirmModal.classList.add('show');
            }
        });
    });
    
    // Handle delete confirmation
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (!orderIdToDelete) return;
            
            // Assuming your delete API is at /api/delete-order.php and takes orderId as a query param
            fetch(`/api/delete-order.php?orderId=${orderIdToDelete}`, { // Make sure this endpoint exists and works
                method: 'DELETE' // Or POST if your server expects that for deletion
            })
            .then(response => response.json()) // Assuming it returns JSON
            .then(data => {
                if (data.success) {
                    showToast('success', data.message || 'Order deleted successfully');
                    setTimeout(() => {
                        window.location.reload(); // Reload to reflect changes
                    }, 1000);
                } else {
                    showToast('error', data.error || 'Failed to delete order');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'An error occurred while deleting the order');
            });
            
            if(deleteConfirmModal) deleteConfirmModal.classList.remove('show');
        });
    }
    
    // Close delete modal
    document.querySelectorAll('.close-modal-button').forEach(button => {
        button.addEventListener('click', function() {
            if(deleteConfirmModal) deleteConfirmModal.classList.remove('show');
        });
    });
    
    // Close modals on escape key
    window.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const viewModal = document.getElementById('viewOrderModal');
            const editModal = document.getElementById('editOrderModal');

            if (deleteConfirmModal && deleteConfirmModal.classList.contains('show')) {
                deleteConfirmModal.classList.remove('show');
            } else if (viewModal && viewModal.offsetParent !== null) { // Check if visible
                 window.location.href = '?page=admin&section=orders'; // Close by redirecting
            } else if (editModal && editModal.offsetParent !== null) { // Check if visible
                 window.location.href = '?page=admin&section=orders'; // Close by redirecting
            }
        }
    });

    // Highlight row if specified in URL (e.g., after an update)
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight');
    if (highlightId) {
        const rowToHighlight = document.querySelector(`table.orders-table tr td:first-child`).parentNode; // Simplistic selector, refine if needed
        // A more robust selector would be to add data-order-id to TR tags
        // Example: document.querySelector(`tr[data-order-id='${highlightId}']`)
        if (rowToHighlight) {
            rowToHighlight.style.backgroundColor = '#fefcbf'; // Light yellow
            setTimeout(() => {
                rowToHighlight.style.backgroundColor = ''; // Reset
                // Optionally remove highlight param from URL
                const cleanUrl = window.location.pathname + '?page=admin&section=orders';
                history.replaceState(null, '', cleanUrl);
            }, 3000);
        }
    }

    // Show/hide custom shipping fields
    document.querySelectorAll('input[name="shipAddrMode"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const customFields = document.getElementById('customShippingFields');
            const accountDisplay = document.getElementById('accountShippingDisplay');
            if (this.value === 'custom') {
                if (customFields) customFields.style.display = 'grid';
                if (accountDisplay) accountDisplay.style.display = 'none';
                // Auto-populate with account address if fields empty
                ['shipLine1','shipLine2','shipCity','shipState','shipZip'].forEach(id => {
                    const el=document.getElementById(id);
                    if(el && !el.value){ el.value = accountAddr[id] || ''; }
                });
            } else {
                if (customFields) customFields.style.display = 'none';
                if (accountDisplay) accountDisplay.style.display = 'block';
            }
        });
    });

    // Inject account address for JS use
    const accountAddr = <?= json_encode([
        'shipLine1' => $orderDetails['addressLine1'] ?? '',
        'shipLine2' => $orderDetails['addressLine2'] ?? '',
        'shipCity'  => $orderDetails['city'] ?? '',
        'shipState' => $orderDetails['state'] ?? '',
        'shipZip'   => $orderDetails['zipCode'] ?? ''
    ]); ?>;

    // Initialize totals on page load
    if (document.getElementById('itemsTable')) {
        updateGrandTotal();
    }
    
    // Initialize tracking number field visibility on page load
    toggleTrackingNumberField();

    // Modal scroll handling - let modal manage its own overflow naturally

    // Handle add-note forms (fulfillment / payment)
    document.querySelectorAll('.note-form').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(form);
            
            // Check if any notes were actually entered
            const noteText = fd.get('note').trim();
            const payText = fd.get('paynote').trim();
            
            if (!noteText && !payText) {
                alert('Please enter a fulfillment note or payment note before saving.');
                return;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Saving...';
            submitBtn.disabled = true;
            
            try {
                const res = await fetch('api/fulfill_order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast('success', 'Notes saved successfully!');
                    // Clear the form
                    form.querySelector('textarea[name="note"]').value = '';
                    form.querySelector('textarea[name="paynote"]').value = '';
                    // Reload the page after a short delay to show updated notes
                    setTimeout(() => {
                        const url = new URL(window.location.href);
                        if (url.searchParams.has('edit')) {
                            url.search = '?page=admin&section=orders&edit=' + fd.get('orderId');
                        } else if (url.searchParams.has('view')) {
                            url.search = '?page=admin&section=orders&view=' + fd.get('orderId');
                        }
                        window.location.href = url.toString();
                    }, 1000);
                } else {
                    showToast('error', data.error || 'Error saving note');
                }
            } catch (err) {
                showToast('error', 'Network error while saving note');
                console.error('Note save error:', err);
            } finally {
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });
    });

    // Inline editing functionality
    document.querySelectorAll('.editable-field').forEach(field => {
        field.addEventListener('click', function() {
            if (this.classList.contains('editing')) return;
            
            console.log('Inline editing clicked:', this.dataset.field);
            
            const orderId = this.dataset.orderId;
            const fieldName = this.dataset.field;
            const fieldType = this.dataset.type;
            
            // Get current value based on field type
            let currentValue;
            if (fieldName === 'status' || fieldName === 'paymentStatus') {
                // For badge fields, get the text from the span inside
                const badge = this.querySelector('span');
                currentValue = badge ? badge.textContent.trim() : this.textContent.trim();
            } else {
                currentValue = this.textContent.trim();
            }
            
            // Store original HTML for restoration
            const originalHTML = this.innerHTML;
            
            this.classList.add('editing');
            
            if (fieldType === 'select') {
                const select = document.createElement('select');
                select.className = 'w-full';
                
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
                    if (option === currentValue) {
                        optionElement.selected = true;
                    }
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
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=updateField&orderId=${orderId}&field=${fieldName}&value=${encodeURIComponent(newValue)}`
                        });
                        
                        const responseText = await response.text();
                        let data;
                        try {
                            data = JSON.parse(responseText);
                        } catch (e) {
                            console.error("JSON parse error:", e, "Raw response:", responseText);
                            throw new Error("Invalid response from server");
                        }
                        
                        if (data.success) {
                            this.classList.remove('editing');
                            
                            // Update the display based on field type
                            if (fieldName === 'status') {
                                this.innerHTML = `<span class="status-badge status-${newValue.toLowerCase()}">${newValue}</span>`;
                            } else if (fieldName === 'paymentStatus') {
                                this.innerHTML = `<span class="payment-status-badge payment-status-${newValue.toLowerCase()}">${newValue}</span>`;
                            } else {
                                this.textContent = newValue;
                            }
                            
                            showToast('success', data.message);
                        } else {
                            showToast('error', data.error || 'Update failed');
                            this.classList.remove('editing');
                            this.innerHTML = originalHTML;
                        }
                    } catch (error) {
                        showToast('error', 'Network error occurred');
                        this.classList.remove('editing');
                        this.innerHTML = originalHTML;
                    }
                };
                
                select.addEventListener('change', () => saveValue(select.value));
                select.addEventListener('blur', () => saveValue(select.value));
                
            } else if (fieldType === 'date') {
                const input = document.createElement('input');
                input.type = 'date';
                input.className = 'w-full';
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
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=updateField&orderId=${orderId}&field=${fieldName}&value=${encodeURIComponent(newValue)}`
                        });
                        
                        const responseText = await response.text();
                        let data;
                        try {
                            data = JSON.parse(responseText);
                        } catch (e) {
                            console.error("JSON parse error:", e, "Raw response:", responseText);
                            throw new Error("Invalid response from server");
                        }
                        
                        if (data.success) {
                            this.classList.remove('editing');
                            this.textContent = newValue || '';
                            showToast('success', data.message);
                        } else {
                            showToast('error', data.error || 'Update failed');
                            this.classList.remove('editing');
                            this.innerHTML = originalHTML;
                        }
                    } catch (error) {
                        showToast('error', 'Network error occurred');
                        this.classList.remove('editing');
                        this.innerHTML = originalHTML;
                    }
                };
                
                input.addEventListener('change', () => saveValue(input.value));
                input.addEventListener('blur', () => saveValue(input.value));
                
                // Handle escape key to cancel editing
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
</script>

<?php
$output = ob_get_clean();
echo $output;
?>

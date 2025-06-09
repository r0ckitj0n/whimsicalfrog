<?php
// Admin Orders Management Section
ob_start();

// Database connection
$pdo = new PDO($dsn, $user, $pass, $options);

// Get orders
$stmt = $pdo->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.userId = u.id ORDER BY o.date DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    .modal-outer { position: fixed; inset: 0; background-color: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 1rem; }
    .modal-content-wrapper { background-color: white; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); padding: 1.25rem; width: 100%; max-width: 60rem; max-height: 90vh; display: flex; flex-direction: column; }
    .modal-form-container { flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column; padding-right: 0.5rem; }
    
    /* Order details in modal */
    .order-details { margin-bottom: 1rem; }
    .order-details-section { margin-bottom: 1rem; padding: 1rem; border-radius: 0.5rem; background-color: #f9fafb; }
    .order-details-section h3 { margin-bottom: 0.5rem; color: #374151; font-size: 1.1rem; font-weight: 600; }
    .order-details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
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

</style>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-5">
        <h1 class="orders-title text-2xl font-bold" style="color: #87ac3a !important;">Orders Management</h1>
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
                    <th>Payment Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="text-center py-4">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['id']) ?></td>
                        <td><?= htmlspecialchars($order['username']) ?></td>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($order['date']))) ?></td>
                        <td>$<?= number_format(floatval($order['total']), 2) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower(htmlspecialchars($order['status'])) ?>">
                                <?= htmlspecialchars($order['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($order['paymentMethod']) ?></td>
                        <td>
                            <span class="payment-status-badge payment-status-<?= strtolower(htmlspecialchars($order['paymentStatus'])) ?>">
                                <?= htmlspecialchars($order['paymentStatus']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="?page=admin_orders&view=<?= htmlspecialchars($order['id']) ?>" class="action-btn view-btn" title="View Order">👁️</a>
                            <a href="?page=admin_orders&edit=<?= htmlspecialchars($order['id']) ?>" class="action-btn edit-btn" title="Edit Order">✏️</a>
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
    $orderStmt = $pdo->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON o.userId = u.id WHERE o.id = ?");
    $orderStmt->execute([$viewOrderId]);
    $orderDetails = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($orderDetails):
    ?>
    <div class="modal-outer" id="viewOrderModal">
        <div class="modal-content-wrapper">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-bold text-green-700">Order Details: <?= htmlspecialchars($viewOrderId) ?></h2>
                <a href="?page=admin_orders" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</a>
            </div>
            
            <div class="modal-form-container">
                <div class="order-details">
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
                                <div class="order-detail-label">Total</div>
                                <div class="order-detail-value">$<?= number_format(floatval($orderDetails['total']), 2) ?></div>
                            </div>
                            <div class="order-detail-item">
                                <div class="order-detail-label">Status</div>
                                <div class="order-detail-value">
                                    <span class="status-badge status-<?= strtolower(htmlspecialchars($orderDetails['status'])) ?>">
                                        <?= htmlspecialchars($orderDetails['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="order-detail-item">
                                <div class="order-detail-label">Shipping Address</div>
                                <div class="order-detail-value"><?= htmlspecialchars($orderDetails['shippingAddress'] ?? 'N/A') ?></div>
                            </div>
                            <?php if (!empty($orderDetails['trackingNumber'])): ?>
                            <div class="order-detail-item">
                                <div class="order-detail-label">Tracking Number</div>
                                <div class="order-detail-value"><?= htmlspecialchars($orderDetails['trackingNumber']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="order-details-section">
                        <h3>Payment Information</h3>
                        <div class="order-details-grid">
                            <div class="order-detail-item">
                                <div class="order-detail-label">Payment Method</div>
                                <div class="order-detail-value"><?= htmlspecialchars($orderDetails['paymentMethod']) ?></div>
                            </div>
                            <div class="order-detail-item">
                                <div class="order-detail-label">Payment Status</div>
                                <div class="order-detail-value">
                                    <span class="payment-status-badge payment-status-<?= strtolower(htmlspecialchars($orderDetails['paymentStatus'])) ?>">
                                        <?= htmlspecialchars($orderDetails['paymentStatus']) ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($orderDetails['paymentMethod'] === 'Check' && !empty($orderDetails['checkNumber'])): ?>
                            <div class="order-detail-item">
                                <div class="order-detail-label">Check Number</div>
                                <div class="order-detail-value"><?= htmlspecialchars($orderDetails['checkNumber']) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($orderDetails['paymentDate'])): ?>
                            <div class="order-detail-item">
                                <div class="order-detail-label">Payment Date</div>
                                <div class="order-detail-value"><?= htmlspecialchars(date('F j, Y', strtotime($orderDetails['paymentDate']))) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($orderDetails['paymentNotes'])): ?>
                            <div class="order-detail-item col-span-2">
                                <div class="order-detail-label">Payment Notes</div>
                                <div class="order-detail-value"><?= htmlspecialchars($orderDetails['paymentNotes']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Order Items would go here if you have them in your database -->
                </div>
                
                <div class="flex justify-end space-x-3 mt-auto pt-4 border-t">
                    <a href="?page=admin_orders" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block text-sm">Close</a>
                    <a href="?page=admin_orders&edit=<?= htmlspecialchars($viewOrderId) ?>" class="brand-button px-4 py-2 rounded text-sm">Edit Order</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Edit Order Modal -->
<?php if ($modalMode === 'edit' && !empty($editOrderId)): ?>
    <?php
    // Get order details
    $orderStmt = $pdo->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON o.userId = u.id WHERE o.id = ?");
    $orderStmt->execute([$editOrderId]);
    $orderDetails = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($orderDetails):
    ?>
    <div class="modal-outer" id="editOrderModal">
        <div class="modal-content-wrapper">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-bold text-green-700">Edit Order: <?= htmlspecialchars($editOrderId) ?></h2>
                <a href="?page=admin_orders" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</a>
            </div>
            
            <div class="modal-form-container">
                <form id="orderForm" method="POST" action="#" class="space-y-4">
                    <input type="hidden" name="orderId" value="<?= htmlspecialchars($editOrderId) ?>">
                    
                    <div class="order-details-section">
                        <h3>Order Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="status" class="block text-gray-700 text-sm font-medium mb-1">Order Status</label>
                                <select id="status" name="status" class="mt-1 block w-full p-2 border border-gray-300 rounded">
                                    <option value="Pending" <?= $orderDetails['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Processing" <?= $orderDetails['status'] === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                    <option value="Shipped" <?= $orderDetails['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                    <option value="Delivered" <?= $orderDetails['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                    <option value="Cancelled" <?= $orderDetails['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div>
                                <label for="trackingNumber" class="block text-gray-700 text-sm font-medium mb-1">Tracking Number</label>
                                <input type="text" id="trackingNumber" name="trackingNumber" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= htmlspecialchars($orderDetails['trackingNumber'] ?? '') ?>">
                            </div>
                            <div class="col-span-2">
                                <label for="shippingAddress" class="block text-gray-700 text-sm font-medium mb-1">Shipping Address</label>
                                <textarea id="shippingAddress" name="shippingAddress" rows="2" class="mt-1 block w-full p-2 border border-gray-300 rounded"><?= htmlspecialchars($orderDetails['shippingAddress'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-details-section">
                        <h3>Payment Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="paymentMethod" class="block text-gray-700 text-sm font-medium mb-1">Payment Method</label>
                                <select id="paymentMethod" name="paymentMethod" class="mt-1 block w-full p-2 border border-gray-300 rounded" onchange="toggleCheckNumberField()">
                                    <option value="Credit Card" <?= $orderDetails['paymentMethod'] === 'Credit Card' ? 'selected' : '' ?>>Credit Card</option>
                                    <option value="PayPal" <?= $orderDetails['paymentMethod'] === 'PayPal' ? 'selected' : '' ?>>PayPal</option>
                                    <option value="Check" <?= $orderDetails['paymentMethod'] === 'Check' ? 'selected' : '' ?>>Check</option>
                                    <option value="Cash" <?= $orderDetails['paymentMethod'] === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                </select>
                            </div>
                            <div>
                                <label for="paymentStatus" class="block text-gray-700 text-sm font-medium mb-1">Payment Status</label>
                                <select id="paymentStatus" name="paymentStatus" class="mt-1 block w-full p-2 border border-gray-300 rounded">
                                    <option value="Pending" <?= $orderDetails['paymentStatus'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Received" <?= $orderDetails['paymentStatus'] === 'Received' ? 'selected' : '' ?>>Received</option>
                                    <!-- Add other statuses if your API supports them, e.g., Processing, Refunded, Failed -->
                                </select>
                            </div>
                            <div id="checkNumberField" style="<?= $orderDetails['paymentMethod'] === 'Check' ? '' : 'display: none;' ?>">
                                <label for="checkNumber" class="block text-gray-700 text-sm font-medium mb-1">Check Number</label>
                                <input type="text" id="checkNumber" name="checkNumber" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= htmlspecialchars($orderDetails['checkNumber'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="paymentDate" class="block text-gray-700 text-sm font-medium mb-1">Payment Date</label>
                                <input type="date" id="paymentDate" name="paymentDate" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= htmlspecialchars($orderDetails['paymentDate'] ?? '') ?>">
                            </div>
                            <div class="col-span-2">
                                <label for="paymentNotes" class="block text-gray-700 text-sm font-medium mb-1">Payment Notes</label>
                                <textarea id="paymentNotes" name="paymentNotes" rows="2" class="mt-1 block w-full p-2 border border-gray-300 rounded"><?= htmlspecialchars($orderDetails['paymentNotes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-auto pt-4 border-t">
                        <a href="?page=admin_orders" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block text-sm">Cancel</a>
                        <button type="submit" id="saveOrderBtn" class="brand-button px-4 py-2 rounded text-sm">
                            <span class="button-text">Save Changes</span>
                            <span class="loading-spinner hidden"></span>
                        </button>
                    </div>
                </form>
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
        orderForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const saveBtn = document.getElementById('saveOrderBtn');
            const btnText = saveBtn.querySelector('.button-text');
            const spinner = saveBtn.querySelector('.loading-spinner');
            
            btnText.classList.add('hidden');
            spinner.classList.remove('hidden');
            saveBtn.disabled = true;
            
            const formData = new FormData(orderForm);
            const payload = {
                orderId: formData.get('orderId'),
                newStatus: formData.get('paymentStatus')
                // Note: /api/update-payment-status.php only handles payment status.
                // To update other fields (order status, tracking, etc.),
                // a different API endpoint that accepts all these fields would be needed.
                // For example, you might collect all fields like this:
                // status: formData.get('status'),
                // trackingNumber: formData.get('trackingNumber'),
                // shippingAddress: formData.get('shippingAddress'),
                // paymentMethod: formData.get('paymentMethod'),
                // checkNumber: formData.get('checkNumber'),
                // paymentDate: formData.get('paymentDate'),
                // paymentNotes: formData.get('paymentNotes')
            };
            
            fetch('/api/update-payment-status.php', { // This URL is for updating payment status only
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // The message "Order updated successfully" might be misleading if only payment status changed.
                    // Consider changing if this form is intended for more.
                    showToast('success', data.message || 'Payment status updated successfully.');
                    setTimeout(() => {
                        window.location.href = '?page=admin_orders&highlight=' + payload.orderId; // Highlight the updated order
                    }, 1000);
                } else {
                    showToast('error', data.error || 'Failed to update payment status.');
                    btnText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                    saveBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'An error occurred while updating the payment status.');
                btnText.classList.remove('hidden');
                spinner.classList.add('hidden');
                saveBtn.disabled = false;
            });
        });
    }
    
    // Handle payment toggle buttons (functionality not present in HTML, but if added, this would be the approach)
    // This is an example if you had direct toggle buttons in the main table:
    document.querySelectorAll('.payment-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            const currentStatus = this.dataset.currentStatus; // e.g., 'Pending'
            let newStatus;

            // Example: Toggling between 'Pending' and 'Received'
            if (this.classList.contains('mark-paid')) { // Assuming 'mark-paid' means set to 'Received'
                newStatus = 'Received';
            } else if (this.classList.contains('mark-unpaid')) { // Assuming 'mark-unpaid' means set to 'Pending'
                newStatus = 'Pending';
            } else {
                console.warn('Unknown payment toggle action');
                return;
            }

            if (newStatus === currentStatus) {
                showToast('info', 'Payment status is already ' + newStatus);
                return;
            }

            const payload = {
                orderId: orderId,
                newStatus: newStatus
            };
            
            fetch('/api/update-payment-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', data.message || 'Payment status updated.');
                    setTimeout(() => {
                        window.location.reload(); // Or update UI dynamically
                    }, 1000);
                } else {
                    showToast('error', data.error || 'Failed to update payment status.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'An error occurred while updating payment status.');
            });
        });
    });
    
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
                 window.location.href = '?page=admin_orders'; // Close by redirecting
            } else if (editModal && editModal.offsetParent !== null) { // Check if visible
                 window.location.href = '?page=admin_orders'; // Close by redirecting
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
                const cleanUrl = window.location.pathname + '?page=admin_orders';
                history.replaceState(null, '', cleanUrl);
            }, 3000);
        }
    }
});
</script>

<?php
$output = ob_get_clean();
echo $output;
?>

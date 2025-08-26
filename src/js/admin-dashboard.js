import Sortable from 'sortablejs';

// --- Dashboard Widget Reordering ---

document.addEventListener('DOMContentLoaded', () => {
    const dashboardGrid = document.getElementById('dashboardGrid');
    if (dashboardGrid) {
        new Sortable(dashboardGrid, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: async (evt) => {
                const sections = Array.from(evt.to.children);
                const payload = sections.map((section, index) => ({
                    section_key: section.dataset.sectionKey,
                    display_order: index + 1
                }));
                await saveDashboardOrder(payload);
            }
        });
    }

    // Attach event listeners for order modals
    document.body.addEventListener('click', function(event) {
        const target = event.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;
        const orderId = target.dataset.orderId;

        if (action === 'open-order-details') {
            openOrderDetailsModal(orderId);
        }
        if (action === 'close-order-details') {
            closeOrderDetailsModal();
        }
    });
});

async function saveDashboardOrder(sections) {
    try {
        const response = await fetch('/api/dashboard_sections.php?action=reorder_sections', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sections })
        });
        const result = await response.json();
        if (result.success) {
            showSaveSuccess('Dashboard order saved');
        } else {
            throw new Error(result.error || 'Failed to save dashboard order.');
        }
    } catch (error) {
        console.error('Error saving dashboard order:', error);
        // Optionally show an error message to the user
    }
}

function showSaveSuccess(message) {
    const indicator = document.createElement('div');
    indicator.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
    indicator.textContent = `✅ ${message}`;
    document.body.appendChild(indicator);
    setTimeout(() => indicator.remove(), 2000);
}


// --- Dashboard Actions ---

function refreshDashboard() {
    window.location.reload();
}

function openDashboardConfig() {
    // Navigate to the settings page, the hash will be handled by the settings script
    window.location.href = '/admin/settings#dashboard_config';
}

// Make functions globally available if called from inline HTML (though this should be refactored)
window.refreshDashboard = refreshDashboard;
window.openDashboardConfig = openDashboardConfig;


// --- Order Fulfillment Modal Logic (from widget) ---

async function openOrderDetailsModal(orderId) {
    const modal = document.getElementById('orderDetailsModal');
    if (!modal) {
        console.error('Order details modal not found');
        return;
    }

    try {
        const response = await fetch(`/api/orders/${orderId}`);
        const result = await response.json();

        if (result.success && result.order) {
            updateOrderModalContent(result.order, result.items || []);
            modal.classList.remove('hidden');
            document.body.classList.add('wf-no-scroll');
        } else {
            throw new Error(result.error || 'Failed to load order details');
        }
    } catch (error) {
        console.error('Error opening order details modal:', error);
        alert('Error: Could not load order details.');
    }
}

function closeOrderDetailsModal() {
    const modal = document.getElementById('orderDetailsModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.classList.remove('wf-no-scroll');
    }
}

function updateOrderModalContent(order, items) {
    document.getElementById('modal-order-id').textContent = order.id;
    document.getElementById('modal-customer').textContent = order.username || 'N/A';
    document.getElementById('modal-date').textContent = new Date(order.date).toLocaleDateString();
    document.getElementById('modal-total').textContent = `$${parseFloat(order.total || 0).toFixed(2)}`;
    document.getElementById('modal-status').textContent = order.order_status || 'Pending';
    document.getElementById('modal-payment-method').textContent = order.paymentMethod || 'N/A';
    document.getElementById('modal-payment-status').textContent = order.paymentStatus || 'Pending';
    document.getElementById('modal-shipping-method').textContent = order.shippingMethod || 'N/A';

    const itemsContainer = document.getElementById('modal-order-items');
    itemsContainer.innerHTML = '';
    if (items && items.length > 0) {
        items.forEach(item => {
            const itemCard = document.createElement('div');
            itemCard.className = 'order-item-card';
            itemCard.innerHTML = `
                <div class="order-item-details">
                    <div class="order-item-name">${item.item_name || item.sku}</div>
                    <div class="order-item-sku">SKU: ${item.sku}</div>
                    <div class="order-item-price">$${parseFloat(item.price || 0).toFixed(2)} &times; ${item.quantity}</div>
                </div>
                <div class="order-item-total">
                    $${(parseFloat(item.price || 0) * parseInt(item.quantity || 0)).toFixed(2)}
                </div>
            `;
            itemsContainer.appendChild(itemCard);
        });
    } else {
        itemsContainer.innerHTML = '<div class="text-gray-500 text-center">No items found</div>';
    }

    const addressElement = document.getElementById('modal-address');
    let address = '';
    if (order.addressLine1) address += order.addressLine1;
    if (order.addressLine2) address += `\n${order.addressLine2}`;
    if (order.city) address += `\n${order.city}`;
    if (order.state) address += `, ${order.state}`;
    if (order.zipCode) address += ` ${order.zipCode}`;
    addressElement.textContent = address || 'No address provided';

    document.getElementById('modal-notes').textContent = order.note || 'No notes';
    document.getElementById('modal-payment-notes').textContent = order.paynote || 'No payment notes';
}

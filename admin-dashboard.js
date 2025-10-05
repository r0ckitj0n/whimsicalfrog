import { buildAdminUrl } from '../core/admin-url-builder.js';
import '../styles/admin-dashboard.css';

// --- Dashboard Event Handlers ---

document.addEventListener('DOMContentLoaded', () => {

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

    // Delegated handler for inline order field updates in the Order Fulfillment widget
    document.body.addEventListener('change', async (event) => {
        const el = event.target.closest('.order-field-update');
        if (!el) return;

        const orderId = el.dataset.orderId;
        const fieldName = el.dataset.field;
        const newValue = el.value;

        // indicate updating via class (no inline styles)
        el.classList.add('wf-field-updating');
        el.disabled = true;

        try {
            const formData = new FormData();
            formData.append('orderId', orderId);
            formData.append('field', fieldName);
            formData.append('value', newValue);
            formData.append('action', 'updateField');

            const response = await fetch('/api/fulfill_order.php', {
                method: 'POST',
                body: formData,
            });
            const result = await response.json();

            if (result && result.success) {
                el.classList.remove('wf-field-updating');
                el.classList.add('wf-field-success');
                setTimeout(() => {
                    el.classList.remove('wf-field-success');
                }, 2000);
            } else {
                el.classList.remove('wf-field-updating');
                el.classList.add('wf-field-error');
                setTimeout(() => {
                    el.classList.remove('wf-field-error');
                }, 2000);
            }
        } catch (err) {
            el.classList.remove('wf-field-updating');
            el.classList.add('wf-field-error');
            setTimeout(() => {
                el.classList.remove('wf-field-error');
            }, 2000);
        } finally {
            el.disabled = false;
        }
    });
});


// --- Dashboard Actions ---

function refreshDashboard() {
    window.location.reload();
}

function openDashboardConfig() {
    // Navigate to the settings page, the hash will be handled by the settings script
    window.location.href = buildAdminUrl('settings') + '#dashboard_config';
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

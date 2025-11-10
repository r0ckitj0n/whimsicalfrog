import { buildAdminUrl } from '../core/admin-url-builder.js';
import { ApiClient } from '../core/api-client.js';
import '../styles/admin-dashboard.css';
import '../styles/admin-inventory.css';

// --- Dashboard Event Handlers ---

function initAdminDashboardHandlers() {
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

    // Click-to-edit for inline dashboard Order Fulfillment cells
    const activateEditableCell = (cell) => {
        if (!cell || cell.dataset.editing === '1') return false;
        const orderId = cell.dataset.orderId;
        const field = cell.dataset.field;
        const type = (cell.dataset.type || 'select').toLowerCase();
        if (!orderId || !field) return false;

        const rawValue = (cell.dataset.rawValue != null) ? String(cell.dataset.rawValue) : (cell.textContent || '').trim();
        const optionsByField = {
            order_status: ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'],
            paymentStatus: ['Pending', 'Received', 'Refunded', 'Failed'],
            paymentMethod: ['Credit Card', 'Cash', 'Check', 'PayPal', 'Venmo', 'Other'],
            shippingMethod: ['Customer Pickup', 'Local Delivery', 'USPS', 'FedEx', 'UPS'],
        };

        let editor;
        if (type === 'date') {
            editor = document.createElement('input');
            editor.type = 'date';
            editor.value = rawValue || '';
        } else {
            const sel = document.createElement('select');
            const list = optionsByField[field] || [];
            if (field === 'paymentMethod' || field === 'shippingMethod') {
                const opt = document.createElement('option'); opt.value = ''; opt.textContent = 'Select…'; sel.appendChild(opt);
            }
            list.forEach(v => { const opt = document.createElement('option'); opt.value = v; opt.textContent = v; sel.appendChild(opt); });
            sel.value = rawValue || sel.value;
            editor = sel;
        }
        editor.className = 'order-field-update text-xs border border-gray-300 rounded px-1 py-0.5';
        editor.dataset.field = field;
        editor.dataset.orderId = orderId;

        const prevText = (cell.textContent || '').trim();
        cell.dataset.prevText = prevText;
        cell.dataset.editing = '1';
        cell.innerHTML = '';
        cell.appendChild(editor);
        try { editor.focus(); } catch(_) {}
        editor.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                cell.textContent = prevText;
                delete cell.dataset.editing;
            }
        });
        editor.addEventListener('blur', () => {
            setTimeout(() => {
                if (cell && cell.dataset && cell.dataset.editing === '1') {
                    cell.textContent = prevText;
                    delete cell.dataset.editing;
                }
            }, 180);
        }, { once: true });
        return true;
    };

    const findEditableCell = (event) => {
        let t = event.target;
        try { if (t && t.nodeType === 3) t = t.parentElement; } catch(_) {}
        let cell = null;
        try { if (t && t.closest) cell = t.closest('.editable-field'); } catch(_) {}
        if (!cell && event.composedPath) {
            try { cell = event.composedPath().find(el => el && el.classList && el.classList.contains('editable-field')) || null; } catch(_) {}
        }
        return cell;
    };

    document.body.addEventListener('click', (event) => {
        const cell = findEditableCell(event);
        if (!cell) return;
        if (activateEditableCell(cell)) { event.preventDefault(); }
    });
    document.body.addEventListener('dblclick', (event) => {
        const cell = findEditableCell(event);
        if (!cell) return;
        if (activateEditableCell(cell)) { event.preventDefault(); }
    });
    document.body.addEventListener('keydown', (event) => {
        const cell = findEditableCell(event);
        if (!cell) return;
        if ((event.key === 'Enter' || event.key === ' ') && !cell.dataset.editing) {
            if (activateEditableCell(cell)) { event.preventDefault(); }
        }
    });

    // Delegated handler for inline order field updates in the Order Fulfillment widget
    document.body.addEventListener('change', async (event) => {
        const el = event.target.closest('.order-field-update');
        if (!el) return;

        let orderId = el.dataset.orderId;
        const fieldName = el.dataset.field;
        const newValue = el.value;

        if (!orderId) {
            try {
                const idEl = document.getElementById('modal-order-id');
                if (idEl) orderId = (idEl.textContent || '').replace('#','').trim();
            } catch (_) {}
        }
        if (!orderId || !fieldName) {
            try { console.warn('[Order Inline Update] Missing orderId or fieldName; aborting', { orderId, fieldName }); } catch(_) {}
            return;
        }

        // indicate updating via class (no inline styles)
        el.classList.add('wf-field-updating');
        el.disabled = true;
        // Also mark wrapper as busy for consistent styling with inventory/orders
        let wrap = null;
        try { wrap = el.closest('.editable, .editable-field'); if (wrap) wrap.classList.add('is-busy'); } catch(_) {}

        try {
            const formData = new FormData();
            formData.append('orderId', orderId);
            formData.append('field', fieldName);
            formData.append('value', newValue);
            formData.append('action', 'updateField');

            const result = await ApiClient.upload('/api/fulfill_order.php', formData);

            if (result && result.success) {
                el.classList.remove('wf-field-updating');
                el.classList.add('wf-field-success');
                setTimeout(() => {
                    el.classList.remove('wf-field-success');
                }, 2000);
                // If editing inside a cell, render new display text and exit edit mode
                const cell = el.closest('.editable-field');
                if (cell) {
                    const f = (el.dataset.field || '').toString();
                    const val = el.value || '';
                    let disp = val;
                    if (f === 'paymentDate' || f === 'date') {
                        // Convert YYYY-MM-DD to 'Mon D, YYYY'
                        try {
                            if (val) {
                                const d = new Date(val + 'T00:00:00');
                                const fmt = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
                                disp = fmt;
                                cell.dataset.rawValue = val;
                            } else {
                                disp = '—';
                                cell.dataset.rawValue = '';
                            }
                        } catch(_) {}
                    } else if (!val) {
                        disp = '—';
                    }
                    cell.textContent = disp;
                    delete cell.dataset.editing;
                }
            } else {
                el.classList.remove('wf-field-updating');
                el.classList.add('wf-field-error');
                setTimeout(() => {
                    el.classList.remove('wf-field-error');
                }, 2000);
                // Revert cell display on error
                const cell = el.closest('.editable-field');
                if (cell) {
                    const prev = cell.dataset.prevText || '';
                    cell.textContent = prev;
                    delete cell.dataset.editing;
                }
            }
        } catch (err) {
            el.classList.remove('wf-field-updating');
            el.classList.add('wf-field-error');
            setTimeout(() => {
                el.classList.remove('wf-field-error');
            }, 2000);
            const cell = el.closest('.editable-field');
            if (cell) {
                const prev = cell.dataset.prevText || '';
                cell.textContent = prev;
                delete cell.dataset.editing;
            }
        } finally {
            el.disabled = false;
            try { if (wrap) wrap.classList.remove('is-busy'); } catch(_) {}
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminDashboardHandlers, { once: true });
} else {
    initAdminDashboardHandlers();
}


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
        const result = await ApiClient.get('/api/get_order.php', { id: orderId, _: Date.now() });

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
    const dateInput = document.getElementById('modal-date');
    if (dateInput) {
        try { dateInput.value = (order.date ? new Date(order.date) : new Date()).toISOString().slice(0,10); } catch(_) {}
        dateInput.dataset.orderId = order.id;
    }
    document.getElementById('modal-total').textContent = `$${parseFloat(order.total || 0).toFixed(2)}`;

    const setSelect = (id, value) => {
        const sel = document.getElementById(id);
        if (!sel) return;
        sel.dataset.orderId = order.id;
        if (value && !Array.from(sel.options).some(o => o.value === value)) {
            const opt = document.createElement('option');
            opt.value = value; opt.textContent = value; sel.appendChild(opt);
        }
        sel.value = value || '';
    };
    setSelect('modal-order-status', order.order_status || 'Pending');
    setSelect('modal-payment-method', order.paymentMethod || '');
    setSelect('modal-payment-status', order.paymentStatus || 'Pending');
    setSelect('modal-shipping-method', order.shippingMethod || '');

    const payDate = document.getElementById('modal-payment-date');
    if (payDate) {
        try { payDate.value = (order.paymentDate ? new Date(order.paymentDate) : null) ? new Date(order.paymentDate).toISOString().slice(0,10) : ''; } catch(_) {}
        payDate.dataset.orderId = order.id;
    }

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

    const updatables = document.querySelectorAll('#orderDetailsModal .order-field-update');
    updatables.forEach(el => { try { el.dataset.orderId = order.id; } catch(_) {} });
}

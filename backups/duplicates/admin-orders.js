import { buildAdminUrl } from '../core/admin-url-builder.js';
import '../styles/admin-modals.css';
import '../styles/admin/admin-legacy-modals.css';
console.log('[AdminOrders] module evaluated');

class AdminOrdersModule {
    constructor() {
        this.orderData = {};
        this.allItems = [];
        this.modalMode = null;
        this.currentOrderId = null;

        this.loadData();
        this.bindEvents();
        // If DOM is already loaded (likely on dynamic import), initialize immediately
        if (document.readyState !== 'loading') {
            this.initInlineEditing();
            this.ensureOrderModalVisible();
            this.ensureOrderOverlayVisible();
        }
    }

    loadData() {
        const dataElement = document.getElementById('order-page-data');
        if (dataElement) {
            const data = JSON.parse(dataElement.textContent);
            this.orderData = data.orderData || {};
            this.allItems = data.allItems || [];
            this.modalMode = data.modalMode;
            this.currentOrderId = data.currentOrderId;
        }
    }

    bindEvents() {
        document.body.addEventListener('click', this.handleDelegatedClick.bind(this));

        // Handle edit/view links within the same page context
        document.addEventListener('click', (e) => {
            const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;
            const href = a.getAttribute('href');
            if (!href) return;

            // Check if this is an edit/view link for the current page
            try {
                const url = new URL(href, window.location.origin);
                const orderId = url.searchParams.get('view') || url.searchParams.get('edit');

                if (orderId && (url.searchParams.get('view') || url.searchParams.get('edit'))) {
                    // This is an edit/view link - handle it within the same page context
                    e.preventDefault();

                    // Update the page data to trigger modal display
                    const pageData = document.getElementById('order-page-data');
                    if (pageData) {
                        try {
                            const data = JSON.parse(pageData.textContent);
                            const action = url.searchParams.get('view') ? 'view' : 'edit';
                            data.currentOrderId = orderId;
                            data.modalMode = action;
                            pageData.textContent = JSON.stringify(data);

                            // Update module state
                            this.currentOrderId = orderId;
                            this.modalMode = action;

                            // NEW: reflect action in the URL so fallback fetch will work
                            try {
                              const newUrl = new URL(window.location.href);
                              newUrl.searchParams.delete('view');
                              newUrl.searchParams.delete('edit');
                              newUrl.searchParams.set(action, orderId);
                              history.replaceState(null, '', newUrl);
                            } catch(_) {}

                            this.ensureOrderModalVisible();
                        } catch (error) {
                            console.error('Error updating order page data:', error);
                            // Fallback to navigation
                            window.location.href = href;
                        }
                    } else {
                        // Fallback to navigation if no page data
                        window.location.href = href;
                    }
                    return;
                }
            } catch (_) {
                // Not a URL we care about, continue normal navigation
            }
        }, true);

        // ESC-to-close when overlay is present
        document.addEventListener('keydown', (e) => {
            const overlay = document.getElementById('orderModal');
            if (!overlay) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                window.location.href = buildAdminUrl('orders');
            }
        });
        const run = () => {
            this.initInlineEditing();
            this.ensureOrderModalVisible();
            this.ensureOrderOverlayVisible();
            // Debug visibility: log modal state and presence
            try {
                const el = document.getElementById('orderModal');
                console.log('[AdminOrders] modalMode=', this.modalMode, 'has#orderModal=', !!el);
            } catch(_) {}
            // Fallback: observe for #orderModal insertion and show it if needed
            try {
                const mo = new MutationObserver(() => {
                    const el = document.getElementById('orderModal');
                    if (el && this.modalMode && !el.classList.contains('show')) {
                        this.showModal(el);
                        console.log('[AdminOrders] Observed #orderModal insertion; applied show');
                    }
                });
                mo.observe(document.body, { childList: true, subtree: true });
                this.__ordersMo = mo;
            } catch(_) {}
        };
        if (document.readyState !== 'loading') run(); else document.addEventListener('DOMContentLoaded', run, { once: true });
    }

    // Small helpers to normalize overlay visibility across CSS guards
    showModal(el) {
        try { el.classList.remove('hidden'); } catch(_) {}
        try { el.classList.add('show'); } catch(_) {}
    }

    hideModal(el) {
        try { el.classList.add('hidden'); } catch(_) {}
        try { el.classList.remove('show'); } catch(_) {}
    }

    handleDelegatedClick(event) {
        const actionTarget = event.target.closest('[data-action]');
        if (!actionTarget) return;

        const action = actionTarget.dataset.action;

        switch (action) {
            case 'confirm-delete':
                this.confirmDelete(actionTarget.dataset.orderId);
                break;
            case 'close-delete-modal':
                this.closeDeleteModal();
                break;
            case 'delete-order':
                this.deleteOrder();
                break;
            case 'show-receipt':
                this.showReceipt(actionTarget.dataset.orderId);
                break;
            case 'close-receipt-modal':
                this.closeReceiptModal();
                break;
            case 'print-receipt':
                this.printReceipt();
                break;
            case 'show-add-item-modal':
                this.showAddItemModal();
                break;
            case 'add-item-to-order':
                this.addItemToOrder(actionTarget.dataset.sku, actionTarget.dataset.name, actionTarget.dataset.price);
                break;
            case 'remove-item-from-order':
                this.removeItemFromOrder(actionTarget.dataset.itemId);
                break;
            case 'show-address-selector':
                this.showAddressSelectorModal();
                break;
            case 'show-add-address-modal':
                this.showAddAddressModal();
                break;
            case 'edit-current-address':
                this.showEditAddressModal();
                break;
            case 'save-address':
                this.saveAddress(event);
                break;
            case 'select-address':
                this.selectAddress(actionTarget.dataset.addressId);
                break;
            case 'close-address-modal':
                this.closeAddressModal();
                break;
            case 'close-order-editor-on-overlay': {
                const overlay = document.getElementById('orderModal');
                if (overlay && event.target === overlay) {
                    event.preventDefault();
                    window.location.href = buildAdminUrl('orders');
                }
                break;
            }
            case 'close-order-editor': {
                event.preventDefault();
                window.location.href = buildAdminUrl('orders');
                break;
            }
            case 'save-order': {
                event.preventDefault();
                const form = document.getElementById('orderForm');
                if (!form) break;
                const fd = new FormData(form);
                const id = (fd.get('orderId') || fd.get('id') || '').toString();
                window.ApiClient.request('/functions/process_order_update.php', { method: 'POST', body: fd })
                    .then(r => r.json().catch(() => ({})))
                    .then(data => {
                        if (data && data.success) {
                            try { window.showNotification && window.showNotification('Order saved', 'success', { title: 'Saved' }); } catch(_) {}
                            const targetId = (data.id || id || '').toString();
                            window.location.href = buildAdminUrl('orders', { edit: targetId, highlight: targetId });
                        } else {
                            const msg = (data && (data.error || data.message)) || 'Failed to save order';
                            try { window.showNotification && window.showNotification(msg, 'error', { title: 'Save failed' }); } catch(_) {}
                        }
                    })
                    .catch(() => { try { window.showNotification && window.showNotification('Network error', 'error', { title: 'Save failed' }); } catch(_) {} });
                break;
            }
        }
    }

    ensureOrderModalVisible() {
        try { console.log('[AdminOrders] ensureOrderModalVisible start'); } catch(_) {}
        const orderModal = document.getElementById('orderModal');
        const params = new URLSearchParams(window.location.search || '');
        const requestedIdFromUrl = params.get('view') || params.get('edit');
        const requestedId = requestedIdFromUrl || this.currentOrderId;
        const action = params.get('view') ? 'view'
                     : params.get('edit') ? 'edit'
                     : this.modalMode || null;

        // If modal exists, show it
        if ((this.modalMode || requestedId) && orderModal) {
            this.showModal(orderModal);
            try { console.log('[AdminOrders] showing existing #orderModal'); } catch(_) {}
            return;
        }

        // Fallback: fetch and inject #orderModal using URL or module state
        if (requestedId && !orderModal && action) {
            try {
                const url = new URL(window.location.href);
                url.searchParams.delete('view');
                url.searchParams.delete('edit');
                url.searchParams.set(action, requestedId);

                window.ApiClient.request(url.toString(), { method: 'GET' })
                    .then(r => r.text())
                    .then(html => {
                        const tmp = document.createElement('div');
                        tmp.innerHTML = html;
                        const el = tmp.querySelector('#orderModal');
                        if (el) {
                            document.body.appendChild(el);
                            this.showModal(el);
                            console.log('[AdminOrders] Injected #orderModal from fetched HTML');
                        } else {
                            console.warn('[AdminOrders] Fallback fetch did not contain #orderModal');
                        }
                    })
                    .catch(() => {});
            } catch (_) {}
        }
    }

    confirmDelete(orderId) {
        const modal = document.getElementById('deleteModal');
        const orderIdTarget = document.getElementById('deleteOrderId');
        if (orderIdTarget) orderIdTarget.textContent = String(orderId || '');
        if (modal) this.showModal(modal);
    }

    closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        if (modal) this.hideModal(modal);
    }

    async showReceipt(orderId) {
        const modal = document.getElementById('receiptModal');
        const content = document.getElementById('receiptContent');
        if (!modal || !content) return;
        // Remember the current order id for print action
        try { this.currentOrderId = orderId; } catch(_) {}
        
        content.innerHTML = '<div class="p-4">Loading...</div>';
        this.showModal(modal);

        // Use an iframe to guarantee identical layout and CSS isolation as checkout receipt
        const url = `/receipt?orderId=${encodeURIComponent(orderId)}&bare=1`;
        const iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.title = 'Receipt';
        iframe.className = 'receipt-iframe';
        iframe.loading = 'eager';
        iframe.addEventListener('load', () => {
            content.innerHTML = '';
            content.appendChild(iframe);
        }, { once: true });
        // In case load fails, still swap content after a timeout
        setTimeout(() => {
            if (!content.contains(iframe)) {
                content.innerHTML = '';
                content.appendChild(iframe);
            }
        }, 800);
    }

    closeReceiptModal() {
        const modal = document.getElementById('receiptModal');
        if (modal) this.hideModal(modal);
    }

    printReceipt() {
        const id = this.currentOrderId ? String(this.currentOrderId) : '';
        if (id) {
            const url = `/receipt?orderId=${encodeURIComponent(id)}&bare=1`;
            try { window.open(url, '_blank', 'noopener'); } catch(_) { window.location.href = url; }
            return;
        }
        // Fallback: print currently injected content (legacy behavior)
        const content = document.getElementById('receiptContent');
        if (content) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`<html><head><title>Receipt</title></head><body>${content.innerHTML}</body></html>`);
            printWindow.print();
        }
    }

    async deleteOrder() {
        const orderIdText = (document.getElementById('deleteOrderId') || {}).textContent || '';
        const id = orderIdText.trim();
        if (!id) return;
        // TODO: Implement actual delete endpoint; for now log and close modal
        console.log('Requesting delete for order', id);
        this.closeDeleteModal();
        // Optionally trigger a reload or navigate to a server-side delete handler
    }

    showAddItemModal() {
        const modal = document.getElementById('addItemModal');
        if (modal) {
            this.showModal(modal);
        }
    }

    async addItemToOrder(sku, name, _price) {
        const quantity = prompt(`Enter quantity for ${name}:`, '1');
        if (!quantity || isNaN(quantity) || Number(quantity) <= 0) {
            return;
        }

        // Logic to add item via AJAX and update UI
        console.log(`Adding ${quantity} of ${sku} to order ${this.currentOrderId}`);
        // This would involve an API call and then a UI refresh/update.
        // For now, we can reload the page to see the change.
        alert('Item adding logic not fully implemented in JS module yet. Reload to see changes if backend is updated.');

    }

    async removeItemFromOrder(itemId) {
        if (!confirm('Are you sure you want to remove this item?')) return;

        // Logic to remove item via AJAX and update UI
        console.log(`Removing item ${itemId} from order ${this.currentOrderId}`);
        // This would involve an API call and then a UI refresh/update.
        // For now, we can reload the page to see the change.
        alert('Item removal logic not fully implemented in JS module yet. Reload to see changes if backend is updated.');
    }

    impersonateCustomer(userId) {
        if (!confirm('Are you sure you want to impersonate this customer?')) return;
        window.location.href = `/?impersonate=${userId}`; // Note: impersonate may need to remain as query param
    }

    initInlineEditing() {
        // Placeholder for the complex inline editing logic
        console.log('Inline editing initialization logic goes here.');
    }

    // Normalize visibility for the new component-based overlay
    ensureOrderOverlayVisible() {
        try {
            const overlay = document.getElementById('orderModal');
            if (!overlay) return;
            overlay.classList.remove('hidden');
            overlay.classList.add('show', 'topmost');
            try { document.documentElement.classList.add('modal-open'); } catch(_) {}
            try { document.body.classList.add('modal-open'); } catch(_) {}
            // Ensure the panel is visibly active and trap focus inside for accessibility
            try {
                const panel = overlay.querySelector('.admin-modal');
                if (panel) {
                    panel.classList.add('wf-admin-panel-visible');
                    if (!overlay.__wfTrapInstalled) {
                        overlay.__wfTrapInstalled = true;
                        const getFocusable = () => Array.from(panel.querySelectorAll('a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])'))
                            .filter(el => !el.hasAttribute('disabled') && el.getAttribute('aria-hidden') !== 'true');
                        const firstEl = getFocusable()[0];
                        if (firstEl && typeof firstEl.focus === 'function') setTimeout(() => firstEl.focus(), 0);
                        panel.addEventListener('keydown', (ev) => {
                            if (ev.key !== 'Tab') return;
                            const focusables = getFocusable();
                            if (focusables.length === 0) return;
                            const first = focusables[0];
                            const last = focusables[focusables.length - 1];
                            if (ev.shiftKey) {
                                if (document.activeElement === first) { ev.preventDefault(); last.focus(); }
                            } else {
                                if (document.activeElement === last) { ev.preventDefault(); first.focus(); }
                            }
                        });
                    }
                }
            } catch(_) {}
        } catch(_) {}
        // Observe late insertions
        try {
            if (this.__orderOverlayMo) return;
            const mo = new MutationObserver(() => {
                const overlay = document.getElementById('orderModal');
                if (overlay && !overlay.__wfShown) {
                    overlay.classList.remove('hidden');
                    overlay.classList.add('show', 'topmost');
                    try {
                        const panel = overlay.querySelector('.admin-modal');
                        if (panel) panel.classList.add('wf-admin-panel-visible');
                    } catch(_) {}
                    overlay.__wfShown = true;
                }
            });
            mo.observe(document.body, { childList: true, subtree: true });
            this.__orderOverlayMo = mo;
        } catch(_) {}
    }
}

new AdminOrdersModule();

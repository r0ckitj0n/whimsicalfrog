import { buildAdminUrl } from '../core/admin-url-builder.js';
import { ApiClient } from '../core/api-client.js';
import '../styles/admin-modals.css';
import '../styles/admin/admin-legacy-modals.css';
console.log('[AdminOrders] module evaluated');

class AdminOrdersModule {
    constructor() {
        this.orderData = {};
        this.allItems = [];
        this.modalMode = null;
        this.currentOrderId = null;
        // Lightweight HTML cache for modal partials
        this.__modalCache = new Map(); // key: `${action}:${id}` -> { html, ts }
        this.__modalPrefetching = new Map(); // key -> Promise in flight
        this.__prefetchHoverTimers = new Map(); // anchor -> timer id
        // Signal readiness early to prevent inline fallback from attaching duplicate handlers
        try { window.__WF_ADMIN_ORDERS_READY = true; } catch(_) {}
        try { window.dispatchEvent(new CustomEvent('wf-admin-orders-ready')); } catch(_) {}

        this.loadData();
        this.bindEvents();
        // If inline fallback installed earlier, detach it now
        try { if (typeof window.__WF_ORDERS_INLINE_CLEANUP === 'function') { window.__WF_ORDERS_INLINE_CLEANUP(); } } catch(_) {}
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

        // Prefetch modal partial on hover over view/edit links (debounced)
        try {
            const onOver = (e) => {
                const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
                if (!a) return;
                const parsed = this.parseActionFromHref(a.getAttribute('href'));
                if (!parsed) return;
                const key = `${parsed.action}:${parsed.id}`;
                if (this.__modalCache.has(key) || this.__modalPrefetching.has(key)) return;
                // Debounce per-anchor
                if (this.__prefetchHoverTimers.get(a)) return;
                const t = setTimeout(() => {
                    this.__prefetchHoverTimers.delete(a);
                    this.prefetchOrderModal(parsed.action, parsed.id).catch(() => {});
                }, 120);
                this.__prefetchHoverTimers.set(a, t);
            };
            const onOut = (e) => {
                const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
                if (!a) return;
                const t = this.__prefetchHoverTimers.get(a);
                if (t) { clearTimeout(t); this.__prefetchHoverTimers.delete(a); }
            };
            document.addEventListener('mouseover', onOver, { passive: true });
            document.addEventListener('mouseout', onOut, { passive: true });
        } catch(_) {}

        // Idle-time prefetch for first visible order link
        try {
            const run = () => {
                try {
                    const a = document.querySelector('a[href*="?view="], a[href*="?edit="]');
                    if (!a) return;
                    const parsed = this.parseActionFromHref(a.getAttribute('href'));
                    if (parsed) this.prefetchOrderModal(parsed.action, parsed.id).catch(() => {});
                } catch(_) {}
            };
            if ('requestIdleCallback' in window) {
                // @ts-ignore
                window.requestIdleCallback(() => run(), { timeout: 1000 });
            } else {
                setTimeout(run, 400);
            }
        } catch(_) {}

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
                if (this.__ordersMo) return; // guard against duplicate observers
                const mo = new MutationObserver(() => {
                    const el = document.getElementById('orderModal');
                    if (el && this.modalMode && !el.classList.contains('show')) {
                        this.showModal(el);
                        console.log('[AdminOrders] Observed #orderModal insertion; applied show');
                        try { mo.disconnect(); } catch(_) {}
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

    // Ensure the overlay sits above header, is centered, and body is locked
    ensureOrderOverlayVisible() {
        try { this.normalizeOrderOverlay(); } catch(_) {}
    }

    normalizeOrderOverlay() {
        try {
            const el = document.getElementById('orderModal');
            if (!el) return;
            // Unhide and ensure visibility classes
            try { el.classList.remove('hidden'); } catch(_) {}
            try { el.classList.add('show'); } catch(_) {}
            // Make sure it's treated as an admin overlay and marked to bypass header offset
            el.classList.add('admin-modal-overlay');
            el.classList.add('over-header');
            el.classList.add('topmost');
            // Classes control centering and stacking (no inline styles per repo guard)
            // Upgrade panel wrapper if needed
            const panel = el.querySelector('.admin-modal, .modal-content, .modal');
            if (panel) {
                try { panel.classList.add('admin-modal'); } catch(_) {}
                try { panel.classList.add('wf-admin-panel-visible'); } catch(_) {}
                try { panel.classList.add('show'); } catch(_) {}
                // Panel layout is governed by CSS classes; no inline styles
            }
            // Lock scroll behind overlay
            try { document.documentElement.classList.add('modal-open'); } catch(_) {}
            try { document.body.classList.add('modal-open'); } catch(_) {}
        } catch(_) {}
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
                // Close only when clicking the true backdrop (overlay itself) or when clicking outside the panel,
                // but ignore clicks on overlay-level controls like nav arrows.
                const clickedNavArrow = !!(event.target && event.target.closest && event.target.closest('.nav-arrow'));
                const clickedInsidePanel = !!(event.target && event.target.closest && event.target.closest('.admin-modal'));
                if (overlay && !clickedNavArrow && (!clickedInsidePanel || event.target === overlay)) {
                    event.preventDefault();
                    try { this.__modalAbortCtrl && this.__modalAbortCtrl.abort(); } catch(_) {}
                    try { window.__WF_BLOCK_TOOLTIP_ATTACH = false; } catch(_) {}
                    window.location.href = buildAdminUrl('orders');
                }
                break;
            }
            case 'close-order-editor': {
                event.preventDefault();
                try { this.__modalAbortCtrl && this.__modalAbortCtrl.abort(); } catch(_) {}
                try { window.__WF_BLOCK_TOOLTIP_ATTACH = false; } catch(_) {}
                window.location.href = buildAdminUrl('orders');
                break;
            }
            case 'save-order': {
                event.preventDefault();
                const form = document.getElementById('orderForm');
                if (!form) break;
                const fd = new FormData(form);
                const id = (fd.get('orderId') || fd.get('id') || '').toString();
                ApiClient.request('/functions/process_order_update.php', { method: 'POST', body: fd })
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

        // Fallback: fetch (or use cache) and inject #orderModal using a lightweight partial
        if (requestedId && !orderModal && action) {
            if (this.__loadingModal) {
                try { console.debug('[AdminOrders] modal load already in-flight, skipping'); } catch(_) {}
                return;
            }
            this.__loadingModal = true;
            try {
                // Temporarily block tooltip attachments to avoid heavy DOM work during modal load
                try { window.__WF_BLOCK_TOOLTIP_ATTACH = true; } catch(_) {}
                // Show a temporary lightweight overlay while loading
                const skeleton = document.createElement('div');
                skeleton.id = 'orderModal';
                skeleton.className = 'admin-modal-overlay topmost over-header order-modal show';
                try { skeleton.setAttribute('data-action', 'close-order-editor-on-overlay'); } catch(_) {}
                skeleton.innerHTML = `
                  <div class="admin-modal admin-modal-content wf-admin-panel-visible show">
                    <div class="modal-header">
                      <h2 class="modal-title">Loading order…</h2>
                      <a href="/admin/orders" class="modal-close" data-action="close-order-editor" aria-label="Close">×</a>
                    </div>
                    <div class="modal-body">
                      <div class="p-4">Please wait…</div>
                    </div>
                  </div>`;
                document.body.appendChild(skeleton);
                try { this.normalizeOrderOverlay(); } catch(_) {}

                const url = new URL(window.location.href);
                url.searchParams.delete('view');
                url.searchParams.delete('edit');
                url.searchParams.set(action, requestedId);
                url.searchParams.set('wf_partial', 'order_modal');

                // Use cache if available
                const key = `${action}:${requestedId}`;
                const cached = this.__modalCache.get(key);
                if (cached && typeof cached.html === 'string' && cached.html.length) {
                    const tmp = document.createElement('div');
                    tmp.innerHTML = cached.html;
                    const el = tmp.querySelector('#orderModal');
                    if (el) {
                        try { skeleton.remove(); } catch(_) { skeleton.classList.add('hidden'); }
                        document.body.appendChild(el);
                        try { this.normalizeOrderOverlay(); } catch(_) {}
                        this.showModal(el);
                        console.log('[AdminOrders] Injected #orderModal from cache');
                        this.__loadingModal = false;
                        try { window.__WF_BLOCK_TOOLTIP_ATTACH = false; window.__wfDebugTooltips && window.__wfDebugTooltips(); } catch(_) {}
                        return;
                    }
                }

                // Abortable fetch with timeout to avoid hangs
                const ctrl = new AbortController();
                this.__modalAbortCtrl && this.__modalAbortCtrl.abort();
                this.__modalAbortCtrl = ctrl;
                const timeout = setTimeout(() => { try { ctrl.abort(); } catch(_) {} }, 5000);

                fetch(url.toString(), { credentials: 'include', signal: ctrl.signal })
                  .then(r => r.text())
                  .then(html => {
                    // Cache trimmed response
                    try { this.setModalCache(key, html); } catch(_) {}
                    const tmp = document.createElement('div');
                    tmp.innerHTML = html;
                    const el = tmp.querySelector('#orderModal');
                    if (el) {
                        try { skeleton.remove(); } catch(_) { skeleton.classList.add('hidden'); }
                        document.body.appendChild(el);
                        try { this.normalizeOrderOverlay(); } catch(_) {}
                        this.showModal(el);
                        console.log('[AdminOrders] Injected #orderModal from partial');
                    } else {
                        console.warn('[AdminOrders] Partial did not contain #orderModal');
                    }
                  })
                  .catch((e) => {
                    if (e && e.name === 'AbortError') {
                      console.warn('[AdminOrders] modal fetch aborted');
                    } else {
                      console.warn('[AdminOrders] modal fetch error', e);
                    }
                  })
                  .finally(() => {
                    clearTimeout(timeout);
                    this.__loadingModal = false;
                    this.__modalAbortCtrl = null;
                    // Re-enable tooltip attachments and schedule a light reattach
                    try { window.__WF_BLOCK_TOOLTIP_ATTACH = false; window.__wfDebugTooltips && window.__wfDebugTooltips(); } catch(_) {}
                  });
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
        const url = `/receipt.php?orderId=${encodeURIComponent(orderId)}&bare=1`;
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
            const url = `/receipt.php?orderId=${encodeURIComponent(id)}&bare=1`;
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
        try {
            const res = await fetch(`/api/delete_order.php?orderId=${encodeURIComponent(id)}`, {
                method: 'DELETE',
                credentials: 'include'
            });
            let data = {};
            try { data = await res.json(); } catch (_) {}
            this.closeDeleteModal();
            if (res.ok && (data && data.success)) {
                try { window.showNotification && window.showNotification('Order deleted', 'success'); } catch(_) {}
                window.location.href = '/admin/orders';
            } else {
                const msg = (data && (data.error || data.message)) || 'Failed to delete order';
                try { window.showNotification && window.showNotification(msg, 'error'); } catch(_) {}
            }
        } catch (e) {
            this.closeDeleteModal();
            try { window.showNotification && window.showNotification('Network error', 'error'); } catch(_) {}
        }
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
        if (this.__inlineEditingInitialized) return;
        this.__inlineEditingInitialized = true;

        const getStatusBadgeClass = (status) => {
            const s = String(status || '').toLowerCase();
            switch (s) {
                case 'pending': return 'badge-status-pending';
                case 'processing': return 'badge-status-processing';
                case 'shipped': return 'badge-status-shipped';
                case 'delivered': return 'badge-status-delivered';
                case 'cancelled': return 'badge-status-cancelled';
                default: return 'badge-status-default';
            }
        };
        const getPaymentStatusBadgeClass = (status) => {
            const s = String(status || '').toLowerCase();
            switch (s) {
                case 'pending': return 'badge-payment-pending';
                case 'received': return 'badge-payment-received';
                case 'processing': return 'badge-payment-processing';
                case 'refunded': return 'badge-payment-refunded';
                case 'failed': return 'badge-payment-failed';
                default: return 'badge-payment-default';
            }
        };

        // Extract options from the filter dropdowns (authoritative for this page)
        const getOptionsFromFilter = (nameAttr) => {
            const sel = document.querySelector(`form.admin-filter-form select[name="${nameAttr}"]`);
            if (!sel) return [];
            return Array.from(sel.options)
                .map(o => o.value)
                .filter(v => v !== '');
        };

        const ORDER_STATUS_OPTS = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
        const PAYMENT_METHOD_OPTS = getOptionsFromFilter('filter_payment_method');
        const SHIPPING_METHOD_OPTS = getOptionsFromFilter('filter_shipping_method');
        const PAYMENT_STATUS_OPTS = getOptionsFromFilter('filter_payment_status').length
            ? getOptionsFromFilter('filter_payment_status')
            : ['Pending', 'Received', 'Processing', 'Refunded', 'Failed'];

        const fieldToApiKey = (field) => {
            // Map DB/UI field names to API payload keys expected by /api/update_order.php
            switch (field) {
                case 'order_status': return 'status';
                case 'paymentMethod': return 'paymentMethod';
                case 'shippingMethod': return 'shippingMethod';
                case 'paymentStatus': return 'paymentStatus';
                case 'date': return 'date'; // now supported by API
                default: return field; // fallback
            }
        };

        const buildEditor = (cell) => {
            const type = cell.getAttribute('data-type') || 'text';
            const field = cell.getAttribute('data-field') || '';
            const originalHtml = cell.innerHTML;
            const originalText = cell.textContent.trim();
            const rawValue = cell.getAttribute('data-raw-value');

            // Prevent double-editors
            if (cell.__editing) return;
            cell.__editing = true;
            const cleanup = (restore = false) => {
                if (restore) cell.innerHTML = originalHtml;
                cell.__editing = false;
            };

            const commit = async (newValue) => {
                const orderId = cell.getAttribute('data-order-id');
                const apiKey = fieldToApiKey(field);
                if (!orderId || !apiKey) {
                    cleanup(true);
                    if (!apiKey && field === 'date') {
                        try { window.showNotification && window.showNotification('Editing date inline is not supported yet', 'info'); } catch(_) {}
                    }
                    return;
                }

                const payload = { orderId: String(orderId) };
                payload[apiKey] = newValue;

                try {
                    const data = await ApiClient.post('/api/update_order.php', payload);
                    if (data && (data.success || data.orderId)) {
                        // Update UI
                        if (field === 'order_status') {
                            const span = cell.querySelector('.status-badge') || document.createElement('span');
                            span.className = `status-badge ${getStatusBadgeClass(newValue)}`;
                            span.textContent = newValue;
                            cell.innerHTML = '';
                            cell.appendChild(span);
                        } else if (field === 'paymentStatus') {
                            const span = cell.querySelector('.payment-status-badge') || document.createElement('span');
                            span.className = `payment-status-badge ${getPaymentStatusBadgeClass(newValue)}`;
                            span.textContent = newValue;
                            cell.innerHTML = '';
                            cell.appendChild(span);
                        } else if (field === 'date') {
                            // Persist raw ISO value if user provided only date
                            // Accept common inputs like YYYY-MM-DD or full datetime
                            let isoDate = String(newValue || '').slice(0, 10);
                            if (/^\d{4}-\d{2}-\d{2}$/.test(isoDate)) {
                                cell.setAttribute('data-raw-value', isoDate);
                            } else {
                                // Fallback: try to compute yyyy-mm-dd from Date
                                const d = new Date(newValue);
                                if (!isNaN(d)) {
                                    const y = d.getFullYear();
                                    const m = String(d.getMonth() + 1).padStart(2, '0');
                                    const dd = String(d.getDate()).padStart(2, '0');
                                    isoDate = `${y}-${m}-${dd}`;
                                    cell.setAttribute('data-raw-value', isoDate);
                                }
                            }
                            // Friendly display like 'Oct 4, 2025'
                            try {
                                const friendly = new Date(isoDate).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
                                cell.textContent = friendly;
                            } catch (_) {
                                cell.textContent = isoDate || newValue;
                            }
                        } else {
                            cell.textContent = newValue;
                        }
                        cleanup(false);
                        try { window.showNotification && window.showNotification('Saved', 'success'); } catch(_) {}
                    } else {
                        cleanup(true);
                        const msg = (data && (data.error || data.message)) || 'Save failed';
                        try { window.showNotification && window.showNotification(msg, 'error'); } catch(_) {}
                    }
                } catch (_) {
                    cleanup(true);
                    try { window.showNotification && window.showNotification('Network error', 'error'); } catch(_) {}
                }
            };

            const onKey = (ev, input) => {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    commit(input.value.trim());
                } else if (ev.key === 'Escape') {
                    ev.preventDefault();
                    cleanup(true);
                }
            };

            if (type === 'select') {
                const field = cell.getAttribute('data-field') || '';
                let options = [];
                if (field === 'order_status') options = ORDER_STATUS_OPTS;
                else if (field === 'paymentMethod') options = PAYMENT_METHOD_OPTS;
                else if (field === 'shippingMethod') options = SHIPPING_METHOD_OPTS;
                else if (field === 'paymentStatus') options = PAYMENT_STATUS_OPTS;

                const select = document.createElement('select');
                select.className = 'form-select';
                options.forEach(opt => {
                    const o = document.createElement('option');
                    o.value = opt;
                    o.textContent = opt;
                    if (opt === originalText) o.selected = true;
                    select.appendChild(o);
                });
                cell.innerHTML = '';
                cell.appendChild(select);
                select.focus();
                select.addEventListener('blur', () => commit(select.value));
                select.addEventListener('keydown', (ev) => onKey(ev, select));
            } else {
                // Default: text/date/number become input
                const input = document.createElement('input');
                input.type = (type === 'date' || type === 'number' || type === 'datetime-local') ? type : 'text';
                input.className = 'form-input';
                if (type === 'date') {
                    // Prefer raw ISO (YYYY-MM-DD) to seed the date control
                    input.value = (rawValue && /^\d{4}-\d{2}-\d{2}$/.test(rawValue)) ? rawValue : originalText;
                } else {
                    input.value = originalText;
                }
                cell.innerHTML = '';
                cell.appendChild(input);
                input.focus();
                input.select && input.select();
                input.addEventListener('blur', () => commit(input.value.trim()));
                input.addEventListener('keydown', (ev) => onKey(ev, input));
            }
        };

        // Delegated click to activate editor (capture=true to avoid other handlers blocking it)
        document.addEventListener('click', (e) => {
            try { console.debug('[AdminOrders] editable click handler fired'); } catch(_) {}
            const cell = e.target.closest && e.target.closest('.editable-field');
            if (!cell) return;
            // Avoid activating if we clicked on an input/select already
            if (e.target.closest('input,select,textarea,button,a')) return;
            try { console.debug('[AdminOrders] building editor for field', cell.getAttribute('data-field'), 'order', cell.getAttribute('data-order-id')); } catch(_) {}
            buildEditor(cell);
        }, true);
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

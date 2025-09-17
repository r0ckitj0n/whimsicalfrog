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
        const run = () => {
            this.initInlineEditing();
            this.ensureOrderModalVisible();
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
        // Safety: ensure pointer events are enabled
        try { el.style.pointerEvents = 'auto'; } catch(_) {}
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
            case 'show-add-item-modal':
                this.showAddItemModal();
                break;
            case 'add-item-to-order':
                this.addItemToOrder(actionTarget.dataset.sku, actionTarget.dataset.name, actionTarget.dataset.price);
                break;
            case 'remove-item-from-order':
                this.removeItemFromOrder(actionTarget.dataset.itemId);
                break;
            case 'impersonate-customer':
                this.impersonateCustomer(actionTarget.dataset.userId);
                break;
            case 'close-modal':
                 // Generic close for any modal with this pattern
                const modal = event.target.closest('.modal-overlay');
                if (modal) this.hideModal(modal);
                break;
        }
    }

    ensureOrderModalVisible() {
        try { console.log('[AdminOrders] ensureOrderModalVisible start'); } catch(_) {}
        const orderModal = document.getElementById('orderModal');
        const params = new URLSearchParams(window.location.search || '');
        const requestedId = params.get('view') || params.get('edit');
        if ((this.modalMode || requestedId) && orderModal) {
            this.showModal(orderModal);
            try { console.log('[AdminOrders] showing existing #orderModal'); } catch(_) {}
            return;
        }
        // Fallback: if URL requested a modal but it's not in the DOM, fetch and inject it
        if (requestedId && !orderModal) {
            try {
                const url = new URL(window.location.href);
                fetch(url.toString(), { credentials: 'include' })
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
}

new AdminOrdersModule();

class AdminOrdersModule {
    constructor() {
        this.orderData = {};
        this.allItems = [];
        this.modalMode = null;
        this.currentOrderId = null;

        this.loadData();
        this.bindEvents();
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
        document.addEventListener('DOMContentLoaded', () => {
            this.initInlineEditing();
            this.initModal();
        });
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
                if (modal) modal.style.display = 'none';
                break;
        }
    }

    initModal() {
        const orderModal = document.getElementById('orderModal');
        if (this.modalMode && orderModal) {
            orderModal.style.display = 'flex';
        }
    }

    confirmDelete(orderId) {
        const modal = document.getElementById('deleteConfirmModal');
        const orderIdInput = document.getElementById('delete_order_id');
        if (modal && orderIdInput) {
            orderIdInput.value = orderId;
            modal.style.display = 'flex';
        }
    }

    closeDeleteModal() {
        const modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    async deleteOrder() {
        const orderIdInput = document.getElementById('delete_order_id');
        if (!orderIdInput || !orderIdInput.value) return;

        // Here you would typically make an API call
        // For now, we'll submit the form that contains the delete logic
        const form = orderIdInput.closest('form');
        if (form) {
            form.submit();
        }
    }

    showAddItemModal() {
        const modal = document.getElementById('addItemModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    async addItemToOrder(sku, name, price) {
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

const AdminCustomersModule = {
    config: {
        customers: [],
        currentCustomerId: null,
        modalMode: null,
    },

    init() {
        const container = document.querySelector('.admin-content-container');
        if (!container) return;

        this.loadData();
        this.bindEventListeners(container);

        if (this.config.modalMode === 'view' || this.config.modalMode === 'edit') {
            this.updateCustomerNavigationButtons();
        }
    },

    loadData() {
        const dataElement = document.getElementById('customer-page-data');
        if (dataElement) {
            try {
                const data = JSON.parse(dataElement.textContent);
                this.config.customers = data.customers || [];
                this.config.currentCustomerId = data.currentCustomerId || null;
                this.config.modalMode = data.modalMode || null;
            } catch (e) {
                console.error('Failed to parse customer page data:', e);
            }
        }
    },

    bindEventListeners(container) {
        container.addEventListener('click', (e) => {
            const target = e.target.closest('[data-action]');
            if (!target) return;

            const action = target.dataset.action;

            switch (action) {
                case 'confirm-delete':
                    this.confirmDelete(target.dataset.customerId, target.dataset.customerName);
                    break;
                case 'close-delete-modal':
                    this.closeDeleteModal();
                    break;
                case 'navigate-customer':
                    this.navigateToCustomer(target.dataset.direction);
                    break;
                case 'submit-customer-form':
                    const formData = new FormData(target.closest('form'));
                    fetch('/api/update_customer.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Redirect or show success message
                            window.location.href = `/admin/customers?message=${encodeURIComponent(data.message)}&type=success`;
                        } else {
                            // Handle error
                        }
                    });
                    break;
            }
        });

        // Global keydown listener for modal navigation
        document.addEventListener('keydown', (e) => {
            if ((this.config.modalMode === 'view' || this.config.modalMode === 'edit') && 
                !['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
                
                if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.navigateToCustomer('prev');
                } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.navigateToCustomer('next');
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    // Redirect to the main customers page to close the modal
                    window.location.href = '/admin/customers';
                }
            }
        });
    },

    confirmDelete(customerId, customerName) {
        const modal = document.getElementById('deleteConfirmModal');
        const messageEl = document.getElementById('modal-message');
        const customerIdInput = document.getElementById('delete_customer_id');

        if (modal && messageEl && customerIdInput) {
            messageEl.textContent = `Are you sure you want to delete ${customerName}? This action cannot be undone.`;
            customerIdInput.value = customerId;
            modal.style.display = 'flex';
        }
    },

    closeDeleteModal() {
        const modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            modal.style.display = 'none';
        }
    },

    navigateToCustomer(direction) {
        const customerIds = this.config.customers.map(c => c.id);
        const currentIndex = customerIds.indexOf(this.config.currentCustomerId);

        if (currentIndex === -1) return;

        let nextIndex = direction === 'next' ? currentIndex + 1 : currentIndex - 1;

        if (nextIndex >= 0 && nextIndex < customerIds.length) {
            const nextCustomerId = customerIds[nextIndex];
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set(this.config.modalMode, nextCustomerId);
            window.location.href = currentUrl.toString();
        }
    },

    updateCustomerNavigationButtons() {
        const customerIds = this.config.customers.map(c => c.id);
        const currentIndex = customerIds.indexOf(this.config.currentCustomerId);
        
        const prevBtn = document.getElementById('prevCustomerBtn');
        const nextBtn = document.getElementById('nextCustomerBtn');

        if (prevBtn) {
            prevBtn.disabled = currentIndex <= 0;
        }
        if (nextBtn) {
            nextBtn.disabled = currentIndex >= customerIds.length - 1;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    AdminCustomersModule.init();
});

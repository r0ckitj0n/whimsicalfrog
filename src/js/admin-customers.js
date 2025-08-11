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

        this.highlightRowFromUrl();
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
                    {
                        const form = target.closest('form');
                        if (form) form.submit();
                    }
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
            modal.classList.add('show');
        }
    },

    closeDeleteModal() {
        const modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            modal.classList.remove('show');
        }
    },

    navigateToCustomer(direction) {
        const customers = this.config.customers || [];
        const ids = customers.map(c => c.id);
        const currentIndex = ids.indexOf(this.config.currentCustomerId);
        if (currentIndex === -1 || ids.length === 0) return;

        let nextIndex = direction === 'next' ? currentIndex + 1 : currentIndex - 1;
        if (nextIndex < 0) nextIndex = ids.length - 1;
        if (nextIndex >= ids.length) nextIndex = 0;

        const nextId = ids[nextIndex];
        const url = new URL(window.location.href);
        // Ensure modal param is set (either 'view' or 'edit') and preserve other params
        const mode = this.config.modalMode === 'edit' ? 'edit' : 'view';
        url.searchParams.set('page', 'admin');
        url.searchParams.set('section', 'customers');
        url.searchParams.set(mode, nextId);
        window.location.href = url.toString();
    },

    updateCustomerNavigationButtons() {
        const customers = this.config.customers || [];
        const ids = customers.map(c => c.id);
        const idx = ids.indexOf(this.config.currentCustomerId);

        const prevBtn = document.getElementById('prevCustomerBtn');
        const nextBtn = document.getElementById('nextCustomerBtn');

        if (!prevBtn || !nextBtn || customers.length === 0 || idx === -1) return;

        // Show buttons and set helpful titles
        prevBtn.style.display = 'block';
        nextBtn.style.display = 'block';

        const counter = `${idx + 1} of ${customers.length}`;
        const prevIndex = idx > 0 ? idx - 1 : customers.length - 1;
        const nextIndex = idx < customers.length - 1 ? idx + 1 : 0;
        const prevCustomer = customers[prevIndex] || {};
        const nextCustomer = customers[nextIndex] || {};
        const prevName = `${prevCustomer.firstName || ''} ${prevCustomer.lastName || ''}`.trim() || 'Unknown';
        const nextName = `${nextCustomer.firstName || ''} ${nextCustomer.lastName || ''}`.trim() || 'Unknown';
        prevBtn.title = `Previous: ${prevName} (${counter})`;
        nextBtn.title = `Next: ${nextName} (${counter})`;
    },

    highlightRowFromUrl() {
        try {
            const url = new URL(window.location.href);
            const id = url.searchParams.get('highlight');
            if (!id) return;
            const row = document.querySelector(`tr[data-customer-id='${CSS.escape(id)}']`);
            if (!row) return;
            row.classList.add('highlighted-row');
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Remove just the highlight param to keep the URL clean
            url.searchParams.delete('highlight');
            window.history.replaceState({}, '', url.toString());
            setTimeout(() => row.classList.remove('highlighted-row'), 3000);
        } catch (e) {
            // no-op
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    AdminCustomersModule.init();
});

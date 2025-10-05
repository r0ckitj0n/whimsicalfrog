import { buildAdminUrl } from '../core/admin-url-builder.js';
import { toastFromData, toastError } from '../core/toast.js';

const AdminCustomersModule = {
    config: {
        customers: [],
        currentCustomerId: null,
        modalMode: null,
    },

    init() {
        console.log('[AdminCustomers] AdminCustomersModule.init() called');
        console.log('[AdminCustomers] Module instance:', this);

        // Check if module is already initialized
        if (this.initialized) {
            console.log('[AdminCustomers] Module already initialized, skipping...');
            return;
        }
        this.initialized = true;

        // Initialize even if the admin content container is not present
        const container = document.querySelector('.admin-content-container') || document.body;

        this.loadData();
        this.bindEventListeners(container);

        // Ensure server-rendered overlay (customerModalOuter) is visible and above shell
        this.ensureCustomerOverlayVisible();

        if (this.config.modalMode === 'view' || this.config.modalMode === 'edit') {
            this.updateCustomerNavigationButtons();
        }

        this.highlightRowFromUrl();

        // Check notification system availability
        console.log('[AdminCustomers] Checking notification system availability:');
        console.log('- window.adminNotifications:', typeof window.adminNotifications);
        console.log('- window.showSuccess:', typeof window.showSuccess);
        console.log('- window.showNotification:', typeof window.showNotification);
        console.log('- window.wfNotifications:', typeof window.wfNotifications);

        // Make handleCustomerSave globally available for manual testing
        window.testCustomerSave = () => {
            console.log('[AdminCustomers] Manual customer save test triggered!');
            this.handleCustomerSave();
        };

        // Test buttons removed as per user request.

        // Debug: Check for form and button existence after initialization
        setTimeout(() => {
            const form = document.getElementById('customerForm');
            const saveBtn = document.getElementById('saveCustomerBtn');
            console.log('[AdminCustomers] DOM element check after init:');
            console.log('- customerForm exists:', !!form);
            console.log('- saveCustomerBtn exists:', !!saveBtn);
            if (form) {
                console.log('- Form method:', form.method);
                console.log('- Form action:', form.action);
                console.log('- Form listeners count:', form.listeners?.length || 'unknown');
            }

            // Check if there are any existing event listeners
            console.log('[AdminCustomers] Checking for existing event listeners...');
            if (form && form._listeners) {
                console.log('- Form listeners:', Object.keys(form._listeners));
            }
            if (saveBtn && saveBtn._listeners) {
                console.log('- Button listeners:', Object.keys(saveBtn._listeners));
            }

            // Check for any directNotificationTest function that might be interfering
            console.log('[AdminCustomers] Checking for interfering functions:');
            console.log('- directNotificationTest defined:', typeof window.directNotificationTest);
            console.log('- Other notification test functions:', Object.keys(window).filter(k => k.includes('test') || k.includes('Test')));
        }, 100);

        console.log('[AdminCustomers] Module initialization complete - ready for customer saves!');
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
        console.log('[AdminCustomers] Setting up event listeners...');
        const isEditMode = this.config.modalMode === 'edit';

        // Handle form submission (only in edit mode)
        const form = document.getElementById('customerForm');
        if (form && isEditMode) {
            console.log('[AdminCustomers] Found customer form, adding submit handler');
            form.addEventListener('submit', (e) => {
                console.log('[AdminCustomers] Form submitted!');
                e.preventDefault();
                this.handleCustomerSave();
            });

            // Also log all form submissions for debugging
            form.addEventListener('submit', (e) => {
                console.log('[AdminCustomers] Form submit event captured:', e);
            }, true); // Use capture phase
        } else if (form && !isEditMode) {
            console.log('[AdminCustomers] Customer form found but not in edit mode, skipping form handlers');
        } else if (!form && isEditMode) {
            console.error('[AdminCustomers] Customer form NOT found but in edit mode!');
        }

        // Handle save button click directly (only in edit mode)
        const saveBtn = document.getElementById('saveCustomerBtn');
        if (saveBtn && isEditMode) {
            console.log('[AdminCustomers] Found save button, adding click handler');
            saveBtn.addEventListener('click', (e) => {
                console.log('[AdminCustomers] Save button clicked directly!');
                e.preventDefault();
                e.stopPropagation();
                this.handleCustomerSave();
            });

            // Also add a more general click handler for debugging
            saveBtn.addEventListener('click', (e) => {
                console.log('[AdminCustomers] Save button click event captured:', e);
            }, true);
        } else if (saveBtn && !isEditMode) {
            console.log('[AdminCustomers] Save button found but not in edit mode, skipping button handlers');
        } else if (!saveBtn && isEditMode) {
            console.error('[AdminCustomers] Save button NOT found but in edit mode!');
        }

        // Add a global click handler to catch any button clicks
        document.addEventListener('click', (e) => {
            if (e.target.id === 'saveCustomerBtn' || e.target.closest('#saveCustomerBtn')) {
                console.log('[AdminCustomers] Save button clicked (global handler)!');
            }
        }, true);

        // Debug: Log all clicks on the page
        document.addEventListener('click', (e) => {
            const target = e.target;
            if (target.tagName === 'BUTTON' || target.tagName === 'INPUT') {
                console.log('[AdminCustomers] Button/Input clicked:', target.id || target.name || target.className, e);
            }
        }, true);

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
            }
        });

        document.body.addEventListener('click', (e) => {
            const el = e.target.closest('[data-action]');
            if (!el) return;
            const action = el.dataset.action;
            if (!action) return;

            switch (action) {
                case 'close-customer-editor-on-overlay': {
                    const overlay = document.getElementById('customerModalOuter');
                    if (overlay && e.target === overlay) {
                        e.preventDefault();
                        window.location.href = buildAdminUrl('customers');
                    }
                    break;
                }
                case 'close-customer-editor': {
                    e.preventDefault();
                    window.location.href = buildAdminUrl('customers');
                    break;
                }
                case 'navigate-to-edit': {
                    // Instead of full page navigation, handle within the same page context
                    e.preventDefault();
                    const href = el.href;
                    const url = new URL(href, window.location.origin);
                    const customerId = url.searchParams.get('view') || url.searchParams.get('edit');

                    if (customerId) {
                        // Update the page data to trigger modal display
                        const pageData = document.getElementById('customer-page-data');
                        if (pageData) {
                            try {
                                const data = JSON.parse(pageData.textContent);
                                const action = url.searchParams.get('view') ? 'view' : 'edit';
                                data.currentCustomerId = customerId;
                                data.modalMode = action;
                                pageData.textContent = JSON.stringify(data);

                                // Reinitialize the module with new data
                                this.config = data;
                                this.ensureCustomerOverlayVisible();
                                this.updateCustomerNavigationButtons();
                            } catch (error) {
                                console.error('Error updating customer page data:', error);
                                // Fallback to navigation
                                window.location.href = href;
                            }
                        } else {
                            // Fallback to navigation if no page data
                            window.location.href = href;
                        }
                    } else {
                        // Fallback to navigation
                        window.location.href = href;
                    }
                    break;
                }
                case 'view-order': {
                    e.preventDefault();
                    const orderId = el.dataset.orderId;
                    if (orderId) {
                        this.openOrderModal(orderId);
                    }
                    break;
                }
                case 'save-customer': {
                    e.preventDefault();
                    this.handleCustomerSave();
                    break;
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
        const mode = this.config.modalMode === 'edit' ? 'edit' : 'view';
        const params = { [mode]: nextId };
        // Preserve existing query parameters, like filters
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.forEach((value, key) => {
            if (key !== 'view' && key !== 'edit' && key !== 'section') {
                params[key] = value;
            }
        });
        window.location.href = buildAdminUrl('customers', params);
    },

    updateCustomerNavigationButtons() {
        const isModalMode = this.config.modalMode === 'view' || this.config.modalMode === 'edit';

        if (!isModalMode) {
            console.log('[AdminCustomers] Not in modal mode, skipping navigation button updates');
            return;
        }

        const customers = this.config.customers || [];
        const ids = customers.map(c => c.id);
        const idx = ids.indexOf(this.config.currentCustomerId);

        const prevBtn = document.getElementById('prevCustomerBtn');
        const nextBtn = document.getElementById('nextCustomerBtn');

        if (!prevBtn || !nextBtn || customers.length === 0 || idx === -1) return;

        // Show buttons and set helpful titles via class toggles
        prevBtn.classList.remove('hidden');
        nextBtn.classList.remove('hidden');

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
            try { row.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) {}
            url.searchParams.delete('highlight');
            try { window.history.replaceState({}, document.title, url.toString()); } catch (_) {}
            setTimeout(() => { try { row.classList.remove('highlighted-row'); } catch (_) {} }, 3000);
        } catch (_) {}
    },

    openOrderModal(orderId) {
        // Store the current URL to return to
        sessionStorage.setItem('returnToCustomer', window.location.href);

        // Navigate to the order page - this will use the exact same modal system
        window.location.href = buildAdminUrl('orders', { view: orderId, returnTo: 'customers' });
    },

    handleCustomerSave() {
        console.log('[AdminCustomers] handleCustomerSave() called!');
        const isEditMode = this.config.modalMode === 'edit';

        if (!isEditMode) {
            console.log('[AdminCustomers] Not in edit mode, skipping save operation');
            return;
        }

        const form = document.getElementById('customerForm');
        if (!form) {
            console.error('[AdminCustomers] Customer form not found!');
            return;
        }

        console.log('[AdminCustomers] Found customer form, proceeding with save...');

        // Add loading state to button
        const saveBtn = document.getElementById('saveCustomerBtn');
        const buttonText = saveBtn?.querySelector('.button-text');
        const loadingSpinner = saveBtn?.querySelector('.loading-spinner');
        if (buttonText && loadingSpinner) {
            console.log('[AdminCustomers] Adding loading state to save button...');
            buttonText.classList.add('hidden');
            loadingSpinner.classList.remove('hidden');
        }

        const fd = new FormData(form);
        const id = (fd.get('customerId') || '').toString();
        console.log('[AdminCustomers] Customer ID:', id);

        fetch('/functions/process_customer_update.php', {
            method: 'POST',
            credentials: 'include',
            body: fd,
        })
            .then(r => {
                console.log('[AdminCustomers] Fetch response status:', r.status, r.statusText);
                return r.json().catch(() => {
                    console.error('[AdminCustomers] Failed to parse JSON response');
                    return {};
                });
            })
            .then(data => {
                console.log('[AdminCustomers] Response data:', data);

                try { if (data) toastFromData(data); } catch (_) {}

                if (data && data.success) {
                    console.log('[AdminCustomers] Customer saved successfully, showing notification...');
                    console.log('[AdminCustomers] Available notification systems:');
                    console.log('- window.wfNotifications:', typeof window.wfNotifications);
                    console.log('- window.adminNotifications:', typeof window.adminNotifications);
                    console.log('- window.showSuccess:', typeof window.showSuccess);
                    console.log('- window.showNotification:', typeof window.showNotification);

                    // Show success notification using universal admin toast helper (persistent, no redirect)
                    console.log('[AdminCustomers] Customer saved successfully, showing persistent toast...');
                    console.log('[AdminCustomers] Available notification systems:');
                    console.log('- window.showAdminToast:', typeof window.showAdminToast);
                    console.log('- window.wfNotifications:', typeof window.wfNotifications);
                    console.log('- window.adminNotifications:', typeof window.adminNotifications);
                    console.log('- window.showSuccess:', typeof window.showSuccess);
                    console.log('- window.showNotification:', typeof window.showNotification);

                    // Use the universal admin toast helper (persistent with icon actions)
                    if (window.showAdminToast) {
                        console.log('[AdminCustomers] Using window.showAdminToast (persistent, no redirect)');
                        window.showAdminToast('Customer saved successfully', 'success', { title: 'Saved' });
                    } else if (window.wfNotifications && typeof window.wfNotifications.success === 'function') {
                        console.log('[AdminCustomers] Using wfNotifications.success');
                        window.wfNotifications.success('Customer saved successfully');
                    } else if (window.adminNotifications) {
                        console.log('[AdminCustomers] Using adminNotifications system');
                        window.adminNotifications.success('Customer saved successfully', { title: 'Saved' });
                    } else if (window.showSuccess) {
                        console.log('[AdminCustomers] Using window.showSuccess');
                        window.showSuccess('Customer saved successfully');
                    } else if (window.showNotification) {
                        console.log('[AdminCustomers] Using window.showNotification');
                        window.showNotification('Customer saved successfully', 'success', { title: 'Saved' });
                    } else {
                        console.log('[AdminCustomers] Using alert fallback');
                        // Last resort fallback
                        alert('Customer saved successfully');
                    }

                    // Do NOT redirect automatically; keep the modal open so the toast remains visible
                    // If we ever want to navigate, we can do it manually from an action in the toast
                    // const targetId = (data.customerId || id || '').toString();
                } else {
                    const msg = (data && (data.error || data.message)) || 'Failed to save customer';
                    console.error('[AdminCustomers] Customer save failed:', data);
                    console.log('[AdminCustomers] Available notification systems for error:');
                    console.log('- window.adminNotifications:', typeof window.adminNotifications);
                    console.log('- window.showError:', typeof window.showError);
                    console.log('- window.showNotification:', typeof window.showNotification);

                    // Show error notification using standardized system
                    try { toastError(msg); } catch (_) {}
                    if (window.adminNotifications) {
                        console.log('[AdminCustomers] Using adminNotifications for error');
                        window.adminNotifications.error(msg, { title: 'Save Failed' });
                    } else if (window.showError) {
                        console.log('[AdminCustomers] Using window.showError');
                        window.showError(msg);
                    } else if (window.showNotification) {
                        console.log('[AdminCustomers] Using window.showNotification for error');
                        window.showNotification(msg, 'error', { title: 'Save Failed' });
                    } else {
                        console.log('[AdminCustomers] Using alert fallback for error');
                        // Last resort fallback
                        alert('Error: ' + msg);
                    }
                }
            })
            .catch((error) => {
                console.error('[AdminCustomers] Network error saving customer:', error);
                const msg = 'Network error saving customer';
                try { toastError(msg); } catch (_) {}
                console.log('[AdminCustomers] Available notification systems for network error:');
                console.log('- window.adminNotifications:', typeof window.adminNotifications);
                console.log('- window.showError:', typeof window.showError);
                console.log('- window.showNotification:', typeof window.showNotification);

                // Show error notification using standardized system
                if (window.adminNotifications) {
                    console.log('[AdminCustomers] Using adminNotifications for network error');
                    window.adminNotifications.error(msg, { title: 'Save Failed' });
                } else if (window.showError) {
                    console.log('[AdminCustomers] Using window.showError for network error');
                    window.showError(msg);
                } else if (window.showNotification) {
                    console.log('[AdminCustomers] Using window.showNotification for network error');
                    window.showNotification(msg, 'error', { title: 'Save Failed' });
                } else {
                    console.log('[AdminCustomers] Using alert fallback for network error');
                    // Last resort fallback
                    alert('Error: ' + msg);
                }
            })
            .finally(() => {
                // Remove loading state from button
                if (buttonText && loadingSpinner) {
                    console.log('[AdminCustomers] Removing loading state from save button...');
                    buttonText.classList.remove('hidden');
                    loadingSpinner.classList.add('hidden');
                }
            });
    },

    testNotifications() {
        console.log('[AdminCustomers] Testing notification systems...');
        if (window.adminNotifications) {
            console.log('[AdminCustomers] Testing adminNotifications...');
            window.adminNotifications.success('Test notification from AdminCustomersModule', { title: 'Test' });
        } else if (window.showSuccess) {
            console.log('[AdminCustomers] Testing showSuccess...');
            window.showSuccess('Test notification from AdminCustomersModule');
        } else if (window.showNotification) {
            console.log('[AdminCustomers] Testing showNotification...');
            window.showNotification('Test notification from AdminCustomersModule', 'info', { title: 'Test' });
        } else {
            console.log('[AdminCustomers] No notification system available, using alert...');
            alert('Test notification from AdminCustomersModule');
        }
    },

    ensureCustomerOverlayVisible() {
        const isModalMode = this.config.modalMode === 'view' || this.config.modalMode === 'edit';

        if (!isModalMode) {
            console.log('[AdminCustomers] Not in modal mode, skipping overlay visibility checks');
            return;
        }

        try {
            const overlay = document.getElementById('customerModalOuter');
            if (!overlay) return;
            overlay.classList.remove('hidden');
            overlay.classList.add('show', 'topmost');
            document.documentElement.classList.add('modal-open');
            document.body.classList.add('modal-open');
            const panel = overlay.querySelector('.admin-modal');
            if (panel) {
                panel.classList.add('wf-admin-panel-visible');
                if (!overlay.__wfTrapInstalled) {
                    overlay.__wfTrapInstalled = true;
                    const getFocusable = () => Array.from(panel.querySelectorAll('a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])')).filter(el => !el.disabled && el.getAttribute('aria-hidden') !== 'true');
                    setTimeout(() => { const first = getFocusable()[0]; if (first && first.focus) first.focus(); }, 0);
                    panel.addEventListener('keydown', ev => {
                        if (ev.key !== 'Tab') return;
                        const f = getFocusable(); if (f.length === 0) return;
                        const first = f[0]; const last = f[f.length - 1];
                        if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
                        else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
                    });
                }
            }
        } catch(_) {}
    },
};

if (document.readyState !== 'loading') {
    AdminCustomersModule.init();
} else {
    document.addEventListener('DOMContentLoaded', () => {
        AdminCustomersModule.init();
    }, { once: true });
}

// Make AdminCustomersModule globally available for debugging
window.AdminCustomersModule = AdminCustomersModule;

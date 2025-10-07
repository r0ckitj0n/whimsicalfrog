import Chart from 'chart.js/auto';
import { ApiClient } from '../core/api-client.js';

const AdminMarketingModule = {
    salesChartInstance: null,
    paymentChartInstance: null,

    init() {
        const marketingPage = document.querySelector('.admin-marketing-page');
        if (!marketingPage) {
            return; // Only run on the marketing page
        }

        this.bindEventListeners();
        this.initializeCharts(marketingPage);
    },

    bindEventListeners() {
        document.body.addEventListener('click', (e) => {
            const target = e.target;

            // Tool section toggles
            const toolButton = target.closest('[data-tool]');
            if (toolButton) {
                this.showMarketingTool(toolButton.dataset.tool);
                return;
            }

            // Form toggles
            const toggleBtn = target.closest('[data-toggle-form]');
            if (toggleBtn) {
                const formId = toggleBtn.dataset.toggleForm;
                this.toggleForm(formId);
                return;
            }

            // Generate discount code
            if (target.closest('#generateDiscountBtn')) {
                this.generateDiscountCode();
                return;
            }
            
            // Initialize tables
            if (target.closest('#initMarketingTablesBtn')) {
                this.initializeMarketingTables();
                return;
            }
        });
    },

    showMarketingTool(toolId) {
        document.querySelectorAll('.marketing-tool-section').forEach(section => {
            section.classList.add('hidden');
        });
        const activeSection = document.getElementById(`${toolId}-section`);
        if (activeSection) {
            activeSection.classList.remove('hidden');
        }
        
        // Load specific tool data
        if (toolId === 'social-media') {
            this.loadSocialAccounts();
        }
    },

    async loadSocialAccounts() {
        try {
            const data = await ApiClient.get('/api/get_social_accounts.php');
            
            const container = document.getElementById('social-accounts-list');
            if (data.success && data.accounts) {
                container.innerHTML = this.renderSocialAccounts(data.accounts);
            } else {
                container.innerHTML = '<p class="text-gray-600">No social accounts connected.</p>';
            }
        } catch (error) {
            console.error('Error loading social accounts:', error);
        }
    },

    renderSocialAccounts(accounts) {
        return accounts.map(account => `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded mb-2">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm">
                        ${account.platform.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="font-medium">${account.platform}</div>
                        <div class="text-sm text-gray-600">@${account.account_name}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs px-2 py-1 rounded ${account.connected ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                        ${account.connected ? 'Connected' : 'Disconnected'}
                    </span>
                    <button onclick="AdminMarketingModule.manageSocialAccount('${account.id}')" class="btn btn-sm btn-secondary">
                        Manage
                    </button>
                </div>
            </div>
        `).join('');
    },

    async manageSocialAccount(_accountId) {
        // Open social account management modal
        alert('Social account management coming soon!');
    },

    toggleForm(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.classList.toggle('hidden');
        }
    },

    generateDiscountCode() {
        const codeInput = document.getElementById('newDiscountCode');
        if (codeInput) {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let code = '';
            for (let i = 0; i < 8; i++) {
                code += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            codeInput.value = code;
        }
    },

    initializeCharts(container) {
        const chartDataEl = container.querySelector('#marketingChartData');
        if (!chartDataEl) return;

        try {
            const data = JSON.parse(chartDataEl.textContent);
            this.renderSalesChart(data.sales);
            this.renderPaymentChart(data.payments);
        } catch (e) {
            console.error('Failed to parse marketing chart data:', e);
        }
    },

    renderSalesChart(data) {
        const ctx = document.getElementById('salesChart')?.getContext('2d');
        if (!ctx) return;

        if (this.salesChartInstance) this.salesChartInstance.destroy();
        this.salesChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Sales',
                    data: data.values,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    },

    renderPaymentChart(data) {
        const ctx = document.getElementById('paymentMethodChart')?.getContext('2d');
        if (!ctx) return;

        if (this.paymentChartInstance) this.paymentChartInstance.destroy();
        this.paymentChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Payment Methods',
                    data: data.values,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                    ]
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    },
    
    async initializeMarketingTables() {
        // This function would contain the logic to create marketing tables via an API call.
        // For now, it just shows an alert as a placeholder.
        alert('Initializing marketing tables...');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    AdminMarketingModule.init();
});

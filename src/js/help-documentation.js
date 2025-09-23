class HelpDocumentation {
    constructor() {
        this.init();
    }

    init() {
        document.getElementById('helpSearch')?.addEventListener('input', (e) => this.search(e.target.value));
        document.querySelectorAll('.toc-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.loadSection(link.getAttribute('href').substring(1));
            });
        });
        this.loadSection('getting-started');
    }

    loadSection(sectionId) {
        const content = {
            'getting-started': `<h2 class="help-title">🚀 Getting Started</h2><p>Welcome! Set up your business info, configure payments, add products, and set up categories.</p>`,
            'inventory': `<h2 class="help-title">📦 Inventory</h2><p>Manage products and stock levels.</p>`,
            'orders': `<h2 class="help-title">📋 Orders</h2><p>Track customer orders.</p>`,
            'rooms': `<h2 class="help-title">🏠 Rooms</h2><p>Configure room layouts.</p>`,
            'payments': `<h2 class="help-title">💳 Payments</h2><p>Set up Square payments.</p>`
        };
        document.getElementById('helpContent').innerHTML = content[sectionId] || content['getting-started'];
    }

    search(query) {
        // Simple search implementation
        if (!query) return;
        console.log('Searching for:', query);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new HelpDocumentation());
} else {
    new HelpDocumentation();
}

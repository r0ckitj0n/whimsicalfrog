/**
 * Shop Page Functionality
 * Handles category filtering and product card layout for the shop page.
 */
import WF from './whimsical-frog-core.js';
import { debounce } from './utils.js';

// Runtime helpers for height equalization without inline styles
const WFSHOP_EQH = { styleEl: null, rules: new Set() };
function ensureEqHStyleEl() {
    if (!WFSHOP_EQH.styleEl) {
        WFSHOP_EQH.styleEl = document.createElement('style');
        WFSHOP_EQH.styleEl.id = 'wf-shop-eqh-styles';
        document.head.appendChild(WFSHOP_EQH.styleEl);
    }
    return WFSHOP_EQH.styleEl;
}
function buildEqHClassName(h) {
    return `wf-eqh-h${h}`;
}
function ensureEqHRule(className, h) {
    if (WFSHOP_EQH.rules.has(className)) return;
    const css = `#productsGrid .${className} { height: ${h}px; }`;
    ensureEqHStyleEl().appendChild(document.createTextNode(css));
    WFSHOP_EQH.rules.add(className);
}

const ShopPage = {
    init() {
        this.categoryButtons = document.querySelectorAll('.category-btn');
        this.productCards = document.querySelectorAll('.product-card');
        this.productsGrid = document.getElementById('productsGrid');

        if (!this.productsGrid || this.categoryButtons.length === 0) {
            // Don't run shop-specific logic if the main components aren't on the page
            return;
        }

        this.setupEventListeners();
        // Use a timeout to ensure images are loaded before calculating heights
        setTimeout(() => this.equalizeCardHeights(), 300);
        WF.log('Shop Page module initialized.');
    },

    setupEventListeners() {
        this.categoryButtons.forEach(btn => {
            btn.addEventListener('click', (e) => this.filterByCategory(e));
        });

        window.addEventListener('resize', debounce(() => this.equalizeCardHeights(), 200));
    },

    filterByCategory(event) {
        const button = event.currentTarget;
        const category = button.dataset.category;
        WF.log(`Filtering by category: ${category}`);

        this.categoryButtons.forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');

        this.productCards.forEach(card => {
            const cardCategory = card.dataset.category;
            const isVisible = (category === 'all' || cardCategory === category);
            card.classList.toggle('hidden', !isVisible);
        });

        // Recalculate heights after the DOM has updated from filtering
        setTimeout(() => this.equalizeCardHeights(), 50);
    },

    equalizeCardHeights() {
        const visibleCards = Array.from(this.productCards).filter(card => !card.classList.contains('hidden'));

        if (visibleCards.length === 0) return;

        // Reset previous height classes to allow natural reflow
        visibleCards.forEach(card => {
            const prev = card.dataset.wfEqhClass;
            if (prev) {
                card.classList.remove(prev);
                delete card.dataset.wfEqhClass;
            }
        });

        // Allow the browser to reflow and calculate natural heights
        requestAnimationFrame(() => {
            const maxHeight = Math.max(...visibleCards.map(card => card.offsetHeight));
    
            if (maxHeight > 0) {
                const h = Math.round(maxHeight);
                const className = buildEqHClassName(h);
                ensureEqHRule(className, h);
                visibleCards.forEach(card => {
                    // Remove any stale class first (in case card becomes visible from previous group)
                    const prev = card.dataset.wfEqhClass;
                    if (prev && prev !== className) card.classList.remove(prev);
                    if (!card.classList.contains(className)) card.classList.add(className);
                    card.dataset.wfEqhClass = className;
                });
            }
        });
    }
};

// Initialize the module when the core is ready
WF.ready(() => ShopPage.init());

export default ShopPage;

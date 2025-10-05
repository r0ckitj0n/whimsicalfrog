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
        // Only bind to actual filter buttons in the middle nav, not the back link
        this.categoryButtons = document.querySelectorAll('.category-navigation .category-btn');
        this.productCards = document.querySelectorAll('.product-card');
        this.productsGrid = document.getElementById('productsGrid');
        this.shopNavArea = document.querySelector('#shopPage .shop-navigation-area');

        if (!this.productsGrid || this.categoryButtons.length === 0) {
            // Don't run shop-specific logic if the main components aren't on the page
            return;
        }

        this.setupEventListeners();
        // Ensure nav height is reflected in CSS variable before layout calc
        this.measureNavHeight();
        this.setupMoreToggles();
        this.attachModalHandlers();
        // Use a timeout to ensure images are loaded before calculating heights
        setTimeout(() => this.equalizeCardHeights(), 300);
        WF.log('Shop Page module initialized.');
    },

    setupEventListeners() {
        this.categoryButtons.forEach(btn => {
            btn.addEventListener('click', (e) => this.filterByCategory(e));
        });

        window.addEventListener('resize', debounce(() => {
            this.measureNavHeight();
            this.equalizeCardHeights();
        }, 200));
    },

    measureNavHeight() {
        if (!this.shopNavArea) return;
        // Read actual nav height and expose to CSS
        const h = this.shopNavArea.offsetHeight;
        if (h && Number.isFinite(h)) {
            // Inject/update a stylesheet rule for the CSS variable (no inline styles)
            let styleEl = document.getElementById('wf-shop-nav-css');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'wf-shop-nav-css';
                document.head.appendChild(styleEl);
            }
            styleEl.textContent = `:root { --shop-nav-height: ${Math.round(h)}px; }`;
        }
    },

    setupMoreToggles() {
        const toggles = this.productsGrid.querySelectorAll('.product-more-toggle');
        toggles.forEach(btn => {
            btn.addEventListener('click', (e) => {
                // Prevent card-level click from triggering modal
                e.stopPropagation();
                const button = e.currentTarget;
                const card = button.closest('.product-card');
                if (!card) return;

                const isNowExpanded = !card.classList.contains('is-expanded');
                // Toggle expanded state on the card
                card.classList.toggle('is-expanded', isNowExpanded);

                // Swap short/full description visibility without inline styles
                const shortEl = card.querySelector('.description-text-short');
                const fullEl = card.querySelector('.description-text-full');
                if (shortEl) shortEl.classList.toggle('hidden', isNowExpanded);
                if (fullEl) fullEl.classList.toggle('hidden', !isNowExpanded);

                // Toggle the additional info block (CSS handles via .is-expanded)
                // Update button a11y and label
                button.setAttribute('aria-expanded', String(isNowExpanded));
                button.textContent = isNowExpanded ? 'Hide Additional Information' : 'Additional Information';

                // When any card is expanded, drop equalization so other rows aren't forced tall
                if (isNowExpanded) {
                    this.removeEqualization();
                } else {
                    // If no cards remain expanded, optionally re-equalize to keep tidy rows
                    const anyExpanded = this.productsGrid.querySelector('.product-card.is-expanded');
                    if (!anyExpanded) {
                        setTimeout(() => this.equalizeCardHeights(), 50);
                    }
                }
            });
        });
    },

    attachModalHandlers() {
        // Button: Add to Cart opens detailed modal
        const buttons = this.productsGrid.querySelectorAll('.add-to-cart-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const card = btn.closest('.product-card');
                if (!card) return;
                // Block interaction if out of stock
                const stockAttr = card.getAttribute('data-stock');
                const stockLevel = Number.parseInt(stockAttr || card.querySelector('.product-stock')?.getAttribute('data-stock') || '0', 10);
                if (!Number.isNaN(stockLevel) && stockLevel <= 0) {
                    // Grey the button state defensively
                    btn.setAttribute('aria-disabled', 'true');
                    btn.classList.add('is-disabled');
                    if (typeof window.showNotification === 'function') {
                        window.showNotification('This item is out of stock.', { type: 'info' });
                    }
                    return; // do not open modal
                }
                this.openModalForCard(card);
            });
        });

        // Card click anywhere else opens detailed modal
        this.productCards.forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('.product-more-toggle, .add-to-cart-btn')) return;
                const stockAttr = card.getAttribute('data-stock');
                const stockLevel = Number.parseInt(stockAttr || card.querySelector('.product-stock')?.getAttribute('data-stock') || '0', 10);
                if (!Number.isNaN(stockLevel) && stockLevel <= 0) {
                    if (typeof window.showNotification === 'function') {
                        window.showNotification('This item is out of stock.', { type: 'info' });
                    }
                    return;
                }
                this.openModalForCard(card);
            });
        });
    },

    openModalForCard(card) {
        const sku = (card.dataset && card.dataset.sku) || '';
        if (!sku) return;

        // OOS guard
        const stockAttr = card.getAttribute('data-stock');
        const stockLevel = Number.parseInt(stockAttr || card.querySelector('.product-stock')?.getAttribute('data-stock') || '0', 10);
        if (!Number.isNaN(stockLevel) && stockLevel <= 0) {
            if (typeof window.showNotification === 'function') {
                window.showNotification('This item is out of stock.', { type: 'info' });
            }
            return;
        }

        const name = (card.dataset && card.dataset.name) || (card.querySelector('.product-title')?.textContent || '').trim();
        const priceStr = (card.dataset && card.dataset.price) || '';
        const price = Number.parseFloat(priceStr) || 0;
        const imgEl = card.querySelector('img.product-image');
        const image = imgEl ? imgEl.getAttribute('src') : '';
        const fullDescEl = card.querySelector('.description-text-full');
        const description = fullDescEl ? fullDescEl.textContent.trim() : '';

        const item = {
            sku,
            name,
            price,
            retailPrice: price,
            currentPrice: price,
            stockLevel,
            image,
            description
        };

        if (window.WhimsicalFrog && window.WhimsicalFrog.GlobalModal && typeof window.WhimsicalFrog.GlobalModal.show === 'function') {
            window.WhimsicalFrog.GlobalModal.show(sku, item);
        } else if (typeof window.showGlobalItemModal === 'function') {
            window.showGlobalItemModal(sku, item);
        } else if (typeof window.showDetailedModal === 'function') {
            window.showDetailedModal(sku, item);
        } else {
            console.warn('[Shop] Global modal system not available');
        }
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
        // If any card is expanded, prefer natural heights
        if (this.productsGrid.querySelector('.product-card.is-expanded')) {
            this.removeEqualization();
            return;
        }

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
    },

    removeEqualization() {
        const cards = Array.from(this.productCards);
        cards.forEach(card => {
            const prev = card.dataset.wfEqhClass;
            if (prev) {
                card.classList.remove(prev);
                delete card.dataset.wfEqhClass;
            }
        });
    }
};

// Initialize the module when the core is ready
WF.ready(() => ShopPage.init());

export default ShopPage;

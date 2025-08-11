/**
 * Shop Page Functionality
 * Handles category filtering and product card layout for the shop page.
 */
import WF from './whimsical-frog-core.js';
import { debounce } from './utils.js';

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

        // Reset heights to allow natural reflow
        visibleCards.forEach(card => { card.style.height = 'auto'; });

        // Allow the browser to reflow and calculate natural heights
        requestAnimationFrame(() => {
            const maxHeight = Math.max(...visibleCards.map(card => card.offsetHeight));
    
            if (maxHeight > 0) {
                visibleCards.forEach(card => { card.style.height = `${maxHeight}px`; });
            }
        });
    }
};

// Initialize the module when the core is ready
WF.ready(() => ShopPage.init());

export default ShopPage;

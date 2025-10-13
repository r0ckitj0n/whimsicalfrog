/**
 * Shop Page Functionality
 * Handles category filtering and product card layout for the shop page.
 */
import './whimsical-frog-core-unified.js';
import apiClient from './api-client.js';
const WF = window.WF;
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
        this.categoryNav = document.querySelector('#shopPage .category-navigation');

        if (!this.productsGrid || this.categoryButtons.length === 0) {
            // Don't run shop-specific logic if the main components aren't on the page
            return;
        }

        // If filters not present, build from product cards
        this.ensureFilters();

        // Refresh categoryButtons reference in case we generated them
        this.categoryButtons = document.querySelectorAll('.category-navigation .category-btn');

        // Read keyword from URL and initialize state
        this.keyword = this.getQueryParam('q');
        this.activeCategory = 'all';

        this.setupEventListeners();
        // Ensure nav height is reflected in CSS variable before layout calc
        this.measureNavHeight();
        this.setupMoreToggles();
        this.attachModalHandlers();
        // Use a timeout to ensure images are loaded before calculating heights
        setTimeout(() => {
            this.applyFilters();
            this.equalizeCardHeights();
        }, 300);
        WF.log('Shop Page module initialized.');
    },

    ensureFilters() {
        // If there is no category container, create one in the middle column
        if (!this.categoryNav) {
            const middle = document.querySelector('#shopPage .navigation-bar');
            if (middle) {
                const nav = document.createElement('div');
                nav.className = 'category-navigation';
                // Insert after left column if present, else append
                middle.appendChild(nav);
                this.categoryNav = nav;
            }
        }
        if (!this.categoryNav) return;

        const existing = this.categoryNav.querySelectorAll('.category-btn');
        if (existing.length > 0) return; // Server rendered; keep as-is

        // Build a unique ordered category list from product cards
        const seen = new Map(); // slug -> label
        Array.from(this.productCards).forEach(card => {
            const slug = card.dataset.category;
            if (!slug) return;
            if (!seen.has(slug)) {
                const label = card.dataset.categoryLabel || slug;
                seen.set(slug, label);
            }
        });

        // Always include All Products first
        const frag = document.createDocumentFragment();
        const mkBtn = (slug, label, isActive=false) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'category-btn btn-chip shop-filter-btn' + (isActive ? ' active' : '');
            b.dataset.category = slug;
            b.textContent = label;
            return b;
        };
        frag.appendChild(mkBtn('all', 'All Products', true));
        for (const [slug, label] of seen.entries()) {
            frag.appendChild(mkBtn(slug, label, false));
        }
        this.categoryNav.appendChild(frag);
    },

    setupEventListeners() {
        // Direct listeners on each button
        this.categoryButtons.forEach(btn => {
            btn.addEventListener('click', (e) => this.filterByCategory(e));
        });

        // Delegated listener on the nav area (resilient to reflows/reinits)
        if (this.shopNavArea) {
            this.shopNavArea.addEventListener('click', (e) => {
                const btn = e.target && e.target.closest && e.target.closest('.category-navigation .category-btn');
                if (btn) {
                    e.preventDefault();
                    this.filterByCategory(btn);
                }
            });
        }

        window.addEventListener('resize', debounce(() => {
            this.measureNavHeight();
            this.equalizeCardHeights();
        }, 200));

        // React to URL q= changes (e.g., back/forward)
        window.addEventListener('popstate', () => {
            this.keyword = this.getQueryParam('q');
            this.applyFilters();
            this.equalizeCardHeights();
        });
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
                const stockSrc = (stockAttr != null ? stockAttr : (card.querySelector('.product-stock')?.getAttribute('data-stock')));
                const stockLevel = Number.parseInt(stockSrc, 10);
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
                const stockSrc = (stockAttr != null ? stockAttr : (card.querySelector('.product-stock')?.getAttribute('data-stock')));
                const stockLevel = Number.parseInt(stockSrc, 10);
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
        const stockSrc = (stockAttr != null ? stockAttr : (card.querySelector('.product-stock')?.getAttribute('data-stock')));
        const stockLevel = Number.parseInt(stockSrc, 10);
        if (!Number.isNaN(stockLevel) && stockLevel <= 0) {
            if (typeof window.showNotification === 'function') {
                window.showNotification('This item is out of stock.', { type: 'info' });
            }
            return;
        }

        const name = (card.dataset && card.dataset.name) || (card.querySelector('.product-title')?.textContent || '').trim();
        const priceStr = (card.dataset && card.dataset.price) || '';
        const p = Number.parseFloat(priceStr);
        const price = Number.isFinite(p) ? p : null;
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

    filterByCategory(eventOrButton) {
        const button = (eventOrButton && eventOrButton.currentTarget)
            ? eventOrButton.currentTarget
            : (eventOrButton && eventOrButton.dataset ? eventOrButton : null);
        if (!button) return;
        const category = button.dataset.category;
        WF.log(`Filtering by category: ${category}`);

        this.categoryButtons.forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');
        this.activeCategory = category || 'all';
        this.applyFilters();
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
    },

    // ==============================
    // Keyword filter and fuzzy match
    // ==============================
    getQueryParam(name) {
        try {
            const params = new URLSearchParams(window.location.search || '');
            return (params.get(name) || '').trim();
        } catch { return ''; }
    },

    applyFilters() {
        const term = (this.keyword || '').toLowerCase();
        const hasTerm = term.length > 0;

        // Remove prior recommendation banner
        this.hideRecommendations();

        // First pass: direct keyword + category filter
        let visibleCount = 0;
        this.productCards.forEach(card => {
            const cat = card.dataset.category || '';
            const matchesCat = (this.activeCategory === 'all' || cat === this.activeCategory);
            let matchesTerm = true;
            if (hasTerm) {
                const txtName = (card.dataset.name || (card.querySelector('.product-title')?.textContent || '')).toLowerCase();
                const txtSku = (card.dataset.sku || '').toLowerCase();
                const txtDesc = (card.querySelector('.description-text-full')?.textContent || card.querySelector('.description-text-short')?.textContent || '').toLowerCase();
                const txtCatLabel = (card.dataset.categoryLabel || '').toLowerCase();
                matchesTerm = txtName.includes(term) || txtSku.includes(term) || txtDesc.includes(term) || txtCatLabel.includes(term);
            }
            const show = matchesCat && (!hasTerm || matchesTerm);
            card.classList.toggle('hidden', !show);
            if (show) visibleCount++;
        });

        if (visibleCount === 0 && hasTerm) {
            // No exact matches, compute fuzzy recommendations
            const recs = this.computeFuzzyRecommendations(term, 6);
            this.showRecommendations(term, recs);
        }
    },

    computeFuzzyRecommendations(term, limit = 6) {
        const items = Array.from(this.productCards).map(card => {
            const name = (card.dataset.name || (card.querySelector('.product-title')?.textContent || '')).trim();
            const sku = (card.dataset.sku || '').trim();
            const desc = (card.querySelector('.description-text-full')?.textContent || card.querySelector('.description-text-short')?.textContent || '').trim();
            const text = `${name} ${sku} ${desc}`.toLowerCase();
            const score = this.fuzzyScore(term, text);
            return { card, score, name, sku };
        });
        items.sort((a, b) => b.score - a.score);
        return items.filter(x => x.score > 0).slice(0, limit);
    },

    fuzzyScore(needle, hay) {
        if (!needle || !hay) return 0;
        // Quick boosts
        if (hay.includes(needle)) return 0.9 + Math.min(0.09, needle.length * 0.005);
        const n = needle.toLowerCase();
        const h = hay.toLowerCase();
        // Prefix bonus
        const tokens = h.split(/[^a-z0-9]+/);
        let max = 0;
        for (const t of tokens) {
            if (!t) continue;
            if (t.startsWith(n)) max = Math.max(max, 0.6 + Math.min(0.3, n.length * 0.02));
            // Levenshtein normalized for short tokens
            const d = this.levenshtein(n, t);
            const norm = 1 - (d / Math.max(n.length, t.length));
            if (norm > max) max = norm * 0.8; // dampen
        }
        return Math.max(0, Math.min(1, max));
    },

    levenshtein(a, b) {
        const m = a.length, n = b.length;
        if (m === 0) return n; if (n === 0) return m;
        const dp = new Array(n + 1);
        for (let j = 0; j <= n; j++) dp[j] = j;
        for (let i = 1; i <= m; i++) {
            let prev = i - 1; // dp[i-1][j-1]
            dp[0] = i;
            for (let j = 1; j <= n; j++) {
                const temp = dp[j];
                const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                dp[j] = Math.min(
                    dp[j] + 1,        // deletion
                    dp[j - 1] + 1,    // insertion
                    prev + cost       // substitution
                );
                prev = temp;
            }
        }
        return dp[n];
    },

    ensureRecommendationsContainer() {
        let box = document.getElementById('shopRecommendations');
        if (!box) {
            box = document.createElement('div');
            box.id = 'shopRecommendations';
            box.className = 'wf-shop-recommendations';
            if (this.productsGrid && this.productsGrid.parentNode) {
                this.productsGrid.parentNode.insertBefore(box, this.productsGrid);
            }
        }
        return box;
    },

    hideRecommendations() {
        const box = document.getElementById('shopRecommendations');
        if (box) box.remove();
    },

    async showRecommendations(term, recs) {
        const box = this.ensureRecommendationsContainer();
        if (!recs || recs.length === 0) {
            box.innerHTML = `<div class="text-gray-600">No items matched "${this.escapeHtml(term)}".</div>`;
            return;
        }
        const explain = `We couldn't find exact matches for "${this.escapeHtml(term)}". Here are some recommended items with similar names, SKUs, or descriptions.`;
        box.innerHTML = `<div class="wf-rec-explain-bubble">${explain}</div>`;

        // Hide all cards, then reveal only recommended cards
        const recCards = recs.map(r => r.card);
        this.productCards.forEach(card => card.classList.add('hidden'));
        recCards.forEach(card => card.classList.remove('hidden'));

        // Fetch encouragements and annotate badges on the recommended cards
        const encouragements = await this.loadEncouragements();
        this.applyEncouragementBadges(recCards, encouragements);
    },

    async loadEncouragements() {
        if (this.__encouragements) return this.__encouragements;
        try {
            const data = await apiClient.get('/api/encouragements.php');
            const arr = Array.isArray(data?.phrases) ? data.phrases : [];
            return (this.__encouragements = arr);
        } catch (_) {
            return (this.__encouragements = []);
        }
    },

    applyEncouragementBadges(cards, phrases) {
        if (!cards || cards.length === 0) return;
        const pool = Array.isArray(phrases) && phrases.length ? phrases : [];
        let i = 0;
        cards.forEach(card => {
            // Remove any previous badge
            const existing = card.querySelector('.wf-enc-badge');
            if (existing) existing.remove();
            const badge = document.createElement('span');
            badge.className = 'wf-enc-badge badge';
            const phrase = pool.length ? pool[i++ % pool.length] : '';
            badge.textContent = phrase || 'Recommended for you';
            // Prefer a header area within card, else prepend to card body
            const hook = card.querySelector('.product-card-header') || card.firstElementChild || card;
            hook.insertAdjacentElement('afterbegin', badge);
        });
    },

    escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }
};

// Initialize the module when the core is ready
WF.ready(() => ShopPage.init());

export default ShopPage;

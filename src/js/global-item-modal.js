/**
 * Global Item Details Modal System
 * Unified modal system for displaying detailed item information across shop and room pages
 */

(function() {
    'use strict';

    // Global modal state
    let currentModalItem = null;
    let modalContainer = null;
    // Cleanup hook for header offset listener
    let removeHeaderOffsetListener = null;

    // ----- Internal helpers for detailed modal interactions (migrated from legacy) -----
    function qs(sel, root = document) { return root.querySelector(sel); }
    function qsa(sel, root = document) { return Array.from(root.querySelectorAll(sel)); }

    const optionState = {
        sku: '',
        basePrice: 0,
        item: null,
        colors: [],
        sizes: [],
        genders: [],
        selected: {
            gender: '',
            colorId: '',
            colorName: '',
            colorCode: '',
            sizeName: '',
            sizeCode: '',
            sizeAdj: 0,
            sizeStock: null,
            colorStock: null,
        }
    };

    const currency = (v) => `$${Number(v || 0).toFixed(2)}`;
    function sanitizeCode(value) {
        return String(value || '').trim().toUpperCase().replace(/[^A-Z0-9]+/g, '');
    }
    async function apiGetLocal(url) {
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`Request failed: ${res.status}`);
        return res.json();
    }
    function setBtnEnabled(modal, enabled) {
        const btn = qs('[data-action="addDetailedToCart"]', modal);
        if (!btn) return;
        btn.disabled = !enabled;
        btn.classList.toggle('opacity-50', !enabled);
        btn.classList.toggle('cursor-not-allowed', !enabled);
        btn.setAttribute('aria-disabled', String(!enabled));
    }
    function updateQtyMax(modal) {
        const input = qs('#detailedQuantity', modal);
        if (!input) return;
        let stockVal = optionState.selected.sizeStock;
        if (stockVal === null || typeof stockVal === 'undefined' || Number.isNaN(Number(stockVal))) {
            stockVal = optionState.selected.colorStock;
        }
        if (stockVal === null || typeof stockVal === 'undefined' || Number.isNaN(Number(stockVal))) {
            stockVal = Number(optionState.item?.stockLevel);
        }
        if (!Number.isFinite(stockVal)) stockVal = 99;
        if (Number(stockVal) <= 0) {
            input.min = '0';
            input.max = '0';
            input.value = '0';
            setBtnEnabled(modal, false);
            return;
        }
        input.min = '1';
        input.max = String(stockVal);
        const clamped = Math.max(1, Math.min(Number(input.value || 1), Number(stockVal)));
        input.value = String(clamped);
    }
    function updateDisplayedPrice(modal) {
        const base = Number(
            optionState.item?.price ?? optionState.item?.currentPrice ?? optionState.item?.retailPrice ?? optionState.item?.originalPrice ?? 0
        );
        const adj = Number(optionState.selected.sizeAdj || 0);
        const currentEl = qs('#detailedCurrentPrice', modal);
        if (currentEl) currentEl.textContent = currency(base + adj);
    }
    function validateSelections(modal) {
        const required = qsa('.required-field', modal).filter((el) => !el.closest('.hidden'));
        const allSelected = required.every((sel) => sel.value && sel.value !== '');
        setBtnEnabled(modal, allSelected);
        return allSelected;
    }
    function populateSelect(select, items, map, placeholder) {
        if (!select) return;
        const prev = select.value;
        select.innerHTML = '';
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder || 'Select an option...';
        select.appendChild(ph);
        items.forEach((it) => {
            const opt = document.createElement('option');
            const { value, label } = map(it);
            const stockNum = Number(it && typeof it.stock_level !== 'undefined' ? it.stock_level : NaN);
            const isOut = Number.isFinite(stockNum) && stockNum <= 0;
            opt.value = value;
            opt.textContent = isOut ? `${label} (Out of stock)` : label;
            if (isOut) { opt.disabled = true; opt.classList.add('option-out-of-stock'); }
            Object.entries(it).forEach(([k, v]) => { try { opt.dataset[k] = v; } catch(_) {} });
            select.appendChild(opt);
        });
        const canRestore = Array.from(select.options).some(o => o.value === prev);
        if (canRestore) select.value = prev; else select.value = '';
    }
    async function loadGenders(modal, sku) {
        try {
            const data = await apiGetLocal(`/api/item_genders.php?action=get_all&item_sku=${encodeURIComponent(sku)}`);
            const genders = Array.isArray(data?.genders) ? data.genders : [];
            optionState.genders = genders;
            const container = qs('#genderSelection', modal);
            const select = qs('#itemGenderSelect', modal);
            if (genders.length > 0) { populateSelect(select, genders, (g) => ({ value: g.gender, label: g.gender }), 'Select a style...'); container?.classList.remove('hidden'); }
            else { container?.classList.add('hidden'); }
        } catch (e) { qs('#genderSelection', modal)?.classList.add('hidden'); }
    }
    async function loadSizes(modal, sku, colorId) {
        try {
            const queryColor = colorId ? encodeURIComponent(colorId) : '0';
            const data = await apiGetLocal(`/api/item_sizes.php?action=get_sizes&item_sku=${encodeURIComponent(sku)}&color_id=${queryColor}`);
            const sizes = Array.isArray(data?.sizes) ? data.sizes : [];
            optionState.sizes = sizes;
            const container = qs('#sizeSelection', modal);
            const select = qs('#itemSizeSelect', modal);
            if (sizes.length > 0) { populateSelect(select, sizes, (s) => ({ value: s.size_code, label: s.size_name }), 'Select a size...'); container?.classList.remove('hidden'); }
            else { container?.classList.add('hidden'); }
            const info = qs('#sizeStockInfo', modal);
            const chosen = sizes.find(s => String(s.size_code) === String(select?.value || ''));
            if (info) info.textContent = chosen ? (Number(chosen.stock_level) > 0 ? `In stock: ${chosen.stock_level}` : 'Out of stock') : '';
            if (chosen) { optionState.selected.sizeAdj = Number(chosen.price_adjustment || 0); optionState.selected.sizeStock = Number(chosen.stock_level); }
            else { optionState.selected.sizeAdj = 0; optionState.selected.sizeStock = null; }
            updateDisplayedPrice(modal); updateQtyMax(modal);
        } catch (e) { qs('#sizeSelection', modal)?.classList.add('hidden'); }
    }
    async function loadColors(modal, sku) {
        try {
            const data = await apiGetLocal(`/api/item_colors.php?action=get_colors&item_sku=${encodeURIComponent(sku)}`);
            const colors = Array.isArray(data?.colors) ? data.colors : [];
            optionState.colors = colors;
            const container = qs('#colorSelection', modal);
            const select = qs('#itemColorSelect', modal);
            if (colors.length > 0) { populateSelect(select, colors, (c) => ({ value: String(c.id), label: c.color_name }), 'Select a color...'); container?.classList.remove('hidden'); }
            else { container?.classList.add('hidden'); }
            const info = qs('#colorStockInfo', modal);
            const chosen = colors.find(c => String(c.id) === String(select?.value || ''));
            if (info) info.textContent = chosen ? (Number(chosen.stock_level) > 0 ? `In stock: ${chosen.stock_level}` : 'Out of stock') : '';
            const colorId = select?.value || '';
            await loadSizes(modal, sku, colorId);
        } catch (e) {
            qs('#colorSelection', modal)?.classList.add('hidden');
            await loadSizes(modal, sku, '');
        }
    }
    async function initOptions(modal, sku, item) {
        optionState.sku = sku;
        optionState.item = item || {};
        optionState.basePrice = Number(item?.price ?? item?.currentPrice ?? item?.retailPrice ?? item?.originalPrice ?? 0);
        optionState.selected = { gender: '', colorId: '', colorName: '', colorCode: '', sizeName: '', sizeCode: '', sizeAdj: 0, sizeStock: null, colorStock: null };
        ['#itemGenderSelect', '#itemColorSelect', '#itemSizeSelect'].forEach(id => { const el = qs(id, modal); if (el) el.value = ''; });
        setBtnEnabled(modal, true);
        await loadGenders(modal, sku);
        await loadColors(modal, sku);
        updateDisplayedPrice(modal);
        updateQtyMax(modal);
        validateSelections(modal);
    }
    function bindCloseHandlers(modal) {
        qsa('[data-action="closeDetailedModal"]', modal).forEach(btn => {
            btn.addEventListener('click', (e) => { e.preventDefault(); try { closeGlobalItemModal(); } catch(_) {} });
        });
        modal.addEventListener('click', (e) => {
            const clickedOutside = !e.target.closest('.detailed-item-modal-container');
            const isOverlay = e.target === modal || e.target?.dataset?.action === 'closeDetailedModalOnOverlay' || clickedOutside;
            if (!isOverlay) return;
            try { closeGlobalItemModal(); } catch (_) {}
        });
        function onKeyDown(ev) { if (ev.key === 'Escape') { try { closeGlobalItemModal(); } catch(_) {} document.removeEventListener('keydown', onKeyDown); } }
        document.addEventListener('keydown', onKeyDown);
    }
    function bindContentInteractions(modal, item) {
        modal.addEventListener('click', (e) => {
            const target = e.target.closest('[data-action="switchDetailedImage"]');
            if (!target) return;
            try { const params = JSON.parse(target.getAttribute('data-params') || '{}'); const url = params.url; const main = qs('#detailedMainImage', modal); if (main && url) main.src = url; } catch(_) {}
        });
        modal.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action="adjustDetailedQuantity"]');
            if (!btn) return; e.preventDefault();
            try { const params = JSON.parse(btn.getAttribute('data-params') || '{}'); const delta = Number(params.delta || 0); const input = qs('#detailedQuantity', modal); if (!input) return; const min = Number(input.getAttribute('min') || 1); const max = Number(input.getAttribute('max') || 99); const next = Math.max(min, Math.min(max, Number(input.value || 1) + delta)); input.value = String(next); } catch(_) {}
        });
        modal.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-action="addDetailedToCart"]');
            if (!btn) return; e.preventDefault();
            try {
                const params = JSON.parse(btn.getAttribute('data-params') || '{}');
                const sku = params.sku || item?.sku;
                const qtyEl = qs('#detailedQuantity', modal);
                const qty = Math.max(1, Number(qtyEl?.value || 1));
                if (!sku) return;
                if (!validateSelections(modal)) {
                    qsa('.required-field', modal).forEach((el) => { if (!el.value && !el.closest('.hidden')) { el.classList.add('ring-2', 'ring-red-300'); setTimeout(() => el.classList.remove('ring-2', 'ring-red-300'), 1000); } });
                    return;
                }
                if (window.WF_Cart && typeof window.WF_Cart.addItem === 'function') {
                    const basePrice = Number(item?.price ?? item?.currentPrice ?? item?.retailPrice ?? item?.originalPrice ?? 0);
                    const selectedColor = optionState.colors.find(c => String(c.id) === String(qs('#itemColorSelect', modal)?.value || ''));
                    const selectedSize = optionState.sizes.find(s => String(s.size_code) === String(qs('#itemSizeSelect', modal)?.value || ''));
                    const selectedGender = String(qs('#itemGenderSelect', modal)?.value || '').trim();
                    const priceAdj = Number(selectedSize?.price_adjustment || 0);
                    const price = basePrice + priceAdj;
                    const name = item?.name ?? item?.title ?? '';
                    const imageCandidate = Array.isArray(item?.images) ? (item.images[0]?.url ?? item.images[0]) : undefined;
                    const colorImage = selectedColor?.image_path ? selectedColor.image_path : undefined;
                    const image = colorImage ?? item?.image ?? imageCandidate ?? '';
                    const parts = [sku];
                    if (selectedGender) parts.push(`G${sanitizeCode(selectedGender)}`);
                    if (selectedSize?.size_code) parts.push(`S${sanitizeCode(selectedSize.size_code)}`);
                    if (selectedColor?.id) parts.push(`C${String(selectedColor.id)}`);
                    const cartSku = parts.join('-');
                    const payload = { ...(item || {}), sku: cartSku, baseSku: sku, quantity: qty, price, name, image,
                        optionGender: selectedGender || undefined, optionSize: selectedSize?.size_name || undefined, optionSizeCode: selectedSize?.size_code || undefined,
                        optionColor: selectedColor?.color_name || undefined, optionColorCode: selectedColor?.color_code || undefined, optionColorId: selectedColor?.id || undefined };
                    await window.WF_Cart.addItem(payload);
                } else if (typeof window.addToCart === 'function') {
                    window.addToCart(sku, qty);
                } else {
                    console.log('[GlobalModal] Add to cart:', { sku, quantity: qty, item });
                }
                try { closeGlobalItemModal(); } catch(_) {}
            } catch (err) { console.error('[GlobalModal] add to cart failed', err); }
        });
        if (item) {
            const currentEl = qs('#detailedCurrentPrice');
            const originalEl = qs('#detailedOriginalPrice');
            const savingsEl = qs('#detailedSavings');
            if (currentEl && originalEl && savingsEl) {
                const original = Number(item?.originalPrice ?? item?.retailPrice ?? item?.price ?? 0);
                const current = Number(item?.price ?? item?.currentPrice ?? item?.retailPrice ?? original);
                currentEl.textContent = `$${current.toFixed(2)}`;
                const onSale = original > current;
                originalEl.textContent = `$${original.toFixed(2)}`;
                savingsEl.textContent = `Save $${(original - current).toFixed(2)}`;
                originalEl.classList.toggle('hidden', !onSale);
                savingsEl.classList.toggle('hidden', !onSale);
            }
        }
        if (!modal.dataset.optionHandlersBound) {
            modal.addEventListener('change', async (e) => {
                const colorSel = e.target && e.target.closest && e.target.closest('#itemColorSelect');
                const sizeSel = e.target && e.target.closest && e.target.closest('#itemSizeSelect');
                const genderSel = e.target && e.target.closest && e.target.closest('#itemGenderSelect');
                if (genderSel) { optionState.selected.gender = String(genderSel.value || ''); validateSelections(modal); }
                if (colorSel) {
                    const val = String(colorSel.value || '');
                    const picked = optionState.colors.find(c => String(c.id) === val);
                    optionState.selected.colorId = val;
                    optionState.selected.colorName = picked?.color_name || '';
                    optionState.selected.colorCode = picked?.color_code || '';
                    optionState.selected.colorStock = picked ? Number(picked.stock_level || 0) : null;
                    const info = qs('#colorStockInfo', modal);
                    if (info) info.textContent = picked ? (Number(picked.stock_level) > 0 ? `In stock: ${picked.stock_level}` : 'Out of stock') : '';
                    const main = qs('#detailedMainImage', modal);
                    if (main && picked?.image_path) main.src = picked.image_path;
                    await loadSizes(modal, optionState.sku, val);
                    validateSelections(modal); updateQtyMax(modal);
                }
                if (sizeSel) {
                    const code = String(sizeSel.value || '');
                    const chosen = optionState.sizes.find(s => String(s.size_code) === code);
                    optionState.selected.sizeName = chosen?.size_name || '';
                    optionState.selected.sizeCode = chosen?.size_code || '';
                    optionState.selected.sizeAdj = Number(chosen?.price_adjustment || 0);
                    optionState.selected.sizeStock = chosen ? Number(chosen.stock_level || 0) : null;
                    const info = qs('#sizeStockInfo', modal);
                    if (info) info.textContent = chosen ? (Number(chosen.stock_level) > 0 ? `In stock: ${chosen.stock_level}` : 'Out of stock') : '';
                    updateDisplayedPrice(modal); validateSelections(modal); updateQtyMax(modal);
                }
            });
            modal.dataset.optionHandlersBound = '1';
        }
    }

    /**
     * Initialize the global modal system
     */
    function initGlobalModal() {
        // Create modal container if it doesn't exist
        if (!document.getElementById('globalModalContainer')) {
            modalContainer = document.createElement('div');
            modalContainer.id = 'globalModalContainer';
            document.body.appendChild(modalContainer);
        } else {
            modalContainer = document.getElementById('globalModalContainer');
        }
    }

    /**
     * Show the global item details modal
     * @param {string} sku - The item SKU
     * @param {object} itemData - Optional pre-loaded item data
     */
    async function showGlobalItemModal(sku, itemData = null) {
        console.log('🔧 showGlobalItemModal called with SKU:', sku, 'itemData:', itemData);
        try {
            // Initialize modal container
            initGlobalModal();
            console.log('🔧 Modal container initialized');

            let item, images;

            if (itemData) {
                // Use provided data
                item = itemData;
                images = itemData.images || [];
                console.log('🔧 Using provided item data:', item);
            } else {
                // Fetch item data from API
                console.log('🔧 Fetching item data from API for SKU:', sku);
                // Use inventory.php and filter for specific SKU
                const allItems = await apiGet(`/api/inventory.php`);
                console.log('🔧 API response - found', allItems.length, 'items');
                
                // Find the specific item by SKU
                item = allItems.find(item => item.sku === sku);
                if (!item) {
                    throw new Error(`Item with SKU ${sku} not found`);
                }
                
                // Set images array (empty for now, could be enhanced later)
                images = [];
                
                console.log('🔧 Item data loaded:', item);
                console.log('Item data loaded:', item);
                console.log('Item stock level:', item.stockLevel);
                console.log('All item fields:', Object.keys(item));
                console.log('Full item data:', JSON.stringify(item, null, 2));
                console.log('Images loaded:', images.length, 'images');
            }

            // Remove any existing modal
            const existingModal = document.getElementById('detailedItemModal');
            if (existingModal) {
                console.log('🔧 Removing existing modal');
                existingModal.remove();
            }

            // Get the modal HTML from the API
            console.log('Sending item to modal with stockLevel:', item.stockLevel);
            console.log('Full item being sent:', item);
            const modalHtml = await apiPost('render_detailed_modal.php', {
                item: item,
                images: images
            });

            if (!modalHtml || typeof modalHtml !== 'string') {
                throw new Error('Modal render API returned invalid HTML');
            }
            console.log('🔧 Modal HTML received, length:', modalHtml.length);
            console.log('🔧 Modal HTML preview:', modalHtml.substring(0, 200) + '...');
            
            // Insert the modal into the container
            modalContainer.innerHTML = modalHtml;
            console.log('🔧 Modal HTML inserted into container');
            
            // Check if modal element was created
            const insertedModal = document.getElementById('detailedItemModal');
            console.log('🔧 Modal element found after insertion:', !!insertedModal);
            // Ensure modal respects page header height via class (no inline styles)
            if (insertedModal) {
                try { insertedModal.classList.add('admin-modal-offset-under-header'); } catch (_) {}
            }
            
            // All inline scripts have been removed from the modal component.
            // The required logic is provided by the canonical detailed modal script,
            // which is now bundled via Vite (imported in src/js/app.js).

            // Store current item data
            currentModalItem = item;
            window.currentDetailedItem = item; // Make it available to the modal script
            console.log('🔧 Current modal item stored');
            
            // Initialize and show the detailed modal using internal logic (no legacy dependency)
            console.log('🔧 Attempting to show modal with internal handlers...');
            const modal = document.getElementById('detailedItemModal');
            if (modal) {
                // ARIA and visibility
                try { window.WFModalUtils && window.WFModalUtils.ensureOnBody && window.WFModalUtils.ensureOnBody(modal); } catch(_) {}
                modal.setAttribute('role', 'dialog');
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('aria-hidden', 'false');
                modal.classList.remove('hidden');
                modal.classList.add('show');
                if (window.WFModals && typeof window.WFModals.lockScroll === 'function') {
                    window.WFModals.lockScroll();
                }
                const focusTarget = qs('[data-action="closeDetailedModal"]', modal) || modal;
                try { focusTarget.focus && focusTarget.focus(); } catch (_) {}

                if (!modal.dataset.interactionsBound) {
                    bindCloseHandlers(modal);
                    bindContentInteractions(modal, item);
                    modal.dataset.interactionsBound = '1';
                }
                try { await initOptions(modal, sku, item); } catch (e) { console.error('[GlobalModal] options init failed', e); }
            }
            // Initialize enhanced modal content after modal is shown
            setTimeout(() => {
                if (typeof window.initializeEnhancedModalContent === 'function') {
                    window.initializeEnhancedModalContent();
                }
            }, 100);
            
        } catch (error) {
            console.error('🔧 Error in showGlobalItemModal:', error);
            // Show user-friendly error
            if (typeof window.showError === 'function') {
                window.showError('Unable to load item details. Please try again.');
            } else {
                alert('Unable to load item details. Please try again.');
            }
        }
    }

    /**
     * Close the global item modal
     */
    function closeGlobalItemModal() {
        console.log('[GlobalModal] close:start');
        const modal = document.getElementById('detailedItemModal');
        if (modal) {
            try { modal.setAttribute('aria-hidden', 'true'); } catch(_) {}
            modal.remove(); // Use remove() for simplicity
            console.log('[GlobalModal] close:removed');
        } else {
            console.log('[GlobalModal] close:no-modal-found');
        }
        // Remove header offset listener if present
        if (typeof removeHeaderOffsetListener === 'function') {
            try { removeHeaderOffsetListener(); } catch (_) {}
            removeHeaderOffsetListener = null;
        }
        // Unlock scroll if no other modals are open
        if (window.WFModals && typeof window.WFModals.unlockScrollIfNoneOpen === 'function') {
            window.WFModals.unlockScrollIfNoneOpen();
            console.log('[GlobalModal] close:unlocked-scroll-if-none-open');
        }
        
        // Clear current item data
        currentModalItem = null;
        console.log('[GlobalModal] close:done');
    }

    /**
     * Closes the modal only if the overlay itself is clicked.
     * @param {Event} event - The click event.
     */
    function closeDetailedModalOnOverlay(event) {
        if (event.target.id === 'detailedItemModal') {
            closeGlobalItemModal();
        }
    }

    /**
     * Get current modal item data
     */
    function getCurrentModalItem() {
        return currentModalItem;
    }

    /**
     * Quick add to cart from popup (for room pages)
     * @param {object} item - Item data from popup
     */
    function quickAddToCart(item) {
        // Hide any popup first
        if (typeof window.hidePopupImmediate === 'function') {
            window.hidePopupImmediate();
        }
        
        // Show the detailed modal for quantity/options selection
        showGlobalItemModal(item.sku, item);
    }

    /**
     * Initialize the global modal system when DOM is ready
     */
    function init() {
        initGlobalModal();

        // Expose public functions
        window.WhimsicalFrog = window.WhimsicalFrog || {};
        window.WhimsicalFrog.GlobalModal = {
            show: showGlobalItemModal,
            close: closeGlobalItemModal,
            closeOnOverlay: closeDetailedModalOnOverlay,
            getCurrentItem: getCurrentModalItem,
            quickAddToCart: quickAddToCart
        };
    }

    // loadScript helper removed as the detailed modal script is bundled via Vite and loaded with app.js

    // Removed setupHeaderOffset (inline CSS variable writes). Using CSS class instead.

    // Initialize on load or immediately if DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Legacy compatibility - these functions will call the new global system
    window.showGlobalItemModal = showGlobalItemModal; // Needed for popup click handler
    window.showItemDetails = showGlobalItemModal;
    window.showItemDetailsModal = showGlobalItemModal; // Added alias for legacy support
    window.showDetailedModal = showGlobalItemModal;
    window.closeDetailedModal = closeGlobalItemModal;
    window.closeDetailedModalOnOverlay = closeDetailedModalOnOverlay;
    window.openQuantityModal = quickAddToCart;

    console.log('Global Item Modal system loaded');
})(); 
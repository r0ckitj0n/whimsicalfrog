/**
 * Global Item Details Modal System
 * Unified modal system for displaying detailed item information across shop and room pages
 */

(function() {
    'use strict';

    // Global modal state
    let currentModalItem = null;
    let modalContainer = null;

    // ----- Internal helpers for detailed modal interactions (migrated from legacy) -----
    function qs(sel, root = document) { return root.querySelector(sel); }
    function qsa(sel, root = document) { return Array.from(root.querySelectorAll(sel)); }

    let optionState = {
        sku: '',
        basePrice: 0,
        item: null,
        colors: [],
        sizes: [],
        sizesAll: [], // cache of all sizes across colors for this item
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
    // Simple full-screen image viewer overlay
    function openImageViewer(src, alt) {
        try {
            const overlay = document.createElement('div');
            overlay.className = 'image-viewer-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-label', 'Image viewer');
            const img = new Image();
            img.src = src;
            if (alt) img.alt = alt;
            overlay.appendChild(img);
            const cleanup = () => {
                try {
                    if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
                    if (overlay._esc) document.removeEventListener('keydown', overlay._esc);
                } catch(_) {}
            };
            overlay.addEventListener('click', (e) => {
                e.preventDefault();
                cleanup();
            });
            overlay._esc = (e) => {
                const key = e.key || e.keyCode;
                if (key === 'Escape' || key === 'Esc' || key === 27) {
                    e.preventDefault();
                    cleanup();
                }
            };
            document.addEventListener('keydown', overlay._esc);
            document.body.appendChild(overlay);
        } catch (err) {
            console.error('[GlobalModal] openImageViewer failed', err);
        }
    }
    async function apiGetLocal(url) {
        if (!window.ApiClient || typeof window.ApiClient.request !== 'function') throw new Error('ApiClient unavailable');
        const data = await window.ApiClient.request(url, { method: 'GET' });
        return data;
    }
    async function apiPost(url, data) {
        if (!window.ApiClient || typeof window.ApiClient.request !== 'function') throw new Error('ApiClient unavailable');
        return window.ApiClient.request(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    }
    function setBtnEnabled(modal, enabled) {
        const btn = qs('[data-action="addDetailedToCart"]', modal);
        if (!btn) return;
        btn.disabled = !enabled;
        btn.classList.toggle('opacity-50', !enabled);
        btn.classList.toggle('cursor-not-allowed', !enabled);
        btn.setAttribute('aria-disabled', String(!enabled));
    }
    // Helper to disable/enhance selects consistently
    function setDisabled(el, disabled) {
        if (!el) return;
        el.disabled = !!disabled;
        el.classList.toggle('opacity-50', !!disabled);
        el.classList.toggle('cursor-not-allowed', !!disabled);
        el.setAttribute('aria-disabled', String(!!disabled));
    }
    function updateQtyMax(modal) {
        const input = qs('#detailedQuantity', modal);
        if (!input) return;
        // Determine the most specific stock available
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
            // Hide options that are out of stock
            if (isOut) return;
            opt.value = value;
            // Build label with per-option stock count when available
            if (Number.isFinite(stockNum)) {
                opt.textContent = `${label} (${Math.max(0, stockNum)} in stock)`;
            } else {
                opt.textContent = label;
            }
            Object.entries(it).forEach(([k, v]) => { try { opt.dataset[k] = v; } catch(_) {} });
            select.appendChild(opt);
        });
        const canRestore = Array.from(select.options).some(o => o.value === prev);
        if (canRestore) select.value = prev; else select.value = '';
    }

    // Recompute color option labels/disabled state based on a chosen size (if any)
    function refreshColorLabelsForSize(modal) {
        const colorSelect = qs('#itemColorSelect', modal);
        const sizeCode = optionState.selected.sizeCode || '';
        if (!colorSelect) return;
        Array.from(colorSelect.options).forEach((opt) => {
            if (!opt.value) return; // skip placeholder
            const colorId = opt.value;
            let stockForThis = null;
            if (sizeCode) {
                const rec = optionState.sizesAll.find(s => String(s.size_code) === String(sizeCode) && String(s.color_id || '') === String(colorId));
                if (rec) stockForThis = Number(rec.stock_level || 0);
            }
            // Base label from dataset or existing text before parens
            const baseLabel = opt.dataset.color_name || opt.textContent.replace(/\s*\([^)]*\)\s*$/, '');
            if (sizeCode) {
                // When a size is chosen, color availability depends on having that size record
                if (stockForThis === null) {
                    // This color does not carry the selected size at all
                    opt.textContent = `${baseLabel} (Not available for this size)`;
                    opt.disabled = true;
                    opt.classList.add('option-out-of-stock');
                } else {
                    opt.textContent = `${baseLabel} (${Math.max(0, stockForThis)} in stock)`;
                    opt.disabled = stockForThis <= 0;
                    opt.classList.toggle('option-out-of-stock', stockForThis <= 0);
                }
            } else {
                // If no size chosen, prefer color-level stock when available on dataset
                const colorStock = Number(opt.dataset.stock_level || NaN);
                if (Number.isFinite(colorStock)) {
                    opt.textContent = `${baseLabel} (${Math.max(0, colorStock)} in stock)`;
                    opt.disabled = colorStock <= 0;
                    opt.classList.toggle('option-out-of-stock', colorStock <= 0);
                } else {
                    opt.textContent = baseLabel;
                    opt.disabled = false;
                    opt.classList.remove('option-out-of-stock');
                }
            }
        });
    }

    // After selecting a color, sizes are already filtered; ensure each size option label shows stock and disable if 0
    function refreshSizeLabels(modal) {
        const sizeSelect = qs('#itemSizeSelect', modal);
        if (!sizeSelect) return;
        Array.from(sizeSelect.options).forEach((opt) => {
            if (!opt.value) return;
            const sizeCode = opt.value;
            // Find current size record in optionState.sizes (already filtered by color)
            const rec = optionState.sizes.find(s => String(s.size_code) === String(sizeCode));
            const stock = rec ? Number(rec.stock_level || 0) : NaN;
            const baseLabel = (rec?.size_name) || opt.textContent.replace(/\s*\([^)]*\)\s*$/, '');
            if (Number.isFinite(stock)) {
                opt.textContent = `${baseLabel} (${Math.max(0, stock)} in stock)`;
                opt.disabled = stock <= 0;
                opt.classList.toggle('option-out-of-stock', stock <= 0);
            } else {
                opt.textContent = baseLabel;
            }
        });
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
            // If genders exist, require gender before size/color
            if (genders.length > 0) {
                setDisabled(qs('#itemSizeSelect', modal), true);
                setDisabled(qs('#itemColorSelect', modal), true);
            }
            // Restore last-selected gender for this SKU if available
            try {
                const key = `wf:lastGender:${sku}`;
                const last = localStorage.getItem(key);
                if (last && select) {
                    const has = Array.from(select.options).some(o => o.value === last);
                    if (has) {
                        select.value = last;
                        optionState.selected.gender = last;
                        // Enable selects since we have a selected gender
                        setDisabled(qs('#itemSizeSelect', modal), false);
                        setDisabled(qs('#itemColorSelect', modal), false);
                    }
                }
            } catch(_) {}
        } catch (e) { qs('#genderSelection', modal)?.classList.add('hidden'); }
    }
    // Load all sizes once; filter client-side for color intersection
    async function loadAllSizes(modal, sku) {
        const gender = String(optionState.selected.gender || '').trim();
        const url = `/api/item_sizes.php?action=get_sizes&item_sku=${encodeURIComponent(sku)}` + (gender ? `&gender=${encodeURIComponent(gender)}` : '');
        const data = await apiGetLocal(url);
        optionState.sizesAll = Array.isArray(data?.sizes) ? data.sizes : [];
    }
    function refreshSizeOptions(modal) {
        const container = qs('#sizeSelection', modal);
        const select = qs('#itemSizeSelect', modal);
        const currentColorId = optionState.selected.colorId || '';
        // Filter: either specific color match or general (null) when no color chosen
        const filtered = optionState.sizesAll.filter((s) => {
            if (!currentColorId) return (s.color_id === null || typeof s.color_id === 'undefined');
            return String(s.color_id || '') === String(currentColorId);
        });
        // Deduplicate by size_code, prefer exact gender match over unisex
        const selectedGender = String(optionState.selected.gender || '').trim();
        const byCode = new Map();
        filtered.forEach((s) => {
            const key = String(s.size_code || '').trim();
            if (!key) return;
            const existing = byCode.get(key);
            if (!existing) {
                byCode.set(key, s);
                return;
            }
            // Preference order: exact gender match > unisex > anything else
            const existingPriority = (existing.gender && selectedGender && existing.gender === selectedGender) ? 2 : (existing.gender == null ? 1 : 0);
            const candidatePriority = (s.gender && selectedGender && s.gender === selectedGender) ? 2 : (s.gender == null ? 1 : 0);
            if (candidatePriority > existingPriority) byCode.set(key, s);
        });
        const deduped = Array.from(byCode.values());
        optionState.sizes = deduped;
        if (deduped.length > 0) {
            populateSelect(select, deduped, (s) => ({ value: s.size_code, label: s.size_name }), 'Select a size...');
            container?.classList.remove('hidden');
        } else {
            container?.classList.add('hidden');
        }
        // Reset size specifics on color change
        optionState.selected.sizeName = '';
        optionState.selected.sizeCode = '';
        optionState.selected.sizeAdj = 0;
        optionState.selected.sizeStock = null;
        const info = qs('#sizeStockInfo', modal);
        if (info) info.textContent = '';
        updateDisplayedPrice(modal);
        updateQtyMax(modal);
    }
    async function loadSizes(modal, sku) {
        try {
            await loadAllSizes(modal, sku);
            refreshSizeOptions(modal);
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
            if (info) info.textContent = '';
            // If genders exist and not selected yet, keep color disabled
            if ((optionState.genders || []).length > 0 && !optionState.selected.gender) {
                setDisabled(select, true);
            }
            await loadSizes(modal, sku);
        } catch (e) {
            qs('#colorSelection', modal)?.classList.add('hidden');
            await loadSizes(modal, sku);
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
        // Prevent clicks within the content container from closing the modal
        const content = qs('.detailed-item-modal-container', modal);
        if (content && !content.dataset.stopPropBound) {
            content.addEventListener('click', (e) => {
                // Any click inside content should not reach the backdrop handler
                e.stopPropagation();
            });
            content.dataset.stopPropBound = '1';
        }
    
        // Clicking anywhere on the backdrop (outside content) closes the modal
        if (!modal.dataset.backdropCloseBound) {
            modal.addEventListener('click', (e) => {
                const clickedInsideContent = !!(e.target && (e.target === content || (content && content.contains(e.target))));
                if (!clickedInsideContent) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('[GlobalModal] Backdrop click detected, closing modal');
                    closeGlobalItemModal();
                }
            });
            modal.dataset.backdropCloseBound = '1';
        }
    
        // Close button handlers
        qsa('[data-action="closeDetailedModal"]', modal).forEach(btn => {
            if (!btn.dataset.closeHandlerBound) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    closeGlobalItemModal();
                });
                btn.dataset.closeHandlerBound = '1';
            }
        });
    
        // Handle quantity adjustment
        qsa('[data-action="adjustDetailedQuantity"]', modal).forEach(btn => {
            if (!btn.dataset.qtyHandlerBound) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const params = JSON.parse(btn.getAttribute('data-params') || '{}');
                    const delta = Number(params.delta || 0);
                    const qtyEl = qs('#detailedQuantity', modal);
                    if (qtyEl) {
                        const current = Number(qtyEl.value || 1);
                        const max = Number(qtyEl.max || 99);
                        const min = Number(qtyEl.min || 1);
                        const newVal = Math.max(min, Math.min(max, current + delta));
                        qtyEl.value = String(newVal);
                    }
                });
                btn.dataset.qtyHandlerBound = '1';
            }
        });
    
        // Add to cart handler
        qsa('[data-action="addDetailedToCart"]', modal).forEach(btn => {
            if (!btn.dataset.cartHandlerBound) {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    try {
                        const params = JSON.parse(btn.getAttribute('data-params') || '{}');
                        const sku = params.sku || currentModalItem?.sku;
                        const qtyEl = qs('#detailedQuantity', modal);
                        const qty = Math.max(1, Number(qtyEl?.value || 1));
                        if (!sku) return;
                        if (!validateSelections(modal)) {
                            qsa('.required-field', modal).forEach((el) => {
                                if (!el.value && !el.closest('.hidden')) {
                                    el.classList.add('ring-2', 'ring-red-300');
                                    setTimeout(() => el.classList.remove('ring-2', 'ring-red-300'), 1000);
                                }
                            });
                            return;
                        }
                        const item = currentModalItem;
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
                            const payload = {
                                ...(item || {}),
                                sku: cartSku,
                                baseSku: sku,
                                quantity: qty,
                                price,
                                name,
                                image,
                                optionGender: selectedGender || undefined,
                                optionSize: selectedSize?.size_name || undefined,
                                optionSizeCode: selectedSize?.size_code || undefined,
                                optionColor: selectedColor?.color_name || undefined,
                                optionColorCode: selectedColor?.color_code || undefined,
                                optionColorId: selectedColor?.id || undefined
                            };
                            await window.WF_Cart.addItem(payload);
                        } else if (typeof window.addToCart === 'function') {
                            window.addToCart(sku, qty);
                        } else {
                            console.log('[GlobalModal] Add to cart:', { sku, quantity: qty, item });
                        }
                        try { closeGlobalItemModal(); } catch(_) {}
                    } catch (err) {
                        console.error('[GlobalModal] add to cart failed', err);
                    }
                });
                btn.dataset.cartHandlerBound = '1';
            }
        });
    
        // Option change handlers (e.g., color, size, gender selection)
        if (!modal.dataset.optionHandlersBound) {
            modal.addEventListener('change', async (e) => {
                const colorSel = e.target && e.target.closest && e.target.closest('#itemColorSelect');
                const sizeSel = e.target && e.target.closest && e.target.closest('#itemSizeSelect');
                const genderSel = e.target && e.target.closest && e.target.closest('#itemGenderSelect');
                if (genderSel) {
                    optionState.selected.gender = String(genderSel.value || '');
                    // Persist selection per SKU
                    try { localStorage.setItem(`wf:lastGender:${optionState.sku}`, optionState.selected.gender || ''); } catch(_) {}
                    // Enable next selects only when gender chosen
                    if (optionState.selected.gender) {
                        setDisabled(qs('#itemSizeSelect', modal), false);
                        setDisabled(qs('#itemColorSelect', modal), false);
                    } else {
                        const requireGender = (optionState.genders || []).length > 0;
                        setDisabled(qs('#itemSizeSelect', modal), requireGender);
                        setDisabled(qs('#itemColorSelect', modal), requireGender);
                    }
                    // On gender change, reset downstream selections and refresh labels
                    const sizeSelect = qs('#itemSizeSelect', modal);
                    const colorSelect = qs('#itemColorSelect', modal);
                    if (sizeSelect) sizeSelect.value = '';
                    if (colorSelect) colorSelect.value = '';
                    optionState.selected.sizeName = '';
                    optionState.selected.sizeCode = '';
                    optionState.selected.sizeAdj = 0;
                    optionState.selected.sizeStock = null;
                    optionState.selected.colorId = '';
                    optionState.selected.colorStock = null;
                    // Re-fetch sizes with gender filter applied
                    try { await loadAllSizes(modal, optionState.sku); } catch(_) {}
                    refreshSizeOptions(modal);
                    refreshColorLabelsForSize(modal);
                    updateQtyMax(modal);
                    validateSelections(modal);
                }
                if (colorSel) {
                    const val = String(colorSel.value || '');
                    const picked = optionState.colors.find(c => String(c.id) === val);
                    optionState.selected.colorId = val;
                    optionState.selected.colorName = picked?.color_name || '';
                    optionState.selected.colorCode = picked?.color_code || '';
                    optionState.selected.colorStock = picked ? Number(picked.stock_level || 0) : null;
                    const info = qs('#colorStockInfo', modal);
                    // If a size is chosen, compute stock for that specific size within this color
                    const sizeCode = optionState.selected.sizeCode || '';
                    let colorSizeStock = null;
                    if (sizeCode && optionState.sizesAll.length) {
                        const sizeRec = optionState.sizesAll.find(s => String(s.size_code) === String(sizeCode) && String(s.color_id || '') === String(val));
                        if (sizeRec) colorSizeStock = Number(sizeRec.stock_level || 0);
                    }
                    if (info) {
                        if (colorSizeStock !== null) info.textContent = colorSizeStock > 0 ? `In stock (this size): ${colorSizeStock}` : 'Out of stock (this size)';
                        else info.textContent = picked ? (Number(picked.stock_level) > 0 ? `In stock: ${picked.stock_level}` : 'Out of stock') : '';
                    }
                    const main = qs('#detailedMainImage', modal);
                    if (main && picked?.image_path) main.src = picked.image_path;
                    // Refresh size options for selected color
                    refreshSizeOptions(modal);
                    // When color changes, ensure size option labels are accurate
                    refreshSizeLabels(modal);
                    // And refresh color labels considering possibly chosen size
                    refreshColorLabelsForSize(modal);
                    validateSelections(modal);
                    updateQtyMax(modal);
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
                    // Update color stock info to reflect this size (if a color is selected)
                    const colorInfo = qs('#colorStockInfo', modal);
                    const selectedColorId = optionState.selected.colorId || '';
                    if (colorInfo && selectedColorId) {
                        const sizeRec = optionState.sizesAll.find(s => String(s.size_code) === String(code) && String(s.color_id || '') === String(selectedColorId));
                        if (sizeRec) colorInfo.textContent = Number(sizeRec.stock_level) > 0 ? `In stock (this size): ${sizeRec.stock_level}` : 'Out of stock (this size)';
                    }
                    // Also refresh color dropdown labels/disabled state for this size selection
                    refreshColorLabelsForSize(modal);
                    updateDisplayedPrice(modal);
                    validateSelections(modal);
                    updateQtyMax(modal);
                }
            });
            modal.dataset.optionHandlersBound = '1';
        }
    }

    function bindEscHandler(modal) {
        if (!modal || modal._escHandlerBound) return;
        const handler = (e) => {
            const key = e.key || e.keyCode;
            if (key === 'Escape' || key === 'Esc' || key === 27) {
                e.preventDefault();
                try { closeGlobalItemModal(); } catch (_) {}
            }
        };
        document.addEventListener('keydown', handler);
        modal._escHandler = handler;
        modal._escHandlerBound = true;
    }

    function closeGlobalItemModal() {
        console.log('[GlobalModal] closeGlobalItemModal called');
        const modal = document.getElementById('detailedItemModal');
        if (modal) {
            modal.classList.remove('show');
            modal.classList.add('hidden');
            modal.classList.remove('force-visible'); // Remove force-visible to allow hiding
            modal.setAttribute('aria-hidden', 'true');
            console.log('[GlobalModal] Modal classes after close:', modal.className);
            if (window.WFModals && typeof window.WFModals.unlockScroll === 'function') {
                window.WFModals.unlockScroll();
            }
            // Remove ESC listener if present
            if (modal._escHandler) {
                document.removeEventListener('keydown', modal._escHandler);
                delete modal._escHandler;
                delete modal._escHandlerBound;
            }
        }
        // Also clear the modal container to ensure complete removal
        if (modalContainer) {
            modalContainer.innerHTML = '';
        }
        // Clear current item
        currentModalItem = null;
        window.currentDetailedItem = null;
        optionState = {
            sku: '',
            item: {},
            basePrice: 0,
            selected: {
                gender: '',
                colorId: '',
                colorName: '',
                colorCode: '',
                sizeName: '',
                sizeCode: '',
                sizeAdj: 0,
                sizeStock: null,
                colorStock: null
            },
            genders: [],
            colors: [],
            sizes: []
        };
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

        // Ensure modal overlay styles are present
        const modalStyles = document.getElementById('global-modal-styles');
        if (!modalStyles) {
            const style = document.createElement('style');
            style.id = 'global-modal-styles';
            style.textContent = `
                #detailedItemModal {
                    position: fixed !important;
                    inset: 0 !important;
                    z-index: var(--z-detailed-item-modal, 100300) !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    background: rgba(0, 0, 0, 0.5) !important;
                    cursor: pointer !important;
                }
                #detailedItemModal.show {
                    display: flex !important;
                }
                #detailedItemModal.hidden {
                    display: none !important;
                }
                /* Fallback force-visible state without touching inline styles */
                #detailedItemModal.force-visible {
                    display: flex !important;
                    opacity: 1 !important;
                    visibility: visible !important;
                    z-index: var(--z-detailed-item-modal, 100300) !important;
                }
                .detailed-item-modal-container {
                    cursor: default !important;
                    max-height: 95vh !important;
                    overflow-y: auto !important;
                }
                /* Override any conflicting styles from room modal */
                body.modal-open #detailedItemModal {
                    display: flex !important;
                    opacity: 1 !important;
                    pointer-events: auto !important;
                    transform: none !important;
                    visibility: visible !important;
                    z-index: var(--z-detailed-item-modal, 100300) !important;
                }
            `;
            document.head.appendChild(style);
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
                const allItems = await apiGetLocal(`/api/inventory.php`);
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
            const modalHtml = await apiPost('/api/render_detailed_modal.php', {
                item: item,
                images: images
            });

            if (!modalHtml || typeof modalHtml !== 'string') {
                throw new Error('Modal render API returned invalid HTML');
            }
            console.log('🔧 Modal HTML received, length:', modalHtml.length);
            console.log('🔧 Modal HTML preview:', modalHtml.substring(0, 200) + '...');
            
            // Insert the modal into the container
            // The modal HTML is a full HTML document, so we need to extract just the content
            modalContainer.innerHTML = modalHtml;

            // Extract the modal content from the full HTML document
            // Use DOMParser to properly parse the full HTML document
            const parser = new DOMParser();
            const doc = parser.parseFromString(modalHtml, 'text/html');

            // Find the modal element within the parsed document
            const modalElement = doc.getElementById('detailedItemModal');

            if (modalElement) {
                // Clear the container and insert just the modal element
                modalContainer.innerHTML = '';
                modalContainer.appendChild(modalElement.cloneNode(true));
                console.log('🔧 Modal HTML extracted and inserted successfully');
            } else {
                // Try to find the modal by class name or other selectors
                const fallbackModal = doc.querySelector('.detailed-item-modal') ||
                                    doc.querySelector('[id*="detailedItemModal"]') ||
                                    doc.querySelector('body > div');

                if (fallbackModal) {
                    modalContainer.innerHTML = '';
                    // Ensure the element has the correct ID
                    fallbackModal.id = 'detailedItemModal';
                    modalContainer.appendChild(fallbackModal.cloneNode(true));
                    console.log('🔧 Fallback modal element found and inserted with correct ID');
                } else {
                    console.error('🔧 No modal element found in HTML content');
                    console.log('🔧 HTML content preview:', modalHtml.substring(0, 500));
                    throw new Error('Modal element not found in rendered HTML');
                }
            }
            
            // Check if modal element was created
            const insertedModal = document.getElementById('detailedItemModal');
            console.log('🔧 Modal element found after insertion:', !!insertedModal);

            // Ensure the modal has the exact ID the CSS rules expect
            if (insertedModal && insertedModal.id !== 'detailedItemModal') {
                console.log('🔧 Fixing modal ID from:', insertedModal.id);
                insertedModal.id = 'detailedItemModal';
            }

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
                    // bindContentInteractions(modal, item); // TODO: Implement this function
                    modal.dataset.interactionsBound = '1';
                } else {
                    // Re-bind close handlers to ensure they work
                    bindCloseHandlers(modal);
                }
                try { await initOptions(modal, sku, item); } catch (e) { console.error('[GlobalModal] options init failed', e); }

                // Bind click-to-enlarge on main image
                const mainImg = qs('#detailedMainImage', modal);
                if (mainImg && !mainImg.dataset.viewerBound) {
                    mainImg.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const src = mainImg.getAttribute('src');
                        const alt = mainImg.getAttribute('alt') || '';
                        if (src) openImageViewer(src, alt);
                    });
                    mainImg.dataset.viewerBound = '1';
                }

                // Bind ESC handler for this modal instance
                bindEscHandler(modal);
                setTimeout(() => {
                    const finalModal = document.getElementById('detailedItemModal');
                    if (finalModal) {
                        console.log('🔧 Final modal check - classes:', finalModal.className);
                        console.log('🔧 Final modal check - styles:', window.getComputedStyle(finalModal));
                        // Force visibility as final fallback using a CSS class (no inline styles)
                        finalModal.classList.add('force-visible');
                    }
                }, 100);
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
        // Expose public functions
        window.WhimsicalFrog = window.WhimsicalFrog || {};
        window.WhimsicalFrog.GlobalModal = {
            show: showGlobalItemModal,
            close: closeGlobalItemModal,
            getCurrentItem: getCurrentModalItem,
            quickAddToCart: quickAddToCart
        };
    }

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
    window.openQuantityModal = quickAddToCart;

    console.log('Global Item Modal system loaded');
}()); // End IIFE

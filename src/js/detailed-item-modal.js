/**
 * Global Item Details Modal System
 * Unified modal system for displaying detailed item information across shop and room pages
 */
import { ApiClient } from '../core/api-client.js';

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
        settings: { cascade_order: ['gender','size','color'], enabled_dimensions: ['gender','size','color'], grouping_rules: {} },
        aggregates: { by_gender: [], by_size: [], by_color: [] },
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
    function isDimEnabled(key) {
        try {
            const enabled = optionState?.settings?.enabled_dimensions;
            return Array.isArray(enabled) ? enabled.includes(key) : true;
        } catch(_) { return true; }
    }
    // --- Aggregates helpers ---
    async function fetchSettings(sku) {
        const data = await apiGetLocal(`/api/item_options.php?action=get_settings&item_sku=${encodeURIComponent(sku)}&wf_dev_admin=1`).catch(() => ({}));
        const settings = data?.settings;
        if (settings) optionState.settings = settings;
        return optionState.settings;
    }
    async function fetchAggregates(sku, filters = {}) {
        const params = new URLSearchParams({ action: 'get_aggregates', item_sku: sku });
        if (filters.gender) params.set('gender', filters.gender);
        if (filters.size_code) params.set('size_code', filters.size_code);
        if (filters.color_id) params.set('color_id', String(filters.color_id));
        const url = `/api/item_options.php?${params.toString()}&wf_dev_admin=1`;
        const data = await apiGetLocal(url).catch(() => ({}));
        const ag = data?.aggregates || {};
        optionState.aggregates = {
            by_gender: Array.isArray(ag.by_gender) ? ag.by_gender : [],
            by_size: Array.isArray(ag.by_size) ? ag.by_size : [],
            by_color: Array.isArray(ag.by_color) ? ag.by_color : [],
        };
        return optionState.aggregates;
    }

    function labelWithStock(label, stock) {
        const n = Number(stock || 0);
        return `${label} (${Math.max(0, n)} in stock)`;
    }
    

    const currency = (v) => `$${Number(v || 0).toFixed(2)}`;
    function sanitizeCode(value) {
        return String(value || '').trim().toUpperCase().replace(/[^A-Z0-9]+/g, '');
    }
    // ---- Stock math helpers derived from bottom-layer sizes (item_sizes) ----
    function hasColorsPresent() {
        return Array.isArray(optionState.colors) && optionState.colors.length > 0;
    }
    function genderMatchesRow(rowGender, selectedGender) {
        const sel = String(selectedGender || '').trim().toLowerCase();
        const row = rowGender == null ? null : String(rowGender).trim().toLowerCase();
        if (!sel) return true; // if no gender selected, accept all rows
        // Normalize synonyms
        const normSel = sel === 'men' ? 'male' : sel === 'women' ? 'female' : sel;
        const normRow = row === null ? null : (row === 'men' ? 'male' : row === 'women' ? 'female' : row);
        if (normSel === 'unisex' || normSel === 'uni') {
            // Only count unisex rows for Unisex selection
            return normRow === null;
        }
        // For explicit gender (male/female), do not include unisex rows
        return normRow !== null && normRow === normSel;
    }
    function sumStockForColor(colorId, opts = {}) {
        const { sizeCode = '', gender = optionState.selected.gender } = opts;
        const code = String(sizeCode || '').trim();
        let total = 0;
        (optionState.sizesAll || []).forEach((s) => {
            if (String(s.color_id || '') !== String(colorId)) return;
            if (code && String(s.size_code || '') !== code) return;
            if (!genderMatchesRow(s.gender, gender)) return;
            total += Number(s.stock_level || 0);
        });
        return total;
    }
    function sumStockForSize(sizeCode, opts = {}) {
        const { colorId = '', gender = optionState.selected.gender } = opts;
        const code = String(sizeCode || '').trim();
        let total = 0;
        (optionState.sizesAll || []).forEach((s) => {
            if (code && String(s.size_code || '') !== code) return;
            if (colorId && String(s.color_id || '') !== String(colorId)) return;
            // If item has colors and no specific colorId is provided, exclude general (colorless) rows
            if (hasColorsPresent() && !colorId && (s.color_id === null || typeof s.color_id === 'undefined')) return;
            if (!genderMatchesRow(s.gender, gender)) return;
            total += Number(s.stock_level || 0);
        });
        return total;
    }
    async function apiGetLocal(url) {
        // Delegate to ApiClient to enforce headers/credentials
        try {
            const data = await ApiClient.get(url);
            return data;
        } catch (e) {
            throw e;
        }
    }
    async function apiPost(url, data) {
        try {
            return await ApiClient.post(url, data);
        } catch (e) {
            throw e;
        }
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
            // Use totalStock only when it's a positive, finite number; otherwise fallback to legacy stockLevel
            const total = Number(optionState.item?.totalStock);
            if (Number.isFinite(total) && total > 0) {
                stockVal = total;
            } else {
                stockVal = Number(optionState.item?.stockLevel);
            }
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
        // Only fields marked as required-field are validated; we toggle that based on enabled_dimensions
        const required = qsa('.required-field', modal).filter((el) => !el.closest('.hidden'));
        const allSelected = required.every((sel) => sel.value && sel.value !== '');
        setBtnEnabled(modal, allSelected);
        return allSelected;
    }
    function applyDimensionSettings(modal) {
        const genderWrap = qs('#genderSelection', modal);
        const sizeWrap = qs('#sizeSelection', modal);
        const colorWrap = qs('#colorSelection', modal);
        const genderSel = qs('#itemGenderSelect', modal);
        const sizeSel = qs('#itemSizeSelect', modal);
        const colorSel = qs('#itemColorSelect', modal);

        const genderOn = isDimEnabled('gender');
        const sizeOn = isDimEnabled('size');
        const colorOn = isDimEnabled('color');

        // Show/hide sections
        if (genderWrap) genderWrap.classList.toggle('hidden', !genderOn);
        if (sizeWrap) sizeWrap.classList.toggle('hidden', !sizeOn);
        if (colorWrap) colorWrap.classList.toggle('hidden', !colorOn);

        // Required-field marking only for enabled dims
        if (genderSel) genderSel.classList.toggle('required-field', !!genderOn);
        if (sizeSel) sizeSel.classList.toggle('required-field', !!sizeOn);
        if (colorSel) colorSel.classList.toggle('required-field', !!colorOn);

        // Disable controls when disabled by settings
        setDisabled(genderSel, !genderOn);
        setDisabled(sizeSel, !sizeOn);
        setDisabled(colorSel, !colorOn);

        // Clear selections for disabled dims
        if (!genderOn) {
            optionState.selected.gender = '';
            if (genderSel) genderSel.value = '';
        }
        if (!sizeOn) {
            optionState.selected.sizeName = '';
            optionState.selected.sizeCode = '';
            if (sizeSel) sizeSel.value = '';
        }
        if (!colorOn) {
            optionState.selected.colorId = '';
            optionState.selected.colorName = '';
            optionState.selected.colorCode = '';
            if (colorSel) colorSel.value = '';
        }
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
            // Build label with per-option stock count when available; if OOS, mark clearly and disable
            if (Number.isFinite(stockNum)) {
                if (isOut) {
                    opt.textContent = `${label} (Out of stock)`;
                } else {
                    opt.textContent = `${label} (${Math.max(0, stockNum)} in stock)`;
                }
            } else {
                opt.textContent = label;
            }
            opt.disabled = !!isOut;
            opt.classList.toggle('option-out-of-stock', !!isOut);
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
        const options = Array.from(colorSelect.options);
        let visibleCount = 0;
        options.forEach((opt, _idx) => {
            if (!opt.value) return; // skip placeholder
            const colorId = opt.value;
            // Prefer aggregate by_color if available, which already applies filters for gender/size
            let stockForThis = null;
            const agg = (optionState.aggregates.by_color || []).find(c => String(c.id) === String(colorId));
            if (agg) stockForThis = Number(agg.stock || 0);
            // If no aggregate, try fallback by matching sizesAll for size/color
            if (stockForThis === null) {
                stockForThis = sumStockForColor(colorId, { sizeCode });
            }
            // Base label from dataset or existing text before parens
            const baseLabel = opt.dataset.color_name || opt.textContent.replace(/\s*\([^)]*\)\s*$/, '');
            if (sizeCode) {
                // When a size is chosen, color availability depends on having that size record
                if (stockForThis === null || stockForThis <= 0) {
                    // Hide colors that don't carry the size or have zero stock
                    opt.hidden = true;
                    opt.disabled = true;
                    opt.classList.add('option-out-of-stock');
                } else {
                    opt.textContent = `${baseLabel} (${Math.max(0, stockForThis)} in stock)`;
                    opt.hidden = false;
                    opt.disabled = false;
                    opt.classList.toggle('option-out-of-stock', false);
                    visibleCount++;
                }
            } else {
                // If no size chosen, prefer aggregate totals for color stock; fall back to dataset only if aggregate missing
                if (stockForThis !== null && Number.isFinite(stockForThis)) {
                    if (stockForThis <= 0) {
                        opt.hidden = true;
                        opt.disabled = true;
                        opt.classList.add('option-out-of-stock');
                    } else {
                        opt.textContent = `${baseLabel} (${Math.max(0, stockForThis)} in stock)`;
                        opt.hidden = false;
                        opt.disabled = false;
                        opt.classList.toggle('option-out-of-stock', false);
                        visibleCount++;
                    }
                } else {
                    // Unknown stock: show option without a count
                    opt.textContent = baseLabel;
                    opt.hidden = false;
                    opt.disabled = false;
                    opt.classList.remove('option-out-of-stock');
                    visibleCount++;
                }
            }
        });
        // If no visible color options, adjust placeholder and disable select
        const placeholder = options.find(o => !o.value);
        if (placeholder) {
            if (visibleCount === 0) {
                placeholder.textContent = sizeCode ? 'No colors available for this size' : 'No colors available';
                colorSelect.value = '';
                setDisabled(colorSelect, true);
            } else {
                placeholder.textContent = 'Select a color...';
                // Respect cascade enabling elsewhere; don't force-enable here
            }
        }
    }

    // After selecting a color, sizes are already filtered; ensure each size option label shows stock and disable if 0
    function refreshSizeLabels(modal) {
        const sizeSelect = qs('#itemSizeSelect', modal);
        if (!sizeSelect) return;
        Array.from(sizeSelect.options).forEach((opt) => {
            if (!opt.value) return;
            const sizeCode = opt.value;
            // Prefer aggregate by_size if available (cascades: gender and/or color may be selected)
            const agg = (optionState.aggregates.by_size || []).find(s => String(s.size_code) === String(sizeCode));
            // Fallback: compute from sizesAll, filtered by current gender and selected color (if any)
            const selectedColorId = optionState.selected.colorId || '';
            const rec = optionState.sizes.find(s => String(s.size_code) === String(sizeCode));
            const baseLabel = (rec?.size_name) || opt.textContent.replace(/\s*\([^)]*\)\s*$/, '');
            const stock = agg ? Number(agg.stock || 0) : sumStockForSize(sizeCode, { colorId: selectedColorId });
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
            if (!isDimEnabled('gender')) {
                // Gender disabled: hide and ensure downstream dims are not blocked by gender
                const container = qs('#genderSelection', modal);
                container?.classList.add('hidden');
                // Do not disable size/color based on gender when disabled
                return;
            }
            const data = await apiGetLocal(`/api/item_genders.php?action=get_all&item_sku=${encodeURIComponent(sku)}`);
            const genders = Array.isArray(data?.genders) ? data.genders : [];
            optionState.genders = genders;
            const container = qs('#genderSelection', modal);
            const select = qs('#itemGenderSelect', modal);
            if (genders.length > 0) {
                // Try to load aggregates to show totals at the gender level
                try { await fetchAggregates(sku, {}); } catch(_) {}
                const mapFn = (g) => {
                    const gName = g.gender || 'Unisex';
                    const ag = (optionState.aggregates.by_gender || []).find(x => String(x.gender).toLowerCase() === String(gName).toLowerCase());
                    const stock = ag ? ag.stock : undefined;
                    const label = stock != null ? labelWithStock(gName, stock) : gName;
                    return { value: gName, label };
                };
                populateSelect(select, genders, mapFn, 'Select a style...');
                container?.classList.remove('hidden');
                // Auto-select if only one gender to prevent empty-looking UI
                if (genders.length === 1 && select) {
                    const only = String(genders[0]?.gender || '').trim();
                    if (only) {
                        select.value = only;
                        optionState.selected.gender = only;
                        // Enable size selection next; keep color disabled until size selected
                        setDisabled(qs('#itemSizeSelect', modal), false);
                        const colorEl = qs('#itemColorSelect', modal);
                        if (colorEl) setDisabled(colorEl, true);
                        try { localStorage.setItem(`wf:lastGender:${sku}`, only); } catch(_) {}
                    }
                }
            } else {
                container?.classList.add('hidden');
            }
            // If genders exist and gender dimension is enabled, require gender before size/color
            if (genders.length > 0 && isDimEnabled('gender')) {
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
                        // Enable next step in cascade if those dims are enabled
                        if (isDimEnabled('size')) setDisabled(qs('#itemSizeSelect', modal), false);
                        const colorEl = qs('#itemColorSelect', modal);
                        if (colorEl && isDimEnabled('color')) setDisabled(colorEl, true);
                    }
                }
            } catch(_) {}
        } catch (e) { qs('#genderSelection', modal)?.classList.add('hidden'); }
    }
    // Load all sizes once; filter client-side for color intersection
    async function loadAllSizes(modal, sku) {
        const gender = String(optionState.selected.gender || '').trim();
        const baseUrl = `/api/item_sizes.php?action=get_sizes&item_sku=${encodeURIComponent(sku)}` + (gender ? `&gender=${encodeURIComponent(gender)}` : '');
        let data = await apiGetLocal(baseUrl).catch(() => ({}));
        let sizes = Array.isArray(data?.sizes) ? data.sizes : [];
        // Fallback to admin endpoint if empty (dev bypass)
        if (!sizes.length) {
            const adminUrl = `/api/item_sizes.php?action=get_all_sizes&item_sku=${encodeURIComponent(sku)}` + (gender ? `&gender=${encodeURIComponent(gender)}` : '') + `&wf_dev_admin=1`;
            data = await apiGetLocal(adminUrl).catch(() => ({}));
            sizes = Array.isArray(data?.sizes) ? data.sizes : [];
        }
        optionState.sizesAll = sizes;
    }
    async function refreshSizeOptions(modal) {
        const container = qs('#sizeSelection', modal);
        const select = qs('#itemSizeSelect', modal);
        const currentColorId = optionState.selected.colorId || '';
        const hasColors = hasColorsPresent();
        const selectedGender = String(optionState.selected.gender || '').trim();
        // Build size options from the bottom layer:
        // - If a color is selected, include only that color
        // - If no color is selected, aggregate across ALL colors
        const byCode = new Map();
        (optionState.sizesAll || []).forEach((s) => {
            // Gender filter: include unisex and exact match
            if (!genderMatchesRow(s.gender, selectedGender)) return;
            if (hasColors) {
                if (currentColorId) {
                    if (String(s.color_id || '') !== String(currentColorId)) return;
                }
                // No color selected: include only per-color rows to aggregate by size
                else {
                    if (s.color_id === null || typeof s.color_id === 'undefined') return;
                }
            }
            const key = String(s.size_code || '').trim();
            if (!key) return;
            const name = s.size_name || key;
            const prev = byCode.get(key) || { size_code: key, size_name: name, stock_level: 0 };
            prev.stock_level += Number(s.stock_level || 0);
            // prefer an exact gender name for display if present
            if (!prev._hasExact && selectedGender && s.gender && String(s.gender).trim() === selectedGender) {
                prev.size_name = s.size_name || name;
                prev._hasExact = true;
            }
            byCode.set(key, prev);
        });
        const aggregated = Array.from(byCode.values());
        // Fetch server aggregates with current filters to avoid client/server drift
        try {
            await fetchAggregates(optionState.sku, {
                gender: selectedGender || undefined,
                ...(currentColorId ? { color_id: currentColorId } : {})
            });
        } catch(_) {}
        // Map stock from server aggregates when available
        const serverBySize = optionState.aggregates.by_size || [];
        aggregated.forEach((it) => {
            const ag = serverBySize.find(s => String(s.size_code) === String(it.size_code));
            if (ag) it.stock_level = Number(ag.stock || 0);
        });
        // Sort by size_code natural-ish order (fallback to existing display order not available here)
        aggregated.sort((a, b) => String(a.size_code).localeCompare(String(b.size_code), undefined, { numeric: true, sensitivity: 'base' }));
        optionState.sizes = aggregated;
        const hasAnySizes = (optionState.sizesAll || []).length > 0;
        if (aggregated.length > 0) {
            // Populate with aggregated stock_level per size so labels/disabled reflect real totals
            populateSelect(select, aggregated, (s) => ({ value: s.size_code, label: s.size_name }), 'Select a size...');
            setDisabled(select, false);
            container?.classList.remove('hidden');
        } else if (hasAnySizes) {
            const ph = hasColors && currentColorId ? 'No sizes for this color' : (hasColors ? 'Select a color to see sizes…' : 'No sizes available');
            populateSelect(select, [], (s) => ({ value: s.size_code, label: s.size_name }), ph);
            setDisabled(select, !!(hasColors && !currentColorId));
            container?.classList.remove('hidden');
        } else {
            container?.classList.add('hidden');
        }
        // Reset size specifics on change of color scope
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
            await refreshSizeOptions(modal);
            // After sizes are populated, ensure labels reflect stock counts
            refreshSizeLabels(modal);
        } catch (e) { qs('#sizeSelection', modal)?.classList.add('hidden'); }
    }
    async function loadColors(modal, sku) {
        try {
            if (!isDimEnabled('color')) {
                // Color disabled: hide UI and ensure size is usable if enabled
                qs('#colorSelection', modal)?.classList.add('hidden');
                if (isDimEnabled('size')) setDisabled(qs('#itemSizeSelect', modal), false);
                return await loadSizes(modal, sku);
            }
            let data = await apiGetLocal(`/api/item_colors.php?action=get_colors&item_sku=${encodeURIComponent(sku)}`).catch(() => ({}));
            let colors = Array.isArray(data?.colors) ? data.colors : [];
            if (!colors.length) {
                // Fallback to admin endpoint with dev bypass to surface inactive rows during debugging
                data = await apiGetLocal(`/api/item_colors.php?action=get_all_colors&item_sku=${encodeURIComponent(sku)}&wf_dev_admin=1`).catch(() => ({}));
                colors = Array.isArray(data?.colors) ? data.colors : [];
            }
            optionState.colors = colors;
            const container = qs('#colorSelection', modal);
            const select = qs('#itemColorSelect', modal);
            if (colors.length > 0) {
                populateSelect(select, colors, (c) => ({ value: String(c.id), label: c.color_name }), 'Select a color...');
                container?.classList.remove('hidden');
                // If only one color, auto-select it to reveal sizes
                if (colors.length === 1 && select) {
                    select.value = String(colors[0].id);
                    optionState.selected.colorId = String(colors[0].id);
                    optionState.selected.colorName = colors[0].color_name || '';
                    optionState.selected.colorCode = colors[0].color_code || '';
                }
                // Always refresh color labels to include stock counts
                refreshColorLabelsForSize(modal);
            } else {
                container?.classList.add('hidden');
                // Ensure size selector is enabled when no colors exist
                setDisabled(qs('#itemSizeSelect', modal), false);
            }
            const info = qs('#colorStockInfo', modal);
            if (info) info.textContent = '';
            // Enforce cascade: if gender dimension is disabled, treat gender as satisfied
            const genderSatisfied = isDimEnabled('gender') ? !!(optionState.selected.gender) : true;
            // If size dimension is disabled, do not require size before color
            const sizeSatisfied = isDimEnabled('size') ? !!(optionState.selected.sizeCode) : true;
            const shouldEnableColor = genderSatisfied && (!colors.length || sizeSatisfied);
            setDisabled(select, !shouldEnableColor);
            await loadSizes(modal, sku);
            // After loading sizes, refresh labels again now that we know size/color stock matrix
            refreshColorLabelsForSize(modal);
            refreshSizeLabels(modal);
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
        // Make option sections visible immediately with placeholders so user can see the dropdowns exist
        try {
            const genderWrap = qs('#genderSelection', modal);
            const colorWrap = qs('#colorSelection', modal);
            const sizeWrap = qs('#sizeSelection', modal);
            genderWrap?.classList.remove('hidden');
            colorWrap?.classList.remove('hidden');
            sizeWrap?.classList.remove('hidden');
            const genderSel = qs('#itemGenderSelect', modal);
            const colorSel = qs('#itemColorSelect', modal);
            const sizeSel = qs('#itemSizeSelect', modal);
            if (genderSel && genderSel.options.length === 0) genderSel.innerHTML = '<option value="">Loading styles…</option>';
            if (colorSel && colorSel.options.length === 0) colorSel.innerHTML = '<option value="">Loading colors…</option>';
            if (sizeSel && sizeSel.options.length === 0) sizeSel.innerHTML = '<option value="">Loading sizes…</option>';
            setDisabled(colorSel, true);
            setDisabled(sizeSel, true);
        } catch(_) {}
        // Load settings first, so we can apply enabled_dimensions before populating controls
        try { await fetchSettings(sku); } catch(_) {}
        applyDimensionSettings(modal);
        try { await fetchAggregates(sku, {}); } catch(_) {}
        // Gender first (unless disabled)
        await loadGenders(modal, sku);
        // Then sizes (so size comes before color in cascade)
        await loadSizes(modal, sku);
        // Finally colors
        await loadColors(modal, sku);
        // If a gender was auto-selected/restored, refresh aggregates with that gender for accurate labels
        if (optionState.selected.gender) {
            try { await fetchAggregates(sku, { gender: optionState.selected.gender }); } catch(_) {}
            refreshSizeLabels(modal);
            refreshColorLabelsForSize(modal);
        }
        updateDisplayedPrice(modal);
        updateQtyMax(modal);
        validateSelections(modal);
        // Final refresh to ensure labels show accurate quantities
        try { refreshColorLabelsForSize(modal); refreshSizeLabels(modal); } catch(_) {}
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
                                sku: cartSku,
                                quantity: qty,
                                price,
                                name,
                                image,
                            };
                            await window.WF_Cart.addItem(payload);
                            try { window.WF_Cart.updateCartDisplay && window.WF_Cart.updateCartDisplay(); } catch(_) {}
                        } else if (window.cart && typeof window.cart.add === 'function') {
                            // Legacy ES module cart singleton bridge
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
                                sku: cartSku,
                                quantity: qty,
                                price,
                                name,
                                image,
                            };
                            try { window.cart.add(payload, qty); } catch(err) { console.warn('[GlobalModal] window.cart.add failed', err); }
                        } else if (window.cart && typeof window.cart.addItem === 'function') {
                            try { window.cart.addItem({ sku, quantity: qty }); } catch(err) { console.warn('[GlobalModal] window.cart.addItem failed', err); }
                        } else if (typeof window.addToCart === 'function') {
                            window.addToCart(sku, qty);
                        } else {
                            // As a last resort, surface a user-visible message so it doesn't look like a no-op
                            try {
                                const msg = 'Cart system not initialized. Please refresh and try again.';
                                if (window.wfNotifications?.show) window.wfNotifications.show(msg, 'error', { duration: 3000 });
                                else if (typeof window.showNotification === 'function') window.showNotification(msg, 'error');
                                else alert(msg);
                            } catch(_) {}
                            console.log('[GlobalModal] Add to cart fallback log:', { sku, quantity: qty, item });
                        }
                        // Keep add-to-cart silent; do not auto-open cart here.
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
                        // Enable size next; keep color disabled until size chosen (if colors exist)
                        setDisabled(qs('#itemSizeSelect', modal), false);
                        const colorEl = qs('#itemColorSelect', modal);
                        if (colorEl) setDisabled(colorEl, true);
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
                    // Re-fetch sizes with gender filter applied and get aggregates for cascades
                    try { await loadAllSizes(modal, optionState.sku); } catch(_) {}
                    try { await fetchAggregates(optionState.sku, { gender: optionState.selected.gender }); } catch(_) {}
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
                        const selGender = String(optionState.selected.gender || '').trim().toLowerCase();
                        const recExact = optionState.sizesAll.find(s => String(s.size_code) === String(sizeCode) && String(s.color_id || '') === String(val) && s.gender && String(s.gender).trim().toLowerCase() === selGender);
                        const recUnisex = optionState.sizesAll.find(s => String(s.size_code) === String(sizeCode) && String(s.color_id || '') === String(val) && (s.gender == null));
                        const sizeRec = recExact || recUnisex || null;
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
                    try { await fetchAggregates(optionState.sku, { gender: optionState.selected.gender || undefined, size_code: optionState.selected.sizeCode || undefined, color_id: val || undefined }); } catch(_) {}
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
                    // Now that a size is chosen, enable color selection if colors exist
                    const colorEl = qs('#itemColorSelect', modal);
                    if (colorEl && (optionState.colors || []).length > 0) setDisabled(colorEl, false);
                    const info = qs('#sizeStockInfo', modal);
                    if (info) info.textContent = chosen ? (Number(chosen.stock_level) > 0 ? `In stock: ${chosen.stock_level}` : 'Out of stock') : '';
                    // Update color stock info to reflect this size (if a color is selected)
                    const colorInfo = qs('#colorStockInfo', modal);
                    const selectedColorId = optionState.selected.colorId || '';
                    if (colorInfo && selectedColorId) {
                        const selGender = String(optionState.selected.gender || '').trim().toLowerCase();
                        const recExact = optionState.sizesAll.find(s => String(s.size_code) === String(code) && String(s.color_id || '') === String(selectedColorId) && s.gender && String(s.gender).trim().toLowerCase() === selGender);
                        const recUnisex = optionState.sizesAll.find(s => String(s.size_code) === String(code) && String(s.color_id || '') === String(selectedColorId) && (s.gender == null));
                        const sizeRec = recExact || recUnisex || null;
                        colorInfo.textContent = sizeRec ? (Number(sizeRec.stock_level) > 0 ? `In stock (this size): ${sizeRec.stock_level}` : 'Out of stock (this size)') : 'Not available for this size';
                    }
                    // Also refresh color dropdown labels/disabled state for this size selection
                    try { await fetchAggregates(optionState.sku, { gender: optionState.selected.gender || undefined, size_code: code || undefined, color_id: optionState.selected.colorId || undefined }); } catch(_) {}
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
            `;
            document.head.appendChild(style);
        }
    }

    // Public API
    window.showGlobalItemModal = async function showGlobalItemModal(sku, itemData) {
        try { window.hideGlobalPopupImmediate && window.hideGlobalPopupImmediate(); } catch(_) {}
        // Create modal HTML from server and inject
        currentModalItem = itemData || { sku };
        // Prepare payload expected by API: { item, images }
        const payloadItem = itemData && typeof itemData === 'object' ? itemData : { sku };
        const payloadImages = Array.isArray(itemData?.images)
            ? itemData.images.map((img) => (typeof img === 'string' ? { image_path: img } : img))
            : (itemData?.image ? [{ image_path: itemData.image }] : []);
        try {
            const html = await apiPost('/api/render_detailed_modal.php', { item: payloadItem, images: payloadImages });
            if (!modalContainer) initGlobalModal();
            modalContainer.innerHTML = html;
            const modal = document.getElementById('detailedItemModal');
            if (!modal) return;

            // Init item options with base SKU (strip only variant suffix tokens -G*, -S*, -C*)
            const rawSku = String(sku || payloadItem?.sku || '');
            const baseSku = rawSku.replace(/(-(G|S|C)[A-Z0-9]+)+$/i, '');

            // Show modal immediately to avoid perceived delay
            modal.classList.remove('hidden');
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            // Bind handlers and ESC early so user can close during load
            bindCloseHandlers(modal);
            bindEscHandler(modal);

            // Initialize options asynchronously (do not block initial render)
            (async () => {
                await initOptions(modal, baseSku, itemData || {});
                // If no colors, show all sizes and enable size selection
                if (optionState?.colors?.length === 0) {
                    optionState.sizes = optionState.sizesAll;
                    const sizeSelect = qs('#itemSizeSelect', modal);
                    if (sizeSelect) sizeSelect.disabled = false;
                }
                // Debug: log SKU and counts for troubleshooting missing options
                try {
                    console.log('[GlobalModal] Base SKU for options:', baseSku, {
                        gendersCount: (optionState?.genders || []).length,
                        colorsCount: (optionState?.colors || []).length,
                        sizesAllCount: (optionState?.sizesAll || []).length
                    });
                } catch(_) {}
            })();
        } catch (err) {
            console.error('[GlobalModal] Failed to open detailed modal', err);
        }
    };


})();

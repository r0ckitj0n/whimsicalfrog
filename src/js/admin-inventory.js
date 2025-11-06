import { buildAdminUrl } from '../core/admin-url-builder.js';
import { ApiClient } from '../core/api-client.js';
import '../styles/admin/admin-legacy-modals.css';
class AdminInventoryModule {
    constructor() {
        this.currentItemSku = null;
        this.items = [];
        this.categories = [];
        this.costBreakdown = { materials: {}, labor: {}, energy: {}, equipment: {}, totals: {} };
        this.currentEditCostItem = null;
        this.tooltipTimeout = null;
        this.currentTooltip = null;
        this.editCarouselPosition = 0;
        this.viewCarouselPosition = 0;
        // AI comparison selection state (migrated from legacy globals)
        this.selectedComparisonChanges = {};
        // Track SKU for delete confirmation modal
        this.itemToDeleteSku = null;
        // Prevent multiple simultaneous modal openings
        this.isOpeningModal = false;
        this.modalMode = null;
        // Store current item data for fallback purposes
        this.currentItemData = null;
        this.itemsBySku = new Map();

        this.loadData();

        // Wire up item details modal openers for View/Edit actions
        this.registerItemDetailsHandlers();
        // Auto-open if URL contains ?view= or ?edit=
        this.autoOpenItemFromQuery();
        this.bindEvents();

        // Server-rendered admin editor handles its own visibility
        console.log('[AdminInventory] Server-rendered admin editor handles its own visibility');

        // Legacy shim: allow older inline code to call into the module if present
        try {
            window.buildComparisonInterface = (aiData, currentMarketingData) => this.buildComparisonInterface(aiData, currentMarketingData);
        } catch (_) {}

        // Initialize SKU from DOM early
        this.initSkuFromDom();
    }

    async distributeGeneralStockEvenly() {
        if (!this.currentItemSku) {
            const skuField = document.getElementById('skuEdit') || document.getElementById('sku') || document.getElementById('skuDisplay');
            const hiddenSku = document.querySelector('input[name="itemSku"]');
            this.currentItemSku = (skuField ? (skuField.value || skuField.textContent || '').trim() : '') || (hiddenSku ? (hiddenSku.value || '').trim() : '');
        }
        if (!this.currentItemSku) { this.showError('No item selected'); return; }
        const btn = document.querySelector('[data-action="distribute-general-stock-evenly"]');
        try { btn && btn.setAttribute('aria-busy','true'); btn && (btn.disabled = true); } catch(_) {}
        try {
            const data = await ApiClient.post('/api/item_sizes.php?action=distribute_general_stock_evenly', {
                item_sku: this.currentItemSku
            }).catch(()=>({}));
            if (!data || !data.success) throw new Error(data?.message || 'Failed');
            const msg = `Distributed ${data.moved_total} units across ${data.colors} colors; updated ${data.updated} rows${data.created?`, created ${data.created}`:''}.`;
            this.showSuccess(msg);
            if (typeof data.new_total_stock !== 'undefined') {
                const stockField = document.getElementById('stockLevel');
                if (stockField) stockField.value = data.new_total_stock;
            }
            // Refresh
            this.loadNestedInventoryEditor();
            try { this.loadItemColors(); } catch(_) {}
            try { this.loadItemSizes(); } catch(_) {}
        } catch (e) {
            this.showError('Error distributing stock: ' + (e.message || ''));
        } finally {
            try { btn && btn.removeAttribute('aria-busy'); btn && (btn.disabled = false); } catch(_) {}
        }
    }

    async ensureColorSizes() {
        if (!this.currentItemSku) {
            const skuField = document.getElementById('skuEdit') || document.getElementById('sku') || document.getElementById('skuDisplay');
            const hiddenSku = document.querySelector('input[name="itemSku"]');
            this.currentItemSku = (skuField ? (skuField.value || skuField.textContent || '').trim() : '') || (hiddenSku ? (hiddenSku.value || '').trim() : '');
        }
        if (!this.currentItemSku) {
            this.showError('No item selected');
            return;
        }
        try {
            const btn = document.querySelector('[data-action="ensure-color-sizes"]');
            try { btn && btn.setAttribute('aria-busy','true'); } catch(_) {}
            const data = await ApiClient.post('/api/item_sizes.php?action=ensure_color_sizes', {
                item_sku: this.currentItemSku
            }).catch(()=>({}));
            if (!data || !data.success) throw new Error(data?.message || 'Failed');
            const msg = `Containers configured: created ${data.created} sizes across ${data.colors} colors${data.skipped ? `, skipped ${data.skipped}` : ''}.`;
            this.showSuccess(msg);
            // Update stock field if provided
            if (typeof data.new_total_stock !== 'undefined') {
                const stockField = document.getElementById('stockLevel');
                if (stockField) stockField.value = data.new_total_stock;
            }
            // Refresh UI to reflect new per-color sizes
            this.loadNestedInventoryEditor();
            try { this.loadItemColors(); } catch(_) {}
            try { this.loadItemSizes(); } catch(_) {}
        } catch (e) {
            this.showError('Error configuring containers: ' + (e.message || ''));
        } finally {
            const btn = document.querySelector('[data-action="ensure-color-sizes"]');
            try { btn && btn.removeAttribute('aria-busy'); } catch(_) {}
        }
    }

    // ------- Nested Inventory Editor (Gender -> Color -> Size) -------
    async loadNestedInventoryEditor() {
        let host = document.getElementById('nestedInventoryEditor');
        if (!host) return;
        const sku = host.dataset.sku || this.currentItemSku;
        const write = (html) => {
            const live = document.getElementById('nestedInventoryEditor');
            if (live) live.innerHTML = html;
        };
        if (!sku) { write('<div class="text-sm text-gray-500">No SKU</div>'); return; }
        write('<div class="text-sm text-gray-500">Loading nested inventory…</div>');
        console.debug('[NestedEditor] start', { sku });

        try {
            console.debug('[NestedEditor] fetching …');
            const [colorsRes, sizesAdminRes] = await Promise.all([
                ApiClient.get('/api/item_colors.php', { action: 'get_all_colors', item_sku: sku, wf_dev_admin: 1 }).catch(()=>({})),
                ApiClient.get('/api/item_sizes.php', { action: 'get_all_sizes', item_sku: sku, wf_dev_admin: 1 }).catch(()=>({}))
            ]);
            console.debug('[NestedEditor] fetched');
            let colors = Array.isArray(colorsRes?.colors) ? colorsRes.colors : [];
            const sizesAll = Array.isArray(sizesAdminRes?.sizes) ? sizesAdminRes.sizes : [];

            // Read control filters
            const genderFilterEl = document.getElementById('nestedGenderFilter');
            const colorFilterEl = document.getElementById('nestedColorFilter');
            const searchEl = document.getElementById('nestedSearch');
            const sortEl = document.getElementById('nestedSort');
            const filterGender = (genderFilterEl?.value || '').trim();
            const filterColorId = (colorFilterEl?.value || '').trim();
            const searchTerm = (searchEl?.value || '').trim().toLowerCase();
            const sortBy = (sortEl?.value || 'code');
            // Restore persisted state if empty
            try {
                const key = `wf:nested:${sku}`;
                const saved = JSON.parse(localStorage.getItem(key) || '{}');
                if (genderFilterEl && !genderFilterEl.value && saved.gender) genderFilterEl.value = saved.gender;
                if (colorFilterEl && !colorFilterEl.value && saved.colorId) colorFilterEl.value = saved.colorId;
                if (searchEl && !searchEl.value && saved.search) searchEl.value = saved.search;
                if (sortEl && !sortEl.value && saved.sort) sortEl.value = saved.sort;
                const showInactiveEl = document.getElementById('nestedShowInactive');
                if (showInactiveEl && saved.showInactive != null) showInactiveEl.checked = !!saved.showInactive;
            } catch(_) {}
            const showInactive = !!document.getElementById('nestedShowInactive')?.checked;

            // Apply showInactive to colors list (include inactive if checked)
            if (!showInactive) {
                colors = colors.filter(c => Number(c.is_active) === 1);
            }

            // Populate color filter options once per load
            if (colorFilterEl && !colorFilterEl.dataset.populated) {
                const prev = colorFilterEl.value;
                colorFilterEl.innerHTML = '<option value="">All</option>' + colors.map(c => `<option value="${String(c.id)}">${c.color_name}</option>`).join('');
                colorFilterEl.value = prev || '';
                colorFilterEl.dataset.populated = '1';
            }
            // Build gender set from sizes
            const genderSet = new Set();
            sizesAll.forEach(s => {
                const g = (s.gender == null || s.gender === '') ? 'Unisex' : String(s.gender);
                genderSet.add(g);
            });
            if (genderSet.size === 0) genderSet.add('Unisex');
            let genders = Array.from(genderSet);
            if (filterGender) genders = genders.filter(g => g === filterGender);

            // Helper groupings
            const byColorId = (list, colorId) => list.filter(s => String(s.color_id || '') === String(colorId || ''));
            const byGender = (list, g) => list.filter(s => {
                const norm = (g === 'Unisex') ? null : g;
                return (norm === null) ? (s.gender == null) : (String(s.gender||'').trim() === String(norm));
            });
            const bySearch = (list) => {
                if (!searchTerm) return list;
                return list.filter(s => {
                    const name = String(s.size_name || '').toLowerCase();
                    const code = String(s.size_code || '').toLowerCase();
                    return name.includes(searchTerm) || code.includes(searchTerm);
                });
            };
            const sortSizes = (list) => {
                const copy = list.slice();
                if (sortBy === 'name') return copy.sort((a,b)=>String(a.size_name||'').localeCompare(String(b.size_name||'')));
                if (sortBy === 'stock') return copy.sort((a,b)=>Number(a.stock_level||0)-Number(b.stock_level||0));
                // default code sort, natural-ish
                return copy.sort((a,b)=>String(a.size_code||'').localeCompare(String(b.size_code||''), undefined, { numeric:true, sensitivity:'base'}));
            };
            const byActive = (list) => showInactive ? list : list.filter(s => Number(s.is_active || 0) === 1);

            // Compose HTML using details/summary for accessibility and simplicity
            let html = '';
            genders.forEach(g => {
                // Compute gender total based on what will be displayed
                const sizesForGender = byActive(byGender(sizesAll, g));
                let genderTotal = 0;
                if (colors.length > 0) {
                    const allowedColorIds = filterColorId ? new Set([String(filterColorId)]) : null;
                    genderTotal = sizesForGender
                        .filter(s => s.color_id != null && (!allowedColorIds || allowedColorIds.has(String(s.color_id))))
                        .reduce((sum, s) => sum + (Number(s.stock_level || 0)), 0);
                } else {
                    genderTotal = sizesForGender
                        .filter(s => s.color_id == null)
                        .reduce((sum, s) => sum + (Number(s.stock_level || 0)), 0);
                }

                html += `<details class="border rounded-md">
  <summary class="cursor-pointer px-3 py-2 bg-gray-50 text-sm font-medium text-gray-800">
    <span class="wf-folder-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M2 5.5A1.5 1.5 0 013.5 4H7l2 2h7.5A1.5 1.5 0 0118 7.5v7A1.5 1.5 0 0116.5 16h-13A1.5 1.5 0 012 14.5v-9z"/></svg></span>
    <span>${g}</span>
    <span class="ml-2 wf-container-badge" title="This is a group container (not directly linked to item stock)">Group container</span>
    <span class="ml-2 wf-container-badge wf-gender-total" title="Sum of visible sizes within this group">Total: ${genderTotal}</span>
  </summary>
  <div class="p-3 space-y-3">
`;
                if (colors.length > 0) {
                    // Render a distinct General Sizes block (sizes with no color) so purpose is obvious
                    const generalSizes = byActive(byGender(sizesAll.filter(s => s.color_id == null), g));
                    const generalFiltered = sortSizes(bySearch(generalSizes));
                    if (generalFiltered.length > 0) {
                        const gTotal = generalFiltered.reduce((sum, s) => sum + (Number(s.stock_level || 0)), 0);
                        html += `<div class="border rounded-md border-amber-200 bg-amber-50">
  <div class="px-3 py-2 flex items-center justify-between">
    <div class="text-sm font-semibold text-amber-800 flex items-center gap-2">
      <span>General Sizes (not tied to a color)</span>
      <span class="wf-container-badge">General</span>
    </div>
    <div class="text-xs text-amber-700"><span class="wf-color-total">Total: ${gTotal}</span></div>
  </div>
  <div class="p-3 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead><tr class="text-gray-600"><th class="text-left pr-4 py-1">Size</th><th class="text-left pr-4 py-1">Code</th><th class="text-left py-1">Stock</th></tr></thead>
      <tbody>
        ${generalFiltered.map(s => `<tr class="border-t"><td class="pr-4 py-1">${s.size_name || s.size_code}</td><td class="pr-4 py-1 text-gray-500">${s.size_code || ''}</td><td class="py-1"><input type="number" class="wf-nested-stock-input border rounded px-2 py-1 w-24" data-size-id="${s.id}" value="${Number(s.stock_level || 0)}" min="0" /></td></tr>`).join('')}
      </tbody>
    </table>
  </div>
</div>`;
                    }
                    // Apply color filter if set
                    const colorList = filterColorId ? colors.filter(c => String(c.id) === String(filterColorId)) : colors;
                    colorList.forEach(c => {
                        const sizes = byActive(byGender(byColorId(sizesAll, c.id), g));
                        const sizesFiltered = sortSizes(bySearch(sizes));
                        if (sizesFiltered.length === 0) return;
                        const colorTotal = sizesFiltered.reduce((sum, s) => sum + (Number(s.stock_level || 0)), 0);
                        // Build size chips (show up to 4, then +N)
                        const sizeCodes = sizesFiltered.map(s => s.size_code || s.size_name || '').filter(Boolean);
                        const maxChips = 4;
                        const chips = sizeCodes.slice(0, maxChips).map(code => `<span class=\"wf-size-chip\">${code}</span>`).join('');
                        const moreCount = Math.max(0, sizeCodes.length - maxChips);
                        const moreChip = moreCount > 0 ? `<span class=\"wf-size-chip more\">+${moreCount}</span>` : '';
                        html += `<div class=\"border rounded-md\" data-color-id=\"${String(c.id)}\">
  <div class=\"px-3 py-2 bg-white flex items-center justify-between\">
    <div class=\"text-sm font-semibold text-gray-700 flex items-center gap-2\">
      <span class=\"wf-box-icon\" aria-hidden=\"true\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 20 20\" fill=\"currentColor\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M4 3h12a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zm1 3v9h10V6H5zm2-2v2h6V4H7z\"/></svg></span>
      <span>${c.color_name}</span>
      <span class=\"wf-container-badge\" title=\"This is an item container (totals roll up from sizes)\">Item container</span>
      <span class=\"hidden sm:inline-flex items-center\">${chips}${moreChip}</span>
    </div>
    <div class=\"text-xs text-gray-600 flex items-center gap-3\">
      <span class=\"wf-color-total\" title=\"Items total for this color\">Total: ${colorTotal}</span>
      <span class=\"text-gray-400\">${c.color_code ? c.color_code : ''}</span>
    </div>
  </div>
  <div class="p-3 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead><tr class="text-gray-600"><th class="text-left pr-4 py-1">Size</th><th class="text-left pr-4 py-1">Code</th><th class="text-left py-1">Stock</th></tr></thead>
      <tbody>
`;
                        sizesFiltered.forEach(s => {
                            html += `<tr class="border-t">
  <td class="pr-4 py-1">${s.size_name || s.size_code}</td>
  <td class="pr-4 py-1 text-gray-500">${s.size_code || ''}</td>
  <td class="py-1"><input type="number" class="wf-nested-stock-input border rounded px-2 py-1 w-24" data-size-id="${s.id}" value="${Number(s.stock_level || 0)}" min="0" /></td>
</tr>`;
                        });
                        html += `      </tbody>
    </table>
  </div>
</div>`;
                    });
                } else {
                    // No colors: show single sizes table filtered by gender where color_id is null
                    const sizes = byActive(byGender(sizesAll.filter(s => s.color_id == null), g));
                    const sizesFiltered = sortSizes(bySearch(sizes));
                    if (sizesFiltered.length > 0) {
                        html += `<div class="border rounded-md">
  <div class="px-3 py-2 bg-white text-sm font-semibold text-gray-700">Sizes</div>
  <div class="p-3 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead><tr class="text-gray-600"><th class="text-left pr-4 py-1">Size</th><th class="text-left pr-4 py-1">Code</th><th class="text-left py-1">Stock</th></tr></thead>
      <tbody>
`;
                        sizesFiltered.forEach(s => {
                            html += `<tr class="border-t">
  <td class="pr-4 py-1">${s.size_name || s.size_code}</td>
  <td class="pr-4 py-1 text-gray-500">${s.size_code || ''}</td>
  <td class="py-1"><input type="number" class="wf-nested-stock-input border rounded px-2 py-1 w-24" data-size-id="${s.id}" value="${Number(s.stock_level || 0)}" min="0" /></td>
</tr>`;
                        });
                        html += `      </tbody>
    </table>
  </div>
</div>`;
                    } else {
                        html += `<div class="text-sm text-gray-500">No sizes for ${g}</div>`;
                    }
                }
                html += `  </div>
</details>`;
            });

            // Helper: recompute totals from currently visible inputs and update labels
            const recomputeFromDom = () => {
                try {
                    // Per-color totals
                    document.querySelectorAll('#nestedInventoryEditor [data-color-id]').forEach(block => {
                        const inputs = block.querySelectorAll('input.wf-nested-stock-input');
                        let sum = 0;
                        inputs.forEach(inp => {
                            const v = Math.floor(Number(inp.value || 0));
                            if (Number.isFinite(v) && v > -Infinity) sum += Math.max(0, v);
                        });
                        const label = block.querySelector('.wf-color-total');
                        if (label) label.textContent = `Total: ${sum}`;
                    });
                    // Per-gender totals (top-level details)
                    document.querySelectorAll('#nestedInventoryEditor > details').forEach(group => {
                        const inputs = group.querySelectorAll('input.wf-nested-stock-input');
                        let sum = 0;
                        inputs.forEach(inp => {
                            const v = Math.floor(Number(inp.value || 0));
                            if (Number.isFinite(v) && v > -Infinity) sum += Math.max(0, v);
                        });
                        const label = group.querySelector('.wf-gender-total');
                        if (label) label.textContent = `Total: ${sum}`;
                    });
                } catch (_) {}
            };

            write(html || '<div class="text-sm text-gray-500">No option inventory defined yet.</div>');
            // If the container was swapped during render, write again
            host = document.getElementById('nestedInventoryEditor');
            if (host && /Loading\s+nested\s+inventory/i.test((host.textContent||'').trim())) {
                write(html || '<div class="text-sm text-gray-500">No option inventory defined yet.</div>');
            }
            // Ensure labels match inputs immediately
            recomputeFromDom();
            console.debug('[NestedEditor] rendered');

            // Bind events for stock inputs (delegated)
            if (!host.__nestedBound) {
                host.addEventListener('keydown', (ev) => {
                    const t = ev.target;
                    if (!(t instanceof HTMLInputElement)) return;
                    if (!t.matches('.wf-nested-stock-input')) return;
                    if (ev.key === 'Enter') { ev.preventDefault(); t.blur(); }
                });
                host.addEventListener('blur', async (ev) => {
                    const t = ev.target;
                    if (!(t instanceof HTMLInputElement)) return;
                    if (!t.matches('.wf-nested-stock-input')) return;
                    const sizeId = Number(t.getAttribute('data-size-id') || 0);
                    const newVal = Math.max(0, Math.floor(Number(t.value || 0)));
                    if (!Number.isFinite(newVal) || sizeId <= 0) return;
                    try {
                        const data = await ApiClient.post('/api/item_sizes.php?action=update_stock', {
                            size_id: sizeId,
                            stock_level: newVal
                        }).catch(() => ({}));
                        if (!data || !data.success) throw new Error(data?.message || 'Save failed');
                        // Update item total stock field if present
                        const total = data?.new_total_stock;
                        const totalInput = document.getElementById('stockLevel');
                        if (typeof total !== 'undefined' && totalInput) { totalInput.value = String(total); }
                        this.showSuccess('Stock saved');
                        // Refresh nested editor to reflect synced totals/labels
                        this.loadNestedInventoryEditor();
                        // Optionally refresh legacy lists too
                        try { this.loadItemColors(); } catch(_) {}
                        try { this.loadItemSizes(); } catch(_) {}
                    } catch (e) {
                        this.showError(e.message || 'Failed to save');
                    }
                }, true);
                // Controls events
                const skuLocal = sku; // capture for closures
                const persist = () => {
                    try {
                        const key = `wf:nested:${skuLocal}`;
                        const payload = {
                            gender: document.getElementById('nestedGenderFilter')?.value || '',
                            colorId: document.getElementById('nestedColorFilter')?.value || '',
                            search: document.getElementById('nestedSearch')?.value || '',
                            sort: document.getElementById('nestedSort')?.value || 'code',
                            showInactive: !!document.getElementById('nestedShowInactive')?.checked,
                        };
                        localStorage.setItem(key, JSON.stringify(payload));
                    } catch(_) {}
                };
                const rerender = () => { persist(); this.loadNestedInventoryEditor(); };
                const genderFilterEl2 = document.getElementById('nestedGenderFilter');
                const colorFilterEl2 = document.getElementById('nestedColorFilter');
                const searchEl2 = document.getElementById('nestedSearch');
                const sortEl2 = document.getElementById('nestedSort');
                genderFilterEl2?.addEventListener('change', rerender);
                colorFilterEl2?.addEventListener('change', rerender);
                searchEl2?.addEventListener('input', () => { clearTimeout(this._nestedSearchT); this._nestedSearchT = setTimeout(rerender, 200); });
                sortEl2?.addEventListener('change', rerender);
                document.getElementById('nestedShowInactive')?.addEventListener('change', rerender);
                // Expand/Collapse controls
                document.getElementById('btnExpandAllNested')?.addEventListener('click', () => {
                    document.querySelectorAll('#nestedInventoryEditor details').forEach(d => { try { d.open = true; } catch(_) {} });
                });
                document.getElementById('btnCollapseAllNested')?.addEventListener('click', () => {
                    document.querySelectorAll('#nestedInventoryEditor details').forEach(d => { try { d.open = false; } catch(_) {} });
                });
                // Color picker handler (delegated)
                document.getElementById('nestedInventoryEditor')?.addEventListener('change', async (e) => {
                    const el = e.target;
                    if (!(el instanceof HTMLInputElement)) return;
                    if (el.type !== 'color' || el.getAttribute('data-action') !== 'update-color-code') return;
                    const colorId = Number(el.getAttribute('data-color-id') || 0);
                    if (!colorId) return;
                    const colorCode = el.value;
                    try {
                        const data = await ApiClient.post('/api/item_colors.php?action=update_color_code', {
                            color_id: colorId,
                            color_code: colorCode
                        }).catch(()=>({}));
                        if (!data || !data.success) throw new Error(data?.message || 'Failed to update color');
                        // Update adjacent hex label
                        const label = el.parentElement?.querySelector('.text-gray-400');
                        if (label) label.textContent = colorCode;
                        this.showSuccess('Color updated');
                        // Optionally refresh color list badges without full reload
                        try { this.loadItemColors(); } catch(_) {}
                    } catch (err) {
                        this.showError(err?.message || 'Failed to update color');
                    }
                }, { once: true });
                // Bulk actions
                const getVisibleInputs = () => Array.from(document.querySelectorAll('#nestedInventoryEditor .wf-nested-stock-input'));
                const applyBulk = async (mode) => {
                    const valEl = document.getElementById('nestedBulkValue');
                    const n = Math.max(0, Math.floor(Number(valEl?.value || 0)));
                    if (!Number.isFinite(n)) { this.showError('Enter a valid bulk value'); return; }
                    const inputs = getVisibleInputs();
                    if (!inputs.length) { this.showInfo('No rows to update'); return; }
                    // Sequential saves to avoid overload
                    for (const inp of inputs) {
                        const sizeId = Number(inp.getAttribute('data-size-id') || 0);
                        if (sizeId <= 0) continue;
                        let newVal;
                        const current = Math.max(0, Math.floor(Number(inp.value || 0)));
                        if (mode === 'set') newVal = n;
                        if (mode === 'add') newVal = current + n;
                        if (mode === 'sub') newVal = Math.max(0, current - n);
                        try {
                            const data = await ApiClient.post('/api/item_sizes.php?action=update_stock', {
                                size_id: sizeId,
                                stock_level: newVal
                            }).catch(()=>({}));
                            if (!data || !data.success) throw new Error(data?.message || 'Save failed');
                        } catch (e) {
                            this.showError(e.message || 'Failed to save some rows');
                            return; // stop on first error
                        }
                    }
                    this.showSuccess('Bulk update completed');
                    this.loadNestedInventoryEditor();
                    try { this.loadItemColors(); } catch(_) {}
                    try { this.loadItemSizes(); } catch(_) {}
                };
                document.getElementById('nestedBulkSet')?.addEventListener('click', () => applyBulk('set'));
                document.getElementById('nestedBulkAdjustPlus')?.addEventListener('click', () => applyBulk('add'));
                document.getElementById('nestedBulkAdjustMinus')?.addEventListener('click', () => applyBulk('sub'));
                host.__nestedBound = true;
            }

        } catch (e) {
            console.error('[NestedEditor] error', e);
            write(`<div class="text-sm text-red-600">Failed to load nested inventory: ${e?.message || 'Unknown error'}</div>`);
        } finally {
            // Final guard: never leave the loading text in place
            const live = document.getElementById('nestedInventoryEditor');
            if (live) {
                const txt = (live.textContent || '').trim();
                if (/^Loading(\s+nested\s+inventory)?…?$/.test(txt)) {
                    write('<div class="text-sm text-gray-500">No option inventory defined yet.</div>');
                }
            }
        }
    }

    // Initialize SKU from DOM early to ensure handlers have access to it
    initSkuFromDom() {
        try {
            // Try several DOM sources, in order of reliability
            if (!this.currentItemSku) {
                // 1) Hidden input emitted by server in edit mode
                const hiddenSku = document.querySelector('input[name="itemSku"]');
                if (hiddenSku && hiddenSku.value) this.currentItemSku = hiddenSku.value.trim();
            }
            if (!this.currentItemSku) {
                // 2) Editable SKU field
                const skuEdit = document.getElementById('skuEdit');
                if (skuEdit && (skuEdit.value || skuEdit.textContent)) this.currentItemSku = (skuEdit.value || skuEdit.textContent).trim();
            }
            if (!this.currentItemSku) {
                // 3) Generic fallbacks used in some templates
                const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
                if (skuField && (skuField.value || skuField.textContent)) this.currentItemSku = (skuField.value || skuField.textContent).trim();
            }
            if (!this.currentItemSku) {
                // 4) Panel data attribute
                const panel = document.getElementById('optionCascadePanel');
                const dsSku = panel && panel.dataset ? (panel.dataset.sku || '') : '';
                if (dsSku) this.currentItemSku = dsSku.trim();
            }
            if (!this.currentItemSku) {
                // 5) Marketing modal header fallback
                const m = document.getElementById('currentEditingSku');
                const txt = m ? (m.textContent || '') : '';
                const match = txt.match(/SKU:\s*(\S+)/i);
                if (match && match[1]) this.currentItemSku = match[1].trim();
            }
        } catch(_) {}
    }

    bindEvents() {
        // Ensure modal is visible even if API calls fail
        const modalOverlay = document.getElementById('inventoryModalOuter');
        if (modalOverlay && !modalOverlay.classList.contains('show')) {
            if (typeof window.showModal === 'function') {
                window.showModal('inventoryModalOuter');
            } else {
                modalOverlay.classList.add('show');
            }
        }

        // Bind delegated keydown handler for inline editing
        document.addEventListener('keydown', (event) => this.handleDelegatedKeydown(event));

        // Bind data-action handlers for modal interactions
        document.addEventListener('click', (event) => {
            const element = event.target.closest('[data-action]');
            if (element) {
                const action = element.getAttribute('data-action');
                switch (action) {
            case 'recompute-nested-totals': {
                try {
                    // Update per-color totals
                    document.querySelectorAll('#nestedInventoryEditor [data-color-id]').forEach(block => {
                        const inputs = block.querySelectorAll('input.wf-nested-stock-input');
                        let sum = 0;
                        inputs.forEach(inp => { const v = Math.floor(Number(inp.value || 0)); if (Number.isFinite(v) && v > -Infinity) sum += Math.max(0, v); });
                        const label = block.querySelector('.wf-color-total');
                        if (label) label.textContent = `Total: ${sum}`;
                    });
                    // Update per-gender totals (each top-level <details>)
                    document.querySelectorAll('#nestedInventoryEditor > details').forEach(group => {
                        const inputs = group.querySelectorAll('input.wf-nested-stock-input');
                        let sum = 0;
                        inputs.forEach(inp => { const v = Math.floor(Number(inp.value || 0)); if (Number.isFinite(v) && v > -Infinity) sum += Math.max(0, v); });
                        const label = group.querySelector('.wf-gender-total');
                        if (label) label.textContent = `Total: ${sum}`;
                    });
                    this.showInfo('Totals recomputed from current inputs (not saved)');
                } catch (_) {}
                break;
            }
            case 'save-visible-size-stocks': {
                // Persist all visible size inputs under the nested editor, sequentially
                const btn = target;
                try { btn.setAttribute('aria-busy', 'true'); btn.disabled = true; } catch(_) {}
                (async () => {
                    const inputs = Array.from(document.querySelectorAll('#nestedInventoryEditor .wf-nested-stock-input'));
                    if (!inputs.length) { this.showInfo('No visible size rows to save'); return; }
                    let lastTotal = null;
                    for (const inp of inputs) {
                        const sizeId = Number(inp.getAttribute('data-size-id') || 0);
                        const newVal = Math.max(0, Math.floor(Number(inp.value || 0)));
                        if (!Number.isFinite(newVal) || sizeId <= 0) continue;
                        try {
                            const data = await ApiClient.post('/api/item_sizes.php?action=update_stock', {
                                size_id: sizeId,
                                stock_level: newVal
                            }).catch(()=>({}));
                            if (!data || !data.success) throw new Error(data?.message || 'Save failed');
                            if (typeof data.new_total_stock !== 'undefined') lastTotal = data.new_total_stock;
                        } catch (e) {
                            this.showError('Error saving some rows: ' + (e.message || ''));
                            break;
                        }
                    }
                    // Update top-level stock field if we have a total
                    if (lastTotal !== null) {
                        const stockField = document.getElementById('stockLevel');
                        if (stockField) stockField.value = lastTotal;
                    }
                    // Refresh views so totals/labels align with server
                    this.loadNestedInventoryEditor();
                    try { this.loadItemColors(); } catch(_) {}
                    try { this.loadItemSizes(); } catch(_) {}
                    this.showSuccess('All visible size totals saved');
                })().finally(() => { try { btn.removeAttribute('aria-busy'); btn.disabled = false; } catch(_) {} });
                break;
            }
            case 'recompute-nested-totals':
                try {
                    // For each color block, sum visible size inputs and update the total label
                    document.querySelectorAll('#nestedInventoryEditor [data-color-id]').forEach(block => {
                        const inputs = block.querySelectorAll('input.wf-nested-stock-input');
                        let sum = 0;
                        inputs.forEach(inp => { const v = Math.floor(Number(inp.value || 0)); if (Number.isFinite(v) && v > 0) sum += v; });
                        const label = block.querySelector('.wf-color-total');
                        if (label) label.textContent = `Total: ${sum}`;
                    });
                    this.showInfo('Totals recomputed from current inputs (not saved)');
                } catch (_) {}
                break;
                    case 'close-admin-editor':
                        this.closeAdminEditor();
                        break;
                    case 'save-inventory':
                        // Form submission is handled by the form itself
                        break;
                    case 'sync-size-stock':
                        this.syncSizeStock();
                        break;
                    case 'add-item-size':
                        this.showSizeModal();
                        break;
                    case 'add-item-color':
                        this.showColorModal();
                        break;
                    case 'open-marketing-manager':
                        this.initSkuFromDom();
                        this.openMarketingManager();
                        break;
                    case 'generate-marketing-copy':
                        // Generate AI marketing content inside Marketing Manager modal
                        this.initSkuFromDom();
                        this.handleGenerateAllMarketing(element);
                        break;
                    case 'close-marketing-manager':
                        this.closeMarketingManager();
                        break;
                    case 'get-cost-suggestion':
                        this.getCostSuggestion();
                        break;
                    case 'get-price-suggestion':
                        this.getPriceSuggestion();
                        break;
                    case 'process-images-ai':
                        this.processImagesWithAI();
                        break;
                    case 'apply-price-suggestion':
                        this.applyPriceSuggestion();
                        break;
                    case 'clear-price-suggestion':
                        this.clearPriceSuggestion();
                        break;
                    case 'open-cost-modal': {
                        const category = element.getAttribute('data-category') || '';
                        this.openCostModal(category);
                        break;
                    }
                    case 'close-cost-modal':
                        this.closeCostModal();
                        break;
                    case 'save-cost-item':
                        this.saveCostItem();
                        break;
                    case 'close-cost-suggestion-choice-dialog':
                        this.closeCostSuggestionChoiceDialog();
                        break;
                    case 'populate-cost-breakdown-from-suggestion': {
                        try {
                            const raw = element.getAttribute('data-suggestion') || '';
                            const parsed = JSON.parse(raw.replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
                            this.populateCostBreakdownFromSuggestion(parsed);
                        } catch (e) {
                            console.error('Invalid suggestion payload:', e);
                            this.showError('Invalid suggestion payload');
                        }
                        break;
                    }
                    case 'apply-suggested-cost-to-cost-field': {
                        this.applySuggestedCostToCostField(element);
                        break;
                    }
                    case 'add-list-item': {
                        const field = element.getAttribute('data-field') || '';
                        this.handleAddListItem(field);
                        break;
                    }
                    case 'remove-list-item': {
                        const field = element.getAttribute('data-field') || '';
                        const item = element.getAttribute('data-item') || '';
                        this.handleRemoveListItem(field, item, element);
                        break;
                    }
                    case 'save-marketing-field': {
                        this.handleSaveMarketingField(fieldName, element);
                        break;
                    }
                    case 'apply-selected-comparison':
                    case 'apply-selected-comparison-changes':
                        this.applySelectedComparisonChanges();
                        break;
                    case 'save-option-settings':
                        this.saveOptionSettings && this.saveOptionSettings();
                        break;
                    case 'reload-option-settings':
                        this.loadOptionSettings && this.loadOptionSettings();
                        break;
                    default:
                        // Handle other data-action attributes
                        break;
                }
            }
        });

        // Bind delegated change handlers for comparison toggles and marketing defaults
        document.addEventListener('click', async (event) => {
            const el = event.target;
            if (!(el instanceof HTMLElement)) return;

            // Toggle individual comparison checkbox
            if (el.matches('[data-action="toggle-comparison"][data-field]')) {
                const fieldKey = el.getAttribute('data-field') || '';
                if (fieldKey) this.toggleComparison(fieldKey);
                return;
            }

            // Select all comparison
            if (el.id === 'selectAllComparison') {
                this.toggleSelectAllComparison();
                return;
            }

            // Marketing defaults (brand voice, content tone)
            if (el.matches('[data-action="marketing-default-change"]')) {
                const settingType = el.getAttribute('data-setting') || '';
                const value = (el.value || '').trim();
                if (settingType) this.updateGlobalMarketingDefault(settingType, value);
                return;
            }
        });

        console.log('[AdminInventory] Event listeners bound');

        // Initialize editor data if editor containers are present
        try {
            // Ensure SKU is available for loaders
            this.initSkuFromDom();

            // Load current images into the editor if the container exists
            if (document.getElementById('currentImagesList')) {
                this.loadModalImages();
            }

            // Load colors/sizes panels if present
            if (document.getElementById('colorsList')) {
                this.loadItemColors();
            }
            if (document.getElementById('sizesList')) {
                this.loadItemSizes();
            }
            // Load option cascade settings if panel exists
            if (document.getElementById('optionCascadePanel')) {
                this.loadOptionSettings && this.loadOptionSettings();
            }
            // Render nested inventory editor if present
            if (document.getElementById('nestedInventoryEditor')) {
                this.loadNestedInventoryEditor();
            }
            // If we still have empty lists due to delayed SKU detection, try again shortly
            setTimeout(() => {
                if (!this.currentItemSku) this.initSkuFromDom();
                this.__ensureEditorDataLoadedIfAvailable();
            }, 0);
        } catch (e) {
            console.warn('[AdminInventory] Failed to initialize editor loaders', e);
        }

        // Enable guarded inline editing for the inventory table
        try { this.enableInlineEditing(); } catch(_) {}
    }

    async shouldBlockStockEdit(sku) {
        try {
            const queries = [];
            // Settings to check enabled dimensions
            queries.push(ApiClient.get('/api/item_options.php', { action: 'get_settings', item_sku: sku, wf_dev_admin: 1 }).catch(()=>({}))); 
            // Presence of sizes/colors/genders
            queries.push(ApiClient.get('/api/item_colors.php', { action: 'get_colors', item_sku: sku }).catch(()=>({}))); 
            queries.push(ApiClient.get('/api/item_sizes.php', { action: 'get_sizes', item_sku: sku }).catch(()=>({}))); 
            queries.push(ApiClient.get('/api/item_genders.php', { action: 'get_all', item_sku: sku }).catch(()=>({}))); 

            const [settingsRes, colorsRes, sizesRes, gendersRes] = await Promise.all(queries);
            const enabled = settingsRes?.settings?.enabled_dimensions || [];
            const hasEnabledDims = Array.isArray(enabled) && enabled.length > 0 && enabled.some(k => ['gender','size','color'].includes(k));
            const hasColors = Array.isArray(colorsRes?.colors) && colorsRes.colors.length > 0;
            const hasSizes = Array.isArray(sizesRes?.sizes) && sizesRes.sizes.length > 0;
            const hasGenders = Array.isArray(gendersRes?.genders) && gendersRes.genders.length > 0;
            return !!(hasEnabledDims || hasColors || hasSizes || hasGenders);
        } catch(_) { return false; }
    }

    enableInlineEditing() {
        const table = document.getElementById('inventoryTable');
        if (!table) return;
        table.addEventListener('click', async (e) => {
            const td = e.target && e.target.closest ? e.target.closest('td.editable[data-field]') : null;
            if (!td) return;
            const field = td.getAttribute('data-field');
            const tr = td.closest('tr');
            const sku = tr ? (tr.getAttribute('data-sku') || '') : '';
            if (!sku) return;

            // Only guard stockLevel for now
            if (field === 'stockLevel') {
                e.preventDefault();
                e.stopPropagation();
                const block = await this.shouldBlockStockEdit(sku);
                if (block) {
                    // Show non-blocking toast with link to editor
                    this.showActionToast('This item has options (gender/size/color). Edit stock in the item editor.', 'Open editor', `/admin/inventory?edit=${encodeURIComponent(sku)}`);
                    return;
                }
                // If no options, allow default browser selection/editing, or implement simple prompt
                // Make cell content editable for quick change
                this.makeCellEditable(td, sku, field);
            }
        });
    }

    makeCellEditable(td, sku, field) {
        const original = td.textContent.trim();
        if (td.querySelector('input')) return;
        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'inline-edit-input border rounded px-1 py-0.5 w-24';
        input.value = original.replace(/[^0-9.-]/g, '');
        td.innerHTML = '';
        td.appendChild(input);
        input.focus();
        input.select();
        const save = async () => {
            const raw = String(input.value || '').trim();
            const num = Number(raw);
            if (!Number.isFinite(num) || num < 0) {
                this.showError('Please enter a non-negative number.');
                td.textContent = original;
                return;
            }
            const newVal = String(Math.floor(num));
            if (newVal === original) { td.textContent = original; return; }
            try {
                const data = await ApiClient.post('/api/update_item_field.php', { sku, field, value: newVal }).catch(() => ({}));
                if (!data || !data.success) throw new Error(data?.error || 'Save failed');
                td.textContent = newVal;
                this.showSuccess('Stock saved');
            } catch (e) {
                td.textContent = original;
                this.showError(e.message || 'Failed to save');
            }
        };
        const cancel = () => { td.textContent = original; };
        input.addEventListener('blur', save, { once: true });
        input.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
            if (ev.key === 'Escape') { ev.preventDefault(); cancel(); }
        });
    }

    /**
     * Ensure editor lists are populated once SKU is known and containers exist.
     */
    __ensureEditorDataLoadedIfAvailable() {
        try {
            if (!this.currentItemSku) return;
            if (document.getElementById('currentImagesList')) {
                // Only (re)load if list appears empty
                if (!document.getElementById('currentImagesList').children.length) {
                    this.loadModalImages && this.loadModalImages();
                }
            }
            if (document.getElementById('colorsList')) {
                if (!document.getElementById('colorsList').children.length) {
                    this.loadItemColors && this.loadItemColors();
                }
            }
            if (document.getElementById('sizesList')) {
                if (!document.getElementById('sizesList').children.length) {
                    this.loadItemSizes && this.loadItemSizes();
                }
            }
        } catch (_) {}
    }

    /**
     * Attach delegated handlers to open the Detailed Item Modal when clicking
     * the View (👁️) or Edit (✏️) actions in the inventory table.
     */
    registerItemDetailsHandlers() {
        console.log('[AdminInventory] Registering item details handlers...');

        // Option B (client-side modal): Handle edit/view within the same page context
        document.addEventListener('click', async (e) => {
            console.log('[AdminInventory] Click detected on document!', e.target);
            // Ignore clicks within inline-editable cells entirely
            try {
                if (e.target && e.target.closest && e.target.closest('.editable-field')) {
                    return;
                }
            } catch(_) {}
            const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            console.log('[AdminInventory] Closest anchor element:', a);

            if (!a) {
                console.log('[AdminInventory] No anchor element found in click path');
                return;
            }

            // Only handle explicit View/Edit buttons inside the actions cell
            try {
                const inActions = !!(a.closest && a.closest('.admin-actions'));
                const isViewOrEditBtn = a.classList && (a.classList.contains('btn-icon--view') || a.classList.contains('btn-icon--edit'));
                if (!inActions || !isViewOrEditBtn) {
                    return;
                }
            } catch(_) {}

            const href = a.getAttribute('href');
            console.log('[AdminInventory] Anchor href:', href);

            if (!href) {
                console.log('[AdminInventory] Anchor has no href attribute');
                return;
            }

            // Check if this is an edit/view link for the current page
            try {
                const url = new URL(href, window.location.origin);
                const sku = url.searchParams.get('view') || url.searchParams.get('edit');
                console.log('[AdminInventory] Parsed URL params:', { sku, view: url.searchParams.get('view'), edit: url.searchParams.get('edit') });

                if (sku && (url.searchParams.get('view') || url.searchParams.get('edit'))) {
                    console.log('[AdminInventory] This is a view/edit link for SKU:', sku);
                    // This is an edit/view link - redirect to admin inventory page for proper admin editor
                    e.preventDefault();
                    console.log('[AdminInventory] Redirecting to admin inventory page for proper admin editor...');

                    // Redirect to the admin inventory page with the same parameters
                    // The admin inventory page will render the proper admin editor with AI options
                    const newUrl = `/admin/inventory?${url.searchParams.toString()}`;
                    console.log('[AdminInventory] Redirecting to:', newUrl);
                    window.location.href = newUrl;
                    return;
                } else {
                    console.log('[AdminInventory] This is not a view/edit link');
                }
            } catch (error) {
                console.error('[AdminInventory] Error parsing URL:', error);
                // Not a URL we care about, continue normal navigation
            }
        }, true);

        console.log('[AdminInventory] Item details handlers registered successfully');

        // Debug: Check for view/edit links in DOM
        this.__debugCheckViewEditLinks();
    }

    __debugCheckViewEditLinks() {
        console.log('[AdminInventory] Checking for view/edit links in DOM...');

        // Look for links with view or edit parameters
        const allLinks = document.querySelectorAll('a[href]');
        console.log('[AdminInventory] Found', allLinks.length, 'total anchor elements');

        const viewEditLinks = Array.from(allLinks).filter(link => {
            const href = link.getAttribute('href');
            if (!href) return false;
            try {
                const url = new URL(href, window.location.origin);
                const sku = url.searchParams.get('view') || url.searchParams.get('edit');
                return sku && (url.searchParams.get('view') || url.searchParams.get('edit'));
            } catch {
                return false;
            }
        });

        console.log('[AdminInventory] Found', viewEditLinks.length, 'view/edit links:', viewEditLinks);

        // Check if inventory-data element exists
        const pageData = document.getElementById('inventory-data');
        console.log('[AdminInventory] Inventory data element:', pageData);

        if (pageData) {
            try {
                const data = JSON.parse(pageData.textContent);
                console.log('[AdminInventory] Current page data:', data);
            } catch (error) {
                console.error('[AdminInventory] Error parsing page data:', error);
            }
        }

        // Set up periodic check to see if links appear later
        let checkCount = 0;
        const checkInterval = setInterval(() => {
            checkCount++;
            const currentLinks = document.querySelectorAll('a[href*="view="], a[href*="edit="]');
            console.log(`[AdminInventory] Check ${checkCount}: Found ${currentLinks.length} view/edit links`);

            if (currentLinks.length > 0 || checkCount >= 10) {
                clearInterval(checkInterval);
                if (currentLinks.length > 0) {
                    console.log('[AdminInventory] View/edit links found:', Array.from(currentLinks).map(link => ({
                        href: link.getAttribute('href'),
                        text: link.textContent.trim(),
                        class: link.className
                    })));
                } else {
                    console.warn('[AdminInventory] No view/edit links found after multiple checks');
                }
            }
        }, 1000);
    }

    /**
     * On page load, if the current URL already has ?view= or ?edit=, open the modal.
     */
    async autoOpenItemFromQuery() {
        // Option A: Server-rendered Admin Item Editor
        // Only redirect if we're NOT already on the admin inventory page
        try {
            const currentPath = window.location.pathname;
            const params = new URLSearchParams(window.location.search || '');
            const sku = params.get('view') || params.get('edit');
            const action = params.get('view') ? 'view' : 'edit';

            if (sku) {
                // Only redirect if we're not already on the admin inventory page
                if (currentPath === '/admin/inventory') {
                    console.log('[AdminInventory] Already on admin inventory page with item parameters:', { sku, action });

                    // Ensure modal is visible even if we're already on the correct page
                    const modalOverlay = document.getElementById('inventoryModalOuter');
                    if (modalOverlay) {
                        if (typeof window.showModal === 'function') {
                            window.showModal('inventoryModalOuter');
                        } else {
                            modalOverlay.classList.add('show');
                        }
                        // Also ensure the body doesn't have modal-open class to prevent background scrolling issues
                        document.body.classList.add('modal-open');
                    }

                    return; // Don't redirect, we're already on the correct page
                }

                console.log('[AdminInventory] URL contains item parameters, redirecting to admin inventory page:', { sku, action });

                // Redirect to the admin inventory page with the same parameters
                // The admin inventory page will render the proper admin editor with AI options
                const newUrl = `/admin/inventory?${params.toString()}`;
                console.log('[AdminInventory] Redirecting to:', newUrl);
                window.location.href = newUrl;
                return;
            }
        } catch (error) {
            console.error('Error in autoOpenItemFromQuery:', error);
        }
    }

    /**
     * Fetches rendered HTML for the detailed item modal and shows it.
     * Uses /api/render_detailed_modal.php with the item and its images.
     * @deprecated - Admin inventory now uses server-rendered admin editor
     */
    async openDetailedItemModal(sku) {
        console.warn('[AdminInventory] openDetailedItemModal is deprecated. Admin inventory now uses server-rendered admin editor.');
        console.log('[AdminInventory] Redirecting to admin inventory page for proper admin editor...');
        window.location.href = `/admin/inventory?view=${encodeURIComponent(sku)}`;
    }

    __ensureModalVisible(el) {
        try { el.classList.remove('hidden'); } catch(_) {}
        try { el.classList.add('show'); } catch(_) {}
        try { el.classList.add('force-visible'); } catch(_) {}
    }

    __forceModalVisibility(el) {
        console.log('[AdminInventory] Forcing modal visibility...');
        try {
            // Apply all possible visibility classes
            el.classList.remove('hidden');
            el.classList.add('show');
            el.classList.add('force-visible');
            el.classList.add('visible');

            // Add emergency visibility CSS if not already present
            let emergencyStyle = document.getElementById('admin-modal-emergency-styles');
            if (!emergencyStyle) {
                emergencyStyle = document.createElement('style');
                emergencyStyle.id = 'admin-modal-emergency-styles';
                emergencyStyle.textContent = `
                    .admin-modal-emergency-visible {
                        display: flex !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                        z-index: 999999 !important;
                        position: fixed !important;
                        inset: 0 !important;
                    }
                `;
                document.head.appendChild(emergencyStyle);
            }

            // Apply emergency visibility class
            el.classList.add('admin-modal-emergency-visible');

            console.log('[AdminInventory] Applied all visibility classes and emergency CSS');

            // Check again after applying styles
            setTimeout(() => {
                const computedStyle = window.getComputedStyle(el);
                console.log('[AdminInventory] Modal visibility after forcing:', {
                    display: computedStyle.display,
                    visibility: computedStyle.visibility,
                    opacity: computedStyle.opacity,
                    classes: Array.from(el.classList)
                });
            }, 10);

        } catch (error) {
            console.error('[AdminInventory] Error forcing modal visibility:', error);
        }
    }

    /**
     * Ensure admin editor overlay/panel visibility and normalize classes/styles
     * to avoid z-index or display conflicts.
     */
    __normalizeAdminEditorOverlay(overlay) {
        try {
            // Ensure overlay is visible via classes only
            overlay.classList.remove('hidden');
            overlay.classList.add('show');
        } catch(_) {}
        try {
            const panel = overlay.querySelector('.admin-modal');
            if (panel) {
                panel.classList.add('wf-admin-panel-visible');
            }
        } catch(_) {}
    }

    /**
     * Diagnostic: log computed z-index and related CSS variable values
     * for the admin editor overlay and its panel.
     */
    __logAdminEditorStacking(overlay) {
        try {
            const cs = window.getComputedStyle(overlay);
            const root = window.getComputedStyle(document.documentElement);
            const overlayZ = cs.zIndex;
            const varOverlayZ = root.getPropertyValue('--wf-admin-overlay-z').trim() || '(unset)';
            const panel = overlay.querySelector('.admin-modal');
            const panelCS = panel ? window.getComputedStyle(panel) : null;
            const panelZ = panelCS ? panelCS.zIndex : '(no panel)';
            const varPanelZ = root.getPropertyValue('--wf-admin-overlay-content-z').trim() || '(unset)';
            console.log('[AdminInventory][Z] overlay', {
                classes: Array.from(overlay.classList),
                overlayZ,
                varOverlayZ,
                display: cs.display,
                visibility: cs.visibility,
                opacity: cs.opacity,
            });
            console.log('[AdminInventory][Z] panel', {
                panelFound: !!panel,
                classes: panel ? Array.from(panel.classList) : [],
                panelZ,
                varPanelZ,
            });
        } catch (e) {
            console.warn('[AdminInventory] Failed to log admin editor stacking', e);
        }
    }

    /**
     * Set up diagnostics and normalization for server-rendered editor overlay.
     * @deprecated - Server-rendered modals handle their own visibility
     */
    __setupOverlayDiagnostics() {
        console.log('[AdminInventory] Server-rendered modals handle their own visibility - skipping diagnostics');
        // Server-rendered admin editor modal handles its own visibility
        // No need for client-side diagnostics or manipulation
    }

    closeDetailedItemModal() {
        // No longer needed since we redirect to admin inventory page
        console.warn('[AdminInventory] closeDetailedItemModal is no longer needed - admin inventory uses server-rendered editor.');
    }

    __useGlobalModalFallback(sku) {
        console.log('[AdminInventory] Using global modal system as fallback for SKU:', sku);

        // Try to use the global modal system if available
        if (window.WhimsicalFrog && window.WhimsicalFrog.GlobalModal && window.WhimsicalFrog.GlobalModal.show) {
            try {
                // Get the item data we already fetched
                const itemData = this.currentItemData;
                if (itemData) {
                    console.log('[AdminInventory] Using pre-fetched item data for global modal');
                    window.WhimsicalFrog.GlobalModal.show(sku, itemData);
                } else {
                    console.log('[AdminInventory] No pre-fetched data, calling global modal without data');
                    window.WhimsicalFrog.GlobalModal.show(sku);
                }
            } catch (error) {
                console.error('[AdminInventory] Global modal fallback failed:', error);
                this.showError('Modal system failed. Please try the direct link.');
            }
        } else {
            console.error('[AdminInventory] Global modal system not available');
            this.showError('Modal system unavailable. Please try the direct link.');
        }
    }

    closeAdminEditor() {
        try {
            const overlay = document.getElementById('inventoryModalOuter');
            if (overlay) {
                if (typeof window.hideModal === 'function') {
                    window.hideModal('inventoryModalOuter');
                } else {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('show');
                }
            }
        } catch (_) {}
        try {
            document.documentElement.classList.remove('modal-open');
            document.body.classList.remove('modal-open');
        } catch (_) {}
        // Don't redirect - just close the modal and stay on the current page
        console.log('[AdminInventory] Modal closed, staying on current page');
    }

    handleDelegatedKeydown(event) {
        const t = event.target;
        if (!(t instanceof HTMLElement)) return;
        if (t.matches('input[data-add-enter-field]') && event.key === 'Enter') {
            event.preventDefault();
            const field = t.getAttribute('data-add-enter-field');
            if (field) this.handleAddListItem(field);
        }
    }

    // Colors: Load and render
    async loadItemColors() {
        // Fallback to form fields if not provided via inventory-data
        if (!this.currentItemSku) {
            const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
            if (skuField && skuField.value) this.currentItemSku = skuField.value;
        }
        if (!this.currentItemSku) {
            const colorsLoading = document.getElementById('colorsLoading');
            if (colorsLoading) {
                colorsLoading.textContent = 'No SKU available';
                colorsLoading.classList.remove('hidden');
            }
            return;
        }
        const colorsLoading = document.getElementById('colorsLoading');
        if (colorsLoading) {
            colorsLoading.textContent = 'Loading colors...';
            colorsLoading.classList.remove('hidden');
        }
        try {
            // Fetch colors and sizes together so we can derive per-color totals from sizes
            const [colorsRes, sizesRes] = await Promise.all([
                ApiClient.get('/api/item_colors.php', { action: 'get_all_colors', item_sku: this.currentItemSku }).then(r => r).catch(()=>({})),
                ApiClient.get('/api/item_sizes.php', { action: 'get_all_sizes', item_sku: this.currentItemSku, wf_dev_admin: 1 }).then(r => r).catch(()=>({}))
            ]);
            const colorsOk = !!colorsRes?.success;
            const sizesOk = !!sizesRes?.success;
            const colors = colorsOk ? (colorsRes.colors || []) : [];
            const sizes = sizesOk ? (sizesRes.sizes || []) : [];
            const totalsByColor = sizes.reduce((acc, s) => {
                const cid = s.color_id ? String(s.color_id) : null;
                if (cid) acc[cid] = (acc[cid] || 0) + (parseInt(s.stock_level, 10) || 0);
                return acc;
            }, {});
            this.renderColors(colors, totalsByColor);
        } catch (e) {
            console.error('Error fetching colors/sizes:', e);
            this.renderColors([]);
        }
    }

    renderColors(colors, totalsByColor = {}) {
        const colorsList = document.getElementById('colorsList');
        const colorsLoading = document.getElementById('colorsLoading');
        if (colorsLoading) colorsLoading.classList.add('hidden');
        if (!colorsList) return;
        if (!Array.isArray(colors) || colors.length === 0) {
            colorsList.innerHTML = '<div class="text-center text-gray-500 text-sm">No colors defined. Click "Add Color" to get started.</div>';
            return;
        }
        const activeColors = colors.filter(c => c.is_active == 1);
        const totalColorStock = activeColors.reduce((sum, c) => {
            const cid = String(c.id);
            const derived = totalsByColor[cid];
            const val = typeof derived === 'number' ? derived : (parseInt(c.stock_level, 10) || 0);
            return sum + val;
        }, 0);
        const stockField = document.getElementById('stockLevel');
        const currentItemStock = stockField ? (parseInt(stockField.value, 10) || 0) : 0;
        const isInSync = totalColorStock === currentItemStock;
        let html = '';
        if (activeColors.length > 0) {
            const syncClass = isInSync ? 'bg-green-50 border-green-200 text-green-800' : 'bg-yellow-50 border-yellow-200 text-yellow-800';
            const syncIcon = isInSync ? '✅' : '⚠️';
            const syncMessage = isInSync ?
                `Stock synchronized (${totalColorStock} total)` :
                `Stock out of sync! Colors total: ${totalColorStock}, Item stock: ${currentItemStock}`;
            html += `
                <div class="border rounded-lg ${syncClass}">
                    <div class="text-sm font-medium">${syncIcon} ${syncMessage}</div>
                    ${!isInSync ? '<div class="text-xs">Click "Sync Stock" to fix this.</div>' : ''}
                </div>
            `;
        }
        html += colors.map(color => {
            const isActive = color.is_active == 1;
            const activeClass = isActive ? 'bg-white' : 'bg-gray-100 opacity-75';
            const activeText = isActive ? '' : ' (Inactive)';
            const cid = String(color.id);
            const derivedTotal = totalsByColor[cid];
            const displayTotal = typeof derivedTotal === 'number' ? derivedTotal : (parseInt(color.stock_level, 10) || 0);
            return `
                <div class="color-item flex items-center justify-between border border-gray-200 rounded-lg ${activeClass}">
                    <div class="flex items-center space-x-3">
                        <div class="color-swatch w-8 h-8 rounded-full border-2 border-gray-300" ${color.color_code ? `data-color="${color.color_code}"` : ''}></div>
                        <div>
                            <div class="font-medium text-gray-800">${color.color_name}${activeText} <span class=\"ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-700 border border-gray-200\" title=\"Items total for this color\">Total: ${displayTotal}</span></div>
                            <div class="text-sm text-gray-500 flex items-center">
                                <span class="inline-stock-editor"
                                      data-type="color"
                                      data-id="${color.id}"
                                      data-field="stock_level"
                                      data-value="${color.stock_level}"
                                      data-action="edit-inline-stock"
                                      title="Click to edit stock level">${color.stock_level}</span>
                                <span class="">in stock</span>
                            </div>
                            ${color.image_path ? `<div class="text-xs text-blue-600">Image: ${color.image_path}</div>` : ''}
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button type="button" data-action="delete-color" data-id="${color.id}" class="bg-red-500 text-white rounded text-xs hover:bg-red-600">Delete</button>
                    </div>
                </div>
            `;
        }).join('');
        colorsList.innerHTML = html;
        // Apply dynamic color classes to swatches (no inline styles)
        colorsList.querySelectorAll('.color-swatch[data-color]').forEach(el => {
            const raw = el.getAttribute('data-color');
            if (!raw) return;
            const hex = (raw || '').trim().toLowerCase();
            const norm = hex.startsWith('#') ? hex : `#${hex}`;
            const six = norm.length === 4
                ? `#${norm[1]}${norm[1]}${norm[2]}${norm[2]}${norm[3]}${norm[3]}`
                : norm;
            const key = six.replace('#','');
            const cls = `color-var-${key}`;
            // Ensure a single stylesheet defines the class
            const set = (window.__wfInventoryColorClasses ||= new Set());
            let styleEl = document.getElementById('inventory-color-classes');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'inventory-color-classes';
                document.head.appendChild(styleEl);
            }
            if (!set.has(cls)) {
                styleEl.textContent += `\n.${cls}{--swatch-color:${six};}`;
                set.add(cls);
            }
            el.classList.add(cls);
        });
    }

    // Color modal creation/show helpers
    showColorModal(color = null) {
        if (!document.getElementById('colorModal')) {
            this.createColorModal();
        }
        const modal = document.getElementById('colorModal');
        const form = document.getElementById('colorForm');
        const modalTitle = document.getElementById('colorModalTitle');
        if (!modal || !form) return;
        form.reset();
        if (color) {
            modalTitle.textContent = 'Edit Color';
            document.getElementById('colorId').value = color.id;
            document.getElementById('colorName').value = color.color_name;
            document.getElementById('colorCode').value = color.color_code || '';
            document.getElementById('colorStockLevel').value = color.stock_level || 0;
            document.getElementById('displayOrder').value = color.display_order || 0;
            document.getElementById('isActive').checked = color.is_active == 1;
            if (color.image_path) {
                const hiddenInput = document.getElementById('colorImagePath');
                if (hiddenInput) hiddenInput.value = color.image_path;
                this.updateImagePreview();
                setTimeout(() => { this.highlightSelectedImageInGrid(color.image_path); }, 100);
            } else {
                const prev = document.getElementById('imagePreviewContainer');
                if (prev) prev.classList.add('hidden');
                setTimeout(() => { this.highlightSelectedImageInGrid(null); }, 100);
            }
        } else {
            modalTitle.textContent = 'Add New Color';
            const prev = document.getElementById('imagePreviewContainer');
            if (prev) prev.classList.add('hidden');
            setTimeout(() => { this.highlightSelectedImageInGrid(null); }, 100);
        }
        modal.classList.remove('hidden');
    }

    createColorModal() {
        const modalHTML = `
        <div id="colorModal" class="modal-overlay hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="colorModalTitle">Add New Color</h2>
                    <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-color-modal" aria-label="Close">×</button>
                </div>
                <div class="modal-body">
                    <form id="colorForm">
                        <input type="hidden" id="colorId" name="colorId">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div class="space-y-4">
                                <div>
                                    <label for="globalColorSelect" class="block text-sm font-medium text-gray-700">Select Color * <span class="text-xs text-gray-500">(from predefined colors)</span></label>
                                    <select id="globalColorSelect" name="globalColorSelect" required class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2">
                                        <option value="">Choose a color...</option>
                                    </select>
                                    <div class="text-xs">
                                        <a href="#" data-action="open-global-colors-management" class="text-blue-600 hover:text-blue-800"><span class="btn-icon btn-icon--settings" aria-hidden="true"></span> Manage Global Colors in Settings</a>
                                    </div>
                                </div>
                                <input type="hidden" id="colorName" name="colorName">
                                <input type="hidden" id="colorCode" name="colorCode">
                                <div id="selectedColorPreview" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700">Selected Color Preview</label>
                                    <div class="flex items-center space-x-3 bg-gray-50 rounded-lg">
                                        <div id="colorPreviewSwatch" class="w-12 h-12 rounded border-2 border-gray-300 shadow-sm"></div>
                                        <div>
                                            <div id="colorPreviewName" class="font-medium text-gray-900"></div>
                                            <div id="colorPreviewCode" class="text-sm text-gray-500"></div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="colorStockLevel" class="block text-sm font-medium text-gray-700">Stock Level</label>
                                    <input type="number" id="colorStockLevel" name="stockLevel" min="0" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2">
                                </div>
                                <div>
                                    <label for="displayOrder" class="block text-sm font-medium text-gray-700">Display Order</label>
                                    <input type="number" id="displayOrder" name="displayOrder" min="0" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2">
                                </div>
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="isActive" name="isActive" class="">
                                        <span class="text-sm font-medium text-gray-700">Active (visible to customers)</span>
                                    </label>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div id="availableImagesGrid">
                                    <label class="block text-sm font-medium text-gray-700">Available Images <span class="text-xs text-gray-500 font-normal">(click to select for this color)</span></label>
                                    <div class="grid grid-cols-4 gap-3 max-h-48 overflow-y-auto border border-gray-200 rounded bg-gray-50"></div>
                                </div>
                                <div id="imagePreviewContainer" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700">Selected Image Preview</label>
                                    <div class="border border-gray-300 rounded-lg bg-gray-50">
                                        <div class="flex justify-center">
                                            <img id="imagePreview" src="" alt="Selected image preview" class="max-h-64 object-contain rounded border border-gray-200 shadow-sm">
                                        </div>
                                        <div id="imagePreviewInfo" class="text-center">
                                            <div id="imagePreviewName" class="text-sm font-medium text-gray-700"></div>
                                            <div id="imagePreviewPath" class="text-xs text-gray-500"></div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="colorImagePath" name="colorImagePath" value="">
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3 border-t border-gray-200">
                            <button type="button" data-action="close-color-modal" class="bg-gray-300 text-gray-800 rounded hover:bg-gray-400 transition-colors">Cancel</button>
                            <button type="submit" class="text-white rounded transition-colors bg-[#87ac3a] hover:bg-[#BF5700]">Save Color</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        // Populate dropdowns
        this.loadGlobalColorsForSelection();
        this.loadAvailableImages();
    }

    async loadAvailableImages() {
        if (!this.currentItemSku) return;
        try {
            const data = await ApiClient.get(`/api/get_item_images.php?sku=${this.currentItemSku}`);
            const availableImagesGrid = document.getElementById('availableImagesGrid');
            if (!availableImagesGrid) return;
            const gridContainer = availableImagesGrid.querySelector('.grid');
            if (!gridContainer) return;
            if (data.success && Array.isArray(data.images) && data.images.length > 0) {
                gridContainer.innerHTML = '';
                data.images.forEach(image => {
                    const imgContainer = document.createElement('div');
                    imgContainer.className = 'relative cursor-pointer hover:opacity-75 transition-all hover:scale-105 hover:shadow-md p-1 rounded';
                    imgContainer.addEventListener('click', () => this.selectImageFromGrid(image.image_path));
                    const img = document.createElement('img');
                    const imageSrc = image.image_path.startsWith('/images/items/') || image.image_path.startsWith('images/items/')
                        ? image.image_path
                        : `/images/items/${image.image_path}`;
                    img.src = imageSrc;
                    img.alt = image.image_path;
                    img.className = 'w-full h-20 object-cover rounded border border-gray-200 hover:border-green-400 transition-colors';
                    img.onerror = () => {
                        img.classList.add('hidden');
                        img.parentElement.innerHTML = '<div class="u-width-100 u-height-100 u-display-flex u-flex-direction-column u-align-items-center u-justify-content-center u-background-f8f9fa u-color-6c757d u-border-radius-8px"><div class="u-font-size-2rem u-margin-bottom-0-5rem u-opacity-0-7">📷</div><div class="u-font-size-0-8rem u-font-weight-500">Image Not Found</div></div>';
                    };
                    const label = document.createElement('div');
                    label.className = 'text-xs text-gray-600 mt-1 text-center';
                    label.textContent = image.image_path;
                    if (image.is_primary) {
                        const badge = document.createElement('div');
                        badge.className = 'absolute top-0 right-0 bg-green-500 text-white text-xs px-1 rounded-bl';
                        badge.textContent = '1°';
                        imgContainer.appendChild(badge);
                    }
                    imgContainer.appendChild(img);
                    imgContainer.appendChild(label);
                    gridContainer.appendChild(imgContainer);
                });
                availableImagesGrid.classList.remove('hidden');
            } else {
                gridContainer.innerHTML = '<div class="col-span-4 text-center text-gray-500"><div class="text-3xl">📷</div><div class="text-sm">No images available for this item</div></div>';
                availableImagesGrid.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error loading available images:', error);
            const gridContainer = document.querySelector('#availableImagesGrid .grid');
            if (gridContainer) {
                gridContainer.innerHTML = '<div class="col-span-4 text-center text-red-500"><div class="text-sm">Error loading images</div></div>';
            }
        }
    }

    selectImageFromGrid(imagePath) {
        const hiddenInput = document.getElementById('colorImagePath');
        if (hiddenInput) {
            hiddenInput.value = imagePath;
            this.updateImagePreview();
            this.highlightSelectedImageInGrid(imagePath);
        }
    }

    updateImagePreview() {
        const hiddenInput = document.getElementById('colorImagePath');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const imagePreview = document.getElementById('imagePreview');
        const imagePreviewName = document.getElementById('imagePreviewName');
        const imagePreviewPath = document.getElementById('imagePreviewPath');
        if (!hiddenInput || !imagePreviewContainer) return;
        const selectedImagePath = hiddenInput.value;
        if (selectedImagePath) {
            const fallbackSrc = selectedImagePath.startsWith('/images/items/') || selectedImagePath.startsWith('images/items/')
                ? selectedImagePath
                : `/images/items/${selectedImagePath}`;
            imagePreview.src = fallbackSrc;
            imagePreview.onerror = () => {
                imagePreview.classList.add('hidden');
                imagePreview.parentElement.innerHTML = '<div class="u-width-100 u-height-100 u-display-flex u-flex-direction-column u-align-items-center u-justify-content-center u-background-f8f9fa u-color-6c757d u-border-radius-8px"><div class="u-font-size-2rem u-margin-bottom-0-5rem u-opacity-0-7">📷</div><div class="u-font-size-0-8rem u-font-weight-500">No Image Available</div></div>';
            };
            if (imagePreviewName) imagePreviewName.textContent = selectedImagePath;
            if (imagePreviewPath) imagePreviewPath.textContent = previewSrc;
            imagePreviewContainer.classList.remove('hidden');
        } else {
            imagePreviewContainer.classList.add('hidden');
        }
    }

    highlightSelectedImageInGrid(selectedPath) {
        const gridContainer = document.querySelector('#availableImagesGrid .grid');
        if (!gridContainer) return;
        const imageContainers = gridContainer.querySelectorAll('div');
        imageContainers.forEach(container => {
            const img = container.querySelector('img');
            if (!img) return;
            const imagePath = img.alt;
            if (selectedPath && imagePath === selectedPath) {
                container.classList.add('ring-2', 'ring-green-500', 'bg-green-50');
                img.classList.add('border-green-400');
            } else {
                container.classList.remove('ring-2', 'ring-green-500', 'bg-green-50');
                img.classList.remove('border-green-400');
            }
        });
    }

    async loadGlobalColorsForSelection() {
        try {
            const data = await ApiClient.get('/api/global_color_size_management.php?action=get_global_colors');
            const select = document.getElementById('globalColorSelect');
            if (!select) return;
            select.innerHTML = '<option value="">Choose a color...</option>';
            if (data.success && Array.isArray(data.colors) && data.colors.length > 0) {
                const colorsByCategory = {};
                data.colors.forEach(color => {
                    const category = color.category || 'General';
                    if (!colorsByCategory[category]) colorsByCategory[category] = [];
                    colorsByCategory[category].push(color);
                });
                Object.keys(colorsByCategory).sort().forEach(category => {
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = category;
                    colorsByCategory[category].forEach(color => {
                        const option = document.createElement('option');
                        option.value = JSON.stringify({ id: color.id, name: color.color_name, code: color.color_code, category: color.category });
                        option.textContent = `${color.color_name} ${color.color_code ? '(' + color.color_code + ')' : ''}`;
                        optgroup.appendChild(option);
                    });
                    select.appendChild(optgroup);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No global colors available - add some in Settings';
                option.disabled = true;
                select.appendChild(option);
            }
        } catch (e) {
            console.error('Error loading global colors:', e);
            this.showError('Error loading global colors');
        }
    }

    // Sizes: Load, render, and modal helpers
    async loadItemSizes(colorId = null) {
        // Fallback to form fields if sku missing
        if (!this.currentItemSku) {
            const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
            if (skuField && skuField.value) this.currentItemSku = skuField.value;
        }
        if (!this.currentItemSku) return;
        let targetColorId = colorId;
        if (targetColorId === null) {
            const colorFilter = document.getElementById('sizeColorFilter');
            if (colorFilter) targetColorId = colorFilter.value;
        }
        try {
            let url = `/api/item_sizes.php?action=get_all_sizes&item_sku=${this.currentItemSku}`;
            if (targetColorId && targetColorId !== 'general') {
                url += `&color_id=${targetColorId}`;
            } else if (targetColorId === 'general') {
                url += '&color_id=0';
            }
            const genderFilter = document.getElementById('sizeGenderFilter');
            if (genderFilter && genderFilter.value) {
                url += `&gender=${encodeURIComponent(genderFilter.value)}`;
            }
            const data = await ApiClient.get(url);
            if (data.success) {
                this.renderSizes(data.sizes);
            } else {
                console.error('Error loading sizes:', data.message);
                this.renderSizes([]);
            }
        } catch (e) {
            console.error('Error fetching sizes:', e);
            this.renderSizes([]);
        }
    }

    renderSizes(sizes) {
        const sizesList = document.getElementById('sizesList');
        const sizesLoading = document.getElementById('sizesLoading');
        if (sizesLoading) sizesLoading.classList.add('hidden');
        if (!sizesList) return;
        if (!Array.isArray(sizes) || sizes.length === 0) {
            sizesList.innerHTML = '<div class="text-center text-gray-500 text-sm">No sizes defined. Click "Add Size" to get started.</div>';
            return;
        }
        const grouped = {};
        sizes.forEach(size => {
            const key = size.color_id ? `color_${size.color_id}` : 'general';
            if (!grouped[key]) grouped[key] = { color_name: size.color_name || 'General Sizes', color_code: size.color_code || null, sizes: [] };
            grouped[key].sizes.push(size);
        });
        const totalSizeStock = sizes.reduce((sum, s) => sum + (parseInt(s.stock_level, 10) || 0), 0);
        const stockField = document.getElementById('stockLevel');
        const currentItemStock = stockField ? (parseInt(stockField.value, 10) || 0) : 0;
        const isInSync = totalSizeStock === currentItemStock;
        let html = '';
        if (sizes.length > 0) {
            const syncClass = isInSync ? 'bg-green-50 border-green-200 text-green-800' : 'bg-yellow-50 border-yellow-200 text-yellow-800';
            const syncIcon = isInSync ? '✅' : '⚠️';
            const syncMessage = isInSync ? `Stock synchronized (${totalSizeStock} total)` : `Stock out of sync! Sizes total: ${totalSizeStock}, Item stock: ${currentItemStock}`;
            html += `
                <div class="border rounded-lg ${syncClass}">
                    <div class="text-sm font-medium">${syncIcon} ${syncMessage}</div>
                    ${!isInSync ? '<div class="text-xs flex items-center gap-2"><button type="button" data-action="sync-size-stock" class="bg-blue-500 text-white rounded text-xs px-2 py-1 hover:bg-blue-600">Sync Stock</button><span>Click to synchronize item stock with sizes total.</span></div>' : ''}
                </div>
            `;
        }
        Object.keys(grouped).forEach(groupKey => {
            const group = grouped[groupKey];
            if (Object.keys(grouped).length > 1) {
                html += `
                    <div class="font-medium text-gray-700 flex items-center">
                        ${group.color_code ? `<div class=\"w-4 h-4 rounded border color-dot\" data-color=\"${group.color_code}\"></div>` : ''}
                        ${group.color_name}
                    </div>
                `;
            }
            group.sizes.forEach(size => {
                const isActive = size.is_active == 1;
                const activeClass = isActive ? 'bg-white' : 'bg-gray-100 opacity-75';
                const activeText = isActive ? '' : ' (Inactive)';
                const priceAdjustmentText = size.price_adjustment > 0 ? ` (+$${size.price_adjustment})` : '';
                const genderBadge = size.gender ? `<span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-700 border border-gray-200">${size.gender}</span>` : '';
                html += `
                    <div class="size-item flex items-center justify-between border border-gray-200 rounded-lg ${activeClass} ml-${Object.keys(grouped).length > 1 ? '4' : '0'}">
                        <div class="flex items-center space-x-3">
                            <div class="size-badge bg-blue-100 text-blue-800 rounded text-sm font-medium">${size.size_code}</div>
                            <div>
                                <div class="font-medium text-gray-800">${size.size_name}${activeText}${priceAdjustmentText}${genderBadge}</div>
                                <div class="text-sm text-gray-500 flex items-center">
                                    <span class="inline-stock-editor"
                                          data-type="size"
                                          data-id="${size.id}"
                                          data-field="stock_level"
                                          data-value="${size.stock_level}"
                                          data-action="edit-inline-stock"
                                          title="Click to edit stock level">${size.stock_level}</span>
                                    <span class="">in stock</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button type="button" data-action="delete-size" data-id="${size.id}" class="bg-red-500 text-white rounded text-xs hover:bg-red-600">Delete</button>
                        </div>
                    </div>
                `;
            });
        });
        sizesList.innerHTML = html;
        // Apply dynamic color classes to color dots (no inline styles)
        sizesList.querySelectorAll('.color-dot[data-color]').forEach(el => {
            const raw = el.getAttribute('data-color');
            if (!raw) return;
            const hex = (raw || '').trim().toLowerCase();
            const norm = hex.startsWith('#') ? hex : `#${hex}`;
            const six = norm.length === 4
                ? `#${norm[1]}${norm[1]}${norm[2]}${norm[2]}${norm[3]}${norm[3]}`
                : norm;
            const key = six.replace('#','');
            const cls = `color-var-${key}`;
            const set = (window.__wfInventoryColorClasses ||= new Set());
            let styleEl = document.getElementById('inventory-color-classes');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'inventory-color-classes';
                document.head.appendChild(styleEl);
            }
            if (!set.has(cls)) {
                styleEl.textContent += `\n.${cls}{--swatch-color:${six};}`;
                set.add(cls);
            }
            el.classList.add(cls);
        });
    }

    async loadColorOptions() {
        if (!this.currentItemSku) return;
        try {
            const data = await ApiClient.get(`/api/item_colors.php?action=get_all_colors&item_sku=${this.currentItemSku}`);
            const colorFilter = document.getElementById('sizeColorFilter');
            if (!colorFilter) return;
            colorFilter.innerHTML = '<option value="general">General Sizes (No Color)</option>';
            if (data.success && Array.isArray(data.colors)) {
                data.colors.forEach(color => {
                    if (color.is_active == 1) {
                        const option = document.createElement('option');
                        option.value = color.id;
                        option.textContent = `${color.color_name} (${color.stock_level} in stock)`;
                        colorFilter.appendChild(option);
                    }
                });
            }
        } catch (e) {
            console.error('Error loading colors for size filter:', e);
        }
    }

    async populateSizeColorSelect() {
        // Populate the size modal color association select (#sizeColorId)
        try {
            const data = await ApiClient.get(`/api/item_colors.php?action=get_all_colors&item_sku=${this.currentItemSku}`);
            const select = document.getElementById('sizeColorId');
            if (!select) return;
            select.innerHTML = '<option value="">General Size (No specific color)</option>';
            if (data.success && Array.isArray(data.colors)) {
                data.colors.forEach(color => {
                    if (color.is_active == 1) {
                        const option = document.createElement('option');
                        option.value = color.id;
                        option.textContent = color.color_name;
                        select.appendChild(option);
                    }
                });
            }
        } catch (e) {
            console.error('Error populating size color select:', e);
        }
    }

    updateSizeConfiguration() {
        const selectedRadio = document.querySelector('input[name="sizeConfiguration"]:checked');
        if (!selectedRadio) return;
        const selectedConfig = selectedRadio.value;
        const sizeTypeSelector = document.getElementById('sizeTypeSelector');
        const sizesSection = document.getElementById('sizesList');
        if (selectedConfig === 'none') {
            if (sizeTypeSelector) sizeTypeSelector.classList.add('hidden');
            if (sizesSection) sizesSection.innerHTML = '<div class="text-center text-gray-500 text-sm">No sizes configured for this item</div>';
        } else if (selectedConfig === 'general') {
            if (sizeTypeSelector) sizeTypeSelector.classList.add('hidden');
            this.loadItemSizes('general');
        } else if (selectedConfig === 'color_specific') {
            if (sizeTypeSelector) sizeTypeSelector.classList.remove('hidden');
            this.loadColorOptions();
            this.loadItemSizes();
        }
    }

    showSizeModal(size = null) {
        if (!document.getElementById('sizeModal')) {
            this.createSizeModal();
        }
        const modal = document.getElementById('sizeModal');
        const form = document.getElementById('sizeForm');
        const modalTitle = document.getElementById('sizeModalTitle');
        if (!modal || !form) return;
        form.reset();
        // Ensure color options are current
        this.populateSizeColorSelect();
        if (size) {
            modalTitle.textContent = 'Edit Size';
            document.getElementById('sizeId').value = size.id;
            document.getElementById('sizeName').value = size.size_name || '';
            document.getElementById('sizeCode').value = size.size_code || '';
            document.getElementById('sizeStockLevel').value = size.stock_level || 0;
            document.getElementById('sizePriceAdjustment').value = size.price_adjustment || 0;
            document.getElementById('sizeDisplayOrder').value = size.display_order || 0;
            document.getElementById('sizeIsActive').checked = size.is_active == 1;
            if (size.color_id) {
                const colorSelect = document.getElementById('sizeColorId');
                if (colorSelect) colorSelect.value = size.color_id;
            }
        } else {
            modalTitle.textContent = 'Add New Size';
            document.getElementById('sizeId').value = '';
            document.getElementById('sizePriceAdjustment').value = '0.00';
            document.getElementById('sizeDisplayOrder').value = '0';
            document.getElementById('sizeIsActive').checked = true;
            const colorFilter = document.getElementById('sizeColorFilter');
            const colorSelect = document.getElementById('sizeColorId');
            if (colorFilter && colorSelect) colorSelect.value = colorFilter.value === 'general' ? '' : colorFilter.value;
        }
        modal.classList.remove('hidden');
    }

    createSizeModal() {
        const modalHTML = `
        <div id="sizeModal" class="modal-overlay hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="sizeModalTitle" class="text-xl font-semibold text-gray-800">Add New Size</h2>
                    <button type="button" data-action="close-size-modal" class="admin-modal-close wf-admin-nav-button" aria-label="Close">×</button>
                </div>
                <div class="modal-body">
                    <form id="sizeForm">
                        <input type="hidden" id="sizeId" name="sizeId">
                        <div class="">
                            <label for="sizeColorId" class="block text-sm font-medium text-gray-700">Color Association</label>
                            <select id="sizeColorId" name="sizeColorId" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2">
                                <option value="">General Size (No specific color)</option>
                            </select>
                            <div class="text-xs text-gray-500">Choose a color if this size is specific to a particular color variant</div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sizeName" class="block text-sm font-medium text-gray-700">Size Name *</label>
                                <input type="text" id="sizeName" name="sizeName" placeholder="e.g., Medium" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2" required>
                            </div>
                            <div>
                                <label for="sizeCode" class="block text-sm font-medium text-gray-700">Size Code *</label>
                                <select id="sizeCode" name="sizeCode" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2" required>
                                    <option value="">Select size...</option>
                                    <option value="XS">XS</option>
                                    <option value="S">S</option>
                                    <option value="M">M</option>
                                    <option value="L">L</option>
                                    <option value="XL">XL</option>
                                    <option value="XXL">XXL</option>
                                    <option value="XXXL">XXXL</option>
                                    <option value="OS">OS (One Size)</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sizeStockLevel" class="block text-sm font-medium text-gray-700">Stock Level</label>
                                <input type="number" id="sizeStockLevel" name="sizeStockLevel" min="0" value="0" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2">
                            </div>
                            <div>
                                <label for="sizePriceAdjustment" class="block text-sm font-medium text-gray-700">Price Adjustment ($)</label>
                                <input type="number" id="sizePriceAdjustment" name="sizePriceAdjustment" step="0.01" value="0.00" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2">
                                <div class="text-xs text-gray-500">Extra charge for this size (e.g., +$2 for XXL)</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sizeDisplayOrder" class="block text-sm font-medium text-gray-700">Display Order</label>
                                <input type="number" id="sizeDisplayOrder" name="sizeDisplayOrder" min="0" value="0" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2">
                                <div class="text-xs text-gray-500">Lower numbers appear first</div>
                            </div>
                            <div class="flex items-center">
                                <label class="flex items-center">
                                    <input type="checkbox" id="sizeIsActive" name="sizeIsActive" class="">
                                    <span class="text-sm font-medium text-gray-700">Active (available to customers)</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3 border-t border-gray-200">
                            <button type="button" data-action="close-size-modal" class="bg-gray-300 text-gray-800 rounded hover:bg-gray-400 transition-colors">Cancel</button>
                            <button type="submit" class="text-white rounded transition-colors bg-[#87ac3a] hover:bg-[#BF5700]">Save Size</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.populateSizeColorSelect();
    }

    loadData() {
        const dataElement = document.getElementById('inventory-data');
        if (dataElement) {
            try {
                const data = JSON.parse(dataElement.textContent);
                this.currentItemSku = data.currentItemSku;
                this.items = data.items || [];
                this.categories = data.categories || [];
                if (this.itemsBySku && this.itemsBySku.clear) {
                    this.itemsBySku.clear();
                    this.items.forEach((item) => {
                        if (item && item.sku) {
                            this.itemsBySku.set(item.sku, item);
                        }
                    });
                }
                if (data.costBreakdown) {
                    this.costBreakdown = {
                        materials: data.costBreakdown.materials || {},
                        labor: data.costBreakdown.labor || {},
                        energy: data.costBreakdown.energy || {},
                        equipment: data.costBreakdown.equipment || {},
                        totals: data.costBreakdown.totals || {}
                    };
                }
            } catch (e) {
                console.error('[AdminInventory] Failed to parse inventory data:', e);
            }
        } else {
            console.warn('[AdminInventory] No inventory-data element found');
        }
    }

    renderThumbnail(container, image, totalCount) {
        container.innerHTML = `
            <div class="wf-thumb-wrap relative w-12 h-12 rounded overflow-hidden bg-gray-100 border">
                <img src="/${image.image_path}" 
                     alt="${image.alt_text || 'Item image'}" 
                     class="w-full h-full object-cover"
                     loading="lazy"
                     decoding="async">
                ${totalCount > 1 ? `<div class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">${totalCount}</div>` : ''}
            </div>
        `;
        // Attach safe error fallback for early method variant as well
        try {
            const imgEl = container.querySelector('img');
            const wrapEl = container.querySelector('.wf-thumb-wrap');
            if (imgEl) {
                imgEl.addEventListener('error', () => {
                    try {
                        if (!container.isConnected) return;
                        const html = '<div class="w-full h-full rounded bg-gray-100 border flex items-center justify-center text-gray-400"><span class="text-lg">📷</span></div>';
                        if (wrapEl) {
                            wrapEl.innerHTML = html;
                        } else {
                            container.innerHTML = html;
                        }
                    } catch (_) {}
                }, { once: true });
            }
        } catch (_) {}
        this.loadExistingMarketingSuggestion(this.currentItemSku);
        this.updateCategoryDropdown();
        // Expose selected handler for legacy calls in PHP markup
        window.handleGlobalColorSelection = this.handleGlobalColorSelection.bind(this);
        // Expose loaders for compatibility with migrated inline calls
        window.loadItemColors = this.loadItemColors.bind(this);
        window.loadItemSizes = this.loadItemSizes.bind(this);
        // Expose suggestion helpers for compatibility with legacy inline calls
        window.loadExistingPriceSuggestion = this.loadExistingPriceSuggestion.bind(this);
        window.loadExistingViewPriceSuggestion = this.loadExistingViewPriceSuggestion.bind(this);
        window.loadExistingMarketingSuggestion = this.loadExistingMarketingSuggestion.bind(this);
        window.displayMarketingSuggestionIndicator = this.displayMarketingSuggestionIndicator.bind(this);

        // Initial loads if sections are present
        if (document.getElementById('colorsList')) {
            this.loadItemColors();
        }
        if (document.getElementById('sizesList')) {
            this.loadItemSizes();
        }

        // Ensure SKU is set and initial images load on page load
        const skuField = document.getElementById('skuEdit') || document.getElementById('sku') || document.getElementById('skuDisplay');
        if (skuField && skuField.value) {
            this.currentItemSku = skuField.value;
        }
        if (document.getElementById('currentImagesList') && this.currentItemSku) {
            this.loadCurrentImages(this.currentItemSku, false);
        }
    }

    bindEvents() {
        document.body.addEventListener('click', this.handleDelegatedClick.bind(this));
        document.body.addEventListener('mouseover', this.handleDelegatedMouseOver.bind(this));
        document.body.addEventListener('mouseout', this.handleDelegatedMouseOut.bind(this));
        document.body.addEventListener('keydown', this.handleDelegatedKeydown.bind(this));
        document.body.addEventListener('submit', this.handleDelegatedSubmit.bind(this), true);
        document.body.addEventListener('change', this.handleDelegatedChange.bind(this));
        
        // Load thumbnails for inventory table
        this.loadInventoryThumbnails();
        
        // Enable inline editing on inventory cells using the unified class
        this.setupInlineEditing();

        const modal = document.getElementById('inventoryModalOuter');
        if (modal) {
            document.addEventListener('keydown', this.handleKeyDown.bind(this));
            modal.addEventListener('click', (event) => {
                if (event.target.id === 'inventoryModalOuter') {
                    this.closeAdminEditor();
                }
            });
        }

        const imageUploadInput = document.getElementById('imageUpload');
        if (imageUploadInput) {
            imageUploadInput.addEventListener('change', this.handleImageUpload.bind(this));
        }
        // Also support additional file inputs if present
        const multiImageUpload = document.getElementById('multiImageUpload');
        if (multiImageUpload) {
            multiImageUpload.addEventListener('change', this.handleImageUpload.bind(this));
        }
        const aiAnalysisUpload = document.getElementById('aiAnalysisUpload');
        if (aiAnalysisUpload) {
            aiAnalysisUpload.addEventListener('change', this.handleImageUpload.bind(this));
        }

        // Listen for category updates from other tabs/windows
        window.addEventListener('storage', (e) => {
            if (e.key === 'categoriesUpdated') {
                // Refresh categories and notify
                if (typeof this.refreshCategoryDropdown === 'function') {
                    this.refreshCategoryDropdown();
                }
                this.showToast('Categories updated! Dropdown refreshed.', 'info');
            }
        });
    }

    handleKeyDown(event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
            return;
        }

        if (event.key === 'ArrowRight') {
            this.navigateToItem('next');
        } else if (event.key === 'ArrowLeft') {
            this.navigateToItem('prev');
        }
    }

    handleDelegatedClick(event) {
        const target = event.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;
        const id = target.dataset.id;
        const category = target.dataset.category;

        if (target.tagName === 'BUTTON') {
            event.preventDefault();
        }
        try {
            // Debug trace for delegated actions
            console.debug('[AdminInventory] click:', { action, target });
        } catch (_) {}

        switch (action) {
            case 'delete-item':
                {
                    const sku = target.dataset.sku;
                    if (!sku) {
                        this.showError('Missing SKU to delete');
                        return;
                    }
                    this.itemToDeleteSku = sku;
                    const modal = document.getElementById('deleteConfirmModal');
                    if (modal) {
                        if (typeof window.showModal === 'function') window.showModal('deleteConfirmModal');
                        else modal.classList.add('show');
                    }
                }
                break;
            case 'close-delete-modal':
                {
                    const modal = document.getElementById('deleteConfirmModal');
                    if (modal) {
                        if (typeof window.hideModal === 'function') window.hideModal('deleteConfirmModal');
                        else modal.classList.remove('show');
                    }
                    this.itemToDeleteSku = null;
                }
                break;
            case 'apply-selected-comparison':
                this.applySelectedComparisonChanges();
                break;
            case 'close-ai-comparison':
                this.closeAiComparisonModal();
                break;
            case 'confirm-delete-item':
                this.handleConfirmDeleteItem();
                break;
            case 'navigate-item':
                this.navigateToItem(target.dataset.direction);
                break;
            case 'add-item-color':
                this.showColorModal();
                break;
            case 'add-item-size':
                this.showSizeModal();
                break;
            case 'set-primary-image':
            case 'set-primary':
                this.setPrimaryImage(id || target.dataset.imageId);
                break;
            case 'delete-image':
                this.confirmDeleteImage(id);
                break;
            case 'trigger-upload':
                {
                    // This is now handled by the native <label for>, but we keep a fallback.
                    event.preventDefault();
                    const targetId = target.dataset.target || 'imageUpload';
                    const input = document.getElementById(targetId);
                    if (input) {
                        input.click();
                    }
                }
                break;
            case 'process-images-ai':
                try { target.setAttribute('aria-busy', 'true'); } catch (_) {}
                this.processExistingImagesWithAI().finally(() => { try { target.removeAttribute('aria-busy'); } catch (_) {} });
                break;
            case 'open-cost-modal':
                this.openCostModal(category, id);
                break;
            case 'close-cost-modal':
                this.closeCostModal();
                break;
            case 'save-cost-item':
                this.saveCostItem();
                break;
            case 'delete-cost-item':
                this.confirmDeleteCostItem(category, id);
                break;
            case 'clear-cost-breakdown':
                this.confirmClearCostBreakdown();
                break;
            case 'get-cost-suggestion':
                try { target.setAttribute('aria-busy', 'true'); } catch (_) {}
                this.getCostSuggestion().finally(() => { try { target.removeAttribute('aria-busy'); } catch (_) {} });
                break;
            case 'get-price-suggestion':
                try { target.setAttribute('aria-busy', 'true'); } catch (_) {}
                this.getPriceSuggestion().finally(() => { try { target.removeAttribute('aria-busy'); } catch (_) {} });
                break;
            case 'apply-price-suggestion':
                this.applyPriceSuggestion();
                break;
            case 'clear-price-suggestion':
                this.clearPriceSuggestion();
                break;
            case 'open-marketing-manager':
                try { target.setAttribute('aria-busy', 'true'); } catch (_) {}
                try { this.openMarketingManager(); } finally { try { target.removeAttribute('aria-busy'); } catch (_) {} }
                break;
            case 'generate-marketing-copy':
                try { target.setAttribute('aria-busy', 'true'); } catch (_) {}
                this.generateMarketingCopy().finally(() => { try { target.removeAttribute('aria-busy'); } catch (_) {} });
                break;
            case 'close-marketing-manager':
                this.closeMarketingManager();
                break;
            case 'close-marketing-modal':
                this.closeMarketingModal();
                break;
            case 'close-ai-comparison-modal':
                this.closeAiComparisonModal();
                break;
            case 'apply-selected-changes':
                this.applySelectedComparisonChanges();
                break;
            case 'apply-marketing-to-item':
                this.applyMarketingToItem();
                break;
            case 'generate-all-marketing':
                this.handleGenerateAllMarketing(target);
                break;
            case 'generate-fresh-marketing':
                this.handleGenerateFreshMarketing(target);
                break;
            case 'apply-and-save-marketing-title':
                this.handleApplyAndSaveMarketingTitle();
                break;
            case 'apply-and-save-marketing-description':
                this.handleApplyAndSaveMarketingDescription();
                break;
            case 'apply-title':
                this.applyTitle(target.dataset.value || '');
                break;
            case 'apply-description':
                this.applyDescription(target.dataset.value || '');
                break;
            case 'show-marketing-tab':
                this.switchMarketingTab(target.dataset.tab, target);
                break;
            case 'apply-cost-suggestion-to-cost':
                 this.applyCostSuggestionToCost();
                 break;
            case 'apply-suggested-cost-to-cost-field':
                this.applySuggestedCostToCostField(target);
                break;
            case 'save-marketing-field':
                this.handleSaveMarketingField(target.dataset.field, target);
                break;
            case 'add-list-item':
                this.handleAddListItem(target.dataset.field);
                break;
            case 'remove-list-item':
                this.handleRemoveListItem(target.dataset.field, target.dataset.item, target);
                break;
            case 'populate-cost-breakdown-from-suggestion':
                this.populateCostBreakdownFromSuggestion(JSON.parse(target.getAttribute('data-suggestion').replace(/&quot;/g, '"').replace(/&#39;/g, "'")));
                this.closeCostSuggestionChoiceDialog();
                break;
            case 'close-cost-suggestion-choice-dialog':
                this.closeCostSuggestionChoiceDialog();
                break;
            case 'refresh-categories':
                this.refreshCategoryDropdown();
                break;
            case 'move-carousel':
                this.moveCarousel(target.dataset.type, parseInt(target.dataset.direction, 10) || 0);
                break;
            case 'show-pricing-tooltip-with-data':
                event.stopPropagation();
                this.showPricingTooltipWithData(event, target.dataset.componentType, decodeURIComponent(target.dataset.explanation || ''));
                break;
            case 'show-pricing-tooltip-text':
                event.stopPropagation();
                this.showPricingTooltip(event, decodeURIComponent(target.dataset.text || ''));
                break;
            case 'edit-inline-stock':
                this.editInlineStock(target);
                break;
            case 'delete-color':
                this.deleteColor(parseInt(target.dataset.id, 10));
                break;
            case 'close-color-modal':
                this.closeColorModal();
                break;
            case 'open-global-colors-management':
                this.openGlobalColorsManagement();
                break;
            case 'delete-size':
                this.deleteSize(parseInt(target.dataset.id, 10));
                break;
            case 'close-size-modal':
                this.closeSizeModal();
                break;
            case 'sync-size-stock':
                this.syncSizeStock();
                break;
            case 'ensure-color-sizes':
                this.ensureColorSizes();
                break;
            case 'distribute-general-stock-evenly':
                this.distributeGeneralStockEvenly();
                break;
            case 'open-color-template-modal':
                this.openColorTemplateModal();
                break;
            case 'close-color-template-modal':
                this.closeColorTemplateModal();
                break;
            case 'apply-color-template':
                this.applySelectedColorTemplate();
                break;
            case 'select-color-template':
                this.selectColorTemplate(parseInt(target.dataset.id, 10));
                break;
            case 'open-size-template-modal':
                this.openSizeTemplateModal();
                break;
            case 'close-size-template-modal':
                this.closeSizeTemplateModal();
                break;
            case 'apply-size-template':
                this.applySelectedSizeTemplate();
                break;
            case 'select-size-template':
                this.selectSizeTemplate(parseInt(target.dataset.id, 10));
                break;
            case 'close-restructure-modal':
                if (typeof window.closeRestructureModal === 'function') window.closeRestructureModal();
                break;
            case 'close-structure-view-modal':
                if (typeof window.closeStructureViewModal === 'function') window.closeStructureViewModal();
                break;
            case 'close-admin-editor':
                try { event.preventDefault(); } catch (_) {}
                this.closeAdminEditor();
                break;
            case 'save-inventory':
                try { event.preventDefault(); } catch (_) {}
                {
                    const formEl = (target && target.closest) ? target.closest('form') : null;
                    const form = formEl || document.getElementById('inventoryForm');
                    if (form instanceof HTMLFormElement) {
                        this.saveInventoryForm(form);
                    } else {
                        console.warn('[AdminInventory] save-inventory clicked but form element not found');
                    }
                }
                break;
        }
    }

    handleDelegatedSubmit(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.id === 'colorForm') {
            event.preventDefault();
            this.saveColor(form);
        } else if (form.id === 'sizeForm') {
            event.preventDefault();
            this.saveSize(form);
        } else if (form.id === 'inventoryForm') {
            // Preserve legacy validation behavior if function exists
            if (typeof window.validateGenderSizeColorRequirements === 'function') {
                const result = window.validateGenderSizeColorRequirements(event);
                if (result === false) {
                    event.preventDefault();
                    return;
                }
            }
            // Always handle via JS to align with API JSON endpoints
            event.preventDefault();
            this.saveInventoryForm(form);
        }
    }

    collectInventoryPayload(_form) {
        // Normalize values for API: update_inventory.php/add_inventory.php
        const getVal = (id) => {
            const el = document.getElementById(id);
            return el ? (el.value || '').trim() : '';
        };
        const num = (v, isFloat = false) => {
            if (v === '' || v == null) return isFloat ? 0 : 0;
            const n = isFloat ? parseFloat(v) : parseInt(v, 10);
            return Number.isFinite(n) ? n : (isFloat ? 0 : 0);
        };
        const payload = {
            sku: getVal('skuEdit') || getVal('sku') || getVal('skuDisplay') || '',
            name: getVal('name'),
            category: getVal('categoryEdit'),
            stockLevel: num(getVal('stockLevel')),
            reorderPoint: num(getVal('reorderPoint')),
            costPrice: num(getVal('costPrice'), true),
            retailPrice: num(getVal('retailPrice'), true),
            description: getVal('description')
        };
        return payload;
    }

    async saveInventoryForm(form) {
        try {
            const payload = this.collectInventoryPayload(form);
            if (!payload.sku || !payload.name) {
                this.showError('SKU and Name are required.');
                return;
            }
            const actionInput = form.querySelector('input[name="action"]');
            const mode = (actionInput ? (actionInput.value || '') : '').toLowerCase(); // 'add' or 'update'
            const isAdd = mode === 'add' || (!mode && !document.getElementById('sku') && !document.getElementById('skuDisplay'));
            const url = isAdd ? '/api/add_inventory.php' : '/api/update_inventory.php';

            const btn = form.querySelector('[data-action="save-inventory"]') || form.querySelector('button[type="submit"]');
            const original = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = isAdd ? 'Creating…' : 'Saving…'; btn.setAttribute('aria-busy', 'true'); }

            const data = await ApiClient.post(url, payload).catch(() => ({}));
            if (!data || data.success !== true) {
                const msg = data && (data.error || data.message) ? (data.error || data.message) : 'Request failed';
                this.showError(`Failed to ${isAdd ? 'create' : 'update'} item: ${msg}`);
                if (btn) { btn.disabled = false; btn.textContent = original; btn.removeAttribute('aria-busy'); }
                return;
            }

            // Success UX (prefer backend message when provided)
            const okMessage = (data && data.message) ? data.message : (isAdd ? 'Updated successfully' : 'Updated successfully');
            this.showSuccess(okMessage);

            // Navigate to canonical editor URL for the SKU to refresh server-rendered overlay state
            const sku = payload.sku || data.id;
            if (sku) {
                window.location.assign(buildAdminUrl('inventory', { edit: sku }));
                return;
            }

            // Fallback: re-enable button if not navigating
            if (btn) { btn.disabled = false; btn.textContent = original; btn.removeAttribute('aria-busy'); }
        } catch (err) {
            console.error('Save inventory error:', err);
            this.showError('Failed to save item due to a network or server error.');
            const form = document.getElementById('inventoryForm');
            const btn = form && (form.querySelector('[data-action="save-inventory"]') || form.querySelector('button[type="submit"]'));
            if (btn) { btn.disabled = false; btn.textContent = 'Save Changes'; btn.removeAttribute('aria-busy'); }
        }
    }

    handleDelegatedChange(event) {
        const t = event.target;
        if (!(t instanceof HTMLElement)) return;
        if (t.id === 'globalColorSelect') {
            this.handleGlobalColorSelection();
        } else if (t.getAttribute && t.getAttribute('name') === 'sizeConfiguration') {
            // Respond to size configuration radio changes
            this.updateSizeConfiguration();
        } else if (t.id === 'sizeColorFilter') {
            this.loadItemSizes();
        } else if (t.id === 'sizeGenderFilter') {
            this.loadItemSizes();
        } else if (t.id === 'colorTemplateCategory') {
            this.filterColorTemplates();
        } else if (t.id === 'sizeTemplateCategory') {
            this.filterSizeTemplates();
        } else if (t.getAttribute && t.getAttribute('name') === 'sizeApplyMode') {
            const colorSelection = document.getElementById('colorSelectionForSizes');
            if (colorSelection) {
                if (t.value === 'color_specific') {
                    colorSelection.classList.remove('hidden');
                    this.loadColorsForSizeTemplate();
                } else {
                    colorSelection.classList.add('hidden');
                }
            }
        } else if (t.matches('select[data-action="marketing-default-change"]')) {
            const setting = t.getAttribute('data-setting');
            if (setting) {
                this.updateGlobalMarketingDefault(setting, t.value);
            }
        } else if (t.id === 'selectAllComparison') {
            this.toggleSelectAllComparison();
        } else if (t.matches('[data-action="toggle-select-all-comparison"]')) {
            this.toggleSelectAllComparison();
        } else if (t.matches('[data-action="toggle-comparison"]')) {
            const fieldKey = t.getAttribute('data-field');
            if (fieldKey) {
                this.toggleComparison(fieldKey);
            }
        }
    }

    handleGlobalColorSelection() {
        const globalColorSelect = document.getElementById('globalColorSelect');
        if (!globalColorSelect) return;
        const selectedValue = globalColorSelect.value;

        const colorNameInput = document.getElementById('colorName');
        const colorCodeInput = document.getElementById('colorCode');
        const selectedColorPreview = document.getElementById('selectedColorPreview');
        const colorPreviewSwatch = document.getElementById('colorPreviewSwatch');
        const colorPreviewName = document.getElementById('colorPreviewName');
        const colorPreviewCode = document.getElementById('colorPreviewCode');

        if (selectedValue) {
            try {
                const colorData = JSON.parse(selectedValue);
                colorNameInput.value = colorData.name;
                colorCodeInput.value = colorData.code || '#000000';
                if (selectedColorPreview) selectedColorPreview.classList.remove('hidden');
                if (colorPreviewSwatch) {
                    const raw = (colorData.code || '#000000').toLowerCase().trim();
                    const norm = raw.startsWith('#') ? raw : `#${raw}`;
                    const six = norm.length === 4
                        ? `#${norm[1]}${norm[1]}${norm[2]}${norm[2]}${norm[3]}${norm[3]}`
                        : norm;
                    const key = six.replace('#','');
                    const cls = `color-var-${key}`;
                    // Remove any previous color-var-* classes
                    colorPreviewSwatch.className = colorPreviewSwatch.className
                        .split(' ')
                        .filter(c => !/^color-var-[0-9a-fA-F]{6}$/.test(c))
                        .join(' ');
                    // Ensure stylesheet exists and class is registered
                    const set = (window.__wfInventoryColorClasses ||= new Set());
                    let styleEl = document.getElementById('inventory-color-classes');
                    if (!styleEl) { styleEl = document.createElement('style'); styleEl.id = 'inventory-color-classes'; document.head.appendChild(styleEl); }
                    if (!set.has(cls)) { styleEl.textContent += `\n.${cls}{--swatch-color:${six};}`; set.add(cls); }
                    colorPreviewSwatch.classList.add(cls);
                }
                if (colorPreviewName) colorPreviewName.textContent = colorData.name;
                if (colorPreviewCode) colorPreviewCode.textContent = colorData.code || 'No color code';
            } catch (e) {
                console.error('Error parsing color data:', e);
            }
        } else {
            if (colorNameInput) colorNameInput.value = '';
            if (colorCodeInput) colorCodeInput.value = '';
            if (selectedColorPreview) selectedColorPreview.classList.add('hidden');
        }
    }

    openGlobalColorsManagement() {
        this.showConfirmationModal(
            'Manage Global Colors',
            'Global colors are managed in Admin Settings > Content Management > Global Colors & Sizes. Open Admin Settings now?',
            () => { window.location.href = '/?page=admin&section=settings'; }
        );
    }

    async syncSizeStock() {
        if (!this.currentItemSku) {
            this.showError('No item selected');
            return;
        }
        try {
            const data = await ApiClient.post('/api/item_sizes.php?action=sync_stock', { item_sku: this.currentItemSku });
            if (data.success) {
                const msg = (data && data.message) ? data.message : (typeof data.new_total_stock !== 'undefined' ? `Updated successfully (Total: ${data.new_total_stock})` : 'Updated successfully');
                this.showSuccess(msg);
                const stockField = document.getElementById('stockLevel');
                if (stockField && data.new_total_stock !== undefined) {
                    stockField.value = data.new_total_stock;
                }
                // Refresh both colors and sizes lists, and the nested editor, so color totals reflect size sums
                try { this.loadItemColors(); } catch (_) {}
                try { this.loadItemSizes(); } catch (_) {}
                try { this.loadNestedInventoryEditor(); } catch (_) {}
            } else {
                this.showError(`Error syncing stock: ${data.message || ''}`);
            }
        } catch (error) {
            console.error('Error syncing stock:', error);
            this.showError('Error syncing stock levels');
        }
    }

    closeColorModal() {
        const modal = document.getElementById('colorModal');
        if (modal) modal.classList.add('hidden');
    }

    async saveColor(formOrEvent) {
        const form = formOrEvent instanceof HTMLFormElement ? formOrEvent : document.getElementById('colorForm');
        if (!form) return;
        const formData = new FormData(form);
        const colorData = {
            item_sku: this.currentItemSku,
            color_name: formData.get('colorName'),
            color_code: formData.get('colorCode'),
            image_path: formData.get('colorImagePath') || '',
            stock_level: parseInt(formData.get('stockLevel'), 10) || 0,
            display_order: parseInt(formData.get('displayOrder'), 10) || 0,
            is_active: formData.get('isActive') ? 1 : 0
        };
        const colorId = formData.get('colorId');
        const isEdit = !!(colorId && colorId !== '');
        if (isEdit) {
            colorData.color_id = parseInt(colorId, 10);
        }
        try {
            const data = await ApiClient.post(`/api/item_colors.php?action=${isEdit ? 'update_color' : 'add_color'}`, colorData);
            if (data.success) {
                this.showSuccess(`Color ${isEdit ? 'updated' : 'added'} successfully${data.new_total_stock ? ` - Total stock: ${data.new_total_stock}` : ''}`);
                this.closeColorModal();
                this.loadItemColors();
                const stockField = document.getElementById('stockLevel');
                if (stockField && data.new_total_stock !== undefined) {
                    stockField.value = data.new_total_stock;
                }
            } else {
                this.showError(`Error ${isEdit ? 'updating' : 'adding'} color: ${data.message}`);
            }
        } catch (error) {
            console.error('Error saving color:', error);
            this.showError(`Error ${isEdit ? 'updating' : 'adding'} color`);
        }
    }

    // ===== Color Template Management =====
    async openColorTemplateModal() {
        if (!this.currentItemSku) {
            this.showError('Please save the item first before applying templates');
            return;
        }
        if (!document.getElementById('colorTemplateModal')) {
            this.createColorTemplateModal();
        }
        await this.loadColorTemplates();
        const modal = document.getElementById('colorTemplateModal');
        if (modal) modal.classList.remove('hidden');
    }

    createColorTemplateModal() {
        const modalHTML = `
        <div id="colorTemplateModal" class="modal-overlay hidden">
            <div class="modal-content" >
                <div class="modal-header">
                    <h2 class="text-xl font-bold text-gray-800">🎨 Color Templates</h2>
                    <button type="button" data-action="close-color-template-modal" class="admin-modal-close wf-admin-nav-button" aria-label="Close">×</button>
                </div>
                <div class="modal-body">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 space-y-4">
                            <div class="">
                                <label class="block text-sm font-medium text-gray-700">Filter by Category:</label>
                                <select id="colorTemplateCategory" class="w-full border border-gray-300 rounded">
                                    <option value="">All Categories</option>
                                </select>
                            </div>
                            <div id="colorTemplatesList" class="space-y-3">
                                <div class="text-center text-gray-500">Loading templates...</div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <h3 class="font-medium text-blue-800">Application Options</h3>
                                <div class="space-y-3">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" id="replaceExistingColors">
                                        <span class="text-sm">Replace existing colors (clear current colors first)</span>
                                    </label>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Default Stock Level for New Colors:</label>
                                        <input type="number" id="defaultColorStock" value="0" min="0" class="w-32 border border-gray-300 rounded text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" data-action="close-color-template-modal" class="bg-gray-300 text-gray-800 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="button" data-action="apply-color-template" id="applyColorTemplateBtn" class="bg-purple-600 text-white rounded hover:bg-purple-700" disabled>
                        Apply Template
                    </button>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    async loadColorTemplates() {
        try {
            const data = await ApiClient.get('/api/color_templates.php?action=get_all');
            if (data.success) {
                this.colorTemplates = data.templates || [];
                this.renderColorTemplates();
                this.loadColorTemplateCategories();
            } else {
                this.showError('Error loading color templates: ' + (data.message || ''));
            }
        } catch (e) {
            console.error('Error loading color templates:', e);
            this.showError('Error loading color templates');
        }
    }

    loadColorTemplateCategories() {
        const categorySelect = document.getElementById('colorTemplateCategory');
        if (!categorySelect) return;
        const categories = [...new Set((this.colorTemplates || []).map(t => t.category))].sort();
        categorySelect.innerHTML = '<option value="">All Categories</option>';
        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            categorySelect.appendChild(option);
        });
    }

    filterColorTemplates() {
        this.renderColorTemplates();
    }

    renderColorTemplates() {
        const container = document.getElementById('colorTemplatesList');
        if (!container) return;
        const selectedCategory = document.getElementById('colorTemplateCategory')?.value || '';
        const templates = this.colorTemplates || [];
        const filtered = selectedCategory ? templates.filter(t => t.category === selectedCategory) : templates;
        if (filtered.length === 0) {
            container.innerHTML = '<div class="text-center text-gray-500">No templates found</div>';
            return;
        }
        container.innerHTML = filtered.map(template => `
            <div class="template-item border border-gray-200 rounded-lg hover:border-purple-300 cursor-pointer"
                 data-action="select-color-template" data-id="${template.id}" data-template-id="${template.id}">
                <div class="flex justify-between items-start p-2">
                    <div>
                        <h4 class="font-medium text-gray-800">${template.template_name}</h4>
                        <p class="text-sm text-gray-600">${template.description || 'No description'}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block bg-blue-100 text-blue-800 text-xs rounded">${template.category}</span>
                        <div class="text-xs text-gray-500">${template.color_count} colors</div>
                    </div>
                </div>
                <div class="template-preview p-2" id="colorPreview${template.id}">
                    <div class="text-xs text-gray-500">Loading colors...</div>
                </div>
            </div>
        `).join('');
        filtered.forEach(t => this.loadColorTemplatePreview(t.id));
    }

    async loadColorTemplatePreview(templateId) {
        try {
            const data = await ApiClient.get(`/api/color_templates.php?action=get_template&template_id=${templateId}`);
            if (data.success && data.template && Array.isArray(data.template.colors)) {
                const previewContainer = document.getElementById(`colorPreview${templateId}`);
                if (previewContainer) {
                    previewContainer.innerHTML = `
                        <div class="flex flex-wrap gap-2">
                            ${data.template.colors.map(color => `
                                <div class=\"flex items-center gap-1 text-xs\">
                                    <div class=\"w-4 h-4 rounded border border-gray-300 color-dot\" ${color.color_code ? `data-color=\"${color.color_code}\"` : ''}></div>
                                    <span>${color.color_name}</span>
                                </div>
                            `).join('')}
                        </div>
                    `;
                    // Apply dynamic color classes to preview dots (no inline styles)
                    previewContainer.querySelectorAll('.color-dot[data-color]').forEach(el => {
                        const raw = el.getAttribute('data-color');
                        if (!raw) return;
                        const hex = (raw || '').trim().toLowerCase();
                        const norm = hex.startsWith('#') ? hex : `#${hex}`;
                        const six = norm.length === 4
                            ? `#${norm[1]}${norm[1]}${norm[2]}${norm[2]}${norm[3]}${norm[3]}`
                            : norm;
                        const key = six.replace('#','');
                        const cls = `color-var-${key}`;
                        const set = (window.__wfInventoryColorClasses ||= new Set());
                        let styleEl = document.getElementById('inventory-color-classes');
                        if (!styleEl) {
                            styleEl = document.createElement('style');
                            styleEl.id = 'inventory-color-classes';
                            document.head.appendChild(styleEl);
                        }
                        if (!set.has(cls)) {
                            styleEl.textContent += `\n.${cls}{--swatch-color:${six};}`;
                            set.add(cls);
                        }
                        el.classList.add(cls);
                    });
                }
            }
        } catch (e) {
            console.error('Error loading color template preview:', e);
        }
    }

    selectColorTemplate(templateId) {
        document.querySelectorAll('#colorTemplatesList .template-item').forEach(item => {
            item.classList.remove('border-purple-500', 'bg-purple-50');
        });
        const item = document.querySelector(`#colorTemplatesList [data-template-id="${templateId}"]`);
        if (item) item.classList.add('border-purple-500', 'bg-purple-50');
        const applyBtn = document.getElementById('applyColorTemplateBtn');
        if (applyBtn) {
            applyBtn.disabled = false;
            applyBtn.setAttribute('data-template-id', String(templateId));
        }
    }

    async applySelectedColorTemplate() {
        const applyBtn = document.getElementById('applyColorTemplateBtn');
        const templateId = applyBtn?.getAttribute('data-template-id');
        if (!templateId) {
            this.showError('Please select a template first');
            return;
        }
        const replaceExisting = document.getElementById('replaceExistingColors')?.checked || false;
        const defaultStock = parseInt(document.getElementById('defaultColorStock')?.value, 10) || 0;
        try {
            if (applyBtn) { applyBtn.disabled = true; applyBtn.textContent = 'Applying...'; }
            const data = await ApiClient.post('/api/color_templates.php?action=apply_to_item', {
                template_id: parseInt(templateId, 10),
                item_sku: this.currentItemSku,
                replace_existing: !!replaceExisting,
                default_stock: defaultStock
            });
            if (data.success) {
                this.showSuccess(`Template applied successfully! Added ${data.colors_added} colors.`);
                this.closeColorTemplateModal();
                this.loadItemColors();
            } else {
                this.showError('Error applying template: ' + (data.message || ''));
            }
        } catch (e) {
            console.error('Error applying color template:', e);
            this.showError('Error applying color template');
        } finally {
            if (applyBtn) { applyBtn.disabled = false; applyBtn.textContent = 'Apply Template'; }
        }
    }

    closeColorTemplateModal() {
        const modal = document.getElementById('colorTemplateModal');
        if (modal) modal.classList.add('hidden');
    }

    // ===== Size Template Management =====
    async openSizeTemplateModal() {
        if (!this.currentItemSku) {
            this.showError('Please save the item first before applying templates');
            return;
        }
        if (!document.getElementById('sizeTemplateModal')) {
            this.createSizeTemplateModal();
        }
        await this.loadSizeTemplates();
        const modal = document.getElementById('sizeTemplateModal');
        if (modal) modal.classList.remove('hidden');
    }

    createSizeTemplateModal() {
        const modalHTML = `
        <div id="sizeTemplateModal" class="modal-overlay hidden">
            <div class="modal-content" >
                <div class="modal-header">
                    <h2 class="text-xl font-bold text-gray-800">📏 Size Templates</h2>
                    <button type="button" data-action="close-size-template-modal" class="admin-modal-close wf-admin-nav-button" aria-label="Close">×</button>
                </div>
                <div class="modal-body" >
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Filter by Category:</label>
                            <select id="sizeTemplateCategory" class="w-full border border-gray-300 rounded">
                                <option value="">All Categories</option>
                            </select>
                        </div>

                        <div id="sizeTemplatesList" class="space-y-3">
                            <div class="text-center text-gray-500">Loading templates...</div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <h3 class="font-medium text-blue-800">Application Options</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Apply Mode:</label>
                                    <div class="space-y-2">
                                        <label class="flex items-center gap-2">
                                            <input type="radio" name="sizeApplyMode" value="general" checked>
                                            <span class="text-sm">General sizes (not color-specific)</span>
                                        </label>
                                        <label class="flex items-center gap-2">
                                            <input type="radio" name="sizeApplyMode" value="color_specific">
                                            <span class="text-sm">Color-specific sizes</span>
                                        </label>
                                    </div>
                                </div>
                                <div id="colorSelectionForSizes" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700">Select Color:</label>
                                    <select id="sizeTemplateColorId" class="w-full border border-gray-300 rounded text-sm">
                                        <option value="">Loading colors...</option>
                                    </select>
                                </div>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="replaceExistingSizes">
                                    <span class="text-sm">Replace existing sizes</span>
                                </label>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Default Stock Level for New Sizes:</label>
                                    <input type="number" id="defaultSizeStock" value="0" min="0" class="w-32 border border-gray-300 rounded text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" data-action="close-size-template-modal" class="bg-gray-300 text-gray-800 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="button" data-action="apply-size-template" id="applySizeTemplateBtn" class="bg-purple-600 text-white rounded hover:bg-purple-700" disabled>
                        Apply Template
                    </button>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    async loadSizeTemplates() {
        try {
            const data = await ApiClient.get('/api/size_templates.php?action=get_all');
            if (data.success) {
                this.sizeTemplates = data.templates || [];
                this.renderSizeTemplates();
                this.loadSizeTemplateCategories();
            } else {
                this.showError('Error loading size templates: ' + (data.message || ''));
            }
        } catch (e) {
            console.error('Error loading size templates:', e);
            this.showError('Error loading size templates');
        }
    }

    loadSizeTemplateCategories() {
        const categorySelect = document.getElementById('sizeTemplateCategory');
        if (!categorySelect) return;
        const categories = [...new Set((this.sizeTemplates || []).map(t => t.category))].sort();
        categorySelect.innerHTML = '<option value="">All Categories</option>';
        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            categorySelect.appendChild(option);
        });
    }

    filterSizeTemplates() {
        this.renderSizeTemplates();
    }

    renderSizeTemplates() {
        const container = document.getElementById('sizeTemplatesList');
        if (!container) return;
        const selectedCategory = document.getElementById('sizeTemplateCategory')?.value || '';
        const templates = this.sizeTemplates || [];
        const filtered = selectedCategory ? templates.filter(t => t.category === selectedCategory) : templates;
        if (filtered.length === 0) {
            container.innerHTML = '<div class="text-center text-gray-500">No templates found</div>';
            return;
        }
        container.innerHTML = filtered.map(template => `
            <div class="template-item border border-gray-200 rounded-lg hover:border-purple-300 cursor-pointer"
                 data-action="select-size-template" data-id="${template.id}" data-template-id="${template.id}">
                <div class="flex justify-between items-start p-2">
                    <div>
                        <h4 class="font-medium text-gray-800">${template.template_name}</h4>
                        <p class="text-sm text-gray-600">${template.description || 'No description'}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block bg-blue-100 text-blue-800 text-xs rounded">${template.category}</span>
                        <div class="text-xs text-gray-500">${template.size_count} sizes</div>
                    </div>
                </div>
                <div class="template-preview p-2" id="sizePreview${template.id}">
                    <div class="text-xs text-gray-500">Loading sizes...</div>
                </div>
            </div>
        `).join('');
        filtered.forEach(t => this.loadSizeTemplatePreview(t.id));
    }

    async loadSizeTemplatePreview(templateId) {
        try {
            const data = await ApiClient.get(`/api/size_templates.php?action=get_template&template_id=${templateId}`);
            if (data.success && data.template && Array.isArray(data.template.sizes)) {
                const previewContainer = document.getElementById(`sizePreview${templateId}`);
                if (previewContainer) {
                    previewContainer.innerHTML = `
                        <div class="flex flex-wrap gap-2">
                            ${data.template.sizes.map(size => `
                                <span class=\"inline-block bg-gray-100 text-gray-700 text-xs rounded px-1\">${size.size_name} (${size.size_code})${size.price_adjustment > 0 ? ' +$' + size.price_adjustment : size.price_adjustment < 0 ? ' $' + size.price_adjustment : ''}</span>
                            `).join('')}
                        </div>
                    `;
                }
            }
        } catch (e) {
            console.error('Error loading size template preview:', e);
        }
    }

    selectSizeTemplate(templateId) {
        document.querySelectorAll('#sizeTemplatesList .template-item').forEach(item => {
            item.classList.remove('border-purple-500', 'bg-purple-50');
        });
        const item = document.querySelector(`#sizeTemplatesList [data-template-id="${templateId}"]`);
        if (item) item.classList.add('border-purple-500', 'bg-purple-50');
        const applyBtn = document.getElementById('applySizeTemplateBtn');
        if (applyBtn) {
            applyBtn.disabled = false;
            applyBtn.setAttribute('data-template-id', String(templateId));
        }
    }

    async loadColorsForSizeTemplate() {
        if (!this.currentItemSku) return;
        try {
            const data = await ApiClient.get(`/api/item_colors.php?action=get_all_colors&item_sku=${this.currentItemSku}`);
            const colorSelect = document.getElementById('sizeTemplateColorId');
            if (!colorSelect) return;
            colorSelect.innerHTML = '<option value="">Select a color...</option>';
            if (data.success && Array.isArray(data.colors) && data.colors.length > 0) {
                data.colors.forEach(color => {
                    if (color.is_active == 1) {
                        const option = document.createElement('option');
                        option.value = color.id;
                        option.textContent = color.color_name;
                        colorSelect.appendChild(option);
                    }
                });
            } else {
                colorSelect.innerHTML = '<option value="">No colors available - add colors first</option>';
            }
        } catch (e) {
            console.error('Error loading colors for size template:', e);
        }
    }

    async applySelectedSizeTemplate() {
        const applyBtn = document.getElementById('applySizeTemplateBtn');
        const templateId = applyBtn?.getAttribute('data-template-id');
        if (!templateId) {
            this.showError('Please select a template first');
            return;
        }
        const applyMode = document.querySelector('input[name="sizeApplyMode"]:checked')?.value || 'general';
        const replaceExisting = document.getElementById('replaceExistingSizes')?.checked || false;
        const defaultStock = parseInt(document.getElementById('defaultSizeStock')?.value, 10) || 0;
        let colorId = null;
        if (applyMode === 'color_specific') {
            colorId = document.getElementById('sizeTemplateColorId')?.value;
            if (!colorId) {
                this.showError('Please select a color for color-specific sizes');
                return;
            }
        }
        try {
            if (applyBtn) { applyBtn.disabled = true; applyBtn.textContent = 'Applying...'; }
            const data = await ApiClient.post('/api/size_templates.php?action=apply_to_item', {
                template_id: parseInt(templateId, 10),
                item_sku: this.currentItemSku,
                apply_mode: applyMode,
                color_id: colorId ? parseInt(colorId, 10) : null,
                replace_existing: !!replaceExisting,
                default_stock: defaultStock
            });
            if (data.success) {
                this.showSuccess(`Template applied successfully! Added ${data.sizes_added} sizes.`);
                this.closeSizeTemplateModal();
                this.loadItemSizes();
            } else {
                this.showError('Error applying template: ' + (data.message || ''));
            }
        } catch (e) {
            console.error('Error applying size template:', e);
            this.showError('Error applying size template');
        } finally {
            if (applyBtn) { applyBtn.disabled = false; applyBtn.textContent = 'Apply Template'; }
        }
    }

    closeSizeTemplateModal() {
        const modal = document.getElementById('sizeTemplateModal');
        if (modal) modal.classList.add('hidden');
    }

    editInlineStock(element) {
        if (!element || element.classList.contains('editing')) return;

        const currentValue = element.getAttribute('data-value');
        const type = element.getAttribute('data-type'); // 'color' or 'size'
        const id = element.getAttribute('data-id');

        const input = document.createElement('input');
        input.type = 'number';
        input.min = '0';
        input.value = currentValue;
        input.className = 'inline-stock-input';

        const originalContent = element.innerHTML;
        element.innerHTML = '';
        element.appendChild(input);
        element.classList.add('editing');
        input.focus();
        input.select();

        const restoreElement = () => {
            element.classList.remove('editing');
            element.innerHTML = originalContent;
        };

        const saveStock = async () => {
            const newValue = parseInt(input.value, 10) || 0;
            if (newValue == currentValue) { restoreElement(); return; }
            try {
                input.disabled = true;
                input.classList.add('is-busy');
                let apiUrl, updateData;
                if (type === 'color') {
                    apiUrl = '/api/item_colors.php?action=update_stock';
                    updateData = { color_id: parseInt(id, 10), stock_level: newValue };
                } else if (type === 'size') {
                    apiUrl = '/api/item_sizes.php?action=update_stock';
                    updateData = { size_id: parseInt(id, 10), stock_level: newValue };
                } else {
                    restoreElement();
                    return;
                }
                const data = await ApiClient.post(apiUrl, updateData);
                if (data.success) {
                    element.setAttribute('data-value', newValue);
                    element.classList.remove('editing');
                    element.innerHTML = String(newValue);
                    if (data.new_total_stock !== undefined) {
                        const stockField = document.getElementById('stockLevel');
                        if (stockField) stockField.value = data.new_total_stock;
                    }
                    if (type === 'color') {
                        if (typeof window.loadItemColors === 'function') window.loadItemColors();
                    } else if (type === 'size') {
                        if (typeof window.loadItemSizes === 'function') window.loadItemSizes();
                    }
                    this.showSuccess(`${type.charAt(0).toUpperCase() + type.slice(1)} stock updated to ${newValue}`);
                } else {
                    throw new Error(data.message || 'Failed to update stock');
                }
            } catch (error) {
                console.error('Error updating stock:', error);
                this.showError(`Error updating ${type} stock: ${error.message}`);
                restoreElement();
            }
        };

        input.addEventListener('blur', saveStock);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveStock();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                restoreElement();
            }
        });
        input.addEventListener('click', (e) => { e.stopPropagation(); });
    }

    deleteColor(colorId) {
        if (!colorId) return;
        this.showConfirmationModal(
            'Delete Color',
            'Are you sure you want to delete this color? This action cannot be undone.',
            async () => {
                try {
                    const data = await ApiClient.post('/api/item_colors.php?action=delete_color', { color_id: colorId });
                    if (data && data.success) {
                        this.showSuccess('Color deleted successfully');
                        if (typeof window.loadItemColors === 'function') window.loadItemColors();
                    } else {
                        this.showError('Error deleting color: ' + ((data && data.message) || ''));
                    }
                } catch (e) {
                    console.error('Error deleting color:', e);
                    this.showError('Error deleting color');
                }
            }
        );
    }

    closeSizeModal() {
        const modal2 = document.getElementById('sizeModal');
        if (modal2) {
            modal2.classList.add('hidden');
        }
    }

    async saveSize(formOrElement) {
        const form = formOrElement instanceof HTMLFormElement ? formOrElement : document.getElementById('sizeForm');
        if (!form) return;
        const formData = new FormData(form);
        const sizeData = {
            item_sku: this.currentItemSku,
            color_id: formData.get('sizeColorId') || null,
            size_name: formData.get('sizeName'),
            size_code: formData.get('sizeCode'),
            stock_level: parseInt(formData.get('sizeStockLevel'), 10) || 0,
            price_adjustment: parseFloat(formData.get('sizePriceAdjustment')) || 0.0,
            display_order: parseInt(formData.get('sizeDisplayOrder'), 10) || 0,
            is_active: formData.get('sizeIsActive') ? 1 : 0
        };
        const sizeId = formData.get('sizeId');
        const isEdit = !!(sizeId && sizeId !== '');
        if (isEdit) sizeData.size_id = parseInt(sizeId, 10);
        try {
            const data = await ApiClient.post(`/api/item_sizes.php?action=${isEdit ? 'update_size' : 'add_size'}`, sizeData);
            if (data && data.success) {
                this.showSuccess(`Size ${isEdit ? 'updated' : 'added'} successfully${data.new_total_stock ? ` - Total stock: ${data.new_total_stock}` : ''}`);
                this.closeSizeModal();
                if (typeof window.loadItemSizes === 'function') window.loadItemSizes();
                const stockField = document.getElementById('stockLevel');
                if (stockField && data.new_total_stock !== undefined) {
                    stockField.value = data.new_total_stock;
                }
            } else {
                this.showError('Error saving size: ' + ((data && data.message) || ''));
            }
        } catch (error) {
            console.error('Error saving size:', error);
            this.showError('Error saving size');
        }
    }

    deleteSize(sizeId) {
        if (!sizeId) return;
        this.showConfirmationModal(
            'Delete Size',
            'Are you sure you want to delete this size? This action cannot be undone.',
            async () => {
                try {
                    const data = await ApiClient.post('/api/item_sizes.php?action=delete_size', { size_id: sizeId });
                    if (data && data.success) {
                        this.showSuccess('Size deleted successfully');
                        if (typeof window.loadItemSizes === 'function') window.loadItemSizes();
                        if (data.new_total_stock !== undefined) {
                            const stockField = document.getElementById('stockLevel');
                            if (stockField) stockField.value = data.new_total_stock;
                        }
                    } else {
                        this.showError('Error deleting size: ' + ((data && data.message) || ''));
                    }
                } catch (e) {
                    console.error('Error deleting size:', e);
                    this.showError('Error deleting size');
                }
            }
        );
    }

    handleDelegatedMouseOver(event) {
        const t = event.target.closest('[data-action]');
        if (!t) return;
        const action = t.dataset.action;
        if (action === 'show-pricing-tooltip-with-data') {
            const type = t.dataset.componentType;
            const explanation = decodeURIComponent(t.dataset.explanation || '');
            this.showPricingTooltipWithData(event, type, explanation);
        } else if (action === 'show-pricing-tooltip-text') {
            const text = decodeURIComponent(t.dataset.text || '');
            this.showPricingTooltip(event, text);
        }
    }

    handleDelegatedMouseOut(event) {
        const t = event.target.closest('[data-action]');
        if (!t) return;
        const action = t.dataset.action;
        if (action === 'show-pricing-tooltip-with-data' || action === 'show-pricing-tooltip-text') {
            this.hidePricingTooltipDelayed();
        }
    }

    hidePricingTooltipDelayed() {
        this.tooltipTimeout = setTimeout(() => {
            if (this.currentTooltip && this.currentTooltip.parentNode) {
                this.currentTooltip.remove();
                this.currentTooltip = null;
            }
        }, 300);
    }

    async getPricingExplanation(reasoningText) {
        try {
            const url = `/api/get_pricing_explanation.php?text=${encodeURIComponent(reasoningText)}`;
            const data = await ApiClient.get(url);
            if (data.success) {
                return { title: data.title, explanation: data.explanation };
            }
        } catch (e) {
            console.error('Error fetching pricing explanation:', e);
        }
        return {
            title: 'AI Pricing Analysis',
            explanation: 'Advanced algorithmic analysis considering multiple market factors and pricing strategies.'
        };
    }

    async showPricingTooltip(event, reasoningText) {
        event.stopPropagation();

        if (this.tooltipTimeout) {
            clearTimeout(this.tooltipTimeout);
            this.tooltipTimeout = null;
        }

        // Remove existing tooltip(s)
        document.querySelectorAll('.pricing-tooltip').forEach(el => el.remove());

        const iconContainer = event.target.closest('.info-icon-container');
        if (!iconContainer) return;
        iconContainer.classList.add('inv-relative');

        // Loading tooltip
        const loading = document.createElement('div');
        loading.className = 'pricing-tooltip absolute z-50 bg-gray-800 text-white text-xs rounded-lg p-3 shadow-lg max-w-xs';
        loading.classList.add('tt-top-center');
        loading.innerHTML = `
            <div class="tooltip-arrow absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-800"></div>
            <div class="flex items-center space-x-2">
                <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-white"></div>
                <span>Loading explanation...</span>
            </div>
        `;
        iconContainer.appendChild(loading);

        try {
            const data = await this.getPricingExplanation(reasoningText);
            loading.remove();

            const tooltip = document.createElement('div');
            tooltip.className = 'pricing-tooltip absolute z-50 bg-gray-800 text-white text-xs rounded-lg p-3 shadow-lg max-w-xs';
            tooltip.classList.add('tt-top-center');
            tooltip.innerHTML = `
                <div class="tooltip-arrow absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-800"></div>
                <div class="font-semibold text-blue-200">${data.title}</div>
                <div>${data.explanation}</div>
            `;
            tooltip.addEventListener('mouseenter', () => {
                if (this.tooltipTimeout) { clearTimeout(this.tooltipTimeout); this.tooltipTimeout = null; }
            });
            tooltip.addEventListener('mouseleave', () => { this.hidePricingTooltipDelayed(); });
            iconContainer.appendChild(tooltip);
            this.currentTooltip = tooltip;

            const outsideClickHandler = (e) => {
                if (!tooltip.contains(e.target)) {
                    if (tooltip.parentNode) tooltip.remove();
                    document.removeEventListener('click', outsideClickHandler);
                }
            };
            document.addEventListener('click', outsideClickHandler);
        } catch (e) {
            if (loading && loading.parentNode) loading.remove();
        }
    }

    showPricingTooltipWithData(event, componentType, explanation) {
        event.stopPropagation();

        if (this.tooltipTimeout) {
            clearTimeout(this.tooltipTimeout);
            this.tooltipTimeout = null;
        }

        document.querySelectorAll('.pricing-tooltip').forEach(el => el.remove());

        const iconContainer = event.target.closest('.info-icon-container');
        if (!iconContainer) return;
        iconContainer.classList.add('inv-relative');

        const tooltip = document.createElement('div');
        tooltip.className = 'pricing-tooltip absolute z-50 bg-gray-800 text-white text-xs rounded-lg p-3 shadow-lg max-w-xs';
        tooltip.classList.add('tt-left-offset');

        const titles = {
            'cost_plus': 'Cost-Plus Pricing',
            'market_research': 'Market Research Analysis',
            'competitive_analysis': 'Competitive Analysis',
            'value_based': 'Value-Based Pricing',
            'brand_premium': 'Brand Premium',
            'psychological_pricing': 'Psychological Pricing',
            'seasonality': 'Seasonal Adjustment',
            'analysis': 'AI Pricing Analysis'
        };
        const title = titles[componentType] || 'Pricing Analysis';

        // Normalize explanation which may be a JSON-encoded string
        let explanationText = explanation;
        try {
            // If explanation is JSON string (encoded), parse it
            const parsed = typeof explanation === 'string' ? JSON.parse(explanation) : explanation;
            if (Array.isArray(parsed)) {
                explanationText = parsed.join(' ');
            } else if (parsed && typeof parsed === 'object') {
                explanationText = Object.values(parsed).join(' ');
            } else if (typeof parsed === 'string') {
                explanationText = parsed;
            }
        } catch (_e) {
            // Not JSON; use as-is
        }

        tooltip.innerHTML = `
            <div class="font-semibold">${title}</div>
            <div>${explanationText}</div>
        `;

        tooltip.addEventListener('mouseenter', () => {
            if (this.tooltipTimeout) { clearTimeout(this.tooltipTimeout); this.tooltipTimeout = null; }
        });
        tooltip.addEventListener('mouseleave', () => { this.hidePricingTooltipDelayed(); });

        iconContainer.appendChild(tooltip);
        this.currentTooltip = tooltip;
    }

    moveCarousel(type, direction) {
        const trackId = type === 'edit' ? 'editCarouselTrack' : 'viewCarouselTrack';
        const positionVar = type === 'edit' ? 'editCarouselPosition' : 'viewCarouselPosition';

        const track = document.getElementById(trackId);
        if (!track) return;

        const slides = track.querySelectorAll('.carousel-slide');
        const totalSlides = slides.length;
        const slidesToShow = 3;
        if (totalSlides <= slidesToShow) return;

        const maxPosition = Math.max(0, totalSlides - slidesToShow);

        let currentPosition = this[positionVar] || 0;
        currentPosition += direction;
        if (currentPosition < 0) currentPosition = 0;
        if (currentPosition > maxPosition) currentPosition = maxPosition;

        this[positionVar] = currentPosition;

        const translateX = currentPosition * 170; // 155px + 15px margin
        const viewport = track.parentElement;
        if (viewport) viewport.scrollLeft = translateX;
        this.updateCarouselNavigation(type, totalSlides);
    }

    updateCarouselNavigation(type, totalSlides) {
        const trackId = type === 'edit' ? 'editCarouselTrack' : 'viewCarouselTrack';
        const positionVar = type === 'edit' ? 'editCarouselPosition' : 'viewCarouselPosition';
        const track = document.getElementById(trackId);
        if (!track) return;
        const container = track.closest('.image-carousel-container');
        const prevBtn = container.querySelector('.carousel-prev');
        const nextBtn = container.querySelector('.carousel-next');
        const slidesToShow = 3;
        const currentPosition = this[positionVar] || 0;
        const maxPosition = Math.max(0, totalSlides - slidesToShow);
        if (prevBtn) prevBtn.classList.toggle('hidden', currentPosition === 0);
        if (nextBtn) nextBtn.classList.toggle('hidden', currentPosition >= maxPosition);
    }

    navigateToItem(direction) {
        if (!this.currentItemSku || this.items.length === 0) return;
        const currentIndex = this.items.findIndex(item => item.sku === this.currentItemSku);
        if (currentIndex === -1) return;
        let nextIndex;
        if (direction === 'next') {
            nextIndex = (currentIndex + 1) % this.items.length;
        } else {
            nextIndex = (currentIndex - 1 + this.items.length) % this.items.length;
        }
        const nextSku = this.items[nextIndex].sku;
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('edit', nextSku);
        currentUrl.searchParams.delete('view');
        window.location.href = currentUrl.toString();
    }

    confirmDeleteImage(imageId) {
        this.showConfirmationModal('Delete Image', 'Are you sure you want to delete this image? This action cannot be undone.', () => this.deleteImage(imageId));
    }


    async handleImageUpload(event) {
        console.log('[AdminInventory] handleImageUpload triggered.');
        const input = event.target;
        const files = input.files;
        if (!files || files.length === 0) {
            console.log('[AdminInventory] No files selected.');
            return;
        }

        // Validate file size (10MB per file)
        const maxBytes = 10 * 1024 * 1024;
        const oversized = [...files].filter(f => f.size > maxBytes);
        if (oversized.length) {
            const names = oversized.map(f => `${f.name} (${(f.size / 1024 / 1024).toFixed(1)}MB)`).join(', ');
            this.showError(`The following files are too large (max 10MB): ${names}`);
            input.value = '';
            return;
        }

        const sku = this.currentItemSku || (document.getElementById('skuEdit')?.value || document.getElementById('skuDisplay')?.value || '');
        if (!sku) {
            this.showError('SKU is required');
            return;
        }

        const formData = new FormData();
        formData.append('sku', sku);
        for (let i = 0; i < files.length; i++) {
            formData.append('images[]', files[i]);
        }
        console.log('[AdminInventory] Files to upload:', files);
        console.log('[AdminInventory] FormData prepared:', { sku: sku, fileCount: files.length });
        const altText = document.getElementById('name')?.value || '';
        formData.append('altText', altText);
        const useAI = document.getElementById('useAIProcessing')?.checked ? 'true' : 'false';
        formData.append('useAIProcessing', useAI);

        const progressContainer = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('uploadProgressBar');
        if (progressContainer && progressBar) {
            progressContainer.classList.remove('hidden');
            // dynamic width class helper
            const ensureWidthClass = (el, percent) => {
                const p = Math.max(0, Math.min(100, Math.round(percent)));
                const set = (window.__wfInvWidthClasses ||= new Set());
                let styleEl = document.getElementById('inventory-dynamic-widths');
                if (!styleEl) { styleEl = document.createElement('style'); styleEl.id = 'inventory-dynamic-widths'; document.head.appendChild(styleEl); }
                const cls = `w-p-${p}`;
                if (!set.has(cls)) { styleEl.textContent += `\n.${cls}{width:${p}%}`; set.add(cls); }
                // remove previous width class
                (el.className || '').split(' ').forEach(c => { if (/^w-p-\d{1,3}$/.test(c)) el.classList.remove(c); });
                el.classList.add(cls);
            };
            ensureWidthClass(progressBar, 0);
        }

        try {
            const result = await ApiClient.upload('/functions/process_multi_image_upload.php', formData, {
                onProgress: (e) => {
                    if (!progressBar || !e || !e.lengthComputable) return;
                    const percent = Math.min(100, Math.round((e.loaded / e.total) * 100));
                    const ensureWidthClass = (el, percentVal) => {
                        const p = Math.max(0, Math.min(100, Math.round(percentVal)));
                        const set = (window.__wfInvWidthClasses ||= new Set());
                        let styleEl = document.getElementById('inventory-dynamic-widths');
                        if (!styleEl) { styleEl = document.createElement('style'); styleEl.id = 'inventory-dynamic-widths'; document.head.appendChild(styleEl); }
                        const cls = `w-p-${p}`;
                        if (!set.has(cls)) { styleEl.textContent += `\n.${cls}{width:${p}%}`; set.add(cls); }
                        (el.className || '').split(' ').forEach(c => { if (/^w-p-\d{1,3}$/.test(c)) el.classList.remove(c); });
                        el.classList.add(cls);
                    };
                    ensureWidthClass(progressBar, percent);
                }
            });

            if (result.success) {
                this.showSuccess(result.message || `Successfully uploaded ${files.length} image(s)`);
                input.value = '';
                const skuToRefresh = sku;
                if (typeof this.loadCurrentImages === 'function') {
                    await this.loadCurrentImages(skuToRefresh, false);
                } else {
                    setTimeout(() => window.location.reload(), 1500);
                }
                if (Array.isArray(result.warnings) && result.warnings.length) {
                    result.warnings.forEach(w => this.showToast(w, 'info'));
                }
            } else {
                throw new Error(result.error || 'Upload failed');
            }
        } catch (error) {
            this.showError(`Upload failed: ${error.message}`);
            console.error('Upload error:', error);
        } finally {
            if (progressContainer && progressBar) {
                setTimeout(() => {
                    progressContainer.classList.add('hidden');
                    // reset width to 0%
                    const ensureWidthClass = (el, p) => {
                        const val = Math.max(0, Math.min(100, Math.round(p)));
                        const set = (window.__wfInvWidthClasses ||= new Set());
                        let styleEl = document.getElementById('inventory-dynamic-widths');
                        if (!styleEl) { styleEl = document.createElement('style'); styleEl.id = 'inventory-dynamic-widths'; document.head.appendChild(styleEl); }
                        const cls = `w-p-${val}`;
                        if (!set.has(cls)) { styleEl.textContent += `\n.${cls}{width:${val}%}`; set.add(cls); }
                        (el.className || '').split(' ').forEach(c => { if (/^w-p-\d{1,3}$/.test(c)) el.classList.remove(c); });
                        el.classList.add(cls);
                    };
                    ensureWidthClass(progressBar, 0);
                }, 800);
            }
        }
    }

    async loadCurrentImages(sku, isViewModal = false) {
        const targetSku = sku || this.currentItemSku;
        console.log('[AdminInventory] loadCurrentImages called with SKU:', targetSku);
        if (!targetSku) {
            console.log('[AdminInventory] No SKU provided, skipping image load');
            return;
        }
        try {
            const data = await ApiClient.get('/api/get_item_images.php', { sku: targetSku });
            console.log('[AdminInventory] Image API response:', data);
            if (data.success) {
                this.displayCurrentImages(data.images, isViewModal);
            } else {
                const container = document.getElementById('currentImagesList');
                if (container) container.innerHTML = '<div class="text-center text-gray-500 text-sm">Failed to load images</div>';
            }
        } catch (error) {
            console.error('Error loading images:', error);
            const container = document.getElementById('currentImagesList');
            if (container) container.innerHTML = '<div class="text-center text-gray-500 text-sm">Error loading images</div>';
        }
    }

    displayCurrentImages(images, isViewModal = false) {
        const container = document.getElementById('currentImagesList');
        if (!container) return;
        if (!images || images.length === 0) {
            container.innerHTML = '<div class="text-gray-500 text-sm col-span-full">No images uploaded yet</div>';
            return;
        }

        container.innerHTML = '';
        const grid = document.createElement('div');
        grid.className = 'grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4';

        images.forEach((image) => {
            const card = document.createElement('div');
            card.className = 'bg-white border rounded-lg overflow-hidden shadow-sm';
            card.innerHTML = `
                <div class="relative">
                    <img src="/${image.image_path}" alt="${image.alt_text || ''}" class="w-full h-32 object-contain bg-gray-50">
                    ${image.is_primary ? '<div class="absolute top-1 left-1 text-xs bg-green-600 text-white px-1 rounded">Primary</div>' : ''}
                </div>
                <div class="p-2 text-xs text-gray-700 truncate" title="${(image.image_path || '').split('/').pop()}">
                    ${(image.image_path || '').split('/').pop()}
                </div>
                ${!isViewModal ? `
                <div class="p-2 pt-0 flex gap-2">
                    ${!image.is_primary ? `<button type="button" data-action="set-primary-image" data-sku="${image.sku}" data-id="${image.id}" class="text-xs py-0.5 px-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">Primary</button>` : ''}
                    <button type="button" data-action="delete-image" data-sku="${image.sku}" data-id="${image.id}" class="text-xs py-0.5 px-2 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">Delete</button>
                </div>` : ''}
            `;
            const imgEl = card.querySelector('img');
            if (imgEl) {
                imgEl.addEventListener('error', () => {
                    imgEl.classList.add('hidden');
                    if (imgEl.parentElement) {
                        imgEl.parentElement.innerHTML = '<div class="u-width-100 u-height-100 u-display-flex u-flex-direction-column u-align-items-center u-justify-content-center u-background-f8f9fa u-color-6c757d u-border-radius-8px"><div class="u-font-size-2rem u-margin-bottom-0-5rem u-opacity-0-7">📷</div><div class="u-font-size-0-8rem u-font-weight-500">Image Not Found</div></div>';
                    }
                });
            }
            grid.appendChild(card);
        });

        container.appendChild(grid);
    }

    async setPrimaryImage(imageId) {
        const sku = this.currentItemSku || (document.getElementById('skuEdit')?.value || document.getElementById('skuDisplay')?.value || '');
        if (!imageId) {
            this.showError('Missing image ID');
            return;
        }
        if (!sku) {
            this.showError('SKU is required');
            return;
        }
        try {
            const data = await ApiClient.post('/api/set_primary_image.php', { imageId, sku });
            if (data && data.success) {
                this.showSuccess('Primary image updated');
                await this.loadCurrentImages(sku, false);
            } else {
                this.showError((data && data.error) || 'Failed to set primary image');
            }
        } catch (e) {
            console.error('setPrimaryImage error:', e);
            this.showError('Failed to set primary image');
        }
    }

    async deleteImage(imageId) {
        try {
            const data = await ApiClient.post('/api/delete_item_image.php', { imageId });
            if (data && data.success) {
                this.showSuccess(data.message || 'Image deleted');
                const sku = this.currentItemSku || (document.getElementById('skuEdit')?.value || document.getElementById('skuDisplay')?.value || '');
                if (sku) await this.loadCurrentImages(sku, false);
            } else {
                this.showError((data && data.error) || 'Failed to delete image');
            }
        } catch (e) {
            console.error('deleteImage error:', e);
            this.showError('Failed to delete image');
        }
    }

    async processExistingImagesWithAI() {
        this.showConfirmationModal('Process Images with AI', 'This will analyze existing images. It may take a moment. Continue?', async () => {
            const button = document.querySelector('[data-action="process-images-ai"]');
            const originalHtml = button ? button.innerHTML : '';
            if (button) {
                button.innerHTML = 'Processing...';
                button.disabled = true;
            }

            const sku = this.currentItemSku || document.getElementById('skuEdit')?.value || document.getElementById('skuDisplay')?.value || '';
            if (!sku) {
                this.showError('SKU is required');
                if (button) {
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                }
                return;
            }

            const self = this;
            try {
                if (window.aiProcessingModal && typeof window.aiProcessingModal.show === 'function') {
                    window.aiProcessingModal.onComplete = async function() {
                        try {
                            if (typeof self.loadCurrentImages === 'function') {
                                await self.loadCurrentImages(sku, false);
                            }
                            self.showSuccess('AI processing completed! Images updated.');
                        } catch (e) {
                            console.warn('Refresh images after AI complete failed:', e);
                        }
                    };
                    window.aiProcessingModal.onCancel = function() {
                        self.showInfo('AI processing was cancelled.');
                    };
                    window.aiProcessingModal.show();
                    if (typeof window.aiProcessingModal.updateProgress === 'function') {
                        window.aiProcessingModal.updateProgress('Analyzing images…');
                    }
                }

                const result = await ApiClient.get('/api/run_image_analysis.php', { sku });
                if (!result.success) {
                    throw new Error(result.error || 'AI processing failed.');
                }

                // Summarize in modal if available
                if (window.aiProcessingModal && typeof window.aiProcessingModal.showSuccess === 'function') {
                    const processed = result.processed || 0;
                    const skipped = result.skipped || 0;
                    const errorCount = Array.isArray(result.errors) ? result.errors.length : 0;
                    window.aiProcessingModal.showSuccess('AI processing finished', [
                        `Processed: ${processed}`,
                        `Skipped: ${skipped}`,
                        errorCount ? `Errors: ${errorCount}` : 'No errors'
                    ]);
                } else {
                    this.showSuccess(`AI processing finished. Processed: ${result.processed || 0}, Skipped: ${result.skipped || 0}`);
                }
            } catch (error) {
                console.error('AI processing error:', error);
                this.showError('AI processing failed: ' + error.message);
            } finally {
                if (button) {
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                }
            }
        });
    }

    openCostModal(category, itemId = null) {
        this.currentEditCostItem = { category, id: itemId };
        const modal = document.getElementById('costItemModal');
        const modalTitle = document.getElementById('costItemModalTitle');
        const costNameField = document.getElementById('costName');
        const costValueField = document.getElementById('costValue');
        const costNameLabel = document.getElementById('costNameLabel');
        modalTitle.textContent = itemId ? `Edit ${category} Item` : `Add ${category} Item`;
        // Label differs by category: materials uses Name, others use Description
        if (costNameLabel) {
            costNameLabel.textContent = (category === 'materials') ? 'Name' : 'Description';
        }
        // Populate values when editing
        try {
            const existing = (itemId && this.costBreakdown && this.costBreakdown[category]) ? this.costBreakdown[category][itemId] : null;
            if (existing) {
                costNameField.value = existing.name || existing.description || '';
                costValueField.value = typeof existing.cost === 'number' ? existing.cost.toFixed(2) : (existing.cost || '');
            } else {
                costNameField.value = '';
                costValueField.value = '';
            }
        } catch (_) {}
        modal.classList.remove('hidden');
    }

    closeCostModal() {
        const modal = document.getElementById('costItemModal');
        modal.classList.add('hidden');
        this.currentEditCostItem = null;
    }

    async saveCostItem() {
        if (!this.currentEditCostItem) return;
        const { category, id } = this.currentEditCostItem;
        const name = document.getElementById('costName').value.trim();
        const cost = parseFloat(document.getElementById('costValue').value);
        if (!name || isNaN(cost)) {
            this.showError('Please enter a valid name and cost.');
            return;
        }
        // Prepare payload shape expected by API
        const body = (category === 'materials') ? { name, cost } : { description: name, cost };
        // Optimistic local update using a temporary id for new items
        const tempId = id || `tmp_${category}_${Date.now()}`;
        if (!this.costBreakdown[category]) this.costBreakdown[category] = {};
        this.costBreakdown[category][tempId] = { ...(category === 'materials' ? { name } : { description: name }), cost };
        this.renderCostList(category);
        this.updateTotalsDisplay();
        this.closeCostModal();
        try {
            const isUpdate = !!id;
            const q = `inventoryId=${encodeURIComponent(this.currentItemSku)}&costType=${encodeURIComponent(category)}` + (isUpdate ? `&id=${encodeURIComponent(id)}` : '');
            const result = await ApiClient.post(`/functions/process_cost_breakdown.php?${q}`, {
                method: isUpdate ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            if (result && result.success) {
                // Ensure local map uses server id for subsequent edits/deletes
                const serverId = (result.data && (result.data.id || result.data.ID || result.data.Id)) || id;
                if (!serverId) return; // nothing to sync
                if (!isUpdate) {
                    // Move temp entry under server id
                    const saved = this.costBreakdown[category][tempId];
                    if (saved) {
                        delete this.costBreakdown[category][tempId];
                        this.costBreakdown[category][serverId] = saved;
                        this.renderCostList(category);
                        this.updateTotalsDisplay();
                    }
                }
            } else {
                this.showError('Failed to save item. Reverting changes.');
                delete this.costBreakdown[category][tempId];
                this.renderCostList(category);
                this.updateTotalsDisplay();
            }
        } catch (error) {
            this.showError('An error occurred while saving.');
            console.error('Save cost item error:', error);
            delete this.costBreakdown[category][tempId];
            this.renderCostList(category);
            this.updateTotalsDisplay();
        }
    }

    confirmDeleteCostItem(category, itemId) {
        this.showConfirmationModal('Delete Cost Item', 'Are you sure you want to delete this item?', () => this.deleteCostItem(category, itemId));
    }

    async deleteCostItem(category, itemId) {
        if (this.costBreakdown[category] && this.costBreakdown[category][itemId]) {
            delete this.costBreakdown[category][itemId];
            this.renderCostList(category);
            this.updateTotalsDisplay();
        }
        try {
            const q = `inventoryId=${encodeURIComponent(this.currentItemSku)}&costType=${encodeURIComponent(category)}&id=${encodeURIComponent(itemId)}`;
            const result = await ApiClient.get(`/functions/process_cost_breakdown.php?${q}`, { method: 'DELETE' });
            if (!result.success) {
                this.showError('Failed to delete item from server.');
            }
        } catch (error) {
            this.showError('An error occurred while deleting.');
            console.error('Delete cost item error:', error);
        }
    }

    async handleConfirmDeleteItem() {
        try {
            const sku = this.itemToDeleteSku;
            if (!sku) return;
            // Close modal immediately for responsiveness
            const modal = document.getElementById('deleteConfirmModal');
            if (modal) {
                if (typeof window.hideModal === 'function') window.hideModal('deleteConfirmModal');
                else modal.classList.remove('show');
            }
            const data = await ApiClient.request(`/functions/process_inventory_update.php?action=delete&sku=${encodeURIComponent(sku)}`, {
                method: 'DELETE'
            });
            if (data && data.success) {
                this.showSuccess(data.message || 'Item deleted');
                setTimeout(() => { window.location.href = '?page=admin&section=inventory'; }, 1000);
            } else {
                this.showError((data && data.error) || 'Failed to delete item.');
            }
        } catch (err) {
            console.error('Delete item error:', err);
            this.showError('Failed to delete item.');
        } finally {
            this.itemToDeleteSku = null;
        }
    }

    renderCostList(category) {
        const listElement = document.getElementById(`${category}List`);
        if (!listElement) return;
        listElement.innerHTML = '';
        const items = this.costBreakdown[category];
        const itemKeys = Object.keys(items || {});
        if (itemKeys.length === 0) {
            listElement.innerHTML = '<p class="text-gray-500 text-xs italic">No items added yet.</p>';
            return;
        }
        itemKeys.forEach(id => {
            const item = items[id];
            const itemRow = document.createElement('div');
            itemRow.className = 'cost-item-row flex justify-between items-center p-2 rounded hover:bg-gray-100';
            const title = (item && (item.name || item.description || '')).toString();
            itemRow.innerHTML = `
                <span>${this.escapeHtml(title)}</span>
                <div class="flex items-center">
                    <span class="mr-4 font-medium">$${parseFloat(item.cost).toFixed(2)}</span>
                    <button data-action="open-cost-modal" data-category="${category}" data-id="${id}" class="text-blue-500 hover:text-blue-700 mr-2">Edit</button>
                    <button data-action="delete-cost-item" data-category="${category}" data-id="${id}" class="text-red-500 hover:text-red-700">Delete</button>
                </div>
            `;
            listElement.appendChild(itemRow);
        });
    }

    updateTotalsDisplay() {
        let materialTotal = 0, laborTotal = 0, energyTotal = 0, equipmentTotal = 0;
        for (const id in this.costBreakdown.materials) materialTotal += parseFloat(this.costBreakdown.materials[id].cost);
        for (const id in this.costBreakdown.labor) laborTotal += parseFloat(this.costBreakdown.labor[id].cost);
        for (const id in this.costBreakdown.energy) energyTotal += parseFloat(this.costBreakdown.energy[id].cost);
        for (const id in this.costBreakdown.equipment) equipmentTotal += parseFloat(this.costBreakdown.equipment[id].cost);
        const totalCost = materialTotal + laborTotal + energyTotal + equipmentTotal;
        this.costBreakdown.totals = { materialTotal, laborTotal, energyTotal, equipmentTotal, totalCost };
        const mt = document.getElementById('materialTotal'); if (mt) mt.textContent = `$${materialTotal.toFixed(2)}`;
        const lt = document.getElementById('laborTotal'); if (lt) lt.textContent = `$${laborTotal.toFixed(2)}`;
        const et = document.getElementById('energyTotal'); if (et) et.textContent = `$${energyTotal.toFixed(2)}`;
        const qt = document.getElementById('equipmentTotal'); if (qt) qt.textContent = `$${equipmentTotal.toFixed(2)}`;
        const tt = document.getElementById('totalCost'); if (tt) tt.textContent = `$${totalCost.toFixed(2)}`;
        const suggestedCostDisplay = document.getElementById('suggestedCostDisplay');
        if(suggestedCostDisplay) {
            suggestedCostDisplay.textContent = `$${totalCost.toFixed(2)}`;
        }
    }

    confirmClearCostBreakdown() {
        this.showConfirmationModal('Clear Cost Breakdown', 'Are you sure you want to delete all cost items? This is irreversible.', () => this.clearCostBreakdownCompletely());
    }

    async clearCostBreakdownCompletely() {
        this.costBreakdown = { materials: {}, labor: {}, energy: {}, equipment: {}, totals: {} };
        ['materials', 'labor', 'energy', 'equipment'].forEach(cat => this.renderCostList(cat));
        this.updateTotalsDisplay();
        try {
            const result = await ApiClient.request(`/functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(this.currentItemSku)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_all' })
            });
            if (result.success) {
                this.showSuccess('Cost breakdown cleared.');
            } else {
                this.showError('Failed to clear breakdown on server.');
            }
        } catch (error) {
            this.showError('An error occurred while clearing the breakdown.');
            console.error('Clear breakdown error:', error);
        }
    }

    async getCostSuggestion() {
        const button = document.querySelector('[data-action="get-cost-suggestion"]');
        const originalHtml = button.innerHTML;
        button.innerHTML = 'Analyzing...';
        button.disabled = true;
        try {
            // Build request body expected by suggest_cost.php (POST JSON)
            const name = document.getElementById('name')?.value || '';
            const description = document.getElementById('description')?.value || '';
            const category = document.getElementById('categoryEdit')?.value || '';
            // Ensure SKU is set
            if (!this.currentItemSku) {
                const skuField = document.getElementById('skuEdit') || document.getElementById('sku') || document.getElementById('skuDisplay');
                if (skuField) this.currentItemSku = skuField.value || skuField.textContent || '';
            }
            const data = await ApiClient.post('/api/suggest_cost.php', {
                sku: this.currentItemSku, name, description, category
            });
            if (data && data.success) {
                // Map single response to suggestions array expected by UI
                const suggestions = [{
                    model: 'AI Engine',
                    suggestedCost: data.suggestedCost,
                    confidence: data.confidence || 'n/a',
                    reasoning: data.reasoning || '',
                    breakdown: data.breakdown || {}
                }];
                this.showCostSuggestionChoiceDialog(suggestions);
            } else {
                this.showError(data.error || 'Failed to get cost suggestion.');
            }
        } catch (error) {
            this.showError('Failed to connect to cost suggestion service.');
            console.error('Get cost suggestion error:', error);
        } finally {
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    }

    async getPriceSuggestion() {
        const button = document.querySelector('[data-action="get-price-suggestion"]');
        const originalHtml = button.innerHTML;
        button.innerHTML = 'Analyzing...';
        button.disabled = true;
        const itemData = {
            name: document.getElementById('name').value,
            description: document.getElementById('description').value,
            category: document.getElementById('categoryEdit')?.value || '',
            costPrice: document.getElementById('costPrice').value,
            sku: this.currentItemSku,
            useImages: true
        };
        try {
            // Ensure SKU is set
            if (!this.currentItemSku) {
                const skuField = document.getElementById('skuEdit') || document.getElementById('sku') || document.getElementById('skuDisplay');
                if (skuField) this.currentItemSku = skuField.value || skuField.textContent || '';
            }
            itemData.sku = this.currentItemSku;
            const data = await ApiClient.post('/api/suggest_price.php', itemData);
            if (data.success) {
                this.displayPriceSuggestion(data);
                this.showSuccess('Price suggestion generated!');
            } else {
                this.showError(data.error || 'Failed to get price suggestion.');
            }
        } catch (error) {
            this.showError('Failed to connect to pricing service.');
            console.error('Get price suggestion error:', error);
        } finally {
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    }

    async processImagesWithAI() {
        const button = document.querySelector('[data-action="process-images-ai"]');
        const originalHtml = button.innerHTML;
        button.innerHTML = 'Processing...';
        button.disabled = true;

        try {
            // Ensure SKU is set
            if (!this.currentItemSku) {
                const skuField = document.getElementById('skuEdit') || document.getElementById('sku') || document.getElementById('skuDisplay');
                if (skuField) this.currentItemSku = skuField.value || skuField.textContent || '';
            }

            if (!this.currentItemSku) {
                this.showError('No SKU selected for image processing');
                return;
            }

            const data = await ApiClient.get('/api/run_image_analysis.php', { sku: this.currentItemSku });

            if (data.success) {
                const processedCount = data.processed || 0;
                const skippedCount = data.skipped || 0;
                const errorCount = data.errors ? data.errors.length : 0;

                if (processedCount > 0) {
                    this.showSuccess(`Successfully processed ${processedCount} image(s) with AI!`);
                    // Refresh image list if there's a way to do so
                    if (window.refreshImageList) {
                        window.refreshImageList();
                    }
                } else {
                    this.showError('No images were processed. They may already be processed or there were errors.');
                }

                if (errorCount > 0) {
                    console.error('Image processing errors:', data.errors);
                    this.showError(`${errorCount} image(s) failed to process. Check console for details.`);
                }

                if (skippedCount > 0) {
                    this.showSuccess(`${skippedCount} image(s) were skipped (already processed).`);
                }
            } else {
                this.showError(data.error || 'Failed to process images with AI.');
            }
        } catch (error) {
            this.showError('Failed to connect to image processing service.');
            console.error('Process images with AI error:', error);
        } finally {
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    }

    displayPriceSuggestion(data) {
        const display = document.getElementById('priceSuggestionDisplay');
        const placeholder = document.getElementById('priceSuggestionPlaceholder');
        if (!display || !placeholder) return;
        placeholder.classList.add('hidden');
        display.classList.remove('hidden');
        document.getElementById('displaySuggestedPrice').textContent = `$${parseFloat(data.suggestedPrice).toFixed(2)}`;
        document.getElementById('displayConfidence').textContent = `${data.confidence || 'N/A'}`;
        const ts = data.createdAt ? new Date(data.createdAt) : new Date();
        document.getElementById('displayTimestamp').textContent = ts.toLocaleString();
        display.dataset.suggestedPrice = data.suggestedPrice;
        const reasoningList = document.getElementById('reasoningList');
        reasoningList.innerHTML = '';
        if (data.reasoning) {
            const items = data.reasoning.split('•').filter(s => s.trim());
            items.forEach(itemText => {
                const li = document.createElement('li');
                li.textContent = itemText.trim();
                reasoningList.appendChild(li);
            });
        }
    }
    applyPriceSuggestion() {
        const display = document.getElementById('priceSuggestionDisplay');
        const retailPriceField = document.getElementById('retailPrice');
        const suggestedPrice = display.dataset.suggestedPrice;
        if (suggestedPrice) {
            retailPriceField.value = parseFloat(suggestedPrice).toFixed(2);
            retailPriceField.classList.add('flash-highlight-green');
            setTimeout(() => { retailPriceField.classList.remove('flash-highlight-green'); }, 2000);
            this.showSuccess('Suggested price applied!');
        }
    }

    clearPriceSuggestion() {
        const display = document.getElementById('priceSuggestionDisplay');
        const placeholder = document.getElementById('priceSuggestionPlaceholder');
        display.classList.add('hidden');
        placeholder.classList.remove('hidden');
        display.dataset.suggestedPrice = '';
    }

    async loadExistingPriceSuggestion(sku) {
        if (!sku) return;
        try {
            const data = await ApiClient.get('/api/get_price_suggestion.php', { sku });
            if (data.success && data.suggestedPrice) {
                this.displayPriceSuggestion(data);
            }
        } catch (error) {
            console.error('Error loading existing price suggestion:', error);
        }
    }
    
    // Legacy view helper: if dedicated view IDs exist, toggle them; otherwise fallback to edit IDs
    async loadExistingViewPriceSuggestion(sku) {
        if (!sku) return;
        try {
            const data = await ApiClient.get('/api/get_price_suggestion.php', { sku, _t: Date.now() });
            const viewDisplay = document.getElementById('viewPriceSuggestionDisplay');
            const viewPlaceholder = document.getElementById('viewPriceSuggestionPlaceholder');
            if (data.success && data.suggestedPrice) {
                if (viewDisplay && viewPlaceholder) {
                    // Minimal view handling
                    viewPlaceholder.classList.add('hidden');
                    viewDisplay.classList.remove('hidden');
                } else {
                    // Fallback to shared display
                    this.displayPriceSuggestion(data);
                }
            } else {
                if (viewDisplay && viewPlaceholder) {
                    viewPlaceholder.classList.remove('hidden');
                    viewDisplay.classList.add('hidden');
                }
            }
        } catch (error) {
            console.error('Error loading view price suggestion:', error);
        }
    }
    
    async loadExistingMarketingSuggestion(sku) {
        if (!sku) return;
        try {
            const data = await ApiClient.get('/api/get_marketing_suggestion.php', { sku });
            if (data.success && data.exists) {
                this.displayMarketingSuggestionIndicator(data.suggestion || null);
            }
        } catch (error) {
            console.error('Error loading existing marketing suggestion:', error);
        }
    }

    displayMarketingSuggestionIndicator(suggestion = null) {
        const marketingButton = document.querySelector('#open-marketing-manager-btn') || document.querySelector('[data-action="open-marketing-manager"]');
        if (!marketingButton) return;
        const existing = marketingButton.querySelector('.suggestion-indicator');
        if (existing) existing.remove();
        const indicator = document.createElement('span');
        indicator.className = 'suggestion-indicator ml-2 px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full';
        indicator.textContent = '💾 Previous';
        try {
            if (suggestion && suggestion.created_at) {
                const dt = new Date(suggestion.created_at);
                indicator.title = `Previous AI analysis available from ${dt.toLocaleDateString()}`;
            } else {
                indicator.title = 'Previous AI analysis available';
            }
        } catch (_) {
            indicator.title = 'Previous AI analysis available';
        }
        marketingButton.appendChild(indicator);
        // Store globally for any legacy UI that expects it
        if (suggestion) window.existingMarketingSuggestion = suggestion;
    }

    async generateMarketingCopy() {
        // Validate SKU and required fields
        const sku = this.currentItemSku || document.getElementById('sku')?.value || document.getElementById('skuDisplay')?.textContent;
        if (!sku) {
            this.showError('No item selected for marketing generation');
            return;
        }
        const nameEl = document.getElementById('name');
        if (!nameEl || !nameEl.value.trim()) {
            this.showError('Item name is required for marketing generation');
            return;
        }

        // Open AI Comparison modal (Vite-managed)
        this.openAiComparisonModal();
        const progressText = document.getElementById('aiProgressText');
        if (progressText) progressText.textContent = 'Initializing AI analysis...';

        // Prepare payload
        const brandVoice = document.getElementById('brandVoice')?.value || '';
        const contentTone = document.getElementById('contentTone')?.value || '';
        const supportsImages = await this.checkAIImageSupport();
        const itemData = {
            sku,
            name: nameEl.value.trim(),
            description: document.getElementById('description')?.value || '',
            category: document.getElementById('categoryEdit')?.value || '',
            brandVoice,
            contentTone,
            useImages: !!supportsImages
        };

        try {
            const data = await ApiClient.post('/api/suggest_marketing.php', itemData);
            if (!data || !data.success) {
                this.showError((data && data.error) || 'Failed to generate marketing content');
                this.closeAiComparisonModal();
                return;
            }

            // Store globally for comparison selection helpers
            window.aiComparisonData = data;

            // Populate comparison UI using module builder
            const current = await ApiClient.get('/api/marketing_manager.php', {
                action: 'get_marketing_data',
                sku,
                _t: Date.now()
            });
            this.buildComparisonInterface(data, current?.data || null);
            if (window.collapseAIProgressSection) {
                try { window.collapseAIProgressSection(); } catch (_) {}
            }
            const applyBtn = document.getElementById('applyChangesBtn');
            if (applyBtn) applyBtn.classList.remove('hidden');
            const status = document.getElementById('statusText');
            if (status) status.textContent = 'Select changes to apply, then click Apply Selected Changes';
            this.showSuccess('AI marketing content generated.');
        } catch (err) {
            console.error('Error generating marketing content:', err);
            this.showError('Failed to generate marketing content');
            this.closeAiComparisonModal();
        }
    }

    closeMarketingModal() {
        const modal = document.getElementById('marketingIntelligenceModal');
        if (modal) modal.remove();
    }

    openMarketingManager() {
        const modal = document.getElementById('marketingManagerModal');
        if (!modal) return;
        const skuEl = document.getElementById('currentEditingSku');
        if (skuEl && this.currentItemSku) skuEl.textContent = `SKU: ${this.currentItemSku}`;
        modal.classList.remove('hidden');
        modal.classList.add('show');
        // optional: show previous suggestion badge
        this.loadExistingMarketingSuggestion(this.currentItemSku);
        // show AI provider/model status
        this.loadAiProviderStatus(modal);
    }

    closeMarketingManager() {
        const modal = document.getElementById('marketingManagerModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('show');
        }
    }

    async loadAiProviderStatus(modal) {
        try {
            if (!modal) modal = document.getElementById('marketingManagerModal');
            if (!modal) return;
            // Fetch AI settings
            const data = await ApiClient.get('/api/ai_settings.php', { action: 'get_settings', _t: Date.now() });
            const settings = (data && data.settings) ? data.settings : {};
            const provider = settings.ai_provider || 'unknown';
            // Map provider to model key for display
            const modelKey = provider + '_model';
            const model = settings[modelKey] || settings.anthropic_model || settings.openai_model || settings.google_model || 'default';

            // Create or update status element in header
            const header = modal.querySelector('.modal-header');
            if (!header) return;
            let statusEl = header.querySelector('#aiProviderStatus');
            if (!statusEl) {
                statusEl = document.createElement('div');
                statusEl.id = 'aiProviderStatus';
                statusEl.className = 'text-xs text-gray-500 flex items-center gap-2';
                // Insert near the close button group if present, else append to header
                const rightGroup = header.querySelector('.flex.items-center.gap-2');
                if (rightGroup) {
                    rightGroup.parentElement.insertBefore(statusEl, rightGroup);
                } else {
                    header.appendChild(statusEl);
                }
            }
            statusEl.textContent = `Using AI: ${provider} • ${model}`;
            // Make the status line clickable to open AI settings
            statusEl.className = 'text-xs text-gray-500 flex items-center gap-2 cursor-pointer';
            statusEl.title = 'Click to open AI Settings';
            statusEl.addEventListener('click', () => {
                // Open AI settings modal if we're on the admin settings page
                if (window.location.pathname.includes('admin_settings')) {
                    const openBtn = document.querySelector('[data-action="open-ai-settings"]');
                    if (openBtn) openBtn.click();
                } else {
                    // Navigate to admin settings page
                    window.location.href = '/admin/admin_settings.php';
                }
            });
        } catch (err) {
            // Non-fatal – just skip status on error
            console.warn('[AdminInventory] Failed to load AI provider status:', err);
        }
    }

    closeAiComparisonModal() {
        const modal = document.getElementById('aiComparisonModal');
        if (!modal) return;
        modal.classList.add('hidden');
    }

    ensureAiComparisonModal() {
        if (document.getElementById('aiComparisonModal')) return;
        const modal = document.createElement('div');
        modal.id = 'aiComparisonModal';
        modal.className = 'admin-modal-overlay hidden fixed inset-0 flex items-start justify-center overflow-y-auto';
        modal.innerHTML = `
            <div class="admin-modal relative mt-8 bg-white rounded-lg shadow-xl w-full max-w-4xl">
                <div class="modal-header flex justify-between items-center p-3 border-b border-gray-200">
                    <h4 class="text-base font-semibold text-gray-800">AI Comparison</h4>
                    <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-ai-comparison" aria-label="Close">×</button>
                </div>
                <div class="modal-body p-4">
                    <div id="aiProgressText" class="text-sm text-gray-500"></div>
                    <div id="aiComparisonContent" class="space-y-4"></div>
                </div>
                <div class="border-t px-4 py-3 bg-gray-50 flex items-center justify-end">
                    <button id="applyChangesBtn" data-action="apply-selected-comparison" class="hidden bg-blue-600 text-white text-sm rounded px-4 py-2 hover:bg-blue-700">Apply Selected Changes</button>
                </div>
            </div>`;
        document.body.appendChild(modal);
    }

    openAiComparisonModal() {
        this.ensureAiComparisonModal();
        const modal = document.getElementById('aiComparisonModal');
        if (modal) modal.classList.remove('hidden');
    }

    // ===== Marketing Defaults (migrated from legacy) =====
    async updateGlobalMarketingDefault(settingType, value) {
        try {
            const updateData = { auto_apply_defaults: 'true' };
            if (settingType === 'brand_voice') {
                updateData.default_brand_voice = value;
                const contentToneField = document.getElementById('contentTone');
                updateData.default_content_tone = contentToneField ? contentToneField.value : 'conversational';
            } else if (settingType === 'content_tone') {
                updateData.default_content_tone = value;
                const brandVoiceField = document.getElementById('brandVoice');
                updateData.default_brand_voice = brandVoiceField ? brandVoiceField.value : 'friendly';
            }
            const data = await ApiClient.post('/api/website_config.php?action=update_marketing_defaults', updateData);
            if (data.success) {
                this.showSuccess(`Global ${settingType.replace('_', ' ')} updated successfully!`);
            } else {
                this.showError(data.error || 'Failed to update global setting');
            }
        } catch (error) {
            console.error('Error updating global marketing default:', error);
            this.showError('Failed to update global setting');
        }
    }

    // ===== AI Comparison (migrated from legacy) =====
    toggleSelectAllComparison() {
        const selectAllCheckbox = document.getElementById('selectAllComparison');
        if (!selectAllCheckbox) return;
        const isChecked = !!selectAllCheckbox.checked;
        const checkboxes = document.querySelectorAll('[data-action="toggle-comparison"][data-field]');
        checkboxes.forEach(cb => {
            cb.checked = isChecked;
            const fieldKey = cb.getAttribute('data-field');
            if (fieldKey) this.toggleComparison(fieldKey);
        });
    }

    toggleComparison(fieldKey) {
        const checkbox = document.getElementById(`comparison-${fieldKey}-checkbox`) || document.querySelector(`[data-action="toggle-comparison"][data-field="${fieldKey}"]`);
        if (!checkbox) return;
        if (checkbox.checked) {
            const value = this.getAiSuggestedValue(fieldKey);
            if (value) this.selectedComparisonChanges[fieldKey] = value;
        } else {
            delete this.selectedComparisonChanges[fieldKey];
        }
        this.updateSelectAllState();
        const applyBtn = document.getElementById('applyChangesBtn');
        const selectedCount = Object.keys(this.selectedComparisonChanges).length;
        if (applyBtn) {
            applyBtn.textContent = selectedCount > 0 ? `Apply ${selectedCount} Selected Changes` : 'Apply Selected Changes';
        }
    }

    getAiSuggestedValue(fieldKey) {
        // Prefer structured data if available
        const ai = window.aiComparisonData || {};
        if (fieldKey === 'title' && ai.title) return ai.title;
        if (fieldKey === 'description' && ai.description) return ai.description;
        if (fieldKey === 'target_audience' && ai.targetAudience) return ai.targetAudience;
        if ((fieldKey === 'demographic_targeting' || fieldKey === 'psychographic_profile') && ai.marketingIntelligence) {
            const v = ai.marketingIntelligence[fieldKey];
            if (v) return v;
        }
        // Fallback: try to read from DOM near the checkbox
        const checkbox = document.getElementById(`comparison-${fieldKey}-checkbox`) || document.querySelector(`[data-action="toggle-comparison"][data-field="${fieldKey}"]`);
        if (checkbox) {
            const card = checkbox.closest('.bg-white') || checkbox.closest('[data-comparison-card]') || checkbox.closest('div');
            const suggestedEl = card && card.querySelector('.bg-green-50 p');
            if (suggestedEl) return suggestedEl.textContent.trim();
        }
        return '';
    }

    updateSelectAllState() {
        const selectAllCheckbox = document.getElementById('selectAllComparison');
        if (!selectAllCheckbox) return;
        const all = Array.from(document.querySelectorAll('[data-action="toggle-comparison"][data-field]'));
        const checkedCount = all.filter(cb => cb.checked).length;
        if (checkedCount === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCount === all.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    // ===== AI Comparison UI Builder (migrated from legacy inline) =====
    createComparisonCard(fieldKey, fieldLabel, currentValue, suggestedValue) {
        const cardId = `comparison-${fieldKey}`;
        return `
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm" data-comparison-card>
                <div class="flex items-center justify-between">
                    <h4 class="font-medium text-gray-800">${fieldLabel}</h4>
                    <label class="flex items-center">
                        <input type="checkbox" id="${cardId}-checkbox" class="" data-action="toggle-comparison" data-field="${fieldKey}">
                        <span class="text-sm text-gray-600">Apply AI suggestion</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded">
                        <h5 class="text-sm font-medium text-gray-600">Current</h5>
                        <p class="text-sm text-gray-800">${currentValue || '<em>No current value</em>'}</p>
                    </div>
                    <div class="bg-green-50 rounded">
                        <h5 class="text-sm font-medium text-green-600">AI Suggested</h5>
                        <p class="text-sm text-gray-800">${suggestedValue}</p>
                    </div>
                </div>
            </div>
        `;
    }

    buildComparisonInterface(aiData, currentMarketingData) {
        const contentDiv = document.getElementById('aiComparisonContent');
        const applyBtn = document.getElementById('applyChangesBtn');
        if (!contentDiv) return;

        let html = '<div class="space-y-6">';
        html += '<div class="text-center">';
        html += '<h3 class="text-lg font-semibold text-gray-800">🎯 AI Content Comparison</h3>';
        html += '<p class="text-sm text-gray-600">Review and select which AI-generated content to apply to your item</p>';
        html += '</div>';

        const availableFields = [];

        // Title comparison
        if (aiData.title) {
            const currentTitle = document.getElementById('name')?.value || '';
            const suggestedTitle = aiData.title;
            if (currentTitle !== suggestedTitle) {
                availableFields.push('title');
                html += this.createComparisonCard('title', 'Item Title', currentTitle, suggestedTitle);
            }
        }

        // Description comparison
        if (aiData.description) {
            const currentDesc = document.getElementById('description')?.value || '';
            const suggestedDesc = aiData.description;
            if (currentDesc !== suggestedDesc) {
                availableFields.push('description');
                html += this.createComparisonCard('description', 'Item Description', currentDesc, suggestedDesc);
            }
        }

        // Marketing fields comparison - use database values as current
        const marketingFields = [
            { key: 'target_audience', label: 'Target Audience', current: currentMarketingData?.target_audience || '', suggested: aiData.targetAudience },
            { key: 'demographic_targeting', label: 'Demographics', current: currentMarketingData?.demographic_targeting || '', suggested: aiData.marketingIntelligence?.demographic_targeting },
            { key: 'psychographic_profile', label: 'Psychographics', current: currentMarketingData?.psychographic_profile || '', suggested: aiData.marketingIntelligence?.psychographic_profile }
        ];
        marketingFields.forEach(field => {
            if (field.suggested && field.current !== field.suggested) {
                availableFields.push(field.key);
                html += this.createComparisonCard(field.key, field.label, field.current, field.suggested);
            }
        });

        // Add select all control if there are available fields
        if (availableFields.length > 0) {
            html = html.replace('<div class="space-y-6">', `
                <div class="space-y-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="p-3 flex items-center justify-between">
                        <div class="text-blue-800 font-medium">Select All Suggested Changes</div>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" id="selectAllComparison">
                            <span class="text-sm text-blue-700">Select All</span>
                        </label>
                    </div>
                </div>`);
        }

        // No change state
        if (availableFields.length === 0) {
            html += '<div class="text-center text-gray-500">';
            html += '<p>No changes detected. All AI suggestions match your current content.</p>';
            html += '</div>';
        }

        html += '</div>';
        contentDiv.innerHTML = html;

        // Apply button visibility
        if (applyBtn) {
            if (availableFields.length > 0) applyBtn.classList.remove('hidden');
            else applyBtn.classList.add('hidden');
        }
    }

    async applySelectedComparisonChanges() {
        const changes = this.selectedComparisonChanges || {};
        const keys = Object.keys(changes);
        if (keys.length === 0) {
            this.showError('Please select at least one change to apply');
            return;
        }
        // Ensure SKU is available
        let sku = this.currentItemSku;
        if (!sku) {
            const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
            sku = skuField && (skuField.value || skuField.textContent);
        }
        if (!sku) {
            console.error('No SKU available for saving changes');
            this.showError('Unable to save changes - no item SKU available');
            return;
        }
        try {
            const results = await Promise.all(keys.map(async (fieldKey) => {
                const value = changes[fieldKey];
                const payload = {
                    sku,
                    field: fieldKey === 'title' ? 'suggested_title' : (fieldKey === 'description' ? 'suggested_description' : fieldKey),
                    value
                };
                try {
                    const res = await ApiClient.post('/api/marketing_manager.php?action=update_field', payload);
                    const data = res;
                    if (!data.success) throw new Error(data.error || 'Save failed');
                    return { fieldKey, success: true };
                } catch (e) {
                    console.error(`Error saving ${fieldKey}:`, e);
                    return { fieldKey, success: false, error: e };
                }
            }));
            const successCount = results.filter(r => r.success).length;
            if (successCount > 0) {
                // Update main form fields for title/description only
                keys.forEach((fieldKey) => {
                    if (fieldKey === 'title' || fieldKey === 'description') {
                        const targetField = document.getElementById(fieldKey === 'title' ? 'name' : 'description');
                        if (targetField) {
                            targetField.value = changes[fieldKey];
                            targetField.classList.add('flash-highlight-green-light');
                            setTimeout(() => { targetField.classList.remove('flash-highlight-green-light'); }, 3000);
                        }
                    }
                });
                this.showSuccess(`${successCount} changes saved to database successfully!`);
                this.closeAiComparisonModal();
            } else {
                this.showError('Failed to save changes to database');
            }
        } catch (err) {
            console.error('Error in batch save operation:', err);
            this.showError('Failed to save changes to database');
        }
    }

    applyMarketingToItem() {
        // Apply values from Marketing Manager modal and persist via API
        const sku = this.currentItemSku || document.getElementById('sku')?.value || document.getElementById('skuDisplay')?.textContent;
        if (!sku) {
            this.showError('Unable to apply marketing - no item SKU available');
            return;
        }

        // Gather fields from modal
        const map = {
            marketingTitle: 'suggested_title',
            marketingDescription: 'suggested_description',
            targetAudience: 'target_audience',
            demographics: 'demographic_targeting',
            psychographics: 'psychographic_profile',
            searchIntent: 'search_intent',
            seasonalRelevance: 'seasonal_relevance'
        };

        const payloads = [];
        Object.keys(map).forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            let value = (el.tagName === 'SELECT') ? el.value : (el.value || '');
            if (typeof value === 'string') value = value.trim();
            if (value) {
                payloads.push({ sku, field: map[id], value });
            }
        });

        // Optional: brand voice and content tone (per-SKU)
        const brandVoiceEl = document.getElementById('brandVoice');
        const contentToneEl = document.getElementById('contentTone');
        if (brandVoiceEl && brandVoiceEl.value) payloads.push({ sku, field: 'brand_voice', value: brandVoiceEl.value });
        if (contentToneEl && contentToneEl.value) payloads.push({ sku, field: 'content_tone', value: contentToneEl.value });

        if (payloads.length === 0) {
            this.showError('No marketing content to apply');
            return;
        }

        // Apply to main form fields immediately
        const titleVal = document.getElementById('marketingTitle')?.value || '';
        const descVal = document.getElementById('marketingDescription')?.value || '';
        if (titleVal) {
            const nameField = document.getElementById('name');
            if (nameField) {
                nameField.value = titleVal;
                nameField.classList.add('flash-highlight-green-light');
                setTimeout(() => { nameField.classList.remove('flash-highlight-green-light'); }, 3000);
            }
        }
        if (descVal) {
            const descField = document.getElementById('description');
            if (descField) {
                descField.value = descVal;
                descField.classList.add('flash-highlight-green-light');
                setTimeout(() => { descField.classList.remove('flash-highlight-green-light'); }, 3000);
            }
        }

        // Persist all fields
        Promise.all(payloads.map(async (p) => {
            try {
                const res = await ApiClient.post('/api/marketing_manager.php?action=update_field', p);
                return !!(res && res.success);
            } catch (e) {
                console.error('Error saving field', p.field, e);
                return false;
            }
        })).then((results) => {
            const successCount = results.filter(Boolean).length;
            if (successCount > 0) {
                this.showSuccess(`${successCount} marketing field(s) saved!`);
                if (window.loadExistingMarketingData) {
                    try { window.loadExistingMarketingData(); } catch (_) {}
                }
            } else {
                this.showError('Failed to save marketing fields');
            }
        });
    }

    // ===== Marketing list add/remove handlers =====
    getMarketingListDomMap() {
        // Map backend list fields -> input IDs and list container IDs in the modal
        return {
            selling_points: { inputId: 'newSellingPoint', listId: 'sellingPointsList' },
            competitive_advantages: { inputId: 'newCompetitiveAdvantage', listId: 'competitiveAdvantagesList' },
            customer_benefits: { inputId: 'newCustomerBenefit', listId: 'customerBenefitsList' },
            seo_keywords: { inputId: 'newSEOKeyword', listId: 'seoKeywordsList' },
            call_to_action_suggestions: { inputId: 'newCallToAction', listId: 'callToActionsList' },
            urgency_factors: { inputId: 'newUrgencyFactor', listId: 'urgencyFactorsList' },
            conversion_triggers: { inputId: 'newConversionTrigger', listId: 'conversionTriggersList' },
            // Additional allowed fields (map if present in DOM)
            emotional_triggers: { inputId: 'newEmotionalTriggers', listId: 'emotionalTriggersList' },
            unique_selling_points: { inputId: 'newUniqueSellingPoints', listId: 'uniqueSellingPointsList' },
            value_propositions: { inputId: 'newValuePropositions', listId: 'valuePropositionsList' },
            marketing_channels: { inputId: 'newMarketingChannels', listId: 'marketingChannelsList' },
            social_proof_elements: { inputId: 'newSocialProofElements', listId: 'socialProofElementsList' },
            objection_handlers: { inputId: 'newObjectionHandlers', listId: 'objectionHandlersList' },
            content_themes: { inputId: 'newContentThemes', listId: 'contentThemesList' },
            pain_points_addressed: { inputId: 'newPainPointsAddressed', listId: 'painPointsAddressedList' },
            lifestyle_alignment: { inputId: 'newLifestyleAlignment', listId: 'lifestyleAlignmentList' }
        };
    }

    // Helpers: loading states and duplicates
    normalizeText(str) {
        return (str || '').trim().replace(/\s+/g, ' ').toLowerCase();
    }

    findAddButtonForField(fieldName) {
        if (!fieldName) return null;
        return document.querySelector(`button[data-action="add-list-item"][data-field="${fieldName}"]`);
    }

    startLoadingButton(btn, text) {
        if (!btn) return;
        if (!btn.dataset.originalText) btn.dataset.originalText = btn.innerText;
        btn.innerText = text || 'Working...';
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    }

    stopLoadingButton(btn) {
        if (!btn) return;
        if (btn.dataset.originalText) btn.innerText = btn.dataset.originalText;
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    startLoadingInput(inputEl) {
        if (!inputEl) return;
        if (!inputEl.dataset.originalPlaceholder) inputEl.dataset.originalPlaceholder = inputEl.placeholder || '';
        inputEl.placeholder = 'Working...';
        inputEl.disabled = true;
    }

    stopLoadingInput(inputEl) {
        if (!inputEl) return;
        if (inputEl.dataset.originalPlaceholder !== undefined) inputEl.placeholder = inputEl.dataset.originalPlaceholder;
        inputEl.disabled = false;
    }

    getExistingItemsForList(listId) {
        const set = new Set();
        const listEl = listId ? document.getElementById(listId) : null;
        if (!listEl) return set;
        // Prefer remove buttons' data since it is encoded consistently
        const removeBtns = listEl.querySelectorAll('button[data-action="remove-list-item"][data-item]');
        removeBtns.forEach(btn => {
            try {
                const decoded = decodeURIComponent(btn.getAttribute('data-item') || '');
                if (decoded) set.add(this.normalizeText(decoded));
            } catch (e) { /* ignore malformed */ }
        });
        // Fallback: spans inside our appended structure
        if (set.size === 0) {
            listEl.querySelectorAll('.marketing-list-item span').forEach(span => {
                set.add(this.normalizeText(span.textContent || ''));
            });
        }
        return set;
    }

    appendMarketingListItem(listId, fieldName, itemText) {
        const listEl = document.getElementById(listId);
        if (!listEl) return;
        const itemDiv = document.createElement('div');
        itemDiv.className = 'marketing-list-item flex justify-between items-center bg-white p-2 rounded border';
        itemDiv.innerHTML = `
            <span class="text-sm text-gray-700">${this.escapeHtml(itemText)}</span>
            <button data-action="remove-list-item" data-field="${fieldName}" data-item="${encodeURIComponent(itemText)}" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
        `;
        listEl.appendChild(itemDiv);
        // Highlight newly added
        itemDiv.classList.add('flash-highlight-green-light');
        setTimeout(() => { itemDiv.classList.remove('flash-highlight-green-light'); }, 800);
    }

    async handleAddListItem(fieldName) {
        try {
            if (!fieldName) return;
            // Resolve SKU
            let sku = this.currentItemSku;
            if (!sku) {
                const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
                sku = skuField && (skuField.value || skuField.textContent);
            }
            if (!sku) {
                this.showError('No item selected');
                return;
            }

            const map = this.getMarketingListDomMap();
            const dom = map[fieldName] || {};
            const inputEl = dom.inputId ? document.getElementById(dom.inputId) : null;
            if (!inputEl || !inputEl.value || !inputEl.value.trim()) {
                this.showError('Please enter a value');
                return;
            }
            const value = inputEl.value.trim();
            const listId = dom.listId;
            const existing = this.getExistingItemsForList(listId);
            if (existing.has(this.normalizeText(value))) {
                this.showError('Item already exists');
                return;
            }

            const addBtn = this.findAddButtonForField(fieldName);
            this.startLoadingButton(addBtn, 'Adding...');
            this.startLoadingInput(inputEl);

            const payload = { sku, field: fieldName, item: value };
            const res = await ApiClient.post('/api/marketing_manager.php?action=add_list_item', payload);
            if (!res || !res.success) {
                this.showError((res && res.error) || 'Failed to add item');
                return;
            }
            // Clear input and update UI
            inputEl.value = '';
            if (listId) this.appendMarketingListItem(listId, fieldName, value);
            this.showSuccess('Item added successfully');
        } catch (err) {
            console.error('Error adding list item:', err);
            this.showError('Failed to add item');
        } finally {
            const map = this.getMarketingListDomMap();
            const dom = map[fieldName] || {};
            const inputEl = dom.inputId ? document.getElementById(dom.inputId) : null;
            const addBtn = this.findAddButtonForField(fieldName);
            this.stopLoadingInput(inputEl);
            this.stopLoadingButton(addBtn);
        }
    }

    async handleRemoveListItem(fieldName, itemEncoded, target) {
        try {
            if (!fieldName) return;
            // Resolve SKU
            let sku = this.currentItemSku;
            if (!sku) {
                const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
                sku = skuField && (skuField.value || skuField.textContent);
            }
            if (!sku) {
                this.showError('No item selected');
                return;
            }
            const item = itemEncoded ? decodeURIComponent(itemEncoded) : '';
            if (!item) {
                this.showError('No item specified');
                return;
            }

            this.startLoadingButton(target, 'Removing...');

            const res = await ApiClient.post('/api/marketing_manager.php?action=remove_list_item', { sku, field: fieldName, item });
            if (!res || !res.success) {
                this.showError((res && res.error) || 'Failed to remove item');
                return;
            }
            // Remove UI element
            let itemEl = null;
            if (target instanceof HTMLElement) {
                itemEl = target.closest('.marketing-list-item');
                if (!itemEl) {
                    const btn = target.closest('button');
                    if (btn && btn.parentElement) itemEl = btn.parentElement;
                }
            }
            if (itemEl && itemEl.parentElement) itemEl.parentElement.removeChild(itemEl);
            this.showSuccess('Item removed');
        } catch (err) {
            console.error('Error removing list item:', err);
            this.showError('Failed to remove item');
        } finally {
            this.stopLoadingButton(target);
        }
    }

    async handleSaveMarketingField(fieldName, target) {
        try {
            if (!fieldName) return;
            // Resolve SKU
            let sku = this.currentItemSku;
            if (!sku) {
                const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
                sku = skuField && (skuField.value || skuField.textContent);
            }
            if (!sku) {
                this.showError('No item selected');
                return;
            }

            // Map backend field -> input element id
            const map = {
                search_intent: 'searchIntent',
                seasonal_relevance: 'seasonalRelevance'
            };
            const inputId = map[fieldName] || '';
            const el = inputId ? document.getElementById(inputId) : null;
            const value = el ? (el.tagName === 'SELECT' ? el.value : (el.value || '')).trim() : '';
            if (!value) {
                this.showError('Please enter a value');
                return;
            }

            this.startLoadingButton(target, 'Saving...');

            const res = await ApiClient.post('/api/marketing_manager.php?action=update_field', { sku, field: fieldName, value });
            const data = res;
            if (!data || !data.success) {
                this.showError((data && data.error) || 'Failed to save field');
                return;
            }
            if (el) {
                el.classList.add('flash-highlight-green-light');
                setTimeout(() => { el.classList.remove('flash-highlight-green-light'); }, 800);
            }
            this.showSuccess('Field saved');
        } catch (err) {
            console.error('Error saving marketing field:', err);
            this.showError('Failed to save field');
        } finally {
            this.stopLoadingButton(target);
        }
    }

    async checkAIImageSupport() {
        // Placeholder: assume supported for now; can be wired to a real endpoint later
        return true;
    }

    async handleGenerateAllMarketing(buttonEl) {
        if (!this.currentItemSku) {
            this.showError('No item selected for marketing generation');
            return;
        }
        const originalHtml = buttonEl.innerHTML;
        buttonEl.innerHTML = '<span class="animate-spin">⏳</span> Generating...';
        buttonEl.disabled = true;

        const brandVoice = document.getElementById('brandVoice')?.value || '';
        const contentTone = document.getElementById('contentTone')?.value || '';
        const supportsImages = await this.checkAIImageSupport();
        const itemData = {
            sku: this.currentItemSku,
            name: document.getElementById('name')?.value || '',
            description: document.getElementById('description')?.value || '',
            category: document.getElementById('categoryEdit')?.value || '',
            brandVoice,
            contentTone,
            useImages: !!supportsImages
        };
        try {
            const res = await ApiClient.post('/api/suggest_marketing.php', itemData);
            const data = res;
            if (data.success) {
                this.showSuccess('🎯 AI content generated for: Target Audience, Selling Points, SEO & Keywords, and Conversion tabs!');
                if (window.populateAllMarketingTabs) window.populateAllMarketingTabs(data);
                if (window.loadExistingMarketingData) {
                    await window.loadExistingMarketingData();
                    // restore voice/tone selections
                    const bv = document.getElementById('brandVoice');
                    if (bv && brandVoice) bv.value = brandVoice;
                    const ct = document.getElementById('contentTone');
                    if (ct && contentTone) ct.value = contentTone;
                }
                // mark changes
                if (typeof window.hasMarketingChanges !== 'undefined') window.hasMarketingChanges = true;
                const fieldsToTrack = ['marketingTitle','marketingDescription','targetAudience','demographics','psychographics','brandVoice','contentTone','searchIntent','seasonalRelevance'];
                fieldsToTrack.forEach(id => {
                    const el = document.getElementById(id);
                    if (el && el.value && window.trackMarketingFieldChange) window.trackMarketingFieldChange(id);
                });
                if (window.updateMarketingSaveButtonVisibility) window.updateMarketingSaveButtonVisibility();
            } else {
                this.showError(data.error || 'Failed to generate marketing content');
            }
        } catch (err) {
            console.error('Error generating marketing content:', err);
            this.showError('Failed to generate marketing content');
        } finally {
            buttonEl.innerHTML = originalHtml;
            buttonEl.disabled = false;
        }
    }

    async handleGenerateFreshMarketing(buttonEl) {
        if (!this.currentItemSku) {
            this.showError('No item selected for marketing generation');
            return;
        }
        const nameField = document.getElementById('name');
        if (!nameField || !nameField.value.trim()) {
            this.showError('Item name is required for marketing generation');
            return;
        }
        const originalHtml = buttonEl.innerHTML;
        buttonEl.innerHTML = '🔥 Generating...';
        buttonEl.disabled = true;

        const brandVoiceField = document.getElementById('brandVoice');
        const contentToneField = document.getElementById('contentTone');
        const itemData = {
            sku: this.currentItemSku,
            name: nameField.value.trim(),
            category: document.getElementById('categoryEdit')?.value || '',
            description: document.getElementById('description')?.value.trim() || '',
            brand_voice: brandVoiceField ? brandVoiceField.value : '',
            content_tone: contentToneField ? contentToneField.value : '',
            fresh_start: true
        };
        try {
            const res = await ApiClient.post('/api/suggest_marketing.php', itemData);
            const data = res;
            if (data.success) {
                this.showSuccess('🔥 Fresh marketing content generated! All fields updated with brand new AI suggestions.');
                if (window.populateAllMarketingTabs) window.populateAllMarketingTabs(data);
                if (window.clearMarketingFields) window.clearMarketingFields();
                // Defer reload of fresh data similar to legacy code
                setTimeout(async () => {
                    if (window.loadExistingMarketingData) await window.loadExistingMarketingData();
                }, 50);
            } else {
                this.showError(data.error || 'Failed to generate marketing content');
            }
        } catch (err) {
            console.error('Error generating fresh marketing content:', err);
            this.showError('Failed to generate marketing content');
        } finally {
            buttonEl.innerHTML = originalHtml;
            buttonEl.disabled = false;
        }
    }

    handleApplyAndSaveMarketingTitle() {
        if (window.applyAndSaveMarketingTitle) {
            window.applyAndSaveMarketingTitle();
        } else {
            // Fallback: just apply to #name
            const val = document.getElementById('marketingTitle')?.value || '';
            this.applyTitle(val);
        }
    }

    handleApplyAndSaveMarketingDescription() {
        if (window.applyAndSaveMarketingDescription) {
            window.applyAndSaveMarketingDescription();
        } else {
            const val = document.getElementById('marketingDescription')?.value || '';
            this.applyDescription(val);
        }
    }

    applyTitle(title) {
        const nameField = document.getElementById('name');
        if (!nameField) return;
        nameField.value = title;
        nameField.classList.add('flash-highlight-purple');
        setTimeout(() => { nameField.classList.remove('flash-highlight-purple'); }, 2000);
        this.showSuccess('Title applied! Remember to save your changes.');
    }

    applyDescription(description) {
        const descriptionField = document.getElementById('description');
        if (!descriptionField) return;
        descriptionField.value = description;
        descriptionField.classList.add('flash-highlight-purple');
        setTimeout(() => { descriptionField.classList.remove('flash-highlight-purple'); }, 2000);
        this.showSuccess('Description applied! Remember to save your changes.');
    }

    switchMarketingTab(tabName, buttonEl) {
        // Hide all content
        document.querySelectorAll('.marketing-tab-content').forEach(tab => tab.classList.add('hidden'));
        // Remove active styles from all buttons
        document.querySelectorAll('.marketing-tab-btn').forEach(btn => {
            btn.classList.remove('active', 'border-purple-500', 'text-purple-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });
        // Show selected tab
        const selectedTab = document.getElementById(`tab-${tabName}`);
        if (selectedTab) selectedTab.classList.remove('hidden');
        // Activate selected button
        if (buttonEl) {
            buttonEl.classList.add('active', 'border-purple-500', 'text-purple-600');
            buttonEl.classList.remove('border-transparent', 'text-gray-500');
        }
    }

    showConfirmationModal(title, message, onConfirm) {
        const modal = document.getElementById('confirmationModal');
        document.getElementById('confirmationModalTitle').textContent = title;
        document.getElementById('confirmationModalMessage').textContent = message;
        const confirmBtn = document.getElementById('confirmationModalConfirm');
        const cancelBtn = document.getElementById('confirmationModalCancel');
        const confirmAndClose = () => {
            this.closeConfirmationModal();
            onConfirm();
        };
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        newConfirmBtn.addEventListener('click', confirmAndClose);
        const newCancelBtn = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        newCancelBtn.addEventListener('click', this.closeConfirmationModal.bind(this));
        modal.classList.remove('hidden');
    }

    closeConfirmationModal() {
        const modal = document.getElementById('confirmationModal');
        modal.classList.add('hidden');
    }

    showCostSuggestionChoiceDialog(suggestions) {
        const dialog = document.getElementById('costSuggestionChoiceDialog');
        const container = document.getElementById('costSuggestionChoices');
        container.innerHTML = '';
        if (!suggestions || suggestions.length === 0) {
            container.innerHTML = '<p>No suggestions available.</p>';
        } else {
            suggestions.forEach(suggestion => {
                const suggestionCard = document.createElement('div');
                suggestionCard.className = 'suggestion-card p-4 border rounded-lg hover:bg-gray-50';
                suggestionCard.innerHTML = `
                    <h4 class="font-bold text-lg">${this.escapeHtml(suggestion.model)}</h4>
                    <p class="text-2xl font-light my-2">$${parseFloat(suggestion.suggestedCost).toFixed(2)}</p>
                    <p class="text-sm text-gray-600">Confidence: ${this.escapeHtml(suggestion.confidence)}</p>
                    <p class="text-xs italic text-gray-500 mt-2">${this.escapeHtml(suggestion.reasoning)}</p>
                    <div class="mt-4 flex gap-2">
                        <button data-action="populate-cost-breakdown-from-suggestion" data-suggestion='${this.escapeHtml(JSON.stringify(suggestion))}' class="btn btn-primary flex-1">Apply Breakdown</button>
                        <button data-action="apply-suggested-cost-to-cost-field" data-suggestion='${this.escapeHtml(JSON.stringify(suggestion))}' class="btn btn-secondary flex-1">Apply to Field</button>
                    </div>
                `;
                container.appendChild(suggestionCard);
            });
        }
        dialog.classList.remove('hidden');
    }

    closeCostSuggestionChoiceDialog() {
        const dialog = document.getElementById('costSuggestionChoiceDialog');
        dialog.classList.add('hidden');
    }
    
    applyCostSuggestionToCost() {
        const suggestedCostDisplay = document.getElementById('suggestedCostDisplay');
        const costPriceField = document.getElementById('costPrice');
        if (suggestedCostDisplay && costPriceField) {
            const suggestedCostText = suggestedCostDisplay.textContent.replace('$', '');
            const suggestedCostValue = parseFloat(suggestedCostText) || 0;
            if (suggestedCostValue > 0) {
                costPriceField.value = suggestedCostValue.toFixed(2);
                costPriceField.classList.add('flash-highlight-blue');
                setTimeout(() => { costPriceField.classList.remove('flash-highlight-blue'); }, 2000);
                this.showSuccess('Suggested cost applied to Cost Price field!');
            } else {
                this.showError('No suggested cost available.');
            }
        }
    }

    applySuggestedCostToCostField(button) {
        try {
            const suggestionData = JSON.parse(button.getAttribute('data-suggestion').replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
            const costPriceField = document.getElementById('costPrice');
            if (costPriceField) {
                const suggestedCost = parseFloat(suggestionData.suggestedCost) || 0;
                costPriceField.value = suggestedCost.toFixed(2);
                costPriceField.classList.add('flash-highlight-green');
                setTimeout(() => { costPriceField.classList.remove('flash-highlight-green'); }, 3000);
                this.closeCostSuggestionChoiceDialog();
                this.showSuccess(`AI suggested cost of $${suggestedCost.toFixed(2)} applied!`);
            }
        } catch (error) {
            console.error('Error applying suggested cost:', error);
            this.showError('Error applying suggested cost.');
        }
    }
    
    async populateCostBreakdownFromSuggestion(suggestionData) {
        await this.clearCostBreakdownCompletely();
        const breakdown = suggestionData.breakdown;
        const categories = ['materials', 'labor', 'energy', 'equipment'];
        for (const category of categories) {
            if (breakdown[category] > 0) {
                const itemId = `${category}_${Date.now()}`;
                const itemData = { name: `Suggested ${category}`, cost: breakdown[category] };
                this.costBreakdown[category][itemId] = itemData;
                await this.saveCostItemToDatabase(category, itemData, itemId);
            }
        }
        this.showSuccess('AI cost breakdown has been applied and saved.');
    }
    
    async saveCostItemToDatabase(category, data, itemId) {
         try {
            // Map data to API body
            const body = (category === 'materials')
                ? { name: data.name || `Suggested ${category}`, cost: data.cost }
                : { description: data.name || data.description || `Suggested ${category}`, cost: data.cost };
            const response = await ApiClient.post('/functions/process_cost_breakdown.php', body, { method: 'POST' });
            const result = response;
            if (!result.success) {
                this.showError('Failed to save suggested cost item.');
            } else if (result.data && result.data.id && itemId && this.costBreakdown[category] && this.costBreakdown[category][itemId]) {
                // Update local key to server id
                const saved = this.costBreakdown[category][itemId];
                delete this.costBreakdown[category][itemId];
                this.costBreakdown[category][result.data.id] = saved;
                this.renderCostList(category);
                this.updateTotalsDisplay();
            }
        } catch (error) {
            console.error('Failed to persist suggested cost item:', error);
            this.showError('Failed to persist suggested cost item.');
        }
    }

    async refreshCategoryDropdown() {
        try {
            const newCategories = await ApiClient.get('/api/get_categories.php');
            this.categories = newCategories;
            this.updateCategoryDropdown();
            this.showSuccess('Categories updated!');
        } catch (error) {
            this.showError('Failed to refresh categories.');
            console.error('Refresh categories error:', error);
        }
    }

    updateCategoryDropdown() {
        const dropdown = document.getElementById('categoryEdit');
        if (!dropdown) return;
        const currentVal = dropdown.value;
        dropdown.innerHTML = '<option value="">Select Category</option>';
        this.categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat;
            option.textContent = cat;
            option.selected = (cat === currentVal);
            dropdown.appendChild(option);
        });
        dropdown.value = currentVal;
    }

    escapeHtml(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
        toast.className = `toast ${colors[type]} text-white p-4 rounded-lg shadow-lg mb-2`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showInfo(message) {
        this.showToast(message, 'info');
    }

    // Non-blocking toast with action link (fixed, auto-dismiss)
    showActionToast(message, linkText, href) {
        try {
            let host = document.getElementById('wf-admin-toast-host');
            if (!host) {
                host = document.createElement('div');
                host.id = 'wf-admin-toast-host';
                host.className = 'wf-admin-toast-host';
                document.body.appendChild(host);
            }
            const el = document.createElement('div');
            el.className = 'wf-admin-toast';
            el.innerHTML = `<span>${message}</span>`;
            if (href) {
                const a = document.createElement('a');
                a.href = href;
                a.textContent = linkText || 'Open';
                // Style comes from CSS
                el.appendChild(a);
            }
            host.appendChild(el);
            setTimeout(() => {
                try { el.classList.add('fade-out'); } catch(_) {}
                setTimeout(() => { try { el.remove(); } catch(_) {} }, 350);
            }, 6000);
        } catch(_) {}
    }

    async loadInventoryThumbnails() {
        const thumbnailContainers = document.querySelectorAll('.thumbnail-container');
        if (!thumbnailContainers.length) return;

        const fallbackFetches = [];

        thumbnailContainers.forEach((container) => {
            const sku = container.dataset.sku;
            if (!sku) return;

            const itemData = this.itemsBySku?.get?.(sku);
            const primaryImage = itemData?.primary_image;
            const totalCount = Number(itemData?.image_count ?? 0);

            if (primaryImage && primaryImage.image_path) {
                this.renderThumbnail(container, primaryImage, totalCount);
                return;
            }

            fallbackFetches.push(this.fetchThumbnailForSku(container, sku));
        });

        if (fallbackFetches.length) {
            await Promise.allSettled(fallbackFetches);
        }
    }

    async fetchThumbnailForSku(container, sku) {
        try {
            const data = await ApiClient.get('/api/get_item_images.php', { sku });

            if (data.success && data.images && data.images.length > 0) {
                const primaryImage = data.images.find((img) => img.is_primary) || data.images[0];
                this.renderThumbnail(container, primaryImage, data.images.length);
                if (this.itemsBySku && this.itemsBySku.has(sku)) {
                    const item = this.itemsBySku.get(sku);
                    item.primary_image = primaryImage;
                    item.image_count = data.images.length;
                }
                return;
            }
        } catch (error) {
            console.error(`Failed to load thumbnail for SKU ${sku}:`, error);
        }

        this.renderEmptyThumbnail(container);
    }

    renderThumbnail(container, image, totalCount) {
        container.innerHTML = `
            <div class="wf-thumb-wrap relative w-12 h-12 rounded overflow-hidden bg-gray-100 border">
                <img src="/${image.image_path}" 
                     alt="${image.alt_text || 'Item image'}" 
                     class="w-full h-full object-cover"
                     loading="lazy"
                     decoding="async">
                ${totalCount > 1 ? `<div class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">${totalCount}</div>` : ''}
            </div>
        `;
        // Attach safe error fallback for early method variant as well
        try {
            const imgEl = container.querySelector('img');
            const wrapEl = container.querySelector('.wf-thumb-wrap');
            if (imgEl) {
                imgEl.addEventListener('error', () => {
                    try {
                        if (!container.isConnected) return;
                        const html = '<div class="w-full h-full rounded bg-gray-100 border flex items-center justify-center text-gray-400"><span class="text-lg">📷</span></div>';
                        if (wrapEl) {
                            wrapEl.innerHTML = html;
                        } else {
                            container.innerHTML = html;
                        }
                    } catch (_) {}
                }, { once: true });
            }
            window.loadItemColors = this.loadItemColors.bind(this);
            window.loadItemSizes = this.loadItemSizes.bind(this);
            // Expose suggestion helpers for compatibility with legacy inline calls
            window.loadExistingPriceSuggestion = this.loadExistingPriceSuggestion?.bind?.(this) || this.loadExistingPriceSuggestion;
            window.loadExistingViewPriceSuggestion = this.loadExistingViewPriceSuggestion?.bind?.(this) || this.loadExistingViewPriceSuggestion;
            window.loadExistingMarketingSuggestion = this.loadExistingMarketingSuggestion?.bind?.(this) || this.loadExistingMarketingSuggestion;
            window.displayMarketingSuggestionIndicator = this.displayMarketingSuggestionIndicator?.bind?.(this) || this.displayMarketingSuggestionIndicator;

            // Load editor sections when present
            if (document.getElementById('colorsList')) {
                this.loadItemColors();
            }
            if (document.getElementById('sizesList')) {
                this.loadItemSizes();
            }
            // Ensure SKU is set and initial images load on page load
            const skuField = document.getElementById('skuEdit') || document.getElementById('skuDisplay') || document.getElementById('sku');
            if (skuField && skuField.value) {
                this.currentItemSku = skuField.value;
            }
            if (document.getElementById('currentImagesList') && this.currentItemSku) {
                this.loadCurrentImages(this.currentItemSku, false);
            }
        } catch (e) {
            console.warn('[AdminInventory] Failed to trigger editor loaders from thumbnail render', e);
        }
    }

    renderEmptyThumbnail(container) {
        container.innerHTML = `
            <div class="w-12 h-12 rounded bg-gray-100 border flex items-center justify-center text-gray-400">
                <span class="text-lg">📷</span>
            </div>
        `;
    }

    deriveThumbnailPath(imagePath) {
        if (!imagePath) return null;
        const parts = imagePath.split('/');
        const fileName = parts.pop();
        if (!fileName) return null;

        const dotIndex = fileName.lastIndexOf('.');
        if (dotIndex === -1) return null;

        const name = fileName.slice(0, dotIndex);
        const ext = fileName.slice(dotIndex + 1).toLowerCase();

        if (ext !== 'png' && ext !== 'jpg' && ext !== 'jpeg' && ext !== 'webp') {
            return null;
        }

        const thumbName = `${name}-thumb.${ext}`;
        parts.push(thumbName);
        const candidate = parts.join('/');
        return candidate;
    }

    deriveVariantPath(imagePath, targetExt) {
        if (!imagePath || !targetExt) return null;
        const lower = targetExt.toLowerCase();
        const parts = imagePath.split('.');
        if (parts.length < 2) return null;
        parts[parts.length - 1] = lower;
        return parts.join('.');
    }

    loadModalImages() {
        // Ensure SKU is set and load images for the modal
        const skuField = document.getElementById('skuEdit') || document.getElementById('skuDisplay') || document.getElementById('sku');
        if (skuField && skuField.value) {
            this.currentItemSku = skuField.value;
            console.log('[AdminInventory] Loading images for SKU:', this.currentItemSku);
        }
        
        const imagesList = document.getElementById('currentImagesList');
        if (imagesList && this.currentItemSku) {
            console.log('[AdminInventory] Found currentImagesList, loading images...');
            this.loadCurrentImages(this.currentItemSku, false);
        } else {
            console.log('[AdminInventory] Missing requirements:', {
                imagesList: !!imagesList,
                currentItemSku: this.currentItemSku
            });
        }
    }

    setupInlineEditing() {
        // Add click handlers for editable cells
        document.addEventListener('click', (e) => {
            const cell = e.target.closest && e.target.closest('.editable-field');
            if (!cell) return;
            // Avoid activating if clicking within an interactive control
            if (e.target.closest('input,select,textarea,button,a')) return;
            // Prevent double editors
            if (cell.__editing) return;
            // Prevent other handlers (like view/edit link interceptors) from acting on this click
            try { e.preventDefault(); e.stopPropagation(); } catch(_) {}
            this.startInlineEdit(cell);
        }, true);
    }

    startInlineEdit(cell) {
        const field = cell.dataset.field;
        const row = cell.closest('tr');
        const sku = row.dataset.sku;
        const currentValue = this.extractCellValue(cell, field);
        // Prevent duplicate editors on the same cell
        cell.__editing = true;
        
        // Store original content for cancel
        cell.dataset.originalContent = cell.innerHTML;
        
        // Create appropriate input based on field type
        const input = this.createEditInput(field, currentValue);
        
        // Replace cell content with input
        cell.innerHTML = '';
        cell.appendChild(input);
        
        // Focus and select
        input.focus();
        if (input.select) input.select();
        
        // Handle save/cancel
        const saveEdit = async () => {
            const newValue = input.value.trim();
            if (newValue !== currentValue) {
                const success = await this.saveInlineEdit(sku, field, newValue);
                if (success) {
                    this.finishInlineEdit(cell, field, newValue);
                } else {
                    this.cancelInlineEdit(cell);
                }
            } else {
                this.cancelInlineEdit(cell);
            }
        };
        
        const cancelEdit = () => {
            this.cancelInlineEdit(cell);
        };
        
        // Event listeners
        input.addEventListener('blur', saveEdit);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveEdit();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancelEdit();
            }
        });
    }

    extractCellValue(cell, field) {
        const text = cell.textContent.trim();
        
        // Handle price fields (remove $ symbol)
        if (field === 'costPrice' || field === 'retailPrice') {
            return text.replace('$', '');
        }
        
        return text;
    }

    createEditInput(field, currentValue) {
        let input;
        
        switch (field) {
            case 'category':
                input = document.createElement('select');
                input.className = 'w-full px-2 py-1 border border-blue-300 rounded text-sm';
                
                // Add current categories as options
                const categories = this.categories || [];
                input.innerHTML = '<option value="">Select Category</option>';
                categories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat;
                    option.selected = cat === currentValue;
                    input.appendChild(option);
                });
                
                // Allow typing new category
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !input.value) {
                        const newCategory = prompt('Enter new category name:');
                        if (newCategory) {
                            const option = document.createElement('option');
                            option.value = newCategory;
                            option.textContent = newCategory;
                            option.selected = true;
                            input.appendChild(option);
                        }
                    }
                });
                break;
                
            case 'stockLevel':
            case 'reorderPoint':
                input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.step = '1';
                input.value = currentValue;
                input.className = 'w-full px-2 py-1 border border-blue-300 rounded text-sm';
                break;
                
            case 'costPrice':
            case 'retailPrice':
                input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.step = '0.01';
                input.value = currentValue;
                input.className = 'w-full px-2 py-1 border border-blue-300 rounded text-sm';
                break;
                
            default: // name and other text fields
                input = document.createElement('input');
                input.type = 'text';
                input.value = currentValue;
                input.className = 'w-full px-2 py-1 border border-blue-300 rounded text-sm';
                break;
        }
        
        return input;
    }

    async saveInlineEdit(sku, field, value) {
        try {
            const result = await ApiClient.post('/api/update_item_field.php', {
                sku: sku,
                field: field,
                value: value
            });
            
            if (result.success) {
                this.showSuccess(`${field} updated successfully`);
                return true;
            } else {
                this.showError(result.error || 'Failed to update field');
                return false;
            }
        } catch (error) {
            console.error('Error saving inline edit:', error);
            this.showError('Network error while saving');
            return false;
        }
    }

    finishInlineEdit(cell, field, newValue) {
        // Format the display value
        let displayValue = newValue;
        
        if (field === 'costPrice' || field === 'retailPrice') {
            displayValue = '$' + parseFloat(newValue).toFixed(2);
        }
        
        // Update cell content
        cell.innerHTML = displayValue;
        
        // Remove editing state
        delete cell.dataset.originalContent;
        try { cell.__editing = false; } catch(_) {}
        
        // Add a brief highlight to show the change
        cell.classList.add('bg-green-100');
        setTimeout(() => {
            cell.classList.remove('bg-green-100');
        }, 2000);
    }

    cancelInlineEdit(cell) {
        // Restore original content
        cell.innerHTML = cell.dataset.originalContent;
        delete cell.dataset.originalContent;
        try { cell.__editing = false; } catch(_) {}
    }
}

(function initAdminInventory() {
    function run() {
        const shouldInit =
            document.getElementById('inventoryTable') ||
            document.getElementById('inventoryModalOuter') ||
            document.getElementById('admin-inventory-container') ||
            document.getElementById('inventory-data') ||
            document.getElementById('currentImagesList') ||
            document.getElementById('multiImageUpload') ||
            document.querySelector('[data-action="process-images-ai"]');
        if (!shouldInit || window.adminInventoryModule) return;
        const instance = new AdminInventoryModule();
        try { window.adminInventoryModule = instance; } catch (_) {}
        if (typeof window.showSuccess !== 'function') { window.showSuccess = (msg) => instance.showSuccess(msg); }
        if (typeof window.showError !== 'function') { window.showError = (msg) => instance.showError(msg); }
        if (typeof window.showToast !== 'function') { window.showToast = (msg, type = 'info') => instance.showToast(msg, type); }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }
    // Ensure we catch late server-rendered overlay insertions during hydration
    setTimeout(run, 0);
})();

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

        this.loadData();

        // Wire up item details modal openers for View/Edit actions
        this.registerItemDetailsHandlers();
        // Auto-open if URL contains ?view= or ?edit=
        this.autoOpenItemFromQuery();
        this.bindEvents();
        this.init();

        // Diagnostics and resilience for modal visibility
        document.addEventListener('DOMContentLoaded', () => {
            try {
                const el = document.getElementById('detailedItemModal');
                console.log('[AdminInventory] has#detailedItemModal at load =', !!el);
            } catch(_) {}
            try {
                const mo = new MutationObserver(() => {
                    const el = document.getElementById('detailedItemModal');
                    if (el && !el.classList.contains('show')) {
                        this.__ensureModalVisible(el);
                        console.log('[AdminInventory] Observed #detailedItemModal insertion; applied show');
                    }
                });
                mo.observe(document.body, { childList: true, subtree: true });
                this.__inventoryMo = mo;
            } catch(_) {}
            // If server-rendered admin editor overlay exists, ensure scroll lock and topmost stacking
            try {
                const overlay = document.getElementById('inventoryModalOuter');
                if (overlay) {
                    overlay.classList.add('topmost');
                    document.documentElement.classList.add('modal-open');
                    document.body.classList.add('modal-open');
                }
            } catch(_) {}
        });

        // Legacy shim: allow older inline code to call into the module if present
        try {
            window.buildComparisonInterface = (aiData, currentMarketingData) => this.buildComparisonInterface(aiData, currentMarketingData);
        } catch (_) {}
    }

    /**
     * Attach delegated handlers to open the Detailed Item Modal when clicking
     * the View (👁️) or Edit (✏️) actions in the inventory table.
     */
    registerItemDetailsHandlers() {
        // Option A (server-rendered admin editor): allow normal navigation.
        // Add a defensive fallback: if some other script prevents default on these links,
        // force navigation programmatically so the server editor renders.
        document.addEventListener('click', (e) => {
            const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;
            const href = a.getAttribute('href');
            if (!href) return;
            try {
                const url = new URL(href, window.location.origin);
                if (url.pathname === '/admin/inventory' && (url.searchParams.get('view') || url.searchParams.get('edit'))) {
                    // If primary click without modifiers, ensure navigation occurs
                    const isPrimary = (e.button === 0);
                    const hasMods = !!(e.metaKey || e.ctrlKey || e.shiftKey || e.altKey);
                    const target = (a.getAttribute('target') || '').toLowerCase();
                    const sameWindow = (target === '' || target === '_self');
                    if (isPrimary && !hasMods && sameWindow) {
                        // Force navigation to be resilient against other preventDefault() calls
                        try { window.location.assign(url.toString()); } catch (_) { window.location.href = url.toString(); }
                    }
                }
            } catch (_) { /* ignore URL parse issues */ }
        }, true); // capture to run even if other bubbling listeners stop propagation
    }

    /**
     * On page load, if the current URL already has ?view= or ?edit=, open the modal.
     */
    async autoOpenItemFromQuery() {
        // Option A (server-rendered admin editor): do not auto-open client modal
        // The server template will render the editor when appropriate.
        return;
    }

    /**
     * Fetches rendered HTML for the detailed item modal and shows it.
     * Uses /api/render_detailed_modal.php with the item and its images.
     */
    async openDetailedItemModal(sku) {
        try {
            console.log('[AdminInventory] openDetailedItemModal start', { sku });
            // Fetch item details and images from existing APIs
            const [itemRes, imagesRes] = await Promise.all([
                fetch(`/api/get_item_details.php?sku=${encodeURIComponent(sku)}`),
                fetch(`/api/get_item_images.php?sku=${encodeURIComponent(sku)}`)
            ]);
            console.log('[AdminInventory] fetch statuses', { item: itemRes.status, images: imagesRes.status });
            const itemJson = await itemRes.json();
            const imagesJson = await imagesRes.json();

            const item = itemJson?.item || itemJson || {};
            const images = imagesJson?.images || imagesJson || [];

            // Render server-side modal HTML to keep parity with shop modal
            const modalHtmlRes = await fetch('/api/render_detailed_modal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item, images })
            });
            console.log('[AdminInventory] render_detailed_modal.php status', modalHtmlRes.status);
            const modalHtml = await modalHtmlRes.text();
            if (!modalHtml || !modalHtml.includes('id="detailedItemModal"')) {
                console.warn('[AdminInventory] Modal HTML missing #detailedItemModal');
            }

            // Remove existing instance if present to avoid duplicates
            const existing = document.getElementById('detailedItemModal');
            if (existing && existing.parentElement) existing.parentElement.removeChild(existing);

            // Inject into body and show
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modalEl = document.getElementById('detailedItemModal');
            if (!modalEl) return;
            // Ensure correct visibility classes
            this.__ensureModalVisible(modalEl);
            // Positioning and z-index are defined in CSS (see detailed-item-modal.css)
            console.log('[AdminInventory] detailedItemModal appended and shown');

            // Attach close handlers if not already wired by global script
            modalEl.addEventListener('click', (evt) => {
                if (evt.target === modalEl || evt.target?.dataset?.action === 'closeDetailedModal' || evt.target?.dataset?.action === 'closeDetailedModalOnOverlay') {
                    this.closeDetailedItemModal();
                }
            });
            document.addEventListener('keydown', this.__detailedEscHandler = (evt) => {
                if (evt.key === 'Escape') this.closeDetailedItemModal();
            });
            // Lock scroll while open
            document.documentElement.classList.add('modal-open');
            document.body.classList.add('modal-open');
        } catch (err) {
            console.warn('Failed to open detailed item modal', err);
        }
    }

    __ensureModalVisible(el) {
        try { el.classList.remove('hidden'); } catch(_) {}
        try { el.classList.add('show'); } catch(_) {}
        try { el.style.pointerEvents = 'auto'; } catch(_) {}
    }

    closeDetailedItemModal() {
        const modalEl = document.getElementById('detailedItemModal');
        if (modalEl) {
            modalEl.classList.add('hidden');
            modalEl.classList.remove('show');
            if (modalEl.parentElement) modalEl.parentElement.removeChild(modalEl);
        }
        if (this.__detailedEscHandler) {
            document.removeEventListener('keydown', this.__detailedEscHandler);
            this.__detailedEscHandler = null;
        }
        // Release scroll lock when no other modals are open
        document.documentElement.classList.remove('modal-open');
        document.body.classList.remove('modal-open');
    }

    closeAdminEditor() {
        try {
            const overlay = document.getElementById('inventoryModalOuter');
            if (overlay) {
                overlay.classList.add('hidden');
                if (overlay.parentElement) overlay.parentElement.removeChild(overlay);
            }
        } catch (_) {}
        try {
            document.documentElement.classList.remove('modal-open');
            document.body.classList.remove('modal-open');
        } catch (_) {}
        try {
            const url = new URL(window.location.href);
            url.searchParams.delete('view');
            url.searchParams.delete('edit');
            url.searchParams.delete('add');
            if (url.pathname !== '/admin/inventory') {
                url.pathname = '/admin/inventory';
            }
            window.location.assign(url.toString());
        } catch (_) {
            window.location.assign('/admin/inventory');
        }
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
            const res = await fetch(`/api/item_colors.php?action=get_all_colors&item_sku=${this.currentItemSku}`);
            const data = await res.json();
            if (data.success) {
                this.renderColors(data.colors);
            } else {
                console.error('Error loading colors:', data.message);
                this.renderColors([]);
            }
        } catch (e) {
            console.error('Error fetching colors:', e);
            this.renderColors([]);
        }
    }

    renderColors(colors) {
        const colorsList = document.getElementById('colorsList');
        const colorsLoading = document.getElementById('colorsLoading');
        if (colorsLoading) colorsLoading.classList.add('hidden');
        if (!colorsList) return;
        if (!Array.isArray(colors) || colors.length === 0) {
            colorsList.innerHTML = '<div class="text-center text-gray-500 text-sm">No colors defined. Click "Add Color" to get started.</div>';
            return;
        }
        const activeColors = colors.filter(c => c.is_active == 1);
        const totalColorStock = activeColors.reduce((sum, c) => sum + (parseInt(c.stock_level, 10) || 0), 0);
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
            return `
                <div class="color-item flex items-center justify-between border border-gray-200 rounded-lg ${activeClass}">
                    <div class="flex items-center space-x-3">
                        <div class="color-swatch w-8 h-8 rounded-full border-2 border-gray-300" ${color.color_code ? `data-color="${color.color_code}"` : ''}></div>
                        <div>
                            <div class="font-medium text-gray-800">${color.color_name}${activeText}</div>
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
                    <button type="button" class="modal-close" data-action="close-color-modal">&times;</button>
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
                                        <a href="#" data-action="open-global-colors-management" class="text-blue-600 hover:text-blue-800">⚙️ Manage Global Colors in Settings</a>
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
                            <button type="submit" class="text-white rounded transition-colors bg-[#87ac3a] hover:bg-[#6b8e23]">Save Color</button>
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
            const response = await fetch(`/api/get_item_images.php?sku=${this.currentItemSku}`);
            const data = await response.json();
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
            const previewSrc = selectedImagePath.startsWith('/images/items/') || selectedImagePath.startsWith('images/items/')
                ? selectedImagePath
                : `/images/items/${selectedImagePath}`;
            imagePreview.src = previewSrc;
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
            const response = await fetch('/api/global_color_size_management.php?action=get_global_colors');
            const data = await response.json();
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
            const response = await fetch(url);
            const data = await response.json();
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
                html += `
                    <div class="size-item flex items-center justify-between border border-gray-200 rounded-lg ${activeClass} ml-${Object.keys(grouped).length > 1 ? '4' : '0'}">
                        <div class="flex items-center space-x-3">
                            <div class="size-badge bg-blue-100 text-blue-800 rounded text-sm font-medium">${size.size_code}</div>
                            <div>
                                <div class="font-medium text-gray-800">${size.size_name}${activeText}${priceAdjustmentText}</div>
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
            const response = await fetch(`/api/item_colors.php?action=get_all_colors&item_sku=${this.currentItemSku}`);
            const data = await response.json();
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
            const response = await fetch(`/api/item_colors.php?action=get_all_colors&item_sku=${this.currentItemSku}`);
            const data = await response.json();
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
                    <button type="button" data-action="close-size-modal" class="modal-close">&times;</button>
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
                            <button type="submit" class="text-white rounded transition-colors bg-[#87ac3a] hover:bg-[#6b8e23]">Save Size</button>
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
                console.error("Error parsing inventory data:", e);
            }
        }
    }

    init() {
        this.renderCostList('materials');
        this.renderCostList('labor');
        this.renderCostList('energy');
        this.renderCostList('equipment');
        this.updateTotalsDisplay();
        this.loadExistingPriceSuggestion(this.currentItemSku);
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
        const skuField = document.getElementById('skuEdit') || document.getElementById('skuDisplay') || document.getElementById('sku');
        if (skuField && skuField.value) {
            this.currentItemSku = skuField.value;
        }
        if (document.getElementById('currentImagesList') && this.currentItemSku) {
            this.loadCurrentImages(this.currentItemSku, false);
        }
    }

    bindEvents() {
        document.body.addEventListener('click', this.handleDelegatedClick.bind(this));
        // Delegated hover for pricing tooltips
        document.body.addEventListener('mouseover', this.handleDelegatedMouseOver.bind(this));
        document.body.addEventListener('mouseout', this.handleDelegatedMouseOut.bind(this));
        document.addEventListener('keydown', this.handleKeyDown.bind(this));
        // Enter-to-add support for list inputs
        document.body.addEventListener('keydown', this.handleDelegatedKeydown.bind(this));

        // Delegated form submits (capture to intercept before default)
        document.body.addEventListener('submit', this.handleDelegatedSubmit.bind(this), true);
        // Delegated change events (for selects/inputs)
        document.body.addEventListener('change', this.handleDelegatedChange.bind(this));

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
                    if (modal) modal.classList.add('show');
                }
                break;
            case 'close-delete-modal':
                {
                    const modal = document.getElementById('deleteConfirmModal');
                    if (modal) modal.classList.remove('show');
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
                    const targetId = target.dataset.target || 'imageUpload';
                    const input = document.getElementById(targetId);
                    if (input && typeof input.click === 'function') {
                        input.click();
                    } else {
                        console.warn('Upload input not found for target:', targetId);
                    }
                }
                break;
            case 'process-images-ai':
                this.processExistingImagesWithAI();
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
                this.getCostSuggestion();
                break;
            case 'get-price-suggestion':
                this.getPriceSuggestion();
                break;
            case 'apply-price-suggestion':
                this.applyPriceSuggestion();
                break;
            case 'clear-price-suggestion':
                this.clearPriceSuggestion();
                break;
            case 'open-marketing-manager':
                this.openMarketingManager();
                break;
            case 'generate-marketing-copy':
                this.generateMarketingCopy();
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
            case 'close-admin-editor-on-overlay':
                // Only close if the actual click target is the overlay itself (backdrop)
                if (target && target.id === 'inventoryModalOuter' && event && event.target === target) {
                    try { event.preventDefault(); } catch (_) {}
                    this.closeAdminEditor();
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
                }
            }
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
            const response = await fetch('/api/item_sizes.php?action=sync_stock', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_sku: this.currentItemSku })
            });
            const data = await response.json();
            if (data.success) {
                this.showSuccess(`Stock synchronized - Total: ${data.new_total_stock}`);
                const stockField = document.getElementById('stockLevel');
                if (stockField && data.new_total_stock !== undefined) {
                    stockField.value = data.new_total_stock;
                }
                this.loadItemSizes();
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
            const response = await fetch(`/api/item_colors.php?action=${isEdit ? 'update_color' : 'add_color'}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(colorData)
            });
            const data = await response.json();
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
                    <button type="button" data-action="close-color-template-modal" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
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
            const response = await fetch('/api/color_templates.php?action=get_all');
            const data = await response.json();
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
            const response = await fetch(`/api/color_templates.php?action=get_template&template_id=${templateId}`);
            const data = await response.json();
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
            const response = await fetch('/api/color_templates.php?action=apply_to_item', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    template_id: parseInt(templateId, 10),
                    item_sku: this.currentItemSku,
                    replace_existing: !!replaceExisting,
                    default_stock: defaultStock
                })
            });
            const data = await response.json();
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
                    <button type="button" data-action="close-size-template-modal" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
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
            const response = await fetch('/api/size_templates.php?action=get_all');
            const data = await response.json();
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
            const response = await fetch(`/api/size_templates.php?action=get_template&template_id=${templateId}`);
            const data = await response.json();
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
            const response = await fetch(`/api/item_colors.php?action=get_all_colors&item_sku=${this.currentItemSku}`);
            const data = await response.json();
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
            const response = await fetch('/api/size_templates.php?action=apply_to_item', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    template_id: parseInt(templateId, 10),
                    item_sku: this.currentItemSku,
                    apply_mode: applyMode,
                    color_id: colorId ? parseInt(colorId, 10) : null,
                    replace_existing: !!replaceExisting,
                    default_stock: defaultStock
                })
            });
            const data = await response.json();
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
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(updateData)
                });
                const data = await response.json();
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
                    const response = await fetch('/api/item_colors.php?action=delete_color', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ color_id: colorId })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.showSuccess('Color deleted successfully');
                        if (typeof window.loadItemColors === 'function') window.loadItemColors();
                    } else {
                        this.showError('Error deleting color: ' + (data.message || ''));
                    }
                } catch (e) {
                    console.error('Error deleting color:', e);
                    this.showError('Error deleting color');
                }
            }
        );
    }

    closeSizeModal() {
        const modal = document.getElementById('sizeModal');
        if (modal) {
            modal.classList.add('hidden');
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
            const response = await fetch(`/api/item_sizes.php?action=${isEdit ? 'update_size' : 'add_size'}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(sizeData)
            });
            const data = await response.json();
            if (data.success) {
                this.showSuccess(`Size ${isEdit ? 'updated' : 'added'} successfully${data.new_total_stock ? ` - Total stock: ${data.new_total_stock}` : ''}`);
                this.closeSizeModal();
                if (typeof window.loadItemSizes === 'function') window.loadItemSizes();
                const stockField = document.getElementById('stockLevel');
                if (stockField && data.new_total_stock !== undefined) {
                    stockField.value = data.new_total_stock;
                }
            } else {
                this.showError('Error saving size: ' + (data.message || ''));
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
                    const response = await fetch('/api/item_sizes.php?action=delete_size', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ size_id: sizeId })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.showSuccess('Size deleted successfully');
                        if (typeof window.loadItemSizes === 'function') window.loadItemSizes();
                        if (data.new_total_stock !== undefined) {
                            const stockField = document.getElementById('stockLevel');
                            if (stockField) stockField.value = data.new_total_stock;
                        }
                    } else {
                        this.showError('Error deleting size: ' + (data.message || ''));
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
            const response = await fetch(url);
            const data = await response.json();
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
        const input = event.target;
        const files = input.files;
        if (!files || files.length === 0) return;

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
            const result = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '/functions/process_multi_image_upload.php');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.onprogress = (e) => {
                    if (!progressBar || !e.lengthComputable) return;
                    const percent = Math.min(100, Math.round((e.loaded / e.total) * 100));
                    // apply dynamic width class
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
                    ensureWidthClass(progressBar, percent);
                };

                xhr.onload = () => {
                    if (progressBar) {
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
                        ensureWidthClass(progressBar, 100);
                    }
                    try {
                        const json = JSON.parse(xhr.responseText || '{}');
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve(json);
                        } else {
                            reject(new Error(json.error || `HTTP ${xhr.status}`));
                        }
                    } catch (e) {
                        console.error('Invalid JSON from upload:', e, xhr.responseText);
                        reject(new Error('Server returned invalid response'));
                    }
                };

                xhr.onerror = () => {
                    reject(new Error('Network error during upload'));
                };

                xhr.send(formData);
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
        if (!targetSku) return;
        try {
            const response = await fetch(`/api/get_item_images.php?sku=${encodeURIComponent(targetSku)}`);
            const data = await response.json();
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
        grid.className = 'grid grid-cols-3 gap-3';

        images.forEach((image) => {
            const card = document.createElement('div');
            card.className = 'bg-white border rounded-lg overflow-hidden shadow-sm';
            card.innerHTML = `
                <div class="relative">
                    <img src="${image.image_path}" alt="${image.alt_text || ''}" class="w-full h-32 object-contain bg-gray-50">
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
            const response = await fetch('/api/set_primary_image.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ imageId, sku })
            });
            const data = await response.json();
            if (data.success) {
                this.showSuccess('Primary image updated');
                await this.loadCurrentImages(sku, false);
            } else {
                this.showError(data.error || 'Failed to set primary image');
            }
        } catch (e) {
            console.error('setPrimaryImage error:', e);
            this.showError('Failed to set primary image');
        }
    }

    async deleteImage(imageId) {
        try {
            const response = await fetch('/api/delete_item_image.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ imageId })
            });
            const data = await response.json();
            if (data.success) {
                this.showSuccess(data.message || 'Image deleted');
                const sku = this.currentItemSku || (document.getElementById('skuEdit')?.value || document.getElementById('skuDisplay')?.value || '');
                if (sku) await this.loadCurrentImages(sku, false);
            } else {
                this.showError(data.error || 'Failed to delete image');
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

                const response = await fetch(`/api/run_image_analysis.php?sku=${encodeURIComponent(sku)}`, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                const result = await response.json();
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
        modalTitle.textContent = itemId ? `Edit ${category} Item` : `Add ${category} Item`;
        if (itemId && this.costBreakdown[category] && this.costBreakdown[category][itemId]) {
            const item = this.costBreakdown[category][itemId];
            costNameField.value = item.name;
            costValueField.value = item.cost;
        } else {
            costNameField.value = '';
            costValueField.value = '';
        }
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
        const itemId = id || `${category}_${Date.now()}`;
        const itemData = { name, cost };
        if (!this.costBreakdown[category]) this.costBreakdown[category] = {};
        this.costBreakdown[category][itemId] = itemData;
        this.renderCostList(category);
        this.updateTotalsDisplay();
        this.closeCostModal();
        try {
            const response = await fetch('/functions/process_cost_breakdown.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: id ? 'update' : 'add', inventory_id: this.currentItemSku, category, item_id: itemId, data: itemData })
            });
            const result = await response.json();
            if (!result.success) {
                this.showError('Failed to save item. Reverting changes.');
                delete this.costBreakdown[category][itemId];
                this.renderCostList(category);
                this.updateTotalsDisplay();
            }
        } catch (error) {
            this.showError('An error occurred while saving.');
            console.error('Save cost item error:', error);
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
            const response = await fetch('/functions/process_cost_breakdown.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', inventory_id: this.currentItemSku, category, item_id: itemId })
            });
            const result = await response.json();
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
            if (modal) modal.classList.remove('show');
            const res = await fetch(`/functions/process_inventory_update.php?action=delete&sku=${encodeURIComponent(sku)}`, {
                method: 'DELETE',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
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
            itemRow.innerHTML = `
                <span>${this.escapeHtml(item.name)}</span>
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
        document.getElementById('materialTotal').textContent = `$${materialTotal.toFixed(2)}`;
        document.getElementById('laborTotal').textContent = `$${laborTotal.toFixed(2)}`;
        document.getElementById('energyTotal').textContent = `$${energyTotal.toFixed(2)}`;
        document.getElementById('equipmentTotal').textContent = `$${equipmentTotal.toFixed(2)}`;
        document.getElementById('totalCost').textContent = `$${totalCost.toFixed(2)}`;
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
            const response = await fetch('/functions/process_cost_breakdown.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_all', inventory_id: this.currentItemSku })
            });
            const result = await response.json();
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
            const response = await fetch(`/api/suggest_cost.php?sku=${this.currentItemSku}`);
            const data = await response.json();
            if (data.success) {
                this.showCostSuggestionChoiceDialog(data.suggestions);
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
            category: document.getElementById('category').value,
            costPrice: document.getElementById('costPrice').value,
            sku: this.currentItemSku,
            useImages: true
        };
        try {
            const response = await fetch('/api/suggest_price.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(itemData)
            });
            const data = await response.json();
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

    displayPriceSuggestion(data) {
        const display = document.getElementById('priceSuggestionDisplay');
        const placeholder = document.getElementById('priceSuggestionPlaceholder');
        if (!display || !placeholder) return;
        placeholder.classList.add('hidden');
        display.classList.remove('hidden');
        document.getElementById('displaySuggestedPrice').textContent = `$${parseFloat(data.suggestedPrice).toFixed(2)}`;
        document.getElementById('displayConfidence').textContent = `${data.confidence || 'N/A'}`;
        document.getElementById('displayTimestamp').textContent = new Date(data.createdAt).toLocaleString();
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
            const response = await fetch(`/api/get_price_suggestion.php?sku=${sku}`);
            const data = await response.json();
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
            const response = await fetch(`/api/get_price_suggestion.php?sku=${encodeURIComponent(sku)}&_t=${Date.now()}`);
            const data = await response.json();
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
            const response = await fetch(`/api/get_marketing_suggestion.php?sku=${sku}`);
            const data = await response.json();
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
            const res = await fetch('/api/suggest_marketing.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(itemData)
            });
            const data = await res.json();
            if (!data || !data.success) {
                this.showError((data && data.error) || 'Failed to generate marketing content');
                this.closeAiComparisonModal();
                return;
            }

            // Store globally for comparison selection helpers
            window.aiComparisonData = data;

            // Populate comparison UI using module builder
            const buildUI = async () => {
                try {
                    const resp = await fetch(`/api/marketing_manager.php?action=get_marketing_data&sku=${encodeURIComponent(sku)}&_t=${Date.now()}`);
                    const current = await resp.json();
                    this.buildComparisonInterface(data, current?.data || null);
                } catch (e) {
                    this.buildComparisonInterface(data, null);
                }
            };
            if (window.collapseAIProgressSection) {
                try { window.collapseAIProgressSection(); } catch (_) {}
            }
            await buildUI();
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
        if (skuEl && this.currentItemSku) skuEl.textContent = this.currentItemSku;
        modal.classList.remove('hidden');
    }

    closeMarketingManager() {
        const modal = document.getElementById('marketingManagerModal');
        if (modal) modal.classList.add('hidden');
    }

    closeAiComparisonModal() {
        const modal = document.getElementById('aiComparisonModal');
        if (modal) modal.classList.add('hidden');
    }

    ensureAiComparisonModal() {
        if (document.getElementById('aiComparisonModal')) return;
        const modal = document.createElement('div');
        modal.id = 'aiComparisonModal';
        modal.className = 'fixed inset-0 z-50 hidden';
        modal.innerHTML = `
            <div class="absolute inset-0 bg-black bg-opacity-40" data-action="close-ai-comparison"></div>
            <div class="relative mx-auto my-8 w-11/12 max-w-4xl bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="border-b px-4 py-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">AI Content Comparison</h3>
                    <button type="button" class="text-gray-500 hover:text-gray-700" data-action="close-ai-comparison">✕</button>
                </div>
                <div class="p-4 space-y-4">
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
            const response = await fetch('/api/website_config.php?action=update_marketing_defaults', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(updateData)
            });
            const data = await response.json();
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
                    const res = await fetch('/api/marketing_manager.php?action=update_field', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
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
                const res = await fetch('/api/marketing_manager.php?action=update_field', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(p)
                });
                const data = await res.json();
                return !!(data && data.success);
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

            const res = await fetch('/api/marketing_manager.php?action=add_list_item', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ sku, field: fieldName, item: value })
            });
            const data = await res.json();
            if (!data || !data.success) {
                this.showError((data && data.error) || 'Failed to add item');
                return;
            }
            // Clear input and update UI
            inputEl.value = '';
            if (listId) this.appendMarketingListItem(listId, fieldName, value);
            this.showSuccess('Item added successfully');
        } catch (err) {
            console.error('Error adding list item:', err);
            this.showError('Failed to add item');
        }
        finally {
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

            const res = await fetch('/api/marketing_manager.php?action=remove_list_item', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ sku, field: fieldName, item })
            });
            const data = await res.json();
            if (!data || !data.success) {
                this.showError((data && data.error) || 'Failed to remove item');
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
        }
        finally {
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

            const res = await fetch('/api/marketing_manager.php?action=update_field', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ sku, field: fieldName, value })
            });
            const data = await res.json();
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
        }
        finally {
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
            useImages: supportsImages
        };
        try {
            const res = await fetch('/api/suggest_marketing.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(itemData)
            });
            const data = await res.json();
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
            const res = await fetch('/api/suggest_marketing.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(itemData)
            });
            const data = await res.json();
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
        this.init();
        this.showSuccess('AI cost breakdown has been applied and saved.');
    }
    
    async saveCostItemToDatabase(category, data, itemId) {
         try {
            const response = await fetch('/functions/process_cost_breakdown.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', inventory_id: this.currentItemSku, category, item_id: itemId, data })
            });
            const result = await response.json();
            if (!result.success) {
                this.showError(`Failed to save ${category} item.`);
            }
        } catch (error) {
            this.showError(`An error occurred while saving ${category}.`);
        }
    }

    async refreshCategoryDropdown() {
        try {
            const response = await fetch('/api/get_categories.php');
            const newCategories = await response.json();
            this.categories = newCategories;
            this.updateCategoryDropdown();
            this.showSuccess('Categories updated!');
        } catch (error) {
            this.showError('Failed to refresh categories.');
            console.error('Refresh categories error:', error);
        }
    }

    updateCategoryDropdown() {
        const dropdown = document.getElementById('category');
        if (!dropdown) return;
        const currentVal = dropdown.value;
        dropdown.innerHTML = '';
        this.categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
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
}

document.addEventListener('DOMContentLoaded', () => {
    const shouldInit =
        document.getElementById('admin-inventory-container') ||
        document.getElementById('inventory-data') ||
        document.getElementById('currentImagesList') ||
        document.getElementById('multiImageUpload') ||
        document.querySelector('[data-action="process-images-ai"]');
    if (shouldInit) {
        const instance = new AdminInventoryModule();
        try { window.adminInventoryModule = instance; } catch (_) {}
        if (typeof window.showSuccess !== 'function') {
            window.showSuccess = (msg) => instance.showSuccess(msg);
        }
        if (typeof window.showError !== 'function') {
            window.showError = (msg) => instance.showError(msg);
        }
        if (typeof window.showToast !== 'function') {
            window.showToast = (msg, type = 'info') => instance.showToast(msg, type);
        }
    }
});

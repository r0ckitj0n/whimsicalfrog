<?php
// components/admin_item_editor.php
// Server-rendered Admin Item Editor (Option A)
// Adapted from archived implementation (2025-08-27/sections/admin_inventory.php)

if (!function_exists('renderAdminItemEditor')) {
    /**
     * Render the admin item editor UI.
     * @param string $mode 'edit', 'add', or 'view'
     * @param array|null $editItem Item row or partial fields
     * @param array $categories List of category names
     * @param array $field_errors Field names with validation errors
     */
    function renderAdminItemEditor(string $mode, ?array $editItem, array $categories, array $field_errors = [], ?string $prevSku = null, ?string $nextSku = null)
    {
        $isEdit = ($mode === 'edit');
        $isView = ($mode === 'view');
        $isReadOnly = $isView;
        $pageUrl = '/admin/inventory';
        $sku = htmlspecialchars($editItem['sku'] ?? '', ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($editItem['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $category = htmlspecialchars($editItem['category'] ?? '', ENT_QUOTES, 'UTF-8');
        $gender = htmlspecialchars($editItem['gender'] ?? '', ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars($editItem['status'] ?? 'draft', ENT_QUOTES, 'UTF-8');
        $stockLevel = htmlspecialchars((string)($editItem['stockLevel'] ?? '0'), ENT_QUOTES, 'UTF-8');
        $reorderPoint = htmlspecialchars((string)($editItem['reorderPoint'] ?? '5'), ENT_QUOTES, 'UTF-8');
        $costPrice = htmlspecialchars(number_format((float)($editItem['costPrice'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8');
        $retailPrice = htmlspecialchars(number_format((float)($editItem['retailPrice'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($editItem['description'] ?? '', ENT_QUOTES, 'UTF-8');
        ?>

        <div id="inventoryModalOuter" class="admin-modal-overlay wf-overlay-viewport over-header topmost show">
            <?php $modeParam = $isEdit ? 'edit' : ($isView ? 'view' : ''); $linkBase = $_GET; unset($linkBase['view'],$linkBase['edit'],$linkBase['add']);
                $selfSku = ($editItem['sku'] ?? '');
                $prevHref = $modeParam ? ('/admin/inventory?' . http_build_query(array_merge($linkBase, [$modeParam => ($prevSku ?: $selfSku)]))) : null;
                $nextHref = $modeParam ? ('/admin/inventory?' . http_build_query(array_merge($linkBase, [$modeParam => ($nextSku ?: $selfSku)]))) : null;
            ?>
            <a href="<?= htmlspecialchars($prevHref ?: '#') ?>" class="nav-arrow nav-arrow-left wf-nav-arrow wf-nav-left" title="Previous item" aria-label="Previous item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <a href="<?= htmlspecialchars($nextHref ?: '#') ?>" class="nav-arrow nav-arrow-right wf-nav-arrow wf-nav-right" title="Next item" aria-label="Next item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 5l7 7-7 7" />
                </svg>
            </a>
            <div class="admin-modal admin-modal-content admin-modal--actions-in-header wf-admin-panel-visible show bg-white rounded-lg shadow-xl w-full max-w-5xl">
                <div class="modal-header flex items-center justify-between gap-3 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-green-700">
                        <?= $isView ? 'View Item' : ($isEdit ? 'Edit Item' : 'Add New Inventory Item') ?><?= ($isEdit || $isView) && $name ? ' (' . $name . ')' : '' ?>
                    </h2>
                    <?php if ($isView && $sku): $editHref = '/admin/inventory?' . http_build_query(array_merge($linkBase, ['edit' => ($editItem['sku'] ?? '')])); ?>
                        <a href="<?= htmlspecialchars($editHref) ?>" class="btn btn-primary btn-sm modal-action-edit" title="Edit Item">Edit</a>
                    <?php elseif ($isEdit): ?>
                        <button type="submit" class="btn btn-primary btn-sm" form="inventoryForm" data-action="save-inventory">Save</button>
                    <?php endif; ?>
                    <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-editor" aria-label="Close">×</button>
                </div>

                <div class="modal-body">
            <form id="inventoryForm" method="POST" action="#" enctype="multipart/form-data" class="wf-modal-form">
                <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'add' ?>">
                <?php if ($isEdit && $sku): ?>
                    <input type="hidden" name="itemSku" value="<?= $sku ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">
                    <!-- Left: Item Information -->
                    <div class="modal-section">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                            <span class="mr-2">📝</span> Item Information
                        </h3>

                        <div class="space-y-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div>
                                    <label for="skuEdit" class="block text-gray-700">SKU *</label>
                                    <input type="text" id="skuEdit" name="sku" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('sku', $field_errors) ? 'field-error-highlight' : '' ?>" required value="<?= $sku ?>"<?= $isReadOnly ? ' readonly' : '' ?>>
                                </div>
                                <div>
                                    <label for="name" class="block text-gray-700">Name *</label>
                                    <input type="text" id="name" name="name" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('name', $field_errors) ? 'field-error-highlight' : '' ?>" required value="<?= $name ?>"<?= $isReadOnly ? ' readonly' : '' ?>>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div>
                                    <label for="categoryEdit" class="block text-gray-700">Category *</nlabel>
                                    <select id="categoryEdit" name="category" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('category', $field_errors) ? 'field-error-highlight' : '' ?>" required<?= $isReadOnly ? ' disabled' : '' ?>>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): $c = htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                                            <option value="<?= $c ?>" <?= ($category === $c) ? 'selected' : '' ?>><?= $c ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="statusEdit" class="block text-gray-700">Status *</label>
                                    <select id="statusEdit" name="status" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('status', $field_errors) ? 'field-error-highlight' : '' ?>" required<?= $isReadOnly ? ' disabled' : '' ?>>
                                        <option value="draft" <?= ($status === 'draft') ? 'selected' : '' ?>>Draft (Hidden)</option>
                                        <option value="live" <?= ($status === 'live') ? 'selected' : '' ?>>Live (Public)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div>
                                    <label for="stockLevel" class="block text-gray-700">Stock Level *</label>
                                    <input type="number" id="stockLevel" name="stockLevel" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('stockLevel', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required value="<?= $stockLevel ?>"<?= $isReadOnly ? ' readonly' : '' ?>>
                                </div>
                                <div>
                                    <label for="reorderPoint" class="block text-gray-700">Reorder Point *</label>
                                    <input type="number" id="reorderPoint" name="reorderPoint" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('reorderPoint', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required value="<?= $reorderPoint ?>"<?= $isReadOnly ? ' readonly' : '' ?>>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div>
                                    <label for="costPrice" class="block text-gray-700">Cost Price ($) *</label>
                                    <input type="number" id="costPrice" name="costPrice" step="0.01" min="0" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('costPrice', $field_errors) ? 'field-error-highlight' : '' ?>" required value="<?= $costPrice ?>"<?= $isReadOnly ? ' readonly' : '' ?>>
                                </div>
                                <div>
                                    <label for="retailPrice" class="block text-gray-700">Retail Price ($) *</label>
                                    <input type="number" id="retailPrice" name="retailPrice" step="0.01" min="0" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('retailPrice', $field_errors) ? 'field-error-highlight' : '' ?>" required value="<?= $retailPrice ?>"<?= $isReadOnly ? ' readonly' : '' ?>>
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-gray-700">Description</label>
                                <textarea id="description" name="description" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded"<?= $isReadOnly ? ' readonly' : '' ?>><?= $description ?></textarea>
                            </div>

                            <?php if (!$isView): ?>
                            <div class="flex justify-end">
                                <button type="button" data-action="open-marketing-manager" class="brand-button px-3 py-2 rounded text-sm">
                                    🎯 Marketing Manager
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right: Cost & Price Analysis (placeholders; JS renders details) -->
                    <div class="modal-section">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                            <span class="mr-2">💰</span> Cost & Price Analysis
                        </h3>
                        <div class="suggestions-container">
                            <div class="cost-breakdown-wrapper">
                                <h4 class="font-semibold text-gray-700 mb-1 text-sm">Cost Breakdown</h4>
                                <?php if (!$isView): ?>
                                <div class="flex items-center gap-2 mb-2">
                                    <button type="button" class="px-2 py-1 bg-purple-600 text-white rounded text-xs hover:bg-purple-700" data-action="get-cost-suggestion">✨ AI Suggest Cost</button>
                                    <button type="button" class="px-2 py-1 bg-purple-600 text-white rounded text-xs hover:bg-purple-700" data-action="get-price-suggestion">✨ AI Generate Price</button>
                                    <button type="button" class="px-2 py-1 bg-purple-600 text-white rounded text-xs hover:bg-purple-700" id="open-marketing-manager-btn" data-action="open-marketing-manager">🎯 AI Marketing</button>
                                    <button type="button" class="px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600" data-action="clear-cost-breakdown">Clear All</button>
                                </div>
                                <?php endif; ?>
                                <div class="mb-2 p-2 bg-green-50 rounded border border-green-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-green-700 font-medium">Suggested Cost:</span>
                                        <span class="font-bold text-green-800 text-lg" id="suggestedCostDisplay">$0.00</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <div>
                                        <div class="flex justify-between items-center">
                                            <h5 class="font-medium text-gray-700 text-sm">Materials</h5>
                                            <?php if (!$isView): ?>
                                            <button type="button" class="text-xs text-blue-600 hover:text-blue-800" data-action="open-cost-modal" data-category="materials">+ Add</button>
                                            <?php endif; ?>
                                        </div>
                                        <div id="materialsList" class="min-h-[2rem] border border-gray-100 rounded p-2"></div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center">
                                            <h5 class="font-medium text-gray-700 text-sm">Labor</h5>
                                            <?php if (!$isView): ?>
                                            <button type="button" class="text-xs text-blue-600 hover:text-blue-800" data-action="open-cost-modal" data-category="labor">+ Add</button>
                                            <?php endif; ?>
                                        </div>
                                        <div id="laborList" class="min-h-[2rem] border border-gray-100 rounded p-2"></div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center">
                                            <h5 class="font-medium text-gray-700 text-sm">Energy</h5>
                                            <?php if (!$isView): ?>
                                            <button type="button" class="text-xs text-blue-600 hover:text-blue-800" data-action="open-cost-modal" data-category="energy">+ Add</button>
                                            <?php endif; ?>
                                        </div>
                                        <div id="energyList" class="min-h-[2rem] border border-gray-100 rounded p-2"></div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-center">
                                            <h5 class="font-medium text-gray-700 text-sm">Equipment</h5>
                                            <?php if (!$isView): ?>
                                            <button type="button" class="text-xs text-blue-600 hover:text-blue-800" data-action="open-cost-modal" data-category="equipment">+ Add</button>
                                            <?php endif; ?>
                                        </div>
                                        <div id="equipmentList" class="min-h-[2rem] border border-gray-100 rounded p-2"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="price-suggestion-wrapper mt-2">
                                <h4 class="font-semibold text-gray-700 mb-1 text-sm">Price Suggestion</h4>
                                <?php if (!$isView): ?>
                                <div class="flex items-center gap-2 mb-2">
                                    <button type="button" class="px-2 py-1 bg-purple-600 text-white rounded text-xs hover:bg-purple-700" data-action="get-price-suggestion">✨ AI Generate Price</button>
                                    <button type="button" class="px-2 py-1 bg-gray-600 text-white rounded text-xs hover:bg-gray-700" data-action="apply-price-suggestion">Apply To Field</button>
                                    <button type="button" class="px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600" data-action="clear-price-suggestion">Clear</button>
                                </div>
                                <?php endif; ?>
                                <div id="priceSuggestionPlaceholder" class="text-sm text-gray-500 italic">No price suggestion generated yet.</div>
                                <div id="priceSuggestionDisplay" class="mb-2 p-2 bg-green-50 rounded border border-green-200 hidden">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-green-700 font-medium">Suggested Price:</span>
                                        <span class="font-bold text-green-800 text-lg" id="displaySuggestedPrice">$0.00</span>
                                    </div>
                                    <div class="mt-1 text-xs text-green-700 flex gap-3">
                                        <span>Confidence: <span id="displayConfidence">N/A</span></span>
                                        <span>Generated: <span id="displayTimestamp">—</span></span>
                                    </div>
                                </div>
                                <div id="reasoningList" class="text-sm text-gray-700 space-y-1"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Full-Width Images Section -->
                <div class="modal-section">
                    <div id="imagesSection" class="images-section-container">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                            <span class="mr-2">🖼️</span> Item Images
                        </h3>
                        <div id="currentImagesContainer" class="current-images-section">
                            <div class="flex justify-between items-center mb-2">
                                <div class="text-sm text-gray-600">Current Images:</div>
                                <?php if ($isEdit): ?>
                                <button type="button" id="processExistingImagesBtn" data-action="process-images-ai" class="px-3 py-2 bg-purple-500 text-white rounded text-sm hover:bg-purple-600 transition-colors">
                                    🎨 AI Process All
                                </button>
                                <?php endif; ?>
                            </div>
                            <div id="currentImagesList" class="w-full"></div>
                        </div>
                        <?php if (!$isView): ?>
                        <div class="multi-image-upload-section mt-4 pt-4 border-t border-gray-200">
                            <input type="file" id="multiImageUpload" name="images[]" multiple accept="image/*" class="visually-hidden-file">
                            <div class="upload-controls">
                                <div class="flex gap-3 flex-wrap items-center mb-3">
                                    <label for="multiImageUpload" role="button" tabindex="0" class="btn btn-primary">📁 Upload Images</label>
                                    <div class="text-sm text-gray-500">Maximum file size: 10MB per image. Supported formats: PNG, JPG, JPEG, WebP, GIF</div>
                                </div>
                                <div id="uploadProgress" class="mt-3 hidden">
                                    <div class="text-sm text-gray-600 mb-2">Uploading images...</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div id="uploadProgressBar" class="bg-blue-600 h-2 rounded-full upload-progress-bar"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bottom: Gender, Size & Color Management -->
                <div class="modal-section">
                    <div class="flex justify-between items-center mb-1">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center"><span class="mr-2">📦</span> Gender, Size & Color Management</h3>
                        <div class="flex items-center gap-1">
                            <button type="button" data-action="ensure-color-sizes" class="bg-gray-700 text-white rounded text-xs px-2 py-1 hover:bg-gray-800" title="Ensure each color has its own size rows (clone general sizes to each color)">Configure containers</button>
                            <button type="button" data-action="distribute-general-stock-evenly" class="bg-amber-600 text-white rounded text-xs px-2 py-1 hover:bg-amber-700" title="Copy general size stock evenly across all colors for each size, then zero the general rows">Distribute general stock</button>
                            <button type="button" data-action="sync-size-stock" class="bg-blue-500 text-white rounded text-xs px-2 py-1 hover:bg-blue-600">Sync Stock</button>
                        </div>
                    </div>
                    <!-- Nested Inventory Editor (JS renders tree here) -->
                    <div class="mb-2">
                        <h4 class="font-medium text-gray-700 text-sm mb-1">Nested Inventory Editor (by Gender ➜ Color ➜ Size)</h4>
                        <div class="flex flex-wrap items-center gap-1 mb-1" id="nestedInventoryControls">
                            <label class="text-xs text-gray-600">Gender
                                <select id="nestedGenderFilter" class="border border-gray-300 rounded text-xs p-1 ml-1">
                                    <option value="">All</option>
                                    <option value="Unisex">Unisex</option>
                                    <option value="Men">Men</option>
                                    <option value="Women">Women</option>
                                    <option value="Boys">Boys</option>
                                    <option value="Girls">Girls</option>
                                    <option value="Baby">Baby</option>
                                </select>
                            </label>
                            <label class="text-xs text-gray-600">Color
                                <select id="nestedColorFilter" class="border border-gray-300 rounded text-xs p-1 ml-1">
                                    <option value="">All</option>
                                </select>
                            </label>
                            <label class="text-xs text-gray-600">Search
                                <input id="nestedSearch" type="text" class="border border-gray-300 rounded text-xs p-1 ml-1" placeholder="Size name/code…" />
                            </label>
                            <label class="text-xs text-gray-600">Sort
                                <select id="nestedSort" class="border border-gray-300 rounded text-xs p-1 ml-1">
                                    <option value="code">Size Code</option>
                                    <option value="name">Size Name</option>
                                    <option value="stock">Stock</option>
                                </select>
                            </label>
                            <button type="button" data-action="recompute-nested-totals" class="ml-1 bg-gray-200 text-gray-800 rounded text-xs px-2 py-1" title="Recalculate color totals from the visible size inputs without saving">Recompute totals</button>
                            <button type="button" data-action="save-visible-size-stocks" class="bg-blue-600 text-white rounded text-xs px-2 py-1 hover:bg-blue-700" title="Persist all visible size quantities to the server">Save all visible totals</button>
                            <div class="ml-auto flex items-center gap-1">
                                <button type="button" id="btnExpandAllNested" class="bg-gray-200 text-gray-800 rounded text-xs px-2 py-1">Expand all</button>
                                <button type="button" id="btnCollapseAllNested" class="bg-gray-200 text-gray-800 rounded text-xs px-2 py-1">Collapse all</button>
                                <label class="text-xs text-gray-600 inline-flex items-center gap-1 ml-1">
                                    <input id="nestedShowInactive" type="checkbox" class="align-middle"> Show inactive
                                </label>
                                <div class="flex items-center gap-1 ml-2 text-xs text-gray-700">
                                    <span>Bulk:</span>
                                    <input id="nestedBulkValue" type="number" min="0" class="border border-gray-300 rounded text-xs p-1 w-20" placeholder="Qty" />
                                    <button type="button" id="nestedBulkSet" class="bg-gray-200 text-gray-800 rounded text-xs px-2 py-1" title="Set all visible stock to value">Set</button>
                                    <button type="button" id="nestedBulkAdjustPlus" class="bg-gray-200 text-gray-800 rounded text-xs px-2 py-1" title="Add value to all visible">+ Add</button>
                                    <button type="button" id="nestedBulkAdjustMinus" class="bg-gray-200 text-gray-800 rounded text-xs px-2 py-1" title="Subtract value from all visible">− Sub</button>
                                </div>
                            </div>
                        </div>
                        <div id="nestedInventoryEditor" class="space-y-2" data-sku="<?= $sku ?>">
                            <div class="text-sm text-gray-500">Loading nested inventory…</div>
                        </div>
                        <div class="mt-1 text-xs text-gray-600">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 border border-gray-200 mr-2">Group container</span>
                            Groups options like Gender. These do not directly hold stock.
                            <br>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 border border-gray-200 mr-2">Item container</span>
                            Represents actual sellable variants (e.g., Colors). Totals roll up from Sizes.
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Tip: Edit size-level stock below. Totals auto-sync to colors and item stock.</div>
                    </div>
                    <div>
                        <!-- Temporary fallback: showing legacy Sizes/Colors panels while nested editor stabilizes -->
                        <div class="mb-1 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">Temporary fallback: Legacy Sizes/Colors lists are visible while the nested editor is loading.</div>
                        <div id="sizesList" class="space-y-2"></div>
                        <div id="sizesLoading" class="text-center text-gray-500 text-sm">Loading sizes...</div>
                        <div id="colorsList" class="space-y-2"></div>
                        <div id="colorsLoading" class="text-center text-gray-500 text-sm">Loading colors...</div>
                    </div>
                    <?php if (!$isView): ?>
                    <div class="mt-2 flex gap-1">
                        <button type="button" data-action="add-item-size" class="bg-gray-700 text-white rounded text-xs px-3 py-2">+ Add Size</button>
                        <button type="button" data-action="add-item-color" class="bg-gray-700 text-white rounded text-xs px-3 py-2">+ Add Color</button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Option Cascade & Grouping -->
                <div class="modal-section">
                    <div class="flex justify-between items-center mb-1">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center"><span class="mr-2">🧭</span> Option Cascade & Grouping</h3>
                        <span class="text-xs text-gray-500">Configure how options are shown and aggregated</span>
                    </div>
                    <div id="optionCascadePanel" class="space-y-2" data-sku="<?= $sku ?>">
                        <div>
                            <h4 class="font-medium text-gray-700 text-sm mb-1">Cascade Order</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-1">
                                <div>
                                    <label class="block text-xs text-gray-600">First</label>
                                    <select id="cascadeOrder1" class="mt-1 block w-full p-2 border border-gray-300 rounded">
                                        <option value="gender">Gender</option>
                                        <option value="size">Size</option>
                                        <option value="color">Color</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600">Second</label>
                                    <select id="cascadeOrder2" class="mt-1 block w-full p-2 border border-gray-300 rounded">
                                        <option value="gender">Gender</option>
                                        <option value="size">Size</option>
                                        <option value="color">Color</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600">Third</label>
                                    <select id="cascadeOrder3" class="mt-1 block w-full p-2 border border-gray-300 rounded">
                                        <option value="gender">Gender</option>
                                        <option value="size">Size</option>
                                        <option value="color">Color</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-700 text-sm mb-1">Enabled Dimensions</h4>
                            <div class="flex gap-2 items-center text-sm">
                                <label class="inline-flex items-center gap-1"><input type="checkbox" id="dimGender" checked> Gender</label>
                                <label class="inline-flex items-center gap-1"><input type="checkbox" id="dimSize" checked> Size</label>
                                <label class="inline-flex items-center gap-1"><input type="checkbox" id="dimColor" checked> Color</label>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-700 text-sm mb-1">Grouping Rules (JSON)</h4>
                            <textarea id="groupingRules" rows="4" class="mt-1 block w-full p-2 border border-gray-300 rounded" placeholder='{&quot;size&quot;: {&quot;Plus&quot;: [&quot;XL&quot;,&quot;XXL&quot;]}}'></textarea>
                            <div class="text-xs text-gray-500 mt-1">Optional. Leave blank to use default size codes and color names.</div>
                        </div>
                        <div class="flex justify-end gap-1">
                            <button type="button" class="btn" data-action="reload-option-settings">Reload</button>
                            <button type="button" class="btn btn-primary" data-action="save-option-settings">Save Option Settings</button>
                        </div>
                        <div id="optionSettingsStatus" class="text-xs text-gray-600"></div>
                    </div>
                </div>
                <!-- Form actions -->
                <div class="wf-modal-actions">
                    <?php if ($isView): ?>
                    <button type="button" class="btn wf-modal-button" data-action="close-admin-editor">Close</button>
                    <?php endif; ?>
                    <?php if (!$isView && !$isEdit): ?>
                    <button type="submit" class="btn btn-primary wf-modal-button" data-action="save-inventory">Save Changes</button>
                    <?php endif; ?>
                </div>
            </form>
                <!-- Cost Item Modal -->
                <div id="costItemModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="admin-modal bg-white rounded-lg shadow-xl w-full max-w-md">
                        <div class="modal-header flex justify-between items-center p-3 border-b border-gray-200">
                            <h4 id="costItemModalTitle" class="text-base font-semibold text-gray-800">Add Cost Item</h4>
                            <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-cost-modal" aria-label="Close">×</button>
                        </div>
                        <div class="modal-body p-4 space-y-3">
                            <div>
                                <label id="costNameLabel" for="costName" class="block text-sm text-gray-700">Name</label>
                                <input type="text" id="costName" class="mt-1 block w-full p-2 border border-gray-300 rounded" placeholder="e.g., Premium cotton blank">
                            </div>
                            <div>
                                <label for="costValue" class="block text-sm text-gray-700">Cost ($)</label>
                                <input type="number" id="costValue" step="0.01" min="0" class="mt-1 block w-full p-2 border border-gray-300 rounded" placeholder="0.00">
                            </div>
                        </div>
                        <div class="modal-footer p-3 border-t border-gray-200 flex justify-end gap-2">
                            <button type="button" class="btn" data-action="close-cost-modal">Cancel</button>
                            <button type="button" class="btn btn-primary" data-action="save-cost-item">Save</button>
                        </div>
                    </div>
                </div>

                <!-- Cost Suggestion Choice Dialog -->
                <div id="costSuggestionChoiceDialog" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="admin-modal bg-white rounded-lg shadow-xl w-full max-w-2xl">
                        <div class="modal-header flex justify-between items-center p-3 border-b border-gray-200">
                            <h4 class="text-base font-semibold text-gray-800">AI Cost Suggestions</h4>
                            <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-cost-suggestion-choice-dialog" aria-label="Close">×</button>
                        </div>
                        <div class="modal-body p-4">
                            <div id="costSuggestionChoices" class="space-y-3"></div>
                        </div>
                        <div class="modal-footer p-3 border-t border-gray-200 flex justify-end">
                            <button type="button" class="btn" data-action="close-cost-suggestion-choice-dialog">Close</button>
                        </div>
                    </div>
                </div>

                <!-- Generic Confirmation Modal -->
                <div id="confirmationModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="admin-modal bg-white rounded-lg shadow-xl w-full max-w-md">
                        <div class="modal-header p-3 border-b border-gray-200">
                            <h4 id="confirmationModalTitle" class="text-base font-semibold text-gray-800">Confirm Action</h4>
                        </div>
                        <div class="modal-body p-4">
                            <p id="confirmationModalMessage" class="text-sm text-gray-700">Are you sure?</p>
                        </div>
                        <div class="modal-footer p-3 border-t border-gray-200 flex justify-end gap-2">
                            <button type="button" id="confirmationModalCancel" class="btn">Cancel</button>
                            <button type="button" id="confirmationModalConfirm" class="btn btn-primary">Confirm</button>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <!-- Marketing Manager Modal -->
        <div id="marketingManagerModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" role="dialog" aria-modal="true" aria-hidden="true">
            <div class="admin-modal bg-white rounded-lg shadow-xl w-full max-w-6xl">
                <div class="modal-header flex justify-between items-center p-3 border-b border-gray-200">
                    <h4 class="text-base font-semibold text-gray-800">AI Marketing Manager</h4>
                    <div class="flex items-center gap-2">
                        <span id="currentEditingSku" class="text-sm text-gray-600">SKU: <?= $sku ?></span>
                        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-marketing-manager" aria-label="Close">×</button>
                    </div>
                </div>
                <div class="modal-body p-4 space-y-4">
                    <!-- Brand Voice and Content Tone -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="brandVoice" class="block text-sm text-gray-700">Brand Voice</label>
                            <select id="brandVoice" class="mt-1 block w-full p-2 border border-gray-300 rounded" data-action="marketing-default-change" data-setting="brand_voice">
                                <option value="">Default</option>
                                <option value="friendly">Friendly</option>
                                <option value="professional">Professional</option>
                                <option value="playful">Playful</option>
                                <option value="casual">Casual</option>
                            </select>
                        </div>
                        <div>
                            <label for="contentTone" class="block text-sm text-gray-700">Content Tone</label>
                            <select id="contentTone" class="mt-1 block w-full p-2 border border-gray-300 rounded" data-action="marketing-default-change" data-setting="content_tone">
                                <option value="">Default</option>
                                <option value="conversational">Conversational</option>
                                <option value="formal">Formal</option>
                                <option value="enthusiastic">Enthusiastic</option>
                                <option value="informative">Informative</option>
                            </select>
                        </div>
                    </div>

                    <!-- Marketing Fields -->
                    <div class="space-y-4">
                        <div>
                            <label for="marketingTitle" class="block text-sm text-gray-700">Suggested Title</label>
                            <input type="text" id="marketingTitle" class="mt-1 block w-full p-2 border border-gray-300 rounded" placeholder="AI-suggested title will appear here">
                        </div>
                        <div>
                            <label for="marketingDescription" class="block text-sm text-gray-700">Suggested Description</label>
                            <textarea id="marketingDescription" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded" placeholder="AI-suggested description will appear here"></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="targetAudience" class="block text-sm text-gray-700">Target Audience</label>
                                <input type="text" id="targetAudience" class="mt-1 block w-full p-2 border border-gray-300 rounded" placeholder="e.g., Young professionals, families">
                            </div>
                            <div>
                                <label for="demographics" class="block text-sm text-gray-700">Demographics</label>
                                <input type="text" id="demographics" class="mt-1 block w-full p-2 border border-gray-300 rounded" placeholder="e.g., Age 25-35, urban">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="psychographics" class="block text-sm text-gray-700">Psychographics</label>
                                <input type="text" id="psychographics" class="mt-1 block w-full p-2 border border-gray-300 rounded" placeholder="e.g., Eco-conscious, tech-savvy">
                            </div>
                            <div>
                                <label for="searchIntent" class="block text-sm text-gray-700">Search Intent</label>
                                <input type="text" id="searchIntent" class="mt-1 block w-full p-2 border border-gray-300 rounded" placeholder="e.g., Buy t-shirts online">
                            </div>
                        </div>

                        <div>
                            <label for="seasonalRelevance" class="block text-sm text-gray-700">Seasonal Relevance</label>
                            <input type="text" id="seasonalRelevance" class="mt-1 block w-full p-2 border border-gray-300 rounded" placeholder="e.g., Summer, Holidays">
                        </div>
                    </div>

                    <!-- List Fields -->
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-700">Marketing Lists</h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm text-gray-700">Selling Points</label>
                                    <button type="button" data-action="add-list-item" data-field="selling_points" class="text-xs text-blue-600 hover:text-blue-800">+ Add</button>
                                </div>
                                <div id="sellingPointsList" class="min-h-[3rem] border border-gray-200 rounded p-2 space-y-1"></div>
                            </div>
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm text-gray-700">SEO Keywords</label>
                                    <button type="button" data-action="add-list-item" data-field="seo_keywords" class="text-xs text-blue-600 hover:text-blue-800">+ Add</button>
                                </div>
                                <div id="seoKeywordsList" class="min-h-[3rem] border border-gray-200 rounded p-2 space-y-1"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm text-gray-700">Customer Benefits</label>
                                    <button type="button" data-action="add-list-item" data-field="customer_benefits" class="text-xs text-blue-600 hover:text-blue-800">+ Add</button>
                                </div>
                                <div id="customerBenefitsList" class="min-h-[3rem] border border-gray-200 rounded p-2 space-y-1"></div>
                            </div>
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm text-gray-700">Competitive Advantages</label>
                                    <button type="button" data-action="add-list-item" data-field="competitive_advantages" class="text-xs text-blue-600 hover:text-blue-800">+ Add</button>
                                </div>
                                <div id="competitiveAdvantagesList" class="min-h-[3rem] border border-gray-200 rounded p-2 space-y-1"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-3 border-t border-gray-200 flex justify-between gap-2">
                    <div>
                        <button type="button" data-action="generate-marketing-copy" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">🎯 Generate AI Marketing Copy</button>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn" data-action="close-marketing-manager">Close</button>
                        <button type="button" class="btn btn-primary" data-action="apply-marketing-to-item">Apply to Item</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

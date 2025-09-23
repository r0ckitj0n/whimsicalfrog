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
    function renderAdminItemEditor(string $mode, ?array $editItem, array $categories, array $field_errors = [])
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

        <div id="inventoryModalOuter" class="admin-modal-overlay show fixed inset-0 flex items-start justify-center overflow-y-auto">
            <div class="admin-modal wf-admin-panel-visible show relative mt-8 bg-white rounded-lg shadow-xl w-full max-w-5xl">
                <div class="modal-header flex justify-between items-center p-2 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-green-700">
                        <?= $isView ? 'View Item' : ($isEdit ? 'Edit Item' : 'Add New Inventory Item') ?><?= ($isEdit || $isView) && $name ? ' (' . $name . ')' : '' ?>
                    </h2>
                    <button type="button" class="modal-close-btn" aria-label="Close" data-action="close-admin-editor">×</button>
                </div>

                <div class="modal-body p-4">
            <form id="inventoryForm" method="POST" action="#" enctype="multipart/form-data" class="flex flex-col gap-6">
                <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'add' ?>">
                <?php if ($isEdit && $sku): ?>
                    <input type="hidden" name="itemSku" value="<?= $sku ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left: Item Information -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <span class="mr-2">📝</span> Item Information
                        </h3>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label for="skuEdit" class="block text-gray-700">SKU *</label>
                                    <input type="text" id="skuEdit" name="sku" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('sku', $field_errors) ? 'field-error-highlight' : '' ?>" required value="<?= $sku ?>"<?= $isReadOnly ? ' readonly' : '' ?>>
                                </div>
                                <div>
                                    <label for="name" class="block text-gray-700">Name *</label>
                                    <input type="text" id="name" name="name" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('name', $field_errors) ? 'field-error-highlight' : '' ?>" required value="<?= $name ?>"<?= $isReadOnly ? ' readonly' : '' ?>>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
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
                                    <label for="genderEdit" class="block text-gray-700">Gender *</label>
                                    <select id="genderEdit" name="gender" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('gender', $field_errors) ? 'field-error-highlight' : '' ?>" required<?= $isReadOnly ? ' disabled' : '' ?>>
                                        <?php
                                        $genders = ['Unisex','Men','Women','Boys','Girls','Baby'];
        echo '<option value="">Select Gender</option>';
        foreach ($genders as $g) {
            $gEsc = htmlspecialchars($g, ENT_QUOTES, 'UTF-8');
            $sel = ($gender === $gEsc) ? 'selected' : '';
            echo "<option value=\"{$gEsc}\" {$sel}>{$gEsc}</option>";
        }
        ?>
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

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label for="stockLevel" class="block text-gray-700">Stock Level *</label>
                                    <input type="number" id="stockLevel" name="stockLevel" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('stockLevel', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required value="<?= $stockLevel ?>"<?= $isReadOnly ? ' readonly' : '' ?>>
                                </div>
                                <div>
                                    <label for="reorderPoint" class="block text-gray-700">Reorder Point *</label>
                                    <input type="number" id="reorderPoint" name="reorderPoint" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('reorderPoint', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required value="<?= $reorderPoint ?>"<?= $isReadOnly ? ' readonly' : '' ?>>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
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
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <span class="mr-2">💰</span> Cost & Price Analysis
                        </h3>
                        <div class="suggestions-container">
                            <div class="cost-breakdown-wrapper">
                                <h4 class="font-semibold text-gray-700 mb-2 text-sm">Cost Breakdown</h4>
                                <?php if (!$isView): ?>
                                <div class="flex items-center gap-2 mb-3">
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
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
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
                            <div class="price-suggestion-wrapper mt-4">
                                <h4 class="font-semibold text-gray-700 mb-2 text-sm">Price Suggestion</h4>
                                <?php if (!$isView): ?>
                                <div class="flex items-center gap-2 mb-3">
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
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div id="imagesSection" class="images-section-container">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <span class="mr-2">🖼️</span> Item Images
                        </h3>
                        <div id="currentImagesContainer" class="current-images-section">
                            <div class="flex justify-between items-center mb-3">
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
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center"><span class="mr-2">📦</span> Gender, Size & Color Management</h3>
                        <button type="button" data-action="sync-size-stock" class="bg-blue-500 text-white rounded text-xs px-2 py-1 hover:bg-blue-600">Sync Stock</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-700 text-sm mb-2">Sizes</h4>
                            <div id="sizesList" class="space-y-2"></div>
                            <div id="sizesLoading" class="text-center text-gray-500 text-sm">Loading sizes...</div>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-700 text-sm mb-2">Colors</h4>
                            <div id="colorsList" class="space-y-2"></div>
                            <div id="colorsLoading" class="text-center text-gray-500 text-sm">Loading colors...</div>
                        </div>
                    </div>
                    <?php if (!$isView): ?>
                    <div class="mt-4 flex gap-2">
                        <button type="button" data-action="add-item-size" class="bg-gray-700 text-white rounded text-xs px-3 py-2">+ Add Size</button>
                        <button type="button" data-action="add-item-color" class="bg-gray-700 text-white rounded text-xs px-3 py-2">+ Add Color</button>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Form actions -->
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn" data-action="close-admin-editor">Close</button>
                    <?php if ($isView): ?>
                    <a href="/admin/inventory?edit=<?= htmlspecialchars($sku) ?>" class="btn btn-primary">Edit Item</a>
                    <?php else: ?>
                    <button type="submit" class="btn btn-primary" data-action="save-inventory">Save Changes</button>
                    <?php endif; ?>
                </div>
            </form>
                <!-- Cost Item Modal -->
                <div id="costItemModal" class="admin-modal-overlay hidden fixed inset-0 flex items-start justify-center overflow-y-auto" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="admin-modal relative mt-8 bg-white rounded-lg shadow-xl w-full max-w-md">
                        <div class="modal-header flex justify-between items-center p-3 border-b border-gray-200">
                            <h4 id="costItemModalTitle" class="text-base font-semibold text-gray-800">Add Cost Item</h4>
                            <button type="button" class="modal-close-btn" data-action="close-cost-modal" aria-label="Close">×</button>
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
                <div id="costSuggestionChoiceDialog" class="admin-modal-overlay hidden fixed inset-0 flex items-start justify-center overflow-y-auto" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="admin-modal relative mt-8 bg-white rounded-lg shadow-xl w-full max-w-2xl">
                        <div class="modal-header flex justify-between items-center p-3 border-b border-gray-200">
                            <h4 class="text-base font-semibold text-gray-800">AI Cost Suggestions</h4>
                            <button type="button" class="modal-close-btn" data-action="close-cost-suggestion-choice-dialog" aria-label="Close">×</button>
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
                <div id="confirmationModal" class="admin-modal-overlay hidden fixed inset-0 flex items-start justify-center overflow-y-auto" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="admin-modal relative mt-8 bg-white rounded-lg shadow-xl w-full max-w-md">
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
        <div id="marketingManagerModal" class="admin-modal-overlay hidden fixed inset-0 flex items-start justify-center overflow-y-auto" role="dialog" aria-modal="true" aria-hidden="true">
            <div class="admin-modal relative mt-8 bg-white rounded-lg shadow-xl w-full max-w-6xl">
                <div class="modal-header flex justify-between items-center p-3 border-b border-gray-200">
                    <h4 class="text-base font-semibold text-gray-800">AI Marketing Manager</h4>
                    <div class="flex items-center gap-2">
                        <span id="currentEditingSku" class="text-sm text-gray-600">SKU: <?= $sku ?></span>
                        <button type="button" class="modal-close-btn" data-action="close-marketing-manager" aria-label="Close">×</button>
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

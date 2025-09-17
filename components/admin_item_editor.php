<?php
// components/admin_item_editor.php
// Server-rendered Admin Item Editor (Option A)
// Adapted from archived implementation (2025-08-27/sections/admin_inventory.php)

if (!function_exists('renderAdminItemEditor')) {
    /**
     * Render the admin item editor UI.
     * @param string $mode 'edit' or 'add'
     * @param array|null $editItem Item row or partial fields
     * @param array $categories List of category names
     * @param array $field_errors Field names with validation errors
     */
    function renderAdminItemEditor(string $mode, ?array $editItem, array $categories, array $field_errors = []) {
        $isEdit = ($mode === 'edit');
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

        <div id="inventoryModalOuter" class="admin-modal-overlay" data-action="close-admin-editor-on-overlay">
            <div class="admin-modal wf-admin-panel-visible">
                <div class="modal-header flex justify-between items-center p-4 border-b border-gray-200">
                    <h2 class="text-lg font-bold text-green-700">
                        <?= $isEdit ? 'Edit Item' : 'Add New Inventory Item' ?><?= $isEdit && $name ? ' (' . $name . ')' : '' ?>
                    </h2>
                    <a href="<?= $pageUrl ?>" class="admin-modal-close text-gray-500 hover:text-gray-700" aria-label="Close" data-action="close-admin-editor">&times;</a>
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
                                    <input type="text" id="skuEdit" name="sku" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('sku', $field_errors) ? 'field-error-highlight' : '' ?>" required value="<?= $sku ?>">
                                </div>
                                <div>
                                    <label for="name" class="block text-gray-700">Name *</label>
                                    <input type="text" id="name" name="name" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('name', $field_errors) ? 'field-error-highlight' : '' ?>" required value="<?= $name ?>">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label for="categoryEdit" class="block text-gray-700">Category *</nlabel>
                                    <select id="categoryEdit" name="category" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('category', $field_errors) ? 'field-error-highlight' : '' ?>" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): $c = htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                                            <option value="<?= $c ?>" <?= ($category === $c) ? 'selected' : '' ?>><?= $c ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="genderEdit" class="block text-gray-700">Gender *</label>
                                    <select id="genderEdit" name="gender" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('gender', $field_errors) ? 'field-error-highlight' : '' ?>" required>
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
                                    <select id="statusEdit" name="status" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('status', $field_errors) ? 'field-error-highlight' : '' ?>" required>
                                        <option value="draft" <?= ($status === 'draft') ? 'selected' : '' ?>>Draft (Hidden)</option>
                                        <option value="live" <?= ($status === 'live') ? 'selected' : '' ?>>Live (Public)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label for="stockLevel" class="block text-gray-700">Stock Level *</label>
                                    <input type="number" id="stockLevel" name="stockLevel" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('stockLevel', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required value="<?= $stockLevel ?>">
                                </div>
                                <div>
                                    <label for="reorderPoint" class="block text-gray-700">Reorder Point *</label>
                                    <input type="number" id="reorderPoint" name="reorderPoint" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('reorderPoint', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required value="<?= $reorderPoint ?>">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label for="costPrice" class="block text-gray-700">Cost Price ($) *</label>
                                    <input type="number" id="costPrice" name="costPrice" step="0.01" min="0" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('costPrice', $field_errors) ? 'field-error-highlight' : '' ?>" required value="<?= $costPrice ?>">
                                </div>
                                <div>
                                    <label for="retailPrice" class="block text-gray-700">Retail Price ($) *</label>
                                    <input type="number" id="retailPrice" name="retailPrice" step="0.01" min="0" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('retailPrice', $field_errors) ? 'field-error-highlight' : '' ?>" required value="<?= $retailPrice ?>">
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-gray-700">Description</label>
                                <textarea id="description" name="description" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded"><?= $description ?></textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="button" data-action="open-marketing-manager" class="brand-button px-3 py-2 rounded text-sm">
                                    🎯 Marketing Manager
                                </button>
                            </div>

                            <!-- Images Section -->
                            <div id="imagesSection" class="images-section-container">
                                <div id="currentImagesContainer" class="current-images-section">
                                    <div class="flex justify-between items-center mb-2">
                                        <div class="text-sm text-gray-600">Current Images:</div>
                                        <?php if ($isEdit): ?>
                                        <button type="button" id="processExistingImagesBtn" data-action="process-images-ai" class="px-2 py-1 bg-purple-500 text-white rounded text-xs hover:bg-purple-600 transition-colors">
                                            🎨 AI Process All
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <div id="currentImagesList" class="w-full"></div>
                                </div>
                                <div class="multi-image-upload-section mt-3">
                                    <input type="file" id="multiImageUpload" name="images[]" multiple accept="image/*" class="hidden">
                                    <div class="upload-controls mb-3">
                                        <div class="flex gap-2 flex-wrap">
                                            <button type="button" data-action="trigger-upload" data-target="multiImageUpload" class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">📁 Upload Images</button>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">Maximum file size: 10MB per image. Supported formats: PNG, JPG, JPEG, WebP, GIF</div>
                                        <div id="uploadProgress" class="mt-2 hidden">
                                            <div class="text-sm text-gray-600 mb-2">Uploading images...</div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div id="uploadProgressBar" class="bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                                <div class="mb-2 p-2 bg-green-50 rounded border border-green-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-green-700 font-medium">Suggested Cost:</span>
                                        <span class="font-bold text-green-800 text-lg" id="suggestedCostDisplay">$0.00</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <h5 class="font-medium text-gray-700 text-sm">Materials</h5>
                                        <div id="materialsList" class="min-h-[2rem] border border-gray-100 rounded p-2"></div>
                                    </div>
                                    <div>
                                        <h5 class="font-medium text-gray-700 text-sm">Labor</h5>
                                        <div id="laborList" class="min-h-[2rem] border border-gray-100 rounded p-2"></div>
                                    </div>
                                    <div>
                                        <h5 class="font-medium text-gray-700 text-sm">Energy</h5>
                                        <div id="energyList" class="min-h-[2rem] border border-gray-100 rounded p-2"></div>
                                    </div>
                                    <div>
                                        <h5 class="font-medium text-gray-700 text-sm">Equipment</h5>
                                        <div id="equipmentList" class="min-h-[2rem] border border-gray-100 rounded p-2"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="price-suggestion-wrapper mt-4">
                                <h4 class="font-semibold text-gray-700 mb-2 text-sm">Price Suggestion</h4>
                                <div class="mb-2 p-2 bg-green-50 rounded border border-green-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-green-700 font-medium">Suggested Price:</span>
                                        <span class="font-bold text-green-800 text-lg" id="displaySuggestedPrice">$0.00</span>
                                    </div>
                                </div>
                                <div id="reasoningList" class="text-sm text-gray-700 space-y-1"></div>
                            </div>
                        </div>
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
                    <div class="mt-4 flex gap-2">
                        <button type="button" data-action="add-item-size" class="bg-gray-700 text-white rounded text-xs px-3 py-2">+ Add Size</button>
                        <button type="button" data-action="add-item-color" class="bg-gray-700 text-white rounded text-xs px-3 py-2">+ Add Color</button>
                    </div>
                </div>
            </form>
                </div>
            </div>
        </div>
        <?php
    }
}

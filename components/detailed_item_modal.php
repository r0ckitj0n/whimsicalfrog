<?php
// Detailed Item Modal Component
// This component displays comprehensive item information in a compact modal

// Include marketing helper for selling points
require_once __DIR__ . '/../api/marketing_helper.php';

/**
 * Helper function to get image URL with fallback
 */
function getImageUrl($imagePath, $directory, $extension = 'webp')
{
    if (empty($imagePath)) {
        return "images/{$directory}/placeholder.{$extension}";
    }

    // Ensure brand prefix for image filenames when missing
    if (strpos($imagePath, 'WF-') !== 0 && strpos($imagePath, "/{$directory}/") === false && strpos($imagePath, 'images/') !== 0) {
        $imagePath = 'WF-' . $imagePath;
    }

    // If path already includes directory, use as-is
    if (strpos($imagePath, "/{$directory}/") !== false || strpos($imagePath, "{$directory}/") !== false) {
        return $imagePath;
    }

    // If it starts with images/, use as-is
    if (strpos($imagePath, 'images/') === 0) {
        return $imagePath;
    }

    // If it looks like a SKU (no extension), add the A suffix and extension
    if (strpos($imagePath, '.') === false) {
        return "images/{$directory}/{$imagePath}A.{$extension}";
    }

    // Add directory prefix
    return "images/{$directory}/" . $imagePath;
}

function renderDetailedItemModal($item, $images = [])
{
    ob_start();

    // Debug: Log what data we're receiving
    error_log('MODAL COMPONENT - Item data keys: ' . implode(', ', array_keys($item)));
    error_log('MODAL COMPONENT - stockLevel value: ' . ($item['stockLevel'] ?? 'NOT SET'));
    error_log('MODAL COMPONENT - Full item: ' . json_encode($item));

    // Helper function to check if data exists and is not empty
    function hasData($value)
    {
        return isset($value) && !empty($value) && $value !== 'N/A' && $value !== null;
    }

    // Helper function to safely check if array key exists and has data
    function hasItemData($item, $key)
    {
        return isset($item[$key]) && hasData($item[$key]);
    }

    // Get selling points for this item
    $sellingPoints = getSellingPoints($item['sku'] ?? '');

    ?>
    <div id="detailedItemModal" class="detailed-item-modal fixed inset-0 hidden flex items-center justify-center z-50 p-2 sm:p-4" data-action="closeDetailedModalOnOverlay">
        <div class="modal-content site-modal--xl bg-white rounded-lg shadow-xl overflow-hidden relative detailed-item-modal-container">
            <!-- Scrollable Content -->
            <div class="overflow-y-auto">
                <div class="p-6">
                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Left Column - Images -->
                        <div class="space-y-3">
                            <!-- Main Image with Click-to-Zoom -->
                            <div class="relative">
                                <!-- Back Button Overlay -->
                                <div class="absolute top-2 left-2 z-20 back-button-container">
                                    <button data-action="closeDetailedModal" class="room-modal-button" aria-label="Close">Close</button>
                                </div>
                                <!-- Dynamic Badge Container -->
                                <div id="detailedBadgeContainer" class="absolute top-2 left-2 z-10 flex flex-col space-y-1">
                                    <!-- Badges will be dynamically inserted here -->
                                </div>
                                
                                <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition-opacity">
                                     <?php if (!empty($images)): ?>
                                         <img id="detailedMainImage" 
                                              src="<?php echo htmlspecialchars(getImageUrl($images[0]['image_path'] ?? '', 'items')); ?>" 
                                              alt="<?php echo htmlspecialchars($item['name'] ?? 'Item'); ?>"
                                              class="w-full h-full object-contain">
                                     <?php else: ?>
                                         <img id="detailedMainImage" 
                                              src="<?php echo htmlspecialchars(getImageUrl($item['sku'] ?? '', 'items')); ?>" 
                                              alt="<?php echo htmlspecialchars($item['name'] ?? 'Item'); ?>"
                                              class="w-full h-full object-contain"
                                              data-fallback-src="/images/items/placeholder.webp">
                                     <?php endif; ?>
                                 </div>
                            </div>
                            
                            <!-- Single Sales Pitch Line (moved under image) -->
                            <div id="detailedSalesPitch" class="mt-3 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-3 text-center">
                                <div id="detailedSalesPitchText" class="text-sm font-semibold text-blue-800">
                                    ✨ Experience premium quality and style that speaks to your unique personality!
                                </div>
                            </div>

                            <!-- Thumbnail Gallery -->
                            <?php if (count($images) > 1): ?>
                            <div class="flex space-x-2 overflow-x-auto">
                                <?php foreach ($images as $index => $image): ?>
                                <div class="flex-shrink-0 w-16 h-16 border-2 <?php echo $index === 0 ? 'border-green-500' : 'border-gray-200'; ?> rounded cursor-pointer hover:border-green-400 transition-colors"
                                     data-action="switchDetailedImage"
                                     data-params='{"url":"<?php echo htmlspecialchars(getImageUrl($image['image_path'] ?? '', 'items')); ?>"}'>
                                    <img src="<?php echo htmlspecialchars(getImageUrl($image['image_path'] ?? '', 'items')); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name'] ?? 'Item'); ?> - Image <?php echo $index + 1; ?>"
                                         class="w-full h-full object-contain rounded"
                                         data-fallback-src="/images/items/placeholder.webp">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            

                        </div>
                        
                        <!-- Right Column - Item Details -->
                        <div class="space-y-4">
                            <!-- Header -->
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($item['name'] ?? 'Item Name'); ?></h2>
                                
                                <!-- Price Section -->
                                <div id="detailedPriceSection" class="flex items-center space-x-2 mb-3">
                                    <span id="detailedCurrentPrice" class="font-bold">
                                        $<?php echo number_format($item['retailPrice'] ?? 0, 2); ?>
                                    </span>
                                    <span id="detailedOriginalPrice" class="text-sm text-gray-500 line-through hidden"></span>
                                    <span id="detailedSavings" class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded hidden"></span>
                                </div>
                                <?php $__eff = (isset($item['totalStock']) && (int)$item['totalStock'] > 0) ? (int)$item['totalStock'] : (int)($item['stockLevel'] ?? 0); ?>
                                <?php if ($__eff <= 0): ?>
                                <!-- Out of Stock indicator near top when not available -->
                                <div class="flex items-center space-x-2 mb-3">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                        Out of Stock
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Selling Points -->
            <?php
            $sellingPoints = getSellingPoints($item['sku'] ?? '');
    if (!empty($sellingPoints)): ?>
            <div class="mb-2 bg-gradient-to-r from-green-50 to-blue-50 rounded-lg py-0 px-3 shadow-sm">
                <h3 class="text-lg font-bold text-gray-900 mb-0 leading-tight flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                    Why You'll Love This
                </h3>
                <div class="selling-points-list space-y-2 -mt-3">
                    <?php foreach ($sellingPoints as $point): ?>
                    <div class="selling-point-row flex items-center">
                        <span class="selling-point-pill inline-flex items-center">
                            <svg class="selling-point-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="selling-point-text ml-2 text-sm font-medium leading-tight"><?php echo htmlspecialchars($point); ?></span>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
                            
                            <!-- Description -->
                            <?php if (hasItemData($item, 'description')): ?>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800 mb-1">Description</h3>
                                <p class="text-xs text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Item Options -->
                            <div id="detailedOptionsContainer" class="space-y-3">
                                <!-- Gender Selection (First in hierarchy) -->
                                <div id="genderSelection" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Gender/Style: <span class="text-red-500">*</span>
                                    </label>
                                    <select id="itemGenderSelect" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500 required-field detailed-select">
                                        <option value="">Select a style...</option>
                                    </select>
                                </div>
                                
                                <!-- Size Selection (Second in hierarchy) -->
                                                            <div id="sizeSelection" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Size: <span class="text-red-500">*</span>
                                </label>
                                <select id="itemSizeSelect" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500 required-field detailed-select">
                                    <option value="">Select a size...</option>
                                </select>
                            </div>
                                
                                <!-- Color Selection (Third in hierarchy) -->
                                                            <div id="colorSelection" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Color: <span class="text-red-500">*</span>
                                </label>
                                <select id="itemColorSelect" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500 required-field detailed-select">
                                    <option value="">Select a color...</option>
                                </select>
                            </div>
                            </div>
                            
                            <!-- Quantity and Add to Cart -->
                            <?php if ((isset($item['totalStock']) && (int)$item['totalStock'] > 0) || (isset($item['stockLevel']) && (int)$item['stockLevel'] > 0)): ?>
                            <div class="space-y-3 border-t pt-3">
                                <!-- Stock Status (simple text above quantity; brand color rules) -->
                                <div class="text-sm font-medium">
                                    <?php $__stock = (isset($item['totalStock']) && (int)$item['totalStock'] > 0) ? (int)$item['totalStock'] : (int)($item['stockLevel'] ?? 0); ?>
                                <span class="stock-status-text <?php echo ($__stock < 5) ? 'text-brand-secondary' : 'text-brand-primary'; ?>">
                                    In stock: <?php echo $__stock; ?>
                                </span>
                                </div>

                                <!-- Qty + Add to Cart inline (button fills remaining space) -->
                                <div class="detailed-qty-and-cart-row flex items-center justify-start gap-3">
                                    <!-- Qty block (left) -->
                                    <div class="flex items-center space-x-3 flex-shrink-0 min-w-[220px] qty-block">
                                        <label class="qty-label text-sm font-medium text-gray-700">Qty:</label>
                                        <div class="quantity-selector flex items-center space-x-2">
                                            <button data-action="adjustDetailedQuantity" data-params='{"delta":-1}' class="px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 transition-colors btn--qty btn--qty-dec">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                                </svg>
                                            </button>
                                            <input type="number" 
                                                   id="detailedQuantity" 
                                                   value="1" 
                                                   min="1" 
                                                   max="<?php echo ($__stock > 0 ? $__stock : 1); ?>"
                                                   class="w-16 text-center border border-gray-300 rounded py-1 text-sm input--qty">
                                            <button data-action="adjustDetailedQuantity" data-params='{"delta":1}' class="px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 transition-colors btn--qty btn--qty-inc">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Add to Cart block (right) fills remaining space -->
                                    <div class="flex-1">
                                        <button data-action="addDetailedToCart" data-params='{"sku":"<?php echo htmlspecialchars($item['sku'] ?? ''); ?>"}' 
                                                class="brand-button wf-add-to-cart-btn btn--detailed-add-to-cart btn btn-lg w-full rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                                 <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13h10m-10 0v6a1 1 0 001 1h8a1 1 0 001-1v-6m-9 0h9"></path>
                                                 </svg>
                                                 <span>Add to Cart</span>
                                            </button>
                                    </div>
                                </div>
                                
                                <!-- Sales pitch moved under image -->
                            </div>
                            <?php endif; ?>
                            
                            <!-- Accordion for Additional Details -->
                            <div class="border-t border-gray-200 pt-4">
                                <button id="additionalInfoToggle" class="w-full flex justify-between items-center text-left text-sm font-semibold text-gray-800 focus:outline-none">
                                    <span>Additional Details</span>
                                    <svg id="additionalInfoIcon" class="w-5 h-5 transition-transform transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div id="additionalInfoContent" class="mt-3 space-y-4 hidden">
                                    <!-- Features -->
                                    <?php if (hasItemData($item, 'features')): ?>
                                    <div>
                                        <strong>Features:</strong>
                                        <span><?php echo htmlspecialchars($item['features']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Material -->
                                    <?php if (hasItemData($item, 'material')): ?>
                                    <div>
                                        <strong>Material:</strong>
                                        <span><?php echo htmlspecialchars($item['material']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Dimensions -->
                                    <?php if (hasItemData($item, 'dimensions')): ?>
                                    <div>
                                        <strong>Dimensions:</strong>
                                        <span><?php echo htmlspecialchars($item['dimensions']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Care Instructions -->
                                    <?php if (hasItemData($item, 'care_instructions')): ?>
                                    <div>
                                        <strong>Care Instructions:</strong>
                                        <span><?php echo htmlspecialchars($item['care_instructions']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Color Options -->
                                    <?php if (hasItemData($item, 'color_options')): ?>
                                    <div>
                                        <strong>Color Options:</strong>
                                        <span><?php echo htmlspecialchars($item['color_options']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Size Options -->
                                    <?php if (hasItemData($item, 'size_options')): ?>
                                    <div>
                                        <strong>Size Options:</strong>
                                        <span><?php echo htmlspecialchars($item['size_options']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Production Time -->
                                    <?php if (hasItemData($item, 'production_time')): ?>
                                    <div>
                                        <strong>Production Time:</strong>
                                        <span><?php echo htmlspecialchars($item['production_time']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Warranty -->
                                    <?php if (hasItemData($item, 'warranty')): ?>
                                    <div>
                                        <strong>Warranty:</strong>
                                        <span><?php echo htmlspecialchars($item['warranty']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Tags -->
                                    <?php if (hasItemData($item, 'tags')): ?>
                                    <div>
                                        <strong>Tags:</strong>
                                        <span><?php echo htmlspecialchars($item['tags']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- SKU -->
                                    <div>
                                        <strong>SKU:</strong>
                                        <span><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></span>
                                    </div>
                                    
                                    <!-- Category -->
                                    <div>
                                        <strong>Category:</strong>
                                        <span><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></span>
                                    </div>
                                    
                                    <!-- Stock Level -->
                                    <div>
                                        <strong>Stock Level:</strong>
                                        <span><?php echo $item['stockLevel'] ?? 0; ?> units</span>
                                    </div>
                                    
                                    <!-- Reorder Point -->
                                    <?php if (isset($item['reorderPoint']) && $item['reorderPoint'] > 0): ?>
                                    <div>
                                        <strong>Reorder Point:</strong>
                                        <span><?php echo $item['reorderPoint']; ?> units</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Zoom functionality is now centralized via openImageViewer -->
    
    
    
    <?php
    return ob_get_clean();
}

// Legacy support - alias the old function name
function renderDetailedProductModal($item, $images = [])
{
    return renderDetailedItemModal($item, $images);
}
?> 
<?php
// Detailed Item Modal Component
// This component displays comprehensive item information in a compact modal

// Include marketing helper for selling points
require_once __DIR__ . '/../api/marketing_helper.php';

function renderDetailedItemModal($item, $images = []) {
    ob_start();
    
    // Helper function to check if data exists and is not empty
    function hasData($value) {
        return isset($value) && !empty($value) && $value !== 'N/A' && $value !== null;
    }
    
    // Helper function to safely check if array key exists and has data
    function hasItemData($item, $key) {
        return isset($item[$key]) && hasData($item[$key]);
    }
    
    // Get selling points for this item
    $sellingPoints = getSellingPoints($item['sku'] ?? '');
    
    ?>
    <div id="detailedItemModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center p-4" onclick="closeDetailedModalOnOverlay(event)" style="z-index: 9999 !important;">
        <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[95vh] overflow-hidden relative" onclick="event.stopPropagation()">
            <!-- Close Button -->
            <button onclick="closeDetailedModal()" class="absolute top-4 right-4 z-10 bg-white rounded-full p-2 shadow-md hover:shadow-lg transition-shadow">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            
            <!-- Scrollable Content -->
            <div class="overflow-y-auto max-h-[95vh]">
                <div class="p-6">
                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Left Column - Images -->
                        <div class="space-y-3">
                            <!-- Main Image with Click-to-Zoom -->
                            <div class="relative">
                                <!-- Sale Badge -->
                                <div id="detailedSaleBadge" class="absolute top-2 left-2 z-10 hidden">
                                    <span class="bg-red-500 text-white px-2 py-1 rounded-full text-xs font-bold shadow-lg">
                                        <span id="detailedSaleText">SALE</span>
                                    </span>
                                </div>
                                
                                <!-- Limited Stock Badge -->
                                <div id="detailedStockBadge" class="absolute top-2 right-2 z-10 hidden">
                                    <span class="bg-orange-500 text-white px-2 py-1 rounded-full text-xs font-bold shadow-lg">
                                        LIMITED STOCK
                                    </span>
                                </div>
                                
                                <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition-opacity" onclick="openImageZoom(document.getElementById('detailedMainImage').src)">
                                    <?php if (!empty($images)): ?>
                                        <img id="detailedMainImage" 
                                             src="<?php echo htmlspecialchars($images[0]['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name'] ?? 'Item'); ?>"
                                             class="w-full h-full object-contain">
                                    <?php else: ?>
                                        <img id="detailedMainImage" 
                                             src="images/items/placeholder.webp" 
                                             alt="<?php echo htmlspecialchars($item['name'] ?? 'Item'); ?>"
                                             class="w-full h-full object-contain">
                                    <?php endif; ?>
                                    
                                    <!-- Zoom Icon Overlay -->
                                    <div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white opacity-0 hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Thumbnail Gallery -->
                            <?php if (count($images) > 1): ?>
                            <div class="flex space-x-2 overflow-x-auto">
                                <?php foreach ($images as $index => $image): ?>
                                <div class="flex-shrink-0 w-16 h-16 border-2 <?php echo $index === 0 ? 'border-green-500' : 'border-gray-200'; ?> rounded cursor-pointer hover:border-green-400 transition-colors"
                                     onclick="switchDetailedImage('<?php echo htmlspecialchars($image['image_path']); ?>')">
                                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name'] ?? 'Item'); ?> - Image <?php echo $index + 1; ?>"
                                         class="w-full h-full object-contain rounded">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Right Column - Item Details -->
                        <div class="space-y-4">
                            <!-- Header -->
                            <div>
                                <div class="text-xs text-gray-500 mb-1">
                                    <?php echo htmlspecialchars($item['category'] ?? 'Item'); ?> • SKU: <?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?>
                                </div>
                                <h2 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($item['name'] ?? 'Item Name'); ?></h2>
                                
                                <!-- Price Section -->
                                <div id="detailedPriceSection" class="flex items-center space-x-2 mb-3">
                                    <span id="detailedCurrentPrice" class="text-xl font-bold text-green-600">
                                        $<?php echo number_format($item['retailPrice'] ?? 0, 2); ?>
                                    </span>
                                    <span id="detailedOriginalPrice" class="text-sm text-gray-500 line-through hidden"></span>
                                    <span id="detailedSavings" class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded hidden"></span>
                                </div>
                                
                                <!-- Stock Status -->
                                <div class="flex items-center space-x-2 mb-3">
                                    <?php if (($item['stockLevel'] ?? 0) > 0): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            In Stock (<?php echo $item['stockLevel'] ?? 0; ?> available)
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                            Out of Stock
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Selling Points -->
                            <?php if (!empty($sellingPoints) && count($sellingPoints) > 0): ?>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                <h3 class="text-sm font-semibold text-green-800 mb-2">Why You'll Love This</h3>
                                <div class="space-y-1">
                                    <?php foreach (array_slice($sellingPoints, 0, 3) as $point): ?>
                                    <div class="flex items-start space-x-2">
                                        <svg class="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-xs text-green-700"><?php echo htmlspecialchars($point); ?></span>
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
                                    <select id="itemGenderSelect" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500 required-field">
                                        <option value="">Select a style...</option>
                                    </select>
                                </div>
                                
                                <!-- Size Selection (Second in hierarchy) -->
                                                            <div id="sizeSelection" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Size: <span class="text-red-500">*</span>
                                </label>
                                <select id="itemSizeSelect" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500 required-field">
                                    <option value="">Select a size...</option>
                                </select>
                                <div id="sizeStockInfo" class="text-xs text-gray-500 mt-1"></div>
                            </div>
                                
                                <!-- Color Selection (Third in hierarchy) -->
                                                            <div id="colorSelection" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Color: <span class="text-red-500">*</span>
                                </label>
                                <select id="itemColorSelect" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500 required-field">
                                    <option value="">Select a color...</option>
                                </select>
                                <div id="colorStockInfo" class="text-xs text-gray-500 mt-1"></div>
                            </div>
                            </div>
                            
                            <!-- Quantity and Add to Cart -->
                            <?php if (($item['stockLevel'] ?? 0) > 0): ?>
                            <div class="space-y-3 border-t pt-3">
                                <!-- Quantity Selector -->
                                <div class="flex items-center space-x-3">
                                    <label class="text-sm font-medium text-gray-700">Qty:</label>
                                    <div class="flex items-center space-x-2">
                                        <button onclick="adjustDetailedQuantity(-1)" class="px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                            </svg>
                                        </button>
                                        <input type="number" 
                                               id="detailedQuantity" 
                                               value="1" 
                                               min="1" 
                                               max="<?php echo $item['stockLevel'] ?? 1; ?>"
                                               class="w-16 text-center border border-gray-300 rounded py-1 text-sm">
                                        <button onclick="adjustDetailedQuantity(1)" class="px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Add to Cart Button -->
                                <button onclick="addDetailedToCart('<?php echo htmlspecialchars($item['sku'] ?? ''); ?>')" 
                                        class="brand-button w-full py-2 px-4 rounded-lg flex items-center justify-center space-x-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13h10m-10 0v6a1 1 0 001 1h8a1 1 0 001-1v-6m-9 0h9"></path>
                                        </svg>
                                        <span>Add to Cart</span>
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Additional Details (Expandable) -->
                            <div class="border-t pt-3">
                                <button onclick="toggleDetailedInfo()" class="flex items-center justify-between w-full text-left">
                                    <span class="text-sm font-semibold text-gray-800">Additional Details</span>
                                    <svg id="detailedInfoIcon" class="w-4 h-4 text-gray-600 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                
                                <div id="detailedInfoContent" class="hidden mt-2 space-y-2 text-xs text-gray-700">
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
    
    <!-- Image Zoom Modal -->
    <div id="imageZoomModal" class="fixed inset-0 bg-black bg-opacity-90 hidden z-[60] flex items-center justify-center p-4" onclick="closeImageZoom()">
        <div class="relative max-w-full max-h-full">
            <button onclick="closeImageZoom()" class="absolute top-4 right-4 z-10 bg-white rounded-full p-2 shadow-md hover:shadow-lg transition-shadow">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <img id="zoomedImage" src="" alt="Zoomed item image" class="max-w-full max-h-full object-contain">
        </div>
    </div>
    
    <script>
    // Global variable to store current item data
    window.currentDetailedItem = <?php echo json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    
    // Function to close detailed modal
    function closeDetailedModal() {
        const modal = document.getElementById('detailedItemModal');
        if (modal) {
            modal.classList.add('hidden');
            
            // Clear any scrollbar monitor
            if (window.scrollbarMonitor) {
                clearInterval(window.scrollbarMonitor);
                window.scrollbarMonitor = null;
            }
            
            // Restore scrolling and remove padding
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
    }
    
    // Function to close modal when clicking overlay
    function closeDetailedModalOnOverlay(event) {
        if (event.target === event.currentTarget) {
            closeDetailedModal();
        }
    }
    
    // Function to switch detailed image
    function switchDetailedImage(imagePath) {
        const mainImage = document.getElementById('detailedMainImage');
        if (mainImage) {
            mainImage.src = imagePath;
        }
        
        // Update thumbnail borders
        const thumbnails = document.querySelectorAll('#detailedItemModal .flex-shrink-0');
        thumbnails.forEach((thumb, index) => {
            const thumbImg = thumb.querySelector('img');
            if (thumbImg && thumbImg.src.includes(imagePath.split('/').pop())) {
                thumb.className = thumb.className.replace('border-gray-200', 'border-green-500');
            } else {
                thumb.className = thumb.className.replace('border-green-500', 'border-gray-200');
            }
        });
    }
    
    // Function to adjust quantity
    function adjustDetailedQuantity(change) {
        const input = document.getElementById('detailedQuantity');
        if (input) {
            let newValue = parseInt(input.value) + change;
            const min = parseInt(input.getAttribute('min')) || 1;
            const max = parseInt(input.getAttribute('max')) || 999;
            
            if (newValue < min) newValue = min;
            if (newValue > max) newValue = max;
            
            input.value = newValue;
        }
    }
    
    // Function to toggle additional info
    function toggleDetailedInfo() {
        const content = document.getElementById('detailedInfoContent');
        const icon = document.getElementById('detailedInfoIcon');
        
        if (content && icon) {
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                icon.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        }
    }
    
    // Global variables for current item options
    window.currentItemOptions = {
        sku: null,
        selectedColor: null,
        selectedSize: null,
        selectedGender: null,
        availableStock: 0
    };
    
    // Add the missing closeDetailedModalHandler function
    window.closeDetailedModalHandler = function() {
        const modal = document.getElementById('detailedItemModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
            modal.classList.remove('show');
            
            // Clear any scrollbar monitor
            if (window.scrollbarMonitor) {
                clearInterval(window.scrollbarMonitor);
                window.scrollbarMonitor = null;
            }
            
            // Restore scrolling and remove padding
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            // Reset options
            window.currentItemOptions = {
                sku: null,
                selectedColor: null,
                selectedSize: null,
                selectedGender: null,
                availableStock: 0
            };
            
            // Reset dropdowns in hierarchy order
            const genderSelect = document.getElementById('itemGenderSelect');
            const sizeSelect = document.getElementById('itemSizeSelect');
            const colorSelect = document.getElementById('itemColorSelect');
            
            if (genderSelect) genderSelect.selectedIndex = 0;
            if (sizeSelect) sizeSelect.selectedIndex = 0;
            if (colorSelect) colorSelect.selectedIndex = 0;
            
            // Hide option containers
            document.getElementById('genderSelection')?.classList.add('hidden');
            document.getElementById('sizeSelection')?.classList.add('hidden');
            document.getElementById('colorSelection')?.classList.add('hidden');
            
            // Clear stock info displays
            const sizeStockInfo = document.getElementById('sizeStockInfo');
            const colorStockInfo = document.getElementById('colorStockInfo');
            if (sizeStockInfo) sizeStockInfo.textContent = '';
            if (colorStockInfo) colorStockInfo.textContent = '';
        }
    };
    
    // Load item options (genders first, then sizes based on gender, then colors based on size)
    async function loadItemOptions(itemSku) {
        if (!itemSku) return;
        
        window.currentItemOptions.sku = itemSku;
        
        try {
            // Load genders first (top of hierarchy)
            const gendersResponse = await fetch(`/api/item_genders.php?action=get_all&item_sku=${itemSku}`);
            const gendersData = await gendersResponse.json();
            
            // Populate gender options
            populateGenderOptions(gendersData.genders || []);
            
            // Load all sizes and colors to show what's available (but initially don't filter)
            const [sizesResponse, colorsResponse] = await Promise.all([
                fetch(`/api/item_sizes.php?action=get_sizes&item_sku=${itemSku}`),
                fetch(`/api/item_colors.php?action=get_colors&item_sku=${itemSku}`)
            ]);
            
            const sizesData = await sizesResponse.json();
            const colorsData = await colorsResponse.json();
            
            // Populate size options (but don't filter yet)
            populateSizeOptions(sizesData.sizes || []);
            
            // Populate color options (but don't filter yet)
            populateColorOptions(colorsData.colors || []);
            
        } catch (error) {
            console.error('Error loading item options:', error);
        }
    }
    
    // Populate color dropdown
    function populateColorOptions(colors) {
        const colorSelect = document.getElementById('itemColorSelect');
        const colorSelection = document.getElementById('colorSelection');
        
        if (!colorSelect || !colorSelection) return;
        
        // Clear existing options and event listeners
        colorSelect.innerHTML = '<option value="">Select a color...</option>';
        colorSelect.replaceWith(colorSelect.cloneNode(true));
        const newColorSelect = document.getElementById('itemColorSelect');
        
        if (colors && colors.length > 0) {
            colors.forEach(color => {
                const option = document.createElement('option');
                option.value = color.id;
                option.textContent = color.color_name;
                option.dataset.stock = color.stock_level;
                option.dataset.colorCode = color.color_code || '';
                newColorSelect.appendChild(option);
            });
            
            colorSelection.classList.remove('hidden');
            
            // Add change event listener
            newColorSelect.addEventListener('change', function() {
                window.currentItemOptions.selectedColor = this.value;
                const selectedOption = this.options[this.selectedIndex];
                const stock = selectedOption.dataset.stock || 0;
                
                document.getElementById('colorStockInfo').textContent = 
                    stock > 0 ? `${stock} available in this color` : 'Out of stock in this color';
                
                updateAvailableStock();
            });
        } else {
            colorSelection.classList.add('hidden');
        }
    }
    
    // Populate size dropdown
    function populateSizeOptions(sizes) {
        const sizeSelect = document.getElementById('itemSizeSelect');
        const sizeSelection = document.getElementById('sizeSelection');
        
        if (!sizeSelect || !sizeSelection) return;
        
        // Clear existing options and event listeners
        sizeSelect.innerHTML = '<option value="">Select a size...</option>';
        sizeSelect.replaceWith(sizeSelect.cloneNode(true));
        const newSizeSelect = document.getElementById('itemSizeSelect');
        
        if (sizes && sizes.length > 0) {
            sizes.forEach(size => {
                const option = document.createElement('option');
                option.value = size.id;
                option.textContent = `${size.size_name} (${size.size_code})`;
                option.dataset.stock = size.stock_level;
                option.dataset.sizeCode = size.size_code;
                newSizeSelect.appendChild(option);
            });
            
            sizeSelection.classList.remove('hidden');
            
            // Add change event listener
            newSizeSelect.addEventListener('change', function() {
                window.currentItemOptions.selectedSize = this.value;
                const selectedOption = this.options[this.selectedIndex];
                const stock = selectedOption.dataset.stock || 0;
                
                document.getElementById('sizeStockInfo').textContent = 
                    stock > 0 ? `${stock} available in this size` : 'Out of stock in this size';
                
                // Reset color selection
                window.currentItemOptions.selectedColor = null;
                
                // Load colors based on selected size
                if (this.value) {
                    loadColorsForSize(window.currentItemOptions.sku, this.value);
                } else {
                    // If no size selected, show all colors
                    loadColorsForSize(window.currentItemOptions.sku, null);
                }
                
                updateAvailableStock();
            });
        } else {
            sizeSelection.classList.add('hidden');
        }
    }
    
    // Populate gender dropdown
    function populateGenderOptions(genders) {
        const genderSelect = document.getElementById('itemGenderSelect');
        const genderSelection = document.getElementById('genderSelection');
        
        if (!genderSelect || !genderSelection) return;
        
        // Clear existing options and event listeners
        genderSelect.innerHTML = '<option value="">Select a style...</option>';
        genderSelect.replaceWith(genderSelect.cloneNode(true));
        const newGenderSelect = document.getElementById('itemGenderSelect');
        
        if (genders && genders.length > 0) {
            genders.forEach(gender => {
                const option = document.createElement('option');
                option.value = gender.id;
                option.textContent = gender.gender;
                newGenderSelect.appendChild(option);
            });
            
            genderSelection.classList.remove('hidden');
            
            // Add change event listener
            newGenderSelect.addEventListener('change', function() {
                window.currentItemOptions.selectedGender = this.value;
                
                // Reset dependent selections
                window.currentItemOptions.selectedSize = null;
                window.currentItemOptions.selectedColor = null;
                
                // Reload sizes based on selected gender
                if (this.value) {
                    loadSizesForGender(window.currentItemOptions.sku, this.value);
                } else {
                    // If no gender selected, show all sizes
                    loadSizesForGender(window.currentItemOptions.sku, null);
                }
                
                // Clear color selection until size is chosen
                clearColorOptions();
            });
        } else {
            genderSelection.classList.add('hidden');
        }
    }
    
    // Load sizes for specific gender
    async function loadSizesForGender(itemSku, genderId) {
        try {
            let url = `/api/item_sizes.php?action=get_sizes&item_sku=${itemSku}`;
            if (genderId) {
                url += `&gender_id=${genderId}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                populateSizeOptions(data.sizes || []);
            }
        } catch (error) {
            console.error('Error loading sizes for gender:', error);
        }
    }
    
    // Load colors for specific size
    async function loadColorsForSize(itemSku, sizeId) {
        try {
            let url = `/api/item_colors.php?action=get_colors&item_sku=${itemSku}`;
            if (sizeId) {
                url += `&size_id=${sizeId}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                populateColorOptions(data.colors || []);
            }
        } catch (error) {
            console.error('Error loading colors for size:', error);
        }
    }
    
    // Clear color options
    function clearColorOptions() {
        const colorSelect = document.getElementById('itemColorSelect');
        const colorSelection = document.getElementById('colorSelection');
        
        if (colorSelect) {
            colorSelect.innerHTML = '<option value="">Select a color...</option>';
            colorSelection.classList.add('hidden');
        }
        
        const colorStockInfo = document.getElementById('colorStockInfo');
        if (colorStockInfo) {
            colorStockInfo.textContent = '';
        }
    }
    
    // Update available stock based on selections (Color has priority since it's most specific)
    function updateAvailableStock() {
        const colorSelect = document.getElementById('itemColorSelect');
        const sizeSelect = document.getElementById('itemSizeSelect');
        
        let availableStock = 0;
        
        // Color is most specific, then size
        if (colorSelect && colorSelect.value) {
            const colorOption = colorSelect.options[colorSelect.selectedIndex];
            availableStock = parseInt(colorOption.dataset.stock || 0);
        } else if (sizeSelect && sizeSelect.value) {
            const sizeOption = sizeSelect.options[sizeSelect.selectedIndex];
            availableStock = parseInt(sizeOption.dataset.stock || 0);
        }
        
        window.currentItemOptions.availableStock = availableStock;
        
        // Update quantity input max value
        const quantityInput = document.getElementById('detailedQuantity');
        if (quantityInput && availableStock > 0) {
            quantityInput.max = availableStock;
            if (parseInt(quantityInput.value) > availableStock) {
                quantityInput.value = availableStock;
            }
        }
    }
    
    // Enhanced addDetailedToCart function with validation
    window.addDetailedToCart = function(sku) {
        const quantity = parseInt(document.getElementById('detailedQuantity')?.value || 1);
        const currentItem = window.currentDetailedItem;
        
        if (!currentItem) {
            console.error('No current item data available');
            return;
        }
        
        // Validation: Check if color selection is required but not selected
        const colorSelect = document.getElementById('itemColorSelect');
        const colorSelection = document.getElementById('colorSelection');
        if (colorSelect && colorSelection && !colorSelection.classList.contains('hidden')) {
            const selectedColor = colorSelect.value;
            if (!selectedColor) {
                // Add visual feedback
                colorSelect.classList.add('validation-error');
                setTimeout(() => colorSelect.classList.remove('validation-error'), 3000);
                
                if (window.cart && window.cart.showErrorNotification) {
                    window.cart.showErrorNotification('Please select a color before adding to cart.');
                } else if (window.showValidation) {
                    window.showValidation('Please select a color before adding to cart.');
                } else {
                    alert('Please select a color before adding to cart.');
                }
                return;
            }
            
            // Check if there's enough color inventory available
            const selectedOption = colorSelect.options[colorSelect.selectedIndex];
            const availableStock = parseInt(selectedOption.dataset.stock || 0);
            
                            if (quantity > availableStock) {
                const errorMsg = availableStock === 0 ? 
                    'This color is currently out of stock.' :
                    `Only ${availableStock} of this color available.`;
                
                if (window.cart && window.cart.showErrorNotification) {
                    window.cart.showErrorNotification(errorMsg);
                } else if (window.showError) {
                    window.showError(errorMsg);
                } else {
                    alert(errorMsg);
                }
                return;
            }
        }
        
        // Validation: Check if size selection is required but not selected
        const sizeSelect = document.getElementById('itemSizeSelect');
        const sizeSelection = document.getElementById('sizeSelection');
        if (sizeSelect && sizeSelection && !sizeSelection.classList.contains('hidden')) {
            const selectedSize = sizeSelect.value;
            if (!selectedSize) {
                // Add visual feedback
                sizeSelect.classList.add('validation-error');
                setTimeout(() => sizeSelect.classList.remove('validation-error'), 3000);
                
                if (window.cart && window.cart.showErrorNotification) {
                    window.cart.showErrorNotification('Please select a size before adding to cart.');
                } else if (window.showValidation) {
                    window.showValidation('Please select a size before adding to cart.');
                } else {
                    alert('Please select a size before adding to cart.');
                }
                return;
            }
            
            // Check if there's enough size inventory available
            const selectedOption = sizeSelect.options[sizeSelect.selectedIndex];
            const availableStock = parseInt(selectedOption.dataset.stock || 0);
            
                            if (quantity > availableStock) {
                const errorMsg = availableStock === 0 ? 
                    'This size is currently out of stock.' :
                    `Only ${availableStock} of this size available.`;
                
                if (window.cart && window.cart.showErrorNotification) {
                    window.cart.showErrorNotification(errorMsg);
                } else if (window.showError) {
                    window.showError(errorMsg);
                } else {
                    alert(errorMsg);
                }
                return;
            }
        }
        
        // Validation: Check if gender selection is required but not selected
        const genderSelect = document.getElementById('itemGenderSelect');
        const genderSelection = document.getElementById('genderSelection');
        if (genderSelect && genderSelection && !genderSelection.classList.contains('hidden')) {
            const selectedGender = genderSelect.value;
            if (!selectedGender) {
                // Add visual feedback
                genderSelect.classList.add('validation-error');
                setTimeout(() => genderSelect.classList.remove('validation-error'), 3000);
                
                if (window.cart && window.cart.showErrorNotification) {
                    window.cart.showErrorNotification('Please select a gender/style before adding to cart.');
                } else if (window.showValidation) {
                    window.showValidation('Please select a gender/style before adding to cart.');
                } else {
                    alert('Please select a gender/style before adding to cart.');
                }
                return;
            }
        }
        
        // Build cart item with proper structure
        const cartItem = {
            sku: sku,
            name: currentItem.name || currentItem.productName || 'Unknown Item',
            price: parseFloat(currentItem.retailPrice || currentItem.price || 0),
            quantity: quantity,
            image: currentItem.primaryImageUrl || currentItem.image || currentItem.imageUrl || `images/items/${sku}A.png`
        };
        
        // Add selected options (cart expects string values, not objects)
        if (window.currentItemOptions.selectedColor) {
            const colorSelect = document.getElementById('itemColorSelect');
            const selectedColorOption = colorSelect.options[colorSelect.selectedIndex];
            cartItem.color = selectedColorOption.textContent; // Just the color name
            cartItem.colorCode = selectedColorOption.dataset.colorCode; // Separate color code field
        }
        
        if (window.currentItemOptions.selectedSize) {
            const sizeSelect = document.getElementById('itemSizeSelect');
            const selectedSizeOption = sizeSelect.options[sizeSelect.selectedIndex];
            cartItem.size = selectedSizeOption.textContent; // Just the size name
            cartItem.sizeName = selectedSizeOption.textContent; // Duplicate for compatibility
            cartItem.sizeCode = selectedSizeOption.dataset.sizeCode; // Separate size code field
        }
        
        if (window.currentItemOptions.selectedGender) {
            const genderSelect = document.getElementById('itemGenderSelect');
            const selectedGenderOption = genderSelect.options[genderSelect.selectedIndex];
            cartItem.gender = selectedGenderOption.textContent; // Just the gender name
        }
        
        // Add to cart using the proper cart system
        if (typeof window.cart !== 'undefined' && window.cart.addItem) {
            window.cart.addItem(cartItem);
            // Note: Cart class will handle the notification automatically via showAddToCartNotifications()
        } else {
            console.error('Cart system not available');
            if (window.showError) {
                window.showError('Unable to add item to cart. Please try again.');
            } else {
                alert('Unable to add item to cart. Please try again.');
            }
        }
        
        // Close modal
        window.closeDetailedModalHandler();
    };
    
    // Hook into the existing modal opening function - safer approach
    // Instead of overriding the global function, we'll enhance the existing showDetailedModalComponent
    if (typeof window.showDetailedModalComponent === 'undefined') {
        window.showDetailedModalComponent = function(sku, itemData) {
            // Show the modal
            const modal = document.getElementById('detailedItemModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.classList.remove('hidden');
                
                // The global modal system already handles scrollbar preservation
                // We just need to load our additional options
                setTimeout(() => {
                    loadItemOptions(sku);
                }, 100);
            }
        };
    }
    
    // Function to open image zoom
    function openImageZoom(imageSrc) {
        const modal = document.getElementById('imageZoomModal');
        const image = document.getElementById('zoomedImage');
        
        if (modal && image) {
            image.src = imageSrc;
            modal.classList.remove('hidden');
            // Don't interfere with the main modal's scrollbar management
            // The main modal already has scrollbar preservation active
        }
    }
    
    // Function to close image zoom
    function closeImageZoom() {
        const modal = document.getElementById('imageZoomModal');
        if (modal) {
            modal.classList.add('hidden');
            // Don't interfere with the main modal's scrollbar management
            // The main modal will handle scrollbar restoration when it closes
        }
    }
    
    // Initialize sales checking for detailed modal
    if (typeof checkAndDisplaySalePrice === 'function' && window.currentDetailedItem) {
        document.addEventListener('DOMContentLoaded', () => {
            const priceElement = document.getElementById('detailedCurrentPrice');
            if (priceElement) {
                checkAndDisplaySalePrice(window.currentDetailedItem, priceElement, null, 'modal');
            }
        });
    }
    
    // Log that detailed modal functions are loaded
    console.log('Detailed modal functions loaded:', {
        toggleDetailedInfo: typeof toggleDetailedInfo,
        closeDetailedModalOnOverlay: typeof closeDetailedModalOnOverlay,
        addDetailedToCart: typeof addDetailedToCart
    });
    </script>
    
    <style>
    /* Required field validation styles */
    .required-field {
        position: relative;
    }
    
    .required-field:invalid {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.2) !important;
    }
    
    .required-field:focus:invalid {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important;
    }
    
    /* Validation error highlight */
    .validation-error {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1) !important;
        animation: shake 0.3s ease-in-out;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-2px); }
        75% { transform: translateX(2px); }
    }
    
    /* Required asterisk styling */
    .text-red-500 {
        color: #ef4444;
        font-weight: bold;
    }
    
    /* Brand button styling for detailed modal - using global CSS values */
    .brand-button {
        background-color: #87ac3a;
        color: #ffffff;
        border-radius: 6px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: all 0.2s ease;
        cursor: pointer;
        border: none;
    }
    
    .brand-button:hover {
        background-color: #6b8e23 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(135, 172, 58, 0.3);
    }
    </style>
    
    <?php
    return ob_get_clean();
}

// Legacy support - alias the old function name
function renderDetailedProductModal($item, $images = []) {
    return renderDetailedItemModal($item, $images);
}
?> 
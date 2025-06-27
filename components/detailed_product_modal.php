<?php
// Detailed Product Modal Component
// This component displays comprehensive product information in a compact modal

// Include marketing helper for selling points
require_once __DIR__ . '/../api/marketing_helper.php';

function renderDetailedProductModal($item, $images = []) {
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
    $sellingPoints = getSellingPoints($item['sku']);
    
    ?>
    <div id="detailedProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" onclick="closeDetailedModalOnOverlay(event)">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[95vh] overflow-hidden" onclick="event.stopPropagation()">
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
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 class="w-full h-full object-contain">
                                        <?php else: ?>
                                            <img id="detailedMainImage" 
                                                 src="images/items/placeholder.png" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
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
                                             alt="<?php echo htmlspecialchars($item['name']); ?> - Image <?php echo $index + 1; ?>"
                                             class="w-full h-full object-contain rounded">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Right Column - Product Details -->
                            <div class="space-y-4">
                                <!-- Header -->
                                <div>
                                    <div class="text-xs text-gray-500 mb-1">
                                        <?php echo htmlspecialchars($item['category'] ?? 'Product'); ?> • SKU: <?php echo htmlspecialchars($item['sku']); ?>
                                    </div>
                                    <h2 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($item['name']); ?></h2>
                                    
                                    <!-- Price Section -->
                                    <div id="detailedPriceSection" class="flex items-center space-x-2 mb-3">
                                        <span id="detailedCurrentPrice" class="text-xl font-bold text-green-600">
                                            $<?php echo number_format($item['retailPrice'], 2); ?>
                                        </span>
                                        <span id="detailedOriginalPrice" class="text-sm text-gray-500 line-through hidden"></span>
                                        <span id="detailedSavings" class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded hidden"></span>
                                    </div>
                                    
                                    <!-- Stock Status -->
                                    <div class="flex items-center space-x-2 mb-3">
                                        <?php if ($item['stockLevel'] > 0): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                                In Stock (<?php echo $item['stockLevel']; ?> available)
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
                                
                                <!-- Product Options -->
                                <div id="detailedOptionsContainer" class="space-y-2">
                                    <!-- Options will be loaded dynamically -->
                                </div>
                                
                                <!-- Quantity and Add to Cart -->
                                <?php if ($item['stockLevel'] > 0): ?>
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
                                                   max="<?php echo $item['stockLevel']; ?>"
                                                   class="w-16 text-center border border-gray-300 rounded py-1 text-sm">
                                            <button onclick="adjustDetailedQuantity(1)" class="px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 transition-colors">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    
                                                        <!-- Add to Cart Button -->
                    <button onclick="addDetailedToCart('<?php echo htmlspecialchars($item['sku']); ?>')" 
                            class="wf-add-to-cart-btn w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
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
                                        <h3 class="text-sm font-semibold text-gray-800">Additional Details</h3>
                                        <svg id="detailedInfoChevron" class="w-4 h-4 text-gray-500 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    
                                    <div id="detailedInfoContent" class="hidden mt-2 space-y-2 text-xs text-gray-600">
                                        <!-- Material -->
                                        <?php if (hasItemData($item, 'material')): ?>
                                        <div class="flex">
                                            <span class="font-medium text-gray-700 w-20">Material:</span>
                                            <span class="text-gray-600"><?php echo htmlspecialchars($item['material']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Dimensions -->
                                        <?php if (hasItemData($item, 'dimensions')): ?>
                                        <div class="flex">
                                            <span class="font-medium text-gray-700 w-20">Size:</span>
                                            <span class="text-gray-600"><?php echo htmlspecialchars($item['dimensions']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Weight -->
                                        <?php if (hasItemData($item, 'weight')): ?>
                                        <div class="flex">
                                            <span class="font-medium text-gray-700 w-20">Weight:</span>
                                            <span class="text-gray-600"><?php echo htmlspecialchars($item['weight']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Features -->
                                        <?php if (hasItemData($item, 'features')): ?>
                                        <div class="flex">
                                            <span class="font-medium text-gray-700 w-20">Features:</span>
                                            <span class="text-gray-600"><?php echo htmlspecialchars($item['features']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Care Instructions -->
                                        <?php if (hasItemData($item, 'care_instructions')): ?>
                                        <div class="flex">
                                            <span class="font-medium text-gray-700 w-20">Care:</span>
                                            <span class="text-gray-600"><?php echo htmlspecialchars($item['care_instructions']); ?></span>
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
    </div>
    
    <!-- Image Zoom Modal -->
    <div id="imageZoomModal" class="fixed inset-0 bg-black bg-opacity-90 hidden z-[60]" onclick="closeImageZoom()">
        <div class="flex items-center justify-center min-h-screen p-4">
            <button onclick="closeImageZoom()" class="absolute top-4 right-4 z-10 bg-white bg-opacity-20 rounded-full p-2 hover:bg-opacity-30 transition-all">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <img id="zoomedImage" src="" alt="" class="max-w-full max-h-full object-contain" onclick="event.stopPropagation()">
        </div>
    </div>
    
    <script>
    // Ensure functions are immediately available when script loads
    (function() {
        // Current item data
        let currentDetailedItem = null;
        
        // Adjust quantity in detailed modal
        function adjustDetailedQuantity(change) {
            const quantityInput = document.getElementById('detailedQuantity');
            if (quantityInput) {
                const currentValue = parseInt(quantityInput.value) || 1;
                const newValue = Math.max(1, Math.min(999, currentValue + change));
                quantityInput.value = newValue;
            }
        }
        
        // Toggle additional details
        function toggleDetailedInfo() {
            const content = document.getElementById('detailedInfoContent');
            const chevron = document.getElementById('detailedInfoChevron');
            
            if (content && chevron) {
                const isHidden = content.classList.contains('hidden');
                if (isHidden) {
                    content.classList.remove('hidden');
                    chevron.style.transform = 'rotate(180deg)';
                } else {
                    content.classList.add('hidden');
                    chevron.style.transform = 'rotate(0deg)';
                }
            }
        }
        
        // Switch main image in detailed modal
        function switchDetailedImage(imageUrl) {
            const mainImage = document.getElementById('detailedMainImage');
            if (mainImage) {
                mainImage.src = imageUrl;
                
                // Update thumbnail borders
                const thumbnails = document.querySelectorAll('#detailedProductModal .flex-shrink-0');
                thumbnails.forEach(thumb => {
                    const thumbImg = thumb.querySelector('img');
                    if (thumbImg && thumbImg.src === imageUrl) {
                        thumb.classList.remove('border-gray-200');
                        thumb.classList.add('border-green-500');
                    } else {
                        thumb.classList.remove('border-green-500');
                        thumb.classList.add('border-gray-200');
                    }
                });
            }
        }
        
        // Image zoom functionality
        function openImageZoom(imageSrc) {
            const zoomModal = document.getElementById('imageZoomModal');
            const zoomImage = document.getElementById('zoomedImage');
            
            if (zoomModal && zoomImage) {
                zoomImage.src = imageSrc;
                zoomModal.style.display = 'flex';
                zoomModal.classList.remove('hidden');
            }
        }
        
        function closeImageZoom() {
            const zoomModal = document.getElementById('imageZoomModal');
            if (zoomModal) {
                zoomModal.style.display = 'none';
                zoomModal.classList.add('hidden');
            }
        }
        
        // Check for active sales and update display
        async function checkDetailedItemSale(sku) {
            try {
                const response = await fetch(`/api/sales.php?action=get_active_sales&item_sku=${sku}`);
                const data = await response.json();
                
                const saleBadge = document.getElementById('detailedSaleBadge');
                const currentPriceEl = document.getElementById('detailedCurrentPrice');
                const originalPriceEl = document.getElementById('detailedOriginalPrice');
                const savingsEl = document.getElementById('detailedSavings');
                const saleTextEl = document.getElementById('detailedSaleText');
                
                if (data.success && data.sale) {
                    const sale = data.sale;
                    const originalPrice = parseFloat(currentDetailedItem.retailPrice);
                    const salePrice = originalPrice * (1 - sale.discount_percentage / 100);
                    const savings = originalPrice - salePrice;
                    
                    // Show sale badge
                    if (saleBadge && saleTextEl) {
                        saleTextEl.textContent = `${sale.discount_percentage}% OFF`;
                        saleBadge.classList.remove('hidden');
                    }
                    
                    // Update prices
                    if (currentPriceEl) {
                        currentPriceEl.textContent = `$${salePrice.toFixed(2)}`;
                        currentPriceEl.classList.remove('text-green-600');
                        currentPriceEl.classList.add('text-red-600');
                    }
                    
                    if (originalPriceEl) {
                        originalPriceEl.textContent = `$${originalPrice.toFixed(2)}`;
                        originalPriceEl.classList.remove('hidden');
                    }
                    
                    if (savingsEl) {
                        savingsEl.textContent = `Save $${savings.toFixed(2)}`;
                        savingsEl.classList.remove('hidden');
                    }
                } else {
                    // No sale - hide sale elements
                    if (saleBadge) saleBadge.classList.add('hidden');
                    if (originalPriceEl) originalPriceEl.classList.add('hidden');
                    if (savingsEl) savingsEl.classList.add('hidden');
                    if (currentPriceEl) {
                        currentPriceEl.classList.remove('text-red-600');
                        currentPriceEl.classList.add('text-green-600');
                    }
                }
            } catch (error) {
                console.log('Error checking sale status:', error);
            }
        }
        
        // Check for limited stock and show badge
        function checkDetailedLimitedStock(stockLevel) {
            const stockBadge = document.getElementById('detailedStockBadge');
            if (stockBadge) {
                if (stockLevel > 0 && stockLevel < 5) {
                    stockBadge.classList.remove('hidden');
                } else {
                    stockBadge.classList.add('hidden');
                }
            }
        }
        
        // Load product options (colors, sizes) for the detailed modal
        async function loadDetailedProductOptions(sku) {
            const optionsContainer = document.getElementById('detailedOptionsContainer');
            if (!optionsContainer) return;
            
            try {
                // Load colors
                const colorResponse = await fetch(`/api/item_colors.php?action=get_colors&item_sku=${sku}`);
                const colorData = await colorResponse.json();
                
                let optionsHTML = '';
                
                if (colorData.success && colorData.colors.length > 0) {
                    optionsHTML += `
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Color:</label>
                            <select class="detailed-color-select w-full border border-gray-300 rounded px-2 py-1 text-xs" ${colorData.colors.length > 1 ? 'data-required="true"' : ''}>
                                ${colorData.colors.length > 1 ? '<option value="">Choose color...</option>' : ''}
                                ${colorData.colors.map(color => `
                                    <option value="${color.color_name}" ${colorData.colors.length === 1 ? 'selected' : ''}>
                                        ${color.color_name} ${color.stock_level > 0 ? `(${color.stock_level} available)` : '(Out of stock)'}
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                    `;
                }
                
                // Load sizes
                const sizeResponse = await fetch(`/api/item_sizes.php?action=get_sizes&item_sku=${sku}&color_id=null`);
                const sizeData = await sizeResponse.json();
                
                if (sizeData.success && sizeData.sizes.length > 0) {
                    optionsHTML += `
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Size:</label>
                            <select class="detailed-size-select w-full border border-gray-300 rounded px-2 py-1 text-xs" ${sizeData.sizes.length > 1 ? 'data-required="true"' : ''}>
                                ${sizeData.sizes.length > 1 ? '<option value="">Choose size...</option>' : ''}
                                ${sizeData.sizes.map(size => {
                                    const priceAdjustment = parseFloat(size.price_adjustment || 0);
                                    const adjustmentText = priceAdjustment !== 0 ? 
                                        ` (${priceAdjustment > 0 ? '+' : ''}$${priceAdjustment.toFixed(2)})` : '';
                                    return `
                                        <option value="${size.size_code}" ${sizeData.sizes.length === 1 ? 'selected' : ''}>
                                            ${size.size_name}${adjustmentText} ${size.stock_level > 0 ? `(${size.stock_level} available)` : '(Out of stock)'}
                                        </option>
                                    `;
                                }).join('')}
                            </select>
                        </div>
                    `;
                }
                
                optionsContainer.innerHTML = optionsHTML;
                
            } catch (error) {
                console.log('Error loading detailed product options:', error);
            }
        }
        
        // Add to cart from detailed modal
        function addDetailedToCart(sku) {
            const modal = document.getElementById('detailedProductModal');
            if (!modal) return;
            
            const quantityInput = document.getElementById('detailedQuantity');
            const colorSelect = modal.querySelector('.detailed-color-select');
            const sizeSelect = modal.querySelector('.detailed-size-select');
            
            const quantity = parseInt(quantityInput?.value) || 1;
            const selectedColor = colorSelect?.value || null;
            const selectedSize = sizeSelect?.value || null;
            
            // Validate required selections
            if (colorSelect && colorSelect.dataset.required === 'true' && !selectedColor) {
                alert('Please select a color before adding to cart.');
                return;
            }
            
            if (sizeSelect && sizeSelect.dataset.required === 'true' && !selectedSize) {
                alert('Please select a size before adding to cart.');
                return;
            }
            
            // Get item data from the modal
            const itemName = modal.querySelector('h2').textContent;
            const priceText = modal.querySelector('#detailedCurrentPrice').textContent;
            const price = parseFloat(priceText.replace('$', ''));
            const imageUrl = modal.querySelector('#detailedMainImage')?.src || 'images/items/placeholder.png';
            
            // Add to cart
            if (typeof window.cart !== 'undefined') {
                const cartItem = {
                    sku: sku,
                    name: itemName,
                    price: price,
                    image: imageUrl,
                    quantity: quantity
                };
                
                if (selectedColor) cartItem.color = selectedColor;
                if (selectedSize) cartItem.size = selectedSize;
                
                window.cart.addItem(cartItem);
                
                // Show confirmation and close modal
                const colorText = selectedColor ? ` - ${selectedColor}` : '';
                const sizeText = selectedSize ? ` - ${selectedSize}` : '';
                const quantityText = quantity > 1 ? ` (${quantity})` : '';
                
                alert(`${itemName}${colorText}${sizeText}${quantityText} added to your cart!`);
                closeDetailedModal();
            } else {
                console.error('Cart functionality not available');
            }
        }
        
        // Close modal function
        function closeDetailedModal() {
            const modal = document.getElementById('detailedProductModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.add('hidden');
                document.body.classList.remove('modal-open');
                document.documentElement.classList.remove('modal-open');
            }
            closeImageZoom(); // Also close zoom if open
        }
        
        // Close modal when clicking on overlay (but not on modal content)
        function closeDetailedModalOnOverlay(event) {
            if (event.target === event.currentTarget) {
                closeDetailedModal();
            }
        }
        
        // Function to show the detailed modal (called from shop page)
        function showDetailedModalComponent(sku, itemData) {
            currentDetailedItem = itemData;
            const modal = document.getElementById('detailedProductModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.classList.remove('hidden');
                document.body.classList.add('modal-open');
                document.documentElement.classList.add('modal-open');
                
                // Load product options
                loadDetailedProductOptions(sku);
                
                // Check for sales and limited stock
                checkDetailedItemSale(sku);
                checkDetailedLimitedStock(itemData.stockLevel || 0);
            }
        }
        
        // Make all functions globally available immediately
        window.showDetailedModalComponent = showDetailedModalComponent;
        window.addDetailedToCart = addDetailedToCart;
        window.adjustDetailedQuantity = adjustDetailedQuantity;
        window.toggleDetailedInfo = toggleDetailedInfo;
        window.switchDetailedImage = switchDetailedImage;
        window.openImageZoom = openImageZoom;
        window.closeImageZoom = closeImageZoom;
        window.closeDetailedModal = closeDetailedModal;
        window.closeDetailedModalOnOverlay = closeDetailedModalOnOverlay;
        window.loadDetailedProductOptions = loadDetailedProductOptions;
        window.checkDetailedItemSale = checkDetailedItemSale;
        window.checkDetailedLimitedStock = checkDetailedLimitedStock;
        
        // Debug log to confirm functions are available
        console.log('Detailed modal functions loaded:', {
            toggleDetailedInfo: typeof window.toggleDetailedInfo,
            closeDetailedModalOnOverlay: typeof window.closeDetailedModalOnOverlay,
            addDetailedToCart: typeof window.addDetailedToCart
        });
    })();
    </script>
    
    <?php
    return ob_get_clean();
}
?> 
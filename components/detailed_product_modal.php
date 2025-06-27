<?php
// Detailed Product Modal Component
// This component displays comprehensive product information in a compact modal

function renderDetailedProductModal($item, $images = []) {
    $primaryImage = !empty($images) ? $images[0] : null;
    $additionalImages = array_slice($images, 1);
    
    // Helper function to check if field has data
    function hasData($value) {
        return !empty($value) && trim($value) !== '';
    }
    
    ob_start();
    ?>
    
    <!-- Detailed Product Modal - Compact Layout -->
    <div id="detailedProductModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4" style="display: none;">
        <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-white border-b px-6 py-3 flex justify-between items-center z-10">
                <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h2>
                <button onclick="closeDetailedModal()" class="text-gray-500 hover:text-gray-700 text-2xl font-bold">
                    &times;
                </button>
            </div>
            
            <!-- Modal Content - Compact Layout -->
            <div class="p-6">
                <div class="flex gap-6">
                    <!-- Left Column - Image (Larger than popup but compact) -->
                    <div class="flex-shrink-0" style="width: 280px;">
                        <!-- Main Image -->
                        <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden mb-3">
                            <?php if ($primaryImage): ?>
                                <img id="detailedMainImage" 
                                     src="<?php echo htmlspecialchars($primaryImage['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="w-full h-full object-contain">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <span>No image available</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Thumbnail Gallery (if multiple images) -->
                        <?php if (count($images) > 1): ?>
                        <div class="grid grid-cols-4 gap-1">
                            <?php foreach (array_slice($images, 0, 4) as $index => $image): ?>
                            <div class="aspect-square bg-gray-100 rounded cursor-pointer overflow-hidden border <?php echo $index === 0 ? 'border-green-500' : 'border-gray-200 hover:border-gray-400'; ?>"
                                 onclick="switchDetailedImage('<?php echo htmlspecialchars($image['image_path']); ?>', this)">
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     alt="View <?php echo $index + 1; ?>"
                                     class="w-full h-full object-cover">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Right Column - Product Details (Compact) -->
                    <div class="flex-1 space-y-4">
                        <!-- Category and SKU -->
                        <div>
                            <div class="text-sm text-gray-500 uppercase tracking-wide"><?php echo htmlspecialchars($item['category'] ?? 'Product'); ?></div>
                            <div class="text-xs text-gray-400 font-mono">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                        </div>
                        
                        <!-- Price and Stock -->
                        <div class="flex items-center justify-between">
                            <div id="detailedPriceSection">
                                <div class="text-2xl font-bold text-green-600">
                                    $<?php echo number_format($item['retailPrice'], 2); ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php if ($item['stockLevel'] > 0): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <?php echo $item['stockLevel']; ?> in stock
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Out of stock
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <?php if (hasData($item['description'])): ?>
                        <div>
                            <p class="text-gray-700 text-sm leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Color and Size Options (will be loaded dynamically) -->
                        <div id="detailedOptionsContainer" class="space-y-3">
                            <!-- Options will be loaded here by JavaScript -->
                        </div>
                        
                        <!-- Quantity and Add to Cart -->
                        <div class="space-y-3 pt-3 border-t">
                            <div class="flex items-center space-x-3">
                                <label class="text-sm font-medium text-gray-700">Quantity:</label>
                                <div class="flex items-center border rounded">
                                    <button onclick="adjustDetailedQuantity(-1)" class="px-2 py-1 text-gray-600 hover:text-gray-800 text-sm">-</button>
                                    <input type="number" id="detailedQuantity" value="1" min="1" max="<?php echo $item['stockLevel']; ?>" 
                                           class="w-12 text-center border-0 focus:ring-0 text-sm">
                                    <button onclick="adjustDetailedQuantity(1)" class="px-2 py-1 text-gray-600 hover:text-gray-800 text-sm">+</button>
                                </div>
                            </div>
                            
                            <?php if ($item['stockLevel'] > 0): ?>
                            <button onclick="addDetailedToCart('<?php echo $item['sku']; ?>')" 
                                    class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg font-medium transition-colors">
                                <?php 
                                require_once __DIR__ . '/../api/business_settings_helper.php';
                                echo htmlspecialchars(getRandomCartButtonText()); 
                                ?>
                            </button>
                            <?php else: ?>
                            <button disabled class="w-full bg-gray-400 text-white py-2 px-4 rounded-lg font-medium cursor-not-allowed">
                                Out of Stock
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Additional Details (Collapsible) -->
                        <?php 
                        $hasAdditionalDetails = hasData($item['materials']) || hasData($item['dimensions']) || 
                                              hasData($item['weight']) || hasData($item['features']) || 
                                              hasData($item['care_instructions']) || hasData($item['technical_details']);
                        ?>
                        <?php if ($hasAdditionalDetails): ?>
                        <div class="pt-3 border-t">
                            <button onclick="toggleDetailedInfo()" class="flex items-center justify-between w-full text-left text-sm font-medium text-gray-700 hover:text-gray-900">
                                <span>Additional Details</span>
                                <svg id="detailedInfoChevron" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <div id="detailedInfoContent" class="hidden mt-3 space-y-3 text-sm">
                                <!-- Materials -->
                                <?php if (hasData($item['materials'])): ?>
                                <div>
                                    <span class="font-medium text-gray-600">Materials:</span>
                                    <span class="text-gray-700"><?php echo htmlspecialchars($item['materials']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Dimensions & Weight -->
                                <?php if (hasData($item['dimensions']) || hasData($item['weight'])): ?>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php if (hasData($item['dimensions'])): ?>
                                    <div>
                                        <span class="font-medium text-gray-600">Dimensions:</span>
                                        <span class="text-gray-700"><?php echo htmlspecialchars($item['dimensions']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (hasData($item['weight'])): ?>
                                    <div>
                                        <span class="font-medium text-gray-600">Weight:</span>
                                        <span class="text-gray-700"><?php echo htmlspecialchars($item['weight']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Features -->
                                <?php if (hasData($item['features'])): ?>
                                <div>
                                    <span class="font-medium text-gray-600">Features:</span>
                                    <span class="text-gray-700"><?php echo htmlspecialchars($item['features']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Care Instructions -->
                                <?php if (hasData($item['care_instructions'])): ?>
                                <div>
                                    <span class="font-medium text-gray-600">Care:</span>
                                    <span class="text-gray-700"><?php echo htmlspecialchars($item['care_instructions']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Switch main image when thumbnail is clicked
    function switchDetailedImage(imagePath, thumbnail) {
        const mainImage = document.getElementById('detailedMainImage');
        if (mainImage) {
            mainImage.src = imagePath;
        }
        
        // Update active thumbnail
        const thumbnails = thumbnail.parentElement.querySelectorAll('div');
        thumbnails.forEach(thumb => {
            thumb.classList.remove('border-green-500');
            thumb.classList.add('border-gray-200');
        });
        thumbnail.classList.remove('border-gray-200');
        thumbnail.classList.add('border-green-500');
    }
    
    // Adjust quantity
    function adjustDetailedQuantity(change) {
        const quantityInput = document.getElementById('detailedQuantity');
        if (quantityInput) {
            const currentValue = parseInt(quantityInput.value) || 1;
            const newValue = Math.max(1, Math.min(parseInt(quantityInput.max) || 99, currentValue + change));
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Color:</label>
                        <select class="detailed-color-select w-full border border-gray-300 rounded px-3 py-1 text-sm" ${colorData.colors.length > 1 ? 'data-required="true"' : ''}>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Size:</label>
                        <select class="detailed-size-select w-full border border-gray-300 rounded px-3 py-1 text-sm" ${sizeData.sizes.length > 1 ? 'data-required="true"' : ''}>
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
        const priceText = modal.querySelector('#detailedPriceSection .text-2xl').textContent;
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
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
        }
    }
    </script>
    
    <?php
    return ob_get_clean();
}
?> 
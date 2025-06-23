<?php
// Detailed Product Modal Component
// This component displays comprehensive product information in a large modal

function renderDetailedProductModal($item, $images = []) {
    $primaryImage = !empty($images) ? $images[0] : null;
    $additionalImages = array_slice($images, 1);
    
    // Helper function to check if field has data
    function hasData($value) {
        return !empty($value) && trim($value) !== '';
    }
    
    ob_start();
    ?>
    
    <!-- Detailed Product Modal -->
    <div id="detailedProductModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4" style="display: none;">
        <div class="bg-white rounded-lg max-w-6xl w-full max-h-[95vh] overflow-y-auto shadow-2xl">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center z-10">
                <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h2>
                <button onclick="closeDetailedModal()" class="text-gray-500 hover:text-gray-700 text-3xl font-bold">
                    &times;
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Column - Images -->
                    <div class="space-y-4">
                        <!-- Main Image -->
                        <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden">
                            <?php if ($primaryImage): ?>
                                <img id="detailedMainImage" 
                                     src="<?php echo htmlspecialchars($primaryImage['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <span>No image available</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Thumbnail Gallery -->
                        <?php if (count($images) > 1): ?>
                        <div class="grid grid-cols-4 gap-2">
                            <?php foreach ($images as $index => $image): ?>
                            <div class="aspect-square bg-gray-100 rounded cursor-pointer overflow-hidden border-2 <?php echo $index === 0 ? 'border-green-500' : 'border-transparent hover:border-gray-300'; ?>"
                                 onclick="switchDetailedImage('<?php echo htmlspecialchars($image['image_path']); ?>', this)">
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?> - View <?php echo $index + 1; ?>"
                                     class="w-full h-full object-cover">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Right Column - Product Details -->
                    <div class="space-y-6">
                        <!-- Basic Info -->
                        <div>
                            <div class="text-3xl font-bold text-green-600 mb-2">
                                $<?php echo number_format($item['retailPrice'], 2); ?>
                            </div>
                            <?php if (hasData($item['description'])): ?>
                            <p class="text-gray-700 text-lg leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stock Status -->
                        <div class="flex items-center space-x-2">
                            <?php if ($item['stockLevel'] > 0): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    ✓ In Stock (<?php echo $item['stockLevel']; ?> available)
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                    ✗ Out of Stock
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Add to Cart Section -->
                        <div class="border-t pt-4">
                            <div class="flex items-center space-x-4 mb-4">
                                <label class="text-sm font-medium text-gray-700">Quantity:</label>
                                <div class="flex items-center border rounded-md">
                                    <button onclick="adjustDetailedQuantity(-1)" class="px-3 py-1 text-gray-600 hover:text-gray-800">-</button>
                                    <input type="number" id="detailedQuantity" value="1" min="1" max="<?php echo $item['stockLevel']; ?>" 
                                           class="w-16 text-center border-0 focus:ring-0">
                                    <button onclick="adjustDetailedQuantity(1)" class="px-3 py-1 text-gray-600 hover:text-gray-800">+</button>
                                </div>
                            </div>
                            
                            <?php if ($item['stockLevel'] > 0): ?>
                            <button onclick="addDetailedToCart('<?php echo $item['sku']; ?>')" 
                                    class="w-full bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-medium text-lg transition-colors">
                                <?php 
                                require_once __DIR__ . '/../api/business_settings_helper.php';
                                echo htmlspecialchars(getRandomCartButtonText()); 
                                ?>
                            </button>
                            <?php else: ?>
                            <button disabled class="w-full bg-gray-400 text-white py-3 px-6 rounded-lg font-medium text-lg cursor-not-allowed">
                                Out of Stock
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Detailed Information Tabs -->
                        <div class="border-t pt-6">
                            <div class="space-y-4">
                                <!-- Materials -->
                                <?php if (hasData($item['materials'])): ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Materials</h3>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($item['materials'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Dimensions & Weight -->
                                <?php if (hasData($item['dimensions']) || hasData($item['weight'])): ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Specifications</h3>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                                </div>
                                <?php endif; ?>
                                
                                <!-- Features -->
                                <?php if (hasData($item['features'])): ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Features</h3>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($item['features'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Options -->
                                <?php if (hasData($item['color_options']) || hasData($item['size_options'])): ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Available Options</h3>
                                    <div class="space-y-2">
                                        <?php if (hasData($item['color_options'])): ?>
                                        <div>
                                            <span class="font-medium text-gray-600">Colors:</span>
                                            <span class="text-gray-700"><?php echo htmlspecialchars($item['color_options']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (hasData($item['size_options'])): ?>
                                        <div>
                                            <span class="font-medium text-gray-600">Sizes:</span>
                                            <span class="text-gray-700"><?php echo htmlspecialchars($item['size_options']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Technical Details -->
                                <?php if (hasData($item['technical_details'])): ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Technical Details</h3>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($item['technical_details'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Care Instructions -->
                                <?php if (hasData($item['care_instructions'])): ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Care Instructions</h3>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($item['care_instructions'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Customization Options -->
                                <?php if (hasData($item['customization_options'])): ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Customization</h3>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($item['customization_options'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Usage Tips -->
                                <?php if (hasData($item['usage_tips'])): ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Usage Tips</h3>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($item['usage_tips'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Production Time -->
                                <?php if (hasData($item['production_time'])): ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Production Time</h3>
                                    <p class="text-gray-700"><?php echo htmlspecialchars($item['production_time']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Warranty -->
                                <?php if (hasData($item['warranty_info'])): ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Warranty</h3>
                                    <p class="text-gray-700"><?php echo htmlspecialchars($item['warranty_info']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function switchDetailedImage(imagePath, thumbnail) {
        document.getElementById('detailedMainImage').src = imagePath;
        
        // Update thumbnail borders
        const thumbnails = thumbnail.parentElement.children;
        for (let i = 0; i < thumbnails.length; i++) {
            thumbnails[i].classList.remove('border-green-500');
            thumbnails[i].classList.add('border-transparent');
        }
        thumbnail.classList.remove('border-transparent');
        thumbnail.classList.add('border-green-500');
    }
    
    function adjustDetailedQuantity(change) {
        const input = document.getElementById('detailedQuantity');
        const currentValue = parseInt(input.value);
        const newValue = currentValue + change;
        const max = parseInt(input.getAttribute('max'));
        
        if (newValue >= 1 && newValue <= max) {
            input.value = newValue;
        }
    }
    
    function addDetailedToCart(sku) {
        const quantity = parseInt(document.getElementById('detailedQuantity').value);
        
        // Use existing cart functionality
        if (typeof addToCart === 'function') {
            addToCart(sku, quantity);
            closeDetailedModal();
        } else {
            // Fallback if addToCart function not available
            alert('Added ' + quantity + ' item(s) to cart!');
            closeDetailedModal();
        }
    }
    
    function closeDetailedModal() {
        document.getElementById('detailedProductModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    function showDetailedModal() {
        document.getElementById('detailedProductModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    </script>
    
    <?php
    return ob_get_clean();
}
?> 
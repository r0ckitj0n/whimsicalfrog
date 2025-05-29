<?php
// Admin inventory management page

// Check if user is logged in and is an admin
$user = json_decode($_SESSION['user'] ?? '{}', true);
if (!isset($user['role']) || $user['role'] !== 'Admin') {
    header('Location: /login.php');
    exit;
}

$products = fetchData('products');
$inventory = fetchData('inventory');

// Get unique product types for dropdown
$productTypes = [];
if ($products) {
    $productData = array_slice($products, 1); // Skip header row
    foreach ($productData as $product) {
        if (!in_array($product[2], $productTypes)) {
            $productTypes[] = $product[2];
        }
    }
}
sort($productTypes); // Sort alphabetically

// Group inventory items by product
$inventoryByProduct = [];
if ($inventory) {
    $inventoryData = array_slice($inventory, 1); // Skip header row
    foreach ($inventoryData as $item) {
        $productId = $item[1];
        if (!isset($inventoryByProduct[$productId])) {
            $inventoryByProduct[$productId] = [];
        }
        $inventoryByProduct[$productId][] = $item;
    }
}
?>
<section id="adminInventoryPage" class="p-6 bg-white rounded-lg shadow-lg">
    <div class="flex justify-between items-center mb-8">
        <h2 class="text-4xl font-merienda text-[#556B2F]">Inventory Management</h2>
        <div class="flex items-center gap-4">
            <a href="/?page=admin" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md">Back to Dashboard</a>
            <button onclick="logout()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md">Logout</button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-8">
        <?php if ($products): ?>
            <?php foreach (array_slice($products, 1) as $product): ?>
                <div class="bg-gray-100 p-6 rounded-lg shadow" data-product-id="<?php echo htmlspecialchars($product[0]); ?>">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Product Image -->
                        <div class="w-full md:w-1/3">
                            <div class="relative">
                                <img src="<?php echo htmlspecialchars($product[8] ?? 'images/placeholder.png'); ?>" 
                                     alt="<?php echo htmlspecialchars($product[1]); ?>" 
                                     class="w-full h-48 object-cover rounded-md">
                                <button onclick="updateProductImage('<?php echo htmlspecialchars($product[0]); ?>')" 
                                        class="absolute bottom-2 right-2 bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-1 px-3 rounded-md text-sm">
                                    Change Image
                                </button>
                            </div>
                        </div>

                        <!-- Product Details -->
                        <div class="w-full md:w-2/3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Product Name</label>
                                    <input type="text" 
                                           value="<?php echo htmlspecialchars($product[1]); ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6B8E23] focus:ring-[#6B8E23]"
                                           onchange="updateProductField('<?php echo htmlspecialchars($product[0]); ?>', 'ProductName', this.value)">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Base Price</label>
                                    <input type="number" 
                                           value="<?php echo htmlspecialchars($product[3]); ?>" 
                                           step="0.01"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6B8E23] focus:ring-[#6B8E23]"
                                           onchange="updateProductField('<?php echo htmlspecialchars($product[0]); ?>', 'BasePrice', this.value)">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Description</label>
                                    <textarea class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6B8E23] focus:ring-[#6B8E23]"
                                              rows="2"
                                              onchange="updateProductField('<?php echo htmlspecialchars($product[0]); ?>', 'Description', this.value)"><?php echo htmlspecialchars($product[4]); ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Product Type</label>
                                    <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6B8E23] focus:ring-[#6B8E23]"
                                            onchange="updateProductField('<?php echo htmlspecialchars($product[0]); ?>', 'ProductType', this.value)">
                                        <?php foreach ($productTypes as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>" 
                                                    <?php echo $product[2] === $type ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">SKU Base</label>
                                    <input type="text" 
                                           value="<?php echo htmlspecialchars($product[5]); ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6B8E23] focus:ring-[#6B8E23]"
                                           onchange="updateProductField('<?php echo htmlspecialchars($product[0]); ?>', 'DefaultSKU_Base', this.value)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Items -->
                    <div class="mt-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium">Inventory Items</h3>
                            <form class="add-inventory-form">
                                <input type="hidden" name="productId" value="<?php echo $product[0]; ?>">
                                <input type="hidden" name="productName" value="<?php echo $product[1]; ?>">
                                <button type="submit" class="bg-[#6B8E23] text-white px-4 py-2 rounded hover:bg-[#556B2F]">
                                    Add Inventory Item
                                </button>
                            </form>
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                            <?php if (isset($inventoryByProduct[$product[0]])): ?>
                                <?php foreach ($inventoryByProduct[$product[0]] as $item): ?>
                                    <div class="bg-white p-4 rounded-md shadow" data-inventory-id="<?php echo htmlspecialchars($item[0]); ?>">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Item Name</label>
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($item[2]); ?>" 
                                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6B8E23] focus:ring-[#6B8E23]"
                                                       onchange="updateInventoryField('<?php echo htmlspecialchars($item[0]); ?>', 'ProductName', this.value)">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Stock Level</label>
                                                <input type="number" 
                                                       value="<?php echo htmlspecialchars($item[6]); ?>" 
                                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6B8E23] focus:ring-[#6B8E23]"
                                                       onchange="updateInventoryField('<?php echo htmlspecialchars($item[0]); ?>', 'StockLevel', this.value)">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Reorder Point</label>
                                                <input type="number" 
                                                       value="<?php echo htmlspecialchars($item[7]); ?>" 
                                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6B8E23] focus:ring-[#6B8E23]"
                                                       onchange="updateInventoryField('<?php echo htmlspecialchars($item[0]); ?>', 'ReorderPoint', this.value)">
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($item[4]); ?>" 
                                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6B8E23] focus:ring-[#6B8E23]"
                                                       onchange="updateInventoryField('<?php echo htmlspecialchars($item[0]); ?>', 'Description', this.value)">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">SKU</label>
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($item[5]); ?>" 
                                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#6B8E23] focus:ring-[#6B8E23]"
                                                       onchange="updateInventoryField('<?php echo htmlspecialchars($item[0]); ?>', 'SKU', this.value)">
                                            </div>
                                        </div>
                                        <div class="mt-4 flex justify-end">
                                            <button onclick="deleteInventoryItem('<?php echo htmlspecialchars($item[0]); ?>')" 
                                                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-3 rounded-md text-sm">
                                                Delete Item
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-700">No inventory items for this product</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-700">No products found</p>
        <?php endif; ?>
    </div>
</section>

<script>
// Add event listeners when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Handle add inventory form submissions
    document.querySelectorAll('.add-inventory-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const productId = this.querySelector('input[name="productId"]').value;
            const productName = this.querySelector('input[name="productName"]').value;
            addInventoryItem(productId, productName);
        });
    });
});

// Product management functions
async function updateProductField(productId, field, value) {
    try {
        const response = await fetch('http://localhost:3000/api/update-product', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                productId,
                field,
                value
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to update product');
        }
        
        showAlert('Product updated successfully', false);
    } catch (error) {
        showAlert(error.message);
    }
}

async function updateProductImage(productId) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    
    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('image', file);
        formData.append('productId', productId);
        
        try {
            const response = await fetch('http://localhost:3000/api/upload-image', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('Failed to upload image');
            }
            
            const data = await response.json();
            const imgElement = document.querySelector(`[data-product-id="${productId}"] img`);
            imgElement.src = data.imageUrl;
            
            showAlert('Image updated successfully', false);
        } catch (error) {
            showAlert(error.message);
        }
    };
    
    input.click();
}

// Inventory management functions
async function updateInventoryField(inventoryId, field, value) {
    try {
        const response = await fetch('http://localhost:3000/api/update-inventory', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                inventoryId,
                field,
                value
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to update inventory');
        }
        
        showAlert('Inventory updated successfully', false);
    } catch (error) {
        showAlert(error.message);
    }
}

async function addInventoryItem(productId, productName) {
    try {
        const response = await fetch('http://localhost:3000/api/add-inventory', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                productId: productId,
                productName: productName,
                description: 'New item description',
                sku: 'NEW-SKU',
                stockLevel: 0,
                reorderPoint: 5
            })
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to add inventory item');
        }

        const result = await response.json();
        console.log('Inventory item added:', result);
        
        // Show success message
        alert('Inventory item added successfully!');
        
        // Reload the page to show the new item
        window.location.reload();
    } catch (error) {
        console.error('Error adding inventory item:', error);
        alert('Error adding inventory item: ' + error.message);
    }
}

async function deleteInventoryItem(inventoryId) {
    if (!confirm('Are you sure you want to delete this inventory item?')) {
        return;
    }
    
    try {
        const response = await fetch('http://localhost:3000/api/delete-inventory', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                inventoryId
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to delete inventory item');
        }
        
        // Remove the item from the DOM
        const itemElement = document.querySelector(`[data-inventory-id="${inventoryId}"]`);
        itemElement.remove();
        
        showAlert('Inventory item deleted successfully', false);
    } catch (error) {
        showAlert(error.message);
    }
}

function showAlert(message, isError = true) {
    const alertBox = document.getElementById('customAlertBox');
    const alertMessage = document.getElementById('customAlertMessage');
    
    alertMessage.textContent = message;
    alertBox.style.backgroundColor = isError ? '#f8d7da' : '#d4edda';
    alertBox.style.color = isError ? '#721c24' : '#155724';
    alertBox.style.borderColor = isError ? '#f5c6cb' : '#c3e6cb';
    alertBox.style.display = 'block';
    
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 3000);
}

function logout() {
    sessionStorage.removeItem('user');
    window.location.href = '/login.php';
}
</script> 
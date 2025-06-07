<?php
// Admin inventory management page

// Check if user is logged in and is an admin
$user = json_decode($_SESSION['user'] ?? '{}', true);
if (!isset($user['role']) || $user['role'] !== 'Admin') {
    header('Location: /login.php');
    exit;
}

// Fetch products and inventory data from Node API (MySQL)
$apiBase = 'https://whimsicalfrog.us';
$productsJson = @file_get_contents($apiBase . '/api/products');
$products = $productsJson ? json_decode($productsJson, true) : [];
$inventoryJson = @file_get_contents($apiBase . '/api/inventory');
$inventory = $inventoryJson ? json_decode($inventoryJson, true) : [];

// Get unique product types for dropdown
$productTypes = [];
if ($products) {
    $productData = array_slice($products, 1); // Skip header row
    foreach ($productData as $product) {
        if (!in_array($product['productType'], $productTypes)) {
            $productTypes[] = $product['productType'];
        }
    }
}
sort($productTypes); // Sort alphabetically

// Group inventory items by product
$inventoryByProduct = [];
if ($inventory) {
    $inventoryData = array_slice($inventory, 1); // Skip header row
    foreach ($inventoryData as $item) {
        $productId = $item['productId'];
        if (!isset($inventoryByProduct[$productId])) {
            $inventoryByProduct[$productId] = [];
        }
        $inventoryByProduct[$productId][] = $item;
    }
}
?>
<style>
  .admin-data-label {
    color: #222 !important;
  }
  .admin-data-value {
    color: #c00 !important;
    font-weight: bold;
  }
</style>
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
                <div class="bg-gray-100 p-6 rounded-lg shadow" data-product-id="<?php echo htmlspecialchars($product['id']); ?>">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Product Image -->
                        <div class="w-full md:w-1/3">
                            <div class="relative">
                                <img src="<?php echo htmlspecialchars($product['image'] ?? 'images/placeholder.png'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="w-full h-48 object-cover rounded-md">
                            </div>
                            <button onclick="updateProductImage('<?php echo htmlspecialchars($product['id']); ?>')" 
                                    class="mt-2 bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-1 px-3 rounded-md text-sm">
                                Change Image
                            </button>
                        </div>

                        <!-- Product Details -->
                        <div class="w-full md:w-2/3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <span class="admin-data-label">Product Name</span>
                                    <span class="admin-data-value"><?php echo htmlspecialchars($product['name']); ?></span>
                                </div>
                                <div>
                                    <span class="admin-data-label">Base Price</span>
                                    <span class="admin-data-value"><?php echo htmlspecialchars($product['basePrice']); ?></span>
                                </div>
                                <div class="md:col-span-2">
                                    <span class="admin-data-label">Description</span>
                                    <span class="admin-data-value"><?php echo htmlspecialchars($product['description']); ?></span>
                                </div>
                                <div>
                                    <span class="admin-data-label">Product Type</span>
                                    <span class="admin-data-value"><?php echo htmlspecialchars($product['productType']); ?></span>
                                </div>
                                <div>
                                    <span class="admin-data-label">SKU Base</span>
                                    <span class="admin-data-value"><?php echo htmlspecialchars($product['defaultSKU_Base']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Items -->
                    <div class="mt-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium">Inventory Items</h3>
                            <form class="add-inventory-form">
                                <input type="hidden" name="productId" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="productName" value="<?php echo $product['name']; ?>">
                                <button type="submit" class="bg-[#6B8E23] text-white px-4 py-2 rounded hover:bg-[#556B2F]">
                                    Add Inventory Item
                                </button>
                            </form>
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                            <?php if (isset($inventoryByProduct[$product['id']])): ?>
                                <?php foreach ($inventoryByProduct[$product['id']] as $item): ?>
                                    <div class="bg-white p-4 rounded-md shadow" data-inventory-id="<?php echo htmlspecialchars($item['id']); ?>">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <span class="admin-data-label">Item Name</span>
                                                <span class="admin-data-value"><?php echo htmlspecialchars($item['name']); ?></span>
                                            </div>
                                            <div>
                                                <span class="admin-data-label">Stock Level</span>
                                                <span class="admin-data-value"><?php echo htmlspecialchars($item['stockLevel']); ?></span>
                                            </div>
                                            <div>
                                                <span class="admin-data-label">Reorder Point</span>
                                                <span class="admin-data-value"><?php echo htmlspecialchars($item['reorderPoint']); ?></span>
                                            </div>
                                            <div class="md:col-span-2">
                                                <span class="admin-data-label">Description</span>
                                                <span class="admin-data-value"><?php echo htmlspecialchars($item['description']); ?></span>
                                            </div>
                                            <div>
                                                <span class="admin-data-label">SKU</span>
                                                <span class="admin-data-value"><?php echo htmlspecialchars($item['sku']); ?></span>
                                            </div>
                                        </div>
                                        <div class="mt-4 flex justify-end">
                                            <button onclick="deleteInventoryItem('<?php echo htmlspecialchars($item['id']); ?>')" 
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
        const response = await fetch('https://whimsicalfrog.us/api/update-product', {
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
        formData.append('category', 'products');
        
        try {
            const response = await fetch('https://whimsicalfrog.us/api/upload-image', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('Failed to upload image');
            }
            
            const data = await response.json();
            setTimeout(() => {
                document.querySelectorAll(`[data-product-id=\"${productId}\"] img`).forEach(img => {
                    img.src = data.image + '?v=' + Date.now();
                });
            }, 2000);
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
        const response = await fetch('https://whimsicalfrog.us/api/update-inventory', {
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
        const response = await fetch('https://whimsicalfrog.us/api/add-inventory', {
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
        const response = await fetch('https://whimsicalfrog.us/api/delete-inventory', {
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
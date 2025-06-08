<?php
// Check if user is logged in and is an admin
$user = json_decode($_SESSION['user'] ?? '{}', true);
if (!isset($user['role']) || $user['role'] !== 'Admin') {
    header('Location: /?page=login');
    exit;
}

// Include the configuration file for database access
require_once 'api/config.php';

// Helper function to calculate sum of costs
function sumCost($items) {
    $total = 0;
    foreach ($items as $item) {
        $total += floatval($item['cost']);
    }
    return $total;
}

// Function to fetch inventory costs from database
function fetchInventoryCosts($pdo, $inventoryId) {
    $materials = [];
    $labor = [];
    $energy = [];
    
    try {
        // Fetch materials
        $stmt = $pdo->prepare('SELECT * FROM inventory_materials WHERE inventoryId = ?');
        $stmt->execute([$inventoryId]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch labor
        $stmt = $pdo->prepare('SELECT * FROM inventory_labor WHERE inventoryId = ?');
        $stmt->execute([$inventoryId]);
        $labor = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch energy
        $stmt = $pdo->prepare('SELECT * FROM inventory_energy WHERE inventoryId = ?');
        $stmt->execute([$inventoryId]);
        $energy = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Handle error silently
    }
    
    return [
        'materials' => $materials,
        'labor' => $labor,
        'energy' => $energy
    ];
}

// Fetch products using local API path
$products = [];
try {
    $productsJson = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/api/products.php');
    if ($productsJson) {
        $products = json_decode($productsJson, true) ?? [];
    }
} catch (Exception $e) {
    // Handle error silently
}

// Fetch inventory using local API path
$inventory = [];
try {
    $inventoryJson = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/api/inventory.php');
    if ($inventoryJson) {
        $inventory = json_decode($inventoryJson, true) ?? [];
    }
} catch (Exception $e) {
    // Handle error silently
}

// Organize inventory by product
$inventoryByProduct = [];
foreach ($inventory as $item) {
    $productId = $item['productId'];
    if (!isset($inventoryByProduct[$productId])) {
        $inventoryByProduct[$productId] = [];
    }
    $inventoryByProduct[$productId][] = $item;
}

// Database connection for cost calculations
$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Handle error silently
}
?>

<section class="admin-section p-6">
    <h2 class="text-2xl font-bold mb-6">Inventory Management</h2>
    
    <div class="flex justify-between items-center mb-6">
        <button id="addProductBtn" class="px-4 py-2 bg-blue-600 text-white rounded">
            Add New Product
        </button>
        <button id="exportInventoryBtn" class="px-4 py-2 bg-green-600 text-white rounded">
            Export Inventory
        </button>
    </div>

    <div class="grid grid-cols-1 gap-8">
        <?php if (count($products) > 0): ?>
            <?php foreach ($products as $product): ?>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Image Preview -->
                        <div class="flex flex-col items-center">
                            <div class="h-64 w-64 bg-gray-100 rounded flex items-center justify-center overflow-hidden">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="max-h-full max-w-full object-contain">
                                <?php else: ?>
                                    <span class="text-gray-400">No Image</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 w-full">
                                <form id="imageForm_<?php echo htmlspecialchars($product['id']); ?>" class="flex flex-col items-center">
                                    <input type="file" id="imageUpload_<?php echo htmlspecialchars($product['id']); ?>" class="w-full text-sm" accept="image/*">
                                    <button type="button" onclick="uploadImage('<?php echo htmlspecialchars($product['id']); ?>')" class="mt-2 px-3 py-1 bg-blue-600 text-white rounded text-sm w-full">Upload Image</button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Product Details -->
                        <div class="flex-1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <span class="admin-data-label">Product Name</span>
                                    <input type="text" id="productName_<?php echo htmlspecialchars($product['id']); ?>" value="<?php echo htmlspecialchars($product['name']); ?>" class="admin-data-value w-full border rounded px-2 py-1">
                                </div>
                                <div>
                                    <span class="admin-data-label">Base Price</span>
                                    <div class="flex items-center">
                                        <input type="number" step="0.01" id="basePrice_<?php echo htmlspecialchars($product['id']); ?>" value="<?php echo htmlspecialchars($product['basePrice']); ?>" class="admin-data-value w-full border rounded px-2 py-1">
                                        <?php
                                        // Calculate suggested retail price (2x the total cost or base price if no costs)
                                        $totalCost = 0;
                                        if (!empty($inventoryByProduct[$product['id']])) {
                                            foreach ($inventoryByProduct[$product['id']] as $item) {
                                                $costs = fetchInventoryCosts($pdo, $item['id']);
                                                $totalCost += sumCost($costs['materials'] ?? []) + sumCost($costs['labor'] ?? []) + sumCost($costs['energy'] ?? []);
                                            }
                                        }
                                        $suggestedPrice = $totalCost > 0 ? $totalCost * 2 : floatval($product['basePrice']) * 2;
                                        ?>
                                        <span class="ml-2 text-gray-600">(Suggested: $<?php echo number_format($suggestedPrice, 2); ?>)</span>
                                    </div>
                                </div>
                                <div>
                                    <span class="admin-data-label">Description</span>
                                    <textarea id="description_<?php echo htmlspecialchars($product['id']); ?>" class="admin-data-value w-full border rounded px-2 py-1"><?php echo htmlspecialchars($product['description']); ?></textarea>
                                </div>
                                <div>
                                    <span class="admin-data-label">Product Type</span>
                                    <input type="text" id="productType_<?php echo htmlspecialchars($product['id']); ?>" value="<?php echo htmlspecialchars($product['productType']); ?>" class="admin-data-value w-full border rounded px-2 py-1">
                                </div>
                                <div>
                                    <span class="admin-data-label">SKU Base</span>
                                    <input type="text" id="defaultSKU_Base_<?php echo htmlspecialchars($product['id']); ?>" value="<?php echo htmlspecialchars($product['defaultSKU_Base']); ?>" class="admin-data-value w-full border rounded px-2 py-1">
                                </div>
                                <div>
                                    <button onclick="updateProduct('<?php echo htmlspecialchars($product['id']); ?>')" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded">Update Product</button>
                                </div>
                            </div>
                            
                            <!-- Inventory Section -->
                            <div class="mt-6">
                                <?php
                                // Calculate total stock across all inventory items for this product
                                $totalStock = 0;
                                if (!empty($inventoryByProduct[$product['id']])) {
                                    foreach ($inventoryByProduct[$product['id']] as $item) {
                                        $totalStock += intval($item['stockLevel']);
                                    }
                                }
                                
                                // Get first inventory item ID for cost management
                                $firstInventoryId = !empty($inventoryByProduct[$product['id']][0]) ? $inventoryByProduct[$product['id']][0]['id'] : '';
                                $itemDescription = $product['name'];
                                
                                // Get all inventory IDs for this product
                                $inventoryIds = [];
                                if (!empty($inventoryByProduct[$product['id']])) {
                                    foreach ($inventoryByProduct[$product['id']] as $item) {
                                        $inventoryIds[] = $item['id'];
                                    }
                                }
                                
                                // Fetch all costs for this product's inventory items
                                $allMaterials = [];
                                $allLabor = [];
                                $allEnergy = [];
                                $totalCost = 0;
                                
                                if (!empty($inventoryByProduct[$product['id']])) {
                                    foreach ($inventoryByProduct[$product['id']] as $item) {
                                        $costs = fetchInventoryCosts($pdo, $item['id']);
                                        $allMaterials = array_merge($allMaterials, $costs['materials'] ?? []);
                                        $allLabor = array_merge($allLabor, $costs['labor'] ?? []);
                                        $allEnergy = array_merge($allEnergy, $costs['energy'] ?? []);
                                        $totalCost += sumCost($costs['materials'] ?? []) + sumCost($costs['labor'] ?? []) + sumCost($costs['energy'] ?? []);
                                    }
                                }
                                ?>
                                <div class="flex flex-col md:flex-row md:items-center gap-4">
                                    <div>
                                        <span class="admin-data-label">Total Stock</span>
                                        <input type="number" value="<?php echo htmlspecialchars($totalStock); ?>" min="0" class="total-stock-input admin-data-value border rounded px-2 py-1 text-sm w-20" 
                                               data-product-id="<?php echo htmlspecialchars($product['id']); ?>"
                                               data-inventory-ids="<?php echo htmlspecialchars(json_encode($inventoryIds)); ?>">
                                    </div>
                                    <div>
                                        <button class="ml-2 px-3 py-2 bg-green-600 text-white rounded text-sm" onclick="openCostModal('<?php echo $firstInventoryId; ?>', '<?php echo htmlspecialchars($itemDescription); ?>')">Manage Costs</button>
                                    </div>
                                </div>
                                
                                <!-- Cost Summary -->
                                <div class="mt-4 p-4 bg-gray-50 rounded">
                                    <h4 class="font-semibold">Total Cost: $<span id="product_<?php echo htmlspecialchars($product['id']); ?>_total_cost"><?php echo number_format($totalCost, 2); ?></span></h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
                                        <div>
                                            <h5 class="font-medium">Materials:</h5>
                                            <ul id="product_<?php echo htmlspecialchars($product['id']); ?>_materials" class="text-sm mt-1">
                                                <?php if (empty($allMaterials)): ?>
                                                    <li>- </li>
                                                <?php else: ?>
                                                    <?php foreach ($allMaterials as $material): ?>
                                                        <li>- <?php echo htmlspecialchars($material['name']); ?>: $<?php echo htmlspecialchars($material['cost']); ?></li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                        <div>
                                            <h5 class="font-medium">Labor:</h5>
                                            <ul id="product_<?php echo htmlspecialchars($product['id']); ?>_labor" class="text-sm mt-1">
                                                <?php if (empty($allLabor)): ?>
                                                    <li>- </li>
                                                <?php else: ?>
                                                    <?php foreach ($allLabor as $labor): ?>
                                                        <li>- <?php echo htmlspecialchars($labor['description']); ?>: $<?php echo htmlspecialchars($labor['cost']); ?></li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                        <div>
                                            <h5 class="font-medium">Energy:</h5>
                                            <ul id="product_<?php echo htmlspecialchars($product['id']); ?>_energy" class="text-sm mt-1">
                                                <?php if (empty($allEnergy)): ?>
                                                    <li>- </li>
                                                <?php else: ?>
                                                    <?php foreach ($allEnergy as $energy): ?>
                                                        <li>- <?php echo htmlspecialchars($energy['description']); ?>: $<?php echo htmlspecialchars($energy['cost']); ?></li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-700">No products found</p>
        <?php endif; ?>
    </div>
</section>

<!-- Cost Management Modal -->
<div id="costModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold" id="costModalTitle">Manage Costs for <span id="costModalItemDesc"></span></h3>
            <button onclick="closeCostModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        
        <input type="hidden" id="costModalItemId">
        
        <!-- Materials Section -->
        <div class="mb-6">
            <h4 class="font-semibold mb-2">Materials</h4>
            <ul id="materialsList" class="mb-4 space-y-2"></ul>
            
            <form id="addMaterialForm" class="bg-gray-50 p-4 rounded">
                <h5 class="font-medium mb-2">Add Material</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Name</label>
                        <input type="text" id="materialName" class="w-full border rounded px-2 py-1 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Cost ($)</label>
                        <input type="number" step="0.01" id="materialCost" class="w-full border rounded px-2 py-1 text-sm" required>
                    </div>
                </div>
                <button type="button" onclick="addMaterial()" class="mt-2 px-3 py-1 bg-blue-600 text-white rounded text-sm">Add Material</button>
            </form>
        </div>
        
        <!-- Labor Section -->
        <div class="mb-6">
            <h4 class="font-semibold mb-2">Labor</h4>
            <ul id="laborList" class="mb-4 space-y-2"></ul>
            
            <form id="addLaborForm" class="bg-gray-50 p-4 rounded">
                <h5 class="font-medium mb-2">Add Labor</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Description</label>
                        <input type="text" id="laborDescription" class="w-full border rounded px-2 py-1 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Cost ($)</label>
                        <input type="number" step="0.01" id="laborCost" class="w-full border rounded px-2 py-1 text-sm" required>
                    </div>
                </div>
                <button type="button" onclick="addLabor()" class="mt-2 px-3 py-1 bg-blue-600 text-white rounded text-sm">Add Labor</button>
            </form>
        </div>
        
        <!-- Energy Section -->
        <div class="mb-6">
            <h4 class="font-semibold mb-2">Energy</h4>
            <ul id="energyList" class="mb-4 space-y-2"></ul>
            
            <form id="addEnergyForm" class="bg-gray-50 p-4 rounded">
                <h5 class="font-medium mb-2">Add Energy</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Description</label>
                        <input type="text" id="energyDescription" class="w-full border rounded px-2 py-1 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Cost ($)</label>
                        <input type="number" step="0.01" id="energyCost" class="w-full border rounded px-2 py-1 text-sm" required>
                    </div>
                </div>
                <button type="button" onclick="addEnergy()" class="mt-2 px-3 py-1 bg-blue-600 text-white rounded text-sm">Add Energy</button>
            </form>
        </div>
        
        <div class="mt-4 p-4 bg-gray-100 rounded">
            <h4 class="font-semibold mb-2">Cost Summary</h4>
            <div class="text-sm">
                <div>Materials: <span id="materialsTotalCost">$0.00</span></div>
                <div>Labor: <span id="laborTotalCost">$0.00</span></div>
                <div>Energy: <span id="energyTotalCost">$0.00</span></div>
                <div class="font-bold border-t mt-2 pt-2">Total: <span id="grandTotalCost">$0.00</span></div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="toast-container"></div>

<style>
.admin-data-label {
    display: block;
    font-size: 0.875rem;
    color: #4b5563;
    margin-bottom: 0.25rem;
}

.admin-data-value {
    font-size: 1rem;
}

.toast-container {
    position: fixed;
    top: 16px;
    right: 16px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.toast {
    padding: 12px 16px;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    transform: translateX(120%);
    transition: transform 0.3s ease-in-out;
    max-width: 350px;
    position: relative;
}

.toast.show {
    transform: translateX(0);
}

.toast-success {
    background-color: #d1e7dd;
    border-left: 4px solid #0f5132;
    color: #0f5132;
}

.toast-error {
    background-color: #f8d7da;
    border-left: 4px solid #842029;
    color: #842029;
}

.toast-icon {
    margin-right: 12px;
    font-size: 18px;
}

.toast-message {
    flex-grow: 1;
}

.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background-color: rgba(255, 255, 255, 0.7);
}
</style>

<script>
// Use the current domain for API calls in both local and production environments
const apiBase = window.location.origin;

// Toast notification system
function showToast(message, isError = false, duration = 3000) {
    const toastContainer = document.getElementById('toast-container');
    
    const toast = document.createElement('div');
    toast.className = `toast ${isError ? 'toast-error' : 'toast-success'}`;
    
    const icon = document.createElement('div');
    icon.className = 'toast-icon';
    icon.innerHTML = isError ? '❌' : '✅';
    
    const messageEl = document.createElement('div');
    messageEl.className = 'toast-message';
    messageEl.textContent = message;
    
    const progressBar = document.createElement('div');
    progressBar.className = 'toast-progress';
    progressBar.style.width = '100%';
    
    toast.appendChild(icon);
    toast.appendChild(messageEl);
    toast.appendChild(progressBar);
    toastContainer.appendChild(toast);
    
    let width = 100;
    const interval = 10;
    const step = 100 / (duration / interval);
    const timer = setInterval(() => {
        width -= step;
        progressBar.style.width = `${width}%`;
        if (width <= 0) {
            clearInterval(timer);
        }
    }, interval);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, duration);
}

// Update product data
function updateProduct(productId) {
    const name = document.getElementById(`productName_${productId}`).value;
    const basePrice = document.getElementById(`basePrice_${productId}`).value;
    const description = document.getElementById(`description_${productId}`).value;
    const productType = document.getElementById(`productType_${productId}`).value;
    const defaultSKU_Base = document.getElementById(`defaultSKU_Base_${productId}`).value;

    const productData = {
        id: productId,
        name,
        basePrice,
        description,
        productType,
        defaultSKU_Base
    };

    showToast('Updating product...', false, 1000);

    fetch(`${apiBase}/api/update-product.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(productData),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`${name} updated successfully!`, false);
            // Highlight updated fields
            const fields = [
                `productName_${productId}`,
                `basePrice_${productId}`,
                `description_${productId}`,
                `productType_${productId}`,
                `defaultSKU_Base_${productId}`
            ];
            
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.add('bg-green-100');
                    setTimeout(() => {
                        field.classList.remove('bg-green-100');
                    }, 1000);
                }
            });
        } else {
            showToast(`Error updating product: ${data.error}`, true);
        }
    })
    .catch((error) => {
        console.error('Error:', error);
        showToast('An unexpected error occurred', true);
    });
}

// Upload product image
function uploadImage(productId) {
    const fileInput = document.getElementById(`imageUpload_${productId}`);
    const file = fileInput.files[0];
    
    if (!file) {
        showToast('Please select an image to upload', true);
        return;
    }
    
    const formData = new FormData();
    formData.append('image', file);
    formData.append('productId', productId);
    
    showToast('Uploading image...', false, 2000);
    
    fetch(`${apiBase}/api/upload-image.php`, {
        method: 'POST',
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Image uploaded successfully!', false);
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(`Error uploading image: ${data.error}`, true);
        }
    })
    .catch((error) => {
        console.error('Error:', error);
        showToast('An unexpected error occurred during upload', true);
    });
}

// Stock level update
document.querySelectorAll('.total-stock-input').forEach(input => {
    input.addEventListener('change', function() {
        updateStockLevel(this);
    });
    input.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            updateStockLevel(this);
        }
    });
});

function updateStockLevel(input) {
    const newTotalStock = parseInt(input.value, 10);
    if (isNaN(newTotalStock) || newTotalStock < 0) {
        showToast('Please enter a valid stock quantity', true);
        return;
    }
    
    const productId = input.dataset.productId;
    const inventoryIds = JSON.parse(input.dataset.inventoryIds || '[]');
    
    if (inventoryIds.length === 0) {
        showToast('No inventory items found for this product', true);
        return;
    }
    
    showToast('Updating stock levels...', false, 1000);
    
    // Get current stock levels to calculate proportions
    const promises = inventoryIds.map(id => 
        fetch(`${apiBase}/api/inventory.php?id=${id}`)
            .then(response => response.json())
    );
    
    Promise.all(promises)
        .then(inventoryItems => {
            const currentStockLevels = inventoryItems.map(item => parseInt(item.stockLevel, 10) || 0);
            const currentTotal = currentStockLevels.reduce((sum, level) => sum + level, 0);
            
            // Calculate new stock levels
            let newStockLevels;
            if (currentTotal === 0) {
                // If all current stock is 0, distribute evenly
                const baseValue = Math.floor(newTotalStock / inventoryIds.length);
                const remainder = newTotalStock % inventoryIds.length;
                newStockLevels = inventoryIds.map((_, index) => 
                    baseValue + (index < remainder ? 1 : 0)
                );
            } else {
                // Distribute proportionally based on current levels
                newStockLevels = currentStockLevels.map(level => 
                    Math.round((level / currentTotal) * newTotalStock)
                );
                
                // Adjust for rounding errors
                const newTotal = newStockLevels.reduce((sum, level) => sum + level, 0);
                if (newTotal !== newTotalStock) {
                    const diff = newTotalStock - newTotal;
                    // Add or subtract the difference from the first non-zero item or the first item if all are zero
                    const indexToAdjust = newStockLevels.findIndex(level => level > 0) || 0;
                    newStockLevels[indexToAdjust] += diff;
                }
            }
            
            // Update each inventory item
            const updatePromises = inventoryIds.map((id, index) => 
                fetch(`${apiBase}/api/update-inventory-stock.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id,
                        stockLevel: newStockLevels[index]
                    }),
                }).then(response => response.json())
            );
            
            return Promise.all(updatePromises);
        })
        .then(results => {
            const allSuccess = results.every(result => result.success);
            if (allSuccess) {
                showToast('Stock levels updated successfully!', false);
                input.classList.add('bg-green-100');
                setTimeout(() => {
                    input.classList.remove('bg-green-100');
                }, 1000);
            } else {
                const errors = results.filter(result => !result.success).map(result => result.error).join(', ');
                showToast(`Error updating some stock levels: ${errors}`, true);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An unexpected error occurred', true);
        });
}

// Cost management functionality
let currentCostItemId = null;
let currentProductId = null;

function openCostModal(itemId, itemDescription) {
    currentCostItemId = itemId;
    document.getElementById('costModal').classList.remove('hidden');
    document.getElementById('costModalItemId').value = itemId;
    document.getElementById('costModalItemDesc').textContent = itemDescription;
    
    // Extract product ID from the item description or data attribute
    const productElement = document.querySelector(`[data-inventory-ids*="${itemId}"]`);
    if (productElement) {
        currentProductId = productElement.dataset.productId;
    }
    
    loadCosts(itemId);
}

function closeCostModal() {
    document.getElementById('costModal').classList.add('hidden');
    currentCostItemId = null;
}

function loadCosts(itemId) {
    showToast('Loading cost data...', false, 1000);
    
    fetch(`${apiBase}/api/inventory-costs.php?id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            // Materials
            const materialsList = document.getElementById('materialsList');
            materialsList.innerHTML = '';
            if (data.materials && data.materials.length > 0) {
                data.materials.forEach(material => {
                    const li = document.createElement('li');
                    li.className = 'flex justify-between items-center bg-white p-2 rounded border';
                    li.innerHTML = `
                        <span class="material-name">${material.name}</span>
                        <span class="material-cost">$${parseFloat(material.cost).toFixed(2)}</span>
                        <div class="flex space-x-2">
                            <button onclick="editMaterial(${material.id}, '${material.name}', ${material.cost})" class="text-blue-600 hover:text-blue-800">Edit</button>
                            <button onclick="deleteCost('material', ${material.id})" class="text-red-600 hover:text-red-800">Delete</button>
                        </div>
                    `;
                    materialsList.appendChild(li);
                });
            } else {
                materialsList.innerHTML = '<li class="text-gray-500">No material costs added yet.</li>';
            }
            
            // Labor
            const laborList = document.getElementById('laborList');
            laborList.innerHTML = '';
            if (data.labor && data.labor.length > 0) {
                data.labor.forEach(labor => {
                    const li = document.createElement('li');
                    li.className = 'flex justify-between items-center bg-white p-2 rounded border';
                    li.innerHTML = `
                        <span class="labor-description">${labor.description}</span>
                        <span class="labor-cost">$${parseFloat(labor.cost).toFixed(2)}</span>
                        <div class="flex space-x-2">
                            <button onclick="editLabor(${labor.id}, '${labor.description}', ${labor.cost})" class="text-blue-600 hover:text-blue-800">Edit</button>
                            <button onclick="deleteCost('labor', ${labor.id})" class="text-red-600 hover:text-red-800">Delete</button>
                        </div>
                    `;
                    laborList.appendChild(li);
                });
            } else {
                laborList.innerHTML = '<li class="text-gray-500">No labor costs added yet.</li>';
            }
            
            // Energy
            const energyList = document.getElementById('energyList');
            energyList.innerHTML = '';
            if (data.energy && data.energy.length > 0) {
                data.energy.forEach(energy => {
                    const li = document.createElement('li');
                    li.className = 'flex justify-between items-center bg-white p-2 rounded border';
                    li.innerHTML = `
                        <span class="energy-description">${energy.description}</span>
                        <span class="energy-cost">$${parseFloat(energy.cost).toFixed(2)}</span>
                        <div class="flex space-x-2">
                            <button onclick="editEnergy(${energy.id}, '${energy.description}', ${energy.cost})" class="text-blue-600 hover:text-blue-800">Edit</button>
                            <button onclick="deleteCost('energy', ${energy.id})" class="text-red-600 hover:text-red-800">Delete</button>
                        </div>
                    `;
                    energyList.appendChild(li);
                });
            } else {
                energyList.innerHTML = '<li class="text-gray-500">No energy costs added yet.</li>';
            }
            
            // Update totals
            document.getElementById('materialsTotalCost').textContent = `$${data.totals.materials.toFixed(2)}`;
            document.getElementById('laborTotalCost').textContent = `$${data.totals.labor.toFixed(2)}`;
            document.getElementById('energyTotalCost').textContent = `$${data.totals.energy.toFixed(2)}`;
            document.getElementById('grandTotalCost').textContent = `$${data.totals.grand.toFixed(2)}`;
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading cost data', true);
        });
}

// Add material cost
function addMaterial() {
    const name = document.getElementById('materialName').value.trim();
    const cost = parseFloat(document.getElementById('materialCost').value);
    
    if (!name || isNaN(cost) || cost < 0) {
        showToast('Please enter a valid name and cost', true);
        return;
    }
    
    const itemId = document.getElementById('costModalItemId').value;
    
    showToast('Adding material cost...', false, 1000);
    
    fetch(`${apiBase}/api/inventory-costs.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add-cost',
            type: 'material',
            inventoryId: itemId,
            data: {
                name: name,
                cost: cost
            }
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Material cost added successfully!', false);
            document.getElementById('materialName').value = '';
            document.getElementById('materialCost').value = '';
            loadCosts(itemId);
            refreshProductData();
        } else {
            showToast(`Error adding material cost: ${data.error}`, true);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An unexpected error occurred', true);
    });
}

// Edit material
function editMaterial(id, name, cost) {
    const materialsList = document.getElementById('materialsList');
    const materialItem = materialsList.querySelector(`li:has(button[onclick*="editMaterial(${id})")`);
    
    if (materialItem) {
        materialItem.innerHTML = `
            <div class="flex w-full space-x-2">
                <input type="text" value="${name}" class="edit-material-name border rounded px-2 py-1 text-sm flex-grow" />
                <input type="number" step="0.01" value="${cost}" class="edit-material-cost border rounded px-2 py-1 text-sm w-24" />
                <button onclick="saveMaterial(${id})" class="bg-green-600 text-white px-2 py-1 rounded text-sm">Save</button>
                <button onclick="loadCosts('${currentCostItemId}')" class="bg-gray-500 text-white px-2 py-1 rounded text-sm">Cancel</button>
            </div>
        `;
    }
}

// Save edited material
function saveMaterial(id) {
    const name = document.querySelector('.edit-material-name').value.trim();
    const cost = parseFloat(document.querySelector('.edit-material-cost').value);
    
    if (!name || isNaN(cost) || cost < 0) {
        showToast('Please enter a valid name and cost', true);
        return;
    }
    
    showToast('Updating material cost...', false, 1000);
    
    fetch(`${apiBase}/api/inventory-costs.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update-cost',
            type: 'material',
            id: id,
            data: {
                name: name,
                cost: cost
            }
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Material cost updated successfully!', false);
            loadCosts(currentCostItemId);
            refreshProductData();
        } else {
            showToast(`Error updating material cost: ${data.error}`, true);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An unexpected error occurred', true);
    });
}

// Add labor cost
function addLabor() {
    const description = document.getElementById('laborDescription').value.trim();
    const cost = parseFloat(document.getElementById('laborCost').value);
    
    if (!description || isNaN(cost) || cost < 0) {
        showToast('Please enter a valid description and cost', true);
        return;
    }
    
    const itemId = document.getElementById('costModalItemId').value;
    
    showToast('Adding labor cost...', false, 1000);
    
    fetch(`${apiBase}/api/inventory-costs.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add-cost',
            type: 'labor',
            inventoryId: itemId,
            data: {
                description: description,
                cost: cost
            }
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Labor cost added successfully!', false);
            document.getElementById('laborDescription').value = '';
            document.getElementById('laborCost').value = '';
            loadCosts(itemId);
            refreshProductData();
        } else {
            showToast(`Error adding labor cost: ${data.error}`, true);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An unexpected error occurred', true);
    });
}

// Edit labor
function editLabor(id, description, cost) {
    const laborList = document.getElementById('laborList');
    const laborItem = laborList.querySelector(`li:has(button[onclick*="editLabor(${id})")`);
    
    if (laborItem) {
        laborItem.innerHTML = `
            <div class="flex w-full space-x-2">
                <input type="text" value="${description}" class="edit-labor-description border rounded px-2 py-1 text-sm flex-grow" />
                <input type="number" step="0.01" value="${cost}" class="edit-labor-cost border rounded px-2 py-1 text-sm w-24" />
                <button onclick="saveLabor(${id})" class="bg-green-600 text-white px-2 py-1 rounded text-sm">Save</button>
                <button onclick="loadCosts('${currentCostItemId}')" class="bg-gray-500 text-white px-2 py-1 rounded text-sm">Cancel</button>
            </div>
        `;
    }
}

// Save edited labor
function saveLabor(id) {
    const description = document.querySelector('.edit-labor-description').value.trim();
    const cost = parseFloat(document.querySelector('.edit-labor-cost').value);
    
    if (!description || isNaN(cost) || cost < 0) {
        showToast('Please enter a valid description and cost', true);
        return;
    }
    
    showToast('Updating labor cost...', false, 1000);
    
    fetch(`${apiBase}/api/inventory-costs.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update-cost',
            type: 'labor',
            id: id,
            data: {
                description: description,
                cost: cost
            }
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Labor cost updated successfully!', false);
            loadCosts(currentCostItemId);
            refreshProductData();
        } else {
            showToast(`Error updating labor cost: ${data.error}`, true);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An unexpected error occurred', true);
    });
}

// Add energy cost
function addEnergy() {
    const description = document.getElementById('energyDescription').value.trim();
    const cost = parseFloat(document.getElementById('energyCost').value);
    
    if (!description || isNaN(cost) || cost < 0) {
        showToast('Please enter a valid description and cost', true);
        return;
    }
    
    const itemId = document.getElementById('costModalItemId').value;
    
    showToast('Adding energy cost...', false, 1000);
    
    fetch(`${apiBase}/api/inventory-costs.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add-cost',
            type: 'energy',
            inventoryId: itemId,
            data: {
                description: description,
                cost: cost
            }
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Energy cost added successfully!', false);
            document.getElementById('energyDescription').value = '';
            document.getElementById('energyCost').value = '';
            loadCosts(itemId);
            refreshProductData();
        } else {
            showToast(`Error adding energy cost: ${data.error}`, true);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An unexpected error occurred', true);
    });
}

// Edit energy
function editEnergy(id, description, cost) {
    const energyList = document.getElementById('energyList');
    const energyItem = energyList.querySelector(`li:has(button[onclick*="editEnergy(${id})")`);
    
    if (energyItem) {
        energyItem.innerHTML = `
            <div class="flex w-full space-x-2">
                <input type="text" value="${description}" class="edit-energy-description border rounded px-2 py-1 text-sm flex-grow" />
                <input type="number" step="0.01" value="${cost}" class="edit-energy-cost border rounded px-2 py-1 text-sm w-24" />
                <button onclick="saveEnergy(${id})" class="bg-green-600 text-white px-2 py-1 rounded text-sm">Save</button>
                <button onclick="loadCosts('${currentCostItemId}')" class="bg-gray-500 text-white px-2 py-1 rounded text-sm">Cancel</button>
            </div>
        `;
    }
}

// Save edited energy
function saveEnergy(id) {
    const description = document.querySelector('.edit-energy-description').value.trim();
    const cost = parseFloat(document.querySelector('.edit-energy-cost').value);
    
    if (!description || isNaN(cost) || cost < 0) {
        showToast('Please enter a valid description and cost', true);
        return;
    }
    
    showToast('Updating energy cost...', false, 1000);
    
    fetch(`${apiBase}/api/inventory-costs.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update-cost',
            type: 'energy',
            id: id,
            data: {
                description: description,
                cost: cost
            }
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Energy cost updated successfully!', false);
            loadCosts(currentCostItemId);
            refreshProductData();
        } else {
            showToast(`Error updating energy cost: ${data.error}`, true);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An unexpected error occurred', true);
    });
}

// Delete cost
function deleteCost(type, id) {
    if (!confirm(`Are you sure you want to delete this ${type} cost?`)) {
        return;
    }
    
    showToast(`Deleting ${type} cost...`, false, 1000);
    
    fetch(`${apiBase}/api/inventory-costs.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'delete-cost',
            type: type,
            id: id
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`${type} cost deleted successfully!`, false);
            loadCosts(currentCostItemId);
            refreshProductData();
        } else {
            showToast(`Error deleting ${type} cost: ${data.error}`, true);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An unexpected error occurred', true);
    });
}

// Refresh product data in background without closing modal
async function refreshProductData() {
    if (!currentProductId) return;
    
    try {
        // Fetch updated cost data for the product
        const response = await fetch(`${apiBase}/api/inventory-costs.php?id=${currentCostItemId}`);
        const data = await response.json();
        
        if (!data) return;
        
        // Update the materials list
        const materialsListElement = document.getElementById(`product_${currentProductId}_materials`);
        if (materialsListElement) {
            materialsListElement.innerHTML = '';
            if (data.materials && data.materials.length > 0) {
                data.materials.forEach(material => {
                    const li = document.createElement('li');
                    li.textContent = `- ${material.name}: $${parseFloat(material.cost).toFixed(2)}`;
                    materialsListElement.appendChild(li);
                });
            } else {
                materialsListElement.innerHTML = '<li>- </li>';
            }
        }
        
        // Update the labor list
        const laborListElement = document.getElementById(`product_${currentProductId}_labor`);
        if (laborListElement) {
            laborListElement.innerHTML = '';
            if (data.labor && data.labor.length > 0) {
                data.labor.forEach(labor => {
                    const li = document.createElement('li');
                    li.textContent = `- ${labor.description}: $${parseFloat(labor.cost).toFixed(2)}`;
                    laborListElement.appendChild(li);
                });
            } else {
                laborListElement.innerHTML = '<li>- </li>';
            }
        }
        
        // Update the energy list
        const energyListElement = document.getElementById(`product_${currentProductId}_energy`);
        if (energyListElement) {
            energyListElement.innerHTML = '';
            if (data.energy && data.energy.length > 0) {
                data.energy.forEach(energy => {
                    const li = document.createElement('li');
                    li.textContent = `- ${energy.description}: $${parseFloat(energy.cost).toFixed(2)}`;
                    energyListElement.appendChild(li);
                });
            } else {
                energyListElement.innerHTML = '<li>- </li>';
            }
        }
        
        // Update the total cost
        const totalCostElement = document.getElementById(`product_${currentProductId}_total_cost`);
        if (totalCostElement) {
            totalCostElement.textContent = parseFloat(data.totals.grand).toFixed(2);
        }
        
        // Update suggested retail price
        const basePriceElement = document.getElementById(`basePrice_${currentProductId}`);
        if (basePriceElement) {
            const basePrice = parseFloat(basePriceElement.value);
            const suggestedPrice = data.totals.grand > 0 ? data.totals.grand * 2 : basePrice * 2;
            const suggestedPriceElement = basePriceElement.nextElementSibling;
            if (suggestedPriceElement) {
                suggestedPriceElement.textContent = `(Suggested: $${suggestedPrice.toFixed(2)})`;
            }
        }
        
        showToast('Product costs on page refreshed', false, 1000);
    } catch (error) {
        console.error('Error refreshing product data:', error);
    }
}

// Export inventory data
document.getElementById('exportInventoryBtn').addEventListener('click', function() {
    // Implementation for export functionality
    showToast('Export functionality coming soon!', false);
});

// Add new product functionality
document.getElementById('addProductBtn').addEventListener('click', function() {
    // Implementation for adding new products
    showToast('Add product functionality coming soon!', false);
});
</script>

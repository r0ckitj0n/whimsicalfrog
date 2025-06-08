<?php
// Admin inventory management page

// Check if user is logged in and is an admin
$user = json_decode($_SESSION['user'] ?? '{}', true);
if (!isset($user['role']) || $user['role'] !== 'Admin') {
    header('Location: /login.php');
    exit;
}

// Detect if we're running locally - FIXED to handle port numbers
$isLocalhost = strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false;
$apiBase = $isLocalhost ? 'http://localhost:3000' : 'https://whimsicalfrog.us';

// Fetch products and inventory data from Node API (MySQL)
$productsJson = @file_get_contents($apiBase . '/api/products');
$products = $productsJson ? json_decode($productsJson, true) : [];
$inventoryJson = @file_get_contents($apiBase . '/api/inventory');
$inventory = $inventoryJson ? json_decode($inventoryJson, true) : [];

// Get unique product types for dropdown
$productTypes = [];
if ($products) {
    foreach ($products as $product) {
        if (!in_array($product['productType'], $productTypes)) {
            $productTypes[] = $product['productType'];
        }
    }
}
sort($productTypes); // Sort alphabetically

// Group inventory items by product
$inventoryByProduct = [];
if ($inventory) {
    foreach ($inventory as $item) {
        $productId = $item['productId'];
        if (!isset($inventoryByProduct[$productId])) {
            $inventoryByProduct[$productId] = [];
        }
        $inventoryByProduct[$productId][] = $item;
    }
}

// Get unique product types for category dropdown
$categories = [];
if ($products) {
    foreach ($products as $product) {
        if (!empty($product['productType']) && !in_array($product['productType'], $categories)) {
            $categories[] = $product['productType'];
        }
    }
}
sort($categories);

// Get filter/search values
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterCategory = isset($_GET['category']) ? $_GET['category'] : 'all';

// Filter products by search and productType (category)
$filteredProducts = $products;
if ($searchTerm !== '' || ($filterCategory !== 'all' && $filterCategory !== '')) {
    $filteredProducts = array_filter($products, function($product) use ($searchTerm, $filterCategory) {
        $matchesSearch = true;
        $matchesCategory = true;
        // Search in product name, description, or SKU base
        if ($searchTerm !== '') {
            $matchesSearch = (
                stripos($product['name'], $searchTerm) !== false ||
                stripos($product['description'], $searchTerm) !== false ||
                stripos($product['defaultSKU_Base'], $searchTerm) !== false
            );
        }
        // Category filter: match productType
        if ($filterCategory !== 'all' && $filterCategory !== '') {
            $matchesCategory = ($product['productType'] === $filterCategory);
        }
        return $matchesSearch && $matchesCategory;
    });
}

// Fetch cost breakdowns for all inventory items
function fetchInventoryCosts($pdo, $inventoryId) {
    $materials = $pdo->query("SELECT * FROM inventory_materials WHERE inventoryId = '" . addslashes($inventoryId) . "'");
    $materials = $materials ? $materials->fetchAll(PDO::FETCH_ASSOC) : [];
    
    $labor = $pdo->query("SELECT * FROM inventory_labor WHERE inventoryId = '" . addslashes($inventoryId) . "'");
    $labor = $labor ? $labor->fetchAll(PDO::FETCH_ASSOC) : [];
    
    $energy = $pdo->query("SELECT * FROM inventory_energy WHERE inventoryId = '" . addslashes($inventoryId) . "'");
    $energy = $energy ? $energy->fetchAll(PDO::FETCH_ASSOC) : [];
    
    return [
        'materials' => $materials,
        'labor' => $labor,
        'energy' => $energy
    ];
}

// Move sumCost function definition to the top, after fetchInventoryCosts
function sumCost($arr) {
    $sum = 0;
    if (is_array($arr)) {
        foreach ($arr as $row) {
            $sum += floatval($row['cost']);
        }
    }
    return $sum;
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
  /* Toast notification styles */
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
<section id="adminInventoryPage" class="p-6 bg-white rounded-lg shadow-lg">
    <!-- Top bar: Back to Dashboard | Search/Filters | Export Inventory -->
    <div class="mb-4 flex flex-row justify-between items-center gap-2">
        <a href="/?page=admin" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium rounded-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Dashboard
        </a>
        <form action="" method="GET" class="flex flex-row items-center gap-2 mb-0" style="flex:1;max-width:600px;justify-content:center;">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="inventory">
            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($searchTerm ?? ''); ?>" class="block w-full px-2 py-1 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500" placeholder="Search..." style="max-width:140px;">
            <button type="submit" class="inline-flex items-center px-2 py-1 border border-transparent rounded-md shadow-sm text-xs font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>
            <select id="category" name="category" class="block px-2 py-1 border border-gray-300 rounded-md text-xs focus:ring-green-500 focus:border-green-500" style="max-width:120px;" onchange="this.form.submit()">
                <option value="all" <?php echo (empty($filterCategory) || $filterCategory === 'all') ? 'selected' : ''; ?>>All Products</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filterCategory === $cat) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($cat)); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500" onclick="window.location.href='/?page=admin&section=inventory&export=1'">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export Inventory
        </button>
    </div>

    <div class="grid grid-cols-1 gap-8">
        <?php if ($filteredProducts): ?>
            <?php foreach ($filteredProducts as $product): ?>
                <div class="bg-gray-100 p-6 rounded-lg shadow" data-product-id="<?php echo htmlspecialchars($product['id']); ?>">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Product Image -->
                        <div class="w-full md:w-1/3 flex flex-col">
                            <div class="relative flex-grow">
                                <img src="<?php echo htmlspecialchars($product['image'] ?? 'images/placeholder.png'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="w-full h-64 object-cover rounded-md">
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
                                    <label for="productName_<?php echo $product['id']; ?>" class="admin-data-label">Product Name</label>
                                    <input type="text" id="productName_<?php echo $product['id']; ?>" class="admin-data-value w-full border rounded px-2 py-1" value="<?php echo htmlspecialchars($product['name']); ?>">
                                </div>
                                <div>
                                    <label for="basePrice_<?php echo $product['id']; ?>" class="admin-data-label">Base Price</label>
                                    <div class="flex items-center">
                                        <input type="number" step="0.01" id="basePrice_<?php echo $product['id']; ?>" class="admin-data-value w-full border rounded px-2 py-1" value="<?php echo htmlspecialchars($product['basePrice']); ?>">
                                        <?php 
                                        // Calculate suggested retail price
                                        $pdo = new PDO('mysql:host=localhost;dbname=whimsicalfrog', 'root', 'Palz2516');
                                        $inventoryItems = $inventoryByProduct[$product['id']] ?? [];
                                        $totalCost = 0;
                                        
                                        if (!empty($inventoryItems)) {
                                            foreach ($inventoryItems as $item) {
                                                $costs = fetchInventoryCosts($pdo, $item['id']);
                                                $itemCost = sumCost($costs['materials'] ?? []) + sumCost($costs['labor'] ?? []) + sumCost($costs['energy'] ?? []);
                                                $totalCost += $itemCost;
                                            }
                                        }
                                        
                                        $suggestedRetail = $totalCost > 0 ? $totalCost * 2 : floatval($product['basePrice']) * 2;
                                        ?>
                                        <span class="ml-2 text-gray-600" id="suggestedRetail_<?php echo $product['id']; ?>">(Suggested: $<?php echo number_format($suggestedRetail, 2); ?>)</span>
                                    </div>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="description_<?php echo $product['id']; ?>" class="admin-data-label">Description</label>
                                    <textarea id="description_<?php echo $product['id']; ?>" class="admin-data-value w-full border rounded px-2 py-1" rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
                                </div>
                                <div>
                                    <label for="productType_<?php echo $product['id']; ?>" class="admin-data-label">Product Type</label>
                                    <input type="text" id="productType_<?php echo $product['id']; ?>" class="admin-data-value w-full border rounded px-2 py-1" value="<?php echo htmlspecialchars($product['productType']); ?>">
                                </div>
                                <div>
                                    <label for="defaultSKU_Base_<?php echo $product['id']; ?>" class="admin-data-label">SKU Base</label>
                                    <input type="text" id="defaultSKU_Base_<?php echo $product['id']; ?>" class="admin-data-value w-full border rounded px-2 py-1" value="<?php echo htmlspecialchars($product['defaultSKU_Base']); ?>">
                                </div>
                            </div>
                             <button onclick="updateProduct('<?php echo htmlspecialchars($product['id']); ?>')"
                                    class="mt-4 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md text-sm">
                                Update Product
                            </button>
                        </div>
                    </div>

                    <!-- Inventory Items - CONSOLIDATED VIEW -->
                    <?php if (!empty($inventoryByProduct[$product['id']])): ?>
                        <?php
                        // Aggregate inventory data
                        $inventoryItems = $inventoryByProduct[$product['id']];
                        $totalStock = 0;
                        $firstInventoryId = $inventoryItems[0]['id']; // Use first inventory item for cost management
                        $itemDescription = $product['name']; // Use product name for modal title
                        
                        // Calculate total stock
                        foreach ($inventoryItems as $item) {
                            $totalStock += intval($item['stockLevel']);
                        }
                        
                        // Fetch and combine costs from all inventory items
                        $pdo = new PDO('mysql:host=localhost;dbname=whimsicalfrog', 'root', 'Palz2516');
                        $allMaterials = [];
                        $allLabor = [];
                        $allEnergy = [];
                        $totalCost = 0;
                        
                        foreach ($inventoryItems as $item) {
                            $costs = fetchInventoryCosts($pdo, $item['id']);
                            $allMaterials = array_merge($allMaterials, $costs['materials'] ?? []);
                            $allLabor = array_merge($allLabor, $costs['labor'] ?? []);
                            $allEnergy = array_merge($allEnergy, $costs['energy'] ?? []);
                            $itemCost = sumCost($costs['materials'] ?? []) + sumCost($costs['labor'] ?? []) + sumCost($costs['energy'] ?? []);
                            $totalCost += $itemCost;
                        }
                        
                        // Create a JSON string of inventory item IDs for use in JavaScript
                        $inventoryItemIds = array_map(function($item) { return $item['id']; }, $inventoryItems);
                        $inventoryItemIdsJson = htmlspecialchars(json_encode($inventoryItemIds), ENT_QUOTES, 'UTF-8');
                        
                        // Create a JSON string of current stock levels for use in JavaScript
                        $stockLevels = array_map(function($item) { return intval($item['stockLevel']); }, $inventoryItems);
                        $stockLevelsJson = htmlspecialchars(json_encode($stockLevels), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="mt-6">
                            <div class="flex flex-col md:flex-row md:items-center gap-4">
                                <div>
                                    <span class="admin-data-label">Total Stock</span>
                                    <input type="number" min="0" value="<?php echo $totalStock; ?>" 
                                           class="total-stock-input admin-data-value border rounded px-2 py-1 text-sm w-20" 
                                           data-product-id="<?php echo $product['id']; ?>"
                                           data-inventory-items="<?php echo $inventoryItemIdsJson; ?>"
                                           data-stock-levels="<?php echo $stockLevelsJson; ?>">
                                </div>
                                <div>
                                    <button class="ml-2 px-3 py-2 bg-green-600 text-white rounded text-sm" onclick="openCostModal('<?php echo $firstInventoryId; ?>', '<?php echo htmlspecialchars($itemDescription); ?>')">Manage Costs</button>
                                </div>
                            </div>
                            <div class="mt-2 text-xs" id="costBreakdown_<?php echo $product['id']; ?>">
                                <strong>Total Cost:</strong> <span id="productTotalCost_<?php echo $product['id']; ?>">$<?php echo number_format($totalCost, 2); ?></span><br>
                                <strong>Materials:</strong>
                                <ul id="productMaterialsList_<?php echo $product['id']; ?>">
                                    <?php if (!empty($allMaterials)): foreach ($allMaterials as $mat): ?>
                                        <li><?php echo htmlspecialchars($mat['name']); ?>: $<?php echo number_format($mat['cost'], 2); ?></li>
                                    <?php endforeach; else: ?>
                                        <li>No materials added yet</li>
                                    <?php endif; ?>
                                </ul>
                                <strong>Labor:</strong>
                                <ul id="productLaborList_<?php echo $product['id']; ?>">
                                    <?php if (!empty($allLabor)): foreach ($allLabor as $lab): ?>
                                        <li><?php echo htmlspecialchars($lab['description']); ?>: $<?php echo number_format($lab['cost'], 2); ?></li>
                                    <?php endforeach; else: ?>
                                        <li>No labor costs added yet</li>
                                    <?php endif; ?>
                                </ul>
                                <strong>Energy:</strong>
                                <ul id="productEnergyList_<?php echo $product['id']; ?>">
                                    <?php if (!empty($allEnergy)): foreach ($allEnergy as $en): ?>
                                        <li><?php echo htmlspecialchars($en['description']); ?>: $<?php echo number_format($en['cost'], 2); ?></li>
                                    <?php endforeach; else: ?>
                                        <li>No energy costs added yet</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-6 text-gray-500 text-sm">No inventory items for this product.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-700">No products found</p>
        <?php endif; ?>
    </div>
    
    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>
</section>

<!-- Cost Management Modal -->
<div id="costModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
    <button class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl" onclick="closeCostModal()">&times;</button>
    <h2 class="text-xl font-bold mb-4">Manage Costs for <span id="costModalItemDescription"></span></h2>
    <div id="costModalContent">
      <div class="mb-4">
        <h3 class="font-semibold">Materials</h3>
        <ul id="materialsList" class="mb-2"></ul>
        <form id="addMaterialForm" class="flex gap-2 mb-2">
          <input type="text" name="name" placeholder="Material Name" class="border rounded px-2 py-1 text-sm" required>
          <input type="number" name="cost" placeholder="Cost" step="0.01" class="border rounded px-2 py-1 text-sm" required>
          <button type="submit" class="bg-green-600 text-white px-2 py-1 rounded text-xs">Add</button>
        </form>
      </div>
      <div class="mb-4">
        <h3 class="font-semibold">Labor</h3>
        <ul id="laborList" class="mb-2"></ul>
        <form id="addLaborForm" class="flex gap-2 mb-2">
          <input type="text" name="description" placeholder="Labor Description" class="border rounded px-2 py-1 text-sm" required>
          <input type="number" name="cost" placeholder="Cost" step="0.01" class="border rounded px-2 py-1 text-sm" required>
          <button type="submit" class="bg-green-600 text-white px-2 py-1 rounded text-xs">Add</button>
        </form>
      </div>
      <div class="mb-4">
        <h3 class="font-semibold">Energy</h3>
        <ul id="energyList" class="mb-2"></ul>
        <form id="addEnergyForm" class="flex gap-2 mb-2">
          <input type="text" name="description" placeholder="Energy Description" class="border rounded px-2 py-1 text-sm" required>
          <input type="number" name="cost" placeholder="Cost" step="0.01" class="border rounded px-2 py-1 text-sm" required>
          <button type="submit" class="bg-green-600 text-white px-2 py-1 rounded text-xs">Add</button>
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
</div>
<script>
// Environment detection for API base URL
const apiBase = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' 
    ? 'http://localhost:3000' 
    : 'https://whimsicalfrog.us';

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
    
    // Add event listeners for total stock inputs
    document.querySelectorAll('.total-stock-input').forEach(input => {
        input.addEventListener('change', updateTotalStock);
        input.addEventListener('blur', updateTotalStock);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                updateTotalStock.call(this);
            }
        });
    });
});

/**
 * Show a modern toast notification
 * @param {string} message - The message to display
 * @param {boolean} isError - Whether this is an error message
 * @param {number} duration - How long to show the notification in ms
 */
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

// Product management functions
async function updateProductField(productId, field, value) {
    try {
        const response = await fetch(`${apiBase}/api/update-product`, {
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
        
        showToast('Product updated successfully', false);
    } catch (error) {
        showToast(error.message, true);
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
        
        showToast('Uploading image...', false, 2000);
        
        try {
            const response = await fetch(`${apiBase}/api/upload-image`, {
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
            showToast('Image updated successfully', false);
        } catch (error) {
            showToast(error.message, true);
        }
    };
    
    input.click();
}

// Inventory management functions
async function updateInventoryField(inventoryId, field, value) {
    try {
        const response = await fetch(`${apiBase}/api/update-inventory`, {
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
        
        showToast('Inventory updated successfully', false);
    } catch (error) {
        showToast(error.message, true);
    }
}

/**
 * Update total stock across all inventory items for a product
 */
async function updateTotalStock() {
    const input = this;
    const newTotalStock = parseInt(input.value);
    const productId = input.getAttribute('data-product-id');
    const inventoryItems = JSON.parse(input.getAttribute('data-inventory-items'));
    const currentStockLevels = JSON.parse(input.getAttribute('data-stock-levels'));
    
    if (isNaN(newTotalStock) || newTotalStock < 0) {
        showToast('Please enter a valid stock quantity', true);
        input.value = currentStockLevels.reduce((a, b) => a + b, 0);
        return;
    }
    
    try {
        showToast('Updating stock levels...', false, 1000);
        
        // If there's only one inventory item, update it directly
        if (inventoryItems.length === 1) {
            const response = await fetch(`${apiBase}/api/update-inventory`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    inventoryId: inventoryItems[0],
                    field: 'stockLevel',
                    value: newTotalStock
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to update inventory stock');
            }
            
            // Update the data attribute with new stock level
            input.setAttribute('data-stock-levels', JSON.stringify([newTotalStock]));
            
            showToast('Stock level updated successfully', false);
            return;
        }
        
        // For multiple inventory items, distribute proportionally
        const currentTotal = currentStockLevels.reduce((a, b) => a + b, 0);
        
        // If current total is 0, distribute evenly
        if (currentTotal === 0) {
            const evenDistribution = Math.floor(newTotalStock / inventoryItems.length);
            const remainder = newTotalStock % inventoryItems.length;
            
            const newStockLevels = inventoryItems.map((_, index) => 
                index === 0 ? evenDistribution + remainder : evenDistribution
            );
            
            // Update each inventory item
            const updatePromises = inventoryItems.map((inventoryId, index) => 
                fetch(`${apiBase}/api/update-inventory`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        inventoryId,
                        field: 'stockLevel',
                        value: newStockLevels[index]
                    })
                })
            );
            
            await Promise.all(updatePromises);
            
            // Update the data attribute with new stock levels
            input.setAttribute('data-stock-levels', JSON.stringify(newStockLevels));
            
            showToast('Stock levels updated successfully', false);
            return;
        }
        
        // Distribute proportionally based on current stock levels
        const newStockLevels = currentStockLevels.map(stockLevel => {
            const proportion = stockLevel / currentTotal;
            return Math.round(newTotalStock * proportion);
        });
        
        // Adjust for rounding errors
        const calculatedTotal = newStockLevels.reduce((a, b) => a + b, 0);
        if (calculatedTotal !== newTotalStock) {
            const diff = newTotalStock - calculatedTotal;
            // Add or subtract the difference from the first non-zero item, or the first item if all are zero
            const indexToAdjust = newStockLevels.findIndex(s => s > 0) || 0;
            newStockLevels[indexToAdjust] += diff;
        }
        
        // Update each inventory item
        const updatePromises = inventoryItems.map((inventoryId, index) => 
            fetch(`${apiBase}/api/update-inventory`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    inventoryId,
                    field: 'stockLevel',
                    value: newStockLevels[index]
                })
            })
        );
        
        await Promise.all(updatePromises);
        
        // Update the data attribute with new stock levels
        input.setAttribute('data-stock-levels', JSON.stringify(newStockLevels));
        
        showToast('Stock levels updated successfully', false);
        
    } catch (error) {
        console.error('Error updating total stock:', error);
        showToast('Error updating stock: ' + error.message, true);
        // Reset to original value
        input.value = currentStockLevels.reduce((a, b) => a + b, 0);
    }
}

async function addInventoryItem(productId, productName) {
    try {
        showToast('Adding inventory item...', false, 1000);
        
        const response = await fetch(`${apiBase}/api/add-inventory`, {
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
        
        showToast('Inventory item added successfully!', false);
        
        // Reload the page to show the new item
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    } catch (error) {
        console.error('Error adding inventory item:', error);
        showToast('Error adding inventory item: ' + error.message, true);
    }
}

async function deleteInventoryItem(inventoryId) {
    if (!confirm('Are you sure you want to delete this inventory item?')) {
        return;
    }
    
    try {
        showToast('Deleting inventory item...', false, 1000);
        
        const response = await fetch(`${apiBase}/api/delete-inventory`, {
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
        
        showToast('Inventory item deleted successfully', false);
    } catch (error) {
        showToast(error.message, true);
    }
}

function logout() {
    sessionStorage.removeItem('user');
    window.location.href = '/login.php';
}

// Cost management functionality
let currentCostItemId = null;
let currentProductId = null;

// Open the cost modal and load data for the specified inventory item
function openCostModal(itemId, description) {
  currentCostItemId = itemId;
  document.getElementById('costModal').classList.remove('hidden');
  document.getElementById('costModalItemDescription').textContent = description || itemId;
  loadCostLists(itemId);
  
  // Store the product ID for later refresh
  getProductIdFromInventory(itemId).then(productId => {
    currentProductId = productId;
  });
}

// Close the cost modal
function closeCostModal() {
  document.getElementById('costModal').classList.add('hidden');
  currentCostItemId = null;
  currentProductId = null;
}

// Fetch costs from the API for the specified inventory item
async function loadCostLists(itemId) {
  try {
    // Show loading indicators
    document.getElementById('materialsList').innerHTML = '<li>Loading materials...</li>';
    document.getElementById('laborList').innerHTML = '<li>Loading labor costs...</li>';
    document.getElementById('energyList').innerHTML = '<li>Loading energy costs...</li>';
    
    // Fetch costs via API
    const response = await fetch(`${apiBase}/api/inventory-costs/${encodeURIComponent(itemId)}`);
    
    if (!response.ok) {
      throw new Error('Failed to load costs');
    }
    
    const data = await response.json();
    
    // Render each cost list
    renderCostList('materialsList', data.materials, 'material');
    renderCostList('laborList', data.labor, 'labor');
    renderCostList('energyList', data.energy, 'energy');
    
    // Update totals display
    document.getElementById('materialsTotalCost').textContent = `$${data.totals.materials.toFixed(2)}`;
    document.getElementById('laborTotalCost').textContent = `$${data.totals.labor.toFixed(2)}`;
    document.getElementById('energyTotalCost').textContent = `$${data.totals.energy.toFixed(2)}`;
    document.getElementById('grandTotalCost').textContent = `$${data.totals.grand.toFixed(2)}`;
  } catch (error) {
    console.error('Error loading costs:', error);
    showToast('Error loading costs: ' + error.message, true);
    
    // Show error messages
    document.getElementById('materialsList').innerHTML = '<li class="text-red-500">Error loading materials</li>';
    document.getElementById('laborList').innerHTML = '<li class="text-red-500">Error loading labor costs</li>';
    document.getElementById('energyList').innerHTML = '<li class="text-red-500">Error loading energy costs</li>';
  }
}

// Render a list of costs with edit/delete options
function renderCostList(listId, items, type) {
  const ul = document.getElementById(listId);
  ul.innerHTML = '';
  
  if (!items || items.length === 0) {
    ul.innerHTML = `<li class="text-gray-500">No ${type} costs added yet</li>`;
    return;
  }
  
  items.forEach(item => {
    const li = document.createElement('li');
    li.className = 'flex items-center gap-2 mb-2';
    
    let label = '';
    let editFields = '';
    
    if (type === 'material') {
      label = `${item.name}: $${parseFloat(item.cost).toFixed(2)}`;
      editFields = `
        <input type='text' value='${item.name}' class='edit-name border rounded px-1 text-xs' style='width:110px;'>
        <input type='number' value='${item.cost}' step='0.01' class='edit-cost border rounded px-1 text-xs' style='width:60px;'>
      `;
    } else {
      // For labor and energy
      label = `${item.description}: $${parseFloat(item.cost).toFixed(2)}`;
      editFields = `
        <input type='text' value='${item.description}' class='edit-desc border rounded px-1 text-xs' style='width:110px;'>
        <input type='number' value='${item.cost}' step='0.01' class='edit-cost border rounded px-1 text-xs' style='width:60px;'>
      `;
    }
    
    li.innerHTML = `
      <span class='view-mode'>${label}</span>
      <span class='edit-mode hidden'>${editFields}</span>
      <button onclick="editCost(this)" class="text-blue-600 text-xs ml-2 view-mode">Edit</button>
      <button onclick="saveCost('${type}', ${item.id}, this)" class="text-green-600 text-xs ml-2 edit-mode hidden">Save</button>
      <button onclick="cancelEdit(this)" class="text-gray-600 text-xs ml-1 edit-mode hidden">Cancel</button>
      <button onclick="deleteCost('${type}', ${item.id})" class="text-red-600 text-xs ml-2 view-mode">Delete</button>
    `;
    
    ul.appendChild(li);
  });
}

// Switch a cost item to edit mode
function editCost(btn) {
  const li = btn.closest('li');
  li.querySelectorAll('.view-mode').forEach(e => e.classList.add('hidden'));
  li.querySelectorAll('.edit-mode').forEach(e => e.classList.remove('hidden'));
}

// Cancel editing a cost item
function cancelEdit(btn) {
  const li = btn.closest('li');
  li.querySelectorAll('.edit-mode').forEach(e => e.classList.add('hidden'));
  li.querySelectorAll('.view-mode').forEach(e => e.classList.remove('hidden'));
}

// Save changes to a cost item
async function saveCost(type, id, btn) {
  try {
    showToast('Saving changes...', false, 1000);
    
    const li = btn.closest('li');
    let data = {};
    
    if (type === 'material') {
      data.name = li.querySelector('.edit-name').value;
      data.cost = li.querySelector('.edit-cost').value;
    } else {
      // For labor and energy
      data.description = li.querySelector('.edit-desc').value;
      data.cost = li.querySelector('.edit-cost').value;
    }
    
    // Validate input
    if ((type === 'material' && !data.name) || 
        (type !== 'material' && !data.description) || 
        !data.cost || isNaN(parseFloat(data.cost))) {
      showToast('Please fill in all fields with valid values', true);
      return;
    }
    
    const response = await fetch(`${apiBase}/api/update-cost`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, id, data })
    });
    
    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.error || 'Failed to update cost');
    }
    
    // Reload costs to show updated data
    await loadCostLists(currentCostItemId);
    showToast('Cost updated successfully', false);
    
    // Refresh the product data on the main page
    await refreshProductData();
  } catch (error) {
    console.error('Error saving cost:', error);
    showToast('Error saving cost: ' + error.message, true);
  }
}

// Delete a cost item
async function deleteCost(type, id) {
  if (!confirm(`Are you sure you want to delete this ${type} cost?`)) {
    return;
  }
  
  try {
    showToast('Deleting cost...', false, 1000);
    
    const response = await fetch(`${apiBase}/api/delete-cost`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, id })
    });
    
    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.error || 'Failed to delete cost');
    }
    
    // Reload costs to show updated data
    await loadCostLists(currentCostItemId);
    showToast(`${type} cost deleted successfully`, false);
    
    // Refresh the product data on the main page
    await refreshProductData();
  } catch (error) {
    console.error('Error deleting cost:', error);
    showToast('Error deleting cost: ' + error.message, true);
  }
}

// Add a new cost item
async function addCost(type, formData) {
  try {
    showToast('Adding cost...', false, 1000);
    
    // Validate input
    if ((type === 'material' && !formData.name) || 
        (type !== 'material' && !formData.description) || 
        !formData.cost || isNaN(parseFloat(formData.cost))) {
      showToast('Please fill in all fields with valid values', true);
      return;
    }
    
    // Add inventory ID to the data
    formData.inventoryId = currentCostItemId;
    
    const response = await fetch(`${apiBase}/api/add-cost`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, inventoryId: currentCostItemId, data: formData })
    });
    
    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.error || 'Failed to add cost');
    }
    
    // Reload costs to show updated data
    await loadCostLists(currentCostItemId);
    showToast(`${type} cost added successfully`, false);
    
    // Refresh the product data on the main page
    await refreshProductData();
    
    return true;
  } catch (error) {
    console.error('Error adding cost:', error);
    showToast('Error adding cost: ' + error.message, true);
    return false;
  }
}

/**
 * Get the product ID from an inventory item ID
 * @param {string} inventoryId - The inventory item ID
 * @returns {Promise<string>} - The product ID
 */
async function getProductIdFromInventory(inventoryId) {
  try {
    const response = await fetch(`${apiBase}/api/inventory`);
    if (!response.ok) {
      throw new Error('Failed to fetch inventory data');
    }
    
    const inventory = await response.json();
    const item = inventory.find(i => i.id === inventoryId);
    
    return item ? item.productId : null;
  } catch (error) {
    console.error('Error getting product ID:', error);
    return null;
  }
}

/**
 * Refresh product data on the main page without full page reload
 */
async function refreshProductData() {
  try {
    if (!currentProductId) {
      // If we don't have the product ID yet, get it from the inventory item
      currentProductId = await getProductIdFromInventory(currentCostItemId);
    }
    
    if (!currentProductId) {
      console.error('Could not determine product ID for refresh');
      showToast('Error: Could not find product to refresh.', true);
      return;
    }
    
    const productContainer = document.querySelector(`[data-product-id="${currentProductId}"]`);
    if (!productContainer) {
      console.error('Could not find product container for ID:', currentProductId);
      showToast('Error: Could not find product container on page.', true);
      return;
    }

    // Fetch updated consolidated costs for the product (using currentCostItemId)
    const costsResponse = await fetch(`${apiBase}/api/inventory-costs/${encodeURIComponent(currentCostItemId)}`);
    if (!costsResponse.ok) {
        const errorText = await costsResponse.text();
        throw new Error(`Failed to fetch updated costs: ${costsResponse.status} ${errorText}`);
    }
    const updatedCostsData = await costsResponse.json();

    // Get base price from the input field on the page
    const basePriceInput = productContainer.querySelector(`#basePrice_${currentProductId}`);
    const basePrice = basePriceInput ? parseFloat(basePriceInput.value) : 0;

    // Update Total Cost display
    const totalCostValueElement = productContainer.querySelector(`#productTotalCost_${currentProductId}`);
    if (totalCostValueElement) {
      totalCostValueElement.textContent = `$${updatedCostsData.totals.grand.toFixed(2)}`;
    }

    // Update Materials List
    const materialsListElement = productContainer.querySelector(`#productMaterialsList_${currentProductId}`);
    if (materialsListElement) {
      materialsListElement.innerHTML = ''; // Clear existing
      if (updatedCostsData.materials && updatedCostsData.materials.length > 0) {
        updatedCostsData.materials.forEach(mat => {
          const li = document.createElement('li');
          li.textContent = `${mat.name}: $${parseFloat(mat.cost).toFixed(2)}`;
          materialsListElement.appendChild(li);
        });
      } else {
        materialsListElement.innerHTML = '<li>No materials added yet</li>';
      }
    }

    // Update Labor List
    const laborListElement = productContainer.querySelector(`#productLaborList_${currentProductId}`);
     if (laborListElement) {
      laborListElement.innerHTML = '';
      if (updatedCostsData.labor && updatedCostsData.labor.length > 0) {
        updatedCostsData.labor.forEach(lab => {
          const li = document.createElement('li');
          li.textContent = `${lab.description}: $${parseFloat(lab.cost).toFixed(2)}`;
          laborListElement.appendChild(li);
        });
      } else {
        laborListElement.innerHTML = '<li>No labor costs added yet</li>';
      }
    }

    // Update Energy List
    const energyListElement = productContainer.querySelector(`#productEnergyList_${currentProductId}`);
    if (energyListElement) {
      energyListElement.innerHTML = '';
      if (updatedCostsData.energy && updatedCostsData.energy.length > 0) {
        updatedCostsData.energy.forEach(en => {
          const li = document.createElement('li');
          li.textContent = `${en.description}: $${parseFloat(en.cost).toFixed(2)}`;
          energyListElement.appendChild(li);
        });
      } else {
        energyListElement.innerHTML = '<li>No energy costs added yet</li>';
      }
    }
    
    // Update Suggested Retail Price
    const suggestedRetailSpan = productContainer.querySelector(`#suggestedRetail_${currentProductId}`);
    if (suggestedRetailSpan) {
        const newSuggestedRetail = updatedCostsData.totals.grand > 0 ? updatedCostsData.totals.grand * 2 : basePrice * 2;
        suggestedRetailSpan.textContent = `(Suggested: $${newSuggestedRetail.toFixed(2)})`;
    }

    showToast('Product costs on page refreshed.', false, 1500);
    
  } catch (error) {
    console.error('Error refreshing product data on page:', error);
    showToast('Could not refresh product data on page: ' + error.message, true);
  }
}


// Form submission handlers
document.getElementById('addMaterialForm').onsubmit = async function(e) {
  e.preventDefault();
  const form = e.target;
  const success = await addCost('material', { 
    name: form.name.value, 
    cost: form.cost.value 
  });
  if (success) {
    form.reset();
  }
};

document.getElementById('addLaborForm').onsubmit = async function(e) {
  e.preventDefault();
  const form = e.target;
  const success = await addCost('labor', { 
    description: form.description.value, 
    cost: form.cost.value 
  });
  if (success) {
    form.reset();
  }
};

document.getElementById('addEnergyForm').onsubmit = async function(e) {
  e.preventDefault();
  const form = e.target;
  const success = await addCost('energy', { 
    description: form.description.value, 
    cost: form.cost.value 
  });
  if (success) {
    form.reset();
  }
};

// Stock level update functionality
document.querySelectorAll('.stock-input').forEach(input => {
  input.addEventListener('change', updateStockLevel);
  input.addEventListener('blur', updateStockLevel);
  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      updateStockLevel.call(this);
    }
  });
});

async function updateStockLevel(e) {
  const input = this;
  const inventoryId = input.getAttribute('data-inventory-id');
  const newValue = input.value;
  
  try {
    showToast('Updating stock level...', false, 1000);
    
    const response = await fetch(`${apiBase}/api/update-inventory`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        inventoryId: inventoryId, 
        field: 'stockLevel',
        value: newValue 
      })
    });
    
    if (!response.ok) {
      throw new Error('Failed to update stock level');
    }
    
    input.classList.add('bg-green-100');
    setTimeout(() => input.classList.remove('bg-green-100'), 800);
    showToast('Stock level updated successfully', false);
  } catch (error) {
    console.error('Error updating stock level:', error);
    showToast('Error updating stock level: ' + error.message, true);
  }
}

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

    fetch(`${apiBase}/api/update-product`, {
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
</script> 

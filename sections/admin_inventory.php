<!-- MAGIC-TEST-COMMENT-20240607 -->
<?php
// Admin inventory management page

// Check if user is logged in and is an admin
$user = json_decode($_SESSION['user'] ?? '{}', true);
if (!isset($user['role']) || $user['role'] !== 'Admin') {
    header('Location: /login.php');
    exit;
}

// Fetch products and inventory data from Node API (MySQL)
$productsJson = @file_get_contents('http://localhost:3000/api/products');
$products = $productsJson ? json_decode($productsJson, true) : [];
$inventoryJson = @file_get_contents('http://localhost:3000/api/inventory');
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
    $materials = $pdo->query("SELECT * FROM inventory_materials WHERE inventoryId = '" . addslashes($inventoryId) . "'")->fetchAll(PDO::FETCH_ASSOC);
    $labor = $pdo->query("SELECT * FROM inventory_labor WHERE inventoryId = '" . addslashes($inventoryId) . "'")->fetchAll(PDO::FETCH_ASSOC);
    $energy = $pdo->query("SELECT * FROM inventory_energy WHERE inventoryId = '" . addslashes($inventoryId) . "'")->fetchAll(PDO::FETCH_ASSOC);
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
                <?php
                $pdo = new PDO('mysql:host=localhost;dbname=whimsicalfrog', 'root', 'Palz2516');
                $costs = fetchInventoryCosts($pdo, $product['id']);
                $totalCost = sumCost($costs['materials'] ?? []) + sumCost($costs['labor'] ?? []) + sumCost($costs['energy'] ?? []);
                ?>
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
                    <?php if (!empty($inventoryByProduct[$product['id']])): ?>
                        <?php foreach ($inventoryByProduct[$product['id']] as $item): ?>
                            <div class="mt-6">
                                <div class="flex flex-col md:flex-row md:items-center gap-4">
                                    <div>
                                        <span class="admin-data-label">Number in Stock</span>
                                        <input type="number" value="<?php echo htmlspecialchars($item['stockLevel']); ?>" min="0" class="stock-input admin-data-value border rounded px-2 py-1 text-sm w-20" data-inventory-id="<?php echo htmlspecialchars($item['id']); ?>">
                                    </div>
                                    <div>
                                        <button class="ml-2 px-3 py-2 bg-green-600 text-white rounded text-sm" onclick="openCostModal('<?php echo $item['id']; ?>')">Manage Costs</button>
                                    </div>
                                </div>
                                <div class="mt-2 text-xs">
                                    <strong>Total Cost:</strong> $<?php echo number_format($totalCost, 2); ?><br>
                                    <strong>Materials:</strong>
                                    <ul>
                                        <?php if (!empty($costs['materials'])): foreach ($costs['materials'] as $mat): ?>
                                            <li><?php echo htmlspecialchars($mat['name']); ?>: $<?php echo number_format($mat['cost'], 2); ?></li>
                                        <?php endforeach; endif; ?>
                                    </ul>
                                    <strong>Labor:</strong>
                                    <ul>
                                        <?php if (!empty($costs['labor'])): foreach ($costs['labor'] as $lab): ?>
                                            <li><?php echo htmlspecialchars($lab['description']); ?>: $<?php echo number_format($lab['cost'], 2); ?></li>
                                        <?php endforeach; endif; ?>
                                    </ul>
                                    <strong>Energy:</strong>
                                    <ul>
                                        <?php if (!empty($costs['energy'])): foreach ($costs['energy'] as $en): ?>
                                            <li><?php echo htmlspecialchars($en['description']); ?>: $<?php echo number_format($en['cost'], 2); ?></li>
                                        <?php endforeach; endif; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="mt-6 text-gray-500 text-sm">No inventory items for this product.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-700">No products found</p>
        <?php endif; ?>
    </div>
</section>

<!-- Cost Management Modal -->
<div id="costModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
    <button class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl" onclick="closeCostModal()">&times;</button>
    <h2 class="text-xl font-bold mb-4">Manage Costs for Inventory Item <span id="costModalItemId"></span></h2>
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
    </div>
  </div>
</div>
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

let currentCostItemId = null;
function openCostModal(itemId) {
  currentCostItemId = itemId;
  document.getElementById('costModal').classList.remove('hidden');
  document.getElementById('costModalItemId').textContent = itemId;
  loadCostLists(itemId);
}
function closeCostModal() {
  document.getElementById('costModal').classList.add('hidden');
  currentCostItemId = null;
}
async function loadCostLists(itemId) {
  // Fetch costs via AJAX
  const res = await fetch(`/api/inventory-costs.php?inventoryId=${encodeURIComponent(itemId)}`);
  const data = await res.json();
  renderCostList('materialsList', data.materials, 'material');
  renderCostList('laborList', data.labor, 'labor');
  renderCostList('energyList', data.energy, 'energy');
}
function renderCostList(listId, items, type) {
  const ul = document.getElementById(listId);
  ul.innerHTML = '';
  items.forEach(item => {
    const li = document.createElement('li');
    li.className = 'flex items-center gap-2';
    let label = '';
    let editFields = '';
    if (type === 'material') {
      label = `${item.name}: $${parseFloat(item.cost).toFixed(2)}`;
      editFields = `<input type='text' value='${item.name}' class='edit-name border rounded px-1 text-xs' style='width:90px;'> <input type='number' value='${item.cost}' step='0.01' class='edit-cost border rounded px-1 text-xs' style='width:60px;'>`;
    }
    if (type === 'labor' || type === 'energy') {
      label = `${item.description}: $${parseFloat(item.cost).toFixed(2)}`;
      editFields = `<input type='text' value='${item.description}' class='edit-desc border rounded px-1 text-xs' style='width:110px;'> <input type='number' value='${item.cost}' step='0.01' class='edit-cost border rounded px-1 text-xs' style='width:60px;'>`;
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
function editCost(btn) {
  const li = btn.closest('li');
  li.querySelectorAll('.view-mode').forEach(e => e.classList.add('hidden'));
  li.querySelectorAll('.edit-mode').forEach(e => e.classList.remove('hidden'));
}
function cancelEdit(btn) {
  const li = btn.closest('li');
  li.querySelectorAll('.edit-mode').forEach(e => e.classList.add('hidden'));
  li.querySelectorAll('.view-mode').forEach(e => e.classList.remove('hidden'));
}
async function saveCost(type, id, btn) {
  const li = btn.closest('li');
  let data = {};
  if (type === 'material') {
    data.name = li.querySelector('.edit-name').value;
    data.cost = li.querySelector('.edit-cost').value;
  } else {
    data.description = li.querySelector('.edit-desc').value;
    data.cost = li.querySelector('.edit-cost').value;
  }
  await fetch(`/api/inventory-costs.php?action=update&type=${type}&id=${id}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  loadCostLists(currentCostItemId);
}
document.getElementById('addMaterialForm').onsubmit = async function(e) {
  e.preventDefault();
  const form = e.target;
  await addCost('material', { name: form.name.value, cost: form.cost.value });
  form.reset();
};
document.getElementById('addLaborForm').onsubmit = async function(e) {
  e.preventDefault();
  const form = e.target;
  await addCost('labor', { description: form.description.value, cost: form.cost.value });
  form.reset();
};
document.getElementById('addEnergyForm').onsubmit = async function(e) {
  e.preventDefault();
  const form = e.target;
  await addCost('energy', { description: form.description.value, cost: form.cost.value });
  form.reset();
};
async function addCost(type, data) {
  data.inventoryId = currentCostItemId;
  await fetch(`/api/inventory-costs.php?action=add&type=${type}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  await loadCostLists(currentCostItemId);
}
async function deleteCost(type, id) {
  await fetch(`/api/inventory-costs.php?action=delete&type=${type}&id=${id}`, { method: 'POST' });
  loadCostLists(currentCostItemId);
}
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
  await fetch('/api/update-inventory-stock.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ inventoryId, stockLevel: newValue })
  });
  input.classList.add('bg-green-100');
  setTimeout(() => input.classList.remove('bg-green-100'), 800);
}
</script> 
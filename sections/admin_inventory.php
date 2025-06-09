<?php
// Admin Inventory Management Section
ob_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page with error
    header('Location: /?page=login&error=unauthorized');
    exit;
}

// Database connection
$pdo = new PDO($dsn, $user, $pass, $options);

// Get inventory items
$stmt = $pdo->query("SELECT * FROM inventory ORDER BY id");
$inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if we're in edit mode
$modalMode = 'add';
$editItem = null;
$editCostBreakdown = null;

if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $modalMode = 'edit';
    $itemId = $_GET['edit'];
    
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$itemId]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($editItem) {
        // Get cost breakdown data
        $materialStmt = $pdo->prepare("SELECT * FROM inventory_materials WHERE inventoryId = ?");
        $materialStmt->execute([$itemId]);
        $materials = $materialStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $laborStmt = $pdo->prepare("SELECT * FROM inventory_labor WHERE inventoryId = ?");
        $laborStmt->execute([$itemId]);
        $labor = $laborStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $energyStmt = $pdo->prepare("SELECT * FROM inventory_energy WHERE inventoryId = ?");
        $energyStmt->execute([$itemId]);
        $energy = $energyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $equipmentStmt = $pdo->prepare("SELECT * FROM inventory_equipment WHERE inventoryId = ?");
        $equipmentStmt->execute([$itemId]);
        $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $materialTotal = 0;
        foreach ($materials as $item) {
            $materialTotal += floatval($item['cost']);
        }
        
        $laborTotal = 0;
        foreach ($labor as $item) {
            $laborTotal += floatval($item['cost']);
        }
        
        $energyTotal = 0;
        foreach ($energy as $item) {
            $energyTotal += floatval($item['cost']);
        }
        
        $equipmentTotal = 0;
        foreach ($equipment as $item) {
            $equipmentTotal += floatval($item['cost']);
        }
        
        $suggestedCost = $materialTotal + $laborTotal + $energyTotal + $equipmentTotal;
        
        $editCostBreakdown = [
            'materials' => $materials,
            'labor' => $labor,
            'energy' => $energy,
            'equipment' => $equipment,
            'totals' => [
                'materialTotal' => $materialTotal,
                'laborTotal' => $laborTotal,
                'energyTotal' => $energyTotal,
                'equipmentTotal' => $equipmentTotal,
                'suggestedCost' => $suggestedCost,
                'currentCost' => $editItem['costPrice']
            ]
        ];
    }
}

// Check if we're in add mode
if (isset($_GET['add']) && $_GET['add'] == 1) {
    $modalMode = 'add';
}

// Get categories for dropdown
$stmt = $pdo->query("SELECT DISTINCT category FROM inventory ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Function to generate a new ID
function generateNewId($prefix, $pdo, $column, $table) {
    $stmt = $pdo->query("SELECT $column FROM $table ORDER BY CAST(SUBSTRING($column, 2) AS UNSIGNED) DESC LIMIT 1");
    $lastRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastNum = $lastRow ? (int)substr($lastRow[$column], 1) : 0;
    return $prefix . str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
}

// Get next available IDs
$nextItemId = generateNewId('I', $pdo, 'id', 'inventory');
$nextProductId = generateNewId('P', $pdo, 'productId', 'inventory');
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-green-800">Inventory Management</h1>
        <button id="addInventoryBtn" class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded">
            Add New Item
        </button>
    </div>
    
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table id="inventoryTable" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reorder</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Retail</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($inventoryItems as $item): ?>
                <tr data-id="<?= htmlspecialchars($item['id']) ?>">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($item['id']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['productId']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 editable" data-field="name"><?= htmlspecialchars($item['name']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 editable" data-field="category"><?= htmlspecialchars($item['category']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 editable" data-field="sku"><?= htmlspecialchars($item['sku']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 editable" data-field="stockLevel"><?= htmlspecialchars($item['stockLevel']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 editable" data-field="reorderPoint"><?= htmlspecialchars($item['reorderPoint']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 editable" data-field="costPrice">$<?= number_format($item['costPrice'], 2) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 editable" data-field="retailPrice">$<?= number_format($item['retailPrice'], 2) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="?page=admin&section=inventory&edit=<?= htmlspecialchars($item['id']) ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                        <a href="#" class="text-red-600 hover:text-red-900 delete-item" data-id="<?= htmlspecialchars($item['id']) ?>">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Inventory Modal -->
<div id="inventoryModal" class="modal">
    <div class="modal-content max-w-4xl">
        <span class="close-button">&times;</span>
        <h2 class="text-2xl font-bold mb-6"><?= $modalMode === 'edit' ? 'Edit' : 'Add' ?> Inventory Item</h2>
        
        <form id="inventoryForm" action="/process_inventory_update.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?= $modalMode === 'edit' ? 'update' : 'add' ?>">
            <?php if ($modalMode === 'edit' && isset($editItem['id'])): ?>
                <input type="hidden" name="itemId" value="<?= htmlspecialchars($editItem['id']) ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="productId">
                            Product ID
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="productId" name="productId" type="text" 
                               value="<?= $modalMode === 'edit' && isset($editItem['productId']) ? htmlspecialchars($editItem['productId']) : htmlspecialchars($nextProductId) ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                            Name *
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="name" name="name" type="text" required 
                               value="<?= $modalMode === 'edit' && isset($editItem['name']) ? htmlspecialchars($editItem['name']) : '' ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="category">
                            Category *
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="category" name="category" type="text" required list="categoryList" 
                               value="<?= $modalMode === 'edit' && isset($editItem['category']) ? htmlspecialchars($editItem['category']) : '' ?>">
                        <datalist id="categoryList">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="sku">
                            SKU *
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="sku" name="sku" type="text" required 
                               value="<?= $modalMode === 'edit' && isset($editItem['sku']) ? htmlspecialchars($editItem['sku']) : '' ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="description">
                            Description
                        </label>
                        <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                                  id="description" name="description" rows="4"><?= $modalMode === 'edit' && isset($editItem['description']) ? htmlspecialchars($editItem['description']) : '' ?></textarea>
                    </div>
                </div>
                
                <div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="stockLevel">
                            Stock Level *
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="stockLevel" name="stockLevel" type="number" min="0" required 
                               value="<?= $modalMode === 'edit' && isset($editItem['stockLevel']) ? htmlspecialchars($editItem['stockLevel']) : '0' ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="reorderPoint">
                            Reorder Point *
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="reorderPoint" name="reorderPoint" type="number" min="0" required 
                               value="<?= $modalMode === 'edit' && isset($editItem['reorderPoint']) ? htmlspecialchars($editItem['reorderPoint']) : '5' ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="costPrice">
                            Cost Price ($) *
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="costPrice" name="costPrice" type="number" step="0.01" min="0" required 
                               value="<?= $modalMode === 'edit' && isset($editItem['costPrice']) ? htmlspecialchars($editItem['costPrice']) : '0.00' ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="retailPrice">
                            Retail Price ($) *
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="retailPrice" name="retailPrice" type="number" step="0.01" min="0" required 
                               value="<?= $modalMode === 'edit' && isset($editItem['retailPrice']) ? htmlspecialchars($editItem['retailPrice']) : '0.00' ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="imageUpload">
                            Product Image
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="imageUpload" name="imageUpload" type="file" accept="image/*">
                        <?php if ($modalMode === 'edit' && isset($editItem['imageUrl']) && !empty($editItem['imageUrl'])): ?>
                            <div class="mt-2">
                                <img src="<?= htmlspecialchars($editItem['imageUrl']) ?>" alt="Product Image" class="h-24 w-auto">
                                <input type="hidden" name="imageUrl" value="<?= htmlspecialchars($editItem['imageUrl']) ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($modalMode === 'edit'): ?>
            <div class="mt-6 border-t pt-4">
                <h3 class="text-lg font-bold mb-4">Cost Breakdown</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Materials -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-bold mb-2">Materials</h4>
                        <div id="materialsList" class="mb-3">
                            <!-- Materials will be rendered here by JS -->
                        </div>
                        <button type="button" onclick="addCostItem('materials')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm">
                            + Add Material
                        </button>
                    </div>
                    
                    <!-- Labor -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-bold mb-2">Labor</h4>
                        <div id="laborList" class="mb-3">
                            <!-- Labor will be rendered here by JS -->
                        </div>
                        <button type="button" onclick="addCostItem('labor')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm">
                            + Add Labor
                        </button>
                    </div>
                    
                    <!-- Energy -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-bold mb-2">Energy</h4>
                        <div id="energyList" class="mb-3">
                            <!-- Energy will be rendered here by JS -->
                        </div>
                        <button type="button" onclick="addCostItem('energy')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm">
                            + Add Energy
                        </button>
                    </div>
                    
                    <!-- Equipment -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-bold mb-2">Equipment</h4>
                        <div id="equipmentList" class="mb-3">
                            <!-- Equipment will be rendered here by JS -->
                        </div>
                        <button type="button" onclick="addCostItem('equipment')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm">
                            + Add Equipment
                        </button>
                    </div>
                </div>
                
                <div class="mt-4 bg-gray-100 p-4 rounded-lg">
                    <h4 class="font-bold mb-2">Cost Summary</h4>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div>
                            <span class="block text-sm text-gray-600">Materials</span>
                            <span id="materialTotal" class="font-bold">$0.00</span>
                        </div>
                        <div>
                            <span class="block text-sm text-gray-600">Labor</span>
                            <span id="laborTotal" class="font-bold">$0.00</span>
                        </div>
                        <div>
                            <span class="block text-sm text-gray-600">Energy</span>
                            <span id="energyTotal" class="font-bold">$0.00</span>
                        </div>
                        <div>
                            <span class="block text-sm text-gray-600">Equipment</span>
                            <span id="equipmentTotal" class="font-bold">$0.00</span>
                        </div>
                        <div>
                            <span class="block text-sm text-gray-600">Suggested Cost</span>
                            <span id="suggestedCost" class="font-bold">$0.00</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" id="applySuggestedCost" class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded text-sm">
                            Apply Suggested Cost
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-6 flex justify-end">
                <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2 close-modal">
                    Cancel
                </button>
                <button type="submit" class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded">
                    Save Item
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Cost Item Form Modal -->
<div id="costFormModal" class="modal">
    <div class="modal-content max-w-md">
        <span class="close-button">&times;</span>
        <h2 class="text-xl font-bold mb-4" id="costFormTitle">Add Cost Item</h2>
        
        <form id="costForm">
            <input type="hidden" id="costItemId" name="id" value="">
            <input type="hidden" id="costItemType" name="costType" value="">
            
            <div class="mb-4" id="nameFieldContainer">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="costItemName">
                    Name
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                       id="costItemName" name="name" type="text" required>
            </div>
            
            <div class="mb-4" id="descriptionFieldContainer">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="costItemDescription">
                    Description
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                       id="costItemDescription" name="description" type="text" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="costItemCost">
                    Cost ($)
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                       id="costItemCost" name="cost" type="number" step="0.01" min="0" required>
            </div>
            
            <div class="flex justify-between">
                <button type="button" id="deleteCostItem" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded hidden">
                    Delete
                </button>
                <div class="flex">
                    <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2 close-modal">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="modal">
    <div class="modal-content max-w-md">
        <span class="close-button">&times;</span>
        <h2 class="text-xl font-bold mb-4">Confirm Delete</h2>
        <p class="mb-6">Are you sure you want to delete this item? This action cannot be undone.</p>
        <div class="flex justify-end">
            <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2 close-modal">
                Cancel
            </button>
            <button type="button" id="confirmDelete" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                Delete
            </button>
        </div>
    </div>
</div>

<script>
// --- Admin Inventory Management JavaScript ---
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Inventory DOMContentLoaded');
    
    // Initialize variables
    let currentItemId = '<?= $modalMode === 'edit' && isset($editItem['id']) ? $editItem['id'] : '' ?>';
    console.log('Initial currentItemId:', currentItemId);
    
    let costBreakdown = <?= $modalMode === 'edit' && $editCostBreakdown ? json_encode($editCostBreakdown) : 'null' ?>;
    console.log('Initial costBreakdown JS object:', costBreakdown);
    
    // Find inventory table
    const inventoryTable = document.getElementById('inventoryTable');
    if (inventoryTable) {
        console.log('Inventory table found, attaching inline edit listeners.');
        
        // Add event listeners for inline editing
        const editableCells = inventoryTable.querySelectorAll('.editable');
        editableCells.forEach(cell => {
            cell.addEventListener('click', function() {
                // Check if already editing
                if (cell.querySelector('input')) return;
                
                const value = cell.innerText;
                const field = cell.dataset.field;
                const itemId = cell.parentNode.dataset.id;
                
                // Save original value for cancel
                cell.dataset.originalValue = value;
                
                // Create input field
                const input = document.createElement('input');
                input.type = field === 'costPrice' || field === 'retailPrice' ? 'number' : 'text';
                if (input.type === 'number') {
                    input.step = '0.01';
                    input.min = '0';
                    input.value = value.replace('$', '');
                } else {
                    input.value = value;
                }
                input.className = 'w-full p-1 border rounded';
                
                // Clear the cell and add input
                cell.innerHTML = '';
                cell.appendChild(input);
                input.focus();
                
                // Handle input blur (save)
                input.addEventListener('blur', function() {
                    saveInlineEdit(itemId, field, input.value, cell);
                });
                
                // Handle Enter key
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        saveInlineEdit(itemId, field, input.value, cell);
                    } else if (e.key === 'Escape') {
                        // Cancel edit
                        cell.innerHTML = cell.dataset.originalValue;
                    }
                });
            });
        });
    }
    
    // Function to save inline edits
    function saveInlineEdit(itemId, field, value, cell) {
        // Validate input
        if (field === 'stockLevel' || field === 'reorderPoint') {
            if (!Number.isInteger(Number(value)) || Number(value) < 0) {
                showToast('error', `${field} must be a non-negative integer`);
                cell.innerHTML = cell.dataset.originalValue;
                return;
            }
        } else if (field === 'costPrice' || field === 'retailPrice') {
            if (isNaN(parseFloat(value)) || parseFloat(value) < 0) {
                showToast('error', `${field} must be a non-negative number`);
                cell.innerHTML = cell.dataset.originalValue;
                return;
            }
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('itemId', itemId);
        formData.append('field', field);
        formData.append('value', value);
        
        // Send AJAX request
        fetch('/process_inventory_update.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the cell with formatted value
                if (field === 'costPrice' || field === 'retailPrice') {
                    cell.innerHTML = '$' + parseFloat(value).toFixed(2);
                } else {
                    cell.innerHTML = value;
                }
                showToast('success', data.message);
            } else {
                showToast('error', data.error);
                cell.innerHTML = cell.dataset.originalValue;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Failed to update field');
            cell.innerHTML = cell.dataset.originalValue;
        });
    }
    
    // Handle inventory form
    const inventoryForm = document.getElementById('inventoryForm');
    if (inventoryForm) {
        console.log('Inventory form #inventoryForm found.');
        
        inventoryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            console.log('Submitting inventory form via AJAX. Action URL:', inventoryForm.action);
            const formData = new FormData(inventoryForm);
            console.log('Form Data:', Object.fromEntries(formData.entries()));
            
            fetch(inventoryForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Raw response status:', response.status, response.statusText);
                return response.text().then(text => {
                    console.log('Raw response text:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw {
                            error: 'Invalid JSON response from server.',
                            details: text,
                            rawText: text
                        };
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    // Redirect after success
                    setTimeout(() => {
                        window.location.href = '?page=admin&section=inventory';
                    }, 1000);
                } else {
                    showToast('error', data.error || 'Failed to save item');
                }
            })
            .catch(error => {
                console.error('Error saving item (Fetch Catch):', error);
                showToast('error', error.error || 'Failed to save item');
            });
        });
    }
    
    // Image upload handling
    const imageUpload = document.getElementById('imageUpload');
    const imagePreview = document.querySelector('#inventoryForm img');
    const imageUrlInput = document.querySelector('input[name="imageUrl"]');
    
    if (imageUpload) {
        console.log('Image upload elements found.');
        
        imageUpload.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const formData = new FormData();
                formData.append('image', file);
                formData.append('itemId', currentItemId);
                
                fetch('/process_image_upload.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create or update preview
                        if (!imagePreview) {
                            const previewContainer = document.createElement('div');
                            previewContainer.className = 'mt-2';
                            
                            const newPreview = document.createElement('img');
                            newPreview.src = data.imageUrl;
                            newPreview.alt = 'Product Image';
                            newPreview.className = 'h-24 w-auto';
                            
                            const newInput = document.createElement('input');
                            newInput.type = 'hidden';
                            newInput.name = 'imageUrl';
                            newInput.value = data.imageUrl;
                            
                            previewContainer.appendChild(newPreview);
                            previewContainer.appendChild(newInput);
                            imageUpload.parentNode.appendChild(previewContainer);
                        } else {
                            imagePreview.src = data.imageUrl;
                            if (imageUrlInput) {
                                imageUrlInput.value = data.imageUrl;
                            } else {
                                const newInput = document.createElement('input');
                                newInput.type = 'hidden';
                                newInput.name = 'imageUrl';
                                newInput.value = data.imageUrl;
                                imagePreview.parentNode.appendChild(newInput);
                            }
                        }
                        
                        showToast('success', 'Image uploaded successfully');
                    } else {
                        showToast('error', data.error || 'Failed to upload image');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'Failed to upload image');
                });
            }
        });
    }
    
    // Cost breakdown management
    const costForm = document.getElementById('costForm');
    if (costForm) {
        console.log('Cost form #costForm found.');
        
        costForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const id = document.getElementById('costItemId').value;
            const type = document.getElementById('costItemType').value;
            const name = document.getElementById('costItemName')?.value || '';
            const description = document.getElementById('costItemDescription')?.value || '';
            const cost = document.getElementById('costItemCost').value;
            
            saveCostItem(id, type, cost, name, description);
        });
    }
    
    // Delete cost item button
    const deleteCostItemBtn = document.getElementById('deleteCostItem');
    if (deleteCostItemBtn) {
        console.log('Confirm delete cost button found.');
        
        deleteCostItemBtn.addEventListener('click', function() {
            const id = document.getElementById('costItemId').value;
            const type = document.getElementById('costItemType').value;
            
            if (confirm('Are you sure you want to delete this cost item?')) {
                deleteCostItem(id, type);
            }
        });
    }
    
    // Initialize cost breakdown if in edit mode
    if (currentItemId && costBreakdown) {
        console.log('Modal is open for editing/adding, attempting to render initial cost breakdown.');
        refreshCostBreakdown(true);
    }
    
    // Add Cost Item function - this was missing and causing errors
    function addCostItem(type) {
        // Reset form
        document.getElementById('costItemId').value = '';
        document.getElementById('costItemType').value = type;
        document.getElementById('costItemCost').value = '';
        
        // Show/hide fields based on type
        const nameContainer = document.getElementById('nameFieldContainer');
        const descContainer = document.getElementById('descriptionFieldContainer');
        
        if (type === 'materials') {
            document.getElementById('costFormTitle').textContent = 'Add Material Cost';
            nameContainer.style.display = 'block';
            descContainer.style.display = 'none';
            document.getElementById('costItemName').value = '';
        } else {
            document.getElementById('costFormTitle').textContent = `Add ${type.charAt(0).toUpperCase() + type.slice(1)} Cost`;
            nameContainer.style.display = 'none';
            descContainer.style.display = 'block';
            document.getElementById('costItemDescription').value = '';
        }
        
        // Hide delete button for new items
        document.getElementById('deleteCostItem').classList.add('hidden');
        
        // Show modal
        document.getElementById('costFormModal').style.display = 'block';
    }
    
    // Edit Cost Item function
    function editCostItem(type, id) {
        console.log('Editing cost item. Type:', type, 'ID:', id);
        
        // Find the item in the costBreakdown object
        const item = costBreakdown[type].find(i => i.id == id);
        if (!item) {
            showToast('error', 'Cost item not found');
            return;
        }
        
        // Set form values
        document.getElementById('costItemId').value = id;
        document.getElementById('costItemType').value = type;
        document.getElementById('costItemCost').value = item.cost;
        
        // Show/hide fields based on type
        const nameContainer = document.getElementById('nameFieldContainer');
        const descContainer = document.getElementById('descriptionFieldContainer');
        
        if (type === 'materials') {
            document.getElementById('costFormTitle').textContent = 'Edit Material Cost';
            nameContainer.style.display = 'block';
            descContainer.style.display = 'none';
            document.getElementById('costItemName').value = item.name || '';
        } else {
            document.getElementById('costFormTitle').textContent = `Edit ${type.charAt(0).toUpperCase() + type.slice(1)} Cost`;
            nameContainer.style.display = 'none';
            descContainer.style.display = 'block';
            document.getElementById('costItemDescription').value = item.description || '';
        }
        
        // Show delete button for existing items
        document.getElementById('deleteCostItem').classList.remove('hidden');
        
        // Show modal
        document.getElementById('costFormModal').style.display = 'block';
    }
    
    // Save Cost Item function
    function saveCostItem(id, type, cost, name, description) {
        console.log('Saving cost item. ID:', id, 'Type:', type, 'Cost:', cost);
        
        const payload = { 
            costType: type, 
            cost: parseFloat(cost), 
            name: name, 
            description: description, 
            inventoryId: currentItemId 
        };
        
        if (id) {
            payload.id = id;
        }
        
        fetch('/process_cost_breakdown.php', {
            method: id ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', data.message);
                document.getElementById('costFormModal').style.display = 'none';
                refreshCostBreakdown();
            } else {
                showToast('error', data.error || 'Failed to save cost item');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Failed to save cost item');
        });
    }
    
    // Delete Cost Item function
    function deleteCostItem(id, type) {
        fetch(`/process_cost_breakdown.php?id=${id}&costType=${type}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', data.message);
                document.getElementById('costFormModal').style.display = 'none';
                refreshCostBreakdown();
            } else {
                showToast('error', data.error || 'Failed to delete cost item');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Failed to delete cost item');
        });
    }
    
    // Refresh Cost Breakdown function
    function refreshCostBreakdown(useExistingData = false) {
        console.log('Refreshing cost breakdown. currentItemId:', currentItemId, 'Use existing data:', useExistingData);
        
        if (!currentItemId) {
            console.warn('Cannot refresh cost breakdown: No current item ID');
            return;
        }
        
        if (useExistingData && costBreakdown) {
            console.log('Using existing costBreakdown data to re-render.');
            renderCostBreakdown(costBreakdown);
            return;
        }
        
        // Fetch latest cost breakdown data
        fetch(`/process_cost_breakdown.php?inventoryId=${currentItemId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Fetched new cost breakdown data:', data);
            if (data.success) {
                costBreakdown = data.data;
                renderCostBreakdown(costBreakdown);
            } else {
                showToast('error', data.error || 'Failed to load cost breakdown');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Failed to load cost breakdown');
        });
    }
    
    // Render Cost Breakdown function
    function renderCostBreakdown(data) {
        // Render each cost type list
        renderCostList('materials', data.materials);
        renderCostList('labor', data.labor);
        renderCostList('energy', data.energy);
        renderCostList('equipment', data.equipment);
        
        // Update totals display
        updateTotalsDisplay(data.totals);
    }
    
    // Render Cost List function
    function renderCostList(type, items) {
        console.log('Rendering cost list for type:', type, 'items:', items);
        const listElement = document.getElementById(`${type}List`);
        if (!listElement) return;
        
        listElement.innerHTML = '';
        
        if (!items || items.length === 0) {
            listElement.innerHTML = '<p class="text-gray-500 text-sm italic">No items added yet.</p>';
            return;
        }
        
        items.forEach(item => {
            const itemElement = document.createElement('div');
            itemElement.className = 'flex justify-between items-center mb-2 p-2 bg-white rounded shadow-sm';
            
            const nameElement = document.createElement('div');
            nameElement.className = 'flex-grow';
            
            if (type === 'materials') {
                nameElement.textContent = item.name;
            } else {
                nameElement.textContent = item.description;
            }
            
            const costElement = document.createElement('div');
            costElement.className = 'ml-4 font-bold';
            costElement.textContent = `$${parseFloat(item.cost).toFixed(2)}`;
            
            const editButton = document.createElement('button');
            editButton.className = 'ml-2 text-blue-600 hover:text-blue-800';
            editButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>';
            editButton.addEventListener('click', () => editCostItem(type, item.id));
            
            itemElement.appendChild(nameElement);
            itemElement.appendChild(costElement);
            itemElement.appendChild(editButton);
            
            listElement.appendChild(itemElement);
        });
    }
    
    // Update Totals Display function
    function updateTotalsDisplay(totals) {
        console.log('Updating totals display. Current costBreakdown.totals:', totals);
        
        document.getElementById('materialTotal').textContent = `$${parseFloat(totals.materialTotal).toFixed(2)}`;
        document.getElementById('laborTotal').textContent = `$${parseFloat(totals.laborTotal).toFixed(2)}`;
        document.getElementById('energyTotal').textContent = `$${parseFloat(totals.energyTotal).toFixed(2)}`;
        document.getElementById('equipmentTotal').textContent = `$${parseFloat(totals.equipmentTotal).toFixed(2)}`;
        document.getElementById('suggestedCost').textContent = `$${parseFloat(totals.suggestedCost).toFixed(2)}`;
        
        console.log('Totals updated: Mat=$' + parseFloat(totals.materialTotal).toFixed(2) + 
                   ', Lab=$' + parseFloat(totals.laborTotal).toFixed(2) + 
                   ', Eng=$' + parseFloat(totals.energyTotal).toFixed(2) + 
                   ', Equ=$' + parseFloat(totals.equipmentTotal).toFixed(2) + 
                   ', Sug=$' + parseFloat(totals.suggestedCost).toFixed(2));
    }
    
    // Apply Suggested Cost button
    const applySuggestedCostBtn = document.getElementById('applySuggestedCost');
    if (applySuggestedCostBtn) {
        applySuggestedCostBtn.addEventListener('click', function() {
            if (!costBreakdown || !costBreakdown.totals) {
                showToast('error', 'Cost breakdown not available');
                return;
            }
            
            const suggestedCost = parseFloat(costBreakdown.totals.suggestedCost);
            document.getElementById('costPrice').value = suggestedCost.toFixed(2);
            
            // Also update the cost price in the database
            fetch('/process_inventory_update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    id: currentItemId,
                    costPrice: suggestedCost
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Cost price updated to suggested cost');
                } else {
                    showToast('error', data.error || 'Failed to update cost price');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Failed to update cost price');
            });
        });
    }
    
    // Modal management
    const addInventoryBtn = document.getElementById('addInventoryBtn');
    const inventoryModal = document.getElementById('inventoryModal');
    const costFormModal = document.getElementById('costFormModal');
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const closeButtons = document.querySelectorAll('.close-button, .close-modal');
    
    // Open inventory modal
    if (addInventoryBtn && inventoryModal) {
        addInventoryBtn.addEventListener('click', function() {
            inventoryModal.style.display = 'block';
        });
    }
    
    // Close modals with close buttons
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            inventoryModal.style.display = 'none';
            costFormModal.style.display = 'none';
            deleteConfirmModal.style.display = 'none';
        });
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === inventoryModal) {
            inventoryModal.style.display = 'none';
        } else if (event.target === costFormModal) {
            costFormModal.style.display = 'none';
        } else if (event.target === deleteConfirmModal) {
            deleteConfirmModal.style.display = 'none';
        }
    });
    
    // Delete item handlers
    const deleteButtons = document.querySelectorAll('.delete-item');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    let itemToDelete = null;
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            itemToDelete = this.dataset.id;
            deleteConfirmModal.style.display = 'block';
        });
    });
    
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (!itemToDelete) return;
            
            fetch(`/process_inventory_update.php?action=delete&itemId=${itemToDelete}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    // Reload page after deletion
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('error', data.error || 'Failed to delete item');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Failed to delete item');
            });
            
            deleteConfirmModal.style.display = 'none';
        });
    }
    
    // Show inventory modal if in edit or add mode
    if ((currentItemId && modalMode === 'edit') || modalMode === 'add') {
        inventoryModal.style.display = 'block';
    }
    
    // Toast notification function
    function showToast(type, message) {
        console.log('Toast:', `[${type}]`, message);
        
        // Create toast if it doesn't exist
        let toast = document.getElementById('toast-notification');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-notification';
            toast.className = 'fixed top-4 right-4 p-4 rounded shadow-lg z-50 transform transition-all duration-500 translate-y-0 opacity-0';
            document.body.appendChild(toast);
        }
        
        // Set toast style based on type
        if (type === 'success') {
            toast.className = 'fixed top-4 right-4 p-4 rounded shadow-lg z-50 bg-green-100 border-l-4 border-green-500 text-green-700 transform transition-all duration-500 translate-y-0 opacity-100';
        } else if (type === 'error') {
            toast.className = 'fixed top-4 right-4 p-4 rounded shadow-lg z-50 bg-red-100 border-l-4 border-red-500 text-red-700 transform transition-all duration-500 translate-y-0 opacity-100';
        } else {
            toast.className = 'fixed top-4 right-4 p-4 rounded shadow-lg z-50 bg-blue-100 border-l-4 border-blue-500 text-blue-700 transform transition-all duration-500 translate-y-0 opacity-100';
        }
        
        // Set message
        toast.textContent = message;
        
        // Show toast
        setTimeout(() => {
            toast.className = toast.className.replace('opacity-0', 'opacity-100');
        }, 10);
        
        // Hide toast after 3 seconds
        setTimeout(() => {
            toast.className = toast.className.replace('opacity-100', 'opacity-0');
            setTimeout(() => {
                toast.remove();
            }, 500);
        }, 3000);
    }
    
    // Check if cart is initialized
    console.log('DOM loaded, checking cart initialization...');
    if (typeof window.shoppingCart !== 'undefined') {
        console.log('Cart is initialized and ready');
    } else {
        console.log('Cart is not initialized yet');
    }
});
</script>

<?php
$output = ob_get_clean();
echo $output;
?>

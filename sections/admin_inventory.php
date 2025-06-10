<?php
// Admin Inventory Management Section
ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// The authentication check is now handled by index.php before including this file

// Include database configuration
require_once __DIR__ . '/../api/config.php';

// Database connection
$pdo = new PDO($dsn, $user, $pass, $options);

// Get inventory items
$stmt = $pdo->query("SELECT * FROM inventory ORDER BY id");
$inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize modal state
$modalMode = ''; // Default to no modal unless 'add', 'edit', or 'view' is in URL
$editItem = null;
$editCostBreakdown = null;
$field_errors = $_SESSION['field_errors'] ?? []; // For highlighting fields with errors
unset($_SESSION['field_errors']);


// Check if we're in view mode
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $itemIdToView = $_GET['view'];
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$itemIdToView]);
    $fetchedViewItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fetchedViewItem) {
        $modalMode = 'view';
        $editItem = $fetchedViewItem; // Reuse editItem for view mode

        // Get cost breakdown data
        $materialStmt = $pdo->prepare("SELECT * FROM inventory_materials WHERE inventoryId = ?");
        $materialStmt->execute([$editItem['id']]);
        $materials = $materialStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $laborStmt = $pdo->prepare("SELECT * FROM inventory_labor WHERE inventoryId = ?");
        $laborStmt->execute([$editItem['id']]);
        $labor = $laborStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $energyStmt = $pdo->prepare("SELECT * FROM inventory_energy WHERE inventoryId = ?");
        $energyStmt->execute([$editItem['id']]);
        $energy = $energyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $equipmentStmt = $pdo->prepare("SELECT * FROM inventory_equipment WHERE inventoryId = ?");
        $equipmentStmt->execute([$editItem['id']]);
        $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $materialTotal = 0; foreach ($materials as $item_cost) { $materialTotal += floatval($item_cost['cost']); }
        $laborTotal = 0; foreach ($labor as $item_cost) { $laborTotal += floatval($item_cost['cost']); }
        $energyTotal = 0; foreach ($energy as $item_cost) { $energyTotal += floatval($item_cost['cost']); }
        $equipmentTotal = 0; foreach ($equipment as $item_cost) { $equipmentTotal += floatval($item_cost['cost']); }
        $suggestedCost = $materialTotal + $laborTotal + $energyTotal + $equipmentTotal;
        
        $editCostBreakdown = [
            'materials' => $materials, 'labor' => $labor, 'energy' => $energy, 'equipment' => $equipment,
            'totals' => [
                'materialTotal' => $materialTotal, 'laborTotal' => $laborTotal, 
                'energyTotal' => $energyTotal, 'equipmentTotal' => $equipmentTotal,
                'suggestedCost' => $suggestedCost, 'currentCost' => $editItem['costPrice']
            ]
        ];
    }
}
// Check if we're in edit mode
elseif (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $itemIdToEdit = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$itemIdToEdit]);
    $fetchedEditItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fetchedEditItem) {
        $modalMode = 'edit';
        $editItem = $fetchedEditItem; 

        // Get cost breakdown data
        $materialStmt = $pdo->prepare("SELECT * FROM inventory_materials WHERE inventoryId = ?");
        $materialStmt->execute([$editItem['id']]);
        $materials = $materialStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $laborStmt = $pdo->prepare("SELECT * FROM inventory_labor WHERE inventoryId = ?");
        $laborStmt->execute([$editItem['id']]);
        $labor = $laborStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $energyStmt = $pdo->prepare("SELECT * FROM inventory_energy WHERE inventoryId = ?");
        $energyStmt->execute([$editItem['id']]);
        $energy = $energyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $equipmentStmt = $pdo->prepare("SELECT * FROM inventory_equipment WHERE inventoryId = ?");
        $equipmentStmt->execute([$editItem['id']]);
        $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $materialTotal = 0; foreach ($materials as $item_cost) { $materialTotal += floatval($item_cost['cost']); }
        $laborTotal = 0; foreach ($labor as $item_cost) { $laborTotal += floatval($item_cost['cost']); }
        $energyTotal = 0; foreach ($energy as $item_cost) { $energyTotal += floatval($item_cost['cost']); }
        $equipmentTotal = 0; foreach ($equipment as $item_cost) { $equipmentTotal += floatval($item_cost['cost']); }
        $suggestedCost = $materialTotal + $laborTotal + $energyTotal + $equipmentTotal;
        
        $editCostBreakdown = [
            'materials' => $materials, 'labor' => $labor, 'energy' => $energy, 'equipment' => $equipment,
            'totals' => [
                'materialTotal' => $materialTotal, 'laborTotal' => $laborTotal, 
                'energyTotal' => $energyTotal, 'equipmentTotal' => $equipmentTotal,
                'suggestedCost' => $suggestedCost, 'currentCost' => $editItem['costPrice']
            ]
        ];
    }
} elseif (isset($_GET['add']) && $_GET['add'] == 1) {
    $modalMode = 'add';
    // For 'add' mode, pre-calculate next IDs
    $stmt = $pdo->query("SELECT id FROM inventory ORDER BY CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC LIMIT 1");
    $lastIdRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastIdNum = $lastIdRow ? (int)substr($lastIdRow['id'], 1) : 0;
    $nextItemId = 'I' . str_pad($lastIdNum + 1, 3, '0', STR_PAD_LEFT);

    $stmtProd = $pdo->query("SELECT productId FROM inventory ORDER BY CAST(SUBSTRING(productId, 2) AS UNSIGNED) DESC LIMIT 1");
    $lastProdIdRow = $stmtProd->fetch(PDO::FETCH_ASSOC);
    $lastProdIdNum = $lastProdIdRow ? (int)substr($lastProdIdRow['productId'], 1) : 0;
    $nextProductId = 'P' . str_pad($lastProdIdNum + 1, 3, '0', STR_PAD_LEFT);
    
    $editItem = ['id' => $nextItemId, 'productId' => $nextProductId]; // Provide IDs for new item form
}

// Get categories for dropdown
$stmt = $pdo->query("SELECT DISTINCT category FROM inventory ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
// Ensure categories is always an array
if (!is_array($categories)) {
    $categories = [];
}

// Search and filter logic
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? '';

$sql = "SELECT * FROM inventory WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE :search OR sku LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($categoryFilter)) {
    $sql .= " AND category = :category";
    $params[':category'] = $categoryFilter;
}
if (!empty($stockFilter)) {
    if ($stockFilter === 'low') {
        $sql .= " AND stockLevel <= reorderPoint AND stockLevel > 0";
    } elseif ($stockFilter === 'out') {
        $sql .= " AND stockLevel = 0";
    } elseif ($stockFilter === 'in') {
        $sql .= " AND stockLevel > 0";
    }
}
$sql .= " ORDER BY id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

?>
<style>
    /* Force the inventory title to be green with highest specificity */
    h1.inventory-title.text-2xl.font-bold {
        color: #87ac3a !important;
    }
    
    /* Brand button styling */
    .brand-button {
        background-color: #87ac3a !important;
        color: white !important;
        transition: background-color 0.3s ease;
    }
    
    .brand-button:hover {
        background-color: #6b8e23 !important; /* Darker shade for hover */
    }
    
    .toast-notification {
        position: fixed; top: 20px; right: 20px; padding: 12px 20px;
        border-radius: 4px; color: white; font-weight: 500; z-index: 9999;
        opacity: 0; transform: translateY(-20px); box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: opacity 0.3s, transform 0.3s;
    }
    .toast-notification.show { opacity: 1; transform: translateY(0); }
    .toast-notification.success { background-color: #48bb78; } /* Tailwind green-500 */
    .toast-notification.error { background-color: #f56565; } /* Tailwind red-500 */
    .toast-notification.info { background-color: #4299e1; } /* Tailwind blue-500 */

    .inventory-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
    .inventory-table th { background-color: #87ac3a; color: white; padding: 10px 12px; text-align: left; font-weight: 600; font-size: 0.8rem; position: sticky; top: 0; z-index: 10; }
    .inventory-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 0.85rem; }
    .inventory-table tr:hover { background-color: #f7fafc; }
    .inventory-table th:first-child { border-top-left-radius: 6px; }
    .inventory-table th:last-child { border-top-right-radius: 6px; }

    .action-btn { padding: 5px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; border: none; }
    .view-btn { background-color: #4299e1; color: white; } .view-btn:hover { background-color: #3182ce; }
    .edit-btn { background-color: #f59e0b; color: white; } .edit-btn:hover { background-color: #d97706; }
    .delete-btn { background-color: #f56565; color: white; } .delete-btn:hover { background-color: #e53e3e; }

    .cost-breakdown { background-color: #f9fafb; border-radius: 6px; padding: 10px; border: 1px solid #e2e8f0; height: 100%; display: flex; flex-direction: column;}
    .cost-breakdown h3 { color: #374151; font-size: 1rem; font-weight: 600; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #d1d5db; }
    .cost-breakdown-section h4 { color: #4b5563; font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; }
    .cost-item { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 1px dashed #e5e7eb; font-size: 0.8rem; }
    .cost-item:last-child { border-bottom: none; }
    .cost-item-name { font-weight: 500; color: #374151; flex-grow: 1; margin-right: 6px; word-break: break-word; }
    .cost-item-value { font-weight: 600; color: #1f2937; white-space: nowrap; }
    .cost-item-actions { display: flex; align-items: center; margin-left: 6px; }
    .cost-edit-btn, .cost-delete-btn { padding: 1px; margin-left: 3px; border: none; background: none; border-radius: 3px; cursor: pointer; font-size: 10px; opacity: 0.7; transition: opacity 0.2s; }
    .cost-edit-btn svg, .cost-delete-btn svg { width: 12px; height: 12px; }
    .cost-edit-btn { color: #4299e1; } .cost-delete-btn { color: #f56565; }
    .cost-edit-btn:hover, .cost-delete-btn:hover { opacity: 1; }

    .add-cost-btn { display: inline-flex; align-items: center; padding: 3px 6px; background-color: #edf2f7; border: 1px dashed #cbd5e0; border-radius: 4px; color: #4a5568; font-size: 0.75rem; cursor: pointer; margin-top: 5px; transition: all 0.2s; }
    .add-cost-btn:hover { background-color: #e2e8f0; border-color: #a0aec0; }
    .add-cost-btn svg { width: 10px; height: 10px; margin-right: 3px; }

    .cost-totals { background-color: #f3f4f6; padding: 8px; border-radius: 6px; margin-top: auto; font-size: 0.8rem; } /* margin-top: auto to push to bottom */
    .cost-total-row { display: flex; justify-content: space-between; padding: 2px 0; }
    .cost-label { font-size: 0.8rem; color: #6b7280; }
    
    .modal-outer { position: fixed; inset: 0; background-color: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 1rem; }
    .modal-content-wrapper { background-color: white; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); padding: 1.25rem; width: 100%; max-width: 60rem; /* Slightly reduced max-width */ max-height: 90vh; display: flex; flex-direction: column; }
    .modal-form-container { flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column; padding-right: 0.5rem; /* For scrollbar */ }
    @media (min-width: 768px) { .modal-form-container { flex-direction: row; } }
    .modal-form-main-column { flex: 1; padding-right: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; /* Reduced gap */ }
    @media (max-width: 767px) { .modal-form-main-column { padding-right: 0; } }
    .modal-form-cost-column { width: 100%; padding-left: 0; margin-top: 1rem; }
    @media (min-width: 768px) { .modal-form-cost-column { flex: 0 0 40%; padding-left: 0.75rem; margin-top: 0; } }\
    
    .modal-form-main-column label { font-size: 0.8rem; margin-bottom: 0.1rem; }
    .modal-form-main-column input[type="text"],
    .modal-form-main-column input[type="number"],
    .modal-form-main-column input[type="file"],
    .modal-form-main-column textarea,
    .modal-form-main-column select {
        font-size: 0.85rem; padding: 0.4rem 0.6rem; /* Reduced padding */
        border: 1px solid #d1d5db; border-radius: 0.25rem; width: 100%;
    }
    .modal-form-main-column textarea { min-height: 60px; }
    .image-preview { position: relative; width: 100%; max-width: 150px; margin-top: 5px; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .image-preview img { width: 100%; height: auto; display: block; }

    .editable { position: relative; padding: 6px 8px; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
    .editable:hover { background-color: #edf2f7; }
    .editable:hover::after { content: "✏️"; position: absolute; right: 5px; top: 50%; transform: translateY(-50%); font-size: 12px; opacity: 0.5; }
    .editing { padding: 0 !important; background-color: #ebf8ff !important; }
    .editing input, .editing select { width: 100%; padding: 6px 8px; border: 1px solid #4299e1; border-radius: 4px; font-size: inherit; font-family: inherit; background-color: white; }
    
    .loading-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; }
    .loading-spinner.dark { border: 2px solid rgba(0,0,0,0.1); border-top-color: #333; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .field-error-highlight { border-color: #f56565 !important; box-shadow: 0 0 0 1px #f56565 !important; }

    .cost-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 100; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
    .cost-modal.show { opacity: 1; pointer-events: auto; }
    .cost-modal-content { background-color: white; border-radius: 8px; padding: 1rem; width: 100%; max-width: 380px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transform: scale(0.95); transition: transform 0.3s; }
    .cost-modal.show .cost-modal-content { transform: scale(1); }
    .cost-modal-content label { font-size: 0.8rem; }
    .cost-modal-content input { font-size: 0.85rem; padding: 0.4rem 0.6rem; }
    .cost-modal-content button { font-size: 0.85rem; padding: 0.4rem 0.8rem; }
</style>

<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-5 gap-4">
        <h1 class="inventory-title text-2xl font-bold" style="color: #87ac3a !important;">Inventory Management</h1>
        <form method="GET" action="" class="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="inventory">
            <input type="text" name="search" placeholder="Search..." class="p-2 border border-gray-300 rounded text-sm flex-grow" value="<?= htmlspecialchars($search); ?>">
            <select name="category" class="p-2 border border-gray-300 rounded text-sm flex-grow">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat); ?>" <?= ($categoryFilter === $cat) ? 'selected' : ''; ?>><?= htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="stock" class="p-2 border border-gray-300 rounded text-sm flex-grow">
                <option value="">All Stock Levels</option>
                <option value="low" <?= ($stockFilter === 'low') ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out" <?= ($stockFilter === 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                <option value="in" <?= ($stockFilter === 'in') ? 'selected' : ''; ?>>In Stock</option>
            </select>
            <button type="submit" class="brand-button p-2 rounded text-sm">Filter</button>
            <a href="?page=admin&section=inventory&add=1" class="brand-button p-2 rounded text-sm text-center">Add New Item</a>
        </form>
    </div>
    
    <?php if ($message): ?>
        <div class="mb-4 p-3 rounded text-white <?= $messageType === 'success' ? 'bg-green-500' : 'bg-red-500'; ?>">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table id="inventoryTable" class="inventory-table">
            <thead>
                <tr>
                    <th>Name</th><th>Category</th><th>SKU</th><th>Stock</th>
                    <th>Reorder Point</th><th>Cost Price</th><th>Retail Price</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventoryItems)): ?>
                    <tr><td colspan="8" class="text-center py-4">No inventory items found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($inventoryItems as $item): ?>
                    <tr data-id="<?= htmlspecialchars($item['id']) ?>" class="<?= (isset($_GET['highlight']) && $_GET['highlight'] == $item['id']) ? 'bg-yellow-100' : '' ?>">
                        <td class="editable" data-field="name"><?= htmlspecialchars($item['name']) ?></td>
                        <td class="editable" data-field="category"><?= htmlspecialchars($item['category']) ?></td>
                        <td><?= htmlspecialchars($item['sku']) ?></td> <!-- SKU not typically inline editable -->
                        <td class="editable" data-field="stockLevel"><?= htmlspecialchars($item['stockLevel']) ?></td>
                        <td class="editable" data-field="reorderPoint"><?= htmlspecialchars($item['reorderPoint']) ?></td>
                        <td class="editable" data-field="costPrice">$<?= number_format(floatval($item['costPrice'] ?? 0), 2) ?></td>
                        <td class="editable" data-field="retailPrice">$<?= number_format(floatval($item['retailPrice'] ?? 0), 2) ?></td>
                        <td>
                            <a href="?page=admin&section=inventory&view=<?= htmlspecialchars($item['id']) ?>" class="action-btn view-btn" title="View Item">👁️</a>
                            <a href="?page=admin&section=inventory&edit=<?= htmlspecialchars($item['id']) ?>" class="action-btn edit-btn" title="Edit Item">✏️</a>
                            <button class="action-btn delete-btn delete-item" data-id="<?= htmlspecialchars($item['id']) ?>" title="Delete Item">🗑️</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($modalMode === 'view' && $editItem): ?>
<div class="modal-outer" id="inventoryModalOuter">
    <div class="modal-content-wrapper">
        <div class="flex justify-between items-center mb-3">
            <h2 class="text-lg font-bold text-green-700">View Item: <?= htmlspecialchars($editItem['name'] ?? 'N/A') ?></h2>
            <a href="?page=admin&section=inventory" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</a>
        </div>

        <div class="modal-form-container">
            <div class="modal-form-main-column">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="productIdDisplay" class="block text-gray-700">Product ID</label>
                        <input type="text" id="productIdDisplay" name="productId" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['productId'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="name" class="block text-gray-700">Name</label>
                        <input type="text" id="name" name="name" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="category" class="block text-gray-700">Category</label>
                        <input type="text" id="category" name="category" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['category'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="sku" class="block text-gray-700">SKU</label>
                        <input type="text" id="sku" name="sku" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['sku'] ?? ''); ?>">
                    </div>
                </div>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="stockLevel" class="block text-gray-700">Stock Level</label>
                        <input type="number" id="stockLevel" name="stockLevel" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['stockLevel'] ?? '0'); ?>">
                    </div>
                    <div>
                        <label for="reorderPoint" class="block text-gray-700">Reorder Point</label>
                        <input type="number" id="reorderPoint" name="reorderPoint" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['reorderPoint'] ?? '5'); ?>">
                    </div>
                </div>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="costPrice" class="block text-gray-700">Cost Price ($)</label>
                        <input type="number" id="costPrice" name="costPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['costPrice'] ?? '0.00'); ?>">
                    </div>
                    <div>
                        <label for="retailPrice" class="block text-gray-700">Retail Price ($)</label>
                        <input type="number" id="retailPrice" name="retailPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['retailPrice'] ?? '0.00'); ?>">
                    </div>
                </div>
                <div>
                    <label for="description" class="block text-gray-700">Description</label>
                    <textarea id="description" name="description" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" rows="2" readonly><?= htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
                </div>
                <?php if (!empty($editItem['imageUrl'])): ?>
                <div>
                    <label class="block text-gray-700">Product Image</label>
                    <div class="image-preview mt-2">
                        <img src="<?= htmlspecialchars($editItem['imageUrl']); ?>" alt="Product Image">
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="modal-form-cost-column">
                <div class="cost-breakdown">
                    <h3>Cost Breakdown</h3>
                    <?php foreach (['materials', 'labor', 'energy', 'equipment'] as $costType): ?>
                    <div class="cost-breakdown-section <?= $costType !== 'materials' ? 'mt-3' : ''; ?>">
                        <h4 class="font-semibold text-gray-700 mb-1 text-sm"><?= ucfirst($costType); ?></h4>
                        <div class="mb-2" id="view_<?= $costType; ?>List" style="max-height: 100px; overflow-y: auto;">
                            <?php if (!empty($editCostBreakdown[$costType])): ?>
                                <?php foreach ($editCostBreakdown[$costType] as $item_cost): ?>
                                <div class="cost-item">
                                    <span class="cost-item-name"><?= htmlspecialchars($costType === 'materials' ? $item_cost['name'] : $item_cost['description']) ?></span>
                                    <div class="cost-item-actions">
                                        <span class="cost-item-value">$<?= number_format(floatval($item_cost['cost']), 2) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-xs italic px-1">No items added.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="cost-totals">
                        <div class="cost-total-row"><span class="cost-label">Materials Total:</span> <span class="cost-item-value">$<?= number_format(floatval($editCostBreakdown['totals']['materialTotal'] ?? 0), 2) ?></span></div>
                        <div class="cost-total-row"><span class="cost-label">Labor Total:</span> <span class="cost-item-value">$<?= number_format(floatval($editCostBreakdown['totals']['laborTotal'] ?? 0), 2) ?></span></div>
                        <div class="cost-total-row"><span class="cost-label">Energy Total:</span> <span class="cost-item-value">$<?= number_format(floatval($editCostBreakdown['totals']['energyTotal'] ?? 0), 2) ?></span></div>
                        <div class="cost-total-row"><span class="cost-label">Equipment Total:</span> <span class="cost-item-value">$<?= number_format(floatval($editCostBreakdown['totals']['equipmentTotal'] ?? 0), 2) ?></span></div>
                        <div class="cost-total-row border-t border-gray-300 pt-1 mt-1">
                            <span class="font-semibold">Suggested Cost:</span> <span class="font-bold text-purple-700">$<?= number_format(floatval($editCostBreakdown['totals']['suggestedCost'] ?? 0), 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-auto pt-4 border-t">
            <a href="?page=admin&section=inventory" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block text-sm">Close</a>
            <a href="?page=admin&section=inventory&edit=<?= htmlspecialchars($editItem['id']) ?>" class="brand-button px-4 py-2 rounded text-sm">Edit Item</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($modalMode === 'add' || ($modalMode === 'edit' && $editItem)): ?>
<div class="modal-outer" id="inventoryModalOuter">
    <div class="modal-content-wrapper">
        <div class="flex justify-between items-center mb-3">
            <h2 class="text-lg font-bold text-green-700"><?= $modalMode === 'add' ? 'Add New Inventory Item' : 'Edit Item (' . htmlspecialchars($editItem['name'] ?? 'N/A') . ')' ?></h2>
            <a href="?page=admin&section=inventory" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</a>
        </div>

        <form id="inventoryForm" method="POST" action="#" class="flex flex-col flex-grow overflow-hidden">
            <input type="hidden" name="action" value="<?= $modalMode === 'add' ? 'add' : 'update'; ?>">
            <?php if ($modalMode === 'edit' && isset($editItem['id'])): ?>
                <input type="hidden" name="itemId" value="<?= htmlspecialchars($editItem['id']); ?>">
            <?php endif; ?>

            <div class="modal-form-container gap-5">
                <div class="modal-form-main-column">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="productIdDisplay" class="block text-gray-700">Product ID</label>
                            <input type="text" id="productIdDisplay" name="productId" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                                   value="<?= htmlspecialchars($editItem['productId'] ?? ($nextProductId ?? '')); ?>">
                        </div>
                        <div>
                            <label for="name" class="block text-gray-700">Name *</label>
                            <input type="text" id="name" name="name" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('name', $field_errors) ? 'field-error-highlight' : '' ?>" required 
                                   value="<?= htmlspecialchars($editItem['name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="category" class="block text-gray-700">Category *</label>
                            <select id="category" name="category" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('category', $field_errors) ? 'field-error-highlight' : '' ?>" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= (isset($editItem['category']) && $editItem['category'] === $cat) ? 'selected' : ''; ?>><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="sku" class="block text-gray-700">SKU *</label>
                            <input type="text" id="sku" name="sku" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('sku', $field_errors) ? 'field-error-highlight' : '' ?>" required 
                                   value="<?= htmlspecialchars($editItem['sku'] ?? ''); ?>">
                        </div>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="stockLevel" class="block text-gray-700">Stock Level *</label>
                            <input type="number" id="stockLevel" name="stockLevel" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('stockLevel', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required 
                                   value="<?= htmlspecialchars($editItem['stockLevel'] ?? '0'); ?>">
                        </div>
                        <div>
                            <label for="reorderPoint" class="block text-gray-700">Reorder Point *</label>
                            <input type="number" id="reorderPoint" name="reorderPoint" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('reorderPoint', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required 
                                   value="<?= htmlspecialchars($editItem['reorderPoint'] ?? '5'); ?>">
                        </div>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="costPrice" class="block text-gray-700">Cost Price ($) *</label>
                            <input type="number" id="costPrice" name="costPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('costPrice', $field_errors) ? 'field-error-highlight' : '' ?>" step="0.01" min="0" required 
                                   value="<?= htmlspecialchars($editItem['costPrice'] ?? '0.00'); ?>">
                        </div>
                        <div>
                            <label for="retailPrice" class="block text-gray-700">Retail Price ($) *</label>
                            <input type="number" id="retailPrice" name="retailPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('retailPrice', $field_errors) ? 'field-error-highlight' : '' ?>" step="0.01" min="0" required 
                                   value="<?= htmlspecialchars($editItem['retailPrice'] ?? '0.00'); ?>">
                        </div>
                    </div>
                    <div>
                        <label for="description" class="block text-gray-700">Description</label>
                        <textarea id="description" name="description" class="mt-1 block w-full p-2 border border-gray-300 rounded" rows="2"><?= htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label for="imageUpload" class="block text-gray-700">Product Image</label>
                        <input type="file" id="imageUpload" name="imageUpload" class="mt-1 block w-full text-sm">
                        <?php if (!empty($editItem['imageUrl'])): ?>
                            <div class="image-preview mt-2">
                                <img src="<?= htmlspecialchars($editItem['imageUrl']); ?>" alt="Current Image">
                            </div>
                            <input type="hidden" name="existingImageUrl" value="<?= htmlspecialchars($editItem['imageUrl']); ?>">
                        <?php else: ?>
                            <div class="image-preview mt-2" style="display:none;"><img></div> <!-- Placeholder for JS -->
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-form-cost-column">
                    <div class="cost-breakdown">
                        <h3>Cost Breakdown</h3>
                        <?php foreach (['materials', 'labor', 'energy', 'equipment'] as $costType): ?>
                        <div class="cost-breakdown-section <?= $costType !== 'materials' ? 'mt-3' : ''; ?>">
                            <h4 class="font-semibold text-gray-700 mb-1 text-sm"><?= ucfirst($costType); ?></h4>
                            <div class="mb-2" id="<?= $costType; ?>List" style="max-height: 100px; overflow-y: auto;">
                                <!-- Cost items will be rendered here by JavaScript -->
                            </div>
                            <button type="button" class="add-cost-btn" onclick="addCostItem('<?= $costType; ?>')">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 mr-1"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                                Add <?= ucfirst(substr($costType, 0, -1)); ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                        <div class="cost-totals">
                            <div class="cost-total-row"><span class="cost-label">Materials Total:</span> <span class="cost-item-value" id="materialsTotalDisplay">$0.00</span></div>
                            <div class="cost-total-row"><span class="cost-label">Labor Total:</span> <span class="cost-item-value" id="laborTotalDisplay">$0.00</span></div>
                            <div class="cost-total-row"><span class="cost-label">Energy Total:</span> <span class="cost-item-value" id="energyTotalDisplay">$0.00</span></div>
                            <div class="cost-total-row"><span class="cost-label">Equipment Total:</span> <span class="cost-item-value" id="equipmentTotalDisplay">$0.00</span></div>
                            <div class="cost-total-row border-t border-gray-300 pt-1 mt-1">
                                <span class="font-semibold">Suggested Cost:</span> <span class="font-bold text-purple-700" id="suggestedCostDisplay">$0.00</span>
                            </div>
                            <div class="mt-1 text-xs">
                                <button type="button" onclick="useSuggestedCost()" class="text-blue-600 hover:text-blue-800 underline">Use suggested cost for item</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-auto pt-4 border-t">
                <a href="?page=admin&section=inventory" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block text-sm">Cancel</a>
                <button type="submit" id="saveItemBtn" class="brand-button px-4 py-2 rounded text-sm">
                    <span class="button-text"><?= $modalMode === 'add' ? 'Add Item' : 'Save Changes'; ?></span>
                    <span class="loading-spinner hidden"></span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<div id="costFormModal" class="cost-modal">
    <div class="cost-modal-content">
        <div class="flex justify-between items-center mb-3">
            <h3 id="costFormTitle" class="text-md font-semibold text-gray-700">Edit Cost Item</h3>
            <button type="button" class="text-gray-500 hover:text-gray-700 text-2xl leading-none" onclick="closeCostModal()">&times;</button>
        </div>
        <form id="costForm" class="space-y-3">
            <input type="hidden" id="costItemId" value="">
            <input type="hidden" id="costItemType" value="">
            <div id="materialNameField" class="hidden">
                <label for="costItemName" class="block text-sm font-medium text-gray-700">Material Name *</label>
                <input type="text" id="costItemName" name="name" class="mt-1 block w-full p-2 border border-gray-300 rounded">
            </div>
            <div id="genericDescriptionField" class="hidden">
                 <label for="costItemDescription" class="block text-sm font-medium text-gray-700">Description *</label>
                <input type="text" id="costItemDescription" name="description" class="mt-1 block w-full p-2 border border-gray-300 rounded">
            </div>
            <div>
                <label for="costItemCost" class="block text-sm font-medium text-gray-700">Cost ($) *</label>
                <input type="number" id="costItemCost" name="cost" step="0.01" min="0" class="mt-1 block w-full p-2 border border-gray-300 rounded" required>
            </div>
            <div class="flex justify-between items-center pt-2">
                <button type="button" id="deleteCostItem" class="px-3 py-1.5 bg-red-500 text-white rounded hover:bg-red-600 text-sm hidden">Delete</button>
                <div class="flex space-x-2">
                    <button type="button" class="px-3 py-1.5 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 text-sm" onclick="closeCostModal()">Cancel</button>
                    <button type="submit" class="brand-button px-3 py-1.5 rounded text-sm">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="deleteConfirmModal" class="cost-modal"> <!-- Reusing cost-modal style for delete confirm -->
    <div class="cost-modal-content max-w-sm">
        <h2 class="text-md font-bold mb-3 text-gray-800">Confirm Delete</h2>
        <p class="mb-4 text-sm text-gray-600">Are you sure you want to delete this item? This action cannot be undone.</p>
        <div class="flex justify-end space-x-2">
            <button type="button" class="px-3 py-1.5 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm close-modal-button">Cancel</button>
            <button type="button" id="confirmDeleteBtn" class="px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700 text-sm">Delete</button>
        </div>
    </div>
</div>


<script>
// Initialize variables
var modalMode = <?= json_encode($modalMode ?? '') ?>;
var currentItemId = <?= json_encode(isset($editItem['id']) ? $editItem['id'] : '') ?>;
var costBreakdown = <?= ($modalMode === 'edit' && isset($editCostBreakdown) && $editCostBreakdown) ? json_encode($editCostBreakdown) : 'null' ?>;

function showToast(type, message) {
    const existingToast = document.getElementById('toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    const toast = document.createElement('div');
    toast.id = 'toast-notification';
    toast.className = `toast-notification ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function addCostItem(type) {
    document.getElementById('costForm').reset();
    document.getElementById('costItemId').value = '';
    document.getElementById('costItemType').value = type;
    document.getElementById('costFormTitle').textContent = `Add ${type.charAt(0).toUpperCase() + type.slice(1)} Cost`;

    const materialNameField = document.getElementById('materialNameField');
    const genericDescriptionField = document.getElementById('genericDescriptionField');

    if (type === 'materials') {
        materialNameField.style.display = 'block';
        genericDescriptionField.style.display = 'none';
    } else {
        materialNameField.style.display = 'none';
        genericDescriptionField.style.display = 'block';
    }
    document.getElementById('deleteCostItem').classList.add('hidden');
    document.getElementById('costFormModal').classList.add('show');
}

function editCostItem(type, id) {
    if (!costBreakdown || !costBreakdown[type]) {
        showToast('error', 'Cost breakdown data not available.');
        return;
    }
    const item_cost = costBreakdown[type].find(i => String(i.id) === String(id));
    if (!item_cost) {
        showToast('error', 'Cost item not found.');
        return;
    }
    document.getElementById('costForm').reset();
    document.getElementById('costItemId').value = item_cost.id;
    document.getElementById('costItemType').value = type;
    document.getElementById('costItemCost').value = item_cost.cost;
    document.getElementById('costFormTitle').textContent = `Edit ${type.charAt(0).toUpperCase() + type.slice(1)} Cost`;

    const materialNameField = document.getElementById('materialNameField');
    const genericDescriptionField = document.getElementById('genericDescriptionField');

    if (type === 'materials') {
        materialNameField.style.display = 'block';
        genericDescriptionField.style.display = 'none';
        document.getElementById('costItemName').value = item_cost.name || '';
    } else {
        materialNameField.style.display = 'none';
        genericDescriptionField.style.display = 'block';
        document.getElementById('costItemDescription').value = item_cost.description || '';
    }
    document.getElementById('deleteCostItem').classList.remove('hidden');
    document.getElementById('costFormModal').classList.add('show');
}

function saveCostItem() { // Called by costForm submit
    const id = document.getElementById('costItemId').value;
    const type = document.getElementById('costItemType').value;
    const cost = document.getElementById('costItemCost').value;
    const name = (type === 'materials') ? document.getElementById('costItemName').value : '';
    const description = (type !== 'materials') ? document.getElementById('costItemDescription').value : '';
    
    const payload = { 
        costType: type, cost: parseFloat(cost), 
        name: name, description: description, 
        inventoryId: currentItemId 
    };
    if (id) payload.id = id;

    fetch('/process_cost_breakdown.php', {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            closeCostModal();
            refreshCostBreakdown();
        } else {
            showToast('error', data.error || `Failed to save ${type} cost`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', `Failed to save ${type} cost`);
    });
}

function deleteCurrentCostItem() { // Called by delete button in costFormModal
    const id = document.getElementById('costItemId').value;
    const type = document.getElementById('costItemType').value;
    if (!id || !type) {
        showToast('error', 'No item selected for deletion.');
        return;
    }
    if (!confirm(`Are you sure you want to delete this ${type.slice(0,-1)} cost item?`)) return;

    const url = `/process_cost_breakdown.php?id=${id}&costType=${type}&inventoryId=${currentItemId}`;

    fetch(url, {
        method: 'DELETE',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            closeCostModal();
            refreshCostBreakdown();
        } else {
            showToast('error', data.error || `Failed to delete ${type} cost`);
        }
    })
    .catch(error => {
        console.error('Error deleting cost item:', error);
        showToast('error', `Failed to delete ${type} cost. Check console for details.`);
    });
}


function refreshCostBreakdown(useExistingData = false) {
    if (!currentItemId) return;
    if (useExistingData && costBreakdown) {
        renderCostBreakdown(costBreakdown);
        return;
    }
    fetch(`/process_cost_breakdown.php?inventoryId=${currentItemId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            costBreakdown = data.data; 
            renderCostBreakdown(costBreakdown);
        } else {
            showToast('error', data.error || 'Failed to load cost breakdown');
        }
    })
    .catch(error => { console.error('Error:', error); showToast('error', 'Failed to load cost breakdown'); });
}

function renderCostBreakdown(data) {
    console.log('renderCostBreakdown called with data:', data);
    if (!data) {
        console.log('No data provided, rendering empty lists');
        ['materials', 'labor', 'energy', 'equipment'].forEach(type => renderCostList(type, []));
        updateTotalsDisplay({ materialTotal: 0, laborTotal: 0, energyTotal: 0, equipmentTotal: 0, suggestedCost: 0 });
        return;
    }
    console.log('Rendering cost breakdown with data:', data);
    ['materials', 'labor', 'energy', 'equipment'].forEach(type => renderCostList(type, data[type] || []));
    updateTotalsDisplay(data.totals || { materialTotal: 0, laborTotal: 0, energyTotal: 0, equipmentTotal: 0, suggestedCost: 0 });
}

function renderCostList(type, items) {
    console.log(`renderCostList called for type: ${type}, items:`, items);
    const listElement = document.getElementById(`${type}List`);
    const viewListElement = document.getElementById(`view_${type}List`);
    
    console.log(`Found listElement for ${type}:`, listElement);
    console.log(`Found viewListElement for ${type}:`, viewListElement);
    
    if (listElement) {
        listElement.innerHTML = ''; 
        if (!items || items.length === 0) {
            listElement.innerHTML = '<p class="text-gray-500 text-xs italic px-1">No items added yet.</p>';
        } else {
            items.forEach(item_cost => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'cost-item';
                const nameText = (type === 'materials' ? item_cost.name : item_cost.description) || 'N/A';
                itemDiv.innerHTML = `
                    <span class="cost-item-name" title="${htmlspecialchars(nameText)}">${htmlspecialchars(nameText)}</span>
                    <div class="cost-item-actions">
                        <span class="cost-item-value">$${parseFloat(item_cost.cost).toFixed(2)}</span>
                        <button type="button" class="cost-edit-btn" onclick="editCostItem('${type}', '${item_cost.id}')" title="Edit Cost"><svg fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg></button>
                    </div>`;
                listElement.appendChild(itemDiv);
            });
        }
    }
    
    if (viewListElement) {
        viewListElement.innerHTML = ''; 
        if (!items || items.length === 0) {
            viewListElement.innerHTML = '<p class="text-gray-500 text-xs italic px-1">No items added.</p>';
        } else {
            items.forEach(item_cost => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'cost-item';
                const nameText = (type === 'materials' ? item_cost.name : item_cost.description) || 'N/A';
                itemDiv.innerHTML = `
                    <span class="cost-item-name" title="${htmlspecialchars(nameText)}">${htmlspecialchars(nameText)}</span>
                    <div class="cost-item-actions">
                        <span class="cost-item-value">$${parseFloat(item_cost.cost).toFixed(2)}</span>
                    </div>`;
                viewListElement.appendChild(itemDiv);
            });
        }
    }
}

function htmlspecialchars(str) {
    if (str === null || str === undefined) return '';
    if (typeof str !== 'string') str = String(str);
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return str.replace(/[&<>\"\']/g, function(m) { return map[m]; });
}

function updateTotalsDisplay(totals) {
    try {
        document.getElementById('materialsTotalDisplay').textContent = '$' + parseFloat(totals.materialTotal || 0).toFixed(2);
        document.getElementById('laborTotalDisplay').textContent = '$' + parseFloat(totals.laborTotal || 0).toFixed(2);
        document.getElementById('energyTotalDisplay').textContent = '$' + parseFloat(totals.energyTotal || 0).toFixed(2);
        document.getElementById('equipmentTotalDisplay').textContent = '$' + parseFloat(totals.equipmentTotal || 0).toFixed(2);
        document.getElementById('suggestedCostDisplay').textContent = '$' + parseFloat(totals.suggestedCost || 0).toFixed(2);
    } catch(e) {
        console.log('Error in updateTotalsDisplay:', e);
    }
}

function useSuggestedCost() {
    if (costBreakdown && costBreakdown.totals && costBreakdown.totals.suggestedCost !== undefined) {
        const suggested = parseFloat(costBreakdown.totals.suggestedCost).toFixed(2);
        document.getElementById('costPrice').value = suggested;
        showToast('info', 'Suggested cost applied to Cost Price field. Save the item to persist.');
    } else {
        showToast('error', 'Suggested cost is not available.');
    }
}

function closeCostModal() {
    document.getElementById('costFormModal').classList.remove('show');
}


document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded - modalMode:', modalMode, 'currentItemId:', currentItemId, 'costBreakdown:', costBreakdown);
    
    // Test if the HTML elements exist
    console.log('materialsList element:', document.getElementById('materialsList'));
    console.log('laborList element:', document.getElementById('laborList'));
    console.log('energyList element:', document.getElementById('energyList'));
    console.log('equipmentList element:', document.getElementById('equipmentList'));
    
    if ((modalMode === 'edit' || modalMode === 'view') && currentItemId && costBreakdown) {
        console.log('Calling refreshCostBreakdown(true)');
        refreshCostBreakdown(true); 
    } else if (modalMode === 'add') {
        console.log('Calling renderCostBreakdown(null) for add mode');
        renderCostBreakdown(null); 
    } else {
        console.log('Conditions not met - modalMode:', modalMode, 'currentItemId:', currentItemId, 'costBreakdown:', !!costBreakdown);
    }
    
    const inventoryTable = document.getElementById('inventoryTable');
    if (inventoryTable) {
        inventoryTable.addEventListener('click', function(e) {
            const cell = e.target.closest('.editable');
            if (!cell || cell.querySelector('input, select')) return;

            const originalValue = cell.dataset.originalValue || cell.textContent.trim();
            const field = cell.dataset.field;
            const itemId = cell.closest('tr').dataset.id;
            cell.dataset.originalValue = originalValue;

            let inputElement;
            if (field === 'category') {
                inputElement = document.createElement('select');
                inputElement.className = 'w-full p-1 border rounded text-sm';
                const currentCategory = originalValue;
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Select Category';
                inputElement.appendChild(option);
                
                (<?= json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?> || []).forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat;
                    if (cat === currentCategory) option.selected = true;
                    inputElement.appendChild(option);
                });
            } else {
                inputElement = document.createElement('input');
                inputElement.type = (field === 'costPrice' || field === 'retailPrice' || field === 'stockLevel' || field === 'reorderPoint') ? 'number' : 'text';
                if (inputElement.type === 'number') {
                    inputElement.step = (field === 'costPrice' || field === 'retailPrice') ? '0.01' : '1';
                    inputElement.min = '0';
                    inputElement.value = originalValue.replace('$', '');
                } else {
                    inputElement.value = originalValue;
                }
                inputElement.className = 'w-full p-1 border rounded text-sm';
            }
            
            cell.innerHTML = '';
            cell.appendChild(inputElement);
            inputElement.focus();
            cell.classList.add('editing');

            const saveEdit = () => {
                const newValue = inputElement.value;
                cell.classList.remove('editing');
                if (newValue !== originalValue || (field === 'category' && newValue !== originalValue)) { 
                    saveInlineEdit(itemId, field, newValue, cell, originalValue);
                } else {
                    cell.innerHTML = originalValue; 
                }
            };

            inputElement.addEventListener('blur', saveEdit, { once: true });
            inputElement.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveEdit();
                } else if (e.key === 'Escape') {
                    cell.classList.remove('editing');
                    cell.innerHTML = originalValue;
                }
            });
        });
    }
    
    function saveInlineEdit(itemId, field, value, cell, originalValue) {
        const formData = new FormData();
        formData.append('itemId', itemId);
        formData.append('field', field);
        formData.append('value', value);
        formData.append('action', 'inline_update');

        fetch('/process_inventory_update.php', {
            method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let displayValue = data.updatedValue !== undefined ? data.updatedValue : value;
                if (field === 'costPrice' || field === 'retailPrice') {
                    displayValue = '$' + parseFloat(displayValue).toFixed(2);
                }
                cell.innerHTML = htmlspecialchars(displayValue);
                showToast('success', data.message || 'Field updated.');
            } else {
                cell.innerHTML = originalValue;
                showToast('error', data.error || 'Failed to update field.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            cell.innerHTML = originalValue;
            showToast('error', 'Failed to update field.');
        });
    }
    
    const inventoryForm = document.getElementById('inventoryForm');
    if (inventoryForm) {
        const saveBtn = inventoryForm.querySelector('#saveItemBtn');
        const btnText = saveBtn ? saveBtn.querySelector('.button-text') : null;
        const spinner = saveBtn ? saveBtn.querySelector('.loading-spinner') : null;

        inventoryForm.addEventListener('submit', function(e) {
            e.preventDefault(); // CRITICAL: Prevent default form submission
            
            if(saveBtn && btnText && spinner) {
                btnText.classList.add('hidden');
                spinner.classList.remove('hidden');
                saveBtn.disabled = true;
            }
            
            const formData = new FormData(inventoryForm);

            fetch('/process_inventory_update.php', { // API endpoint for processing
                method: 'POST', 
                body: formData, 
                headers: { 'X-Requested-With': 'XMLHttpRequest' } // Important for backend to identify AJAX
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    // If not JSON, read as text and throw an error to be caught by .catch()
                    return response.text().then(text => { 
                        throw new Error("Server returned non-JSON response: " + text.substring(0, 200)); 
                    });
                }
            })
            .then(data => { // This block executes if response.json() was successful
                if (data.success) {
                    showToast('success', data.message);
                    
                    // Redirect to the inventory page, optionally highlighting the item
                    // This ensures the main table is refreshed and modal is closed.
                    let redirectUrl = '?page=admin&section=inventory';
                    if (data.itemId) { // itemId is returned by add/update operations
                        redirectUrl += '&highlight=' + data.itemId;
                    }
                    // Use a short delay to allow toast to be seen before navigation
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 500); 
                    // Button will be re-enabled by page reload, no need to manually do it here.
                    return; 

                } else { // data.success is false
                    showToast('error', data.error || 'Failed to save item. Please check inputs.');
                    if(saveBtn && btnText && spinner) {
                        btnText.classList.remove('hidden');
                        spinner.classList.add('hidden');
                        saveBtn.disabled = false;
                    }
                    if (data.field_errors) {
                        document.querySelectorAll('.field-error-highlight').forEach(el => el.classList.remove('field-error-highlight'));
                        data.field_errors.forEach(fieldName => {
                            const fieldElement = document.getElementById(fieldName) || document.querySelector(`[name="${fieldName}"]`);
                            if (fieldElement) fieldElement.classList.add('field-error-highlight');
                        });
                    }
                }
            })
            .catch(error => { // Catches network errors or the error thrown from non-JSON response
                console.error('Error saving item:', error);
                showToast('error', 'An unexpected error occurred: ' + error.message);
                 if(saveBtn && btnText && spinner) {
                    btnText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                    saveBtn.disabled = false;
                }
            });
        });
    }
    
    const imageUpload = document.getElementById('imageUpload');
    if (imageUpload) {
        imageUpload.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const formData = new FormData();
                formData.append('image', file);
                const itemIdField = document.querySelector('input[name="itemId"]');
                formData.append('itemId', itemIdField ? itemIdField.value : currentItemId);
                
                const previewDiv = this.parentNode.querySelector('.image-preview');
                const previewImg = previewDiv ? previewDiv.querySelector('img') : null;

                fetch('/process_image_upload.php', {
                    method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (previewDiv && previewImg) {
                            previewImg.src = data.imageUrl;
                            previewDiv.style.display = 'block';
                        }
                        const currentInventoryForm = document.getElementById('inventoryForm'); // Get form again
                        if (currentInventoryForm) {
                            let hiddenUrlInput = currentInventoryForm.querySelector('input[name="existingImageUrl"]');
                            if (!hiddenUrlInput) {
                                hiddenUrlInput = document.createElement('input');
                                hiddenUrlInput.type = 'hidden';
                                hiddenUrlInput.name = 'existingImageUrl';
                                currentInventoryForm.appendChild(hiddenUrlInput);
                            }
                             hiddenUrlInput.value = data.imageUrl;
                        }
                        showToast('success', 'Image uploaded successfully.');
                    } else {
                        showToast('error', data.error || 'Failed to upload image.');
                    }
                })
                .catch(error => { console.error('Error:', error); showToast('error', 'Failed to upload image.'); });
            }
        });
    }
    
    const costForm = document.getElementById('costForm');
    if (costForm) {
        costForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveCostItem();
        });
    }
    
    const deleteCostItemBtn = document.getElementById('deleteCostItem');
    if (deleteCostItemBtn) {
        deleteCostItemBtn.addEventListener('click', deleteCurrentCostItem);
    }
    
    const deleteConfirmModalElement = document.getElementById('deleteConfirmModal');
    const confirmDeleteActualBtn = document.getElementById('confirmDeleteBtn');
    let itemToDeleteId = null;

    document.querySelectorAll('.delete-item').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            itemToDeleteId = this.dataset.id;
            if(deleteConfirmModalElement) deleteConfirmModalElement.classList.add('show');
        });
    });

    if (confirmDeleteActualBtn && deleteConfirmModalElement) {
        confirmDeleteActualBtn.addEventListener('click', function() {
            if (!itemToDeleteId) return;
            fetch(`/process_inventory_update.php?action=delete&itemId=${itemToDeleteId}`, {
                method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    // Redirect to refresh the list after successful deletion
                    setTimeout(() => { window.location.href = '?page=admin&section=inventory'; }, 1000);
                } else {
                    showToast('error', data.error || 'Failed to delete item.');
                }
            })
            .catch(error => { console.error('Error:', error); showToast('error', 'Failed to delete item.'); });
            deleteConfirmModalElement.classList.remove('show');
        });
    }
    
    deleteConfirmModalElement?.querySelectorAll('.close-modal-button').forEach(btn => {
        btn.addEventListener('click', () => deleteConfirmModalElement.classList.remove('show'));
    });

    window.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const mainModal = document.getElementById('inventoryModalOuter');
            // Check if mainModal is actually displayed (not just present in DOM)
            if (mainModal && mainModal.offsetParent !== null) { 
                window.location.href = '?page=admin&section=inventory'; // Redirect to close
            } else if (document.getElementById('costFormModal')?.classList.contains('show')) {
                 closeCostModal();
            } else if (deleteConfirmModalElement && deleteConfirmModalElement.classList.contains('show')) {
                deleteConfirmModalElement.classList.remove('show');
            }
        }
    });

    // Highlight row if specified in URL
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight');
    if (highlightId) {
        const rowToHighlight = document.querySelector(`tr[data-id='${highlightId}']`);
        if (rowToHighlight) {
            rowToHighlight.classList.add('bg-yellow-100'); 
            rowToHighlight.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => {
                rowToHighlight.classList.remove('bg-yellow-100');
                const cleanUrl = window.location.pathname + '?page=admin&section=inventory'; // Remove highlight param
                history.replaceState({path: cleanUrl}, '', cleanUrl);
            }, 3000);
        }
    }

});
</script>

<?php
$output = ob_get_clean();
echo $output;
?>

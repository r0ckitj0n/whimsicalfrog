<?php
// Include database configuration
require_once __DIR__ . '/../api/config.php';

// Initialize variables
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$stockFilter = isset($_GET['stock']) ? $_GET['stock'] : '';
$message = '';
$messageType = '';

// Connect to database
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get all inventory items with filters
    $query = "SELECT * FROM inventory WHERE 1=1";
    $params = [];
    
    if (!empty($searchTerm)) {
        $query .= " AND (name LIKE ? OR category LIKE ? OR sku LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($categoryFilter)) {
        $query .= " AND category = ?";
        $params[] = $categoryFilter;
    }
    
    if (!empty($stockFilter)) {
        if ($stockFilter === 'low') {
            $query .= " AND stockLevel <= reorderPoint AND stockLevel > 0";
        } elseif ($stockFilter === 'out') {
            $query .= " AND stockLevel = 0";
        } elseif ($stockFilter === 'in') {
            $query .= " AND stockLevel > 0";
        }
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    $totalItems = count($inventoryItems);
    
    // Low stock count
    $lowStockCount = 0;
    foreach ($inventoryItems as $item) {
        if ($item['stockLevel'] <= $item['reorderPoint'] && $item['stockLevel'] > 0) {
            $lowStockCount++;
        }
    }
    
    // Category count
    $categories = [];
    foreach ($inventoryItems as $item) {
        if (!in_array($item['category'], $categories)) {
            $categories[] = $item['category'];
        }
    }
    $categoryCount = count($categories);
    
    // Total cost value
    $totalCostValue = 0;
    foreach ($inventoryItems as $item) {
        $totalCostValue += floatval($item['costPrice']) * intval($item['stockLevel']);
    }
    
    // Total retail value
    $totalRetailValue = 0;
    foreach ($inventoryItems as $item) {
        $totalRetailValue += floatval($item['retailPrice']) * intval($item['stockLevel']);
    }
    
    // Get all unique categories for filter dropdown
    $categoryStmt = $pdo->query("SELECT DISTINCT category FROM inventory ORDER BY category");
    $allCategories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Process form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new item
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            $productId = $_POST['productId'];
            $name = $_POST['name'];
            $category = $_POST['category'];
            $sku = $_POST['sku'];
            $stockLevel = $_POST['stockLevel'];
            $reorderPoint = $_POST['reorderPoint'];
            $costPrice = $_POST['costPrice'];
            $retailPrice = $_POST['retailPrice'];
            $description = $_POST['description'];
            $imageUrl = $_POST['imageUrl'];
            
            // Generate a new ID
            $idStmt = $pdo->query("SELECT MAX(SUBSTRING(id, 2)) as max_id FROM inventory");
            $maxId = $idStmt->fetch(PDO::FETCH_ASSOC)['max_id'];
            $newId = 'I' . str_pad(intval($maxId) + 1, 3, '0', STR_PAD_LEFT);
            
            $insertStmt = $pdo->prepare("INSERT INTO inventory (id, productId, name, category, sku, stockLevel, reorderPoint, costPrice, retailPrice, description, imageUrl) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($insertStmt->execute([$newId, $productId, $name, $category, $sku, $stockLevel, $reorderPoint, $costPrice, $retailPrice, $description, $imageUrl])) {
                $message = "Item added successfully!";
                $messageType = "success";
                // Redirect to refresh the page
                header("Location: ?page=admin&section=inventory&message=$message&type=$messageType");
                exit;
            } else {
                $message = "Failed to add item.";
                $messageType = "error";
            }
        }
        
        // Update existing item
        else if (isset($_POST['action']) && $_POST['action'] === 'update') {
            $id = $_POST['itemId'];
            $productId = $_POST['productId'];
            $name = $_POST['name'];
            $category = $_POST['category'];
            $sku = $_POST['sku'];
            $stockLevel = $_POST['stockLevel'];
            $reorderPoint = $_POST['reorderPoint'];
            $costPrice = $_POST['costPrice'];
            $retailPrice = $_POST['retailPrice'];
            $description = $_POST['description'];
            $imageUrl = $_POST['imageUrl'];
            
            $updateStmt = $pdo->prepare("UPDATE inventory SET 
                                        productId = ?, 
                                        name = ?, 
                                        category = ?, 
                                        sku = ?, 
                                        stockLevel = ?, 
                                        reorderPoint = ?, 
                                        costPrice = ?, 
                                        retailPrice = ?, 
                                        description = ?, 
                                        imageUrl = ? 
                                        WHERE id = ?");
            
            if ($updateStmt->execute([$productId, $name, $category, $sku, $stockLevel, $reorderPoint, $costPrice, $retailPrice, $description, $imageUrl, $id])) {
                $message = "Item updated successfully!";
                $messageType = "success";
                // Redirect to refresh the page
                header("Location: ?page=admin&section=inventory&message=$message&type=$messageType");
                exit;
            } else {
                $message = "Failed to update item.";
                $messageType = "error";
            }
        }
        
        // Delete item
        else if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = $_POST['itemId'];
            
            $deleteStmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
            
            if ($deleteStmt->execute([$id])) {
                $message = "Item deleted successfully!";
                $messageType = "success";
                // Redirect to refresh the page
                header("Location: ?page=admin&section=inventory&message=$message&type=$messageType");
                exit;
            } else {
                $message = "Failed to delete item.";
                $messageType = "error";
            }
        }
    }
    
    // Get cost breakdown for an item if ID is provided
    $costBreakdown = null;
    if (isset($_GET['edit']) && !empty($_GET['edit'])) {
        $editItemId = $_GET['edit'];
        
        // Get item details
        $itemStmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $itemStmt->execute([$editItemId]);
        $editItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get materials costs
        $materialStmt = $pdo->prepare("SELECT * FROM inventory_materials WHERE inventoryId = ?");
        $materialStmt->execute([$editItemId]);
        $materials = $materialStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get labor costs
        $laborStmt = $pdo->prepare("SELECT * FROM inventory_labor WHERE inventoryId = ?");
        $laborStmt->execute([$editItemId]);
        $labor = $laborStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get energy costs
        $energyStmt = $pdo->prepare("SELECT * FROM inventory_energy WHERE inventoryId = ?");
        $energyStmt->execute([$editItemId]);
        $energy = $energyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $materialTotal = 0;
        foreach ($materials as $material) {
            $materialTotal += floatval($material['cost']);
        }
        
        $laborTotal = 0;
        foreach ($labor as $laborItem) {
            $laborTotal += floatval($laborItem['cost']);
        }
        
        $energyTotal = 0;
        foreach ($energy as $energyItem) {
            $energyTotal += floatval($energyItem['cost']);
        }
        
        $suggestedCost = $materialTotal + $laborTotal + $energyTotal;
        
        $costBreakdown = [
            'materials' => $materials,
            'labor' => $labor,
            'energy' => $energy,
            'totals' => [
                'materialTotal' => $materialTotal,
                'laborTotal' => $laborTotal,
                'energyTotal' => $energyTotal,
                'suggestedCost' => $suggestedCost
            ]
        ];
    }
    
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "error";
    $inventoryItems = [];
    $totalItems = 0;
    $lowStockCount = 0;
    $categoryCount = 0;
    $totalCostValue = 0;
    $totalRetailValue = 0;
    $allCategories = [];
}

// Display message from redirect if available
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'];
}
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 text-green-700">Inventory Management</h1>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Total Items</h3>
            <p class="text-2xl font-bold text-green-700"><?php echo $totalItems; ?></p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Low Stock</h3>
            <p class="text-2xl font-bold text-orange-500"><?php echo $lowStockCount; ?></p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Categories</h3>
            <p class="text-2xl font-bold text-blue-500"><?php echo $categoryCount; ?></p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Total Cost Value</h3>
            <p class="text-2xl font-bold text-purple-700">$<?php echo number_format($totalCostValue, 2); ?></p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Total Retail Value</h3>
            <p class="text-2xl font-bold text-indigo-700">$<?php echo number_format($totalRetailValue, 2); ?></p>
        </div>
    </div>
    
    <!-- Admin Navigation Tabs -->
    <style>
        .admin-nav {
            display: flex;
            overflow-x: auto;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .admin-nav a {
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 600;
            color: #4a5568;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }
        .admin-nav a:hover {
            color: #2d3748;
            border-bottom-color: #cbd5e0;
        }
        .admin-nav a.active {
            color: #87ac3a;
            border-bottom-color: #87ac3a;
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 4px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            opacity: 1;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .toast-notification.success {
            background-color: #48bb78;
        }
        .toast-notification.error {
            background-color: #f56565;
        }
        
        /* Enhanced Inventory Table */
        .inventory-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 20px;
        }
        .inventory-table th {
            background-color: #87ac3a;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .inventory-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .inventory-table tr:hover {
            background-color: #f7fafc;
        }
        .inventory-table th:first-child {
            border-top-left-radius: 8px;
        }
        .inventory-table th:last-child {
            border-top-right-radius: 8px;
        }
        
        /* Action Buttons */
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 16px;
        }
        .edit-btn {
            background-color: #4299e1;
            color: white;
        }
        .edit-btn:hover {
            background-color: #3182ce;
        }
        .delete-btn {
            background-color: #f56565;
            color: white;
        }
        .delete-btn:hover {
            background-color: #e53e3e;
        }
        
        /* Cost Breakdown Styles */
        .cost-breakdown {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            border: 1px solid #e2e8f0;
        }
        .cost-breakdown h3 {
            color: #4a5568;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .cost-breakdown-section {
            margin-bottom: 16px;
        }
        .cost-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .cost-item:last-child {
            border-bottom: none;
        }
        .cost-item-name {
            font-weight: 500;
        }
        .cost-item-value {
            font-weight: 600;
            color: #4a5568;
        }
        .cost-totals {
            background-color: #edf2f7;
            padding: 12px;
            border-radius: 6px;
            margin-top: 12px;
        }
        .cost-total-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
        .suggested-cost {
            color: #805ad5;
            font-weight: 600;
            margin-left: 8px;
        }
        .cost-label {
            font-size: 14px;
            color: #718096;
        }
    </style>
    
    <!-- Toast Notification -->
    <?php if (!empty($message)): ?>
    <div class="toast-notification <?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
    <script>
        // Auto-hide the notification after 3 seconds
        setTimeout(function() {
            document.querySelector('.toast-notification').style.opacity = '0';
        }, 3000);
    </script>
    <?php endif; ?>
    
    <!-- Search and Filter Controls -->
    <form method="GET" action="" class="flex flex-col md:flex-row gap-4 mb-6">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="inventory">
        <div class="flex-1">
            <input type="text" name="search" placeholder="Search inventory..." class="w-full p-2 border border-gray-300 rounded" value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <div class="flex-1">
            <select name="category" class="w-full p-2 border border-gray-300 rounded">
                <option value="">All Categories</option>
                <?php foreach ($allCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($categoryFilter === $cat) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1">
            <select name="stock" class="w-full p-2 border border-gray-300 rounded">
                <option value="">All Stock Levels</option>
                <option value="low" <?php echo ($stockFilter === 'low') ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out" <?php echo ($stockFilter === 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                <option value="in" <?php echo ($stockFilter === 'in') ? 'selected' : ''; ?>>In Stock</option>
            </select>
        </div>
        <div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded">Filter</button>
        </div>
        <div>
            <a href="?page=admin&section=inventory&add=1" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded inline-block text-center">Add New Item</a>
        </div>
    </form>
    
    <!-- Inventory Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>SKU</th>
                    <th>Stock</th>
                    <th>Reorder Point</th>
                    <th>Cost Price</th>
                    <th>Retail Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventoryItems)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4">No inventory items found.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($inventoryItems as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                        <td><?php echo htmlspecialchars($item['sku']); ?></td>
                        <td><?php echo intval($item['stockLevel']); ?></td>
                        <td><?php echo intval($item['reorderPoint']); ?></td>
                        <td>$<?php echo number_format(floatval($item['costPrice']), 2); ?></td>
                        <td>$<?php echo number_format(floatval($item['retailPrice']), 2); ?></td>
                        <td>
                            <a href="?page=admin&section=inventory&edit=<?php echo $item['id']; ?>" class="action-btn edit-btn" title="Edit Item">✏️</a>
                            <a href="?page=admin&section=inventory&delete=<?php echo $item['id']; ?>" class="action-btn delete-btn" title="Delete Item" onclick="return confirm('Are you sure you want to delete this item?')">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add/Edit Item Modal -->
    <?php if (isset($_GET['add']) || isset($_GET['edit'])): ?>
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-green-700">
                    <?php echo isset($_GET['add']) ? 'Add New Item' : 'Edit Item'; ?>
                </h2>
                <a href="?page=admin&section=inventory" class="text-gray-500 hover:text-gray-700">&times;</a>
            </div>
            
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="action" value="<?php echo isset($_GET['add']) ? 'add' : 'update'; ?>">
                <?php if (isset($_GET['edit'])): ?>
                <input type="hidden" name="itemId" value="<?php echo htmlspecialchars($editItem['id']); ?>">
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="productId" class="block text-sm font-medium text-gray-700">Product ID</label>
                        <input type="text" id="productId" name="productId" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                               value="<?php echo isset($editItem) ? htmlspecialchars($editItem['productId']) : ''; ?>">
                    </div>
                    
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" id="name" name="name" class="mt-1 block w-full p-2 border border-gray-300 rounded" required 
                               value="<?php echo isset($editItem) ? htmlspecialchars($editItem['name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                        <input type="text" id="category" name="category" class="mt-1 block w-full p-2 border border-gray-300 rounded" required 
                               value="<?php echo isset($editItem) ? htmlspecialchars($editItem['category']) : ''; ?>">
                    </div>
                    
                    <div>
                        <label for="sku" class="block text-sm font-medium text-gray-700">SKU</label>
                        <input type="text" id="sku" name="sku" class="mt-1 block w-full p-2 border border-gray-300 rounded" required 
                               value="<?php echo isset($editItem) ? htmlspecialchars($editItem['sku']) : ''; ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="stockLevel" class="block text-sm font-medium text-gray-700">Stock Level</label>
                        <input type="number" id="stockLevel" name="stockLevel" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" required 
                               value="<?php echo isset($editItem) ? intval($editItem['stockLevel']) : '0'; ?>">
                    </div>
                    
                    <div>
                        <label for="reorderPoint" class="block text-sm font-medium text-gray-700">Reorder Point</label>
                        <input type="number" id="reorderPoint" name="reorderPoint" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" required 
                               value="<?php echo isset($editItem) ? intval($editItem['reorderPoint']) : '0'; ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="costPrice" class="block text-sm font-medium text-gray-700">Cost Price ($)</label>
                        <div class="flex items-center">
                            <input type="number" id="costPrice" name="costPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" step="0.01" required 
                                   value="<?php echo isset($editItem) ? number_format(floatval($editItem['costPrice']), 2, '.', '') : '0.00'; ?>">
                            <?php if (isset($costBreakdown) && $costBreakdown['totals']['suggestedCost'] > 0): ?>
                            <span class="suggested-cost ml-2">(Suggested: $<?php echo number_format($costBreakdown['totals']['suggestedCost'], 2); ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <label for="retailPrice" class="block text-sm font-medium text-gray-700">Retail Price ($)</label>
                        <input type="number" id="retailPrice" name="retailPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" step="0.01" required 
                               value="<?php echo isset($editItem) ? number_format(floatval($editItem['retailPrice']), 2, '.', '') : '0.00'; ?>">
                    </div>
                </div>
                
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded"><?php echo isset($editItem) ? htmlspecialchars($editItem['description']) : ''; ?></textarea>
                </div>
                
                <div>
                    <label for="imageUrl" class="block text-sm font-medium text-gray-700">Image URL</label>
                    <input type="text" id="imageUrl" name="imageUrl" class="mt-1 block w-full p-2 border border-gray-300 rounded" 
                           value="<?php echo isset($editItem) ? htmlspecialchars($editItem['imageUrl']) : ''; ?>">
                </div>
                
                <!-- Cost Breakdown Section -->
                <?php if (isset($costBreakdown)): ?>
                <div class="cost-breakdown">
                    <h3>Cost Breakdown</h3>
                    
                    <!-- Materials Section -->
                    <div class="cost-breakdown-section">
                        <h4 class="font-semibold text-gray-700 mb-2">Materials</h4>
                        <div class="mb-2">
                            <?php if (!empty($costBreakdown['materials'])): ?>
                                <?php foreach ($costBreakdown['materials'] as $material): ?>
                                <div class="cost-item">
                                    <span class="cost-item-name"><?php echo htmlspecialchars($material['name']); ?></span>
                                    <span class="cost-item-value">$<?php echo number_format(floatval($material['cost']), 2); ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-gray-500 text-sm italic">No materials data available</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Labor Section -->
                    <div class="cost-breakdown-section">
                        <h4 class="font-semibold text-gray-700 mb-2">Labor</h4>
                        <div class="mb-2">
                            <?php if (!empty($costBreakdown['labor'])): ?>
                                <?php foreach ($costBreakdown['labor'] as $laborItem): ?>
                                <div class="cost-item">
                                    <span class="cost-item-name"><?php echo htmlspecialchars($laborItem['description']); ?></span>
                                    <span class="cost-item-value">$<?php echo number_format(floatval($laborItem['cost']), 2); ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-gray-500 text-sm italic">No labor data available</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Energy Section -->
                    <div class="cost-breakdown-section">
                        <h4 class="font-semibold text-gray-700 mb-2">Energy</h4>
                        <div class="mb-2">
                            <?php if (!empty($costBreakdown['energy'])): ?>
                                <?php foreach ($costBreakdown['energy'] as $energyItem): ?>
                                <div class="cost-item">
                                    <span class="cost-item-name"><?php echo htmlspecialchars($energyItem['description']); ?></span>
                                    <span class="cost-item-value">$<?php echo number_format(floatval($energyItem['cost']), 2); ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-gray-500 text-sm italic">No energy data available</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Totals Section -->
                    <div class="cost-totals">
                        <div class="cost-total-row">
                            <span class="cost-label">Materials Total:</span>
                            <span class="cost-item-value">$<?php echo number_format($costBreakdown['totals']['materialTotal'], 2); ?></span>
                        </div>
                        <div class="cost-total-row">
                            <span class="cost-label">Labor Total:</span>
                            <span class="cost-item-value">$<?php echo number_format($costBreakdown['totals']['laborTotal'], 2); ?></span>
                        </div>
                        <div class="cost-total-row">
                            <span class="cost-label">Energy Total:</span>
                            <span class="cost-item-value">$<?php echo number_format($costBreakdown['totals']['energyTotal'], 2); ?></span>
                        </div>
                        <div class="cost-total-row border-t border-gray-300 pt-2 mt-2">
                            <span class="font-semibold">Suggested Cost:</span>
                            <span class="font-bold text-purple-700">$<?php echo number_format($costBreakdown['totals']['suggestedCost'], 2); ?></span>
                        </div>
                        <div class="mt-2 text-sm text-gray-600">
                            <button type="button" onclick="document.getElementById('costPrice').value='<?php echo number_format($costBreakdown['totals']['suggestedCost'], 2, '.', ''); ?>'; document.getElementById('costPrice').style.backgroundColor='#c6f6d5'; setTimeout(function() { document.getElementById('costPrice').style.backgroundColor=''; }, 1000);" class="text-blue-600 hover:text-blue-800 underline">Use suggested cost</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-end space-x-3">
                    <a href="?page=admin&section=inventory" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Save Item</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Delete Confirmation Modal -->
    <?php if (isset($_GET['delete'])): ?>
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4 text-red-600">Confirm Delete</h2>
            <p class="mb-4">Are you sure you want to delete this item? This action cannot be undone.</p>
            <div class="flex justify-end space-x-3">
                <a href="?page=admin&section=inventory" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block">Cancel</a>
                <form method="POST" action="" class="inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="itemId" value="<?php echo htmlspecialchars($_GET['delete']); ?>">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

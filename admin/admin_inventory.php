<?php
// Admin Inventory Management Section



// Authentication check handled by index.php before including this file

// Bootstrap: if this file is accessed directly (not via index.php), load config
// which defines DB credentials and includes the Database/Logger classes.
if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../api/config.php';
} else {
    // When included from index.php, config.php is already loaded.
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/logger.php';
}

// Get database instance
$pdo = Database::getInstance();

// Initialize data processing
$editItem = null;
$editCostBreakdown = null;
$field_errors = $_SESSION['field_errors'] ?? [];
unset($_SESSION['field_errors']);

// Streamlined modal mode detection
$modalMode = match(true) {
    isset($_GET['view']) && !empty($_GET['view']) => 'view',
    isset($_GET['edit']) && !empty($_GET['edit']) => 'edit',
    isset($_GET['add']) && $_GET['add'] == 1 => 'add',
    default => ''
};


// Process modal data based on mode
$editItem = null;
if ($modalMode === 'view' || $modalMode === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE sku = ?");
    $stmt->execute([$_GET[$modalMode]]);
    $editItem = $stmt->fetch() ?: null;
} elseif ($modalMode === 'add') {
    $stmt = $pdo->prepare("SELECT sku FROM items WHERE sku LIKE 'WF-GEN-%' ORDER BY sku DESC LIMIT 1");
    $stmt->execute();
    $lastSku = $stmt->fetch();
    $lastNum = $lastSku ? (int)substr($lastSku['sku'], -3) : 0;
    $editItem = ['sku' => 'WF-GEN-' . str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT)];
}

// Cost breakdown temporarily disabled during SKU migration
$editCostBreakdown = null;

// Get categories for dropdown
$stmt = $pdo->prepare("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN) ?? [];

// Process search and filters using modern PHP
$filters = [
    'search' => $_GET['search'] ?? '',
    'category' => $_GET['category'] ?? '',
    'stock' => $_GET['stock'] ?? ''
];

// Build dynamic query with streamlined approach
$whereConditions = ['1=1'];
$queryParams = [];

// Search filter
if (!empty($filters['search'])) {
    $whereConditions[] = "(i.name LIKE :search OR i.sku LIKE :search OR i.description LIKE :search)";
    $queryParams[':search'] = '%' . $filters['search'] . '%';
}

// Category filter
if (!empty($filters['category'])) {
    $whereConditions[] = "i.category = :category";
    $queryParams[':category'] = $filters['category'];
}

// Stock filter with match expression
if (!empty($filters['stock'])) {
    $stockCondition = match($filters['stock']) {
        'low' => "i.stockLevel <= i.reorderPoint AND i.stockLevel > 0",
        'out' => "i.stockLevel = 0",
        'in' => "i.stockLevel > 0",
        default => "1=1"
    };
    $whereConditions[] = $stockCondition;
}

// ------------------- Pagination -------------------
$perPage = 50;
$currentPage = isset($_GET['pageNum']) ? max(1, (int)$_GET['pageNum']) : 1;
$offset = ($currentPage - 1) * $perPage;

// Get total record count for pagination controls
$countSql = "SELECT COUNT(*) FROM items i WHERE " . implode(' AND ', $whereConditions);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($queryParams);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRecords / $perPage);

// Main paginated query
$sql = "SELECT i.*, COALESCE(img_count.image_count, 0) as image_count 
        FROM items i 
        LEFT JOIN (
            SELECT sku, COUNT(*) as image_count 
            FROM item_images 
            GROUP BY sku
        ) img_count ON i.sku = img_count.sku 
        WHERE " . implode(' AND ', $whereConditions) . " 
        ORDER BY i.sku ASC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
// Bind pagination params separately because they must be integers
foreach ($queryParams as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

// Message handling for user feedback
$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

?>



<div class="admin-content-container">

    <div class="admin-filter-section">
        <div class="admin-filters">
        <form method="GET" action="/admin/inventory" class="admin-filter-form">
            <input type="text" name="search" placeholder="Search..." class="admin-form-input" value="<?= htmlspecialchars($filters['search'] ?? ''); ?>">
            <select name="category" class="admin-form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat); ?>" <?= ($filters['category'] === $cat) ? 'selected' : ''; ?>><?= htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="stock" class="admin-form-select">
                <option value="">All Stock Levels</option>
                <option value="low" <?= ($filters['stock'] === 'low') ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out" <?= ($filters['stock'] === 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                <option value="in" <?= ($filters['stock'] === 'in') ? 'selected' : ''; ?>>In Stock</option>
            </select>
            <span class="admin-actions">
                <button type="submit" class="btn btn-primary admin-filter-button">Filter</button>
                <button type="button" data-action="refresh-categories" class="btn btn-secondary admin-filter-button btn-icon" title="Refresh Categories">🔄</button>
                <a href="/admin/inventory?add=1" class="btn btn-primary admin-filter-button">Add New Item</a>
            </span>
        </form>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="admin-message <?= $messageType === 'success' ? 'admin-message-success' : 'admin-message-error'; ?>">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="admin-table-section">
        <table id="inventoryTable" class="inventory-table admin-data-table">
            <thead>
                <tr>
                    <th>Image</th><th>Images</th><th>Name</th><th>Category</th><th>SKU</th><th>Stock</th>
                    <th>Reorder Point</th><th>Cost Price</th><th>Retail Price</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="10" class="text-center">No items found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr data-sku="<?= htmlspecialchars($item['sku'] ?? '') ?>" class="<?= (isset($_GET['highlight']) && $_GET['highlight'] == $item['sku']) ? 'bg-yellow-100' : '' ?> hover:bg-gray-50">
                        <td>
                            <div class="thumbnail-container" data-sku="<?= htmlspecialchars($item['sku'] ?? '') ?>" >
                                <div class="thumbnail-loading" >...</div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="inline-flex items-center rounded-full text-xs font-medium <?= ($item['image_count'] > 0) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                <?= intval($item['image_count']) ?>
                            </span>
                        </td>
                                            <td class="editable" data-field="name"><?= htmlspecialchars($item['name'] ?? '') ?></td>
                    <td class="editable" data-field="category"><?= htmlspecialchars($item['category'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['sku'] ?? '') ?></td> <!-- SKU not typically inline editable -->
                    <td class="editable" data-field="stockLevel"><?= htmlspecialchars($item['stockLevel'] ?? '0') ?></td>
                    <td class="editable" data-field="reorderPoint"><?= htmlspecialchars($item['reorderPoint'] ?? '0') ?></td>
                        <td class="editable" data-field="costPrice">$<?= number_format(floatval($item['costPrice'] ?? 0), 2) ?></td>
                        <td class="editable" data-field="retailPrice">$<?= number_format(floatval($item['retailPrice'] ?? 0), 2) ?></td>
                        <td>
                            <div class="flex space-x-2">
                                <a href="/admin/inventory?view=<?= htmlspecialchars($item['sku'] ?? '') ?>" class="text-blue-600 hover:text-blue-800" title="View Item">👁️</a>
                                <a href="/admin/inventory?edit=<?= htmlspecialchars($item['sku'] ?? '') ?>" class="text-green-600 hover:text-green-800" title="Edit Item">✏️</a>
                                <button data-action="delete-item" class="text-red-600 hover:text-red-800" data-sku="<?= htmlspecialchars($item['sku'] ?? '') ?>" title="Delete Item">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination flex justify-center mt-4 space-x-2">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php
                    // Preserve existing filters in pagination links
                    $queryParams = [
                        'pageNum' => $p,
                        'search' => $filters['search'] ?? '',
                        'category' => $filters['category'] ?? '',
                        'stock' => $filters['stock'] ?? ''
                    ];
                    $queryString = http_build_query(array_filter($queryParams));
                ?>
                <a href="/admin/inventory?<?= $queryString ?>"
                   class="px-3 py-1 rounded text-sm <?= ($p == $currentPage) ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($modalMode === 'view' && $editItem): ?>
<div class="modal-outer" id="inventoryModalOuter">
    <!-- Navigation Arrows -->
    <button id="prevItemBtn" data-action="navigate-item" data-direction="prev" class="nav-arrow left" title="Previous item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
        </svg>
    </button>
    <button id="nextItemBtn" data-action="navigate-item" data-direction="next" class="nav-arrow right" title="Next item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
        </svg>
    </button>
    
    <div class="modal-content-wrapper">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold text-green-700">View Item: <?= htmlspecialchars($editItem['name'] ?? 'N/A') ?></h2>
            <a href="/admin/inventory" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</a>
        </div>

        <div class="modal-form-container gap-5">
            <div class="modal-form-main-column">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="skuDisplay" class="block text-gray-700">SKU</label>
                        <input type="text" id="skuDisplay" name="sku" class="block w-full border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['sku'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="name" class="block text-gray-700">Name</label>
                        <input type="text" id="name" name="name" class="block w-full border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="category" class="block text-gray-700">Category</label>
                        <input type="text" id="category" name="category" class="block w-full border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['category'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="sku" class="block text-gray-700">SKU</label>
                        <input type="text" id="sku" name="sku" class="block w-full border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['sku'] ?? ''); ?>">
                    </div>
                </div>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="stockLevel" class="block text-gray-700">Stock Level</label>
                        <input type="number" id="stockLevel" name="stockLevel" class="block w-full border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['stockLevel'] ?? '0'); ?>">
                    </div>
                    <div>
                        <label for="reorderPoint" class="block text-gray-700">Reorder Point</label>
                        <input type="number" id="reorderPoint" name="reorderPoint" class="block w-full border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['reorderPoint'] ?? '5'); ?>">
                    </div>
                </div>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="costPrice" class="block text-gray-700">Cost Price ($)</label>
                        <input type="number" id="costPrice" name="costPrice" class="block w-full border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['costPrice'] ?? '0.00'); ?>">
                    </div>
                    <div>
                        <label for="retailPrice" class="block text-gray-700">Retail Price ($)</label>
                        <input type="number" id="retailPrice" name="retailPrice" class="block w-full border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['retailPrice'] ?? '0.00'); ?>">
                    </div>
                </div>
                <div>
                    <label for="description" class="block text-gray-700">Description</label>
                    <textarea id="description" name="description" class="block w-full border border-gray-300 rounded bg-gray-100" rows="2" readonly><?= htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
                </div>
                                    <!-- Item Images Section - Same layout as edit modal -->
<div class="images-section-container" id="imagesSection">
                    
                    <!-- Current Images Display -->
                    <div id="currentImagesContainer" class="current-images-section">
                        <div class="text-sm text-gray-600">Current Images:</div>
                        <div id="currentImagesList" class="w-full">
                            <!-- Current images will be loaded here with dynamic layout -->
                            <div class="text-center text-gray-500 text-sm" id="viewModalImagesLoading">Loading images...</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-form-suggestions-column">
                <div class="suggestions-container">
                    <!-- Cost Breakdown Section -->
                    <div class="cost-breakdown-wrapper">
                        <div class="cost-breakdown">
                            <h3>Cost Breakdown</h3>
                            
                            <!-- Suggested Cost Display - Moved to top with price styling -->
                            <div class="bg-green-50 rounded border border-green-200">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-green-700 font-medium">Suggested Cost:</span>
                                    <span class="font-bold text-green-800 text-lg" id="suggestedCostDisplay">$0.00</span>
                                </div>
                            </div>
                            
                            <?php foreach (['materials', 'labor', 'energy', 'equipment'] as $costType): ?>
                            <div class="cost-breakdown-section <?= $costType !== 'materials' ? 'mt-3' : ''; ?>">
                                <h4 class="font-semibold text-gray-700 text-sm"><?= ucfirst($costType); ?></h4>
                                <div class="" id="view_<?= $costType; ?>List" >
                                    <?php if (!empty($editCostBreakdown[$costType])): ?>
                                        <?php foreach ($editCostBreakdown[$costType] as $item_cost): ?>
                                        <div class="cost-item">
                                            <span class="cost-item-name"><?= htmlspecialchars($costType === 'materials' ? $item_cost['name'] : $item_cost['description']) ?></span>
                                            <div class="cost-item-actions">
                                                <span class="cost-item-value">$<?= number_format(floatval($item_cost['cost'] ?? 0), 2) ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-gray-500 text-xs italic">No items added.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="cost-totals hidden">
                                <div class="cost-total-row hidden"><span class="cost-label">Materials Total:</span> <span class="cost-item-value" id="materialsTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row hidden"><span class="cost-label">Labor Total:</span> <span class="cost-item-value" id="laborTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row hidden"><span class="cost-label">Energy Total:</span> <span class="cost-item-value" id="energyTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row hidden"><span class="cost-label">Equipment Total:</span> <span class="cost-item-value" id="equipmentTotalDisplay">$0.00</span></div>
                            </div>
                        </div>
                    </div>
                
                    <!-- Price Suggestion Section for View Modal -->
                    <div class="price-suggestion-wrapper">
                        <div class="price-suggestion bg-white border border-gray-200 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <span class="">🎯</span> Price Suggestion
                            </h3>
                            
                            <!-- Price Suggestion Display -->
                            <div id="viewPriceSuggestionDisplay" class="hidden">
                                
                                <!-- Suggested Price Display -->
                                <div class="bg-green-50 rounded border border-green-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-green-700 font-medium">Suggested Price:</span>
                                        <span class="font-bold text-green-800 text-lg" id="viewDisplaySuggestedPrice">$0.00</span>
                                    </div>
                                </div>
                                
                                <!-- Reasoning Section -->
                                <div class="">
                                    <h4 class="font-semibold text-gray-700 text-sm">AI Reasoning</h4>
                                    <div class="" id="viewReasoningList">
                                        <!-- Reasoning items will be rendered here by JavaScript -->
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-green-600" id="viewDisplayConfidence">Medium confidence</span>
                                    <span class="text-green-500" id="viewDisplayTimestamp">Just now</span>
                                </div>
                            </div>
                            
                                        <!-- Price Suggestion Placeholder -->
            <div id="viewPriceSuggestionPlaceholder" class="bg-gray-50 border border-gray-200 rounded-lg">
                <div class="text-center text-gray-500">
                    <div class="text-2xl">🎯</div>
                    <div class="text-sm">No price suggestion available</div>
                    <div class="text-xs text-gray-400">Price suggestions are generated in edit mode</div>
                </div>
            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-auto border-t">
            <a href="?page=admin&section=inventory" class="bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block text-sm">Close</a>
                            <a href="?page=admin&section=inventory&edit=<?= htmlspecialchars($editItem['sku'] ?? '') ?>" class="brand-button rounded text-sm">Edit Item</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($modalMode === 'add' || ($modalMode === 'edit' && $editItem)): ?>
<div class="modal-outer" id="inventoryModalOuter">
    <!-- Navigation Arrows (only show for edit mode, not add mode) -->
    <?php if ($modalMode === 'edit'): ?>
    <button id="prevItemBtn" data-action="navigate-item" data-direction="prev" class="nav-arrow left" title="Previous item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
        </svg>
    </button>
    <button id="nextItemBtn" data-action="navigate-item" data-direction="next" class="nav-arrow right" title="Next item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
        </svg>
    </button>
    <?php endif; ?>
    
    <div class="modal-content-wrapper">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold text-green-700"><?= $modalMode === 'add' ? 'Add New Inventory Item' : 'Edit Item (' . htmlspecialchars($editItem['name'] ?? 'N/A') . ')' ?></h2>
            <a href="/admin/inventory" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</a>
        </div>

        <form id="inventoryForm" method="POST" action="#" enctype="multipart/form-data" class="flex flex-col overflow-y-auto">
            <input type="hidden" name="action" value="<?= $modalMode === 'add' ? 'add' : 'update'; ?>">
            <?php if ($modalMode === 'edit' && isset($editItem['sku'])): ?>
                <input type="hidden" name="itemSku" value="<?= htmlspecialchars($editItem['sku'] ?? ''); ?>">
            <?php endif; ?>

            <!-- 3-Grid Layout: Top Row (2 boxes) + Bottom Row (1 full-width box) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Box: Item Information -->
                <div class="bg-white border border-gray-200 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <span class="">📝</span> Item Information
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="skuEdit" class="block text-gray-700">SKU *</label>
                                <input type="text" id="skuEdit" name="sku" class="block w-full border border-gray-300 rounded <?= in_array('sku', $field_errors) ? 'field-error-highlight' : '' ?>" required 
                                       value="<?= htmlspecialchars($editItem['sku'] ?? ($nextSku ?? '')); ?>" placeholder="Auto-generated if empty">
                            </div>
                            <div>
                                <label for="name" class="block text-gray-700">Name *</label>
                                <input type="text" id="name" name="name" class="block w-full border border-gray-300 rounded <?= in_array('name', $field_errors) ? 'field-error-highlight' : '' ?>" required 
                                       value="<?= htmlspecialchars($editItem['name'] ?? ''); ?>"
                                       data-tooltip="The name of your item. Try to be more creative than 'Thing' or 'Stuff'. Your customers deserve better than that.">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label for="categoryEdit" class="block text-gray-700">Category *</label>
                                <select id="categoryEdit" name="category" class="block w-full border border-gray-300 rounded <?= in_array('category', $field_errors) ? 'field-error-highlight' : '' ?> <?= $modalMode === 'add' ? 'hidden' : '' ?>" required
                                        data-tooltip="Which category does this belong to? If you can't figure this out, maybe running a business isn't for you.">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat); ?>" <?= (isset($editItem['category']) && $editItem['category'] === $cat) ? 'selected' : ''; ?>><?= htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($modalMode === 'add'): ?>
                                <div id="aiCategoryMessage" class="bg-blue-50 border border-blue-200 rounded text-sm text-blue-800">
                                    🤖 AI will automatically select the best category after you upload a photo and we analyze your item!
                                </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="genderEdit" class="block text-gray-700">Gender *</label>
                                <select id="genderEdit" name="gender" class="block w-full border border-gray-300 rounded <?= in_array('gender', $field_errors) ? 'field-error-highlight' : '' ?>" required
                                        data-tooltip="Who is this for? Choose wisely - your customers judge you for everything, including assumptions about their gender.">
                                    <option value="">Select Gender</option>
                                    <option value="Unisex" <?= (isset($editItem['gender']) && $editItem['gender'] === 'Unisex') ? 'selected' : ''; ?>>Unisex</option>
                                    <option value="Men" <?= (isset($editItem['gender']) && $editItem['gender'] === 'Men') ? 'selected' : ''; ?>>Men</option>
                                    <option value="Women" <?= (isset($editItem['gender']) && $editItem['gender'] === 'Women') ? 'selected' : ''; ?>>Women</option>
                                    <option value="Boys" <?= (isset($editItem['gender']) && $editItem['gender'] === 'Boys') ? 'selected' : ''; ?>>Boys</option>
                                    <option value="Girls" <?= (isset($editItem['gender']) && $editItem['gender'] === 'Girls') ? 'selected' : ''; ?>>Girls</option>
                                    <option value="Baby" <?= (isset($editItem['gender']) && $editItem['gender'] === 'Baby') ? 'selected' : ''; ?>>Baby</option>
                                </select>
                            </div>
                            <div>
                                <label for="statusEdit" class="block text-gray-700">Status *</label>
                                <select id="statusEdit" name="status" class="block w-full border border-gray-300 rounded <?= in_array('status', $field_errors) ? 'field-error-highlight' : '' ?>" required
                                        data-tooltip="Draft items are hidden from customers. Live items are public. Don't put garbage live and wonder why nobody buys it.">
                                    <option value="draft" <?= (isset($editItem['status']) && $editItem['status'] === 'draft') ? 'selected' : ''; ?>>Draft (Hidden)</option>
                                    <option value="live" <?= (isset($editItem['status']) && $editItem['status'] === 'live') ? 'selected' : ''; ?>>Live (Public)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <div class="button-with-badge">
                                <button type="button" id="open-marketing-manager-btn" class="brand-button rounded text-sm" data-action="open-marketing-manager"
                                        data-tooltip="Let AI write your marketing copy because apparently describing your own products is too hard. Don't worry, the robots are better at it anyway.">
                                     🎯 Marketing Manager
                                </button>
                                <div id="step-badge-1" class="step-badge step-badge-1 pulse hidden"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="stockLevel" class="block text-gray-700">Stock Level *</label>
                                <input type="number" id="stockLevel" name="stockLevel" class="block w-full border border-gray-300 rounded <?= in_array('stockLevel', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required 
                                       value="<?= htmlspecialchars($editItem['stockLevel'] ?? '0'); ?>"
                                       data-tooltip="How many of these do you actually have? Don't lie - we're not your accountant, but your customers will be mad if you oversell.">
                            </div>
                            <div>
                                <label for="reorderPoint" class="block text-gray-700">Reorder Point *</label>
                                <input type="number" id="reorderPoint" name="reorderPoint" class="block w-full border border-gray-300 rounded <?= in_array('reorderPoint', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required 
                                       value="<?= htmlspecialchars($editItem['reorderPoint'] ?? '5'); ?>"
                                       data-tooltip="When to panic and order more. Set this too low and you'll run out. Set it too high and you'll have a warehouse full of stuff nobody wants.">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="costPrice" class="block text-gray-700">Cost Price ($) *</label>
                                <input type="number" id="costPrice" name="costPrice" class="block w-full border border-gray-300 rounded <?= in_array('costPrice', $field_errors) ? 'field-error-highlight' : '' ?>" step="0.01" min="0" required 
                                       value="<?= htmlspecialchars($editItem['costPrice'] ?? '0.00'); ?>"
                                       data-tooltip="How much you paid for this. Don't include your tears and frustration - those are free. This is just the cold, hard cash you spent.">
                            </div>
                            <div>
                                <label for="retailPrice" class="block text-gray-700">Retail Price ($) *</label>
                                <input type="number" id="retailPrice" name="retailPrice" class="block w-full border border-gray-300 rounded <?= in_array('retailPrice', $field_errors) ? 'field-error-highlight' : '' ?>" step="0.01" min="0" required 
                                       value="<?= htmlspecialchars($editItem['retailPrice'] ?? '0.00'); ?>"
                                       data-tooltip="What you're charging customers. Try to make it higher than your cost price - that's how profit works. Revolutionary concept, I know.">
                            </div>
                        </div>
                        
                        <div>
                            <label for="description" class="block text-gray-700">Description</label>
                            <textarea id="description" name="description" class="block w-full border border-gray-300 rounded" rows="3" placeholder="Enter item description or click 'Marketing Manager' for AI-powered suggestions..."
                                      data-tooltip="Describe your item. Be more creative than 'It's good' or 'People like it'. Your customers have questions, and this is where you answer them."><?= htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
                        </div>

                        <!-- Item Images Section -->
                        <div class="images-section-container" id="imagesSection">
                            <!-- Current Images Display -->
                            <div id="currentImagesContainer" class="current-images-section">
                                <div class="flex justify-between items-center">
                                    <div class="text-sm text-gray-600">Current Images:</div>
                                    <button type="button" id="processExistingImagesBtn" data-action="process-images-ai" class="bg-purple-500 text-white rounded text-xs hover:bg-purple-600 transition-colors"  data-tooltip="Let AI automatically crop all existing images to their edges and convert them to WebP format. Because apparently manually cropping photos is too much work for you.">
                                        🎨 AI Process All
                                    </button>
                                </div>
                                <div id="currentImagesList" class="w-full">
                                    <!-- Current images will be loaded here with dynamic layout -->
                                </div>
                            </div>
                            
                            <!-- Multi-Image Upload Section - Only show in edit/add mode -->
                            <div class="multi-image-upload-section" >
                                <input type="file" id="multiImageUpload" name="images[]" multiple accept="image/*" class="hidden" data-action="multi-image-upload">
                                <?php if ($modalMode === 'add'): ?>
                                <input type="file" id="aiAnalysisUpload" accept="image/*" class="hidden" data-action="ai-upload">
                                <?php endif; ?>
                                <div class="upload-controls">
                                    <div class="flex gap-2 flex-wrap">
                                        <?php if ($modalMode === 'add'): ?>
                                        <button type="button" data-action="trigger-upload" data-target="aiAnalysisUpload" class="bg-purple-500 text-white rounded hover:bg-purple-600 text-sm">
                                            🤖 Upload Photo for AI Analysis
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" data-action="trigger-upload" data-target="multiImageUpload" class="bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                                            📁 Upload Images
                                        </button>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Maximum file size: 10MB per image. Supported formats: PNG, JPG, JPEG, WebP, GIF
                                    </div>
                                    <div class="">
                                        <label class="flex items-center">
                                            <input type="checkbox" id="useAIProcessing" name="useAIProcessing" class="" checked>
                                            <span class="text-sm font-medium text-gray-700">🎨 Auto-crop to edges with AI</span>
                                        </label>
                                        <div class="text-xs text-gray-500">
                                            Automatically detect and crop to the outermost edges of objects in your images
                                        </div>
                                    </div>
                                    <div id="uploadProgress" class="hidden">
                                        <div class="text-sm text-gray-600">Uploading images...</div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div id="uploadProgressBar" class="bg-blue-600 h-2 rounded-full" ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Structure Analysis & Redesign Section (conditionally displayed) -->
                        <div id="structureAnalysisSection" class="structure-analysis-section hidden">
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-sm font-medium text-yellow-800">🎯 Size/Color System Analysis</h3>
                                        <div class="text-sm text-yellow-700">
                                            <p>Your current structure may be backwards! For better inventory management, you should have <strong>sizes first</strong> (S, M, L, XL), then colors available for each size.</p>
                                            <div class="" id="structureAnalysisResult">
                                                <em>Click "Analyze" to check your current structure...</em>
                                            </div>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button type="button" data-action="analyze-structure" class="bg-yellow-600 hover:bg-yellow-700 text-white rounded text-sm font-medium">
                                                🔍 Analyze Current Structure
                                            </button>
                                            <button type="button" data-action="show-restructure-modal" class="bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-medium">
                                                🎯 Restructure System
                                            </button>
                                            <button type="button" data-action="show-new-structure-view" class="bg-green-600 hover:bg-green-700 text-white rounded text-sm font-medium">
                                                👀 View New Structure
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Box: Cost & Price Suggestions -->
                <div class="bg-white border border-gray-200 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <span class="">💰</span> Cost & Price Analysis
                    </h3>
                    
                    <div class="suggestions-container">
                        <!-- Cost Breakdown Section -->
                        <div class="cost-breakdown-wrapper">
                            <div class="cost-breakdown">
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <span class="">💰</span> Cost Breakdown
                                </h3>
                                
                                <div class="button-with-badge w-full">
                                    <button type="button" data-action="use-suggested-cost" class="w-full bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors" 
                                            id="get-suggested-cost-btn" data-tooltip="Let AI analyze your item and suggest cost breakdown including materials, labor, energy, and equipment. Because apparently calculating costs is rocket science now.">
                                        🧮 Get Suggested Cost
                                    </button>
                                    <div id="step-badge-2" class="step-badge step-badge-2 pulse hidden"></div>
                                </div>
                                
                                <!-- Suggested Cost Display - Moved to top with price styling -->
                                <div class="bg-green-50 rounded border border-green-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-green-700 font-medium">Suggested Cost:</span>
                                        <span class="font-bold text-green-800 text-lg" id="suggestedCostDisplay">$0.00</span>
                                    </div>
                                </div>
                                
                                <div class="">
                                    <button type="button" data-action="apply-cost-suggestion" class="w-full bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors"
                                            id="apply-suggested-cost-btn" data-tooltip="Take the AI-suggested cost and put it in your cost field. For when you trust robots more than your own business judgment.">
                                        💰 Apply to Cost Field
                                    </button>
                                </div>
                                
                                <!-- Template Selection Section -->
                                <div class="bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-medium text-blue-800 text-sm">📋 Cost Templates</h4>
                                        <button type="button" data-action="toggle-template-section" class="text-blue-600 hover:text-blue-800 text-xs">
                                            <span id="templateToggleText">Show Templates</span>
                                        </button>
                                    </div>
                                    
                                    <div id="templateSection" class="hidden space-y-3">
                                        <!-- Load Template -->
                                        <div class="flex gap-2">
                                            <select id="templateSelect" class="flex-1 border border-blue-300 rounded text-xs">
                                                <option value="">Choose a template...</option>
                                            </select>
                                            <button type="button" data-action="load-template" class="bg-blue-600 text-white rounded text-xs hover:bg-blue-700">
                                                Load
                                            </button>
                                        </div>
                                        
                                        <!-- Save Template -->
                                        <div class="flex gap-2">
                                            <input type="text" id="templateName" placeholder="Template name..." class="flex-1 border border-blue-300 rounded text-xs">
                                            <button type="button" data-action="save-as-template" class="bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                                Save as Template
                                            </button>
                                        </div>
                                    </div>
                                        <div class="text-xs text-blue-600">
                                            Load existing templates or save current breakdown as a reusable template
                                        </div>
                                    </div>
                                
                                <?php foreach (['materials', 'labor', 'energy', 'equipment'] as $costType): ?>
                                <div class="cost-breakdown-section <?= $costType !== 'materials' ? 'mt-3' : ''; ?>">
                                    <h4 class="font-semibold text-gray-700 text-sm"><?= ucfirst($costType); ?></h4>
                                    <div class="" id="<?= $costType; ?>List" >
                                        <!-- Cost items will be rendered here by JavaScript -->
                                    </div>
                                    <button type="button" class="add-cost-btn" data-action="add-cost-item" data-cost-type="<?= $costType; ?>" 
                                            id="add-<?= $costType; ?>-btn" data-tooltip="<?php
                                                $tooltips = [
                                                    'materials' => 'Add raw materials and supplies to your cost breakdown. Wood, fabric, glue, tears of frustration - whatever goes into making your product.',
                                                    'labor' => 'Add time and effort costs. Your hours, assistant wages, the cost of your sanity - everything that involves human effort to create this masterpiece.',
                                                    'energy' => 'Add electricity, gas, and other utilities used in production. Because apparently even the power company wants a cut of your profits.',
                                                    'equipment' => 'Add tool depreciation, equipment rental, and machinery costs. That expensive printer, cutting machine, or whatever gadget you convinced yourself was \"essential\" for the business.'
                                                ];
                                                echo $tooltips[$costType] ?? 'Add cost items for this category.';
                                            ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                                        Add <?php
                                            $labels = ['materials' => 'Material', 'labor' => 'Labor', 'energy' => 'Energy', 'equipment' => 'Equipment'];
                                            echo $labels[$costType] ?? ucfirst($costType);
                                        ?>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="cost-totals hidden">
                                <div class="cost-total-row hidden"><span class="cost-label">Materials Total:</span> <span class="cost-item-value" id="materialsTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row hidden"><span class="cost-label">Labor Total:</span> <span class="cost-item-value" id="laborTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row hidden"><span class="cost-label">Energy Total:</span> <span class="cost-item-value" id="energyTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row hidden"><span class="cost-label">Equipment Total:</span> <span class="cost-item-value" id="equipmentTotalDisplay">$0.00</span></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Price Suggestion Section -->
                        <div class="price-suggestion-wrapper">
                            <div class="price-suggestion bg-white border border-gray-200 rounded-lg">
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <span class="">🎯</span> Price Suggestion
                                </h3>
                                
                                <div class="button-with-badge w-full">
                                    <button type="button" data-action="use-suggested-price" class="w-full bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors font-medium"
                                            id="get-suggested-price-btn" data-tooltip="Let AI analyze your item and suggest optimal pricing based on cost analysis, market research, and competitive analysis. Because apparently setting prices is too complicated for humans now.">
                                        🎯 Get Suggested Price
                                    </button>
                                    <div id="step-badge-3" class="step-badge step-badge-3 pulse hidden"></div>
                                </div>
                                
                                <!-- Price Suggestion Display -->
                                <div id="priceSuggestionDisplay" class="hidden">
                                    
                                    <!-- Suggested Price Display -->
                                    <div class="bg-green-50 rounded border border-green-200">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-green-700 font-medium">Suggested Price:</span>
                                            <span class="font-bold text-green-800 text-lg" id="displaySuggestedPrice">$0.00</span>
                                        </div>
                                    </div>
                                    
                                    <button type="button" data-action="apply-price-suggestion" class="w-full bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors"
                                            id="apply-suggested-price-btn" data-tooltip="Take the AI-suggested price and put it in your price field. Let the robots do your pricing - what could go wrong?">
                                        Apply to Retail Price
                                    </button>
                                    
                                    <!-- Reasoning Section -->
                                    <div class="">
                                        <h4 class="font-semibold text-gray-700 text-sm">AI Reasoning</h4>
                                        <div class="" id="reasoningList">
                                            <!-- Reasoning items will be rendered here by JavaScript -->
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-green-600" id="displayConfidence">Medium confidence</span>
                                        <span class="text-green-500" id="displayTimestamp">Just now</span>
                                    </div>
                                </div>
                                
                                <!-- Price Suggestion Placeholder -->
                                <div id="priceSuggestionPlaceholder" class="bg-gray-50 border border-gray-200 rounded-lg">
                                    <div class="text-center text-gray-500">
                                        <div class="text-2xl">🎯</div>
                                        <div class="text-sm">No price suggestion yet</div>
                                        <div class="text-xs text-gray-400">Click "Get Suggested Price" above to get AI pricing analysis</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Box: Gender, Size & Color Management (Full Width) -->
            <div class="bg-white border border-gray-200 rounded-lg" >
                <div class="gender-size-color-management-section">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <span class="">📦</span> Gender, Size & Color Management
                        </h3>
                        <div class="text-sm text-orange-600 bg-orange-50 rounded">
                            Hierarchy: Gender → Size → Color
                        </div>
                    </div>
                    
                    <!-- Requirement Notice -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="text-sm text-yellow-800">
                            <strong>⚠️ Publication Requirements:</strong> Items must have at least one gender, size, and color assigned before they can be set to "Live" status.
                        </div>
                    </div>
                    
                    <!-- Management Sections -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Gender Management -->
                        <div class="gender-section">
                            <div class="flex justify-between items-center">
                                <h4 class="text-md font-semibold text-gray-700 flex items-center">
                                    <span class="">👥</span> Gender Options
                                </h4>
                                <button type="button" data-action="add-item-gender" class="text-white rounded text-sm transition-colors">
                                    + Add Gender
                                </button>
                            </div>
                            <div id="gendersList" class="space-y-2">
                                <div class="text-center text-gray-500 text-sm" id="gendersLoading">Loading genders...</div>
                            </div>
                        </div>
                        
                        <!-- Size Management -->
                        <div class="size-section">
                            <div class="flex justify-between items-center">
                                <h4 class="text-md font-semibold text-gray-700 flex items-center">
                                    <span class="">📏</span> Size Options
                                </h4>
                                <button type="button" data-action="add-item-size" class="text-white rounded text-sm transition-colors">
                                    + Add Size
                                </button>
                            </div>
                            <div id="sizesList" class="space-y-2">
                                <div class="text-center text-gray-500 text-sm" id="sizesLoading">Loading sizes...</div>
                            </div>
                        </div>
                        
                        <!-- Color Management -->
                        <div class="color-section">
                            <div class="flex justify-between items-center">
                                <h4 class="text-md font-semibold text-gray-700 flex items-center">
                                    <span class="">🎨</span> Color Options
                                </h4>
                                <div class="flex space-x-2">
                                    <button type="button" data-action="match-image-to-color" class="bg-purple-600 text-white rounded text-xs hover:bg-purple-700">
                                        🖼️ Match Image
                                    </button>
                                    <button type="button" data-action="add-item-color" class="text-white rounded text-xs" >
                                        + Add Color
                                    </button>
                                </div>
                            </div>
                            <div id="colorsList" class="space-y-2">
                                <div class="text-center text-gray-500 text-sm" id="colorsLoading">Loading colors...</div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Stock Summary -->
                    <div id="stockSummary" class="bg-gray-50 border border-gray-200 rounded-lg">
                        <div class="text-sm text-gray-600" id="stockSummaryText">Stock summary will update as you add gender, size, and color options.</div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-auto border-t">
                <a href="?page=admin&section=inventory" class="bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block text-sm">Cancel</a>
                <button type="submit" id="saveItemBtn" class="brand-button rounded text-sm">
                    <span class="button-text"><?= $modalMode === 'add' ? 'Add Item' : 'Save Changes'; ?></span>
                    <span class="loading-spinner hidden"></span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../components/ai_processing_modal.php'; ?>

<div id="costFormModal" class="cost-modal">
    <div class="cost-modal-content">
        <div class="flex justify-between items-center">
            <h3 id="costFormTitle" class="text-md font-semibold text-gray-700">Edit Cost Item</h3>
            <button type="button" class="text-gray-500 hover:text-gray-700 text-2xl leading-none" data-action="close-cost-modal">&times;</button>
        </div>
        <form id="costForm" class="space-y-3">
            <input type="hidden" id="costItemId" value="">
            <input type="hidden" id="costItemType" value="">
            <div id="materialNameField" class="hidden">
                <label for="costItemName" class="block text-sm font-medium text-gray-700">Material Name *</label>
                <input type="text" id="costItemName" name="name" class="block w-full border border-gray-300 rounded">
            </div>
            <div id="genericDescriptionField" class="hidden">
                 <label for="costItemDescription" class="block text-sm font-medium text-gray-700">Description *</label>
                <input type="text" id="costItemDescription" name="description" class="block w-full border border-gray-300 rounded">
            </div>
            <div>
                <label for="costItemCost" class="block text-sm font-medium text-gray-700">Cost ($) *</label>
                <input type="number" id="costItemCost" name="cost" step="0.01" min="0" class="block w-full border border-gray-300 rounded" required>
            </div>
            <div class="flex justify-between items-center">
                <button type="button" id="deleteCostItem" class="py-1\.5 bg-red-500 text-white rounded hover:bg-red-600 text-sm hidden">Delete</button>
                <div class="flex space-x-2">
                    <button type="button" class="py-1\.5 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 text-sm" data-action="close-cost-modal">Cancel</button>
                    <button type="submit" class="brand-button py-1\.5 rounded text-sm">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="deleteConfirmModal" class="cost-modal"> <!-- Reusing cost-modal style for delete confirm -->
    <div class="cost-modal-content max-w-sm">
        <h2 class="text-md font-bold text-gray-800">Confirm Delete</h2>
        <p class="text-sm text-gray-600">Are you sure you want to delete this item? This action cannot be undone.</p>
        <div class="flex justify-end space-x-2">
            <button type="button" class="py-1\.5 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm" data-action="close-delete-modal">Cancel</button>
            <button type="button" class="py-1\.5 bg-red-600 text-white rounded hover:bg-red-700 text-sm" data-action="confirm-delete-item">Delete</button>
        </div>
    </div>
</div>

<!-- Cost Item Delete Confirmation Modal -->
<div id="deleteCostConfirmModal" class="cost-modal">

<!-- Marketing Manager Modal -->
<div id="marketingManagerModal" class="admin-modal-overlay hidden" >
    <div class="admin-modal-content">
        <!-- Modal Header -->
        <div class="admin-modal-header" >
            <div class="flex items-center">
                <h2 class="text-xl font-bold text-white">🎯 Marketing Manager</h2>
                <span class="text-green-100 text-sm font-medium bg-green-800 bg-opacity-30 rounded">Currently editing: <span id="currentEditingSku"></span></span>
            </div>
            <button data-action="close-marketing-manager" class="modal-close">&times;</button>
        </div>
        
        <!-- Tab Navigation -->
        <div class="admin-tab-bar">
            <div class="flex items-center">
                <div id="marketingItemImageHeader" class="flex-shrink-0">
                    <!-- Primary image will be loaded here -->
                </div>
                <div class="flex space-x-4 overflow-x-auto">
                    <button id="contentTab" data-action="show-marketing-tab" class="css-category-tab active" data-tab="content">
                        📝 Content
                    </button>
                    <button id="audienceTab" data-action="show-marketing-tab" class="css-category-tab" data-tab="audience">
                        👥 Target Audience
                    </button>
                    <button id="sellingTab" data-action="show-marketing-tab" class="css-category-tab" data-tab="selling">
                        ⭐ Selling Points
                    </button>
                    <button id="seoTab" data-action="show-marketing-tab" class="css-category-tab" data-tab="seo">
                        🔍 SEO & Keywords
                    </button>
                    <button id="conversionTab" data-action="show-marketing-tab" class="css-category-tab" data-tab="conversion">
                        💰 Conversion
                    </button>
                </div>
            </div>
        </div>
        
        <!-- AI Help Text - Below Tab Buttons -->
        <div class="bg-blue-50 border-b border-blue-200">
            <div class="flex items-center text-sm text-blue-700">
                <span class="">💡</span>
                <span>Use AI to automatically generate marketing content for all tabs</span>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="modal-body" >
            <div id="marketingManagerContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
        
        <!-- Footer -->
        <div class="modal-footer">
            <div class="flex space-x-3">
                <button data-action="apply-marketing-to-item" 
                        class="text-sm font-medium text-white rounded-md transition-all duration-200 transform hover:scale-105 shadow-md hover:shadow-lg"
                        >
                    📝 Apply to Item
                </button>
                <button data-action="close-marketing-manager" 
                        class="text-sm font-medium text-white rounded-md transition-all duration-200 transform hover:scale-105 shadow-md hover:shadow-lg"
                        >
                    ✓ Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- AI Content Comparison Modal -->
<div id="aiComparisonModal" class="admin-modal-overlay hidden">
    <div class="admin-modal-content">
        <!-- Fixed Header -->
        <div class="admin-modal-header" >
            <h2 class="modal-title">🤖 AI Content Comparison & Selection</h2>
            <button data-action="close-ai-comparison-modal" class="modal-close">&times;</button>
        </div>
        
        <!-- AI Analysis Progress Section (Collapsible) -->
        <div id="aiAnalysisProgressSection" class="bg-gradient-to-r from-blue-50 to-purple-50 border-b flex-shrink-0 transition-all duration-500 overflow-hidden" >
            <div class="sm:px-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div id="aiProgressSpinner" class="modal-loading-spinner"></div>
                        <span class="text-sm font-semibold text-gray-800">AI Analysis in Progress</span>
                    </div>
                    <span id="aiProgressText" class="text-xs text-gray-600">Initializing...</span>
                </div>
                
                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="aiProgressBar" class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full transition-all duration-300" ></div>
                </div>
                
                <!-- Detailed Progress Steps -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                    <div id="step1-analyze" class="flex items-center gap-1 rounded bg-white/50">
                        <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                        <span>Analyzing Content</span>
                    </div>
                    <div id="step2-extract-insights" class="flex items-center gap-1 rounded bg-white/50">
                        <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                        <span>Extracting Insights</span>
                    </div>
                    <div id="step3-generate-content" class="flex items-center gap-1 rounded bg-white/50">
                        <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                        <span>Generating Content</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scrollable Content Area -->
        <div class="flex-1 overflow-y-scroll min-h-0">
            <div class="sm:p-6">
                <div id="aiComparisonContent" class="space-y-4">
                    <div class="text-center text-gray-500">AI analysis in progress...</div>
                </div>
            </div>
        </div>
        
        <!-- Fixed Footer -->
        <div class="modal-footer">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <span id="statusText"></span>
                </div>
                <div class="flex gap-2">
                    <button data-action="apply-selected-changes" id="applyChangesBtn" class="bg-green-600 hover:bg-green-700 text-white rounded font-medium hidden">
                        Apply Selected Changes
                    </button>
                    <button data-action="close-ai-comparison-modal" class="modal-button btn btn-secondary">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cost Item Delete Confirmation Modal -->
<div id="deleteCostConfirmModal" class="cost-modal">
    <div class="cost-modal-content max-w-sm">
        <h2 class="text-md font-bold text-red-600">Delete Cost Item</h2>
        <p class="text-sm text-gray-600" id="deleteCostConfirmText">Are you sure you want to delete this cost item? This action cannot be undone.</p>
        <div class="flex justify-end space-x-2">
            <button type="button" class="py-1.5 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm" data-action="close-cost-delete-modal">Cancel</button>
            <button type="button" id="confirmCostDeleteBtn" class="py-1.5 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                <span class="button-text">Delete</span>
                <span class="loading-spinner hidden">⏳</span>
            </button>
        </div>
    </div>
</div>
                            <label class="flex items-center bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" id="applyLabor" class="text-green-600 focus:ring-green-500"
                                       ${Math.abs(parseFloat(currentCosts.labor || 0) - parseFloat(suggestionData.breakdown.labor || 0)) > 0.01 ? 'checked' : ''}>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">Labor Cost</div>
                                    <div class="text-sm text-gray-600">
                                        $${parseFloat(currentCosts.labor || 0).toFixed(2)} → $${parseFloat(suggestionData.breakdown.labor || 0).toFixed(2)}
                                        <span class="text-xs ${parseFloat(suggestionData.breakdown.labor || 0) > parseFloat(currentCosts.labor || 0) ? 'text-red-600' : 'text-green-600'}">
                                            (${parseFloat(suggestionData.breakdown.labor || 0) > parseFloat(currentCosts.labor || 0) ? '+' : ''}${(parseFloat(suggestionData.breakdown.labor || 0) - parseFloat(currentCosts.labor || 0)).toFixed(2)})
                                        </span>
                                    </div>
                                </div>
                            </label>
                        </div>
                        
                        <div class="space-y-3">
                            <label class="flex items-center bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" id="applyEnergy" class="text-green-600 focus:ring-green-500"
                                       ${Math.abs(parseFloat(currentCosts.energy || 0) - parseFloat(suggestionData.breakdown.energy || 0)) > 0.01 ? 'checked' : ''}>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">Energy Cost</div>
                                    <div class="text-sm text-gray-600">
                                        $${parseFloat(currentCosts.energy || 0).toFixed(2)} → $${parseFloat(suggestionData.breakdown.energy || 0).toFixed(2)}
                                        <span class="text-xs ${parseFloat(suggestionData.breakdown.energy || 0) > parseFloat(currentCosts.energy || 0) ? 'text-red-600' : 'text-green-600'}">
                                            (${parseFloat(suggestionData.breakdown.energy || 0) > parseFloat(currentCosts.energy || 0) ? '+' : ''}${(parseFloat(suggestionData.breakdown.energy || 0) - parseFloat(currentCosts.energy || 0)).toFixed(2)})
                                        </span>
                                    </div>
                                </div>
                            </label>
                            
                            <label class="flex items-center bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" id="applyEquipment" class="text-green-600 focus:ring-green-500"
                                       ${Math.abs(parseFloat(currentCosts.equipment || 0) - parseFloat(suggestionData.breakdown.equipment || 0)) > 0.01 ? 'checked' : ''}>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">Equipment Cost</div>
                                    <div class="text-sm text-gray-600">
                                        $${parseFloat(currentCosts.equipment || 0).toFixed(2)} → $${parseFloat(suggestionData.breakdown.equipment || 0).toFixed(2)}
                                        <span class="text-xs ${parseFloat(suggestionData.breakdown.equipment || 0) > parseFloat(currentCosts.equipment || 0) ? 'text-red-600' : 'text-green-600'}">
                                            (${parseFloat(suggestionData.breakdown.equipment || 0) > parseFloat(currentCosts.equipment || 0) ? '+' : ''}${(parseFloat(suggestionData.breakdown.equipment || 0) - parseFloat(currentCosts.equipment || 0)).toFixed(2)})
                                        </span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Select Options -->
                <div class="bg-gray-50 rounded-lg border border-gray-200">
                    <h4 class="font-medium text-gray-800">⚡ Quick Select Options</h4>
                    <div class="flex flex-wrap gap-2">
                        <button data-action="select-all-cost-fields" data-select="true" class="bg-green-100 text-green-700 rounded text-sm hover:bg-green-200 transition-colors">
                            ✅ Select All
                        </button>
                        <button data-action="select-all-cost-fields" data-select="false" class="bg-red-100 text-red-700 rounded text-sm hover:bg-red-200 transition-colors">
                            ❌ Select None
                        </button>
                        <button data-action="select-only-higher-values" class="bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200 transition-colors">
                            📈 Only Higher Values
                        </button>
                        <button data-action="select-only-lower-values" class="bg-purple-100 text-purple-700 rounded text-sm hover:bg-purple-200 transition-colors">
                            📉 Only Lower Values
                        </button>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col gap-3">
                    <!-- Primary Action: Apply Total to Cost Field -->
                    <button data-action="apply-suggested-cost-to-field" data-suggestion='${JSON.stringify(suggestionData).replace(/'/g, '&#39;').replace(/"/g, '&quot;')}' 
                            class="w-full bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 text-white rounded-lg font-semibold shadow-lg transition-all duration-200">
                        💰 Use Total Cost ($${parseFloat(suggestionData.suggestedCost).toFixed(2)}) in Cost Price Field
                    </button>
                    
                    <!-- Detailed Breakdown Actions -->
                    <div class="border-t border-gray-200">
                        <p class="text-sm text-gray-600 text-center">Or manage detailed cost breakdown:</p>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button data-action="replace-all-cost-values" data-suggestion='${JSON.stringify(suggestionData).replace(/'/g, '&#39;').replace(/"/g, '&quot;')}' 
                                    class="w-full bg-gradient-to-r from-red-600 to-orange-600 hover:from-red-700 hover:to-orange-700 text-white rounded-lg font-medium shadow transition-all duration-200 text-sm">
                                🔄 Replace Current Cost Breakdown
                            </button>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <button data-action="apply-selected-cost-fields" data-suggestion='${JSON.stringify(suggestionData).replace(/'/g, '&#39;').replace(/"/g, '&quot;')}' 
                                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg font-medium shadow transition-all duration-200 text-sm">
                                    ✅ Apply Selected Cost Fields
                                </button>
                                <button data-action="sync-cost-fields-from-form" class="w-full bg-gray-100 text-gray-800 rounded-lg hover:bg-gray-200 font-medium text-sm">
                                    🔁 Sync from Form Fields
                                </button>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 text-center space-y-1">
                            <div>🔄 <strong>Replace:</strong> Deletes all current cost items and creates new ones with AI values</div>
                            <div>➕ <strong>Add:</strong> Only selected fields will be added. Unselected fields keep their current values.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        `;
    
    document.body.appendChild(modal);
    
    // Close on overlay click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeCostSuggestionChoiceDialog();
        }
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCostSuggestionChoiceDialog();
        }
    });
}

function getCurrentCostBreakdown() {
    // Get current cost totals from the displayed values
    const currentCosts = {
        materials: 0,
        labor: 0,
        energy: 0,
        equipment: 0,
        total: 0
    };
    
    // Try to get values from totals display elements
    const materialsTotal = document.getElementById('materialsTotalDisplay');
    const laborTotal = document.getElementById('laborTotalDisplay');
    const energyTotal = document.getElementById('energyTotalDisplay');
    const equipmentTotal = document.getElementById('equipmentTotalDisplay');
    const suggestedTotal = document.getElementById('suggestedCostDisplay');
    
    if (materialsTotal) {
        currentCosts.materials = parseFloat(materialsTotal.textContent.replace('$', '')) || 0;
    }
    if (laborTotal) {
        currentCosts.labor = parseFloat(laborTotal.textContent.replace('$', '')) || 0;
    }
    if (energyTotal) {
        currentCosts.energy = parseFloat(energyTotal.textContent.replace('$', '')) || 0;
    }
    if (equipmentTotal) {
        currentCosts.equipment = parseFloat(equipmentTotal.textContent.replace('$', '')) || 0;
    }
    if (suggestedTotal) {
        currentCosts.total = parseFloat(suggestedTotal.textContent.replace('$', '')) || 0;
    }
    
    // If no total from display, calculate it
    if (currentCosts.total === 0) {
        currentCosts.total = currentCosts.materials + currentCosts.labor + currentCosts.energy + currentCosts.equipment;
    }
    
    return currentCosts;
}

function checkForExistingCosts() {
    // Check if there are any existing cost breakdown items
    const categories = ['materials', 'labor', 'energy', 'equipment'];
    
    for (const category of categories) {
        const listElement = document.getElementById(`${category}List`);
        if (listElement) {
            const items = listElement.querySelectorAll('.cost-item');
            if (items.length > 0) {
                return true;
            }
        }
    }
    
    return false;
}

// Quick select functions for cost field selection
function selectAllCostFields(selectAll) {
    const checkboxes = ['applyMaterials', 'applyLabor', 'applyEnergy', 'applyEquipment'];
    checkboxes.forEach(id => {
        const checkbox = document.getElementById(id);
        if (checkbox) checkbox.checked = selectAll;
    });
}

function selectOnlyHigherValues() {
    // This function will be called with current data available in the modal context
    // We need to parse the suggestion data from the button
    const modal = document.getElementById('costSuggestionChoiceModal');
    if (!modal) return;
    
    const button = modal.querySelector('[data-suggestion]');
    if (!button) return;
    
    try {
        const suggestionData = JSON.parse(button.getAttribute('data-suggestion').replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
        const currentCosts = getCurrentCostBreakdown();
        
        // Select only fields where AI suggestion is higher than current
        document.getElementById('applyMaterials').checked = 
            parseFloat(suggestionData.breakdown.materials || 0) > parseFloat(currentCosts.materials || 0);
        document.getElementById('applyLabor').checked = 
            parseFloat(suggestionData.breakdown.labor || 0) > parseFloat(currentCosts.labor || 0);
        document.getElementById('applyEnergy').checked = 
            parseFloat(suggestionData.breakdown.energy || 0) > parseFloat(currentCosts.energy || 0);
        document.getElementById('applyEquipment').checked = 
            parseFloat(suggestionData.breakdown.equipment || 0) > parseFloat(currentCosts.equipment || 0);
    } catch (e) {
        console.error('Error in selectOnlyHigherValues:', e);
    }
}

function selectOnlyLowerValues() {
    // This function will be called with current data available in the modal context
    const modal = document.getElementById('costSuggestionChoiceModal');
    if (!modal) return;
    
    const button = modal.querySelector('[data-suggestion]');
    if (!button) return;
    
    try {
        const suggestionData = JSON.parse(button.getAttribute('data-suggestion').replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
        const currentCosts = getCurrentCostBreakdown();
        
        // Select only fields where AI suggestion is lower than current
        document.getElementById('applyMaterials').checked = 
            parseFloat(suggestionData.breakdown.materials || 0) < parseFloat(currentCosts.materials || 0);
        document.getElementById('applyLabor').checked = 
            parseFloat(suggestionData.breakdown.labor || 0) < parseFloat(currentCosts.labor || 0);
        document.getElementById('applyEnergy').checked = 
            parseFloat(suggestionData.breakdown.energy || 0) < parseFloat(currentCosts.energy || 0);
        document.getElementById('applyEquipment').checked = 
            parseFloat(suggestionData.breakdown.equipment || 0) < parseFloat(currentCosts.equipment || 0);
    } catch (e) {
        console.error('Error in selectOnlyLowerValues:', e);
    }
}

// Replace all current cost values with AI suggestions
async function replaceAllCostValues(button) {
    try {
        const suggestionData = JSON.parse(button.getAttribute('data-suggestion').replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
        
        // Close the dialog
        closeCostSuggestionChoiceDialog();
        
        // Show loading state
        showInfo('Replacing all cost values with AI suggestions...');
        
        // Clear ALL existing cost items first
        const allCategories = ['materials', 'labor', 'energy', 'equipment'];await clearExistingCostItems(allCategories);
        
        // Wait a moment for the clearing to complete
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Add all AI suggested values
        const addPromises = [];
        
        allCategories.forEach(category => {
            const cost = parseFloat(suggestionData.breakdown[category] || 0);
            if (cost > 0) {
                console.log(`Queuing ${category} cost addition (replace mode):`, cost);
                addPromises.push(addCostItemDirectly(category, `AI Suggested ${category.charAt(0).toUpperCase() + category.slice(1)}`, cost));
            }
        });
        
        if (addPromises.length > 0) {
            const results = await Promise.all(addPromises);
            console.log('All AI cost values applied (replace mode):', results);
            
            // Refresh the cost breakdown display
            setTimeout(() => {
                refreshCostBreakdown();
                showSuccess('✅ All cost values replaced with AI suggestions!');
            }, 1000);
        } else {
            showWarning('No valid AI cost values to apply.');
        }
        
    } catch (error) {
        console.error('Error replacing all cost values:', error);
        showError('Error replacing cost values. Please check the console for details.');
    }
}

// Apply only selected cost fields
async function applySelectedCostFields(button) {
    try {
        const suggestionData = JSON.parse(button.getAttribute('data-suggestion').replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
        
        // Get which fields are selected
        const applyMaterials = document.getElementById('applyMaterials').checked;
        const applyLabor = document.getElementById('applyLabor').checked;
        const applyEnergy = document.getElementById('applyEnergy').checked;
        const applyEquipment = document.getElementById('applyEquipment').checked;
        
        // Create a modified suggestion data with only selected fields
        const selectedData = {
            ...suggestionData,
            breakdown: {
                materials: applyMaterials ? suggestionData.breakdown.materials : null,
                labor: applyLabor ? suggestionData.breakdown.labor : null,
                energy: applyEnergy ? suggestionData.breakdown.energy : null,
                equipment: applyEquipment ? suggestionData.breakdown.equipment : null
            },
            selectedFields: {
                materials: applyMaterials,
                labor: applyLabor,
                energy: applyEnergy,
                equipment: applyEquipment
            }
        };
        
        // Close the dialog
        closeCostSuggestionChoiceDialog();
        
        // Apply the selected changes
        await applySelectedCostBreakdown(selectedData);
        
    } catch (e) {
        console.error('Error applying selected cost fields:', e);
        showError('Error applying selected cost fields. Please try again.');
    }
}

// Apply selected cost breakdown (modified version)
async function applySelectedCostBreakdown(selectedData) {// Show loading state
    showInfo('Applying selected cost changes...');
    
    // Clear existing cost breakdown if any fields are selected
    const hasSelections = selectedData.selectedFields.materials || 
                         selectedData.selectedFields.labor || 
                         selectedData.selectedFields.energy || 
                         selectedData.selectedFields.equipment;
    
    if (hasSelections) {
        try {
            // First, clear existing cost items for selected categories
            const categoriesToClear = [];
            const categories = ['materials', 'labor', 'energy', 'equipment'];
            
            categories.forEach(category => {
                if (selectedData.selectedFields[category] && selectedData.breakdown[category] !== null) {
                    categoriesToClear.push(category);
                }
            });
            
            if (categoriesToClear.length > 0) {await clearExistingCostItems(categoriesToClear);
                
                // Wait a moment for the clearing to complete
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // Now add the new cost items
                const addPromises = [];
                
                categories.forEach(category => {
                    if (selectedData.selectedFields[category] && selectedData.breakdown[category] !== null) {
                        const cost = parseFloat(selectedData.breakdown[category]);
                        if (cost > 0) {addPromises.push(addCostItemDirectly(category, `AI Suggested ${category.charAt(0).toUpperCase() + category.slice(1)}`, cost));
                        }
                    }
                });
                
                if (addPromises.length > 0) {
                    const results = await Promise.all(addPromises);// Refresh the cost breakdown display
                    setTimeout(() => {
                        refreshCostBreakdown();
                        showSuccess('Selected cost fields applied successfully!');
                    }, 1000);
                } else {
                    showWarning('No valid cost values to apply.');
                }
            }
        } catch (error) {
            console.error('Error applying cost breakdown:', error);
            showError('Error applying cost fields. Please check the console for details.');
        }
    } else {
        showWarning('No fields were selected to apply.');
    }
}

// Helper function to clear existing cost items for specific categories
async function clearExistingCostItems(categories) {try {
        // First, get the current cost breakdown to find item IDs to delete
        const response = await fetch(`functions/functions/process_cost_breakdown.php?inventoryId=${currentItemSku}&costType=all`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error(`Failed to fetch cost breakdown: ${response.status}`);
        }
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(`Failed to get cost breakdown: ${data.error}`);
        }
        
        const deletePromises = [];
        
        // Delete items for each selected category
        categories.forEach(category => {
            if (data.data[category] && Array.isArray(data.data[category])) {
                data.data[category].forEach(item => {
                    if (item.id) {deletePromises.push(deleteCostItemDirect(category, item.id));
                    }
                });
            }
        });
        
        if (deletePromises.length > 0) {
            const results = await Promise.all(deletePromises);} else {}
        
    } catch (error) {
        console.error('Error clearing existing cost items:', error);
        throw error;
    }
}

// Helper function to delete a single cost item directly via API
function deleteCostItemDirect(type, itemId) {const url = `functions/functions/process_cost_breakdown.php?inventoryId=${currentItemSku}&costType=${type}&id=${itemId}`;
    
    return fetch(url, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {return response.json();
    })
    .then(result => {if (!result.success) {
            console.error(`Failed to delete ${type} cost item ${itemId}:`, result.error);
            throw new Error(`Failed to delete ${type} cost item: ${result.error}`);
        }
        return result;
    })
    .catch(error => {
        console.error(`Error deleting ${type} cost item ${itemId}:`, error);
        throw error;
    });
}

// Helper function to add cost item directly
function addCostItemDirectly(type, description, cost) {const url = `functions/functions/process_cost_breakdown.php`;
    
    // Create the data object based on cost type
    let requestData = {
        inventoryId: currentItemSku,
        costType: type,
        cost: parseFloat(cost.toFixed(2))
    };
    
    // Add type-specific fields
    if (type === 'materials') {
        requestData.name = description;
    } else {
        requestData.description = description;
    }return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(requestData)
    })
    .then(response => {return response.json();
    })
    .then(result => {if (!result.success) {
            console.error(`Failed to add ${type} cost:`, result.error);
            throw new Error(`Failed to add ${type} cost: ${result.error}`);
        }
        return result;
    })
    .catch(error => {
        console.error(`Error adding ${type} cost:`, error);
        throw error;
    });
}

function closeCostSuggestionChoiceDialog() {
    const modal = document.getElementById('costSuggestionChoiceModal');
    if (modal) {
        modal.remove();
    }
}

function showPriceSuggestionChoiceDialog(suggestionData) {
    // Get current pricing values for comparison
    const currentPrice = getCurrentPrice();
    const hasExistingPrice = checkForExistingPriceSuggestion();
    
    // Create the modal overlay
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'priceSuggestionChoiceModal';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="bg-gradient-to-r from-green-600 to-blue-600 flex-shrink-0">
                <h2 class="text-xl font-bold text-white flex items-center">
                    🎯 AI Price Suggestion - Side by Side Comparison
                </h2>
            </div>
            
            <div class="overflow-y-auto flex-1 custom-scrollbar" >
                <!-- AI Analysis Summary -->
                <div class="bg-green-50 rounded-lg border border-green-200">
                    <h3 class="font-semibold text-gray-800 flex items-center">
                        <span class="">🤖</span> AI Pricing Analysis
                    </h3>
                    <p class="text-sm text-gray-700">${suggestionData.reasoning || 'Advanced pricing analysis completed'}</p>
                    <div class="text-xs text-green-600">
                        <strong>Confidence:</strong> ${suggestionData.confidence || 'medium'} • 
                        <strong>Suggested Price:</strong> $${parseFloat(suggestionData.suggestedPrice).toFixed(2)}
                    </div>
                </div>
                
                <!-- Side by Side Comparison -->
                <div class="">
                    <h3 class="font-semibold text-gray-800">💰 Price Comparison</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Current Price Column -->
                        <div class="bg-gray-50 rounded-lg border border-gray-200">
                            <h4 class="font-semibold text-gray-700 text-center">📊 Current Price</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center bg-white rounded border">
                                    <span class="text-sm font-medium text-gray-600">Retail Price:</span>
                                    <span class="font-semibold text-gray-800">$${parseFloat(currentPrice.retail || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center bg-white rounded border">
                                    <span class="text-sm font-medium text-gray-600">Cost Price:</span>
                                    <span class="font-semibold text-gray-800">$${parseFloat(currentPrice.cost || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center bg-gray-100 rounded border-2 border-gray-300">
                                    <span class="font-semibold text-gray-700">Profit Margin:</span>
                                    <span class="text-lg font-bold text-gray-800">${currentPrice.retail > 0 ? (((currentPrice.retail - currentPrice.cost) / currentPrice.retail) * 100).toFixed(1) : '0.0'}%</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- AI Suggested Price Column -->
                        <div class="bg-green-50 rounded-lg border border-green-200">
                            <h4 class="font-semibold text-green-700 text-center">🤖 AI Suggested Price</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center bg-white rounded border border-green-200">
                                    <span class="text-sm font-medium text-green-600">Suggested Price:</span>
                                    <span class="font-semibold text-green-800">$${parseFloat(suggestionData.suggestedPrice).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center bg-white rounded border border-green-200">
                                    <span class="text-sm font-medium text-green-600">Cost Price:</span>
                                    <span class="font-semibold text-green-800">$${parseFloat(currentPrice.cost || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center bg-green-100 rounded border-2 border-green-300">
                                    <span class="font-semibold text-green-700">Profit Margin:</span>
                                    <span class="text-lg font-bold text-green-800">${suggestionData.suggestedPrice > 0 ? (((suggestionData.suggestedPrice - currentPrice.cost) / suggestionData.suggestedPrice) * 100).toFixed(1) : '0.0'}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pricing Components Breakdown -->
                ${suggestionData.components && suggestionData.components.length > 0 ? `
                <div class="">
                    <h3 class="font-semibold text-gray-800">🔍 Pricing Components Analysis</h3>
                    <div class="bg-blue-50 rounded-lg border border-blue-200">
                        <div class="space-y-3">
                            ${suggestionData.components.map(component => `
                                <div class="flex justify-between items-center bg-white rounded border">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-800">${component.label}</div>
                                        <div class="text-xs text-gray-600">${component.explanation || ''}</div>
                                    </div>
                                    <span class="font-semibold text-blue-800">$${parseFloat(component.amount).toFixed(2)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <!-- Price Selection -->
                <div class="">
                    <h3 class="font-semibold text-gray-800">🎯 Choose Your Pricing Strategy</h3>
                    <div class="bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-sm text-yellow-800">
                            <span class="font-semibold">💡 Pro Tip:</span> You can apply the AI suggested price or keep your current price. 
                            The AI analysis provides valuable insights for your pricing decision.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="priceChoice" value="suggested" id="applySuggestedPrice" class="text-green-600 focus:ring-green-500" checked>
                            <div class="flex-1">
                                <div class="font-medium text-gray-800">Use AI Suggested Price</div>
                                <div class="text-sm text-gray-600">
                                    $${parseFloat(suggestionData.suggestedPrice).toFixed(2)} 
                                    <span class="text-xs ${suggestionData.suggestedPrice > currentPrice.retail ? 'text-green-600' : 'text-red-600'}">
                                        (${suggestionData.suggestedPrice > currentPrice.retail ? '+' : ''}${(suggestionData.suggestedPrice - currentPrice.retail).toFixed(2)} vs current)
                                    </span>
                                </div>
                            </div>
                        </label>
                        
                        <label class="flex items-center bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="priceChoice" value="current" id="keepCurrentPrice" class="text-gray-600 focus:ring-gray-500">
                            <div class="flex-1">
                                <div class="font-medium text-gray-800">Keep Current Price</div>
                                <div class="text-sm text-gray-600">
                                    $${parseFloat(currentPrice.retail || 0).toFixed(2)} (no change)
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                ${hasExistingPrice ? `
                    <div class="bg-amber-50 rounded-lg border border-amber-200">
                        <div class="flex items-center">
                            <span class="text-amber-600">⚠️</span>
                            <span class="font-medium text-amber-800">Existing Price Suggestion Found</span>
                        </div>
                        <p class="text-sm text-amber-700">
                            You have an existing price suggestion displayed. This new analysis will replace the previous suggestion.
                        </p>
                    </div>
                ` : ''}
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button data-action="apply-selected-price-choice" data-suggestion='${JSON.stringify(suggestionData).replace(/'/g, '&#39;').replace(/"/g, '&quot;')}' 
                            class="flex-1 bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 text-white rounded-lg font-semibold shadow-lg transition-all duration-200">
                        🎯 Apply Selected Choice
                    </button>
                    
                    <button data-action="close-price-suggestion-choice-dialog" 
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold transition-all duration-200">
                        ❌ Cancel
                    </button>
                </div>
                
                <div class="text-xs text-gray-500 text-center">
                    💡 Tip: The AI analysis will be saved for reference even if you keep your current price
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close on overlay click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closePriceSuggestionChoiceDialog();
        }
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePriceSuggestionChoiceDialog();
        }
    });
}

function checkForExistingPriceSuggestion() {
    // Check if there's an existing price suggestion displayed
    const display = document.getElementById('priceSuggestionDisplay');
    return display && !display.classList.contains('hidden');
}

function closePriceSuggestionChoiceDialog() {
    const modal = document.getElementById('priceSuggestionChoiceModal');
    if (modal) {
        modal.remove();
    }
}

function getCurrentPrice() {
    // Get current pricing values from form fields
    const retailPriceField = document.getElementById('retailPrice');
    const costPriceField = document.getElementById('costPrice');
    
    return {
        retail: parseFloat(retailPriceField ? retailPriceField.value : 0) || 0,
        cost: parseFloat(costPriceField ? costPriceField.value : 0) || 0
    };
}

function applySelectedPriceChoice(buttonElement) {
    // Get suggestion data from the button's data attribute
    const suggestionData = JSON.parse(buttonElement.dataset.suggestion);
    
    // Get selected choice
    const selectedChoice = document.querySelector('input[name="priceChoice"]:checked').value;
    
    // Close the choice dialog
    closePriceSuggestionChoiceDialog();
    
    if (selectedChoice === 'suggested') {
        // Apply the AI suggested price to the retail price field
        const retailPriceField = document.getElementById('retailPrice');
        if (retailPriceField) {
            retailPriceField.value = parseFloat(suggestionData.suggestedPrice).toFixed(2);
            
            // Trigger change event to update any dependencies
            retailPriceField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        showSuccess( `✅ AI suggested price applied! New price: $${suggestionData.suggestedPrice} (${suggestionData.confidence || 'medium'} confidence)`);
    } else {
        showInfo( '📋 Current price kept. AI analysis saved for reference.');
    }
    
    // Always display the price suggestion inline for reference
    displayPriceSuggestion({
        suggestedPrice: suggestionData.suggestedPrice,
        reasoning: suggestionData.reasoning,
        confidence: suggestionData.confidence,
        factors: suggestionData.factors,
        components: suggestionData.components,
        createdAt: new Date().toISOString(),
        applied: selectedChoice === 'suggested'
    });
}

function applySuggestedPriceAnalysis(buttonElement) {
    // Legacy function - redirect to new function for backward compatibility
    applySelectedPriceChoice(buttonElement);
}

// Cost suggestion helpers migrated to Vite module (src/js/admin-inventory.js)


async function useSuggestedPrice() {
    console.log('🎯 useSuggestedPrice() called - generating NEW price suggestion');
    const nameField = document.getElementById('name');
    const descriptionField = document.getElementById('description');
    const categoryField = document.getElementById('categoryEdit');
    const costPriceField = document.getElementById('costPrice');
    
    if (!nameField || !nameField.value.trim()) {
        showError( 'Item name is required for price suggestion');
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '🔍 Analyzing...';
    button.disabled = true;// Check if current AI model supports images
    const supportsImages = await checkAIImageSupport();
    
    // Gather item data
    const itemData = {
        name: nameField.value.trim(),
        description: descriptionField ? descriptionField.value.trim() : '',
        category: categoryField ? categoryField.value : '',
        costPrice: costPriceField ? parseFloat(costPriceField.value) || 0 : 0,
        sku: currentItemSku || '',
        useImages: supportsImages
    };
    
    try {
        // Call the price suggestion APIconst response = await fetch('/api/suggest_price.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin', // Include session cookies
            body: JSON.stringify(itemData)
        });const data = await response.json();if (data.success) {// Show choice dialog with the new figures
            showPriceSuggestionChoiceDialog(data);
        } else {showError( data.error || 'Failed to get price suggestion');
        }
    } catch (error) {
        console.error('Error getting price suggestion:', error);
        showError( 'Failed to connect to pricing service');
    } finally {
        // Restore button state
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

function displayPriceSuggestion(data) {
    const display = document.getElementById('priceSuggestionDisplay');
    const placeholder = document.getElementById('priceSuggestionPlaceholder');
    const priceElement = document.getElementById('displaySuggestedPrice');
    const reasoningList = document.getElementById('reasoningList');
    const confidenceElement = document.getElementById('displayConfidence');
    const timestampElement = document.getElementById('displayTimestamp');
    
    if (display && priceElement && reasoningList && confidenceElement && timestampElement) {
        priceElement.textContent = '$' + parseFloat(data.suggestedPrice).toFixed(2);
        
        // Clear existing reasoning list
        reasoningList.innerHTML = '';
        
        // Use the new components structure if available, otherwise fall back to parsing reasoning
        if (data.components && data.components.length > 0) {
            data.components.forEach(component => {
                const listItem = document.createElement('div');
                listItem.className = 'cost-item-row flex justify-between items-center p-1 rounded text-xs mb-1';
                
                listItem.innerHTML = `
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center space-x-2">
                            <div class="info-icon-container relative">
                                <span class="info-icon cursor-help w-4 h-4 border border-blue-500 text-blue-500 bg-transparent rounded-full flex items-center justify-center text-xs font-bold hover:bg-blue-50" 
                                      data-action="show-pricing-tooltip-with-data"
                                      data-component-type="${component.type}"
                                      data-explanation="${encodeURIComponent(String(component.explanation))}">i</span>
                            </div>
                            <span class="text-green-700">${component.label}</span>
                        </div>
                        <span class="text-green-600 font-semibold">$${parseFloat(component.amount).toFixed(2)}</span>
                    </div>
                `;
                reasoningList.appendChild(listItem);
            });
        } else {
            // Fallback to old parsing method
            const reasoning = data.reasoning || 'No reasoning provided';
            const reasoningItems = reasoning.split('•').filter(item => item.trim().length > 0);
            
            if (reasoningItems.length > 0) {
                reasoningItems.forEach(item => {
                    const trimmedItem = item.trim();
                    if (trimmedItem) {
                        // Extract dollar amount if it exists
                        let dollarAmount = '';
                        let cleanedItem = trimmedItem;
                        const dollarMatch = cleanedItem.match(/:\s*\$(\d+(?:\.\d{2})?)/);
                        if (dollarMatch) {
                            dollarAmount = '$' + dollarMatch[1];
                            cleanedItem = cleanedItem.replace(/:\s*\$\d+(\.\d{2})?/, ''); // Remove from main text
                        }
                        
                        if (cleanedItem) {
                            const listItem = document.createElement('div');
                            listItem.className = 'cost-item-row flex justify-between items-center p-1 rounded text-xs mb-1';
                            
                            listItem.innerHTML = `
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center space-x-2">
                                        <div class="info-icon-container relative">
                                            <span class="info-icon cursor-help w-4 h-4 border border-blue-500 text-blue-500 bg-transparent rounded-full flex items-center justify-center text-xs font-bold hover:bg-blue-50" 
                                                  data-action="show-pricing-tooltip-text"
                                                  data-text="${encodeURIComponent(cleanedItem)}">i</span>
                                        </div>
                                        <span class="text-green-700">${cleanedItem}</span>
                                    </div>
                                    ${dollarAmount ? `<span class="text-green-600 font-semibold">${dollarAmount}</span>` : ''}
                                </div>
                            `;
                            reasoningList.appendChild(listItem);
                        }
                    }
                });
            } else {
                reasoningList.innerHTML = '<p class="text-gray-500 text-xs italic">No reasoning provided.</p>';
            }
        }
        
        confidenceElement.textContent = (data.confidence || 'medium').charAt(0).toUpperCase() + (data.confidence || 'medium').slice(1) + ' confidence';
        
        // Format timestamp
        const date = new Date(data.createdAt);
        const now = new Date();
        const diffMinutes = Math.floor((now - date) / (1000 * 60));
        
        let timeText;
        if (diffMinutes < 1) {
            timeText = 'Just now';
        } else if (diffMinutes < 60) {
            timeText = `${diffMinutes} min ago`;
        } else if (diffMinutes < 1440) {
            timeText = `${Math.floor(diffMinutes / 60)} hr ago`;
        } else {
            timeText = date.toLocaleDateString();
        }
        timestampElement.textContent = timeText;
        
        // Store the suggested price for apply function
        display.dataset.suggestedPrice = data.suggestedPrice;
        
        // Hide placeholder and show the display
        if (placeholder) placeholder.classList.add('hidden');
        display.classList.remove('hidden');
    }
}

function applyPriceSuggestion() {
    const display = document.getElementById('priceSuggestionDisplay');
    const retailPriceField = document.getElementById('retailPrice');
    
    if (display && retailPriceField && display.dataset.suggestedPrice) {
        retailPriceField.value = parseFloat(display.dataset.suggestedPrice).toFixed(2);
        
        // Add visual feedback
        retailPriceField.style.backgroundColor = '#dcfce7';
        setTimeout(() => {
            retailPriceField.style.backgroundColor = '';
        }, 2000);
        
        showSuccess( 'Suggested price applied to Retail Price field!');
    }
}

function applyCostSuggestionToCost() {
    const suggestedCostDisplay = document.getElementById('suggestedCostDisplay');
    const costPriceField = document.getElementById('costPrice');
    
    if (suggestedCostDisplay && costPriceField) {
        // Get the suggested cost value from the cost breakdown display
        const suggestedCostText = suggestedCostDisplay.textContent.replace('$', '');
        const suggestedCostValue = parseFloat(suggestedCostText) || 0;
        
        if (suggestedCostValue > 0) {
            costPriceField.value = suggestedCostValue.toFixed(2);
            
            // Add visual feedback with blue color for cost
            costPriceField.style.backgroundColor = '#dbeafe';
            setTimeout(() => {
                costPriceField.style.backgroundColor = '';
            }, 2000);
            
            showSuccess( 'Suggested cost applied to Cost Price field!');
        } else {
            showError( 'No suggested cost available. Please generate a cost suggestion first using "🧮 Get Suggested Cost".');
        }
    } else {
        showError( 'Cost suggestion elements not found. Please refresh the page.');
    }
}

function applySuggestedCostToCostField(button) {
    try {
        // Parse the suggestion data from the button
        const suggestionData = JSON.parse(button.getAttribute('data-suggestion').replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
        
        // Get the cost price field
        const costPriceField = document.getElementById('costPrice');
        
        if (costPriceField) {
            // Apply the suggested cost to the cost price field
            const suggestedCost = parseFloat(suggestionData.suggestedCost) || 0;
            costPriceField.value = suggestedCost.toFixed(2);
            
            // Add visual feedback with green color for cost
            costPriceField.style.backgroundColor = '#dcfce7';
            costPriceField.style.borderColor = '#16a34a';
            setTimeout(() => {
                costPriceField.style.backgroundColor = '';
                costPriceField.style.borderColor = '';
            }, 3000);
            
            // Close the modal
            closeCostSuggestionChoiceDialog();
            
            // Show success message
            showSuccess( `AI suggested cost of $${suggestedCost.toFixed(2)} applied to Cost Price field!`);
        } else {
            showError( 'Cost Price field not found. Please refresh the page.');
        }
    } catch (error) {
        console.error('Error applying suggested cost to cost field:', error);
        showError( 'Error applying suggested cost. Please try again.');
    }
}

async function getPricingExplanation(reasoningText) {
    try {
        const url = `/api/get_pricing_explanation.php?text=${encodeURIComponent(reasoningText)}`;const response = await fetch(url);const data = await response.json();if (data.success) {
            return {
                title: data.title,
                explanation: data.explanation
            };
        } else {return {
                title: 'AI Pricing Analysis',
                explanation: 'Advanced algorithmic analysis considering multiple market factors and pricing strategies.'
            };
        }
    } catch (error) {
        console.error('Error fetching pricing explanation:', error);
        return {
            title: 'AI Pricing Analysis',
            explanation: 'Advanced algorithmic analysis considering multiple market factors and pricing strategies.'
        };
    }
}

let tooltipTimeout;
let currentTooltip;

function hidePricingTooltipDelayed() {
    tooltipTimeout = setTimeout(() => {
        if (currentTooltip && currentTooltip.parentNode) {
            currentTooltip.remove();
            currentTooltip = null;
        }
    }, 300); // 300ms delay
}

// New function to show tooltip with direct component data
async function showPricingTooltipWithData(event, componentType, explanation) {
    event.stopPropagation();
    
    // Clear any pending hide timeout
    if (tooltipTimeout) {
        clearTimeout(tooltipTimeout);
        tooltipTimeout = null;
    }
    
    // Remove any existing tooltips
    const existingTooltip = document.querySelector('.pricing-tooltip');
    if (existingTooltip) {
        existingTooltip.remove();
    }
    
    // Show tooltip with direct data
    const iconContainer = event.target.closest('.info-icon-container');
    iconContainer.style.position = 'relative';
    
    const tooltip = document.createElement('div');
    tooltip.className = 'pricing-tooltip absolute z-50 bg-gray-800 text-white text-xs rounded-lg p-3 shadow-lg max-w-xs';
    tooltip.style.cssText = `
        left: 25px;
        top: -10px;
        white-space: normal;
        line-height: 1.4;
        pointer-events: auto;
    `;
    
    // Create title based on component type
    const titles = {
        'cost_plus': 'Cost-Plus Pricing',
        'market_research': 'Market Research Analysis',
        'competitive_analysis': 'Competitive Analysis',
        'value_based': 'Value-Based Pricing',
        'brand_premium': 'Brand Premium',
        'psychological_pricing': 'Psychological Pricing',
        'seasonality': 'Seasonal Adjustment',
        'analysis': 'AI Pricing Analysis'
    };
    
    const title = titles[componentType] || 'Pricing Analysis';
    
    tooltip.innerHTML = `
        <div class="font-semibold">${title}</div>
        <div>${explanation}</div>
    `;
    
    // Add hover persistence
    tooltip.addEventListener('mouseenter', () => {
        if (tooltipTimeout) {
            clearTimeout(tooltipTimeout);
            tooltipTimeout = null;
        }
    });
    
    tooltip.addEventListener('mouseleave', () => {
        hidePricingTooltipDelayed();
    });
    
    iconContainer.appendChild(tooltip);
    currentTooltip = tooltip;
}

async function showPricingTooltip(event, reasoningText) {
    event.stopPropagation();
    
    // Clear any pending hide timeout
    if (tooltipTimeout) {
        clearTimeout(tooltipTimeout);
        tooltipTimeout = null;
    }
    
    // Remove any existing tooltips
    const existingTooltip = document.querySelector('.pricing-tooltip');
    if (existingTooltip) {
        existingTooltip.remove();
    }
    
    // Show loading tooltip first
    const iconContainer = event.target.closest('.info-icon-container');
    iconContainer.style.position = 'relative';
    
    const loadingTooltip = document.createElement('div');
    loadingTooltip.className = 'pricing-tooltip absolute z-50 bg-gray-800 text-white text-xs rounded-lg p-3 shadow-lg max-w-xs';
    loadingTooltip.style.cssText = `
        top: -10px;
        left: 50%;
        transform: translateX(-50%) translateY(-100%);
        word-wrap: break-word;
        line-height: 1.4;
    `;
    loadingTooltip.innerHTML = `
        <div class="tooltip-arrow absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-800"></div>
        <div class="flex items-center space-x-2">
            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-white"></div>
            <span>Loading explanation...</span>
        </div>
    `;
    iconContainer.appendChild(loadingTooltip);
    
    try {
        // Get explanation from database
        const explanationData = await getPricingExplanation(reasoningText);
        // Remove loading tooltip
        loadingTooltip.remove();
        
        // Create actual tooltip with data
        const tooltip = document.createElement('div');
        tooltip.className = 'pricing-tooltip absolute z-50 bg-gray-800 text-white text-xs rounded-lg p-3 shadow-lg max-w-xs';
        tooltip.style.cssText = `
            top: -10px;
            left: 50%;
            transform: translateX(-50%) translateY(-100%);
            word-wrap: break-word;
            line-height: 1.4;
        `;
        tooltip.innerHTML = `
            <div class="tooltip-arrow absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-800"></div>
            <div class="font-semibold text-blue-200">${explanationData.title}</div>
            <div>${explanationData.explanation}</div>
        `;
        
        // Add hover persistence to tooltip
        tooltip.addEventListener('mouseenter', () => {
            if (tooltipTimeout) {
                clearTimeout(tooltipTimeout);
                tooltipTimeout = null;
            }
        });
        
        tooltip.addEventListener('mouseleave', () => {
            hidePricingTooltipDelayed();
        });
        
        iconContainer.appendChild(tooltip);
        currentTooltip = tooltip;
        
        // Auto-hide after 8 seconds or on outside click
        const hideTooltip = () => {
            if (tooltip && tooltip.parentNode) {
                tooltip.remove();
            }
            document.removeEventListener('click', outsideClickHandler);
        };
        
        const outsideClickHandler = (e) => {
            if (!tooltip.contains(e.target) && !iconContainer.contains(e.target)) {
                hideTooltip();
            }
        };
        
        setTimeout(hideTooltip, 8000);
        setTimeout(() => document.addEventListener('click', outsideClickHandler), 100);
        
    } catch (error) {
        console.error('Error showing pricing tooltip:', error);
        loadingTooltip.remove();
        
        // Show error tooltip
        const errorTooltip = document.createElement('div');
        errorTooltip.className = 'pricing-tooltip absolute z-50 bg-red-800 text-white text-xs rounded-lg p-3 shadow-lg max-w-xs';
        errorTooltip.style.cssText = `
            top: -10px;
            left: 50%;
            transform: translateX(-50%) translateY(-100%);
            word-wrap: break-word;
            line-height: 1.4;
        `;
        errorTooltip.innerHTML = `
            <div class="tooltip-arrow absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-red-800"></div>
            <div class="font-semibold">Error Loading Explanation</div>
            <div>Unable to load pricing explanation. Please try again.</div>
        `;
        iconContainer.appendChild(errorTooltip);
        
        setTimeout(() => {
            if (errorTooltip && errorTooltip.parentNode) {
                errorTooltip.remove();
            }
        }, 3000);
    }
}

function clearPriceSuggestion() {
    const display = document.getElementById('priceSuggestionDisplay');
    const placeholder = document.getElementById('priceSuggestionPlaceholder');
    const reasoningList = document.getElementById('reasoningList');
    
    if (display) {
        display.classList.add('hidden');
    }
    if (placeholder) {
        placeholder.classList.remove('hidden');
    }
    if (reasoningList) {
        reasoningList.innerHTML = '';
    }
}

// View Modal Price Suggestion Functions
function getViewModePriceSuggestion() {
    if (!currentItemSku) {
        showError( 'No item SKU available');
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '🔍 Analyzing...';
    button.disabled = true;
    
    // Get item data from view modal fields
    const nameField = document.getElementById('name');
    const descriptionField = document.getElementById('description');
    const categoryField = document.getElementById('category');
    const costPriceField = document.getElementById('costPrice');
    
    // Check if current AI model supports images
    checkAIImageSupport().then(supportsImages => {
        // Gather item data
        const itemData = {
            name: nameField ? nameField.value.trim() : '',
            description: descriptionField ? descriptionField.value.trim() : '',
            category: categoryField ? categoryField.value : '',
            costPrice: costPriceField ? parseFloat(costPriceField.value) || 0 : 0,
            sku: currentItemSku,
            useImages: supportsImages
        };
        
        // Call the price suggestion API
        fetch('/api/suggest_price.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin', // Include session cookies
        body: JSON.stringify(itemData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Display the price suggestion inline for view modal
            displayViewPriceSuggestion({
                suggestedPrice: data.suggestedPrice,
                reasoning: data.reasoning,
                confidence: data.confidence,
                factors: data.factors,
                createdAt: new Date().toISOString()
            });
            
            showSuccess( 'Price suggestion generated and saved!');
        } else {
            showError( data.error || 'Failed to get price suggestion');
        }
    })
    .catch(error => {
        console.error('Error getting price suggestion:', error);
        showError( 'Failed to connect to pricing service');
    })
    .finally(() => {
        // Restore button state
        button.innerHTML = originalText;
        button.disabled = false;
    });
    });
}

function displayViewPriceSuggestion(data) {
    const display = document.getElementById('viewPriceSuggestionDisplay');
    const placeholder = document.getElementById('viewPriceSuggestionPlaceholder');
    const priceElement = document.getElementById('viewDisplaySuggestedPrice');
    const reasoningList = document.getElementById('viewReasoningList');
    const confidenceElement = document.getElementById('viewDisplayConfidence');
    const timestampElement = document.getElementById('viewDisplayTimestamp');
    
    if (display && priceElement && reasoningList && confidenceElement && timestampElement) {
        priceElement.textContent = '$' + parseFloat(data.suggestedPrice).toFixed(2);
        
        // Clear existing reasoning list
        reasoningList.innerHTML = '';
        
        // Use the new components structure if available, otherwise fall back to parsing reasoning
        if (data.components && data.components.length > 0) {
            data.components.forEach(component => {
                const listItem = document.createElement('div');
                listItem.className = 'cost-item-row flex justify-between items-center p-1 rounded text-xs mb-1';
                
                listItem.innerHTML = `
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center space-x-2">
                            <div class="info-icon-container relative">
                                <span class="info-icon cursor-help w-4 h-4 border border-blue-500 text-blue-500 bg-transparent rounded-full flex items-center justify-center text-xs font-bold hover:bg-blue-50" 
                                      data-action="show-pricing-tooltip-with-data"
                                      data-component-type="${component.type}"
                                      data-explanation="${encodeURIComponent(String(component.explanation))}">i</span>
                            </div>
                            <span class="text-green-700">${component.label}</span>
                        </div>
                        <span class="text-green-600 font-semibold">$${parseFloat(component.amount).toFixed(2)}</span>
                    </div>
                `;
                reasoningList.appendChild(listItem);
            });
        } else {
            // Fallback to old parsing method
            const reasoning = data.reasoning || 'No reasoning provided';
            const reasoningItems = reasoning.split('•').filter(item => item.trim().length > 0);
            
            if (reasoningItems.length > 0) {
                reasoningItems.forEach(item => {
                    const trimmedItem = item.trim();
                    if (trimmedItem) {
                        // Extract dollar amount if it exists
                        let dollarAmount = '';
                        let cleanedItem = trimmedItem;
                        const dollarMatch = cleanedItem.match(/:\s*\$(\d+(?:\.\d{2})?)/);
                        if (dollarMatch) {
                            dollarAmount = '$' + dollarMatch[1];
                            cleanedItem = cleanedItem.replace(/:\s*\$\d+(\.\d{2})?/, ''); // Remove from main text
                        }
                        
                        if (cleanedItem) {
                            const listItem = document.createElement('div');
                            listItem.className = 'cost-item-row flex justify-between items-center p-1 rounded text-xs mb-1';
                            
                            listItem.innerHTML = `
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center space-x-2">
                                        <div class="info-icon-container relative">
                                            <span class="info-icon cursor-help w-4 h-4 border border-blue-500 text-blue-500 bg-transparent rounded-full flex items-center justify-center text-xs font-bold hover:bg-blue-50" 
                                                  data-action="show-pricing-tooltip-text"
                                                  data-text="${encodeURIComponent(cleanedItem)}">i</span>
                                        </div>
                                        <span class="text-green-700">${cleanedItem}</span>
                                    </div>
                                    ${dollarAmount ? `<span class="text-green-600 font-semibold">${dollarAmount}</span>` : ''}
                                </div>
                            `;
                            reasoningList.appendChild(listItem);
                        }
                    }
                });
            } else {
                reasoningList.innerHTML = '<p class="text-gray-500 text-xs italic">No reasoning provided.</p>';
            }
        }
        
        confidenceElement.textContent = (data.confidence || 'medium').charAt(0).toUpperCase() + (data.confidence || 'medium').slice(1) + ' confidence';
        
        // Format timestamp
        const date = new Date(data.createdAt);
        const now = new Date();
        const diffMinutes = Math.floor((now - date) / (1000 * 60));
        
        let timeText;
        if (diffMinutes < 1) {
            timeText = 'Just now';
        } else if (diffMinutes < 60) {
            timeText = `${diffMinutes} min ago`;
        } else if (diffMinutes < 1440) {
            timeText = `${Math.floor(diffMinutes / 60)} hr ago`;
        } else {
            timeText = date.toLocaleDateString();
        }
        timestampElement.textContent = timeText;
        
        // Store the suggested price for potential future use
        display.dataset.suggestedPrice = data.suggestedPrice;
        
        // Hide placeholder and show the display
        if (placeholder) placeholder.classList.add('hidden');
        display.classList.remove('hidden');
    }
}

function clearViewPriceSuggestion() {
    const display = document.getElementById('viewPriceSuggestionDisplay');
    const placeholder = document.getElementById('viewPriceSuggestionPlaceholder');
    const reasoningList = document.getElementById('viewReasoningList');
    
    if (display) {
        display.classList.add('hidden');
    }
    if (placeholder) {
        placeholder.classList.remove('hidden');
    }
    if (reasoningList) {
        reasoningList.innerHTML = '';
    }
}
// Cost breakdown helpers migrated to Vite module (src/js/admin-inventory.js)

function generateMarketingCopy() {
    const nameField = document.getElementById('name');
    const categoryField = document.getElementById('categoryEdit');
    const descriptionField = document.getElementById('description');
    
    if (!nameField || !nameField.value.trim()) {
        showError( 'Item name is required for marketing copy generation');
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '✨ Generating...';
    button.disabled = true;
    
    // Gather item data
    const itemData = {
        name: nameField.value.trim(),
        category: categoryField ? categoryField.value : '',
        currentDescription: descriptionField ? descriptionField.value.trim() : ''
    };
    
    // Add SKU to item data
    itemData.sku = currentItemSku;
    
    // Call the AI marketing suggestion API
    fetch('/api/suggest_marketing.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(itemData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show comprehensive marketing intelligence modal
            showMarketingIntelligenceModal(data);
        } else {
            showError( data.error || 'Failed to generate marketing suggestions');
        }
    })
    .catch(error => {
        console.error('Error generating marketing suggestions:', error);
        showError( 'Failed to connect to AI marketing service');
    })
    .finally(() => {
        // Restore button state
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function showMarketingIntelligenceModal(data) {
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.id = 'marketingIntelligenceModal';
    
    // Create modal content with comprehensive marketing intelligence
    overlay.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-7xl max-h-[95vh] overflow-y-auto">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                        <span class="text-2xl">🧠</span>
                    </div>
                    <div class="">
                        <h3 class="text-xl font-bold text-gray-900">AI Marketing Intelligence</h3>
                        <p class="text-sm text-gray-500">Comprehensive marketing analysis and suggestions</p>
                        <div class="flex items-center">
                            <span class="text-xs text-green-600 font-medium">Confidence: ${Math.round(data.confidence * 100)}%</span>
                            <span class="text-xs text-gray-400">• Powered by AI Analysis</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600" data-action="close-marketing-modal">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Primary Content -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Enhanced Title & Description -->
                <div class="space-y-4">
                    <div class="bg-blue-50 rounded-lg">
                        <h4 class="font-semibold text-gray-800 flex items-center">
                            <span class="">🏷️</span> AI-Enhanced Title
                        </h4>
                        <div class="bg-white border border-blue-200 rounded-lg hover:bg-blue-50 cursor-pointer" data-action="apply-title" data-value="${data.title.replace(/\"/g, '&quot;').replace(/'/g, '&#39;')}">
                            <div class="font-medium text-gray-800">${data.title}</div>
                            <div class="text-xs text-blue-600">Click to apply to item name</div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg">
                        <h4 class="font-semibold text-gray-800 flex items-center">
                            <span class="">📝</span> AI-Crafted Description
                        </h4>
                        <div class="bg-white border border-green-200 rounded-lg hover:bg-green-50 cursor-pointer" data-action="apply-description" data-value="${data.description.replace(/\"/g, '&quot;').replace(/'/g, '&#39;')}">
                            <div class="text-gray-800 text-sm">${data.description}</div>
                            <div class="text-xs text-green-600">Click to apply to item description</div>
                        </div>
                    </div>
                </div>
                
                <!-- Target Audience & Keywords -->
                <div class="space-y-4">
                    <div class="bg-purple-50 rounded-lg">
                        <h4 class="font-semibold text-gray-800 flex items-center">
                            <span class="">🎯</span> Target Audience
                        </h4>
                        <p class="text-sm text-gray-700">${data.targetAudience}</p>
                    </div>
                    
                    <div class="bg-yellow-50 rounded-lg">
                        <h4 class="font-semibold text-gray-800 flex items-center">
                            <span class="">🔍</span> SEO Keywords
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            ${data.keywords.map(keyword => `
                                <span class="bg-yellow-100 text-yellow-800 text-xs rounded-full font-medium">${keyword}</span>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Marketing Intelligence Tabs -->
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button data-action="show-marketing-tab" data-tab="selling" class="marketing-tab-btn active border-b-2 border-purple-500 font-medium text-sm text-purple-600">
                        💰 Selling Points
                    </button>
                    <button data-action="show-marketing-tab" data-tab="competitive" class="marketing-tab-btn border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                        ⚡ Competitive Edge
                    </button>
                    <button data-action="show-marketing-tab" data-tab="conversion" class="marketing-tab-btn border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                        🎯 Conversion
                    </button>
                    <button data-action="show-marketing-tab" data-tab="channels" class="marketing-tab-btn border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                        📢 Channels
                    </button>
                </nav>
            </div>
            
            <!-- Tab Content -->
            <div id="marketing-tab-content">
                <!-- Selling Points Tab -->
                <div id="tab-selling" class="marketing-tab-content">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-green-50 rounded-lg">
                            <h5 class="font-semibold text-gray-800">💎 Key Selling Points</h5>
                            <ul class="text-sm text-gray-700 space-y-1">
                                ${data.marketingIntelligence.selling_points.map(point => `
                                    <li class="flex items-start"><span class="text-green-600">•</span>${point}</li>
                                `).join('')}
                            </ul>
                        </div>
                        <div class="bg-blue-50 rounded-lg">
                            <h5 class="font-semibold text-gray-800">🎭 Emotional Triggers</h5>
                            <div class="flex flex-wrap gap-2">
                                ${data.marketingIntelligence.emotional_triggers.map(trigger => `
                                    <span class="bg-blue-100 text-blue-800 text-xs rounded-full">${trigger}</span>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Competitive Edge Tab -->
                <div id="tab-competitive" class="marketing-tab-content hidden">
                    <div class="bg-red-50 rounded-lg">
                        <h5 class="font-semibold text-gray-800">🏆 Competitive Advantages</h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            ${data.marketingIntelligence.competitive_advantages.map(advantage => `
                                <div class="bg-white rounded-lg border border-red-200">
                                    <div class="font-medium text-gray-800 text-sm">${advantage}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                
                <!-- Conversion Tab -->
                <div id="tab-conversion" class="marketing-tab-content hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-orange-50 rounded-lg">
                            <h5 class="font-semibold text-gray-800">🎯 Call-to-Action Ideas</h5>
                            <ul class="text-sm text-gray-700 space-y-2">
                                ${data.marketingIntelligence.call_to_action_suggestions.map(cta => `
                                    <li class="bg-white rounded border border-orange-200 font-medium">"${cta}"</li>
                                `).join('')}
                            </ul>
                        </div>
                        <div class="bg-pink-50 rounded-lg">
                            <h5 class="font-semibold text-gray-800">⚡ Conversion Boosters</h5>
                            <div class="space-y-2 text-sm text-gray-700">
                                <div class="bg-white rounded border border-pink-200">
                                    <strong>Urgency:</strong> Limited time offer
                                </div>
                                <div class="bg-white rounded border border-pink-200">
                                    <strong>Social Proof:</strong> Customer testimonials
                                </div>
                                <div class="bg-white rounded border border-pink-200">
                                    <strong>Guarantee:</strong> Satisfaction promise
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Marketing Channels Tab -->
                <div id="tab-channels" class="marketing-tab-content hidden">
                    <div class="bg-indigo-50 rounded-lg">
                        <h5 class="font-semibold text-gray-800">📢 Recommended Marketing Channels</h5>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            ${data.marketingIntelligence.marketing_channels.map(channel => `
                                <div class="bg-white rounded-lg border border-indigo-200 text-center">
                                    <div class="font-medium text-gray-800 text-sm">${channel}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- AI Analysis Summary -->
            <div class="bg-gray-50 rounded-lg">
                <h4 class="font-semibold text-gray-800 flex items-center">
                    <span class="">🧠</span> AI Analysis Summary
                </h4>
                <p class="text-sm text-gray-700">${data.reasoning}</p>
            </div>
            
            <div class="flex justify-between items-center">
                <div class="text-xs text-gray-500">
                    Analysis saved to database • All suggestions are AI-generated recommendations
                </div>
                <button type="button" data-action="close-marketing-modal" class="bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-lg hover:from-purple-600 hover:to-pink-600 transition-all duration-200 font-medium">
                    Close Analysis
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    // Close on overlay click
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            // Directly remove the modal overlay; handled via data-action elsewhere
            overlay.remove();
        }
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('marketingIntelligenceModal');
            if (modal) modal.remove();
        }
    });
}

// closeMarketingIntelligenceModal migrated to delegated handler in js/admin-inventory.js

// showMarketingTab migrated to delegated handler in js/admin-inventory.js

// applyTitle migrated to delegated handler in js/admin-inventory.js

// applyDescription migrated to delegated handler in js/admin-inventory.js

function closeCostModal() {
    document.getElementById('costFormModal').classList.remove('show');
}

function deleteCostItem(id, type) {
    showDeleteCostDialog(id, type);
}

function showDeleteCostDialog(id, type) {
    const typeLabel = type.slice(0, -1); // Remove 's' from end (materials -> material)
    const modal = document.createElement('div');
    modal.className = 'delete-cost-modal-overlay';
    modal.innerHTML = `
        <div class="delete-cost-modal">
            <div class="delete-cost-header">
                <h3>🗑️ Delete ${typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1)} Cost</h3>
            </div>
            <div class="delete-cost-body">
                <p>Are you sure you want to remove this ${typeLabel} cost item?</p>
                <p class="delete-cost-note">This action cannot be undone and will update your cost calculations.</p>
            </div>
            <div class="delete-cost-actions">
                <button type="button" class="delete-cost-cancel">Keep It</button>
                <button type="button" class="delete-cost-confirm">Yes, Delete</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add event listeners
    modal.querySelector('.delete-cost-cancel').addEventListener('click', () => {
        document.body.removeChild(modal);
    });
    
    modal.querySelector('.delete-cost-confirm').addEventListener('click', () => {
        document.body.removeChild(modal);
        performCostItemDeletion(id, type);
    });
    
    // Close on overlay click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            document.body.removeChild(modal);
        }
    });
    
    // Close on escape key
    const escapeHandler = (e) => {
        if (e.key === 'Escape') {
            document.body.removeChild(modal);
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

function performCostItemDeletion(id, type) {
    const url = `/functions/process_cost_breakdown.php?id=${id}&costType=${type}&inventoryId=${currentItemSku}`;
    
    fetch(url, {
        method: 'DELETE',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess( data.message || 'Cost item deleted successfully');
            // Refresh the cost breakdown to update the display
            refreshCostBreakdown(false);
        } else {
            showError( data.error || 'Failed to delete cost item');
        }
    })
    .catch(error => {
        console.error('Error deleting cost item:', error);
        showError( 'Failed to delete cost item');
    });
}


document.addEventListener('DOMContentLoaded', function() {// Initialize navigation buttons for view/edit modes
    if (modalMode === 'view' || modalMode === 'edit') {
        updateNavigationButtons();
    }
    
    // Only check for cost breakdown elements if we're in a modal mode
    if (modalMode === 'edit' || modalMode === 'view' || modalMode === 'add') {
        // Test if the HTML elements exist
        console.log('materialsList element:', document.getElementById('materialsList'));
        console.log('laborList element:', document.getElementById('laborList'));
        console.log('energyList element:', document.getElementById('energyList'));
        console.log('equipmentList element:', document.getElementById('equipmentList'));
        
        if ((modalMode === 'edit' || modalMode === 'view') && currentItemSku) {
            console.log('Calling refreshCostBreakdown(false) to load data');
            refreshCostBreakdown(false);
            
            // Load existing price suggestion for edit mode
            if (modalMode === 'edit') {
                loadExistingPriceSuggestion(currentItemSku);
            }
            
            // Load existing price suggestion for view mode
            if (modalMode === 'view') {
                loadExistingViewPriceSuggestion(currentItemSku);
            }
            
            // Load existing marketing suggestion for edit/view mode
            loadExistingMarketingSuggestion(currentItemSku);
            

        } else if (modalMode === 'add') {
            console.log('Calling renderCostBreakdown(null) for add mode');
            renderCostBreakdown(null); 
        } else {}
    } else {}
    
    // Add event listener for delete cost buttons (using event delegation)
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('delete-cost-btn')) {
            const id = e.target.dataset.id;
            const type = e.target.dataset.type;
            deleteCostItem(id, type);
        }
    });
    
    const inventoryTable = document.getElementById('inventoryTable');
    if (inventoryTable) {
        inventoryTable.addEventListener('click', function(e) {
            const cell = e.target.closest('.editable');
            if (!cell || cell.querySelector('input, select')) return;

            const originalValue = cell.dataset.originalValue || cell.textContent.trim();
            const field = cell.dataset.field;
            const itemSku = cell.closest('tr').dataset.sku;
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
                
                (window.inventoryCategories || <?= json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?> || []).forEach(cat => {
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
                if (newValue !== originalValue) { 
                    saveInlineEdit(itemSku, field, newValue, cell, originalValue);
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

            fetch('/functions/process_inventory_update.php', { // API endpoint for processing
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
                        showSuccess( data.message);
                        
                        // Check if this is an add operation (modal mode is 'add')
                        const isAddOperation = window.location.search.includes('add=1');
                        
                        if (isAddOperation) {
                            // Mark item as just added for step badge display
                            if (data.sku && typeof markItemAsJustAdded === 'function') {
                                markItemAsJustAdded(data.sku);
                            }
                            
                            // For add operations, keep modal open and reset form for next item
                            if(saveBtn && btnText && spinner) {
                                btnText.classList.remove('hidden');
                                spinner.classList.add('hidden');
                                saveBtn.disabled = false;
                            }
                            
                            // Clear form fields except category (keep for convenience)
                            const form = document.getElementById('inventoryForm');
                            const fieldsToKeep = ['category', 'categoryEdit'];
                            const inputs = form.querySelectorAll('input, textarea, select');
                            inputs.forEach(input => {
                                if (!fieldsToKeep.includes(input.name) && !fieldsToKeep.includes(input.id)) {
                                    if (input.type === 'checkbox' || input.type === 'radio') {
                                        input.checked = false;
                                    } else {
                                        input.value = '';
                                    }
                                }
                            });
                            
                            // Generate new SKU for next item
                            const skuField = document.getElementById('sku');
                            if (skuField) {
                                // Extract the number from the current SKU and increment
                                const currentSku = data.sku || skuField.value;
                                const match = currentSku.match(/WF-GEN-(\d+)/);
                                if (match) {
                                    const nextNum = parseInt(match[1]) + 1;
                                    const nextSku = 'WF-GEN-' + String(nextNum).padStart(3, '0');
                                    skuField.value = nextSku;
                                }
                            }
                            
                            // Clear image preview
                            const imagePreview = document.querySelector('.image-preview');
                            if (imagePreview) {
                                imagePreview.style.display = 'none';
                            }
                            
                            // Focus on name field for next item
                            const nameField = document.getElementById('name');
                            if (nameField) {
                                nameField.focus();
                            }
                            
                            return; // Stay in modal
                        } else {
                            // For edit operations, redirect as before
                            let redirectUrl = '?page=admin&section=inventory';
                            if (data.sku) { // sku is returned by add/update operations
                                redirectUrl += '&highlight=' + data.sku;
                            }
                            // Use a short delay to allow toast to be seen before navigation
                            setTimeout(() => {
                                window.location.href = redirectUrl;
                            }, 500);
                            return;
                        } 

                } else { // data.success is false
                    showError( data.error || 'Failed to save item. Please check inputs.');
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
                showError( 'An unexpected error occurred: ' + error.message);
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
                        formData.append('sku', currentItemSku);
                
                const previewDiv = this.parentNode.querySelector('.image-preview');
                const previewImg = previewDiv ? previewDiv.querySelector('img') : null;

                fetch('/functions/process_image_upload.php', {
                    method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Images are now managed through the item_images table and carousel system
                        // Refresh the current images display to show the newly uploaded image
                        const currentSku = document.getElementById('skuEdit')?.value || document.getElementById('skuDisplay')?.value;
                        if (currentSku) {
                            loadCurrentImages(currentSku, modalMode === 'edit');
                        }
                        showSuccess( 'Image uploaded successfully.');
                    } else {
                        showError( data.error || 'Failed to upload image.');
                    }
                })
                .catch(error => { console.error('Error:', error); showError( 'Failed to upload image.'); });
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
    
    const confirmCostDeleteBtn = document.getElementById('confirmCostDeleteBtn');
    if (confirmCostDeleteBtn) {
        confirmCostDeleteBtn.addEventListener('click', confirmCostDeletion);
    }
    
    // Delete confirmation handled via delegated events in js/admin-inventory.js

    window.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const mainModal = document.getElementById('inventoryModalOuter');
            // Check if mainModal is actually displayed (not just present in DOM)
            if (mainModal && mainModal.offsetParent !== null) { 
                window.location.href = '?page=admin&section=inventory'; // Redirect to close
            } else if (document.getElementById('costFormModal')?.classList.contains('show')) {
                 closeCostModal();
            } else if (document.getElementById('deleteCostConfirmModal')?.classList.contains('show')) {
                 closeCostDeleteModal();
            } else if (document.getElementById('deleteConfirmModal')?.classList.contains('show')) {
                document.getElementById('deleteConfirmModal').classList.remove('show');
            }
        }
    });

    // Highlight row if specified in URL
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight');
    if (highlightId) {
        const rowToHighlight = document.querySelector(`tr[data-sku='${highlightId}']`);
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

    // Auto fetch SKU when category or attributes change (add mode only)
    const catSelect=document.getElementById('categoryEdit');
    const genderSelect=document.getElementById('genderEdit');
    const skuInput=document.getElementById('skuEdit');
    
    if(catSelect&&skuInput){
        // Function to generate enhanced SKU
        function generateEnhancedSKU() {
            const cat = catSelect.value;
            if (!cat) { 
                skuInput.value = ''; 
                return; 
            }
            
            const gender = genderSelect?.value || '';
            
            // Check if enhanced SKU generation is enabled (you can add UI toggle later)
            const useEnhanced = localStorage.getItem('useEnhancedSKU') === 'true' || false;
            
            let url = `/api/next_sku.php?cat=${encodeURIComponent(cat)}`;
            
            if (useEnhanced && gender) {
                url += `&enhanced=true&gender=${encodeURIComponent(gender)}`;
            }
            
            fetch(url)
                .then(r=>r.json())
                .then(d=>{ 
                    if(d.success){ 
                        skuInput.value = d.sku;
                        if (d.enhanced) {
                            showInfo(`🎯 Enhanced SKU generated: ${d.sku} (includes ${Object.values(d.attributes).filter(v => v).join(', ')})`);
                        }
                    }
                });
        }
        
        // Listen for changes on category and gender
        catSelect.addEventListener('change', generateEnhancedSKU);
        if (genderSelect) {
            genderSelect.addEventListener('change', generateEnhancedSKU);
        }
    }
    
    // Handle SKU regeneration when category changes manually
    async function handleCategoryChange() {
        const categoryField = document.getElementById('categoryEdit');
        const skuField = document.getElementById('skuEdit');
        
        if (categoryField && skuField) {
            const newCategory = categoryField.value;
            if (newCategory) {
                try {
                    const response = await fetch(`/api/next_sku.php?cat=${encodeURIComponent(newCategory)}`);
                    const result = await response.json();
                    if (result.success) {
                        skuField.value = result.sku;
                        showInfo( `SKU updated to ${result.sku} for category ${newCategory}`);
                    }
                } catch (error) {
                    console.error('Error generating new SKU:', error);
                }
            }
        }
    }

});

// Function to refresh category dropdown
function refreshCategoryDropdown() {
    return fetch('/api/get_categories.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the main category filter dropdown
                const filterCategorySelect = document.querySelector('select[name="category"]');
                if (filterCategorySelect) {
                    const currentValue = filterCategorySelect.value;
                    filterCategorySelect.innerHTML = '<option value="">All Categories</option>';
                    data.categories.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat;
                        option.textContent = cat;
                        if (cat === currentValue) option.selected = true;
                        filterCategorySelect.appendChild(option);
                    });
                }
                
                // Update the edit modal category dropdown
                const editCategorySelect = document.getElementById('categoryEdit');
                if (editCategorySelect) {
                    const currentValue = editCategorySelect.value;
                    editCategorySelect.innerHTML = '<option value="">Select Category</option>';
                    data.categories.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat;
                        option.textContent = cat;
                        if (cat === currentValue) option.selected = true;
                        editCategorySelect.appendChild(option);
                    });
                }
                
                // Update the global categories array used by inline editing
                window.inventoryCategories = data.categories;
                
                return data.categories;
            } else {
                console.error('Failed to refresh categories:', data.error);
                return [];
            }
        })
        .catch(error => {
            console.error('Error refreshing categories:', error);
            return [];
        });
}

// Make the function globally available
window.refreshCategoryDropdown = refreshCategoryDropdown;

// Listen for category updates from other tabs/windows
window.addEventListener('storage', function(e) {
    if (e.key === 'categoriesUpdated') {
        // Categories were updated in another tab, refresh our dropdown
        refreshCategoryDropdown().then(() => {
            showInfo( 'Categories updated! Dropdown refreshed.');
        });
    }
});

// The module binds to #multiImageUpload and manages validation, progress, upload, and refresh.

// AI image processing is handled by the Vite module via data-action="process-images-ai".

// Image list loading and rendering is handled by the Vite module.

function loadThumbnailImage(sku, container) {
    if (!sku || !container) {
        console.log('loadThumbnailImage: Missing sku or container', { sku, container });
        return;
    }
    
    console.log('Loading thumbnail for SKU:', sku);
    
    fetch(`/api/get_item_images.php?sku=${encodeURIComponent(sku)}`)
    .then(response => {
        console.log('API Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('API Response data for', sku, ':', data);
        
        if (data.success && data.images && data.images.length > 0) {
            // Find primary image or use first image
            const primaryImage = data.images.find(img => img.is_primary) || data.images[0];
            console.log('Using primary image:', primaryImage);
            
            // Try WebP first, fallback to PNG
            const webpPath = primaryImage.image_path.replace(/\.(png|jpg|jpeg)$/i, '.webp');
            const originalPath = primaryImage.image_path;
            
            console.log('Trying WebP path:', webpPath);
            console.log('Fallback PNG path:', originalPath);
            
            // Create thumbnail image with programmatic error handling (no inline events)
            const img = document.createElement('img');
            img.src = webpPath;
            img.alt = 'thumb';
            const handlePngFallback = () => {
                console.log('PNG also failed');
                container.innerHTML = '<div class="u-width-40px u-height-40px u-background-f0f0f0 u-border-radius-6px u-display-flex u-align-items-center u-justify-content-center u-font-size-12px u-color-999">No img</div>';
            };
            function handleWebpError() {
                console.log('WebP failed, trying PNG');
                img.removeEventListener('error', handleWebpError);
                img.addEventListener('error', handlePngFallback);
                img.src = originalPath;
            }
            img.addEventListener('error', handleWebpError);
            container.innerHTML = '';
            container.appendChild(img);
        } else {
            console.log('No images found for', sku);
            container.innerHTML = '<div >No img</div>';
        }
    })
    .catch(error => {
        console.error('Error loading thumbnail for', sku, ':', error);
        container.innerHTML = '<div >Error</div>';
    });
}

// Load thumbnails for inventory list
document.addEventListener('DOMContentLoaded', function() {
    const thumbnailContainers = document.querySelectorAll('.thumbnail-container');
    thumbnailContainers.forEach((container, index) => {
        const sku = container.dataset.sku;
        if (sku) {
            // Stagger the requests to avoid overwhelming the server
            setTimeout(() => {
                loadThumbnailImage(sku, container);
            }, index * 50); // 50ms delay between each request
        }
    });

    // ==================== INLINE EDITING FUNCTIONALITY ====================
    // Add inline editing functionality for inventory table
    document.querySelectorAll('.editable').forEach(function(cell) {
        cell.addEventListener('click', async function() {
            if (this.querySelector('input, select')) return; // Already editing
            
            const field = this.dataset.field;
            const currentText = this.textContent.trim();
            const row = this.closest('tr');
            const sku = row.dataset.sku || row.querySelector('a[href*="view="]')?.href.match(/view=([^&]+)/)?.[1];
            
            if (!sku) {
                console.error('Could not find item SKU');
                return;
            }
            
            let currentValue = currentText;
            // Remove currency formatting for price fields
            if (field === 'costPrice' || field === 'retailPrice') {
                currentValue = currentText.replace('$', '').replace(',', '');
            }
            
            let inputElement;
            
            // Create appropriate input element
            if (field === 'category') {
                inputElement = document.createElement('select');
                inputElement.innerHTML = '<option value="">Select Category</option>';
                
                // Add categories from global array
                (window.inventoryCategories || []).forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat;
                    if (cat === currentValue) option.selected = true;
                    inputElement.appendChild(option);
                });
            } else if (field === 'name') {
                inputElement = document.createElement('input');
                inputElement.type = 'text';
                inputElement.value = currentValue;
            } else {
                inputElement = document.createElement('input');
                inputElement.type = 'number';
                inputElement.value = currentValue;
                inputElement.min = '0';
                if (field === 'costPrice' || field === 'retailPrice') {
                    inputElement.step = '0.01';
                }
            }
            
            inputElement.className = 'inline-edit-input';
            inputElement.style.width = '100%';
            inputElement.style.padding = '4px 6px';
            inputElement.style.border = '1px solid #4299e1';
            inputElement.style.borderRadius = '4px';
            inputElement.style.fontSize = 'inherit';
            inputElement.style.fontFamily = 'inherit';
            inputElement.style.backgroundColor = 'white';
            inputElement.style.boxSizing = 'border-box';
            inputElement.style.margin = '0';
            inputElement.style.minWidth = '0';
            
            // Save function
            const saveValue = async () => {
                const newValue = inputElement.value.trim();
                
                if (newValue === currentValue) {
                    // No change, just restore original
                    this.textContent = currentText;
                    return;
                }
                
                if (!newValue) {
                    showError( 'Value cannot be empty');
                    return;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('sku', sku);
                    formData.append('field', field);
                    formData.append('value', newValue);
                    
                    const response = await fetch('api/update-inventory-field.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    let result;
                    try {
                        result = await response.json();
                    } catch (e) {
                        throw new Error('Invalid JSON response from server');
                    }
                    
                    if (result.success) {
                        showSuccess( result.message || 'Updated successfully');
                        
                        // Update display with proper formatting
                        if (field === 'costPrice' || field === 'retailPrice') {
                            this.textContent = '$' + parseFloat(newValue).toFixed(2);
                        } else {
                            this.textContent = newValue;
                        }
                    } else {
                        showError( result.error || 'Update failed');
                        this.textContent = currentText; // Restore original
                    }
                } catch (error) {
                    console.error('Update error:', error);
                    showError( 'Failed to update: ' + error.message);
                    this.textContent = currentText; // Restore original
                }
            };
            
            // Cancel function
            const cancelEdit = () => {
                this.textContent = currentText;
            };
            
            // Set up input element
            this.textContent = '';
            this.appendChild(inputElement);
            inputElement.focus();
            inputElement.select();
            
            // Handle save/cancel events
            inputElement.addEventListener('blur', saveValue);
            inputElement.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    inputElement.blur(); // Trigger save
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cancelEdit();
                }
            });
        });
        
        // Add hover effect for editable cells
        cell.style.cursor = 'pointer';
        cell.title = 'Click to edit';
        
        cell.addEventListener('mouseenter', function() {
            if (!this.querySelector('input')) {
                this.style.backgroundColor = '#f0fdf4';
                this.style.outline = '1px solid #16a34a';
            }
        });
        
        cell.addEventListener('mouseleave', function() {
            if (!this.querySelector('input')) {
                this.style.backgroundColor = '';
                this.style.outline = '';
            }
        });
    });
});

// Function to normalize carousel image heights
function normalizeCarouselImageHeights(trackId) {
    const track = document.getElementById(trackId);
    if (!track) return;
    
    const imageContainers = track.querySelectorAll('.carousel-image-container');
    const images = track.querySelectorAll('.carousel-image');
    
    if (images.length === 0) return;
    
    // Wait for all images to load
    let loadedCount = 0;
    const totalImages = images.length;
    
    const checkAllLoaded = () => {
        loadedCount++;
        if (loadedCount === totalImages) {
            // All images loaded, now find the tallest
            let maxHeight = 0;
            
            images.forEach(img => {
                if (img.complete && img.naturalHeight > 0) {
                    // Calculate the height this image would have at the container width
                    const containerWidth = img.parentElement.offsetWidth;
                    const aspectRatio = img.naturalWidth / img.naturalHeight;
                    const scaledHeight = containerWidth / aspectRatio;
                    maxHeight = Math.max(maxHeight, scaledHeight);
                }
            });
            
            // Set minimum height to ensure reasonable size
            maxHeight = Math.max(maxHeight, 200);
            maxHeight = Math.min(maxHeight, 400); // Cap at 400px// Apply the height to all image containers
            imageContainers.forEach(container => {
                container.style.height = maxHeight + 'px';
            });
        }
    };
    
    // Add load listeners to all images
    images.forEach(img => {
        if (img.complete) {
            checkAllLoaded();
        } else {
            img.addEventListener('load', checkAllLoaded);
            img.addEventListener('error', checkAllLoaded); // Count errors as "loaded" too
        }
    });
}

// Function to save inline edits
function saveInlineEdit(itemSku, field, newValue, cell, originalValue) {
    const formData = new FormData();
    formData.append('sku', itemSku);
    formData.append('field', field);
    formData.append('value', newValue);
    
    fetch('/functions/process_inventory_update.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cell with formatted value
            if (field === 'costPrice' || field === 'retailPrice') {
                cell.innerHTML = '$' + parseFloat(newValue).toFixed(2);
            } else {
                cell.innerHTML = newValue;
            }
            showSuccess( data.message);
        } else {
            cell.innerHTML = originalValue; // Restore original value
            showError( data.error || 'Failed to update field');
        }
    })
    .catch(error => {
        console.error('Error updating field:', error);
        cell.innerHTML = originalValue; // Restore original value
        showError( 'Failed to update field: ' + error.message);
    });
}

// Carousel navigation function
function moveCarousel(type, direction) {
    const trackId = type === 'edit' ? 'editCarouselTrack' : 'viewCarouselTrack';
    const positionVar = type === 'edit' ? 'editCarouselPosition' : 'viewCarouselPosition';
    
    const track = document.getElementById(trackId);
    if (!track) {return;
    }
    
    const slides = track.querySelectorAll('.carousel-slide');
    const totalSlides = slides.length;
    const slidesToShow = 3; // Show 3 images at a time// Only allow navigation if there are more than 3 images
    if (totalSlides <= slidesToShow) {return;
    }
    
    const maxPosition = Math.max(0, totalSlides - slidesToShow);
    
    // Update position
    let currentPosition = window[positionVar] || 0;
    currentPosition += direction;
    
    // Clamp position
    if (currentPosition < 0) currentPosition = 0;
    if (currentPosition > maxPosition) currentPosition = maxPosition;
    
    // Store position
    window[positionVar] = currentPosition;
    
    // Apply transform - move by one slide width including margin
    // Each slide is 155px + 15px margin = 170px per slide
    const translateX = -(currentPosition * 170);
    track.style.transform = `translateX(${translateX}px)`;// Update button visibility
    updateCarouselNavigation(type, totalSlides);
}

function updateCarouselNavigation(type, totalSlides) {
    const trackId = type === 'edit' ? 'editCarouselTrack' : 'viewCarouselTrack';
    const positionVar = type === 'edit' ? 'editCarouselPosition' : 'viewCarouselPosition';
    
    const track = document.getElementById(trackId);
    if (!track) {return;
    }
    
    const container = track.closest('.image-carousel-container');
    const prevBtn = container.querySelector('.carousel-prev');
    const nextBtn = container.querySelector('.carousel-next');
    
    const slidesToShow = 3;
    const currentPosition = window[positionVar] || 0;
    const maxPosition = Math.max(0, totalSlides - slidesToShow);if (prevBtn) {
        prevBtn.style.display = currentPosition === 0 ? 'none' : 'block';}
    if (nextBtn) {
        nextBtn.style.display = currentPosition >= maxPosition ? 'none' : 'block';}
}

// Cost breakdown template functions migrated to Vite module (src/js/admin-inventory.js); inline toggle/list removed.

// Global change tracking system for marketing manager
let originalMarketingData = {};
let hasMarketingChanges = false;
let hasTitleChanges = false;
let hasDescriptionChanges = false;

// Initialize change tracking
function initializeMarketingChangeTracking() {
    originalMarketingData = {};
    hasMarketingChanges = false;
    hasTitleChanges = false;
    hasDescriptionChanges = false;
    updateMarketingSaveButtonVisibility();
}

// Track changes in marketing fields
function trackMarketingFieldChange(fieldId, value = null) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    const currentValue = value !== null ? value : field.value;
    const originalValue = originalMarketingData[fieldId] || '';
    
    // Check if value has changed from original
    const hasChanged = currentValue !== originalValue;
    
    // Update specific field change states
    if (fieldId === 'marketingTitle') {
        hasTitleChanges = hasChanged;} else if (fieldId === 'marketingDescription') {
        hasDescriptionChanges = hasChanged;}
    
    // Update global change state
    if (hasChanged && !hasMarketingChanges) {
        hasMarketingChanges = true;
        updateMarketingSaveButtonVisibility();
    } else if (!hasChanged) {
        // Check if any other fields have changes
        checkAllMarketingFieldsForChanges();
    }
}

// Check all tracked fields for changes
function checkAllMarketingFieldsForChanges() {
    const trackedFields = [
        'marketingTitle', 'marketingDescription', 'targetAudience', 'demographics',
        'psychographics', 'brandVoice', 'contentTone', 'searchIntent', 'seasonalRelevance'
    ];
    
    let anyChanges = false;
    trackedFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            const currentValue = field.value;
            const originalValue = originalMarketingData[fieldId] || '';
            const hasChanged = currentValue !== originalValue;
            
            // Update specific field change states
            if (fieldId === 'marketingTitle') {
                hasTitleChanges = hasChanged;
            } else if (fieldId === 'marketingDescription') {
                hasDescriptionChanges = hasChanged;
            }
            
            if (hasChanged) {
                anyChanges = true;
            }
        }
    });
    
    hasMarketingChanges = anyChanges;
    updateMarketingSaveButtonVisibility();
}

// Update save button visibility based on changes
function updateMarketingSaveButtonVisibility() {// Title save button
    const titleSaveButton = document.querySelector('[data-action="apply-and-save-marketing-title"]')
    if (titleSaveButton) {
        if (hasTitleChanges) {
            titleSaveButton.style.display = '';
            titleSaveButton.classList.add('animate-pulse');
        } else {
            titleSaveButton.style.display = 'none';
            titleSaveButton.classList.remove('animate-pulse');
        }
    }
    
    // Description save button
    const descriptionSaveButton = document.querySelector('[data-action="apply-and-save-marketing-description"]');
    if (descriptionSaveButton) {
        if (hasDescriptionChanges) {
            descriptionSaveButton.style.display = '';
            descriptionSaveButton.classList.add('animate-pulse');
        } else {
            descriptionSaveButton.style.display = 'none';
            descriptionSaveButton.classList.remove('animate-pulse');
        }
    }
    
    // Other marketing save buttons (for other tabs)
    const otherSaveButtons = document.querySelectorAll([
        '[onclick*="saveMarketingField"]',
        '[onclick*="saveMarketingFields"]'
    ].join(','));
    
    otherSaveButtons.forEach(button => {
        if (hasMarketingChanges) {
            button.style.display = '';
            button.classList.add('animate-pulse');
        } else {
            button.style.display = 'none';
            button.classList.remove('animate-pulse');
        }
    });
    
    // Add visual indicator for unsaved changes
    const modal = document.getElementById('marketingManagerModal');
    if (modal) {
        const header = modal.querySelector('.bg-gradient-to-r');
        if (header) {
            if (hasMarketingChanges) {
                header.classList.add('from-orange-600', 'to-orange-700');
                header.classList.remove('from-purple-600', 'to-purple-700');
            } else {
                header.classList.remove('from-orange-600', 'to-orange-700');
                header.classList.add('from-purple-600', 'to-purple-700');
            }
        }
    }
}

// Store original form data when loading
function storeOriginalMarketingData() {
    const trackedFields = [
        'marketingTitle', 'marketingDescription', 'targetAudience', 'demographics',
        'psychographics', 'brandVoice', 'contentTone', 'searchIntent', 'seasonalRelevance'
    ];
    
    trackedFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            originalMarketingData[fieldId] = field.value;
        }
    });
    
    hasMarketingChanges = false;
    hasTitleChanges = false;
    hasDescriptionChanges = false;
    updateMarketingSaveButtonVisibility();
}

// Add event listeners to form fields
function addMarketingChangeListeners() {
    const trackedFields = [
        'marketingTitle', 'marketingDescription', 'targetAudience', 'demographics',
        'psychographics', 'brandVoice', 'contentTone', 'searchIntent', 'seasonalRelevance'
    ];
    
    trackedFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', () => {
                trackMarketingFieldChange(fieldId);
                checkForTitleDescriptionChanges(fieldId);
            });
            field.addEventListener('change', () => {
                trackMarketingFieldChange(fieldId);
                checkForTitleDescriptionChanges(fieldId);
            });
        }
    });
}

    // Check if title or description differ from current item data and show/hide save buttons
function checkForTitleDescriptionChanges(fieldId) {
    if (fieldId === 'marketingTitle') {
        const titleField = document.getElementById('marketingTitle');
        const saveButton = titleField?.parentElement?.querySelector('button[data-action="apply-and-save-marketing-title"]');
        const nameField = document.getElementById('name');
        
        if (titleField && saveButton && nameField) {
            const currentTitle = titleField.value.trim();
            const itemTitle = nameField.value || '';
        
            if (currentTitle && currentTitle !== itemTitle) {
                saveButton.style.display = 'inline-block';
            } else {
                saveButton.style.display = 'none';
            }
        }
    }
    
    if (fieldId === 'marketingDescription') {
        const descField = document.getElementById('marketingDescription');
        const saveButton = descField?.parentElement?.querySelector('button[data-action="apply-and-save-marketing-description"]');
        const itemDescField = document.getElementById('description');
        
        if (descField && saveButton && itemDescField) {
            const currentDesc = descField.value.trim();
            const itemDesc = itemDescField.value || '';
        
            if (currentDesc && currentDesc !== itemDesc) {
                saveButton.style.display = 'inline-block';
            } else {
                saveButton.style.display = 'none';
            }
        }
    }
}

// Apply and save marketing title to item
function applyAndSaveMarketingTitle() {
    const titleField = document.getElementById('marketingTitle');
    if (!titleField || !currentItemSku) return;
    
    const newTitle = titleField.value.trim();
    if (!newTitle) return;
    
    // Update the item name
    updateInventoryField(currentItemSku, 'name', newTitle, 'Item title updated from Marketing Manager');
    
    // Hide the save button
    const saveButton = titleField.parentElement.querySelector('button[data-action="apply-and-save-marketing-title"]');
    if (saveButton) saveButton.style.display = 'none';
    
    showSuccess( '✅ Item title updated successfully');
}

// Apply and save marketing description to item
function applyAndSaveMarketingDescription() {
    const descField = document.getElementById('marketingDescription');
    if (!descField || !currentItemSku) return;
    
    const newDesc = descField.value.trim();
    if (!newDesc) return;
    
    // Update the item description
    updateInventoryField(currentItemSku, 'description', newDesc, 'Item description updated from Marketing Manager');
    
    // Hide the save button
    const saveButton = descField.parentElement.querySelector('button[data-action="apply-and-save-marketing-description"]');
    if (saveButton) saveButton.style.display = 'none';
    
    showSuccess( '✅ Item description updated successfully');
}

// Reset change tracking after successful save
function resetMarketingChangeTracking() {
    storeOriginalMarketingData();
}

// Marketing Manager Functions

// Enhanced modal visibility functions (handled by Vite module in src/js/admin-inventory.js)

// openMarketingManager handled by AdminInventoryModule in src/js/admin-inventory.js

// closeMarketingManager handled by AdminInventoryModule in src/js/admin-inventory.js

function applyMarketingToItem() {// Get the marketing title and description fields
    const marketingTitle = document.getElementById('marketingTitle');
    const marketingDescription = document.getElementById('marketingDescription');
    
    // Get the main item title and description fields
    const itemNameField = document.getElementById('name');
    const itemDescriptionField = document.getElementById('description');
    
    let appliedChanges = 0;
    
    // Apply marketing title to item name if both exist and have content
    if (marketingTitle && marketingTitle.value.trim() && itemNameField) {
        const newTitle = marketingTitle.value.trim();
        itemNameField.value = newTitle;
        
        // Add temporary highlight to show the change
        itemNameField.style.backgroundColor = '#dcfce7';
        itemNameField.style.border = '2px solid #22c55e';
        
        appliedChanges++;}
    
    // Apply marketing description to item description if both exist and have content
    if (marketingDescription && marketingDescription.value.trim() && itemDescriptionField) {
        const newDescription = marketingDescription.value.trim();
        itemDescriptionField.value = newDescription;
        
        // Add temporary highlight to show the change
        itemDescriptionField.style.backgroundColor = '#dcfce7';
        itemDescriptionField.style.border = '2px solid #22c55e';
        
        appliedChanges++;}
    
    if (appliedChanges > 0) {
        showSuccess(`✅ Applied ${appliedChanges} marketing ${appliedChanges === 1 ? 'field' : 'fields'} to item successfully!`);
        
        // Remove highlights after 3 seconds
        setTimeout(() => {
            if (itemNameField) {
                itemNameField.style.backgroundColor = '';
                itemNameField.style.border = '';
            }
            if (itemDescriptionField) {
                itemDescriptionField.style.backgroundColor = '';
                itemDescriptionField.style.border = '';
            }
        }, 3000);
        
        // Close the Marketing Manager modal
        closeMarketingManager();
    } else {
        showWarning('⚠️ No marketing content found to apply. Please enter a title or description first.');
    }
}




function showMarketingManagerTab(tabName) {
    // Update tab buttons - remove active class from all tabs
    document.querySelectorAll('.css-category-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Apply active class to selected tab
    const activeTab = document.getElementById(tabName + 'Tab');
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    // Load tab content
    loadMarketingTabContent(tabName);
}

function loadMarketingData() {const contentDiv = document.getElementById('marketingManagerContent');if (contentDiv) {
        contentDiv.innerHTML = '<div class="modal-loading">' +
            '<div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>' +
            '<p class="text-gray-600">Loading marketing data...</p>' +
        '</div>';
        
        // Force content div to be visible with clean styling
        contentDiv.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important;';}
    
    // Load content tab by default
    showMarketingManagerTab('content');
}

function loadMarketingTabContent(tabName) {
    const contentDiv = document.getElementById('marketingManagerContent');
    
    switch(tabName) {
        case 'content':
            loadContentTab(contentDiv);
            break;
        case 'audience':
            loadAudienceTab(contentDiv);
            break;
        case 'selling':
            loadSellingTab(contentDiv);
            break;
        case 'seo':
            loadSEOTab(contentDiv);
            break;
        case 'conversion':
            loadConversionTab(contentDiv);
            break;
    }
}

function loadContentTab(contentDiv) {
    contentDiv.innerHTML = '<div class="space-y-6">' +
        '<div class="bg-purple-200 rounded-lg">' +
            '<div class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-end">' +
                '<div>' +
                    '<label class="block text-xs text-white">Brand Voice</label>' +
                    '<select id="brandVoice" class="w-full border border-purple-200 rounded bg-gray-50 text-sm" data-action="marketing-default-change" data-setting="brand_voice">' +
                        '<option value="">Select voice...</option>' +
                        '<option value="friendly">Friendly</option>' +
                        '<option value="professional">Professional</option>' +
                        '<option value="playful">Playful</option>' +
                        '<option value="luxurious">Luxurious</option>' +
                        '<option value="casual">Casual</option>' +
                    '</select>' +
                '</div>' +
                '<div>' +
                    '<label class="block text-xs text-white">Content Tone</label>' +
                    '<select id="contentTone" class="w-full border border-purple-200 rounded bg-gray-50 text-sm" data-action="marketing-default-change" data-setting="content_tone">' +
                        '<option value="">Select tone...</option>' +
                        '<option value="informative">Informative</option>' +
                        '<option value="persuasive">Persuasive</option>' +
                        '<option value="emotional">Emotional</option>' +
                        '<option value="urgent">Urgent</option>' +
                        '<option value="conversational">Conversational</option>' +
                    '</select>' +
                '</div>' +
                '<div>' +
                    '<button data-action="generate-all-marketing" class="w-full bg-purple-600 hover:bg-purple-700 text-white rounded text-xs font-medium">' +
                        '🧠 Generate AI' +
                    '</button>' +
                '</div>' +
                '<div>' +
                    '<button data-action="generate-fresh-marketing" class="w-full bg-orange-600 hover:bg-orange-700 text-white rounded text-xs font-medium">' +
                        '🔥 Fresh Start' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<div class="text-center">' +
                '<p class="text-xs text-white">💡 Global settings • AI generates content for all tabs based on voice & tone</p>' +
            '</div>' +
        '</div>' +
        '<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">' +
            '<div class="bg-blue-50 rounded-lg">' +
                '<label class="block text-sm font-medium text-gray-800">📝 Item Title</label>' +
                                  '<textarea id="marketingTitle" class="w-full border border-blue-300 rounded-lg text-sm resize-none" rows="2" placeholder="Enter enhanced item title..."></textarea>' +
                '<div class="flex justify-center">' +
                    '<button data-action="apply-and-save-marketing-title" class="bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-medium">' +
                        '📝 Apply & Save' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<div class="bg-green-50 rounded-lg">' +
                '<label class="block text-sm font-medium text-gray-800">📄 Item Description</label>' +
                                  '<textarea id="marketingDescription" class="w-full border border-green-300 rounded-lg text-sm resize-none" rows="4" placeholder="Enter detailed item description..."></textarea>' +
                '<div class="flex justify-center">' +
                    '<button data-action="apply-and-save-marketing-description" class="bg-green-600 hover:bg-green-700 text-white rounded text-xs font-medium">' +
                        '📝 Apply & Save' +
                    '</button>' +
                '</div>' +
            '</div>' +
        '</div>' +
    '</div>';
    
    // Load existing data and set up change tracking
    loadExistingMarketingData().then(() => {
        setTimeout(() => {
            storeOriginalMarketingData();
            addMarketingChangeListeners();
            // Load global marketing defaults
            loadGlobalMarketingDefaults();
            // Load primary image after tab content is rendered
            loadMarketingItemImage();
        }, 200);
    });
}

function loadAudienceTab(contentDiv) {
    contentDiv.innerHTML = '<div class="space-y-6">' +
        '<h3 class="text-lg font-semibold text-gray-800">Target Audience Management</h3>' +
        '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">' +
            '<div class="bg-orange-50 rounded-lg">' +
            '<label class="block text-sm font-medium text-gray-700">Primary Target Audience</label>' +
            '<textarea id="targetAudience" class="w-full border border-orange-200 rounded-lg" rows="3" placeholder="Describe your ideal customer..."></textarea>' +
            '<button data-action="save-marketing-field" data-field="target_audience" class="text-orange-600 hover:text-orange-800 text-sm">Save</button>' +
        '</div>' +
        '<div class="bg-pink-50 rounded-lg">' +
            '<label class="block text-sm font-medium text-gray-700">Demographics</label>' +
            '<textarea id="demographics" class="w-full border border-pink-200 rounded-lg" rows="3" placeholder="Age, gender, income, location..."></textarea>' +
            '<button data-action="save-marketing-field" data-field="demographic_targeting" class="text-pink-600 hover:text-pink-800 text-sm">Save</button>' +
        '</div>' +
        '</div>' +
        '<div class="bg-indigo-50 rounded-lg">' +
            '<label class="block text-sm font-medium text-gray-700">Psychographic Profile</label>' +
            '<textarea id="psychographics" class="w-full border border-indigo-200 rounded-lg" rows="3" placeholder="Interests, values, lifestyle, personality traits..."></textarea>' +
            '<button data-action="save-marketing-field" data-field="psychographic_profile" class="text-indigo-600 hover:text-indigo-800 text-sm">Save</button>' +
        '</div>' +
    '</div>';
    
    loadExistingMarketingData().then(() => {
        setTimeout(() => {
            storeOriginalMarketingData();
            addMarketingChangeListeners();
            // Load primary image after tab content is rendered
            loadMarketingItemImage();
        }, 200);
    });
}

function loadSellingTab(contentDiv) {
    contentDiv.innerHTML = `
        <div class="max-h-[60vh] overflow-y-auto">
            <div class="space-y-6">
                <h3 class="text-lg font-semibold text-gray-800">Selling Points & Advantages</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-green-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <label class="block text-sm font-medium text-gray-700">Key Selling Points</label>
                            <button data-action="add-list-item" data-field="selling_points" class="text-green-600 hover:text-green-800 text-sm">+ Add</button>
                        </div>
                        <div id="sellingPointsList" class="space-y-2 max-h-40 overflow-y-auto">
                            <!-- Dynamic content -->
                        </div>
                        <input type="text" id="newSellingPoint" placeholder="Enter new selling point..." class="w-full border border-green-200 rounded" data-add-enter-field="selling_points">
                    </div>
                    
                    <div class="bg-red-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <label class="block text-sm font-medium text-gray-700">Competitive Advantages</label>
                            <button data-action="add-list-item" data-field="competitive_advantages" class="text-red-600 hover:text-red-800 text-sm">+ Add</button>
                        </div>
                        <div id="competitiveAdvantagesList" class="space-y-2 max-h-40 overflow-y-auto">
                            <!-- Dynamic content -->
                        </div>
                        <input type="text" id="newCompetitiveAdvantage" placeholder="What makes you better..." class="w-full border border-red-200 rounded" data-add-enter-field="competitive_advantages">
                    </div>
                </div>
                
                <div class="bg-yellow-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <label class="block text-sm font-medium text-gray-700">Customer Benefits</label>
                        <button data-action="add-list-item" data-field="customer_benefits" class="text-yellow-600 hover:text-yellow-800 text-sm">+ Add</button>
                    </div>
                    <div id="customerBenefitsList" class="space-y-2 max-h-40 overflow-y-auto">
                        <!-- Dynamic content -->
                    </div>
                    <input type="text" id="newCustomerBenefit" placeholder="What benefit does customer get..." class="w-full border border-yellow-200 rounded" data-add-enter-field="customer_benefits">
                </div>
            </div>
        </div>
    `;
    
    loadExistingMarketingData();
}

function loadSEOTab(contentDiv) {
    contentDiv.innerHTML = `
        <div class="max-h-[60vh] overflow-y-auto">
            <div class="space-y-6">
                <h3 class="text-lg font-semibold text-gray-800">SEO & Keywords</h3>
                
                <div class="bg-blue-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <label class="block text-sm font-medium text-gray-700">SEO Keywords</label>
                        <button data-action="add-list-item" data-field="seo_keywords" class="text-blue-600 hover:text-blue-800 text-sm">+ Add</button>
                    </div>
                    <div id="seoKeywordsList" class="space-y-2 max-h-40 overflow-y-auto">
                        <!-- Dynamic content -->
                    </div>
                    <input type="text" id="newSEOKeyword" placeholder="Enter keyword or phrase..." class="w-full border border-blue-200 rounded" data-add-enter-field="seo_keywords">
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-purple-50 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700">Search Intent</label>
                        <select id="searchIntent" class="w-full border border-purple-200 rounded">
                            <option value="">Select intent...</option>
                            <option value="informational">Informational</option>
                            <option value="navigational">Navigational</option>
                            <option value="transactional">Transactional</option>
                            <option value="commercial">Commercial Investigation</option>
                        </select>
                        <button data-action="save-marketing-field" data-field="search_intent" class="text-purple-600 hover:text-purple-800 text-sm">Save</button>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700">Seasonal Relevance</label>
                        <textarea id="seasonalRelevance" class="w-full border border-green-200 rounded-lg" rows="3" placeholder="Christmas, summer, back-to-school, etc..."></textarea>
                        <button data-action="save-marketing-field" data-field="seasonal_relevance" class="text-green-600 hover:text-green-800 text-sm">Save</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    loadExistingMarketingData().then(() => {
        setTimeout(() => {
            storeOriginalMarketingData();
            addMarketingChangeListeners();
        }, 200);
    });
}

function loadConversionTab(contentDiv) {
    contentDiv.innerHTML = `
        <div class="max-h-[60vh] overflow-y-auto">
            <div class="space-y-6">
                <h3 class="text-lg font-semibold text-gray-800">Conversion Optimization</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-orange-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <label class="block text-sm font-medium text-gray-700">Call-to-Action Suggestions</label>
                            <button data-action="add-list-item" data-field="call_to_action_suggestions" class="text-orange-600 hover:text-orange-800 text-sm">+ Add</button>
                        </div>
                        <div id="callToActionsList" class="space-y-2 max-h-40 overflow-y-auto">
                            <!-- Dynamic content -->
                        </div>
                        <input type="text" id="newCallToAction" placeholder="Get Yours Today, Buy Now, etc..." class="w-full border border-orange-200 rounded" data-add-enter-field="call_to_action_suggestions">
                    </div>
                    
                    <div class="bg-red-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <label class="block text-sm font-medium text-gray-700">Urgency Factors</label>
                            <button data-action="add-list-item" data-field="urgency_factors" class="text-red-600 hover:text-red-800 text-sm">+ Add</button>
                        </div>
                        <div id="urgencyFactorsList" class="space-y-2 max-h-40 overflow-y-auto">
                            <!-- Dynamic content -->
                        </div>
                        <input type="text" id="newUrgencyFactor" placeholder="Limited time, while supplies last..." class="w-full border border-red-200 rounded" data-add-enter-field="urgency_factors">
                    </div>
                </div>
                
                <div class="bg-purple-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <label class="block text-sm font-medium text-gray-700">Conversion Triggers</label>
                        <button data-action="add-list-item" data-field="conversion_triggers" class="text-purple-600 hover:text-purple-800 text-sm">+ Add</button>
                    </div>
                    <div id="conversionTriggersList" class="space-y-2 max-h-40 overflow-y-auto">
                        <!-- Dynamic content -->
                    </div>
                    <input type="text" id="newConversionTrigger" placeholder="Free shipping, money-back guarantee..." class="w-full border border-purple-200 rounded" data-add-enter-field="conversion_triggers">
                </div>
            </div>
        </div>
    `;
    
    loadExistingMarketingData();
}

function loadExistingMarketingData() {
    if (!currentItemSku) {return Promise.resolve();
    }return fetch(`/api/marketing_manager.php?action=get_marketing_data&sku=${currentItemSku}&_t=${Date.now()}`)
    .then(response => response.json())
    .then(data => {if (data.success && data.data) {populateMarketingFields(data.data);
        } else {clearMarketingFields(); // Clear any cached/previous content
        }
        return data;
    })
    .catch(error => {
        console.error('Marketing Manager: Error loading data for SKU', currentItemSku, ':', error);
        clearMarketingFields(); // Clear any cached content on error
        throw error;
    });
}

function loadMarketingItemImage() {
    if (!currentItemSku) {return;
    }fetch(`/api/get_item_images.php?sku=${encodeURIComponent(currentItemSku)}`)
    .then(response => response.json())
    .then(data => {
        const headerContainer = document.getElementById('marketingItemImageHeader');
        
        if (!headerContainer) {return;
        }
        
        if (data.success && data.primaryImage && data.primaryImage.file_exists) {
            const primaryImage = data.primaryImage;headerContainer.innerHTML = '<div class="w-40 h-40 rounded-lg border-2 border-gray-200 overflow-hidden bg-white shadow-lg">' +
                '<img src="' + primaryImage.image_path + '" ' +
                     'alt="' + currentItemSku + '" ' +
                     'class="w-full h-full object-cover hover:scale-105 transition-transform duration-200" ' +
                     'data-fallback="placeholder">' +
            '</div>';
        } else {// Show placeholder
            headerContainer.innerHTML = '<div class="w-40 h-40 rounded-lg border-2 border-gray-200 bg-gray-100 flex flex-col items-center justify-center shadow-lg">' +
                '<svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>' +
                '</svg>' +
            '</div>';
        }
    })
    .catch(error => {
        console.error('Marketing Manager: Error loading primary image:', error);
        // Show error placeholder
        const headerContainer = document.getElementById('marketingItemImageHeader');
        
        if (headerContainer) {
            headerContainer.innerHTML = '<div class="w-40 h-40 rounded-lg border-2 border-red-200 bg-red-50 flex flex-col items-center justify-center shadow-lg">' +
                '<svg class="w-16 h-16 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>' +
                '</svg>' +
            '</div>';
        }
    });
}

function clearMarketingFields() {// Clear text fields
    const textFields = [
        'marketingTitle', 'marketingDescription', 'targetAudience', 'demographics',
        'psychographics', 'searchIntent', 'seasonalRelevance'
    ];
    
    textFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = '';
        }
    });
    
    // Auto-populate title and description with current item data when no marketing data exists
    const nameField = document.getElementById('name');
    const descriptionField = document.getElementById('description');
    
    if (nameField && nameField.value.trim()) {
        const titleField = document.getElementById('marketingTitle');
        if (titleField) {
            titleField.value = nameField.value.trim();
            console.log('Marketing Manager: Auto-populated title with current item name:', nameField.value.trim());
        }
    }
    
    if (descriptionField && descriptionField.value.trim()) {
        const marketingDescField = document.getElementById('marketingDescription');
        if (marketingDescField) {
            marketingDescField.value = descriptionField.value.trim();
            console.log('Marketing Manager: Auto-populated description with current item description:', descriptionField.value.trim());
        }
    }
    
    // Clear list fields
    const listFields = [
        'sellingPointsList', 'competitiveAdvantagesList', 'customerBenefitsList', 
        'seoKeywordsList', 'callToActionsList', 'urgencyFactorsList', 'conversionTriggersList'
    ];
    
    listFields.forEach(listId => {
        const list = document.getElementById(listId);
        if (list) {
            list.innerHTML = '';
        }
    });
    
    // Reset change tracking flags
    hasMarketingChanges = false;
    hasTitleChanges = false;
    hasDescriptionChanges = false;
    
    // Hide save buttons
    updateMarketingSaveButtonVisibility();
}

function populateMarketingFields(data) {// Populate text fields (excluding brand voice and content tone which are global settings)
    const textFields = {
        'marketingTitle': 'suggested_title',
        'marketingDescription': 'suggested_description',
        'targetAudience': 'target_audience',
        'demographics': 'demographic_targeting',
        'psychographics': 'psychographic_profile',
        'searchIntent': 'search_intent',
        'seasonalRelevance': 'seasonal_relevance'
    };
    
    let fieldsPopulated = false;
    Object.keys(textFields).forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && data[textFields[fieldId]]) {field.value = data[textFields[fieldId]];
            fieldsPopulated = true;
            
            // Trigger individual field change tracking
            if (fieldId === 'marketingTitle') {
                hasTitleChanges = true;
            } else if (fieldId === 'marketingDescription') {
                hasDescriptionChanges = true;
            }
        }
    });
    
    // Auto-populate title and description with current item data if not in marketing data
    const nameField = document.getElementById('name');
    const descriptionField = document.getElementById('description');
    
    // Auto-populate title if no marketing title exists but item name does
    const titleField = document.getElementById('marketingTitle');
    if (titleField && !data.suggested_title && nameField && nameField.value.trim()) {
        titleField.value = nameField.value.trim();
        console.log('Marketing Manager: Auto-populated title with current item name:', nameField.value.trim());
        fieldsPopulated = true;
    }
    
    // Auto-populate description if no marketing description exists but item description does
    const marketingDescField = document.getElementById('marketingDescription');
    if (marketingDescField && !data.suggested_description && descriptionField && descriptionField.value.trim()) {
        marketingDescField.value = descriptionField.value.trim();
        console.log('Marketing Manager: Auto-populated description with current item description:', descriptionField.value.trim());
        fieldsPopulated = true;
    }
    
    // If any fields were populated, trigger change tracking to show save buttons
    if (fieldsPopulated) {
        hasMarketingChanges = true;
        updateMarketingSaveButtonVisibility();
    }// Populate list fields
    const listFields = {
        'sellingPointsList': 'selling_points',
        'competitiveAdvantagesList': 'competitive_advantages',
        'customerBenefitsList': 'customer_benefits',
        'seoKeywordsList': 'seo_keywords',
        'callToActionsList': 'call_to_action_suggestions',
        'urgencyFactorsList': 'urgency_factors',
        'conversionTriggersList': 'conversion_triggers'
    };
    
    Object.keys(listFields).forEach(listId => {
        const list = document.getElementById(listId);
        if (list && data[listFields[listId]] && Array.isArray(data[listFields[listId]])) {
            list.innerHTML = '';
            data[listFields[listId]].forEach(item => {
                addListItemToUI(listId, item, listFields[listId]);
            });
        }
    });
}

function addListItemToUI(listId, item, fieldName) {
    const list = document.getElementById(listId);
    if (!list) return;
    
    const itemDiv = document.createElement('div');
    itemDiv.className = 'flex justify-between items-center bg-white p-2 rounded border';
    itemDiv.innerHTML = `
        <span class="text-sm text-gray-700">${item}</span>
        <button data-action="remove-list-item" data-field="${fieldName}" data-item="${encodeURIComponent(item)}" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
    `;
    
    list.appendChild(itemDiv);
}

function addListItem(fieldName) {
    const inputId = 'new' + fieldName.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join('');
    const input = document.getElementById(inputId);
    
    if (!input || !input.value.trim()) {
        showError( 'Please enter a value');
        return;
    }
    
    const value = input.value.trim();
    
    fetch('/api/marketing_manager.php?action=add_list_item', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            sku: currentItemSku,
            field: fieldName,
            item: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            const listId = fieldName.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join('') + 'List';
            addListItemToUI(listId, value, fieldName);
            showSuccess( 'Item added successfully');
        } else {
            showError( data.error || 'Failed to add item');
        }
    })
    .catch(error => {
        console.error('Error adding list item:', error);
        showError( 'Failed to add item');
    });
}

function removeListItem(fieldName, item) {
    fetch('/api/marketing_manager.php?action=remove_list_item', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            sku: currentItemSku,
            field: fieldName,
            item: item
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadExistingMarketingData(); // Refresh the display
            showSuccess( 'Item removed successfully');
        } else {
            showError( data.error || 'Failed to remove item');
        }
    })
    .catch(error => {
        console.error('Error removing list item:', error);
        showError( 'Failed to remove item');
    });
}

function saveMarketingField(fieldName) {
    const fieldId = fieldName === 'suggested_title' ? 'marketingTitle' :
                   fieldName === 'suggested_description' ? 'marketingDescription' :
                   fieldName === 'target_audience' ? 'targetAudience' :
                   fieldName === 'demographic_targeting' ? 'demographics' :
                   fieldName === 'psychographic_profile' ? 'psychographics' :
                   fieldName === 'brand_voice' ? 'brandVoice' :
                   fieldName === 'content_tone' ? 'contentTone' :
                   fieldName === 'search_intent' ? 'searchIntent' :
                   fieldName === 'seasonal_relevance' ? 'seasonalRelevance' : fieldName;
    
    const field = document.getElementById(fieldId);
    if (!field) {
        showError( 'Field not found');
        return;
    }
    
    const value = field.value.trim();
    if (!value) {
        showError( 'Please enter a value');
        return;
    }
    
    fetch('/api/marketing_manager.php?action=update_field', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            sku: currentItemSku,
            field: fieldName,
            value: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess( 'Field saved successfully');
            resetMarketingChangeTracking();
        } else {
            showError( data.error || 'Failed to save field');
        }
    })
    .catch(error => {
        console.error('Error saving field:', error);
        showError( 'Failed to save field');
    });
}

function saveMarketingFields(fieldNames) {
    if (!Array.isArray(fieldNames) || fieldNames.length === 0) {
        showError( 'No fields specified');
        return;
    }
    
    const fieldsData = {};
    let hasValues = false;
    
    // Collect all field values
    for (const fieldName of fieldNames) {
        const fieldId = fieldName === 'suggested_title' ? 'marketingTitle' :
                       fieldName === 'suggested_description' ? 'marketingDescription' :
                       fieldName === 'target_audience' ? 'targetAudience' :
                       fieldName === 'demographic_targeting' ? 'demographics' :
                       fieldName === 'psychographic_profile' ? 'psychographics' :
                       fieldName === 'brand_voice' ? 'brandVoice' :
                       fieldName === 'content_tone' ? 'contentTone' :
                       fieldName === 'search_intent' ? 'searchIntent' :
                       fieldName === 'seasonal_relevance' ? 'seasonalRelevance' : fieldName;
        
        const field = document.getElementById(fieldId);
        if (field && field.value.trim()) {
            fieldsData[fieldName] = field.value.trim();
            hasValues = true;
        }
    }
    
    if (!hasValues) {
        showError( 'Please enter values for the fields');
        return;
    }
    
    // Save all fields
    const promises = Object.entries(fieldsData).map(([fieldName, value]) => {
        return fetch('/api/marketing_manager.php?action=update_field', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                sku: currentItemSku,
                field: fieldName,
                value: value
            })
        }).then(response => response.json());
    });
    
    Promise.all(promises)
    .then(results => {
        const allSuccessful = results.every(result => result.success);
        if (allSuccessful) {
            showSuccess( `All ${fieldNames.length} fields saved successfully`);
            resetMarketingChangeTracking();
        } else {
            const failedCount = results.filter(result => !result.success).length;
            showWarning( `${fieldNames.length - failedCount} fields saved, ${failedCount} failed`);
        }
    })
    .catch(error => {
        console.error('Error saving fields:', error);
        showError( 'Failed to save fields');
    });
}

function applyMarketingTitle() {
    const titleField = document.getElementById('marketingTitle');
    const nameField = document.getElementById('name');
    
    if (titleField && nameField && titleField.value.trim()) {
        const newTitle = titleField.value.trim();
        nameField.value = newTitle;
        nameField.style.backgroundColor = '#f3e8ff';
        
        // Auto-save the item with the new title
        const updateData = {
            sku: currentItemSku,
            name: newTitle,
            description: document.getElementById('description')?.value || '',
            category: document.getElementById('categoryEdit')?.value || '',
            retailPrice: document.getElementById('retailPrice')?.value || '',
            costPrice: document.getElementById('costPrice')?.value || '',
            stockLevel: document.getElementById('stockLevel')?.value || '',
            reorderPoint: document.getElementById('reorderPoint')?.value || ''
        };
        
        fetch('/api/update-inventory.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(updateData)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showSuccess( 'Title applied and item saved automatically!');
            } else {
                console.error('API error:', data);
                showError( 'Failed to save: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error auto-saving product:', error);
            showError( 'Network error: ' + error.message);
        });
        
        setTimeout(() => {
            nameField.style.backgroundColor = '';
        }, 2000);
    }
}

function applyMarketingDescription() {
    const descField = document.getElementById('marketingDescription');
    const productDescField = document.getElementById('description');
    
    if (descField && productDescField && descField.value.trim()) {
        const newDescription = descField.value.trim();
        productDescField.value = newDescription;
        productDescField.style.backgroundColor = '#f0fdf4';
        
        // Auto-save the product with the new description
        const updateData = {
            sku: currentItemSku,
            name: document.getElementById('name')?.value || '',
            description: newDescription,
            category: document.getElementById('categoryEdit')?.value || '',
            retailPrice: document.getElementById('retailPrice')?.value || '',
            costPrice: document.getElementById('costPrice')?.value || '',
            stockLevel: document.getElementById('stockLevel')?.value || '',
            reorderPoint: document.getElementById('reorderPoint')?.value || ''
        };
        
        fetch('/api/update-inventory.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(updateData)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                                        showSuccess( 'Description applied and item saved automatically!');
            } else {
                console.error('API error:', data);
                showError( 'Failed to save: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error auto-saving product:', error);
            showError( 'Network error: ' + error.message);
        });
        
        setTimeout(() => {
            productDescField.style.backgroundColor = '';
        }, 2000);
    }
}

// Combined functions that apply to product AND save as draft
function applyAndSaveMarketingTitle() {
    const titleField = document.getElementById('marketingTitle');
    const nameField = document.getElementById('name');
    
    if (!titleField || !titleField.value.trim()) {
        showError( 'Please enter a title');
        return;
    }
    
    const newTitle = titleField.value.trim();
    
    // Save as draft first
    fetch('/api/marketing_manager.php?action=update_field', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            sku: currentItemSku,
            field: 'suggested_title',
            value: newTitle
        })
    })
    .then(response => response.json())
    .then(draftData => {
        if (draftData.success) {
            // Draft saved successfully, now apply to product
            if (nameField) {
                nameField.value = newTitle;
                nameField.style.backgroundColor = '#f3e8ff';
                
                // Auto-save the item with the new title
                const updateData = {
                    sku: currentItemSku,
                    name: newTitle,
                    description: document.getElementById('description')?.value || '',
                    category: document.getElementById('categoryEdit')?.value || '',
                    retailPrice: document.getElementById('retailPrice')?.value || '',
                    costPrice: document.getElementById('costPrice')?.value || '',
                    stockLevel: document.getElementById('stockLevel')?.value || '',
                    reorderPoint: document.getElementById('reorderPoint')?.value || ''
                };
                
                fetch('/api/update-inventory.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(updateData)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showSuccess( 'Title saved as draft and applied to item!');
                        // Reset only title changes, not all marketing changes
                        originalMarketingData['marketingTitle'] = newTitle;
                        hasTitleChanges = false;
                        updateMarketingSaveButtonVisibility();
                    } else {
                        console.error('API error:', data);
                        showError( 'Failed to save product: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error auto-saving product:', error);
                    showError( 'Failed to save product: ' + error.message);
                });
                
                setTimeout(() => {
                    nameField.style.backgroundColor = '';
                }, 2000);
            }
        } else {
            showError( 'Failed to save draft: ' + (draftData.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error saving draft:', error);
        showError( 'Failed to save draft: ' + error.message);
    });
}

function applyAndSaveMarketingDescription() {
    const descField = document.getElementById('marketingDescription');
    const productDescField = document.getElementById('description');
    
    if (!descField || !descField.value.trim()) {
        showError( 'Please enter a description');
        return;
    }
    
    const newDescription = descField.value.trim();
    
    // Save as draft first
    fetch('/api/marketing_manager.php?action=update_field', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            sku: currentItemSku,
            field: 'suggested_description',
            value: newDescription
        })
    })
    .then(response => response.json())
    .then(draftData => {
        if (draftData.success) {
            // Draft saved successfully, now apply to product
            if (productDescField) {
                productDescField.value = newDescription;
                productDescField.style.backgroundColor = '#f0fdf4';
                
                // Auto-save the product with the new description
                const updateData = {
                    sku: currentItemSku,
                    name: document.getElementById('name')?.value || '',
                    description: newDescription,
                    category: document.getElementById('categoryEdit')?.value || '',
                    retailPrice: document.getElementById('retailPrice')?.value || '',
                    costPrice: document.getElementById('costPrice')?.value || '',
                    stockLevel: document.getElementById('stockLevel')?.value || '',
                    reorderPoint: document.getElementById('reorderPoint')?.value || ''
                };
                
                fetch('/api/update-inventory.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(updateData)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showSuccess( 'Description saved as draft and applied to item!');
                        // Reset only description changes, not all marketing changes
                        originalMarketingData['marketingDescription'] = newDescription;
                        hasDescriptionChanges = false;
                        updateMarketingSaveButtonVisibility();
                    } else {
                        console.error('API error:', data);
                        showError( 'Failed to save product: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error auto-saving product:', error);
                    showError( 'Failed to save product: ' + error.message);
                });
                
                setTimeout(() => {
                    productDescField.style.backgroundColor = '';
                }, 2000);
            }
        } else {
            showError( 'Failed to save draft: ' + (draftData.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error saving draft:', error);
        showError( 'Failed to save draft: ' + error.message);
    });
}

function addAIContentBadges(tabNames) {
    tabNames.forEach(tabName => {
        const tabButton = document.getElementById(tabName + 'Tab');
        if (tabButton && !tabButton.querySelector('.ai-badge')) {
            const badge = document.createElement('span');
            badge.className = 'ai-badge ml-1 bg-green-500 text-white text-xs px-1 py-0\.5 rounded';
            badge.textContent = 'AI';
            badge.title = 'Contains AI-generated content';
            tabButton.appendChild(badge);
        }
    });
}

function populateAllMarketingTabs(aiData) {
    if (!aiData || !aiData.marketingIntelligence) return;
    
    const intelligence = aiData.marketingIntelligence;
    
    // Save all the AI-generated data to the database
    const fieldsToSave = [
        // Target Audience tab data
        { field: 'target_audience', value: aiData.targetAudience || '' },
        { field: 'demographic_targeting', value: intelligence.demographic_targeting || '' },
        { field: 'psychographic_profile', value: intelligence.psychographic_profile || '' },
        
        // SEO & Keywords tab data
        { field: 'seo_keywords', value: intelligence.seo_keywords || [] },
        { field: 'search_intent', value: intelligence.search_intent || '' },
        { field: 'seasonal_relevance', value: intelligence.seasonal_relevance || '' },
        
        // Selling Points tab data
        { field: 'selling_points', value: intelligence.selling_points || [] },
        { field: 'competitive_advantages', value: intelligence.competitive_advantages || [] },
        { field: 'customer_benefits', value: intelligence.customer_benefits || [] },
        
        // Conversion tab data
        { field: 'call_to_action_suggestions', value: intelligence.call_to_action_suggestions || [] },
        { field: 'urgency_factors', value: intelligence.urgency_factors || [] },
        { field: 'conversion_triggers', value: intelligence.conversion_triggers || [] }
    ];
    
    // Save all fields to database
    const savePromises = fieldsToSave.map(item => {
        return fetch('/api/marketing_manager.php?action=update_field', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                sku: currentItemSku,
                field: item.field,
                value: item.value
            })
        }).then(response => response.json());
    });
    
    // Wait for all saves to complete
    Promise.all(savePromises).then(results => {
        const successCount = results.filter(r => r.success).length;// Add visual indicators to tabs that now have AI content
         addAIContentBadges(['audience', 'selling', 'seo', 'conversion']);
         
         // If user is currently viewing one of the populated tabs, refresh it
         const currentTab = document.querySelector('.marketing-tab.bg-white');
         if (currentTab) {
             const tabName = currentTab.id.replace('Tab', '');
             if (['audience', 'selling', 'seo', 'conversion'].includes(tabName)) {
                 loadMarketingTabContent(tabName);
             }
         }
    }).catch(error => {
        console.error('Error saving marketing fields:', error);
    });
}

function generateNewMarketingContent() {
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="animate-spin">⏳</span> Generating...';
    button.disabled = true;
    
    // Preserve current brand voice and content tone settings
    const currentBrandVoice = document.getElementById('brandVoice')?.value || '';
    const currentContentTone = document.getElementById('contentTone')?.value || '';
    
    // Check if current AI model supports images and get current item data
    checkAIImageSupport().then(supportsImages => {
        const itemData = {
            sku: currentItemSku,
            name: document.getElementById('name')?.value || '',
            description: document.getElementById('description')?.value || '',
            category: document.getElementById('categoryEdit')?.value || '',
            retailPrice: document.getElementById('retailPrice')?.value || '',
            // Include brand voice and tone preferences
            brandVoice: currentBrandVoice,
            contentTone: currentContentTone,
            useImages: supportsImages
        };
        
        fetch('/api/suggest_marketing.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(itemData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess( '🎯 AI content generated for: Target Audience, Selling Points, SEO & Keywords, and Conversion tabs!');
            
            // Populate all tabs with AI-generated data
            populateAllMarketingTabs(data);
            
            // Refresh the current tab display but preserve voice/tone settings
            loadExistingMarketingData().then(() => {
                // Restore the brand voice and tone settings after loading
                if (currentBrandVoice) {
                    const brandVoiceField = document.getElementById('brandVoice');
                    if (brandVoiceField) brandVoiceField.value = currentBrandVoice;
                }
                if (currentContentTone) {
                    const contentToneField = document.getElementById('contentTone');
                    if (contentToneField) contentToneField.value = currentContentTone;
                }
                
                // Trigger change tracking for AI-generated content
                hasMarketingChanges = true;
                
                // Also trigger change tracking for specific fields that may have been updated
                const fieldsToTrack = ['marketingTitle', 'marketingDescription', 'targetAudience', 'demographics', 
                                     'psychographics', 'brandVoice', 'contentTone', 'searchIntent', 'seasonalRelevance'];
                fieldsToTrack.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && field.value) {
                        trackMarketingFieldChange(fieldId);
                    }
                });
                
                updateMarketingSaveButtonVisibility();
            });
        } else {
            showError( data.error || 'Failed to generate marketing content');
        }
    })
    .catch(error => {
        console.error('Error generating marketing content:', error);
        showError( 'Failed to generate marketing content');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
    });
}

// Generate fresh marketing comparison (ignores existing data)
function generateFreshMarketingComparison() {
    if (!currentItemSku) {
        showError( 'No item selected for marketing generation');
        return;
    }

    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '🔥 Generating...';
    button.disabled = true;

    // Get basic item data from the edit form
    const nameField = document.getElementById('name');
    const categoryField = document.getElementById('categoryEdit');
    const descriptionField = document.getElementById('description');

    if (!nameField || !nameField.value.trim()) {
        showError( 'Item name is required for marketing generation');
        button.innerHTML = originalText;
        button.disabled = false;
        return;
    }

    // Get current global settings
    const brandVoiceField = document.getElementById('brandVoice');
    const contentToneField = document.getElementById('contentTone');

    // Prepare data for fresh generation (no existing marketing data)
    const itemData = {
        sku: currentItemSku,
        name: nameField.value.trim(),
        category: categoryField ? categoryField.value : '',
        description: descriptionField ? descriptionField.value.trim() : '',
        brand_voice: brandVoiceField ? brandVoiceField.value : '',
        content_tone: contentToneField ? contentToneField.value : '',
        fresh_start: true  // This tells the API to ignore existing marketing data
    };fetch('/api/suggest_marketing.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(itemData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess( '🔥 Fresh marketing content generated! All fields updated with brand new AI suggestions.');
            
            // Populate all tabs with fresh AI-generated data
            populateAllMarketingTabs(data);
            
            // Clear any cached content and reload with fresh data
            clearMarketingFields();
            
            // Load the fresh data
            setTimeout(() => {
                loadExistingMarketingData().then(() => {
                    // Restore the global settings
                    if (brandVoiceField && itemData.brand_voice) {
                        brandVoiceField.value = itemData.brand_voice;
                    }
                    if (contentToneField && itemData.content_tone) {
                        contentToneField.value = itemData.content_tone;
                    }
                    
                    // Mark as having changes
                    hasMarketingChanges = true;
                    updateMarketingSaveButtonVisibility();
                });
            }, 500);
            
        } else {
            showError( data.error || 'Failed to generate fresh marketing content');
        }
    })
    .catch(error => {
        console.error('Error generating fresh marketing content:', error);
        showError( 'Failed to generate fresh marketing content');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Load global marketing defaults
async function loadGlobalMarketingDefaults() {
    try {
        const response = await fetch('/api/website_config.php?action=get_marketing_defaults');
        const data = await response.json();
        
        if (data.success) {
            const defaults = data.data;
            
            // Set brand voice if available
            const brandVoiceField = document.getElementById('brandVoice');
            if (brandVoiceField && defaults.default_brand_voice) {
                brandVoiceField.value = defaults.default_brand_voice;
            }
            
            // Set content tone if available
            const contentToneField = document.getElementById('contentTone');
            if (contentToneField && defaults.default_content_tone) {
                contentToneField.value = defaults.default_content_tone;
            }
        }
    } catch (error) {
        console.error('Error loading global marketing defaults:', error);
    }
}

// migrated: updateGlobalMarketingDefault handled in AdminInventoryModule

// AI Content Generation with Comparison Modal
function generateAllMarketingContent() {if (!currentItemSku) {
        showError( 'No item selected for marketing content generation');
        return;
    }
    
    // Show the comparison modal
    showAIComparisonModal();
    
    // Start the AI analysis process
    startAIAnalysisProcess();
}

function showAIComparisonModal() {const modal = document.getElementById('aiComparisonModal');
    const progressSection = document.getElementById('aiAnalysisProgressSection');
    
    if (modal) {// Remove hidden class
        modal.classList.remove('hidden');
        
        // Clear any existing CSS classes that might hide it
        modal.className = '';
        
        // Apply aggressive CSS styling to force visibility (same as Marketing Manager fix)
        modal.style.cssText = `
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: rgba(0, 0, 0, 0.75) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            z-index: 2147483647 !important;
            visibility: visible !important;
            opacity: 1 !important;
            padding: 8px !important;
        `;
        
        // Force it to the front of the DOM (same fix as Marketing Manager)
        document.body.appendChild(modal);} else {
        console.error('AI Comparison Modal not found!');
    }
    
    if (progressSection) {
        progressSection.style.display = 'block';
        progressSection.style.maxHeight = '200px';
        progressSection.style.opacity = '1';
        progressSection.style.paddingTop = '';
        progressSection.style.paddingBottom = '';
    }
    
    const contentDiv = document.getElementById('aiComparisonContent');
    if (contentDiv) {
        contentDiv.innerHTML = '<div class="text-center text-gray-500">AI analysis in progress...</div>';
    }}

function closeAIComparisonModal() {const modal = document.getElementById('aiComparisonModal');
    if (modal) {// Reset all inline styles that were set by showAIComparisonModal
        modal.style.cssText = '';
        
        // Add the hidden class
        modal.classList.add('hidden');
        
        // Force hide with inline styles to override any remaining CSS
        modal.style.display = 'none !important';
        modal.style.visibility = 'hidden !important';
        modal.style.opacity = '0 !important';} else {
        console.error('AI Comparison Modal not found!');
    }
}

function startAIAnalysisProcess() {
    // Initialize progress
    updateAIAnalysisProgress('initializing', 'Initializing AI analysis...');
    setStepStatus('step1-analyze', 'waiting');
    setStepStatus('step2-extract-insights', 'waiting');
    setStepStatus('step3-generate-content', 'waiting');
    updateAIProgressBar(0);
    
    // Get current brand voice and tone
    const brandVoice = document.getElementById('brandVoice')?.value || 'friendly';
    const contentTone = document.getElementById('contentTone')?.value || 'conversational';
    
    // Prepare item data for AI generation
    const itemData = {
        sku: currentItemSku,
        name: document.getElementById('name')?.value || '',
        description: document.getElementById('description')?.value || '',
        category: document.getElementById('categoryEdit')?.value || '',
        retailPrice: document.getElementById('retailPrice')?.value || '',
        brandVoice: brandVoice,
        contentTone: contentTone,
        useImages: true
    };
    
    // Step 1: Start analysis
    setTimeout(() => {
        updateAIAnalysisProgress('starting', 'Starting AI content analysis...');
        setStepStatus('step1-analyze', 'active');
        updateAIProgressBar(10);
        
        // Step 2: Extract insights
        setTimeout(() => {
            setStepStatus('step1-analyze', 'completed');
            updateAIProgressBar(40);
            updateAIAnalysisProgress('extracting', 'Extracting marketing insights...');
            setStepStatus('step2-extract-insights', 'active');
            
            // Step 3: Generate content
            setTimeout(() => {
                setStepStatus('step2-extract-insights', 'completed');
                updateAIProgressBar(70);
                updateAIAnalysisProgress('generating', 'Generating enhanced content...');
                setStepStatus('step3-generate-content', 'active');
                
                // Make the actual API call
                fetch('/api/suggest_marketing.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(itemData)
                })
                .then(response => response.json())
                .then(data => {
                    setStepStatus('step3-generate-content', 'completed');
                    updateAIProgressBar(100);
                    updateAIAnalysisProgress('completed', '✅ AI analysis completed successfully!');
                    
                    if (data.success) {
                        // Show comparison results
                        setTimeout(() => {
                            collapseAIProgressSection();
                            showComparisonResults(data);
                        }, 1500);
                    } else {
                        showError( data.error || 'Failed to generate marketing content');
                        closeAIComparisonModal();
                    }
                })
                .catch(error => {
                    console.error('Error generating marketing content:', error);
                    setStepStatus('step3-generate-content', 'error');
                    updateAIAnalysisProgress('error', '❌ Error generating content');
                    showError( 'Failed to generate marketing content');
                });
            }, 1000);
        }, 1000);
    }, 500);
}

function updateAIAnalysisProgress(stage, message) {
    const progressText = document.getElementById('aiProgressText');
    const spinner = document.getElementById('aiProgressSpinner');
    
    if (progressText) {
        progressText.textContent = message;
    }
    
    if (spinner) {
        if (stage === 'completed') {
            spinner.classList.remove('animate-spin');
            spinner.innerHTML = '✅';
            spinner.classList.add('text-green-600');
        } else if (stage === 'error') {
            spinner.classList.remove('animate-spin');
            spinner.innerHTML = '❌';
            spinner.classList.add('text-red-600');
        }
    }
}

function setStepStatus(stepId, status) {
    const stepElement = document.getElementById(stepId);
    if (!stepElement) return;
    
    const indicator = stepElement.querySelector('div');
    
    // Reset classes
    indicator.className = 'w-3 h-3 rounded-full';
    
    switch (status) {
        case 'waiting':
            indicator.classList.add('bg-gray-300');
            break;
        case 'active':
            indicator.classList.add('bg-blue-500', 'animate-pulse');
            break;
        case 'completed':
            indicator.classList.add('bg-green-500');
            break;
        case 'error':
            indicator.classList.add('bg-red-500');
            break;
    }
}

function updateAIProgressBar(percentage) {
    const progressBar = document.getElementById('aiProgressBar');
    if (progressBar) {
        progressBar.style.width = `${percentage}%`;
    }
}

function collapseAIProgressSection() {
    const progressSection = document.getElementById('aiAnalysisProgressSection');
    if (progressSection) {
        progressSection.style.maxHeight = '0px';
        progressSection.style.opacity = '0';
        progressSection.style.paddingTop = '0';
        progressSection.style.paddingBottom = '0';
        
        setTimeout(() => {
            progressSection.style.display = 'none';
        }, 500);
    }
}

function showComparisonResults(data) {
    const contentDiv = document.getElementById('aiComparisonContent');
    const applyBtn = document.getElementById('applyChangesBtn');
    
    if (!contentDiv) return;
    
    // Store the AI data globally
    window.aiComparisonData = data;
    
    // First, get current marketing data from database to compare againstfetch(`/api/marketing_manager.php?action=get_marketing_data&sku=${currentItemSku}&_t=${Date.now()}`)
        .then(response => response.json())
        .then(currentData => {buildComparisonInterface(data, currentData.data);
        })
        .catch(error => {
            console.error('Error loading current marketing data:', error);
            // Fallback to building interface without current data
            buildComparisonInterface(data, null);
        });
}

function buildComparisonInterface(aiData, currentMarketingData) {
    const contentDiv = document.getElementById('aiComparisonContent');
    const applyBtn = document.getElementById('applyChangesBtn');
    
    // Build comparison interface
    let html = '<div class="space-y-6">';
    html += '<div class="text-center">';
    html += '<h3 class="text-lg font-semibold text-gray-800">🎯 AI Content Comparison</h3>';
    html += '<p class="text-sm text-gray-600">Review and select which AI-generated content to apply to your item</p>';
    html += '</div>';
    
    // Store available fields for select all functionality
    let availableFields = [];
    
    // Title comparison
    if (aiData.title) {
        const currentTitle = document.getElementById('name')?.value || '';
        const suggestedTitle = aiData.title;
        
        if (currentTitle !== suggestedTitle) {
            availableFields.push('title');
            html += createComparisonCard('title', 'Item Title', currentTitle, suggestedTitle);
        }
    }
    
    // Description comparison
    if (aiData.description) {
        const currentDesc = document.getElementById('description')?.value || '';
        const suggestedDesc = aiData.description;
        
        if (currentDesc !== suggestedDesc) {
            availableFields.push('description');
            html += createComparisonCard('description', 'Item Description', currentDesc, suggestedDesc);
        }
    }
    
    // Marketing fields comparison - use database values as current
    const marketingFields = [
        { 
            key: 'target_audience', 
            label: 'Target Audience', 
            current: currentMarketingData?.target_audience || '', 
            suggested: aiData.targetAudience 
        },
        { 
            key: 'demographic_targeting', 
            label: 'Demographics', 
            current: currentMarketingData?.demographic_targeting || '', 
            suggested: aiData.marketingIntelligence?.demographic_targeting 
        },
        { 
            key: 'psychographic_profile', 
            label: 'Psychographics', 
            current: currentMarketingData?.psychographic_profile || '', 
            suggested: aiData.marketingIntelligence?.psychographic_profile 
        }
    ];
    
    marketingFields.forEach(field => {
        if (field.suggested && field.current !== field.suggested) {
            availableFields.push(field.key);
            html += createComparisonCard(field.key, field.label, field.current, field.suggested);
        }
    });
    
    // Add select all control if there are available fields
    if (availableFields.length > 0) {
        html = html.replace('<div class="space-y-6">', `
            <div class="space-y-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="selectAllComparison" class="h-4 w-4 text-blue-600 border-gray-300 rounded" data-action="toggle-select-all-comparison">
                        <label for="selectAllComparison" class="font-medium text-blue-800">Select All AI Suggestions</label>
                    </div>
                    <span class="text-sm text-blue-600">${availableFields.length} suggestions available</span>
                </div>
                <p class="text-sm text-blue-600">Apply all AI-generated content to your item at once</p>
            </div>
        `);
    }
    
    // Only show "no changes" message if there are truly no available fields
    if (availableFields.length === 0) {
        html += '<div class="text-center text-gray-500">';
        html += '<p>No changes detected. All AI suggestions match your current content.</p>';
        html += '<div class="text-xs bg-gray-100 rounded">';
        html += '<strong>Debug Info:</strong><br>';
        html += `Current Title: "${document.getElementById('name')?.value || 'N/A'}"<br>`;
        html += `AI Title: "${aiData.title || 'N/A'}"<br>`;
        html += `Current Desc: "${(document.getElementById('description')?.value || 'N/A').substring(0, 50)}..."<br>`;
        html += `AI Desc: "${(aiData.description || 'N/A').substring(0, 50)}..."`;
        html += '</div>';
        html += '</div>';
    }
    
    html += '</div>';
    
    contentDiv.innerHTML = html;
    
    // Store available fields globally for select all functionality
    window.availableComparisonFields = availableFields;
    
    // Show apply button only if there are changes
    if (applyBtn) {
        if (availableFields.length > 0) {
            applyBtn.classList.remove('hidden');
        } else {
            applyBtn.classList.add('hidden');
        }
    }
}

function createComparisonCard(fieldKey, fieldLabel, currentValue, suggestedValue) {
    const cardId = `comparison-${fieldKey}`;
    return `
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="flex items-center justify-between">
                <h4 class="font-medium text-gray-800">${fieldLabel}</h4>
                <label class="flex items-center">
                    <input type="checkbox" id="${cardId}-checkbox" class="" data-action="toggle-comparison" data-field="${fieldKey}">
                    <span class="text-sm text-gray-600">Apply AI suggestion</span>
                </label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 rounded">
                    <h5 class="text-sm font-medium text-gray-600">Current</h5>
                    <p class="text-sm text-gray-800">${currentValue || '<em>No current value</em>'}</p>
                </div>
                <div class="bg-green-50 rounded">
                    <h5 class="text-sm font-medium text-green-600">AI Suggested</h5>
                    <p class="text-sm text-gray-800">${suggestedValue}</p>
                </div>
            </div>
        </div>
    `;
}

// migrated: AI comparison selection handlers managed by AdminInventoryModule
</script>
<!-- MIGRATED: Inline inventory helpers removed; now handled by Vite module at src/js/admin-inventory.js
// AI Content Comparison Modal Functions (cleaned up)
window.aiComparisonData = {};
let totalFields = 0;
let processedFields = 0;

function getNestedValue(obj, path) {
    if (!path.includes('.')) {
        return obj[path];
    }
    return path.split('.').reduce((current, key) => current && current[key], obj);
}

function handleModalBackdropClick(event, modalId) {
    // Only close if clicking on the backdrop (not on the modal content)
    if (event.target === event.currentTarget) {
        if (modalId === 'marketingManagerModal') {
            closeMarketingManager();
        }
    }
}

// Legacy global listeners for #open-marketing-manager-btn removed; use data-action="open-marketing-manager" and delegated handlers in src/js/admin-inventory.js

// Color Management Functions

// Load colors for the current item
async function loadItemColors() {// Try to get SKU from multiple sources
    if (!currentItemSku) {
        const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
        if (skuField && skuField.value) {
            currentItemSku = skuField.value;}
    }
    
    if (!currentItemSku) {const colorsLoading = document.getElementById('colorsLoading');
        if (colorsLoading) {
            colorsLoading.textContent = 'No SKU available';
        }
        return;
    }
    
    // Show loading state
    const colorsLoading = document.getElementById('colorsLoading');
    if (colorsLoading) {
        colorsLoading.textContent = 'Loading colors...';
        colorsLoading.style.display = 'block';
    }
    
    try {const response = await fetch(`/api/item_colors.php?action=get_all_colors&item_sku=${currentItemSku}`);
        const data = await response.json();if (data.success) {
            renderColors(data.colors);
        } else {
            console.error('Error loading colors:', data.message);
            renderColors([]);
        }
    } catch (error) {
        console.error('Error fetching colors:', error);
        renderColors([]);
    }
}

// Render colors list
function renderColors(colors) {
    const colorsList = document.getElementById('colorsList');
    const colorsLoading = document.getElementById('colorsLoading');
    
    if (colorsLoading) {
        colorsLoading.style.display = 'none';
    }
    
    if (!colorsList) return;
    
    if (colors.length === 0) {
        colorsList.innerHTML = '<div class="text-center text-gray-500 text-sm">No colors defined. Click "Add Color" to get started.</div>';
        return;
    }
    
    // Calculate total stock from active colors
    const activeColors = colors.filter(c => c.is_active == 1);
    const totalColorStock = activeColors.reduce((sum, c) => sum + parseInt(c.stock_level || 0), 0);
    
    // Get current item stock level
    const stockField = document.getElementById('stockLevel');
    const currentItemStock = stockField ? parseInt(stockField.value || 0) : 0;
    
    // Check if stock is in sync
    const isInSync = totalColorStock === currentItemStock;
    
    let html = '';
    
    // Add sync status indicator if there are active colors
    if (activeColors.length > 0) {
        const syncClass = isInSync ? 'bg-green-50 border-green-200 text-green-800' : 'bg-yellow-50 border-yellow-200 text-yellow-800';
        const syncIcon = isInSync ? '✅' : '⚠️';
        const syncMessage = isInSync ? 
            `Stock synchronized (${totalColorStock} total)` : 
            `Stock out of sync! Colors total: ${totalColorStock}, Item stock: ${currentItemStock}`;
        
        html += `
            <div class="border rounded-lg ${syncClass}">
                <div class="text-sm font-medium">${syncIcon} ${syncMessage}</div>
                ${!isInSync ? '<div class="text-xs">Click "Sync Stock" to fix this.</div>' : ''}
            </div>
        `;
    }
    
    html += colors.map(color => {
        const isActive = color.is_active == 1;
        const activeClass = isActive ? 'bg-white' : 'bg-gray-100 opacity-75';
        const activeText = isActive ? '' : ' (Inactive)';
        
        return `
            <div class="color-item flex items-center justify-between border border-gray-200 rounded-lg ${activeClass}">
                <div class="flex items-center space-x-3">
                    <div class="color-swatch w-8 h-8 rounded-full border-2 border-gray-300" ></div>
                                            <div>
                            <div class="font-medium text-gray-800">${color.color_name}${activeText}</div>
                            <div class="text-sm text-gray-500 flex items-center">
                                <span class="inline-stock-editor" 
                                      data-type="color" 
                                      data-id="${color.id}" 
                                      data-field="stock_level" 
                                      data-value="${color.stock_level}"
                                      data-action="edit-inline-stock"
                                      title="Click to edit stock level">
                                    ${color.stock_level}
                                </span>
                                <span class="">in stock</span>
                            </div>
                            ${color.image_path ? `<div class="text-xs text-blue-600">Image: ${color.image_path}</div>` : ''}
                        </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" data-action="delete-color" data-id="${color.id}" class="bg-red-500 text-white rounded text-xs hover:bg-red-600">
                        Delete
                    </button>
                </div>
            </div>
        `;
    }).join('');
    
    colorsList.innerHTML = html;
}

// Add new color
function addNewColor() {
    showColorModal();
}

// Edit existing color
async function editColor(colorId) {
    try {
        const response = await fetch(`/api/item_colors.php?action=get_all_colors&item_sku=${currentItemSku}`);
        const data = await response.json();
        
        if (data.success) {
            const color = data.colors.find(c => c.id == colorId);
            if (color) {
                showColorModal(color);
            }
        }
    } catch (error) {
        console.error('Error fetching color for edit:', error);
    }
}

// Delete color
async function deleteColor(colorId) {// Create a styled confirmation modal instead of browser confirm
    const confirmResult = await showStyledConfirm(
        'Delete Color',
        'Are you sure you want to delete this color? This action cannot be undone.',
        'Delete',
        'Cancel'
    );
    
    if (!confirmResult) {return;
    }try {
        const response = await fetch('/api/item_colors.php?action=delete_color', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ color_id: colorId })
        });
        
        const data = await response.json();if (data.success) {showSuccess( 'Color deleted successfully');
            loadItemColors(); // Reload colors
        } else {showError( 'Error deleting color: ' + data.message);
        }
    } catch (error) {
        console.error('💥 Error deleting color:', error);
        showError( 'Error deleting color');
    }
}

// Show color modal
function showColorModal(color = null) {
    // Create modal if it doesn't exist
    if (!document.getElementById('colorModal')) {
        createColorModal();
    }
    
    const modal = document.getElementById('colorModal');
    const form = document.getElementById('colorForm');
    const modalTitle = document.getElementById('colorModalTitle');
    
    // Reset form
    form.reset();
    
    if (color) {
        // Edit mode
        modalTitle.textContent = 'Edit Color';
        document.getElementById('colorId').value = color.id;
        document.getElementById('colorStockLevel').value = color.stock_level;
        document.getElementById('displayOrder').value = color.display_order;
        document.getElementById('isActive').checked = color.is_active == 1;
        
        // Try to find and select the matching global color
        setTimeout(() => {
            const globalColorSelect = document.getElementById('globalColorSelect');
            if (globalColorSelect && color.color_name) {
                // Look for matching color by name and code
                let foundMatch = false;
                for (let i = 0; i < globalColorSelect.options.length; i++) {
                    const option = globalColorSelect.options[i];
                    if (option.value) {
                        try {
                            const colorData = JSON.parse(option.value);
                            if (colorData.name === color.color_name && 
                                colorData.code === color.color_code) {
                                globalColorSelect.value = option.value;
                                handleGlobalColorSelection(); // Trigger preview update
                                foundMatch = true;
                                break;
                            }
                        } catch (error) {
                            // Skip invalid options
                        }
                    }
                }
                
                // If no exact match found, manually populate fields for backward compatibility
                if (!foundMatch) {
                    document.getElementById('colorName').value = color.color_name;
                    document.getElementById('colorCode').value = color.color_code || '#000000';
                    
                    // Show manual preview for existing colors not in global system
                    const selectedColorPreview = document.getElementById('selectedColorPreview');
                    const colorPreviewSwatch = document.getElementById('colorPreviewSwatch');
                    const colorPreviewName = document.getElementById('colorPreviewName');
                    const colorPreviewCode = document.getElementById('colorPreviewCode');
                    
                    if (selectedColorPreview) {
                        selectedColorPreview.classList.remove('hidden');
                        colorPreviewSwatch.style.backgroundColor = color.color_code || '#000000';
                        colorPreviewName.textContent = color.color_name + ' (Legacy Color)';
                        colorPreviewCode.textContent = color.color_code || 'No color code';
                    }
                }
            }
            
                         // Set image path if available
            const hiddenInput = document.getElementById('colorImagePath');
            if (hiddenInput && color.image_path) {
                hiddenInput.value = color.image_path;
                updateImagePreview(); // Update preview when editing existing color
                highlightSelectedImageInGrid(color.image_path); // Highlight in grid
            }
        }, 300); // Small delay to ensure options are loaded
    } else {
        // Add mode
        modalTitle.textContent = 'Add New Color';
        document.getElementById('colorId').value = '';
        document.getElementById('colorStockLevel').value = '0';
        document.getElementById('displayOrder').value = '0';
        document.getElementById('isActive').checked = true;
        
        // Clear global color selection and preview
        setTimeout(() => {
            const globalColorSelect = document.getElementById('globalColorSelect');
            if (globalColorSelect) {
                globalColorSelect.value = '';
            }
            
            const selectedColorPreview = document.getElementById('selectedColorPreview');
            if (selectedColorPreview) {
                selectedColorPreview.classList.add('hidden');
            }
            
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            if (imagePreviewContainer) {
                imagePreviewContainer.classList.add('hidden');
            }
            
            // Clear hidden fields
            document.getElementById('colorName').value = '';
            document.getElementById('colorCode').value = '';
            
            // Clear image selection and highlighting
            const hiddenInput = document.getElementById('colorImagePath');
            if (hiddenInput) {
                hiddenInput.value = '';
            }
            highlightSelectedImageInGrid(null);
        }, 100);
    }
    
    modal.classList.remove('hidden');
}

// Create color modal
function createColorModal() {
    const modalHTML = `
        <div id="colorModal" class="modal-overlay hidden">
            <div class="modal-content" >
                <div class="modal-header">
                    <h2 id="colorModalTitle">Add New Color</h2>
                    <button type="button" class="modal-close" data-action="close-color-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="colorForm">
                        <input type="hidden" id="colorId" name="colorId">
                        
                        <!-- Two-column layout -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Left Column: Color Selection & Basic Info -->
                            <div class="space-y-4">
                                <div>
                                    <label for="globalColorSelect" class="block text-sm font-medium text-gray-700">
                                        Select Color *
                                        <span class="text-xs text-gray-500">(from predefined colors)</span>
                                    </label>
                                    <select id="globalColorSelect" name="globalColorSelect" required class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2">
                                        <option value="">Choose a color...</option>
                                    </select>
                                    <div class="text-xs">
                                        <a href="#" data-action="open-global-colors-management" class="text-blue-600 hover:text-blue-800">
                                            ⚙️ Manage Global Colors in Settings
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Hidden fields populated by global color selection -->
                                <input type="hidden" id="colorName" name="colorName">
                                <input type="hidden" id="colorCode" name="colorCode">
                                
                                <!-- Display selected color -->
                                <div id="selectedColorPreview" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700">Selected Color Preview</label>
                                    <div class="flex items-center space-x-3 bg-gray-50 rounded-lg">
                                        <div id="colorPreviewSwatch" class="w-12 h-12 rounded border-2 border-gray-300 shadow-sm"></div>
                                        <div>
                                            <div id="colorPreviewName" class="font-medium text-gray-900"></div>
                                            <div id="colorPreviewCode" class="text-sm text-gray-500"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="colorStockLevel" class="block text-sm font-medium text-gray-700">Stock Level</label>
                                    <input type="number" id="colorStockLevel" name="stockLevel" min="0" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2" >
                                </div>
                                
                                <div>
                                    <label for="displayOrder" class="block text-sm font-medium text-gray-700">Display Order</label>
                                    <input type="number" id="displayOrder" name="displayOrder" min="0" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2" >
                                </div>
                                
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="isActive" name="isActive" class="">
                                        <span class="text-sm font-medium text-gray-700">Active (visible to customers)</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Right Column: Image Selection & Preview -->
                            <div class="space-y-4">
                                <!-- Available Images Grid (moved to top) -->
                                <div id="availableImagesGrid">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Available Images
                                        <span class="text-xs text-gray-500 font-normal">(click to select for this color)</span>
                                    </label>
                                    <div class="grid grid-cols-4 gap-3 max-h-48 overflow-y-auto border border-gray-200 rounded bg-gray-50">
                                        <!-- Images will be populated here -->
                                    </div>
                                </div>
                                
                                <!-- Image Preview -->
                                <div id="imagePreviewContainer" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700">Selected Image Preview</label>
                                    <div class="border border-gray-300 rounded-lg bg-gray-50">
                                        <div class="flex justify-center">
                                            <img id="imagePreview" src="" alt="Selected image preview" class="max-h-64 object-contain rounded border border-gray-200 shadow-sm">
                                        </div>
                                        <div id="imagePreviewInfo" class="text-center">
                                            <div id="imagePreviewName" class="text-sm font-medium text-gray-700"></div>
                                            <div id="imagePreviewPath" class="text-xs text-gray-500"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden field for storing selected image path -->
                                <input type="hidden" id="colorImagePath" name="colorImagePath" value="">
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-3 border-t border-gray-200">
                            <button type="button" data-action="close-color-modal" class="bg-gray-300 text-gray-800 rounded hover:bg-gray-400 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="text-white rounded transition-colors bg-[#87ac3a] hover:bg-[#6b8e23]">
                                Save Color
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Load global colors and available images for the dropdown
    loadGlobalColorsForSelection();
    loadAvailableImages();
}

// Load available images for color assignment
async function loadAvailableImages() {
    if (!currentItemSku) return;
    
    try {
        const response = await fetch(`/api/get_item_images.php?sku=${currentItemSku}`);
        const data = await response.json();
        
        const availableImagesGrid = document.getElementById('availableImagesGrid');
        if (!availableImagesGrid) return;
        
        const gridContainer = availableImagesGrid.querySelector('.grid');
        
        if (data.success && data.images && data.images.length > 0) {
            // Clear existing grid content
            gridContainer.innerHTML = '';
            
            // Populate images grid
            data.images.forEach(image => {
                const imgContainer = document.createElement('div');
                imgContainer.className = 'relative cursor-pointer hover:opacity-75 transition-all hover:scale-105 hover:shadow-md p-1 rounded';
                imgContainer.onclick = () => selectImageFromGrid(image.image_path);
                
                const img = document.createElement('img');
                // Handle image path - don't double up the /images/items/ prefix
                const imageSrc = image.image_path.startsWith('/images/items/') || image.image_path.startsWith('images/items/') 
                    ? image.image_path 
                    : `/images/items/${image.image_path}`;
                img.src = imageSrc;
                img.alt = image.image_path;
                img.className = 'w-full h-20 object-cover rounded border border-gray-200 hover:border-green-400 transition-colors';
                img.onerror = () => {
                    img.style.display = 'none';
                    img.parentElement.innerHTML = '<div class="u-width-100 u-height-100 u-display-flex u-flex-direction-column u-align-items-center u-justify-content-center u-background-f8f9fa u-color-6c757d u-border-radius-8px"><div class="u-font-size-2rem u-margin-bottom-0-5rem u-opacity-0-7">📷</div><div class="u-font-size-0-8rem u-font-weight-500">Image Not Found</div></div>';
                };
                
                const label = document.createElement('div');
                label.className = 'text-xs text-gray-600 mt-1 text-center';
                label.textContent = image.image_path;
                
                if (image.is_primary) {
                    const primaryBadge = document.createElement('div');
                    primaryBadge.className = 'absolute top-0 right-0 bg-green-500 text-white text-xs px-1 rounded-bl';
                    primaryBadge.textContent = '1°';
                    imgContainer.appendChild(primaryBadge);
                }
                
                imgContainer.appendChild(img);
                imgContainer.appendChild(label);
                gridContainer.appendChild(imgContainer);
            });
            
            // Always show the grid when images are available
            availableImagesGrid.style.display = 'block';
        } else {
            // Show message when no images available
            gridContainer.innerHTML = '<div class="col-span-4 text-center text-gray-500"><div class="text-3xl">📷</div><div class="text-sm">No images available for this item</div></div>';
            availableImagesGrid.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading available images:', error);
        const gridContainer = availableImagesGrid?.querySelector('.grid');
        if (gridContainer) {
            gridContainer.innerHTML = '<div class="col-span-4 text-center text-red-500"><div class="text-sm">Error loading images</div></div>';
        }
    }
}

// Select image from grid
function selectImageFromGrid(imagePath) {
    const hiddenInput = document.getElementById('colorImagePath');
    if (hiddenInput) {
        hiddenInput.value = imagePath;
        updateImagePreview();
        
        // Highlight selected image in grid
        highlightSelectedImageInGrid(imagePath);
    }
}

// Update image preview when selection changes
function updateImagePreview() {
    const hiddenInput = document.getElementById('colorImagePath');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewName = document.getElementById('imagePreviewName');
    const imagePreviewPath = document.getElementById('imagePreviewPath');
    
    if (!hiddenInput || !imagePreviewContainer) return;
    
    const selectedImagePath = hiddenInput.value;
    
    if (selectedImagePath) {
        // Show preview - handle image path correctly
        const previewSrc = selectedImagePath.startsWith('/images/items/') || selectedImagePath.startsWith('images/items/') 
            ? selectedImagePath 
            : `/images/items/${selectedImagePath}`;
        imagePreview.src = previewSrc;
        imagePreview.onerror = () => {
            imagePreview.style.display = 'none';
            imagePreview.parentElement.innerHTML = '<div class="u-width-100 u-height-100 u-display-flex u-flex-direction-column u-align-items-center u-justify-content-center u-background-f8f9fa u-color-6c757d u-border-radius-8px"><div class="u-font-size-2rem u-margin-bottom-0-5rem u-opacity-0-7">📷</div><div class="u-font-size-0-8rem u-font-weight-500">No Image Available</div></div>';
        };
        
        imagePreviewName.textContent = selectedImagePath;
        imagePreviewPath.textContent = previewSrc;
        
        imagePreviewContainer.classList.remove('hidden');
    } else {
        // Hide preview
        imagePreviewContainer.classList.add('hidden');
    }
}

// Highlight selected image in the grid
function highlightSelectedImageInGrid(selectedPath) {
    const gridContainer = document.querySelector('#availableImagesGrid .grid');
    if (!gridContainer) return;
    
    const imageContainers = gridContainer.querySelectorAll('div[onclick]');
    imageContainers.forEach(container => {
        const img = container.querySelector('img');
        if (img) {
            const imagePath = img.alt;
            if (selectedPath && imagePath === selectedPath) {
                container.classList.add('ring-2', 'ring-green-500', 'bg-green-50');
                img.classList.add('border-green-400');
            } else {
                container.classList.remove('ring-2', 'ring-green-500', 'bg-green-50');
                img.classList.remove('border-green-400');
            }
        }
    });
}

// Load global colors for selection dropdown
async function loadGlobalColorsForSelection() {
    try {
        const response = await fetch('/api/global_color_size_management.php?action=get_global_colors');
        const data = await response.json();
        
        const globalColorSelect = document.getElementById('globalColorSelect');
        if (!globalColorSelect) return;
        
        // Clear existing options except the first one
        globalColorSelect.innerHTML = '<option value="">Choose a color...</option>';
        
        if (data.success && data.colors && data.colors.length > 0) {
            // Group colors by category
            const colorsByCategory = {};
            data.colors.forEach(color => {
                const category = color.category || 'General';
                if (!colorsByCategory[category]) {
                    colorsByCategory[category] = [];
                }
                colorsByCategory[category].push(color);
            });
            
            // Add colors grouped by category
            Object.keys(colorsByCategory).sort().forEach(category => {
                const optgroup = document.createElement('optgroup');
                optgroup.label = category;
                
                colorsByCategory[category].forEach(color => {
                    const option = document.createElement('option');
                    option.value = JSON.stringify({
                        id: color.id,
                        name: color.color_name,
                        code: color.color_code,
                        category: color.category
                    });
                    option.textContent = `${color.color_name} ${color.color_code ? '(' + color.color_code + ')' : ''}`;
                    optgroup.appendChild(option);
                });
                
                globalColorSelect.appendChild(optgroup);
            });
        } else {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No global colors available - add some in Settings';
            option.disabled = true;
            globalColorSelect.appendChild(option);
        }
    } catch (error) {
        console.error('Error loading global colors:', error);
        showError('Error loading global colors');
    }
}

// Handle global color selection
function handleGlobalColorSelection() {
    const globalColorSelect = document.getElementById('globalColorSelect');
    const selectedValue = globalColorSelect.value;
    
    const colorNameInput = document.getElementById('colorName');
    const colorCodeInput = document.getElementById('colorCode');
    const selectedColorPreview = document.getElementById('selectedColorPreview');
    const colorPreviewSwatch = document.getElementById('colorPreviewSwatch');
    const colorPreviewName = document.getElementById('colorPreviewName');
    const colorPreviewCode = document.getElementById('colorPreviewCode');
    
    if (selectedValue) {
        try {
            const colorData = JSON.parse(selectedValue);
            
            // Populate hidden fields
            colorNameInput.value = colorData.name;
            colorCodeInput.value = colorData.code || '#000000';
            
            // Show color preview
            selectedColorPreview.classList.remove('hidden');
            colorPreviewSwatch.style.backgroundColor = colorData.code || '#000000';
            colorPreviewName.textContent = colorData.name;
            colorPreviewCode.textContent = colorData.code || 'No color code';
            
        } catch (error) {
            console.error('Error parsing color data:', error);
        }
    } else {
        // Clear fields and hide preview
        colorNameInput.value = '';
        colorCodeInput.value = '';
        selectedColorPreview.classList.add('hidden');
    }
}

// Open global colors management (redirect to settings)
function openGlobalColorsManagement() {
    // Show info modal about managing colors in settings
    if (confirm('Global colors are managed in Admin Settings > Content Management > Global Colors & Sizes.\n\nWould you like to open the Admin Settings page?')) {
        window.location.href = '/?page=admin&section=settings';
    }
}

// Close color modal
function closeColorModal() {
    const modal = document.getElementById('colorModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Save color
async function saveColor(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const colorData = {
        item_sku: currentItemSku,
        color_name: formData.get('colorName'),
        color_code: formData.get('colorCode'),
        image_path: formData.get('colorImagePath') || '',
        stock_level: parseInt(formData.get('stockLevel')) || 0,
        display_order: parseInt(formData.get('displayOrder')) || 0,
        is_active: formData.get('isActive') ? 1 : 0
    };
    
    const colorId = formData.get('colorId');
    const isEdit = colorId && colorId !== '';
    
    if (isEdit) {
        colorData.color_id = parseInt(colorId);
    }
    
    try {
        const response = await fetch(`/api/item_colors.php?action=${isEdit ? 'update_color' : 'add_color'}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(colorData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess( `Color ${isEdit ? 'updated' : 'added'} successfully${data.new_total_stock ? ` - Total stock: ${data.new_total_stock}` : ''}`);
            closeColorModal();
            loadItemColors(); // Reload colors
            
            // Update the stock level field if it exists
            const stockField = document.getElementById('stockLevel');
            if (stockField && data.new_total_stock !== undefined) {
                stockField.value = data.new_total_stock;
            }
        } else {
            showError( `Error ${isEdit ? 'updating' : 'adding'} color: ` + data.message);
        }
    } catch (error) {
        console.error('Error saving color:', error);
        showError( `Error ${isEdit ? 'updating' : 'adding'} color`);
    }
}

// Sync stock levels manually
async function syncStockLevels() {
    if (!currentItemSku) {
        showError( 'No item selected');
        return;
    }
    
    try {
        const response = await fetch(`/api/item_colors.php?action=sync_stock`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_sku: currentItemSku
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess( `Stock synchronized - Total: ${data.new_total_stock}`);
            
            // Update the stock level field if it exists
            const stockField = document.getElementById('stockLevel');
            if (stockField && data.new_total_stock !== undefined) {
                stockField.value = data.new_total_stock;
            }
            
            // Reload colors to show updated information
            loadItemColors();
        } else {
            showError( `Error syncing stock: ${data.message}`);
        }
    } catch (error) {
        console.error('Error syncing stock:', error);
        showError( 'Error syncing stock levels');
    }
}

// Load colors when modal opens
document.addEventListener('DOMContentLoaded', function() {// Load colors when in edit mode and we have a valid SKU
    if ((modalMode === 'edit' || modalMode === 'view') && currentItemSku) {setTimeout(loadItemColors, 500); // Small delay to ensure elements are ready
        
        // Check if structure analysis should be shown (only in edit mode)
        if (modalMode === 'edit') {
            setTimeout(checkAndShowStructureAnalysis, 600);
        }
    } else if (document.getElementById('sku') || document.getElementById('skuDisplay')) {
        // Fallback: try to get SKU from form fields
        const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
        if (skuField && skuField.value) {
            currentItemSku = skuField.value;setTimeout(loadItemColors, 500);
            
            // Check if structure analysis should be shown
            if (modalMode === 'edit') {
                setTimeout(checkAndShowStructureAnalysis, 600);
            }
        }
    }
});

// Size Management Functions
let currentSizeConfiguration = 'none'; // Track current size configuration mode

// Update size configuration based on radio button selection
function updateSizeConfiguration() {
    const selectedConfig = document.querySelector('input[name="sizeConfiguration"]:checked').value;
    currentSizeConfiguration = selectedConfig;
    
    const sizeTypeSelector = document.getElementById('sizeTypeSelector');
    const sizesSection = document.getElementById('sizesList');
    
    if (selectedConfig === 'none') {
        // Hide size management completely
        sizeTypeSelector.classList.add('hidden');
        sizesSection.innerHTML = '<div class="text-center text-gray-500 text-sm">No sizes configured for this item</div>';
    } else if (selectedConfig === 'general') {
        // Show general sizes (not color-specific)
        sizeTypeSelector.classList.add('hidden');
        loadItemSizes('general');
    } else if (selectedConfig === 'color_specific') {
        // Show color selector and load color-specific sizes
        sizeTypeSelector.classList.remove('hidden');
        loadColorOptions();
        loadItemSizes();
    }
}

// Load available colors for the size color filter
async function loadColorOptions() {
    if (!currentItemSku) return;
    
    try {
        const response = await fetch(`/api/item_colors.php?action=get_all_colors&item_sku=${currentItemSku}`);
        const data = await response.json();
        
        const colorFilter = document.getElementById('sizeColorFilter');
        if (!colorFilter) return;
        
        // Clear existing options except the first one
        colorFilter.innerHTML = '<option value="general">General Sizes (No Color)</option>';
        
        if (data.success && data.colors && data.colors.length > 0) {
            data.colors.forEach(color => {
                if (color.is_active == 1) { // Only show active colors
                    const option = document.createElement('option');
                    option.value = color.id;
                    option.textContent = `${color.color_name} (${color.stock_level} in stock)`;
                    colorFilter.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Error loading colors for size filter:', error);
    }
}

// Load sizes for current item
async function loadItemSizes(colorId = null) {
    if (!currentItemSku) {return;
    }
    
    // Determine which color to load sizes for
    let targetColorId = colorId;
    if (targetColorId === null) {
        const colorFilter = document.getElementById('sizeColorFilter');
        if (colorFilter) {
            targetColorId = colorFilter.value;
        }
    }
    
    try {
        let url = `/api/item_sizes.php?action=get_all_sizes&item_sku=${currentItemSku}`;
        if (targetColorId && targetColorId !== 'general') {
            url += `&color_id=${targetColorId}`;
        } else if (targetColorId === 'general') {
            url += '&color_id=0'; // Explicitly request general sizes
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {renderSizes(data.sizes);
        } else {
            console.error('Error loading sizes:', data.message);
            renderSizes([]);
        }
    } catch (error) {
        console.error('Error fetching sizes:', error);
        renderSizes([]);
    }
}

// Render sizes list
function renderSizes(sizes) {
    const sizesList = document.getElementById('sizesList');
    const sizesLoading = document.getElementById('sizesLoading');
    
    if (sizesLoading) {
        sizesLoading.style.display = 'none';
    }
    
    if (!sizesList) return;
    
    if (sizes.length === 0) {
        sizesList.innerHTML = '<div class="text-center text-gray-500 text-sm">No sizes defined. Click "Add Size" to get started.</div>';
        return;
    }
    
    // Group sizes by color if they have color associations
    const groupedSizes = {};
    sizes.forEach(size => {
        const key = size.color_id ? `color_${size.color_id}` : 'general';
        if (!groupedSizes[key]) {
            groupedSizes[key] = {
                color_name: size.color_name || 'General Sizes',
                color_code: size.color_code || null,
                sizes: []
            };
        }
        groupedSizes[key].sizes.push(size);
    });
    
    let html = '';
    
    // Calculate total stock from all sizes
    const totalSizeStock = sizes.reduce((sum, s) => sum + parseInt(s.stock_level || 0), 0);
    
    // Get current item stock level
    const stockField = document.getElementById('stockLevel');
    const currentItemStock = stockField ? parseInt(stockField.value || 0) : 0;
    
    // Check if stock is in sync
    const isInSync = totalSizeStock === currentItemStock;
    
    // Add sync status indicator if there are sizes
    if (sizes.length > 0) {
        const syncClass = isInSync ? 'bg-green-50 border-green-200 text-green-800' : 'bg-yellow-50 border-yellow-200 text-yellow-800';
        const syncIcon = isInSync ? '✅' : '⚠️';
        const syncMessage = isInSync ? 
            `Stock synchronized (${totalSizeStock} total)` : 
            `Stock out of sync! Sizes total: ${totalSizeStock}, Item stock: ${currentItemStock}`;
        
        html += `
            <div class="border rounded-lg ${syncClass}">
                <div class="text-sm font-medium">${syncIcon} ${syncMessage}</div>
                ${!isInSync ? '<div class="text-xs">Click "Sync Stock" to fix this.</div>' : ''}
            </div>
        `;
    }
    
    // Render each group
    Object.keys(groupedSizes).forEach(groupKey => {
        const group = groupedSizes[groupKey];
        
        // Add group header if there are multiple groups
        if (Object.keys(groupedSizes).length > 1) {
            html += `
                <div class="font-medium text-gray-700 flex items-center">
                    ${group.color_code ? `<div class="w-4 h-4 rounded border" ></div>` : ''}
                    ${group.color_name}
                </div>
            `;
        }
        
        // Render sizes in this group
        group.sizes.forEach(size => {
            const isActive = size.is_active == 1;
            const activeClass = isActive ? 'bg-white' : 'bg-gray-100 opacity-75';
            const activeText = isActive ? '' : ' (Inactive)';
            const priceAdjustmentText = size.price_adjustment > 0 ? ` (+$${size.price_adjustment})` : '';
            
            html += `
                <div class="size-item flex items-center justify-between border border-gray-200 rounded-lg ${activeClass} ml-${Object.keys(groupedSizes).length > 1 ? '4' : '0'}">
                    <div class="flex items-center space-x-3">
                        <div class="size-badge bg-blue-100 text-blue-800 rounded text-sm font-medium">
                            ${size.size_code}
                        </div>
                        <div>
                            <div class="font-medium text-gray-800">${size.size_name}${activeText}${priceAdjustmentText}</div>
                            <div class="text-sm text-gray-500 flex items-center">
                                <span class="inline-stock-editor" 
                                      data-type="size" 
                                      data-id="${size.id}" 
                                      data-field="stock_level" 
                                      data-value="${size.stock_level}"
                                      data-action="edit-inline-stock"
                                      title="Click to edit stock level">
                                    ${size.stock_level}
                                </span>
                                <span class="">in stock</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button type="button" data-action="delete-size" data-id="${size.id}" class="bg-red-500 text-white rounded text-xs hover:bg-red-600">
                            Delete
                        </button>
                    </div>
                </div>
            `;
        });
    });
    
    sizesList.innerHTML = html;
}

// Add new size
function addNewSize() {
    showSizeModal();
}

// Edit existing size
async function editSize(sizeId) {
    try {
        const response = await fetch(`/api/item_sizes.php?action=get_all_sizes&item_sku=${currentItemSku}`);
        const data = await response.json();
        
        if (data.success) {
            const size = data.sizes.find(s => s.id == sizeId);
            if (size) {
                showSizeModal(size);
            }
        }
    } catch (error) {
        console.error('Error fetching size for edit:', error);
    }
}

// Delete size
async function deleteSize(sizeId) {
    const confirmResult = await showStyledConfirm(
        'Delete Size',
        'Are you sure you want to delete this size? This action cannot be undone.',
        'Delete',
        'Cancel'
    );
    
    if (!confirmResult) return;
    
    try {
        const response = await fetch('/api/item_sizes.php?action=delete_size', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ size_id: sizeId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Size deleted successfully');
            loadItemSizes(); // Reload sizes
            
            // Update stock field if provided
            if (data.new_total_stock !== undefined) {
                const stockField = document.getElementById('stockLevel');
                if (stockField) {
                    stockField.value = data.new_total_stock;
                }
            }
        } else {
            showError('Error deleting size: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting size:', error);
        showError('Error deleting size');
    }
}

// Show size modal
function showSizeModal(size = null) {
    // Create modal if it doesn't exist
    if (!document.getElementById('sizeModal')) {
        createSizeModal();
    }
    
    const modal = document.getElementById('sizeModal');
    const form = document.getElementById('sizeForm');
    const modalTitle = document.getElementById('sizeModalTitle');
    
    // Reset form
    form.reset();
    
    if (size) {
        // Edit mode
        modalTitle.textContent = 'Edit Size';
        document.getElementById('sizeId').value = size.id;
        document.getElementById('sizeName').value = size.size_name;
        document.getElementById('sizeCode').value = size.size_code;
        document.getElementById('sizeStockLevel').value = size.stock_level;
        document.getElementById('sizePriceAdjustment').value = size.price_adjustment;
        document.getElementById('sizeDisplayOrder').value = size.display_order;
        document.getElementById('sizeIsActive').checked = size.is_active == 1;
        
        // Set color if it exists
        if (size.color_id) {
            const colorSelect = document.getElementById('sizeColorId');
            if (colorSelect) {
                colorSelect.value = size.color_id;
            }
        }
    } else {
        // Add mode
        modalTitle.textContent = 'Add New Size';
        document.getElementById('sizeId').value = '';
        document.getElementById('sizePriceAdjustment').value = '0.00';
        document.getElementById('sizeDisplayOrder').value = '0';
        document.getElementById('sizeIsActive').checked = true;
        
        // Set default color based on current filter
        const colorFilter = document.getElementById('sizeColorFilter');
        const colorSelect = document.getElementById('sizeColorId');
        if (colorFilter && colorSelect) {
            colorSelect.value = colorFilter.value === 'general' ? '' : colorFilter.value;
        }
    }
    
    modal.classList.remove('hidden');
}

// Create size modal
function createSizeModal() {
    const modalHTML = `
        <div id="sizeModal" class="modal-overlay hidden">
            <div class="modal-content" >
                <div class="modal-header">
                    <h2 id="sizeModalTitle" class="text-xl font-semibold text-gray-800">Add New Size</h2>
                    <button type="button" data-action="close-size-modal" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="sizeForm">
                        <input type="hidden" id="sizeId" name="sizeId">
                        
                        <div class="">
                            <label for="sizeColorId" class="block text-sm font-medium text-gray-700">Color Association</label>
                            <select id="sizeColorId" name="sizeColorId" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2" >
                                <option value="">General Size (No specific color)</option>
                            </select>
                            <div class="text-xs text-gray-500">Choose a color if this size is specific to a particular color variant</div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sizeName" class="block text-sm font-medium text-gray-700">Size Name *</label>
                                <input type="text" id="sizeName" name="sizeName" placeholder="e.g., Medium" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2"  required>
                            </div>
                            <div>
                                <label for="sizeCode" class="block text-sm font-medium text-gray-700">Size Code *</label>
                                <select id="sizeCode" name="sizeCode" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2"  required>
                                    <option value="">Select size...</option>
                                    <option value="XS">XS</option>
                                    <option value="S">S</option>
                                    <option value="M">M</option>
                                    <option value="L">L</option>
                                    <option value="XL">XL</option>
                                    <option value="XXL">XXL</option>
                                    <option value="XXXL">XXXL</option>
                                    <option value="OS">OS (One Size)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sizeStockLevel" class="block text-sm font-medium text-gray-700">Stock Level</label>
                                <input type="number" id="sizeStockLevel" name="sizeStockLevel" min="0" value="0" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2" >
                            </div>
                            <div>
                                <label for="sizePriceAdjustment" class="block text-sm font-medium text-gray-700">Price Adjustment ($)</label>
                                <input type="number" id="sizePriceAdjustment" name="sizePriceAdjustment" step="0.01" value="0.00" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2" >
                                <div class="text-xs text-gray-500">Extra charge for this size (e.g., +$2 for XXL)</div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sizeDisplayOrder" class="block text-sm font-medium text-gray-700">Display Order</label>
                                <input type="number" id="sizeDisplayOrder" name="sizeDisplayOrder" min="0" value="0" class="w-full border border-gray-300 rounded-md focus:outline-none focus:ring-2" >
                                <div class="text-xs text-gray-500">Lower numbers appear first</div>
                            </div>
                            <div class="flex items-center">
                                <label class="flex items-center">
                                    <input type="checkbox" id="sizeIsActive" name="sizeIsActive" class="">
                                    <span class="text-sm font-medium text-gray-700">Active (available to customers)</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 border-t border-gray-200">
                            <button type="button" data-action="close-size-modal" class="bg-gray-300 text-gray-800 rounded hover:bg-gray-400 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="text-white rounded transition-colors bg-[#87ac3a] hover:bg-[#6b8e23]">
                                Save Size
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Load colors for the color selector
    loadSizeModalColors();
}

// Load colors for size modal
async function loadSizeModalColors() {
    if (!currentItemSku) return;
    
    try {
        const response = await fetch(`/api/item_colors.php?action=get_all_colors&item_sku=${currentItemSku}`);
        const data = await response.json();
        const colorSelect = document.getElementById('sizeColorId');
        if (!colorSelect) return;

        // Clear existing options except the first one
        colorSelect.innerHTML = '<option value="">General Size (No specific color)</option>';

        if (data.success && data.colors && data.colors.length > 0) {
            data.colors.forEach(color => {
                if (color.is_active == 1) {
                    const option = document.createElement('option');
                    option.value = color.id;
                    option.textContent = `${color.color_name}`;
                    colorSelect.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Error loading colors for size modal:', error);
    }
}

// Close size modal
function closeSizeModal() {
    const modal = document.getElementById('sizeModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Save size
async function saveSize(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const sizeData = {
        item_sku: currentItemSku,
        color_id: formData.get('sizeColorId') || null,
        size_name: formData.get('sizeName'),
        size_code: formData.get('sizeCode'),
        stock_level: parseInt(formData.get('sizeStockLevel')) || 0,
        price_adjustment: parseFloat(formData.get('sizePriceAdjustment')) || 0.00,
        display_order: parseInt(formData.get('sizeDisplayOrder')) || 0,
        is_active: formData.get('sizeIsActive') ? 1 : 0
    };
    
    const sizeId = formData.get('sizeId');
    const isEdit = sizeId && sizeId !== '';
    
    if (isEdit) {
        sizeData.size_id = parseInt(sizeId);
    }
    
    try {
        const response = await fetch(`/api/item_sizes.php?action=${isEdit ? 'update_size' : 'add_size'}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(sizeData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(`Size ${isEdit ? 'updated' : 'added'} successfully${data.new_total_stock ? ` - Total stock: ${data.new_total_stock}` : ''}`);
            closeSizeModal();
            loadItemSizes(); // Reload sizes
            
            // Update the stock level field if it exists
            const stockField = document.getElementById('stockLevel');
            if (stockField && data.new_total_stock !== undefined) {
                stockField.value = data.new_total_stock;
            }
        } else {
            showError(`Error ${isEdit ? 'updating' : 'adding'} size: ` + data.message);
        }
    } catch (error) {
        console.error('Error saving size:', error);
        showError(`Error ${isEdit ? 'updating' : 'adding'} size`);
    }
}

// Sync size stock levels manually
async function syncSizeStock() {
    if (!currentItemSku) {
        showError('No item selected');
        return;
    }
    
    try {
        const response = await fetch(`/api/item_sizes.php?action=sync_stock`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_sku: currentItemSku
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(`Stock synchronized - Total: ${data.new_total_stock}`);
            
            // Update the stock level field if it exists
            const stockField = document.getElementById('stockLevel');
            if (stockField && data.new_total_stock !== undefined) {
                stockField.value = data.new_total_stock;
            }
            
            // Reload sizes to show updated information
            loadItemSizes();
        } else {
            showError(`Error syncing stock: ${data.message}`);
        }
    } catch (error) {
        console.error('Error syncing stock:', error);
        showError('Error syncing stock levels');
    }
}

// Initialize size management when modal opens
function initializeSizeManagement() {
    if (!currentItemSku) return;
    
    // Load sizes to determine configuration
    loadItemSizes();
    
    // Load colors for color-specific mode
    if (currentSizeConfiguration === 'color_specific') {
        loadColorOptions();
    }
}

// Helper function to get current size data
async function getCurrentSizeData(sizeId) {
    try {
        const response = await fetch(`/api/item_sizes.php?action=get_all_sizes&item_sku=${currentItemSku}`);
        const data = await response.json();
        
        if (data.success && data.sizes) {
            return data.sizes.find(size => size.id == sizeId);
        }
        return null;
    } catch (error) {
        console.error('Error fetching size data:', error);
        return null;
    }
}

// ========== STRUCTURE ANALYSIS & REDESIGN FUNCTIONS ==========

// Analyze current size/color structure
async function analyzeStructure() {
    if (!currentItemSku) {
        showError('Please save the item first');
        return;
    }
    
    try {
        const response = await fetch('/api/redesign_size_color_system.php?action=analyze_current_structure&item_sku=' + encodeURIComponent(currentItemSku) + '&admin_token=whimsical_admin_2024');
        const data = await response.json();
        
        if (data.success) {
            const analysis = data.analysis;
            const resultDiv = document.getElementById('structureAnalysisResult');
            
            let html = `
                <div class="bg-white border border-yellow-300 rounded-lg">
                    <h4 class="font-medium text-gray-800">📊 Analysis Results:</h4>
                    <div class="text-sm space-y-1">
                        <div><strong>Colors:</strong> ${analysis.total_colors}</div>
                        <div><strong>Sizes:</strong> ${analysis.total_sizes}</div>
                    </div>
            `;
            
            if (analysis.structure_issues.length > 0) {
                html += `
                    <div class="">
                        <h5 class="font-medium text-red-700">⚠️ Issues Found:</h5>
                        <ul class="text-sm text-red-600 space-y-1">
                            ${analysis.structure_issues.map(issue => `<li>• ${issue}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
            
            if (analysis.recommendations.length > 0) {
                html += `
                    <div class="">
                        <h5 class="font-medium text-blue-700">💡 Recommendations:</h5>
                        <ul class="text-sm text-blue-600 space-y-1">
                            ${analysis.recommendations.map(rec => `<li>• ${rec}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
            
            html += '</div>';
            resultDiv.innerHTML = html;
            
        } else {
            showError('Error analyzing structure: ' + data.message);
        }
    } catch (error) {
        console.error('Error analyzing structure:', error);
        showError('Error analyzing structure');
    }
}

// Show restructure modal
async function showRestructureModal() {
    if (!currentItemSku) {
        showError('Please save the item first');
        return;
    }
    
    try {
        const response = await fetch('/api/redesign_size_color_system.php?action=propose_new_structure&item_sku=' + encodeURIComponent(currentItemSku) + '&admin_token=whimsical_admin_2024');
        const data = await response.json();
        
        if (data.success) {
            createRestructureModal(data);
        } else {
            showError('Error getting proposal: ' + data.message);
        }
    } catch (error) {
        console.error('Error getting proposal:', error);
        showError('Error getting proposal');
    }
}

// Create restructure modal
function createRestructureModal(proposal) {
    // Remove existing modal if it exists
    const existingModal = document.getElementById('restructureModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Extract data from the CORRECT API response structure
    const proposedSizes = proposal.proposedSizes || [];
    const allColors = proposal.allColors || [];
    const totalCombinations = proposal.totalCombinations || 0;
    const message = proposal.message || 'Structure analysis complete';
    
    const modal = document.createElement('div');
    modal.id = 'restructureModal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Fixed Header -->
            <div class="border-b border-gray-200 flex-shrink-0">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">✅ Current Size/Color Structure</h2>
                    <button type="button" data-action="close-restructure-modal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Scrollable Content -->
            <div class="overflow-y-auto flex-1" >
                
                
                <!-- Summary Section -->
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-b">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">✅ Hierarchy is CORRECT</h3>
                        <div class="text-sm text-gray-600">
                            <span class="bg-white rounded shadow-sm">${proposedSizes.length} sizes × ${allColors.length} colors = ${totalCombinations} combinations</span>
                        </div>
                    </div>
                    <p class="text-sm text-gray-700">${message}</p>
                    
                    <!-- Structure Info -->
                    <div class="bg-white bg-opacity-70 rounded-lg">
                        <div class="text-sm">
                            <strong>Hierarchy:</strong> Item → Sizes → Colors → Stock<br>
                            <strong>Structure:</strong> Each size contains multiple colors as options
                        </div>
                    </div>
                </div>
                
                <!-- Content Sections -->
                <div class="">
                    <!-- Size-Color Combinations -->
                    <div>
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-gray-700 flex items-center">
                                📦 Size-Color Combinations
                                <span class="text-sm text-gray-500">${totalCombinations} active combinations</span>
                            </h4>
                        </div>
                        
                        <div class="space-y-3" id="sizeColorCombinations">
                            ${proposedSizes.map((size, sizeIndex) => `
                                <div class="border border-gray-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2">
                                            <span class="bg-blue-100 text-blue-800 rounded text-xs font-medium">${size.code}</span>
                                            <span class="text-sm font-semibold">${size.name}</span>
                                            ${size.price_adjustment > 0 ? `<span class="text-green-600 text-xs">+$${parseFloat(size.price_adjustment).toFixed(2)}</span>` : ''}
                                        </div>
                                        <span class="text-xs text-gray-500">${size.colors ? size.colors.length : 0} colors, ${size.stock || 0} total stock</span>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                        ${(size.colors || []).map((color, colorIndex) => `
                                            <div class="flex items-center justify-between bg-gray-50 rounded border">
                                                <div class="flex items-center space-x-2">
                                                    <div class="w-3 h-3 rounded-full border border-gray-300" ></div>
                                                    <span class="text-xs font-medium">${color.color_name}</span>
                                                </div>
                                                <span class="text-xs text-gray-600">${color.stock_level}</span>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        
                        <div class="text-xs text-gray-500 bg-yellow-50 rounded">
                            💡 Each number represents the stock level for that specific size-color combination (e.g., "5" = 5 Small Red T-shirts)
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Fixed Footer -->
            <div class="border-t border-gray-200 flex-shrink-0 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        <span class="bg-white rounded shadow-sm">✅ Structure is correct: Sizes → Colors → Stock</span>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" data-action="close-restructure-modal" class="bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Utility functions for the new interface
function setAllStock(value) {
    document.querySelectorAll('.stock-input').forEach(input => {
        const sizeCheckbox = input.closest('.border').querySelector('.size-checkbox');
        const colorCheckbox = input.closest('.size-color-combo').querySelector('.color-checkbox');
        if (sizeCheckbox && sizeCheckbox.checked && colorCheckbox && colorCheckbox.checked) {
            input.value = value;
        }
    });
    updateCombinations();
}

function updateCombinations() {
    let totalCombinations = 0;
    let totalStock = 0;
    
    // Update each size total and overall totals
    document.querySelectorAll('.size-checkbox').forEach(sizeCheckbox => {
        const sizeIndex = sizeCheckbox.dataset.sizeIndex;
        const sizeTotal = document.getElementById(`sizeTotal_${sizeIndex}`);
        const isChecked = sizeCheckbox.checked;
        
        let sizeStockTotal = 0;
        let sizeColorCount = 0;
        
        // Update colors for this size
        const colorsContainer = document.getElementById(`colorsFor_${sizeIndex}`);
        if (colorsContainer) {
            colorsContainer.style.opacity = isChecked ? '1' : '0.5';
            
            colorsContainer.querySelectorAll('.color-checkbox').forEach(colorCheckbox => {
                const colorCombo = colorCheckbox.closest('.size-color-combo');
                const stockInput = colorCombo.querySelector('.stock-input');
                
                colorCheckbox.disabled = !isChecked;
                stockInput.disabled = !isChecked || !colorCheckbox.checked;
                
                if (isChecked && colorCheckbox.checked) {
                    const stock = parseInt(stockInput.value) || 0;
                    sizeStockTotal += stock;
                    sizeColorCount++;
                    totalCombinations++;
                    totalStock += stock;
                }
                
                // Visual feedback for disabled combinations
                colorCombo.style.opacity = (!isChecked || !colorCheckbox.checked) ? '0.5' : '1';
            });
        }
        
        if (sizeTotal) {
            sizeTotal.textContent = `${sizeColorCount} colors, ${sizeStockTotal} total stock`;
        }
    });
    
    // Update header count
    const combinationCount = document.getElementById('combinationCount');
    if (combinationCount) {
        combinationCount.textContent = `(${totalCombinations} active combinations)`;
    }
    
    // Update footer summary
    const summaryElement = document.getElementById('restructureSummary');
    const applyButton = document.getElementById('applyRestructureBtn');
    
    if (summaryElement && applyButton) {
        if (totalCombinations === 0) {
            summaryElement.textContent = 'Select at least 1 size and 1 color to continue';
            summaryElement.className = 'text-sm text-red-600';
            applyButton.disabled = true;
            applyButton.className = 'px-4 py-2 bg-gray-400 text-gray-200 rounded cursor-not-allowed text-sm font-medium';
        } else {
            summaryElement.textContent = `Will create ${totalCombinations} size-color combinations with ${totalStock} total stock`;
            summaryElement.className = 'text-sm text-gray-600';
            applyButton.disabled = false;
            applyButton.className = 'px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm font-medium';
        }
    }
}

// Apply restructure
async function applyRestructure() {
    if (!window.currentProposal || !currentItemSku) {
        showError('Missing proposal data');
        return;
    }
    
    const proposal = window.currentProposal;
    
    // Build new structure from the size-color combinations
    const newStructure = [];
    
    document.querySelectorAll('.size-checkbox:checked').forEach(sizeCheckbox => {
        const sizeIndex = parseInt(sizeCheckbox.getAttribute('data-size-index'));
        const size = proposal.proposed_sizes[sizeIndex];
        
        if (!size) return;
        
        const colors = [];
        
        // Get all color combinations for this size
        document.querySelectorAll(`.color-checkbox[data-size-index="${sizeIndex}"]:checked`).forEach(colorCheckbox => {
            const colorIndex = parseInt(colorCheckbox.getAttribute('data-color-index'));
            const color = proposal.proposed_colors[colorIndex];
            const stockInput = document.querySelector(`.stock-input[data-size-index="${sizeIndex}"][data-color-index="${colorIndex}"]`);
            const stockLevel = parseInt(stockInput.value) || 0;
            
            if (color) {
                colors.push({
                    color_name: color.color_name,
                    color_code: color.color_code,
                    stock_level: stockLevel
                });
            }
        });
        
        if (colors.length > 0) {
            newStructure.push({
                size_name: size.size_name,
                size_code: size.size_code,
                price_adjustment: size.price_adjustment,
                colors: colors
            });
        }
    });
    
    if (newStructure.length === 0) {
        showError('Please select at least one size and one color combination');
        return;
    }
    
    try {
        const response = await fetch('/api/redesign_size_color_system.php?action=migrate_to_new_structure', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_sku: currentItemSku,
                new_structure: newStructure,
                preserve_stock: true
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(`Structure restructured successfully! Created ${data.structure_created}. New total stock: ${data.new_total_stock}`);
            closeRestructureModal();
            
            // Reload colors and sizes
            loadItemColors();
            loadItemSizes();
            
            // Update main stock field
            const stockField = document.getElementById('stockLevel');
            if (stockField) {
                stockField.value = data.new_total_stock;
            }
            
        } else {
            showError('Error applying restructure: ' + data.message);
        }
    } catch (error) {
        console.error('Error applying restructure:', error);
        showError('Error applying restructure: ' + error.message);
    }
}

// Close restructure modal
function closeRestructureModal() {
    const modal = document.getElementById('restructureModal');
    if (modal) {
        modal.remove();
    }
    window.currentProposal = null;
}

// Show new structure view
async function showNewStructureView() {
    if (!currentItemSku) {
        showError('Please save the item first');
        return;
    }
    
    try {
        const response = await fetch('/api/redesign_size_color_system.php?action=get_restructured_view&item_sku=' + encodeURIComponent(currentItemSku) + '&admin_token=whimsical_admin_2024');
        const data = await response.json();
        
        if (data.success) {
            createStructureViewModal(data);
        } else {
            showError('Error getting structure view: ' + data.message);
        }
    } catch (error) {
        console.error('Error getting structure view:', error);
        showError('Error getting structure view');
    }
}

// Create structure view modal
function createStructureViewModal(data) {
    // Remove existing modal if it exists
    const existingModal = document.getElementById('structureViewModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    const modal = document.createElement('div');
    modal.id = 'structureViewModal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Fixed Header -->
            <div class="border-b border-gray-200 flex-shrink-0">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">👀 Current Structure View</h2>
                    <button type="button" data-action="close-structure-view-modal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Scrollable Content -->
            <div class="overflow-y-auto flex-1" >
                
                
                <div class="">
                    <div class="text-sm text-gray-600">
                        <strong>Item:</strong> ${data.item_sku} | <strong>Total Combinations:</strong> ${data.total_combinations}
                    </div>
                </div>
                
                ${data.structure.length === 0 ? `
                    <div class="text-center text-gray-500">
                        <p>No properly structured size-color combinations found.</p>
                        <p class="">Use the "Restructure System" button to create a logical structure.</p>
                    </div>
                ` : `
                    <div class="space-y-4">
                        ${data.structure.map(size => `
                            <div class="border border-gray-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                        <span class="bg-blue-100 text-blue-800 rounded text-sm font-medium">${size.size_code}</span>
                                        ${size.size_name}
                                        ${size.price_adjustment > 0 ? `<span class="text-green-600 text-sm">+$${size.price_adjustment}</span>` : ''}
                                    </h3>
                                    <div class="text-sm text-gray-600">
                                        <strong>Total Stock:</strong> ${size.total_stock}
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                    ${size.colors.map(color => `
                                        <div class="flex items-center justify-between bg-gray-50 rounded border">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-4 h-4 rounded-full border border-gray-300" ></div>
                                                <span class="text-sm font-medium">${color.color_name}</span>
                                            </div>
                                            <span class="text-sm text-gray-600">${color.stock_level} stock</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `}
            </div>
            
            <!-- Fixed Footer -->
            <div class="border-t border-gray-200 flex-shrink-0">
                <div class="flex justify-center">
                    <button type="button" data-action="close-structure-view-modal" class="bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Close structure view modal
function closeStructureViewModal() {
    const modal = document.getElementById('structureViewModal');
    if (modal) {
        modal.remove();
    }
}

// Check if structure analysis should be shown (only for backwards structures)
async function checkAndShowStructureAnalysis() {
    if (!currentItemSku) {return;
    }
    
    try {
        const response = await fetch('/api/redesign_size_color_system.php?action=check_if_backwards&item_sku=' + encodeURIComponent(currentItemSku) + '&admin_token=whimsical_admin_2024');
        const data = await response.json();
        
        if (data.success && data.is_backwards) {const analysisSection = document.getElementById('structureAnalysisSection');
            if (analysisSection) {
                analysisSection.style.display = 'block';
            }
        } else {const analysisSection = document.getElementById('structureAnalysisSection');
            if (analysisSection) {
                analysisSection.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error checking structure backwards status:', error);
        // Hide analysis section on error
        const analysisSection = document.getElementById('structureAnalysisSection');
        if (analysisSection) {
            analysisSection.style.display = 'none';
        }
    }
}

-->



<?php
$output = ob_get_clean();
echo $output;
?>
<?php
// sections/admin_inventory.php — Primary implementation for Inventory (phase 1)

// Load config and helpers
require_once dirname(__DIR__) . '/api/config.php';

// Get database instance
$pdo = Database::getInstance();

// Initialize state
$editItem = null;
$editCostBreakdown = null;
$field_errors = $_SESSION['field_errors'] ?? [];
unset($_SESSION['field_errors']);

// Modal detection (kept for later phases)
$modalMode = match(true) {
    isset($_GET['view']) && !empty($_GET['view']) => 'view',
    isset($_GET['edit']) && !empty($_GET['edit']) => 'edit',
    isset($_GET['add']) && $_GET['add'] == 1 => 'add',
    default => ''
};

// Preload edit item when needed
if ($modalMode === 'view' || $modalMode === 'edit') {
    $editItem = Database::queryOne("SELECT * FROM items WHERE sku = ?", [$_GET[$modalMode]]) ?: null;
} elseif ($modalMode === 'add') {
    $lastSku = Database::queryOne("SELECT sku FROM items WHERE sku LIKE 'WF-GEN-%' ORDER BY sku DESC LIMIT 1");
    $lastNum = $lastSku ? (int)substr($lastSku['sku'], -3) : 0;
    $editItem = ['sku' => 'WF-GEN-' . str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT)];
}

// Cost breakdown temporarily disabled during SKU migration
$editCostBreakdown = null;

// Categories for dropdown
$categories = array_column(
    Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category"),
    'category'
) ?? [];

// Filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'category' => $_GET['category'] ?? '',
    'stock' => $_GET['stock'] ?? ''
];

// Build WHERE
$whereConditions = ['1=1'];
$queryParams = [];

if (!empty($filters['search'])) {
    $whereConditions[] = "(i.name LIKE :search OR i.sku LIKE :search OR i.description LIKE :search)";
    $queryParams[':search'] = '%' . $filters['search'] . '%';
}
if (!empty($filters['category'])) {
    $whereConditions[] = "i.category = :category";
    $queryParams[':category'] = $filters['category'];
}
if (!empty($filters['stock'])) {
    $stockCondition = match($filters['stock']) {
        'low' => "i.stockLevel <= i.reorderPoint AND i.stockLevel > 0",
        'out' => "i.stockLevel = 0",
        'in' => "i.stockLevel > 0",
        default => "1=1"
    };
    $whereConditions[] = $stockCondition;
}

// Pagination
$perPage = 50;
$currentPage = isset($_GET['pageNum']) ? max(1, (int)$_GET['pageNum']) : 1;
$offset = ($currentPage - 1) * $perPage;

$countSql = "SELECT COUNT(*) AS cnt FROM items i WHERE " . implode(' AND ', $whereConditions);
$countRow = Database::queryOne($countSql, $queryParams);
$totalRecords = (int)($countRow['cnt'] ?? 0);
$totalPages = (int)ceil($totalRecords / $perPage);

$sql = "SELECT i.*, COALESCE(img_count.image_count, 0) as image_count 
        FROM items i 
        LEFT JOIN (
            SELECT sku, COUNT(*) as image_count 
            FROM item_images 
            GROUP BY sku
        ) img_count ON i.sku = img_count.sku 
        WHERE " . implode(' AND ', $whereConditions) . " 
        ORDER BY i.sku ASC
        LIMIT " . intval($perPage) . " OFFSET " . intval($offset);

$items = Database::queryAll($sql, $queryParams);

// Messages
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
                        <td><?= htmlspecialchars($item['sku'] ?? '') ?></td>
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

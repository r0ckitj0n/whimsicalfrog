<?php
// sections/admin_inventory.php — Primary implementation for Inventory (phase 1)

// Load config and helpers
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/vite_helper.php';

// Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include dirname(__DIR__) . '/partials/header.php';
    if (!function_exists('__wf_admin_inventory_footer_shutdown')) {
        function __wf_admin_inventory_footer_shutdown()
        {
            @include __DIR__ . '/../partials/footer.php';
        }
    }
    register_shutdown_function('__wf_admin_inventory_footer_shutdown');
}

// Always include admin navbar on inventory page, even when accessed directly
$section = 'inventory';
include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';

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
    $openValRaw = (string)($_GET[$modalMode] ?? '');
    // Defensive: if user appended debug_nav=1 without '&', the value can look like WF-AR-001debug_nav=1
    if (($p = stripos($openValRaw, 'debug_nav')) !== false) {
        $openValRaw = substr($openValRaw, 0, $p);
    }
    $openVal = trim($openValRaw);
    $editItem = Database::queryOne("SELECT * FROM items WHERE sku = ?", [$openVal]) ?: null;
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

// Sorting (whitelisted)
$sortBy = isset($_GET['sort']) ? strtolower((string)$_GET['sort']) : 'sku';
$sortDir = isset($_GET['dir']) ? strtolower((string)$_GET['dir']) : 'asc';
$validDir = ($sortDir === 'desc') ? 'DESC' : 'ASC';
$sortMap = [
    'name' => 'i.name',
    'category' => 'i.category',
    'sku' => 'i.sku',
    'stock' => 'i.stockLevel',
    'reorder' => 'i.reorderPoint',
    'cost' => 'i.costPrice',
    'retail' => 'i.retailPrice',
    'images' => 'image_count',
];
$orderColumn = $sortMap[$sortBy] ?? 'i.sku';
$orderClause = $orderColumn . ' ' . $validDir . ', i.sku ASC';

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

// No pagination - display all items
$sql = "SELECT i.*, COALESCE(img_count.image_count, 0) as image_count 
        FROM items i 
        LEFT JOIN (
            SELECT sku, COUNT(*) as image_count 
            FROM item_images 
            GROUP BY sku
        ) img_count ON i.sku = img_count.sku 
        WHERE " . implode(' AND ', $whereConditions) . " 
        ORDER BY " . $orderClause;

$items = Database::queryAll($sql, $queryParams);

// Preload primary images for all items in the current result set
$primaryImages = [];
if (!empty($items)) {
    $skus = array_values(array_filter(array_map(static function ($item) {
        return $item['sku'] ?? null;
    }, $items)));

    if (!empty($skus)) {
        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $imageRows = Database::queryAll(
            "SELECT sku, image_path, alt_text, is_primary, sort_order, id
             FROM item_images
             WHERE sku IN ($placeholders)
             ORDER BY sku ASC, is_primary DESC, sort_order ASC, id ASC",
            $skus
        );

        foreach ($imageRows as $row) {
            $sku = $row['sku'] ?? null;
            if (!$sku || isset($primaryImages[$sku])) {
                continue;
            }

            $width = null;
            $height = null;
            $fileSize = null;
            $imagePath = $row['image_path'] ?? null;
            if ($imagePath) {
                $fullPath = dirname(__DIR__) . '/' . ltrim($imagePath, '/');
                if (is_file($fullPath)) {
                    $dimensions = @getimagesize($fullPath);
                    if (is_array($dimensions)) {
                        $width = $dimensions[0] ?? null;
                        $height = $dimensions[1] ?? null;
                    }
                    $fileSize = @filesize($fullPath);
                }
            }

            $primaryImages[$sku] = [
                'image_path' => $imagePath,
                'alt_text' => $row['alt_text'] ?? null,
                'is_primary' => (bool)($row['is_primary'] ?? false),
                'sort_order' => (int)($row['sort_order'] ?? 0),
                'width' => $width ? (int)$width : null,
                'height' => $height ? (int)$height : null,
                'file_size' => $fileSize !== false ? ($fileSize !== null ? (int)$fileSize : null) : null
            ];
        }
    }

    foreach ($items as &$item) {
        $sku = $item['sku'] ?? null;
        $item['primary_image'] = $sku && isset($primaryImages[$sku]) ? $primaryImages[$sku] : null;
    }
    unset($item);
}

// Messages
$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

// Helpers for sortable header links
function invSortUrl($column, $currentSort, $currentDir) {
    $newDir = ($column === $currentSort && $currentDir === 'asc') ? 'desc' : 'asc';
    $queryParams = $_GET;
    $queryParams['sort'] = $column;
    $queryParams['dir'] = $newDir;
    // Reset to first page when sorting changes
    unset($queryParams['pageNum']);
    // Remove modal/view/edit params
    unset($queryParams['view'], $queryParams['edit'], $queryParams['add']);
    return '/admin/inventory?' . http_build_query($queryParams);
}
function invSortIndicator($column, $currentSort, $currentDir) {
    if ($column !== $currentSort) return '';
    return ($currentDir === 'asc') ? '↑' : '↓';
}
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
                    <th>Image</th>
                    <th>
                        <a href="<?= invSortUrl('images', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='images' ? ' is-active' : '' ?>">Images <?= invSortIndicator('images', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= invSortUrl('name', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='name' ? ' is-active' : '' ?>">Name <?= invSortIndicator('name', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= invSortUrl('category', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='category' ? ' is-active' : '' ?>">Category <?= invSortIndicator('category', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= invSortUrl('sku', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='sku' ? ' is-active' : '' ?>">SKU <?= invSortIndicator('sku', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= invSortUrl('stock', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='stock' ? ' is-active' : '' ?>">Stock <?= invSortIndicator('stock', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= invSortUrl('reorder', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='reorder' ? ' is-active' : '' ?>">Reorder Point <?= invSortIndicator('reorder', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= invSortUrl('cost', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='cost' ? ' is-active' : '' ?>">Cost Price <?= invSortIndicator('cost', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>
                        <a href="<?= invSortUrl('retail', $sortBy, $sortDir) ?>" class="table-sort-link<?= $sortBy==='retail' ? ' is-active' : '' ?>">Retail Price <?= invSortIndicator('retail', $sortBy, $sortDir) ?></a>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="10" class="text-center">No items found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php 
                        $linkBase = $_GET; unset($linkBase['view'], $linkBase['edit'], $linkBase['add']);
                        foreach ($items as $item): ?>
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
                            <div class="admin-actions">
                                <?php $viewHref = '/admin/inventory?' . http_build_query(array_merge($linkBase, ['view' => ($item['sku'] ?? '')])); ?>
                                <?php $editHref = '/admin/inventory?' . http_build_query(array_merge($linkBase, ['edit' => ($item['sku'] ?? '')])); ?>
                                <a href="<?= htmlspecialchars($viewHref) ?>" class="text-blue-600 hover:text-blue-800" title="View Item">👁️</a>
                                <a href="<?= htmlspecialchars($editHref) ?>" class="text-green-600 hover:text-green-800" title="Edit Item">✏️</a>
                                <button data-action="delete-item" class="text-red-600 hover:text-red-800" data-sku="<?= htmlspecialchars($item['sku'] ?? '') ?>" title="Delete Item">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php
// Option A: Server-rendered Admin Item Editor
// Treat both view and edit modes as editor render to avoid client modal and header redirects after output.
if ($modalMode === 'view' || $modalMode === 'edit' || $modalMode === 'add') {
    // Compute prev/next SKUs from the full items array that's displayed in the table
    $prevSku = null;
    $nextSku = null;
    if ($editItem) {
        $currentSku = (string)($editItem['sku'] ?? '');
        $rawSkus = array_values(array_map(static function ($row) { return (string)($row['sku'] ?? ''); }, $items));
        $norm = static function ($v) { return strtolower(trim((string)$v)); };
        $skuListNorm = array_map($norm, $rawSkus);
        $idx = array_search($norm($currentSku), $skuListNorm, true);
        if ($idx === false) {
            // Fallback: prefer sanitized $openVal, else fallback to raw query params
            $openKey = ($openVal !== '') ? $openVal : ($_GET['view'] ?? ($_GET['edit'] ?? null));
            if ($openKey !== null && $openKey !== '') {
                $altIdx = array_search($norm($openKey), $skuListNorm, true);
                if ($altIdx !== false) { $idx = $altIdx; }
            }
        }
        $n = count($rawSkus);
        if ($idx !== false && $n > 0) {
            $prevSku = $rawSkus[(($idx - 1 + $n) % $n)];
            $nextSku = $rawSkus[(($idx + 1) % $n)];
        }
        // Removed temporary debug panel output
    }

    require_once dirname(__DIR__) . '/components/admin_item_editor.php';
    // Render the editor UI (adapted from archived implementation)
    renderAdminItemEditor($modalMode, $editItem, $categories, $field_errors ?? [], $prevSku, $nextSku);
}
?>


</div>

<!-- Data for JavaScript -->
<script type="application/json" id="inventory-data">
<?= json_encode([
    'categories' => $categories,
    'items' => $items ?? [],
    'currentItemSku' => $editItem['sku'] ?? null,
    'modalMode' => $modalMode ?? null
]) ?>
</script>

<?php echo vite_entry('src/entries/admin-inventory.js'); ?>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="delete-modal">
    <div class="delete-modal-content">
        <h2 class="delete-modal-title">Confirm Delete</h2>
        <p class="delete-modal-message" id="modal-message">
            Are you sure you want to delete this inventory item? This action cannot be undone.
        </p>
        <div class="delete-modal-actions">
            <button type="button" class="btn btn-secondary" data-action="close-delete-modal">Cancel</button>
            <button type="button" class="btn btn-danger" data-action="confirm-delete-item">Delete</button>
        </div>
    </div>
</div>

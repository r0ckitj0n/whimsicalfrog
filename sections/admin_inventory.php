<?php
// Admin Inventory Management Section
ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// The authentication check is now handled by index.php before including this file

// Include database configuration
require_once __DIR__ . '/../api/config.php';

// Database connection
$pdo = new PDO($dsn, $user, $pass, $options);

// Get items
$stmt = $pdo->query("SELECT * FROM items ORDER BY sku");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize modal state
$modalMode = ''; // Default to no modal unless 'add', 'edit', or 'view' is in URL
$editItem = null;
$editCostBreakdown = null;
$field_errors = $_SESSION['field_errors'] ?? []; // For highlighting fields with errors
unset($_SESSION['field_errors']);


// Check if we're in view mode
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $itemIdToView = $_GET['view'];
    $stmt = $pdo->prepare("SELECT * FROM items WHERE sku = ?");
    $stmt->execute([$itemIdToView]);
    $fetchedViewItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fetchedViewItem) {
        $modalMode = 'view';
        $editItem = $fetchedViewItem; // Reuse editItem for view mode

        // Get cost breakdown data (temporarily disabled during SKU migration)
        $editCostBreakdown = null;
    }
}
// Check if we're in edit mode
elseif (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $itemIdToEdit = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM items WHERE sku = ?");
    $stmt->execute([$itemIdToEdit]);
    $fetchedEditItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fetchedEditItem) {
        $modalMode = 'edit';
        $editItem = $fetchedEditItem; 

        // Get cost breakdown data (temporarily disabled during SKU migration)
        $editCostBreakdown = null;
    }
} elseif (isset($_GET['add']) && $_GET['add'] == 1) {
    $modalMode = 'add';
    // Generate next SKU for new item
    $stmtSku = $pdo->query("SELECT sku FROM items WHERE sku LIKE 'WF-GEN-%' ORDER BY sku DESC LIMIT 1");
    $lastSkuRow = $stmtSku->fetch(PDO::FETCH_ASSOC);
    $lastSkuNum = $lastSkuRow ? (int)substr($lastSkuRow['sku'], -3) : 0;
    $nextSku = 'WF-GEN-' . str_pad($lastSkuNum + 1, 3, '0', STR_PAD_LEFT);
    
    $editItem = ['sku' => $nextSku];
}

// Get categories for dropdown from items table
$stmt = $pdo->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!is_array($categories)) {
    $categories = [];
}

// Search and filter logic
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? '';

// Modified query to include image count
$sql = "SELECT i.*, COALESCE(img_count.image_count, 0) as image_count 
        FROM items i 
        LEFT JOIN (
            SELECT sku, COUNT(*) as image_count 
            FROM item_images 
            GROUP BY sku
        ) img_count ON i.sku = img_count.sku 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (i.name LIKE :search OR i.sku LIKE :search OR i.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($categoryFilter)) {
    $sql .= " AND i.category = :category";
    $params[':category'] = $categoryFilter;
}
if (!empty($stockFilter)) {
    if ($stockFilter === 'low') {
        $sql .= " AND i.stockLevel <= i.reorderPoint AND i.stockLevel > 0";
    } elseif ($stockFilter === 'out') {
        $sql .= " AND i.stockLevel = 0";
    } elseif ($stockFilter === 'in') {
        $sql .= " AND i.stockLevel > 0";
    }
}
$sql .= " ORDER BY i.sku ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    .inventory-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; table-layout: fixed; }
    .inventory-table th { background-color: #87ac3a; color: white; padding: 10px 12px; text-align: left; font-weight: 600; font-size: 0.8rem; position: sticky; top: 0; z-index: 10; }
    .inventory-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 0.85rem; overflow: hidden; text-overflow: ellipsis; }
    .inventory-table tr:hover { background-color: #f7fafc; }
    .inventory-table th:first-child { border-top-left-radius: 6px; }
    .inventory-table th:last-child { border-top-right-radius: 6px; }
    
    /* Fixed column widths to prevent resizing during inline editing */
    .inventory-table th:nth-child(1), .inventory-table td:nth-child(1) { width: 60px; } /* Image */
    .inventory-table th:nth-child(2), .inventory-table td:nth-child(2) { width: 180px; } /* Name */
    .inventory-table th:nth-child(3), .inventory-table td:nth-child(3) { width: 120px; } /* Category */
    .inventory-table th:nth-child(4), .inventory-table td:nth-child(4) { width: 100px; } /* SKU */
    .inventory-table th:nth-child(5), .inventory-table td:nth-child(5) { width: 80px; } /* Stock */
    .inventory-table th:nth-child(6), .inventory-table td:nth-child(6) { width: 90px; } /* Reorder Point */
    .inventory-table th:nth-child(7), .inventory-table td:nth-child(7) { width: 90px; } /* Cost Price */
    .inventory-table th:nth-child(8), .inventory-table td:nth-child(8) { width: 90px; } /* Retail Price */
    .inventory-table th:nth-child(9), .inventory-table td:nth-child(9) { width: 70px; } /* Images */
    .inventory-table th:nth-child(10), .inventory-table td:nth-child(10) { width: 120px; } /* Actions */
    
    /* Responsive adjustments for smaller screens */
    @media (max-width: 1200px) {
        .inventory-table { table-layout: auto; }
        .inventory-table th, .inventory-table td { width: auto !important; min-width: 60px; }
        .inventory-table th:nth-child(2), .inventory-table td:nth-child(2) { min-width: 120px; } /* Name */
        .inventory-table th:nth-child(5), .inventory-table td:nth-child(5) { min-width: 60px; } /* Stock */
        .inventory-table th:nth-child(6), .inventory-table td:nth-child(6) { min-width: 70px; } /* Reorder Point */
    }

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
    .cost-item-actions { display: flex; align-items: center; margin-left: 6px; gap: 4px; }
.delete-cost-btn { 
    background: #f56565; 
    color: white; 
    border: none; 
    border-radius: 3px; 
    width: 18px; 
    height: 18px; 
    font-size: 12px; 
    cursor: pointer; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    transition: background-color 0.2s;
}
.delete-cost-btn:hover { background: #e53e3e; }

/* Friendly Delete Cost Dialog */
.delete-cost-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.2s ease-out;
}

.delete-cost-modal {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    max-width: 400px;
    width: 90%;
    animation: slideIn 0.3s ease-out;
}

.delete-cost-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid #e5e7eb;
}

.delete-cost-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 8px;
}

.delete-cost-body {
    padding: 20px 24px;
}

.delete-cost-body p {
    margin: 0 0 12px 0;
    color: #374151;
    line-height: 1.5;
}

.delete-cost-note {
    font-size: 0.9rem;
    color: #6b7280;
    font-style: italic;
}

.delete-cost-actions {
    padding: 16px 24px 20px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.delete-cost-cancel {
    padding: 8px 16px;
    border: 1px solid #d1d5db;
    background: white;
    color: #374151;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.delete-cost-cancel:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.delete-cost-confirm {
    padding: 8px 16px;
    border: none;
    background: #ef4444;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s;
}

.delete-cost-confirm:hover {
    background: #dc2626;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { 
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to { 
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
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
    .modal-content-wrapper { background-color: white; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); padding: 1.25rem; width: 100%; max-width: calc(80rem + 10px); /* Increased max-width for wider modal */ max-height: calc(90vh + 10px); display: flex; flex-direction: column; }
    .modal-form-container { flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column; padding-right: 0.5rem; /* For scrollbar */ }
    @media (min-width: 768px) { .modal-form-container { flex-direction: row; } }
    .modal-form-main-column { flex: 1; padding-right: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; /* Reduced gap */ }
    @media (max-width: 767px) { .modal-form-main-column { padding-right: 0; } }
    .modal-form-suggestions-column { width: 100%; padding-left: 0; margin-top: 1rem; display: flex; flex-direction: column; gap: 0.75rem; }
    @media (min-width: 768px) { .modal-form-suggestions-column { flex: 0 0 50%; padding-left: 0.75rem; margin-top: 0; } }
    
    /* Two-column layout for cost and price suggestions */
    .suggestions-container { display: flex; flex-direction: column; gap: 0.75rem; }
    @media (min-width: 1024px) { .suggestions-container { flex-direction: row; gap: 0.75rem; } }
    .cost-breakdown-wrapper, .price-suggestion-wrapper { flex: 1; }
    
    /* Legacy support for single cost column */
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

    .editable { position: relative; padding: 6px 8px; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; white-space: nowrap; }
    .editable:hover { background-color: #edf2f7; }
    .editable:hover::after { content: "✏️"; position: absolute; right: 5px; top: 50%; transform: translateY(-50%); font-size: 12px; opacity: 0.5; }
    .editing { padding: 2px !important; background-color: #ebf8ff !important; }
    .editing input, .editing select { 
        width: 100%; 
        padding: 4px 6px; 
        border: 1px solid #4299e1; 
        border-radius: 4px; 
        font-size: inherit; 
        font-family: inherit; 
        background-color: white;
        box-sizing: border-box;
        margin: 0;
        min-width: 0; /* Prevents input from expanding beyond container */
    }
    
    .loading-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; }
    .loading-spinner.dark { border: 2px solid rgba(0,0,0,0.1); border-top-color: #333; }
    .loading-spinner.hidden { display:none !important; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .field-error-highlight { border-color: #f56565 !important; box-shadow: 0 0 0 1px #f56565 !important; }

    .cost-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 100; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
    .cost-modal.show { opacity: 1; pointer-events: auto; }
    .cost-modal-content { background-color: white; border-radius: 8px; padding: 1rem; width: 100%; max-width: 380px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transform: scale(0.95); transition: transform 0.3s; }
    .cost-modal.show .cost-modal-content { transform: scale(1); }
    .cost-modal-content label { font-size: 0.8rem; }
    .cost-modal-content input { font-size: 0.85rem; padding: 0.4rem 0.6rem; }
    .cost-modal-content button { font-size: 0.85rem; padding: 0.4rem 0.8rem; }
    
    /* Enhanced image layout styles */
    .images-section-container.full-width-images {
        width: 100%;
        max-width: none;
    }
    
    .image-grid-container {
        width: 100%;
    }
    
    .image-item {
        position: relative;
        transition: transform 0.2s ease-in-out;
    }
    
    .image-item:hover {
        transform: translateY(-2px);
        z-index: 5;
    }
    
    /* Responsive image grid improvements */
    @media (max-width: 768px) {
        .image-grid-container .grid-cols-4 {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .image-grid-container .grid-cols-3 {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
    
    @media (max-width: 480px) {
        .image-grid-container .grid-cols-2,
        .image-grid-container .grid-cols-3,
        .image-grid-container .grid-cols-4 {
            grid-template-columns: 1fr !important;
        }
    }
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
            <button type="button" onclick="refreshCategoryDropdown().then(() => showToast('success', 'Categories refreshed!'))" class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded text-sm" title="Refresh Categories">🔄</button>
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
                    <th>Image</th><th>Name</th><th>Category</th><th>SKU</th><th>Stock</th>
                    <th>Reorder Point</th><th>Cost Price</th><th>Retail Price</th><th>Images</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="9" class="text-center py-4">No items found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr data-sku="<?= htmlspecialchars($item['sku'] ?? '') ?>" class="<?= (isset($_GET['highlight']) && $_GET['highlight'] == $item['sku']) ? 'bg-yellow-100' : '' ?> hover:bg-gray-50">
                        <td>
                            <div class="thumbnail-container" data-sku="<?= htmlspecialchars($item['sku'] ?? '') ?>" style="width:40px;height:40px;">
                                <div class="thumbnail-loading" style="width:40px;height:40px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#999;">...</div>
                            </div>
                        </td>
                                            <td class="editable" data-field="name"><?= htmlspecialchars($item['name'] ?? '') ?></td>
                    <td class="editable" data-field="category"><?= htmlspecialchars($item['category'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['sku'] ?? '') ?></td> <!-- SKU not typically inline editable -->
                    <td class="editable" data-field="stockLevel"><?= htmlspecialchars($item['stockLevel'] ?? '0') ?></td>
                    <td class="editable" data-field="reorderPoint"><?= htmlspecialchars($item['reorderPoint'] ?? '0') ?></td>
                        <td class="editable" data-field="costPrice">$<?= number_format(floatval($item['costPrice'] ?? 0), 2) ?></td>
                        <td class="editable" data-field="retailPrice">$<?= number_format(floatval($item['retailPrice'] ?? 0), 2) ?></td>
                        <td class="text-center">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= ($item['image_count'] > 0) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                <?= intval($item['image_count']) ?>
                            </span>
                        </td>
                        <td>
                                                    <a href="?page=admin&section=inventory&view=<?= htmlspecialchars($item['sku'] ?? '') ?>" class="action-btn view-btn" title="View Item">👁️</a>
                        <a href="?page=admin&section=inventory&edit=<?= htmlspecialchars($item['sku'] ?? '') ?>" class="action-btn edit-btn" title="Edit Item">✏️</a>
                        <button class="action-btn delete-btn delete-item" data-sku="<?= htmlspecialchars($item['sku'] ?? '') ?>" title="Delete Item">🗑️</button>
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

        <div class="modal-form-container gap-5">
            <div class="modal-form-main-column">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="skuDisplay" class="block text-gray-700">SKU</label>
                        <input type="text" id="skuDisplay" name="sku" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly 
                               value="<?= htmlspecialchars($editItem['sku'] ?? ''); ?>">
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
                                    <!-- Item Images Section - Same layout as edit modal -->
<div class="images-section-container" id="imagesSection">
                    
                    <!-- Current Images Display -->
                    <div id="currentImagesContainer" class="current-images-section">
                        <div class="text-sm text-gray-600 mb-2">Current Images:</div>
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
                            <div class="mb-4 p-2 bg-green-50 rounded border border-green-200">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-green-700 font-medium">Suggested Cost:</span>
                                    <span class="font-bold text-green-800 text-lg" id="suggestedCostDisplay">$0.00</span>
                                </div>
                            </div>
                            
                            <?php foreach (['materials', 'labor', 'energy', 'equipment'] as $costType): ?>
                            <div class="cost-breakdown-section <?= $costType !== 'materials' ? 'mt-3' : ''; ?>">
                                <h4 class="font-semibold text-gray-700 mb-1 text-sm"><?= ucfirst($costType); ?></h4>
                                <div class="mb-2" id="view_<?= $costType; ?>List" style="max-height: 100px; overflow-y: auto;">
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
                                        <p class="text-gray-500 text-xs italic px-1">No items added.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="cost-totals" style="display: none;">
                                <div class="cost-total-row" style="display: none;"><span class="cost-label">Materials Total:</span> <span class="cost-item-value" id="materialsTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row" style="display: none;"><span class="cost-label">Labor Total:</span> <span class="cost-item-value" id="laborTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row" style="display: none;"><span class="cost-label">Energy Total:</span> <span class="cost-item-value" id="energyTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row" style="display: none;"><span class="cost-label">Equipment Total:</span> <span class="cost-item-value" id="equipmentTotalDisplay">$0.00</span></div>
                            </div>
                        </div>
                    </div>
                
                    <!-- Price Suggestion Section for View Modal -->
                    <div class="price-suggestion-wrapper">
                        <div class="price-suggestion bg-white border border-gray-200 rounded-lg p-4">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                                <span class="mr-2">🎯</span> Price Suggestion
                            </h3>
                            
                            <button type="button" onclick="getViewModePriceSuggestion()" class="w-full px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors font-medium mb-4">
                                🎯 Get Suggested Price
                            </button>
                            
                            <!-- Price Suggestion Display -->
                            <div id="viewPriceSuggestionDisplay" class="mb-4 hidden">
                                <div class="flex items-start justify-between mb-3">
                                    <h4 class="font-medium text-gray-800 text-sm">💡 AI Price Analysis</h4>
                                    <button type="button" onclick="clearViewPriceSuggestion()" class="text-gray-600 hover:text-gray-800 text-xs">×</button>
                                </div>
                                
                                <!-- Suggested Price Display -->
                                <div class="mb-3 p-2 bg-green-50 rounded border border-green-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-green-700 font-medium">Suggested Price:</span>
                                        <span class="font-bold text-green-800 text-lg" id="viewDisplaySuggestedPrice">$0.00</span>
                                    </div>
                                </div>
                                
                                <!-- Reasoning Section -->
                                <div class="mb-3">
                                    <h4 class="font-semibold text-gray-700 mb-1 text-sm">AI Reasoning</h4>
                                    <div class="mb-2" id="viewReasoningList">
                                        <!-- Reasoning items will be rendered here by JavaScript -->
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center text-xs mb-3">
                                    <span class="text-green-600" id="viewDisplayConfidence">Medium confidence</span>
                                    <span class="text-green-500" id="viewDisplayTimestamp">Just now</span>
                                </div>
                            </div>
                            
                            <!-- Price Suggestion Placeholder -->
                            <div id="viewPriceSuggestionPlaceholder" class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                <div class="text-center text-gray-500">
                                    <div class="text-2xl mb-1">🎯</div>
                                    <div class="text-sm">No price suggestion yet</div>
                                    <div class="text-xs mt-1 text-gray-400">Click "Get Suggested Price" above to get AI pricing analysis</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-auto pt-4 border-t">
            <a href="?page=admin&section=inventory" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block text-sm">Close</a>
                            <a href="?page=admin&section=inventory&edit=<?= htmlspecialchars($editItem['sku'] ?? '') ?>" class="brand-button px-4 py-2 rounded text-sm">Edit Item</a>
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

        <form id="inventoryForm" method="POST" action="#" enctype="multipart/form-data" class="flex flex-col flex-grow overflow-hidden">
            <input type="hidden" name="action" value="<?= $modalMode === 'add' ? 'add' : 'update'; ?>">
            <?php if ($modalMode === 'edit' && isset($editItem['sku'])): ?>
                <input type="hidden" name="itemSku" value="<?= htmlspecialchars($editItem['sku'] ?? ''); ?>">
            <?php endif; ?>

            <div class="modal-form-container gap-5">
                <div class="modal-form-main-column">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="skuEdit" class="block text-gray-700">SKU *</label>
                            <input type="text" id="skuEdit" name="sku" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('sku', $field_errors) ? 'field-error-highlight' : '' ?>" required 
                                   value="<?= htmlspecialchars($editItem['sku'] ?? ($nextSku ?? '')); ?>" placeholder="Auto-generated if empty">
                        </div>
                        <div>
                            <label for="name" class="block text-gray-700">Name *</label>
                            <input type="text" id="name" name="name" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('name', $field_errors) ? 'field-error-highlight' : '' ?>" required 
                                   value="<?= htmlspecialchars($editItem['name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="categoryEdit" class="block text-gray-700">Category *</label>
                            <select id="categoryEdit" name="category" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('category', $field_errors) ? 'field-error-highlight' : '' ?>" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat); ?>" <?= (isset($editItem['category']) && $editItem['category'] === $cat) ? 'selected' : ''; ?>><?= htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" onclick="openMarketingManager()" class="brand-button px-3 py-2 rounded text-sm">
                                🎯 Marketing Manager
                            </button>
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
                        <textarea id="description" name="description" class="mt-1 block w-full p-2 border border-gray-300 rounded" rows="3" placeholder="Enter product description or click 'Marketing Manager' for AI-powered suggestions..."><?= htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
                    </div>
                    <!-- Item Images Section - Now spans full width when needed -->
                    <div class="images-section-container" id="imagesSection">
                        
                        <!-- Current Images Display -->
                        <div id="currentImagesContainer" class="current-images-section">
                            <div class="text-sm text-gray-600 mb-2">Current Images:</div>
                            <div id="currentImagesList" class="w-full">
                                <!-- Current images will be loaded here with dynamic layout -->
                            </div>
                        </div>
                        
                        <!-- Multi-Image Upload Section - Only show in edit/add mode -->
                        <div class="multi-image-upload-section mt-3" style="<?= $modalMode === 'view' ? 'display: none;' : '' ?>">
                            <input type="file" id="multiImageUpload" name="images[]" multiple accept="image/*" class="hidden">
                            <div class="upload-controls mb-3">
                                <div class="flex gap-2 flex-wrap">
                                    <button type="button" onclick="document.getElementById('multiImageUpload').click()" class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                                        📁 Upload Images
                                    </button>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Maximum file size: 10MB per image. Supported formats: PNG, JPG, JPEG, WebP, GIF
                                </div>
                                <div id="uploadProgress" class="mt-2 hidden">
                                    <div class="text-sm text-gray-600 mb-2">Uploading images...</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div id="uploadProgressBar" class="bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Legacy support for existing image -->
                        <?php if (!empty($editItem['imageUrl'])): ?>
                            <input type="hidden" name="existingImageUrl" value="<?= htmlspecialchars($editItem['imageUrl']); ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-form-suggestions-column">
                    <div class="suggestions-container">
                        <!-- Cost Breakdown Section -->
                        <div class="cost-breakdown-wrapper">
                            <div class="cost-breakdown">
                                <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                                    <span class="mr-2">💰</span> Cost Breakdown
                                </h3>
                                
                                <button type="button" onclick="useSuggestedCost()" class="w-full px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors mb-4">
                                    🧮 Get Suggested Cost
                                </button>
                                
                                <!-- Suggested Cost Display - Moved to top with price styling -->
                                <div class="mb-4 p-2 bg-green-50 rounded border border-green-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-green-700 font-medium">Suggested Cost:</span>
                                        <span class="font-bold text-green-800 text-lg" id="suggestedCostDisplay">$0.00</span>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <button type="button" onclick="applyCostSuggestionToCost()" class="w-full px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors">
                                        💰 Apply Costs to Cost Field
                                    </button>
                                </div>
                                
                                <!-- Template Selection Section -->
                                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-medium text-blue-800 text-sm">📋 Cost Templates</h4>
                                        <button type="button" onclick="toggleTemplateSection()" class="text-blue-600 hover:text-blue-800 text-xs">
                                            <span id="templateToggleText">Show Templates</span>
                                        </button>
                                    </div>
                                    
                                    <div id="templateSection" class="hidden space-y-3">
                                        <!-- Load Template -->
                                        <div class="flex gap-2">
                                            <select id="templateSelect" class="flex-1 px-2 py-1 border border-blue-300 rounded text-xs">
                                                <option value="">Choose a template...</option>
                                            </select>
                                            <button type="button" onclick="loadTemplate()" class="px-2 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700">
                                                Load
                                            </button>
                                        </div>
                                        
                                        <!-- Save Template -->
                                        <div class="flex gap-2">
                                            <input type="text" id="templateName" placeholder="Template name..." class="flex-1 px-2 py-1 border border-blue-300 rounded text-xs">
                                            <button type="button" onclick="saveAsTemplate()" class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                                Save as Template
                                            </button>
                                        </div>
                                        
                                        <div class="text-xs text-blue-600">
                                            💡 Load existing templates or save current breakdown as a reusable template
                                        </div>
                                    </div>
                                </div>
                                
                                <?php foreach (['materials', 'labor', 'energy', 'equipment'] as $costType): ?>
                                <div class="cost-breakdown-section <?= $costType !== 'materials' ? 'mt-3' : ''; ?>">
                                    <h4 class="font-semibold text-gray-700 mb-1 text-sm"><?= ucfirst($costType); ?></h4>
                                    <div class="mb-2" id="<?= $costType; ?>List" style="max-height: 100px; overflow-y: auto;">
                                        <!-- Cost items will be rendered here by JavaScript -->
                                    </div>
                                    <button type="button" class="add-cost-btn" onclick="addCostItem('<?= $costType; ?>')">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 mr-1"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                                        Add <?php 
                                            $labels = ['materials' => 'Material', 'labor' => 'Labor', 'energy' => 'Energy', 'equipment' => 'Equipment'];
                                            echo $labels[$costType] ?? ucfirst($costType);
                                        ?>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                                                            <div class="cost-totals" style="display: none;">
                                <div class="cost-total-row" style="display: none;"><span class="cost-label">Materials Total:</span> <span class="cost-item-value" id="materialsTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row" style="display: none;"><span class="cost-label">Labor Total:</span> <span class="cost-item-value" id="laborTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row" style="display: none;"><span class="cost-label">Energy Total:</span> <span class="cost-item-value" id="energyTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row" style="display: none;"><span class="cost-label">Equipment Total:</span> <span class="cost-item-value" id="equipmentTotalDisplay">$0.00</span></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Price Suggestion Section -->
                        <div class="price-suggestion-wrapper">
                            <div class="price-suggestion bg-white border border-gray-200 rounded-lg p-4">
                                <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                                    <span class="mr-2">🎯</span> Price Suggestion
                                </h3>
                                
                                <button type="button" onclick="useSuggestedPrice()" class="w-full px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors font-medium mb-4">
                                    🎯 Get Suggested Price
                                </button>
                                
                                <!-- Price Suggestion Display -->
                                <div id="priceSuggestionDisplay" class="mb-4 hidden">
                                    <div class="flex items-start justify-between mb-3">
                                        <h4 class="font-medium text-gray-800 text-sm">💡 AI Price Analysis</h4>
                                        <button type="button" onclick="clearPriceSuggestion()" class="text-gray-600 hover:text-gray-800 text-xs">×</button>
                                    </div>
                                    
                                    <!-- Suggested Price Display -->
                                    <div class="mb-3 p-2 bg-green-50 rounded border border-green-200">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-green-700 font-medium">Suggested Price:</span>
                                            <span class="font-bold text-green-800 text-lg" id="displaySuggestedPrice">$0.00</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Reasoning Section -->
                                    <div class="mb-3">
                                        <h4 class="font-semibold text-gray-700 mb-1 text-sm">AI Reasoning</h4>
                                        <div class="mb-2" id="reasoningList">
                                            <!-- Reasoning items will be rendered here by JavaScript -->
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between items-center text-xs mb-3">
                                        <span class="text-green-600" id="displayConfidence">Medium confidence</span>
                                        <span class="text-green-500" id="displayTimestamp">Just now</span>
                                    </div>
                                    
                                    <button type="button" onclick="applyPriceSuggestion()" class="w-full px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors">
                                        Apply to Retail Price
                                    </button>
                                </div>
                                
                                <!-- Price Suggestion Placeholder -->
                                <div id="priceSuggestionPlaceholder" class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                    <div class="text-center text-gray-500">
                                        <div class="text-2xl mb-1">🎯</div>
                                        <div class="text-sm">No price suggestion yet</div>
                                        <div class="text-xs mt-1 text-gray-400">Click "Get Suggested Price" above to get AI pricing analysis</div>
                                    </div>
                                </div>
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

<!-- Cost Item Delete Confirmation Modal -->
<div id="deleteCostConfirmModal" class="cost-modal">
    <div class="cost-modal-content max-w-sm">
        <h2 class="text-md font-bold mb-3 text-red-600">Delete Cost Item</h2>
        <p class="mb-4 text-sm text-gray-600" id="deleteCostConfirmText">Are you sure you want to delete this cost item? This action cannot be undone.</p>
        <div class="flex justify-end space-x-2">
            <button type="button" class="px-3 py-1.5 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm" onclick="closeCostDeleteModal()">Cancel</button>
            <button type="button" id="confirmCostDeleteBtn" class="px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                <span class="button-text">Delete</span>
                <span class="loading-spinner hidden">⏳</span>
            </button>
        </div>
    </div>
</div>


<script>
// Initialize variables
var modalMode = <?= json_encode($modalMode ?? '') ?>;
        var currentItemSku = <?= json_encode(isset($editItem['sku']) ? $editItem['sku'] : '') ?>;
var costBreakdown = <?= ($modalMode === 'edit' && isset($editCostBreakdown) && $editCostBreakdown) ? json_encode($editCostBreakdown) : 'null' ?>;

// Initialize global categories array
window.inventoryCategories = <?= json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?> || [];

// Define image management functions first
function setPrimaryImage(sku, imageId) {
    console.log('setPrimaryImage called with:', sku, imageId);
    fetch('/api/set_primary_image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            sku: sku,
            imageId: imageId
        })
    })
    .then(response => {
        console.log('Primary response status:', response.status);
        return response.text(); // Get as text first to see what we're getting
    })
    .then(text => {
        console.log('Primary response text:', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showToast('success', 'Primary image updated');
                loadCurrentImages(sku);
            } else {
                showToast('error', data.error || 'Failed to set primary image');
            }
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            showToast('error', 'Server returned invalid response: ' + text.substring(0, 100));
        }
    })
    .catch(error => {
        console.error('Error setting primary image:', error);
        showToast('error', 'Failed to set primary image');
    });
}

function deleteItemImage(imageId, sku) {
    console.log('deleteItemImage called with:', imageId, sku);
    
    // Show custom confirmation modal
    showImageDeleteConfirmation(imageId, sku);
}

function showImageDeleteConfirmation(imageId, sku) {
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    overlay.id = 'imageDeleteModal';
    
    // Create modal content
    overlay.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm mx-4">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-gray-900">Delete Image</h3>
                    <p class="text-sm text-gray-500">This action cannot be undone.</p>
                </div>
            </div>
                            <p class="text-gray-700 mb-6">Are you sure you want to delete this image? It will be permanently removed from the item.</p>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeImageDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="confirmImageDelete(${imageId}, '${sku}')" class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                    Delete Image
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    // Close on overlay click
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeImageDeleteModal();
        }
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageDeleteModal();
        }
    });
}

function closeImageDeleteModal() {
    const modal = document.getElementById('imageDeleteModal');
    if (modal) {
        modal.remove();
    }
}

function confirmImageDelete(imageId, sku) {
    console.log('Confirming delete for image:', imageId, sku);
    
    // Close the modal
    closeImageDeleteModal();
    
    // Proceed with deletion
    fetch('/api/delete_item_image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            imageId: imageId
        })
    })
    .then(response => {
        console.log('Delete response status:', response.status);
        return response.text(); // Get as text first to see what we're getting
    })
    .then(text => {
        console.log('Delete response text:', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showToast('success', 'Image deleted');
                loadCurrentImages(sku);
            } else {
                showToast('error', data.error || 'Failed to delete image');
            }
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            showToast('error', 'Server returned invalid response: ' + text.substring(0, 100));
        }
    })
    .catch(error => {
        console.error('Error deleting image:', error);
        showToast('error', 'Failed to delete image');
    });
}

// Make functions globally accessible immediately
window.setPrimaryImage = setPrimaryImage;
window.deleteItemImage = deleteItemImage;

// Debug function availability
console.log('Functions defined:', {
    setPrimaryImage: typeof window.setPrimaryImage,
    deleteItemImage: typeof window.deleteItemImage
});

// Add event delegation for image action buttons
document.addEventListener('click', function(e) {
    if (e.target.dataset.action === 'set-primary') {
        e.preventDefault();
        const sku = e.target.dataset.sku;
        const imageId = e.target.dataset.imageId;
        console.log('Event delegation - setPrimaryImage called with:', sku, imageId);
        setPrimaryImage(sku, imageId);
    } else if (e.target.dataset.action === 'delete-image') {
        e.preventDefault();
        const sku = e.target.dataset.sku;
        const imageId = e.target.dataset.imageId;
        console.log('Event delegation - deleteItemImage called with:', imageId, sku);
        deleteItemImage(imageId, sku);
    }
});

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
                        inventoryId: currentItemSku 
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
    
    // Show pretty confirmation modal instead of ugly browser confirm
    const itemName = (type === 'materials') ? 
        document.getElementById('costItemName').value : 
        document.getElementById('costItemDescription').value;
    
    const typeDisplay = type.slice(0, -1); // Remove 's' from end
    document.getElementById('deleteCostConfirmText').textContent = 
        `Are you sure you want to delete the ${typeDisplay} "${itemName}"? This action cannot be undone.`;
    
    // Store the deletion details for the confirm button
    window.pendingCostDeletion = { id, type };
    
    // Show the modal
    document.getElementById('deleteCostConfirmModal').classList.add('show');
}

function confirmCostDeletion() {
    if (!window.pendingCostDeletion) return;
    
    const { id, type } = window.pendingCostDeletion;
    const confirmBtn = document.getElementById('confirmCostDeleteBtn');
    const btnText = confirmBtn.querySelector('.button-text');
    const spinner = confirmBtn.querySelector('.loading-spinner');
    
    // Show loading state
    btnText.classList.add('hidden');
    spinner.classList.remove('hidden');
    confirmBtn.disabled = true;

            const url = `/process_cost_breakdown.php?id=${id}&costType=${type}&inventoryId=${currentItemSku}`;

    fetch(url, {
        method: 'DELETE',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            closeCostDeleteModal();
            closeCostModal();
            refreshCostBreakdown();
        } else {
            showToast('error', data.error || `Failed to delete ${type} cost`);
        }
    })
    .catch(error => {
        console.error('Error deleting cost item:', error);
        showToast('error', `Failed to delete ${type} cost. Check console for details.`);
    })
    .finally(() => {
        // Reset button state
        btnText.classList.remove('hidden');
        spinner.classList.add('hidden');
        confirmBtn.disabled = false;
        window.pendingCostDeletion = null;
    });
}

function closeCostDeleteModal() {
    document.getElementById('deleteCostConfirmModal').classList.remove('show');
    window.pendingCostDeletion = null;
}


let isRefreshingCostBreakdown = false; // Prevent multiple simultaneous calls

function refreshCostBreakdown(useExistingData = false) {
            if (!currentItemSku || isRefreshingCostBreakdown) return;
    
    if (useExistingData && costBreakdown) {
        renderCostBreakdown(costBreakdown);
        return;
    }
    
    isRefreshingCostBreakdown = true;
            fetch(`/process_cost_breakdown.php?inventoryId=${currentItemSku}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
    .then(response => response.json())
    .then(data => {
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
    })
    .finally(() => {
        isRefreshingCostBreakdown = false;
    });
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
                        <button type="button" class="delete-cost-btn" data-id="${item_cost.id}" data-type="${type}" title="Delete this cost item">×</button>
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

function showCostSuggestionChoiceDialog(suggestionData) {
    // Check if there are existing cost breakdown items
    const hasExistingCosts = checkForExistingCosts();
    
    // Create the modal overlay
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    modal.id = 'costSuggestionChoiceModal';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-blue-600 to-green-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    🧮 AI Cost Suggestion Ready
                </h2>
            </div>
            
            <div class="p-6">
                <!-- AI Analysis Summary -->
                <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <h3 class="font-semibold text-gray-800 mb-2 flex items-center">
                        <span class="mr-2">🤖</span> AI Analysis
                    </h3>
                    <p class="text-sm text-gray-700 mb-2">${suggestionData.reasoning}</p>
                    <div class="text-xs text-blue-600">
                        <strong>Confidence:</strong> ${suggestionData.confidence} • 
                        <strong>Total Suggested Cost:</strong> $${parseFloat(suggestionData.suggestedCost).toFixed(2)}
                    </div>
                </div>
                
                <!-- Cost Breakdown Preview -->
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-800 mb-3">💰 Suggested Cost Breakdown</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="bg-red-50 p-3 rounded border border-red-200">
                            <div class="text-xs text-red-600 font-medium">Materials</div>
                            <div class="text-lg font-bold text-red-800">$${parseFloat(suggestionData.breakdown.materials || 0).toFixed(2)}</div>
                        </div>
                        <div class="bg-blue-50 p-3 rounded border border-blue-200">
                            <div class="text-xs text-blue-600 font-medium">Labor</div>
                            <div class="text-lg font-bold text-blue-800">$${parseFloat(suggestionData.breakdown.labor || 0).toFixed(2)}</div>
                        </div>
                        <div class="bg-yellow-50 p-3 rounded border border-yellow-200">
                            <div class="text-xs text-yellow-600 font-medium">Energy</div>
                            <div class="text-lg font-bold text-yellow-800">$${parseFloat(suggestionData.breakdown.energy || 0).toFixed(2)}</div>
                        </div>
                        <div class="bg-purple-50 p-3 rounded border border-purple-200">
                            <div class="text-xs text-purple-600 font-medium">Equipment</div>
                            <div class="text-lg font-bold text-purple-800">$${parseFloat(suggestionData.breakdown.equipment || 0).toFixed(2)}</div>
                        </div>
                    </div>
                    <div class="mt-3 p-3 bg-green-50 rounded border border-green-200">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold text-green-800">Total Cost:</span>
                            <span class="text-xl font-bold text-green-800">$${parseFloat(suggestionData.suggestedCost).toFixed(2)}</span>
                        </div>
                    </div>
                </div>
                
                ${hasExistingCosts ? `
                    <div class="mb-6 p-4 bg-amber-50 rounded-lg border border-amber-200">
                        <div class="flex items-center mb-2">
                            <span class="text-amber-600 mr-2">⚠️</span>
                            <span class="font-medium text-amber-800">Existing Cost Data Found</span>
                        </div>
                        <p class="text-sm text-amber-700">
                            You have existing cost breakdown items. If you choose to use the new figures, 
                            your current cost data will be cleared and replaced with the AI suggestions.
                        </p>
                    </div>
                ` : ''}
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button onclick="applySuggestedCostBreakdown(this)" data-suggestion='${JSON.stringify(suggestionData).replace(/'/g, '&#39;').replace(/"/g, '&quot;')}' 
                            class="flex-1 bg-gradient-to-r from-blue-600 to-green-600 hover:from-blue-700 hover:to-green-700 text-white px-6 py-3 rounded-lg font-semibold shadow-lg transition-all duration-200">
                        🎯 Use New AI Figures ${hasExistingCosts ? '(Replace Current)' : ''}
                    </button>
                    

                    <button onclick="closeCostSuggestionChoiceDialog()" 
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold transition-all duration-200">
                        ❌ Keep Current Figures
                    </button>
                </div>
                
                <div class="mt-4 text-xs text-gray-500 text-center">
                    💡 Tip: You can always generate new suggestions later if needed
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

function closeCostSuggestionChoiceDialog() {
    const modal = document.getElementById('costSuggestionChoiceModal');
    if (modal) {
        modal.remove();
    }
}

async function applySuggestedCostBreakdown(buttonElement) {
    // Get suggestion data from the button's data attribute
    const suggestionData = JSON.parse(buttonElement.dataset.suggestion);
    
    // Close the choice dialog
    closeCostSuggestionChoiceDialog();
    
    // Show loading state
    showToast('info', 'Applying AI cost breakdown...');
    
    try {
        // Populate the cost breakdown with the suggestion and save to database
        await populateCostBreakdownFromSuggestion(suggestionData);
        
        showToast('success', `✅ AI cost breakdown applied and saved! Total: $${suggestionData.suggestedCost} (${suggestionData.confidence} confidence)`);
    } catch (error) {
        console.error('Error applying cost breakdown:', error);
        showToast('error', 'Failed to apply cost breakdown');
    }
}



async function useSuggestedCost() {
    const nameField = document.getElementById('name');
    const descriptionField = document.getElementById('description');
    const categoryField = document.getElementById('categoryEdit');
    
    if (!nameField || !nameField.value.trim()) {
        showToast('error', 'Item name is required for cost suggestion');
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '🔍 Analyzing...';
    button.disabled = true;
    
    // Gather item data
    const itemData = {
        name: nameField.value.trim(),
        description: descriptionField ? descriptionField.value.trim() : '',
        category: categoryField ? categoryField.value : '',
        sku: currentItemSku || ''
    };
    
    try {
        // Call the cost suggestion API
        const response = await fetch('/api/suggest_cost.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(itemData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show choice dialog with the new figures
            showCostSuggestionChoiceDialog(data);
        } else {
            showToast('error', data.error || 'Failed to get cost suggestion');
        }
    } catch (error) {
        console.error('Error getting cost suggestion:', error);
        showToast('error', 'Failed to connect to cost suggestion service');
    } finally {
        // Restore button state
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

function useSuggestedPrice() {
    const nameField = document.getElementById('name');
    const descriptionField = document.getElementById('description');
    const categoryField = document.getElementById('categoryEdit');
    const costPriceField = document.getElementById('costPrice');
    
    if (!nameField || !nameField.value.trim()) {
        showToast('error', 'Item name is required for price suggestion');
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '🔍 Analyzing...';
    button.disabled = true;
    
    // Gather item data
    const itemData = {
        name: nameField.value.trim(),
        description: descriptionField ? descriptionField.value.trim() : '',
        category: categoryField ? categoryField.value : '',
        costPrice: costPriceField ? parseFloat(costPriceField.value) || 0 : 0,
        sku: currentItemSku || ''
    };
    
    // Call the price suggestion API
    fetch('/api/suggest_price.php', {
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
            // Display the price suggestion inline
            displayPriceSuggestion({
                suggestedPrice: data.suggestedPrice,
                reasoning: data.reasoning,
                confidence: data.confidence,
                factors: data.factors,
                createdAt: new Date().toISOString()
            });
            
            showToast('success', 'Price suggestion generated and saved!');
        } else {
            showToast('error', data.error || 'Failed to get price suggestion');
        }
    })
    .catch(error => {
        console.error('Error getting price suggestion:', error);
        showToast('error', 'Failed to connect to pricing service');
    })
    .finally(() => {
        // Restore button state
        button.innerHTML = originalText;
        button.disabled = false;
    });
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
                                      onclick="showPricingTooltipWithData(event, '${component.type}', ${JSON.stringify(component.explanation)})"
                                      onmouseenter="showPricingTooltipWithData(event, '${component.type}', ${JSON.stringify(component.explanation)})"
                                      onmouseleave="hidePricingTooltipDelayed()">i</span>
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
                                                  onclick="showPricingTooltip(event, '${cleanedItem.replace(/'/g, "\\'")}')"
                                                  onmouseenter="showPricingTooltip(event, '${cleanedItem.replace(/'/g, "\\'")}')"
                                                  onmouseleave="hidePricingTooltipDelayed()">i</span>
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
                reasoningList.innerHTML = '<p class="text-gray-500 text-xs italic px-1">No reasoning provided.</p>';
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
        
        showToast('success', 'Suggested price applied to Retail Price field!');
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
            
            showToast('success', 'Suggested cost applied to Cost Price field!');
        } else {
            showToast('error', 'No suggested cost available. Please generate a cost suggestion first using "🧮 Get Suggested Cost".');
        }
    } else {
        showToast('error', 'Cost suggestion elements not found. Please refresh the page.');
    }
}



async function getPricingExplanation(reasoningText) {
    try {
        const url = `/api/get_pricing_explanation.php?text=${encodeURIComponent(reasoningText)}`;
        console.log('Fetching from URL:', url);
        
        const response = await fetch(url);
        console.log('Response status:', response.status);
        
        const data = await response.json();
        console.log('Response data:', data);
        
        if (data.success) {
            return {
                title: data.title,
                explanation: data.explanation
            };
        } else {
            console.log('API returned success=false:', data.error);
            return {
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
        <div class="font-semibold mb-1">${title}</div>
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
        console.log('Fetching explanation for:', reasoningText);
        const explanationData = await getPricingExplanation(reasoningText);
        console.log('Received explanation data:', explanationData);
        
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
            <div class="font-semibold text-blue-200 mb-2">${explanationData.title}</div>
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
            <div class="font-semibold mb-1">Error Loading Explanation</div>
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
        showToast('error', 'No item SKU available');
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
    
    // Gather item data
    const itemData = {
        name: nameField ? nameField.value.trim() : '',
        description: descriptionField ? descriptionField.value.trim() : '',
        category: categoryField ? categoryField.value : '',
        costPrice: costPriceField ? parseFloat(costPriceField.value) || 0 : 0,
        sku: currentItemSku
    };
    
    // Call the price suggestion API
    fetch('/api/suggest_price.php', {
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
            // Display the price suggestion inline for view modal
            displayViewPriceSuggestion({
                suggestedPrice: data.suggestedPrice,
                reasoning: data.reasoning,
                confidence: data.confidence,
                factors: data.factors,
                createdAt: new Date().toISOString()
            });
            
            showToast('success', 'Price suggestion generated and saved!');
        } else {
            showToast('error', data.error || 'Failed to get price suggestion');
        }
    })
    .catch(error => {
        console.error('Error getting price suggestion:', error);
        showToast('error', 'Failed to connect to pricing service');
    })
    .finally(() => {
        // Restore button state
        button.innerHTML = originalText;
        button.disabled = false;
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
                                      onclick="showPricingTooltipWithData(event, '${component.type}', ${JSON.stringify(component.explanation)})"
                                      onmouseenter="showPricingTooltipWithData(event, '${component.type}', ${JSON.stringify(component.explanation)})"
                                      onmouseleave="hidePricingTooltipDelayed()">i</span>
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
                                                  onclick="showPricingTooltip(event, '${cleanedItem.replace(/'/g, "\\'")}')"
                                                  onmouseenter="showPricingTooltip(event, '${cleanedItem.replace(/'/g, "\\'")}')"
                                                  onmouseleave="hidePricingTooltipDelayed()">i</span>
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
                reasoningList.innerHTML = '<p class="text-gray-500 text-xs italic px-1">No reasoning provided.</p>';
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

function loadExistingViewPriceSuggestion(sku) {
    if (!sku) return;
    
    fetch(`/api/get_price_suggestion.php?sku=${encodeURIComponent(sku)}`)
    .then(response => response.json())
    .then(data => {
        console.log('View Price suggestion API response:', data); // Debug log
        if (data.success && data.suggestedPrice) {
            displayViewPriceSuggestion(data);
        } else {
            // Show placeholder if no existing suggestion
            const placeholder = document.getElementById('viewPriceSuggestionPlaceholder');
            const display = document.getElementById('viewPriceSuggestionDisplay');
            if (placeholder) placeholder.classList.remove('hidden');
            if (display) display.classList.add('hidden');
        }
    })
    .catch(error => {
        console.error('Error loading view price suggestion:', error);
        // Show placeholder on error
        const placeholder = document.getElementById('viewPriceSuggestionPlaceholder');
        const display = document.getElementById('viewPriceSuggestionDisplay');
        if (placeholder) placeholder.classList.remove('hidden');
        if (display) display.classList.add('hidden');
    });
}

// View-specific functions removed - view modal now uses same functions as edit modal

function loadExistingPriceSuggestion(sku) {
    if (!sku) return;
    
    fetch(`/api/get_price_suggestion.php?sku=${encodeURIComponent(sku)}`)
    .then(response => response.json())
    .then(data => {
        console.log('Price suggestion API response:', data); // Debug log
        if (data.success && data.suggestedPrice) {
            displayPriceSuggestion(data);
        } else {
            // Show placeholder if no existing suggestion
            const placeholder = document.getElementById('priceSuggestionPlaceholder');
            const display = document.getElementById('priceSuggestionDisplay');
            if (placeholder) placeholder.classList.remove('hidden');
            if (display) display.classList.add('hidden');
        }
    })
    .catch(error => {
        console.error('Error loading price suggestion:', error);
        // Show placeholder on error
        const placeholder = document.getElementById('priceSuggestionPlaceholder');
        const display = document.getElementById('priceSuggestionDisplay');
        if (placeholder) placeholder.classList.remove('hidden');
        if (display) display.classList.add('hidden');
    });
}

function loadExistingCostSuggestion(sku) {
    // This function is kept for potential future use but currently not needed
    // since we populate the cost breakdown directly instead of showing inline display
    return;
}

function loadExistingMarketingSuggestion(sku) {
    if (!sku) return;
    
    fetch(`/api/get_marketing_suggestion.php?sku=${encodeURIComponent(sku)}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.exists) {
            displayMarketingSuggestionIndicator(data.suggestion);
        }
    })
    .catch(error => {
        console.error('Error loading marketing suggestion:', error);
    });
}

function displayMarketingSuggestionIndicator(suggestion) {
    // Find the marketing copy button
    const marketingButton = document.querySelector('button[onclick="generateMarketingCopy(event)"]');
    if (!marketingButton) return;
    
    // Add indicator that previous suggestion exists
    const existingIndicator = marketingButton.querySelector('.suggestion-indicator');
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    const indicator = document.createElement('span');
    indicator.className = 'suggestion-indicator ml-2 px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full';
    indicator.innerHTML = '💾 Previous';
    indicator.title = `Previous AI analysis available from ${new Date(suggestion.created_at).toLocaleDateString()}`;
    
    marketingButton.appendChild(indicator);
    
    // Store the suggestion data for potential reuse
    window.existingMarketingSuggestion = suggestion;
}

async function populateCostBreakdownFromSuggestion(suggestionData) {
    // Clear existing cost breakdown from both UI and database
    await clearCostBreakdownCompletely();
    
    // Initialize cost breakdown if not already done
    if (!costBreakdown) {
        costBreakdown = {
            materials: {},
            labor: {},
            energy: {},
            equipment: {},
            totals: {}
        };
    }
    
    const currentSku = currentItemSku;
    
    // Populate each category from the suggestion breakdown
    const breakdown = suggestionData.breakdown;
    
    // Materials
    if (breakdown.materials > 0) {
        const materialId = 'material_' + Date.now();
        costBreakdown.materials[materialId] = {
            name: 'Suggested Materials',
            cost: breakdown.materials
        };
        addCostItemToUI('materials', materialId, 'Suggested Materials', breakdown.materials);
        
        // Save to database
        await saveCostItemToDatabase('materials', {
            inventoryId: currentSku,
            name: 'Suggested Materials',
            cost: breakdown.materials
        });
    }
    
    // Labor
    if (breakdown.labor > 0) {
        const laborId = 'labor_' + Date.now();
        costBreakdown.labor[laborId] = {
            name: 'Suggested Labor',
            cost: breakdown.labor
        };
        addCostItemToUI('labor', laborId, 'Suggested Labor', breakdown.labor);
        
        // Save to database
        await saveCostItemToDatabase('labor', {
            inventoryId: currentSku,
            description: 'Suggested Labor',
            cost: breakdown.labor
        });
    }
    
    // Energy
    if (breakdown.energy > 0) {
        const energyId = 'energy_' + Date.now();
        costBreakdown.energy[energyId] = {
            name: 'Suggested Energy',
            cost: breakdown.energy
        };
        addCostItemToUI('energy', energyId, 'Suggested Energy', breakdown.energy);
        
        // Save to database
        await saveCostItemToDatabase('energy', {
            inventoryId: currentSku,
            description: 'Suggested Energy',
            cost: breakdown.energy
        });
    }
    
    // Equipment
    if (breakdown.equipment > 0) {
        const equipmentId = 'equipment_' + Date.now();
        costBreakdown.equipment[equipmentId] = {
            name: 'Suggested Equipment',
            cost: breakdown.equipment
        };
        addCostItemToUI('equipment', equipmentId, 'Suggested Equipment', breakdown.equipment);
        
        // Save to database
        await saveCostItemToDatabase('equipment', {
            inventoryId: currentSku,
            description: 'Suggested Equipment',
            cost: breakdown.equipment
        });
    }
    
    // Calculate and update totals
    const totals = {
        materialTotal: breakdown.materials || 0,
        laborTotal: breakdown.labor || 0,
        energyTotal: breakdown.energy || 0,
        equipmentTotal: breakdown.equipment || 0,
        suggestedCost: suggestionData.suggestedCost
    };
    
    // Update totals display
    updateTotalsDisplay(totals);
    
    // Show the cost breakdown section if it's hidden
    const costBreakdownSection = document.getElementById('costBreakdownSection');
    if (costBreakdownSection && costBreakdownSection.classList.contains('hidden')) {
        costBreakdownSection.classList.remove('hidden');
    }
    
    // Add a note about the suggestion
    const noteElement = document.createElement('div');
    noteElement.className = 'mt-3 p-2 bg-blue-50 border border-blue-200 rounded text-sm text-blue-800';
    noteElement.innerHTML = `
        <strong>💡 AI Suggestion Applied & Saved:</strong> ${suggestionData.reasoning}
        <br><small>Confidence: ${suggestionData.confidence} • Cost breakdown has been saved to database</small>
    `;
    
    // Remove any existing suggestion note
    const existingNote = document.querySelector('.cost-suggestion-note');
    if (existingNote) {
        existingNote.remove();
    }
    
    // Add the note to the cost breakdown section
    noteElement.classList.add('cost-suggestion-note');
    const costTotalsDiv = document.getElementById('costTotals');
    if (costTotalsDiv) {
        costTotalsDiv.parentNode.insertBefore(noteElement, costTotalsDiv.nextSibling);
    }
}

function clearCostBreakdown() {
    // Clear the data
    if (costBreakdown) {
        costBreakdown.materials = {};
        costBreakdown.labor = {};
        costBreakdown.energy = {};
        costBreakdown.equipment = {};
        costBreakdown.totals = {};
    }
    
    // Clear the UI lists
    ['materials', 'labor', 'energy', 'equipment'].forEach(category => {
        const listElement = document.getElementById(`${category}List`);
        if (listElement) {
            listElement.innerHTML = '<p class="text-gray-500 text-xs italic px-1">No items added yet.</p>';
        }
    });
    
    // Clear totals display
    updateTotalsDisplay({ 
        materialTotal: 0, 
        laborTotal: 0, 
        energyTotal: 0, 
        equipmentTotal: 0, 
        suggestedCost: 0 
    });
    
    // Remove any existing suggestion note
    const existingNote = document.querySelector('.cost-suggestion-note');
    if (existingNote) {
        existingNote.remove();
    }
}

async function clearCostBreakdownCompletely() {
    // First clear the UI and local data
    clearCostBreakdown();
    
    // Then clear from database
    if (currentItemSku) {
        try {
            const response = await fetch('process_cost_breakdown.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'clear_all',
                    inventoryId: currentItemSku
                })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                console.error('Failed to clear cost breakdown from database:', result.error);
                showToast('warning', 'UI cleared but database may still contain old cost data');
                return false;
            }
            
            return true;
        } catch (error) {
            console.error('Error clearing cost breakdown from database:', error);
            showToast('warning', 'UI cleared but database may still contain old cost data');
            return false;
        }
    }
}

function addCostItemToUI(category, itemId, itemName, itemCost) {
    const listElement = document.getElementById(`${category}List`);
    if (!listElement) {
        console.error(`Could not find list element for category: ${category}`);
        return;
    }
    
    // Remove the "No items added yet" message if it exists
    const noItemsMsg = listElement.querySelector('.text-gray-500');
    if (noItemsMsg) {
        noItemsMsg.remove();
    }
    
    // Create the item element
    const itemDiv = document.createElement('div');
    itemDiv.className = 'cost-item';
    itemDiv.innerHTML = `
        <span class="cost-item-name" title="${htmlspecialchars(itemName)}">${htmlspecialchars(itemName)}</span>
        <div class="cost-item-actions">
            <span class="cost-item-value">$${parseFloat(itemCost).toFixed(2)}</span>
            <button type="button" class="delete-cost-btn" data-id="${itemId}" data-type="${category}" title="Delete this cost item">×</button>
        </div>
    `;
    
    listElement.appendChild(itemDiv);
}

function updateCostTotals() {
    // This function is used elsewhere in the code, so we'll create it as an alias
    // Calculate totals from the current costBreakdown data
    if (!costBreakdown) {
        updateTotalsDisplay({ 
            materialTotal: 0, 
            laborTotal: 0, 
            energyTotal: 0, 
            equipmentTotal: 0, 
            suggestedCost: 0 
        });
        return;
    }
    
    const totals = {
        materialTotal: Object.values(costBreakdown.materials || {}).reduce((sum, item) => sum + parseFloat(item.cost || 0), 0),
        laborTotal: Object.values(costBreakdown.labor || {}).reduce((sum, item) => sum + parseFloat(item.cost || 0), 0),
        energyTotal: Object.values(costBreakdown.energy || {}).reduce((sum, item) => sum + parseFloat(item.cost || 0), 0),
        equipmentTotal: Object.values(costBreakdown.equipment || {}).reduce((sum, item) => sum + parseFloat(item.cost || 0), 0)
    };
    
    totals.suggestedCost = totals.materialTotal + totals.laborTotal + totals.energyTotal + totals.equipmentTotal;
    
    updateTotalsDisplay(totals);
}

async function saveCostItemToDatabase(costType, data) {
    try {
        const response = await fetch('process_cost_breakdown.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                inventoryId: data.inventoryId,
                costType: costType,
                name: data.name,
                description: data.description,
                cost: data.cost
            })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            console.error('Failed to save cost item:', result.error);
            showToast('error', 'Failed to save cost item: ' + result.error);
            return false;
        }
        
        return true;
    } catch (error) {
        console.error('Error saving cost item:', error);
        showToast('error', 'Error saving cost item: ' + error.message);
        return false;
    }
}

function generateMarketingCopy() {
    const nameField = document.getElementById('name');
    const categoryField = document.getElementById('categoryEdit');
    const descriptionField = document.getElementById('description');
    
    if (!nameField || !nameField.value.trim()) {
        showToast('error', 'Item name is required for marketing copy generation');
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
            showToast('error', data.error || 'Failed to generate marketing suggestions');
        }
    })
    .catch(error => {
        console.error('Error generating marketing suggestions:', error);
        showToast('error', 'Failed to connect to AI marketing service');
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
    overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    overlay.id = 'marketingIntelligenceModal';
    
    // Create modal content with comprehensive marketing intelligence
    overlay.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-7xl mx-4 max-h-[95vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                        <span class="text-2xl">🧠</span>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-xl font-bold text-gray-900">AI Marketing Intelligence</h3>
                        <p class="text-sm text-gray-500">Comprehensive marketing analysis and suggestions</p>
                        <div class="flex items-center mt-1">
                            <span class="text-xs text-green-600 font-medium">Confidence: ${Math.round(data.confidence * 100)}%</span>
                            <span class="ml-2 text-xs text-gray-400">• Powered by AI Analysis</span>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="closeMarketingIntelligenceModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Primary Content -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Enhanced Title & Description -->
                <div class="space-y-4">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <span class="mr-2">🏷️</span> AI-Enhanced Title
                        </h4>
                        <div class="p-3 bg-white border border-blue-200 rounded-lg hover:bg-blue-50 cursor-pointer" onclick="applyTitle('${data.title.replace(/'/g, "\\'")}')">
                            <div class="font-medium text-gray-800">${data.title}</div>
                            <div class="text-xs text-blue-600 mt-1">Click to apply to product name</div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <span class="mr-2">📝</span> AI-Crafted Description
                        </h4>
                        <div class="p-3 bg-white border border-green-200 rounded-lg hover:bg-green-50 cursor-pointer" onclick="applyDescription('${data.description.replace(/'/g, "\\'")}')">
                            <div class="text-gray-800 text-sm">${data.description}</div>
                            <div class="text-xs text-green-600 mt-1">Click to apply to product description</div>
                        </div>
                    </div>
                </div>
                
                <!-- Target Audience & Keywords -->
                <div class="space-y-4">
                    <div class="bg-purple-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <span class="mr-2">🎯</span> Target Audience
                        </h4>
                        <p class="text-sm text-gray-700">${data.targetAudience}</p>
                    </div>
                    
                    <div class="bg-yellow-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <span class="mr-2">🔍</span> SEO Keywords
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            ${data.keywords.map(keyword => `
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full font-medium">${keyword}</span>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Marketing Intelligence Tabs -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex space-x-8">
                    <button onclick="showMarketingTab('selling')" class="marketing-tab-btn active py-2 px-1 border-b-2 border-purple-500 font-medium text-sm text-purple-600">
                        💰 Selling Points
                    </button>
                    <button onclick="showMarketingTab('competitive')" class="marketing-tab-btn py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                        ⚡ Competitive Edge
                    </button>
                    <button onclick="showMarketingTab('conversion')" class="marketing-tab-btn py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                        🎯 Conversion
                    </button>
                    <button onclick="showMarketingTab('channels')" class="marketing-tab-btn py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                        📢 Channels
                    </button>
                </nav>
            </div>
            
            <!-- Tab Content -->
            <div id="marketing-tab-content">
                <!-- Selling Points Tab -->
                <div id="tab-selling" class="marketing-tab-content">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-green-50 rounded-lg p-4">
                            <h5 class="font-semibold text-gray-800 mb-2">💎 Key Selling Points</h5>
                            <ul class="text-sm text-gray-700 space-y-1">
                                ${data.marketingIntelligence.selling_points.map(point => `
                                    <li class="flex items-start"><span class="text-green-600 mr-2">•</span>${point}</li>
                                `).join('')}
                            </ul>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h5 class="font-semibold text-gray-800 mb-2">🎭 Emotional Triggers</h5>
                            <div class="flex flex-wrap gap-2">
                                ${data.marketingIntelligence.emotional_triggers.map(trigger => `
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">${trigger}</span>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Competitive Edge Tab -->
                <div id="tab-competitive" class="marketing-tab-content hidden">
                    <div class="bg-red-50 rounded-lg p-4">
                        <h5 class="font-semibold text-gray-800 mb-3">🏆 Competitive Advantages</h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            ${data.marketingIntelligence.competitive_advantages.map(advantage => `
                                <div class="bg-white p-3 rounded-lg border border-red-200">
                                    <div class="font-medium text-gray-800 text-sm">${advantage}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                
                <!-- Conversion Tab -->
                <div id="tab-conversion" class="marketing-tab-content hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-orange-50 rounded-lg p-4">
                            <h5 class="font-semibold text-gray-800 mb-2">🎯 Call-to-Action Ideas</h5>
                            <ul class="text-sm text-gray-700 space-y-2">
                                ${data.marketingIntelligence.call_to_action_suggestions.map(cta => `
                                    <li class="bg-white p-2 rounded border border-orange-200 font-medium">"${cta}"</li>
                                `).join('')}
                            </ul>
                        </div>
                        <div class="bg-pink-50 rounded-lg p-4">
                            <h5 class="font-semibold text-gray-800 mb-2">⚡ Conversion Boosters</h5>
                            <div class="space-y-2 text-sm text-gray-700">
                                <div class="bg-white p-2 rounded border border-pink-200">
                                    <strong>Urgency:</strong> Limited time offer
                                </div>
                                <div class="bg-white p-2 rounded border border-pink-200">
                                    <strong>Social Proof:</strong> Customer testimonials
                                </div>
                                <div class="bg-white p-2 rounded border border-pink-200">
                                    <strong>Guarantee:</strong> Satisfaction promise
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Marketing Channels Tab -->
                <div id="tab-channels" class="marketing-tab-content hidden">
                    <div class="bg-indigo-50 rounded-lg p-4">
                        <h5 class="font-semibold text-gray-800 mb-3">📢 Recommended Marketing Channels</h5>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            ${data.marketingIntelligence.marketing_channels.map(channel => `
                                <div class="bg-white p-3 rounded-lg border border-indigo-200 text-center">
                                    <div class="font-medium text-gray-800 text-sm">${channel}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- AI Analysis Summary -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h4 class="font-semibold text-gray-800 mb-2 flex items-center">
                    <span class="mr-2">🧠</span> AI Analysis Summary
                </h4>
                <p class="text-sm text-gray-700">${data.reasoning}</p>
            </div>
            
            <div class="flex justify-between items-center mt-6">
                <div class="text-xs text-gray-500">
                    Analysis saved to database • All suggestions are AI-generated recommendations
                </div>
                <button type="button" onclick="closeMarketingIntelligenceModal()" class="px-6 py-2 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-lg hover:from-purple-600 hover:to-pink-600 transition-all duration-200 font-medium">
                    Close Analysis
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    // Close on overlay click
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeMarketingIntelligenceModal();
        }
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMarketingIntelligenceModal();
        }
    });
}

function closeMarketingIntelligenceModal() {
    const modal = document.getElementById('marketingIntelligenceModal');
    if (modal) {
        modal.remove();
    }
}

function showMarketingTab(tabName) {
    // Hide all tab content
    document.querySelectorAll('.marketing-tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.marketing-tab-btn').forEach(btn => {
        btn.classList.remove('active', 'border-purple-500', 'text-purple-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected tab
    const selectedTab = document.getElementById(`tab-${tabName}`);
    if (selectedTab) {
        selectedTab.classList.remove('hidden');
    }
    
    // Activate selected button
    const selectedButton = event.target;
    selectedButton.classList.add('active', 'border-purple-500', 'text-purple-600');
    selectedButton.classList.remove('border-transparent', 'text-gray-500');
}

function applyTitle(title) {
    const nameField = document.getElementById('name');
    if (nameField) {
        nameField.value = title;
        nameField.style.backgroundColor = '#f3e8ff';
        setTimeout(() => {
            nameField.style.backgroundColor = '';
        }, 2000);
        showToast('success', 'Title applied! Remember to save your changes.');
    }
}

function applyDescription(description) {
    const descriptionField = document.getElementById('description');
    if (descriptionField) {
        descriptionField.value = description;
        descriptionField.style.backgroundColor = '#f3e8ff';
        setTimeout(() => {
            descriptionField.style.backgroundColor = '';
        }, 2000);
        showToast('success', 'Description applied! Remember to save your changes.');
    }
}

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
    const url = `/process_cost_breakdown.php?id=${id}&costType=${type}&inventoryId=${currentItemSku}`;
    
    fetch(url, {
        method: 'DELETE',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message || 'Cost item deleted successfully');
            // Refresh the cost breakdown to update the display
            refreshCostBreakdown(false);
        } else {
            showToast('error', data.error || 'Failed to delete cost item');
        }
    })
    .catch(error => {
        console.error('Error deleting cost item:', error);
        showToast('error', 'Failed to delete cost item');
    });
}


document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded - modalMode:', modalMode, 'currentItemSku:', currentItemSku, 'costBreakdown:', costBreakdown);
    
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
                console.log('Loading existing price suggestion for edit mode, SKU:', currentItemSku);
                loadExistingPriceSuggestion(currentItemSku);
            }
            
            // Load existing price suggestion for view mode
            if (modalMode === 'view') {
                console.log('Loading existing price suggestion for view mode, SKU:', currentItemSku);
                loadExistingViewPriceSuggestion(currentItemSku);
            }
            
            // Load existing marketing suggestion for edit/view mode
            console.log('Loading existing marketing suggestion for SKU:', currentItemSku);
            loadExistingMarketingSuggestion(currentItemSku);
            

        } else if (modalMode === 'add') {
            console.log('Calling renderCostBreakdown(null) for add mode');
            renderCostBreakdown(null); 
        } else {
            console.log('Conditions not met - modalMode:', modalMode, 'currentItemSku:', currentItemSku, 'costBreakdown:', !!costBreakdown);
        }
    } else {
        console.log('No modal mode active, skipping cost breakdown initialization');
    }
    
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
                    if (data.sku) { // sku is returned by add/update operations
                        redirectUrl += '&highlight=' + data.sku;
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
                        formData.append('sku', currentItemSku);
                
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
    
    const confirmCostDeleteBtn = document.getElementById('confirmCostDeleteBtn');
    if (confirmCostDeleteBtn) {
        confirmCostDeleteBtn.addEventListener('click', confirmCostDeletion);
    }
    
    const deleteConfirmModalElement = document.getElementById('deleteConfirmModal');
    const confirmDeleteActualBtn = document.getElementById('confirmDeleteBtn');
    let itemToDeleteSku = null;

    document.querySelectorAll('.delete-item').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            itemToDeleteSku = this.dataset.sku;
            if(deleteConfirmModalElement) deleteConfirmModalElement.classList.add('show');
        });
    });

    if (confirmDeleteActualBtn && deleteConfirmModalElement) {
        confirmDeleteActualBtn.addEventListener('click', function() {
            if (!itemToDeleteSku) return;
            fetch(`/process_inventory_update.php?action=delete&sku=${itemToDeleteSku}`, {
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
            } else if (document.getElementById('deleteCostConfirmModal')?.classList.contains('show')) {
                closeCostDeleteModal();
            } else if (deleteConfirmModalElement && deleteConfirmModalElement.classList.contains('show')) {
                deleteConfirmModalElement.classList.remove('show');
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

    // Auto fetch SKU when category changes (add mode only)
    const catSelect=document.getElementById('categoryEdit');
    const skuInput=document.getElementById('skuEdit');
    if(catSelect&&skuInput){
        catSelect.addEventListener('change',()=>{
            const cat=catSelect.value;
            if(!cat){ skuInput.value=''; return; }
            fetch('/api/next_sku.php?cat='+encodeURIComponent(cat))
              .then(r=>r.json()).then(d=>{ if(d.success){ skuInput.value=d.sku; } });
        });
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
            showToast('info', 'Categories updated! Dropdown refreshed.');
        });
    }
});

// Multi-Image Upload Functions
let selectedFiles = [];

// Handle file selection and auto-upload
document.getElementById('multiImageUpload')?.addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    if (files.length === 0) return;
    
    // Validate file sizes before upload (10MB = 10 * 1024 * 1024 bytes)
    const maxFileSize = 10 * 1024 * 1024;
    const oversizedFiles = files.filter(file => file.size > maxFileSize);
    
    if (oversizedFiles.length > 0) {
        const fileNames = oversizedFiles.map(file => `${file.name} (${(file.size / 1024 / 1024).toFixed(1)}MB)`).join(', ');
        showToast('error', `The following files are too large (max 10MB allowed): ${fileNames}`);
        // Clear the file input
        this.value = '';
        return;
    }
    
    // Show progress indicator
    const progressContainer = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    progressContainer.classList.remove('hidden');
    progressBar.style.width = '0%';
    
    // Auto-upload the selected files
    autoUploadImages(files);
});

function autoUploadImages(files) {
    console.log('autoUploadImages called with files:', files);
    
    const sku = (document.getElementById('skuEdit') || document.getElementById('skuDisplay'))?.value;
    console.log('SKU:', sku);
    
    if (!sku) {
        console.error('No SKU found');
        showToast('error', 'SKU is required');
        hideUploadProgress();
        return;
    }
    
    const formData = new FormData();
    files.forEach((file, index) => {
        console.log(`Adding file ${index + 1}:`, file.name, file.size, 'bytes');
        formData.append('images[]', file);
    });
    
    formData.append('sku', sku);
    formData.append('altText', document.getElementById('name')?.value || '');
    
    console.log('FormData prepared, starting upload...');
    
    // Update progress bar
    const progressBar = document.getElementById('uploadProgressBar');
    progressBar.style.width = '25%';
    
    fetch('/process_multi_image_upload.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        console.log('Upload response status:', response.status);
        console.log('Upload response headers:', response.headers);
        progressBar.style.width = '75%';
        return response.text(); // Get as text first to see what we're getting
    })
    .then(text => {
        console.log('Upload response text:', text);
        progressBar.style.width = '100%';
        
        try {
            const data = JSON.parse(text);
            console.log('Parsed upload response:', data);
            
            if (data.success) {
                showToast('success', data.message || `Successfully uploaded ${files.length} image(s)`);
                
                // Clear the file input
                document.getElementById('multiImageUpload').value = '';
                
                // Refresh current images display
                loadCurrentImages(sku);
                
            } else {
                console.error('Upload failed:', data.error);
                showToast('error', data.error || 'Upload failed');
            }
            
            if (data.warnings && data.warnings.length > 0) {
                data.warnings.forEach(warning => {
                    console.warn('Upload warning:', warning);
                    showToast('warning', warning);
                });
            }
        } catch (e) {
            console.error('Failed to parse JSON response:', e);
            console.error('Raw response:', text.substring(0, 500));
            showToast('error', 'Server returned invalid response: ' + text.substring(0, 100));
        }
    })
    .catch(error => {
        console.error('Upload fetch error:', error);
        showToast('error', 'Upload failed: ' + error.message);
    })
    .finally(() => {
        // Hide progress after a short delay
        setTimeout(() => {
            hideUploadProgress();
        }, 1000);
    });
}

function hideUploadProgress() {
    const progressContainer = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    progressContainer.classList.add('hidden');
    progressBar.style.width = '0%';
}

function loadCurrentImages(sku, isViewModal = false) {
    if (!sku) return;
    
    fetch(`/api/get_item_images.php?sku=${encodeURIComponent(sku)}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Use the same function for both edit and view modals
            displayCurrentImages(data.images, isViewModal);
        } else {
            console.error('Failed to load images:', data.error);
            const container = document.getElementById('currentImagesList');
            const loadingDiv = document.getElementById('viewModalImagesLoading') || document.getElementById('currentImagesLoading');
            if (loadingDiv) loadingDiv.remove();
            if (container) container.innerHTML = '<div class="text-center text-gray-500 text-sm">Failed to load images</div>';
        }
    })
    .catch(error => {
        console.error('Error loading images:', error);
        const container = document.getElementById('currentImagesList');
        const loadingDiv = document.getElementById('viewModalImagesLoading') || document.getElementById('currentImagesLoading');
        if (loadingDiv) loadingDiv.remove();
        if (container) container.innerHTML = '<div class="text-center text-gray-500 text-sm">Error loading images</div>';
    });
}

function loadThumbnailImage(sku, container) {
    if (!sku || !container) return;
    
    fetch(`/api/get_item_images.php?sku=${encodeURIComponent(sku)}`)
    .then(response => response.json())
    .then(data => {
        const loadingDiv = container.querySelector('.thumbnail-loading');
        if (loadingDiv) loadingDiv.remove();
        
        if (data.success && data.images && data.images.length > 0) {
            // Find primary image or use first image
            const primaryImage = data.images.find(img => img.is_primary) || data.images[0];
            container.innerHTML = `<img src="${primaryImage.image_path}" alt="thumb" style="width:40px;height:40px;object-fit:cover;border-radius:6px;box-shadow:0 1px 3px #bbb;" onerror="this.parentElement.innerHTML='<div style=&quot;width:40px;height:40px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#999;&quot;>No img</div>'">`;
        } else {
            container.innerHTML = '<div style="width:40px;height:40px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#999;">No img</div>';
        }
    })
    .catch(error => {
        console.error('Error loading thumbnail:', error);
        const loadingDiv = container.querySelector('.thumbnail-loading');
        if (loadingDiv) loadingDiv.remove();
        container.innerHTML = '<div style="width:40px;height:40px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#999;">No img</div>';
    });
}

function displayCurrentImages(images, isViewModal = false) {
    const container = document.getElementById('currentImagesList');
    
    if (!images || images.length === 0) {
        container.innerHTML = '<div class="text-gray-500 text-sm col-span-full">No images uploaded yet</div>';
        return;
    }
    
    container.innerHTML = '';
    
    // Determine carousel type and track ID
    const carouselType = isViewModal ? 'view' : 'edit';
    const trackId = isViewModal ? 'viewCarouselTrack' : 'editCarouselTrack';
    
    // Create carousel container
    const carouselContainer = document.createElement('div');
    carouselContainer.className = 'image-carousel-container relative';
    carouselContainer.style.width = '100%';
    carouselContainer.innerHTML = `
        <div class="image-carousel-wrapper overflow-hidden" style="width: 100%; max-width: 525px;">
            <div class="image-carousel-track flex transition-transform duration-300 ease-in-out" id="${trackId}">
                <!-- Images will be added here -->
            </div>
        </div>
        ${images.length > 3 ? `
            <button type="button" class="carousel-nav carousel-prev absolute left-2 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-90 hover:bg-opacity-100 rounded-full p-3 shadow-lg z-10 transition-all" onclick="moveCarousel('${carouselType}', -1)">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>
            <button type="button" class="carousel-nav carousel-next absolute right-2 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-90 hover:bg-opacity-100 rounded-full p-3 shadow-lg z-10 transition-all" onclick="moveCarousel('${carouselType}', 1)">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>
        ` : ''}
    `;
    
    const track = carouselContainer.querySelector(`#${trackId}`);
    
    // Add all images to the carousel track
    images.forEach((image, index) => {
        const imageDiv = document.createElement('div');
        imageDiv.className = 'carousel-slide flex-shrink-0';
        // Use fixed pixel width to show exactly 3 images - calculate based on container
        // Container is ~506px, so each slide should be ~155px to fit 3 with gaps
        imageDiv.style.width = '155px';
        imageDiv.style.marginRight = '15px';
        
        console.log(`Creating slide ${index + 1}, width: 155px, marginRight: 15px`);
        
        // Action buttons only for edit modal
        const actionButtons = isViewModal ? '' : `
            <div class="flex gap-1 mt-1 flex-wrap">
                        ${!image.is_primary ? `<button type="button" data-action="set-primary" data-sku="${image.sku}" data-image-id="${image.id}" class="text-xs px-1 py-0.5 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors" title="Set as Primary">Primary</button>` : ''}
                                  <button type="button" data-action="delete-image" data-sku="${image.sku}" data-image-id="${image.id}" class="text-xs px-1 py-0.5 bg-red-500 text-white rounded hover:bg-red-600 transition-colors" title="Delete Image">Delete</button>
            </div>
        `;
        
        imageDiv.innerHTML = `
            <div class="relative bg-white border-2 rounded-lg overflow-hidden shadow-md h-full">
                <div class="relative carousel-image-container" style="height: 150px;">
                    <img src="${image.image_path}" alt="${image.alt_text}" 
                         class="w-full h-full object-contain bg-gray-50 carousel-image" 
                         onerror="this.src='images/items/placeholder.png'"
                         style="object-position: center;">
                </div>
                <div class="p-2 bg-gray-50">
                    ${!isViewModal ? `<div class="text-xs text-gray-700 truncate font-medium" title="${image.image_path.split('/').pop()}">${image.image_path.split('/').pop()}</div>` : ''}
                    ${image.is_primary ? '<div class="text-xs text-green-600 font-semibold mt-1">⭐ Primary</div>' : ''}
                    ${actionButtons}
                </div>
            </div>
        `;
        track.appendChild(imageDiv);
    });
    
    // Set the track width based on number of images using fixed pixel widths
    // Each slide is 155px + 15px margin = 170px per slide
    let trackWidth;
    if (images.length <= 3) {
        trackWidth = '100%';
    } else {
        // Calculate total width needed: (slides * 155px) + ((slides-1) * 15px gaps)
        const totalWidth = (images.length * 155) + ((images.length - 1) * 15);
        trackWidth = totalWidth + 'px';
    }
    track.style.width = trackWidth;
    
    // Debug: Force container to show only 3 images worth of width
    const wrapper = track.parentElement;
    if (wrapper && images.length > 3) {
        // 3 images * 155px + 2 gaps * 15px = 495px
        wrapper.style.width = '495px';
        wrapper.style.maxWidth = '495px';
        console.log('Forced wrapper width to 495px to show exactly 3 images');
    }
    
    console.log(`Track width set to: ${trackWidth} for ${images.length} images`);
    
    container.appendChild(carouselContainer);
    
    // Initialize carousel position
    const positionVar = isViewModal ? 'viewCarouselPosition' : 'editCarouselPosition';
    window[positionVar] = 0;
    
    // Images now have fixed height, no normalization needed
    
    // Debug: Check actual container and track dimensions
    setTimeout(() => {
        const containerWidth = container.offsetWidth;
        const trackElement = document.getElementById(trackId);
        const trackWidth = trackElement ? trackElement.offsetWidth : 'not found';
        const slides = trackElement ? trackElement.querySelectorAll('.carousel-slide') : [];
        
        console.log(`Carousel debug for ${carouselType}:`);
        console.log(`- Container width: ${containerWidth}px`);
        console.log(`- Track width: ${trackWidth}px`);
        console.log(`- Number of slides: ${slides.length}`);
        if (slides.length > 0) {
            console.log(`- First slide width: ${slides[0].offsetWidth}px`);
            console.log(`- First slide computed width: ${getComputedStyle(slides[0]).width}`);
        }
    }, 100);
    
    // Update carousel navigation visibility
    updateCarouselNavigation(carouselType, images.length);
    
    console.log('Loaded', images.length, 'images for', carouselType, 'carousel, track width:', trackWidth);
}

// Helper functions removed - now using carousel layout

// displayViewModalImages function removed - now using unified displayCurrentImages function

function loadThumbnailImage(sku, container) {
    if (!sku || !container) return;
    
    fetch(`/api/get_item_images.php?sku=${encodeURIComponent(sku)}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.images && data.images.length > 0) {
            // Find primary image or use first image
            const primaryImage = data.images.find(img => img.is_primary) || data.images[0];
            
            container.innerHTML = `
                <img src="${primaryImage.image_path}" alt="thumb" 
                     style="width:40px;height:40px;object-fit:cover;border-radius:6px;box-shadow:0 1px 3px #bbb;" 
                     onerror="this.parentElement.innerHTML='<div style=&quot;width:40px;height:40px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#999;&quot;>No img</div>'">
            `;
        } else {
            container.innerHTML = '<div style="width:40px;height:40px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#999;">No img</div>';
        }
    })
    .catch(error => {
        console.error('Error loading thumbnail for', sku, ':', error);
        container.innerHTML = '<div style="width:40px;height:40px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#999;">No img</div>';
    });
}

// Load current images when modal opens
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const viewId = urlParams.get('view');
    
    if (modalMode === 'edit' && editId) {
        // Wait a bit for the DOM to be fully ready
        setTimeout(() => {
            const skuField = document.getElementById('skuEdit') || document.getElementById('skuDisplay');
            if (skuField && skuField.value) {
                console.log('Loading current images for edit modal:', skuField.value);
                loadCurrentImages(skuField.value, false);
            } else {
                console.log('No SKU found for loading images');
            }
        }, 100);
         } else if (modalMode === 'view' && viewId) {
        // Load images for view modal
        setTimeout(() => {
            // For view modal, get the SKU from the readonly field
            const skuField = document.getElementById('skuDisplay');
            if (skuField && skuField.value) {
                console.log('Loading current images for view modal:', skuField.value);
                loadCurrentImages(skuField.value, true);
            } else {
                console.log('No SKU found for view modal');
                const container = document.getElementById('currentImagesList');
                const loadingDiv = document.getElementById('viewModalImagesLoading');
                if (loadingDiv) loadingDiv.remove();
                if (container) container.innerHTML = '<div class="text-center text-gray-500 text-sm">No SKU available</div>';
            }
        }, 100);
    }
    
    // Load thumbnails for inventory list
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
});

// ==================== INLINE EDITING FUNCTIONALITY ====================

document.addEventListener('DOMContentLoaded', function() {
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
                    showToast('error', 'Value cannot be empty');
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
                        showToast('success', result.message || 'Updated successfully');
                        
                        // Update display with proper formatting
                        if (field === 'costPrice' || field === 'retailPrice') {
                            this.textContent = '$' + parseFloat(newValue).toFixed(2);
                        } else {
                            this.textContent = newValue;
                        }
                    } else {
                        showToast('error', result.error || 'Update failed');
                        this.textContent = currentText; // Restore original
                    }
                } catch (error) {
                    console.error('Update error:', error);
                    showToast('error', 'Failed to update: ' + error.message);
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
            maxHeight = Math.min(maxHeight, 400); // Cap at 400px
            
            console.log(`Setting carousel image height to: ${maxHeight}px`);
            
            // Apply the height to all image containers
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
    
    fetch('/process_inventory_update.php', {
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
            showToast('success', data.message);
        } else {
            cell.innerHTML = originalValue; // Restore original value
            showToast('error', data.error || 'Failed to update field');
        }
    })
    .catch(error => {
        console.error('Error updating field:', error);
        cell.innerHTML = originalValue; // Restore original value
        showToast('error', 'Failed to update field: ' + error.message);
    });
}

// Carousel navigation function
function moveCarousel(type, direction) {
    const trackId = type === 'edit' ? 'editCarouselTrack' : 'viewCarouselTrack';
    const positionVar = type === 'edit' ? 'editCarouselPosition' : 'viewCarouselPosition';
    
    const track = document.getElementById(trackId);
    if (!track) {
        console.log(`Carousel track not found: ${trackId}`);
        return;
    }
    
    const slides = track.querySelectorAll('.carousel-slide');
    const totalSlides = slides.length;
    const slidesToShow = 3; // Show 3 images at a time
    
    console.log(`Moving ${type} carousel, direction: ${direction}, total slides: ${totalSlides}`);
    
    // Only allow navigation if there are more than 3 images
    if (totalSlides <= slidesToShow) {
        console.log(`Not enough slides to navigate: ${totalSlides} <= ${slidesToShow}`);
        return;
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
    track.style.transform = `translateX(${translateX}px)`;
    
    console.log(`Moved to position ${currentPosition}, translateX: ${translateX}px, maxPosition: ${maxPosition}`);
    
    // Update button visibility
    updateCarouselNavigation(type, totalSlides);
}

function updateCarouselNavigation(type, totalSlides) {
    const trackId = type === 'edit' ? 'editCarouselTrack' : 'viewCarouselTrack';
    const positionVar = type === 'edit' ? 'editCarouselPosition' : 'viewCarouselPosition';
    
    const track = document.getElementById(trackId);
    if (!track) {
        console.log(`Track not found for navigation update: ${trackId}`);
        return;
    }
    
    const container = track.closest('.image-carousel-container');
    const prevBtn = container.querySelector('.carousel-prev');
    const nextBtn = container.querySelector('.carousel-next');
    
    const slidesToShow = 3;
    const currentPosition = window[positionVar] || 0;
    const maxPosition = Math.max(0, totalSlides - slidesToShow);
    
    console.log(`Updating ${type} navigation: totalSlides=${totalSlides}, currentPosition=${currentPosition}, maxPosition=${maxPosition}`);
    
    if (prevBtn) {
        prevBtn.style.display = currentPosition === 0 ? 'none' : 'block';
        console.log(`${type} prev button:`, currentPosition === 0 ? 'hidden' : 'visible');
    }
    if (nextBtn) {
        nextBtn.style.display = currentPosition >= maxPosition ? 'none' : 'block';
        console.log(`${type} next button:`, currentPosition >= maxPosition ? 'hidden' : 'visible');
    }
}

// Cost Breakdown Template Functions
function toggleTemplateSection() {
    const section = document.getElementById('templateSection');
    const toggleText = document.getElementById('templateToggleText');
    
    if (section.classList.contains('hidden')) {
        section.classList.remove('hidden');
        toggleText.textContent = 'Hide Templates';
        loadTemplateList();
    } else {
        section.classList.add('hidden');
        toggleText.textContent = 'Show Templates';
    }
}

function loadTemplateList() {
    const select = document.getElementById('templateSelect');
    const categoryField = document.getElementById('categoryEdit');
    const category = categoryField ? categoryField.value : '';
    
    // Clear existing options
    select.innerHTML = '<option value="">Choose a template...</option>';
    
    // Build URL with optional category filter
    let url = '/api/cost_breakdown_templates.php?action=list';
    if (category) {
        url += `&category=${encodeURIComponent(category)}`;
    }
    
    fetch(url)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.templates) {
            data.templates.forEach(template => {
                const option = document.createElement('option');
                option.value = template.id;
                option.textContent = `${template.template_name}${template.category ? ` (${template.category})` : ''}`;
                select.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Error loading templates:', error);
        showToast('error', 'Failed to load templates');
    });
}

function loadTemplate() {
    const select = document.getElementById('templateSelect');
    const templateId = select.value;
    
    if (!templateId) {
        showToast('error', 'Please select a template to load');
        return;
    }
    
    fetch(`/api/cost_breakdown_templates.php?action=get&id=${templateId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.template) {
            applyTemplateToBreakdown(data.template);
            showToast('success', `Template "${data.template.template_name}" loaded successfully!`);
        } else {
            showToast('error', data.error || 'Failed to load template');
        }
    })
    .catch(error => {
        console.error('Error loading template:', error);
        showToast('error', 'Failed to load template');
    });
}

function applyTemplateToBreakdown(template) {
    // Clear existing cost breakdown
    ['materials', 'labor', 'energy', 'equipment'].forEach(costType => {
        const list = document.getElementById(`${costType}List`);
        if (list) {
            list.innerHTML = '';
        }
    });
    
    // Apply template data
    const costTypes = ['materials', 'labor', 'energy', 'equipment'];
    costTypes.forEach(costType => {
        if (template[costType] && Array.isArray(template[costType])) {
            template[costType].forEach(item => {
                addCostItemFromTemplate(costType, item);
            });
        }
    });
    
    // Recalculate totals
    updateCostTotals();
}

function addCostItemFromTemplate(costType, itemData) {
    const list = document.getElementById(`${costType}List`);
    if (!list) return;
    
    const costItem = document.createElement('div');
    costItem.className = 'cost-item';
    
    const nameSpan = document.createElement('span');
    nameSpan.className = 'cost-item-name';
    nameSpan.textContent = itemData.name || '';
    
    const actionsDiv = document.createElement('div');
    actionsDiv.className = 'cost-item-actions';
    
    const valueSpan = document.createElement('span');
    valueSpan.className = 'cost-item-value';
    valueSpan.textContent = `$${parseFloat(itemData.cost || 0).toFixed(2)}`;
    
    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'cost-item-edit';
    editBtn.innerHTML = '✏️';
    editBtn.onclick = () => editCostItem(costItem, costType);
    
    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'cost-item-delete';
    deleteBtn.innerHTML = '🗑️';
    deleteBtn.onclick = () => deleteCostItem(costItem);
    
    actionsDiv.appendChild(valueSpan);
    actionsDiv.appendChild(editBtn);
    actionsDiv.appendChild(deleteBtn);
    
    costItem.appendChild(nameSpan);
    costItem.appendChild(actionsDiv);
    
    // Store the data
    costItem.dataset.name = itemData.name || '';
    costItem.dataset.cost = itemData.cost || '0';
    costItem.dataset.unit = itemData.unit || '';
    
    list.appendChild(costItem);
}

function saveAsTemplate() {
    const templateNameField = document.getElementById('templateName');
    const categoryField = document.getElementById('categoryEdit');
    const nameField = document.getElementById('name');
    
    const templateName = templateNameField.value.trim();
    if (!templateName) {
        showToast('error', 'Please enter a template name');
        return;
    }
    
    // Gather current cost breakdown data
    const templateData = {
        template_name: templateName,
        description: `Template created from ${nameField ? nameField.value : 'item'}`,
        category: categoryField ? categoryField.value : '',
        sku: currentItemSku || '',
        materials: [],
        labor: [],
        energy: [],
        equipment: []
    };
    
    // Extract cost data from current breakdown
    ['materials', 'labor', 'energy', 'equipment'].forEach(costType => {
        const list = document.getElementById(`${costType}List`);
        if (list) {
            const items = list.querySelectorAll('.cost-item');
            items.forEach(item => {
                templateData[costType].push({
                    name: item.dataset.name || '',
                    cost: parseFloat(item.dataset.cost || '0'),
                    unit: item.dataset.unit || ''
                });
            });
        }
    });
    
    // Save template
    fetch('/api/cost_breakdown_templates.php?action=save_from_breakdown', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(templateData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', `Template "${templateName}" saved successfully!`);
            templateNameField.value = '';
            loadTemplateList(); // Refresh the template list
        } else {
            showToast('error', data.error || 'Failed to save template');
        }
    })
    .catch(error => {
        console.error('Error saving template:', error);
        showToast('error', 'Failed to save template');
    });
}

// Marketing Manager Functions
function openMarketingManager() {
    const modal = document.getElementById('marketingManagerModal');
    if (modal) {
        modal.classList.remove('hidden');
        loadMarketingData();
    }
}

function closeMarketingManager() {
    const modal = document.getElementById('marketingManagerModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function showMarketingManagerTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.marketing-tab').forEach(tab => {
        tab.classList.remove('bg-white', 'text-purple-600', 'border-purple-600');
        tab.classList.add('text-gray-600');
    });
    
    const activeTab = document.getElementById(tabName + 'Tab');
    if (activeTab) {
        activeTab.classList.add('bg-white', 'text-purple-600', 'border-purple-600');
        activeTab.classList.remove('text-gray-600');
    }
    
    // Load tab content
    loadMarketingTabContent(tabName);
}

function loadMarketingData() {
    const contentDiv = document.getElementById('marketingManagerContent');
    contentDiv.innerHTML = `
        <div class="text-center py-8">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Loading marketing data...</p>
        </div>
    `;
    
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
    contentDiv.innerHTML = `
        <div class="space-y-6">
            <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-lg p-4">
                <div class="flex flex-col lg:flex-row lg:items-end gap-4">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">AI Content Generation</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Brand Voice</label>
                                <select id="brandVoice" class="w-full p-2 border border-purple-200 rounded">
                                    <option value="">Select voice...</option>
                                    <option value="friendly">Friendly & Approachable</option>
                                    <option value="professional">Professional & Trustworthy</option>
                                    <option value="playful">Playful & Fun</option>
                                    <option value="luxurious">Luxurious & Premium</option>
                                    <option value="casual">Casual & Relaxed</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Content Tone</label>
                                <select id="contentTone" class="w-full p-2 border border-purple-200 rounded">
                                    <option value="">Select tone...</option>
                                    <option value="informative">Informative</option>
                                    <option value="persuasive">Persuasive</option>
                                    <option value="emotional">Emotional</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="conversational">Conversational</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <button onclick="saveMarketingFields(['brand_voice', 'content_tone'])" class="bg-purple-100 hover:bg-purple-200 text-purple-700 px-3 py-2 rounded text-sm">
                            💾 Save Settings
                        </button>
                        <button onclick="generateNewMarketingContent()" class="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white px-4 py-2 rounded-lg font-semibold shadow-lg">
                            🧠 Generate AI Content for All Tabs
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-blue-50 rounded-lg p-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Product Title</label>
                    <textarea id="marketingTitle" class="w-full p-3 border border-blue-200 rounded-lg" rows="2" placeholder="Enter enhanced product title..."></textarea>
                    <div class="mt-2 flex justify-between">
                        <button onclick="applyMarketingTitle()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                            📝 Apply & Save to Product
                        </button>
                        <button onclick="saveMarketingField('suggested_title')" class="text-blue-600 hover:text-blue-800 text-sm underline">
                            Save Draft
                        </button>
                    </div>
                </div>
                
                <div class="bg-green-50 rounded-lg p-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Product Description</label>
                    <textarea id="marketingDescription" class="w-full p-3 border border-green-200 rounded-lg" rows="4" placeholder="Enter detailed product description..."></textarea>
                    <div class="mt-2 flex justify-between">
                        <button onclick="applyMarketingDescription()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                            📝 Apply & Save to Product
                        </button>
                        <button onclick="saveMarketingField('suggested_description')" class="text-green-600 hover:text-green-800 text-sm underline">
                            Save Draft
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Load existing data
    loadExistingMarketingData();
}

function loadAudienceTab(contentDiv) {
    contentDiv.innerHTML = `
        <div class="space-y-6">
            <h3 class="text-lg font-semibold text-gray-800">Target Audience Management</h3>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-orange-50 rounded-lg p-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Primary Target Audience</label>
                    <textarea id="targetAudience" class="w-full p-3 border border-orange-200 rounded-lg" rows="3" placeholder="Describe your ideal customer..."></textarea>
                    <button onclick="saveMarketingField('target_audience')" class="mt-2 text-orange-600 hover:text-orange-800 text-sm">Save</button>
                </div>
                
                <div class="bg-pink-50 rounded-lg p-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Demographics</label>
                    <textarea id="demographics" class="w-full p-3 border border-pink-200 rounded-lg" rows="3" placeholder="Age, gender, income, location..."></textarea>
                    <button onclick="saveMarketingField('demographic_targeting')" class="mt-2 text-pink-600 hover:text-pink-800 text-sm">Save</button>
                </div>
            </div>
            
            <div class="bg-indigo-50 rounded-lg p-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Psychographic Profile</label>
                <textarea id="psychographics" class="w-full p-3 border border-indigo-200 rounded-lg" rows="3" placeholder="Interests, values, lifestyle, personality traits..."></textarea>
                <button onclick="saveMarketingField('psychographic_profile')" class="mt-2 text-indigo-600 hover:text-indigo-800 text-sm">Save</button>
            </div>
        </div>
    `;
    
    loadExistingMarketingData();
}

function loadSellingTab(contentDiv) {
    contentDiv.innerHTML = `
        <div class="space-y-6">
            <h3 class="text-lg font-semibold text-gray-800">Selling Points & Advantages</h3>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-green-50 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">Key Selling Points</label>
                        <button onclick="addListItem('selling_points')" class="text-green-600 hover:text-green-800 text-sm">+ Add</button>
                    </div>
                    <div id="sellingPointsList" class="space-y-2 mb-3">
                        <!-- Dynamic content -->
                    </div>
                    <input type="text" id="newSellingPoint" placeholder="Enter new selling point..." class="w-full p-2 border border-green-200 rounded" onkeypress="if(event.key==='Enter') addListItem('selling_points')">
                </div>
                
                <div class="bg-red-50 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">Competitive Advantages</label>
                        <button onclick="addListItem('competitive_advantages')" class="text-red-600 hover:text-red-800 text-sm">+ Add</button>
                    </div>
                    <div id="competitiveAdvantagesList" class="space-y-2 mb-3">
                        <!-- Dynamic content -->
                    </div>
                    <input type="text" id="newCompetitiveAdvantage" placeholder="What makes you better..." class="w-full p-2 border border-red-200 rounded" onkeypress="if(event.key==='Enter') addListItem('competitive_advantages')">
                </div>
            </div>
            
            <div class="bg-yellow-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium text-gray-700">Customer Benefits</label>
                    <button onclick="addListItem('customer_benefits')" class="text-yellow-600 hover:text-yellow-800 text-sm">+ Add</button>
                </div>
                <div id="customerBenefitsList" class="space-y-2 mb-3">
                    <!-- Dynamic content -->
                </div>
                <input type="text" id="newCustomerBenefit" placeholder="What benefit does customer get..." class="w-full p-2 border border-yellow-200 rounded" onkeypress="if(event.key==='Enter') addListItem('customer_benefits')">
            </div>
        </div>
    `;
    
    loadExistingMarketingData();
}

function loadSEOTab(contentDiv) {
    contentDiv.innerHTML = `
        <div class="space-y-6">
            <h3 class="text-lg font-semibold text-gray-800">SEO & Keywords</h3>
            
            <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium text-gray-700">SEO Keywords</label>
                    <button onclick="addListItem('seo_keywords')" class="text-blue-600 hover:text-blue-800 text-sm">+ Add</button>
                </div>
                <div id="seoKeywordsList" class="space-y-2 mb-3">
                    <!-- Dynamic content -->
                </div>
                <input type="text" id="newSEOKeyword" placeholder="Enter keyword or phrase..." class="w-full p-2 border border-blue-200 rounded" onkeypress="if(event.key==='Enter') addListItem('seo_keywords')">
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-purple-50 rounded-lg p-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Intent</label>
                    <select id="searchIntent" class="w-full p-2 border border-purple-200 rounded">
                        <option value="">Select intent...</option>
                        <option value="informational">Informational</option>
                        <option value="navigational">Navigational</option>
                        <option value="transactional">Transactional</option>
                        <option value="commercial">Commercial Investigation</option>
                    </select>
                    <button onclick="saveMarketingField('search_intent')" class="mt-2 text-purple-600 hover:text-purple-800 text-sm">Save</button>
                </div>
                
                <div class="bg-green-50 rounded-lg p-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Seasonal Relevance</label>
                    <textarea id="seasonalRelevance" class="w-full p-3 border border-green-200 rounded-lg" rows="3" placeholder="Christmas, summer, back-to-school, etc..."></textarea>
                    <button onclick="saveMarketingField('seasonal_relevance')" class="mt-2 text-green-600 hover:text-green-800 text-sm">Save</button>
                </div>
            </div>
        </div>
    `;
    
    loadExistingMarketingData();
}

function loadConversionTab(contentDiv) {
    contentDiv.innerHTML = `
        <div class="space-y-6">
            <h3 class="text-lg font-semibold text-gray-800">Conversion Optimization</h3>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-orange-50 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">Call-to-Action Suggestions</label>
                        <button onclick="addListItem('call_to_action_suggestions')" class="text-orange-600 hover:text-orange-800 text-sm">+ Add</button>
                    </div>
                    <div id="callToActionsList" class="space-y-2 mb-3">
                        <!-- Dynamic content -->
                    </div>
                    <input type="text" id="newCallToAction" placeholder="Get Yours Today, Buy Now, etc..." class="w-full p-2 border border-orange-200 rounded" onkeypress="if(event.key==='Enter') addListItem('call_to_action_suggestions')">
                </div>
                
                <div class="bg-red-50 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">Urgency Factors</label>
                        <button onclick="addListItem('urgency_factors')" class="text-red-600 hover:text-red-800 text-sm">+ Add</button>
                    </div>
                    <div id="urgencyFactorsList" class="space-y-2 mb-3">
                        <!-- Dynamic content -->
                    </div>
                    <input type="text" id="newUrgencyFactor" placeholder="Limited time, while supplies last..." class="w-full p-2 border border-red-200 rounded" onkeypress="if(event.key==='Enter') addListItem('urgency_factors')">
                </div>
            </div>
            
            <div class="bg-purple-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium text-gray-700">Conversion Triggers</label>
                    <button onclick="addListItem('conversion_triggers')" class="text-purple-600 hover:text-purple-800 text-sm">+ Add</button>
                </div>
                <div id="conversionTriggersList" class="space-y-2 mb-3">
                    <!-- Dynamic content -->
                </div>
                <input type="text" id="newConversionTrigger" placeholder="Free shipping, money-back guarantee..." class="w-full p-2 border border-purple-200 rounded" onkeypress="if(event.key==='Enter') addListItem('conversion_triggers')">
            </div>
        </div>
    `;
    
    loadExistingMarketingData();
}

function loadExistingMarketingData() {
    if (!currentItemSku) return Promise.resolve();
    
    return fetch(`/api/marketing_manager.php?action=get_marketing_data&sku=${currentItemSku}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            populateMarketingFields(data.data);
        }
        return data;
    })
    .catch(error => {
        console.error('Error loading marketing data:', error);
        throw error;
    });
}

function populateMarketingFields(data) {
    // Populate text fields
    const textFields = {
        'marketingTitle': 'suggested_title',
        'marketingDescription': 'suggested_description',
        'targetAudience': 'target_audience',
        'demographics': 'demographic_targeting',
        'psychographics': 'psychographic_profile',
        'brandVoice': 'brand_voice',
        'contentTone': 'content_tone',
        'searchIntent': 'search_intent',
        'seasonalRelevance': 'seasonal_relevance'
    };
    
    Object.keys(textFields).forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && data[textFields[fieldId]]) {
            field.value = data[textFields[fieldId]];
        }
    });
    
    // Populate list fields
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
        <button onclick="removeListItem('${fieldName}', '${item}')" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
    `;
    
    list.appendChild(itemDiv);
}

function addListItem(fieldName) {
    const inputId = 'new' + fieldName.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join('');
    const input = document.getElementById(inputId);
    
    if (!input || !input.value.trim()) {
        showToast('error', 'Please enter a value');
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
            showToast('success', 'Item added successfully');
        } else {
            showToast('error', data.error || 'Failed to add item');
        }
    })
    .catch(error => {
        console.error('Error adding list item:', error);
        showToast('error', 'Failed to add item');
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
            showToast('success', 'Item removed successfully');
        } else {
            showToast('error', data.error || 'Failed to remove item');
        }
    })
    .catch(error => {
        console.error('Error removing list item:', error);
        showToast('error', 'Failed to remove item');
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
        showToast('error', 'Field not found');
        return;
    }
    
    const value = field.value.trim();
    if (!value) {
        showToast('error', 'Please enter a value');
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
            showToast('success', 'Field saved successfully');
        } else {
            showToast('error', data.error || 'Failed to save field');
        }
    })
    .catch(error => {
        console.error('Error saving field:', error);
        showToast('error', 'Failed to save field');
    });
}

function saveMarketingFields(fieldNames) {
    if (!Array.isArray(fieldNames) || fieldNames.length === 0) {
        showToast('error', 'No fields specified');
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
        showToast('error', 'Please enter values for the fields');
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
            showToast('success', `All ${fieldNames.length} fields saved successfully`);
        } else {
            const failedCount = results.filter(result => !result.success).length;
            showToast('warning', `${fieldNames.length - failedCount} fields saved, ${failedCount} failed`);
        }
    })
    .catch(error => {
        console.error('Error saving fields:', error);
        showToast('error', 'Failed to save fields');
    });
}

function applyMarketingTitle() {
    const titleField = document.getElementById('marketingTitle');
    const nameField = document.getElementById('name');
    
    if (titleField && nameField && titleField.value.trim()) {
        const newTitle = titleField.value.trim();
        nameField.value = newTitle;
        nameField.style.backgroundColor = '#f3e8ff';
        
        // Auto-save the product with the new title
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
                showToast('success', 'Title applied and product saved automatically!');
            } else {
                console.error('API error:', data);
                showToast('error', 'Failed to save: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error auto-saving product:', error);
            showToast('error', 'Network error: ' + error.message);
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
                showToast('success', 'Description applied and product saved automatically!');
            } else {
                console.error('API error:', data);
                showToast('error', 'Failed to save: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error auto-saving product:', error);
            showToast('error', 'Network error: ' + error.message);
        });
        
        setTimeout(() => {
            productDescField.style.backgroundColor = '';
        }, 2000);
    }
}

function addAIContentBadges(tabNames) {
    tabNames.forEach(tabName => {
        const tabButton = document.getElementById(tabName + 'Tab');
        if (tabButton && !tabButton.querySelector('.ai-badge')) {
            const badge = document.createElement('span');
            badge.className = 'ai-badge ml-1 bg-green-500 text-white text-xs px-1 py-0.5 rounded';
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
        const successCount = results.filter(r => r.success).length;
        console.log(`Successfully saved ${successCount}/${fieldsToSave.length} marketing fields`);
        
                 // Add visual indicators to tabs that now have AI content
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
    
    // Get current item data
    const itemData = {
        sku: currentItemSku,
        name: document.getElementById('name')?.value || '',
        description: document.getElementById('description')?.value || '',
        category: document.getElementById('categoryEdit')?.value || '',
        retailPrice: document.getElementById('retailPrice')?.value || '',
        // Include brand voice and tone preferences
        brandVoice: currentBrandVoice,
        contentTone: currentContentTone
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
            showToast('success', '🎯 AI content generated for: Target Audience, Selling Points, SEO & Keywords, and Conversion tabs!');
            
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
            });
        } else {
            showToast('error', data.error || 'Failed to generate marketing content');
        }
    })
    .catch(error => {
        console.error('Error generating marketing content:', error);
        showToast('error', 'Failed to generate marketing content');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}
</script>

<!-- Marketing Manager Modal -->
<div id="marketingManagerModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-7xl max-h-[95vh] overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4 flex justify-between items-center">
            <h2 class="text-xl font-bold text-white">🎯 Marketing Manager</h2>
            <button onclick="closeMarketingManager()" class="text-white hover:text-gray-200 text-2xl font-bold">&times;</button>
        </div>
        
        <!-- Tab Navigation -->
        <div class="bg-gray-50 px-6 py-2 border-b">
            <div class="flex space-x-4">
                <button id="contentTab" class="marketing-tab px-4 py-2 rounded-t-lg bg-white text-purple-600 border-b-2 border-purple-600 font-semibold" onclick="showMarketingManagerTab('content')">Content & Copy</button>
                <button id="audienceTab" class="marketing-tab px-4 py-2 rounded-t-lg text-gray-600 hover:text-purple-600" onclick="showMarketingManagerTab('audience')">Target Audience</button>
                <button id="sellingTab" class="marketing-tab px-4 py-2 rounded-t-lg text-gray-600 hover:text-purple-600" onclick="showMarketingManagerTab('selling')">Selling Points</button>
                <button id="seoTab" class="marketing-tab px-4 py-2 rounded-t-lg text-gray-600 hover:text-purple-600" onclick="showMarketingManagerTab('seo')">SEO & Keywords</button>
                <button id="conversionTab" class="marketing-tab px-4 py-2 rounded-t-lg text-gray-600 hover:text-purple-600" onclick="showMarketingManagerTab('conversion')">Conversion</button>
            </div>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[calc(95vh-140px)]">
            <div id="marketingManagerContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<?php
$output = ob_get_clean();
echo $output;
?>


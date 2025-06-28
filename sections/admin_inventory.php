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
    
    /* Brand button styling - Enhanced with better hover effects */
    .brand-button {
        background-color: #87ac3a !important;
        color: white !important;
        transition: all 0.2s ease !important;
        border: none !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-decoration: none !important;
        font-weight: 500 !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
    }
    
    .brand-button:hover {
        background-color: #6b8e23 !important; /* Darker shade for hover */
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15) !important;
    }
    
    .brand-button:active {
        transform: translateY(0) !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
    }
    
    .toast-notification {
        position: fixed; 
        top: 20px; 
        right: 20px; 
        padding: 16px 20px;
        border-radius: 12px; 
        color: white; 
        font-weight: 500; 
        z-index: 9999;
        opacity: 0; 
        transform: translateY(-20px) translateX(100px); 
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        max-width: 400px;
        min-width: 300px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        gap: 12px;
        font-family: system-ui, -apple-system, sans-serif;
    }
    
    .toast-notification.show { 
        opacity: 1; 
        transform: translateY(0) translateX(0); 
    }
    
    .toast-notification.success { 
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-color: rgba(16, 185, 129, 0.3);
    }
    
    .toast-notification.error { 
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        border-color: rgba(239, 68, 68, 0.3);
    }
    
    .toast-notification.info { 
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-color: rgba(59, 130, 246, 0.3);
    }
    
    .toast-icon {
        font-size: 20px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
    }
    
    .toast-content {
        flex: 1;
        font-size: 14px;
        line-height: 1.4;
    }
    
    .toast-close {
        background: none;
        border: none;
        color: rgba(255,255,255,0.8);
        cursor: pointer;
        font-size: 18px;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    
    .toast-close:hover {
        background: rgba(255,255,255,0.2);
        color: white;
    }

    .inventory-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; table-layout: fixed; }
    .inventory-table th { background-color: #87ac3a; color: white; padding: 10px 12px; text-align: left; font-weight: 600; font-size: 0.8rem; position: sticky; top: 0; z-index: 10; }
    .inventory-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 0.85rem; overflow: hidden; text-overflow: ellipsis; }
    .inventory-table tr:hover { background-color: #f7fafc; }
    .inventory-table th:first-child { border-top-left-radius: 6px; }
    .inventory-table th:last-child { border-top-right-radius: 6px; }
    
    /* Fixed column widths to prevent resizing during inline editing */
    .inventory-table th:nth-child(1), .inventory-table td:nth-child(1) { width: 60px; } /* Image */
    .inventory-table th:nth-child(2), .inventory-table td:nth-child(2) { width: 70px; } /* Images */
    .inventory-table th:nth-child(3), .inventory-table td:nth-child(3) { width: 180px; } /* Name */
    .inventory-table th:nth-child(4), .inventory-table td:nth-child(4) { width: 120px; } /* Category */
    .inventory-table th:nth-child(5), .inventory-table td:nth-child(5) { width: 100px; } /* SKU */
    .inventory-table th:nth-child(6), .inventory-table td:nth-child(6) { width: 80px; } /* Stock */
    .inventory-table th:nth-child(7), .inventory-table td:nth-child(7) { width: 90px; } /* Reorder Point */
    .inventory-table th:nth-child(8), .inventory-table td:nth-child(8) { width: 90px; } /* Cost Price */
    .inventory-table th:nth-child(9), .inventory-table td:nth-child(9) { width: 90px; } /* Retail Price */
    .inventory-table th:nth-child(10), .inventory-table td:nth-child(10) { width: 120px; } /* Actions */
    
    /* Responsive adjustments for smaller screens */
    @media (max-width: 1200px) {
        .inventory-table { table-layout: auto; }
        .inventory-table th, .inventory-table td { width: auto !important; min-width: 60px; }
        .inventory-table th:nth-child(3), .inventory-table td:nth-child(3) { min-width: 120px; } /* Name */
        .inventory-table th:nth-child(6), .inventory-table td:nth-child(6) { min-width: 60px; } /* Stock */
        .inventory-table th:nth-child(7), .inventory-table td:nth-child(7) { min-width: 70px; } /* Reorder Point */
    }

    .action-btn { padding: 5px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; border: none; }
    .view-btn { background-color: #4299e1; color: white; } .view-btn:hover { background-color: #3182ce; }
    .edit-btn { background-color: #f59e0b; color: white; } .edit-btn:hover { background-color: #d97706; }
    .marketing-btn { background-color: #8b5cf6; color: white; } .marketing-btn:hover { background-color: #7c3aed; }
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
    /* Navigation Arrow Styling */
    .nav-arrow {
        position: fixed;
        top: 50%;
        transform: translateY(-50%);
        z-index: 60; /* Higher than modal z-index */
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(4px);
        color: white;
        border: none;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .nav-arrow:hover {
        background: rgba(0, 0, 0, 0.5);
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    }
    
    .nav-arrow:active {
        transform: translateY(-50%) scale(0.95);
    }
    
    .nav-arrow svg {
        width: 24px;
        height: 24px;
        stroke-width: 2.5;
    }
    
    .nav-arrow.left {
        left: 20px;
    }
    
    .nav-arrow.right {
        right: 20px;
    }
    
    /* Hide arrows on smaller screens to avoid overlap */
    @media (max-width: 768px) {
        .nav-arrow {
            display: none;
        }
    }

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

/* Marketing Manager Modal Visibility Fix */
#marketingManagerModal {
    display: none !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background-color: rgba(0, 0, 0, 0.5) !important;
    z-index: 2147483647 !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 1rem !important;
}

#marketingManagerModal.show {
    display: flex !important;
}

#marketingManagerModal .modal-content {
    background: white !important;
    border-radius: 8px !important;
    max-width: 90vw !important;
    max-height: 90vh !important;
    overflow-y: auto !important;
    z-index: 2147483648 !important;
    position: relative !important;
}

/* Custom scrollbar styles for cost suggestion modal */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f7fafc;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 12px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f7fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 6px;
    border: 2px solid #f7fafc;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

.custom-scrollbar::-webkit-scrollbar-corner {
    background: #f7fafc;
}
</style>

<div class="container mx-auto px-4 py-2">
    <div class="flex flex-col md:flex-row justify-between items-center mb-5 gap-4">
        
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
            <button type="button" onclick="refreshCategoryDropdown().then(() => showSuccess( 'Categories refreshed!'))" class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded text-sm" title="Refresh Categories">🔄</button>
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
                    <th>Image</th><th>Images</th><th>Name</th><th>Category</th><th>SKU</th><th>Stock</th>
                    <th>Reorder Point</th><th>Cost Price</th><th>Retail Price</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="10" class="text-center py-4">No items found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr data-sku="<?= htmlspecialchars($item['sku'] ?? '') ?>" class="<?= (isset($_GET['highlight']) && $_GET['highlight'] == $item['sku']) ? 'bg-yellow-100' : '' ?> hover:bg-gray-50">
                        <td>
                            <div class="thumbnail-container" data-sku="<?= htmlspecialchars($item['sku'] ?? '') ?>" style="width:40px;height:40px;">
                                <div class="thumbnail-loading" style="width:40px;height:40px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#999;">...</div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= ($item['image_count'] > 0) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
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
    <!-- Navigation Arrows -->
    <button id="prevItemBtn" onclick="navigateToItem('prev')" class="nav-arrow left" title="Previous item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
        </svg>
    </button>
    <button id="nextItemBtn" onclick="navigateToItem('next')" class="nav-arrow right" title="Next item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
        </svg>
    </button>
    
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
                            
                            <!-- Price Suggestion Display -->
                            <div id="viewPriceSuggestionDisplay" class="mb-4 hidden">
                                
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
                    <div class="text-sm">No price suggestion available</div>
                    <div class="text-xs mt-1 text-gray-400">Price suggestions are generated in edit mode</div>
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
    <!-- Navigation Arrows (only show for edit mode, not add mode) -->
    <?php if ($modalMode === 'edit'): ?>
    <button id="prevItemBtn" onclick="navigateToItem('prev')" class="nav-arrow left" title="Previous item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
        </svg>
    </button>
    <button id="nextItemBtn" onclick="navigateToItem('next')" class="nav-arrow right" title="Next item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
        </svg>
    </button>
    <?php endif; ?>
    
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
                                   value="<?= htmlspecialchars($editItem['name'] ?? ''); ?>"
                                   data-tooltip="The name of your item. Try to be more creative than 'Thing' or 'Stuff'. Your customers deserve better than that.">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="categoryEdit" class="block text-gray-700">Category *</label>
                            <select id="categoryEdit" name="category" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('category', $field_errors) ? 'field-error-highlight' : '' ?>" required <?= $modalMode === 'add' ? 'style="display:none;"' : '' ?>
                                    data-tooltip="Which category does this belong to? If you can't figure this out, maybe running a business isn't for you.">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat); ?>" <?= (isset($editItem['category']) && $editItem['category'] === $cat) ? 'selected' : ''; ?>><?= htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($modalMode === 'add'): ?>
                            <div id="aiCategoryMessage" class="mt-1 p-3 bg-blue-50 border border-blue-200 rounded text-sm text-blue-800">
                                🤖 AI will automatically select the best category after you upload a photo and we analyze your item!
                            </div>
                            <?php endif; ?>
                        </div>
                            <div class="flex items-end">
                                <button type="button" id="open-marketing-manager-btn" class="brand-button px-3 py-2 rounded text-sm"
                                        data-tooltip="Let AI write your marketing copy because apparently describing your own products is too hard. Don't worry, the robots are better at it anyway.">
                                     🎯 Marketing Manager
                                </button>
                        </div>
                    </div>

                     <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="stockLevel" class="block text-gray-700">Stock Level *</label>
                            <input type="number" id="stockLevel" name="stockLevel" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('stockLevel', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required 
                                   value="<?= htmlspecialchars($editItem['stockLevel'] ?? '0'); ?>"
                                   data-tooltip="How many of these do you actually have? Don't lie - we're not your accountant, but your customers will be mad if you oversell.">
                        </div>
                        <div>
                            <label for="reorderPoint" class="block text-gray-700">Reorder Point *</label>
                            <input type="number" id="reorderPoint" name="reorderPoint" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('reorderPoint', $field_errors) ? 'field-error-highlight' : '' ?>" min="0" required 
                                   value="<?= htmlspecialchars($editItem['reorderPoint'] ?? '5'); ?>"
                                   data-tooltip="When to panic and order more. Set this too low and you'll run out. Set it too high and you'll have a warehouse full of stuff nobody wants.">
                        </div>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="costPrice" class="block text-gray-700">Cost Price ($) *</label>
                            <input type="number" id="costPrice" name="costPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('costPrice', $field_errors) ? 'field-error-highlight' : '' ?>" step="0.01" min="0" required 
                                   value="<?= htmlspecialchars($editItem['costPrice'] ?? '0.00'); ?>"
                                   data-tooltip="How much you paid for this. Don't include your tears and frustration - those are free. This is just the cold, hard cash you spent.">
                        </div>
                        <div>
                            <label for="retailPrice" class="block text-gray-700">Retail Price ($) *</label>
                            <input type="number" id="retailPrice" name="retailPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded <?= in_array('retailPrice', $field_errors) ? 'field-error-highlight' : '' ?>" step="0.01" min="0" required 
                                   value="<?= htmlspecialchars($editItem['retailPrice'] ?? '0.00'); ?>"
                                   data-tooltip="What you're charging customers. Try to make it higher than your cost price - that's how profit works. Revolutionary concept, I know.">
                        </div>
                    </div>
                    <div>
                        <label for="description" class="block text-gray-700">Description</label>
                        <textarea id="description" name="description" class="mt-1 block w-full p-2 border border-gray-300 rounded" rows="3" placeholder="Enter item description or click 'Marketing Manager' for AI-powered suggestions..."
                                  data-tooltip="Describe your item. Be more creative than 'It's good' or 'People like it'. Your customers have questions, and this is where you answer them."><?= htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
                    </div>
                    <!-- Item Images Section - Now spans full width when needed -->
                    <div class="images-section-container" id="imagesSection">
                        
                        <!-- Current Images Display -->
                        <div id="currentImagesContainer" class="current-images-section">
                            <div class="flex justify-between items-center mb-2">
                                <div class="text-sm text-gray-600">Current Images:</div>
                                <button type="button" id="processExistingImagesBtn" onclick="processExistingImagesWithAI()" class="px-2 py-1 bg-purple-500 text-white rounded text-xs hover:bg-purple-600 transition-colors" style="<?= $modalMode === 'view' ? 'display: none;' : '' ?>" data-tooltip="Let AI automatically crop all existing images to their edges and convert them to WebP format. Because apparently manually cropping photos is too much work for you.">
                                    🎨 AI Process All
                                </button>
                            </div>
                            <div id="currentImagesList" class="w-full">
                                <!-- Current images will be loaded here with dynamic layout -->
                            </div>
                        </div>
                        
                        <!-- Multi-Image Upload Section - Only show in edit/add mode -->
                        <div class="multi-image-upload-section mt-3" style="<?= $modalMode === 'view' ? 'display: none;' : '' ?>">
                            <input type="file" id="multiImageUpload" name="images[]" multiple accept="image/*" class="hidden">
                            <?php if ($modalMode === 'add'): ?>
                            <input type="file" id="aiAnalysisUpload" accept="image/*" class="hidden">
                            <?php endif; ?>
                            <div class="upload-controls mb-3">
                                <div class="flex gap-2 flex-wrap">
                                    <?php if ($modalMode === 'add'): ?>
                                    <button type="button" onclick="document.getElementById('aiAnalysisUpload').click()" class="px-3 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 text-sm">
                                        🤖 Upload Photo for AI Analysis
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" onclick="document.getElementById('multiImageUpload').click()" class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                                        📁 Upload Images
                                    </button>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Maximum file size: 10MB per image. Supported formats: PNG, JPG, JPEG, WebP, GIF
                                </div>
                                <div class="mt-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" id="useAIProcessing" name="useAIProcessing" class="mr-2" checked>
                                        <span class="text-sm font-medium text-gray-700">🎨 Auto-crop to edges with AI</span>
                                    </label>
                                    <div class="text-xs text-gray-500 mt-1 ml-6">
                                        Automatically detect and crop to the outermost edges of objects in your images
                                    </div>
                                </div>
                                <div id="uploadProgress" class="mt-2 hidden">
                                    <div class="text-sm text-gray-600 mb-2">Uploading images...</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div id="uploadProgressBar" class="bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Note: Images now managed through item_images table and image carousel above -->
                    </div>
                    
                    <!-- Color Management Section -->
                    <div class="color-management-section mt-4" style="<?= $modalMode === 'view' ? 'display: none;' : '' ?>">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <span class="mr-2">🎨</span> Color Options
                            </h3>
                            <div class="flex space-x-2">
                                <button type="button" onclick="openColorTemplateModal()" class="px-3 py-2 bg-purple-600 text-white rounded text-sm hover:bg-purple-700 transition-colors" title="Apply color template">
                                    📋 Templates
                                </button>
                                <button type="button" onclick="syncStockLevels()" class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors" title="Sync total stock with color quantities">
                                    🔄 Sync Stock
                                </button>
                                <button type="button" onclick="addNewColor()" class="px-3 py-2 text-white rounded text-sm transition-colors" style="background-color: #87ac3a;" onmouseover="this.style.backgroundColor='#6b8e23'" onmouseout="this.style.backgroundColor='#87ac3a'">
                                    + Add Color
                                </button>
                            </div>
                        </div>
                        
                        <div id="colorsList" class="space-y-2">
                            <!-- Colors will be loaded here -->
                            <div class="text-center text-gray-500 text-sm" id="colorsLoading">Loading colors...</div>
                        </div>
                    </div>
                    
                    <!-- Size Management Section -->
                    <div class="size-management-section mt-4" style="<?= $modalMode === 'view' ? 'display: none;' : '' ?>">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <span class="mr-2">📏</span> Size Options
                            </h3>
                            <div class="flex space-x-2">
                                <button type="button" onclick="openSizeTemplateModal()" class="px-3 py-2 bg-purple-600 text-white rounded text-sm hover:bg-purple-700 transition-colors" title="Apply size template">
                                    📋 Templates
                                </button>
                                <button type="button" onclick="syncSizeStock()" class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors" title="Sync stock levels with size quantities">
                                    🔄 Sync Stock
                                </button>
                                <button type="button" onclick="addNewSize()" class="px-3 py-2 text-white rounded text-sm transition-colors" style="background-color: #87ac3a;" onmouseover="this.style.backgroundColor='#6b8e23'" onmouseout="this.style.backgroundColor='#87ac3a'">
                                    + Add Size
                                </button>
                            </div>
                        </div>
                        
                        <!-- Size Configuration Toggle -->
                        <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-blue-800 text-sm">📋 Size Configuration</h4>
                                <div class="text-xs text-blue-600">
                                    Choose how sizes work for this item
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="sizeConfiguration" value="none" class="mr-2" checked onchange="updateSizeConfiguration()">
                                    <span class="text-sm">No sizes - Single item</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="sizeConfiguration" value="general" class="mr-2" onchange="updateSizeConfiguration()">
                                    <span class="text-sm">General sizes - Same item in different sizes</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="sizeConfiguration" value="color_specific" class="mr-2" onchange="updateSizeConfiguration()">
                                    <span class="text-sm">Color-specific sizes - Different sizes for each color</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Size Type Selector (for color-specific mode) -->
                        <div id="sizeTypeSelector" class="mb-3 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Color for Size Management:</label>
                            <select id="sizeColorFilter" class="w-full px-3 py-2 border border-gray-300 rounded text-sm" onchange="loadItemSizes()">
                                <option value="general">General Sizes (No Color)</option>
                                <!-- Color options will be loaded here -->
                            </select>
                        </div>
                        
                        <div id="sizesList" class="space-y-2">
                            <!-- Sizes will be loaded here -->
                            <div class="text-center text-gray-500 text-sm" id="sizesLoading">Loading sizes...</div>
                        </div>
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
                                
                                <button type="button" onclick="useSuggestedCost()" class="w-full px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors mb-4" 
                                        id="get-suggested-cost-btn" data-tooltip="Let AI analyze your item and suggest cost breakdown including materials, labor, energy, and equipment. Because apparently calculating costs is rocket science now.">
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
                                    <button type="button" onclick="applyCostSuggestionToCost()" class="w-full px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors"
                                            id="apply-suggested-cost-btn" data-tooltip="Take the AI-suggested cost and put it in your cost field. For when you trust robots more than your own business judgment.">
                                        💰 Apply to Cost Field
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
                                    <button type="button" class="add-cost-btn" onclick="addCostItem('<?= $costType; ?>')" 
                                            id="add-<?= $costType; ?>-btn" data-tooltip="<?php 
                                                $tooltips = [
                                                    'materials' => 'Add raw materials and supplies to your cost breakdown. Wood, fabric, glue, tears of frustration - whatever goes into making your product.',
                                                    'labor' => 'Add time and effort costs. Your hours, assistant wages, the cost of your sanity - everything that involves human effort to create this masterpiece.',
                                                    'energy' => 'Add electricity, gas, and other utilities used in production. Because apparently even the power company wants a cut of your profits.',
                                                    'equipment' => 'Add tool depreciation, equipment rental, and machinery costs. That expensive printer, cutting machine, or whatever gadget you convinced yourself was \"essential\" for the business.'
                                                ];
                                                echo $tooltips[$costType] ?? 'Add cost items for this category.';
                                            ?>">
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
                                
                                <button type="button" onclick="useSuggestedPrice()" class="w-full px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors font-medium mb-4"
                                        id="get-suggested-price-btn" data-tooltip="Let AI analyze your item and suggest optimal pricing based on cost analysis, market research, and competitive analysis. Because apparently setting prices is too complicated for humans now.">
                                    🎯 Get Suggested Price
                                </button>
                                
                                <!-- Price Suggestion Display -->
                                <div id="priceSuggestionDisplay" class="mb-4 hidden">
                                    
                                    <!-- Suggested Price Display -->
                                    <div class="mb-3 p-2 bg-green-50 rounded border border-green-200">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-green-700 font-medium">Suggested Price:</span>
                                            <span class="font-bold text-green-800 text-lg" id="displaySuggestedPrice">$0.00</span>
                                        </div>
                                    </div>
                                    
                                    <button type="button" onclick="applyPriceSuggestion()" class="w-full px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors mb-3"
                                            id="apply-suggested-price-btn" data-tooltip="Take the AI-suggested price and put it in your price field. Let the robots do your pricing - what could go wrong?">
                                        Apply to Retail Price
                                    </button>
                                    
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

<?php include __DIR__ . '/../components/ai_processing_modal.php'; ?>

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

<!-- Marketing Manager Modal -->
<div id="marketingManagerModal" class="admin-modal-overlay" style="z-index: 2147483647; display: none;">
    <div class="admin-modal-content">
        <!-- Modal Header -->
        <div class="admin-modal-header" style="background: linear-gradient(to right, #87ac3a, #6b8e23); position: relative;">
            <div class="flex items-center">
                <h2 class="text-xl font-bold text-white mr-3">🎯 Marketing Manager</h2>
                <span class="text-green-100 text-sm font-medium px-2 py-1 bg-green-800 bg-opacity-30 rounded">Currently editing: <span id="currentEditingSku"></span></span>
            </div>
            <button onclick="closeMarketingManager()" class="modal-close">&times;</button>
        </div>
        
        <!-- Tab Navigation -->
        <div class="admin-tab-bar">
            <div class="flex items-center">
                <div id="marketingItemImageHeader" class="flex-shrink-0 mr-4">
                    <!-- Primary image will be loaded here -->
                </div>
                <div class="flex space-x-4 overflow-x-auto">
                    <button id="contentTab" class="css-category-tab active" onclick="showMarketingManagerTab('content')">📝 Content</button>
                    <button id="audienceTab" class="css-category-tab" onclick="showMarketingManagerTab('audience')">👥 Target Audience</button>
                    <button id="sellingTab" class="css-category-tab" onclick="showMarketingManagerTab('selling')">⭐ Selling Points</button>
                    <button id="seoTab" class="css-category-tab" onclick="showMarketingManagerTab('seo')">🔍 SEO & Keywords</button>
                    <button id="conversionTab" class="css-category-tab" onclick="showMarketingManagerTab('conversion')">💰 Conversion</button>
                </div>
            </div>
        </div>
        
        <!-- AI Help Text - Below Tab Buttons -->
        <div class="px-6 py-3 bg-blue-50 border-b border-blue-200">
            <div class="flex items-center text-sm text-blue-700">
                <span class="mr-2">💡</span>
                <span>Use AI to automatically generate marketing content for all tabs</span>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="modal-body" style="flex: 1; overflow-y: auto;">
            <div id="marketingManagerContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
        
        <!-- Footer -->
        <div class="modal-footer">
            <div class="flex space-x-3">
                <button onclick="applyMarketingToItem()" 
                        class="px-6 py-2 text-sm font-medium text-white rounded-md transition-all duration-200 transform hover:scale-105 shadow-md hover:shadow-lg"
                        style="background: linear-gradient(to right, #3b82f6, #1d4ed8); border: none;">
                    📝 Apply to Item
                </button>
                <button onclick="closeMarketingManager()" 
                        class="px-6 py-2 text-sm font-medium text-white rounded-md transition-all duration-200 transform hover:scale-105 shadow-md hover:shadow-lg"
                        style="background: linear-gradient(to right, #87ac3a, #6b8e23); border: none;">
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
        <div class="admin-modal-header" style="background: linear-gradient(135deg, #10b981, #3b82f6);">
            <h2 class="modal-title">🤖 AI Content Comparison & Selection</h2>
            <button onclick="closeAIComparisonModal()" class="modal-close">&times;</button>
        </div>
        
        <!-- AI Analysis Progress Section (Collapsible) -->
        <div id="aiAnalysisProgressSection" class="bg-gradient-to-r from-blue-50 to-purple-50 border-b flex-shrink-0 transition-all duration-500 overflow-hidden" style="max-height: 200px;">
            <div class="px-4 sm:px-6 py-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div id="aiProgressSpinner" class="modal-loading-spinner"></div>
                        <span class="text-sm font-semibold text-gray-800">AI Analysis in Progress</span>
                    </div>
                    <span id="aiProgressText" class="text-xs text-gray-600">Initializing...</span>
                </div>
                
                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 rounded-full h-2 mb-3">
                    <div id="aiProgressBar" class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                
                <!-- Detailed Progress Steps -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                    <div id="step1-analyze" class="flex items-center gap-1 p-2 rounded bg-white/50">
                        <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                        <span>Analyzing Content</span>
                    </div>
                    <div id="step2-extract-insights" class="flex items-center gap-1 p-2 rounded bg-white/50">
                        <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                        <span>Extracting Insights</span>
                    </div>
                    <div id="step3-generate-content" class="flex items-center gap-1 p-2 rounded bg-white/50">
                        <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                        <span>Generating Content</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scrollable Content Area -->
        <div class="flex-1 overflow-y-scroll min-h-0">
            <div class="p-4 sm:p-6">
                <div id="aiComparisonContent" class="space-y-4">
                    <div class="text-center text-gray-500 py-8">AI analysis in progress...</div>
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
                    <button onclick="applySelectedChanges()" id="applyChangesBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-medium hidden">
                        Apply Selected Changes
                    </button>
                    <button onclick="closeAIComparisonModal()" class="modal-button btn-secondary">
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
    
    // AI Analysis functionality for new items
    document.addEventListener('DOMContentLoaded', function() {
        const aiUpload = document.getElementById('aiAnalysisUpload');
        if (aiUpload) {
            aiUpload.addEventListener('change', handleAIAnalysisUpload);
        }
    });
    
    async function handleAIAnalysisUpload(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        // Show loading state
        const aiMessage = document.getElementById('aiCategoryMessage');
        if (aiMessage) {
            aiMessage.innerHTML = '🔄 Analyzing image with AI... This may take a moment.';
            aiMessage.className = 'mt-1 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800';
        }
        
        try {
            const formData = new FormData();
            formData.append('image', file);
            
            const response = await fetch('/api/ai_item_analysis.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success && result.analysis) {
                // Populate form fields with AI analysis
                const analysis = result.analysis;
                
                // Set SKU
                const skuField = document.getElementById('skuEdit');
                if (skuField) skuField.value = analysis.suggested_sku || '';
                
                // Set title/name
                const nameField = document.getElementById('name');
                if (nameField) nameField.value = analysis.title || '';
                
                // Set description
                const descField = document.getElementById('description');
                if (descField) descField.value = analysis.description || '';
                
                // Set category and show the dropdown
                const categoryField = document.getElementById('categoryEdit');
                if (categoryField) {
                    // Add new category option if it doesn't exist
                    const categoryExists = Array.from(categoryField.options).some(option => option.value === analysis.category);
                    if (!categoryExists && analysis.category) {
                        const newOption = document.createElement('option');
                        newOption.value = analysis.category;
                        newOption.textContent = analysis.category;
                        categoryField.appendChild(newOption);
                    }
                    
                    categoryField.value = analysis.category || '';
                    categoryField.style.display = 'block';
                }
                
                // Update AI message with success
                if (aiMessage) {
                    aiMessage.innerHTML = `✅ AI Analysis Complete! Category: <strong>${analysis.category}</strong>, Confidence: ${analysis.confidence}. You can now edit any details before saving.`;
                    aiMessage.className = 'mt-1 p-3 bg-green-50 border border-green-200 rounded text-sm text-green-800';
                }
                
                // Show edit item modal with pre-filled data
                showSuccess( 'AI analysis complete! Review and edit the generated details.');
                
            } else {
                throw new Error(result.error || 'AI analysis failed');
            }
            
        } catch (error) {
            console.error('AI analysis error:', error);
            if (aiMessage) {
                aiMessage.innerHTML = `❌ AI analysis failed: ${error.message}. Please fill in the details manually.`;
                aiMessage.className = 'mt-1 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-800';
            }
            showError( 'AI analysis failed: ' + error.message);
        }
    }

// Initialize items list for navigation
var allItems = <?= json_encode(array_values($items)) ?>;
var currentItemIndex = -1;

// Find current item index if we're in view/edit mode
if (currentItemSku && allItems.length > 0) {
    currentItemIndex = allItems.findIndex(item => item.sku === currentItemSku);
}

// Helper function to check if current AI model supports images
async function checkAIImageSupport() {
    try {
        const response = await fetch('/api/get_ai_model_capabilities.php?action=get_current');
        const data = await response.json();
        return data.success && data.supports_images;
    } catch (error) {
        console.error('Error checking AI image support:', error);
        return false;
    }
}

// Navigation functions
function navigateToItem(direction) {
    if (allItems.length === 0) return;
    
    let newIndex = currentItemIndex;
    
    if (direction === 'prev') {
        newIndex = currentItemIndex > 0 ? currentItemIndex - 1 : allItems.length - 1;
    } else if (direction === 'next') {
        newIndex = currentItemIndex < allItems.length - 1 ? currentItemIndex + 1 : 0;
    }
    
    if (newIndex !== currentItemIndex && newIndex >= 0 && newIndex < allItems.length) {
        const targetItem = allItems[newIndex];
        const currentMode = modalMode === 'view' ? 'view' : 'edit';
        let newUrl = `?page=admin&section=inventory&${currentMode}=${encodeURIComponent(targetItem.sku)}`;
        
        // Preserve any existing search/filter parameters
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('search')) newUrl += `&search=${encodeURIComponent(urlParams.get('search'))}`;
        if (urlParams.get('category')) newUrl += `&category=${encodeURIComponent(urlParams.get('category'))}`;
        if (urlParams.get('stock')) newUrl += `&stock=${encodeURIComponent(urlParams.get('stock'))}`;
        
        window.location.href = newUrl;
    }
}

// Update navigation button states
function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevItemBtn');
    const nextBtn = document.getElementById('nextItemBtn');
    
    if (prevBtn && nextBtn && allItems.length > 0) {
        // Always enable buttons for circular navigation
        prevBtn.style.display = 'block';
        nextBtn.style.display = 'block';
        
        // Add item counter to buttons for better UX
        const itemCounter = `${currentItemIndex + 1} of ${allItems.length}`;
        const currentItem = allItems[currentItemIndex];
        const prevIndex = currentItemIndex > 0 ? currentItemIndex - 1 : allItems.length - 1;
        const nextIndex = currentItemIndex < allItems.length - 1 ? currentItemIndex + 1 : 0;
        const prevItem = allItems[prevIndex];
        const nextItem = allItems[nextIndex];
        
        prevBtn.title = `Previous: ${prevItem?.name || 'Unknown'} (${itemCounter})`;
        nextBtn.title = `Next: ${nextItem?.name || 'Unknown'} (${itemCounter})`;
    }
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    // Only activate in modal mode and when not typing in input fields
    if ((modalMode === 'view' || modalMode === 'edit') && 
        !['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
        
        if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            navigateToItem('prev');
        } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            navigateToItem('next');
        }
    }
});

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
                showSuccess( 'Primary image updated');
                loadCurrentImages(sku);
            } else {
                showError( data.error || 'Failed to set primary image');
            }
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            showError( 'Server returned invalid response: ' + text.substring(0, 100));
        }
    })
    .catch(error => {
        console.error('Error setting primary image:', error);
        showError( 'Failed to set primary image');
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
    overlay.className = 'modal-overlay';
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
                showSuccess( 'Image deleted');
                loadCurrentImages(sku);
            } else {
                showError( data.error || 'Failed to delete image');
            }
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            showError( 'Server returned invalid response: ' + text.substring(0, 100));
        }
    })
    .catch(error => {
        console.error('Error deleting image:', error);
        showError( 'Failed to delete image');
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

// Using global notification system - no custom showToast needed

// Styled confirmation dialog
function showStyledConfirm(title, message, confirmText = 'Confirm', cancelText = 'Cancel') {
    return new Promise((resolve) => {
        // Remove any existing confirmation modal
        const existingModal = document.getElementById('styled-confirm-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Create the modal
        const modal = document.createElement('div');
        modal.id = 'styled-confirm-modal';
        modal.className = 'modal-overlay';
        modal.style.zIndex = '999999';
        
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h3 style="margin: 0; color: #374151; font-size: 1.2rem;">${title}</h3>
                </div>
                <div class="modal-body">
                    <p style="margin: 0 0 20px 0; color: #6b7280; line-height: 1.5;">${message}</p>
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button id="styled-confirm-cancel" class="btn-secondary" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #d1d5db; background: white; color: #374151; cursor: pointer;">
                            ${cancelText}
                        </button>
                        <button id="styled-confirm-ok" class="btn-primary" style="padding: 8px 16px; border-radius: 6px; border: none; background: #ef4444; color: white; cursor: pointer;">
                            ${confirmText}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Add event listeners
        document.getElementById('styled-confirm-cancel').addEventListener('click', () => {
            modal.remove();
            resolve(false);
        });
        
        document.getElementById('styled-confirm-ok').addEventListener('click', () => {
            modal.remove();
            resolve(true);
        });
        
        // Close on overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
                resolve(false);
            }
        });
        
        // Show the modal
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
    });
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
        showError( 'Cost breakdown data not available.');
        return;
    }
    const item_cost = costBreakdown[type].find(i => String(i.id) === String(id));
    if (!item_cost) {
        showError( 'Cost item not found.');
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
            showSuccess( data.message);
            closeCostModal();
            refreshCostBreakdown();
        } else {
            showError( data.error || `Failed to save ${type} cost`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError( `Failed to save ${type} cost`);
    });
}

function deleteCurrentCostItem() { // Called by delete button in costFormModal
    const id = document.getElementById('costItemId').value;
    const type = document.getElementById('costItemType').value;
    if (!id || !type) {
        showError( 'No item selected for deletion.');
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
            showSuccess( data.message);
            closeCostDeleteModal();
            closeCostModal();
            refreshCostBreakdown();
        } else {
            showError( data.error || `Failed to delete ${type} cost`);
        }
    })
    .catch(error => {
        console.error('Error deleting cost item:', error);
        showError( `Failed to delete ${type} cost. Check console for details.`);
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
            showError( data.error || 'Failed to load cost breakdown');
        }
    })
    .catch(error => { 
        console.error('Error:', error); 
        showError( 'Failed to load cost breakdown'); 
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
    // Get current cost breakdown values for comparison
    const currentCosts = getCurrentCostBreakdown();
    const hasExistingCosts = checkForExistingCosts();
    
    // Create the modal overlay
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'costSuggestionChoiceModal';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="bg-gradient-to-r from-blue-600 to-green-600 px-6 py-4 flex-shrink-0">
                <h2 class="text-xl font-bold text-white flex items-center">
                    🧮 AI Cost Suggestion - Side by Side Comparison
                </h2>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1 custom-scrollbar" style="max-height: 70vh;">
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
                
                <!-- Side by Side Comparison -->
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-800 mb-3">💰 Cost Breakdown Comparison</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Current Values Column -->
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <h4 class="font-semibold text-gray-700 mb-3 text-center">📊 Current Values</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center p-2 bg-white rounded border">
                                    <span class="text-sm font-medium text-gray-600">Materials:</span>
                                    <span class="font-semibold text-gray-800">$${parseFloat(currentCosts.materials || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-2 bg-white rounded border">
                                    <span class="text-sm font-medium text-gray-600">Labor:</span>
                                    <span class="font-semibold text-gray-800">$${parseFloat(currentCosts.labor || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-2 bg-white rounded border">
                                    <span class="text-sm font-medium text-gray-600">Energy:</span>
                                    <span class="font-semibold text-gray-800">$${parseFloat(currentCosts.energy || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-2 bg-white rounded border">
                                    <span class="text-sm font-medium text-gray-600">Equipment:</span>
                                    <span class="font-semibold text-gray-800">$${parseFloat(currentCosts.equipment || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-100 rounded border-2 border-gray-300">
                                    <span class="font-semibold text-gray-700">Total:</span>
                                    <span class="text-lg font-bold text-gray-800">$${parseFloat(currentCosts.total || 0).toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- AI Suggested Values Column -->
                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <h4 class="font-semibold text-green-700 mb-3 text-center">🤖 AI Suggested Values</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center p-2 bg-white rounded border border-green-200">
                                    <span class="text-sm font-medium text-green-600">Materials:</span>
                                    <span class="font-semibold text-green-800">$${parseFloat(suggestionData.breakdown.materials || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-2 bg-white rounded border border-green-200">
                                    <span class="text-sm font-medium text-green-600">Labor:</span>
                                    <span class="font-semibold text-green-800">$${parseFloat(suggestionData.breakdown.labor || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-2 bg-white rounded border border-green-200">
                                    <span class="text-sm font-medium text-green-600">Energy:</span>
                                    <span class="font-semibold text-green-800">$${parseFloat(suggestionData.breakdown.energy || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-2 bg-white rounded border border-green-200">
                                    <span class="text-sm font-medium text-green-600">Equipment:</span>
                                    <span class="font-semibold text-green-800">$${parseFloat(suggestionData.breakdown.equipment || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-green-100 rounded border-2 border-green-300">
                                    <span class="font-semibold text-green-700">Total:</span>
                                    <span class="text-lg font-bold text-green-800">$${parseFloat(suggestionData.suggestedCost).toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Individual Field Selection -->
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-800 mb-3">🎯 Choose Which Values to Apply</h3>
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200 mb-4">
                        <p class="text-sm text-yellow-800">
                            <span class="font-semibold">💡 Pro Tip:</span> Select individual fields below to apply only the AI suggestions you want to keep. 
                            Unselected fields will retain their current values.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <label class="flex items-center p-3 bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" id="applyMaterials" class="mr-3 text-green-600 focus:ring-green-500" 
                                       ${Math.abs(parseFloat(currentCosts.materials || 0) - parseFloat(suggestionData.breakdown.materials || 0)) > 0.01 ? 'checked' : ''}>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">Materials Cost</div>
                                    <div class="text-sm text-gray-600">
                                        ${parseFloat(currentCosts.materials || 0).toFixed(2)} → $${parseFloat(suggestionData.breakdown.materials || 0).toFixed(2)}
                                        <span class="ml-2 text-xs ${parseFloat(suggestionData.breakdown.materials || 0) > parseFloat(currentCosts.materials || 0) ? 'text-red-600' : 'text-green-600'}">
                                            (${parseFloat(suggestionData.breakdown.materials || 0) > parseFloat(currentCosts.materials || 0) ? '+' : ''}${(parseFloat(suggestionData.breakdown.materials || 0) - parseFloat(currentCosts.materials || 0)).toFixed(2)})
                                        </span>
                                    </div>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" id="applyLabor" class="mr-3 text-green-600 focus:ring-green-500"
                                       ${Math.abs(parseFloat(currentCosts.labor || 0) - parseFloat(suggestionData.breakdown.labor || 0)) > 0.01 ? 'checked' : ''}>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">Labor Cost</div>
                                    <div class="text-sm text-gray-600">
                                        $${parseFloat(currentCosts.labor || 0).toFixed(2)} → $${parseFloat(suggestionData.breakdown.labor || 0).toFixed(2)}
                                        <span class="ml-2 text-xs ${parseFloat(suggestionData.breakdown.labor || 0) > parseFloat(currentCosts.labor || 0) ? 'text-red-600' : 'text-green-600'}">
                                            (${parseFloat(suggestionData.breakdown.labor || 0) > parseFloat(currentCosts.labor || 0) ? '+' : ''}${(parseFloat(suggestionData.breakdown.labor || 0) - parseFloat(currentCosts.labor || 0)).toFixed(2)})
                                        </span>
                                    </div>
                                </div>
                            </label>
                        </div>
                        
                        <div class="space-y-3">
                            <label class="flex items-center p-3 bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" id="applyEnergy" class="mr-3 text-green-600 focus:ring-green-500"
                                       ${Math.abs(parseFloat(currentCosts.energy || 0) - parseFloat(suggestionData.breakdown.energy || 0)) > 0.01 ? 'checked' : ''}>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">Energy Cost</div>
                                    <div class="text-sm text-gray-600">
                                        $${parseFloat(currentCosts.energy || 0).toFixed(2)} → $${parseFloat(suggestionData.breakdown.energy || 0).toFixed(2)}
                                        <span class="ml-2 text-xs ${parseFloat(suggestionData.breakdown.energy || 0) > parseFloat(currentCosts.energy || 0) ? 'text-red-600' : 'text-green-600'}">
                                            (${parseFloat(suggestionData.breakdown.energy || 0) > parseFloat(currentCosts.energy || 0) ? '+' : ''}${(parseFloat(suggestionData.breakdown.energy || 0) - parseFloat(currentCosts.energy || 0)).toFixed(2)})
                                        </span>
                                    </div>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" id="applyEquipment" class="mr-3 text-green-600 focus:ring-green-500"
                                       ${Math.abs(parseFloat(currentCosts.equipment || 0) - parseFloat(suggestionData.breakdown.equipment || 0)) > 0.01 ? 'checked' : ''}>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">Equipment Cost</div>
                                    <div class="text-sm text-gray-600">
                                        $${parseFloat(currentCosts.equipment || 0).toFixed(2)} → $${parseFloat(suggestionData.breakdown.equipment || 0).toFixed(2)}
                                        <span class="ml-2 text-xs ${parseFloat(suggestionData.breakdown.equipment || 0) > parseFloat(currentCosts.equipment || 0) ? 'text-red-600' : 'text-green-600'}">
                                            (${parseFloat(suggestionData.breakdown.equipment || 0) > parseFloat(currentCosts.equipment || 0) ? '+' : ''}${(parseFloat(suggestionData.breakdown.equipment || 0) - parseFloat(currentCosts.equipment || 0)).toFixed(2)})
                                        </span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Select Options -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <h4 class="font-medium text-gray-800 mb-3">⚡ Quick Select Options</h4>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="selectAllCostFields(true)" class="px-3 py-1 bg-green-100 text-green-700 rounded text-sm hover:bg-green-200 transition-colors">
                            ✅ Select All
                        </button>
                        <button onclick="selectAllCostFields(false)" class="px-3 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200 transition-colors">
                            ❌ Select None
                        </button>
                        <button onclick="selectOnlyHigherValues()" class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200 transition-colors">
                            📈 Only Higher Values
                        </button>
                        <button onclick="selectOnlyLowerValues()" class="px-3 py-1 bg-purple-100 text-purple-700 rounded text-sm hover:bg-purple-200 transition-colors">
                            📉 Only Lower Values
                        </button>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col gap-3">
                    <!-- Primary Action: Apply Total to Cost Field -->
                    <button onclick="applySuggestedCostToCostField(this)" data-suggestion='${JSON.stringify(suggestionData).replace(/'/g, '&#39;').replace(/"/g, '&quot;')}' 
                            class="w-full bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 text-white px-6 py-3 rounded-lg font-semibold shadow-lg transition-all duration-200">
                        💰 Use Total Cost ($${parseFloat(suggestionData.suggestedCost).toFixed(2)}) in Cost Price Field
                    </button>
                    
                    <!-- Detailed Breakdown Actions -->
                    <div class="border-t border-gray-200 pt-3">
                        <p class="text-sm text-gray-600 mb-3 text-center">Or manage detailed cost breakdown:</p>
                        <div class="flex flex-col gap-2">
                            <button onclick="replaceAllCostValues(this)" data-suggestion='${JSON.stringify(suggestionData).replace(/'/g, '&#39;').replace(/"/g, '&quot;')}' 
                                    class="w-full bg-gradient-to-r from-red-600 to-orange-600 hover:from-red-700 hover:to-orange-700 text-white px-4 py-2 rounded-lg font-medium shadow transition-all duration-200 text-sm">
                                🔄 Replace Current Cost Breakdown
                            </button>
                            
                            <div class="flex flex-col sm:flex-row gap-2">
                                <button onclick="applySelectedCostFields(this)" data-suggestion='${JSON.stringify(suggestionData).replace(/'/g, '&#39;').replace(/"/g, '&quot;')}' 
                                        class="flex-1 bg-gradient-to-r from-blue-600 to-green-600 hover:from-blue-700 hover:to-green-700 text-white px-4 py-2 rounded-lg font-medium shadow transition-all duration-200 text-sm">
                                    ➕ Add Selected to Breakdown
                                </button>
                                
                                <button onclick="closeCostSuggestionChoiceDialog()" 
                                        class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-all duration-200 text-sm">
                                    ❌ Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-xs text-gray-500 text-center space-y-1">
                    <div>🔄 <strong>Replace:</strong> Deletes all current cost items and creates new ones with AI values</div>
                    <div>➕ <strong>Add:</strong> Only selected fields will be added. Unselected fields keep their current values.</div>
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
        const allCategories = ['materials', 'labor', 'energy', 'equipment'];
        console.log('Clearing all existing cost items for complete replacement');
        await clearExistingCostItems(allCategories);
        
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
async function applySelectedCostBreakdown(selectedData) {
    console.log('Applying selected cost breakdown:', selectedData);
    
    // Show loading state
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
            
            if (categoriesToClear.length > 0) {
                console.log('Clearing existing cost items for categories:', categoriesToClear);
                await clearExistingCostItems(categoriesToClear);
                
                // Wait a moment for the clearing to complete
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // Now add the new cost items
                const addPromises = [];
                
                categories.forEach(category => {
                    if (selectedData.selectedFields[category] && selectedData.breakdown[category] !== null) {
                        const cost = parseFloat(selectedData.breakdown[category]);
                        if (cost > 0) {
                            console.log(`Queuing ${category} cost addition:`, cost);
                            addPromises.push(addCostItemDirectly(category, `AI Suggested ${category.charAt(0).toUpperCase() + category.slice(1)}`, cost));
                        }
                    }
                });
                
                if (addPromises.length > 0) {
                    const results = await Promise.all(addPromises);
                    console.log('All cost items added successfully:', results);
                    
                    // Refresh the cost breakdown display
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
async function clearExistingCostItems(categories) {
    console.log('Clearing existing cost items for categories:', categories);
    
    try {
        // First, get the current cost breakdown to find item IDs to delete
        const response = await fetch(`process_cost_breakdown.php?inventoryId=${currentItemSku}&costType=all`, {
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
                    if (item.id) {
                        console.log(`Queuing deletion of ${category} item ID ${item.id}`);
                        deletePromises.push(deleteCostItemDirect(category, item.id));
                    }
                });
            }
        });
        
        if (deletePromises.length > 0) {
            const results = await Promise.all(deletePromises);
            console.log('All existing cost items cleared:', results);
        } else {
            console.log('No existing cost items found to clear');
        }
        
    } catch (error) {
        console.error('Error clearing existing cost items:', error);
        throw error;
    }
}

// Helper function to delete a single cost item directly via API
function deleteCostItemDirect(type, itemId) {
    console.log(`Deleting ${type} cost item ID ${itemId}`);
    
    const url = `process_cost_breakdown.php?inventoryId=${currentItemSku}&costType=${type}&id=${itemId}`;
    
    return fetch(url, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log(`Delete ${type} item ${itemId} response status:`, response.status);
        return response.json();
    })
    .then(result => {
        console.log(`Delete ${type} item ${itemId} result:`, result);
        if (!result.success) {
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
function addCostItemDirectly(type, description, cost) {
    console.log(`Adding ${type} cost:`, {type, description, cost, currentItemSku});
    
    const url = `process_cost_breakdown.php`;
    
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
    }
    
    console.log(`Sending cost request to ${url} with JSON data:`, requestData);
    
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(requestData)
    })
    .then(response => {
        console.log(`Cost ${type} response status:`, response.status);
        return response.json();
    })
    .then(result => {
        console.log(`Cost ${type} result:`, result);
        if (!result.success) {
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
            <div class="bg-gradient-to-r from-green-600 to-blue-600 px-6 py-4 flex-shrink-0">
                <h2 class="text-xl font-bold text-white flex items-center">
                    🎯 AI Price Suggestion - Side by Side Comparison
                </h2>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1 custom-scrollbar" style="max-height: 70vh;">
                <!-- AI Analysis Summary -->
                <div class="mb-6 p-4 bg-green-50 rounded-lg border border-green-200">
                    <h3 class="font-semibold text-gray-800 mb-2 flex items-center">
                        <span class="mr-2">🤖</span> AI Pricing Analysis
                    </h3>
                    <p class="text-sm text-gray-700 mb-2">${suggestionData.reasoning || 'Advanced pricing analysis completed'}</p>
                    <div class="text-xs text-green-600">
                        <strong>Confidence:</strong> ${suggestionData.confidence || 'medium'} • 
                        <strong>Suggested Price:</strong> $${parseFloat(suggestionData.suggestedPrice).toFixed(2)}
                    </div>
                </div>
                
                <!-- Side by Side Comparison -->
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-800 mb-3">💰 Price Comparison</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Current Price Column -->
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <h4 class="font-semibold text-gray-700 mb-3 text-center">📊 Current Price</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center p-3 bg-white rounded border">
                                    <span class="text-sm font-medium text-gray-600">Retail Price:</span>
                                    <span class="font-semibold text-gray-800">$${parseFloat(currentPrice.retail || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-white rounded border">
                                    <span class="text-sm font-medium text-gray-600">Cost Price:</span>
                                    <span class="font-semibold text-gray-800">$${parseFloat(currentPrice.cost || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-gray-100 rounded border-2 border-gray-300">
                                    <span class="font-semibold text-gray-700">Profit Margin:</span>
                                    <span class="text-lg font-bold text-gray-800">${currentPrice.retail > 0 ? (((currentPrice.retail - currentPrice.cost) / currentPrice.retail) * 100).toFixed(1) : '0.0'}%</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- AI Suggested Price Column -->
                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <h4 class="font-semibold text-green-700 mb-3 text-center">🤖 AI Suggested Price</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center p-3 bg-white rounded border border-green-200">
                                    <span class="text-sm font-medium text-green-600">Suggested Price:</span>
                                    <span class="font-semibold text-green-800">$${parseFloat(suggestionData.suggestedPrice).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-white rounded border border-green-200">
                                    <span class="text-sm font-medium text-green-600">Cost Price:</span>
                                    <span class="font-semibold text-green-800">$${parseFloat(currentPrice.cost || 0).toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-green-100 rounded border-2 border-green-300">
                                    <span class="font-semibold text-green-700">Profit Margin:</span>
                                    <span class="text-lg font-bold text-green-800">${suggestionData.suggestedPrice > 0 ? (((suggestionData.suggestedPrice - currentPrice.cost) / suggestionData.suggestedPrice) * 100).toFixed(1) : '0.0'}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pricing Components Breakdown -->
                ${suggestionData.components && suggestionData.components.length > 0 ? `
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-800 mb-3">🔍 Pricing Components Analysis</h3>
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <div class="space-y-3">
                            ${suggestionData.components.map(component => `
                                <div class="flex justify-between items-center p-2 bg-white rounded border">
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
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-800 mb-3">🎯 Choose Your Pricing Strategy</h3>
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200 mb-4">
                        <p class="text-sm text-yellow-800">
                            <span class="font-semibold">💡 Pro Tip:</span> You can apply the AI suggested price or keep your current price. 
                            The AI analysis provides valuable insights for your pricing decision.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center p-4 bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="priceChoice" value="suggested" id="applySuggestedPrice" class="mr-3 text-green-600 focus:ring-green-500" checked>
                            <div class="flex-1">
                                <div class="font-medium text-gray-800">Use AI Suggested Price</div>
                                <div class="text-sm text-gray-600">
                                    $${parseFloat(suggestionData.suggestedPrice).toFixed(2)} 
                                    <span class="ml-2 text-xs ${suggestionData.suggestedPrice > currentPrice.retail ? 'text-green-600' : 'text-red-600'}">
                                        (${suggestionData.suggestedPrice > currentPrice.retail ? '+' : ''}${(suggestionData.suggestedPrice - currentPrice.retail).toFixed(2)} vs current)
                                    </span>
                                </div>
                            </div>
                        </label>
                        
                        <label class="flex items-center p-4 bg-white rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="priceChoice" value="current" id="keepCurrentPrice" class="mr-3 text-gray-600 focus:ring-gray-500">
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
                    <div class="mb-6 p-4 bg-amber-50 rounded-lg border border-amber-200">
                        <div class="flex items-center mb-2">
                            <span class="text-amber-600 mr-2">⚠️</span>
                            <span class="font-medium text-amber-800">Existing Price Suggestion Found</span>
                        </div>
                        <p class="text-sm text-amber-700">
                            You have an existing price suggestion displayed. This new analysis will replace the previous suggestion.
                        </p>
                    </div>
                ` : ''}
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button onclick="applySelectedPriceChoice(this)" data-suggestion='${JSON.stringify(suggestionData).replace(/'/g, '&#39;').replace(/"/g, '&quot;')}' 
                            class="flex-1 bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 text-white px-6 py-3 rounded-lg font-semibold shadow-lg transition-all duration-200">
                        🎯 Apply Selected Choice
                    </button>
                    
                    <button onclick="closePriceSuggestionChoiceDialog()" 
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold transition-all duration-200">
                        ❌ Cancel
                    </button>
                </div>
                
                <div class="mt-4 text-xs text-gray-500 text-center">
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

async function applySuggestedCostBreakdown(buttonElement) {
    // Get suggestion data from the button's data attribute
    const suggestionData = JSON.parse(buttonElement.dataset.suggestion);
    
    // Close the choice dialog
    closeCostSuggestionChoiceDialog();
    
    // Show loading state
    showInfo( 'Applying AI cost breakdown...');
    
    try {
        // Populate the cost breakdown with the suggestion and save to database
        await populateCostBreakdownFromSuggestion(suggestionData);
        
        showSuccess( `✅ AI cost breakdown applied and saved! Total: $${suggestionData.suggestedCost} (${suggestionData.confidence} confidence)`);
    } catch (error) {
        console.error('Error applying cost breakdown:', error);
        showError( 'Failed to apply cost breakdown');
    }
}

async function useSuggestedCost() {
    const nameField = document.getElementById('name');
    const descriptionField = document.getElementById('description');
    const categoryField = document.getElementById('categoryEdit');
    
    if (!nameField || !nameField.value.trim()) {
        showError( 'Item name is required for cost suggestion');
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
            showError( data.error || 'Failed to get cost suggestion');
        }
    } catch (error) {
        console.error('Error getting cost suggestion:', error);
        showError( 'Failed to connect to cost suggestion service');
    } finally {
        // Restore button state
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

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
    button.disabled = true;
    console.log('🔄 Button state changed to loading...');
    
    // Check if current AI model supports images
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
        // Call the price suggestion API
        console.log('📡 Sending request to /api/suggest_price.php with data:', itemData);
        const response = await fetch('/api/suggest_price.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin', // Include session cookies
            body: JSON.stringify(itemData)
        });
        
        console.log('📨 Response status:', response.status);
        const data = await response.json();
        console.log('📋 Response data:', data);
        
        if (data.success) {
            console.log('✅ Success! Showing price suggestion dialog');
            // Show choice dialog with the new figures
            showPriceSuggestionChoiceDialog(data);
        } else {
            console.log('❌ API returned error:', data.error);
            showError( data.error || 'Failed to get price suggestion');
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
    
    fetch(`/api/get_price_suggestion.php?sku=${encodeURIComponent(sku)}&_t=${Date.now()}`)
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
    
    fetch(`/api/get_price_suggestion.php?sku=${encodeURIComponent(sku)}&_t=${Date.now()}`)
    .then(response => response.json())
    .then(data => {
        console.log('📂 Loading existing price suggestion for SKU:', sku, 'Result:', data); // Debug log
        if (data.success && data.suggestedPrice) {
            console.log('✅ Found existing price suggestion, displaying it');
            displayPriceSuggestion(data);
        } else {
            console.log('ℹ️ No existing price suggestion found (this is normal) - showing placeholder');
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
                showWarning( 'UI cleared but database may still contain old cost data');
                return false;
            }
            
            return true;
        } catch (error) {
            console.error('Error clearing cost breakdown from database:', error);
            showWarning( 'UI cleared but database may still contain old cost data');
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
            showError( 'Failed to save cost item: ' + result.error);
            return false;
        }
        
        return true;
    } catch (error) {
        console.error('Error saving cost item:', error);
        showError( 'Error saving cost item: ' + error.message);
        return false;
    }
}

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
                            <div class="text-xs text-blue-600 mt-1">Click to apply to item name</div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <span class="mr-2">📝</span> AI-Crafted Description
                        </h4>
                        <div class="p-3 bg-white border border-green-200 rounded-lg hover:bg-green-50 cursor-pointer" onclick="applyDescription('${data.description.replace(/'/g, "\\'")}')">
                            <div class="text-gray-800 text-sm">${data.description}</div>
                            <div class="text-xs text-green-600 mt-1">Click to apply to item description</div>
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
        showSuccess( 'Title applied! Remember to save your changes.');
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
        showSuccess( 'Description applied! Remember to save your changes.');
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


document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded - modalMode:', modalMode, 'currentItemSku:', currentItemSku, 'costBreakdown:', costBreakdown);
    
    // Initialize navigation buttons for view/edit modes
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
                    showSuccess( data.message);
                    
                    // Check if this is an add operation (modal mode is 'add')
                    const isAddOperation = window.location.search.includes('add=1');
                    
                    if (isAddOperation) {
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

                fetch('/process_image_upload.php', {
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
                    showSuccess( data.message);
                    // Redirect to refresh the list after successful deletion
                    setTimeout(() => { window.location.href = '?page=admin&section=inventory'; }, 1000);
                } else {
                    showError( data.error || 'Failed to delete item.');
                }
            })
            .catch(error => { console.error('Error:', error); showError( 'Failed to delete item.'); });
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
        showError( `The following files are too large (max 10MB allowed): ${fileNames}`);
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
        showError( 'SKU is required');
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
    formData.append('useAIProcessing', document.getElementById('useAIProcessing')?.checked ? 'true' : 'false');
    
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
                showSuccess( data.message || `Successfully uploaded ${files.length} image(s)`);
                
                // Clear the file input
                document.getElementById('multiImageUpload').value = '';
                
                // Refresh current images display
                loadCurrentImages(sku);
                
            } else {
                console.error('Upload failed:', data.error);
                showError( data.error || 'Upload failed');
            }
            
            if (data.warnings && data.warnings.length > 0) {
                data.warnings.forEach(warning => {
                    console.warn('Upload warning:', warning);
                    showWarning( warning);
                });
            }
        } catch (e) {
            console.error('Failed to parse JSON response:', e);
            console.error('Raw response:', text.substring(0, 500));
            showError( 'Server returned invalid response: ' + text.substring(0, 100));
        }
    })
    .catch(error => {
        console.error('Upload fetch error:', error);
        showError( 'Upload failed: ' + error.message);
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

// AI Processing Functions
async function processExistingImagesWithAI() {
    const sku = (document.getElementById('skuEdit') || document.getElementById('skuDisplay'))?.value;
    
    if (!sku) {
        showError( 'SKU is required');
        return;
    }
    
    try {
        // Set up completion callback
        window.aiProcessingModal.onComplete = function() {
            // Refresh current images display
            loadCurrentImages(sku);
            showSuccess( 'AI processing completed! Images have been updated.');
        };
        
        // Set up cancel callback
        window.aiProcessingModal.onCancel = function() {
            showInfo( 'AI processing was cancelled.');
        };
        
        // Start processing
        const response = await fetch('/api/process_image_ai.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'process_uploaded_image',
                sku: sku,
                options: {
                    convertToWebP: true,
                    quality: 90,
                    preserveTransparency: true,
                    useAI: true,
                    fallbackTrimPercent: 0.05
                }
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Processing failed');
        }
        
        // Show results
        window.aiProcessingModal.show();
        window.aiProcessingModal.showSuccess(
            `Successfully processed ${data.processed_images} image(s)`,
            [`Processed ${data.processed_images} images`, 'All images optimized with AI edge detection']
        );
        
    } catch (error) {
        console.error('AI processing error:', error);
        showError( 'AI processing failed: ' + error.message);
    }
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

// Removed duplicate loadThumbnailImage function - using the one below

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
    }, 200);
    
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

// Load current images when modal opens and handle thumbnails
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
        }, 200);
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
        }, 200);
    }
    
    // ===== IMAGE SYSTEM ARCHITECTURE =====
    // Current system uses:
    // 1. Database: item_images table with SKU-based relationships
    // 2. API: get_item_images.php returns images for a given SKU
    // 3. Frontend: thumbnail containers with data-sku attributes
    // 4. JavaScript: loadThumbnailImage() function fetches from API
    // This system is WORKING CORRECTLY - no mismatch between components

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
        showError( 'Failed to load templates');
    });
}

function loadTemplate() {
    const select = document.getElementById('templateSelect');
    const templateId = select.value;
    
    if (!templateId) {
        showError( 'Please select a template to load');
        return;
    }
    
    fetch(`/api/cost_breakdown_templates.php?action=get&id=${templateId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.template) {
            applyTemplateToBreakdown(data.template);
            showSuccess( `Template "${data.template.template_name}" loaded successfully!`);
        } else {
            showError( data.error || 'Failed to load template');
        }
    })
    .catch(error => {
        console.error('Error loading template:', error);
        showError( 'Failed to load template');
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
        showError( 'Please enter a template name');
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
            showSuccess( `Template "${templateName}" saved successfully!`);
            templateNameField.value = '';
            loadTemplateList(); // Refresh the template list
        } else {
            showError( data.error || 'Failed to save template');
        }
    })
    .catch(error => {
        console.error('Error saving template:', error);
        showError( 'Failed to save template');
    });
}

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
        hasTitleChanges = hasChanged;
        console.log('Title changes:', hasTitleChanges);
    } else if (fieldId === 'marketingDescription') {
        hasDescriptionChanges = hasChanged;
        console.log('Description changes:', hasDescriptionChanges);
    }
    
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
function updateMarketingSaveButtonVisibility() {
    console.log('Updating save button visibility - Title:', hasTitleChanges, 'Description:', hasDescriptionChanges);
    
    // Title save button
    const titleSaveButton = document.querySelector('[onclick*="applyAndSaveMarketingTitle"]');
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
    const descriptionSaveButton = document.querySelector('[onclick*="applyAndSaveMarketingDescription"]');
    if (descriptionSaveButton) {
        if (hasDescriptionChanges) {
            descriptionSaveButton.style.display = '';
            descriptionSaveButton.classList.add('animate-pulse');
        } else {
            descriptionSaveButton.style.display = 'none';
            descriptionSaveButton.classList.remove('animate-pulse');
        }
    } else {
        // Debug: Try alternative selector
        const altDescButton = document.querySelector('button[onclick="applyAndSaveMarketingDescription()"]');
        if (altDescButton) {
            if (hasDescriptionChanges) {
                altDescButton.style.display = '';
                altDescButton.classList.add('animate-pulse');
            } else {
                altDescButton.style.display = 'none';
                altDescButton.classList.remove('animate-pulse');
            }
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
        const saveButton = titleField?.parentElement?.querySelector('button[onclick="applyAndSaveMarketingTitle()"]');
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
        const saveButton = descField?.parentElement?.querySelector('button[onclick="applyAndSaveMarketingDescription()"]');
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
    const saveButton = titleField.parentElement.querySelector('button[onclick="applyAndSaveMarketingTitle()"]');
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
    const saveButton = descField.parentElement.querySelector('button[onclick="applyAndSaveMarketingDescription()"]');
    if (saveButton) saveButton.style.display = 'none';
    
    showSuccess( '✅ Item description updated successfully');
}

// Reset change tracking after successful save
function resetMarketingChangeTracking() {
    storeOriginalMarketingData();
}

// Marketing Manager Functions

// Enhanced modal visibility functions
function showMarketingModal() {
    const modal = document.getElementById('marketingManagerModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        modal.classList.remove('hidden');
        console.log('Marketing Manager: Modal display set to flex with show class');
    }
}

function hideMarketingModal() {
    const modal = document.getElementById('marketingManagerModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        modal.classList.add('hidden');
        console.log('Marketing Manager: Modal display set to none');
    }
}

function openMarketingManager() {
    console.log('Marketing Manager: Opening modal for SKU:', currentItemSku);
    
    if (!currentItemSku) {
        showValidation('No item selected. Please select an item first.');
        return;
    }
    
    // Get the modal
    const modal = document.getElementById('marketingManagerModal');
    console.log('Marketing Manager: Modal element found:', !!modal);
    
    if (!modal) {
        console.error('Marketing Manager modal not found');
        return;
    }
    
    // Remove any existing classes that might hide it
    modal.className = '';
    
    // Professional styling with guaranteed visibility
    modal.style.cssText = `
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0, 0, 0, 0.75) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        z-index: 50000 !important;
        visibility: visible !important;
        opacity: 1 !important;
        padding: 1rem !important;
    `;
    
    // Professional modal content styling
    const modalContent = modal.querySelector('.bg-white');
    if (modalContent) {
        modalContent.style.cssText = `
            background: white !important;
            border-radius: 0.75rem !important;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25) !important;
            width: 100% !important;
            max-width: 72rem !important;
            max-height: 95vh !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            z-index: 50001 !important;
        `;
        console.log('Marketing Manager: Professional modal content styled');
    }
    
    // Force it to the front of the DOM (this was the key fix!)
    document.body.appendChild(modal);
    
    // Update the SKU indicator in the header
    const skuIndicator = document.getElementById('currentEditingSku');
    if (skuIndicator) {
        skuIndicator.textContent = currentItemSku;
    }
    
    console.log('Marketing Manager: Modal opened with professional styling');
    
    // Load the item image in the header
    loadMarketingItemImage();
    
    // Load marketing data and show content tab
    loadMarketingData();
    showMarketingManagerTab('content');
}

function closeMarketingManager() {
    const modal = document.getElementById('marketingManagerModal');
    if (modal) {
        modal.style.display = 'none';
        console.log('Marketing Manager: Modal closed');
        
        // Clear any unsaved changes warning if needed
        hasMarketingChanges = false;
        hasTitleChanges = false;
        hasDescriptionChanges = false;
    }
}

function applyMarketingToItem() {
    console.log('Marketing Manager: Applying marketing content to item fields');
    
    // Get the marketing title and description fields
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
        
        appliedChanges++;
        console.log('Marketing Manager: Applied title:', newTitle);
    }
    
    // Apply marketing description to item description if both exist and have content
    if (marketingDescription && marketingDescription.value.trim() && itemDescriptionField) {
        const newDescription = marketingDescription.value.trim();
        itemDescriptionField.value = newDescription;
        
        // Add temporary highlight to show the change
        itemDescriptionField.style.backgroundColor = '#dcfce7';
        itemDescriptionField.style.border = '2px solid #22c55e';
        
        appliedChanges++;
        console.log('Marketing Manager: Applied description:', newDescription);
    }
    
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

function loadMarketingData() {
    console.log('Marketing Manager: loadMarketingData called');
    const contentDiv = document.getElementById('marketingManagerContent');
    console.log('Marketing Manager: Content div found:', !!contentDiv);
    
    if (contentDiv) {
        contentDiv.innerHTML = '<div class="modal-loading">' +
            '<div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mx-auto"></div>' +
            '<p class="mt-4 text-gray-600">Loading marketing data...</p>' +
        '</div>';
        
        // Force content div to be visible with clean styling
        contentDiv.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important;';
        
        console.log('Marketing Manager: Content div styled and populated');
    }
    
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
        '<div class="bg-purple-200 rounded-lg p-3 mb-4">' +
            '<div class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-end">' +
                '<div>' +
                    '<label class="block text-xs text-white mb-1">Brand Voice</label>' +
                    '<select id="brandVoice" class="w-full p-2 border border-purple-200 rounded bg-gray-50 text-sm" onchange="updateGlobalMarketingDefault(\'brand_voice\', this.value)">' +
                        '<option value="">Select voice...</option>' +
                        '<option value="friendly">Friendly</option>' +
                        '<option value="professional">Professional</option>' +
                        '<option value="playful">Playful</option>' +
                        '<option value="luxurious">Luxurious</option>' +
                        '<option value="casual">Casual</option>' +
                    '</select>' +
                '</div>' +
                '<div>' +
                    '<label class="block text-xs text-white mb-1">Content Tone</label>' +
                    '<select id="contentTone" class="w-full p-2 border border-purple-200 rounded bg-gray-50 text-sm" onchange="updateGlobalMarketingDefault(\'content_tone\', this.value)">' +
                        '<option value="">Select tone...</option>' +
                        '<option value="informative">Informative</option>' +
                        '<option value="persuasive">Persuasive</option>' +
                        '<option value="emotional">Emotional</option>' +
                        '<option value="urgent">Urgent</option>' +
                        '<option value="conversational">Conversational</option>' +
                    '</select>' +
                '</div>' +
                '<div>' +
                    '<button onclick="generateAllMarketingContent()" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded text-xs font-medium">' +
                        '🧠 Generate AI' +
                    '</button>' +
                '</div>' +
                '<div>' +
                    '<button onclick="generateFreshMarketingComparison()" class="w-full bg-orange-600 hover:bg-orange-700 text-white px-3 py-2 rounded text-xs font-medium">' +
                        '🔥 Fresh Start' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<div class="mt-2 text-center">' +
                '<p class="text-xs text-white">💡 Global settings • AI generates content for all tabs based on voice & tone</p>' +
            '</div>' +
        '</div>' +
        '<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">' +
            '<div class="bg-blue-50 rounded-lg p-4">' +
                '<label class="block text-sm font-medium text-gray-800 mb-2">📝 Item Title</label>' +
                                  '<textarea id="marketingTitle" class="w-full p-3 border border-blue-300 rounded-lg text-sm resize-none" rows="2" placeholder="Enter enhanced item title..."></textarea>' +
                '<div class="mt-2 flex justify-center">' +
                    '<button onclick="applyAndSaveMarketingTitle()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-xs font-medium" style="display: none;">' +
                        '📝 Apply & Save' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<div class="bg-green-50 rounded-lg p-4">' +
                '<label class="block text-sm font-medium text-gray-800 mb-2">📄 Item Description</label>' +
                                  '<textarea id="marketingDescription" class="w-full p-3 border border-green-300 rounded-lg text-sm resize-none" rows="4" placeholder="Enter detailed item description..."></textarea>' +
                '<div class="mt-2 flex justify-center">' +
                    '<button onclick="applyAndSaveMarketingDescription()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-xs font-medium" style="display: none;">' +
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
            '<div class="bg-orange-50 rounded-lg p-4">' +
                '<label class="block text-sm font-medium text-gray-700 mb-2">Primary Target Audience</label>' +
                '<textarea id="targetAudience" class="w-full p-3 border border-orange-200 rounded-lg" rows="3" placeholder="Describe your ideal customer..."></textarea>' +
                '<button onclick="saveMarketingField(\'target_audience\')" class="mt-2 text-orange-600 hover:text-orange-800 text-sm" style="display: none;">Save</button>' +
            '</div>' +
            '<div class="bg-pink-50 rounded-lg p-4">' +
                '<label class="block text-sm font-medium text-gray-700 mb-2">Demographics</label>' +
                '<textarea id="demographics" class="w-full p-3 border border-pink-200 rounded-lg" rows="3" placeholder="Age, gender, income, location..."></textarea>' +
                '<button onclick="saveMarketingField(\'demographic_targeting\')" class="mt-2 text-pink-600 hover:text-pink-800 text-sm" style="display: none;">Save</button>' +
            '</div>' +
        '</div>' +
        '<div class="bg-indigo-50 rounded-lg p-4">' +
            '<label class="block text-sm font-medium text-gray-700 mb-2">Psychographic Profile</label>' +
            '<textarea id="psychographics" class="w-full p-3 border border-indigo-200 rounded-lg" rows="3" placeholder="Interests, values, lifestyle, personality traits..."></textarea>' +
            '<button onclick="saveMarketingField(\'psychographic_profile\')" class="mt-2 text-indigo-600 hover:text-indigo-800 text-sm" style="display: none;">Save</button>' +
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
        <div class="max-h-[60vh] overflow-y-auto pr-2">
            <div class="space-y-6">
                <h3 class="text-lg font-semibold text-gray-800">Selling Points & Advantages</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Key Selling Points</label>
                            <button onclick="addListItem('selling_points')" class="text-green-600 hover:text-green-800 text-sm">+ Add</button>
                        </div>
                        <div id="sellingPointsList" class="space-y-2 mb-3 max-h-40 overflow-y-auto">
                            <!-- Dynamic content -->
                        </div>
                        <input type="text" id="newSellingPoint" placeholder="Enter new selling point..." class="w-full p-2 border border-green-200 rounded" onkeypress="if(event.key==='Enter') addListItem('selling_points')">
                    </div>
                    
                    <div class="bg-red-50 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Competitive Advantages</label>
                            <button onclick="addListItem('competitive_advantages')" class="text-red-600 hover:text-red-800 text-sm">+ Add</button>
                        </div>
                        <div id="competitiveAdvantagesList" class="space-y-2 mb-3 max-h-40 overflow-y-auto">
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
                    <div id="customerBenefitsList" class="space-y-2 mb-3 max-h-40 overflow-y-auto">
                        <!-- Dynamic content -->
                    </div>
                    <input type="text" id="newCustomerBenefit" placeholder="What benefit does customer get..." class="w-full p-2 border border-yellow-200 rounded" onkeypress="if(event.key==='Enter') addListItem('customer_benefits')">
                </div>
            </div>
        </div>
    `;
    
    loadExistingMarketingData();
}

function loadSEOTab(contentDiv) {
    contentDiv.innerHTML = `
        <div class="max-h-[60vh] overflow-y-auto pr-2">
            <div class="space-y-6">
                <h3 class="text-lg font-semibold text-gray-800">SEO & Keywords</h3>
                
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700">SEO Keywords</label>
                        <button onclick="addListItem('seo_keywords')" class="text-blue-600 hover:text-blue-800 text-sm">+ Add</button>
                    </div>
                    <div id="seoKeywordsList" class="space-y-2 mb-3 max-h-40 overflow-y-auto">
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
                        <button onclick="saveMarketingField('search_intent')" class="mt-2 text-purple-600 hover:text-purple-800 text-sm" style="display: none;">Save</button>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Seasonal Relevance</label>
                        <textarea id="seasonalRelevance" class="w-full p-3 border border-green-200 rounded-lg" rows="3" placeholder="Christmas, summer, back-to-school, etc..."></textarea>
                        <button onclick="saveMarketingField('seasonal_relevance')" class="mt-2 text-green-600 hover:text-green-800 text-sm" style="display: none;">Save</button>
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
        <div class="max-h-[60vh] overflow-y-auto pr-2">
            <div class="space-y-6">
                <h3 class="text-lg font-semibold text-gray-800">Conversion Optimization</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-orange-50 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Call-to-Action Suggestions</label>
                            <button onclick="addListItem('call_to_action_suggestions')" class="text-orange-600 hover:text-orange-800 text-sm">+ Add</button>
                        </div>
                        <div id="callToActionsList" class="space-y-2 mb-3 max-h-40 overflow-y-auto">
                            <!-- Dynamic content -->
                        </div>
                        <input type="text" id="newCallToAction" placeholder="Get Yours Today, Buy Now, etc..." class="w-full p-2 border border-orange-200 rounded" onkeypress="if(event.key==='Enter') addListItem('call_to_action_suggestions')">
                    </div>
                    
                    <div class="bg-red-50 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Urgency Factors</label>
                            <button onclick="addListItem('urgency_factors')" class="text-red-600 hover:text-red-800 text-sm">+ Add</button>
                        </div>
                        <div id="urgencyFactorsList" class="space-y-2 mb-3 max-h-40 overflow-y-auto">
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
                    <div id="conversionTriggersList" class="space-y-2 mb-3 max-h-40 overflow-y-auto">
                        <!-- Dynamic content -->
                    </div>
                    <input type="text" id="newConversionTrigger" placeholder="Free shipping, money-back guarantee..." class="w-full p-2 border border-purple-200 rounded" onkeypress="if(event.key==='Enter') addListItem('conversion_triggers')">
                </div>
            </div>
        </div>
    `;
    
    loadExistingMarketingData();
}

function loadExistingMarketingData() {
    if (!currentItemSku) {
        console.log('Marketing Manager: No currentItemSku available');
        return Promise.resolve();
    }
    
    console.log('Marketing Manager: Loading data for SKU:', currentItemSku);
    
    return fetch(`/api/marketing_manager.php?action=get_marketing_data&sku=${currentItemSku}&_t=${Date.now()}`)
    .then(response => response.json())
    .then(data => {
        console.log('Marketing Manager: API response for SKU', currentItemSku, ':', data);
        if (data.success && data.data) {
            console.log('Marketing Manager: Populating fields with item-specific data');
            populateMarketingFields(data.data);
        } else {
            console.log('Marketing Manager: No existing data found for SKU', currentItemSku, '- fields will be empty');
            clearMarketingFields(); // Clear any cached/previous content
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
    if (!currentItemSku) {
        console.log('Marketing Manager: No SKU available for image loading');
        return;
    }
    
    console.log('Marketing Manager: Loading primary image for SKU:', currentItemSku);
    
    fetch(`/api/get_item_images.php?sku=${encodeURIComponent(currentItemSku)}`)
    .then(response => response.json())
    .then(data => {
        const headerContainer = document.getElementById('marketingItemImageHeader');
        
        if (!headerContainer) {
            console.log('Marketing Manager: Header image container not found');
            return;
        }
        
        if (data.success && data.primaryImage && data.primaryImage.file_exists) {
            const primaryImage = data.primaryImage;
            console.log('Marketing Manager: Loading primary image:', primaryImage.image_path);
            
            headerContainer.innerHTML = '<div class="w-40 h-40 rounded-lg border-2 border-gray-200 overflow-hidden bg-white shadow-lg">' +
                '<img src="' + primaryImage.image_path + '" ' +
                     'alt="' + currentItemSku + '" ' +
                     'class="w-full h-full object-cover hover:scale-105 transition-transform duration-200" ' +
                     'onerror="this.parentElement.innerHTML=\'<div class=\\\'w-full h-full bg-gray-100 flex items-center justify-center text-gray-400 text-xs\\\'>📷</div>\'">' +
            '</div>';
        } else {
            console.log('Marketing Manager: No primary image found for SKU:', currentItemSku);
            // Show placeholder
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

function clearMarketingFields() {
    console.log('Marketing Manager: Clearing all fields to prevent cached content');
    
    // Clear text fields
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

function populateMarketingFields(data) {
    console.log('Marketing Manager: Populating fields with data:', data);
    
    // Populate text fields (excluding brand voice and content tone which are global settings)
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
        if (field && data[textFields[fieldId]]) {
            console.log(`Marketing Manager: Setting ${fieldId} to:`, data[textFields[fieldId]]);
            field.value = data[textFields[fieldId]];
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
    }
    
    console.log('Marketing Manager: Fields populated successfully, fieldsPopulated:', fieldsPopulated);
    
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
    };

    console.log('Fresh Marketing Generation: Generating fresh content for SKU:', currentItemSku);

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

// Update global marketing default
async function updateGlobalMarketingDefault(settingType, value) {
    try {
        const updateData = {
            auto_apply_defaults: 'true'
        };
        
        if (settingType === 'brand_voice') {
            updateData.default_brand_voice = value;
            // Also get current content tone to include in update
            const contentToneField = document.getElementById('contentTone');
            updateData.default_content_tone = contentToneField ? contentToneField.value : 'conversational';
        } else if (settingType === 'content_tone') {
            updateData.default_content_tone = value;
            // Also get current brand voice to include in update
            const brandVoiceField = document.getElementById('brandVoice');
            updateData.default_brand_voice = brandVoiceField ? brandVoiceField.value : 'friendly';
        }
        
        const response = await fetch('/api/website_config.php?action=update_marketing_defaults', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(updateData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess( `Global ${settingType.replace('_', ' ')} updated successfully!`);
        } else {
            showError( data.error || 'Failed to update global setting');
        }
    } catch (error) {
        console.error('Error updating global marketing default:', error);
        showError( 'Failed to update global setting');
    }
}

// AI Content Generation with Comparison Modal
function generateAllMarketingContent() {
    console.log('generateAllMarketingContent called');
    
    if (!currentItemSku) {
        showError( 'No item selected for marketing content generation');
        return;
    }
    
    // Show the comparison modal
    showAIComparisonModal();
    
    // Start the AI analysis process
    startAIAnalysisProcess();
}

function showAIComparisonModal() {
    console.log('showAIComparisonModal called');
    const modal = document.getElementById('aiComparisonModal');
    const progressSection = document.getElementById('aiAnalysisProgressSection');
    
    if (modal) {
        console.log('AI Comparison Modal found, applying visibility fixes');
        
        // Remove hidden class
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
        document.body.appendChild(modal);
        
        console.log('AI Comparison Modal visibility forced');
    } else {
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
        contentDiv.innerHTML = '<div class="text-center text-gray-500 py-8">AI analysis in progress...</div>';
    }
    
    console.log('AI Comparison Modal setup complete');
}

function closeAIComparisonModal() {
    console.log('closeAIComparisonModal function called');
    const modal = document.getElementById('aiComparisonModal');
    if (modal) {
        console.log('AI Comparison Modal found, hiding modal');
        
        // Reset all inline styles that were set by showAIComparisonModal
        modal.style.cssText = '';
        
        // Add the hidden class
        modal.classList.add('hidden');
        
        // Force hide with inline styles to override any remaining CSS
        modal.style.display = 'none !important';
        modal.style.visibility = 'hidden !important';
        modal.style.opacity = '0 !important';
        
        console.log('AI Comparison Modal closed successfully');
    } else {
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
    aiComparisonData = data;
    selectedChanges = {};
    
    // First, get current marketing data from database to compare against
    console.log('Loading current marketing data from database for comparison...');
    
    fetch(`/api/marketing_manager.php?action=get_marketing_data&sku=${currentItemSku}&_t=${Date.now()}`)
        .then(response => response.json())
        .then(currentData => {
            console.log('Current marketing data loaded:', currentData);
            buildComparisonInterface(data, currentData.data);
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
    html += '<div class="text-center mb-6">';
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
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="selectAllComparison" class="mr-3 h-4 w-4 text-blue-600 border-gray-300 rounded" onchange="toggleSelectAll()">
                        <label for="selectAllComparison" class="font-medium text-blue-800">Select All AI Suggestions</label>
                    </div>
                    <span class="text-sm text-blue-600">${availableFields.length} suggestions available</span>
                </div>
                <p class="text-sm text-blue-600 mt-2">Apply all AI-generated content to your item at once</p>
            </div>
        `);
    }
    
    // Only show "no changes" message if there are truly no available fields
    if (availableFields.length === 0) {
        html += '<div class="text-center py-8 text-gray-500">';
        html += '<p>No changes detected. All AI suggestions match your current content.</p>';
        html += '<div class="mt-4 text-xs bg-gray-100 p-4 rounded">';
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
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-medium text-gray-800">${fieldLabel}</h4>
                <label class="flex items-center">
                    <input type="checkbox" id="${cardId}-checkbox" class="mr-2" onchange="toggleComparison('${fieldKey}')">
                    <span class="text-sm text-gray-600">Apply AI suggestion</span>
                </label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-3 rounded">
                    <h5 class="text-sm font-medium text-gray-600 mb-2">Current</h5>
                    <p class="text-sm text-gray-800">${currentValue || '<em>No current value</em>'}</p>
                </div>
                <div class="bg-green-50 p-3 rounded">
                    <h5 class="text-sm font-medium text-green-600 mb-2">AI Suggested</h5>
                    <p class="text-sm text-gray-800">${suggestedValue}</p>
                </div>
            </div>
        </div>
    `;
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAllComparison');
    const isChecked = selectAllCheckbox.checked;
    
    // Get all available fields and toggle their checkboxes
    if (window.availableComparisonFields) {
        window.availableComparisonFields.forEach(fieldKey => {
            const fieldCheckbox = document.getElementById(`comparison-${fieldKey}-checkbox`);
            if (fieldCheckbox) {
                fieldCheckbox.checked = isChecked;
                // Trigger the individual toggle to update selectedChanges
                toggleComparison(fieldKey);
            }
        });
    }
}

function toggleComparison(fieldKey) {
    console.log('toggleComparison called for field:', fieldKey);
    const checkbox = document.getElementById(`comparison-${fieldKey}-checkbox`);
    console.log('Checkbox found:', checkbox, 'checked:', checkbox?.checked);
    if (checkbox.checked) {
        // Get the value from the correct location in the AI data
        let value = null;
        if (fieldKey === 'title') {
            value = aiComparisonData.title;
        } else if (fieldKey === 'description') {
            value = aiComparisonData.description;
        } else if (fieldKey === 'target_audience') {
            value = aiComparisonData.targetAudience;
        } else if (fieldKey === 'demographic_targeting' || fieldKey === 'psychographic_profile') {
            value = aiComparisonData.marketingIntelligence?.[fieldKey];
        }
        
        if (value) {
            selectedChanges[fieldKey] = value;
            console.log('Added to selectedChanges:', fieldKey, '=', value);
        }
    } else {
        delete selectedChanges[fieldKey];
        console.log('Removed from selectedChanges:', fieldKey);
    }
    
    console.log('Current selectedChanges after toggle:', selectedChanges);
    
    // Update select all checkbox state based on individual selections
    updateSelectAllState();
    
    // Update apply button text
    const applyBtn = document.getElementById('applyChangesBtn');
    const selectedCount = Object.keys(selectedChanges).length;
    if (applyBtn) {
        applyBtn.textContent = selectedCount > 0 ? `Apply ${selectedCount} Selected Changes` : 'Apply Selected Changes';
    }
}

function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById('selectAllComparison');
    if (!selectAllCheckbox || !window.availableComparisonFields) return;
    
    const totalFields = window.availableComparisonFields.length;
    const selectedCount = Object.keys(selectedChanges).length;
    
    if (selectedCount === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    } else if (selectedCount === totalFields) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.indeterminate = false;
    } else {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = true;
    }
}

function applySelectedChanges() {
    console.log('applySelectedChanges called');
    console.log('selectedChanges object:', selectedChanges);
    console.log('selectedChanges keys length:', Object.keys(selectedChanges).length);
    
    if (Object.keys(selectedChanges).length === 0) {
        console.log('No changes selected, showing warning');
        showWarning('Please select at least one change to apply');
        return;
    }
    
    if (!currentItemSku) {
        console.error('No SKU available for saving changes');
        showError('Unable to save changes - no item SKU available');
        return;
    }
    
    console.log('Saving selected changes to database for SKU:', currentItemSku);
    
    // Save all selected changes to the database
    const savePromises = Object.entries(selectedChanges).map(([fieldKey, value]) => {
        console.log(`Saving field ${fieldKey} to database:`, value);
        
        return fetch('/api/marketing_manager.php?action=update_field', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                sku: currentItemSku,
                field: fieldKey === 'title' ? 'suggested_title' : 
                       fieldKey === 'description' ? 'suggested_description' : fieldKey,
                value: value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`✅ Successfully saved ${fieldKey} to database`);
            } else {
                console.error(`❌ Failed to save ${fieldKey}:`, data.error);
                throw new Error(`Failed to save ${fieldKey}: ${data.error}`);
            }
            return { fieldKey, success: true };
        })
        .catch(error => {
            console.error(`❌ Error saving ${fieldKey}:`, error);
            return { fieldKey, success: false, error };
        });
    });
    
    // Wait for all database saves to complete
    Promise.all(savePromises)
        .then(results => {
            const successCount = results.filter(r => r.success).length;
            const failCount = results.length - successCount;
            
            console.log(`Database save results: ${successCount} success, ${failCount} failed`);
            
            if (successCount > 0) {
                // Update main form fields for title and description only
                // (Marketing Manager will load from database when opened)
                updateMainFormFields();
                
                // Show success message
                showSuccess(`${successCount} changes saved to database successfully!`);
                
                // If Marketing Manager modal is open, refresh its content from database
                refreshMarketingManagerContent();
                
            } else {
                showError('Failed to save changes to database');
            }
            
            // Close AI comparison modal immediately
            console.log('Closing AI Comparison Modal...');
            closeAIComparisonModal();
        })
        .catch(error => {
            console.error('Error in batch save operation:', error);
            showError('Failed to save changes to database');
        });
    
    function updateMainFormFields() {
        // Only update title and description in main form
        // Other fields exist only in Marketing Manager modal
        Object.entries(selectedChanges).forEach(([fieldKey, value]) => {
            let targetField = null;
            
            switch (fieldKey) {
                case 'title':
                    targetField = document.getElementById('name');
                    break;
                case 'description':
                    targetField = document.getElementById('description');
                    break;
                // Don't update marketing-specific fields in main form
                // They will be loaded from database when Marketing Manager opens
            }
            
            if (targetField) {
                console.log(`Updating main form field ${fieldKey}`);
                targetField.value = value;
                targetField.style.backgroundColor = '#f0fdf4'; // Light green highlight
                
                // Remove highlight after delay
                setTimeout(() => {
                    targetField.style.backgroundColor = '';
                }, 3000);
            }
        });
    }
    
    function refreshMarketingManagerContent() {
        // Check if Marketing Manager modal is open
        const marketingModal = document.getElementById('marketingManagerModal');
        if (marketingModal && !marketingModal.classList.contains('hidden')) {
            console.log('Marketing Manager is open - refreshing content from database');
            
            // Reload the current tab content to reflect database changes
            const activeTab = document.querySelector('.admin-tab-button.active');
            if (activeTab) {
                const tabName = activeTab.textContent.includes('📝') ? 'content' :
                              activeTab.textContent.includes('👥') ? 'audience' :
                              activeTab.textContent.includes('⭐') ? 'selling' :
                              activeTab.textContent.includes('🔍') ? 'seo' :
                              activeTab.textContent.includes('💰') ? 'conversion' : 'content';
                
                console.log('Refreshing active tab:', tabName);
                loadMarketingTabContent(tabName);
            }
        }
    }
}
</script>
<script>
// AI Content Comparison Modal Functions (cleaned up)
let aiComparisonData = {};
let selectedChanges = {};
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

// Make functions globally accessible for inline onclick handlers
// Marketing Manager Button Event Listener
document.addEventListener("DOMContentLoaded", function() {
    document.addEventListener("click", function(event) {
        if (event.target && event.target.id === "open-marketing-manager-btn") {
            if (typeof openMarketingManager === "function") {
                openMarketingManager();
            } else {
                console.error("openMarketingManager function not found");
            }
        }
    });
});

// Make functions globally accessible
window.openMarketingManager = openMarketingManager;
window.closeMarketingManager = closeMarketingManager;


// Clean Marketing Manager Event Listener (single instance) 
document.addEventListener("click", function(event) {
    if (event.target && event.target.id === "open-marketing-manager-btn") {
        event.preventDefault();
        event.stopPropagation();
        console.log("Marketing Manager button clicked - opening modal");
        
        // Don't close the edit modal - just open Marketing Manager on top
        if (typeof openMarketingManager === "function") {
            openMarketingManager();
        } else {
            console.error("openMarketingManager function not found");
        }
        return false; // Prevent any other event handlers
    }
});

// Color Management Functions

// Load colors for the current item
async function loadItemColors() {
    console.log('loadItemColors called, currentItemSku:', currentItemSku);
    
    // Try to get SKU from multiple sources
    if (!currentItemSku) {
        const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
        if (skuField && skuField.value) {
            currentItemSku = skuField.value;
            console.log('Found SKU from field:', currentItemSku);
        }
    }
    
    if (!currentItemSku) {
        console.log('No SKU available for loading colors');
        const colorsLoading = document.getElementById('colorsLoading');
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
    
    try {
        console.log('Fetching colors for SKU:', currentItemSku);
        const response = await fetch(`/api/item_colors.php?action=get_all_colors&item_sku=${currentItemSku}`);
        const data = await response.json();
        
        console.log('Colors API response:', data);
        
        if (data.success) {
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
            <div class="mb-3 p-2 border rounded-lg ${syncClass}">
                <div class="text-sm font-medium">${syncIcon} ${syncMessage}</div>
                ${!isInSync ? '<div class="text-xs mt-1">Click "Sync Stock" to fix this.</div>' : ''}
            </div>
        `;
    }
    
    html += colors.map(color => {
        const isActive = color.is_active == 1;
        const activeClass = isActive ? 'bg-white' : 'bg-gray-100 opacity-75';
        const activeText = isActive ? '' : ' (Inactive)';
        
        return `
            <div class="color-item flex items-center justify-between p-3 border border-gray-200 rounded-lg ${activeClass}">
                <div class="flex items-center space-x-3">
                    <div class="color-swatch w-8 h-8 rounded-full border-2 border-gray-300" style="background-color: ${color.color_code || '#ccc'}"></div>
                    <div>
                        <div class="font-medium text-gray-800">${color.color_name}${activeText}</div>
                        <div class="text-sm text-gray-500">${color.stock_level} in stock</div>
                        ${color.image_path ? `<div class="text-xs text-blue-600">Image: ${color.image_path}</div>` : ''}
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" onclick="editColor(${color.id})" class="px-2 py-1 bg-blue-500 text-white rounded text-xs hover:bg-blue-600">
                        Edit
                    </button>
                    <button type="button" onclick="deleteColor(${color.id})" class="px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600">
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
async function deleteColor(colorId) {
    console.log('🗑️ deleteColor called with colorId:', colorId);
    
    // Create a styled confirmation modal instead of browser confirm
    const confirmResult = await showStyledConfirm(
        'Delete Color',
        'Are you sure you want to delete this color? This action cannot be undone.',
        'Delete',
        'Cancel'
    );
    
    if (!confirmResult) {
        console.log('❌ User cancelled color deletion');
        return;
    }
    
    console.log('✅ User confirmed color deletion, proceeding...');
    
    try {
        const response = await fetch('/api/item_colors.php?action=delete_color', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ color_id: colorId })
        });
        
        const data = await response.json();
        console.log('📡 API response:', data);
        
        if (data.success) {
            console.log('🎉 Color deleted successfully, calling showToast...');
            showSuccess( 'Color deleted successfully');
            loadItemColors(); // Reload colors
        } else {
            console.log('❌ API error:', data.message);
            showError( 'Error deleting color: ' + data.message);
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
            const imageSelect = document.getElementById('colorImagePath');
            if (imageSelect && color.image_path) {
                imageSelect.value = color.image_path;
                updateImagePreview(); // Update preview when editing existing color
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
            highlightSelectedImageInGrid(null);
        }, 100);
    }
    
    modal.classList.remove('hidden');
}

// Create color modal
function createColorModal() {
    const modalHTML = `
        <div id="colorModal" class="modal-overlay hidden">
            <div class="modal-content" style="max-width: 900px; max-height: 85vh; overflow-y: auto;">
                <div class="modal-header">
                    <h2 id="colorModalTitle">Add New Color</h2>
                    <button type="button" class="modal-close" onclick="closeColorModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="colorForm" onsubmit="saveColor(event)">
                        <input type="hidden" id="colorId" name="colorId">
                        
                        <!-- Two-column layout -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Left Column: Color Selection & Basic Info -->
                            <div class="space-y-4">
                                <div>
                                    <label for="globalColorSelect" class="block text-sm font-medium text-gray-700 mb-2">
                                        Select Color *
                                        <span class="text-xs text-gray-500">(from predefined colors)</span>
                                    </label>
                                    <select id="globalColorSelect" name="globalColorSelect" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2" style="--tw-ring-color: #87ac3a;" onchange="handleGlobalColorSelection()">
                                        <option value="">Choose a color...</option>
                                    </select>
                                    <div class="mt-2 text-xs">
                                        <a href="#" onclick="openGlobalColorsManagement()" class="text-blue-600 hover:text-blue-800">
                                            ⚙️ Manage Global Colors in Settings
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Hidden fields populated by global color selection -->
                                <input type="hidden" id="colorName" name="colorName">
                                <input type="hidden" id="colorCode" name="colorCode">
                                
                                <!-- Display selected color -->
                                <div id="selectedColorPreview" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Selected Color Preview</label>
                                    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                        <div id="colorPreviewSwatch" class="w-10 h-10 rounded border-2 border-gray-300 shadow-sm"></div>
                                        <div>
                                            <div id="colorPreviewName" class="font-medium text-gray-900"></div>
                                            <div id="colorPreviewCode" class="text-sm text-gray-500"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="colorStockLevel" class="block text-sm font-medium text-gray-700 mb-2">Stock Level</label>
                                    <input type="number" id="colorStockLevel" name="stockLevel" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2" style="--tw-ring-color: #87ac3a;">
                                </div>
                                
                                <div>
                                    <label for="displayOrder" class="block text-sm font-medium text-gray-700 mb-2">Display Order</label>
                                    <input type="number" id="displayOrder" name="displayOrder" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2" style="--tw-ring-color: #87ac3a;">
                                </div>
                                
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" id="isActive" name="isActive" class="mr-2">
                                        <span class="text-sm font-medium text-gray-700">Active (visible to customers)</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Right Column: Image Selection & Preview -->
                            <div class="space-y-4">
                                <div>
                                    <label for="colorImagePath" class="block text-sm font-medium text-gray-700 mb-2">Associated Image</label>
                                    <select id="colorImagePath" name="colorImagePath" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2" style="--tw-ring-color: #87ac3a;" onchange="updateImagePreview()">
                                        <option value="">No specific image (use default)</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Choose which item image to show when this color is selected</p>
                                </div>
                                
                                <!-- Image Preview -->
                                <div id="imagePreviewContainer" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Image Preview</label>
                                    <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
                                        <div class="flex justify-center">
                                            <img id="imagePreview" src="" alt="Selected image preview" class="max-w-full max-h-48 object-contain rounded border border-gray-200 shadow-sm">
                                        </div>
                                        <div id="imagePreviewInfo" class="mt-2 text-center">
                                            <div id="imagePreviewName" class="text-sm font-medium text-gray-700"></div>
                                            <div id="imagePreviewPath" class="text-xs text-gray-500"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Available Images Grid -->
                                <div id="availableImagesGrid" class="hidden">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Available Images</label>
                                    <div class="grid grid-cols-3 gap-2 max-h-32 overflow-y-auto border border-gray-200 rounded p-2">
                                        <!-- Images will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                            <button type="button" onclick="closeColorModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-white rounded transition-colors" style="background-color: #87ac3a;" onmouseover="this.style.backgroundColor='#6b8e23'" onmouseout="this.style.backgroundColor='#87ac3a'">
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
        
        const imageSelect = document.getElementById('colorImagePath');
        const availableImagesGrid = document.getElementById('availableImagesGrid');
        
        if (!imageSelect) return;
        
        // Clear existing options except the first one
        imageSelect.innerHTML = '<option value="">No specific image (use default)</option>';
        
        if (data.success && data.images && data.images.length > 0) {
            // Populate dropdown
            data.images.forEach(image => {
                const option = document.createElement('option');
                option.value = image.image_path;
                option.textContent = `${image.image_path}${image.is_primary ? ' (Primary)' : ''}`;
                imageSelect.appendChild(option);
            });
            
            // Populate images grid
            if (availableImagesGrid) {
                const gridContainer = availableImagesGrid.querySelector('.grid');
                gridContainer.innerHTML = '';
                
                data.images.forEach(image => {
                    const imgContainer = document.createElement('div');
                    imgContainer.className = 'relative cursor-pointer hover:opacity-75 transition-opacity';
                    imgContainer.onclick = () => selectImageFromGrid(image.image_path);
                    
                    const img = document.createElement('img');
                    img.src = `/images/items/${image.image_path}`;
                    img.alt = image.image_path;
                    img.className = 'w-full h-16 object-cover rounded border border-gray-200';
                    img.onerror = () => {
                        img.src = '/images/items/placeholder.png';
                    };
                    
                    const label = document.createElement('div');
                    label.className = 'text-xs text-gray-600 mt-1 truncate';
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
                
                availableImagesGrid.classList.remove('hidden');
            }
        } else {
            // Hide grid if no images
            if (availableImagesGrid) {
                availableImagesGrid.classList.add('hidden');
            }
        }
    } catch (error) {
        console.error('Error loading available images:', error);
    }
}

// Select image from grid
function selectImageFromGrid(imagePath) {
    const imageSelect = document.getElementById('colorImagePath');
    if (imageSelect) {
        imageSelect.value = imagePath;
        updateImagePreview();
    }
}

// Update image preview when selection changes
function updateImagePreview() {
    const imageSelect = document.getElementById('colorImagePath');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewName = document.getElementById('imagePreviewName');
    const imagePreviewPath = document.getElementById('imagePreviewPath');
    
    if (!imageSelect || !imagePreviewContainer) return;
    
    const selectedImagePath = imageSelect.value;
    
    if (selectedImagePath) {
        // Show preview
        imagePreview.src = `/images/items/${selectedImagePath}`;
        imagePreview.onerror = () => {
            imagePreview.src = '/images/items/placeholder.png';
        };
        
        imagePreviewName.textContent = selectedImagePath;
        imagePreviewPath.textContent = `/images/items/${selectedImagePath}`;
        
        imagePreviewContainer.classList.remove('hidden');
        
        // Highlight selected image in grid
        highlightSelectedImageInGrid(selectedImagePath);
    } else {
        // Hide preview
        imagePreviewContainer.classList.add('hidden');
        highlightSelectedImageInGrid(null);
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
                container.classList.add('ring-2', 'ring-green-500');
            } else {
                container.classList.remove('ring-2', 'ring-green-500');
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
        const response = await fetch('/api/item_colors.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'sync_stock',
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
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded - modalMode:', modalMode, 'currentItemSku:', currentItemSku);
    
    // Load colors when in edit mode and we have a valid SKU
    if ((modalMode === 'edit' || modalMode === 'view') && currentItemSku) {
        console.log('Loading colors for SKU:', currentItemSku);
        setTimeout(loadItemColors, 500); // Small delay to ensure elements are ready
    } else if (document.getElementById('sku') || document.getElementById('skuDisplay')) {
        // Fallback: try to get SKU from form fields
        const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
        if (skuField && skuField.value) {
            currentItemSku = skuField.value;
            console.log('Found SKU from field:', currentItemSku);
            setTimeout(loadItemColors, 500);
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
    if (!currentItemSku) {
        console.log('No currentItemSku available for loading sizes');
        return;
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
        
        if (data.success) {
            console.log('Loaded sizes:', data.sizes);
            renderSizes(data.sizes);
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
            <div class="mb-3 p-2 border rounded-lg ${syncClass}">
                <div class="text-sm font-medium">${syncIcon} ${syncMessage}</div>
                ${!isInSync ? '<div class="text-xs mt-1">Click "Sync Stock" to fix this.</div>' : ''}
            </div>
        `;
    }
    
    // Render each group
    Object.keys(groupedSizes).forEach(groupKey => {
        const group = groupedSizes[groupKey];
        
        // Add group header if there are multiple groups
        if (Object.keys(groupedSizes).length > 1) {
            html += `
                <div class="mb-2 font-medium text-gray-700 flex items-center">
                    ${group.color_code ? `<div class="w-4 h-4 rounded border mr-2" style="background-color: ${group.color_code}"></div>` : ''}
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
                <div class="size-item flex items-center justify-between p-3 border border-gray-200 rounded-lg ${activeClass} ml-${Object.keys(groupedSizes).length > 1 ? '4' : '0'}">
                    <div class="flex items-center space-x-3">
                        <div class="size-badge bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm font-medium">
                            ${size.size_code}
                        </div>
                        <div>
                            <div class="font-medium text-gray-800">${size.size_name}${activeText}${priceAdjustmentText}</div>
                            <div class="text-sm text-gray-500">${size.stock_level} in stock</div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button type="button" onclick="editSize(${size.id})" class="px-2 py-1 bg-blue-500 text-white rounded text-xs hover:bg-blue-600">
                            Edit
                        </button>
                        <button type="button" onclick="deleteSize(${size.id})" class="px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600">
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
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h2 id="sizeModalTitle" class="text-xl font-semibold text-gray-800">Add New Size</h2>
                    <button type="button" onclick="closeSizeModal()" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="sizeForm" onsubmit="saveSize(event)">
                        <input type="hidden" id="sizeId" name="sizeId">
                        
                        <div class="mb-4">
                            <label for="sizeColorId" class="block text-sm font-medium text-gray-700 mb-2">Color Association</label>
                            <select id="sizeColorId" name="sizeColorId" class="w-full px-3 py-2 border border-gray-300 rounded">
                                <option value="">General Size (No specific color)</option>
                            </select>
                            <div class="text-xs text-gray-500 mt-1">Choose a color if this size is specific to a particular color variant</div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="sizeName" class="block text-sm font-medium text-gray-700 mb-2">Size Name *</label>
                                <input type="text" id="sizeName" name="sizeName" placeholder="e.g., Medium" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                            </div>
                            <div>
                                <label for="sizeCode" class="block text-sm font-medium text-gray-700 mb-2">Size Code *</label>
                                <select id="sizeCode" name="sizeCode" class="w-full px-3 py-2 border border-gray-300 rounded" required>
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
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="sizeStockLevel" class="block text-sm font-medium text-gray-700 mb-2">Stock Level</label>
                                <input type="number" id="sizeStockLevel" name="sizeStockLevel" min="0" value="0" class="w-full px-3 py-2 border border-gray-300 rounded">
                            </div>
                            <div>
                                <label for="sizePriceAdjustment" class="block text-sm font-medium text-gray-700 mb-2">Price Adjustment ($)</label>
                                <input type="number" id="sizePriceAdjustment" name="sizePriceAdjustment" step="0.01" value="0.00" class="w-full px-3 py-2 border border-gray-300 rounded">
                                <div class="text-xs text-gray-500 mt-1">Extra charge for this size (e.g., +$2 for XXL)</div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="sizeDisplayOrder" class="block text-sm font-medium text-gray-700 mb-2">Display Order</label>
                                <input type="number" id="sizeDisplayOrder" name="sizeDisplayOrder" min="0" value="0" class="w-full px-3 py-2 border border-gray-300 rounded">
                                <div class="text-xs text-gray-500 mt-1">Lower numbers appear first</div>
                            </div>
                            <div class="flex items-center pt-6">
                                <label class="flex items-center">
                                    <input type="checkbox" id="sizeIsActive" name="sizeIsActive" class="mr-2">
                                    <span class="text-sm font-medium text-gray-700">Active (available to customers)</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeSizeModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-white rounded transition-colors" style="background-color: #87ac3a;" onmouseover="this.style.backgroundColor='#6b8e23'" onmouseout="this.style.backgroundColor='#87ac3a'">
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
        const response = await fetch('/api/item_sizes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'sync_stock',
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

// Add to the existing DOMContentLoaded event listener
const originalDOMContentLoaded = document.addEventListener;
document.addEventListener('DOMContentLoaded', function() {
    // Call existing color loading logic
    console.log('DOMContentLoaded - modalMode:', modalMode, 'currentItemSku:', currentItemSku);
    
    // Load colors when in edit mode and we have a valid SKU
    if ((modalMode === 'edit' || modalMode === 'view') && currentItemSku) {
        console.log('Loading colors for SKU:', currentItemSku);
        setTimeout(loadItemColors, 500);
        setTimeout(initializeSizeManagement, 600); // Initialize sizes after colors
    } else if (document.getElementById('sku') || document.getElementById('skuDisplay')) {
        // Fallback: try to get SKU from form fields
        const skuField = document.getElementById('sku') || document.getElementById('skuDisplay');
        if (skuField && skuField.value) {
            currentItemSku = skuField.value;
            console.log('Found SKU from field:', currentItemSku);
            setTimeout(loadItemColors, 500);
            setTimeout(initializeSizeManagement, 600);
        }
    }
});

// Color Template Management Functions
let colorTemplates = [];
let sizeTemplates = [];

// Open Color Template Modal
async function openColorTemplateModal() {
    if (!currentItemSku) {
        showError('Please save the item first before applying templates');
        return;
    }
    
    // Create modal if it doesn't exist
    if (!document.getElementById('colorTemplateModal')) {
        createColorTemplateModal();
    }
    
    // Load templates
    await loadColorTemplates();
    
    // Show modal
    const modal = document.getElementById('colorTemplateModal');
    modal.classList.remove('hidden');
}

// Create Color Template Modal
function createColorTemplateModal() {
    const modalHTML = `
        <div id="colorTemplateModal" class="modal-overlay hidden">
            <div class="modal-content" style="max-width: 800px;">
                <div class="modal-header">
                    <h2 class="text-xl font-bold text-gray-800">🎨 Color Templates</h2>
                    <button type="button" onclick="closeColorTemplateModal()" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="modal-body" style="max-height: 600px; overflow-y: auto;">
                    <!-- Template Categories -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Category:</label>
                        <select id="colorTemplateCategory" onchange="filterColorTemplates()" class="w-full px-3 py-2 border border-gray-300 rounded">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                    
                    <!-- Template List -->
                    <div id="colorTemplatesList" class="space-y-3">
                        <div class="text-center text-gray-500">Loading templates...</div>
                    </div>
                    
                    <!-- Application Options -->
                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h3 class="font-medium text-blue-800 mb-3">Application Options</h3>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" id="replaceExistingColors" class="mr-2">
                                <span class="text-sm">Replace existing colors (clear current colors first)</span>
                            </label>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Default Stock Level for New Colors:</label>
                                <input type="number" id="defaultColorStock" value="0" min="0" class="w-32 px-3 py-2 border border-gray-300 rounded text-sm">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeColorTemplateModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="button" onclick="applySelectedColorTemplate()" id="applyColorTemplateBtn" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700" disabled>
                        Apply Template
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Load Color Templates
async function loadColorTemplates() {
    try {
        const response = await fetch('/api/color_templates.php?action=get_all');
        const data = await response.json();
        
        if (data.success) {
            colorTemplates = data.templates;
            renderColorTemplates();
            loadColorTemplateCategories();
        } else {
            showError('Error loading color templates: ' + data.message);
        }
    } catch (error) {
        console.error('Error loading color templates:', error);
        showError('Error loading color templates');
    }
}

// Load Color Template Categories
function loadColorTemplateCategories() {
    const categorySelect = document.getElementById('colorTemplateCategory');
    if (!categorySelect) return;
    
    const categories = [...new Set(colorTemplates.map(t => t.category))].sort();
    
    categorySelect.innerHTML = '<option value="">All Categories</option>';
    categories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categorySelect.appendChild(option);
    });
}

// Filter Color Templates
function filterColorTemplates() {
    renderColorTemplates();
}

// Render Color Templates
function renderColorTemplates() {
    const container = document.getElementById('colorTemplatesList');
    if (!container) return;
    
    const selectedCategory = document.getElementById('colorTemplateCategory')?.value || '';
    const filteredTemplates = selectedCategory 
        ? colorTemplates.filter(t => t.category === selectedCategory)
        : colorTemplates;
    
    if (filteredTemplates.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500">No templates found</div>';
        return;
    }
    
    container.innerHTML = filteredTemplates.map(template => `
        <div class="template-item border border-gray-200 rounded-lg p-4 hover:border-purple-300 cursor-pointer" 
             onclick="selectColorTemplate(${template.id})" data-template-id="${template.id}">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <h4 class="font-medium text-gray-800">${template.template_name}</h4>
                    <p class="text-sm text-gray-600">${template.description || 'No description'}</p>
                </div>
                <div class="text-right">
                    <span class="inline-block px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">${template.category}</span>
                    <div class="text-xs text-gray-500 mt-1">${template.color_count} colors</div>
                </div>
            </div>
            <div class="template-preview" id="colorPreview${template.id}">
                <div class="text-xs text-gray-500">Loading colors...</div>
            </div>
        </div>
    `).join('');
    
    // Load color previews
    filteredTemplates.forEach(template => {
        loadColorTemplatePreview(template.id);
    });
}

// Load Color Template Preview
async function loadColorTemplatePreview(templateId) {
    try {
        const response = await fetch(`/api/color_templates.php?action=get_template&template_id=${templateId}`);
        const data = await response.json();
        
        if (data.success && data.template.colors) {
            const previewContainer = document.getElementById(`colorPreview${templateId}`);
            if (previewContainer) {
                previewContainer.innerHTML = `
                    <div class="flex flex-wrap gap-1 mt-2">
                        ${data.template.colors.map(color => `
                            <div class="flex items-center space-x-1 text-xs">
                                <div class="w-4 h-4 rounded border border-gray-300" style="background-color: ${color.color_code || '#ccc'}"></div>
                                <span>${color.color_name}</span>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading color template preview:', error);
    }
}

// Select Color Template
function selectColorTemplate(templateId) {
    // Remove previous selection
    document.querySelectorAll('.template-item').forEach(item => {
        item.classList.remove('border-purple-500', 'bg-purple-50');
    });
    
    // Add selection to clicked template
    const templateItem = document.querySelector(`[data-template-id="${templateId}"]`);
    if (templateItem) {
        templateItem.classList.add('border-purple-500', 'bg-purple-50');
    }
    
    // Enable apply button
    const applyBtn = document.getElementById('applyColorTemplateBtn');
    if (applyBtn) {
        applyBtn.disabled = false;
        applyBtn.setAttribute('data-template-id', templateId);
    }
}

// Apply Selected Color Template
async function applySelectedColorTemplate() {
    const applyBtn = document.getElementById('applyColorTemplateBtn');
    const templateId = applyBtn?.getAttribute('data-template-id');
    
    if (!templateId) {
        showError('Please select a template first');
        return;
    }
    
    const replaceExisting = document.getElementById('replaceExistingColors')?.checked || false;
    const defaultStock = parseInt(document.getElementById('defaultColorStock')?.value) || 0;
    
    try {
        applyBtn.disabled = true;
        applyBtn.textContent = 'Applying...';
        
        const response = await fetch('/api/color_templates.php?action=apply_to_item', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                template_id: parseInt(templateId),
                item_sku: currentItemSku,
                replace_existing: replaceExisting,
                default_stock: defaultStock
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(`Template applied successfully! Added ${data.colors_added} colors.`);
            closeColorTemplateModal();
            loadItemColors(); // Reload colors
        } else {
            showError('Error applying template: ' + data.message);
        }
    } catch (error) {
        console.error('Error applying color template:', error);
        showError('Error applying color template');
    } finally {
        applyBtn.disabled = false;
        applyBtn.textContent = 'Apply Template';
    }
}

// Close Color Template Modal
function closeColorTemplateModal() {
    const modal = document.getElementById('colorTemplateModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Size Template Management Functions

// Open Size Template Modal
async function openSizeTemplateModal() {
    if (!currentItemSku) {
        showError('Please save the item first before applying templates');
        return;
    }
    
    // Create modal if it doesn't exist
    if (!document.getElementById('sizeTemplateModal')) {
        createSizeTemplateModal();
    }
    
    // Load templates
    await loadSizeTemplates();
    
    // Show modal
    const modal = document.getElementById('sizeTemplateModal');
    modal.classList.remove('hidden');
}

// Create Size Template Modal
function createSizeTemplateModal() {
    const modalHTML = `
        <div id="sizeTemplateModal" class="modal-overlay hidden">
            <div class="modal-content" style="max-width: 800px;">
                <div class="modal-header">
                    <h2 class="text-xl font-bold text-gray-800">📏 Size Templates</h2>
                    <button type="button" onclick="closeSizeTemplateModal()" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="modal-body" style="max-height: 600px; overflow-y: auto;">
                    <!-- Template Categories -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Category:</label>
                        <select id="sizeTemplateCategory" onchange="filterSizeTemplates()" class="w-full px-3 py-2 border border-gray-300 rounded">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                    
                    <!-- Template List -->
                    <div id="sizeTemplatesList" class="space-y-3">
                        <div class="text-center text-gray-500">Loading templates...</div>
                    </div>
                    
                    <!-- Application Options -->
                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h3 class="font-medium text-blue-800 mb-3">Application Options</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Apply Mode:</label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="radio" name="sizeApplyMode" value="general" class="mr-2" checked>
                                        <span class="text-sm">General sizes (not color-specific)</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="sizeApplyMode" value="color_specific" class="mr-2">
                                        <span class="text-sm">Color-specific sizes</span>
                                    </label>
                                </div>
                            </div>
                            <div id="colorSelectionForSizes" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Color:</label>
                                <select id="sizeTemplateColorId" class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                                    <option value="">Loading colors...</option>
                                </select>
                            </div>
                            <label class="flex items-center">
                                <input type="checkbox" id="replaceExistingSizes" class="mr-2">
                                <span class="text-sm">Replace existing sizes</span>
                            </label>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Default Stock Level for New Sizes:</label>
                                <input type="number" id="defaultSizeStock" value="0" min="0" class="w-32 px-3 py-2 border border-gray-300 rounded text-sm">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeSizeTemplateModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="button" onclick="applySelectedSizeTemplate()" id="applySizeTemplateBtn" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700" disabled>
                        Apply Template
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add event listeners for apply mode radio buttons
    document.querySelectorAll('input[name="sizeApplyMode"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const colorSelection = document.getElementById('colorSelectionForSizes');
            if (this.value === 'color_specific') {
                colorSelection.classList.remove('hidden');
                loadColorsForSizeTemplate();
            } else {
                colorSelection.classList.add('hidden');
            }
        });
    });
}

// Load Size Templates
async function loadSizeTemplates() {
    try {
        const response = await fetch('/api/size_templates.php?action=get_all');
        const data = await response.json();
        
        if (data.success) {
            sizeTemplates = data.templates;
            renderSizeTemplates();
            loadSizeTemplateCategories();
        } else {
            showError('Error loading size templates: ' + data.message);
        }
    } catch (error) {
        console.error('Error loading size templates:', error);
        showError('Error loading size templates');
    }
}

// Load Size Template Categories
function loadSizeTemplateCategories() {
    const categorySelect = document.getElementById('sizeTemplateCategory');
    if (!categorySelect) return;
    
    const categories = [...new Set(sizeTemplates.map(t => t.category))].sort();
    
    categorySelect.innerHTML = '<option value="">All Categories</option>';
    categories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categorySelect.appendChild(option);
    });
}

// Filter Size Templates
function filterSizeTemplates() {
    renderSizeTemplates();
}

// Render Size Templates
function renderSizeTemplates() {
    const container = document.getElementById('sizeTemplatesList');
    if (!container) return;
    
    const selectedCategory = document.getElementById('sizeTemplateCategory')?.value || '';
    const filteredTemplates = selectedCategory 
        ? sizeTemplates.filter(t => t.category === selectedCategory)
        : sizeTemplates;
    
    if (filteredTemplates.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500">No templates found</div>';
        return;
    }
    
    container.innerHTML = filteredTemplates.map(template => `
        <div class="template-item border border-gray-200 rounded-lg p-4 hover:border-purple-300 cursor-pointer" 
             onclick="selectSizeTemplate(${template.id})" data-template-id="${template.id}">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <h4 class="font-medium text-gray-800">${template.template_name}</h4>
                    <p class="text-sm text-gray-600">${template.description || 'No description'}</p>
                </div>
                <div class="text-right">
                    <span class="inline-block px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">${template.category}</span>
                    <div class="text-xs text-gray-500 mt-1">${template.size_count} sizes</div>
                </div>
            </div>
            <div class="template-preview" id="sizePreview${template.id}">
                <div class="text-xs text-gray-500">Loading sizes...</div>
            </div>
        </div>
    `).join('');
    
    // Load size previews
    filteredTemplates.forEach(template => {
        loadSizeTemplatePreview(template.id);
    });
}

// Load Size Template Preview
async function loadSizeTemplatePreview(templateId) {
    try {
        const response = await fetch(`/api/size_templates.php?action=get_template&template_id=${templateId}`);
        const data = await response.json();
        
        if (data.success && data.template.sizes) {
            const previewContainer = document.getElementById(`sizePreview${templateId}`);
            if (previewContainer) {
                previewContainer.innerHTML = `
                    <div class="flex flex-wrap gap-2 mt-2">
                        ${data.template.sizes.map(size => `
                            <span class="inline-block px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded">
                                ${size.size_name} (${size.size_code})${size.price_adjustment > 0 ? ' +$' + size.price_adjustment : size.price_adjustment < 0 ? ' $' + size.price_adjustment : ''}
                            </span>
                        `).join('')}
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading size template preview:', error);
    }
}

// Select Size Template
function selectSizeTemplate(templateId) {
    // Remove previous selection
    document.querySelectorAll('.template-item').forEach(item => {
        item.classList.remove('border-purple-500', 'bg-purple-50');
    });
    
    // Add selection to clicked template
    const templateItem = document.querySelector(`[data-template-id="${templateId}"]`);
    if (templateItem) {
        templateItem.classList.add('border-purple-500', 'bg-purple-50');
    }
    
    // Enable apply button
    const applyBtn = document.getElementById('applySizeTemplateBtn');
    if (applyBtn) {
        applyBtn.disabled = false;
        applyBtn.setAttribute('data-template-id', templateId);
    }
}

// Load Colors for Size Template
async function loadColorsForSizeTemplate() {
    if (!currentItemSku) return;
    
    try {
        const response = await fetch(`/api/item_colors.php?action=get_all_colors&item_sku=${currentItemSku}`);
        const data = await response.json();
        
        const colorSelect = document.getElementById('sizeTemplateColorId');
        if (!colorSelect) return;
        
        colorSelect.innerHTML = '<option value="">Select a color...</option>';
        
        if (data.success && data.colors && data.colors.length > 0) {
            data.colors.forEach(color => {
                if (color.is_active == 1) {
                    const option = document.createElement('option');
                    option.value = color.id;
                    option.textContent = color.color_name;
                    colorSelect.appendChild(option);
                }
            });
        } else {
            colorSelect.innerHTML = '<option value="">No colors available - add colors first</option>';
        }
    } catch (error) {
        console.error('Error loading colors for size template:', error);
    }
}

// Apply Selected Size Template
async function applySelectedSizeTemplate() {
    const applyBtn = document.getElementById('applySizeTemplateBtn');
    const templateId = applyBtn?.getAttribute('data-template-id');
    
    if (!templateId) {
        showError('Please select a template first');
        return;
    }
    
    const applyMode = document.querySelector('input[name="sizeApplyMode"]:checked')?.value || 'general';
    const replaceExisting = document.getElementById('replaceExistingSizes')?.checked || false;
    const defaultStock = parseInt(document.getElementById('defaultSizeStock')?.value) || 0;
    
    let colorId = null;
    if (applyMode === 'color_specific') {
        colorId = document.getElementById('sizeTemplateColorId')?.value;
        if (!colorId) {
            showError('Please select a color for color-specific sizes');
            return;
        }
    }
    
    try {
        applyBtn.disabled = true;
        applyBtn.textContent = 'Applying...';
        
        const response = await fetch('/api/size_templates.php?action=apply_to_item', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                template_id: parseInt(templateId),
                item_sku: currentItemSku,
                apply_mode: applyMode,
                color_id: colorId ? parseInt(colorId) : null,
                replace_existing: replaceExisting,
                default_stock: defaultStock
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(`Template applied successfully! Added ${data.sizes_added} sizes.`);
            closeSizeTemplateModal();
            loadItemSizes(); // Reload sizes
        } else {
            showError('Error applying template: ' + data.message);
        }
    } catch (error) {
        console.error('Error applying size template:', error);
        showError('Error applying size template');
    } finally {
        applyBtn.disabled = false;
        applyBtn.textContent = 'Apply Template';
    }
}

// Close Size Template Modal
function closeSizeTemplateModal() {
    const modal = document.getElementById('sizeTemplateModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

</script>

<script src="js/modal-close-positioning.js?v=<?= $cache_buster ?>"></script>

<?php
$output = ob_get_clean();
echo $output;
?>
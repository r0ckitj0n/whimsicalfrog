<?php
// sections/admin_categories.php — Primary implementation for Category Management

// Satisfy legacy guards (harmless if already set)
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// Authentication check - case insensitive
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (!isAdminWithToken()) {
    echo '<div class="text-danger">Access denied.</div>';
    return;
}

try {
    $categories = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
    $categories = array_column($categories, 'category');
} catch (Exception $e) {
    Logger::error('Categories loading failed', ['error' => $e->getMessage()]);
    $categories = [];
}

// Category code generation
function cat_code($cat)
{
    $map = [
        'T-Shirts' => 'TS',
        'Tumblers' => 'TU',
        'Artwork' => 'AR',
        'Sublimation' => 'SU',
        'WindowWraps' => 'WW'
    ];
    return $map[$cat] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $cat), 0, 2));
}

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';
?>

<div class="">
    <div class="admin-header-section">
        <h1 class="admin-title">Category Management</h1>
        <a href="/?page=admin&section=settings" class="btn btn-primary">Back to Settings</a>
    </div>
    
    <?php if ($message): ?>
        <div class="admin-alert <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Add Category Form -->
    <div class="admin-card">
        <h3 class="admin-card-title">Add New Category</h3>
        <form id="addCategoryForm" class="admin-form-inline">
            <input type="text" id="newCategory" name="newCategory" 
                   placeholder="Enter category name..." class="form-input" required>
            <button type="submit" class="btn btn-primary">Add Category</button>
        </form>
    </div>

    <!-- Categories List -->
    <div class="admin-card">
        <?php if (empty($categories)): ?>
            <div class="admin-empty-state">
                <div class="empty-icon">📂</div>
                <div class="empty-title">No categories found</div>
                <div class="empty-subtitle">Add your first category above to get started.</div>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>SKU Code</th>
                        <th>Example SKU</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="categoryTableBody">
                    <?php foreach ($categories as $cat):
                        $code = cat_code($cat);
                        $exampleSku = "WF-{$code}-001";
                        ?>
                        <tr data-category="<?= htmlspecialchars($cat) ?>">
                            <td>
                                <div class="editable-field" data-original="<?= htmlspecialchars($cat) ?>" 
                                     title="Click to edit category name">
                                    <?= htmlspecialchars($cat) ?>
                                </div>
                            </td>
                            <td><span class="code-badge"><?= htmlspecialchars($code) ?></span></td>
                            <td><span class="code-badge"><?= htmlspecialchars($exampleSku) ?></span></td>
                            <td>
                                <button class="text-red-600 hover:text-red-800 delete-category-btn" 
                                        data-category="<?= htmlspecialchars($cat) ?>" title="Delete Category">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- SKU Information -->
    <div class="admin-info-card">
        <h4 class="info-title">📋 SKU Naming Scheme</h4>
        <p class="info-text">Categories automatically generate SKU prefixes: <strong>WF-[CODE]-###</strong></p>
        <p class="info-text">Example: "T-Shirts" → <span class="code-badge">WF-TS-001</span></p>
        <p class="info-meta">The naming scheme updates automatically when you add or remove categories.</p>
    </div>
</div>


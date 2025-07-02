<?php
// Admin Categories Management
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=admin&section=categories');
    exit;
}

// Authentication check - case insensitive
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/auth.php';
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
function cat_code($cat) {
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

<div class="container mx-auto px-4 py-6">
    <div class="admin-header-section">
        <h1 class="admin-title">Category Management</h1>
        <a href="/?page=admin&section=settings" class="btn-primary">Back to Settings</a>
    </div>
    
    <?php if ($message): ?>
        <div class="admin-alert <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Add Category Form -->
    <div class="admin-card mb-6">
        <h3 class="admin-card-title">Add New Category</h3>
        <form id="addCategoryForm" class="admin-form-inline">
            <input type="text" id="newCategory" name="newCategory" 
                   placeholder="Enter category name..." class="form-input flex-grow" required>
            <button type="submit" class="btn-primary">Add Category</button>
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
    <div class="admin-info-card mt-6">
        <h4 class="info-title">📋 SKU Naming Scheme</h4>
        <p class="info-text">Categories automatically generate SKU prefixes: <strong>WF-[CODE]-###</strong></p>
        <p class="info-text">Example: "T-Shirts" → <span class="code-badge">WF-TS-001</span></p>
        <p class="info-meta">The naming scheme updates automatically when you add or remove categories.</p>
    </div>
</div>

<script>
// Category code generation (client-side)
function generateCategoryCode(category) {
    const map = {
        'T-Shirts': 'TS', 'Tumblers': 'TU', 'Artwork': 'AR',
        'Sublimation': 'SU', 'WindowWraps': 'WW'
    };
    return map[category] || category.replace(/[^A-Za-z]/g, '').substring(0, 2).toUpperCase();
}

// Add Category
document.getElementById('addCategoryForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const category = document.getElementById('newCategory').value.trim();
    if (!category) return;
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Adding...';
    submitBtn.disabled = true;
    
    try {
        const res = await fetch('/process_category_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', category })
        });
        const data = await res.json();
        
        if (data.success) {
            const code = data.categoryCode || generateCategoryCode(category);
            const exampleSku = `WF-${code}-001`;
            
            // Add new row
            const tbody = document.getElementById('categoryTableBody');
            const tr = document.createElement('tr');
            tr.dataset.category = category;
            tr.innerHTML = `
                <td>
                    <div class="editable-field" data-original="${category}" title="Click to edit category name">
                        ${category}
                    </div>
                </td>
                <td><span class="code-badge">${code}</span></td>
                <td><span class="code-badge">${exampleSku}</span></td>
                <td>
                    <button class="text-red-600 hover:text-red-800 delete-category-btn" data-category="${category}" title="Delete Category">🗑️</button>
                </td>
            `;
            tbody.appendChild(tr);
            
            document.getElementById('newCategory').value = '';
            showGlobalNotification(`Category "${category}" added successfully! SKU code: ${code}`, 'success');
            
            // Notify other pages
            localStorage.setItem('categoriesUpdated', Date.now().toString());
            if (window.opener?.refreshCategoryDropdown) {
                window.opener.refreshCategoryDropdown();
            }
        } else {
            showGlobalNotification(data.error || 'Failed to add category', 'error');
        }
    } catch(err) {
        Logger.error('Category add failed', err);
        showGlobalNotification('Server error occurred', 'error');
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});

// Delete Category
document.getElementById('categoryTableBody').addEventListener('click', async (e) => {
    if (!e.target.classList.contains('delete-category-btn')) return;
    
    const category = e.target.dataset.category;
    if (!confirm(`Delete category "${category}"?\n\nThis will remove the category from all products and update the SKU naming scheme.\nThis action cannot be undone.`)) return;
    
    const btn = e.target;
    const originalText = btn.textContent;
    btn.textContent = '⏳';
    btn.disabled = true;
    
    try {
        const res = await fetch('/process_category_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', category })
        });
        const data = await res.json();
        
        if (data.success) {
            e.target.closest('tr').remove();
            showGlobalNotification(`Category "${category}" deleted successfully!`, 'success');
            
            localStorage.setItem('categoriesUpdated', Date.now().toString());
            if (window.opener?.refreshCategoryDropdown) {
                window.opener.refreshCategoryDropdown();
            }
        } else {
            showGlobalNotification(data.error || 'Failed to delete category', 'error');
            btn.textContent = originalText;
            btn.disabled = false;
        }
    } catch(err) {
        Logger.error('Category delete failed', err);
        showGlobalNotification('Server error occurred', 'error');
        btn.textContent = originalText;
        btn.disabled = false;
    }
});

// Inline Editing
document.getElementById('categoryTableBody').addEventListener('click', (e) => {
    if (e.target.classList.contains('editable-field') && !e.target.classList.contains('editing')) {
        startInlineEdit(e.target);
    }
});

function startInlineEdit(categoryDiv) {
    const originalName = categoryDiv.dataset.original;
    const currentName = categoryDiv.textContent.trim();
    
    if (document.querySelector('.editing')) return;
    
    categoryDiv.classList.add('editing');
    const input = document.createElement('input');
    input.type = 'text';
    input.value = currentName;
    input.className = 'form-input category-edit-input';
    input.dataset.original = originalName;
    
    categoryDiv.innerHTML = '';
    categoryDiv.appendChild(input);
    input.focus();
    input.select();
    
    const saveEdit = async () => {
        const newName = input.value.trim();
        
        if (!newName) {
            showGlobalNotification('Category name cannot be empty', 'error');
            cancelEdit();
            return;
        }
        
        if (newName === originalName) {
            cancelEdit();
            return;
        }
        
        input.disabled = true;
        input.value = 'Saving...';
        
        try {
            const res = await fetch('/process_category_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'rename', 
                    category: originalName, 
                    newCategory: newName 
                })
            });
            const data = await res.json();
            
            if (data.success) {
                // Update UI
                categoryDiv.classList.remove('editing');
                categoryDiv.innerHTML = newName;
                categoryDiv.dataset.original = newName;
                
                const row = categoryDiv.closest('tr');
                row.dataset.category = newName;
                row.querySelector('.delete-category-btn').dataset.category = newName;
                
                // Update codes
                const newCode = data.newCategoryCode;
                const codeSpans = row.querySelectorAll('.code-badge');
                codeSpans[0].textContent = newCode;
                codeSpans[1].textContent = `WF-${newCode}-001`;
                
                showGlobalNotification(`Category renamed to "${newName}" successfully! ${data.affectedProducts} products updated.`, 'success');
                
                localStorage.setItem('categoriesUpdated', Date.now().toString());
                if (window.opener?.refreshCategoryDropdown) {
                    window.opener.refreshCategoryDropdown();
                }
            } else {
                showGlobalNotification(data.error || 'Failed to rename category', 'error');
                cancelEdit();
            }
        } catch(err) {
            Logger.error('Category rename failed', err);
            showGlobalNotification('Server error occurred', 'error');
            cancelEdit();
        }
    };
    
    const cancelEdit = () => {
        categoryDiv.classList.remove('editing');
        categoryDiv.innerHTML = currentName;
    };
    
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') saveEdit();
        if (e.key === 'Escape') cancelEdit();
    });
    
    input.addEventListener('blur', saveEdit);
}
</script> 
<?php
// Admin Categories Management
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=admin&section=categories');
    exit;
}

// Only Admin allowed
if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'Admin') {
    echo '<div class="text-red-600">Access denied.</div>';
    return;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';

$pdo = new PDO($dsn, $user, $pass, $options);

// Fetch distinct categories used either in products or inventory
$stmt = $pdo->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Function to generate category code (same as in process_category_action.php)
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

<style>
    /* Force the categories title to be green with highest specificity */
    h1.categories-title.text-2xl.font-bold {
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

    .categories-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
    .categories-table th { background-color: #87ac3a; color: white; padding: 10px 12px; text-align: left; font-weight: 600; font-size: 0.8rem; position: sticky; top: 0; z-index: 10; }
    .categories-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 0.85rem; }
    .categories-table tr:hover { background-color: #f7fafc; }
    .categories-table th:first-child { border-top-left-radius: 6px; }
    .categories-table th:last-child { border-top-right-radius: 6px; }

    .action-btn { padding: 5px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; border: none; }
    .delete-btn { background-color: #f56565; color: white; } .delete-btn:hover { background-color: #e53e3e; }

    .category-code {
        background-color: #f3f4f6;
        color: #374151;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: monospace;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .naming-scheme-info {
        background-color: #f0f9ff;
        border: 1px solid #0ea5e9;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 20px;
    }

    .naming-scheme-info h4 {
        color: #0369a1;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .naming-scheme-info p {
        color: #0c4a6e;
        font-size: 0.875rem;
        margin-bottom: 4px;
    }

    .editable-category {
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: background-color 0.2s;
        position: relative;
    }

    .editable-category:hover {
        background-color: #f3f4f6;
    }

    .editable-category.editing {
        background-color: #fef3c7;
        cursor: text;
    }

    .category-edit-input {
        border: 2px solid #f59e0b;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 0.875rem;
        font-weight: 500;
        width: 100%;
        background-color: white;
    }

    .category-edit-input:focus {
        outline: none;
        border-color: #d97706;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
    }

    .edit-hint {
        font-size: 0.75rem;
        color: #6b7280;
        font-style: italic;
        margin-top: 2px;
    }
</style>

<div class="container mx-auto px-4 py-6">
    <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h1 class="categories-title text-2xl font-bold" style="color: #87ac3a !important;">Category Management</h1>
        <a href="/?page=admin&section=settings" class="brand-button px-4 py-2 rounded text-sm">Back to Settings</a>
    </div>
    
    <?php if ($message): ?>
        <div class="mb-4 p-3 rounded text-white <?= $messageType === 'success' ? 'bg-green-500' : 'bg-red-500'; ?>">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Add Category Form -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Add New Category</h3>
        <form id="addCategoryForm" class="flex gap-2">
            <input type="text" id="newCategory" name="newCategory" placeholder="Enter category name..." class="border border-gray-300 rounded p-2 flex-grow" required>
            <button type="submit" class="brand-button px-4 py-2 rounded">Add Category</button>
        </form>
    </div>

    <!-- Categories List -->
    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <?php if (empty($categories)): ?>
            <div class="text-center text-gray-500 py-12">
                <div class="text-4xl mb-4">📂</div>
                <div class="text-lg font-medium mb-2">No categories found</div>
                <div class="text-sm">Add your first category above to get started.</div>
            </div>
        <?php else: ?>
            <table class="categories-table">
                <thead>
                    <tr>
                        <th>Category Name</th><th>SKU Code</th><th>Example SKU</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="categoryTableBody">
                    <?php foreach ($categories as $cat): 
                        $code = cat_code($cat);
                        $exampleSku = "WF-{$code}-001";
                    ?>
                        <tr class="hover:bg-gray-50" data-category="<?= htmlspecialchars($cat); ?>">
                            <td>
                                <div class="editable-category font-medium text-gray-900" data-original="<?= htmlspecialchars($cat); ?>" title="Click to edit category name">
                                    <?= htmlspecialchars($cat); ?>
                                </div>
                            </td>
                            <td><span class="category-code"><?= htmlspecialchars($code); ?></span></td>
                            <td><span class="category-code"><?= htmlspecialchars($exampleSku); ?></span></td>
                            <td>
                                <button class="action-btn delete-btn deleteCategoryBtn" data-category="<?= htmlspecialchars($cat); ?>" title="Delete Category">🗑️</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- SKU Naming Scheme Information (Bottom) -->
    <div class="naming-scheme-info mt-6">
        <h4>📋 SKU Naming Scheme</h4>
        <p>Categories automatically generate SKU prefixes in the format: <strong>WF-[CODE]-###</strong></p>
        <p>Example: "T-Shirts" → <span class="category-code">WF-TS-001</span>, "Custom Category" → <span class="category-code">WF-CU-001</span></p>
        <p><em>The naming scheme updates automatically when you add or remove categories.</em></p>
    </div>
</div>

<script>
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
    }, 4000);
}

// Function to generate category code (same logic as backend)
function generateCategoryCode(category) {
    const map = {
        'T-Shirts': 'TS',
        'Tumblers': 'TU', 
        'Artwork': 'AR',
        'Sublimation': 'SU',
        'WindowWraps': 'WW'
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
            // Generate the category code and example SKU
            const code = data.categoryCode || generateCategoryCode(category);
            const exampleSku = `WF-${code}-001`;
            
            // Add new row to table
            const tbody = document.getElementById('categoryTableBody');
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';
            tr.dataset.category = category;
            tr.innerHTML = `
                <td>
                    <div class="editable-category font-medium text-gray-900" data-original="${category}" title="Click to edit category name">
                        ${category}
                    </div>
                </td>
                <td><span class="category-code">${code}</span></td>
                <td><span class="category-code">${exampleSku}</span></td>
                <td>
                    <button class="action-btn delete-btn deleteCategoryBtn" data-category="${category}" title="Delete Category">🗑️</button>
                </td>
            `;
            tbody.appendChild(tr);
            
            // Clear form
            document.getElementById('newCategory').value = '';
            
            // Show success message
            showToast('success', `Category "${category}" added successfully! SKU code: ${code}`);
            
            // Show naming scheme update info
            if (data.namingSchemeUpdated) {
                console.log('Naming scheme updated:', data.categoryMappings);
            }
            
            // Notify other pages that categories have been updated
            localStorage.setItem('categoriesUpdated', Date.now().toString());
            
            // If inventory page is open in another tab, refresh its categories
            if (window.opener && window.opener.refreshCategoryDropdown) {
                window.opener.refreshCategoryDropdown();
            }
        } else {
            showToast('error', data.error || 'Failed to add category');
        }
    } catch(err) {
        console.error(err);
        showToast('error', 'Server error occurred');
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});

// Delegate delete buttons
document.getElementById('categoryTableBody').addEventListener('click', async (e) => {
    if (e.target.classList.contains('deleteCategoryBtn')) {
        const category = e.target.dataset.category;
        if (!confirm(`Delete category "${category}"?\n\nThis will:\n• Remove the category from all products\n• Update the SKU naming scheme\n• This action cannot be undone`)) return;
        
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
                // Remove row from table
                e.target.closest('tr').remove();
                
                // Show success message
                showToast('success', `Category "${category}" deleted successfully! Naming scheme updated.`);
                
                // Show naming scheme update info
                if (data.namingSchemeUpdated) {
                    console.log('Naming scheme updated:', data.categoryMappings);
                }
                
                // Notify other pages that categories have been updated
                localStorage.setItem('categoriesUpdated', Date.now().toString());
                
                // If inventory page is open in another tab, refresh its categories
                if (window.opener && window.opener.refreshCategoryDropdown) {
                    window.opener.refreshCategoryDropdown();
                }
            } else {
                showToast('error', data.error || 'Failed to delete category');
                btn.textContent = originalText;
                btn.disabled = false;
            }
        } catch(err) {
            console.error(err);
            showToast('error', 'Server error occurred');
            btn.textContent = originalText;
            btn.disabled = false;
        }
    }
});

// Inline editing functionality
document.getElementById('categoryTableBody').addEventListener('click', (e) => {
    if (e.target.classList.contains('editable-category') && !e.target.classList.contains('editing')) {
        startInlineEdit(e.target);
    }
});

function startInlineEdit(categoryDiv) {
    const originalName = categoryDiv.dataset.original;
    const currentName = categoryDiv.textContent.trim();
    
    // Prevent multiple edits
    if (document.querySelector('.editing')) return;
    
    categoryDiv.classList.add('editing');
    
    // Create input field
    const input = document.createElement('input');
    input.type = 'text';
    input.value = currentName;
    input.className = 'category-edit-input';
    input.dataset.original = originalName;
    
    // Replace content with input
    categoryDiv.innerHTML = '';
    categoryDiv.appendChild(input);
    
    // Focus and select text
    input.focus();
    input.select();
    
    // Handle save/cancel
    const saveEdit = async () => {
        const newName = input.value.trim();
        
        if (!newName) {
            showToast('error', 'Category name cannot be empty');
            cancelEdit();
            return;
        }
        
        if (newName === originalName) {
            cancelEdit();
            return;
        }
        
        // Show loading state
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
                // Update the display
                categoryDiv.classList.remove('editing');
                categoryDiv.innerHTML = newName;
                categoryDiv.dataset.original = newName;
                
                // Update the row's data-category attribute
                const row = categoryDiv.closest('tr');
                row.dataset.category = newName;
                
                // Update delete button's data-category
                const deleteBtn = row.querySelector('.deleteCategoryBtn');
                deleteBtn.dataset.category = newName;
                
                // Update SKU code and example
                const newCode = data.newCategoryCode;
                const codeSpan = row.querySelector('.category-code');
                codeSpan.textContent = newCode;
                
                const exampleSpan = row.querySelectorAll('.category-code')[1];
                exampleSpan.textContent = `WF-${newCode}-001`;
                
                // Show success message
                showToast('success', `Category renamed to "${newName}" successfully! ${data.affectedProducts} products updated.`);
                
                // Notify other pages that categories have been updated
                localStorage.setItem('categoriesUpdated', Date.now().toString());
                
                // If inventory page is open in another tab, refresh its categories
                if (window.opener && window.opener.refreshCategoryDropdown) {
                    window.opener.refreshCategoryDropdown();
                }
            } else {
                showToast('error', data.error || 'Failed to rename category');
                cancelEdit();
            }
        } catch(err) {
            console.error(err);
            showToast('error', 'Server error occurred');
            cancelEdit();
        }
    };
    
    const cancelEdit = () => {
        categoryDiv.classList.remove('editing');
        categoryDiv.innerHTML = originalName;
    };
    
    // Event listeners
    input.addEventListener('blur', saveEdit);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveEdit();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelEdit();
        }
    });
}
</script> 
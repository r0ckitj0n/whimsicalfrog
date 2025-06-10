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
$stmt = $pdo->query("SELECT DISTINCT productType AS category FROM products WHERE productType IS NOT NULL UNION SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<div class="bg-white shadow rounded p-4">
    <h2 class="text-lg font-bold mb-4">Manage Categories</h2>

    <!-- Add Category -->
    <form id="addCategoryForm" class="flex gap-2 mb-6">
        <input type="text" id="newCategory" name="newCategory" placeholder="New Category Name" class="border rounded p-2 flex-grow" required>
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Add</button>
    </form>

    <!-- Existing Categories -->
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b">
                <th class="text-left py-2">Category</th>
                <th class="text-right py-2">Actions</th>
            </tr>
        </thead>
        <tbody id="categoryTableBody">
            <?php foreach ($categories as $cat): ?>
                <tr class="border-b" data-category="<?php echo htmlspecialchars($cat); ?>">
                    <td class="py-2"><?php echo htmlspecialchars($cat); ?></td>
                    <td class="py-2 text-right">
                        <button class="deleteCategoryBtn text-red-600 hover:underline" data-category="<?php echo htmlspecialchars($cat); ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// Add Category
document.getElementById('addCategoryForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const category = document.getElementById('newCategory').value.trim();
    if (!category) return;
    try {
        const res = await fetch('/process_category_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', category })
        });
        const data = await res.json();
        if (data.success) {
            // prepend new row
            const tbody = document.getElementById('categoryTableBody');
            const tr = document.createElement('tr');
            tr.className = 'border-b';
            tr.dataset.category = category;
            tr.innerHTML = `<td class="py-2">${category}</td><td class="py-2 text-right"><button class="deleteCategoryBtn text-red-600 hover:underline" data-category="${category}">Delete</button></td>`;
            tbody.prepend(tr);
            document.getElementById('newCategory').value = '';
        } else {
            alert(data.error || 'Failed to add category');
        }
    } catch(err) {
        console.error(err);
        alert('Server error');
    }
});

// Delegate delete buttons
document.getElementById('categoryTableBody').addEventListener('click', async (e) => {
    if (e.target.classList.contains('deleteCategoryBtn')) {
        const category = e.target.dataset.category;
        if (!confirm(`Delete category "${category}"? This will unset the category on all products & inventory items.`)) return;
        try {
            const res = await fetch('/process_category_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', category })
            });
            const data = await res.json();
            if (data.success) {
                // remove row
                e.target.closest('tr').remove();
            } else {
                alert(data.error || 'Failed to delete category');
            }
        } catch(err) {
            console.error(err);
            alert('Server error');
        }
    }
});
</script> 
<?php
// Admin page section

// Check if user is logged in and is an admin
$user = json_decode($_SESSION['user'] ?? '{}', true);
if (!isset($user['role']) || $user['role'] !== 'Admin') {
    header('Location: /login.php');
    exit;
}

$inventory = fetchData('inventory');
$orders = fetchData('sales_orders');
?>
<section id="adminPage" class="p-6 bg-white rounded-lg shadow-lg">
    <div class="flex justify-between items-center mb-8">
        <h2 class="text-4xl font-merienda text-[#556B2F]">Admin Dashboard</h2>
        <div class="flex items-center gap-4">
            <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
            <button onclick="logout()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md">Logout</button>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-gray-100 p-4 rounded-lg shadow">
            <h3 class="text-xl font-semibold text-[#556B2F] mb-2">Orders</h3>
            <ul id="mockOrdersList" class="list-disc list-inside text-gray-700">
                <?php if ($orders): ?>
                    <?php foreach (array_slice($orders, 1) as $order): ?>
                        <li>Order #<?php echo htmlspecialchars($order[0]); ?> - <?php echo htmlspecialchars($order[4]); ?> - <?php echo htmlspecialchars($order[3]); ?></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No orders found</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="bg-gray-100 p-4 rounded-lg shadow">
            <h3 class="text-xl font-semibold text-[#556B2F] mb-2">Inventory Management</h3>
            <?php if ($inventory): ?>
                <?php foreach (array_slice($inventory, 1) as $item): ?>
                    <p class="text-gray-700"><?php echo htmlspecialchars($item[2]); ?> (<?php echo htmlspecialchars($item[4]); ?>): <?php echo htmlspecialchars($item[6]); ?> in stock</p>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-700">No inventory data available</p>
            <?php endif; ?>
            <a href="/?page=admin_inventory" class="mt-2 inline-block bg-[#6B8E23] hover:bg-[#556B2F] text-white font-sm py-1 px-3 rounded-md">Manage Inventory</a>
        </div>
    </div>

    <div class="admin-section">
        <h2>Product Groups</h2>
        <div id="productGroupsList" class="product-groups-list">
            <!-- Product groups will be loaded here -->
        </div>
        <div class="add-group-form">
            <input type="text" id="newGroupName" placeholder="New group name" class="admin-input">
            <button onclick="addProductGroup()" class="admin-button">Add Group</button>
        </div>
    </div>

    <div class="mt-6 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded" role="alert">
        <p class="font-bold">Developer Note:</p>
        <p class="text-sm">Actual data manipulation would require a secure backend API to interact with your Google Sheet or a dedicated database. Do not attempt to modify Google Sheets directly from client-side JavaScript in a production environment due to security risks and API limitations.</p>
    </div>
</section>

<script>
function logout() {
    sessionStorage.removeItem('user');
    window.location.href = '/login.php';
}

// Product Groups Management
async function loadProductGroups() {
    try {
        const response = await fetch('http://localhost:3000/api/product-groups');
        const groups = await response.json();
        const groupsList = document.getElementById('productGroupsList');
        groupsList.innerHTML = groups.map(group => `
            <div class="product-group-item">
                <span class="group-name">${group}</span>
                <div class="group-actions">
                    <button onclick="editProductGroup('${group}')" class="admin-button small">Edit</button>
                    <button onclick="deleteProductGroup('${group}')" class="admin-button small danger">Delete</button>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading product groups:', error);
        alert('Failed to load product groups');
    }
}

async function addProductGroup() {
    const nameInput = document.getElementById('newGroupName');
    const name = nameInput.value.trim();
    
    if (!name) {
        alert('Please enter a group name');
        return;
    }

    try {
        const response = await fetch('http://localhost:3000/api/product-groups', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ name })
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to add group');
        }

        nameInput.value = '';
        await loadProductGroups();
        alert('Product group added successfully');
    } catch (error) {
        console.error('Error adding product group:', error);
        alert(error.message);
    }
}

async function editProductGroup(oldName) {
    const newName = prompt('Enter new name for the group:', oldName);
    if (!newName || newName === oldName) return;

    try {
        const response = await fetch(`http://localhost:3000/api/product-groups/${encodeURIComponent(oldName)}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ newName })
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to update group');
        }

        await loadProductGroups();
        alert('Product group updated successfully');
    } catch (error) {
        console.error('Error updating product group:', error);
        alert(error.message);
    }
}

async function deleteProductGroup(name) {
    if (!confirm(`Are you sure you want to delete the "${name}" group? This will also delete all products in this group.`)) {
        return;
    }

    try {
        const response = await fetch(`http://localhost:3000/api/product-groups/${encodeURIComponent(name)}`, {
            method: 'DELETE'
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to delete group');
        }

        await loadProductGroups();
        alert('Product group deleted successfully');
    } catch (error) {
        console.error('Error deleting product group:', error);
        alert(error.message);
    }
}

// Load product groups when the page loads
document.addEventListener('DOMContentLoaded', () => {
    loadProductGroups();
});
</script>

<style>
    /* ... existing styles ... */

    .product-groups-list {
        margin: 20px 0;
    }

    .product-group-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        margin: 5px 0;
        background: #f5f5f5;
        border-radius: 4px;
    }

    .group-name {
        font-weight: 500;
    }

    .group-actions {
        display: flex;
        gap: 10px;
    }

    .admin-button.small {
        padding: 5px 10px;
        font-size: 0.9em;
    }

    .admin-button.danger {
        background-color: #dc3545;
    }

    .admin-button.danger:hover {
        background-color: #c82333;
    }

    .add-group-form {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .admin-input {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        flex: 1;
    }
</style> 
<?php
// Admin inventory management page
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=admin');
    exit;
}

// Verify admin privileges
if (!isset($isAdmin) || !$isAdmin) {
    echo '<div class="text-center py-12"><h1 class="text-2xl font-bold text-red-600">Access Denied</h1></div>';
    exit;
}
?>

<section id="adminInventoryPage" class="py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-merienda text-[#556B2F]">Inventory Management</h1>
        <a href="/?page=admin" class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
            ← Back to Admin
        </a>
    </div>

    <!-- Add Inventory Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-merienda text-[#556B2F] mb-4">Add New Inventory Item</h2>
        <form id="addInventoryForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label for="itemName" class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                <input type="text" id="itemName" name="itemName" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
            </div>
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select id="category" name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
                    <option value="">Select Category</option>
                    <option value="Material">Material</option>
                    <option value="Labor">Labor</option>
                    <option value="Energy">Energy</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div>
                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input type="number" id="quantity" name="quantity" min="0" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
            </div>
            <div>
                <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
                <input type="text" id="unit" name="unit" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
            </div>
            <div>
                <label for="costPerUnit" class="block text-sm font-medium text-gray-700 mb-1">Cost Per Unit ($)</label>
                <input type="number" id="costPerUnit" name="costPerUnit" min="0" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
            </div>
            <div>
                <label for="totalCost" class="block text-sm font-medium text-gray-700 mb-1">Total Cost ($)</label>
                <input type="number" id="totalCost" name="totalCost" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
            </div>
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]"></textarea>
            </div>
            <div class="md:col-span-2 lg:col-span-3 mt-4">
                <button type="submit" class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                    Add Inventory Item
                </button>
            </div>
        </form>
    </div>

    <!-- Inventory List -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-merienda text-[#556B2F]">Current Inventory</h2>
            <div class="flex gap-2">
                <select id="filterCategory" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
                    <option value="All">All Categories</option>
                    <option value="Material">Material</option>
                    <option value="Labor">Labor</option>
                    <option value="Energy">Energy</option>
                    <option value="Other">Other</option>
                </select>
                <button id="refreshInventoryBtn" class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                    Refresh
                </button>
            </div>
        </div>
        
        <!-- Inventory Totals -->
        <div id="inventoryTotals" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <h3 class="text-lg font-semibold text-green-800">Total Inventory Value</h3>
                <p id="totalInventoryValue" class="text-2xl font-bold text-green-600">$0.00</p>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <h3 class="text-lg font-semibold text-blue-800">Materials</h3>
                <p id="materialsCost" class="text-2xl font-bold text-blue-600">$0.00</p>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                <h3 class="text-lg font-semibold text-purple-800">Labor</h3>
                <p id="laborCost" class="text-2xl font-bold text-purple-600">$0.00</p>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                <h3 class="text-lg font-semibold text-yellow-800">Energy</h3>
                <p id="energyCost" class="text-2xl font-bold text-yellow-600">$0.00</p>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table id="inventoryTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost/Unit</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="inventoryTableBody">
                    <!-- Inventory items will be loaded here -->
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">Loading inventory...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Edit Inventory Modal -->
<div id="editInventoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-2xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-merienda text-[#556B2F]">Edit Inventory Item</h3>
            <button id="closeEditModal" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="editInventoryForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" id="editItemId" name="itemId">
            <div>
                <label for="editItemName" class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                <input type="text" id="editItemName" name="itemName" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
            </div>
            <div>
                <label for="editCategory" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select id="editCategory" name="category" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
                    <option value="Material">Material</option>
                    <option value="Labor">Labor</option>
                    <option value="Energy">Energy</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div>
                <label for="editQuantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input type="number" id="editQuantity" name="quantity" min="0" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
            </div>
            <div>
                <label for="editUnit" class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
                <input type="text" id="editUnit" name="unit" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
            </div>
            <div>
                <label for="editCostPerUnit" class="block text-sm font-medium text-gray-700 mb-1">Cost Per Unit ($)</label>
                <input type="number" id="editCostPerUnit" name="costPerUnit" min="0" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]">
            </div>
            <div>
                <label for="editTotalCost" class="block text-sm font-medium text-gray-700 mb-1">Total Cost ($)</label>
                <input type="number" id="editTotalCost" name="totalCost" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
            </div>
            <div class="md:col-span-2">
                <label for="editNotes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="editNotes" name="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#6B8E23] focus:border-[#6B8E23]"></textarea>
            </div>
            <div class="md:col-span-2 mt-4 flex justify-between">
                <button type="submit" class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                    Update Item
                </button>
                <button type="button" id="deleteInventoryBtn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition-colors">
                    Delete Item
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const addInventoryForm = document.getElementById('addInventoryForm');
    const editInventoryForm = document.getElementById('editInventoryForm');
    const editInventoryModal = document.getElementById('editInventoryModal');
    const closeEditModal = document.getElementById('closeEditModal');
    const deleteInventoryBtn = document.getElementById('deleteInventoryBtn');
    const refreshInventoryBtn = document.getElementById('refreshInventoryBtn');
    const filterCategory = document.getElementById('filterCategory');
    
    // Calculate total cost when quantity or cost per unit changes
    function calculateTotalCost(quantityInput, costPerUnitInput, totalCostInput) {
        const quantity = parseFloat(quantityInput.value) || 0;
        const costPerUnit = parseFloat(costPerUnitInput.value) || 0;
        const totalCost = (quantity * costPerUnit).toFixed(2);
        totalCostInput.value = totalCost;
    }
    
    // Add event listeners for calculation on add form
    const quantityInput = document.getElementById('quantity');
    const costPerUnitInput = document.getElementById('costPerUnit');
    const totalCostInput = document.getElementById('totalCost');
    
    quantityInput.addEventListener('input', () => calculateTotalCost(quantityInput, costPerUnitInput, totalCostInput));
    costPerUnitInput.addEventListener('input', () => calculateTotalCost(quantityInput, costPerUnitInput, totalCostInput));
    
    // Add event listeners for calculation on edit form
    const editQuantityInput = document.getElementById('editQuantity');
    const editCostPerUnitInput = document.getElementById('editCostPerUnit');
    const editTotalCostInput = document.getElementById('editTotalCost');
    
    editQuantityInput.addEventListener('input', () => calculateTotalCost(editQuantityInput, editCostPerUnitInput, editTotalCostInput));
    editCostPerUnitInput.addEventListener('input', () => calculateTotalCost(editQuantityInput, editCostPerUnitInput, editTotalCostInput));
    
    // Load inventory data
    function loadInventory() {
        const category = filterCategory.value;
        const tableBody = document.getElementById('inventoryTableBody');
        tableBody.innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">Loading inventory...</td></tr>';
        
        // Use direct SQL query through PHP file instead of API endpoint
        fetch('/process_inventory_get.php' + (category !== 'All' ? '?category=' + category : ''))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                updateInventoryTable(data);
                updateInventoryTotals(data);
            })
            .catch(error => {
                console.error('Error loading inventory:', error);
                tableBody.innerHTML = `<tr><td colspan="8" class="px-6 py-4 text-center text-red-500">Error loading inventory: ${error.message}</td></tr>`;
            });
    }
    
    // Update inventory table with data
    function updateInventoryTable(inventoryData) {
        const tableBody = document.getElementById('inventoryTableBody');
        tableBody.innerHTML = '';
        
        if (!inventoryData || inventoryData.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">No inventory items found</td></tr>';
            return;
        }
        
        inventoryData.forEach(item => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            
            // Map database column names to display names
            const name = item.name || '';
            const category = item.category || '';
            const quantity = item.stockLevel || 0;
            const sku = item.sku || '';
            const costPerUnit = 0; // Not in database, placeholder
            const totalCost = 0; // Not in database, placeholder
            const notes = item.description || '';
            
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap">${name}</td>
                <td class="px-6 py-4 whitespace-nowrap">${category}</td>
                <td class="px-6 py-4 whitespace-nowrap">${quantity}</td>
                <td class="px-6 py-4 whitespace-nowrap">${sku}</td>
                <td class="px-6 py-4 whitespace-nowrap">$${parseFloat(costPerUnit).toFixed(2)}</td>
                <td class="px-6 py-4 whitespace-nowrap">$${parseFloat(totalCost).toFixed(2)}</td>
                <td class="px-6 py-4 whitespace-nowrap">${notes || ''}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <button class="edit-btn text-blue-600 hover:text-blue-800" data-id="${item.id}">
                        Edit
                    </button>
                </td>
            `;
            
            tableBody.appendChild(row);
            
            // Add event listener to edit button
            const editBtn = row.querySelector('.edit-btn');
            editBtn.addEventListener('click', () => openEditModal(item));
        });
    }
    
    // Update inventory totals
    function updateInventoryTotals(inventoryData) {
        let totalValue = 0;
        let materialsCost = 0;
        let laborCost = 0;
        let energyCost = 0;
        
        inventoryData.forEach(item => {
            // Since we don't have totalCost in the database, we'll use a placeholder value
            const itemCost = 0; // Placeholder
            totalValue += itemCost;
            
            if (item.category === 'Material') {
                materialsCost += itemCost;
            } else if (item.category === 'Labor') {
                laborCost += itemCost;
            } else if (item.category === 'Energy') {
                energyCost += itemCost;
            }
        });
        
        document.getElementById('totalInventoryValue').textContent = `$${totalValue.toFixed(2)}`;
        document.getElementById('materialsCost').textContent = `$${materialsCost.toFixed(2)}`;
        document.getElementById('laborCost').textContent = `$${laborCost.toFixed(2)}`;
        document.getElementById('energyCost').textContent = `$${energyCost.toFixed(2)}`;
    }
    
    // Open edit modal with item data
    function openEditModal(item) {
        document.getElementById('editItemId').value = item.id;
        document.getElementById('editItemName').value = item.name || '';
        document.getElementById('editCategory').value = item.category || 'Other';
        document.getElementById('editQuantity').value = item.stockLevel || 0;
        document.getElementById('editUnit').value = item.sku || '';
        document.getElementById('editCostPerUnit').value = 0; // Placeholder
        document.getElementById('editNotes').value = item.description || '';
        
        // Calculate total cost
        calculateTotalCost(editQuantityInput, editCostPerUnitInput, editTotalCostInput);
        
        // Show modal
        editInventoryModal.classList.remove('hidden');
    }
    
    // Close edit modal
    function closeEditModal() {
        editInventoryModal.classList.add('hidden');
    }
    
    // Add inventory item
    addInventoryForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            itemName: document.getElementById('itemName').value,
            category: document.getElementById('category').value,
            quantity: parseFloat(document.getElementById('quantity').value),
            unit: document.getElementById('unit').value,
            costPerUnit: parseFloat(document.getElementById('costPerUnit').value),
            totalCost: parseFloat(document.getElementById('totalCost').value),
            notes: document.getElementById('notes').value
        };
        
        // Use direct SQL query through PHP file instead of API endpoint
        fetch('/process_inventory_add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Show success message
            const customAlert = document.getElementById('customAlertBox');
            const customAlertMessage = document.getElementById('customAlertMessage');
            customAlertMessage.textContent = 'Inventory item added successfully!';
            customAlertMessage.className = 'text-green-700';
            customAlert.style.display = 'block';
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                customAlert.style.display = 'none';
            }, 3000);
            
            // Reset form and reload inventory
            addInventoryForm.reset();
            loadInventory();
        })
        .catch(error => {
            console.error('Error adding inventory item:', error);
            
            // Show error message
            const customAlert = document.getElementById('customAlertBox');
            const customAlertMessage = document.getElementById('customAlertMessage');
            customAlertMessage.textContent = 'Error adding inventory item: ' + error.message;
            customAlertMessage.className = 'text-red-700';
            customAlert.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                customAlert.style.display = 'none';
            }, 5000);
        });
    });
    
    // Update inventory item
    editInventoryForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            id: document.getElementById('editItemId').value,
            name: document.getElementById('editItemName').value,
            category: document.getElementById('editCategory').value,
            stockLevel: parseFloat(document.getElementById('editQuantity').value),
            sku: document.getElementById('editUnit').value,
            description: document.getElementById('editNotes').value
        };
        
        // Use direct SQL query through PHP file instead of API endpoint
        fetch('/process_inventory_update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Show success message
            const customAlert = document.getElementById('customAlertBox');
            const customAlertMessage = document.getElementById('customAlertMessage');
            customAlertMessage.textContent = 'Inventory item updated successfully!';
            customAlertMessage.className = 'text-green-700';
            customAlert.style.display = 'block';
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                customAlert.style.display = 'none';
            }, 3000);
            
            // Close modal and reload inventory
            closeEditModal();
            loadInventory();
        })
        .catch(error => {
            console.error('Error updating inventory item:', error);
            
            // Show error message
            const customAlert = document.getElementById('customAlertBox');
            const customAlertMessage = document.getElementById('customAlertMessage');
            customAlertMessage.textContent = 'Error updating inventory item: ' + error.message;
            customAlertMessage.className = 'text-red-700';
            customAlert.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                customAlert.style.display = 'none';
            }, 5000);
        });
    });
    
    // Delete inventory item
    deleteInventoryBtn.addEventListener('click', function() {
        const itemId = document.getElementById('editItemId').value;
        
        if (confirm('Are you sure you want to delete this inventory item?')) {
            // Use direct SQL query through PHP file instead of API endpoint
            fetch('/process_inventory_delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: itemId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Show success message
                const customAlert = document.getElementById('customAlertBox');
                const customAlertMessage = document.getElementById('customAlertMessage');
                customAlertMessage.textContent = 'Inventory item deleted successfully!';
                customAlertMessage.className = 'text-green-700';
                customAlert.style.display = 'block';
                
                // Auto-hide after 3 seconds
                setTimeout(() => {
                    customAlert.style.display = 'none';
                }, 3000);
                
                // Close modal and reload inventory
                closeEditModal();
                loadInventory();
            })
            .catch(error => {
                console.error('Error deleting inventory item:', error);
                
                // Show error message
                const customAlert = document.getElementById('customAlertBox');
                const customAlertMessage = document.getElementById('customAlertMessage');
                customAlertMessage.textContent = 'Error deleting inventory item: ' + error.message;
                customAlertMessage.className = 'text-red-700';
                customAlert.style.display = 'block';
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    customAlert.style.display = 'none';
                }, 5000);
            });
        }
    });
    
    // Event listeners
    closeEditModal.addEventListener('click', closeEditModal);
    refreshInventoryBtn.addEventListener('click', loadInventory);
    filterCategory.addEventListener('change', loadInventory);
    
    // Initial load
    loadInventory();
});
</script>

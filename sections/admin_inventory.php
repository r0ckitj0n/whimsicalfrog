<div class="admin-section-header" style="display: none;">
    <h2>Inventory Management</h2>
    <a href="/?page=admin" class="back-button">← Back to Admin</a>
</div>

<div class="admin-content">
    <div class="inventory-controls">
        <div class="search-filter-container">
            <input type="text" id="inventorySearch" placeholder="Search inventory..." class="search-input">
            <select id="categoryFilter" class="filter-select">
                <option value="">All Categories</option>
                <!-- Categories will be populated dynamically -->
            </select>
            <button id="refreshInventory" class="action-button refresh-button">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
        <button id="addInventoryBtn" class="action-button add-button">
            <i class="fas fa-plus"></i> Add New Item
        </button>
    </div>

    <div class="inventory-stats">
        <div class="stat-card">
            <h3>Total Items</h3>
            <p id="totalItems">0</p>
        </div>
        <div class="stat-card">
            <h3>Low Stock</h3>
            <p id="lowStockCount">0</p>
        </div>
        <div class="stat-card">
            <h3>Categories</h3>
            <p id="categoryCount">0</p>
        </div>
        <div class="stat-card">
            <h3>Total Value</h3>
            <p id="totalValue">$0.00</p>
        </div>
    </div>

    <div class="inventory-table-container">
        <table id="inventoryTable" class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>SKU</th>
                    <th>Stock</th>
                    <th>Reorder Point</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Inventory items will be loaded here -->
            </tbody>
        </table>
        <div id="noInventory" class="no-data-message" style="display: none;">
            No inventory items found. Add some items to get started!
        </div>
        <div id="loadingInventory" class="loading-message">
            <i class="fas fa-spinner fa-spin"></i> Loading inventory...
        </div>
    </div>
</div>

<!-- Add/Edit Inventory Modal -->
<div id="inventoryModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2 id="modalTitle">Add New Inventory Item</h2>
        <form id="inventoryForm">
            <input type="hidden" id="inventoryId">
            
            <div class="form-group">
                <label for="productId">Product ID:</label>
                <input type="text" id="productId" required>
            </div>
            
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" required>
            </div>
            
            <div class="form-group">
                <label for="category">Category:</label>
                <input type="text" id="category" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description"></textarea>
            </div>
            
            <div class="form-group">
                <label for="sku">SKU:</label>
                <input type="text" id="sku" required>
            </div>
            
            <div class="form-row">
                <div class="form-group half">
                    <label for="stockLevel">Stock Level:</label>
                    <input type="number" id="stockLevel" min="0" required>
                </div>
                
                <div class="form-group half">
                    <label for="reorderPoint">Reorder Point:</label>
                    <input type="number" id="reorderPoint" min="0" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="imageUrl">Image URL:</label>
                <input type="text" id="imageUrl">
            </div>
            
            <div class="form-actions">
                <button type="button" id="cancelInventory" class="button secondary">Cancel</button>
                <button type="submit" id="saveInventory" class="button primary">Save Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content delete-modal">
        <h2>Confirm Deletion</h2>
        <p>Are you sure you want to delete this inventory item? This action cannot be undone.</p>
        <div class="form-actions">
            <button id="cancelDelete" class="button secondary">Cancel</button>
            <button id="confirmDelete" class="button danger">Delete</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DOM elements
    const inventoryTable = document.getElementById('inventoryTable');
    const inventoryTableBody = inventoryTable.querySelector('tbody');
    const noInventoryMessage = document.getElementById('noInventory');
    const loadingMessage = document.getElementById('loadingInventory');
    const totalItemsElement = document.getElementById('totalItems');
    const lowStockCountElement = document.getElementById('lowStockCount');
    const categoryCountElement = document.getElementById('categoryCount');
    const totalValueElement = document.getElementById('totalValue');
    const categoryFilter = document.getElementById('categoryFilter');
    const searchInput = document.getElementById('inventorySearch');
    
    // Modal elements
    const inventoryModal = document.getElementById('inventoryModal');
    const modalTitle = document.getElementById('modalTitle');
    const inventoryForm = document.getElementById('inventoryForm');
    const inventoryIdInput = document.getElementById('inventoryId');
    const productIdInput = document.getElementById('productId');
    const nameInput = document.getElementById('name');
    const categoryInput = document.getElementById('category');
    const descriptionInput = document.getElementById('description');
    const skuInput = document.getElementById('sku');
    const stockLevelInput = document.getElementById('stockLevel');
    const reorderPointInput = document.getElementById('reorderPoint');
    const imageUrlInput = document.getElementById('imageUrl');
    
    // Delete modal elements
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteButton = document.getElementById('confirmDelete');
    let itemToDelete = null;
    
    // Buttons
    const addInventoryBtn = document.getElementById('addInventoryBtn');
    const refreshInventoryBtn = document.getElementById('refreshInventory');
    const cancelInventoryBtn = document.getElementById('cancelInventory');
    const cancelDeleteBtn = document.getElementById('cancelDelete');
    
    // Close modal when clicking the close button or outside the modal
    document.querySelectorAll('.close-button, #cancelInventory').forEach(element => {
        element.addEventListener('click', closeInventoryModal);
    });
    
    document.getElementById('cancelDelete').addEventListener('click', function() {
        deleteModal.style.display = 'none';
    });
    
    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target === inventoryModal) {
            closeInventoryModal();
        }
        if (event.target === deleteModal) {
            deleteModal.style.display = 'none';
        }
    });
    
    // Add new inventory item
    addInventoryBtn.addEventListener('click', function() {
        resetForm();
        modalTitle.textContent = 'Add New Inventory Item';
        inventoryModal.style.display = 'block';
    });
    
    // Refresh inventory
    refreshInventoryBtn.addEventListener('click', loadInventory);
    
    // Form submission
    inventoryForm.addEventListener('submit', function(e) {
        e.preventDefault();
        saveInventoryItem();
    });
    
    // Load inventory on page load
    loadInventory();
    
    // Load inventory function
    function loadInventory() {
        showLoading(true);
        
        // Get filter values
        const searchTerm = searchInput.value.trim();
        const categoryValue = categoryFilter.value;
        
        // Build query parameters
        let queryParams = new URLSearchParams();
        if (searchTerm) queryParams.append('search', searchTerm);
        if (categoryValue) queryParams.append('category', categoryValue);
        
        // Make API call to get inventory - Using the correct API path in /api/ directory
        fetch('/api/inventory.php?' + queryParams.toString())
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                displayInventory(data);
                updateStats(data);
                populateCategories(data);
            })
            .catch(error => {
                console.error('Error fetching inventory:', error);
                showError('Failed to load inventory. Please try again.');
            })
            .finally(() => {
                showLoading(false);
            });
    }
    
    // Display inventory data
    function displayInventory(data) {
        // Clear existing rows
        inventoryTableBody.innerHTML = '';
        
        if (data.length === 0) {
            noInventoryMessage.style.display = 'block';
            inventoryTable.style.display = 'none';
            return;
        }
        
        noInventoryMessage.style.display = 'none';
        inventoryTable.style.display = 'table';
        
        // Add rows for each inventory item
        data.forEach(item => {
            const row = document.createElement('tr');
            
            // Highlight low stock items
            if (item.stockLevel <= item.reorderPoint) {
                row.classList.add('low-stock');
            }
            
            row.innerHTML = `
                <td>${item.id}</td>
                <td>${item.productId}</td>
                <td>${item.name}</td>
                <td>${item.category}</td>
                <td>${item.sku}</td>
                <td>${item.stockLevel}</td>
                <td>${item.reorderPoint}</td>
                <td class="actions">
                    <button class="edit-button" data-id="${item.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="delete-button" data-id="${item.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            inventoryTableBody.appendChild(row);
        });
        
        // Add event listeners to edit and delete buttons
        document.querySelectorAll('.edit-button').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                editInventoryItem(id, data);
            });
        });
        
        document.querySelectorAll('.delete-button').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                showDeleteConfirmation(id);
            });
        });
    }
    
    // Update inventory statistics
    function updateStats(data) {
        // Total items
        totalItemsElement.textContent = data.length;
        
        // Low stock count
        const lowStockCount = data.filter(item => item.stockLevel <= item.reorderPoint).length;
        lowStockCountElement.textContent = lowStockCount;
        
        // Category count
        const categories = new Set(data.map(item => item.category));
        categoryCountElement.textContent = categories.size;
        
        // Calculate total value (assuming we have price data)
        // This is a placeholder - you may need to adjust based on your actual data structure
        totalValueElement.textContent = '$0.00'; // Placeholder
    }
    
    // Populate category filter
    function populateCategories(data) {
        // Get unique categories
        const categories = [...new Set(data.map(item => item.category))];
        
        // Save current selection
        const currentSelection = categoryFilter.value;
        
        // Clear existing options (except the first one)
        while (categoryFilter.options.length > 1) {
            categoryFilter.remove(1);
        }
        
        // Add category options
        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            categoryFilter.appendChild(option);
        });
        
        // Restore selection if possible
        if (currentSelection && categories.includes(currentSelection)) {
            categoryFilter.value = currentSelection;
        }
    }
    
    // Edit inventory item
    function editInventoryItem(id, data) {
        const item = data.find(item => item.id === id);
        if (!item) return;
        
        // Populate form
        inventoryIdInput.value = item.id;
        productIdInput.value = item.productId;
        nameInput.value = item.name;
        categoryInput.value = item.category;
        descriptionInput.value = item.description || '';
        skuInput.value = item.sku;
        stockLevelInput.value = item.stockLevel;
        reorderPointInput.value = item.reorderPoint;
        imageUrlInput.value = item.imageUrl || '';
        
        // Update modal title and show
        modalTitle.textContent = 'Edit Inventory Item';
        inventoryModal.style.display = 'block';
    }
    
    // Show delete confirmation
    function showDeleteConfirmation(id) {
        itemToDelete = id;
        deleteModal.style.display = 'block';
        
        // Set up confirm delete button
        confirmDeleteButton.onclick = function() {
            deleteInventoryItem(itemToDelete);
            deleteModal.style.display = 'none';
        };
    }
    
    // Save inventory item (create or update)
    function saveInventoryItem() {
        const formData = {
            id: inventoryIdInput.value,
            productId: productIdInput.value,
            name: nameInput.value,
            category: categoryInput.value,
            description: descriptionInput.value,
            sku: skuInput.value,
            stockLevel: parseInt(stockLevelInput.value),
            reorderPoint: parseInt(reorderPointInput.value),
            imageUrl: imageUrlInput.value
        };
        
        const isNewItem = !formData.id;
        const url = isNewItem ? '/api/add-inventory.php' : '/api/update-inventory.php';
        const method = isNewItem ? 'POST' : 'PUT';
        
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
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
            if (data.success) {
                showSuccess(isNewItem ? 'Inventory item added successfully!' : 'Inventory item updated successfully!');
                closeInventoryModal();
                loadInventory(); // Reload inventory
            } else {
                showError(data.message || 'Failed to save inventory item.');
            }
        })
        .catch(error => {
            console.error('Error saving inventory item:', error);
            showError('Failed to save inventory item. Please try again.');
        });
    }
    
    // Delete inventory item
    function deleteInventoryItem(id) {
        fetch('/api/delete-inventory.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showSuccess('Inventory item deleted successfully!');
                loadInventory(); // Reload inventory
            } else {
                showError(data.message || 'Failed to delete inventory item.');
            }
        })
        .catch(error => {
            console.error('Error deleting inventory item:', error);
            showError('Failed to delete inventory item. Please try again.');
        });
    }
    
    // Reset form fields
    function resetForm() {
        inventoryForm.reset();
        inventoryIdInput.value = '';
    }
    
    // Close inventory modal
    function closeInventoryModal() {
        inventoryModal.style.display = 'none';
        resetForm();
    }
    
    // Show/hide loading message
    function showLoading(show) {
        loadingMessage.style.display = show ? 'block' : 'none';
        if (show) {
            noInventoryMessage.style.display = 'none';
        }
    }
    
    // Show success message
    function showSuccess(message) {
        // Implement your success notification here
        alert(message); // Placeholder - replace with your notification system
    }
    
    // Show error message
    function showError(message) {
        // Implement your error notification here
        alert(message); // Placeholder - replace with your notification system
    }
    
    // Filter functionality
    searchInput.addEventListener('input', debounce(loadInventory, 300));
    categoryFilter.addEventListener('change', loadInventory);
    
    // Debounce function to limit API calls
    function debounce(func, delay) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), delay);
        };
    }
});
</script>

<style>
/* Admin inventory page specific styles */
.admin-content {
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Inventory controls */
.inventory-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.search-filter-container {
    display: flex;
    gap: 10px;
    flex: 1;
    flex-wrap: wrap;
}

.search-input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    flex: 1;
    min-width: 200px;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: white;
}

.action-button {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.refresh-button {
    background-color: #f0f0f0;
    color: #333;
}

.refresh-button:hover {
    background-color: #e0e0e0;
}

.add-button {
    background-color: #87ac3a;
    color: white;
}

.add-button:hover {
    background-color: #76953a;
}

/* Inventory stats */
.inventory-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-card h3 {
    margin: 0;
    font-size: 1rem;
    color: #666;
    margin-bottom: 10px;
}

.stat-card p {
    margin: 0;
    font-size: 1.8rem;
    font-weight: bold;
    color: #87ac3a;
}

/* Inventory table */
.inventory-table-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 20px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.data-table th {
    background-color: #87ac3a;
    color: white;
    font-weight: 500;
    position: sticky;
    top: 0;
}

.data-table tbody tr:hover {
    background-color: #f5f5f5;
}

.data-table .actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.edit-button,
.delete-button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.edit-button {
    color: #4a90e2;
}

.edit-button:hover {
    background-color: rgba(74, 144, 226, 0.1);
}

.delete-button {
    color: #e53935;
}

.delete-button:hover {
    background-color: rgba(229, 57, 53, 0.1);
}

.low-stock {
    background-color: #fff8e1;
}

.low-stock td {
    color: #ff8f00;
}

/* Loading and no data messages */
.loading-message,
.no-data-message {
    padding: 20px;
    text-align: center;
    color: #666;
}

.loading-message i {
    margin-right: 10px;
    color: #87ac3a;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    padding: 20px;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    padding: 30px;
    width: 100%;
    max-width: 600px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
}

.close-button {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    transition: color 0.2s ease;
}

.close-button:hover {
    color: #333;
}

.delete-modal {
    max-width: 400px;
    text-align: center;
}

/* Form styles */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #555;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-row {
    display: flex;
    gap: 20px;
}

.form-group.half {
    flex: 1;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

/* Button styles */
.button {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.button.primary {
    background-color: #87ac3a;
    color: white;
}

.button.primary:hover {
    background-color: #76953a;
}

.button.secondary {
    background-color: #f0f0f0;
    color: #333;
}

.button.secondary:hover {
    background-color: #e0e0e0;
}

.button.danger {
    background-color: #e53935;
    color: white;
}

.button.danger:hover {
    background-color: #c62828;
}

/* Responsive styles */
@media (max-width: 768px) {
    .inventory-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .data-table {
        display: block;
        overflow-x: auto;
    }
    
    .search-filter-container {
        flex-direction: column;
        width: 100%;
    }
    
    .inventory-controls {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .action-button {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .inventory-stats {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        padding: 20px;
    }
}
</style>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 text-green-700">Inventory Management</h1>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Total Items</h3>
            <p id="totalItems" class="text-2xl font-bold text-green-700">0</p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Low Stock</h3>
            <p id="lowStockCount" class="text-2xl font-bold text-orange-500">0</p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Categories</h3>
            <p id="categoryCount" class="text-2xl font-bold text-blue-500">0</p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Total Cost Value</h3>
            <p id="totalCostValue" class="text-2xl font-bold text-purple-700">$0.00</p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Total Retail Value</h3>
            <p id="totalRetailValue" class="text-2xl font-bold text-indigo-700">$0.00</p>
        </div>
    </div>
    
    <!-- Admin Navigation Tabs -->
    <style>
        .admin-nav {
            display: flex;
            overflow-x: auto;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .admin-nav a {
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 600;
            color: #4a5568;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }
        .admin-nav a:hover {
            color: #2d3748;
            border-bottom-color: #cbd5e0;
        }
        .admin-nav a.active {
            color: #87ac3a;
            border-bottom-color: #87ac3a;
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 4px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .toast-notification.success {
            background-color: #48bb78;
        }
        .toast-notification.error {
            background-color: #f56565;
        }
        .toast-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Editable Table Cells */
        .editable {
            position: relative;
            cursor: pointer;
            padding: 8px;
            transition: all 0.2s;
        }
        .editable:hover {
            background-color: rgba(135, 172, 58, 0.1);
        }
        .editable:hover::after {
            content: '✏️';
            font-size: 12px;
            position: absolute;
            top: 2px;
            right: 2px;
            opacity: 0.5;
        }
        .editable.editing {
            padding: 0;
            border: 2px solid #87ac3a;
        }
        .editable.editing:hover::after {
            content: '';
        }
        .editable input {
            width: 100%;
            padding: 8px;
            border: none;
            background: white;
            outline: none;
        }
        .editable.saving {
            background-color: rgba(255, 193, 7, 0.2);
        }
        .editable.success {
            background-color: rgba(72, 187, 120, 0.2);
        }
        .editable.error {
            background-color: rgba(245, 101, 101, 0.2);
        }
        
        /* Enhanced Inventory Table */
        .inventory-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 20px;
        }
        .inventory-table th {
            background-color: #87ac3a;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .inventory-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .inventory-table tr:hover {
            background-color: #f7fafc;
        }
        .inventory-table th:first-child {
            border-top-left-radius: 8px;
        }
        .inventory-table th:last-child {
            border-top-right-radius: 8px;
        }
        
        /* Action Buttons */
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 16px;
        }
        .edit-btn {
            background-color: #4299e1;
            color: white;
        }
        .edit-btn:hover {
            background-color: #3182ce;
        }
        .delete-btn {
            background-color: #f56565;
            color: white;
        }
        .delete-btn:hover {
            background-color: #e53e3e;
        }
        
        /* Cost Breakdown Styles */
        .cost-breakdown {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            border: 1px solid #e2e8f0;
        }
        .cost-breakdown h3 {
            color: #4a5568;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .cost-breakdown-section {
            margin-bottom: 16px;
        }
        .cost-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .cost-item:last-child {
            border-bottom: none;
        }
        .cost-item-name {
            font-weight: 500;
        }
        .cost-item-value {
            font-weight: 600;
            color: #4a5568;
        }
        .cost-totals {
            background-color: #edf2f7;
            padding: 12px;
            border-radius: 6px;
            margin-top: 12px;
        }
        .cost-total-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
        .suggested-cost {
            color: #805ad5;
            font-weight: 600;
            margin-left: 8px;
        }
        .cost-label {
            font-size: 14px;
            color: #718096;
        }
    </style>
    
    <!-- Search and Filter Controls -->
    <div class="flex flex-col md:flex-row gap-4 mb-6">
        <div class="flex-1">
            <input type="text" id="searchInput" placeholder="Search inventory..." class="w-full p-2 border border-gray-300 rounded">
        </div>
        <div class="flex-1">
            <select id="categoryFilter" class="w-full p-2 border border-gray-300 rounded">
                <option value="">All Categories</option>
            </select>
        </div>
        <div class="flex-1">
            <select id="stockFilter" class="w-full p-2 border border-gray-300 rounded">
                <option value="">All Stock Levels</option>
                <option value="low">Low Stock</option>
                <option value="out">Out of Stock</option>
                <option value="in">In Stock</option>
            </select>
        </div>
        <div>
            <button id="addItemBtn" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded">Add New Item</button>
        </div>
    </div>
    
    <!-- Inventory Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table id="inventoryTable" class="inventory-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>SKU</th>
                    <th>Stock</th>
                    <th>Reorder Point</th>
                    <th>Cost Price</th>
                    <th>Retail Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="inventoryTableBody">
                <tr>
                    <td colspan="8" class="text-center py-4">Loading inventory data...</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Toast Notification -->
    <div id="toastNotification" class="toast-notification">
        <span id="toastMessage"></span>
    </div>
    
    <!-- Add/Edit Item Modal -->
    <div id="itemModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 id="modalTitle" class="text-xl font-bold text-green-700">Add New Item</h2>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            
            <form id="itemForm" class="space-y-4">
                <input type="hidden" id="itemId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="productId" class="block text-sm font-medium text-gray-700">Product ID</label>
                        <input type="text" id="productId" class="mt-1 block w-full p-2 border border-gray-300 rounded">
                    </div>
                    
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" id="name" class="mt-1 block w-full p-2 border border-gray-300 rounded" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                        <input type="text" id="category" class="mt-1 block w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    
                    <div>
                        <label for="sku" class="block text-sm font-medium text-gray-700">SKU</label>
                        <input type="text" id="sku" class="mt-1 block w-full p-2 border border-gray-300 rounded" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="stockLevel" class="block text-sm font-medium text-gray-700">Stock Level</label>
                        <input type="number" id="stockLevel" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" required>
                    </div>
                    
                    <div>
                        <label for="reorderPoint" class="block text-sm font-medium text-gray-700">Reorder Point</label>
                        <input type="number" id="reorderPoint" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="costPrice" class="block text-sm font-medium text-gray-700">Cost Price ($)</label>
                        <div class="flex items-center">
                            <input type="number" id="costPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" step="0.01" required>
                            <span id="suggestedCostLabel" class="suggested-cost ml-2 hidden">(Suggested: $0.00)</span>
                        </div>
                    </div>
                    
                    <div>
                        <label for="retailPrice" class="block text-sm font-medium text-gray-700">Retail Price ($)</label>
                        <input type="number" id="retailPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" step="0.01" required>
                    </div>
                </div>
                
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded"></textarea>
                </div>
                
                <div>
                    <label for="imageUrl" class="block text-sm font-medium text-gray-700">Image URL</label>
                    <input type="text" id="imageUrl" class="mt-1 block w-full p-2 border border-gray-300 rounded">
                </div>
                
                <!-- Cost Breakdown Section -->
                <div id="costBreakdownContainer" class="cost-breakdown hidden">
                    <h3>Cost Breakdown</h3>
                    
                    <!-- Materials Section -->
                    <div class="cost-breakdown-section">
                        <h4 class="font-semibold text-gray-700 mb-2">Materials</h4>
                        <div id="materialsList" class="mb-2">
                            <div class="text-gray-500 text-sm italic">No materials data available</div>
                        </div>
                    </div>
                    
                    <!-- Labor Section -->
                    <div class="cost-breakdown-section">
                        <h4 class="font-semibold text-gray-700 mb-2">Labor</h4>
                        <div id="laborList" class="mb-2">
                            <div class="text-gray-500 text-sm italic">No labor data available</div>
                        </div>
                    </div>
                    
                    <!-- Energy Section -->
                    <div class="cost-breakdown-section">
                        <h4 class="font-semibold text-gray-700 mb-2">Energy</h4>
                        <div id="energyList" class="mb-2">
                            <div class="text-gray-500 text-sm italic">No energy data available</div>
                        </div>
                    </div>
                    
                    <!-- Totals Section -->
                    <div class="cost-totals">
                        <div class="cost-total-row">
                            <span class="cost-label">Materials Total:</span>
                            <span id="materialsTotalValue" class="cost-item-value">$0.00</span>
                        </div>
                        <div class="cost-total-row">
                            <span class="cost-label">Labor Total:</span>
                            <span id="laborTotalValue" class="cost-item-value">$0.00</span>
                        </div>
                        <div class="cost-total-row">
                            <span class="cost-label">Energy Total:</span>
                            <span id="energyTotalValue" class="cost-item-value">$0.00</span>
                        </div>
                        <div class="cost-total-row border-t border-gray-300 pt-2 mt-2">
                            <span class="font-semibold">Suggested Cost:</span>
                            <span id="suggestedCostValue" class="font-bold text-purple-700">$0.00</span>
                        </div>
                        <div class="mt-2 text-sm text-gray-600">
                            <button type="button" id="useSuggestedCostBtn" class="text-blue-600 hover:text-blue-800 underline">Use suggested cost</button>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelBtn" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">Cancel</button>
                    <button type="submit" id="saveBtn" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Save Item</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-bold mb-4 text-red-600">Confirm Delete</h2>
            <p class="mb-4">Are you sure you want to delete this item? This action cannot be undone.</p>
            <div class="flex justify-end space-x-3">
                <button id="cancelDeleteBtn" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">Cancel</button>
                <button id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Global variables
            let allInventory = [];
            let currentItemId = null;
            let originalValue = null;
            let currentCostBreakdown = null;
            
            // DOM Elements
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const stockFilter = document.getElementById('stockFilter');
            const inventoryTableBody = document.getElementById('inventoryTableBody');
            const addItemBtn = document.getElementById('addItemBtn');
            const itemModal = document.getElementById('itemModal');
            const modalTitle = document.getElementById('modalTitle');
            const itemForm = document.getElementById('itemForm');
            const closeModal = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelBtn');
            const deleteModal = document.getElementById('deleteModal');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            const toastNotification = document.getElementById('toastNotification');
            const toastMessage = document.getElementById('toastMessage');
            
            // Stats Elements
            const totalItemsEl = document.getElementById('totalItems');
            const lowStockCountEl = document.getElementById('lowStockCount');
            const categoryCountEl = document.getElementById('categoryCount');
            const totalCostValueEl = document.getElementById('totalCostValue');
            const totalRetailValueEl = document.getElementById('totalRetailValue');
            
            // Form Fields
            const itemIdInput = document.getElementById('itemId');
            const productIdInput = document.getElementById('productId');
            const nameInput = document.getElementById('name');
            const categoryInput = document.getElementById('category');
            const skuInput = document.getElementById('sku');
            const stockLevelInput = document.getElementById('stockLevel');
            const reorderPointInput = document.getElementById('reorderPoint');
            const costPriceInput = document.getElementById('costPrice');
            const retailPriceInput = document.getElementById('retailPrice');
            const descriptionInput = document.getElementById('description');
            const imageUrlInput = document.getElementById('imageUrl');
            
            // Cost Breakdown Elements
            const costBreakdownContainer = document.getElementById('costBreakdownContainer');
            const materialsList = document.getElementById('materialsList');
            const laborList = document.getElementById('laborList');
            const energyList = document.getElementById('energyList');
            const materialsTotalValue = document.getElementById('materialsTotalValue');
            const laborTotalValue = document.getElementById('laborTotalValue');
            const energyTotalValue = document.getElementById('energyTotalValue');
            const suggestedCostValue = document.getElementById('suggestedCostValue');
            const suggestedCostLabel = document.getElementById('suggestedCostLabel');
            const useSuggestedCostBtn = document.getElementById('useSuggestedCostBtn');
            
            // Fetch inventory data
            function fetchInventory() {
                const queryParams = new URLSearchParams();
                if (searchInput.value) queryParams.append('search', searchInput.value);
                if (categoryFilter.value) queryParams.append('category', categoryFilter.value);
                if (stockFilter.value) queryParams.append('stock', stockFilter.value);
                
                fetch('/api/inventory.php?' + queryParams.toString())
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        allInventory = data;
                        displayInventory(data);
                        updateStats(data);
                        populateCategories(data);
                    })
                    .catch(error => {
                        console.error('Error fetching inventory:', error);
                        showError('Failed to load inventory. Please try again.');
                    });
            }
            
            // Display inventory in table
            function displayInventory(items) {
                if (items.length === 0) {
                    inventoryTableBody.innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center py-4">No inventory items found.</td>
                        </tr>
                    `;
                    return;
                }
                
                inventoryTableBody.innerHTML = '';
                
                items.forEach(item => {
                    const row = document.createElement('tr');
                    
                    // Name column (editable)
                    const nameCell = document.createElement('td');
                    nameCell.className = 'editable';
                    nameCell.dataset.field = 'name';
                    nameCell.dataset.id = item.id;
                    nameCell.textContent = item.name;
                    nameCell.addEventListener('click', startEditing);
                    
                    // Category column (editable)
                    const categoryCell = document.createElement('td');
                    categoryCell.className = 'editable';
                    categoryCell.dataset.field = 'category';
                    categoryCell.dataset.id = item.id;
                    categoryCell.textContent = item.category;
                    categoryCell.addEventListener('click', startEditing);
                    
                    // SKU column (editable)
                    const skuCell = document.createElement('td');
                    skuCell.className = 'editable';
                    skuCell.dataset.field = 'sku';
                    skuCell.dataset.id = item.id;
                    skuCell.textContent = item.sku;
                    skuCell.addEventListener('click', startEditing);
                    
                    // Stock Level column (editable)
                    const stockCell = document.createElement('td');
                    stockCell.className = 'editable';
                    stockCell.dataset.field = 'stockLevel';
                    stockCell.dataset.id = item.id;
                    stockCell.dataset.type = 'number';
                    stockCell.textContent = item.stockLevel;
                    stockCell.addEventListener('click', startEditing);
                    
                    // Reorder Point column (editable)
                    const reorderCell = document.createElement('td');
                    reorderCell.className = 'editable';
                    reorderCell.dataset.field = 'reorderPoint';
                    reorderCell.dataset.id = item.id;
                    reorderCell.dataset.type = 'number';
                    reorderCell.textContent = item.reorderPoint;
                    reorderCell.addEventListener('click', startEditing);
                    
                    // Cost Price column (editable)
                    const costCell = document.createElement('td');
                    costCell.className = 'editable';
                    costCell.dataset.field = 'costPrice';
                    costCell.dataset.id = item.id;
                    costCell.dataset.type = 'price';
                    costCell.textContent = `$${parseFloat(item.costPrice).toFixed(2)}`;
                    costCell.addEventListener('click', startEditing);
                    
                    // Retail Price column (editable)
                    const retailCell = document.createElement('td');
                    retailCell.className = 'editable';
                    retailCell.dataset.field = 'retailPrice';
                    retailCell.dataset.id = item.id;
                    retailCell.dataset.type = 'price';
                    retailCell.textContent = `$${parseFloat(item.retailPrice).toFixed(2)}`;
                    retailCell.addEventListener('click', startEditing);
                    
                    // Actions column
                    const actionsCell = document.createElement('td');
                    
                    // Edit button with icon
                    const editBtn = document.createElement('button');
                    editBtn.className = 'action-btn edit-btn';
                    editBtn.innerHTML = '✏️';
                    editBtn.title = 'Edit Item';
                    editBtn.addEventListener('click', () => openEditModal(item));
                    
                    // Delete button with icon
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'action-btn delete-btn';
                    deleteBtn.innerHTML = '🗑️';
                    deleteBtn.title = 'Delete Item';
                    deleteBtn.addEventListener('click', () => openDeleteModal(item.id));
                    
                    actionsCell.appendChild(editBtn);
                    actionsCell.appendChild(deleteBtn);
                    
                    // Append all cells to the row
                    row.appendChild(nameCell);
                    row.appendChild(categoryCell);
                    row.appendChild(skuCell);
                    row.appendChild(stockCell);
                    row.appendChild(reorderCell);
                    row.appendChild(costCell);
                    row.appendChild(retailCell);
                    row.appendChild(actionsCell);
                    
                    inventoryTableBody.appendChild(row);
                });
            }
            
            // Update stats
            function updateStats(items) {
                // Total items
                totalItemsEl.textContent = items.length;
                
                // Low stock count
                const lowStockItems = items.filter(item => 
                    parseInt(item.stockLevel) <= parseInt(item.reorderPoint) && parseInt(item.stockLevel) > 0
                );
                lowStockCountEl.textContent = lowStockItems.length;
                
                // Category count
                const categories = new Set(items.map(item => item.category));
                categoryCountEl.textContent = categories.size;
                
                // Total cost value
                const totalCost = items.reduce((sum, item) => {
                    return sum + (parseFloat(item.costPrice) * parseInt(item.stockLevel));
                }, 0);
                totalCostValueEl.textContent = `$${totalCost.toFixed(2)}`;
                
                // Total retail value
                const totalRetail = items.reduce((sum, item) => {
                    return sum + (parseFloat(item.retailPrice) * parseInt(item.stockLevel));
                }, 0);
                totalRetailValueEl.textContent = `$${totalRetail.toFixed(2)}`;
            }
            
            // Populate category filter
            function populateCategories(items) {
                const categories = [...new Set(items.map(item => item.category))];
                
                // Clear existing options except the first one
                while (categoryFilter.options.length > 1) {
                    categoryFilter.remove(1);
                }
                
                // Add new options
                categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category;
                    option.textContent = category;
                    categoryFilter.appendChild(option);
                });
            }
            
            // Fetch cost breakdown data
            function fetchCostBreakdown(inventoryId) {
                costBreakdownContainer.classList.add('hidden');
                
                fetch(`/api/inventory-costs.php?inventoryId=${inventoryId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response.success) {
                            currentCostBreakdown = response.data;
                            displayCostBreakdown(response.data);
                            
                            // Show suggested cost label next to cost price input
                            const suggestedCost = response.data.totals.suggestedCost;
                            suggestedCostLabel.textContent = `(Suggested: $${suggestedCost.toFixed(2)} based on breakdown)`;
                            suggestedCostLabel.classList.remove('hidden');
                            
                            // Auto-populate cost price if it's 0.00
                            if (parseFloat(costPriceInput.value) === 0) {
                                costPriceInput.value = suggestedCost.toFixed(2);
                            }
                        } else {
                            // Hide cost breakdown if there's an error
                            costBreakdownContainer.classList.add('hidden');
                            suggestedCostLabel.classList.add('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching cost breakdown:', error);
                        costBreakdownContainer.classList.add('hidden');
                        suggestedCostLabel.classList.add('hidden');
                    });
            }
            
            // Display cost breakdown data
            function displayCostBreakdown(data) {
                // Display materials
                if (data.materials && data.materials.length > 0) {
                    let materialsHtml = '';
                    data.materials.forEach(material => {
                        materialsHtml += `
                            <div class="cost-item">
                                <span class="cost-item-name">${material.name}</span>
                                <span class="cost-item-value">$${parseFloat(material.cost).toFixed(2)}</span>
                            </div>
                        `;
                    });
                    materialsList.innerHTML = materialsHtml;
                } else {
                    materialsList.innerHTML = '<div class="text-gray-500 text-sm italic">No materials data available</div>';
                }
                
                // Display labor
                if (data.labor && data.labor.length > 0) {
                    let laborHtml = '';
                    data.labor.forEach(labor => {
                        laborHtml += `
                            <div class="cost-item">
                                <span class="cost-item-name">${labor.description}</span>
                                <span class="cost-item-value">$${parseFloat(labor.cost).toFixed(2)}</span>
                            </div>
                        `;
                    });
                    laborList.innerHTML = laborHtml;
                } else {
                    laborList.innerHTML = '<div class="text-gray-500 text-sm italic">No labor data available</div>';
                }
                
                // Display energy
                if (data.energy && data.energy.length > 0) {
                    let energyHtml = '';
                    data.energy.forEach(energy => {
                        energyHtml += `
                            <div class="cost-item">
                                <span class="cost-item-name">${energy.description}</span>
                                <span class="cost-item-value">$${parseFloat(energy.cost).toFixed(2)}</span>
                            </div>
                        `;
                    });
                    energyList.innerHTML = energyHtml;
                } else {
                    energyList.innerHTML = '<div class="text-gray-500 text-sm italic">No energy data available</div>';
                }
                
                // Update totals
                materialsTotalValue.textContent = `$${data.totals.materialTotal.toFixed(2)}`;
                laborTotalValue.textContent = `$${data.totals.laborTotal.toFixed(2)}`;
                energyTotalValue.textContent = `$${data.totals.energyTotal.toFixed(2)}`;
                suggestedCostValue.textContent = `$${data.totals.suggestedCost.toFixed(2)}`;
                
                // Show the cost breakdown container
                costBreakdownContainer.classList.remove('hidden');
            }
            
            // Open add item modal
            function openAddModal() {
                modalTitle.textContent = 'Add New Item';
                itemForm.reset();
                itemIdInput.value = '';
                suggestedCostLabel.classList.add('hidden');
                costBreakdownContainer.classList.add('hidden');
                itemModal.classList.remove('hidden');
            }
            
            // Open edit item modal
            function openEditModal(item) {
                modalTitle.textContent = 'Edit Item';
                
                // Populate form fields
                itemIdInput.value = item.id;
                productIdInput.value = item.productId || '';
                nameInput.value = item.name || '';
                categoryInput.value = item.category || '';
                skuInput.value = item.sku || '';
                stockLevelInput.value = item.stockLevel || 0;
                reorderPointInput.value = item.reorderPoint || 0;
                costPriceInput.value = item.costPrice || 0;
                retailPriceInput.value = item.retailPrice || 0;
                descriptionInput.value = item.description || '';
                imageUrlInput.value = item.imageUrl || '';
                
                // Fetch and display cost breakdown
                fetchCostBreakdown(item.id);
                
                itemModal.classList.remove('hidden');
            }
            
            // Close modal
            function closeModal() {
                itemModal.classList.add('hidden');
                deleteModal.classList.add('hidden');
            }
            
            // Open delete confirmation modal
            function openDeleteModal(id) {
                currentItemId = id;
                deleteModal.classList.remove('hidden');
            }
            
            // Save item (add or update)
            function saveItem(event) {
                event.preventDefault();
                
                const isEditing = itemIdInput.value !== '';
                const endpoint = isEditing ? '/api/update-inventory.php' : '/api/add-inventory.php';
                const method = isEditing ? 'PUT' : 'POST';
                
                const formData = {
                    id: itemIdInput.value,
                    productId: productIdInput.value,
                    name: nameInput.value,
                    category: categoryInput.value,
                    sku: skuInput.value,
                    stockLevel: stockLevelInput.value,
                    reorderPoint: reorderPointInput.value,
                    costPrice: costPriceInput.value,
                    retailPrice: retailPriceInput.value,
                    description: descriptionInput.value,
                    imageUrl: imageUrlInput.value
                };
                
                fetch(endpoint, {
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
                        showSuccess(isEditing ? 'Item updated successfully' : 'Item added successfully');
                        closeModal();
                        fetchInventory();
                    } else {
                        showError(data.error || 'Failed to save item');
                    }
                })
                .catch(error => {
                    console.error('Error saving item:', error);
                    showError('Failed to save item. Please try again.');
                });
            }
            
            // Delete item
            function deleteItem() {
                fetch('/api/delete-inventory.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: currentItemId })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showSuccess('Item deleted successfully');
                        closeModal();
                        fetchInventory();
                    } else {
                        showError(data.error || 'Failed to delete item');
                    }
                })
                .catch(error => {
                    console.error('Error deleting item:', error);
                    showError('Failed to delete item. Please try again.');
                });
            }
            
            // Use suggested cost
            function useSuggestedCost() {
                if (currentCostBreakdown && currentCostBreakdown.totals) {
                    costPriceInput.value = currentCostBreakdown.totals.suggestedCost.toFixed(2);
                    costPriceInput.classList.add('bg-green-100');
                    setTimeout(() => {
                        costPriceInput.classList.remove('bg-green-100');
                    }, 1000);
                }
            }
            
            // Inline editing functions
            function startEditing(event) {
                const cell = event.currentTarget;
                
                // Don't start editing if already editing
                if (cell.classList.contains('editing')) return;
                
                // Save original value for cancel
                originalValue = cell.textContent;
                
                // Create input element
                const input = document.createElement('input');
                
                // Set input type and value based on data type
                if (cell.dataset.type === 'number') {
                    input.type = 'number';
                    input.min = '0';
                    input.value = originalValue;
                } else if (cell.dataset.type === 'price') {
                    input.type = 'number';
                    input.min = '0';
                    input.step = '0.01';
                    input.value = originalValue.replace('$', '');
                } else {
                    input.type = 'text';
                    input.value = originalValue;
                }
                
                // Set up event listeners
                input.addEventListener('blur', saveEditing);
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        input.blur();
                    } else if (e.key === 'Escape') {
                        cancelEditing(cell);
                    }
                });
                
                // Clear cell and add input
                cell.textContent = '';
                cell.appendChild(input);
                cell.classList.add('editing');
                
                // Focus input
                input.focus();
            }
            
            function saveEditing(event) {
                const input = event.target;
                const cell = input.parentElement;
                
                // Get field info
                const field = cell.dataset.field;
                const id = cell.dataset.id;
                const type = cell.dataset.type;
                
                // Validate input
                let value = input.value.trim();
                
                if (type === 'number' && (isNaN(value) || value === '')) {
                    value = '0';
                } else if (type === 'price' && (isNaN(value) || value === '')) {
                    value = '0.00';
                }
                
                // Show saving state
                cell.classList.add('saving');
                
                // Prepare data for API
                const updateData = {
                    id: id,
                    field: field,
                    value: value
                };
                
                // Send update to API
                fetch('/api/update-inventory.php', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(updateData)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Update cell with formatted value
                        if (type === 'price') {
                            cell.textContent = `$${parseFloat(value).toFixed(2)}`;
                        } else {
                            cell.textContent = value;
                        }
                        
                        // Update local data
                        const itemIndex = allInventory.findIndex(item => item.id === id);
                        if (itemIndex !== -1) {
                            allInventory[itemIndex][field] = value;
                            updateStats(allInventory);
                        }
                        
                        // Show success state briefly
                        cell.classList.remove('saving', 'editing');
                        cell.classList.add('success');
                        setTimeout(() => {
                            cell.classList.remove('success');
                        }, 1000);
                        
                        showSuccess(`Updated ${field} successfully`);
                    } else {
                        // Revert to original value on error
                        cell.textContent = originalValue;
                        cell.classList.remove('saving', 'editing');
                        cell.classList.add('error');
                        setTimeout(() => {
                            cell.classList.remove('error');
                        }, 1000);
                        
                        showError(data.error || `Failed to update ${field}`);
                    }
                })
                .catch(error => {
                    console.error('Error updating field:', error);
                    
                    // Revert to original value on error
                    cell.textContent = originalValue;
                    cell.classList.remove('saving', 'editing');
                    cell.classList.add('error');
                    setTimeout(() => {
                        cell.classList.remove('error');
                    }, 1000);
                    
                    showError(`Failed to update ${field}. Please try again.`);
                });
            }
            
            function cancelEditing(cell) {
                cell.textContent = originalValue;
                cell.classList.remove('editing');
            }
            
            // Toast notification functions
            function showSuccess(message) {
                toastMessage.textContent = message;
                toastNotification.className = 'toast-notification success';
                toastNotification.classList.add('show');
                
                setTimeout(() => {
                    toastNotification.classList.remove('show');
                }, 3000);
            }
            
            function showError(message) {
                toastMessage.textContent = message;
                toastNotification.className = 'toast-notification error';
                toastNotification.classList.add('show');
                
                setTimeout(() => {
                    toastNotification.classList.remove('show');
                }, 3000);
            }
            
            // Event listeners
            addItemBtn.addEventListener('click', openAddModal);
            closeModal.addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);
            itemForm.addEventListener('submit', saveItem);
            cancelDeleteBtn.addEventListener('click', closeModal);
            confirmDeleteBtn.addEventListener('click', deleteItem);
            useSuggestedCostBtn.addEventListener('click', useSuggestedCost);
            
            searchInput.addEventListener('input', fetchInventory);
            categoryFilter.addEventListener('change', fetchInventory);
            stockFilter.addEventListener('change', fetchInventory);
            
            // Initial data load
            fetchInventory();
        });
    </script>
</div>
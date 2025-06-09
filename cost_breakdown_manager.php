<?php
// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once __DIR__ . '/api/config.php';

// Initialize variables
$message = '';
$messageType = '';

// Connect to database
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get all inventory items
    $stmt = $pdo->query("SELECT id, name, category, costPrice FROM inventory ORDER BY name");
    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "error";
    $inventoryItems = [];
}

// Get selected item if provided
$selectedItemId = isset($_GET['item']) ? $_GET['item'] : '';
$selectedItem = null;

if (!empty($selectedItemId)) {
    try {
        // Get item details
        $itemStmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $itemStmt->execute([$selectedItemId]);
        $selectedItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Error retrieving item: " . $e->getMessage();
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost Breakdown Manager - Whimsical Frog</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Custom Styles */
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-20px);
            transition: opacity 0.3s, transform 0.3s;
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
        
        /* Action Buttons */
        .action-btn {
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 14px;
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
        
        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Animate cost changes */
        .highlight-change {
            animation: highlight 1s ease-out;
        }
        @keyframes highlight {
            0% { background-color: #c6f6d5; }
            100% { background-color: transparent; }
        }
        
        /* Modal styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .modal-backdrop.show {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-content {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform: scale(0.9);
            transition: transform 0.3s;
        }
        .modal-backdrop.show .modal-content {
            transform: scale(1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Toast Notification -->
    <div id="toast" class="toast-notification"></div>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-green-700">Cost Breakdown Manager</h1>
            <a href="?page=admin&section=inventory" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                Back to Inventory
            </a>
        </div>
        
        <!-- Item Selection -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Select Inventory Item</h2>
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <select id="itemSelector" class="w-full p-2 border border-gray-300 rounded">
                        <option value="">-- Select an item --</option>
                        <?php foreach ($inventoryItems as $item): ?>
                        <option value="<?php echo $item['id']; ?>" <?php echo ($selectedItemId === $item['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['name']); ?> (<?php echo $item['id']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button id="loadItemBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                        Load Item
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Cost Breakdown Display -->
        <div id="costBreakdownContainer" class="<?php echo empty($selectedItemId) ? 'hidden' : ''; ?>">
            <!-- Item Details -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-700" id="itemNameDisplay">
                        <?php echo isset($selectedItem) ? htmlspecialchars($selectedItem['name']) : ''; ?>
                    </h2>
                    <span class="bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-sm" id="itemIdDisplay">
                        <?php echo isset($selectedItem) ? $selectedItem['id'] : ''; ?>
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <span class="text-gray-600 text-sm">Category:</span>
                        <p id="itemCategoryDisplay" class="font-medium">
                            <?php echo isset($selectedItem) ? htmlspecialchars($selectedItem['category']) : ''; ?>
                        </p>
                    </div>
                    <div>
                        <span class="text-gray-600 text-sm">Current Cost Price:</span>
                        <p id="itemCostDisplay" class="font-medium">
                            $<?php echo isset($selectedItem) ? number_format(floatval($selectedItem['costPrice']), 2) : '0.00'; ?>
                        </p>
                    </div>
                    <div>
                        <span class="text-gray-600 text-sm">Retail Price:</span>
                        <p id="itemRetailDisplay" class="font-medium">
                            $<?php echo isset($selectedItem) ? number_format(floatval($selectedItem['retailPrice']), 2) : '0.00'; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Cost Breakdown Sections -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column: Materials and Labor -->
                <div>
                    <!-- Materials Section -->
                    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-700">Materials</h3>
                            <button id="addMaterialBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                Add Material
                            </button>
                        </div>
                        
                        <div id="materialsList" class="divide-y divide-gray-200">
                            <!-- Materials will be loaded here via JavaScript -->
                            <div class="py-4 text-center text-gray-500 italic" id="noMaterialsMsg">
                                No materials added yet
                            </div>
                        </div>
                    </div>
                    
                    <!-- Labor Section -->
                    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-700">Labor</h3>
                            <button id="addLaborBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                Add Labor
                            </button>
                        </div>
                        
                        <div id="laborList" class="divide-y divide-gray-200">
                            <!-- Labor items will be loaded here via JavaScript -->
                            <div class="py-4 text-center text-gray-500 italic" id="noLaborMsg">
                                No labor costs added yet
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Energy and Totals -->
                <div>
                    <!-- Energy Section -->
                    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-700">Energy</h3>
                            <button id="addEnergyBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                Add Energy
                            </button>
                        </div>
                        
                        <div id="energyList" class="divide-y divide-gray-200">
                            <!-- Energy items will be loaded here via JavaScript -->
                            <div class="py-4 text-center text-gray-500 italic" id="noEnergyMsg">
                                No energy costs added yet
                            </div>
                        </div>
                    </div>
                    
                    <!-- Totals Section -->
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">Cost Summary</h3>
                        
                        <div class="cost-totals">
                            <div class="cost-total-row">
                                <span class="cost-label">Materials Total:</span>
                                <span id="materialsTotalDisplay" class="cost-item-value">$0.00</span>
                            </div>
                            <div class="cost-total-row">
                                <span class="cost-label">Labor Total:</span>
                                <span id="laborTotalDisplay" class="cost-item-value">$0.00</span>
                            </div>
                            <div class="cost-total-row">
                                <span class="cost-label">Energy Total:</span>
                                <span id="energyTotalDisplay" class="cost-item-value">$0.00</span>
                            </div>
                            <div class="cost-total-row border-t border-gray-300 pt-2 mt-2">
                                <span class="font-semibold">Suggested Cost:</span>
                                <span id="suggestedCostDisplay" class="font-bold text-purple-700">$0.00</span>
                            </div>
                            
                            <div class="mt-4 flex justify-between items-center">
                                <div>
                                    <span class="text-sm text-gray-600">Current Cost Price:</span>
                                    <span id="currentCostDisplay" class="ml-2 font-medium">$0.00</span>
                                </div>
                                <button id="updateCostBtn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded text-sm">
                                    Update Cost Price
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Empty State -->
        <div id="emptyStateContainer" class="<?php echo !empty($selectedItemId) ? 'hidden' : ''; ?> bg-white p-8 rounded-lg shadow-md text-center">
            <svg class="w-20 h-20 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
            </svg>
            <h2 class="text-xl font-semibold text-gray-700 mb-2">No Item Selected</h2>
            <p class="text-gray-500 mb-6">Please select an inventory item to manage its cost breakdown</p>
        </div>
    </div>
    
    <!-- Add/Edit Material Modal -->
    <div id="materialModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 id="materialModalTitle" class="text-lg font-semibold text-gray-700">Add Material</h3>
                <button class="text-gray-500 hover:text-gray-700 close-modal" data-modal="materialModal">&times;</button>
            </div>
            
            <form id="materialForm" class="space-y-4">
                <input type="hidden" id="materialId" value="">
                <input type="hidden" id="materialInventoryId" value="">
                
                <div>
                    <label for="materialName" class="block text-sm font-medium text-gray-700 mb-1">Material Name</label>
                    <input type="text" id="materialName" class="w-full p-2 border border-gray-300 rounded" required>
                </div>
                
                <div>
                    <label for="materialCost" class="block text-sm font-medium text-gray-700 mb-1">Cost ($)</label>
                    <input type="number" id="materialCost" class="w-full p-2 border border-gray-300 rounded" min="0" step="0.01" required>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 close-modal" data-modal="materialModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        <span id="materialSubmitText">Save Material</span>
                        <span id="materialSubmitSpinner" class="loading-spinner hidden"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add/Edit Labor Modal -->
    <div id="laborModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 id="laborModalTitle" class="text-lg font-semibold text-gray-700">Add Labor</h3>
                <button class="text-gray-500 hover:text-gray-700 close-modal" data-modal="laborModal">&times;</button>
            </div>
            
            <form id="laborForm" class="space-y-4">
                <input type="hidden" id="laborId" value="">
                <input type="hidden" id="laborInventoryId" value="">
                
                <div>
                    <label for="laborDescription" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" id="laborDescription" class="w-full p-2 border border-gray-300 rounded" required>
                </div>
                
                <div>
                    <label for="laborCost" class="block text-sm font-medium text-gray-700 mb-1">Cost ($)</label>
                    <input type="number" id="laborCost" class="w-full p-2 border border-gray-300 rounded" min="0" step="0.01" required>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 close-modal" data-modal="laborModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        <span id="laborSubmitText">Save Labor</span>
                        <span id="laborSubmitSpinner" class="loading-spinner hidden"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add/Edit Energy Modal -->
    <div id="energyModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 id="energyModalTitle" class="text-lg font-semibold text-gray-700">Add Energy</h3>
                <button class="text-gray-500 hover:text-gray-700 close-modal" data-modal="energyModal">&times;</button>
            </div>
            
            <form id="energyForm" class="space-y-4">
                <input type="hidden" id="energyId" value="">
                <input type="hidden" id="energyInventoryId" value="">
                
                <div>
                    <label for="energyDescription" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" id="energyDescription" class="w-full p-2 border border-gray-300 rounded" required>
                </div>
                
                <div>
                    <label for="energyCost" class="block text-sm font-medium text-gray-700 mb-1">Cost ($)</label>
                    <input type="number" id="energyCost" class="w-full p-2 border border-gray-300 rounded" min="0" step="0.01" required>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 close-modal" data-modal="energyModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        <span id="energySubmitText">Save Energy</span>
                        <span id="energySubmitSpinner" class="loading-spinner hidden"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-red-600">Confirm Delete</h3>
                <button class="text-gray-500 hover:text-gray-700 close-modal" data-modal="deleteModal">&times;</button>
            </div>
            
            <p class="mb-4" id="deleteConfirmText">Are you sure you want to delete this item?</p>
            
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 close-modal" data-modal="deleteModal">Cancel</button>
                <button id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    <span id="deleteSubmitText">Delete</span>
                    <span id="deleteSubmitSpinner" class="loading-spinner hidden"></span>
                </button>
            </div>
            
            <input type="hidden" id="deleteItemId" value="">
            <input type="hidden" id="deleteItemType" value="">
        </div>
    </div>
    
    <!-- Update Cost Price Confirmation Modal -->
    <div id="updateCostModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-purple-600">Update Cost Price</h3>
                <button class="text-gray-500 hover:text-gray-700 close-modal" data-modal="updateCostModal">&times;</button>
            </div>
            
            <p class="mb-4">Do you want to update the cost price to match the suggested cost?</p>
            
            <div class="bg-gray-100 p-4 rounded mb-4">
                <div class="flex justify-between items-center">
                    <span>Current Cost Price:</span>
                    <span id="updateCurrentCost" class="font-medium">$0.00</span>
                </div>
                <div class="flex justify-between items-center mt-2">
                    <span>Suggested Cost:</span>
                    <span id="updateSuggestedCost" class="font-medium text-purple-600">$0.00</span>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 close-modal" data-modal="updateCostModal">Cancel</button>
                <button id="confirmUpdateCostBtn" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                    <span id="updateCostSubmitText">Update Cost</span>
                    <span id="updateCostSubmitSpinner" class="loading-spinner hidden"></span>
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables
        let currentItemId = '<?php echo $selectedItemId; ?>';
        let costBreakdown = {
            materials: [],
            labor: [],
            energy: [],
            totals: {
                materialTotal: 0,
                laborTotal: 0,
                energyTotal: 0,
                suggestedCost: 0
            }
        };
        
        // DOM Ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize event listeners
            initEventListeners();
            
            // Load cost breakdown data if item is selected
            if (currentItemId) {
                loadCostBreakdown(currentItemId);
            }
        });
        
        // Initialize event listeners
        function initEventListeners() {
            // Item selection
            document.getElementById('loadItemBtn').addEventListener('click', function() {
                const selectedItemId = document.getElementById('itemSelector').value;
                if (selectedItemId) {
                    window.location.href = '?item=' + selectedItemId;
                }
            });
            
            // Add buttons
            document.getElementById('addMaterialBtn').addEventListener('click', function() {
                openAddModal('material');
            });
            
            document.getElementById('addLaborBtn').addEventListener('click', function() {
                openAddModal('labor');
            });
            
            document.getElementById('addEnergyBtn').addEventListener('click', function() {
                openAddModal('energy');
            });
            
            // Update cost price button
            document.getElementById('updateCostBtn').addEventListener('click', function() {
                openUpdateCostModal();
            });
            
            // Form submissions
            document.getElementById('materialForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveMaterial();
            });
            
            document.getElementById('laborForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveLabor();
            });
            
            document.getElementById('energyForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveEnergy();
            });
            
            // Confirm delete button
            document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                const itemId = document.getElementById('deleteItemId').value;
                const itemType = document.getElementById('deleteItemType').value;
                deleteItem(itemId, itemType);
            });
            
            // Confirm update cost button
            document.getElementById('confirmUpdateCostBtn').addEventListener('click', function() {
                updateCostPrice();
            });
            
            // Close modal buttons
            document.querySelectorAll('.close-modal').forEach(button => {
                button.addEventListener('click', function() {
                    const modalId = this.getAttribute('data-modal');
                    closeModal(modalId);
                });
            });
        }
        
        // Load cost breakdown data
        function loadCostBreakdown(itemId) {
            showLoading();
            
            fetch('process_cost_breakdown.php?inventoryId=' + itemId + '&costType=all')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        costBreakdown = data.data;
                        displayCostBreakdown();
                        hideLoading();
                    } else {
                        showToast('Error: ' + data.error, 'error');
                        hideLoading();
                    }
                })
                .catch(error => {
                    console.error('Error fetching cost breakdown:', error);
                    showToast('Failed to load cost breakdown. Please try again.', 'error');
                    hideLoading();
                });
        }
        
        // Display cost breakdown data
        function displayCostBreakdown() {
            // Display materials
            const materialsList = document.getElementById('materialsList');
            materialsList.innerHTML = '';
            
            if (costBreakdown.materials.length > 0) {
                document.getElementById('noMaterialsMsg').classList.add('hidden');
                costBreakdown.materials.forEach(material => {
                    const materialItem = document.createElement('div');
                    materialItem.className = 'py-3 flex justify-between items-center';
                    materialItem.innerHTML = `
                        <div>
                            <span class="font-medium">${escapeHtml(material.name)}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="mr-4 font-semibold">$${parseFloat(material.cost).toFixed(2)}</span>
                            <button class="action-btn edit-btn mr-1" onclick="openEditModal('material', ${material.id})">✏️</button>
                            <button class="action-btn delete-btn" onclick="openDeleteModal('material', ${material.id}, '${escapeHtml(material.name)}')">🗑️</button>
                        </div>
                    `;
                    materialsList.appendChild(materialItem);
                });
            } else {
                document.getElementById('noMaterialsMsg').classList.remove('hidden');
            }
            
            // Display labor
            const laborList = document.getElementById('laborList');
            laborList.innerHTML = '';
            
            if (costBreakdown.labor.length > 0) {
                document.getElementById('noLaborMsg').classList.add('hidden');
                costBreakdown.labor.forEach(labor => {
                    const laborItem = document.createElement('div');
                    laborItem.className = 'py-3 flex justify-between items-center';
                    laborItem.innerHTML = `
                        <div>
                            <span class="font-medium">${escapeHtml(labor.description)}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="mr-4 font-semibold">$${parseFloat(labor.cost).toFixed(2)}</span>
                            <button class="action-btn edit-btn mr-1" onclick="openEditModal('labor', ${labor.id})">✏️</button>
                            <button class="action-btn delete-btn" onclick="openDeleteModal('labor', ${labor.id}, '${escapeHtml(labor.description)}')">🗑️</button>
                        </div>
                    `;
                    laborList.appendChild(laborItem);
                });
            } else {
                document.getElementById('noLaborMsg').classList.remove('hidden');
            }
            
            // Display energy
            const energyList = document.getElementById('energyList');
            energyList.innerHTML = '';
            
            if (costBreakdown.energy.length > 0) {
                document.getElementById('noEnergyMsg').classList.add('hidden');
                costBreakdown.energy.forEach(energy => {
                    const energyItem = document.createElement('div');
                    energyItem.className = 'py-3 flex justify-between items-center';
                    energyItem.innerHTML = `
                        <div>
                            <span class="font-medium">${escapeHtml(energy.description)}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="mr-4 font-semibold">$${parseFloat(energy.cost).toFixed(2)}</span>
                            <button class="action-btn edit-btn mr-1" onclick="openEditModal('energy', ${energy.id})">✏️</button>
                            <button class="action-btn delete-btn" onclick="openDeleteModal('energy', ${energy.id}, '${escapeHtml(energy.description)}')">🗑️</button>
                        </div>
                    `;
                    energyList.appendChild(energyItem);
                });
            } else {
                document.getElementById('noEnergyMsg').classList.remove('hidden');
            }
            
            // Update totals
            document.getElementById('materialsTotalDisplay').textContent = '$' + costBreakdown.totals.materialTotal.toFixed(2);
            document.getElementById('laborTotalDisplay').textContent = '$' + costBreakdown.totals.laborTotal.toFixed(2);
            document.getElementById('energyTotalDisplay').textContent = '$' + costBreakdown.totals.energyTotal.toFixed(2);
            document.getElementById('suggestedCostDisplay').textContent = '$' + costBreakdown.totals.suggestedCost.toFixed(2);
            
            // Update current cost display
            const currentCost = document.getElementById('itemCostDisplay').textContent.replace('$', '');
            document.getElementById('currentCostDisplay').textContent = '$' + parseFloat(currentCost).toFixed(2);
            
            // Highlight changes with animation
            animateElement('materialsTotalDisplay');
            animateElement('laborTotalDisplay');
            animateElement('energyTotalDisplay');
            animateElement('suggestedCostDisplay');
        }
        
        // Open add modal
        function openAddModal(type) {
            resetForm(type);
            
            document.getElementById(type + 'ModalTitle').textContent = 'Add ' + capitalizeFirstLetter(type);
            document.getElementById(type + 'InventoryId').value = currentItemId;
            document.getElementById(type + 'Id').value = '';
            
            openModal(type + 'Modal');
        }
        
        // Open edit modal
        function openEditModal(type, id) {
            resetForm(type);
            
            document.getElementById(type + 'ModalTitle').textContent = 'Edit ' + capitalizeFirstLetter(type);
            document.getElementById(type + 'Id').value = id;
            document.getElementById(type + 'InventoryId').value = currentItemId;
            
            // Find the item in the costBreakdown
            let item;
            if (type === 'material') {
                item = costBreakdown.materials.find(m => m.id == id);
                if (item) {
                    document.getElementById('materialName').value = item.name;
                    document.getElementById('materialCost').value = parseFloat(item.cost).toFixed(2);
                }
            } else if (type === 'labor') {
                item = costBreakdown.labor.find(l => l.id == id);
                if (item) {
                    document.getElementById('laborDescription').value = item.description;
                    document.getElementById('laborCost').value = parseFloat(item.cost).toFixed(2);
                }
            } else if (type === 'energy') {
                item = costBreakdown.energy.find(e => e.id == id);
                if (item) {
                    document.getElementById('energyDescription').value = item.description;
                    document.getElementById('energyCost').value = parseFloat(item.cost).toFixed(2);
                }
            }
            
            openModal(type + 'Modal');
        }
        
        // Open delete modal
        function openDeleteModal(type, id, name) {
            document.getElementById('deleteItemId').value = id;
            document.getElementById('deleteItemType').value = type;
            
            let itemTypeDisplay = type;
            if (type === 'material') {
                itemTypeDisplay = 'material';
            } else if (type === 'labor') {
                itemTypeDisplay = 'labor cost';
            } else if (type === 'energy') {
                itemTypeDisplay = 'energy cost';
            }
            
            document.getElementById('deleteConfirmText').textContent = `Are you sure you want to delete the ${itemTypeDisplay} "${name}"?`;
            
            openModal('deleteModal');
        }
        
        // Open update cost modal
        function openUpdateCostModal() {
            const currentCost = document.getElementById('currentCostDisplay').textContent.replace('$', '');
            const suggestedCost = costBreakdown.totals.suggestedCost.toFixed(2);
            
            document.getElementById('updateCurrentCost').textContent = '$' + parseFloat(currentCost).toFixed(2);
            document.getElementById('updateSuggestedCost').textContent = '$' + suggestedCost;
            
            openModal('updateCostModal');
        }
        
        // Save material
        function saveMaterial() {
            const id = document.getElementById('materialId').value;
            const inventoryId = document.getElementById('materialInventoryId').value;
            const name = document.getElementById('materialName').value;
            const cost = document.getElementById('materialCost').value;
            
            const isEdit = id !== '';
            const method = isEdit ? 'PUT' : 'POST';
            const url = isEdit 
                ? `process_cost_breakdown.php?inventoryId=${inventoryId}&costType=materials&id=${id}` 
                : 'process_cost_breakdown.php?inventoryId=' + inventoryId;
            
            const data = {
                costType: 'materials',
                name: name,
                cost: parseFloat(cost)
            };
            
            showFormLoading('material');
            
            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    closeModal('materialModal');
                    showToast(result.message, 'success');
                    loadCostBreakdown(inventoryId);
                } else {
                    showToast('Error: ' + result.error, 'error');
                }
                hideFormLoading('material');
            })
            .catch(error => {
                console.error('Error saving material:', error);
                showToast('Failed to save material. Please try again.', 'error');
                hideFormLoading('material');
            });
        }
        
        // Save labor
        function saveLabor() {
            const id = document.getElementById('laborId').value;
            const inventoryId = document.getElementById('laborInventoryId').value;
            const description = document.getElementById('laborDescription').value;
            const cost = document.getElementById('laborCost').value;
            
            const isEdit = id !== '';
            const method = isEdit ? 'PUT' : 'POST';
            const url = isEdit 
                ? `process_cost_breakdown.php?inventoryId=${inventoryId}&costType=labor&id=${id}` 
                : 'process_cost_breakdown.php?inventoryId=' + inventoryId;
            
            const data = {
                costType: 'labor',
                description: description,
                cost: parseFloat(cost)
            };
            
            showFormLoading('labor');
            
            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    closeModal('laborModal');
                    showToast(result.message, 'success');
                    loadCostBreakdown(inventoryId);
                } else {
                    showToast('Error: ' + result.error, 'error');
                }
                hideFormLoading('labor');
            })
            .catch(error => {
                console.error('Error saving labor:', error);
                showToast('Failed to save labor. Please try again.', 'error');
                hideFormLoading('labor');
            });
        }
        
        // Save energy
        function saveEnergy() {
            const id = document.getElementById('energyId').value;
            const inventoryId = document.getElementById('energyInventoryId').value;
            const description = document.getElementById('energyDescription').value;
            const cost = document.getElementById('energyCost').value;
            
            const isEdit = id !== '';
            const method = isEdit ? 'PUT' : 'POST';
            const url = isEdit 
                ? `process_cost_breakdown.php?inventoryId=${inventoryId}&costType=energy&id=${id}` 
                : 'process_cost_breakdown.php?inventoryId=' + inventoryId;
            
            const data = {
                costType: 'energy',
                description: description,
                cost: parseFloat(cost)
            };
            
            showFormLoading('energy');
            
            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    closeModal('energyModal');
                    showToast(result.message, 'success');
                    loadCostBreakdown(inventoryId);
                } else {
                    showToast('Error: ' + result.error, 'error');
                }
                hideFormLoading('energy');
            })
            .catch(error => {
                console.error('Error saving energy:', error);
                showToast('Failed to save energy. Please try again.', 'error');
                hideFormLoading('energy');
            });
        }
        
        // Delete item
        function deleteItem(id, type) {
            const url = `process_cost_breakdown.php?inventoryId=${currentItemId}&costType=${type}&id=${id}`;
            
            showFormLoading('delete');
            
            fetch(url, {
                method: 'DELETE'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    closeModal('deleteModal');
                    showToast(result.message, 'success');
                    loadCostBreakdown(currentItemId);
                } else {
                    showToast('Error: ' + result.error, 'error');
                }
                hideFormLoading('delete');
            })
            .catch(error => {
                console.error('Error deleting item:', error);
                showToast('Failed to delete item. Please try again.', 'error');
                hideFormLoading('delete');
            });
        }
        
        // Update cost price
        function updateCostPrice() {
            const suggestedCost = costBreakdown.totals.suggestedCost;
            
            showFormLoading('updateCost');
            
            // This would typically call an API endpoint to update the inventory item's cost price
            fetch('process_inventory_update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: currentItemId,
                    costPrice: suggestedCost
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    closeModal('updateCostModal');
                    showToast('Cost price updated successfully', 'success');
                    
                    // Update displayed cost price
                    document.getElementById('itemCostDisplay').textContent = '$' + suggestedCost.toFixed(2);
                    document.getElementById('currentCostDisplay').textContent = '$' + suggestedCost.toFixed(2);
                    
                    // Highlight the updated cost price
                    animateElement('itemCostDisplay');
                    animateElement('currentCostDisplay');
                } else {
                    showToast('Error: ' + (result.error || 'Failed to update cost price'), 'error');
                }
                hideFormLoading('updateCost');
            })
            .catch(error => {
                console.error('Error updating cost price:', error);
                showToast('Failed to update cost price. Please try again.', 'error');
                hideFormLoading('updateCost');
            });
        }
        
        // Helper Functions
        
        // Open modal
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('show');
        }
        
        // Close modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
        }
        
        // Reset form
        function resetForm(type) {
            if (type === 'material') {
                document.getElementById('materialName').value = '';
                document.getElementById('materialCost').value = '';
            } else if (type === 'labor') {
                document.getElementById('laborDescription').value = '';
                document.getElementById('laborCost').value = '';
            } else if (type === 'energy') {
                document.getElementById('energyDescription').value = '';
                document.getElementById('energyCost').value = '';
            }
        }
        
        // Show toast notification
        function showToast(message, type) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast-notification ' + type;
            
            // Add show class to trigger animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Hide after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Show loading state
        function showLoading() {
            // You could add a loading spinner or overlay here
        }
        
        // Hide loading state
        function hideLoading() {
            // Remove loading spinner or overlay
        }
        
        // Show form loading
        function showFormLoading(formType) {
            document.getElementById(formType + 'SubmitText').classList.add('hidden');
            document.getElementById(formType + 'SubmitSpinner').classList.remove('hidden');
        }
        
        // Hide form loading
        function hideFormLoading(formType) {
            document.getElementById(formType + 'SubmitText').classList.remove('hidden');
            document.getElementById(formType + 'SubmitSpinner').classList.add('hidden');
        }
        
        // Animate element (highlight changes)
        function animateElement(elementId) {
            const element = document.getElementById(elementId);
            element.classList.remove('highlight-change');
            
            // Trigger reflow to restart animation
            void element.offsetWidth;
            
            element.classList.add('highlight-change');
        }
        
        // Capitalize first letter
        function capitalizeFirstLetter(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>

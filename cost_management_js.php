// Cost Item Management Functions
// This script assumes it's included in a context where `currentItemId` is defined (e.g., within admin_inventory.php when an item is being edited)
// and that `costBreakdown` global variable is initialized with the item's cost data.

// Ensure costBreakdown is initialized if not already (e.g., if this script is loaded separately)
if (typeof costBreakdown === 'undefined') {
    costBreakdown = {
        materials: [],
        labor: [],
        energy: [],
        equipment: [],
        totals: {
            materialTotal: 0,
            laborTotal: 0,
            energyTotal: 0,
            equipmentTotal: 0,
            suggestedCost: 0
        }
    };
}

// Add cost item
function addCostItem(type) {
    document.getElementById('costForm').reset();
    document.getElementById('costItemId').value = ''; // Clear item ID for add mode
    document.getElementById('costType').value = type;
    document.getElementById('costModalTitle').textContent = 'Add ' + capitalizeFirstLetter(type) + ' Cost';

    if (type === 'materials') {
        document.getElementById('materialNameField').classList.remove('hidden');
        document.getElementById('descriptionField').classList.add('hidden');
    } else {
        document.getElementById('materialNameField').classList.add('hidden');
        document.getElementById('descriptionField').classList.remove('hidden');
    }
    document.getElementById('costModal').classList.add('show');
}

// Edit cost item
function editCostItem(type, id) {
    document.getElementById('costForm').reset();
    document.getElementById('costItemId').value = id;
    document.getElementById('costType').value = type;
    document.getElementById('costModalTitle').textContent = 'Edit ' + capitalizeFirstLetter(type) + ' Cost';

    let itemToEdit;
    if (type === 'materials') {
        document.getElementById('materialNameField').classList.remove('hidden');
        document.getElementById('descriptionField').classList.add('hidden');
        itemToEdit = costBreakdown.materials.find(item => item.id == id);
        if (itemToEdit) {
            document.getElementById('materialName').value = itemToEdit.name;
            document.getElementById('itemCost').value = itemToEdit.cost;
        }
    } else {
        document.getElementById('materialNameField').classList.add('hidden');
        document.getElementById('descriptionField').classList.remove('hidden');
        if (type === 'labor') itemToEdit = costBreakdown.labor.find(item => item.id == id);
        else if (type === 'energy') itemToEdit = costBreakdown.energy.find(item => item.id == id);
        else if (type === 'equipment') itemToEdit = costBreakdown.equipment.find(item => item.id == id);
        
        if (itemToEdit) {
            document.getElementById('itemDescription').value = itemToEdit.description;
            document.getElementById('itemCost').value = itemToEdit.cost;
        }
    }
    document.getElementById('costModal').classList.add('show');
}

// Save cost item (Add or Update)
function saveCostItem() {
    const id = document.getElementById('costItemId').value;
    const type = document.getElementById('costType').value;
    const cost = document.getElementById('itemCost').value;

    const costSubmitText = document.getElementById('costSubmitText');
    const costSubmitSpinner = document.getElementById('costSubmitSpinner');

    costSubmitText.classList.add('hidden');
    costSubmitSpinner.classList.remove('hidden');

    const data = {
        costType: type,
        cost: parseFloat(cost)
    };

    if (type === 'materials') {
        data.name = document.getElementById('materialName').value;
    } else {
        data.description = document.getElementById('itemDescription').value;
    }

    const isUpdate = id !== '';
    const method = isUpdate ? 'PUT' : 'POST';
    const url = isUpdate 
        ? `process_cost_breakdown.php?inventoryId=${currentItemId}&costType=${type}&id=${id}` 
        : `process_cost_breakdown.php?inventoryId=${currentItemId}`;

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => { throw err; });
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            closeCostModal();
            showSuccess(result.message || capitalizeFirstLetter(type) + ' cost saved successfully.');
            refreshCostBreakdown(); // Refresh the entire cost breakdown section
        } else {
            showError('Error: ' + (result.error || 'Failed to save cost item.'));
        }
    })
    .catch(error => {
        console.error('Error saving cost item:', error);
        showError('Failed to save cost item. ' + (error.error || error.message || 'Please try again.'));
    })
    .finally(() => {
        costSubmitText.classList.remove('hidden');
        costSubmitSpinner.classList.add('hidden');
    });
}

// Open delete confirmation modal for cost item
function deleteCostItem(type, id, name) {
    document.getElementById('deleteCostItemId').value = id;
    document.getElementById('deleteCostType').value = type;
    let typeDisplay = type.slice(0, -1); // "materials" -> "material"

    document.getElementById('deleteCostText').textContent = `Are you sure you want to delete the ${typeDisplay} "${name}"?`;
    document.getElementById('deleteCostModal').classList.add('show');
}

// Confirm and execute delete cost item
function confirmDeleteCostItem() {
    const id = document.getElementById('deleteCostItemId').value;
    const type = document.getElementById('deleteCostType').value;

    const deleteCostText = document.getElementById('deleteCostText'); // The button text span
    const deleteCostSpinner = document.getElementById('deleteCostSpinner');

    // Assuming the button text span is the one for the delete button itself
    const deleteButtonTextSpan = document.getElementById('confirmDeleteCostBtn').querySelector('span:not(.loading-spinner)');


    if (deleteButtonTextSpan) deleteButtonTextSpan.classList.add('hidden');
    deleteCostSpinner.classList.remove('hidden');

    const url = `process_cost_breakdown.php?inventoryId=${currentItemId}&costType=${type}&id=${id}`;

    fetch(url, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => { throw err; });
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            closeDeleteCostModal();
            showSuccess(result.message || capitalizeFirstLetter(type) + ' cost deleted successfully.');
            refreshCostBreakdown(); // Refresh the entire cost breakdown section
        } else {
            showError('Error: ' + (result.error || 'Failed to delete cost item.'));
        }
    })
    .catch(error => {
        console.error('Error deleting cost item:', error);
        showError('Failed to delete cost item. ' + (error.error || error.message || 'Please try again.'));
    })
    .finally(() => {
        if (deleteButtonTextSpan) deleteButtonTextSpan.classList.remove('hidden');
        deleteCostSpinner.classList.add('hidden');
    });
}


// Refresh cost breakdown display
function refreshCostBreakdown() {
    if (!currentItemId) return;

    fetch(`process_cost_breakdown.php?inventoryId=${currentItemId}&costType=all`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => { throw err; });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            costBreakdown = data.data; // Update global costBreakdown object
            renderCostList('materials', costBreakdown.materials);
            renderCostList('labor', costBreakdown.labor);
            renderCostList('energy', costBreakdown.energy);
            renderCostList('equipment', costBreakdown.equipment);
            updateTotalsDisplay();
        } else {
            showError('Error refreshing cost breakdown: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error refreshing cost breakdown:', error);
        showError('Failed to refresh cost breakdown. ' + (error.error || error.message || ''));
    });
}

// Render a specific cost list
function renderCostList(type, items) {
    const listElement = document.getElementById(type + 'List');
    if (!listElement) return;

    listElement.innerHTML = ''; // Clear current items

    if (items && items.length > 0) {
        items.forEach(item => {
            const itemName = type === 'materials' ? item.name : item.description;
            const itemDiv = document.createElement('div');
            itemDiv.className = 'cost-item';
            itemDiv.dataset.id = item.id;
            itemDiv.innerHTML = `
                <span class="cost-item-name">${escapeHtml(itemName)}</span>
                <div class="flex items-center">
                    <span class="cost-item-value">$${parseFloat(item.cost).toFixed(2)}</span>
                    <div class="cost-item-actions">
                        <button type="button" class="text-green-600 hover:text-green-800 mr-2" onclick="editCostItem('${type}', ${item.id})" title="Edit Cost">✏️</button>
                        <button type="button" class="text-red-600 hover:text-red-800" onclick="deleteCostItem('${type}', ${item.id}, '${escapeHtml(itemName)}')" title="Delete Cost">🗑️</button>
                    </div>
                </div>
            `;
            listElement.appendChild(itemDiv);
        });
    } else {
        const noDataDiv = document.createElement('div');
        noDataDiv.className = 'text-gray-500 text-sm italic';
        noDataDiv.textContent = 'No ' + type + ' data available';
        listElement.appendChild(noDataDiv);
    }
}

// Update totals display
function updateTotalsDisplay() {
    if (!costBreakdown || !costBreakdown.totals) return;

    document.getElementById('materialsTotalDisplay').textContent = '$' + parseFloat(costBreakdown.totals.materialTotal || 0).toFixed(2);
    document.getElementById('laborTotalDisplay').textContent = '$' + parseFloat(costBreakdown.totals.laborTotal || 0).toFixed(2);
    document.getElementById('energyTotalDisplay').textContent = '$' + parseFloat(costBreakdown.totals.energyTotal || 0).toFixed(2);
    document.getElementById('equipmentTotalDisplay').textContent = '$' + parseFloat(costBreakdown.totals.equipmentTotal || 0).toFixed(2);
    document.getElementById('suggestedCostDisplay').textContent = '$' + parseFloat(costBreakdown.totals.suggestedCost || 0).toFixed(2);
    
    // Update the "(Suggested: $X.XX)" text next to cost price input if it exists
    const costPriceInput = document.getElementById('costPrice');
    if (costPriceInput) {
        let suggestedSpan = costPriceInput.parentElement.querySelector('.suggested-cost');
        if (!suggestedSpan) {
            suggestedSpan = document.createElement('span');
            suggestedSpan.className = 'suggested-cost ml-2';
            costPriceInput.parentElement.appendChild(suggestedSpan);
        }
        if (costBreakdown.totals.suggestedCost > 0) {
            suggestedSpan.textContent = `(Suggested: $${parseFloat(costBreakdown.totals.suggestedCost).toFixed(2)})`;
            suggestedSpan.classList.remove('hidden');
        } else {
            suggestedSpan.classList.add('hidden');
        }
    }
}

// Close cost modal
function closeCostModal() {
    document.getElementById('costModal').classList.remove('show');
}

// Close delete cost modal
function closeDeleteCostModal() {
    document.getElementById('deleteCostModal').classList.remove('show');
}

// Use suggested cost for the main item form
function useSuggestedCost() {
    if (costBreakdown && costBreakdown.totals) {
        const costPriceField = document.getElementById('costPrice');
        costPriceField.value = parseFloat(costBreakdown.totals.suggestedCost || 0).toFixed(2);
        costPriceField.style.backgroundColor = '#c6f6d5'; // Highlight change
        setTimeout(() => {
            costPriceField.style.backgroundColor = '';
        }, 1000);
    }
}

// Capitalize first letter helper
function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// Escape HTML to prevent XSS
function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') {
        return unsafe;
    }
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}


// Initialize event listeners for cost management (if not already done in admin_inventory.php)
// This assumes the modals and forms are part of the main document.
document.addEventListener('DOMContentLoaded', function() {
    const costForm = document.getElementById('costForm');
    if (costForm) {
        costForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveCostItem();
        });
    }

    const confirmDeleteCostBtn = document.getElementById('confirmDeleteCostBtn');
    if (confirmDeleteCostBtn) {
        confirmDeleteCostBtn.addEventListener('click', function() {
            confirmDeleteCostItem();
        });
    }
    
    // Initial call to render costs if data is already loaded (e.g. on page load for an edited item)
    // This might be redundant if admin_inventory.php already renders PHP-side,
    // but good for ensuring JS consistency if data is fetched/updated client-side.
    if (typeof currentItemId !== 'undefined' && currentItemId) {
         // The PHP part of admin_inventory.php already renders the initial state.
         // This refreshCostBreakdown() call could be made if we want JS to fully take over rendering after load.
         // For now, PHP renders initial, JS updates on action.
         // refreshCostBreakdown(); 
    }
});

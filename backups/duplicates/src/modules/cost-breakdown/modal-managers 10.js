/**
 * Cost Breakdown Modal Managers
 * Handles modal functionality for cost breakdown operations
 */

export class CostBreakdownModalManagers {
  constructor() {
    this.currentItemId = null;
  }

  /**
   * Set the current item ID
   * @param {string} itemId - The inventory item ID
   */
  setCurrentItemId(itemId) {
    this.currentItemId = itemId;
  }

  /**
   * Open a modal by ID
   * @param {string} modalId - The modal element ID
   */
  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('show');
    }
  }

  /**
   * Close a modal by ID
   * @param {string} modalId - The modal element ID
   */
  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('show');
    }
  }

  /**
   * Open add modal for a specific cost type
   * @param {string} costType - The cost type (material/labor/energy)
   */
  openAddModal(costType) {
    this.resetForm(costType);
    const modalTitle = document.getElementById(`${costType}ModalTitle`);
    if (modalTitle) {
      modalTitle.textContent = `Add ${this.capitalizeFirstLetter(costType)}`;
    }

    const inventoryInput = document.getElementById(`${costType}InventoryId`);
    if (inventoryInput) {
      inventoryInput.value = this.currentItemId || '';
    }

    const idInput = document.getElementById(`${costType}Id`);
    if (idInput) {
      idInput.value = '';
    }

    this.openModal(`${costType}Modal`);
  }

  /**
   * Open edit modal for a specific cost item
   * @param {string} costType - The cost type (material/labor/energy)
   * @param {string} itemId - The item ID to edit
   * @param {Object} costBreakdown - The current cost breakdown data
   */
  openEditModal(costType, itemId, costBreakdown) {
    this.resetForm(costType);
    const modalTitle = document.getElementById(`${costType}ModalTitle`);
    if (modalTitle) {
      modalTitle.textContent = `Edit ${this.capitalizeFirstLetter(costType)}`;
    }

    const idInput = document.getElementById(`${costType}Id`);
    const inventoryInput = document.getElementById(`${costType}InventoryId`);
    if (idInput) idInput.value = itemId;
    if (inventoryInput) inventoryInput.value = this.currentItemId || '';

    let item;
    if (costType === 'material') {
      item = (costBreakdown.materials || []).find((m) => m.id == itemId);
      if (item) {
        const nameInput = document.getElementById('materialName');
        const costInput = document.getElementById('materialCost');
        if (nameInput) nameInput.value = item.name;
        if (costInput) costInput.value = parseFloat(item.cost).toFixed(2);
      }
    } else if (costType === 'labor') {
      item = (costBreakdown.labor || []).find((l) => l.id == itemId);
      if (item) {
        const descInput = document.getElementById('laborDescription');
        const costInput = document.getElementById('laborCost');
        if (descInput) descInput.value = item.description;
        if (costInput) costInput.value = parseFloat(item.cost).toFixed(2);
      }
    } else if (costType === 'energy') {
      item = (costBreakdown.energy || []).find((e) => e.id == itemId);
      if (item) {
        const descInput = document.getElementById('energyDescription');
        const costInput = document.getElementById('energyCost');
        if (descInput) descInput.value = item.description;
        if (costInput) costInput.value = parseFloat(item.cost).toFixed(2);
      }
    }

    this.openModal(`${costType}Modal`);
  }

  /**
   * Open delete confirmation modal
   * @param {string} costType - The cost type (material/labor/energy)
   * @param {string} itemId - The item ID to delete
   * @param {string} itemName - The name of the item to delete
   */
  openDeleteModal(costType, itemId, itemName) {
    const idInput = document.getElementById('deleteItemId');
    const typeInput = document.getElementById('deleteItemType');
    if (idInput) idInput.value = itemId;
    if (typeInput) typeInput.value = costType;

    let itemTypeDisplay = costType;
    if (costType === 'material') itemTypeDisplay = 'material';
    else if (costType === 'labor') itemTypeDisplay = 'labor cost';
    else if (costType === 'energy') itemTypeDisplay = 'energy cost';

    const confirmText = document.getElementById('deleteConfirmText');
    if (confirmText) {
      confirmText.textContent = `Are you sure you want to delete the ${itemTypeDisplay} "${itemName}"?`;
    }

    this.openModal('deleteModal');
  }

  /**
   * Open update cost price modal
   * @param {Object} costBreakdown - The current cost breakdown data
   */
  openUpdateCostModal(costBreakdown) {
    const currentCostDisplay = document.getElementById('currentCostDisplay');
    const currentCost = (currentCostDisplay?.textContent || '').replace('$', '');
    const suggestedCost = (costBreakdown.totals?.suggestedCost || 0).toFixed(2);

    const currentInput = document.getElementById('updateCurrentCost');
    const suggestedInput = document.getElementById('updateSuggestedCost');
    if (currentInput) currentInput.textContent = `$${parseFloat(currentCost || '0').toFixed(2)}`;
    if (suggestedInput) suggestedInput.textContent = `$${suggestedCost}`;

    this.openModal('updateCostModal');
  }

  /**
   * Reset form inputs for a cost type
   * @param {string} costType - The cost type (material/labor/energy)
   */
  resetForm(costType) {
    if (costType === 'material') {
      const nameInput = document.getElementById('materialName');
      const costInput = document.getElementById('materialCost');
      if (nameInput) nameInput.value = '';
      if (costInput) costInput.value = '';
    } else if (costType === 'labor') {
      const descInput = document.getElementById('laborDescription');
      const costInput = document.getElementById('laborCost');
      if (descInput) descInput.value = '';
      if (costInput) costInput.value = '';
    } else if (costType === 'energy') {
      const descInput = document.getElementById('energyDescription');
      const costInput = document.getElementById('energyCost');
      if (descInput) descInput.value = '';
      if (costInput) costInput.value = '';
    }
  }

  /**
   * Show loading state for a form
   * @param {string} formType - The form type
   */
  showFormLoading(formType) {
    const submitText = document.getElementById(`${formType}SubmitText`);
    const submitSpinner = document.getElementById(`${formType}SubmitSpinner`);
    if (submitText) submitText.classList.add('hidden');
    if (submitSpinner) submitSpinner.classList.remove('hidden');
  }

  /**
   * Hide loading state for a form
   * @param {string} formType - The form type
   */
  hideFormLoading(formType) {
    const submitText = document.getElementById(`${formType}SubmitText`);
    const submitSpinner = document.getElementById(`${formType}SubmitSpinner`);
    if (submitText) submitText.classList.remove('hidden');
    if (submitSpinner) submitSpinner.classList.add('hidden');
  }

  /**
   * Capitalize first letter of a string
   * @param {string} str - The string to capitalize
   * @returns {string} Capitalized string
   */
  capitalizeFirstLetter(str) {
    return (str || '').charAt(0).toUpperCase() + (str || '').slice(1);
  }
}

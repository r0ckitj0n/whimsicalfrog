/**
 * Cost Breakdown Form Handlers
 * Handles form processing and validation for cost breakdown operations
 */

export class CostBreakdownFormHandlers {
  constructor(apiHandlers, modalManagers, notificationManager) {
    this.apiHandlers = apiHandlers;
    this.modalManagers = modalManagers;
    this.notificationManager = notificationManager;
  }

  /**
   * Initialize form event handlers
   * @param {string} currentItemId - The current inventory item ID
   */
  init(currentItemId) {
    this.modalManagers.setCurrentItemId(currentItemId);

    // Bind form submissions
    this.bindFormSubmissions();

    // Bind close modal handlers
    this.bindCloseModalHandlers();
  }

  /**
   * Bind form submission handlers
   */
  bindFormSubmissions() {
    const materialForm = document.getElementById('materialForm');
    if (materialForm) {
      materialForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveMaterial();
      });
    }

    const laborForm = document.getElementById('laborForm');
    if (laborForm) {
      laborForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveLabor();
      });
    }

    const energyForm = document.getElementById('energyForm');
    if (energyForm) {
      energyForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveEnergy();
      });
    }

    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener('click', () => {
        const itemId = (document.getElementById('deleteItemId') || {}).value;
        const itemType = (document.getElementById('deleteItemType') || {}).value;
        if (itemId && itemType) {
          this.deleteItem(itemId, itemType);
        }
      });
    }

    const confirmUpdateCostBtn = document.getElementById('confirmUpdateCostBtn');
    if (confirmUpdateCostBtn) {
      confirmUpdateCostBtn.addEventListener('click', () => {
        this.updateCostPrice();
      });
    }
  }

  /**
   * Bind close modal handlers
   */
  bindCloseModalHandlers() {
    const closeButtons = document.querySelectorAll('.close-modal');
    closeButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        const modalId = btn.getAttribute('data-modal');
        if (modalId) {
          this.modalManagers.closeModal(modalId);
        }
      });
    });
  }

  /**
   * Save material form
   */
  async saveMaterial() {
    const id = (document.getElementById('materialId') || {}).value || '';
    const inventoryId = (document.getElementById('materialInventoryId') || {}).value || this.modalManagers.currentItemId || '';
    const name = (document.getElementById('materialName') || {}).value || '';
    const cost = (document.getElementById('materialCost') || {}).value || '';

    // Validate inputs
    if (!name.trim()) {
      this.notificationManager.showError('Material name is required');
      return;
    }

    if (!cost || isNaN(parseFloat(cost)) || parseFloat(cost) < 0) {
      this.notificationManager.showError('Valid cost is required');
      return;
    }

    this.modalManagers.showFormLoading('material');

    try {
      const result = await this.apiHandlers.saveMaterial(inventoryId, { name, cost: parseFloat(cost) }, id);
      this.modalManagers.closeModal('materialModal');
      this.notificationManager.showSuccess(result.message || 'Material saved successfully');
      // Trigger reload of cost breakdown data
      this.dispatchCustomEvent('costBreakdown:reload', { inventoryId });
    } catch (error) {
      this.notificationManager.showError(error.message || 'Failed to save material');
    } finally {
      this.modalManagers.hideFormLoading('material');
    }
  }

  /**
   * Save labor form
   */
  async saveLabor() {
    const id = (document.getElementById('laborId') || {}).value || '';
    const inventoryId = (document.getElementById('laborInventoryId') || {}).value || this.modalManagers.currentItemId || '';
    const description = (document.getElementById('laborDescription') || {}).value || '';
    const cost = (document.getElementById('laborCost') || {}).value || '';

    // Validate inputs
    if (!description.trim()) {
      this.notificationManager.showError('Labor description is required');
      return;
    }

    if (!cost || isNaN(parseFloat(cost)) || parseFloat(cost) < 0) {
      this.notificationManager.showError('Valid cost is required');
      return;
    }

    this.modalManagers.showFormLoading('labor');

    try {
      const result = await this.apiHandlers.saveLabor(inventoryId, { description, cost: parseFloat(cost) }, id);
      this.modalManagers.closeModal('laborModal');
      this.notificationManager.showSuccess(result.message || 'Labor saved successfully');
      // Trigger reload of cost breakdown data
      this.dispatchCustomEvent('costBreakdown:reload', { inventoryId });
    } catch (error) {
      this.notificationManager.showError(error.message || 'Failed to save labor');
    } finally {
      this.modalManagers.hideFormLoading('labor');
    }
  }

  /**
   * Save energy form
   */
  async saveEnergy() {
    const id = (document.getElementById('energyId') || {}).value || '';
    const inventoryId = (document.getElementById('energyInventoryId') || {}).value || this.modalManagers.currentItemId || '';
    const description = (document.getElementById('energyDescription') || {}).value || '';
    const cost = (document.getElementById('energyCost') || {}).value || '';

    // Validate inputs
    if (!description.trim()) {
      this.notificationManager.showError('Energy description is required');
      return;
    }

    if (!cost || isNaN(parseFloat(cost)) || parseFloat(cost) < 0) {
      this.notificationManager.showError('Valid cost is required');
      return;
    }

    this.modalManagers.showFormLoading('energy');

    try {
      const result = await this.apiHandlers.saveEnergy(inventoryId, { description, cost: parseFloat(cost) }, id);
      this.modalManagers.closeModal('energyModal');
      this.notificationManager.showSuccess(result.message || 'Energy saved successfully');
      // Trigger reload of cost breakdown data
      this.dispatchCustomEvent('costBreakdown:reload', { inventoryId });
    } catch (error) {
      this.notificationManager.showError(error.message || 'Failed to save energy');
    } finally {
      this.modalManagers.hideFormLoading('energy');
    }
  }

  /**
   * Delete cost item
   * @param {string} itemId - The item ID to delete
   * @param {string} itemType - The item type (material/labor/energy)
   */
  async deleteItem(itemId, itemType) {
    const inventoryId = this.modalManagers.currentItemId;

    this.modalManagers.showFormLoading('delete');

    try {
      const result = await this.apiHandlers.deleteCostItem(inventoryId, itemType, itemId);
      this.modalManagers.closeModal('deleteModal');
      this.notificationManager.showSuccess(result.message || 'Item deleted successfully');
      // Trigger reload of cost breakdown data
      this.dispatchCustomEvent('costBreakdown:reload', { inventoryId });
    } catch (error) {
      this.notificationManager.showError(error.message || 'Failed to delete item');
    } finally {
      this.modalManagers.hideFormLoading('delete');
    }
  }

  /**
   * Update inventory cost price
   */
  async updateCostPrice() {
    const inventoryId = this.modalManagers.currentItemId;

    this.modalManagers.showFormLoading('updateCost');

    try {
      const currentCostDisplay = document.getElementById('currentCostDisplay');
      const currentCost = parseFloat((currentCostDisplay?.textContent || '').replace('$', '') || '0');

      // Get suggested cost from the API or current breakdown data
      // This would need to be passed in or fetched
      const suggestedCost = currentCost; // Placeholder - should be calculated

      const _result = await this.apiHandlers.updateInventoryCostPrice(inventoryId, suggestedCost);
      this.modalManagers.closeModal('updateCostModal');
      // Update display values
      const itemCostDisplay = document.getElementById('itemCostDisplay');
      const formattedCost = `$${suggestedCost.toFixed(2)}`;
      if (itemCostDisplay) itemCostDisplay.textContent = formattedCost;
      if (currentCostDisplay) currentCostDisplay.textContent = formattedCost;

      // Animate the change
      this.animateElement('itemCostDisplay');
      this.animateElement('currentCostDisplay');

      // Trigger reload of cost breakdown data
      this.dispatchCustomEvent('costBreakdown:reload', { inventoryId });
    } catch (error) {
      this.notificationManager.showError(error.message || 'Failed to update cost price');
    } finally {
      this.modalManagers.hideFormLoading('updateCost');
    }
  }

  /**
   * Animate element change
   * @param {string} elementId - The element ID to animate
   */
  animateElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;

    element.classList.remove('highlight-change');
    void element.offsetWidth; // Force reflow to restart animation
    element.classList.add('highlight-change');
  }

  /**
   * Dispatch custom event
   * @param {string} eventName - Event name
   * @param {Object} detail - Event detail data
   */
  dispatchCustomEvent(eventName, detail = {}) {
    const event = new CustomEvent(eventName, { detail });
    document.dispatchEvent(event);
  }
}

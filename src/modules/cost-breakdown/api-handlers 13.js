/**
 * Cost Breakdown API Handlers
 * Handles all API interactions for cost breakdown functionality
 */

export class CostBreakdownApiHandlers {
  constructor() {
    this.baseUrl = '/functions/process_cost_breakdown.php';
  }

  /**
   * Load cost breakdown data for an item
   * @param {string} itemId - The inventory item ID
   * @returns {Promise<Object>} Cost breakdown data
   */
  async loadCostBreakdown(itemId) {
    try {
      const response = await fetch(`${this.baseUrl}?inventoryId=${encodeURIComponent(itemId)}&costType=all`);
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      const data = await response.json();
      if (!data.success) {
        throw new Error('Failed to load cost breakdown');
      }
      return data.data || {
        materials: [],
        labor: [],
        energy: [],
        totals: { materialTotal: 0, laborTotal: 0, energyTotal: 0, suggestedCost: 0 }
      };
    } catch (error) {
      console.error('Error loading cost breakdown:', error);
      throw error;
    }
  }

  /**
   * Save material cost
   * @param {string} inventoryId - The inventory item ID
   * @param {Object} materialData - Material data {name, cost}
   * @param {string} id - Material ID (for updates)
   * @returns {Promise<Object>} Save result
   */
  async saveMaterial(inventoryId, materialData, id = '') {
    const isEdit = id !== '';
    const method = isEdit ? 'PUT' : 'POST';
    const url = isEdit
      ? `${this.baseUrl}?inventoryId=${encodeURIComponent(inventoryId)}&costType=materials&id=${encodeURIComponent(id)}`
      : `${this.baseUrl}?inventoryId=${encodeURIComponent(inventoryId)}`;

    try {
      const response = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ costType: 'materials', ...materialData })
      });

      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.error || 'Failed to save material');
      }

      return result;
    } catch (error) {
      console.error('Error saving material:', error);
      throw error;
    }
  }

  /**
   * Save labor cost
   * @param {string} inventoryId - The inventory item ID
   * @param {Object} laborData - Labor data {description, cost}
   * @param {string} id - Labor ID (for updates)
   * @returns {Promise<Object>} Save result
   */
  async saveLabor(inventoryId, laborData, id = '') {
    const isEdit = id !== '';
    const method = isEdit ? 'PUT' : 'POST';
    const url = isEdit
      ? `${this.baseUrl}?inventoryId=${encodeURIComponent(inventoryId)}&costType=labor&id=${encodeURIComponent(id)}`
      : `${this.baseUrl}?inventoryId=${encodeURIComponent(inventoryId)}`;

    try {
      const response = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ costType: 'labor', ...laborData })
      });

      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.error || 'Failed to save labor');
      }

      return result;
    } catch (error) {
      console.error('Error saving labor:', error);
      throw error;
    }
  }

  /**
   * Save energy cost
   * @param {string} inventoryId - The inventory item ID
   * @param {Object} energyData - Energy data {description, cost}
   * @param {string} id - Energy ID (for updates)
   * @returns {Promise<Object>} Save result
   */
  async saveEnergy(inventoryId, energyData, id = '') {
    const isEdit = id !== '';
    const method = isEdit ? 'PUT' : 'POST';
    const url = isEdit
      ? `${this.baseUrl}?inventoryId=${encodeURIComponent(inventoryId)}&costType=energy&id=${encodeURIComponent(id)}`
      : `${this.baseUrl}?inventoryId=${encodeURIComponent(inventoryId)}`;

    try {
      const response = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ costType: 'energy', ...energyData })
      });

      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.error || 'Failed to save energy');
      }

      return result;
    } catch (error) {
      console.error('Error saving energy:', error);
      throw error;
    }
  }

  /**
   * Delete cost item
   * @param {string} inventoryId - The inventory item ID
   * @param {string} type - Cost type (material/labor/energy)
   * @param {string} id - Item ID to delete
   * @returns {Promise<Object>} Delete result
   */
  async deleteCostItem(inventoryId, type, id) {
    const url = `${this.baseUrl}?inventoryId=${encodeURIComponent(inventoryId)}&costType=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`;

    try {
      const response = await fetch(url, { method: 'DELETE' });
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.error || 'Failed to delete item');
      }

      return result;
    } catch (error) {
      console.error('Error deleting cost item:', error);
      throw error;
    }
  }

  /**
   * Update inventory cost price
   * @param {string} inventoryId - The inventory item ID
   * @param {number} costPrice - New cost price
   * @returns {Promise<Object>} Update result
   */
  async updateInventoryCostPrice(inventoryId, costPrice) {
    try {
      const response = await fetch('/functions/process_inventory_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: inventoryId, costPrice })
      });

      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.error || 'Failed to update cost price');
      }

      return result;
    } catch (error) {
      console.error('Error updating cost price:', error);
      throw error;
    }
  }
}

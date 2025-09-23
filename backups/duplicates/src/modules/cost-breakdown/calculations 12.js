/**
 * Cost Breakdown Calculations
 * Handles business logic and calculations for cost breakdown operations
 */

export class CostBreakdownCalculations {
  constructor() {
    this.costBreakdown = {
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
  }

  /**
   * Set cost breakdown data
   * @param {Object} data - Cost breakdown data
   */
  setCostBreakdownData(data) {
    this.costBreakdown = {
      materials: data.materials || [],
      labor: data.labor || [],
      energy: data.energy || [],
      totals: data.totals || {
        materialTotal: 0,
        laborTotal: 0,
        energyTotal: 0,
        suggestedCost: 0
      }
    };
  }

  /**
   * Get cost breakdown data
   * @returns {Object} Current cost breakdown data
   */
  getCostBreakdownData() {
    return this.costBreakdown;
  }

  /**
   * Calculate totals from current data
   * @returns {Object} Calculated totals
   */
  calculateTotals() {
    const materialTotal = this.calculateCategoryTotal(this.costBreakdown.materials);
    const laborTotal = this.calculateCategoryTotal(this.costBreakdown.labor);
    const energyTotal = this.calculateCategoryTotal(this.costBreakdown.energy);

    const suggestedCost = this.calculateSuggestedCost(materialTotal, laborTotal, energyTotal);

    this.costBreakdown.totals = {
      materialTotal,
      laborTotal,
      energyTotal,
      suggestedCost
    };

    return this.costBreakdown.totals;
  }

  /**
   * Calculate total for a category
   * @param {Array} items - Array of cost items
   * @returns {number} Category total
   */
  calculateCategoryTotal(items) {
    return items.reduce((total, item) => {
      const cost = parseFloat(item.cost) || 0;
      return total + cost;
    }, 0);
  }

  /**
   * Calculate suggested cost price
   * @param {number} materialTotal - Total material cost
   * @param {number} laborTotal - Total labor cost
   * @param {number} energyTotal - Total energy cost
   * @returns {number} Suggested cost price
   */
  calculateSuggestedCost(materialTotal, laborTotal, energyTotal) {
    const totalCost = materialTotal + laborTotal + energyTotal;

    // Apply markup for profit margin (example: 40% markup)
    const markup = 1.4; // 40% markup
    const suggestedCost = totalCost * markup;

    return Math.round(suggestedCost * 100) / 100; // Round to 2 decimal places
  }

  /**
   * Add cost item to category
   * @param {string} category - Category (materials/labor/energy)
   * @param {Object} item - Cost item to add
   */
  addCostItem(category, item) {
    if (!this.costBreakdown[category]) {
      this.costBreakdown[category] = [];
    }

    // Generate ID if not provided
    if (!item.id) {
      item.id = this.generateId(category);
    }

    this.costBreakdown[category].push(item);
    this.calculateTotals();
  }

  /**
   * Update cost item in category
   * @param {string} category - Category (materials/labor/energy)
   * @param {string} itemId - Item ID to update
   * @param {Object} updatedItem - Updated cost item
   */
  updateCostItem(category, itemId, updatedItem) {
    if (!this.costBreakdown[category]) return false;

    const itemIndex = this.costBreakdown[category].findIndex(item => item.id == itemId);
    if (itemIndex === -1) return false;

    this.costBreakdown[category][itemIndex] = { ...updatedItem, id: itemId };
    this.calculateTotals();
    return true;
  }

  /**
   * Remove cost item from category
   * @param {string} category - Category (materials/labor/energy)
   * @param {string} itemId - Item ID to remove
   * @returns {boolean} Success status
   */
  removeCostItem(category, itemId) {
    if (!this.costBreakdown[category]) return false;

    const itemIndex = this.costBreakdown[category].findIndex(item => item.id == itemId);
    if (itemIndex === -1) return false;

    this.costBreakdown[category].splice(itemIndex, 1);
    this.calculateTotals();
    return true;
  }

  /**
   * Generate unique ID for cost item
   * @param {string} category - Category prefix
   * @returns {string} Generated ID
   */
  generateId(category) {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 1000);
    const prefix = category.substring(0, 3).toUpperCase();
    return `${prefix}_${timestamp}_${random}`;
  }

  /**
   * Get cost item by ID and category
   * @param {string} category - Category (materials/labor/energy)
   * @param {string} itemId - Item ID
   * @returns {Object|null} Cost item or null if not found
   */
  getCostItem(category, itemId) {
    if (!this.costBreakdown[category]) return null;

    return this.costBreakdown[category].find(item => item.id == itemId) || null;
  }

  /**
   * Get all cost items for a category
   * @param {string} category - Category (materials/labor/energy)
   * @returns {Array} Cost items for category
   */
  getCostItems(category) {
    return this.costBreakdown[category] || [];
  }

  /**
   * Calculate profit margin
   * @param {number} sellingPrice - Item selling price
   * @param {number} totalCost - Total cost
   * @returns {Object} Profit margin data
   */
  calculateProfitMargin(sellingPrice, totalCost) {
    if (totalCost === 0) return { margin: 0, percentage: 0 };

    const profit = sellingPrice - totalCost;
    const margin = profit / sellingPrice;
    const percentage = margin * 100;

    return {
      profit: Math.round(profit * 100) / 100,
      margin: Math.round(margin * 10000) / 100, // Percentage with 2 decimal places
      percentage: Math.round(percentage * 100) / 100
    };
  }

  /**
   * Calculate cost breakdown summary
   * @returns {Object} Summary data
   */
  getSummary() {
    const totals = this.costBreakdown.totals;
    const totalItems = this.costBreakdown.materials.length +
                      this.costBreakdown.labor.length +
                      this.costBreakdown.energy.length;

    return {
      totalItems,
      materialItems: this.costBreakdown.materials.length,
      laborItems: this.costBreakdown.labor.length,
      energyItems: this.costBreakdown.energy.length,
      totals,
      breakdown: {
        materials: {
          items: this.costBreakdown.materials.length,
          total: totals.materialTotal,
          percentage: this.calculatePercentage(totals.materialTotal, totals.suggestedCost)
        },
        labor: {
          items: this.costBreakdown.labor.length,
          total: totals.laborTotal,
          percentage: this.calculatePercentage(totals.laborTotal, totals.suggestedCost)
        },
        energy: {
          items: this.costBreakdown.energy.length,
          total: totals.energyTotal,
          percentage: this.calculatePercentage(totals.energyTotal, totals.suggestedCost)
        }
      }
    };
  }

  /**
   * Calculate percentage of total
   * @param {number} value - Value to calculate percentage for
   * @param {number} total - Total value
   * @returns {number} Percentage
   */
  calculatePercentage(value, total) {
    if (total === 0) return 0;
    return Math.round((value / total) * 100);
  }

  /**
   * Validate cost item data
   * @param {Object} item - Cost item to validate
   * @param {string} category - Category (materials/labor/energy)
   * @returns {Object} Validation result {valid: boolean, errors: Array}
   */
  validateCostItem(item, category) {
    const errors = [];

    if (category === 'material') {
      if (!item.name || typeof item.name !== 'string' || item.name.trim().length === 0) {
        errors.push('Material name is required');
      }
    } else {
      if (!item.description || typeof item.description !== 'string' || item.description.trim().length === 0) {
        errors.push(`${this.capitalizeFirstLetter(category)} description is required`);
      }
    }

    if (!item.cost || isNaN(parseFloat(item.cost)) || parseFloat(item.cost) < 0) {
      errors.push('Valid cost is required');
    }

    return {
      valid: errors.length === 0,
      errors
    };
  }

  /**
   * Export cost breakdown data
   * @returns {Object} Export data
   */
  exportData() {
    return {
      materials: [...this.costBreakdown.materials],
      labor: [...this.costBreakdown.labor],
      energy: [...this.costBreakdown.energy],
      totals: { ...this.costBreakdown.totals },
      summary: this.getSummary(),
      exportDate: new Date().toISOString()
    };
  }

  /**
   * Import cost breakdown data
   * @param {Object} data - Data to import
   */
  importData(data) {
    if (data.materials) this.costBreakdown.materials = [...data.materials];
    if (data.labor) this.costBreakdown.labor = [...data.labor];
    if (data.energy) this.costBreakdown.energy = [...data.energy];
    if (data.totals) this.costBreakdown.totals = { ...data.totals };

    this.calculateTotals();
  }

  /**
   * Clear all cost breakdown data
   */
  clearData() {
    this.costBreakdown = {
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
  }

  /**
   * Capitalize first letter helper
   * @param {string} str - String to capitalize
   * @returns {string} Capitalized string
   */
  capitalizeFirstLetter(str) {
    return (str || '').charAt(0).toUpperCase() + (str || '').slice(1);
  }
}

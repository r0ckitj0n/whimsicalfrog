/**
 * Cost Breakdown Coordinator
 * Main coordinator that ties together all cost breakdown modules
 */

// import { CostBreakdownApiHandlers } from './cost-breakdown/api-handlers.js';
// import CostBreakdownModalManagers from './cost-breakdown/modal-managers.js';
// import CostBreakdownFormHandlers from './cost-breakdown/form-handlers.js';
// import { CostBreakdownCalculations } from './calculations.js';

export class CostBreakdownCoordinator {
  constructor() {
    this.currentItemId = null;
    this.costBreakdown = {
      materials: [],
      labor: [],
      energy: [],
      totals: { materialTotal: 0, laborTotal: 0, energyTotal: 0, suggestedCost: 0 }
    };

    // Initialize modules
    // this.apiHandlers = new CostBreakdownApiHandlers();
    // this.modalManagers = new CostBreakdownModalManagers();
    // this.calculations = new CostBreakdownCalculations();
    // this.notificationManager = this.createNotificationManager();
    // this.formHandlers = new CostBreakdownFormHandlers(
    //   this.apiHandlers,
    //   this.modalManagers,
    //   this.notificationManager
    // );
  }

  /**
   * Initialize the cost breakdown system
   * @param {string} itemId - Optional item ID to load immediately
   */
  init(itemId = null) {
    // Set current item ID
    this.currentItemId = itemId || this.getCurrentItemIdFromUrl();

    // Only run on the cost breakdown page
    if (!this.isCostBreakdownPage()) {
      return;
    }

    // Bind events
    this.bindEvents();

    // Initialize form handlers
    this.formHandlers.init(this.currentItemId);

    // Load cost breakdown if we have an item ID
    if (this.currentItemId) {
      this.loadCostBreakdown(this.currentItemId);
    }

    console.log('[CostBreakdownCoordinator] Initialized');
    window.CostBreakdownCoordinator = this; // Expose for debugging
  }

  /**
   * Check if current page is the cost breakdown page
   * @returns {boolean} True if on cost breakdown page
   */
  isCostBreakdownPage() {
    const container = document.getElementById('costBreakdownContainer');
    const page = document.body.dataset?.page || '';
    const path = document.body.dataset?.path || window.location.pathname;

    return !!container ||
           /cost_breakdown_manager\.php$/i.test(path) ||
           /admin\/cost-breakdown-manager/i.test(page);
  }

  /**
   * Get current item ID from URL parameters
   * @returns {string} Item ID from URL
   */
  getCurrentItemIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('item') || '';
  }

  /**
   * Bind all event handlers
   */
  bindEvents() {
    // Bind load item button
    const loadItemBtn = document.getElementById('loadItemBtn');
    if (loadItemBtn) {
      loadItemBtn.addEventListener('click', () => {
        const selectedItemId = (document.getElementById('itemSelector') || {}).value;
        if (selectedItemId) {
          this.loadItem(selectedItemId);
        }
      });
    }

    // Bind add buttons
    const addMaterialBtn = document.getElementById('addMaterialBtn');
    if (addMaterialBtn) {
      addMaterialBtn.addEventListener('click', () => {
        this.modalManagers.openAddModal('material');
      });
    }

    const addLaborBtn = document.getElementById('addLaborBtn');
    if (addLaborBtn) {
      addLaborBtn.addEventListener('click', () => {
        this.modalManagers.openAddModal('labor');
      });
    }

    const addEnergyBtn = document.getElementById('addEnergyBtn');
    if (addEnergyBtn) {
      addEnergyBtn.addEventListener('click', () => {
        this.modalManagers.openAddModal('energy');
      });
    }

    // Bind update cost button
    const updateCostBtn = document.getElementById('updateCostBtn');
    if (updateCostBtn) {
      updateCostBtn.addEventListener('click', () => {
        this.modalManagers.openUpdateCostModal(this.calculations.getCostBreakdownData());
      });
    }

    // Bind delegated edit/delete handlers
    this.bindDelegatedHandlers();

    // Listen for custom events
    this.bindCustomEvents();
  }

  /**
   * Bind delegated event handlers for edit/delete buttons
   */
  bindDelegatedHandlers() {
    const lists = ['#materialsList', '#laborList', '#energyList'];
    lists.forEach((selector) => {
      const root = document.querySelector(selector);
      if (!root) return;

      root.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;

        const action = btn.getAttribute('data-action');
        if (!action) return;

        try {
          const paramsAttr = btn.getAttribute('data-params');
          const params = paramsAttr ? JSON.parse(paramsAttr) : {};

          if (action === 'openEditModal') {
            this.modalManagers.openEditModal(
              params.type,
              params.id,
              this.calculations.getCostBreakdownData()
            );
          } else if (action === 'openDeleteModal') {
            const item = this.calculations.getCostItem(params.type, params.id);
            if (item) {
              this.modalManagers.openDeleteModal(
                params.type,
                params.id,
                params.type === 'material' ? item.name : item.description
              );
            }
          }
        } catch (err) {
          console.error('[CostBreakdownCoordinator] Action error:', err);
          this.notificationManager.showError('Invalid action parameters');
        }
      });
    });
  }

  /**
   * Bind custom event listeners
   */
  bindCustomEvents() {
    document.addEventListener('costBreakdown:reload', (e) => {
      const inventoryId = e.detail?.inventoryId || this.currentItemId;
      if (inventoryId) {
        this.loadCostBreakdown(inventoryId);
      }
    });
  }

  /**
   * Load cost breakdown for an item
   * @param {string} itemId - The inventory item ID
   */
  async loadCostBreakdown(itemId) {
    this.showLoading();

    try {
      const data = await this.apiHandlers.loadCostBreakdown(itemId);
      this.calculations.setCostBreakdownData(data);
      this.displayCostBreakdown();
      this.currentItemId = itemId;
    } catch (error) {
      this.notificationManager.showError('Failed to load cost breakdown. Please try again.');
    } finally {
      this.hideLoading();
    }
  }

  /**
   * Load a different item
   * @param {string} itemId - The inventory item ID to load
   */
  loadItem(itemId) {
    const url = new URL(window.location.href);
    url.searchParams.set('item', itemId);
    window.location.href = url.toString();
  }

  /**
   * Display cost breakdown in the UI
   */
  displayCostBreakdown() {
    const data = this.calculations.getCostBreakdownData();

    // Display materials
    this.displayCostItems('#materialsList', data.materials, '#noMaterialsMsg');

    // Display labor
    this.displayCostItems('#laborList', data.labor, '#noLaborMsg');

    // Display energy
    this.displayCostItems('#energyList', data.energy, '#noEnergyMsg');

    // Display totals
    this.displayTotals(data.totals);

    // Animate changes
    ['#materialsTotalDisplay', '#laborTotalDisplay', '#energyTotalDisplay', '#suggestedCostDisplay']
      .forEach((selector) => {
        const elementId = selector.replace('#', '');
        this.animateElement(elementId);
      });
  }

  /**
   * Display cost items in a list
   * @param {string} listSelector - CSS selector for the list
   * @param {Array} items - Array of cost items
   * @param {string} noItemsSelector - CSS selector for no items message
   */
  displayCostItems(listSelector, items, noItemsSelector) {
    const list = document.querySelector(listSelector);
    if (!list) return;

    list.innerHTML = '';

    if (items.length > 0) {
      document.querySelector(noItemsSelector)?.classList.add('hidden');

      items.forEach((item) => {
        const div = document.createElement('div');
        div.className = 'py-3 flex justify-between items-center';
        div.innerHTML = `
          <div>
            <span class="font-medium">${this.escapeHtml(
              listSelector.includes('materials') ? item.name : item.description
            )}</span>
          </div>
          <div class="flex items-center">
            <span class="font-semibold">$${parseFloat(item.cost).toFixed(2)}</span>
            <button class="admin-action-button btn btn-xs btn-icon btn-icon--edit" data-action="openEditModal" aria-label="Edit" title="Edit"
                    data-params='${JSON.stringify({
                      type: this.getCostTypeFromSelector(listSelector),
                      id: item.id
                    })}'></button>
            <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="openDeleteModal" aria-label="Delete" title="Delete"
                    data-params='${JSON.stringify({
                      type: this.getCostTypeFromSelector(listSelector),
                      id: item.id,
                      name: listSelector.includes('materials') ? item.name : item.description
                    })}'></button>
          </div>`;
        list.appendChild(div);
      });
    } else {
      document.querySelector(noItemsSelector)?.classList.remove('hidden');
    }
  }

  /**
   * Display totals in the UI
   * @param {Object} totals - Totals object
   */
  displayTotals(totals) {
    const setText = (selector, value) => {
      const el = document.querySelector(selector);
      if (el) el.textContent = value;
    };

    setText('#materialsTotalDisplay', `$${totals.materialTotal.toFixed(2)}`);
    setText('#laborTotalDisplay', `$${totals.laborTotal.toFixed(2)}`);
    setText('#energyTotalDisplay', `$${totals.energyTotal.toFixed(2)}`);
    setText('#suggestedCostDisplay', `$${totals.suggestedCost.toFixed(2)}`);

    // Update current cost display
    const currentCostDisplay = document.querySelector('#currentCostDisplay');
    const itemCostDisplay = document.querySelector('#itemCostDisplay');
    if (currentCostDisplay && itemCostDisplay) {
      const currentCost = (itemCostDisplay.textContent || '').replace('$', '');
      currentCostDisplay.textContent = `$${parseFloat(currentCost || '0').toFixed(2)}`;
    }
  }

  /**
   * Get cost type from selector
   * @param {string} selector - CSS selector
   * @returns {string} Cost type
   */
  getCostTypeFromSelector(selector) {
    if (selector.includes('materials')) return 'material';
    if (selector.includes('labor')) return 'labor';
    if (selector.includes('energy')) return 'energy';
    return '';
  }

  /**
   * Animate element change
   * @param {string} elementId - Element ID to animate
   */
  animateElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;

    element.classList.remove('highlight-change');
    void element.offsetWidth; // Force reflow
    element.classList.add('highlight-change');
  }

  /**
   * Show loading state
   */
  showLoading() {
    // Implementation for showing loading spinner/overlay
    console.log('[CostBreakdownCoordinator] Loading...');
  }

  /**
   * Hide loading state
   */
  hideLoading() {
    console.log('[CostBreakdownCoordinator] Loading complete');
  }

  /**
   * Create notification manager
   * @returns {Object} Notification manager
   */
  createNotificationManager() {
    return {
      showSuccess: (msg) => {
        if (typeof window.showSuccess === 'function') return window.showSuccess(msg);
        if (typeof window.showToast === 'function') return window.showToast(msg, 'success');
        console.log('[SUCCESS]', msg);
      },
      showError: (msg) => {
        if (typeof window.showError === 'function') return window.showError(msg);
        if (typeof window.showToast === 'function') return window.showToast(msg, 'error');
        console.error('[ERROR]', msg);
      }
    };
  }

  /**
   * Escape HTML for safe display
   * @param {string} unsafe - Unsafe HTML string
   * @returns {string} Escaped HTML string
   */
  escapeHtml(unsafe = '') {
    return String(unsafe)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
}

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    new CostBreakdownCoordinator().init();
  }, { once: true });
} else {
  new CostBreakdownCoordinator().init();
}

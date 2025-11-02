// Cost Breakdown Manager - Vite module
// Migrated from admin/cost_breakdown_manager.php inline script

import { ApiClient } from '../core/api-client.js';

(function () {
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const showSuccess = (msg) => {
    if (typeof window.showSuccess === 'function') return window.showSuccess(msg);
    if (typeof window.showToast === 'function') return window.showToast(msg, 'success');
    console.log('[SUCCESS]', msg);
  };
  const showError = (msg) => {
    if (typeof window.showError === 'function') return window.showError(msg);
    if (typeof window.showToast === 'function') return window.showToast(msg, 'error');
    console.error('[ERROR]', msg);
  };

  const CostBreakdownModule = {
    currentItemId: null,
    costBreakdown: {
      materials: [],
      labor: [],
      energy: [],
      totals: { materialTotal: 0, laborTotal: 0, energyTotal: 0, suggestedCost: 0 },
    },

    init() {
      // Only run on the cost breakdown page
      const container = qs('#costBreakdownContainer');
      const page = (document.body.dataset && document.body.dataset.page) || '';
      const path = (document.body.dataset && document.body.dataset.path) || window.location.pathname;
      const isCostBreakdownPage = !!container || /cost_breakdown_manager\.php$/i.test(path) || /admin\/cost-breakdown-manager/i.test(page);
      if (!isCostBreakdownPage) return;

      // Determine current item id from query string
      const params = new URLSearchParams(window.location.search);
      this.currentItemId = params.get('item') || '';

      this.bindEvents();

      if (this.currentItemId) {
        this.loadCostBreakdown(this.currentItemId);
      }

      console.log('[AdminCostBreakdown] Initialized');
      window.AdminCostBreakdownModule = this; // expose for debugging
    },

    bindEvents() {
      const loadItemBtn = qs('#loadItemBtn');
      if (loadItemBtn) {
        loadItemBtn.addEventListener('click', () => {
          const selectedItemId = (qs('#itemSelector') || {}).value;
          if (selectedItemId) {
            const url = new URL(window.location.href);
            url.searchParams.set('item', selectedItemId);
            window.location.href = url.toString();
          }
        });
      }

      const addMaterialBtn = qs('#addMaterialBtn');
      if (addMaterialBtn) addMaterialBtn.addEventListener('click', () => this.openAddModal('material'));

      const addLaborBtn = qs('#addLaborBtn');
      if (addLaborBtn) addLaborBtn.addEventListener('click', () => this.openAddModal('labor'));

      const addEnergyBtn = qs('#addEnergyBtn');
      if (addEnergyBtn) addEnergyBtn.addEventListener('click', () => this.openAddModal('energy'));

      const updateCostBtn = qs('#updateCostBtn');
      if (updateCostBtn) updateCostBtn.addEventListener('click', () => this.openUpdateCostModal());

      const materialForm = qs('#materialForm');
      if (materialForm) materialForm.addEventListener('submit', (e) => { e.preventDefault(); this.saveMaterial(); });

      const laborForm = qs('#laborForm');
      if (laborForm) laborForm.addEventListener('submit', (e) => { e.preventDefault(); this.saveLabor(); });

      const energyForm = qs('#energyForm');
      if (energyForm) energyForm.addEventListener('submit', (e) => { e.preventDefault(); this.saveEnergy(); });

      const confirmDeleteBtn = qs('#confirmDeleteBtn');
      if (confirmDeleteBtn) confirmDeleteBtn.addEventListener('click', () => {
        const itemId = (qs('#deleteItemId') || {}).value;
        const itemType = (qs('#deleteItemType') || {}).value;
        if (itemId && itemType) this.deleteItem(itemId, itemType);
      });

      const confirmUpdateCostBtn = qs('#confirmUpdateCostBtn');
      if (confirmUpdateCostBtn) confirmUpdateCostBtn.addEventListener('click', () => this.updateCostPrice());

      qsa('.close-modal').forEach((btn) => {
        btn.addEventListener('click', () => {
          const modalId = btn.getAttribute('data-modal');
          if (modalId) this.closeModal(modalId);
        });
      });

      // Delegate edit/delete buttons within lists
      const lists = ['#materialsList', '#laborList', '#energyList'];
      lists.forEach((sel) => {
        const root = qs(sel);
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
              this.openEditModal(params.type, params.id);
            } else if (action === 'openDeleteModal') {
              this.openDeleteModal(params.type, params.id, params.name || '');
            }
          } catch (err) {
            console.error('[AdminCostBreakdown] Action error:', err);
          }
        });
      });
    },

    async loadCostBreakdown(itemId) {
      this.showLoading();
      try {
        const data = await ApiClient.get('/functions/process_cost_breakdown.php', {
          inventoryId: itemId,
          costType: 'all'
        });
        if (data.success) {
          this.costBreakdown = data.data || this.costBreakdown;
          this.displayCostBreakdown();
        } else {
          showError('Failed to load cost breakdown. Please try again.');
        }
      } catch (err) {
        console.error('Error fetching cost breakdown:', err);
        showError('Failed to load cost breakdown. Please try again.');
      } finally {
        this.hideLoading();
      }
    },

    displayCostBreakdown() {
      // Materials
      const materialsList = qs('#materialsList');
      if (materialsList) {
        materialsList.innerHTML = '';
        if (this.costBreakdown.materials.length > 0) {
          qs('#noMaterialsMsg')?.classList.add('hidden');
          this.costBreakdown.materials.forEach((m) => {
            const div = document.createElement('div');
            div.className = 'py-3 flex justify-between items-center';
            div.innerHTML = `
              <div>
                <span class="font-medium">${this.escapeHtml(m.name)}</span>
              </div>
              <div class="flex items-center">
                <span class="font-semibold">$${parseFloat(m.cost).toFixed(2)}</span>
                <button class="admin-action-button btn btn-xs btn-icon btn-icon--edit" data-action="openEditModal" data-params='{"type":"material","id":${m.id}}' title="Edit Material" aria-label="Edit Material"></button>
                <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="openDeleteModal" data-params='{"type":"material","id":${m.id},"name":"${this.escapeHtml(m.name)}"}' title="Delete Material" aria-label="Delete Material"></button>
              </div>`;
            materialsList.appendChild(div);
          });
        } else {
          qs('#noMaterialsMsg')?.classList.remove('hidden');
        }
      }

      // Labor
      const laborList = qs('#laborList');
      if (laborList) {
        laborList.innerHTML = '';
        if (this.costBreakdown.labor.length > 0) {
          qs('#noLaborMsg')?.classList.add('hidden');
          this.costBreakdown.labor.forEach((l) => {
            const div = document.createElement('div');
            div.className = 'py-3 flex justify-between items-center';
            div.innerHTML = `
              <div>
                <span class="font-medium">${this.escapeHtml(l.description)}</span>
              </div>
              <div class="flex items-center">
                <span class="font-semibold">$${parseFloat(l.cost).toFixed(2)}</span>
                <button class="admin-action-button btn btn-xs btn-icon btn-icon--edit" data-action="openEditModal" data-params='{"type":"labor","id":${l.id}}' title="Edit Labor" aria-label="Edit Labor"></button>
                <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="openDeleteModal" data-params='{"type":"labor","id":${l.id},"name":"${this.escapeHtml(l.description)}"}' title="Delete Labor" aria-label="Delete Labor"></button>
              </div>`;
            laborList.appendChild(div);
          });
        } else {
          qs('#noLaborMsg')?.classList.remove('hidden');
        }
      }

      // Energy
      const energyList = qs('#energyList');
      if (energyList) {
        energyList.innerHTML = '';
        if (this.costBreakdown.energy.length > 0) {
          qs('#noEnergyMsg')?.classList.add('hidden');
          this.costBreakdown.energy.forEach((e) => {
            const div = document.createElement('div');
            div.className = 'py-3 flex justify-between items-center';
            div.innerHTML = `
              <div>
                <span class="font-medium">${this.escapeHtml(e.description)}</span>
              </div>
              <div class="flex items-center">
                <span class="font-semibold">$${parseFloat(e.cost).toFixed(2)}</span>
                <button class="admin-action-button btn btn-xs btn-icon btn-icon--edit" data-action="openEditModal" data-params='{"type":"energy","id":${e.id}}' title="Edit Energy" aria-label="Edit Energy"></button>
                <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="openDeleteModal" data-params='{"type":"energy","id":${e.id},"name":"${this.escapeHtml(e.description)}"}' title="Delete Energy" aria-label="Delete Energy"></button>
              </div>`;
            energyList.appendChild(div);
          });
        } else {
          qs('#noEnergyMsg')?.classList.remove('hidden');
        }
      }

      // Totals
      const totals = this.costBreakdown.totals || {};
      const setText = (id, val) => { const el = qs(id); if (el) el.textContent = val; };
      setText('#materialsTotalDisplay', `$${(totals.materialTotal || 0).toFixed(2)}`);
      setText('#laborTotalDisplay', `$${(totals.laborTotal || 0).toFixed(2)}`);
      setText('#energyTotalDisplay', `$${(totals.energyTotal || 0).toFixed(2)}`);
      setText('#suggestedCostDisplay', `$${(totals.suggestedCost || 0).toFixed(2)}`);

      const currentCostDisplay = qs('#currentCostDisplay');
      const itemCostDisplay = qs('#itemCostDisplay');
      if (currentCostDisplay && itemCostDisplay) {
        const currentCost = (itemCostDisplay.textContent || '').replace('$', '');
        currentCostDisplay.textContent = `$${parseFloat(currentCost || '0').toFixed(2)}`;
      }

      ['#materialsTotalDisplay', '#laborTotalDisplay', '#energyTotalDisplay', '#suggestedCostDisplay']
        .forEach((id) => this.animateElement(id.replace('#', '')));
    },

    openAddModal(type) {
      this.resetForm(type);
      const title = qs(`#${type}ModalTitle`);
      if (title) title.textContent = `Add ${this.capitalizeFirstLetter(type)}`;
      const invInput = qs(`#${type}InventoryId`);
      if (invInput) invInput.value = this.currentItemId || '';
      const idInput = qs(`#${type}Id`);
      if (idInput) idInput.value = '';
      this.openModal(`${type}Modal`);
    },

    openEditModal(type, id) {
      this.resetForm(type);
      const title = qs(`#${type}ModalTitle`);
      if (title) title.textContent = `Edit ${this.capitalizeFirstLetter(type)}`;
      const idInput = qs(`#${type}Id`);
      const invInput = qs(`#${type}InventoryId`);
      if (idInput) idInput.value = id;
      if (invInput) invInput.value = this.currentItemId || '';

      let item;
      if (type === 'material') {
        item = (this.costBreakdown.materials || []).find((m) => m.id == id);
        if (item) {
          const name = qs('#materialName');
          const cost = qs('#materialCost');
          if (name) name.value = item.name;
          if (cost) cost.value = parseFloat(item.cost).toFixed(2);
        }
      } else if (type === 'labor') {
        item = (this.costBreakdown.labor || []).find((l) => l.id == id);
        if (item) {
          const desc = qs('#laborDescription');
          const cost = qs('#laborCost');
          if (desc) desc.value = item.description;
          if (cost) cost.value = parseFloat(item.cost).toFixed(2);
        }
      } else if (type === 'energy') {
        item = (this.costBreakdown.energy || []).find((e) => e.id == id);
        if (item) {
          const desc = qs('#energyDescription');
          const cost = qs('#energyCost');
          if (desc) desc.value = item.description;
          if (cost) cost.value = parseFloat(item.cost).toFixed(2);
        }
      }

      this.openModal(`${type}Modal`);
    },

    openDeleteModal(type, id, name) {
      const idEl = qs('#deleteItemId');
      const typeEl = qs('#deleteItemType');
      if (idEl) idEl.value = id;
      if (typeEl) typeEl.value = type;

      let itemTypeDisplay = type;
      if (type === 'material') itemTypeDisplay = 'material';
      else if (type === 'labor') itemTypeDisplay = 'labor cost';
      else if (type === 'energy') itemTypeDisplay = 'energy cost';

      const confirmText = qs('#deleteConfirmText');
      if (confirmText) confirmText.textContent = `Are you sure you want to delete the ${itemTypeDisplay} "${name}"?`;

      this.openModal('deleteModal');
    },

    openUpdateCostModal() {
      const currentCostDisplay = qs('#currentCostDisplay');
      const currentCost = (currentCostDisplay?.textContent || '').replace('$', '');
      const suggestedCost = (this.costBreakdown.totals?.suggestedCost || 0).toFixed(2);
      const updCurrent = qs('#updateCurrentCost');
      const updSuggested = qs('#updateSuggestedCost');
      if (updCurrent) updCurrent.textContent = `$${parseFloat(currentCost || '0').toFixed(2)}`;
      if (updSuggested) updSuggested.textContent = `$${suggestedCost}`;
      this.openModal('updateCostModal');
    },

    async saveMaterial() {
      const id = (qs('#materialId') || {}).value || '';
      const inventoryId = (qs('#materialInventoryId') || {}).value || this.currentItemId || '';
      const name = (qs('#materialName') || {}).value || '';
      const cost = (qs('#materialCost') || {}).value || '';
      const isEdit = id !== '';
      const method = isEdit ? 'PUT' : 'POST';
      const url = isEdit
        ? `/functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}&costType=materials&id=${encodeURIComponent(id)}`
        : `/functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}`;
      const data = { costType: 'materials', name, cost: parseFloat(cost) };

      this.showFormLoading('material');
      try {
        const result = await ApiClient.request(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        if (result.success) {
          this.closeModal('materialModal');
          showSuccess(result.message || 'Saved');
          this.loadCostBreakdown(inventoryId);
        } else {
          showError('Error: ' + (result.error || 'Failed'));
        }
      } catch (err) {
        console.error('Error saving material:', err);
        showError('Failed to save material. Please try again.');
      } finally {
        this.hideFormLoading('material');
      }
    },

    async saveLabor() {
      const id = (qs('#laborId') || {}).value || '';
      const inventoryId = (qs('#laborInventoryId') || {}).value || this.currentItemId || '';
      const description = (qs('#laborDescription') || {}).value || '';
      const cost = (qs('#laborCost') || {}).value || '';
      const isEdit = id !== '';
      const method = isEdit ? 'PUT' : 'POST';
      const url = isEdit
        ? `/functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}&costType=labor&id=${encodeURIComponent(id)}`
        : `/functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}`;
      const data = { costType: 'labor', description, cost: parseFloat(cost) };

      this.showFormLoading('labor');
      try {
        const result = await ApiClient.request(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        if (result.success) {
          this.closeModal('laborModal');
          showSuccess(result.message || 'Saved');
          this.loadCostBreakdown(inventoryId);
        } else {
          showError('Error: ' + (result.error || 'Failed'));
        }
      } catch (err) {
        console.error('Error saving labor:', err);
        showError('Failed to save labor. Please try again.');
      } finally {
        this.hideFormLoading('labor');
      }
    },

    async saveEnergy() {
      const id = (qs('#energyId') || {}).value || '';
      const inventoryId = (qs('#energyInventoryId') || {}).value || this.currentItemId || '';
      const description = (qs('#energyDescription') || {}).value || '';
      const cost = (qs('#energyCost') || {}).value || '';
      const isEdit = id !== '';
      const method = isEdit ? 'PUT' : 'POST';
      const url = isEdit
        ? `/functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}&costType=energy&id=${encodeURIComponent(id)}`
        : `/functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}`;
      const data = { costType: 'energy', description, cost: parseFloat(cost) };

      this.showFormLoading('energy');
      try {
        const result = await ApiClient.request(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        if (result.success) {
          this.closeModal('energyModal');
          showSuccess(result.message || 'Saved');
          this.loadCostBreakdown(inventoryId);
        } else {
          showError('Error: ' + (result.error || 'Failed'));
        }
      } catch (err) {
        console.error('Error saving energy:', err);
        showError('Failed to save energy. Please try again.');
      } finally {
        this.hideFormLoading('energy');
      }
    },

    async deleteItem(id, type) {
      const url = `/functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(this.currentItemId || '')}&costType=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`;
      this.showFormLoading('delete');
      try {
        const result = await ApiClient.request(url, { method: 'DELETE' });
        if (result.success) {
          this.closeModal('deleteModal');
          showSuccess(result.message || 'Deleted');
          this.loadCostBreakdown(this.currentItemId);
        } else {
          showError('Error: ' + (result.error || 'Failed'));
        }
      } catch (err) {
        console.error('Error deleting item:', err);
        showError('Failed to delete item. Please try again.');
      } finally {
        this.hideFormLoading('delete');
      }
    },

    async updateCostPrice() {
      const suggestedCost = this.costBreakdown?.totals?.suggestedCost || 0;
      this.showFormLoading('updateCost');
      try {
        const result = await ApiClient.request('/functions/process_inventory_update.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: this.currentItemId, costPrice: suggestedCost }),
        });
        if (result.success) {
          this.closeModal('updateCostModal');
          showSuccess('Cost price updated successfully');
          const itemCostDisplay = qs('#itemCostDisplay');
          const currentCostDisplay = qs('#currentCostDisplay');
          if (itemCostDisplay) itemCostDisplay.textContent = `$${Number(suggestedCost).toFixed(2)}`;
          if (currentCostDisplay) currentCostDisplay.textContent = `$${Number(suggestedCost).toFixed(2)}`;
          this.animateElement('itemCostDisplay');
          this.animateElement('currentCostDisplay');
        } else {
          showError('Error: ' + (result.error || 'Failed to update cost price'));
        }
      } catch (err) {
        console.error('Error updating cost price:', err);
        showError('Failed to update cost price. Please try again.');
      } finally {
        this.hideFormLoading('updateCost');
      }
    },

    // Helpers
    openModal(id) {
      const modal = qs(`#${id}`);
      if (modal) modal.classList.add('show');
    },
    closeModal(id) {
      const modal = qs(`#${id}`);
      if (modal) modal.classList.remove('show');
    },
    resetForm(type) {
      if (type === 'material') {
        const n = qs('#materialName'); const c = qs('#materialCost');
        if (n) n.value = ''; if (c) c.value = '';
      } else if (type === 'labor') {
        const d = qs('#laborDescription'); const c = qs('#laborCost');
        if (d) d.value = ''; if (c) c.value = '';
      } else if (type === 'energy') {
        const d = qs('#energyDescription'); const c = qs('#energyCost');
        if (d) d.value = ''; if (c) c.value = '';
      }
    },
    showLoading() {
      // hook for spinner/overlay if needed
    },
    hideLoading() {},
    showFormLoading(formType) {
      qs(`#${formType}SubmitText`)?.classList.add('hidden');
      qs(`#${formType}SubmitSpinner`)?.classList.remove('hidden');
    },
    hideFormLoading(formType) {
      qs(`#${formType}SubmitText`)?.classList.remove('hidden');
      qs(`#${formType}SubmitSpinner`)?.classList.add('hidden');
    },
    animateElement(elementId) {
      const el = qs(`#${elementId}`);
      if (!el) return;
      el.classList.remove('highlight-change');
      void el.offsetWidth; // reflow to restart animation
      el.classList.add('highlight-change');
    },
    capitalizeFirstLetter(str) { return (str || '').charAt(0).toUpperCase() + (str || '').slice(1); },
    escapeHtml(unsafe = '') {
      return String(unsafe)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => CostBreakdownModule.init(), { once: true });
  } else {
    CostBreakdownModule.init();
  }
})();

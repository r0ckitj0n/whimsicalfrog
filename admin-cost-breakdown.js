// Admin Cost Breakdown Manager - Vite entry
// Migrated from inline script in sections/admin_cost_breakdown_manager.php

(function () {
  // State
  let currentItemId = '';
  let costBreakdown = {
    materials: [],
    labor: [],
    energy: [],
    totals: { materialTotal: 0, laborTotal: 0, energyTotal: 0, suggestedCost: 0 },
  };

  // Utils
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  function getUrlParam(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name) || '';
  }

  function escapeHtml(unsafe) {
    return String(unsafe)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function capitalizeFirstLetter(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  // Toast helpers
  function showToast(message, type = 'info') {
    const toast = $('#toast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'toast-notification ' + (type === 'error' ? 'is-error' : type === 'success' ? 'is-success' : '');
    toast.classList.remove('hidden');
    setTimeout(() => {
      toast.classList.add('hidden');
    }, 3000);
  }
  const showError = (m) => showToast(m, 'error');
  const showSuccess = (m) => showToast(m, 'success');

  function animateElement(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.classList.remove('highlight-change');
    void el.offsetWidth; // reflow
    el.classList.add('highlight-change');
  }

  // Modal helpers
  function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('show');
  }
  function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('show');
  }

  function resetForm(type) {
    if (type === 'material') {
      $('#materialName').value = '';
      $('#materialCost').value = '';
    } else if (type === 'labor') {
      $('#laborDescription').value = '';
      $('#laborCost').value = '';
    } else if (type === 'energy') {
      $('#energyDescription').value = '';
      $('#energyCost').value = '';
    }
  }

  function showFormLoading(kind) {
    const text = document.getElementById(kind + 'SubmitText');
    const spin = document.getElementById(kind + 'SubmitSpinner');
    if (text) text.classList.add('hidden');
    if (spin) spin.classList.remove('hidden');
  }
  function hideFormLoading(kind) {
    const text = document.getElementById(kind + 'SubmitText');
    const spin = document.getElementById(kind + 'SubmitSpinner');
    if (text) text.classList.remove('hidden');
    if (spin) spin.classList.add('hidden');
  }

  // Data loading
  function loadCostBreakdown(itemId) {
    window.ApiClient.request('functions/process_cost_breakdown.php?inventoryId=' + encodeURIComponent(itemId) + '&costType=all', { method: 'GET' })
      .then((data) => {
        if (data && data.success) {
          costBreakdown = data.data;
          displayCostBreakdown();
        } else {
          showError('Failed to load cost breakdown');
        }
      })
      .catch((e) => {
        console.error(e);
        showError('Failed to load cost breakdown');
      });
  }

  // Render
  function displayCostBreakdown() {
    // Materials
    const materialsList = document.getElementById('materialsList');
    if (materialsList) {
      materialsList.innerHTML = '';
      if (costBreakdown.materials.length) {
        document.getElementById('noMaterialsMsg')?.classList.add('hidden');
        costBreakdown.materials.forEach((material) => {
          const div = document.createElement('div');
          div.className = 'py-3 flex justify-between items-center';
          div.innerHTML = `
            <div>
              <span class="font-medium">${escapeHtml(material.name)}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="font-semibold">$${parseFloat(material.cost).toFixed(2)}</span>
              <button class="text-green-600 hover:text-green-800 js-edit" title="Edit Material">✏️</button>
              <button class="text-red-600 hover:text-red-800 js-delete" title="Delete Material">🗑️</button>
            </div>`;
          div.querySelector('.js-edit').addEventListener('click', () => openEditModal('material', material.id));
          div.querySelector('.js-delete').addEventListener('click', () => openDeleteModal('material', material.id, material.name));
          materialsList.appendChild(div);
        });
      } else {
        document.getElementById('noMaterialsMsg')?.classList.remove('hidden');
      }
    }

    // Labor
    const laborList = document.getElementById('laborList');
    if (laborList) {
      laborList.innerHTML = '';
      if (costBreakdown.labor.length) {
        document.getElementById('noLaborMsg')?.classList.add('hidden');
        costBreakdown.labor.forEach((labor) => {
          const div = document.createElement('div');
          div.className = 'py-3 flex justify-between items-center';
          div.innerHTML = `
            <div>
              <span class="font-medium">${escapeHtml(labor.description)}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="font-semibold">$${parseFloat(labor.cost).toFixed(2)}</span>
              <button class="text-green-600 hover:text-green-800 js-edit" title="Edit Labor">✏️</button>
              <button class="text-red-600 hover:text-red-800 js-delete" title="Delete Labor">🗑️</button>
            </div>`;
          div.querySelector('.js-edit').addEventListener('click', () => openEditModal('labor', labor.id));
          div.querySelector('.js-delete').addEventListener('click', () => openDeleteModal('labor', labor.id, labor.description));
          laborList.appendChild(div);
        });
      } else {
        document.getElementById('noLaborMsg')?.classList.remove('hidden');
      }
    }

    // Energy
    const energyList = document.getElementById('energyList');
    if (energyList) {
      energyList.innerHTML = '';
      if (costBreakdown.energy.length) {
        document.getElementById('noEnergyMsg')?.classList.add('hidden');
        costBreakdown.energy.forEach((energy) => {
          const div = document.createElement('div');
          div.className = 'py-3 flex justify-between items-center';
          div.innerHTML = `
            <div>
              <span class="font-medium">${escapeHtml(energy.description)}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="font-semibold">$${parseFloat(energy.cost).toFixed(2)}</span>
              <button class="text-green-600 hover:text-green-800 js-edit" title="Edit Energy">✏️</button>
              <button class="text-red-600 hover:text-red-800 js-delete" title="Delete Energy">🗑️</button>
            </div>`;
          div.querySelector('.js-edit').addEventListener('click', () => openEditModal('energy', energy.id));
          div.querySelector('.js-delete').addEventListener('click', () => openDeleteModal('energy', energy.id, energy.description));
          energyList.appendChild(div);
        });
      } else {
        document.getElementById('noEnergyMsg')?.classList.remove('hidden');
      }
    }

    // Totals
    const t = costBreakdown.totals || { materialTotal: 0, laborTotal: 0, energyTotal: 0, suggestedCost: 0 };
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = '$' + Number(value).toFixed(2); };
    setText('materialsTotalDisplay', t.materialTotal);
    setText('laborTotalDisplay', t.laborTotal);
    setText('energyTotalDisplay', t.energyTotal);
    setText('suggestedCostDisplay', t.suggestedCost);

    animateElement('materialsTotalDisplay');
    animateElement('laborTotalDisplay');
    animateElement('energyTotalDisplay');
    animateElement('suggestedCostDisplay');
  }

  // Actions
  function openAddModal(type) {
    resetForm(type);
    const title = document.getElementById(type + 'ModalTitle');
    const inv = document.getElementById(type + 'InventoryId');
    const idEl = document.getElementById(type + 'Id');
    if (title) title.textContent = 'Add ' + capitalizeFirstLetter(type);
    if (inv) inv.value = currentItemId;
    if (idEl) idEl.value = '';
    openModal(type + 'Modal');
  }
  function openEditModal(type, id) {
    resetForm(type);
    const title = document.getElementById(type + 'ModalTitle');
    const inv = document.getElementById(type + 'InventoryId');
    const idEl = document.getElementById(type + 'Id');
    if (title) title.textContent = 'Edit ' + capitalizeFirstLetter(type);
    if (idEl) idEl.value = id;
    if (inv) inv.value = currentItemId;

    let item;
    if (type === 'material') {
      item = (costBreakdown.materials || []).find((m) => m.id == id);
      if (item) {
        $('#materialName').value = item.name;
        $('#materialCost').value = parseFloat(item.cost).toFixed(2);
      }
    } else if (type === 'labor') {
      item = (costBreakdown.labor || []).find((l) => l.id == id);
      if (item) {
        $('#laborDescription').value = item.description;
        $('#laborCost').value = parseFloat(item.cost).toFixed(2);
      }
    } else if (type === 'energy') {
      item = (costBreakdown.energy || []).find((e) => e.id == id);
      if (item) {
        $('#energyDescription').value = item.description;
        $('#energyCost').value = parseFloat(item.cost).toFixed(2);
      }
    }
    openModal(type + 'Modal');
  }
  function openDeleteModal(type, id, name) {
    const delId = document.getElementById('deleteItemId');
    const delType = document.getElementById('deleteItemType');
    if (delId) delId.value = id;
    if (delType) delType.value = type;
    const text = document.getElementById('deleteConfirmText');
    if (text) text.textContent = `Are you sure you want to delete the ${type === 'labor' ? 'labor cost' : type === 'energy' ? 'energy cost' : 'material'} "${name}"?`;
    openModal('deleteModal');
  }
  function openUpdateCostModal() {
    const currentCostEl = document.getElementById('currentCostDisplay');
    const suggested = (costBreakdown.totals?.suggestedCost || 0).toFixed(2);
    const updCurr = document.getElementById('updateCurrentCost');
    const updSugg = document.getElementById('updateSuggestedCost');
    if (updCurr && currentCostEl) {
      const val = currentCostEl.textContent.replace('$', '');
      updCurr.textContent = '$' + parseFloat(val || '0').toFixed(2);
    }
    if (updSugg) updSugg.textContent = '$' + suggested;
    openModal('updateCostModal');
  }

  // Network mutations
  function saveMaterial() {
    const id = $('#materialId').value;
    const inventoryId = $('#materialInventoryId').value;
    const name = $('#materialName').value;
    const cost = $('#materialCost').value;
    const isEdit = id !== '';
    const method = isEdit ? 'PUT' : 'POST';
    const url = isEdit
      ? `functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}&costType=materials&id=${encodeURIComponent(id)}`
      : `functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}`;
    const data = { costType: 'materials', name, cost: parseFloat(cost) };
    showFormLoading('material');
    window.ApiClient.request(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
      .then((res) => {
        if (res.success) {
          closeModal('materialModal');
          showSuccess(res.message || 'Saved');
          loadCostBreakdown(inventoryId);
        } else {
          showError('Error: ' + (res.error || 'Failed'));
        }
      })
      .catch((e) => { console.error(e); showError('Failed to save material'); })
      .finally(() => hideFormLoading('material'));
  }
  function saveLabor() {
    const id = $('#laborId').value;
    const inventoryId = $('#laborInventoryId').value;
    const description = $('#laborDescription').value;
    const cost = $('#laborCost').value;
    const isEdit = id !== '';
    const method = isEdit ? 'PUT' : 'POST';
    const url = isEdit
      ? `functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}&costType=labor&id=${encodeURIComponent(id)}`
      : `functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}`;
    const data = { costType: 'labor', description, cost: parseFloat(cost) };
    showFormLoading('labor');
    window.ApiClient.request(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
      .then((res) => {
        if (res.success) {
          closeModal('laborModal');
          showSuccess(res.message || 'Saved');
          loadCostBreakdown(inventoryId);
        } else {
          showError('Error: ' + (res.error || 'Failed'));
        }
      })
      .catch((e) => { console.error(e); showError('Failed to save labor'); })
      .finally(() => hideFormLoading('labor'));
  }
  function saveEnergy() {
    const id = $('#energyId').value;
    const inventoryId = $('#energyInventoryId').value;
    const description = $('#energyDescription').value;
    const cost = $('#energyCost').value;
    const isEdit = id !== '';
    const method = isEdit ? 'PUT' : 'POST';
    const url = isEdit
      ? `functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}&costType=energy&id=${encodeURIComponent(id)}`
      : `functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(inventoryId)}`;
    const data = { costType: 'energy', description, cost: parseFloat(cost) };
    showFormLoading('energy');
    window.ApiClient.request(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
      .then((res) => {
        if (res.success) {
          closeModal('energyModal');
          showSuccess(res.message || 'Saved');
          loadCostBreakdown(inventoryId);
        } else {
          showError('Error: ' + (res.error || 'Failed'));
        }
      })
      .catch((e) => { console.error(e); showError('Failed to save energy'); })
      .finally(() => hideFormLoading('energy'));
  }
  function deleteItem(id, type) {
    const url = `functions/process_cost_breakdown.php?inventoryId=${encodeURIComponent(currentItemId)}&costType=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`;
    showFormLoading('delete');
    window.ApiClient.request(url, { method: 'DELETE' })
      .then((res) => {
        if (res.success) {
          closeModal('deleteModal');
          showSuccess(res.message || 'Deleted');
          loadCostBreakdown(currentItemId);
        } else {
          showError('Error: ' + (res.error || 'Failed'));
        }
      })
      .catch((e) => { console.error(e); showError('Failed to delete item'); })
      .finally(() => hideFormLoading('delete'));
  }
  function updateCostPrice() {
    const suggested = Number(costBreakdown.totals?.suggestedCost || 0);
    showFormLoading('updateCost');
    window.ApiClient.request('functions/process_inventory_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: currentItemId, costPrice: suggested }),
    })
      .then((res) => {
        if (res.success) {
          closeModal('updateCostModal');
          showSuccess('Cost price updated successfully');
          const itemCostEl = document.getElementById('currentCostDisplay');
          if (itemCostEl) itemCostEl.textContent = '$' + suggested.toFixed(2);
          animateElement('currentCostDisplay');
        } else {
          showError('Error: ' + (res.error || 'Failed to update cost price'));
        }
      })
      .catch((e) => { console.error(e); showError('Failed to update cost price'); })
      .finally(() => hideFormLoading('updateCost'));
  }

  // Event wiring
  function initEventListeners() {
    const loadBtn = document.getElementById('loadItemBtn');
    if (loadBtn) loadBtn.addEventListener('click', () => {
      const sel = document.getElementById('itemSelector');
      const val = sel ? sel.value : '';
      if (val) window.location.href = '?item=' + encodeURIComponent(val);
    });

    document.getElementById('addMaterialBtn')?.addEventListener('click', () => openAddModal('material'));
    document.getElementById('addLaborBtn')?.addEventListener('click', () => openAddModal('labor'));
    document.getElementById('addEnergyBtn')?.addEventListener('click', () => openAddModal('energy'));
    document.getElementById('updateCostBtn')?.addEventListener('click', openUpdateCostModal);

    document.getElementById('materialForm')?.addEventListener('submit', (e) => { e.preventDefault(); saveMaterial(); });
    document.getElementById('laborForm')?.addEventListener('submit', (e) => { e.preventDefault(); saveLabor(); });
    document.getElementById('energyForm')?.addEventListener('submit', (e) => { e.preventDefault(); saveEnergy(); });

    document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
      const id = document.getElementById('deleteItemId')?.value;
      const type = document.getElementById('deleteItemType')?.value;
      if (id && type) deleteItem(id, type);
    });

    document.getElementById('confirmUpdateCostBtn')?.addEventListener('click', updateCostPrice);

    $$('.close-modal').forEach((btn) => btn.addEventListener('click', (_e) => {
      const modalId = btn.getAttribute('data-modal');
      if (modalId) closeModal(modalId);
    }));
  }

  // Boot
  document.addEventListener('DOMContentLoaded', () => {
    currentItemId = getUrlParam('item') || '';
    initEventListeners();
    if (currentItemId) {
      const container = document.getElementById('costBreakdownContainer');
      const empty = document.getElementById('emptyStateContainer');
      if (container) container.classList.remove('hidden');
      if (empty) empty.classList.add('hidden');
      loadCostBreakdown(currentItemId);
    }
  });
})();

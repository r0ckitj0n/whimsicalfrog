(function(){
  const byId = (id) => document.getElementById(id);
  const qs = (sel) => document.querySelector(sel);
  const qsa = (sel) => document.querySelectorAll(sel);

  const state = {
    activeTab: 'mappings',
    lastExplicit: [],
    lastDerived: [],
    lastCoordinates: [],
    unrepresentedItems: [],
    unrepresentedCategories: [],
    debounceTimer: null
  };

  function setMsg(html, type = 'ok'){
    const el = byId('aimMessage'); if(!el) return;
    const cls = type === 'error' ? 'text-red-700 bg-red-100 border-red-300' : 'text-green-700 bg-green-100 border-green-300';
    el.innerHTML = `<div class="border ${cls} rounded-md px-4 py-3 text-sm">${html}</div>`;
    setTimeout(() => { el.innerHTML = ''; }, 5000);
  }

  async function fetchJSON(url, opts){
    try {
      const res = await fetch(url, opts);
      if (!res.ok) {
        const errorText = await res.text();
        throw new Error(`HTTP ${res.status}: ${errorText}`);
      }
      return await res.json();
    } catch (e) {
      console.error(`[AreaItem] Fetch failed for ${url}:`, e);
      setMsg(`Network error fetching data from ${url}.`, 'error');
      return null;
    }
  }

  // --- Render Functions ---
  function renderMappings(){
    const container = byId('aimMappingsContainer');
    if (!container) return;

    const explicitHtml = renderExplicitTable(state.lastExplicit || []);
    const derivedHtml = renderDerivedTable(state.lastDerived || []);
    const header = state.lastDerivedCategory ? `<div class="mb-2 text-sm text-gray-600">Derived category: <strong>${state.lastDerivedCategory}</strong></div>` : '';
    const syncBar = `<div class="mb-4 flex flex-wrap gap-2">
      <button class="btn btn-sm btn-secondary" data-action="aim-sync-order">Sync Order from Derived</button>
      <button class="btn btn-sm btn-secondary" data-action="aim-apply-empty">Apply Derived to Empty Areas</button>
      <button class="btn btn-sm btn-secondary" data-action="aim-resequence-all">Re-sequence All</button>
    </div>`;

    container.innerHTML = `${header}${syncBar}${explicitHtml}${derivedHtml}`;
    container.classList.remove('hidden');
  }

  function renderExplicitTable(list) {
    const rows = list.map(m => {
      const typeOpts = `<option value="item" ${m.mapping_type === 'item' ? 'selected' : ''}>Item</option>\n<option value="category" ${m.mapping_type === 'category' ? 'selected' : ''}>Category</option>`;
      const targetPlaceholder = m.mapping_type === 'item' ? 'SKU (e.g., WF-TS-002)' : 'Category ID';
      const targetValue = m.mapping_type === 'item' ? (m.item_sku || '') : (m.category_id || '');
      return `<tr data-id="${m.id}">\n<td class="p-2"><input class="form-input w-32" data-field="area_selector" value="${m.area_selector || ''}"></td>\n<td class="p-2"><select class="form-input" data-field="mapping_type">${typeOpts}</select></td>\n<td class="p-2"><input class="form-input w-48" data-field="target" placeholder="${targetPlaceholder}" value="${targetValue}"></td>\n<td class="p-2"><input type="number" class="form-input w-20" data-field="display_order" value="${m.display_order || 0}"></td>\n<td class="p-2 whitespace-nowrap">\n<button class="btn btn-xs btn-primary" data-action="aim-save" data-id="${m.id}">Save</button>\n<button class="btn btn-xs btn-danger" data-action="aim-delete" data-id="${m.id}">Delete</button>\n</td>\n</tr>`;
    }).join('');

    return `<div class="mb-2 font-semibold">Explicit Mappings</div>\n<div class="overflow-x-auto border rounded-lg">\n<table class="min-w-full text-sm divide-y divide-gray-200">\n<thead class="bg-gray-50">\n<tr>\n<th class="p-2 text-left">Area</th><th class="p-2 text-left">Type</th><th class="p-2 text-left">Target</th><th class="p-2 text-left">Order</th><th class="p-2 text-left">Actions</th>\n</tr>\n</thead>\n<tbody class="divide-y divide-gray-200">${rows}</tbody>\n<tfoot class="bg-gray-50">\n<tr>\n<td class="p-2"><input class="form-input w-32" id="aimNewArea" placeholder=".area-1"></td>\n<td class="p-2"><select class="form-input" id="aimNewType"><option value="item">Item</option><option value="category">Category</option></select></td>\n<td class="p-2"><input class="form-input w-48" id="aimNewTarget" placeholder="SKU or Category ID"></td>\n<td class="p-2"><input type="number" class="form-input w-20" id="aimNewOrder" value="0"></td>\n<td class="p-2"><button class="btn btn-xs btn-success" data-action="aim-add-explicit">Add</button></td>\n</tr>\n</tfoot>\n</table>\n</div>`;
  }

  function renderDerivedTable(list) {
    if (!list || list.length === 0) return '<div class="p-3 text-sm text-gray-500">No derived data for this room.</div>';
    const rows = list.map((m, idx) => {
      const label = m.sku ? `Item SKU ${m.sku}` : (m.item_id ? `Item #${m.item_id}` : 'Item');
      return `<tr>\n<td class="p-2">${m.area_selector || ''}</td>\n<td class="p-2">${label}</td>\n<td class="p-2">${m.display_order || idx + 1}</td>\n<td class="p-2">${m.sku ? `<button class="btn btn-xs btn-secondary" data-action="aim-convert" data-area="${m.area_selector}" data-sku="${m.sku}">Convert to Explicit</button>` : ''}</td>\n</tr>`;
    }).join('');
    return `<div class="mt-6 mb-2 font-semibold">Live (Derived) View</div>\n<div class="overflow-x-auto border rounded-lg">\n<table class="min-w-full text-sm divide-y divide-gray-200">\n<thead class="bg-gray-50"><tr><th class="p-2 text-left">Area</th><th class="p-2 text-left">Resolved Target</th><th class="p-2 text-left">Order</th><th class="p-2 text-left">Actions</th></tr></thead>\n<tbody class="divide-y divide-gray-200">${rows}</tbody>\n</table>\n</div>`;
  }

  function renderUnrepresented() {
    const container = byId('tab-panel-unrepresented');
    if (!container) return;
    container.innerHTML = `\n<div class="grid grid-cols-1 md:grid-cols-2 gap-8">\n<div>\n<h3 class="font-semibold mb-2">Unrepresented Items</h3>\n<input type="search" id="unrepItemSearch" class="form-input w-full mb-2" placeholder="Search items by SKU or name...">\n<div id="unrepItemsList" class="border rounded-lg overflow-y-auto h-96"></div>\n</div>\n<div>\n<h3 class="font-semibold mb-2">Unrepresented Categories</h3>\n<input type="search" id="unrepCategorySearch" class="form-input w-full mb-2" placeholder="Search categories by name...">\n<div id="unrepCategoriesList" class="border rounded-lg overflow-y-auto h-96"></div>\n</div>\n</div>`;
    loadUnrepresentedItems();
    loadUnrepresentedCategories();
  }

  function renderCoordinates() {
    const container = byId('tab-panel-coordinates');
    if (!container) return;
    const room = byId('aimRoomSelect').value;
    if (!room) {
      container.innerHTML = '<p class="text-gray-500">Select a room to view coordinates.</p>';
      return;
    }
    container.innerHTML = '<div class="h-96 border rounded-lg bg-gray-50 flex items-center justify-center"><p>Loading coordinates...</p></div>';
    fetchJSON(`/api/area_mappings.php?action=get_room_coordinates&room=${encodeURIComponent(room)}`).then(data => {
      if (data && data.success) {
        const coords = data.coordinates || [];
        const list = coords.map((c, i) => `<li>.area-${i+1}: ${JSON.stringify(c)}</li>`).join('');
        container.innerHTML = `<h3 class="font-semibold mb-2">Room Coordinates (${coords.length} found)</h3><ul class="text-xs font-mono bg-gray-100 p-4 rounded-md h-96 overflow-y-auto">${list || '<li>No coordinates found.</li>'}</ul>`;
      } else {
        container.innerHTML = '<p class="text-red-500">Failed to load coordinates.</p>';
      }
    });
  }

  // --- Data Loading ---
  async function loadMappings(){
    const room = byId('aimRoomSelect').value;
    const container = byId('aimMappingsContainer');
    if(!room) { container.innerHTML = '<div class="p-3 text-gray-500">Select a room to view mappings.</div>'; container.classList.remove('hidden'); return; }
    container.innerHTML = '<div class="p-3 text-gray-500">Loading...</div>';
    container.classList.remove('hidden');

    const [exp, live] = await Promise.all([
      fetchJSON(`/api/area_mappings.php?action=get_mappings&room=${encodeURIComponent(room)}`),
      fetchJSON(`/api/area_mappings.php?action=get_live_view&room=${encodeURIComponent(room)}`)
    ]);

    state.lastExplicit = (exp && exp.success) ? exp.mappings : [];
    state.lastDerived = (live && live.success) ? live.mappings : [];
    state.lastDerivedCategory = (live && live.success) ? live.category : '';

    renderMappings();
  }

  function loadUnrepresentedItems(query = '') {
    const container = byId('unrepItemsList');
    container.innerHTML = '<p class="p-4 text-gray-500">Loading items...</p>';
    fetchJSON(`/api/unrepresented_items.php?q=${encodeURIComponent(query)}`).then(data => {
      if (data && data.success) {
        state.unrepresentedItems = data.items || [];
        const listHtml = state.unrepresentedItems.map(item => 
          `<div class="p-2 border-b flex justify-between items-center">\n<div>\n<div class="font-semibold">${item.name}</div>\n<div class="text-xs text-gray-500">SKU: ${item.sku}</div>\n</div>\n<button class="btn btn-xs btn-secondary" data-action="aim-quick-map-item" data-sku="${item.sku}">Map...</button>\n</div>`
        ).join('');
        container.innerHTML = listHtml || '<p class="p-4 text-gray-500">No unrepresented items found.</p>';
      } else {
        container.innerHTML = '<p class="p-4 text-red-500">Failed to load items.</p>';
      }
    });
  }

  function loadUnrepresentedCategories(query = '') {
    const container = byId('unrepCategoriesList');
    container.innerHTML = '<p class="p-4 text-gray-500">Loading categories...</p>';
    fetchJSON(`/api/unrepresented_categories.php?q=${encodeURIComponent(query)}`).then(data => {
      if (data && data.success) {
        state.unrepresentedCategories = data.categories || [];
        const listHtml = state.unrepresentedCategories.map(cat => 
          `<div class="p-2 border-b flex justify-between items-center">\n<div>\n<div class="font-semibold">${cat.name}</div>\n<div class="text-xs text-gray-500">ID: ${cat.id}</div>\n</div>\n<button class="btn btn-xs btn-secondary" data-action="aim-quick-map-category" data-id="${cat.id}">Map...</button>\n</div>`
        ).join('');
        container.innerHTML = listHtml || '<p class="p-4 text-gray-500">No unrepresented categories found.</p>';
      } else {
        container.innerHTML = '<p class="p-4 text-red-500">Failed to load categories.</p>';
      }
    });
  }

  // --- Event Handlers & Core Logic ---
  function handleTabClick(e) {
    const tabButton = e.target.closest('.aim-tab');
    if (!tabButton) return;

    const tabName = tabButton.dataset.tab;
    if (state.activeTab === tabName) return;

    state.activeTab = tabName;

    qsa('.aim-tab').forEach(tab => {
      tab.classList.toggle('border-indigo-500', tab.dataset.tab === tabName);
      tab.classList.toggle('text-indigo-600', tab.dataset.tab === tabName);
      tab.classList.toggle('border-transparent', tab.dataset.tab !== tabName);
      tab.classList.toggle('text-gray-500', tab.dataset.tab !== tabName);
    });

    qsa('.aim-tab-panel').forEach(panel => {
      panel.classList.toggle('hidden', panel.id !== `tab-panel-${tabName}`);
    });

    if (tabName === 'unrepresented') {
      renderUnrepresented();
    } else if (tabName === 'coordinates') {
      renderCoordinates();
    }
  }

  async function handleAction(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    const action = btn.dataset.action;
    const id = btn.dataset.id;
    const room = byId('aimRoomSelect').value;

    switch (action) {
      case 'aim-save':
        await updateMapping(id);
        break;
      case 'aim-delete':
        if (confirm('Are you sure you want to delete this mapping?')) {
          await deleteMapping(id);
        }
        break;
      case 'aim-add-explicit':
        await addMapping();
        break;
      case 'aim-convert':
        await convertDerived(btn.dataset.area, btn.dataset.sku);
        break;
      case 'aim-quick-map-item':
      case 'aim-quick-map-category':
        if (!room) {
          setMsg('Please select a room first before mapping.', 'error');
          return;
        }
        const type = action === 'aim-quick-map-item' ? 'item' : 'category';
        const target = type === 'item' ? btn.dataset.sku : btn.dataset.id;
        const area = prompt(`Enter the area selector to map this ${type} to (e.g., .area-1):`);
        if (area) {
          await addMapping({ area_selector: area, mapping_type: type, target: target });
        }
        break;
    }
  }

  async function addMapping(prefill = {}) {
    const room = byId('aimRoomSelect').value;
    const area = prefill.area_selector || byId('aimNewArea').value.trim();
    const type = prefill.mapping_type || byId('aimNewType').value;
    const target = prefill.target || byId('aimNewTarget').value.trim();
    const order = byId('aimNewOrder') ? byId('aimNewOrder').value : 0;

    if (!room || !area || !target) {
      setMsg('Room, Area, and Target are required to add a mapping.', 'error');
      return;
    }

    const payload = { action: 'add_mapping', room, area_selector: area, mapping_type: type, display_order: order };
    if (type === 'item') payload.item_sku = target; else payload.category_id = target;

    const result = await fetchJSON('/api/area_mappings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    if (result && result.success) {
      setMsg('Mapping added successfully.', 'ok');
      await loadMappings();
      if (byId('aimNewArea')) byId('aimNewArea').value = '';
      if (byId('aimNewTarget')) byId('aimNewTarget').value = '';
    } else {
      setMsg(result ? result.message : 'Failed to add mapping.', 'error');
    }
  }

  async function updateMapping(id) {
    const row = qs(`tr[data-id="${id}"]`);
    if (!row) return;

    const payload = {
      id,
      mapping_type: row.querySelector('[data-field="mapping_type"]').value,
      area_selector: row.querySelector('[data-field="area_selector"]').value.trim(),
      display_order: parseInt(row.querySelector('[data-field="display_order"]').value, 10) || 0
    };
    const target = row.querySelector('[data-field="target"]').value.trim();
    if (payload.mapping_type === 'item') payload.item_sku = target; else payload.category_id = target;

    const result = await fetchJSON('/api/area_mappings.php', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    if (result && result.success) {
      setMsg('Mapping updated.', 'ok');
    } else {
      setMsg(result ? result.message : 'Failed to update mapping.', 'error');
    }
  }

  async function deleteMapping(id) {
    const result = await fetchJSON('/api/area_mappings.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
    if (result && result.success) {
      setMsg('Mapping deleted.', 'ok');
      await loadMappings();
    } else {
      setMsg(result ? result.message : 'Failed to delete mapping.', 'error');
    }
  }
  
  async function convertDerived(area_selector, sku) {
    const room = byId('aimRoomSelect').value;
    if (!room || !sku || !area_selector) return;

    const payload = { action: 'add_mapping', room, area_selector, mapping_type: 'item', item_sku: sku, display_order: 0 };
    const result = await fetchJSON('/api/area_mappings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });

    if (result && result.success) {
        setMsg('Converted to explicit mapping.', 'ok');
        await loadMappings();
    } else {
        setMsg(result ? result.message : 'Failed to convert mapping.', 'error');
    }
  }

  function handleSearch(e) {
    clearTimeout(state.debounceTimer);
    const query = e.target.value;
    const type = e.target.id === 'unrepItemSearch' ? 'item' : 'category';
    state.debounceTimer = setTimeout(() => {
      if (type === 'item') loadUnrepresentedItems(query); else loadUnrepresentedCategories(query);
    }, 300);
  }

  function run(){
    document.addEventListener('click', (e) => {
      handleTabClick(e);
      handleAction(e);
    });
    byId('aimRoomSelect').addEventListener('change', loadMappings);
    document.addEventListener('input', (e) => {
        if (e.target.id === 'unrepItemSearch' || e.target.id === 'unrepCategorySearch') {
            handleSearch(e);
        }
    });
  }

  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, {once:true});
  else run();
})();

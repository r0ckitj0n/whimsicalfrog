// Admin: Area-Item Mapper UI logic
(function(){
  const byId = (id) => document.getElementById(id);
  let _lastExplicit = [];
  let lastDerived = [];

  function setMsg(elId, html, type){
    const el = byId(elId); if(!el) return;
    const cls = type==='error' ? 'text-red-700 bg-red-50 border-red-200' : 'text-green-700 bg-green-50 border-green-200';
    el.innerHTML = `<div class="border ${cls} rounded px-3 py-2 text-sm">${html}<\/div>`;
  }

  async function applyDerivedForEmpty(){
    if(!Array.isArray(lastDerived) || lastDerived.length===0) { setMsg('aimMessage','No derived data available.','error'); return; }
    const room = byId('aimRoomSelect').value;
    if(!room){ setMsg('aimMessage','Choose a room first.','error'); return; }
    // Collect existing explicit areas
    const explicitAreas = new Set();
    document.querySelectorAll('tr[data-id]').forEach(row => {
      const areaSel = row.querySelector('[data-field="area_selector"]').value.trim();
      if(areaSel) explicitAreas.add(areaSel);
    });
    const creates = [];
    lastDerived.forEach((d, i) => {
      const area = d.area_selector;
      const sku = d.sku || null;
      const order = i+1;
      if(!area || explicitAreas.has(area) || !sku) return;
      const payload = { action:'add_mapping', room, area_selector: area, mapping_type:'item', item_sku: sku, display_order: order };
      creates.push(fetch('/api/area_mappings.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) }).then(r=>r.json()));
    });
    if(creates.length===0){ setMsg('aimMessage','No empty areas to apply derived to.','ok'); return; }
    try{
      await Promise.all(creates);
      setMsg('aimMessage','Applied derived mappings to empty areas.','ok');
      await loadMappings();
    }catch(e){ setMsg('aimMessage','Failed to apply to empty areas.','error'); }
  }

  async function fetchJSON(url, opts){
    const res = await fetch(url, opts);
    const text = await res.text();
    let data = null;
    try { data = JSON.parse(text); }
    catch(e){ try { console.error('[AreaItem] Invalid JSON from', url, '\nRaw:', text); } catch(_) {} }
    return {res, data, text};
  }

  function renderExplicitTable(list){
    const rows = (list||[]).map(m => {
      const typeOpts = `
        <option value="item" ${m.mapping_type==='item'?'selected':''}>Item<\/option>
        <option value="category" ${m.mapping_type==='category'?'selected':''}>Category<\/option>`;
      const targetPlaceholder = m.mapping_type==='item' ? 'SKU (e.g., WF-TS-002)' : 'Category ID (number)';
      const targetValue = m.mapping_type==='item' ? (m.item_sku||'') : (m.category_id||'');
      return `
        <tr data-id="${m.id}">
          <td class="px-3 py-2"><input class="form-input w-32" data-field="area_selector" value="${m.area_selector||''}"><\/td>
          <td class="px-3 py-2">
            <select class="form-input" data-field="mapping_type">${typeOpts}<\/select>
          <\/td>
          <td class="px-3 py-2">
            <input class="form-input w-48" data-field="target" placeholder="${targetPlaceholder}" value="${targetValue}">
          <\/td>
          <td class="px-3 py-2 w-24"><input type="number" class="form-input w-20" data-field="display_order" value="${m.display_order||0}"><\/td>
          <td class="px-3 py-2">
            <button class="btn btn-xs btn-primary" data-action="aim-save" data-id="${m.id}">Save<\/button>
            <button class="btn btn-xs btn-danger" data-action="aim-delete" data-id="${m.id}">Delete<\/button>
          <\/td>
        <\/tr>`;
    }).join('');
    return `
      <div class="mb-2 font-semibold">Explicit Mappings<\/div>
      <div class="overflow-auto border rounded">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2">Area<\/th>
              <th class="text-left px-3 py-2">Type<\/th>
              <th class="text-left px-3 py-2">Target<\/th>
              <th class="text-left px-3 py-2">Order<\/th>
              <th class="text-left px-3 py-2">Actions<\/th>
            <\/tr>
          <\/thead>
          <tbody>${rows || ''}<\/tbody>
          <tfoot class="bg-gray-50 border-t">
            <tr>
              <td class="px-3 py-2"><input class="form-input w-32" id="aimNewArea" placeholder=".area-1"><\/td>
              <td class="px-3 py-2">
                <select class="form-input" id="aimNewType">
                  <option value="item">Item<\/option>
                  <option value="category">Category<\/option>
                <\/select>
              <\/td>
              <td class="px-3 py-2"><input class="form-input w-48" id="aimNewTarget" placeholder="SKU or Category ID"><\/td>
              <td class="px-3 py-2 w-24"><input type="number" class="form-input w-20" id="aimNewOrder" value="0"><\/td>
              <td class="px-3 py-2"><button class="btn btn-xs btn-success" data-action="aim-add-explicit">Add<\/button><\/td>
            <\/tr>
          <\/tfoot>
        <\/table>
      <\/div>`;
  }

  function renderDerivedTable(list){
    if(!Array.isArray(list) || list.length===0){
      return '<div class="p-3 text-gray-500">No derived data for this room.<\/div>';
    }
    const rows = list.map((m, idx) => {
      const label = m.sku ? `Item SKU ${m.sku}` : (m.item_id ? `Item #${m.item_id}` : 'Item');
      return `
        <tr>
          <td class="px-3 py-2">${m.area_selector||''}<\/td>
          <td class="px-3 py-2">${label}<\/td>
          <td class="px-3 py-2">${m.display_order||idx+1}<\/td>
          <td class="px-3 py-2">
            ${m.sku ? `<button class="btn btn-xs btn-secondary" data-action="aim-convert" data-area="${m.area_selector}" data-sku="${m.sku}">Convert to Explicit<\/button>` : '<span class="text-xs text-gray-400">n/a<\/span>'}
          <\/td>
        <\/tr>`;
    }).join('');
    return `
      <div class="mt-6 mb-2 font-semibold">Live (Derived) View<\/div>
      <div class="overflow-auto border rounded">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2">Area<\/th>
              <th class="text-left px-3 py-2">Resolved Target<\/th>
              <th class="text-left px-3 py-2">Order<\/th>
              <th class="text-left px-3 py-2">Actions<\/th>
            <\/tr>
          <\/thead>
          <tbody>${rows}<\/tbody>
        <\/table>
      <\/div>`;
  }

  function renderCombined(explicitList, derivedList, meta){
    const tgt = byId('aimMappingsList'); if(!tgt) return;
    _lastExplicit = explicitList || [];
    lastDerived = derivedList || [];
    const explicitHtml = renderExplicitTable(explicitList||[]);
    const derivedHtml = renderDerivedTable(derivedList||[]);
    const header = meta && meta.category ? `<div class="mb-2 text-sm text-gray-600">Derived category: <strong>${meta.category}<\/strong><\/div>` : '';
    const syncBar = `<div class="mb-3 flex gap-2">
      <button class="btn btn-sm btn-secondary" data-action="aim-sync-order">Sync order from derived<\/button>
      <button class="btn btn-sm btn-secondary" data-action="aim-apply-empty">Apply derived for empty areas only<\/button>
      <button class="btn btn-sm btn-secondary" data-action="aim-resequence-all">Re-sequence all<\/button>
    <\/div>`;
    tgt.innerHTML = `${header}${syncBar}${explicitHtml}${derivedHtml}`;
    attachAutocompleteAndValidation && attachAutocompleteAndValidation();
    // Add preview icons for target inputs so the helper is actively used
    document.querySelectorAll('input[data-field="target"]').forEach((inp) => { try { addPreviewIcon(inp); } catch(e) { /* no-op */ } });
  }

  const previewData = new WeakMap(); // input -> item object
  let popoverEl = null;
  let popoverHover = false; // true while mouse is over the popover
  let popoverHideTimer = null;
  function ensurePopover(){
    if(popoverEl) return popoverEl;
    popoverEl = document.createElement('div');
    popoverEl.className = 'aim-popover absolute z-[10000] hidden bg-white border rounded shadow p-2 text-sm w-64 right-8 top-1/2 -translate-y-1/2';
    popoverEl.addEventListener('mouseenter', ()=>{ popoverHover = true; if(popoverHideTimer){ clearTimeout(popoverHideTimer); popoverHideTimer=null; } });
    popoverEl.addEventListener('mouseleave', ()=>{
      popoverHover = false;
      // If leaving popover and not over input, hide after a short delay
      if(popoverHideTimer){ clearTimeout(popoverHideTimer); }
      popoverHideTimer = setTimeout(()=>{ if(!popoverHover) hidePreviewPopover(); }, 200);
    });
    return popoverEl;
  }
  function setPreviewData(input, item){
    if(item){ previewData.set(input, item); }
    else { previewData.delete(input); }
  }
  function showPreviewPopover(input){
    const item = previewData.get(input); if(!item) return;
    const el = ensurePopover();
    const td = input.closest('td');
    if(!td) return;
    td.classList.add('relative');
    if(el.parentElement !== td){
      td.appendChild(el);
    }
    el.classList.remove('hidden');
  }
  function hidePreviewPopover(){ if(popoverEl){ popoverEl.classList.add('hidden'); } }

  function addPreviewIcon(input){
    const td = input.closest('td');
    if(!td) return;
    td.classList.add('relative');
    if(td.querySelector('.aim-preview-btn')) return; // already present
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'aim-preview-btn absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-800';
    btn.title = 'Preview item';
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M2.05 12a9.94 9.94 0 0 1 19.9 0 9.94 9.94 0 0 1-19.9 0Z"></path></svg>';
    td.appendChild(btn);
    btn.addEventListener('click', async (ev)=>{
      ev.preventDefault();
      const row = input.closest('tr');
      const typeSel = row ? row.querySelector('[data-field="mapping_type"]') : byId('aimNewType');
      const type = typeSel ? typeSel.value : 'item';
      const value = String(input.value||'').trim();
      if(type !== 'item' || !value){ return; }
      // If we don't have preview data yet, fetch it now
      if(!previewData.get(input)){
        try{
          const res = await fetch('/api/get_items.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ item_ids:[value] })});
          const data = await res.json();
          if(Array.isArray(data) && data.length>0){ setPreviewData(input, data[0]); }
        }catch(e){ /* ignore */ }
      }
      showPreviewPopover(input);
    });
  }

  async function loadMappings(){
    const room = byId('aimRoomSelect').value;
    if(!room){ byId('aimMappingsList').innerHTML = '<div class="p-3 text-gray-500">Select a room to view mappings.<\/div>'; return; }
    const [exp, live] = await Promise.all([
      fetchJSON(`/api/area_mappings.php?action=get_mappings&room=${encodeURIComponent(room)}`),
      fetchJSON(`/api/area_mappings.php?action=get_live_view&room=${encodeURIComponent(room)}`)
    ]);
    const explicit = (exp && exp.data && exp.data.success && Array.isArray(exp.data.mappings)) ? exp.data.mappings : [];
    let derived = [];
    const meta = {};
    if (live && live.data && live.data.success) {
      derived = (live.data.mappings || []).map(m => ({...m, derived: true, id: m.id || ''}));
      meta.category = live.data.category;
      setMsg('aimMessage', `Loaded data. ${explicit.length? 'Explicit mappings present.' : 'Showing derived live view (read-only) from room_maps and category items.'}`, 'ok');
    } else {
      const snippet = (live && live.text ? live.text : '').slice(0,200);
      const status = live && live.res ? `${live.res.status} ${live.res.statusText}` : 'n/a';
      setMsg('aimMessage', `Live view failed (status ${status}). ${live && live.data && live.data.message ? live.data.message : ''} ${snippet ? '(Raw: '+snippet+')' : ''}`, 'error');
    }
    renderCombined(explicit, derived, meta);
  }

  async function addMapping(){
    const room = byId('aimRoomSelect').value;
    const selInput = byId('aimNewArea') || byId('aimAreaSelector');
    const typeInput = byId('aimNewType') || byId('aimMappingType');
    const targetInput = byId('aimNewTarget') || byId('aimTargetId');
    const orderInput = byId('aimNewOrder');
    const sel = (selInput && selInput.value || '').trim();
    const type = (typeInput && typeInput.value) || 'item';
    const targetRaw = (targetInput && String(targetInput.value).trim()) || '';
    const display_order = orderInput ? (parseInt(orderInput.value,10)||0) : 0;
    if(!room){ setMsg('aimMessage','Choose a room first.','error'); return; }
    if(!sel){ setMsg('aimMessage','Area selector is required.','error'); return; }
    if(!targetRaw){ setMsg('aimMessage','Provide a target (SKU for item or Category ID).','error'); return; }
    const payload = { action:'add_mapping', room, area_selector: sel, mapping_type: type, display_order };
    if(type==='item') payload.item_sku = targetRaw; else payload.category_id = parseInt(targetRaw,10)||null;
    const { data } = await fetchJSON('/api/area_mappings.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    if(data && data.success){ setMsg('aimMessage','Mapping added.','ok'); byId('aimTargetId') && (byId('aimTargetId').value=''); await loadMappings(); }
    else { setMsg('aimMessage','Failed to add mapping' + (data&&data.message?': '+data.message:''), 'error'); }
  }

  async function updateMapping(id){
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if(!row) return;
    const mapping_type = row.querySelector('[data-field="mapping_type"]').value;
    const area_selector = row.querySelector('[data-field="area_selector"]').value.trim();
    const target = row.querySelector('[data-field="target"]').value.trim();
    const display_order = parseInt(row.querySelector('[data-field="display_order"]').value, 10) || 0;
    const payload = { id, mapping_type, area_selector, display_order };
    if(mapping_type==='item') payload.item_sku = target; else payload.category_id = parseInt(target,10)||null;
    const { data } = await fetchJSON('/api/area_mappings.php', { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    if(data && data.success){ setMsg('aimMessage','Mapping updated.','ok'); await loadMappings(); }
    else { setMsg('aimMessage','Failed to update mapping' + (data&&data.message?': '+data.message:''), 'error'); }
  }

  async function convertDerived(area_selector, sku){
    const room = byId('aimRoomSelect').value;
    if(!room || !sku || !area_selector) return;
    const payload = { action:'add_mapping', room, area_selector, mapping_type:'item', item_sku: sku, display_order: 0 };
    const { data } = await fetchJSON('/api/area_mappings.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    if(data && data.success){ setMsg('aimMessage','Converted to explicit mapping.','ok'); await loadMappings(); }
    else { setMsg('aimMessage','Failed to convert: ' + (data&&data.message?data.message:''), 'error'); }
  }

  async function deleteMapping(id){
    const { data } = await fetchJSON('/api/area_mappings.php', { method:'DELETE', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id }) });
    if(data && data.success){ setMsg('aimMessage','Mapping deleted.','ok'); await loadMappings(); }
    else { setMsg('aimMessage','Failed to delete mapping' + (data&&data.message?': '+data.message:''), 'error'); }
  }

  function onClick(e){
    const btn = e.target.closest('[data-action]'); if(!btn) return;
    const act = btn.getAttribute('data-action');
    if(act==='aim-delete'){ e.preventDefault(); deleteMapping(parseInt(btn.getAttribute('data-id'),10)); }
    if(act==='aim-save'){ e.preventDefault(); updateMapping(parseInt(btn.getAttribute('data-id'),10)); }
    if(act==='aim-add-explicit' || btn.id==='aimAddBtn'){ e.preventDefault(); addMapping(); }
    if(act==='aim-convert'){ e.preventDefault(); convertDerived(btn.getAttribute('data-area'), btn.getAttribute('data-sku')); }
    if(act==='aim-sync-order'){ e.preventDefault(); syncOrderFromDerived(); }
    if(act==='aim-resequence-all'){ e.preventDefault(); re_sequence_all(); }
    if(act==='aim-apply-empty'){ e.preventDefault(); applyDerivedForEmpty(); }
  }
  function onChange(e){
    if(e.target && e.target.id==='aimRoomSelect'){ loadMappings(); }
  }

  function run(){
    document.addEventListener('click', onClick);
    document.addEventListener('change', onChange);
    loadMappings();
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', run, {once:true});
  else run();
})();

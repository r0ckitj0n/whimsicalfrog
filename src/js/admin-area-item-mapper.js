// Admin: Area-Item Mapper UI logic
(function(){
  const byId = (id) => document.getElementById(id);

  function setMsg(elId, html, type){
    const el = byId(elId); if(!el) return;
    const cls = type==='error' ? 'text-red-700 bg-red-50 border-red-200' : 'text-green-700 bg-green-50 border-green-200';
    el.innerHTML = `<div class="border ${cls} rounded px-3 py-2 text-sm">${html}</div>`;
  }

  async function fetchJSON(url, opts){
    const res = await fetch(url, opts);
    const text = await res.text();
    let data = null;
    try { data = JSON.parse(text); }
    catch(e){ try { console.error('[AreaItem] Invalid JSON from', url, '\nRaw:', text); } catch(_) {} }
    return {res, data, text};
  }

  function renderMappings(list){
    const tgt = byId('aimMappingsList'); if(!tgt) return;
    if(!Array.isArray(list) || list.length===0){ tgt.innerHTML = '<div class="p-3 text-gray-500">No mappings for this room.<\/div>'; return; }
    const rows = list.map(m => {
      const lbl = m.mapping_type === 'category' ? `Category #${m.category_id||''}` : `Item #${m.item_id||''}`;
      return `<div class="flex items-center justify-between px-3 py-2 border-b">
        <div>
          <div class="font-medium">${m.area_selector||''} → ${lbl}</div>
          <div class="text-xs text-gray-500">Mapping #${m.id} · order ${m.display_order||0}</div>
        </div>
        <div class="flex gap-2 items-center">
          <button class="btn btn-xs btn-danger" data-action="aim-delete" data-id="${m.id}">Delete<\/button>
        </div>
      <\/div>`;
    }).join('');
    tgt.innerHTML = `<div class="border rounded">${rows}<\/div>`;
  }

  async function loadMappings(){
    const room = byId('aimRoomSelect').value;
    if(!room){ byId('aimMappingsList').innerHTML = '<div class="p-3 text-gray-500">Select a room to view mappings.<\/div>'; return; }
    const { data, text } = await fetchJSON(`/api/area_mappings.php?action=get_mappings&room=${encodeURIComponent(room)}`);
    if(data && data.success){ renderMappings(data.mappings || []); }
    else {
      const snippet = (text||'').slice(0,200);
      setMsg('aimMessage', 'Failed to load mappings' + (data&&data.message?': '+data.message:'') + (text&&!data? ' (Raw: '+snippet+')':''), 'error');
    }
  }

  async function addMapping(){
    const room = byId('aimRoomSelect').value;
    const sel = byId('aimAreaSelector').value.trim();
    const type = byId('aimMappingType').value;
    const targetId = parseInt(byId('aimTargetId').value, 10) || null;
    if(!room){ setMsg('aimMessage','Choose a room first.','error'); return; }
    if(!sel){ setMsg('aimMessage','Area selector is required.','error'); return; }
    if(!targetId){ setMsg('aimMessage','Provide a valid ID for the item/category.','error'); return; }
    const payload = { action:'add_mapping', room, area_selector: sel, mapping_type: type };
    if(type==='item') payload.item_id = targetId; else payload.category_id = targetId;
    const { data } = await fetchJSON('/api/area_mappings.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    if(data && data.success){ setMsg('aimMessage','Mapping added.','ok'); byId('aimTargetId').value=''; await loadMappings(); }
    else { setMsg('aimMessage','Failed to add mapping' + (data&&data.message?': '+data.message:''), 'error'); }
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
    if(btn.id==='aimAddBtn'){ e.preventDefault(); addMapping(); }
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

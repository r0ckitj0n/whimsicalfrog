// Admin: Room Map Manager UI logic
import { ApiClient } from '../core/api-client.js';
(function(){
  const byId = (id) => document.getElementById(id);

  function setMsg(elId, html, type){
    const el = byId(elId); if(!el) return;
    const cls = type==='error' ? 'text-red-700 bg-red-50 border-red-200' : 'text-green-700 bg-green-50 border-green-200';
    el.innerHTML = `<div class="border ${cls} rounded px-3 py-2 text-sm">${html}</div>`;
  }

  async function fetchJSON(url, opts){
    try {
      const result = await ApiClient.request(url, opts || {});
      if (typeof result === 'string') {
        return { res: null, data: null, text: result };
      }
      return { res: null, data: result, text: JSON.stringify(result) };
    } catch (e) {
      const msg = String(e && e.message ? e.message : e);
      try { console.error('[RoomMaps] API error', url, msg); } catch(_) {}
      return { res: null, data: null, text: msg };
    }
  }

  async function loadMaps(){
    const room = byId('rmRoomSelect').value;
    if(!room){ byId('rmMapsList').innerHTML = '<div class="p-3 text-gray-500">Select a room to view maps.<\/div>'; return; }
    const { data, text } = await fetchJSON(`/api/room_maps.php?room=${encodeURIComponent(room)}`);
    if(!data || !data.success){
      const snippet = (text||'').slice(0,200).replace(/[\s\S]/g, c => c);
      byId('rmMapsList').innerHTML = `<div class="p-3 text-red-600">Failed to load maps${data&&data.message?': '+data.message:''}. ${text&&!data? '(Raw: '+snippet+')' : ''}<\/div>`;
      return;
    }
    const maps = data.maps || [];
    if(!maps.length){ byId('rmMapsList').innerHTML = '<div class="p-3 text-gray-500">No maps yet for this room.<\/div>'; return; }
    const rows = maps.map(m => {
      const active = m.is_active ? '<span class="ml-2 px-2 py-0.5 text-xs rounded bg-green-100 text-green-700">Active<\/span>' : '';
      return `<div class="flex items-center justify-between px-3 py-2 border-b">
        <div>
          <div class="font-medium">${m.map_name||'Untitled'} ${active}</div>
          <div class="text-xs text-gray-500">#${m.id}</div>
        </div>
        <div class="flex gap-2">
          <button class="btn btn-xs" data-action="rm-apply" data-id="${m.id}">Apply<\/button>
          <button class="btn btn-xs btn-danger" data-action="rm-delete" data-id="${m.id}">Delete<\/button>
        </div>
      <\/div>`;
    }).join('');
    byId('rmMapsList').innerHTML = `<div class="border rounded">${rows}<\/div>`;
  }

  async function createMap(){
    const room = byId('rmRoomSelect').value;
    const name = byId('rmMapName').value.trim() || 'Untitled';
    const coordsRaw = byId('rmCoordinates').value.trim();
    if(!room){ setMsg('rmMessage','Choose a room first.','error'); return; }
    let coords;
    try{ coords = coordsRaw ? JSON.parse(coordsRaw) : []; }
    catch(e){ setMsg('rmMessage','Coordinates must be valid JSON.','error'); return; }
    const payload = { action:'save', room, map_name:name, coordinates:coords };
    const { data, text } = await fetchJSON('/api/room_maps.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
    if(data && data.success){ setMsg('rmMessage','Map saved.','ok'); await loadMaps(); }
    else { setMsg('rmMessage','Failed to save map'+(data&&data.message?': '+data.message:'')+(text&&!data? ' (Raw: '+text.slice(0,120)+')':''),'error'); }
  }

  async function applyMap(id){
    const room = byId('rmRoomSelect').value; if(!room) return;
    const payload = { action:'apply', room, map_id: id };
    const { data, text } = await fetchJSON('/api/room_maps.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
    if(data && data.success){ setMsg('rmMessage','Map applied.','ok'); await loadMaps(); }
    else { setMsg('rmMessage','Failed to apply map'+(data&&data.message?': '+data.message:'')+(text&&!data? ' (Raw: '+text.slice(0,120)+')':''),'error'); }
  }

  async function deleteMap(id){
    const { data, text } = await fetchJSON('/api/room_maps.php', { method:'DELETE', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ map_id:id })});
    if(data && data.success){ setMsg('rmMessage','Map deleted.','ok'); await loadMaps(); }
    else { setMsg('rmMessage','Failed to delete map'+(data&&data.message?': '+data.message:'')+(text&&!data? ' (Raw: '+text.slice(0,120)+')':''),'error'); }
  }

  function onClick(e){
    const btn = e.target.closest('[data-action]'); if(!btn) return;
    const act = btn.getAttribute('data-action');
    if(act==='rm-apply'){ e.preventDefault(); applyMap(btn.getAttribute('data-id')); }
    if(act==='rm-delete'){ e.preventDefault(); deleteMap(btn.getAttribute('data-id')); }
    if(btn.id==='rmCreateMapBtn'){ e.preventDefault(); createMap(); }
  }

  function onChange(e){
    if(e.target && e.target.id==='rmRoomSelect'){ loadMaps(); }
  }

  function run(){
    document.addEventListener('click', onClick);
    document.addEventListener('change', onChange);
    // initial state
    loadMaps();
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', run, {once:true});
  else run();
})();

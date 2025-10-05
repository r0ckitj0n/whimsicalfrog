// Admin: Room Map Editor (SVG polygon drawing over background)
import '../styles/admin-room-map-editor.css';
(function(){
  const byId = (id) => document.getElementById(id);

  const state = {
    room: '',
    bgUrl: '',
    drawing: false,
    currentPoints: [], // [[x,y], ...]
    polygons: [],      // array of { points: [[x,y], ...] }
  };

  function setMsg(html, type){
    const el = byId('rmeMessage'); if(!el) return;
    const cls = type==='error' ? 'text-red-700 bg-red-50 border-red-200' : 'text-green-700 bg-green-50 border-green-200';
    el.innerHTML = `<div class="border ${cls} rounded px-3 py-2 text-sm">${html}</div>`;
  }

  // Inject a CSS rule once, and toggle via data attributes to avoid inline styles
  let __rmeStyleEl = null;
  function ensureStyleEl(){
    if (__rmeStyleEl) return __rmeStyleEl;
    const el = document.createElement('style');
    el.type = 'text/css';
    document.head.appendChild(el);
    __rmeStyleEl = el;
    return el;
  }
  function applyCanvasBgFromData(){
    const canvas = byId('rmeCanvas'); if (!canvas) return;
    const url = (canvas.getAttribute('data-bg-url')||'').trim();
    if (!url) return;
    const el = ensureStyleEl();
    // Scope rule to the specific element id
    el.textContent = `#rmeCanvas[data-bg-url][data-bg-applied="1"]{background-image:url(${JSON.stringify(url)})}`;
    canvas.setAttribute('data-bg-applied','1');
  }
  function ensureBg(){
    const url = (byId('rmeBgUrl').value||'').trim();
    const canvas = byId('rmeCanvas');
    if (url && canvas){
      canvas.setAttribute('data-bg-url', url);
      applyCanvasBgFromData();
    }
  }

  function preload(url){
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(url);
      img.onerror = () => reject(url);
      img.src = url;
    });
  }

  async function setBgForRoom(room) {
    if (!room) return;
    let base = '';
    if (room === 'landing') {
      base = '/images/backgrounds/background-home';
    } else if (room === 'main') {
      base = '/images/backgrounds/background-room-main';
    } else {
      base = `/images/backgrounds/background-room${room}`;
    }

    const webp = `${base}.webp`;
    const png = `${base}.png`;
    try {
      await preload(webp);
      byId('rmeBgUrl').value = webp;
      ensureBg();
    } catch (_) {
      try {
        await preload(png);
        byId('rmeBgUrl').value = png;
        ensureBg();
      } catch (e) {
        setMsg(`Could not load background image for room '${room}'.`, 'error');
      }
    }
  }

  function svg(){ return byId('rmeSvg'); }

  function redraw(){
    // Clear SVG and draw all polygons + current poly
    const g = svg(); if(!g) return;
    while (g.firstChild) g.removeChild(g.firstChild);

    const NS = 'http://www.w3.org/2000/svg';
    const drawPoly = (pts, isActive) => {
      if (!pts || pts.length < 2) return;
      const el = document.createElementNS(NS, 'polyline');
      el.setAttribute('points', pts.map(p => p.join(',')).join(' '));
      el.setAttribute('fill', isActive ? 'rgba(59,130,246,0.15)' : 'rgba(16,185,129,0.15)');
      el.setAttribute('stroke', isActive ? '#3b82f6' : '#10b981');
      el.setAttribute('stroke-width', '2');
      g.appendChild(el);
    };

    state.polygons.forEach(poly => drawPoly(poly.points, false));
    drawPoly(state.currentPoints, true);

    // Draw active points as circles for feedback
    const NS2 = NS;
    state.currentPoints.forEach(([x,y]) => {
      const c = document.createElementNS(NS2, 'circle');
      c.setAttribute('cx', x);
      c.setAttribute('cy', y);
      c.setAttribute('r', 4);
      c.setAttribute('fill', '#2563eb');
      g.appendChild(c);
    });
  }

  function clientToSvgCoords(evt){
    svg(); const canvas = byId('rmeCanvas');
    const rect = canvas.getBoundingClientRect();
    const x = evt.clientX - rect.left;
    const y = evt.clientY - rect.top;
    // Clamp to container
    return [Math.max(0, Math.min(rect.width, x)), Math.max(0, Math.min(rect.height, y))];
  }

  function startPolygon(){
    state.drawing = true;
    state.currentPoints = [];
    redraw();
  }
  function addPoint(evt){
    if (!state.drawing) return;
    const pt = clientToSvgCoords(evt);
    state.currentPoints.push(pt);
    redraw();
  }
  function undoPoint(){
    if (!state.drawing) return;
    state.currentPoints.pop();
    redraw();
  }
  function finishPolygon(){
    if (!state.drawing) return;
    if (state.currentPoints.length >= 3) {
      state.polygons.push({ points: state.currentPoints.slice() });
      state.currentPoints = [];
      state.drawing = false;
      syncTextarea();
      redraw();
    } else {
      setMsg('A polygon needs at least 3 points.','error');
    }
  }
  function clearAll(){
    state.currentPoints = [];
    state.polygons = [];
    state.drawing = false;
    syncTextarea();
    redraw();
  }

  function syncTextarea(){
    const ta = byId('rmeCoords'); if(!ta) return;
    const payload = { polygons: state.polygons.map(p => ({ points: p.points })) };
    ta.value = JSON.stringify(payload, null, 2);
  }
  function loadFromTextarea(){
    const ta = byId('rmeCoords'); if(!ta) return;
    try{
      const obj = JSON.parse(ta.value || '{}');
      if (obj && Array.isArray(obj.polygons)) {
        state.polygons = obj.polygons.map(p => ({ points: Array.isArray(p.points) ? p.points : [] }));
        state.currentPoints = [];
        state.drawing = false;
        redraw();
      }
    } catch(e){ setMsg('Invalid JSON in Coordinates textarea.','error'); }
  }

  async function fetchJSON(url, opts){
    const res = await fetch(url, opts);
    const text = await res.text();
    let data = null; try { data = JSON.parse(text); } catch(_){ console.error('[MapEditor] Invalid JSON', text); }
    return { res, data, text };
  }

  async function loadActive(){
    const room = (byId('rmeRoomSelect').value||'').trim();
    if (!room) { setMsg('Choose a room first.','error'); return; }
    const { data, text: _text } = await fetchJSON(`/api/room_maps.php?room=${encodeURIComponent(room)}&active_only=true`);
    if (!data || !data.success) {
      setMsg('Failed to load active map' + (data&&data.message?': '+data.message:''), 'error');
      return;
    }
    const coords = (data.map && data.map.coordinates) || {};
    if (coords && Array.isArray(coords.polygons)) {
      state.polygons = coords.polygons.map(p => ({ points: Array.isArray(p.points) ? p.points : [] }));
      state.currentPoints = [];
      state.drawing = false;
      syncTextarea();
      redraw();
      setMsg('Active map loaded.','ok');
    } else {
      state.polygons = [];
      state.currentPoints = [];
      state.drawing = false;
      syncTextarea();
      redraw();
      setMsg('No active map found for this room.','error');
    }
  }

  async function saveMap(){
    const room = (byId('rmeRoomSelect').value||'').trim();
    if (!room) { setMsg('Choose a room first.','error'); return; }
    const name = 'Editor ' + new Date().toLocaleString();
    // Prepare coordinates structure
    const coords = { polygons: state.polygons.map(p => ({ points: p.points })) };
    const payload = { action:'save', room, map_name: name, coordinates: coords };
    const { data, text } = await fetchJSON('/api/room_maps.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    if (data && data.success) { setMsg('Map saved. Use Room Map Manager to apply it.','ok'); }
    else { setMsg('Failed to save map' + (data&&data.message?': '+data.message:'') + (text&&!data? ' (Raw: '+text.slice(0,120)+')':''), 'error'); }
  }

  function onClick(evt){
    const t = evt.target;
    if (t && t.id === 'rmeStartPolyBtn') { evt.preventDefault(); startPolygon(); return; }
    if (t && t.id === 'rmeFinishPolyBtn') { evt.preventDefault(); finishPolygon(); return; }
    if (t && t.id === 'rmeUndoPointBtn') { evt.preventDefault(); undoPoint(); return; }
    if (t && t.id === 'rmeClearBtn') { evt.preventDefault(); clearAll(); return; }
    if (t && t.id === 'rmeLoadActiveBtn') { evt.preventDefault(); loadActive(); return; }
    if (t && t.id === 'rmeSaveMapBtn') { evt.preventDefault(); saveMap(); return; }
    if (t && t.id === 'rmeBgUrl') { ensureBg(); return; }
    if (t && t.id === 'rmeFullscreenBtn') {
      evt.preventDefault();
      const frame = byId('rmeFrame');
      if (!frame) return;
      const active = frame.classList.toggle('rme-fullscreen');
      t.textContent = active ? 'Exit Fullscreen' : 'Fullscreen';
      // Redraw to ensure SVG sizing updates to new container size
      setTimeout(redraw, 0);
      return;
    }
  }

  function onCanvasClick(evt){
    if (!state.drawing) return;
    evt.preventDefault();
    evt.stopPropagation();
    addPoint(evt);
  }

  function onChange(evt) {
    if (evt.target && evt.target.id === 'rmeRoomSelect') {
      state.room = evt.target.value;
      setBgForRoom(state.room);
    }
    if (evt.target && evt.target.id === 'rmeCoords') {
      loadFromTextarea();
    }
  }

  async function loadRooms() {
    const select = byId('rmeRoomSelect');
    if (!select) return;

    try {
      const response = await fetch('/api/get_rooms.php');
      if (!response.ok) throw new Error('API request failed');
      const rooms = await response.json();

      if (Array.isArray(rooms)) {
        rooms.forEach(room => {
          const option = document.createElement('option');
          option.value = room.id; // room_number
          option.textContent = room.name;
          select.appendChild(option);
        });
      }
    } catch (e) {
      setMsg('Could not load room list from the database.', 'error');
      console.error('Failed to load rooms:', e);
    }
  }

  function run() {
    ensureBg();
    loadRooms(); // Fetch and populate rooms
    document.addEventListener('click', onClick);
    document.addEventListener('change', onChange);
    const canvas = byId('rmeCanvas');
    if (canvas) canvas.addEventListener('click', onCanvasClick);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, { once:true });
  else run();
})();

// Admin: Room Map Editor (SVG polygon drawing over background)
import '../styles/admin-room-map-editor.css';
import { ApiClient } from '../core/api-client.js';
(function(){
  const byId = (id) => document.getElementById(id);

  const state = {
    room: '',
    bgUrl: '',
    // Rectangle editor state
    rectangles: [],
    selectedId: null,
    selectedIds: [],
    tool: 'select', // 'select' | 'create'
    drag: {
      mode: 'none', // 'none' | 'creating' | 'moving' | 'resizing'
      startX: 0,
      startY: 0,
      orig: null,   // original rect snapshot {top,left,width,height}
      handle: null, // which handle during resize
      group: null,
      reorderTargetId: null,
    },
    snap: { enabled: false, size: 5 },
    grid: { color: '#e5e7eb' },
    // Per-room rendering metadata
    roomMeta: new Map(), // key: room_number, value: { render_context: 'page'|'modal', target_aspect: number|null }
    rebaseKey: '',
  };

  function setMsg(html, type){
    const el = byId('rmeMessage'); if(!el) return;
    let cls, icon;
    if (type === 'error') {
      cls = 'text-red-700 bg-red-50 border-red-200';
      icon = '❌';
    } else if (type === 'info') {
      cls = 'text-blue-700 bg-blue-50 border-blue-200';
      icon = 'ℹ️';
    } else {
      cls = 'text-green-700 bg-green-50 border-green-200';
      icon = '✅';
    }
    el.innerHTML = `<div class="border ${cls} rounded px-3 py-2 text-sm">${icon} ${html}</div>`;
  }

  // Show/hide the intro hint paragraph in the sidebar intro panel
  function setIntroHintVisible(show){
    const el = byId('rmeIntroHint');
    if (!el) return;
    if (show) el.classList.remove('hidden'); else el.classList.add('hidden');
  }
  function updateIntroHintFromState(){
    const hasAreas = Array.isArray(state.rectangles) && state.rectangles.length > 0;
    setIntroHintVisible(!hasAreas);
  }

  // Normalize room value for room_maps API calls
  function normalizeRoomForApi(room){
    if (!room) return null;
    const s = String(room).trim();
    if (s === 'main') return '0';
    if (/^\d+$/.test(s)) return s; // numeric including 0
    if (/^[A-Za-z]$/.test(s)) return s.toUpperCase(); // support letter rooms like 'A'
    return null;
  }

  // Background image handling via <img> to align with SVG overlay scaling
  function updateFrameAspectForRoom(room, imgW, imgH){
    const frame = byId('rmeFrame'); if (!frame) return;
    const meta = state.roomMeta.get(String(room||''));
    const st = ensureDynStyle();
    const usePage = (meta && meta.render_context === 'page') || (!meta && (room === 'A' || room === '0'));
    const explicit = meta && typeof meta.target_aspect === 'number' && meta.target_aspect > 0 ? meta.target_aspect : null;
    if (usePage){
      const ratio = explicit || getSiteViewportAspect();
      st.textContent = `#rmeFrame.match-viewport{aspect-ratio:${ratio};}`;
      frame.classList.add('match-viewport');
      frame.classList.remove('match-image');
    } else {
      const ratio = explicit || ((imgW && imgH) ? (imgW / imgH) : 16/9);
      st.textContent = `#rmeFrame.match-image{aspect-ratio:${ratio};}`;
      frame.classList.add('match-image');
      frame.classList.remove('match-viewport');
    }
  }

  function getHeaderOffsetPx(){
    try {
      const pdoc = window.parent && window.parent.document;
      if (!pdoc) return 0;
      const sources = [pdoc.documentElement, pdoc.body];
      for (const node of sources){
        const cs = window.parent.getComputedStyle(node);
        const v = (cs.getPropertyValue('--wf-overlay-offset') || cs.getPropertyValue('--wf-header-offset') || cs.getPropertyValue('--header-offset') || '').trim();
        if (v){ const n = parseFloat(v); if (!isNaN(n)) return n; }
      }
    } catch(_){}
    return 0;
  }

  function getSiteViewportAspect(){
    try {
      const pw = (window.parent && window.parent.innerWidth) || window.innerWidth || 1;
      const ph = (window.parent && window.parent.innerHeight) || window.innerHeight || 1;
      // Visible area for page overlays accounts for overlay/header offset
      const hoff = getHeaderOffsetPx();
      return Math.max(1, pw) / Math.max(1, ph - hoff);
    } catch(_){
      return Math.max(1, (window.innerWidth||1)) / Math.max(1, (window.innerHeight||1));
    }
  }
  function setupSvgForImage(img){
    const s = svg(); if (!s || !img) return;
    const w = img.naturalWidth || 0;
    const h = img.naturalHeight || 0;
    if (w > 0 && h > 0){
      s.setAttribute('viewBox', `0 0 ${w} ${h}`);
      const roomVal = (byId('rmeRoomSelect') && byId('rmeRoomSelect').value || '').trim();
      const meta = state.roomMeta.get(String(roomVal||''));
      const isPage = (meta && meta.render_context === 'page') || (!meta && (roomVal === 'A' || roomVal === '0'));
      // Live pages (Landing/Main) use center/cover backgrounds; match with xMidYMid
      s.setAttribute('preserveAspectRatio', isPage ? 'xMidYMid slice' : 'xMidYMid slice');
      const bg = byId('rmeBgImg');
      if (bg){
        // Center for page and modal to mirror live site backgrounds
        bg.classList.toggle('pos-top', false);
        bg.classList.toggle('pos-center', true);
      }
      // Rebase existing rectangles to this image's natural size when needed (page rooms)
      maybeRebaseRectsForRoom(roomVal, w, h);
      // Adjust editor frame aspect to match render context for the selected room, then redraw
      updateFrameAspectForRoom(roomVal, w, h);
      redraw();
    }
  }
  function refreshFrameAspect(){
    const roomVal = (byId('rmeRoomSelect') && byId('rmeRoomSelect').value || '').trim();
    const img = byId('rmeBgImg');
    const w = img && img.naturalWidth || 0;
    const h = img && img.naturalHeight || 0;
    updateFrameAspectForRoom(roomVal, w, h);
  }

  function maybeRebaseRectsForRoom(room, natW, natH){
    if (!room || !natW || !natH) return;
    const meta = state.roomMeta.get(String(room));
    const isPage = (meta && meta.render_context === 'page') || (!meta && (room === 'A' || room === '0'));
    if (!isPage) return; // Do not rebase modal rooms; prevents double-scaling (e.g., room 2)
    // Canonical authoring baseline for page rooms used in site CSS: 1280x896
    const baseW = 1280, baseH = 896;
    if (Math.abs(natW - baseW) < 1 && Math.abs(natH - baseH) < 1) return; // no rebase needed
    const key = `${room}|${natW}x${natH}`;
    if (state.rebaseKey === key) return; // already rebased for this image size
    const sx = natW / baseW;
    const sy = natH / baseH;
    state.rectangles = state.rectangles.map(r => ({
      ...r,
      left: r.left * sx,
      top: r.top * sy,
      width: r.width * sx,
      height: r.height * sy,
    }));
    state.rebaseKey = key;
    syncTextarea();
  }
  function applyCanvasBgFromData(){
    const canvas = byId('rmeCanvas'); if (!canvas) return;
    const img = byId('rmeBgImg'); if (!img) return;
    const url = (canvas.getAttribute('data-bg-url')||'').trim();
    if (!url) { img.removeAttribute('src'); return; }
    if (img.src === url) { if (img.complete) setupSvgForImage(img); return; }
    img.onload = () => setupSvgForImage(img);
    img.onerror = () => setMsg('Could not load background image.', 'error');
    img.src = url;
    canvas.setAttribute('data-bg-applied','1');
  }
  function ensureBg(){
    const url = (byId('rmeBgUrl').value||'').trim();
    const canvas = byId('rmeCanvas');
    if (!canvas) return;
    if (url){
      canvas.setAttribute('data-bg-url', url);
    } else {
      canvas.removeAttribute('data-bg-url');
    }
    applyCanvasBgFromData();
  }

  // Ensure the canvas frame uses the same viewport aspect ratio as the live page
  let __rmeDynStyle = null;
  function ensureDynStyle(){
    if (__rmeDynStyle) return __rmeDynStyle;
    const el = document.createElement('style');
    el.type = 'text/css';
    document.head.appendChild(el);
    __rmeDynStyle = el;
    return el;
  }
  // Note: we compute container aspect contextually in setupSvgForImage() via ensureDynStyle()

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
    const r = String(room).trim();
    if (/^[Aa]$/.test(r)) {
      base = '/images/backgrounds/background-roomA';
    } else if (r === 'main') {
      base = '/images/backgrounds/background-room0';
    } else if (r === '0' || /^\d+$/.test(r)) {
      base = `/images/backgrounds/background-room${r}`;
    } else {
      // Fallback to main if unknown value
      base = '/images/backgrounds/background-room0';
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

  function canvasRect(){
    const canvas = byId('rmeCanvas');
    return canvas ? canvas.getBoundingClientRect() : { left:0, top:0, width:0, height:0 };
  }

  function nextAreaSelector(){
    const n = state.rectangles.length + 1;
    return `.area-${n}`;
  }

  function findRectById(id){
    return state.rectangles.find(r => r.id === id) || null;
  }

  function isSelected(id){ return state.selectedIds.includes(id); }

  function setSelection(ids){
    const uniq = Array.from(new Set(ids.filter(Boolean)));
    state.selectedIds = uniq;
    state.selectedId = uniq.length === 1 ? uniq[0] : null;
  }

  function selectRect(id, additive){
    if (additive){
      if (isSelected(id)) setSelection(state.selectedIds.filter(x => x !== id));
      else setSelection([...state.selectedIds, id]);
    } else {
      setSelection([id]);
    }
    redraw();
    renderAreaList();
  }

  function ensureAreaList(){
    // Area list is now part of the static HTML template
    return document.getElementById('rmeAreaList');
  }

  function renderAreaList(){
    const list = ensureAreaList();
    if (!list) return;
    if (!state.rectangles.length){ list.innerHTML = '<div class="p-2 text-gray-500">No areas defined.</div>'; return; }
    list.innerHTML = state.rectangles.map((r, idx) => {
      const sel = isSelected(r.id) ? ' rme-area-row is-selected' : ' rme-area-row';
      const name = r.selector || `.area-${idx+1}`;
      return `<div class="${sel}" data-row data-id="${r.id}" draggable="true">
        <div class="flex items-center gap-2">
          <input class="form-input form-input-sm w-36" data-field="selector" data-id="${r.id}" value="${name}">
          <div class="text-xs text-gray-500">(${Math.round(r.left)},${Math.round(r.top)}) ${Math.round(r.width)}×${Math.round(r.height)}</div>
        </div>
        <div class="flex gap-1">
          <button class="btn btn-xs" data-action="rme-up" data-id="${r.id}" title="Move Up">↑</button>
          <button class="btn btn-xs" data-action="rme-down" data-id="${r.id}" title="Move Down">↓</button>
          <button class="btn btn-xs" data-action="rme-dup" data-id="${r.id}" title="Duplicate">⧉</button>
          <button class="btn btn-xs" data-action="rme-select" data-id="${r.id}">Select</button>
          <button class="btn btn-xs btn-danger" data-action="rme-delete" data-id="${r.id}">Delete</button>
        </div>
      </div>`;
    }).join('');
  }

  function redraw(){
    const g = svg(); if(!g) return;
    while (g.firstChild) g.removeChild(g.firstChild);
    const NS = 'http://www.w3.org/2000/svg';

    // Optional grid overlay when snap is enabled
    if (state.snap.enabled){
      // Use SVG viewBox width/height for grid spacing so it aligns with image space
      const vb = (svg().getAttribute('viewBox')||'').trim();
      let vbW = 0, vbH = 0;
      if (vb) {
        const parts = vb.split(/\s+/).map(Number);
        if (parts.length === 4) { vbW = parts[2]||0; vbH = parts[3]||0; }
      }
      if (!vbW || !vbH) { const rect = canvasRect(); vbW = rect.width; vbH = rect.height; }
      const step = Math.max(1, state.snap.size|0);
      for (let x = step; x < vbW; x += step){
        const vl = document.createElementNS(NS, 'line');
        vl.setAttribute('x1', x);
        vl.setAttribute('y1', 0);
        vl.setAttribute('x2', x);
        vl.setAttribute('y2', vbH);
        vl.setAttribute('class', 'rme-gridline');
        vl.setAttribute('stroke', state.grid.color || '#e5e7eb');
        g.appendChild(vl);
      }
      for (let y = step; y < vbH; y += step){
        const hl = document.createElementNS(NS, 'line');
        hl.setAttribute('x1', 0);
        hl.setAttribute('y1', y);
        hl.setAttribute('x2', vbW);
        hl.setAttribute('y2', y);
        hl.setAttribute('class', 'rme-gridline');
        hl.setAttribute('stroke', state.grid.color || '#e5e7eb');
        g.appendChild(hl);
      }
    }

    // Draw rectangles
    state.rectangles.forEach(r => {
      const el = document.createElementNS(NS, 'rect');
      el.setAttribute('x', r.left);
      el.setAttribute('y', r.top);
      el.setAttribute('width', Math.max(0, r.width));
      el.setAttribute('height', Math.max(0, r.height));
      el.setAttribute('data-id', r.id);
      let cls = 'rme-rect';
      if (isSelected(r.id)) cls += ' is-selected';
      if (state.drag.reorderTargetId && state.drag.reorderTargetId === r.id) cls += ' reorder-target';
      el.setAttribute('class', cls);
      g.appendChild(el);
    });

    // Draw handles for selected
    const sel = state.rectangles.find(x => x.id === state.selectedId);
    if (sel){
      const handles = calcHandles(sel);
      handles.forEach(h => {
        const hh = document.createElementNS(NS, 'rect');
        hh.setAttribute('x', h.x - 5);
        hh.setAttribute('y', h.y - 5);
        hh.setAttribute('width', 10);
        hh.setAttribute('height', 10);
        hh.setAttribute('data-id', sel.id);
        hh.setAttribute('data-handle', h.name);
        hh.setAttribute('class', 'rme-handle');
        g.appendChild(hh);
      });
    }
  }

  function clientToSvgCoords(evt){
    const s = svg(); if (!s) return [0,0];
    const pt = s.createSVGPoint();
    pt.x = evt.clientX;
    pt.y = evt.clientY;
    const inv = s.getScreenCTM();
    try {
      const res = pt.matrixTransform(inv.inverse());
      return [res.x, res.y];
    } catch (_) {
      // Fallback to canvas pixel coords if CTM not ready
      const rect = canvasRect();
      const x = evt.clientX - rect.left;
      const y = evt.clientY - rect.top;
      return [Math.max(0, Math.min(rect.width, x)), Math.max(0, Math.min(rect.height, y))];
    }
  }

  function calcHandles(r){
    const x1 = r.left, y1 = r.top;
    const x2 = r.left + r.width, y2 = r.top + r.height;
    const cx = (x1 + x2)/2, cy = (y1 + y2)/2;
    return [
      { name:'nw', x:x1, y:y1 },
      { name:'n',  x:cx, y:y1 },
      { name:'ne', x:x2, y:y1 },
      { name:'e',  x:x2, y:cy },
      { name:'se', x:x2, y:y2 },
      { name:'s',  x:cx, y:y2 },
      { name:'sw', x:x1, y:y2 },
      { name:'w',  x:x1, y:cy },
    ];
  }

  function startCreate(){
    state.tool = 'create';
    setMsg('Click and drag on the canvas to draw a new area.', 'ok');
  }

  function clearAll(){
    state.rectangles = [];
    state.selectedId = null;
    syncTextarea();
    redraw();
    renderAreaList();
  }

  // Mouse interactions (create/move/resize)
  function onMouseDown(evt){
    const target = evt.target;
    const [x,y] = clientToSvgCoords(evt);

    // Handle resize handles first
    const handle = target && target.getAttribute && target.getAttribute('data-handle');
    if (handle){
      const id = target.getAttribute('data-id');
      const r = findRectById(id);
      if (r){
        state.selectedId = r.id;
        state.drag.mode = 'resizing';
        state.drag.startX = x; state.drag.startY = y;
        state.drag.orig = { top:r.top, left:r.left, width:r.width, height:r.height };
        state.drag.handle = handle;
        evt.preventDefault(); return;
      }
    }

    // If clicking on a rectangle, start moving
    const idAttr = target && target.getAttribute && target.getAttribute('data-id');
    if (idAttr){
      const r = findRectById(idAttr);
      if (r){
        if (evt.metaKey || evt.ctrlKey) {
          selectRect(r.id, true);
          evt.preventDefault(); return;
        }
        selectRect(r.id, false);
        state.drag.mode = 'moving';
        state.drag.startX = x; state.drag.startY = y;
        state.drag.orig = { top:r.top, left:r.left, width:r.width, height:r.height };
        state.drag.group = state.selectedIds.map(id => {
          const rr = findRectById(id);
          return rr ? { id: rr.id, left: rr.left, top: rr.top } : null;
        }).filter(Boolean);
        evt.preventDefault(); return;
      }
    }

    // Otherwise, if tool=create, start a new rectangle
    if (state.tool === 'create'){
      const id = String(Date.now());
      const selector = nextAreaSelector();
      state.rectangles.push({ id, selector, top:y, left:x, width:0, height:0 });
      state.selectedId = id;
      state.drag.mode = 'creating';
      state.drag.startX = x; state.drag.startY = y;
      state.drag.orig = { top:y, left:x, width:0, height:0 };
      redraw();
      // Hide the intro hint once the first area is being created
      setIntroHintVisible(false);
      evt.preventDefault(); return;
    }

    // Select none when clicking on empty space
    state.selectedId = null;
    redraw();
    renderAreaList();
  }

  function onMouseMove(evt){
    if (state.drag.mode === 'none') return;
    const [x,y] = clientToSvgCoords(evt);
    const r = findRectById(state.selectedId);
    const dx = x - state.drag.startX;
    const dy = y - state.drag.startY;

    if (state.drag.mode === 'moving' && state.drag.orig){
      if (state.drag.group && state.drag.group.length > 1){
        state.drag.group.forEach(g => {
          const rr = findRectById(g.id);
          if (!rr) return;
          let nl = g.left + dx; let nt = g.top + dy;
          // Shift-constrain: lock to primary axis of movement
          if (evt.shiftKey){ if (Math.abs(dx) > Math.abs(dy)) nt = g.top; else nl = g.left; }
          if (state.snap.enabled) { nl = Math.round(nl / state.snap.size) * state.snap.size; nt = Math.round(nt / state.snap.size) * state.snap.size; }
          rr.left = Math.max(0, nl);
          rr.top = Math.max(0, nt);
        });
      } else if (r) {
        let nl = state.drag.orig.left + dx; let nt = state.drag.orig.top + dy;
        if (evt.shiftKey){ if (Math.abs(dx) > Math.abs(dy)) nt = state.drag.orig.top; else nl = state.drag.orig.left; }
        if (state.snap.enabled) { nl = Math.round(nl / state.snap.size) * state.snap.size; nt = Math.round(nt / state.snap.size) * state.snap.size; }
        r.left = Math.max(0, nl);
        r.top = Math.max(0, nt);
      }
      // Detect on-canvas reorder target under cursor
      state.drag.reorderTargetId = null;
      const el = document.elementFromPoint(evt.clientX, evt.clientY);
      if (el && el.getAttribute){
        const hid = el.getAttribute('data-id');
        const hHandle = el.getAttribute('data-handle');
        if (hid && !hHandle && !state.selectedIds.includes(hid)) state.drag.reorderTargetId = hid;
      }
      redraw();
    } else if (state.drag.mode === 'creating' && state.drag.orig){
      let l = Math.min(state.drag.startX, x);
      let t = Math.min(state.drag.startY, y);
      let w = Math.abs(x - state.drag.startX);
      let h = Math.abs(y - state.drag.startY);
      // Shift-constrain: lock to horizontal or vertical
      if (evt.shiftKey){ if (Math.abs(w) > Math.abs(h)) h = 1; else w = 1; }
      if (state.snap.enabled){
        l = Math.round(l / state.snap.size) * state.snap.size;
        t = Math.round(t / state.snap.size) * state.snap.size;
        w = Math.max(1, Math.round(w / state.snap.size) * state.snap.size);
        h = Math.max(1, Math.round(h / state.snap.size) * state.snap.size);
      }
      r.left = l; r.top = t; r.width = w; r.height = h;
      redraw();
    } else if (state.drag.mode === 'resizing' && state.drag.orig){
      const h = state.drag.handle;
      // Start with original
      const { top, left, width, height } = state.drag.orig;
      const right = left + width;
      const bottom = top + height;
      let nx1 = left, ny1 = top, nx2 = right, ny2 = bottom;
      if (h.includes('n')) ny1 = Math.min(y, bottom - 1);
      if (h.includes('s')) ny2 = Math.max(y, top + 1);
      if (h.includes('w')) nx1 = Math.min(x, right - 1);
      if (h.includes('e')) nx2 = Math.max(x, left + 1);
      let nl = nx1, nt = ny1, nw = nx2 - nx1, nh = ny2 - ny1;
      // Shift-constrain: lock to axis by zeroing the lesser delta
      if (evt.shiftKey){ if (Math.abs(nw) > Math.abs(nh)) nh = 1; else nw = 1; }
      if (state.snap.enabled){
        nl = Math.round(nl / state.snap.size) * state.snap.size;
        nt = Math.round(nt / state.snap.size) * state.snap.size;
        nw = Math.max(1, Math.round(nw / state.snap.size) * state.snap.size);
        nh = Math.max(1, Math.round(nh / state.snap.size) * state.snap.size);
      }
      r.left = nl; r.top = nt; r.width = nw; r.height = nh;
      redraw();
    }
  }

  function onMouseUp(){
    if (state.drag.mode !== 'none'){
      state.drag.mode = 'none';
      // If moving and reorder target set, perform block reorder
      if (state.drag.reorderTargetId && state.drag.orig){
        const targetId = state.drag.reorderTargetId;
        const targetIdx = state.rectangles.findIndex(r => r.id === targetId);
        const selIds = state.selectedIds.length ? state.selectedIds.slice() : (state.selectedId ? [state.selectedId] : []);
        if (targetIdx >= 0 && selIds.length){
          // Extract selected block in current order
          const block = state.rectangles.filter(r => selIds.includes(r.id));
          // Remove selected
          state.rectangles = state.rectangles.filter(r => !selIds.includes(r.id));
          // Recompute target index after removal
          let insertAt = state.rectangles.findIndex(r => r.id === targetId);
          if (insertAt < 0) insertAt = state.rectangles.length;
          state.rectangles.splice(insertAt, 0, ...block);
        }
      }
      state.drag.handle = null; state.drag.orig = null; state.drag.group = null; state.drag.reorderTargetId = null;
      syncTextarea();
      renderAreaList();
      redraw();
    }
  }
  async function clearAll(){
    if (state.rectangles.length === 0) {
      setMsg('No areas to clear.', 'info');
      return;
    }
    if (typeof window.showConfirmationModal !== 'function') { setMsg('Confirmation UI unavailable. Action canceled.', 'error'); return; }
    const ok = await window.showConfirmationModal({
      title: 'Clear All Areas',
      message: `Are you sure you want to remove all ${state.rectangles.length} area(s)? This cannot be undone.`,
      confirmText: 'Clear All',
      confirmStyle: 'danger',
      icon: '⚠️',
      iconType: 'danger'
    });
    if (!ok) return;
    state.rectangles = [];
    setSelection([]);
    syncTextarea();
    redraw();
    renderAreaList();
    setMsg('All areas removed. Don\'t forget to save if you want to keep this change!', 'info');
    setIntroHintVisible(true);
  }

  function syncTextarea(){
    const ta = byId('rmeCoords'); if(!ta) return;
    ta.value = JSON.stringify(state.rectangles.map(({selector, top,left,width,height})=>({selector, top,left,width,height})), null, 2);
  }

  function polygonsToRectangles(polys){
    // Convert [{points:[[x,y],...]}, ...] -> [{top,left,width,height}, ...]
    const rects = [];
    (polys||[]).forEach((p, i) => {
      const pts = Array.isArray(p.points) ? p.points : [];
      if (pts.length){
        const xs = pts.map(pt => pt[0]);
        const ys = pts.map(pt => pt[1]);
        const x1 = Math.min(...xs), x2 = Math.max(...xs);
        const y1 = Math.min(...ys), y2 = Math.max(...ys);
        rects.push({ selector: `.area-${i+1}`, top: y1, left: x1, width: x2 - x1, height: y2 - y1 });
      }
    });
    return rects;
  }

  function loadFromTextarea(){
    const ta = byId('rmeCoords'); if(!ta) return;
    try{
      const obj = JSON.parse(ta.value || '[]');
      let rects = [];
      if (Array.isArray(obj)) rects = obj;
      else if (obj && Array.isArray(obj.polygons)) rects = polygonsToRectangles(obj.polygons);
      state.rectangles = rects.map((r, i) => ({
        id: r.id ? String(r.id) : String(Date.now() + i),
        selector: r.selector || `.area-${i+1}`,
        top: Number(r.top)||0,
        left: Number(r.left)||0,
        width: Math.max(1, Number(r.width)||0),
        height: Math.max(1, Number(r.height)||0),
      }));
      state.selectedId = null;
      redraw();
      renderAreaList();
      updateIntroHintFromState();
    } catch(e){ setMsg('Invalid JSON in Coordinates textarea.','error'); }
  }

  async function fetchJSON(url, opts){
    try {
      const result = await ApiClient.request(url, opts || {});
      if (typeof result === 'string') {
        return { res: null, data: null, text: result };
      }
      return { res: null, data: result, text: JSON.stringify(result) };
    } catch (e) {
      return { res: null, data: null, text: String(e && e.message ? e.message : e) };
    }
  }

  async function loadActive(){
    console.log('[RoomMapEditor] loadActive called');
    const room = (byId('rmeRoomSelect').value||'').trim();
    console.log('[RoomMapEditor] Selected room:', room);
    if (!room) { setMsg('Please select a room from the dropdown first.','error'); return; }
    const roomParam = normalizeRoomForApi(room);
    console.log('[RoomMapEditor] Normalized room param:', roomParam);
    if (roomParam === null) { setMsg('This room type is not supported for maps. Please choose a numbered room.', 'error'); return; }
    
    setMsg('Loading map...', 'info');
    const url = `/api/room_maps.php?room=${encodeURIComponent(roomParam)}&active_only=true`;
    console.log('[RoomMapEditor] Fetching:', url);
    let dataObj = null;
    try {
      const { data } = await fetchJSON(url);
      dataObj = data || null;
    } catch(_) { dataObj = null; }
    console.log('[RoomMapEditor] API response data (active_only):', dataObj);

    // If no active map, fallback to latest map for the room
    if (!dataObj || dataObj.success === false || !dataObj.data || !dataObj.data.map) {
      const fallbackUrl = `/api/room_maps.php?room=${encodeURIComponent(roomParam)}`;
      console.log('[RoomMapEditor] Falling back to latest map list:', fallbackUrl);
      let fb = null;
      try {
        const { data } = await fetchJSON(fallbackUrl);
        fb = data || null;
      } catch(_) { fb = null; }
      if (fb && fb.success && fb.data && Array.isArray(fb.data.maps) && fb.data.maps.length > 0) {
        // Use the most recently created map
        const latest = fb.data.maps[0];
        dataObj = { success: true, data: { map: latest } };
      }
    }

    if (!dataObj || !dataObj.success || !dataObj.data || !dataObj.data.map) { setIntroHintVisible(true); setMsg('No map found for this room yet.', 'info'); return; }
    const coords = (dataObj.data.map && dataObj.data.map.coordinates);
    let rects = [];
    if (Array.isArray(coords)) rects = coords;
    else if (coords && Array.isArray(coords.polygons)) rects = polygonsToRectangles(coords.polygons);
    state.rectangles = (rects||[]).map((r, i) => ({
      id: r.id ? String(r.id) : String(Date.now() + i),
      selector: r.selector || `.area-${i+1}`,
      top: Number(r.top)||0,
      left: Number(r.left)||0,
      width: Math.max(1, Number(r.width)||0),
      height: Math.max(1, Number(r.height)||0),
    }));
    state.selectedId = null;
    syncTextarea();
    redraw();
    renderAreaList();
    if (state.rectangles.length) {
      setMsg(`Map loaded successfully! Found ${state.rectangles.length} clickable area(s).`, 'ok');
      setIntroHintVisible(false);
    } else {
      // Show guidance in the intro panel instead of crowding the message bar
      setIntroHintVisible(true);
      setMsg('No map found for this room yet.', 'info');
    }
    // Refresh saved maps list whenever we load a map for this room
    loadSavedMaps();
  }

  // --- Saved Maps Management (Apply/Delete/List) ---
  function renderSavedMapsList(maps){
    const host = byId('rmeMapsList');
    if (!host) return;
    if (!Array.isArray(maps) || maps.length === 0) {
      host.innerHTML = '<div class="p-3 text-gray-500">No maps yet for this room.</div>';
      return;
    }
    const rows = maps.map(m => {
      const active = m.is_active ? '<span class="ml-2 px-2 py-0.5 text-xs rounded bg-green-100 text-green-700">Active</span>' : '';
      const name = (m.map_name || 'Untitled').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      return `<div class="flex items-center justify-between px-3 py-2 border-b">
        <div>
          <div class="font-medium">${name} ${active}</div>
          <div class="text-xs text-gray-500">#${m.id}</div>
        </div>
        <div class="flex gap-2">
          <button class="btn btn-xs" data-action="rme-apply-map" data-id="${m.id}">Apply</button>
          <button class="btn btn-xs btn-danger" data-action="rme-delete-map" data-id="${m.id}">Delete</button>
        </div>
      </div>`;
    }).join('');
    host.innerHTML = `<div class="border rounded">${rows}</div>`;
  }

  async function loadSavedMaps(){
    const list = byId('rmeMapsList');
    const roomVal = (byId('rmeRoomSelect') && byId('rmeRoomSelect').value || '').trim();
    if (!list || !roomVal) return;
    list.innerHTML = '<div class="p-3 text-gray-500">Loading maps...</div>';
    const room = normalizeRoomForApi(roomVal);
    const { data } = await fetchJSON(`/api/room_maps.php?room=${encodeURIComponent(room)}`);
    if (data && data.success) {
      const maps = (data.data && data.data.maps) || data.maps || [];
      renderSavedMapsList(maps);
    } else {
      list.innerHTML = '<div class="p-3 text-red-600">Failed to load maps.</div>';
    }
  }

  async function applySavedMap(id){
    const roomVal = (byId('rmeRoomSelect') && byId('rmeRoomSelect').value || '').trim();
    if (!id || !roomVal) return;
    const room = normalizeRoomForApi(roomVal);
    const payload = { action: 'apply', room, map_id: id };
    const { data } = await fetchJSON('/api/room_maps.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    if (data && data.success) {
      setMsg('Map applied.', 'ok');
      await loadActive();
      await loadSavedMaps();
    } else {
      setMsg('Failed to apply map' + (data&&data.message? ': ' + data.message : ''), 'error');
    }
  }

  async function deleteSavedMap(id){
    if (!id) return;
    if (typeof window.showConfirmationModal !== 'function') { setMsg('Confirmation UI unavailable. Action canceled.', 'error'); return; }
    const ok = await window.showConfirmationModal({
      title: 'Delete Map',
      message: 'Delete this saved map?',
      confirmText: 'Delete',
      confirmStyle: 'danger',
      icon: '⚠️',
      iconType: 'danger'
    });
    if (!ok) return;
    const { data } = await fetchJSON('/api/room_maps.php', { method:'DELETE', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ map_id: id }) });
    if (data && data.success) {
      setMsg('Map deleted.', 'ok');
      await loadSavedMaps();
    } else {
      setMsg('Failed to delete map' + (data&&data.message? ': ' + data.message : ''), 'error');
    }
  }

  async function saveMap(){
    const room = (byId('rmeRoomSelect').value||'').trim();
    if (!room) { setMsg('Please select a room from the dropdown first.','error'); return; }
    const roomParam = normalizeRoomForApi(room);
    if (roomParam === null) { setMsg('This room type is not supported for maps. Please choose a numbered room.', 'error'); return; }
    
    if (state.rectangles.length === 0) {
      setMsg('No areas to save. Add at least one clickable area first.', 'error');
      return;
    }
    
    setMsg('Saving map...', 'info');
    const name = 'Editor ' + new Date().toLocaleString();
    const coords = state.rectangles.map(({selector, top,left,width,height})=>({selector, top,left,width,height}));
    const payload = { action:'save', room: roomParam, map_name: name, coordinates: coords };
    const { data } = await fetchJSON('/api/room_maps.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    if (data && data.success) { 
      // Auto-apply the newly saved map for immediate effect
      const newId = data.map_id;
      if (newId) {
        const applyPayload = { action: 'apply', room: roomParam, map_id: newId };
        const applyRes = await fetchJSON('/api/room_maps.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(applyPayload) });
        if (applyRes && applyRes.data && applyRes.data.success) {
          setMsg(`Map saved and applied with ${state.rectangles.length} area(s).`,'ok');
          return;
        }
      }
      setMsg(`Map saved with ${state.rectangles.length} area(s).`,'ok'); 
    } else { 
      setMsg('Could not save map' + (data&&data.message?': '+data.message:''), 'error'); 
    }
  }

  function onClick(evt){
    const t = evt.target;
    if (t && t.id === 'rmeStartPolyBtn') { evt.preventDefault(); startCreate(); return; }
    if (t && t.id === 'rmeFinishPolyBtn') { evt.preventDefault(); /* no-op for rectangles */ return; }
    if (t && t.id === 'rmeUndoPointBtn') { evt.preventDefault(); /* no-op for rectangles */ return; }
    if (t && t.id === 'rmeClearBtn') { evt.preventDefault(); clearAll(); return; }
    if (t && t.id === 'rmeLoadActiveBtn') { evt.preventDefault(); loadActive(); return; }
    if (t && t.id === 'rmeSaveMapBtn') { evt.preventDefault(); saveMap(); return; }
    if (t && t.id === 'rmeLoadMapsBtn') { evt.preventDefault(); loadSavedMaps(); return; }
    if (t && t.id === 'rmeBgUrl') { ensureBg(); return; }
    // Snap grid preset buttons
    if (t && t.hasAttribute('data-snap')) {
      evt.preventDefault();
      const size = parseInt(t.getAttribute('data-snap'), 10);
      state.snap.enabled = true;
      state.snap.size = size;
      redraw();
      setMsg(`Snap grid enabled: ${size}px`, 'info');
      return;
    }
    if (t && t.id === 'rmeFullscreenBtn') {
      evt.preventDefault();
      // Fullscreen the entire editor container (or the page body if in modal)
      const container = document.querySelector('.bg-gray-100') || document.body;
      if (!container) return;
      
      if (!document.fullscreenElement) {
        container.requestFullscreen().then(() => {
          t.textContent = 'Exit Fullscreen';
          setTimeout(redraw, 100);
        }).catch(err => {
          console.warn('[RoomMapEditor] Fullscreen request failed:', err);
          setMsg('Fullscreen not available', 'error');
        });
      } else {
        document.exitFullscreen().then(() => {
          t.textContent = 'Fullscreen';
          setTimeout(redraw, 100);
        });
      }
      return;
    }
  }

  // Area list delegated actions
  function onAreaListClick(e){
    const btn = e.target.closest('[data-action]'); if(!btn) return;
    const act = btn.getAttribute('data-action');
    const id = btn.getAttribute('data-id');
    if (act === 'rme-select'){ e.preventDefault(); selectRect(id); }
    if (act === 'rme-delete'){
      e.preventDefault();
      const idx = state.rectangles.findIndex(r => r.id === id);
      if (idx >= 0){ state.rectangles.splice(idx,1); if (state.selectedId === id) state.selectedId = null; syncTextarea(); redraw(); renderAreaList(); }
    }
    if (act === 'rme-up'){
      e.preventDefault();
      const idx = state.rectangles.findIndex(r => r.id === id);
      if (idx > 0){ const tmp = state.rectangles[idx-1]; state.rectangles[idx-1] = state.rectangles[idx]; state.rectangles[idx] = tmp; redraw(); renderAreaList(); syncTextarea(); }
    }
    if (act === 'rme-down'){
      e.preventDefault();
      const idx = state.rectangles.findIndex(r => r.id === id);
      if (idx >= 0 && idx < state.rectangles.length-1){ const tmp = state.rectangles[idx+1]; state.rectangles[idx+1] = state.rectangles[idx]; state.rectangles[idx] = tmp; redraw(); renderAreaList(); syncTextarea(); }
    }
    if (act === 'rme-dup'){
      e.preventDefault();
      const src = findRectById(id); if (!src) return;
      const nid = String(Date.now());
      const dup = {
        id: nid,
        selector: (src.selector||'') ? `${src.selector}-copy` : nextAreaSelector(),
        top: src.top + 10,
        left: src.left + 10,
        width: src.width,
        height: src.height,
      };
      state.rectangles.splice(state.rectangles.indexOf(src)+1, 0, dup);
      setSelection([nid]);
      redraw(); renderAreaList(); syncTextarea();
    }
  }

  // Drag-and-drop reordering in area list
  let __dragRowId = null;
  function onAreaDragStart(e){
    const row = e.target.closest('[data-row]'); if (!row) return;
    __dragRowId = row.getAttribute('data-id');
    try { e.dataTransfer.setData('text/plain', __dragRowId); } catch(_) {}
  }
  function onAreaDragOver(e){
    if (!__dragRowId) return;
    e.preventDefault();
  }
  function onAreaDrop(e){
    const row = e.target.closest('[data-row]'); if (!row) return;
    const targetId = row.getAttribute('data-id');
    if (!__dragRowId || !targetId || __dragRowId === targetId) { __dragRowId = null; return; }
    const from = state.rectangles.findIndex(r => r.id === __dragRowId);
    const to = state.rectangles.findIndex(r => r.id === targetId);
    if (from === -1 || to === -1){ __dragRowId = null; return; }
    const [moved] = state.rectangles.splice(from, 1);
    state.rectangles.splice(to, 0, moved);
    __dragRowId = null;
    redraw(); renderAreaList(); syncTextarea();
  }

  function onAreaListChange(e){
    const input = e.target.closest('[data-field="selector"]');
    if (!input) return;
    const id = input.getAttribute('data-id');
    const r = findRectById(id); if (!r) return;
    r.selector = String(input.value||'').trim() || r.selector;
    syncTextarea();
  }

  function onChange(evt) {
    if (evt.target && evt.target.id === 'rmeRoomSelect') {
      state.room = evt.target.value;
      setBgForRoom(state.room);
      // Auto-load active map for selected room to immediately visualize/edit
      loadActive();
    }
    if (evt.target && evt.target.id === 'rmeCoords') {
      loadFromTextarea();
    }
    if (evt.target && evt.target.id === 'rmeBgUrl') {
      ensureBg();
    }
    if (evt.target && evt.target.id === 'rmeGridColor') {
      state.grid.color = evt.target.value;
      redraw();
    }
  }

  function onKeyDown(evt){
    // Keyboard nudges for selected rectangle
    const ids = state.selectedIds.length ? state.selectedIds : (state.selectedId ? [state.selectedId] : []);
    if (!ids.length) return;
    const step = evt.shiftKey ? 10 : 1;
    let used = true;
    if (evt.key === 'Delete' || evt.key === 'Backspace'){
      state.rectangles = state.rectangles.filter(x => !ids.includes(x.id));
      setSelection([]);
    } else if (evt.key === 'ArrowUp' || evt.key === 'ArrowDown' || evt.key === 'ArrowLeft' || evt.key === 'ArrowRight'){
      ids.forEach(id => {
        const r = findRectById(id); if (!r) return;
        let l = r.left, t = r.top;
        if (evt.key === 'ArrowUp') t = t - step;
        if (evt.key === 'ArrowDown') t = t + step;
        if (evt.key === 'ArrowLeft') l = l - step;
        if (evt.key === 'ArrowRight') l = l + step;
        if (state.snap.enabled){ l = Math.round(l / state.snap.size) * state.snap.size; t = Math.round(t / state.snap.size) * state.snap.size; }
        r.left = Math.max(0, l); r.top = Math.max(0, t);
      });
    } else used = false;
    if (used){ evt.preventDefault(); redraw(); syncTextarea(); renderAreaList(); }
  }

  async function loadRooms() {
    const select = byId('rmeRoomSelect');
    if (!select) return;

    try {
      // Fetch active room settings (names, ordering)
      const settingsResp = await ApiClient.get('/api/room_settings.php', { action: 'get_all' });
      const settingsRooms = (settingsResp && (settingsResp.rooms || (settingsResp.data && settingsResp.data.rooms))) || [];
      // Fetch all room maps to include rooms that may not be in settings (e.g., 'A')
      const mapsResp = await ApiClient.get('/api/room_maps.php');
      const allMaps = (mapsResp && (mapsResp.maps || (mapsResp.data && mapsResp.data.maps))) || [];

      const nameByRoom = new Map();
      settingsRooms.forEach(r => {
        const key = String(r.room_number);
        if (!nameByRoom.has(key)) nameByRoom.set(key, r.room_name || '');
        // Capture meta if present (future-proof). Fallback: A/0 => page, others => modal
        const rc = (r.render_context && (r.render_context === 'page' || r.render_context === 'modal')) ? r.render_context : (key === 'A' || key === '0' ? 'page' : 'modal');
        const ta = (typeof r.target_aspect_ratio === 'number') ? r.target_aspect_ratio : null;
        state.roomMeta.set(key, { render_context: rc, target_aspect: ta });
      });

      const presentRooms = new Set([...settingsRooms.map(r => String(r.room_number)), ...allMaps.map(m => String(m.room_number))]);

      // Build unified list with fallbacks
      const unified = Array.from(presentRooms).map(key => {
        let label = nameByRoom.get(key) || '';
        if (!label) {
          if (/^[Aa]$/.test(key)) label = 'Landing';
          else if (key === '0') label = 'Main Room';
          else if (/^\d+$/.test(key)) label = `Room ${key}`;
          else label = key;
        }
        return { room_number: key, room_name: label };
      });


      // Sort: letters first (A, B), then 0, then 1..N by natural order
      unified.sort((a,b)=>{
        const ra=a.room_number, rb=b.room_number;
        const isLetter = v=>/^[A-Za-z]$/.test(v);
        if (isLetter(ra) && !isLetter(rb)) return -1;
        if (!isLetter(ra) && isLetter(rb)) return 1;
        // numeric compare if both numeric
        const na = /^\d+$/.test(ra) ? parseInt(ra,10) : Number.NaN;
        const nb = /^\d+$/.test(rb) ? parseInt(rb,10) : Number.NaN;
        if (!Number.isNaN(na) && !Number.isNaN(nb)) return na - nb;
        return String(ra).localeCompare(String(rb));
      });

      // Clear and populate
      while (select.options.length > 1) select.remove(1);
      unified.forEach(r => {
        const opt = document.createElement('option');
        opt.value = String(r.room_number);
        opt.textContent = `${r.room_number} — ${r.room_name}`;
        select.appendChild(opt);
      });

      if (unified.length === 0) {
        setMsg('No rooms found in database. Create a room first.', 'error');
      } else {
        setMsg(`Loaded ${unified.length} room(s) from database.`, 'info');
        // Auto-select first room and load background + active map
        if (!select.value && unified.length > 0) {
          select.value = String(unified[0].room_number);
          state.room = select.value;
          setBgForRoom(state.room);
          await loadActive();
        }
      }
    } catch (e) {
      setMsg(`Could not load room list: ${e.message}`, 'error');
      console.error('[RoomMapEditor] Failed to load rooms:', e);
    }
  }

  function run() {
    ensureBg();
    // Keep native container sizing; no forced aspect ratio to avoid misalignment
    loadRooms(); // Fetch and populate rooms
    document.addEventListener('click', onClick);
    document.addEventListener('change', onChange);
    document.addEventListener('keydown', onKeyDown);
    // Saved maps actions (delegated to list container)
    const mapsList = byId('rmeMapsList');
    if (mapsList) {
      mapsList.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]'); if (!btn) return;
        const act = btn.getAttribute('data-action');
        const id = btn.getAttribute('data-id');
        if (act === 'rme-apply-map') { e.preventDefault(); applySavedMap(id); }
        if (act === 'rme-delete-map') { e.preventDefault(); deleteSavedMap(id); }
      });
    }
    window.addEventListener('resize', () => { refreshFrameAspect(); setTimeout(redraw, 0); });
    // Simplify button labels for shop owners
    const startBtn = byId('rmeStartPolyBtn');
    if (startBtn) {
      startBtn.textContent = '➕ Add New Area';
      startBtn.title = 'Click on the map to draw a new clickable area';
    }
    
    // These buttons are hidden in the template but ensure they stay hidden
    const finishBtn = byId('rmeFinishPolyBtn'); if (finishBtn) finishBtn.classList.add('hidden');
    const undoBtn = byId('rmeUndoPointBtn'); if (undoBtn) undoBtn.classList.add('hidden');
    // Canvas pointer events
    const g = svg();
    if (g){
      g.addEventListener('mousedown', onMouseDown);
      window.addEventListener('mousemove', onMouseMove);
    }
    window.addEventListener('mouseup', onMouseUp);
    // Area list events
    const list = ensureAreaList();
    if (list) {
      list.addEventListener('click', onAreaListClick);
      list.addEventListener('change', onAreaListChange);
      list.addEventListener('mousedown', function(e){
        const row = e.target.closest('[data-row]');
        if (!row || e.target.closest('button') || e.target.closest('input')) return;
        const id = row.getAttribute('data-id');
        if (id){ selectRect(id, e.metaKey || e.ctrlKey); e.preventDefault(); }
      });
      list.addEventListener('dragstart', onAreaDragStart);
      list.addEventListener('dragover', onAreaDragOver);
      list.addEventListener('drop', onAreaDrop);
    }

    ensureToolbar();
  }

  function ensureToolbar(){
    const tb = document.getElementById('rmeToolbar');
    if (tb) return;
    const host = byId('rmeFrame'); if (!host || !host.parentNode) return;
    const wrap = document.createElement('div');
    wrap.className = 'mt-3 flex items-center rme-toolbar';
    wrap.id = 'rmeToolbar';
    wrap.innerHTML = `
      <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" id="rmeSnapToggle" class="form-checkbox"> Snap
      </label>
      <label class="inline-flex items-center gap-2 text-sm">
        <span>Grid</span>
        <input type="number" id="rmeSnapSize" class="form-input w-20" min="1" value="5">
      </label>
      <label class="inline-flex items-center gap-2 text-sm">
        <span>Preset</span>
        <select id="rmeGridPreset" class="form-input">
          <option value="5">5px</option>
          <option value="10">10px</option>
          <option value="20">20px</option>
          <option value="40">40px</option>
        </select>
      </label>
      <label class="inline-flex items-center gap-2 text-sm">
        <span>Color</span>
        <input type="color" id="rmeGridColor" class="form-input w-12" value="#e5e7eb">
      </label>
    `;
    host.parentNode.insertBefore(wrap, host.nextSibling);
    const snapToggle = document.getElementById('rmeSnapToggle');
    const snapSize = document.getElementById('rmeSnapSize');
    const preset = document.getElementById('rmeGridPreset');
    const gridColor = document.getElementById('rmeGridColor');
    if (snapToggle) snapToggle.addEventListener('change', () => { state.snap.enabled = !!snapToggle.checked; redraw(); });
    if (snapSize) snapSize.addEventListener('change', () => { const v = parseInt(snapSize.value||'5',10); state.snap.size = isNaN(v)||v<1?5:v; redraw(); });
    if (preset) preset.addEventListener('change', () => { const v = parseInt(preset.value||'5',10); state.snap.size = isNaN(v)||v<1?5:v; if (snapSize) snapSize.value = String(state.snap.size); redraw(); });
    if (gridColor) gridColor.addEventListener('change', () => { state.grid.color = gridColor.value || '#e5e7eb'; redraw(); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, { once:true });
  else run();
})();

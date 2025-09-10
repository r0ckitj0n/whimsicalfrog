<?php
// admin/admin_room_status.php
// Simple admin page to visualize per-room active background and map status.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Room Status - Admin</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; margin: 0; padding: 20px; background: #f9fafb; color: #111827; }
    h1 { margin: 0 0 16px; font-size: 24px; }
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 10px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    th { background: #f3f4f6; font-weight: 600; font-size: 14px; color: #374151; }
    td { font-size: 14px; }
    .muted { color: #6b7280; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .ok { color: #065f46; background: #ecfdf5; border-color: #a7f3d0; }
    .warn { color: #92400e; background: #fffbeb; border-color: #fde68a; }
    .error { color: #991b1b; background: #fef2f2; border-color: #fecaca; }
    .links a { color: #2563eb; text-decoration: none; margin-right: 8px; }
    .links a:hover { text-decoration: underline; }
    .toolbar { display: flex; gap: 8px; align-items: center; margin-bottom: 12px; }
    input[type="text"] { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; min-width: 220px; }
    button { padding: 8px 12px; border: 1px solid #2563eb; background: #2563eb; color: #fff; border-radius: 6px; cursor: pointer; }
    button.secondary { background: #fff; color: #2563eb; }
    .status { margin: 10px 0; font-size: 14px; }
  </style>
</head>
<body>
  <h1>Room Status</h1>
  <div class="card">
    <div class="toolbar">
      <input id="roomsInput" type="text" placeholder="Rooms (e.g., 1,2,3 or blank for all)" />
      <button id="refreshBtn">Refresh</button>
      <button id="clearBtn" class="secondary">Clear</button>
      <button id="exportCsvBtn" class="secondary">Export CSV</button>
      <label style="margin-left:12px; display:flex; align-items:center; gap:6px;">
        <input id="autoRefreshToggle" type="checkbox" /> Auto-refresh (30s)
      </label>
      <label style="margin-left:12px; display:flex; align-items:center; gap:6px;">
        <input id="debugToggle" type="checkbox" /> Debug overlay links (coords_debug=1)
      </label>
    </div>
    <div id="statusMsg" class="status muted">Loading…</div>
    <div class="table-wrap">
      <table id="statusTable">
        <thead>
          <tr>
            <th>Room</th>
            <th>Active Background</th>
            <th>Active Map</th>
            <th>Links</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        </tbody>
      </table>
    </div>
  </div>

<script>
function timeAgo(iso){
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return '';
  const diff = Math.max(0, Date.now() - d.getTime());
  const s = Math.floor(diff/1000);
  if (s < 60) return `${s}s ago`;
  const m = Math.floor(s/60);
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m/60);
  if (h < 24) return `${h}h ago`;
  const days = Math.floor(h/24);
  return `${days}d ago`;
}

// Toast/notification helper
function notify(type, title, message){
  try {
    if (typeof window.showNotification === 'function') {
      window.showNotification({ type, title, message });
      return;
    }
  } catch(_) {}
  // Fallback
  console.log(`[${type || 'info'}] ${title || ''} ${message || ''}`);
}

async function fetchRoomStatus(roomsCsv){
  const base = '/api/room_status.php';
  const url = roomsCsv && roomsCsv.trim() ? `${base}?rooms=${encodeURIComponent(roomsCsv.trim())}` : base;
  const res = await fetch(url, { credentials: 'same-origin' });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const data = await res.json();
  if (!data || !data.success) throw new Error(data && data.message || 'Unknown error');
  return data.rooms || [];
}

function renderStatus(rows){
  const tbody = document.querySelector('#statusTable tbody');
  tbody.innerHTML = '';
  if (!rows.length){
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 4;
    td.className = 'muted';
    td.textContent = 'No rooms found.';
    tr.appendChild(td);
    tbody.appendChild(tr);
    return;
  }
  rows.forEach(row => {
    const tr = document.createElement('tr');
    // Room
    const tdRoom = document.createElement('td');
    tdRoom.innerHTML = `<span class="pill">Room ${row.room_number}</span>`;
    tr.appendChild(tdRoom);

    // Background
    const tdBg = document.createElement('td');
    if (row.background) {
      const bg = row.background;
      const fname = (bg.webp_filename || bg.image_filename || '').split('/').pop();
      const ts = bg.updated_at || bg.created_at;
      const human = timeAgo(ts);
      tdBg.innerHTML = `<div><strong>${bg.background_name || 'Original'}</strong></div>
                        <div class="muted">${fname || '—'}${human ? ` • Updated ${human}` : ''}</div>`;
    } else {
      tdBg.innerHTML = '<span class="pill warn">No active background</span>';
    }
    tr.appendChild(tdBg);

    // Map
    const tdMap = document.createElement('td');
    if (row.map) {
      const human = timeAgo(row.map.updated_at);
      const rn = String(row.room_number);
      const label = `room${rn}/${row.map.map_name || 'Map'}`;
      tdMap.innerHTML = `<div><strong>${label}</strong></div>
                         <div class="muted">Areas: ${row.map.area_count}${human ? ` • Updated ${human}` : ''}</div>`;
    } else {
      tdMap.innerHTML = '<span class="pill warn">No active map</span>';
    }
    tr.appendChild(tdMap);

    // Links
    const tdLinks = document.createElement('td');
    tdLinks.className = 'links';
    const rn = String(row.room_number);
    const debugOn = document.getElementById('debugToggle')?.checked;
    const dbg = debugOn ? '&coords_debug=1' : '';
    const dbgHash = debugOn ? '#debug' : '';
    tdLinks.innerHTML = `
      <a href="/admin/admin_settings.php?room=${encodeURIComponent(rn)}&tab=backgrounds#backgrounds" target="_blank" rel="noopener">Backgrounds</a>
      <a href="/admin/admin_settings.php?room=${encodeURIComponent(rn)}&tab=room-maps#room-maps" target="_blank" rel="noopener">Room Maps</a>
      <a href="/?page=room${rn}${dbg}" target="_blank" rel="noopener">View Room</a>
      <a href="/?page=room_main${dbg}" target="_blank" rel="noopener">Main (debug)</a>
    `;
    tr.appendChild(tdLinks);

    // Actions
    const tdActions = document.createElement('td');
    const applyBtn = document.createElement('button');
    applyBtn.textContent = 'Apply Original Map';
    applyBtn.addEventListener('click', async () => {
      try {
        if (!confirm(`Apply 'Original' map for Room ${rn}? This will set it active.`)) return;
        const prevText = applyBtn.textContent;
        applyBtn.textContent = 'Applying…';
        applyBtn.disabled = true;
        // Fetch maps for room to get the Original map id
        const res = await fetch(`/api/room_maps.php?room=${encodeURIComponent(rn)}`, { credentials: 'include' });
        const data = await res.json().catch(() => ({}));
        const list = data && (data.maps || data.data || data) || [];
        const findOriginal = (arr) => {
          if (!Array.isArray(arr)) return null;
          // Look for exact 'Original' first, else contains 'Original'
          let m = arr.find(x => (x.map_name || '').trim() === 'Original');
          if (!m) m = arr.find(x => (x.map_name || '').toLowerCase().includes('original'));
          return m || null;
        };
        const original = findOriginal(list);
        if (!original || !original.id) {
          notify('error', 'Original Map Not Found', `Room ${rn}: could not find an 'Original' map.`);
          return;
        }
        // Apply the map
        const applyRes = await fetch('/api/room_maps.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ action: 'apply', room: rn, map_id: original.id })
        });
        const applyData = await applyRes.json().catch(() => ({}));
        if (applyRes.ok && applyData && applyData.success) {
          notify('success', 'Map Applied', `Applied 'Original' map for Room ${rn}.`);
          refresh();
        } else {
          const msg = (applyData && (applyData.message || applyData.error)) || `HTTP ${applyRes.status}`;
          notify('error', 'Apply Failed', msg);
        }
      } catch (e) {
        console.error(e);
        notify('error', 'Apply Failed', e?.message || 'Failed to apply Original map.');
      } finally {
        applyBtn.disabled = false;
        applyBtn.textContent = 'Apply Original Map';
      }
    });
    tdActions.appendChild(applyBtn);

    // Background action: Set Original Background
    const bgBtn = document.createElement('button');
    bgBtn.textContent = 'Set Original Background';
    bgBtn.style.marginLeft = '8px';
    bgBtn.addEventListener('click', async () => {
      try {
        if (!confirm(`Set 'Original' background for Room ${rn}? This will set it active.`)) return;
        const prevText = bgBtn.textContent;
        bgBtn.textContent = 'Applying…';
        bgBtn.disabled = true;
        // Fetch backgrounds for room to find Original
        const res = await fetch(`/api/backgrounds.php?room=${encodeURIComponent(rn)}`, { credentials: 'include' });
        const data = await res.json().catch(() => ({}));
        const list = (data && (data.backgrounds || data.data || data)) || [];
        const findOriginal = (arr) => {
          if (!Array.isArray(arr)) return null;
          let b = arr.find(x => (x.background_name || '').trim() === 'Original');
          if (!b) b = arr.find(x => (x.background_name || '').toLowerCase().includes('original'));
          return b || null;
        };
        const original = findOriginal(list);
        if (!original || !original.id) {
          notify('error', 'Original Background Not Found', `Room ${rn}: could not find an 'Original' background.`);
          return;
        }
        // Apply background
        const applyRes = await fetch('/api/backgrounds.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ action: 'apply', room: rn, background_id: original.id })
        });
        const applyData = await applyRes.json().catch(() => ({}));
        if (applyRes.ok && applyData && applyData.success) {
          notify('success', 'Background Applied', `Applied 'Original' background for Room ${rn}.`);
          refresh();
        } else {
          const msg = (applyData && (applyData.message || applyData.error)) || `HTTP ${applyRes.status}`;
          notify('error', 'Apply Failed', msg);
        }
      } catch (e) {
        console.error(e);
        notify('error', 'Apply Failed', e?.message || 'Failed to apply Original background.');
      } finally {
        bgBtn.disabled = false;
        bgBtn.textContent = 'Set Original Background';
      }
    });
    tdActions.appendChild(bgBtn);
    tr.appendChild(tdActions);

    tbody.appendChild(tr);
  });
}

async function refresh(){
  const msg = document.getElementById('statusMsg');
  msg.textContent = 'Loading…';
  try {
    const roomsCsv = document.getElementById('roomsInput').value;
    const rows = await fetchRoomStatus(roomsCsv);
    renderStatus(rows);
    msg.textContent = `Loaded ${rows.length} room(s).`;
  } catch (e) {
    console.error(e);
    msg.textContent = `Error: ${e.message || e}`;
    msg.classList.remove('muted');
    msg.classList.add('error');
  }
}

document.getElementById('refreshBtn').addEventListener('click', refresh);

document.getElementById('clearBtn').addEventListener('click', () => {
  document.getElementById('roomsInput').value = '';
  refresh();
});

// Export CSV of current table
document.getElementById('exportCsvBtn').addEventListener('click', () => {
  const tbody = document.querySelector('#statusTable tbody');
  const rows = Array.from(tbody.querySelectorAll('tr'));
  const header = ['room_number','background_name','background_file','map_name','area_count'];
  const data = [header];
  rows.forEach(tr => {
    const tds = tr.querySelectorAll('td');
    if (tds.length < 3) return; // skip empty state row
    const roomText = tds[0].textContent.trim().replace(/^Room\s+/i,'');
    const bgName = (tds[1].querySelector('strong')?.textContent || '').trim();
    const bgFile = (tds[1].querySelector('.muted')?.textContent || '').split('•')[0].trim();
    const mapName = (tds[2].querySelector('strong')?.textContent || '').trim();
    const areasMatch = (tds[2].querySelector('.muted')?.textContent || '').match(/Areas:\s*(\d+)/i);
    const areaCount = areasMatch ? areasMatch[1] : '';
    data.push([roomText, bgName, bgFile, mapName, areaCount]);
  });
  const csv = data.map(r => r.map(v => {
    const s = String(v ?? '');
    if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g,'""') + '"';
    return s;
  }).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = 'room-status.csv';
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 2000);
});

// Auto-refresh toggle
let __autoTimer = null;
const autoToggle = document.getElementById('autoRefreshToggle');
autoToggle.addEventListener('change', () => {
  if (autoToggle.checked) {
    // Immediate refresh then every 30s
    refresh();
    __autoTimer = setInterval(refresh, 30000);
  } else if (__autoTimer) {
    clearInterval(__autoTimer);
    __autoTimer = null;
  }
});

// initial
refresh();
</script>
</body>
</html>

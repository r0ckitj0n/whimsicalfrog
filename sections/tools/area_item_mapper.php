<?php
// Area-Item Mapper (migrated to sections/tools)
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/api/room_helpers.php';
if (class_exists('Auth')) {
    Auth::requireAdmin();
} elseif (function_exists('requireAdmin')) {
    requireAdmin();
}

// Get room data
$rooms = getRoomDoorsData();
$roomOptions = [['value' => 'A', 'label' => 'Landing Page'], ['value' => '0', 'label' => 'Main Room']];
foreach ($rooms as $room) {
    $roomOptions[] = ['value' => $room['room_number'], 'label' => $room['room_name'] ?: "Room {$room['room_number']}"];
}

$__wf_modal = isset($_GET['modal']) && $_GET['modal'] !== '0';
$__wf_included_layout = false;
if (!$__wf_modal && !function_exists('__wf_admin_root_footer_shutdown')) {
    include dirname(__DIR__, 2) . '/partials/header.php';
    $__wf_included_layout = true;
}
?>
<?php if ($__wf_modal): ?>
  <div class="bg-white">
    <div class="px-4 py-3 border-b">
      <p class="text-sm text-gray-600 mt-1">Map room areas to items or categories, and find unrepresented content.</p>
    </div>
    <div class="p-4">
      <div id="aimMessage" class="mb-3"></div>
      <div class="grid md:grid-cols-3 gap-4 mb-4">
        <div>
          <label for="aimRoomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room</label>
          <select id="aimRoomSelect" class="form-input w-full">
            <option value="">Choose a room...</option>
            <?php foreach ($roomOptions as $option): ?>
              <option value="<?= htmlspecialchars($option['value']) ?>"><?= htmlspecialchars($option['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Area Selector (e.g., .area-1)</label>
          <input id="aimAreaSelector" type="text" class="form-input w-full" placeholder=".area-1">
        </div>
        <div class="grid grid-cols-2 gap-3 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Mapping Type</label>
            <select id="aimMappingType" class="form-input w-full">
              <option value="item">Item</option>
              <option value="category">Category</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Target</label>
            <input id="aimTargetId" type="text" class="form-input w-full" placeholder="SKU or Category ID">
          </div>
        </div>
      </div>
      <div class="flex gap-3 mb-4">
        <button id="aimAddBtn" class="btn btn-primary">Add Mapping</button>
      </div>
      <div>
        <h2 class="text-lg font-semibold mb-2">Mappings</h2>
        <div id="aimMappingsList" class="border rounded overflow-hidden"></div>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="bg-gray-100 min-h-screen p-4 sm:p-6 lg:p-8">
    <div class="w-full max-w-[90vw] mx-auto bg-white rounded-lg shadow-xl">
      <div class="p-6 border-b">
        <h1 class="text-2xl font-bold">Area-Item Mapper</h1>
        <p class="text-sm text-gray-600 mt-1">Map room areas to items or categories, and find unrepresented content.</p>
      </div>
      <div class="p-6">
        <div id="aimMessage" class="mb-4"></div>
        <div class="border-b border-gray-200">
          <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button data-tab="mappings" class="aim-tab whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600">Mappings</button>
            <button data-tab="unrepresented" class="aim-tab whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Unrepresented</button>
            <button data-tab="coordinates" class="aim-tab whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Coordinates</button>
          </nav>
        <!-- Tab Panels -->
        <div class="pt-6">
          <!-- Mappings Panel -->
          <div id="tab-panel-mappings" class="aim-tab-panel">
            <div class="mb-4">
              <label for="aimRoomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room</label>
              <select id="aimRoomSelect" class="form-input w-full max-w-md">
                <option value="">Choose a room...</option>
                <?php foreach (getRoomDoorsData() as $room): ?>
                  <option value="<?= htmlspecialchars($room['room_number']) ?>"><?= htmlspecialchars($room['room_name'] ?: "Room {$room['room_number']}") ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Area Selector (e.g., .area-1)</label>
              <input id="aimAreaSelector" type="text" class="form-input w-full" placeholder=".area-1">
            </div>
            <div class="grid grid-cols-2 gap-3 items-end">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mapping Type</label>
                <select id="aimMappingType" class="form-input w-full">
                  <option value="item">Item</option>
                  <option value="category">Category</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Target</label>
                <input id="aimTargetId" type="text" class="form-input w-full" placeholder="SKU or Category ID">
              </div>
            </div>
            <div class="flex gap-3 mb-4">
              <button id="aimAddBtn" class="btn btn-primary">Add Mapping</button>
            </div>
            <div>
              <h2 class="text-lg font-semibold mb-2">Mappings</h2>
              <div id="aimMappingsList" class="border rounded overflow-hidden"></div>
            </div>
          </div>
          <!-- Unrepresented Panel -->
          <div id="tab-panel-unrepresented" class="aim-tab-panel hidden"></div>
          <!-- Coordinates Panel -->
          <div id="tab-panel-coordinates" class="aim-tab-panel hidden"></div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php if ($__wf_included_layout) {
    include dirname(__DIR__, 2) . '/partials/footer.php';
} ?>
<?php
// Prefer Vite entry in dev/prod; fall back to direct module script if manifest lookup fails
try {
    $printed = '';
    if (function_exists('vite_entry')) {
        // Capture output in case helper echoes a comment instead of throwing
        ob_start();
        echo vite_entry('src/js/admin-area-item-mapper.js');
        $printed = ob_get_clean();
    }
    $needsFallback = false;
    if (!$printed) {
        $needsFallback = true;
    } else {
        $needle = 'Entry not found';
        if (stripos($printed, $needle) !== false) {
            $needsFallback = true;
        }
    }
    if ($needsFallback) {
        echo ($printed ?: "<!-- vite_entry missing or returned no tags -->\n");
    } else {
        echo $printed;
    }
    // Always include inline fallback (guarded) to ensure functionality regardless of asset loading
    // Last-resort inline fallback: simple event binding without imports (output as raw HTML to avoid PHP string escaping issues)
    ?>
        <script>
        (function(){
          if (window.__AIM_LOADED === "1") { console.info('[AIM] Inline fallback skipped; module active'); return; }
          console.info('[AIM] Inline fallback active');
          const msg = (h,t)=>{var m=document.getElementById("aimMessage"); if(!m) return; var cls=t==="error"?"text-red-700 bg-red-100 border-red-300":"text-green-700 bg-green-100 border-green-300"; m.innerHTML = `<div class="border ${cls} rounded-md px-4 py-3 text-sm">${h}</div>`; setTimeout(()=>{m.innerHTML='';},4000); };
          async function j(url){
            const r = await fetch(url, { credentials: "same-origin", headers: { 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest' } });
            const text = await r.text();
            console.info('[AIM] fetch raw', { url, status: r.status, ok: r.ok, body: text.slice(0, 200) });
            // Extract JSON from responses that may include environment banners
            const start = text.indexOf('{');
            const end = text.lastIndexOf('}');
            let obj = null;
            if (start !== -1 && end !== -1 && end >= start) {
              const payload = text.slice(start, end + 1);
              try { obj = JSON.parse(payload); } catch(_) { obj = null; }
            }
            return obj || { success:false };
          }
          function render(target, explicit, derived, cat){
            console.info('[AIM] Render', { explicitCount: (explicit||[]).length, derivedCount: (derived||[]).length, category: cat });
            const exp = (explicit||[]).map(m=>{
              const typeOpts = `<option value="item" ${m.mapping_type==='item'?'selected':''}>Item</option><option value="category" ${m.mapping_type==='category'?'selected':''}>Category</option>`;
              const targetVal = m.mapping_type==='item' ? (m.item_sku||'') : (m.category_id||'');
              return `<tr data-id="${m.id||''}">
                <td class='p-2'><input class='form-input w-32' data-field='area_selector' value='${m.area_selector||''}'></td>
                <td class='p-2'><select class='form-input' data-field='mapping_type'>${typeOpts}</select></td>
                <td class='p-2'><input class='form-input w-48' data-field='target' value='${targetVal}' placeholder='SKU or Category ID'></td>
                <td class='p-2'><input type='number' class='form-input w-20' data-field='display_order' value='${m.display_order||0}'></td>
                <td class='p-2 whitespace-nowrap'>
                  <button class='btn btn-xs btn-primary' data-action='aim-save' data-id='${m.id||''}'>Save</button>
                  <button class='btn btn-xs btn-danger' data-action='aim-delete' data-id='${m.id||''}'>Delete</button>
                </td>
              </tr>`;
            }).join('');
            const der = (derived||[]).map((m,i)=>{
              const label = m.sku?(`Item SKU ${m.sku}`):'';
              return `<tr>
                <td class='p-2'>${m.area_selector||''}</td>
                <td class='p-2'>${label}</td>
                <td class='p-2'>${m.display_order||i+1}</td>
                <td class='p-2'>${m.sku?`<button class='btn btn-xs btn-secondary' data-action='aim-convert' data-area='${m.area_selector}' data-sku='${m.sku}'>Convert to Explicit</button>`:''}</td>
              </tr>`;
            }).join('');
            const header = cat?`<div class='mb-2 text-sm text-gray-600'>Derived category: <strong>${cat}</strong></div>`:'';
            target.innerHTML = `${header}
              <div class='mb-2 font-semibold'>Explicit Mappings</div>
              <div class='overflow-x-auto border rounded-lg'><table class='min-w-full text-sm divide-y divide-gray-200'><thead class='bg-gray-50'><tr><th class='p-2 text-left'>Area</th><th class='p-2 text-left'>Type</th><th class='p-2 text-left'>Target</th><th class='p-2 text-left'>Order</th><th class='p-2 text-left'>Actions</th></tr></thead><tbody class='divide-y divide-gray-200'>${exp}</tbody>
              <tfoot class='bg-gray-50'><tr>
                <td class='p-2'><input class='form-input w-32' id='aimNewArea' placeholder='.area-1'></td>
                <td class='p-2'><select class='form-input' id='aimNewType'><option value='item'>Item</option><option value='category'>Category</option></select></td>
                <td class='p-2'><input class='form-input w-48' id='aimNewTarget' placeholder='SKU or Category ID'></td>
                <td class='p-2'><input type='number' class='form-input w-20' id='aimNewOrder' value='0'></td>
                <td class='p-2'><button class='btn btn-xs btn-success' data-action='aim-add-explicit'>Add</button></td>
              </tr></tfoot>
              </table></div>
              <div class='mt-6 mb-2 font-semibold'>Live (Derived) View</div>
              <div class='overflow-x-auto border rounded-lg'><table class='min-w-full text-sm divide-y divide-gray-200'><thead class='bg-gray-50'><tr><th class='p-2 text-left'>Area</th><th class='p-2 text-left'>Resolved Target</th><th class='p-2 text-left'>Order</th><th class='p-2 text-left'>Actions</th></tr></thead><tbody class='divide-y divide-gray-200'>${der}</tbody></table></div>`;
          }
          async function apiPost(url, payload){
            const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-WF-ApiClient':'1','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body: JSON.stringify(payload) });
            const t = await r.text(); const i=t.lastIndexOf('{'); return (i>=0)?JSON.parse(t.slice(i)):{success:false};
          }
          async function apiPut(url, payload){
            const r = await fetch(url, { method:'PUT', headers:{'Content-Type':'application/json','X-WF-ApiClient':'1','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body: JSON.stringify(payload) });
            const t = await r.text(); const i=t.lastIndexOf('{'); return (i>=0)?JSON.parse(t.slice(i)):{success:false};
          }
          async function apiDelete(url, payload){
            const r = await fetch(url, { method:'DELETE', headers:{'Content-Type':'application/json','X-WF-ApiClient':'1','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body: JSON.stringify(payload) });
            const t = await r.text(); const i=t.lastIndexOf('{'); return (i>=0)?JSON.parse(t.slice(i)):{success:false};
          }
          async function addExplicit(){
            const room = (document.getElementById('aimRoomSelect')||{}).value||'';
            const area = (document.getElementById('aimNewArea')||{}).value||'';
            const type = (document.getElementById('aimNewType')||{}).value||'item';
            const target = (document.getElementById('aimNewTarget')||{}).value||'';
            const order = (document.getElementById('aimNewOrder')||{}).value||0;
            if (!room || !area || !target) { msg('Room, Area, and Target are required.', 'error'); return; }
            const payload = { action:'add_mapping', room, area_selector: area, mapping_type: type, display_order: order };
            if (type==='item') payload.item_sku = String(target); else payload.category_id = String(target);
            const res = await apiPost('/api/area_mappings.php', payload);
            if (res && res.success){ msg('Mapping added.', 'ok'); load(); } else { msg((res&&res.message)||'Failed to add mapping.', 'error'); }
          }
          async function saveExplicit(id){
            const row = document.querySelector(`tr[data-id="${id}"]`); if (!row) return;
            const payload = { id,
              mapping_type: row.querySelector('[data-field="mapping_type"]').value,
              area_selector: row.querySelector('[data-field="area_selector"]').value.trim(),
              display_order: parseInt(row.querySelector('[data-field="display_order"]').value||'0',10) };
            const target = row.querySelector('[data-field="target"]').value.trim();
            if (payload.mapping_type==='item') payload.item_sku = target; else payload.category_id = target;
            const res = await apiPut('/api/area_mappings.php', payload);
            if (res && (res.success||res.updated||res.no_changes)){ msg('Mapping saved.', 'ok'); } else { msg((res&&res.message)||'Failed to save mapping.', 'error'); }
          }
          async function deleteExplicit(id){
            const res = await apiDelete('/api/area_mappings.php', { id });
            if (res && res.success){ msg('Mapping deleted.', 'ok'); load(); } else { msg((res&&res.message)||'Failed to delete mapping.', 'error'); }
          }
          async function convertDerived(area, sku){
            const room = (document.getElementById('aimRoomSelect')||{}).value||'';
            if (!room || !area || !sku) return;
            const res = await apiPost('/api/area_mappings.php', { action:'add_mapping', room, area_selector: area, mapping_type:'item', item_sku: sku, display_order: 0 });
            if (res && res.success){ msg('Converted to explicit mapping.', 'ok'); load(); } else { msg((res&&res.message)||'Failed to convert.', 'error'); }
          }
          async function load(){
            const sel=document.getElementById('aimRoomSelect');
            const tgt=document.getElementById('aimMappingsList')||document.getElementById('aimMappingsContainer');
            if(!sel||!tgt) return;
            const room = (sel.value||'').trim();
            console.info('[AIM] Loading room', room);
            if(!room){ tgt.innerHTML = '<div class="p-3 text-gray-500">Select a room to view mappings.</div>'; return; }
            tgt.innerHTML = '<div class="p-3 text-gray-500">Loading...</div>';
            try {
              const [exp, live] = await Promise.all([
                j(`/api/area_mappings.php?action=get_mappings&room=${encodeURIComponent(room)}`),
                j(`/api/area_mappings.php?action=get_live_view&room=${encodeURIComponent(room)}`)
              ]);
              console.info('[AIM] exp payload', exp);
              console.info('[AIM] live payload', live);
              const explicit = exp && exp.success ? ((exp.data && exp.data.mappings) || []) : [];
              const derived = live && live.success ? ((live.data && live.data.mappings) || []) : [];
              const cat = live && live.success ? ((live.data && live.data.category) || '') : '';
              if (!derived.length && (room === 'A' || room === 'a')) {
                msg('No derived items for Landing Page. Choose Main Room (0) or Rooms 1–5 for sample data.', 'ok');
              }
              render(tgt, explicit, derived, cat);
              if ((!exp||exp.success===false)||(!live||live.success===false)) msg('Loaded with limited data.', 'error');
            } catch(e){ console.error('[AIM Fallback] load failed', e); msg('Failed to load mappings.', 'error'); }
          }
          document.addEventListener('change', function(e){ if (e.target && e.target.id==='aimRoomSelect') { console.info('[AIM] Room changed'); load(); } });
          document.addEventListener('click', function(e){
            const t = e.target.closest('[data-action]'); if (!t) return;
            const act = t.getAttribute('data-action');
            if (act==='aim-add-explicit'){ e.preventDefault(); addExplicit(); }
            if (act==='aim-save'){ e.preventDefault(); const id=t.getAttribute('data-id'); if (id) saveExplicit(id); }
            if (act==='aim-delete'){ e.preventDefault(); const id=t.getAttribute('data-id'); if (!id) return; if (typeof window.showConfirmationModal !== 'function') { msg('Confirmation UI unavailable. Action canceled.', 'error'); return; } const ok = await window.showConfirmationModal({ title: 'Delete Mapping', message: 'Delete this mapping?', confirmText: 'Delete', confirmStyle: 'danger', icon: '⚠️', iconType: 'danger' }); if (!ok) return; deleteExplicit(id); }
            if (act==='aim-convert'){ e.preventDefault(); const area=t.getAttribute('data-area'); const sku=t.getAttribute('data-sku'); convertDerived(area, sku); }
          });
          // Auto-run if a value is preselected
          try { var rs = document.getElementById('aimRoomSelect'); if (rs && rs.value) { console.info('[AIM] Auto-load on preselected room'); load(); } } catch(_) {}
        })();
        </script>
        <?php
    
} catch (Throwable $e) {
    echo "<!-- Vite entry failed: " . htmlspecialchars($e->getMessage()) . " -->\n";
    echo '<script type="module" src="/src/js/admin-area-item-mapper.js"></script>' . "\n";
}
?>

<?php
/**
 * Room Configuration Manager (migrated to sections/tools)
 */

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

if (class_exists('Auth')) {
    Auth::requireAdmin();
} elseif (function_exists('requireAdmin')) {
    requireAdmin();
}

// Check if this is being loaded in a modal context (no layout needed)
$is_modal_context = isset($_GET['modal']) || strpos($_SERVER['HTTP_REFERER'] ?? '', 'admin_settings') !== false;

$__wf_included_layout = false;
if (!$is_modal_context && !function_exists('__wf_admin_root_footer_shutdown')) {
    include dirname(__DIR__, 2) . '/partials/header.php';
    $__wf_included_layout = true;
}
if ($is_modal_context) {
    include dirname(__DIR__, 2) . '/partials/modal_header.php';
}
?>
<div class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <?php if (!$is_modal_context): ?>
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Room Configuration Manager</h1>
            <?php endif; ?>
            <div id="messageContainer"></div>
            <div class="mb-6">
                <label for="roomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room:</label>
                <select id="roomSelect" class="form-input w-full" data-change-action="loadRoomConfig">
                    <option value="">Choose a room...</option>
                    <option value="1">Room 1</option>
                    <option value="2">Room 2</option>
                    <option value="3">Room 3</option>
                    <option value="4">Room 4</option>
                    <option value="5">Room 5</option>
                </select>
            </div>
            <div class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Current Room Configurations</h2>
                <div id="roomConfigContainer"></div>
            </div>
            <div class="border-t pt-8">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Configure Room Settings</h2>
                <div id="configFormContainer" class="config-form-container hidden">
                    <form id="roomConfigForm" class="space-y-8">
                        <input type="hidden" id="roomNumber" name="room_number">
                        <div class="config-section">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Popup Settings</h3>
                            <div class="config-group">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Show Delay (ms)</label>
                                    <input type="number" id="show_delay" name="show_delay" value="50" min="0" max="1000" class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Hide Delay (ms)</label>
                                    <input type="number" id="hide_delay" name="hide_delay" value="150" min="0" max="1000" class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Width (px)</label>
                                    <input type="number" id="max_width" name="max_width" value="450" min="200" max="800" class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Width (px)</label>
                                    <input type="number" id="min_width" name="min_width" value="280" min="200" max="600" class="form-input w-full">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="enable_sales_check" checked class="mr-2">
                                    Enable Sales Check
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="show_category" checked class="mr-2">
                                    Show Category
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="show_description" checked class="mr-2">
                                    Show Description
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="enable_image_fallback" checked class="mr-2">
                                    Image Fallback
                                </label>
                            </div>
                        </div>
                        <div class="config-section">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Modal Settings</h3>
                            <div class="config-group">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Quantity</label>
                                    <input type="number" id="max_quantity" name="max_quantity" value="999" min="1" max="9999" class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Quantity</label>
                                    <input type="number" id="min_quantity" name="min_quantity" value="1" min="1" max="10" class="form-input w-full">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                <label class="flex items-center">
                                    <input type="checkbox" id="enable_colors" name="enable_colors" checked class="mr-2">
                                    Enable Colors
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="enable_sizes" name="enable_sizes" checked class="mr-2">
                                    Enable Sizes
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="show_unit_price" checked class="mr-2">
                                    Show Unit Price
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="enable_stock_checking" checked class="mr-2">
                                    Stock Checking
                                </label>
                            </div>
                        </div>
                        <div class="config-section">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Interaction Settings</h3>
                            <div class="config-group">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Debounce Time (ms)</label>
                                    <input type="number" id="debounce_time" name="debounce_time" value="50" min="0" max="500" class="form-input w-full">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="click_to_details" checked class="mr-2">
                                    Click to Details
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="hover_to_popup" checked class="mr-2">
                                    Hover to Popup
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="popup_add_to_cart" checked class="mr-2">
                                    Popup Add to Cart
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="enable_touch_events" checked class="mr-2">
                                    Touch Events
                                </label>
                            </div>
                        </div>
                        <div class="config-section">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Visual Settings</h3>
                            <div class="config-group">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Popup Animation</label>
                                    <select id="popup_animation" name="popup_animation" class="form-input w-full">
                                        <option value="fade">Fade</option>
                                        <option value="slide">Slide</option>
                                        <option value="scale">Scale</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Modal Animation</label>
                                    <select id="modal_animation" name="modal_animation" class="form-input w-full">
                                        <option value="scale">Scale</option>
                                        <option value="fade">Fade</option>
                                        <option value="slide">Slide</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="flex items-center">
                                    <input type="checkbox" id="icons_white_background" name="icons_white_background" class="mr-2" checked>
                                    Show white background behind room items (panels)
                                </label>
                                <div class="text-xs text-gray-500 mt-1">Uncheck to make item backgrounds transparent for this room.</div>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-4">
                            <button type="button" data-action="resetForm" class="btn btn-secondary">Reset to Defaults</button>
                            <button type="submit" class="btn btn-primary" id="saveConfigBtn">Save Configuration</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($__wf_included_layout) {
    include dirname(__DIR__, 2) . '/partials/footer.php';
} ?>

<?php if ($is_modal_context): ?>
<?php /* embed resets handled by body[data-embed] utilities */ ?>

<script>(function(){
  var f=document.getElementById('roomConfigForm');
  var s=document.getElementById('roomSelect');
  var c=document.getElementById('configFormContainer');
  if(!f||!s) return;

  async function apiRequest(method, url, data=null, options={}){
    const A = (typeof window !== 'undefined') ? (window.ApiClient || null) : null;
    const m = String(method||'GET').toUpperCase();
    if (A && typeof A.request === 'function') {
      if (m === 'GET') return A.get(url, (options && options.params) || {});
      if (m === 'POST') return A.post(url, data||{}, options||{});
      if (m === 'PUT') return A.put(url, data||{}, options||{});
      if (m === 'DELETE') return A.delete(url, options||{});
      return A.request(url, { method: m, ...(options||{}) });
    }
    const isForm = (typeof FormData !== 'undefined') && (data instanceof FormData);
    const headers = isForm ? { 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) }
                           : { 'Content-Type': 'application/json', 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) };
    const cfg = { credentials:'include', method:m, headers, ...(options||{}) };
    if (!isForm && data !== null && typeof cfg.body === 'undefined') cfg.body = JSON.stringify(data);
    if (isForm && typeof cfg.body === 'undefined') cfg.body = data;
    const res = await fetch(url, cfg);
    return res.json().catch(()=>({}));
  }
  const apiGet = (url, params) => apiRequest('GET', url, null, { params });
  const apiPost = (url, body, options) => apiRequest('POST', url, body, options);

  function load(room){
    if(!room) return;
    // Load JSON config (legacy UI settings)
    var p1 = apiGet('/api/room_config.php?action=get&room='+encodeURIComponent(room))
      .then(function(j){
        var cfg=(j&&j.config)||{};
        Object.keys(cfg).forEach(function(k){
          var el=f.querySelector('[name="'+k+'"]');
          if(!el) return;
          if(el.type==='checkbox') el.checked=!!cfg[k];
          else el.value=cfg[k];
        });
      });
    // Load DB flags (room_settings)
    var p2 = apiGet('/api/room_settings.php?action=get_room&room_number='+encodeURIComponent(room))
      .then(function(j){
        var room=(j&&j.room)||{};
        var cb=f.querySelector('#icons_white_background');
        if(cb){
          if(room && typeof room.icons_white_background !== 'undefined'){
            cb.checked = !!Number(room.icons_white_background);
          } else {
            cb.checked = (room==="1" || String(s.value)==="1") ? false : true;
          }
        }
      }).catch(function(){});
    Promise.allSettled([p1,p2]).finally(function(){
      var rn=f.querySelector('#roomNumber'); if(rn) rn.value=room;
      if(c) c.classList.remove('hidden');
    });
  }

  s.addEventListener('change', function(e){ load(e.target.value); });

  f.addEventListener('submit', function(e){
    e.preventDefault();
    var room=s.value; if(!room){
      if (window.parent && typeof window.parent.showAlertModal === 'function') {
        window.parent.showAlertModal({ title: 'Missing Room', message: 'Select a room' });
      } else if (typeof window.showAlertModal === 'function') {
        window.showAlertModal({ title: 'Missing Room', message: 'Select a room' });
      } else { alert('Select a room'); }
      return; }
    var fd=new FormData(f), cfg={};
    fd.forEach(function(v,k){
      if(k==='room_number') return;
      var el=f.querySelector('[name="'+k+'"]');
      var val = (el && el.type==='checkbox') ? el.checked : v;
      var n = Number(val);
      cfg[k] = isNaN(n) ? val : n;
    });
    var saveJson = apiPost('/api/room_config.php?action=save', {room:room, config:cfg});
    var saveFlags = apiRequest('PUT','/api/room_settings.php', {action:'update_flags', room_number: room, icons_white_background: !!(f.querySelector('#icons_white_background')||{}).checked});
    Promise.all([saveJson, saveFlags]).then(function(results){
      var ok = (results[0]&&results[0].success)!==false && (results[1]&&results[1].success)!==false;
      if(ok){
        var msg = 'Settings saved successfully';
        if (window.parent && typeof window.parent.showAlertModal === 'function') {
          window.parent.showAlertModal({ title: 'Saved', message: msg, icon: '✅', iconType: 'success' }).then(function(){ try{ location.reload(); }catch(_){} });
        } else if (typeof window.showAlertModal === 'function') {
          window.showAlertModal({ title: 'Saved', message: msg, icon: '✅', iconType: 'success' }).then(function(){ try{ location.reload(); }catch(_){} });
        } else { alert(msg); location.reload(); }
      }
      else {
        var emsg = 'Save failed: ' + (JSON.stringify(results));
        if (window.parent && typeof window.parent.showAlertModal === 'function') { window.parent.showAlertModal({ title: 'Save Failed', message: emsg, icon: '⚠️', iconType: 'warning' }); }
        else if (typeof window.showAlertModal === 'function') { window.showAlertModal({ title: 'Save Failed', message: emsg, icon: '⚠️', iconType: 'warning' }); }
        else { alert(emsg); }
      }
    }).catch(function(){
      var emsg = 'Save failed';
      if (window.parent && typeof window.parent.showAlertModal === 'function') { window.parent.showAlertModal({ title: 'Save Failed', message: emsg, icon: '⚠️', iconType: 'warning' }); }
      else if (typeof window.showAlertModal === 'function') { window.showAlertModal({ title: 'Save Failed', message: emsg, icon: '⚠️', iconType: 'warning' }); }
      else { alert(emsg); }
    });
  });

  if(s.value) load(s.value);
})();</script>
<?php endif; ?>

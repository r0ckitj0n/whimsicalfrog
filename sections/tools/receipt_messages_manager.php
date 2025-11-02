<?php
$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

require_once $root . '/api/config.php';
require_once $root . '/includes/functions.php';

if (!$inModal) {
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_receipt_mgr_footer_shutdown')) {
      function __wf_receipt_mgr_footer_shutdown(){ @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_receipt_mgr_footer_shutdown');
  }
  $section = 'settings';
  include_once $root . '/components/admin_nav_tabs.php';
}
if ($inModal) { include $root . '/partials/modal_header.php'; }
?>
<?php if (!$inModal): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>

<div class="p-3 admin-actions-icons<?php echo $inModal ? ' modal-absolute-fill' : ''; ?>" id="receiptMessagesManagerRoot">
  <?php if (!$inModal): ?>
  <div class="admin-card">
    <h1 class="admin-card-title">Receipt Messages Manager</h1>
    <div class="text-sm text-gray-600">Configure context-specific receipt messages and the extra sales verbiage shown on receipts.</div>
  </div>
  <?php endif; ?>

  <div class="grid gap-3 md:grid-cols-2">
    <div class="space-y-3">
      <div class="admin-card">
        <h3 class="admin-card-title">Context Rules</h3>
        <div class="text-sm text-gray-600">Rules are evaluated by priority: Shipping Method → Item Category → Item Count → Default.</div>
      </div>
      <div class="admin-card">
        <div class="flex items-center justify-between mb-2">
          <div class="font-semibold">Rules</div>
          <button id="rmNewRule" class="btn btn-primary btn-sm">New Rule</button>
        </div>
        <table class="admin-table text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="p-2 text-left">Type</th>
              <th class="p-2 text-left">Condition</th>
              <th class="p-2 text-left">Title</th>
              <th class="p-2 text-left">Content</th>
              <th class="p-2 text-left w-28">Actions</th>
            </tr>
          </thead>
          <tbody id="rmRulesBody" class="divide-y">
            <tr><td colspan="5" class="p-3 text-center text-gray-500">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="space-y-3">
      <div class="admin-card">
        <h3 class="admin-card-title">Sales Verbiage</h3>
        <div class="text-sm text-gray-600">Shown below the main receipt message when available.</div>
      </div>
      <div class="admin-card<?php echo $inModal ? ' p-0 wf-card-fill' : ''; ?>">
        <?php if ($inModal): ?>
        <?php /* modal: rely on reusable utilities for layout/fill */ ?>
        <?php endif; ?>
        <form id="salesVerbiageForm" class="space-y-3<?php echo $inModal ? ' wf-panel-fill wf-flex-col' : ''; ?>" data-action="prevent-submit">
          <div>
            <label class="block text-xs font-semibold mb-1" for="rmThankYou">Thank You Message</label>
            <textarea id="rmThankYou" class="form-textarea w-full" rows="2"></textarea>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1" for="rmNextSteps">Next Steps</label>
            <textarea id="rmNextSteps" class="form-textarea w-full" rows="2"></textarea>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1" for="rmSocial">Social Sharing</label>
            <textarea id="rmSocial" class="form-textarea w-full" rows="2"></textarea>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1" for="rmReturn">Return Customer</label>
            <textarea id="rmReturn" class="form-textarea w-full" rows="2"></textarea>
          </div>
          <div class="flex items-center justify-end gap-2<?php echo $inModal ? ' wf-hidden' : ''; ?>">
            <span id="rmSalesStatus" class="text-sm text-gray-500" aria-live="polite"></span>
            <button type="button" id="rmSalesSave" class="btn btn-primary btn-sm">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Rule Editor Modal -->
<div id="rmRuleModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="rmRuleTitle">
  <div class="admin-modal admin-modal-content">
    <div class="modal-header">
      <h2 id="rmRuleTitle" class="admin-card-title">Receipt Rule</h2>
      <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="rm-close" aria-label="Close">×</button>
    </div>
    <div class="modal-body">
      <form id="rmRuleForm" class="space-y-3" data-action="prevent-submit">
        <input type="hidden" id="rmRuleId" />
        <div>
          <label class="block text-xs font-semibold mb-1" for="rmType">Type</label>
          <select id="rmType" class="form-select w-full">
            <option value="shipping_method">Shipping Method</option>
            <option value="item_category">Item Category</option>
            <option value="item_count">Item Count</option>
            <option value="default">Default</option>
          </select>
        </div>
        <div class="grid gap-2 md:grid-cols-2">
          <div>
            <label class="block text-xs font-semibold mb-1" for="rmCondKey">Condition Key</label>
            <input id="rmCondKey" class="form-input w-full" placeholder="e.g., method, category, count, status" />
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1" for="rmCondVal">Condition Value</label>
            <input id="rmCondVal" class="form-input w-full" placeholder="e.g., USPS, Tumblers, 1, completed" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1" for="rmTitle">Title</label>
          <input id="rmTitle" class="form-input w-full" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1" for="rmContent">Content</label>
          <textarea id="rmContent" class="form-textarea w-full" rows="4"></textarea>
        </div>
        <div class="flex items-center justify-end gap-2">
          <button type="button" class="btn btn-secondary btn-sm" data-action="rm-close">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm" id="rmSaveRule">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  function notify(msg, type){
    try {
      if (window.parent && window.parent.wfNotifications && typeof window.parent.wfNotifications.show === 'function') { window.parent.wfNotifications.show(msg, type||'info'); return; }
      if (window.parent && typeof window.parent.showNotification === 'function') { window.parent.showNotification(msg, type||'info'); return; }
      if (typeof window.showNotification === 'function') { window.showNotification(msg, type||'info'); return; }
    } catch(_) {}
  }
  async function brandedConfirm(message, options){
    try {
      if (window.parent && typeof window.parent.showConfirmationModal === 'function') {
        return await window.parent.showConfirmationModal({ title:(options&&options.title)||'Please confirm', message, confirmText:(options&&options.confirmText)||'Confirm', confirmStyle:(options&&options.confirmStyle)||'danger', icon:'⚠️', iconType:(options&&options.iconType)||'warning' });
      }
      if (typeof window.showConfirmationModal === 'function') {
        return await window.showConfirmationModal({ title:(options&&options.title)||'Please confirm', message, confirmText:(options&&options.confirmText)||'Confirm', confirmStyle:(options&&options.confirmStyle)||'danger', icon:'⚠️', iconType:(options&&options.iconType)||'warning' });
      }
    } catch(_) {}
    notify('Confirmation UI unavailable. Action canceled.', 'error');
    return false;
  }
  // API helpers: prefer shared ApiClient if available; otherwise fetch with identifying header
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
    const headers = { 'Content-Type': 'application/json', 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) };
    const cfg = { credentials:'include', method:m, headers, ...(options||{}) };
    if (data !== null && typeof cfg.body === 'undefined') cfg.body = JSON.stringify(data);
    const res = await fetch(url, cfg);
    return res.json().catch(()=>({}));
  }
  const apiGet = (url, params) => apiRequest('GET', url, null, { params });
  const apiPost = (url, body, options) => apiRequest('POST', url, body, options);
  const rulesBody = document.getElementById('rmRulesBody');
  const btnNew = document.getElementById('rmNewRule');
  const ruleOverlay = document.getElementById('rmRuleModal');
  const fId = document.getElementById('rmRuleId');
  const fType = document.getElementById('rmType');
  const fKey = document.getElementById('rmCondKey');
  const fVal = document.getElementById('rmCondVal');
  const fTitle = document.getElementById('rmTitle');
  const fContent = document.getElementById('rmContent');

  const svForm = document.getElementById('salesVerbiageForm');
  const svSave = document.getElementById('rmSalesSave');
  const svStatus = document.getElementById('rmSalesStatus');
  const inputMap = {
    receipt_thank_you_message: document.getElementById('rmThankYou'),
    receipt_next_steps: document.getElementById('rmNextSteps'),
    receipt_social_sharing: document.getElementById('rmSocial'),
    receipt_return_customer: document.getElementById('rmReturn')
  };

  const setSalesStatus = (m, ok) => { if (!svStatus) return; svStatus.textContent = m||''; svStatus.style.color = ok ? '#065f46' : '#b91c1c'; };
  const sendStatus = (m, ok) => { try { if (window.parent && window.parent !== window) window.parent.postMessage({ source:'wf-rm', type:'status', message: m||'', ok: !!ok }, '*'); } catch(_) {} };

  function showOverlay(el){
    try {
      const pd = window.parent && window.parent.document;
      if (pd) {
        const ifr = Array.from(pd.querySelectorAll('iframe')).find(f => { try { return f.contentWindow === window; } catch(_) { return false; } });
        const ov = ifr ? ifr.closest('.admin-modal-overlay') : null;
        if (ov && ov.id && typeof window.parent.showModal === 'function') { try { window.parent.showModal(ov.id); } catch(_){} }
        if (ov) { try { ov.classList.add('wf-dim-backdrop'); } catch(_){} }
      }
    } catch(_) {}
    el.classList.remove('hidden');
    el.classList.add('show');
    el.setAttribute('aria-hidden','false');
  }
  function hideOverlay(el){
    el.classList.add('hidden');
    el.classList.remove('show');
    el.setAttribute('aria-hidden','true');
    try {
      const anyOpen = !!document.querySelector('.admin-modal-overlay.show');
      if (anyOpen) return;
      const pd = window.parent && window.parent.document;
      if (pd) {
        const ifr = Array.from(pd.querySelectorAll('iframe')).find(f => { try { return f.contentWindow === window; } catch(_) { return false; } });
        const ov = ifr ? ifr.closest('.admin-modal-overlay') : null;
        if (ov) { try { ov.classList.remove('wf-dim-backdrop'); } catch(_){} }
      }
    } catch(_) {}
  }

  async function loadRules(){
    if (!rulesBody) return;
    rulesBody.innerHTML = '<tr><td colspan="5" class="p-3 text-center text-gray-500">Loading…</td></tr>';
    try{
      const j = await apiGet('/api/receipt_settings.php?action=get_settings');
      const grouped = (j && j.settings) ? j.settings : { shipping_method:[], item_category:[], item_count:[], default:[] };
      const rows = [];
      const push = (row) => {
        const id = row.id || '';
        const type = row.setting_type || '';
        const cond = `${row.condition_key||''} = ${row.condition_value||''}`;
        const title = row.message_title || '';
        const content = row.message_content || '';
        rows.push(`<tr>
          <td class="p-2"><code>${type}</code></td>
          <td class="p-2">${escapeHtml(cond)}</td>
          <td class="p-2">${escapeHtml(title)}</td>
          <td class="p-2">${escapeHtml(content)}</td>
          <td class="p-2">
            <button class="admin-action-button btn btn-xs btn-icon btn-icon--edit" data-action="rm-edit" title="Edit" aria-label="Edit" data-id="${id}"></button>
            <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="rm-delete" title="Delete" aria-label="Delete" data-id="${id}"></button>
          </td>
        </tr>`);
      };
      ['shipping_method','item_category','item_count','default'].forEach(k => { (grouped[k]||[]).forEach(push); });
      rulesBody.innerHTML = rows.join('') || '<tr><td colspan="5" class="p-3 text-center text-gray-500">No rules yet</td></tr>';
    }catch(_){ rulesBody.innerHTML = '<tr><td colspan="5" class="p-3 text-center text-red-600">Failed to load</td></tr>'; }
  }

  function openEditor(row){
    try { if (ruleOverlay.parentElement !== document.body) document.body.appendChild(ruleOverlay); } catch(_){}
    fId.value = row?.id || '';
    fType.value = row?.setting_type || 'default';
    fKey.value = row?.condition_key || (row?.setting_type==='default'?'status':'');
    fVal.value = row?.condition_value || (row?.setting_type==='default'?'completed':'');
    fTitle.value = row?.message_title || '';
    fContent.value = row?.message_content || '';
    showOverlay(ruleOverlay);
  }
  function closeEditor(){ hideOverlay(ruleOverlay); }

  document.getElementById('rmRuleForm')?.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    const payloadRule = {
      id: fId.value ? Number(fId.value) : undefined,
      setting_type: fType.value,
      condition_key: fKey.value.trim(),
      condition_value: fVal.value.trim(),
      message_title: fTitle.value.trim(),
      message_content: fContent.value.trim(),
      ai_generated: false
    };
    try{
      const j = await apiPost('/api/receipt_settings.php?action=update_settings', { settings:[ payloadRule ] });
      if (j && j.success){ closeEditor(); loadRules(); }
    }catch(_){ /* ignore */ }
  });

  document.addEventListener('click', async (ev)=>{
    const a = ev.target && ev.target.closest ? ev.target.closest('[data-action]') : null;
    if (!a) return;
    const act = a.getAttribute('data-action');
    if (act === 'rm-close') { ev.preventDefault(); closeEditor(); return; }
    if (act === 'rm-edit') {
      ev.preventDefault();
      const id = a.getAttribute('data-id');
      // Load current row from table DOM
      try{
        // Fetch latest rules and find the one
        const j = await apiGet('/api/receipt_settings.php?action=get_settings');
        const all = ([]).concat(...Object.values(j.settings||{}));
        const row = all.find(x => String(x.id) === String(id));
        openEditor(row||null);
      }catch(_){ openEditor(null); }
      return;
    }
    if (act === 'rm-delete') {
      ev.preventDefault();
      const id = a.getAttribute('data-id');
      if (!id) return;
      if (!(await brandedConfirm('Delete this rule?', { confirmText:'Delete', confirmStyle:'danger', iconType:'danger' }))) return;
      try{
        const j = await apiPost('/api/receipt_settings.php?action=delete', { id: Number(id) });
        if (j && j.success) loadRules();
      }catch(_){ /* ignore */ }
      return;
    }
  });

  btnNew && btnNew.addEventListener('click', ()=> openEditor(null));

  async function loadSales(){
    try{
      setSalesStatus('Loading…', true); sendStatus('Loading…', true);
      const j = await apiGet('/api/sales_messages.php');
      const m = j && j.messages ? j.messages : {};
      Object.keys(inputMap).forEach(k => { const el = inputMap[k]; if (el) el.value = (m[k]||''); });
      setSalesStatus('Loaded', true); sendStatus('Loaded', true);
    }catch(_){ setSalesStatus('Load failed', false); sendStatus('Load failed', false); }
  }
  async function saveSales(){
    try{
      setSalesStatus('Saving…', true); sendStatus('Saving…', true);
      const payload = {};
      Object.keys(inputMap).forEach(k => { payload[k] = (inputMap[k]?.value||'').trim(); });
      const j = await apiPost('/api/sales_messages.php', payload);
      if (j && j.success) { setSalesStatus('Saved', true); sendStatus('Saved', true); } else { setSalesStatus('Save failed', false); sendStatus('Save failed', false); }
    }catch(_){ setSalesStatus('Save failed', false); sendStatus('Save failed', false); }
  }
  svSave && svSave.addEventListener('click', async (ev)=>{ ev.preventDefault(); saveSales(); });
  try {
    window.addEventListener('message', function(ev){
      try {
        const d = ev && ev.data; if (!d || d.source !== 'wf-rm-parent') return;
        if (d.type === 'save') saveSales();
      } catch(_) {}
    });
  } catch(_) {}

  function escapeHtml(s){ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; }

  function init(){ loadRules(); loadSales(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once:true }); else init();
})();
</script>

<?php if (!$inModal): ?>
  </div>
</div>
<?php endif; ?>

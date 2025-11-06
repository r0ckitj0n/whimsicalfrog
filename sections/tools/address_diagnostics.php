<?php
// Admin Address Diagnostics Tool
// Shows canonical business address block and computes miles to a target address/ZIP via /api/distance.php

if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once dirname(__DIR__, 2) . '/api/business_settings_helper.php';
require_once dirname(__DIR__, 2) . '/includes/vite_helper.php';

$isModal = isset($_GET['modal']) && $_GET['modal'] == '1';
if ($isModal) {
    include dirname(__DIR__, 2) . '/partials/modal_header.php';
} else {
    if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
        $page = 'admin';
        include dirname(__DIR__, 2) . '/partials/header.php';
        if (!function_exists('__wf_addr_diag_footer_shutdown')) {
            function __wf_addr_diag_footer_shutdown(){ @include __DIR__ . '/../../partials/footer.php'; }
        }
        register_shutdown_function('__wf_addr_diag_footer_shutdown');
    }
}
?>
<div class="p-4">
  <div class="admin-card mb-4">
    <h2 class="admin-card-title mb-2">Canonical Business Address</h2>
    <div id="bizBlock" class="font-mono text-sm"></div>
    <div class="text-sm text-gray-600">Sourced from business_info: business_address, business_address2, business_city, business_state, business_postal.</div>
  </div>

  <div class="admin-card">
    <h2 class="admin-card-title mb-2">Compute Miles To Target</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="block font-semibold text-gray-700 mb-1" for="toAddress">Address Line 1</label>
        <input id="toAddress" class="form-input" placeholder="91 Singletree Ln" />
      </div>
      <div>
        <label class="block font-semibold text-gray-700 mb-1" for="toCity">City</label>
        <input id="toCity" class="form-input" placeholder="Dawsonville" />
      </div>
      <div>
        <label class="block font-semibold text-gray-700 mb-1" for="toState">State</label>
        <input id="toState" class="form-input" placeholder="GA" />
      </div>
      <div>
        <label class="block font-semibold text-gray-700 mb-1" for="toZip">ZIP</label>
        <input id="toZip" class="form-input" placeholder="30534" />
      </div>
    </div>
    <div class="flex items-center gap-2 mt-3">
      <button id="btnCompute" class="btn btn-primary">Compute Miles</button>
      <button id="btnUseSample" class="btn btn-secondary">Use Sample Address</button>
      <span id="status" class="text-sm text-gray-600"></span>
    </div>
    <div id="result" class="mt-3"></div>
    <details class="mt-2">
      <summary>Debug</summary>
      <pre id="debugOut" class="bg-gray-50 p-2 rounded max-h-64 overflow-auto"></pre>
    </details>
  </div>

<script>
(async function(){
  const $ = (id)=>document.getElementById(id);
  const status = $('status');
  const result = $('result');
  const debugOut = $('debugOut');
  let biz = null;

  async function apiRequest(method, url, data=null, options={}){
    const WF = (typeof window !== 'undefined') ? (window.WhimsicalFrog && window.WhimsicalFrog.api) : null;
    const A = WF || ((typeof window !== 'undefined') ? (window.ApiClient || null) : null);
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

  function renderBlock(info){
    const lines = [];
    if (info.business_address) lines.push(info.business_address);
    if (info.business_address2) lines.push(info.business_address2);
    let cityLine = '';
    if (info.business_city) cityLine += info.business_city;
    if (info.business_state) cityLine += (cityLine?', ':'') + info.business_state;
    if (info.business_postal) cityLine += (cityLine?' ':'') + info.business_postal;
    if (cityLine) lines.push(cityLine);
    $('bizBlock').innerHTML = lines.map(l=>escapeHtml(l)).join('<br />');
  }

  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }

  try {
    status.textContent = 'Loading business info…';
    const j = await apiGet('/api/business_settings.php?action=get_business_info');
    biz = (j && j.data) || j || {};
    renderBlock(biz);
    status.textContent = '';
  } catch(e){ status.textContent = 'Failed to load business info'; }

  $('btnUseSample').addEventListener('click', (e)=>{
    e.preventDefault();
    $('toAddress').value = '91 Singletree Ln';
    $('toCity').value = 'Dawsonville';
    $('toState').value = 'GA';
    $('toZip').value = '30534';
  });

  $('btnCompute').addEventListener('click', async (e)=>{
    e.preventDefault();
    if(!biz){ status.textContent='Business info not loaded'; return; }
    status.textContent = 'Computing…';
    result.textContent = '';
    debugOut.textContent = '';

    const from = {
      address: biz.business_address || '',
      city: biz.business_city || '',
      state: biz.business_state || '',
      zip: biz.business_postal || ''
    };
    const to = {
      address: $('toAddress').value.trim(),
      city: $('toCity').value.trim(),
      state: $('toState').value.trim(),
      zip: $('toZip').value.trim(),
    };

    try{
      const jr = await apiPost('/api/distance.php', { from, to, debug: true });
      const d = jr && jr.data ? jr.data : jr;
      const miles = d && typeof d.miles !== 'undefined' ? d.miles : null;
      const cached = !!(d && d.cached);
      const estimated = !!(d && d.estimated);
      status.textContent = '';
      if (miles === null) {
        result.innerHTML = '<strong>Result:</strong> miles = null (ineligible)';
      } else {
        result.innerHTML = '<strong>Result:</strong> ' + miles.toFixed(2) + ' miles' + (cached ? ' (cached)' : '') + (estimated ? ' [estimated]' : '');
      }
      debugOut.textContent = JSON.stringify(d, null, 2);
    } catch(err){
      status.textContent = 'Error computing miles';
      debugOut.textContent = String(err && err.message || err);
    }
  });
})();
</script>
<?php if (!$isModal) { /* footer via shutdown */ } ?>

<?php
// Admin Address Diagnostics Tool
// Shows canonical business address block and computes miles to a target address/ZIP via /api/distance.php

if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once dirname(__DIR__, 2) . '/api/business_settings_helper.php';

?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>Address Diagnostics</title>
<style>
 body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; padding: 16px; }
 .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 16px; }
 .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
 .label { font-weight: 600; color: #374151; margin-bottom: 4px; display: block; }
 .input { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; }
 .btn { padding: 8px 12px; border-radius: 6px; cursor: pointer; border: 1px solid #2563eb; background: #3b82f6; color: #fff; }
 .btn.secondary { background: #fff; color: #1f2937; border-color: #9ca3af; }
 .muted { color: #6b7280; font-size: 12px; }
 pre { background: #f9fafb; padding: 8px; border-radius: 6px; overflow: auto; max-height: 200px; }
 .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
</style>
</head>
<body>
  <div class="card">
    <h2>Canonical Business Address</h2>
    <div id="bizBlock" class="mono"></div>
    <div class="muted">Sourced from business_info: business_address, business_address2, business_city, business_state, business_postal.</div>
  </div>

  <div class="card">
    <h2>Compute Miles To Target</h2>
    <div class="row">
      <div>
        <label class="label" for="toAddress">Address Line 1</label>
        <input id="toAddress" class="input" placeholder="91 Singletree Ln" />
      </div>
      <div>
        <label class="label" for="toCity">City</label>
        <input id="toCity" class="input" placeholder="Dawsonville" />
      </div>
      <div>
        <label class="label" for="toState">State</label>
        <input id="toState" class="input" placeholder="GA" />
      </div>
      <div>
        <label class="label" for="toZip">ZIP</label>
        <input id="toZip" class="input" placeholder="30534" />
      </div>
    </div>
    <div class="flex-row mt-10">
      <button id="btnCompute" class="btn">Compute Miles</button>
      <button id="btnUseSample" class="btn secondary">Use Sample Address</button>
      <span id="status" class="muted"></span>
    </div>
    <div id="result" class="mt-10"></div>
    <details class="mt-2">
      <summary>Debug</summary>
      <pre id="debugOut"></pre>
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
</body>
</html>

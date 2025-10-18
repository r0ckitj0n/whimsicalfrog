<?php
// sections/tools/size_color_redesign.php — UI for Size/Color System Redesign
require_once dirname(__DIR__, 2) . '/partials/modal_header.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/auth_helper.php';
AuthHelper::requireAdmin();
?>
<div class="tool-wrap">
  <h2 class="admin-card-title">Size/Color System Redesign</h2>
  <div class="admin-card compact my-2">
    <div class="flex items-center gap-2">
      <label for="itemSku" class="text-sm font-medium">Item SKU</label>
      <input id="itemSku" class="form-input w-56" placeholder="e.g. WF-TS-001" />
      <button id="btnAnalyze" class="btn btn-secondary">Analyze</button>
      <button id="btnPropose" class="btn btn-secondary">Propose</button>
      <button id="btnView" class="btn">View Restructured</button>
      <span id="statusChip" class="pill ml-auto"></span>
    </div>
  </div>
  <div class="grid md:grid-cols-2 gap-3">
    <div class="admin-card">
      <div class="modal-header"><h3 class="admin-card-title">Analysis</h3></div>
      <div class="modal-body">
        <div id="analysisSummary" class="text-sm text-gray-700"></div>
        <div class="mt-2">
          <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Issues</div>
          <ul id="analysisIssues" class="list-disc list-inside text-sm text-gray-700 space-y-1"></ul>
        </div>
        <div class="mt-2">
          <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Recommendations</div>
          <ul id="analysisRecs" class="list-disc list-inside text-sm text-gray-700 space-y-1"></ul>
        </div>
      </div>
    </div>
    <div class="admin-card">
      <div class="modal-header"><h3 class="admin-card-title">Proposed Structure</h3></div>
      <div class="modal-body">
        <div id="proposedMeta" class="text-sm text-gray-700 mb-2"></div>
        <div id="proposedView" class="space-y-2"></div>
        <div class="mt-3 flex items-center justify-between">
          <div class="text-sm text-gray-600">Edit after migration in Attributes if needed.</div>
          <div class="flex items-center gap-2">
            <label class="text-sm"><input type="checkbox" id="preserveStock" checked /> Preserve stock</label>
            <label class="text-sm ml-2"><input type="checkbox" id="dryRun" /> Dry Run</label>
            <button id="btnMigrate" class="btn btn-primary">Migrate</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="admin-card mt-3">
    <div class="modal-header"><h3 class="admin-card-title">Output</h3></div>
    <div class="modal-body">
      <pre id="output" class="tool-output">Ready.</pre>
    </div>
  </div>
</div>
<script>
(function(){
  const out = document.getElementById('output');
  const skuEl = document.getElementById('itemSku');
  const statusChip = document.getElementById('statusChip');
  let lastProposed = null;
  function log(obj){ try { out.textContent = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2); } catch(_) { out.textContent = String(obj); } }
  async function api(action, params = null, body = null) {
    const url = new URL('/api/redesign_size_color_system.php', window.location.origin);
    if (action) url.searchParams.set('action', action);
    if (params && typeof params === 'object') {
      Object.entries(params).forEach(([k,v]) => { if (v !== undefined && v !== null) url.searchParams.set(k, String(v)); });
    }
    const res = await fetch(url.toString(), {
      method: body ? 'POST' : 'GET',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined
    });
    const text = await res.text();
    try { return JSON.parse(text); } catch(e) { return { success: false, raw: text, error: 'Invalid JSON' }; }
  }
  function getQS(name){ try { return new URLSearchParams(window.location.search).get(name); } catch(_) { return null; } }
  function setStatus(msg, ok){ if (!statusChip) return; statusChip.textContent = msg || ''; statusChip.style.background = ok ? '#e0f2fe' : '#fee2e2'; }
  function renderAnalysis(a){
    const sum = document.getElementById('analysisSummary');
    const issues = document.getElementById('analysisIssues');
    const recs = document.getElementById('analysisRecs');
    if (sum) sum.innerHTML = a ? `Colors: <strong>${a.total_colors}</strong> · Sizes: <strong>${a.total_sizes}</strong> · Backwards: <strong>${a.is_backwards ? 'Yes' : 'No'}</strong>` : '';
    if (issues) issues.innerHTML = (a && Array.isArray(a.structure_issues) && a.structure_issues.length) ? a.structure_issues.map(x=>`<li>${x}</li>`).join('') : '<li>None</li>';
    if (recs) recs.innerHTML = (a && Array.isArray(a.recommendations) && a.recommendations.length) ? a.recommendations.map(x=>`<li>${x}</li>`).join('') : '<li>None</li>';
  }
  function renderProposed(data){
    const pv = document.getElementById('proposedView');
    const meta = document.getElementById('proposedMeta');
    if (!pv) return;
    pv.innerHTML = '';
    if (!data || !Array.isArray(data.proposedSizes)) { if (meta) meta.textContent = ''; return; }
    if (meta) meta.textContent = data.message || '';
    const rows = data.proposedSizes.map(s => {
      const colors = (s.colors||[]).map(c => `<span class="pill" title="${(c.color_code||'').replace(/"/g,'&quot;')}"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${c.color_code||'#000'};margin-right:6px;vertical-align:-1px;"></span>${(c.color_name||'')}</span>`).join(' ');
      return `<div class="border rounded p-2"><div class="flex items-center justify-between"><div class="font-medium">${s.name||s.size_name||''} <span class="text-xs text-gray-500">${s.code||s.size_code||''}</span></div><div class="text-sm text-gray-600">Price Δ ${Number(s.price_adjustment||0).toFixed(2)}</div></div><div class="mt-2 flex flex-wrap gap-1">${colors||'<span class="text-sm text-gray-500">No colors</span>'}</div></div>`;
    }).join('');
    pv.innerHTML = rows || '<div class="text-sm text-gray-500">No proposal generated yet.</div>';
  }
  (function prefill(){
    const qsSku = getQS('item_sku');
    if (qsSku) { skuEl.value = qsSku; try { localStorage.setItem('wf_last_redesign_sku', qsSku); } catch(_){ } }
    else { try { const last = localStorage.getItem('wf_last_redesign_sku'); if (last) skuEl.value = last; } catch(_){ } }
  })();
  document.getElementById('btnAnalyze').addEventListener('click', async () => {
    const sku = skuEl.value.trim(); if (!sku) { log('Please enter a SKU'); return; }
    try { localStorage.setItem('wf_last_redesign_sku', sku); } catch(_){ }
    log('Analyzing...'); setStatus('Checking…', true);
    const quick = await api('check_if_backwards', { item_sku: sku });
    if (quick && quick.success) setStatus(quick.is_backwards ? 'Backwards' : 'Good', !quick.is_backwards);
    const data = await api('analyze_current_structure', { item_sku: sku });
    if (data && data.success) renderAnalysis(data.analysis);
    log(data);
  });
  document.getElementById('btnPropose').addEventListener('click', async () => {
    const sku = skuEl.value.trim(); if (!sku) { log('Please enter a SKU'); return; }
    try { localStorage.setItem('wf_last_redesign_sku', sku); } catch(_){ }
    log('Proposing...');
    const data = await api('propose_new_structure', { item_sku: sku });
    if (data && data.success) { lastProposed = data; renderProposed(data); }
    log(data);
  });
  document.getElementById('btnView').addEventListener('click', async () => {
    const sku = skuEl.value.trim(); if (!sku) { log('Please enter a SKU'); return; }
    log('Loading view...');
    const data = await api('get_restructured_view', { item_sku: sku });
    log(data);
  });
  document.getElementById('btnMigrate').addEventListener('click', async () => {
    const sku = skuEl.value.trim(); if (!sku) { log('Please enter a SKU'); return; }
    try { localStorage.setItem('wf_last_redesign_sku', sku); } catch(_){ }
    const preserve = document.getElementById('preserveStock').checked;
    const dryRun = document.getElementById('dryRun').checked;
    log('Migrating...');
    let newStructure = [];
    if (lastProposed && Array.isArray(lastProposed.proposedSizes)) {
      newStructure = lastProposed.proposedSizes.map(s => ({
        size_name: s.name || s.size_name || '',
        size_code: s.code || s.size_code || '',
        price_adjustment: s.price_adjustment || 0,
        colors: (s.colors || []).map(c => ({ color_name: c.color_name || c.name || '', color_code: c.color_code || c.code || '#000000', stock_level: c.stock_level || 0 }))
      }));
    }
    const payload = { item_sku: sku, new_structure: newStructure, preserve_stock: preserve, dry_run: !!dryRun };
    const data = await api('migrate_to_new_structure', null, payload);
    log(data);
    if (data && data.success && !dryRun) { try { alert('Migration completed successfully'); } catch(_){ } }
  });
})();
</script>

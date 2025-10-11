<?php
// sections/tools/size_color_redesign.php — UI for Size/Color System Redesign
require_once dirname(__DIR__, 1) . '/partials/modal_header.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/auth_helper.php';
AuthHelper::requireAdmin();
?>
<div class="tool-wrap">
  <h2 class="admin-card-title">Size/Color System Redesign</h2>
  <div class="tool-card">
    <div class="row">
      <label for="itemSku">Item SKU</label>
      <input id="itemSku" class="form-input" placeholder="e.g. WF-TS-001" />
      <button id="btnAnalyze" class="btn btn-secondary">Analyze Structure</button>
      <button id="btnPropose" class="btn btn-secondary">Propose New Structure</button>
    </div>
  </div>
  <div class="tool-card">
    <div class="row justify-between">
      <div><strong>Migration</strong></div>
      <div>
        <label><input type="checkbox" id="preserveStock" checked /> Preserve stock</label>
        <label class="ml-2"><input type="checkbox" id="dryRun" /> Dry Run (no commit)</label>
      </div>
    </div>
    <div class="row mt-2">
      <button id="btnMigrate" class="btn btn-brand">Migrate to Proposed Structure</button>
    </div>
  </div>
  <div class="tool-card">
    <div class="mb-1"><strong>Output</strong></div>
    <pre id="output" class="tool-output">Ready.</pre>
  </div>
</div>
<script>
(function(){
  const out = document.getElementById('output');
  const skuEl = document.getElementById('itemSku');
  let lastProposed = null;
  function log(obj){ try { out.textContent = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2); } catch(_) { out.textContent = String(obj); } }
  async function api(action, body) {
    const res = await fetch('/api/redesign_size_color_system.php' + (action ? ('?action=' + encodeURIComponent(action)) : ''), {
      method: body ? 'POST' : 'GET',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined
    });
    const text = await res.text();
    try { return JSON.parse(text); } catch(e) { return { success: false, raw: text, error: 'Invalid JSON' }; }
  }
  function getQS(name){ try { return new URLSearchParams(window.location.search).get(name); } catch(_) { return null; } }
  // Prefill from query string
  (function prefill(){
    const qsSku = getQS('item_sku');
    if (qsSku) {
      skuEl.value = qsSku;
      try { localStorage.setItem('wf_last_redesign_sku', qsSku); } catch(_){ }
    } else {
      try {
        const last = localStorage.getItem('wf_last_redesign_sku');
        if (last) skuEl.value = last;
      } catch(_){ }
    }
  })();
  document.getElementById('btnAnalyze').addEventListener('click', async () => {
    const sku = skuEl.value.trim(); if (!sku) { log('Please enter a SKU'); return; }
    try { localStorage.setItem('wf_last_redesign_sku', sku); } catch(_){ }
    log('Analyzing...');
    const data = await api('analyze_current_structure&item_sku=' + encodeURIComponent(sku));
    log(data);
  });
  document.getElementById('btnPropose').addEventListener('click', async () => {
    const sku = skuEl.value.trim(); if (!sku) { log('Please enter a SKU'); return; }
    try { localStorage.setItem('wf_last_redesign_sku', sku); } catch(_){ }
    log('Proposing...');
    const data = await api('propose_new_structure&item_sku=' + encodeURIComponent(sku));
    if (data && data.success) {
      lastProposed = data;
    }
    log(data);
  });
  document.getElementById('btnMigrate').addEventListener('click', async () => {
    const sku = skuEl.value.trim(); if (!sku) { log('Please enter a SKU'); return; }
    try { localStorage.setItem('wf_last_redesign_sku', sku); } catch(_){ }
    const preserve = document.getElementById('preserveStock').checked;
    const dryRun = document.getElementById('dryRun').checked;
    log('Migrating...');
    // Use last proposed structure if available; otherwise send empty and let API validate
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
    const data = await api('migrate_to_new_structure', payload);
    log(data);
    if (data && data.success && !dryRun) {
      try { alert('Migration completed successfully'); } catch(_){ }
    }
  });
})();
</script>

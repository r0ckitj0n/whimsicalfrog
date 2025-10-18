<?php
// Admin Repo Cleanup Tool
// Modal-friendly: if ?modal=1, do not include full header/footer

$isModal = isset($_GET['modal']) && $_GET['modal'] == '1';

try {
    require_once dirname(__DIR__, 2) . '/api/config.php';
} catch (Throwable $e) {
    // Fallback minimal includes
}

if (!$isModal) {
    // Shared layout header
    $page = 'admin';
    include dirname(__DIR__, 2) . '/partials/header.php';
    if (!function_exists('__wf_repo_cleanup_footer_shutdown')) {
        function __wf_repo_cleanup_footer_shutdown() {
            @include dirname(__DIR__, 2) . '/partials/footer.php';
        }
    }
    register_shutdown_function('__wf_repo_cleanup_footer_shutdown');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Repository Cleanup</title>
  <style>
    body { background:#fff; font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; }
    .container { max-width: 1100px; margin: 0 auto; padding: 1rem; }
    .tools-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .card { border:1px solid #ddd; border-radius:8px; padding:1rem; background:#fafafa; }
    .card h2 { margin:0 0 .5rem; font-size:1.1rem; }
    .row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .btn { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; padding:.6rem .9rem; border-radius:6px; border:1px solid #bbb; background:#f1f1f1; cursor:pointer; }
    .btn:hover { background:#e7e7e7; }
    .btn-primary { background:#2563eb; color:#fff; border-color:#1e40af; }
    .btn-primary:hover { background:#1d4ed8; }
    .btn-danger { background:#b91c1c; color:#fff; border-color:#7f1d1d; }
    .muted { color:#666; font-size:.9rem; }
    pre { background:#111; color:#0f0; padding:1rem; border-radius:6px; overflow:auto; max-height:40vh; }
    .list { border:1px solid #ddd; border-radius:6px; background:#fff; padding:.5rem; max-height:50vh; overflow:auto; }
    .list-item { display:flex; justify-content:space-between; border-bottom:1px solid #eee; padding:.4rem .2rem; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size:.85rem; }
    .list-item:last-child { border-bottom:none; }
    .tag { display:inline-block; padding:.1rem .45rem; border-radius:999px; background:#eef; color:#234; font-size:.75rem; border:1px solid #ccd; }
    .tag--ok { background:#e6ffed; color:#165; border-color:#b7f0c1; }
  </style>
</head>
<body>
  <div class="container">
    <h1>🧹 Repository Cleanup</h1>
    <p class="muted">Audit and safely archive unnecessary files. Default is a dry-run that writes a report under <code>reports/cleanup/</code>. Execution moves files to <code>backups/cleanup/</code> preserving paths.</p>

    <div class="tools-grid">
      <div class="card">
        <h2>Actions</h2>
        <div class="row">
          <button class="btn btn-primary" id="btnAudit">Run Audit (Dry Run)</button>
          <button class="btn btn-danger" id="btnExecute">Execute Cleanup</button>
          <input type="text" id="restoreTs" placeholder="Restore timestamp (YYYYMMDD_HHMMSS)" class="adm-input flex-1 min-w-220">
          <button class="btn" id="btnRestore">Restore</button>
        </div>
        <p class="muted">Categories: A (duplicates), B (backup/temp), C (.htaccess snapshots), D (migrations>30d), E (deprecated endpoints), F (dev/test caches)</p>
        <div class="row">
          <label><input type="checkbox" class="cat" value="A" checked> A</label>
          <label><input type="checkbox" class="cat" value="B" checked> B</label>
          <label><input type="checkbox" class="cat" value="C" checked> C</label>
          <label><input type="checkbox" class="cat" value="D" checked> D</label>
          <label><input type="checkbox" class="cat" value="E" checked> E</label>
          <label><input type="checkbox" class="cat" value="F" checked> F</label>
        </div>
      </div>

      <div class="card">
        <h2>Latest Report</h2>
        <div id="reportSummary" class="list"></div>
      </div>
    </div>

    <div class="card mt-4">
      <h2>Raw Output</h2>
      <pre id="output">Ready.</pre>
    </div>
  </div>

  <script>
    async function api(action, payload) {
      const url = '/api/repo_cleanup.php?action=' + encodeURIComponent(action);
      const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(payload||{}) });
      const j = await r.json();
      return j;
    }

    function getSelectedCategories() {
      const boxes = Array.from(document.querySelectorAll('.cat:checked')).map(el=>el.value).join(',');
      return boxes || null;
    }

    function renderSummary(data) {
      const root = document.getElementById('reportSummary');
      root.innerHTML = '';
      if (!data || !data.summary) { root.textContent = 'No report available yet.'; return; }
      const frag = document.createDocumentFragment();
      Object.entries(data.summary).forEach(([k, info]) => {
        const div = document.createElement('div');
        div.className = 'list-item';
        div.innerHTML = `<span><span class="tag">${k}</span></span><span>${info.count||0} files, ${info.bytes||0} bytes</span>`;
        frag.appendChild(div);
      });
      root.appendChild(frag);
    }

    function showOutput(obj){
      const pre = document.getElementById('output');
      pre.textContent = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2);
    }

    async function loadLatest() {
      try {
        const j = await api('latest_audit');
        if (j && j.success && j.data) {
          renderSummary(j.data);
        }
      } catch (e) {}
    }

    document.getElementById('btnAudit').addEventListener('click', async ()=>{
      showOutput('Running audit …');
      const categories = getSelectedCategories();
      const j = await api('audit', { categories });
      showOutput(j);
      if (j && j.success) renderSummary(j);
    });

    document.getElementById('btnExecute').addEventListener('click', async ()=>{
      if (!confirm('This will move files into backups/cleanup/. Proceed?')) return;
      showOutput('Executing cleanup …');
      const categories = getSelectedCategories();
      const j = await api('execute', { categories });
      showOutput(j);
    });

    document.getElementById('btnRestore').addEventListener('click', async ()=>{
      const stamp = document.getElementById('restoreTs').value.trim();
      if (!stamp) { alert('Enter restore timestamp'); return; }
      if (!confirm('Restore files from backups/cleanup/' + stamp + '?')) return;
      showOutput('Restoring …');
      const j = await api('restore', { timestamp: stamp });
      showOutput(j);
    });

    loadLatest();
  </script>
</body>
</html>

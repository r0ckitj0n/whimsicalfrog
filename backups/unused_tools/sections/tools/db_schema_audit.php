  
 <!--
 const diffA = $('diffA');
  const diffB = $('diffB');
  const btnRunDiff = $('btnRunDiff');
  const diffResults = $('diffResults');
  function renderReportPreviewData(data){
    try {
      const s = data && data.summary || {};
      const cols = data && Array.isArray(data.columns) ? data.columns : [];
      const tables = data && Array.isArray(data.unused_tables) ? data.unused_tables : [];
      const topCols = cols.filter(c=>c.safe_candidate).slice(0, 10);
      const topTables = tables.filter(t=>t.safe_candidate).slice(0, 10);
      const el = document.getElementById('reportPreview');
      if (!el) return;
      const toRow = (arr, map)=> arr.map(map).map(li=>`<li class="mono">${li}</li>`).join('');
      el.innerHTML = `
        <div>Tables: <strong>${s.tables||0}</strong> · Columns: <strong>${s.columns||0}</strong> · Candidate drops: <strong>${s.candidates||0}</strong></div>
        <div class="adm-grid adm-grid--2 mt-2">
          <div>
            <div class="muted mb-1">Top candidate columns</div>
            <ol>${toRow(topCols, c=>`${escape(c.table)}.${escape(c.column)} (refs:${c.ref_files||0})`) || '<li class="muted">None</li>'}</ol>
          </div>
          <div>
            <div class="muted mb-1">Top candidate tables</div>
            <ol>${toRow(topTables, t=>`${escape(t.table)} (rows:${t.row_estimate||0}, refs:${t.ref_files||0})`) || '<li class="muted">None</li>'}</ol>
          </div>
        </div>`;
    } catch(_) { /* noop */ }
  }

  function renderInline(data){
    try {
      const cols = (data && data.columns) || [];
      const tabs = (data && data.unused_tables) || [];
      inlineCols.innerHTML = '';
      inlineTabs.innerHTML = '';
      const fragC = document.createDocumentFragment();
      cols.forEach(c=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td class="mono">${escape(c.table)}</td><td class="mono">${escape(c.column)}</td><td>${escape(c.data_type||'')}</td><td>${c.ref_files||0}</td><td>${c.safe_candidate?'<span class="pill green">Yes</span>':'<span class="pill red">No</span>'}</td>`;
        fragC.appendChild(tr);
      });
      inlineCols.appendChild(fragC);
      const fragT = document.createDocumentFragment();
      tabs.forEach(t=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td class="mono">${escape(t.table)}</td><td>${t.row_estimate||''}</td><td>${formatSize(t.size_bytes)}</td><td>${t.ref_files||0}</td><td>${t.fk_inbound||0}</td><td>${t.fk_outbound||0}</td><td>${t.safe_candidate?'<span class="pill green">Yes</span>':'<span class="pill red">No</span>'}</td>`;
        fragT.appendChild(tr);
      });
      inlineTabs.appendChild(fragT);
    } catch(_){ /* noop */ }
  }

  function populateDiffSelects(list){
    function setOpts(sel){ sel.innerHTML=''; list.forEach(it=>{ const opt=document.createElement('option'); opt.value=it.path; opt.textContent=it.timestamp; sel.appendChild(opt); }); }
    if (Array.isArray(list) && list.length){ setOpts(diffA); setOpts(diffB); }
  }
  // CSV export of current results
  btnExportCsv.addEventListener('click', ()=>{
    const lines = [];
    lines.push(['Type','Table','Column','DataType','Key','Idx','FK','RefFiles','Candidate'].join(','));
    (lastColumns||[]).forEach(c=>{
      lines.push(['column', c.table, c.column, c.data_type||'', c.key||'', c.index_count||0, c.fk_count||0, c.ref_files||0, c.safe_candidate?1:0].map(v=>`"${String(v).replace(/"/g,'""')}"`).join(','));
    });
  // Enable execute when warnings are acknowledged
  if (warnAck) warnAck.addEventListener('change', ()=>{
    if (hasWarnings) btnExecute.disabled = !warnAck.checked; 
  });

  // Load and Save ignores with server
  btnLoadIgnores.addEventListener('click', async ()=>{
    try {
      setStatus('Loading ignores…');
      const r = await fetch('/api/db_schema_audit.php?action=get_ignores', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(getAuthPayload({ action:'get_ignores' })) });
      const j = await r.json();
      if (j && j.success) {
        const srvCols = Array.isArray(j.data.columns) ? j.data.columns : [];
        const srvTbls = Array.isArray(j.data.tables) ? j.data.tables : [];
        const curCols = loadIgnored('columns');
        const curTbls = loadIgnored('tables');
        const mergedCols = Array.from(new Set([...(curCols||[]), ...srvCols]));
        const mergedTbls = Array.from(new Set([...(curTbls||[]), ...srvTbls]));
        saveIgnored('columns', mergedCols);
        saveIgnored('tables', mergedTbls);
        setStatus('Ignores loaded');
        renderTable(lastColumns); renderTables(lastTables);
      } else { setStatus('Load failed'); }
    } catch(_){ setStatus('Load error'); }
  });

  btnSaveIgnores.addEventListener('click', async ()=>{
    try {
      setStatus('Saving ignores…');
      const payload = getAuthPayload({ action:'save_ignores', data: { columns: loadIgnored('columns'), tables: loadIgnored('tables') } });
      const r = await fetch('/api/db_schema_audit.php?action=save_ignores', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
      const j = await r.json();
      if (j && j.success) { setStatus('Ignores saved'); } else { setStatus('Save failed'); }
    } catch(_){ setStatus('Save error'); }
  });
    lines.push(['Type','Table','Rows','SizeBytes','Columns','Refs','FK_In','FK_Out','Candidate'].join(','));
    (lastTables||[]).forEach(t=>{
      lines.push(['table', t.table, t.row_estimate||'', t.size_bytes||'', t.columns||'', t.ref_files||0, t.fk_inbound||0, t.fk_outbound||0, t.safe_candidate?1:0].map(v=>`"${String(v).replace(/"/g,'""')}"`).join(','));
    });
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'db_schema_audit.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(()=>URL.revokeObjectURL(url), 1000);
  });

  // Reports browser
  async function refreshReports(){
    try {
      const r = await fetch('/api/db_schema_audit.php?action=list_reports', { credentials:'include' });
      const j = await r.json();
      reportsList.innerHTML = '';
      if (!(j && j.success && Array.isArray(j.data))) { reportsList.textContent = 'No reports'; return; }
      const frag = document.createDocumentFragment();
      j.data.forEach(it=>{
        const div = document.createElement('div');
        div.className = 'list-item';
        const link = document.createElement('a'); link.href = it.path; link.textContent = it.timestamp; link.target = '_blank';
        const meta = document.createElement('span'); meta.className = 'muted'; meta.textContent = ` ${it.bytes||0} bytes, ${it.modified||''}`;
        const left = document.createElement('span'); left.appendChild(link); left.appendChild(meta);
        const actions = document.createElement('span'); actions.style.marginLeft = 'auto'; actions.style.display = 'flex'; actions.style.gap = '6px';
        const btnLoad = document.createElement('button'); btnLoad.className = 'btn secondary'; btnLoad.textContent = 'Load'; btnLoad.addEventListener('click', ()=>loadReport(it.path));
        const btnPreview = document.createElement('a'); btnPreview.className = 'btn secondary'; btnPreview.textContent = 'Preview'; btnPreview.href = it.path; btnPreview.target = '_blank';
        actions.appendChild(btnLoad); actions.appendChild(btnPreview);
        div.appendChild(left);
        div.appendChild(actions);
        frag.appendChild(div);
      });
      reportsList.appendChild(frag);
      populateDiffSelects(j.data);
    } catch(_){ reportsList.textContent = 'Error loading reports'; }
  }
  if (btnRefreshReports) btnRefreshReports.addEventListener('click', refreshReports);
  refreshReports();

  async function loadReport(path){
    try {
      setStatus('Loading report…');
      const r = await fetch(path, { credentials:'include' });
      const j = await r.json();
      const d = j || {};
      // Support both flat payloads and {data:{...}}
      const data = d.data || d;
      lastColumns = data.columns || [];
      lastTables = data.unused_tables || [];
      lastSummary = data.summary || null;
      lastScanConfig = data.scan_config || null;
      renderTable(lastColumns);
      renderTables(lastTables);
      summary.innerHTML = `Tables: <strong>${(lastSummary&&lastSummary.tables)||0}</strong> · Columns: <strong>${(lastSummary&&lastSummary.columns)||0}</strong> · Candidate drops: <strong>${(lastSummary&&lastSummary.candidates)||0}</strong>`;
      renderReportPreviewData(data);
      // Apply saved selections if present
      applySelectedColumns(data.selected_columns||[]);
      applySelectedTables(data.selected_tables||[]);
      // Apply saved UI state if present
      try {
        const u = data.ui_state||{};
        const ft = document.getElementById('filterTable'); if (ft) ft.value = u.filter_table||'';
        const oc = document.getElementById('onlyCandidates'); if (oc) oc.checked = !!u.only_candidates;
        const otc = document.getElementById('onlyTableCandidates'); if (otc) otc.checked = !!u.only_table_candidates;
        toggleHideIgnored.checked = !!u.hide_ignored;
        toggleGroup.checked = !!u.grouped; grouped = !!u.grouped;
      } catch(_){ }
      await updateSql();
      // If inline is visible, render it too
      if (inlineReportWrap && inlineReportWrap.style.display !== 'none') renderInline(data);
      setStatus('Report loaded');
      btnSaveReport.disabled = !isAdmin; // leave disabled state based on auth
    } catch(_){ setStatus('Failed to load report'); }
  }
  function getAuthPayload(extra){
    const base = extra && typeof extra === 'object' ? extra : {};
    if (adminToken) base.admin_token = adminToken;
    return base;
  }

  // Check auth status and toggle controls
  (async function checkAuth(){
    try {
      const r = await fetch('/api/db_schema_audit.php?action=whoami', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(getAuthPayload({ action:'whoami' })) });
      const j = await r.json();
      isAdmin = !!(j && j.success && j.data && j.data.admin);
      adminVia = (j && j.data && j.data.via) || 'unknown';
      if (authBadge) authBadge.textContent = 'Auth: ' + (isAdmin ? ('admin (' + adminVia + ')') : 'not admin');
      // Disable actions requiring admin if not admin
      btnExecute.disabled = !isAdmin;
      btnSaveReport.disabled = !isAdmin;
    } catch(_){ if (authBadge) authBadge.textContent = 'Auth: error'; }
  })();

  // Persist and react to admin token
  try { const saved = localStorage.getItem('wf_admin_token'); if (saved) { adminToken = saved; if (adminTokenInput) adminTokenInput.value = saved; } } catch(_){ }
  if (adminTokenInput) adminTokenInput.addEventListener('input', ()=>{ adminToken = adminTokenInput.value.trim(); try { localStorage.setItem('wf_admin_token', adminToken); } catch(_){ } });
-->
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>DB Schema Audit</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; padding: 16px; }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .btn { padding: 8px 12px; border-radius: 6px; cursor: pointer; border: 1px solid #2563eb; background: #3b82f6; color: #fff; }
    .btn.secondary { background: #fff; color: #1f2937; border-color: #9ca3af; }
    .btn.danger { background: #dc2626; border-color: #b91c1c; }
    .muted { color: #6b7280; font-size: 12px; }
    .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
    th { background: #f9fafb; position: sticky; top: 0; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
    .pill { display:inline-block; padding:2px 6px; border-radius:12px; font-size:11px; border:1px solid #e5e7eb; }
    .pill.green { background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
    .pill.red { background:#fef2f2; color:#991b1b; border-color:#fecaca; }
    details pre { max-height: 240px; overflow:auto; }
  </style>
</head>
<body>
  <div class="card">
    <h2>DB Schema Audit</h2>
    <p class="muted">Scans current database schema and cross-references this repository to find columns not referenced by the code. Always review suggestions carefully.</p>
    <div class="muted mt-1">
      <strong>How to use</strong>
      <ol class="mt-1 ml-3">
        <li>Configure scan options (excludes, file extensions).</li>
        <li>Click <em>Run Dry Run Scan</em> to analyze usage.</li>
        <li>Review <em>Columns</em> and <em>Unused Tables</em>. Use filters and Ignore to hide false positives.</li>
        <li>Export CSV or <em>Save Report</em> for auditing; optionally <em>Download SQL</em>.</li>
        <li>Only on localhost, confirm and <em>Execute Drops</em> when you’re certain.</li>
      </ol>
    </div>
    <div class="muted mt-2">
      <strong>Legend</strong>: 
      <span class="pill green">candidate</span> = safe to drop (heuristic)
      · <span class="pill red">no</span> = not safe (has keys, FKs, refs, or reserved)
    </div>
    <div class="row mt-2">
      <div>
        <label class="muted">Excluded directories (one per line)</label>
        <textarea id="excludes" class="mono adm-textarea w-full" placeholder="backups\ndist\nnode_modules"></textarea>
      </div>

  <div class="card">
    <h3>Saved Reports</h3>
    <div class="flex-row mt-2 mb-2">
      <button id="btnRefreshReports" class="btn secondary">Refresh</button>
      <span class="muted">List of saved reports in <code>reports/db_schema_audit/</code></span>
    </div>
    <div id="reportsList" class="list"></div>
  </div>

  <div class="card">
    <h3>Report Preview</h3>
    <div id="reportPreview" class="muted">No report loaded.</div>
  </div>

  <div class="card">
    <h3>Inline Full Report</h3>
    <div class="flex-row mt-2 mb-2">
      <button id="btnToggleInline" class="btn secondary">Show Inline</button>
      <span class="muted">Displays currently loaded or latest scan in read-only tables</span>
    </div>
    <div id="inlineReportWrap" class="is-hidden">
      <div class="adm-grid adm-grid--1">
        <div>
          <h4>Columns (read-only)</h4>
          <table>
            <thead><tr><th>Table</th><th>Column</th><th>Type</th><th>Refs</th><th>Candidate</th></tr></thead>
            <tbody id="inlineCols"></tbody>
          </table>
        </div>
        <div>
          <h4>Tables (read-only)</h4>
          <table>
            <thead><tr><th>Table</th><th>Rows</th><th>Size</th><th>Refs</th><th>FK In</th><th>FK Out</th><th>Candidate</th></tr></thead>
            <tbody id="inlineTabs"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Reserved Lists (Config)</h3>
    <div class="muted">Items listed here will never be suggested as candidates. Columns may be specified as <code>table.column</code> or just the column name.</div>
    <div class="row mt-2">
      <div>
        <label class="muted">Reserved Tables (one per line)</label>
        <textarea id="reservedTables" class="mono adm-textarea adm-textarea--lg w-full"></textarea>
      </div>
      <div>
        <label class="muted">Reserved Columns (one per line or comma-separated)</label>
        <textarea id="reservedColumns" class="mono adm-textarea adm-textarea--lg w-full"></textarea>
      </div>
    </div>
    <div class="flex-row mt-2">
      <button id="btnLoadConfig" class="btn secondary">Load Config</button>
      <button id="btnSaveConfig" class="btn secondary">Save Config</button>
      <button id="btnPresetStrict" class="btn secondary">Preset: Strict</button>
      <button id="btnPresetRelaxed" class="btn secondary">Preset: Relaxed</button>
      <span class="muted">Stored at <code>reports/db_schema_audit/config.json</code></span>
    </div>
  </div>

  <div class="card">
    <h3>Diff Two Reports</h3>
    <div class="flex-row mt-2 mb-2 flex-wrap">
      <select id="diffA" class="adm-select"></select>
      <span class="muted">vs</span>
      <select id="diffB" class="adm-select"></select>
      <button id="btnRunDiff" class="btn">Run Diff</button>
    </div>
    <div id="diffResults" class="muted">No diff yet.</div>
  </div>
      <div>
        <label class="muted">Extensions to scan (comma-separated)</label>
        <div class="muted mt-1" id="scanConfigNote"></div>
        <input id="exts" class="mono adm-input mt-1 w-full" placeholder="php, js, mjs, ts, css, html" />
      </div>
    </div>
    <div class="flex-row mt-2 flex-wrap">
      <button id="btnScan" class="btn" title="Analyze DB schema vs code references using the options above">Run Dry Run Scan</button>
      <button id="btnSaveReport" class="btn secondary" disabled title="Save the current scan results to reports/db_schema_audit/">Save Report</button>
      <button id="btnCopySQL" class="btn secondary" disabled title="Copy the generated DROP statements to clipboard">Copy SQL</button>
      <button id="btnDownloadSQL" class="btn secondary" disabled title="Download the generated DROP statements as a .sql file">Download SQL</button>
      <button id="btnExecute" class="btn danger" disabled title="Only on localhost. Requires confirming DROP and backup acknowledgement.">Execute Drops (Local Only)</button>
      <button id="btnLoadIgnores" class="btn secondary" title="Merge server-side ignore lists into your local ignores">Load Ignores</button>
      <button id="btnSaveIgnores" class="btn secondary" title="Save your local ignores (columns/tables) to the server">Save Ignores</button>
      <span id="authBadge" class="muted ml-auto">Auth: checking…</span>
      <span id="status" class="muted"></span>
    </div>
  </div>
{{ ... }}

  <div class="card">
    <h3>Summary</h3>
    <div id="summary" class="muted">No data yet.</div>
  </div>

  <div class="card">
    <h3>Columns</h3>
    <div class="muted">Select columns you wish to include in the drop plan. Non-candidates are disabled.</div>
    <div class="flex-row mb-2">
      <input id="filterTable" placeholder="Filter by table (contains)" class="adm-input flex-1" />
      <label class="muted"><input type="checkbox" id="onlyCandidates" /> Only candidates</label>
    </div>
    <div class="scroll-area mt-2">
      <table>
        <thead>
          <tr>
            <th class="w-22"><input type="checkbox" id="chkAll" /></th>
            <th>Table</th>
            <th>Column</th>
            <th>Type</th>
            <th>Key</th>
            <th>Idx</th>
            <th>FK</th>
            <th>Refs</th>
            <th>Examples</th>
            <th>Candidate</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="rows"></tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h3>Unused Tables (heuristic)</h3>
    <div class="muted">Tables with no code references and no FK links are suggested as candidates. Review cautiously.</div>
    <div class="scroll-area scroll-area--sm mt-2">
      <table>
        <thead>
          <tr>
            <th class="w-22"><input type="checkbox" id="chkAllTables" /></th>
            <th>Table</th>
            <th>Rows</th>
            <th>Size</th>
            <th>Columns</th>
            <th>Refs</th>
            <th>FK In</th>
            <th>FK Out</th>
            <th>Idx</th>
            <th>Candidate</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="rowsTables"></tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h3>Generated SQL</h3>
    <details open>
      <summary>Statements</summary>
      <pre id="sqlOut" class="mono"></pre>
    </details>
    <details>
      <summary>Warnings</summary>
      <ul id="sqlWarnings" class="muted list-disc ml-3 mt-1"></ul>
    </details>
  </div>

  <div class="card">
    <h3>Execution</h3>
    <div class="muted">Execution is permitted only on localhost and requires explicit confirmation.</div>
    <div class="row mt-2">
      <div>
        <label class="muted">Type <strong>DROP</strong> to confirm</label>
        <input id="confirmText" class="adm-input w-full" placeholder="DROP" />
      </div>
      <div>
        <label class="muted"><input type="checkbox" id="backupAck" /> I confirm I have a recent database backup</label>
        <div id="warnAckWrap" class="mt-2 is-hidden">
          <label class="muted"><input type="checkbox" id="warnAck" /> I acknowledge the warnings above</label>
        </div>
      </div>
    </div>
  </div>

<script>
(function(){
  const $ = (id)=>document.getElementById(id);
  // Elements used across features
  const diffA = $('diffA');
  const diffB = $('diffB');
  const btnRunDiff = $('btnRunDiff');
  const diffResults = $('diffResults');
  const status = $('status');
  const rows = $('rows');
  const summary = $('summary');
  const sqlOut = $('sqlOut');
  const sqlWarnings = $('sqlWarnings');
  const chkAll = $('chkAll');
  const btnScan = $('btnScan');
  const btnCopySQL = $('btnCopySQL');
  const btnDownloadSQL = $('btnDownloadSQL');
  const btnSaveReport = $('btnSaveReport');
  const btnExecute = $('btnExecute');
  const btnLoadIgnores = $('btnLoadIgnores');
  const btnSaveIgnores = $('btnSaveIgnores');
  const btnExportCsv = $('btnExportCsv');
  const authBadge = $('authBadge');
  const adminTokenInput = $('adminToken');
  const btnRefreshReports = $('btnRefreshReports');
  const reportsList = $('reportsList');
  const btnToggleInline = $('btnToggleInline');
  const inlineReportWrap = $('inlineReportWrap');
  const inlineCols = $('inlineCols');
  const inlineTabs = $('inlineTabs');
  const confirmText = $('confirmText');
  const backupAck = $('backupAck');
  const warnAck = $('warnAck');
  const warnAckWrap = $('warnAckWrap');
  const btnBackup = $('btnBackup');
  const toggleGroup = $('toggleGroup');
  const toggleHideIgnored = $('toggleHideIgnored');
  const btnSelectAllCandidates = $('btnSelectAllCandidates');
  const btnClearSelections = $('btnClearSelections');
  const btnSelectZeroRef = $('btnSelectZeroRef');
  const btnInvertColumnSel = $('btnInvertColumnSel');
  const btnSelectAllTableCandidates = $('btnSelectAllTableCandidates');
  const btnClearTableSelections = $('btnClearTableSelections');
  const btnSelectSafeTables = $('btnSelectSafeTables');
  const btnInvertTableSel = $('btnInvertTableSel');
  const btnLoadConfig = $('btnLoadConfig');
  const btnSaveConfig = $('btnSaveConfig');
  const btnPresetStrict = $('btnPresetStrict');
  const btnPresetRelaxed = $('btnPresetRelaxed');
  const reservedTablesEl = $('reservedTables');
  const reservedColumnsEl = $('reservedColumns');

  let lastColumns = [];
  let lastTables = [];
  let lastSummary = null;
  let lastScanConfig = null;
  let grouped = false;
  let isAdmin = false;
  let adminVia = 'unknown';
  let adminToken = '';
  let hasWarnings = false;
  let currentSQL = '';

  // Utilities migrated from stray block
  function escape(s){ return String(s||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }
  function shortPath(p){ if(!p) return ''; try{ const parts = p.split('/'); return escape(parts.slice(-3).join('/')); }catch(_){ return escape(p); } }
  function formatSize(bytes){ try{ const b = parseInt(bytes||0,10); if(!b||b<=0) return ''; const units=['B','KB','MB','GB','TB']; let i=0; let v=b; while(v>=1024&&i<units.length-1){ v/=1024; i++; } return v.toFixed(i?1:0)+' '+units[i]; }catch(_){ return ''; } }

  function renderReportPreviewData(data){
    try {
      const s = data && data.summary || {};
      const cols = data && Array.isArray(data.columns) ? data.columns : [];
      const tables = data && Array.isArray(data.unused_tables) ? data.unused_tables : [];
      const topCols = cols.filter(c=>c.safe_candidate).slice(0, 10);
      const topTables = tables.filter(t=>t.safe_candidate).slice(0, 10);
      const el = document.getElementById('reportPreview');
      if (!el) return;
      const toRow = (arr, map)=> arr.map(map).map(li=>`<li class="mono">${li}</li>`).join('');
      el.innerHTML = `
        <div>Tables: <strong>${s.tables||0}</strong> · Columns: <strong>${s.columns||0}</strong> · Candidate drops: <strong>${s.candidates||0}</strong></div>
        <div class="adm-grid adm-grid--2 mt-2">
          <div>
            <div class="muted mb-1">Top candidate columns</div>
            <ol>${toRow(topCols, c=>`${escape(c.table)}.${escape(c.column)} (refs:${c.ref_files||0})`) || '<li class="muted">None</li>'}</ol>
          </div>
          <div>
            <div class="muted mb-1">Top candidate tables</div>
            <ol>${toRow(topTables, t=>`${escape(t.table)} (rows:${t.row_estimate||0}, refs:${t.ref_files||0})`) || '<li class="muted">None</li>'}</ol>
          </div>
        </div>`;
    } catch(_) { /* noop */ }
  }

  function renderInline(data){
    try {
      const cols = (data && data.columns) || [];
      const tabs = (data && data.unused_tables) || [];
      inlineCols.innerHTML = '';
      inlineTabs.innerHTML = '';
      const fragC = document.createDocumentFragment();
      cols.forEach(c=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td class="mono">${escape(c.table)}</td><td class="mono">${escape(c.column)}</td><td>${escape(c.data_type||'')}</td><td>${c.ref_files||0}</td><td>${c.safe_candidate?'<span class="pill green">Yes</span>':'<span class="pill red">No</span>'}</td>`;
        fragC.appendChild(tr);
      });
      inlineCols.appendChild(fragC);
      const fragT = document.createDocumentFragment();
      tabs.forEach(t=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td class="mono">${escape(t.table)}</td><td>${t.row_estimate||''}</td><td>${formatSize(t.size_bytes)}</td><td>${t.ref_files||0}</td><td>${t.fk_inbound||0}</td><td>${t.fk_outbound||0}</td><td>${t.safe_candidate?'<span class=\"pill green\">Yes</span>':'<span class=\"pill red\">No</span>'}</td>`;
        fragT.appendChild(tr);
      });
      inlineTabs.appendChild(fragT);
    } catch(_){ /* noop */ }
  }

  function populateDiffSelects(list){
    function setOpts(sel){ sel.innerHTML=''; list.forEach(it=>{ const opt=document.createElement('option'); opt.value=it.path; opt.textContent=it.timestamp; sel.appendChild(opt); }); }
    if (Array.isArray(list) && list.length){ if (diffA) setOpts(diffA); if (diffB) setOpts(diffB); }
  }

  const LS_IGNORE_COLS = 'wf_db_audit_ignore_columns';
  const LS_IGNORE_TABLES = 'wf_db_audit_ignore_tables';
  function loadIgnored(kind){
    try { const raw = localStorage.getItem(kind === 'tables' ? LS_IGNORE_TABLES : LS_IGNORE_COLS); return raw ? JSON.parse(raw) : []; } catch(_) { return []; }
  }
  function saveIgnored(kind, arr){
    try { localStorage.setItem(kind === 'tables' ? LS_IGNORE_TABLES : LS_IGNORE_COLS, JSON.stringify(arr||[])); } catch(_) {}
  }
  function isIgnoredColumn(t,c){ const list = loadIgnored('columns'); return list.indexOf(`${t}.${c}`) !== -1; }
  function isIgnoredTable(t){ const list = loadIgnored('tables'); return list.indexOf(t) !== -1; }
  function ignoreColumn(t,c){ const list = loadIgnored('columns'); const k = `${t}.${c}`; if (list.indexOf(k)===-1) { list.push(k); saveIgnored('columns', list); }}
  function ignoreTable(t){ const list = loadIgnored('tables'); if (list.indexOf(t)===-1) { list.push(t); saveIgnored('tables', list); }}
  function unignoreColumn(t,c){ let list = loadIgnored('columns'); const k = `${t}.${c}`; list = list.filter(x=>x!==k); saveIgnored('columns', list); }
  function unignoreTable(t){ let list = loadIgnored('tables'); list = list.filter(x=>x!==t); saveIgnored('tables', list); }

  function setStatus(msg){ status.textContent = msg || ''; }

  function renderTable(cols){
    rows.innerHTML = '';
    const q = (document.getElementById('filterTable').value || '').toLowerCase();
    const onlyCand = !!document.getElementById('onlyCandidates').checked;
    const hideIgnored = !!toggleHideIgnored.checked;
    const filtered = cols.filter(c => {
      if (onlyCand && !c.safe_candidate) return false;
      if (q && String(c.table||'').toLowerCase().indexOf(q) === -1) return false;
      if (hideIgnored && isIgnoredColumn(c.table, c.column)) return false;
      return true;
    });

  btnSaveReport.addEventListener('click', async ()=>{
    if (!lastSummary) { setStatus('Run a scan first'); return; }
    setStatus('Saving report…');
    try {
      const payload = getAuthPayload({ action:'save_report', data: {
        summary: lastSummary || {},
        columns: lastColumns || [],
        unused_tables: lastTables || [],
        scan_config: lastScanConfig || {},
        selected_columns: selected(),
        selected_tables: selectedTables(),
        ui_state: {
          filter_table: (document.getElementById('filterTable').value||''),
          only_candidates: !!document.getElementById('onlyCandidates').checked,
          only_table_candidates: !!document.getElementById('onlyTableCandidates').checked,
          hide_ignored: !!toggleHideIgnored.checked,
          grouped: !!toggleGroup.checked
        }
      }});
      const r = await fetch('/api/db_schema_audit.php?action=save_report', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
      const j = await r.json();
      if (j && j.success) {
        setStatus('Report saved: ' + (j.data && j.data.timestamp));
      } else {
        setStatus('Save failed');
      }
    } catch(e){ setStatus('Save error'); }
  });

    if (grouped) {
      const byTable = {};
      filtered.forEach((c, idx) => { (byTable[c.table] = byTable[c.table] || []).push({ c, idx }); });
      Object.keys(byTable).sort().forEach(table => {
        const header = document.createElement('tr');
        header.innerHTML = `<td></td><td colspan="10" class="mono table-group-header">${escape(table)}</td>`;
        rows.appendChild(header);
        byTable[table].forEach(({c, idx}) => addColumnRow(c, idx));
      });
    } else {
      filtered.forEach((c, idx)=> addColumnRow(c, idx));
    }
    updateSql();
  }

  function addColumnRow(c, idx){
      const disable = !c.safe_candidate;
      const ignored = isIgnoredColumn(c.table, c.column);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="checkbox" data-idx="${idx}" ${disable?'disabled':''}></td>
        <td class="mono">${escape(c.table)}</td>
        <td class="mono">${escape(c.column)}</td>
        <td>${escape(c.data_type||'')}</td>
        <td>${escape(c.key||'')}</td>
        <td>${c.index_count||0}</td>
        <td>${c.fk_count||0}</td>
        <td>${c.ref_files||0}</td>
        <td class="mono">${(c.examples||[]).map(e=>shortPath(e)).join('<br>')}</td>
        <td>${c.safe_candidate?'<span class="pill green">candidate</span>':'<span class="pill red" title="'+escape((c.not_safe_reasons||[]).join(', '))+'">no</span>'}</td>
        <td>
          <button class="btn secondary btn-ignore-col" data-t="${escape(c.table)}" data-c="${escape(c.column)}">${ignored?'Unignore':'Ignore'}</button>
        </td>
      `;
      rows.appendChild(tr);
  }
  

  function renderTables(tables){
    const tbody = document.getElementById('rowsTables');
    tbody.innerHTML = '';
    const hideIgnored = !!toggleHideIgnored.checked;
    tables.filter(t => !hideIgnored || !isIgnoredTable(t.table)).forEach((t, idx)=>{
      const disable = !t.safe_candidate;
      const ignored = isIgnoredTable(t.table);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="checkbox" data-tidx="${idx}" ${disable?'disabled':''}></td>
        <td class="mono">${escape(t.table)}</td>
        <td>${t.row_estimate ?? ''}</td>
        <td>${formatSize(t.size_bytes)}</td>
        <td>${t.columns ?? ''}</td>
        <td>${t.ref_files ?? 0}</td>
        <td>${t.fk_inbound ?? 0}</td>
        <td>${t.fk_outbound ?? 0}</td>
        <td>${t.indexes ?? 0}</td>
        <td>${t.safe_candidate?'<span class="pill green">candidate</span>':'<span class="pill red" title="'+escape((t.not_safe_reasons||[]).join(', '))+'">no</span>'}</td>
        <td>
          <button class="btn secondary btn-ignore-table" data-t="${escape(t.table)}">${ignored?'Unignore':'Ignore'}</button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  // getAuthPayload used by multiple requests
  function getAuthPayload(extra){
    const base = extra && typeof extra === 'object' ? extra : {};
    if (adminToken) base.admin_token = adminToken;
    return base;
  }

  function selected(){
    const out = [];
    rows.querySelectorAll('input[type=checkbox][data-idx]:checked').forEach(cb=>{
      const i = parseInt(cb.getAttribute('data-idx'),10);
      if (!isNaN(i) && lastColumns[i]) out.push({ table: lastColumns[i].table, column: lastColumns[i].column });
    });
    return out;
  }

  function selectedTables(){
    const out = [];
    document.querySelectorAll('#rowsTables input[type=checkbox][data-tidx]:checked').forEach(cb=>{
      const i = parseInt(cb.getAttribute('data-tidx'),10);
      if (!isNaN(i) && lastTables[i]) out.push({ table: lastTables[i].table });
    });
    return out;
  }

  async function updateSql(){
    const sel = selected();
    const selTables = selectedTables();
    if (sel.length === 0 && selTables.length === 0) { sqlOut.textContent = ''; btnCopySQL.disabled = true; btnExecute.disabled = true; return; }
    try {
      const r = await fetch('/api/db_schema_audit.php?action=generate_sql', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(getAuthPayload({ action: 'generate_sql', columns: sel, tables: selTables }))
      });
      const j = await r.json();
      if (j && j.success) {
        currentSQL = j.data.sql || '';
        sqlOut.textContent = currentSQL;
        btnCopySQL.disabled = !currentSQL;
        btnDownloadSQL.disabled = !currentSQL;
        // warnings handling
        hasWarnings = Array.isArray(j.data.warnings) && j.data.warnings.length > 0;
        warnAckWrap.style.display = hasWarnings ? '' : 'none';
        if (warnAck) warnAck.checked = false;
        btnExecute.disabled = hasWarnings ? true : false;
        // render warnings
        sqlWarnings.innerHTML = '';
        (j.data.warnings||[]).forEach(w=>{ const li=document.createElement('li'); li.textContent = w; sqlWarnings.appendChild(li); });
      } else {
        currentSQL = '';
        sqlOut.textContent = 'Failed to generate SQL';
        btnCopySQL.disabled = true; btnDownloadSQL.disabled = true; btnExecute.disabled = true;
        sqlWarnings.innerHTML = '';
        hasWarnings = false;
        warnAckWrap.style.display = 'none';
      }
    } catch (e){ sqlOut.textContent = 'Error generating SQL'; btnCopySQL.disabled = true; btnExecute.disabled = true; }
  }

  if (btnDownloadSQL) btnDownloadSQL.addEventListener('click', ()=>{
    if (!currentSQL) return;
    const blob = new Blob([currentSQL], { type: 'text/sql;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'db_schema_audit.sql';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(()=>URL.revokeObjectURL(url), 1000);
  });

  chkAll.addEventListener('change', ()=>{
    const cbs = rows.querySelectorAll('input[type=checkbox][data-idx]:not(:disabled)');
    cbs.forEach(cb=>{ cb.checked = chkAll.checked; });
    updateSql();
  });
  document.getElementById('chkAllTables').addEventListener('change', (e)=>{
    const cbs = document.querySelectorAll('#rowsTables input[type=checkbox][data-tidx]:not(:disabled)');
    cbs.forEach(cb=>{ cb.checked = e.target.checked; });
    updateSql();
  });

  rows.addEventListener('change', (e)=>{
    const cb = e.target.closest && e.target.closest('input[type=checkbox][data-idx]');
    if (!cb) return;
    updateSql();
  });

  // Ignore actions
  document.addEventListener('click', (e)=>{
    const btnCol = e.target.closest && e.target.closest('.btn-ignore-col');
    if (btnCol) {
      e.preventDefault();
      const t = btnCol.getAttribute('data-t');
      const c = btnCol.getAttribute('data-c');
      if (btnCol.textContent === 'Ignore') { ignoreColumn(t,c); } else { unignoreColumn(t,c); }
      renderTable(lastColumns);
      return;
    }
    const btnTbl = e.target.closest && e.target.closest('.btn-ignore-table');
    if (btnTbl) {
      e.preventDefault();
      const t = btnTbl.getAttribute('data-t');
      if (btnTbl.textContent === 'Ignore') { ignoreTable(t); } else { unignoreTable(t); }
      renderTables(lastTables);
      return;
    }
  });

  btnCopySQL.addEventListener('click', async ()=>{
    try { await navigator.clipboard.writeText(sqlOut.textContent || ''); setStatus('SQL copied'); setTimeout(()=>setStatus(''), 1000); } catch(_){ setStatus('Copy failed'); }
  });

  // Backup now
  if (btnBackup) btnBackup.addEventListener('click', async ()=>{
    setStatus('Creating backup…');
    try {
      const r = await fetch('/api/backup_website.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ download_to_computer: false, keep_on_server: true }) });
      const j = await r.json();
      if (j && j.success) {
        const size = j.size_formatted || (j.size + ' bytes');
        setStatus(`Backup created: ${j.filename} (${size})`);
      } else {
        setStatus('Backup failed');
      }
    } catch(_){ setStatus('Backup error'); }
  });

  btnExecute.addEventListener('click', async ()=>{
    const sel = selected();
    const selTables = selectedTables();
    if (sel.length === 0 && selTables.length === 0) { setStatus('No selections'); return; }
    const confirmOk = (confirmText.value.trim().toUpperCase() === 'DROP');
    const backupOk = !!backupAck.checked;
    if (!confirmOk || !backupOk) { setStatus('Confirm with "DROP" and check backup'); return; }
    if (hasWarnings && (!warnAck || !warnAck.checked)) { setStatus('Acknowledge warnings to proceed'); return; }
    setStatus('Executing…');
    try {
      const r = await fetch('/api/db_schema_audit.php?action=execute', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(getAuthPayload({ action: 'execute', columns: sel, tables: selTables, confirm: 'DROP', backup_ack: true }))
      });
      const j = await r.json();
      if (j && j.success) {
        setStatus('Executed ' + (j.data && j.data.executed || 0) + ' statements');
      } else {
        setStatus('Execution failed: ' + (j && (j.error||'') || 'unknown'));
      }
    } catch (e){ setStatus('Execution error'); }
  });

  btnScan.addEventListener('click', async ()=>{
    setStatus('Scanning… (this may take a minute)');
    rows.innerHTML='';
    sqlOut.textContent='';
    sqlWarnings.innerHTML='';
    summary.textContent='';
    lastColumns = [];
    lastTables = [];
    try {
      const excludes = (document.getElementById('excludes').value || '').split(/\n+/).map(s=>s.trim()).filter(Boolean);
      const extsEl = document.getElementById('exts');
      const exts = extsEl ? (extsEl.value || '').split(/[\,\s]+/).map(s=>s.trim()).filter(Boolean) : [];
      const r = await fetch('/api/db_schema_audit.php?action=scan', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(getAuthPayload({ action:'scan', excludes, exts })) });
      const j = await r.json();
      if (j && j.success) {
        const d = j.data || {};
        lastColumns = d.columns || [];
        lastTables = d.unused_tables || [];
        lastSummary = d.summary || null;
        renderTable(lastColumns);
        renderTables(lastTables);
        summary.innerHTML = `Tables: <strong>${(d.summary&&d.summary.tables)||0}</strong> · Columns: <strong>${(d.summary&&d.summary.columns)||0}</strong> · Candidate drops: <strong>${(d.summary&&d.summary.candidates)||0}</strong>`;
        renderReportPreviewData(d);
        try {
          const cfg = d.scan_config || {}; lastScanConfig = cfg; const ex = (cfg.excludes||[]).join(', '); const extsStr = (cfg.exts||[]).join(', ');
          document.getElementById('scanConfigNote').textContent = `Using excludes: ${ex} | extensions: ${extsStr}`;
          if (!document.getElementById('excludes').value) document.getElementById('excludes').value = (cfg.excludes||[]).join('\n');
          const extsEl2 = document.getElementById('exts');
          if (extsEl2 && !extsEl2.value) extsEl2.value = (cfg.exts||[]).join(',');
        } catch(_){ }
        setStatus('');
        btnSaveReport.disabled = false;
      } else {
        setStatus('Scan failed');
      }
    } catch (e) {
      setStatus('Scan error');
    }
  });
  document.getElementById('filterTable').addEventListener('input', ()=>renderTable(lastColumns));
  document.getElementById('onlyCandidates').addEventListener('change', ()=>renderTable(lastColumns));
  toggleGroup.addEventListener('change', ()=>{ grouped = !!toggleGroup.checked; renderTable(lastColumns); });
  toggleHideIgnored.addEventListener('change', ()=>{ renderTable(lastColumns); renderTables(lastTables); });

  // Reports browser and auth (migrated from stray block)
  async function refreshReports(){
    try {
      const r = await fetch('/api/db_schema_audit.php?action=list_reports', { credentials:'include' });
      const j = await r.json();
      reportsList.innerHTML = '';
      if (!(j && j.success && Array.isArray(j.data))) { reportsList.textContent = 'No reports'; return; }
      const frag = document.createDocumentFragment();
      j.data.forEach(it=>{
        const div = document.createElement('div');
        div.className = 'list-item';
        const link = document.createElement('a'); link.href = it.path; link.textContent = it.timestamp; link.target = '_blank';
        const meta = document.createElement('span'); meta.className = 'muted'; meta.textContent = ` ${it.bytes||0} bytes, ${it.modified||''}`;
        const left = document.createElement('span'); left.appendChild(link); left.appendChild(meta);
        const actions = document.createElement('span'); actions.style.marginLeft = 'auto'; actions.style.display = 'flex'; actions.style.gap = '6px';
        const btnLoad = document.createElement('button'); btnLoad.className = 'btn secondary'; btnLoad.textContent = 'Load'; btnLoad.addEventListener('click', ()=>loadReport(it.path));
        const btnPreview = document.createElement('a'); btnPreview.className = 'btn secondary'; btnPreview.textContent = 'Preview'; btnPreview.href = it.path; btnPreview.target = '_blank';
        actions.appendChild(btnLoad); actions.appendChild(btnPreview);
        div.appendChild(left);
        div.appendChild(actions);
        frag.appendChild(div);
      });
      reportsList.appendChild(frag);
      populateDiffSelects(j.data);
    } catch(_){ reportsList.textContent = 'Error loading reports'; }
  }
  if (btnRefreshReports) btnRefreshReports.addEventListener('click', refreshReports);
  refreshReports();

  async function loadReport(path){
    try {
      setStatus('Loading report…');
      const r = await fetch(path, { credentials:'include' });
      const j = await r.json();
      const d = j || {};
      // Support both flat payloads and {data:{...}}
      const data = d.data || d;
      lastColumns = data.columns || [];
      lastTables = data.unused_tables || [];
      lastSummary = data.summary || null;
      lastScanConfig = data.scan_config || null;
      renderTable(lastColumns);
      renderTables(lastTables);
      summary.innerHTML = `Tables: <strong>${(lastSummary&&lastSummary.tables)||0}</strong> · Columns: <strong>${(lastSummary&&lastSummary.columns)||0}</strong> · Candidate drops: <strong>${(lastSummary&&lastSummary.candidates)||0}</strong>`;
      renderReportPreviewData(data);
      // Apply saved selections if present
      applySelectedColumns(data.selected_columns||[]);
      applySelectedTables(data.selected_tables||[]);
      // Apply saved UI state if present
      try {
        const u = data.ui_state||{};
        const ft = document.getElementById('filterTable'); if (ft) ft.value = u.filter_table||'';
        const oc = document.getElementById('onlyCandidates'); if (oc) oc.checked = !!u.only_candidates;
        const otc = document.getElementById('onlyTableCandidates'); if (otc) otc.checked = !!u.only_table_candidates;
        toggleHideIgnored.checked = !!u.hide_ignored;
        toggleGroup.checked = !!u.grouped; grouped = !!u.grouped;
      } catch(_){ }
      await updateSql();
      // If inline is visible, render it too
      if (inlineReportWrap && inlineReportWrap.style.display !== 'none') renderInline(data);
      setStatus('Report loaded');
      btnSaveReport.disabled = !isAdmin; // leave disabled state based on auth
    } catch(_){ setStatus('Failed to load report'); }
  }

  // Check auth status and toggle controls
  (async function checkAuth(){
    try {
      const r = await fetch('/api/db_schema_audit.php?action=whoami', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify(getAuthPayload({ action:'whoami' })) });
      const j = await r.json();
      isAdmin = !!(j && j.success && j.data && j.data.admin);
      adminVia = (j && j.data && j.data.via) || 'unknown';
      if (authBadge) authBadge.textContent = 'Auth: ' + (isAdmin ? ('admin (' + adminVia + ')') : 'not admin');
      // Disable actions requiring admin if not admin
      btnExecute.disabled = !isAdmin;
      btnSaveReport.disabled = !isAdmin;
    } catch(_){ if (authBadge) authBadge.textContent = 'Auth: error'; }
  })();

  // Persist and react to admin token
  try { const saved = localStorage.getItem('wf_admin_token'); if (saved) { adminToken = saved; if (adminTokenInput) adminTokenInput.value = saved; } } catch(_){ }
  if (adminTokenInput) adminTokenInput.addEventListener('input', ()=>{ adminToken = adminTokenInput.value.trim(); try { localStorage.setItem('wf_admin_token', adminToken); } catch(_){ } });

  // Export CSV
  btnExportCsv.addEventListener('click', ()=>{
    try {
      const hideIgnored = !!toggleHideIgnored.checked;
      const colRows = lastColumns.filter(c => !hideIgnored || !isIgnoredColumn(c.table, c.column));
      const tblRows = lastTables.filter(t => !hideIgnored || !isIgnoredTable(t.table));
      const esc = (v) => {
        const s = String(v ?? '');
        return '"' + s.replace(/"/g,'""') + '"';
      };
      let csv = 'type,table,column,data_type,key,indexes,fks,ref_files,row_estimate,notes\n';
      colRows.forEach(c => {
        csv += ['column', c.table, c.column, c.data_type||'', c.key||'', c.index_count||0, c.fk_count||0, c.ref_files||0, '', (c.safe_candidate?'candidate':(c.not_safe_reasons||[]).join('; '))].map(esc).join(',') + '\n';
      });
      tblRows.forEach(t => {
        csv += ['table', t.table, '', '', '', t.indexes||0, (t.fk_inbound||0)+(t.fk_outbound||0), t.ref_files||0, t.row_estimate||'', (t.safe_candidate?'candidate':(t.not_safe_reasons||[]).join('; '))].map(esc).join(',') + '\n';
      });
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = 'db_schema_audit.csv'; document.body.appendChild(a); a.click(); setTimeout(()=>{ URL.revokeObjectURL(url); a.remove(); }, 0);
      setStatus('CSV exported');
    } catch(_){ setStatus('CSV export failed'); }
  });
})();
</script>
</body>
</html>

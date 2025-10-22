<?php
// sections/tools/deploy_manager.php
// Browser-based Deploy Manager (Backup, Fast Deploy, Full Deploy)

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'Deploy Manager has been removed.';
return;

// Resolve project root
$ROOT = dirname(__DIR__, 2);

// Optional auth include (enforce if available)
$authPath = $ROOT . '/includes/auth.php';
$authHelperPath = $ROOT . '/includes/auth_helper.php';
if (file_exists($authPath)) {
    require_once $authPath;
    if (file_exists($authHelperPath)) {
        require_once $authHelperPath;
    }
    if (class_exists('AuthHelper')) {
        $loggedIn = AuthHelper::isLoggedIn();
    } else {
        $loggedIn = function_exists('isLoggedIn') ? isLoggedIn() : false;
    }
    if (!$loggedIn) {
        http_response_code(403);
        echo '<div class="adm-denied">'
           . '<h1 class="adm-denied__title">Access denied</h1>'
           . '<p class="mb-2">Please login to access the Deploy Manager.</p>'
           . '<p><a href="/admin/login.php" class="adm-link">Go to Login</a></p>'
           . '</div>';
        return;
    }
}

// Common helpers
function run_shell($cmd, $cwd) {
    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptorspec, $pipes, $cwd, [
        'PATH' => getenv('PATH'),
        'HOME' => getenv('HOME'),
    ]);
    if (!is_resource($proc)) {
        return ["success" => false, "output" => "Failed to start command." ];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($proc);

    $all = trim($stdout . (strlen($stderr) ? "\n--- STDERR ---\n$stderr" : ''));
    return [
        'success' => ($status === 0),
        'exit_code' => $status,
        'output' => $all,
    ];
}

// Handle actions
$action = $_POST['action'] ?? '';
$dryRun = isset($_POST['dry_run']) && $_POST['dry_run'] === '1' ? '1' : '0';
$dbw = isset($_POST['dbw']) && $_POST['dbw'] === '1' ? '1' : '0';
$response = null;

if ($action) {
    if ($action === 'backup') {
        // Call the existing backup API endpoint (same-origin)
        $url = '/api/backup_website.php';
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode([ 'download_to_computer' => false, 'keep_on_server' => true ]),
                'ignore_errors' => true,
            ]
        ];
        $ctx = stream_context_create($opts);
        $out = @file_get_contents($url, false, $ctx);
        $response = [
            'success' => $out !== false,
            'output' => $out !== false ? $out : 'Backup API call failed.',
        ];
    } elseif ($action === 'fast_deploy' || $action === 'full_deploy') {
        // For deploy actions, UI prefers SSE streaming via /sections/tools/deploy_stream.php.
        // Keep non-streaming fallback when JS is disabled.
        $cmd = ($action === 'fast_deploy')
            ? (($dryRun === '1') ? 'WF_DRY_RUN=1 bash scripts/deploy.sh' : 'bash scripts/deploy.sh')
            : (($dryRun === '1') ? 'WF_DRY_RUN=1 bash scripts/deploy_full.sh' : 'bash scripts/deploy_full.sh');
        $response = run_shell($cmd, $ROOT);
    } elseif ($action === 'verify') {
        // Verify Only: perform lightweight HTTP checks (no deploy)
        $baseUrl = 'https://whimsicalfrog.us' . (getenv('WF_PUBLIC_BASE') ?: '');
        $lines = [];
        $lines[] = "Verifying: $baseUrl";
        $targets = [
            ['', 'Home page'],
            ['/dist/.vite/manifest.json', 'Vite manifest (.vite)'],
            ['/dist/manifest.json', 'Vite manifest (fallback)'],
            ['/images/logos/logo-whimsicalfrog.webp', 'Logo image'],
        ];
        foreach ($targets as [$path, $label]) {
            $ch = curl_init($baseUrl . $path);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_TIMEOUT => 15,
            ]);
            $ok = curl_exec($ch);
            $code = ($ok === false) ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $lines[] = sprintf("  • %s -> HTTP %s", $label, $code ?: 'ERR');
        }
        $response = ['success' => true, 'output' => implode("\n", $lines)];
    } elseif ($action === 'quarantine') {
        // Run duplicate/backup quarantine script
        $cmd = 'bash scripts/maintenance/quarantine_duplicates.sh';
        $response = run_shell($cmd, $ROOT);
    } elseif ($action === 'stale_scan' || $action === 'stale_move') {
        // Run stale assets scan (report) or move
        $cmd = 'node scripts/maintenance/find_stale_assets.mjs';
        if ($action === 'stale_move') { $cmd .= ' --move'; }
        if ($dbw === '1') { $cmd .= ' --db-whitelist'; }
        $response = run_shell($cmd, $ROOT);
    }
}

// Render UI
$EMBED = isset($_GET['modal']) && $_GET['modal'] == '1';
$headerPath = $ROOT . '/partials/header.php';
$footerPath = $ROOT . '/partials/footer.php';
if (!$EMBED && file_exists($headerPath)) require $headerPath;
?>

<div class="wf-container adm-container <?php echo $EMBED ? 'adm--embed' : ''; ?>">
  <?php if (!$EMBED): ?>
  <div class="adm-card__header">
    <h1 class="adm-title">Deploy Manager</h1>
    <form id="dryRunForm" method="post" class="adm-form-inline">
      <input type="hidden" name="action" value="noop" />
      <label class="adm-label-inline">
        <input type="checkbox" id="dryRunToggle" <?php echo $dryRun==='1' ? 'checked' : '';?> />
        Dry Run
      </label>
      <label class="adm-label-inline" title="Whitelist assets referenced in the database when scanning for stale">
        <input type="checkbox" id="dbWhitelistToggle" <?php echo $dbw==='1' ? 'checked' : '';?> />
        DB Whitelist
      </label>
    </form>
    <div class="adm-header-actions">
      <button type="button" class="wf-admin-nav-button" id="openWhitelistModalBtn">Manage Whitelist</button>
    </div>
  </div>
  <?php endif; ?>

  <div class="adm-section">
    <h2>Stale Cleanup Planner</h2>
    <div class="adm-actions mb-2">
      <button type="button" class="wf-admin-nav-button" id="plannerScanBtn">Run JSON Scan</button>
      <label class="adm-label-inline ml-2"><input type="checkbox" id="plannerDbw" <?php echo $dbw==='1' ? 'checked' : '';?> /> Use DB Whitelist</label>
    </div>
    <div id="plannerArea" class="adm-panel text-sm text-gray-800">
      <div class="text-muted">Click "Run JSON Scan" to preview stale assets. You can then select files to whitelist or move.</div>
    </div>
    <div class="adm-actions mt-2 hidden" id="plannerActions">
      <button type="button" class="wf-admin-nav-button" id="plannerWhitelistBtn">Whitelist Selected</button>
      <button type="button" class="wf-admin-nav-button" id="plannerMoveBtn">Move Selected to backups/stale/</button>
    </div>
  </div>

  <div class="adm-section">
    <h2>Repository Hygiene</h2>
    <div class="adm-grid adm-grid--auto">
      <form method="post" onsubmit="return submitAction(this)">
        <input type="hidden" name="action" value="quarantine" />
        <input type="hidden" name="dry_run" value="0" />
        <input type="hidden" name="dbw" value="<?php echo htmlspecialchars($dbw, ENT_QUOTES); ?>" />
        <button class="wf-admin-nav-button adm-btn--full">Quarantine Duplicates/Backups</button>
        <div class="text-muted">Moves files like "* 2.ext", "*.bak" into backups/duplicates/ (preserving paths).</div>
      </form>

      <form method="post" onsubmit="return submitAction(this)">
        <input type="hidden" name="action" value="stale_scan" />
        <input type="hidden" name="dry_run" value="0" />
        <input type="hidden" name="dbw" value="<?php echo htmlspecialchars($dbw, ENT_QUOTES); ?>" />
        <button class="wf-admin-nav-button adm-btn--full">Scan for Stale Assets</button>
        <div class="text-muted">Reports unreferenced assets in images/, src/styles/, src/js/, src/modules/.</div>
      </form>

      <form method="post" onsubmit="return submitAction(this)">
        <input type="hidden" name="action" value="stale_move" />
        <input type="hidden" name="dry_run" value="0" />
        <input type="hidden" name="dbw" value="<?php echo htmlspecialchars($dbw, ENT_QUOTES); ?>" />
        <button class="wf-admin-nav-button adm-btn--full">Move Stale Assets to backups/stale/</button>
        <div class="text-muted">Quarantines reported stale assets to backups/stale/ (preserving paths).</div>
      </form>
    </div>
  </div>

  <div class="adm-section adm-grid adm-grid--auto">
    <form method="post" onsubmit="return submitAction(this)">
      <input type="hidden" name="action" value="backup" />
      <input type="hidden" name="dry_run" value="0" />
      <input type="hidden" name="dbw" value="<?php echo htmlspecialchars($dbw, ENT_QUOTES); ?>" />
      <button class="wf-admin-nav-button adm-btn--full">Create Live Backup</button>
      <div class="text-muted">Triggers /api/backup_website.php</div>
    </form>

    <form method="post" onsubmit="return submitAction(this)" data-stream="1">
      <input type="hidden" name="action" value="fast_deploy" />
      <input type="hidden" name="dry_run" value="<?php echo htmlspecialchars($dryRun, ENT_QUOTES); ?>" />
      <input type="hidden" name="dbw" value="<?php echo htmlspecialchars($dbw, ENT_QUOTES); ?>" />
      <button class="wf-admin-nav-button adm-btn--full">Fast Deploy (Files)</button>
      <div class="text-muted">Build + SFTP mirror (Skips DB). Honors Dry Run.</div>
    </form>

    <form method="post" onsubmit="return submitAction(this)" data-stream="1">
      <input type="hidden" name="action" value="full_deploy" />
      <input type="hidden" name="dry_run" value="<?php echo htmlspecialchars($dryRun, ENT_QUOTES); ?>" />
      <input type="hidden" name="dbw" value="<?php echo htmlspecialchars($dbw, ENT_QUOTES); ?>" />
      <button class="wf-admin-nav-button adm-btn--full">Full Deploy (DB + Files)</button>
      <div class="text-muted">Dump local DB, restore to live (API or direct), then files. Honors Dry Run.</div>
    </form>

    <form method="post" onsubmit="return submitAction(this)">
      <input type="hidden" name="action" value="verify" />
      <input type="hidden" name="dry_run" value="0" />
      <input type="hidden" name="dbw" value="<?php echo htmlspecialchars($dbw, ENT_QUOTES); ?>" />
      <button class="wf-admin-nav-button adm-btn--full">Verify Only (HTTP Checks)</button>
      <div class="text-muted">Checks home, manifest, and logo endpoints. No deploy.</div>
    </form>
  </div>

  <div class="adm-section">
    <h2>Result</h2>
    <pre class="adm-pre">
<?php if ($action && $response) {
    echo htmlspecialchars((string)$response['output'], ENT_QUOTES);
} else {
    echo "Ready. Choose an action above.";
} ?>
    </pre>
  </div>

  <div class="adm-section">
    <h2>Last Runs</h2>
    <div id="lastRuns" class="adm-last-runs text-sm text-gray-700"></div>
  </div>
</div>

<!-- Inline Whitelist Modal -->
<div id="deployWhitelistModal" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="deployWhitelistTitle">
  <div class="admin-modal admin-modal-content admin-modal--md">
    <div class="modal-header">
      <h2 id="deployWhitelistTitle" class="admin-card-title">Asset Whitelist</h2>
      <button type="button" class="admin-modal-close wf-admin-nav-button" aria-label="Close">×</button>
    </div>
    <div class="modal-body">
      <p class="text-sm text-gray-600 mb-2">Patterns below are excluded from stale cleanup when "DB Whitelist" is enabled. Patterns match by substring on the full path (case-insensitive).</p>
      <form id="wlAddForm" class="adm-form-inline mb-3" onsubmit="return addWhitelistPattern(event)">
        <input type="text" id="wlPattern" class="adm-input" placeholder="e.g. images/backgrounds/ or logo-whimsicalfrog.webp" required />
        <button type="submit" class="wf-admin-nav-button">Add</button>
      </form>
      <div id="wlList" class="adm-panel">
        <div class="text-muted">Loading…</div>
      </div>
    </div>
  </div>
  <style>
    /* Focus scroll on topmost modal only */
    html.wf-modal-scroll-lock, body.wf-modal-scroll-lock { overflow: hidden !important; }
    .admin-modal-overlay { overflow: hidden; }
    .admin-modal-overlay.wf-topmost { pointer-events: auto; }
    .admin-modal-overlay:not(.wf-topmost) { pointer-events: none; }
    .admin-modal-overlay.wf-topmost .admin-modal-content { overflow: auto; }
  </style>
</div>

<script>
const resultPre = document.querySelector('pre');
const lastRunsEl = document.getElementById('lastRuns');
function saveRun(action, exitCode) {
  try {
    const key = 'wfDeployHistory';
    const now = new Date();
    const item = { time: now.toISOString(), exit: exitCode };
    const cur = JSON.parse(localStorage.getItem(key) || '{}');
    cur[action] = item;
    localStorage.setItem(key, JSON.stringify(cur));
  } catch (_) {}
}
function renderLastRuns() {
  if (!lastRunsEl) return;
  try {
    const cur = JSON.parse(localStorage.getItem('wfDeployHistory') || '{}');
    const labels = {
      backup: 'Create Live Backup',
      fast_deploy: 'Fast Deploy',
      full_deploy: 'Full Deploy',
      verify: 'Verify Only',
      quarantine: 'Quarantine Duplicates',
      stale_scan: 'Scan Stale Assets',
      stale_move: 'Move Stale Assets'
    };
    const rows = Object.keys(labels).map(k => {
      const v = cur[k];
      const ts = v && v.time ? new Date(v.time).toLocaleString() : '—';
      const ex = v && typeof v.exit !== 'undefined' ? v.exit : '—';
      return `<div class="adm-last-runs__row"><span class="adm-last-runs__label">${labels[k]}:</span> <span class="adm-last-runs__value">${ts} (exit ${ex})</span></div>`;
    });
    lastRunsEl.innerHTML = rows.join('');
  } catch (_) { lastRunsEl.textContent = 'No data.'; }
}
renderLastRuns();
function appendLine(text){
  if (!resultPre) return;
  resultPre.textContent += (resultPre.textContent ? "\n" : "") + text;
  resultPre.scrollTop = resultPre.scrollHeight;
}
function submitAction(form){
  const dryToggle = document.getElementById('dryRunToggle');
  const dbwToggle = document.getElementById('dbWhitelistToggle');
  const dryInput = form.querySelector('input[name="dry_run"]');
  const dbwInput = form.querySelector('input[name="dbw"]');
  if (dryInput && dryToggle) dryInput.value = dryToggle.checked ? '1' : '0';
  if (dbwInput && dbwToggle) dbwInput.value = dbwToggle.checked ? '1' : '0';
  const action = (form.querySelector('input[name="action"]')||{}).value;
  const wantsStream = form.hasAttribute('data-stream');
  if (wantsStream && (action === 'fast_deploy' || action === 'full_deploy')){
    // Use SSE streaming
    try {
      resultPre.textContent = '';
      const params = new URLSearchParams();
      params.set('action', action);
      params.set('dry_run', dryInput ? dryInput.value : '0');
      params.set('dbw', dbwInput ? dbwInput.value : '0');
      const es = new EventSource(`/sections/tools/deploy_stream.php?${params.toString()}`);
      es.addEventListener('message', (ev)=>{ appendLine(ev.data); });
      es.addEventListener('done', ()=>{ appendLine('--- Completed ---'); try { saveRun(action, 0); renderLastRuns(); } catch(_){} es.close(); });
      es.addEventListener('error', ()=>{ appendLine('--- Stream error ---'); es.close(); });
    } catch(e){ appendLine('Streaming not available; falling back.'); return true; }
    return false;
  }
  // Non-streaming (backup/verify)
  setTimeout(()=>{ try { saveRun(action, 0); renderLastRuns(); } catch(_){} }, 0);
  return true;
}

// Stale Cleanup Planner
const plannerArea = document.getElementById('plannerArea');
const plannerActions = document.getElementById('plannerActions');
const plannerDbw = document.getElementById('plannerDbw');
const plannerScanBtn = document.getElementById('plannerScanBtn');
const plannerWhitelistBtn = document.getElementById('plannerWhitelistBtn');
const plannerMoveBtn = document.getElementById('plannerMoveBtn');
let plannerData = { stale: [] };

function renderPlannerTable(items){
  if (!plannerArea) return;
  if (!items || !items.length) { plannerArea.innerHTML = '<div class="text-muted">No stale assets found.</div>'; plannerActions.classList.add('hidden'); return; }
  const rows = items.map((p, idx) => `
    <tr>
      <td><input type="checkbox" class="planner-check" data-path="${p}"></td>
      <td>${p}</td>
    </tr>
  `).join('');
  plannerArea.innerHTML = `
    <div class="mb-2 flex items-center gap-2">
      <label><input type="checkbox" id="plannerSelectAll"> Select all</label>
      <span class="text-muted">(${items.length} items)</span>
    </div>
    <div class="admin-scrollbox admin-scrollbox--md border rounded">
      <table class="adm-table w-full text-sm">
        <thead><tr><th style="width:48px"></th><th>Path</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
  `;
  plannerActions.classList.remove('hidden');
  const selAll = document.getElementById('plannerSelectAll');
  if (selAll) selAll.addEventListener('change', ()=>{
    document.querySelectorAll('.planner-check').forEach(cb => { cb.checked = selAll.checked; });
  });
}

async function runPlannerScan(){
  try {
    if (plannerArea) plannerArea.innerHTML = '<div class="text-muted">Scanning…</div>';
    const res = await fetch('/api/stale_scan.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ dbw: plannerDbw && plannerDbw.checked ? 1 : 0 }) });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Scan failed');
    plannerData = { stale: Array.isArray(json.stale) ? json.stale : [] };
    renderPlannerTable(plannerData.stale);
  } catch(e){ if (plannerArea) plannerArea.innerHTML = '<div class="text-error">Scan error.</div>'; }
}

function getPlannerSelection(){
  return Array.from(document.querySelectorAll('.planner-check'))
    .filter(cb => cb.checked)
    .map(cb => cb.getAttribute('data-path'))
    .filter(Boolean);
}

async function plannerWhitelistSelected(){
  const files = getPlannerSelection();
  if (!files.length) { alert('Select at least one file.'); return; }
  try {
    const res = await fetch('/api/asset_whitelist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'add_many', patterns: files }) });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Whitelist failed');
    // Re-scan to update list
    runPlannerScan();
  } catch(e){ alert('Whitelist failed'); }
}

async function plannerMoveSelected(){
  const files = getPlannerSelection();
  if (!files.length) { alert('Select at least one file.'); return; }
  try {
    const res = await fetch('/api/move_to_stale.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ files }) });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Move failed');
    // Re-scan to update list
    runPlannerScan();
  } catch(e){ alert('Move failed'); }
}

if (plannerScanBtn) plannerScanBtn.addEventListener('click', (e)=>{ e.preventDefault(); runPlannerScan(); });
if (plannerWhitelistBtn) plannerWhitelistBtn.addEventListener('click', (e)=>{ e.preventDefault(); plannerWhitelistSelected(); });
if (plannerMoveBtn) plannerMoveBtn.addEventListener('click', (e)=>{ e.preventDefault(); plannerMoveSelected(); });

// Whitelist modal handlers
const wlModal = document.getElementById('deployWhitelistModal');
const openWlBtn = document.getElementById('openWhitelistModalBtn');
const wlList = document.getElementById('wlList');
function setTopmostModal(modalEl){
  try {
    document.documentElement.classList.add('wf-modal-scroll-lock');
    document.body.classList.add('wf-modal-scroll-lock');
    document.querySelectorAll('.admin-modal-overlay').forEach(n => n.classList.remove('wf-topmost'));
    if (modalEl) modalEl.classList.add('wf-topmost');
  } catch(_){}
}
function openWhitelistModal(){
  if (!wlModal) return;
  wlModal.classList.remove('hidden');
  setTopmostModal(wlModal);
  loadWhitelist();
}
function closeWhitelistModal(){
  if (!wlModal) return;
  wlModal.classList.add('hidden');
  // If no other visible overlays, unlock scroll
  const anyOpen = Array.from(document.querySelectorAll('.admin-modal-overlay')).some(n => !n.classList.contains('hidden'));
  if (!anyOpen) { document.documentElement.classList.remove('wf-modal-scroll-lock'); document.body.classList.remove('wf-modal-scroll-lock'); }
}
async function loadWhitelist(){
  try {
    wlList.innerHTML = '<div class="text-muted">Loading…</div>';
    const res = await fetch('/api/asset_whitelist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'list' }) });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Failed');
    const rows = Array.isArray(json.data) ? json.data : [];
    if (!rows.length) { wlList.innerHTML = '<div class="text-muted">No whitelist patterns yet.</div>'; return; }
    wlList.innerHTML = rows.map(r => (
      `<div class="adm-row flex items-center justify-between">
        <div class="truncate" title="${r.pattern}">${r.pattern}</div>
        <button class="wf-admin-nav-button" data-id="${r.id}" onclick="return removeWhitelistPattern(event, ${r.id})">Remove</button>
      </div>`
    )).join('');
  } catch(e){ wlList.innerHTML = '<div class="text-error">Error loading whitelist.</div>'; }
}
async function addWhitelistPattern(ev){
  ev.preventDefault();
  const input = document.getElementById('wlPattern');
  const pattern = (input && input.value || '').trim();
  if (!pattern) return false;
  try {
    const res = await fetch('/api/asset_whitelist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'add', pattern }) });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Failed');
    input.value = '';
    loadWhitelist();
  } catch(e){ alert('Failed to add pattern'); }
  return false;
}
async function removeWhitelistPattern(ev, id){
  if (ev) ev.preventDefault();
  if (!id) return false;
  try {
    const res = await fetch('/api/asset_whitelist.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'remove', id }) });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Failed');
    loadWhitelist();
  } catch(e){ alert('Failed to remove'); }
  return false;
}
if (openWlBtn) openWlBtn.addEventListener('click', (e)=>{ e.preventDefault(); openWhitelistModal(); });
if (wlModal) wlModal.addEventListener('click', (e)=>{ if (e.target === wlModal) closeWhitelistModal(); });
if (wlModal) wlModal.querySelector('.admin-modal-close')?.addEventListener('click', (e)=>{ e.preventDefault(); closeWhitelistModal(); });
</script>

<?php if (!$EMBED && file_exists($footerPath)) require $footerPath; ?>

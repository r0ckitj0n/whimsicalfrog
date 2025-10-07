<?php
// sections/tools/deploy_manager.php
// Browser-based Deploy Manager (Backup, Fast Deploy, Full Deploy)

// Resolve project root
$ROOT = dirname(__DIR__, 2);

// Optional auth include (enforce if available)
$authPath = $ROOT . '/includes/auth.php';
if (file_exists($authPath)) {
    require_once $authPath;
    if (function_exists('isLoggedIn') && !isLoggedIn()) {
        http_response_code(403);
        echo '<div style="max-width:720px;margin:40px auto;font-family:system-ui, sans-serif">'
           . '<h1 style="font-size:20px;font-weight:800;color:#b91c1c;margin:0 0 8px">Access denied</h1>'
           . '<p style="margin:0 0 12px">Please login to access the Deploy Manager.</p>'
           . '<p><a href="/admin/login.php" style="color:#2563eb">Go to Login</a></p>'
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
    }
}

// Render UI
$EMBED = isset($_GET['modal']) && $_GET['modal'] == '1';
$headerPath = $ROOT . '/partials/header.php';
$footerPath = $ROOT . '/partials/footer.php';
if (!$EMBED && file_exists($headerPath)) require $headerPath;
?>

<div class="wf-container" style="max-width: 900px; margin: <?php echo $EMBED ? '0 auto' : '40px auto'; ?>; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;">
  <div style="padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between;">
    <h1 style="margin:0; font-size: 1.25rem; font-weight: 800;">Deploy Manager</h1>
    <form id="dryRunForm" method="post" style="margin:0; display:flex; align-items:center; gap:10px;">
      <input type="hidden" name="action" value="noop" />
      <label style="display:flex; align-items:center; gap:6px; font-weight:600;">
        <input type="checkbox" id="dryRunToggle" <?php echo $dryRun==='1' ? 'checked' : '';?> />
        Dry Run
      </label>
    </form>
  </div>

  <div style="padding: 16px 20px; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
    <form method="post" onsubmit="return submitAction(this)">
      <input type="hidden" name="action" value="backup" />
      <input type="hidden" name="dry_run" value="0" />
      <button class="wf-admin-nav-button" style="width:100%">Create Live Backup</button>
      <div style="font-size:12px; color:#6b7280; margin-top:6px;">Triggers /api/backup_website.php</div>
    </form>

    <form method="post" onsubmit="return submitAction(this)" data-stream="1">
      <input type="hidden" name="action" value="fast_deploy" />
      <input type="hidden" name="dry_run" value="<?php echo htmlspecialchars($dryRun, ENT_QUOTES); ?>" />
      <button class="wf-admin-nav-button" style="width:100%">Fast Deploy (Files)</button>
      <div style="font-size:12px; color:#6b7280; margin-top:6px;">Build + SFTP mirror (Skips DB). Honors Dry Run.</div>
    </form>

    <form method="post" onsubmit="return submitAction(this)" data-stream="1">
      <input type="hidden" name="action" value="full_deploy" />
      <input type="hidden" name="dry_run" value="<?php echo htmlspecialchars($dryRun, ENT_QUOTES); ?>" />
      <button class="wf-admin-nav-button" style="width:100%">Full Deploy (DB + Files)</button>
      <div style="font-size:12px; color:#6b7280; margin-top:6px;">Dump local DB, restore to live (API or direct), then files. Honors Dry Run.</div>
    </form>

    <form method="post" onsubmit="return submitAction(this)">
      <input type="hidden" name="action" value="verify" />
      <input type="hidden" name="dry_run" value="0" />
      <button class="wf-admin-nav-button" style="width:100%">Verify Only (HTTP Checks)</button>
      <div style="font-size:12px; color:#6b7280; margin-top:6px;">Checks home, manifest, and logo endpoints. No deploy.</div>
    </form>
  </div>

  <div style="padding: 16px 20px; border-top:1px solid #e5e7eb;">
    <h2 style="margin:0 0 8px; font-size:1rem; font-weight:800;">Result</h2>
    <pre style="white-space:pre-wrap; background:#0b1021; color:#e5e7eb; padding:12px; border-radius:8px; min-height:220px; overflow:auto;">
<?php if ($action && $response) {
    echo htmlspecialchars((string)$response['output'], ENT_QUOTES);
} else {
    echo "Ready. Choose an action above.";
} ?>
    </pre>
  </div>
</div>

<script>
const resultPre = document.querySelector('pre');
function appendLine(text){
  if (!resultPre) return;
  resultPre.textContent += (resultPre.textContent ? "\n" : "") + text;
  resultPre.scrollTop = resultPre.scrollHeight;
}
function submitAction(form){
  const dryToggle = document.getElementById('dryRunToggle');
  const dryInput = form.querySelector('input[name="dry_run"]');
  if (dryInput && dryToggle) dryInput.value = dryToggle.checked ? '1' : '0';
  const action = (form.querySelector('input[name="action"]')||{}).value;
  const wantsStream = form.hasAttribute('data-stream');
  if (wantsStream && (action === 'fast_deploy' || action === 'full_deploy')){
    // Use SSE streaming
    try {
      resultPre.textContent = '';
      const params = new URLSearchParams();
      params.set('action', action);
      params.set('dry_run', dryInput ? dryInput.value : '0');
      const es = new EventSource(`/sections/tools/deploy_stream.php?${params.toString()}`);
      es.addEventListener('message', (ev)=>{ appendLine(ev.data); });
      es.addEventListener('done', ()=>{ appendLine('--- Completed ---'); es.close(); });
      es.addEventListener('error', ()=>{ appendLine('--- Stream error ---'); es.close(); });
    } catch(e){ appendLine('Streaming not available; falling back.'); return true; }
    return false;
  }
  // Non-streaming (backup/verify)
  return true;
}
</script>

<?php if (!$EMBED && file_exists($footerPath)) require $footerPath; ?>

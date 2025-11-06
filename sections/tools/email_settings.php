<?php
// Email Settings standalone page (embeddable in modal)
// Detect modal context
$isModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

if (!$isModal) {
    // Shared header for full-page access
    $page = 'admin';
    include dirname(__DIR__, 2) . '/partials/header.php';
}
?>
<?php if ($isModal): ?>
  <?php include dirname(__DIR__, 2) . '/partials/modal_header.php'; ?>
  
<?php else: ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Email Settings</title>
    <style>
      body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#fff; color:#111; }
      .wf-wrap { max-width: 1200px; margin: 0 auto; padding: 16px; }
      .wf-header { display:flex; align-items:center; justify-content:space-between; margin-bottom: 12px; }
      .wf-title { font-size: 20px; font-weight: 700; }
      .wf-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
      .wf-grid-md-2 { grid-template-columns: 1fr 1fr; }
      .wf-grid-xl-3 { grid-template-columns: 1fr 1fr 1fr; }
      @media (min-width: 1200px) { .wf-grid-xl-3 { grid-template-columns: 1fr 1fr 1fr; } }
      @media (max-width: 640px) { .wf-grid { grid-template-columns: 1fr; } }
      .wf-row { display:flex; flex-direction:column; gap:6px; }
      label { font-size: 12px; color:#374151; }
      input[type="text"], input[type="email"], input[type="number"], input[type="password"], select { padding:8px; border:1px solid #d1d5db; border-radius:6px; }
      .wf-hint { font-size: 12px; color:#6b7280; }
      .wf-section { border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:12px; background:#fafafa; }
      .wf-actions-right { display:flex; gap:8px; justify-content:flex-end; margin-top: 8px; }
      .btn { padding:8px 12px; border-radius:6px; border:1px solid #d1d5db; background:#fff; cursor:pointer; }
      .btn-primary { background:#2563eb; color:#fff; border-color:#1d4ed8; }
      .status { font-size: 12px; margin-left:auto; }
      .success { color:#065f46; }
      .error { color:#b91c1c; }
      .wf-inline { display:flex; align-items:center; gap:8px; }
      .wf-log-console { font-size: 12px; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; }
    </style>
  </head>
  <body>
<?php endif; ?>
  <div class="wf-wrap" id="emailSettingsRoot">
    <div class="wf-header">
      <div class="wf-title">✉️ Email Settings</div>
      <div id="status" class="status"></div>
    </div>

    <div id="fatalErrorBox" class="wf-section" hidden>
      <div id="fatalErrorMessage" class="error"></div>
      <div class="wf-hint" id="fatalErrorHelp"></div>
    </div>

    <div class="wf-section" id="statusSection">
      <div class="wf-grid wf-grid-md-2 wf-grid-xl-3">
        <div class="wf-row">
          <label>Provider</label>
          <div id="statusProvider" class="wf-hint">Loading…</div>
        </div>
        <div class="wf-row">
          <label>Effective From</label>
          <div id="statusFrom" class="wf-hint">Loading…</div>
        </div>
        <div class="wf-row">
          <label>Secrets</label>
          <div id="statusSecrets" class="wf-hint">Loading…</div>
        </div>
        <div class="wf-row">
          <label>Business Information</label>
          <div class="wf-inline">
            <div>
              <div id="businessNameDisplay" class="wf-hint"></div>
              <div id="businessEmailDisplay" class="wf-hint"></div>
              <div id="supportEmailDisplay" class="wf-hint"></div>
              <div id="businessWebsiteDisplay" class="wf-hint"></div>
              <div id="businessDomainDisplay" class="wf-hint"></div>
            </div>
            <button class="btn" id="applyBusinessToEmailBtn" title="Set From/Admin to Business Info">Use Business Email</button>
          </div>
        </div>
      </div>
    </div>

    <div class="wf-section">
      <div class="wf-grid wf-grid-md-2 wf-grid-xl-3">
        <div class="wf-row">
          <label for="fromEmail">From Email</label>
          <input id="fromEmail" type="email" placeholder="noreply@example.com" readonly />
          <div class="wf-hint">Managed by Business Information. Update there to change.</div>
        </div>
        <div class="wf-row">
          <label for="fromName">From Name</label>
          <input id="fromName" type="text" placeholder="Your Business Name" readonly />
          <div class="wf-hint">Managed by Business Information. Update there to change.</div>
        </div>
        <div class="wf-row">
          <label for="adminEmail">Admin Email</label>
          <input id="adminEmail" type="email" placeholder="admin@example.com" readonly />
          <div class="wf-hint">Managed by Business Information. Update there to change.</div>
        </div>
        <div class="wf-row">
          <label for="bccEmail">BCC Email (optional)</label>
          <input id="bccEmail" type="email" placeholder="audit@example.com" />
        </div>
        <div class="wf-row">
          <label for="replyTo">Reply-To Email (optional)</label>
          <input id="replyTo" type="email" placeholder="support@example.com" />
        </div>
      </div>
    </div>

    <div class="wf-section">
      <div class="wf-row wf-inline mb-2">
        <input id="smtpEnabled" type="checkbox" />
        <label for="smtpEnabled">Enable SMTP</label>
      </div>
      <div class="wf-grid wf-grid-md-2 wf-grid-xl-3">
        <div class="wf-row">
          <label for="smtpHost">SMTP Host</label>
          <input id="smtpHost" type="text" placeholder="smtp.gmail.com" autocomplete="off" />
        </div>
        <div class="wf-row">
          <label for="smtpPort">SMTP Port</label>
          <input id="smtpPort" type="number" placeholder="587" autocomplete="off" />
        </div>
        <div class="wf-row">
          <label for="smtpUsername">SMTP Username</label>
          <input id="smtpUsername" type="text" placeholder="user@example.com" autocomplete="username" />
          <div class="wf-hint">Stored in DB for reference; secret store is the authority for sending.</div>
        </div>
        <div class="wf-row">
          <label for="smtpPassword">SMTP Password</label>
          <input id="smtpPassword" type="password" placeholder="••••••••" autocomplete="new-password" />
          <div class="wf-hint">Saved to secret store only.</div>
        </div>
        <div class="wf-row">
          <label for="smtpEncryption">Encryption</label>
          <select id="smtpEncryption">
            <option value="">(none)</option>
            <option value="tls">TLS</option>
            <option value="ssl">SSL</option>
          </select>
        </div>
        <div class="wf-row wf-inline">
          <input id="smtpAuth" type="checkbox" />
          <label for="smtpAuth">Require SMTP Authentication</label>
        </div>
        <div class="wf-row">
          <label for="smtpTimeout">SMTP Timeout (seconds)</label>
          <input id="smtpTimeout" type="number" placeholder="30" autocomplete="off" />
        </div>
        <div class="wf-row wf-inline">
          <input id="smtpDebug" type="checkbox" />
          <label for="smtpDebug">Enable SMTP Debug Logging</label>
        </div>
      </div>
    </div>

    <div class="wf-section">
      <div class="wf-grid wf-grid-md-2 wf-grid-xl-3">
        <div class="wf-row">
          <label for="returnPath">Return-Path (bounce address)</label>
          <input id="returnPath" type="email" placeholder="bounce@yourdomain.com" />
          <div class="wf-hint">Optional envelope sender for bounces.</div>
        </div>
        <div class="wf-row">
          <label for="dkimDomain">DKIM Domain</label>
          <input id="dkimDomain" type="text" placeholder="yourdomain.com" />
        </div>
        <div class="wf-row">
          <label for="dkimSelector">DKIM Selector</label>
          <input id="dkimSelector" type="text" placeholder="default" />
        </div>
        <div class="wf-row">
          <label for="dkimIdentity">DKIM Identity (optional)</label>
          <input id="dkimIdentity" type="email" placeholder="noreply@yourdomain.com" />
        </div>
        <div class="wf-row">
          <label for="dkimPrivateKey">DKIM Private Key (PEM)</label>
          <textarea id="dkimPrivateKey" rows="4" placeholder="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"></textarea>
          <div class="wf-hint">Stored securely in the secret store. Leave blank to keep existing.</div>
        </div>
        <div class="wf-row wf-inline">
          <input id="smtpAllowSelfSigned" type="checkbox" />
          <label for="smtpAllowSelfSigned">Allow self-signed TLS certificates</label>
        </div>
        <div class="wf-row">
          <label>DKIM DNS Helper</label>
          <div class="wf-grid wf-grid-md-2">
            <div class="wf-row">
              <label for="dkimDnsName">Record Name</label>
              <div class="wf-inline">
                <input id="dkimDnsName" type="text" placeholder="selector._domainkey.example.com" readonly />
                <button type="button" class="btn" id="copyDkimNameBtn">Copy</button>
              </div>
            </div>
            <div class="wf-row">
              <label for="dkimDnsValue">Record Value (public key)</label>
              <textarea id="dkimDnsValue" rows="3" placeholder="v=DKIM1; k=rsa; p=..." readonly></textarea>
            </div>
            <div class="wf-row">
              <div id="dkimStatusHint" class="wf-hint"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="wf-section">
      <div class="wf-grid wf-grid-md-2">
        <div class="wf-row">
          <label for="testEmail">Send Test Email To</label>
          <div class="wf-inline">
            <input id="testEmail" type="email" placeholder="you@example.com" class="flex-1" />
            <button class="btn" id="sendTestBtn">Send Test</button>
            <button class="btn" id="liveTestBtn" title="Stream SMTP logs live">Live Test</button>
            <button class="btn" id="preflightBtn" title="Connect to SMTP without sending">Preflight</button>
            <button class="btn" id="stopLiveBtn" title="Stop live stream" hidden>Stop</button>
          </div>
        </div>
        <div class="wf-row" id="liveLogRow" hidden>
          <label>Live Logs</label>
          <pre id="liveLog" class="wf-log-console">Waiting…</pre>
        </div>
      </div>
    </div>

    <div class="wf-actions-right">
      <button class="btn" id="closeBtn">Close</button>
      <button class="btn btn-primary" id="saveBtn">Save Settings</button>
    </div>
  </div>

  <script>
    (function(){
      const $ = (id) => document.getElementById(id);
      const statusEl = $('status');
      let __es = null;

      function setStatus(msg, ok){
        if(!statusEl) return; statusEl.textContent = msg||''; statusEl.className = 'status ' + (ok? 'success':'error');
      }

      function updateStatusDisplay(cfg){
        try {
          const prov = (cfg && cfg.effectiveProvider) || ((cfg && cfg.smtpEnabled) ? 'smtp' : 'mail');
          const host = (cfg && cfg.smtpHost) || '';
          const port = (cfg && cfg.smtpPort) || '';
          const enc = (cfg && cfg.smtpEncryption) || '';
          const fromN = (cfg && (cfg.fromName || cfg.effectiveFromName)) || '';
          const fromE = (cfg && (cfg.fromEmail || cfg.effectiveFromEmail)) || '';
          const uPresent = !!(cfg && cfg.secretUsernamePresent);
          const pPresent = !!(cfg && cfg.secretPasswordPresent);
          const bizName = (cfg && cfg.businessName) || '';
          const bizEmail = (cfg && cfg.businessEmail) || '';
          const supEmail = (cfg && cfg.supportEmail) || '';
          const bizWeb = (cfg && (cfg.businessWebsite || cfg.siteUrl)) || '';
          const bizDomain = (cfg && cfg.businessDomain) || '';

          $('statusProvider').textContent = prov === 'smtp'
            ? `SMTP ${host || '(host?)'}:${port || '?'} ${enc ? enc.toUpperCase() : ''}`.trim()
            : 'PHP mail()';
          $('statusFrom').textContent = `${fromN || '(name?)'} <${fromE || 'email?'}>`;
          const dkimPresent = !!(cfg && cfg.dkimPrivateKeyPresent);
          $('statusSecrets').textContent = `username: ${uPresent ? 'present' : 'missing'}, password: ${pPresent ? 'present' : 'missing'}, dkim: ${dkimPresent ? 'present' : 'missing'}`;
          $('businessNameDisplay').textContent = bizName ? `Name: ${bizName}` : '';
          $('businessEmailDisplay').textContent = bizEmail ? `Email: ${bizEmail}` : '';
          $('supportEmailDisplay').textContent = supEmail ? `Support: ${supEmail}` : '';
          if (bizWeb) {
            try { $('businessWebsiteDisplay').innerHTML = `Website: <a href="${bizWeb}" target="_blank" rel="noopener">${bizWeb}</a>`; } catch(_) { $('businessWebsiteDisplay').textContent = `Website: ${bizWeb}`; }
          } else {
            $('businessWebsiteDisplay').textContent = '';
          }
          $('businessDomainDisplay').textContent = bizDomain ? `Domain: ${bizDomain}` : '';
        } catch (_) {}
      }

      async function apiRequest(method, url, data=null, options={}){
        const WF = (typeof window !== 'undefined') ? (window.WhimsicalFrog && window.WhimsicalFrog.api) : null;
        const A = WF || ((typeof window !== 'undefined') ? (window.ApiClient || null) : null);
        const m = String(method||'GET').toUpperCase();
        const isForm = (typeof FormData !== 'undefined') && (data instanceof FormData);
        if (A && typeof A.request === 'function') {
          if (isForm) {
            // Let client handle multipart boundary; do NOT set Content-Type
            return A.request(url, { method: m, body: data, headers: {}, ...(options||{}) });
          }
          if (m === 'GET' && A.get) return A.get(url, (options && options.params) || {});
          if (m === 'POST' && A.post) return A.post(url, data||{}, options||{});
          if (m === 'PUT' && A.put) return A.put(url, data||{}, options||{});
          if (m === 'DELETE' && A.delete) return A.delete(url, options||{});
          return A.request(url, { method: m, ...(options||{}) });
        }
        const headers = isForm ? { 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) }
                               : { 'Content-Type': 'application/json', 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) };
        const cfg = { credentials:'include', method:m, headers, ...(options||{}) };
        if (!isForm && data !== null && typeof cfg.body === 'undefined') cfg.body = JSON.stringify(data);
        if (isForm && typeof cfg.body === 'undefined') cfg.body = data;
        const res = await fetch(url, cfg);
        return res.json().catch(()=>({}));
      }
      const apiGet = (url, params) => apiRequest('GET', url, null, { params });

      function showFatalError(msg){
        try {
          const box = $('fatalErrorBox');
          const m = $('fatalErrorMessage');
          const help = $('fatalErrorHelp');
          if (box && m && help) {
            m.textContent = msg || 'Failed to load email settings.';
            help.innerHTML = '<div>Common causes:</div>'+
              '<ul>'+
              '<li>Database is not running or unreachable</li>'+
              '<li>Required Business Information is missing (business_name, business_email)</li>'+
              '<li>Local dev origin mismatch or cookies disabled preventing auth/session</li>'+
              '</ul>'+
              '<div>Try: restart MySQL, verify .env DB settings, open Business Information and save name/email, then reload this modal.</div>';
            box.hidden = false;
          }
        } catch(_) {}
      }

      async function loadConfig(){
        try {
          setStatus('Loading…');
          const j = await apiGet('/api/get_email_config.php');
          if(!j || !j.success) throw new Error(j?.error || 'Failed to load');
          const c = j.config || {};
          $('fromEmail').value = c.fromEmail || '';
          $('fromName').value = c.fromName || '';
          $('adminEmail').value = c.adminEmail || '';
          $('bccEmail').value = c.bccEmail || '';
          $('replyTo').value = c.replyTo || '';
          // Advanced headers/DKIM
          try { $('returnPath').value = c.returnPath || ''; } catch(_) {}
          try { $('dkimDomain').value = c.dkimDomain || ''; } catch(_) {}
          try { $('dkimSelector').value = c.dkimSelector || ''; } catch(_) {}
          try { $('dkimIdentity').value = c.dkimIdentity || ''; } catch(_) {}
          $('smtpEnabled').checked = !!c.smtpEnabled;
          $('smtpHost').value = c.smtpHost || '';
          $('smtpPort').value = c.smtpPort || '';
          $('smtpUsername').value = c.smtpUsername || '';
          $('smtpPassword').value = '';
          $('smtpEncryption').value = c.smtpEncryption || '';
          $('smtpAuth').checked = !!c.smtpAuth;
          $('smtpTimeout').value = c.smtpTimeout || '';
          $('smtpDebug').checked = !!c.smtpDebug;
          try { $('smtpAllowSelfSigned').checked = !!c.smtpAllowSelfSigned; } catch(_) {}
          // Prefill test email from business info if empty
          try {
            const t = $('testEmail');
            if (t && !t.value) {
              t.value = c.adminEmail || c.supportEmail || c.fromEmail || c.businessEmail || '';
            }
          } catch (_) {}
          window.__emailCfg = c;
          updateStatusDisplay(c);
          updateDkimHelper();
          setStatus('Loaded', true);
        } catch(e){ setStatus(e.message || 'Load failed'); showFatalError(e.message || 'Load failed'); }
      }

      function updateDkimHelper(){
        try {
          const dom = document.getElementById('dkimDomain')?.value.trim() || '';
          const selRaw = document.getElementById('dkimSelector')?.value.trim() || '';
          const sel = selRaw || 'default';
          const name = (dom && sel) ? (sel + '._domainkey.' + dom) : '';
          const nameEl = document.getElementById('dkimDnsName');
          const valEl = document.getElementById('dkimDnsValue');
          if (nameEl) nameEl.value = name;
          if (valEl) valEl.value = name ? 'v=DKIM1; k=rsa; p=...' : '';
          const hint = document.getElementById('dkimStatusHint');
          const present = !!(window.__emailCfg && window.__emailCfg.dkimPrivateKeyPresent);
          if (hint) hint.textContent = present ? 'DKIM key present in secrets. Publish public key as p= in DNS.' : 'No DKIM private key found. Save one to enable DKIM.';
        } catch(_) {}
      }

      async function saveConfig(){
        try{
          setStatus('Saving…');
          const form = new FormData();
          form.append('action','save');
          // fromEmail/fromName/adminEmail are managed by Business Information and not saved here
          form.append('bccEmail', $('bccEmail').value.trim());
          form.append('replyTo', $('replyTo').value.trim());
          // Advanced headers/DKIM
          try { form.append('returnPath', $('returnPath').value.trim()); } catch(_) {}
          try { form.append('dkimDomain', $('dkimDomain').value.trim()); } catch(_) {}
          try { form.append('dkimSelector', $('dkimSelector').value.trim()); } catch(_) {}
          try { form.append('dkimIdentity', $('dkimIdentity').value.trim()); } catch(_) {}
          try { const dk = $('dkimPrivateKey'); if (dk && dk.value.trim() !== '') form.append('dkimPrivateKey', dk.value.trim()); } catch(_) {}
          try { if ($('smtpAllowSelfSigned').checked) form.append('smtpAllowSelfSigned', '1'); } catch(_) {}
          if ($('smtpEnabled').checked) form.append('smtpEnabled','1');
          form.append('smtpHost', $('smtpHost').value.trim());
          form.append('smtpPort', $('smtpPort').value.trim());
          form.append('smtpUsername', $('smtpUsername').value.trim());
          if ($('smtpPassword').value.trim() !== '') form.append('smtpPassword', $('smtpPassword').value.trim());
          form.append('smtpEncryption', $('smtpEncryption').value);
          if ($('smtpAuth').checked) form.append('smtpAuth','1');
          form.append('smtpTimeout', $('smtpTimeout').value.trim());
          if ($('smtpDebug').checked) form.append('smtpDebug','1');

          const j = await apiRequest('POST','/api/save_email_config.php', form);
          if(!j || !j.success) throw new Error(j?.error || 'Save failed');
          setStatus('Saved', true);
        } catch(e){ setStatus(e.message || 'Save failed'); }
      }

      async function sendTest(){
        try{
          const to = $('testEmail').value.trim();
          if(!to){ setStatus('Enter test email'); return; }
          setStatus('Sending…');
          const form = new FormData();
          form.append('action','test');
          form.append('testEmail', to);
          const j = await apiRequest('POST','/api/save_email_config.php', form);
          if(!j || !j.success) throw new Error(j?.error || 'Test failed');
          setStatus('Test email sent!', true);
        } catch(e){ setStatus(e.message || 'Test failed'); }
      }

      function appendLog(line){
        const pre = $('liveLog'); if (!pre) return;
        if (pre.textContent === 'Waiting…') pre.textContent = '';
        pre.textContent += (typeof line === 'string' ? line : JSON.stringify(line)) + "\n";
        pre.scrollTop = pre.scrollHeight;
      }

      function stopLive(){
        if (__es) { try { __es.close(); } catch(_) {} __es = null; }
        try { $('stopLiveBtn').hidden = true; } catch(_) {}
      }

      function startLive(){
        const to = $('testEmail').value.trim();
        if(!to){ setStatus('Enter test email'); return; }
        try { $('liveLogRow').hidden = false; $('liveLog').textContent = 'Waiting…'; } catch(_) {}
        stopLive();
        const url = '/api/email_test_stream.php?to=' + encodeURIComponent(to);
        __es = new EventSource(url, { withCredentials: true });
        try { $('stopLiveBtn').hidden = false; } catch(_) {}
        setStatus('Streaming…');
        __es.addEventListener('start', (e) => { try { appendLog('[start] ' + (e.data||'')); } catch(_) {} });
        __es.addEventListener('config', (e) => {
          try {
            const cfg = JSON.parse(e.data||'{}');
            appendLog(`[config] provider=${cfg.provider} from=${cfg.fromName} <${cfg.fromEmail}>`);
            if (cfg.smtp) appendLog(`[smtp] host=${cfg.smtp.host} port=${cfg.smtp.port} enc=${cfg.smtp.encryption} auth=${cfg.smtp.auth} user=${cfg.smtp.username_present?'present':'missing'} pass=${cfg.smtp.password_present?'present':'missing'}`);
          } catch(_) {}
        });
        __es.addEventListener('smtp', (e) => { try { const d = JSON.parse(e.data||'{}'); appendLog(`[smtp:${d.level}] ${d.msg}`); } catch(_) {} });
        __es.addEventListener('log', (e) => { try { const d = JSON.parse(e.data||'{}'); appendLog(`[log ${d.t}] ${d.msg}`); } catch(_) {} });
        __es.addEventListener('progress', (e) => { appendLog('[progress] ' + (e.data||'')); });
        __es.addEventListener('error', (e) => { appendLog('[error] ' + (e.data||'')); });
        __es.addEventListener('done', (e) => {
          try {
            const d = JSON.parse(e.data||'{}');
            appendLog(`[done] ${d.success ? 'SUCCESS' : 'FAIL'} - ${d.message || ''}`);
            setStatus(d.success ? 'Live test succeeded' : (d.message || 'Live test failed'), !!d.success);
          } catch(_) {}
          stopLive();
        });
        __es.onerror = () => { appendLog('[event-source] connection error'); stopLive(); };
      }

      function startPreflight(){
        try { $('liveLogRow').hidden = false; $('liveLog').textContent = 'Waiting…'; } catch(_) {}
        stopLive();
        const url = '/api/email_test_stream.php?mode=preflight&_=' + Date.now();
        __es = new EventSource(url, { withCredentials: true });
        try { $('stopLiveBtn').hidden = false; } catch(_) {}
        setStatus('Preflight streaming…');
        __es.addEventListener('start', (e) => { try { appendLog('[start] ' + (e.data||'')); } catch(_) {} });
        __es.addEventListener('smtp', (e) => { try { const d = JSON.parse(e.data||'{}'); appendLog(`[smtp:${d.level}] ${d.msg}`); } catch(_) {} });
        __es.addEventListener('log', (e) => { try { const d = JSON.parse(e.data||'{}'); appendLog(`[log ${d.t}] ${d.msg}`); } catch(_) {} });
        __es.addEventListener('progress', (e) => { appendLog('[progress] ' + (e.data||'')); });
        __es.addEventListener('error', (e) => { appendLog('[error] ' + (e.data||'')); });
        __es.addEventListener('done', (e) => {
          try {
            const d = JSON.parse(e.data||'{}');
            appendLog(`[done] ${d.success ? 'SUCCESS' : 'FAIL'} - ${d.message || ''}`);
            setStatus(d.success ? 'SMTP preflight OK' : (d.message || 'SMTP preflight failed'), !!d.success);
          } catch(_) {}
          stopLive();
        });
        __es.onerror = () => { appendLog('[event-source] connection error'); stopLive(); };
      }

      document.addEventListener('DOMContentLoaded', function(){
        $('saveBtn')?.addEventListener('click', saveConfig);
        $('sendTestBtn')?.addEventListener('click', sendTest);
        $('liveTestBtn')?.addEventListener('click', startLive);
        $('preflightBtn')?.addEventListener('click', startPreflight);
        $('stopLiveBtn')?.addEventListener('click', stopLive);
        $('closeBtn')?.addEventListener('click', function(){
          try {
            window.parent?.document?.querySelector('[data-action="close-admin-modal"]')?.click();
          } catch(_){ try { window.close?.(); } catch(__){} }
        });
        $('applyBusinessToEmailBtn')?.addEventListener('click', () => {
          try {
            const bn = $('businessNameDisplay').textContent.replace(/^Name:\s*/,'').trim();
            const be = $('businessEmailDisplay').textContent.replace(/^Email:\s*/,'').trim();
            const se = $('supportEmailDisplay').textContent.replace(/^Support:\s*/,'').trim();
            if (be) $('fromEmail').value = be;
            if (bn) $('fromName').value = bn;
            if (be) $('adminEmail').value = be;
            if (se) $('replyTo').value = se;
            setStatus('Applied Business Info to From/Admin', true);
          } catch(_) {}
        });
        document.getElementById('dkimDomain')?.addEventListener('input', updateDkimHelper);
        document.getElementById('dkimSelector')?.addEventListener('input', updateDkimHelper);
        document.getElementById('copyDkimNameBtn')?.addEventListener('click', function(){
          try { const v = document.getElementById('dkimDnsName')?.value || ''; if (v) { navigator.clipboard.writeText(v); setStatus('Copied DKIM name', true); } } catch(_){}
        });
        loadConfig();
      });
    })();
  </script>
</body>
</html>
<?php if (!$isModal) { include dirname(__DIR__, 2) . '/partials/footer.php'; } ?>

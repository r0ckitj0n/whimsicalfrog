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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Email Settings</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#fff; color:#111; }
    .wrap { max-width: 900px; margin: 0 auto; padding: 16px; }
    .header { display:flex; align-items:center; justify-content:space-between; margin-bottom: 12px; }
    .title { font-size: 20px; font-weight: 700; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .row { display:flex; flex-direction:column; gap:6px; }
    label { font-size: 12px; color:#374151; }
    input[type="text"], input[type="email"], input[type="number"], input[type="password"], select { padding:8px; border:1px solid #d1d5db; border-radius:6px; }
    .hint { font-size: 12px; color:#6b7280; }
    .section { border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:12px; background:#fafafa; }
    .actions { display:flex; gap:8px; justify-content:flex-end; margin-top: 8px; }
    .btn { padding:8px 12px; border-radius:6px; border:1px solid #d1d5db; background:#fff; cursor:pointer; }
    .btn-primary { background:#2563eb; color:#fff; border-color:#1d4ed8; }
    .status { font-size: 12px; margin-left:auto; }
    .success { color:#065f46; }
    .error { color:#b91c1c; }
    .inline { display:flex; align-items:center; gap:8px; }
  </style>
</head>
<body>
  <div class="wrap" id="emailSettingsRoot">
    <div class="header">
      <div class="title">✉️ Email Settings</div>
      <div id="status" class="status"></div>
    </div>

    <div class="section" id="statusSection">
      <div class="grid">
        <div class="row">
          <label>Provider</label>
          <div id="statusProvider" class="hint">Loading…</div>
        </div>
        <div class="row">
          <label>Effective From</label>
          <div id="statusFrom" class="hint">Loading…</div>
        </div>
        <div class="row">
          <label>Secrets</label>
          <div id="statusSecrets" class="hint">Loading…</div>
        </div>
        <div class="row">
          <label>Business Information</label>
          <div class="inline">
            <div>
              <div id="businessNameDisplay" class="hint"></div>
              <div id="businessEmailDisplay" class="hint"></div>
            </div>
            <button class="btn" id="applyBusinessToEmailBtn" title="Set From/Admin to Business Info">Use Business Email</button>
          </div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="grid">
        <div class="row">
          <label for="fromEmail">From Email</label>
          <input id="fromEmail" type="email" placeholder="noreply@example.com" />
          <div class="hint">Appears as the sender address.</div>
        </div>
        <div class="row">
          <label for="fromName">From Name</label>
          <input id="fromName" type="text" placeholder="Your Business Name" />
        </div>
        <div class="row">
          <label for="adminEmail">Admin Email</label>
          <input id="adminEmail" type="email" placeholder="you@example.com" />
          <div class="hint">Used for admin notifications.</div>
        </div>
        <div class="row">
          <label for="bccEmail">BCC Email (optional)</label>
          <input id="bccEmail" type="email" placeholder="audit@example.com" />
        </div>
        <div class="row">
          <label for="replyTo">Reply-To Email (optional)</label>
          <input id="replyTo" type="email" placeholder="support@example.com" />
        </div>
      </div>
    </div>

    <div class="section">
      <div class="row inline mb-2">
        <input id="smtpEnabled" type="checkbox" />
        <label for="smtpEnabled">Enable SMTP</label>
      </div>
      <div class="grid">
        <div class="row">
          <label for="smtpHost">SMTP Host</label>
          <input id="smtpHost" type="text" placeholder="smtp.gmail.com" />
        </div>
        <div class="row">
          <label for="smtpPort">SMTP Port</label>
          <input id="smtpPort" type="number" placeholder="587" />
        </div>
        <div class="row">
          <label for="smtpUsername">SMTP Username</label>
          <input id="smtpUsername" type="text" placeholder="user@example.com" />
          <div class="hint">Stored in DB for reference; secret store is the authority for sending.</div>
        </div>
        <div class="row">
          <label for="smtpPassword">SMTP Password</label>
          <input id="smtpPassword" type="password" placeholder="••••••••" />
          <div class="hint">Saved to secret store only.</div>
        </div>
        <div class="row">
          <label for="smtpEncryption">Encryption</label>
          <select id="smtpEncryption">
            <option value="">(none)</option>
            <option value="tls">TLS</option>
            <option value="ssl">SSL</option>
          </select>
        </div>
        <div class="row inline">
          <input id="smtpAuth" type="checkbox" />
          <label for="smtpAuth">Require SMTP Authentication</label>
        </div>
        <div class="row">
          <label for="smtpTimeout">SMTP Timeout (seconds)</label>
          <input id="smtpTimeout" type="number" placeholder="30" />
        </div>
        <div class="row inline">
          <input id="smtpDebug" type="checkbox" />
          <label for="smtpDebug">Enable SMTP Debug Logging</label>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="row">
        <label for="testEmail">Send Test Email To</label>
        <div class="inline">
          <input id="testEmail" type="email" placeholder="you@example.com" class="flex-1" />
          <button class="btn" id="sendTestBtn">Send Test</button>
          <button class="btn" id="liveTestBtn" title="Stream SMTP logs live">Live Test</button>
          <button class="btn" id="stopLiveBtn" title="Stop live stream" style="display:none">Stop</button>
        </div>
      </div>
      <div class="row" id="liveLogRow" style="display:none">
        <label>Live Logs</label>
        <pre id="liveLog" style="background:#0b1020;color:#d1e7ff;border-radius:6px;padding:10px;min-height:140px;max-height:300px;overflow:auto;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;font-size:12px;">Waiting…</pre>
      </div>
    </div>

    <div class="actions">
      <button class="btn" id="closeBtn" onclick="try{window.parent.document.querySelector('[data-action=\"close-admin-modal\"]')?.click()}catch(_){window.close?.()}">Close</button>
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

          $('statusProvider').textContent = prov === 'smtp'
            ? `SMTP ${host || '(host?)'}:${port || '?'} ${enc ? enc.toUpperCase() : ''}`.trim()
            : 'PHP mail()';
          $('statusFrom').textContent = `${fromN || '(name?)'} <${fromE || 'email?'}>`;
          $('statusSecrets').textContent = `username: ${uPresent ? 'present' : 'missing'}, password: ${pPresent ? 'present' : 'missing'}`;
          $('businessNameDisplay').textContent = bizName ? `Name: ${bizName}` : '';
          $('businessEmailDisplay').textContent = bizEmail ? `Email: ${bizEmail}` : '';
        } catch (_) {}
      }

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
        const isForm = (typeof FormData !== 'undefined') && (data instanceof FormData);
        const headers = isForm ? { 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) }
                               : { 'Content-Type': 'application/json', 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) };
        const cfg = { credentials:'include', method:m, headers, ...(options||{}) };
        if (!isForm && data !== null && typeof cfg.body === 'undefined') cfg.body = JSON.stringify(data);
        if (isForm && typeof cfg.body === 'undefined') cfg.body = data;
        const res = await fetch(url, cfg);
        return res.json().catch(()=>({}));
      }
      const apiGet = (url, params) => apiRequest('GET', url, null, { params });

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
          $('smtpEnabled').checked = !!c.smtpEnabled;
          $('smtpHost').value = c.smtpHost || '';
          $('smtpPort').value = c.smtpPort || '';
          $('smtpUsername').value = c.smtpUsername || '';
          $('smtpPassword').value = '';
          $('smtpEncryption').value = c.smtpEncryption || '';
          $('smtpAuth').checked = !!c.smtpAuth;
          $('smtpTimeout').value = c.smtpTimeout || '';
          $('smtpDebug').checked = !!c.smtpDebug;
          updateStatusDisplay(c);
          setStatus('Loaded', true);
        } catch(e){ setStatus(e.message || 'Load failed'); }
      }

      async function saveConfig(){
        try{
          setStatus('Saving…');
          const form = new FormData();
          form.append('action','save');
          form.append('fromEmail', $('fromEmail').value.trim());
          form.append('fromName', $('fromName').value.trim());
          form.append('adminEmail', $('adminEmail').value.trim());
          form.append('bccEmail', $('bccEmail').value.trim());
          form.append('replyTo', $('replyTo').value.trim());
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
        $('stopLiveBtn').style.display = 'none';
      }

      function startLive(){
        const to = $('testEmail').value.trim();
        if(!to){ setStatus('Enter test email'); return; }
        try { $('liveLogRow').style.display = ''; $('liveLog').textContent = 'Waiting…'; } catch(_) {}
        stopLive();
        const url = '/api/email_test_stream.php?to=' + encodeURIComponent(to);
        __es = new EventSource(url, { withCredentials: true });
        $('stopLiveBtn').style.display = '';
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

      document.addEventListener('DOMContentLoaded', function(){
        $('saveBtn')?.addEventListener('click', saveConfig);
        $('sendTestBtn')?.addEventListener('click', sendTest);
        $('liveTestBtn')?.addEventListener('click', startLive);
        $('stopLiveBtn')?.addEventListener('click', stopLive);
        $('applyBusinessToEmailBtn')?.addEventListener('click', () => {
          try {
            const bn = $('businessNameDisplay').textContent.replace(/^Name:\s*/,'').trim();
            const be = $('businessEmailDisplay').textContent.replace(/^Email:\s*/,'').trim();
            if (be) $('fromEmail').value = be;
            if (bn) $('fromName').value = bn;
            if (be) $('adminEmail').value = be;
            setStatus('Applied Business Info to From/Admin', true);
          } catch(_) {}
        });
        loadConfig();
      });
    })();
  </script>
</body>
</html>
<?php if (!$isModal) { include dirname(__DIR__, 2) . '/partials/footer.php'; } ?>

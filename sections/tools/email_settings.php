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
    .btn-brand { background:#2563eb; color:#fff; border-color:#1d4ed8; }
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
      </div>
    </div>

    <div class="section">
      <div class="row">
        <label for="testEmail">Send Test Email To</label>
        <div class="inline">
          <input id="testEmail" type="email" placeholder="you@example.com" class="flex-1" />
          <button class="btn" id="sendTestBtn">Send Test</button>
        </div>
      </div>
    </div>

    <div class="actions">
      <button class="btn" id="closeBtn" onclick="try{window.parent.document.querySelector('[data-action=\"close-admin-modal\"]')?.click()}catch(_){window.close?.()}">Close</button>
      <button class="btn btn-brand" id="saveBtn">Save Settings</button>
    </div>
  </div>

  <script>
    (function(){
      const $ = (id) => document.getElementById(id);
      const statusEl = $('status');

      function setStatus(msg, ok){
        if(!statusEl) return; statusEl.textContent = msg||''; statusEl.className = 'status ' + (ok? 'success':'error');
      }

      async function loadConfig(){
        try {
          setStatus('Loading…');
          const r = await fetch('/api/get_email_config.php', { credentials: 'include' });
          const j = await r.json();
          if(!j || !j.success) throw new Error(j?.error || 'Failed to load');
          const c = j.config || {};
          $('fromEmail').value = c.fromEmail || '';
          $('fromName').value = c.fromName || '';
          $('adminEmail').value = c.adminEmail || '';
          $('bccEmail').value = c.bccEmail || '';
          $('smtpEnabled').checked = !!c.smtpEnabled;
          $('smtpHost').value = c.smtpHost || '';
          $('smtpPort').value = c.smtpPort || '';
          $('smtpUsername').value = c.smtpUsername || '';
          $('smtpPassword').value = '';
          $('smtpEncryption').value = c.smtpEncryption || '';
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
          if ($('smtpEnabled').checked) form.append('smtpEnabled','1');
          form.append('smtpHost', $('smtpHost').value.trim());
          form.append('smtpPort', $('smtpPort').value.trim());
          form.append('smtpUsername', $('smtpUsername').value.trim());
          if ($('smtpPassword').value.trim() !== '') form.append('smtpPassword', $('smtpPassword').value.trim());
          form.append('smtpEncryption', $('smtpEncryption').value);

          const r = await fetch('/api/save_email_config.php', { method:'POST', credentials:'include', body: form });
          const j = await r.json();
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
          const r = await fetch('/api/save_email_config.php', { method:'POST', credentials:'include', body: form });
          const j = await r.json();
          if(!j || !j.success) throw new Error(j?.error || 'Test failed');
          setStatus('Test email sent!', true);
        } catch(e){ setStatus(e.message || 'Test failed'); }
      }

      document.addEventListener('DOMContentLoaded', function(){
        $('saveBtn')?.addEventListener('click', saveConfig);
        $('sendTestBtn')?.addEventListener('click', sendTest);
        loadConfig();
      });
    })();
  </script>
</body>
</html>
<?php if (!$isModal) { include dirname(__DIR__, 2) . '/partials/footer.php'; } ?>

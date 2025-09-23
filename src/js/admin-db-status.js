// Admin DB Status page module
(function AdminDbStatus() {
  function handleRunCommandClick(e) {
    const btn = e.target.closest('[data-action="runCommand"]');
    if (!btn) return;
    e.preventDefault();
    try {
      const params = btn.dataset.params ? JSON.parse(btn.dataset.params) : {};
      const command = params.command || '';
      if (!command) return;
      runCommand(command);
    } catch (err) {
      alert('Invalid command parameters');
    }
  }

  function runCommand(cmd) {
    // Allow optional env/table via data-params on the triggering element
    const active = window.__lastDbStatusParams || {};
    const qs = new URLSearchParams();
    qs.set('action', cmd);
    if (active.env) qs.set('env', active.env);
    if (active.table) qs.set('table', active.table);

    const doFetch = (withCsrfToken) => fetch(`/api/db_tools.php?${qs.toString()}`, {
      headers: withCsrfToken ? { 'X-CSRF-Token': withCsrfToken } : undefined,
      credentials: 'include'
    });

    doFetch()
      .then(async (res) => {
        if (res.status === 428) {
          // Need CSRF token; try to fetch and retry once
          const tokenRes = await fetch('/api/db_tools.php?action=csrf_token', { credentials: 'include' });
          const token = tokenRes.headers.get('X-CSRF-Token') || (await tokenRes.json().catch(()=>({}))).data?.csrf_token;
          if (token) {
            const retry = await doFetch(token);
            return retry.json();
          }
        }
        return res.json();
      })
      .then((data) => {
        if (!data) return;
        if (data.data && (cmd === 'version' || cmd === 'table_counts' || cmd === 'db_size' || cmd === 'list_tables' || cmd === 'describe')) {
          // Render into a well-known container if present
          const out = document.getElementById('dbToolsOutput');
          if (out) {
            out.textContent = JSON.stringify(data.data, null, 2);
          }
        }
        alert(data.message || (data.success ? 'Command executed' : 'Command failed'));
        if (data.success && (cmd === 'test-css' || cmd === 'generate-css')) {
          location.reload();
        }
      })
      .catch((error) => {
        alert('Error: ' + (error?.message || String(error)));
      });
  }

  function init() {
    document.addEventListener('click', (e) => {
      // persist params for env/table if provided by button
      const btn = e.target.closest('[data-action="runCommand"]');
      if (btn && btn.dataset.params) {
        try { window.__lastDbStatusParams = JSON.parse(btn.dataset.params); } catch (_) { /* ignore */ }
      }
      handleRunCommandClick(e);
    });

    // Auto-refresh every 30 seconds
    setInterval(() => {
      location.reload();
    }, 30000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();

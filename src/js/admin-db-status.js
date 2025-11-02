// Admin DB Status page module
import { ApiClient } from '../core/api-client.js';
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
      if (typeof window.showAlertModal === 'function') {
        window.showAlertModal({ title: 'Invalid Parameters', message: 'Invalid command parameters' });
      } else { alert('Invalid command parameters'); }
    }
  }

  function runCommand(cmd) {
    // Allow optional env/table via data-params on the triggering element
    const active = window.__lastDbStatusParams || {};
    const qs = new URLSearchParams();
    qs.set('action', cmd);
    if (active.env) qs.set('env', active.env);
    if (active.table) qs.set('table', active.table);

    const doRequest = async (withCsrfToken) => {
      const headers = withCsrfToken ? { 'X-CSRF-Token': withCsrfToken } : undefined;
      return ApiClient.request(`/api/db_tools.php?${qs.toString()}`, { headers });
    };

    doRequest()
      .then(async (data) => {
        if (data && data.status === 428) {
          // Fallback if server returns 428 in JSON form
          const tokenResp = await ApiClient.get('/api/db_tools.php', { action: 'csrf_token' }).catch(()=>null);
          const token = tokenResp?.headers?.get?.('X-CSRF-Token') || tokenResp?.data?.csrf_token || tokenResp?.csrf_token;
          if (token) {
            return doRequest(token);
          }
        }
        return data;
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
        const msg = data.message || (data.success ? 'Command executed' : 'Command failed');
        if (typeof window.showAlertModal === 'function') {
          window.showAlertModal({ title: 'Database Tools', message: msg, icon: data.success ? '✅' : '⚠️', iconType: data.success ? 'success' : 'warning' });
        } else { alert(msg); }
        if (data.success && (cmd === 'test-css' || cmd === 'generate-css')) {
          location.reload();
        }
      })
      .catch((error) => {
        const emsg = 'Error: ' + (error?.message || String(error));
        if (typeof window.showAlertModal === 'function') {
          window.showAlertModal({ title: 'Database Tools', message: emsg, icon: '⚠️', iconType: 'warning' });
        } else { alert(emsg); }
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

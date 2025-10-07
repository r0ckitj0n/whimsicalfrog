// Admin DB/Web Manager module
// Wires delegated handlers and migrates inline JS from admin/db_web_manager.php
import { ApiClient } from '../core/api-client.js';

(function initDbWebManager() {
  const byId = (id) => document.getElementById(id);
  const qsAll = (sel) => Array.from(document.querySelectorAll(sel));

  function showTab(tabName, btn) {
    qsAll('.tab-content').forEach((el) => el.classList.remove('active'));
    qsAll('.tab').forEach((el) => el.classList.remove('active'));
    const content = byId(tabName);
    if (content) content.classList.add('active');
    if (btn) btn.classList.add('active');
  }

  function loadStatus() {
    ApiClient.request(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=status',
    })
      .then((data) => {
        const el = byId('status');
        if (!el) return;
        if (data.success) {
          el.innerHTML = `
            <div class="status-grid">
              <div class="status-item"><strong>${data.data.version}</strong>MySQL Version</div>
              <div class="status-item"><strong>${data.data.tables}</strong>Tables</div>
              <div class="status-item"><strong>${data.data.size}</strong>Database Size</div>
              <div class="status-item"><strong>${data.data.host}</strong>Host</div>
              <div class="status-item"><strong>${data.data.database}</strong>Database</div>
            </div>`;
        } else {
          el.innerHTML = `<div class="error">Error: ${data.error}</div>`;
        }
      })
      .catch((err) => {
        const el = byId('status');
        if (el) el.innerHTML = `<div class="error">Connection failed: ${err.message}</div>`;
      });
  }

  function executeQuery() {
    const sqlEl = byId('sqlQuery');
    const resEl = byId('queryResults');
    if (!sqlEl || !resEl) return;
    const sql = sqlEl.value.trim();
    if (!sql) {
      alert('Please enter a SQL query');
      return;
    }

    resEl.innerHTML = '<div class="loading">Executing query...</div>';

    ApiClient.request(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=query&sql=${encodeURIComponent(sql)}`,
    })
      .then((data) => {
        if (data.success) {
          if (data.type === 'select' && data.data.length > 0) {
            let html = `<div class="success">Query executed successfully. ${data.rows} rows returned.</div>`;
            html += '<table><thead><tr>';
            Object.keys(data.data[0]).forEach((key) => (html += `<th>${key}</th>`));
            html += '</tr></thead><tbody>';
            data.data.forEach((row) => {
              html += '<tr>';
              Object.values(row).forEach((v) => (html += `<td>${v !== null ? v : '<em>NULL</em>'}</td>`));
              html += '</tr>';
            });
            html += '</tbody></table>';
            resEl.innerHTML = html;
          } else if (data.type === 'update') {
            resEl.innerHTML = `<div class="success">Query executed successfully. ${data.affected} rows affected.</div>`;
          } else {
            resEl.innerHTML = '<div class="success">Query executed successfully. No results returned.</div>';
          }
        } else {
          resEl.innerHTML = `<div class="error">Error: ${data.error}</div>`;
        }
      })
      .catch((err) => {
        resEl.innerHTML = `<div class="error">Request failed: ${err.message}</div>`;
      });
  }

  function clearQuery() {
    const sqlEl = byId('sqlQuery');
    const resEl = byId('queryResults');
    if (sqlEl) sqlEl.value = '';
    if (resEl) resEl.innerHTML = '';
  }

  function loadTables() {
    const resEl = byId('tablesResults');
    if (!resEl) return;
    resEl.innerHTML = '<div class="loading">Loading tables...</div>';

    ApiClient.request(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=tables',
    })
      .then((data) => {
        if (data.success) {
          let html = '<table><thead><tr><th>Table Name</th><th>Row Count</th><th>Actions</th></tr></thead><tbody>';
          data.data.forEach((table) => {
            html += `<tr>
              <td><strong>${table.name}</strong></td>
              <td>${table.rows}</td>
              <td>
                <button data-action="quickQuery" data-params='{"sql":"SELECT * FROM \`${table.name}\` LIMIT 10"}'>Preview</button>
                <button data-action="quickQuery" data-params='{"sql":"DESCRIBE \`${table.name}\`"}'>Structure</button>
              </td>
            </tr>`;
          });
          html += '</tbody></table>';
          resEl.innerHTML = html;
        } else {
          resEl.innerHTML = `<div class="error">Error: ${data.error}</div>`;
        }
      });
  }

  function describeTable() {
    const nameEl = byId('tableName');
    const resEl = byId('structureResults');
    if (!nameEl || !resEl) return;
    const tableName = nameEl.value.trim();
    if (!tableName) {
      alert('Please enter a table name');
      return;
    }
    resEl.innerHTML = '<div class="loading">Loading table structure...</div>';

    ApiClient.request(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=describe&table=${encodeURIComponent(tableName)}`,
    })
      .then((data) => {
        if (data.success) {
          let html = '<table><thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead><tbody>';
          data.data.forEach((field) => {
            html += `<tr>
              <td><strong>${field.Field}</strong></td>
              <td>${field.Type}</td>
              <td>${field.Null}</td>
              <td>${field.Key}</td>
              <td>${field.Default || '<em>NULL</em>'}</td>
              <td>${field.Extra}</td>
            </tr>`;
          });
          html += '</tbody></table>';
          resEl.innerHTML = html;
        } else {
          resEl.innerHTML = `<div class="error">Error: ${data.error}</div>`;
        }
      });
  }

  function quickQuery(sql) {
    const sqlEl = byId('sqlQuery');
    if (!sqlEl) return;
    sqlEl.value = sql;
    showTab('query');
    // activate first tab button visually if present
    const firstTab = document.querySelector('.tabs .tab');
    if (firstTab) firstTab.classList.add('active');
    executeQuery();
  }

  function onClick(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-action');
    const params = (() => {
      try {
        const raw = btn.getAttribute('data-params');
        return raw ? JSON.parse(raw) : {};
      } catch {
        return {};
      }
    })();

    switch (action) {
      case 'showTab':
        showTab(params.tabName, btn);
        break;
      case 'executeQuery':
        executeQuery();
        break;
      case 'clearQuery':
        clearQuery();
        break;
      case 'loadTables':
        loadTables();
        break;
      case 'describeTable':
        describeTable();
        break;
      case 'quickQuery':
        if (params.sql) quickQuery(params.sql);
        break;
      default:
        break;
    }
  }

  function run() {
    document.addEventListener('click', onClick);
    loadStatus();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
})();

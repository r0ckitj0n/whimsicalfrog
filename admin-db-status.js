// Admin DB Status page module: handles API-run DB smoke test UI
import '../styles/admin-db-status.css';
(function(){
  function el(id){ return document.getElementById(id); }
  function h(s){ return s == null ? '' : String(s); }
  function qsParam(name){
    try {
      var params = new URLSearchParams(window.location.search);
      return params.get(name);
    } catch(e) { return null; }
  }
  function renderParsed(container, data){
    if (!container) return;
    if (!data || data.ok !== true){
      var err = (data && data.error) ? data.error : 'Unknown error';
      container.innerHTML = '<div class="error">❌ ' + h(err) + '</div>';
      return;
    }
    var html = ''+
      '<div class="stat-row"><span>Env</span><span class="stat-value">' + h(data.env) + '</span></div>'+
      '<div class="stat-row"><span>Target</span><span class="stat-value">' + h(data.target) + '</span></div>'+
      '<div class="stat-row"><span>Host</span><span class="stat-value">' + h(data.config && data.config.host) + '</span></div>'+
      '<div class="stat-row"><span>Database</span><span class="stat-value">' + h(data.config && data.config.db) + '</span></div>'+
      '<div class="stat-row"><span>User</span><span class="stat-value">' + h(data.config && data.config.user) + '</span></div>'+
      '<div class="stat-row"><span>Port</span><span class="stat-value">' + h(data.config && data.config.port) + '</span></div>'+
      '<div class="stat-row"><span>MySQL Version</span><span class="stat-value">' + h(data.mysql_version) + '</span></div>'+
      '<div class="stat-row"><span>Current DB</span><span class="stat-value">' + h(data.current_db) + '</span></div>'+
      '<div class="stat-row"><span>Tables</span><span class="stat-value">' + h(data.tables) + '</span></div>'+
      '<div class="success">✅ Connection OK</div>';
    container.innerHTML = html;
  }
  function endpointExists(url){
    try {
      return window.ApiClient.request(url, { method: 'GET' })
        .then(function(){ return true; })
        .catch(function(){ return false; });
    } catch(e){ return Promise.resolve(false); }
  }

  function runApiSmoke(targetOverride){
    try {
      var wrap = el('apiSmokeTest');
      var parsed = el('apiParsed');
      var raw = el('apiRaw');
      var targetSel = el('apiTargetSelect');
      if (wrap) wrap.classList.remove('is-hidden');
      if (parsed) parsed.innerHTML = '<div class="stat-row"><span>Status</span><span class="stat-value">Running…</span></div>';
      if (raw) raw.textContent = '(loading…)';
      var target = typeof targetOverride === 'string' && targetOverride ? targetOverride : (targetSel ? targetSel.value : 'current');
      if (targetSel && targetSel.value !== target) {
        targetSel.value = target;
      }
      var url = '/api/db_smoke_test.php?target=' + encodeURIComponent(target);
      endpointExists('/api/db_smoke_test.php').then(function(exists){
        if (!exists){
          if (raw) raw.textContent = 'api/db_smoke_test.php not found; smoke test endpoint is not available.';
          if (parsed) parsed.innerHTML = '<div class="error">❌ Smoke test endpoint not available</div>';
          return;
        }
        window.ApiClient.request(url, { method: 'GET' })
          .then(function(data){
            if (raw) raw.textContent = JSON.stringify(data, null, 2);
            renderParsed(parsed, data);
          })
          .catch(function(err){
            if (raw) raw.textContent = String(err && err.message || err || 'Unknown error');
            if (parsed) parsed.innerHTML = '<div class="error">❌ ' + h(err && err.message || err || 'Unknown error') + '</div>';
          });
      });
    } catch(e) {}
  }
  function init(){
    var btn = document.getElementById('runApiSmokeBtn');
    if (btn) btn.addEventListener('click', function(ev){ ev.preventDefault(); runApiSmoke(); });

    // Auto-run if ?auto_api=1 is present; accept optional ?target=
    var auto = qsParam('auto_api');
    if (auto === '1' || auto === 'true') {
      var target = qsParam('target');
      if (target && ['current','local','live'].indexOf(target) === -1) {
        target = 'current';
      }
      runApiSmoke(target || undefined);
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();

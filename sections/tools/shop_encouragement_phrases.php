<?php
$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

require_once $root . '/api/config.php';
require_once $root . '/includes/functions.php';

if (!$inModal) {
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_shop_enc_footer_shutdown')) {
      function __wf_shop_enc_footer_shutdown(){ @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_shop_enc_footer_shutdown');
  }
  $section = 'settings';
  include_once $root . '/components/admin_nav_tabs.php';
}
if ($inModal) {
  include $root . '/partials/modal_header.php';
}
?>
<?php if (!$inModal): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>

<div class="p-3<?php echo $inModal ? ' modal-absolute-fill wf-flex-col wf-panel-fill' : ''; ?>">
  <?php if (!$inModal): ?>
  <div class="admin-card">
    <h1 class="admin-card-title">Shop Encouragement Phrases</h1>
    <div class="text-sm text-gray-600">Manage phrases shown as badges on recommended items. One per line.</div>
  </div>
  <?php endif; ?>

  <div class="admin-card<?php echo $inModal ? ' p-0 wf-card-fill' : ''; ?>">
    <?php if ($inModal): ?>
      <form id="sepForm" data-action="prevent-submit" class="wf-panel-fill wf-flex-col">
        <textarea id="sepTextarea" class="form-textarea w-full wf-textarea-fill wf-form-textarea-tight" placeholder="e.g. Staff Favorite\nTrending\nGreat Gift"></textarea>
      </form>
    <?php else: ?>
      <form id="sepForm" class="space-y-3" data-action="prevent-submit">
        <label class="block text-sm font-semibold" for="sepTextarea">Phrases</label>
        <textarea id="sepTextarea" class="form-textarea w-full" rows="10" placeholder="e.g. Staff Favorite\nTrending\nGreat Gift"></textarea>
        <div class="flex items-center justify-end gap-2">
          <span id="sepStatus" class="text-sm text-gray-500" aria-live="polite"></span>
          <button type="button" id="sepSave" class="btn btn-primary btn-sm">Save</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  const ta = document.getElementById('sepTextarea');
  const saveBtn = document.getElementById('sepSave');
  const statusEl = document.getElementById('sepStatus');
  const setStatus = (m, ok) => { if (!statusEl) return; statusEl.textContent = m||''; statusEl.style.color = ok ? '#065f46' : '#b91c1c'; };
  const sendStatus = (m, ok) => { try { if (window.parent && window.parent !== window) window.parent.postMessage({ source:'wf-sep', type:'status', message: m||'', ok: !!ok }, '*'); } catch(_) {} };

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
    const headers = { 'Content-Type': 'application/json', 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) };
    const cfg = { credentials:'include', method:m, headers, ...(options||{}) };
    if (data !== null && typeof cfg.body === 'undefined') cfg.body = JSON.stringify(data);
    const res = await fetch(url, cfg);
    return res.json().catch(()=>({}));
  }
  const apiGet = (url, params) => apiRequest('GET', url, null, { params });
  const apiPost = (url, body, options) => apiRequest('POST', url, body, options);

  async function load(){
    try{
      setStatus('Loading…', true); sendStatus('Loading…', true);
      const j = await apiGet('/api/shop_encouragement_phrases.php');
      const arr = Array.isArray(j && j.phrases) ? j.phrases : [];
      ta.value = arr.join('\n');
      setStatus('Loaded', true); sendStatus('Loaded', true);
    }catch(_){ setStatus('Load failed', false); sendStatus('Load failed', false); }
  }
  function parse(){
    const raw = (ta.value||'').split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
    const out=[]; for (let i=0;i<raw.length && out.length<100;i++){ if (!out.includes(raw[i])) out.push(raw[i]); }
    return out;
  }
  async function save(){
    try{
      setStatus('Saving…', true); sendStatus('Saving…', true);
      const phrases = parse();
      const j = await apiPost('/api/shop_encouragement_phrases.php', { phrases });
      if (j && j.success){ setStatus('Saved', true); sendStatus('Saved', true); } else { setStatus('Save failed', false); sendStatus('Save failed', false); }
    }catch(_){ setStatus('Save failed', false); sendStatus('Save failed', false); }
  }
  saveBtn && saveBtn.addEventListener('click', (e)=>{ e.preventDefault(); save(); });
  try {
    window.addEventListener('message', function(ev){
      try {
        const d = ev && ev.data; if (!d || d.source !== 'wf-sep-parent') return;
        if (d.type === 'save') save();
      } catch(_) {}
    });
  } catch(_) {}
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', load, { once:true }); else load();
})();
</script>

<?php if (!$inModal): ?>
  </div>
</div>
<?php endif; ?>

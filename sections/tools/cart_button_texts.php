<?php
$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

require_once $root . '/api/config.php';
require_once $root . '/includes/functions.php';

if (!$inModal) {
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_cart_texts_footer_shutdown')) {
      function __wf_cart_texts_footer_shutdown(){ @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_cart_texts_footer_shutdown');
  }
  $section = 'settings';
  include_once $root . '/components/admin_nav_tabs.php';
}
if ($inModal) {
  include $root . '/partials/modal_header.php';
}
?>
<script>
(function(){
  try { window.__WF_BLOCK_TOOLTIP_ATTACH = true; } catch (_) {}
  try {
    if (document && document.addEventListener) {
      document.addEventListener('DOMContentLoaded', function(){
        try { if (document.body) document.body.setAttribute('data-wf-block-tooltips','1'); } catch(_) {}
      }, { once:true });
    }
  } catch (_) {}
})();
</script>
<?php if (!$inModal): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>

<div class="p-3<?php echo $inModal ? ' modal-absolute-fill wf-flex-col wf-panel-fill' : ''; ?>">
  <?php if ($inModal): ?>
  
  <?php endif; ?>
  <?php if (!$inModal): ?>
  <div class="admin-card">
    <h1 class="admin-card-title">Cart Button Texts</h1>
    <div class="text-sm text-gray-600">Manage alternate texts for the Add to Cart button. One per line.</div>
  </div>
  <?php endif; ?>

  <div class="admin-card<?php echo $inModal ? ' p-0 wf-card-fill' : ''; ?>">
    <?php if ($inModal): ?>
      <form id="cbtForm" data-action="prevent-submit" class="wf-panel-fill wf-flex-col">
        <textarea id="cbtTextarea" class="form-textarea w-full wf-textarea-fill wf-form-textarea-tight" placeholder="e.g. Add to Cart\nGrab It Now\nGet Yours"></textarea>
      </form>
    <?php else: ?>
      <form id="cbtForm" class="space-y-3" data-action="prevent-submit">
        <label class="block text-sm font-semibold" for="cbtTextarea">Button Text Variations</label>
        <textarea id="cbtTextarea" class="form-textarea w-full" rows="10" placeholder="e.g. Add to Cart\nGrab It Now\nGet Yours"></textarea>
        <div class="flex items-center justify-end gap-2 cbt-actions">
          <span id="cbtStatus" class="text-sm text-gray-500" aria-live="polite"></span>
          <button type="button" id="cbtSave" class="btn btn-primary btn-sm">Save</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  const ta = document.getElementById('cbtTextarea');
  const saveBtn = document.getElementById('cbtSave');
  const statusEl = document.getElementById('cbtStatus');
  const setStatus = (m, ok) => { if (!statusEl) return; statusEl.textContent = m||''; statusEl.style.color = ok ? '#065f46' : '#b91c1c'; };
  const sendStatus = (m, ok) => { try { if (window.parent && window.parent !== window) window.parent.postMessage({ source:'wf-cbt', type:'status', message: m||'', ok: !!ok }, '*'); } catch(_) {} };

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
      const j = await apiGet('/api/cart_button_texts.php');
      const arr = Array.isArray(j && j.texts) ? j.texts : [];
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
      const texts = parse();
      const j = await apiPost('/api/cart_button_texts.php', { texts });
      if (j && j.success){
        setStatus('Saved', true); sendStatus('Saved', true);
      } else {
        const err = (j && (j.error || j.message)) ? String(j.error || j.message) : '';
        const msg = err ? `Save failed: ${err}` : 'Save failed';
        setStatus(msg, false); sendStatus(msg, false);
      }
    }catch(_){ setStatus('Save failed', false); sendStatus('Save failed', false); }
  }
  saveBtn && saveBtn.addEventListener('click', (e)=>{ e.preventDefault(); save(); });
  try {
    window.addEventListener('message', function(ev){
      try {
        const d = ev && ev.data; if (!d || d.source !== 'wf-cbt-parent') return;
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

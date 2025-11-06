<?php
$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');
require_once $root . '/api/config.php';
require_once $root . '/includes/vite_helper.php';
if (!$inModal) {
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_automation_footer_shutdown')) {
      function __wf_automation_footer_shutdown(){ @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_automation_footer_shutdown');
  }
}
if ($inModal) {
  include $root . '/partials/modal_header.php';
}
?>
<div class="admin-marketing-page">
  <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
    <div class="modal-header">
      <h2 id="automationManagerTitle" class="admin-card-title">⚙️ Automation Manager</h2>
      <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
    </div>
    <div class="modal-body">
      <div class="rounded border p-3 mb-3" id="automationShippingAttrTools">
        <div class="flex items-center justify-between gap-2">
          <h3 class="text-sm font-semibold">Item Shipping Attributes</h3>
          <span id="automationShippingAttrStatus" class="text-sm text-gray-600" aria-live="polite"></span>
        </div>
        <div class="mt-2 flex items-center gap-2">
          <button type="button" class="btn btn-secondary" id="autoEnsureItemDimsBtn">Ensure Columns</button>
          <button type="button" class="btn btn-primary" id="autoBackfillItemDimsBtn">Backfill with AI</button>
        </div>
        <div id="automationShippingAttrPreview" class="mt-2 text-xs text-gray-600"></div>
      </div>
      <div id="automationManagerContent" class="space-y-3 text-sm text-gray-700">Loading automations…</div>
    </div>
  </div>
</div>
<?php echo vite_entry('src/entries/admin-marketing.js'); ?>
<script>
(function boot(){
  function go(){
    try{ if(window.AdminMarketingModule && window.AdminMarketingModule.openAutomationManager){ window.AdminMarketingModule.openAutomationManager(); return true; } }catch(e){}
    return false;
  }
  if(!go()){
    let tries=0; const t=setInterval(()=>{ if(go()|| ++tries>40) clearInterval(t); }, 100);
  }
})();
</script>
<script>
(function(){
  function qs(id){ return document.getElementById(id); }
  function setStatus(t, ok){ try{ var s=qs('automationShippingAttrStatus'); if(!s) return; s.textContent=t||''; s.classList.remove('text-green-700','text-red-700'); s.classList.add(ok?'text-green-700':'text-red-700'); }catch(_){} }
  function renderPreview(r){ try{ var p=qs('automationShippingAttrPreview'); if(!p) return; var res=(r&&r.results)||{}; var upd=Number(res.updated||0); var sk=Number(res.skipped||0); var ensured=!!res.ensured; var lines=[]; lines.push('Ensured columns: '+(ensured?'yes':'no')); lines.push('Updated: '+upd+', Skipped: '+sk); var prev=Array.isArray(res.preview)?res.preview:[]; if(prev.length){ var list=prev.slice(0,8).map(function(it){ var dims=(it.LxWxH_in||[]).join('×'); return (it.sku||'')+' · '+(it.weight_oz!=null?String(it.weight_oz)+' oz':'')+(dims?(' · '+dims+' in'):''); }); lines.push('Examples: '+list.join('; ')); } p.textContent=lines.join(' | '); }catch(_){} }
  function handleJson(j){ var d=(j&&j.data)||j||{}; renderPreview(d); setStatus('Done', true); }
  function handleErr(){ setStatus('Failed', false); }
  function ensure(){ setStatus('Ensuring…', true); try { (window.WhimsicalFrog&&WhimsicalFrog.api||{}).get('/api/item_dimensions_tools.php', { action:'ensure_columns' }).then(handleJson).catch(handleErr); } catch(_){ handleErr(); } }
  function backfill(){ if(!confirm('Run AI backfill for item shipping attributes?')) return; setStatus('Running…', true); try { (window.WhimsicalFrog&&WhimsicalFrog.api||{}).post('/api/item_dimensions_tools.php', { action:'run_all', use_ai:1 }).then(handleJson).catch(handleErr); } catch(_){ handleErr(); } }
  function init(){ var a=qs('autoEnsureItemDimsBtn'); var b=qs('autoBackfillItemDimsBtn'); if(a&&!a.__wf){ a.__wf=true; a.addEventListener('click', ensure, true); } if(b&&!b.__wf){ b.__wf=true; b.addEventListener('click', backfill, true); } }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', init, {once:true}); } else { init(); }
})();
</script>

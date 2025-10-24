<?php
$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');
require_once $root . '/api/config.php';
require_once $root . '/includes/vite_helper.php';
if (!$inModal) {
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_social_manager_footer_shutdown')) {
      function __wf_social_manager_footer_shutdown(){ @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_social_manager_footer_shutdown');
  }
}
?>
<style>
.hidden{display:none !important}
</style>
<div class="admin-marketing-page">
  <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
    <div class="modal-header">
      <h2 id="socialManagerTitle" class="admin-card-title">📱 Social Accounts Manager</h2>
      <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
    </div>
    <div class="modal-body">
      <div id="socialManagerContent" class="space-y-2 text-sm text-gray-700">Loading accounts…</div>
    </div>
  </div>
</div>
<?php echo vite_entry('src/entries/admin-marketing.js'); ?>
<script>
(function boot(){
  function go(){
    try{ if(window.AdminMarketingModule && window.AdminMarketingModule.openSocialManager){ window.AdminMarketingModule.openSocialManager(); return true; } }catch(e){}
    return false;
  }
  if(!go()){
    let tries=0; const t=setInterval(()=>{ if(go()|| ++tries>40) clearInterval(t); }, 100);
  }
})();
</script>

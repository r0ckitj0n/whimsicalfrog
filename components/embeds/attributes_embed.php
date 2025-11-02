<?php
// Attributes (Gender, Size & Color) Embed for Settings modal iframe
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once dirname(__DIR__, 2) . '/api/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/auth_helper.php';
AuthHelper::requireAdmin();

$__wf_modal = isset($_GET['modal']) && $_GET['modal'] !== '0';
if ($__wf_modal) {
    // Minimal header enables embed child autosize and trims default chrome
    include dirname(__DIR__, 2) . '/partials/modal_header.php';
} else {
    $page = 'admin';
    include dirname(__DIR__, 2) . '/partials/header.php';
}
?>
<?php if ($__wf_modal): ?>
<style>
  /* Hide global chrome inside iframe and remove trailing gaps */
  .site-header, .universal-page-header, .admin-tab-navigation { display: none !important; }
  html, body { background: transparent !important; margin:0 !important; height:auto !important; min-height:auto !important; overflow:visible !important; }
  #admin-section-content { padding: 6px 12px 12px !important; display:block; height:auto !important; max-height:none !important; overflow:visible !important; }
  .admin-header-section { display: none !important; }
  /* Eliminate bottom whitespace from collapsed margins */
  #admin-section-content > *:last-child { margin-bottom: 0 !important; }
</style>
<?php endif; ?>
<div id="admin-section-content">
<?php
// Render the Inventory Admin page; the hash will focus the attributes section
include dirname(__DIR__, 2) . '/sections/admin_inventory.php';
?>
</div>
<script>
// Scroll to attributes section if present
(function(){
  try {
    var el = document.querySelector('#attributes, a[name="attributes"], [id*="attributes"]');
    if (el && el.scrollIntoView) { setTimeout(function(){ el.scrollIntoView({behavior:'instant', block:'start'}); }, 50); }
  } catch(_) {}
})();
</script>
<?php if (!$__wf_modal) { include dirname(__DIR__, 2) . '/partials/footer.php'; } ?>

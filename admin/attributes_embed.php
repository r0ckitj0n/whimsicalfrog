<?php
// Attributes (Gender, Size & Color) Embed for Settings modal iframe
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
if (!function_exists('isAdminWithToken') || !isAdminWithToken()) {
    http_response_code(403);
    echo '<div style="padding:16px;color:#b91c1c;font-family:sans-serif">Access denied.</div>';
    exit;
}
$page = 'admin';
include dirname(__DIR__) . '/partials/header.php';
?>
<style>
/* Hide global header and admin tabs inside the iframe */
.site-header, .universal-page-header, .admin-tab-navigation { display: none !important; }
html, body { background: transparent !important; }
#admin-section-content { padding: 6px 12px 12px !important; }
/* Maximize space: hide page-level headers inside embeds */
.admin-header-section { display: none !important; }
</style>
<div id="admin-section-content">
<?php
// Render the Inventory Admin page; the hash will focus the attributes section
include __DIR__ . '/admin_inventory.php';
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
<?php include dirname(__DIR__) . '/partials/footer.php'; ?>

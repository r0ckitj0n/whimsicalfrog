<?php
// Database Manager (CLI tool guidance page)
$__wf_included_layout = false;
if (!function_exists('__wf_admin_root_footer_shutdown')) {
    include dirname(__DIR__, 2) . '/partials/header.php';
    $__wf_included_layout = true;
}
?>
<section class="page-content container mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-2">Database Manager (CLI)</h1>
  <p class="text-gray-700 mb-6">This tool is designed to be run from the command line for safety. Use the commands below on your server or local development machine.</p>

  <div class="bg-white rounded shadow p-4 mb-6">
    <h2 class="text-xl font-medium mb-2">Usage</h2>
    <pre class="bg-gray-50 rounded p-3 overflow-auto"><code>php admin/db_manager.php -help
php admin/db_manager.php -env=local -action=status
php admin/db_manager.php -env=live -action=query -sql="SELECT COUNT(*) FROM items"
php admin/db_manager.php -action=sync -from=local -to=live</code></pre>
  </div>

  <div class="bg-white rounded shadow p-4">
    <h2 class="text-xl font-medium mb-2">Location</h2>
    <p class="text-gray-700">Script: <code>admin/db_manager.php</code> (moved backups kept at <code>backups/admin/db_manager.php</code> if deactivated)</p>
  </div>
</section>
<?php if ($__wf_included_layout) { include dirname(__DIR__, 2) . '/partials/footer.php'; } ?>

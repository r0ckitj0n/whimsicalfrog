<?php
// Database Manager (CLI tool guidance page)
$__wf_included_layout = false;
if (!function_exists('__wf_admin_root_footer_shutdown')) {
    include dirname(__DIR__, 2) . '/partials/header.php';
    $__wf_included_layout = true;
}
?>
<section class="page-content container mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-2">Database Tools</h1>
  <p class="text-gray-700 mb-6">For most cases, use the Web Database Manager under <a href="/admin/?section=db-web-manager" class="text-blue-600 hover:text-blue-800">Admin → DB Web Manager</a>. CLI usage is deprecated in this repo; see repository history if you need historical CLI scripts.</p>

  <div class="bg-white rounded shadow p-4 mb-6">
    <h2 class="text-xl font-medium mb-2">Recommended</h2>
    <ul class="list-disc list-inside text-sm text-gray-700">
      <li>Use <strong>DB Web Manager</strong> for status, table listing, and describe.</li>
      <li>Use <strong>API</strong> endpoints in <code>/api/db_tools.php</code> for introspection (status/version/table_counts/db_size/list_tables/describe).</li>
    </ul>
  </div>

  <div class="bg-white rounded shadow p-4">
    <h2 class="text-xl font-medium mb-2">CLI (Deprecated)</h2>
    <p class="text-gray-700">CLI scripts were previously stored under <code>admin/</code>. Refer to repository history for <code>admin/db_manager.php</code> if needed.</p>
  </div>
</section>
<?php if ($__wf_included_layout) {
    include dirname(__DIR__, 2) . '/partials/footer.php';
} ?>

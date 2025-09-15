<?php
// Area-Item Mapper (migrated to sections/tools)
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
if (class_exists('Auth')) { Auth::requireAdmin(); } elseif (function_exists('requireAdmin')) { requireAdmin(); }

$__wf_included_layout = false;
if (!function_exists('__wf_admin_root_footer_shutdown')) {
    include dirname(__DIR__) . '/partials/header.php';
    $__wf_included_layout = true;
}
?>
<div class="bg-gray-100 min-h-screen">
  <div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow p-6">
      <h1 class="text-2xl font-bold mb-4">Area-Item Mapper</h1>
      <p class="text-sm text-gray-600 mb-4">Link clickable areas to items or categories for each room.</p>
      <div id="aimMessage" class="mb-4"></div>
      <div class="grid md:grid-cols-3 gap-4 mb-6">
        <div>
          <label for="aimRoomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room</label>
          <select id="aimRoomSelect" class="form-input w-full">
            <option value="">Choose a room...</option>
            <option value="1">Room 1</option>
            <option value="2">Room 2</option>
            <option value="3">Room 3</option>
            <option value="4">Room 4</option>
            <option value="5">Room 5</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Area Selector (e.g., .area-1)</label>
          <input id="aimAreaSelector" type="text" class="form-input w-full" placeholder=".area-1">
        </div>
        <div class="grid grid-cols-2 gap-3 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Mapping Type</label>
            <select id="aimMappingType" class="form-input w-full">
              <option value="item">Item</option>
              <option value="category">Category</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ID</label>
            <input id="aimTargetId" type="number" class="form-input w-full" placeholder="Item or Category ID">
          </div>
        </div>
      </div>
      <div class="flex gap-3 mb-6">
        <button id="aimAddBtn" class="btn btn-primary">Add Mapping</button>
      </div>
      <div>
        <h2 class="text-xl font-semibold mb-2">Mappings</h2>
        <div id="aimMappingsList" class="border rounded overflow-hidden"></div>
      </div>
    </div>
  </div>
</div>
<?php if ($__wf_included_layout) { include dirname(__DIR__) . '/partials/footer.php'; } ?>
<script type="module" src="/src/js/admin-area-item-mapper.js"></script>

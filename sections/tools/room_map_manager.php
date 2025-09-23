<?php
// Room Map Manager (migrated to sections/tools)
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
if (class_exists('Auth')) {
    Auth::requireAdmin();
} elseif (function_exists('requireAdmin')) {
    requireAdmin();
}

$__wf_included_layout = false;
if (!function_exists('__wf_admin_root_footer_shutdown')) {
    include dirname(__DIR__) . '/partials/header.php';
    $__wf_included_layout = true;
}
?>
<div class="bg-gray-100 min-h-screen">
  <div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow p-6">
      <h1 class="text-2xl font-bold mb-4">Room Map Manager</h1>
      <p class="text-sm text-gray-600 mb-4">Create, activate, restore and delete room maps for each room.</p>
      <div id="rmMessage" class="mb-4"></div>
      <div class="grid md:grid-cols-3 gap-4 mb-6">
        <div>
          <label for="rmRoomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room</label>
          <select id="rmRoomSelect" class="form-input w-full">
            <option value="">Choose a room...</option>
            <option value="1">Room 1</option>
            <option value="2">Room 2</option>
            <option value="3">Room 3</option>
            <option value="4">Room 4</option>
            <option value="5">Room 5</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Map Name</label>
          <input id="rmMapName" type="text" class="form-input w-full" placeholder="e.g., Default Layout">
        </div>
        <div class="flex items-end">
          <button id="rmCreateMapBtn" class="btn btn-primary w-full">Save New Map</button>
        </div>
      </div>

      <div class="mb-6">
        <h2 class="text-xl font-semibold mb-2">Maps</h2>
        <div id="rmMapsList" class="border rounded overflow-hidden"></div>
      </div>

      <div class="border-t pt-6">
        <h2 class="text-xl font-semibold mb-2">Coordinates JSON</h2>
        <p class="text-xs text-gray-500 mb-2">Paste or edit coordinates JSON for the map you are creating.</p>
        <textarea id="rmCoordinates" class="form-textarea w-full h-48 font-mono" placeholder='[{"selector":".area-1","coords":[[10,10],[100,10],[100,100],[10,100]]}]'></textarea>
      </div>
    </div>
  </div>
</div>
<?php if ($__wf_included_layout) {
    include dirname(__DIR__) . '/partials/footer.php';
} ?>
<?php echo vite_entry('src/js/admin-room-map-manager.js'); ?>

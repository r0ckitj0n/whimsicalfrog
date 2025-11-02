<?php
// Area-Item Mapper (migrated to sections/tools)
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/api/room_helpers.php';
if (class_exists('Auth')) {
    Auth::requireAdmin();
} elseif (function_exists('requireAdmin')) {
    requireAdmin();
}

// Get room data
$rooms = getRoomDoorsData();
$roomOptions = [['value' => 'A', 'label' => 'Landing Page'], ['value' => '0', 'label' => 'Main Room']];
foreach ($rooms as $room) {
    $roomOptions[] = ['value' => $room['room_number'], 'label' => $room['room_name'] ?: "Room {$room['room_number']}"];
}

$__wf_modal = isset($_GET['modal']) && $_GET['modal'] !== '0';
$__wf_included_layout = false;
if (!$__wf_modal && !function_exists('__wf_admin_root_footer_shutdown')) {
  include dirname(__DIR__, 2) . '/partials/header.php';
  $__wf_included_layout = true;
}
if ($__wf_modal) {
    include dirname(__DIR__, 2) . '/partials/modal_header.php';
}
?>
<?php if ($__wf_modal): ?>
  <div class="bg-white">
    <div class="px-4 py-3 border-b">
      <p class="text-sm text-gray-600 mt-1">Map room areas to items or categories, and find unrepresented content.</p>
    </div>
    <div class="p-4 mx-auto wf-max-w-none wf-w-full">
      <div class="grid gap-6 md:grid-cols-2">
        <div>
          <div id="aimMessage" class="mb-3"></div>
          <div class="grid gap-4">
            <div>
              <label for="aimRoomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room</label>
              <select id="aimRoomSelect" class="form-input w-full">
                <option value="">Choose a room...</option>
                <?php foreach ($roomOptions as $option): ?>
                  <option value="<?= htmlspecialchars($option['value']) ?>"><?= htmlspecialchars($option['label']) ?></option>
                <?php endforeach; ?>
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Target</label>
                <input id="aimTargetId" type="text" class="form-input w-full" placeholder="SKU or Category ID">
              </div>
            </div>
            <div class="flex gap-3">
              <button id="aimAddBtn" class="btn btn-sm btn-primary">Add Mapping</button>
            </div>
          </div>
        </div>
        <div>
          <h2 class="text-lg font-semibold mb-2">Mappings</h2>
          <div id="aimMappingsList" class="border rounded overflow-hidden wf-w-full wf-max-w-none"></div>
        </div>
      </div>
    </div>
  </div>
  
<?php else: ?>
  <div class="bg-gray-100 min-h-screen p-4 sm:p-6 lg:p-8">
    <div class="w-full max-w-[90vw] mx-auto bg-white rounded-lg shadow-xl">
      <div class="p-6 border-b">
        <h1 class="text-2xl font-bold">Area Mappings</h1>
        <p class="text-sm text-gray-600 mt-1">Map room areas to items or categories, and find unrepresented content.</p>
      </div>
      <div class="p-6">
        <div id="aimMessage" class="mb-4"></div>
        <div class="border-b border-gray-200">
          <nav class="-mb-px flex space-x-8" aria-label="Tabs" role="tablist">
            <button data-tab="mappings" role="tab" aria-selected="true" class="aim-tab tab-outline whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600">Mappings</button>
            <button data-tab="unrepresented" role="tab" aria-selected="false" class="aim-tab tab-outline whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Unrepresented</button>
            <button data-tab="coordinates" role="tab" aria-selected="false" class="aim-tab tab-outline whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Coordinates</button>
          </nav>
        <!-- Tab Panels -->
        <div class="pt-6">
          <!-- Mappings Panel -->
          <div id="tab-panel-mappings" class="aim-tab-panel">
            <div class="mb-4">
              <label for="aimRoomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room</label>
              <select id="aimRoomSelect" class="form-input w-full max-w-md">
                <option value="">Choose a room...</option>
                <?php foreach (getRoomDoorsData() as $room): ?>
                  <option value="<?= htmlspecialchars($room['room_number']) ?>"><?= htmlspecialchars($room['room_name'] ?: "Room {$room['room_number']}") ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-4">
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Target</label>
                <input id="aimTargetId" type="text" class="form-input w-full" placeholder="SKU or Category ID">
              </div>
            </div>
            <div class="flex gap-3 mb-4">
              <button id="aimAddBtn" class="btn btn-sm btn-primary">Add Mapping</button>
            </div>
            <div>
              <h2 class="text-lg font-semibold mb-2">Mappings</h2>
              <div id="aimMappingsList" class="border rounded overflow-hidden"></div>
            </div>
          </div>
          <!-- Unrepresented Panel -->
          <div id="tab-panel-unrepresented" class="aim-tab-panel hidden"></div>
          <!-- Coordinates Panel -->
          <div id="tab-panel-coordinates" class="aim-tab-panel hidden"></div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php if ($__wf_included_layout) {
    include dirname(__DIR__, 2) . '/partials/footer.php';
} ?>
<?php
// Emit Vite-managed entry only (no direct /src fallbacks or inline JS)
try {
    if (function_exists('vite')) {
        echo vite('js/area-item-mapper.js');
    } elseif (function_exists('vite_entry')) {
        echo vite_entry('src/entries/area-item-mapper.js');
    }
} catch (Throwable $e) {
    echo "<!-- Vite emission failed: " . htmlspecialchars($e->getMessage()) . " -->\n";
}
?>

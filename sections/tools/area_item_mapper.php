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
?>
<div class="bg-gray-100 min-h-screen p-4 sm:p-6 lg:p-8">
  <div class="w-full max-w-[90vw] mx-auto bg-white rounded-lg shadow-xl">
    <div class="p-6 border-b">
      <h1 class="text-2xl font-bold">Area-Item Mapper</h1>
      <p class="text-sm text-gray-600 mt-1">Map room areas to items or categories, and find unrepresented content.</p>
    </div>

    <div class="p-6">
      <div id="aimMessage" class="mb-4"></div>

      <?php if (!$__wf_modal): ?>
      <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
          <button data-tab="mappings" class="aim-tab whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600">Mappings</button>
          <button data-tab="unrepresented" class="aim-tab whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Unrepresented</button>
          <button data-tab="coordinates" class="aim-tab whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Coordinates</button>
        </nav>
      </div>
      <?php endif; ?>

      <!-- Tab Panels -->
      <div class="pt-6">
        <!-- Mappings Panel -->
        <div id="tab-panel-mappings" class="aim-tab-panel">
          <div class="mb-4">
            <label for="aimRoomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room</label>
            <select id="aimRoomSelect" class="form-input w-full max-w-md">
              <option value="">Choose a room...</option>
              <?php foreach ($roomOptions as $option): ?>
                <option value="<?= htmlspecialchars($option['value']) ?>"><?= htmlspecialchars($option['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="aimMappingsContainer" class="hidden"></div>
        </div>

        <!-- Unrepresented Panel -->
        <div id="tab-panel-unrepresented" class="aim-tab-panel hidden"></div>

        <!-- Coordinates Panel -->
        <div id="tab-panel-coordinates" class="aim-tab-panel hidden"></div>
      </div>
    </div>
  </div>
</div>
<?php if ($__wf_included_layout) {
    include dirname(__DIR__, 2) . '/partials/footer.php';
} ?>
<?php echo vite_entry('src/js/admin-area-item-mapper.js'); ?>

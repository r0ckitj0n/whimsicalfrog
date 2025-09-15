<?php
// Room Map Editor - draw polygons over background image per room
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
if (class_exists('Auth')) { Auth::requireAdmin(); } elseif (function_exists('requireAdmin')) { requireAdmin(); }

$__wf_included_layout = false;
if (!function_exists('__wf_admin_root_footer_shutdown')) {
    include __DIR__ . '/../partials/header.php';
    $__wf_included_layout = true;
}
?>
<div class="bg-gray-100 min-h-screen">
  <div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow p-6">
      <h1 class="text-2xl font-bold mb-4">Room Map Editor</h1>
      <p class="text-sm text-gray-600 mb-4">Draw clickable polygons over a background image for each room, then save as a map.</p>

      <div id="rmeMessage" class="mb-4"></div>

      <div class="grid md:grid-cols-4 gap-4 items-end mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2" for="rmeRoomSelect">Room</label>
          <select id="rmeRoomSelect" class="form-input w-full">
            <option value="">Choose a room...</option>
            <option value="1">Room 1</option>
            <option value="2">Room 2</option>
            <option value="3">Room 3</option>
            <option value="4">Room 4</option>
            <option value="5">Room 5</option>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2" for="rmeBgUrl">Background Image URL</label>
          <input id="rmeBgUrl" type="text" class="form-input w-full" placeholder="/images/backgrounds/room1.webp">
        </div>
        <div class="flex gap-2">
          <button id="rmeLoadActiveBtn" class="btn btn-secondary">Load Active</button>
          <button id="rmeSaveMapBtn" class="btn btn-primary">Save as New Map</button>
        </div>
      </div>

      <div class="flex gap-2 mb-3">
        <button id="rmeStartPolyBtn" class="btn btn-secondary">Start Polygon</button>
        <button id="rmeFinishPolyBtn" class="btn">Finish Polygon</button>
        <button id="rmeUndoPointBtn" class="btn">Undo Point</button>
        <button id="rmeClearBtn" class="btn btn-danger">Clear All</button>
      </div>

      <div id="rmeFrame" class="border rounded overflow-hidden relative rme-canvas-wrap">
        <div id="rmeCanvas" class="relative rme-canvas">
          <svg id="rmeSvg" xmlns="http://www.w3.org/2000/svg" class="absolute inset-0 w-full h-full rme-svg"></svg>
        </div>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Coordinates JSON</label>
        <textarea id="rmeCoords" class="form-textarea w-full h-40 font-mono text-xs" placeholder='{"polygons":[{"points":[[x,y],[x,y],...]}, ...]}'></textarea>
      </div>
    </div>
  </div>
</div>
<?php if ($__wf_included_layout) { include __DIR__ . '/../partials/footer.php'; } ?>
<script type="module" src="/src/js/admin-room-map-editor.js"></script>

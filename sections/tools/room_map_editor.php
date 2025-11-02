<?php
// Room Map Editor (migrated to sections/tools)
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
if (class_exists('Auth')) {
    Auth::requireAdmin();
} elseif (function_exists('requireAdmin')) {
    requireAdmin();
}

$__wf_included_layout = false;
$is_modal_context = isset($_GET['modal'])
    || strpos($_SERVER['HTTP_REFERER'] ?? '', 'section=settings') !== false
    || strpos($_SERVER['HTTP_REFERER'] ?? '', 'admin_settings') !== false;
if (!$is_modal_context && !function_exists('__wf_admin_root_footer_shutdown')) {
    include dirname(__DIR__, 2) . '/partials/header.php';
    $__wf_included_layout = true;
}
if ($is_modal_context) {
    include dirname(__DIR__, 2) . '/partials/modal_header.php';
}
?>
<div class="rme-app" data-version="redesign-v2">
  <div class="rme-card">
    <header class="rme-header">
      <!-- Header kept minimal to free vertical space; title moved to intro panel -->
    </header>

    <div class="rme-layout">
      <aside class="rme-sidebar">
        <section id="rmeIntroSection" class="rme-panel">
          <h2 class="rme-panel-title">🗺️ Room Map Manager <span class="rme-title-badge">[NEW]</span></h2>
          <p class="rme-panel-subtitle">Select a room, load or manage saved maps, and drag the area boxes to adjust clickable regions.</p>
          <p id="rmeIntroHint" class="rme-panel-subtitle hidden">ℹ️ No map found for this room yet. Click "Add New Area" to get started!</p>
        </section>
        <section id="rmeRoomBgSection" class="rme-panel">
          <h2 class="rme-panel-title">Room &amp; Background</h2>
          <div class="rme-field-group">
            <label for="rmeRoomSelect" class="rme-label">Room</label>
            <select id="rmeRoomSelect" class="form-input">
              <option value="">Choose a room...</option>
            </select>
          </div>
          <div class="rme-field-group">
            <label for="rmeBgUrl" class="rme-label">Background Image URL</label>
            <input id="rmeBgUrl" type="text" class="form-input" placeholder="/images/backgrounds/room1.webp">
          </div>
        </section>

        <section id="rmeActionsSection" class="rme-panel">
          <h2 class="rme-panel-title">Quick Actions</h2>
          <div class="rme-actions-grid">
            <button id="rmeLoadActiveBtn" class="admin-action-button btn btn-secondary btn-icon btn-icon--download" aria-label="Load Map" title="Load the currently active map for this room"></button>
            <button id="rmeSaveMapBtn" class="admin-action-button btn btn-primary btn-icon btn-icon--save" aria-label="Save Map" title="Save your changes as a new map"></button>
            <button id="rmeFullscreenBtn" class="admin-action-button btn btn-info btn-icon btn-icon--fullscreen" aria-label="Fullscreen" title="Expand to fullscreen"></button>
            <button id="rmeStartPolyBtn" class="admin-action-button btn btn-secondary btn-icon btn-icon--add" aria-label="Add New Area" title="Click to start drawing a new clickable area"></button>
            <button id="rmeFinishPolyBtn" class="admin-action-button btn hidden btn-icon btn-icon--save" aria-label="Finish" title="Finish"></button>
            <button id="rmeUndoPointBtn" class="admin-action-button btn hidden btn-icon btn-icon--undo" aria-label="Undo" title="Undo"></button>
            <button id="rmeClearBtn" class="admin-action-button btn btn-danger btn-icon btn-icon--delete" aria-label="Clear All Areas" title="Remove all areas from the map"></button>
          </div>
        </section>

        <section id="rmeSavedMapsSection" class="rme-panel">
          <h2 class="rme-panel-title">Saved Maps</h2>
          <div class="flex items-center justify-between mb-2">
            <p class="rme-panel-subtitle">Apply or delete saved maps for the selected room.</p>
            <button id="rmeLoadMapsBtn" class="admin-action-button btn btn-xs btn-icon btn-icon--refresh" aria-label="Reload" title="Reload"></button>
          </div>
          <div id="rmeMapsList" class="border rounded overflow-hidden"></div>
        </section>

        <section id="rmeSnapGridSection" class="rme-panel">
          <h2 class="rme-panel-title">Snap Grid</h2>
          <div class="rme-field-group">
            <span class="rme-label">Preset</span>
            <div class="rme-preset-grid">
              <button class="admin-action-button btn btn-xs" data-snap="5">5px</button>
              <button class="admin-action-button btn btn-xs" data-snap="10">10px</button>
              <button class="admin-action-button btn btn-xs" data-snap="20">20px</button>
              <button class="admin-action-button btn btn-xs" data-snap="40">40px</button>
            </div>
          </div>
          <div class="rme-field-group">
            <label for="rmeGridColor" class="rme-label">Grid Color</label>
            <input type="color" id="rmeGridColor" class="rme-color-input" value="#e5e7eb">
          </div>
        </section>

        <section id="rmeAreasSection" class="rme-panel">
          <h2 class="rme-panel-title">Clickable Areas</h2>
          <div id="rmeAreaList" class="rme-area-list"></div>
        </section>

        <section id="rmeCoordsSection" class="rme-panel">
          <h2 class="rme-panel-title">Coordinates JSON</h2>
          <textarea id="rmeCoords" class="rme-textarea" placeholder='{"polygons":[{"points":[[x,y],[x,y],...]}]}'></textarea>
        </section>
      </aside>

      <section class="rme-main">
        <div class="rme-panel rme-canvas-panel">
          <div class="rme-panel-heading">
            <div id="rmeMessage" class="rme-message"></div>
            <h2 class="rme-panel-title">Interactive Map</h2>
            <p class="rme-panel-subtitle">Drag handles to resize areas, or use Add New Area to place additional regions.</p>
          </div>
          <div id="rmeFrame" class="rme-frame">
            <div id="rmeCanvas" class="rme-canvas">
              <img id="rmeBgImg" class="rme-bg" alt="Room background">
              <svg id="rmeSvg" xmlns="http://www.w3.org/2000/svg" class="rme-svg"></svg>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
<?php if ($is_modal_context): ?>
<?php /* embed resets handled by body[data-embed] utilities */ ?>
<?php endif; ?>
<?php if ($__wf_included_layout) {
    include dirname(__DIR__, 2) . '/partials/footer.php';
} ?>
<?php echo vite_entry('src/entries/admin-room-map-editor.js'); ?>

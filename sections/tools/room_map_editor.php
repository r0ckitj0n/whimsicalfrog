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
?>
<div class="rme-app" data-version="redesign-v2">
  <div class="rme-card">
    <header class="rme-header">
      <!-- Header kept minimal to free vertical space; title moved to intro panel -->
    </header>

    <div class="rme-layout">
      <aside class="rme-sidebar">
        <section id="rmeIntroSection" class="rme-panel">
          <h2 class="rme-panel-title">🗺️ Room Map Editor <span class="rme-title-badge">[NEW DESIGN]</span></h2>
          <p class="rme-panel-subtitle">Select a room, load its map, and drag the area boxes to adjust clickable regions.</p>
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
            <button id="rmeLoadActiveBtn" class="btn btn-secondary" title="Load the currently active map for this room">📥 Load Map</button>
            <button id="rmeSaveMapBtn" class="btn btn-primary" title="Save your changes as a new map">💾 Save Map</button>
            <button id="rmeFullscreenBtn" class="btn btn-info" title="Expand to fullscreen">⛶ Fullscreen</button>
            <button id="rmeStartPolyBtn" class="btn btn-secondary" title="Click to start drawing a new clickable area">➕ Add New Area</button>
            <button id="rmeFinishPolyBtn" class="btn hidden">Finish Polygon</button>
            <button id="rmeUndoPointBtn" class="btn hidden">Undo Point</button>
            <button id="rmeClearBtn" class="btn btn-danger" title="Remove all areas from the map">🗑️ Clear All Areas</button>
          </div>
        </section>

        <section id="rmeSnapGridSection" class="rme-panel">
          <h2 class="rme-panel-title">Snap Grid</h2>
          <div class="rme-field-group">
            <span class="rme-label">Preset</span>
            <div class="rme-preset-grid">
              <button class="btn btn-xs" data-snap="5">5px</button>
              <button class="btn btn-xs" data-snap="10">10px</button>
              <button class="btn btn-xs" data-snap="20">20px</button>
              <button class="btn btn-xs" data-snap="40">40px</button>
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
<style>
  body { margin: 0; padding: 0; background: #fff; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
  .bg-gray-100 { background: #fff !important; min-height: auto !important; }
  .container { max-width: none !important; margin: 0 !important; padding: 0 !important; }
  .shadow, .shadow-lg, .shadow-md { box-shadow: none !important; }
  h1 { font-size: 20px !important; margin-bottom: 16px !important; }
</style>
<?php endif; ?>
<?php if ($__wf_included_layout) {
    include dirname(__DIR__, 2) . '/partials/footer.php';
} ?>
<?php echo vite_entry('src/entries/admin-room-map-editor.js'); ?>

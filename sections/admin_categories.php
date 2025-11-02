<?php
// sections/admin_categories.php — Primary implementation for Category Management

// Bootstrap dependencies and enforce authentication BEFORE any output
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/auth_helper.php';

AuthHelper::requireAdmin();

// Detect modal context
$isModal = isset($_GET['modal']) && $_GET['modal'] == '1';
if (!$isModal) {
    // Check if loaded via iframe from admin settings (referrer detection)
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $isModal = strpos($referrer, 'admin_settings') !== false || strpos($referrer, 'admin/') !== false;
}

if ($isModal) {
    // Modal context - load minimal header for CSS/JS only
    require_once dirname(__DIR__) . '/partials/modal_header.php';
    // Embed layout/styles handled via body[data-embed] and admin utilities (Vite CSS)
} else {
    // Full page context - load complete admin layout
    require_once dirname(__DIR__) . '/partials/modal_header.php';
    
    // Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
    if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
        $page = 'admin';
        include dirname(__DIR__) . '/partials/header.php';
        if (!function_exists('__wf_admin_categories_footer_shutdown')) {
            function __wf_admin_categories_footer_shutdown()
            {
                @include __DIR__ . '/../partials/footer.php';
            }
        }
        register_shutdown_function('__wf_admin_categories_footer_shutdown');
    }

    // Always include admin navbar on categories page, even when accessed directly
    $section = 'categories';
    include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';
}

try {
    // Fetch categories from items
    $categoriesFromItems = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
    $categories = array_column($categoriesFromItems, 'category');

    // Fetch canonical categories for dropdowns
    $canonicalCategories = Database::queryAll("SELECT id, name FROM categories ORDER BY name");
    // Build a lookup by name for ID resolution when rendering rows
    $canonicalByName = [];
    if (!empty($canonicalCategories)) {
        foreach ($canonicalCategories as $row) {
            $nm = trim((string)($row['name'] ?? ''));
            if ($nm !== '') {
                $canonicalByName[$nm] = (int)($row['id'] ?? 0);
            }
        }
    }
    // PATCH_POPULATE_CATEGORIES: merge canonical + item-derived
    $categories = [];
    if (!empty($canonicalCategories)) {
        foreach ($canonicalCategories as $c) {
            $n = trim((string)($c['name'] ?? ''));
            if ($n !== '') {
                $categories[$n] = $n;
            }
        }
    }
    if (!empty($categoriesFromItems)) {
        foreach ($categoriesFromItems as $item) {
            $n = trim((string)($item['category'] ?? ''));
            if ($n !== '') {
                $categories[$n] = $n;
            }
        }
    }
    $categories = array_values($categories);
    natcasesort($categories);
    $categories = array_values($categories);

    // Fetch all SKU rules and create a map
    $sku_rules_raw = Database::queryAll("SELECT category_name, sku_prefix FROM sku_rules");
    $sku_map = array_column($sku_rules_raw, 'sku_prefix', 'category_name');

} catch (Exception $e) {
    Logger::error('Categories/SKU loading failed', ['error' => $e->getMessage()]);
    $categories = [];
    $canonicalCategories = [];
    $sku_map = [];
}

// SKU code generation from database map
function get_sku_code($cat, $map)
{
    return $map[$cat] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $cat), 0, 2));
}

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

?>
<div id="categoryManagementRoot" class="p-4">
    <?php if (!$isModal): ?>
    <h1 class="admin-title">Category Management</h1>
    <?php endif; ?>

     <!-- Tabs Navigation -->
    <div class="admin-card my-2">
      <div class="admin-tablist" role="tablist" aria-label="Category Management Tabs">
        <button type="button" role="tab" id="tabBtnOverview" class="btn btn-primary tab-outline" aria-selected="true" aria-controls="tabPanelOverview">Overview</button>
        <button type="button" role="tab" id="tabBtnCategories" class="btn tab-outline" aria-selected="false" aria-controls="tabPanelCategories">Categories</button>
        <button type="button" role="tab" id="tabBtnAssignments" class="btn tab-outline" aria-selected="false" aria-controls="tabPanelAssignments">Assignments</button>
        <button type="button" role="tab" id="tabBtnSkuRules" class="btn tab-outline" aria-selected="false" aria-controls="tabPanelSkuRules">SKU Rules</button>
      </div>
    </div>

    <!-- Tab Panels -->
    <div id="tabPanelOverview" role="tabpanel" aria-labelledby="tabBtnOverview">
      <div class="admin-card">
        <h3 class="admin-card-title">Per-Room Overview</h3>
        <div id="rcOverviewContainer" class="space-y-2">
          <div class="text-gray-600 text-sm">Loading overview…</div>
        </div>
      </div>
    </div>

    <div id="tabPanelCategories" role="tabpanel" aria-labelledby="tabBtnCategories" class="hidden">
    
    <?php if ($message): ?>
        <div class="admin-alert <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Categories List -->
    <div class="admin-card">
        <?php if (empty($categories)): ?>
            <div class="admin-empty-state">
                <div class="empty-icon">📂</div>
                <div class="empty-title">No categories found</div>
                <div class="empty-subtitle">Add your first category below to get started.</div>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>SKU Code</th>
                        <th>Example SKU</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="categoryTableBody">
                    <?php foreach ($categories as $cat):
                        $code = get_sku_code($cat, $sku_map);
                        $exampleSku = "WF-{$code}-001";
                        ?>
                        <tr data-category="<?= htmlspecialchars($cat) ?>" data-category-id="<?= isset($canonicalByName[$cat]) ? (int)$canonicalByName[$cat] : 0 ?>">
                            <td class="editable-cell" data-field="category_name" data-original-value="<?= htmlspecialchars($cat) ?>">
                                <span class="cell-text"><?= htmlspecialchars($cat) ?></span>
                            </td>
                            <td class="editable-cell" data-field="sku_code" data-original-value="<?= htmlspecialchars($code) ?>">
                                <span class="cell-text code-badge"><?= htmlspecialchars($code) ?></span>
                            </td>
                            <td><span class="code-badge"><?= htmlspecialchars($exampleSku) ?></span></td>
                            <td>
                                <button class="btn btn-danger btn-sm btn-icon btn-icon--delete delete-category-btn" 
                                        data-category="<?= htmlspecialchars($cat) ?>" aria-label="Delete Category" title="Delete Category"></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Add Category Form (moved below list) -->
    <div class="admin-card">
        <form id="addCategoryForm" class="admin-form-inline assignment-form">
            <button type="submit" class="btn btn-primary">Add Category</button>
            <input type="text" id="newCategory" name="newCategory" 
                   placeholder="Enter category name..." class="form-input assignment-form__select" required>
        </form>
    </div>

    </div> <!-- end of tabPanelCategories -->

    <!-- Assignments Panel (initially hidden) -->
    <div id="tabPanelAssignments" role="tabpanel" aria-labelledby="tabBtnAssignments" class="hidden">
      <div class="admin-card">
        <h3 class="admin-card-title">Room-Category Assignments</h3>
        <div id="rcAssignmentsContainer" class="admin-table-wrapper">
          <div class="text-gray-600 text-sm">Loading assignments…</div>
        </div>
      </div>

      <div class="admin-card">
        <form id="addAssignmentForm" class="admin-form-inline assignment-form">
          <button type="submit" class="btn btn-primary btn-icon btn-icon--add" aria-label="Add Assignment" title="Add Assignment"></button>
          <select id="roomNumber" name="roomNumber" class="form-input assignment-form__select">
            <option value="1">Room 1</option>
            <option value="2">Room 2</option>
            <option value="3">Room 3</option>
            <option value="4">Room 4</option>
            <option value="5">Room 5</option>
          </select>
          <select id="categoryId" name="categoryId" class="form-input assignment-form__select">
            <?php foreach ($canonicalCategories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <div id="tabPanelSkuRules" role="tabpanel" aria-labelledby="tabBtnSkuRules" class="hidden">
      <div class="admin-card">
        <h3 class="admin-card-title">SKU Naming Rules</h3>
        <div id="skuRulesContainer" class="admin-table-wrapper">
          <div class="text-gray-600 text-sm">Loading SKU rules…</div>
        </div>
      </div>
      <div class="admin-card">
        <form id="addSkuRuleForm" class="admin-form-inline assignment-form">
          <button type="submit" class="btn btn-primary btn-icon btn-icon--add" aria-label="Add SKU Rule" title="Add SKU Rule"></button>
          <input type="text" id="newSkuCategoryName" placeholder="Category Name" class="form-input assignment-form__select" required>
          <input type="text" id="newSkuPrefix" placeholder="SKU Prefix" class="form-input assignment-form__select" required>
        </form>
      </div>
    </div>

    <script>
      (function() {
        const tabs = [
          {btn: 'tabBtnOverview', panel: 'tabPanelOverview'},
          {btn: 'tabBtnCategories', panel: 'tabPanelCategories'},
          {btn: 'tabBtnAssignments', panel: 'tabPanelAssignments'},
          {btn: 'tabBtnSkuRules', panel: 'tabPanelSkuRules'}
        ];
        async function brandedConfirm(message, options){
          try {
            if (window.parent && typeof window.parent.showConfirmationModal === 'function') {
              return await window.parent.showConfirmationModal({
                title: (options && options.title) || 'Please confirm',
                message,
                confirmText: (options && options.confirmText) || 'Confirm',
                confirmStyle: (options && options.confirmStyle) || 'confirm',
                icon: (options && options.icon) || '⚠️',
                iconType: (options && options.iconType) || 'warning'
              });
            }
            if (typeof window.showConfirmationModal === 'function') {
              return await window.showConfirmationModal({
                title: (options && options.title) || 'Please confirm',
                message,
                confirmText: (options && options.confirmText) || 'Confirm',
                confirmStyle: (options && options.confirmStyle) || 'confirm',
                icon: (options && options.icon) || '⚠️',
                iconType: (options && options.iconType) || 'warning'
              });
            }
          } catch(_) {}
          try {
            if (window.parent && window.parent.wfNotifications && typeof window.parent.wfNotifications.show === 'function') { window.parent.wfNotifications.show('Confirmation UI unavailable. Action canceled.', 'error'); }
            else if (window.parent && typeof window.parent.showNotification === 'function') { window.parent.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); }
            else if (typeof window.showNotification === 'function') { window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); }
          } catch(_) {}
          return false;
        }
        function notify(msg, type){
          try {
            if (window.parent && window.parent.wfNotifications && typeof window.parent.wfNotifications.show === 'function') { window.parent.wfNotifications.show(msg, type || 'info'); return; }
            if (window.parent && typeof window.parent.showNotification === 'function') { window.parent.showNotification(msg, type || 'info'); return; }
            if (typeof window.showNotification === 'function') { window.showNotification(msg, type || 'info'); return; }
            if (type === 'error' && typeof window.showError === 'function') { window.showError(msg); return; }
            if (type === 'success' && typeof window.showSuccess === 'function') { window.showSuccess(msg); return; }
          } catch(_) {}
          try { alert(msg); } catch(_) {}
        }
        // Canonical categories for inline editing in Assignments
        const CANONICAL_CATEGORIES = <?php
          $___json = json_encode($canonicalCategories, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
          echo ($___json === false || $___json === null) ? '[]' : $___json;
        ?>;
        const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
        function showPanel(key) {
          tabs.forEach(t => {
            const btn = document.getElementById(t.btn);
            const panel = document.getElementById(t.panel);
            const active = (t.panel === key);
            if (btn) btn.setAttribute('aria-selected', active ? 'true' : 'false');
            if (panel) panel.classList.toggle('hidden', !active);
            if (btn) {
              btn.classList.toggle('btn-primary', active);
            }
          });

        (function(){
          const form = document.getElementById('addCategoryForm');
          if (!form) return;
          form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const nameEl = document.getElementById('newCategory');
            const name = (nameEl?.value || '').trim();
            if (!name) return;
            try {
              const res = await fetchJSON('/api/category_manager.php', { method: 'POST', body: { action: 'add_category', name } });
              if (res?.success) { window.location.reload(); }
              else { notify('Add failed', 'error'); }
            } catch (err) { notify('Error: ' + err.message, 'error'); }
          });
        })();
          if (key === 'tabPanelOverview') loadOverview();
          if (key === 'tabPanelAssignments') loadAssignments();
          if (key === 'tabPanelSkuRules') loadSkuRules();
        }
        tabs.forEach((t, index) => {
          const btn = document.getElementById(t.btn);
          if (btn) btn.addEventListener('click', () => showPanel(t.panel));
        });
        // Keyboard navigation (roving tabindex) for tabs
        (function(){
          const tablist = document.querySelector('.admin-tablist[role="tablist"]');
          const btns = tabs.map(t => document.getElementById(t.btn)).filter(Boolean);
          const setTabIndex = (activeIndex) => {
            btns.forEach((b, i) => b.setAttribute('tabindex', i === activeIndex ? '0' : '-1'));
          };
          let activeIndex = 0;
          setTabIndex(activeIndex);
          // Keep tabindex in sync on mouse click
          btns.forEach((b, i) => b.addEventListener('click', () => { activeIndex = i; setTabIndex(activeIndex); }));
          if (tablist) {
            tablist.addEventListener('keydown', (e) => {
              const key = e.key;
              const max = btns.length - 1;
              if (key === 'ArrowRight') { e.preventDefault(); activeIndex = (activeIndex + 1) % btns.length; setTabIndex(activeIndex); btns[activeIndex].focus(); showPanel(btns[activeIndex].id.replace('Btn','Panel')); }
              else if (key === 'ArrowLeft') { e.preventDefault(); activeIndex = (activeIndex - 1 + btns.length) % btns.length; setTabIndex(activeIndex); btns[activeIndex].focus(); showPanel(btns[activeIndex].id.replace('Btn','Panel')); }
              else if (key === 'Home') { e.preventDefault(); activeIndex = 0; setTabIndex(activeIndex); btns[activeIndex].focus(); showPanel(btns[activeIndex].id.replace('Btn','Panel')); }
              else if (key === 'End') { e.preventDefault(); activeIndex = max; setTabIndex(activeIndex); btns[activeIndex].focus(); showPanel(btns[activeIndex].id.replace('Btn','Panel')); }
              else if (key === 'Enter' || key === ' ') { e.preventDefault(); showPanel(btns[activeIndex].id.replace('Btn','Panel')); }
            });
          }
        })();
        showPanel('tabPanelOverview'); // default

        async function fetchJSON(url, options) {
          const opts = options || {};
          const headers = { 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(opts.headers || {}) };
          const init = { credentials: 'same-origin', headers, ...opts };
          if (opts && opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
            init.headers = { ...init.headers, 'Content-Type': 'application/json' };
            init.body = JSON.stringify(opts.body);
          }
          const res = await fetch(url, init);
          const raw = await res.text().catch(() => '');
          if (!res.ok) {
            let errorMsg = `HTTP ${res.status}`;
            try { const errJson = JSON.parse(raw); errorMsg = errJson.message || errorMsg; } catch(_) { errorMsg = raw || errorMsg; }
            throw new Error(errorMsg);
          }
          const trimmed = raw.trim();
          // Try direct parse first
          try { return JSON.parse(trimmed); } catch(_e) {}
          // Fallback: handle banners/noise by extracting trailing JSON starting at last { or [
          const iBrace = trimmed.lastIndexOf('{');
          const iBracket = trimmed.lastIndexOf('[');
          const start = Math.max(iBrace, iBracket);
          if (start >= 0) {
            try { return JSON.parse(trimmed.slice(start)); } catch(e) {
              console.warn('[Categories] Invalid JSON payload', { snippet: trimmed.slice(0, 160) });
            }
          }
          throw new Error('Invalid JSON response');
        }

        async function loadOverview() {
          const el = document.getElementById('rcOverviewContainer');
          if (!el) return;
          el.innerHTML = '<div class="text-gray-600 text-sm">Loading overview…</div>';
          try {
            const data = await fetchJSON('/api/room_category_assignments.php?action=get_summary');
            if (!data?.success) throw new Error(data?.message || 'Failed to load summary');
            if (!data.summary?.length) {
              el.innerHTML = '<div class="text-gray-600 text-sm">No room-category assignments found.</div>';
              return;
            }
            el.innerHTML = data.summary.map(row => {
              const primary = row.primary_category ? ` <span class="code-badge">Primary: ${row.primary_category}</span>` : '';
              return `<div class="admin-info-card"><b>Room ${row.room_number}${row.room_name ? ` (${row.room_name})` : ''}</b>: ${row.categories || '—'}${primary}</div>`;
            }).join('');
          } catch (e) {
            el.innerHTML = `<div class="text-danger">Error loading overview: ${e.message}</div>`;
          }
        }

        async function loadAssignments() {
          const el = document.getElementById('rcAssignmentsContainer');
          if (!el) return;
          el.innerHTML = '<div class="text-gray-600 text-sm">Loading assignments…</div>';
          try {
            const data = await fetchJSON('/api/room_category_assignments.php?action=get_all');
            if (!data?.success) throw new Error(data?.message || 'Failed to load assignments');
            if (!data.assignments?.length) {
              el.innerHTML = '<div class="text-gray-600 text-sm">No assignments found.</div>';
              return;
            }
            // Bulk actions toolbar
            const toolbar = document.createElement('div');
            toolbar.className = 'admin-form-inline';
            toolbar.innerHTML = `
              <div class="flex-row">
                <label class="flex-row"><input type="checkbox" id="selectAllAssignments"> <span>Select All</span></label>
                <select id="bulkMoveRoom" class="form-input">
                  <option value="">Move to Room…</option>
                  <option value="1">Room 1</option>
                  <option value="2">Room 2</option>
                  <option value="3">Room 3</option>
                  <option value="4">Room 4</option>
                  <option value="5">Room 5</option>
                </select>
                <button type="button" id="bulkMoveBtn" class="btn btn-icon btn-icon--link" aria-label="Move Selected" title="Move Selected"></button>
                <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-icon btn-icon--delete" aria-label="Delete Selected" title="Delete Selected"></button>
              </div>
            `;

            const table = document.createElement('table');
            table.className = 'admin-table';
            table.innerHTML = `
              <thead>
                <tr>
                  <th class="col-36px"><input type="checkbox" id="selectAllAssignmentsHeader"></th>
                  <th>Room</th>
                  <th>Category</th>
                  <th>Order</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="assignmentsTbody">
                ${data.assignments.map(a => `
                  <tr data-id="${a.id}" data-room="${a.room_number}" data-category-id="${a.category_id}" data-is-primary="${a.is_primary}" data-order="${a.display_order}" draggable="true">
                    <td><input type="checkbox" class="assignment-select" data-id="${a.id}"></td>
                    <td>${a.room_number}</td>
                    <td>${escapeHtml(a.category_name)}${Number(a.is_primary) === 1 ? ' <span class="code-badge">Primary</span>' : ''}</td>
                    <td>${a.display_order}</td>
                    <td>
                      <button class="btn btn-secondary btn-sm btn-icon btn-icon--edit" data-action="edit-assignment" data-id="${a.id}" aria-label="Edit" title="Edit"></button>
                      <button class="btn btn-sm btn-icon btn-icon--up" data-action="order-up" data-id="${a.id}" data-order="${a.display_order}" aria-label="Move Up" title="Move Up"></button>
                      <button class="btn btn-sm btn-icon btn-icon--down" data-action="order-down" data-id="${a.id}" data-order="${a.display_order}" aria-label="Move Down" title="Move Down"></button>
                      <button class="btn btn-primary btn-sm btn-icon btn-icon--sparkles" data-action="set-primary" data-room="${a.room_number}" data-category-id="${a.category_id}" aria-label="Set Primary" title="Set Primary"></button>
                      <button class="btn btn-danger btn-sm btn-icon btn-icon--delete" data-action="delete-assignment" data-id="${a.id}" aria-label="Delete" title="Delete"></button>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            `;
            el.innerHTML = '';
            el.appendChild(toolbar);
            el.appendChild(table);
            try { if (window.WF_Icons && typeof window.WF_Icons.applyIcons === 'function') { window.WF_Icons.applyIcons(el); } } catch(_) {}

            // Drag & Drop within same room
            const tbody = table.querySelector('#assignmentsTbody');
            let dragRow = null;
            tbody.addEventListener('dragstart', (e) => {
              const row = e.target.closest('tr');
              if (!row) return;
              dragRow = row;
              row.classList.add('dragging');
              e.dataTransfer.effectAllowed = 'move';
            });
            tbody.addEventListener('dragend', (e) => {
              const row = e.target.closest('tr');
              if (row) row.classList.remove('dragging');
              dragRow = null;
            });
            tbody.addEventListener('dragover', (e) => {
              const overRow = e.target.closest('tr');
              if (!dragRow || !overRow) return;
              if (overRow.dataset.room !== dragRow.dataset.room) return; // only within same room
              e.preventDefault();
              const rect = overRow.getBoundingClientRect();
              const after = (e.clientY - rect.top) > rect.height / 2;
              tbody.querySelectorAll('tr.drag-over').forEach(r => r.classList.remove('drag-over'));
              if (after) {
                overRow.after(dragRow);
              } else {
                overRow.before(dragRow);
              }
              overRow.classList.add('drag-over');
            });
            tbody.addEventListener('drop', async (e) => {
              // Compute new orders within the room of the dropped row
              if (!dragRow) return;
              const room = dragRow.dataset.room;
              const rows = Array.from(tbody.querySelectorAll(`tr[data-room="${room}"]`));
              const assignments = rows.map((r, idx) => ({ id: Number(r.dataset.id), display_order: idx }));
              try {
                const res = await fetchJSON('/api/room_category_assignments.php', {
                  method: 'PUT',
                  body: { action: 'update_order', assignments }
                });
                // Reload to reflect stable order numbers
                if (res?.success || res?.message) loadAssignments();
              } catch (err) { console.warn('DnD update failed', err); }
            });

            // Bulk actions handlers
            const getSelectedIds = () => Array.from(el.querySelectorAll('.assignment-select:checked')).map(cb => Number(cb.dataset.id));
            const headerChk = el.querySelector('#selectAllAssignmentsHeader');
            const selectAll = el.querySelector('#selectAllAssignments');
            const bulkMoveSel = el.querySelector('#bulkMoveRoom');
            const bulkMoveBtn = el.querySelector('#bulkMoveBtn');
            const bulkDeleteBtn = el.querySelector('#bulkDeleteBtn');
            const syncHeader = () => {
              const all = el.querySelectorAll('.assignment-select');
              const checked = el.querySelectorAll('.assignment-select:checked');
              if (headerChk) headerChk.checked = all.length && checked.length === all.length;
              if (selectAll) selectAll.checked = headerChk ? headerChk.checked : false;
            };
            if (headerChk) headerChk.addEventListener('change', () => {
              el.querySelectorAll('.assignment-select').forEach(cb => { cb.checked = headerChk.checked; });
              if (selectAll) selectAll.checked = headerChk.checked;
            });
            if (selectAll) selectAll.addEventListener('change', () => {
              const val = selectAll.checked;
              if (headerChk) headerChk.checked = val;
              el.querySelectorAll('.assignment-select').forEach(cb => { cb.checked = val; });
            });
            el.addEventListener('change', (ev) => {
              if (ev.target && ev.target.classList && ev.target.classList.contains('assignment-select')) syncHeader();
            });
            if (bulkDeleteBtn) bulkDeleteBtn.addEventListener('click', async () => {
              const ids = getSelectedIds();
              if (!ids.length) return notify('Select one or more assignments', 'error');
              if (!await brandedConfirm('Delete selected assignments?', { confirmText: 'Delete', confirmStyle: 'danger', iconType: 'danger' })) return;
              try {
                for (const id of ids) {
                  await fetchJSON('/api/room_category_assignments.php', { method: 'DELETE', body: { assignment_id: id } });
                }
                loadAssignments();
              } catch (err) { notify('Error deleting: ' + err.message, 'error'); }
            });
            if (bulkMoveBtn) bulkMoveBtn.addEventListener('click', async () => {
              const ids = getSelectedIds();
              const room = Number(bulkMoveSel?.value || 0);
              if (!ids.length) return notify('Select one or more assignments', 'error');
              if (!room) return notify('Choose a target room', 'error');
              try {
                for (const id of ids) {
                  await fetchJSON('/api/room_category_assignments.php', { method: 'PUT', body: { action: 'update_assignment', id, room_number: room } });
                }
                loadAssignments();
              } catch (err) { notify('Error moving: ' + err.message, 'error'); }
            });

            document.getElementById('addAssignmentForm').addEventListener('submit', async (e) => {
              e.preventDefault();
              const roomNumber = document.getElementById('roomNumber').value;
              const categoryId = document.getElementById('categoryId').value;

              try {
                const result = await fetchJSON('/api/room_category_assignments.php', {
                    method: 'POST',
                    body: { action: 'add', room_number: roomNumber, category_id: categoryId }
                });
                if (result.success) {
                  loadAssignments();
                } else {
                  notify('Failed to add assignment: ' + result.message, 'error');
                }
              } catch (err) {
                notify('An error occurred: ' + err.message, 'error');
              }
            });

            table.addEventListener('click', async (e) => {
              if (!e.target || !e.target.dataset) return;
              const action = e.target.dataset.action;
              if (action === 'delete-assignment') {
                const id = e.target.dataset.id;
                if (await brandedConfirm('Are you sure you want to delete this assignment?', { confirmText: 'Delete', confirmStyle: 'danger', iconType: 'danger' })) {
                  try {
                    const result = await fetchJSON('/api/room_category_assignments.php', {
                        method: 'DELETE',
                        body: { assignment_id: id }
                    });
                    if (result.success) {
                      loadAssignments();
                    } else {
                      notify('Failed to delete assignment: ' + result.message, 'error');
                    }
                  } catch (err) {
                    notify('An error occurred: ' + err.message, 'error');
                  }
                }
              } else if (action === 'set-primary') {
                const room = e.target.dataset.room;
                const categoryId = e.target.dataset.categoryId;
                try {
                  const res = await fetchJSON('/api/room_category_assignments.php', { method: 'POST', body: { action: 'set_primary', room_number: room, category_id: categoryId } });
                  if (res?.success || res?.message) loadAssignments();
                } catch (err) { notify('Error setting primary: ' + err.message, 'error'); }
              } else if (action === 'order-up' || action === 'order-down') {
                const id = e.target.dataset.id;
                const current = Number(e.target.dataset.order) || 0;
                const next = action === 'order-up' ? current - 1 : current + 1;
                try {
                  const res = await fetchJSON('/api/room_category_assignments.php', { method: 'PUT', body: { action: 'update_single_order', assignment_id: id, display_order: next } });
                  if (res?.success || res?.message) loadAssignments();
                } catch (err) { notify('Error updating order: ' + err.message, 'error'); }
              } else if (action === 'edit-assignment') {
                const row = e.target.closest('tr');
                if (!row) return;
                const id = Number(row.dataset.id);
                const currRoom = Number(row.dataset.room);
                const currCat = Number(row.dataset.categoryId);
                const currOrder = Number(row.dataset.order);
                const currPrimary = Number(row.dataset.isPrimary) === 1;
                // Build inline editors
                const cells = row.children;
                // Room select (1..5)
                const roomTd = cells[0];
                const roomSel = document.createElement('select');
                roomSel.className = 'form-input';
                [1,2,3,4,5].forEach(n => {
                  const opt = document.createElement('option');
                  opt.value = String(n);
                  opt.textContent = 'Room ' + n;
                  if (n === currRoom) opt.selected = true;
                  roomSel.appendChild(opt);
                });
                roomTd.innerHTML = '';
                roomTd.appendChild(roomSel);

                // Category select
                const catTd = cells[1];
                const catSel = document.createElement('select');
                catSel.className = 'form-input';
                CANONICAL_CATEGORIES.forEach(c => {
                  const opt = document.createElement('option');
                  opt.value = String(c.id);
                  opt.textContent = c.name;
                  if (Number(c.id) === currCat) opt.selected = true;
                  catSel.appendChild(opt);
                });
                catTd.innerHTML = '';
                catTd.appendChild(catSel);

                // Order input
                const orderTd = cells[2];
                const orderInput = document.createElement('input');
                orderInput.type = 'number';
                orderInput.className = 'form-input';
                orderInput.value = String(currOrder);
                orderTd.innerHTML = '';
                orderTd.appendChild(orderInput);

                // Actions: Primary checkbox + Save/Cancel
                const actionsTd = cells[3];
                const primaryWrap = document.createElement('label');
                const primaryCb = document.createElement('input');
                primaryCb.type = 'checkbox';
                primaryCb.checked = currPrimary;
                primaryWrap.appendChild(primaryCb);
                primaryWrap.appendChild(document.createTextNode(' Primary'));
                const saveBtn = document.createElement('button');
                saveBtn.className = 'btn btn-primary btn-sm ml-2 btn-icon btn-icon--save';
                saveBtn.setAttribute('aria-label', 'Save');
                saveBtn.title = 'Save';
                saveBtn.setAttribute('data-action', 'save-assignment');
                saveBtn.setAttribute('data-id', String(id));
                const cancelBtn = document.createElement('button');
                cancelBtn.className = 'btn btn-secondary btn-sm ml-1';
                cancelBtn.textContent = 'Cancel';
                cancelBtn.setAttribute('aria-label', 'Cancel');
                cancelBtn.title = 'Cancel';
                cancelBtn.setAttribute('data-action', 'cancel-edit');
                actionsTd.innerHTML = '';
                actionsTd.appendChild(primaryWrap);
                actionsTd.appendChild(saveBtn);
                actionsTd.appendChild(cancelBtn);
                

                // Save handler (delegated)
              } else if (action === 'save-assignment') {
                const row = e.target.closest('tr');
                if (!row) return;
                const id = Number(row.dataset.id);
                const roomSel = row.children[0].querySelector('select');
                const catSel = row.children[1].querySelector('select');
                const orderInput = row.children[2].querySelector('input');
                const primaryCb = row.children[3].querySelector('input[type="checkbox"]');
                const payload = {
                  action: 'update_assignment',
                  id,
                  room_number: Number(roomSel.value),
                  category_id: Number(catSel.value),
                  display_order: Number(orderInput.value),
                  is_primary: primaryCb.checked ? 1 : 0
                };
                try {
                  const res = await fetchJSON('/api/room_category_assignments.php', { method: 'PUT', body: payload });
                  if (res?.success || res?.message) loadAssignments();
                } catch (err) { notify('Error saving: ' + err.message, 'error'); }
              } else if (action === 'cancel-edit') {
                loadAssignments();
              }
            });
          } catch (e) {
            el.innerHTML = `<div class="text-danger">Error loading assignments: ${e.message}</div>`;
          }
        }

        async function loadSkuRules() {
          const container = document.getElementById('skuRulesContainer');
          if (!container) return;
          container.innerHTML = '<div class="text-gray-600 text-sm">Loading SKU rules…</div>';
          try {
            const data = await fetchJSON('/api/sku_rules.php');
            if (!data?.success) throw new Error(data?.message || 'Failed to load SKU rules');

            if (!data.rules?.length) {
              container.innerHTML = '<div class="text-gray-600 text-sm">No SKU rules found.</div>';
              return;
            }

            const rows = data.rules.map((rule) => {
              const id = Number(rule.id);
              const rawName = typeof rule.category_name === 'string' ? rule.category_name : '';
              const rawPrefix = typeof rule.sku_prefix === 'string' ? rule.sku_prefix : '';
              const safeName = escapeHtml(rawName);
              const safePrefix = escapeHtml(rawPrefix);
              const exampleSku = rawPrefix ? `WF-${rawPrefix}-001` : '—';
              return `
                <tr data-rule-id="${id}">
                  <td class="editable-cell" data-field="category_name" data-original-value="${safeName}">
                    <span class="cell-text">${safeName || '—'}</span>
                  </td>
                  <td class="editable-cell" data-field="sku_prefix" data-original-value="${safePrefix}">
                    <span class="cell-text code-badge">${safePrefix || '—'}</span>
                  </td>
                  <td><span class="code-badge sku-example">${escapeHtml(exampleSku)}</span></td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm btn-icon btn-icon--delete" data-action="delete-sku-rule" aria-label="Delete Rule" title="Delete Rule"></button>
                  </td>
                </tr>
              `;
            }).join('');

            container.innerHTML = `
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Category Name</th>
                    <th>SKU Code</th>
                    <th>Example SKU</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="skuRulesTableBody">
                  ${rows}
                </tbody>
              </table>
            `;
            try { if (window.WF_Icons && typeof window.WF_Icons.applyIcons === 'function') { window.WF_Icons.applyIcons(container); } } catch(_) {}
          } catch (e) {
            container.innerHTML = `<div class="text-danger">Error loading SKU rules: ${escapeHtml(e.message)}</div>`;
          }
        }

        (function(){
          const tbodyEl = document.getElementById('categoryTableBody');
          if (!tbodyEl) return; // Categories table not rendered (empty list); skip binding
          tbodyEl.addEventListener('click', async function(e) {
            const targetCell = e.target.closest('.editable-cell');
            const delBtn = e.target.closest('.delete-category-btn');
            if (delBtn) {
                e.preventDefault();
                const row = delBtn.closest('tr');
                const id = Number(row?.getAttribute('data-category-id')) || 0;
                const name = row?.getAttribute('data-category') || '';
                if (!await brandedConfirm('Delete this category? This also removes related assignments and SKU rule.', { confirmText: 'Delete', confirmStyle: 'danger', iconType: 'danger' })) return;
                (async () => {
                    try {
                        const res = await fetchJSON('/api/category_manager.php', { method: 'POST', body: { action: 'delete_category', id, name } });
                        if (res?.success) { window.location.reload(); }
                        else { notify('Delete failed', 'error'); }
                    } catch (err) { notify('Error: ' + err.message, 'error'); }
                })();
                return;
            }
            if (!targetCell || targetCell.querySelector('input')) return; // Already in edit mode

            const field = targetCell.dataset.field;
            const originalValue = targetCell.dataset.originalValue;
            const category = targetCell.closest('tr').dataset.category;
            const textSpan = targetCell.querySelector('.cell-text');

            const input = document.createElement('input');
            input.type = 'text';
            input.value = originalValue;
            input.className = 'form-input';
            
            const saveBtn = document.createElement('button');
            saveBtn.textContent = 'Save';
            saveBtn.className = 'btn btn-primary btn-sm ml-2';

            const cancelBtn = document.createElement('button');
            cancelBtn.textContent = 'Cancel';
            cancelBtn.className = 'btn btn-secondary btn-sm ml-1';

            targetCell.innerHTML = '';
            targetCell.appendChild(input);
            targetCell.appendChild(saveBtn);
            targetCell.appendChild(cancelBtn);
            input.focus();

            const revertUI = () => {
                targetCell.innerHTML = '';
                targetCell.appendChild(textSpan);
            };

            cancelBtn.onclick = revertUI;

            saveBtn.onclick = async () => {
                const newValue = input.value.trim();
                if (newValue === '' || newValue === originalValue) {
                    revertUI();
                    return;
                }

                let payload = { action: '' };
                if (field === 'category_name') {
                    payload.action = 'update_category_name';
                    payload.old_name = category;
                    payload.new_name = newValue;
                } else if (field === 'sku_code') {
                    payload.action = 'update_sku_code';
                    payload.category_name = category;
                    payload.new_sku = newValue;
                }

                try {
                    const response = await fetchJSON('/api/category_manager.php', {
                        method: 'POST',
                        body: payload
                    });

                    if (response.success) {
                        // For category name change, we need to reload to see changes reflected everywhere
                        if (field === 'category_name') {
                            window.location.reload();
                        } else {
                            textSpan.textContent = newValue;
                            targetCell.dataset.originalValue = newValue;
                            revertUI();
                        }
                    } else {
                        notify('Update failed: ' + response.message, 'error');
                        revertUI();
                    }
                } catch (error) {
                    notify('An error occurred: ' + error.message, 'error');
                    revertUI();
                }
            };
          });
        })();

        const skuRulesContainerEl = document.getElementById('skuRulesContainer');
        if (skuRulesContainerEl) {
          skuRulesContainerEl.addEventListener('click', async (event) => {
            const deleteBtn = event.target.closest('[data-action="delete-sku-rule"]');
            if (deleteBtn) {
              event.preventDefault();
              const row = deleteBtn.closest('tr');
              const id = Number(row?.dataset.ruleId);
              if (!id) return;
              if (!await brandedConfirm('Delete this SKU rule?', { confirmText: 'Delete', confirmStyle: 'danger', iconType: 'danger' })) return;
              try {
                await fetchJSON('/api/sku_rules.php', { method: 'DELETE', body: { id } });
                loadSkuRules();
              } catch (err) {
                notify('Error deleting rule: ' + err.message, 'error');
              }
              return;
            }

            const cell = event.target.closest('.editable-cell');
            if (!cell || cell.querySelector('input')) return;
            const row = cell.closest('tr');
            if (!row) return;
            const textSpan = cell.querySelector('.cell-text');
            if (!textSpan) return;
            const field = cell.dataset.field;
            const originalVisible = textSpan.textContent === '—' ? '' : textSpan.textContent;

            const input = document.createElement('input');
            input.type = 'text';
            input.value = originalVisible;
            input.className = 'form-input';

            const saveBtn = document.createElement('button');
            saveBtn.type = 'button';
            saveBtn.className = 'btn btn-primary btn-sm ml-2 btn-icon btn-icon--save';
            saveBtn.setAttribute('aria-label', 'Save');
            saveBtn.title = 'Save';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-secondary btn-sm ml-1';
            cancelBtn.textContent = 'Cancel';
            cancelBtn.setAttribute('aria-label', 'Cancel');
            cancelBtn.title = 'Cancel';

            cell.innerHTML = '';
            cell.appendChild(input);
            cell.appendChild(saveBtn);
            cell.appendChild(cancelBtn);
            input.focus();

            const revert = () => {
              cell.innerHTML = '';
              cell.appendChild(textSpan);
            };

            cancelBtn.addEventListener('click', (ev) => {
              ev.preventDefault();
              revert();
            });

            saveBtn.addEventListener('click', async (ev) => {
              ev.preventDefault();
              const newValue = input.value.trim();
              if (newValue === originalVisible) {
                revert();
                return;
              }

              const id = Number(row.dataset.ruleId);
              if (!id) {
                revert();
                return;
              }

              const nameCell = row.querySelector('[data-field="category_name"] .cell-text');
              const prefixCell = row.querySelector('[data-field="sku_prefix"] .cell-text');
              let currentName = nameCell ? nameCell.textContent.trim() : '';
              let currentPrefix = prefixCell ? prefixCell.textContent.trim() : '';
              if (currentName === '—') currentName = '';
              if (currentPrefix === '—') currentPrefix = '';

              if (field === 'category_name') {
                currentName = newValue;
              } else {
                currentPrefix = newValue;
              }

              try {
                const res = await fetchJSON('/api/sku_rules.php', {
                  method: 'PUT',
                  body: { id, category_name: currentName, sku_prefix: currentPrefix }
                });
                if (!res?.success) throw new Error(res?.message || 'Update failed');

                textSpan.textContent = newValue || '—';
                cell.dataset.originalValue = newValue;
                if (field === 'sku_prefix') {
                  const exampleEl = row.querySelector('.sku-example');
                  if (exampleEl) {
                    exampleEl.textContent = newValue ? `WF-${newValue}-001` : '—';
                  }
                }
                revert();
              } catch (err) {
                notify('Error updating rule: ' + err.message, 'error');
                revert();
              }
            });
          });
        }

        const addSkuRuleForm = document.getElementById('addSkuRuleForm');
        if (addSkuRuleForm) {
          addSkuRuleForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const nameInput = document.getElementById('newSkuCategoryName');
            const prefixInput = document.getElementById('newSkuPrefix');
            const categoryName = (nameInput?.value || '').trim();
            const skuPrefix = (prefixInput?.value || '').trim();
            if (!categoryName || !skuPrefix) return;
            try {
              await fetchJSON('/api/sku_rules.php', { method: 'POST', body: { category_name: categoryName, sku_prefix: skuPrefix } });
              if (nameInput) nameInput.value = '';
              if (prefixInput) prefixInput.value = '';
              loadSkuRules();
            } catch (err) {
              notify('Error adding rule: ' + err.message, 'error');
            }
          });
        }
      })();
    </script>
    <script>
      // Notify parent of content height so the modal iframe can fit exactly
      (function(){
        try {
          if (window.parent && window.parent !== window) {
            function sendSize(){
              try {
                var h = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight, document.body.offsetHeight, document.documentElement.offsetHeight);
                window.parent.postMessage({ type: 'wf-iframe-size', key: 'categories', height: h }, '*');
              } catch(_) {}
            }
            // Initial and after load
            if (document.readyState === 'loading') {
              document.addEventListener('DOMContentLoaded', sendSize);
            } else { sendSize(); }
            window.addEventListener('load', sendSize);
            // Observe future layout changes
            if ('ResizeObserver' in window) {
              try { new ResizeObserver(sendSize).observe(document.body); } catch(_) {}
            }
            window.addEventListener('resize', sendSize);
          }
        } catch(_) {}
      })();
    </script>
</div>
</body>
</html>

<?php
// Admin Settings (JS-powered). Renders the wrapper the module expects and seeds minimal context.
// Guard auth if helper exists
if (function_exists('isLoggedIn') && !isLoggedIn()) {
    echo '<div class="text-center"><h1 class="text-2xl font-bold text-red-600">Please login to access settings</h1></div>';
    return;
}

// Current user for account prefill
$userData = function_exists('getCurrentUser') ? (getCurrentUser() ?? []) : [];
$uid = $userData['id'] ?? ($userData['userId'] ?? '');
$firstNamePrefill = $userData['firstName'] ?? ($userData['first_name'] ?? '');
$lastNamePrefill = $userData['lastName'] ?? ($userData['last_name'] ?? '');
$emailPrefill = $userData['email'] ?? '';

// Basic page title to match admin design
?>
<!-- WF: SETTINGS WRAPPER START -->
<div class="settings-page container mx-auto px-4 py-6" data-page="admin-settings" data-user-id="<?= htmlspecialchars((string)$uid) ?>">
  <noscript>
    <div class="admin-alert alert-warning">
      JavaScript is required to use the Settings page.
    </div>
  </noscript>

  <!-- Root containers the JS module can enhance -->
  <div id="adminSettingsRoot" class="admin-settings-root">
    <!-- Settings cards grid using legacy classes -->
    <div class="settings-grid">
      <!-- Content Management -->
      <section class="settings-section content-section card-theme-blue">
        <header class="section-header">
          <h3 class="section-title">Content Management</h3>
          <p class="section-description">Organize products, categories, and room content</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-dashboard-config" onclick="try{ if (showModal('dashboardConfigModal')) { populateDashboardFallback('dashboardConfigModal'); } }catch(e){} return false;">Dashboard Configuration</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-categories" onclick="try{ if (showModal('categoriesModal')) { populateCategories(); } }catch(e){} return false;">Categories</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-attributes" onclick="try{ if (showModal('attributesModal')) { populateAttributes(); } }catch(e){} return false;">Genders, Sizes, &amp; Colors</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-room-settings" onclick="return window.__openLegacyModal && window.__openLegacyModal('room-settings');">Room Settings</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-room-category-links" onclick="return window.__openLegacyModal && window.__openLegacyModal('room-category-links');">Room-Category Links</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-template-manager" onclick="return window.__openLegacyModal && window.__openLegacyModal('template-manager');">Template Manager</button>
        </div>
      </section>

      <!-- Visual & Design -->
      <section class="settings-section visual-section card-theme-purple">
        <header class="section-header">
          <h3 class="section-title">Visual &amp; Design</h3>
          <p class="section-description">Customize appearance and interactive elements</p>
        </header>
        <div class="section-content">
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/dashboard#css">CSS Rules</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/dashboard#background">Background Manager</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/?section=room-map-editor">Room Map Editor</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/room_main#area-mapper">Area-Item Mapper</a>
        </div>
      </section>

      <!-- Business & Analytics -->
      <section class="settings-section business-section card-theme-emerald">
        <header class="section-header">
          <h3 class="section-title">Business &amp; Analytics</h3>
          <p class="section-description">Manage sales, promotions, and business insights</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-business-info">Business Information</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-square-settings">Configure Square</button>
        </div>
      </section>

      <!-- Communication -->
      <section class="settings-section communication-section card-theme-orange">
        <header class="section-header">
          <h3 class="section-title">Communication</h3>
          <p class="section-description">Email configuration and customer messaging</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-settings">Email Configuration</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-history">Email History</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-test">Send Sample Email</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-logging-status">Logging Status</button>
          <a class="admin-settings-button btn-primary btn-full-width" href="/receipt.php">Receipt Messages</a>
        </div>
      </section>

      <!-- Technical & System -->
      <section class="settings-section technical-section card-theme-red">
        <header class="section-header">
          <h3 class="section-title">Technical &amp; System</h3>
          <p class="section-description">System tools and advanced configuration</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-account-settings">Account Settings</button>
          <a class="admin-settings-button btn-secondary btn-full-width" href="/admin/account_settings">Open Account Settings Page (fallback)</a>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-secrets-modal">Secrets Manager</button>
          <a class="admin-settings-button btn-secondary btn-full-width" href="/admin/secrets">Open Secrets Page (fallback)</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/cost_breakdown_manager">Cost Breakdown Manager</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/customers">User Manager</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/reports_browser.php">Reports &amp; Documentation Browser</a>
        </div>
      </section>

      <!-- AI & Automation -->
      <section class="settings-section ai-automation-section card-theme-teal">
        <header class="section-header">
          <h3 class="section-title">AI &amp; Automation</h3>
          <p class="section-description">Artificial intelligence and automation settings</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width ai-settings-btn" data-action="open-ai-settings">AI Provider</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width ai-tools-btn" data-action="open-ai-tools">AI &amp; Automation Tools</button>
        </div>
      </section>
    </div>

    <!-- Square Settings Modal (hidden by default) -->
    <div id="squareSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="squareSettingsTitle">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 id="squareSettingsTitle" class="admin-card-title">🟩 Square Settings</h2>
          <button type="button" class="admin-modal-close" data-action="close-square-settings" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="flex items-center justify-between mb-2">
            <button id="squareSettingsBtn" type="button" class="btn-secondary">
              Status
              <span id="squareConfiguredChip" class="status-chip chip-off">Not configured</span>
            </button>
          </div>

          <!-- Connection Status -->
          <div id="squareConnectionStatus" class="mb-4 p-3 rounded-lg border border-gray-200 bg-gray-50">
            <div class="flex items-center gap-2">
              <span id="connectionIndicator" class="w-3 h-3 rounded-full bg-gray-400"></span>
              <span id="connectionText" class="text-sm text-gray-700">Not Connected</span>
            </div>
          </div>

          <!-- Config Form (client saves via JS) -->
          <form id="squareConfigForm" data-action="prevent-submit" class="space-y-4">
            <!-- Environment -->
            <div>
              <label class="block text-sm font-medium mb-1">Environment</label>
              <div class="flex items-center gap-4">
                <label class="inline-flex items-center gap-2">
                  <input type="radio" name="environment" value="sandbox" checked>
                  <span>Sandbox</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input type="radio" name="environment" value="production">
                  <span>Production</span>
                </label>
              </div>
            </div>

            <!-- App ID / Location ID -->
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="squareAppId" class="block text-sm font-medium mb-1">Application ID</label>
                <input id="squareAppId" name="app_id" type="text" class="form-input w-full" placeholder="sq0idp-...">
              </div>
              <div>
                <label for="squareLocationId" class="block text-sm font-medium mb-1">Location ID</label>
                <input id="squareLocationId" name="location_id" type="text" class="form-input w-full" placeholder="L8K4...">
              </div>
            </div>

            <!-- Access Token (never prefilled) -->
            <div>
              <label for="squareAccessToken" class="block text-sm font-medium mb-1">Access Token</label>
              <input id="squareAccessToken" name="access_token" type="password" class="form-input w-full" placeholder="Paste your Square access token">
              <p class="text-xs text-gray-500 mt-1">Token is never prefetched for security. Saving will store it server-side.</p>
            </div>

            <!-- Sync options -->
            <div>
              <label class="block text-sm font-medium mb-2">Sync Options</label>
              <div class="grid gap-2 md:grid-cols-2">
                <label class="inline-flex items-center gap-2">
                  <input id="syncPrices" type="checkbox" checked>
                  <span>Sync Prices</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input id="syncInventory" type="checkbox" checked>
                  <span>Sync Inventory</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input id="syncDescriptions" type="checkbox">
                  <span>Sync Descriptions</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input id="autoSync" type="checkbox">
                  <span>Enable Auto Sync</span>
                </label>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap items-center gap-3">
              <button id="saveSquareSettingsBtn" type="button" class="btn-primary" data-action="square-save-settings">Save Settings</button>
              <button type="button" class="btn-secondary" data-action="square-test-connection">Test Connection</button>
              <button type="button" class="btn-secondary" data-action="square-sync-items">Sync Items</button>
              <button type="button" class="btn-danger" data-action="square-clear-token">Clear Token</button>
            </div>

            <div id="connectionResult" class="text-sm text-gray-600"></div>
          </form>
        </div>
      </div>
    </div>

    <!-- Categories Modal (hidden by default) -->
    <div id="categoriesModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="categoriesTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="categoriesTitle" class="admin-card-title">📂 Categories</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="space-y-4">
            <div class="flex items-center gap-2">
              <input id="catNewName" type="text" class="form-input" placeholder="New category name" />
              <button type="button" class="btn-primary" data-action="cat-add">Add</button>
            </div>
            <div id="catResult" class="text-sm text-gray-600"></div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr>
                    <th class="p-2 text-left">Name</th>
                    <th class="p-2 text-left">Items</th>
                    <th class="p-2 text-left">Actions</th>
                  </tr>
                </thead>
                <tbody id="catTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Attributes Management Modal (hidden by default) -->
    <div id="attributesModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="attributesTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="attributesTitle" class="admin-card-title">🧩 Gender, Size &amp; Color Management</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div id="attributesResult" class="text-sm text-gray-500 mb-2"></div>
          <div class="grid gap-4 md:grid-cols-3">
            <div class="attr-col">
              <h3 class="text-base font-semibold mb-2">Gender</h3>
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="gender" onsubmit="return false;">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add gender (e.g., Unisex)" maxlength="64">
                <button type="submit" class="btn btn-primary" data-action="attr-add" data-type="gender">Add</button>
              </form>
              <ul id="attrListGender" class="attr-list space-y-1"></ul>
            </div>
            <div class="attr-col">
              <h3 class="text-base font-semibold mb-2">Size</h3>
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="size" onsubmit="return false;">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add size (e.g., XL)" maxlength="64">
                <button type="submit" class="btn btn-primary" data-action="attr-add" data-type="size">Add</button>
              </form>
              <ul id="attrListSize" class="attr-list space-y-1"></ul>
            </div>
            <div class="attr-col">
              <h3 class="text-base font-semibold mb-2">Color</h3>
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="color" onsubmit="return false;">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add color (e.g., Royal Blue)" maxlength="64">
                <button type="submit" class="btn btn-primary" data-action="attr-add" data-type="color">Add</button>
              </form>
              <ul id="attrListColor" class="attr-list space-y-1"></ul>
            </div>
          </div>
          <div class="attributes-actions flex justify-end mt-4">
            <button type="button" class="btn btn-secondary" data-action="attr-save-order">Save Order</button>
          </div>
        </div>
      </div>
    </div>

    <!-- CSS Rules Modal (hidden by default) -->
    <div id="cssRulesModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="cssRulesTitle">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 id="cssRulesTitle" class="admin-card-title">🎨 CSS Rules</h2>
          <button type="button" class="admin-modal-close" data-action="close-css-rules" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <form id="cssRulesForm" data-action="prevent-submit" class="space-y-4">
            <p class="text-sm text-gray-700">Edit core CSS variables used site-wide. Changes are saved to the database and applied instantly.</p>

            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="cssToastBg" class="block text-sm font-medium mb-1">Toast Background</label>
                <input id="cssToastBg" name="toast_bg" type="color" class="form-input w-full" value="#87ac3a" />
                <p class="text-xs text-gray-500">Maps to <code>--toast-bg</code></p>
              </div>
              <div>
                <label for="cssToastText" class="block text-sm font-medium mb-1">Toast Text</label>
                <input id="cssToastText" name="toast_text" type="color" class="form-input w-full" value="#ffffff" />
                <p class="text-xs text-gray-500">Maps to <code>--toast-text</code></p>
              </div>
            </div>

            <!-- ... -->
          </form>
        </div>
      </div>
    </div>

    <!-- ... -->

    <script>
      (function(){
        try {
          if (!document || !document.addEventListener) return;
          var log = function(){
            try {
              var args = Array.prototype.slice.call(arguments);
              console.info.apply(console, ['[SettingsFailsafe]'].concat(args));
            } catch(_) {}
          };

          const ensureStatus = (modalEl, text) => {
            try {
              const header = modalEl && modalEl.querySelector('.modal-header');
              if (!header) return;
              let chip = header.querySelector('.modal-status-chip');
              if (!chip) {
                chip = document.createElement('span');
                chip.className = 'modal-status-chip';
                chip.style.cssText = 'margin-left:8px;font-size:12px;color:#059669;';
                chip.setAttribute('aria-live','polite');
                header.appendChild(chip);
              }
              if (text) chip.textContent = text;
            } catch(_) {}
          };
          async function populateCategories(){
            const modal = document.getElementById('categoriesModal'); if (!modal) return;
            const tbody = modal.querySelector('#catTableBody'); const result = modal.querySelector('#catResult');
            if (result) result.textContent = 'Loading…';
            try {
              const data = await catApi('/api/categories.php?action=list');
              const cats = (data.data && data.data.categories) ? data.data.categories : [];
              tbody.innerHTML = '';
              cats.forEach(c => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                  <td class="p-2"><span class="cat-name" data-name="${c.name.replace(/"/g,'&quot;')}">${c.name}</span></td>
                  <td class="p-2 text-gray-600">${(c.item_count != null ? c.item_count : 0)}</td>
                  <td class="p-2">
                    <button class="btn btn-secondary" data-action="cat-rename" data-name="${c.name.replace(/"/g,'&quot;')}">Rename</button>
                    <button class="btn btn-secondary text-red-700" data-action="cat-delete" data-name="${c.name.replace(/"/g,'&quot;')}">Delete</button>
                  </td>`;
                tbody.appendChild(tr);
              });
              if (result) result.textContent = cats.length ? '' : 'No categories found yet.';
            } catch (e) {
              if (result) result.textContent = (e && e.message) ? e.message : 'Failed to load categories';
            }
          }

          // Simple HTML5 drag-and-drop sorting for Active list
          function enableDragSort(listEl) {
            if (!listEl) return;
            const getItem = (el) => el && el.closest ? el.closest('li') : null;
            let dragEl = null;
            listEl.querySelectorAll('li').forEach(li => { li.setAttribute('draggable', 'true'); });
            listEl.addEventListener('dragstart', (e) => {
              const li = getItem(e.target); if (!li) return; dragEl = li; li.classList.add('dragging');
              e.dataTransfer.effectAllowed = 'move';
            });
            listEl.addEventListener('dragend', (e) => { const li = getItem(e.target); if (li) li.classList.remove('dragging'); dragEl = null; });
            listEl.addEventListener('dragover', (e) => {
              e.preventDefault();
              const after = getDragAfterElement(listEl, e.clientY);
              if (!dragEl) return;
              if (after == null) {
                listEl.appendChild(dragEl);
              } else {
                listEl.insertBefore(dragEl, after);
              }
            });
            function getDragAfterElement(container, y) {
              const els = Array.prototype.slice.call(container.querySelectorAll('li:not(.dragging)'));
              return els.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) { return { offset, element: child }; }
                else { return closest; }
              }, { offset: Number.NEGATIVE_INFINITY }).element;
            }
          }

          // ---- Attributes: lightweight CRUD wiring (gender, size, color) ----
          async function attrApi(path, payload){
            const url = typeof path === 'string' ? path : '/api/attributes.php?action=list';
            const opts = payload
              ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With':'XMLHttpRequest' }, body: JSON.stringify(payload), credentials: 'include' }
              : { method: 'GET', headers: { 'X-Requested-With':'XMLHttpRequest' }, credentials: 'include' };
            const res = await fetch(url, opts);
            const status = res.status; const text = await res.text();
            if (status < 200 || status >= 300) throw new Error(`HTTP ${status}: ${text.slice(0,200)}`);
            const data = text ? JSON.parse(text) : {};
            if (!data || data.success !== true) throw new Error((data && data.error) || 'Unexpected response');
            return data;
          }
          function renderAttrList(ul, arr, type){
            if (!ul) return; ul.innerHTML = '';
            const values = Array.isArray(arr) ? arr : [];
            const colEl = ul && ul.closest ? ul.closest('.attr-col') : null;
            const header = colEl ? colEl.querySelector('h3') : null;
            const setCount = (n) => { if (header) header.textContent = `${header.textContent.replace(/\s*\(.*\)$/, '')} (${n})`; };
            if (values.length === 0) {
              const li = document.createElement('li');
              li.className = 'text-sm text-gray-500 italic px-2 py-1';
              li.textContent = 'No values yet';
              ul.appendChild(li);
              setCount(0);
              return;
            }
            values.forEach(({value}) => {
              const li = document.createElement('li');
              li.className = 'flex items-center justify-between gap-2 px-2 py-1 border border-gray-200 rounded bg-white';
              li.setAttribute('draggable','true');
              const label = document.createElement('span'); label.className = 'text-sm'; label.textContent = value; label.setAttribute('data-value', value);
              const right = document.createElement('span'); right.className = 'flex items-center gap-2';
              const renameBtn = document.createElement('button'); renameBtn.type='button'; renameBtn.className='btn btn-secondary'; renameBtn.textContent='Rename'; renameBtn.setAttribute('data-action','attr-rename'); renameBtn.setAttribute('data-type', type); renameBtn.setAttribute('data-value', value);
              const delBtn = document.createElement('button'); delBtn.type='button'; delBtn.className='btn btn-secondary text-red-700'; delBtn.textContent='Delete'; delBtn.setAttribute('data-action','attr-delete'); delBtn.setAttribute('data-type', type); delBtn.setAttribute('data-value', value);
              right.appendChild(renameBtn); right.appendChild(delBtn);
              li.appendChild(label); li.appendChild(right); ul.appendChild(li);
            });
            setCount(values.length);
          }
          async function populateAttributes(){
            const modal = document.getElementById('attributesModal'); if (!modal) return;
            const res = modal.querySelector('#attributesResult'); if (res) res.textContent='Loading…';
            try{
              const data = await attrApi('/api/attributes.php?action=list');
              let a = (data && data.data && data.data.attributes) ? data.data.attributes : { gender:[], size:[], color:[] };
              try { console.info('[Attributes] primary list counts', {g:(a.gender && a.gender.length)||0, s:(a.size && a.size.length)||0, c:(a.color && a.color.length)||0}); } catch(_) {}

              // Ensure list containers exist (defensive)
              const ensureListEl = (id, colQuery) => {
                let ul = document.getElementById(id);
                if (!ul) {
                  const col = modal.querySelector(colQuery);
                  if (col) {
                    ul = document.createElement('ul');
                    ul.id = id;
                    ul.className = 'attr-list space-y-1';
                    col.appendChild(ul);
                    try { console.info('[Attributes] created missing list', id); } catch(_) {}
                  }
                }
                return ul;
              };
              const genderUl = ensureListEl('attrListGender', '.attr-col:nth-of-type(1)');
              const sizeUl = ensureListEl('attrListSize', '.attr-col:nth-of-type(2)');
              const colorUl = ensureListEl('attrListColor', '.attr-col:nth-of-type(3)');
              try { console.info('[Attributes] list nodes', { genderUl: !!genderUl, sizeUl: !!sizeUl, colorUl: !!colorUl }); } catch(_) {}
              // Per-type fallback: if an individual list is empty, fetch that legacy list
              try {
                const fetchJson = async (url) => {
                  const r = await fetch(url, { credentials:'include', headers:{'X-Requested-With':'XMLHttpRequest'} });
                  return await r.json().catch(()=>({}));
                };
                if (!a.gender || a.gender.length===0) {
                  const g = await fetchJson('/api/global_color_size_management.php?action=get_global_genders');
                  a.gender = Array.isArray(g && g.genders) ? g.genders.map(x=>({ value: String(x.gender_name||'').trim() })).filter(x=>x.value!=='') : [];
                }
                if (!a.size || a.size.length===0) {
                  const s = await fetchJson('/api/global_color_size_management.php?action=get_global_sizes');
                  a.size = Array.isArray(s && s.sizes) ? s.sizes.map(x=>({ value: String((x.size_code||x.size_name||'')).trim() })).filter(x=>x.value!=='') : [];
                }
                if (!a.color || a.color.length===0) {
                  const c = await fetchJson('/api/global_color_size_management.php?action=get_global_colors');
                  a.color = Array.isArray(c && c.colors) ? c.colors.map(x=>({ value: String(x.color_name||'').trim() })).filter(x=>x.value!=='') : [];
                }
              } catch(_) {}
              // Backstop: if all still empty, try one-shot parallel fallback
              if ((!a.gender || a.gender.length===0) && (!a.size || a.size.length===0) && (!a.color || a.color.length===0)) {
                try {
                  const [gRes, sRes, cRes] = await Promise.all([
                    fetch('/api/global_color_size_management.php?action=get_global_genders', { credentials:'include', headers:{'X-Requested-With':'XMLHttpRequest'} }),
                    fetch('/api/global_color_size_management.php?action=get_global_sizes', { credentials:'include', headers:{'X-Requested-With':'XMLHttpRequest'} }),
                    fetch('/api/global_color_size_management.php?action=get_global_colors', { credentials:'include', headers:{'X-Requested-With':'XMLHttpRequest'} }),
                  ]);
                  const [g, s, c] = await Promise.all([gRes.json().catch(()=>({})), sRes.json().catch(()=>({})), cRes.json().catch(()=>({}))]);
                  a = {
                    gender: Array.isArray(g && g.genders) ? g.genders.map(x=>({ value: String(x.gender_name||'').trim() })).filter(x=>x.value!=='') : [],
                    size: Array.isArray(s && s.sizes) ? s.sizes.map(x=>({ value: String((x.size_code||x.size_name||'')).trim() })).filter(x=>x.value!=='') : [],
                    color: Array.isArray(c && c.colors) ? c.colors.map(x=>({ value: String(x.color_name||'').trim() })).filter(x=>x.value!=='') : [],
                  };
                  try { console.info('[Attributes] legacy fallback counts', {g:a.gender.length, s:a.size.length, c:a.color.length}); } catch(_) {}
                } catch(_) {}
              }
              renderAttrList(document.getElementById('attrListGender'), a.gender, 'gender');
              renderAttrList(document.getElementById('attrListSize'), a.size, 'size');
              renderAttrList(document.getElementById('attrListColor'), a.color, 'color');
              // Debug: log list item counts
              try {
                const lg = document.querySelectorAll('#attrListGender li').length;
                const ls = document.querySelectorAll('#attrListSize li').length;
                const lc = document.querySelectorAll('#attrListColor li').length;
                console.info('[Attributes] list lengths', { gender: lg, size: ls, color: lc });
              } catch(_) {}
              try { enableDragSort(document.getElementById('attrListGender')); } catch(_) {}
              try { enableDragSort(document.getElementById('attrListSize')); } catch(_) {}
              try { enableDragSort(document.getElementById('attrListColor')); } catch(_) {}
              // Force scroll styles as a failsafe (in case CSS didn’t load)
              try {
                const cols = Array.from(document.querySelectorAll('#attributesModal .attr-col'));
                cols.forEach(col => { col.style.display='flex'; col.style.flexDirection='column'; col.style.minHeight='0'; });
                const lists = [
                  document.getElementById('attrListGender'),
                  document.getElementById('attrListSize'),
                  document.getElementById('attrListColor')
                ].filter(Boolean);
                // Compute dynamic max-height so lists fit within modal viewport
                const modalEl = document.getElementById('attributesModal');
                const bodyEl = modalEl ? modalEl.querySelector('.modal-body') : null;
                const bodyH = bodyEl ? Math.max(0, bodyEl.getBoundingClientRect().height) : 0;
                // Account for per-column input form height and gaps (~120px buffer)
                const listMax = Math.max(160, Math.floor(bodyH - 120));
                lists.forEach(ul => {
                  ul.style.height='';
                  ul.style.maxHeight=listMax + 'px';
                  ul.style.overflowY='auto';
                  ul.style.minHeight='0';
                  ul.style.paddingRight='6px';
                  ul.style.border='1px solid #e5e7eb';
                  ul.style.borderRadius='6px';
                  ul.style.background='#fff';
                });
              } catch(_) {}
              if (res) res.textContent='';
            } catch(e){ if (res) res.textContent = (e && e.message) ? e.message : 'Failed to load attributes'; }
          }

          const lazyFrame = (frameId, modalId) => {
            if (!frameId) return;
            try {
              const f = document.getElementById(frameId);
              if (f) {
                const ds = f.getAttribute('data-src') || f.getAttribute('src');
                if (ds && f.getAttribute('src') !== ds) {
                  console.info('[Settings] lazyFrame set src', { frameId, ds });
                  // Focus on load for accessibility and set status
                  f.addEventListener('load', () => {
                    try {
                      const modalEl = modalId ? document.getElementById(modalId) : f.closest('.admin-modal-overlay');
                      ensureStatus(modalEl, 'Loaded');
                      // Best-effort focus into iframe content
                      setTimeout(() => {
                        try { f.contentWindow && f.contentWindow.focus && f.contentWindow.focus(); } catch(_) {}
                        try { f.focus(); } catch(_) {}
                        // If a specific internal modal should open, postMessage into the iframe
                        try {
                          const openTarget = f.getAttribute('data-open-modal');
                          if (openTarget && f.contentWindow) {
                            console.info('[AttributesBridge] posting open-modal to iframe', openTarget);
                            // Try multiple times to tolerate delayed script initialization inside iframe
                            let attempts = 0;
                            const send = () => {
                              attempts++;
                              try { f.contentWindow.postMessage({ type: 'open-modal', modal: openTarget }, '*'); } catch(_) {}
                              if (attempts < 10) setTimeout(send, 200);
                            };
                            send();
                          }
                        } catch(_) {}
                      }, 0);
                    } catch(_) {}
                  }, { once: true });
                  f.setAttribute('src', ds);
                }
              }
            } catch(_) {}
          };

          const tryOpen = (modalId, frameId, url, openModalKey) => {
            try {
              let modalEl = document.getElementById(modalId);
              if (!modalEl) {
                // Inject minimal overlay if missing
                const overlay = document.createElement('div');
                overlay.id = modalId;
                overlay.className = 'admin-modal-overlay hidden';
                overlay.setAttribute('aria-hidden','true');
                overlay.setAttribute('role','dialog');
                overlay.setAttribute('aria-modal','true');
                overlay.setAttribute('tabindex','-1');
                let bodyHtml = '';
                const srcUrl = url && typeof url === 'string' ? url : '/admin/inventory#attributes';
                if (frameId) {
                  const dataOpen = openModalKey ? ` data-open-modal="${openModalKey}"` : '';
                  bodyHtml = `<iframe id="${frameId}" src="${srcUrl}" data-src="${srcUrl}"${dataOpen} class="wf-admin-embed-frame"></iframe>`;
                }
                overlay.innerHTML = `
                  <div class="admin-modal">
                    <div class="modal-header">
                      <h2 class="admin-card-title">Modal</h2>
                      <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
                      <span class="modal-status-chip" aria-live="polite"></span>
                    </div>
                    <div class="modal-body">${bodyHtml}</div>
                  </div>`;
                document.body.appendChild(overlay);
                console.info('[Settings] created overlay', { modalId, frameId, srcUrl });
              }
              if (showModal(modalId)) {
                modalEl = document.getElementById(modalId);
                try { ensureHeaderHint(modalEl); } catch(_) {}
                if (frameId) {
                  try { ensureStatus(modalEl, 'Loading…'); } catch(_) {}
                  // Update data-src/src if caller passed a URL on an existing iframe
                  try {
                    const f = document.getElementById(frameId);
                    if (f && url && typeof url === 'string') { f.setAttribute('data-src', url); if (f.getAttribute('src') !== url) f.setAttribute('src', url); }
                    if (f && openModalKey) { f.setAttribute('data-open-modal', openModalKey); }
                  } catch(_) {}
                  lazyFrame(frameId, modalId);
                } else {
                  try { ensureStatus(modalEl, 'Loaded'); ensureQANote(modalEl, 'Loaded'); } catch(_) {}
                }
                try { console.info('[Settings] delegated-open', modalId); } catch(_) {}
                return true;
              }
            } catch(_) {}
            return false;
          };

          document.addEventListener('click', (e) => {
            const t = e.target;
            if (t && t.classList && t.classList.contains('admin-modal-overlay')) {
              const id = t.id;
              if (id) {
                try { e.preventDefault(); e.stopPropagation(); } catch(_) {}
                t.classList.add('hidden'); t.classList.remove('show'); t.setAttribute('aria-hidden','true'); log('overlay-close', id);
              }
            }
          }, true);

          // Hard fallback opener that works even if delegation is interrupted
          window.__openLegacyModal = function(name){
            try{
              if(name==='room-settings') return tryOpen('roomSettingsModal','roomSettingsFrame','/admin/room_main','');
              if(name==='room-category-links') return tryOpen('roomCategoryLinksModal','roomCategoryLinksFrame','/admin/inventory#room-category-links','');
              if(name==='template-manager') return tryOpen('templateManagerModal','templateManagerFrame','/admin/inventory#templates','');
            }catch(_){return false}
            return false;
          };

          // Dedicated handlers for Room Settings, Room-Category Links, Template Manager
          document.addEventListener('click', (e) => {
            const t = e.target;
            const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
            // Room Settings
            if (closest('[data-action="open-room-settings"]')) {
              e.preventDefault();
              tryOpen('roomSettingsModal', 'roomSettingsFrame', '/admin/room_main', '');
              return;
            }
            // Room-Category Links
            if (closest('[data-action="open-room-category-links"]')) {
              e.preventDefault();
              tryOpen('roomCategoryLinksModal', 'roomCategoryLinksFrame', '/admin/inventory#room-category-links', '');
              return;
            }
            // Template Manager
            if (closest('[data-action="open-template-manager"]')) {
              e.preventDefault();
              tryOpen('templateManagerModal', 'templateManagerFrame', '/admin/inventory#templates', '');
              return;
            }
          }, true);

          // Capturing-phase handler to guarantee Attributes actions fire
          document.addEventListener('click', (e) => {
            try {
              const t = e.target;
              const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
              if (!document.getElementById('attributesModal')) return;
              const inModal = t && t.closest && t.closest('#attributesModal');
              if (!inModal) return;

              const res = document.querySelector('#attributesResult');
              // Add
              const addAttrBtn = closest('[data-action="attr-add"]');
              if (addAttrBtn) {
                e.preventDefault();
                const type = addAttrBtn.getAttribute('data-type')||'';
                const form = addAttrBtn.closest('[data-action="attr-add-form"]');
                const input = form ? form.querySelector('.attr-input') : null;
                const val = input ? (input.value||'').trim() : '';
                if (!type || !val) { if (res) res.textContent='Enter a value.'; return; }
                if (res) res.textContent='Adding…';
                attrApi('/api/attributes.php?action=add', { action:'add', type, value: val })
                  .then(function(){ if (input) input.value=''; populateAttributes(); if (res) res.textContent='Added.'; })
                  .catch(function(err){ if (res) res.textContent = (err && err.message) ? err.message : 'Failed to add.'; try{ console.error('[Attributes] add error', err); }catch(_){} });
                return;
              }

              // Rename
              const rnBtn = closest('[data-action="attr-rename"]');
              if (rnBtn) {
                e.preventDefault();
                const type = rnBtn.getAttribute('data-type')||'';
                const oldVal = rnBtn.getAttribute('data-value')||'';
                const newVal = prompt(`Rename ${type}`, oldVal)||'';
                if (!newVal || newVal.trim()===oldVal) return;
                if (res) res.textContent='Renaming…';
                attrApi('/api/attributes.php?action=rename', { action:'rename', type, old_value: oldVal, new_value: newVal.trim() })
                  .then(function(){ populateAttributes(); if (res) res.textContent='Renamed.'; })
                  .catch(function(err){ if (res) res.textContent = (err && err.message) ? err.message : 'Failed to rename.'; try{ console.error('[Attributes] rename error', err); }catch(_){} });
                return;
              }

              // Delete
              const delBtn = closest('[data-action="attr-delete"]');
              if (delBtn) {
                e.preventDefault();
                const type = delBtn.getAttribute('data-type')||'';
                const val = delBtn.getAttribute('data-value')||'';
                if (!confirm(`Delete ${type} value "${val}"?`)) return;
                if (res) res.textContent='Deleting…';
                attrApi('/api/attributes.php?action=delete', { action:'delete', type, value: val })
                  .then(function(){ populateAttributes(); if (res) res.textContent='Deleted.'; })
                  .catch(function(err){ if (res) res.textContent = (err && err.message) ? err.message : 'Failed to delete.'; try{ console.error('[Attributes] delete error', err); }catch(_){} });
                return;
              }
            } catch(_) {}
          }, true);

          document.addEventListener('keydown', (e) => {
            try {
              if (e.key === 'Escape' || e.key === 'Esc') {
                const openOverlays = Array.from(document.querySelectorAll('.admin-modal-overlay.show'));
                if (openOverlays.length) {
                  const top = openOverlays[openOverlays.length - 1];
                  e.preventDefault(); e.stopPropagation();
                  top.classList.add('hidden'); top.classList.remove('show'); top.setAttribute('aria-hidden','true');
                  log('esc-close', top.id || '(no id)');
                }
              }
            } catch(_) {}
          }, true);

          // Minimal open/close helpers to ensure Settings buttons work even without bundles
          const __settingsShowModal = (id) => {
            try {
              const el = document.getElementById(id);
              if (!el) return false;
              // Reparent overlay to <body> to avoid stacking/overflow contexts
              try { if (el.parentNode && el.parentNode !== document.body) document.body.appendChild(el); } catch(_) {}
              // Enforce fixed, full-viewport overlay with strong stacking context
              try {
                el.style.position = 'fixed';
                el.style.left = '0';
                el.style.top = '0';
                el.style.right = '0';
                el.style.bottom = '0';
                el.style.width = '100%';
                el.style.height = '100%';
                el.style.zIndex = '99999';
              } catch(_) {}
              // Offset overlay below header so dialog never appears under it
              try {
                const header = document.querySelector('.site-header') || document.querySelector('.universal-page-header');
                const hh = header && header.getBoundingClientRect ? Math.max(40, Math.round(header.getBoundingClientRect().height)) : 64;
                el.style.paddingTop = (hh + 12) + 'px';
                el.style.alignItems = 'flex-start';
              } catch(_) {}
              try { el.removeAttribute('hidden'); } catch(_) {}
              try { el.classList.remove('hidden'); } catch(_) {}
              try { el.classList.add('show'); } catch(_) {}
              try { el.setAttribute('aria-hidden','false'); } catch(_) {}
              try { el.style.display = 'flex'; } catch(_) {}
              try { console.info('[SettingsFailsafe] showModal', id); } catch(_) {}
              return true;
            } catch(_) { return false; }
          };
          const __settingsHideModal = (id) => {
            try {
              const el = document.getElementById(id);
              if (!el) return false;
              el.classList.add('hidden');
              el.classList.remove('show');
              el.setAttribute('aria-hidden','true');
              try { console.info('[SettingsFailsafe] hideModal', id); } catch(_) {}
              return true;
            } catch(_) { return false; }
          };
          // Always expose namespaced helpers for this page
          try { window.__settingsShowModal = __settingsShowModal; } catch(_) {}
          try { window.__settingsHideModal = __settingsHideModal; } catch(_) {}
          // Also bind to generic names in case other code expects them; override to guarantee behavior on this page
          try { window.showModal = __settingsShowModal; } catch(_) {}
          try { window.hideModal = __settingsHideModal; } catch(_) {}

          // Strong, page-local click binding for three core buttons, independent of other delegates
          function bindDirectOpeners(){
            try {
              const pairs = [
                { sel: '[data-action="open-dashboard-config"]', id: 'dashboardConfigModal' },
                { sel: '[data-action="open-categories"]', id: 'categoriesModal' },
                { sel: '[data-action="open-attributes"]', id: 'attributesModal' }
              ];
              pairs.forEach(({ sel, id }) => {
                const btns = Array.from(document.querySelectorAll(sel));
                btns.forEach(btn => {
                  // Avoid duplicate handlers
                  if (btn.__wfBound) return; btn.__wfBound = true;
                  btn.addEventListener('click', function(ev){
                    try { ev.preventDefault(); ev.stopPropagation(); } catch(_) {}
                    try { __settingsShowModal(id); } catch(_) {}
                  }, true);
                });
              });
              // Also bind direct handlers for Room Settings, Room-Category Links, and Template Manager
              const directDefs = [
                { sel: '[data-action="open-room-settings"]', open: () => tryOpen('roomSettingsModal','roomSettingsFrame','/admin/room_main','') },
                { sel: '[data-action="open-room-category-links"]', open: () => tryOpen('roomCategoryLinksModal','roomCategoryLinksFrame','/admin/inventory#room-category-links','') },
                { sel: '[data-action="open-template-manager"]', open: () => tryOpen('templateManagerModal','templateManagerFrame','/admin/inventory#templates','') },
              ];
              directDefs.forEach(({ sel, open }) => {
                const nodes = Array.from(document.querySelectorAll(sel));
                nodes.forEach(node => {
                  if (node.__wfBound) return; node.__wfBound = true;
                  node.addEventListener('click', function(ev){
                    try { ev.preventDefault(); ev.stopPropagation(); } catch(_) {}
                    try { open(); } catch(_) {}
                  }, true);
                });
              });
            } catch(_) {}
          }
          if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bindDirectOpeners, { once: true });
          } else {
            bindDirectOpeners();
          }

          // --- Runtime Backstop: ensure core modals exist even if markup was excluded ---
          (function ensureCoreModals(){
            try {
              const makeOverlay = (id, title, bodyHtml) => {
                const el = document.createElement('div');
                el.id = id;
                el.className = 'admin-modal-overlay hidden';
                el.setAttribute('role','dialog'); el.setAttribute('aria-modal','true'); el.setAttribute('aria-hidden','true'); el.tabIndex = -1;
                el.innerHTML = [
                  '<div class="admin-modal admin-modal-content">',
                  '  <div class="modal-header">',
                  `    <h2 class="admin-card-title">${title}</h2>`,
                  '    <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>',
                  '    <span class="modal-status-chip" aria-live="polite"></span>',
                  '  </div>',
                  `  <div class="modal-body">${bodyHtml||''}</div>`,
                  '</div>'
                ].join('');
                document.body.appendChild(el);
                try { console.info('[SettingsFailsafe] injected overlay', id); } catch(_) {}
                return el;
              };

              if (!document.getElementById('dashboardConfigModal')) {
                makeOverlay('dashboardConfigModal', '⚙️ Dashboard Configuration', [
                  '<div class="space-y-4">',
                  '  <p class="text-sm text-gray-700">Manage which sections appear on your Dashboard. Add/remove from the lists below, then click Save.</p>',
                  '  <div class="grid gap-4 md:grid-cols-2">',
                  '    <div>',
                  '      <h3 class="text-base font-semibold mb-2">Active Sections</h3>',
                  '      <ul id="dashboardActiveSections" class="list-disc pl-5 text-sm text-gray-800"></ul>',
                  '    </div>',
                  '    <div>',
                  '      <h3 class="text-base font-semibold mb-2">Available Sections</h3>',
                  '      <ul id="dashboardAvailableSections" class="list-disc pl-5 text-sm text-gray-800"></ul>',
                  '    </div>',
                  '  </div>',
                  '  <div class="flex justify-between items-center">',
                  '    <div id="dashboardConfigResult" class="text-sm text-gray-500"></div>',
                  '    <div class="flex items-center gap-2">',
                  '      <button type="button" class="btn" data-action="dashboard-config-reset">Reset to defaults</button>',
                  '      <button type="button" class="btn-secondary" data-action="dashboard-config-refresh">Refresh</button>',
                  '      <button type="button" class="btn-primary" data-action="dashboard-config-save">Save</button>',
                  '    </div>',
                  '  </div>',
                  '</div>'
                ].join(''));
              }

              if (!document.getElementById('categoriesModal')) {
                makeOverlay('categoriesModal', '📂 Categories', [
                  '<div class="space-y-4">',
                  '  <div class="flex items-center gap-2">',
                  '    <input id="catNewName" type="text" class="form-input" placeholder="New category name" />',
                  '    <button type="button" class="btn-primary" data-action="cat-add">Add</button>',
                  '  </div>',
                  '  <div id="catResult" class="text-sm text-gray-600"></div>',
                  '  <div class="overflow-x-auto">',
                  '    <table class="min-w-full text-sm">',
                  '      <thead>',
                  '        <tr><th class="p-2 text-left">Name</th><th class="p-2 text-left">Items</th><th class="p-2 text-left">Actions</th></tr>',
                  '      </thead>',
                  '      <tbody id="catTableBody"></tbody>',
                  '    </table>',
                  '  </div>',
                  '</div>'
                ].join(''));
              }

              if (!document.getElementById('attributesModal')) {
                makeOverlay('attributesModal', '🧩 Gender, Size & Color Management', [
                  '<div id="attributesResult" class="text-sm text-gray-500 mb-2"></div>',
                  '<div class="grid gap-4 md:grid-cols-3">',
                  '  <div class="attr-col"><h3 class="text-base font-semibold mb-2">Gender</h3><ul id="attrListGender" class="attr-list space-y-1"></ul></div>',
                  '  <div class="attr-col"><h3 class="text-base font-semibold mb-2">Size</h3><ul id="attrListSize" class="attr-list space-y-1"></ul></div>',
                  '  <div class="attr-col"><h3 class="text-base font-semibold mb-2">Color</h3><ul id="attrListColor" class="attr-list space-y-1"></ul></div>',
                  '</div>',
                  '<div class="attributes-actions flex justify-end mt-4">',
                  '  <button type="button" class="btn btn-secondary" data-action="attr-save-order">Save Order</button>',
                  '</div>'
                ].join(''));
              }
            } catch(_) {}
          })();

          // ---- Dashboard Config: minimal fallback population and save wiring ----
          async function dashApi(path, payload) {
            let url = typeof path === 'string' ? path : '/api/dashboard_sections.php?action=get_sections';
            // Avoid object spread to maintain compatibility in inline script
            var baseHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
            var headers = baseHeaders;
            if (payload) {
              headers = Object.assign({}, baseHeaders, { 'Content-Type': 'application/json' });
            }
            var opts = payload
              ? { method: 'POST', headers: headers, body: JSON.stringify(payload), credentials: 'include' }
              : { method: 'GET', headers: headers, credentials: 'include' };
            const res = await fetch(url, opts).catch(function(e){ throw new Error((e && e.message) ? e.message : 'Network error'); });
            const status = res.status;
            const text = await res.text().catch(() => '');
            if (status < 200 || status >= 300) throw new Error(`HTTP ${status}: ${text.slice(0, 200)}`);
            let data; try { data = text ? JSON.parse(text) : {}; } catch (e) { throw new Error(`Non-JSON response: ${text.slice(0, 200)}`); }
            if ((!data || Object.keys(data).length === 0) && /\/api\/dashboard_sections\.php/.test(url)) data = { success: true };
            if (!data || data.success !== true) throw new Error(data && data.error ? String(data.error) : `Unexpected response: ${text.slice(0, 200) || '(empty)'}`);
            return data;
          }

          function renderDashboardLists(container) {
            const activeUl = container.querySelector('#dashboardActiveSections');
            const availUl = container.querySelector('#dashboardAvailableSections');
            if (!activeUl || !availUl) return { activeUl: null, availUl: null };
            activeUl.innerHTML = '';
            availUl.innerHTML = '';
            const makeLi = (item, isActive) => {
              const li = document.createElement('li');
              li.dataset.key = (item.section_key || item.key || '').trim();
              li.setAttribute('draggable', isActive ? 'true' : 'false');
              const title = item.display_title || item.title || (item.section_info && item.section_info.title) || li.dataset.key;
              li.className = 'wf-dash-item flex flex-col gap-1 px-2 py-2 border border-gray-200 rounded bg-white';
              const rowTop = document.createElement('div');
              rowTop.className = 'flex items-center justify-between gap-2';
              const label = document.createElement('span');
              label.className = 'wf-dash-item-title text-sm text-gray-800';
              label.textContent = title;
              // width selector
              const widthSel = document.createElement('select');
              widthSel.className = 'wf-dash-width text-xs border border-gray-300 rounded px-1 py-0.5';
              widthSel.setAttribute('data-key', li.dataset.key);
              const optHalf = document.createElement('option'); optHalf.value = 'half-width'; optHalf.textContent = 'Half width';
              const optFull = document.createElement('option'); optFull.value = 'full-width'; optFull.textContent = 'Full width';
              widthSel.appendChild(optHalf); widthSel.appendChild(optFull);
              const initialWidth = (item.width_class || 'half-width');
              try { widthSel.value = initialWidth; } catch(_) { widthSel.value = 'half-width'; }
              widthSel.disabled = !isActive;
              const btn = document.createElement('button');
              btn.type = 'button';
              btn.className = 'btn btn-secondary';
              btn.textContent = isActive ? 'Remove' : 'Add';
              btn.setAttribute('data-action', isActive ? 'dashboard-remove-section' : 'dashboard-add-section');
              btn.setAttribute('data-key', li.dataset.key);
              const rightWrap = document.createElement('span');
              rightWrap.className = 'flex items-center gap-2';
              // Move up/down controls (keyboard accessible)
              const upBtn = document.createElement('button');
              upBtn.type = 'button';
              upBtn.className = 'btn btn-secondary';
              upBtn.setAttribute('aria-label','Move up');
              upBtn.textContent = '↑';
              upBtn.setAttribute('data-action','dashboard-move-up');
              const downBtn = document.createElement('button');
              downBtn.type = 'button';
              downBtn.className = 'btn btn-secondary';
              downBtn.setAttribute('aria-label','Move down');
              downBtn.textContent = '↓';
              downBtn.setAttribute('data-action','dashboard-move-down');
              upBtn.disabled = !isActive; downBtn.disabled = !isActive;
              rightWrap.appendChild(widthSel);
              rightWrap.appendChild(upBtn);
              rightWrap.appendChild(downBtn);
              rightWrap.appendChild(btn);
              rowTop.appendChild(label);
              rowTop.appendChild(rightWrap);
              // row bottom: title/description controls
              const rowBottom = document.createElement('div');
              rowBottom.className = 'flex flex-wrap items-center gap-2 pl-1';
              const cbTitle = document.createElement('label');
              cbTitle.className = 'flex items-center gap-1 text-xs text-gray-700';
              const cbTitleInput = document.createElement('input'); cbTitleInput.type = 'checkbox'; cbTitleInput.className = 'wf-dash-show-title'; cbTitleInput.checked = (item.show_title !== 0);
              cbTitle.appendChild(cbTitleInput); cbTitle.appendChild(document.createTextNode('Show title'));
              const cbDesc = document.createElement('label');
              cbDesc.className = 'flex items-center gap-1 text-xs text-gray-700';
              const cbDescInput = document.createElement('input'); cbDescInput.type = 'checkbox'; cbDescInput.className = 'wf-dash-show-desc'; cbDescInput.checked = (item.show_description !== 0);
              cbDesc.appendChild(cbDescInput); cbDesc.appendChild(document.createTextNode('Show description'));
              const ctInput = document.createElement('input');
              ctInput.type = 'text'; ctInput.maxLength = 80; ctInput.className = 'wf-dash-custom-title text-xs border border-gray-300 rounded px-1'; ctInput.placeholder = 'Custom title (max 80)';
              ctInput.value = item.custom_title || '';
              const ctClear = document.createElement('button'); ctClear.type = 'button'; ctClear.className = 'btn btn-secondary'; ctClear.textContent = 'Clear'; ctClear.setAttribute('data-action','dashboard-clear-title');
              const cdInput = document.createElement('input');
              cdInput.type = 'text'; cdInput.maxLength = 160; cdInput.className = 'wf-dash-custom-desc text-xs border border-gray-300 rounded px-1 min-w-[200px]'; cdInput.placeholder = 'Custom description (max 160)';
              cdInput.value = item.custom_description || '';
              const cdClear = document.createElement('button'); cdClear.type = 'button'; cdClear.className = 'btn btn-secondary'; cdClear.textContent = 'Clear'; cdClear.setAttribute('data-action','dashboard-clear-desc');
              [cbTitleInput, cbDescInput, ctInput, cdInput].forEach(el => el.disabled = !isActive);
              rowBottom.appendChild(cbTitle);
              rowBottom.appendChild(cbDesc);
              rowBottom.appendChild(ctInput);
              rowBottom.appendChild(ctClear);
              rowBottom.appendChild(cdInput);
              rowBottom.appendChild(cdClear);
              // Inline validation styling
              const applyValidity = (inp, max) => {
                const ok = (inp.value || '').length <= max;
                inp.setAttribute('aria-invalid', ok ? 'false' : 'true');
                try { inp.style.outline = ok ? 'none' : '2px solid #dc2626'; } catch(_) {}
              };
              ctInput.addEventListener('input', () => applyValidity(ctInput, 80));
              cdInput.addEventListener('input', () => applyValidity(cdInput, 160));
              applyValidity(ctInput, 80); applyValidity(cdInput, 160);
              li.appendChild(rowTop);
              li.appendChild(rowBottom);
              return li;
            };
            return { activeUl, availUl, makeLi };
          }

          async function populateDashboardFallback(modalId = 'dashboardConfigModal') {
            const el = document.getElementById(modalId);
            if (!el) return;
            const body = el.querySelector('.modal-body');
            if (!body) return;
            const result = el.querySelector('#dashboardConfigResult');
            try { if (result) result.textContent = 'Loading…'; } catch(_) {}
            try {
              const data = await dashApi('/api/dashboard_sections.php?action=get_sections');
              const sections = (data && data.data && data.data.sections) ? data.data.sections : (data && data.sections ? data.sections : []);
              const lists = renderDashboardLists(el);
              if (!lists.activeUl) return;
              let avail = (data && data.data && data.data.available_sections) ? data.data.available_sections : ((data && data.available_sections) ? data.available_sections : {});
              if (!avail || Object.keys(avail).length === 0) {
                avail = { metrics:{title:'📊 Quick Metrics'}, recent_orders:{title:'📋 Recent Orders'}, low_stock:{title:'⚠️ Low Stock Alerts'}, inventory_summary:{title:'📦 Inventory Summary'}, customer_summary:{title:'👥 Customer Overview'}, marketing_tools:{title:'📈 Marketing Tools'}, order_fulfillment:{title:'🚚 Order Fulfillment'}, reports_summary:{title:'📊 Reports Summary'} };
              }
              const activeKeys = new Set();
              const pushActive = (obj) => {
                const key = (obj.section_key || obj.key || '').trim(); if (!key || activeKeys.has(key)) return;
                activeKeys.add(key);
                const enriched = Object.assign({}, obj, { section_key: key, key: key, title: ((avail[key] && avail[key].title) || obj.display_title || obj.title || key), is_active: 1 });
                lists.activeUl.appendChild(lists.makeLi(enriched, true));
              };
              if (Array.isArray(sections) && sections.length) {
                sections.forEach((s) => { if (s && (s.is_active === 1 || s.is_active === true)) pushActive(s); });
              } else {
                try {
                  const raw = localStorage.getItem('wf.dashboard.sections');
                  const snap = raw ? JSON.parse(raw) : [];
                  if (Array.isArray(snap) && snap.length) {
                    snap.forEach(item => pushActive({ key: item.key || item.section_key, section_key: item.section_key || item.key, title: item.title || item.section_key || item.key }));
                  }
                } catch(_) {}
              }
              Object.keys(avail).forEach(function(key){
                if (activeKeys.has(key)) return;
                lists.availUl.appendChild(lists.makeLi({ key: key, title: (avail[key] && avail[key].title) || key }, false));
              });
              try { if (result) result.textContent = ''; } catch(_) {}
              // enable drag ordering on active list
              try { enableDragSort(lists.activeUl); } catch(_) {}
            } catch (err) {
              console.error('[SettingsFailsafe] Dashboard populate failed', err);
              try { if (result) result.textContent = (err && err.message) ? err.message : 'Failed to load sections.'; } catch(_) {}
            }
          }

          // Delegated click wiring for core Settings actions (failsafe)
          document.addEventListener('click', (e) => {
            try {
              const t = e.target;
              const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
              // Scope to settings page DOM root if present
              if (!document.getElementById('adminSettingsRoot')) return;

              if (closest('[data-action="open-dashboard-config"]')) { try { console.info('[SettingsFailsafe] click open-dashboard-config'); } catch(_) {} e.preventDefault(); e.stopPropagation(); if (showModal('dashboardConfigModal')) { populateDashboardFallback('dashboardConfigModal'); } return; }
              if (closest('[data-action="open-attributes"]')) { try { console.info('[SettingsFailsafe] click open-attributes'); } catch(_) {} e.preventDefault(); e.stopPropagation(); if (showModal('attributesModal')) { populateAttributes(); } return; }
              if (closest('[data-action="open-categories"]')) { e.preventDefault(); e.stopPropagation(); if (showModal('categoriesModal')) { populateCategories(); } return; }
              if (closest('[data-action="dashboard-config-refresh"]')) { e.preventDefault(); e.stopPropagation(); populateDashboardFallback('dashboardConfigModal'); return; }
              if (closest('[data-action="dashboard-config-reset"]')) { e.preventDefault(); e.stopPropagation();
                const el = document.getElementById('dashboardConfigModal');
                const result = el ? el.querySelector('#dashboardConfigResult') : null;
                if (result) result.textContent = 'Resetting…';
                dashApi('/api/dashboard_sections.php?action=reset_defaults').then(function(){ if (result) result.textContent = 'Defaults restored.'; populateDashboardFallback('dashboardConfigModal'); }).catch(function(err){ if (result) result.textContent = (err && err.message) ? err.message : 'Failed to reset.'; });
                return;
              }
              if (closest('[data-action="dashboard-config-save"]')) {
                e.preventDefault(); e.stopPropagation();
                try {
                  const el = document.getElementById('dashboardConfigModal');
                  const result = el ? el.querySelector('#dashboardConfigResult') : null;
                  const overlay = (el && el.closest) ? el.closest('.admin-modal-overlay') : null;
                  const chip = overlay ? overlay.querySelector('.modal-status-chip') : null;
                  const activeUl = el ? el.querySelector('#dashboardActiveSections') : null;
                  if (!el || !activeUl) return;
                  const seen = new Set();
                  const items = Array.from(activeUl.querySelectorAll('li')).filter(li => { const k = (li.dataset.key||'').trim(); if (!k || seen.has(k)) return false; seen.add(k); return true; });
                  if (!items.length) { if (result) { result.textContent = 'Add at least one section before saving.'; } return; }
                  const payload = {
                    action: 'update_sections',
                    sections: items.map((li, idx) => ({
                      section_key: (li.dataset.key||'').trim(),
                      display_order: idx + 1,
                      is_active: 1,
                      show_title: (function(){ const el = li.querySelector('.wf-dash-show-title'); return el ? (el.checked ? 1 : 0) : 1; })(),
                      show_description: (function(){ const el = li.querySelector('.wf-dash-show-desc'); return el ? (el.checked ? 1 : 0) : 1; })(),
                      custom_title: (function(){ const el = li.querySelector('.wf-dash-custom-title'); return el ? (el.value||'') : null; })(),
                      custom_description: (function(){ const el = li.querySelector('.wf-dash-custom-desc'); return el ? (el.value||'') : null; })(),
                      width_class: (function(){ const sel = li.querySelector('.wf-dash-width'); return sel ? (sel.value||'half-width') : 'half-width'; })()
                    }))
                  };
                  if (result) result.textContent = 'Saving…';
                  dashApi('/api/dashboard_sections.php?action=update_sections', payload).then(() => {
                    if (result) result.textContent = 'Saved.';
                    try {
                      const now = new Date();
                      const t = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                      if (chip) chip.textContent = `Saved at ${t}`;
                    } catch(_) {}
                    try { localStorage.setItem('wf.dashboard.sections', JSON.stringify(payload.sections.map(s => ({ key: s.section_key, section_key: s.section_key, title: s.section_key, is_active: 1 })))); } catch(_) {}
                  }).catch(function(err){ if (result) result.textContent = (err && err.message) ? err.message : 'Failed to save.'; });
                } catch (err) { console.error('[SettingsFailsafe] Dashboard save failed', err); }
                return;
              }

              // Add/Remove actions
              const addBtn = closest('[data-action="dashboard-add-section"]');
              if (addBtn) {
                e.preventDefault(); e.stopPropagation();
                try {
                  const modal = document.getElementById('dashboardConfigModal');
                  const activeUl = modal ? modal.querySelector('#dashboardActiveSections') : null;
                  const availUl = modal ? modal.querySelector('#dashboardAvailableSections') : null;
                  if (!activeUl || !availUl) return;
                  const li = addBtn.closest('li');
                  if (li && li.parentNode === availUl) {
                    activeUl.appendChild(li);
                    const btn = li.querySelector('[data-action]');
                    if (btn) { btn.setAttribute('data-action','dashboard-remove-section'); btn.textContent = 'Remove'; btn.setAttribute('data-key', li.dataset.key || ''); }
                    const sel = li.querySelector('.wf-dash-width'); if (sel) sel.disabled = false;
                    ['.wf-dash-show-title','.wf-dash-show-desc','.wf-dash-custom-title','.wf-dash-custom-desc'].forEach(cls => { const el = li.querySelector(cls); if (el) el.disabled = false; });
                    li.setAttribute('draggable','true');
                  }
                } catch(_) {}
                return;
              }
              const remBtn = closest('[data-action="dashboard-remove-section"]');
              if (remBtn) {
                e.preventDefault(); e.stopPropagation();
                try {
                  const modal = document.getElementById('dashboardConfigModal');
                  const activeUl = modal ? modal.querySelector('#dashboardActiveSections') : null;
                  const availUl = modal ? modal.querySelector('#dashboardAvailableSections') : null;
                  if (!activeUl || !availUl) return;
                  const li = remBtn.closest('li');
                  if (li && li.parentNode === activeUl) {
                    availUl.appendChild(li);
                    const btn = li.querySelector('[data-action]');
                    if (btn) { btn.setAttribute('data-action','dashboard-add-section'); btn.textContent = 'Add'; btn.setAttribute('data-key', li.dataset.key || ''); }
                    const sel = li.querySelector('.wf-dash-width'); if (sel) sel.disabled = true;
                    ['.wf-dash-show-title','.wf-dash-show-desc','.wf-dash-custom-title','.wf-dash-custom-desc'].forEach(cls => { const el = li.querySelector(cls); if (el) el.disabled = true; });
                    li.setAttribute('draggable','false');
                  }
                } catch(_) {}
                return;
              }

              // Move up/down actions (keyboard accessible)
              const moveUp = closest('[data-action="dashboard-move-up"]');
              if (moveUp) {
                e.preventDefault(); e.stopPropagation();
                try {
                  const li = moveUp.closest('li'); if (!li) return; const ul = li.parentNode; if (!ul) return;
                  const prev = li.previousElementSibling; if (prev) ul.insertBefore(li, prev);
                } catch(_) {}
                return;
              }
              const moveDown = closest('[data-action="dashboard-move-down"]');
              if (moveDown) {
                e.preventDefault(); e.stopPropagation();
                try {
                  const li = moveDown.closest('li'); if (!li) return; const ul = li.parentNode; if (!ul) return;
                  const next = li.nextElementSibling; if (next) ul.insertBefore(next, li);
                } catch(_) {}
                return;
              }

              // Clear custom title/description
              const clearTitle = closest('[data-action="dashboard-clear-title"]');
              if (clearTitle) { e.preventDefault(); e.stopPropagation(); try { const li = clearTitle.closest('li'); const inp = li && li.querySelector('.wf-dash-custom-title'); if (inp) { inp.value=''; inp.dispatchEvent(new Event('input', { bubbles:true })); } } catch(_) {} return; }
              const clearDesc = closest('[data-action="dashboard-clear-desc"]');
              if (clearDesc) { e.preventDefault(); e.stopPropagation(); try { const li = clearDesc.closest('li'); const inp = li && li.querySelector('.wf-dash-custom-desc'); if (inp) { inp.value=''; inp.dispatchEvent(new Event('input', { bubbles:true })); } } catch(_) {} return; }
              if (closest('[data-action="open-email-settings"]')) { e.preventDefault(); e.stopPropagation(); showModal('emailSettingsModal'); return; }
              if (closest('[data-action="open-square-settings"]')) { e.preventDefault(); e.stopPropagation(); showModal('squareSettingsModal'); return; }
              if (closest('[data-action="open-logging-status"]')) { e.preventDefault(); e.stopPropagation(); showModal('loggingStatusModal'); return; }
              if (closest('[data-action="open-ai-settings"]')) { e.preventDefault(); e.stopPropagation(); showModal('aiSettingsModal'); return; }
              if (closest('[data-action="open-ai-tools"]')) { e.preventDefault(); e.stopPropagation(); showModal('aiToolsModal'); return; }
              if (closest('[data-action="open-background-manager"]')) { e.preventDefault(); e.stopPropagation(); showModal('backgroundManagerModal'); return; }
              if (closest('[data-action="open-receipt-settings"]')) { e.preventDefault(); e.stopPropagation(); showModal('receiptSettingsModal'); return; }
              if (closest('[data-action="open-secrets-modal"]')) { e.preventDefault(); e.stopPropagation(); showModal('secretsModal'); return; }

              if (closest('[data-action="close-admin-modal"]')) {
                e.preventDefault(); e.stopPropagation();
                const overlay = t.closest('.admin-modal-overlay');
                if (overlay && overlay.id) hideModal(overlay.id);
                return;
              }

              // Categories: add
              if (closest('[data-action="cat-add"]')) {
                e.preventDefault(); e.stopPropagation();
                const modal = document.getElementById('categoriesModal'); if (!modal) return;
                const input = modal.querySelector('#catNewName'); const result = modal.querySelector('#catResult');
                const name = (input && input.value) ? input.value.trim() : '';
                if (!name) { if (result) result.textContent = 'Please enter a name.'; return; }
                if (result) result.textContent = 'Adding…';
                catApi('/api/categories.php?action=add', { action: 'add', name }).then(function(){
                  if (input) input.value = '';
                  populateCategories(); if (result) result.textContent = 'Added.';
                }).catch(function(err){ if (result) result.textContent = (err && err.message) ? err.message : 'Failed to add.'; });
                return;
              }
              // Categories: rename
              if (closest('[data-action="cat-rename"]')) {
                e.preventDefault(); e.stopPropagation();
                const btn = closest('[data-action="cat-rename"]'); const oldName = btn.getAttribute('data-name')||'';
                const newName = prompt('Rename category', oldName) || '';
                if (!newName || newName.trim() === '' || newName === oldName) return;
                const modal = document.getElementById('categoriesModal'); const result = modal ? modal.querySelector('#catResult') : null;
                if (result) result.textContent = 'Renaming…';
                catApi('/api/categories.php?action=rename', { action: 'rename', old_name: oldName, new_name: newName.trim(), update_items: true })
                  .then(function(){ populateCategories(); if (result) result.textContent = 'Renamed.'; })
                  .catch(function(err){ if (result) result.textContent = (err && err.message) ? err.message : 'Failed to rename.'; });
                return;
              }
              // Categories: delete
              if (closest('[data-action="cat-delete"]')) {
                e.preventDefault(); e.stopPropagation();
                const btn = closest('[data-action="cat-delete"]'); const name = btn.getAttribute('data-name')||'';
                const reassign = prompt(`Delete "${name}". Optionally enter a category to reassign items to (leave blank to cancel if in use).`, '');
                if (reassign === null) return; // user cancelled
                const payload = { action: 'delete', name };
                if (typeof reassign === 'string' && reassign.trim() !== '') payload.reassign_to = reassign.trim();
                const modal = document.getElementById('categoriesModal'); const result = modal ? modal.querySelector('#catResult') : null;
                if (result) result.textContent = 'Deleting…';
                catApi('/api/categories.php?action=delete', payload)
                  .then(function(){ populateCategories(); if (result) result.textContent = 'Deleted.'; })
                  .catch(function(err){ if (result) result.textContent = (err && err.message) ? err.message : 'Failed to delete.'; });
                return;
              }
            } catch(_) {}
          }, true);
        } catch(_) {}
      })();
    </script>
                  </div>
                </div>
              </div>
    <!-- Dashboard Configuration Modal (hidden by default) -->
    <div id="dashboardConfigModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="dashboardConfigTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="dashboardConfigTitle" class="admin-card-title">⚙️ Dashboard Configuration</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="space-y-4">
            <p class="text-sm text-gray-700">Manage which sections appear on your Dashboard. Add/remove from the lists below, then click Save.</p>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <h3 class="text-base font-semibold mb-2">Active Sections</h3>
                <ul id="dashboardActiveSections" class="list-disc pl-5 text-sm text-gray-800"></ul>
              </div>
              <div>
                <h3 class="text-base font-semibold mb-2">Available Sections</h3>
                <ul id="dashboardAvailableSections" class="list-disc pl-5 text-sm text-gray-800"></ul>
              </div>
            </div>
            <div class="flex justify-between items-center">
              <div id="dashboardConfigResult" class="text-sm text-gray-500"></div>
              <div class="flex items-center gap-2">
                <button type="button" class="btn" data-action="dashboard-config-reset">Reset to defaults</button>
                <button type="button" class="btn-secondary" data-action="dashboard-config-refresh">Refresh</button>
                <button type="button" class="btn-primary" data-action="dashboard-config-save">Save</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Logging Status Modal (hidden by default) -->
    <div id="loggingStatusModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 class="admin-card-title">📜 Logging Status</h2>
          <button type="button" class="admin-modal-close" data-action="close-logging-status" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <div class="space-y-3">
            <div id="loggingSummary" class="text-sm text-gray-700">Current log levels and destinations will appear here.</div>
            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn-secondary" data-action="logging-refresh-status">Refresh</button>
              <button type="button" class="btn-secondary" data-action="logging-open-file">Open Latest Log File</button>
              <button type="button" class="btn-danger" data-action="logging-clear-logs">Clear Logs</button>
            </div>
            <div id="loggingStatusResult" class="status status--info"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Secrets Manager Modal (hidden by default) -->
    <div id="secretsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 class="admin-card-title">🔒 Secrets Manager</h2>
          <button type="button" class="admin-modal-close" data-action="close-secrets-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <form id="secretsForm" data-action="prevent-submit" class="space-y-4">
            <p class="text-sm text-gray-700">Paste JSON or key=value lines to update secrets. Sensitive values are never prefilled.</p>
            <textarea id="secretsPayload" name="secrets_payload" class="form-textarea w-full" rows="8" placeholder='{"SMTP_PASS":"..."}'></textarea>
            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn-primary" data-action="secrets-save">Save Secrets</button>
              <button type="button" class="btn-secondary" data-action="secrets-rotate">Rotate Keys</button>
              <button type="button" class="btn-secondary" data-action="secrets-export">Export Secrets</button>
            </div>
            <div id="secretsResult" class="status status--info"></div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
?>
<!-- WF: SETTINGS WRAPPER END -->

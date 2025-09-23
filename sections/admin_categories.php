<?php
// sections/admin_categories.php — Primary implementation for Category Management

// Load the lightweight modal header to get CSS/JS
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

// Authentication check - case insensitive
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (!isAdminWithToken()) {
    echo '<div class="p-4 text-danger">Access denied.</div>';
    echo '</body></html>'; // Close the document
    return;
}

try {
    // Fetch categories from items
    $categoriesFromItems = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
    $categories = array_column($categoriesFromItems, 'category');

    // Fetch canonical categories for dropdowns
    $canonicalCategories = Database::queryAll("SELECT id, name FROM categories ORDER BY name");
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
<style>
    .btn {
        display: inline-block;
        padding: 0.5rem 1rem;
        font-weight: 500;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        text-decoration: none;
        border: 1px solid transparent;
    }
    .btn-brand {
        background-color: var(--brand-primary, #0ea5e9);
        color: white;
        border-color: var(--brand-primary, #0ea5e9);
    }
    .btn-brand:hover {
        opacity: 0.9;
    }
    .btn-secondary {
        background-color: #e2e8f0;
        color: #2d3748;
        border-color: #e2e8f0;
    }
    .btn-secondary:hover {
        background-color: #cbd5e0;
    }
</style>
<div class="p-4">
    <h1 class="admin-title">Category Management</h1>

    <!-- Tabs Navigation -->
    <div class="admin-card" style="margin: 8px 0;">
      <div class="admin-form-inline" role="tablist" aria-label="Category Management Tabs" style="gap: 8px;">
        <button type="button" id="tabBtnCategories" class="btn btn-brand" aria-selected="true" aria-controls="tabPanelCategories">Categories</button>
        <button type="button" id="tabBtnAssignments" class="btn" aria-selected="false" aria-controls="tabPanelAssignments">Assignments</button>
        <button type="button" id="tabBtnOverview" class="btn" aria-selected="false" aria-controls="tabPanelOverview">Overview</button>
        <button type="button" id="tabBtnSkuRules" class="btn" aria-selected="false" aria-controls="tabPanelSkuRules">SKU Rules</button>
      </div>
    </div>

    <!-- Tab Panels -->
    <div id="tabPanelCategories" role="tabpanel" aria-labelledby="tabBtnCategories">
    
    <?php if ($message): ?>
        <div class="admin-alert <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Add Category Form -->
    <div class="admin-card">
        <h3 class="admin-card-title">Add New Category</h3>
        <form id="addCategoryForm" class="admin-form-inline">
            <input type="text" id="newCategory" name="newCategory" 
                   placeholder="Enter category name..." class="form-input" required>
            <button type="submit" class="btn btn-brand">Add Category</button>
        </form>
    </div>

    <!-- Categories List -->
    <div class="admin-card">
        <?php if (empty($categories)): ?>
            <div class="admin-empty-state">
                <div class="empty-icon">📂</div>
                <div class="empty-title">No categories found</div>
                <div class="empty-subtitle">Add your first category above to get started.</div>
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
                        <tr data-category="<?= htmlspecialchars($cat) ?>">
                            <td class="editable-cell" data-field="category_name" data-original-value="<?= htmlspecialchars($cat) ?>">
                                <span class="cell-text"><?= htmlspecialchars($cat) ?></span>
                            </td>
                            <td class="editable-cell" data-field="sku_code" data-original-value="<?= htmlspecialchars($code) ?>">
                                <span class="cell-text code-badge"><?= htmlspecialchars($code) ?></span>
                            </td>
                            <td><span class="code-badge"><?= htmlspecialchars($exampleSku) ?></span></td>
                            <td>
                                <button class="text-red-600 hover:text-red-800 delete-category-btn" 
                                        data-category="<?= htmlspecialchars($cat) ?>" title="Delete Category">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    </div> <!-- end of tabPanelCategories -->

    <!-- Assignments Panel (initially hidden) -->
    <div id="tabPanelAssignments" role="tabpanel" aria-labelledby="tabBtnAssignments" style="display:none">
      <div class="admin-card">
        <h3 class="admin-card-title">Room-Category Assignments</h3>
        <div id="rcAssignmentsContainer" class="admin-table-wrapper">
          <div class="text-gray-600 text-sm">Loading assignments…</div>
        </div>

        <div class="admin-card" style="margin-top: 20px;">
          <h3 class="admin-card-title">Add New Assignment</h3>
          <form id="addAssignmentForm" class="admin-form-inline">
            <select id="roomNumber" name="roomNumber" class="form-input">
              <option value="1">Room 1</option>
              <option value="2">Room 2</option>
              <option value="3">Room 3</option>
              <option value="4">Room 4</option>
              <option value="5">Room 5</option>
            </select>
            <select id="categoryId" name="categoryId" class="form-input">
              <?php foreach ($canonicalCategories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-brand">Add Assignment</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Overview Panel (per-room summary) -->
    <div id="tabPanelOverview" role="tabpanel" aria-labelledby="tabBtnOverview" style="display:none">
      <div class="admin-card">
        <h3 class="admin-card-title">Per-Room Overview</h3>
        <div id="rcOverviewContainer" class="space-y-2">
          <div class="text-gray-600 text-sm">Loading overview…</div>
        </div>
      </div>
    </div>

    <div id="tabPanelSkuRules" role="tabpanel" aria-labelledby="tabBtnSkuRules" style="display:none">
      <div class="admin-card">
        <h3 class="admin-card-title">SKU Naming Rules</h3>
        <div id="skuRulesContainer"></div>
      </div>
    </div>

    <script>
      (function() {
        const tabs = [
          {btn: 'tabBtnCategories', panel: 'tabPanelCategories'},
          {btn: 'tabBtnAssignments', panel: 'tabPanelAssignments'},
          {btn: 'tabBtnOverview', panel: 'tabPanelOverview'},
          {btn: 'tabBtnSkuRules', panel: 'tabPanelSkuRules'}
        ];
        function showPanel(key) {
          tabs.forEach(t => {
            const btn = document.getElementById(t.btn);
            const panel = document.getElementById(t.panel);
            const active = (t.panel === key);
            if (btn) btn.setAttribute('aria-selected', active ? 'true' : 'false');
            if (panel) panel.style.display = active ? '' : 'none';
            if (btn) {
                btn.classList.toggle('btn-brand', active);
                if (!active) btn.classList.remove('btn-brand');
            }
          });
          if (key === 'tabPanelOverview') loadOverview();
          if (key === 'tabPanelAssignments') loadAssignments();
          if (key === 'tabPanelSkuRules') loadSkuRules();
        }
        tabs.forEach(t => {
          const btn = document.getElementById(t.btn);
          if (btn) btn.addEventListener('click', () => showPanel(t.panel));
        });
        showPanel('tabPanelCategories'); // default

        async function fetchJSON(url, options) {
          const opts = options || {};
          const init = { credentials: 'same-origin', ...opts };
          if (opts && opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
            init.headers = { 'Content-Type': 'application/json', ...(opts.headers || {}) };
            init.body = JSON.stringify(opts.body);
          }
          const res = await fetch(url, init);
          if (!res.ok) {
              const text = await res.text().catch(() => '');
              let errorMsg = `HTTP error! status: ${res.status}`;
              try {
                  const jsonError = JSON.parse(text);
                  errorMsg = jsonError.message || text;
              } catch (e) { errorMsg = text || errorMsg; }
              throw new Error(errorMsg);
          }
          return res.json();
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
            const table = document.createElement('table');
            table.className = 'admin-table';
            table.innerHTML = `
              <thead>
                <tr>
                  <th>Room</th>
                  <th>Category</th>
                  <th>Order</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                ${data.assignments.map(a => `
                  <tr>
                    <td>${a.room_number}</td>
                    <td>${a.category_name}</td>
                    <td>${a.display_order}</td>
                    <td>
                      <button class="btn-danger" data-action="delete-assignment" data-id="${a.id}">Delete</button>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            `;
            el.innerHTML = '';
            el.appendChild(table);

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
                  alert('Failed to add assignment: ' + result.message);
                }
              } catch (err) {
                alert('An error occurred: ' + err.message);
              }
            });

            table.addEventListener('click', async (e) => {
              if (e.target.dataset.action === 'delete-assignment') {
                const id = e.target.dataset.id;
                if (confirm('Are you sure you want to delete this assignment?')) {
                  try {
                    const result = await fetchJSON('/api/room_category_assignments.php', {
                        method: 'DELETE',
                        body: { assignment_id: id }
                    });
                    if (result.success) {
                      loadAssignments();
                    } else {
                      alert('Failed to delete assignment: ' + result.message);
                    }
                  } catch (err) {
                    alert('An error occurred: ' + err.message);
                  }
                }
              }
            });
          } catch (e) {
            el.innerHTML = `<div class="text-danger">Error loading assignments: ${e.message}</div>`;
          }
        }

        async function loadSkuRules() {
          const el = document.getElementById('skuRulesContainer');
          if (!el) return;
          el.innerHTML = '<div class="text-gray-600 text-sm">Loading SKU rules…</div>';
          try {
            const data = await fetchJSON('/api/sku_rules.php');
            if (!data?.success) throw new Error(data?.message || 'Failed to load SKU rules');

            const table = document.createElement('table');
            table.className = 'admin-table';
            table.innerHTML = `
              <thead>
                <tr>
                  <th>Category Name</th>
                  <th>SKU Prefix</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                ${data.rules.map(r => `
                  <tr>
                    <td><input type="text" class="form-input" value="${r.category_name}" data-id="${r.id}" data-field="category_name"></td>
                    <td><input type="text" class="form-input" value="${r.sku_prefix}" data-id="${r.id}" data-field="sku_prefix"></td>
                    <td>
                      <button class="btn btn-brand" data-action="update-sku-rule" data-id="${r.id}">Update</button>
                      <button class="btn-danger" data-action="delete-sku-rule" data-id="${r.id}">Delete</button>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            `;
            el.innerHTML = '';
            el.appendChild(table);

            const form = document.createElement('form');
            form.id = 'addSkuRuleForm';
            form.className = 'admin-form-inline';
            form.innerHTML = `
              <input type="text" id="newSkuCategoryName" placeholder="Category Name" class="form-input" required>
              <input type="text" id="newSkuPrefix" placeholder="SKU Prefix" class="form-input" required>
              <button type="submit" class="btn btn-brand">Add Rule</button>
            `;
            el.appendChild(form);

            form.addEventListener('submit', async (e) => {
              e.preventDefault();
              const categoryName = document.getElementById('newSkuCategoryName').value;
              const skuPrefix = document.getElementById('newSkuPrefix').value;
              try {
                await fetchJSON('/api/sku_rules.php', { method: 'POST', body: { category_name: categoryName, sku_prefix: skuPrefix } });
                loadSkuRules();
              } catch (err) {
                alert('Error adding rule: ' + err.message);
              }
            });

            table.addEventListener('click', async (e) => {
              const action = e.target.dataset.action;
              const id = e.target.dataset.id;
              if (action === 'delete-sku-rule') {
                if (!confirm('Are you sure?')) return;
                try {
                  await fetchJSON('/api/sku_rules.php', { method: 'DELETE', body: { id } });
                  loadSkuRules();
                } catch (err) {
                  alert('Error deleting rule: ' + err.message);
                }
              } else if (action === 'update-sku-rule') {
                const row = e.target.closest('tr');
                const categoryName = row.querySelector('[data-field="category_name"]').value;
                const skuPrefix = row.querySelector('[data-field="sku_prefix"]').value;
                try {
                  await fetchJSON('/api/sku_rules.php', { method: 'PUT', body: { id, category_name: categoryName, sku_prefix: skuPrefix } });
                  loadSkuRules();
                } catch (err) {
                  alert('Error updating rule: ' + err.message);
                }
              }
            });
          } catch (e) {
            el.innerHTML = `<div class="text-danger">Error loading SKU rules: ${e.message}</div>`;
          }
        }

        document.getElementById('categoryTableBody').addEventListener('click', function(e) {
            const targetCell = e.target.closest('.editable-cell');
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
            saveBtn.className = 'btn btn-brand btn-sm ml-2';

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
                        alert('Update failed: ' + response.message);
                        revertUI();
                    }
                } catch (error) {
                    alert('An error occurred: ' + error.message);
                    revertUI();
                }
            };
        });
      })();
    </script>
</div>
</body>
</html>

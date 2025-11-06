<?php
// Cost Breakdown Manager — sections/tools primary implementation

// Error reporting (optional during development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/api/config.php';

if (class_exists('Auth')) {
    Auth::requireAdmin();
} elseif (function_exists('requireAdmin')) {
    requireAdmin();
}

// Initialize variables
$message = '';
$messageType = '';

// Connect to database and load items (use items + SKU to align with cost breakdown API)
try {
    Database::getInstance();
    $inventoryItems = Database::queryAll("SELECT sku AS id, name, category, costPrice FROM items ORDER BY name");
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "error";
    $inventoryItems = [];
} catch (Exception $e) {
    error_log('DB init failed: ' . $e->getMessage());
    $inventoryItems = [];
}

// Selected item (load by SKU)
$selectedItemId = $_GET['item'] ?? '';
$selectedItem = null;
if (!empty($selectedItemId)) {
    try {
        $selectedItem = Database::queryOne("SELECT * FROM items WHERE sku = ?", [$selectedItemId]);
    } catch (PDOException $e) {
        $message = 'Error retrieving item: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Header include (only if root layout not bootstrapped)
$is_modal_context = isset($_GET['modal']) || strpos($_SERVER['HTTP_REFERER'] ?? '', 'admin_settings') !== false;
$__wf_included_layout = false;
if (!$is_modal_context && !function_exists('__wf_admin_root_footer_shutdown')) {
    $page = 'admin/cost-breakdown-manager';
    include dirname(__DIR__, 2) . '/partials/header.php';
    $__wf_included_layout = true;
}
if ($is_modal_context) {
    // Minimal modal header to enable embed autosize child and shared styles
    include dirname(__DIR__, 2) . '/partials/modal_header.php';
}
?>
    <!-- Toast Notification -->
    <div id="toast" class="toast-notification"></div>
    
    <div class="">
        <div class="flex justify-between items-center">
            <h1 class="text-brand-primary">Cost Breakdown Manager</h1>
            <?php if (!$is_modal_context): ?>
            <a href="/admin/?section=inventory" class="btn btn-secondary btn-sm">
                Back to Inventory
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Item Selection -->
        <div class="card-standard">
            <h2 class="text-brand-primary">Select Inventory Item</h2>
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <select id="itemSelector" class="w-full border border-gray-300 rounded-brand">
                        <option value="">- Select an item -</option>
                        <?php foreach ($inventoryItems as $item): ?>
                        <option value="<?php echo htmlspecialchars($item['id']); ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>" data-category="<?php echo htmlspecialchars($item['category'] ?? ''); ?>" data-cost="<?php echo htmlspecialchars((string)($item['costPrice'] ?? '0')); ?>" <?php echo ($selectedItemId === $item['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['name']); ?> (<?php echo htmlspecialchars($item['id']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button id="loadItemBtn" class="btn btn-primary btn-sm">
                        Load Item
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Cost Breakdown Display -->
        <div id="costBreakdownContainer" class="<?php echo empty($selectedItemId) ? 'hidden' : ''; ?>">
            <!-- Item Details -->
            <div class="card-standard">
                <div class="flex justify-between items-center">
                    <h2 class="text-brand-primary" id="itemNameDisplay">
                        <?php echo htmlspecialchars($selectedItem['name'] ?? 'No Item Selected'); ?>
                    </h2>
                    <span class="bg-gray-200 text-gray-700 rounded-brand text-sm" id="itemIdDisplay">
                        ID: <?php echo htmlspecialchars($selectedItem['id'] ?? 'N/A'); ?>
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <span class="text-gray-600 text-sm">Category:</span>
                        <div id="categoryDisplay" class="font-medium"><?php echo htmlspecialchars($selectedItem['category'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <span class="text-gray-600 text-sm">Current Cost Price:</span>
                        <div id="itemCostDisplay" class="font-medium">$<?php echo number_format($selectedItem['costPrice'] ?? 0, 2); ?></div>
                    </div>
                    <div>
                        <span class="text-gray-600 text-sm">Retail Price:</span>
                        <div id="retailPriceDisplay" class="font-medium">$<?php echo number_format($selectedItem['retailPrice'] ?? 0, 2); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Cost Breakdown Sections in 3 columns -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Column 1: Materials -->
                <div>
                    <div class="card-standard">
                        <div class="flex justify-between items-center">
                            <h3 class="text-brand-primary">Materials</h3>
                            <button id="addMaterialBtn" class="btn btn-primary btn-sm">+ Add Material</button>
                        </div>
                        <div id="materialsList" class="mt-4 space-y-2">
                            <div class="text-center text-gray-500 italic" id="noMaterialsMsg">No materials added yet</div>
                        </div>
                    </div>
                </div>

                <!-- Column 2: Labor -->
                <div>
                    <div class="card-standard">
                        <div class="flex justify-between items-center">
                            <h3 class="text-brand-primary">Labor</h3>
                            <button id="addLaborBtn" class="btn btn-primary btn-sm">+ Add Labor</button>
                        </div>
                        <div id="laborList" class="divide-y divide-gray-200">
                            <div class="text-center text-gray-500 italic" id="noLaborMsg">No labor costs added yet</div>
                        </div>
                    </div>
                </div>

                <!-- Column 3: Summary and Energy -->
                <div>
                    <div class="card-standard">
                        <h3 class="text-brand-primary">Cost Summary</h3>
                        <div class="space-y-2 mt-4">
                            <div class="flex justify-between">
                                <span>Materials Total:</span>
                                <span id="materialsTotalDisplay" class="cost-item-value">$0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Labor Total:</span>
                                <span id="laborTotalDisplay" class="cost-item-value">$0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Energy Total:</span>
                                <span id="energyTotalDisplay" class="cost-item-value">$0.00</span>
                            </div>
                            <div class="flex justify-between font-bold border-t border-gray-300">
                                <span>Suggested Cost Price:</span>
                                <span id="suggestedCostDisplay" class="font-bold text-brand-primary">$0.00</span>
                            </div>
                            <div class="mt-2 pt-2 border-t border-gray-200">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Current Cost Price:</span>
                                    <span id="currentCostDisplay" class="font-medium">$0.00</span>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button id="updateCostBtn" class="btn btn-primary btn-sm">
                                    Update Cost Price
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-standard mt-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-brand-primary">Energy</h3>
                            <button id="addEnergyBtn" class="btn btn-primary btn-sm">Add Energy</button>
                        </div>
                        <div id="energyList" class="divide-y divide-gray-200">
                            <div class="text-center text-gray-500 italic" id="noEnergyMsg">No energy costs added yet</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Empty State -->
        <div id="emptyStateContainer" class="<?php echo !empty($selectedItemId) ? 'hidden' : ''; ?> bg-white rounded-lg shadow-md text-center">
            <svg class="w-20 h-20 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
            </svg>
            <h2 class="text-xl font-semibold text-gray-700">No Item Selected</h2>
            <p class="text-gray-500">Please select an inventory item to manage its cost breakdown</p>
        </div>
    </div>

    <script>
        let currentItemId = <?php echo json_encode($selectedItemId ?: null); ?>;
        let costBreakdown = { materials: [], labor: [], energy: [], totals: { materialTotal: 0, laborTotal: 0, energyTotal: 0, suggestedCost: 0 } };

        document.getElementById('loadItemBtn').addEventListener('click', onItemLoad);

        document.getElementById('itemSelector').addEventListener('change', onItemLoad);

        function onItemLoad(){
            const sel = document.getElementById('itemSelector');
            if (!sel || !sel.value) return;
            currentItemId = sel.value;
            // Update header fields from selected option's data
            const opt = sel.options[sel.selectedIndex];
            if (opt) {
                const name = opt.getAttribute('data-name') || '';
                const cat = opt.getAttribute('data-category') || '';
                const cost = opt.getAttribute('data-cost') || '0';
                const idEl = document.getElementById('itemIdDisplay');
                const nmEl = document.getElementById('itemNameDisplay');
                if (nmEl) nmEl.textContent = name || (`Item ${currentItemId}`);
                if (idEl) idEl.textContent = `ID: ${currentItemId}`;
                const catEl = document.getElementById('categoryDisplay');
                if (catEl && cat) catEl.textContent = cat;
                const itemCostEl = document.getElementById('itemCostDisplay');
                if (itemCostEl) itemCostEl.textContent = `$${parseFloat(cost||'0').toFixed(2)}`;
            }
            document.getElementById('costBreakdownContainer').classList.remove('hidden');
            document.getElementById('emptyStateContainer').classList.add('hidden');
            loadCostBreakdown(currentItemId);
        }

        // Auto-load if an item is preselected on initial render
        (function(){
            try {
                const sel = document.getElementById('itemSelector');
                if ((currentItemId && String(currentItemId).length) || (sel && sel.value)) {
                    onItemLoad();
                }
            } catch(_){}
        })();

        document.getElementById('addMaterialBtn').addEventListener('click', function() {
            openAddModal('material');
        });
        document.getElementById('addLaborBtn').addEventListener('click', function() {
            openAddModal('labor');
        });
        document.getElementById('addEnergyBtn').addEventListener('click', function() {
            openAddModal('energy');
        });
        document.getElementById('updateCostBtn').addEventListener('click', function() { openUpdateCostModal(); });

        // Load cost breakdown data
        function loadCostBreakdown(itemId) {
            showLoading();
            __wfApiJson('GET', '/functions/process_cost_breakdown.php?inventoryId=' + itemId + '&costType=all')
                .then(data => {
                    if (data.success) {
                        costBreakdown = data.data;
                        displayCostBreakdown();
                    } else {
                        showError('Failed to load cost breakdown. Please try again.');
                    }
                    hideLoading();
                })
                .catch(err => { console.error(err); showError('Failed to load cost breakdown.'); hideLoading(); });
        }

        function displayCostBreakdown() {
            const materialsList = document.getElementById('materialsList');
            materialsList.innerHTML = '';
            if (costBreakdown.materials.length > 0) {
                document.getElementById('noMaterialsMsg').classList.add('hidden');
                costBreakdown.materials.forEach(material => {
                    const el = document.createElement('div');
                    el.className = 'py-3 flex justify-between items-center';
                    el.innerHTML = `
                        <div><span class="font-medium">${escapeHtml(material.name)}</span></div>
                        <div class="flex items-center">
                            <span class="font-semibold">$${parseFloat(material.cost).toFixed(2)}</span>
                            <button class="admin-action-button btn btn-xs btn-icon btn-icon--edit" data-action="openEditModal" data-params='{"type":"material","id":${material.id}}' title="Edit Material" aria-label="Edit Material"></button>
                            <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="openDeleteModal" data-params='{"type":"material","id":${material.id},"name":"${escapeHtml(material.name)}"}' title="Delete Material" aria-label="Delete Material"></button>
                        </div>`;
                    materialsList.appendChild(el);
                });
            } else {
                document.getElementById('noMaterialsMsg').classList.remove('hidden');
            }

            const laborList = document.getElementById('laborList');
            laborList.innerHTML = '';
            if (costBreakdown.labor.length > 0) {
                document.getElementById('noLaborMsg').classList.add('hidden');
                costBreakdown.labor.forEach(labor => {
                    const el = document.createElement('div');
                    el.className = 'py-3 flex justify-between items-center';
                    el.innerHTML = `
                        <div><span class="font-medium">${escapeHtml(labor.description)}</span></div>
                        <div class="flex items-center">
                            <span class="font-semibold">$${parseFloat(labor.cost).toFixed(2)}</span>
                            <button class="admin-action-button btn btn-xs btn-icon btn-icon--edit" data-action="openEditModal" data-params='{"type":"labor","id":${labor.id}}' title="Edit Labor" aria-label="Edit Labor"></button>
                            <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="openDeleteModal" data-params='{"type":"labor","id":${labor.id},"name":"${escapeHtml(labor.description)}"}' title="Delete Labor" aria-label="Delete Labor"></button>
                        </div>`;
                    laborList.appendChild(el);
                });
            } else {
                document.getElementById('noLaborMsg').classList.remove('hidden');
            }

            const energyList = document.getElementById('energyList');
            energyList.innerHTML = '';
            if (costBreakdown.energy.length > 0) {
                document.getElementById('noEnergyMsg').classList.add('hidden');
                costBreakdown.energy.forEach(energy => {
                    const el = document.createElement('div');
                    el.className = 'py-3 flex justify-between items-center';
                    el.innerHTML = `
                        <div><span class="font-medium">${escapeHtml(energy.description)}</span></div>
                        <div class="flex items-center">
                            <span class="font-semibold">$${parseFloat(energy.cost).toFixed(2)}</span>
                            <button class="admin-action-button btn btn-xs btn-icon btn-icon--edit" data-action="openEditModal" data-params='{"type":"energy","id":${energy.id}}' title="Edit Energy" aria-label="Edit Energy"></button>
                            <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="openDeleteModal" data-params='{"type":"energy","id":${energy.id},"name":"${escapeHtml(energy.description)}"}' title="Delete Energy" aria-label="Delete Energy"></button>
                        </div>`;
                    energyList.appendChild(el);
                });
            } else {
                document.getElementById('noEnergyMsg').classList.remove('hidden');
            }

            document.getElementById('materialsTotalDisplay').textContent = '$' + costBreakdown.totals.materialTotal.toFixed(2);
            document.getElementById('laborTotalDisplay').textContent = '$' + costBreakdown.totals.laborTotal.toFixed(2);
            document.getElementById('energyTotalDisplay').textContent = '$' + costBreakdown.totals.energyTotal.toFixed(2);
            document.getElementById('suggestedCostDisplay').textContent = '$' + costBreakdown.totals.suggestedCost.toFixed(2);

            const currentCostText = document.getElementById('itemCostDisplay')?.textContent || '0';
            const currentCost = currentCostText.replace('$', '');
            document.getElementById('currentCostDisplay').textContent = '$' + parseFloat(currentCost).toFixed(2);

            animateElement('materialsTotalDisplay');
            animateElement('laborTotalDisplay');
            animateElement('energyTotalDisplay');
            animateElement('suggestedCostDisplay');
        }

        function openAddModal(type) {
            resetForm(type);
            document.getElementById(type + 'ModalTitle').textContent = 'Add ' + capitalizeFirstLetter(type);
            document.getElementById(type + 'InventoryId').value = currentItemId;
            document.getElementById(type + 'Id').value = '';
            openModal(type + 'Modal');
        }
        function openEditModal(type, id) {
            resetForm(type);
            document.getElementById(type + 'ModalTitle').textContent = 'Edit ' + capitalizeFirstLetter(type);
            document.getElementById(type + 'Id').value = id;
            document.getElementById(type + 'InventoryId').value = currentItemId;
            let item;
            if (type === 'material') {
                item = costBreakdown.materials.find(m => m.id == id);
                if (item) { document.getElementById('materialName').value = item.name; document.getElementById('materialCost').value = parseFloat(item.cost).toFixed(2); }
            } else if (type === 'labor') {
                item = costBreakdown.labor.find(l => l.id == id);
                if (item) { document.getElementById('laborDescription').value = item.description; document.getElementById('laborCost').value = parseFloat(item.cost).toFixed(2); }
            } else if (type === 'energy') {
                item = costBreakdown.energy.find(e => e.id == id);
                if (item) { document.getElementById('energyDescription').value = item.description; document.getElementById('energyCost').value = parseFloat(item.cost).toFixed(2); }
            }
            openModal(type + 'Modal');
        }
        function openDeleteModal(type, id, name) {
            document.getElementById('deleteItemId').value = id;
            document.getElementById('deleteItemType').value = type;
            let itemTypeDisplay = type === 'labor' ? 'labor cost' : (type === 'energy' ? 'energy cost' : 'material');
            document.getElementById('deleteConfirmText').textContent = `Are you sure you want to delete the ${itemTypeDisplay} "${name}"?`;
            openModal('deleteModal');
        }
        function openUpdateCostModal() {
            const currentCost = document.getElementById('currentCostDisplay').textContent.replace('$', '');
            const suggestedCost = costBreakdown.totals.suggestedCost.toFixed(2);
            document.getElementById('updateCurrentCost').textContent = '$' + parseFloat(currentCost).toFixed(2);
            document.getElementById('updateSuggestedCost').textContent = '$' + suggestedCost;
            openModal('updateCostModal');
        }

        function saveMaterial() {
            const id = document.getElementById('materialId').value;
            const inventoryId = document.getElementById('materialInventoryId').value;
            const name = document.getElementById('materialName').value;
            const cost = document.getElementById('materialCost').value;
            const isEdit = id !== '';
            const method = isEdit ? 'PUT' : 'POST';
            const url = isEdit ? `/functions/process_cost_breakdown.php?inventoryId=${inventoryId}&costType=materials&id=${id}` : `/functions/process_cost_breakdown.php?inventoryId=${inventoryId}`;
            const data = { costType: 'materials', name, cost: parseFloat(cost) };
            showFormLoading('material');
            __wfApiJson(method, url, data)
            .then(result => { if (result.success) { closeModal('materialModal'); showSuccess(result.message); loadCostBreakdown(inventoryId); } else { showError('Error: ' + result.error); } hideFormLoading('material'); })
            .catch(() => { showError('Failed to save material. Please try again.'); hideFormLoading('material'); });
        }
        function saveLabor() {
            const id = document.getElementById('laborId').value;
            const inventoryId = document.getElementById('laborInventoryId').value;
            const description = document.getElementById('laborDescription').value;
            const cost = document.getElementById('laborCost').value;
            const isEdit = id !== '';
            const method = isEdit ? 'PUT' : 'POST';
            const url = isEdit ? `/functions/process_cost_breakdown.php?inventoryId=${inventoryId}&costType=labor&id=${id}` : `/functions/process_cost_breakdown.php?inventoryId=${inventoryId}`;
            const data = { costType: 'labor', description, cost: parseFloat(cost) };
            showFormLoading('labor');
            __wfApiJson(method, url, data)
            .then(result => { if (result.success) { closeModal('laborModal'); showSuccess(result.message); loadCostBreakdown(inventoryId); } else { showError('Error: ' + result.error); } hideFormLoading('labor'); })
            .catch(() => { showError('Failed to save labor. Please try again.'); hideFormLoading('labor'); });
        }
        function saveEnergy() {
            const id = document.getElementById('energyId').value;
            const inventoryId = document.getElementById('energyInventoryId').value;
            const description = document.getElementById('energyDescription').value;
            const cost = document.getElementById('energyCost').value;
            const isEdit = id !== '';
            const method = isEdit ? 'PUT' : 'POST';
            const url = isEdit ? `/functions/process_cost_breakdown.php?inventoryId=${inventoryId}&costType=energy&id=${id}` : `/functions/process_cost_breakdown.php?inventoryId=${inventoryId}`;
            const data = { costType: 'energy', description, cost: parseFloat(cost) };
            showFormLoading('energy');
            __wfApiJson(method, url, data)
            .then(result => { if (result.success) { closeModal('energyModal'); showSuccess(result.message); loadCostBreakdown(inventoryId); } else { showError('Error: ' + result.error); } hideFormLoading('energy'); })
            .catch(() => { showError('Failed to save energy. Please try again.'); hideFormLoading('energy'); });
        }
        function deleteItem(id, type) {
            const url = `/functions/process_cost_breakdown.php?inventoryId=${currentItemId}&costType=${type}&id=${id}`;
            showFormLoading('delete');
            __wfApiJson('DELETE', url)
            .then(result => { if (result.success) { closeModal('deleteModal'); showSuccess(result.message); loadCostBreakdown(currentItemId); } else { showError('Error: ' + result.error); } hideFormLoading('delete'); })
            .catch(() => { showError('Failed to delete item. Please try again.'); hideFormLoading('delete'); });
        }
        function updateCostPrice() {
            const suggestedCost = costBreakdown.totals.suggestedCost;
            showFormLoading('updateCost');
            __wfApiJson('POST', '/functions/process_inventory_update.php', { id: currentItemId, costPrice: suggestedCost })
            .then(result => {
                if (result.success) {
                    closeModal('updateCostModal');
                    showSuccess('Cost price updated successfully');
                    document.getElementById('itemCostDisplay').textContent = '$' + suggestedCost.toFixed(2);
                    document.getElementById('currentCostDisplay').textContent = '$' + suggestedCost.toFixed(2);
                    animateElement('itemCostDisplay');
                    animateElement('currentCostDisplay');
                } else { showError('Error: ' + (result.error || 'Failed to update cost price')); }
                hideFormLoading('updateCost');
            })
            .catch(() => { showError('Failed to update cost price. Please try again.'); hideFormLoading('updateCost'); });
        }

        // Unified API helper for JSON endpoints (prefers WhimsicalFrog.api/ApiClient)
        async function __wfApiJson(method, url, body){
            try {
                const A = (window.WhimsicalFrog && window.WhimsicalFrog.api) ? window.WhimsicalFrog.api : (window.ApiClient || null);
                if (A && typeof A.request === 'function') {
                    if (method === 'GET' && A.get) return A.get(url);
                    if (method === 'POST' && A.post) return A.post(url, body||{});
                    if (method === 'PUT' && A.put) return A.put(url, body||{});
                    if (method === 'DELETE' && A.delete) return A.delete(url);
                    return A.request(url, { method, body: body ? JSON.stringify(body) : undefined, headers: { 'Content-Type': 'application/json' } });
                }
            } catch(_){ }
            const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-WF-ApiClient': '1' }, body: body ? JSON.stringify(body) : undefined, credentials: 'include' });
            return res.json();
        }

        // Helpers
        function __wfFindParentOverlay(){
            try {
                const pd = window.parent && window.parent.document;
                if (!pd) return null;
                const ifr = Array.from(pd.querySelectorAll('iframe')).find(f => { try { return f.contentWindow === window; } catch(_) { return false; } });
                if (!ifr) return null;
                return ifr.closest('.admin-modal-overlay');
            } catch(_) { return null; }
        }
        function openModal(id){
            try {
                const ov = __wfFindParentOverlay();
                if (ov && ov.id && window.parent && typeof window.parent.showModal === 'function') { try { window.parent.showModal(ov.id); } catch(_){} }
                if (ov) { try { ov.classList.add('wf-dim-backdrop'); } catch(_){} }
            } catch(_){}
            const el = document.getElementById(id);
            if (el) el.classList.add('show');
        }
        function closeModal(id){
            const el = document.getElementById(id);
            if (el) el.classList.remove('show');
            try {
                const anyOpen = !!document.querySelector('.admin-modal-overlay.show');
                if (anyOpen) return;
                const ov = __wfFindParentOverlay();
                if (ov) { try { ov.classList.remove('wf-dim-backdrop'); } catch(_){} }
            } catch(_){}
        }
        function resetForm(type){ if(type==='material'){ materialName.value=''; materialCost.value=''; } if(type==='labor'){ laborDescription.value=''; laborCost.value=''; } if(type==='energy'){ energyDescription.value=''; energyCost.value=''; } }
        function showLoading(){}
        function hideLoading(){}
        function showFormLoading(t){ document.getElementById(t+'SubmitText').classList.add('hidden'); document.getElementById(t+'SubmitSpinner').classList.remove('hidden'); }
        function hideFormLoading(t){ document.getElementById(t+'SubmitText').classList.remove('hidden'); document.getElementById(t+'SubmitSpinner').classList.add('hidden'); }
        function animateElement(id){ const el=document.getElementById(id); if(!el) return; el.classList.remove('highlight-change'); void el.offsetWidth; el.classList.add('highlight-change'); }
        function capitalizeFirstLetter(s){ return s.charAt(0).toUpperCase() + s.slice(1); }
        function escapeHtml(u){ return u.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
    </script>
<?php if ($__wf_included_layout) {
    include dirname(__DIR__, 2) . '/partials/footer.php';
} ?>

<?php if ($is_modal_context): ?>
<?php /* embed resets handled by body[data-embed] utilities */ ?>
<?php endif; ?>

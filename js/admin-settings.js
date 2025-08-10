let mapperIsDrawing = false;
let mapperStartX, mapperStartY;
let mapperCurrentArea = null;
let mapperAreaCount = 0;
const mapperOriginalImageWidth = 1280;
const mapperOriginalImageHeight = 896;



function openSystemConfigModal() {
    // Centralized open to ensure scroll lock via WFModals
    if (typeof window.openModal === 'function') {
        window.openModal('systemConfigModal');
    } else {
        const modal = document.getElementById('systemConfigModal');
        if (modal) { modal.classList.remove('hidden'); modal.classList.add('show'); }
    }
    loadSystemConfiguration();
}

async function loadSystemConfiguration() {
    const loadingDiv = document.getElementById('systemConfigLoading');
    const contentDiv = document.getElementById('systemConfigContent');
    
    // Show loading state
    loadingDiv.style.display = 'block';
    
    try {
        const response = await fetch('/api/get_system_config.php');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Hide loading and populate content
            loadingDiv.style.display = 'none';
            contentDiv.innerHTML = generateSystemConfigHTML(data);
        } else {
            throw new Error(result.error || 'Failed to load system configuration');
        }
    } catch (error) {
        console.error('Error loading system configuration:', error);
        loadingDiv.innerHTML = `
            <div class="modal-loading">
                <div class="text-red-500">⚠️</div>
                <p class="text-red-600">Failed to load system configuration</p>
                <p class="text-sm text-gray-500">${error.message}</p>
                <button onclick="loadSystemConfiguration()" class="bg-orange-500 text-white rounded hover:bg-orange-600">
                    Retry
                </button>
            </div>
        `;
    }
}

function generateSystemConfigHTML(data) {
    const lastOrderDate = data.statistics.last_order_date ? 
        new Date(data.statistics.last_order_date).toLocaleDateString() : 'No orders yet';
    
    return `
        <!-- Current System Architecture -->
        <div class="bg-green-50 border-l-4 border-green-400">
            <h4 class="font-semibold text-green-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                Current System Architecture (Live Data)
            </h4>
            <div class="space-y-3 text-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h5 class="font-semibold text-green-700">🎯 Primary Identifier</h5>
                        <p class="text-green-600"><strong>${data.system_info.primary_identifier}</strong> - Human-readable codes</p>
                        <p class="text-xs text-green-600">Format: ${data.system_info.sku_format}</p>
                        <p class="text-xs text-green-600">Examples: ${data.sample_skus.slice(0, 3).join(', ')}</p>
                    </div>
                    <div>
                        <h5 class="font-semibold text-green-700">🏷️ Main Entity</h5>
                        <p class="text-green-600"><strong>${data.system_info.main_entity}</strong></p>
                        <p class="text-xs text-green-600">All inventory and shop items</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comprehensive SKU Methodology Documentation -->
        <div class="bg-blue-50 border-l-4 border-blue-400">
            <h4 class="font-semibold text-blue-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" clip-rule="evenodd"></path>
                </svg>
                📖 Complete SKU & ID Methodology Documentation
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="space-y-3">
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">🏷️ SKU System Overview</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>• <strong>Primary Format:</strong> WF-[CATEGORY]-[NUMBER]</p>
                            <p>• <strong>Enhanced Format:</strong> WF-[CAT]-[GENDER]-[SIZE]-[COLOR]-[NUM]</p>
                            <p>• <strong>Database:</strong> SKU-only system (no legacy IDs)</p>
                            <p>• <strong>Generation:</strong> Automatic via API with sequential numbering</p>
                            <p>• <strong>Usage:</strong> Primary key across all tables</p>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">🔄 Migration History</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>✅ <strong>Phase 1:</strong> Eliminated dual itemId/SKU system</p>
                            <p>✅ <strong>Phase 2:</strong> Migrated "products" → "items" terminology</p>
                            <p>✅ <strong>Phase 3:</strong> Fixed order ID generation (sequence-based)</p>
                            <p>✅ <strong>Phase 4:</strong> Implemented global color/size management</p>
                            <p>✅ <strong>Current:</strong> Pure SKU-only architecture</p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">🛠️ API Endpoints</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>• <code>/api/next_sku.php</code> - Generate new SKUs</p>
                            <p>• <code>/api/get_items.php</code> - Retrieve items by SKU</p>
                            <p>• <code>/api/get_item_images.php</code> - Item images</p>
                            <p>• <code>/api/add-order.php</code> - Create orders (fixed)</p>
                            <p>• <code>/api/update-inventory-field.php</code> - SKU updates</p>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">📊 Current Statistics</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>• <strong>Items:</strong> ${data.statistics.total_items} (${data.statistics.total_images} images)</p>
                            <p>• <strong>Orders:</strong> ${data.statistics.total_orders} (${data.statistics.total_order_items} items)</p>
                            <p>• <strong>Categories:</strong> ${data.statistics.categories_count} active</p>
                            <p>• <strong>Last Order:</strong> ${data.statistics.last_order_date ? new Date(data.statistics.last_order_date).toLocaleDateString() : 'None'}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SKU Categories -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400">
            <h4 class="font-semibold text-yellow-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                </svg>
                Active Categories & SKU Codes
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                ${Object.entries(data.category_codes).map(([category, code]) => {
                    const isActive = data.categories.includes(category);
                    return `
                        <div class="text-center ${isActive ? 'bg-yellow-100' : 'bg-gray-100'} rounded">
                            <div class="font-semibold ${isActive ? 'text-yellow-700' : 'text-gray-500'}">${code}</div>
                            <div class="text-xs ${isActive ? 'text-yellow-600' : 'text-gray-400'}">${category}</div>
                            ${isActive ? '<div class="text-xs text-green-600">✅ Active</div>' : '<div class="text-xs text-gray-400">Inactive</div>'}
                        </div>
                    `;
                }).join('')}
            </div>
        </div>



        <!-- ID Number Legend -->
        <div class="bg-orange-50 border-l-4 border-orange-400">
            <h4 class="font-semibold text-orange-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                ID Number Legend & Formats
            </h4>
            <div class="space-y-4">
                <!-- Customer IDs -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        Customer IDs
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> [MonthLetter][Day][SequenceNumber]</p>
                        ${data.id_formats.recent_customers.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_customers.map(c => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${c.id}</code> (${c.username || 'No username'})`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 py-0\.5 rounded">F14004</code></p>`
                        }
                        <div class="text-xs text-orange-500">
                            <p>• <strong>F</strong> = June (A=Jan, B=Feb, C=Mar, D=Apr, E=May, F=Jun, G=Jul, H=Aug, I=Sep, J=Oct, K=Nov, L=Dec)</p>
                            <p>• <strong>14</strong> = 14th day of the month</p>
                            <p>• <strong>004</strong> = 4th customer registered</p>
                        </div>
                    </div>
                </div>

                <!-- Order IDs - Updated with Sequence Fix -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2L3 7v11a1 1 0 001 1h12a1 1 0 001-1V7l-7-5zM8 15v-3h4v3H8z" clip-rule="evenodd"></path>
                        </svg>
                        Order IDs - Sequence-Based System ✅
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> [CustomerNum][MonthLetter][Day][ShippingCode][SequenceNum]</p>
                        ${data.id_formats.recent_orders.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_orders.map(o => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${o}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 py-0\.5 rounded">01F30P75</code></p>`
                        }
                        <div class="text-xs text-orange-500">
                            <p>• <strong>01</strong> = Last 2 digits of customer number</p>
                            <p>• <strong>F30</strong> = June 30th (order date)</p>
                            <p>• <strong>P</strong> = Pickup (P=Pickup, L=Local, U=USPS, F=FedEx, X=UPS)</p>
                            <p>• <strong>75</strong> = Sequential number (eliminates duplicates)</p>
                        </div>
                        
                        <!-- Recent Fix Notice -->
                        <div class="bg-green-50 rounded">
                            <p class="font-medium text-green-700">🔧 Recent Fix Applied:</p>
                            <p class="text-xs text-green-600">• Replaced random number with sequence-based system</p>
                            <p class="text-xs text-green-600">• Eliminates "Duplicate entry" constraint violations</p>
                            <p class="text-xs text-green-600">• Sequential: 17F30P75 → 17F30P76 → 17F30P77</p>
                            <p class="text-xs text-green-600">• Robust for concurrent checkout processing</p>
                        </div>
                        
                        <!-- Shipping Codes -->
                        <div class="bg-blue-50 rounded">
                            <p class="font-medium text-blue-700">📦 Shipping Method Codes:</p>
                            <p class="text-xs text-blue-600">• <strong>P</strong> = Customer Pickup • <strong>L</strong> = Local Delivery</p>
                            <p class="text-xs text-blue-600">• <strong>U</strong> = USPS • <strong>F</strong> = FedEx • <strong>X</strong> = UPS</p>
                        </div>
                    </div>
                </div>

                <!-- Product/Inventory IDs (SKUs) - Enhanced Documentation -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                        </svg>
                        Product & Inventory IDs (SKUs) - Complete System
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Primary Format:</strong> ${data.system_info.sku_format}</p>
                        ${data.sample_skus.length > 0 ? 
                            `<p><strong>Current Examples:</strong> ${data.sample_skus.slice(0, 5).map(sku => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${sku}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Examples:</strong> <code class="bg-orange-100 py-0\.5 rounded">WF-TS-001</code>, <code class="bg-orange-100 py-0\.5 rounded">WF-TU-002</code></p>`
                        }
                        
                        <!-- Enhanced SKU Format -->
                        <div class="bg-orange-50 rounded">
                            <p class="font-medium text-orange-700">Enhanced SKU Format (Optional):</p>
                            <p><strong>WF-[CATEGORY]-[GENDER]-[SIZE]-[COLOR]-[NUMBER]</strong></p>
                            <p class="text-xs">Example: <code class="bg-orange-100 py-0\.5 rounded">WF-TS-M-L-BLK-001</code> = WhimsicalFrog T-Shirt, Men's Large, Black, #001</p>
                        </div>
                        
                        <!-- Category Codes -->
                        <div class="text-xs text-orange-500">
                            <p class="font-medium">Category Codes:</p>
                            ${Object.entries(data.category_codes).map(([category, code]) => 
                                `<p>• <strong>${code}</strong> = ${category}</p>`
                            ).join('')}
                        </div>
                        
                        <!-- SKU Generation -->
                        <div class="bg-green-50 rounded">
                            <p class="font-medium text-green-700">🔄 Auto-Generation:</p>
                            <p class="text-xs text-green-600">• SKUs are automatically generated with sequential numbering</p>
                            <p class="text-xs text-green-600">• API: <code>/api/next_sku.php?cat=[CATEGORY]</code></p>
                            <p class="text-xs text-green-600">• Enhanced: <code>&gender=M&size=L&color=Black&enhanced=true</code></p>
                        </div>
                        
                        <!-- Database Integration -->
                        <div class="bg-blue-50 rounded">
                            <p class="font-medium text-blue-700">🗄️ Database Integration:</p>
                            <p class="text-xs text-blue-600">• Primary table: <code>items</code> (SKU as primary key)</p>
                            <p class="text-xs text-blue-600">• Images: <code>item_images</code> (linked via SKU)</p>
                            <p class="text-xs text-blue-600">• Orders: <code>order_items</code> (references SKU)</p>
                            <p class="text-xs text-blue-600">• Migration complete: No legacy ID columns</p>
                        </div>
                    </div>
                </div>

                <!-- Order Item IDs -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path>
                        </svg>
                        Order Item IDs
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> OI[SequentialNumber]</p>
                        ${data.id_formats.recent_order_items.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_order_items.map(oi => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${oi}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 py-0\.5 rounded">OI001</code></p>`
                        }
                        <div class="text-xs text-orange-500">
                            <p>• <strong>OI</strong> = Order Item prefix</p>
                            <p>• <strong>001</strong> = Sequential 3-digit number (001, 002, 003, etc.)</p>
                            <p class="italic">Simple, clean, and easy to reference!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    `;
}

function closeSystemConfigModal() {
    if (typeof window.closeModal === 'function') {
        window.closeModal('systemConfigModal');
    } else {
        const el = document.getElementById('systemConfigModal');
        if (el) { el.classList.remove('show'); el.classList.add('hidden'); }
    }
}

window.openDatabaseMaintenanceModal = function openDatabaseMaintenanceModal() {
    console.log('openDatabaseMaintenanceModal called');
    const modal = document.getElementById('databaseMaintenanceModal');
    if (!modal) {
        console.error('databaseMaintenanceModal element not found!');
        if (window.showError) {
            window.showError('Database Maintenance modal not found. Please refresh the page.');
        } else {
            alert('Database Maintenance modal not found. Please refresh the page.');
        }
        return;
    }
    console.log('Opening database maintenance modal...');
    if (typeof window.openModal === 'function') {
        window.openModal('databaseMaintenanceModal');
    } else {
        modal.classList.remove('hidden');
        modal.classList.add('show');
    }
    // Hide loading and show connection tab by default
    document.getElementById('databaseMaintenanceLoading').style.display = 'none';
    switchDatabaseTab(document.querySelector('[data-tab="connection"]'), 'connection');
    // Also load the current configuration immediately
    loadCurrentDatabaseConfig();
}

async function loadDatabaseInformation() {
    const loadingDiv = document.getElementById('databaseMaintenanceLoading');
    const contentDiv = document.getElementById('databaseMaintenanceContent');
    
    // Show loading state
    loadingDiv.style.display = 'block';
    
    try {
        const response = await fetch('/api/get_database_info.php');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Hide loading and populate content
            loadingDiv.style.display = 'none';
            contentDiv.innerHTML = generateDatabaseMaintenanceHTML(data);
        } else {
            throw new Error(result.error || 'Failed to load database information');
        }
    } catch (error) {
        console.error('Error loading database information:', error);
        loadingDiv.innerHTML = `
            <div class="modal-loading">
                <div class="text-red-500">⚠️</div>
                <p class="text-red-600">Failed to load database information</p>
                <p class="text-sm text-gray-500">${error.message}</p>
                <button onclick="loadDatabaseInformation()" class="bg-red-500 text-white rounded hover:bg-red-600">
                    Retry
                </button>
            </div>
        `;
    }
}

function generateDatabaseMaintenanceHTML(data) {
    return `
        <!-- Database Schema -->
        <div class="bg-purple-50 border-l-4 border-purple-400">
            <h4 class="font-semibold text-purple-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"></path>
                </svg>
                Database Tables & Structure (${data.total_active} Active + ${data.total_backup} Backup)
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                ${Object.entries(data.organized || {}).map(([category, tables]) => {
                    const categoryLabels = {
                        'core_ecommerce': '🛒 Core E-commerce',
                        'user_management': '👥 User Management', 
                        'inventory_cost': '💰 Inventory & Cost',
                        'product_categories': '🏷️ Product Categories',
                        'room_management': '🏠 Room Management',
                        'email_system': '📧 Email System',
                        'business_config': '⚙️ Business Config',
                        'system_logs': '📄 System Logs',
                        'backup_tables': '🗄️ Backup Tables'
                    };

                    const categoryLabel = categoryLabels[category] || `❓ Unknown Category: ${category}`;

                    return `
                        <div class="bg-white rounded border border-purple-200 p-3">
                            <h5 class="font-semibold text-purple-700">${categoryLabel}</h5>
                            <ul class="text-xs text-purple-600 mt-2 space-y-1">
                                ${tables.map(table => `
                                    <li class="flex justify-between items-center">
                                        <span>${table.name}</span>
                                        <span class="text-purple-500 font-mono">${table.rows} rows</span>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;
}

// -----------------------------
// Database Maintenance Helpers
// -----------------------------

function getEvent(e) {
    // Normalize event across inline and programmatic invocations
    return e || (typeof window !== 'undefined' ? window.event : undefined);
}

function showResult(element, success, message) {
    if (!element) return;
    element.className = success 
        ? 'px-3 py-2 bg-green-50 border border-green-200 rounded text-sm'
        : 'px-3 py-2 bg-red-50 border border-red-200 rounded text-sm';
    element.innerHTML = message;
    element.classList.remove('hidden');
}

async function scanDatabaseConnections(e) {
    const evt = getEvent(e);
    const button = evt?.target || document.querySelector('[data-action="scan-db"], #scanDatabaseConnectionsBtn');
    const resultsDiv = document.getElementById('conversionResults');
    if (button) {
        button.disabled = true;
        button.textContent = '🔄 Scanning...';
    }
    if (resultsDiv) {
        resultsDiv.className = 'mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
        resultsDiv.innerHTML = '⏳ Scanning PHP files for database connections...';
        resultsDiv.classList.remove('hidden');
    }
    try {
        const response = await fetch('/api/convert_to_centralized_db.php?action=scan&format=json&admin_token=whimsical_admin_2024');
        const result = await response.json();
        if (result.success) {
            if (result.needs_conversion > 0) {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-yellow-50 border border-yellow-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-yellow-800">⚠️ Files Need Conversion</div>
                        <div class="text-xs space-y-1 text-yellow-700">
                            <div>Total PHP files: ${result.total_files}</div>
                            <div>Files needing conversion: ${result.needs_conversion}</div>
                            <div class="">Files with direct PDO connections:</div>
                            <ul class="list-disc list-inside">
                                ${result.files.slice(0, 10).map(f => `<li>${f}</li>`).join('')}
                                ${result.files.length > 10 ? `<li>... and ${result.files.length - 10} more</li>` : ''}
                            </ul>
                        </div>
                    `;
                }
            } else {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-green-50 border border-green-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-green-800">✅ All Files Use Centralized Database!</div>
                        <div class="text-xs text-green-700">Scanned ${result.total_files} PHP files - no conversion needed</div>
                    `;
                }
            }
        } else {
            throw new Error(result.message || 'Scan failed');
        }
    } catch (error) {
        if (resultsDiv) {
            resultsDiv.className = 'mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm';
            resultsDiv.innerHTML = `<div class="text-red-800">❌ Scan failed: ${error.message}</div>`;
        }
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = '📊 Scan Files';
        }
    }
}

async function convertDatabaseConnections(e) {
    const evt = getEvent(e);
    const button = evt?.target || document.querySelector('[data-action="convert-db"], #convertDatabaseConnectionsBtn');
    const resultsDiv = document.getElementById('conversionResults');
    // Use native confirm for now; page also includes enhanced modals elsewhere
    if (!confirm('This will modify files with direct PDO connections and create backups. Continue?')) {
        return;
    }
    if (button) {
        button.disabled = true;
        button.textContent = '🔄 Converting...';
    }
    if (resultsDiv) {
        resultsDiv.className = 'mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
        resultsDiv.innerHTML = '⏳ Converting files to use centralized database connections...';
        resultsDiv.classList.remove('hidden');
    }
    try {
        const response = await fetch('/api/convert_to_centralized_db.php?action=convert&format=json&admin_token=whimsical_admin_2024');
        const result = await response.json();
        if (result.success) {
            if (result.converted > 0) {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-green-50 border border-green-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-green-800">🎉 Conversion Completed!</div>
                        <div class="text-xs space-y-1 text-green-700">
                            <div>Files converted: ${result.converted}</div>
                            <div>Conversion failures: ${result.failed}</div>
                            <div class="">💾 Backups were created for all modified files</div>
                            <div class="text-yellow-700">⚠️ Please test your application to ensure everything works correctly</div>
                        </div>
                        ${result.results.filter(r => r.status === 'converted').length > 0 ? `
                            <details class="">
                                <summary class="cursor-pointer text-green-700 hover:text-green-900">View converted files</summary>
                                <ul class="list-disc list-inside text-xs">
                                    ${result.results.filter(r => r.status === 'converted').map(r => 
                                        `<li>${r.file} (${r.changes} changes)</li>`
                                    ).join('')}
                                </ul>
                            </details>
                        ` : ''}
                    `;
                }
            } else {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-blue-800">ℹ️ No Files Needed Conversion</div>
                        <div class="text-xs text-blue-700">All files are already using centralized database connections</div>
                    `;
                }
            }
        } else {
            throw new Error(result.message || 'Conversion failed');
        }
    } catch (error) {
        if (resultsDiv) {
            resultsDiv.className = 'mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm';
            resultsDiv.innerHTML = `<div class="text-red-800">❌ Conversion failed: ${error.message}</div>`;
        }
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = '🔄 Convert All';
        }
    }
}

function openConversionTool() {
    window.open('/api/convert_to_centralized_db.php?admin_token=whimsical_admin_2024', '_blank');
}

function toggleDatabaseBackupTables() {
    const container = document.getElementById('databaseBackupTablesContainer');
    const icon = document.getElementById('databaseBackupToggleIcon');
    if (!container || !icon) return;
    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        icon.textContent = '▼';
    } else {
        container.classList.add('hidden');
        icon.textContent = '▶';
    }
}

async function viewTable(tableName) {
    try {
        const modal = document.getElementById('tableViewModal');
        const title = document.getElementById('tableViewTitle');
        const content = document.getElementById('tableViewContent');
        if (title) title.textContent = `Loading ${tableName}...`;
        if (content) content.innerHTML = '<div class="text-center"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';
        if (typeof window.openModal === 'function') {
            window.openModal('tableViewModal');
        } else if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('show');
        }

        const response = await fetch('/api/db_manager.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'query',
                sql: `SELECT * FROM \`${tableName}\` LIMIT 100`
            })
        });
        const data = await response.json();
        if (data.success && data.data) {
            if (title) title.textContent = `Table: ${tableName} (${data.row_count} records shown, max 100)`;
            if (!Array.isArray(data.data) || data.data.length === 0) {
                if (content) content.innerHTML = '<div class="text-center text-gray-500">Table is empty</div>';
                return;
            }
            const columns = Object.keys(data.data[0]);
            const tableHtml = `
                <div class="overflow-x-auto max-h-96">
                    <table class="min-w-full bg-white border border-gray-200 text-xs">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                ${columns.map(col => `<th class="border-b text-left font-semibold text-gray-700">${col}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${data.data.map(row => `
                                <tr class="hover:bg-gray-50">
                                    ${columns.map(col => {
                                        let value = row[col];
                                        if (value === null) value = '<span class="text-gray-400">NULL</span>';
                                        else if (typeof value === 'string' && value.length > 50) value = value.substring(0, 50) + '...';
                                        return `<td class="border-b">${value}</td>`;
                                    }).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            if (content) content.innerHTML = tableHtml;
        } else {
            if (title) title.textContent = `Error loading ${tableName}`;
            if (content) content.innerHTML = `<div class="text-red-600">Error: ${data.error || 'Failed to load table data'}</div>`;
        }
    } catch (error) {
        console.error('Error viewing table:', error);
        const title = document.getElementById('tableViewTitle');
        const content = document.getElementById('tableViewContent');
        if (title) title.textContent = `Error loading ${tableName}`;
        if (content) content.innerHTML = `<div class="text-red-600">Error: ${error.message}</div>`;
    }
}

function closeTableViewModal() {
    if (typeof window.closeModal === 'function') {
        window.closeModal('tableViewModal');
    } else {
        const modal = document.getElementById('tableViewModal');
        if (modal) { modal.classList.remove('show'); modal.classList.add('hidden'); }
    }
}

async function getDatabaseTableCount() {
    try {
        const response = await fetch('/api/get_database_info.php');
        const result = await response.json();
        if (result.success && result.data) {
            return result.data.total_active || 'several';
        }
        return 'several';
    } catch (error) {
        return 'several';
    }
}

async function compactRepairDatabase() {
    const tableCount = await getDatabaseTableCount();
    const confirmed = await (window.showConfirmationModal ? window.showConfirmationModal({
        title: 'Database Compact & Repair',
        subtitle: 'Optimize and repair your database for better performance',
        message: 'This operation will create a safety backup first, then optimize and repair all database tables to improve performance and fix any corruption issues.',
        details: `
            <ul>
                <li>✅ Create automatic safety backup before optimization</li>
                <li>🔧 Optimize ${tableCount} database tables for better performance</li>
                <li>🛠️ Repair any table corruption or fragmentation issues</li>
                <li>⚡ Improve database speed and efficiency</li>
                <li>⏱️ Process typically takes 2-3 minutes</li>
            </ul>
        `,
        icon: '🔧',
        iconType: 'info',
        confirmText: 'Start Optimization',
        cancelText: 'Cancel'
    }) : Promise.resolve(confirm('Create a safety backup, then optimize and repair all database tables?')));
    if (!confirmed) return;

    if (typeof window.showBackupProgressModal === 'function') {
        window.showBackupProgressModal('🔧 Database Compact & Repair', 'database-repair');
    }
    const progressSteps = document.getElementById('backupProgressSteps');
    const progressTitle = document.getElementById('backupProgressTitle');
    const progressSubtitle = document.getElementById('backupProgressSubtitle');
    if (progressTitle) progressTitle.textContent = '🔧 Database Compact & Repair';
    if (progressSubtitle) progressSubtitle.textContent = 'Optimizing and repairing database tables...';

    try {
        if (progressSteps) {
            progressSteps.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Creating safety backup...</p>
                        <p class="text-xs text-gray-500">Backing up database before optimization</p>
                    </div>
                </div>
            `;
        }
        const backupResponse = await fetch('/api/backup_database.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ destination: 'cloud' })
        });
        const backupResult = await backupResponse.json();
        if (!backupResult.success) {
            throw new Error('Failed to create safety backup: ' + (backupResult.error || 'Unknown error'));
        }

        if (progressSteps) {
            progressSteps.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Safety backup created</p>
                        <p class="text-xs text-gray-500">Database backed up successfully</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Optimizing database tables...</p>
                        <p class="text-xs text-gray-500">Running OPTIMIZE and REPAIR operations</p>
                    </div>
                </div>
            `;
        }
        const repairResponse = await fetch('/api/compact_repair_database.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({})
        });
        const repairResult = await repairResponse.json();
        if (!repairResult.success) {
            throw new Error('Database optimization failed: ' + (repairResult.error || 'Unknown error'));
        }

        if (progressSteps) {
            progressSteps.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Safety backup created</p>
                        <p class="text-xs text-gray-500">Database backed up successfully</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Database optimization complete</p>
                        <p class="text-xs text-gray-500">${repairResult.tables_processed || 0} tables optimized and repaired</p>
                    </div>
                </div>
            `;
        }

        if (typeof window.showBackupCompletionDetails === 'function') {
            window.showBackupCompletionDetails({
                success: true,
                filename: backupResult.filename,
                filepath: backupResult.filepath,
                size: backupResult.size,
                timestamp: backupResult.timestamp,
                destinations: ['Server'],
                tables_optimized: repairResult.tables_processed || 0,
                operation_type: 'Database Compact & Repair'
            });
        }
    } catch (error) {
        console.error('Database optimization error:', error);
        if (typeof window.showError === 'function') {
            window.showError(error.message || 'Database optimization failed');
        } else {
            alert(error.message || 'Database optimization failed');
        }
    }
}

// -----------------------------
// Credentials & SSL Utilities (migrated)
// -----------------------------

function renderResult(div, success, html) {
    if (!div) return;
    const base = 'px-3 py-2 border rounded text-sm';
    if (success) {
        div.className = `${base} bg-green-50 border-green-200`;
    } else {
        div.className = `${base} bg-red-50 border-red-200`;
    }
    div.innerHTML = html;
    div.classList.remove('hidden');
}

async function updateDatabaseConfig(ev) {
    try {
        const resultDiv = document.getElementById('credentialsUpdateResult');
        const button = ev?.target || document.activeElement;

        const updateData = {
            host: document.getElementById('newHost')?.value,
            database: document.getElementById('newDatabase')?.value,
            username: document.getElementById('newUsername')?.value,
            password: document.getElementById('newPassword')?.value,
            environment: document.getElementById('environmentSelect')?.value,
            ssl_enabled: document.getElementById('sslEnabled')?.checked || false,
            ssl_cert: document.getElementById('sslCertPath')?.value || ''
        };

        if (!updateData.host || !updateData.database || !updateData.username) {
            renderResult(resultDiv, false, 'Please fill in all required fields');
            return;
        }

        const confirmAction = async () => {
            if (button) { button.disabled = true; button.textContent = '💾 Updating...'; }
            if (resultDiv) {
                resultDiv.className = 'px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
                resultDiv.innerHTML = '⏳ Updating configuration...';
                resultDiv.classList.remove('hidden');
            }
            try {
                const response = await fetch('/api/database_maintenance.php?action=update_config&admin_token=whimsical_admin_2024', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(updateData)
                });
                const result = await response.json();
                if (result.success) {
                    renderResult(resultDiv, true, `
                        <div class="font-medium text-green-800">✅ Configuration Updated!</div>
                        <div class="text-xs text-green-700">Backup created: ${result.backup_created}</div>
                        <div class="text-xs text-yellow-700">⚠️ Please refresh the page to use new settings</div>
                    `);
                    setTimeout(() => { try { loadCurrentDatabaseConfig(); } catch(_) {} }, 2000);
                } else {
                    renderResult(resultDiv, false, `Update failed: ${result.message}`);
                }
            } catch (error) {
                renderResult(resultDiv, false, `Network error: ${error.message}`);
            } finally {
                if (button) { button.disabled = false; button.textContent = '💾 Update Credentials'; }
            }
        };

        if (typeof window.showConfirmationModal === 'function') {
            window.showConfirmationModal({
                title: 'Update database credentials?',
                message: `A backup will be created automatically for ${updateData.environment} environment(s).`,
                confirmText: 'Yes, Update',
                cancelText: 'Cancel',
                onConfirm: confirmAction
            });
        } else if (confirm(`Are you sure you want to update database credentials for ${updateData.environment} environment(s)? A backup will be created automatically.`)) {
            await confirmAction();
        }
    } catch (err) {
        console.error('[AdminSettings] updateDatabaseConfig error', err);
    }
}

async function testSSLConnection(ev) {
    try {
        const resultDiv = document.getElementById('sslTestResult');
        const button = ev?.target || document.activeElement;

        const sslData = {
            host: document.getElementById('testHost')?.value || document.getElementById('newHost')?.value,
            database: document.getElementById('testDatabase')?.value || document.getElementById('newDatabase')?.value,
            username: document.getElementById('testUsername')?.value || document.getElementById('newUsername')?.value,
            password: document.getElementById('testPassword')?.value || document.getElementById('newPassword')?.value,
            ssl_enabled: true,
            ssl_cert: document.getElementById('sslCertPath')?.value
        };

        if (!sslData.ssl_cert) {
            renderResult(resultDiv, false, 'Please specify SSL certificate path');
            return;
        }

        if (button) { button.disabled = true; button.textContent = '🔄 Testing SSL...'; }
        if (resultDiv) {
            resultDiv.className = 'px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
            resultDiv.innerHTML = '⏳ Testing SSL connection...';
            resultDiv.classList.remove('hidden');
        }

        try {
            const response = await fetch('/api/database_maintenance.php?action=test_connection&admin_token=whimsical_admin_2024', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(sslData)
            });
            const result = await response.json();
            if (result.success) {
                renderResult(resultDiv, true, `
                    <div class="font-medium text-green-800">🔒 SSL Connection Successful!</div>
                    <div class="text-xs space-y-1 text-green-700">
                        <div>SSL Certificate: Valid</div>
                        <div>Encryption: Active</div>
                        <div>MySQL Version: ${result.info?.mysql_version || ''}</div>
                    </div>
                `);
            } else {
                renderResult(resultDiv, false, `SSL connection failed: ${result.message}`);
            }
        } catch (error) {
            renderResult(resultDiv, false, `SSL test error: ${error.message}`);
        } finally {
            if (button) { button.disabled = false; button.textContent = '🔒 Test SSL Connection'; }
        }
    } catch (err) {
        console.error('[AdminSettings] testSSLConnection error', err);
    }
}

// Temporary window shims for backward compatibility with inline handlers
if (typeof window !== 'undefined') {
    window.scanDatabaseConnections = scanDatabaseConnections;
    window.convertDatabaseConnections = convertDatabaseConnections;
    window.openConversionTool = openConversionTool;
    window.toggleDatabaseBackupTables = toggleDatabaseBackupTables;
    window.viewTable = viewTable;
    window.closeTableViewModal = closeTableViewModal;
    window.compactRepairDatabase = compactRepairDatabase;
    window.updateDatabaseConfig = updateDatabaseConfig;
    window.testSSLConnection = testSSLConnection;
}

// -----------------------------
// Delegated Listeners (Progressive Migration)
// -----------------------------

let WF_AdminSettingsListenersInitialized = false;

function tagInlineHandlersForMigration(root = document) {
    // Add data-action tags based on existing inline onclick attributes to ease removal later
    try {
        const mappings = [
            { contains: 'scanDatabaseConnections', action: 'scan-db' },
            { contains: 'convertDatabaseConnections', action: 'convert-db' },
            { contains: 'openConversionTool', action: 'open-conversion-tool' },
            { contains: 'compactRepairDatabase', action: 'compact-repair' },
            { contains: 'toggleDatabaseBackupTables', action: 'toggle-backup-tables' },
            { contains: 'closeTableViewModal', action: 'close-table-view' },
            { contains: 'updateDatabaseConfig', action: 'update-db-config' },
            { contains: 'testSSLConnection', action: 'test-ssl' }
        ];
        const clickable = root.querySelectorAll('[onclick]');
        clickable.forEach(el => {
            const code = (el.getAttribute('onclick') || '').toString();
            for (const map of mappings) {
                if (code.includes(map.contains)) {
                    if (!el.dataset.action) el.dataset.action = map.action;
                }
            }
            // Special handling: viewTable('<tableName>') -> data-action="view-table" + data-table
            if (code.includes('viewTable(')) {
                if (!el.dataset.action) el.dataset.action = 'view-table';
                try {
                    const m = code.match(/viewTable\((?:'([^']+)'|\"([^\"]+)\"|([^\)]+))\)/);
                    const table = (m && (m[1] || m[2] || m[3] || '')).toString().trim().replace(/^`|`$/g, '').replace(/^\"|\"$/g, '').replace(/^'|'$/g, '');
                    if (table && !el.dataset.table) el.dataset.table = table;
                } catch (_) {}
            }
        });
    } catch (e) {
        console.debug('[AdminSettings] tagInlineHandlersForMigration error', e);
    }
}

function stripInlineHandlersForMigration(root = document) {
    try {
        const selectors = [
            '[onclick*="scanDatabaseConnections"]',
            '[onclick*="convertDatabaseConnections"]',
            '[onclick*="openConversionTool"]',
            '[onclick*="compactRepairDatabase"]',
            '[onclick*="toggleDatabaseBackupTables"]',
            '[onclick*="closeTableViewModal"]',
            '[onclick*="viewTable("]',
            '[onclick*="updateDatabaseConfig"]',
            '[onclick*="testSSLConnection"]'
        ];
        root.querySelectorAll(selectors.join(',')).forEach(el => {
            // Preserve original inline handler for debugging/rollback visibility
            if (!el.dataset.onclickLegacy) {
                el.dataset.onclickLegacy = el.getAttribute('onclick') || '';
            }
            el.removeAttribute('onclick');
            el.dataset.migrated = 'true';
        });
    } catch (e) {
        console.debug('[AdminSettings] stripInlineHandlersForMigration error', e);
    }
}

function initAdminSettingsDelegatedListeners() {
    if (WF_AdminSettingsListenersInitialized) return;
    WF_AdminSettingsListenersInitialized = true;

    // Tag existing inline handlers for smoother migration
    const runTagAndStrip = () => { tagInlineHandlersForMigration(); stripInlineHandlersForMigration(); };
    if (document.readyState !== 'loading') {
        runTagAndStrip();
    } else {
        document.addEventListener('DOMContentLoaded', () => runTagAndStrip(), { once: true });
    }
    
    // Initialize SSL option visibility on load
    initSSLHandlers();
    
    // Observe future DOM changes to tag dynamically injected elements
    try {
        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.type === 'childList') {
                    m.addedNodes.forEach(node => {
                        if (node.nodeType === 1) {
                            tagInlineHandlersForMigration(node);
                            stripInlineHandlersForMigration(node);
                            // Re-evaluate SSL option visibility for injected content
                            initSSLHandlers(node);
                        }
                    });
                } else if (m.type === 'attributes' && m.attributeName === 'onclick') {
                    tagInlineHandlersForMigration(m.target);
                    stripInlineHandlersForMigration(m.target);
                }
            }
        });
        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['onclick']
        });
    } catch (err) {
        console.debug('[AdminSettings] MutationObserver unavailable', err);
    }

    // Delegated change handler (SSL checkbox)
    document.addEventListener('change', (e) => {
        const target = e.target;
        if (target && target.matches && target.matches('#sslEnabled')) {
            const sslOptions = document.getElementById('sslOptions');
            if (sslOptions) {
                if (target.checked) sslOptions.classList.remove('hidden');
                else sslOptions.classList.add('hidden');
            }
        }
    }, true);

    // Delegated click handler
    document.addEventListener('click', (e) => {
        const target = e.target;

        // Helper to match closest element
        const closest = (sel) => target.closest(sel);

        // Scan Files
        if (closest('[data-action="scan-db"]')) {
            e.preventDefault();
            scanDatabaseConnections(e);
            return;
        }

        // Convert All
        if (closest('[data-action="convert-db"]')) {
            e.preventDefault();
            convertDatabaseConnections(e);
            return;
        }

        // Open Conversion Tool
        if (closest('[data-action="open-conversion-tool"]')) {
            e.preventDefault();
            openConversionTool();
            return;
        }

        // Compact & Repair
        if (closest('[data-action="compact-repair"]')) {
            e.preventDefault();
            compactRepairDatabase();
            return;
        }

        // Toggle Backup Tables
        if (closest('[data-action="toggle-backup-tables"]')) {
            e.preventDefault();
            toggleDatabaseBackupTables();
            return;
        }

        // Close Table Viewer
        if (closest('[data-action="close-table-view"]')) {
            e.preventDefault();
            closeTableViewModal();
            return;
        }

        // View Table (needs argument)
        const viewBtn = closest('[data-action="view-table"]');
        if (viewBtn) {
            e.preventDefault();
            let tableName = viewBtn.dataset.table || viewBtn.dataset.tableName;
            if (!tableName && viewBtn.dataset.onclickLegacy) {
                try {
                    const m = viewBtn.dataset.onclickLegacy.match(/viewTable\((?:'([^']+)'|\"([^\"]+)\"|([^\)]+))\)/);
                    tableName = (m && (m[1] || m[2] || m[3] || '')).toString().trim();
                } catch(_) {}
            }
            if (tableName) {
                viewTable(tableName);
            } else {
                console.warn('[AdminSettings] view-table clicked but no table name found');
            }
            return;
        }

        // Update DB Credentials
        if (closest('[data-action="update-db-config"]')) {
            e.preventDefault();
            updateDatabaseConfig(e);
            return;
        }

        // Test SSL Connection
        if (closest('[data-action=\"test-ssl\"]')) {
            e.preventDefault();
            testSSLConnection(e);
            return;
        }
    }, true);
}

// Initialize listeners ASAP
if (typeof window !== 'undefined') {
    if (document.readyState !== 'loading') {
        initAdminSettingsDelegatedListeners();
    } else {
        document.addEventListener('DOMContentLoaded', () => initAdminSettingsDelegatedListeners(), { once: true });
    }
}

// Helper to initialize SSL checkbox-driven visibility
function initSSLHandlers(root = document) {
    try {
        const sslCheckbox = root.querySelector ? root.querySelector('#sslEnabled') : null;
        const sslOptions = root.querySelector ? root.querySelector('#sslOptions') : null;
        if (sslCheckbox && sslOptions) {
            sslOptions.classList.toggle('hidden', !sslCheckbox.checked);
        }
    } catch (_) {}
}

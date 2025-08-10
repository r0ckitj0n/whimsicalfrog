let mapperIsDrawing = false;
let mapperStartX, mapperStartY;
let mapperCurrentArea = null;
let mapperAreaCount = 0;
const mapperOriginalImageWidth = 1280;
const mapperOriginalImageHeight = 896;



function openSystemConfigModal() {
    const modal = document.getElementById('systemConfigModal');
    modal.classList.remove('hidden');
    modal.style.display = 'block';
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
    document.getElementById('systemConfigModal').style.display = 'none';
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
    console.log('Modal before changes:', modal.style.display, modal.classList.contains('hidden'));
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    console.log('Modal after changes:', modal.style.display, modal.classList.contains('hidden'));
    console.log('Modal computed style:', window.getComputedStyle(modal).display, window.getComputedStyle(modal).visibility);
    console.log('Modal z-index:', window.getComputedStyle(modal).zIndex);
    console.log('Modal position:', modal.getBoundingClientRect());
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

<?php
// Admin settings page - Authentication is now handled by index.php
?>
<style>
  .admin-data-label {
    color: #222 !important;
  }
  .admin-data-value {
    color: #c00 !important;
    font-weight: bold;
  }
</style>
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-2">Payment Integration Settings</h2>
    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Square Payment Integration</label>
        <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded">
            <strong>Coming Soon:</strong> You will be able to connect your Square account here to accept credit card payments online.<br>
            When available, paste your Square Application ID and Access Token below.<br>
            <em>(This section is a placeholder. No credentials are stored yet.)</em>
        </div>
    </div>
    <form>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Square Application ID</label>
            <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Paste Square Application ID here" disabled>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Square Access Token</label>
            <input type="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Paste Square Access Token here" disabled>
        </div>
        <button type="button" class="brand-button px-4 py-2 rounded cursor-not-allowed" disabled>Save (Coming Soon)</button>
    </form>
</div>
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Other Settings</h2>
    <div class="space-y-3">
        <div>
            <p class="text-sm text-gray-600 mb-3">Manage product categories used across inventory and shop.</p>
            <a href="/?page=admin&section=categories" class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium rounded mr-3">Manage Categories</a>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-3">View information about ID numbering system used throughout the platform.</p>
            <button onclick="openIdLegendModal()" class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                ID# Legend
            </button>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-3">Map clickable areas on room images for product placement and navigation.</p>
            <button onclick="openRoomMapperModal()" class="inline-flex items-center px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                </svg>
                Room Mapper
            </button>
        </div>
    </div>
</div>

<!-- ID Legend Modal -->
<div id="idLegendModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">ID Number Legend</h3>
                <button onclick="closeIdLegendModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="space-y-6">
                <!-- Customer IDs -->
                <div class="bg-purple-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-purple-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        Customer IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> [MonthLetter][Day][SequenceNumber]</p>
                        <p class="text-sm text-gray-700"><strong>Example:</strong> <code class="bg-gray-200 px-2 py-1 rounded">F14004</code></p>
                        <div class="text-sm text-gray-600">
                            <p>• <strong>F</strong> = June (A=Jan, B=Feb, C=Mar, D=Apr, E=May, F=Jun, G=Jul, H=Aug, I=Sep, J=Oct, K=Nov, L=Dec)</p>
                            <p>• <strong>14</strong> = 14th day of the month</p>
                            <p>• <strong>004</strong> = 4th customer registered</p>
                        </div>
                    </div>
                </div>

                <!-- Order IDs -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2L3 7v11a1 1 0 001 1h12a1 1 0 001-1V7l-7-5zM8 15v-3h4v3H8z" clip-rule="evenodd"></path>
                        </svg>
                        Order IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> [CustomerNum][MonthLetter][Day][ShippingCode][RandomNum]</p>
                        <p class="text-sm text-gray-700"><strong>Example:</strong> <code class="bg-gray-200 px-2 py-1 rounded">01F14P23</code></p>
                        <div class="text-sm text-gray-600">
                            <p>• <strong>01</strong> = Last 2 digits of customer number</p>
                            <p>• <strong>F14</strong> = June 14th (order date)</p>
                            <p>• <strong>P</strong> = Pickup (P=Pickup, L=Local, U=USPS, F=FedEx, X=UPS)</p>
                            <p>• <strong>23</strong> = Random 2-digit number for uniqueness</p>
                        </div>
                    </div>
                </div>

                <!-- Product/Inventory IDs -->
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-green-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                        </svg>
                        Product & Inventory IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> [CategoryPrefix][SequenceNumber]</p>
                        <p class="text-sm text-gray-700"><strong>Examples:</strong></p>
                        <div class="text-sm text-gray-600 space-y-1">
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">I001</code> = Inventory Item #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">TS001</code> = T-Shirt Product #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">TU001</code> = Tumbler Product #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">AW001</code> = Artwork Product #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">MG001</code> = Mug Product #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">GN001</code> = General/Window Wrap Product #1</p>
                        </div>
                    </div>
                </div>

                <!-- Order Item IDs -->
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-yellow-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path>
                        </svg>
                        Order Item IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> OI[SequentialNumber]</p>
                        <p class="text-sm text-gray-700"><strong>Example:</strong> <code class="bg-gray-200 px-2 py-1 rounded">OI001</code></p>
                        <div class="text-sm text-gray-600">
                            <p>• <strong>OI</strong> = Order Item prefix</p>
                            <p>• <strong>001</strong> = Sequential 3-digit number (001, 002, 003, etc.)</p>
                            <p class="text-xs italic">Simple, clean, and easy to reference!</p>
                        </div>
                    </div>
                </div>

                <!-- Marketing IDs -->
                <div class="bg-red-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-red-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path>
                        </svg>
                        Marketing IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> [TypePrefix][RandomAlphanumeric]</p>
                        <p class="text-sm text-gray-700"><strong>Examples:</strong></p>
                        <div class="text-sm text-gray-600 space-y-1">
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">EC001</code> = Email Campaign #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">DC001</code> = Discount Code #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">SP001</code> = Social Post #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">SA001</code> = Social Account #1</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">ES001</code> = Email Subscriber #1</p>
                        </div>
                    </div>
                </div>

                <!-- Legacy IDs -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        Legacy/Admin IDs
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-700"><strong>Format:</strong> [Letter][SequenceNumber]</p>
                        <p class="text-sm text-gray-700"><strong>Examples:</strong></p>
                        <div class="text-sm text-gray-600 space-y-1">
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">F13001</code> = Legacy customer/admin account</p>
                            <p>• <code class="bg-gray-200 px-2 py-1 rounded">U962</code> = Legacy user format (deprecated)</p>
                        </div>
                        <p class="text-xs text-gray-500 italic">Note: Legacy formats are maintained for existing records but new records use the current specifications.</p>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="mt-6 flex justify-end">
                <button onclick="closeIdLegendModal()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white text-sm font-medium rounded">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Room Mapper Modal -->
<div id="roomMapperModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white shadow-xl w-full h-full overflow-y-auto">
        <div class="flex justify-between items-center p-6 border-b">
            <h2 class="text-2xl font-bold text-gray-800">Room Mapper - Clickable Area Helper</h2>
            <button onclick="closeRoomMapperModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        
        <div class="p-6">
            <p class="text-gray-600 mb-4">This tool helps you map clickable areas on your room images with the same scaling as your live site.</p>
            
            <div class="controls mb-4">
                <div class="flex flex-wrap gap-3 mb-3">
                    <div class="flex items-center">
                        <label for="roomMapperSelect" class="mr-2">Select Room:</label>
                        <select id="roomMapperSelect" class="px-3 py-2 border border-gray-300 rounded">
                            <option value="landing">Landing Page</option>
                            <option value="room_main">Main Room</option>
                            <option value="room_artwork">Artwork Room</option>
                            <option value="room_tshirts" selected>T-Shirts Room</option>
                            <option value="room_tumblers">Tumblers Room</option>
                            <option value="room_sublimation">Sublimation Room</option>
                            <option value="room_windowwraps">Window Wraps Room</option>
                        </select>
                    </div>
                    <button onclick="toggleMapperGrid()" class="px-3 py-2 bg-gray-500 text-white rounded">Toggle Grid</button>
                    <button onclick="clearMapperAreas()" class="px-3 py-2 bg-red-500 text-white rounded">Clear Areas</button>
                </div>
                
                <div class="flex flex-wrap gap-3 mb-3">
                    <div class="flex items-center">
                        <input type="text" id="mapNameInput" placeholder="Enter map name..." class="px-3 py-2 border border-gray-300 rounded mr-2" />
                        <button onclick="saveRoomMap()" class="px-3 py-2 bg-green-500 hover:bg-green-600 text-white rounded">Save Map</button>
                    </div>
                    <div class="flex items-center">
                        <select id="savedMapsSelect" class="px-3 py-2 border border-gray-300 rounded mr-2">
                            <option value="">Select saved map...</option>
                        </select>
                        <button onclick="loadSavedMap()" class="px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded mr-2">Load</button>
                        <button onclick="applySavedMap()" class="px-3 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded mr-2">Apply to Live</button>
                        <button onclick="deleteSavedMap()" class="px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded">Delete</button>
                    </div>
                    
                    <!-- Map Preview Legend -->
                    <div class="mt-2 text-xs text-gray-600 bg-gray-50 p-2 rounded border">
                        <strong>Preview Colors:</strong>
                        <span class="inline-flex items-center ml-2">
                            <span class="w-3 h-3 border-2 border-green-500 bg-green-200 rounded mr-1"></span>
                            Original (Protected)
                        </span>
                        <span class="inline-flex items-center ml-2">
                            <span class="w-3 h-3 border-2 border-blue-500 bg-blue-200 rounded mr-1"></span>
                            Active Map
                        </span>
                        <span class="inline-flex items-center ml-2">
                            <span class="w-3 h-3 border-2 border-gray-500 bg-gray-200 rounded mr-1"></span>
                            Inactive Map
                        </span>
                    </div>
                </div>
                
                <div id="mapStatus" class="text-sm mb-3"></div>
                
                <!-- History Section -->
                <div class="border-t pt-4">
                    <h4 class="font-semibold text-gray-800 mb-3">📜 Map History</h4>
                    <div class="flex items-center gap-3 mb-3">
                        <button onclick="toggleHistoryView()" class="px-3 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded text-sm">
                            <span id="historyToggleText">Show History</span>
                        </button>
                        <span class="text-sm text-gray-600">View and restore previous map versions</span>
                    </div>
                    
                    <div id="historySection" class="hidden">
                        <div class="bg-gray-50 border border-gray-200 rounded p-4 max-h-80 overflow-y-auto">
                            <div id="historyList" class="space-y-2">
                                <p class="text-gray-500 text-sm">Select a room to view its history</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="room-mapper-container relative mb-4" id="roomMapperContainer">
                <div class="room-mapper-wrapper relative w-full bg-gray-800 rounded-lg overflow-hidden" id="roomMapperDisplay" style="height: 60vh; background-size: contain; background-position: center; background-repeat: no-repeat;">
                    <div class="grid-overlay absolute top-0 left-0 w-full h-full pointer-events-none hidden" id="mapperGridOverlay" style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 20px 20px;"></div>
                    <!-- Clickable areas will be added here -->
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-4">
                <p class="text-blue-800"><strong>Note:</strong> This mapper uses the exact same scaling system as your live site. The coordinates generated will match the room page layout perfectly.</p>
            </div>
            
            <div class="bg-gray-100 border border-gray-300 rounded p-4 max-h-96 overflow-y-auto font-mono text-sm" id="mapperCoordinates">
                Click and drag on the image to create clickable areas. Coordinates will appear here.
            </div>
        </div>
    </div>
</div>

<style>
.room-mapper-clickable-area {
    position: absolute;
    border: 2px solid red;
    background: rgba(255, 0, 0, 0.2);
    cursor: pointer;
    z-index: 100;
    transition: all 0.2s ease;
}
.room-mapper-clickable-area:hover {
    background: rgba(255, 0, 0, 0.4);
    transform: scale(1.02);
}
.room-mapper-container.grid-active .grid-overlay {
    display: block !important;
}

/* Map type specific styling */
.room-mapper-clickable-area.original-map {
    border: 2px solid #10b981 !important;
    background: rgba(16, 185, 129, 0.2) !important;
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.3);
}
.room-mapper-clickable-area.original-map:hover {
    background: rgba(16, 185, 129, 0.4) !important;
}

.room-mapper-clickable-area.active-map {
    border: 2px solid #3b82f6 !important;
    background: rgba(59, 130, 246, 0.2) !important;
    box-shadow: 0 0 6px rgba(59, 130, 246, 0.3);
}
.room-mapper-clickable-area.active-map:hover {
    background: rgba(59, 130, 246, 0.4) !important;
}

.room-mapper-clickable-area.inactive-map {
    border: 2px solid #6b7280 !important;
    background: rgba(107, 114, 128, 0.2) !important;
}
.room-mapper-clickable-area.inactive-map:hover {
    background: rgba(107, 114, 128, 0.4) !important;
}
</style>

<script>
let mapperIsDrawing = false;
let mapperStartX, mapperStartY;
let mapperCurrentArea = null;
let mapperAreaCount = 0;
const mapperOriginalImageWidth = 1280;
const mapperOriginalImageHeight = 896;

function openIdLegendModal() {
    document.getElementById('idLegendModal').style.display = 'block';
}

function closeIdLegendModal() {
    document.getElementById('idLegendModal').style.display = 'none';
}

function openRoomMapperModal() {
    document.getElementById('roomMapperModal').style.display = 'flex';
    initializeRoomMapper();
}

function closeRoomMapperModal() {
    document.getElementById('roomMapperModal').style.display = 'none';
}

function initializeRoomMapper() {
    const roomSelect = document.getElementById('roomMapperSelect');
    const roomDisplay = document.getElementById('roomMapperDisplay');
    const roomContainer = document.getElementById('roomMapperContainer');
    const coordinates = document.getElementById('mapperCoordinates');

    roomSelect.addEventListener('change', function() {
        // Special handling for landing page image
        if (this.value === 'landing') {
            roomDisplay.style.backgroundImage = `url('images/home_background.png')`;
        } else {
            roomDisplay.style.backgroundImage = `url('images/${this.value}.png')`;
        }
        clearMapperAreas();
        loadSavedMapsForRoom(this.value);
    });

    // Initialize with the selected room
    if (roomSelect.value === 'landing') {
        roomDisplay.style.backgroundImage = `url('images/home_background.png')`;
    } else {
        roomDisplay.style.backgroundImage = `url('images/${roomSelect.value}.png')`;
    }
    
    // Load saved maps for the initial room
    loadSavedMapsForRoom(roomSelect.value);

    roomDisplay.addEventListener('mousedown', function(e) {
        const rect = roomDisplay.getBoundingClientRect();
        mapperStartX = e.clientX - rect.left;
        mapperStartY = e.clientY - rect.top;
        
        mapperIsDrawing = true;
        
        mapperCurrentArea = document.createElement('div');
        mapperCurrentArea.className = 'room-mapper-clickable-area';
        mapperCurrentArea.style.left = mapperStartX + 'px';
        mapperCurrentArea.style.top = mapperStartY + 'px';
        mapperCurrentArea.style.width = '0px';
        mapperCurrentArea.style.height = '0px';
        roomDisplay.appendChild(mapperCurrentArea);
    });

    roomDisplay.addEventListener('mousemove', function(e) {
        if (mapperIsDrawing && mapperCurrentArea) {
            const rect = roomDisplay.getBoundingClientRect();
            const currentX = e.clientX - rect.left;
            const currentY = e.clientY - rect.top;
            
            const width = Math.abs(currentX - mapperStartX);
            const height = Math.abs(currentY - mapperStartY);
            const left = Math.min(currentX, mapperStartX);
            const top = Math.min(currentY, mapperStartY);
            
            mapperCurrentArea.style.left = left + 'px';
            mapperCurrentArea.style.top = top + 'px';
            mapperCurrentArea.style.width = width + 'px';
            mapperCurrentArea.style.height = height + 'px';
        }
    });

    roomDisplay.addEventListener('mouseup', function(e) {
        if (mapperIsDrawing && mapperCurrentArea) {
            mapperIsDrawing = false;
            mapperAreaCount++;
            
            // Get container dimensions
            const wrapperWidth = roomDisplay.offsetWidth;
            const wrapperHeight = roomDisplay.offsetHeight;
            
            // Calculate scaling factors
            const wrapperAspectRatio = wrapperWidth / wrapperHeight;
            const imageAspectRatio = mapperOriginalImageWidth / mapperOriginalImageHeight;
            
            let renderedImageWidth, renderedImageHeight;
            let offsetX = 0;
            let offsetY = 0;
            
            // Calculate image rendering dimensions within container
            if (wrapperAspectRatio > imageAspectRatio) {
                renderedImageHeight = wrapperHeight;
                renderedImageWidth = renderedImageHeight * imageAspectRatio;
                offsetX = (wrapperWidth - renderedImageWidth) / 2;
            } else {
                renderedImageWidth = wrapperWidth;
                renderedImageHeight = renderedImageWidth / imageAspectRatio;
                offsetY = (wrapperHeight - renderedImageHeight) / 2;
            }
            
            // Get the pixel values from the drawn area
            const leftPx = parseFloat(mapperCurrentArea.style.left);
            const topPx = parseFloat(mapperCurrentArea.style.top);
            const widthPx = parseFloat(mapperCurrentArea.style.width);
            const heightPx = parseFloat(mapperCurrentArea.style.height);
            
            // Convert to original image coordinates
            const originalLeft = Math.round(((leftPx - offsetX) / renderedImageWidth) * mapperOriginalImageWidth);
            const originalTop = Math.round(((topPx - offsetY) / renderedImageHeight) * mapperOriginalImageHeight);
            const originalWidth = Math.round((widthPx / renderedImageWidth) * mapperOriginalImageWidth);
            const originalHeight = Math.round((heightPx / renderedImageHeight) * mapperOriginalImageHeight);
            
            const cssClass = `area-${mapperAreaCount}`;
            mapperCurrentArea.setAttribute('data-area', cssClass);
            
            const cssCode = `.${cssClass} { top: ${originalTop}px; left: ${originalLeft}px; width: ${originalWidth}px; height: ${originalHeight}px; }`;
            
            // JavaScript array format for room pages
            const jsArrayFormat = `{ selector: '.${cssClass}', top: ${originalTop}, left: ${originalLeft}, width: ${originalWidth}, height: ${originalHeight} }, // Area ${mapperAreaCount}`;
            
            const selectedRoom = roomSelect.value;
            coordinates.innerHTML += `
                <div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <strong>Area ${mapperAreaCount} (${selectedRoom}):</strong><br>
                    <div style="margin: 5px 0;">CSS: ${cssCode}</div>
                    <div style="margin: 5px 0;">JS Array: ${jsArrayFormat}</div>
                </div>
            `;
            
            mapperCurrentArea = null;
        }
    });
}

function toggleMapperGrid() {
    const roomContainer = document.getElementById('roomMapperContainer');
    roomContainer.classList.toggle('grid-active');
}

function clearMapperAreas() {
    const roomDisplay = document.getElementById('roomMapperDisplay');
    const coordinates = document.getElementById('mapperCoordinates');
    const areas = roomDisplay.querySelectorAll('.room-mapper-clickable-area');
    areas.forEach(area => area.remove());
    coordinates.innerHTML = 'Click and drag on the image to create clickable areas. Coordinates will appear here.';
    mapperAreaCount = 0;
}

// New room map management functions
async function loadSavedMapsForRoom(roomType) {
    console.log(`🔍 Loading saved maps for room: ${roomType}`);
    
    try {
        const response = await fetch(`api/room_maps.php?room_type=${roomType}`);
        console.log(`API response status: ${response.status}`);
        
        const data = await response.json();
        console.log('API response data:', data);
        
        const savedMapsSelect = document.getElementById('savedMapsSelect');
        savedMapsSelect.innerHTML = '<option value="">Select saved map...</option>';
        
        if (data.success && data.maps) {
            console.log(`Found ${data.maps.length} maps for ${roomType}`);
            data.maps.forEach(map => {
                const option = document.createElement('option');
                option.value = map.id;
                const protectedText = map.map_name === 'Original' ? ' 🔒 PROTECTED' : '';
                const activeText = map.is_active ? ' (ACTIVE)' : '';
                option.textContent = `${map.map_name}${activeText}${protectedText}`;
                option.dataset.mapData = JSON.stringify(map);
                savedMapsSelect.appendChild(option);
                
                console.log(`Added map to dropdown: ${option.textContent}`);
            });
            
            // Add event listener to show bounding boxes when map is selected
            if (!savedMapsSelect.hasAttribute('data-listener-added')) {
                savedMapsSelect.addEventListener('change', function() {
                    if (this.value) {
                        previewSelectedMap();
                    } else {
                        clearMapperAreas();
                    }
                });
                savedMapsSelect.setAttribute('data-listener-added', 'true');
            }
        } else {
            console.log(`No maps found for ${roomType}:`, data);
        }
        
        updateMapStatus(roomType);
    } catch (error) {
        console.error('Error loading saved maps:', error);
        showMapperMessage('Error loading saved maps', 'error');
    }
}

async function saveRoomMap() {
    const roomType = document.getElementById('roomMapperSelect').value;
    const mapName = document.getElementById('mapNameInput').value.trim();
    
    if (!mapName) {
        showMapperMessage('Please enter a map name', 'error');
        return;
    }
    
    const areas = document.querySelectorAll('.room-mapper-clickable-area');
    if (areas.length === 0) {
        showMapperMessage('Please create some clickable areas first', 'error');
        return;
    }
    
    // Extract coordinates from current areas
    const coordinates = [];
    areas.forEach((area, index) => {
        const areaData = area.getAttribute('data-area');
        if (areaData) {
            // Parse from the coordinates display to get the actual coordinate data
            const coordDiv = document.querySelector(`#mapperCoordinates div:nth-child(${index + 1})`);
            if (coordDiv) {
                const jsArrayMatch = coordDiv.textContent.match(/{ selector: '([^']+)', top: (\d+), left: (\d+), width: (\d+), height: (\d+) }/);
                if (jsArrayMatch) {
                    coordinates.push({
                        selector: jsArrayMatch[1],
                        top: parseInt(jsArrayMatch[2]),
                        left: parseInt(jsArrayMatch[3]),
                        width: parseInt(jsArrayMatch[4]),
                        height: parseInt(jsArrayMatch[5])
                    });
                }
            }
        }
    });
    
    try {
        const response = await fetch('api/room_maps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'save',
                room_type: roomType,
                map_name: mapName,
                coordinates: coordinates
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMapperMessage('Map saved successfully!', 'success');
            document.getElementById('mapNameInput').value = '';
            loadSavedMapsForRoom(roomType);
        } else {
            showMapperMessage('Error saving map: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error saving map:', error);
        showMapperMessage('Error saving map', 'error');
    }
}

function previewSelectedMap() {
    const savedMapsSelect = document.getElementById('savedMapsSelect');
    const selectedOption = savedMapsSelect.options[savedMapsSelect.selectedIndex];
    
    if (!selectedOption || !selectedOption.value) {
        return;
    }
    
    const mapData = JSON.parse(selectedOption.dataset.mapData);
    
    // Clear current areas
    clearMapperAreas();
    
    // Load the coordinates from the saved map for preview
    if (mapData.coordinates && mapData.coordinates.length > 0) {
        const roomDisplay = document.getElementById('roomMapperDisplay');
        const coordinates = document.getElementById('mapperCoordinates');
        
        mapData.coordinates.forEach((coord, index) => {
            mapperAreaCount++;
            
            // Create visual area
            const area = document.createElement('div');
            area.className = 'room-mapper-clickable-area';
            area.setAttribute('data-area', coord.selector);
            
            // Special styling for preview mode
            const isOriginal = mapData.map_name === 'Original';
            const isActive = mapData.is_active;
            
            if (isOriginal) {
                area.classList.add('original-map');
            } else if (isActive) {
                area.classList.add('active-map');
            } else {
                area.classList.add('inactive-map');
            }
            
            // We need to convert the original coordinates back to display coordinates
            const wrapperWidth = roomDisplay.offsetWidth;
            const wrapperHeight = roomDisplay.offsetHeight;
            const wrapperAspectRatio = wrapperWidth / wrapperHeight;
            const imageAspectRatio = mapperOriginalImageWidth / mapperOriginalImageHeight;
            
            let renderedImageWidth, renderedImageHeight;
            let offsetX = 0;
            let offsetY = 0;
            
            if (wrapperAspectRatio > imageAspectRatio) {
                renderedImageHeight = wrapperHeight;
                renderedImageWidth = renderedImageHeight * imageAspectRatio;
                offsetX = (wrapperWidth - renderedImageWidth) / 2;
            } else {
                renderedImageWidth = wrapperWidth;
                renderedImageHeight = renderedImageWidth / imageAspectRatio;
                offsetY = (wrapperHeight - renderedImageHeight) / 2;
            }
            
            // Convert back to display coordinates
            const displayLeft = (coord.left / mapperOriginalImageWidth) * renderedImageWidth + offsetX;
            const displayTop = (coord.top / mapperOriginalImageHeight) * renderedImageHeight + offsetY;
            const displayWidth = (coord.width / mapperOriginalImageWidth) * renderedImageWidth;
            const displayHeight = (coord.height / mapperOriginalImageHeight) * renderedImageHeight;
            
            area.style.left = displayLeft + 'px';
            area.style.top = displayTop + 'px';
            area.style.width = displayWidth + 'px';
            area.style.height = displayHeight + 'px';
            
            roomDisplay.appendChild(area);
            
            // Add to coordinates display
            const cssCode = `.${coord.selector} { top: ${coord.top}px; left: ${coord.left}px; width: ${coord.width}px; height: ${coord.height}px; }`;
            const jsArrayFormat = `{ selector: '.${coord.selector}', top: ${coord.top}, left: ${coord.left}, width: ${coord.width}, height: ${coord.height} }, // Area ${index + 1}`;
            
            coordinates.innerHTML += `
                <div style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <strong>Area ${index + 1} (${document.getElementById('roomMapperSelect').value}):</strong><br>
                    <div style="margin: 5px 0;">CSS: ${cssCode}</div>
                    <div style="margin: 5px 0;">JS Array: ${jsArrayFormat}</div>
                </div>
            `;
        });
        
        // Show preview message with color coding
        const mapType = mapData.map_name === 'Original' ? 'Original 🔒' : mapData.map_name;
        const status = mapData.is_active ? 'ACTIVE' : 'INACTIVE';
        showMapperMessage(`Previewing: ${mapType} (${status}) - ${mapData.coordinates.length} areas`, 'info');
    }
}

async function loadSavedMap() {
    const savedMapsSelect = document.getElementById('savedMapsSelect');
    const selectedOption = savedMapsSelect.options[savedMapsSelect.selectedIndex];
    
    if (!selectedOption || !selectedOption.value) {
        showMapperMessage('Please select a map to load', 'error');
        return;
    }
    
    // Use the preview function but with a different message
    previewSelectedMap();
    showMapperMessage('Map loaded for editing!', 'success');
}

async function applySavedMap() {
    const savedMapsSelect = document.getElementById('savedMapsSelect');
    const selectedOption = savedMapsSelect.options[savedMapsSelect.selectedIndex];
    
    if (!selectedOption || !selectedOption.value) {
        showMapperMessage('Please select a map to apply', 'error');
        return;
    }
    
    const mapId = selectedOption.value;
    const roomType = document.getElementById('roomMapperSelect').value;
    
    if (!confirm('This will apply the selected map to the live room. Are you sure?')) {
        return;
    }
    
    try {
        const response = await fetch('api/room_maps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'apply',
                room_type: roomType,
                map_id: mapId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMapperMessage('Map applied to live room successfully!', 'success');
            loadSavedMapsForRoom(roomType); // Refresh the list to show active status
        } else {
            showMapperMessage('Error applying map: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error applying map:', error);
        showMapperMessage('Error applying map', 'error');
    }
}

async function deleteSavedMap() {
    const savedMapsSelect = document.getElementById('savedMapsSelect');
    const selectedOption = savedMapsSelect.options[savedMapsSelect.selectedIndex];
    
    if (!selectedOption || !selectedOption.value) {
        showMapperMessage('Please select a map to delete', 'error');
        return;
    }
    
    const mapId = selectedOption.value;
    const mapName = selectedOption.textContent;
    
    if (!confirm(`Are you sure you want to delete the map "${mapName}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch('api/room_maps.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                map_id: mapId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMapperMessage('Map deleted successfully!', 'success');
            loadSavedMapsForRoom(document.getElementById('roomMapperSelect').value);
        } else {
            showMapperMessage('Error deleting map: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error deleting map:', error);
        showMapperMessage('Error deleting map', 'error');
    }
}

async function updateMapStatus(roomType) {
    try {
        const response = await fetch(`api/room_maps.php?room_type=${roomType}&active_only=true`);
        const data = await response.json();
        
        const statusDiv = document.getElementById('mapStatus');
        
        if (data.success && data.map) {
            statusDiv.innerHTML = `<span class="text-green-600">✓ Active Map: ${data.map.map_name} (${data.map.coordinates.length} areas)</span>`;
        } else {
            statusDiv.innerHTML = `<span class="text-yellow-600">⚠ No active map for this room</span>`;
        }
    } catch (error) {
        console.error('Error checking map status:', error);
    }
}

function showMapperMessage(message, type) {
    const statusDiv = document.getElementById('mapStatus');
    const colorClass = type === 'success' ? 'text-green-600' : type === 'error' ? 'text-red-600' : 'text-blue-600';
    const icon = type === 'success' ? '✓' : type === 'error' ? '❌' : 'ℹ';
    
    statusDiv.innerHTML = `<span class="${colorClass}">${icon} ${message}</span>`;
    
    // Clear message after 5 seconds
    setTimeout(() => {
        const roomType = document.getElementById('roomMapperSelect').value;
        updateMapStatus(roomType);
    }, 5000);
}

// History functionality
function toggleHistoryView() {
    const historySection = document.getElementById('historySection');
    const toggleText = document.getElementById('historyToggleText');
    
    if (historySection.classList.contains('hidden')) {
        historySection.classList.remove('hidden');
        toggleText.textContent = 'Hide History';
        loadRoomHistory();
    } else {
        historySection.classList.add('hidden');
        toggleText.textContent = 'Show History';
    }
}

async function loadRoomHistory() {
    const roomType = document.getElementById('roomMapperSelect').value;
    const historyList = document.getElementById('historyList');
    
    try {
        const response = await fetch(`api/room_maps.php?room_type=${roomType}`);
        const data = await response.json();
        
        if (data.success && data.maps && data.maps.length > 0) {
            historyList.innerHTML = '';
            
            data.maps.forEach(map => {
                const historyItem = document.createElement('div');
                historyItem.className = 'border border-gray-300 rounded p-3 bg-white';
                
                const statusBadge = map.is_active ? 
                    '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">ACTIVE</span>' : 
                    '<span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">INACTIVE</span>';
                
                const coordinateCount = map.coordinates ? map.coordinates.length : 0;
                
                historyItem.innerHTML = `
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h5 class="font-medium text-gray-900">${map.map_name}</h5>
                            <p class="text-sm text-gray-600">
                                Created: ${new Date(map.created_at).toLocaleString()}<br>
                                Areas: ${coordinateCount} | Room: ${roomType}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1">
                            ${statusBadge}
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="restoreMap(${map.id}, '${map.map_name}', false)" 
                                class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs rounded">
                            Restore as New
                        </button>
                        <button onclick="restoreMap(${map.id}, '${map.map_name}', true)" 
                                class="px-2 py-1 bg-purple-500 hover:bg-purple-600 text-white text-xs rounded">
                            Restore & Apply
                        </button>
                        <button onclick="previewHistoricalMap(${map.id}, '${map.map_name}')" 
                                class="px-2 py-1 bg-gray-500 hover:bg-gray-600 text-white text-xs rounded">
                            Preview
                        </button>
                        ${!map.is_active && map.map_name !== 'Original' ? `<button onclick="deleteHistoricalMap(${map.id}, '${map.map_name}')" 
                                class="px-2 py-1 bg-red-500 hover:bg-red-600 text-white text-xs rounded">
                            Delete
                        </button>` : ''}
                        ${map.map_name === 'Original' ? `<span class="px-2 py-1 bg-gray-300 text-gray-600 text-xs rounded cursor-not-allowed">
                            🔒 Protected
                        </span>` : ''}
                    </div>
                `;
                
                historyList.appendChild(historyItem);
            });
        } else {
            historyList.innerHTML = '<p class="text-gray-500 text-sm">No map history found for this room</p>';
        }
    } catch (error) {
        console.error('Error loading room history:', error);
        historyList.innerHTML = '<p class="text-red-500 text-sm">Error loading history</p>';
    }
}

async function restoreMap(mapId, mapName, applyImmediately) {
    const action = applyImmediately ? 'restore and apply' : 'restore';
    
    if (!confirm(`Are you sure you want to ${action} the map "${mapName}"?`)) {
        return;
    }
    
    try {
        const response = await fetch('api/room_maps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'restore',
                map_id: mapId,
                apply_immediately: applyImmediately
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const message = applyImmediately ? 
                'Map restored and applied successfully!' : 
                'Map restored successfully!';
            showMapperMessage(message, 'success');
            
            // Refresh the lists
            const roomType = document.getElementById('roomMapperSelect').value;
            loadSavedMapsForRoom(roomType);
            loadRoomHistory();
        } else {
            showMapperMessage('Error restoring map: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error restoring map:', error);
        showMapperMessage('Error restoring map', 'error');
    }
}

async function previewHistoricalMap(mapId, mapName) {
    try {
        const response = await fetch(`api/room_maps.php?room_type=${document.getElementById('roomMapperSelect').value}`);
        const data = await response.json();
        
        if (data.success && data.maps) {
            const map = data.maps.find(m => m.id == mapId);
            if (map) {
                clearMapperAreas();
                
                // Load the coordinates visually (similar to loadSavedMap but for preview)
                if (map.coordinates && map.coordinates.length > 0) {
                    const roomDisplay = document.getElementById('roomMapperDisplay');
                    const coordinates = document.getElementById('mapperCoordinates');
                    
                    map.coordinates.forEach((coord, index) => {
                        mapperAreaCount++;
                        
                        // Create visual area
                        const area = document.createElement('div');
                        area.className = 'room-mapper-clickable-area';
                        area.style.border = '2px solid orange'; // Different color for preview
                        area.style.backgroundColor = 'rgba(255, 165, 0, 0.2)';
                        area.setAttribute('data-area', coord.selector);
                        
                        // Convert coordinates to display coordinates
                        const wrapperWidth = roomDisplay.offsetWidth;
                        const wrapperHeight = roomDisplay.offsetHeight;
                        const wrapperAspectRatio = wrapperWidth / wrapperHeight;
                        const imageAspectRatio = mapperOriginalImageWidth / mapperOriginalImageHeight;
                        
                        let renderedImageWidth, renderedImageHeight;
                        let offsetX = 0;
                        let offsetY = 0;
                        
                        if (wrapperAspectRatio > imageAspectRatio) {
                            renderedImageHeight = wrapperHeight;
                            renderedImageWidth = renderedImageHeight * imageAspectRatio;
                            offsetX = (wrapperWidth - renderedImageWidth) / 2;
                        } else {
                            renderedImageWidth = wrapperWidth;
                            renderedImageHeight = renderedImageWidth / imageAspectRatio;
                            offsetY = (wrapperHeight - renderedImageHeight) / 2;
                        }
                        
                        const displayLeft = (coord.left / mapperOriginalImageWidth) * renderedImageWidth + offsetX;
                        const displayTop = (coord.top / mapperOriginalImageHeight) * renderedImageHeight + offsetY;
                        const displayWidth = (coord.width / mapperOriginalImageWidth) * renderedImageWidth;
                        const displayHeight = (coord.height / mapperOriginalImageHeight) * renderedImageHeight;
                        
                        area.style.left = displayLeft + 'px';
                        area.style.top = displayTop + 'px';
                        area.style.width = displayWidth + 'px';
                        area.style.height = displayHeight + 'px';
                        
                        roomDisplay.appendChild(area);
                    });
                    
                    showMapperMessage(`Previewing historical map: ${mapName}`, 'info');
                }
            }
        }
    } catch (error) {
        console.error('Error previewing historical map:', error);
        showMapperMessage('Error previewing map', 'error');
    }
}

async function deleteHistoricalMap(mapId, mapName) {
    if (!confirm(`Are you sure you want to permanently delete the historical map "${mapName}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch('api/room_maps.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                map_id: mapId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMapperMessage('Historical map deleted successfully!', 'success');
            loadRoomHistory(); // Refresh history
        } else {
            showMapperMessage('Error deleting historical map: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error deleting historical map:', error);
        showMapperMessage('Error deleting historical map', 'error');
    }
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const idModal = document.getElementById('idLegendModal');
    const mapperModal = document.getElementById('roomMapperModal');
    
    if (event.target == idModal) {
        closeIdLegendModal();
    }
    if (event.target == mapperModal) {
        closeRoomMapperModal();
    }
}
</script> 

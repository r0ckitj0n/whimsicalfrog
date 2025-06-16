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
            <p class="text-sm text-gray-600 mb-3">Configure email settings for order confirmations and notifications.</p>
            <div class="flex flex-wrap gap-2">
                <button onclick="openEmailConfigModal()" class="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Email Configuration
                </button>
                <button onclick="openEmailHistoryModal()" class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Email History
                </button>
                <button onclick="fixSampleEmail()" class="inline-flex items-center px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded" id="fixSampleEmailBtn">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Fix Sample Email
                </button>
            </div>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-3">Manage product categories used across inventory and shop.</p>
            <a href="/?page=admin&section=categories" class="brand-button inline-flex items-center px-4 py-2 text-white text-sm font-medium rounded mr-3" style="color: white !important;">Manage Categories</a>
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
        <div>
            <p class="text-sm text-gray-600 mb-3">Manage background images for all rooms. Original backgrounds are protected and cannot be deleted.</p>
            <button onclick="openBackgroundManagerModal()" class="inline-flex items-center px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white text-sm font-medium rounded">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Background Manager
            </button>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-3">Assign product categories to numbered rooms for better organization and automatic product filtering.</p>
            <button onclick="openRoomCategoryManagerModal()" class="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                Room-Category Assignments
            </button>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-3">Visual mapper to see room-category relationships and manage clickable area assignments.</p>
            <div class="flex gap-2">
                <button onclick="openRoomCategoryMapperModal()" class="inline-flex items-center px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                    </svg>
                    Room-Category Mapper
                </button>
                <button onclick="openAreaItemMapperModal()" class="inline-flex items-center px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium rounded">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                    Area-Item Mapper
                </button>
            </div>
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
        <div class="flex justify-between items-center p-4 border-b">
            <h2 class="text-xl font-bold text-gray-800">Room Mapper - Clickable Area Helper</h2>
            <button onclick="closeRoomMapperModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        
        <div class="p-4">
            <p class="text-gray-600 mb-3 text-sm">This tool helps you map clickable areas on your room images with the same scaling as your live site.</p>
            
            <div class="controls mb-3">
                <div class="flex flex-wrap gap-2 mb-2 text-sm">
                    <div class="flex items-center">
                        <label for="roomMapperSelect" class="mr-2 text-sm">Room:</label>
                        <select id="roomMapperSelect" class="px-2 py-1 border border-gray-300 rounded text-sm">
                            <option value="landing" selected>Landing Page</option>
                            <option value="room_main">Main Room</option>
                            <option value="room_artwork">Artwork Room</option>
                            <option value="room_tshirts">T-Shirts Room</option>
                            <option value="room_tumblers">Tumblers Room</option>
                            <option value="room_sublimation">Sublimation Room</option>
                            <option value="room_windowwraps">Window Wraps Room</option>
                        </select>
                    </div>
                    <button onclick="toggleMapperGrid()" class="px-2 py-1 bg-gray-500 text-white rounded text-sm">Grid</button>
                    <button onclick="clearMapperAreas()" class="px-2 py-1 bg-red-500 text-white rounded text-sm">Clear</button>
                </div>
                
                <div class="flex flex-wrap gap-2 mb-2 text-sm">
                    <div class="flex items-center">
                        <input type="text" id="mapNameInput" placeholder="Map name..." class="px-2 py-1 border border-gray-300 rounded mr-1 text-sm" />
                        <button onclick="saveRoomMap()" class="px-2 py-1 bg-green-500 hover:bg-green-600 text-white rounded text-sm">Save</button>
                    </div>
                    <div class="flex items-center">
                        <select id="savedMapsSelect" class="px-2 py-1 border border-gray-300 rounded mr-1 text-sm">
                            <option value="">Select saved map...</option>
                        </select>
                        <button onclick="loadSavedMap()" class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded mr-1 text-sm">Load</button>
                        <button onclick="applySavedMap()" class="px-2 py-1 bg-purple-500 hover:bg-purple-600 text-white rounded mr-1 text-sm">Apply</button>
                        <button onclick="deleteSavedMap()" class="px-2 py-1 bg-red-500 hover:bg-red-600 text-white rounded text-sm">Delete</button>
                    </div>
                    
                    <!-- Map Preview Legend -->
                    <div class="mt-1 text-xs text-gray-600 bg-gray-50 p-1 rounded border">
                        <strong>Colors:</strong>
                        <span class="inline-flex items-center ml-1">
                            <span class="w-2 h-2 border border-green-500 bg-green-200 rounded mr-1"></span>
                            Original
                        </span>
                        <span class="inline-flex items-center ml-1">
                            <span class="w-2 h-2 border border-blue-500 bg-blue-200 rounded mr-1"></span>
                            Active
                        </span>
                        <span class="inline-flex items-center ml-1">
                            <span class="w-2 h-2 border border-gray-500 bg-gray-200 rounded mr-1"></span>
                            Inactive
                        </span>
                    </div>
                </div>
                
                <div id="mapStatus" class="text-xs mb-2"></div>
                
                <!-- History Section -->
                <div class="border-t pt-2">
                    <div class="flex items-center gap-2 mb-2">
                        <button onclick="toggleHistoryView()" class="px-2 py-1 bg-indigo-500 hover:bg-indigo-600 text-white rounded text-xs">
                            <span id="historyToggleText">📜 History</span>
                        </button>
                        <span class="text-xs text-gray-600">View previous versions</span>
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
                <div class="room-mapper-wrapper relative w-full bg-gray-800 rounded-lg overflow-hidden" id="roomMapperDisplay" style="height: 85vh; background-size: contain; background-position: center; background-repeat: no-repeat;">
                    <div class="grid-overlay absolute top-0 left-0 w-full h-full pointer-events-none hidden" id="mapperGridOverlay" style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 20px 20px;"></div>
                    <!-- Clickable areas will be added here -->
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded p-2 mb-2">
                <p class="text-blue-800 text-xs"><strong>Note:</strong> Uses exact scaling as live site for perfect coordinate matching.</p>
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
    const roomType = document.getElementById('roomMapperSelect').value;
    
    // Check if it's a protected Original map
    if (mapName.includes('Original') && mapName.includes('🔒')) {
        showMapperMessage('❌ Original maps are protected and cannot be deleted!', 'error');
        return;
    }
    
    // Create a friendly confirmation dialog
    const isActive = mapName.includes('(ACTIVE)');
    const activeWarning = isActive ? '\n\n⚠️ This is currently the ACTIVE map for this room!' : '';
    
    const confirmMessage = `🗑️ Delete Map Confirmation
    
Map: "${mapName.replace(/\s*\(ACTIVE\)\s*/, '').replace(/\s*🔒\s*PROTECTED\s*/, '')}"
Room: ${roomType}${activeWarning}

Are you sure you want to permanently delete this map? 

This action cannot be undone, and all coordinate data will be lost forever.`;
    
    if (!confirm(confirmMessage)) {
        showMapperMessage('Map deletion cancelled', 'info');
        return;
    }
    
    // Show deleting message
    showMapperMessage('🗑️ Deleting map...', 'info');
    
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
            showMapperMessage('🎉 Map deleted successfully! The map has been permanently removed.', 'success');
            loadSavedMapsForRoom(roomType);
            
            // Clear the preview if this map was being previewed
            clearMapperAreas();
        } else {
            if (data.message && data.message.includes('Original maps cannot be deleted')) {
                showMapperMessage('🔒 Original maps are protected and cannot be deleted!', 'error');
            } else {
                showMapperMessage('❌ Failed to delete map: ' + data.message, 'error');
            }
        }
    } catch (error) {
        console.error('Error deleting map:', error);
        showMapperMessage('❌ Network error occurred while deleting map. Please try again.', 'error');
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
    const roomType = document.getElementById('roomMapperSelect').value;
    
    // Create a friendly confirmation dialog for historical maps
    const confirmMessage = `🗑️ Delete Historical Map
    
Map: "${mapName}"
Room: ${roomType}
Type: Historical/Archived Map

⚠️ This will permanently delete this map from your history!

Are you sure you want to continue? This action cannot be undone, and you won't be able to restore this map version in the future.`;
    
    if (!confirm(confirmMessage)) {
        showMapperMessage('Historical map deletion cancelled', 'info');
        return;
    }
    
    // Show deleting message
    showMapperMessage('🗑️ Deleting historical map...', 'info');
    
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
            showMapperMessage('🎉 Historical map deleted successfully! The map has been removed from your history.', 'success');
            loadRoomHistory(); // Refresh history
        } else {
            if (data.message && data.message.includes('Original maps cannot be deleted')) {
                showMapperMessage('🔒 Original maps are protected and cannot be deleted!', 'error');
            } else {
                showMapperMessage('❌ Failed to delete historical map: ' + data.message, 'error');
            }
        }
    } catch (error) {
        console.error('Error deleting historical map:', error);
        showMapperMessage('❌ Network error occurred while deleting historical map. Please try again.', 'error');
    }
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const idModal = document.getElementById('idLegendModal');
    const mapperModal = document.getElementById('roomMapperModal');
    const backgroundModal = document.getElementById('backgroundManagerModal');
    
    if (event.target == idModal) {
        closeIdLegendModal();
    }
    if (event.target == mapperModal) {
        closeRoomMapperModal();
    }
    if (event.target == backgroundModal) {
        closeBackgroundManagerModal();
    }
    
    const roomCategoryModal = document.getElementById('roomCategoryManagerModal');
    if (event.target == roomCategoryModal) {
        closeRoomCategoryManagerModal();
    }
}

// Room-Category Manager Functions
function openRoomCategoryManagerModal() {
    document.getElementById('roomCategoryManagerModal').style.display = 'flex';
    loadAvailableCategories();
    loadRoomCategorySummary();
    loadRoomCategories();
    
    // Add event listener for room selection change
    document.getElementById('roomCategorySelect').addEventListener('change', loadRoomCategories);
}

function closeRoomCategoryManagerModal() {
    document.getElementById('roomCategoryManagerModal').style.display = 'none';
}

async function loadAvailableCategories() {
    try {
        const response = await fetch('api/room_category_assignments.php');
        const data = await response.json();
        
        if (data.success) {
            const categorySelect = document.getElementById('categorySelect');
            categorySelect.innerHTML = '<option value="">Select a category...</option>';
            
            data.available_categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                if (category.description) {
                    option.title = category.description;
                }
                categorySelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading categories:', error);
        // Show friendly error in the dropdown
        const categorySelect = document.getElementById('categorySelect');
        categorySelect.innerHTML = '<option value="">⚠️ Unable to load categories - please refresh</option>';
    }
}

async function loadRoomCategorySummary() {
    try {
        const response = await fetch('api/room_category_assignments.php');
        const data = await response.json();
        
        if (data.success) {
            const summaryDiv = document.getElementById('roomCategorySummary');
            summaryDiv.innerHTML = '';
            
            data.summary.forEach(room => {
                const roomDiv = document.createElement('div');
                roomDiv.className = 'bg-white border rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow';
                
                const primaryBadge = room.primary_category_names ? 
                    `<span class="inline-block bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full mb-2">👑 ${room.primary_category_names}</span>` : 
                    '<span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full mb-2">No Primary</span>';
                
                roomDiv.innerHTML = `
                    <div class="mb-2">
                        <h4 class="font-semibold text-gray-800 text-sm mb-1">Room ${room.room_number}</h4>
                        <p class="text-xs text-gray-600">${room.room_name}</p>
                    </div>
                    <div class="mb-2">
                        ${primaryBadge}
                    </div>
                    <div class="text-xs text-gray-600">
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-medium">Categories:</span>
                            <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">${room.total_categories}</span>
                        </div>
                        <div class="text-xs text-gray-500 truncate" title="${room.all_categories || 'None'}">
                            ${room.all_categories || 'None'}
                        </div>
                    </div>
                `;
                
                summaryDiv.appendChild(roomDiv);
            });
        }
    } catch (error) {
        console.error('Error loading room category summary:', error);
        document.getElementById('roomCategorySummary').innerHTML = '<div class="col-span-full"><div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center"><p class="text-red-600">😕 Unable to load room summary</p><p class="text-sm text-red-500 mt-1">Please refresh the page or try again later</p></div></div>';
    }
}

async function loadRoomCategories() {
    const roomNumber = document.getElementById('roomCategorySelect').value;
    
    try {
        const response = await fetch(`api/room_category_assignments.php?room_number=${roomNumber}`);
        const data = await response.json();
        
        if (data.success) {
            const listDiv = document.getElementById('roomCategoriesList');
            listDiv.innerHTML = '';
            
            if (data.assignments.length === 0) {
                listDiv.innerHTML = '<p class="text-gray-500 italic">No categories assigned to this room</p>';
                return;
            }
            
            data.assignments.forEach(assignment => {
                const assignmentDiv = document.createElement('div');
                assignmentDiv.className = 'bg-white border rounded-lg p-3 flex justify-between items-center';
                
                const primaryBadge = assignment.is_primary ? 
                    '<span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full mr-2">👑 PRIMARY</span>' : 
                    '<span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full mr-2">Secondary</span>';
                
                assignmentDiv.innerHTML = `
                    <div>
                        <div class="flex items-center mb-1">
                            ${primaryBadge}
                            <span class="font-medium">${assignment.category_name}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            Created: ${new Date(assignment.created_at).toLocaleDateString()}
                        </div>
                        ${assignment.category_description ? `<div class="text-xs text-gray-400 mt-1">${assignment.category_description}</div>` : ''}
                    </div>
                    <div class="flex space-x-2">
                        ${!assignment.is_primary ? `<button onclick="setPrimaryCategory(${roomNumber}, ${assignment.category_id})" class="text-orange-600 hover:text-orange-800 text-sm">Make Primary</button>` : ''}
                        <button onclick="removeRoomCategory(${assignment.id})" class="text-red-600 hover:text-red-800 text-sm">Remove</button>
                    </div>
                `;
                
                listDiv.appendChild(assignmentDiv);
            });
        }
    } catch (error) {
        console.error('Error loading room categories:', error);
        document.getElementById('roomCategoriesList').innerHTML = '<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center"><p class="text-red-600">😕 Unable to load categories for this room</p><p class="text-sm text-red-500 mt-1">Please try selecting the room again or refresh the page</p></div>';
    }
}

async function addRoomCategory() {
    const roomNumber = parseInt(document.getElementById('roomCategorySelect').value);
    const categoryId = parseInt(document.getElementById('categorySelect').value);
    const isPrimary = document.getElementById('isPrimaryCategory').checked;
    
    if (!categoryId) {
        showNotification('Category Required', 'Please select a category to assign to this room.', 'warning');
        return;
    }
    
    // Get room name from the select option text
    const roomSelect = document.getElementById('roomCategorySelect');
    const roomName = roomSelect.options[roomSelect.selectedIndex].text.split(' - ')[1] || 'Unknown Room';
    
    try {
        const response = await fetch('api/room_category_assignments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add_assignment',
                room_number: roomNumber,
                room_name: roomName,
                category_id: categoryId,
                is_primary: isPrimary ? 1 : 0,
                display_order: 0
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Reset form
            document.getElementById('categorySelect').value = '';
            document.getElementById('isPrimaryCategory').checked = false;
            
            // Reload data
            loadRoomCategories();
            loadRoomCategorySummary();
            
            // Get category name for friendly message
            const categorySelect = document.getElementById('categorySelect');
            const categoryName = categorySelect.options[categorySelect.selectedIndex]?.text || 'Category';
            
            showNotification('Success!', `${categoryName} has been assigned to this room.`, 'success');
        } else {
            showNotification('Unable to assign category', data.message, 'error');
        }
    } catch (error) {
        console.error('Error adding room category:', error);
        showNotification('Connection Problem', 'Please check your internet connection and try again.', 'error');
    }
}

async function setPrimaryCategory(roomNumber, categoryId) {
    try {
        const response = await fetch('api/room_category_assignments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'set_primary',
                room_number: roomNumber,
                category_id: categoryId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadRoomCategories();
            loadRoomCategorySummary();
            
            showNotification('Primary Category Updated!', 'This category is now the main category for this room.', 'success');
        } else {
            showNotification('Couldn\'t update primary category', data.message, 'error');
        }
    } catch (error) {
        console.error('Error setting primary category:', error);
        showNotification('Connection Issue', 'Please try again in a moment.', 'error');
    }
}

async function removeRoomCategory(assignmentId) {
    // Get the category name from the button's parent element
    const button = event.target;
    const assignmentDiv = button.closest('.bg-white');
    const categoryNameElement = assignmentDiv ? assignmentDiv.querySelector('.font-medium') : null;
    const categoryName = categoryNameElement ? categoryNameElement.textContent.trim() : 'this category';
    
    showConfirmation(
        `Remove ${categoryName}?`,
        `This will unassign ${categoryName} from this room. You can always add it back later if needed.`,
        async () => {
            try {
                const response = await fetch('api/room_category_assignments.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        assignment_id: assignmentId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    loadRoomCategories();
                    loadRoomCategorySummary();
                    showNotification('Success!', `${categoryName} removed successfully!`, 'success');
                } else {
                    showNotification('Error', `Unable to remove ${categoryName}: ${data.message}`, 'error');
                }
            } catch (error) {
                console.error('Error removing room category:', error);
                showNotification('Connection Error', 'Please check your internet connection and try again.', 'error');
            }
        }
    );
}

// Display order functionality removed from UI but still maintained in database
// Categories are still ordered by display_order field in the backend queries

// Room-Category Visual Mapper Functions
function openRoomCategoryMapperModal() {
    document.getElementById('roomCategoryMapperModal').style.display = 'flex';
    loadRoomCategoryCards();
}

function closeRoomCategoryMapperModal() {
    document.getElementById('roomCategoryMapperModal').style.display = 'none';
}

async function loadRoomCategoryCards() {
    try {
        const response = await fetch('api/room_category_assignments.php?action=get_summary');
        const result = await response.json();
        
        if (result.success) {
            displayRoomCategoryCards(result.summary);
        } else {
            showNotification('Load Error', 'Failed to load room category mappings', 'error');
        }
    } catch (error) {
        console.error('Error loading room category cards:', error);
        showNotification('Connection Error', 'Failed to load room category mappings', 'error');
    }
}

function displayRoomCategoryCards(summary) {
    const container = document.getElementById('roomCategoryCards');
    const roomNames = {
        '0': 'Landing Page',
        '1': 'Main Room',
        '2': 'T-Shirts Room',
        '3': 'Tumblers Room',
        '4': 'Artwork Room',
        '5': 'Sublimation Room',
        '6': 'Window Wraps Room'
    };
    
    let cardsHTML = '';
    
    // Create cards for all rooms (0-6)
    for (let roomNum = 0; roomNum <= 6; roomNum++) {
        const roomData = summary.find(s => s.room_number == roomNum);
        const roomName = roomNames[roomNum];
        
        cardsHTML += `
            <div class="bg-white border-2 border-teal-200 rounded-lg p-4 hover:shadow-lg transition-shadow cursor-pointer" onclick="openRoomCategoryManagerModal(${roomNum})">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-bold text-gray-800">Room ${roomNum}</h4>
                    <span class="text-xs bg-teal-100 text-teal-800 px-2 py-1 rounded-full">${roomData ? roomData.total_categories : 0} categories</span>
                </div>
                <div class="text-sm text-gray-600 mb-3">${roomName}</div>
                
                ${roomData && roomData.primary_category ? `
                    <div class="mb-2">
                        <div class="flex items-center text-sm">
                            <span class="text-yellow-500 mr-1">👑</span>
                            <span class="font-semibold text-gray-800">${roomData.primary_category}</span>
                        </div>
                    </div>
                ` : ''}
                
                ${roomData && roomData.secondary_categories && roomData.secondary_categories.length > 0 ? `
                    <div class="text-xs text-gray-600">
                        <div class="font-medium mb-1">Secondary:</div>
                        <div class="space-y-1">
                            ${roomData.secondary_categories.map(cat => `<div class="bg-gray-100 px-2 py-1 rounded text-xs">${cat}</div>`).join('')}
                        </div>
                    </div>
                ` : roomData && roomData.total_categories === 0 ? `
                    <div class="text-xs text-gray-400 italic">No categories assigned</div>
                ` : ''}
            </div>
        `;
    }
    
    container.innerHTML = cardsHTML;
}

// Area-Item Mapper Functions
let selectedAreaForSwap = null;
let areaMapperData = {
    coordinates: [],
    mappings: [],
    availableItems: [],
    availableCategories: []
};

function openAreaItemMapperModal() {
    document.getElementById('areaItemMapperModal').style.display = 'flex';
    initializeAreaItemMapper();
}

function closeAreaItemMapperModal() {
    document.getElementById('areaItemMapperModal').style.display = 'none';
    selectedAreaForSwap = null;
}

async function initializeAreaItemMapper() {
    // Load available rooms first
    await loadAvailableRooms();
    
    // Load available items and categories
    await loadAvailableItemsAndCategories();
    
    // Set up room selection change handler
    const roomSelect = document.getElementById('areaMapperRoomSelect');
    roomSelect.addEventListener('change', function() {
        loadAreaMapperRoom(this.value);
    });
    
    // Set up mapping type change handler
    const mappingTypeSelect = document.getElementById('mappingType');
    mappingTypeSelect.addEventListener('change', function() {
        toggleMappingSelectors(this.value);
    });
    
    // Load initial room
    if (roomSelect.value) {
        loadAreaMapperRoom(roomSelect.value);
    }
}

async function loadAvailableRooms() {
    try {
        const response = await fetch('api/area_mappings.php?action=get_available_rooms');
        const result = await response.json();
        
        if (result.success) {
            const roomSelect = document.getElementById('areaMapperRoomSelect');
            roomSelect.innerHTML = '';
            
            if (result.rooms.length === 0) {
                roomSelect.innerHTML = '<option value="">No rooms with clickable areas found</option>';
                return;
            }
            
            result.rooms.forEach(room => {
                const option = document.createElement('option');
                option.value = room.value;
                option.textContent = room.name;
                roomSelect.appendChild(option);
            });
        } else {
            console.error('Failed to load available rooms:', result.message);
        }
    } catch (error) {
        console.error('Error loading available rooms:', error);
    }
}

async function loadAvailableItemsAndCategories() {
    try {
        // Load items
        const itemsResponse = await fetch('api/area_mappings.php?action=get_available_items');
        const itemsResult = await itemsResponse.json();
        
        if (itemsResult.success) {
            areaMapperData.availableItems = itemsResult.items;
            populateItemSelect();
        }
        
        // Load categories
        const categoriesResponse = await fetch('api/area_mappings.php?action=get_available_categories');
        const categoriesResult = await categoriesResponse.json();
        
        if (categoriesResult.success) {
            areaMapperData.availableCategories = categoriesResult.categories;
            populateCategorySelect();
        }
    } catch (error) {
        console.error('Error loading items and categories:', error);
    }
}

function populateItemSelect() {
    const select = document.getElementById('itemSelect');
    select.innerHTML = '<option value="">Select item...</option>';
    
    areaMapperData.availableItems.forEach(item => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = `${item.name} - $${item.retailPrice} (${item.category || 'No Category'})`;
        select.appendChild(option);
    });
}

function populateCategorySelect() {
    const select = document.getElementById('categorySelect');
    select.innerHTML = '<option value="">Select category...</option>';
    
    areaMapperData.availableCategories.forEach(category => {
        const option = document.createElement('option');
        option.value = category.id;
        option.textContent = category.name;
        select.appendChild(option);
    });
}

function toggleMappingSelectors(type) {
    const itemSelector = document.getElementById('itemSelector');
    const categorySelector = document.getElementById('categorySelector');
    
    if (type === 'item') {
        itemSelector.classList.remove('hidden');
        categorySelector.classList.add('hidden');
    } else if (type === 'category') {
        itemSelector.classList.add('hidden');
        categorySelector.classList.remove('hidden');
    } else {
        itemSelector.classList.add('hidden');
        categorySelector.classList.add('hidden');
    }
}

async function loadAreaMapperRoom(roomType) {
    try {
        // Load room coordinates
        const coordResponse = await fetch(`api/area_mappings.php?action=get_room_coordinates&room_type=${roomType}`);
        const coordResult = await coordResponse.json();
        
        if (coordResult.success) {
            areaMapperData.coordinates = coordResult.coordinates;
            populateAreaSelector();
            displayRoomBackground(roomType);
        }
        
        // Load existing mappings
        const mappingsResponse = await fetch(`api/area_mappings.php?action=get_mappings&room_type=${roomType}`);
        const mappingsResult = await mappingsResponse.json();
        
        if (mappingsResult.success) {
            areaMapperData.mappings = mappingsResult.mappings;
            displayAreaMappings();
            displayVisualAreas();
        }
    } catch (error) {
        console.error('Error loading area mapper room:', error);
        showNotification('Load Error', 'Failed to load room data', 'error');
    }
}

function populateAreaSelector() {
    const select = document.getElementById('areaSelector');
    select.innerHTML = '<option value="">Select area...</option>';
    
    areaMapperData.coordinates.forEach(coord => {
        const option = document.createElement('option');
        option.value = coord.selector;
        option.textContent = coord.selector.replace('.area-', 'Area ');
        select.appendChild(option);
    });
}

function displayRoomBackground(roomType) {
    const display = document.getElementById('areaMapperDisplay');
    
    if (roomType === 'landing') {
        display.style.backgroundImage = "url('images/home_background.png')";
    } else {
        display.style.backgroundImage = `url('images/${roomType}.png')`;
    }
}

function displayAreaMappings() {
    const container = document.getElementById('areaMappingsList');
    
    if (areaMapperData.mappings.length === 0) {
        container.innerHTML = '<div class="text-gray-500 text-sm">No area mappings found</div>';
        return;
    }
    
    let html = '';
    areaMapperData.mappings.forEach(mapping => {
        const typeIcon = mapping.mapping_type === 'item' ? '🟢' : '🔵';
        const typeLabel = mapping.mapping_type === 'item' ? 'Item' : 'Category';
        const price = mapping.item_price ? ` - $${mapping.item_price}` : '';
        
        html += `
            <div class="bg-gray-50 border rounded p-3 area-mapping-item" data-mapping-id="${mapping.id}">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="font-medium text-sm">${mapping.area_selector.replace('.area-', 'Area ')}</div>
                        <div class="text-xs text-gray-600">${typeIcon} ${typeLabel}: ${mapping.mapped_name}${price}</div>
                    </div>
                    <button onclick="removeAreaMapping(${mapping.id})" class="text-red-500 hover:text-red-700 text-xs ml-2">
                        Remove
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function displayVisualAreas() {
    const display = document.getElementById('areaMapperDisplay');
    
    // Clear existing areas
    display.querySelectorAll('.visual-area').forEach(area => area.remove());
    
    // Add visual areas
    areaMapperData.coordinates.forEach(coord => {
        const mapping = areaMapperData.mappings.find(m => m.area_selector === coord.selector);
        
        const area = document.createElement('div');
        area.className = 'visual-area absolute cursor-pointer transition-all duration-200';
        area.dataset.selector = coord.selector;
        area.dataset.mappingId = mapping ? mapping.id : '';
        
        // Color coding based on mapping type
        if (mapping) {
            if (mapping.mapping_type === 'item') {
                area.style.border = '3px solid #10b981'; // Green for items
                area.style.backgroundColor = 'rgba(16, 185, 129, 0.2)';
            } else {
                area.style.border = '3px solid #3b82f6'; // Blue for categories
                area.style.backgroundColor = 'rgba(59, 130, 246, 0.2)';
            }
            
            // Add tooltip
            area.title = `${coord.selector.replace('.area-', 'Area ')}: ${mapping.mapped_name}`;
        } else {
            area.style.border = '2px dashed #9ca3af'; // Gray for unmapped
            area.style.backgroundColor = 'rgba(156, 163, 175, 0.1)';
            area.title = `${coord.selector.replace('.area-', 'Area ')}: Unmapped`;
        }
        
        // Position the area
        const wrapperWidth = display.offsetWidth;
        const wrapperHeight = display.offsetHeight;
        const originalImageWidth = 1280;
        const originalImageHeight = 896;
        
        const wrapperAspectRatio = wrapperWidth / wrapperHeight;
        const imageAspectRatio = originalImageWidth / originalImageHeight;
        
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
        
        const displayLeft = (coord.left / originalImageWidth) * renderedImageWidth + offsetX;
        const displayTop = (coord.top / originalImageHeight) * renderedImageHeight + offsetY;
        const displayWidth = (coord.width / originalImageWidth) * renderedImageWidth;
        const displayHeight = (coord.height / originalImageHeight) * renderedImageHeight;
        
        area.style.left = displayLeft + 'px';
        area.style.top = displayTop + 'px';
        area.style.width = displayWidth + 'px';
        area.style.height = displayHeight + 'px';
        
        // Add click handler for swapping
        area.addEventListener('click', function() {
            handleAreaClick(this);
        });
        
        display.appendChild(area);
    });
}

function handleAreaClick(areaElement) {
    const mappingId = areaElement.dataset.mappingId;
    
    if (!mappingId) {
        showNotification('Unmapped Area', 'This area is not mapped to any item or category', 'info');
        return;
    }
    
    if (selectedAreaForSwap === null) {
        // First selection
        selectedAreaForSwap = mappingId;
        areaElement.style.boxShadow = '0 0 15px #f59e0b';
        areaElement.style.transform = 'scale(1.05)';
        showNotification('Area Selected', 'Area selected. Click another mapped area to swap.', 'info');
    } else if (selectedAreaForSwap === mappingId) {
        // Deselect
        selectedAreaForSwap = null;
        areaElement.style.boxShadow = '';
        areaElement.style.transform = '';
        showNotification('Selection Cleared', 'Selection cleared.', 'info');
    } else {
        // Second selection - perform swap
        swapAreaMappings(selectedAreaForSwap, mappingId);
    }
}

async function swapAreaMappings(area1Id, area2Id) {
    try {
        const response = await fetch('api/area_mappings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'swap_mappings',
                area1_id: area1Id,
                area2_id: area2Id
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Swap Successful', 'Area mappings swapped successfully', 'success');
            selectedAreaForSwap = null;
            
            // Reload the current room
            const roomType = document.getElementById('areaMapperRoomSelect').value;
            loadAreaMapperRoom(roomType);
        } else {
            showNotification('Swap Failed', result.message || 'Failed to swap mappings', 'error');
        }
    } catch (error) {
        console.error('Error swapping mappings:', error);
        showNotification('Connection Error', 'Failed to swap mappings', 'error');
    }
}

async function addAreaMapping() {
    const roomType = document.getElementById('areaMapperRoomSelect').value;
    const areaSelector = document.getElementById('areaSelector').value;
    const mappingType = document.getElementById('mappingType').value;
    const itemId = document.getElementById('itemSelect').value;
    const categoryId = document.getElementById('categorySelect').value;
    
    if (!roomType || !areaSelector || !mappingType) {
        showNotification('Missing Information', 'Please fill in all required fields', 'error');
        return;
    }
    
    if (mappingType === 'item' && !itemId) {
        showNotification('Missing Item', 'Please select an item', 'error');
        return;
    }
    
    if (mappingType === 'category' && !categoryId) {
        showNotification('Missing Category', 'Please select a category', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/area_mappings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add_mapping',
                room_type: roomType,
                area_selector: areaSelector,
                mapping_type: mappingType,
                item_id: mappingType === 'item' ? itemId : null,
                category_id: mappingType === 'category' ? categoryId : null
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Mapping Added', 'Area mapping added successfully', 'success');
            
            // Clear form
            document.getElementById('areaSelector').value = '';
            document.getElementById('mappingType').value = '';
            document.getElementById('itemSelect').value = '';
            document.getElementById('categorySelect').value = '';
            toggleMappingSelectors('');
            
            // Reload the current room
            loadAreaMapperRoom(roomType);
        } else {
            showNotification('Add Failed', result.message || 'Failed to add mapping', 'error');
        }
    } catch (error) {
        console.error('Error adding mapping:', error);
        showNotification('Connection Error', 'Failed to add mapping', 'error');
    }
}

async function removeAreaMapping(mappingId) {
    if (!confirm('Are you sure you want to remove this area mapping?')) {
        return;
    }
    
    try {
        const response = await fetch('api/area_mappings.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: mappingId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Mapping Removed', 'Area mapping removed successfully', 'success');
            
            // Reload the current room
            const roomType = document.getElementById('areaMapperRoomSelect').value;
            loadAreaMapperRoom(roomType);
        } else {
            showNotification('Remove Failed', result.message || 'Failed to remove mapping', 'error');
        }
    } catch (error) {
        console.error('Error removing mapping:', error);
        showNotification('Connection Error', 'Failed to remove mapping', 'error');
    }
}


</script>

<!-- Room-Category Manager Modal -->
<div id="roomCategoryManagerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white shadow-xl w-full h-full overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h2 class="text-xl font-bold text-gray-800">🏠📦 Room-Category Assignments</h2>
            <button onclick="closeRoomCategoryManagerModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        
        <div class="p-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Left Panel: Room Selection & Category Management -->
                <div>
                    <div class="mb-4">
                        <label for="roomCategorySelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room:</label>
                        <select id="roomCategorySelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="0">Room 0 - Landing Page</option>
                            <option value="1">Room 1 - Main Room</option>
                            <option value="2">Room 2 - T-Shirts Room</option>
                            <option value="3">Room 3 - Tumblers Room</option>
                            <option value="4">Room 4 - Artwork Room</option>
                            <option value="5">Room 5 - Sublimation Room</option>
                            <option value="6">Room 6 - Window Wraps Room</option>
                        </select>
                    </div>
                    
                    <div class="bg-white border rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Add Category to Room</h3>
                        <div class="space-y-3">
                            <div>
                                <label for="categorySelect" class="block text-sm font-medium text-gray-700 mb-1">Category:</label>
                                <select id="categorySelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                    <option value="">Select a category...</option>
                                </select>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="isPrimaryCategory" class="mr-2">
                                <label for="isPrimaryCategory" class="text-sm text-gray-700">Set as primary category for this room</label>
                            </div>
                            <button onclick="addRoomCategory()" class="w-full bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-semibold transition-colors">
                                Add Category
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Right Panel: Categories for Selected Room -->
                <div>
                    <h3 class="font-semibold text-gray-800 mb-3">Categories for Selected Room</h3>
                    <div id="roomCategoriesList" class="space-y-2 max-h-96 overflow-y-auto">
                        Loading categories...
                    </div>
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded p-3 mb-6">
                <h3 class="font-semibold text-blue-800 mb-2">💡 Room-Category Assignment Guide</h3>
                <div class="text-sm text-blue-700 space-y-1">
                    <p><strong>Numbered Rooms:</strong> Rooms are identified by numbers (0-6) for clearer organization</p>
                    <p><strong>Primary Category:</strong> The main category associated with a room (only one per room)</p>
                    <p><strong>Secondary Categories:</strong> Additional categories that can be displayed in a room</p>
                    <p><strong>Product Categories:</strong> T-Shirts, Tumblers, Artwork, Sublimation, Window Wraps</p>
                    <p><strong>Use Cases:</strong> Product filtering, automatic room navigation, category organization</p>
                    <p class="text-xs mt-2 italic">💡 Each room can have multiple categories assigned, but only one primary category for main identification.</p>
                </div>
            </div>
            
            <!-- All Room-Category Mappings Summary - Full Width at Bottom -->
            <div>
                <h3 class="font-semibold text-gray-800 mb-3">All Room-Category Mappings</h3>
                <div id="roomCategorySummary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    Loading summary...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Background Manager Modal -->
<div id="backgroundManagerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white shadow-xl w-full h-full overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h2 class="text-xl font-bold text-gray-800">🖼️ Background Manager</h2>
            <button onclick="closeBackgroundManagerModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        
        <div class="p-4">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Panel: Room Selection & Controls (1/3 width) -->
                <div class="lg:col-span-1">
                    <div class="mb-4">
                        <label for="backgroundRoomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room:</label>
                        <select id="backgroundRoomSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="landing">Landing Page</option>
                            <option value="room_main">Main Room</option>
                            <option value="room_artwork">Artwork Room</option>
                            <option value="room_tshirts">T-Shirts Room</option>
                            <option value="room_tumblers">Tumblers Room</option>
                            <option value="room_sublimation">Sublimation Room</option>
                            <option value="room_windowwraps">Window Wraps Room</option>
                        </select>
                    </div>
                    
                    <div class="bg-white border rounded-lg p-4 mb-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Upload New Background</h3>
                        <div class="space-y-3">
                            <div>
                                <label for="backgroundName" class="block text-sm font-medium text-gray-700 mb-1">Background Name:</label>
                                <input type="text" id="backgroundName" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" placeholder="e.g., Summer Theme">
                            </div>
                            <div>
                                <label for="backgroundFile" class="block text-sm font-medium text-gray-700 mb-1">Image File:</label>
                                <input type="file" id="backgroundFile" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                <p class="text-xs text-gray-500 mt-1">Supported: JPG, PNG, WebP (Max 10MB)</p>
                            </div>
                            <button onclick="uploadBackground()" class="w-full bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg font-semibold transition-colors">
                                Upload Background
                            </button>
                        </div>
                    </div>
                    
                    <!-- Available Backgrounds moved here -->
                    <div>
                        <h3 class="font-semibold text-gray-800 mb-3">Available Backgrounds</h3>
                        <div id="backgroundsList" class="space-y-3 max-h-96 overflow-y-auto">
                            Loading backgrounds...
                        </div>
                    </div>
                </div>
                
                <!-- Right Panel: Current Active Background Preview (2/3 width) -->
                <div class="lg:col-span-2">
                    <div class="bg-gray-50 rounded-lg p-4 h-full">
                        <h3 class="font-semibold text-gray-800 mb-3">Current Active Background</h3>
                        <div id="currentBackgroundInfo" class="text-sm text-gray-600 mb-4">
                            Loading...
                        </div>
                        <div id="currentBackgroundPreview" class="border rounded-lg overflow-hidden bg-white flex items-center justify-center" style="min-height: 400px; max-height: 80vh;">
                            <div class="text-gray-400 text-center">
                                <svg class="w-16 h-16 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                </svg>
                                <p>Background preview will appear here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded p-3 mt-4">
                <h3 class="font-semibold text-blue-800 mb-2">📐 Background Dimension Guidelines</h3>
                <div class="text-sm text-blue-700 space-y-1">
                    <p><strong>Landing Page:</strong> 1920x1080px (16:9 ratio) - Full screen background</p>
                    <p><strong>Main Room:</strong> 1920x1080px (16:9 ratio) - Full screen background</p>
                    <p><strong>Room Pages:</strong> 1280x960px (4:3 ratio) - Room container background</p>
                    <p class="text-xs mt-2 italic">💡 Images will be automatically scaled to fit these dimensions while maintaining aspect ratio.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Background Manager Functions
function openBackgroundManagerModal() {
    document.getElementById('backgroundManagerModal').style.display = 'flex';
    initializeBackgroundManager();
}

function closeBackgroundManagerModal() {
    document.getElementById('backgroundManagerModal').style.display = 'none';
}

function initializeBackgroundManager() {
    const roomSelect = document.getElementById('backgroundRoomSelect');
    
    // Load backgrounds for initial room
    loadBackgroundsForRoom(roomSelect.value);
    
    // Add event listener for room changes
    roomSelect.addEventListener('change', function() {
        loadBackgroundsForRoom(this.value);
    });
}

async function loadBackgroundsForRoom(roomType) {
    try {
        // Load current active background
        const activeResponse = await fetch(`api/get_background.php?room_type=${roomType}`);
        const activeData = await activeResponse.json();
        
        const currentInfo = document.getElementById('currentBackgroundInfo');
        const currentPreview = document.getElementById('currentBackgroundPreview');
        
        if (activeData.success && activeData.background) {
            const bg = activeData.background;
            currentInfo.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-lg">${bg.background_name}</p>
                        <p class="text-sm text-gray-600">${bg.image_filename}</p>
                        ${bg.webp_filename ? `<p class="text-sm text-gray-600">WebP: ${bg.webp_filename}</p>` : ''}
                        ${bg.created_at ? `<p class="text-xs text-gray-400">Created: ${new Date(bg.created_at).toLocaleDateString()}</p>` : ''}
                    </div>
                    ${bg.background_name === 'Original' ? '<span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full font-medium">🔒 PROTECTED</span>' : '<span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full font-medium">✅ ACTIVE</span>'}
                </div>
            `;
            
            // Create an actual image element for better scaling
            const imageUrl = `images/${bg.webp_filename || bg.image_filename}`;
            
            // Create image with proper loading and error handling
            const img = new Image();
            img.onload = function() {
                currentPreview.innerHTML = `<img src="${imageUrl}" alt="${bg.background_name}" class="max-w-full h-auto object-contain rounded-lg shadow-lg" style="max-height: 75vh;">`;
            };
            img.onerror = function() {
                currentPreview.innerHTML = `
                    <div class="text-red-400 text-center">
                        <svg class="w-16 h-16 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                        </svg>
                        <p>Error loading background image</p>
                        <p class="text-xs">${bg.image_filename}</p>
                    </div>
                `;
            };
            img.src = imageUrl;
            currentPreview.style.backgroundImage = 'none';
        } else {
            currentInfo.innerHTML = '<p class="text-red-500">No active background found</p>';
            currentPreview.innerHTML = `
                <div class="text-gray-400 text-center">
                    <svg class="w-16 h-16 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                    </svg>
                    <p>No background found</p>
                </div>
            `;
            currentPreview.style.backgroundImage = 'none';
        }
        
        // Load all backgrounds for this room
        const allResponse = await fetch(`api/backgrounds.php?room_type=${roomType}`);
        const allData = await allResponse.json();
        
        const backgroundsList = document.getElementById('backgroundsList');
        
        if (allData.success && allData.backgrounds) {
            backgroundsList.innerHTML = '';
            
            allData.backgrounds.forEach(bg => {
                const bgItem = document.createElement('div');
                bgItem.className = `border rounded-lg p-3 ${bg.is_active ? 'border-blue-300 bg-blue-50' : 'border-gray-200'}`;
                
                const imageUrl = `images/${bg.webp_filename || bg.image_filename}`;
                
                bgItem.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <div class="w-16 h-12 bg-gray-200 rounded overflow-hidden flex-shrink-0" style="background-image: url('${imageUrl}'); background-size: cover; background-position: center;"></div>
                        <div class="flex-grow min-w-0">
                            <div class="flex items-center justify-between">
                                <h4 class="font-medium text-sm truncate">${bg.background_name}</h4>
                                <div class="flex items-center space-x-1">
                                    ${bg.background_name === 'Original' ? 
                                        '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">🔒 PROTECTED</span>' : 
                                        (bg.is_active ? 
                                            '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">ACTIVE</span>' : 
                                            '<span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">INACTIVE</span>'
                                        )
                                    }
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 truncate">${bg.image_filename}</p>
                            ${bg.created_at ? `<p class="text-xs text-gray-400">${new Date(bg.created_at).toLocaleDateString()}</p>` : ''}
                        </div>
                    </div>
                    <div class="mt-3 flex space-x-2">
                        ${!bg.is_active ? `<button onclick="applyBackground('${roomType}', ${bg.id})" class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white text-xs rounded font-medium">Apply</button>` : ''}
                        <button onclick="previewBackground('${imageUrl}', '${bg.background_name}')" class="px-3 py-1 bg-gray-500 hover:bg-gray-600 text-white text-xs rounded">Preview</button>
                        ${bg.background_name !== 'Original' ? `<button onclick="deleteBackground(${bg.id}, '${bg.background_name}')" class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white text-xs rounded">Delete</button>` : ''}
                    </div>
                `;
                
                backgroundsList.appendChild(bgItem);
            });
        } else {
            backgroundsList.innerHTML = '<p class="text-gray-500 text-sm">No backgrounds found for this room</p>';
        }
        
    } catch (error) {
        console.error('Error loading backgrounds:', error);
        document.getElementById('currentBackgroundInfo').innerHTML = '<p class="text-red-500">Error loading background info</p>';
        document.getElementById('backgroundsList').innerHTML = '<p class="text-red-500">Error loading backgrounds</p>';
    }
}

async function applyBackground(roomType, backgroundId) {
    if (!confirm('Are you sure you want to apply this background? It will become the active background for this room.')) {
        return;
    }
    
    try {
        const response = await fetch('api/backgrounds.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'apply',
                room_type: roomType,
                background_id: backgroundId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showBackgroundMessage('Background applied successfully!', 'success');
            loadBackgroundsForRoom(roomType);
        } else {
            showBackgroundMessage('Error applying background: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error applying background:', error);
        showBackgroundMessage('Error applying background', 'error');
    }
}

async function deleteBackground(backgroundId, backgroundName) {
    if (!confirm(`Are you sure you want to delete the background "${backgroundName}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch('api/backgrounds.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                background_id: backgroundId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showBackgroundMessage('Background deleted successfully!', 'success');
            const roomType = document.getElementById('backgroundRoomSelect').value;
            loadBackgroundsForRoom(roomType);
        } else {
            showBackgroundMessage('Error deleting background: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error deleting background:', error);
        showBackgroundMessage('Error deleting background', 'error');
    }
}

function previewBackground(imageUrl, backgroundName) {
    // Create a preview modal
    const previewModal = document.createElement('div');
    previewModal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
    previewModal.innerHTML = `
        <div class="bg-white rounded-lg p-4 max-w-4xl max-h-full overflow-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Preview: ${backgroundName}</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <img src="${imageUrl}" alt="${backgroundName}" class="max-w-full max-h-96 object-contain mx-auto">
        </div>
    `;
    
    document.body.appendChild(previewModal);
}

async function uploadBackground() {
    const roomType = document.getElementById('backgroundRoomSelect').value;
    const backgroundName = document.getElementById('backgroundName').value.trim();
    const fileInput = document.getElementById('backgroundFile');
    
    if (!backgroundName) {
        showBackgroundMessage('Please enter a background name', 'error');
        return;
    }
    
    if (!fileInput.files || fileInput.files.length === 0) {
        showBackgroundMessage('Please select an image file', 'error');
        return;
    }
    
    const file = fileInput.files[0];
    
    // Validate file size (10MB limit)
    if (file.size > 10 * 1024 * 1024) {
        showBackgroundMessage('File size must be less than 10MB', 'error');
        return;
    }
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        showBackgroundMessage('Please select a valid image file', 'error');
        return;
    }
    
    showBackgroundMessage('Uploading background...', 'info');
    
    // For now, show a placeholder message since we need to implement the actual upload
    showBackgroundMessage('Background upload feature coming soon! For now, manually add images to the images/ folder and use the API to register them.', 'info');
    
    // Clear the form
    document.getElementById('backgroundName').value = '';
    document.getElementById('backgroundFile').value = '';
}

function showBackgroundMessage(message, type) {
    // Create or update message display
    let messageDiv = document.getElementById('backgroundMessage');
    if (!messageDiv) {
        messageDiv = document.createElement('div');
        messageDiv.id = 'backgroundMessage';
        messageDiv.className = 'fixed top-4 right-4 px-4 py-2 rounded-lg text-white font-medium z-50';
        document.body.appendChild(messageDiv);
    }
    
    // Set message and styling based on type
    messageDiv.textContent = message;
    messageDiv.className = 'fixed top-4 right-4 px-4 py-2 rounded-lg text-white font-medium z-50';
    
    switch (type) {
        case 'success':
            messageDiv.classList.add('bg-green-500');
            break;
        case 'error':
            messageDiv.classList.add('bg-red-500');
            break;
        case 'info':
            messageDiv.classList.add('bg-blue-500');
            break;
        default:
            messageDiv.classList.add('bg-gray-500');
    }
    
    // Auto-hide after 3 seconds
    setTimeout(() => {
        if (messageDiv) {
            messageDiv.remove();
        }
    }, 3000);
}

// Custom notification functions
function showNotification(title, message, type = 'info') {
    const modal = document.getElementById('customNotificationModal');
    const icon = document.getElementById('notificationIcon');
    const titleEl = document.getElementById('notificationTitle');
    const messageEl = document.getElementById('notificationMessage');
    
    // Set content
    titleEl.textContent = title;
    messageEl.textContent = message;
    
    // Set icon and styling based on type
    switch (type) {
        case 'success':
            icon.textContent = '✅';
            titleEl.className = 'text-lg font-semibold text-green-800';
            break;
        case 'error':
            icon.textContent = '❌';
            titleEl.className = 'text-lg font-semibold text-red-800';
            break;
        case 'warning':
            icon.textContent = '⚠️';
            titleEl.className = 'text-lg font-semibold text-yellow-800';
            break;
        case 'info':
        default:
            icon.textContent = 'ℹ️';
            titleEl.className = 'text-lg font-semibold text-blue-800';
            break;
    }
    
    // Show modal
    modal.style.display = 'flex';
}

function closeCustomNotification() {
    document.getElementById('customNotificationModal').style.display = 'none';
}

// Custom confirmation dialog
function showConfirmation(title, message, onConfirm) {
    const modal = document.getElementById('customNotificationModal');
    const icon = document.getElementById('notificationIcon');
    const titleEl = document.getElementById('notificationTitle');
    const messageEl = document.getElementById('notificationMessage');
    
    // Set content
    titleEl.textContent = title;
    messageEl.textContent = message;
    icon.textContent = '❓';
    titleEl.className = 'text-lg font-semibold text-gray-800';
    
    // Update buttons for confirmation
    const buttonContainer = modal.querySelector('.flex.justify-end');
    buttonContainer.innerHTML = `
        <button onclick="closeCustomNotification()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors mr-2">
            Cancel
        </button>
        <button onclick="confirmAction()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
            Confirm
        </button>
    `;
    
    // Store the callback
    window.pendingConfirmAction = onConfirm;
    
    // Show modal
    modal.style.display = 'flex';
}

function confirmAction() {
    if (window.pendingConfirmAction) {
        window.pendingConfirmAction();
        window.pendingConfirmAction = null;
    }
    closeCustomNotification();
    
    // Reset buttons back to normal
    const buttonContainer = document.querySelector('#customNotificationModal .flex.justify-end');
    buttonContainer.innerHTML = `
        <button onclick="closeCustomNotification()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
            OK
        </button>
    `;
}

// Email Configuration Functions
async function openEmailConfigModal() {
    document.getElementById('emailConfigModal').style.display = 'flex';
    
    // Load current configuration
    try {
        const response = await fetch('/api/get_email_config.php');
        const data = await response.json();
        
        if (data.success) {
            populateEmailForm(data.config);
        } else {
            // Set recommended defaults for IONOS
            populateEmailForm({
                fromEmail: 'orders@whimsicalfrog.us',
                fromName: 'WhimsicalFrog',
                adminEmail: 'admin@whimsicalfrog.us',
                bccEmail: '',
                smtpEnabled: true,
                smtpHost: 'smtp.ionos.com',
                smtpPort: '587',
                smtpUsername: 'orders@whimsicalfrog.us',
                smtpPassword: '',
                smtpEncryption: 'tls'
            });
        }
    } catch (error) {
        console.error('Error loading email config:', error);
        showNotification('Error', 'Failed to load current email configuration', 'error');
    }
}

function closeEmailConfigModal() {
    document.getElementById('emailConfigModal').style.display = 'none';
}

function populateEmailForm(config) {
    document.getElementById('fromEmail').value = config.fromEmail || '';
    document.getElementById('fromName').value = config.fromName || '';
    document.getElementById('adminEmail').value = config.adminEmail || '';
    document.getElementById('bccEmail').value = config.bccEmail || '';
    
    const smtpEnabled = document.getElementById('smtpEnabled');
    smtpEnabled.checked = config.smtpEnabled || false;
    toggleSmtpSettings();
    
    if (config.smtpEnabled) {
        document.getElementById('smtpHost').value = config.smtpHost || '';
        document.getElementById('smtpPort').value = config.smtpPort || '587';
        document.getElementById('smtpUsername').value = config.smtpUsername || '';
        document.getElementById('smtpPassword').value = config.smtpPassword || '';
        document.getElementById('smtpEncryption').value = config.smtpEncryption || 'tls';
    }
}

function toggleSmtpSettings() {
    const smtpEnabled = document.getElementById('smtpEnabled').checked;
    const smtpSettings = document.getElementById('smtpSettings');
    smtpSettings.style.display = smtpEnabled ? 'grid' : 'none';
}

async function sendTestEmail() {
    const testEmail = document.getElementById('testEmailAddress').value;
    if (!testEmail) {
        showNotification('Error', 'Please enter a test email address', 'error');
        return;
    }
    
    // Collect current form data
    const formData = new FormData(document.getElementById('emailConfigForm'));
    formData.append('testEmail', testEmail);
    formData.append('action', 'test');
    
    try {
        const response = await fetch('/api/save_email_config.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Success', 'Test email sent successfully!', 'success');
        } else {
            showNotification('Error', result.error || 'Failed to send test email', 'error');
        }
    } catch (error) {
        console.error('Error sending test email:', error);
        showNotification('Error', 'Failed to send test email', 'error');
    }
}

// Handle email config form submission
document.addEventListener('DOMContentLoaded', function() {
    const emailConfigForm = document.getElementById('emailConfigForm');
    if (emailConfigForm) {
        emailConfigForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'save');
            
            try {
                const response = await fetch('/api/save_email_config.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    showNotification('Success', 'Email configuration saved successfully!', 'success');
                    closeEmailConfigModal();
                } else {
                    showNotification('Error', result.error || 'Failed to save configuration', 'error');
                }
            } catch (error) {
                console.error('Error saving email config:', error);
                showNotification('Error', 'Failed to save email configuration', 'error');
            }
        });
    }
    
    // Add SMTP toggle functionality
    const smtpEnabledCheckbox = document.getElementById('smtpEnabled');
    if (smtpEnabledCheckbox) {
        smtpEnabledCheckbox.addEventListener('change', toggleSmtpSettings);
    }
    
    // Email edit form submission
    const emailEditForm = document.getElementById('emailEditForm');
    if (emailEditForm) {
        emailEditForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            
            submitButton.textContent = 'Sending...';
            submitButton.disabled = true;
            
            fetch('api/resend_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Success', 'Email sent successfully!', 'success');
                    closeEmailEditModal();
                    loadEmailHistory(currentEmailHistoryPage); // Refresh the list
                } else {
                    showNotification('Error', data.error || 'Failed to send email', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error', 'Failed to send email', 'error');
            })
            .finally(() => {
                submitButton.textContent = originalText;
                submitButton.disabled = false;
            });
        });
    }
});

// Email History Functions
let currentEmailHistoryPage = 1;
const emailHistoryPageSize = 20;

function openEmailHistoryModal() {
    document.getElementById('emailHistoryModal').style.display = 'flex';
    loadEmailHistory();
}

function closeEmailHistoryModal() {
    document.getElementById('emailHistoryModal').style.display = 'none';
}

function loadEmailHistory(page = 1) {
    currentEmailHistoryPage = page;
    
    const dateFilter = document.getElementById('emailHistoryDateFilter').value;
    const typeFilter = document.getElementById('emailHistoryTypeFilter').value;
    const statusFilter = document.getElementById('emailHistoryStatusFilter').value;
    
    const params = new URLSearchParams({
        page: page,
        limit: emailHistoryPageSize,
        date_filter: dateFilter,
        type_filter: typeFilter,
        status_filter: statusFilter
    });
    
    const tableBody = document.getElementById('emailHistoryTableBody');
    tableBody.innerHTML = `
        <tr>
            <td colspan="6" class="border border-gray-300 px-4 py-8 text-center text-gray-500">
                <div class="flex flex-col items-center">
                    <svg class="w-8 h-8 mb-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Loading email history...
                </div>
            </td>
        </tr>
    `;
    
    fetch('api/get_email_history.php?' + params)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayEmailHistory(data.emails, data.pagination);
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="border border-gray-300 px-4 py-8 text-center text-red-500">
                            <div class="flex flex-col items-center">
                                <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                ${data.error || 'Failed to load email history'}
                            </div>
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading email history:', error);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="border border-gray-300 px-4 py-8 text-center text-red-500">
                        <div class="flex flex-col items-center">
                            <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Error loading email history
                        </div>
                    </td>
                </tr>
            `;
        });
}

function displayEmailHistory(emails, pagination) {
    const tableBody = document.getElementById('emailHistoryTableBody');
    
    if (!emails || emails.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="border border-gray-300 px-4 py-8 text-center text-gray-500">
                    <div class="flex flex-col items-center">
                        <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        No emails found matching your criteria
                    </div>
                </td>
            </tr>
        `;
        document.getElementById('emailHistoryPagination').style.display = 'none';
        return;
    }
    
    tableBody.innerHTML = emails.map(email => {
        const statusBadge = email.status === 'sent' 
            ? '<span class="inline-flex px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Sent</span>'
            : '<span class="inline-flex px-2 py-1 text-xs font-semibold bg-red-100 text-red-800 rounded-full">Failed</span>';
        
        const typeDisplayMap = {
            'order_confirmation': 'Order Confirmation',
            'admin_notification': 'Admin Notification',
            'test_email': 'Test Email',
            'manual_resend': 'Manual Resend'
        };
        
        const typeDisplay = typeDisplayMap[email.email_type] || email.email_type;
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-2 text-sm">
                    ${new Date(email.sent_at).toLocaleString()}
                </td>
                <td class="border border-gray-300 px-4 py-2 text-sm">
                    ${escapeHtml(email.to_email)}
                </td>
                <td class="border border-gray-300 px-4 py-2 text-sm">
                    <div class="max-w-xs truncate" title="${escapeHtml(email.subject)}">
                        ${escapeHtml(email.subject)}
                    </div>
                </td>
                <td class="border border-gray-300 px-4 py-2 text-sm">
                    ${typeDisplay}
                </td>
                <td class="border border-gray-300 px-4 py-2 text-sm">
                    ${statusBadge}
                </td>
                <td class="border border-gray-300 px-4 py-2 text-sm">
                    <div class="flex space-x-2">
                        <button onclick="viewEmailDetails(${email.id})" class="px-2 py-1 bg-blue-500 text-white rounded text-xs hover:bg-blue-600" title="View Details">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                        <button onclick="editAndResendEmail(${email.id})" class="px-2 py-1 bg-green-500 text-white rounded text-xs hover:bg-green-600" title="Edit & Resend">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    // Update pagination
    if (pagination) {
        document.getElementById('emailHistoryStart').textContent = pagination.start;
        document.getElementById('emailHistoryEnd').textContent = pagination.end;
        document.getElementById('emailHistoryTotal').textContent = pagination.total;
        
        const prevBtn = document.getElementById('emailHistoryPrevBtn');
        const nextBtn = document.getElementById('emailHistoryNextBtn');
        
        prevBtn.disabled = !pagination.has_prev;
        nextBtn.disabled = !pagination.has_next;
        
        prevBtn.className = pagination.has_prev 
            ? 'px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm'
            : 'px-3 py-1 bg-gray-300 text-gray-700 rounded text-sm';
            
        nextBtn.className = pagination.has_next
            ? 'px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm'
            : 'px-3 py-1 bg-gray-300 text-gray-700 rounded text-sm';
        
        document.getElementById('emailHistoryPagination').style.display = 'flex';
    } else {
        document.getElementById('emailHistoryPagination').style.display = 'none';
    }
}

function loadEmailHistoryPage(direction) {
    if (direction === 'prev' && currentEmailHistoryPage > 1) {
        loadEmailHistory(currentEmailHistoryPage - 1);
    } else if (direction === 'next') {
        loadEmailHistory(currentEmailHistoryPage + 1);
    }
}

function fixSampleEmail() {
    const button = document.getElementById('fixSampleEmailBtn');
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = `
        <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Fixing...
    `;
    button.disabled = true;
    
    // First check session state for debugging
    fetch('api/debug_session.php')
    .then(response => response.json())
    .then(sessionData => {
        console.log('Session Debug Info:', sessionData);
        
        if (!sessionData.auth_status.is_authenticated) {
            showNotification('Error', 'Authentication required. Please refresh the page and try again.', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
            return;
        }
        
        // Proceed with fixing sample email using database manager
        const formData = new FormData();
        formData.append('action', 'fix_sample_email');
        formData.append('admin_token', 'whimsical_admin_2024'); // Fallback auth
        
        return fetch('api/db_manager.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
    })
    .then(response => {
        if (!response) return; // Authentication failed, already handled
        
        if (!response.ok) {
            // Try to get error details from response
            return response.json().then(errorData => {
                console.error('Database Manager Error Details:', errorData);
                throw new Error(`HTTP ${response.status}: ${errorData.error || response.statusText}`);
            }).catch(() => {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (!data) return; // Authentication failed, already handled
        
        if (data.success) {
            showNotification('Success', data.message, 'success');
            
            // Show debug info if available
            if (data.debug && data.debug.existing_emails) {
                console.log('Sample Email Fix Debug Info:', data.debug);
            }
            
            // Refresh email history if it's open
            const emailHistoryModal = document.getElementById('emailHistoryModal');
            if (emailHistoryModal && emailHistoryModal.style.display !== 'none') {
                loadEmailHistory(1);
            }
        } else {
            showNotification('Error', data.error || 'Failed to fix sample email', 'error');
            
            // Show debug info for troubleshooting
            if (data.debug) {
                console.error('Sample Email Fix Debug Info:', data.debug);
            }
        }
    })
    .catch(error => {
        console.error('Error fixing sample email:', error);
        showNotification('Error', 'Network error while fixing sample email: ' + error.message, 'error');
    })
    .finally(() => {
        // Restore button state
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function viewEmailDetails(emailId) {
    fetch('api/get_email_details.php?id=' + emailId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Email Details:\n\n' + 
                      'To: ' + data.email.to_email + '\n' +
                      'Subject: ' + data.email.subject + '\n' +
                      'Sent: ' + new Date(data.email.sent_at).toLocaleString() + '\n' +
                      'Status: ' + data.email.status + '\n\n' +
                      'Content:\n' + data.email.content);
            } else {
                showNotification('Error', data.error || 'Failed to load email details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error', 'Failed to load email details', 'error');
        });
}

function editAndResendEmail(emailId) {
    fetch('api/get_email_details.php?id=' + emailId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('originalEmailId').value = emailId;
                document.getElementById('editEmailTo').value = data.email.to_email;
                document.getElementById('editEmailSubject').value = data.email.subject;
                document.getElementById('editEmailContent').value = data.email.content;
                
                document.getElementById('emailEditModal').style.display = 'flex';
            } else {
                showNotification('Error', data.error || 'Failed to load email for editing', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error', 'Failed to load email for editing', 'error');
        });
}

function closeEmailEditModal() {
    document.getElementById('emailEditModal').style.display = 'none';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<!-- Email History Modal -->
<div id="emailHistoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Email History</h3>
                <button onclick="closeEmailHistoryModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            
            <!-- Filter Controls -->
            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <select id="emailHistoryDateFilter" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Type</label>
                        <select id="emailHistoryTypeFilter" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            <option value="all">All Types</option>
                            <option value="order_confirmation">Order Confirmations</option>
                            <option value="admin_notification">Admin Notifications</option>
                            <option value="test_email">Test Emails</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="emailHistoryStatusFilter" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            <option value="all">All Status</option>
                            <option value="sent">Sent Successfully</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="loadEmailHistory()" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-sm font-medium">
                            Filter
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Email History Table -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium">Date/Time</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium">To</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium">Subject</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium">Type</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium">Status</th>
                            <th class="border border-gray-300 px-4 py-2 text-left text-sm font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="emailHistoryTableBody">
                        <tr>
                            <td colspan="6" class="border border-gray-300 px-4 py-8 text-center text-gray-500">
                                <div class="flex flex-col items-center">
                                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Loading email history...
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div id="emailHistoryPagination" class="flex justify-between items-center mt-4" style="display: none;">
                <div class="text-sm text-gray-700">
                    Showing <span id="emailHistoryStart">0</span> to <span id="emailHistoryEnd">0</span> of <span id="emailHistoryTotal">0</span> results
                </div>
                <div class="flex space-x-2">
                    <button onclick="loadEmailHistoryPage('prev')" id="emailHistoryPrevBtn" class="px-3 py-1 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm" disabled>
                        Previous
                    </button>
                    <button onclick="loadEmailHistoryPage('next')" id="emailHistoryNextBtn" class="px-3 py-1 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm" disabled>
                        Next
                    </button>
                </div>
            </div>
            
            <!-- Close Button -->
            <div class="flex justify-end mt-6 pt-4 border-t">
                <button onclick="closeEmailHistoryModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Email Edit/Resend Modal -->
<div id="emailEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Edit & Resend Email</h3>
                <button onclick="closeEmailEditModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            
            <form id="emailEditForm" class="space-y-4">
                <input type="hidden" id="originalEmailId" name="originalEmailId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Email Address</label>
                        <input type="email" id="editEmailTo" name="emailTo" class="w-full p-2 border border-gray-300 rounded-md" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <input type="text" id="editEmailSubject" name="emailSubject" class="w-full p-2 border border-gray-300 rounded-md" required>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Content</label>
                    <textarea id="editEmailContent" name="emailContent" rows="15" class="w-full p-2 border border-gray-300 rounded-md font-mono text-sm" required></textarea>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex">
                        <svg class="w-5 h-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <h4 class="text-yellow-800 font-medium">Important Notice</h4>
                            <p class="text-yellow-700 text-sm">You are editing and resending an email. The original email record will remain unchanged, and a new email log entry will be created for this resend.</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeEmailEditModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 font-medium">
                        Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Email Configuration Modal -->
<div id="emailConfigModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Email Configuration</h3>
                <button onclick="closeEmailConfigModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            
            <form id="emailConfigForm" class="space-y-4">
                <!-- Basic Settings -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3">Basic Email Settings</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Email Address</label>
                            <input type="email" id="fromEmail" name="fromEmail" class="w-full p-2 border border-gray-300 rounded-md" placeholder="orders@whimsicalfrog.us" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                            <input type="text" id="fromName" name="fromName" class="w-full p-2 border border-gray-300 rounded-md" placeholder="WhimsicalFrog" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Admin Email</label>
                            <input type="email" id="adminEmail" name="adminEmail" class="w-full p-2 border border-gray-300 rounded-md" placeholder="admin@whimsicalfrog.us" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">BCC Email (Optional)</label>
                            <input type="email" id="bccEmail" name="bccEmail" class="w-full p-2 border border-gray-300 rounded-md" placeholder="backup@whimsicalfrog.us">
                        </div>
                    </div>
                </div>

                <!-- SMTP Settings -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="flex items-center mb-3">
                        <input type="checkbox" id="smtpEnabled" name="smtpEnabled" class="mr-2">
                        <label class="font-semibold text-gray-800">Enable SMTP (Recommended for IONOS)</label>
                    </div>
                    <div id="smtpSettings" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="display: none;">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                            <input type="text" id="smtpHost" name="smtpHost" class="w-full p-2 border border-gray-300 rounded-md" placeholder="smtp.ionos.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
                            <select id="smtpPort" name="smtpPort" class="w-full p-2 border border-gray-300 rounded-md">
                                <option value="587">587 (TLS - Recommended)</option>
                                <option value="465">465 (SSL)</option>
                                <option value="25">25 (Plain)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                            <input type="text" id="smtpUsername" name="smtpUsername" class="w-full p-2 border border-gray-300 rounded-md" placeholder="orders@whimsicalfrog.us">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                            <input type="password" id="smtpPassword" name="smtpPassword" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Your email password">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                            <select id="smtpEncryption" name="smtpEncryption" class="w-full p-2 border border-gray-300 rounded-md">
                                <option value="tls">TLS (Recommended)</option>
                                <option value="ssl">SSL</option>
                                <option value="">None</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Test Email -->
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3">Test Configuration</h4>
                    <div class="flex gap-2">
                        <input type="email" id="testEmailAddress" class="flex-1 p-2 border border-gray-300 rounded-md" placeholder="Enter test email address">
                        <button type="button" onclick="sendTestEmail()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-md font-medium">
                            Send Test Email
                        </button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeEmailConfigModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-md hover:bg-orange-600 font-medium">
                        Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Custom Notification Modal -->
<div id="customNotificationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div id="notificationIcon" class="text-2xl mr-3"></div>
                <h3 id="notificationTitle" class="text-lg font-semibold text-gray-800"></h3>
            </div>
            <p id="notificationMessage" class="text-gray-600 mb-6"></p>
            <div class="flex justify-end">
                <button onclick="closeCustomNotification()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Room-Category Visual Mapper Modal -->
<div id="roomCategoryMapperModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white shadow-xl w-full h-full overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h2 class="text-xl font-bold text-gray-800">🗺️ Room-Category Visual Mapper</h2>
            <button onclick="closeRoomCategoryMapperModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        
        <div class="p-4">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Room Cards -->
                <div class="lg:col-span-4">
                    <h3 class="text-lg font-semibold mb-4">Room-Category Mappings Overview</h3>
                    <div id="roomCategoryCards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <!-- Room cards will be loaded here -->
                    </div>
                </div>
            </div>
            
            <div class="bg-teal-50 border border-teal-200 rounded p-3 mt-6">
                <h3 class="font-semibold text-teal-800 mb-2">💡 Visual Mapper Guide</h3>
                <div class="text-sm text-teal-700 space-y-1">
                    <p><strong>Room Cards:</strong> Visual representation of each room and its assigned categories</p>
                    <p><strong>Primary Categories:</strong> Highlighted with crown icon (👑)</p>
                    <p><strong>Secondary Categories:</strong> Listed below primary categories</p>
                    <p><strong>Quick Actions:</strong> Click on room cards to manage assignments</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Area-Item Mapper Modal -->
<div id="areaItemMapperModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white shadow-xl w-full h-full overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h2 class="text-xl font-bold text-gray-800">🎯 Area-Item Mapper</h2>
            <button onclick="closeAreaItemMapperModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        
        <div class="p-4">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Panel: Room Selection & Controls -->
                <div class="lg:col-span-1">
                    <div class="mb-4">
                        <label for="areaMapperRoomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room:</label>
                        <select id="areaMapperRoomSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="room_tshirts">T-Shirts Room</option>
                            <option value="room_tumblers">Tumblers Room</option>
                            <option value="room_artwork">Artwork Room</option>
                            <option value="room_sublimation">Sublimation Room</option>
                            <option value="room_windowwraps">Window Wraps Room</option>
                        </select>
                    </div>
                    
                    <div class="bg-white border rounded-lg p-4 mb-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Area Mappings</h3>
                        <div id="areaMappingsList" class="space-y-2 max-h-96 overflow-y-auto">
                            <!-- Area mappings will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="bg-white border rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Add New Mapping</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Area:</label>
                                <select id="areaSelector" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Select area...</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mapping Type:</label>
                                <select id="mappingType" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Select type...</option>
                                    <option value="item">Specific Item</option>
                                    <option value="category">Category</option>
                                </select>
                            </div>
                            <div id="itemSelector" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Item:</label>
                                <select id="itemSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Select item...</option>
                                </select>
                            </div>
                            <div id="categorySelector" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Category:</label>
                                <select id="categorySelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Select category...</option>
                                </select>
                            </div>
                            <button onclick="addAreaMapping()" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold transition-colors">
                                Add Mapping
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Right Panel: Visual Room Display -->
                <div class="lg:col-span-2">
                    <h3 class="font-semibold text-gray-800 mb-3">Visual Area Mapper</h3>
                    <div class="area-mapper-container relative mb-4" id="areaMapperContainer">
                        <div class="area-mapper-wrapper relative w-full bg-gray-800 rounded-lg overflow-hidden" id="areaMapperDisplay" style="height: 70vh; background-size: contain; background-position: center; background-repeat: no-repeat;">
                            <!-- Clickable areas will be displayed here -->
                        </div>
                    </div>
                    
                    <div class="bg-indigo-50 border border-indigo-200 rounded p-3">
                        <h4 class="font-semibold text-indigo-800 mb-2">🎯 Area Mapper Instructions</h4>
                        <div class="text-sm text-indigo-700 space-y-1">
                            <p><strong>View Mappings:</strong> Colored areas show what's assigned to each clickable zone</p>
                            <p><strong>Swap Items:</strong> Click two mapped areas to swap their assignments</p>
                            <p><strong>Color Coding:</strong> 🟢 Items | 🔵 Categories | ⚪ Unmapped</p>
                            <p><strong>Hover:</strong> See details about what's mapped to each area</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> 

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
<!-- Admin Settings Grid Layout -->
<div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-6">
    
    <!-- Content Management -->
    <div class="bg-white shadow rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
            Content Management
        </h3>
        <div class="space-y-2">
            <a href="/?page=admin&section=categories" class="w-full brand-button text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                Categories
            </a>
            <button onclick="openRoomSettingsModal()" class="w-full bg-cyan-500 hover:bg-cyan-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-5l-2-2H5a2 2 0 00-2 2z"></path>
                </svg>
                Room Settings
            </button>
            <button onclick="openRoomCategoryManagerModal()" class="w-full btn-primary text-sm flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                Room-Category Links
            </button>
        </div>
    </div>

    <!-- Room & Visual Tools -->
    <div class="bg-white shadow rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
            </svg>
            Room & Visual Tools
        </h3>
        <div class="space-y-2">
            <button onclick="openRoomMapperModal()" class="w-full btn-primary text-sm flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                </svg>
                Room Mapper
            </button>
            <button onclick="openBackgroundManagerModal()" class="w-full bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Background Manager
            </button>
            <div class="flex gap-1">
                <button onclick="openRoomCategoryMapperModal()" class="flex-1 bg-teal-500 hover:bg-teal-600 text-white px-2 py-2 rounded text-xs font-medium">
                    Room Mapper
                </button>
                <button onclick="openAreaItemMapperModal()" class="flex-1 bg-indigo-500 hover:bg-indigo-600 text-white px-2 py-2 rounded text-xs font-medium">
                    Area Mapper
                </button>
            </div>
        </div>
    </div>

    <!-- Business & Design -->
    <div class="bg-white shadow rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
            </svg>
            Business & Design
        </h3>
        <div class="space-y-2">
            <button onclick="openAISettingsModal()" class="w-full bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                AI Settings
            </button>
            <button onclick="openGlobalCSSModal()" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zM21 5a2 2 0 00-2-2h-4a2 2 0 00-2 2v12a4 4 0 004 4h4a2 2 0 002-2V5z"></path>
                </svg>
                Global CSS Rules
            </button>
            <button onclick="openTemplateManagerModal()" class="w-full bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Template Manager
            </button>
            <button onclick="openAnalyticsModal()" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Analytics & Insights
            </button>
        </div>
    </div>

    <!-- Email & Communications -->
    <div class="bg-white shadow rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            Email & Communications
        </h3>
        <div class="space-y-2">
            <button onclick="openEmailConfigModal()" class="w-full bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                </svg>
                Email Configuration
            </button>
            <button onclick="openEmailHistoryModal()" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Email History
            </button>
            <button onclick="fixSampleEmail()" class="w-full btn-primary text-sm flex items-center" id="fixSampleEmailBtn">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                </svg>
                Fix Sample Email
            </button>
        </div>
    </div>

    <!-- System & Technical -->
    <div class="bg-white shadow rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
            </svg>
            System & Technical
        </h3>
        <div class="space-y-2">
            <button onclick="openSystemConfigModal()" class="w-full btn-primary text-sm flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
                System Reference
            </button>
            <button onclick="openFileExplorerModal()" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-5l-2-2H5a2 2 0 00-2 2z"></path>
                </svg>
                File Explorer
            </button>
            <button onclick="openDatabaseMaintenanceModal()" class="w-full bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                </svg>
                Database Maintenance
            </button>
        </div>
    </div>

    <!-- Payment Integration -->
    <div class="bg-white shadow rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            Payment Integration
        </h3>
        <div class="space-y-2">
            <div class="p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded text-sm">
                <strong>Coming Soon:</strong> Square payment integration for online credit card processing.
            </div>
            <button type="button" class="w-full bg-gray-400 text-white px-3 py-2 rounded text-sm font-medium cursor-not-allowed" disabled>
                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Configure Square (Coming Soon)
            </button>
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
                            <option value="room4">Artwork Room</option>
                            <option value="room2">T-Shirts Room</option>
                            <option value="room3">Tumblers Room</option>
                            <option value="room5">Sublimation Room</option>
                            <option value="room6">Window Wraps Room</option>
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



function openSystemConfigModal() {
    document.getElementById('systemConfigModal').style.display = 'block';
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
            <div class="text-center py-8">
                <div class="text-red-500 mb-3">⚠️</div>
                <p class="text-red-600">Failed to load system configuration</p>
                <p class="text-sm text-gray-500">${error.message}</p>
                <button onclick="loadSystemConfiguration()" class="mt-3 px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600">
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
        <div class="bg-green-50 border-l-4 border-green-400 p-4">
            <h4 class="font-semibold text-green-800 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                Current System Architecture (Live Data)
            </h4>
            <div class="space-y-3 text-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h5 class="font-semibold text-green-700 mb-2">🎯 Primary Identifier</h5>
                        <p class="text-green-600"><strong>${data.system_info.primary_identifier}</strong> - Human-readable codes</p>
                        <p class="text-xs text-green-600">Format: ${data.system_info.sku_format}</p>
                        <p class="text-xs text-green-600">Examples: ${data.sample_skus.slice(0, 3).join(', ')}</p>
                    </div>
                    <div>
                        <h5 class="font-semibold text-green-700 mb-2">🏷️ Main Entity</h5>
                        <p class="text-green-600"><strong>${data.system_info.main_entity}</strong></p>
                        <p class="text-xs text-green-600">All inventory and shop items</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SKU Categories -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
            <h4 class="font-semibold text-yellow-800 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                </svg>
                Active Categories & SKU Codes
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                ${Object.entries(data.category_codes).map(([category, code]) => {
                    const isActive = data.categories.includes(category);
                    return `
                        <div class="text-center p-2 ${isActive ? 'bg-yellow-100' : 'bg-gray-100'} rounded">
                            <div class="font-semibold ${isActive ? 'text-yellow-700' : 'text-gray-500'}">${code}</div>
                            <div class="text-xs ${isActive ? 'text-yellow-600' : 'text-gray-400'}">${category}</div>
                            ${isActive ? '<div class="text-xs text-green-600">✅ Active</div>' : '<div class="text-xs text-gray-400">Inactive</div>'}
                        </div>
                    `;
                }).join('')}
            </div>
        </div>



        <!-- ID Number Legend -->
        <div class="bg-orange-50 border-l-4 border-orange-400 p-4">
            <h4 class="font-semibold text-orange-800 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                ID Number Legend & Formats
            </h4>
            <div class="space-y-4">
                <!-- Customer IDs -->
                <div class="bg-white p-3 rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 mb-2 flex items-center text-sm">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        Customer IDs
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> [MonthLetter][Day][SequenceNumber]</p>
                        ${data.id_formats.recent_customers.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_customers.map(c => 
                                `<code class="bg-orange-100 px-1 py-0.5 rounded">${c.id}</code> (${c.username || 'No username'})`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 px-1 py-0.5 rounded">F14004</code></p>`
                        }
                        <div class="text-xs text-orange-500 mt-2">
                            <p>• <strong>F</strong> = June (A=Jan, B=Feb, C=Mar, D=Apr, E=May, F=Jun, G=Jul, H=Aug, I=Sep, J=Oct, K=Nov, L=Dec)</p>
                            <p>• <strong>14</strong> = 14th day of the month</p>
                            <p>• <strong>004</strong> = 4th customer registered</p>
                        </div>
                    </div>
                </div>

                <!-- Order IDs -->
                <div class="bg-white p-3 rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 mb-2 flex items-center text-sm">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2L3 7v11a1 1 0 001 1h12a1 1 0 001-1V7l-7-5zM8 15v-3h4v3H8z" clip-rule="evenodd"></path>
                        </svg>
                        Order IDs
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> [CustomerNum][MonthLetter][Day][ShippingCode][RandomNum]</p>
                        ${data.id_formats.recent_orders.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_orders.map(o => 
                                `<code class="bg-orange-100 px-1 py-0.5 rounded">${o}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 px-1 py-0.5 rounded">01F14P23</code></p>`
                        }
                        <div class="text-xs text-orange-500 mt-2">
                            <p>• <strong>01</strong> = Last 2 digits of customer number</p>
                            <p>• <strong>F14</strong> = June 14th (order date)</p>
                            <p>• <strong>P</strong> = Pickup (P=Pickup, L=Local, U=USPS, F=FedEx, X=UPS)</p>
                            <p>• <strong>23</strong> = Random 2-digit number for uniqueness</p>
                        </div>
                    </div>
                </div>

                <!-- Product/Inventory IDs (SKUs) -->
                <div class="bg-white p-3 rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 mb-2 flex items-center text-sm">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                        </svg>
                        Product & Inventory IDs (SKUs)
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> ${data.system_info.sku_format}</p>
                        ${data.sample_skus.length > 0 ? 
                            `<p><strong>Current Examples:</strong> ${data.sample_skus.slice(0, 5).map(sku => 
                                `<code class="bg-orange-100 px-1 py-0.5 rounded">${sku}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Examples:</strong> <code class="bg-orange-100 px-1 py-0.5 rounded">WF-TS-001</code>, <code class="bg-orange-100 px-1 py-0.5 rounded">WF-TU-002</code></p>`
                        }
                        <div class="text-xs text-orange-500 mt-2">
                            ${Object.entries(data.category_codes).map(([category, code]) => 
                                `<p>• <strong>${code}</strong> = ${category}</p>`
                            ).join('')}
                        </div>
                    </div>
                </div>

                <!-- Order Item IDs -->
                <div class="bg-white p-3 rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 mb-2 flex items-center text-sm">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path>
                        </svg>
                        Order Item IDs
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> OI[SequentialNumber]</p>
                        ${data.id_formats.recent_order_items.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_order_items.map(oi => 
                                `<code class="bg-orange-100 px-1 py-0.5 rounded">${oi}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 px-1 py-0.5 rounded">OI001</code></p>`
                        }
                        <div class="text-xs text-orange-500 mt-2">
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

function openDatabaseMaintenanceModal() {
    document.getElementById('databaseMaintenanceModal').style.display = 'block';
    loadDatabaseInformation();
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
            <div class="text-center py-8">
                <div class="text-red-500 mb-3">⚠️</div>
                <p class="text-red-600">Failed to load database information</p>
                <p class="text-sm text-gray-500">${error.message}</p>
                <button onclick="loadDatabaseInformation()" class="mt-3 px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    Retry
                </button>
            </div>
        `;
    }
}

function generateDatabaseMaintenanceHTML(data) {
    return `
        <!-- Database Schema -->
        <div class="bg-purple-50 border-l-4 border-purple-400 p-4">
            <h4 class="font-semibold text-purple-800 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"></path>
                </svg>
                Database Tables & Structure (${data.total_active} Active + ${data.total_backup} Backup)
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                ${Object.entries(data.organized).map(([category, tables]) => {
                    const categoryLabels = {
                        'core_tables': '🏗️ Core Tables',
                        'user_management': '👥 User Management', 
                        'inventory_cost': '💰 Inventory Cost',
                        'product_categories': '🏷️ Product Categories',
                        'room_management': '🏠 Room Management',
                        'email_system': '📧 Email System',
                        'business_config': '⚙️ Business Config',
                        'social_media': '📱 Social Media'
                    };
                    
                    return `
                        <div class="bg-transparent rounded p-3 border border-purple-200">
                            <h5 class="font-semibold text-purple-700 mb-2 text-xs">${categoryLabels[category] || category}</h5>
                            <ul class="space-y-1">
                                ${Object.entries(tables).map(([table, exists]) => 
                                    `<li>
                                        <button onclick="viewTable('${table}')" 
                                                class="text-left w-full hover:bg-purple-100 rounded px-1 py-0.5 transition-colors">
                                            <code class="bg-transparent border border-purple-200 px-1 py-0.5 rounded text-xs">${table}</code> 
                                            ${exists ? '✅' : '❌'}
                                        </button>
                                    </li>`
                                ).join('')}
                            </ul>
                        </div>
                    `;
                }).join('')}
            </div>
            
            <!-- Backup Tables (Collapsible) -->
            <div class="mt-4">
                <button onclick="toggleDatabaseBackupTables()" class="text-xs text-purple-600 hover:text-purple-800 flex items-center">
                    <span id="databaseBackupToggleIcon">▶</span>
                    <span class="ml-1">Show Backup Tables (${data.total_backup})</span>
                </button>
                <div id="databaseBackupTablesContainer" class="hidden mt-2 bg-gray-100 rounded p-2">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                        ${data.backup_tables.map(table => 
                            `<button onclick="viewTable('${table}')" 
                                     class="text-left hover:bg-gray-200 rounded px-1 py-0.5 transition-colors">
                                <code class="bg-gray-200 px-1 py-0.5 rounded">${table}</code>
                            </button>`
                        ).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function closeDatabaseMaintenanceModal() {
    document.getElementById('databaseMaintenanceModal').style.display = 'none';
}

function toggleDatabaseBackupTables() {
    const container = document.getElementById('databaseBackupTablesContainer');
    const icon = document.getElementById('databaseBackupToggleIcon');
    
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
        // Show loading state
        const modal = document.getElementById('tableViewModal');
        const title = document.getElementById('tableViewTitle');
        const content = document.getElementById('tableViewContent');
        
        title.textContent = `Loading ${tableName}...`;
        content.innerHTML = '<div class="text-center py-4"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';
        modal.style.display = 'flex';
        
        // Fetch table data
        const response = await fetch('api/db_manager.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'query',
                sql: `SELECT * FROM \`${tableName}\` LIMIT 100`,
                admin_token: 'whimsical_admin_2024'
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            title.textContent = `Table: ${tableName} (${data.row_count} records shown, max 100)`;
            
            if (data.data.length === 0) {
                content.innerHTML = '<div class="text-center py-4 text-gray-500">Table is empty</div>';
                return;
            }
            
            // Create table HTML
            const columns = Object.keys(data.data[0]);
            let tableHtml = `
                <div class="overflow-x-auto max-h-96">
                    <table class="min-w-full bg-white border border-gray-200 text-xs">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                ${columns.map(col => `<th class="px-2 py-1 border-b text-left font-semibold text-gray-700">${col}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${data.data.map(row => `
                                <tr class="hover:bg-gray-50">
                                    ${columns.map(col => {
                                        let value = row[col];
                                        if (value === null) value = '<span class="text-gray-400">NULL</span>';
                                        else if (typeof value === 'string' && value.length > 50) value = value.substring(0, 50) + '...';
                                        return `<td class="px-2 py-1 border-b">${value}</td>`;
                                    }).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            
            content.innerHTML = tableHtml;
        } else {
            title.textContent = `Error loading ${tableName}`;
            content.innerHTML = `<div class="text-red-600 p-4">Error: ${data.error || 'Failed to load table data'}</div>`;
        }
        
    } catch (error) {
        console.error('Error viewing table:', error);
        document.getElementById('tableViewTitle').textContent = `Error loading ${tableName}`;
        document.getElementById('tableViewContent').innerHTML = `<div class="text-red-600 p-4">Error: ${error.message}</div>`;
    }
}

function closeTableViewModal() {
    document.getElementById('tableViewModal').style.display = 'none';
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
    // Show pretty confirmation dialog
    const tableCount = await getDatabaseTableCount();
    
    const confirmed = await showConfirmationModal({
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
    });
    
    if (!confirmed) {
        return;
    }
    
    // Show progress modal
    showBackupProgressModal('🔧 Database Compact & Repair', 'database-repair');
    
    const progressSteps = document.getElementById('backupProgressSteps');
    const progressTitle = document.getElementById('backupProgressTitle');
    const progressSubtitle = document.getElementById('backupProgressSubtitle');
    
    progressTitle.textContent = '🔧 Database Compact & Repair';
    progressSubtitle.textContent = 'Optimizing and repairing database tables...';
    
    try {
        // Step 1: Create backup first
        progressSteps.innerHTML = `
            <div class="flex items-center space-x-3 mb-4">
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
        
        // Create database backup first
        const backupResponse = await fetch('/api/backup_database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                destination: 'cloud' // Always backup to server for safety
            })
        });
        
        const backupResult = await backupResponse.json();
        if (!backupResult.success) {
            throw new Error('Failed to create safety backup: ' + (backupResult.error || 'Unknown error'));
        }
        
        // Step 2: Compact and repair
        progressSteps.innerHTML = `
            <div class="flex items-center space-x-3 mb-4">
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
            <div class="flex items-center space-x-3 mb-4">
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
        
        // Run compact and repair operations
        const repairResponse = await fetch('/api/compact_repair_database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                admin_token: 'whimsical_admin_2024'
            })
        });
        
        const repairResult = await repairResponse.json();
        if (!repairResult.success) {
            throw new Error('Database optimization failed: ' + (repairResult.error || 'Unknown error'));
        }
        
        // Step 3: Complete
        progressSteps.innerHTML = `
            <div class="flex items-center space-x-3 mb-4">
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
            <div class="flex items-center space-x-3 mb-4">
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
        
        // Show completion details
        showBackupCompletionDetails({
            success: true,
            filename: backupResult.filename,
            filepath: backupResult.filepath,
            size: backupResult.size,
            timestamp: backupResult.timestamp,
            destinations: ['Server'],
            tables_optimized: repairResult.tables_processed || 0,
            operation_type: 'Database Compact & Repair'
        });
        
        // Refresh database information
        setTimeout(() => {
            loadDatabaseInformation();
        }, 2000);
        
    } catch (error) {
        console.error('Database compact/repair error:', error);
        
        // Show error state
        progressSteps.innerHTML = `
            <div class="flex items-center space-x-3 mb-4">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-red-900">Database optimization failed</p>
                    <p class="text-xs text-red-600">${error.message}</p>
                </div>
            </div>
        `;
        
        // Add retry button
        const retryButton = document.createElement('button');
        retryButton.className = 'mt-4 px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-colors';
        retryButton.textContent = 'Retry Operation';
        retryButton.onclick = () => {
            closeBackupProgressModal();
            compactRepairDatabase();
        };
        progressSteps.appendChild(retryButton);
    }
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
                            <option value="room4">Artwork Room</option>
                            <option value="room2">T-Shirts Room</option>
                            <option value="room3">Tumblers Room</option>
                            <option value="room5">Sublimation Room</option>
                            <option value="room6">Window Wraps Room</option>
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

<!-- AI Settings Modal -->
<div id="aiSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-2 sm:p-4" style="display: none;">
    <div class="bg-white shadow-xl rounded-lg w-full max-w-4xl h-full max-h-[95vh] flex flex-col">
        <!-- Fixed Header -->
        <div class="flex justify-between items-center p-4 border-b bg-white rounded-t-lg flex-shrink-0">
            <h2 class="text-xl font-bold text-gray-800">🤖 AI Settings</h2>
            <button onclick="closeAISettingsModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        
        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6">
            <div id="aiSettingsContent" class="space-y-4">
                <div class="text-center text-gray-500 py-8">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <p>Loading AI settings...</p>
                </div>
            </div>
        </div>
        
        <!-- Fixed Footer with Guide -->
        <div class="bg-purple-50 border-t border-purple-200 p-3 sm:p-4 rounded-b-lg flex-shrink-0">
            <h3 class="font-semibold text-purple-800 mb-2 text-sm sm:text-base">🤖 AI Settings Guide</h3>
            <div class="text-xs sm:text-sm text-purple-700 space-y-1">
                <p><strong>Temperature:</strong> 0.1-0.5 = consistent, 0.6-1.0 = creative</p>
                <p><strong>Conservative Mode:</strong> Eliminates randomness</p>
                <p><strong>Multipliers:</strong> Adjust all AI suggestions</p>
                <p><strong>Weights:</strong> Control pricing strategy influence</p>
            </div>
        </div>
    </div>
</div> 

<script>
// AI Settings Modal Functions
let aiSettingsData = {};

function openAISettingsModal() {
    document.getElementById('aiSettingsModal').style.display = 'flex';
    loadAISettings();
}

function closeAISettingsModal() {
    document.getElementById('aiSettingsModal').style.display = 'none';
}

async function loadAISettings() {
    try {
        const response = await fetch('/api/business_settings.php?action=get_by_category&category=ai');
        const data = await response.json();
        
        if (data.success) {
            displayAISettings(data.settings);
        } else {
            showAISettingsError('Failed to load AI settings: ' + data.error);
        }
    } catch (error) {
        showAISettingsError('Error loading AI settings: ' + error.message);
    }
}

function displayAISettings(settings) {
    const contentContainer = document.getElementById('aiSettingsContent');
    
    if (settings.length === 0) {
        contentContainer.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <p>No AI settings found</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">AI Configuration Settings</h3>
            <div class="space-y-3">
    `;
    
    settings.forEach(setting => {
        html += createAISettingField(setting);
    });
    
    html += `
            </div>
            <div class="pt-4 border-t mt-6 text-center">
                <button onclick="saveAISettings()" class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg font-semibold transition-colors w-full sm:w-auto">
                    💾 Save AI Settings
                </button>
            </div>
        </div>
    `;
    
    contentContainer.innerHTML = html;
}

function createAISettingField(setting) {
    const description = setting.description ? `<p class="text-xs text-gray-500 mt-1">${setting.description}</p>` : '';
    
    let inputField = '';
    
    switch (setting.setting_type) {
        case 'boolean':
            const isChecked = ['true', '1'].includes(setting.setting_value.toLowerCase()) ? 'checked' : '';
            inputField = `
                <label class="flex items-center justify-between">
                    <span class="text-sm text-gray-700 flex-1">${setting.display_name}</span>
                    <input type="checkbox" id="setting_${setting.setting_key}" ${isChecked} 
                           class="ml-2 h-4 w-4 text-purple-600 border-gray-300 rounded">
                </label>
            `;
            break;
            
        case 'number':
            // Special handling for temperature and weight fields
            let step = '0.01';
            let min = '0';
            let max = '1';
            
            if (setting.setting_key.includes('temperature')) {
                min = '0.1';
                max = '1.0';
            } else if (setting.setting_key.includes('multiplier')) {
                min = '0.1';
                max = '5.0';
            }
            
            inputField = `
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <label class="text-sm font-medium text-gray-700">${setting.display_name}</label>
                        <input type="number" id="setting_${setting.setting_key}" value="${setting.setting_value}" 
                               min="${min}" max="${max}" step="${step}"
                               class="w-16 px-2 py-1 border border-gray-300 rounded text-center text-sm"
                               oninput="document.getElementById('range_${setting.setting_key}').value = this.value">
                    </div>
                    <input type="range" id="range_${setting.setting_key}" value="${setting.setting_value}" 
                           min="${min}" max="${max}" step="${step}"
                           class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                           oninput="document.getElementById('setting_${setting.setting_key}').value = this.value">
                </div>
            `;
            break;
            
        default: // text
            inputField = `
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700">${setting.display_name}</label>
                    <input type="text" id="setting_${setting.setting_key}" value="${setting.setting_value}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
            `;
    }
    
    return `
        <div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4">
            ${inputField}
            ${description}
        </div>
    `;
}

async function saveAISettings() {
    const settings = document.querySelectorAll(`[id^="setting_"]`);
    const updates = [];
    
    for (const setting of settings) {
        const settingKey = setting.id.replace('setting_', '');
        let value = '';
        
        if (setting.type === 'checkbox') {
            value = setting.checked ? 'true' : 'false';
        } else {
            value = setting.value;
        }
        
        updates.push({
            setting_key: settingKey,
            setting_value: value
        });
    }
    
    try {
        const promises = updates.map(update => 
            fetch('/api/business_settings.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(update)
            })
        );
        
        const responses = await Promise.all(promises);
        const results = await Promise.all(responses.map(r => r.json()));
        
        const failed = results.filter(r => !r.success);
        if (failed.length > 0) {
            showAISettingsError('Some AI settings failed to save: ' + failed.map(f => f.error).join(', '));
        } else {
            showAISettingsSuccess('AI settings saved successfully!');
        }
        
    } catch (error) {
        showAISettingsError('Error saving AI settings: ' + error.message);
    }
}

function showAISettingsSuccess(message) {
    showCustomNotification('✅', 'Success', message);
}

function showAISettingsError(message) {
    showCustomNotification('❌', 'Error', message);
}

</script>

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
                
                // Build status badge
                const statusBadge = bg.background_name === 'Original' ? 
                    '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">🔒 PROTECTED</span>' : 
                    (bg.is_active ? 
                        '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">ACTIVE</span>' : 
                        '<span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">INACTIVE</span>'
                    );
                
                // Build action buttons
                const applyButton = !bg.is_active ? 
                    `<button onclick="applyBackground('${roomType}', ${bg.id})" class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white text-xs rounded font-medium">Apply</button>` : '';
                
                const deleteButton = bg.background_name !== 'Original' ? 
                    `<button onclick="deleteBackground(${bg.id}, '${bg.background_name}')" class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white text-xs rounded">Delete</button>` : '';
                
                const createdDate = bg.created_at ? 
                    `<p class="text-xs text-gray-400">${new Date(bg.created_at).toLocaleDateString()}</p>` : '';
                
                bgItem.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <div class="w-16 h-12 bg-gray-200 rounded overflow-hidden flex-shrink-0" style="background-image: url('${imageUrl}'); background-size: cover; background-position: center;"></div>
                        <div class="flex-grow min-w-0">
                            <div class="flex items-center justify-between">
                                <h4 class="font-medium text-sm truncate">${bg.background_name}</h4>
                                <div class="flex items-center space-x-1">
                                    ${statusBadge}
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 truncate">${bg.image_filename}</p>
                            ${createdDate}
                        </div>
                    </div>
                    <div class="mt-3 flex space-x-2">
                        ${applyButton}
                        <button onclick="previewBackground('${imageUrl}', '${bg.background_name}')" class="px-3 py-1 bg-gray-500 hover:bg-gray-600 text-white text-xs rounded">Preview</button>
                        ${deleteButton}
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

<!-- Room Settings Modal -->
<div id="roomSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white shadow-xl w-full max-w-6xl h-full max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center p-4 border-b">
            <h2 class="text-xl font-bold text-gray-800">🏠 Room Settings</h2>
            <button onclick="closeRoomSettingsModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        
        <div class="p-4">
            <div class="bg-cyan-50 border border-cyan-200 rounded p-3 mb-6">
                <h3 class="font-semibold text-cyan-800 mb-2">🏠 Room Settings Guide</h3>
                <div class="text-sm text-cyan-700 space-y-1">
                    <p><strong>Dynamic Room Names:</strong> Configure room titles and descriptions that appear throughout the site</p>
                    <p><strong>Door Labels:</strong> Set the text that appears on door signs in the main room</p>
                    <p><strong>Display Order:</strong> Control the order rooms appear in navigation and dropdowns</p>
                    <p><strong>Room Numbers:</strong> Rooms 0-6 are core rooms and cannot be deleted (0=Landing, 1=Main, 2-6=Product Rooms)</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Room List -->
                <div>
                    <h3 class="font-semibold text-gray-800 mb-3">Current Rooms</h3>
                    <div id="roomSettingsList" class="space-y-3">
                        <!-- Room cards will be loaded here -->
                        <div class="text-center text-gray-500 py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-cyan-500 mx-auto mb-2"></div>
                            Loading rooms...
                        </div>
                    </div>
                </div>
                
                <!-- Edit Form -->
                <div>
                    <h3 class="font-semibold text-gray-800 mb-3">Edit Room Settings</h3>
                    <div id="roomEditForm" class="bg-gray-50 rounded-lg p-4">
                        <div class="text-center text-gray-500 py-8">
                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-5l-2-2H5a2 2 0 00-2 2z"></path>
                            </svg>
                            <p>Select a room to edit its settings</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Room Settings Modal Functions
let currentEditingRoom = null;

function openRoomSettingsModal() {
    document.getElementById('roomSettingsModal').style.display = 'flex';
    loadRoomSettings();
}

function closeRoomSettingsModal() {
    document.getElementById('roomSettingsModal').style.display = 'none';
    currentEditingRoom = null;
}

async function loadRoomSettings() {
    try {
        const response = await fetch('/api/room_settings.php?action=get_all');
        const data = await response.json();
        
        if (data.success) {
            displayRoomSettingsList(data.rooms);
        } else {
            showRoomSettingsError('Failed to load room settings: ' + data.message);
        }
    } catch (error) {
        showRoomSettingsError('Error loading room settings: ' + error.message);
    }
}

function displayRoomSettingsList(rooms) {
    const container = document.getElementById('roomSettingsList');
    
    if (rooms.length === 0) {
        container.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <p>No rooms found</p>
                <button onclick="initializeRoomSettings()" class="mt-2 bg-cyan-500 hover:bg-cyan-600 text-white px-4 py-2 rounded">
                    Initialize Room Settings
                </button>
            </div>
        `;
        return;
    }
    
    container.innerHTML = '';
    
    rooms.forEach(room => {
        const roomCard = document.createElement('div');
        roomCard.className = 'border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition-colors';
        roomCard.onclick = () => editRoomSettings(room);
        
        const roomTypeLabel = getRoomTypeLabel(room.room_number);
        const isCore = room.room_number >= 0 && room.room_number <= 6;
        
        roomCard.innerHTML = `
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-sm font-medium text-gray-600">Room ${room.room_number}</span>
                        ${isCore ? '<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Core</span>' : ''}
                    </div>
                    <h4 class="font-semibold text-gray-800">${room.room_name}</h4>
                    <p class="text-sm text-gray-600 mb-2">${room.door_label}</p>
                    <p class="text-xs text-gray-500">${room.description || 'No description'}</p>
                </div>
                <div class="text-right text-xs text-gray-500">
                    <div>Order: ${room.display_order}</div>
                    <div class="mt-1">
                        <span class="text-cyan-600 hover:text-cyan-800">Edit →</span>
                    </div>
                </div>
            </div>
        `;
        
        container.appendChild(roomCard);
    });
}

function getRoomTypeLabel(roomNumber) {
    const labels = {
        0: 'Landing Page',
        1: 'Main Room',
        2: 'T-Shirts',
        3: 'Tumblers', 
        4: 'Artwork',
        5: 'Sublimation',
        6: 'Window Wraps'
    };
    return labels[roomNumber] || `Room ${roomNumber}`;
}

function editRoomSettings(room) {
    currentEditingRoom = room;
    const formContainer = document.getElementById('roomEditForm');
    
    const isCore = room.room_number >= 0 && room.room_number <= 6;
    
    formContainer.innerHTML = `
        <form onsubmit="saveRoomSettings(event)" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Room Number ${isCore ? '<span class="text-red-500">*</span>' : ''}
                </label>
                <input type="number" id="editRoomNumber" value="${room.room_number}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg ${isCore ? 'bg-gray-100' : ''}"
                       ${isCore ? 'readonly' : ''}>
                ${isCore ? '<p class="text-xs text-gray-500 mt-1">Core rooms cannot change numbers</p>' : ''}
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Room Name <span class="text-red-500">*</span>
                </label>
                <input type="text" id="editRoomName" value="${room.room_name}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                <p class="text-xs text-gray-500 mt-1">This appears as the main title in the room</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Door Label <span class="text-red-500">*</span>
                </label>
                <input type="text" id="editDoorLabel" value="${room.door_label}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                <p class="text-xs text-gray-500 mt-1">This appears on door signs in the main room</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="editRoomDescription" rows="3" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg">${room.description || ''}</textarea>
                <p class="text-xs text-gray-500 mt-1">This appears as subtitle text in the room</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Display Order</label>
                <input type="number" id="editDisplayOrder" value="${room.display_order}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg" min="0">
                <p class="text-xs text-gray-500 mt-1">Lower numbers appear first in navigation</p>
            </div>
            
            <div class="flex gap-3 pt-4 border-t">
                <button type="submit" class="flex-1 bg-cyan-500 hover:bg-cyan-600 text-white py-2 px-4 rounded-lg font-medium">
                    Save Changes
                </button>
                <button type="button" onclick="cancelRoomEdit()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded-lg">
                    Cancel
                </button>
            </div>
        </form>
    `;
}

async function saveRoomSettings(event) {
    event.preventDefault();
    
    if (!currentEditingRoom) return;
    
    const formData = {
        action: 'update_room',
        room_number: parseInt(document.getElementById('editRoomNumber').value),
        room_name: document.getElementById('editRoomName').value.trim(),
        door_label: document.getElementById('editDoorLabel').value.trim(),
        description: document.getElementById('editRoomDescription').value.trim(),
        display_order: parseInt(document.getElementById('editDisplayOrder').value) || 0
    };
    
    if (!formData.room_name || !formData.door_label) {
        showRoomSettingsError('Room name and door label are required');
        return;
    }
    
    try {
        const response = await fetch('/api/room_settings.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showRoomSettingsSuccess('Room settings updated successfully');
            loadRoomSettings(); // Reload the list
            cancelRoomEdit(); // Clear the form
        } else {
            showRoomSettingsError('Failed to update room: ' + data.message);
        }
    } catch (error) {
        showRoomSettingsError('Error updating room: ' + error.message);
    }
}

function cancelRoomEdit() {
    currentEditingRoom = null;
    const formContainer = document.getElementById('roomEditForm');
    formContainer.innerHTML = `
        <div class="text-center text-gray-500 py-8">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-5l-2-2H5a2 2 0 00-2 2z"></path>
            </svg>
            <p>Select a room to edit its settings</p>
        </div>
    `;
}

async function initializeRoomSettings() {
    try {
        const response = await fetch('/api/init_room_settings_db.php');
        const data = await response.json();
        
        if (data.success) {
            showRoomSettingsSuccess('Room settings initialized successfully');
            loadRoomSettings();
        } else {
            showRoomSettingsError('Failed to initialize: ' + data.message);
        }
    } catch (error) {
        showRoomSettingsError('Error initializing: ' + error.message);
    }
}

function showRoomSettingsError(message) {
    // Create or update error notification
    let notification = document.getElementById('roomSettingsNotification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'roomSettingsNotification';
        notification.className = 'fixed top-4 right-4 z-50 max-w-sm';
        document.body.appendChild(notification);
    }
    
    notification.innerHTML = `
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded shadow-lg">
            <div class="flex justify-between items-start">
                <span class="text-sm">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-red-500 hover:text-red-700">&times;</button>
            </div>
        </div>
    `;
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function showRoomSettingsSuccess(message) {
    // Create or update success notification
    let notification = document.getElementById('roomSettingsNotification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'roomSettingsNotification';
        notification.className = 'fixed top-4 right-4 z-50 max-w-sm';
        document.body.appendChild(notification);
    }
    
    notification.innerHTML = `
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-lg">
            <div class="flex justify-between items-start">
                <span class="text-sm">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-green-500 hover:text-green-700">&times;</button>
            </div>
        </div>
    `;
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 3000);
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
                            <option value="">Loading rooms...</option>
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

<!-- System Configuration Modal -->
<div id="systemConfigModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">⚙️ System Reference</h3>
                <button onclick="closeSystemConfigModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="space-y-6" id="systemConfigContent">
                <!-- Loading state -->
                <div class="text-center py-8" id="systemConfigLoading">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-500 mx-auto mb-3"></div>
                    <p class="text-gray-600">Loading system configuration...</p>
                </div>
                
                <!-- Content will be loaded dynamically here -->
            </div>
        </div>
    </div>
</div>

<!-- Database Maintenance Modal -->
<div id="databaseMaintenanceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
    <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">🗄️ Database Maintenance</h3>
                <button onclick="closeDatabaseMaintenanceModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Database Action Buttons -->
            <div class="mb-4 p-3 bg-transparent rounded-lg">
                <div class="flex items-center justify-center space-x-4">
                    <button onclick="showDatabaseBackupModal()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors duration-200">
                        🗄️ Backup Website Database
                    </button>
                    <button onclick="compactRepairDatabase()" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors duration-200">
                        🔧 Compact & Repair Database
                    </button>
                </div>
            </div>
            
            <!-- Modal Content -->
            <div class="space-y-6" id="databaseMaintenanceContent">
                <!-- Loading state -->
                <div class="text-center py-8" id="databaseMaintenanceLoading">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-red-500 mx-auto mb-3"></div>
                    <p class="text-gray-600">Loading database information...</p>
                </div>
                
                <!-- Content will be loaded dynamically here -->
            </div>
        </div>
    </div>
</div>

<!-- Table View Modal -->
<div id="tableViewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
    <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-4">
                <h3 id="tableViewTitle" class="text-lg font-bold text-gray-900">🗄️ Table View</h3>
                <button onclick="closeTableViewModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div id="tableViewContent" class="space-y-4">
                <!-- Table content will be loaded dynamically here -->
            </div>
        </div>
    </div>
</div>

<!-- File Explorer Modal -->
<div id="fileExplorerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
    <div class="relative top-5 mx-auto p-5 border w-11/12 max-w-7xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">📁 File Explorer</h3>
                <button onclick="closeFileExplorerModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Full Path Display -->
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-center">
                    <span class="text-sm font-medium text-blue-800 mr-2">Current Path:</span>
                    <span id="fullPath" class="text-sm text-blue-700 font-mono break-all">/Users/jongraves/Documents/Websites/WhimsicalFrog</span>
                </div>
            </div>
            
            <!-- Toolbar -->
            <div class="flex items-center justify-between mb-4 p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center space-x-2">
                    <button onclick="navigateUp()" id="upButton" class="px-3 py-1 bg-gray-500 hover:bg-gray-600 text-white rounded text-sm" disabled>
                        ↑ Up
                    </button>
                    <button onclick="refreshDirectory()" class="px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm">
                        🔄 Refresh
                    </button>
                </div>
                <div class="flex items-center">
                    <button onclick="showBackupModal()" class="px-4 py-1 bg-green-500 hover:bg-green-600 text-white rounded text-sm font-medium">
                        💾 Backup Website Files
                    </button>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="showCreateFolderDialog()" class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white rounded text-sm">
                        📁+ New Folder
                    </button>
                    <button onclick="showCreateFileDialog()" class="px-3 py-1 bg-indigo-500 hover:bg-indigo-600 text-white rounded text-sm">
                        📄+ New File
                    </button>
                </div>
            </div>
            
            <!-- File List -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Left Panel: File Browser -->
                <div class="bg-white border rounded-lg">
                    <div class="p-3 border-b bg-gray-50">
                        <h4 class="font-semibold text-gray-800">Directory Contents</h4>
                    </div>
                    <div id="fileList" class="p-3 max-h-96 overflow-y-auto">
                        <!-- File list will be loaded here -->
                        <div class="text-center py-8" id="fileListLoading">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-500 mx-auto mb-2"></div>
                            <p class="text-gray-600 text-sm">Loading files...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Right Panel: File Editor -->
                <div class="bg-white border rounded-lg">
                    <div class="p-3 border-b bg-gray-50 flex items-center justify-between">
                        <h4 class="font-semibold text-gray-800">File Editor</h4>
                        <div id="editorActions" class="hidden space-x-2">
                            <button onclick="saveFile()" class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white rounded text-sm">
                                💾 Save
                            </button>
                            <button onclick="closeEditor()" class="px-3 py-1 bg-gray-500 hover:bg-gray-600 text-white rounded text-sm">
                                ✕ Close
                            </button>
                        </div>
                    </div>
                    <div id="fileEditor" class="p-3">
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p>Select a file to view or edit</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- File Info Panel -->
            <div id="fileInfoPanel" class="mt-4 p-3 bg-gray-50 rounded-lg hidden">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-700">Size:</span>
                        <span id="fileSize" class="text-gray-600">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Modified:</span>
                        <span id="fileModified" class="text-gray-600">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Permissions:</span>
                        <span id="filePermissions" class="text-gray-600">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Type:</span>
                        <span id="fileType" class="text-gray-600">-</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backup Website Modal -->
<div id="backupModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-green-600 rounded-lg flex items-center justify-center">
                            <span class="text-white text-lg">💾</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Create Website Backup</h3>
                    </div>
                    <button onclick="closeBackupModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Body -->
            <div class="px-6 py-4">
                <div class="mb-4">
                    <p class="text-gray-600 text-sm mb-4">Create a complete backup of your website files and database. Choose your backup destination(s):</p>
                    
                    <!-- Backup Options -->
                    <div class="space-y-3 mb-6">
                        <label class="flex items-start space-x-3 cursor-pointer p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <input type="checkbox" id="backupToComputer" class="mt-1 w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 focus:ring-2" checked onchange="updateBackupButton()">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <span class="text-lg">💻</span>
                                    <span class="font-medium text-gray-900">Download to Computer</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Backup file will be downloaded to your device</p>
                            </div>
                        </label>
                        
                        <label class="flex items-start space-x-3 cursor-pointer p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <input type="checkbox" id="backupToCloud" class="mt-1 w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 focus:ring-2" checked onchange="updateBackupButton()">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <span class="text-lg">☁️</span>
                                    <span class="font-medium text-gray-900">Keep on Server</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Backup stored on server (max 10 backups)</p>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Info Box -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <div class="flex items-start space-x-2">
                            <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="text-sm">
                                <p class="text-blue-800 font-medium">Backup Information</p>
                                <p class="text-blue-700 text-xs mt-1">Includes all website files, database, and configurations. Typical size: ~25MB</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Warning for no selection -->
                    <div id="backupWarning" class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 hidden">
                        <div class="flex items-start space-x-2">
                            <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="text-sm">
                                <p class="text-red-800 font-medium">No Destination Selected</p>
                                <p class="text-red-700 text-xs mt-1">Please select at least one backup destination</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <div class="flex items-center justify-end space-x-3">
                    <button onclick="closeBackupModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                        Cancel
                    </button>
                    <button id="createBackupButton" onclick="executeBackup()" class="px-4 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 border border-transparent rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="flex items-center space-x-2">
                            <span>💾</span>
                            <span>Create Backup</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backup Progress Modal -->
<div id="backupProgressModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center">
                        <span class="text-white text-lg">⚡</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Backup in Progress</h3>
                </div>
            </div>
            
            <!-- Progress Content -->
            <div id="backupProgressContent" class="px-6 py-6">
                <!-- Initial Progress State -->
                <div id="backupProgressState" class="text-center">
                    <div class="mb-4">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                    </div>
                    <h4 class="text-lg font-medium text-gray-900 mb-2">Creating Backup...</h4>
                    <p class="text-gray-600 text-sm mb-4">Please wait while we create your website backup</p>
                    
                    <!-- Progress Steps -->
                    <div class="space-y-2 text-left">
                        <div class="flex items-center space-x-3">
                            <div class="w-4 h-4 bg-blue-500 rounded-full animate-pulse"></div>
                            <span class="text-sm text-gray-700">Collecting website files...</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-4 h-4 bg-gray-300 rounded-full"></div>
                            <span class="text-sm text-gray-500">Compressing archive...</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-4 h-4 bg-gray-300 rounded-full"></div>
                            <span class="text-sm text-gray-500" id="destinationStep">Preparing destinations...</span>
                        </div>
                    </div>
                </div>
                
                <!-- Success State (hidden initially) -->
                <div id="backupSuccessState" class="text-center hidden">
                    <div class="mb-4">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>
                    <h4 class="text-lg font-medium text-green-800 mb-2">Backup Complete!</h4>
                    <div id="backupDetails" class="text-left bg-gray-50 rounded-lg p-4 mb-4">
                        <!-- Details will be populated here -->
                    </div>
                </div>
                
                <!-- Error State (hidden initially) -->
                <div id="backupErrorState" class="text-center hidden">
                    <div class="mb-4">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto">
                            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                    </div>
                    <h4 class="text-lg font-medium text-red-800 mb-2">Backup Failed</h4>
                    <p id="backupErrorMessage" class="text-red-600 text-sm mb-4"></p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <div class="flex items-center justify-end space-x-3">
                    <button id="backupProgressCloseBtn" onclick="closeBackupProgressModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors hidden">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Database Backup Modal -->
<div id="databaseBackupModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-purple-600 rounded-lg flex items-center justify-center">
                            <span class="text-white text-lg">🗄️</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Backup Website Database</h3>
                    </div>
                    <button onclick="closeDatabaseBackupModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Body -->
            <div class="px-6 py-4">
                <div class="mb-4">
                    <p class="text-gray-600 text-sm mb-4">Create a backup of your website database including all tables and data. Choose your backup destination(s):</p>
                    
                    <!-- Backup Options -->
                    <div class="space-y-3 mb-6">
                        <label class="flex items-start space-x-3 cursor-pointer p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <input type="checkbox" id="dbBackupToComputer" class="mt-1 w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 focus:ring-2" checked onchange="updateDatabaseBackupButton()">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <span class="text-lg">💻</span>
                                    <span class="font-medium text-gray-900">Download to Computer</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Database backup will be downloaded to your device</p>
                            </div>
                        </label>
                        
                        <label class="flex items-start space-x-3 cursor-pointer p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <input type="checkbox" id="dbBackupToCloud" class="mt-1 w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 focus:ring-2" checked onchange="updateDatabaseBackupButton()">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <span class="text-lg">☁️</span>
                                    <span class="font-medium text-gray-900">Keep on Server</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Database backup stored on server (max 10 backups)</p>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Info Box -->
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 mb-4">
                        <div class="flex items-start space-x-2">
                            <svg class="w-5 h-5 text-purple-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="text-sm">
                                <p class="text-purple-800 font-medium">Database Backup Information</p>
                                <p class="text-purple-700 text-xs mt-1">Includes all database tables, data, and structure. Typical size: ~1-5MB</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Warning for no selection -->
                    <div id="dbBackupWarning" class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 hidden">
                        <div class="flex items-start space-x-2">
                            <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="text-sm">
                                <p class="text-red-800 font-medium">No Destination Selected</p>
                                <p class="text-red-700 text-xs mt-1">Please select at least one backup destination</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <div class="flex items-center justify-end space-x-3">
                    <button onclick="closeDatabaseBackupModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                        Cancel
                    </button>
                    <button id="createDatabaseBackupButton" onclick="executeDatabaseBackup()" class="px-4 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 border border-transparent rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="flex items-center space-x-2">
                            <span>🗄️</span>
                            <span>Create Database Backup</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// File Explorer JavaScript
let currentDirectory = '';
let currentFile = null;
let fileExplorerData = {};

function openFileExplorerModal() {
    document.getElementById('fileExplorerModal').style.display = 'block';
    // Small delay to ensure DOM elements are rendered
    setTimeout(() => {
        loadDirectory('');
    }, 10);
}

function closeFileExplorerModal() {
    document.getElementById('fileExplorerModal').style.display = 'none';
    closeEditor();
}

async function loadDirectory(path = '') {
    try {
        const response = await fetch(`api/file_manager.php?action=list&path=${encodeURIComponent(path)}`);
        const result = await response.json();
        
        if (result.success) {
            currentDirectory = result.path;
            fileExplorerData = result;
            displayFileList(result.items);
            updatePathDisplay();
            updateUpButton();
        } else {
            showNotification('Error', result.error, 'error');
        }
    } catch (error) {
        console.error('Error loading directory:', error);
        showNotification('Error', 'Failed to load directory', 'error');
    }
}

function displayFileList(items) {
    const fileListDiv = document.getElementById('fileList');
    const loadingDiv = document.getElementById('fileListLoading');
    
    // Safety check to prevent null reference errors
    if (!fileListDiv) {
        console.error('File list element not found');
        return;
    }
    
    if (loadingDiv) {
        loadingDiv.style.display = 'none';
    }
    

    
    if (items.length === 0) {
        fileListDiv.innerHTML = '<p class="text-gray-500 text-center py-4">Directory is empty</p>';
        return;
    }
    
    let html = '<div class="space-y-1">';
    
    items.forEach(item => {
        const icon = item.type === 'directory' ? '📁' : getFileIcon(item.extension);
        const sizeText = item.type === 'file' ? item.size_formatted : '';
        const modifiedDate = new Date(item.modified * 1000).toLocaleDateString();
        
        html += `
            <div class="flex items-center justify-between p-2 hover:bg-gray-100 rounded cursor-pointer" 
                 onclick="${item.type === 'directory' ? `loadDirectory('${item.path}')` : `selectFile('${item.path}')`}">
                <div class="flex items-center flex-1">
                    <span class="mr-2">${icon}</span>
                    <div class="flex-1">
                        <div class="font-medium text-gray-800">${item.name}</div>
                        <div class="text-xs text-gray-500">${sizeText} ${modifiedDate}</div>
                    </div>
                </div>
                <div class="flex items-center space-x-1">
                    ${item.type === 'file' && item.viewable ? 
                        `<button onclick="event.stopPropagation(); viewFile('${item.path}')" 
                                class="px-2 py-1 text-xs bg-blue-500 hover:bg-blue-600 text-white rounded">View</button>` : ''}
                    ${item.type === 'file' && item.editable ? 
                        `<button onclick="event.stopPropagation(); editFile('${item.path}')" 
                                class="px-2 py-1 text-xs bg-green-500 hover:bg-green-600 text-white rounded">Edit</button>` : ''}
                    <button onclick="event.stopPropagation(); deleteItem('${item.path}', '${item.type}')" 
                            class="px-2 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded">Delete</button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    fileListDiv.innerHTML = html;
}

function getFileIcon(extension) {
    const icons = {
        'php': '🐘',
        'js': '📜',
        'css': '🎨',
        'html': '🌐',
        'json': '📋',
        'txt': '📄',
        'md': '📝',
        'png': '🖼️',
        'jpg': '🖼️',
        'jpeg': '🖼️',
        'webp': '🖼️',
        'svg': '🖼️',
        'log': '📊',
        'sh': '⚙️'
    };
    return icons[extension] || '📄';
}

function updatePathDisplay() {
    const fullPathElement = document.getElementById('fullPath');
    if (fullPathElement) {
        const basePath = '/Users/jongraves/Documents/Websites/WhimsicalFrog';
        const fullPath = currentDirectory === '' ? basePath : basePath + '/' + currentDirectory;
        fullPathElement.textContent = fullPath;
    }
}

function updateUpButton() {
    const upButton = document.getElementById('upButton');
    if (upButton) {
        upButton.disabled = currentDirectory === '';
    }
}

function navigateUp() {
    if (currentDirectory !== '' && fileExplorerData.parent !== null) {
        loadDirectory(fileExplorerData.parent === '.' ? '' : fileExplorerData.parent);
    }
}

function refreshDirectory() {
    loadDirectory(currentDirectory);
}

function selectFile(path) {
    // This is called when clicking on a file (not edit/view buttons)
    // For now, just show file info
    showFileInfo(path);
}

 async function viewFile(path) {
     try {
         const response = await fetch(`api/file_manager.php?action=read&path=${encodeURIComponent(path)}`);
        const result = await response.json();
        
        if (result.success) {
            currentFile = {
                path: result.path,
                content: result.content,
                editable: result.editable,
                readonly: true
            };
            displayFileInEditor(result);
        } else {
            showNotification('Error', result.error, 'error');
        }
    } catch (error) {
        console.error('Error reading file:', error);
        showNotification('Error', 'Failed to read file', 'error');
    }
}

 async function editFile(path) {
     try {
         const response = await fetch(`api/file_manager.php?action=read&path=${encodeURIComponent(path)}`);
        const result = await response.json();
        
        if (result.success) {
            currentFile = {
                path: result.path,
                content: result.content,
                editable: result.editable,
                readonly: false
            };
            displayFileInEditor(result);
        } else {
            showNotification('Error', result.error, 'error');
        }
    } catch (error) {
        console.error('Error reading file:', error);
        showNotification('Error', 'Failed to read file', 'error');
    }
}

function displayFileInEditor(fileData) {
    const editorDiv = document.getElementById('fileEditor');
    const actionsDiv = document.getElementById('editorActions');
    
    const isReadonly = currentFile.readonly || !fileData.editable;
    
    editorDiv.innerHTML = `
        <div class="mb-2">
            <div class="flex items-center justify-between">
                <h5 class="font-medium text-gray-800">${fileData.filename}</h5>
                <span class="text-xs text-gray-500">${isReadonly ? 'Read-only' : 'Editable'}</span>
            </div>
        </div>
        <textarea id="fileContent" 
                  class="w-full h-80 p-3 border border-gray-300 rounded font-mono text-sm resize-none"
                  ${isReadonly ? 'readonly' : ''}
                  placeholder="File content will appear here...">${fileData.content}</textarea>
    `;
    
    if (!isReadonly) {
        actionsDiv.classList.remove('hidden');
    } else {
        actionsDiv.classList.add('hidden');
    }
    
    showFileInfo(fileData.path, fileData);
}

function showFileInfo(path, fileData = null) {
    const infoPanel = document.getElementById('fileInfoPanel');
    
    if (fileData && infoPanel) {
        const fileSizeEl = document.getElementById('fileSize');
        const fileModifiedEl = document.getElementById('fileModified');
        const filePermissionsEl = document.getElementById('filePermissions');
        const fileTypeEl = document.getElementById('fileType');
        
        if (fileSizeEl) fileSizeEl.textContent = formatFileSize(fileData.size);
        if (fileModifiedEl) fileModifiedEl.textContent = new Date(fileData.modified * 1000).toLocaleString();
        if (filePermissionsEl) filePermissionsEl.textContent = '-';
        if (fileTypeEl) fileTypeEl.textContent = fileData.filename.split('.').pop().toUpperCase();
        
        infoPanel.classList.remove('hidden');
    }
}

function formatFileSize(bytes) {
    if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    } else if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
    } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    } else {
        return bytes + ' bytes';
    }
}

async function saveFile() {
    if (!currentFile || currentFile.readonly) {
        showNotification('Error', 'No editable file selected', 'error');
        return;
    }
    
    const content = document.getElementById('fileContent').value;
    
         try {
         const response = await fetch('api/file_manager.php?action=write', {
             method: 'POST',
             headers: {
                 'Content-Type': 'application/json'
             },
             body: JSON.stringify({
                 path: currentFile.path,
                 content: content
             })
         });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Success', 'File saved successfully', 'success');
            currentFile.content = content;
        } else {
            showNotification('Error', result.error, 'error');
        }
    } catch (error) {
        console.error('Error saving file:', error);
        showNotification('Error', 'Failed to save file', 'error');
    }
}

function closeEditor() {
    const editorDiv = document.getElementById('fileEditor');
    const actionsDiv = document.getElementById('editorActions');
    const infoPanel = document.getElementById('fileInfoPanel');
    
    editorDiv.innerHTML = `
        <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p>Select a file to view or edit</p>
        </div>
    `;
    
    actionsDiv.classList.add('hidden');
    infoPanel.classList.add('hidden');
    currentFile = null;
}

async function deleteItem(path, type) {
    const itemType = type === 'directory' ? 'folder' : 'file';
    
    if (!confirm(`Are you sure you want to delete this ${itemType}?\n\n${path}`)) {
        return;
    }
    
    try {
                 const response = await fetch(`api/file_manager.php?action=delete&path=${encodeURIComponent(path)}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Success', `${itemType.charAt(0).toUpperCase() + itemType.slice(1)} deleted successfully`, 'success');
            refreshDirectory();
            
            // Close editor if deleted file was open
            if (currentFile && currentFile.path === path) {
                closeEditor();
            }
        } else {
            showNotification('Error', result.error, 'error');
        }
    } catch (error) {
        console.error('Error deleting item:', error);
        showNotification('Error', `Failed to delete ${itemType}`, 'error');
    }
}

function showCreateFolderDialog() {
    const folderName = prompt('Enter folder name:');
    if (folderName && folderName.trim()) {
        createFolder(folderName.trim());
    }
}

function showCreateFileDialog() {
    const fileName = prompt('Enter file name (with extension):');
    if (fileName && fileName.trim()) {
        createFile(fileName.trim());
    }
}

async function createFolder(name) {
    const path = currentDirectory ? `${currentDirectory}/${name}` : name;
    
    try {
                 const response = await fetch('api/file_manager.php?action=mkdir', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ path: path })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Success', 'Folder created successfully', 'success');
            refreshDirectory();
        } else {
            showNotification('Error', result.error, 'error');
        }
    } catch (error) {
        console.error('Error creating folder:', error);
        showNotification('Error', 'Failed to create folder', 'error');
    }
}

async function createFile(name) {
    const path = currentDirectory ? `${currentDirectory}/${name}` : name;
    
    try {
                 const response = await fetch('api/file_manager.php?action=write', {
             method: 'POST',
             headers: {
                 'Content-Type': 'application/json'
             },
             body: JSON.stringify({
                 path: path,
                 content: ''
             })
         });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Success', 'File created successfully', 'success');
            refreshDirectory();
        } else {
            showNotification('Error', result.error, 'error');
        }
    } catch (error) {
        console.error('Error creating file:', error);
        showNotification('Error', 'Failed to create file', 'error');
    }
}

// Backup Modal Functions
function showBackupModal() {
    document.getElementById('backupModal').classList.remove('hidden');
    updateBackupButton();
}

function closeBackupModal() {
    document.getElementById('backupModal').classList.add('hidden');
}

function updateBackupButton() {
    const computerCheckbox = document.getElementById('backupToComputer');
    const cloudCheckbox = document.getElementById('backupToCloud');
    const backupButton = document.getElementById('createBackupButton');
    const warningDiv = document.getElementById('backupWarning');
    
    const hasSelection = computerCheckbox.checked || cloudCheckbox.checked;
    
    if (hasSelection) {
        backupButton.disabled = false;
        backupButton.classList.remove('opacity-50', 'cursor-not-allowed');
        warningDiv.classList.add('hidden');
    } else {
        backupButton.disabled = true;
        backupButton.classList.add('opacity-50', 'cursor-not-allowed');
        warningDiv.classList.remove('hidden');
    }
}

async function executeBackup() {
    const computerCheckbox = document.getElementById('backupToComputer');
    const cloudCheckbox = document.getElementById('backupToCloud');
    
    if (!computerCheckbox.checked && !cloudCheckbox.checked) {
        showNotification('Error', 'Please select at least one backup destination', 'error');
        return;
    }
    
    const downloadToComputer = computerCheckbox.checked;
    const keepOnServer = cloudCheckbox.checked;
    
    // Close modal first
    closeBackupModal();
    
    // Show detailed progress modal
    showBackupProgressModal(downloadToComputer, keepOnServer);
    
    try {
        const response = await fetch('api/backup_website.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                download_to_computer: downloadToComputer,
                keep_on_server: keepOnServer
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success in progress modal
            showBackupComplete(result, downloadToComputer, keepOnServer);
            
            // Download the backup file if requested
            if (downloadToComputer && result.download_url) {
                // Small delay to let user see the success message
                setTimeout(() => {
                    const link = document.createElement('a');
                    link.href = result.download_url;
                    link.download = result.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }, 1000);
            }
        } else {
            showBackupError(result.error || 'Failed to create backup');
        }
    } catch (error) {
        console.error('Error creating backup:', error);
        showBackupError('Network error occurred while creating backup');
    }
}

// Legacy function for backward compatibility
async function backupWebsite() {
    showBackupModal();
}

// Backup Progress Modal Functions
function showBackupProgressModal(downloadToComputer, keepOnServer) {
    document.getElementById('backupProgressModal').classList.remove('hidden');
    
    // Reset modal to progress state
    document.getElementById('backupProgressState').classList.remove('hidden');
    document.getElementById('backupSuccessState').classList.add('hidden');
    document.getElementById('backupErrorState').classList.add('hidden');
    document.getElementById('backupProgressCloseBtn').classList.add('hidden');
    
    // Update destination step text
    const destinations = [];
    if (downloadToComputer) destinations.push('computer download');
    if (keepOnServer) destinations.push('server storage');
    document.getElementById('destinationStep').textContent = `Preparing ${destinations.join(' and ')}...`;
    
    // Simulate progress steps
    setTimeout(() => {
        // Step 1: Files collected
        const steps = document.querySelectorAll('#backupProgressState .space-y-2 > div');
        if (steps[0]) {
            steps[0].querySelector('.w-4').classList.remove('animate-pulse');
            steps[0].querySelector('.w-4').classList.add('bg-green-500');
            steps[0].querySelector('span').classList.remove('text-gray-700');
            steps[0].querySelector('span').classList.add('text-green-700');
        }
        
        // Step 2: Compressing
        if (steps[1]) {
            steps[1].querySelector('.w-4').classList.remove('bg-gray-300');
            steps[1].querySelector('.w-4').classList.add('bg-blue-500', 'animate-pulse');
            steps[1].querySelector('span').classList.remove('text-gray-500');
            steps[1].querySelector('span').classList.add('text-gray-700');
        }
    }, 500);
    
    setTimeout(() => {
        // Step 2: Compression complete
        const steps = document.querySelectorAll('#backupProgressState .space-y-2 > div');
        if (steps[1]) {
            steps[1].querySelector('.w-4').classList.remove('animate-pulse');
            steps[1].querySelector('.w-4').classList.add('bg-green-500');
            steps[1].querySelector('span').classList.add('text-green-700');
        }
        
        // Step 3: Destinations
        if (steps[2]) {
            steps[2].querySelector('.w-4').classList.remove('bg-gray-300');
            steps[2].querySelector('.w-4').classList.add('bg-blue-500', 'animate-pulse');
            steps[2].querySelector('span').classList.remove('text-gray-500');
            steps[2].querySelector('span').classList.add('text-gray-700');
        }
    }, 1000);
}

function showBackupComplete(result, downloadToComputer, keepOnServer) {
    // Hide progress state
    document.getElementById('backupProgressState').classList.add('hidden');
    document.getElementById('backupSuccessState').classList.remove('hidden');
    document.getElementById('backupProgressCloseBtn').classList.remove('hidden');
    
    // Format file size
    const sizeFormatted = result.size_formatted || formatFileSize(result.size || 0);
    
    // Build destinations list
    const destinations = [];
    if (downloadToComputer) destinations.push('💻 Downloaded to your computer');
    if (keepOnServer) destinations.push('☁️ Stored on server');
    
    // Format creation time
    const createdTime = new Date(result.created).toLocaleString();
    
    // Build details HTML
    let detailsHTML = `
        <div class="space-y-3">
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Filename:</span>
                <span class="text-gray-900 font-mono text-sm">${result.filename}</span>
            </div>
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Location:</span>
                <span class="text-gray-900 font-mono text-sm">${result.path}</span>
            </div>
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Size:</span>
                <span class="text-gray-900">${sizeFormatted}</span>
            </div>
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Created:</span>
                <span class="text-gray-900">${createdTime}</span>
            </div>
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Destinations:</span>
                <div class="text-right">
                    ${destinations.map(dest => `<div class="text-gray-900 text-sm">${dest}</div>`).join('')}
                </div>
            </div>
    `;
    
    // Add cleanup info if available
    if (result.cleanup && result.cleanup.deleted > 0) {
        detailsHTML += `
            <div class="flex items-start justify-between pt-2 border-t border-gray-200">
                <span class="font-medium text-orange-700">Cleanup:</span>
                <span class="text-orange-900">${result.cleanup.deleted} old backup${result.cleanup.deleted > 1 ? 's' : ''} deleted</span>
            </div>
        `;
    }
    
    detailsHTML += '</div>';
    
    document.getElementById('backupDetails').innerHTML = detailsHTML;
    
    // Auto-close after 10 seconds for server-only backups
    if (!downloadToComputer && keepOnServer) {
        setTimeout(() => {
            closeBackupProgressModal();
        }, 10000);
    }
}

function showBackupError(errorMessage) {
    // Hide progress state
    document.getElementById('backupProgressState').classList.add('hidden');
    document.getElementById('backupErrorState').classList.remove('hidden');
    document.getElementById('backupProgressCloseBtn').classList.remove('hidden');
    
    document.getElementById('backupErrorMessage').textContent = errorMessage;
}

function closeBackupProgressModal() {
    document.getElementById('backupProgressModal').classList.add('hidden');
}

// Helper function for file size formatting (client-side)
function formatFileSize(bytes) {
    if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    } else if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
    } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    } else {
        return bytes + ' bytes';
    }
}

// Database Backup Modal Functions
function showDatabaseBackupModal() {
    document.getElementById('databaseBackupModal').classList.remove('hidden');
    updateDatabaseBackupButton();
}

function closeDatabaseBackupModal() {
    document.getElementById('databaseBackupModal').classList.add('hidden');
}

function updateDatabaseBackupButton() {
    const computerCheckbox = document.getElementById('dbBackupToComputer');
    const cloudCheckbox = document.getElementById('dbBackupToCloud');
    const backupButton = document.getElementById('createDatabaseBackupButton');
    const warningDiv = document.getElementById('dbBackupWarning');
    
    const hasSelection = computerCheckbox.checked || cloudCheckbox.checked;
    
    if (hasSelection) {
        backupButton.disabled = false;
        backupButton.classList.remove('opacity-50', 'cursor-not-allowed');
        warningDiv.classList.add('hidden');
    } else {
        backupButton.disabled = true;
        backupButton.classList.add('opacity-50', 'cursor-not-allowed');
        warningDiv.classList.remove('hidden');
    }
}

async function executeDatabaseBackup() {
    const computerCheckbox = document.getElementById('dbBackupToComputer');
    const cloudCheckbox = document.getElementById('dbBackupToCloud');
    
    if (!computerCheckbox.checked && !cloudCheckbox.checked) {
        showNotification('Error', 'Please select at least one backup destination', 'error');
        return;
    }
    
    const downloadToComputer = computerCheckbox.checked;
    const keepOnServer = cloudCheckbox.checked;
    
    // Close modal first
    closeDatabaseBackupModal();
    
    // Show detailed progress modal (reuse the same one but with database-specific text)
    showDatabaseBackupProgressModal(downloadToComputer, keepOnServer);
    
    try {
        const response = await fetch('api/backup_database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                download_to_computer: downloadToComputer,
                keep_on_server: keepOnServer
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success in progress modal
            showDatabaseBackupComplete(result, downloadToComputer, keepOnServer);
            
            // Download the backup file if requested
            if (downloadToComputer && result.download_url) {
                // Small delay to let user see the success message
                setTimeout(() => {
                    const link = document.createElement('a');
                    link.href = result.download_url;
                    link.download = result.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }, 1000);
            }
        } else {
            showBackupError(result.error || 'Failed to create database backup');
        }
    } catch (error) {
        console.error('Error creating database backup:', error);
        showBackupError('Network error occurred while creating database backup');
    }
}

function showDatabaseBackupProgressModal(downloadToComputer, keepOnServer) {
    document.getElementById('backupProgressModal').classList.remove('hidden');
    
    // Update modal title for database backup
    document.querySelector('#backupProgressModal h3').textContent = 'Database Backup in Progress';
    document.querySelector('#backupProgressModal .w-10 span').textContent = '🗄️';
    
    // Reset modal to progress state
    document.getElementById('backupProgressState').classList.remove('hidden');
    document.getElementById('backupSuccessState').classList.add('hidden');
    document.getElementById('backupErrorState').classList.add('hidden');
    document.getElementById('backupProgressCloseBtn').classList.add('hidden');
    
    // Update progress text for database backup
    document.querySelector('#backupProgressState h4').textContent = 'Creating Database Backup...';
    document.querySelector('#backupProgressState p').textContent = 'Please wait while we create your database backup';
    
    // Update step text for database backup
    const steps = document.querySelectorAll('#backupProgressState .space-y-2 > div span');
    if (steps[0]) steps[0].textContent = 'Connecting to database...';
    if (steps[1]) steps[1].textContent = 'Exporting database structure and data...';
    
    // Update destination step text
    const destinations = [];
    if (downloadToComputer) destinations.push('computer download');
    if (keepOnServer) destinations.push('server storage');
    document.getElementById('destinationStep').textContent = `Preparing ${destinations.join(' and ')}...`;
    
    // Reset step styles
    const stepDots = document.querySelectorAll('#backupProgressState .space-y-2 > div .w-4');
    stepDots.forEach((dot, index) => {
        dot.className = 'w-4 h-4 bg-gray-300 rounded-full';
        if (index === 0) {
            dot.classList.add('bg-blue-500', 'animate-pulse');
            dot.classList.remove('bg-gray-300');
        }
    });
    
    // Simulate progress steps
    setTimeout(() => {
        // Step 1: Database connected
        if (stepDots[0]) {
            stepDots[0].classList.remove('animate-pulse');
            stepDots[0].classList.add('bg-green-500');
            steps[0].classList.add('text-green-700');
        }
        
        // Step 2: Exporting
        if (stepDots[1]) {
            stepDots[1].classList.remove('bg-gray-300');
            stepDots[1].classList.add('bg-blue-500', 'animate-pulse');
            steps[1].classList.remove('text-gray-500');
            steps[1].classList.add('text-gray-700');
        }
    }, 500);
    
    setTimeout(() => {
        // Step 2: Export complete
        if (stepDots[1]) {
            stepDots[1].classList.remove('animate-pulse');
            stepDots[1].classList.add('bg-green-500');
            steps[1].classList.add('text-green-700');
        }
        
        // Step 3: Destinations
        if (stepDots[2]) {
            stepDots[2].classList.remove('bg-gray-300');
            stepDots[2].classList.add('bg-blue-500', 'animate-pulse');
            document.getElementById('destinationStep').classList.remove('text-gray-500');
            document.getElementById('destinationStep').classList.add('text-gray-700');
        }
    }, 1000);
}

function showDatabaseBackupComplete(result, downloadToComputer, keepOnServer) {
    // Hide progress state
    document.getElementById('backupProgressState').classList.add('hidden');
    document.getElementById('backupSuccessState').classList.remove('hidden');
    document.getElementById('backupProgressCloseBtn').classList.remove('hidden');
    
    // Update success title for database backup
    document.querySelector('#backupSuccessState h4').textContent = 'Database Backup Complete!';
    
    // Format file size
    const sizeFormatted = result.size_formatted || formatFileSize(result.size || 0);
    
    // Build destinations list
    const destinations = [];
    if (downloadToComputer) destinations.push('💻 Downloaded to your computer');
    if (keepOnServer) destinations.push('☁️ Stored on server');
    
    // Format creation time
    const createdTime = new Date(result.created).toLocaleString();
    
    // Build details HTML
    let detailsHTML = `
        <div class="space-y-3">
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Filename:</span>
                <span class="text-gray-900 font-mono text-sm">${result.filename}</span>
            </div>
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Location:</span>
                <span class="text-gray-900 font-mono text-sm">${result.path}</span>
            </div>
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Size:</span>
                <span class="text-gray-900">${sizeFormatted}</span>
            </div>
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Created:</span>
                <span class="text-gray-900">${createdTime}</span>
            </div>
    `;
    
    // Add table count if available
    if (result.table_count) {
        detailsHTML += `
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Tables:</span>
                <span class="text-gray-900">${result.table_count} tables exported</span>
            </div>
        `;
    }
    
    detailsHTML += `
            <div class="flex items-start justify-between">
                <span class="font-medium text-gray-700">Destinations:</span>
                <div class="text-right">
                    ${destinations.map(dest => `<div class="text-gray-900 text-sm">${dest}</div>`).join('')}
                </div>
            </div>
    `;
    
    // Add cleanup info if available
    if (result.cleanup && result.cleanup.deleted > 0) {
        detailsHTML += `
            <div class="flex items-start justify-between pt-2 border-t border-gray-200">
                <span class="font-medium text-purple-700">Cleanup:</span>
                <span class="text-purple-900">${result.cleanup.deleted} old database backup${result.cleanup.deleted > 1 ? 's' : ''} deleted</span>
            </div>
        `;
    }
    
    detailsHTML += '</div>';
    
    document.getElementById('backupDetails').innerHTML = detailsHTML;
    
    // Auto-close after 10 seconds for server-only backups
    if (!downloadToComputer && keepOnServer) {
        setTimeout(() => {
            closeBackupProgressModal();
        }, 10000);
    }
}

// Global CSS Rules Management
function openGlobalCSSModal() {
    document.getElementById('globalCSSModal').style.display = 'flex';
    loadGlobalCSSRules();
}

function closeGlobalCSSModal() {
    document.getElementById('globalCSSModal').style.display = 'none';
}

async function loadGlobalCSSRules() {
    const loadingDiv = document.getElementById('globalCSSLoading');
    const contentDiv = document.getElementById('globalCSSContent');
    
    loadingDiv.style.display = 'block';
    contentDiv.style.display = 'none';
    
    try {
        const response = await fetch('/api/global_css_rules.php?action=list');
        const result = await response.json();
        
        if (result.success) {
            loadingDiv.style.display = 'none';
            contentDiv.style.display = 'block';
            renderGlobalCSSRules(result.grouped);
        } else {
            throw new Error(result.error || 'Failed to load CSS rules');
        }
    } catch (error) {
        console.error('Error loading CSS rules:', error);
        loadingDiv.innerHTML = `
            <div class="text-center py-8">
                <div class="text-red-500 mb-3">⚠️</div>
                <p class="text-red-600">Failed to load CSS rules</p>
                <p class="text-sm text-gray-500">${error.message}</p>
                <button onclick="loadGlobalCSSRules()" class="mt-3 px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    Retry
                </button>
            </div>
        `;
    }
}

function renderGlobalCSSRules(groupedRules) {
    const contentDiv = document.getElementById('globalCSSContent');
    
    // Create user-friendly sections
    const sections = {
        'brand': {
            title: '🎨 Brand Colors',
            description: 'Your main brand colors used throughout the website',
            gradient: 'from-blue-50 to-indigo-50 border-blue-200',
            titleColor: 'text-blue-800'
        },
        'buttons': {
            title: '🔘 Button Styles', 
            description: 'How all buttons look and feel',
            gradient: 'from-green-50 to-emerald-50 border-green-200',
            titleColor: 'text-green-800'
        },
        'typography': {
            title: '📝 Text & Fonts',
            description: 'Font styles and text appearance',
            gradient: 'from-purple-50 to-pink-50 border-purple-200',
            titleColor: 'text-purple-800'
        },
        'layout': {
            title: '📐 Layout & Spacing',
            description: 'Page layout, spacing, and structure',
            gradient: 'from-orange-50 to-yellow-50 border-orange-200',
            titleColor: 'text-orange-800'
        },
        'forms': {
            title: '📝 Form Elements',
            description: 'Input fields, dropdowns, and form styling',
            gradient: 'from-teal-50 to-cyan-50 border-teal-200',
            titleColor: 'text-teal-800'
        },
        'navigation': {
            title: '🧭 Navigation',
            description: 'Menu and navigation styling',
            gradient: 'from-gray-50 to-slate-50 border-gray-200',
            titleColor: 'text-gray-800'
        },
        'modals': {
            title: '🪟 Popups & Modals',
            description: 'Popup windows and overlay styling',
            gradient: 'from-rose-50 to-pink-50 border-rose-200',
            titleColor: 'text-rose-800'
        },
        'admin': {
            title: '⚙️ Admin Interface',
            description: 'Admin panel and backend styling',
            gradient: 'from-violet-50 to-purple-50 border-violet-200',
            titleColor: 'text-violet-800'
        }
    };

    let html = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    `;

    // Render each section
    Object.keys(sections).forEach(sectionKey => {
        const section = sections[sectionKey];
        const rules = groupedRules[sectionKey] || [];
        
        if (rules.length === 0) return; // Skip empty sections
        
        html += `
            <div class="bg-gradient-to-br ${section.gradient} rounded-lg p-6 border">
                <h4 class="text-lg font-semibold ${section.titleColor} mb-2">
                    ${section.title}
                </h4>
                <p class="text-sm text-gray-600 mb-4">${section.description}</p>
                <div class="space-y-3">
        `;
        
        rules.forEach(rule => {
            const friendlyName = getFriendlyName(rule.rule_name);
            const isColor = rule.css_property.includes('color');
            
            html += `
                <div class="bg-white rounded-lg p-3 border border-white/50">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        ${friendlyName}
                    </label>
                    ${isColor ? 
                        `<div class="flex items-center space-x-3">
                            <input type="color" 
                                   class="w-12 h-8 border border-gray-300 rounded cursor-pointer" 
                                   value="${rule.css_value.startsWith('#') ? rule.css_value : '#87ac3a'}"
                                   onchange="updateColorValue(this, ${rule.id})">
                            <input type="text" 
                                   class="css-rule-input flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm font-mono"
                                   data-rule-id="${rule.id}"
                                   data-property="${rule.css_property}"
                                   value="${rule.css_value}"
                                   placeholder="#87ac3a">
                            <div class="w-8 h-8 rounded border border-gray-300" style="background-color: ${rule.css_value}"></div>
                        </div>` :
                        `<input type="text" 
                               class="css-rule-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                               data-rule-id="${rule.id}"
                               data-property="${rule.css_property}"
                               value="${rule.css_value}"
                               placeholder="${getPlaceholder(rule.css_property)}">`
                    }
                    <div class="text-xs text-gray-500 mt-1">${getHelpText(rule.rule_name)}</div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    });
    
    html += `
        </div>
        
        <!-- Live Preview Section -->
        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                👀 Live Preview
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-3">
                    <p class="text-sm font-medium text-gray-600">Buttons:</p>
                    <button class="css-preview-button px-4 py-2 rounded font-medium" style="background-color: var(--button-bg-primary, #87ac3a); color: var(--button-text-primary, #ffffff);">Primary Button</button>
                    <button class="css-preview-button-secondary px-4 py-2 rounded font-medium border" style="border-color: var(--button-bg-primary, #87ac3a); color: var(--button-bg-primary, #87ac3a);">Secondary Button</button>
                </div>
                <div class="space-y-3">
                    <p class="text-sm font-medium text-gray-600">Text:</p>
                    <h3 class="css-preview-heading" style="color: var(--primary-color, #87ac3a); font-family: var(--font-family-primary, 'Merienda', cursive);">Sample Heading</h3>
                    <p class="css-preview-text" style="font-family: var(--font-family-primary, 'Merienda', cursive);">Sample paragraph text with your chosen font and colors.</p>
                </div>
                <div class="space-y-3">
                    <p class="text-sm font-medium text-gray-600">Form Elements:</p>
                    <input type="text" class="css-preview-input w-full px-3 py-2 border rounded" placeholder="Sample input field" style="border-color: var(--input-border-color, #d1d5db);">
                    <div class="css-preview-card p-3 rounded border" style="background-color: var(--modal-bg-color, #ffffff); border-radius: var(--border-radius-default, 8px);">
                        <p class="text-sm">Sample card content</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    contentDiv.innerHTML = html;
}

function getFriendlyName(ruleName) {
    const friendlyNames = {
        'primary_color': 'Main Brand Color',
        'primary_color_hover': 'Brand Color (Hover)',
        'secondary_color': 'Secondary Color',
        'accent_color': 'Accent Color',
        'button_bg_primary': 'Button Background',
        'button_bg_primary_hover': 'Button Background (Hover)',
        'button_text_primary': 'Button Text Color',
        'button_padding': 'Button Padding',
        'button_border_radius': 'Button Roundness',
        'font_family_primary': 'Main Font',
        'font_size_base': 'Base Text Size',
        'font_size_heading': 'Heading Size',
        'line_height_base': 'Line Spacing',
        'input_border_color': 'Input Border Color',
        'input_focus_color': 'Input Focus Color',
        'input_padding': 'Input Padding',
        'input_border_radius': 'Input Roundness',
        'nav_bg_color': 'Navigation Background',
        'nav_text_color': 'Navigation Text',
        'nav_link_hover': 'Navigation Hover Color',
        'modal_bg_color': 'Modal Background',
        'modal_border_radius': 'Modal Roundness',
        'modal_shadow': 'Modal Shadow',
        'admin_bg_color': 'Admin Background',
        'admin_text_color': 'Admin Text Color',
        'spacing_small': 'Small Spacing',
        'spacing_medium': 'Medium Spacing', 
        'spacing_large': 'Large Spacing',
        'border_radius_default': 'Default Roundness',
        'shadow_default': 'Default Shadow'
    };
    
    return friendlyNames[ruleName] || ruleName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function getPlaceholder(property) {
    const placeholders = {
        'font-family': "'Merienda', cursive",
        'font-size': '16px',
        'padding': '10px 20px',
        'margin': '16px',
        'border-radius': '8px',
        'box-shadow': '0 4px 6px rgba(0, 0, 0, 0.1)',
        'line-height': '1.6',
        'max-width': '1200px'
    };
    
    return placeholders[property] || property;
}

function getHelpText(ruleName) {
    const helpTexts = {
        'primary_color': 'Main color used for buttons, links, and accents',
        'button_bg_primary': 'Background color for all primary buttons',
        'font_family_primary': 'Main font used throughout the website',
        'font_size_base': 'Standard text size (e.g., 16px)',
        'button_padding': 'Space inside buttons (e.g., 10px 20px)',
        'border_radius_default': 'How rounded corners are (e.g., 8px)',
        'input_border_color': 'Border color for form inputs',
        'nav_bg_color': 'Background color of navigation menu',
        'modal_bg_color': 'Background color of popup windows',
        'spacing_medium': 'Standard spacing between elements'
    };
    
    return helpTexts[ruleName] || 'Customize this style property';
}

function showCSSCategory(category) {
    // Update tab styles
    document.querySelectorAll('.css-category-tab').forEach(tab => {
        tab.classList.remove('border-indigo-500', 'text-indigo-600');
        tab.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.querySelector(`[data-category="${category}"]`).classList.remove('border-transparent', 'text-gray-500');
    document.querySelector(`[data-category="${category}"]`).classList.add('border-indigo-500', 'text-indigo-600');
    
    // Show/hide content
    document.querySelectorAll('.css-category-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.getElementById(`css-category-${category}`).classList.remove('hidden');
}

function updateColorValue(colorInput, ruleId) {
    const textInput = document.querySelector(`[data-rule-id="${ruleId}"]`);
    textInput.value = colorInput.value;
}

async function saveGlobalCSSRules() {
    const inputs = document.querySelectorAll('.css-rule-input');
    const rules = [];
    
    inputs.forEach(input => {
        rules.push({
            id: input.dataset.ruleId,
            css_value: input.value
        });
    });
    
    try {
        const response = await fetch('/api/global_css_rules.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'update_bulk',
                rules: JSON.stringify(rules),
                admin_token: 'whimsical_admin_2024'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Generate and apply CSS
            await generateAndApplyCSS();
            
            // Show success message
            showAlert('CSS rules updated successfully!', 'success');
            
            // Close modal
            closeGlobalCSSModal();
        } else {
            throw new Error(result.error || 'Failed to save CSS rules');
        }
    } catch (error) {
        console.error('Error saving CSS rules:', error);
        showAlert('Failed to save CSS rules: ' + error.message, 'error');
    }
}

async function generateAndApplyCSS() {
    try {
        const response = await fetch('/api/global_css_rules.php?action=generate_css');
        const result = await response.json();
        
        if (result.success) {
            // Remove existing dynamic CSS
            const existingStyle = document.getElementById('dynamic-global-css');
            if (existingStyle) {
                existingStyle.remove();
            }
            
            // Add new CSS
            const style = document.createElement('style');
            style.id = 'dynamic-global-css';
            style.textContent = result.css_content;
            document.head.appendChild(style);
        }
    } catch (error) {
        console.error('Error generating CSS:', error);
    }
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white' : 
        'bg-blue-500 text-white'
    }`;
    alertDiv.textContent = message;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

async function resetToDefaults() {
    if (!confirm('Are you sure you want to reset all styles to their default values? This cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch('/api/global_css_rules.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'reset_defaults',
                admin_token: 'whimsical_admin_2024'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Styles reset to defaults successfully!', 'success');
            loadGlobalCSSRules(); // Reload the interface
            await generateAndApplyCSS(); // Apply the reset styles
        } else {
            throw new Error(result.error || 'Failed to reset styles');
        }
    } catch (error) {
        console.error('Error resetting styles:', error);
        showAlert('Failed to reset styles: ' + error.message, 'error');
    }
}

// Load and apply CSS on page load
document.addEventListener('DOMContentLoaded', function() {
    generateAndApplyCSS();
});
</script>

<!-- Global CSS Rules Modal -->
<div id="globalCSSModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[90vh] overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-500 to-green-600">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <span class="text-white text-lg">🎨</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Website Style Settings</h3>
                            <p class="text-green-100 text-sm">Customize colors, fonts, and appearance across your entire website</p>
                        </div>
                    </div>
                    <button onclick="closeGlobalCSSModal()" class="text-white hover:text-green-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Body -->
            <div class="overflow-y-auto max-h-[calc(90vh-200px)]">
                <!-- Loading State -->
                <div id="globalCSSLoading" class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-500 mx-auto mb-3"></div>
                    <p class="text-gray-600">Loading style settings...</p>
                </div>
                
                <!-- Content -->
                <div id="globalCSSContent" class="p-6" style="display: none;">
                    <!-- User-friendly sections will be loaded here -->
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    💡 Changes apply to your entire website instantly
                </div>
                <div class="flex space-x-3">
                    <button onclick="resetToDefaults()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Reset to Defaults
                    </button>
                    <button onclick="closeGlobalCSSModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button onclick="saveGlobalCSSRules()" 
                            class="btn-primary text-sm">
                        💾 Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template Manager Modal -->
<div id="templateManagerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-500 to-purple-600">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <span class="text-white text-lg">📋</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Template Manager</h3>
                            <p class="text-purple-100 text-sm">Manage cost breakdown templates and pricing suggestions</p>
                        </div>
                    </div>
                    <button onclick="closeTemplateManagerModal()" class="text-white hover:text-purple-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Body -->
            <div class="overflow-y-auto max-h-[calc(90vh-200px)]">
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6" aria-label="Tabs">
                        <button onclick="switchTemplateTab('cost-templates')" 
                                class="template-tab whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-purple-500 text-purple-600" 
                                data-tab="cost-templates">
                            🧮 Cost Breakdown Templates
                        </button>
                        <button onclick="switchTemplateTab('suggestion-history')" 
                                class="template-tab whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300" 
                                data-tab="suggestion-history">
                            📊 Suggestion History
                        </button>
                    </nav>
                </div>
                
                <!-- Cost Breakdown Templates Tab -->
                <div id="cost-templates-tab" class="template-tab-content p-6">
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-800">Cost Breakdown Templates</h4>
                            <button onclick="createNewCostTemplate()" class="btn-primary text-sm">
                                + Create New Template
                            </button>
                        </div>
                        
                        <!-- Loading State -->
                        <div id="costTemplatesLoading" class="text-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mx-auto mb-3"></div>
                            <p class="text-gray-600">Loading templates...</p>
                        </div>
                        
                        <!-- Templates List -->
                        <div id="costTemplatesList" class="space-y-4" style="display: none;">
                            <!-- Templates will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- Suggestion History Tab -->
                <div id="suggestion-history-tab" class="template-tab-content p-6 hidden">
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-800">Price & Cost Suggestion History</h4>
                            <div class="flex space-x-2">
                                <select id="suggestionTypeFilter" class="px-3 py-2 border border-gray-300 rounded text-sm">
                                    <option value="all">All Suggestions</option>
                                    <option value="price">Price Suggestions</option>
                                    <option value="cost">Cost Suggestions</option>
                                </select>
                                <button onclick="refreshSuggestionHistory()" class="btn-secondary text-sm">
                                    🔄 Refresh
                                </button>
                            </div>
                        </div>
                        
                        <!-- Loading State -->
                        <div id="suggestionHistoryLoading" class="text-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mx-auto mb-3"></div>
                            <p class="text-gray-600">Loading suggestion history...</p>
                        </div>
                        
                        <!-- Suggestions List -->
                        <div id="suggestionHistoryList" class="space-y-4" style="display: none;">
                            <!-- Suggestions will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    💡 Templates help speed up cost calculations and maintain consistency
                </div>
                <div class="flex space-x-3">
                    <button onclick="closeTemplateManagerModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Template Manager Functions
function openTemplateManagerModal() {
    document.getElementById('templateManagerModal').classList.remove('hidden');
    loadCostTemplates();
    loadSuggestionHistory();
}

function closeTemplateManagerModal() {
    document.getElementById('templateManagerModal').classList.add('hidden');
}

function switchTemplateTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.template-tab').forEach(tab => {
        tab.classList.remove('border-purple-500', 'text-purple-600');
        tab.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.querySelector(`[data-tab="${tabName}"]`).classList.remove('border-transparent', 'text-gray-500');
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('border-purple-500', 'text-purple-600');
    
    // Show/hide content
    document.querySelectorAll('.template-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.getElementById(`${tabName}-tab`).classList.remove('hidden');
}

async function loadCostTemplates() {
    const loading = document.getElementById('costTemplatesLoading');
    const list = document.getElementById('costTemplatesList');
    
    loading.style.display = 'block';
    list.style.display = 'none';
    
    try {
        const response = await fetch('/api/cost_breakdown_templates.php?action=list', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderCostTemplates(data.templates);
        } else {
            throw new Error(data.error || 'Failed to load templates');
        }
    } catch (error) {
        console.error('Error loading cost templates:', error);
        list.innerHTML = '<div class="text-red-600 text-center py-4">Failed to load templates</div>';
    } finally {
        loading.style.display = 'none';
        list.style.display = 'block';
    }
}

function renderCostTemplates(templates) {
    const list = document.getElementById('costTemplatesList');
    
    if (templates.length === 0) {
        list.innerHTML = '<div class="text-gray-500 text-center py-8">No templates found. Create your first template!</div>';
        return;
    }
    
    list.innerHTML = templates.map(template => `
        <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h5 class="font-semibold text-gray-800">${template.template_name}</h5>
                    <p class="text-sm text-gray-600">${template.description || 'No description'}</p>
                    <div class="flex items-center mt-2 space-x-4 text-xs text-gray-500">
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">${template.category}</span>
                        <span>Created: ${new Date(template.created_at).toLocaleDateString()}</span>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button onclick="editCostTemplate(${template.id})" class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                    <button onclick="deleteCostTemplate(${template.id})" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="font-medium text-gray-700">Materials:</span>
                    <span class="text-gray-600">$${calculateTemplateCategoryTotal(template.materials)}</span>
                </div>
                <div>
                    <span class="font-medium text-gray-700">Labor:</span>
                    <span class="text-gray-600">$${calculateTemplateCategoryTotal(template.labor)}</span>
                </div>
                <div>
                    <span class="font-medium text-gray-700">Energy:</span>
                    <span class="text-gray-600">$${calculateTemplateCategoryTotal(template.energy)}</span>
                </div>
                <div>
                    <span class="font-medium text-gray-700">Equipment:</span>
                    <span class="text-gray-600">$${calculateTemplateCategoryTotal(template.equipment)}</span>
                </div>
            </div>
            
            <div class="mt-3 pt-3 border-t border-gray-200">
                <div class="flex justify-between items-center">
                    <span class="font-semibold text-gray-800">Total Cost:</span>
                    <span class="font-bold text-lg text-green-600">$${calculateTemplateTotal(template)}</span>
                </div>
            </div>
        </div>
    `).join('');
}

function calculateTemplateCategoryTotal(categoryData) {
    if (!categoryData || typeof categoryData !== 'object') return '0.00';
    
    const items = Array.isArray(categoryData) ? categoryData : Object.values(categoryData);
    const total = items.reduce((sum, item) => {
        const cost = typeof item === 'object' ? (item.cost || 0) : item;
        return sum + parseFloat(cost || 0);
    }, 0);
    
    return total.toFixed(2);
}

function calculateTemplateTotal(template) {
    const materials = parseFloat(calculateTemplateCategoryTotal(template.materials));
    const labor = parseFloat(calculateTemplateCategoryTotal(template.labor));
    const energy = parseFloat(calculateTemplateCategoryTotal(template.energy));
    const equipment = parseFloat(calculateTemplateCategoryTotal(template.equipment));
    
    return (materials + labor + energy + equipment).toFixed(2);
}

async function loadSuggestionHistory() {
    const loading = document.getElementById('suggestionHistoryLoading');
    const list = document.getElementById('suggestionHistoryList');
    
    loading.style.display = 'block';
    list.style.display = 'none';
    
    try {
        // For now, just show a placeholder since we need to create list endpoints
        list.innerHTML = '<div class="text-gray-500 text-center py-8">Suggestion history will be displayed here once you start using the cost and price suggestion features.</div>';
        
    } catch (error) {
        console.error('Error loading suggestion history:', error);
        list.innerHTML = '<div class="text-red-600 text-center py-4">Failed to load suggestion history</div>';
    } finally {
        loading.style.display = 'none';
        list.style.display = 'block';
    }
}

function refreshSuggestionHistory() {
    loadSuggestionHistory();
}
</script>

<!-- Analytics & Insights Modal -->
<div id="analyticsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-7xl w-full max-h-[90vh] overflow-y-auto m-4">
        <div class="flex justify-between items-center p-6 border-b border-gray-200">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <svg class="w-8 h-8 mr-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Analytics & Insights Dashboard
                </h2>
                <p class="text-gray-600 mt-1">Comprehensive website analytics and AI-powered optimization suggestions</p>
            </div>
            <button onclick="closeAnalyticsModal()" class="text-gray-500 hover:text-gray-700 text-2xl font-bold">&times;</button>
        </div>
        
        <div class="p-6">
            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button onclick="switchAnalyticsTab('overview')" data-tab="overview" 
                            class="analytics-tab border-b-2 border-blue-500 text-blue-600 py-2 px-1 text-sm font-medium">
                        Overview
                    </button>
                    <button onclick="switchAnalyticsTab('behavior')" data-tab="behavior" 
                            class="analytics-tab border-b-2 border-transparent text-gray-500 hover:text-gray-700 py-2 px-1 text-sm font-medium">
                        User Behavior
                    </button>
                    <button onclick="switchAnalyticsTab('products')" data-tab="products" 
                            class="analytics-tab border-b-2 border-transparent text-gray-500 hover:text-gray-700 py-2 px-1 text-sm font-medium">
                        Product Performance
                    </button>
                    <button onclick="switchAnalyticsTab('optimization')" data-tab="optimization" 
                            class="analytics-tab border-b-2 border-transparent text-gray-500 hover:text-gray-700 py-2 px-1 text-sm font-medium">
                        AI Optimization
                    </button>
                </nav>
            </div>
            
            <!-- Overview Tab -->
            <div id="overview-tab" class="analytics-tab-content">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Key Metrics Cards -->
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm">Total Sessions</p>
                                <p class="text-3xl font-bold" id="totalSessions">--</p>
                            </div>
                            <svg class="w-8 h-8 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm">Conversion Rate</p>
                                <p class="text-3xl font-bold" id="conversionRate">--%</p>
                            </div>
                            <svg class="w-8 h-8 text-green-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-purple-100 text-sm">Avg. Session Duration</p>
                                <p class="text-3xl font-bold" id="avgSessionDuration">--</p>
                            </div>
                            <svg class="w-8 h-8 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-orange-100 text-sm">Bounce Rate</p>
                                <p class="text-3xl font-bold" id="bounceRate">--%</p>
                            </div>
                            <svg class="w-8 h-8 text-orange-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Conversion Funnel</h3>
                        <div id="conversionFunnel" class="space-y-3">
                            <!-- Funnel visualization will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Pages</h3>
                        <div id="topPages" class="space-y-2">
                            <!-- Top pages will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Behavior Tab -->
            <div id="behavior-tab" class="analytics-tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Device & Browser Analytics</h3>
                        <div id="deviceAnalytics">
                            <!-- Device analytics will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">User Flow Analysis</h3>
                        <div id="userFlow">
                            <!-- User flow will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Performance Tab -->
            <div id="products-tab" class="analytics-tab-content hidden">
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Product Performance Metrics</h3>
                    <div id="productPerformance">
                        <!-- Product performance will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- AI Optimization Tab -->
            <div id="optimization-tab" class="analytics-tab-content hidden">
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">AI-Powered Optimization Suggestions</h3>
                        <button onclick="generateOptimizationSuggestions()" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            🤖 Generate New Suggestions
                        </button>
                    </div>
                    
                    <div id="optimizationSuggestions" class="space-y-4">
                        <!-- Optimization suggestions will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <select id="analyticsTimeframe" onchange="refreshAnalytics()" class="border border-gray-300 rounded px-3 py-1 text-sm">
                    <option value="1d">Last 24 Hours</option>
                    <option value="7d" selected>Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                    <option value="90d">Last 90 Days</option>
                </select>
                <button onclick="refreshAnalytics()" class="text-blue-500 hover:text-blue-700 text-sm font-medium">
                    🔄 Refresh Data
                </button>
            </div>
            <div class="flex space-x-3">
                <button onclick="closeAnalyticsModal()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Analytics Modal Functions
function openAnalyticsModal() {
    document.getElementById('analyticsModal').classList.remove('hidden');
    loadAnalyticsData();
}

function closeAnalyticsModal() {
    document.getElementById('analyticsModal').classList.add('hidden');
}

function switchAnalyticsTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.analytics-tab').forEach(tab => {
        tab.classList.remove('border-blue-500', 'text-blue-600');
        tab.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.querySelector(`[data-tab="${tabName}"]`).classList.remove('border-transparent', 'text-gray-500');
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('border-blue-500', 'text-blue-600');
    
    // Show/hide content
    document.querySelectorAll('.analytics-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.getElementById(`${tabName}-tab`).classList.remove('hidden');
}

async function loadAnalyticsData() {
    const timeframe = document.getElementById('analyticsTimeframe').value;
    
    try {
        // Load analytics report
        const response = await fetch(`/api/analytics_tracker.php?action=get_analytics_report&timeframe=${timeframe}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateAnalyticsDashboard(data.data);
        } else {
            throw new Error(data.error || 'Failed to load analytics data');
        }
    } catch (error) {
        console.error('Error loading analytics:', error);
        showAnalyticsError('Failed to load analytics data. Make sure you have visitor data to analyze.');
    }
}

function updateAnalyticsDashboard(data) {
    // Update overview metrics
    document.getElementById('totalSessions').textContent = data.overall_stats?.total_sessions || '0';
    document.getElementById('conversionRate').textContent = 
        data.overall_stats?.total_sessions > 0 ? 
        ((data.overall_stats.conversions / data.overall_stats.total_sessions) * 100).toFixed(1) + '%' : '0%';
    document.getElementById('avgSessionDuration').textContent = 
        data.overall_stats?.avg_session_duration ? 
        Math.round(data.overall_stats.avg_session_duration) + 's' : '0s';
    document.getElementById('bounceRate').textContent = 
        data.overall_stats?.bounce_rate ? 
        Math.round(data.overall_stats.bounce_rate) + '%' : '0%';
    
    // Update conversion funnel
    updateConversionFunnel(data.conversion_funnel || []);
    
    // Update top pages
    updateTopPages(data.top_pages || []);
    
    // Update product performance
    updateProductPerformance(data.product_performance || []);
}

function updateConversionFunnel(funnelData) {
    const container = document.getElementById('conversionFunnel');
    
    if (funnelData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No funnel data available yet. Start getting visitors to see conversion insights!</p>';
        return;
    }
    
    const stepNames = {
        'landing': 'Landing Page',
        'product_view': 'Product Views',
        'cart_add': 'Add to Cart',
        'checkout_start': 'Checkout Started',
        'checkout_complete': 'Purchase Complete'
    };
    
    const maxSessions = Math.max(...funnelData.map(step => step.sessions_count));
    
    container.innerHTML = funnelData.map((step, index) => {
        const percentage = maxSessions > 0 ? (step.sessions_count / maxSessions) * 100 : 0;
        const dropoff = index > 0 ? 
            ((funnelData[index-1].sessions_count - step.sessions_count) / funnelData[index-1].sessions_count * 100).toFixed(1) : 0;
        
        return `
            <div class="flex items-center space-x-4">
                <div class="flex-1">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium text-gray-700">${stepNames[step.funnel_step] || step.funnel_step}</span>
                        <span class="text-sm text-gray-600">${step.sessions_count} sessions</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" style="width: ${percentage}%"></div>
                    </div>
                    ${index > 0 && dropoff > 0 ? `<p class="text-xs text-red-600 mt-1">${dropoff}% drop-off</p>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function updateTopPages(topPages) {
    const container = document.getElementById('topPages');
    
    if (topPages.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No page data available yet.</p>';
        return;
    }
    
    container.innerHTML = topPages.map(page => `
        <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
            <div>
                <p class="text-sm font-medium text-gray-800">${page.page_url}</p>
                <p class="text-xs text-gray-500">${page.page_type}</p>
            </div>
            <div class="text-right">
                <p class="text-sm font-medium text-gray-800">${page.views} views</p>
                <p class="text-xs text-gray-500">${Math.round(page.avg_time)}s avg</p>
            </div>
        </div>
    `).join('');
}

function updateProductPerformance(productData) {
    const container = document.getElementById('productPerformance');
    
    if (productData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No product data available yet. Product analytics will appear once you have visitor interactions.</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cart Adds</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchases</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conversion</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${productData.map(product => `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">${product.product_sku}</div>
                                <div class="text-sm text-gray-500">${product.product_name || 'Unknown'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${product.views_count}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${product.cart_adds_count}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${product.purchases_count}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${product.conversion_rate}%</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function generateOptimizationSuggestions() {
    const container = document.getElementById('optimizationSuggestions');
    container.innerHTML = '<div class="text-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-3"></div><p class="text-gray-600">Analyzing your website data and generating AI-powered suggestions...</p></div>';
    
    try {
        const response = await fetch('/api/analytics_tracker.php?action=get_optimization_suggestions', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayOptimizationSuggestions(data.suggestions);
        } else {
            throw new Error(data.error || 'Failed to generate suggestions');
        }
    } catch (error) {
        console.error('Error generating suggestions:', error);
        container.innerHTML = '<div class="text-red-600 text-center py-4">Failed to generate optimization suggestions. Make sure you have enough visitor data for analysis.</div>';
    }
}

function displayOptimizationSuggestions(suggestions) {
    const container = document.getElementById('optimizationSuggestions');
    
    if (suggestions.length === 0) {
        container.innerHTML = '<div class="text-gray-500 text-center py-8">Great job! No major optimization issues detected. Your website is performing well. Keep monitoring as you get more traffic.</div>';
        return;
    }
    
    const priorityColors = {
        'critical': 'bg-red-100 border-red-500 text-red-800',
        'high': 'bg-orange-100 border-orange-500 text-orange-800',
        'medium': 'bg-yellow-100 border-yellow-500 text-yellow-800',
        'low': 'bg-blue-100 border-blue-500 text-blue-800'
    };
    
    const priorityIcons = {
        'critical': '🚨',
        'high': '⚠️',
        'medium': '💡',
        'low': 'ℹ️'
    };
    
    container.innerHTML = suggestions.map(suggestion => `
        <div class="border-l-4 ${priorityColors[suggestion.priority]} p-4 rounded-r-lg">
            <div class="flex items-start">
                <div class="flex-shrink-0 text-2xl mr-3">
                    ${priorityIcons[suggestion.priority]}
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-lg font-semibold">${suggestion.title}</h4>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs px-2 py-1 rounded bg-gray-200 text-gray-700">${suggestion.type}</span>
                            <span class="text-xs px-2 py-1 rounded bg-green-200 text-green-700">${Math.round(suggestion.confidence_score * 100)}% confidence</span>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-3">${suggestion.description}</p>
                    <div class="bg-white p-3 rounded border">
                        <h5 class="font-medium text-gray-800 mb-1">💡 Recommended Action:</h5>
                        <p class="text-gray-700">${suggestion.suggested_action}</p>
                    </div>
                    <div class="flex items-center justify-between mt-3 text-sm text-gray-600">
                        <span>Impact: ${suggestion.potential_impact}</span>
                        <span>Priority: ${suggestion.priority.toUpperCase()}</span>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function showAnalyticsError(message) {
    const containers = ['totalSessions', 'conversionRate', 'avgSessionDuration', 'bounceRate'];
    containers.forEach(id => {
        document.getElementById(id).textContent = '--';
    });
    
    document.getElementById('conversionFunnel').innerHTML = `<p class="text-gray-500 text-center py-4">${message}</p>`;
    document.getElementById('topPages').innerHTML = `<p class="text-gray-500 text-center py-4">${message}</p>`;
}

function refreshAnalytics() {
    loadAnalyticsData();
}
</script>

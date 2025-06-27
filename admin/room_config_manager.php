<?php
/**
 * Room Configuration Manager
 * Admin interface for managing room configurations
 */

// Include centralized authentication
require_once __DIR__ . '/../includes/auth.php';

// Require admin authentication
requireAdmin();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Configuration Manager - WhimsicalFrog Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .config-section {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .config-input {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 0.5rem;
            width: 100%;
        }
        .success-message {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .error-message {
            background-color: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Room Configuration Manager</h1>
            
            <div id="messageContainer"></div>
            
            <!-- Room Selection -->
            <div class="mb-6">
                <label for="roomSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Room:</label>
                <select id="roomSelect" class="config-input" onchange="loadRoomConfig()">
                    <option value="">Choose a room...</option>
                    <option value="2">Room 2 - T-Shirts</option>
                    <option value="3">Room 3 - Tumblers</option>
                    <option value="4">Room 4 - Artwork</option>
                    <option value="5">Room 5 - Sublimation</option>
                    <option value="6">Room 6 - Window Wraps</option>
                </select>
            </div>
            
            <div class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Current Room Configurations</h2>
                
                <div id="roomConfigContainer"></div>
            </div>
            
            <div class="border-t pt-8">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Configure Room Settings</h2>
                
                <div id="configFormContainer" style="display: none;">
                    <form id="roomConfigForm" class="space-y-8">
                        <input type="hidden" id="roomNumber" name="room_number">
                        
                        <div class="config-section">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Popup Settings</h3>
                        <div class="config-group">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Show Delay (ms)</label>
                                <input type="number" id="show_delay" name="show_delay" value="50" min="0" max="1000" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hide Delay (ms)</label>
                                <input type="number" id="hide_delay" name="hide_delay" value="150" min="0" max="1000" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max Width (px)</label>
                                <input type="number" id="max_width" name="max_width" value="450" min="200" max="800" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Min Width (px)</label>
                                <input type="number" id="min_width" name="min_width" value="280" min="200" max="600" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="enable_sales_check" checked class="mr-2">
                                Enable Sales Check
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="show_category" checked class="mr-2">
                                Show Category
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="show_description" checked class="mr-2">
                                Show Description
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="enable_image_fallback" checked class="mr-2">
                                Image Fallback
                            </label>
                        </div>
                    </div>
                    
                    <div class="config-section">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Modal Settings</h3>
                        <div class="config-group">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max Quantity</label>
                                <input type="number" id="max_quantity" name="max_quantity" value="999" min="1" max="9999" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Min Quantity</label>
                                <input type="number" id="min_quantity" name="min_quantity" value="1" min="1" max="10" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" id="enable_colors" name="enable_colors" checked class="mr-2">
                                Enable Colors
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="enable_sizes" name="enable_sizes" checked class="mr-2">
                                Enable Sizes
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="show_unit_price" checked class="mr-2">
                                Show Unit Price
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="enable_stock_checking" checked class="mr-2">
                                Stock Checking
                            </label>
                        </div>
                    </div>
                    
                    <div class="config-section">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Interaction Settings</h3>
                        <div class="config-group">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Debounce Time (ms)</label>
                                <input type="number" id="debounce_time" name="debounce_time" value="50" min="0" max="500" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="click_to_details" checked class="mr-2">
                                Click to Details
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="hover_to_popup" checked class="mr-2">
                                Hover to Popup
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="popup_add_to_cart" checked class="mr-2">
                                Popup Add to Cart
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="enable_touch_events" checked class="mr-2">
                                Touch Events
                            </label>
                        </div>
                    </div>
                    
                    <div class="config-section">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Visual Settings</h3>
                        <div class="config-group">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Popup Animation</label>
                                <select id="popup_animation" name="popup_animation" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    <option value="fade">Fade</option>
                                    <option value="slide">Slide</option>
                                    <option value="scale">Scale</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Modal Animation</label>
                                <select id="modal_animation" name="modal_animation" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    <option value="scale">Scale</option>
                                    <option value="fade">Fade</option>
                                    <option value="slide">Slide</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Button Style</label>
                                <select name="button_style" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                    <option value="brand">Brand</option>
                                    <option value="modern">Modern</option>
                                    <option value="classic">Classic</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="window.location.href='/admin'" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Back to Admin
                        </button>
                        <button type="button" onclick="saveConfiguration()" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        let currentRoomConfig = null;

        async function loadRoomConfig() {
            const roomSelect = document.getElementById('roomSelect');
            const roomNumber = roomSelect.value;
            
            if (!roomNumber) {
                document.getElementById('configFormContainer').style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`/api/room_config.php?action=get_room_config&room=${roomNumber}`);
                const config = await response.json();
                
                if (config.error) {
                    showMessage('Error loading configuration: ' + config.error, 'error');
                    return;
                }

                currentRoomConfig = config;
                populateForm(config);
                document.getElementById('configFormContainer').style.display = 'block';
                document.getElementById('roomNumber').value = roomNumber;
                
            } catch (error) {
                showMessage('Error loading configuration: ' + error.message, 'error');
            }
        }

        function populateForm(config) {
            // Popup Settings
            document.getElementById('show_delay').value = config.popup_settings?.show_delay || 50;
            document.getElementById('hide_delay').value = config.popup_settings?.hide_delay || 150;
            document.getElementById('max_width').value = config.popup_settings?.max_width || 450;
            document.getElementById('min_width').value = config.popup_settings?.min_width || 280;
            
            // Modal Settings
            document.getElementById('enable_colors').checked = config.modal_settings?.enable_colors || false;
            document.getElementById('enable_sizes').checked = config.modal_settings?.enable_sizes || false;
            document.getElementById('max_quantity').value = config.modal_settings?.max_quantity || 999;
            document.getElementById('min_quantity').value = config.modal_settings?.min_quantity || 1;
            
            // Interaction Settings
            document.getElementById('debounce_time').value = config.interaction_settings?.debounce_time || 50;
            
            // Visual Settings
            document.getElementById('popup_animation').value = config.visual_settings?.popup_animation || 'fade';
            document.getElementById('modal_animation').value = config.visual_settings?.modal_animation || 'scale';
        }

        async function saveConfiguration() {
            const roomNumber = document.getElementById('roomNumber').value;
            
            if (!roomNumber) {
                showMessage('Please select a room first', 'error');
                return;
            }

            const config = {
                room_number: parseInt(roomNumber),
                config: {
                    popup_settings: {
                        show_delay: parseInt(document.getElementById('show_delay').value),
                        hide_delay: parseInt(document.getElementById('hide_delay').value),
                        max_width: parseInt(document.getElementById('max_width').value),
                        min_width: parseInt(document.getElementById('min_width').value),
                        enable_sales_check: true,
                        show_category: true,
                        show_description: true,
                        enable_image_fallback: true
                    },
                    modal_settings: {
                        enable_colors: document.getElementById('enable_colors').checked,
                        enable_sizes: document.getElementById('enable_sizes').checked,
                        max_quantity: parseInt(document.getElementById('max_quantity').value),
                        min_quantity: parseInt(document.getElementById('min_quantity').value),
                        show_unit_price: true,
                        show_total_calculation: true,
                        enable_stock_checking: true
                    },
                    interaction_settings: {
                        click_to_details: true,
                        hover_to_popup: true,
                        popup_add_to_cart: true,
                        enable_touch_events: true,
                        debounce_time: parseInt(document.getElementById('debounce_time').value)
                    },
                    visual_settings: {
                        popup_animation: document.getElementById('popup_animation').value,
                        modal_animation: document.getElementById('modal_animation').value,
                        button_style: 'brand',
                        color_theme: 'whimsical'
                    }
                }
            };

            try {
                const response = await fetch('/api/room_config.php?action=update_config', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(config)
                });

                const result = await response.json();
                
                if (result.success) {
                    showMessage('Configuration saved successfully!', 'success');
                    loadAllConfigurations();
                } else {
                    showMessage('Error saving configuration: ' + result.message, 'error');
                }
                
            } catch (error) {
                showMessage('Error saving configuration: ' + error.message, 'error');
            }
        }

        async function loadAllConfigurations() {
            try {
                const response = await fetch('/api/room_config.php?action=get_all_configs');
                const configs = await response.json();
                
                displayConfigurations(configs);
                
            } catch (error) {
                console.error('Error loading configurations:', error);
            }
        }

        function displayConfigurations(configs) {
            const container = document.getElementById('roomConfigContainer');
            
            if (!configs || configs.length === 0) {
                container.innerHTML = '<p class="text-gray-600">No room configurations found. Create one below.</p>';
                return;
            }

            let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">';
            
            configs.forEach(config => {
                const popupSettings = JSON.parse(config.popup_settings || '{}');
                const modalSettings = JSON.parse(config.modal_settings || '{}');
                const visualSettings = JSON.parse(config.visual_settings || '{}');
                
                html += `
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-lg mb-2">Room ${config.room_number}</h3>
                        <div class="text-sm text-gray-600">
                            <p>Popup Animation: ${visualSettings.popup_animation || 'fade'}</p>
                            <p>Colors Enabled: ${modalSettings.enable_colors ? 'Yes' : 'No'}</p>
                            <p>Sizes Enabled: ${modalSettings.enable_sizes ? 'Yes' : 'No'}</p>
                            <p>Last Updated: ${new Date(config.updated_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        function showMessage(message, type) {
            const container = document.getElementById('messageContainer');
            const className = type === 'success' ? 'success-message' : 'error-message';
            
            container.innerHTML = `<div class="${className}">${message}</div>`;
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        // Load configurations on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAllConfigurations();
        });
    </script>
</body>
</html> 
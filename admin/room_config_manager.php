<?php
/**
 * Room Configuration Manager
 * Admin interface for managing room configurations
 * Cleaned up version with centralized CSS and improved structure
 */

// Include centralized functionality
require_once __DIR__ . '/../includes/functions.php';

// Require admin authentication
Auth::requireAdmin();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Configuration Manager - WhimsicalFrog Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Admin styles now loaded from database via main CSS system -->
    <style>
        /* Load essential CSS from database */
        <?php
        // For standalone admin files, load CSS directly from database
        require_once '../includes/database.php';
        try {
            $db = Database::getInstance();
            $rules = $db->query("SELECT rule_name, css_property, css_value FROM global_css_rules WHERE is_active = 1 AND category IN ('admin', 'modals', 'forms', 'buttons') ORDER BY category, rule_name")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rules as $rule) {
                echo ".{$rule['rule_name']} { {$rule['css_property']}: {$rule['css_value']}; }\n";
            }
        } catch (Exception $e) {
            echo "/* CSS loading failed */\n";
        }
        ?>
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
                <select id="roomSelect" class="form-input w-full" onchange="loadRoomConfig()">
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
                                    <input type="number" id="show_delay" name="show_delay" value="50" min="0" max="1000" class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Hide Delay (ms)</label>
                                    <input type="number" id="hide_delay" name="hide_delay" value="150" min="0" max="1000" class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Width (px)</label>
                                    <input type="number" id="max_width" name="max_width" value="450" min="200" max="800" class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Width (px)</label>
                                    <input type="number" id="min_width" name="min_width" value="280" min="200" max="600" class="form-input w-full">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
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
                                    <input type="number" id="max_quantity" name="max_quantity" value="999" min="1" max="9999" class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Quantity</label>
                                    <input type="number" id="min_quantity" name="min_quantity" value="1" min="1" max="10" class="form-input w-full">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
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
                                    <input type="number" id="debounce_time" name="debounce_time" value="50" min="0" max="500" class="form-input w-full">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
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
                                    <select id="popup_animation" name="popup_animation" class="form-input w-full">
                                        <option value="fade">Fade</option>
                                        <option value="slide">Slide</option>
                                        <option value="scale">Scale</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Modal Animation</label>
                                    <select id="modal_animation" name="modal_animation" class="form-input w-full">
                                        <option value="scale">Scale</option>
                                        <option value="fade">Fade</option>
                                        <option value="slide">Slide</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-4">
                            <button type="button" onclick="resetForm()" class="btn-secondary">Reset to Defaults</button>
                            <button type="submit" class="btn-primary">Save Configuration</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/admin-common.js?v=<?php echo time(); ?>"></script>
    <script>
    // Room configuration management functionality
    let currentRoomConfig = {};
    
    async function loadRoomConfig() {
        const roomNumber = document.getElementById('roomSelect').value;
        if (!roomNumber) {
            document.getElementById('configFormContainer').style.display = 'none';
            return;
        }
        
        try {
            const response = await fetch(`../api/room_config.php?action=get&room=${roomNumber}`);
            const data = await response.json();
            
            if (data.success) {
                currentRoomConfig = data.config || {};
                populateForm(currentRoomConfig);
                document.getElementById('configFormContainer').style.display = 'block';
                document.getElementById('roomNumber').value = roomNumber;
            } else {
                showMessage('Error loading room configuration: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error loading room config:', error);
            showMessage('Failed to load room configuration', 'error');
        }
    }
    
    function populateForm(config) {
        // Populate form fields with config values
        Object.keys(config).forEach(key => {
            const element = document.querySelector(`[name="${key}"]`);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = config[key];
                } else {
                    element.value = config[key];
                }
            }
        });
    }
    
    function resetForm() {
        const form = document.getElementById('roomConfigForm');
        form.reset();
        
        // Reset to default values
        const defaults = {
            show_delay: 50,
            hide_delay: 150,
            max_width: 450,
            min_width: 280,
            max_quantity: 999,
            min_quantity: 1,
            debounce_time: 50,
            popup_animation: 'fade',
            modal_animation: 'scale'
        };
        
        populateForm(defaults);
    }
    
    document.getElementById('roomConfigForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const config = {};
        
        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            config[key] = value;
        }
        
        // Handle checkboxes separately
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            config[checkbox.name] = checkbox.checked;
        });
        
        try {
            const response = await fetch('../api/room_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'save',
                    room: config.room_number,
                    config: config
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showMessage('Room configuration saved successfully!', 'success');
                currentRoomConfig = config;
            } else {
                showMessage('Error saving configuration: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error saving room config:', error);
            showMessage('Failed to save room configuration', 'error');
        }
    });
    
    function showMessage(message, type) {
        const container = document.getElementById('messageContainer');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
        
        container.innerHTML = `
            <div class="${alertClass}">
                ${message}
            </div>
        `;
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            container.innerHTML = '';
        }, 5000);
    }
    </script>
</body>
</html> 
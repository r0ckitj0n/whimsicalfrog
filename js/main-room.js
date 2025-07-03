/**
 * Main Room JavaScript - Centralized functionality for main room door interactions
 * Enhanced version with proper coordinate positioning and database integration
 */

// Main room configuration - now loads coordinates from database
const MainRoomConfig = {
    originalImageWidth: 1280,
    originalImageHeight: 896,
    doorCoordinates: [] // Will be loaded from database
};

// Enhanced Room Settings Modal System
const EnhancedRoomSettings = {
    modal: null,
    
    open() {
        if (this.modal && this.modal.style.display === 'block') {
            return; // Already open
        }
        
        this.createModal();
        this.loadRoomSettings();
        this.modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    },
    
    close() {
        if (this.modal) {
            this.modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    },
    
    createModal() {
        if (this.modal) return;
        
        this.modal = document.createElement('div');
        this.modal.className = 'admin-modal-overlay';
        this.modal.innerHTML = `
            <div class="admin-modal-content" style="max-width: 900px; width: 95%;">
                <div class="admin-modal-header">
                    <h2>Enhanced Room Settings</h2>
                    <button type="button" class="modal-close" onclick="EnhancedRoomSettings.close()">×</button>
                </div>
                <div class="admin-modal-body">
                    <div class="loading-message">
                        <p>Loading room settings...</p>
                        <div class="loading-spinner"></div>
                    </div>
                    <div id="roomSettingsContent" style="display: none;">
                        <div class="settings-tabs">
                            <button class="tab-button active" data-tab="rooms">Room Configuration</button>
                            <button class="tab-button" data-tab="display">Display Settings</button>
                        </div>
                        <div class="tab-content">
                            <div id="rooms-tab" class="tab-panel active">
                                <h3>Room Names & Descriptions</h3>
                                <div id="roomsList"></div>
                            </div>
                                                         <div id="display-tab" class="tab-panel">
                                 <h3>Main Room Display Settings</h3>
                                 <div class="form-group">
                                     <label>
                                         <input type="checkbox" id="fullScreenMode"> 
                                         Enable Full-Screen Mode for Main Room
                                     </label>
                                     <small>Makes the main room display like the landing page</small>
                                 </div>
                                 <div class="form-group">
                                     <label>
                                         <input type="checkbox" id="showMainRoomTitle"> 
                                         Show Main Room Title & Description
                                     </label>
                                     <small>Display the main room title and description overlay at the top of the room</small>
                                 </div>
                             </div>
                        </div>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <button type="button" class="btn-secondary" onclick="EnhancedRoomSettings.close()">Cancel</button>
                    <button type="button" class="btn-primary" onclick="EnhancedRoomSettings.saveAllSettings()">Save All Settings</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(this.modal);
        
        // Tab switching functionality
        this.modal.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', (e) => {
                const tabId = e.target.dataset.tab;
                this.switchTab(tabId);
            });
        });
    },
    
    switchTab(tabId) {
        // Update buttons
        this.modal.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabId);
        });
        
        // Update panels
        this.modal.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.toggle('active', panel.id === `${tabId}-tab`);
        });
    },
    
    async loadRoomSettings() {
        try {
            // Load room settings
            const roomsResponse = await fetch('/api/room_settings.php?action=get_all&admin_token=whimsical_admin_2024');
            const roomsData = await roomsResponse.json();
            
            // Load business settings (for full-screen mode)
            const businessResponse = await fetch('/api/business_settings.php?action=get_by_category&category=rooms&admin_token=whimsical_admin_2024');
            const businessData = await businessResponse.json();
            
            this.populateRoomsTab(roomsData.rooms || []);
            this.populateDisplayTab(businessData.settings || []);
            
            // Hide loading, show content
            this.modal.querySelector('.loading-message').style.display = 'none';
            this.modal.querySelector('#roomSettingsContent').style.display = 'block';
            
        } catch (error) {
            console.error('Error loading room settings:', error);
            this.modal.querySelector('.loading-message').innerHTML = '<p style="color: red;">Error loading settings. Please try again.</p>';
        }
    },
    
    populateRoomsTab(rooms) {
        const roomsList = this.modal.querySelector('#roomsList');
        const productRooms = rooms.filter(room => room.room_number >= 2 && room.room_number <= 6)
                                  .sort((a, b) => a.display_order - b.display_order);
        
        roomsList.innerHTML = productRooms.map(room => `
            <div class="room-setting-item">
                <h4>Room ${room.room_number}: ${room.room_name}</h4>
                <div class="form-group">
                    <label>Room Name:</label>
                    <input type="text" data-room="${room.room_number}" data-field="room_name" 
                           value="${room.room_name || ''}" class="form-input">
                </div>
                <div class="form-group">
                    <label>Door Label:</label>
                    <input type="text" data-room="${room.room_number}" data-field="door_label" 
                           value="${room.door_label || ''}" class="form-input">
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea data-room="${room.room_number}" data-field="description" 
                              class="form-input" rows="2">${room.description || ''}</textarea>
                </div>
            </div>
        `).join('');
    },
    
    populateDisplayTab(settings) {
        const fullScreenSetting = settings.find(s => s.setting_key === 'main_room_fullscreen');
        const fullScreenCheckbox = this.modal.querySelector('#fullScreenMode');
        
        if (fullScreenCheckbox && fullScreenSetting) {
            fullScreenCheckbox.checked = fullScreenSetting.setting_value === 'true';
        }
        
        const showTitleSetting = settings.find(s => s.setting_key === 'main_room_show_title');
        const showTitleCheckbox = this.modal.querySelector('#showMainRoomTitle');
        
        if (showTitleCheckbox && showTitleSetting) {
            showTitleCheckbox.checked = showTitleSetting.setting_value === 'true';
        }
    },
    
    async saveAllSettings() {
        const saveButton = this.modal.querySelector('.btn-primary');
        const originalText = saveButton.textContent;
        saveButton.textContent = 'Saving...';
        saveButton.disabled = true;
        
        try {
            // Save room settings
            const roomInputs = this.modal.querySelectorAll('[data-room]');
            const roomUpdates = {};
            
            roomInputs.forEach(input => {
                const roomNumber = input.dataset.room;
                const field = input.dataset.field;
                const value = input.value;
                
                if (!roomUpdates[roomNumber]) {
                    roomUpdates[roomNumber] = { room_number: roomNumber };
                }
                roomUpdates[roomNumber][field] = value;
            });
            
            // Save each room
            for (const roomData of Object.values(roomUpdates)) {
                await this.saveRoomSetting(roomData);
            }
            
                         // Save full-screen setting
             const fullScreenMode = this.modal.querySelector('#fullScreenMode').checked;
             await this.saveBusinessSetting('main_room_fullscreen', fullScreenMode ? 'true' : 'false');
             
             // Save show title setting
             const showMainRoomTitle = this.modal.querySelector('#showMainRoomTitle').checked;
             await this.saveBusinessSetting('main_room_show_title', showMainRoomTitle ? 'true' : 'false');
            
            // Success feedback
            this.showSuccess('All settings saved successfully!');
            
            // Reload page to apply changes
            setTimeout(() => {
                window.location.reload();
            }, 1000);
            
        } catch (error) {
            console.error('Error saving settings:', error);
            this.showError('Error saving settings. Please try again.');
        } finally {
            saveButton.textContent = originalText;
            saveButton.disabled = false;
        }
    },
    
    async saveRoomSetting(roomData) {
        const response = await fetch('/api/room_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                admin_token: 'whimsical_admin_2024',
                action: 'update_room',
                ...roomData
            })
        });
        
        if (!response.ok) {
            throw new Error(`Failed to save room ${roomData.room_number}`);
        }
    },
    
    async saveBusinessSetting(key, value) {
        const response = await fetch('/api/business_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                admin_token: 'whimsical_admin_2024',
                action: 'update_setting',
                key: key,
                value: value
            })
        });
        
        if (!response.ok) {
            throw new Error(`Failed to save business setting ${key}`);
        }
    },
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    },
    
    showError(message) {
        this.showNotification(message, 'error');
    },
    
    showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            background: ${type === 'success' ? '#10b981' : '#dc2626'};
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
};

// Load coordinates from database like other rooms
async function loadMainRoomCoordinatesFromDatabase() {
    try {
        const response = await fetch(`api/get_room_coordinates.php?room_type=room_main`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Database not available`);
        }
        
        const data = await response.json();
        
        if (data.success && data.coordinates && data.coordinates.length > 0) {
            // Convert selector format from "area-1" to ".area-1" for CSS selectors
            MainRoomConfig.doorCoordinates = data.coordinates.map(coord => ({
                ...coord,
                selector: coord.selector.startsWith('.') ? coord.selector : `.${coord.selector}`
            }));
            console.log('Main room coordinates loaded from database:', MainRoomConfig.doorCoordinates);
            return true;
        } else {
            console.error('No active room map found in database for room_main');
            return false;
        }
    } catch (error) {
        console.error('Error loading main room coordinates from database:', error);
        return false;
    }
}

// Coordinate positioning system - Enhanced to override CSS positioning
function applyDoorCoordinates() {
    const container = document.querySelector('#mainRoomPage');
    if (!container) {
        console.warn('Main room container not found');
        return;
    }
    
    if (!MainRoomConfig.doorCoordinates || MainRoomConfig.doorCoordinates.length === 0) {
        console.warn('No door coordinates available to apply');
        return;
    }
    
    const containerRect = container.getBoundingClientRect();
    const scaleX = containerRect.width / MainRoomConfig.originalImageWidth;
    const scaleY = containerRect.height / MainRoomConfig.originalImageHeight;
    
    console.log('Applying door coordinates with scale:', { scaleX, scaleY, containerRect });
    
    MainRoomConfig.doorCoordinates.forEach((coord, index) => {
        const door = container.querySelector(coord.selector);
        if (door) {
            const scaledTop = coord.top * scaleY;
            const scaledLeft = coord.left * scaleX;
            const scaledWidth = coord.width * scaleX;
            const scaledHeight = coord.height * scaleY;
            
            // Override CSS positioning by setting important styles
            door.style.setProperty('top', `${scaledTop}px`, 'important');
            door.style.setProperty('left', `${scaledLeft}px`, 'important');
            door.style.setProperty('width', `${scaledWidth}px`, 'important');
            door.style.setProperty('height', `${scaledHeight}px`, 'important');
            door.style.setProperty('bottom', 'auto', 'important');
            door.style.setProperty('right', 'auto', 'important');
            door.style.setProperty('transform', 'none', 'important');
            
            console.log(`Applied coordinates to ${coord.selector}:`, {
                top: scaledTop,
                left: scaledLeft,
                width: scaledWidth,
                height: scaledHeight
            });
        } else {
            console.warn(`Door element not found: ${coord.selector}`);
        }
    });
}

// Room navigation function
function enterRoom(roomNumber) {
    console.log('Entering room:', roomNumber);
    window.location.href = `/?page=room${roomNumber}`;
}

// Global function for Enhanced Room Settings Modal
function openEnhancedRoomSettingsModal() {
    EnhancedRoomSettings.open();
}

// Initialize systems when DOM is ready
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Main room JavaScript initializing...');
    
    // Load coordinates from database first
    const coordinatesLoaded = await loadMainRoomCoordinatesFromDatabase();
    
    if (coordinatesLoaded) {
        // Apply door coordinates with delay to ensure CSS is loaded
        setTimeout(() => {
            applyDoorCoordinates();
        }, 100);
        
        // Also apply coordinates when page is fully loaded
        window.addEventListener('load', function() {
            setTimeout(() => {
                applyDoorCoordinates();
            }, 100);
        });
        
        // Reapply coordinates on window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(applyDoorCoordinates, 100);
        });
        
        console.log('Main room JavaScript initialization complete');
    } else {
        console.error('Failed to load main room coordinates from database');
    }
});

// Expose global functions
window.MainRoomConfig = MainRoomConfig;
window.EnhancedRoomSettings = EnhancedRoomSettings;
window.openEnhancedRoomSettingsModal = openEnhancedRoomSettingsModal;
window.enterRoom = enterRoom; 
import { ApiClient } from '../../core/api-client.js';
/**
 * Room Settings Manager
 * Handles room configuration and settings modal functionality
 */

export class RoomSettingsManager {
  constructor() {
    this.modal = null;
    this.notificationManager = this.createNotificationManager();
  }

  /**
   * Open room settings modal
   */
  open() {
    if (this.modal && !this.modal.classList.contains('hidden')) {
      return;
    }

    this.createModal();
    this.loadRoomSettings();
    this.modal.classList.remove('hidden');
    document.body.classList.add('modal-open');
  }

  /**
   * Close room settings modal
   */
  close() {
    if (!this.modal) return;

    this.modal.classList.add('hidden');
    document.body.classList.remove('modal-open');
  }

  /**
   * Create the settings modal
   */
  createModal() {
    if (this.modal) return;

    const modalWrapper = document.createElement('div');
    modalWrapper.className = 'admin-modal-overlay hidden';
    modalWrapper.innerHTML = `
      <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
        <div class="modal-header admin-modal-header">
          <h2>Enhanced Room Settings</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body admin-modal-body">
          <div class="loading-message">
            <p>Loading room settings...</p>
            <div class="loading-spinner"></div>
          </div>
          <div id="roomSettingsContent" class="hidden">
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
        <div class="modal-footer admin-modal-footer">
          <button type="button" class="btn btn-secondary" data-action="close">Cancel</button>
          <button type="button" class="btn btn-primary" data-action="save">Save All Settings</button>
        </div>
      </div>
    `;

    document.body.appendChild(modalWrapper);
    this.modal = modalWrapper;

    this.bindModalEvents();
  }

  /**
   * Bind modal event handlers
   */
  bindModalEvents() {
    // Close button (support legacy .modal-close and new .admin-modal-close)
    const closeBtn = this.modal.querySelector('.admin-modal-close, .modal-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.close());
    }

    // Close action button
    const closeActionBtn = this.modal.querySelector('[data-action="close"]');
    if (closeActionBtn) {
      closeActionBtn.addEventListener('click', () => this.close());
    }

    // Tab switching
    const tabsContainer = this.modal.querySelector('.settings-tabs');
    if (tabsContainer) {
      tabsContainer.addEventListener('click', (e) => {
        const btn = e.target.closest('.tab-button');
        if (!btn) return;

        const tabId = btn.dataset.tab;

        // Update tab buttons
        this.modal.querySelectorAll('.tab-button').forEach((b) => {
          b.classList.toggle('active', b === btn);
        });

        // Update tab panels
        this.modal.querySelectorAll('.tab-panel').forEach((p) => {
          p.classList.toggle('active', p.id === `${tabId}-tab`);
        });
      });
    }

    // Save button
    const saveBtn = this.modal.querySelector('[data-action="save"]');
    if (saveBtn) {
      saveBtn.addEventListener('click', () => this.saveAllSettings());
    }
  }

  /**
   * Load room settings from API
   */
  async loadRoomSettings() {
    try {
      const [roomsData, businessData] = await Promise.all([
        ApiClient.get('/api/room_settings.php', { action: 'get_all', admin_token: 'whimsical_admin_2024' }),
        ApiClient.get('/api/business_settings.php', { action: 'get_by_category', category: 'rooms', admin_token: 'whimsical_admin_2024' })
      ]);

      this.populateRoomsTab(roomsData.rooms || []);
      this.populateDisplayTab(businessData.settings || []);

      this.showContent();
    } catch (error) {
      console.error('Error loading room settings:', error);
      this.showError('Error loading settings. Please try again.');
    }
  }

  /**
   * Populate rooms tab with room data
   * @param {Array} rooms - Array of room objects
   */
  populateRoomsTab(rooms) {
    const roomsList = this.modal.querySelector('#roomsList');
    if (!roomsList) return;

    const productRooms = rooms
      .filter((r) => r.room_number >= 2 && r.room_number <= 6)
      .sort((a, b) => a.display_order - b.display_order);

    roomsList.innerHTML = productRooms
      .map((room) => `
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
      `)
      .join('');
  }

  /**
   * Populate display tab with settings
   * @param {Array} settings - Array of business settings
   */
  populateDisplayTab(settings) {
    const fullScreenSetting = settings.find((s) => s.setting_key === 'main_room_fullscreen');
    const showTitleSetting = settings.find((s) => s.setting_key === 'main_room_show_title');

    const fullScreenCheckbox = this.modal.querySelector('#fullScreenMode');
    const showTitleCheckbox = this.modal.querySelector('#showMainRoomTitle');

    if (fullScreenCheckbox && fullScreenSetting) {
      fullScreenCheckbox.checked = fullScreenSetting.setting_value === 'true';
    }

    if (showTitleCheckbox && showTitleSetting) {
      showTitleCheckbox.checked = showTitleSetting.setting_value === 'true';
    }
  }

  /**
   * Show modal content (hide loading)
   */
  showContent() {
    const loadingMessage = this.modal.querySelector('.loading-message');
    const content = this.modal.querySelector('#roomSettingsContent');

    if (loadingMessage) loadingMessage.classList.add('hidden');
    if (content) content.classList.remove('hidden');
  }

  /**
   * Show error message
   * @param {string} message - Error message
   */
  showError(message) {
    const loadingMessage = this.modal.querySelector('.loading-message');
    if (loadingMessage) {
      loadingMessage.innerHTML = `<p class="text-error">${message}</p>`;
    }
  }

  /**
   * Save all settings
   */
  async saveAllSettings() {
    const saveButton = this.modal.querySelector('.btn-primary');
    if (!saveButton) return;

    const originalText = saveButton.textContent;
    saveButton.textContent = 'Saving...';
    saveButton.disabled = true;

    try {
      // Save room updates
      await this.saveRoomUpdates();

      // Save display settings
      await this.saveDisplaySettings();

      this.notificationManager.showSuccess('All settings saved successfully!');
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } catch (error) {
      console.error('Error saving settings:', error);
      this.notificationManager.showError('Error saving settings. Please try again.');
    } finally {
      saveButton.textContent = originalText;
      saveButton.disabled = false;
    }
  }

  /**
   * Save room updates
   */
  async saveRoomUpdates() {
    const roomInputs = this.modal.querySelectorAll('[data-room]');
    const roomUpdates = {};

    roomInputs.forEach((input) => {
      const roomNumber = input.dataset.room;
      const field = input.dataset.field;
      const value = input.value;

      if (!roomUpdates[roomNumber]) {
        roomUpdates[roomNumber] = { room_number: roomNumber };
      }
      roomUpdates[roomNumber][field] = value;
    });

    for (const roomData of Object.values(roomUpdates)) {
      await ApiClient.post('/api/room_settings.php?action=update_room', {
        admin_token: 'whimsical_admin_2024',
        ...roomData
      });
    }
  }

  /**
   * Save display settings
   */
  async saveDisplaySettings() {
    const fullScreenMode = this.modal.querySelector('#fullScreenMode')?.checked ? 'true' : 'false';
    const showMainRoomTitle = this.modal.querySelector('#showMainRoomTitle')?.checked ? 'true' : 'false';

    await Promise.all([
      ApiClient.post('/api/business_settings.php?action=update_setting', {
        admin_token: 'whimsical_admin_2024',
        key: 'main_room_fullscreen',
        value: fullScreenMode
      }),
      ApiClient.post('/api/business_settings.php?action=update_setting', {
        admin_token: 'whimsical_admin_2024',
        key: 'main_room_show_title',
        value: showMainRoomTitle
      })
    ]);
  }

  /**
   * Create notification manager
   * @returns {Object} Notification manager
   */
  createNotificationManager() {
    return {
      showSuccess: (message) => {
        const notification = document.createElement('div');
        notification.className = 'wf-toast success';
        notification.textContent = message;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 3000);
      },
      showError: (message) => {
        const notification = document.createElement('div');
        notification.className = 'wf-toast error';
        notification.textContent = message;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 3000);
      }
    };
  }
}

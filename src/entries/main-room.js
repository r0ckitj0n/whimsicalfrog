import '../styles/room-main.css';
import { ApiClient } from '../core/api-client.js';

// Configuration for coordinate-based door positioning
const MainRoomConfig = {
  originalImageWidth: 1280,
  originalImageHeight: 896,
  doorCoordinates: [],
};

async function _loadMainRoomCoordinatesFromDatabase() {
  try {
    // Don't try to load coordinates for room_main since it doesn't need them
    console.log('[MainRoom] Skipping coordinate load for room_main - not needed');
    return false;
  } catch (err) {
    console.error('Error loading main room coordinates from database:', err);
    return false;
  }
}

function _applyDoorCoordinates() {
  const container = document.querySelector('#mainRoomPage');
  if (!container) return;
  if (!MainRoomConfig.doorCoordinates || MainRoomConfig.doorCoordinates.length === 0) return;

  const rect = container.getBoundingClientRect();
  const scaleX = rect.width / MainRoomConfig.originalImageWidth;
  const scaleY = rect.height / MainRoomConfig.originalImageHeight;

  const styleEl = ensureCoordsStyleEl();
  let css = '';
  MainRoomConfig.doorCoordinates.forEach((coord) => {
    const door = container.querySelector(coord.selector);
    if (!door) return;
    const scaledTop = coord.top * scaleY;
    const scaledLeft = coord.left * scaleX;
    const scaledWidth = coord.width * scaleX;
    const scaledHeight = coord.height * scaleY;
    door.classList.add('use-door-vars');
    const selector = `#mainRoomPage ${coord.selector}.use-door-vars`;
    css += `${selector}{top:${scaledTop.toFixed(2)}px;left:${scaledLeft.toFixed(2)}px;width:${scaledWidth.toFixed(2)}px;height:${scaledHeight.toFixed(2)}px;}`;
  });
  styleEl.textContent = css;
}

function enterRoom(roomNumber) {
  window.location.href = `/?page=room${roomNumber}`;
}

function setupDoorClicks() {
  document.querySelectorAll('#mainRoomPage .door-area[data-room]').forEach((el) => {
    el.addEventListener('click', () => {
      const n = el.getAttribute('data-room');
      if (n) enterRoom(n);
    });
  });
}

function insertFullscreenLogoutLinkIfNeeded() {
  const container = document.getElementById('mainRoomPage');
  if (!container) return;
  if (!container.classList.contains('fullscreen')) return;

  const logoutLink = document.createElement('a');
  logoutLink.textContent = 'Logout';
  logoutLink.href = '/logout.php';
  logoutLink.className = 'logout-fullscreen-link';
  document.body.appendChild(logoutLink);
}

// EnhancedRoomSettings minimal exposure (kept for compatibility)
const EnhancedRoomSettings = {
  modal: null,
  open() {
    if (this.modal && !this.modal.classList.contains('hidden')) return;
    this.createModal();
    this.loadRoomSettings();
    this.modal.classList.remove('hidden');
    document.body.classList.add('modal-open');
  },
  close() {
    if (!this.modal) return;
    this.modal.classList.add('hidden');
    document.body.classList.remove('modal-open');
  },
  createModal() {
    if (this.modal) return;
    const wrap = document.createElement('div');
    wrap.className = 'admin-modal-overlay hidden';
    wrap.innerHTML = `
      <div class="admin-modal-content modal-wide">
        <div class="admin-modal-header">
          <h2>Enhanced Room Settings</h2>
          <button type="button" class="modal-close">×</button>
        </div>
        <div class="admin-modal-body">
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
        <div class="admin-modal-footer">
          <button type="button" class="btn-secondary" data-action="close">Cancel</button>
          <button type="button" class="btn-primary" data-action="save">Save All Settings</button>
        </div>
      </div>
    `;
    document.body.appendChild(wrap);
    this.modal = wrap;

    // wire buttons
    this.modal.querySelector('.modal-close')?.addEventListener('click', () => this.close());
    this.modal.querySelector('[data-action="close"]')?.addEventListener('click', () => this.close());
    this.modal.querySelector('.settings-tabs')?.addEventListener('click', (e) => {
      const btn = e.target.closest('.tab-button');
      if (!btn) return;
      const tabId = btn.dataset.tab;
      this.modal.querySelectorAll('.tab-button').forEach((b) => b.classList.toggle('active', b === btn));
      this.modal.querySelectorAll('.tab-panel').forEach((p) => p.classList.toggle('active', p.id === `${tabId}-tab`));
    });
    this.modal.querySelector('[data-action="save"]')?.addEventListener('click', () => this.saveAllSettings());
  },
  async loadRoomSettings() {
    try {
      const roomsData = await ApiClient.get('/api/room_settings.php', { action: 'get_all', admin_token: 'whimsical_admin_2024' });
      const businessData = await ApiClient.get('/api/business_settings.php', { action: 'get_by_category', category: 'rooms', admin_token: 'whimsical_admin_2024' });
      this.populateRoomsTab(roomsData.rooms || []);
      this.populateDisplayTab(businessData.settings || []);
      this.modal.querySelector('.loading-message')?.classList.add('hidden');
      this.modal.querySelector('#roomSettingsContent')?.classList.remove('hidden');
    } catch (e) {
      console.error('Error loading room settings:', e);
      const m = this.modal.querySelector('.loading-message');
      if (m) m.innerHTML = '<p class="text-error">Error loading settings. Please try again.</p>';
    }
  },
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
            <input type="text" data-room="${room.room_number}" data-field="room_name" value="${room.room_name || ''}" class="form-input">
          </div>
          <div class="form-group">
            <label>Door Label:</label>
            <input type="text" data-room="${room.room_number}" data-field="door_label" value="${room.door_label || ''}" class="form-input">
          </div>
          <div class="form-group">
            <label>Description:</label>
            <textarea data-room="${room.room_number}" data-field="description" class="form-input" rows="2">${room.description || ''}</textarea>
          </div>
        </div>
      `)
      .join('');
  },
  populateDisplayTab(settings) {
    const fullScreenSetting = settings.find((s) => s.setting_key === 'main_room_fullscreen');
    const showTitleSetting = settings.find((s) => s.setting_key === 'main_room_show_title');
    const full = this.modal.querySelector('#fullScreenMode');
    const show = this.modal.querySelector('#showMainRoomTitle');
    if (full && fullScreenSetting) full.checked = fullScreenSetting.setting_value === 'true';
    if (show && showTitleSetting) show.checked = showTitleSetting.setting_value === 'true';
  },
  async saveAllSettings() {
    const saveButton = this.modal.querySelector('.btn-primary');
    if (!saveButton) return;
    const original = saveButton.textContent;
    saveButton.textContent = 'Saving...';
    saveButton.disabled = true;
    try {
      const roomInputs = this.modal.querySelectorAll('[data-room]');
      const roomUpdates = {};
      roomInputs.forEach((input) => {
        const roomNumber = input.dataset.room;
        const field = input.dataset.field;
        const value = input.value;
        if (!roomUpdates[roomNumber]) roomUpdates[roomNumber] = { room_number: roomNumber };
        roomUpdates[roomNumber][field] = value;
      });
      for (const roomData of Object.values(roomUpdates)) {
        await ApiClient.post('/api/room_settings.php', { admin_token: 'whimsical_admin_2024', action: 'update_room', ...roomData });
      }
      const fullScreenMode = this.modal.querySelector('#fullScreenMode')?.checked ? 'true' : 'false';
      await ApiClient.post('/api/business_settings.php', { admin_token: 'whimsical_admin_2024', action: 'update_setting', key: 'main_room_fullscreen', value: fullScreenMode });
      const showMainRoomTitle = this.modal.querySelector('#showMainRoomTitle')?.checked ? 'true' : 'false';
      await ApiClient.post('/api/business_settings.php', { admin_token: 'whimsical_admin_2024', action: 'update_setting', key: 'main_room_show_title', value: showMainRoomTitle });
      // success toast
      const n = document.createElement('div');
      n.className = 'wf-toast success';
      n.textContent = 'All settings saved successfully!';
      document.body.appendChild(n);
      setTimeout(() => n.remove(), 3000);
      setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
      console.error('Error saving settings:', e);
      const n = document.createElement('div');
      n.className = 'wf-toast error';
      n.textContent = 'Error saving settings. Please try again.';
      document.body.appendChild(n);
      setTimeout(() => n.remove(), 3000);
    } finally {
      saveButton.textContent = original;
      saveButton.disabled = false;
    }
  },
};

function boot() {
  setupDoorClicks();
  insertFullscreenLogoutLinkIfNeeded();
  // No need to load coordinates for room_main
  console.log('[MainRoom] Skipping coordinate setup for room_main');
}

document.addEventListener('DOMContentLoaded', boot);

// Back-compat globals (not used by templates after refactor)
window.MainRoomConfig = MainRoomConfig;
window.EnhancedRoomSettings = EnhancedRoomSettings;
window.enterRoom = enterRoom;
window.openEnhancedRoomSettingsModal = () => EnhancedRoomSettings.open();

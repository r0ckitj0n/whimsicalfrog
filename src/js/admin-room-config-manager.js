// Admin Room Config Manager module
// Migrates inline JS from admin/room_config_manager.php to a Vite-managed module

import { ApiClient } from '../core/api-client.js';

(function initRoomConfigManager() {
  let currentRoomConfig = {};

  const byId = (id) => document.getElementById(id);
  const _qs = (sel) => document.querySelector(sel);

  function populateForm(config) {
    try { console.log('[RoomConfig] populateForm called with config:', config); } catch (_) {}
    Object.keys(config || {}).forEach((key) => {
      const el = document.querySelector(`[name="${key}"]`);
      if (!el) return;
      if (el.type === 'checkbox') {
        el.checked = !!config[key];
      } else {
        el.value = config[key];
      }
    });
  }

  function renderCurrentConfig() {
    const tgt = byId('roomConfigContainer');
    if (!tgt) return;
    const cfg = currentRoomConfig || {};
    const rows = Object.keys(cfg).sort().map((k) => {
      let v = cfg[k];
      if (typeof v === 'boolean') v = v ? 'true' : 'false';
      return `<tr><td class="px-3 py-1 text-gray-600">${k}</td><td class="px-3 py-1 font-mono">${v}</td></tr>`;
    }).join('');
    tgt.innerHTML = `
      <div class="border rounded-md overflow-hidden">
        <div class="px-3 py-2 bg-gray-50 border-b text-sm font-semibold text-gray-700">Current Configuration</div>
        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <tbody>${rows || '<tr><td class="px-3 py-2 text-gray-500">No configuration loaded.<\/td></tr>'}</tbody>
          </table>
        </div>
      </div>`;
  }

  function resetForm() {
    const form = byId('roomConfigForm');
    if (form) form.reset();

    const defaults = {
      show_delay: 50,
      hide_delay: 150,
      max_width: 450,
      min_width: 280,
      max_quantity: 999,
      min_quantity: 1,
      debounce_time: 50,
      popup_animation: 'fade',
      modal_animation: 'scale',
    };

    populateForm(defaults);
  }

  async function loadRoomConfig() {
    const roomSelect = byId('roomSelect');
    const container = byId('configFormContainer');
    const roomNumber = roomSelect ? roomSelect.value : '';
    try { console.log('[RoomConfig] loadRoomConfig room:', roomNumber); } catch (_) {}
    if (!roomNumber) {
      if (container && container.classList) container.classList.add('hidden');
      return;
    }

    try {
      const data = await ApiClient.get('/api/room_config.php?action=get', { room: roomNumber });
      if (data && data.success) {
        currentRoomConfig = data.config || {};
        populateForm(currentRoomConfig);
        if (container && container.classList) container.classList.remove('hidden');
        const roomNumberEl = byId('roomNumber');
        if (roomNumberEl) roomNumberEl.value = roomNumber;
        showMessage('Loaded configuration for room ' + roomNumber, 'success');
        try { console.log('[RoomConfig] load success for room', roomNumber, currentRoomConfig); } catch (_) {}
        renderCurrentConfig();
      } else {
        showMessage('Error loading room configuration: ' + (data.message || 'Unknown error'), 'error');
        try { console.warn('[RoomConfig] load failed payload:', data); } catch (_) {}
      }
    } catch (err) {
      console.error('Error loading room config:', err);
      showMessage('Failed to load room configuration', 'error');
    }
  }

  function showMessage(message, type) {
    const container = byId('messageContainer');
    if (!container) return;
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    container.innerHTML = `
      <div class="${alertClass}">
        ${message}
      </div>`;
    setTimeout(() => {
      container.innerHTML = '';
    }, 5000);
  }

  async function onSubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    if (!(form instanceof HTMLFormElement)) return;

    const formData = new FormData(form);
    const config = {};
    for (const [key, value] of formData.entries()) {
      config[key] = value;
    }
    form.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
      config[cb.name] = cb.checked;
    });

    try {
      const data = await ApiClient.post('/api/room_config.php', {
        action: 'save',
        room: config.room_number,
        config,
      });
      if (data.success) {
        showMessage('Room configuration saved successfully!', 'success');
        currentRoomConfig = config;
        renderCurrentConfig();
      } else {
        showMessage('Error saving configuration: ' + (data.message || 'Unknown error'), 'error');
      }
    } catch (err) {
      console.error('Error saving room config:', err);
      showMessage('Failed to save room configuration', 'error');
    }
  }

  function onClick(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-action');
    if (action === 'resetForm') {
      e.preventDefault();
      resetForm();
    }
  }

  function onChange(e) {
    const sel = e.target.closest('#roomSelect');
    if (sel) loadRoomConfig();
  }

  function run() {
    const form = byId('roomConfigForm');
    if (form) form.addEventListener('submit', onSubmit);

    document.addEventListener('click', onClick);
    document.addEventListener('change', onChange);
    // Explicit listener on the room select to ensure change is detected reliably
    const roomSel = byId('roomSelect');
    if (roomSel) {
      roomSel.addEventListener('change', function() {
        try { console.log('[RoomConfig] #roomSelect change ->', this.value); } catch (_) {}
        loadRoomConfig();
      });
    }

    // Initial state
    const sel = byId('roomSelect');
    if (sel && sel.value) {
      try { console.log('[RoomConfig] initial room value:', sel.value); } catch (_) {}
      loadRoomConfig();
    } else {
      const container = byId('configFormContainer');
      if (container && container.classList) container.classList.add('hidden');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
})();

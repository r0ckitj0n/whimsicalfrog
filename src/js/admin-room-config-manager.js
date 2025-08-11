// Admin Room Config Manager module
// Migrates inline JS from admin/room_config_manager.php to a Vite-managed module

(function initRoomConfigManager() {
  let currentRoomConfig = {};

  const byId = (id) => document.getElementById(id);
  const qs = (sel) => document.querySelector(sel);

  function populateForm(config) {
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
    if (!roomNumber) {
      if (container) container.style.display = 'none';
      return;
    }

    try {
      const res = await fetch(`../api/room_config.php?action=get&room=${encodeURIComponent(roomNumber)}`);
      const data = await res.json();
      if (data.success) {
        currentRoomConfig = data.config || {};
        populateForm(currentRoomConfig);
        if (container) container.style.display = 'block';
        const roomNumberEl = byId('roomNumber');
        if (roomNumberEl) roomNumberEl.value = roomNumber;
      } else {
        showMessage('Error loading room configuration: ' + (data.message || 'Unknown error'), 'error');
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
      const res = await fetch('../api/room_config.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save',
          room: config.room_number,
          config,
        }),
      });
      const data = await res.json();
      if (data.success) {
        showMessage('Room configuration saved successfully!', 'success');
        currentRoomConfig = config;
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

    // Initial state
    const sel = byId('roomSelect');
    if (sel && sel.value) {
      loadRoomConfig();
    } else {
      const container = byId('configFormContainer');
      if (container) container.style.display = 'none';
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
})();

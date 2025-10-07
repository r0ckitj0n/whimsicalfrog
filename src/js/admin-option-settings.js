// src/js/admin-option-settings.js
// Lightweight controller for Option Cascade & Grouping panel

(function () {
  'use strict';

  const { ApiClient } = window || {};

  async function apiGet(url, params) {
    return ApiClient.get(url, params);
  }
  async function apiPost(url, data) {
    return ApiClient.post(url, data);
  }

  function readPanelValues(panel) {
    const order = [
      panel.querySelector('#cascadeOrder1')?.value || 'gender',
      panel.querySelector('#cascadeOrder2')?.value || 'size',
      panel.querySelector('#cascadeOrder3')?.value || 'color',
    ];
    // De-duplicate while preserving order
    const seen = new Set();
    const cascade_order = order.filter((d) => {
      if (!d || seen.has(d)) return false;
      seen.add(d);
      return true;
    });
    const enabled_dimensions = [
      panel.querySelector('#dimGender')?.checked ? 'gender' : null,
      panel.querySelector('#dimSize')?.checked ? 'size' : null,
      panel.querySelector('#dimColor')?.checked ? 'color' : null,
    ].filter(Boolean);
    let grouping_rules = {};
    const raw = (panel.querySelector('#groupingRules')?.value || '').trim();
    if (raw) {
      try { grouping_rules = JSON.parse(raw); } catch (_) {}
    }
    return { cascade_order, enabled_dimensions, grouping_rules };
  }

  function applyPanelValues(panel, settings) {
    const order = Array.isArray(settings?.cascade_order) ? settings.cascade_order.slice(0, 3) : ['gender', 'size', 'color'];
    const enabled = Array.isArray(settings?.enabled_dimensions) ? settings.enabled_dimensions : ['gender','size','color'];
    const grouping = settings?.grouping_rules || {};

    const selects = [panel.querySelector('#cascadeOrder1'), panel.querySelector('#cascadeOrder2'), panel.querySelector('#cascadeOrder3')];
    selects.forEach((sel, i) => { if (sel) sel.value = order[i] || 'gender'; });

    const setChecked = (id, key) => {
      const el = panel.querySelector(id);
      if (el) el.checked = enabled.includes(key);
    };
    setChecked('#dimGender', 'gender');
    setChecked('#dimSize', 'size');
    setChecked('#dimColor', 'color');

    const ta = panel.querySelector('#groupingRules');
    if (ta) ta.value = Object.keys(grouping).length ? JSON.stringify(grouping, null, 2) : '';
  }

  async function loadOptionSettings(panel) {
    const sku = panel?.dataset?.sku || '';
    const status = panel.querySelector('#optionSettingsStatus');
    try {
      if (status) status.textContent = 'Loading settings…';
      const data = await apiGet('/api/item_options.php', { action: 'get_settings', item_sku: sku, wf_dev_admin: 1 });
      applyPanelValues(panel, data?.settings || {});
      if (status) status.textContent = 'Settings loaded';
    } catch (e) {
      if (status) status.textContent = 'Failed to load settings';
      console.warn('[OptionSettings] load failed', e);
    }
  }

  async function saveOptionSettings(panel) {
    const sku = panel?.dataset?.sku || '';
    const status = panel.querySelector('#optionSettingsStatus');
    try {
      if (status) status.textContent = 'Saving…';
      const payload = {
        action: 'update_settings',
        item_sku: sku,
        ...readPanelValues(panel),
      };
      const data = await apiPost('/api/item_options.php', payload);
      if (data?.success) {
        if (status) status.textContent = 'Saved';
      } else {
        if (status) status.textContent = 'Save failed';
      }
    } catch (e) {
      if (status) status.textContent = 'Save failed';
      console.warn('[OptionSettings] save failed', e);
    }
  }

  function bind(panel) {
    const reloadBtn = panel.querySelector('[data-action="reload-option-settings"]');
    const saveBtn = panel.querySelector('[data-action="save-option-settings"]');
    if (reloadBtn && !reloadBtn._bound) {
      reloadBtn.addEventListener('click', () => loadOptionSettings(panel));
      reloadBtn._bound = true;
    }
    if (saveBtn && !saveBtn._bound) {
      saveBtn.addEventListener('click', () => saveOptionSettings(panel));
      saveBtn._bound = true;
    }
  }

  function init() {
    const panel = document.getElementById('optionCascadePanel');
    if (!panel) return;
    bind(panel);
    loadOptionSettings(panel);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

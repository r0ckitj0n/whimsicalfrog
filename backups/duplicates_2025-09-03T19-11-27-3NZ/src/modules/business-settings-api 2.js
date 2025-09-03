// Business Settings API helpers
// Uses ApiClient to interact with /api/business_settings.php

import { ApiClient } from '../core/apiClient.js';

function toQuery(params) {
  const usp = new URLSearchParams();
  Object.entries(params || {}).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') usp.append(k, v);
  });
  return usp.toString();
}

export const BusinessSettingsAPI = {
  async getByCategory(category) {
    if (!category) throw new Error('category is required');
    // Assuming api/business_settings.php supports action=get&category=...
    const qs = toQuery({ action: 'get', category });
    return ApiClient.get(`/api/business_settings.php?${qs}`);
  },

  async upsert(category, settings) {
    if (!category) throw new Error('category is required');
    if (!settings || typeof settings !== 'object') throw new Error('settings map is required');
    // upsertSettings() accepts JSON with { action: 'upsert', category, settings }
    return ApiClient.post('business_settings.php', {
      action: 'upsert',
      category,
      settings,
    });
  },

  async get(keys = []) {
    // Optional: fetch specific keys
    const qs = toQuery({ action: 'get', keys: Array.isArray(keys) ? keys.join(',') : String(keys || '') });
    return ApiClient.get(`/api/business_settings.php?${qs}`);
  },
};

export default BusinessSettingsAPI;

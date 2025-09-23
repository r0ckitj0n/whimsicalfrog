// Business Settings API helpers
// Uses ApiClient to interact with /api/business_settings.php

import { ApiClient } from '../core/api-client.js';

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
    // Backend expects get_by_category
    const qs = toQuery({ action: 'get_by_category', category });
    return ApiClient.get(`/api/business_settings.php?${qs}`);
  },

  async upsert(category, settings) {
    if (!category) throw new Error('category is required');
    if (!settings || typeof settings !== 'object') throw new Error('settings map is required');
    // upsertSettings() accepts JSON with { action: 'upsert_settings', category, settings }
    return ApiClient.post('business_settings.php', {
      action: 'upsert_settings',
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

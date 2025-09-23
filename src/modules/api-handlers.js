// Admin Settings - API Handlers
// Handles all API interactions and data fetching

import { ApiClient } from '../core/api-client.js';

export const APIHandlers = {
  // Fetch business settings
  async fetchBusinessSettings() {
    try {
      const response = await ApiClient.get('/api/business_settings.php?action=get');
      return response.data || {};
    } catch (error) {
      console.error('Failed to fetch business settings:', error);
      return {};
    }
  },

  // Fetch CSS rules
  async fetchCSSRules() {
    try {
      const response = await ApiClient.get('/api/css_rules.php?action=list');
      return response.data || {};
    } catch (error) {
      console.error('Failed to fetch CSS rules:', error);
      return {};
    }
  },

  // Fetch global attributes (genders, sizes, colors)
  async fetchGlobalAttributes() {
    try {
      const [gendersRes, sizesRes, colorsRes] = await Promise.all([
        fetch('/api/global_color_size_management.php?action=get_global_genders&admin_token=whimsical_admin_2024'),
        fetch('/api/global_color_size_management.php?action=get_global_sizes&admin_token=whimsical_admin_2024'),
        fetch('/api/global_color_size_management.php?action=get_global_colors&admin_token=whimsical_admin_2024')
      ]);

      const [genders, sizes, colors] = await Promise.all([
        gendersRes.json(),
        sizesRes.json(),
        colorsRes.json()
      ]);

      return {
        genders: genders.success ? genders.genders || [] : [],
        sizes: sizes.success ? sizes.sizes || [] : [],
        colors: colors.success ? colors.colors || [] : []
      };
    } catch (error) {
      console.error('Failed to fetch global attributes:', error);
      return { genders: [], sizes: [], colors: [] };
    }
  },

  // Save business settings
  async saveBusinessSettings(data) {
    try {
      const response = await ApiClient.post('/api/business_settings.php', {
        action: 'upsert_settings',
        category: 'business',
        settings: data
      });
      return response;
    } catch (error) {
      console.error('Failed to save business settings:', error);
      throw error;
    }
  },

  // Save AI settings
  async saveAISettings(data) {
    try {
      const response = await ApiClient.post('/api/ai_settings.php', {
        action: 'save_settings',
        settings: data
      });
      return response;
    } catch (error) {
      console.error('Failed to save AI settings:', error);
      throw error;
    }
  },

  // Save CSS rule
  async saveCSSRule(data) {
    try {
      const response = await ApiClient.post('/api/css_rules.php', {
        action: 'upsert',
        ...data
      });
      return response;
    } catch (error) {
      console.error('Failed to save CSS rule:', error);
      throw error;
    }
  },

  // Test email configuration
  async testEmailSettings(config) {
    try {
      const response = await ApiClient.post('/api/email_test.php', {
        action: 'test',
        config
      });
      return response;
    } catch (error) {
      console.error('Failed to test email settings:', error);
      throw error;
    }
  }
};

// Make API functions available globally for backward compatibility
if (typeof window !== 'undefined') {
  window.fetchBusinessSettings = () => APIHandlers.fetchBusinessSettings();
  window.fetchGlobalAttributes = () => APIHandlers.fetchGlobalAttributes();
  window.saveBusinessSettings = (data) => APIHandlers.saveBusinessSettings(data);
  window.saveAISettings = (data) => APIHandlers.saveAISettings(data);
  window.testEmailSettings = (config) => APIHandlers.testEmailSettings(config);
}

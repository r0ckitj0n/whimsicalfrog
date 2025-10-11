// Admin Settings - Initialization
// Handles system initialization and setup

import { ApiClient } from '../core/api-client.js';

export const Initialization = {
  // Main initialization function
  init() {
    console.log('[AdminSettings] Initializing system...');

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        this.onDOMReady();
      }, { once: true });
    } else {
      this.onDOMReady();
    }
  },

  onDOMReady() {
    console.log('[AdminSettings] DOM ready, initializing modules...');

    // Initialize all sub-systems
    this.initSSLHandlers();
    this.initBrandPalette();
    this.initGlobalAttributes();
    this.initCSSRules();
    this.initBusinessSettings();

    console.log('[AdminSettings] Initialization complete');
  },

  // Initialize SSL checkbox handlers
  initSSLHandlers(root = document) {
    try {
      const sslCheckbox = root.querySelector ? root.querySelector('#sslEnabled') : null;
      const sslOptions = root.querySelector ? root.querySelector('#sslOptions') : null;

      if (sslCheckbox && sslOptions) {
        const updateVisibility = () => {
          sslOptions.classList.toggle('hidden', !sslCheckbox.checked);
        };

        // Initial state
        updateVisibility();

        // Listen for changes
        sslCheckbox.addEventListener('change', updateVisibility);
      }
    } catch (error) {
      console.error('Failed to initialize SSL handlers:', error);
    }
  },

  // Initialize brand palette functionality
  initBrandPalette() {
    if (typeof window !== 'undefined') {
      window.brandPalette = window.brandPalette || [];
      window.renderBrandPalette = this.renderBrandPalette.bind(this);
      window.collectBusinessInfo = this.collectBusinessInfo.bind(this);
      window.applyBusinessCssToRoot = this.applyBusinessCssToRoot.bind(this);
    }
  },

  // Initialize global attributes (genders, sizes, colors)
  async initGlobalAttributes() {
    try {
      if (typeof window !== 'undefined') {
        window.globalAttributes = { genders: [], sizes: [], colors: [] };
        window.fetchGlobalAttributes = this.fetchGlobalAttributes.bind(this);
        window.addAttribute = this.addAttribute.bind(this);
        window.editAttribute = this.editAttribute.bind(this);
        window.deleteAttribute = this.deleteAttribute.bind(this);
        window.populateAttributesModal = this.populateAttributesModal.bind(this);
        window.initAttributesModal = this.initAttributesModal.bind(this);
      }

      // Fetch initial data
      await this.fetchGlobalAttributes();
    } catch (error) {
      console.error('Failed to initialize global attributes:', error);
    }
  },

  // Initialize CSS rules system
  async initCSSRules() {
    try {
      if (typeof window !== 'undefined') {
        window.updateCSSRule = this.updateCSSRule.bind(this);
        window.updateCSSVariable = this.updateCSSVariable.bind(this);
        window.updateSectionWidth = this.updateSectionWidth.bind(this);
      }
    } catch (error) {
      console.error('Failed to initialize CSS rules:', error);
    }
  },

  // Initialize business settings
  async initBusinessSettings() {
    try {
      if (typeof window !== 'undefined') {
        window.loadBusinessInfo = this.loadBusinessInfo.bind(this);
        window.applyBusinessInfo = this.applyBusinessInfo.bind(this);
        window.saveBusinessInfo = this.saveBusinessInfo.bind(this);
      }

      // Load initial business info
      await this.loadBusinessInfo();
    } catch (error) {
      console.error('Failed to initialize business settings:', error);
    }
  },

  // Utility methods (these would contain the actual implementation)
  renderBrandPalette() {
    console.log('renderBrandPalette called');
    // Implementation would populate brand palette UI
  },

  collectBusinessInfo() {
    console.log('collectBusinessInfo called');
    // Collect form data and return as object
    const form = document.getElementById('businessInfoForm');
    if (!form) return {};

    const formData = new FormData(form);
    return Object.fromEntries(formData.entries());
  },

  applyBusinessCssToRoot() {
    console.log('applyBusinessCssToRoot called');
    // Apply business branding to CSS root variables
  },

  async fetchGlobalAttributes() {
    try {
      console.log('fetchGlobalAttributes called');
      const [genders, sizes, colors] = await Promise.all([
        ApiClient.get('/api/global_color_size_management.php?action=get_global_genders&admin_token=whimsical_admin_2024'),
        ApiClient.get('/api/global_color_size_management.php?action=get_global_sizes&admin_token=whimsical_admin_2024'),
        ApiClient.get('/api/global_color_size_management.php?action=get_global_colors&admin_token=whimsical_admin_2024')
      ]);

      if (typeof window !== 'undefined') {
        window.globalAttributes = {
          genders: genders.success ? genders.genders || [] : [],
          sizes: sizes.success ? sizes.sizes || [] : [],
          colors: colors.success ? colors.colors || [] : []
        };
      }

      return window.globalAttributes;
    } catch (error) {
      console.error('Failed to fetch global attributes:', error);
      if (typeof window !== 'undefined') {
        window.globalAttributes = { genders: [], sizes: [], colors: [] };
      }
      return { genders: [], sizes: [], colors: [] };
    }
  },

  addAttribute(type, name, code = null) {
    console.log('addAttribute called:', type, name, code);
    // Implementation would add attribute to database and refresh UI
  },

  editAttribute(type, id) {
    console.log('editAttribute called:', type, id);
    // Implementation would populate edit form
  },

  deleteAttribute(type, id) {
    console.log('deleteAttribute called:', type, id);
    // Implementation would remove attribute from database and refresh UI
  },

  populateAttributesModal(modal) {
    console.log('populateAttributesModal called:', modal);
    // Implementation would populate the attributes modal with current data
    if (typeof window !== 'undefined' && window.globalAttributes) {
      const { genders, sizes, colors } = window.globalAttributes;

      // Populate gender list
      const genderList = document.getElementById('attrListGender');
      if (genderList) {
        genderList.innerHTML = genders.map(gender => `
          <li class="flex justify-between items-center py-1">
            <span>${gender.name}</span>
            <button class="text-red-500 text-xs" data-action="attr-delete" data-type="gender" data-id="${gender.id}">Delete</button>
          </li>
        `).join('');
      }

      // Populate size list
      const sizeList = document.getElementById('attrListSize');
      if (sizeList) {
        sizeList.innerHTML = sizes.map(size => `
          <li class="flex justify-between items-center py-1">
            <span>${size.name} (${size.code})</span>
            <button class="text-red-500 text-xs" data-action="attr-delete" data-type="size" data-id="${size.id}">Delete</button>
          </li>
        `).join('');
      }

      // Populate color list
      const colorList = document.getElementById('attrListColor');
      if (colorList) {
        colorList.innerHTML = colors.map(color => `
          <li class="flex justify-between items-center py-1">
            <span>${color.name}</span>
            <button class="text-red-500 text-xs" data-action="attr-delete" data-type="color" data-id="${color.id}">Delete</button>
          </li>
        `).join('');
      }
    }
  },

  initAttributesModal(modal) {
    console.log('initAttributesModal called:', modal);
    // Initialize attributes modal when it opens
    this.populateAttributesModal(modal);
  },

  updateCSSRule(input) {
    console.log('updateCSSRule called:', input);
    // Real-time CSS rule updates
  },

  updateCSSVariable(input) {
    console.log('updateCSSVariable called:', input);
    // Real-time CSS variable updates
  },

  updateSectionWidth(sectionKey, value) {
    console.log('updateSectionWidth called:', sectionKey, value);
    // Update section width in dashboard configuration
  },

  async loadBusinessInfo() {
    try {
      console.log('loadBusinessInfo called');
      // Load business information from API (backend supports get_business_info)
      const data = await ApiClient.get('/api/business_settings.php?action=get_business_info');

      const settings = (data && (data.data || data)) || null;
      if (settings) this.applyBusinessInfo(settings);
    } catch (error) {
      console.error('Failed to load business info:', error);
    }
  },

  applyBusinessInfo(settings) {
    console.log('applyBusinessInfo called:', settings);
    // Apply business settings to form fields
    if (!settings) return;

    const fieldMappings = {
      'bizName': 'business_name',
      'bizEmail': 'business_email',
      'bizWebsite': 'website',
      'bizPhone': 'phone',
      'bizAddress': 'address',
      'bizCity': 'city',
      'bizState': 'state',
      'bizPostal': 'postal_code',
      'bizCountry': 'country'
    };

    Object.entries(fieldMappings).forEach(([fieldId, settingKey]) => {
      const element = document.getElementById(fieldId);
      if (element && settings[settingKey]) {
        element.value = settings[settingKey];
      }
    });
  },

  async saveBusinessInfo() {
    try {
      console.log('saveBusinessInfo called');
      const businessData = this.collectBusinessInfo();
      const result = await ApiClient.post('/api/business_settings.php?action=upsert_settings', {
        category: 'business',
        settings: businessData
      });
      if (result.success) {
        console.log('Business info saved successfully');
      } else {
        console.error('Failed to save business info:', result.error);
      }
    } catch (error) {
      console.error('Error saving business info:', error);
    }
  }
};

// Auto-initialize
Initialization.init();

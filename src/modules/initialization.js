// Admin Settings - Initialization
// Handles system initialization and setup

import { ApiClient } from '../core/api-client.js';

export const Initialization = {
  // Lazy-load Business Settings API to avoid mixed static/dynamic imports
  async getBusinessSettingsAPI() {
    const mod = await import('../modules/business-settings-api.js');
    return mod?.default || mod?.BusinessSettingsAPI;
  },
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

      await this.loadBusinessInfo();
    } catch (error) {
      console.error('Failed to initialize business settings:', error);
    }
  },

  // Utility methods (these would contain the actual implementation)
  renderBrandPalette() {
    try {
      const container = document.getElementById('brandPaletteContainer');
      if (!container) return;
      const palette = (window.brandPalette || []).slice(0);
      container.innerHTML = palette.map((p, i) => (
        `<div class="inline-flex items-center gap-2 mr-2 mb-2"><span class="w-6 h-6 rounded border" style="background:${p.hex}"></span><span class="text-xs">${p.name}</span><button type="button" class="btn btn-secondary btn-xs" data-action="business-palette-delete" data-index="${i}">Remove</button></div>`
      )).join('');
    } catch (_) {}
  },

  collectBusinessInfo() {
    try {
      const getVal = (id, d='') => { const el = document.getElementById(id); return el ? (el.value || d) : d; };
      return {
        business_brand_primary: getVal('brandPrimary'),
        business_brand_secondary: getVal('brandSecondary'),
        business_brand_accent: getVal('brandAccent'),
        business_brand_background: getVal('brandBackground'),
        business_brand_text: getVal('brandText'),
        business_public_header_bg: getVal('publicHeaderBg'),
        business_public_header_text: getVal('publicHeaderText'),
        business_public_modal_bg: getVal('publicModalBg'),
        business_public_modal_text: getVal('publicModalText'),
        business_public_page_bg: getVal('publicPageBg'),
        business_public_page_text: getVal('publicPageText'),
        business_brand_font_primary: getVal('brandFontPrimary'),
        business_brand_font_secondary: getVal('brandFontSecondary'),
        business_css_vars: (document.getElementById('customCssVars')?.value || ''),
      };
    } catch (_) { return {}; }
  },

  applyBusinessCssToRoot(data) {
    try {
      const d = data || this.collectBusinessInfo();
      const ensureStyleEl = () => {
        let el = document.getElementById('wf-brand-live');
        if (!el) { el = document.createElement('style'); el.id = 'wf-brand-live'; document.head.appendChild(el); }
        return el;
      };
      const cssSafe = (v) => (v == null ? '' : String(v));
      const s = ensureStyleEl();
      const css = [
        ':root{',
        `--brand-primary:${cssSafe(d.business_brand_primary || '')};`,
        `--brand-secondary:${cssSafe(d.business_brand_secondary || '')};`,
        `--brand-accent:${cssSafe(d.business_brand_accent || '')};`,
        `--brand-bg:${cssSafe(d.business_brand_background || '')};`,
        `--brand-text:${cssSafe(d.business_brand_text || '')};`,
        `--brand-font-primary:${cssSafe(d.business_brand_font_primary || '')};`,
        `--brand-font-secondary:${cssSafe(d.business_brand_font_secondary || '')};`,
        d.business_public_header_bg ? `--public-header-bg:${cssSafe(d.business_public_header_bg)};` : '',
        d.business_public_header_text ? `--public-header-text:${cssSafe(d.business_public_header_text)};` : '',
        d.business_public_modal_bg ? `--public-modal-bg:${cssSafe(d.business_public_modal_bg)};` : '',
        d.business_public_modal_text ? `--public-modal-text:${cssSafe(d.business_public_modal_text)};` : '',
        d.business_public_page_bg ? `--site-page-bg:${cssSafe(d.business_public_page_bg)};` : '',
        d.business_public_page_text ? `--site-page-text:${cssSafe(d.business_public_page_text)};` : '',
        '}',
      ].join('');
      s.textContent = css;
      const fp = cssSafe(d.business_brand_font_primary || '');
      const fs = cssSafe(d.business_brand_font_secondary || '');
      let fpStyle = document.getElementById('wf-brand-font-preview');
      if (!fpStyle) { fpStyle = document.createElement('style'); fpStyle.id = 'wf-brand-font-preview'; document.head.appendChild(fpStyle); }
      fpStyle.textContent = [
        fp ? `#brandFontPrimaryLabel{font-family:${fp}}` : '',
        fs ? `#brandFontSecondaryLabel{font-family:${fs}}` : '',
      ].filter(Boolean).join('\n');
    } catch (_) {}
  },

  async loadBusinessInfo() {
    try {
      const BusinessSettingsAPI = await this.getBusinessSettingsAPI();
      const info = await BusinessSettingsAPI.getBusinessInfo();
      if (info && info.success) {
        this.applyBusinessInfo(info);
        return info;
      }
    } catch (_) {}
    return {};
  },

  applyBusinessInfo(info) {
    try {
      const d = info && info.data ? info.data : info;
      const set = (id, v) => { const el = document.getElementById(id); if (el && v != null && v !== '') { try { el.value = v; } catch(_) {} } };
      set('brandPrimary', d.business_brand_primary || '');
      set('brandSecondary', d.business_brand_secondary || '');
      set('brandAccent', d.business_brand_accent || '');
      set('brandBackground', d.business_brand_background || '');
      set('brandText', d.business_brand_text || '');
      set('publicHeaderBg', d.business_public_header_bg || '');
      set('publicHeaderText', d.business_public_header_text || '');
      set('publicModalBg', d.business_public_modal_bg || '');
      set('publicModalText', d.business_public_modal_text || '');
      set('publicPageBg', d.business_public_page_bg || '');
      set('publicPageText', d.business_public_page_text || '');
      set('brandFontPrimary', d.business_brand_font_primary || '');
      set('brandFontSecondary', d.business_brand_font_secondary || '');
      const cv = document.getElementById('customCssVars'); if (cv && d.business_css_vars) cv.value = d.business_css_vars;
      this.applyBusinessCssToRoot({
        business_brand_primary: d.business_brand_primary,
        business_brand_secondary: d.business_brand_secondary,
        business_brand_accent: d.business_brand_accent,
        business_brand_background: d.business_brand_background,
        business_brand_text: d.business_brand_text,
        business_public_header_bg: d.business_public_header_bg,
        business_public_header_text: d.business_public_header_text,
        business_public_modal_bg: d.business_public_modal_bg,
        business_public_modal_text: d.business_public_modal_text,
        business_public_page_bg: d.business_public_page_bg,
        business_public_page_text: d.business_public_page_text,
        business_brand_font_primary: d.business_brand_font_primary,
        business_brand_font_secondary: d.business_brand_font_secondary,
      });
    } catch (_) {}
  },

  async saveBusinessInfo(info) {
    try {
      const data = info && Object.keys(info).length ? info : this.collectBusinessInfo();
      const BusinessSettingsAPI = await this.getBusinessSettingsAPI();
      const res = await BusinessSettingsAPI.upsert('business', data);
      if (res && res.success) {
        this.applyBusinessCssToRoot(data);
        return res;
      }
      return res || { success: false };
    } catch (e) {
      return { success: false, error: e && e.message ? e.message : 'save failed' };
    }
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
            <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="attr-delete" data-type="gender" data-id="${gender.id}" aria-label="Delete" title="Delete"></button>
          </li>
        `).join('');
      }

      // Populate size list
      const sizeList = document.getElementById('attrListSize');
      if (sizeList) {
        sizeList.innerHTML = sizes.map(size => `
          <li class="flex justify-between items-center py-1">
            <span>${size.name} (${size.code})</span>
            <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="attr-delete" data-type="size" data-id="${size.id}" aria-label="Delete" title="Delete"></button>
          </li>
        `).join('');
      }

      // Populate color list
      const colorList = document.getElementById('attrListColor');
      if (colorList) {
        colorList.innerHTML = colors.map(color => `
          <li class="flex justify-between items-center py-1">
            <span>${color.name}</span>
            <button class="admin-action-button btn btn-xs btn-danger btn-icon btn-icon--delete" data-action="attr-delete" data-type="color" data-id="${color.id}" aria-label="Delete" title="Delete"></button>
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
};

// Auto-initialize
Initialization.init();

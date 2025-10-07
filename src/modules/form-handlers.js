// Admin Settings - Form Handlers
// Handles form submissions and data processing

import { ApiClient } from '../core/api-client.js';

export const FormHandlers = {
  // Handle form submissions with data-action attributes
  init() {
    document.addEventListener('submit', (e) => {
      const form = e.target;
      if (!form || !form.matches('form[data-action]')) return;

      const action = form.getAttribute('data-action');
      if (!action) return;

      e.preventDefault();
      this.handleFormSubmission(form, action);
    });

    // Handle individual form field changes
    document.addEventListener('change', (e) => {
      const input = e.target;
      if (!input || !input.matches('[data-action][data-field]')) return;

      const action = input.getAttribute('data-action');
      if (!action.includes('field-change')) return;

      this.handleFieldChange(input);
    });

    // Handle button clicks within forms
    document.addEventListener('click', (e) => {
      const button = e.target.closest('[data-action][data-form-action]');
      if (!button) return;

      const action = button.getAttribute('data-action');
      const formAction = button.getAttribute('data-form-action');

      if (formAction === 'submit') {
        e.preventDefault();
        const form = button.closest('form');
        if (form) {
          this.handleFormSubmission(form, action);
        }
      }
    });
  },

  async handleFormSubmission(form, action) {
    try {
      const formData = new FormData(form);
      const data = Object.fromEntries(formData.entries());

      console.log('Form submission:', action, data);

      switch (action) {
        case 'save-dashboard-config':
          await this.saveDashboardConfig(data);
          break;
        case 'business-save':
          await this.saveBusinessInfo(data);
          break;
        case 'business-save-branding':
          await this.saveBusinessBranding(data);
          break;
        case 'square-save-settings':
          await this.saveSquareSettings(data);
          break;
        case 'secrets-save':
          await this.saveSecrets(data);
          break;
        case 'attr-save-order':
          await this.saveAttributeOrder(data);
          break;
        case 'email-send-test':
          await this.sendTestEmail(data);
          break;
        case 'save-ai-settings':
          await this.saveAISettings(data);
          break;
        case 'prevent-submit':
          // Just prevent default, don't submit
          console.log('Form submission prevented');
          break;
        default:
          console.warn('Unknown form action:', action);
          this.showFormMessage(form, 'Unknown action: ' + action, 'error');
      }
    } catch (error) {
      console.error('Form submission error:', error);
      this.showFormMessage(form, 'Error: ' + error.message, 'error');
    }
  },

  async handleFieldChange(input) {
    try {
      const field = input.getAttribute('data-field');
      const value = input.value;

      console.log('Field change:', field, value);

      // Handle real-time field updates
      switch (field) {
        case 'css-rule':
          this.updateCSSRule(input);
          break;
        case 'css-variable':
          this.updateCSSVariable(input);
          break;
        case 'business-branding':
          this.updateBusinessBrandingPreview(input);
          break;
        case 'smtp-enabled':
          this.toggleSmtpSettings(input.checked);
          break;
        default:
          console.log('Field change:', field, value);
      }
    } catch (error) {
      console.error('Field change error:', error);
    }
  },

  async saveDashboardConfig(data) {
    try {
      const result = await ApiClient.post('/api/dashboard_sections.php?action=update_sections', data);
      if (result.success) {
        this.showFormMessage(null, 'Dashboard configuration saved successfully!', 'success');
        // Refresh the modal data
        if (window.showModal && window.loadDashboardConfig) {
          window.loadDashboardConfig();
        }
      } else {
        throw new Error(result.error || 'Failed to save dashboard configuration');
      }
    } catch (error) {
      console.error('Error saving dashboard config:', error);
      this.showFormMessage(null, 'Error saving dashboard configuration: ' + error.message, 'error');
    }
  },

  async saveBusinessInfo(data) {
    try {
      const result = await ApiClient.post('/api/business_settings.php?action=upsert_settings', {
        category: 'business',
        settings: data
      });
      if (result.success) {
        this.showFormMessage(null, 'Business information saved successfully!', 'success');
        // Update the preview if visible
        if (window.applyBusinessCssToRoot) {
          window.applyBusinessCssToRoot(data);
        }
      } else {
        throw new Error(result.error || 'Failed to save business information');
      }
    } catch (error) {
      console.error('Error saving business info:', error);
      this.showFormMessage(null, 'Error saving business information: ' + error.message, 'error');
    }
  },

  async saveBusinessBranding(data) {
    try {
      const result = await ApiClient.post('/api/business_settings.php?action=upsert_settings', {
        category: 'branding',
        settings: data
      });
      if (result.success) {
        this.showFormMessage(null, 'Business branding saved successfully!', 'success');
        // Update the preview immediately
        if (window.applyBusinessCssToRoot) {
          window.applyBusinessCssToRoot(data);
        }
      } else {
        throw new Error(result.error || 'Failed to save business branding');
      }
    } catch (error) {
      console.error('Error saving business branding:', error);
      this.showFormMessage(null, 'Error saving business branding: ' + error.message, 'error');
    }
  },

  async saveSquareSettings(data) {
    try {
      const result = await ApiClient.post('/api/square_config.php?action=save', data);
      if (result.success) {
        this.showFormMessage(null, 'Square settings saved successfully!', 'success');
        // Update connection status
        if (window.updateSquareConnectionStatus) {
          window.updateSquareConnectionStatus();
        }
      } else {
        throw new Error(result.error || 'Failed to save Square settings');
      }
    } catch (error) {
      console.error('Error saving Square settings:', error);
      this.showFormMessage(null, 'Error saving Square settings: ' + error.message, 'error');
    }
  },

  async saveSecrets(data) {
    try {
      const csrfToken = document.getElementById('secretsCsrf')?.value || '';
      const result = await ApiClient.post('/api/secrets.php?action=save', data, { headers: { 'X-CSRF-Token': csrfToken } });
      if (result.success) {
        this.showFormMessage(null, 'Secrets saved successfully!', 'success');
        // Clear the form
        const secretsPayload = document.getElementById('secretsPayload');
        if (secretsPayload) {
          secretsPayload.value = '';
        }
      } else {
        throw new Error(result.error || 'Failed to save secrets');
      }
    } catch (error) {
      console.error('Error saving secrets:', error);
      this.showFormMessage(null, 'Error saving secrets: ' + error.message, 'error');
    }
  },

  async saveAttributeOrder(data) {
    try {
      const result = await ApiClient.post('/api/global_color_size_management.php?action=update_order', data);
      if (result.success) {
        this.showFormMessage(null, 'Attribute order saved successfully!', 'success');
      } else {
        throw new Error(result.error || 'Failed to save attribute order');
      }
    } catch (error) {
      console.error('Error saving attribute order:', error);
      this.showFormMessage(null, 'Error saving attribute order: ' + error.message, 'error');
    }
  },

  async sendTestEmail(data) {
    try {
      const result = await ApiClient.post('/api/email_test.php?action=send_test', data);
      if (result.success) {
        this.showFormMessage(null, 'Test email sent successfully to ' + data.testRecipient + '!', 'success');
      } else {
        throw new Error(result.error || 'Failed to send test email');
      }
    } catch (error) {
      console.error('Error sending test email:', error);
      this.showFormMessage(null, 'Error sending test email: ' + error.message, 'error');
    }
  },

  async saveAISettings(data) {
    try {
      const result = await ApiClient.post('/api/ai_settings.php?action=save', data);
      if (result.success) {
        this.showFormMessage(null, 'AI settings saved successfully!', 'success');
      } else {
        throw new Error(result.error || 'Failed to save AI settings');
      }
    } catch (error) {
      console.error('Error saving AI settings:', error);
      this.showFormMessage(null, 'Error saving AI settings: ' + error.message, 'error');
    }
  },

  updateCSSRule(input) {
    try {
      const ruleName = input.name;
      const ruleValue = input.value;

      // Apply CSS rule through proper CSS variable management
      // This should be handled by the CSS rules system
      console.log('CSS rule updated:', ruleName, ruleValue);

      // Trigger CSS update event for proper handling
      const event = new CustomEvent('cssRuleChanged', {
        detail: { name: ruleName, value: ruleValue }
      });
      document.dispatchEvent(event);

    } catch (error) {
      console.error('Error updating CSS rule:', error);
    }
  },

  updateCSSVariable(input) {
    try {
      const varName = input.name;
      const varValue = input.value;

      // Apply CSS variable through proper CSS variable management
      console.log('CSS variable updated:', varName, varValue);

      // Trigger CSS variable update event for proper handling
      const event = new CustomEvent('cssVariableChanged', {
        detail: { name: varName, value: varValue }
      });
      document.dispatchEvent(event);

    } catch (error) {
      console.error('Error updating CSS variable:', error);
    }
  },

  updateBusinessBrandingPreview(input) {
    try {
      const fieldName = input.name;
      const fieldValue = input.value;

      // Update preview elements based on field
      const previewTitle = document.getElementById('brandPreviewTitle');
      const previewText = document.getElementById('brandPreviewText');

      if (previewTitle && fieldName === 'bizName') {
        previewTitle.textContent = fieldValue || 'Brand Preview Title';
      }

      if (previewText && fieldName === 'bizTagline') {
        previewText.textContent = fieldValue || 'This is sample content using your brand fonts and colors.';
      }

      // Update color swatches if colors changed
      if (fieldName.startsWith('palette-')) {
        this.updateBrandPalettePreview();
      }

      console.log('Business branding preview updated:', fieldName, fieldValue);
    } catch (error) {
      console.error('Error updating business branding preview:', error);
    }
  },

  toggleSmtpSettings(enabled) {
    try {
      const smtpSettings = document.getElementById('smtpSettings');
      if (smtpSettings) {
        smtpSettings.classList.toggle('hidden', !enabled);
      }
      console.log('SMTP settings toggled:', enabled);
    } catch (error) {
      console.error('Error toggling SMTP settings:', error);
    }
  },

  updateBrandPalettePreview() {
    try {
      const swatches = document.getElementById('brandPreviewSwatches');
      if (swatches && window.brandPalette) {
        swatches.innerHTML = window.brandPalette.map((color, index) => `
          <div class="w-8 h-8 rounded border-2 border-gray-300 brand-swatch" data-index="${index}" title="${color.name}"></div>
        `).join('');
      }
    } catch (error) {
      console.error('Error updating brand palette preview:', error);
    }
  },

  showFormMessage(form, message, type = 'info') {
    // Create or update status message
    let statusEl = form ? form.querySelector('.form-status') : document.querySelector('#formStatusGlobal');

    if (!statusEl) {
      statusEl = document.createElement('div');
      statusEl.className = 'form-status mt-2 p-2 rounded text-sm';

      if (form) {
        form.appendChild(statusEl);
      } else {
        statusEl.id = 'formStatusGlobal';
        document.body.appendChild(statusEl);
      }
    }

    // Set message and styling based on type
    statusEl.textContent = message;
    statusEl.className = 'form-status mt-2 p-2 rounded text-sm';

    switch (type) {
      case 'success':
        statusEl.classList.add('bg-green-100', 'text-green-800', 'border', 'border-green-300');
        break;
      case 'error':
        statusEl.classList.add('bg-red-100', 'text-red-800', 'border', 'border-red-300');
        break;
      default:
        statusEl.classList.add('bg-blue-100', 'text-blue-800', 'border', 'border-blue-300');
    }

    // Auto-hide after 5 seconds
    setTimeout(() => {
      if (statusEl && statusEl.parentNode) {
        statusEl.remove();
      }
    }, 5000);
  }
};

// Initialize form handlers
FormHandlers.init();

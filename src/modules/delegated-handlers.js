// Admin Settings - Delegated Click Handlers
// Handles all click events with data-action attributes

import { ApiClient } from '../core/api-client.js';

document.addEventListener('click', async (e) => {
  const t = e.target;
  const closest = (sel) => t && t.closest ? t.closest(sel) : null;

  // Business Info Modal handlers
  if (closest('[data-action="open-business-info"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const mod = await import('../modules/business-settings-api.js');
      const BusinessSettingsAPI = mod?.default || mod?.BusinessSettingsAPI;
      const info = await BusinessSettingsAPI.getBusinessInfo();
      // Apply business info and show modal
      if (window.applyBusinessInfo) {
        window.applyBusinessInfo(info.data || {});
      }
      if (window.showModal) {
        window.showModal('businessInfoModal');
      }
    } catch (error) {
      console.error('Error loading business info:', error);
    }
    return;
  }

  // Social Media Manager Modal (iframe)
  if (closest('[data-action="open-social-media-manager"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const id = 'socialMediaManagerModal';
      const modal = document.getElementById(id);
      if (modal) {
        // Prime iframe src once
        try {
          if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
          }
          modal.classList.add('over-header');
          const frame = modal.querySelector('#socialMediaManagerFrame');
          if (frame && (!frame.getAttribute('src') || frame.getAttribute('src') === 'about:blank')) {
            const ds = frame.getAttribute('data-src') || '/sections/tools/social_manager.php?modal=1&vite=dev';
            frame.setAttribute('src', ds);
          }
        } catch (_) {}
        // Show via utils if present
        if (window.WFModalUtils && typeof window.WFModalUtils.showModalById === 'function') {
          window.WFModalUtils.showModalById(id);
        } else if (typeof window.showModal === 'function') {
          window.showModal(id);
        } else {
          modal.classList.remove('hidden');
          modal.classList.add('show');
          try { modal.setAttribute('aria-hidden','false'); } catch(_) {}
        }
      }
    } catch (error) {
      console.error('Error opening Social Media Manager modal:', error);
    }
    return;
  }

  // Social Media Posts Templates Modal (iframe)
  if (closest('[data-action="open-social-posts"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const id = 'socialPostsManagerModal';
      const modal = document.getElementById(id);
      if (modal) {
        try {
          if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
          }
          const frame = modal.querySelector('#socialPostsManagerFrame');
          if (frame && (!frame.getAttribute('src') || frame.getAttribute('src') === 'about:blank')) {
            const ds = frame.getAttribute('data-src') || '/sections/tools/social_posts_manager.php?modal=1';
            frame.setAttribute('src', ds);
          }
        } catch (_) {}
        if (window.WFModalUtils && typeof window.WFModalUtils.showModalById === 'function') {
          window.WFModalUtils.showModalById(id);
        } else if (typeof window.showModal === 'function') {
          window.showModal(id);
        } else {
          modal.classList.remove('hidden');
          modal.classList.add('show');
          try { modal.setAttribute('aria-hidden','false'); } catch(_) {}
        }
      }
    } catch (error) {
      console.error('Error opening Social Media Posts modal:', error);
    }
    return;
  }

  // Dashboard Configuration Modal - Handled by inline script in admin_settings.php
  // if (closest('[data-action="open-dashboard-config"]')) {
  //   e.preventDefault();
  //   e.stopPropagation();
  //   try {
  //     // Load dashboard configuration data
  //     const data = await ApiClient.get('/api/dashboard_sections.php?action=get_sections');
  //     console.log('Dashboard config data:', data);

  //     // Show modal
  //     if (window.showModal) {
  //       window.showModal('dashboardConfigModal');
  //     }
  //   } catch (error) {
  //     console.error('Error loading dashboard config:', error);
  //   }
  //   return;
  // }

  // Categories Management Modal
  if (closest('[data-action="open-categories"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const id = 'categoriesModal';
      // Prime iframe inside the target modal
      try {
        const modal = document.getElementById(id);
        if (modal) {
          const frame = modal.querySelector('iframe');
          if (frame && (!frame.getAttribute('src') || frame.getAttribute('src') === 'about:blank')) {
            const ds = frame.getAttribute('data-src') || frame.getAttribute('src') || '/sections/admin_categories.php?modal=1';
            if (!frame.getAttribute('src') || frame.getAttribute('src') === 'about:blank') {
              frame.setAttribute('src', ds);
            }
          }
          // Ensure overlay is attached to body and above header
          try {
            if (modal.parentElement && modal.parentElement !== document.body) {
              document.body.appendChild(modal);
            }
            modal.classList.add('over-header');
          } catch (_) {}
        }
      } catch (_) {}

      // Show modal (prefer standardized utils)
      if (window.WFModalUtils && typeof window.WFModalUtils.showModalById === 'function') {
        window.WFModalUtils.showModalById(id);
      } else if (typeof window.showModal === 'function') {
        window.showModal(id);
      } else {
        const el = document.getElementById(id);
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          el.classList.remove('hidden');
          el.classList.add('show');
          try { el.setAttribute('aria-hidden', 'false'); } catch(_) {}
        }
      }
    } catch (error) {
      console.error('Error opening categories modal:', error);
    }
    return;
  }

  // Attributes Management Modal (iframe embed)
  if (closest('[data-action="open-attributes"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const modal = document.getElementById('attributesModal');
      if (modal) {
        // Ensure the iframe src is set once
        const frame = modal.querySelector('#attributesFrame');
        if (frame && !frame.getAttribute('src')) {
          const ds = frame.getAttribute('data-src') || '/components/embeds/attributes_manager.php?modal=1';
          frame.setAttribute('src', ds);
        }
        // Bring modal to front and show
        try {
          if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
          }
          modal.classList.add('over-header');
        } catch (_) {}
        modal.classList.remove('hidden');
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
      }
    } catch (error) {
      console.error('Error opening attributes modal:', error);
    }
    return;
  }

  // Email Settings Modal
  if (closest('[data-action="open-email-settings"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      // Load email configuration data
      const data = await ApiClient.get('/api/email_config.php?action=get');
      console.log('Email config data:', data);

      // Show modal
      if (window.showModal) {
        window.showModal('emailSettingsModal');
      }
    } catch (error) {
      console.error('Error loading email settings:', error);
    }
    return;
  }

  // Square Settings Modal
  if (closest('[data-action="open-square-settings"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      // Load Square configuration data
      const data = await ApiClient.get('/api/square_config.php?action=get');
      console.log('Square config data:', data);

      // Show modal
      if (window.showModal) {
        window.showModal('squareSettingsModal');
      }
    } catch (error) {
      console.error('Error loading Square settings:', error);
    }
    return;
  }

  // AI Settings Modal
  if (closest('[data-action="open-ai-settings"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      // Load AI configuration data
      const data = await ApiClient.get('/api/ai_settings.php?action=get');
      console.log('AI settings data:', data);

      // Show modal
      if (window.showModal) {
        window.showModal('aiSettingsModal');
      }
    } catch (error) {
      console.error('Error loading AI settings:', error);
    }
    return;
  }

  // AI Tools Modal
  if (closest('[data-action="open-ai-tools"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      // Show modal
      if (window.showModal) {
        window.showModal('aiToolsModal');
      }
    } catch (error) {
      console.error('Error opening AI tools modal:', error);
    }
    return;
  }

  // Brand Palette handlers
  if (closest('[data-action="business-palette-add"]')) {
    e.preventDefault();
    const nameInput = document.getElementById('newPaletteName');
    const hexInput = document.getElementById('newPaletteHex');
    if (nameInput && hexInput && window.brandPalette) {
      const name = nameInput.value.trim();
      const hex = hexInput.value.trim();
      if (name && hex) {
        window.brandPalette.push({ name, hex });
        nameInput.value = '';
        hexInput.value = '#000000';
        if (window.renderBrandPalette) {
          window.renderBrandPalette();
        }
        const s = window.collectBusinessInfo ? window.collectBusinessInfo() : {};
        if (window.applyBusinessCssToRoot) {
          window.applyBusinessCssToRoot(s);
        }
      }
    }
    return;
  }

  if (closest('[data-action="business-palette-delete"]')) {
    e.preventDefault();
    const index = parseInt(e.target.dataset.index, 10);
    if (!isNaN(index) && window.brandPalette && window.brandPalette[index]) {
      window.brandPalette.splice(index, 1);
      if (window.renderBrandPalette) {
        window.renderBrandPalette();
      }
      const s = window.collectBusinessInfo ? window.collectBusinessInfo() : {};
      if (window.applyBusinessCssToRoot) {
        window.applyBusinessCssToRoot(s);
      }
    }
    return;
  }

  // Attributes Management handlers
  if (closest('[data-action="attr-add"]')) {
    e.preventDefault();
    e.stopPropagation();
    const button = e.target;
    const type = button.dataset.type;
    if (window.addAttribute && type) {
      const form = button.closest('form');
      const input = form ? form.querySelector('.attr-input') : null;
      const value = input ? input.value.trim() : '';
      if (value) {
        if (type === 'size') {
          const parts = value.split(' ');
          if (parts.length >= 2) {
            const sizeName = parts.slice(0, -1).join(' ');
            const sizeCode = parts[parts.length - 1];
            window.addAttribute(type, sizeName, sizeCode);
          }
        } else {
          window.addAttribute(type, value);
        }
        if (input) input.value = '';
      }
    }
    return;
  }

  // Business Save handlers
  if (closest('[data-action="business-save"]')) {
    e.preventDefault();
    e.stopPropagation();
    // The Save button may live in the modal header (outside the form),
    // so do not require a closest form to trigger the save.
    // Prefer calling the centralized save function directly.
    if (window.saveBusinessInfo) {
      window.saveBusinessInfo();
    }
    return;
  }

  if (closest('[data-action="business-save-branding"]')) {
    e.preventDefault();
    e.stopPropagation();
    if (window.saveBusinessInfo) {
      window.saveBusinessInfo();
    }
    return;
  }

  // Email Settings handlers
  if (closest('[data-action="email-send-test"]')) {
    e.preventDefault();
    e.stopPropagation();
    const form = closest('form');
    const testRecipient = form ? form.querySelector('#testRecipient') : null;
    if (testRecipient && testRecipient.value) {
      console.log('Sending test email to:', testRecipient.value);
      // Implementation would go here
    }
    return;
  }

  // Square Settings handlers
  if (closest('[data-action="square-save-settings"]')) {
    e.preventDefault();
    e.stopPropagation();
    console.log('Saving Square settings...');
    // Implementation would go here
    return;
  }

  if (closest('[data-action="square-test-connection"]')) {
    e.preventDefault();
    e.stopPropagation();
    console.log('Testing Square connection...');
    // Implementation would go here
    return;
  }

  // Dashboard Configuration handlers - Handled by inline script in admin_settings.php
  // if (closest('[data-action="dashboard-config-save"]')) {
  //   e.preventDefault();
  //   e.stopPropagation();
  //   console.log('Saving dashboard configuration...');
  //   // Implementation would go here
  // }

  // Email History handlers
  if (closest('[data-action="email-history-search"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const searchInput = document.getElementById('emailHistorySearch');
      const searchTerm = searchInput ? searchInput.value : '';

      if (searchTerm.trim()) {
        await this.loadEmailHistory({ search: searchTerm });
      } else {
        await this.loadEmailHistory();
      }
    } catch (error) {
      console.error('Error searching email history:', error);
    }
    return;
  }

  if (closest('[data-action="email-history-apply-filters"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const filters = {
        from: document.getElementById('emailHistoryFrom')?.value,
        to: document.getElementById('emailHistoryTo')?.value,
        type: document.getElementById('emailHistoryType')?.value,
        status: document.getElementById('emailHistoryStatusFilter')?.value,
        sort: document.getElementById('emailHistorySort')?.value
      };

      await this.loadEmailHistory(filters);
    } catch (error) {
      console.error('Error applying email history filters:', error);
    }
    return;
  }

  if (closest('[data-action="email-history-clear-filters"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      // Clear all filter inputs
      const inputs = ['emailHistorySearch', 'emailHistoryFrom', 'emailHistoryTo', 'emailHistoryType'];
      inputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) input.value = '';
      });

      // Reset dropdowns
      const statusFilter = document.getElementById('emailHistoryStatusFilter');
      if (statusFilter) statusFilter.value = '';

      const sortSelect = document.getElementById('emailHistorySort');
      if (sortSelect) sortSelect.value = 'sent_at_desc';

      // Reload with no filters
      await this.loadEmailHistory();
    } catch (error) {
      console.error('Error clearing email history filters:', error);
    }
    return;
  }

  if (closest('[data-action="email-history-refresh"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      await this.loadEmailHistory();
    } catch (error) {
      console.error('Error refreshing email history:', error);
    }
    return;
  }

  if (closest('[data-action="email-history-download"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const filters = {
        from: document.getElementById('emailHistoryFrom')?.value,
        to: document.getElementById('emailHistoryTo')?.value,
        type: document.getElementById('emailHistoryType')?.value,
        status: document.getElementById('emailHistoryStatusFilter')?.value,
        sort: document.getElementById('emailHistorySort')?.value
      };

      await this.downloadEmailHistory(filters);
    } catch (error) {
      console.error('Error downloading email history:', error);
    }
    return;
  }

  if (closest('[data-action="email-history-prev"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const currentPage = parseInt(document.getElementById('emailHistoryPage')?.textContent?.match(/Page (\d+)/)?.[1] || '1');
      if (currentPage > 1) {
        await this.loadEmailHistory({}, currentPage - 1);
      }
    } catch (error) {
      console.error('Error loading previous page:', error);
    }
    return;
  }

  if (closest('[data-action="email-history-next"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const currentPage = parseInt(document.getElementById('emailHistoryPage')?.textContent?.match(/Page (\d+)/)?.[1] || '1');
      await this.loadEmailHistory({}, currentPage + 1);
    } catch (error) {
      console.error('Error loading next page:', error);
    }
    return;
  }

  // Email History Detail Drawer handlers
  if (closest('[data-action="email-history-close-drawer"]')) {
    e.preventDefault();
    e.stopPropagation();
    this.closeEmailHistoryDrawer();
    return;
  }

  if (closest('[data-action="email-history-copy-subject"]')) {
    e.preventDefault();
    e.stopPropagation();
    const subject = document.getElementById('ehdSubject')?.textContent;
    if (subject) {
      navigator.clipboard.writeText(subject);
      this.showToast('Subject copied to clipboard');
    }
    return;
  }

  if (closest('[data-action="email-history-copy-to"]')) {
    e.preventDefault();
    e.stopPropagation();
    const to = document.getElementById('ehdTo')?.textContent;
    if (to) {
      navigator.clipboard.writeText(to);
      this.showToast('Recipient copied to clipboard');
    }
    return;
  }

  if (closest('[data-action="email-history-copy-type"]')) {
    e.preventDefault();
    e.stopPropagation();
    const type = document.getElementById('ehdType')?.textContent;
    if (type) {
      navigator.clipboard.writeText(type);
      this.showToast('Type copied to clipboard');
    }
    return;
  }

  // Dashboard Configuration handlers - Handled by inline script in admin_settings.php
  // if (closest('[data-action="dashboard-config-refresh"]')) {
  //   e.preventDefault();
  //   e.stopPropagation();
  //   try {
  //     await this.loadDashboardConfig();
  //   } catch (error) {
  //     console.error('Error refreshing dashboard config:', error);
  //   }
  //   return;
  // }

  // Dashboard Configuration handlers - Handled by inline script in admin_settings.php
  // if (closest('[data-action="dashboard-config-reset"]')) {
  //   e.preventDefault();
  //   e.stopPropagation();
  //   try {
  //     if (confirm('Reset dashboard configuration to defaults?')) {
  //       const result = await ApiClient.post('/api/dashboard_sections.php?action=reset_defaults', {});

  //       if (result.success) {
  //         await this.loadDashboardConfig();
  //         this.showToast('Dashboard configuration reset to defaults');
  //       } else {
  //         throw new Error(result.error || 'Failed to reset dashboard configuration');
  //       }
  //     }
  //   } catch (error) {
  //     console.error('Error resetting dashboard config:', error);
  //     this.showToast('Error resetting dashboard configuration: ' + error.message, 'error');
  //   }
  //   return;
  // }

  // Dashboard Configuration handlers - Handled by inline script in admin_settings.php
  // if (closest('[data-action="move-up"]')) {
  //   e.preventDefault();
  //   e.stopPropagation();
  //   try {
  //     const key = e.target.dataset.key;
  //     if (key) {
  //       this.moveDashboardItem(key, -1);
  //     }
  //   } catch (error) {
  //     console.error('Error moving dashboard item up:', error);
  //   }
  //   return;
  // }

  // if (closest('[data-action="move-down"]')) {
  //   e.preventDefault();
  //   e.stopPropagation();
  //   try {
  //     const key = e.target.dataset.key;
  //     if (key) {
  //       this.moveDashboardItem(key, 1);
  //     }
  //   } catch (error) {
  //     console.error('Error moving dashboard item down:', error);
  //   }
  //   return;
  // }

  // CSS Rules handlers
  if (closest('[data-action="open-css-rules"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      // Load CSS rules data
      const data = await ApiClient.get('/api/css_rules.php?action=list');
      console.log('CSS rules data:', data);

      // Show modal
      if (window.showModal) {
        window.showModal('cssRulesModal');
      }
    } catch (error) {
      console.error('Error loading CSS rules:', error);
    }
    return;
  }

  // Logging Status handlers
  if (closest('[data-action="open-logging-status"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      // Show modal
      if (window.showModal) {
        window.showModal('loggingStatusModal');
      }
      // Load summary + shortcuts
      try {
        if (DelegatedHandlers && typeof DelegatedHandlers.loadLoggingStatusAndShortcuts === 'function') {
          DelegatedHandlers.loadLoggingStatusAndShortcuts();
        }
      } catch (_) {}
    } catch (error) {
      console.error('Error opening logging status modal:', error);
    }
    return;
  }

  if (closest('[data-action="logging-refresh-status"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      if (DelegatedHandlers && typeof DelegatedHandlers.loadLoggingStatusAndShortcuts === 'function') {
        DelegatedHandlers.loadLoggingStatusAndShortcuts();
      }
    } catch (error) {
      console.error('Error refreshing logging status:', error);
    }
    return;
  }

  if (closest('[data-action="logging-open-file"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      // Try to fetch logs and open the top-priority file log
      let targetPath = null;
      try {
        const res = await ApiClient.get('/api/website_logs.php?action=list_logs');
        if (res && res.success && Array.isArray(res.logs)) {
          const filePriority = {
            'file:php_error.log': 0,
            'file:application.log': 1,
            'file:vite_server.log': 2,
            'file:php_server.log': 3,
            'file:monitor.log': 4,
            'file:monitor_root.log': 5,
            'file:autostart.log': 6
          };
          const isFile = (log) => (log && (log.log_source === 'file' || String(log.type).startsWith('file:')));
          const weight = (log) => {
            const t = String(log.type);
            return (t in filePriority) ? filePriority[t] : 50;
          };
          const first = res.logs.filter(isFile).sort((a,b)=> weight(a) - weight(b))[0];
          if (first && first.path) {
            targetPath = first.path;
          }
        }
      } catch (_) { /* ignore and use fallback */ }

      if (!targetPath) {
        targetPath = 'logs/php_error.log';
      }

      const a = document.createElement('a');
      a.href = `/api/admin_file_proxy.php?path=${encodeURIComponent(targetPath)}`;
      a.target = '_blank';
      a.rel = 'noopener';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    } catch (error) {
      console.error('Error opening latest log preview:', error);
    }
    return;
  }

  if (closest('[data-action="logging-clear-logs"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const ok = await (window.showConfirmationModal && window.showConfirmationModal({
        title: 'Clear Error Logs',
        message: 'Clear application error logs? This cannot be undone.',
        confirmText: 'Clear Logs',
        confirmStyle: 'danger',
        icon: '⚠️',
        iconType: 'danger'
      }));
      if (!ok) return;
      if (DelegatedHandlers && typeof DelegatedHandlers.clearLog === 'function') {
        DelegatedHandlers.clearLog('error_logs');
      }
    } catch (error) {
      console.error('Error clearing logs:', error);
    }
    return;
  }

  if (closest('[data-action="logging-download-all"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const a = document.createElement('a');
      a.href = '/api/website_logs.php?action=download';
      a.target = '_blank';
      a.rel = 'noopener';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    } catch (error) {
      console.error('Error downloading all logs:', error);
    }
    return;
  }

  if (closest('[data-action="logging-preview-log"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const btn = closest('[data-action="logging-preview-log"]');
      const type = btn && btn.dataset ? btn.dataset.type : '';
      if (type && DelegatedHandlers && typeof DelegatedHandlers.previewLog === 'function') {
        DelegatedHandlers.previewLog(type);
      }
    } catch (error) {
      console.error('Error previewing log:', error);
    }
    return;
  }

  if (closest('[data-action="logging-download-log"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const btn = closest('[data-action="logging-download-log"]');
      const type = btn && btn.dataset ? btn.dataset.type : '';
      if (type) {
        const a = document.createElement('a');
        a.href = `/api/website_logs.php?action=download_log&type=${encodeURIComponent(type)}`;
        a.target = '_blank';
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
      }
    } catch (error) {
      console.error('Error downloading log:', error);
    }
    return;
  }

  if (closest('[data-action="secrets-rotate"]')) {
    e.preventDefault();
    e.stopPropagation();
    console.log('Secrets rotation triggered');
    // Implementation would go here
    return;
  }

  if (closest('[data-action="secrets-export"]')) {
    e.preventDefault();
    e.stopPropagation();
    console.log('Secrets export triggered');
    // Implementation would go here
    return;
  }

  if (closest('[data-action="secrets-save"]')) {
    e.preventDefault();
    e.stopPropagation();
    console.log('Saving secrets...');
    // Implementation would go here
    return;
  }

  // Attributes Delete handlers
  if (closest('[data-action="attr-delete"]')) {
    e.preventDefault();
    e.stopPropagation();
    const button = e.target;
    const type = button.dataset.type;
    const id = button.dataset.id;

    if (window.deleteAttribute && type && id) {
      const ok = await (window.showConfirmationModal && window.showConfirmationModal({
        title: 'Delete Attribute',
        message: `Delete this ${type}?`,
        confirmText: 'Delete',
        confirmStyle: 'danger',
        icon: '⚠️',
        iconType: 'danger'
      }));
      if (!ok) return;
      window.deleteAttribute(type, id);
      // Refresh the modal data
      if (window.populateAttributesModal) {
        window.populateAttributesModal('attributesModal');
      }
    }
    return;
  }
});

// Utility methods for complex features
const DelegatedHandlers = {
  async loadLoggingStatusAndShortcuts() {
    try {
      await Promise.all([
        this.loadLoggingSummary(),
        this.loadLoggingShortcuts()
      ]);
    } catch (error) {
      console.error('Error loading logging summary/shortcuts:', error);
    }
  },

  async loadLoggingSummary() {
    try {
      const res = await ApiClient.get('/api/website_logs.php?action=get_status');
      const sum = document.getElementById('loggingSummary');
      if (sum && res && res.success && res.status) {
        const f = res.status.file_logging || {};
        const d = res.status.database_logging || {};
        const parts = [];
        parts.push(`File logs: ${f.enabled ? 'enabled' : 'disabled'} (${f.total_size || '0 MB'})`);
        parts.push(`DB logs - errors: ${d.error_logs ?? 0}, email: ${d.email_logs ?? 0}, admin: ${d.admin_activity_logs ?? 0}, analytics: ${d.analytics_logs ?? 0}`);
        sum.textContent = parts.join(' • ');
      }
    } catch (error) {
      console.error('Error loading logging summary:', error);
    }
  },

  async loadLoggingShortcuts() {
    try {
      const res = await ApiClient.get('/api/website_logs.php?action=list_logs');
      const list = document.getElementById('loggingShortcutsList');
      if (!list) return;
      if (!res || !res.success || !Array.isArray(res.logs)) {
        list.innerHTML = '<div class="text-sm text-gray-500">No logs available.</div>';
        return;
      }
      // Sort: important file logs first, then key DB logs
      const filePriority = {
        'file:php_error.log': 0,
        'file:application.log': 1,
        'file:vite_server.log': 2,
        'file:php_server.log': 3,
        'file:monitor.log': 4,
        'file:monitor_root.log': 5,
        'file:autostart.log': 6
      };
      const dbOrder = ['error_logs','admin_activity_logs','email_logs','order_logs','inventory_logs','user_activity_logs','analytics_logs'];
      const weight = (log) => {
        const t = String(log.type);
        if (t.startsWith('file:')) return (t in filePriority) ? filePriority[t] : 50; // other files after the key ones
        const i = dbOrder.indexOf(t);
        return (i === -1) ? 200 : (100 + i); // DB logs after files
      };
      const sorted = res.logs.slice().sort((a,b)=> weight(a) - weight(b));
      list.innerHTML = sorted.map(log => {
        const last = log.last_entry ? new Date(log.last_entry).toLocaleString() : 'Never';
        const desc = log.description || '';
        const name = log.name || log.type;
        const type = log.type;
        const isFile = (log.log_source === 'file') || String(type).startsWith('file:');
        if (isFile) {
          const size = log.size || '';
          const safePath = encodeURIComponent(log.path || '');
          return `
            <div class="flex items-start justify-between gap-3 p-2 border rounded">
              <div class="min-w-0">
                <div class="font-medium">${name}</div>
                <div class="text-xs text-gray-600">${desc}</div>
                <div class="text-xs text-gray-500 mt-0.5">Size: ${size || '—'} • Last Modified: ${last}</div>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <a class="btn btn-secondary btn-sm" href="/api/admin_file_proxy.php?path=${safePath}" target="_blank" rel="noopener">Open</a>
                <a class="btn btn-secondary btn-sm" href="/api/admin_file_proxy.php?path=${safePath}" download>Download</a>
              </div>
            </div>
          `;
        }
        const count = typeof log.entries === 'number' ? log.entries : 0;
        return `
          <div class="flex items-start justify-between gap-3 p-2 border rounded">
            <div class="min-w-0">
              <div class="font-medium">${name}</div>
              <div class="text-xs text-gray-600">${desc}</div>
              <div class="text-xs text-gray-500 mt-0.5">Entries: ${count} • Last: ${last}</div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <button class="btn btn-secondary btn-sm" data-action="logging-preview-log" data-type="${type}">Preview</button>
              <button class="btn btn-secondary btn-sm" data-action="logging-download-log" data-type="${type}">Download CSV</button>
            </div>
          </div>
          <div id="logPreview_${type}" class="hidden border-l-4 border-gray-200 pl-3 ml-2 mt-1" style="max-height: 360px; overflow:auto;"></div>
        `;
      }).join('');
    } catch (error) {
      console.error('Error loading logging shortcuts:', error);
    }
  },

  async previewLog(type) {
    try {
      if (!type) return;
      const target = document.getElementById(`logPreview_${type}`);
      if (!target) return;
      // Toggle visibility if already populated
      if (target.__wfPopulated) {
        target.classList.toggle('hidden');
        return;
      }
      const res = await ApiClient.get('/api/website_logs.php?action=get_log', { type, page: 1, limit: 50 });
      if (!res || !res.success) {
        target.innerHTML = '<div class="text-xs text-red-600">Failed to load preview.</div>';
        target.classList.remove('hidden');
        target.__wfPopulated = true;
        return;
      }
      const entries = Array.isArray(res.entries) ? res.entries : [];
      if (!entries.length) {
        target.innerHTML = '<div class="text-xs text-gray-500">No recent entries.</div>';
      } else {
        target.innerHTML = `
          <div class="text-xs text-gray-700 space-y-1">
            ${entries.map(e => `<div class=\"truncate\">• ${this._formatLogEntry(type, e)}</div>`).join('')}
          </div>
        `;
      }
      target.classList.remove('hidden');
      target.__wfPopulated = true;
    } catch (error) {
      console.error('Error previewing log:', error);
    }
  },

  async clearLog(type) {
    try {
      if (!type) return;
      const res = await ApiClient.post('/api/website_logs.php?action=clear_log', { type });
      const status = document.getElementById('loggingStatusResult');
      if (status) {
        status.textContent = (res && res.success) ? (res.message || 'Log cleared') : (res && res.error ? res.error : 'Failed to clear log');
      }
      // Refresh shortcuts to update counts
      this.loadLoggingShortcuts();
    } catch (error) {
      console.error('Error clearing log:', error);
    }
  },

  _formatLogEntry(type, e) {
    try {
      switch(type){
        case 'error_logs': return `${e.created_at || ''} - ${e.error_type || 'ERROR'}: ${e.message || ''}`;
        case 'admin_activity_logs': return `${e.timestamp || ''} - ${e.action_type || ''}: ${e.action_description || ''}`;
        case 'email_logs': return `${e.sent_at || ''} - ${e.status || ''}: ${e.email_subject || ''}`;
        case 'order_logs': return `${e.created_at || ''} - ${e.order_id || ''}: ${e.action || ''}`;
        case 'inventory_logs': return `${e.timestamp || ''} - ${e.item_sku || ''}: ${e.action_type || ''}`;
        case 'user_activity_logs': return `${e.timestamp || ''} - ${e.activity_type || ''}: ${e.activity_description || ''}`;
        case 'analytics_logs': return `${e.timestamp || ''} - ${e.event_type || ''} on ${e.page_url || ''}`;
        default: return JSON.stringify(e);
      }
    } catch(_) { return ''; }
  },
  async loadEmailHistory(filters = {}, page = 1) {
    try {
      const params = {
        page: page,
        limit: 20,
        ...filters
      };

      const result = await ApiClient.get('/api/email_history.php?action=list', params);

      if (result.success) {
        this.populateEmailHistory(result.data);
        this.updateEmailHistoryPagination(result.pagination);
      } else {
        throw new Error(result.error || 'Failed to load email history');
      }
    } catch (error) {
      console.error('Error loading email history:', error);
      this.showToast('Error loading email history: ' + error.message, 'error');
    }
  },

  populateEmailHistory(emails) {
    const container = document.getElementById('emailHistoryList');
    if (!container) return;

    if (!emails || emails.length === 0) {
      container.innerHTML = '<div class="p-4 text-center text-gray-500">No emails found</div>';
      return;
    }

    container.innerHTML = emails.map(email => `
      <div class="email-row border-b hover:bg-gray-50 cursor-pointer p-3" data-email-id="${email.id}">
        <div class="flex justify-between items-start">
          <div class="flex-1 min-w-0">
            <div class="font-medium text-sm truncate">${email.subject}</div>
            <div class="text-xs text-gray-500 mt-1">To: ${email.to_email} • Type: ${email.type}</div>
            <div class="text-xs text-gray-400">${new Date(email.sent_at).toLocaleString()}</div>
          </div>
          <div class="flex items-center gap-2">
            <span class="status-chip ${email.status === 'sent' ? 'chip-success' : email.status === 'failed' ? 'chip-error' : 'chip-warning'}">
              ${email.status}
            </span>
            <button class="btn btn-secondary btn-sm" data-action="email-history-view-details" data-email-id="${email.id}">Details</button>
          </div>
        </div>
      </div>
    `).join('');

    // Add click handlers for email rows
    container.querySelectorAll('[data-action="email-history-view-details"]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const emailId = btn.dataset.emailId;
        this.showEmailHistoryDetails(emailId);
      });
    });
  },

  updateEmailHistoryPagination(pagination) {
    const pageInfo = document.getElementById('emailHistoryPage');
    if (pageInfo) {
      pageInfo.textContent = `Page ${pagination.current_page} of ${pagination.total_pages}`;
    }
  },

  async downloadEmailHistory(filters = {}) {
    try {
      // Request CSV via ApiClient (returns text for non-JSON). Convert to Blob for download.
      const csvText = await ApiClient.get('/api/email_history.php?action=export', { ...filters });
      const blob = new Blob([csvText || ''], { type: 'text/csv' });

      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `email-history-${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);

      this.showToast('Email history downloaded successfully');
    } catch (error) {
      console.error('Error downloading email history:', error);
      this.showToast('Error downloading email history: ' + error.message, 'error');
    }
  },

  closeEmailHistoryDrawer() {
    const drawer = document.getElementById('emailHistoryDrawer');
    const overlay = document.getElementById('emailHistoryDrawerOverlay');

    if (drawer) drawer.classList.add('hidden');
    if (overlay) overlay.classList.add('hidden');
  },

  async showEmailHistoryDetails(emailId) {
    try {
      const result = await ApiClient.get('/api/email_history.php?action=get', { id: emailId });

      if (result.success) {
        const email = result.data;
        this.populateEmailHistoryDetails(email);
        this.showEmailHistoryDrawer();
      } else {
        throw new Error(result.error || 'Failed to load email details');
      }
    } catch (error) {
      console.error('Error loading email details:', error);
      this.showToast('Error loading email details: ' + error.message, 'error');
    }
  },

  populateEmailHistoryDetails(email) {
    // Populate detail fields
    const fields = {
      'ehdSubject': email.subject,
      'ehdTo': email.to_email,
      'ehdType': email.type
    };

    Object.entries(fields).forEach(([id, value]) => {
      const element = document.getElementById(id);
      if (element) element.textContent = value || 'N/A';
    });

    // Populate content area
    const content = document.getElementById('emailHistoryDrawerContent');
    if (content) {
      content.innerHTML = `
        <div class="space-y-3">
          <div>
            <strong>From:</strong> ${email.from_email || 'N/A'}
          </div>
          <div>
            <strong>BCC:</strong> ${email.bcc_email || 'N/A'}
          </div>
          <div>
            <strong>Headers:</strong>
            <pre class="text-xs bg-gray-100 p-2 mt-1 overflow-auto">${JSON.stringify(email.headers, null, 2)}</pre>
          </div>
          <div>
            <strong>Body:</strong>
            <div class="text-xs bg-gray-50 p-2 mt-1 max-h-32 overflow-auto">${email.body || 'No body content'}</div>
          </div>
        </div>
      `;
    }
  },

  showEmailHistoryDrawer() {
    const drawer = document.getElementById('emailHistoryDrawer');
    const overlay = document.getElementById('emailHistoryDrawerOverlay');

    if (drawer) drawer.classList.remove('hidden');
    if (overlay) overlay.classList.remove('hidden');
  },

  showToast(message, type = 'info') {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 p-3 rounded shadow-lg text-sm font-medium transition-all transform translate-x-full ${
      type === 'error' ? 'bg-red-500 text-white' :
      type === 'success' ? 'bg-green-500 text-white' :
      'bg-blue-500 text-white'
    }`;

    toast.textContent = message;
    document.body.appendChild(toast);

    // Animate in
    setTimeout(() => {
      toast.classList.remove('translate-x-full');
    }, 100);

    // Auto remove
    setTimeout(() => {
      toast.classList.add('translate-x-full');
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    }, 3000);
  },

  // Dashboard configuration methods
  async loadDashboardConfig() {
    try {
      const data = await ApiClient.get('/api/dashboard_sections.php?action=get_sections');

      if (data.success) {
        this.populateDashboardConfig(data.data);
      } else {
        throw new Error(data.error || 'Failed to load dashboard configuration');
      }
    } catch (error) {
      console.error('Error loading dashboard config:', error);
      this.showToast('Error loading dashboard configuration: ' + error.message, 'error');
    }
  },

  populateDashboardConfig(data) {
    const tbody = document.getElementById('dashboardSectionsBody');
    if (!tbody) return;

    if (!data || !data.sections) {
      tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-gray-500">No sections found</td></tr>';
      return;
    }

    tbody.innerHTML = data.sections.map((section, index) => {
      const isActive = section.is_active ? 'checked' : '';
      const widthSelected = section.width_class === 'full-width' ? 'selected' : '';

      return `
        <tr class="border-b hover:bg-gray-50" draggable="true" data-section-key="${section.section_key}">
          <td class="p-2">
            <div class="flex items-center gap-1">
              <button class="text-xs p-1 hover:bg-gray-200" data-action="move-up" data-key="${section.section_key}" ${index === 0 ? 'disabled' : ''}>▲</button>
              <button class="text-xs p-1 hover:bg-gray-200" data-action="move-down" data-key="${section.section_key}" ${index === data.sections.length - 1 ? 'disabled' : ''}>▼</button>
              <span class="ml-1 text-gray-500">${section.display_order || index + 1}</span>
            </div>
          </td>
          <td class="p-2">${section.custom_title || section.section_key}</td>
          <td class="p-2"><code class="text-xs">${section.section_key}</code></td>
          <td class="p-2">
            <select class="dash-width text-xs border rounded px-1 py-0.5" data-key="${section.section_key}">
              <option value="half-width" ${section.width_class === 'half-width' ? 'selected' : ''}>Half</option>
              <option value="full-width" ${widthSelected}>Full</option>
            </select>
          </td>
          <td class="p-2">
            <input type="checkbox" class="dash-active" data-key="${section.section_key}" ${isActive}>
          </td>
        </tr>
      `;
    }).join('');

    // Add change handlers for width and active state
    tbody.querySelectorAll('.dash-width').forEach(select => {
      select.addEventListener('change', (e) => {
        this.updateDashboardItemWidth(e.target.dataset.key, e.target.value);
      });
    });

    tbody.querySelectorAll('.dash-active').forEach(checkbox => {
      checkbox.addEventListener('change', (e) => {
        this.updateDashboardItemActive(e.target.dataset.key, e.target.checked);
      });
    });

    // Initialize drag and drop
    this.initDashboardConfigDragAndDrop();
  },

  async moveDashboardItem(sectionKey, direction) {
    const tbody = document.getElementById('dashboardSectionsBody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const currentIndex = rows.findIndex(row => row.dataset.sectionKey === sectionKey);

    if (currentIndex === -1) return;

    const newIndex = currentIndex + direction;
    if (newIndex < 0 || newIndex >= rows.length) return;

    // Move the row
    if (direction < 0) {
      tbody.insertBefore(rows[currentIndex], rows[newIndex]);
    } else {
      tbody.insertBefore(rows[currentIndex], rows[newIndex].nextSibling);
    }

    // Update order numbers
    this.updateDashboardOrderNumbers();

    this.showToast('Dashboard item moved');
  },

  updateDashboardOrderNumbers() {
    const tbody = document.getElementById('dashboardSectionsBody');
    if (!tbody) return;

    tbody.querySelectorAll('tr').forEach((row, index) => {
      const orderSpan = row.querySelector('span');
      if (orderSpan) {
        orderSpan.textContent = index + 1;
      }
    });
  },

  updateDashboardItemWidth(sectionKey, width) {
    console.log('Dashboard item width updated:', sectionKey, width);
    // This would typically save to the server
  },

  updateDashboardItemActive(sectionKey, active) {
    console.log('Dashboard item active state updated:', sectionKey, active);
    // This would typically save to the server
  },

  initDashboardConfigDragAndDrop() {
    const tbody = document.getElementById('dashboardSectionsBody');
    if (!tbody) return;

    let draggedElement = null;

    tbody.addEventListener('dragstart', (e) => {
      if (e.target.tagName === 'TR') {
        draggedElement = e.target;
        e.target.classList.add('dragging');
      }
    });

    tbody.addEventListener('dragend', (_) => {
      if (draggedElement) {
        draggedElement.classList.remove('dragging');
        draggedElement = null;
      }
    });

    tbody.addEventListener('dragover', (e) => {
      e.preventDefault();
      const target = e.target.closest('tr');
      if (target && target !== draggedElement) {
        const rect = target.getBoundingClientRect();
        const midpoint = rect.top + rect.height / 2;

        if (e.clientY < midpoint) {
          target.parentNode.insertBefore(draggedElement, target);
        } else {
          target.parentNode.insertAfter(draggedElement, target);
        }
      }
    });
  }
};

// Make methods available globally for use by event handlers
if (typeof window !== 'undefined') {
  window.loadEmailHistory = (filters, page) => DelegatedHandlers.loadEmailHistory(filters, page);
  window.downloadEmailHistory = (filters) => DelegatedHandlers.downloadEmailHistory(filters);
  window.showEmailHistoryDetails = (id) => DelegatedHandlers.showEmailHistoryDetails(id);
  window.closeEmailHistoryDrawer = () => DelegatedHandlers.closeEmailHistoryDrawer();
  window.loadDashboardConfig = () => DelegatedHandlers.loadDashboardConfig();
  window.updateSquareConnectionStatus = () => DelegatedHandlers.updateSquareConnectionStatus();
}

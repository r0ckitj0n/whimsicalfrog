// Admin Settings Bridge
// Lightweight initializer to migrate inline scripts to Vite-managed code
// - Loads Email settings (prefers BusinessSettingsAPI; falls back to legacy endpoint)
// - Wires basic UI behaviors (toggle SMTP section)

import { ApiClient } from '../core/apiClient.js';
import BusinessSettingsAPI from '../modules/business-settings-api.js';

function byId(id){ return document.getElementById(id); }
function setVal(id, v){ const el = byId(id); if (el) el.value = v ?? ''; }
function setChecked(id, v){ const el = byId(id); if (el) el.checked = !!v; }

function normalizeEmailConfigFromSettings(settings) {
  // Map DB settings object to the UI fields used by the page
  const s = settings || {};
  const bool = (v) => v === true || v === '1' || v === 1 || v === 'true' || v === 'on';
  const num = (v) => (v === undefined || v === null || v === '' ? '' : Number(v));
  return {
    fromEmail: s.from_email || s.fromEmail || '',
    fromName: s.from_name || s.fromName || '',
    adminEmail: s.admin_email || s.adminEmail || '',
    bccEmail: s.bcc_email || s.bccEmail || '',
    smtpEnabled: bool(s.smtp_enabled ?? s.smtpEnabled),
    smtpHost: s.smtp_host || s.smtpHost || '',
    smtpPort: num(s.smtp_port ?? s.smtpPort),
    smtpUsername: s.smtp_username || s.smtpUsername || '',
    // smtpPassword never filled from API
    smtpEncryption: (s.smtp_encryption || s.smtpEncryption || '').toString().toLowerCase(),
  };
}

async function loadEmailConfig() {
  // Prefer BusinessSettings API category 'email'
  try {
    const data = await BusinessSettingsAPI.getByCategory('email');
    // Expect shape: { success: true, settings: { key: value, ... } } or direct map
    const settings = (data && (data.settings || data.data || data)) || {};
    return normalizeEmailConfigFromSettings(settings);
  } catch (e) {
    console.warn('[AdminSettingsBridge] BusinessSettingsAPI email fetch failed; falling back to legacy endpoint', e);
  }
  // Fallback to legacy endpoint to preserve behavior
  try {
    const legacy = await ApiClient.get('/api/get_email_config.php');
    if (legacy && legacy.success && legacy.config) return legacy.config;
    return legacy?.config || {};
  } catch (e) {
    console.error('[AdminSettingsBridge] Legacy email config fetch failed', e);
    return {};
  }
}

function applyEmailConfig(cfg){
  setVal('fromEmail', cfg.fromEmail);
  setVal('fromName', cfg.fromName);
  setVal('adminEmail', cfg.adminEmail);
  setVal('bccEmail', cfg.bccEmail);
  setChecked('smtpEnabled', cfg.smtpEnabled);
  setVal('smtpHost', cfg.smtpHost);
  if (cfg.smtpPort !== undefined && cfg.smtpPort !== null && cfg.smtpPort !== '') setVal('smtpPort', String(cfg.smtpPort));
  setVal('smtpUsername', cfg.smtpUsername);
  setVal('smtpEncryption', cfg.smtpEncryption);
  // Toggle SMTP settings visibility
  const en = byId('smtpEnabled'); const ss = byId('smtpSettings');
  if (ss && en) {
    if (en.checked) ss.classList.remove('hidden');
    else ss.classList.add('hidden');
  }
}

function wireToggles(){
  const en = byId('smtpEnabled'); const ss = byId('smtpSettings');
  if (en && ss) {
    en.addEventListener('change', () => {
      if (en.checked) ss.classList.remove('hidden');
      else ss.classList.add('hidden');
    });
  }
}

function wireTestEmail(){
  const btn = document.querySelector('[data-action="email-send-test"]');
  const input = byId('testEmailAddress');
  if (!btn || !input) return;
  const isValidEmail = (v) => /.+@.+\..+/.test(v);
  btn.addEventListener('click', async () => {
    const to = (input.value || '').trim();
    if (!isValidEmail(to)) {
      showToast('error', 'Invalid Email', 'Enter a valid test email address.');
      input.focus();
      return;
    }
    btn.disabled = true;
    const orig = btn.textContent;
    btn.textContent = 'Sending…';
    try {
      const res = await fetch('/api/email_test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ to })
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok && data && data.success) {
        showToast('success', 'Test Email Sent', `A test email was sent to ${to}.`);
      } else {
        const err = data && data.error ? data.error : `HTTP ${res.status}`;
        showToast('error', 'Test Email Failed', err);
      }
    } catch (e) {
      showToast('error', 'Network Error', e?.message || 'Unable to send test email.');
    } finally {
      btn.disabled = false;
      btn.textContent = orig;
    }
  });
}

function collectEmailSettings() {
  const get = (id) => (byId(id) ? byId(id).value.trim() : '');
  const getBool = (id) => !!(byId(id) && byId(id).checked);
  const getNum = (id) => {
    const v = get(id);
    if (v === '') return '';
    const n = Number(v);
    return Number.isFinite(n) ? n : '';
  };

  const payload = {
    from_email: get('fromEmail'),
    from_name: get('fromName'),
    admin_email: get('adminEmail'),
    bcc_email: get('bccEmail'),
    smtp_enabled: getBool('smtpEnabled') ? 1 : 0,
    smtp_host: get('smtpHost'),
    smtp_port: getNum('smtpPort'),
    smtp_encryption: get('smtpEncryption'),
  };
  // Only include secrets if user provided new values
  const smtpUsername = get('smtpUsername');
  if (smtpUsername !== '') payload.smtp_username = smtpUsername;
  const smtpPassword = get('smtpPassword');
  if (smtpPassword !== '') payload.smtp_password = smtpPassword;

  return payload;
}

function showToast(type, title, message) {
  if (typeof window.showNotification === 'function') {
    window.showNotification({ type, title, message });
  } else {
    const prefix = type === 'error' ? '[Error]' : type === 'success' ? '[Success]' : '[Info]';
    console.log(prefix, title || '', message || '');
    if (type === 'error') alert(`${title || 'Error'}\n${message || ''}`);
  }
}

function wireSaveHandler(){
  const form = byId('emailConfigForm');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    try {
      e.preventDefault();
    } catch(_) {}
    const settings = collectEmailSettings();
    // Basic validation
    if (!settings.from_email) {
      showToast('error', 'Missing From Email', 'Please enter a From Email address.');
      return;
    }
    if (settings.smtp_enabled) {
      if (!settings.smtp_host) {
        showToast('error', 'Missing SMTP Host', 'Please enter SMTP Host or disable SMTP.');
        return;
      }
      if (settings.smtp_port === '') {
        showToast('error', 'Missing SMTP Port', 'Please select an SMTP Port.');
        return;
      }
    }

    try {
      await BusinessSettingsAPI.upsert('email', settings);
      showToast('success', 'Email Settings Saved', 'Your email configuration has been updated.');
    } catch (err) {
      console.error('[AdminSettingsBridge] Failed to save email settings', err);
      showToast('error', 'Save Failed', err?.message || 'Could not save settings.');
    }
  });
}

async function initEmailSection(){
  const cfg = await loadEmailConfig();
  applyEmailConfig(cfg);
  wireToggles();
  wireSaveHandler();
  wireTestEmail();
}

function onReady(fn){
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
  else fn();
}

export function init(){
  if (typeof window !== 'undefined') {
    if (window.__WF_ADMIN_SETTINGS_BRIDGE_INIT) return; // idempotent guard
    window.__WF_ADMIN_SETTINGS_BRIDGE_INIT = true;
  }
  onReady(() => {
    // Only run on admin settings page
    const body = document.body;
    const path = (body?.dataset?.path || location.pathname || '').toLowerCase();
    const isSettings = (
      body?.dataset?.page === 'admin/settings'
      || (body?.dataset?.isAdmin === 'true' && (
        path.includes('/admin/settings')
        || path.includes('/admin/admin_settings')
        || path.endsWith('admin_settings.php')
      ))
    );
    if (!isSettings) return;
    initEmailSection();

    // Helper show/hide for modals
    const showModal = (id) => {
      const el = document.getElementById(id);
      if (!el) return false;
      el.classList.remove('hidden');
      el.classList.add('show');
      el.setAttribute('aria-hidden', 'false');
      return true;
    };
    const hideModal = (id) => {
      const el = document.getElementById(id);
      if (!el) return false;
      el.classList.add('hidden');
      el.classList.remove('show');
      el.setAttribute('aria-hidden', 'true');
      return true;
    };

    // Safety sweep: ensure all managed overlays start hidden to avoid multi-open on load
    try {
      const managed = ['businessInfoModal','squareSettingsModal','emailSettingsModal','loggingStatusModal','aiSettingsModal','aiToolsModal','secretsModal','cssRulesModal'];
      managed.forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
          el.classList.add('hidden');
          el.classList.remove('show');
          el.setAttribute('aria-hidden', 'true');
        }
      });
    } catch(_) {}

    // Delegated clicks for opening/closing modals and routing
    document.addEventListener('click', (e) => {
      const t = e.target;
      const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
      const managedOverlays = new Set(['businessInfoModal','squareSettingsModal','emailSettingsModal','loggingStatusModal','aiSettingsModal','aiToolsModal','secretsModal']);

      // Overlay click closes when clicking on the overlay container
      if (t && t.classList && t.classList.contains('admin-modal-overlay')) {
        // Determine which modal by id
        const overlayId = t.id;
        if (overlayId && managedOverlays.has(overlayId)) {
          e.preventDefault();
          if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
          else e.stopPropagation();
          hideModal(overlayId);
        }
        return;
      }

      // Business Info
      if (closest('[data-action="open-business-info"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('businessInfoModal'); return; }
      if (closest('[data-action="close-business-info"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('businessInfoModal'); return; }

      // Square Settings
      if (closest('[data-action="open-square-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('squareSettingsModal'); return; }
      if (closest('[data-action="close-square-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('squareSettingsModal'); return; }

      // Email Settings
      if (closest('[data-action="open-email-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('emailSettingsModal'); return; }
      if (closest('[data-action="close-email-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('emailSettingsModal'); return; }
      // Open Email Test: open email modal and focus test input if present
      if (closest('[data-action="open-email-test"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); if (showModal('emailSettingsModal')) { const test = document.getElementById('testEmailAddress'); if (test) setTimeout(() => test.focus(), 50); } return; }

      // Logging Status
      if (closest('[data-action="open-logging-status"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('loggingStatusModal'); return; }
      if (closest('[data-action="close-logging-status"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('loggingStatusModal'); return; }

      // AI Settings
      if (closest('[data-action="open-ai-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('aiSettingsModal'); return; }
      if (closest('[data-action="close-ai-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('aiSettingsModal'); return; }

      // AI Tools
      if (closest('[data-action="open-ai-tools"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('aiToolsModal'); return; }
      if (closest('[data-action="close-ai-tools"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('aiToolsModal'); return; }

      // Account Settings fallback navigation if no modal
      if (closest('[data-action="open-account-settings"]')) {
        e.preventDefault();
        e.stopPropagation();
        if (!showModal('accountSettingsModal')) {
          window.location.href = '/admin.php?section=account_settings';
        }
        return;
      }

      // Secrets Manager fallback navigation if no modal
      if (closest('[data-action="open-secrets-modal"]')) {
        e.preventDefault();
        e.stopPropagation();
        if (!showModal('secretsModal')) {
          window.location.href = '/admin.php?section=secrets';
        }
        return;
      }

      // Email History: route to dashboard/email history anchor for now
      if (closest('[data-action="open-email-history"]')) {
        e.preventDefault();
        e.stopPropagation();
        window.location.href = '/admin.php?section=dashboard#email-history';
        return;
      }
    });
  });
}

// Auto-init when imported
init();

/* TooltipManager: Vite-managed admin tooltip system
 * - Fetches dynamic tooltip content from /api/help_tooltips.php
 * - Binds to elements by #id or [data-help-id]
 * - Provides window.toggleGlobalTooltips() and respects localStorage flag
 * - Lightweight, no external deps
 */

import { ApiClient } from '../core/api-client.js';
const API_URL = '/api/help_tooltips.php';
const LS_KEY_ENABLED = 'wf_tooltips_enabled';
const SS_KEY_SESSION_ENABLE = 'wf_tooltips_session_enabled';

function getPageContext() {
  try {
    const ds = document.body?.dataset || {};
    // Admin section from router query param or body data-page
    const params = new URLSearchParams(window.location.search || '');
    const qSection = (params.get('section') || '').toLowerCase();
    const bodyPage = (ds.page || '').toLowerCase();
    if (qSection) return qSection;
    if (bodyPage && bodyPage.startsWith('admin/')) return bodyPage.split('/')[1];
    if (bodyPage.startsWith('admin')) return bodyPage.replace(/^admin\/?/, '');
    // Fallback: path segment after /admin/
    const parts = (window.location.pathname || '').split('?')[0].split('#')[0].split('/').filter(Boolean);
    if (parts.join('/') === 'sections/admin_router.php' && qSection) {
        return qSection;
    }
    const idx = parts.indexOf('admin');
    if (idx >= 0 && parts[idx + 1]) return parts[idx + 1].replace(/\.php$/i, '');
  } catch {}
  return 'settings';
}

function isEnabled() {
  try {
    // Session override: if explicitly enabled for this session, honor it
    const sessionOverride = sessionStorage.getItem(SS_KEY_SESSION_ENABLE);
    if (sessionOverride === 'true') return true;
  } catch {}
  try { return localStorage.getItem(LS_KEY_ENABLED) !== 'false'; } catch { return true; }
}
function setEnabled(v) {
  try { localStorage.setItem(LS_KEY_ENABLED, v ? 'true' : 'false'); } catch {}
}

function createTooltipEl(title, content, position = 'top') {
  const tip = document.createElement('div');
  tip.className = `wf-tooltip wf-tooltip--${position}`;
  tip.innerHTML = `
    <div class="wf-tooltip__content">
      ${title ? `<div class="wf-tooltip__title">${title}</div>` : ''}
      <div class="wf-tooltip__body">${content || ''}</div>
    </div>
    <div class="wf-tooltip__arrow"></div>
  `;
  return tip;
}

let __wfTipStyleEl = null;
let __wfTipRuleCounter = 0;
const __wfAttachedTooltipTargets = new WeakSet();

function ensureTipStyleEl() {
  if (!__wfTipStyleEl) {
    __wfTipStyleEl = document.createElement('style');
    __wfTipStyleEl.setAttribute('data-wf-tooltip-dynamic', '1');
    document.head.appendChild(__wfTipStyleEl);
  }
  return __wfTipStyleEl;
}

function computeTooltipPosition(target, tip, position) {
  const rect = target.getBoundingClientRect();
  const tipRect = tip.getBoundingClientRect();
  const topOffsets = {
    top: rect.top + window.scrollY - tipRect.height - 8,
    bottom: rect.bottom + window.scrollY + 8,
    left: rect.top + window.scrollY + (rect.height - tipRect.height) / 2,
    right: rect.top + window.scrollY + (rect.height - tipRect.height) / 2,
  };
  let left = rect.left + window.scrollX + (rect.width - tipRect.width) / 2;
  let top = topOffsets.top;
  if (position === 'bottom') top = topOffsets.bottom;
  if (position === 'left') { left = rect.left + window.scrollX - tipRect.width - 8; top = topOffsets.left; }
  if (position === 'right') { left = rect.right + window.scrollX + 8; top = topOffsets.right; }
  return {
    left: Math.max(8, left),
    top: Math.max(8, top)
  };
}

function attachTooltip(target, tipData) {
  if (!target || __wfAttachedTooltipTargets.has(target)) return;
  __wfAttachedTooltipTargets.add(target);
  let tip;
  let showTimer = null;
  const doShow = () => {
    if (!isEnabled()) return;
    hide();
    tip = createTooltipEl(tipData.title, tipData.content, tipData.position || 'top');
    document.body.appendChild(tip);
    // Compute position and inject via dynamic CSS rule to avoid inline styles
    const pos = computeTooltipPosition(target, tip, tipData.position || 'top');
    const ruleId = (++__wfTipRuleCounter);
    const cls = `wf-tip-pos-${ruleId}`;
    tip.classList.add(cls);
    const styleEl = ensureTipStyleEl();
    try {
      const sheet = styleEl.sheet;
      const rule = `.wf-tooltip.${cls}{ left:${pos.left}px; top:${pos.top}px; }`;
      // Insert rule at the end
      sheet.insertRule(rule, sheet.cssRules.length);
    } catch(_) {
      // Fallback: appendText if insertRule fails
      styleEl.appendChild(document.createTextNode(`.wf-tooltip.${cls}{ left:${pos.left}px; top:${pos.top}px; }`));
    }
  };
  const hide = () => {
    if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
    tip = null;
    if (showTimer) { clearTimeout(showTimer); showTimer = null; }
  };
  const scheduleShow = () => {
    if (showTimer) { clearTimeout(showTimer); showTimer = null; }
    showTimer = setTimeout(() => { doShow(); }, 1000);
  };
  const cancelShow = () => { if (showTimer) { clearTimeout(showTimer); showTimer = null; } };
  target.addEventListener('mouseenter', scheduleShow);
  target.addEventListener('mouseleave', () => { cancelShow(); hide(); });
  target.addEventListener('focus', scheduleShow);
  target.addEventListener('blur', () => { cancelShow(); hide(); });
  // Note: intentionally avoid click-based toggle to prevent duplicate tooltips
  // and to avoid interfering with modal clicks/focus management.
}

function mapSettingsSelectors(elementId) {
  // Non-invasive mapping from known IDs to current Settings selectors
  const pairs = [
    ['dashboardConfigBtn', '[data-action="open-dashboard-config"]'],
    ['globalColorSizeBtn', 'a[href*="#attributes"], a[href*="color" i]'],
    ['roomsBtn', 'a[href*="/sections/admin_router.php?section=room_main"], [data-action="open-room-settings"]'],
    ['roomCategoryBtn', 'a[href*="#room-category-links"]'],
    ['templateManagerBtn', 'a[href*="#templates"]'],
    ['globalCSSBtn', 'a[href*="#css"], [data-action="open-css-rules"]'],
    ['backgroundManagerBtn', 'a[href*="#background"]'],
    ['roomMapperBtn', 'a[href*="#mapper"]'],
    ['areaItemMapperBtn', 'a[href*="#area-mapper"]'],
    ['aiSettingsBtn', '[data-action="open-ai-settings"]'],
    ['cssRulesBtn', '[data-action="open-css-rules"]'],
    ['squareSettingsBtn', '[data-action="open-square-settings"], #squareSettingsBtn'],
    ['emailConfigBtn', '[data-action="open-email-settings"]'],
    ['emailHistoryBtn', '[data-action="open-email-history"]'],
    ['fixSampleEmailBtn', '[data-action="open-email-test"]'],
    ['systemDocumentationBtn', '[data-action="open-system-documentation"]'],
    ['databaseTablesBtn', '[data-action="open-database-tables"]'],
    ['databaseMaintenanceBtn', '[data-action="open-database-maintenance"]'],
    // Admin navigation help elements
    ['adminHelpDocsBtn', '#adminHelpDocsBtn, [data-action="open-admin-help-modal"]'],
    ['adminHelpToggleBtn', '#adminHelpToggleBtn, [data-action="help-toggle-global-tooltips"]'],
    ['adminHelpCombo', '#adminHelpCombo'],
    // Newly mapped items
    ['businessInfoBtn', '[data-action="open-business-info"]'],
    ['loggingStatusBtn', '[data-action="open-logging-status"]'],
    ['receiptMessagesBtn', 'a[href="/receipt.php"]'],
    ['accountSettingsBtn', '[data-action="open-account-settings"], a[href="/sections/admin_router.php?section=account_settings"]'],
    ['secretsManagerBtn', '[data-action="open-secrets-modal"], a[href="/sections/admin_router.php?section=secrets"]'],
    ['costBreakdownBtn', 'a[href="/sections/admin_router.php?section=cost-breakdown-manager"]'],
    ['userManagerBtn', 'a[href="/sections/admin_router.php?section=customers"]'],
    // Square modal action buttons
    ['squareSaveBtn', '[data-action="square-save-settings"]'],
    ['squareTestBtn', '[data-action="square-test-connection"]'],
    ['squareSyncItemsBtn', '[data-action="square-sync-items"]'],
    ['squareClearTokenBtn', '[data-action="square-clear-token"]'],
    // CSS rules modal actions
    ['cssRulesSaveBtn', '[data-action="css-rules-save"]'],
    ['cssRulesCloseBtn', '[data-action="close-css-rules"]'],
    // Business info modal actions
    ['businessInfoSaveBtn', '[data-action="business-info-save"]'],
    ['businessInfoCloseBtn', '[data-action="close-business-info"]'],
    // AI settings modal actions
    ['aiSettingsSaveBtn', '[data-action="save-ai-settings"]'],
    ['aiSettingsTestBtn', '[data-action="test-ai-settings"]'],
    // Email settings modal close
    ['emailSettingsCloseBtn', '[data-action="close-email-settings"]'],
    // AI tools modal actions
    ['aiRunDiagnosticsBtn', '[data-action="ai-run-diagnostics"]'],
    ['aiClearCacheBtn', '[data-action="ai-clear-cache"]'],
    ['aiRefreshProvidersBtn', '[data-action="ai-refresh-providers"]'],
  ];
  const m = new Map(pairs);
  const sel = m.get(elementId);
  if (!sel) return null;
  try { return document.querySelector(sel); } catch { return null; }
}

async function loadTooltips() {
  const contextRaw = getPageContext();
  // Normalize likely values to our API expectations
  const aliases = { 'admin_settings': 'settings', 'admin-settings': 'settings' };
  const page_context = aliases[contextRaw] || contextRaw || 'settings';

  try {
    // Try both the specific page context and "admin" for admin pages
    const contexts = [page_context];
    if (page_context !== 'admin') {
      contexts.push('admin');
    }
    
    let allTooltips = [];
    for (const context of contexts) {
      const url = `${API_URL}?action=get&page_context=${encodeURIComponent(context)}`;
      const json = await ApiClient.get(url);
      if (json?.success && json.tooltips) {
        allTooltips = allTooltips.concat(json.tooltips);
      }
    }
    
    const tooltips = allTooltips;

    tooltips.forEach(tt => {
      const id = tt.element_id;
      let target = document.getElementById(id);
      if (!target) target = document.querySelector(`[data-help-id="${CSS.escape(id)}"]`);
      if (!target && page_context === 'settings') {
        target = mapSettingsSelectors(id);
      }
      // Fallback: allow element_id to reference a data-action
      if (!target) {
        try { target = document.querySelector(`[data-action="${CSS.escape(id)}"]`); } catch {}
      }
      if (!target) return;
      attachTooltip(target, { title: tt.title, content: tt.content, position: tt.position || 'top' });
    });
  } catch (e) {
    console.warn('[TooltipManager] Failed to load tooltips', e);
  }
}

function ensureCss() {
  // CSS is imported via main.css (src/styles/components/tooltip.css)
}

export default function initializeTooltipManager() {
  ensureCss();
  loadTooltips();
  
  // Also try loading tooltips after a short delay to catch dynamically loaded elements
  setTimeout(() => {
    loadTooltips();
  }, 250);
  
  window.toggleGlobalTooltips = function() {
    const next = !isEnabled();
    setEnabled(next);
    // Provide immediate feedback by removing any visible tips
    document.querySelectorAll('.wf-tooltip').forEach(el => el.remove());
    return next;
  };
}

// Auto-init on admin pages
try {
  const isAdmin = (/^\/?admin(\/|$)/i.test(location.pathname)) || (/^\/sections\/admin_router\.php$/i.test(location.pathname));
  if (isAdmin) {
    initializeTooltipManager();
  }
} catch {}

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
const TOOLTIP_SHOW_DELAY = 1000; // ms

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
  tip.className = `wf-tooltip wf-tooltip--managed wf-tooltip--${position}`;
  tip.innerHTML = `
    <div class="wf-tooltip__content">
      ${title ? `<div class="wf-tooltip__title">${title}</div>` : ''}
      <div class="wf-tooltip__body">${content || ''}</div>
    </div>
    <div class="wf-tooltip__arrow"></div>
  `;
  return tip;
}

const __wfAttachedTooltipTargets = new WeakSet();

// Dedicated stylesheet for positioning rules to avoid inline styles
let __wfTooltipPosStyle = null;
function ensurePositionStylesheet() {
  try {
    if (__wfTooltipPosStyle && document.contains(__wfTooltipPosStyle)) return __wfTooltipPosStyle.sheet;
    let el = document.querySelector('style[data-wf-tooltip-positions]');
    if (!el) {
      el = document.createElement('style');
      el.setAttribute('data-wf-tooltip-positions', '1');
      document.head.appendChild(el);
    }
    __wfTooltipPosStyle = el;
    return el.sheet;
  } catch { return null; }
}

let __wfTipSeq = 0;
function applyPositionClass(tip, left, top) {
  const sheet = ensurePositionStylesheet();
  if (!sheet) return { cls: null, ruleIndex: -1 };
  const cls = `wf-tip-pos-${++__wfTipSeq}`;
  const selector = `.wf-tooltip.wf-tooltip--managed.${cls}`;
  const rule = `${selector}{left:${Math.max(0, Math.round(left))}px;top:${Math.max(0, Math.round(top))}px;}`;
  let ruleIndex = -1;
  try { ruleIndex = sheet.insertRule(rule, sheet.cssRules.length); } catch(_) {}
  if (cls) tip.classList.add(cls);
  return { cls, ruleIndex };
}
function removePositionRule(cls, ruleIndex) {
  try {
    const sheet = ensurePositionStylesheet();
    if (!sheet) return;
    // If we have the exact index and it matches the selector, delete directly
    if (ruleIndex >= 0 && sheet.cssRules && sheet.cssRules[ruleIndex] && sheet.cssRules[ruleIndex].selectorText === `.wf-tooltip.wf-tooltip--managed.${cls}`) {
      sheet.deleteRule(ruleIndex);
      return;
    }
    // Fallback: search and delete the first matching rule
    for (let i = 0; i < sheet.cssRules.length; i++) {
      const r = sheet.cssRules[i];
      if (r.selectorText === `.wf-tooltip.wf-tooltip--managed.${cls}`) { sheet.deleteRule(i); break; }
    }
  } catch(_) {}
}

function computeTooltipPosition(target, tip, position, { isFixed = true } = {}) {
  const rect = target.getBoundingClientRect();
  const tipRect = tip.getBoundingClientRect();
  const sy = isFixed ? 0 : window.scrollY;
  const sx = isFixed ? 0 : window.scrollX;
  const topOffsets = {
    top: rect.top + sy - tipRect.height - 8,
    bottom: rect.bottom + sy + 8,
    left: rect.top + sy + (rect.height - tipRect.height) / 2,
    right: rect.top + sy + (rect.height - tipRect.height) / 2,
  };
  let left = rect.left + sx + (rect.width - tipRect.width) / 2;
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
  try { if (target.hasAttribute && target.hasAttribute('title')) { target.setAttribute('data-wf-orig-title', target.getAttribute('title') || ''); target.removeAttribute('title'); } } catch(_){
  }
  let tip;
  let showTimer = null;
  let posClass = null; let posRuleIndex = -1;
  const doShow = () => {
    if (!isEnabled()) return;
    hide();
    tip = createTooltipEl(tipData.title, tipData.content, tipData.position || 'top');
    document.body.appendChild(tip);
    // Compute position for fixed tooltips (viewport-relative)
    const pos = computeTooltipPosition(target, tip, tipData.position || 'top', { isFixed: true });
    // Apply positioning via a generated CSS class (no inline styles)
    try { const r = applyPositionClass(tip, pos.left, pos.top); posClass = r.cls; posRuleIndex = r.ruleIndex; } catch(_) {}
    // Optional debug
    try {
      if (sessionStorage.getItem('wf_tooltip_debug') === '1') {
        console.info('[TooltipManager] show tip', { title: tipData.title, pos });
        tip.classList.add('wf-tooltip--debug');
      }
    } catch(_) {}
  };
  const hide = () => {
    if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
    if (posClass) { removePositionRule(posClass, posRuleIndex); posClass = null; posRuleIndex = -1; }
    tip = null;
    if (showTimer) { clearTimeout(showTimer); showTimer = null; }
  };
  const scheduleShow = () => {
    if (showTimer) { clearTimeout(showTimer); showTimer = null; }
    // Make tips feel snappier
    showTimer = setTimeout(() => { doShow(); }, TOOLTIP_SHOW_DELAY);
  };
  const cancelShow = () => { if (showTimer) { clearTimeout(showTimer); showTimer = null; } };
  target.addEventListener('mouseenter', scheduleShow);
  target.addEventListener('mouseleave', () => { cancelShow(); hide(); });
  target.addEventListener('focus', scheduleShow);
  target.addEventListener('blur', () => { cancelShow(); hide(); });
  // Note: intentionally avoid click-based toggle to prevent duplicate tooltips
  // and to avoid interfering with modal clicks/focus management.
}

// Only attach tooltips to relevant interactive controls (avoid inputs/text fields)
function isRelevantTooltipTarget(el) {
  try {
    if (!el) return false;
    // Prefer explicit interactive elements
    if (el.matches && el.matches('button, a, [role="button"]')) return true;
    // Elements with button-like classes
    if (el.matches && el.matches('.btn, .wf-admin-nav-button, .wf-modal-button')) return true;
    if (el.matches && el.matches('label, .field-label, [data-field-label]')) return true;
    // Exclude common form fields
    if (el.matches && el.matches('input, select, textarea')) return false;
    // Exclude generic containers (forms/divs) unless they are explicitly button-like
    if (el.tagName === 'FORM') return false;
    // Fallback: if nested inside a button, consider it relevant
    if (el.closest && el.closest('button, [role="button"], .btn, .wf-admin-nav-button, .wf-modal-button')) return true;
  } catch(_) {}
  return false;
}

function remapToLabelTarget(el) {
  try {
    if (!el) return null;
    if (el.matches && el.matches('label, .field-label, [data-field-label]')) return el;
    if (el.matches && el.matches('input, select, textarea')) {
      const id = el.id || '';
      if (id) {
        const byFor = document.querySelector(`label[for="${CSS.escape(id)}"]`);
        if (byFor) return byFor;
      }
      const group = el.closest('.form-group, .field, .input-group, .form-row, .wf-field');
      if (group) {
        const lbl = group.querySelector('label, .field-label, [data-field-label]');
        if (lbl) return lbl;
      }
      const prev = el.previousElementSibling;
      if (prev && prev.matches && prev.matches('label, .field-label, [data-field-label]')) return prev;
      const parent = el.parentElement;
      if (parent) {
        const lbl = parent.querySelector('label, .field-label, [data-field-label]');
        if (lbl) return lbl;
      }
      return null;
    }
    return el;
  } catch(_) { return el; }
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
  // Global guard: allow pages to temporarily block tooltip attachment during heavy UI work
  try { if (window.__WF_BLOCK_TOOLTIP_ATTACH) { console.info('[TooltipManager] Skipping attach (blocked)'); return; } } catch(_) {}
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
    // Also include common tooltips shared across pages
    if (!contexts.includes('common')) contexts.push('common');
    
    let allTooltips = [];
    for (const context of contexts) {
      const url = `${API_URL}?action=get&page_context=${encodeURIComponent(context)}`;
      const json = await ApiClient.get(url);
      if (json?.success && json.tooltips) {
        allTooltips = allTooltips.concat(json.tooltips);
      }
    }
    
    const tooltips = allTooltips;
    // Expose loaded tooltips for audit tools
    try { window.__WF_LOADED_TOOLTIPS = tooltips.slice(); } catch(_) {}
    console.info('[TooltipManager] Loaded tooltips', { page_context, contexts, count: tooltips.length });

    // Prepare quick lookup of existing element IDs
    const existingIds = new Set(Array.from(document.querySelectorAll('[id]')).map(el => el.id));
    let attached = 0;
    const uniqueTargets = new Set();
    const perIdAttachedCount = Object.create(null);
    const missing = [];

    const resolveTargets = (raw) => {
      if (!raw) return [];
      const s = String(raw).trim();
      // If looks like a selector, honor it directly
      if (s.startsWith('#') || s.startsWith('.') || s.startsWith('[')) {
        try { return Array.from(document.querySelectorAll(s)); } catch { return []; }
      }
      // data-action:VALUE explicit prefix support (and alias action:VALUE)
      if (s.toLowerCase().startsWith('data-action:') || s.toLowerCase().startsWith('action:')) {
        const lower = s.toLowerCase();
        const prefix = lower.startsWith('data-action:') ? 'data-action:' : 'action:';
        const val = s.slice(prefix.length);
        try {
          const nodes = Array.from(document.querySelectorAll(`[data-action="${CSS.escape(val)}"]`));
          if (String(val).toLowerCase() === 'prevent-submit') {
            const targets = [];
            for (const el of nodes) {
              if (el && el.tagName === 'FORM') {
                const btn = el.querySelector('button[type="submit"], button[id*="save" i], [data-action*="save" i], .btn.btn-primary');
                if (btn) targets.push(btn);
              } else if (el && el.matches && el.matches('button, [role="button"]')) {
                targets.push(el);
              }
            }
            return targets;
          }
          return nodes;
        } catch { return []; }
      }
      // Direct ID hit
      if (existingIds.has(s)) return [document.getElementById(s)];
      // Mapped selectors for settings page
      if (page_context === 'settings') {
        const m = mapSettingsSelectors(s);
        if (m) return [m];
      }
      // Special-case mappings for known generic IDs commonly found in DB
      const specials = {
        adminHelpDocsLink: '#adminHelpDocsBtn, [data-action="open-admin-help-modal"]',
        closeBtn: '.admin-modal-close, [data-action="close-admin-modal"], [id*="close" i]',
        helpBtn: '[data-action*="help" i], [id*="help" i]',
        importBtn: '[data-action*="import" i], [id*="import" i]',
        previewBtn: '[data-action*="preview" i], [id*="preview" i]',
        resetBtn: '[data-action*="reset" i], [id*="reset" i]',
        duplicateBtn: '[data-action*="duplicate" i], [id*="duplicate" i]',
        'cancel-btn': '[data-action*="cancel" i], [id*="cancel" i]',
        saveBtn: '[data-action*="save" i], [id*="save" i]',
        'save-btn': '[data-action*="save" i], [id*="save" i]'
      };
      if (Object.prototype.hasOwnProperty.call(specials, s)) {
        try { const els = Array.from(document.querySelectorAll(specials[s])); if (els.length) return els; } catch {}
      }
      // Heuristic: try keyword search in common interactive elements
      const tryKeywords = (keywords) => {
        const sel = ['button', 'a', '[role="button"]', '[data-action]'].join(',');
        try {
          const nodes = Array.from(document.querySelectorAll(sel));
          const hits = [];
          for (const n of nodes) {
            const txt = (n.textContent || '').toLowerCase();
            const id = (n.id || '').toLowerCase();
            const da = (n.getAttribute('data-action') || '').toLowerCase();
            if (keywords.some(k => txt.includes(k) || id.includes(k) || da.includes(k))) hits.push(n);
          }
          if (hits.length) return hits;
        } catch {}
        return [];
      };
      // Map common ends-with patterns like "...Btn" or kebab-case names
      const lower = s.toLowerCase();
      if (lower.includes('cancel')) { const els = tryKeywords(['cancel']); if (els.length) return els; }
      if (lower.includes('close')) { const els = tryKeywords(['close']); if (els.length) return els; }
      if (lower.includes('duplicate')) { const els = tryKeywords(['duplicate']); if (els.length) return els; }
      if (lower.includes('save')) { const els = tryKeywords(['save', 'apply']); if (els.length) return els; }
      if (lower.includes('help')) { const els = tryKeywords(['help']); if (els.length) return els; }
      if (lower.includes('import')) { const els = tryKeywords(['import']); if (els.length) return els; }
      if (lower.includes('preview')) { const els = tryKeywords(['preview']); if (els.length) return els; }
      if (lower.includes('reset')) { const els = tryKeywords(['reset', 'default']); if (els.length) return els; }
      if (lower.includes('move')) { const els = tryKeywords(['move up','move down','move']); if (els.length) return els; }
      // data-help-id fallback
      try { const els = Array.from(document.querySelectorAll(`[data-help-id="${CSS.escape(s)}"]`)); if (els.length) return els; } catch {}
      // data-action equals raw
      try { const els = Array.from(document.querySelectorAll(`[data-action="${CSS.escape(s)}"]`)); if (els.length) return els; } catch {}
      return [];
    };

    const GENERIC_KEYS = ['cancel','close','duplicate','help','import','preview','reset','save','move-up','move-down','move'];
    const isGenericId = (id) => {
      const s = String(id || '').toLowerCase();
      if (s === 'adminhelpdocslink' || s === 'cancel-btn') return true;
      if (GENERIC_KEYS.some(k => s.includes(k))) return true;
      return false;
    };
    // Per-page rules (tweak caps/blacklist by page)
    // Trimmed to reduce DOM work on admin pages while keeping at least one attachment per row.
    const pageRules = {
      // Admin contexts
      orders:    { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] },
      inventory: { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] },
      customers: { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] },
      reports:   { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] },
      marketing: { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] },
      pos:       { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] },
      'db-status': { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] },
      'room-config-manager': { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] },
      'cost-breakdown-manager': { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] },
      admin:     { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] },
      // Settings tends to have more forms; allow slightly higher but still trimmed
      settings:  { maxGenericPerId: 2, maxPerTooltip: 1, blacklist: [] },
      // Shared/common fallbacks
      common:    { maxGenericPerId: 2, maxPerTooltip: 1, blacklist: [] },
    };
    const rules = pageRules[page_context] || { maxGenericPerId: 1, maxPerTooltip: 1, blacklist: [] };
    const MAX_GENERIC_ATTACH_PER_ID = rules.maxGenericPerId ?? 1;
    const MAX_ATTACH_PER_TOOLTIP = rules.maxPerTooltip ?? 1;

    const attachedTargets = [];
    for (const tt of tooltips) {
      let targets = resolveTargets(tt.element_id);
      if (!targets.length) { missing.push(tt.element_id); continue; }
      const data = { title: tt.title, content: tt.content, position: tt.position || 'top' };
      // Skip blacklisted IDs and cap generic attachments per ID to avoid over-attachment on repeated controls
      if (rules.blacklist && rules.blacklist.includes(tt.element_id)) {
        continue;
      }
      if (isGenericId(tt.element_id)) {
        const count = perIdAttachedCount[tt.element_id] || 0;
        const remain = Math.max(0, MAX_GENERIC_ATTACH_PER_ID - count);
        if (remain <= 0) { continue; }
        if (targets.length > remain) targets = targets.slice(0, remain);
      }
      // Additional cap: limit attachments per tooltip row to keep DOM light on admin pages
      if (targets.length > MAX_ATTACH_PER_TOOLTIP) targets = targets.slice(0, MAX_ATTACH_PER_TOOLTIP);
      for (const t of targets) {
        const mapped = remapToLabelTarget(t);
        if (!mapped) continue;
        if (!isRelevantTooltipTarget(mapped) && !(mapped.matches && mapped.matches('label, .field-label, [data-field-label]'))) continue;
        attachTooltip(mapped, data);
        attached++;
        uniqueTargets.add(t);
        perIdAttachedCount[tt.element_id] = (perIdAttachedCount[tt.element_id] || 0) + 1;
        if (attachedTargets.length < 6) attachedTargets.push({ target: t, data });
      }
    }

    // Retry attaching to missing IDs when new nodes are added to DOM (for dynamic UIs)
    try {
      if (!window.__WF_TOOLTIP_RETRY_BOUND && missing.length) {
        window.__WF_TOOLTIP_RETRY_BOUND = true;
        let retryCount = 0;
        const MAX_RETRIES = 10;
        const pending = new Set(missing);
        const attempt = () => {
          if (!pending.size) return;
          const nowMissing = [];
          for (const id of Array.from(pending)) {
            const targets = resolveTargets(id);
            if (targets.length) {
              const tt = tooltips.find(t => t.element_id === id);
              const data = tt ? { title: tt.title, content: tt.content, position: tt.position || 'top' } : { title: '', content: '', position: 'top' };
              for (const t of targets) {
                if (!isRelevantTooltipTarget(t)) continue;
                attachTooltip(t, data);
              }
              pending.delete(id);
            } else {
              nowMissing.push(id);
            }
          }
          if (pending.size === 0) return;
          if (++retryCount <= MAX_RETRIES) setTimeout(attempt, 400);
        };
        const mo = new MutationObserver(() => { attempt(); });
        mo.observe(document.body, { childList: true, subtree: true });
        // Kick off initial retry attempts
        setTimeout(attempt, 250);
      }
    } catch(_) {}

    // Attempt attaching inside same-origin iframes for generic/action:* IDs (modal content)
    const attachInFrame = (doc) => {
      try {
        if (doc && ((doc.defaultView && doc.defaultView.__WF_BLOCK_TOOLTIP_ATTACH === true) || (doc.body && doc.body.getAttribute && doc.body.getAttribute('data-wf-block-tooltips') === '1'))) { return; }
        const q = (sel) => { try { return Array.from(doc.querySelectorAll(sel)); } catch { return []; } };
        const existingIdsFrame = new Set(Array.from(doc.querySelectorAll('[id]')).map(el => el.id));
        const resolveInFrame = (raw) => {
          if (!raw) return [];
          const s = String(raw);
          const lower = s.toLowerCase();
          if (lower.startsWith('data-action:') || lower.startsWith('action:')) {
            const prefix = lower.startsWith('data-action:') ? 'data-action:' : 'action:';
            const val = s.slice(prefix.length);
            if (String(val).toLowerCase() === 'prevent-submit') {
              const forms = q(`[data-action="${CSS.escape(val)}"]`);
              const targets = [];
              for (const f of forms) {
                if (f && f.tagName === 'FORM') {
                  const btn = f.querySelector('button[type="submit"], button[id*="save" i], [data-action*="save" i], .btn.btn-primary');
                  if (btn) targets.push(btn);
                } else if (f && f.matches && f.matches('button, [role="button"]')) {
                  targets.push(f);
                }
              }
              return targets;
            }
            return q(`[data-action="${CSS.escape(val)}"]`);
          }
          if (existingIdsFrame.has(s)) { const el = doc.getElementById(s); return el ? [el] : []; }
          const sel = ['button', 'a', '[role="button"]', '[data-action]'].join(',');
          let nodes = []; try { nodes = Array.from(doc.querySelectorAll(sel)); } catch {}
          const hits = [];
          for (const n of nodes) {
            const txt = (n.textContent || '').toLowerCase();
            const id = (n.id || '').toLowerCase();
            const da = (n.getAttribute('data-action') || '').toLowerCase();
            if (GENERIC_KEYS.some(k => id.includes(k) || da.includes(k) || txt.includes(k))) hits.push(n);
          }
          return hits;
        };
        for (const tt of tooltips) {
          const isAction = String(tt.element_id || '').toLowerCase().startsWith('action:');
          if (!isAction && !isGenericId(tt.element_id)) continue;
          let targets = resolveInFrame(tt.element_id);
          if (!targets.length) continue;
          if (isGenericId(tt.element_id)) {
            const count = perIdAttachedCount[tt.element_id] || 0;
            const remain = Math.max(0, MAX_GENERIC_ATTACH_PER_ID - count);
            if (remain <= 0) continue;
            if (targets.length > remain) targets = targets.slice(0, remain);
          }
          const data = { title: tt.title, content: tt.content, position: tt.position || 'top' };
          for (const t of targets) {
            const mapped = remapToLabelTarget(t);
            if (!mapped) continue;
            if (!isRelevantTooltipTarget(mapped) && !(mapped.matches && mapped.matches('label, .field-label, [data-field-label]'))) continue;
            attachTooltip(mapped, data);
            attached++;
            uniqueTargets.add(t);
            perIdAttachedCount[tt.element_id] = (perIdAttachedCount[tt.element_id] || 0) + 1;
          }
        }
      } catch (_) {}
    };
    try {
      const iframes = Array.from(document.querySelectorAll('iframe'));
      for (const frame of iframes) {
        const doc = frame.contentDocument || frame.contentWindow?.document;
        if (doc) attachInFrame(doc);
        // Attach on subsequent loads too
        try { frame.addEventListener('load', () => {
          const d2 = frame.contentDocument || frame.contentWindow?.document; if (d2) attachInFrame(d2);
        }, { once:false }); } catch(_) {}
      }
    } catch(_) {}

    // Concise summary
    const uniqCount = uniqueTargets.size;
    if (attached !== tooltips.length) {
      const sample = missing.slice(0, 8);
      console.warn('[TooltipManager] Attached', uniqCount, 'unique targets from', tooltips.length, 'DB rows. Missing sample:', sample);
    } else {
      console.info('[TooltipManager] Attached tooltips to unique targets:', uniqCount);
    }

    // If DB returned tooltips but none attached (selector/ID mismatches), attach a visible fallback
    if (tooltips.length > 0 && attached === 0) {
      try {
        const demoTargets = [
          '#adminHelpToggleBtn',
          '[data-action="help-toggle-global-tooltips"]',
          '[data-action="open-email-settings"]',
          '[data-action="open-business-info"]',
        ];
        for (const sel of demoTargets) {
          const el = document.querySelector(sel);
          if (el) {
            attachTooltip(el, { title: 'Help Tooltips', content: 'Hover to see contextual help. DB has entries, but element IDs/selectors may not match. We attached this fallback so you can see it working.', position: 'right' });
            console.info('[TooltipManager] Fallback tooltip attached to', sel);
            break;
          }
        }
      } catch(_) {}
    }

    if (!tooltips.length) {
      console.warn('[TooltipManager] No tooltips attached for this page. Ensure help_tooltips rows exist for contexts:', contexts);
      // Minimal fallback: attach a demo tooltip to the global help toggle if present
      try {
        const demoTarget = document.querySelector('#adminHelpToggleBtn, [data-action="help-toggle-global-tooltips"]');
        if (demoTarget) {
          attachTooltip(demoTarget, {
            title: 'Help Tooltips',
            content: 'Toggle this to enable/disable contextual help tooltips.',
            position: 'right'
          });
          console.info('[TooltipManager] Fallback demo tooltip attached to help toggle button');
        }
      } catch(_) {}
    }

    // Auto-preview removed after verification
  } catch (e) {
    console.warn('[TooltipManager] Failed to load tooltips', e);
  }
}

function ensureCss() {
  try {
    if (document.querySelector('style[data-wf-tooltip-base]')) return;
    const css = `/* WF Tooltip Base (injected) */\n` +
    `.wf-tooltip{position:absolute;z-index:12050;max-width:min(320px,90vw);color:#fff;pointer-events:none;display:block;opacity:1}` +
    `.wf-tooltip.wf-tooltip--managed{position:fixed}` +
    `.wf-tooltip__content{background:rgba(17,24,39,.98);border-radius:8px;padding:10px 12px;box-shadow:0 10px 25px rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.08)}` +
    `.wf-tooltip__title{font-weight:700;color:#c7d2fe;margin-bottom:4px}` +
    `.wf-tooltip__body{font-size:12px;line-height:1.45}` +
    `.wf-tooltip__arrow{display:none !important}` +
    `.wf-tooltip--debug{outline:1px solid #ef4444}`;
    const st = document.createElement('style');
    st.setAttribute('data-wf-tooltip-base','1');
    st.textContent = css;
    document.head.appendChild(st);
  } catch (_) { /* noop */ }
}

export default function initializeTooltipManager() {
  if (window.__WF_TIP_MGR_INIT) return; window.__WF_TIP_MGR_INIT = true;
  ensureCss();
  // Ensure enabled for this admin session (can be toggled off via UI)
  try { sessionStorage.setItem(SS_KEY_SESSION_ENABLE, 'true'); } catch(_) {}
  loadTooltips();
  
  // Also try loading tooltips after a short delay to catch dynamically loaded elements
  setTimeout(() => {
    try { if (window.__WF_BLOCK_TOOLTIP_ATTACH) { setTimeout(() => loadTooltips(), 250); return; } } catch(_) {}
    loadTooltips();
  }, 250);
  
  window.toggleGlobalTooltips = function() {
    const next = !isEnabled();
    setEnabled(next);
  };

  // Expose an audit helper for admins in console
  window.WF_TooltipAudit = {
    // List elements that likely need a tooltip and suggest upsert payloads
    suggest(pageCtxOverride) {
      try {
        const pageCtx = String(pageCtxOverride || ((document.body && document.body.dataset && document.body.dataset.page) || 'settings')).toLowerCase();
        const candidates = Array.from(document.querySelectorAll('button, a, [role="button"], [data-action], label'))
          .filter(el => !el.disabled && el.offsetParent !== null);
        const getId = (el) => el.id || el.getAttribute('data-help-id') || el.getAttribute('data-action') || '';
        const textOf = (el) => (el.getAttribute('aria-label') || el.textContent || '').trim().replace(/\s+/g,' ').slice(0,120);
        const loaded = (window.__WF_LOADED_TOOLTIPS || []);
        const existing = new Set(loaded.map(t => String(t.element_id)));
        const blacklisted = new Set([
          'adminHelpToggleBtn',
          'adminHelpDocsBtn',
          'adminHelpDocsLink',
          'adminHelpToggleGlobalTooltips',
          'adminHelpDocsModalClose',
          'adminHelpDocsModalOpen',
          'adminHelpDocsModalCancel',
          'adminHelpDocsModalSave',
          'adminHelpDocsModalReset',
          'adminHelpDocsModalPreview',
          'adminHelpDocsModalImport',
          'adminHelpDocsModalDuplicate',
          'adminHelpDocsModalDelete',
          'adminHelpDocsModalClose',
          'adminHelpDocsModalOpen',
          'adminHelpDocsModalCancel',
          'adminHelpDocsModalSave',
          'adminHelpDocsModalReset',
          'adminHelpDocsModalPreview',
          'adminHelpDocsModalImport',
          'adminHelpDocsModalDuplicate',
          'adminHelpDocsModalDelete',
        ]);
        const suggestions = [];
        for (const el of candidates) {
          let id = getId(el);
          if (!id && el.matches && el.matches('label')) {
            const forId = el.getAttribute('for');
            if (forId) id = `label[for="${forId}"]`;
          }
          if (el.matches && el.matches('input, select, textarea')) {
            const fid = el.id || '';
            if (fid) id = `label[for="${fid}"]`;
          }
          if (!id) continue;
          if (blacklisted.has(id)) continue;
          if (existing.has(id)) continue;
          // Skip very generic/noisy controls
          const low = id.toLowerCase();
          if (/(^|-)close(btn)?$/.test(low)) continue;
          if (/^toggle|^open-|^close-|^btn$/i.test(low)) continue;
          const label = textOf(el) || id;
          suggestions.push({
            element_id: id,
            page_context: pageCtx,
            title: label,
            content: `Pro tip: ${label}. Yes, it does exactly what you think — with a little extra flair.`,
            position: 'top',
            is_active: 1
          });
          if (suggestions.length >= 50) break;
        }
        console.info('[WF_TooltipAudit] suggestions', suggestions);
        return suggestions;
      } catch (e) { console.warn('[WF_TooltipAudit] failed', e); return []; }
    }
  };

  // Auto-reattach: throttled helper to avoid spamming loadTooltips
  (function(){
    let last = 0; let t = null;
    const THROTTLE_MS = 500;
    const reattach = () => {
      const now = Date.now();
      const elapsed = now - last;
      const run = () => { try { if (window.__WF_BLOCK_TOOLTIP_ATTACH) { setTimeout(() => reattach(), THROTTLE_MS); return; } loadTooltips(); last = Date.now(); } catch(_) {} };
      if (elapsed >= THROTTLE_MS) { run(); }
      else {
        clearTimeout(t); t = setTimeout(run, THROTTLE_MS - elapsed);
      }
    };
    window.__wfDebugTooltips = reattach; // keep existing name but now throttled

    // Clicks that likely open modals/panels
    const shouldTrigger = (el) => {
      if (!el) return false;
      const da = (el.getAttribute('data-action') || '').toLowerCase();
      if (da.startsWith('open-') || da.includes('-open-')) return true;
      const href = (el.getAttribute('href') || '').toLowerCase();
      if (href.includes('modal') || href.includes('#')) return true;
      return false;
    };
    document.addEventListener('click', (e) => {
      try {
        const el = e.target?.closest('a, button, [role="button"], [data-action]');
        if (shouldTrigger(el)) setTimeout(reattach, 350);
      } catch(_) {}
    }, true);

    // Observe DOM for iframes and modal containers being added
    try {
      const mo = new MutationObserver((mutations) => {
        for (const m of mutations) {
          for (const n of m.addedNodes) {
            if (!(n instanceof HTMLElement)) continue;
            if (n.tagName === 'IFRAME' || n.querySelector?.('iframe')) { setTimeout(reattach, 300); return; }
            if (n.classList?.contains('modal') || n.matches?.('[role="dialog"], .admin-modal, .room-modal-overlay')) { setTimeout(reattach, 300); return; }
          }
        }
      });
      mo.observe(document.body, { childList:true, subtree:true });
    } catch(_) {}
  })();
}

try {
  const ds = (document.body && document.body.dataset) || {};
  const page = (ds.page || '').toLowerCase();
  const isAdminPage = (
    ds.isAdmin === 'true'
    || page.includes('admin')
    || /admin_router\.php$/i.test(location.pathname)
    || location.search.includes('section=')
    || /^\/?admin(\/|$)/i.test(location.pathname)
  );
  if (isAdminPage) {
    try {
      initializeTooltipManager();
    } catch (_) {}
  }
  // __wfDebugTooltips is defined in initializeTooltipManager() and throttled
} catch (_) {}

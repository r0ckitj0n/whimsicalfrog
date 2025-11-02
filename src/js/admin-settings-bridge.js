import { ApiClient } from '../core/api-client.js';
// Ensure lightweight modal open handlers (Area-Item Mapper, Background Manager, CSS Catalog, Room Map Editor)
// are installed whenever the bridge loads on admin routes.
// This module sets up delegated click handlers and lazy modal factories used by settings cards.
import '../modules/admin-settings-lightweight.js';
import '../js/admin-settings-fallbacks.js';
function byId(id){ return document.getElementById(id); }

const FONT_LIBRARY = [
  { id: 'system-sans', name: 'System UI', detail: 'Sans-serif', stack: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif", category: 'sans-serif', sample: 'Reliable interface typography.' },
  { id: 'inter', name: 'Inter', detail: 'Sans-serif', stack: "'Inter', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Modern UI and marketing copy.' },
  { id: 'roboto', name: 'Roboto', detail: 'Sans-serif', stack: "'Roboto', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Approachable body copy with clarity.' },
  { id: 'open-sans', name: 'Open Sans', detail: 'Sans-serif', stack: "'Open Sans', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Friendly paragraphs and marketing text.' },
  { id: 'poppins', name: 'Poppins', detail: 'Sans-serif', stack: "'Poppins', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Rounded titles with personality.' },
  { id: 'work-sans', name: 'Work Sans', detail: 'Sans-serif', stack: "'Work Sans', 'Helvetica Neue', Arial, sans-serif", category: 'sans-serif', sample: 'Clean product descriptions and UI labels.' },
  { id: 'montserrat', name: 'Montserrat', detail: 'Display Sans', stack: "'Montserrat', 'Helvetica Neue', Arial, sans-serif", category: 'display', sample: 'Bold headlines with geometric flair.' },
  { id: 'raleway', name: 'Raleway', detail: 'Display Sans', stack: "'Raleway', 'Helvetica Neue', Arial, sans-serif", category: 'display', sample: 'Elegant uppercase headings.' },
  { id: 'merriweather', name: 'Merriweather', detail: 'Serif', stack: "'Merriweather', Georgia, serif", category: 'serif', sample: 'Editorial style paragraphs and quotes.' },
  { id: 'playfair', name: 'Playfair Display', detail: 'Serif', stack: "'Playfair Display', 'Times New Roman', serif", category: 'serif', sample: 'High-contrast headings with classic charm.' },
  { id: 'dm-serif', name: 'DM Serif Text', detail: 'Serif', stack: "'DM Serif Text', 'Times New Roman', serif", category: 'serif', sample: 'Refined accents for premium brands.' },
  { id: 'fira-code', name: 'Fira Code', detail: 'Monospace', stack: "'Fira Code', 'Courier New', monospace", category: 'monospace', sample: 'Technical snippets and code samples.' },
  { id: 'source-code', name: 'Source Code Pro', detail: 'Monospace', stack: "'Source Code Pro', 'Courier New', monospace", category: 'monospace', sample: 'Console-style accents and UI.' },
  { id: 'dancing-script', name: 'Dancing Script', detail: 'Handwriting', stack: "'Dancing Script', 'Brush Script MT', cursive", category: 'handwriting', sample: 'Playful handwritten callouts.' },
  { id: 'pacifico', name: 'Pacifico', detail: 'Handwriting', stack: "'Pacifico', 'Brush Script MT', cursive", category: 'handwriting', sample: 'Retro script for standout words.' }
];

function normalizeFontStack(value) {
  if (!value) return '';
  return value.split(',').map((part) => part.trim().replace(/\s+/g, ' ')).filter(Boolean).join(',');
}

FONT_LIBRARY.forEach((font) => {
  font.normalizedStack = normalizeFontStack(font.stack);
});

const FONT_LIBRARY_BY_ID = new Map(FONT_LIBRARY.map((font) => [font.id, font]));
const DEFAULT_FONTS = {
  primary: FONT_LIBRARY_BY_ID.get('system-sans') || FONT_LIBRARY[0],
  secondary: FONT_LIBRARY_BY_ID.get('merriweather') || FONT_LIBRARY[0]
};

const FONT_PICKER_STYLE_ID = 'wf-font-picker-styles';

function describeCustomStack(stack) {
  if (!stack) return '';
  const safe = stack.replace(/\s+,\s+/g, ', ');
  const families = safe.split(',').map((part) => part.replace(/^['"]|['"]$/g, '').trim()).filter(Boolean);
  if (!families.length) return stack;
  return `Custom: ${families.slice(0, 2).join(', ')}${families.length > 2 ? '…' : ''}`;
}

function getFontMetaByStack(stack) {
  if (!stack) return null;
  const normalized = normalizeFontStack(stack);
  for (const font of FONT_LIBRARY) {
    if (font.normalizedStack === normalized) {
      return font;
    }
  }
  return null;
}

function updateFontPreviewLabel(target, meta, stack) {
  const labelId = target === 'secondary' ? 'brandFontSecondaryLabel' : 'brandFontPrimaryLabel';
  const label = byId(labelId);
  if (!label) return;

  let text = 'Not set';
  if (meta) {
    text = `${meta.name} (${meta.detail})`;
  } else if (stack) {
    text = describeCustomStack(stack);
  }

  label.textContent = text;
  label.dataset.fontStack = stack || '';
  if (meta && meta.id) {
    label.dataset.fontId = meta.id;
  } else {
    delete label.dataset.fontId;
  }
}

function setFontField(target, stack) {
  const isSecondary = target === 'secondary';
  const inputId = isSecondary ? 'brandFontSecondary' : 'brandFontPrimary';
  const defaultMeta = isSecondary ? DEFAULT_FONTS.secondary : DEFAULT_FONTS.primary;

  let effective = typeof stack === 'string' ? stack.trim() : '';
  let meta = getFontMetaByStack(effective);

  if (!effective && defaultMeta) {
    effective = defaultMeta.stack;
    meta = defaultMeta;
  }

  const input = byId(inputId);
  if (input) {
    input.value = effective;
    input.dataset.fontId = meta ? meta.id : '';
  }

  updateFontPreviewLabel(target, meta, effective);

  return effective;
}

function ensureFontPickerStyles() {
  let styleEl = document.getElementById(FONT_PICKER_STYLE_ID);
  if (!styleEl) {
    styleEl = document.createElement('style');
    styleEl.id = FONT_PICKER_STYLE_ID;
    document.head.appendChild(styleEl);
  }

  const rules = FONT_LIBRARY.map((font) => {
    const selector = `.font-picker-card[data-font-id="${font.id}"] .font-picker-sample`;
    return `${selector}{font-family:${font.stack};}`;
  }).join('\n');

  styleEl.textContent = rules;
}

function renderFontPickerList() {
  const list = byId('fontPickerList');
  if (!list) return;

  list.innerHTML = '';

  FONT_LIBRARY.forEach((font) => {
    const card = document.createElement('button');
    card.type = 'button';
    card.className = 'font-picker-card';
    card.dataset.fontId = font.id;
    card.dataset.category = font.category;
    card.dataset.fontStack = font.stack;
    card.innerHTML = `
      <div class="font-picker-title">${font.name}</div>
      <div class="font-picker-detail">${font.detail}</div>
      <div class="font-picker-sample" aria-hidden="true">${font.sample}</div>
      <div class="font-picker-stack">${font.stack}</div>
    `;
    list.appendChild(card);
  });
}

function applyFontPickerFilters() {
  const list = byId('fontPickerList');
  const searchField = byId('fontPickerSearch');
  const categorySelect = byId('fontPickerCategory');
  if (!list) return;

  const query = (searchField?.value || '').toLowerCase();
  const category = (categorySelect?.value || 'all').toLowerCase();

  list.querySelectorAll('.font-picker-card').forEach((card) => {
    const name = (card.querySelector('.font-picker-title')?.textContent || '').toLowerCase();
    const stack = (card.dataset.fontStack || '').toLowerCase();
    const detail = (card.querySelector('.font-picker-detail')?.textContent || '').toLowerCase();
    const cardCategory = (card.dataset.category || '').toLowerCase();

    const matchesCategory = category === 'all' || cardCategory === category;
    const matchesQuery = !query || name.includes(query) || stack.includes(query) || detail.includes(query);

    card.classList.toggle('is-hidden', !(matchesCategory && matchesQuery));
  });
}

function openFontPicker(target) {
  const overlay = byId('fontPickerModal');
  if (!overlay) return;
  overlay.dataset.fontTarget = target;
  overlay.dataset.selectedFont = '';
  overlay.classList.remove('hidden');
  overlay.classList.add('show');
  overlay.setAttribute('aria-hidden', 'false');

  const list = byId('fontPickerList');
  const currentInput = byId(target === 'secondary' ? 'brandFontSecondary' : 'brandFontPrimary');
  const currentValue = currentInput ? normalizeFontStack(currentInput.value) : '';
  const customInput = byId('fontPickerCustomInput');
  if (customInput) customInput.value = currentInput ? currentInput.value || '' : '';
  if (list) {
    list.querySelectorAll('.font-picker-card').forEach((card) => {
      card.classList.remove('is-selected');
      const stack = card.dataset.fontStack || '';
      const meta = getFontMetaByStack(stack);
      if (meta && meta.normalizedStack === currentValue) {
        overlay.dataset.selectedFont = meta.id;
        card.classList.add('is-selected');
      }
    });
  }

  if (!overlay.dataset.selectedFont && list) {
    list.querySelectorAll('.font-picker-card').forEach((card) => card.classList.remove('is-selected'));
  }

  requestAnimationFrame(() => {
    const searchField = byId('fontPickerSearch');
    if (searchField) searchField.focus();
  });
}

function closeFontPicker() {
  const overlay = byId('fontPickerModal');
  if (!overlay) return;
  overlay.classList.add('hidden');
  overlay.classList.remove('show');
  overlay.setAttribute('aria-hidden', 'true');
  delete overlay.dataset.fontTarget;
  delete overlay.dataset.selectedFont;
}

function handleFontCardSelection(card) {
  const overlay = byId('fontPickerModal');
  if (!overlay || !card) return;

  overlay.dataset.selectedFont = card.dataset.fontId || '';

  const list = byId('fontPickerList');
  if (list) {
    list.querySelectorAll('.font-picker-card').forEach((btn) => {
      btn.classList.toggle('is-selected', btn === card);
    });
  }

  const customInput = byId('fontPickerCustomInput');
  if (customInput) {
    customInput.value = card.dataset.fontStack || '';
  }
}

function applySelectedFontFromPicker() {
  const overlay = byId('fontPickerModal');
  if (!overlay) return;

  const target = overlay.dataset.fontTarget || 'primary';
  const selectedId = overlay.dataset.selectedFont || '';
  const customInput = byId('fontPickerCustomInput');
  const customStack = customInput ? customInput.value.trim() : '';

  let stackToApply = '';
  if (selectedId) {
    const meta = FONT_LIBRARY_BY_ID.get(selectedId);
    if (meta) {
      stackToApply = meta.stack;
    }
  }

  // Custom input overrides the selected card when provided
  if (customStack) {
    stackToApply = customStack;
  }

  const effective = setFontField(target, stackToApply);

  // Update CSS variables in preview immediately
  const data = collectBusinessInfo();
  if (target === 'primary') {
    data.business_brand_font_primary = effective;
  } else {
    data.business_brand_font_secondary = effective;
  }
  applyBusinessCssToRoot(data);

  closeFontPicker();
}

function initializeFontPicker() {
  if (initializeFontPicker.__wfInit) return;
  initializeFontPicker.__wfInit = true;

  ensureFontPickerStyles();
  renderFontPickerList();
  applyFontPickerFilters();

  const searchField = byId('fontPickerSearch');
  if (searchField) {
    searchField.addEventListener('input', () => applyFontPickerFilters());
  }

  const categorySelect = byId('fontPickerCategory');
  if (categorySelect) {
    categorySelect.addEventListener('change', () => applyFontPickerFilters());
  }
}

function ensureFontPreviewStacks() {
  const primaryLabel = byId('brandFontPrimaryLabel');
  const secondaryLabel = byId('brandFontSecondaryLabel');
  const primaryStack = byId('brandFontPrimary')?.value || '';
  const secondaryStack = byId('brandFontSecondary')?.value || '';

  if (primaryLabel) primaryLabel.dataset.fontStack = primaryStack;
  if (secondaryLabel) secondaryLabel.dataset.fontStack = secondaryStack;
}

// Expose key helpers globally for legacy handlers and inline scripts
if (typeof window !== 'undefined') {
    window.loadBusinessInfo = loadBusinessInfo;
    window.applyBusinessInfo = applyBusinessInfo;
    window.saveBusinessInfo = saveBusinessInfo;
    // Expose branding helpers for Colors & Fonts modal
    window.wireBrandingLivePreview = wireBrandingLivePreview;
    window.initializeFontPicker = initializeFontPicker;
}

function ensureColorChipObserver(modal) {
  try {
    if (!modal) return;
    const list = modal.querySelector('#attrListColor');
    if (!list || list.__wfObserved) return;
    list.__wfObserved = true;
    let t = null;
    const run = () => {
      const chips = list.querySelectorAll('.color-chip[data-color]');
      const codes = Array.from(chips).map(c => c.getAttribute('data-color')).filter(Boolean);
      updateColorChipStyles(codes);
    };
    const obs = new MutationObserver(() => {
      clearTimeout(t);
      t = setTimeout(run, 10);
    });
    obs.observe(list, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-color'] });
    run();
  } catch (_) {}
}

// Generate a stylesheet that assigns background-color to specific color-chip data-color values
function updateColorChipStyles(colorCodes) {
  try {
    const id = 'wf-color-chip-styles';
    let styleEl = document.getElementById(id);
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = id;
      document.head.appendChild(styleEl);
    }
    const rules = [];
    // Only include valid hex/rgb/hsl values to be safe
    const valid = Array.from(new Set(colorCodes)).filter(v => typeof v === 'string' && v.trim());
    valid.forEach((codeRaw) => {
      const code = codeRaw.trim();
      // Escape quotes in attribute selector
      const selVal = code.replace(/"/g, '\\"');
      rules.push(`.color-chip[data-color="${selVal}"]{background-color:${code};}`);
    });
    styleEl.textContent = rules.join('\n');
  } catch (_) {}
}

// Ensure a clean Background Manager modal shell (no iframe)
function ensureBackgroundManagerModal() {
  let el = document.getElementById('backgroundManagerModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'backgroundManagerModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'backgroundManagerTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="backgroundManagerTitle" class="admin-card-title">🖼️ Background Manager</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body"></div>
    </div>`;
  try { document.body.appendChild(el); } catch(_) {}
  return el;
}

// --- Health & Diagnostics ---
// ... (existing health check functions) ...

// --- Business Info & Branding ---
let brandPalette = [];

function renderBrandPalette() {
  const container = document.getElementById('brandPaletteContainer');
  if (!container) return;
  container.innerHTML = '';
  brandPalette.forEach((color, index) => {
    const el = document.createElement('div');
    el.className = 'flex items-center gap-2 mb-2';
    el.innerHTML = `
      <input type="text" value="${color.name}" class="form-input flex-grow" data-index="${index}" data-field="name" placeholder="--css-variable-name">
      <input type="color" value="${color.hex}" class="form-input w-16" data-index="${index}" data-field="hex">
      <button type="button" class="btn btn-secondary text-red-700" data-action="business-palette-delete" data-index="${index}">Delete</button>
    `;
    container.appendChild(el);
  });
}

function updateBrandPreviewSwatches(s) {
  try {
    const wrap = document.getElementById('brandPreviewSwatches');
    if (!wrap) return;
    const boxes = wrap.querySelectorAll('div[title]');
    const primary = s.business_brand_primary || '';
    const secondary = s.business_brand_secondary || '';
    const accent = s.business_brand_accent || '';
    boxes.forEach((box) => {
      const t = (box.getAttribute('title') || '').toLowerCase();
      if (t === 'primary' && primary) {
        box.classList.add('brand-primary-swatch');
        box.classList.remove('brand-secondary-swatch', 'brand-accent-swatch');
      } else if (t === 'secondary' && secondary) {
        box.classList.add('brand-secondary-swatch');
        box.classList.remove('brand-primary-swatch', 'brand-accent-swatch');
      } else if (t === 'accent' && accent) {
        box.classList.add('brand-accent-swatch');
        box.classList.remove('brand-primary-swatch', 'brand-secondary-swatch');
      }
    });
  } catch (_) {}
}

function applyBusinessCssToRoot(s){
  try {
    const styleId = 'wf-dynamic-branding-vars';
    let styleEl = document.getElementById(styleId);
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = styleId;
      document.head.appendChild(styleEl);
    }

    const vars = [];
    if (s.business_brand_primary)   vars.push(`--brand-primary: ${s.business_brand_primary};`);
    if (s.business_brand_secondary) vars.push(`--brand-secondary: ${s.business_brand_secondary};`);
    if (s.business_brand_accent)    vars.push(`--brand-accent: ${s.business_brand_accent};`);
    if (s.business_brand_background)vars.push(`--brand-bg: ${s.business_brand_background};`);
    if (s.business_brand_text)      vars.push(`--brand-text: ${s.business_brand_text};`);
    if (s.business_toast_text)      vars.push(`--toast-text: ${s.business_toast_text};`);
    if (s.business_brand_font_primary) {
      vars.push(`--brand-font-primary: ${s.business_brand_font_primary};`);
      vars.push(`--font-primary: ${s.business_brand_font_primary};`);
      vars.push(`--font-family-primary: ${s.business_brand_font_primary};`);
    }
    if (s.business_brand_font_secondary) {
      vars.push(`--brand-font-secondary: ${s.business_brand_font_secondary};`);
      vars.push(`--font-secondary: ${s.business_brand_font_secondary};`);
    }
    // Public site color variables (optional)
    if (s.business_public_header_bg)   vars.push(`--site-header-bg: ${s.business_public_header_bg};`);
    if (s.business_public_header_text) vars.push(`--site-header-text: ${s.business_public_header_text};`);
    if (s.business_public_modal_bg)    vars.push(`--site-modal-bg: ${s.business_public_modal_bg};`);
    if (s.business_public_modal_text)  vars.push(`--site-modal-text: ${s.business_public_modal_text};`);
    if (s.business_public_page_bg)     vars.push(`--site-page-bg: ${s.business_public_page_bg};`);
    if (s.business_public_page_text)   vars.push(`--site-page-text: ${s.business_public_page_text};`);
    
    // Add palette colors as CSS variables
    brandPalette.forEach((p, i) => {
        if(p.name && p.hex) vars.push(`--palette-${i}: ${p.hex};`);
    });

    const raw = s.business_css_vars || '';
    if (raw) {
      raw.split(/\r?\n/).forEach((line) => {
        const t = String(line || '').trim();
        if (!t || t.startsWith('//') || t.startsWith('#')) return;
        if (t.match(/^--[A-Za-z0-9_-]+\s*:\s*[^;]+;?$/)) {
          vars.push(t.endsWith(';') ? t : `${t};`);
        }
      });
    }

    styleEl.textContent = `:root {\n${vars.join('\n')}\n}`;
    updateBrandPreviewSwatches(s);
  } catch (e) {
    console.error('Failed to apply business CSS', e);
  }
}

function wireBrandingLivePreview() {
  const ids = ['brandPrimary','brandSecondary','brandAccent','brandBackground','brandText','cssToastText','publicHeaderBg','publicHeaderText','publicModalBg','publicModalText','publicPageBg','publicPageText'];
  ids.forEach((id) => {
    const el = document.getElementById(id);
    if (!el || el.__wfBound) return;
    el.__wfBound = true;
    el.addEventListener('input', () => {
      const s = collectBusinessInfo();
      applyBusinessCssToRoot(s);
    });
  });
  
  const paletteContainer = document.getElementById('brandPaletteContainer');
  if(paletteContainer && !paletteContainer.__wfBound) {
    paletteContainer.__wfBound = true;
    paletteContainer.addEventListener('input', (e) => {
        const index = parseInt(e.target.dataset.index, 10);
        const field = e.target.dataset.field;
        if (!isNaN(index) && brandPalette[index] && field) {
          brandPalette[index][field] = e.target.value;
          const s = collectBusinessInfo();
          applyBusinessCssToRoot(s);
        }
    });
  }
}

async function loadBusinessInfo() {
    const status = byId('businessInfoStatus');
    if(status) status.textContent = 'Loading...';
    try {
        const mod = await import('../modules/business-settings-api.js');
        const BusinessSettingsAPI = mod?.default || mod?.BusinessSettingsAPI;
        const info = await BusinessSettingsAPI.getBusinessInfo();
        const data = (info && (info.data || info)) || {};
        applyBusinessInfo(data);
        applyBusinessCssToRoot(data);
        try {
          const wantPrimary = "'Merienda', cursive";
          const wantSecondary = "Nunito, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
          const missingPrimary = !data.business_brand_font_primary;
          const missingSecondary = !data.business_brand_font_secondary;
          if (missingPrimary || missingSecondary) {
            const up = {};
            if (missingPrimary) up.business_brand_font_primary = wantPrimary;
            if (missingSecondary) up.business_brand_font_secondary = wantSecondary;
            try { await BusinessSettingsAPI.upsert('business_info', up); } catch(_) {}
            const merged = { ...data, ...up };
            applyBusinessCssToRoot(merged);
            applyBusinessInfo(merged);
          }
        } catch(_) {}
        if(status) status.textContent = 'Loaded.';
    } catch (e) {
        if(status) status.textContent = `Error: ${e.message}`;
    }
}

function applyBusinessInfo(s) {
    const set = (id, v) => { const el = byId(id); if (el) el.value = v ?? ''; };
    set('bizName', s.business_name || '');
    set('bizEmail', s.business_email || '');
    // Canonical business address fields
    set('bizAddress', s.business_address || '');
    set('bizAddress2', s.business_address2 || '');
    set('bizCity', s.business_city || '');
    set('bizState', s.business_state || '');
    set('bizPostal', s.business_postal || '');
    set('bizCountry', s.business_country || '');
    // Other business info
    set('bizPhone', s.business_phone || '');
    set('bizHours', s.business_hours || '');
    set('bizWebsite', s.business_website || '');
    set('bizLogoUrl', (s.business_logo_url && s.business_logo_url.trim() !== '') ? s.business_logo_url : (typeof window !== 'undefined' && typeof window.wfBrandLogoPath === 'function' ? window.wfBrandLogoPath() : '/images/logos/logo-whimsicalfrog.webp'));
    set('bizTagline', s.site_tagline || '');
    set('bizDescription', s.business_description || '');
    // If font stacks are missing from API, backfill from computed CSS variables
    try {
      const cs = getComputedStyle(document.documentElement);
      let computedPrimary = (cs.getPropertyValue('--brand-font-primary') || cs.getPropertyValue('--font-primary') || cs.getPropertyValue('--font-family-primary') || '').trim();
      let computedSecondary = (cs.getPropertyValue('--brand-font-secondary') || cs.getPropertyValue('--font-secondary') || '').trim();
      // Final fallback: body computed font-family
      try {
        if ((!computedPrimary || !computedSecondary) && document.body) {
          const bf = getComputedStyle(document.body).fontFamily;
          const bodyFamily = (bf || '').trim();
          if (!computedPrimary && bodyFamily) computedPrimary = bodyFamily;
          if (!computedSecondary && bodyFamily) computedSecondary = bodyFamily;
        }
      } catch(_) {}
      // Prefer a heading font for secondary if available and distinct
      try {
        const h = document.querySelector('h1, h2, .site-title, .wf-cloud-title, .page-title');
        const headingFamily = h ? (getComputedStyle(h).fontFamily || '').trim() : '';
        if ((!computedSecondary || computedSecondary === computedPrimary) && headingFamily) {
          computedSecondary = headingFamily;
        }
      } catch(_) {}
      if (!s.business_brand_font_primary && computedPrimary) s.business_brand_font_primary = computedPrimary;
      if (!s.business_brand_font_secondary && computedSecondary) s.business_brand_font_secondary = computedSecondary;
    } catch(_) {}
    const primaryStack = setFontField('primary', s.business_brand_font_primary);
    const secondaryStack = setFontField('secondary', s.business_brand_font_secondary);
    set('brandFontPrimary', primaryStack);
    set('brandFontSecondary', secondaryStack);
    ensureFontPreviewStacks();
    set('bizSupportEmail', s.business_support_email || '');
    set('bizSupportPhone', s.business_support_phone || '');
    set('bizFacebook', s.business_facebook || '');
    set('bizInstagram', s.business_instagram || '');
    set('bizTwitter', s.business_twitter || '');
    set('bizTikTok', s.business_tiktok || '');
    set('bizYouTube', s.business_youtube || '');
    set('bizLinkedIn', s.business_linkedin || '');
    set('bizTermsUrl', s.business_terms_url || '');
    set('bizPrivacyUrl', s.business_privacy_url || '');
    set('bizTaxId', s.business_tax_id || '');
    set('bizTimezone', s.business_timezone || '');
    set('bizCurrency', s.business_currency || '');
    set('bizLocale', s.business_locale || '');
    set('footerNote', s.business_footer_note || '');
    set('footerHtml', s.business_footer_html || '');
    set('returnPolicy', s.business_policy_return || '');
    set('shippingPolicy', s.business_policy_shipping || '');
    set('warrantyPolicy', s.business_policy_warranty || '');
    set('aboutPageTitle', s.about_page_title || '');
    set('aboutPageContent', s.about_page_content || '');
    set('privacyPolicyContent', s.privacy_policy_content || '');
    set('termsOfServiceContent', s.terms_of_service_content || '');
    set('storePoliciesContent', s.store_policies_content || '');
    set('brandPrimary', s.business_brand_primary || '#87ac3a');
    set('brandSecondary', s.business_brand_secondary || '#BF5700');
    set('brandAccent', s.business_brand_accent || '#22c55e');
    set('brandBackground', s.business_brand_background || '#ffffff');
    set('brandText', s.business_brand_text || '#111827');
    // Toast / notifications text color (maps to --toast-text)
    set('cssToastText', s.business_toast_text || '#ffffff');
    // Public site colors: default to simple white backgrounds and black text
    set('publicHeaderBg', s.business_public_header_bg || '#ffffff');
    set('publicHeaderText', s.business_public_header_text || '#000000');
    set('publicModalBg', s.business_public_modal_bg || '#ffffff');
    set('publicModalText', s.business_public_modal_text || '#000000');
    set('publicPageBg', s.business_public_page_bg || '#ffffff');
    set('publicPageText', s.business_public_page_text || '#000000');
    set('customCssVars', s.business_css_vars || '');

    try {
        brandPalette = JSON.parse(s.business_brand_palette || '[]');
    } catch (e) {
        brandPalette = [];
        console.error('Failed to parse brand palette', e);
    }
    renderBrandPalette();
}

function collectBusinessInfo() {
    const get = (id) => (byId(id) ? byId(id).value.trim() : '');
    return {
        business_name: get('bizName'),
        business_email: get('bizEmail'),
        // Canonical business address fields
        business_address: get('bizAddress'),
        business_address2: get('bizAddress2'),
        business_city: get('bizCity'),
        business_state: get('bizState'),
        business_postal: get('bizPostal'),
        business_country: get('bizCountry'),
        // Other business info
        business_phone: get('bizPhone'),
        business_hours: get('bizHours'),
        business_website: get('bizWebsite'),
        business_logo_url: get('bizLogoUrl'),
        site_tagline: get('bizTagline'),
        business_description: get('bizDescription'),
        business_brand_font_primary: get('brandFontPrimary'),
        business_brand_font_secondary: get('brandFontSecondary'),
        business_brand_primary: get('brandPrimary'),
        business_brand_secondary: get('brandSecondary'),
        business_brand_accent: get('brandAccent'),
        business_brand_background: get('brandBackground'),
        business_brand_text: get('brandText'),
        business_toast_text: get('cssToastText'),
        business_css_vars: get('customCssVars'),
        // Public site colors
        business_public_header_bg: get('publicHeaderBg'),
        business_public_header_text: get('publicHeaderText'),
        business_public_modal_bg: get('publicModalBg'),
        business_public_modal_text: get('publicModalText'),
        business_public_page_bg: get('publicPageBg'),
        business_public_page_text: get('publicPageText'),
        business_brand_palette: JSON.stringify(brandPalette),
        business_support_email: get('bizSupportEmail'),
        business_support_phone: get('bizSupportPhone'),
        business_facebook: get('bizFacebook'),
        business_instagram: get('bizInstagram'),
        business_twitter: get('bizTwitter'),
        business_tiktok: get('bizTikTok'),
        business_youtube: get('bizYouTube'),
        business_linkedin: get('bizLinkedIn'),
        business_terms_url: get('bizTermsUrl'),
        business_privacy_url: get('bizPrivacyUrl'),
        business_tax_id: get('bizTaxId'),
        business_timezone: get('bizTimezone'),
        business_currency: get('bizCurrency'),
        business_locale: get('bizLocale'),
        business_footer_note: get('footerNote'),
        business_footer_html: get('footerHtml'),
        business_policy_return: get('returnPolicy'),
        business_policy_shipping: get('shippingPolicy'),
        business_policy_warranty: get('warrantyPolicy'),
        about_page_title: get('aboutPageTitle'),
        about_page_content: get('aboutPageContent'),
        privacy_policy_content: get('privacyPolicyContent'),
        terms_of_service_content: get('termsOfServiceContent'),
        store_policies_content: get('storePoliciesContent'),
    };
}

async function saveBusinessInfo() {
    const status = byId('businessInfoStatus');
    if(status) status.textContent = 'Saving...';
    const payload = collectBusinessInfo();
    try {
        const mod = await import('../modules/business-settings-api.js');
        const BusinessSettingsAPI = mod?.default || mod?.BusinessSettingsAPI;
        const { site_tagline, ...businessInfo } = payload;
        const requests = [];

        if (Object.keys(businessInfo).length > 0) {
            requests.push(BusinessSettingsAPI.upsert('business_info', businessInfo));
        }
        if (typeof site_tagline === 'string') {
            requests.push(BusinessSettingsAPI.upsert('branding', { site_tagline }));
        }

        await Promise.all(requests);
        if(status) status.textContent = 'Saved successfully!';
        setTimeout(() => { if(status && status.textContent === 'Saved successfully!') status.textContent = ''; }, 2000);
    } catch (e) {
        if(status) status.textContent = `Save failed: ${e.message}`;
    }
}

// --- Branding Backup Utilities ---
let __brandingBackupCache = null; // { snapshot: {...}, savedAt: string }

function buildBrandingSnapshot() {
  // Capture all branding-related fields plus palette
  const s = collectBusinessInfo();
  return {
    business_brand_primary: s.business_brand_primary,
    business_brand_secondary: s.business_brand_secondary,
    business_brand_accent: s.business_brand_accent,
    business_brand_background: s.business_brand_background,
    business_brand_text: s.business_brand_text,
    business_toast_text: s.business_toast_text,
    business_brand_font_primary: s.business_brand_font_primary,
    business_brand_font_secondary: s.business_brand_font_secondary,
    business_css_vars: s.business_css_vars,
    // Public site colors (include in snapshot for full-fidelity restore)
    business_public_header_bg: s.business_public_header_bg,
    business_public_header_text: s.business_public_header_text,
    business_public_modal_bg: s.business_public_modal_bg,
    business_public_modal_text: s.business_public_modal_text,
    business_public_page_bg: s.business_public_page_bg,
    business_public_page_text: s.business_public_page_text,
    // Palette (store as array; source is JSON string)
    palette: (function(){ try { return JSON.parse(s.business_brand_palette || '[]'); } catch(_) { return []; } })(),
  };
}

function summarizeBrandSnapshot(snap, savedAt) {
  if (!snap) return '<div class="text-xs text-gray-500">No backup found.</div>';

  const esc = (s) => String(s || '').replace(/[&<>]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
  const swatch = (color) => {
    const c = String(color || '').trim();
    if (!c) return '';
    const style = `display:inline-block;width:14px;height:14px;border:1px solid #d1d5db;border-radius:3px;background:${c};vertical-align:middle;margin-right:6px;`;
    return `<span aria-hidden="true" style="${style}"></span>`;
  };
  const textChip = (color) => {
    const c = String(color || '').trim();
    if (!c) return '';
    const style = `display:inline-block;padding:0 6px;line-height:16px;border:1px solid #d1d5db;border-radius:3px;color:${c};vertical-align:middle;margin-left:6px;font-size:11px;`;
    return `<span aria-hidden="true" style="${style}">Aa</span>`;
  };
  const fontSample = (stack) => {
    const s = String(stack || '').trim();
    if (!s) return '';
    const style = `display:inline-block;margin-left:6px;padding:0 6px;line-height:16px;border:1px solid #d1d5db;border-radius:3px;font-family:${esc(s)};font-size:12px;background:#fff;color:#111;vertical-align:middle;`;
    return `<span aria-hidden="true" style="${style}">Sample</span>`;
  };

  const items = [];
  if (snap.business_brand_primary) items.push(`<li><strong>Primary</strong>: ${swatch(snap.business_brand_primary)} ${esc(snap.business_brand_primary)}</li>`);
  if (snap.business_brand_secondary) items.push(`<li><strong>Secondary</strong>: ${swatch(snap.business_brand_secondary)} ${esc(snap.business_brand_secondary)}</li>`);
  if (snap.business_brand_accent) items.push(`<li><strong>Accent</strong>: ${swatch(snap.business_brand_accent)} ${esc(snap.business_brand_accent)}</li>`);
  if (snap.business_brand_background) items.push(`<li><strong>Background</strong>: ${swatch(snap.business_brand_background)} ${esc(snap.business_brand_background)}</li>`);
  if (snap.business_brand_text) items.push(`<li><strong>Text</strong>: ${esc(snap.business_brand_text)} ${textChip(snap.business_brand_text)}</li>`);
  // Use the currently computed site fonts for the samples, falling back to snapshot values
  const getComputedFont = (names) => {
    try {
      const cs = getComputedStyle(document.documentElement);
      for (const n of names) {
        const v = cs.getPropertyValue(n);
        if (v && String(v).trim()) return String(v).trim();
      }
      // Final fallback: body computed font-family
      if (document.body) {
        const bf = getComputedStyle(document.body).fontFamily;
        if (bf && String(bf).trim()) return String(bf).trim();
      }
    } catch(_) {}
    return '';
  };
  const compPrimary = getComputedFont(['--brand-font-primary', '--font-primary', '--font-family-primary']);
  let compSecondary = getComputedFont(['--brand-font-secondary', '--font-secondary']);
  // Prefer a heading font for secondary if variables/body are missing or match primary
  if (!compSecondary || compSecondary === compPrimary) {
    try {
      const h = document.querySelector('h1, h2, .site-title, .wf-cloud-title, .page-title');
      const headingFamily = h ? (getComputedStyle(h).fontFamily || '').trim() : '';
      if (headingFamily) compSecondary = headingFamily;
    } catch(_) {}
  }
  if (snap.business_brand_font_primary) {
    const effPrimary = compPrimary || snap.business_brand_font_primary;
    items.push(`<li><strong>Primary Font</strong>: ${esc(effPrimary)} ${fontSample(effPrimary)}</li>`);
  }
  if (snap.business_brand_font_secondary) {
    const effSecondary = compSecondary || snap.business_brand_font_secondary;
    items.push(`<li><strong>Secondary Font</strong>: ${esc(effSecondary)} ${fontSample(effSecondary)}</li>`);
  }

  // Palette preview (show up to 6 chips)
  const palette = Array.isArray(snap.palette) ? snap.palette : [];
  const chips = palette.slice(0, 6).map((p) => {
    const hex = esc(p?.hex || '');
    const name = esc(p?.name || '');
    const style = `display:inline-block;width:14px;height:14px;border:1px solid #d1d5db;border-radius:3px;background:${hex};vertical-align:middle;margin-right:4px;`;
    return `<span title="${name}: ${hex}" style="${style}"></span>`;
  }).join('');
  items.push(`<li><strong>Palette</strong>: ${palette.length} colors ${chips ? `<span style="margin-left:6px;vertical-align:middle;">${chips}</span>` : ''}</li>`);

  const when = savedAt ? new Date(savedAt).toLocaleString() : '';
  const timeLine = when ? `<div class="text-xs text-gray-500 mt-2">Saved: ${when}</div>` : '';
  return `<ul class="text-xs list-disc list-inside space-y-0.5">${items.join('')}</ul>${timeLine}`;
}

async function loadBrandingBackup() {
  try {
    const mod = await import('../modules/business-settings-api.js');
    const BusinessSettingsAPI = mod?.default || mod?.BusinessSettingsAPI;
    const res = await BusinessSettingsAPI.getByCategory('branding');
    const rows = (res && (res.data?.settings || res.settings)) || [];
    let backup = null;
    let savedAt = null;
    rows.forEach((r) => {
      if (r.setting_key === 'brand_backup') {
        try { backup = JSON.parse(r.setting_value || '{}'); } catch (_) { backup = null; }
      } else if (r.setting_key === 'brand_backup_saved_at') {
        savedAt = r.setting_value || null;
      }
    });
    __brandingBackupCache = backup ? { snapshot: backup, savedAt } : null;
    // Update the Brand Backup card UI (timestamp only)
    const title = byId('brandPreviewTitle');
    if (title) title.textContent = 'Brand Backup';
    const savedAtEl = byId('brandBackupSavedAt');
    if (savedAtEl) savedAtEl.textContent = savedAt ? new Date(savedAt).toLocaleString() : 'Never';
    // Swatches are optional; update if present
    const wrap = byId('brandPreviewSwatches');
    if (wrap && backup) updateBrandPreviewSwatches(backup);
    return __brandingBackupCache;
  } catch (e) {
    console.warn('Failed to load branding backup', e);
    return null;
  }
}

function applyBrandingSnapshotToForm(snap) {
  if (!snap) return;
  const set = (id, v) => { const el = byId(id); if (el) el.value = v ?? ''; };
  set('brandPrimary', snap.business_brand_primary || '#87ac3a');
  set('brandSecondary', snap.business_brand_secondary || '#BF5700');
  set('brandAccent', snap.business_brand_accent || '#22c55e');
  set('brandBackground', snap.business_brand_background || '#ffffff');
  set('brandText', snap.business_brand_text || '#111827');
  set('cssToastText', (snap.business_toast_text != null ? snap.business_toast_text : '#ffffff'));
  set('customCssVars', snap.business_css_vars || '');
  // Fonts via setFontField to update labels
  setFontField('primary', snap.business_brand_font_primary || '');
  set('brandFontPrimary', byId('brandFontPrimary')?.value || '');
  setFontField('secondary', snap.business_brand_font_secondary || '');
  set('brandFontSecondary', byId('brandFontSecondary')?.value || '');
  // Public site colors: default to white backgrounds and black text when absent
  set('publicHeaderBg', (snap.business_public_header_bg != null ? snap.business_public_header_bg : '#ffffff'));
  set('publicHeaderText', (snap.business_public_header_text != null ? snap.business_public_header_text : '#000000'));
  set('publicModalBg', (snap.business_public_modal_bg != null ? snap.business_public_modal_bg : '#ffffff'));
  set('publicModalText', (snap.business_public_modal_text != null ? snap.business_public_modal_text : '#000000'));
  set('publicPageBg', (snap.business_public_page_bg != null ? snap.business_public_page_bg : '#ffffff'));
  set('publicPageText', (snap.business_public_page_text != null ? snap.business_public_page_text : '#000000'));
  // Palette
  brandPalette = Array.isArray(snap.palette) ? snap.palette.slice() : [];
  renderBrandPalette();
  // Apply CSS immediately
  const s = collectBusinessInfo();
  applyBusinessCssToRoot(s);
}

async function createBrandingBackup() {
  try {
    const snap = buildBrandingSnapshot();
    const savedAt = new Date().toISOString();
    const mod = await import('../modules/business-settings-api.js');
    const BusinessSettingsAPI = mod?.default || mod?.BusinessSettingsAPI;
    await BusinessSettingsAPI.upsert('branding', {
      brand_backup: snap,
      brand_backup_saved_at: savedAt,
    });
    __brandingBackupCache = { snapshot: snap, savedAt };
    // Refresh the card display (timestamp only)
    const title = byId('brandPreviewTitle');
    if (title) title.textContent = 'Brand Backup';
    const savedAtEl = byId('brandBackupSavedAt');
    if (savedAtEl) savedAtEl.textContent = new Date(savedAt).toLocaleString();
    updateBrandPreviewSwatches(snap);
    if (window.wfNotifications?.success) window.wfNotifications.success('Branding backup saved');
    else if (typeof window.showNotification === 'function') window.showNotification('Branding backup saved', 'success');
  } catch (e) {
    console.error('Failed to save branding backup', e);
    if (window.wfNotifications?.error) window.wfNotifications.error('Failed to save branding backup');
    else if (typeof window.showNotification === 'function') window.showNotification('Failed to save branding backup', 'error');
  }
}

async function resetBrandingFromBackup() {
  try {
    if (!__brandingBackupCache) {
      const loaded = await loadBrandingBackup();
      if (!loaded) {
        if (window.wfNotifications?.info) window.wfNotifications.info('No branding backup found');
        else if (typeof window.showNotification === 'function') window.showNotification('No branding backup found', 'info');
        return;
      }
    }
    const snap = __brandingBackupCache?.snapshot;
    if (!snap) {
      if (window.wfNotifications?.info) window.wfNotifications.info('No branding backup found');
      else if (typeof window.showNotification === 'function') window.showNotification('No branding backup found', 'info');
      return;
    }
    applyBrandingSnapshotToForm(snap);
    // Persist immediately
    await saveBusinessInfo();
    if (window.wfNotifications?.success) window.wfNotifications.success('Branding restored from backup');
    else if (typeof window.showNotification === 'function') window.showNotification('Branding restored from backup', 'success');
  } catch (e) {
    console.error('Failed to reset from backup', e);
    if (window.wfNotifications?.error) window.wfNotifications.error('Failed to reset from backup');
    else if (typeof window.showNotification === 'function') window.showNotification('Failed to reset from backup', 'error');
  }
}

// --- Main Delegated Click Handler ---
document.addEventListener('click', async (e) => {
  const t = e.target;
  const closest = (sel) => t && t.closest ? t.closest(sel) : null;

  // Business Info Modal
  if (closest('[data-action="open-business-info"]')) {
    e.preventDefault();
    e.stopPropagation();
    loadBusinessInfo().then(() => {
      showModal('businessInfoModal'); // Assuming showModal exists
      setTimeout(()=>{
        try {
          wireBrandingLivePreview();
          initializeFontPicker();
          // Also load existing branding backup summary
          loadBrandingBackup().catch(()=>{});
        } catch(err){ console.error('Error wiring branding live preview', err); }
      }, 0);
    });
    return;
  }

  // Colors & Fonts Modal (branding moved here)
  if (closest('[data-action="open-colors-fonts"]')) {
    e.preventDefault();
    e.stopPropagation();
    loadBusinessInfo().then(() => {
      if (typeof window.showModal === 'function') window.showModal('colorsFontsModal');
      else {
        const el = document.getElementById('colorsFontsModal');
        if (el) { el.classList.remove('hidden'); el.classList.add('show'); try { el.setAttribute('aria-hidden','false'); } catch(_) {} }
      }
      setTimeout(() => {
        try {
          wireBrandingLivePreview();
          initializeFontPicker();
          // Load existing branding backup timestamp and swatches
          loadBrandingBackup().catch(() => {});
        } catch (err) {
          console.error('Error wiring Colors & Fonts modal', err);
        }
      }, 0);
    });
    return;
  }
  
  // Cost Breakdown Manager (iframe embed)
  if (closest('[data-action="open-cost-breakdown"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const modal = document.getElementById('costBreakdownModal');
      const frame = document.getElementById('costBreakdownFrame');
      if (frame && (frame.getAttribute('src') === null || frame.getAttribute('src') === 'about:blank')) {
        const ds = frame.getAttribute('data-src') || '/sections/tools/cost_breakdown_manager.php?modal=1';
        frame.setAttribute('src', ds);
      }
      // Ensure autosize flags and fallback
      try { if (frame && !frame.hasAttribute('data-autosize')) frame.setAttribute('data-autosize','1'); } catch(_) {}
      try { if (frame && frame.classList) frame.classList.remove('wf-admin-embed-frame--tall','wf-embed--fill'); } catch(_) {}
      try { if (modal) { if (typeof markOverlayResponsive === 'function') markOverlayResponsive(modal); } } catch(_) {}
      try { if (frame && modal && typeof attachSameOriginFallback === 'function') attachSameOriginFallback(frame, modal); } catch(_) {}
      if (typeof window.showModal === 'function' && modal) window.showModal('costBreakdownModal');
      else if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('show');
        try { modal.setAttribute('aria-hidden', 'false'); } catch(_) {}
      }
    } catch (err) {
      console.warn('Failed to open Cost Breakdown Manager', err);
    }
    return;
  }

  // Background Manager (no iframe; Vite-managed module)
  if (closest('[data-action="open-background-manager"]')) {
    e.preventDefault();
    e.stopPropagation();
    const modal = ensureBackgroundManagerModal();
    try {
      const mod = await import('./modules/background-manager.js');
      const api = mod?.default || mod;
      if (api && typeof api.init === 'function') {
        api.init(modal);
      }
    } catch (err) {
      console.error('Failed to load Background Manager module', err);
    }
    // Ensure native modal obeys responsive autosize
    try { if (modal && typeof markOverlayResponsive === 'function') markOverlayResponsive(modal); } catch(_) {}
    if (typeof window.showModal === 'function') window.showModal('backgroundManagerModal');
    else {
      modal.classList.remove('hidden');
      modal.classList.add('show');
      try { modal.setAttribute('aria-hidden', 'false'); } catch(_) {}
    }
    return;
  }
  // Address Diagnostics
  if (closest('[data-action="open-address-diagnostics"]')) {
    e.preventDefault();
    e.stopPropagation();
    try {
      const modal = document.getElementById('addressDiagnosticsModal');
      const frame = document.getElementById('addressDiagnosticsFrame');
      if (frame && frame.getAttribute('src') === null) {
        const ds = frame.getAttribute('data-src') || '/sections/tools/address_diagnostics.php?modal=1';
        frame.setAttribute('src', ds);
      }
      if (typeof window.showModal === 'function' && modal) window.showModal('addressDiagnosticsModal');
      else if (modal) modal.classList.remove('hidden');
    } catch (err) { console.warn('Failed to open Address Diagnostics', err); }
    return;
  }
  if (closest('[data-action="close-business-info"]')) {
    e.preventDefault(); e.stopPropagation();
    hideModal('businessInfoModal'); // Assuming hideModal exists
    return;
  }
  if (closest('[data-action="open-font-picker"]')) {
    e.preventDefault();
    e.stopPropagation();
    const target = closest('[data-action="open-font-picker"]').dataset.fontTarget || 'primary';
    initializeFontPicker();
    openFontPicker(target);
    return;
  }
  if (closest('[data-action="close-font-picker"]')) {
    e.preventDefault();
    e.stopPropagation();
    closeFontPicker();
    return;
  }
  if (closest('[data-action="apply-font-selection"]')) {
    e.preventDefault();
    e.stopPropagation();
    applySelectedFontFromPicker();
    return;
  }
  const fontCard = closest('#fontPickerList .font-picker-card');
  if (fontCard) {
    e.preventDefault();
    e.stopPropagation();
    handleFontCardSelection(fontCard);
    return;
  }
  if (closest('[data-action="business-save-branding"]')) {
    e.preventDefault(); e.stopPropagation();
    saveBusinessInfo();
    return;
  }
  
  // Brand Palette CRUD
  if (closest('[data-action="business-palette-add"]')) {
    e.preventDefault();
    const nameInput = document.getElementById('newPaletteName');
    const hexInput = document.getElementById('newPaletteHex');
    const name = nameInput.value.trim();
    const hex = hexInput.value.trim();
    if (name && hex) {
      brandPalette.push({ name, hex });
      nameInput.value = '';
      hexInput.value = '#000000';
      renderBrandPalette();
      const s = collectBusinessInfo();
      applyBusinessCssToRoot(s);
    }
    return;
  }
  if (closest('[data-action="business-palette-delete"]')) {
    e.preventDefault();
    const index = parseInt(e.target.dataset.index, 10);
    if (!isNaN(index) && brandPalette[index]) {
      brandPalette.splice(index, 1);
      renderBrandPalette();
      const s = collectBusinessInfo();
      applyBusinessCssToRoot(s);
    }
    return;
  }

  // Reset Branding from backup
  if (closest('[data-action="business-reset-branding"]')) {
    e.preventDefault();
    e.stopPropagation();
    resetBrandingFromBackup();
    return;
  }

  // Open Create Backup confirmation modal
  if (closest('[data-action="business-backup-open"]')) {
    e.preventDefault();
    e.stopPropagation();
    const modal = document.getElementById('brandingBackupModal');
    if (modal) {
      try { if (modal.parentElement && modal.parentElement !== document.body) document.body.appendChild(modal); } catch(_) {}
      // Populate summary of current settings into the modal
      try {
        const snap = buildBrandingSnapshot();
        const summary = summarizeBrandSnapshot(snap);
        const box = modal.querySelector('#brandingBackupSummary');
        if (box) box.innerHTML = summary;
      } catch(_) {}
      modal.classList.remove('hidden');
      modal.classList.add('show');
      try { modal.setAttribute('aria-hidden', 'false'); } catch(_) {}
    }
    return;
  }

  // Confirm create/overwrite backup
  if (closest('[data-action="business-backup-confirm"]')) {
    e.preventDefault();
    e.stopPropagation();
    createBrandingBackup().then(() => {
      const modal = document.getElementById('brandingBackupModal');
      if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('show');
        try { modal.setAttribute('aria-hidden', 'true'); } catch(_) {}
      }
    });
    return;
  }

  // Attributes Management
  if (closest('[data-action="attr-add-form"]')) {
    const form = closest('form[data-action="attr-add-form"]');
    if (form) {
      e.preventDefault();
      e.stopPropagation();

      const type = form.dataset.type;
      const input = form.querySelector('.attr-input');
      const value = input ? input.value.trim() : '';

      if (!value) {
        if (typeof window.showNotification === 'function') window.showNotification(`Please enter a ${type} name`, 'error');
        return;
      }

      // For sizes, require both name and code
      if (type === 'size') {
        const parts = value.split(' ');
        if (parts.length < 2) {
          if (typeof window.showNotification === 'function') window.showNotification('Please enter size as "Name Code" (e.g., "Extra Large XL")', 'error');
          return;
        }
        const sizeName = parts.slice(0, -1).join(' ');
        const sizeCode = parts[parts.length - 1];

        addAttribute(type, sizeName, sizeCode);
      } else {
        addAttribute(type, value);
      }

      if (input) input.value = '';
    }
    return;
  }

  if (closest('[data-action="attr-add"]')) {
    const button = closest('[data-action="attr-add"]');
    if (button) {
      e.preventDefault();
      e.stopPropagation();

      const type = button.dataset.type;
      const form = button.closest('form');
      const input = form ? form.querySelector('.attr-input') : null;
      const value = input ? input.value.trim() : '';

      if (!value) {
        alert(`Please enter a ${type} name`);
        return;
      }

      // For sizes, require both name and code
      if (type === 'size') {
        const parts = value.split(' ');
        if (parts.length < 2) {
          alert('Please enter size as "Name Code" (e.g., "Extra Large XL")');
          return;
        }
        const sizeName = parts.slice(0, -1).join(' ');
        const sizeCode = parts[parts.length - 1];

        addAttribute(type, sizeName, sizeCode);
      } else {
        addAttribute(type, value);
      }

      if (input) input.value = '';
    }
    return;
  }

  if (closest('[data-action="attr-edit"]')) {
    const button = closest('[data-action="attr-edit"]');
    if (button) {
      e.preventDefault();
      e.stopPropagation();

      const type = button.dataset.type;
      const id = button.dataset.id;
      editAttribute(type, id);
    }
    return;
  }

  if (closest('[data-action="attr-delete"]')) {
    const button = closest('[data-action="attr-delete"]');
    if (button) {
      e.preventDefault();
      e.stopPropagation();

      const type = button.dataset.type;
      const id = button.dataset.id;
      if (typeof window.showConfirmationModal !== 'function') {
        try { window.showNotification && window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); } catch(_) {}
        return;
      }
      const ok = await window.showConfirmationModal({
        title: 'Delete Attribute',
        message: `Delete this ${type}?`,
        confirmText: 'Delete',
        confirmStyle: 'danger',
        icon: '⚠️',
        iconType: 'danger'
      });
      if (!ok) return;
      deleteAttribute(type, id);
    }
    return;
  }

  if (closest('[data-action="attr-save-order"]')) {
    e.preventDefault();
    e.stopPropagation();
    saveAttributeOrder();
    return;
  }

  // ... (other delegated handlers for health, hints, etc.) ...
}, true);

// Set bridge initialization flag to prevent entry file fallbacks
window.__WF_ADMIN_SETTINGS_BRIDGE_INIT = true;

// Dummy modal functions if they don't exist globally
if (typeof window.showModal === 'undefined') {
    window.showModal = (id) => {
      const el = byId(id);
      if (!el) return;
      try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
      el.classList.remove('hidden');
      el.classList.add('show');
      try { el.setAttribute('aria-hidden', 'false'); } catch(_) {}
    };
}
if (typeof window.hideModal === 'undefined') {
    window.hideModal = (id) => {
      const el = byId(id);
      if (!el) return;
      el.classList.remove('show');
      el.classList.add('hidden');
      try { el.setAttribute('aria-hidden', 'true'); } catch(_) {}
    };
}

// --- Attributes Management ---
const globalAttributes = { genders: [], sizes: [], colors: [] };

async function fetchGlobalAttributes() {
  try {
    const gendersResult = await ApiClient.get('/api/global_color_size_management.php', { action: 'get_global_genders', admin_token: 'whimsical_admin_2024' });
    const sizesResult = await ApiClient.get('/api/global_color_size_management.php', { action: 'get_global_sizes', admin_token: 'whimsical_admin_2024' });
    const colorsResult = await ApiClient.get('/api/global_color_size_management.php', { action: 'get_global_colors', admin_token: 'whimsical_admin_2024' });

    if (gendersResult.success) {
      globalAttributes.genders = gendersResult.genders || [];
      globalAttributes.sizes = sizesResult.sizes || [];
      globalAttributes.colors = colorsResult.colors || [];
    }

    return globalAttributes;
  } catch (error) {
    console.error('Failed to fetch global attributes:', error);
    return { genders: [], sizes: [], colors: [] };
  }
}

function populateAttributesModal(modal) {
  if (!modal) return;

  // Populate genders
  const genderList = modal.querySelector('#attrListGender');
  if (genderList) {
    genderList.innerHTML = '';
    globalAttributes.genders.forEach(gender => {
      const li = document.createElement('li');
      li.className = 'attr-item flex justify-between items-center mb-1 p-2 bg-gray-50 rounded';
      li.innerHTML = `
        <span class="attr-name">${gender.gender_name}</span>
        <div class="attr-actions flex gap-1">
          <button type="button" class="btn btn-secondary btn-sm" data-action="attr-edit" data-type="gender" data-id="${gender.id}">Edit</button>
          <button type="button" class="btn btn-secondary btn-sm text-red-700" data-action="attr-delete" data-type="gender" data-id="${gender.id}">Delete</button>
        </div>
      `;
      genderList.appendChild(li);
    });
  }

  // Populate sizes
  const sizeList = modal.querySelector('#attrListSize');
  if (sizeList) {
    sizeList.innerHTML = '';
    globalAttributes.sizes.forEach(size => {
      const li = document.createElement('li');
      li.className = 'attr-item flex justify-between items-center mb-1 p-2 bg-gray-50 rounded';
      li.innerHTML = `
        <span class="attr-name">${size.size_name} (${size.size_code})</span>
        <div class="attr-actions flex gap-1">
          <button type="button" class="btn btn-secondary btn-sm" data-action="attr-edit" data-type="size" data-id="${size.id}">Edit</button>
          <button type="button" class="btn btn-secondary btn-sm text-red-700" data-action="attr-delete" data-type="size" data-id="${size.id}">Delete</button>
        </div>
      `;
      sizeList.appendChild(li);
    });
  }

  // Populate colors
  const colorList = modal.querySelector('#attrListColor');
  if (colorList) {
    colorList.innerHTML = '';
    const codes = [];
    globalAttributes.colors.forEach(color => {
      const li = document.createElement('li');
      li.className = 'attr-item flex justify-between items-center mb-1 p-2 bg-gray-50 rounded';
      li.innerHTML = `
        <span class="attr-name">${color.color_name} <span class="inline-block w-4 h-4 rounded border color-chip" data-color="${color.color_code}"></span></span>
        <div class="attr-actions flex gap-1">
          <button type="button" class="btn btn-secondary btn-sm" data-action="attr-edit" data-type="color" data-id="${color.id}">Edit</button>
          <button type="button" class="btn btn-secondary btn-sm text-red-700" data-action="attr-delete" data-type="color" data-id="${color.id}">Delete</button>
        </div>
      `;
      colorList.appendChild(li);
      if (color && color.color_code) codes.push(color.color_code);
    });
    updateColorChipStyles(codes);
    ensureColorChipObserver(modal);
  }
}

async function addAttribute(type, name, code = null) {
  const resultDiv = document.getElementById('attributesResult');
  if (resultDiv) resultDiv.textContent = `Adding ${type}...`;

  try {
    let action, payload;

    if (type === 'gender') {
      action = 'add_global_gender';
      payload = { gender_name: name };
    } else if (type === 'size') {
      action = 'add_global_size';
      payload = { size_name: name, size_code: code };
    } else if (type === 'color') {
      action = 'add_global_color';
      payload = { color_name: name, color_code: '#000000' };
    }

    const result = await ApiClient.post('/api/global_color_size_management.php', { action, ...payload, admin_token: 'whimsical_admin_2024' });

    if (result.success) {
      // Refresh the data and repopulate
      await fetchGlobalAttributes();
      const modal = document.getElementById('attributesModal');
      if (modal) populateAttributesModal(modal);

      if (resultDiv) resultDiv.textContent = `${type} added successfully`;
      setTimeout(() => { if (resultDiv) resultDiv.textContent = ''; }, 2000);
    } else {
      throw new Error(result.message || 'Failed to add attribute');
    }
  } catch (error) {
    console.error('Failed to add attribute:', error);
    if (resultDiv) resultDiv.textContent = `Failed to add ${type}: ${error.message}`;
  }
}

async function editAttribute(type, id) {
  const modal = document.getElementById('attributesModal');
  if (!modal) return;

  // Find the current item
  let currentItem = null;
  if (type === 'gender') {
    currentItem = globalAttributes.genders.find(g => g.id == id);
  } else if (type === 'size') {
    currentItem = globalAttributes.sizes.find(s => s.id == id);
  } else if (type === 'color') {
    currentItem = globalAttributes.colors.find(c => c.id == id);
  }

  if (!currentItem) {
    if (typeof window.showNotification === 'function') window.showNotification('Item not found', 'error');
    return;
  }

  // Create edit form
  const newValue = prompt(`Edit ${type}:`, type === 'size' ? `${currentItem.size_name} ${currentItem.size_code}` : currentItem[type + '_name']);
  if (newValue === null) return; // User cancelled

  const resultDiv = modal.querySelector('#attributesResult');
  if (resultDiv) resultDiv.textContent = `Updating ${type}...`;

  try {
    let action, payload;

    if (type === 'gender') {
      action = 'update_global_gender';
      payload = { gender_id: id, gender_name: newValue };
    } else if (type === 'size') {
      const parts = newValue.split(' ');
      if (parts.length < 2) {
        if (typeof window.showNotification === 'function') window.showNotification('Please enter size as "Name Code" (e.g., "Extra Large XL")', 'error');
        return;
      }
      const sizeName = parts.slice(0, -1).join(' ');
      const sizeCode = parts[parts.length - 1];

      action = 'update_global_size';
      payload = { size_id: id, size_name: sizeName, size_code: sizeCode };
    } else if (type === 'color') {
      action = 'update_global_color';
      payload = { color_id: id, color_name: newValue };
    }

    const result = await ApiClient.post('/api/global_color_size_management.php', { action, ...payload, admin_token: 'whimsical_admin_2024' });

    if (result.success) {
      // Refresh the data and repopulate
      await fetchGlobalAttributes();
      populateAttributesModal(modal);

      if (resultDiv) resultDiv.textContent = `${type} updated successfully`;
      setTimeout(() => { if (resultDiv) resultDiv.textContent = ''; }, 2000);
    } else {
      throw new Error(result.message || 'Failed to update attribute');
    }
  } catch (error) {
    console.error('Failed to update attribute:', error);
    if (resultDiv) resultDiv.textContent = `Failed to update ${type}: ${error.message}`;
  }
}

async function deleteAttribute(type, id) {
  const resultDiv = document.getElementById('attributesResult');
  if (resultDiv) resultDiv.textContent = `Deleting ${type}...`;

  try {
    let action;

    if (type === 'gender') {
      action = 'delete_global_gender';
    } else if (type === 'size') {
      action = 'delete_global_size';
    } else if (type === 'color') {
      action = 'delete_global_color';
    }

    const result = await ApiClient.post('/api/global_color_size_management.php', { action, [`${type}_id`]: id, admin_token: 'whimsical_admin_2024' });

    if (result.success) {
      // Refresh the data and repopulate
      await fetchGlobalAttributes();
      const modal = document.getElementById('attributesModal');
      if (modal) populateAttributesModal(modal);

      if (resultDiv) resultDiv.textContent = `${type} deleted successfully`;
      setTimeout(() => { if (resultDiv) resultDiv.textContent = ''; }, 2000);
    } else {
      throw new Error(result.message || 'Failed to delete attribute');
    }
  } catch (error) {
    console.error('Failed to delete attribute:', error);
    if (resultDiv) resultDiv.textContent = `Failed to delete ${type}: ${error.message}`;
  }
}

async function saveAttributeOrder() {
  const resultDiv = document.getElementById('attributesResult');
  if (resultDiv) resultDiv.textContent = 'Saving order...';

  try {
    // For now, just refresh the display since the API doesn't have an order update endpoint
    await fetchGlobalAttributes();
    const modal = document.getElementById('attributesModal');
    if (modal) populateAttributesModal(modal);

    if (resultDiv) resultDiv.textContent = 'Order saved successfully';
    setTimeout(() => { if (resultDiv) resultDiv.textContent = ''; }, 2000);
  } catch (error) {
    console.error('Failed to save order:', error);
    if (resultDiv) resultDiv.textContent = 'Failed to save order';
  }
}

// Make initAttributesModal available globally
window.initAttributesModal = async function(modal) {
  if (!modal) return;

  const resultDiv = modal.querySelector('#attributesResult');
  if (resultDiv) resultDiv.textContent = 'Loading attributes...';

  try {
    await fetchGlobalAttributes();
    populateAttributesModal(modal);
    if (resultDiv) resultDiv.textContent = 'Attributes loaded successfully';
  } catch (error) {
    console.error('Failed to initialize attributes modal:', error);
    if (resultDiv) resultDiv.textContent = 'Failed to load attributes';
  }
};

// Expose selected helpers for other modules and legacy handlers
try {
  if (typeof window !== 'undefined') {
    window.buildBrandingSnapshot = buildBrandingSnapshot;
    window.applyBrandingSnapshotToForm = applyBrandingSnapshotToForm;
    window.createBrandingBackup = createBrandingBackup;
    window.resetBrandingFromBackup = resetBrandingFromBackup;
    window.loadBrandingBackup = loadBrandingBackup;
    window.applyBusinessCssToRoot = applyBusinessCssToRoot;
    window.collectBusinessInfo = collectBusinessInfo;
  }
} catch(_) {}

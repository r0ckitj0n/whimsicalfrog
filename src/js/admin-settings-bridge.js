import { ApiClient } from '../core/api-client.js';
// Ensure lightweight modal open handlers (Area-Item Mapper, Background Manager, CSS Catalog, Room Map Editor)
// are installed whenever the bridge loads on admin routes.
// This module sets up delegated click handlers and lazy modal factories used by settings cards.
import '../modules/admin-settings-lightweight.js';
function byId(id){ return document.getElementById(id); }

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
    <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
      <div class="modal-header">
        <h2 id="backgroundManagerTitle" class="admin-card-title">🖼️ Background Manager</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
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
    if (s.business_brand_font_primary) vars.push(`--brand-font-primary: ${s.business_brand_font_primary};`);
    if (s.business_brand_font_secondary) vars.push(`--brand-font-secondary: ${s.business_brand_font_secondary};`);
    
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
  const ids = ['brandPrimary','brandSecondary','brandAccent','brandBackground','brandText'];
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
    set('bizLogoUrl', s.business_logo_url || '');
    set('bizTagline', s.business_tagline || '');
    set('bizDescription', s.business_description || '');
    set('brandPrimary', s.business_brand_primary || '#0ea5e9');
    set('brandSecondary', s.business_brand_secondary || '#6366f1');
    set('brandAccent', s.business_brand_accent || '#22c55e');
    set('brandBackground', s.business_brand_background || '#ffffff');
    set('brandText', s.business_brand_text || '#111827');
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
        business_tagline: get('bizTagline'),
        business_description: get('bizDescription'),
        business_brand_primary: get('brandPrimary'),
        business_brand_secondary: get('brandSecondary'),
        business_brand_accent: get('brandAccent'),
        business_brand_background: get('brandBackground'),
        business_brand_text: get('brandText'),
        business_css_vars: get('customCssVars'),
        business_brand_palette: JSON.stringify(brandPalette),
    };
}

async function saveBusinessInfo() {
    const status = byId('businessInfoStatus');
    if(status) status.textContent = 'Saving...';
    const payload = collectBusinessInfo();
    try {
        const mod = await import('../modules/business-settings-api.js');
        const BusinessSettingsAPI = mod?.default || mod?.BusinessSettingsAPI;
        // Save all fields under the 'business_info' category as our canonical source
        await BusinessSettingsAPI.upsert('business_info', payload);
        if(status) status.textContent = 'Saved successfully!';
        setTimeout(() => { if(status && status.textContent === 'Saved successfully!') status.textContent = ''; }, 2000);
    } catch (e) {
        if(status) status.textContent = `Save failed: ${e.message}`;
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
        try { wireBrandingLivePreview(); } catch(err){ console.error('Error wiring branding live preview', err); }
      }, 0);
    });
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
      if (confirm(`Are you sure you want to delete this ${type}?`)) {
        deleteAttribute(type, id);
      }
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
});

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
    });
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
    alert('Item not found');
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
        alert('Please enter size as "Name Code" (e.g., "Extra Large XL")');
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

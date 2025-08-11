let mapperIsDrawing = false;
let mapperStartX, mapperStartY;
let mapperCurrentArea = null;
let mapperAreaCount = 0;
const mapperOriginalImageWidth = 1280;
const mapperOriginalImageHeight = 896;



function openSystemConfigModal() {
    // Centralized open to ensure scroll lock via WFModals
    if (typeof window.openModal === 'function') {
        window.openModal('systemConfigModal');
    } else {
        const modal = document.getElementById('systemConfigModal');
        if (modal) { modal.classList.remove('hidden'); modal.classList.add('show'); }
    }
    loadSystemConfiguration();
}

// -----------------------------
// Content Tone & Brand Voice Management (migrated)
// -----------------------------

// In-memory state
let contentToneOptions = [];
let brandVoiceOptions = [];
let contentToneOriginalIds = new Set();
let brandVoiceOriginalIds = new Set();

// ---- Content Tone ----
async function loadContentToneOptions() {
  try {
    const response = await fetch('/api/content_tone_options.php?action=get_active&admin_token=whimsical_admin_2024');
    const result = await response.json();
    if (result.success && Array.isArray(result.options) && result.options.length > 0) {
      contentToneOptions = result.options.map(o => ({ id: o.value, name: o.label, description: o.description }));
      contentToneOriginalIds = new Set(contentToneOptions.map(o => o.id));
      populateContentToneDropdown();
    } else {
      await initializeDefaultContentToneOptions();
    }
  } catch (error) {
    console.error('Error loading content tone options:', error.message);
    loadDefaultContentToneOptions();
  }
}

async function initializeDefaultContentToneOptions() {
  try {
    const response = await fetch('/api/content_tone_options.php?action=initialize_defaults&admin_token=whimsical_admin_2024', { method: 'POST' });
    const result = await response.json();
    if (result.success) {
      await loadContentToneOptions();
    } else {
      loadDefaultContentToneOptions();
    }
  } catch (error) {
    console.error('Error initializing default content tone options:', error.message);
    loadDefaultContentToneOptions();
  }
}

function loadDefaultContentToneOptions() {
  contentToneOptions = [
    { id: 'professional', name: 'Professional', description: 'Formal, business-focused tone' },
    { id: 'friendly', name: 'Friendly', description: 'Warm and approachable' },
    { id: 'casual', name: 'Casual', description: 'Relaxed and informal' },
    { id: 'energetic', name: 'Energetic', description: 'Enthusiastic and dynamic' },
    { id: 'sophisticated', name: 'Sophisticated', description: 'Elegant and refined' },
    { id: 'playful', name: 'Playful', description: 'Fun and lighthearted' },
    { id: 'authoritative', name: 'Authoritative', description: 'Expert and confident' },
    { id: 'conversational', name: 'Conversational', description: 'Natural and engaging' },
    { id: 'inspiring', name: 'Inspiring', description: 'Motivational and uplifting' },
    { id: 'trustworthy', name: 'Trustworthy', description: 'Reliable and credible' },
    { id: 'innovative', name: 'Innovative', description: 'Forward-thinking and creative' },
    { id: 'luxurious', name: 'Luxurious', description: 'Premium and exclusive' }
  ];
  contentToneOriginalIds = new Set(contentToneOptions.map(o => o.id));
  populateContentToneDropdown();
}

function populateContentToneDropdown() {
  const dropdown = document.getElementById('ai_content_tone');
  if (!dropdown) return;
  const currentValue = dropdown.value;
  dropdown.innerHTML = '<option value="">Select content tone...</option>';
  contentToneOptions.forEach(opt => {
    const el = document.createElement('option');
    el.value = opt.id; el.textContent = opt.name; if (opt.description) el.title = opt.description;
    dropdown.appendChild(el);
  });
  if (currentValue) dropdown.value = currentValue;
}

function manageContentToneOptions() {
  showContentToneModal();
}

function showContentToneModal() {
  const existing = document.getElementById('contentToneModal');
  if (existing) existing.remove();
  const modalHtml = `
    <div id="contentToneModal" class="admin-modal-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" data-action="overlay-close">
      <div class="admin-modal bg-white rounded-lg w-full max-w-2xl max-h-[90vh] flex flex-col" role="dialog" aria-modal="true" aria-labelledby="contentToneTitle">
        <div class="flex justify-between items-center border-b p-4">
          <h3 id="contentToneTitle" class="text-lg font-semibold text-gray-800">Manage Content Tone Options</h3>
          <button type="button" class="text-gray-500 hover:text-gray-700 text-xl" data-action="content-tone-close" aria-label="Close">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <p class="text-sm text-gray-600">Manage the content tone options available for AI content generation.</p>
              <button type="button" class="bg-blue-500 hover:bg-blue-600 text-white rounded text-sm px-3 py-1" data-action="content-tone-add">Add Option</button>
            </div>
            <div id="contentToneList" class="space-y-2"></div>
          </div>
        </div>
        <div class="border-t flex justify-end space-x-2 p-3">
          <button type="button" class="text-gray-600 hover:text-gray-800 px-3 py-1" data-action="content-tone-close">Cancel</button>
          <button type="button" class="btn btn-primary px-3 py-1" data-action="content-tone-save">Save Changes</button>
        </div>
      </div>
    </div>`;
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  displayContentToneOptions();
}

function closeContentToneModal() {
  const modal = document.getElementById('contentToneModal');
  if (modal) modal.remove();
}

function displayContentToneOptions() {
  const container = document.getElementById('contentToneList');
  if (!container) return;
  container.innerHTML = '';
  contentToneOptions.forEach((opt, index) => {
    const row = document.createElement('div');
    row.className = 'flex items-center space-x-3 bg-gray-50 rounded-lg p-2';
    row.innerHTML = `
      <input type="text" value="${opt.name || ''}" class="flex-1 border border-gray-300 rounded text-sm px-2 py-1"
             data-action="content-tone-update" data-index="${index}" data-field="name" placeholder="Name">
      <input type="text" value="${opt.description || ''}" class="flex-1 border border-gray-300 rounded text-sm px-2 py-1"
             data-action="content-tone-update" data-index="${index}" data-field="description" placeholder="Description (optional)">
      <button type="button" class="text-red-500 hover:text-red-700" data-action="content-tone-remove" data-index="${index}" aria-label="Remove">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
      </button>`;
    container.appendChild(row);
  });
}

function addContentToneOption() {
  contentToneOptions.push({ id: 'custom_' + Date.now(), name: 'New Tone', description: 'Custom content tone' });
  displayContentToneOptions();
}

function updateContentToneOption(index, field, value) {
  if (contentToneOptions[index]) {
    contentToneOptions[index][field] = value;
    if (field === 'name') {
      // Preserve original id for rename detection
      if (!contentToneOptions[index]._originalId) {
        contentToneOptions[index]._originalId = contentToneOptions[index].id;
      }
      contentToneOptions[index].id = (value || '').toLowerCase().replace(/[^a-z0-9]/g, '_');
    }
  }
}

function removeContentToneOption(index) {
  if (!isNaN(index) && index >= 0 && index < contentToneOptions.length) {
    if (confirm('Are you sure you want to remove this content tone option?')) {
      contentToneOptions.splice(index, 1);
      displayContentToneOptions();
    }
  }
}

async function saveContentToneOptions() {
  try {
    // Determine renames and deletions
    const currentIds = new Set(contentToneOptions.map(o => o.id));
    const renamedOldIds = new Set();
    for (const o of contentToneOptions) {
      if (o._originalId && o._originalId !== o.id) renamedOldIds.add(o._originalId);
    }
    const toDelete = Array.from(contentToneOriginalIds).filter(id => !currentIds.has(id) && !renamedOldIds.has(id));

    // Persist current options
    for (const o of contentToneOptions) {
      if (o._originalId && o._originalId !== o.id && contentToneOriginalIds.has(o._originalId)) {
        // Rename -> delete old, add new
        await deleteContentToneOptionFromDB(o._originalId);
        await saveContentToneOption(o, true);
      } else if (contentToneOriginalIds.has(o.id)) {
        await saveContentToneOption(o, false);
      } else {
        await saveContentToneOption(o, true);
      }
    }

    // Delete removed options
    for (const id of toDelete) {
      await deleteContentToneOptionFromDB(id);
    }

    // Reload and refresh UI
    await loadContentToneOptions();
    populateContentToneDropdown();
    try { showNotification('Content Tone Options', 'Options saved!', 'success'); } catch (_) {}
    closeContentToneModal();
  } catch (error) {
    try { showNotification('Content Tone Options', 'Error saving options: ' + error.message, 'error'); } catch (_) {}
  }
}

async function saveContentToneOption(option, isNew = false) {
  try {
    const action = isNew ? 'add' : 'update';
    const response = await fetch(`/api/content_tone_options.php?action=${action}&admin_token=whimsical_admin_2024`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: option.id, value: option.value || option.id, label: option.name, description: option.description })
    });
    const result = await response.json();
    if (!result.success) { try { showNotification('Content Tone Options', 'Failed to save option: ' + result.error, 'error'); } catch (_) {} }
    return !!result.success;
  } catch (error) {
    try { showNotification('Content Tone Options', 'Error saving option: ' + error.message, 'error'); } catch (_) {}
    return false;
  }
}

async function deleteContentToneOptionFromDB(optionId) {
  try {
    const response = await fetch(`/api/content_tone_options.php?action=delete&id=${optionId}&admin_token=whimsical_admin_2024`, { method: 'DELETE' });
    const result = await response.json();
    if (!result.success) { try { showNotification('Content Tone Options', 'Failed to delete option: ' + result.error, 'error'); } catch (_) {} }
    return !!result.success;
  } catch (error) {
    try { showNotification('Content Tone Options', 'Error deleting option: ' + error.message, 'error'); } catch (_) {}
    return false;
  }
}

// ---- Brand Voice ----
async function loadBrandVoiceOptions() {
  try {
    const response = await fetch('/api/brand_voice_options.php?action=get_active&admin_token=whimsical_admin_2024');
    const result = await response.json();
    if (result.success && Array.isArray(result.options) && result.options.length > 0) {
      brandVoiceOptions = result.options.map(o => ({ id: o.value, name: o.label, description: o.description }));
      brandVoiceOriginalIds = new Set(brandVoiceOptions.map(o => o.id));
      populateBrandVoiceDropdown();
    } else {
      await initializeDefaultBrandVoiceOptions();
    }
  } catch (error) {
    console.error('Error loading brand voice options:', error.message);
    loadDefaultBrandVoiceOptions();
  }
}

async function initializeDefaultBrandVoiceOptions() {
  try {
    const response = await fetch('/api/brand_voice_options.php?action=initialize_defaults&admin_token=whimsical_admin_2024', { method: 'POST' });
    const result = await response.json();
    if (result.success) {
      await loadBrandVoiceOptions();
    } else {
      loadDefaultBrandVoiceOptions();
    }
  } catch (error) {
    console.error('Error initializing default brand voice options:', error.message);
    loadDefaultBrandVoiceOptions();
  }
}

function loadDefaultBrandVoiceOptions() {
  brandVoiceOptions = [
    { id: 'friendly_approachable', name: 'Friendly & Approachable', description: 'Warm, welcoming, and easy to connect with' },
    { id: 'professional_trustworthy', name: 'Professional & Trustworthy', description: 'Business-focused, reliable, and credible' },
    { id: 'playful_fun', name: 'Playful & Fun', description: 'Lighthearted, entertaining, and engaging' },
    { id: 'luxurious_premium', name: 'Luxurious & Premium', description: 'High-end, sophisticated, and exclusive' },
    { id: 'casual_relaxed', name: 'Casual & Relaxed', description: 'Laid-back, informal, and comfortable' },
    { id: 'authoritative_expert', name: 'Authoritative & Expert', description: 'Knowledgeable, confident, and commanding' },
    { id: 'warm_personal', name: 'Warm & Personal', description: 'Intimate, caring, and heartfelt' },
    { id: 'innovative_forward_thinking', name: 'Innovative & Forward-Thinking', description: 'Creative, cutting-edge, and progressive' },
    { id: 'energetic_dynamic', name: 'Energetic & Dynamic', description: 'Enthusiastic, vibrant, and exciting' },
    { id: 'sophisticated_elegant', name: 'Sophisticated & Elegant', description: 'Refined, polished, and tasteful' },
    { id: 'conversational_natural', name: 'Conversational & Natural', description: 'Dialogue-like, personal, and engaging' },
    { id: 'inspiring_motivational', name: 'Inspiring & Motivational', description: 'Uplifting, encouraging, and empowering' }
  ];
  brandVoiceOriginalIds = new Set(brandVoiceOptions.map(o => o.id));
  populateBrandVoiceDropdown();
}

function populateBrandVoiceDropdown() {
  const dropdown = document.getElementById('ai_brand_voice');
  if (!dropdown) return;
  const currentValue = dropdown.value;
  dropdown.innerHTML = '<option value="">Select brand voice...</option>';
  brandVoiceOptions.forEach(opt => {
    const el = document.createElement('option');
    el.value = opt.id; el.textContent = opt.name; if (opt.description) el.title = opt.description;
    dropdown.appendChild(el);
  });
  if (currentValue) dropdown.value = currentValue;
}

function manageBrandVoiceOptions() {
  showBrandVoiceModal();
}

function showBrandVoiceModal() {
  const existing = document.getElementById('brandVoiceModal');
  if (existing) existing.remove();
  const modalHtml = `
    <div id="brandVoiceModal" class="admin-modal-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" data-action="overlay-close">
      <div class="admin-modal bg-white rounded-lg w-full max-w-2xl max-h-[90vh] flex flex-col" role="dialog" aria-modal="true" aria-labelledby="brandVoiceTitle">
        <div class="flex justify-between items-center border-b p-4">
          <h3 id="brandVoiceTitle" class="text-lg font-semibold text-gray-800">Manage Brand Voice Options</h3>
          <button type="button" class="text-gray-500 hover:text-gray-700 text-xl" data-action="brand-voice-close" aria-label="Close">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <p class="text-sm text-gray-600">Manage the brand voice options available for AI content generation.</p>
              <button type="button" class="bg-blue-500 hover:bg-blue-600 text-white rounded text-sm px-3 py-1" data-action="brand-voice-add">Add Option</button>
            </div>
            <div id="brandVoiceList" class="space-y-2"></div>
          </div>
        </div>
        <div class="border-t flex justify-end space-x-2 p-3">
          <button type="button" class="text-gray-600 hover:text-gray-800 px-3 py-1" data-action="brand-voice-close">Cancel</button>
          <button type="button" class="btn btn-primary px-3 py-1" data-action="brand-voice-save">Save Changes</button>
        </div>
      </div>
    </div>`;
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  displayBrandVoiceOptions();
}

function closeBrandVoiceModal() {
  const modal = document.getElementById('brandVoiceModal');
  if (modal) modal.remove();
}

function displayBrandVoiceOptions() {
  const container = document.getElementById('brandVoiceList');
  if (!container) return;
  container.innerHTML = '';
  brandVoiceOptions.forEach((opt, index) => {
    const row = document.createElement('div');
    row.className = 'flex items-center space-x-3 bg-gray-50 rounded-lg p-2';
    row.innerHTML = `
      <input type="text" value="${opt.name || ''}" class="flex-1 border border-gray-300 rounded text-sm px-2 py-1"
             data-action="brand-voice-update" data-index="${index}" data-field="name" placeholder="Name">
      <input type="text" value="${opt.description || ''}" class="flex-1 border border-gray-300 rounded text-sm px-2 py-1"
             data-action="brand-voice-update" data-index="${index}" data-field="description" placeholder="Description (optional)">
      <button type="button" class="text-red-500 hover:text-red-700" data-action="brand-voice-remove" data-index="${index}" aria-label="Remove">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
      </button>`;
    container.appendChild(row);
  });
}

function addBrandVoiceOption() {
  brandVoiceOptions.push({ id: 'custom_' + Date.now(), name: 'New Voice', description: 'Custom brand voice' });
  displayBrandVoiceOptions();
}

function updateBrandVoiceOption(index, field, value) {
  if (brandVoiceOptions[index]) {
    brandVoiceOptions[index][field] = value;
    if (field === 'name') {
      if (!brandVoiceOptions[index]._originalId) {
        brandVoiceOptions[index]._originalId = brandVoiceOptions[index].id;
      }
      brandVoiceOptions[index].id = (value || '').toLowerCase().replace(/[^a-z0-9]/g, '_');
    }
  }
}

function removeBrandVoiceOption(index) {
  if (!isNaN(index) && index >= 0 && index < brandVoiceOptions.length) {
    if (confirm('Are you sure you want to remove this brand voice option?')) {
      brandVoiceOptions.splice(index, 1);
      displayBrandVoiceOptions();
    }
  }
}

async function saveBrandVoiceOptions() {
  try {
    const currentIds = new Set(brandVoiceOptions.map(o => o.id));
    const renamedOldIds = new Set();
    for (const o of brandVoiceOptions) {
      if (o._originalId && o._originalId !== o.id) renamedOldIds.add(o._originalId);
    }
    const toDelete = Array.from(brandVoiceOriginalIds).filter(id => !currentIds.has(id) && !renamedOldIds.has(id));

    for (const o of brandVoiceOptions) {
      if (o._originalId && o._originalId !== o.id && brandVoiceOriginalIds.has(o._originalId)) {
        await deleteBrandVoiceOptionFromDB(o._originalId);
        await saveBrandVoiceOption(o, true);
      } else if (brandVoiceOriginalIds.has(o.id)) {
        await saveBrandVoiceOption(o, false);
      } else {
        await saveBrandVoiceOption(o, true);
      }
    }

    for (const id of toDelete) {
      await deleteBrandVoiceOptionFromDB(id);
    }

    await loadBrandVoiceOptions();
    populateBrandVoiceDropdown();
    try { showNotification('Brand Voice Options', 'Options saved!', 'success'); } catch (_) {}
    closeBrandVoiceModal();
  } catch (error) {
    try { showNotification('Brand Voice Options', 'Error saving options: ' + error.message, 'error'); } catch (_) {}
  }
}

async function saveBrandVoiceOption(option, isNew = false) {
  try {
    const action = isNew ? 'add' : 'update';
    const response = await fetch(`/api/brand_voice_options.php?action=${action}&admin_token=whimsical_admin_2024`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: option.id, value: option.value || option.id, label: option.name, description: option.description })
    });
    const result = await response.json();
    if (!result.success) { try { showNotification('Brand Voice Options', 'Failed to save option: ' + result.error, 'error'); } catch (_) {} }
    return !!result.success;
  } catch (error) {
    try { showNotification('Brand Voice Options', 'Error saving option: ' + error.message, 'error'); } catch (_) {}
    return false;
  }
}

async function deleteBrandVoiceOptionFromDB(optionId) {
  try {
    const response = await fetch(`/api/brand_voice_options.php?action=delete&id=${optionId}&admin_token=whimsical_admin_2024`, { method: 'DELETE' });
    const result = await response.json();
    if (!result.success) { try { showNotification('Brand Voice Options', 'Failed to delete option: ' + result.error, 'error'); } catch (_) {} }
    return !!result.success;
  } catch (error) {
    try { showNotification('Brand Voice Options', 'Error deleting option: ' + error.message, 'error'); } catch (_) {}
    return false;
  }
}

// -----------------------------
// AI Settings Modal & Model Management (migrated)
// -----------------------------

// Open/Close AI Settings modal (safe wrappers)
function openAISettingsModal() {
  const modal = document.getElementById('aiSettingsModal');
  if (!modal) return;
  modal.classList.remove('hidden');
  modal.style.display = 'flex';
  try { if (typeof loadAISettings === 'function') loadAISettings(); } catch (_) {}
  try { loadAIProviders(); } catch (_) {}
  try { if (typeof loadContentToneOptions === 'function') loadContentToneOptions(); } catch (_) {}
  try { if (typeof loadBrandVoiceOptions === 'function') loadBrandVoiceOptions(); } catch (_) {}
}

function closeAISettingsModal() {
  const modal = document.getElementById('aiSettingsModal');
  if (!modal) return;
  modal.style.display = 'none';
  modal.classList.add('hidden');
}

async function loadAIProviders() {
  try {
    const response = await fetch('/api/ai_settings.php?action=get_providers');
    const data = await response.json();
    if (data.success) {
      displayAIProviders(data.providers);
    } else {
      console.error('Failed to load AI providers:', data.error);
    }
  } catch (error) {
    console.error('Error loading AI providers:', error.message);
  }
}

function displayAIProviders(providers) {
  try {
    window.aiProviders = providers;
  } catch (_) {}
}

function toggleSection(section) {
  if (!section) return;
  const content = document.getElementById(`${section}-content`);
  const icon = document.getElementById(`${section}-icon`);
  if (content) content.classList.toggle('hidden');
  if (icon && content) icon.textContent = !content.classList.contains('hidden') ? '▼' : '▶';
}

function toggleProviderSections() {
  const selectedProvider = document.querySelector('input[name="ai_provider"]:checked')?.value || getDefaultAIProvider();
  const providers = ['openai','anthropic','google','meta'];
  providers.forEach(p => {
    const sec = document.getElementById(`${p}_section`);
    if (!sec) return;
    if (selectedProvider === 'jons_ai') {
      sec.classList.add('hidden');
    } else {
      if (p === selectedProvider) sec.classList.remove('hidden');
      else sec.classList.add('hidden');
    }
  });
  try {
    if (typeof window.aiSettings !== 'undefined' && window.aiSettings) {
      loadModelsForCurrentProvider(window.aiSettings);
    } else if (selectedProvider && selectedProvider !== 'jons_ai') {
      refreshModels(selectedProvider);
    }
  } catch (_) {}
}

async function saveAISettings() {
  const settings = {
    ai_provider: document.querySelector('input[name="ai_provider"]:checked')?.value || getDefaultAIProvider(),
    openai_api_key: document.getElementById('openai_api_key')?.value || '',
    openai_model: document.getElementById('openai_model')?.value || 'gpt-3.5-turbo',
    anthropic_api_key: document.getElementById('anthropic_api_key')?.value || '',
    anthropic_model: document.getElementById('anthropic_model')?.value || 'claude-3-haiku-20240307',
    google_api_key: document.getElementById('google_api_key')?.value || '',
    google_model: document.getElementById('google_model')?.value || 'gemini-pro',
    meta_api_key: document.getElementById('meta_api_key')?.value || '',
    meta_model: document.getElementById('meta_model')?.value || 'meta-llama/llama-3.1-70b-instruct',
    ai_temperature: parseFloat(document.getElementById('ai_temperature')?.value || 0.7),
    ai_max_tokens: parseInt(document.getElementById('ai_max_tokens')?.value || 1000),
    ai_timeout: parseInt(document.getElementById('ai_timeout')?.value || 30),
    fallback_to_local: document.getElementById('fallback_to_local')?.checked || false,
    ai_brand_voice: document.getElementById('ai_brand_voice')?.value || '',
    ai_content_tone: document.getElementById('ai_content_tone')?.value || 'professional',
    ai_cost_temperature: parseFloat(document.getElementById('ai_cost_temperature')?.value || 0.7),
    ai_price_temperature: parseFloat(document.getElementById('ai_price_temperature')?.value || 0.7),
    ai_cost_multiplier_base: parseFloat(document.getElementById('ai_cost_multiplier_base')?.value || 1.0),
    ai_price_multiplier_base: parseFloat(document.getElementById('ai_price_multiplier_base')?.value || 1.0),
    ai_conservative_mode: document.getElementById('ai_conservative_mode')?.checked || false,
    ai_market_research_weight: parseFloat(document.getElementById('ai_market_research_weight')?.value || 0.3),
    ai_cost_plus_weight: parseFloat(document.getElementById('ai_cost_plus_weight')?.value || 0.4),
    ai_value_based_weight: parseFloat(document.getElementById('ai_value_based_weight')?.value || 0.3)
  };

  try {
    const response = await fetch('/api/ai_settings.php?action=update_settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(settings)
    });
    const result = await response.json();
    if (result.success) {
      showAISettingsSuccess('AI settings saved successfully!');
    } else {
      showAISettingsError('Failed to save AI settings: ' + result.error);
    }
  } catch (error) {
    showAISettingsError('Error saving AI settings: ' + error.message);
  }
}

function showAISettingsSuccess(message) {
  try { showNotification('AI Settings Saved', message, 'success'); } catch (_) { console.log(message); }
  setTimeout(() => { try { closeAISettingsModal(); } catch (_) {} }, 1500);
}

function showAISettingsError(message) {
  try { showNotification('AI Settings Error', message, 'error'); } catch (_) { console.error(message); }
}

async function testAIProvider() {
  const selectedProvider = document.querySelector('input[name="ai_provider"]:checked')?.value || getDefaultAIProvider();
  try {
    try { showNotification('Testing AI Provider', `Testing ${selectedProvider} provider...`, 'info'); } catch (_) {}
    const response = await fetch(`/api/ai_settings.php?action=test_provider&provider=${selectedProvider}`);
    const result = await response.json();
    if (result.success) {
      try { showNotification('AI Provider Test', `✅ ${selectedProvider} provider test successful!`, 'success'); } catch (_) {}
    } else {
      try { showNotification('AI Provider Test', `❌ ${selectedProvider} provider test failed: ${result.message}`, 'error'); } catch (_) {}
    }
  } catch (error) {
    try { showNotification('AI Provider Test', `❌ Test failed: ${error.message}`, 'error'); } catch (_) { console.error(error); }
  }
}

// AI Model Loading Functions
let availableModels = {};

async function loadAllModels() {
  try {
    const response = await fetch('/api/get_ai_models.php?provider=all');
    const result = await response.json();
    if (result.success) {
      availableModels = result.models;
      populateModelDropdown('openai', availableModels.openai);
      populateModelDropdown('anthropic', availableModels.anthropic);
      populateModelDropdown('google', availableModels.google);
      populateModelDropdown('meta', availableModels.meta);
    } else {
      console.error('❌ Failed to load AI models:', result.error);
      loadFallbackModels();
    }
  } catch (error) {
    console.error('❌ Error loading AI models:', error.message);
    loadFallbackModels();
  }
}

async function loadAllModelsWithSelection(settings) {
  try {
    const response = await fetch('/api/get_ai_models.php?provider=all');
    const result = await response.json();
    if (result.success) {
      availableModels = result.models;
      populateModelDropdownWithSelection('openai', availableModels.openai, settings.openai_model);
      populateModelDropdownWithSelection('anthropic', availableModels.anthropic, settings.anthropic_model);
      populateModelDropdownWithSelection('google', availableModels.google, settings.google_model);
      populateModelDropdownWithSelection('meta', availableModels.meta, settings.meta_model);
    } else {
      console.error('❌ Failed to load AI models:', result.error);
      loadFallbackModelsWithSelection(settings);
    }
  } catch (error) {
    console.error('❌ Error loading AI models:', error.message);
    loadFallbackModelsWithSelection(settings);
  }
}

function getDefaultAIProvider() {
  if (typeof window.aiSettings !== 'undefined' && window.aiSettings) {
    const providers = ['jons_ai', 'openai', 'anthropic', 'google', 'meta'];
    for (const provider of providers) {
      if (window.aiSettings[`${provider}_api_key`]) return provider;
    }
  }
  return 'jons_ai';
}

async function loadModelsForCurrentProvider(settings) {
  const selectedProvider = settings.ai_provider || getDefaultAIProvider();
  if (selectedProvider === 'jons_ai') return;
  try {
    const response = await fetch(`/api/get_ai_models.php?provider=${selectedProvider}&admin_token=whimsical_admin_2024`);
    const result = await response.json();
    if (result.success) {
      availableModels[selectedProvider] = result.models;
      const modelKey = `${selectedProvider}_model`;
      populateModelDropdownWithSelection(selectedProvider, result.models, settings[modelKey]);
    } else {
      console.error(`❌ Failed to load ${selectedProvider} models:`, result.error);
      loadFallbackModelsForProviderWithSelection(selectedProvider, settings);
    }
  } catch (error) {
    console.error(`❌ Error loading ${selectedProvider} models:`, error.message);
    loadFallbackModelsForProviderWithSelection(selectedProvider, settings);
  }
}

async function refreshModels(provider) {
  try {
    try { showNotification('Refreshing Models', `Loading ${provider} models...`, 'info'); } catch (_) {}
    const response = await fetch(`/api/get_ai_models.php?provider=${provider}&admin_token=whimsical_admin_2024`);
    const result = await response.json();
    if (result.success) {
      availableModels[provider] = result.models;
      populateModelDropdown(provider, result.models);
      try { showNotification('Models Refreshed', `✅ ${provider} models updated`, 'success'); } catch (_) {}
    } else {
      try { showNotification('Models Error', `❌ Failed to load ${provider} models: ${result.error}`, 'error'); } catch (_) {}
      loadFallbackModelsForProvider(provider);
    }
  } catch (error) {
    try { showNotification('Models Error', `❌ Error loading ${provider} models: ${error.message}`, 'error'); } catch (_) {}
    loadFallbackModelsForProvider(provider);
  }
}

function populateModelDropdown(provider, models) {
  const selectElement = document.getElementById(`${provider}_model`);
  if (!selectElement) return;
  const currentValue = selectElement.value;
  selectElement.innerHTML = '';
  if (!models || models.length === 0) {
    selectElement.innerHTML = '<option value="">No models available</option>';
    return;
  }
  models.forEach(model => {
    const option = document.createElement('option');
    option.value = model.id;
    option.textContent = `${model.name} - ${model.description}`;
    option.title = model.description;
    selectElement.appendChild(option);
  });
  if (currentValue && selectElement.querySelector(`option[value="${currentValue}"]`)) {
    selectElement.value = currentValue;
  } else {
    selectElement.selectedIndex = 0;
  }
}

function populateModelDropdownWithSelection(provider, models, selectedValue) {
  const selectElement = document.getElementById(`${provider}_model`);
  if (!selectElement) return;
  selectElement.innerHTML = '';
  if (!models || models.length === 0) {
    selectElement.innerHTML = '<option value="">No models available</option>';
    return;
  }
  models.forEach(model => {
    const option = document.createElement('option');
    option.value = model.id;
    option.textContent = `${model.name} - ${model.description}`;
    option.title = model.description;
    if (model.id === selectedValue) option.selected = true;
    selectElement.appendChild(option);
  });
  if (!selectedValue || !selectElement.querySelector(`option[value="${selectedValue}"]`)) {
    selectElement.selectedIndex = 0;
  }
}

function loadFallbackModels() {
  const fallbackModels = {
    openai: [
      { id: 'gpt-4o', name: 'GPT-4o', description: 'Latest and most capable model' },
      { id: 'gpt-4-turbo', name: 'GPT-4 Turbo', description: 'Fast and capable' },
      { id: 'gpt-4', name: 'GPT-4', description: 'Highly capable model' },
      { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo', description: 'Fast and affordable' }
    ],
    anthropic: [
      { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet', description: 'Most intelligent model' },
      { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku', description: 'Fastest model' },
      { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus', description: 'Most capable for reasoning' },
      { id: 'claude-3-sonnet-20240229', name: 'Claude 3 Sonnet', description: 'Balanced performance' },
      { id: 'claude-3-haiku-20240307', name: 'Claude 3 Haiku', description: 'Fast and affordable' }
    ],
    google: [
      { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro', description: 'Most capable Gemini model' },
      { id: 'gemini-1.5-flash', name: 'Gemini 1.5 Flash', description: 'Fast and efficient' },
      { id: 'gemini-pro', name: 'Gemini Pro', description: 'Balanced performance' },
      { id: 'gemini-pro-vision', name: 'Gemini Pro Vision', description: 'Multimodal capabilities' }
    ],
    meta: [
      { id: 'meta-llama/llama-3.1-405b-instruct', name: 'Llama 3.1 405B', description: 'Most capable model' },
      { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Llama 3.1 70B', description: 'Balanced performance' },
      { id: 'meta-llama/llama-3.1-8b-instruct', name: 'Llama 3.1 8B', description: 'Fast and affordable' },
      { id: 'meta-llama/llama-3-70b-instruct', name: 'Llama 3 70B', description: 'Previous generation' },
      { id: 'meta-llama/llama-3-8b-instruct', name: 'Llama 3 8B', description: 'Lightweight' }
    ]
  };
  availableModels = fallbackModels;
  populateModelDropdown('openai', fallbackModels.openai);
  populateModelDropdown('anthropic', fallbackModels.anthropic);
  populateModelDropdown('google', fallbackModels.google);
  populateModelDropdown('meta', fallbackModels.meta);
}

function loadFallbackModelsForProvider(provider) {
  const fallbackModels = {
    openai: [
      { id: 'gpt-4o', name: 'GPT-4o', description: 'Latest and most capable model' },
      { id: 'gpt-4-turbo', name: 'GPT-4 Turbo', description: 'Fast and capable' },
      { id: 'gpt-4', name: 'GPT-4', description: 'Highly capable model' },
      { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo', description: 'Fast and affordable' }
    ],
    anthropic: [
      { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet', description: 'Most intelligent model' },
      { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku', description: 'Fastest model' },
      { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus', description: 'Most capable for reasoning' },
      { id: 'claude-3-sonnet-20240229', name: 'Claude 3 Sonnet', description: 'Balanced performance' },
      { id: 'claude-3-haiku-20240307', name: 'Claude 3 Haiku', description: 'Fast and affordable' }
    ],
    google: [
      { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro', description: 'Most capable Gemini model' },
      { id: 'gemini-1.5-flash', name: 'Gemini 1.5 Flash', description: 'Fast and efficient' },
      { id: 'gemini-pro', name: 'Gemini Pro', description: 'Balanced performance' },
      { id: 'gemini-pro-vision', name: 'Gemini Pro Vision', description: 'Multimodal capabilities' }
    ]
  };
  if (fallbackModels[provider]) {
    availableModels[provider] = fallbackModels[provider];
    populateModelDropdown(provider, fallbackModels[provider]);
  }
}

function loadFallbackModelsWithSelection(settings) {
  const fallbackModels = {
    openai: [
      { id: 'gpt-4o', name: 'GPT-4o', description: 'Latest and most capable model' },
      { id: 'gpt-4-turbo', name: 'GPT-4 Turbo', description: 'Fast and capable' },
      { id: 'gpt-4', name: 'GPT-4', description: 'Highly capable model' },
      { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo', description: 'Fast and affordable' }
    ],
    anthropic: [
      { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet', description: 'Most intelligent model' },
      { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku', description: 'Fastest model' },
      { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus', description: 'Most capable for reasoning' },
      { id: 'claude-3-sonnet-20240229', name: 'Claude 3 Sonnet', description: 'Balanced performance' },
      { id: 'claude-3-haiku-20240307', name: 'Claude 3 Haiku', description: 'Fast and affordable' }
    ],
    google: [
      { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro', description: 'Most capable Gemini model' },
      { id: 'gemini-1.5-flash', name: 'Gemini 1.5 Flash', description: 'Fast and efficient' },
      { id: 'gemini-pro', name: 'Gemini Pro', description: 'Balanced performance' },
      { id: 'gemini-pro-vision', name: 'Gemini Pro Vision', description: 'Multimodal capabilities' }
    ],
    meta: [
      { id: 'meta-llama/llama-3.1-405b-instruct', name: 'Llama 3.1 405B', description: 'Most capable model' },
      { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Llama 3.1 70B', description: 'Balanced performance' },
      { id: 'meta-llama/llama-3.1-8b-instruct', name: 'Llama 3.1 8B', description: 'Fast and affordable' },
      { id: 'meta-llama/llama-3-70b-instruct', name: 'Llama 3 70B', description: 'Previous generation' },
      { id: 'meta-llama/llama-3-8b-instruct', name: 'Llama 3 8B', description: 'Lightweight' }
    ]
  };
  availableModels = fallbackModels;
  populateModelDropdownWithSelection('openai', fallbackModels.openai, settings.openai_model);
  populateModelDropdownWithSelection('anthropic', fallbackModels.anthropic, settings.anthropic_model);
  populateModelDropdownWithSelection('google', fallbackModels.google, settings.google_model);
  populateModelDropdownWithSelection('meta', fallbackModels.meta, settings.meta_model);
}

function loadFallbackModelsForProviderWithSelection(provider, settings) {
  const fallbackModels = {
    openai: [
      { id: 'gpt-4o', name: 'GPT-4o', description: 'Latest and most capable model' },
      { id: 'gpt-4-turbo', name: 'GPT-4 Turbo', description: 'Fast and capable' },
      { id: 'gpt-4', name: 'GPT-4', description: 'Highly capable model' },
      { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo', description: 'Fast and affordable' }
    ],
    anthropic: [
      { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet', description: 'Most intelligent model' },
      { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku', description: 'Fastest model' },
      { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus', description: 'Most capable for reasoning' },
      { id: 'claude-3-sonnet-20240229', name: 'Claude 3 Sonnet', description: 'Balanced performance' },
      { id: 'claude-3-haiku-20240307', name: 'Claude 3 Haiku', description: 'Fast and affordable' }
    ],
    google: [
      { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro', description: 'Most capable Gemini model' },
      { id: 'gemini-1.5-flash', name: 'Gemini 1.5 Flash', description: 'Fast and efficient' },
      { id: 'gemini-pro', name: 'Gemini Pro', description: 'Balanced performance' },
      { id: 'gemini-pro-vision', name: 'Gemini Pro Vision', description: 'Multimodal capabilities' }
    ],
    meta: [
      { id: 'meta-llama/llama-3.1-405b-instruct', name: 'Llama 3.1 405B', description: 'Most capable model' },
      { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Llama 3.1 70B', description: 'Balanced performance' },
      { id: 'meta-llama/llama-3.1-8b-instruct', name: 'Llama 3.1 8B', description: 'Fast and affordable' },
      { id: 'meta-llama/llama-3-70b-instruct', name: 'Llama 3 70B', description: 'Previous generation' },
      { id: 'meta-llama/llama-3-8b-instruct', name: 'Llama 3 8B', description: 'Lightweight' }
    ]
  };
  if (fallbackModels[provider]) {
    availableModels[provider] = fallbackModels[provider];
    const modelKey = `${provider}_model`;
    populateModelDropdownWithSelection(provider, fallbackModels[provider], settings[modelKey]);
  }
}

async function loadSystemConfiguration() {
    const loadingDiv = document.getElementById('systemConfigLoading');
    const contentDiv = document.getElementById('systemConfigContent');
    
    // Show loading state
    loadingDiv.style.display = 'block';
    
    try {
        const response = await fetch('/api/get_system_config.php');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Hide loading and populate content
            loadingDiv.style.display = 'none';
            contentDiv.innerHTML = generateSystemConfigHTML(data);
        } else {
            throw new Error(result.error || 'Failed to load system configuration');
        }
    } catch (error) {
        console.error('Error loading system configuration:', error);
        loadingDiv.innerHTML = `
            <div class="modal-loading">
                <div class="text-red-500">⚠️</div>
                <p class="text-red-600">Failed to load system configuration</p>
                <p class="text-sm text-gray-500">${error.message}</p>
                <button onclick="loadSystemConfiguration()" class="bg-orange-500 text-white rounded hover:bg-orange-600">
                    Retry
                </button>
            </div>
        `;
    }
}

function generateSystemConfigHTML(data) {
    const lastOrderDate = data.statistics.last_order_date ? 
        new Date(data.statistics.last_order_date).toLocaleDateString() : 'No orders yet';
    
    return `
        <!-- Current System Architecture -->
        <div class="bg-green-50 border-l-4 border-green-400">
            <h4 class="font-semibold text-green-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                Current System Architecture (Live Data)
            </h4>
            <div class="space-y-3 text-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h5 class="font-semibold text-green-700">🎯 Primary Identifier</h5>
                        <p class="text-green-600"><strong>${data.system_info.primary_identifier}</strong> - Human-readable codes</p>
                        <p class="text-xs text-green-600">Format: ${data.system_info.sku_format}</p>
                        <p class="text-xs text-green-600">Examples: ${data.sample_skus.slice(0, 3).join(', ')}</p>
                    </div>
                    <div>
                        <h5 class="font-semibold text-green-700">🏷️ Main Entity</h5>
                        <p class="text-green-600"><strong>${data.system_info.main_entity}</strong></p>
                        <p class="text-xs text-green-600">All inventory and shop items</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comprehensive SKU Methodology Documentation -->
        <div class="bg-blue-50 border-l-4 border-blue-400">
            <h4 class="font-semibold text-blue-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" clip-rule="evenodd"></path>
                </svg>
                📖 Complete SKU & ID Methodology Documentation
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="space-y-3">
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">🏷️ SKU System Overview</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>• <strong>Primary Format:</strong> WF-[CATEGORY]-[NUMBER]</p>
                            <p>• <strong>Enhanced Format:</strong> WF-[CAT]-[GENDER]-[SIZE]-[COLOR]-[NUM]</p>
                            <p>• <strong>Database:</strong> SKU-only system (no legacy IDs)</p>
                            <p>• <strong>Generation:</strong> Automatic via API with sequential numbering</p>
                            <p>• <strong>Usage:</strong> Primary key across all tables</p>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">🔄 Migration History</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>✅ <strong>Phase 1:</strong> Eliminated dual itemId/SKU system</p>
                            <p>✅ <strong>Phase 2:</strong> Migrated "products" → "items" terminology</p>
                            <p>✅ <strong>Phase 3:</strong> Fixed order ID generation (sequence-based)</p>
                            <p>✅ <strong>Phase 4:</strong> Implemented global color/size management</p>
                            <p>✅ <strong>Current:</strong> Pure SKU-only architecture</p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">🛠️ API Endpoints</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>• <code>/api/next_sku.php</code> - Generate new SKUs</p>
                            <p>• <code>/api/get_items.php</code> - Retrieve items by SKU</p>
                            <p>• <code>/api/get_item_images.php</code> - Item images</p>
                            <p>• <code>/api/add-order.php</code> - Create orders (fixed)</p>
                            <p>• <code>/api/update-inventory-field.php</code> - SKU updates</p>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded border">
                        <h5 class="font-semibold text-blue-700">📊 Current Statistics</h5>
                        <div class="text-xs text-blue-600 space-y-1">
                            <p>• <strong>Items:</strong> ${data.statistics.total_items} (${data.statistics.total_images} images)</p>
                            <p>• <strong>Orders:</strong> ${data.statistics.total_orders} (${data.statistics.total_order_items} items)</p>
                            <p>• <strong>Categories:</strong> ${data.statistics.categories_count} active</p>
                            <p>• <strong>Last Order:</strong> ${data.statistics.last_order_date ? new Date(data.statistics.last_order_date).toLocaleDateString() : 'None'}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SKU Categories -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400">
            <h4 class="font-semibold text-yellow-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                </svg>
                Active Categories & SKU Codes
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                ${Object.entries(data.category_codes).map(([category, code]) => {
                    const isActive = data.categories.includes(category);
                    return `
                        <div class="text-center ${isActive ? 'bg-yellow-100' : 'bg-gray-100'} rounded">
                            <div class="font-semibold ${isActive ? 'text-yellow-700' : 'text-gray-500'}">${code}</div>
                            <div class="text-xs ${isActive ? 'text-yellow-600' : 'text-gray-400'}">${category}</div>
                            ${isActive ? '<div class="text-xs text-green-600">✅ Active</div>' : '<div class="text-xs text-gray-400">Inactive</div>'}
                        </div>
                    `;
                }).join('')}
            </div>
        </div>



        <!-- ID Number Legend -->
        <div class="bg-orange-50 border-l-4 border-orange-400">
            <h4 class="font-semibold text-orange-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                ID Number Legend & Formats
            </h4>
            <div class="space-y-4">
                <!-- Customer IDs -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        Customer IDs
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> [MonthLetter][Day][SequenceNumber]</p>
                        ${data.id_formats.recent_customers.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_customers.map(c => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${c.id}</code> (${c.username || 'No username'})`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 py-0\.5 rounded">F14004</code></p>`
                        }
                        <div class="text-xs text-orange-500">
                            <p>• <strong>F</strong> = June (A=Jan, B=Feb, C=Mar, D=Apr, E=May, F=Jun, G=Jul, H=Aug, I=Sep, J=Oct, K=Nov, L=Dec)</p>
                            <p>• <strong>14</strong> = 14th day of the month</p>
                            <p>• <strong>004</strong> = 4th customer registered</p>
                        </div>
                    </div>
                </div>

                <!-- Order IDs - Updated with Sequence Fix -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2L3 7v11a1 1 0 001 1h12a1 1 0 001-1V7l-7-5zM8 15v-3h4v3H8z" clip-rule="evenodd"></path>
                        </svg>
                        Order IDs - Sequence-Based System ✅
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> [CustomerNum][MonthLetter][Day][ShippingCode][SequenceNum]</p>
                        ${data.id_formats.recent_orders.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_orders.map(o => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${o}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 py-0\.5 rounded">01F30P75</code></p>`
                        }
                        <div class="text-xs text-orange-500">
                            <p>• <strong>01</strong> = Last 2 digits of customer number</p>
                            <p>• <strong>F30</strong> = June 30th (order date)</p>
                            <p>• <strong>P</strong> = Pickup (P=Pickup, L=Local, U=USPS, F=FedEx, X=UPS)</p>
                            <p>• <strong>75</strong> = Sequential number (eliminates duplicates)</p>
                        </div>
                        
                        <!-- Recent Fix Notice -->
                        <div class="bg-green-50 rounded">
                            <p class="font-medium text-green-700">🔧 Recent Fix Applied:</p>
                            <p class="text-xs text-green-600">• Replaced random number with sequence-based system</p>
                            <p class="text-xs text-green-600">• Eliminates "Duplicate entry" constraint violations</p>
                            <p class="text-xs text-green-600">• Sequential: 17F30P75 → 17F30P76 → 17F30P77</p>
                            <p class="text-xs text-green-600">• Robust for concurrent checkout processing</p>
                        </div>
                        
                        <!-- Shipping Codes -->
                        <div class="bg-blue-50 rounded">
                            <p class="font-medium text-blue-700">📦 Shipping Method Codes:</p>
                            <p class="text-xs text-blue-600">• <strong>P</strong> = Customer Pickup • <strong>L</strong> = Local Delivery</p>
                            <p class="text-xs text-blue-600">• <strong>U</strong> = USPS • <strong>F</strong> = FedEx • <strong>X</strong> = UPS</p>
                        </div>
                    </div>
                </div>

                <!-- Product/Inventory IDs (SKUs) - Enhanced Documentation -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                        </svg>
                        Product & Inventory IDs (SKUs) - Complete System
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Primary Format:</strong> ${data.system_info.sku_format}</p>
                        ${data.sample_skus.length > 0 ? 
                            `<p><strong>Current Examples:</strong> ${data.sample_skus.slice(0, 5).map(sku => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${sku}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Examples:</strong> <code class="bg-orange-100 py-0\.5 rounded">WF-TS-001</code>, <code class="bg-orange-100 py-0\.5 rounded">WF-TU-002</code></p>`
                        }
                        
                        <!-- Enhanced SKU Format -->
                        <div class="bg-orange-50 rounded">
                            <p class="font-medium text-orange-700">Enhanced SKU Format (Optional):</p>
                            <p><strong>WF-[CATEGORY]-[GENDER]-[SIZE]-[COLOR]-[NUMBER]</strong></p>
                            <p class="text-xs">Example: <code class="bg-orange-100 py-0\.5 rounded">WF-TS-M-L-BLK-001</code> = WhimsicalFrog T-Shirt, Men's Large, Black, #001</p>
                        </div>
                        
                        <!-- Category Codes -->
                        <div class="text-xs text-orange-500">
                            <p class="font-medium">Category Codes:</p>
                            ${Object.entries(data.category_codes).map(([category, code]) => 
                                `<p>• <strong>${code}</strong> = ${category}</p>`
                            ).join('')}
                        </div>
                        
                        <!-- SKU Generation -->
                        <div class="bg-green-50 rounded">
                            <p class="font-medium text-green-700">🔄 Auto-Generation:</p>
                            <p class="text-xs text-green-600">• SKUs are automatically generated with sequential numbering</p>
                            <p class="text-xs text-green-600">• API: <code>/api/next_sku.php?cat=[CATEGORY]</code></p>
                            <p class="text-xs text-green-600">• Enhanced: <code>&gender=M&size=L&color=Black&enhanced=true</code></p>
                        </div>
                        
                        <!-- Database Integration -->
                        <div class="bg-blue-50 rounded">
                            <p class="font-medium text-blue-700">🗄️ Database Integration:</p>
                            <p class="text-xs text-blue-600">• Primary table: <code>items</code> (SKU as primary key)</p>
                            <p class="text-xs text-blue-600">• Images: <code>item_images</code> (linked via SKU)</p>
                            <p class="text-xs text-blue-600">• Orders: <code>order_items</code> (references SKU)</p>
                            <p class="text-xs text-blue-600">• Migration complete: No legacy ID columns</p>
                        </div>
                    </div>
                </div>

                <!-- Order Item IDs -->
                <div class="bg-white rounded-lg border border-orange-200">
                    <h5 class="font-semibold text-orange-700 flex items-center text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path>
                        </svg>
                        Order Item IDs
                    </h5>
                    <div class="text-xs text-orange-600 space-y-1">
                        <p><strong>Format:</strong> OI[SequentialNumber]</p>
                        ${data.id_formats.recent_order_items.length > 0 ? 
                            `<p><strong>Recent Examples:</strong> ${data.id_formats.recent_order_items.map(oi => 
                                `<code class="bg-orange-100 py-0\.5 rounded">${oi}</code>`
                            ).join(', ')}</p>` : 
                            `<p><strong>Example:</strong> <code class="bg-orange-100 py-0\.5 rounded">OI001</code></p>`
                        }
                        <div class="text-xs text-orange-500">
                            <p>• <strong>OI</strong> = Order Item prefix</p>
                            <p>• <strong>001</strong> = Sequential 3-digit number (001, 002, 003, etc.)</p>
                            <p class="italic">Simple, clean, and easy to reference!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    `;
}

function closeSystemConfigModal() {
    if (typeof window.closeModal === 'function') {
        window.closeModal('systemConfigModal');
    } else {
        const el = document.getElementById('systemConfigModal');
        if (el) { el.classList.remove('show'); el.classList.add('hidden'); }
    }
}

window.openDatabaseMaintenanceModal = function openDatabaseMaintenanceModal() {
    console.log('openDatabaseMaintenanceModal called');
    const modal = document.getElementById('databaseMaintenanceModal');
    if (!modal) {
        console.error('databaseMaintenanceModal element not found!');
        if (window.showError) {
            window.showError('Database Maintenance modal not found. Please refresh the page.');
        } else {
            alert('Database Maintenance modal not found. Please refresh the page.');
        }
        return;
    }
    console.log('Opening database maintenance modal...');
    if (typeof window.openModal === 'function') {
        window.openModal('databaseMaintenanceModal');
    } else {
        modal.classList.remove('hidden');
        modal.classList.add('show');
    }
    // Hide loading and show connection tab by default
    document.getElementById('databaseMaintenanceLoading').style.display = 'none';
    switchDatabaseTab(document.querySelector('[data-tab="connection"]'), 'connection');
    // Also load the current configuration immediately
    loadCurrentDatabaseConfig();
}

async function loadDatabaseInformation() {
    const loadingDiv = document.getElementById('databaseMaintenanceLoading');
    const contentDiv = document.getElementById('databaseMaintenanceContent');
    
    // Show loading state
    loadingDiv.style.display = 'block';
    
    try {
        const response = await fetch('/api/get_database_info.php');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Hide loading and populate content
            loadingDiv.style.display = 'none';
            contentDiv.innerHTML = generateDatabaseMaintenanceHTML(data);
        } else {
            throw new Error(result.error || 'Failed to load database information');
        }
    } catch (error) {
        console.error('Error loading database information:', error);
        loadingDiv.innerHTML = `
            <div class="modal-loading">
                <div class="text-red-500">⚠️</div>
                <p class="text-red-600">Failed to load database information</p>
                <p class="text-sm text-gray-500">${error.message}</p>
                <button onclick="loadDatabaseInformation()" class="bg-red-500 text-white rounded hover:bg-red-600">
                    Retry
                </button>
            </div>
        `;
    }
}

function generateDatabaseMaintenanceHTML(data) {
    return `
        <!-- Database Schema -->
        <div class="bg-purple-50 border-l-4 border-purple-400">
            <h4 class="font-semibold text-purple-800 flex items-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"></path>
                </svg>
                Database Tables & Structure (${data.total_active} Active + ${data.total_backup} Backup)
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                ${Object.entries(data.organized || {}).map(([category, tables]) => {
                    const categoryLabels = {
                        'core_ecommerce': '🛒 Core E-commerce',
                        'user_management': '👥 User Management', 
                        'inventory_cost': '💰 Inventory & Cost',
                        'product_categories': '🏷️ Product Categories',
                        'room_management': '🏠 Room Management',
                        'email_system': '📧 Email System',
                        'business_config': '⚙️ Business Config',
                        'system_logs': '📄 System Logs',
                        'backup_tables': '🗄️ Backup Tables'
                    };

                    const categoryLabel = categoryLabels[category] || `❓ Unknown Category: ${category}`;

                    return `
                        <div class="bg-white rounded border border-purple-200 p-3">
                            <h5 class="font-semibold text-purple-700">${categoryLabel}</h5>
                            <ul class="text-xs text-purple-600 mt-2 space-y-1">
                                ${tables.map(table => `
                                    <li class="flex justify-between items-center">
                                        <span>${table.name}</span>
                                        <span class="text-purple-500 font-mono">${table.rows} rows</span>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;
}

// -----------------------------
// Database Maintenance Helpers
// -----------------------------

function getEvent(e) {
    // Normalize event across inline and programmatic invocations
    return e || (typeof window !== 'undefined' ? window.event : undefined);
}

function showResult(element, success, message) {
    if (!element) return;
    element.className = success 
        ? 'px-3 py-2 bg-green-50 border border-green-200 rounded text-sm'
        : 'px-3 py-2 bg-red-50 border border-red-200 rounded text-sm';
    element.innerHTML = message;
    element.classList.remove('hidden');
}

async function scanDatabaseConnections(e) {
    const evt = getEvent(e);
    const button = evt?.target || document.querySelector('[data-action="scan-db"], #scanDatabaseConnectionsBtn');
    const resultsDiv = document.getElementById('conversionResults');
    if (button) {
        button.disabled = true;
        button.textContent = '🔄 Scanning...';
    }
    if (resultsDiv) {
        resultsDiv.className = 'mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
        resultsDiv.innerHTML = '⏳ Scanning PHP files for database connections...';
        resultsDiv.classList.remove('hidden');
    }
    try {
        const response = await fetch('/api/convert_to_centralized_db.php?action=scan&format=json&admin_token=whimsical_admin_2024');
        const result = await response.json();
        if (result.success) {
            if (result.needs_conversion > 0) {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-yellow-50 border border-yellow-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-yellow-800">⚠️ Files Need Conversion</div>
                        <div class="text-xs space-y-1 text-yellow-700">
                            <div>Total PHP files: ${result.total_files}</div>
                            <div>Files needing conversion: ${result.needs_conversion}</div>
                            <div class="">Files with direct PDO connections:</div>
                            <ul class="list-disc list-inside">
                                ${result.files.slice(0, 10).map(f => `<li>${f}</li>`).join('')}
                                ${result.files.length > 10 ? `<li>... and ${result.files.length - 10} more</li>` : ''}
                            </ul>
                        </div>
                    `;
                }
            } else {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-green-50 border border-green-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-green-800">✅ All Files Use Centralized Database!</div>
                        <div class="text-xs text-green-700">Scanned ${result.total_files} PHP files - no conversion needed</div>
                    `;
                }
            }
        } else {
            throw new Error(result.message || 'Scan failed');
        }
    } catch (error) {
        if (resultsDiv) {
            resultsDiv.className = 'mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm';
            resultsDiv.innerHTML = `<div class="text-red-800">❌ Scan failed: ${error.message}</div>`;
        }
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = '📊 Scan Files';
        }
    }
}

async function convertDatabaseConnections(e) {
    const evt = getEvent(e);
    const button = evt?.target || document.querySelector('[data-action="convert-db"], #convertDatabaseConnectionsBtn');
    const resultsDiv = document.getElementById('conversionResults');
    // Use native confirm for now; page also includes enhanced modals elsewhere
    if (!confirm('This will modify files with direct PDO connections and create backups. Continue?')) {
        return;
    }
    if (button) {
        button.disabled = true;
        button.textContent = '🔄 Converting...';
    }
    if (resultsDiv) {
        resultsDiv.className = 'mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
        resultsDiv.innerHTML = '⏳ Converting files to use centralized database connections...';
        resultsDiv.classList.remove('hidden');
    }
    try {
        const response = await fetch('/api/convert_to_centralized_db.php?action=convert&format=json&admin_token=whimsical_admin_2024');
        const result = await response.json();
        if (result.success) {
            if (result.converted > 0) {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-green-50 border border-green-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-green-800">🎉 Conversion Completed!</div>
                        <div class="text-xs space-y-1 text-green-700">
                            <div>Files converted: ${result.converted}</div>
                            <div>Conversion failures: ${result.failed}</div>
                            <div class="">💾 Backups were created for all modified files</div>
                            <div class="text-yellow-700">⚠️ Please test your application to ensure everything works correctly</div>
                        </div>
                        ${result.results.filter(r => r.status === 'converted').length > 0 ? `
                            <details class="">
                                <summary class="cursor-pointer text-green-700 hover:text-green-900">View converted files</summary>
                                <ul class="list-disc list-inside text-xs">
                                    ${result.results.filter(r => r.status === 'converted').map(r => 
                                        `<li>${r.file} (${r.changes} changes)</li>`
                                    ).join('')}
                                </ul>
                            </details>
                        ` : ''}
                    `;
                }
            } else {
                if (resultsDiv) {
                    resultsDiv.className = 'mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
                    resultsDiv.innerHTML = `
                        <div class="font-medium text-blue-800">ℹ️ No Files Needed Conversion</div>
                        <div class="text-xs text-blue-700">All files are already using centralized database connections</div>
                    `;
                }
            }
        } else {
            throw new Error(result.message || 'Conversion failed');
        }
    } catch (error) {
        if (resultsDiv) {
            resultsDiv.className = 'mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm';
            resultsDiv.innerHTML = `<div class="text-red-800">❌ Conversion failed: ${error.message}</div>`;
        }
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = '🔄 Convert All';
        }
    }
}

function openConversionTool() {
    window.open('/api/convert_to_centralized_db.php?admin_token=whimsical_admin_2024', '_blank');
}

function toggleDatabaseBackupTables() {
    const container = document.getElementById('databaseBackupTablesContainer');
    const icon = document.getElementById('databaseBackupToggleIcon');
    if (!container || !icon) return;
    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        icon.textContent = '▼';
    } else {
        container.classList.add('hidden');
        icon.textContent = '▶';
    }
}

async function viewTable(tableName) {
    try {
        const modal = document.getElementById('tableViewModal');
        const title = document.getElementById('tableViewTitle');
        const content = document.getElementById('tableViewContent');
        if (title) title.textContent = `Loading ${tableName}...`;
        if (content) content.innerHTML = '<div class="text-center"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';
        if (typeof window.openModal === 'function') {
            window.openModal('tableViewModal');
        } else if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('show');
        }

        const response = await fetch('/api/db_manager.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'query',
                sql: `SELECT * FROM \`${tableName}\` LIMIT 100`
            })
        });
        const data = await response.json();
        if (data.success && data.data) {
            if (title) title.textContent = `Table: ${tableName} (${data.row_count} records shown, max 100)`;
            if (!Array.isArray(data.data) || data.data.length === 0) {
                if (content) content.innerHTML = '<div class="text-center text-gray-500">Table is empty</div>';
                return;
            }
            const columns = Object.keys(data.data[0]);
            const tableHtml = `
                <div class="overflow-x-auto max-h-96">
                    <table class="min-w-full bg-white border border-gray-200 text-xs">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                ${columns.map(col => `<th class="border-b text-left font-semibold text-gray-700">${col}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${data.data.map(row => `
                                <tr class="hover:bg-gray-50">
                                    ${columns.map(col => {
                                        let value = row[col];
                                        if (value === null) value = '<span class="text-gray-400">NULL</span>';
                                        else if (typeof value === 'string' && value.length > 50) value = value.substring(0, 50) + '...';
                                        return `<td class="border-b">${value}</td>`;
                                    }).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            if (content) content.innerHTML = tableHtml;
        } else {
            if (title) title.textContent = `Error loading ${tableName}`;
            if (content) content.innerHTML = `<div class="text-red-600">Error: ${data.error || 'Failed to load table data'}</div>`;
        }
    } catch (error) {
        console.error('Error viewing table:', error);
        const title = document.getElementById('tableViewTitle');
        const content = document.getElementById('tableViewContent');
        if (title) title.textContent = `Error loading ${tableName}`;
        if (content) content.innerHTML = `<div class="text-red-600">Error: ${error.message}</div>`;
    }
}

function closeTableViewModal() {
    if (typeof window.closeModal === 'function') {
        window.closeModal('tableViewModal');
    } else {
        const modal = document.getElementById('tableViewModal');
        if (modal) { modal.classList.remove('show'); modal.classList.add('hidden'); }
    }
}

async function getDatabaseTableCount() {
    try {
        const response = await fetch('/api/get_database_info.php');
        const result = await response.json();
        if (result.success && result.data) {
            return result.data.total_active || 'several';
        }
        return 'several';
    } catch (error) {
        return 'several';
    }
}

async function compactRepairDatabase() {
    const tableCount = await getDatabaseTableCount();
    const confirmed = await (window.showConfirmationModal ? window.showConfirmationModal({
        title: 'Database Compact & Repair',
        subtitle: 'Optimize and repair your database for better performance',
        message: 'This operation will create a safety backup first, then optimize and repair all database tables to improve performance and fix any corruption issues.',
        details: `
            <ul>
                <li>✅ Create automatic safety backup before optimization</li>
                <li>🔧 Optimize ${tableCount} database tables for better performance</li>
                <li>🛠️ Repair any table corruption or fragmentation issues</li>
                <li>⚡ Improve database speed and efficiency</li>
                <li>⏱️ Process typically takes 2-3 minutes</li>
            </ul>
        `,
        icon: '🔧',
        iconType: 'info',
        confirmText: 'Start Optimization',
        cancelText: 'Cancel'
    }) : Promise.resolve(confirm('Create a safety backup, then optimize and repair all database tables?')));
    if (!confirmed) return;

    if (typeof window.showBackupProgressModal === 'function') {
        window.showBackupProgressModal('🔧 Database Compact & Repair', 'database-repair');
    }
    const progressSteps = document.getElementById('backupProgressSteps');
    const progressTitle = document.getElementById('backupProgressTitle');
    const progressSubtitle = document.getElementById('backupProgressSubtitle');
    if (progressTitle) progressTitle.textContent = '🔧 Database Compact & Repair';
    if (progressSubtitle) progressSubtitle.textContent = 'Optimizing and repairing database tables...';

    try {
        if (progressSteps) {
            progressSteps.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Creating safety backup...</p>
                        <p class="text-xs text-gray-500">Backing up database before optimization</p>
                    </div>
                </div>
            `;
        }
        const backupResponse = await fetch('/api/backup_database.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ destination: 'cloud' })
        });
        const backupResult = await backupResponse.json();
        if (!backupResult.success) {
            throw new Error('Failed to create safety backup: ' + (backupResult.error || 'Unknown error'));
        }

        if (progressSteps) {
            progressSteps.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Safety backup created</p>
                        <p class="text-xs text-gray-500">Database backed up successfully</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Optimizing database tables...</p>
                        <p class="text-xs text-gray-500">Running OPTIMIZE and REPAIR operations</p>
                    </div>
                </div>
            `;
        }
        const repairResponse = await fetch('/api/compact_repair_database.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({})
        });
        const repairResult = await repairResponse.json();
        if (!repairResult.success) {
            throw new Error('Database optimization failed: ' + (repairResult.error || 'Unknown error'));
        }

        if (progressSteps) {
            progressSteps.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Safety backup created</p>
                        <p class="text-xs text-gray-500">Database backed up successfully</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Database optimization complete</p>
                        <p class="text-xs text-gray-500">${repairResult.tables_processed || 0} tables optimized and repaired</p>
                    </div>
                </div>
            `;
        }

        if (typeof window.showBackupCompletionDetails === 'function') {
            window.showBackupCompletionDetails({
                success: true,
                filename: backupResult.filename,
                filepath: backupResult.filepath,
                size: backupResult.size,
                timestamp: backupResult.timestamp,
                destinations: ['Server'],
                tables_optimized: repairResult.tables_processed || 0,
                operation_type: 'Database Compact & Repair'
            });
        }
    } catch (error) {
        console.error('Database optimization error:', error);
        if (typeof window.showError === 'function') {
            window.showError(error.message || 'Database optimization failed');
        } else {
            alert(error.message || 'Database optimization failed');
        }
    }
}

// -----------------------------
// Credentials & SSL Utilities (migrated)
// -----------------------------

function renderResult(div, success, html) {
    if (!div) return;
    const base = 'px-3 py-2 border rounded text-sm';
    if (success) {
        div.className = `${base} bg-green-50 border-green-200`;
    } else {
        div.className = `${base} bg-red-50 border-red-200`;
    }
    div.innerHTML = html;
    div.classList.remove('hidden');
}

async function updateDatabaseConfig(ev) {
    try {
        const resultDiv = document.getElementById('credentialsUpdateResult');
        const button = ev?.target || document.activeElement;

        const updateData = {
            host: document.getElementById('newHost')?.value,
            database: document.getElementById('newDatabase')?.value,
            username: document.getElementById('newUsername')?.value,
            password: document.getElementById('newPassword')?.value,
            environment: document.getElementById('environmentSelect')?.value,
            ssl_enabled: document.getElementById('sslEnabled')?.checked || false,
            ssl_cert: document.getElementById('sslCertPath')?.value || ''
        };

        if (!updateData.host || !updateData.database || !updateData.username) {
            renderResult(resultDiv, false, 'Please fill in all required fields');
            return;
        }

        const confirmAction = async () => {
            if (button) { button.disabled = true; button.textContent = '💾 Updating...'; }
            if (resultDiv) {
                resultDiv.className = 'px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
                resultDiv.innerHTML = '⏳ Updating configuration...';
                resultDiv.classList.remove('hidden');
            }
            try {
                const response = await fetch('/api/database_maintenance.php?action=update_config&admin_token=whimsical_admin_2024', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(updateData)
                });
                const result = await response.json();
                if (result.success) {
                    renderResult(resultDiv, true, `
                        <div class="font-medium text-green-800">✅ Configuration Updated!</div>
                        <div class="text-xs text-green-700">Backup created: ${result.backup_created}</div>
                        <div class="text-xs text-yellow-700">⚠️ Please refresh the page to use new settings</div>
                    `);
                    setTimeout(() => { try { loadCurrentDatabaseConfig(); } catch(_) {} }, 2000);
                } else {
                    renderResult(resultDiv, false, `Update failed: ${result.message}`);
                }
            } catch (error) {
                renderResult(resultDiv, false, `Network error: ${error.message}`);
            } finally {
                if (button) { button.disabled = false; button.textContent = '💾 Update Credentials'; }
            }
        };

        if (typeof window.showConfirmationModal === 'function') {
            window.showConfirmationModal({
                title: 'Update database credentials?',
                message: `A backup will be created automatically for ${updateData.environment} environment(s).`,
                confirmText: 'Yes, Update',
                cancelText: 'Cancel',
                onConfirm: confirmAction
            });
        } else if (confirm(`Are you sure you want to update database credentials for ${updateData.environment} environment(s)? A backup will be created automatically.`)) {
            await confirmAction();
        }
    } catch (err) {
        console.error('[AdminSettings] updateDatabaseConfig error', err);
    }
}

async function testSSLConnection(ev) {
    try {
        const resultDiv = document.getElementById('sslTestResult');
        const button = ev?.target || document.activeElement;

        const sslData = {
            host: document.getElementById('testHost')?.value || document.getElementById('newHost')?.value,
            database: document.getElementById('testDatabase')?.value || document.getElementById('newDatabase')?.value,
            username: document.getElementById('testUsername')?.value || document.getElementById('newUsername')?.value,
            password: document.getElementById('testPassword')?.value || document.getElementById('newPassword')?.value,
            ssl_enabled: true,
            ssl_cert: document.getElementById('sslCertPath')?.value
        };

        if (!sslData.ssl_cert) {
            renderResult(resultDiv, false, 'Please specify SSL certificate path');
            return;
        }

        if (button) { button.disabled = true; button.textContent = '🔄 Testing SSL...'; }
        if (resultDiv) {
            resultDiv.className = 'px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm';
            resultDiv.innerHTML = '⏳ Testing SSL connection...';
            resultDiv.classList.remove('hidden');
        }

        try {
            const response = await fetch('/api/database_maintenance.php?action=test_connection&admin_token=whimsical_admin_2024', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(sslData)
            });
            const result = await response.json();
            if (result.success) {
                renderResult(resultDiv, true, `
                    <div class="font-medium text-green-800">🔒 SSL Connection Successful!</div>
                    <div class="text-xs space-y-1 text-green-700">
                        <div>SSL Certificate: Valid</div>
                        <div>Encryption: Active</div>
                        <div>MySQL Version: ${result.info?.mysql_version || ''}</div>
                    </div>
                `);
            } else {
                renderResult(resultDiv, false, `SSL connection failed: ${result.message}`);
            }
        } catch (error) {
            renderResult(resultDiv, false, `SSL test error: ${error.message}`);
        } finally {
            if (button) { button.disabled = false; button.textContent = '🔒 Test SSL Connection'; }
        }
    } catch (err) {
        console.error('[AdminSettings] testSSLConnection error', err);
    }
}

// Temporary window shims for backward compatibility with inline handlers
if (typeof window !== 'undefined') {
    window.scanDatabaseConnections = scanDatabaseConnections;
    window.convertDatabaseConnections = convertDatabaseConnections;
    window.openConversionTool = openConversionTool;
    window.toggleDatabaseBackupTables = toggleDatabaseBackupTables;
    window.viewTable = viewTable;
    window.closeTableViewModal = closeTableViewModal;
    window.compactRepairDatabase = compactRepairDatabase;
    window.updateDatabaseConfig = updateDatabaseConfig;
    window.testSSLConnection = testSSLConnection;
    // AI settings shims
    window.openAISettingsModal = openAISettingsModal;
    window.closeAISettingsModal = closeAISettingsModal;
    window.loadAIProviders = loadAIProviders;
    window.displayAIProviders = displayAIProviders;
    window.toggleSection = toggleSection;
    window.toggleProviderSections = toggleProviderSections;
    window.saveAISettings = saveAISettings;
    window.showAISettingsSuccess = showAISettingsSuccess;
    window.showAISettingsError = showAISettingsError;
    window.testAIProvider = testAIProvider;
    window.loadAllModels = loadAllModels;
    window.loadAllModelsWithSelection = loadAllModelsWithSelection;
    window.getDefaultAIProvider = getDefaultAIProvider;
    window.loadModelsForCurrentProvider = loadModelsForCurrentProvider;
    window.refreshModels = refreshModels;
    window.populateModelDropdown = populateModelDropdown;
    window.populateModelDropdownWithSelection = populateModelDropdownWithSelection;
    window.loadFallbackModels = loadFallbackModels;
    window.loadFallbackModelsForProvider = loadFallbackModelsForProvider;
    window.loadFallbackModelsWithSelection = loadFallbackModelsWithSelection;
    window.loadFallbackModelsForProviderWithSelection = loadFallbackModelsForProviderWithSelection;
    // Content Tone shims
    window.loadContentToneOptions = loadContentToneOptions;
    window.initializeDefaultContentToneOptions = initializeDefaultContentToneOptions;
    window.loadDefaultContentToneOptions = loadDefaultContentToneOptions;
    window.populateContentToneDropdown = populateContentToneDropdown;
    window.manageContentToneOptions = manageContentToneOptions;
    window.showContentToneModal = showContentToneModal;
    window.closeContentToneModal = closeContentToneModal;
    window.displayContentToneOptions = displayContentToneOptions;
    window.addContentToneOption = addContentToneOption;
    window.updateContentToneOption = updateContentToneOption;
    window.removeContentToneOption = removeContentToneOption;
    window.saveContentToneOptions = saveContentToneOptions;
    window.saveContentToneOption = saveContentToneOption;
    window.deleteContentToneOptionFromDB = deleteContentToneOptionFromDB;
    // Brand Voice shims
    window.loadBrandVoiceOptions = loadBrandVoiceOptions;
    window.initializeDefaultBrandVoiceOptions = initializeDefaultBrandVoiceOptions;
    window.loadDefaultBrandVoiceOptions = loadDefaultBrandVoiceOptions;
    window.populateBrandVoiceDropdown = populateBrandVoiceDropdown;
    window.manageBrandVoiceOptions = manageBrandVoiceOptions;
    window.showBrandVoiceModal = showBrandVoiceModal;
    window.closeBrandVoiceModal = closeBrandVoiceModal;
    window.displayBrandVoiceOptions = displayBrandVoiceOptions;
    window.addBrandVoiceOption = addBrandVoiceOption;
    window.updateBrandVoiceOption = updateBrandVoiceOption;
    window.removeBrandVoiceOption = removeBrandVoiceOption;
    window.saveBrandVoiceOptions = saveBrandVoiceOptions;
    window.saveBrandVoiceOption = saveBrandVoiceOption;
    window.deleteBrandVoiceOptionFromDB = deleteBrandVoiceOptionFromDB;
}

// -----------------------------
// Delegated Listeners (Progressive Migration)
// -----------------------------

let WF_AdminSettingsListenersInitialized = false;

function tagInlineHandlersForMigration(root = document) {
    // Add data-action tags based on existing inline onclick attributes to ease removal later
    try {
        const mappings = [
            { contains: 'scanDatabaseConnections', action: 'scan-db' },
            { contains: 'convertDatabaseConnections', action: 'convert-db' },
            { contains: 'openConversionTool', action: 'open-conversion-tool' },
            { contains: 'compactRepairDatabase', action: 'compact-repair' },
            { contains: 'toggleDatabaseBackupTables', action: 'toggle-backup-tables' },
            { contains: 'closeTableViewModal', action: 'close-table-view' },
            { contains: 'updateDatabaseConfig', action: 'update-db-config' },
            { contains: 'testSSLConnection', action: 'test-ssl' },
            // AI Settings modal & helpers
            { contains: 'closeAISettingsModal', action: 'ai-close-settings' },
            { contains: 'saveAISettings', action: 'ai-save-settings' },
            { contains: 'testAIProvider', action: 'ai-test-provider' },
            { contains: 'refreshModels', action: 'ai-refresh-models' },
            { contains: 'toggleSection', action: 'ai-toggle-section' },
            { contains: 'manageBrandVoiceOptions', action: 'ai-manage-brand-voice' },
            { contains: 'manageContentToneOptions', action: 'ai-manage-content-tone' },
            // Content Tone modal inline handlers
            { contains: 'closeContentToneModal', action: 'content-tone-close' },
            { contains: 'addContentToneOption', action: 'content-tone-add' },
            { contains: 'saveContentToneOptions', action: 'content-tone-save' },
            // Brand Voice modal inline handlers
            { contains: 'closeBrandVoiceModal', action: 'brand-voice-close' },
            { contains: 'addBrandVoiceOption', action: 'brand-voice-add' },
            { contains: 'saveBrandVoiceOptions', action: 'brand-voice-save' }
        ];
        const clickable = root.querySelectorAll('[onclick], [onchange]');
        clickable.forEach(el => {
            const code = (el.getAttribute('onclick') || el.getAttribute('onchange') || '').toString();
            for (const map of mappings) {
                if (code.includes(map.contains)) {
                    if (!el.dataset.action) el.dataset.action = map.action;
                }
            }
            // Special handling: viewTable('<tableName>') -> data-action="view-table" + data-table
            if (code.includes('viewTable(')) {
                if (!el.dataset.action) el.dataset.action = 'view-table';
                try {
                    const m = code.match(/viewTable\((?:'([^']+)'|\"([^\"]+)\"|([^\)]+))\)/);
                    const table = (m && (m[1] || m[2] || m[3] || '')).toString().trim().replace(/^`|`$/g, '').replace(/^\"|\"$/g, '').replace(/^'|'$/g, '');
                    if (table && !el.dataset.table) el.dataset.table = table;
                } catch (_) {}
            }
            // Extract arguments for Content Tone updates/removals
            if (code.includes('updateContentToneOption(')) {
                if (!el.dataset.action) el.dataset.action = 'content-tone-update';
                try {
                    const m = code.match(/updateContentToneOption\((\d+)\s*,\s*'([^']+)'/);
                    if (m) {
                        el.dataset.index = m[1];
                        el.dataset.field = m[2];
                    }
                } catch (_) {}
            }
            if (code.includes('removeContentToneOption(')) {
                if (!el.dataset.action) el.dataset.action = 'content-tone-remove';
                try {
                    const m = code.match(/removeContentToneOption\((\d+)\)/);
                    if (m) el.dataset.index = m[1];
                } catch (_) {}
            }
            // Extract arguments for Brand Voice updates/removals
            if (code.includes('updateBrandVoiceOption(')) {
                if (!el.dataset.action) el.dataset.action = 'brand-voice-update';
                try {
                    const m = code.match(/updateBrandVoiceOption\((\d+)\s*,\s*'([^']+)'/);
                    if (m) {
                        el.dataset.index = m[1];
                        el.dataset.field = m[2];
                    }
                } catch (_) {}
            }
            if (code.includes('removeBrandVoiceOption(')) {
                if (!el.dataset.action) el.dataset.action = 'brand-voice-remove';
                try {
                    const m = code.match(/removeBrandVoiceOption\((\d+)\)/);
                    if (m) el.dataset.index = m[1];
                } catch (_) {}
            }
        });
    } catch (e) {
        console.debug('[AdminSettings] tagInlineHandlersForMigration error', e);
    }
}

function stripInlineHandlersForMigration(root = document) {
    try {
        const selectors = [
            '[onclick*="scanDatabaseConnections"]',
            '[onclick*="convertDatabaseConnections"]',
            '[onclick*="openConversionTool"]',
            '[onclick*="compactRepairDatabase"]',
            '[onclick*="toggleDatabaseBackupTables"]',
            '[onclick*="closeTableViewModal"]',
            '[onclick*="viewTable("]',
            '[onclick*="updateDatabaseConfig"]',
            '[onclick*="testSSLConnection"]',
            // AI Settings & sections
            '[onclick*="closeAISettingsModal"]',
            '[onclick*="saveAISettings"]',
            '[onclick*="testAIProvider"]',
            '[onclick*="refreshModels"]',
            '[onclick*="toggleSection"]',
            // Content Tone modal
            '[onclick*="closeContentToneModal"]',
            '[onclick*="addContentToneOption"]',
            '[onclick*="saveContentToneOptions"]',
            '[onclick*="updateContentToneOption"]',
            '[onclick*="removeContentToneOption"]',
            // Brand Voice modal
            '[onclick*="closeBrandVoiceModal"]',
            '[onclick*="addBrandVoiceOption"]',
            '[onclick*="saveBrandVoiceOptions"]',
            '[onclick*="updateBrandVoiceOption"]',
            '[onclick*="removeBrandVoiceOption"]'
        ];
        root.querySelectorAll(selectors.join(',')).forEach(el => {
            // Preserve original inline handler for debugging/rollback visibility
            if (!el.dataset.onclickLegacy) {
                el.dataset.onclickLegacy = el.getAttribute('onclick') || '';
            }
            el.removeAttribute('onclick');
            el.dataset.migrated = 'true';
        });
    } catch (e) {
        console.debug('[AdminSettings] stripInlineHandlersForMigration error', e);
    }
}

function initAdminSettingsDelegatedListeners() {
    if (WF_AdminSettingsListenersInitialized) return;
    WF_AdminSettingsListenersInitialized = true;

    // Tag existing inline handlers for smoother migration
    const runTagAndStrip = () => { tagInlineHandlersForMigration(); stripInlineHandlersForMigration(); };
    if (document.readyState !== 'loading') {
        runTagAndStrip();
    } else {
        document.addEventListener('DOMContentLoaded', () => runTagAndStrip(), { once: true });
    }
    
    // Initialize SSL option visibility on load
    initSSLHandlers();
    
    // Observe future DOM changes to tag dynamically injected elements
    try {
        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.type === 'childList') {
                    m.addedNodes.forEach(node => {
                        if (node.nodeType === 1) {
                            tagInlineHandlersForMigration(node);
                            stripInlineHandlersForMigration(node);
                            // Re-evaluate SSL option visibility for injected content
                            initSSLHandlers(node);
                        }
                    });
                } else if (m.type === 'attributes' && m.attributeName === 'onclick') {
                    tagInlineHandlersForMigration(m.target);
                    stripInlineHandlersForMigration(m.target);
                }
            }
        });
        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['onclick']
        });
    } catch (err) {
        console.debug('[AdminSettings] MutationObserver unavailable', err);
    }

    // Delegated change handler (SSL checkbox)
    document.addEventListener('change', (e) => {
        const target = e.target;
        if (target && target.matches && target.matches('#sslEnabled')) {
            const sslOptions = document.getElementById('sslOptions');
            if (sslOptions) {
                if (target.checked) sslOptions.classList.remove('hidden');
                else sslOptions.classList.add('hidden');
            }
        }

        // AI Provider radio change -> toggle provider sections
        if (target && target.matches && target.matches('input[name="ai_provider"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.toggleProviderSections === 'function') {
                    window.toggleProviderSections();
                } else if (typeof toggleProviderSections === 'function') {
                    toggleProviderSections();
                }
            } catch (_) {}
        }

        // Content Tone inline input updates
        const ctUpdate = target && target.closest && target.closest('[data-action="content-tone-update"]');
        if (ctUpdate || (target && target.dataset && target.dataset.action === 'content-tone-update')) {
            const el = ctUpdate || target;
            const idx = parseInt(el.dataset.index || '-1', 10);
            const field = el.dataset.field || 'name';
            const val = target.value;
            if (!isNaN(idx)) {
                const fn = (typeof window !== 'undefined' && typeof window.updateContentToneOption === 'function') ? window.updateContentToneOption : (typeof updateContentToneOption === 'function' ? updateContentToneOption : null);
                if (fn) fn(idx, field, val);
            }
        }

        // Brand Voice inline input updates
        const bvUpdate = target && target.closest && target.closest('[data-action="brand-voice-update"]');
        if (bvUpdate || (target && target.dataset && target.dataset.action === 'brand-voice-update')) {
            const el = bvUpdate || target;
            const idx = parseInt(el.dataset.index || '-1', 10);
            const field = el.dataset.field || 'name';
            const val = target.value;
            if (!isNaN(idx)) {
                const fn = (typeof window !== 'undefined' && typeof window.updateBrandVoiceOption === 'function') ? window.updateBrandVoiceOption : (typeof updateBrandVoiceOption === 'function' ? updateBrandVoiceOption : null);
                if (fn) fn(idx, field, val);
            }
        }
    }, true);

    // Delegated click handler
    document.addEventListener('click', (e) => {
        const target = e.target;

        // Helper to match closest element
        const closest = (sel) => target.closest(sel);
        const invokeCallback = (cb) => {
            try {
                if (!cb) return;
                if (typeof window !== 'undefined' && typeof cb === 'string' && typeof window[cb] === 'function') {
                    window[cb]();
                    return;
                }
                if (typeof cb === 'string') {
                    const f = new Function(`return (${cb});`);
                    const fn = f();
                    if (typeof fn === 'function') fn();
                }
            } catch (err) {
                console.warn('[AdminSettings] modal callback failed', err);
            }
        };

        // Scan Files
        if (closest('[data-action="scan-db"]')) {
            e.preventDefault();
            scanDatabaseConnections(e);
            return;
        }

        // Convert All
        if (closest('[data-action="convert-db"]')) {
            e.preventDefault();
            convertDatabaseConnections(e);
            return;
        }

        // Open Conversion Tool
        if (closest('[data-action="open-conversion-tool"]')) {
            e.preventDefault();
            openConversionTool();
            return;
        }

        // Compact & Repair
        if (closest('[data-action="compact-repair"]')) {
            e.preventDefault();
            compactRepairDatabase();
            return;
        }

        // Toggle Backup Tables
        if (closest('[data-action="toggle-backup-tables"]')) {
            e.preventDefault();
            toggleDatabaseBackupTables();
            return;
        }

        // Close Table Viewer
        if (closest('[data-action="close-table-view"]')) {
            e.preventDefault();
            closeTableViewModal();
            return;
        }

        // View Table (needs argument)
        const viewBtn = closest('[data-action="view-table"]');
        if (viewBtn) {
            e.preventDefault();
            let tableName = viewBtn.dataset.table || viewBtn.dataset.tableName;
            if (!tableName && viewBtn.dataset.onclickLegacy) {
                try {
                    const m = viewBtn.dataset.onclickLegacy.match(/viewTable\((?:'([^']+)'|\"([^\"]+)\"|([^\)]+))\)/);
                    tableName = (m && (m[1] || m[2] || m[3] || '')).toString().trim();
                } catch(_) {}
            }
            if (tableName) {
                viewTable(tableName);
            } else {
                console.warn('[AdminSettings] view-table clicked but no table name found');
            }
            return;
        }

        // Update DB Credentials
        if (closest('[data-action="update-db-config"]')) {
            e.preventDefault();
            updateDatabaseConfig(e);
            return;
        }

        // Test SSL Connection
        if (closest('[data-action="test-ssl"]')) {
            e.preventDefault();
            testSSLConnection(e);
            return;
        }

        // Perform Export
        if (closest('[data-action="perform-export"]')) {
            e.preventDefault();
            if (typeof window !== 'undefined' && typeof window.performExport === 'function') {
                window.performExport();
            } else if (typeof performExport === 'function') {
                performExport();
            } else {
                console.warn('[AdminSettings] performExport not found');
            }
            return;
        }

        // Import SQL
        if (closest('[data-action="import-sql"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.importSQLFile === 'function') ? window.importSQLFile : (typeof importSQLFile === 'function' ? importSQLFile : null);
            if (fn) fn(); else console.warn('[AdminSettings] importSQLFile not found');
            return;
        }

        // Import CSV
        if (closest('[data-action="import-csv"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.importCSVFile === 'function') ? window.importCSVFile : (typeof importCSVFile === 'function' ? importCSVFile : null);
            if (fn) fn(); else console.warn('[AdminSettings] importCSVFile not found');
            return;
        }

        // Import JSON
        if (closest('[data-action="import-json"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.importJSONFile === 'function') ? window.importJSONFile : (typeof importJSONFile === 'function' ? importJSONFile : null);
            if (fn) fn(); else console.warn('[AdminSettings] importJSONFile not found');
            return;
        }

        // Close Admin Modal (icon or footer button)
        const closeBtn = closest('[data-action="close-admin-modal"]');
        if (closeBtn) {
            e.preventDefault();
            const overlay = closeBtn.closest('.admin-modal-overlay') || closeBtn.closest('.admin-modal');
            if (overlay) overlay.remove();
            return;
        }

        // Modal Cancel with optional callback
        const cancelBtn = closest('[data-action="modal-cancel"]');
        if (cancelBtn) {
            e.preventDefault();
            const overlay = cancelBtn.closest('.admin-modal-overlay') || cancelBtn.closest('.admin-modal');
            if (overlay) overlay.remove();
            const cb = cancelBtn.dataset.callback;
            if (cb) invokeCallback(cb);
            return;
        }

        // Modal Confirm with callback
        const confirmBtn = closest('[data-action="modal-confirm"]');
        if (confirmBtn) {
            e.preventDefault();
            const overlay = confirmBtn.closest('.admin-modal-overlay') || confirmBtn.closest('.admin-modal');
            if (overlay) overlay.remove();
            const cb = confirmBtn.dataset.callback;
            if (cb) invokeCallback(cb);
            return;
        }

        // Maintenance confirm modal actions
        const maintCancel = closest('[data-action="maintenance-cancel"]');
        if (maintCancel) {
            e.preventDefault();
            const overlay = maintCancel.closest('.admin-modal-overlay');
            if (overlay) overlay.remove();
            try { if (typeof window !== 'undefined' && typeof window.maintenanceConfirmResolve === 'function') window.maintenanceConfirmResolve(false); } catch (_) {}
            return;
        }

        const maintContinue = closest('[data-action="maintenance-continue"]');
        if (maintContinue) {
            e.preventDefault();
            const overlay = maintContinue.closest('.admin-modal-overlay');
            if (overlay) overlay.remove();
            try { if (typeof window !== 'undefined' && typeof window.maintenanceConfirmResolve === 'function') window.maintenanceConfirmResolve(true); } catch (_) {}
            return;
        }

        // Click on overlay background to close (only when clicking the overlay itself)
        if (target && target.matches && target.matches('[data-action="overlay-close"]')) {
            // Only close if the click target IS the overlay, not inner content
            if (e.target === target) {
                const id = target.id || '';
                try {
                    if (id === 'roomCategoryManagerModal' && typeof window.closeRoomCategoryManagerModal === 'function') {
                        window.closeRoomCategoryManagerModal();
                    } else if (id === 'backgroundManagerModal' && typeof window.closeBackgroundManagerModal === 'function') {
                        window.closeBackgroundManagerModal();
                    } else if (id === 'aiSettingsModal' && typeof window.closeAISettingsModal === 'function') {
                        window.closeAISettingsModal();
                    } else {
                        target.remove();
                    }
                } catch (_) { target.remove(); }
            }
            return;
        }

        // Open Room-Category Manager for a specific room
        const openRCM = closest('[data-action="open-room-category-manager"]');
        if (openRCM) {
            e.preventDefault();
            const room = openRCM.dataset.room ? (isNaN(openRCM.dataset.room) ? openRCM.dataset.room : parseInt(openRCM.dataset.room, 10)) : null;
            if (typeof window.openRoomCategoryManagerModal === 'function') {
                window.openRoomCategoryManagerModal(room);
            } else if (typeof openRoomCategoryManagerModal === 'function') {
                openRoomCategoryManagerModal(room);
            }
            return;
        }

        // Add Room Category
        if (closest('[data-action="add-room-category"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.addRoomCategory === 'function') ? window.addRoomCategory : (typeof addRoomCategory === 'function' ? addRoomCategory : null);
            if (fn) fn(); else console.warn('[AdminSettings] addRoomCategory not found');
            return;
        }

        // Set Primary Category
        const setPrimaryBtn = closest('[data-action="set-primary-category"]');
        if (setPrimaryBtn) {
            e.preventDefault();
            const room = setPrimaryBtn.dataset.room;
            const catId = setPrimaryBtn.dataset.categoryId || setPrimaryBtn.dataset.categoryid;
            const fn = (typeof window !== 'undefined' && typeof window.setPrimaryCategory === 'function') ? window.setPrimaryCategory : (typeof setPrimaryCategory === 'function' ? setPrimaryCategory : null);
            if (fn && room != null && catId != null) {
                fn(isNaN(room) ? room : parseInt(room, 10), parseInt(catId, 10));
            } else {
                console.warn('[AdminSettings] setPrimaryCategory missing args or not found');
            }
            return;
        }

        // Remove Room Category
        const removeRCBtn = closest('[data-action="remove-room-category"]');
        if (removeRCBtn) {
            e.preventDefault();
            const id = removeRCBtn.dataset.assignmentId || removeRCBtn.dataset.assignmentid;
            const fn = (typeof window !== 'undefined' && typeof window.removeRoomCategory === 'function') ? window.removeRoomCategory : (typeof removeRoomCategory === 'function' ? removeRoomCategory : null);
            if (fn && id != null) fn(parseInt(id, 10)); else console.warn('[AdminSettings] removeRoomCategory not found');
            return;
        }

        // Upload Background
        if (closest('[data-action="upload-background"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.uploadBackground === 'function') ? window.uploadBackground : (typeof uploadBackground === 'function' ? uploadBackground : null);
            if (fn) fn(); else console.warn('[AdminSettings] uploadBackground not found');
            return;
        }

        // Apply Background
        const applyBgBtn = closest('[data-action="apply-background"]');
        if (applyBgBtn) {
            e.preventDefault();
            const room = applyBgBtn.dataset.room;
            const bgId = applyBgBtn.dataset.backgroundId || applyBgBtn.dataset.backgroundid;
            const fn = (typeof window !== 'undefined' && typeof window.applyBackground === 'function') ? window.applyBackground : (typeof applyBackground === 'function' ? applyBackground : null);
            if (fn && room != null && bgId != null) fn(room, parseInt(bgId, 10)); else console.warn('[AdminSettings] applyBackground missing args or not found');
            return;
        }

        // Delete Background
        const delBgBtn = closest('[data-action="delete-background"]');
        if (delBgBtn) {
            e.preventDefault();
            const id = delBgBtn.dataset.backgroundId || delBgBtn.dataset.backgroundid;
            const name = delBgBtn.dataset.backgroundName || delBgBtn.dataset.backgroundname || '';
            const fn = (typeof window !== 'undefined' && typeof window.deleteBackground === 'function') ? window.deleteBackground : (typeof deleteBackground === 'function' ? deleteBackground : null);
            if (fn && id != null) fn(parseInt(id, 10), name); else console.warn('[AdminSettings] deleteBackground missing args or not found');
            return;
        }

        // Preview Background
        const previewBtn = closest('[data-action="preview-background"]');
        if (previewBtn) {
            e.preventDefault();
            const url = previewBtn.dataset.imageUrl || previewBtn.dataset.imageurl;
            const name = previewBtn.dataset.backgroundName || previewBtn.dataset.backgroundname || '';
            const fn = (typeof window !== 'undefined' && typeof window.previewBackground === 'function') ? window.previewBackground : (typeof previewBackground === 'function' ? previewBackground : null);
            if (fn && url) fn(url, name); else console.warn('[AdminSettings] previewBackground missing args or not found');
            return;
        }

        // Close Preview Overlay
        const closePreview = closest('[data-action="close-preview"]');
        if (closePreview) {
            e.preventDefault();
            const overlay = closePreview.closest('.fixed') || closePreview.closest('.admin-modal-overlay');
            if (overlay) overlay.remove();
            return;
        }

        // -----------------------------
        // Map History actions
        // -----------------------------
        const mapRestoreBtn = closest('[data-action="map-restore"]');
        if (mapRestoreBtn) {
            e.preventDefault();
            const id = parseInt(mapRestoreBtn.dataset.mapId || mapRestoreBtn.dataset.mapid || '-1', 10);
            const nameEnc = mapRestoreBtn.dataset.mapName || mapRestoreBtn.dataset.mapname || '';
            const name = (() => { try { return decodeURIComponent(nameEnc); } catch (_) { return nameEnc; } })();
            const apply = String(mapRestoreBtn.dataset.apply || '').toLowerCase() === 'true';
            const fn = (typeof window !== 'undefined' && typeof window.restoreMap === 'function') ? window.restoreMap : (typeof restoreMap === 'function' ? restoreMap : null);
            if (fn && id >= 0) fn(id, name, apply); else console.warn('[AdminSettings] restoreMap not found or bad args');
            return;
        }

        const mapPreviewBtn = closest('[data-action="map-preview"]');
        if (mapPreviewBtn) {
            e.preventDefault();
            const id = parseInt(mapPreviewBtn.dataset.mapId || mapPreviewBtn.dataset.mapid || '-1', 10);
            const nameEnc = mapPreviewBtn.dataset.mapName || mapPreviewBtn.dataset.mapname || '';
            const name = (() => { try { return decodeURIComponent(nameEnc); } catch (_) { return nameEnc; } })();
            const fn = (typeof window !== 'undefined' && typeof window.previewHistoricalMap === 'function') ? window.previewHistoricalMap : (typeof previewHistoricalMap === 'function' ? previewHistoricalMap : null);
            if (fn && id >= 0) fn(id, name); else console.warn('[AdminSettings] previewHistoricalMap not found or bad args');
            return;
        }

        const mapDeleteBtn = closest('[data-action="map-delete"]');
        if (mapDeleteBtn) {
            e.preventDefault();
            const id = parseInt(mapDeleteBtn.dataset.mapId || mapDeleteBtn.dataset.mapid || '-1', 10);
            const nameEnc = mapDeleteBtn.dataset.mapName || mapDeleteBtn.dataset.mapname || '';
            const name = (() => { try { return decodeURIComponent(nameEnc); } catch (_) { return nameEnc; } })();
            const fn = (typeof window !== 'undefined' && typeof window.deleteHistoricalMap === 'function') ? window.deleteHistoricalMap : (typeof deleteHistoricalMap === 'function' ? deleteHistoricalMap : null);
            if (fn && id >= 0) fn(id, name); else console.warn('[AdminSettings] deleteHistoricalMap not found or bad args');
            return;
        }

        // -----------------------------
        // Email History and Email actions
        // -----------------------------
        const emailViewBtn = closest('[data-action="email-view"]');
        if (emailViewBtn) {
            e.preventDefault();
            const id = parseInt(emailViewBtn.dataset.emailId || emailViewBtn.dataset.emailid || '-1', 10);
            const fn = (typeof window !== 'undefined' && typeof window.viewEmailDetails === 'function') ? window.viewEmailDetails : (typeof viewEmailDetails === 'function' ? viewEmailDetails : null);
            if (fn && id >= 0) fn(id); else console.warn('[AdminSettings] viewEmailDetails not found or bad id');
            return;
        }

        const emailEditBtn = closest('[data-action="email-edit-resend"]');
        if (emailEditBtn) {
            e.preventDefault();
            const id = parseInt(emailEditBtn.dataset.emailId || emailEditBtn.dataset.emailid || '-1', 10);
            const fn = (typeof window !== 'undefined' && typeof window.editAndResendEmail === 'function') ? window.editAndResendEmail : (typeof editAndResendEmail === 'function' ? editAndResendEmail : null);
            if (fn && id >= 0) fn(id); else console.warn('[AdminSettings] editAndResendEmail not found or bad id');
            return;
        }

        if (closest('[data-action="email-history-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailHistoryModal === 'function') {
                    window.closeEmailHistoryModal();
                } else {
                    const m = document.getElementById('emailHistoryModal') || (document.querySelector('#emailHistoryModal'));
                    if (m) m.remove();
                }
            } catch (_) {}
            return;
        }

        if (closest('[data-action="email-history-filter"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.loadEmailHistory === 'function') ? window.loadEmailHistory : (typeof loadEmailHistory === 'function' ? loadEmailHistory : null);
            if (fn) fn(); else console.warn('[AdminSettings] loadEmailHistory not found');
            return;
        }

        const emailPageBtn = closest('[data-action="email-history-page"]');
        if (emailPageBtn) {
            e.preventDefault();
            const dir = emailPageBtn.dataset.direction || '';
            const fn = (typeof window !== 'undefined' && typeof window.loadEmailHistoryPage === 'function') ? window.loadEmailHistoryPage : (typeof loadEmailHistoryPage === 'function' ? loadEmailHistoryPage : null);
            if (fn && dir) fn(dir); else console.warn('[AdminSettings] loadEmailHistoryPage not found or dir missing');
            return;
        }

        if (closest('[data-action="email-edit-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailEditModal === 'function') {
                    window.closeEmailEditModal();
                } else {
                    const m = document.getElementById('emailEditModal'); if (m) m.remove();
                }
            } catch (_) {}
            return;
        }

        const dismissBtn = closest('[data-action="dismiss-notification"]');
        if (dismissBtn) {
            e.preventDefault();
            const box = dismissBtn.closest('.bg-green-100') || dismissBtn.closest('.shadow-lg') || (dismissBtn.parentElement && dismissBtn.parentElement.parentElement) || dismissBtn.closest('.admin-modal-overlay');
            if (box && box.remove) box.remove();
            return;
        }

        if (closest('[data-action="email-config-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailConfigModal === 'function') {
                    window.closeEmailConfigModal();
                } else {
                    const m = document.getElementById('emailConfigModal'); if (m) m.remove();
                }
            } catch (_) {}
            return;
        }

        if (closest('[data-action="email-send-test"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.sendTestEmail === 'function') ? window.sendTestEmail : (typeof sendTestEmail === 'function' ? sendTestEmail : null);
            if (fn) fn(); else console.warn('[AdminSettings] sendTestEmail not found');
            return;
        }

        if (closest('[data-action="template-manager-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeTemplateManagerModal === 'function') {
                    window.closeTemplateManagerModal();
                } else {
                    const overlay = document.getElementById('templateManagerModal') || (document.querySelector('#templateManagerModal')) || (document.querySelector('#emailTemplateManagerModal'));
                    const m = overlay || (document.querySelector('#templateManagerModal .admin-modal-overlay')) || (document.querySelector('.admin-modal-overlay'));
                    if (m) m.remove();
                }
            } catch (_) {}
            return;
        }

        if (closest('[data-action="email-template-edit-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailTemplateEditModal === 'function') {
                    window.closeEmailTemplateEditModal();
                } else {
                    const m = document.getElementById('emailTemplateEditModal'); if (m) m.remove();
                }
            } catch (_) {}
            return;
        }

        if (closest('[data-action="email-template-save"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.saveEmailTemplate === 'function') ? window.saveEmailTemplate : (typeof saveEmailTemplate === 'function' ? saveEmailTemplate : null);
            if (fn) fn(); else console.warn('[AdminSettings] saveEmailTemplate not found');
            return;
        }

        if (closest('[data-action="email-template-preview-close"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeEmailTemplatePreviewModal === 'function') {
                    window.closeEmailTemplatePreviewModal();
                } else {
                    const m = document.getElementById('emailTemplatePreviewModal'); if (m) m.remove();
                }
            } catch (_) {}
            return;
        }

        // -----------------------------
        // AI Settings Modal actions
        // -----------------------------
        if (closest('[data-action="ai-close-settings"]')) {
            e.preventDefault();
            try {
                if (typeof window !== 'undefined' && typeof window.closeAISettingsModal === 'function') {
                    window.closeAISettingsModal();
                } else {
                    const overlay = document.getElementById('aiSettingsModal');
                    if (overlay) overlay.remove();
                }
            } catch (_) {}
            return;
        }

        if (closest('[data-action="ai-save-settings"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.saveAISettings === 'function') ? window.saveAISettings : (typeof saveAISettings === 'function' ? saveAISettings : null);
            if (fn) fn(); else console.warn('[AdminSettings] saveAISettings not found');
            return;
        }

        if (closest('[data-action="ai-test-provider"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.testAIProvider === 'function') ? window.testAIProvider : (typeof testAIProvider === 'function' ? testAIProvider : null);
            if (fn) fn(); else console.warn('[AdminSettings] testAIProvider not found');
            return;
        }

        const aiToggle = closest('[data-action="ai-toggle-section"]');
        if (aiToggle) {
            e.preventDefault();
            const section = aiToggle.dataset.section;
            try {
                if (typeof window !== 'undefined' && typeof window.toggleSection === 'function') {
                    window.toggleSection(section);
                } else if (section) {
                    // Generic toggle fallback
                    const content = document.getElementById(`${section}-content`);
                    const icon = document.getElementById(`${section}-icon`);
                    if (content) content.classList.toggle('hidden');
                    if (icon) icon.textContent = content && !content.classList.contains('hidden') ? '▼' : '▶';
                }
            } catch (_) {}
            return;
        }

        const refreshBtn = closest('[data-action="ai-refresh-models"]');
        if (refreshBtn) {
            e.preventDefault();
            const provider = refreshBtn.dataset.provider;
            const fn = (typeof window !== 'undefined' && typeof window.refreshModels === 'function') ? window.refreshModels : (typeof refreshModels === 'function' ? refreshModels : null);
            if (fn && provider) fn(provider); else console.warn('[AdminSettings] refreshModels not found or provider missing');
            return;
        }

        if (closest('[data-action="ai-manage-brand-voice"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.manageBrandVoiceOptions === 'function') ? window.manageBrandVoiceOptions : (typeof manageBrandVoiceOptions === 'function' ? manageBrandVoiceOptions : null);
            if (fn) fn(); else console.warn('[AdminSettings] manageBrandVoiceOptions not found');
            return;
        }

        if (closest('[data-action="ai-manage-content-tone"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.manageContentToneOptions === 'function') ? window.manageContentToneOptions : (typeof manageContentToneOptions === 'function' ? manageContentToneOptions : null);
            if (fn) fn(); else console.warn('[AdminSettings] manageContentToneOptions not found');
            return;
        }

        // -----------------------------
        // Content Tone modal actions
        // -----------------------------
        if (closest('[data-action="content-tone-close"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.closeContentToneModal === 'function') ? window.closeContentToneModal : (typeof closeContentToneModal === 'function' ? closeContentToneModal : null);
            if (fn) fn();
            else {
                const m = document.getElementById('contentToneModal'); if (m) m.remove();
            }
            return;
        }
        if (closest('[data-action="content-tone-add"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.addContentToneOption === 'function') ? window.addContentToneOption : (typeof addContentToneOption === 'function' ? addContentToneOption : null);
            if (fn) fn(); else console.warn('[AdminSettings] addContentToneOption not found');
            return;
        }
        if (closest('[data-action="content-tone-save"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.saveContentToneOptions === 'function') ? window.saveContentToneOptions : (typeof saveContentToneOptions === 'function' ? saveContentToneOptions : null);
            if (fn) fn(); else console.warn('[AdminSettings] saveContentToneOptions not found');
            return;
        }
        const ctRemove = closest('[data-action="content-tone-remove"]');
        if (ctRemove) {
            e.preventDefault();
            const idx = parseInt(ctRemove.dataset.index || '-1', 10);
            const fn = (typeof window !== 'undefined' && typeof window.removeContentToneOption === 'function') ? window.removeContentToneOption : (typeof removeContentToneOption === 'function' ? removeContentToneOption : null);
            if (fn && !isNaN(idx)) fn(idx); else console.warn('[AdminSettings] removeContentToneOption missing args or not found');
            return;
        }

        // -----------------------------
        // Brand Voice modal actions
        // -----------------------------
        if (closest('[data-action="brand-voice-close"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.closeBrandVoiceModal === 'function') ? window.closeBrandVoiceModal : (typeof closeBrandVoiceModal === 'function' ? closeBrandVoiceModal : null);
            if (fn) fn();
            else {
                const m = document.getElementById('brandVoiceModal'); if (m) m.remove();
            }
            return;
        }
        if (closest('[data-action="brand-voice-add"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.addBrandVoiceOption === 'function') ? window.addBrandVoiceOption : (typeof addBrandVoiceOption === 'function' ? addBrandVoiceOption : null);
            if (fn) fn(); else console.warn('[AdminSettings] addBrandVoiceOption not found');
            return;
        }
        if (closest('[data-action="brand-voice-save"]')) {
            e.preventDefault();
            const fn = (typeof window !== 'undefined' && typeof window.saveBrandVoiceOptions === 'function') ? window.saveBrandVoiceOptions : (typeof saveBrandVoiceOptions === 'function' ? saveBrandVoiceOptions : null);
            if (fn) fn(); else console.warn('[AdminSettings] saveBrandVoiceOptions not found');
            return;
        }
        const bvRemove = closest('[data-action="brand-voice-remove"]');
        if (bvRemove) {
            e.preventDefault();
            const idx = parseInt(bvRemove.dataset.index || '-1', 10);
            const fn = (typeof window !== 'undefined' && typeof window.removeBrandVoiceOption === 'function') ? window.removeBrandVoiceOption : (typeof removeBrandVoiceOption === 'function' ? removeBrandVoiceOption : null);
            if (fn && !isNaN(idx)) fn(idx); else console.warn('[AdminSettings] removeBrandVoiceOption missing args or not found');
            return;
        }
    }, true);

    // Live updates:
    // 1) numeric range displays via data-value-target (textContent)
    // 2) mirrored inputs via data-target-id (sync value without recursion)
    document.addEventListener('input', (e) => {
        const t = e.target;
        if (!t || !t.getAttribute) return;

        // 1) Update text content targets
        const valueTargetId = t.getAttribute('data-value-target');
        if (valueTargetId) {
            const out = document.getElementById(valueTargetId);
            if (out) out.textContent = t.value;
        }

        // 2) Mirror value to paired control
        const mirrorTargetId = t.getAttribute('data-target-id');
        if (mirrorTargetId) {
            const dest = document.getElementById(mirrorTargetId);
            if (dest && 'value' in dest) {
                if (dest.value !== t.value) {
                    // Prevent feedback loop by marking the destination during sync
                    if (!window.WF_InputMirrorSync) window.WF_InputMirrorSync = new WeakSet();
                    if (window.WF_InputMirrorSync.has(t)) return;
                    window.WF_InputMirrorSync.add(dest);
                    try { dest.value = t.value; } catch (_) {}
                    try { dest.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {}
                    window.WF_InputMirrorSync.delete(dest);
                }
            } else if (dest) {
                dest.textContent = t.value;
            }
        }

        // 3) Handle option editors in Content Tone and Brand Voice modals
        const action = t.getAttribute('data-action');
        if (action === 'content-tone-change') {
            const idx = parseInt(t.getAttribute('data-index') || '-1', 10);
            const field = t.getAttribute('data-field');
            const val = t.value;
            const fn = (typeof window !== 'undefined' && typeof window.updateContentToneOption === 'function') ? window.updateContentToneOption : (typeof updateContentToneOption === 'function' ? updateContentToneOption : null);
            if (fn && !isNaN(idx) && field) fn(idx, field, val);
        } else if (action === 'brand-voice-change') {
            const idx = parseInt(t.getAttribute('data-index') || '-1', 10);
            const field = t.getAttribute('data-field');
            const val = t.value;
            const fn = (typeof window !== 'undefined' && typeof window.updateBrandVoiceOption === 'function') ? window.updateBrandVoiceOption : (typeof updateBrandVoiceOption === 'function' ? updateBrandVoiceOption : null);
            if (fn && !isNaN(idx) && field) fn(idx, field, val);
        }
    }, true);

    // Also respond to 'change' to mimic previous onchange behavior
    document.addEventListener('change', (e) => {
        const t = e.target;
        if (!t || !t.getAttribute) return;
        const action = t.getAttribute('data-action');
        if (action === 'content-tone-change') {
            const idx = parseInt(t.getAttribute('data-index') || '-1', 10);
            const field = t.getAttribute('data-field');
            const val = t.value;
            const fn = (typeof window !== 'undefined' && typeof window.updateContentToneOption === 'function') ? window.updateContentToneOption : (typeof updateContentToneOption === 'function' ? updateContentToneOption : null);
            if (fn && !isNaN(idx) && field) fn(idx, field, val);
        } else if (action === 'brand-voice-change') {
            const idx = parseInt(t.getAttribute('data-index') || '-1', 10);
            const field = t.getAttribute('data-field');
            const val = t.value;
            const fn = (typeof window !== 'undefined' && typeof window.updateBrandVoiceOption === 'function') ? window.updateBrandVoiceOption : (typeof updateBrandVoiceOption === 'function' ? updateBrandVoiceOption : null);
            if (fn && !isNaN(idx) && field) fn(idx, field, val);
        }
    }, true);
}

// Initialize listeners ASAP
if (typeof window !== 'undefined') {
    if (document.readyState !== 'loading') {
        initAdminSettingsDelegatedListeners();
    } else {
        document.addEventListener('DOMContentLoaded', () => initAdminSettingsDelegatedListeners(), { once: true });
    }
}

// Helper to initialize SSL checkbox-driven visibility
function initSSLHandlers(root = document) {
    try {
        const sslCheckbox = root.querySelector ? root.querySelector('#sslEnabled') : null;
        const sslOptions = root.querySelector ? root.querySelector('#sslOptions') : null;
        if (sslCheckbox && sslOptions) {
            sslOptions.classList.toggle('hidden', !sslCheckbox.checked);
        }
    } catch (_) {}
}

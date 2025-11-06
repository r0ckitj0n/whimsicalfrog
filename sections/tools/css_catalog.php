<?php
// CSS Catalog: lists all CSS classes from reports/css-classes.json in an admin-friendly UI.
// Supports modal context via ?modal=1 (no header/footer) or full page with shared layout.

$isModal = isset($_GET['modal']) && $_GET['modal'] == '1';
$root = dirname(__DIR__, 2);
$reportPath = $root . '/reports/css-classes.json';
$classes = [];
$generatedAt = '';
$error = '';
$sources = [];
if (file_exists($reportPath)) {
    $json = @file_get_contents($reportPath);
    if ($json !== false) {
        $data = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['allClasses'])) {
            $classes = $data['allClasses'];
            $generatedAt = $data['generatedAt'] ?? '';
            if (isset($data['sources']) && is_array($data['sources'])) {
                $sources = $data['sources'];
            }
        } else {
            $error = 'Failed to parse css-classes.json';
        }
    } else {
        $error = 'Unable to read css-classes.json';
    }
} else {
    $error = 'Report not found. Run the extractor script to generate reports/css-classes.json.';
}

// Group by prefix (segment before first '-' or '_')
$groups = [];
foreach ($classes as $cls) {
    $name = ltrim($cls, '.');
    if ($name === '') continue;
    if (strpos($name, '-') !== false) {
        $prefix = strstr($name, '-', true);
    } elseif (strpos($name, '_') !== false) {
        $prefix = strstr($name, '_', true);
    } else {
        $prefix = $name;
    }
    $groups[$prefix][] = $cls;
}
ksort($groups);

// Build group-by-file and usage counts (how many files reference each class)
$groupsByFile = [];
$usageCounts = [];
if (!empty($sources)) {
    foreach ($sources as $src) {
        $file = isset($src['file']) ? (string)$src['file'] : '';
        $clist = isset($src['classes']) && is_array($src['classes']) ? $src['classes'] : [];
        if ($file === '' || empty($clist)) continue;
        foreach ($clist as $c) {
            // Normalize to class token including leading dot
            $token = (strpos($c, '.') === 0) ? $c : ('.' . ltrim($c, '.'));
            $groupsByFile[$file][] = $token;
            $usageCounts[$token] = isset($usageCounts[$token]) ? ($usageCounts[$token] + 1) : 1;
        }
    }
    ksort($groupsByFile);
}

if (!$isModal) {
    // Full layout
    if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
        $page = 'admin';
        include $root . '/partials/header.php';
        if (!function_exists('__wf_css_catalog_footer_shutdown')) {
            function __wf_css_catalog_footer_shutdown() { @include __DIR__ . '/../../partials/footer.php'; }
        }
        register_shutdown_function('__wf_css_catalog_footer_shutdown');
    }
    // Admin tabs
    $section = 'settings';
    include_once $root . '/components/admin_nav_tabs.php';
} else {
    // Modal context: minimal header for Vite dev/prod assets
    include $root . '/partials/modal_header.php';
}
?>
<?php if (!$isModal): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>

<div class="container mx-auto p-4 bg-white">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">CSS Class Catalog</h1>
    <div class="text-sm text-gray-600">Generated: <?= htmlspecialchars($generatedAt ?: 'n/a') ?></div>
  </div>
  <div class="mb-3">
    <div class="inline-flex gap-2">
      <button type="button" id="tabCatalog" class="btn btn-secondary tab-outline" data-tab="catalog" aria-selected="true">Catalog</button>
      <button type="button" id="tabEditable" class="btn tab-outline" data-tab="editable" aria-selected="false">Editable Settings</button>
    </div>
  </div>
  <div class="mb-3 grid gap-2 md:grid-cols-2">
    <input id="cssSearch" type="search" class="form-input w-full" placeholder="Filter classes (e.g., modal, btn, admin-)" />
    <div class="flex items-center justify-end gap-2">
      <label class="text-sm inline-flex items-center gap-2"><input id="toggleWithTooltips" type="checkbox" /> <span>Show only with tooltips</span></label>
      <label class="text-sm inline-flex items-center gap-2"><input type="radio" name="groupMode" id="groupByPrefix" checked /> <span>Group by prefix</span></label>
      <label class="text-sm inline-flex items-center gap-2"><input type="radio" name="groupMode" id="groupByFile" /> <span>Group by file</span></label>
      <button id="expandAll" type="button" class="btn btn-secondary">Expand all</button>
      <button id="collapseAll" type="button" class="btn btn-secondary">Collapse all</button>
    </div>
  </div>
  
  <div id="panelCatalog">
  <?php if ($error): ?>
    <div class="admin-alert alert-warning"><?= htmlspecialchars($error) ?></div>
  <?php else: ?>
    <div id="cssGroups" class="space-y-4" data-mode="prefix">
      <?php foreach ($groups as $prefix => $list): ?>
        <div class="border rounded" data-group="<?= htmlspecialchars($prefix) ?>">
          <div class="px-3 py-2 font-semibold bg-gray-50 flex items-center justify-between">
            <div>Group: <?= htmlspecialchars($prefix) ?> <span class="text-xs text-gray-500">(<?= count($list) ?>)</span></div>
            <div class="text-xs">
              <button type="button" class="btn btn-secondary btn-xs" data-action="group-toggle">Toggle</button>
            </div>
          </div>
          <ul class="p-3 grid gap-1 md:grid-cols-2 lg:grid-cols-3">
            <?php sort($list); foreach ($list as $cls): ?>
              <li class="text-sm flex items-center justify-between gap-2 py-1 px-2 rounded hover:bg-gray-50" data-class="<?= htmlspecialchars($cls) ?>">
                <div class="truncate"><code><?= htmlspecialchars($cls) ?></code> <?php $u=$usageCounts[$cls]??0; if($u>0): ?><span class="ml-1 inline-block text-[10px] text-gray-500">(<?= (int)$u ?>)</span><?php endif; ?></div>
                <div class="flex items-center gap-1">
                  <button type="button" class="btn btn-secondary btn-xs" title="Copy class to clipboard" data-action="copy-class">Copy</button>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
    <div id="cssGroupsByFile" class="space-y-4 hidden" data-mode="file">
      <?php foreach ($groupsByFile as $file => $list): ?>
        <?php $unique = array_values(array_unique($list)); sort($unique); ?>
        <div class="border rounded" data-group="<?= htmlspecialchars($file) ?>">
          <div class="px-3 py-2 font-semibold bg-gray-50 flex items-center justify-between">
            <div>File: <?= htmlspecialchars($file) ?> <span class="text-xs text-gray-500">(<?= count($unique) ?>)</span></div>
            <div class="text-xs">
              <button type="button" class="btn btn-secondary btn-xs" data-action="group-toggle">Toggle</button>
            </div>
          </div>
          <ul class="p-3 grid gap-1 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($unique as $cls): ?>
              <li class="text-sm flex items-center justify-between gap-2 py-1 px-2 rounded hover:bg-gray-50" data-class="<?= htmlspecialchars($cls) ?>">
                <div class="truncate"><code><?= htmlspecialchars($cls) ?></code> <?php $u=$usageCounts[$cls]??0; if($u>0): ?><span class="ml-1 inline-block text-[10px] text-gray-500">(<?= (int)$u ?>)</span><?php endif; ?></div>
                <div class="flex items-center gap-1">
                  <button type="button" class="btn btn-secondary btn-xs" title="Copy class to clipboard" data-action="copy-class">Copy</button>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Editable CSS Settings Panel -->
<div id="panelEditable" class="hidden">
  <div class="border rounded p-3 mb-4">
    <div class="font-semibold text-lg mb-2">Brand Colors</div>
    <div class="grid md:grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium mb-1">Primary Color
          <span class="inline-block text-gray-500 text-xs" title="Primary brand color. The vibe that shouts ‘we tried’.">🛈</span>
        </label>
        <div class="flex items-center gap-2">
          <input id="editBrandPrimary" type="color" class="form-input" value="#87ac3a" />
          <input id="editBrandPrimaryHex" type="text" class="form-input flex-1" placeholder="#87ac3a" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Secondary Color
          <span class="inline-block text-gray-500 text-xs" title="Backup brand color. When primary needs a nap.">🛈</span>
        </label>
        <div class="flex items-center gap-2">
          <input id="editBrandSecondary" type="color" class="form-input" value="#BF5700" />
          <input id="editBrandSecondaryHex" type="text" class="form-input flex-1" placeholder="#BF5700" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Accent Color
          <span class="inline-block text-gray-500 text-xs" title="The ‘pop’. Use responsibly; it’s caffeinated.">🛈</span>
        </label>
        <div class="flex items-center gap-2">
          <input id="editBrandAccent" type="color" class="form-input" value="#22c55e" />
          <input id="editBrandAccentHex" type="text" class="form-input flex-1" placeholder="#22c55e" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Background Color
          <span class="inline-block text-gray-500 text-xs" title="Where all the content lives. Like walls, but less beige.">🛈</span>
        </label>
        <div class="flex items-center gap-2">
          <input id="editBrandBackground" type="color" class="form-input" value="#ffffff" />
          <input id="editBrandBackgroundHex" type="text" class="form-input flex-1" placeholder="#ffffff" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Text Color
          <span class="inline-block text-gray-500 text-xs" title="The actual words. Make them readable. Your future self will thank you.">🛈</span>
        </label>
        <div class="flex items-center gap-2">
          <input id="editBrandText" type="color" class="form-input" value="#111827" />
          <input id="editBrandTextHex" type="text" class="form-input flex-1" placeholder="#111827" />
        </div>
      </div>
    </div>
    <div class="mt-4 flex items-center justify-end gap-2">
      <button id="btnBrandReset" type="button" class="btn btn-secondary">Reset to defaults</button>
      <button id="btnBrandSave" type="button" class="btn btn-primary">Save Brand Colors</button>
    </div>
  </div>

  <div class="border rounded p-3 mb-4">
    <div class="font-semibold text-lg mb-2">Brand Fonts</div>
    <div class="grid md:grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium mb-1">Primary Font
          <span class="inline-block text-gray-500 text-xs" title="Your personality font. Make it charming, not alarming.">🛈</span>
        </label>
        <input id="editFontPrimary" type="text" class="form-input w-full" placeholder="Merienda, cursive" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Secondary Font
          <span class="inline-block text-gray-500 text-xs" title="The workhorse font. It does the heavy lifting while primary gets the credit.">🛈</span>
        </label>
        <input id="editFontSecondary" type="text" class="form-input w-full" placeholder="Arial, sans-serif" />
      </div>
    </div>
    <div class="mt-4 flex items-center justify-end gap-2">
      <button id="btnFontSave" type="button" class="btn btn-primary">Save Fonts</button>
    </div>
  </div>

  <div class="border rounded p-3 mb-4">
    <div class="font-semibold text-lg mb-2">Custom CSS Variables</div>
    <p class="text-sm text-gray-600 mb-2">Add your own CSS variables, one per line, like: <code>--brand-shadow: 0 10px 20px rgba(0,0,0,.15);</code>
      <span class="inline-block text-gray-500 text-xs" title="This is your sandbox. Make variables. Break nothing. Maybe.">🛈</span>
    </p>
    <textarea id="editCustomCssVars" class="form-textarea w-full" rows="6" placeholder="--brand-shadow: 0 10px 20px rgba(0,0,0,.15);"></textarea>
    <div class="mt-3 flex items-center justify-end">
      <button id="btnCustomSave" type="button" class="btn btn-primary">Save Custom Variables</button>
    </div>
  </div>

  <div id="editableStatus" class="text-xs text-gray-600"></div>
</div>



<script>
(function(){
  // Tabs
  const tabCatalog = document.getElementById('tabCatalog');
  const tabEditable = document.getElementById('tabEditable');
  const panelCatalog = document.getElementById('panelCatalog');
  const panelEditable = document.getElementById('panelEditable');
  function setTab(which){
    const isCat = which === 'catalog';
    panelCatalog.classList.toggle('hidden', !isCat);
    panelEditable.classList.toggle('hidden', isCat);
    tabCatalog.classList.toggle('btn-secondary', isCat);
    tabEditable.classList.toggle('btn-secondary', !isCat);
    // Reflect selection for outline utility
    tabCatalog.setAttribute('aria-selected', isCat ? 'true' : 'false');
    tabEditable.setAttribute('aria-selected', isCat ? 'false' : 'true');
  }
  tabCatalog && tabCatalog.addEventListener('click', () => setTab('catalog'));
  tabEditable && tabEditable.addEventListener('click', () => setTab('editable'));
  setTab('catalog');

  // API helpers
  async function apiRequest(method, url, data=null, options={}){
    const WF = (typeof window !== 'undefined') ? (window.WhimsicalFrog && window.WhimsicalFrog.api) : null;
    const A = WF || ((typeof window !== 'undefined') ? (window.ApiClient || null) : null);
    const m = String(method||'GET').toUpperCase();
    if (A && typeof A.request === 'function') {
      if (m === 'GET') return A.get(url, (options && options.params) || {});
      if (m === 'POST') return A.post(url, data||{}, options||{});
      if (m === 'PUT') return A.put(url, data||{}, options||{});
      if (m === 'DELETE') return A.delete(url, options||{});
      return A.request(url, { method: m, ...(options||{}) });
    }
    const headers = { 'Content-Type': 'application/json', 'X-WF-ApiClient': '1', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers||{}) };
    const cfg = { credentials:'include', method:m, headers, ...(options||{}) };
    if (data !== null && typeof cfg.body === 'undefined') cfg.body = JSON.stringify(data);
    const res = await fetch(url, cfg);
    return res.json().catch(()=>null);
  }
  const apiGet = (url, params) => apiRequest('GET', url, null, { params });
  const apiPost = (url, body, options) => apiRequest('POST', url, body, options);

  const q = document.getElementById('cssSearch');
  if (!q) return;
  q.addEventListener('input', () => {
    const term = q.value.toLowerCase().trim();
    document.querySelectorAll('#cssGroups li[data-class],#cssGroupsByFile li[data-class]').forEach(li => {
      const cls = li.getAttribute('data-class') || '';
      li.style.display = !term || cls.toLowerCase().includes(term) ? '' : 'none';
    });
  });

  // Editable Settings logic
  const brand = {
    primary: { color: document.getElementById('editBrandPrimary'), hex: document.getElementById('editBrandPrimaryHex') },
    secondary: { color: document.getElementById('editBrandSecondary'), hex: document.getElementById('editBrandSecondaryHex') },
    accent: { color: document.getElementById('editBrandAccent'), hex: document.getElementById('editBrandAccentHex') },
    background: { color: document.getElementById('editBrandBackground'), hex: document.getElementById('editBrandBackgroundHex') },
    text: { color: document.getElementById('editBrandText'), hex: document.getElementById('editBrandTextHex') },
  };
  const editCustom = document.getElementById('editCustomCssVars');
  const statusEl = document.getElementById('editableStatus');
  const btnBrandReset = document.getElementById('btnBrandReset');
  const btnBrandSave = document.getElementById('btnBrandSave');
  const btnCustomSave = document.getElementById('btnCustomSave');
  const fontPrimaryEl = document.getElementById('editFontPrimary');
  const fontSecondaryEl = document.getElementById('editFontSecondary');
  const btnFontSave = document.getElementById('btnFontSave');

  function applyCssVar(name, val){ try { document.documentElement.style.setProperty(name, val); } catch(_){} }
  function syncPair(p, varName){
    if (!p) return;
    p.hex.value = p.color.value;
    const syncVal = (v) => { p.hex.value = v; applyCssVar(varName, v); };
    p.color.addEventListener('input', () => syncVal(p.color.value));
    p.hex.addEventListener('input', () => { const v=p.hex.value.trim(); if (/^#?[0-9a-f]{3,8}$/i.test(v)) { const vv = v.startsWith('#')?v:'#'+v; p.color.value = vv; applyCssVar(varName, vv); } });
  }
  syncPair(brand.primary, '--brand-primary');
  syncPair(brand.secondary, '--brand-secondary');
  syncPair(brand.accent, '--brand-accent');
  syncPair(brand.background, '--brand-bg');
  syncPair(brand.text, '--brand-text');

  // Parse and apply custom CSS variables from textarea input
  function applyCustomVars(text){
    try {
      // Clear only variables we explicitly set? We keep it additive to avoid clobbering other vars
      const lines = String(text||'').split(/\r?\n/);
      for (const raw of lines){
        const line = raw.trim();
        if (!line) continue;
        const m = line.match(/^(--[A-Za-z0-9_-]+)\s*:\s*([^;]+);?$/);
        if (m) {
          applyCssVar(m[1], m[2]);
        }
      }
    } catch(_){}
  }
  if (editCustom) {
    editCustom.addEventListener('input', () => applyCustomVars(editCustom.value));
  }

  async function loadBusiness(){
    try {
      const j = await apiGet('/api/business_settings.php?action=get_business_info');
      if (!j || !j.success) { statusEl.textContent = 'Unable to load current settings.'; return; }
      const s = j.data || {};
      const set = (pair, key, def, varName) => { try { const val = (s[key] ?? def ?? '').toString().trim(); if (!pair) return; const use= val || def || '#000000'; pair.color.value = use; pair.hex.value = use; applyCssVar(varName, use); } catch(_) {} };
      set(brand.primary, 'business_brand_primary', '#87ac3a', '--brand-primary');
      set(brand.secondary, 'business_brand_secondary', '#BF5700', '--brand-secondary');
      set(brand.accent, 'business_brand_accent', '#22c55e', '--brand-accent');
      set(brand.background, 'business_brand_background', '#ffffff', '--brand-bg');
      set(brand.text, 'business_brand_text', '#111827', '--brand-text');
      editCustom.value = (s['business_css_vars'] || '').trim();
      // Apply custom vars live
      applyCustomVars(editCustom.value);
      // Fonts
      const fp = (s['business_font_primary'] || 'Merienda, cursive').toString().trim();
      const fs = (s['business_font_secondary'] || 'Arial, sans-serif').toString().trim();
      if (fontPrimaryEl) { fontPrimaryEl.value = fp; applyCssVar('--font-primary', fp); }
      if (fontSecondaryEl) { fontSecondaryEl.value = fs; applyCssVar('--font-secondary', fs); }
      statusEl.textContent = '';
    } catch(_) { statusEl.textContent = 'Unable to load current settings.'; }
  }
  loadBusiness();

  btnBrandReset && btnBrandReset.addEventListener('click', () => {
    try {
      const defaults = { primary:'#87ac3a', secondary:'#BF5700', accent:'#22c55e', background:'#ffffff', text:'#111827' };
      brand.primary.color.value = defaults.primary; brand.primary.hex.value = defaults.primary; applyCssVar('--brand-primary', defaults.primary);
      brand.secondary.color.value = defaults.secondary; brand.secondary.hex.value = defaults.secondary; applyCssVar('--brand-secondary', defaults.secondary);
      brand.accent.color.value = defaults.accent; brand.accent.hex.value = defaults.accent; applyCssVar('--brand-accent', defaults.accent);
      brand.background.color.value = defaults.background; brand.background.hex.value = defaults.background; applyCssVar('--brand-bg', defaults.background);
      brand.text.color.value = defaults.text; brand.text.hex.value = defaults.text; applyCssVar('--brand-text', defaults.text);
      if (fontPrimaryEl) { fontPrimaryEl.value = 'Merienda, cursive'; applyCssVar('--font-primary', 'Merienda, cursive'); }
      if (fontSecondaryEl) { fontSecondaryEl.value = 'Arial, sans-serif'; applyCssVar('--font-secondary', 'Arial, sans-serif'); }
      statusEl.textContent = 'Reset to defaults. Calm, rational colors restored.';
    } catch(_) {}
  });

  btnBrandSave && btnBrandSave.addEventListener('click', async () => {
    try {
      const payload = {
        settings: {
          business_brand_primary: brand.primary.hex.value,
          business_brand_secondary: brand.secondary.hex.value,
          business_brand_accent: brand.accent.hex.value,
          business_brand_background: brand.background.hex.value,
          business_brand_text: brand.text.hex.value,
        },
        category: 'business'
      };
      const j = await apiPost('/api/business_settings.php?action=upsert_settings', payload);
      statusEl.textContent = (j && j.success) ? 'Brand colors saved! Prepare to bask in tasteful gradients.' : 'Save failed. The colors revolted.';
    } catch(_) { statusEl.textContent = 'Save failed. Try again.'; }
  });

  btnCustomSave && btnCustomSave.addEventListener('click', async () => {
    try {
      const payload = { settings: { business_css_vars: editCustom.value || '' }, category: 'business' };
      const j = await apiPost('/api/business_settings.php?action=upsert_settings', payload);
      statusEl.textContent = (j && j.success) ? 'Custom variables saved! You wield great power.' : 'Save failed. CSS gods disapprove.';
    } catch(_) { statusEl.textContent = 'Save failed. Try again.'; }
  });

  btnFontSave && btnFontSave.addEventListener('click', async () => {
    try {
      const payload = { settings: { business_font_primary: (fontPrimaryEl?.value || '').trim(), business_font_secondary: (fontSecondaryEl?.value || '').trim() }, category: 'business' };
      const j = await apiPost('/api/business_settings.php?action=upsert_settings', payload);
      if (fontPrimaryEl) applyCssVar('--font-primary', fontPrimaryEl.value || 'Merienda, cursive');
      if (fontSecondaryEl) applyCssVar('--font-secondary', fontSecondaryEl.value || 'Arial, sans-serif');
      statusEl.textContent = (j && j.success) ? 'Fonts saved! Your brand voice just cleared its throat.' : 'Save failed. The fonts went on strike.';
    } catch(_) { statusEl.textContent = 'Save failed. Try again.'; }
  });
  });
  const onlyTips = document.getElementById('toggleWithTooltips');
  const byHasTooltip = (li) => !!li.getAttribute('data-has-tooltip');
  onlyTips && onlyTips.addEventListener('change', () => {
    const on = !!onlyTips.checked;
    document.querySelectorAll('#cssGroups li[data-class],#cssGroupsByFile li[data-class]').forEach(li => {
      if (!on) { li.style.removeProperty('display'); return; }
      li.style.display = byHasTooltip(li) ? '' : 'none';
    });
  });

  // Expand/collapse groups
  const expandAll = document.getElementById('expandAll');
  const collapseAll = document.getElementById('collapseAll');
  const setGroupOpen = (grp, open) => {
    const ul = grp.querySelector('ul'); if (!ul) return;
    ul.style.display = open ? '' : 'none';
  };
  expandAll && expandAll.addEventListener('click', () => {
    document.querySelectorAll('#cssGroups > div[data-group],#cssGroupsByFile > div[data-group]').forEach(g => setGroupOpen(g, true));
  });
  collapseAll && collapseAll.addEventListener('click', () => {
    document.querySelectorAll('#cssGroups > div[data-group],#cssGroupsByFile > div[data-group]').forEach(g => setGroupOpen(g, false));
  });

  // Grouping mode toggle
  const byPrefix = document.getElementById('groupByPrefix');
  const byFile = document.getElementById('groupByFile');
  const prefixWrap = document.getElementById('cssGroups');
  const fileWrap = document.getElementById('cssGroupsByFile');
  function applyMode(){
    const useFile = !!(byFile && byFile.checked);
    if (useFile) { fileWrap && fileWrap.classList.remove('hidden'); prefixWrap && prefixWrap.classList.add('hidden'); }
    else { prefixWrap && prefixWrap.classList.remove('hidden'); fileWrap && fileWrap.classList.add('hidden'); }
  }
  byPrefix && byPrefix.addEventListener('change', applyMode);
  byFile && byFile.addEventListener('change', applyMode);
  applyMode();

  function handleListClick(e){
    const target = e.target;
    const li = target.closest && target.closest('li[data-class]');
    const grp = target.closest && target.closest('div[data-group]');
    const actionBtn = (a) => target.matches(`[data-action="${a}"]`);
    if (actionBtn('group-toggle') && grp) {
      const ul = grp.querySelector('ul'); if (!ul) return;
      ul.style.display = (ul.style.display === 'none') ? '' : 'none';
      return;
    }
    if (!li) return;
    const cls = li.getAttribute('data-class') || '';
    if (actionBtn('copy-class')) {
      try { navigator.clipboard.writeText(cls); } catch(_) {}
      return;
    }
  }
  document.getElementById('cssGroups')?.addEventListener('click', handleListClick);
  document.getElementById('cssGroupsByFile')?.addEventListener('click', handleListClick);
  // Lightweight hover tooltip for elements with title (convert to floating bubble)
  document.addEventListener('mouseover', (ev) => {
    const el = ev.target.closest('[title]');
    if (!el || el.__wfTip) return;
    const text = el.getAttribute('title');
    if (!text) return;
    el.removeAttribute('title');
    const tip = document.createElement('div');
    tip.className = 'wf-inline-tooltip';
    tip.textContent = text;
    Object.assign(tip.style, { position:'absolute', zIndex:'10070', background:'#111', color:'#fff', padding:'8px 10px', borderRadius:'8px', fontSize:'12px', maxWidth:'320px', boxShadow:'0 8px 22px rgba(0,0,0,.25)' });
    const rect = el.getBoundingClientRect();
    tip.style.left = Math.round(rect.left + window.scrollX) + 'px';
    tip.style.top = Math.round(rect.bottom + window.scrollY + 6) + 'px';
    document.body.appendChild(tip);
    el.__wfTip = tip;
    el.addEventListener('mouseleave', () => { try { el.__wfTip.remove(); } catch(_) {} delete el.__wfTip; }, { once:true });
  });
})();
</script>

<?php if (!$isModal): ?>
  </div>
</div>
<?php endif; ?>

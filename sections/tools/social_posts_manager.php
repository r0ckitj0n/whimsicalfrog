<?php
// sections/tools/social_posts_manager.php — Social Media Posts Manager
// Supports modal embedding via ?modal=1 for admin settings integration.

$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

require_once $root . '/api/config.php';

if (!$inModal) {
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_social_posts_manager_footer_shutdown')) {
      function __wf_social_posts_manager_footer_shutdown() { @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_social_posts_manager_footer_shutdown');
  }
  $section = 'settings';
  include_once $root . '/components/admin_nav_tabs.php';
}

if ($inModal) {
  include $root . '/partials/modal_header.php';
}
?>
<?php if (!$inModal): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>

<div id="socialPostsManagerRoot" class="p-3">
  <?php if (!$inModal): ?>
  <div class="admin-card">
    <div class="flex items-center justify-between">
      <h1 class="admin-card-title">Social Media Posts</h1>
      <div class="text-sm text-gray-600">Create templates and publish to connected accounts</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="admin-card admin-form-inline mb-3">
    <button id="spmRefresh" class="btn btn-secondary btn-sm">Refresh</button>
    <button id="spmNew" class="btn btn-primary btn-sm">New Template</button>
  </div>

  <div class="admin-card">
    <table class="admin-table">
      <thead class="bg-gray-50 border-b">
        <tr>
          <th class="text-left p-2 w-10">ID</th>
          <th class="text-left p-2">Name</th>
          <th class="text-left p-2">Platforms</th>
          <th class="text-left p-2">Active</th>
          <th class="text-left p-2 w-64">Actions</th>
        </tr>
      </thead>
      <tbody id="spmBody" class="divide-y">
        <tr><td colspan="5" class="p-3 text-center text-gray-500">Loading…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Editor Modal -->
<div id="spmEditorOverlay" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="spmEditorTitle">
  <div class="admin-modal admin-modal-content admin-modal--lg">
    <div class="modal-header">
      <h2 id="spmEditorTitle" class="admin-card-title">Post Template</h2>
      <button type="button" class="admin-modal-close" data-action="spm-editor-close" aria-label="Close">×</button>
    </div>
    <div class="modal-body">
      <form id="spmEditorForm" class="space-y-2" data-action="prevent-submit">
        <input type="hidden" id="spmId" />
        <div class="grid gap-2 md:grid-cols-2">
          <div>
            <label class="block text-xs font-semibold mb-1" for="spmName">Name</label>
            <input id="spmName" class="form-input w-full" type="text" required />
          </div>
          <label class="inline-flex items-center text-xs mt-6">
            <input id="spmActive" type="checkbox" class="mr-2" checked /> Active
          </label>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1" for="spmItem">Item</label>
          <select id="spmItem" class="form-select w-full">
            <option value="">— Select item —</option>
          </select>
          <div id="spmItemInfo" class="mt-1 text-xs text-gray-600 hidden"></div>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1" for="spmPitch">Sales Pitch Template</label>
          <select id="spmPitch" class="form-select w-full">
            <option value="">— Auto (by category) —</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1" for="spmContent">Post Content</label>
          <textarea id="spmContent" class="form-textarea w-full h-40" placeholder="Write your post…" required></textarea>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1" for="spmItemImage">Item Images</label>
          <select id="spmItemImage" class="form-select w-full" disabled>
            <option value="">— Select from this item’s images —</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1" for="spmImage">Image (optional)</label>
          <select id="spmImage" class="form-select w-full">
            <option value="">— No image —</option>
          </select>
          <div id="spmImagePreviewWrap" class="mt-2 hidden">
            <img id="spmImagePreview" src="" alt="Selected image preview" class="max-h-40 rounded border" loading="lazy" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">Platforms</label>
          <div class="flex flex-wrap gap-3 text-sm">
            <label class="inline-flex items-center gap-2"><input type="checkbox" class="spm-platform" value="facebook" />Facebook</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" class="spm-platform" value="instagram" />Instagram</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" class="spm-platform" value="twitter" />Twitter/X</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" class="spm-platform" value="linkedin" />LinkedIn</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" class="spm-platform" value="youtube" />YouTube (Community)</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" class="spm-platform" value="tiktok" />TikTok</label>
          </div>
        </div>
        <div class="pt-2 flex gap-2 justify-end">
          <button type="button" class="btn btn-secondary btn-xs" data-action="spm-editor-cancel">Cancel</button>
          <button type="submit" class="btn btn-primary btn-xs" id="spmSave">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  function notify(msg, type){
    try {
      if (window.parent && window.parent.wfNotifications && typeof window.parent.wfNotifications.show === 'function') { window.parent.wfNotifications.show(msg, type||'info'); return; }
      if (window.parent && typeof window.parent.showNotification === 'function') { window.parent.showNotification(msg, type||'info'); return; }
      if (typeof window.showNotification === 'function') { window.showNotification(msg, type||'info'); return; }
    } catch(_) {}
  }
  async function brandedConfirm(message, options){
    try {
      if (window.parent && typeof window.parent.showConfirmationModal === 'function') {
        return await window.parent.showConfirmationModal({ title:(options&&options.title)||'Please confirm', message, confirmText:(options&&options.confirmText)||'Confirm', confirmStyle:(options&&options.confirmStyle)||'danger', icon:'⚠️', iconType:(options&&options.iconType)||'warning' });
      }
      if (typeof window.showConfirmationModal === 'function') {
        return await window.showConfirmationModal({ title:(options&&options.title)||'Please confirm', message, confirmText:(options&&options.confirmText)||'Confirm', confirmStyle:(options&&options.confirmStyle)||'danger', icon:'⚠️', iconType:(options&&options.iconType)||'warning' });
      }
    } catch(_) {}
    notify('Confirmation UI unavailable. Action canceled.', 'error');
    return false;
  }
  // API helpers: prefer shared ApiClient if available; otherwise fetch with identifying header to suppress dev warnings
  async function apiRequest(method, url, data=null, options={}){
    const A = (typeof window !== 'undefined') ? (window.ApiClient || null) : null;
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
  const body = document.getElementById('spmBody');
  const overlay = document.getElementById('spmEditorOverlay');
  const form = document.getElementById('spmEditorForm');
  const fId = document.getElementById('spmId');
  const fName = document.getElementById('spmName');
  const fActive = document.getElementById('spmActive');
  const fContent = document.getElementById('spmContent');
  const fImage = document.getElementById('spmImage');
  const imgPrevWrap = document.getElementById('spmImagePreviewWrap');
  const imgPrev = document.getElementById('spmImagePreview');
  const fItemSel = document.getElementById('spmItem');
  const fPitchSel = document.getElementById('spmPitch');
  const itemInfo = document.getElementById('spmItemInfo');
  const fItemImageSel = document.getElementById('spmItemImage');

  let __imagesLoaded = false;
  let __imagesLoading = null;
  let __itemsLoaded = false;
  let __itemsLoading = null;
  let __items = [];

  function updateImagePreview() {
    try {
      const url = (fImage && fImage.value) ? fImage.value : '';
      if (url) {
        imgPrevWrap?.classList.remove('hidden');
        if (imgPrev) imgPrev.src = url;
      } else {
        imgPrevWrap?.classList.add('hidden');
        if (imgPrev) imgPrev.src = '';
      }
    } catch (_) {}
  }

  async function loadImagesOnce() {
    if (__imagesLoaded) return true;
    if (__imagesLoading) { try { await __imagesLoading; return true; } catch(_) { return false; } }
    __imagesLoading = (async () => {
      try {
        const j = await apiGet('/api/list_images.php');
        const list = (j && j.success && Array.isArray(j.images)) ? j.images : [];
        if (fImage) {
          const cur = fImage.value || fImage.getAttribute('data-pending-value') || '';
          const opts = ['<option value="">— No image —</option>'].concat(
            list.map(it => `<option value="${it.url}" data-path="${it.path}">${it.name}</option>`)
          );
          fImage.innerHTML = opts.join('');
          if (cur) {
            // Prefer exact URL match; else match by data-path (relative)
            fImage.value = cur;
            if (fImage.value !== cur) {
              const opt = Array.from(fImage.options).find(o => (o.getAttribute('data-path') || '') === cur);
              if (opt) fImage.value = opt.value;
            }
          }
        }
        __imagesLoaded = true;
      } catch (_) {}
    })();
    try { await __imagesLoading; return true; } catch(_) { return false; }
  }

  // Items and sales pitch helpers
  async function loadItemsOnce() {
    if (__itemsLoaded) return true;
    if (__itemsLoading) { try { await __itemsLoading; return true; } catch(_) { return false; } }
    __itemsLoading = (async () => {
      let data = [];
      try {
        const res = await apiGet('/api/inventory.php');
        data = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
      } catch (_) { data = []; }
      __items = data;
      if (fItemSel) {
        const opts = ['<option value="">— Select item —</option>'].concat(
          __items.map(it => {
            const sku = (it.sku||'').toString();
            const name = (it.name||'').toString();
            const price = (it.retailPrice!=null? it.retailPrice : it.retail_price);
            const p = isNaN(parseFloat(price))? '' : ('$'+parseFloat(price).toFixed(2));
            const cat = (it.category||'').toString();
            const label = [name||sku, p].filter(Boolean).join(' — ');
            const safe = (s) => (s||'').replace(/"/g,'&quot;');
            return `<option value="${safe(sku)}" data-name="${safe(name)}" data-price="${safe(price)}" data-category="${safe(cat)}">${label}</option>`;
          })
        );
        fItemSel.innerHTML = opts.join('');
      }
      __itemsLoaded = true;
    })();
    try { await __itemsLoading; return true; } catch(_) { return false; }
  }

  const PITCH_BANK = {
    tumblers: [
      { id:'tumblers_1', label:'Everyday hydration', tpl:'Stay hydrated in style with the {name}! Double-wall insulation keeps drinks cold for hours. Only {price}. #Tumblers #Hydration' },
      { id:'tumblers_2', label:'On-the-go favorite', tpl:'Your new on-the-go favorite: {name}. Durable, leak-resistant, and ready for any adventure. {price}. #Drinkware' }
    ],
    earrings: [
      { id:'earrings_1', label:'Handcrafted sparkle', tpl:'Handcrafted {name} earrings add instant sparkle. Lightweight and hypoallergenic. Just {price}. #Earrings #Handmade' },
      { id:'earrings_2', label:'Everyday elegance', tpl:'Dress it up or keep it casual—{name} earrings complete any look. {price}. #Jewelry' }
    ],
    shirts: [
      { id:'shirts_1', label:'Comfy essential', tpl:'Meet your new favorite tee: {name}. Soft, breathable, and ready for every day. {price}. #GraphicTee' },
      { id:'shirts_2', label:'Statement style', tpl:'Make a statement in {name}. Premium feel with a relaxed fit. {price}. #Style' }
    ],
    stickers: [
      { id:'stickers_1', label:'Waterproof vinyl', tpl:'Stick with style! {name} premium vinyl sticker is waterproof and durable. Only {price}. #Stickers' },
      { id:'stickers_2', label:'Personalize anything', tpl:'Personalize your world with {name}. Smooth finish, lasting color. {price}. #Decals' }
    ],
    ornaments: [
      { id:'ornaments_1', label:'Seasonal keepsake', tpl:'Deck the halls with {name}! A charming keepsake for the season. {price}. #Holiday' },
      { id:'ornaments_2', label:'Gift-ready', tpl:'Celebrate the moments with {name} ornament—gift-ready and timeless. {price}. #Ornaments' }
    ],
    keychains: [
      { id:'keychains_1', label:'Everyday carry', tpl:'Carry a little joy: {name} keychain. Sturdy and cute. {price}. #Keychains' },
      { id:'keychains_2', label:'Pop of personality', tpl:'Add a pop of personality to your keys with {name}. {price}. #Accessories' }
    ],
    mugs: [
      { id:'mugs_1', label:'Morning favorite', tpl:'Start your day with {name}. Cozy sips, microwave and dishwasher safe. {price}. #Mugs' },
      { id:'mugs_2', label:'Giftable mug', tpl:'Warm moments begin with {name}. The perfect gift at {price}. #Coffee' }
    ],
    candles: [
      { id:'candles_1', label:'Cozy vibes', tpl:'Set the mood with {name}. Clean-burning and cozy. {price}. #Candles' },
      { id:'candles_2', label:'Scented ambience', tpl:'Elevate your space with {name}. Long-lasting scent you’ll love. {price}. #Home' }
    ],
    default: [
      { id:'default_1', label:'Short & sweet', tpl:'Loving the {name}! Grab yours for {price}. #NewArrival' },
      { id:'default_2', label:'Feature highlight', tpl:'Say hello to {name}—crafted with care and made to last. Only {price}. #ShopSmall' }
    ]
  };

  function normalizeCategory(c){
    const s = (c||'').toLowerCase();
    if (s.includes('tumbler')) return 'tumblers';
    if (s.includes('earring')) return 'earrings';
    if (s.includes('shirt')||s.includes('tee')||s.includes('t-shirt')) return 'shirts';
    if (s.includes('sticker')||s.includes('decal')) return 'stickers';
    if (s.includes('ornament')) return 'ornaments';
    if (s.includes('keychain')||s.includes('key chain')) return 'keychains';
    if (s.includes('mug')) return 'mugs';
    if (s.includes('candle')) return 'candles';
    return 'default';
  }

  function formatPrice(v){
    const n = parseFloat(v); if (isNaN(n)) return '';
    return '$' + n.toFixed(2);
  }

  function getTemplatesForCategory(cat){
    const key = normalizeCategory(cat);
    return PITCH_BANK[key] || PITCH_BANK.default;
  }

  function renderPitch(tpl, item){
    const map = {
      '{name}': item.name || item.sku || '',
      '{price}': formatPrice(item.price),
      '{category}': item.category || '',
      '{sku}': item.sku || ''
    };
    let out = tpl;
    Object.keys(map).forEach(k => { out = out.split(k).join(map[k]); });
    return out;
  }

  function populatePitchOptions(cat){
    const list = getTemplatesForCategory(cat);
    if (!fPitchSel) return [];
    const opts = list.map((t)=>`<option value="${t.id}">${t.label}</option>`).join('');
    fPitchSel.innerHTML = opts || '<option value="">No templates</option>';
    return list;
  }

  function findImageForSku(sku){
    if (!sku || !fImage) return;
    const opts = Array.from(fImage.options||[]);
    const lowerSku = (sku||'').toLowerCase();
    const match = opts.find(o => {
      const path = (o.getAttribute('data-path')||'').toLowerCase();
      const name = (o.textContent||'').toLowerCase();
      return path.includes(lowerSku) || name.includes(lowerSku);
    });
    if (match) {
      fImage.value = match.value;
      if (fImage.value !== match.value) fImage.value = match.value;
      updateImagePreview();
    } else {
      fImage.value = '';
      updateImagePreview();
    }
  }

  function populateItemImagesForSku(sku){
    if (!fItemImageSel) return;
    const opts = Array.from(fImage?.options || []);
    const lowerSku = (sku||'').toLowerCase();
    let matches = opts.filter(o => {
      const p = (o.getAttribute('data-path')||'').toLowerCase();
      return p.includes('/images/items') && p.includes(lowerSku);
    });
    if (!matches.length && /[a-z]$/.test(lowerSku)) {
      const base = lowerSku.slice(0, -1);
      matches = opts.filter(o => {
        const p = (o.getAttribute('data-path')||'').toLowerCase();
        return p.includes('/images/items') && p.includes(base);
      });
    }
    if (!sku) {
      fItemImageSel.innerHTML = '<option value="">— Select from this item’s images —</option>';
      fItemImageSel.disabled = true;
      return [];
    }
    if (!matches.length) {
      fItemImageSel.innerHTML = '<option value="">No images found for this item</option>';
      fItemImageSel.disabled = true;
      return [];
    }
    const makeLabel = (o) => {
      const path = o.getAttribute('data-path')||'';
      const parts = path.split('/');
      return parts[parts.length-1] || path;
    };
    const html = ['<option value="">— Select from this item’s images —</option>']
      .concat(matches.map(o => `<option value="${o.value}" data-path="${o.getAttribute('data-path')||''}">${makeLabel(o)}</option>`));
    fItemImageSel.innerHTML = html.join('');
    fItemImageSel.disabled = false;
    return matches;
  }

  function getPlatforms(){
    return Array.from(document.querySelectorAll('.spm-platform:checked')).map(cb => cb.value);
  }
  function setPlatforms(list){
    const set = new Set(Array.isArray(list) ? list : []);
    document.querySelectorAll('.spm-platform').forEach(cb => { cb.checked = set.has(cb.value); });
  }

  async function loadTemplates(){
    if (!body) return;
    body.innerHTML = '<tr><td colspan="5" class="p-3 text-center text-gray-500">Loading…</td></tr>';
    const j = await apiGet('/api/social_posts_templates.php?action=list');
    if (!j || !j.success) { body.innerHTML = '<tr><td colspan="5" class="p-3 text-center text-red-600">Failed to load</td></tr>'; return; }
    const rows = (j.templates||[]).map(t => {
      const active = t.is_active ? 'Yes' : 'No';
      const plats = (t.platforms||[]).map(p => `<span class=\"code-badge\">${p}</span>`).join(' ');
      return `<tr>
        <td class="p-2">${t.id}</td>
        <td class="p-2">${(t.name||'')}</td>
        <td class="p-2">${plats||'<span class=\"text-gray-400\">—</span>'}</td>
        <td class="p-2">${active}</td>
        <td class="p-2">
          <button class="btn btn-secondary btn-sm" data-action="spm-edit" data-id="${t.id}">Edit</button>
          <button class="btn btn-secondary btn-sm" data-action="spm-duplicate" data-id="${t.id}">Duplicate</button>
          <button class="btn btn-danger btn-sm" data-action="spm-delete" data-id="${t.id}">Delete</button>
          <button class="btn btn-primary btn-sm" data-action="spm-publish" data-id="${t.id}">Publish to All</button>
        </td>
      </tr>`;
    }).join('');
    body.innerHTML = rows || '<tr><td colspan="5" class="p-3 text-center text-gray-500">No templates</td></tr>';
  }

  function openEditor(t){
    try { if (overlay.parentElement !== document.body) document.body.appendChild(overlay); } catch(_){ }
    overlay.classList.add('show');
    overlay.classList.remove('hidden');
    overlay.setAttribute('aria-hidden','false');
    fId.value = t?.id || '';
    fName.value = t?.name || '';
    fActive.checked = (String(t?.is_active)==='1' || t?.is_active===true);
    fContent.value = t?.content || '';
    // Defer setting the image value until options are loaded
    const desiredImage = t?.image_url || '';
    if (!__imagesLoaded) {
      fImage?.setAttribute('data-pending-value', desiredImage);
      loadImagesOnce().then(() => {
        if (desiredImage) {
          fImage.value = desiredImage;
          if (fImage.value !== desiredImage) {
            const opt = Array.from(fImage.options).find(o => (o.getAttribute('data-path') || '') === desiredImage);
            if (opt) fImage.value = opt.value;
          }
        }
        updateImagePreview();
      });
    } else {
      fImage.value = desiredImage;
      if (fImage.value !== desiredImage) {
        const opt = Array.from(fImage.options).find(o => (o.getAttribute('data-path') || '') === desiredImage);
        if (opt) fImage.value = opt.value;
      }
      updateImagePreview();
    }
    setPlatforms(t?.platforms || []);
  }
  function closeEditor(){
    overlay.classList.add('hidden');
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden','true');
  }

  document.getElementById('spmRefresh')?.addEventListener('click', loadTemplates);
  document.getElementById('spmNew')?.addEventListener('click', async () => { await Promise.all([loadImagesOnce(), loadItemsOnce()]); openEditor(null); });

  fImage?.addEventListener('change', updateImagePreview);

  fItemSel?.addEventListener('change', () => {
    const opt = fItemSel.options[fItemSel.selectedIndex];
    if (!opt || !opt.value) { itemInfo?.classList.add('hidden'); itemInfo.innerHTML=''; return; }
    const sku = opt.value;
    const name = opt.getAttribute('data-name')||'';
    const price = opt.getAttribute('data-price')||'';
    const category = opt.getAttribute('data-category')||'';
    if (itemInfo) { itemInfo.classList.remove('hidden'); itemInfo.innerHTML = `${name||sku} • ${formatPrice(price)||''} • ${category||''}`; }
    const list = populatePitchOptions(category) || [];
    const t0 = list[0]?.tpl || '';
    const it = { sku, name, price, category };
    if (t0) { fContent.value = renderPitch(t0, it); }
    const applyImages = () => { populateItemImagesForSku(sku); };
    if (__imagesLoaded) { applyImages(); } else { loadImagesOnce().then(applyImages); }
  });

  fPitchSel?.addEventListener('change', () => {
    const optItem = fItemSel && fItemSel.options ? fItemSel.options[fItemSel.selectedIndex] : null;
    if (!optItem || !optItem.value) return;
    const sku = optItem.value;
    const name = optItem.getAttribute('data-name')||'';
    const price = optItem.getAttribute('data-price')||'';
    const category = optItem.getAttribute('data-category')||'';
    const list = getTemplatesForCategory(category);
    const chosen = list.find(t => t.id === fPitchSel.value) || list[0];
    if (chosen) { fContent.value = renderPitch(chosen.tpl, { sku, name, price, category }); }
  });

  document.addEventListener('click', async (ev) => {
    const btn = ev.target && ev.target.closest ? ev.target.closest('[data-action]') : null;
    if (!btn) return;
    const action = btn.getAttribute('data-action');
    if (action === 'spm-editor-close' || action === 'spm-editor-cancel') {
      ev.preventDefault(); closeEditor();
    } else if (action === 'spm-edit') {
      ev.preventDefault();
      const id = btn.getAttribute('data-id');
      const j = await apiGet(`/api/social_posts_templates.php?action=get&id=${encodeURIComponent(id)}`);
      if (j && j.success && j.template) openEditor(j.template);
    } else if (action === 'spm-duplicate') {
      ev.preventDefault();
      const id = btn.getAttribute('data-id');
      const j = await apiGet(`/api/social_posts_templates.php?action=get&id=${encodeURIComponent(id)}`);
      if (j && j.success && j.template) {
        const t = j.template;
        openEditor({ id:'', name: `${t.name||'Template'} (Copy)`, content: t.content||'', image_url: t.image_url||'', platforms: t.platforms||[], is_active: t.is_active });
      }
    } else if (action === 'spm-delete') {
      ev.preventDefault();
      const id = btn.getAttribute('data-id');
      if (!(await brandedConfirm('Delete this template?', { confirmText:'Delete', confirmStyle:'danger', iconType:'danger' }))) return;
      await apiPost('/api/social_posts_templates.php?action=delete', { id });
      loadImagesOnce();
  loadItemsOnce();
  loadTemplates();
    } else if (action === 'spm-publish') {
      ev.preventDefault();
      const id = btn.getAttribute('data-id');
      const j = await api(`/api/social_posts_templates.php?action=get&id=${encodeURIComponent(id)}`);
      if (j && j.success && j.template) {
        const t = j.template;
        const payload = { content: t.content||'', image_url: t.image_url||'', platforms: Array.isArray(t.platforms)?t.platforms:[], publish_all: true };
        const res = await apiPost('/api/publish_social.php?action=publish', payload);
        if (res && res.success) notify('Published to all configured accounts', 'success'); else notify('Publish failed', 'error');
      }
    }
  });

  form?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (!fName.value.trim()) { notify('Name is required', 'error'); return; }
    if (!fContent.value.trim()) { notify('Content is required', 'error'); return; }
    const payload = {
      id: fId.value || undefined,
      name: fName.value.trim(),
      content: fContent.value,
      image_url: fImage.value,
      platforms: getPlatforms(),
      is_active: fActive.checked ? 1 : 0,
    };
    const isUpdate = !!fId.value;
    const url = isUpdate ? '/api/social_posts_templates.php?action=update' : '/api/social_posts_templates.php?action=create';
    const r = await fetch(url, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(payload) });
    const j = await r.json().catch(()=>null);
    if (j && j.success) { closeEditor(); loadTemplates(); } else { notify('Save failed', 'error'); }
  });

  loadTemplates();
})();
</script>

<?php if (!$inModal): ?>
  </div>
</div>
<?php endif; ?>

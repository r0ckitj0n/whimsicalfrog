<?php
// sections/tools/action_icons_manager.php
// Modal-friendly Action Icons Manager with CRUD over icon mappings
$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

if (!$inModal) {
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_icons_manager_footer_shutdown')) {
      function __wf_icons_manager_footer_shutdown() { @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_icons_manager_footer_shutdown');
  }
  $section = 'settings';
  include_once $root . '/components/admin_nav_tabs.php';
}
if ($inModal) {
  include $root . '/partials/modal_header.php';
}
?>
<!-- Ensure dynamic icon CSS is present for previews and global updates -->
<link rel="stylesheet" href="/api/admin_icon_map.php?action=get_css" />
<?php if (!$inModal): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>

<style>
  html, body { height: 100%; }
  body { display: flex; flex-direction: column; min-height: 0; }
  #iconsManagerRoot { display: flex; justify-content: center; align-items: stretch; flex: 1 1 auto; min-height: 0; }
  #iconsManagerRoot .icons-manager-inner { width: 100%; max-width: 640px; display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
  @media (min-width: 768px) { #iconsManagerRoot .icons-manager-inner { max-width: 720px; } }
  @media (min-width: 1024px) { #iconsManagerRoot .icons-manager-inner { max-width: 800px; } }
  #iconsManagerRoot .admin-card { margin-left: auto; margin-right: auto; }
  #iconsManagerRoot .icons-card-main { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
  #iconsManagerRoot .icons-table-wrap { flex: 1 1 auto; min-height: 0; overflow: auto; }
  #iconsManagerRoot table.admin-table { width: 100%; }
  #iconsManagerRoot .form-input.w-40 { max-width: 320px; width: 100%; }
  #iconsManagerRoot .form-input.w-20 { max-width: 140px; width: 100%; }
  #iconsManagerRoot .btn-icon { display: inline-flex; align-items: center; justify-content: center; }
  /* Force emoji-capable font for icon previews to avoid tofu/weird glyphs */
  #iconsManagerRoot .btn-icon::before {
    font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", "Noto Emoji", "EmojiOne Color", "Twemoji Mozilla", system-ui, sans-serif !important;
    font-size: 18px; /* slightly larger for clarity in preview */
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }
  /* Use inline emoji span for preview to avoid ::before encoding issues */
  #iconsManagerRoot .preview-btn::before { content: none !important; }
  #iconsManagerRoot .preview-btn .emoji { display:inline-block; font-size:18px; line-height:1; vertical-align:middle; font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", "Noto Emoji", "EmojiOne Color", "Twemoji Mozilla", system-ui, sans-serif !important; }
  /* In this manager, disable ::before for all icon buttons to avoid conflicts; use inline spans instead */
  #iconsManagerRoot .btn-icon[class*="btn-icon--"]::before { content: none !important; }
  #iconsManagerRoot .btn-icon .emoji { display:inline-block; font-size:18px; line-height:1; vertical-align:middle; font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", "Noto Emoji", "EmojiOne Color", "Twemoji Mozilla", system-ui, sans-serif !important; }
  #iconsManagerRoot .admin-card-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  #iconsManagerRoot .flex.items-center.gap-2 { flex-wrap: wrap; }
  #iconsManagerRoot #newKey { max-width: 280px; }
  #iconsManagerRoot #newEmoji { max-width: 120px; }
  #iconsManagerRoot #iconsBody td.p-2 { vertical-align: middle; }
  /* Neutralize ALL tinted icon variants within this manager (no brand or tinted fills) */
  #iconsManagerRoot .btn-icon,
  #iconsManagerRoot .btn-icon[class*="btn-icon--"] {
    background: transparent !important;
    color: #374151 !important;
    border-color: #e5e7eb !important;
  }
  #iconsManagerRoot .btn-icon:hover,
  #iconsManagerRoot .btn-icon[class*="btn-icon--"]:hover { background: transparent !important; }
</style>

<?php if ($inModal): ?>
<?php /* modal adjustments are handled via reusable utilities on wrappers */ ?>
<?php endif; ?>

<div id="iconsManagerRoot" class="p-3 admin-actions-icons<?php echo $inModal ? ' wf-panel-fill' : ''; ?>">
  <div class="icons-manager-inner<?php echo $inModal ? ' wf-w-full wf-max-w-none wf-flex-1' : ''; ?>">
  <?php if (!$inModal): ?>
  <div class="admin-card icons-card-main">
    <div class="flex items-center justify-between mb-2">
      <h1 class="admin-card-title">Action Icons Manager</h1>
      <div class="text-sm text-gray-600">Control which emoji/icon each action class uses</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="admin-card icons-card-main">
    <div class="flex items-center justify-between mb-2">
      <h3 class="admin-card-title">Legend</h3>
      <div class="flex items-center gap-2">
        <button id="iconsReload" class="btn-icon btn-icon--refresh" title="Reload" aria-label="Reload"><span class="emoji">🔄</span></button>
        <button id="iconsSave" class="btn-icon btn-icon--save" title="Save" aria-label="Save"><span class="emoji">💾</span></button>
        <button id="iconsResetDefaults" class="btn-icon btn-icon--undo" title="Reset to defaults" aria-label="Reset to defaults"><span class="emoji">↩</span></button>
      </div>
    </div>
    <div class="text-sm text-gray-600 mb-2">These map to CSS classes like <code>.btn-icon--add</code>. Change the emoji to update the icon everywhere.</div>
    <div class="icons-table-wrap<?php echo $inModal ? ' wf-overflow-auto' : ''; ?>">
      <table class="admin-table">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left p-2 w-40">Action Key</th>
            <th class="text-left p-2 w-24">Icon</th>
            <th class="text-left p-2">Preview</th>
            <th class="text-left p-2 w-24">Delete</th>
          </tr>
        </thead>
        <tbody id="iconsBody" class="divide-y">
          <tr><td colspan="4" class="p-3 text-center text-gray-500">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="admin-card mt-3">
    <div class="p-3">
      <div class="flex items-center justify-between mb-2">
        <h3 class="admin-card-title">Add New Mapping</h3>
      </div>
      <div class="flex items-center gap-2">
        <input id="newKey" type="text" class="form-input" placeholder="action-key (e.g., add)" />
        <input id="newEmoji" type="text" class="form-input" placeholder="emoji (e.g., ➕)" />
        <button id="addRow" class="btn btn-primary btn-sm">Add</button>
      </div>
      <p class="text-xs text-gray-600 mt-2">Tip: Changes are applied live via CSS. No rebuild required.</p>
    </div>
  </div>
</div>
</div>

<script>
(function(){
  const body = document.body;
  const iconsBody = document.getElementById('iconsBody');
  const reloadBtn = document.getElementById('iconsReload');
  const saveBtn = document.getElementById('iconsSave');
  const resetBtn = document.getElementById('iconsResetDefaults');
  const addBtn = document.getElementById('addRow');
  const fKey = document.getElementById('newKey');
  const fEmoji = document.getElementById('newEmoji');

  const DEFAULTS = {
    add:'➕', edit:'✏️', duplicate:'📄', delete:'🗑️', view:'👁️', preview:'👁️', 'preview-inline':'🪟',
    refresh:'🔄', send:'📤', save:'💾', archive:'🗄️', settings:'⚙️', download:'⬇️', upload:'⬆️',
    external:'↗️', link:'🔗', info:'ℹ️', help:'❓', print:'🖨️', up:'▲', down:'▼', close:'×'
  };

  function rowHtml(key, emoji){
    const safeKey = (key || '').trim().toLowerCase();
    const cls = 'btn-icon btn-icon--' + safeKey.replace(/\s+|_/g,'-');
    return `<tr data-key="${safeKey}">
      <td class="p-2"><input type="text" class="form-input w-40 key-input" value="${safeKey}" aria-label="Action key" /></td>
      <td class="p-2"><input type="text" class="form-input w-20 emoji-input" value="${emoji}" aria-label="Emoji for ${safeKey}" /></td>
      <td class="p-2"><button type="button" class="preview-btn ${cls}" title="${safeKey}" aria-label="${safeKey}"><span class="emoji"></span></button></td>
      <td class="p-2"><button type="button" class="admin-action-button btn-icon btn-icon--delete" data-action="del" data-preserve-label="1" aria-label="Remove mapping" title="Remove mapping"><span class="emoji">🗑️</span></button></td>
    </tr>`;
  }

  function setPreviewEmoji(tr, emoji){
    try {
      const span = tr.querySelector('.preview-btn .emoji');
      if (span) { span.textContent = String(emoji || ''); }
    } catch(_){}
  }

  async function load(){
    iconsBody.innerHTML = '<tr><td colspan="4" class="p-3 text-center text-gray-500">Loading…</td></tr>';
    try {
      const res = await fetch('/api/admin_icon_map.php?action=get_map', { credentials:'include' });
      const j = await res.json();
      const saved = (j && j.map) ? j.map : {};
      // Merge default keys with saved map so all standard actions (incl. close) are shown
      const map = Object.assign({}, DEFAULTS, saved);
      const entries = Object.entries(map);
      const rows = entries.map(([k,v])=>rowHtml(k, v)).join('');
      iconsBody.innerHTML = rows || '<tr><td colspan="4" class="p-3 text-center text-gray-500">No mappings yet</td></tr>';
      // Apply visible emoji text to previews
      entries.forEach(([k,v])=>{
        const tr = iconsBody.querySelector(`tr[data-key="${k}"]`);
        if (tr) setPreviewEmoji(tr, v);
      });
    } catch(_) {
      iconsBody.innerHTML = '<tr><td colspan="4" class="p-3 text-center text-red-600">Failed to load</td></tr>';
    }
  }

  function collect(){
    const out = {};
    iconsBody.querySelectorAll('tr[data-key]').forEach(tr => {
      const keyInput = tr.querySelector('input.key-input');
      const emojiInput = tr.querySelector('input.emoji-input');
      const rawKey = (keyInput?.value || '').trim().toLowerCase();
      const key = rawKey.replace(/\s+|_/g,'-');
      const emoji = (emojiInput?.value || '').trim();
      if (key && emoji) out[key] = emoji;
    });
    return out;
  }

  async function save(){
    const map = collect();
    const res = await fetch('/api/admin_icon_map.php?action=set_map', {
      method:'POST', credentials:'include', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ map })
    });
    const j = await res.json().catch(()=>({success:false}));
    if (j && j.success) {
      // Bust icon CSS cache locally and in parent admin doc so all icons update immediately
      try {
        const selfLink = document.querySelector('link[href^="/api/admin_icon_map.php?action=get_css"]');
        if (selfLink) { selfLink.href = '/api/admin_icon_map.php?action=get_css&v=' + Date.now(); }
      } catch(_){}
      try {
        if (window.parent && window.parent.document) {
          const parentLink = window.parent.document.querySelector('link[href^="/api/admin_icon_map.php?action=get_css"]');
          if (parentLink) { parentLink.href = '/api/admin_icon_map.php?action=get_css&v=' + Date.now(); }
        }
      } catch(_){}
      alert('Saved');
    } else {
      alert('Save failed');
    }
  }

  function add(){
    const rawKey = (fKey.value||'').trim().toLowerCase();
    const key = rawKey.replace(/\s+|_/g,'-');
    const emoji = (fEmoji.value||'').trim();
    if (!key || !emoji) return;
    if (iconsBody.querySelector(`tr[data-key="${key}"]`)) return;
    iconsBody.insertAdjacentHTML('beforeend', rowHtml(key, emoji));
    const tr = iconsBody.querySelector(`tr[data-key="${key}"]`);
    if (tr) setPreviewEmoji(tr, emoji);
    fKey.value=''; fEmoji.value=''; fKey.focus();
  }

  function onClick(ev){
    const btn = ev.target && ev.target.closest ? ev.target.closest('[data-action="del"]') : null;
    if (!btn) return;
    const tr = btn.closest('tr[data-key]');
    if (tr) tr.remove();
  }

  function onInput(ev){
    const keyEl = ev.target && ev.target.closest ? ev.target.closest('input.key-input') : null;
    const emojiEl = ev.target && ev.target.closest ? ev.target.closest('input.emoji-input') : null;
    const tr = (keyEl||emojiEl) ? (keyEl||emojiEl).closest('tr[data-key]') : null;
    if (!tr) return;
    if (keyEl) {
      const raw = (keyEl.value||'').trim().toLowerCase();
      const norm = raw.replace(/\s+|_/g,'-');
      tr.setAttribute('data-key', norm);
      const preview = tr.querySelector('.preview-btn');
      if (preview && preview.classList) {
        preview.className = 'preview-btn btn-icon btn-icon--' + norm;
        preview.title = norm;
        preview.setAttribute('aria-label', norm);
      }
    }
    if (emojiEl) {
      setPreviewEmoji(tr, emojiEl.value || '');
    }
  }

  reloadBtn && reloadBtn.addEventListener('click', load);
  saveBtn && saveBtn.addEventListener('click', save);
  resetBtn && resetBtn.addEventListener('click', async ()=>{
    const rows = Object.entries(DEFAULTS).map(([k,v])=>rowHtml(k,v)).join('');
    iconsBody.innerHTML = rows;
  });
  addBtn && addBtn.addEventListener('click', add);
  iconsBody && iconsBody.addEventListener('click', onClick);
  iconsBody && iconsBody.addEventListener('input', onInput);

  load();
})();
</script>

<?php if (!$inModal): ?>
  </div>
</div>
<?php endif; ?>

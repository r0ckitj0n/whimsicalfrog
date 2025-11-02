<?php
// Attributes Manager Embed: shows three lists (Genders, Sizes, Colors) for the settings modal
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once dirname(__DIR__, 2) . '/api/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/auth_helper.php';

// Auth: allow session-admin; in dev, also allow explicit admin_token for iframe usage
try {
    $token = $_GET['admin_token'] ?? $_POST['admin_token'] ?? null;
    if (!$token || $token !== (AuthHelper::ADMIN_TOKEN ?? 'whimsical_admin_2024')) {
        AuthHelper::requireAdmin();
    }
} catch (Throwable $____) {
    AuthHelper::requireAdmin();
}

$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

// Fetch data
$genders = [];
$sizes = [];
$colors = [];

try {
    Database::getInstance();
    // Distinct genders from item_genders
    try {
        $genders = array_map(fn($r) => $r['gender'] ?? '', Database::queryAll("SELECT DISTINCT gender FROM item_genders ORDER BY gender"));
    } catch (Throwable $e) {
        $genders = [];
    }
    // Size templates
    try {
        $sizes = Database::queryAll(
            "SELECT st.id, st.template_name, st.category, COUNT(sti.id) AS size_count
             FROM size_templates st
             LEFT JOIN size_template_items sti ON st.id = sti.template_id AND sti.is_active = 1
             WHERE st.is_active = 1
             GROUP BY st.id
             ORDER BY st.category, st.template_name"
        );
    } catch (Throwable $e) {
        $sizes = [];
    }
    // Color templates
    try {
        $colors = Database::queryAll(
            "SELECT ct.id, ct.template_name, ct.category, COUNT(cti.id) AS color_count
             FROM color_templates ct
             LEFT JOIN color_template_items cti ON ct.id = cti.template_id AND cti.is_active = 1
             WHERE ct.is_active = 1
             GROUP BY ct.id
             ORDER BY ct.template_name"
        );
    } catch (Throwable $e) {
        $colors = [];
    }
} catch (Throwable $e) {
    // fallthrough; lists will render empty gracefully
}

if ($inModal) { include dirname(__DIR__, 2) . '/partials/modal_header.php'; }
?>
<?php if ($inModal): ?>
<style>
  /* Ensure grid layout and card styling in modal context */
  /* html/body intrinsic sizing is governed by embed-iframe.css via app.js */
  html, body { height:auto !important; min-height:auto !important; margin:0; background:transparent; overflow:visible !important; }
  #admin-section-content { display:block; width:100%; max-width:none; height:auto !important; max-height:none !important; overflow:visible !important; overflow-y: visible !important; overflow-x: hidden !important; }
  .attributes-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); grid-auto-rows: min-content; align-items: start; justify-content: stretch; gap: 16px; padding-bottom: 0; margin-top: 10px; position: relative; z-index: 0; }
  .attributes-grid { min-height:auto; height:auto; align-content: start; }
  .attributes-grid > .card { height: auto; width: 100%; min-width: 0; }
  /* Remove trailing bottom whitespace from collapsed margins in modal context */
  #admin-section-content > *:last-child { margin-bottom: 0 !important; }
  .attributes-grid { margin-bottom: 0 !important; }
  .admin-card.my-2 { margin-bottom: 0 !important; }
  .card { border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; display:flex; flex-direction:column; overflow:hidden; padding: 0 !important; }
  .card-header { position:relative; z-index:1; background:#fff !important; padding: 12px 48px 12px 14px !important; min-height: 44px; line-height: 1.3; font-weight: 600; border-bottom: 1px solid #e5e7eb; display:flex; align-items:center; justify-content:flex-start; gap:8px; text-align:left; margin: 0 !important; }
  .card-body { padding: 10px 12px; max-height: unset; overflow: visible; flex: 0 0 auto; min-height: 0; }
  .attributes-grid .card-body ul.simple li {
    display:grid;
    grid-template-columns: minmax(240px, 1fr) max-content; /* wider min ensures labels remain visible */
    align-items:center;
    gap:8px;
  }
  .attributes-grid .card-body ul.simple li > span:first-child {
    display: inline-block;
    min-width: 0; /* allow ellipsis */
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .attributes-grid .card-body ul.simple li .row-actions {
    white-space: nowrap;
  }
  .muted { color: #6b7280; font-size: 12px; }
  ul.simple { list-style: none; padding-left: 0; margin: 0; }
  ul.simple li { padding: 6px 4px; border-bottom: 1px dashed #f1f5f9; display:flex; justify-content: space-between; gap:10px; }
  /* Re-assert grid layout for attribute rows to preserve label visibility */
  .attributes-grid .card-body ul.simple li { display:grid !important; grid-template-columns: minmax(240px, 1fr) max-content !important; align-items:center !important; gap:8px !important; }
  .attributes-grid .card-body ul.simple li > span:first-child { min-width: 0 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }
  .attributes-grid .card-body ul.simple li .row-actions { white-space: nowrap !important; justify-self: end !important; }
  .pill { font-size: 11px; background: #f3f4f6; color: #374151; padding: 2px 6px; border-radius: 12px; }
  .toolbar { position:absolute; right:12px; top:50%; transform:translateY(-50%); display:flex; gap:6px; }
  .row-actions { display:flex; gap:6px; }
  @media (max-width: 740px) {
    .attributes-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
  }
  @media (max-width: 520px) {
    .attributes-grid { grid-template-columns: 1fr; }
  }
  /* Inline modal helpers */
  .attr-modal-overlay { position: fixed; inset: 0; background: rgba(17,24,39,.6); display:none; z-index: 2147483000; }
  .attr-modal-overlay.show { display:block; }
  .attr-modal { position: absolute; top: 6vh; bottom: 6vh; left: 6vw; right: 6vw; max-width: 1100px; margin: 0 auto; background:#fff; border-radius:10px; box-shadow: 0 20px 50px rgba(0,0,0,.35); display:flex; flex-direction:column; border: none; overflow:hidden; }
  .attr-modal-header { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border-bottom:1px solid #e5e7eb; background:#fff; }
  .attr-modal-title { font-weight:600; font-size:14px; }
  .attr-modal-body { overflow-y:auto; overflow-x:hidden; padding:12px 14px; height:100%; background:#fff; }
  #admin-section-content.dimmed { filter: blur(1px) brightness(.85); }
  .editor.loading { pointer-events:none; opacity:.7; }
  .spinner { display:inline-block; width:14px; height:14px; border:2px solid #cbd5e1; border-top-color:#2563eb; border-radius:50%; animation:spin 1s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .color-swatch { background-color: var(--swatch-color, #000); }
  .flex-row-6 { display:flex; gap:6px; align-items:center; }
  .grid-2-2-1-auto { display:grid; grid-template-columns: 2fr 2fr 1fr auto; gap:8px; align-items:center; }
  .move { display:flex; gap:4px; }
  .is-hidden { display:none; }
  .editor .items .row { display:grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap:8px; align-items:center; padding:4px 0; border-bottom:1px dashed #f1f5f9; }
  .editor .grid { display:grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap:8px; align-items:center; }
  .editor .grid.header { font-size:11px; color:#6b7280; }
  .editor input { width:100%; padding:4px 6px; border:1px solid #e5e7eb; border-radius:6px; font-size:12px; }
  .inline-actions { display:flex; gap:8px; margin-top:8px; }
  .apply-box { margin-top:12px; padding:10px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; }
  .apply-box .row { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:8px; align-items:end; }
  .empty { padding: 8px; color: #6b7280; font-size: 13px; }
  .admin-actions-icons .btn { background: #2563eb; color: #fff; border-color: #1d4ed8; }
  .admin-actions-icons .btn:hover { filter: brightness(0.95); }
  .admin-actions-icons .btn.btn-danger { background:#ef4444; border-color:#dc2626; }
  .admin-actions-icons .btn.btn-secondary { background:#111827; color:#fff; border-color:#111827; }
  .toast { position: fixed; top: 12px; right: 12px; color:#fff; padding:10px 12px; border-radius:8px; font-size:12px; z-index: 99999; box-shadow: 0 2px 10px rgba(0,0,0,.2); }
  .toast-ok { background:#065f46; }
  .toast-err { background:#991b1b; }
</style>
<?php endif; ?>
<?php if (!$inModal): ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>Attributes Manager</title>
<style>
  /* Hide global header and admin tabs inside the iframe */
  .site-header, .universal-page-header, .admin-tab-navigation { display: none !important; }
  html, body { background: #fff !important; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, Noto Sans, 'Apple Color Emoji','Segoe UI Emoji'; height: 100%; overflow: hidden; }
  #admin-section-content { padding: 8px 12px 0 !important; height: 100%; max-height: 100vh; overflow: hidden; box-sizing: border-box; display:flex; flex-direction:column; }
  .attributes-grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); grid-auto-rows: 1fr; align-items: stretch; gap: 16px; padding-bottom: 0; flex:1; min-height:0; }
  .card { border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; display:flex; flex-direction:column; overflow:hidden; padding: 0 !important; }
  .card-header { position:relative; z-index:1; background:#fff !important; padding: 12px 48px 12px 14px !important; min-height: 44px; line-height: 1.3; font-weight: 600; border-bottom: 1px solid #e5e7eb; display:flex; align-items:center; justify-content:flex-start; gap:8px; text-align:left; margin: 0 !important; }
  .card-body { padding: 10px 12px; max-height: unset; overflow: auto; flex:1; min-height:0; }
  .muted { color: #6b7280; font-size: 12px; }
  ul.simple { list-style: none; padding-left: 0; margin: 0; }
  ul.simple li { padding: 6px 4px; border-bottom: 1px dashed #f1f5f9; display:flex; justify-content: space-between; gap:10px; }
  .pill { font-size: 11px; background: #f3f4f6; color: #374151; padding: 2px 6px; border-radius: 12px; }
  .empty { padding: 8px; color: #6b7280; font-size: 13px; }
  .toolbar { margin-left: auto; display:flex; gap:6px; }
  .btn { font-size: 12px; padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 6px; background:#fff; cursor:pointer; }
  .btn-primary { background:#2563eb; color:white; border-color:#1d4ed8; }
  .btn-danger { background:#ef4444; color:white; border-color:#dc2626; }
  .row-actions { display:flex; gap:6px; }
  .editor { border-top:1px solid #e5e7eb; margin-top:8px; padding-top:8px; }
  .editor h4 { margin: 4px 0 8px; font-size: 13px; }
  .editor .grid { display:grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap:8px; align-items:center; }
  .editor .grid.header { font-size:11px; color:#6b7280; }
  .editor input { width:100%; padding:4px 6px; border:1px solid #e5e7eb; border-radius:6px; font-size:12px; }
  .editor .items { margin-top:8px; }
  .editor .items .row { display:grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap:8px; align-items:center; padding:4px 0; border-bottom:1px dashed #f1f5f9; }
  .editor .items .row .move { display:flex; gap:4px; }
  .inline-actions { display:flex; gap:8px; margin-top:8px; }
  .apply-box { margin-top:12px; padding:10px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; }
  .apply-box .row { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:8px; align-items:end; }
  .toast { position: fixed; top: 12px; right: 12px; background:#111827; color:#fff; padding:10px 12px; border-radius:8px; font-size:12px; z-index: 99999; box-shadow: 0 2px 10px rgba(0,0,0,.2); }
  .toast-ok { background:#065f46; }
  .toast-err { background:#991b1b; }
  .invalid { border-color:#ef4444 !important; background:#fef2f2; }
  .editor.loading { pointer-events:none; opacity:.7; }
  .spinner { display:inline-block; width:14px; height:14px; border:2px solid #cbd5e1; border-top-color:#2563eb; border-radius:50%; animation:spin 1s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* Inline modal for editors */
  .attr-modal-overlay { position: fixed; inset: 0; background: rgba(17,24,39,.6); display:none; z-index: 2147483000; }
  .attr-modal-overlay.show { display:block; }
  .attr-modal {
    position: absolute;
    top: 6vh; bottom: 6vh; left: 6vw; right: 6vw;
    max-width: 1100px; margin: 0 auto; background:#fff;
    border-radius:10px; box-shadow: 0 20px 50px rgba(0,0,0,.35);
    display:flex; flex-direction:column; border: none; overflow:hidden;
  }
  .attr-modal-header { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border-bottom:1px solid #e5e7eb; background:#fff; }
  .attr-modal-title { font-weight:600; font-size:14px; }
  .attr-modal-close { border:none; background:transparent; font-size:18px; line-height:1; cursor:pointer; padding:4px 8px; }
  .attr-modal-body { overflow-y:auto; overflow-x:hidden; padding:12px 14px; height:100%; background:#fff; }
  /* Neutralize any odd borders around the mounted editor */
  .attr-modal-body > .editor { border: 0; border-radius:8px; box-shadow:none; outline: none; }
  /* Dim background content explicitly when modal is open */
  #admin-section-content.dimmed { filter: blur(1px) brightness(.85); }
</style>
</head>
<body>
<?php endif; ?>
<div id="admin-section-content" class="p-3 admin-actions-icons">
  <div class="admin-card compact my-2">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="admin-card-title">Inventory Structure Tools</h3>
        <div class="text-sm text-gray-600">Analyze and migrate an item's Size/Color structure.</div>
      </div>
      <div>
        <button id="sizeColorRedesignBtn" class="btn btn-primary" data-action="open-size-color-redesign">Open Size/Color Redesign</button>
      </div>
    </div>
  </div>
  <div class="attributes-grid">
    <div class="card">
      <div class="card-header">Genders <span class="muted">(distinct across catalog)</span>
        <div class="toolbar">
          <button class="btn-icon btn-icon--add" data-action="gender-add" title="Add" aria-label="Add"></button>
        </div>
      </div>
      <div class="card-body">
        <div id="genderList">
          <?php if (!empty($genders)): ?>
            <ul class="simple">
              <?php foreach ($genders as $g): ?>
                <li>
                  <span><?= htmlspecialchars((string)$g) ?></span>
                  <span class="row-actions">
                    <button class="btn-icon btn-icon--edit" data-action="gender-rename" title="Rename" aria-label="Rename" data-gender="<?= htmlspecialchars((string)$g) ?>"></button>
                    <button class="btn-icon btn-icon--delete" data-action="gender-delete" title="Delete" aria-label="Delete" data-gender="<?= htmlspecialchars((string)$g) ?>"></button>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="empty">No genders found.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Sizes <span class="muted">(templates)</span>
        <div class="toolbar">
          <button class="btn-icon btn-icon--add" data-action="size-new" title="New Template" aria-label="New Template"></button>
        </div>
      </div>
      <div class="card-body">
        <div id="sizeList">
          <?php if (!empty($sizes)): ?>
            <ul class="simple">
              <?php foreach ($sizes as $t): ?>
                <li>
                  <span><?= htmlspecialchars((string)($t['template_name'] ?? '')) ?><?= $t['category'] ? ' · '.htmlspecialchars((string)$t['category']) : '' ?></span>
                  <span class="row-actions">
                    <span class="pill"><?=(int)($t['size_count'] ?? 0)?> sizes</span>
                    <button class="btn-icon btn-icon--edit" data-action="size-edit" title="Edit" aria-label="Edit" data-id="<?= (int)($t['id'] ?? 0) ?>"></button>
                    <button class="btn-icon btn-icon--duplicate" data-action="size-dup" title="Duplicate" aria-label="Duplicate" data-id="<?= (int)($t['id'] ?? 0) ?>"></button>
                    <button class="btn-icon btn-icon--delete" data-action="size-delete" title="Delete" aria-label="Delete" data-id="<?= (int)($t['id'] ?? 0) ?>"></button>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="empty">No size templates found.</div>
          <?php endif; ?>
        </div>
        <div id="sizeEditor" class="is-hidden"></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Colors <span class="muted">(templates)</span>
        <div class="toolbar">
          <button class="btn-icon btn-icon--add" data-action="color-new" title="New Template" aria-label="New Template"></button>
        </div>
      </div>
      <div class="card-body">
        <div id="colorList">
          <?php if (!empty($colors)): ?>
            <ul class="simple">
              <?php foreach ($colors as $t): ?>
                <li>
                  <span><?= htmlspecialchars((string)($t['template_name'] ?? '')) ?><?= $t['category'] ? ' · '.htmlspecialchars((string)$t['category']) : '' ?></span>
                  <span class="row-actions">
                    <span class="pill"><?=(int)($t['color_count'] ?? 0)?> colors</span>
                    <button class="btn-icon btn-icon--edit" data-action="color-edit" title="Edit" aria-label="Edit" data-id="<?= (int)($t['id'] ?? 0) ?>"></button>
                    <button class="btn-icon btn-icon--duplicate" data-action="color-dup" title="Duplicate" aria-label="Duplicate" data-id="<?= (int)($t['id'] ?? 0) ?>"></button>
                    <button class="btn-icon btn-icon--delete" data-action="color-delete" title="Delete" aria-label="Delete" data-id="<?= (int)($t['id'] ?? 0) ?>"></button>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="empty">No color templates found.</div>
          <?php endif; ?>
        </div>
        <div id="colorEditor" class="is-hidden"></div>
      </div>
    </div>
  </div>
</div>
<!-- Editor Modal injected into this embed -->
<div id="attrEditorModal" class="attr-modal-overlay" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1">
  <div class="attr-modal">
    <div class="attr-modal-header">
      <div class="attr-modal-title" id="attrEditorTitle">Edit Template</div>
      <button type="button" class="attr-modal-close admin-modal-close wf-admin-nav-button" data-action="attr-modal-close" aria-label="Close">×</button>
    </div>
    <div class="attr-modal-body">
      <div id="attrEditorMount"></div>
    </div>
  </div>
  </div>
<script>
(function(){
  const ADMIN_TOKEN = 'whimsical_admin_2024';
  function api(u, opts = {}) {
    try {
      const url = new URL(u, window.location.origin);
      // Ensure admin token present on query
      if (!url.searchParams.has('admin_token')) {
        url.searchParams.set('admin_token', ADMIN_TOKEN);
      }
      const init = { credentials: 'include', ...opts };
      // Inject admin token into body when applicable
      if (init.body) {
        const ct = (init.headers && (init.headers['Content-Type'] || init.headers['content-type'])) || '';
        if (typeof init.body === 'string' && ct.includes('application/json')) {
          try {
            const obj = JSON.parse(init.body || '{}');
            if (!('admin_token' in obj)) obj.admin_token = ADMIN_TOKEN;
            init.body = JSON.stringify(obj);
          } catch(_){}
        } else if (typeof FormData !== 'undefined' && init.body instanceof FormData) {
          if (!init.body.has('admin_token')) init.body.append('admin_token', ADMIN_TOKEN);
        }
      }
      return fetch(url.toString(), init).then(r => r.json()).catch(() => null);
    } catch(_) {
      return Promise.resolve(null);
    }
  }

  async function reloadGenders(){
    try {
      const j = await api('/api/genders_admin.php?action=list_distinct');
      const wrap = document.getElementById('genderList');
      if (!wrap) return;
      if (!j || !j.success) { wrap.innerHTML = '<div class="empty">Failed to load genders</div>'; return; }
      const rows = (j.genders||[]).map(g => `
        <li>
          <span>${escapeHtml(g)}</span>
          <span class="row-actions">
            <button class="btn-icon btn-icon--edit" data-action="gender-rename" title="Rename" aria-label="Rename" data-gender="${escapeAttr(g)}"></button>
            <button class="btn-icon btn-icon--delete" data-action="gender-delete" title="Delete" aria-label="Delete" data-gender="${escapeAttr(g)}"></button>
          </span>
        </li>`).join('');
      wrap.innerHTML = rows ? `<ul class="simple">${rows}</ul>` : '<div class="empty">No genders found.</div>';
    } catch(_){}
  }

  async function reloadSizes(){
    const wrap = document.getElementById('sizeList');
    if (!wrap) return;
    const j = await api('/api/size_templates.php?action=get_all');
    if (!j || !j.success) { wrap.innerHTML = '<div class="empty">Failed to load size templates</div>'; return; }
    const rows = (j.templates||[]).map(t => `
      <li>
        <span>${escapeHtml(t.template_name||'')}${t.category ? ' · '+escapeHtml(t.category) : ''}</span>
        <span class="row-actions">
          <span class="pill">${Number(t.size_count||0)} sizes</span>
          <button class="btn-icon btn-icon--edit" data-action="size-edit" title="Edit" aria-label="Edit" data-id="${t.id}"></button>
          <button class="btn-icon btn-icon--duplicate" data-action="size-dup" title="Duplicate" aria-label="Duplicate" data-id="${t.id}"></button>
          <button class="btn-icon btn-icon--delete" data-action="size-delete" title="Delete" aria-label="Delete" data-id="${t.id}"></button>
        </span>
      </li>`).join('');
    wrap.innerHTML = rows ? `<ul class="simple">${rows}</ul>` : '<div class="empty">No size templates found.</div>';
  }

  async function reloadColors(){
    const wrap = document.getElementById('colorList');
    if (!wrap) return;
    const j = await api('/api/color_templates.php?action=get_all');
    if (!j || !j.success) { wrap.innerHTML = '<div class="empty">Failed to load color templates</div>'; return; }
    const rows = (j.templates||[]).map(t => `
      <li>
        <span>${escapeHtml(t.template_name||'')}${t.category ? ' · '+escapeHtml(t.category) : ''}</span>
        <span class="row-actions">
          <span class="pill">${Number(t.color_count||0)} colors</span>
          <button class="btn-icon btn-icon--edit" data-action="color-edit" title="Edit" aria-label="Edit" data-id="${t.id}"></button>
          <button class="btn-icon btn-icon--duplicate" data-action="color-dup" title="Duplicate" aria-label="Duplicate" data-id="${t.id}"></button>
          <button class="btn-icon btn-icon--delete" data-action="color-delete" title="Delete" aria-label="Delete" data-id="${t.id}"></button>
        </span>
      </li>`).join('');
    wrap.innerHTML = rows ? `<ul class="simple">${rows}</ul>` : '<div class="empty">No color templates found.</div>';
  }

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }
  function escapeAttr(s){ return escapeHtml(s).replace(/"/g,'&quot;'); }

  async function brandedConfirm(message, options){
    try {
      if (window.parent && typeof window.parent.showConfirmationModal === 'function') {
        return await window.parent.showConfirmationModal({
          title: (options && options.title) || 'Please confirm',
          message,
          confirmText: (options && options.confirmText) || 'Confirm',
          confirmStyle: (options && options.confirmStyle) || 'confirm',
          icon: (options && options.icon) || '⚠️',
          iconType: (options && options.iconType) || 'warning'
        });
      }
      if (typeof window.showConfirmationModal === 'function') {
        return await window.showConfirmationModal({
          title: (options && options.title) || 'Please confirm',
          message,
          confirmText: (options && options.confirmText) || 'Confirm',
          confirmStyle: (options && options.confirmStyle) || 'confirm',
          icon: (options && options.icon) || '⚠️',
          iconType: (options && options.iconType) || 'warning'
        });
      }
    } catch(_) {}
    try {
      if (window.parent && window.parent.wfNotifications && typeof window.parent.wfNotifications.show === 'function') { window.parent.wfNotifications.show('Confirmation UI unavailable. Action canceled.', 'error'); }
      else if (window.parent && typeof window.parent.showNotification === 'function') { window.parent.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); }
      else if (typeof window.showNotification === 'function') { window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); }
    } catch(_) {}
    return false;
  }
  function notify(msg, type){
    try {
      if (window.parent && window.parent.wfNotifications && typeof window.parent.wfNotifications.show === 'function') { window.parent.wfNotifications.show(msg, type || 'info'); return; }
      if (window.parent && typeof window.parent.showNotification === 'function') { window.parent.showNotification(msg, type || 'info'); return; }
      if (typeof window.showNotification === 'function') { window.showNotification(msg, type || 'info'); return; }
      if (type === 'error' && typeof window.showError === 'function') { window.showError(msg); return; }
      if (type === 'success' && typeof window.showSuccess === 'function') { window.showSuccess(msg); return; }
    } catch(_) {}
    try { alert(msg); } catch(_) {}
  }

  // Modal helpers
  function openAttrModal(kind, title){
    const overlay = document.getElementById('attrEditorModal');
    const mount = document.getElementById('attrEditorMount');
    if (!overlay || !mount) return;
    // Move the freshly rendered inline editor into the modal mount
    const editor = document.querySelector(`.editor[data-kind="${kind}"]`);
    if (editor) {
      mount.innerHTML = '';
      mount.appendChild(editor);
    }
    // Title + show
    const t = document.getElementById('attrEditorTitle');
    if (t) t.textContent = title || (kind === 'size' ? 'Edit Size Template' : 'Edit Color Template');
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
    // Focus and scroll handling
    try { overlay.setAttribute('tabindex', '-1'); overlay.focus({ preventScroll: false }); } catch(_){ }
    try { document.body.dataset.prevOverflow = document.body.style.overflow || ''; document.body.style.overflow = 'hidden'; } catch(_){ }
    // Dim this embed's content for clarity
    try { (document.getElementById('admin-section-content')||{}).classList?.add('dimmed'); } catch(_){ }
    // Dim the entire parent admin modal (outside the iframe)
    try {
      const pd = window.parent && window.parent.document;
      if (pd) {
        // Inject one-time style for dimming the parent modal if not present
        if (!pd.getElementById('wf-attr-parent-dim-style')) {
          const st = pd.createElement('style');
          st.id = 'wf-attr-parent-dim-style';
          st.textContent = `
            .admin-modal-overlay.wf-dim-backdrop .admin-modal { filter: brightness(.8) saturate(.9); }
            .admin-modal-overlay.wf-dim-backdrop::after { content:''; position:fixed; inset:0; background: rgba(17,24,39,.35); pointer-events:none; }
          `;
          pd.head.appendChild(st);
        }
        const parentOverlay = pd.querySelector('.admin-modal-overlay');
        if (parentOverlay) parentOverlay.classList.add('wf-dim-backdrop');
      }
    } catch(_){ }
  }
  function closeAttrModal(){
    const overlay = document.getElementById('attrEditorModal');
    const mount = document.getElementById('attrEditorMount');
    if (!overlay || !mount) return;
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
    mount.innerHTML = '';
    // Restore scroll and dimming
    try { document.body.style.overflow = document.body.dataset.prevOverflow || ''; delete document.body.dataset.prevOverflow; } catch(_){ }
    try { (document.getElementById('admin-section-content')||{}).classList?.remove('dimmed'); } catch(_){ }
    try {
      const pd = window.parent && window.parent.document;
      if (pd) {
        const parentOverlay = pd.querySelector('.admin-modal-overlay');
        if (parentOverlay) parentOverlay.classList.remove('wf-dim-backdrop');
      }
    } catch(_){ }
  }

  // Toast + validation helpers
  function toast(msg, ok=true){
    try {
      const t = document.createElement('div');
      t.className = 'toast ' + (ok ? 'toast-ok' : 'toast-err');
      t.textContent = msg;
      document.body.appendChild(t);
      setTimeout(()=>{ try{ t.remove(); }catch(_){ } }, 1800);
    } catch(_){ console.log(msg); }
  }
  function validateSizePayload(name, rows){
    if (!name || !rows.length) return 'Template name and at least one size are required';
    return '';
  }
  function validateColorPayload(name, rows){
    if (!name || !rows.length) return 'Template name and at least one color are required';
    return '';
  }

  const ROOT = document.getElementById('admin-section-content') || document;
  ROOT.addEventListener('click', async (ev) => {
    const btn = ev.target && ev.target.closest ? ev.target.closest('[data-action]') : null;
    if (!btn) return;
    const action = btn.getAttribute('data-action');
    try {
      if (action === 'open-size-color-redesign') {
        ev.preventDefault(); ev.stopPropagation();
        try {
          const pd = window.parent && window.parent.document;
          if (pd) {
            const m = pd.getElementById('sizeColorRedesignModal');
            if (m) {
              try {
                if (m.parentElement && m.parentElement !== pd.body) {
                  pd.body.appendChild(m);
                }
                m.classList.add('over-header');
              } catch(_) {}
              try {
                const f = pd.getElementById('sizeColorRedesignFrame');
                if (f && (!f.getAttribute('src') || f.getAttribute('src') === 'about:blank')) {
                  const ds = f.getAttribute('data-src') || '/sections/tools/size_color_redesign.php?modal=1';
                  f.setAttribute('src', ds);
                }
              } catch(_) {}
              m.classList.remove('hidden');
              m.classList.add('show');
              m.setAttribute('aria-hidden','false');
              m.classList.remove('pointer-events-none');
              m.classList.add('pointer-events-auto');
            } else {
              // Fallback: open tool directly if parent modal not found
              try { window.open('/sections/tools/size_color_redesign.php?modal=1', '_blank', 'noreferrer'); } catch(__) {}
            }
          } else {
            try { window.open('/sections/tools/size_color_redesign.php?modal=1', '_blank', 'noreferrer'); } catch(__) {}
          }
        } catch(_) {
          try { window.open('/sections/tools/size_color_redesign.php?modal=1', '_blank', 'noreferrer'); } catch(__) {}
        }
        return;
      }
      if (action === 'gender-add') {
        const name = prompt('New gender name:');
        if (!name) return;
        await api('/api/genders_admin.php?action=create', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ name })
        });
        await reloadGenders();
      } else if (action === 'gender-rename') {
        const old = btn.getAttribute('data-gender') || '';
        const name = prompt('Rename gender to:', old);
        if (!name || name === old) return;
        await api('/api/genders_admin.php?action=rename', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ old, new: name })
        });
        await reloadGenders();
      } else if (action === 'gender-delete') {
        const name = btn.getAttribute('data-gender') || '';
        if (!name) return;
        {
          const ok = await brandedConfirm(`Delete gender "${name}" everywhere? This cannot be undone.`, { confirmText: 'Delete', confirmStyle: 'danger', iconType: 'danger' });
          if (!ok) return;
        }
        await api('/api/genders_admin.php?action=delete', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ name })
        });
        await reloadGenders();
      } else if (action === 'size-new') {
        ev.preventDefault(); ev.stopPropagation();
        const template_name = prompt('Size template name:');
        if (!template_name) return;
        const category = prompt('Category (optional):') || 'General';
        const sizes = [];
        const r = await api('/api/size_templates.php?action=create_template', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ template_name, description:'', category, sizes })
        });
        await reloadSizes();
        try {
          const tid = (r && r.data && r.data.template_id) || (r && r.template_id);
          if (tid) { await openSizeEditor(tid); openAttrModal('size', 'New Size Template'); }
        } catch(_){ }
      } else if (action === 'size-edit') {
        ev.preventDefault(); ev.stopPropagation();
        const id = btn.getAttribute('data-id');
        await openSizeEditor(id);
        openAttrModal('size', 'Edit Size Template');
      } else if (action === 'size-delete') {
        ev.preventDefault(); ev.stopPropagation();
        const id = btn.getAttribute('data-id');
        if (!id) return;
        {
          const ok = await brandedConfirm('Delete this size template?', { confirmText: 'Delete', confirmStyle: 'danger', iconType: 'danger' });
          if (!ok) return;
        }
        const form = new FormData(); form.append('template_id', id);
        await api('/api/size_templates.php?action=delete_template', { method:'POST', body: form });
        await reloadSizes();
        renderSizeEditor(null);
        notify('Size template deleted', 'success');
      } else if (action === 'color-new') {
        ev.preventDefault(); ev.stopPropagation();
        const template_name = prompt('Color template name:');
        if (!template_name) return;
        const category = prompt('Category (optional):') || 'General';
        const colors = [];
        const cr = await api('/api/color_templates.php?action=create_template', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ template_name, description:'', category, colors })
        });
        await reloadColors();
        try {
          const tid = (cr && cr.data && cr.data.template_id) || (cr && cr.template_id);
          if (tid) { await openColorEditor(tid); openAttrModal('color', 'New Color Template'); }
        } catch(_){ }
      } else if (action === 'color-edit') {
        ev.preventDefault(); ev.stopPropagation();
        const id = btn.getAttribute('data-id');
        await openColorEditor(id);
        openAttrModal('color', 'Edit Color Template');
      } else if (action === 'color-delete') {
        ev.preventDefault(); ev.stopPropagation();
        const id = btn.getAttribute('data-id');
        if (!id) return;
        {
          const ok = await brandedConfirm('Delete this color template?', { confirmText: 'Delete', confirmStyle: 'danger', iconType: 'danger' });
          if (!ok) return;
        }
        const form = new FormData(); form.append('template_id', id);
        await api('/api/color_templates.php?action=delete_template', { method:'POST', body: form });
        await reloadColors();
        renderColorEditor(null);
        notify('Color template deleted', 'success');
      } else if (action === 'size-dup') {
        const id = btn.getAttribute('data-id');
        const j = await api(`/api/size_templates.php?action=get_template&template_id=${encodeURIComponent(id)}`);
        if (!j || !j.success || !j.template) return toast('Failed to duplicate: source not found', false);
        const tpl = j.template;
        const payload = {
          template_name: `Copy of ${tpl.template_name || 'Template'}`.slice(0, 100),
          description: tpl.description || '',
          category: tpl.category || 'General',
          sizes: (tpl.sizes||[]).map((s,idx)=>({ size_name:s.size_name, size_code:s.size_code, price_adjustment:s.price_adjustment||0, display_order: idx }))
        };
        const cr = await api('/api/size_templates.php?action=create_template', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        await reloadSizes();
        const nid = (cr && cr.data && cr.data.template_id) || (cr && cr.template_id);
        if (nid) { toast('Template duplicated'); await openSizeEditor(nid); } else { toast('Duplicated, but could not open editor', false); }
      } else if (action === 'color-dup') {
        const id = btn.getAttribute('data-id');
        const j = await api(`/api/color_templates.php?action=get_template&template_id=${encodeURIComponent(id)}`);
        if (!j || !j.success || !j.template) return toast('Failed to duplicate: source not found', false);
        const tpl = j.template;
        const payload = {
          template_name: `Copy of ${tpl.template_name || 'Template'}`.slice(0, 100),
          description: tpl.description || '',
          category: tpl.category || 'General',
          colors: (tpl.colors||[]).map((c,idx)=>({ color_name:c.color_name, color_code:c.color_code, display_order: (idx+1) }))
        };
        const cr = await api('/api/color_templates.php?action=create_template', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        await reloadColors();
        const nid = (cr && cr.data && cr.data.template_id) || (cr && cr.template_id);
        if (nid) { toast('Template duplicated'); await openColorEditor(nid); } else { toast('Duplicated, but could not open editor', false); }
      } else if (action === 'row-up' || action === 'row-down' || action === 'row-del') {
        ev.preventDefault(); ev.stopPropagation();
        const row = btn.closest('.row'); if (!row) return;
        const container = row.parentElement; if (!container) return;
        if (action === 'row-del') { row.remove(); return; }
        if (action === 'row-up' && row.previousElementSibling) {
          container.insertBefore(row, row.previousElementSibling);
        } else if (action === 'row-down' && row.nextElementSibling) {
          container.insertBefore(row.nextElementSibling, row);
        }
      } else if (action === 'size-row-add') {
        ev.preventDefault(); ev.stopPropagation();
        const rows = document.getElementById('sizeRows'); if (!rows) return;
        const idx = rows.children.length;
        rows.insertAdjacentHTML('beforeend', `
          <div class="row" data-index="${idx}">
            <input data-field="size_name" placeholder="Size (e.g., XL)" />
            <input data-field="size_code" placeholder="Code (e.g., XL)" />
            <input data-field="price_adjustment" type="number" step="0.01" value="0" />
            <input data-field="display_order" type="number" value="${idx}" />
            <div class="move">
              <button class="btn-icon btn-icon--up" data-action="row-up" title="Move up" aria-label="Move up"></button>
              <button class="btn-icon btn-icon--down" data-action="row-down" title="Move down" aria-label="Move down"></button>
              <button class="btn-icon btn-icon--delete" data-action="row-del" title="Remove" aria-label="Remove"></button>
            </div>
          </div>`);
      } else if (action === 'color-row-add') {
        ev.preventDefault(); ev.stopPropagation();
        const rows = document.getElementById('colorRows'); if (!rows) return;
        const idx = rows.children.length + 1;
        rows.insertAdjacentHTML('beforeend', `
          <div class=\"row grid-2-2-1-auto\" data-index=\"${idx}\">
            <input data-field=\"color_name\" placeholder=\"Color (e.g., Royal Blue)\" />
            <div class=\"flex-row-6\">
              <input class=\"color-swatch\" type=\"color\" value=\"#000000\" />
              <input data-field=\"color_code\" placeholder=\"#RRGGBB or name\" />
            </div>
            <input data-field=\"display_order\" type=\"number\" value=\"${idx}\" />
            <div class=\"move\">
              <button class=\"btn-icon btn-icon--up\" data-action=\"row-up\" title=\"Move up\" aria-label=\"Move up\"></button>
              <button class=\"btn-icon btn-icon--down\" data-action=\"row-down\" title=\"Move down\" aria-label=\"Move down\"></button>
              <button class=\"btn-icon btn-icon--delete\" data-action=\"row-del\" title=\"Remove\" aria-label=\"Remove\"></button>
            </div>
          </div>`);
      }
    } catch(_) { /* optionally toast */ }
  }, true);

  // Close modal on overlay click and Escape key
  document.getElementById('attrEditorModal')?.addEventListener('click', (e) => {
    if (e.target && e.target === e.currentTarget) closeAttrModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const overlay = document.getElementById('attrEditorModal');
      if (overlay && overlay.classList.contains('show')) closeAttrModal();
    }
  });

  // Save/Cancel handlers for editors and modal close
  ROOT.addEventListener('click', async (ev) => {
    const btn = ev.target && ev.target.closest ? ev.target.closest('[data-action]') : null;
    if (!btn) return;
    const action = btn.getAttribute('data-action');
    try {
      if (action === 'attr-modal-close') { ev.preventDefault(); ev.stopPropagation(); closeAttrModal(); return; }
      if (action === 'size-save'){
        ev.preventDefault(); ev.stopPropagation();
        const ed = document.querySelector('.editor[data-kind="size"]'); if (!ed) return;
        const id = ed.getAttribute('data-id');
        const payload = {
          template_id: Number(id),
          template_name: (document.getElementById('sizeTplName')||{}).value || '',
          category: (document.getElementById('sizeTplCategory')||{}).value || 'General',
          description: (document.getElementById('sizeTplDesc')||{}).value || '',
          sizes: collectSizeRows()
        };
        const err = validateSizePayload(payload.template_name, payload.sizes);
        // Inline validation highlights
        Array.from(document.querySelectorAll('#sizeRows [data-field="size_name"]')).forEach(inp=>{
          if (!inp.value.trim()) inp.classList.add('invalid'); else inp.classList.remove('invalid');
        });
        if (err) { toast(err, false); return; }
        ed.classList.add('loading');
        try {
          await api('/api/size_templates.php?action=update_template', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
          await reloadSizes();
          toast('Size template saved');
        } finally {
          ed.classList.remove('loading');
        }
      }
      if (action === 'size-cancel'){
        ev.preventDefault(); ev.stopPropagation();
        renderSizeEditor(null);
        closeAttrModal();
      }
      if (action === 'color-save'){
        ev.preventDefault(); ev.stopPropagation();
        const ed = document.querySelector('.editor[data-kind="color"]'); if (!ed) return;
        const id = ed.getAttribute('data-id');
        const payload = {
          template_id: Number(id),
          template_name: (document.getElementById('colorTplName')||{}).value || '',
          category: (document.getElementById('colorTplCategory')||{}).value || 'General',
          description: (document.getElementById('colorTplDesc')||{}).value || '',
          colors: collectColorRows()
        };
        const err2 = validateColorPayload(payload.template_name, payload.colors);
        // Inline validation highlights
        Array.from(document.querySelectorAll('#colorRows [data-field="color_name"]')).forEach(inp=>{
          if (!inp.value.trim()) inp.classList.add('invalid'); else inp.classList.remove('invalid');
        });
        if (err2) { toast(err2, false); return; }
        ed.classList.add('loading');
        try {
          await api('/api/color_templates.php?action=update_template', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
          await reloadColors();
          toast('Color template saved');
        } finally {
          ed.classList.remove('loading');
        }
      }
      if (action === 'color-cancel'){
        ev.preventDefault(); ev.stopPropagation();
        renderColorEditor(null);
        closeAttrModal();
      }
      if (action === 'size-apply'){
        ev.preventDefault(); ev.stopPropagation();
        const ed = document.querySelector('.editor[data-kind="size"]'); if (!ed) return;
        const id = ed.getAttribute('data-id');
        const sku = (document.getElementById('sizeApplySku')||{}).value || '';
        const mode = (document.getElementById('sizeApplyMode')||{}).value || 'all';
        const colorId = (document.getElementById('sizeApplyColorId')||{}).value || '';
        const defStock = parseInt((document.getElementById('sizeApplyDefaultStock')||{}).value || '0', 10) || 0;
        const replaceExisting = !!(document.getElementById('sizeApplyReplace')||{}).checked;
        if (!sku) { toast('Item SKU is required', false); return; }
        const payload = {
          template_id: Number(id),
          item_sku: sku,
          apply_mode: mode,
          color_id: mode === 'color_specific' ? (colorId||null) : null,
          default_stock: defStock,
          replace_existing: replaceExisting
        };
        ed.classList.add('loading');
        try {
          await api('/api/size_templates.php?action=apply_to_item', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
          toast('Sizes applied to item');
        } finally { ed.classList.remove('loading'); }
      }
      if (action === 'color-apply'){
        ev.preventDefault(); ev.stopPropagation();
        const ed = document.querySelector('.editor[data-kind="color"]'); if (!ed) return;
        const id = ed.getAttribute('data-id');
        const sku = (document.getElementById('colorApplySku')||{}).value || '';
        const defStock = parseInt((document.getElementById('colorApplyDefaultStock')||{}).value || '0', 10) || 0;
        const replaceExisting = !!(document.getElementById('colorApplyReplace')||{}).checked;
        if (!sku) { toast('Item SKU is required', false); return; }
        const payload = {
          template_id: Number(id),
          item_sku: sku,
          default_stock: defStock,
          replace_existing: replaceExisting
        };
        ed.classList.add('loading');
        try {
          await api('/api/color_templates.php?action=apply_to_item', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
          toast('Colors applied to item');
        } finally { ed.classList.remove('loading'); }
      }
    } catch(_){ /* ignore */ }
  });

  // Keyboard reordering: Alt+ArrowUp/Down moves the current row
  document.addEventListener('keydown', (e) => {
    if (!e.altKey) return;
    if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
    const active = document.activeElement;
    if (!active) return;
    const row = active.closest && active.closest('.items .row');
    const container = row && row.parentElement;
    if (!row || !container) return;
    e.preventDefault();
    if (e.key === 'ArrowUp' && row.previousElementSibling) {
      container.insertBefore(row, row.previousElementSibling);
    } else if (e.key === 'ArrowDown' && row.nextElementSibling) {
      container.insertBefore(row.nextElementSibling, row);
    }
  });

  // Initial live refresh to ensure up-to-date lists
  reloadGenders();
  reloadSizes();
  reloadColors();

  // Inline editors for Size and Color templates
  function renderSizeEditor(t){
    const wrap = document.getElementById('sizeEditor');
    if (!wrap) return;
    if (!t) { wrap.innerHTML = ''; wrap.style.display = 'none'; return; }
    const sizes = Array.isArray(t.sizes) ? t.sizes.slice().sort((a,b)=> (a.display_order||0)-(b.display_order||0)) : [];
    wrap.innerHTML = `
      <div class="editor" data-kind="size" data-id="${t.id}">
        <h4>Edit Size Template</h4>
        <div class=\"grid grid-2-2-2\">
          <div><input id="sizeTplName" placeholder="Template name" value="${escapeAttr(t.template_name||'')}" /></div>
          <div><input id="sizeTplCategory" placeholder="Category" value="${escapeAttr(t.category||'')}" /></div>
          <div><input id="sizeTplDesc" placeholder="Description" value="${escapeAttr(t.description||'')}" /></div>
        </div>
        <div class="items">
          <div class=\"grid header grid-2-1-1-1-auto\">
            <div>Size Name</div><div>Code</div><div>Price Δ</div><div>Order</div><div>Actions</div>
          </div>
          <div id="sizeRows">
            ${sizes.map((s,idx)=>`
              <div class="row" data-index="${idx}">
                <input data-field="size_name" value="${escapeAttr(s.size_name||'')}" />
                <input data-field="size_code" value="${escapeAttr(s.size_code||'')}" />
                <input data-field="price_adjustment" type="number" step="0.01" value="${escapeAttr(s.price_adjustment||0)}" />
                <input data-field="display_order" type="number" value="${escapeAttr(s.display_order||idx)}" />
                <div class="move">
                  <button class="btn btn-icon btn-icon--up" data-action="row-up" aria-label="Move up" title="Move up"></button>
                  <button class="btn btn-icon btn-icon--down" data-action="row-down" aria-label="Move down" title="Move down"></button>
                  <button class="btn btn-icon btn-icon--delete" data-action="row-del" aria-label="Remove" title="Remove"></button>
                </div>
              </div>`).join('')}
          </div>
          <div class="inline-actions">
            <button class="btn" data-action="size-row-add">Add Size</button>
            <button class="btn btn-primary" data-action="size-save">Save Template</button>
            <button class="btn" data-action="size-cancel">Cancel</button>
          </div>
          <div class=\"apply-box\">
            <div class=\"adm-subtitle\">Apply to Item</div>
            <div class="row">
              <div><input id="sizeApplySku" placeholder="Item SKU (required)" /></div>
              <div>
                <select id="sizeApplyMode">
                  <option value="all">All Colors</option>
                  <option value="color_specific">Specific Color ID</option>
                </select>
              </div>
              <div><input id="sizeApplyColorId" placeholder="Color ID (optional)" /></div>
              <div><input id="sizeApplyDefaultStock" type="number" placeholder="Default stock (0)" value="0" /></div>
            </div>
            <div class=\"flex-row mt-1\">
              <label><input type="checkbox" id="sizeApplyReplace" /> Replace existing sizes</label>
              <button class="btn" data-action="size-apply">Apply</button>
            </div>
          </div>
        </div>
      </div>`;
    wrap.style.display = 'block';
  }

  function renderColorEditor(t){
    const wrap = document.getElementById('colorEditor');
    if (!wrap) return;
    if (!t) { wrap.innerHTML = ''; wrap.style.display = 'none'; return; }
    const colors = Array.isArray(t.colors) ? t.colors.slice().sort((a,b)=> (a.display_order||0)-(b.display_order||0)) : [];
    wrap.innerHTML = `
      <div class="editor" data-kind="color" data-id="${t.id}">
        <h4>Edit Color Template</h4>
        <div class="grid grid-2-2-2">
          <div><input id="colorTplName" placeholder="Template name" value="${escapeAttr(t.template_name||'')}" /></div>
          <div><input id="colorTplCategory" placeholder="Category" value="${escapeAttr(t.category||'')}" /></div>
          <div><input id="colorTplDesc" placeholder="Description" value="${escapeAttr(t.description||'')}" /></div>
        </div>
        <div class="items">
          <div class="grid header grid-2-2-1-auto">
            <div>Color Name</div><div>Color Code</div><div>Order</div><div>Actions</div>
          </div>
          <div id="colorRows">
            ${colors.map((c,idx)=>`
              <div class="row grid-2-2-1-auto" data-index="${idx}">
                <input data-field="color_name" value="${escapeAttr(c.color_name||'')}" />
                <div class="flex-row-6">
                  <input class="color-swatch" type="color" value="${escapeAttr((c.color_code||'').match(/^#?[0-9a-fA-F]{6}$/) ? (c.color_code.startsWith('#')?c.color_code:'#'+c.color_code) : '#000000')}" />
                  <input data-field="color_code" placeholder="#RRGGBB or name" value="${escapeAttr(c.color_code||'')}" />
                </div>
                <input data-field="display_order" type="number" value="${escapeAttr(c.display_order||idx+1)}" />
                <div class="move">
                  <button class="btn btn-icon btn-icon--up" data-action="row-up" aria-label="Move up" title="Move up"></button>
                  <button class="btn btn-icon btn-icon--down" data-action="row-down" aria-label="Move down" title="Move down"></button>
                  <button class="btn btn-icon btn-icon--delete" data-action="row-del" aria-label="Remove" title="Remove"></button>
                </div>
              </div>`).join('')}
          </div>
          <div class="inline-actions">
            <button class="btn" data-action="color-row-add">Add Color</button>
            <button class="btn btn-primary" data-action="color-save">Save Template</button>
            <button class="btn" data-action="color-cancel">Cancel</button>
          </div>
          <div class="apply-box">
            <div class="adm-subtitle">Apply to Item</div>
            <div class="row grid-3-eq">
              <div><input id="colorApplySku" placeholder="Item SKU (required)" /></div>
              <div><input id="colorApplyDefaultStock" type="number" placeholder="Default stock (0)" value="0" /></div>
              <div class="flex-row">
                <label><input type="checkbox" id="colorApplyReplace" /> Replace existing colors</label>
              </div>
            </div>
            <div class="mt-1">
              <button class="btn" data-action="color-apply">Apply</button>
            </div>
          </div>
        </div>
      </div>`;
    wrap.style.display = 'block';
  }

  async function openSizeEditor(id){
    const j = await api(`/api/size_templates.php?action=get_template&template_id=${encodeURIComponent(id)}`);
    if (j && j.success) renderSizeEditor(j.template); else toast('Failed to load size template', false);
  }
  async function openColorEditor(id){
    const j = await api(`/api/color_templates.php?action=get_template&template_id=${encodeURIComponent(id)}`);
    if (j && j.success) renderColorEditor(j.template); else toast('Failed to load color template', false);
  }

  function collectSizeRows(){
    const rows = Array.from(document.querySelectorAll('#sizeRows .row'));
    return rows.map((row, idx) => {
      const get = (sel)=> (row.querySelector(`[data-field="${sel}"]`)||{}).value || '';
      return {
        size_name: get('size_name').trim(),
        size_code: get('size_code').trim(),
        price_adjustment: parseFloat(get('price_adjustment')||'0')||0,
        display_order: parseInt(get('display_order')||idx,10)
      };
    }).filter(s=>s.size_name);
  }
  function collectColorRows(){
    const rows = Array.from(document.querySelectorAll('#colorRows .row'));
    return rows.map((row, idx) => {
      const get = (sel)=> (row.querySelector(`[data-field="${sel}"]`)||{}).value || '';
      return {
        color_name: get('color_name').trim(),
        color_code: (()=>{ const v=(get('color_code')||'').trim(); const m=v.match(/^#?[0-9a-fA-F]{6}$/); return m ? (v.startsWith('#')? v : ('#'+v)) : v; })(),
        display_order: parseInt(get('display_order')||idx+1, 10)
      };
    }).filter(c=>c.color_name);
  }
  // Two-way sync between color swatch and color_code inputs
  document.addEventListener('input', (e) => {
    const target = e.target;
    if (!target) return;
    // From swatch -> code
    if (target.classList && target.classList.contains('color-swatch')) {
      const row = target.closest('.row');
      if (!row) return;
      const code = row.querySelector('[data-field="color_code"]');
      if (code) { code.value = target.value || '#000000'; code.classList.remove('invalid'); }
      return;
    }
    // From code -> swatch
    if (target.matches && target.matches('[data-field="color_code"]')) {
      const row = target.closest('.row');
      if (!row) return;
      const sw = row.querySelector('.color-swatch');
      if (!sw) return;
      const v = (target.value || '').trim();
      const m = v.match(/^#?[0-9a-fA-F]{6}$/);
      if (m) { sw.value = v.startsWith('#') ? v : ('#' + v); target.classList.remove('invalid'); }
      else { target.classList.add('invalid'); }
    }
  });

})();
</script>
</body>
</html>

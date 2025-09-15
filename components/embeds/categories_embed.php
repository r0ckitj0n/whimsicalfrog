<?php
// Categories Manager Embed (for Settings modal iframe)
// Renders the Categories management UI in an isolated context with header/nav hidden.

// Mark as included-from-index to prevent redirect logic in admin_categories.php
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

require_once dirname(__DIR__, 2) . '/api/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

// Basic access guard (reuse token-based admin detection if available)
if (!function_exists('isAdminWithToken') || !isAdminWithToken()) {
    http_response_code(403);
    echo '<div style="padding:16px;color:#b91c1c;font-family:sans-serif">Access denied.</div>';
    exit;
}

// Bootstrap layout so Vite-managed CSS/JS load, but hide header/nav inside the iframe for a clean embed
$page = 'admin';
include dirname(__DIR__, 2) . '/partials/header.php';
?>
<style>
/* Hide global header and admin tabs inside the iframe */
.site-header, .universal-page-header, .admin-tab-navigation { display: none !important; }
/* Neutral iframe body background to blend with parent modal */
html, body { background: transparent !important; }
/* Tighten padding for embed (reduce space below modal header) */
#admin-section-content { padding: 6px 12px 12px !important; }
/* Remove page-level headers inside embeds to maximize space */
.admin-header-section { display: none !important; }
/* Ensure the first card sits close to the top */
.add-category-card { margin-top: 0 !important; }
</style>
<div id="admin-section-content">
<?php
// Render the categories UI (no redirect thanks to INCLUDED_FROM_INDEX)
include dirname(__DIR__, 2) . '/admin/admin_categories.php';
?>
</div>
<script>
(function(){
  const api = async (action, payload) => {
    const form = new URLSearchParams();
    form.set('action', action);
    if (payload) { Object.entries(payload).forEach(([k,v]) => { if (v !== undefined && v !== null) form.set(k, v); }); }
    const res = await fetch('/api/categories.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form });
    const data = await res.json().catch(() => ({ success:false, error:'Invalid JSON' }));
    if (!data.success) { const msg = (data && data.error) ? data.error : `HTTP ${res.status}`; throw new Error(msg); }
    return data.data || {};
  };

  const THRESHOLDS = {
    NAME_RENAME_CONFIRM: 50,
    SKU_REWRITE_BG_DEFAULT: 1000,
  };

  const tableBody = document.getElementById('categoryTableBody');
  const addForm = document.getElementById('addCategoryForm');
  const newCatInput = document.getElementById('newCategory');

  async function refresh() {
    try {
      const data = await api('list');
      const cats = (data && data.categories) ? data.categories : [];
      if (!tableBody) return;
      tableBody.innerHTML = '';
      cats.forEach(row => {
        const code = row.code || '';
        const ex = `WF-${code}-001`;
        const tr = document.createElement('tr');
        tr.setAttribute('data-category-id', String(row.id));
        tr.setAttribute('data-category', row.name);
        tr.setAttribute('data-category-code', code);
        tr.innerHTML = `
          <td>
            <div class="editable-field" data-field="name" data-original="${escapeHtml(row.name)}" title="Click to edit category name">${escapeHtml(row.name)}</div>
          </td>
          <td>
            <span class="code-badge editable-code" data-field="code" title="Click to edit code">${escapeHtml(code)}</span>
          </td>
          <td><span class="code-badge">${escapeHtml(ex)}</span></td>
          <td>
            <button class="text-red-600 hover:text-red-800 delete-category-btn" data-category="${escapeHtml(row.name)}" title="Delete Category">🗑️</button>
          </td>`;
        tableBody.appendChild(tr);
      });
    } catch (e) {
      console.warn('[Categories] refresh failed', e);
    }
  }

  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

  async function previewCounts(id, next){
    try {
      const data = await api('preview_update', { id, name: next.name, code: next.code });
      return data || {};
    } catch (e) { return {}; }
  }

  function makeEditable(el){
    if (!el) return;
    const field = el.getAttribute('data-field');
    const tr = el.closest('tr');
    const id = tr ? Number(tr.getAttribute('data-category-id')||'0') : 0;
    const original = el.textContent.trim();
    const input = document.createElement('input');
    input.type = 'text'; input.value = original; input.className = 'form-input'; input.style.minWidth = '140px';
    el.replaceWith(input);
    input.focus();
    input.addEventListener('keydown', async (e) => {
      if (e.key === 'Escape') { cancel(); }
      else if (e.key === 'Enter') { e.preventDefault(); await save(); }
    });
    input.addEventListener('blur', async () => { await save(); });
    function cancel(){ input.replaceWith(el); }
    async function save(){
      const val = input.value.trim();
      if (val === original) { cancel(); return; }
      try {
        const payload = { id };
        if (field === 'name') payload.name = val; else if (field === 'code') payload.code = val.toUpperCase();
        // Preview counts
        const preview = await previewCounts(id, { name: payload.name, code: payload.code });
        const itemsCount = Number(preview.items_rename_count || 0);
        const skuCount = Number(preview.sku_rewrite_count || 0);

        // Decision matrix
        if (field === 'name' && itemsCount > THRESHOLDS.NAME_RENAME_CONFIRM) {
          const ok = confirm(`This will update the category name on ${itemsCount} item(s). Proceed?`);
          if (!ok) { cancel(); return; }
          await api('update', payload);
          await refresh();
          return;
        }
        if (field === 'code') {
          if (skuCount > THRESHOLDS.SKU_REWRITE_BG_DEFAULT) {
            const ok = confirm(`About ${skuCount} SKU(s) will be affected. Proceed in BACKGROUND?`);
            if (!ok) { cancel(); return; }
            await api('start_rewrite_job', { id, code: payload.code });
            await refresh();
            return;
          } else if (skuCount > THRESHOLDS.NAME_RENAME_CONFIRM) {
            const ok = confirm(`This will rewrite ${skuCount} SKU(s) now. Proceed?`);
            if (!ok) { cancel(); return; }
            await api('update', payload);
            await refresh();
            return;
          }
        }
        // Small changes: just apply
        await api('update', payload);
        await refresh();
      } catch (err) {
        alert(err.message || 'Update failed');
        cancel();
      }
    }
  }

  if (tableBody) {
    tableBody.addEventListener('click', (e) => {
      const nameEl = e.target.closest('.editable-field[data-field="name"]');
      const codeEl = e.target.closest('.editable-code[data-field="code"]');
      if (nameEl) { makeEditable(nameEl); return; }
      if (codeEl) { makeEditable(codeEl); return; }
      const delBtn = e.target.closest('.delete-category-btn');
      if (delBtn) {
        const tr = delBtn.closest('tr'); const id = tr ? Number(tr.getAttribute('data-category-id')||'0') : 0;
        const name = tr ? (tr.getAttribute('data-category')||'') : '';
        if (!id) return;
        const ok = confirm(`Delete category "${name}"? If items use this category, deletion may be blocked.`);
        if (!ok) return;
        (async () => {
          try { await api('delete', { id }); await refresh(); }
          catch (err) {
            // Build remap UI inline
            buildRemapUI(tr, id, name).catch(() => { alert(err.message || 'Delete failed'); });
          }
        })();
      }
    });
  }

  async function buildRemapUI(tr, id, name){
    // Remove existing panel if any
    const old = tr.nextElementSibling && tr.nextElementSibling.classList.contains('remap-row') ? tr.nextElementSibling : null;
    if (old) old.remove();
    const data = await api('list');
    const cats = (data && data.categories) ? data.categories : [];
    const select = document.createElement('select'); select.className = 'form-input';
    const opt0 = document.createElement('option'); opt0.value = ''; opt0.textContent = 'Select target category…'; select.appendChild(opt0);
    cats.filter(c => String(c.id) !== String(id)).forEach(c => { const o = document.createElement('option'); o.value = String(c.id); o.textContent = `${c.name} (${c.code})`; select.appendChild(o); });
    const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'btn btn-primary'; btn.textContent = 'Force Remap & Delete';
    const td = document.createElement('td'); td.colSpan = 4;
    const wrap = document.createElement('div'); wrap.className = 'flex items-center gap-2';
    const msg = document.createElement('div'); msg.className = 'text-sm text-gray-700'; msg.textContent = `Category "${name}" is in use. Remap items to:`;
    wrap.appendChild(msg); wrap.appendChild(select); wrap.appendChild(btn); td.appendChild(wrap);
    const row = document.createElement('tr'); row.className = 'remap-row'; row.appendChild(td);
    tr.parentNode.insertBefore(row, tr.nextSibling);
    btn.addEventListener('click', async () => {
      const target_id = select.value ? Number(select.value) : 0;
      if (!target_id) { alert('Choose a target category'); return; }
      try { await api('delete', { id, force: '1', target_id }); await refresh(); } catch (e) { alert(e.message || 'Remap failed'); }
    });
  }

  if (addForm) {
    addForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const name = (newCatInput && newCatInput.value ? newCatInput.value.trim() : '');
      if (!name) return;
      try {
        await api('add', { name });
        if (newCatInput) newCatInput.value = '';
        await refresh();
      } catch (err) {
        alert(err.message || 'Add failed');
      }
    });
  }

  // Initial table hydration when categories table exists
  refresh();
})();
</script>
<?php include dirname(__DIR__, 2) . '/partials/footer.php'; ?>

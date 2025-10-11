// Enhances the nested inventory editor with color swatches and a hex color picker
// without modifying the large admin-inventory.js directly.

(function(){})();
import { ApiClient } from '../core/api-client.js';

(function () {
  const NESTED_ID = 'nestedInventoryEditor';

  const validHex = (v) => typeof v === 'string' && /^#?[0-9A-Fa-f]{6}$/.test(v);
  const toHex = (v) => {
    if (!validHex(v)) return '#888888';
    return v.startsWith('#') ? v : `#${v}`;
  };

  const addColorControls = (root) => {
    if (!root) return;
    const colorBlocks = root.querySelectorAll('[data-color-id]');
    colorBlocks.forEach((block) => {
      try {
        // Skip if already enhanced
        if (block.__wfColorEnhanced) return;
        const colorId = Number(block.getAttribute('data-color-id') || 0);
        if (!colorId) return;
        const headerRight = block.querySelector('.text-xs.text-gray-600.flex.items-center.gap-3');
        const headerTitleRow = block.querySelector('.text-sm.font-semibold.text-gray-700.flex.items-center.gap-2');
        if (!headerRight || !headerTitleRow) return;

        // Find existing color code text
        const codeSpan = headerRight.querySelector('.text-gray-400');
        const rawCode = codeSpan && codeSpan.textContent ? codeSpan.textContent.trim() : '';
        const hex = toHex(rawCode || '#888888');

        // Insert small swatch beside color name
        if (!headerTitleRow.querySelector('.wf-inline-swatch')) {
          const sw = document.createElement('span');
          sw.className = 'wf-inline-swatch inline-block w-4 h-4 rounded border';
          sw.title = hex;
          // eslint-disable-next-line no-restricted-syntax
          sw.style.background = hex;
          headerTitleRow.insertBefore(sw, headerTitleRow.children[1] || null);
        }

        // Insert color input before hex label on the right
        if (!headerRight.querySelector('input[type="color"]')) {
          const input = document.createElement('input');
          input.type = 'color';
          input.value = hex;
          input.className = 'h-5 w-8 border rounded';
          input.setAttribute('data-color-id', String(colorId));
          input.setAttribute('data-action', 'update-color-code');
          headerRight.insertBefore(input, codeSpan || null);
          input.addEventListener('change', async () => {
            const value = input.value;
            try {
              const data = await ApiClient.post('/api/item_colors.php?action=update_color_code', { color_id: colorId, color_code: value });
              if (!data?.success) throw new Error(data?.message || 'Failed to update color');
              // Update label and swatch
              if (codeSpan) codeSpan.textContent = value;
              const swatch = headerTitleRow.querySelector('.wf-inline-swatch');
              // eslint-disable-next-line no-restricted-syntax
              if (swatch) swatch.style.background = value;
              if (typeof window.showNotification === 'function') {
                window.showNotification('Color updated', 'success');
              }
            } catch (e) {
              if (typeof window.showNotification === 'function') {
                window.showNotification(e?.message || 'Failed to update color', 'error');
              }
            }
          });
        }

        // Insert a compact hex pill chip if not present
        if (!headerRight.querySelector('.wf-hex-pill') && codeSpan) {
          const pill = document.createElement('span');
          pill.className = 'wf-hex-pill inline-flex items-center px-1.5 py-0.5 rounded border text-[10px] text-gray-700 bg-gray-50';
          pill.textContent = (codeSpan.textContent || hex);
          headerRight.insertBefore(pill, codeSpan);
          codeSpan.classList.add('hidden');
        }

        // Insert Rename/Delete small actions
        if (!headerRight.querySelector('.wf-color-actions')) {
          const actions = document.createElement('span');
          actions.className = 'wf-color-actions inline-flex items-center gap-2';

          const renameBtn = document.createElement('button');
          renameBtn.type = 'button';
          renameBtn.className = 'text-xs text-blue-600 hover:underline';
          renameBtn.textContent = 'Rename';
          renameBtn.addEventListener('click', async () => {
            try {
              const nameEl = headerTitleRow.querySelector('span:last-child');
              const currentName = (nameEl && nameEl.textContent) ? nameEl.textContent.trim() : '';
              const newName = window.prompt('Enter new color name:', currentName);
              if (!newName || newName.trim() === '' || newName === currentName) return;
              const data = await ApiClient.post('/api/item_colors.php?action=update_color_name', { color_id: colorId, color_name: newName.trim() });
              if (!data?.success) throw new Error(data?.message || 'Failed to rename color');
              if (nameEl) nameEl.textContent = newName.trim();
              if (typeof window.showNotification === 'function') window.showNotification('Color renamed', 'success');
            } catch (err) {
              if (typeof window.showNotification === 'function') window.showNotification(err?.message || 'Rename failed', 'error');
            }
          });

          const deleteBtn = document.createElement('button');
          deleteBtn.type = 'button';
          deleteBtn.className = 'text-xs text-red-600 hover:underline';
          deleteBtn.textContent = 'Delete';
          deleteBtn.addEventListener('click', async () => {
            try {
              if (!window.confirm('Delete this color and its size rows? This cannot be undone.')) return;
              const data = await ApiClient.post('/api/item_colors.php?action=delete_color', { color_id: colorId });
              if (!data?.success) throw new Error(data?.message || 'Failed to delete color');
              // Remove block from DOM; nested editor will be refreshed by the admin module later
              block.remove();
              if (typeof window.showNotification === 'function') window.showNotification('Color deleted', 'success');
            } catch (err) {
              if (typeof window.showNotification === 'function') window.showNotification(err?.message || 'Delete failed', 'error');
            }
          });

          actions.appendChild(renameBtn);
          actions.appendChild(deleteBtn);
          headerRight.appendChild(actions);
        }

        block.__wfColorEnhanced = true;
      } catch (_) {}
    });
  };

  const observeNested = () => {
    let root = document.getElementById(NESTED_ID);
    if (!root) {
      // Poll for late-created modal content
      const finder = setInterval(() => {
        root = document.getElementById(NESTED_ID);
        if (root) {
          clearInterval(finder);
          // Now that root exists, continue setup
          addColorControls(root);
          const mo = new MutationObserver((mutations) => {
            for (const m of mutations) {
              if (m.type === 'childList') addColorControls(root);
            }
          });
          mo.observe(root, { childList: true, subtree: true });
          // Force a first-time render
          setTimeout(() => tryFallback(true), 300);
          // Keep nudging until non-loading content appears
          const nudge = setInterval(async () => {
            const text = (root.textContent || '').trim();
            if (!/Loading\s+nested\s+inventory/i.test(text)) { clearInterval(nudge); return; }
            await tryFallback(true);
          }, 1000);
        }
      }, 250);
      return;
    }
    // Initial pass
    addColorControls(root);
    // Observe dynamic updates from rerenders
    const mo = new MutationObserver((mutations) => {
      for (const m of mutations) {
        if (m.type === 'childList') addColorControls(root);
      }
    });
    mo.observe(root, { childList: true, subtree: true });

    // Fallback renderer: if core renderer leaves the loading text in place, replace with a minimal tree
    const tryFallback = async (force = false) => {
      try {
        const text = (root.textContent || '').trim();
        if (!force && !/Loading\s+nested\s+inventory/i.test(text)) return; // core renderer likely succeeded
        const sku = root.dataset && root.dataset.sku ? root.dataset.sku : '';
        if (!sku) return;
        // Fetch raw data
        const [cRes, sRes] = await Promise.all([
          ApiClient.get('/api/item_colors.php', { action: 'get_all_colors', item_sku: sku, wf_dev_admin: 1 }),
          ApiClient.get('/api/item_sizes.php', { action: 'get_all_sizes', item_sku: sku, wf_dev_admin: 1 }),
        ]);
        let colors = []; let sizes = [];
        if (cRes && typeof cRes === 'object' && Array.isArray(cRes.colors)) {
          colors = cRes.colors;
        } else if (typeof cRes === 'string') {
          try { const j = JSON.parse(cRes); if (Array.isArray(j?.colors)) colors = j.colors; } catch(_) {}
        }
        if (sRes && typeof sRes === 'object' && Array.isArray(sRes.sizes)) {
          sizes = sRes.sizes;
        } else if (typeof sRes === 'string') {
          try { const j = JSON.parse(sRes); if (Array.isArray(j?.sizes)) sizes = j.sizes; } catch(_) {}
        }
        if (!Array.isArray(sizes)) sizes = [];
        const toGender = (g) => (!g || g === '') ? 'Unisex' : String(g);
        const genders = Array.from(new Set(sizes.map(s => toGender(s.gender))));
        if (genders.length === 0) genders.push('Unisex');
        const colorById = (colors || []).reduce((m,c)=>{ m[String(c.id)] = c; return m; }, {});
        const byKey = sizes.reduce((m,s)=>{ const g = toGender(s.gender); const cid = s.color_id == null ? 'general' : String(s.color_id); const k = `${g}::${cid}`; (m[k] = m[k] || []).push(s); return m; }, {});

        const esc = (v) => String(v == null ? '' : v);
        let html = '';
        for (const g of genders) {
          const keys = Object.keys(byKey).filter(k => k.startsWith(`${g}::`));
          let genderTotal = 0; const blocks = [];
          for (const k of keys) {
            const list = byKey[k] || [];
            const cid = k.split('::')[1];
            const isGeneral = (cid === 'general');
            const c = isGeneral ? { color_name: 'General Sizes', color_code: '' } : (colorById[cid] || { color_name: `Color ${cid}`, color_code: '' });
            const sum = list.reduce((t,s)=>t + Number(s.stock_level||0), 0); genderTotal += sum;
            const rows = list
              .sort((a,b)=> String(a.size_code||'').localeCompare(String(b.size_code||''), undefined, {numeric:true, sensitivity:'base'}))
              .map(s => `<tr class="border-t"><td class="pr-4 py-1">${esc(s.size_name||s.size_code)}</td><td class="pr-4 py-1 text-gray-500">${esc(s.size_code||'')}</td><td class="py-1">${Number(s.stock_level||0)}</td></tr>`) 
              .join('');
            // Use a small square via inline style; if linting forbids, this is a fallback view only.
            const code = (c.color_code && /^#?[0-9A-Fa-f]{6}$/.test(String(c.color_code))) ? (String(c.color_code).startsWith('#') ? String(c.color_code) : ('#'+c.color_code)) : '';
            const swatch = code ? `<span class="inline-block w-4 h-4 rounded border" style="background:${code}" title="${code}"></span>` : '';
            blocks.push(`
              <div class="border rounded-md">
                <div class="px-3 py-2 bg-white flex items-center justify-between">
                  <div class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <span>${swatch}${esc(c.color_name)}</span>
                    ${isGeneral ? '<span class="wf-container-badge">General</span>' : '<span class="wf-container-badge">Item container</span>'}
                  </div>
                  <div class="text-xs text-gray-600 flex items-center gap-3">
                    <span class="wf-color-total">Total: ${sum}</span>
                    ${code ? `<span class="text-gray-400">${code}</span>` : ''}
                  </div>
                </div>
                <div class="p-3 overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead><tr class="text-gray-600"><th class="text-left pr-4 py-1">Size</th><th class="text-left pr-4 py-1">Code</th><th class="text-left py-1">Stock</th></tr></thead>
                    <tbody>${rows || '<tr><td class="py-2 text-gray-500" colspan="3">No sizes</td></tr>'}</tbody>
                  </table>
                </div>
              </div>
            `);
          }
          html += `
            <details class="border rounded-md" open>
              <summary class="cursor-pointer px-3 py-2 bg-gray-50 text-sm font-medium text-gray-800">
                <span>${esc(g)}</span>
                <span class="ml-2 wf-container-badge wf-gender-total">Total: ${genderTotal}</span>
              </summary>
              <div class="p-3 space-y-3">${blocks.join('') || '<div class="text-sm text-gray-500">No sizes for this group</div>'}</div>
            </details>
          `;
        }
        root.innerHTML = html || '<div class="text-sm text-gray-500">No option inventory defined yet.</div>';
      } catch(_) { /* swallow */ }
    };

    // Try fallback shortly after first paint and keep nudging if stuck
    setTimeout(() => tryFallback(true), 500);
    const nudge = setInterval(async () => {
      const text = (root.textContent || '').trim();
      if (!/Loading\s+nested\s+inventory/i.test(text)) { clearInterval(nudge); return; }
      await tryFallback(true);
    }, 1000);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observeNested, { once: true });
  } else {
    observeNested();
  }
})();

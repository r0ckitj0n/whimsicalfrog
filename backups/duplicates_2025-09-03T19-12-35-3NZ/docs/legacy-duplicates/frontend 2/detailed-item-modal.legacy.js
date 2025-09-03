// WhimsicalFrog Detailed Item Modal – top-level path
// This duplicate ensures availability at /js/detailed-item-modal.js for dynamic loader paths.
// If you need a single source, you can remove this file and update the loader path in src/js/global-item-modal.js.

(function () {
  'use strict';

  function qs(sel, root = document) { return root.querySelector(sel); }
  function qsa(sel, root = document) { return Array.from(root.querySelectorAll(sel)); }

  // Simple in-module state for the single modal instance
  const optionState = {
    sku: '',
    basePrice: 0,
    item: null,
    colors: [],
    sizes: [],
    genders: [],
    selected: {
      gender: '',
      colorId: '',
      colorName: '',
      colorCode: '',
      sizeName: '',
      sizeCode: '',
      sizeAdj: 0,
      sizeStock: null,
      colorStock: null,
    }
  };

  const currency = (v) => `$${Number(v || 0).toFixed(2)}`;

  function sanitizeCode(value) {
    return String(value || '')
      .trim()
      .toUpperCase()
      .replace(/[^A-Z0-9]+/g, '');
  }

  async function apiGet(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`Request failed: ${res.status}`);
    return res.json();
  }

  function setBtnEnabled(modal, enabled) {
    const btn = qs('[data-action="addDetailedToCart"]', modal);
    if (!btn) return;
    btn.disabled = !enabled;
    btn.classList.toggle('opacity-50', !enabled);
    btn.classList.toggle('cursor-not-allowed', !enabled);
    btn.setAttribute('aria-disabled', String(!enabled));
  }

  function updateQtyMax(modal) {
    const input = qs('#detailedQuantity', modal);
    if (!input) return;
    // Determine stock without treating 0 as "no value"
    let stockVal = optionState.selected.sizeStock;
    if (stockVal === null || typeof stockVal === 'undefined' || Number.isNaN(Number(stockVal))) {
      stockVal = optionState.selected.colorStock;
    }
    if (stockVal === null || typeof stockVal === 'undefined' || Number.isNaN(Number(stockVal))) {
      stockVal = Number(optionState.item?.stockLevel);
    }
    if (!Number.isFinite(stockVal)) {
      stockVal = 99;
    }
    if (Number(stockVal) <= 0) {
      input.min = '0';
      input.max = '0';
      input.value = '0';
      setBtnEnabled(modal, false);
      return;
    }
    input.min = '1';
    input.max = String(stockVal);
    const clamped = Math.max(1, Math.min(Number(input.value || 1), Number(stockVal)));
    input.value = String(clamped);
  }

  function updateDisplayedPrice(modal) {
    const base = Number(
      optionState.item?.price ?? optionState.item?.currentPrice ?? optionState.item?.retailPrice ?? optionState.item?.originalPrice ?? 0
    );
    const adj = Number(optionState.selected.sizeAdj || 0);
    const currentEl = qs('#detailedCurrentPrice', modal);
    if (currentEl) currentEl.textContent = currency(base + adj);
  }

  function validateSelections(modal) {
    // All visible .required-field selects must be non-empty
    const required = qsa('.required-field', modal).filter((el) => !el.closest('.hidden'));
    const allSelected = required.every((sel) => sel.value && sel.value !== '');
    setBtnEnabled(modal, allSelected);
    return allSelected;
  }

  function populateSelect(select, items, map, placeholder) {
    if (!select) return;
    const prev = select.value;
    select.innerHTML = '';
    const ph = document.createElement('option');
    ph.value = '';
    ph.textContent = placeholder || 'Select an option...';
    select.appendChild(ph);
    items.forEach((it) => {
      const opt = document.createElement('option');
      const { value, label } = map(it);
      const stockNum = Number(it && typeof it.stock_level !== 'undefined' ? it.stock_level : NaN);
      const isOut = Number.isFinite(stockNum) && stockNum <= 0;
      opt.value = value;
      opt.textContent = isOut ? `${label} (Out of stock)` : label;
      if (isOut) {
        opt.disabled = true;
        opt.classList.add('option-out-of-stock');
      }
      // Attach metadata
      Object.entries(it).forEach(([k, v]) => { try { opt.dataset[k] = v; } catch(_) {} });
      select.appendChild(opt);
    });
    // Try to preserve previous selection if still valid
    const canRestore = Array.from(select.options).some(o => o.value === prev);
    if (canRestore) select.value = prev; else select.value = '';
  }

  async function loadGenders(modal, sku) {
    try {
      const data = await apiGet(`/api/item_genders.php?action=get_all&item_sku=${encodeURIComponent(sku)}`);
      const genders = Array.isArray(data?.genders) ? data.genders : [];
      optionState.genders = genders;
      const container = qs('#genderSelection', modal);
      const select = qs('#itemGenderSelect', modal);
      if (genders.length > 0) {
        populateSelect(select, genders, (g) => ({ value: g.gender, label: g.gender }), 'Select a style...');
        container?.classList.remove('hidden');
      } else {
        container?.classList.add('hidden');
      }
    } catch (e) {
      // Hide on error
      const container = qs('#genderSelection', modal);
      container?.classList.add('hidden');
    }
  }

  async function loadColors(modal, sku) {
    try {
      const data = await apiGet(`/api/item_colors.php?action=get_colors&item_sku=${encodeURIComponent(sku)}`);
      const colors = Array.isArray(data?.colors) ? data.colors : [];
      optionState.colors = colors;
      const container = qs('#colorSelection', modal);
      const select = qs('#itemColorSelect', modal);
      if (colors.length > 0) {
        populateSelect(select, colors, (c) => ({ value: String(c.id), label: c.color_name }), 'Select a color...');
        container?.classList.remove('hidden');
      } else {
        container?.classList.add('hidden');
      }
      // Update stock info if a color is selected
      const info = qs('#colorStockInfo', modal);
      const chosen = colors.find(c => String(c.id) === String(select?.value || ''));
      if (info) info.textContent = chosen ? (Number(chosen.stock_level) > 0 ? `In stock: ${chosen.stock_level}` : 'Out of stock') : '';
      // Also refresh sizes for current color selection
      const colorId = select?.value || '';
      await loadSizes(modal, sku, colorId);
    } catch (e) {
      const container = qs('#colorSelection', modal);
      container?.classList.add('hidden');
      // Still try to load generic sizes
      await loadSizes(modal, sku, '');
    }
  }

  async function loadSizes(modal, sku, colorId) {
    try {
      const queryColor = colorId ? encodeURIComponent(colorId) : '0';
      const data = await apiGet(`/api/item_sizes.php?action=get_sizes&item_sku=${encodeURIComponent(sku)}&color_id=${queryColor}`);
      const sizes = Array.isArray(data?.sizes) ? data.sizes : [];
      optionState.sizes = sizes;
      const container = qs('#sizeSelection', modal);
      const select = qs('#itemSizeSelect', modal);
      if (sizes.length > 0) {
        populateSelect(select, sizes, (s) => ({ value: s.size_code, label: s.size_name }), 'Select a size...');
        container?.classList.remove('hidden');
      } else {
        container?.classList.add('hidden');
      }
      const info = qs('#sizeStockInfo', modal);
      const chosen = sizes.find(s => String(s.size_code) === String(select?.value || ''));
      if (info) info.textContent = chosen ? (Number(chosen.stock_level) > 0 ? `In stock: ${chosen.stock_level}` : 'Out of stock') : '';
      // Update qty max & price
      if (chosen) {
        optionState.selected.sizeAdj = Number(chosen.price_adjustment || 0);
        optionState.selected.sizeStock = Number(chosen.stock_level);
      } else {
        optionState.selected.sizeAdj = 0;
        optionState.selected.sizeStock = null;
      }
      updateDisplayedPrice(modal);
      updateQtyMax(modal);
    } catch (e) {
      const container = qs('#sizeSelection', modal);
      container?.classList.add('hidden');
    }
  }

  async function initOptions(modal, sku, item) {
    optionState.sku = sku;
    optionState.item = item || {};
    optionState.basePrice = Number(item?.price ?? item?.currentPrice ?? item?.retailPrice ?? item?.originalPrice ?? 0);
    optionState.selected = { gender: '', colorId: '', colorName: '', colorCode: '', sizeName: '', sizeCode: '', sizeAdj: 0, sizeStock: null, colorStock: null };

    // Reset selects UI
    ['#itemGenderSelect', '#itemColorSelect', '#itemSizeSelect'].forEach(id => {
      const el = qs(id, modal);
      if (el) {
        el.value = '';
      }
    });

    setBtnEnabled(modal, true); // enable by default; will toggle off if required and empty

    await loadGenders(modal, sku);
    await loadColors(modal, sku);
    // loadSizes is called by loadColors to honor current color selection
    updateDisplayedPrice(modal);
    updateQtyMax(modal);
    validateSelections(modal);
  }

  function tryLockScroll() {
    try { if (window.WFModals && typeof WFModals.lockScroll === 'function') WFModals.lockScroll(); } catch (_) {}
  }
  function tryUnlockScrollIfNone() {
    try { if (window.WFModals && typeof WFModals.unlockScrollIfNoneOpen === 'function') WFModals.unlockScrollIfNoneOpen(); } catch (_) {}
  }

  function renderPrice(item) {
    const currentEl = qs('#detailedCurrentPrice');
    const originalEl = qs('#detailedOriginalPrice');
    const savingsEl = qs('#detailedSavings');
    if (!currentEl || !originalEl || !savingsEl) return;

    const original = Number(
      item?.originalPrice ?? item?.retailPrice ?? item?.price ?? 0
    );
    const current = Number(
      item?.price ?? item?.currentPrice ?? item?.retailPrice ?? original
    );

    currentEl.textContent = `$${current.toFixed(2)}`;

    const onSale = original > current;
    originalEl.textContent = `$${original.toFixed(2)}`;
    savingsEl.textContent = `Save $${(original - current).toFixed(2)}`;
    originalEl.classList.toggle('hidden', !onSale);
    savingsEl.classList.toggle('hidden', !onSale);
  }

  function bindCloseHandlers(modal) {
    qsa('[data-action="closeDetailedModal"]', modal).forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        if (typeof window.closeDetailedModal === 'function') window.closeDetailedModal();
        tryUnlockScrollIfNone();
      });
    });

    modal.addEventListener('click', (e) => {
      const clickedOutside = !e.target.closest('.detailed-item-modal-container');
      const isOverlay = e.target === modal || e.target?.dataset?.action === 'closeDetailedModalOnOverlay' || clickedOutside;
      if (!isOverlay) return;
      if (typeof window.closeDetailedModalOnOverlay === 'function') {
        window.closeDetailedModalOnOverlay(e);
      } else if (typeof window.closeDetailedModal === 'function') {
        window.closeDetailedModal();
      }
      tryUnlockScrollIfNone();
    });

    function onKeyDown(ev) {
      if (ev.key === 'Escape') {
        if (typeof window.closeDetailedModal === 'function') window.closeDetailedModal();
        tryUnlockScrollIfNone();
        document.removeEventListener('keydown', onKeyDown);
      }
    }
    document.addEventListener('keydown', onKeyDown);
  }

  function bindContentInteractions(modal, item) {
    modal.addEventListener('click', (e) => {
      const target = e.target.closest('[data-action="switchDetailedImage"]');
      if (!target) return;
      try {
        const params = JSON.parse(target.getAttribute('data-params') || '{}');
        const url = params.url;
        const main = qs('#detailedMainImage', modal);
        if (main && url) main.src = url;
      } catch (_) {}
    });

    modal.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action="adjustDetailedQuantity"]');
      if (!btn) return;
      e.preventDefault();
      try {
        const params = JSON.parse(btn.getAttribute('data-params') || '{}');
        const delta = Number(params.delta || 0);
        const input = qs('#detailedQuantity', modal);
        if (!input) return;
        const min = Number(input.getAttribute('min') || 1);
        const max = Number(input.getAttribute('max') || 99);
        const next = Math.max(min, Math.min(max, Number(input.value || 1) + delta));
        input.value = String(next);
      } catch (_) {}
    });

    modal.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-action="addDetailedToCart"]');
      if (!btn) return;
      e.preventDefault();
      try {
        const params = JSON.parse(btn.getAttribute('data-params') || '{}');
        const sku = params.sku || item?.sku;
        const qtyEl = qs('#detailedQuantity', modal);
        const qty = Math.max(1, Number(qtyEl?.value || 1));
        if (!sku) return;

        // Validate required selections
        if (!validateSelections(modal)) {
          // Brief visual nudge
          qsa('.required-field', modal).forEach((el) => {
            if (!el.value && !el.closest('.hidden')) {
              el.classList.add('ring-2', 'ring-red-300');
              setTimeout(() => el.classList.remove('ring-2', 'ring-red-300'), 1000);
            }
          });
          return;
        }

        if (window.WF_Cart && typeof window.WF_Cart.addItem === 'function') {
          // Build a flat payload expected by the cart system
          const basePrice = Number(
            item?.price ?? item?.currentPrice ?? item?.retailPrice ?? item?.originalPrice ?? 0
          );
          const selectedColor = optionState.colors.find(c => String(c.id) === String(qs('#itemColorSelect', modal)?.value || ''));
          const selectedSize = optionState.sizes.find(s => String(s.size_code) === String(qs('#itemSizeSelect', modal)?.value || ''));
          const selectedGender = String(qs('#itemGenderSelect', modal)?.value || '').trim();

          const priceAdj = Number(selectedSize?.price_adjustment || 0);
          const price = basePrice + priceAdj;
          const name = item?.name ?? item?.title ?? '';
          const imageCandidate = Array.isArray(item?.images)
            ? (item.images[0]?.url ?? item.images[0])
            : undefined;
          const colorImage = selectedColor?.image_path ? selectedColor.image_path : undefined;
          const image = colorImage ?? item?.image ?? imageCandidate ?? '';

          // Compose a variant-aware SKU to prevent merging different selections
          const parts = [sku];
          if (selectedGender) parts.push(`G${sanitizeCode(selectedGender)}`);
          if (selectedSize?.size_code) parts.push(`S${sanitizeCode(selectedSize.size_code)}`);
          if (selectedColor?.id) parts.push(`C${String(selectedColor.id)}`);
          const cartSku = parts.join('-');

          const payload = {
            ...(item || {}),
            sku: cartSku,
            baseSku: sku,
            quantity: qty,
            price,
            name,
            image,
            optionGender: selectedGender || undefined,
            optionSize: selectedSize?.size_name || undefined,
            optionSizeCode: selectedSize?.size_code || undefined,
            optionColor: selectedColor?.color_name || undefined,
            optionColorCode: selectedColor?.color_code || undefined,
            optionColorId: selectedColor?.id || undefined,
          };
          await window.WF_Cart.addItem(payload);
        } else if (typeof window.addToCart === 'function') {
          window.addToCart(sku, qty);
        } else {
          console.log('[DetailedModal] Add to cart:', { sku, quantity: qty, item });
        }

        if (typeof window.closeDetailedModal === 'function') window.closeDetailedModal();
        tryUnlockScrollIfNone();
      } catch (err) {
        console.error('[DetailedModal] add to cart failed', err);
      }
    });

    const toggle = qs('#additionalInfoToggle', modal);
    const content = qs('#additionalInfoContent', modal);
    const icon = qs('#additionalInfoIcon', modal);
    if (toggle && content) {
      toggle.addEventListener('click', () => {
        const isHidden = content.classList.toggle('hidden');
        if (icon) icon.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(180deg)';
      });
    }

    if (item) renderPrice(item);

    // Option change handlers (delegated once)
    if (!modal.dataset.optionHandlersBound) {
      modal.addEventListener('change', async (e) => {
        const colorSel = e.target && e.target.closest && e.target.closest('#itemColorSelect');
        const sizeSel = e.target && e.target.closest && e.target.closest('#itemSizeSelect');
        const genderSel = e.target && e.target.closest && e.target.closest('#itemGenderSelect');

        if (genderSel) {
          optionState.selected.gender = String(genderSel.value || '');
          validateSelections(modal);
        }

        if (colorSel) {
          const val = String(colorSel.value || '');
          const picked = optionState.colors.find(c => String(c.id) === val);
          optionState.selected.colorId = val;
          optionState.selected.colorName = picked?.color_name || '';
          optionState.selected.colorCode = picked?.color_code || '';
          optionState.selected.colorStock = picked ? Number(picked.stock_level || 0) : null;
          const info = qs('#colorStockInfo', modal);
          if (info) info.textContent = picked ? (Number(picked.stock_level) > 0 ? `In stock: ${picked.stock_level}` : 'Out of stock') : '';
          // Update main image if color has one
          const main = qs('#detailedMainImage', modal);
          if (main && picked?.image_path) main.src = picked.image_path;
          await loadSizes(modal, optionState.sku, val);
          validateSelections(modal);
          updateQtyMax(modal);
        }

        if (sizeSel) {
          const code = String(sizeSel.value || '');
          const chosen = optionState.sizes.find(s => String(s.size_code) === code);
          optionState.selected.sizeName = chosen?.size_name || '';
          optionState.selected.sizeCode = chosen?.size_code || '';
          optionState.selected.sizeAdj = Number(chosen?.price_adjustment || 0);
          optionState.selected.sizeStock = chosen ? Number(chosen.stock_level || 0) : null;
          const info = qs('#sizeStockInfo', modal);
          if (info) info.textContent = chosen ? (Number(chosen.stock_level) > 0 ? `In stock: ${chosen.stock_level}` : 'Out of stock') : '';
          updateDisplayedPrice(modal);
          validateSelections(modal);
          updateQtyMax(modal);
        }
      });
      modal.dataset.optionHandlersBound = '1';
    }
  }

  window.showDetailedModalComponent = function (sku, item) {
    const modal = document.getElementById('detailedItemModal');
    if (!modal) {
      console.warn('[DetailedModal] modal element not found');
      return;
    }

    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.remove('hidden');
    modal.classList.add('show');

    document.body.classList.add('modal-open');
    tryLockScroll();

    const focusTarget = qs('[data-action="closeDetailedModal"]', modal) || modal;
    try { focusTarget.focus && focusTarget.focus(); } catch (_) {}

    if (!modal.dataset.interactionsBound) {
      bindCloseHandlers(modal);
      bindContentInteractions(modal, item);
      modal.dataset.interactionsBound = '1';
    }

    // Initialize options for this item
    try { initOptions(modal, sku, item); } catch (e) { console.error('[DetailedModal] options init failed', e); }
  };

  window.initializeEnhancedModalContent = function () {
    const modal = document.getElementById('detailedItemModal');
    if (!modal) return;
  };

})();

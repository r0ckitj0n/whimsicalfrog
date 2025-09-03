// src/ui/searchModal.js
// ES module conversion of legacy search.js.
import { apiGet } from '../core/apiClient.js';

/* global document, window */

class SearchModal {
  constructor() {
    this.modal = null;
    this.searchInput = null;
    this.isSearching = false;
    this.currentSearchTerm = '';
    this.currentResults = [];
    this.init();
  }

  init() {
    this.createModalHTML();
    this.modal = document.getElementById('searchModal');
    this.searchInput = document.getElementById('headerSearchInput');
    this.bindEvents();
  }

  createModalHTML() {
    if (document.getElementById('searchModal')) return; // prevent duplicates
    const html = `<div id="searchModal" class="wf-search-modal hidden">
        <div class="search-modal-content">
          <div class="search-modal-header">
            <h2 class="search-modal-title">Search Results</h2>
            <button class="search-modal-close" type="button">&times;</button>
          </div>
          <div class="search-modal-body" id="searchModalBody"></div>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
  }

  bindEvents() {
    const closeBtn = this.modal.querySelector('.search-modal-close');
    closeBtn.addEventListener('click', () => this.close());

    if (this.searchInput) {
      this.searchInput.addEventListener('keypress', e => {
        if (e.key === 'Enter') {
          const term = this.searchInput.value.trim();
          if (term) this.performSearch(term);
        }
      });
      const icon = this.searchInput.parentElement?.querySelector('svg');
      icon?.addEventListener('click', () => {
        const term = this.searchInput.value.trim();
        if (term) this.performSearch(term);
      });
    }

    this.modal.addEventListener('click', e => {
      if (e.target === this.modal) this.close();
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && this.isOpen()) this.close();
    });
  }

  async performSearch(term) {
    if (this.isSearching) return;
    this.isSearching = true;
    this.currentSearchTerm = term;
    this.showLoading();
    this.open();
    try {
      const res = await apiGet(`search_items.php?q=${encodeURIComponent(term)}`);
      const data = await res.json();
      if (data.success) this.displayResults(data); else this.displayError(data.message || 'Search failed');
    } catch (e) {
      console.error('[Search] error', e);
      this.displayError('Error searching, please try again.');
    } finally {
      this.isSearching = false;
    }
  }

  showLoading() {
    const body = document.getElementById('searchModalBody');
    body.innerHTML = `<div class="search-loading"><div class="spinner"></div><p>Searching for "${this.currentSearchTerm}"...</p></div>`;
  }

  displayResults(data) {
    this.currentResults = data.results || [];
    const body = document.getElementById('searchModalBody');
    if (!data.results.length) {
      body.innerHTML = `<div class="search-no-results"><div class="search-no-results-icon">🔍</div><h3>No results found</h3><p>We couldn't find anything for "${data.search_term}"</p></div>`;
      return;
    }
    let html = `<div class="search-results-header">Found <strong>${data.count}</strong> result${data.count !== 1 ? 's' : ''} for "${data.search_term}"</div><div class="search-results-grid">`;
    data.results.forEach(item => {
      const stockClass = item.in_stock ? 'in-stock' : 'out-of-stock';
      const disabled = !item.in_stock ? 'disabled' : '';
      html += `<div class="search-result-item"><div class="search-result-clickable" data-sku="${item.sku}"><img src="${item.image_url}" alt="${item.name}" class="search-result-image" data-fallback-src="/images/items/placeholder.webp"/><div class="search-result-content"><h3>${item.name}</h3><p class="${stockClass}">${item.stock_status}</p><span>${item.formatted_price}</span></div></div><div class="search-result-actions"><button class="search-add-to-cart-btn" data-sku="${item.sku}" ${disabled}>${item.in_stock ? 'Add to Cart' : 'Out of Stock'}</button></div></div>`;
    });
    html += '</div>';
    body.innerHTML = html;
    // attach events
    body.querySelectorAll('.search-result-clickable').forEach(el => el.addEventListener('click', () => this.viewItemDetails(el.dataset.sku)));
    body.querySelectorAll('.search-add-to-cart-btn').forEach(btn => btn.addEventListener('click', e => this.addToCart(btn.dataset.sku, e)));
  }

  displayError(msg) {
    const body = document.getElementById('searchModalBody');
    body.innerHTML = `<div class="search-error"><p>${msg}</p></div>`;
  }

  addToCart(sku, evt) {
    evt.stopPropagation();
    const item = this.currentResults.find(i => i.sku === sku);
    if (!item) return;
    // Unified payload for both cart systems
    const payload = { sku: item.sku, name: item.name, price: parseFloat(item.price), image: item.image_url, quantity: 1 };

    // Prefer the unified CartSystem if available
    if (window.WF_Cart && typeof window.WF_Cart.addItem === 'function') {
      window.WF_Cart.addItem(payload);
      return;
    }

    // Fallback to legacy singleton if present
    if (window.cart && typeof window.cart.add === 'function') {
      window.cart.add(payload, 1);
      // Manually emit a cartUpdated event so listeners (e.g., header, modal) refresh
      try {
        window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { action: 'add', state: (window.cart.getState ? window.cart.getState() : undefined) } }));
      } catch (_) {}
    }
  }

  viewItemDetails(sku) {
    this.close();
    const currentPage = new URLSearchParams(window.location.search).get('page');
    if (currentPage === 'shop' && typeof window.showItemDetails === 'function') {
      window.showItemDetails(sku);
    } else {
      window.showGlobalItemModal?.(sku);
    }
  }

  open() {
    // Show modal and apply standardized scroll lock
    this.modal.classList.remove('hidden');
    this.modal.classList.add('show');
    WFModals?.lockScroll?.();
  }
  close() {
    // Hide modal and remove scroll lock only if no other modals are open
    this.modal.classList.add('hidden');
    this.modal.classList.remove('show');
    WFModals?.unlockScrollIfNoneOpen?.();
  }
  isOpen() { return !this.modal.classList.contains('hidden') && this.modal.classList.contains('show'); }
}

// Auto-init where header search exists
if (document.getElementById('headerSearchInput')) {
  window.searchModal = new SearchModal();
}

// Legacy export
window.SearchModal = SearchModal;

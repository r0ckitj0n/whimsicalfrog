// src/ui/search-modal.ts
// ES module conversion of legacy search.js.
import { ApiClient } from '../core/ApiClient.js';
import { cart as cartStore } from '../commerce/cart-system.js';
import { PAGE } from '../core/constants.js';

interface SearchResult {
  sku: string;
  name: string;
  price: string | number;
  formatted_price: string;
  image_url: string;
  in_stock: boolean;
  stock_status: string;
}

interface SearchResponse {
  success: boolean;
  results: SearchResult[];
  count: number;
  search_term: string;
  message?: string;
}

class SearchModal {
  private modal: HTMLElement | null = null;
  private searchInput: HTMLInputElement | null = null;
  private isSearching = false;
  private currentSearchTerm = '';
  private currentResults: SearchResult[] = [];

  constructor() {
    this.init();
  }

  init(): void {
    this.createModalHTML();
    this.modal = document.getElementById('searchModal');
    this.searchInput = document.getElementById('headerSearchInput') as HTMLInputElement;
    this.bindEvents();
  }

  private createModalHTML(): void {
    if (document.getElementById('searchModal')) return; // prevent duplicates
    const html = `<div id="searchModal" class="wf-search-modal hidden">
        <div class="search-modal-content">
          <div class="search-modal-header">
            <h2 class="search-modal-title">Search Results</h2>
            <button class="admin-action-btn btn-icon--close search-modal-close" type="button" aria-label="Close"></button>
          </div>
          <div class="search-modal-body" id="searchModalBody"></div>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
  }

  private bindEvents(): void {
    if (!this.modal) return;
    const closeBtn = this.modal.querySelector('.search-modal-close');
    closeBtn?.addEventListener('click', () => this.close());

    if (this.searchInput) {
      this.searchInput.addEventListener('keypress', (e: KeyboardEvent) => {
        if (e.key === 'Enter') {
          const term = this.searchInput?.value.trim();
          if (term) this.performSearch(term);
        }
      });
      const icon = this.searchInput.parentElement?.parentElement?.querySelector('.btn-icon--search');
      icon?.addEventListener('click', () => {
        const term = this.searchInput?.value.trim();
        if (term) this.performSearch(term);
      });
    }

    this.modal.addEventListener('click', (e: MouseEvent) => {
      if (e.target === this.modal) this.close();
    });
    document.addEventListener('keydown', (e: KeyboardEvent) => {
      if (e.key === 'Escape' && this.isOpen()) this.close();
    });
  }

  async performSearch(term: string): Promise<void> {
    if (this.isSearching) return;
    this.isSearching = true;
    this.currentSearchTerm = term;
    this.showLoading();
    this.open();
    try {
      const data = await ApiClient.get(`search_items.php?q=${encodeURIComponent(term)}`) as SearchResponse;
      if (data.success) {
        this.displayResults(data);
      } else {
        this.displayError(data.message || 'Search failed');
      }
    } catch (e) {
      console.error('[Search] error', e);
      this.displayError('Error searching, please try again.');
    } finally {
      this.isSearching = false;
    }
  }

  private showLoading(): void {
    const body = document.getElementById('searchModalBody');
    if (body) {
      body.innerHTML = `<div class="search-loading"><div class="spinner"></div><p>Searching for "${this.currentSearchTerm}"...</p></div>`;
    }
  }

  private displayResults(data: SearchResponse): void {
    this.currentResults = data.results || [];
    const body = document.getElementById('searchModalBody');
    if (!body) return;

    if (!data.results.length) {
      body.innerHTML = `<div class="search-no-results"><div class="search-no-results-icon">🔍</div><h3>No results found</h3><p>We couldn't find anything for "${data.search_term}"</p></div>`;
      return;
    }
    let html = `<div class="search-results-header">Found <strong>${data.count}</strong> result${data.count !== 1 ? 's' : ''} for "${data.search_term}"</div><div class="search-results-grid">`;
    data.results.forEach(item => {
      const stockClass = item.in_stock ? 'in-stock' : 'out-of-stock';
      const disabled = !item.in_stock ? 'disabled' : '';
      html += `<div class="search-result-item"><div class="search-result-clickable" data-sku="${item.sku}"><img src="${item.image_url}" alt="${item.name}" class="search-result-image" data-fallback-src="/images/items/placeholder.webp" loading="lazy"/><div class="search-result-content"><h3>${item.name}</h3><p class="${stockClass}">${item.stock_status}</p><span>${item.formatted_price}</span></div></div><div class="search-result-actions"><button class="search-add-to-cart-btn btn btn-sm" data-sku="${item.sku}" ${disabled}>${item.in_stock ? 'Add to Cart' : 'Out of Stock'}</button></div></div>`;
    });
    html += '</div>';
    body.innerHTML = html;

    // attach events
    body.querySelectorAll('.search-result-clickable').forEach(el => {
      el.addEventListener('click', () => {
        const sku = (el as HTMLElement).dataset.sku;
        if (sku) this.viewItemDetails(sku);
      });
    });
    body.querySelectorAll('.search-add-to-cart-btn').forEach(btn => {
      btn.addEventListener('click', (e: Event) => {
        const sku = (btn as HTMLElement).dataset.sku;
        if (sku) this.addToCart(sku, e);
      });
    });
  }

  private displayError(msg: string): void {
    const body = document.getElementById('searchModalBody');
    if (body) {
      body.innerHTML = `<div class="search-error"><p>${msg}</p></div>`;
    }
  }

  private addToCart(sku: string, evt: Event): void {
    evt.stopPropagation();
    const item = this.currentResults.find(i => i.sku === sku);
    if (!item) return;
    const payload = { sku: item.sku, name: item.name, price: parseFloat(String(item.price)), image: item.image_url, quantity: 1 };

    if (cartStore && typeof cartStore.add === 'function') {
      cartStore.add(payload, 1);
      return;
    }
    if (window.cart && typeof window.cart.add === 'function') {
      window.cart.add(payload, 1);
      try {
        window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { action: 'add', state: (window.cart.getState ? window.cart.getState() : undefined) } }));
      } catch { /* Cart event dispatch failed */ }
    }
  }

  private viewItemDetails(sku: string): void {
    this.close();
    const current_page = new URLSearchParams(window.location.search).get('page');
    if (current_page === PAGE.SHOP && typeof window.showItemDetailsModal === 'function') {
      window.showItemDetailsModal(sku);
    } else {
      window.showGlobalItemModal?.(sku);
    }
  }

  open(): void {
    if (!this.modal) return;
    this.modal.classList.remove('hidden');
    this.modal.classList.add('show');
    window.WFModals?.lockScroll?.();
  }

  close(): void {
    if (!this.modal) return;
    this.modal.classList.add('hidden');
    this.modal.classList.remove('show');
    window.WFModals?.unlockScrollIfNoneOpen?.();
  }

  isOpen(): boolean {
    return !!this.modal && !this.modal.classList.contains('hidden') && this.modal.classList.contains('show');
  }
}

// Auto-init where header search exists
if (typeof document !== 'undefined' && document.getElementById('headerSearchInput')) {
  window.searchModal = new SearchModal();
}

// Legacy export
if (typeof window !== 'undefined') {
  window.SearchModal = SearchModal;
}

export default SearchModal;

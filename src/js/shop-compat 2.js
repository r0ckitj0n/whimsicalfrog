// Shop page lightweight compatibility bindings
// Restores modal open on product cards and category filter without WF core dependency.

(function initShopCompat() {
  if (typeof document === 'undefined') return;
  const onReady = (fn) => {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
    else fn();
  };

  onReady(() => {
    const grid = document.getElementById('productsGrid');
    if (!grid) return; // Not on shop page

    const cards = Array.from(document.querySelectorAll('#productsGrid .product-card'));
    const addBtns = Array.from(document.querySelectorAll('#productsGrid .add-to-cart-btn'));
    const categoryButtons = Array.from(document.querySelectorAll('.category-navigation .category-btn'));

    function openModalForCard(card) {
      if (!card) return;
      const sku = (card.dataset && card.dataset.sku) || '';
      if (!sku) return;

      const name = (card.dataset && card.dataset.name) || (card.querySelector('.product-title')?.textContent || '').trim();
      const priceStr = (card.dataset && card.dataset.price) || '';
      const price = Number.parseFloat(priceStr) || 0;
      const stockEl = card.querySelector('.product-stock');
      const stockLevel = stockEl ? Number.parseInt(stockEl.getAttribute('data-stock') || '0', 10) : 0;
      const imgEl = card.querySelector('img.product-image');
      const image = imgEl ? imgEl.getAttribute('src') : '';
      const fullDescEl = card.querySelector('.description-text-full');
      const description = fullDescEl ? fullDescEl.textContent.trim() : '';

      const item = { sku, name, price, retailPrice: price, currentPrice: price, stockLevel, image, description };

      if (window.WhimsicalFrog?.GlobalModal?.show) {
        window.WhimsicalFrog.GlobalModal.show(sku, item);
      } else if (typeof window.showGlobalItemModal === 'function') {
        window.showGlobalItemModal(sku, item);
      } else if (typeof window.showDetailedModal === 'function') {
        window.showDetailedModal(sku, item);
      } else {
        console.warn('[shop-compat] Modal system not available');
      }
    }

    // Bind Add to Cart buttons to open modal (quantity/options)
    addBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const card = btn.closest('.product-card');
        openModalForCard(card);
      });
    });

    // Card click opens modal unless clicking interactive areas
    cards.forEach(card => {
      card.addEventListener('click', (e) => {
        if (e.target.closest('.product-more-toggle, .add-to-cart-btn')) return;
        openModalForCard(card);
      });
    });

    // Category filter buttons
    function filterByCategory(category) {
      cards.forEach(card => {
        const cardCategory = card.dataset.category;
        const isVisible = (category === 'all' || cardCategory === category);
        card.classList.toggle('hidden', !isVisible);
      });
    }

    categoryButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const category = btn.dataset.category;
        categoryButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filterByCategory(category);
      });
    });
  });
})();

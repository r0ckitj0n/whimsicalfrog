// Public page module: cart
// Handles rendering and close behaviors for the cart overlay

console.log('[Page] cart-page.js loaded');

function ready(fn) {
  if (document.readyState !== 'loading') fn();
  else document.addEventListener('DOMContentLoaded', fn, { once: true });
}

ready(() => {
  const init = async () => {
    const api = (window.cart && typeof window.cart.renderCart === 'function')
      ? window.cart
      : (window.WF_Cart && typeof window.WF_Cart.renderCart === 'function')
        ? window.WF_Cart
        : null;

    if (!api) {
      // Wait for cart system to be available
      setTimeout(init, 100);
      return;
    }

    try {
      await api.renderCart();
    } catch (e) {
      console.error('[CartPage] Error rendering cart initially', e);
    }

    // Re-render on cart updates
    window.addEventListener('cartUpdated', async () => {
      try {
        await api.renderCart();
      } catch (e) {
        console.error('[CartPage] Error re-rendering cart on update', e);
      }
    });

    // Overlay click to close
    const cartOverlay = document.getElementById('cartPage');
    const cartModal = cartOverlay?.querySelector('div');

    if (cartOverlay) {
      cartOverlay.addEventListener('click', (e) => {
        if (e.target === cartOverlay) {
          window.location.href = '/?page=room_main';
        }
      });

      if (cartModal) {
        cartModal.addEventListener('click', (e) => e.stopPropagation());
      }
    }

    // Escape key to close
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' || e.keyCode === 27) {
        const cartPage = document.getElementById('cartPage');
        if (cartPage && !cartPage.classList.contains('hidden')) {
          window.location.href = '/?page=room_main';
        }
      }
    });
  };

  init();
});

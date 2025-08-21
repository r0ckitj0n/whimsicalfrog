<?php
// Minimal cart page: auto-open the global cart modal on load.
?>
<div class="p-6 text-center text-gray-600">Opening your cart…</div>
<script>
  (function(){
    function openCart() {
      if (window.WF_CartModal && typeof window.WF_CartModal.open === 'function') {
        window.WF_CartModal.open();
      } else if (window.openCartModal) {
        window.openCartModal();
      } else {
        // Retry shortly; modules may still be initializing
        setTimeout(openCart, 100);
      }
    }
    if (document.readyState !== 'loading') openCart();
    else document.addEventListener('DOMContentLoaded', openCart, { once: true });
  })();
  // Optional: if user closes the modal on the /cart route, navigate them back to shopping
  window.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && window.location.pathname.replace(/\/?$/, '/') === '/cart/') {
      // You can change this to '/shop' or '/' depending on your preferred landing
      // window.location.href = '/';
    }
  });
  // Ensure body has a minimal height so the page doesn't look empty behind the modal
  document.documentElement.style.minHeight = '100%';
  document.body.style.minHeight = '100%';
  document.body.style.backgroundColor = 'transparent';
  document.body.style.margin = '0';
  document.body.style.padding = '0';
  // No additional content needed – header/footer are already included by index.php
</script>

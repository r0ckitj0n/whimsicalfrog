// Centralized image fallback handling
// Handles images with attributes:
// - data-fallback-src: swap to this URL on error (one-time)
// - data-fallback="placeholder": hide image and append a generic placeholder block
// - data-fallback="thumbnail": hide image and replace parent content with a thumbnail placeholder

(function setupImageFallback() {
  function onError(e) {
    const img = e.target;
    if (!(img instanceof HTMLImageElement)) return;

    // Prevent infinite loop
    img.removeEventListener('error', onError, true);

    const fallbackSrc = img.getAttribute('data-fallback-src');
    const behavior = img.getAttribute('data-fallback');

    if (fallbackSrc && img.src !== fallbackSrc) {
      img.src = fallbackSrc;
      return;
    }

    if (behavior === 'placeholder') {
      img.classList.add('hidden');
      const ph = document.createElement('div');
      ph.className = 'width_100 height_100 display_flex flex_col align_center justify_center bg_f8f9fa color_6b7280 border_radius_normal';
      ph.innerHTML = "<div class='font_size_3rem margin_bottom_10 opacity_07'>📷</div><div class='font_size_0_9 font_weight_500'>Image Not Found</div>";
      if (img.parentElement) img.parentElement.appendChild(ph);
      return;
    }

    if (behavior === 'thumbnail') {
      img.classList.add('hidden');
      const ph = document.createElement('div');
      ph.className = 'width_100 height_100 display_flex align_center justify_center bg_f8f9fa color_6b7280 font_size_1_5rem';
      ph.textContent = '📷';
      if (img.parentElement) img.parentElement.innerHTML = '';
      if (img.parentElement) img.parentElement.appendChild(ph);
      return;
    }

    // Default: just hide if nothing else specified
    img.classList.add('hidden');
  }

  function bind(root = document) {
    root.addEventListener('error', onError, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => bind(), { once: true });
  } else {
    bind();
  }
})();

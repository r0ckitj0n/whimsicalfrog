// Image Carousel Module (Vite-managed)
// Provides global functions changeSlide() and goToSlide() for compatibility
// Initializes all carousels rendered by components/image_carousel.php

(function initImageCarouselModule() {
  if (window.__WF_IMAGE_CAROUSEL_INIT__) return;
  window.__WF_IMAGE_CAROUSEL_INIT__ = true;

  window.carouselStates = window.carouselStates || {};

  function getOrInitState(container) {
    if (!container || !container.id) return null;
    const id = container.id;
    if (!window.carouselStates[id]) {
      const slides = container.querySelectorAll('.carousel-slide');
      const autoplay = (container.dataset.autoplay || '').toString() === 'true';
      window.carouselStates[id] = {
        currentSlide: 0,
        totalSlides: slides.length || 0,
        autoplay,
        autoplayInterval: null,
      };

      // Setup autoplay controls
      if (autoplay && (slides.length || 0) > 1) {
        startAutoplay(id);
        container.addEventListener('mouseenter', () => stopAutoplay(id));
        container.addEventListener('mouseleave', () => startAutoplay(id));
      }
    }
    return window.carouselStates[id];
  }

  function startAutoplay(id) {
    const st = window.carouselStates[id];
    if (!st) return;
    stopAutoplay(id);
    st.autoplayInterval = setInterval(() => changeSlide(id, 1), 3000);
  }

  function stopAutoplay(id) {
    const st = window.carouselStates[id];
    if (st && st.autoplayInterval) {
      clearInterval(st.autoplayInterval);
      st.autoplayInterval = null;
    }
  }

  function getContainer(id) {
    const el = document.getElementById(id);
    if (!el) console.warn('[image-carousel] Container not found for id:', id);
    return el;
  }

  function updateUI(carousel, slideIndex) {
    if (!carousel) return;

    // Slides
    const slides = carousel.querySelectorAll('.carousel-slide');
    slides.forEach((slide, idx) => {
      slide.classList.toggle('carousel-image-active', idx === slideIndex);
      slide.classList.toggle('carousel-image-inactive', idx !== slideIndex);
    });

    // Thumbnails
    const thumbs = carousel.querySelectorAll('.carousel-thumbnail');
    thumbs.forEach((thumb, idx) => {
      thumb.classList.toggle('carousel-thumbnail-active', idx === slideIndex);
      thumb.classList.toggle('carousel_border_color_active', idx === slideIndex);
      thumb.classList.toggle('carousel_border_color_inactive', idx !== slideIndex);
    });

    // Indicators
    const indicators = carousel.querySelectorAll('.carousel-indicator');
    indicators.forEach((ind, idx) => {
      ind.classList.toggle('active', idx === slideIndex);
      ind.classList.toggle('carousel_indicator_bg_active', idx === slideIndex);
      ind.classList.toggle('carousel_indicator_bg_inactive', idx !== slideIndex);
    });
  }

  function changeSlide(id, direction) {
    const st = window.carouselStates[id] || getOrInitState(getContainer(id));
    const c = getContainer(id);
    if (!st || !c) return;
    const next = (st.currentSlide + direction + st.totalSlides) % st.totalSlides;
    goToSlide(id, next);
  }

  function goToSlide(id, index) {
    const st = window.carouselStates[id] || getOrInitState(getContainer(id));
    const c = getContainer(id);
    if (!st || !c) return;
    const bounded = Math.max(0, Math.min(index, st.totalSlides - 1));
    updateUI(c, bounded);
    st.currentSlide = bounded;
  }

  // Expose global functions for compatibility with central-functions and data-action handlers
  window.changeSlide = changeSlide;
  window.goToSlide = goToSlide;

  function initializeAll() {
    document.querySelectorAll('.image-carousel-container.carousel-container[id]').forEach((container) => {
      getOrInitState(container);
    });
    console.log('[image-carousel] Initialized');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAll, { once: true });
  } else {
    initializeAll();
  }
})();

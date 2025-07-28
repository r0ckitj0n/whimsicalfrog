/**
 * WhimsicalFrog Main Application Module
 * Lightweight wrapper that depends on CartSystem and handles page-level UI.
 */
(function () {
  'use strict';

  if (!window.WhimsicalFrog || typeof window.WhimsicalFrog.registerModule !== 'function') {
    console.error('[MainApplication] WhimsicalFrog Core not found.');
    return;
  }

  const mainAppModule = {
  name: 'MainApplication',
  dependencies: [],

  init(WF) {
    this.WF = WF;
    this.ensureSingleNavigation();
    this.updateMainCartCounter();
    this.setupEventListeners();
    this.handleLoginForm();
    this.WF.log('Main Application module initialized.');
  },

  ensureSingleNavigation() {
    const navs = document.querySelectorAll('nav.main-nav');
    if (navs.length > 1) {
      this.WF.log(`Found ${navs.length} navigation elements, removing duplicates...`);
      navs.forEach((el, idx) => { if (idx > 0) el.remove(); });
    }
  },

  updateMainCartCounter() {
    const el = document.getElementById('cartCount');
    if (window.cart && el) {
      el.textContent = `${window.cart.getCount()} items`;
    }
  },

  setupEventListeners() {
    this.WF.eventBus.on('cartUpdated', () => this.updateMainCartCounter());
    if (this.WF.ready) this.WF.ready(() => this.updateMainCartCounter());
  },

  handleLoginForm() {
    const form = document.getElementById('loginForm');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = document.getElementById('username').value;
      const password = document.getElementById('password').value;
      const errorMessage = document.getElementById('errorMessage');
      if (errorMessage) errorMessage.classList.add('hidden');
      try {
        const data = await this.WF.api.post('/functions/process_login.php', { username, password });
        sessionStorage.setItem('user', JSON.stringify(data.user || data));
        if (data.redirectUrl) {
          window.location.href = data.redirectUrl;
        } else if (localStorage.getItem('pendingCheckout') === 'true') {
          localStorage.removeItem('pendingCheckout');
          window.location.href = '/?page=cart';
        } else {
          window.location.href = data.role === 'Admin' ? '/?page=admin' : '/?page=room_main';
        }
      } catch (err) {
        if (errorMessage) {
          errorMessage.textContent = err.message;
          errorMessage.classList.remove('hidden');
        }
      }
    });
  },

  async loadModalBackground(roomType) {
    if (!roomType) {
      this.WF.log('[MainApplication] No roomType provided for modal background.', 'warn');
      return;
    }
    try {
      const data = await this.WF.api.get(`/api/get_background.php?room_type=${roomType}`);
      if (data && data.success && data.background) {
        const bg = data.background;
        const supportsWebP = document.documentElement.classList.contains('webp');
        let filename = supportsWebP && bg.webp_filename ? bg.webp_filename : bg.image_filename;
        // Ensure filename does not already include the backgrounds/ prefix
        if (!filename.startsWith('backgrounds/')) {
          filename = `backgrounds/${filename}`;
        }
        const imageUrl = `/images/${filename}?v=${Date.now()}`;

        // Set background on modal overlay (this provides the backdrop)
        const overlay = document.querySelector('.room-modal-overlay');

        if (overlay) {
          // Set CSS variable for background image to work with room-modal.css
          overlay.style.setProperty('--dynamic-bg-url', `url('${imageUrl}')`);
          // Also set the background directly as a fallback to ensure it works
          overlay.style.backgroundImage = `linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('${imageUrl}')`;
          overlay.style.backgroundSize = 'cover';
          overlay.style.backgroundPosition = 'center';
          overlay.style.backgroundRepeat = 'no-repeat';
          this.WF.log(`[MainApplication] Modal overlay background loaded for ${roomType}: ${imageUrl}`);
        } else {
          this.WF.log('[MainApplication] Modal overlay not found when setting background', 'warn');
        }

        // The iframe content will handle its own background loading via the embedded script
      } else {
        this.WF.log(`[MainApplication] No background data found for ${roomType}`, 'warn');
      }
    } catch (err) {
      this.WF.log(`[MainApplication] Error loading modal background for ${roomType}: ${err.message}`, 'error');
    }
  },

  resetToPageBackground() {
    const overlay = document.querySelector('.room-modal-overlay');

    if (overlay) {
      overlay.style.removeProperty('--dynamic-bg-url');
      overlay.style.removeProperty('background-image');
      this.WF.log('[MainApplication] Modal overlay background reset.');
    }
  }
};

  // Register module once
  window.WhimsicalFrog.registerModule(mainAppModule.name, mainAppModule);
})();
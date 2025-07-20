// src/core/utils.js - ES module replacement for legacy js/utils.js
// Provides DOM helper utilities and re-exports ApiClient so legacy code can reference window.Utils.ApiClient.

import { apiGet, apiPost, apiPut, apiDelete, uploadFile } from './apiClient.js';

/**
 * DOM utility helpers used across the frontend.
 * Note: Only a subset of the huge legacy DOMUtils is included—expand as needed.
 */
export class DOMUtils {
  /**
   * Safely set innerHTML with optional loading placeholder.
   * @param {HTMLElement} el
   * @param {string} html
   * @param {boolean} loading
   */
  static setContent(el, html, loading = false) {
    if (!el) return;
    if (loading) {
      el.innerHTML = '<div class="wf-loading">Loading...</div>';
      requestAnimationFrame(() => { el.innerHTML = html; });
    } else {
      el.innerHTML = html;
    }
  }

  /**
   * Create a simple toast notification.
   * @param {string} msg
   * @param {'success'|'error'|'info'} type
   * @param {number} dur
   */
  static showToast(msg, type = 'info', dur = 3000) {
    const toast = document.createElement('div');
    toast.className = `wf-toast wf-toast-${type}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), dur);
  }
}

// Build a compatibility facade similar to the original Utils global.
export const Utils = {
  apiGet,
  apiPost,
  apiPut,
  apiDelete,
  uploadFile,
  DOMUtils,
};

// Expose for legacy inline scripts still expecting window.Utils
// eslint-disable-next-line no-undef
if (typeof window !== 'undefined') {
  window.Utils = Utils;
}
